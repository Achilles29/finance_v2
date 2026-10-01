# Finance POS Printer Local Agent

Local Agent menjembatani browser POS dengan printer fisik Bluetooth, serial, atau USB. Aplikasi Finance menyimpan koneksi, layout, dan aturan cetak di database. Agent membaca printer aktif dari API bootstrap, lalu membuka endpoint lokal untuk setiap printer.

## Cara kerja

1. Admin mengatur koneksi printer, tampilan umum, layout, dan aturan cetak di Finance.
2. Browser POS pada komputer kasir mengirim pekerjaan ke `http://127.0.0.1:<python_port>/cetak`.
3. Agent memilih printer berdasarkan data bootstrap dan mengirim teks ESC/POS, logo, serta QR fisik.
4. Layout/routing yang diubah di Finance akan di-refresh oleh agent secara berkala. Restart hanya diperlukan bila port agent diubah atau agent bermasalah.

Agent sengaja hanya mendengarkan `127.0.0.1`. Browser POS dan agent harus berjalan pada komputer kasir yang sama.

## File yang disalin ke komputer kasir

Salin folder ini secara utuh. Minimal file berikut harus ada:

- `agent.py`
- `requirements.txt`
- `config.example.json`, lalu salin menjadi `config.json`
- `run_windows.bat` atau `run_linux.sh`
- `install_windows_task.bat` / `uninstall_windows_task.bat`, atau
  `install_linux_service.sh` / `uninstall_linux_service.sh` untuk lifecycle
  service
- `detect_windows.bat` atau `detect_linux.sh`
- `detect_printers.py` dan `check_saved_printers.py` untuk pemeriksaan

Jangan salin `config.json` dari komputer lain tanpa memeriksa nama agent, API key, dan perangkatnya. Nilai key hanya dikirim ke endpoint bootstrap melalui header `X-Printer-Key`, bukan query string.

## Dependensi

```text
Flask>=3.0.0
pyserial>=3.5
Pillow>=10.0.0
qrcode[pil]>=7.4.2
```

`qrcode[pil]` dan `Pillow` dipakai untuk mode QR berbasis gambar. Bila keduanya belum terpasang, agent sekarang mencoba fallback QR native ESC/POS pada printer yang mendukungnya.

## Windows

1. Install Python 3.10+ dari https://www.python.org/downloads/windows/ dan centang `Add Python to PATH`.
2. Salin folder ini, misalnya ke `C:\FinancePosPrinterAgent`.
3. Buka Command Prompt pada folder tersebut:

```bat
cd C:\FinancePosPrinterAgent
python -m venv .venv
.venv\Scripts\activate
python -m pip install --upgrade pip
python -m pip install -r requirements.txt
```

4. Hubungkan dan nyalakan printer, kemudian jalankan `detect_windows.bat`.
5. Salin `config.example.json` menjadi `config.json`, lalu isi konfigurasi agent yang benar.
6. Uji bootstrap:

```bat
.venv\Scripts\python.exe agent.py --config config.json --once
```

7. Jika valid, jalankan `run_windows.bat`.
8. Klik dua kali `install_windows_task.bat`. Ia membuat Task Scheduler bernama
   **Finance POS Printer Agent** untuk user kasir saat login. Untuk
   menghentikan autostart tanpa menghapus konfigurasi, jalankan
   `uninstall_windows_task.bat`.

## Linux (Debian/Ubuntu)

```bash
sudo apt update
sudo apt install -y python3 python3-venv python3-pip bluez libjpeg-dev zlib1g-dev
sudo usermod -aG dialout $USER
```

Keluar lalu masuk kembali setelah menambahkan grup `dialout`.

```bash
cd /opt/finance-pos-printer-agent
python3 -m venv .venv
. .venv/bin/activate
python -m pip install --upgrade pip
python -m pip install -r requirements.txt
chmod +x run_linux.sh detect_linux.sh
./detect_linux.sh
cp config.example.json config.json
./.venv/bin/python agent.py --config config.json --once
./run_linux.sh
```

Untuk autostart, jalankan `./install_linux_service.sh` sebagai user operator
(script meminta `sudo` hanya saat memasang unit systemd). Untuk melepasnya,
jalankan `./uninstall_linux_service.sh`. Kedua script tidak menghapus folder
agent maupun `config.json`.

## Format config

```json
{
  "agent_name": "FINANCE-POS-PRINTER-01",
  "retry_seconds": 10,
  "print_retry_count": 2,
  "log_file": "./agent.log",
  "log_max_bytes": 2097152,
  "log_backup_count": 3,
  "api": {
    "enabled": true,
    "base_url": "https://finance.example.com",
    "allowed_origins": ["https://finance.example.com"],
    "endpoint": "/pos/printers/bootstrap",
    "key": "",
    "agent_name_param": "agent_name",
    "refresh_seconds": 30,
    "timeout_seconds": 8
  },
  "logo": {
    "mode": "esc_star",
    "threshold": 180,
    "scale": 1.5,
    "max_height_dots": 160,
    "fetch_timeout_seconds": 10
  },
  "printers": []
}
```

