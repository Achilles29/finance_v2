<?php
$this->load->view('assets/_nav', ['asset_nav_active' => 'recon']);
$recon = $recon ?? [];
$lines = $lines ?? [];
$labels = $physical_status_labels ?? [];
$isDraft = ($recon['status'] ?? '') === 'DRAFT';
$lineCount = count($lines);
$checkedCount = 0;
$issueCount = 0;
foreach ($lines as $line) {
  $physical = strtoupper((string)($line['physical_status'] ?? 'NOT_CHECKED'));
  if ($physical !== 'NOT_CHECKED') $checkedCount++;
  if (in_array($physical, ['BROKEN', 'MISSING', 'NEED_REPAIR'], true)) $issueCount++;
}
$pct = $lineCount > 0 ? round(($checkedCount / $lineCount) * 100) : 0;
$statusBadge = static function ($status): string {
  $status = strtoupper((string)$status);
  return ['DRAFT' => 'warning', 'POSTED' => 'success', 'CANCELLED' => 'secondary'][$status] ?? 'secondary';
};
$expectedBadge = static function ($status): string {
  $status = strtoupper((string)$status);
  return ['ACTIVE' => 'success', 'BROKEN' => 'danger', 'REPAIR' => 'warning', 'LOST' => 'dark', 'RETIRED' => 'secondary', 'DISPOSED' => 'secondary'][$status] ?? 'secondary';
};
?>
<style>
.asset-page-title{display:flex;align-items:center;gap:.6rem}
.asset-title-icon{width:36px;height:36px;flex:0 0 36px;border-radius:8px;display:grid;place-items:center;background:#f3faf7;color:#18745c}
.asset-title-icon i{font-size:1.1rem;line-height:1}
.asset-panel{border:1px solid #e7d8ce;border-radius:8px;background:#fff;box-shadow:0 10px 24px rgba(35,24,18,.04)}
.asset-stat{border:1px solid #eadbd1;border-radius:8px;background:#fff;min-height:88px}
.asset-stat .label{font-size:.74rem;text-transform:uppercase;color:#8b6f61;letter-spacing:.02em}
.asset-stat .value{font-size:1.25rem;font-weight:800;color:#2f1f1a}
.asset-recon-photo{width:52px;height:52px;min-width:52px;border-radius:8px;object-fit:cover;background:#f8fafc;border:1px solid #e5e7eb}
.asset-recon-empty{width:52px;height:52px;min-width:52px;border-radius:8px;display:grid;place-items:center;background:#f8fafc;color:#94a3b8;border:1px dashed #cbd5e1}
.asset-recon-line-note{min-width:220px;resize:vertical}
.asset-table{table-layout:fixed}
.asset-table th{font-size:.76rem;text-transform:uppercase;color:#77665c;letter-spacing:.02em;white-space:nowrap}
.asset-table td{vertical-align:middle}
.asset-progress{height:8px;border-radius:99px;background:#eef2f7;overflow:hidden}
.asset-progress span{display:block;height:100%;background:#18745c}
@media (max-width: 991.98px){.asset-table{table-layout:auto}.asset-table td,.asset-table th{white-space:normal}.asset-recon-line-note{min-width:170px}}
</style>

<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
  <div>
    <h4 class="mb-1 asset-page-title"><span class="asset-title-icon"><i class="ri ri-calendar-check-line"></i></span><span><?= html_escape($recon['recon_no'] ?? '-') ?></span></h4>
    <div class="text-muted">Periode <?= html_escape($recon['period_month'] ?? '-') ?><?= !empty($recon['division_name']) ? ' / ' . html_escape($recon['division_name']) : ' / Semua divisi' ?></div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= site_url('asset-management/recon') ?>" class="btn btn-outline-secondary">Kembali</a>
    <?php if ($isDraft && !empty($can_cancel)): ?>
      <a href="<?= site_url('asset-management/recon/' . (int)$recon['id'] . '/cancel') ?>" class="btn btn-outline-danger" onclick="return confirm('Batalkan rekon ini?')"><i class="ri ri-close-circle-line me-1"></i>Cancel</a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Status</div><div class="value"><span class="badge bg-<?= $statusBadge($recon['status'] ?? '') ?>"><?= html_escape($recon['status'] ?? '-') ?></span></div><div class="small text-muted">Tahap rekon</div></div></div>
  <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Progress</div><div class="value"><?= $pct ?>%</div><div class="small text-muted"><?= $checkedCount ?>/<?= $lineCount ?> unit dicek</div></div></div>
  <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Issue fisik</div><div class="value text-danger"><?= number_format($issueCount, 0, ',', '.') ?></div><div class="small text-muted">Butuh bukti/catatan</div></div></div>
  <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Generated</div><div class="value" style="font-size:1rem"><?= html_escape(substr((string)($recon['generated_at'] ?? $recon['created_at'] ?? '-'), 0, 16)) ?></div><div class="small text-muted"><?= html_escape($recon['division_name'] ?? 'Semua divisi') ?></div></div></div>
</div>

<div class="asset-panel mb-3 p-3">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div class="flex-grow-1">
      <div class="fw-semibold">Checklist fisik aset</div>
      <div class="small text-muted">Aset rusak, hilang, atau butuh perbaikan wajib memiliki catatan dan bukti foto sebelum posting.</div>
    </div>
    <div style="min-width:220px">
      <div class="d-flex justify-content-between small"><span><?= $checkedCount ?>/<?= $lineCount ?></span><span><?= $pct ?>%</span></div>
      <div class="asset-progress"><span style="width:<?= $pct ?>%"></span></div>
    </div>
  </div>
</div>

<form method="post" action="<?= site_url('asset-management/recon/' . (int)$recon['id'] . '/save') ?>" enctype="multipart/form-data">
  <div class="asset-panel">
    <div class="table-responsive">
      <table class="table table-hover asset-table mb-0">
        <colgroup>
          <col style="width:32%">
          <col style="width:10%">
          <col style="width:17%">
          <col style="width:9%">
          <col style="width:20%">
          <col style="width:12%">
        </colgroup>
        <thead class="table-light">
          <tr>
            <th>Aset</th>
            <th>Ekspektasi</th>
            <th>Hasil Fisik</th>
            <th>Kondisi</th>
            <th>Catatan</th>
            <th>Bukti</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($lines)): ?><tr><td colspan="6" class="text-center text-muted py-5">Snapshot rekon belum memiliki aset.</td></tr><?php endif; ?>
          <?php foreach ($lines as $line): ?>
            <?php $photo = trim((string)($line['photo_path'] ?? '')); $lineId = (int)$line['id']; ?>
            <tr>
              <td>
                <div class="d-flex gap-2 align-items-center">
                  <?php if ($photo !== ''): ?><img class="asset-recon-photo" src="<?= html_escape(base_url($photo)) ?>" alt="<?= html_escape($line['asset_name'] ?? '') ?>"><?php else: ?><span class="asset-recon-empty"><i class="ri ri-file-text-line"></i></span><?php endif; ?>
                  <div class="min-w-0">
                    <div class="fw-semibold text-truncate"><?= html_escape($line['asset_name'] ?? '-') ?></div>
                    <div class="small text-muted text-truncate"><?= html_escape($line['asset_code'] ?? '-') ?> | <?= html_escape($line['category_name'] ?? '-') ?></div>
                    <div class="small text-muted text-truncate"><?= html_escape($line['division_name'] ?? '-') ?><?= !empty($line['custodian_name']) ? ' | PIC ' . html_escape($line['custodian_name']) : '' ?></div>
                  </div>
                </div>
              </td>
              <td><span class="badge bg-<?= $expectedBadge($line['expected_status'] ?? '') ?>"><?= html_escape($line['expected_status'] ?? '-') ?></span></td>
              <td>
                <select name="physical_status[<?= $lineId ?>]" class="form-select form-select-sm" <?= !$isDraft || empty($can_edit) ? 'disabled' : '' ?>>
                  <?php foreach ($labels as $key => $label): ?>
                    <option value="<?= html_escape($key) ?>" <?= ($line['physical_status'] ?? 'NOT_CHECKED') === $key ? 'selected' : '' ?>><?= html_escape($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <input type="number" name="condition_score[<?= $lineId ?>]" class="form-control form-control-sm" min="0" max="100" value="<?= html_escape((string)($line['condition_score'] ?? $line['asset_condition_score'] ?? 100)) ?>" <?= !$isDraft || empty($can_edit) ? 'disabled' : '' ?>>
              </td>
              <td>
                <textarea name="notes[<?= $lineId ?>]" class="form-control form-control-sm asset-recon-line-note" rows="2" <?= !$isDraft || empty($can_edit) ? 'disabled' : '' ?>><?= html_escape($line['notes'] ?? '') ?></textarea>
              </td>
              <td>
                <?php if (!empty($line['evidence_path'])): ?>
                  <a class="btn btn-sm btn-outline-secondary d-block mb-1" href="<?= html_escape(base_url($line['evidence_path'])) ?>" target="_blank" rel="noopener"><i class="ri ri-file-text-line me-1"></i>Bukti</a>
                <?php endif; ?>
                <input type="file" name="evidence_<?= $lineId ?>" class="form-control form-control-sm" accept="image/*" <?= !$isDraft || empty($can_edit) ? 'disabled' : '' ?>>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($isDraft && !empty($can_edit)): ?>
      <div class="p-3 border-top d-flex flex-wrap justify-content-end gap-2">
        <button type="submit" class="btn btn-outline-primary"><i class="ri ri-save-line me-1"></i>Simpan Checklist</button>
        <?php if (!empty($can_post)): ?>
          <button type="submit" formaction="<?= site_url('asset-management/recon/' . (int)$recon['id'] . '/post') ?>" class="btn btn-primary" onclick="return confirm('Posting rekon akan memperbarui status aset. Lanjutkan?')"><i class="ri ri-arrow-up-circle-line me-1"></i>Posting Rekon</button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</form>
