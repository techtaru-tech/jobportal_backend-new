@echo off
REM ---------------------------------------------------------------
REM  Local dev launcher for the Inthes API backend.
REM
REM  Laragon does not register PHP globally, so this sets PATH for
REM  this window only, then starts the dev server on 0.0.0.0 so a
REM  phone or emulator on the same WiFi can reach it.
REM
REM  Just double-click this file. Nothing to paste, nothing to
REM  configure. Press Ctrl+C in the window to stop the server.
REM ---------------------------------------------------------------

set "PATH=C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\composer;C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin;%PATH%"

cd /d "%~dp0"

echo.
echo  Inthes API backend
echo  ------------------
php -r "echo '  PHP      : ' . PHP_VERSION . PHP_EOL;"
echo   Local    : http://127.0.0.1:8000/api/v1
echo   Emulator : http://10.0.2.2:8000/api/v1
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4"') do (
    for /f "tokens=1" %%b in ("%%a") do echo   LAN      : http://%%b:8000/api/v1
)
echo.
echo  Press Ctrl+C to stop.
echo.

php artisan serve --host=0.0.0.0 --port=8000

REM Keep the window open if the server exits with an error, so the
REM message stays readable instead of the window vanishing.
if errorlevel 1 pause