`api.base_url` menjadi origin browser yang diizinkan setelah dinormalisasi ke skema, host, dan port. `api.allowed_origins` bersifat opsional dan hanya menerima daftar origin exact tanpa wildcard; gunakan ini bila Finance dibuka dari origin lain atau saat mode offline (`api.enabled` = `false`). Origin harus berupa `http://` atau `https://` dengan host dan port opsional, tanpa path. Tanpa `base_url` yang valid dan tanpa allowlist, endpoint lokal `/cetak` menolak semua request browser.

Bootstrap key wajib dipasang sebagai environment `POS_PRINTER_BOOTSTRAP_KEY` pada PHP-FPM dan nilai pasangan pada `api.key` di komputer kasir. Jangan menaruh nilainya di dokumentasi atau commit. Endpoint bootstrap hanya menerima header `X-Printer-Key`; agent dan helper pemeriksaan tidak lagi mengirim key melalui query string. Jika environment PHP-FPM kosong, bootstrap berhenti fail-closed dan harus diperbaiki sebelum agent dijalankan.

### Pairing per komputer dan rotasi key

Untuk instalasi customer/produksi, gunakan environment PHP-FPM
`POS_PRINTER_AGENT_KEYS` sebagai JSON privat. Kunci JSON adalah nama agent
huruf besar yang sama dengan `agent_name`; setiap agent mendapatkan secret
berbeda. Contoh bentuk (gunakan secret acak asli, bukan nilai contoh):

```json
{
  "FINANCE-POS-PRINTER-01": {"current": "secret-agent-satu", "previous": ""},
  "FINANCE-POS-PRINTER-02": {"current": "secret-agent-dua", "previous": ""}
}
```

Unduh `config.json` dari Finance dengan nama agent yang tepat. Finance hanya
menaruh `current` milik agent tersebut ke file yang diunduh. Saat rotasi,
pindahkan key lama ke `previous`, unduh config baru, restart agent, pastikan
cetak berhasil, lalu kosongkan `previous`. Selama map ini terpasang, agent
yang tidak terdaftar ditolak; global `POS_PRINTER_BOOTSTRAP_KEY` hanya untuk
instalasi lama yang belum dipasangkan per-agent.

Agent menyimpan config hasil refresh secara atomik dengan izin file privat
apabila sistem operasi mendukungnya. Log juga berotasi otomatis; defaultnya
2 MB × 3 arsip. Jangan mengirim `config.json` atau `agent.log` ke git/tiket
dukungan karena config dapat berisi key bootstrap.

## Endpoint lokal

Setiap printer aktif membuka endpoint sesuai `python_port` dari bootstrap, misalnya:

- `GET http://127.0.0.1:3000/health`
- `POST http://127.0.0.1:3000/cetak`

Contoh payload cetak:

```json
{
  "text": "ISI STRUK",
  "paper_width_mm": 80
}
```

## Marker khusus

Logo dan QR ditulis ke payload sebagai marker, kemudian diubah agent menjadi gambar ESC/POS:

```text
[[LOGO_URL:https://domain/logo.png]]
[[QRCODE:https://domain/review/abc]]
```

## Pemeriksaan masalah

- QR tidak tercetak: restart agent lebih dulu. Bila printer tidak mendukung QR native ESC/POS, jalankan kembali `python -m pip install -r requirements.txt` agar agent kembali memakai mode gambar.
- Test gagal: buka `http://127.0.0.1:<port>/health` pada komputer kasir dan periksa `agent.log`.
- Browser ditolak saat mencetak: pastikan origin halaman Finance sama persis dengan `api.base_url` atau tercantum di `api.allowed_origins`; `null`, origin kosong, dan wildcard tidak didukung.
- Teks terlalu sempit: cocokkan `paper_width_mm` dan `chars_per_line` pada Koneksi Printer dengan printer fisik.
- Routing salah: periksa Aturan Cetak di Finance. Jangan mengubah routing di `config.json`.
- Port berubah: restart agent karena proses lama masih memegang port sebelumnya.
- Koneksi printer diubah dari Finance: endpoint `/health` akan memberi status
  `restart_required`. Restart service/task agent; perubahan koneksi sengaja
  tidak diterapkan setengah jalan agar port lama tidak tersisa aktif.
- Periksa versi tanpa memulai service: `python agent.py --version`. Uji
  konfigurasi dan pairing server: `python agent.py --config config.json --once`.
