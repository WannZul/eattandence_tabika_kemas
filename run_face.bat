@echo off
setlocal
cd /d "%~dp0"
title eAttendance TABIKA KEMAS - Kiosk

if not defined APP_API_URL (
  echo ERROR: APP_API_URL tidak diterima daripada pelancar web.
  pause
  exit /b 1
)
if not defined KIOSK_API_KEY (
  echo ERROR: KIOSK_API_KEY tidak diterima daripada pelancar web.
  pause
  exit /b 1
)
if not exist "%~dp0.venv\Scripts\python.exe" (
  echo ERROR: Virtual environment Python belum tersedia.
  echo Jalankan: py -3.11 -m venv .venv
  echo Kemudian: .\.venv\Scripts\python.exe -m pip install -r requirements.txt
  pause
  exit /b 1
)

"%~dp0.venv\Scripts\python.exe" -c "import sys, cv2, mediapipe, numpy, requests; assert sys.version_info[:2] == (3, 11); assert hasattr(cv2, 'face')" >nul 2>nul
if errorlevel 1 (
  echo ERROR: Python kiosk mesti versi 3.11 dengan pakej lengkap termasuk cv2.face.
  echo Jalankan: .\.venv\Scripts\python.exe -m pip install -r requirements.txt
  pause
  exit /b 1
)

"%~dp0.venv\Scripts\python.exe" "%~dp0face_recognition.py"
set "EXIT_CODE=%ERRORLEVEL%"
if not "%EXIT_CODE%"=="0" (
  echo.
  echo Kiosk berhenti dengan ralat %EXIT_CODE%.
  pause
)
exit /b %EXIT_CODE%
