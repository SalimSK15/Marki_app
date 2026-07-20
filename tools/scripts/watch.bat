@echo off
cd /d %~dp0\..\..
tools\dart-sass\sass.bat tools\sass\main.scss public\assets\css\styles.css --watch
pause