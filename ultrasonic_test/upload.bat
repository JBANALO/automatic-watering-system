@echo off
REM Arduino ESP32 Upload Script for HC-SR04 Test
REM This script uploads ultrasonic_test.ino to ESP32

setlocal enabledelayedexpansion

echo.
echo ======================================
echo Arduino ESP32 Ultrasonic Test Upload
echo ======================================
echo.

REM Check if Arduino CLI is installed
where arduino-cli >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Arduino CLI not found in PATH
    echo Please install it or add Arduino to PATH
    pause
    exit /b 1
)

echo Detecting ESP32 board and COM port...
arduino-cli board list

echo.
set /p PORT="Enter COM port (e.g., COM3): "
set /p FQBN="Enter board FQBN (default: esp32:esp32:esp32): "

if "!FQBN!"=="" set FQBN=esp32:esp32:esp32

echo.
echo Uploading to port: !PORT!
echo Board: !FQBN!
echo.

cd /d "%~dp0"
arduino-cli upload -p !PORT! -b !FQBN! --verbose

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ✓ Upload successful!
    echo.
    echo Opening Serial Monitor...
    timeout /t 2
    start cmd /k "mode !PORT! BAUD=115200 PARITY=N DATA=8 STOP=1 && title Serial Monitor && color 0A && @echo on"
) else (
    echo.
    echo ✗ Upload failed!
    echo.
)

pause
