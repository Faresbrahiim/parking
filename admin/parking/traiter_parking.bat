@echo off
chcp 65001 >nul
setlocal
pushd "%~dp0.."
set "ADMIN=%CD%"
popd

set "PHPBIN="
if exist "C:\xampp\php\php.exe" set "PHPBIN=C:\xampp\php\php.exe"
if "%PHPBIN%"=="" where php >nul 2>&1 && for /f "delims=" %%i in ('where php 2^>nul') do set "PHPBIN=%%i" & goto :run
:run
if "%PHPBIN%"=="" (
  echo PHP introuvable. Installe XAMPP ou ajoute php.exe au PATH.
  pause
  exit /b 1
)

"%PHPBIN%" "%ADMIN%\traiter_parking_cli.php"
set "RC=%errorlevel%"
exit /b %RC%
