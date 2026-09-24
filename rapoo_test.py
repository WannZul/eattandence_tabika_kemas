import cv2

cap = cv2.VideoCapture(1, cv2.CAP_DSHOW)

print("Camera status:", cap.isOpened())

while True:
    success, frame = cap.read()

    print("Read:", success)

    if not success:
        break

    cv2.imshow("Rapoo Test", frame)

    if cv2.waitKey(2) & 0xFF == ord('q'):
        break

cap.release()
cv2.destroyAllWindows()