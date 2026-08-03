@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\scripts\test_team_repository.php
set "RESULT=%ERRORLEVEL%"
echo.
if "%RESULT%"=="0" (
  echo La section Equipe et acces peut lire la base correctement.
) else (
  echo Consultez INSTALLATION_ET_TEST_FINAL.md.
)
pause
exit /b %RESULT%
