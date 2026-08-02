@echo off
setlocal

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0install_algeria_locations.ps1"

if errorlevel 1 (
    echo.
    echo Echec de l'installation des wilayas et communes locales.
    echo Verifiez votre connexion Internet puis relancez ce fichier.
    pause
    exit /b 1
)

echo.
echo Installation terminee. MARKI peut maintenant charger les communes sans Internet.
pause
