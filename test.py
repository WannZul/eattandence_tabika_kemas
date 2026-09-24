import cv2
import mediapipe as mp

print("OpenCV:", cv2.__version__)
print("MediaPipe:", mp.__version__)

cap = cv2.VideoCapture(1)

while True:
    success, frame = cap.read()

    if not success:
        break

    cv2.imshow("Rapoo", frame)

    if cv2.waitKey(2) & 0xFF == ord('q'):
        break

cap.release()
cv2.destroyAllWindows()