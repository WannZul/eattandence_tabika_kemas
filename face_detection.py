import cv2
import mediapipe as mp

print("OpenCV:", cv2.__version__)
print("MediaPipe:", mp.__version__)

# ==========================================
# MEDIAPIPE FACE DETECTION
# ==========================================

mp_face_detection = mp.solutions.face_detection
mp_drawing = mp.solutions.drawing_utils

face_detection = mp_face_detection.FaceDetection(
    model_selection=0,
    min_detection_confidence=0.7
)

# ==========================================
# WEBCAM RAPOO
# ==========================================

cap = cv2.VideoCapture(1)

if not cap.isOpened():
    print("Camera Rapoo tidak dapat dibuka.")
    exit()

while True:

    success, frame = cap.read()

    if not success:
        print("Tidak dapat membaca camera.")
        break

    # Tukar BGR ke RGB
    rgb_frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)

    # Face Detection
    results = face_detection.process(rgb_frame)

    # Saiz frame
    height, width, _ = frame.shape

    # Default
    face_detected = False

    # ==========================================
    # CHECK FACE
    # ==========================================

    if results.detections:

        # Jika lebih daripada 1 muka
        if len(results.detections) > 1:

            cv2.putText(
                frame,
                "ONLY 1 FACE ALLOWED",
                (20, 40),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.9,
                (0, 0, 255),
                2
            )

            # Lukis semua muka
            for detection in results.detections:
                mp_drawing.draw_detection(frame, detection)

        else:

            # Hanya 1 muka
            detection = results.detections[0]

            # Confidence
            confidence = detection.score[0]

            # Bounding box
            bbox = detection.location_data.relative_bounding_box

            x = int(bbox.xmin * width)
            y = int(bbox.ymin * height)

            w = int(bbox.width * width)
            h = int(bbox.height * height)

            # Pastikan koordinat tidak negatif
            x = max(0, x)
            y = max(0, y)

            # ==========================================
            # CHECK SAIZ MUKA
            # ==========================================

            face_size_ok = w >= 100 and h >= 100

            # ==========================================
            # CHECK MUKA DI TENGAH
            # ==========================================

            face_center_x = x + (w // 2)
            face_center_y = y + (h // 2)

            frame_center_x = width // 2
            frame_center_y = height // 2

            center_x_ok = abs(face_center_x - frame_center_x) < width * 0.25
            center_y_ok = abs(face_center_y - frame_center_y) < height * 0.25

            center_ok = center_x_ok and center_y_ok

            # ==========================================
            # CHECK SEMUA SYARAT
            # ==========================================

            if confidence >= 0.7 and face_size_ok and center_ok:

                face_detected = True

                # Lukis kotak hijau
                cv2.rectangle(
                    frame,
                    (x, y),
                    (x + w, y + h),
                    (0, 255, 0),
                    2
                )

                cv2.putText(
                    frame,
                    "FACE READY",
                    (20, 40),
                    cv2.FONT_HERSHEY_SIMPLEX,
                    0.9,
                    (0, 255, 0),
                    2
                )

            else:

                # Lukis kotak merah
                cv2.rectangle(
                    frame,
                    (x, y),
                    (x + w, y + h),
                    (0, 0, 255),
                    2
                )

                cv2.putText(
                    frame,
                    "ADJUST YOUR FACE",
                    (20, 40),
                    cv2.FONT_HERSHEY_SIMPLEX,
                    0.9,
                    (0, 0, 255),
                    2
                )

            # ==========================================
            # PAPAR CONFIDENCE
            # ==========================================

            confidence_text = f"Confidence: {confidence * 100:.1f}%"

            cv2.putText(
                frame,
                confidence_text,
                (20, 75),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.7,
                (255, 255, 255),
                2
            )

            # Papar saiz muka
            size_text = f"Face Size: {w} x {h}"

            cv2.putText(
                frame,
                size_text,
                (20, 105),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.7,
                (255, 255, 255),
                2
            )

            # Lukis detection MediaPipe
            mp_drawing.draw_detection(frame, detection)

    else:

        cv2.putText(
            frame,
            "NO FACE DETECTED",
            (20, 40),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.9,
            (0, 0, 255),
            2
        )

    # ==========================================
    # PAPAR CAMERA
    # ==========================================

    cv2.imshow("Rapoo Face Detection", frame)

    # Tekan Q untuk keluar
    if cv2.waitKey(2) & 0xFF == ord('q'):
        break


# ==========================================
# TUTUP CAMERA
# ==========================================

cap.release()
cv2.destroyAllWindows()