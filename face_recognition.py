import cv2
import mediapipe as mp
import os
import numpy as np
import mysql.connector
from datetime import datetime
import webbrowser


# ==========================================
# SETTINGS
# ==========================================

DATASET_PATH = "dataset"

# RAPOO WEBCAM
CAMERA_INDEX = 0

# LBPH
CONFIDENCE_THRESHOLD = 100

# Berapa kali nama yang sama perlu dikesan
# sebelum attendance direkod
REQUIRED_MATCHES = 5

# Rekod page
RECORD_PAGE = (
    "http://localhost/PROJECT%20FYP/rekod_kehadiran.php"
)


# ==========================================
# DATABASE
# ==========================================

db = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="face_attendance"
)

cursor = db.cursor()


# ==========================================
# MEDIAPIPE FACE DETECTION
# ==========================================

mp_face_detection = mp.solutions.face_detection

face_detection = mp_face_detection.FaceDetection(
    model_selection=0,
    min_detection_confidence=0.5
)


# ==========================================
# LOAD DATASET
# ==========================================

faces = []
labels = []
student_names = {}

label_id = 0

print("\n==========================================")
print("LOADING DATASET")
print("==========================================")

# Sort supaya susunan folder sentiasa konsisten
folders = sorted(os.listdir(DATASET_PATH))

for folder_name in folders:

    folder_path = os.path.join(
        DATASET_PATH,
        folder_name
    )

    # Pastikan folder
    if not os.path.isdir(folder_path):
        continue

    # Pecahkan Student ID dan nama
    # Contoh:
    # M002_AMNI
    # M005_TOK

    parts = folder_name.split("_", 1)

    if len(parts) != 2:
        print("Folder tidak ikut format:", folder_name)
        continue

    student_id = parts[0]
    student_name = parts[1]

    student_names[label_id] = (
        student_id,
        student_name
    )

    print(
        "Label:",
        label_id,
        "| ID:",
        student_id,
        "| Name:",
        student_name
    )

    # ======================================
    # LOAD SEMUA GAMBAR DALAM FOLDER
    # ======================================

    image_files = sorted(
        os.listdir(folder_path)
    )

    for file_name in image_files:

        file_path = os.path.join(
            folder_path,
            file_name
        )

        img = cv2.imread(
            file_path,
            cv2.IMREAD_GRAYSCALE
        )

        if img is None:
            continue

        # Pastikan saiz sama
        img = cv2.resize(
            img,
            (200, 200)
        )

        faces.append(img)
        labels.append(label_id)

    label_id += 1


# ==========================================
# CHECK DATASET
# ==========================================

if len(faces) == 0:

    print("\nERROR!")
    print("Tiada gambar dalam dataset.")
    print("Sila check folder dataset.")

    input("\nTekan ENTER untuk keluar...")
    exit()


print("\nJumlah gambar:", len(faces))
print("Jumlah student:", len(student_names))


# ==========================================
# TRAIN LBPH
# ==========================================

recognizer = cv2.face.LBPHFaceRecognizer_create(
    radius=1,
    neighbors=8,
    grid_x=8,
    grid_y=8
)

recognizer.train(
    faces,
    np.array(labels)
)

print("\n==========================================")
print("TRAINING SELESAI")
print("==========================================")


# ==========================================
# CAMERA
# ==========================================

cap = cv2.VideoCapture(
    CAMERA_INDEX,
    cv2.CAP_DSHOW
)

cap.set(
    cv2.CAP_PROP_FRAME_WIDTH,
    640
)

cap.set(
    cv2.CAP_PROP_FRAME_HEIGHT,
    480
)


if not cap.isOpened():

    print("\nCamera tidak dapat dibuka.")

    input("\nTekan ENTER untuk keluar...")
    exit()


print("\n==========================================")
print("CAMERA READY")
print("==========================================")
print("AMNI  = M002")
print("TOK   = M005")
print("------------------------------------------")
print("Tekan Q untuk keluar.")
print("==========================================\n")


# ==========================================
# MATCH HISTORY
# ==========================================

match_history = []

attendance_saved = False


# ==========================================
# START SCANNING
# ==========================================

