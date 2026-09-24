@echo off
setlocal
cd /d "%~dp0"

if "%APP_API_URL%"=="" (
  echo ERROR: Tetapkan APP_API_URL kepada URL HTTPS api/kiosk.php.
  pause
  exit /b 1
)
if "%KIOSK_API_KEY%"=="" (
  echo ERROR: Tetapkan KIOSK_API_KEY sebelum menjalankan kiosk.
  pause
  exit /b 1
)

py -3.11 --version >nul 2>nul
if not errorlevel 1 (
  py -3.11 "%~dp0face_recognition.py"
  set "EXIT_CODE=%ERRORLEVEL%"
  goto finish
)

python --version >nul 2>nul
if errorlevel 1 (
  echo ERROR: Python 3.11 tidak dijumpai. Pasang Python dan requirements.txt.
  pause
  exit /b 1
)
python "%~dp0face_recognition.py"
set "EXIT_CODE=%ERRORLEVEL%"

:finish
if not "%EXIT_CODE%"=="0" pause
exit /b %EXIT_CODE%
