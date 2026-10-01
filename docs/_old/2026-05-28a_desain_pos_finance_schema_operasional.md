# Desain POS Finance: Schema Operasional + Database Penunjang
**Tanggal:** 2026-05-28  
**Status:** Draft revisi untuk verifikasi sebelum implementasi SQL final dan UI

## 1) Tujuan
Dokumen ini menyiapkan fondasi database POS di repo `finance` dengan pendekatan:
1. Mengadopsi domain POS dari `core` yang sudah terbukti berjalan.
2. Menyesuaikan struktur agar lebih efisien untuk kebutuhan `finance`.
3. Menyiapkan domain utama dan domain penunjang sebelum kita membangun kasir desktop dan mobile.
4. Menjaga performa POS tetap ringan walaupun stok produk bergantung pada resep dan stok bahan/component.

Domain yang dicakup pada tahap ini:
1. Member dan promo: point, voucher, stamp.
2. Metode pembayaran.
3. Void.
4. Refund.
5. Shift kasir.
6. Produk paket.
7. Pengaturan printer.
8. Availability produk berbasis resep + override produk.

## 2) Keputusan Revisi Penting

### 2.1 Member adalah entitas utama
Keputusan revisi:
1. Yang dihidupkan adalah database member.
2. POS saat mengetik nama/no HP/member no akan mencari ke database member.
3. Jika ada, kasir memilih member yang cocok lalu transaksi menyimpan `member_id`.
4. Jika tidak ada atau tidak dipilih, transaksi tetap jalan dengan `member_id = NULL`.
5. Walk-in customer tidak perlu tabel sendiri.

Konsekuensi:
1. Domain `crm_customer` dihapus dari rancangan POS tahap ini.
2. Diganti dengan satu master `crm_member`.
3. Semua loyalty dan promo terhubung ke `crm_member`.
4. Jika nanti aplikasi member dibuat, tabel ini sudah menjadi single source of truth.
5. Tabel member harus cukup lengkap sejak awal agar tidak bongkar fondasi lagi nanti.

### 2.2 Voucher bisa umum atau tertaut member
Keputusan revisi:
1. Voucher tetap bisa bersifat umum.
2. Voucher juga bisa tertaut ke member tertentu.
3. Karena itu instance voucher aktual harus punya `member_id` yang nullable.

Artinya:
1. Jika voucher hasil promo transaksi member, maka voucher di-issue ke `member_id` tersebut.
2. Jika voucher umum, `member_id = NULL`.
3. POS tetap bisa mengelola dua pola voucher tanpa domain ganda.

### 2.3 Availability cache tetap dipakai
Keputusan revisi:
1. `pos_product_availability_cache` tetap dipakai.
2. Tetapi cache wajib di-update langsung setiap ada transaksi yang mempengaruhi stok atau resep.

Artinya:
1. Cache bukan snapshot harian.
2. Cache bersifat event-driven dan near real-time.
3. POS membaca cache agar cepat.
4. Final guard tetap boleh dilakukan saat add-to-cart atau checkout.

### 2.4 Stok POS dicatat saat commit konsumsi, bukan saat payment
Keputusan revisi:
1. Pengurangan stok dan update availability cache tidak menunggu payment.
2. Update stok dilakukan saat transaksi POS sudah masuk ke tahap commit konsumsi stok.
3. Payment menyelesaikan sisi finansial, bukan memicu pengurangan stok utama.

## 3) Prinsip Adopsi dari Core ke Finance
1. Prefix POS tetap `pos_`.
2. Prefix member tetap `crm_`.
3. Master produk tetap memakai `mst_product`.
4. Extra/topping tetap memakai `mst_extra`, `mst_extra_group`, `mst_product_extra_map`.
5. Metode pembayaran POS wajib terhubung ke `fin_company_account`.
6. Actor transaksi POS memakai `org_employee`.
7. Printer dibagi dua lapis:
   - pengaturan tampilan dokumen disimpan di database umum
   - device printer desktop disimpan terpisah
   - device printer mobile disiapkan nanti saat mulai develop mobile
8. POS tidak menghitung availability dari nol di request list produk kasir.

