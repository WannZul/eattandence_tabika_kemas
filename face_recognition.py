"""Local eAttendance face kiosk agent (Python 3.11).

The public PHP host never opens a webcam. This agent synchronizes authorized private
images, validates each profile, trains LBPH locally, and submits attendance over HTTPS.
"""
from __future__ import annotations

import hashlib
import json
import os
import re
import sys
import time
import uuid
from collections import defaultdict, deque
from datetime import datetime
from pathlib import Path
from urllib.parse import urlparse

import cv2
import mediapipe as mp
import numpy as np
import requests

BASE_DIR = Path(__file__).resolve().parent
CACHE_DIR = Path(os.getenv("KIOSK_CACHE_DIR", BASE_DIR / "kiosk_cache")).resolve()
API_URL = os.getenv("APP_API_URL", "").strip()
API_KEY = os.getenv("KIOSK_API_KEY", "").strip()
KIOSK_ID = os.getenv("KIOSK_ID", "kiosk-local").strip()
CAMERA_INDEX = int(os.getenv("CAMERA_INDEX", "0"))
MATCH_THRESHOLD = float(os.getenv("LBPH_THRESHOLD", "78"))
REQUIRED_MATCHES = int(os.getenv("REQUIRED_MATCHES", "5"))
MATCH_WINDOW_SECONDS = float(os.getenv("MATCH_WINDOW_SECONDS", "2.5"))
COOLDOWN_SECONDS = float(os.getenv("ATTENDANCE_COOLDOWN_SECONDS", "60"))
CAMERA_FAILURE_LIMIT = max(3, int(os.getenv("CAMERA_FAILURE_LIMIT", "10")))
CAMERA_REOPEN_ATTEMPTS = max(1, int(os.getenv("CAMERA_REOPEN_ATTEMPTS", "3")))
CAMERA_FAILURE_BACKOFF = max(0.05, float(os.getenv("CAMERA_FAILURE_BACKOFF", "0.2")))
ALLOW_HTTP = os.getenv("APP_ALLOW_INSECURE_HTTP", "false").lower() in {"1", "true", "yes"}
_INSTANCE_LOCK = None


