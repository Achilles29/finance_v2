# Panduan Pola Coding — Finance App
**Status:** WAJIB DIBACA sebelum coding  
**Terakhir diperbarui:** 2026-05-30  
**Berlaku untuk:** Semua pengembangan di direktori `finance/`

> Dokumen ini adalah **satu-satunya acuan pola coding**. Setiap halaman baru, controller baru, atau fitur baru harus mengikuti pola di sini — tidak boleh improvisasi sendiri. Jika ada pola baru yang disepakati, tuliskan di sini, jangan hanya di kepala atau di chat.

---

## DAFTAR ISI

1. [Stack & Teknologi](#1-stack--teknologi)
2. [Struktur Direktori](#2-struktur-direktori)
3. [Konvensi Nama Database](#3-konvensi-nama-database)
4. [Pola Controller](#4-pola-controller)
5. [Pola Model](#5-pola-model)
6. [Pola View — Struktur Halaman](#6-pola-view--struktur-halaman)
7. [Pola View — Judul Halaman](#7-pola-view--judul-halaman)
8. [Pola Filter & Search](#8-pola-filter--search)
9. [Pola Tabel Data](#9-pola-tabel-data)
10. [Pola Pagination](#10-pola-pagination)
11. [Pola AJAX & Fetch API](#11-pola-ajax--fetch-api)
12. [Pola Form Modal](#12-pola-form-modal)
13. [Flash Message](#13-flash-message)
14. [Format Angka & Tanggal](#14-format-angka--tanggal)
15. [Keamanan & Escaping](#15-keamanan--escaping)
16. [Status Badge](#16-status-badge)
17. [Tombol Aksi](#17-tombol-aksi)
18. [Dialog Konfirmasi UI](#18-dialog-konfirmasi-ui)
19. [Pola Routing](#19-pola-routing)
20. [Pola Permission](#20-pola-permission)
21. [Summary Card](#21-summary-card)
22. [Pola Audit Log](#22-pola-audit-log)
23. [Standar Tab Bertingkat (Penghubung/Jenis/Halaman)](#23-standar-tab-bertingkat-penghubungjenishalaman)

---

## 1. Stack & Teknologi

| Layer | Keputusan | Catatan |
|---|---|---|
| Backend | CodeIgniter 3.1.x | Tidak naik ke CI4 |
| PHP | 7.4+ | XAMPP lokal dan server |
| Database | MySQL 5.7+ / MariaDB | Database `db_finance` (terpisah dari `core`) |
| Frontend | Bootstrap 5 + Materio theme | Tema merah (#c0392b) + krem |
| Icons | Remix Icons (`ri-*`) | Muat dari `assets/vendor/fonts/iconify-icons.css`, bukan subset parsial |
| DataTable | Server-side via PHP, bukan DataTables.js | Gunakan pola tabel + pagination manual |
| AJAX | Fetch API (bukan jQuery $.ajax) | `Content-Type: application/json` |
| CSS Custom | `assets/css/app.css` | Tambahkan di sini, bukan inline style |
| JS Custom | `assets/js/app.js` | Tambahkan fungsi global di sini |

**Catatan:** `jQuery` masih ada (via Materio) tapi **penggunaan baru pakai Fetch API**, bukan `$.ajax()`.

---

## 2. Struktur Direktori

```
finance/
├── application/
│   ├── config/           — konfigurasi CI (database, routes, autoload)
│   ├── controllers/      — 1 file per modul utama (Purchase.php, Payroll.php, dll.)
│   ├── models/           — 1 file per entitas utama (Purchase_model.php, dll.)
│   ├── views/
│   │   ├── layout/       — template bersama (JANGAN diubah tanpa koordinasi)
│   │   │   ├── header.php   — <head>, CSS
│   │   │   ├── main.php     — wrapper layout + flash message
│   │   │   ├── navbar.php   — top bar
│   │   │   ├── sidebar.php  — navigasi kiri
│   │   │   └── footer.php   — JS libraries
│   │   ├── auth/         — login, dll.
│   │   ├── purchase/     — semua view Purchase controller
│   │   ├── payroll/      — semua view Payroll controller
│   │   └── [modul]/      — folder = nama controller (lowercase)
│   ├── core/
│   │   └── MY_Controller.php  — base controller (JANGAN diubah kecuali perlu)
│   ├── helpers/
│   │   └── ui_number_helper.php  — fungsi ui_num()
│   └── libraries/
│       └── InventoryLedger.php
├── assets/
│   ├── css/app.css       — custom CSS global
│   ├── js/app.js         — custom JS global
│   └── libs/             — vendor libraries
├── docs/                 — dokumentasi (file ini ada di sini)
└── sql/                  — migration scripts
```

---

## 3. Konvensi Nama Database

### Prefix Tabel

| Prefix | Domain |
|---|---|
| `sys_` | Sistem: menu, konfigurasi, audit log |
| `auth_` | Autentikasi & RBAC |
| `org_` | Organisasi & HR: karyawan, jabatan, divisi |
| `att_` | Absensi |
| `pay_` | Payroll & pencairan gaji |
| `mst_` | Master data lintas domain |
| `pur_` | Pembelian (purchase) |
| `inv_` | Inventori & gudang |
| `prd_` | Produksi |
| `pos_` | Point of Sale |
| `crm_` | Customer & loyalty |
| `fin_` | Keuangan & akuntansi |
| `rpt_` | View/tabel laporan |

### Konvensi Kolom

| Tipe | Konvensi | Contoh |
|---|---|---|
| Primary key | `id` BIGINT UNSIGNED AUTO_INCREMENT | Semua tabel |
| Foreign key | `{entitas}_id` | `employee_id`, `order_id` |
| Nomor dokumen | `{entitas}_no` | `po_no`, `order_no` |
| Kode unik | `{entitas}_code` | `employee_code` |
| Status | `status` ENUM | `'DRAFT'`, `'ACTIVE'`, `'VOID'` |
| Boolean | `is_{kondisi}` TINYINT(1) | `is_active`, `is_locked` |
| Snapshot | `{field}_snapshot` | `employee_name_snapshot` |
| Timestamp | `created_at`, `updated_at` DATETIME | Semua tabel |
| Dibuat oleh | `created_by` INT (FK ke `auth_user.id`) | |
| Nominal/Amount | DECIMAL(18,2) | `amount`, `total_amount` |
| Quantity bahan | DECIMAL(18,6) | Lebih presisi untuk perhitungan |
| Quantity orang | DECIMAL(10,2) | `qty_person` |

### Format Nomor Dokumen

| Dokumen | Format | Contoh |
|---|---|---|
| Purchase Order | `PO-{YYYY}{MM}-{NNN}` | `PO-202605-001` |
| Store Request | `SR-{YYYY}{MM}-{NNN}` | `SR-202605-001` |
| Disbursement Gaji | `SLD-{YYYY}{MM}-{NNN}` | `SLD-202605-001` |
| Kasbon | `KSB-{YYYY}{MM}-{NNN}` | `KSB-202605-001` |
| Uang Makan | `UM-{YYYY}W{WW}-{NNN}` | `UM-2026W18-001` |
| POS Order | `ORD-{YYYYMMDD}-{NNN}` | `ORD-20260501-001` |
| NIP Karyawan | `EMP-{YYYY}{MM}{NNN}` | `EMP-2026050001` |

### Konvensi File SQL

Format: `YYYY-MM-DD[a/b/c]_deskripsi_singkat.sql`  
Huruf suffix = urutan eksekusi di hari yang sama.

**Prinsip:** Tidak ada ALTER TABLE tanpa file SQL baru. Perubahan tabel = file baru di `sql/`.

---

## 4. Pola Controller

```php
<?php
// Nama file: NamaModul.php
// Extends MY_Controller — WAJIB

class Purchase extends MY_Controller
{
    // === KONSTANTA PERMISSION ===
    // Format: '{modul}.{sub}.{action}' atau '{modul}.{sub}'
    const PAGE_ORDER       = 'purchase.order';
    const PAGE_CATALOG     = 'purchase.catalog';
    const PAGE_RECEIPT     = 'purchase.receipt';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Purchase_model');
    }

    // === HALAMAN DAFTAR (INDEX) ===
    public function index()
    {
        $this->require_permission(self::PAGE_ORDER, 'view');

        // Sanitasi input filter
        $q      = trim((string)($this->input->get('q', true) ?? ''));
        $status = strtoupper(trim((string)($this->input->get('status', true) ?? '')));
        $page   = max(1, (int)($this->input->get('page', true) ?? 1));
        $limit  = (int)($this->input->get('limit', true) ?? 50);
        if ($limit <= 0 || $limit > 300) $limit = 50;

        $data = [
            'title'       => 'Purchase Order',           // Judul halaman
            'active_menu' => 'purchase.order',           // Kode menu sidebar
            'q'           => $q,
            'status'      => $status,
            'page'        => $page,
            'limit'       => $limit,
            'rows'        => $this->Purchase_model->list_orders($q, $status, $page, $limit),
            'total'       => $this->Purchase_model->count_orders($q, $status),
            'summary'     => $this->Purchase_model->get_summary(),
        ];

        $this->render('purchase/index', $data);
    }

    // === HALAMAN FORM BUAT BARU ===
    public function create()
    {
        $this->require_permission(self::PAGE_ORDER, 'create');
        $data = [
            'title'       => 'Buat Purchase Order',
            'active_menu' => 'purchase.order',
            'vendors'     => $this->Purchase_model->get_vendors(),
        ];
        $this->render('purchase/form', $data);
    }

    // === ENDPOINT SIMPAN (AJAX POST) ===
    public function store()
    {
        if (!$this->can(self::PAGE_ORDER, 'create')) {
            $this->jsonError('Tidak ada izin.', 403);
            return;
        }

        $payload = $this->requestPayload();   // Ambil JSON body

        // Validasi
        if (empty($payload['vendor_id'])) {
            $this->jsonError('Vendor wajib diisi.', 422);
            return;
        }

        $result = $this->Purchase_model->create_order($payload, $this->current_user['id']);

        if (!($result['ok'] ?? false)) {
            $this->jsonError($result['message'] ?? 'Gagal menyimpan.', 422);
            return;
        }

        $this->jsonOk($result['data'] ?? [], 'Purchase Order berhasil dibuat.');
    }

    // === ENDPOINT DELETE (AJAX) ===
    public function delete($id = 0)
    {
        if (!$this->can(self::PAGE_ORDER, 'delete')) {
            $this->jsonError('Tidak ada izin.', 403);
            return;
        }
        $result = $this->Purchase_model->delete_order((int)$id, $this->current_user['id']);
        if (!($result['ok'] ?? false)) {
            $this->jsonError($result['message'] ?? 'Gagal menghapus.', 422);
            return;
        }
        $this->jsonOk([], 'Berhasil dihapus.');
    }
}
```

**Aturan penting controller:**
- Semua controller extends `MY_Controller`
- Konstanta permission wajib didefinisikan di atas
- Input SELALU di-sanitasi sebelum dipakai (`trim`, cast tipe, range check)
- AJAX endpoint gunakan `$this->jsonOk()` / `$this->jsonError()`
- `active_menu` harus cocok dengan kode menu di database agar sidebar aktif benar

---

## 5. Pola Model

```php
<?php

class Purchase_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // === QUERY LIST (dengan filter + pagination) ===
    public function list_orders(string $q, string $status, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        $this->db->select('po.*, v.vendor_name');
        $this->db->from('pur_purchase_order po');
        $this->db->join('mst_vendor v', 'v.id = po.vendor_id', 'left');

        if ($q !== '') {
            $this->db->group_start();
            $this->db->like('po.po_no', $q);
            $this->db->or_like('v.vendor_name', $q);
            $this->db->group_end();
        }
        if ($status !== '') {
            $this->db->where('po.status', $status);
        }

        $this->db->order_by('po.created_at', 'DESC');
        $this->db->limit($limit, $offset);

        return $this->db->get()->result_array();
    }

    public function count_orders(string $q, string $status): int
    {
        // Query sama tapi tanpa limit/offset, hanya COUNT
        $this->db->from('pur_purchase_order po');
        $this->db->join('mst_vendor v', 'v.id = po.vendor_id', 'left');
        if ($q !== '') {
            $this->db->group_start();
            $this->db->like('po.po_no', $q);
            $this->db->or_like('v.vendor_name', $q);
            $this->db->group_end();
        }
        if ($status !== '') {
            $this->db->where('po.status', $status);
        }
        return (int)$this->db->count_all_results();
    }

    // === WRITE (INSERT/UPDATE) — selalu return array {ok, message, data} ===
    public function create_order(array $payload, int $user_id): array
    {
        $this->db->trans_begin();
        try {
            // Insert
            $this->db->insert('pur_purchase_order', [
                'vendor_id'  => (int)$payload['vendor_id'],
                'status'     => 'DRAFT',
                'created_by' => $user_id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $id = $this->db->insert_id();

            $this->db->trans_commit();
            return ['ok' => true, 'message' => 'Berhasil', 'data' => ['id' => $id]];
        } catch (Exception $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
```

**Aturan penting model:**
- Semua fungsi write (insert/update/delete) mengembalikan `['ok' => bool, 'message' => string, 'data' => array]`
- Operasi multi-tabel WAJIB pakai `trans_begin` / `trans_commit` / `trans_rollback`
- Tidak ada logika presentasi di model (tidak ada `htmlspecialchars`, tidak ada `number_format`)
- Gunakan Query Builder CI3, bukan raw query kecuali kompleksitas mengharuskan

---

## 6. Pola View — Struktur Halaman

**Setiap halaman view mengikuti urutan ini persis:**

```php
<?php
// ============================================================
// BAGIAN 1: Persiapan data PHP (di atas, sebelum HTML)
// ============================================================
$baseUrl    = site_url('purchase-orders');
$createUrl  = site_url('purchase-orders/create');
$rows       = is_array($rows ?? null) ? $rows : [];
$q          = $q ?? '';
$statusFilter = $status ?? '';

// Hitung pagination
$totalPages = $limit > 0 ? (int)ceil($total / $limit) : 1;
$prevPage   = max(1, $page - 1);
$nextPage   = min($totalPages, $page + 1);

// Helper URL builder (pertahankan filter saat ganti halaman)
$buildQuery = function(array $override = []) use ($q, $statusFilter, $limit): string {
    return http_build_query(array_filter(array_merge(
        ['q' => $q, 'status' => $statusFilter, 'limit' => $limit],
        $override
    ), fn($v) => $v !== '' && $v !== null));
};
?>

<!-- ============================================================ -->
<!-- BAGIAN 2: Judul Halaman + Tombol Aksi                       -->
<!-- ============================================================ -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">
            <i class="ri ri-shopping-cart-2-line me-1 text-primary"></i>
            <?= html_escape($title) ?>
        </h4>
        <small class="text-muted">Kelola semua purchase order</small>
    </div>
    <div class="d-flex gap-2">
        <?php if ($canCreate): ?>
        <a href="<?= $createUrl ?>" class="btn btn-primary">
            <i class="ri ri-add-line me-1"></i>Buat PO
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- BAGIAN 3: Summary Cards (jika ada)                          -->
<!-- ============================================================ -->
<!-- Lihat Bagian 20 untuk pola summary card -->

<!-- ============================================================ -->
<!-- BAGIAN 4: Alert Area (untuk pesan AJAX)                     -->
<!-- ============================================================ -->
<div id="alert-area" class="mb-3"></div>

<!-- ============================================================ -->
<!-- BAGIAN 5: Filter & Search                                    -->
<!-- ============================================================ -->
<!-- Lihat Bagian 8 untuk pola filter -->

<!-- ============================================================ -->
<!-- BAGIAN 6: Tabel Data                                        -->
<!-- ============================================================ -->
<!-- Lihat Bagian 9 untuk pola tabel -->

<!-- ============================================================ -->
<!-- BAGIAN 7: Modal (jika ada)                                  -->
<!-- ============================================================ -->
<!-- Lihat Bagian 12 untuk pola modal -->

<!-- ============================================================ -->
<!-- BAGIAN 8: Script JS (di bawah semua HTML)                   -->
<!-- ============================================================ -->
<script>
// Semua JS untuk halaman ini di sini
// Gunakan fungsi dari app.js jika tersedia
</script>
```

---

## 7. Pola View — Judul Halaman

**Wajib konsisten.** Setiap halaman punya judul dengan format ini:

```php
<!-- Halaman index/daftar -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">
            <i class="ri ri-[icon-name] me-1 text-primary"></i>
            <?= html_escape($title) ?>
        </h4>
        <small class="text-muted">[deskripsi singkat halaman]</small>
    </div>
    <div class="d-flex gap-2">
        <!-- Tombol aksi utama (Tambah, Export, dll.) -->
    </div>
</div>
```

```php
<!-- Halaman detail / form -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">
            <i class="ri ri-[icon-name] me-1 text-primary"></i>
            <?= html_escape($title) ?>
        </h4>
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="<?= site_url('purchase-orders') ?>">Purchase Order</a>
                </li>
                <li class="breadcrumb-item active">Detail PO</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url('purchase-orders') ?>" class="btn btn-outline-secondary">
            <i class="ri ri-arrow-left-line me-1"></i>Kembali
        </a>
    </div>
</div>
```

**`$title` diset di controller**, bukan di view. Tidak boleh hardcode teks judul di view.

---

## 8. Pola Filter & Search

**Wajib pakai GET form.** Filter tidak pernah pakai POST. Clear = link ke URL bersih.

```php
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" action="<?= $baseUrl ?>" class="row g-2 align-items-end">

            <!-- Search teks -->
            <div class="col-12 col-md-4">
                <label class="form-label mb-1 small">Cari</label>
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="No. PO, nama vendor..."
                       value="<?= html_escape($q) ?>">
            </div>

            <!-- Filter status (dropdown) -->
            <div class="col-6 col-md-2">
                <label class="form-label mb-1 small">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="DRAFT"    <?= $status === 'DRAFT'    ? 'selected' : '' ?>>Draft</option>
                    <option value="APPROVED" <?= $status === 'APPROVED' ? 'selected' : '' ?>>Approved</option>
                    <option value="PAID"     <?= $status === 'PAID'     ? 'selected' : '' ?>>Paid</option>
                    <option value="VOID"     <?= $status === 'VOID'     ? 'selected' : '' ?>>Void</option>
                </select>
            </div>

            <!-- Filter tanggal (jika diperlukan) -->
            <div class="col-6 col-md-2">
                <label class="form-label mb-1 small">Bulan</label>
                <input type="month" name="month" class="form-control form-control-sm"
                       value="<?= html_escape($month ?? '') ?>">
            </div>

            <!-- Tombol -->
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary flex-fill">
                    <i class="ri ri-search-line"></i> Filter
                </button>
                <a href="<?= $baseUrl ?>" class="btn btn-sm btn-outline-danger">
                    <i class="ri ri-close-line"></i>
                </a>
            </div>

        </form>
    </div>
</div>
```

**Aturan filter:**
- `name` atribut harus sama dengan variabel yang dibaca di controller
- Value input selalu diisi dari PHP variable (biar tidak kosong saat reload)
- Tombol clear = anchor tag ke `$baseUrl` tanpa parameter
- Filter tanggal pakai `type="month"` (`YYYY-MM`) atau `type="date"` (`YYYY-MM-DD`)

---

## 9. Pola Tabel Data

```php
<div class="card">
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:140px">No. PO</th>
                    <th>Vendor</th>
                    <th>Tgl Order</th>
                    <th class="text-end" style="width:140px">Total</th>
                    <th style="width:100px">Status</th>
                    <th style="width:100px" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="ri ri-inbox-line ri-2x d-block mb-2"></i>
                        Belum ada data<?= $q ? ' yang cocok dengan filter' : '' ?>.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <a href="<?= site_url('purchase-orders/' . $row['id']) ?>"
                           class="fw-semibold text-decoration-none">
                            <?= html_escape($row['po_no']) ?>
                        </a>
                    </td>
                    <td><?= html_escape($row['vendor_name']) ?></td>
                    <td><?= date('d M Y', strtotime($row['order_date'])) ?></td>
                    <td class="text-end"><?= ui_num($row['total_amount']) ?></td>
                    <td><?= status_badge($row['status']) /* lihat Bagian 16 */ ?></td>
                    <td class="text-center">
                        <!-- Lihat Bagian 17 untuk tombol aksi -->
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Footer: info jumlah + pagination -->
    <div class="card-footer d-flex justify-content-between align-items-center py-2">
        <small class="text-muted">
            Menampilkan <?= count($rows) ?> dari <?= number_format($total) ?> data
        </small>
        <?php // Lihat Bagian 10 untuk pagination ?>
    </div>
</div>
```

**Aturan tabel:**
- Selalu pakai `table-striped table-hover align-middle`
- Header tabel: `table-light`
- Kolom nominal/angka: `class="text-end"`, pakai `ui_num()`
- Kolom aksi: `class="text-center"`, lebar tetap
- Empty state wajib ada dengan ikon dan teks yang informatif
- Link ke detail: teks utama baris, bukan tombol terpisah

---

## 10. Pola Pagination

```php
<?php
// Hitung di controller atau di atas view
$totalPages = $limit > 0 ? (int)ceil($total / $limit) : 1;
$prevPage   = max(1, $page - 1);
$nextPage   = min($totalPages, $page + 1);

// Builder halaman dengan ellipsis
$pageItems = [];
if ($totalPages <= 7) {
    $pageItems = range(1, $totalPages);
} else {
    $pageItems = [1];
    if ($page > 3) $pageItems[] = '...';
    foreach (range(max(2, $page - 1), min($totalPages - 1, $page + 1)) as $p) {
        $pageItems[] = $p;
    }
    if ($page < $totalPages - 2) $pageItems[] = '...';
    $pageItems[] = $totalPages;
}
?>

<?php if ($totalPages > 1): ?>
<div class="d-flex gap-1">
    <a class="btn btn-sm btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>"
       href="<?= site_url($baseUrl . '?' . $buildQuery(['page' => $prevPage])) ?>">
        <i class="ri ri-arrow-left-s-line"></i>
    </a>
    <?php foreach ($pageItems as $item): ?>
        <?php if ($item === '...'): ?>
            <span class="btn btn-sm btn-outline-secondary disabled">…</span>
        <?php else: ?>
            <a class="btn btn-sm <?= ($page === $item) ? 'btn-primary' : 'btn-outline-secondary' ?>"
               href="<?= site_url($baseUrl . '?' . $buildQuery(['page' => $item])) ?>">
                <?= $item ?>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
    <a class="btn btn-sm btn-outline-secondary <?= $page >= $totalPages ? 'disabled' : '' ?>"
       href="<?= site_url($baseUrl . '?' . $buildQuery(['page' => $nextPage])) ?>">
        <i class="ri ri-arrow-right-s-line"></i>
    </a>
</div>
<?php endif; ?>
```

**Aturan pagination:**
- `$buildQuery()` wajib dipakai agar filter aktif tidak hilang saat ganti halaman
- Default `$limit = 50` kecuali modul tertentu butuh berbeda
- Tombol prev/next disable saat di halaman pertama/terakhir

---

## 11. Pola AJAX & Fetch API

### Endpoint AJAX (Controller)

```php
// Di controller — SEMUA AJAX endpoint return JSON
public function store()
{
    if (!$this->can(self::PAGE_XXX, 'create')) {
        $this->jsonError('Tidak ada izin.', 403);
        return;
    }

    $payload = $this->requestPayload();  // Baca JSON body

    // Validasi
    if (empty($payload['field_wajib'])) {
        $this->jsonError('Field wajib wajib diisi.', 422);
        return;
    }

    $result = $this->Model->do_something($payload, $this->current_user['id']);

    if (!($result['ok'] ?? false)) {
        $this->jsonError($result['message'] ?? 'Gagal.', 422);
        return;
    }

    $this->jsonOk($result['data'] ?? [], 'Berhasil disimpan.');
}
```

### Fetch API (View/JS)

```javascript
// Fungsi helper — tersedia global via assets/js/app.js
// showAlert(type, message) — tampilkan pesan di #alert-area
// showAlertIn(selector, type, message) — tampilkan di elemen tertentu

// ── Spinner helper (gunakan di semua tombol transaksi) ────────
// Simpan origHtml sebelum fetch, restore di .finally()
//
//   var origHtml = btn.innerHTML;
//   btn.disabled = true;
//   btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Menyimpan...';
//   fetch(...)
//   .then(...)
//   .catch(...)
//   .finally(function () { btn.disabled = false; btn.innerHTML = origHtml; });
//
// Label spinner sesuaikan konteks: Menyimpan... / Memuat... / Memposting... / Memproses...

// Pola standar AJAX POST
function submitForm(url, payload, onSuccess) {
    const btn = document.getElementById('btn-submit');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Menyimpan...';
    }

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(r => r.json().then(j => ({ status: r.status, json: j })))
    .then(res => {
        if (res.status >= 400 || !res.json?.ok) {
            throw new Error(res.json?.message || 'Terjadi kesalahan.');
        }
        showAlert('success', res.json.message || 'Berhasil disimpan.');
        if (typeof onSuccess === 'function') onSuccess(res.json);
    })
    .catch(err => {
        showAlert('danger', err.message);
    })
    .finally(() => {
        if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
    });
}

// Contoh pemakaian
document.getElementById('btn-save').addEventListener('click', function() {
    const payload = {
        vendor_id: Number(document.getElementById('vendor_id').value || 0),
        notes:     document.getElementById('notes').value.trim() || null
    };

    // Validasi client-side
    if (!payload.vendor_id) {
        showAlert('danger', 'Vendor wajib dipilih.');
        return;
    }

    submitForm(BASE_URL + 'purchase/order/store', payload, function(data) {
        setTimeout(() => window.location.href = BASE_URL + 'purchase-orders/' + data.data.id, 600);
    });
});
```

**Aturan AJAX:**
- `BASE_URL` sudah tersedia di semua halaman (di-set di `layout/footer.php`)
- Selalu disable tombol submit saat request berlangsung
- Selalu tampilkan pesan error dari server (jangan hanya "Terjadi kesalahan")
- Setelah sukses: reload, redirect, atau update DOM — pilih sesuai konteks
- Jangan pakai `$.ajax()` untuk kode baru

---

## 12. Pola Form Modal

```html
<!-- Trigger button -->
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalForm">
    <i class="ri ri-add-line me-1"></i>Tambah
</button>

<!-- Modal -->
<div class="modal fade" id="modalForm" tabindex="-1" aria-labelledby="modalFormLabel">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalFormLabel">Tambah Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modal-alert-area" class="mb-3"></div>

                <div class="mb-3">
                    <label class="form-label">Nama Vendor <span class="text-danger">*</span></label>
                    <input type="text" id="vendor_name" class="form-control"
                           placeholder="Masukkan nama vendor">
                </div>
                <!-- Field lainnya -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btn-modal-save">
                    Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Reset modal saat ditutup
document.getElementById('modalForm').addEventListener('hidden.bs.modal', function() {
    document.getElementById('vendor_name').value = '';
    document.getElementById('modal-alert-area').innerHTML = '';
});

// Submit modal
document.getElementById('btn-modal-save').addEventListener('click', function() {
    const payload = {
        vendor_name: document.getElementById('vendor_name').value.trim()
    };
    if (!payload.vendor_name) {
        showAlertIn('#modal-alert-area', 'danger', 'Nama vendor wajib diisi.');
        return;
    }
    submitForm(BASE_URL + 'master/vendor/store', payload, function() {
        bootstrap.Modal.getInstance(document.getElementById('modalForm')).hide();
        setTimeout(() => window.location.reload(), 400);
    });
});
</script>
```

**Aturan modal:**
- Selalu ada `id="modal-alert-area"` di dalam modal untuk pesan error
- Reset field saat modal ditutup (`hidden.bs.modal`)
- Label field wajib pakai `<span class="text-danger">*</span>`
- Modal ukuran default `modal-dialog-centered`, pakai `modal-lg` jika form besar

---

## 13. Flash Message

### Set di Controller (redirect setelah aksi)

```php
$this->session->set_flashdata('success', 'Purchase Order berhasil dibuat.');
redirect('purchase-orders');

$this->session->set_flashdata('error', 'Terjadi kesalahan. Silakan coba lagi.');
redirect('purchase-orders');

$this->session->set_flashdata('warning', 'Stok hampir habis.');
redirect('purchase-orders');
```

### Sudah otomatis tampil di layout/main.php

Tidak perlu tambahkan apapun di view — flash message muncul otomatis di atas konten.

### Alert inline (untuk AJAX, tanpa redirect)

```javascript
// Tersedia via app.js
showAlert('success', 'Data berhasil disimpan.');   // di #alert-area
showAlert('danger', 'Terjadi kesalahan.');
showAlert('warning', 'Perhatian: stok menipis.');
showAlert('info', 'Sedang diproses...');
```

---

## 14. Format Angka & Tanggal

### Angka (PHP)

```php
// Helper ui_num() — format Indonesia (koma = desimal, titik = ribuan)
echo ui_num(1234567.89);           // → 1.234.567,89
echo ui_num(1234567.89, 0);        // → 1.234.568
echo ui_num($row['amount']);       // Default 2 desimal

// JANGAN pakai number_format() langsung di view — pakai ui_num()
// KECUALI ada kebutuhan format khusus yang tidak bisa ui_num() tangani
```

### Angka (JavaScript)

```javascript
// Tersedia via app.js
formatRupiah(1234567.89)    // → "1.234.567,89"
formatRupiahIDR(1234567.89) // → "Rp 1.234.567"
```

### Angka (Form Qty)

```text
- Untuk form input qty operasional/procurement di UI: tampilkan dan input maksimal 2 angka di belakang koma.
- Input HTML quantity gunakan `step="0.01"` dan default value seperti `1.00`, bukan `1` atau `1.0000`.
- Nilai turunan yang readonly (mis. `qty_content` hasil konversi dari `qty_buy`) tetap ditampilkan 2 desimal.
- Jangan tampilkan raw decimal 4-6 digit di form/table preview kecuali memang halaman analitis/perhitungan teknis membutuhkan presisi lebih tinggi.
```

### Tanggal (PHP)

```php
// Format tampilan Indonesia
date('d M Y', strtotime($row['created_at']))          // → 18 Mei 2026
date('d M Y H:i', strtotime($row['created_at']))      // → 18 Mei 2026 14:30
date('d/m/Y', strtotime($row['date']))                // → 18/05/2026 (tabel padat)

// JANGAN echo langsung datetime dari DB (format tidak manusiawi)
// echo $row['created_at'];  // ❌ "2026-05-18 14:30:00"
```

### Tanggal (Input Form)

```html
<!-- Selalu format YYYY-MM-DD untuk value input type="date" -->
<input type="date" name="order_date"
       value="<?= html_escape($row['order_date'] ?? date('Y-m-d')) ?>">
```

---

## 15. Keamanan & Escaping

```php
// WAJIB: Semua output ke HTML pakai html_escape()
<?= html_escape($row['name']) ?>
<?= html_escape((string)($value ?? '')) ?>

// WAJIB: Data ke attribute HTML pakai htmlspecialchars
<button data-id="<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>">

// WAJIB: Data ke JavaScript pakai json_encode()
<script>
const rowData = <?= json_encode($rows) ?>;
const config  = <?= json_encode(['base_url' => base_url()]) ?>;
</script>

// JANGAN: echo langsung tanpa escaping
<?= $row['name'] ?>  // ❌ XSS vulnerability

// Input dari user SELALU disanitasi di controller sebelum dikirim ke model
$name = trim((string)($this->input->post('name', true) ?? ''));
```

---

## 16. Status Badge

```php
// Fungsi helper di ui_number_helper.php (atau tambahkan di sana)
// Penggunaan konsisten di seluruh aplikasi:

// Status mapping warna
$status_class = [
    'DRAFT'      => 'secondary',
    'PENDING'    => 'warning',
    'APPROVED'   => 'info',
    'ACTIVE'     => 'success',
    'PAID'       => 'success',
    'CLOSED'     => 'dark',
    'VOID'       => 'danger',
    'REJECTED'   => 'danger',
    'PARTIAL'    => 'warning',
];
$status_label = [
    'DRAFT'    => 'Draft',
    'PENDING'  => 'Menunggu',
    'APPROVED' => 'Disetujui',
    'ACTIVE'   => 'Aktif',
    'PAID'     => 'Lunas',
    'CLOSED'   => 'Selesai',
    'VOID'     => 'Dibatalkan',
    'REJECTED' => 'Ditolak',
    'PARTIAL'  => 'Sebagian',
];
$cls = $status_class[$row['status']] ?? 'secondary';
$label = $status_label[$row['status']] ?? $row['status'];
?>
<span class="badge bg-<?= $cls ?>"><?= html_escape($label) ?></span>
```

**Aturan badge:**
- Selalu pakai `badge bg-{warna}` Bootstrap
- Warna status harus konsisten antar halaman (lihat mapping di atas)
- Status yang tampil di UI harus berupa bahasa pengguna, bukan enum mentah dari database
- Jika render status dilakukan di JavaScript, tetap sediakan mapping label yang setara dan jangan tampilkan kode seperti `NOT_PROCESSED`, `AUTO_WASTE`, atau `REFUNDED_FULL` langsung ke user

---

## 17. Tombol Aksi

```php
<!-- Pola tombol aksi di kolom tabel -->

<td class="action-cell">
    <div class="d-flex gap-1 flex-nowrap justify-content-end">
        <a href="<?= site_url('purchase-orders/' . $row['id']) ?>"
           class="btn btn-sm btn-outline-info action-icon-btn"
           data-bs-toggle="tooltip"
           title="Detail"
           aria-label="Detail">
            <i class="ri ri-eye-line"></i>
        </a>
        <?php if ($canEdit && $row['status'] === 'DRAFT'): ?>
        <a href="<?= site_url('purchase-orders/edit/' . $row['id']) ?>"
           class="btn btn-sm btn-outline-primary action-icon-btn"
           data-bs-toggle="tooltip"
           title="Edit"
           aria-label="Edit">
            <i class="ri ri-edit-line"></i>
        </a>
        <?php endif; ?>
    </div>
</td>

<!-- Jika aksi 4+ atau kombinasi terlalu padat, pindah ke dropdown -->
<td class="action-cell">
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                type="button" data-bs-toggle="dropdown">
            <i class="ri ri-more-2-line"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="...">
                <i class="ri ri-eye-line me-2"></i>Detail</a></li>
            <li><a class="dropdown-item" href="...">
                <i class="ri ri-edit-line me-2"></i>Edit</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="#"
                   onclick="confirmDelete(<?= $row['id'] ?>)">
                <i class="ri ri-delete-bin-line me-2"></i>Hapus</a></li>
        </ul>
    </div>
</td>
```

### 17.1 Prinsip Wajib

- Kolom aksi tabel menggunakan tombol ikon, bukan teks polos.
- Toolbar di luar tabel boleh memakai ikon + teks.
- Gunakan class bersama `action-cell` + `action-icon-btn` untuk halaman umum.
- Jangan buat variasi lokal baru (`user-actions`, `action-wrap`, `po-action-btn`, dll.) jika shared class yang ada sudah cukup.
- Jika halaman memang punya workflow khusus dan padat, buat override lokal hanya bila shared class tidak cukup, lalu dokumentasikan di file ini.

### 17.2 Bentuk, Ukuran, dan Model Tombol

| Konteks | Class utama | Ukuran tombol | Ukuran ikon | Catatan |
|---|---|---:|---:|---|
| Aksi tabel umum | `action-icon-btn` | `30×30px` | `0.92rem` | Radius `8px`, icon-only, `min-width: 30px` |
| Tombol teks pendek | `action-text-btn` | tinggi min `30px` | `0.95rem` | Untuk toolbar/filter ringan |
| Produksi component | `component-action-btn` | `38×38px` | `~1.12rem` | Khusus `production/component-*` |
| Dropdown trigger aksi | `btn btn-sm btn-outline-secondary` | ikut Bootstrap | `0.95rem` | Pakai `ri-more-2-line` |

### 17.3 Kapan Pakai Icon-Only vs Icon+Teks

- Kolom aksi per baris tabel: pakai icon-only.
- Tombol aksi utama di header halaman: pakai ikon + teks.
- Aksi destruktif dokumen besar di area detail/form: boleh ikon + teks agar dampaknya jelas.
- Jika aksi inline per baris sudah `4` atau lebih, pindah ke dropdown.
- Jika aksi hanya `1-3`, tetap inline dan satu baris.

### 17.4 Kamus Ikon Aksi Kanonik

| Aksi | Ikon standar | Warna default |
|---|---|---|
| Detail / Lihat | `ri-eye-line` | `btn-outline-info` |
| Edit / Ubah | `ri-edit-line` | `btn-outline-primary` |
| Tambah | `ri-add-line` | `btn-primary` / `btn-outline-primary` |
| Hapus | `ri-delete-bin-line` | `btn-outline-danger` |
| Void / Batal Dokumen | `ri-close-circle-line` | `btn-outline-danger` |
| Post / Finalisasi | `ri-upload-2-line` | `btn-outline-success` / `btn-success` |
| Toggle Status | `ri-refresh-line` | `btn-outline-warning` |
| Approve / Verifikasi | `ri-check-line` | `btn-outline-success` |
| Print | `ri-printer-line` | `btn-outline-secondary` |
| Export / Download | `ri-download-cloud-line` | `btn-outline-secondary` |
| Permission / Security | `ri-shield-keyhole-line` | `btn-outline-warning` |
| Riwayat / Log | `ri-history-line` | `btn-outline-secondary` |
| Formula / Relation | `ri-function-line` atau `ri-links-line` | `btn-outline-info` |
| Checklist / Matrix | `ri-checkbox-multiple-line` | `btn-outline-info` |
| More / Overflow | `ri-more-2-line` | `btn-outline-secondary` |

### 17.5 Larangan dan Anti-Pattern

- Jangan pakai `ri-pencil-line` untuk aksi edit normal. Standar edit adalah `ri-edit-line`.
- Jangan campur tombol teks panjang di kolom aksi tabel yang sempit jika aksi itu bisa diwakili ikon.
- Jangan campur ukuran `28×28`, `30×30`, `36×36` secara acak dalam modul yang sama.
- Jangan pakai ikon berbeda untuk aksi yang sama dalam modul yang sama.
- Jangan pakai `window.confirm()` untuk tombol aksi sensitif baru; lihat Bagian 18.
- Jangan hilangkan `title` dan `aria-label` pada tombol icon-only.

### 17.6 Struktur HTML yang Wajib Diikuti

```php
<td class="action-cell">
    <div class="d-flex gap-1 flex-nowrap justify-content-end">
        <a href="..." class="btn btn-sm btn-outline-info action-icon-btn" title="Detail" aria-label="Detail">
            <i class="ri ri-eye-line"></i>
        </a>
        <a href="..." class="btn btn-sm btn-outline-primary action-icon-btn" title="Edit" aria-label="Edit">
            <i class="ri ri-edit-line"></i>
        </a>
        <button type="button" class="btn btn-sm btn-outline-danger action-icon-btn" title="Hapus" aria-label="Hapus">
            <i class="ri ri-delete-bin-line"></i>
        </button>
    </div>
</td>
```

### 17.7 Temuan Audit yang Harus Dianggap Tidak Sesuai Standar

- Halaman yang masih memakai wrapper lokal tanpa `action-cell` / `action-icon-btn` padahal aksinya icon-only harus dinormalisasi saat disentuh. Contoh saat audit ini: `users/index.php`, `attendance/schedules.php`, `purchase/stock_adjustment_index.php`.
- Aksi `Edit` yang masih memakai `ri-pencil-line` harus diganti ke `ri-edit-line`. Contoh saat audit ini: `sidebar/manage.php`, `attendance/overtime_entries.php`, `payroll/manual_adjustments.php`, `payroll/cash_advances.php`, `procurement/store_requests.php`, `purchase/index.php`.
- Tombol teks seperti `Post`, `Delete`, `Void`, `Hapus` di kolom aksi tabel sebaiknya diubah ke tombol ikon bila konteksnya memang aksi baris, bukan aksi header dokumen.

### 17.8 Pengecualian yang Dibenarkan

- Halaman `production/component-*` boleh tetap memakai `component-action-btn` karena ukuran dan densitasnya memang berbeda.
- Tombol aksi di area form/detail yang bukan kolom tabel boleh tetap pakai ikon + teks jika perlu menekankan konsekuensi bisnis.
- Jika satu aksi perlu label eksplisit demi keselamatan operasional, ikon + teks lebih diutamakan daripada icon-only.

---

## 18. Dialog Konfirmasi UI

**Standar wajib:** jangan pakai `window.alert()` atau `window.confirm()` langsung untuk fitur baru di halaman Finance.

Gunakan helper global dari `assets/js/app.js`:

```js
function uiConfirm(message, options) {
    if (window.FinanceUI && typeof window.FinanceUI.confirm === 'function') {
        return window.FinanceUI.confirm(message, options || {});
    }
    return Promise.resolve(window.confirm(String(message || 'Lanjutkan aksi?')));
}

uiConfirm('Fulfillment akan memindahkan stok gudang ke divisi tujuan.', {
    title: 'Konfirmasi Fulfillment',
    okText: 'Post Fulfillment',
    cancelText: 'Batal'
}).then(function (ok) {
    if (!ok) return;
    // lanjutkan aksi
});
```

**Aturan dialog konfirmasi:**
- Gunakan `FinanceUI.confirm` sebagai tampilan utama; `window.confirm` hanya fallback darurat
- Judul dialog harus spesifik sesuai aksi, bukan generik
- Tombol utama pakai kata kerja final yang jelas: `Post Fulfillment`, `Generate PO`, `Void Dokumen`
- Pesan dialog menjelaskan dampak bisnis singkat, bukan hanya `Yakin?`

---

## 19. Pola Routing

```php
// File: application/config/routes.php

// === POLA STANDAR PER MODUL ===

// Halaman daftar
$route['purchase-orders']              = 'purchase/index';
$route['purchase-orders/(:num)']       = 'purchase/detail/$1';
$route['purchase-orders/create']       = 'purchase/create';
$route['purchase-orders/edit/(:num)']  = 'purchase/edit/$1';

// Endpoint aksi (AJAX atau form POST) — pakai sub-path /action
$route['purchase/order/store']           = 'purchase/order_store';
$route['purchase/order/update/(:num)']   = 'purchase/order_update/$1';
$route['purchase/order/delete/(:num)']   = 'purchase/order_delete/$1';
$route['purchase/order/approve/(:num)']  = 'purchase/order_approve/$1';
$route['purchase/order/void/(:num)']     = 'purchase/order_void/$1';

// Endpoint AJAX search/lookup
$route['purchase/catalog/search']   = 'purchase/catalog_search';
$route['purchase/vendor/search']    = 'purchase/vendor_search';

// Master data via controller Master (polymorphic)
$route['master/(:any)/list']   = 'master/list/$1';
$route['master/(:any)/store']  = 'master/store/$1';
$route['master/(:any)/update'] = 'master/update/$1';
$route['master/(:any)/delete'] = 'master/delete/$1';
```

**Aturan routing:**
- URL halaman (yang dibuka user) = kata benda plural + kebab-case: `purchase-orders`, `salary-disbursements`
- URL endpoint aksi = prefiks modul + sub-path: `purchase/order/store`
- Tidak pakai nama method controller langsung di URL publik
- ID resource di URL: `(:num)` untuk integer, `(:any)` untuk string/slug

---

## 20. Pola Permission

```php
// Di controller — definisikan konstanta
const PAGE_ORDER = 'purchase.order';

// Halaman yang perlu login dan permission
$this->require_permission(self::PAGE_ORDER, 'view');    // Redirect jika tidak ada izin

// Cek izin sebelum aksi (AJAX)
if (!$this->can(self::PAGE_ORDER, 'delete')) {
    $this->jsonError('Tidak ada izin.', 403);
    return;
}
```

```php
// Di view — JANGAN pakai $this->can() (CI3: $this di view = CI_Loader, bukan controller)
// WAJIB: definisikan variabel permission di BAGIAN 1 (persiapan data PHP) view:
$_is_super = !empty($current_user['is_superadmin']);
$canCreate = $_is_super || !empty($user_perms['purchase.order']['can_create']);
$canEdit   = $_is_super || !empty($user_perms['purchase.order']['can_edit']);
$canDelete = $_is_super || !empty($user_perms['purchase.order']['can_delete']);

// Lalu gunakan variabel tersebut:
<?php if ($canCreate): ?>
    <a href="..." class="btn btn-primary">Buat PO</a>
<?php endif; ?>

<?php if ($canDelete): ?>
    <button onclick="confirmDelete(...)">Hapus</button>
<?php endif; ?>
```

**Aksi permission yang tersedia:** `view`, `create`, `edit`, `delete`, `export`

---

## 21. Summary Card

```php
<!-- Pola summary card di atas tabel -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3 px-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small text-muted mb-1">Total PO</div>
                        <div class="h5 mb-0 fw-bold">
                            <?= number_format($summary['total'] ?? 0) ?>
                        </div>
                    </div>
                    <div class="text-primary opacity-75">
                        <i class="ri ri-file-list-3-line ri-xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3 px-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small text-muted mb-1">Nilai Total</div>
                        <div class="h5 mb-0 fw-bold">
                            <?= ui_num($summary['total_amount'] ?? 0, 0) ?>
                        </div>
                    </div>
                    <div class="text-success opacity-75">
                        <i class="ri ri-money-dollar-circle-line ri-xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
```

**Aturan summary card:**
- Maksimal 4 kartu per baris di desktop (col-md-3)
- Di mobile: 2 kartu per baris (col-6)
- Ikon kanan atas setiap kartu, warna sesuai konteks
- Nilai pakai `ui_num()` atau `number_format()` — tidak pernah raw number

---

## 21. Sistem Visual & Tipografi — Finance vs Core

Finance App menggunakan **Materio** sebagai tema dasar (bukan Tabler seperti Core App).
Perbedaan visual ini **disengaja** dan harus dipertahankan agar kedua aplikasi mudah dibedakan.

### 21.1  Identitas Tipografi Finance

| Token | Nilai | Keterangan |
|---|---|---|
| Font utama | `'Plus Jakarta Sans'` | Dimuat dari Google Fonts |
| Fallback | `'Segoe UI', Tahoma, sans-serif` | |
| Body size | `0.9375rem` (15px) | Lebih besar dari default Materio |
| Body weight | `400` + `500` untuk penekanan | Bukan thin/300 |
| Label/header kolom | `0.8125rem`, weight `700` | Uppercase + letter-spacing |
| Heading h4 | `1.25rem`, weight `800` | Judul halaman |
| Heading h5 | `1.0rem`, weight `700` | Judul card/section |
| Heading h6 | `0.875rem`, weight `700` | Sub-label |
| Font utama dimuat di | `views/layout/header.php` | Google Fonts CDN (preconnect) |

### 21.2  Token CSS (dideklarasi di `assets/css/app.css`)

```css
:root {
  --fin-font:       'Plus Jakarta Sans', 'Segoe UI', Tahoma, sans-serif;
  --fin-body-sz:    0.9375rem;
  --fin-body-wt:    400;
  --fin-emph-wt:    500;
  --fin-strong-wt:  600;
  --fin-label-sz:   0.8125rem;
  --fin-label-wt:   700;
  --fin-small-sz:   0.75rem;
  --fin-h4-sz:      1.25rem;  --fin-h4-wt: 800;
  --fin-h5-sz:      1.0rem;   --fin-h5-wt: 700;
  --fin-card-radius: 12px;
  --fin-btn-height:  32px;
    --fin-btn-icon-sz: 30px;
}
```

### 21.3  Pola Page Header Standar

Setiap halaman list/detail **wajib** menggunakan struktur `.fin-page-header`:

```html
<div class="fin-page-header">
  <div>
    <p class="fin-breadcrumb"><a href="<?= base_url('dashboard') ?>">Dashboard</a> / Modul</p>
    <h4 class="fin-page-title">Judul Halaman</h4>
    <p class="fin-page-subtitle">Deskripsi singkat halaman</p>
  </div>
  <div class="fin-page-actions">
    <?php if ($canCreate): ?>
    <a href="<?= base_url('modul/create') ?>" class="btn btn-primary btn-sm">
      <i class="ri ri-add-line"></i> Tambah
    </a>
    <?php endif; ?>
  </div>
</div>
```

### 21.4  Tombol Aksi di Kolom Tabel

Gunakan `td.action-cell` + `action-icon-btn` — jangan pernah pakai `flex-wrap` di action cell:

```html
<td class="action-cell">
  <div class="d-flex gap-1 flex-nowrap justify-content-end">
    <a href="..." class="btn btn-sm btn-outline-info action-icon-btn" title="...">
      <i class="ri ri-eye-line"></i>
    </a>
    <?php if ($canEdit): ?>
    <a href="..." class="btn btn-sm btn-outline-secondary action-icon-btn" title="Edit">
      <i class="ri ri-edit-line"></i>
    </a>
    <?php endif; ?>
  </div>
</td>
```

CSS yang berlaku (dari `app.css`):
- `td.action-cell` → `white-space: nowrap`, `text-align: right`, `width: 1%`
- `.action-icon-btn` → `30×30px`, `min-width: 30px`, `border-radius: 8px`, hover naik 1px
- Ikon di dalam `action-icon-btn` → sekitar `0.92rem`
- Untuk halaman `production/component-*`, gunakan `component-action-btn`, bukan `action-icon-btn`

### 21.5  Perbedaan Finance vs Core (referensi)

| Aspek | Core (Tabler) | Finance (Materio) |
|---|---|---|
| Font | System-UI / Inter | Plus Jakarta Sans |
| Body weight | 400 / thin | 400 + emphasis 500 |
| Heading weight | 600 | 700–800 |
| Card | Flat, border | Shadow, borderless, radius 12px |
| Tabel header | Lowercase, abu | Uppercase, bold 700, warm |
| Tombol aksi | Flat text-link | Icon button 28×28, hover shadow |
| Background | Putih/abu | Cream `#f5f0eb` |
| Primary | Biru/teal | Merah `#c0392b` |
| Sidebar | Light abu | Merah gradient gelap |

### 21.6  Aturan

1. **Jangan** load font dari file statis lokal kecuali sudah disetujui — gunakan Google Fonts CDN dengan `preconnect`.
2. **Jangan** override `var(--fin-*)` per halaman — ubah di `app.css` saja.
3. **Jangan** gunakan `font-size < 12px` untuk teks yang bisa dibaca pengguna.
4. Semua `th` di tabel Finance sudah dapat styling otomatis dari `app.css §4` — tidak perlu tambah class.
5. Setiap kali menambah CSS baru ke `app.css`, bump versi query string di `header.php`: `?v=YYYYMMDD{rev}`.

---

## 22. Pola Audit Log

Audit log untuk modul keuangan, gudang, purchase, store request, dan produksi **harus mengikuti prinsip yang sama jelasnya dengan** `fin_account_mutation_log`: pengguna harus bisa melihat nilai/status **sebelum** dan **sesudah** transaksi, bukan hanya delta atau catatan umum.

### 22.1 Prinsip Wajib

1. Setiap transaksi yang mengubah saldo, kuantitas, status, atau nilai dokumen wajib menyimpan metadata referensi: modul, tabel sumber, ID referensi, nomor dokumen, aktor, timestamp.
2. Audit log wajib menyimpan atau bisa menampilkan dengan deterministik nilai **before** dan **after**.
3. Untuk nominal rekening: gunakan pola `balance_before` + `balance_after` seperti `fin_account_mutation_log`.
4. Untuk stok/inventori: minimal harus tersedia `qty_delta` dan `qty_after`; di UI audit wajib tampilkan juga `qty_before = qty_after - qty_delta` bila kolom before tidak disimpan.
5. Untuk status dokumen: wajib simpan `status_before` dan `status_after`.
6. Untuk lot/FIFO: wajib ada referensi lot sumber/tujuan, qty mutasi, unit cost, dan saldo lot setelah transaksi. Jika before tidak disimpan sebagai kolom, tampilan audit tetap harus menghitung dan menampilkan before secara eksplisit.
7. Catatan audit tidak boleh hanya berbunyi "updated" atau "posted"; harus menyebut konteks bisnis singkat, misalnya `Pembayaran PO`, `Fulfillment SR`, `Usage batch produksi`, `Void receipt`.

### 22.2 Format Minimal per Domain

| Domain | Kolom minimum yang harus ada / bisa ditampilkan |
|---|---|
| Keuangan | `amount`, `balance_before`, `balance_after`, `mutation_type` |
| Gudang / stok | `qty_buy_delta`, `qty_content_delta`, `qty_buy_after`, `qty_content_after`, `unit_cost` |
| Purchase / SR status | `action_code`, `status_before`, `status_after`, `notes` |
| FIFO / lot | `lot_no`, `source_lot_no`, `target_lot_no`, `qty_out`, `unit_cost`, `saldo_before`, `saldo_after` |

### 22.3 Aturan Tampilan UI Audit

1. Halaman audit/detail harus menampilkan kolom before dan after secara berdampingan bila relevan.
2. Jangan hanya tampilkan delta (`-10`, `+50000`) tanpa konteks posisi akhir.
3. Untuk stok, format yang dianjurkan: `Opening / Delta / Closing` atau `Before / Delta / After`.
4. Untuk status, format yang dianjurkan: `DRAFT -> APPROVED`, `APPROVED -> VOID`.
5. Untuk lot, tampilkan minimal: lot sumber, lot target, qty, unit cost, saldo lot sesudah mutasi.

### 22.4 Aturan Implementasi Baru

1. Saat membuat tabel log baru, default-kan desain ke pola before/after; jangan menunda dengan alasan "nanti dihitung manual" kecuali ada alasan kuat.
2. Jika modul lama belum punya kolom before, UI audit baru tetap harus menghitung before dari data yang tersedia bila perhitungannya deterministik.
3. Jika perubahan menyentuh transaksi finansial atau inventori, reviewer wajib memeriksa apakah audit trail-nya sudah cukup untuk rekonstruksi histori.
4. Pola `fin_account_mutation_log` adalah referensi utama untuk audit trail yang jelas dan mudah dibaca.

---

## Catatan Tambahan

### Hal yang sering tidak konsisten dan HARUS diperhatikan

1. **`active_menu`** — Wajib diisi di setiap `$data` array controller. Jika tidak diisi, sidebar tidak highlight menu aktif.
2. **`$title`** — Wajib diisi di `$data`. Tidak boleh hardcode judul di view.
3. **Filter `value=`** — Semua input filter wajib diisi dari variabel PHP. Jika lupa, filter hilang saat submit.
4. **Empty state** — Setiap tabel wajib ada kondisi `empty($rows)` dengan pesan yang informatif.
5. **Tombol clear filter** — Selalu ada link ke URL bersih tanpa parameter.
6. **`html_escape()`** — Wajib di semua output. Tidak boleh ada `<?= $var ?>` tanpa escaping.
7. **Angka** — Selalu `ui_num()`. Tidak boleh echo raw decimal dari DB.
8. **Modal reset** — Selalu ada event `hidden.bs.modal` yang reset field dan alert area.
9. **Permission cek di view** — Tombol aksi sensitif (edit, delete) wajib dicek dengan variabel `$canEdit`, `$canDelete` yang didefinisikan di bagian persiapan PHP atas view (bukan `$this->can()` — di CI3 `$this` di view adalah CI_Loader, bukan controller).
10. **`$buildQuery()`** — Wajib dipakai di pagination agar filter tidak hilang saat ganti halaman.
---

## Aturan Tambahan - Clear Filter (Wajib)

Untuk semua halaman list yang memiliki filter/search:

1. Harus ada tombol `Clear Filter`.
2. `Clear Filter` mengembalikan state ke default halaman:
   - keyword/search kosong
   - tab status kembali default (umumnya `ACTIVE`)
   - filter select kembali `Semua`
   - page kembali `1`
   - limit kembali default (umumnya `50`)
3. Setelah `Clear Filter`, data harus langsung refresh tanpa pindah halaman lain.
4. Setelah aksi CRUD (tambah/edit/hapus/toggle), user harus tetap berada pada state filter + page terakhir.

---

## Aturan Tambahan - Status Badge (Wajib)

Untuk semua kolom/status di list dan detail:

1. Jangan tampilkan status sebagai teks polos.
2. Wajib gunakan badge berwarna sesuai status.
3. Gunakan helper `ui_status_badge()` agar konsisten lintas halaman.
4. Mapping minimal:
   - `ACTIVE/AKTIF` -> hijau
   - `INACTIVE/NONAKTIF` -> abu
   - `DRAFT` -> kuning
   - `POSTED/APPROVED/DONE` -> biru
   - `VOID/CANCEL/REJECTED` -> merah
5. Untuk status baru di luar mapping, gunakan badge netral lalu update helper global.

---

## 23. Standar Tab Bertingkat (Penghubung/Jenis/Halaman)

Untuk halaman indeks modul operasional yang punya banyak konteks (contoh Purchase), gunakan **3 lapis tab** berikut:

1. `Tab Penghubung Halaman`:
- Fungsi: pindah lintas halaman dalam satu grup modul.
- Wajib mengikuti urutan menu sidebar (berdasarkan `sys_menu.sort_order`), bukan urutan hardcode acak.
- Gaya: tab/button utama (paling menonjol).

2. `Tab Pembagi Jenis`:
- Fungsi: filter status/jenis data (contoh `Semua`, `Draft`, `Approved`, dst).
- Gaya: pill status standar.

3. `Tab Pembagi Halaman`:
- Fungsi: mengganti cara baca data dalam halaman yang sama (contoh `Per Nota` vs `Per Rincian`).
- Gaya: **harus beda visual** dari tab pembagi jenis agar user tidak bingung konteks.

Aturan tambahan:
- Jangan gunakan 1 gaya tab yang sama persis untuk ketiga fungsi di atas.
- Jika ada `dropdown status` di kolom tabel, posisikan sejajar dengan badge status (inline) untuk menjaga tinggi baris tetap ringkas.
- Untuk teks hero/subtitle halaman, hindari kalimat panjang yang tidak menambah aksi; prioritaskan ruang untuk tab dan filter.

Spesifikasi visual wajib untuk grup seperti `purchase-orders`, `store-requests`, dan halaman laporan yang memakai tab penghubung serupa:
- `Tab Penghubung Halaman` harus mengikuti gaya purchase-order live: background default putih, border tipis hangat, radius sekitar `10px`, teks `600-700`, active maroon `#9f2141` dengan teks putih, dan hover krem lembut. Ini adalah gaya kanonik untuk pindah lintas halaman dalam satu rumpun modul.
- `Tab Penghubung Halaman` boleh menampilkan `hint` kecil/sekunder di bawah atau di samping label utama, tetapi label utama tetap paling dominan dan urutan tab harus mengikuti menu sidebar, bukan hardcode acak.
- `Tab Pembagi Jenis` tetap berbentuk pill status dan harus lebih ringan dari tab penghubung. Untuk grup yang mengikuti pola Purchase Order, gunakan dasar netral hangat seperti `#eef3f1` atau keluarga abu-hijau pucat, border tipis `#d6e0dc`, teks gelap lembut, dan active teal gelap `#1f5d54` dengan teks putih. Jangan memakai warna active maroon penuh untuk tab jenis jika tab penghubung sudah ada di halaman yang sama.
- `Tab Pembagi Halaman` (`Per Nota`, `Per Rincian`, dst.) harus beda visual dari tab jenis. Untuk pola Purchase Order, gunakan dasar netral `#efe8e2`, border `#cec2b8`, teks `#544740`, dan active `#2f2a4f` dengan teks putih. Jangan samakan active state level 3 dengan level 2.
- Form filter di halaman grup purchase/procurement harus meniru pola purchase-order: field ringkas, tinggi kontrol seragam, label pendek, tombol aksi tidak boleh tenggelam warnanya ke background card, dan layout desktop diupayakan hemat tinggi sebelum menambah baris baru.
- Hero title tidak perlu kicker seperti `Overview` jika tidak menambah konteks aksi. Ruang vertikal lebih diprioritaskan untuk tab, warning operasional, dan filter.
- Tombol aksi utama di hero (`Create Order`, `Tambah SR`, dll.) tidak boleh terlalu tinggi. Gunakan padding moderat dan posisikan sejajar atau sedikit turun dari title block agar hero tidak terasa boros.
