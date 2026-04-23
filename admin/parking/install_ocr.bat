@echo off
cd /d "%~dp0"
REM Installation OCR (Internet requis la 1ere fois).
set PY=%LOCALAPPDATA%\Programs\Python\Python312\python.exe
if not exist "%PY%" set PY=%LOCALAPPDATA%\Programs\Python\Python311\python.exe
if not exist "%PY%" set PY=python
if exist "%PY%" (
  >"%~dp0python_exe_path.txt" echo %PY%
)
"%PY%" -m pip install --upgrade pip
"%PY%" -m pip install -r requirements.txt
"%PY%" ocr_selftest.py
echo.
echo Termine. Ouvrez dans le navigateur : http://localhost/PFA/admin/api/ocr_status.php
echo Puis deposez une photo dans parking\entree et ouvrez admin.html
pause
