@echo off
REM ============================================================
REM backup_full.bat — Full local DB dump (Windows)
REM
REM Untuk penjadwalan otomatis pakai Windows Task Scheduler:
REM   Trigger   : Every 30 minutes
REM   Action    : C:\xampp\htdocs\finance\scripts\backup\backup_full.bat
REM   Start in  : C:\xampp\htdocs\finance
REM ============================================================

setlocal enabledelayedexpansion

REM ── Path setup ──────────────────────────────────────────
set SCRIPT_DIR=%~dp0
set FINANCE_ROOT=%SCRIPT_DIR%..\..
set BACKUP_DIR=%FINANCE_ROOT%\backup\dumps
set LOG_DIR=%FINANCE_ROOT%\backup\logs

REM ── Load .env ────────────────────────────────────────────
set ENV_FILE=%SCRIPT_DIR%.env
if not exist "%ENV_FILE%" (
    echo [ERROR] File .env tidak ditemukan di %SCRIPT_DIR%
    echo         Salin .env.example ke .env dan isi konfigurasi.
    exit /b 1
)
for /f "usebackq tokens=1,* delims==" %%A in ("%ENV_FILE%") do (
    set %%A=%%B
)

REM ── Defaults ─────────────────────────────────────────────
if not defined DB_HOST set DB_HOST=localhost
if not defined DB_PORT set DB_PORT=3306
if not defined DB_USER set DB_USER=root
if not defined DB_PASS set DB_PASS=
if not defined DB_NAME set DB_NAME=db_finance

REM ── Timestamp ────────────────────────────────────────────
for /f "tokens=1-6 delims=/: " %%a in ('echo %date% %time%') do (
    set YYYY=%%c
    set MM=%%a
    set DD=%%b
    set HH=%%d
    set MIN=%%e
    set SEC=%%f
)
set TIMESTAMP=%YYYY%%MM%%DD%_%HH%%MIN%%SEC%
set DUMPFILE=%BACKUP_DIR%\backup_%DB_NAME%_%TIMESTAMP%.sql

mkdir "%BACKUP_DIR%" 2>nul
mkdir "%LOG_DIR%" 2>nul

set LOGFILE=%LOG_DIR%\backup_%TIMESTAMP%.log

echo [%date% %time%] ====== Backup START: %TIMESTAMP% ====== >> "%LOGFILE%"
echo [%date% %time%] Database: %DB_NAME%@%DB_HOST%:%DB_PORT% >> "%LOGFILE%"
echo [%date% %time%] Storage: LOCAL-ONLY on this host >> "%LOGFILE%"
echo [%date% %time%] Off-site: SEPARATE encrypted backup must be configured independently >> "%LOGFILE%"

REM ── Mysqldump ────────────────────────────────────────────
set MYSQLDUMP_PATH=C:\xampp\mysql\bin\mysqldump.exe
if not exist "%MYSQLDUMP_PATH%" set MYSQLDUMP_PATH=mysqldump

if defined DB_PASS (
    "%MYSQLDUMP_PATH%" --host=%DB_HOST% --port=%DB_PORT% --user=%DB_USER% --password=%DB_PASS% --single-transaction --routines --no-tablespaces --set-gtid-purged=OFF --skip-lock-tables %DB_NAME% > "%DUMPFILE%"
) else (
    "%MYSQLDUMP_PATH%" --host=%DB_HOST% --port=%DB_PORT% --user=%DB_USER% --single-transaction --routines --no-tablespaces --set-gtid-purged=OFF --skip-lock-tables %DB_NAME% > "%DUMPFILE%"
)

if errorlevel 1 (
    echo [%date% %time%] [ERROR] mysqldump gagal! >> "%LOGFILE%"
    exit /b 1
)
echo [%date% %time%] Dump OK: %DUMPFILE% >> "%LOGFILE%"

REM ── Integrity dan retention aman ─────────────────────────
for /f "usebackq delims=" %%H in (`powershell -NoProfile -Command "(Get-FileHash -Algorithm SHA256 -LiteralPath '%DUMPFILE%').Hash.ToLowerInvariant()"`) do set DUMP_HASH=%%H
if not defined DUMP_HASH (
    echo [%date% %time%] [ERROR] SHA-256 gagal dibuat! >> "%LOGFILE%"
    exit /b 1
)
echo !DUMP_HASH!  backup_%DB_NAME%_%TIMESTAMP%.sql> "%DUMPFILE%.sha256"
echo [%date% %time%] SHA-256: !DUMP_HASH! >> "%LOGFILE%"
echo [%date% %time%] Retention: tidak ada penghapusan otomatis; review dan quarantine wajib >> "%LOGFILE%"

echo [%date% %time%] ====== Backup SELESAI ====== >> "%LOGFILE%"
echo.
echo Backup selesai. Log: %LOGFILE%