def acquire_single_instance() -> None:
    """Prevent repeated web-button clicks from opening multiple camera agents."""
    global _INSTANCE_LOCK
    CACHE_DIR.mkdir(parents=True, exist_ok=True, mode=0o700)
    lock_path = CACHE_DIR / "kiosk-instance.lock"
    handle = lock_path.open("a+b")
    try:
        handle.seek(0, os.SEEK_END)
        if handle.tell() == 0:
            handle.write(b"0")
            handle.flush()
        handle.seek(0)
        if os.name == "nt":
            import msvcrt
            msvcrt.locking(handle.fileno(), msvcrt.LK_NBLCK, 1)
        else:
            import fcntl
            fcntl.flock(handle.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
    except (OSError, BlockingIOError) as exc:
        handle.close()
        raise RuntimeError("Kiosk sudah berjalan pada komputer ini. Tutup tetingkap sedia ada sebelum membuka yang baharu.") from exc
    _INSTANCE_LOCK = handle


def fail(message: str) -> None:
    print(f"ERROR: {message}", file=sys.stderr)
    raise SystemExit(1)


def validate_configuration() -> None:
    if not API_URL or not API_KEY:
        fail("APP_API_URL dan KIOSK_API_KEY mesti ditetapkan.")
    parsed = urlparse(API_URL)
    if parsed.scheme != "https" and not (ALLOW_HTTP and parsed.scheme == "http" and parsed.hostname in {"localhost", "127.0.0.1", "::1"}):
        fail("APP_API_URL mesti menggunakan HTTPS. HTTP hanya dibenarkan untuk localhost dengan APP_ALLOW_INSECURE_HTTP=true.")
    if len(API_KEY) < 24:
        fail("KIOSK_API_KEY terlalu pendek (minimum 24 aksara).")


def session() -> requests.Session:
    client = requests.Session()
    client.headers.update({"Authorization": f"Bearer {API_KEY}", "Accept": "application/json", "User-Agent": "eAttendance-Kiosk/2.0"})
    return client


class ApiError(RuntimeError):
    def __init__(self, message: str, status_code: int):
        super().__init__(message)
        self.status_code = status_code


def api_json(client: requests.Session, method: str, **kwargs) -> dict:
    try:
        response = client.request(method, API_URL, timeout=(5, 25), **kwargs)
        try:
            payload = response.json()
        except ValueError as exc:
            raise RuntimeError(f"API mengembalikan respons bukan JSON (HTTP {response.status_code}).") from exc
    except requests.RequestException as exc:
        raise RuntimeError(f"API tidak dapat dihubungi: {exc}") from exc
    if not response.ok:
        raise ApiError(str(payload.get("message", "API mengembalikan ralat.")), response.status_code)
    if not payload.get("success"):
        raise ApiError(str(payload.get("message", "API mengembalikan ralat.")), response.status_code)
    return payload


def sync_dataset(client: requests.Session) -> tuple[list[dict], int]:
    CACHE_DIR.mkdir(parents=True, exist_ok=True, mode=0o700)
    metadata_path = CACHE_DIR / "metadata.json"
    previous = {}
    if metadata_path.exists():
        try:
            previous = json.loads(metadata_path.read_text(encoding="utf-8"))
        except (OSError, ValueError):
            previous = {}
    old_labels = {item["student_id"]: int(item["label"]) for item in previous.get("students", []) if "student_id" in item and "label" in item}
    next_label = max(old_labels.values(), default=-1) + 1
    payload = api_json(client, "GET", params={"action": "dataset"})
    minimum_samples = int(payload.get("minimum_usable_samples", 4))
    if minimum_samples < 1 or minimum_samples > 6:
        raise RuntimeError("Minimum sampel yang diberikan API tidak sah.")
    students: list[dict] = []
    expected_files: set[Path] = {metadata_path}
    for student in payload.get("students", []):
        student_id = str(student.get("student_id", ""))
        fingerprint = str(student.get("dataset_fingerprint", ""))
        if not student_id or re.fullmatch(r"[a-f0-9]{64}", fingerprint) is None:
            continue
        label = old_labels.get(student_id)
        if label is None:
            label, next_label = next_label, next_label + 1
        local_images = []
        for image in student.get("images", []):
            image_id, digest = int(image["id"]), str(image["sha256"])
            destination = CACHE_DIR / f"{image_id}_{digest[:16]}.jpg"
            expected_files.add(destination)
            valid = destination.exists() and hashlib.sha256(destination.read_bytes()).hexdigest() == digest
            if not valid:
                response = client.get(API_URL, params={"action": "image", "id": image_id}, timeout=(5, 30))
                response.raise_for_status()
                content = response.content
                if hashlib.sha256(content).hexdigest() != digest:
                    raise RuntimeError(f"Checksum imej {image_id} tidak sepadan.")
                temp = destination.with_suffix(".tmp")
                temp.write_bytes(content)
                try:
                    temp.chmod(0o600)
                except OSError:
                    pass
                temp.replace(destination)
            local_images.append(str(destination))
        if local_images:
            students.append({
                "student_id": student_id,
                "name": str(student.get("name", student_id)),
                "class": str(student.get("class", "")),
                "label": label,
                "dataset_fingerprint": fingerprint,
                "face_status": str(student.get("face_status", "pending")),
                "images": local_images,
            })
    for candidate in CACHE_DIR.glob("*.jpg"):
        if candidate not in expected_files:
            candidate.unlink(missing_ok=True)
    metadata_path.write_text(json.dumps({"generated_at": payload.get("generated_at"), "minimum_usable_samples": minimum_samples, "students": students}, ensure_ascii=False, indent=2), encoding="utf-8")
    try:
        metadata_path.chmod(0o600)
    except OSError:
        pass
    return students, minimum_samples


def crop_single_face(image: np.ndarray, detector) -> np.ndarray | None:
    detections = detector.process(cv2.cvtColor(image, cv2.COLOR_BGR2RGB)).detections or []
    if len(detections) != 1:
        return None
    box = detections[0].location_data.relative_bounding_box
    height, width = image.shape[:2]
    x, y = int(box.xmin * width), int(box.ymin * height)
    box_width, box_height = int(box.width * width), int(box.height * height)
    padding_x, padding_y = int(box_width * 0.2), int(box_height * 0.2)
    x1, y1 = max(0, x - padding_x), max(0, y - padding_y)
    x2, y2 = min(width, x + box_width + padding_x), min(height, y + box_height + padding_y)
    crop = image[y1:y2, x1:x2]
    if crop.size == 0:
        return None
    return cv2.resize(cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY), (200, 200))


def report_validation(client: requests.Session, student: dict, usable_samples: int, minimum_samples: int) -> None:
    ready = usable_samples >= minimum_samples
    status = "ready" if ready else "invalid"
    message = (
        f"{usable_samples} sampel wajah boleh digunakan (minimum {minimum_samples})."
        if ready
        else f"Hanya {usable_samples} sampel wajah boleh digunakan; minimum {minimum_samples}. Daftar semula imej."
    )
    api_json(client, "POST", json={
        "action": "validation",
        "student_id": student["student_id"],
        "dataset_fingerprint": student["dataset_fingerprint"],
        "status": status,
        "usable_samples": usable_samples,
        "message": message,
    }, headers={"Content-Type": "application/json"})


