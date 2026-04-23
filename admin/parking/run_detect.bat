@echo off
REM Wrapper pour detect_plaque_one.py :
REM fixe les variables d'environnement avant de lancer Python.
REM Argument 1 : chemin vers l'image a analyser.

setlocal
set "PYTHONIOENCODING=utf-8"
set "PYTHONUTF8=1"
set "OPENBLAS_NUM_THREADS=1"
set "OMP_NUM_THREADS=1"
set "MKL_NUM_THREADS=1"
set "NUMEXPR_NUM_THREADS=1"
set "VECLIB_MAXIMUM_THREADS=1"
set "PFA_USE_EASYOCR=1"
set "PFA_USE_YOLO=1"

REM Chemin Python : respecte PFA_PYTHON si defini, sinon cherche Python312/311
set "PY=%PFA_PYTHON%"
if "%PY%"=="" (
    if exist "%LOCALAPPDATA%\Programs\Python\Python312\python.exe" (
        set "PY=%LOCALAPPDATA%\Programs\Python\Python312\python.exe"
    ) else if exist "%LOCALAPPDATA%\Programs\Python\Python311\python.exe" (
        set "PY=%LOCALAPPDATA%\Programs\Python\Python311\python.exe"
    ) else (
        set "PY=python"
    )
)

"%PY%" "%~dp0detect_plaque_one.py" %1
endlocal
