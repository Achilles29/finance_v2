# Final Legacy Registry Deletion List

Tanggal: 2026-06-15

Tujuan dokumen ini:
- Menentukan daftar final `sys_page` / `sys_menu` legacy yang aman dihapus permanen dari registry database
- Memisahkan mana yang benar-benar sudah tidak punya pemakai lagi
- Menghindari salah hapus alias route / helper internal yang masih dipakai code

Catatan penting:
- "Aman dihapus" di sini artinya aman dihapus dari registry database `sys_page` / `sys_menu`
- Bukan berarti route alias di `application/config/routes.php` harus ikut dihapus sekarang
- Beberapa URL lama memang masih sengaja dipertahankan sebagai redirect / compatibility route

## 1. Aman Dihapus Dari `sys_page`

Semua item di bawah ini sudah tidak dipakai lagi sebagai permission registry aktif.

| `page_code` | URL lama / konteks | Pengganti aktif | Status | Alasan |
| --- | --- | --- | --- | --- |
| `inventory.stock.daily.recon.division` | `/inventory/stock/daily-recon/division` | `inventory.stock.opname.division.index` | Aman hapus | URL tetap hidup, tapi registry page aktif sudah pindah ke page baru. |
| `production.component.opening.monthly` | `/production/component-opening-monthly` | tidak dipakai sebagai page registry aktif | Aman hapus | Route masih hidup, tetapi tidak lagi memakai page registry ini. |
| `master.component.index` | `/master/component` | `production.component.masters` | Aman hapus | Sidebar dan flow aktif sudah pindah ke master component versi produksi. |
| `master.component_formula.index` | `/master/relation/component-formula` | `production.component.formulas` | Aman hapus | Registry hak akses aktif tidak lagi memakai page lama. |
| `master.purchase.company_account` | `/master/company-account` | `finance.account.index` via `/finance/accounts` | Aman hapus | Controller/menu aktif sudah pindah ke rekening keuangan. |
| `master.purchase.payment_channel` | `/master/payment-channel` | tidak ada | Aman hapus | Halaman 404 dan tidak dipakai lagi. |
| `procurement.purchasing.index` | `/procurement/purchasing-desk` | `procurement.store_request.index` via `/store-requests` | Aman hapus | Route lama sekarang hanya redirect. |
| `purchase.account.index` | `/purchase/account` | `finance.account.index` via `/finance/accounts` | Aman hapus | Controller lama sekarang hanya redirect. |
| `purchase.stock.opening.index` | `/purchase/stock/opening` | `purchase.stock.opening.warehouse.index` via `/inventory/stock/opening/warehouse` | Aman hapus | Route lama sekarang hanya redirect. |
| `system.backup.guide` | `/dbtools/backup-guide` | `system.dbtools.settings` | Aman hapus | Permission aktif sekarang satu pintu ke `/dbtools`. |
| `system.replication.guide` | `/dbtools/replication-guide` | `system.dbtools.settings` | Aman hapus | Sama seperti backup guide, hanya alias ke halaman DB tools. |
| `grp.finance` | page registry parent lama | tidak perlu page registry | Aman hapus | Jika masih ada sebagai `sys_page`, ini hanya artefak lama. |
| `grp.purchase` | page registry parent lama | tidak perlu page registry | Aman hapus | Jika masih ada sebagai `sys_page`, ini hanya artefak lama. |

## 2. Aman Dihapus Dari `sys_menu`

Semua item di bawah ini aman dihapus permanen dari `sys_menu` karena sidebar aktif dan controller aktif tidak lagi memakainya.

| `menu_code` | URL lama | Pengganti aktif | Status | Alasan |
| --- | --- | --- | --- | --- |
| `master.component` | `/master/component` | `/production/component-masters` | Aman hapus | Sidebar aktif sudah pindah ke master produksi. |
| `master.component.formula` | `/master/relation/component-formula` | `/production/component-formulas` | Aman hapus | Sidebar aktif sudah pindah ke formula produksi. |
| `purchase.account` | `/purchase/account` | `/finance/accounts` | Aman hapus | Sidebar rekening sekarang ada di rumpun keuangan. |
| `master.purchase.payment_channel` | `/master/payment-channel` | tidak ada | Aman hapus | Menu rusak / 404 dan sudah tidak dipakai. |
| `system.backup.guide` | `/dbtools/backup-guide` | `/dbtools` | Aman hapus | Sudah digabung ke halaman DB tools. |
| `system.replication.guide` | `/dbtools/replication-guide` | `/dbtools` | Aman hapus | Sudah digabung ke halaman DB tools. |
| `pos.member` | `/pos/members` | `/loyalty/members` | Aman hapus | Menu lama tidak diperlukan lagi, walau route alias masih boleh hidup. |

