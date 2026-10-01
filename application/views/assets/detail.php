<?php
$this->load->view('assets/_nav', ['asset_nav_active' => 'items']);
$asset = $asset ?? [];
$events = $events ?? [];
$masterLockReady = !empty($master_lock_ready);
$isMasterLocked = !empty($asset['is_master_locked']);
$fmtMoney = static function ($value): string { return 'Rp ' . number_format((float)$value, 0, ',', '.'); };
$photo = trim((string)($asset['photo_path'] ?? ''));
$statusClass = [
  'ACTIVE' => 'success',
  'BROKEN' => 'danger',
  'REPAIR' => 'warning',
  'LOST' => 'dark',
  'RETIRED' => 'secondary',
  'DISPOSED' => 'secondary',
][strtoupper((string)($asset['status'] ?? ''))] ?? 'secondary';
?>
<style>
.asset-page-title{display:flex;align-items:center;gap:.6rem}
.asset-title-icon{width:36px;height:36px;flex:0 0 36px;border-radius:8px;display:grid;place-items:center;background:#f3faf7;color:#18745c}
.asset-title-icon i{font-size:1.1rem;line-height:1}
.asset-panel{border:1px solid #e7d8ce;border-radius:8px;background:#fff;box-shadow:0 10px 24px rgba(35,24,18,.04)}
.asset-photo-frame{width:100%;aspect-ratio:1/1;border-radius:8px;background:#f8fafc;border:1px solid #e5e7eb;display:grid;place-items:center;overflow:hidden}
.asset-detail-photo{width:100%;height:100%;object-fit:cover}
.asset-kv{display:grid;grid-template-columns:170px minmax(0,1fr);gap:.55rem .9rem}
.asset-kv .k{color:#7b6a60}
.asset-stat{border:1px solid #eadbd1;border-radius:8px;background:#fff;min-height:88px}
.asset-stat .label{font-size:.74rem;text-transform:uppercase;color:#8b6f61;letter-spacing:.02em}
.asset-stat .value{font-size:1.25rem;font-weight:800;color:#2f1f1a}
.asset-progress{height:8px;border-radius:99px;background:#eef2f7;overflow:hidden}
.asset-progress span{display:block;height:100%;background:#18745c}
.asset-table{table-layout:fixed}
.asset-table th{font-size:.76rem;text-transform:uppercase;color:#77665c;letter-spacing:.02em;white-space:nowrap}
.asset-table td{vertical-align:middle}
@media (max-width: 991.98px){.asset-table{table-layout:auto}.asset-table th,.asset-table td{white-space:normal}}
@media (max-width: 767.98px){.asset-kv{grid-template-columns:1fr}.asset-kv .v{font-weight:600}}
</style>

<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
  <div>
    <h4 class="mb-1 asset-page-title"><span class="asset-title-icon"><i class="ri ri-archive-2-line"></i></span><span><?= html_escape($asset['asset_name'] ?? '-') ?></span></h4>
    <div class="text-muted"><?= html_escape($asset['asset_code'] ?? '-') ?></div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if (!empty($asset['group_key'])): ?><a href="<?= site_url('asset-management/group/' . rawurlencode((string)$asset['group_key'])) ?>" class="btn btn-outline-secondary"><i class="ri ri-stack-line me-1"></i>Grup</a><?php endif; ?>
    <?php if (!empty($can_damage)): ?><a href="<?= site_url('asset-management/damage/' . (int)$asset['id']) ?>" class="btn btn-outline-danger"><i class="ri ri-alert-line me-1"></i>Lapor Rusak</a><?php endif; ?>
    <?php if ($isMasterLocked && !empty($can_change_create)): ?><a href="<?= site_url('asset-management/changes/create/' . (int)$asset['id']) ?>" class="btn btn-primary"><i class="ri ri-file-edit-line me-1"></i>Ajukan Perubahan</a><?php endif; ?>
    <?php if (!$isMasterLocked && !empty($can_edit)): ?><a href="<?= site_url('asset-management/edit/' . (int)$asset['id']) ?>" class="btn btn-primary"><i class="ri ri-edit-line me-1"></i>Edit Data Awal</a><?php endif; ?>
    <?php if (!$isMasterLocked && !empty($can_delete)): ?>
      <form method="post" action="<?= site_url('asset-management/delete/' . (int)$asset['id']) ?>" onsubmit="return confirm('Hapus unit aset ini? Aset hanya dapat dihapus bila masih pendataan dan belum memiliki jejak operasional.');"><button type="submit" class="btn btn-outline-danger"><i class="ri ri-delete-bin-line me-1"></i>Hapus Unit</button></form>
    <?php endif; ?>
    <?php if (!$isMasterLocked && !empty($can_lock) && $masterLockReady): ?>
      <form method="post" action="<?= site_url('asset-management/lock/' . (int)$asset['id']) ?>" onsubmit="return confirm('Kunci data awal aset ini? Perubahan berikutnya wajib melalui pengajuan perubahan data aset.');"><button type="submit" class="btn btn-outline-success"><i class="ri ri-lock-line me-1"></i>Kunci Data</button></form>
    <?php endif; ?>
    <a href="<?= site_url('asset-management') ?>" class="btn btn-outline-secondary">Kembali</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="asset-panel p-3 h-100">
      <div class="asset-photo-frame">
        <?php if ($photo !== ''): ?>
          <img class="asset-detail-photo" src="<?= html_escape(base_url($photo)) ?>" alt="<?= html_escape($asset['asset_name'] ?? '') ?>">
        <?php else: ?>
          <div class="text-muted text-center"><i class="ri ri-file-text-line d-block mb-1" style="font-size:2rem"></i>Belum ada foto</div>
        <?php endif; ?>
      </div>
      <div class="mt-3 d-flex justify-content-between align-items-center">
        <span class="badge bg-<?= $statusClass ?>"><?= html_escape($asset['status_label'] ?? '-') ?></span>
        <span class="fw-semibold">Kondisi <?= (int)($asset['condition_score'] ?? 0) ?>%</span>
      </div>
      <div class="asset-progress mt-2"><span style="width:<?= max(0, min(100, (int)($asset['condition_score'] ?? 0))) ?>%"></span></div>
      <?php if ($masterLockReady): ?>
        <div class="mt-3 p-2 rounded border <?= $isMasterLocked ? 'border-success bg-light' : 'border-secondary bg-light' ?>">
          <div class="fw-semibold"><i class="ri <?= $isMasterLocked ? 'ri-lock-line' : 'ri-lock-unlock-line' ?> me-1"></i>Data awal: <?= html_escape($asset['master_lock_status_label'] ?? '-') ?></div>
          <div class="small text-muted"><?= $isMasterLocked ? 'Perubahan identitas dan nilai wajib melalui pengajuan agar riwayatnya tercatat.' : 'Pendataan masih dapat dilengkapi langsung sebelum dikunci.' ?></div>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="row g-3 mb-3">
      <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Nilai buku</div><div class="value"><?= $fmtMoney($asset['book_value'] ?? 0) ?></div><div class="small text-muted">Susut <?= number_format((float)($asset['depreciation_percent'] ?? 0), 1, ',', '.') ?>%</div></div></div>
      <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Harga beli</div><div class="value"><?= $fmtMoney($asset['acquisition_cost'] ?? 0) ?></div><div class="small text-muted"><?= html_escape($asset['acquisition_date'] ?? '-') ?></div></div></div>
      <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Umur manfaat</div><div class="value"><?= (int)($asset['useful_life_months'] ?? 0) ?></div><div class="small text-muted">bulan</div></div></div>
      <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Metode</div><div class="value" style="font-size:1rem"><?= html_escape($asset['depreciation_method'] ?? '-') ?></div><div class="small text-muted"><?= html_escape($asset['depreciation_start_month'] ?? '-') ?></div></div></div>
    </div>

    <div class="asset-panel mb-3">
      <div class="p-3 border-bottom fw-semibold">Ringkasan unit</div>
      <div class="p-3 asset-kv">
        <div class="k">Kategori</div><div class="v"><?= html_escape($asset['category_name'] ?? '-') ?></div>
        <div class="k">Brand / model</div><div class="v"><?= html_escape(trim((string)($asset['brand'] ?? '') . ' ' . (string)($asset['model_name'] ?? '')) ?: '-') ?></div>
        <div class="k">Serial</div><div class="v"><?= html_escape($asset['serial_no'] ?? '-') ?></div>
        <div class="k">Batch</div><div class="v"><?= html_escape($asset['batch_no'] ?? '-') ?></div>
        <div class="k">Divisi</div><div class="v"><?= html_escape($asset['division_name'] ?? '-') ?></div>
        <div class="k">Outlet / lokasi</div><div class="v"><?= html_escape(trim((string)($asset['outlet_name'] ?? '') . ' ' . (string)($asset['current_location'] ?? '')) ?: '-') ?></div>
        <div class="k">PIC</div><div class="v"><?= html_escape($asset['custodian_name'] ?? '-') ?></div>
        <?php if ($masterLockReady): ?><div class="k">Kunci data awal</div><div class="v"><?= html_escape($asset['master_lock_status_label'] ?? '-') ?><?= !empty($asset['master_locked_at']) ? ' | ' . html_escape($asset['master_locked_at']) : '' ?></div><?php endif; ?>
        <div class="k">Catatan</div><div class="v"><?= nl2br(html_escape($asset['notes'] ?? '-')) ?></div>
      </div>
    </div>

    <div class="asset-panel">
      <div class="p-3 border-bottom fw-semibold">Audit trail</div>
      <div class="table-responsive">
        <table class="table table-hover asset-table mb-0">
          <colgroup>
            <col style="width:13%">
            <col style="width:12%">
            <col style="width:17%">
            <col style="width:42%">
            <col style="width:16%">
          </colgroup>
          <thead class="table-light"><tr><th>Tanggal</th><th>Event</th><th>Status</th><th>Catatan</th><th>Bukti</th></tr></thead>
          <tbody>
            <?php if (empty($events)): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada event.</td></tr><?php endif; ?>
            <?php foreach ($events as $event): ?>
              <tr>
                <td><?= html_escape($event['event_date'] ?? '-') ?></td>
                <td><span class="badge bg-label-secondary"><?= html_escape($event['event_type'] ?? '-') ?></span></td>
                <td><?= html_escape(($event['from_status'] ?? '-') . ' -> ' . ($event['to_status'] ?? '-')) ?></td>
                <td><?= nl2br(html_escape($event['reason'] ?? '-')) ?></td>
                <td>
                  <?php if (!empty($event['evidence_path'])): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= html_escape(base_url($event['evidence_path'])) ?>" target="_blank" rel="noopener"><i class="ri ri-file-text-line me-1"></i>Lihat</a>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
