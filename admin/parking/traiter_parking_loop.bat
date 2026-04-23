@echo off
chcp 65001 >nul
echo Traitement parking toutes les 30 secondes (Ctrl+C pour arreter).
echo Depose les photos dans parking\entree ou sortie — pas besoin d'ouvrir le site.
echo.
:loop
call "%~dp0traiter_parking.bat"
timeout /t 30 /nobreak >nul
goto :loop
