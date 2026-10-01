# Desain Modul Bonus & Penilaian 360

Tanggal: 2026-06-13

## 1. Tujuan

Modul bonus di repo `finance` tidak boleh hanya menjadi alat bagi-bagi uang setelah omzet masuk.
Modul ini harus menjadi jembatan antara:
- target keuangan
- performa operasional harian
- disiplin absensi
- kualitas pelayanan
- evaluasi perilaku tim
- kontrol manual superadmin

Artinya bonus dibangun sebagai `hasil evaluasi`, bukan sekadar `persentase omzet`.

## 2. Prinsip Utama

1. Pengaturan target dan pengaturan bonus dipisah.
2. Bonus tetap bisa membaca target keuangan sebagai gerbang kelulusan.
3. Bonus bisa dibagi berbeda per pegawai, per divisi, per jabatan, bahkan per shift.
4. Penalti tidak boleh liar. Semua penalti harus punya tipe, alasan, pelaku input, dan jejak moderasi.
5. Penilaian 360 tidak otomatis langsung menghukum. Nilainya masuk sebagai sinyal moderasi untuk superadmin.
6. Bonus harus tetap bisa diaudit per hari dan direkap per bulan.

## 3. Sumber Data Bonus

### 3.1 Keuangan & target
- `fin_target_plan`
- `fin_target_plan_line`
- `fin_target_realization`
- `fin_period_close`

### 3.2 Absensi
- `att_daily`
- `att_shift`
- `att_shift_schedule`
- `att_attendance_policy`
- ledger PH

### 3.3 Operasional POS
- `pos_order`
- `pos_order_line`
- `pos_payment`
- `pos_refund`
- `pos_order_monitor_task`

### 3.4 Payroll manual intervention
- `pay_manual_adjustment`
- penalti bonus manual
- moderasi peer review

## 4. Faktor Penentu Bonus

Bonus final per pegawai dibentuk dari beberapa komponen.

### 4.1 Gerbang target
Target dipakai sebagai gerbang bonus, bukan selalu sebagai angka pembagi langsung.
Contoh mode:
- `NONE`: target tidak dipakai sebagai syarat bonus
- `ALL_REQUIRED`: semua indikator penting harus lolos
- `WEIGHTED_SCORE`: bonus boleh jalan bila skor total melewati batas

### 4.2 Kontribusi omzet per shift
Pegawai tidak diberi nilai sama rata.
Pegawai yang hadir di rentang shift yang omzetnya besar mendapat proporsi poin lebih besar.

Contoh komponen:
- omzet per shift
- jumlah order per shift
- jumlah order paid final per shift
- refund pada shift tersebut

### 4.3 Bobot struktur kerja
Bobot bisa berbeda untuk:
- divisi
- jabatan
- pegawai tertentu
- shift tertentu

Ini dipakai untuk menyesuaikan kenyataan operasional. Contoh:
- kitchen malam bisa punya bobot berbeda dari bar pagi
- kepala dapur bisa punya bobot berbeda dari helper

### 4.4 Kualitas kehadiran
Bonus bisa dipengaruhi oleh:
- hadir normal
- terlambat
- alpha
- ambil PH
- status libur biasa

Catatan konsep penting:
- `LIBUR` = pegawai memang tidak dijadwalkan kerja, tidak ada urusan bonus harian
- `PH` = hak libur pengganti karena sebelumnya masuk di hari libur nasional
- jika pegawai sedang `ambil PH`, hari itu bisa:
  - tidak dapat bonus harian
  - atau tetap dihitung tetapi poin dikurangi
  - atau dianggap netral
- kebijakan ini harus tersimpan eksplisit di aturan bonus, tidak dibiarkan implisit

### 4.5 Waktu penyajian
Bonus tidak boleh buta terhadap kualitas layanan.
Perlu komponen `waktu penyajian` yang dibaca dari monitor order/kitchen.

Minimal metrik harian:
- total order terlayani
- rata-rata menit dari order ke ready/served
- jumlah order tepat waktu
- jumlah order lewat batas
- skor service-time harian per outlet/divisi/shift

### 4.6 Penilaian 360
Penilaian rekan kerja 1-5 bintang dipakai sebagai sinyal perilaku.
Tidak langsung memotong bonus secara otomatis besar-besaran.
Jalur aman:
- pegawai memberi rating + alasan
- superadmin memoderasi
- hasil moderasi bisa diterjemahkan menjadi tambah/kurang poin bonus manual

## 5. Penalti Bonus

### 5.1 Penalti otomatis
Contoh:
- alpha
- telat berat
- ambil PH pada hari yang mestinya memotong bonus
- service time sangat buruk

### 5.2 Penalti manual
Contoh sesuai kebutuhan user:
- belum follow IG Namua
- belum share story / tagging IG Namua
- penalty personal
- penalty tim
- area kitchen kotor saat audit pagi, dibebankan ke tim shift terakhir

Setiap penalti harus menyimpan:
- tipe penalti
- scope: personal / tim
- tanggal kejadian
- pegawai/divisi/shift terkait
- pengurang poin
- pengurang nominal bila perlu
- alasan
- siapa yang input
- siapa yang approve

## 6. Penilaian 360

### 6.1 Alur pegawai
1. Pegawai check-in / check-out di `my/attendance`
2. Sistem mendeteksi rekan yang hadir di hari yang sama
3. Sistem menampilkan pengingat penilaian 360
4. Pegawai memberi nilai 1-5 + alasan
5. Nilai masuk status `SUBMITTED`

