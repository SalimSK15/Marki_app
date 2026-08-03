@echo off
set PORT=%1
if "%PORT%"=="" set PORT=80
powershell.exe -NoProfile -Command "Start-Process powershell.exe -Verb RunAs -Wait -ArgumentList '-NoProfile -ExecutionPolicy Bypass -File ""%~dp0allow_marki_private_network.ps1"" -Port %PORT% -RuleName ""MARKI Local HTTP %PORT%""'"
pause
