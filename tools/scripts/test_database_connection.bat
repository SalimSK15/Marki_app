@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\scripts\test_database_connection.php
set "RESULT=%ERRORLEVEL%"
echo.
if "%RESULT%"=="0" (
  echo La base de donnees est correctement configuree.
) else (
  echo Consultez GUIDE_INSTALLATION_ET_TEST_GLOBAL.md.
)
pause
exit /b %RESULT%
