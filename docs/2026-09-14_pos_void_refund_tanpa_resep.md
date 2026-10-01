# POS void/refund produk tanpa resep — Batch 252

- **Temuan terbukti dari kode/test:** konfirmasi produk tanpa resep (contoh event) menyimpan `stock_commit_status=NOT_REQUIRED` tanpa snapshot. `build_order_reversal_plan()` sebelumnya menolak snapshot kosong untuk semua order, sehingga preview dan kedua writer void/refund ikut gagal.
- **Perbaikan:** `application/models/Pos_model.php` mengizinkan plan kosong jika snapshot tidak ada **dan status tersimpan `NOT_REQUIRED`**. Produk tetap dapat dipilih untuk pembatalan; penghitungan nilai dan perubahan order tetap memakai jalur void/refund yang sama. Reversal stok kosong menjadi no-op, bukan membuat snapshot/lot/adjustment fiktif.
- **Order campuran aman:** snapshot yang ada dibaca lebih dahulu. Menambahkan event tanpa resep pada order yang sebelumnya memakai stok tidak membuat snapshot produk lama dilewati. Pemilihan event saja tidak mengembalikan stok produk lain.
- **Guard dipertahankan:** status `POSTED`, `FAILED`, pending/queued/processing, reversed atau tidak dikenal dengan snapshot hilang tetap ditolak; tidak menebak keamanan berdasarkan resep produk saat ini. Batas qty, pembayaran/refund tersisa, mutasi kas refund, lock, rollback, RBAC/CSRF/step-up tidak diubah.
- **Cakupan:** shared model untuk POS web dan API mobile. Tidak mengubah controller/kontrak APK, bridge Telegram, data transaksi, credential/server, SQL, atau melakukan deploy/push.
- **Bukti:** tes baru gagal pada kode lama, kemudian **45 pemeriksaan DB-free PASS** setelah patch. Reversal invariant, availability, step-up web/mobile, CSRF transaksi dan binding mobile writer juga PASS. Tes baru wajib di quality gate `a1-pos-reversal-no-stock`; kontrak daftar gate diperbarui untuk entry tersebut, tidak menurunkan gate.
- **Batas pengujian:** data sintetis/fake DB; menjalankan metode preview, selection, reversal no-op, dan kalkulasi refund asli, plus pemeriksaan jalur writer/otorisasi. Tidak menjalankan end-to-end transaksi POS atau mutasi rekening nyata pada DB aplikasi.

## Uji pengguna melalui POS → Kasir

- [ ] Order uji event tanpa resep, belum dibayar: buka Void/Refund, pilih item, lakukan VOID. Preview harus terbuka; tidak ada pengembalian stok.
- [ ] Order uji event sudah dibayar: pilih REFUND, metode pengembalian, dan item/qty. Coba sebagian dahulu, kemudian sisa. Pengembalian tidak boleh melebihi nilai pembayaran yang masih dapat dikembalikan menurut kebijakan existing.
- [ ] Order campuran event + produk resep: batalkan event saja dan pastikan stok produk lain tetap. Batalkan produk resep dan pastikan reversal mengikuti snapshot produk tersebut.
- [ ] Coba ulang order yang sudah dibatalkan/refund penuh; tetap tidak boleh membatalkan/mengembalikan uang kedua kali.

Tidak ada SQL yang perlu dijalankan untuk perbaikan ini. Catatan audit/progress utama tetap pada dokumen `_30`; pengambilan cutoff rilis berikutnya dicatat di `_28`.