while True:

    ret, frame = cap.read()

    if not ret:
        print("Tidak dapat membaca camera.")
        break

    # ======================================
    # FLIP CAMERA
    # ======================================

    frame = cv2.flip(
        frame,
        1
    )

    # ======================================
    # CONVERT BGR → RGB
    # ======================================

    rgb = cv2.cvtColor(
        frame,
        cv2.COLOR_BGR2RGB
    )

    # ======================================
    # FACE DETECTION
    # ======================================

    results = face_detection.process(rgb)

    current_name = "UNKNOWN"
    current_confidence = 999


    if results.detections:

        for detection in results.detections:

            bbox = detection.location_data.relative_bounding_box

            h, w, _ = frame.shape

            x = int(bbox.xmin * w)
            y = int(bbox.ymin * h)

            box_w = int(bbox.width * w)
            box_h = int(bbox.height * h)

            # ==================================
            # PADDING
            # ==================================

            padding_x = int(box_w * 0.20)
            padding_y = int(box_h * 0.20)

            x1 = max(
                0,
                x - padding_x
            )

            y1 = max(
                0,
                y - padding_y
            )

            x2 = min(
                w,
                x + box_w + padding_x
            )

            y2 = min(
                h,
                y + box_h + padding_y
            )

            # ==================================
            # CROP FACE
            # ==================================

            face = frame[
                y1:y2,
                x1:x2
            ]

            if face.size == 0:
                continue

            # ==================================
            # GRAYSCALE
            # ==================================

            gray_face = cv2.cvtColor(
                face,
                cv2.COLOR_BGR2GRAY
            )

            # ==================================
            # RESIZE
            # ==================================

            gray_face = cv2.resize(
                gray_face,
                (200, 200)
            )

            # ==================================
            # RECOGNIZE
            # ==================================

            predicted_label, confidence = recognizer.predict(
                gray_face
            )

            print(
                "Predicted:",
                predicted_label,
                "| Confidence:",
                round(confidence, 2)
            )


            # ==================================
            # CHECK RESULT
            # ==================================

            if (
                predicted_label in student_names
                and confidence <= CONFIDENCE_THRESHOLD
            ):

                student_id, student_name = student_names[
                    predicted_label
                ]

                current_name = student_name
                current_confidence = confidence

                print(
                    "Detected:",
                    student_id,
                    student_name,
                    "| Score:",
                    round(confidence, 2)
                )

                # ==================================
                # MATCH HISTORY
                # ==================================

                match_history.append(
                    predicted_label
                )

                # Simpan 10 bacaan terakhir sahaja
                if len(match_history) > 10:

                    match_history.pop(0)

                # ==================================
                # CHECK CONSISTENT MATCH
                # ==================================

                recent_matches = [
                    x for x in match_history
                    if x == predicted_label
                ]

                if len(recent_matches) >= REQUIRED_MATCHES:

                    print("\n================================")
                    print("FACE CONFIRMED")
                    print("Student ID:", student_id)
                    print("Name:", student_name)
                    print("================================")


                    # ==================================
                    # CURRENT DATE & TIME
                    # ==================================

                    now = datetime.now()

                    current_date = now.strftime(
                        "%Y-%m-%d"
                    )

                    current_time = now.strftime(
                        "%H:%M:%S"
                    )


                    # ==================================
                    # CHECK ATTENDANCE
                    # ==================================

                    check_sql = """
                    SELECT id
                    FROM attendance
                    WHERE student_id = %s
                    AND date = %s
                    """

                    cursor.execute(
                        check_sql,
                        (
                            student_id,
                            current_date
                        )
                    )

                    existing = cursor.fetchone()


                    # ==================================
                    # INSERT ATTENDANCE
                    # ==================================

                    if existing is None:

                        insert_sql = """
                        INSERT INTO attendance
                        (student_id, name, date, time, status)
                        VALUES (%s, %s, %s, %s, %s)
                        """

                        cursor.execute(
                            insert_sql,
                            (
                                student_id,
                                student_name,
                                current_date,
                                current_time,
                                "Hadir"
                            )
                        )

                        db.commit()

                        print(
                            "\nAttendance berjaya direkod!"
                        )

                        print(
                            "ID:",
                            student_id
                        )

                        print(
                            "Name:",
                            student_name
                        )

                        print(
                            "Date:",
                            current_date
                        )

                        print(
                            "Time:",
                            current_time
                        )

                    else:

                        print(
                            "\nAttendance sudah direkod hari ini."
                        )


                    attendance_saved = True

                    break


            else:

                current_name = "UNKNOWN"
                current_confidence = confidence

                # Jangan masukkan UNKNOWN
                # dalam history
                match_history = []


            # ==================================
            # DRAW RECTANGLE
            # ==================================

            cv2.rectangle(
                frame,
                (x1, y1),
                (x2, y2),
                (57, 73, 219),
                2
            )

            # ==================================
            # DISPLAY NAME
            # ==================================

            if current_name != "UNKNOWN":

                text_display = (
                    current_name
                    + " "
                    + str(round(current_confidence, 1))
                )

            else:

                text_display = "UNKNOWN"


            cv2.putText(
                frame,
                text_display,
                (x1, y1 - 10),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.8,
                (57, 73, 219),
                2
            )


    else:

        # Tiada muka
        match_history = []

        cv2.putText(
            frame,
            "No Face Detected",
            (20, 40),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.8,
            (0, 0, 255),
            2
        )


    # ======================================
    # SHOW CAMERA
    # ======================================

    cv2.imshow(
        "Face Recognition - Rapoo",
        frame
    )


    # ======================================
    # Q = EXIT
    # ======================================

    key = cv2.waitKey(1) & 0xFF

    if key == ord("q"):

        print("\nUser keluar dari camera.")
        break


    # ======================================
    # ATTENDANCE SAVED
    # ======================================

    if attendance_saved:
        break


# ==========================================
# CLOSE CAMERA
# ==========================================

cap.release()

cv2.destroyAllWindows()

face_detection.close()

cursor.close()

db.close()


# ==========================================
# OPEN RECORD PAGE
# ==========================================

if attendance_saved:

    print("\nMembuka Attendance Record...")

    webbrowser.open(
        RECORD_PAGE
    )

else:

    print("\nAttendance tidak direkod.")


print("\nProgram tamat.")