## 4) Mapping Master Utama
1. Produk jual: `mst_product`
2. Resep produk: `mst_product_recipe`
3. Base/prepare: `mst_component`
4. Formula base/prepare: `mst_component_formula`
5. Bahan baku: `mst_material`
6. Extra/topping: `mst_extra`
7. Satuan: `mst_uom`
8. Rekening/kas perusahaan: `fin_company_account`
9. Pegawai: `org_employee`
10. Divisi produk: `mst_product_division`
11. Divisi operasional: `mst_operational_division`

## 5) Domain Schema yang Diusulkan

### 5.1 Domain Outlet, Terminal, Session, Shift
Tabel utama:
1. `pos_outlet`
2. `pos_terminal`
3. `pos_shift`
4. `pos_shift_summary`
5. `pos_cashier_session`

### 5.2 Domain Member, Loyalty, Promo
Tabel utama yang diringkas:
1. `crm_member`
2. `pos_point_rule`
3. `pos_stamp_campaign`
4. `pos_voucher_campaign`
5. `pos_point_ledger`
6. `pos_stamp_ledger`
7. `pos_voucher_issue`
8. `pos_voucher_redemption`

Alasan peringkasan:
1. Fondasi awal lebih ringan.
2. Cache saldo point dan stamp langsung disimpan di `crm_member`.
3. Bucket/usage detail bisa ditambahkan nanti jika aturan expiry/redeem menjadi sangat kompleks.
4. Untuk migrasi bertahap, rule/campaign dipisah dari runtime ledger agar dependency FK ke `pos_order`/`pos_payment` tidak melingkar.

### 5.3 Domain Order dan Payment POS
Tabel utama:
1. `pos_order`
2. `pos_order_line`
3. `pos_order_line_extra`
4. `pos_order_state_log`
5. `pos_payment_method`
6. `pos_payment`
7. `pos_payment_line`

### 5.4 Domain Void dan Refund
Tabel utama:
1. `pos_void`
2. `pos_void_line`
3. `pos_void_line_extra`
4. `pos_refund`
5. `pos_refund_line`

Catatan:
1. `pos_refund_line_extra` tidak perlu tabel terpisah.
2. `pos_refund_line` cukup menyimpan `line_type`:
   - `PRODUCT`
   - `EXTRA`
3. Void tetap memakai detail extra terpisah karena auditnya lebih granular.

### 5.5 Domain Produk Paket
Tabel utama:
1. `pos_product_bundle`
2. `pos_product_bundle_line`

### 5.5.1 Standar harga bundle untuk laporan profit
Keputusan yang disarankan:
1. Harga jual riil bundle adalah `selling_price` pada header bundle / order line bundle.
2. Harga normal item penyusun tetap dibaca dari harga jual produk masing-masing.
3. Jika line bundle **tidak** diisi `unit_price_override`, sistem **tidak** memakai harga normal penuh untuk laporan profit line.
4. Sebagai gantinya, sistem mengalokasikan harga jual riil bundle ke tiap line secara **proporsional** terhadap harga normal line.
5. `unit_price_override` hanya dipakai bila bisnis memang ingin membebankan nilai bundle secara manual ke item tertentu.

Contoh:
1. Bebek = `43.000`
2. Es Teh = `10.000`
3. Total normal = `53.000`
4. Harga bundle = `50.000`
5. Maka jika tanpa override manual, sistem mengalokasikan:
   - Bebek = `43/53 x 50.000`
   - Es Teh = `10/53 x 50.000`

Alasan:
1. Jika laporan profit per produk memakai harga normal penuh `53.000`, maka revenue line akan lebih besar dari revenue transaksi riil.
2. Jika semua bundle dipaksa override manual, operasional akan berat dan rawan salah input.
3. Auto-proportional memberi keseimbangan antara akurasi margin dan efisiensi input.

Rule operasional:
1. Default mode bundle = `AUTO_PROPORTIONAL`.
2. Jika ada `unit_price_override` per line, line tersebut memakai nilai override.
3. Jika sebagian line di-override dan sebagian tidak, sisa nilai bundle dialokasikan proporsional ke line yang tidak di-override.
4. Jumlah final seluruh line harus sama persis dengan harga jual riil bundle setelah pembulatan.

### 5.6 Domain Printer POS
Tabel umum lintas desktop/mobile:
1. `pos_printer_template_master`
2. `pos_printer_template`
3. `pos_printer_profile`
4. `pos_printer_content_setting`
5. `pos_printer_event_setting`
6. `pos_printer_route_rule`
7. `pos_printer_job`
8. `pos_printer_job_log`

