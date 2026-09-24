import cv2
import mediapipe as mp
import os
import time

# Masukkan maklumat murid
student_id = input("Masukkan ID Murid: ")
student_name = input("Masukkan Nama Murid: ")

# Folder simpan gambar
folder_name = student_id + "_" + student_name
path = "dataset/" + folder_name

if not os.path.exists(path):
    os.makedirs(path)

# MediaPipe Face Detection
mp_face_detection = mp.solutions.face_detection
mp_drawing = mp.solutions.drawing_utils

face_detection = mp_face_detection.FaceDetection(
    model_selection=0,
    min_detection_confidence=0.5
)

# Webcam Rapoo (Camera 1)
cap = cv2.VideoCapture(1)

cap.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
cap.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)

count = 0

while True:
    success, frame = cap.read()

    if not success:
        print("Frame gagal dibaca")
        continue

    # Tukar BGR ke RGB
    rgb_frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)

    # Detect muka
    results = face_detection.process(rgb_frame)

    if results.detections:
        for detection in results.detections:

            # Lukis kotak dan titik
            mp_drawing.draw_detection(frame, detection)

            # Simpan gambar setiap 0.5 saat
            if count < 6:
                filename = f"{path}/{count}.jpg"
                cv2.imwrite(filename, frame)

                count += 1
                print("Gambar disimpan:", count)

                time.sleep(0.5)

    # Papar webcam
    cv2.imshow("Rapoo Face Registration", frame)

    # Siap selepas 6 gambar
    if count >= 6:
        print("Pendaftaran wajah selesai!")
        break

    # Tekan Q untuk keluar
    if cv2.waitKey(2) & 0xFF == ord('q'):
        break


cap.release()
cv2.destroyAllWindows()