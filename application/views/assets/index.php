<?php
$this->load->view('assets/_nav', ['asset_nav_active' => 'items']);
$filters = $filters ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$summary = $summary ?? [];
$rows = $rows ?? [];
$statusLabels = $status_labels ?? [];
$masterLockReady = !empty($master_lock_ready);
$canLock = !empty($can_lock) && $masterLockReady;
$fmtMoney = static function ($value): string {
  return 'Rp ' . number_format((float)$value, 0, ',', '.');
};
?>
<style>
.asset-thumb{width:58px;height:58px;border-radius:8px;object-fit:cover;background:#f3f4f6;border:1px solid #e5e7eb}
.asset-empty-thumb{width:58px;height:58px;border-radius:8px;display:grid;place-items:center;background:#f8fafc;color:#94a3b8;border:1px dashed #cbd5e1}
.asset-stat{border:1px solid #e7d8ce;border-radius:8px;background:#fff;min-height:92px}
.asset-stat .label{font-size:.76rem;text-transform:uppercase;color:#8b6f61;letter-spacing:.02em}
.asset-stat .value{font-size:1.35rem;font-weight:800;color:#2f1f1a}
.asset-table{table-layout:fixed}
.asset-table th{font-size:.76rem;text-transform:uppercase;color:#77665c;letter-spacing:.02em;white-space:nowrap}
.asset-table td{vertical-align:middle}
.asset-progress{height:8px;border-radius:99px;background:#eef2f7;overflow:hidden}
.asset-progress span{display:block;height:100%;background:#18745c}
.asset-status-pills{display:flex;flex-wrap:wrap;gap:.35rem}
.asset-status-pills .badge{font-weight:600}
.asset-actions{display:inline-flex;justify-content:flex-end;gap:.35rem;min-width:86px}
.asset-actions .btn{white-space:nowrap}
@media (max-width: 991.98px){.asset-table{table-layout:auto}.asset-actions{width:100%;justify-content:flex-start!important}.asset-table td{white-space:normal}}
</style>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
  <div>
    <h4 class="mb-1">Pengelolaan Aset Namua</h4>
    <div class="text-muted">Tampilan utama diringkas per produk aset yang sama. Detail grup tetap menampilkan setiap unit fisik beserta kode asetnya.</div>
  </div>
  <?php if (!empty($can_create)): ?>
    <a href="<?= site_url('asset-management/create') ?>" class="btn btn-primary">
      <i class="ri ri-add-line me-1"></i>Tambah Bulk
    </a>
  <?php endif; ?>
</div>

<?php if (empty($table_ready)): ?>
  <div class="alert alert-warning">Tabel aset belum siap. Jalankan SQL <code>2026-08-09a_asset_management_module.sql</code>.</div>
<?php endif; ?>
<?php if (!empty($table_ready) && !$masterLockReady): ?>
  <div class="alert alert-warning">Fitur kunci data aset belum siap. Jalankan SQL <code>2026-08-24b_asset_master_lock_and_change_request_foundation.sql</code> sebelum mengunci pendataan aset.</div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Jenis aset</div><div class="value"><?= number_format((int)($pg['total'] ?? 0), 0, ',', '.') ?></div><div class="small text-muted">Grup produk pada filter</div></div></div>
  <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Total unit</div><div class="value"><?= number_format((int)($summary['total'] ?? 0), 0, ',', '.') ?></div><div class="small text-muted">Unit fisik tercatat</div></div></div>
  <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Bermasalah</div><div class="value text-danger"><?= number_format((int)(($summary['broken'] ?? 0) + ($summary['repair'] ?? 0) + ($summary['lost'] ?? 0)), 0, ',', '.') ?></div><div class="small text-muted">Rusak, repair, hilang</div></div></div>
  <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Nilai buku</div><div class="value"><?= $fmtMoney($summary['book_value'] ?? 0) ?></div><div class="small text-muted">Estimasi setelah penyusutan</div></div></div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get" action="<?= site_url('asset-management') ?>">
      <div class="col-12 col-lg-3">
        <label class="form-label">Cari aset</label>
        <input type="text" class="form-control" name="q" value="<?= html_escape($filters['q'] ?? '') ?>" placeholder="Nama, kategori, brand, model, kode unit, lokasi">
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label">Status unit</label>
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
        <a class="btn btn-outline-secondary" href="<?= site_url('asset-management') ?>" title="Bersihkan filter" aria-label="Bersihkan filter"><i class="ri ri-refresh-line" aria-hidden="true"></i></a>
      </div>
    </form>
  </div>
</div>

<form method="post" action="<?= site_url('asset-management/lock-bulk') ?>" onsubmit="return confirm('Kunci semua unit yang masih terbuka pada produk aset terpilih? Setelah terkunci, perubahan data awal harus melalui pengajuan perubahan data aset.');">
  <input type="hidden" name="back_url" value="<?= html_escape('asset-management' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')) ?>">
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover asset-table mb-0">
      <colgroup>
        <?php if ($canLock): ?><col style="width:5%"><?php endif; ?>
        <col style="width:<?= $canLock ? '23%' : '26%' ?>">
        <col style="width:8%">
        <col style="width:18%">
        <col style="width:18%">
        <col style="width:12%">
        <col style="width:10%">
        <col style="width:8%">
      </colgroup>
      <thead class="table-light">
        <tr>
          <?php if ($canLock): ?><th class="text-center"><input class="form-check-input" type="checkbox" data-asset-lock-select-all title="Pilih semua produk pada halaman ini"></th><?php endif; ?>
          <th>Produk Aset</th>
          <th class="text-center">Qty</th>
          <th>Status Unit</th>
          <th>Lokasi</th>
          <th>Nilai Grup</th>
          <th>Kondisi</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="<?= $canLock ? 8 : 7 ?>" class="text-center text-muted py-5">Belum ada grup aset pada filter ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $photo = trim((string)($row['photo_path'] ?? ''));
            $query = $_GET;
            unset($query['page'], $query['q']);
            $detailUrl = site_url('asset-management/group/' . rawurlencode((string)$row['group_key']));
            if (!empty($query)) {
              $detailUrl .= '?' . http_build_query($query);
            }
          ?>
          <tr>
            <?php if ($canLock): ?>
              <td class="text-center">
                <?php if ((int)($row['open_count'] ?? 0) > 0): ?>
                  <input class="form-check-input" type="checkbox" name="group_keys[]" value="<?= html_escape((string)$row['group_key']) ?>" data-asset-lock-item>
                <?php else: ?>
                  <i class="ri ri-lock-line text-success" title="Semua unit sudah terkunci"></i>
                <?php endif; ?>
              </td>
            <?php endif; ?>
            <td style="min-width:310px">
              <div class="d-flex gap-2 align-items-center">
                <?php if ($photo !== ''): ?>
                  <img class="asset-thumb" src="<?= html_escape(base_url($photo)) ?>" alt="<?= html_escape($row['asset_name'] ?? '') ?>">
                <?php else: ?>
                  <span class="asset-empty-thumb"><i class="ri ri-archive-2-line"></i></span>
                <?php endif; ?>
                <div>
                  <div class="fw-semibold"><?= html_escape($row['asset_name'] ?? '-') ?></div>
                  <div class="small text-muted"><?= html_escape($row['category_name'] ?? '-') ?><?= !empty($row['sample_asset_code']) ? ' | contoh ' . html_escape($row['sample_asset_code']) : '' ?></div>
                  <?php if (!empty($row['brand']) || !empty($row['model_name'])): ?>
                    <div class="small text-muted"><?= html_escape(trim((string)($row['brand'] ?? '') . ' ' . (string)($row['model_name'] ?? ''))) ?></div>
                  <?php endif; ?>
                  <?php if ($masterLockReady): ?>
                    <div class="small mt-1"><span class="badge bg-<?= (int)($row['locked_count'] ?? 0) > 0 ? 'success' : 'secondary' ?>">Terkunci <?= (int)($row['locked_count'] ?? 0) ?></span> <span class="text-muted">Pendataan <?= (int)($row['open_count'] ?? 0) ?></span></div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td class="text-center">
              <div class="fw-bold fs-5"><?= number_format((int)($row['unit_count'] ?? 0), 0, ',', '.') ?></div>
              <div class="small text-muted">unit</div>
            </td>
            <td style="min-width:220px">
              <div class="asset-status-pills">
                <span class="badge bg-success">Aktif <?= (int)($row['active_count'] ?? 0) ?></span>
                <?php if ((int)($row['broken_count'] ?? 0) > 0): ?><span class="badge bg-danger">Rusak <?= (int)$row['broken_count'] ?></span><?php endif; ?>
                <?php if ((int)($row['repair_count'] ?? 0) > 0): ?><span class="badge bg-warning">Repair <?= (int)$row['repair_count'] ?></span><?php endif; ?>
                <?php if ((int)($row['lost_count'] ?? 0) > 0): ?><span class="badge bg-dark">Hilang <?= (int)$row['lost_count'] ?></span><?php endif; ?>
                <?php if ((int)($row['retired_count'] ?? 0) > 0): ?><span class="badge bg-secondary">Nonaktif <?= (int)$row['retired_count'] ?></span><?php endif; ?>
              </div>
            </td>
            <td style="min-width:220px">
              <div><?= html_escape($row['division_names'] ?: '-') ?></div>
              <div class="small text-muted"><?= html_escape($row['locations'] ?: 'Lokasi belum dirinci') ?></div>
            </td>
            <td>
              <div class="fw-semibold"><?= $fmtMoney($row['book_value'] ?? 0) ?></div>
              <div class="small text-muted">Beli <?= $fmtMoney($row['acquisition_value'] ?? 0) ?></div>
              <div class="small text-muted">Susut rata-rata <?= number_format((float)($row['avg_depreciation_percent'] ?? 0), 1, ',', '.') ?>%</div>
            </td>
            <td style="min-width:140px">
              <div class="d-flex justify-content-between small"><span>Rata-rata</span><strong><?= number_format((float)($row['avg_condition'] ?? 0), 1, ',', '.') ?>%</strong></div>
              <div class="asset-progress"><span style="width:<?= max(0, min(100, (float)($row['avg_condition'] ?? 0))) ?>%"></span></div>
            </td>
            <td class="text-end">
              <div class="d-inline-flex flex-wrap gap-1 asset-actions justify-content-end">
                <?php if (!empty($can_edit) && (!empty($can_create) || !empty($can_delete)) && (int)($row['open_count'] ?? 0) > 0): ?>
                  <a class="btn btn-sm btn-outline-primary" href="<?= html_escape($detailUrl . '#quantity') ?>">Atur Qty</a>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= html_escape($detailUrl) ?>">Detail</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex align-items-center gap-2">
      <span class="text-muted small">Total <?= number_format((int)($pg['total'] ?? 0), 0, ',', '.') ?> grup aset</span>
      <?php if ($canLock): ?><button type="submit" class="btn btn-sm btn-outline-success"><i class="ri ri-lock-line me-1"></i>Kunci Produk Terpilih</button><?php endif; ?>
    </div>
    <div class="btn-group">
      <?php
        $query = $_GET;
        $prev = max(1, (int)$pg['page'] - 1);
        $next = min((int)$pg['total_pages'], (int)$pg['page'] + 1);
        $query['page'] = $prev;
      ?>
      <a class="btn btn-sm btn-outline-secondary <?= (int)$pg['page'] <= 1 ? 'disabled' : '' ?>" href="<?= site_url('asset-management?' . http_build_query($query)) ?>">Prev</a>
      <button class="btn btn-sm btn-outline-secondary" type="button" disabled>Page <?= (int)$pg['page'] ?>/<?= (int)$pg['total_pages'] ?></button>
      <?php $query['page'] = $next; ?>
      <a class="btn btn-sm btn-outline-secondary <?= (int)$pg['page'] >= (int)$pg['total_pages'] ? 'disabled' : '' ?>" href="<?= site_url('asset-management?' . http_build_query($query)) ?>">Next</a>
    </div>
  </div>
</div>
</form>

<?php if ($canLock): ?>
<script>
(function(){
  var all = document.querySelector('[data-asset-lock-select-all]');
  if (!all) return;
  all.addEventListener('change', function(){
    document.querySelectorAll('[data-asset-lock-item]').forEach(function(item){ item.checked = all.checked; });
  });
})();
</script>
<?php endif; ?>
