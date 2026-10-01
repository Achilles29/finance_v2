<?php
$this->load->view('assets/_nav', ['asset_nav_active' => 'changes']);
$request = $request ?? [];
$asset = $asset ?? [];
$changes = $request['change_rows'] ?? [];
$status = strtoupper((string)($request['status'] ?? ''));
$statusClass = [
    'PENDING' => 'warning',
    'APPROVED' => 'primary',
    'REJECTED' => 'danger',
    'POSTED' => 'success',
    'CANCELLED' => 'secondary',
][$status] ?? 'secondary';
?>
<style>
.asset-change-kv{display:grid;grid-template-columns:160px minmax(0,1fr);gap:.55rem .9rem}
.asset-change-kv .k{color:#7b6a60}.asset-change-kv .v{font-weight:500}
.asset-change-table th{font-size:.76rem;text-transform:uppercase;letter-spacing:.02em}
@media(max-width:767.98px){.asset-change-kv{grid-template-columns:1fr}.asset-change-kv .v{margin-top:-.35rem}}
</style>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <h4 class="mb-1">Detail Perubahan Data Aset</h4>
    <div class="text-muted"><?= html_escape($request['request_no'] ?? '-') ?></div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary" href="<?= site_url('asset-management/detail/' . (int)($asset['id'] ?? 0)) ?>"><i class="ri ri-archive-2-line me-1"></i>Detail aset</a>
    <a class="btn btn-outline-secondary" href="<?= site_url('asset-management/changes') ?>">Kembali</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold">Ringkasan</span><span class="badge bg-<?= $statusClass ?>"><?= html_escape($request['status_label'] ?? '-') ?></span></div>
      <div class="card-body asset-change-kv">
        <div class="k">Aset</div><div class="v"><a href="<?= site_url('asset-management/detail/' . (int)($asset['id'] ?? 0)) ?>"><?= html_escape(($asset['asset_code'] ?? '-') . ' | ' . ($asset['asset_name'] ?? '-')) ?></a></div>
        <div class="k">Divisi</div><div class="v"><?= html_escape($request['division_name'] ?? '-') ?></div>
        <div class="k">Diajukan oleh</div><div class="v"><?= html_escape($request['requested_by_name'] ?? '-') ?><div class="small text-muted"><?= html_escape($request['created_at'] ?? '-') ?></div></div>
        <div class="k">Alasan</div><div class="v"><?= nl2br(html_escape($request['reason'] ?? '-')) ?></div>
        <?php if (!empty($request['approved_at'])): ?><div class="k">Disetujui</div><div class="v"><?= html_escape(($request['approved_by_name'] ?? '-') . ' | ' . ($request['approved_at'] ?? '-')) ?></div><?php endif; ?>
        <?php if (!empty($request['rejected_at'])): ?><div class="k">Penolakan</div><div class="v"><?= html_escape(($request['rejected_by_name'] ?? '-') . ' | ' . ($request['rejected_at'] ?? '-')) ?><div class="small text-danger mt-1"><?= nl2br(html_escape($request['rejection_reason'] ?? '-')) ?></div></div><?php endif; ?>
        <?php if (!empty($request['posted_at'])): ?><div class="k">Diterapkan</div><div class="v"><?= html_escape(($request['posted_by_name'] ?? '-') . ' | ' . ($request['posted_at'] ?? '-')) ?></div><?php endif; ?>
        <?php if (!empty($request['evidence_path'])): ?><div class="k">Bukti</div><div class="v"><a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="<?= html_escape(base_url($request['evidence_path'])) ?>">Lihat bukti</a></div><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header fw-semibold">Data yang akan berubah</div>
      <div class="table-responsive">
        <table class="table table-hover asset-change-table mb-0">
          <thead class="table-light"><tr><th>Data</th><th>Sebelum</th><th>Sesudah diajukan</th></tr></thead>
          <tbody>
            <?php if (empty($changes)): ?><tr><td colspan="3" class="text-center text-muted py-4">Tidak ada perbedaan data yang dapat ditampilkan.</td></tr><?php endif; ?>
            <?php foreach ($changes as $change): ?>
              <tr><td class="fw-semibold"><?= html_escape($change['label'] ?? '-') ?></td><td><?= html_escape($change['before_label'] ?? '-') ?></td><td class="text-primary fw-semibold"><?= html_escape($change['after_label'] ?? '-') ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($status === 'PENDING' && !empty($can_edit)): ?>
      <div class="card mb-3"><div class="card-body d-flex flex-wrap align-items-end gap-2">
        <form method="post" action="<?= site_url('asset-management/changes/' . (int)$request['id'] . '/approve') ?>"><button type="submit" class="btn btn-primary" onclick="return confirm('Setujui pengajuan ini? Data aset belum berubah sampai tombol Terapkan ditekan.');"><i class="ri ri-check-line me-1"></i>Setujui</button></form>
        <form method="post" action="<?= site_url('asset-management/changes/' . (int)$request['id'] . '/reject') ?>" class="d-flex flex-wrap align-items-end gap-2 flex-grow-1">
          <div class="flex-grow-1"><label class="form-label small mb-1">Alasan penolakan</label><input type="text" name="rejection_reason" class="form-control" required placeholder="Jelaskan data yang perlu diperbaiki"></div>
          <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Tolak pengajuan ini?');"><i class="ri ri-close-line me-1"></i>Tolak</button>
        </form>
      </div></div>
    <?php endif; ?>
    <?php if ($status === 'APPROVED' && !empty($can_edit)): ?>
      <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><span>Pengajuan sudah disetujui. Terapkan hanya setelah memastikan detail perubahan di atas benar.</span><form method="post" action="<?= site_url('asset-management/changes/' . (int)$request['id'] . '/post') ?>"><button type="submit" class="btn btn-success" onclick="return confirm('Terapkan perubahan ini ke data aset? Riwayat aset akan diperbarui.');"><i class="ri ri-save-line me-1"></i>Terapkan Perubahan</button></form></div>
    <?php endif; ?>
    <?php if ($status === 'PENDING' && (!empty($can_delete) || !empty($can_cancel_own))): ?>
      <form method="post" action="<?= site_url('asset-management/changes/' . (int)$request['id'] . '/cancel') ?>"><button type="submit" class="btn btn-outline-secondary" onclick="return confirm('Batalkan pengajuan perubahan ini?');"><i class="ri ri-close-circle-line me-1"></i>Batalkan Pengajuan</button></form>
    <?php endif; ?>
  </div>
</div>