def train(students: list[dict], detector, client: requests.Session, minimum_samples: int):
    if not hasattr(cv2, "face"):
        fail("opencv-contrib-python diperlukan (modul cv2.face tiada).")
    faces: list[np.ndarray] = []
    labels: list[int] = []
    ready_students: list[dict] = []
    for student in students:
        student_faces = []
        for filename in student["images"]:
            image = cv2.imread(filename)
            if image is None:
                continue
            face = crop_single_face(image, detector)
            if face is not None:
                student_faces.append(face)
        report_validation(client, student, len(student_faces), minimum_samples)
        if len(student_faces) < minimum_samples:
            print(f"Profil tidak sah: {student['student_id']} ({len(student_faces)}/{minimum_samples} sampel).")
            continue
        ready_students.append(student)
        faces.extend(student_faces)
        labels.extend([int(student["label"])] * len(student_faces))
    if not faces:
        raise RuntimeError(f"Tiada profil mempunyai minimum {minimum_samples} sampel wajah boleh guna.")
    recognizer = cv2.face.LBPHFaceRecognizer_create(radius=1, neighbors=8, grid_x=8, grid_y=8)
    recognizer.train(faces, np.asarray(labels, dtype=np.int32))
    label_map = {int(item["label"]): item for item in ready_students}
    print(f"Dataset sedia: {len(ready_students)} murid, {len(faces)} imej wajah. {len(students) - len(ready_students)} profil dikecualikan.")
    return recognizer, label_map


def load_model(client: requests.Session, detector):
    students, minimum_samples = sync_dataset(client)
    recognizer, label_map = train(students, detector, client, minimum_samples)
    return students, label_map, recognizer


def submit_attendance(client: requests.Session, student: dict, confidence: float) -> str:
    payload = {
        "action": "attendance",
        "event_id": str(uuid.uuid4()),
        "student_id": student["student_id"],
        "kiosk_id": KIOSK_ID,
        "confidence": round(confidence, 2),
        "observed_at": datetime.now().astimezone().isoformat(timespec="seconds"),
        "dataset_fingerprint": student["dataset_fingerprint"],
    }
    last_error: RuntimeError | None = None
    for attempt in range(2):
        try:
            result = api_json(client, "POST", json=payload, headers={"Content-Type": "application/json"})
            return result.get("message", "Kehadiran direkod.")
        except ApiError as exc:
            if exc.status_code == 409:
                raise RuntimeError(f"{exc} Tekan R untuk sync dataset sebelum cuba lagi.") from exc
            last_error = exc
            if attempt == 0:
                time.sleep(0.8)
        except RuntimeError as exc:
            last_error = exc
            if attempt == 0:
                time.sleep(0.8)
    raise last_error or RuntimeError("Kehadiran tidak dapat dihantar.")


def open_camera():
    capture = cv2.VideoCapture(CAMERA_INDEX, cv2.CAP_DSHOW if os.name == "nt" else cv2.CAP_ANY)
    capture.set(cv2.CAP_PROP_FRAME_WIDTH, 1280)
    capture.set(cv2.CAP_PROP_FRAME_HEIGHT, 720)
    return capture


