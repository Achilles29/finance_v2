<?php
$this->load->view('assets/_nav', ['asset_nav_active' => 'labels']);
$filters = $filters ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$rows = $rows ?? [];
$statusLabels = $status_labels ?? [];
$businessProfile = is_array($business_profile ?? null) ? $business_profile : [];
$assetBrand = trim((string)($businessProfile['short_name'] ?? $businessProfile['display_name'] ?? ''));
$assetBrand = $assetBrand !== '' ? $assetBrand : 'FINANCE';
?>
<style>
.asset-panel{border:1px solid #e7d8ce;border-radius:8px;background:#fff;box-shadow:0 10px 24px rgba(35,24,18,.04)}
.asset-label-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(245px,1fr));gap:12px}
.asset-label-card{border:1px solid #1f2937;border-radius:8px;background:#fff;padding:10px;min-height:148px;display:grid;grid-template-columns:94px minmax(0,1fr);gap:10px;page-break-inside:avoid}
.asset-label-qr{width:94px;height:94px;border:1px solid #e5e7eb;border-radius:6px;object-fit:contain}
.asset-label-code{font-size:.8rem;font-weight:800;color:#111827;line-height:1.15;word-break:break-word}
.asset-label-name{font-size:.9rem;font-weight:700;line-height:1.18;color:#2f1f1a}
.asset-label-meta{font-size:.72rem;color:#6b7280;line-height:1.25}
.asset-label-brand{grid-column:1/-1;border-top:1px dashed #d1d5db;padding-top:6px;font-size:.74rem;color:#374151}
@media print{
  body{background:#fff!important}
  .layout-menu,.layout-navbar,.asset-no-print,.footer{display:none!important}
  .content-wrapper,.container-xxl,.container-p-y{padding:0!important;margin:0!important;max-width:none!important}
  .asset-panel{border:0;box-shadow:none}
  .asset-label-grid{grid-template-columns:repeat(3,1fr);gap:8px}
  .asset-label-card{min-height:132px}
}
</style>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3 asset-no-print">
  <div>
    <h4 class="mb-1"><i class="ri ri-fingerprint-line me-1"></i>QR Label Aset</h4>
    <div class="text-muted">Cetak label per unit aset untuk scan detail, cek fisik, dan rekon.</div>
  </div>
  <button type="button" class="btn btn-primary" onclick="window.print()"><i class="ri ri-printer-line me-1"></i>Cetak Label</button>
</div>

<div class="asset-panel mb-3 asset-no-print">
  <div class="p-3">
    <form class="row g-2 align-items-end" method="get" action="<?= site_url('asset-management/labels') ?>">
      <div class="col-12 col-lg-3">
        <label class="form-label">Cari aset</label>
        <input type="search" class="form-control" name="q" value="<?= html_escape($filters['q'] ?? '') ?>" placeholder="Kode, nama, kategori, serial, lokasi">
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
          <?php foreach (['ALL' => 'Semua', 'ISSUE' => 'Bermasalah'] + $statusLabels as $key => $label): ?>
            <option value="<?= html_escape($key) ?>" <?= (($filters['status'] ?? '') === $key) ? 'selected' : '' ?>><?= html_escape($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label">Kategori</label>
        <select class="form-select" name="category_id">
          <option value="0">Semua</option>
          <?php foreach (($categories ?? []) as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= (int)($filters['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>><?= html_escape($cat['category_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label">Divisi</label>
        <select class="form-select" name="division_id">
          <option value="0">Semua</option>
          <?php foreach (($divisions ?? []) as $div): ?>
            <option value="<?= (int)$div['id'] ?>" <?= (int)($filters['division_id'] ?? 0) === (int)$div['id'] ? 'selected' : '' ?>><?= html_escape($div['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-1">
        <label class="form-label">Baris</label>
        <select class="form-select" name="per_page">
          <?php foreach ([10,25,50,100] as $pp): ?>
            <option value="<?= $pp ?>" <?= (int)($pg['per_page'] ?? 25) === $pp ? 'selected' : '' ?>><?= $pp ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-lg-2 asset-filter-actions">
        <button class="btn btn-outline-primary" type="submit" title="Terapkan filter" aria-label="Terapkan filter"><i class="ri ri-search-line" aria-hidden="true"></i></button>
        <a class="btn btn-outline-secondary" href="<?= site_url('asset-management/labels') ?>" title="Bersihkan filter" aria-label="Bersihkan filter"><i class="ri ri-refresh-line" aria-hidden="true"></i></a>
      </div>
    </form>
  </div>
</div>

<div class="asset-panel p-3">
  <?php if (empty($rows)): ?>
    <div class="text-center text-muted py-5">Belum ada aset pada filter ini.</div>
  <?php else: ?>
    <div class="asset-label-grid">
      <?php foreach ($rows as $row): ?>
        <?php
          $targetUrl = site_url('asset-management/detail/' . (int)($row['id'] ?? 0));
          $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . rawurlencode($targetUrl);
          $meta = trim((string)($row['division_name'] ?? '') . ' | ' . (string)($row['current_location'] ?? '') . ' | ' . (string)($row['custodian_name'] ?? ''), ' |');
        ?>
        <div class="asset-label-card">
          <img class="asset-label-qr" src="<?= html_escape($qrUrl) ?>" alt="QR <?= html_escape($row['asset_code'] ?? '') ?>">
          <div class="min-w-0">
            <div class="asset-label-code"><?= html_escape($row['asset_code'] ?? '-') ?></div>
            <div class="asset-label-name mt-1"><?= html_escape($row['asset_name'] ?? '-') ?></div>
            <div class="asset-label-meta mt-1"><?= html_escape($row['category_name'] ?? '-') ?></div>
            <div class="asset-label-meta"><?= html_escape($meta ?: 'Lokasi/PIC belum ditentukan') ?></div>
          </div>
          <div class="asset-label-brand"><?= html_escape($assetBrand) ?> ASSET | Scan untuk detail aset dan riwayat audit</div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="asset-no-print mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
  <span class="text-muted small">Total <?= number_format((int)($pg['total'] ?? 0), 0, ',', '.') ?> aset</span>
  <div class="btn-group">
    <?php
      $query = $_GET;
      $prev = max(1, (int)$pg['page'] - 1);
      $next = min((int)$pg['total_pages'], (int)$pg['page'] + 1);
      $query['page'] = $prev;
    ?>
    <a class="btn btn-sm btn-outline-secondary <?= (int)$pg['page'] <= 1 ? 'disabled' : '' ?>" href="<?= site_url('asset-management/labels?' . http_build_query($query)) ?>">Prev</a>
    <button class="btn btn-sm btn-outline-secondary" type="button" disabled>Page <?= (int)$pg['page'] ?>/<?= (int)$pg['total_pages'] ?></button>
    <?php $query['page'] = $next; ?>
    <a class="btn btn-sm btn-outline-secondary <?= (int)$pg['page'] >= (int)$pg['total_pages'] ? 'disabled' : '' ?>" href="<?= site_url('asset-management/labels?' . http_build_query($query)) ?>">Next</a>
  </div>
</div>
