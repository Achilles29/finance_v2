<?php
$schedules = (array)($schedules ?? []);
$templates = (array)($templates ?? []);
$groups = (array)($groups ?? []);
$reportTypes = (array)($report_types ?? []);
$filters = (array)($filters ?? []);
$pg = (array)($pg ?? ['page' => 1, 'per_page' => 25, 'total' => count($schedules), 'total_pages' => 1, 'offset' => 0]);
$canCreate = (bool)($can_create ?? false);
$canEdit = (bool)($can_edit ?? false);
$canDelete = (bool)($can_delete ?? false);
$mutationCsrf = (string)($wa_report_schedule_mutation_csrf ?? '');
$buildScheduleUrl = static function (array $overrides = []) use ($filters, $pg) {
  $query = array_merge([
    'q' => $filters['q'] ?? '',
    'status' => $filters['status'] ?? '',
    'report_type' => $filters['report_type'] ?? '',
    'group_id' => $filters['group_id'] ?? 0,
    'date_offset_days' => $filters['date_offset_days'] ?? '',
    'per_page' => $pg['per_page'] ?? 25,
    'page' => $pg['page'] ?? 1,
  ], $overrides);
  foreach ($query as $key => $value) {
    if ($value === '' || $value === null || $value === 0 || $value === '0') {
      unset($query[$key]);
    }
  }
  return site_url('wa/template/schedules') . (empty($query) ? '' : '?' . http_build_query($query));
};
?>

<style>
.wa-schedule-card {
  border: 1px solid #f0d9d2;
  border-radius: 1.1rem;
  background: linear-gradient(135deg, #fffaf7, #fff);
  box-shadow: 0 .75rem 1.8rem rgba(87, 50, 43, .08);
}
.wa-schedule-table {
  max-height: 62vh;
  overflow: auto;
}
.wa-schedule-table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: #a20f2d;
  color: #fff;
}
.wa-modal .modal-content {
  border: 0;
  border-radius: 1.25rem;
  overflow: hidden;
  box-shadow: 0 1.2rem 3rem rgba(74, 38, 35, .22);
}
.wa-modal .modal-header {
  align-items: flex-start;
  background: linear-gradient(135deg, #fff7f2, #fff);
  border-bottom: 1px solid #f0d9d2;
  padding: 1.25rem 1.4rem;
}
.wa-modal .modal-body { padding: 1.4rem; }
.wa-modal .modal-footer {
  background: #fffaf7;
  border-top: 1px solid #f0d9d2;
  padding: 1rem 1.4rem;
}
.wa-modal .form-control,
.wa-modal .form-select {
  border-radius: .8rem;
}
.wa-time-row {
  display: flex;
  gap: .5rem;
  align-items: center;
  margin-bottom: .5rem;
}
.wa-time-row .form-control { flex: 1; }
.wa-command-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1rem;
}
.wa-command-card {
  border: 1px solid #f0d9d2;
  border-radius: 1rem;
  background: #fff;
  padding: 1rem;
  min-height: 100%;
}
.wa-command-card code {
  display: block;
  white-space: normal;
  word-break: break-word;
  color: #9b1833;
  background: #fff3ed;
  border: 1px solid #f3d6cd;
  border-radius: .65rem;
  padding: .55rem .7rem;
  margin-top: .45rem;
}
</style>

