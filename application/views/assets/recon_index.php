<?php
$this->load->view('assets/_nav', ['asset_nav_active' => 'recon']);
$filters = $filters ?? ['month' => date('Y-m'), 'status' => 'ALL', 'division_id' => 0];
$previewRows = $preview_rows ?? [];
$previewSummary = $preview_summary ?? [];
$previewLimit = (int)($preview_limit ?? 100);
$historyLimit = (int)($history_limit ?? 25);
$activeTab = (string)($active_tab ?? 'preview');
$baseQuery = [
  'month' => $filters['month'] ?? date('Y-m'),
  'division_id' => (int)($filters['division_id'] ?? 0),
  'status' => $filters['status'] ?? 'ALL',
  'preview_rows' => $previewLimit,
  'history_rows' => $historyLimit,
];
$fmtMoney = static function ($value): string {
  return 'Rp ' . number_format((float)$value, 0, ',', '.');
};
$statusBadge = static function ($status): string {
  $status = strtoupper((string)$status);
  $map = ['ACTIVE' => 'success', 'BROKEN' => 'danger', 'REPAIR' => 'warning', 'LOST' => 'dark', 'RETIRED' => 'secondary', 'DISPOSED' => 'secondary'];
  return $map[$status] ?? 'secondary';
};
$reconBadge = static function ($status): string {
  $status = strtoupper((string)$status);
  return ['DRAFT' => 'warning', 'POSTED' => 'success', 'CANCELLED' => 'secondary'][$status] ?? 'secondary';
};
?>
<style>
.asset-page-title{display:flex;align-items:center;gap:.6rem}
.asset-title-icon{width:36px;height:36px;flex:0 0 36px;border-radius:8px;display:grid;place-items:center;background:#f3faf7;color:#18745c}
.asset-title-icon i{font-size:1.1rem;line-height:1}
.asset-panel{border:1px solid #e7d8ce;border-radius:8px;background:#fff;box-shadow:0 10px 24px rgba(35,24,18,.04)}
.asset-stat{border:1px solid #eadbd1;border-radius:8px;background:#fff;min-height:90px}
.asset-stat .label{font-size:.74rem;text-transform:uppercase;color:#8b6f61;letter-spacing:.02em}
.asset-stat .value{font-size:1.35rem;font-weight:800;color:#2f1f1a;line-height:1.15}
.asset-table-scroll{max-height:560px;overflow:auto}
.asset-table{table-layout:fixed;min-width:980px}
.asset-table thead th{position:sticky;top:0;z-index:2;background:#f8f4f0}
.asset-table th{font-size:.76rem;text-transform:uppercase;color:#77665c;letter-spacing:.02em;white-space:nowrap}
.asset-table td{vertical-align:middle}
.asset-thumb{width:52px;height:52px;min-width:52px;border-radius:8px;object-fit:cover;background:#f8fafc;border:1px solid #e5e7eb}
.asset-empty-thumb{width:52px;height:52px;min-width:52px;border-radius:8px;display:grid;place-items:center;background:#f8fafc;color:#94a3b8;border:1px dashed #cbd5e1}
.asset-progress{height:8px;border-radius:99px;background:#eef2f7;overflow:hidden}
.asset-progress span{display:block;height:100%;background:#18745c}
.asset-actions{display:inline-flex;justify-content:flex-end;gap:.35rem;min-width:74px}
.asset-actions .btn{white-space:nowrap}
@media (max-width: 991.98px){.asset-table{table-layout:auto}.asset-table th,.asset-table td{white-space:normal}.asset-actions{justify-content:flex-start}}
</style>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
  <div>
    <h4 class="mb-1 asset-page-title"><span class="asset-title-icon"><i class="ri ri-calendar-check-line"></i></span><span>Rekon Aset Bulanan</span></h4>
    <div class="text-muted">Cek preview kandidat aset dulu, lalu generate snapshot rekon untuk periode yang dipilih.</div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Kandidat snapshot</div><div class="value"><?= number_format((int)($previewSummary['total'] ?? 0), 0, ',', '.') ?></div><div class="small text-muted">Unit masuk rekon</div></div></div>
  <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Aset baru periode</div><div class="value text-success"><?= number_format((int)($previewSummary['new_in_period'] ?? 0), 0, ',', '.') ?></div><div class="small text-muted">Akan ikut dicek</div></div></div>
  <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Perlu perhatian</div><div class="value text-danger"><?= number_format((int)($previewSummary['issue'] ?? 0), 0, ',', '.') ?></div><div class="small text-muted">Rusak/perbaikan</div></div></div>
  <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Nilai buku kandidat</div><div class="value"><?= $fmtMoney($previewSummary['book_value'] ?? 0) ?></div><div class="small text-muted">Estimasi saat ini</div></div></div>
</div>

<div class="asset-panel mb-3">
  <div class="p-3 border-bottom">
    <div class="fw-semibold">Filter rekon</div>
    <div class="small text-muted">Filter ini mengatur preview dan daftar riwayat snapshot.</div>
  </div>
  <div class="p-3">
    <form class="row g-2 align-items-end" method="get" action="<?= site_url('asset-management/recon') ?>">
      <input type="hidden" name="tab" value="<?= html_escape($activeTab) ?>">
      <input type="hidden" name="preview_rows" value="<?= (int)$previewLimit ?>">
      <input type="hidden" name="history_rows" value="<?= (int)$historyLimit ?>">
      <div class="col-6 col-lg-2">
        <label class="form-label">Periode</label>
        <input type="month" name="month" class="form-control" value="<?= html_escape($filters['month'] ?? date('Y-m')) ?>">
      </div>
      <div class="col-6 col-lg-3">
        <label class="form-label">Divisi</label>
        <select name="division_id" class="form-select">
          <option value="0">Semua divisi</option>
          <?php foreach (($divisions ?? []) as $div): ?>
            <option value="<?= (int)$div['id'] ?>" <?= (int)($filters['division_id'] ?? 0) === (int)$div['id'] ? 'selected' : '' ?>><?= html_escape($div['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label">Status riwayat</label>
        <select name="status" class="form-select">
          <?php foreach (['ALL' => 'Semua', 'DRAFT' => 'Draft', 'POSTED' => 'Posted', 'CANCELLED' => 'Cancelled'] as $key => $label): ?>
            <option value="<?= $key ?>" <?= ($filters['status'] ?? 'ALL') === $key ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-lg-5 asset-filter-actions">
        <button class="btn btn-outline-primary" type="submit" title="Terapkan filter" aria-label="Terapkan filter"><i class="ri ri-search-line" aria-hidden="true"></i></button>
        <a class="btn btn-outline-secondary" href="<?= site_url('asset-management/recon') ?>" title="Bersihkan filter" aria-label="Bersihkan filter"><i class="ri ri-refresh-line" aria-hidden="true"></i></a>
      </div>
    </form>
  </div>
</div>

<?php
$workspace_tabs = [
  ['label' => 'Preview Snapshot', 'url' => site_url('asset-management/recon?' . http_build_query(array_merge($baseQuery, ['tab' => 'preview']))), 'active' => $activeTab === 'preview'],
  ['label' => 'Riwayat Snapshot', 'url' => site_url('asset-management/recon?' . http_build_query(array_merge($baseQuery, ['tab' => 'history']))), 'active' => $activeTab === 'history'],
];
$workspace_tab_label = 'Rekon Aset';
$workspace_tab_aria_label = 'Pilih tampilan rekonsiliasi aset';
$this->load->view('layout/_workspace_tabs', get_defined_vars());
?>

<?php if ($activeTab === 'preview'): ?>
  <div class="asset-panel">
    <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-end gap-2">
      <div>
        <div class="fw-semibold">Preview kandidat snapshot</div>
        <div class="small text-muted">Ditampilkan <?= number_format(count($previewRows), 0, ',', '.') ?> dari <?= number_format((int)($previewSummary['total'] ?? 0), 0, ',', '.') ?> unit. Snapshot tetap mengambil semua kandidat.</div>
      </div>
      <form class="d-flex align-items-end gap-2" method="get" action="<?= site_url('asset-management/recon') ?>">
        <?php foreach (['month','division_id','status','history_rows'] as $name): ?>
          <input type="hidden" name="<?= $name ?>" value="<?= html_escape((string)($baseQuery[$name] ?? '')) ?>">
        <?php endforeach; ?>
        <input type="hidden" name="tab" value="preview">
        <div>
          <label class="form-label small mb-1">Baris</label>
          <select name="preview_rows" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ([25,50,100,250,500] as $n): ?><option value="<?= $n ?>" <?= $previewLimit === $n ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?>
          </select>
        </div>
      </form>
    </div>
    <div class="asset-table-scroll">
      <table class="table table-hover asset-table mb-0">
        <colgroup>
          <col style="width:34%"><col style="width:12%"><col style="width:22%"><col style="width:16%"><col style="width:16%">
        </colgroup>
        <thead class="table-light"><tr><th>Aset</th><th>Status</th><th>Lokasi</th><th>Nilai</th><th>Kondisi</th></tr></thead>
        <tbody>
          <?php if (empty($previewRows)): ?><tr><td colspan="5" class="text-center text-muted py-5">Tidak ada kandidat aset untuk snapshot ini.</td></tr><?php endif; ?>
          <?php foreach ($previewRows as $row): ?>
            <?php $photo = trim((string)($row['photo_path'] ?? '')); ?>
            <tr>
              <td>
                <div class="d-flex gap-2 align-items-center">
                  <?php if ($photo !== ''): ?><img class="asset-thumb" src="<?= html_escape(base_url($photo)) ?>" alt="<?= html_escape($row['asset_name'] ?? '') ?>"><?php else: ?><span class="asset-empty-thumb"><i class="ri ri-file-text-line"></i></span><?php endif; ?>
                  <div class="min-w-0">
                    <div class="fw-semibold text-truncate"><?= html_escape($row['asset_name'] ?? '-') ?></div>
                    <div class="small text-muted text-truncate"><?= html_escape($row['asset_code'] ?? '-') ?> | <?= html_escape($row['category_name'] ?? '-') ?></div>
                    <?php if (!empty($row['is_new_in_period'])): ?><span class="badge bg-success-subtle text-success mt-1">Baru periode ini</span><?php endif; ?>
                  </div>
                </div>
              </td>
              <td><span class="badge bg-<?= $statusBadge($row['status'] ?? '') ?>"><?= html_escape($row['status_label'] ?? '-') ?></span></td>
              <td><div class="text-truncate"><?= html_escape($row['division_name'] ?? '-') ?></div><div class="small text-muted text-truncate"><?= html_escape($row['current_location'] ?: ($row['outlet_name'] ?? '-')) ?></div></td>
              <td><div class="fw-semibold"><?= $fmtMoney($row['book_value'] ?? 0) ?></div><div class="small text-muted">Beli <?= $fmtMoney($row['acquisition_cost'] ?? 0) ?></div></td>
              <td><div class="d-flex justify-content-between small"><span>Skor</span><strong><?= (int)($row['condition_score'] ?? 0) ?>%</strong></div><div class="asset-progress"><span style="width:<?= max(0, min(100, (int)($row['condition_score'] ?? 0))) ?>%"></span></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <form class="p-3 border-top" method="post" action="<?= site_url('asset-management/recon/generate') ?>">
      <input type="hidden" name="period_month" value="<?= html_escape($filters['month'] ?? date('Y-m')) ?>">
      <input type="hidden" name="division_id" value="<?= (int)($filters['division_id'] ?? 0) ?>">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-8">
          <label class="form-label">Catatan snapshot</label>
          <input type="text" name="notes" class="form-control" placeholder="Contoh: Rekon tutup bulan outlet pusat">
        </div>
        <div class="col-12 col-lg-4 d-grid">
          <button class="btn btn-primary <?= empty($can_create) || (int)($previewSummary['total'] ?? 0) <= 0 ? 'disabled' : '' ?>" type="submit" onclick="return confirm('Generate snapshot dari preview ini?')"><i class="ri ri-file-list-3-line me-1"></i>Generate Snapshot dari Preview</button>
        </div>
      </div>
    </form>
  </div>
<?php else: ?>
  <div class="asset-panel">
    <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-end gap-2">
      <div>
        <div class="fw-semibold">Riwayat snapshot rekon</div>
        <div class="small text-muted">Draft bisa dibuka untuk checklist fisik, lalu diposting setelah lengkap.</div>
      </div>
      <form class="d-flex align-items-end gap-2" method="get" action="<?= site_url('asset-management/recon') ?>">
        <?php foreach (['month','division_id','status','preview_rows'] as $name): ?>
          <input type="hidden" name="<?= $name ?>" value="<?= html_escape((string)($baseQuery[$name] ?? '')) ?>">
        <?php endforeach; ?>
        <input type="hidden" name="tab" value="history">
        <div>
          <label class="form-label small mb-1">Baris</label>
          <select name="history_rows" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ([10,25,50,100] as $n): ?><option value="<?= $n ?>" <?= $historyLimit === $n ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?>
          </select>
        </div>
      </form>
    </div>
    <div class="asset-table-scroll">
      <table class="table table-hover asset-table mb-0">
        <colgroup>
          <col style="width:23%"><col style="width:12%"><col style="width:21%"><col style="width:19%"><col style="width:10%"><col style="width:9%"><col style="width:6%">
        </colgroup>
        <thead class="table-light"><tr><th>No Rekon</th><th>Periode</th><th>Divisi</th><th>Progress</th><th>Issue</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
          <?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-5">Belum ada rekon pada filter ini.</td></tr><?php endif; ?>
          <?php foreach (($rows ?? []) as $row): ?>
            <?php $lineCount = (int)($row['line_count'] ?? 0); $checkedCount = (int)($row['checked_count'] ?? 0); $pct = $lineCount > 0 ? round(($checkedCount / $lineCount) * 100) : 0; ?>
            <tr>
              <td><div class="fw-semibold text-truncate"><?= html_escape($row['recon_no'] ?? '-') ?></div><div class="small text-muted text-truncate"><?= html_escape($row['created_at'] ?? '-') ?></div></td>
              <td><?= html_escape($row['period_month'] ?? '-') ?></td>
              <td><div class="text-truncate"><?= html_escape($row['division_name'] ?? 'Semua divisi') ?></div></td>
              <td><div class="d-flex justify-content-between small"><span><?= $checkedCount ?>/<?= $lineCount ?></span><span><?= $pct ?>%</span></div><div class="asset-progress"><span style="width:<?= $pct ?>%"></span></div></td>
              <td><span class="badge bg-<?= (int)($row['issue_count'] ?? 0) > 0 ? 'danger' : 'success' ?>"><?= (int)($row['issue_count'] ?? 0) ?></span></td>
              <td><span class="badge bg-<?= $reconBadge($row['status'] ?? '') ?>"><?= html_escape($row['status'] ?? '-') ?></span></td>
              <td class="text-end"><span class="asset-actions"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('asset-management/recon/' . (int)$row['id']) ?>">Buka</a></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