Tabel device desktop:
1. `pos_printer_desktop_device`

### 5.7 Domain Availability Produk POS
Tabel utama:
1. `pos_product_availability_cache`
2. `pos_product_availability_override`

## 6) Aturan Availability Produk POS

### 6.1 Sumber hitung
Availability produk dihitung dari:
1. `mst_product_recipe`
2. stok material aktif
3. stok component aktif
4. konversi UOM yang valid
5. override manual bila ada
6. jenis bahan pada recipe line

### 6.2 Kenapa tidak hitung live saat list POS dibuka
Secara teori bisa, tetapi akan berat jika:
1. jumlah produk banyak
2. recipe produk bercabang ke component
3. component punya formula sendiri
4. stok sudah memakai FIFO/layer
5. kasir desktop dan mobile sama-sama aktif

Risiko jika hitung live di request:
1. load POS lambat
2. query meledak
3. lock ke tabel stok meningkat
4. pengalaman kasir jelek saat jam sibuk

### 6.3 Model yang disarankan
1. List produk POS membaca `pos_product_availability_cache`.
2. Cache di-update langsung saat ada transaksi yang mempengaruhi.
3. Event minimum yang wajib memicu rebuild:
   - receipt purchase
   - store request fulfillment / reversal
   - stock adjustment
   - opening stock
   - production batch component
   - component adjustment/opening
   - void/refund yang return stock
   - perubahan recipe produk
   - perubahan formula component
   - perubahan override produk
4. Saat add-to-cart atau checkout, sistem boleh melakukan guard final untuk produk yang sedang dipilih.

### 6.4 Informasi minimum di cache
1. status tersedia
2. mode sumber `AUTO` atau `OVERRIDE`
3. estimasi qty jual tersedia
4. bottleneck material/component
5. snapshot HPP live
6. timestamp compute
7. dirty flag bila perlu rebuild
8. status apakah ada line non-main yang kosong

### 6.5 Override berdasarkan jenis bahan recipe
Flow yang disarankan:
1. Saat produk dipilih, sistem mengecek line recipe yang menjadi bottleneck.
2. Jika bottleneck berasal dari bahan `MAIN`, produk tidak boleh dioverride menjadi available.
3. Jika bottleneck berasal dari bahan pelengkap/non-main, produk masih boleh dijual lewat override.
4. POS harus menampilkan bahan mana yang kosong agar kasir mengerti kenapa produk tertahan atau kenapa override diperlukan.

Contoh:
1. `Nasi Ayam Goreng`
2. ayam dan nasi tersedia
3. timun kosong
4. kalau timun bertipe pelengkap, produk masih bisa dioverride
5. kalau ayam bertipe `MAIN`, produk tidak boleh dioverride available

Catatan:
1. POS perlu membaca peran bahan dari recipe line.
2. Jika recipe line di `finance` belum cukup eksplisit, kita perlu hardening kecil di schema recipe agar role `MAIN` vs `PELENGKAP` konsisten.

## 7) HPP Live, FIFO, dan Fallback untuk POS

### 7.1 Arah yang disarankan
POS harus membaca HPP live dari hasil perhitungan recipe yang memperhatikan stok FIFO material/component agar:
1. margin lebih realistis
2. refund/void reversal lebih akurat
3. laporan COGS kasir lebih valid

### 7.2 Fallback saat stok live kosong
Fallback yang disarankan:
1. pakai layer FIFO yang masih valid
2. kalau tidak ada, fallback ke cache HPP live material/component/product
3. kalau tetap tidak ada, fallback ke `hpp_standard`

Catatan:
1. POS tetap harus bisa transaksi saat data live tidak sempurna.
2. Tetapi sumber fallback harus tercatat jelas di snapshot cost.

## 8) Void dan Refund Operasional

### 8.1 Modal alert status proses
Saat void atau refund, sistem wajib memunculkan alert:
1. apakah produk sudah diproses atau belum
2. apakah stok perlu dikembalikan

### 8.2 Jika produk belum diproses
1. tampilkan checklist `kembalikan ke stok`
2. jika diceklis, sistem reversal recipe consumption
3. cache availability ikut di-update langsung

### 8.3 Jika produk sudah diproses
1. produk tidak bisa sekadar dikembalikan ke stok
2. checkbox return to stock harus nonaktif
3. sistem membuat adjustment otomatis untuk bahan/component sesuai kebijakan disposisi

