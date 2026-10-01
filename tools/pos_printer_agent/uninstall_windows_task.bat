@echo off
setlocal EnableExtensions
set "TASK_NAME=Finance POS Printer Agent"

schtasks /Delete /TN "%TASK_NAME%" /F
if errorlevel 1 (
  echo INFO: Task tidak ditemukan atau sudah dihapus.
  exit /b 0
)
echo OK: autostart %TASK_NAME% dihapus. Folder dan config.json tidak dihapus.
