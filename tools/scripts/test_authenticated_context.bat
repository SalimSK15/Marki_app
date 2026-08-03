@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\scripts\test_authenticated_context.php
set "exitCode=%errorlevel%"
echo.
pause
exit /b %exitCode%
