<?php
$broadcasts   = (array)($broadcasts ?? []);
$filterStatus = (string)($filter_status ?? '');
$filterTarget = (string)($filter_target_type ?? '');
$filterTab    = (string)($filter_tab ?? 'active');
$filterQ      = (string)($filter_q ?? '');
$page         = max(1, (int)($page ?? 1));
$perPage      = max(1, (int)($per_page ?? 25));
$totalRows    = max(0, (int)($total_rows ?? count($broadcasts)));
$totalPages   = max(1, (int)($total_pages ?? 1));
$perPageOptions = (array)($per_page_options ?? [10, 25, 50, 100]);
$statusCounts = (array)($status_counts ?? []);
$canCreate    = (bool)($can_create ?? false);
$canEdit      = (bool)($can_edit ?? false);
$canDelete    = (bool)($can_delete ?? false);
$mutationCsrf = (string)($wa_broadcast_mutation_csrf ?? '');
$personalOutboundEnabled = (bool)($personal_outbound_enabled ?? false);
$personalOutboundLockMessage = (string)($personal_outbound_lock_message ?? 'Pengiriman WhatsApp personal sedang dikunci sementara.');

$statusOptions = ['DRAFT','QUEUED','SENDING','DONE','FAILED','CANCELLED'];
$statusLabel   = ['DRAFT'=>'Draft','QUEUED'=>'Dijadwalkan','SENDING'=>'Berjalan','DONE'=>'Selesai','FAILED'=>'Gagal','CANCELLED'=>'Nonaktif'];
$statusBadge   = ['DRAFT'=>'wa-badge wa-badge-muted','QUEUED'=>'wa-badge wa-badge-info','SENDING'=>'wa-badge wa-badge-warn','DONE'=>'wa-badge wa-badge-ok','FAILED'=>'wa-badge wa-badge-danger','CANCELLED'=>'wa-badge wa-badge-off'];
$targetTypeLabel = [
  'MANUAL' => 'Manual',
  'SELECTED_MEMBERS' => 'Member Terpilih',
  'ALL_MEMBERS' => 'Semua Member',
  'MEMBER_ACTIVE' => 'Member Aktif',
  'CUSTOM' => 'Custom',
];

$makeUrl = static function (array $overrides = []) use ($filterTab, $filterStatus, $filterTarget, $filterQ, $perPage) {
    $params = [
        'tab' => $filterTab,
        'q' => $filterQ,
        'status' => $filterStatus,
        'target_type' => $filterTarget,
        'per_page' => $perPage,
    ];
    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return site_url('wa/broadcast') . (empty($params) ? '' : '?' . http_build_query($params));
};
$tabCounts = [
    'active' => (int)($statusCounts['active'] ?? 0),
    'inactive' => (int)($statusCounts['inactive'] ?? 0),
    'all' => (int)($statusCounts['all'] ?? 0),
];
$fromRow = $totalRows > 0 ? (($page - 1) * $perPage) + 1 : 0;
$toRow = $totalRows > 0 ? min($totalRows, $page * $perPage) : 0;
?>