Catatan:
1. Di tahap awal, minimal sistem harus membedakan `processed` vs `not processed`
2. Pada hardening phase, disposal bisa dipisah menjadi `WASTE`, `SPOIL`, atau `RETURN`

## 9) Status dan Audit Minimum
1. Semua header transaksi harus punya `status`.
2. Semua dokumen reversal harus punya `reason`, `actor`, `timestamp`.
3. Semua tabel POS utama minimal punya:
   - `created_at`
   - `updated_at`
4. Semua transaksi kritikal wajib punya nomor dokumen unik:
   - `order_no`
   - `payment_no`
   - `shift_no`
   - `void_no`
   - `refund_no`

## 10) Rekomendasi Urutan Implementasi

### Tahap 1 - Foundation wajib
1. `pos_outlet`
2. `pos_terminal`
3. `pos_shift`
4. `pos_shift_summary`
5. `pos_cashier_session`
6. `crm_member`
7. `pos_payment_method`
8. `pos_order`
9. `pos_order_line`
10. `pos_order_line_extra`
11. `pos_order_state_log`
12. `pos_payment`
13. `pos_payment_line`
14. `pos_product_availability_cache`
15. `pos_product_availability_override`

### Tahap 2 - Penunjang operasional wajib
1. loyalty: point, stamp, voucher
2. bundle
3. printer common settings
4. printer desktop device
5. void
6. refund

### Tahap 3 - Hardening
1. full FIFO consumption untuk stok POS
2. mobile printer device setting
3. promo rule yang lebih kompleks
4. worker rebuild availability + printer queue
5. deposit/reservation/manual discount/compliment

## 11) Rancangan Detail Member dan Loyalty

### 11.1 `crm_member`
Kolom minimum yang disarankan:
1. `member_no`
2. `member_name`
3. `mobile_phone`
4. `email`
5. `birth_date`
6. `gender`
7. `address`
8. `city`
9. `postal_code`
10. `emergency_contact_name`
11. `emergency_contact_phone`
12. `member_tier`
13. `joined_at`
14. `point_balance_cache`
15. `stamp_balance_cache`
16. `total_spending`
17. `member_status`
18. `notes`

### 11.2 Point yang diringkas
Tahap awal:
1. `pos_point_rule`
2. `pos_point_ledger`

Catatan:
1. saldo cepat dibaca dari `crm_member.point_balance_cache`
2. bucket detail belum wajib di phase awal
3. jika expiry point makin kompleks, bucket bisa ditambahkan nanti

### 11.3 Stamp yang diringkas
Tahap awal:
1. `pos_stamp_campaign`
2. `pos_stamp_ledger`

Catatan:
1. saldo cepat dibaca dari `crm_member.stamp_balance_cache`
2. usage detail belum wajib di phase awal

### 11.4 Voucher yang diringkas
Tahap awal:
1. `pos_voucher_campaign`
2. `pos_voucher_issue`
3. `pos_voucher_redemption`

Fungsi:
1. `pos_voucher_campaign` = aturan voucher
2. `pos_voucher_issue` = voucher instance aktual
3. `pos_voucher_redemption` = histori pemakaian voucher

Rule:
1. `member_id` di `pos_voucher_issue` nullable
2. jika voucher hasil promo transaksi member, `member_id` diisi
3. jika voucher umum, `member_id = NULL`

## 12) Poin Approval
1. Domain member dihidupkan sebagai `crm_member`, tanpa tabel customer umum terpisah.
2. Walk-in transaction cukup `member_id = NULL`.
3. `pos_product_availability_cache` tetap dipakai, tetapi update-nya wajib event-driven.
4. Override availability harus mengikuti jenis bahan `MAIN` vs `PELENGKAP`.
5. Void tetap punya tabel extra terpisah, refund tidak perlu.
6. Voucher bisa umum atau tertaut ke member.
7. Printer setting umum dipisah dari device setting agar siap dipakai desktop dan mobile.
8. Pengurangan stok POS dan update cache mengikuti commit konsumsi stok, bukan payment.

## 13) Output Tahap Ini
Tahap ini menyiapkan:
1. blueprint schema POS versi `finance`
2. draft migrasi SQL foundation POS tahap awal

Belum termasuk:
1. UI kasir
2. menu/sidebar POS
3. posting stok POS detail
4. printer mobile
5. deposit/reservation flow
6. manual discount dan compliment approval flow
