@echo off
REM =====================================================================
REM Installe Tesseract-OCR (UB-Mannheim build) + pytesseract.
REM Lancer en ADMINISTRATEUR.
REM =====================================================================
setlocal enabledelayedexpansion

set "INSTALLER=%TEMP%\tesseract-ocr-w64-setup.exe"
set "URL=https://github.com/UB-Mannheim/tesseract/releases/download/v5.3.3.20231005/tesseract-ocr-w64-setup-5.3.3.20231005.exe"
set "URL_ALT=https://digi.bib.uni-mannheim.de/tesseract/tesseract-ocr-w64-setup-5.3.3.20231005.exe"

echo.
echo ============================================
echo = Installation de Tesseract-OCR            =
echo ============================================
echo.

if exist "C:\Program Files\Tesseract-OCR\tesseract.exe" (
    echo Tesseract deja installe dans C:\Program Files\Tesseract-OCR
    goto :install_python
)

REM --- Tentative 1 : curl.exe (Windows 10+, gere TLS 1.2 correctement) ---
where curl.exe >nul 2>&1
if %errorlevel%==0 (
    echo [1/3] Tentative avec curl.exe  ^(miroir UB-Mannheim^)...
    curl.exe -L --fail --retry 3 --retry-delay 2 -o "%INSTALLER%" "%URL_ALT%"
    if exist "%INSTALLER%" if not "%~z1"=="0" goto :have_installer
)

REM --- Tentative 2 : curl.exe sur GitHub ---
where curl.exe >nul 2>&1
if %errorlevel%==0 (
    echo [2/3] Tentative avec curl.exe  ^(GitHub^)...
    curl.exe -L --fail --retry 3 --retry-delay 2 -o "%INSTALLER%" "%URL%"
    if exist "%INSTALLER%" goto :have_installer
)

REM --- Tentative 3 : PowerShell avec TLS 1.2 force ---
echo [3/3] Tentative avec PowerShell ^(TLS 1.2 force^)...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; ^
   try { Invoke-WebRequest -Uri '%URL_ALT%' -OutFile '%INSTALLER%' -UseBasicParsing -TimeoutSec 120 } ^
   catch { Invoke-WebRequest -Uri '%URL%' -OutFile '%INSTALLER%' -UseBasicParsing -TimeoutSec 120 }"

if exist "%INSTALLER%" goto :have_installer

REM --- Echec : aide manuelle ---
echo.
echo ========================================================
echo  ECHEC du telechargement automatique.
echo ========================================================
echo.
echo Deux solutions manuelles :
echo.
echo  A^) Ouvre dans ton navigateur :
echo     https://digi.bib.uni-mannheim.de/tesseract/
echo     Telecharge  tesseract-ocr-w64-setup-5.x.x.exe
echo.
echo  B^) Ou depuis GitHub :
echo     https://github.com/UB-Mannheim/tesseract/releases
echo.
echo Puis relance ce .bat apres avoir sauve l'exe dans :
echo     %INSTALLER%
echo.
echo  OU installe-le simplement a la main ^(clic-clic-clic^),
echo  coche "Arabic" dans "Additional language data",
echo  et relance ensuite ce .bat pour pytesseract + ara.traineddata.
echo.
pause
goto :install_python

:have_installer
echo.
echo Telechargement OK : %INSTALLER%
echo Lancement de l'installeur...
echo   -^> Garde l'emplacement par defaut  C:\Program Files\Tesseract-OCR
echo   -^> Dans "Additional language data"  coche  "Arabic"
echo.
"%INSTALLER%"

:install_python
echo.
echo ============================================
echo = Installation de pytesseract + Pillow     =
echo ============================================

set "PY=%LOCALAPPDATA%\Programs\Python\Python312\python.exe"
if not exist "%PY%" (
    where python >nul 2>&1 && set "PY=python"
)
echo Python utilise : %PY%
"%PY%" -m pip install --disable-pip-version-check pytesseract Pillow

echo.
echo ============================================
echo = Copie de ara.traineddata                 =
echo ============================================
if exist "C:\Program Files\Tesseract-OCR\tessdata" (
    if exist "%~dp0ara.traineddata" (
        copy /Y "%~dp0ara.traineddata" "C:\Program Files\Tesseract-OCR\tessdata\" >nul
        echo ara.traineddata copie dans C:\Program Files\Tesseract-OCR\tessdata\
    ) else (
        echo ATTENTION : %~dp0ara.traineddata introuvable.
    )
) else (
    echo ATTENTION : dossier Tesseract introuvable ^(installation non faite ?^).
)

echo.
echo ============================================
echo = Verification                             =
echo ============================================
if exist "C:\Program Files\Tesseract-OCR\tesseract.exe" (
    "C:\Program Files\Tesseract-OCR\tesseract.exe" --version
    echo.
    echo Langues disponibles :
    "C:\Program Files\Tesseract-OCR\tesseract.exe" --list-langs
) else (
    echo Tesseract pas encore installe.
)

echo.
echo Termine. Relance l'admin et depose une photo dans parking\entree\.
pause