def failure_frame(message: str) -> np.ndarray:
    frame = np.zeros((720, 1280, 3), dtype=np.uint8)
    cv2.putText(frame, message, (50, 330), cv2.FONT_HERSHEY_SIMPLEX, 0.9, (80, 190, 255), 2)
    cv2.putText(frame, "Q: keluar · R: sync dataset", (50, 390), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
    return frame


def main() -> None:
    validate_configuration()
    acquire_single_instance()
    client = session()
    health = api_json(client, "GET", params={"action": "health"})
    print(f"API aktif: {health.get('server_time')} ({health.get('timezone')})")
    detector = mp.solutions.face_detection.FaceDetection(model_selection=0, min_detection_confidence=0.65)
    cap = None
    try:
        students, label_map, recognizer = load_model(client, detector)
        cap = open_camera()
        if not cap.isOpened():
            raise RuntimeError(f"Kamera indeks {CAMERA_INDEX} tidak dapat dibuka.")
        histories: dict[int, deque[float]] = defaultdict(deque)
        cooldowns: dict[str, float] = {}
        banner, banner_until = "Kiosk sedia", time.monotonic() + 3
        read_failures = 0
        reopen_attempts = 0
        print("Imbasan aktif. Tekan Q untuk keluar atau R untuk menyegerak semula dataset.")
        while True:
            ok, frame = cap.read()
            if not ok:
                read_failures += 1
                status_text = f"Bacaan kamera gagal ({read_failures}/{CAMERA_FAILURE_LIMIT})"
                cv2.imshow("eAttendance TABIKA KEMAS - Kiosk", failure_frame(status_text))
                key = cv2.waitKey(max(1, int(CAMERA_FAILURE_BACKOFF * 1000))) & 0xFF
                if key == ord("q"):
                    break
                if key == ord("r"):
                    try:
                        students, label_map, recognizer = load_model(client, detector)
                        histories.clear()
                        print("Dataset berjaya disegerakkan semasa kamera dipulihkan.")
                    except (RuntimeError, requests.RequestException, OSError) as exc:
                        print(f"Sync gagal: {exc}", file=sys.stderr)
                if read_failures < CAMERA_FAILURE_LIMIT:
                    continue
                cap.release()
                reopen_attempts += 1
                if reopen_attempts > CAMERA_REOPEN_ATTEMPTS:
                    raise RuntimeError(f"Kamera indeks {CAMERA_INDEX} gagal selepas {CAMERA_REOPEN_ATTEMPTS} percubaan buka semula. Semak sambungan dan CAMERA_INDEX.")
                cv2.imshow("eAttendance TABIKA KEMAS - Kiosk", failure_frame(f"Membuka semula kamera ({reopen_attempts}/{CAMERA_REOPEN_ATTEMPTS})..."))
                cv2.waitKey(max(1, int(CAMERA_FAILURE_BACKOFF * 2000)))
                cap = open_camera()
                read_failures = 0
                continue

            read_failures = 0
            reopen_attempts = 0
            frame = cv2.flip(frame, 1)
            rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
            detections = detector.process(rgb).detections or []
            now = time.monotonic()
            for detection in detections:
                box = detection.location_data.relative_bounding_box
                height, width = frame.shape[:2]
                x, y = int(box.xmin * width), int(box.ymin * height)
                box_width, box_height = int(box.width * width), int(box.height * height)
                padding_x, padding_y = int(box_width * 0.2), int(box_height * 0.2)
                x1, y1 = max(0, x - padding_x), max(0, y - padding_y)
                x2, y2 = min(width, x + box_width + padding_x), min(height, y + box_height + padding_y)
                crop = frame[y1:y2, x1:x2]
                if crop.size == 0:
                    continue
                gray = cv2.resize(cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY), (200, 200))
                label, confidence = recognizer.predict(gray)
                student = label_map.get(int(label))
                recognized = student is not None and confidence <= MATCH_THRESHOLD
                color = (32, 170, 95) if recognized else (30, 50, 220)
                caption = f"{student['name']} {confidence:.1f}" if recognized else "TIDAK DIKENALI"
                cv2.rectangle(frame, (x1, y1), (x2, y2), color, 2)
                cv2.putText(frame, caption, (x1, max(25, y1 - 8)), cv2.FONT_HERSHEY_SIMPLEX, 0.65, color, 2)
                if not recognized:
                    continue
                history = histories[int(label)]
                history.append(now)
                while history and history[0] < now - MATCH_WINDOW_SECONDS:
                    history.popleft()
                student_id = student["student_id"]
                if len(history) >= REQUIRED_MATCHES and cooldowns.get(student_id, 0) <= now:
                    try:
                        result = submit_attendance(client, student, confidence)
                        banner = f"{student['name']}: {result}"
                        banner_until = now + 4
                        cooldowns[student_id] = now + COOLDOWN_SECONDS
                    except RuntimeError as exc:
                        banner = f"Gagal {student['name']}: {exc}"
                        banner_until = now + 5
                        cooldowns[student_id] = now + 8
                    history.clear()
            if now < banner_until:
                cv2.rectangle(frame, (0, 0), (frame.shape[1], 50), (15, 70, 50), -1)
                cv2.putText(frame, banner, (15, 33), cv2.FONT_HERSHEY_SIMPLEX, 0.75, (255, 255, 255), 2)
            cv2.putText(frame, "Q: keluar · R: sync", (15, frame.shape[0] - 15), cv2.FONT_HERSHEY_SIMPLEX, 0.55, (255, 255, 255), 1)
            cv2.imshow("eAttendance TABIKA KEMAS - Kiosk", frame)
            key = cv2.waitKey(1) & 0xFF
            if key == ord("q"):
                break
            if key == ord("r"):
                try:
                    refreshed, refreshed_map, refreshed_recognizer = load_model(client, detector)
                    students, label_map, recognizer = refreshed, refreshed_map, refreshed_recognizer
                    histories.clear()
                    banner, banner_until = "Dataset berjaya disegerakkan", time.monotonic() + 4
                except (RuntimeError, requests.RequestException, OSError) as exc:
                    banner, banner_until = f"Sync gagal: {exc}", time.monotonic() + 5
    finally:
        if cap is not None:
            cap.release()
        cv2.destroyAllWindows()
        detector.close()


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("Kiosk dihentikan.")
    except (RuntimeError, requests.RequestException, OSError) as exc:
        fail(str(exc))
