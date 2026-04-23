@echo off
chcp 65001 >nul
cd /d "%~dp0"
title Parking - installation
echo.
echo  *** Installation automatique (Python dans ce dossier + IA + modele) ***
echo      Premier lancement : 5-15 min selon la connexion.
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup_tout.ps1"
if errorlevel 1 (
  echo.
  echo ECHEC. Verifiez Internet, l'espace disque, et les messages ci-dessus.
  pause
  exit /b 1
)
echo.
pause
