@echo off
chcp 65001 >nul
echo =======================================
echo  DETECTION AUTOMATIQUE PAR PHOTOS
echo =======================================
echo.
cd /d "C:\xampp\htdocs\PFA\admin\parking"

if not exist "tools\python-embed\python.exe" (
    echo [!] ERREUR: Python non installe
    echo [!] Lancez d'abord: SETUP_TOUT.bat
    echo.
    pause
    exit /b 1
)

echo [i] Creation des dossiers...
if not exist "auto_detect" mkdir auto_detect
if not exist "processed" mkdir processed

echo [i] Surveillance automatique du dossier auto_detect
echo [i] Deposez vos photos dans: auto_detect\
echo [i] Les plaques seront detectees automatiquement!
echo [i] CTRL+C pour arreter.
echo.

start "" "tools\python-embed\python.exe" auto_detect_photos.py --folder auto_detect --interval 3.0
