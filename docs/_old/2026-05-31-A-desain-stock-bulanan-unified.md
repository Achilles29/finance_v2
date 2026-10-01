# Desain Stock Bulanan Per Domain

## Arah Desain

- Gudang, stok bahan baku divisi, dan component tidak digabung dalam satu tabel.
- Masing-masing domain punya tabel sendiri untuk `stock`, `opening`, dan `opname`.
- Movement log tetap menjadi source of truth dan audit trail.
- Daily matrix tetap diarahkan membaca movement log langsung, bukan projection bulanan.

## Tabel Baru

Migration awal ada di [finance/sql/2026-05-31b_inventory_monthly_unified_projection_foundation.sql](finance/sql/2026-05-31b_inventory_monthly_unified_projection_foundation.sql).

### Gudang

- `inv_warehouse_monthly_stock`
- `inv_warehouse_monthly_opening`
- `inv_warehouse_monthly_opname`

### Bahan baku divisi

- `inv_division_monthly_stock`
- `inv_division_monthly_opening`
- `inv_division_monthly_opname`

### Component

- `inv_component_monthly_stock`
- `inv_component_monthly_opening`
- `inv_component_monthly_opname`

Component saya pisahkan dari shape bahan baku. Dia tidak dipaksa membawa dimensi profil item atau material, tetapi tetap punya lokasi, divisi, component, uom, qty, dan nilai.

## Keputusan Kunci

- Warehouse dan division tetap memakai `identity_key` kanonik supaya identitas profil tidak pecah saat write.
- Division tetap membawa `destination_type` karena ini dimensi operasional yang nyata.
- Component tetap memakai model lokasi produksi dan tidak ikut skema profil atau FIFO bahan baku secara paksa.
- Satu domain satu tabel utama bulanan. Jadi query saldo bulanan tidak harus scan domain lain.

## Alur Yang Harus Dipindah

- Opening gudang: post, edit, import, void, rebuild.
- Opening divisi: post, edit, import, void, rebuild.
- Opening component: post, edit, import, void.
- PO masuk ke gudang dan void atau hapusnya.
- PO direct ke divisi dan void atau hapusnya.
- Store request keluar dari gudang dan rollback edit atau voidnya.
- POS usage ke divisi, termasuk void, refund, reopen, dan repair stok.
- Penyesuaian gudang berikut void atau hapusnya.
- Penyesuaian divisi berikut void atau hapusnya.
- Penyesuaian component berikut void atau hapusnya.
- Batch produksi component: post dan void.
- Reclassify profile, expiry normalization, merge identity, dan rebuild tools.
- Generate opname akhir bulan dan carry-forward opening bulan berikutnya.

## Catatan Tambahan

- Reconcile page dan daily matrix page harus tetap jujur terhadap sumber query aktual.
- Tabel component monthly lama dibackup dulu ke `inv_component_monthly_opening_backup_20260531` dan `inv_component_monthly_opname_backup_20260531` bila masih schema legacy, lalu schema baru memakai nama final tanpa suffix tambahan.
- Kalau nanti perlu rollback histori lama, backup table tetap tersedia sebagai referensi sebelum cutover.

## Urutan Implementasi Yang Disarankan

1. Finalkan schema per domain.
2. Pindahkan write path opening dan voidnya.
3. Pindahkan PO, SR, adjustment, POS, dan component batch.
4. Pindahkan reader saldo bulanan ke tabel baru per domain.
5. Ubah daily matrix agar membaca movement log langsung.
6. Tambahkan generator opname dan carry-forward opening per domain.