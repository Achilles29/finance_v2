# Audit RBAC Menu dan Page Aktif

## Seed yang dijalankan sekarang

- `dashboard` perlu page baru `dashboard.index` karena route aktif `/` belum masuk `sys_page`.
- Menu `my.home`, `my.attendance`, `my.leave`, `my.payroll`, `my.profile`, `my.meal`, dan `my.cash_advance` perlu page baru agar muncul di matrix role dan benar-benar bisa dihormati controller.
- `grp.finance` dan `grp.purchase` tetap tanpa `page_id` karena hanya parent struktural dengan URL `#`.

## Page aktif tanpa menu aktif: keep

- `auth.roles.manage`, `auth.roles.matrix`, `auth.users.manage`, `auth.users.permissions`: page teknis untuk aksi lanjutan dari halaman daftar.
- `procurement.workbench.index`: anchor permission yang masih dipakai controller `Procurement::store_requests()`.
- `purchase.catalog.index`: endpoint/internal permission untuk pencarian katalog purchase.
- `purchase.stock.opening.index`: alias/redirect ke opening warehouse, masih dipakai route lama.
- `pos.member.index`: masih dipakai controller `Pos` untuk endpoint kompatibilitas member POS; row menu `pos.member` ada tetapi sedang nonaktif.

## Page aktif tanpa menu aktif: legacy candidate

- `master.purchase.company_account`: tidak lagi dipakai controller aktif; route/menu hidup memakai `finance.account.index` untuk `/master/company-account`.
- `procurement.purchasing.index`: tidak lagi dipakai controller aktif; route `procurement/purchasing-desk` sekarang diarahkan ke `store_requests()` dengan permission `procurement.workbench.index`.

## Catatan rollout

- Setelah SQL seed dijalankan, user perlu login ulang agar session `user_perms` memuat page baru.
- Controller `Dashboard` dan `My` sudah memakai guard aman: page baru hanya dipaksa jika row `sys_page` sudah ada, jadi deploy kode bisa mendahului eksekusi SQL server tanpa memutus akses.