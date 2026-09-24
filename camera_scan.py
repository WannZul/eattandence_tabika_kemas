import cv2

for i in range(5):
    cap = cv2.VideoCapture(i, cv2.CAP_DSHOW)

    if cap.isOpened():
        success, frame = cap.read()

        if success:
            print("Camera", i, "BERFUNGSI")
        else:
            print("Camera", i, "dikesan tapi tak boleh baca frame")

        cap.release()

    else:
        print("Camera", i, "tiada")