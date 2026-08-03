@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\scripts\test_environment.php
set "RESULT=%ERRORLEVEL%"
echo.
pause
exit /b %RESULT%
