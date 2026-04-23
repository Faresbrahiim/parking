@echo off
REM =====================================================================
REM Ajoute des exclusions Windows Defender pour accelerer Python/EasyOCR.
REM Cause principale de la lenteur OCR : Defender scanne chaque DLL de torch
REM a chaque import (== plusieurs minutes au lieu de quelques secondes).
REM
REM IMPORTANT : clic droit sur ce fichier -> "Executer en tant qu'administrateur"
REM =====================================================================
setlocal

echo.
echo === Ajout des exclusions Windows Defender ===
echo.

set "PY_DIR=%LOCALAPPDATA%\Programs\Python\Python312"
set "PARKING_DIR=%~dp0"

echo Exclusion dossier Python : %PY_DIR%
powershell -NoProfile -Command "Add-MpPreference -ExclusionPath '%PY_DIR%' -ErrorAction SilentlyContinue"

echo Exclusion dossier parking : %PARKING_DIR%
powershell -NoProfile -Command "Add-MpPreference -ExclusionPath '%PARKING_DIR%' -ErrorAction SilentlyContinue"

echo Exclusion process python.exe
powershell -NoProfile -Command "Add-MpPreference -ExclusionProcess 'python.exe' -ErrorAction SilentlyContinue"

echo Exclusion cache EasyOCR : %USERPROFILE%\.EasyOCR
powershell -NoProfile -Command "Add-MpPreference -ExclusionPath '%USERPROFILE%\.EasyOCR' -ErrorAction SilentlyContinue"

echo Exclusion cache Ultralytics : %USERPROFILE%\AppData\Roaming\Ultralytics
powershell -NoProfile -Command "Add-MpPreference -ExclusionPath '%USERPROFILE%\AppData\Roaming\Ultralytics' -ErrorAction SilentlyContinue"

echo.
echo === Verification des exclusions actives ===
powershell -NoProfile -Command "(Get-MpPreference).ExclusionPath"

echo.
echo Termine. Relance maintenant l'OCR, la premiere passe chargera
echo les modeles (30-60s), les suivantes seront presque instantanees.
echo.
pause
