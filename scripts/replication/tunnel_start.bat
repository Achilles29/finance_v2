@echo off
REM ============================================================
REM tunnel_start.bat — SSH tunnel untuk Windows
REM Port 3307 lokal → port 3306 Server 1 (via SSH)
REM
REM Auto-start: Task Scheduler → trigger At Logon → action: jalankan file ini
REM ============================================================

set MASTER_HOST=IP_ATAU_DOMAIN_SERVER_1
set SSH_USER=root
set LOCAL_PORT=3307
set REMOTE_PORT=3306

echo Membuat SSH tunnel: localhost:%LOCAL_PORT% ^-^> %MASTER_HOST%:%REMOTE_PORT%

REM Cek apakah tunnel sudah aktif
netstat -an | findstr ":%LOCAL_PORT% " | findstr "LISTENING" >nul 2>&1
if not errorlevel 1 (
    echo Tunnel sudah aktif di port %LOCAL_PORT%
    exit /b 0
)

REM Buat tunnel (OpenSSH bawaan Windows 10/11)
ssh -fN -o "ServerAliveInterval=30" -o "ServerAliveCountMax=3" -o "StrictHostKeyChecking=no" -L %LOCAL_PORT%:127.0.0.1:%REMOTE_PORT% %SSH_USER%@%MASTER_HOST%

if errorlevel 1 (
    echo [ERROR] Gagal membuat tunnel. Pastikan OpenSSH terinstall dan SSH key sudah dikonfigurasi.
) else (
    echo Tunnel aktif.
)