## 3. Jangan Dihapus Dulu

Item berikut ini terlihat legacy, tetapi ternyata masih punya pemakai di code atau masih sengaja dipertahankan sebagai compatibility layer.

| Kode / URL | Kenapa jangan dihapus dulu |
| --- | --- |
| `pos.member.index` | Masih dipakai fallback permission di [Pos.php](c:\xampp\htdocs\finance\application\controllers\Pos.php). Menu boleh hilang, tapi page registry lama jangan dihapus dulu sampai fallback dibersihkan. |
| `/purchase/catalog/search` | Masih dipakai oleh [order_create.php](c:\xampp\htdocs\finance\application\views\purchase\order_create.php) dan [division_po_sr_form.php](c:\xampp\htdocs\finance\application\views\procurement\division_po_sr_form.php). |
| `/purchase/catalog/sync-core` | Masih dipanggil dari [order_create.php](c:\xampp\htdocs\finance\application\views\purchase\order_create.php) dan controller `Purchase::catalog_sync_core()`. |
| `/master/relation/component-formula/*` | Masih dipakai untuk edit/delete compatibility di [relation_form.php](c:\xampp\htdocs\finance\application\views\master\relation_form.php) dan [relation_list.php](c:\xampp\htdocs\finance\application\views\master\relation_list.php). |
| `grp.finance` dan `grp.purchase` pada `sys_menu` | Parent sidebar aktif. Yang aman dihapus hanya page registry palsunya bila ada, bukan menu parent-nya. |
| `production.component.opening.monthly` pada `sys_menu` | Row DB-nya memang sudah nonaktif, tetapi route dan `active_menu` compatibility masih dipakai halaman opening bulanan component. Jangan dianggap legacy mati total dulu. |
| `/procurement/purchasing-desk` | Route redirect masih aktif. Registry page lama boleh hilang, tetapi route aliasnya jangan dicabut dulu. |
| `/purchase/account` | Sama: page registry lama boleh hilang, tapi route alias redirect masih dipakai. |
| `/purchase/stock/opening` | Sama: masih dipakai sebagai alias redirect ke opening gudang. |
| `/dbtools/backup-guide` dan `/dbtools/replication-guide` | Route alias masih boleh dipertahankan walau menu/page registry lamanya dibersihkan. |

## 4. Ringkasan Praktis

Kalau targetnya sekarang adalah "bersihkan registry database tanpa memutus aplikasi", maka langkah paling aman adalah:

1. Hapus permanen `sys_menu`:
   - `master.component`
   - `master.component.formula`
   - `purchase.account`
   - `master.purchase.payment_channel`
   - `system.backup.guide`
   - `system.replication.guide`
   - `pos.member`

2. Hapus permanen `sys_page`:
   - `inventory.stock.daily.recon.division`
   - `production.component.opening.monthly`
   - `master.component.index`
   - `master.component_formula.index`
   - `master.purchase.company_account`
   - `master.purchase.payment_channel`
   - `procurement.purchasing.index`
   - `purchase.account.index`
   - `purchase.stock.opening.index`
   - `system.backup.guide`
   - `system.replication.guide`
   - `grp.finance`
   - `grp.purchase`

3. Tahan dulu:
   - `pos.member.index`
   - route/helper purchase catalog
   - route compatibility `master/relation/component-formula`

## 5. Referensi Code Utama

- [Pos.php](c:\xampp\htdocs\finance\application\controllers\Pos.php)
- [Procurement.php](c:\xampp\htdocs\finance\application\controllers\Procurement.php)
- [Purchase.php](c:\xampp\htdocs\finance\application\controllers\Purchase.php)
- [System_tools.php](c:\xampp\htdocs\finance\application\controllers\System_tools.php)
- [routes.php](c:\xampp\htdocs\finance\application\config\routes.php)
- [2026-06-14f_role_matrix_sidebar_alignment.sql](c:\xampp\htdocs\finance\sql\2026-06-14f_role_matrix_sidebar_alignment.sql)