<div class="container-xxl py-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h4 class="mb-1 fw-bold"><i class="ri ri-calendar-schedule-line me-1"></i>Jadwal Laporan WA</h4>
      <p class="text-muted mb-0 small">Kirim laporan dari database ke grup WhatsApp pada jam yang ditentukan.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= site_url('wa/template') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="ri ri-arrow-left-line me-1"></i>Template Pesan
      </a>
      <?php if ($canCreate): ?>
      <button class="btn btn-primary btn-sm" type="button" onclick="newSchedule()">
        <i class="ri ri-add-line me-1"></i>Tambah Jadwal
      </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($flash = $this->session->flashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php elseif ($flash = $this->session->flashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <div class="alert alert-info small">
    <strong>Placeholder template:</strong>
    <code>{{report_title}}</code>, <code>{{report_body}}</code>, <code>{{tanggal}}</code>, <code>{{generated_at}}</code>, <code>{{nama_grup}}</code>.
    Perintah cron lokal dari root aplikasi: <code>php index.php whatsapp api_schedule_run</code>.
  </div>

  <ul class="nav nav-pills gap-2 mb-3" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-wa-schedules" type="button" role="tab">
        <i class="ri ri-calendar-schedule-line me-1"></i>Jadwal
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-wa-commands" type="button" role="tab">
        <i class="ri ri-terminal-box-line me-1"></i>Daftar Command
      </button>
    </li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane fade show active" id="tab-wa-schedules" role="tabpanel">
      <div class="wa-schedule-card p-3 mb-3">
        <form method="get" action="<?= site_url('wa/template/schedules') ?>" class="row g-2 align-items-end">
          <div class="col-lg-3 col-md-6">
            <label class="form-label small fw-semibold">Pencarian</label>
            <input type="text" name="q" value="<?= html_escape((string)($filters['q'] ?? '')) ?>" class="form-control" placeholder="Nama, template, grup, catatan...">
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label small fw-semibold">Status</label>
            <select name="status" class="form-select">
              <?php $statusFilter = (string)($filters['status'] ?? ''); ?>
              <option value="">Semua status</option>
              <option value="ACTIVE" <?= $statusFilter === 'ACTIVE' ? 'selected' : '' ?>>Aktif</option>
              <option value="INACTIVE" <?= $statusFilter === 'INACTIVE' ? 'selected' : '' ?>>Nonaktif</option>
              <option value="SENT" <?= $statusFilter === 'SENT' ? 'selected' : '' ?>>Terakhir sent</option>
              <option value="FAILED" <?= $statusFilter === 'FAILED' ? 'selected' : '' ?>>Terakhir failed</option>
              <option value="NEVER" <?= $statusFilter === 'NEVER' ? 'selected' : '' ?>>Belum pernah jalan</option>
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label small fw-semibold">Tipe Laporan</label>
            <select name="report_type" class="form-select">
              <?php $typeFilter = (string)($filters['report_type'] ?? ''); ?>
              <option value="">Semua tipe</option>
              <?php foreach ($reportTypes as $key => $label): ?>
                <option value="<?= html_escape($key) ?>" <?= $typeFilter === (string)$key ? 'selected' : '' ?>><?= html_escape($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label small fw-semibold">Grup</label>
            <select name="group_id" class="form-select">
              <?php $groupFilter = (int)($filters['group_id'] ?? 0); ?>
              <option value="0">Semua grup</option>
              <?php foreach ($groups as $group): ?>
                <option value="<?= (int)$group['id'] ?>" <?= $groupFilter === (int)$group['id'] ? 'selected' : '' ?>><?= html_escape($group['group_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-lg-1 col-md-4">
            <label class="form-label small fw-semibold">Data</label>
            <?php $dateFilter = (string)($filters['date_offset_days'] ?? ''); ?>
            <select name="date_offset_days" class="form-select">
              <option value="">Semua</option>
              <option value="0" <?= $dateFilter === '0' ? 'selected' : '' ?>>Hari ini</option>
              <option value="-1" <?= $dateFilter === '-1' ? 'selected' : '' ?>>Kemarin</option>
            </select>
          </div>
          <div class="col-lg-1 col-md-4">
            <label class="form-label small fw-semibold">Baris</label>
            <select name="per_page" class="form-select">
              <?php foreach ([10, 25, 50, 100] as $size): ?>
                <option value="<?= $size ?>" <?= (int)($pg['per_page'] ?? 25) === $size ? 'selected' : '' ?>><?= $size ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-lg-1 col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
            <a href="<?= site_url('wa/template/schedules') ?>" class="btn btn-outline-secondary" title="Reset filter"><i class="ri ri-refresh-line"></i></a>
          </div>
        </form>
      </div>

      <div class="wa-schedule-card p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
          <div class="small text-muted">
            Menampilkan
            <strong><?= number_format(min((int)($pg['offset'] ?? 0) + 1, (int)($pg['total'] ?? 0)), 0, ',', '.') ?></strong>
            -
            <strong><?= number_format(min((int)($pg['offset'] ?? 0) + count($schedules), (int)($pg['total'] ?? 0)), 0, ',', '.') ?></strong>
            dari <strong><?= number_format((int)($pg['total'] ?? 0), 0, ',', '.') ?></strong> jadwal
          </div>
          <div class="small text-muted">Hal. <?= number_format((int)($pg['page'] ?? 1), 0, ',', '.') ?> / <?= number_format((int)($pg['total_pages'] ?? 1), 0, ',', '.') ?></div>
        </div>
        <div class="wa-schedule-table">
          <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Jadwal</th>
            <th>Tipe Laporan</th>
            <th>Template</th>
            <th>Grup</th>
            <th>Jam</th>
            <th>Status Terakhir</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($schedules as $row): ?>
          <?php
            $isActive = (int)($row['is_active'] ?? 0) === 1;
            $payload = [
              'id' => (int)$row['id'],
              'name' => (string)($row['name'] ?? ''),
              'report_type' => (string)($row['report_type'] ?? ''),
              'template_id' => (int)($row['template_id'] ?? 0),
              'group_id' => (int)($row['group_id'] ?? 0),
              'send_time' => substr((string)($row['send_time'] ?? '08:00'), 0, 5),
              'date_offset_days' => (int)($row['date_offset_days'] ?? 0),
              'is_active' => $isActive ? 1 : 0,
              'notes' => (string)($row['notes'] ?? ''),
            ];
          ?>
          <tr class="<?= $isActive ? '' : 'text-muted opacity-75' ?>">
            <td>
              <div class="fw-semibold"><?= html_escape($row['name'] ?? '-') ?></div>
              <div class="small text-muted"><?= $isActive ? 'Aktif' : 'Nonaktif' ?><?= (int)($row['date_offset_days'] ?? 0) === -1 ? ' · data kemarin' : ' · data hari ini' ?></div>
            </td>
            <td><?= html_escape($reportTypes[$row['report_type'] ?? ''] ?? ($row['report_type'] ?? '-')) ?></td>
            <td><?= html_escape($row['template_name'] ?? '-') ?></td>
            <td>
              <div><?= html_escape($row['group_name'] ?? '-') ?></div>
              <code class="small text-muted"><?= html_escape($row['group_jid'] ?? '-') ?></code>
            </td>
            <td class="fw-semibold"><?= html_escape(substr((string)($row['send_time'] ?? '-'), 0, 5)) ?></td>
            <td>
              <?php if (!empty($row['last_status'])): ?>
                <span class="badge <?= $row['last_status'] === 'SENT' ? 'bg-success' : 'bg-danger' ?>"><?= html_escape($row['last_status']) ?></span>
                <div class="small text-muted"><?= html_escape($row['last_run_at'] ?? '-') ?></div>
                <?php if (!empty($row['last_error'])): ?><div class="small text-danger"><?= html_escape($row['last_error']) ?></div><?php endif; ?>
              <?php else: ?>
                <span class="text-muted">Belum pernah jalan</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <div class="d-flex justify-content-end gap-1 flex-wrap">
                <?php if ($canEdit): ?>
                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="editSchedule(<?= htmlspecialchars(json_encode($payload), ENT_QUOTES) ?>)">
                  <i class="ri ri-edit-line"></i>
                </button>
                <form method="post" action="<?= site_url('wa/template/schedules') ?>" class="d-inline">
                  <input type="hidden" name="action" value="send_now">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <?php if ($mutationCsrf !== ''): ?><input type="hidden" name="wa_report_schedule_mutation_csrf" value="<?= html_escape($mutationCsrf) ?>"><?php endif; ?>
                  <button type="submit" class="btn btn-xs btn-outline-primary js-schedule-submit" data-confirm="Kirim laporan ini sekarang?">Kirim</button>
                </form>
                <form method="post" action="<?= site_url('wa/template/schedules') ?>" class="d-inline">
                  <input type="hidden" name="action" value="toggle_schedule">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <?php if ($mutationCsrf !== ''): ?><input type="hidden" name="wa_report_schedule_mutation_csrf" value="<?= html_escape($mutationCsrf) ?>"><?php endif; ?>
                  <button type="submit" class="btn btn-xs <?= $isActive ? 'btn-outline-warning' : 'btn-outline-success' ?>"><?= $isActive ? 'Off' : 'On' ?></button>
                </form>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                <form method="post" action="<?= site_url('wa/template/schedules') ?>" class="d-inline">
                  <input type="hidden" name="action" value="delete_schedule">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <?php if ($mutationCsrf !== ''): ?><input type="hidden" name="wa_report_schedule_mutation_csrf" value="<?= html_escape($mutationCsrf) ?>"><?php endif; ?>
                  <button type="submit" class="btn btn-xs btn-outline-danger" onclick="return confirm('Hapus jadwal ini?')"><i class="ri ri-delete-bin-line"></i></button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($schedules)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Belum ada jadwal laporan WA.</td></tr>
        <?php endif; ?>
        </tbody>
          </table>
        </div>
        <?php if ((int)($pg['total_pages'] ?? 1) > 1): ?>
          <?php
            $currentPage = (int)($pg['page'] ?? 1);
            $totalPages = (int)($pg['total_pages'] ?? 1);
            $startPage = max(1, $currentPage - 2);
            $endPage = min($totalPages, $currentPage + 2);
          ?>
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
            <a class="btn btn-sm btn-outline-secondary <?= $currentPage <= 1 ? 'disabled' : '' ?>" href="<?= html_escape($buildScheduleUrl(['page' => max(1, $currentPage - 1)])) ?>">Sebelumnya</a>
            <div class="btn-group btn-group-sm" role="group">
              <?php if ($startPage > 1): ?>
                <a class="btn btn-outline-secondary" href="<?= html_escape($buildScheduleUrl(['page' => 1])) ?>">1</a>
                <?php if ($startPage > 2): ?><span class="btn btn-outline-secondary disabled">...</span><?php endif; ?>
              <?php endif; ?>
              <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a class="btn <?= $i === $currentPage ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= html_escape($buildScheduleUrl(['page' => $i])) ?>"><?= $i ?></a>
              <?php endfor; ?>
              <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?><span class="btn btn-outline-secondary disabled">...</span><?php endif; ?>
                <a class="btn btn-outline-secondary" href="<?= html_escape($buildScheduleUrl(['page' => $totalPages])) ?>"><?= $totalPages ?></a>
              <?php endif; ?>
            </div>
            <a class="btn btn-sm btn-outline-secondary <?= $currentPage >= $totalPages ? 'disabled' : '' ?>" href="<?= html_escape($buildScheduleUrl(['page' => min($totalPages, $currentPage + 1)])) ?>">Berikutnya</a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="tab-pane fade" id="tab-wa-commands" role="tabpanel">
      <div class="wa-schedule-card p-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
          <div>
            <h5 class="fw-bold mb-1">Command Bot WhatsApp</h5>
            <p class="text-muted small mb-0">Command hanya diproses dari grup WA yang sudah aktif di mapping grup.</p>
          </div>
          <span class="badge bg-label-primary">Ketik langsung di grup</span>
        </div>

        <div class="wa-command-grid">
          <div class="wa-command-card">
            <h6 class="fw-bold mb-1">Menu Bantuan</h6>
            <p class="small text-muted mb-2">Menampilkan semua perintah yang bisa dipakai.</p>
            <code>menu</code>
            <code>bantuan</code>
          </div>
          <div class="wa-command-card">
            <h6 class="fw-bold mb-1">Omzet</h6>
            <p class="small text-muted mb-2">Ringkasan omzet, PAID, belum terbayar, refund, dan total bersih.</p>
            <code>omzet</code>
            <code>omzet kemarin</code>
          </div>
          <div class="wa-command-card">
            <h6 class="fw-bold mb-1">Belanja</h6>
            <p class="small text-muted mb-2">Rincian pengeluaran/belanja harian sesuai format laporan otomatis.</p>
            <code>belanja</code>
            <code>belanja kemarin</code>
          </div>
          <div class="wa-command-card">
            <h6 class="fw-bold mb-1">Adjustment</h6>
            <p class="small text-muted mb-2">Rincian adjustment bahan, gudang, dan component hari berjalan.</p>
            <code>adjustment</code>
            <code>penyesuaian kemarin</code>
          </div>
          <div class="wa-command-card">
            <h6 class="fw-bold mb-1">Pengajuan PO/SR</h6>
            <p class="small text-muted mb-2">Rincian pengajuan PO/SR divisi dari modul procurement.</p>
            <code>pengajuan</code>
            <code>po sr kemarin</code>
          </div>
          <div class="wa-command-card">
            <h6 class="fw-bold mb-1">Keuangan</h6>
            <p class="small text-muted mb-2">Saldo rekening aktif dan rekap mutasi hari ini.</p>
            <code>keuangan</code>
            <code>saldo</code>
            <code>data keuangan</code>
          </div>
          <div class="wa-command-card">
            <h6 class="fw-bold mb-1">Estimasi Keuangan</h6>
            <p class="small text-muted mb-2">Ringkasan dan rincian harian seperti halaman Estimasi Keuangan.</p>
            <code>estimasi</code>
            <code>estimasi bulan lalu</code>
            <code>estimasi 2026-08</code>
            <code>estimasi agustus 2026</code>
          </div>
          <div class="wa-command-card">
            <h6 class="fw-bold mb-1">Mutasi Rekening</h6>
            <p class="small text-muted mb-2">Daftar mutasi rekening hari ini atau kemarin.</p>
            <code>mutasi</code>
            <code>mutasi kemarin</code>
            <p class="small text-muted mt-2 mb-2">Input langsung hanya untuk admin grup aktif. Kategori wajib untuk IN/OUT; TRANSFER tidak memerlukan kategori. Pesan yang sama tidak diposting ulang.</p>
            <code>mutasi bantuan</code>
            <code>mutasi in TUNAI 50000 setoran owner kategori:OWNER_CAPITAL</code>
            <code>mutasi out TUNAI 25000 beli bensin kategori:OPERATING_EXPENSE</code>
            <code>mutasi transfer TUNAI MANDIRI 100000 setor bank</code>
          </div>
          <div class="wa-command-card">
            <h6 class="fw-bold mb-1">PO Belum PAID</h6>
            <p class="small text-muted mb-2">Daftar purchase order belum lunas lintas bulan.</p>
            <code>hutang</code>
            <code>po belum bayar</code>
          </div>
          <div class="wa-command-card">
            <h6 class="fw-bold mb-1">Stok Kritis</h6>
            <p class="small text-muted mb-2">Stok nol/minus dan component yang sudah menyentuh batas min stock.</p>
            <code>stok kritis</code>
          </div>
          <div class="wa-command-card">
            <h6 class="fw-bold mb-1">POS Pending</h6>
            <p class="small text-muted mb-2">Order POS yang belum final atau stoknya belum selesai diproses.</p>
            <code>pos pending</code>
          </div>
          <div class="wa-command-card">
            <h6 class="fw-bold mb-1">Kas Hari Ini</h6>
            <p class="small text-muted mb-2">Rekap masuk, keluar, dan net per rekening untuk tanggal berjalan.</p>
            <code>kas hari ini</code>
          </div>
          <div class="wa-command-card">
            <h6 class="fw-bold mb-1">Refund & Void</h6>
            <p class="small text-muted mb-2">Daftar refund dan void POS hari ini, termasuk nilai dan kebijakan stok.</p>
            <code>refund hari ini</code>
            <code>void hari ini</code>
          </div>
          <div class="wa-command-card">
            <h6 class="fw-bold mb-1">Top Produk</h6>
            <p class="small text-muted mb-2">Produk terjual terbanyak dari order yang sudah PAID/refund parsial.</p>
            <code>top produk</code>
          </div>
          <div class="wa-command-card">
            <h6 class="fw-bold mb-1">Absensi</h6>
            <p class="small text-muted mb-2">Cek absen bolong dan pengajuan absensi yang masih pending.</p>
            <code>absen bolong</code>
            <code>pengajuan absen pending</code>
          </div>
          <div class="wa-command-card">
            <h6 class="fw-bold mb-1">Rekonsiliasi Stok</h6>
            <p class="small text-muted mb-2">Ringkasan stock mismatch dan stok minus bulan berjalan.</p>
            <code>stock missmatch</code>
            <code>stock minus</code>
          </div>
          <div class="wa-command-card">
            <h6 class="fw-bold mb-1">Batch & Queue POS</h6>
            <p class="small text-muted mb-2">Batch component draft/gagal posting dan queue commit stock POS.</p>
            <code>batch gagal</code>
            <code>queue pos</code>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade wa-modal" id="modal-schedule" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="schedule-title">Tambah Jadwal Laporan</h5>
          <div class="small text-muted mt-1">Pilih tipe laporan, template pesan, grup tujuan, dan jam kirim.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="<?= site_url('wa/template/schedules') ?>">
        <input type="hidden" name="action" value="save_schedule">
        <input type="hidden" name="id" id="schedule-id" value="0">
        <?php if ($mutationCsrf !== ''): ?><input type="hidden" name="wa_report_schedule_mutation_csrf" value="<?= html_escape($mutationCsrf) ?>"><?php endif; ?>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Nama Jadwal</label>
              <input type="text" name="name" id="schedule-name" class="form-control" required placeholder="Omzet harian ke grup owner">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Tipe Laporan</label>
              <select name="report_type" id="schedule-report-type" class="form-select" required>
                <?php foreach ($reportTypes as $key => $label): ?>
                  <option value="<?= html_escape($key) ?>"><?= html_escape($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Template Pesan</label>
              <select name="template_id" id="schedule-template-id" class="form-select" required>
                <?php foreach ($templates as $tpl): ?>
                  <option value="<?= (int)$tpl['id'] ?>"><?= html_escape($tpl['name']) ?> (<?= html_escape($tpl['template_code']) ?>)</option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Template sebaiknya memakai <code>{{report_body}}</code>.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Grup Tujuan</label>
              <select name="group_id" id="schedule-group-id" class="form-select" required>
                <?php foreach ($groups as $group): ?>
                  <option value="<?= (int)$group['id'] ?>"><?= html_escape($group['group_name']) ?><?= empty($group['group_jid']) ? ' - JID kosong' : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Jam Kirim</label>
              <div id="schedule-time-list"></div>
              <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-schedule-time">
                <i class="ri ri-add-line me-1"></i>Tambah Jam
              </button>
              <div class="form-text">Setiap jam akan dibuat sebagai jadwal terpisah.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Tanggal Data</label>
              <select name="date_offset_days" id="schedule-date-offset" class="form-select">
                <option value="0">Hari ini</option>
                <option value="-1">Kemarin</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Status</label>
              <select name="is_active" id="schedule-is-active" class="form-select">
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Catatan</label>
              <input type="text" name="notes" id="schedule-notes" class="form-control" placeholder="Opsional">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan Jadwal</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function scheduleTimeList() {
  return document.getElementById('schedule-time-list');
}

function addScheduleTime(value = '21:00') {
  const list = scheduleTimeList();
  if (!list) return;
  const row = document.createElement('div');
  row.className = 'wa-time-row';
  row.innerHTML = `
    <input type="time" name="send_time[]" class="form-control schedule-time-input" required value="${String(value || '21:00').slice(0, 5)}">
    <button type="button" class="btn btn-outline-danger btn-sm" data-remove-time title="Hapus jam">
      <i class="ri ri-close-line"></i>
    </button>
  `;
  list.appendChild(row);
  refreshTimeRemoveButtons();
}

function setScheduleTimes(values) {
  const list = scheduleTimeList();
  if (!list) return;
  list.innerHTML = '';
  const rows = Array.isArray(values) && values.length ? values : ['21:00'];
  rows.forEach(value => addScheduleTime(value));
}

function refreshTimeRemoveButtons() {
  const list = scheduleTimeList();
  if (!list) return;
  const buttons = list.querySelectorAll('[data-remove-time]');
  buttons.forEach(btn => { btn.disabled = buttons.length <= 1; });
}

function newSchedule() {
  document.getElementById('schedule-title').textContent = 'Tambah Jadwal Laporan';
  document.getElementById('schedule-id').value = 0;
  document.getElementById('schedule-name').value = '';
  document.getElementById('schedule-report-type').value = 'OMZET_TODAY';
  document.getElementById('schedule-template-id').selectedIndex = 0;
  document.getElementById('schedule-group-id').selectedIndex = 0;
  setScheduleTimes(['21:00']);
  document.getElementById('schedule-date-offset').value = '0';
  document.getElementById('schedule-is-active').value = '1';
  document.getElementById('schedule-notes').value = '';
  new bootstrap.Modal(document.getElementById('modal-schedule')).show();
}

function editSchedule(row) {
  document.getElementById('schedule-title').textContent = 'Edit Jadwal Laporan';
  document.getElementById('schedule-id').value = row.id || 0;
  document.getElementById('schedule-name').value = row.name || '';
  document.getElementById('schedule-report-type').value = row.report_type || 'OMZET_TODAY';
  document.getElementById('schedule-template-id').value = row.template_id || '';
  document.getElementById('schedule-group-id').value = row.group_id || '';
  setScheduleTimes([row.send_time || '21:00']);
  document.getElementById('schedule-date-offset').value = String(row.date_offset_days ?? 0);
  document.getElementById('schedule-is-active').value = String(row.is_active ?? 1);
  document.getElementById('schedule-notes').value = row.notes || '';
  new bootstrap.Modal(document.getElementById('modal-schedule')).show();
}

document.getElementById('modal-schedule')?.addEventListener('hidden.bs.modal', function () {
  document.getElementById('schedule-title').textContent = 'Tambah Jadwal Laporan';
  this.querySelector('form').reset();
  document.getElementById('schedule-id').value = 0;
  setScheduleTimes(['21:00']);
});

document.getElementById('btn-add-schedule-time')?.addEventListener('click', function() {
  addScheduleTime('21:00');
});

document.getElementById('schedule-time-list')?.addEventListener('click', function(e) {
  const btn = e.target.closest('[data-remove-time]');
  if (!btn) return;
  btn.closest('.wa-time-row')?.remove();
  refreshTimeRemoveButtons();
});

document.addEventListener('submit', function(e) {
  const btn = e.submitter?.classList?.contains('js-schedule-submit') ? e.submitter : null;
  if (!btn) return;
  const question = btn.getAttribute('data-confirm');
  if (question && !confirm(question)) {
    e.preventDefault();
    return;
  }
  e.preventDefault();
  const form = btn.closest('form');
  if (!form) return;
  const original = btn.innerHTML;
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 35000);
  btn.disabled = true;
  btn.innerHTML = '<i class="ri ri-loader-4-line me-1"></i>Mengirim...';
  fetch(form.action, {
    method: 'POST',
    body: new FormData(form),
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    signal: controller.signal
  })
    .then(resp => resp.json().catch(() => ({ ok: false, message: 'Response backend bukan JSON valid.' })))
    .then(data => {
      const alert = document.createElement('div');
      alert.className = 'alert alert-' + (data.ok ? 'success' : 'danger') + ' alert-dismissible fade show';
      alert.innerHTML = `${data.message || (data.ok ? 'Laporan terkirim.' : 'Gagal kirim laporan.')}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
      document.querySelector('.container-xxl')?.prepend(alert);
      if (data.ok) {
        setTimeout(() => window.location.reload(), 700);
      }
    })
    .catch(err => {
      const alert = document.createElement('div');
      alert.className = 'alert alert-danger alert-dismissible fade show';
      alert.innerHTML = `${err?.name === 'AbortError' ? 'Kirim laporan timeout. Cek status WA Bot dan log pengiriman.' : 'Gagal kirim laporan.'}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
      document.querySelector('.container-xxl')?.prepend(alert);
    })
    .finally(() => {
      clearTimeout(timeout);
      btn.disabled = false;
      btn.innerHTML = original;
    });
});

setScheduleTimes(['21:00']);
</script>
