# C1 — Katalog Komersial Finance dan Control Center

**Tanggal:** 2026-09-07
**Status:** `CATALOG_DRAFT_READY` — belum menjadi release customer atau
enforcement runtime.

## Hasil

- `app-manifest.json` Finance v2 dibuat sebagai sumber katalog produk.
- Control mengimpor `NAMUA_FINANCE` sebagai metadata vendor dengan 28 feature,
  4 edition, dan 29 dependency feature.
- Edition awal: `STARTER_POS`, `OPERATIONS`, `CONTROL`, `ENTERPRISE`.
- Limit katalog awal: Starter 1 outlet/1 terminal; Operations dan Control 1
  outlet/3 terminal; Enterprise 5 outlet/5 terminal. Add-on dan override per
  customer belum dibangun.
- Hak pakai `PERPETUAL` dan `maintenance_ends_at` dipisahkan. Maintenance
  default 365 hari; habis maintenance tidak mengubah hak menjalankan versi
  Finance yang telah dibeli.
- Lease offline tetap metadata keamanan terpisah dan Finance memakai
  `WARN_ONLY`; belum ada FeatureGate di aplikasi Finance.

## Perubahan Control Center

- Migration `20260907100000_c1_perpetual_maintenance_and_feature_dependencies.sql`
  sudah dijalankan pada database Control staging.
- Registry source aman kini mengizinkan `/www/wwwroot/finance/app-manifest.json`.
- Preview Control menampilkan dependency feature serta model hak pakai dan
  maintenance.
- Backup otomatis sebelum migration tersimpan pada:
  `/www/backup/database/control/namua_control_center_20260907_061710.sql.gz`.

## Validasi

- Parser manifest Finance: 7/7 PASS, termasuk dependency tidak dikenal dan
  siklus dependency ditolak.
- Migration disposable Control: PASS.
- Contract registry catalog Control: PASS.
- Contract delivery/licensing Control: PASS.
- Security/static scan Control pada PHP 8.4: 61/61 PASS.
- Preflight release Finance, konsistensi roadmap, dan contract quality gate:
  PASS.

## Batas yang masih berlaku

- Source Finance masih dirty; katalog ini hanya draft dan tidak boleh dipakai
  membangun artefak customer.
- Tidak ada database/transaksi Finance, customer, instance, activation code,
  release, deployment, atau FeatureGate yang dibuat oleh batch ini.
- Harga, EULA/SLA, data policy, add-on, override entitlement per customer,
  installer, dan lisensi runtime tetap pekerjaan C1 lanjutan/C3/C4.
