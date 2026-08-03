@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\scripts\test_settings_repository.php
set "exitCode=%errorlevel%"
echo.
pause
exit /b %exitCode%
