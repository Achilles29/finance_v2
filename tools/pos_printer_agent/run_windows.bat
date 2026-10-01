@echo off
setlocal EnableExtensions
cd /d %~dp0
if not exist config.json (
  echo ERROR: config.json belum ada. Unduh config.json dari Finance lalu simpan di folder agent ini.
  exit /b 2
)
if exist .venv\Scripts\python.exe (
  .venv\Scripts\python.exe agent.py --config config.json
) else (
  python agent.py --config config.json
)
