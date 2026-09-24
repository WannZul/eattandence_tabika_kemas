import cv2

for i in [0, 1]:

    cap = cv2.VideoCapture(i, cv2.CAP_DSHOW)

    print("Testing Camera Index:", i)

    if not cap.isOpened():
        print("Camera", i, "tak boleh buka")
        continue

    success, frame = cap.read()

    if success:
        print("Camera", i, "BERFUNGSI")

        cv2.imshow("Camera Index " + str(i), frame)

        print("Tekan Q untuk tutup camera", i)

        while True:
            if cv2.waitKey(1) & 0xFF == ord('q'):
                break

    cap.release()
    cv2.destroyAllWindows()

print("Test selesai")