<style>
.wa-page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:14px}
.wa-page-title{font-weight:800;color:#2a1a1a;margin:0}
.wa-page-subtitle{color:#8d7770;font-size:13px;margin:3px 0 0}
.wa-panel{background:#fff;border:1px solid #eadbd4;border-radius:18px;box-shadow:0 8px 24px rgba(76,45,32,.06)}
.wa-panel-pad{padding:18px}
.wa-tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}
.wa-tab{display:inline-flex;align-items:center;gap:8px;border:1px solid #ead2c7;background:#fffaf7;color:#7d5249;border-radius:999px;padding:9px 14px;text-decoration:none;font-weight:700;font-size:13px}
.wa-tab.active{background:#a5282c;color:#fff;border-color:#a5282c;box-shadow:0 8px 16px rgba(165,40,44,.18)}
.wa-count{display:inline-flex;min-width:24px;height:24px;align-items:center;justify-content:center;border-radius:999px;background:rgba(255,255,255,.65);font-size:12px}
.wa-tab:not(.active) .wa-count{background:#f4e4de;color:#a5282c}
.wa-filter-grid{display:grid;grid-template-columns:minmax(220px,1.5fr) minmax(160px,1fr) minmax(180px,1fr) 120px auto;gap:12px;align-items:end}
.wa-field label{display:block;font-size:12px;font-weight:800;color:#7a4a41;margin-bottom:6px;text-transform:uppercase;letter-spacing:.02em}
.wa-field .form-control,.wa-field .form-select{border-color:#e2cfc7;border-radius:12px;min-height:42px}
.wa-table-wrap{max-height:66vh;overflow:auto;border-radius:18px}
.wa-table{min-width:1100px;margin:0}
.wa-table thead th{position:sticky;top:0;z-index:2;background:#a70f2f;color:#fff;border:0;padding:13px 14px;font-size:12px;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}
.wa-table tbody td{padding:14px;border-color:#f0ded6;vertical-align:middle}
.wa-table tbody tr:hover{background:#fff7f4}
.wa-title-link{font-weight:800;color:#7d1b1e;text-decoration:none}
.wa-title-link:hover{color:#b83236}
.wa-muted{font-size:12px;color:#8d7770}
.wa-badge{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:5px 10px;font-size:12px;font-weight:800;white-space:nowrap}
.wa-badge-muted{background:#eef0f4;color:#58606c}
.wa-badge-info{background:#e5f1ff;color:#1f65ad}
.wa-badge-warn{background:#fff0c2;color:#9a6500}
.wa-badge-ok{background:#e3f8df;color:#24752d}
.wa-badge-danger{background:#ffe0e6;color:#c72238}
.wa-badge-off{background:#f0ebe8;color:#7a6a65}
.wa-progress{height:8px;background:#f2e8e3;border-radius:999px;overflow:hidden;min-width:100px}
.wa-progress-bar{height:100%;background:linear-gradient(90deg,#b0242b,#f0a126);border-radius:999px}
.wa-actions{display:flex;justify-content:center;gap:6px;flex-wrap:wrap}
.wa-actions .btn{border-radius:10px;padding:6px 9px}
.wa-footer{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding:14px 16px;border-top:1px solid #eadbd4;background:#fffaf7;border-radius:0 0 18px 18px}
.wa-pagination{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.wa-page-link{min-width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #e1c9bf;border-radius:10px;color:#7d1b1e;text-decoration:none;font-weight:700;background:#fff}
.wa-page-link.active{background:#a5282c;color:#fff;border-color:#a5282c}
.wa-page-link.disabled{pointer-events:none;color:#b9aaa4;background:#f7f0ec}
@media (max-width: 992px){.wa-filter-grid{grid-template-columns:1fr 1fr}.wa-page-head{align-items:stretch}.wa-page-head .btn{width:100%}}
@media (max-width: 576px){.wa-filter-grid{grid-template-columns:1fr}.wa-page-head{display:block}.wa-page-head .btn{margin-top:10px}}
</style>

<div class="container-xxl py-3">
  <div class="wa-page-head">
    <div>
      <h4 class="wa-page-title"><i class="ri ri-broadcast-line me-1"></i>Broadcast WhatsApp</h4>
      <p class="wa-page-subtitle">Kelola antrean pesan personal ke target member, pola jeda, status pengiriman, dan broadcast nonaktif.</p>
    </div>
    <?php if ($canCreate && $personalOutboundEnabled): ?>
    <a href="<?= site_url('wa/broadcast/create') ?>" class="btn btn-primary">
      <i class="ri ri-add-line me-1"></i>Buat Broadcast
    </a>
    <?php endif; ?>
  </div>

  <?php if ($flash = $this->session->flashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php elseif ($flash = $this->session->flashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <?php if (!$personalOutboundEnabled): ?>
  <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
    <i class="ri ri-shield-keyhole-line fs-5"></i>
    <div><strong>Broadcast personal dikunci.</strong><br><?= html_escape($personalOutboundLockMessage) ?></div>
  </div>
  <?php endif; ?>

  <div class="wa-tabs">
    <a class="wa-tab <?= $filterTab === 'active' ? 'active' : '' ?>" href="<?= html_escape($makeUrl(['tab' => 'active', 'status' => '', 'page' => 1])) ?>">
      Aktif <span class="wa-count"><?= number_format($tabCounts['active'], 0, ',', '.') ?></span>
    </a>
    <a class="wa-tab <?= $filterTab === 'inactive' ? 'active' : '' ?>" href="<?= html_escape($makeUrl(['tab' => 'inactive', 'status' => '', 'page' => 1])) ?>">
      Nonaktif <span class="wa-count"><?= number_format($tabCounts['inactive'], 0, ',', '.') ?></span>
    </a>
    <a class="wa-tab <?= $filterTab === 'all' ? 'active' : '' ?>" href="<?= html_escape($makeUrl(['tab' => 'all', 'status' => '', 'page' => 1])) ?>">
      Semua <span class="wa-count"><?= number_format($tabCounts['all'], 0, ',', '.') ?></span>
    </a>
  </div>

  <div class="wa-panel wa-panel-pad mb-3">
    <form method="get" class="wa-filter-grid">
      <input type="hidden" name="tab" value="<?= html_escape($filterTab) ?>">
      <div class="wa-field">
        <label>Cari</label>
        <input type="text" name="q" class="form-control" placeholder="Nama, catatan, isi pesan, pembuat..." value="<?= html_escape($filterQ) ?>">
      </div>
      <div class="wa-field">
        <label>Status</label>
        <select name="status" class="form-select">
          <option value="">Semua status</option>
          <?php foreach ($statusOptions as $s): ?>
          <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= html_escape($statusLabel[$s] ?? $s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="wa-field">
        <label>Tipe Target</label>
        <select name="target_type" class="form-select">
          <option value="">Semua target</option>
          <?php foreach ($targetTypeLabel as $value => $label): ?>
          <option value="<?= $value ?>" <?= $filterTarget === $value ? 'selected' : '' ?>><?= html_escape($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="wa-field">
        <label>Baris</label>
        <select name="per_page" class="form-select">
          <?php foreach ($perPageOptions as $opt): ?>
          <option value="<?= (int)$opt ?>" <?= $perPage === (int)$opt ? 'selected' : '' ?>><?= (int)$opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="ri ri-filter-3-line me-1"></i>Filter</button>
        <a href="<?= site_url('wa/broadcast') ?>" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>

  <div class="wa-panel">
    <div class="wa-table-wrap">
      <table class="table wa-table align-middle">
        <thead>
          <tr>
            <th>Broadcast</th>
            <th>Target</th>
            <th>Status</th>
            <th>Progress</th>
            <th class="text-end">Terkirim</th>
            <th class="text-end">Gagal</th>
            <th>Jadwal</th>
            <th>Dibuat</th>
            <th>Dibuat Oleh</th>
            <th class="text-center">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($broadcasts as $bc): ?>
          <?php
            $totalTargets = max(0, (int)($bc['total_targets'] ?? 0));
            $totalSent = max(0, (int)($bc['total_sent'] ?? 0));
            $totalFailed = max(0, (int)($bc['total_failed'] ?? 0));
            $doneTargets = min($totalTargets, $totalSent + $totalFailed);
            $progress = $totalTargets > 0 ? min(100, round(($doneTargets / $totalTargets) * 100)) : 0;
            $bcStatus = strtoupper((string)($bc['status'] ?? 'DRAFT'));
            $canDeactivate = $canEdit && in_array($bcStatus, ['DRAFT','QUEUED','SENDING','FAILED'], true);
          ?>
          <tr>
            <td>
              <a href="<?= site_url('wa/broadcast/detail/' . (int)$bc['id']) ?>" class="wa-title-link">
                <?= html_escape((string)$bc['name']) ?>
              </a>
              <div class="wa-muted"><?= html_escape($targetTypeLabel[$bc['target_type']] ?? (string)$bc['target_type']) ?></div>
              <?php if (!empty($bc['media_path'])): ?>
              <div class="wa-muted"><i class="ri ri-image-line"></i> Ada gambar</div>
              <?php endif; ?>
            </td>
            <td class="fw-bold"><?= number_format($totalTargets, 0, ',', '.') ?></td>
            <td><span class="<?= $statusBadge[$bcStatus] ?? 'wa-badge wa-badge-muted' ?>"><?= html_escape($statusLabel[$bcStatus] ?? $bcStatus) ?></span></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="wa-progress flex-grow-1"><div class="wa-progress-bar" style="width:<?= (int)$progress ?>%"></div></div>
                <span class="wa-muted"><?= (int)$progress ?>%</span>
              </div>
            </td>
            <td class="text-end text-success fw-bold"><?= number_format($totalSent, 0, ',', '.') ?></td>
            <td class="text-end text-danger fw-bold"><?= number_format($totalFailed, 0, ',', '.') ?></td>
            <td class="wa-muted">
              <?= !empty($bc['scheduled_at']) ? html_escape(date('d/m/y H:i', strtotime((string)$bc['scheduled_at']))) : '-' ?>
            </td>
            <td class="wa-muted"><?= !empty($bc['created_at']) ? html_escape(date('d/m/y H:i', strtotime((string)$bc['created_at']))) : '-' ?></td>
            <td class="wa-muted"><?= html_escape((string)($bc['created_by_name'] ?? '-')) ?></td>
            <td>
              <div class="wa-actions">
                <a href="<?= site_url('wa/broadcast/detail/' . (int)$bc['id']) ?>" class="btn btn-sm btn-outline-primary" title="Detail">
                  <i class="ri ri-eye-line"></i>
                </a>
                <?php if ($canEdit && in_array($bcStatus, ['DRAFT','FAILED','CANCELLED'], true)): ?>
                <a href="<?= site_url('wa/broadcast/edit/' . (int)$bc['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                  <i class="ri ri-edit-line"></i>
                </a>
                <?php endif; ?>
                <?php if ($canDeactivate): ?>
                <form method="post" action="<?= site_url('wa/broadcast/deactivate/' . (int)$bc['id']) ?>" class="d-inline" onsubmit="return confirm('Nonaktifkan broadcast ini? Broadcast tidak akan dikirim lagi.');">
                  <input type="hidden" name="wa_broadcast_mutation_csrf" value="<?= html_escape($mutationCsrf) ?>">
                  <button type="submit" class="btn btn-sm btn-outline-warning" title="Nonaktifkan">
                    <i class="ri ri-pause-circle-line"></i>
                  </button>
                </form>
                <?php endif; ?>
                <?php if ($canDelete && in_array($bcStatus, ['DRAFT','FAILED','CANCELLED'], true)): ?>
                <form method="post" action="<?= site_url('wa/broadcast/delete/' . (int)$bc['id']) ?>" class="d-inline" onsubmit="return confirm('Hapus broadcast ini?');">
                  <input type="hidden" name="wa_broadcast_mutation_csrf" value="<?= html_escape($mutationCsrf) ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                    <i class="ri ri-delete-bin-line"></i>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($broadcasts)): ?>
          <tr><td colspan="10" class="text-center text-muted py-5">Tidak ada broadcast sesuai filter.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="wa-footer">
      <div class="wa-muted">
        Menampilkan <?= number_format($fromRow, 0, ',', '.') ?>-<?= number_format($toRow, 0, ',', '.') ?> dari <?= number_format($totalRows, 0, ',', '.') ?> broadcast
      </div>
      <div class="wa-pagination">
        <a class="wa-page-link <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= html_escape($makeUrl(['page' => max(1, $page - 1)])) ?>">‹</a>
        <?php
          $start = max(1, $page - 2);
          $end = min($totalPages, $page + 2);
          if ($start > 1) {
              echo '<a class="wa-page-link" href="' . html_escape($makeUrl(['page' => 1])) . '">1</a>';
              if ($start > 2) echo '<span class="wa-muted px-1">...</span>';
          }
          for ($i = $start; $i <= $end; $i++) {
              echo '<a class="wa-page-link ' . ($i === $page ? 'active' : '') . '" href="' . html_escape($makeUrl(['page' => $i])) . '">' . (int)$i . '</a>';
          }
          if ($end < $totalPages) {
              if ($end < $totalPages - 1) echo '<span class="wa-muted px-1">...</span>';
              echo '<a class="wa-page-link" href="' . html_escape($makeUrl(['page' => $totalPages])) . '">' . (int)$totalPages . '</a>';
          }
        ?>
        <a class="wa-page-link <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= html_escape($makeUrl(['page' => min($totalPages, $page + 1)])) ?>">›</a>
      </div>
    </div>
  </div>
</div>
