@echo off
cd /d %~dp0
if exist .venv\Scripts\python.exe (
  .venv\Scripts\python.exe detect_printers.py > printer_detect.json
) else (
  python detect_printers.py > printer_detect.json
)
echo.
echo Hasil deteksi disimpan di printer_detect.json
if exist printer_detect.json type printer_detect.json
pause