### 6.2 Alur admin
1. Superadmin membuka workspace bonus
2. Tab `Penilaian 360` menampilkan rating masuk
3. Superadmin bisa setujui / tolak / beri catatan moderasi
4. Jika perlu, admin membuat koreksi poin bonus berdasar hasil review tersebut

### 6.3 Batasan penting
- hanya rekan yang hadir di hari yang sama yang bisa dinilai
- tidak boleh menilai diri sendiri
- satu pegawai hanya boleh menilai satu rekan satu kali per tanggal
- rating rendah wajib alasan
- hasil mentah hanya terlihat superadmin/moderator

## 7. Bentuk Data Utama

### 7.1 Konfigurasi bonus
`pay_bonus_config`

Fungsi:
- rumah pengaturan global bonus
- menyimpan mode perhitungan pool, persen bonus, hubungan ke target, dan flag faktor evaluasi

### 7.2 Rule bonus operasional
`pay_bonus_rule`

Fungsi:
- aturan yang berjalan di outlet/divisi tertentu
- menentukan apakah target wajib lolos, bagaimana PH diperlakukan, dan berapa penalti standar

### 7.3 Bobot bonus
`pay_bonus_weight_rule`

Fungsi:
- mengatur bobot divisi, jabatan, pegawai, atau shift

### 7.4 Tipe penalti
`pay_bonus_penalty_type`

Fungsi:
- master jenis penalti agar input manual konsisten

### 7.5 Kejadian penalti
`pay_bonus_penalty_event`

Fungsi:
- menyimpan penalti aktual per hari

### 7.6 Ringkasan service time
`pay_bonus_service_metric_daily`

Fungsi:
- cache harian kualitas penyajian supaya bonus tidak selalu hitung ulang dari order mentah

### 7.7 Pool bonus harian
`pay_bonus_pool_daily`

Fungsi:
- menyimpan ringkasan hari bonus: omzet, refund, target score, service score, pool amount

### 7.8 Pool per shift
`pay_bonus_pool_shift`

Fungsi:
- memecah pool harian per shift

### 7.9 Distribusi bonus pegawai harian
`pay_bonus_employee_daily`

Fungsi:
- menyimpan angka mentah dan final per pegawai per hari

### 7.10 Rekap bonus bulanan
`pay_bonus_monthly_summary`

Fungsi:
- rekap final yang siap dibaca payroll

### 7.11 Peer feedback
`perf_peer_feedback`

Fungsi:
- menampung rating 1-5 dan alasan antar pegawai

### 7.12 Koreksi bonus manual
`pay_bonus_manual_adjustment`

Fungsi:
- tempat superadmin menambah/mengurangi poin/nominal bonus secara akuntabel

## 8. Hubungan dengan Target Keuangan

Target dan bonus dipisah, tetapi saling bicara.

Pola yang dipakai:
- target hidup di `fin_target_plan`
- bonus rule boleh menunjuk `linked_target_plan_id`
- saat generate bonus harian/bulanan, sistem membaca skor realisasi target
- hasil target masuk ke `target_score_percent` dan `target_gate_passed`

Dengan ini:
- target tetap dikelola di modul target
- bonus cukup membaca hasil target, tidak menduplikasi definisi target

## 9. Hubungan dengan Payroll

Bonus final bulanan jangan langsung dicampur ke gaji harian mentah.
Alur aman:
1. bonus dihitung dan direkap di modul bonus
2. bonus bulanan yang sudah disetujui bisa dipush ke `pay_manual_adjustment` atau tabel payout bonus khusus pada periode payroll tertentu
3. slip gaji membaca bonus sebagai komponen terpisah

Keuntungan:
- bonus tidak mengotori logika payroll harian
- bonus bisa disetujui terlambat tanpa merusak histori absensi

## 10. Halaman yang Disiapkan

### 10.1 Admin
- `/payroll/bonus`
  - tab ringkasan
  - tab aturan bonus
  - tab pool bonus harian
  - tab penalti bonus
  - tab penilaian 360
  - tab panduan

### 10.2 Pegawai
- `/my/bonus`
  - ringkasan bonus saya
  - history bonus harian/bulanan
  - form penilaian rekan hari ini

### 10.3 Pengingat dari absensi
- `my/attendance` menampilkan pengingat bila ada rekan hari ini yang belum dinilai

## 11. Keputusan Desain Penting

1. Penilaian 360 tidak otomatis menjadi potongan bonus final tanpa moderasi.
2. PH dibaca sebagai kejadian khusus bonus, terpisah dari konsep libur biasa.
3. Waktu penyajian dibuat sebagai metrik harian tersendiri agar mudah diaudit.
4. Bonus bulanan tidak langsung memodifikasi payroll sampai status bonus disetujui.
5. Penalti manual personal dan tim sama-sama didukung.

## 12. Tahap Implementasi Teknis

### Tahap 1
- buat tabel fondasi bonus + peer review
- buat menu dan halaman workspace admin + pegawai
- tampilkan ringkasan data dan pengingat peer review

### Tahap 2
- CRUD aturan bonus
- CRUD tipe penalti
- submit peer review pegawai
- moderasi peer review superadmin

### Tahap 3
- generate pool bonus harian dari POS + target + service time
- distribusi bonus per pegawai per shift
- rekap bonus bulanan

### Tahap 4
- push bonus approved ke payroll period / manual adjustment
- pelaporan bonus lengkap

## 13. Catatan Penting

Kalau ingin hasil bonus dipercaya tim, modul ini harus bisa menjawab 3 pertanyaan:
- kenapa saya dapat bonus segini?
- kenapa rekan saya beda angkanya?
- potongan bonus ini asalnya dari mana?

Desain di atas sengaja dibuat supaya tiga pertanyaan itu selalu bisa dijawab dari data.
