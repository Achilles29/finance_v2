@echo off
setlocal EnableExtensions
cd /d %~dp0

set "TASK_NAME=Finance POS Printer Agent"
set "RUNNER=%~dp0run_windows.bat"

if not exist "%RUNNER%" (
  echo ERROR: run_windows.bat tidak ditemukan.
  exit /b 2
)
if not exist "%~dp0config.json" (
  echo ERROR: config.json belum ada. Unduh config.json dari Finance terlebih dahulu.
  exit /b 2
)

schtasks /Create /TN "%TASK_NAME%" /TR "\"%RUNNER%\"" /SC ONLOGON /RL LIMITED /F
if errorlevel 1 (
  echo ERROR: Task Scheduler belum dapat membuat service agent. Jalankan Command Prompt sebagai user kasir yang akan memakai printer.
  exit /b 1
)

echo.
echo OK: %TASK_NAME% akan berjalan saat user kasir login.
echo Untuk menjalankan sekarang, buka run_windows.bat. Untuk mengganti koneksi printer, restart task ini atau login ulang.
