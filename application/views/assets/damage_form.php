<?php
$this->load->view('assets/_nav', ['asset_nav_active' => 'damage']);
$asset = $asset ?? null;
$report = $report ?? null;
$isEdit = !empty($is_edit) && is_array($report);
$hasAsset = is_array($asset) && !empty($asset['id']);
$fmtMoney = static function ($value): string { return 'Rp ' . number_format((float)$value, 0, ',', '.'); };
$assetMeta = $hasAsset
  ? trim((string)($asset['category_name'] ?? '') . ' | ' . (string)($asset['division_name'] ?? '') . ' | ' . (string)($asset['current_location'] ?? ''), ' |')
  : '';
$eventDate = $isEdit ? (string)($report['event_date'] ?? date('Y-m-d')) : date('Y-m-d');
$toStatus = $isEdit ? strtoupper((string)($report['event_to_status'] ?? 'BROKEN')) : 'BROKEN';
$conditionAfter = $isEdit ? (int)($report['condition_score_after'] ?? 0) : 0;
$lossAmount = $isEdit ? (float)($report['amount'] ?? 0) : ($hasAsset ? (float)($asset['book_value'] ?? 0) : 0);
$reasonText = $isEdit ? (string)($report['reason'] ?? '') : '';
$formAction = $isEdit
  ? site_url('asset-management/damage/update/' . (int)$report['event_id'])
  : site_url('asset-management/damage/store');
?>
<style>
.damage-lookup{position:relative}
.damage-lookup-results{position:absolute;z-index:20;left:0;right:0;top:100%;margin-top:4px;background:#fff;border:1px solid #d8c9bd;border-radius:8px;box-shadow:0 14px 34px rgba(35,24,18,.16);max-height:330px;overflow:auto;display:none}
.damage-lookup-results.is-open{display:block}
.damage-lookup-item{width:100%;border:0;background:#fff;text-align:left;padding:.75rem .9rem;border-bottom:1px solid #f0e4db}
.damage-lookup-item:last-child{border-bottom:0}
.damage-lookup-item:hover{background:#fff7f2}
.damage-selected{border:1px solid #e7d8ce;border-radius:8px;background:#fff;padding:1rem}
.damage-selected.is-empty{border-style:dashed;color:#8a817b;background:#fafafa}
.damage-selected-thumb{width:58px;height:58px;border-radius:8px;object-fit:cover;background:#f8fafc;border:1px solid #e5e7eb}
.damage-selected-empty{width:58px;height:58px;border-radius:8px;display:grid;place-items:center;background:#f8fafc;color:#94a3b8;border:1px dashed #cbd5e1}
.damage-side-photo{width:100%;max-height:260px;border-radius:8px;object-fit:cover;background:#f8fafc;border:1px solid #e5e7eb}
</style>

<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
  <div>
    <h4 class="mb-1"><?= $isEdit ? 'Edit Laporan Kerusakan Aset' : ($hasAsset ? 'Lapor Aset Rusak / Hilang' : 'Tambah Laporan Kerusakan Aset') ?></h4>
    <div class="text-muted"><?= $hasAsset ? html_escape(($asset['asset_code'] ?? '-') . ' - ' . ($asset['asset_name'] ?? '-')) : 'Cari aset dulu, lalu isi kronologi dan upload bukti foto.' ?></div>
  </div>
  <a href="<?= $isEdit ? site_url('asset-management/damage') : ($hasAsset ? site_url('asset-management/detail/' . (int)$asset['id']) : site_url('asset-management/damage')) ?>" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header fw-semibold">Aset yang dilaporkan</div>
      <div class="card-body">
        <div class="damage-lookup mb-3">
          <label class="form-label">Cari aset</label>
          <input type="search" id="damageAssetSearch" class="form-control" placeholder="Ketik kode, nama, serial, atau lokasi" autocomplete="off" value="<?= $hasAsset ? html_escape(($asset['asset_code'] ?? '') . ' - ' . ($asset['asset_name'] ?? '')) : '' ?>" <?= $isEdit ? 'readonly' : '' ?>>
          <div id="damageAssetResults" class="damage-lookup-results"></div>
          <div class="form-text"><?= $isEdit ? 'Aset dikunci saat edit laporan agar audit trail tetap konsisten.' : 'Minimal 2 karakter. Hasil mengambil aset aktif maupun yang sudah bermasalah.' ?></div>
        </div>

        <div id="damageSelectedAsset" class="damage-selected <?= $hasAsset ? '' : 'is-empty' ?>">
          <?php if ($hasAsset): ?>
            <div class="d-flex gap-2 align-items-center">
              <?php if (!empty($asset['photo_path'])): ?>
                <img class="damage-selected-thumb" src="<?= html_escape(base_url($asset['photo_path'])) ?>" alt="<?= html_escape($asset['asset_name'] ?? '') ?>">
              <?php else: ?>
                <span class="damage-selected-empty"><i class="ri ri-file-text-line"></i></span>
              <?php endif; ?>
              <div>
                <div class="fw-semibold"><?= html_escape($asset['asset_name'] ?? '-') ?></div>
                <div class="small text-muted"><?= html_escape($asset['asset_code'] ?? '-') ?></div>
                <div class="small text-muted"><?= html_escape($assetMeta ?: '-') ?></div>
              </div>
            </div>
            <hr>
            <div class="mb-2"><span class="text-muted">Nilai buku estimasi</span><div class="fw-semibold"><?= $fmtMoney($asset['book_value'] ?? 0) ?></div></div>
            <div><span class="text-muted">Kondisi saat ini</span><div class="fw-semibold"><?= (int)($asset['condition_score'] ?? 0) ?>%</div></div>
          <?php else: ?>
            <div class="text-center py-4">
              <i class="ri ri-search-line d-block mb-2" style="font-size:2rem"></i>
              <div class="fw-semibold">Belum ada aset dipilih</div>
              <div class="small">Gunakan kolom pencarian untuk memilih barang yang akan dilaporkan.</div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <form class="card" method="post" action="<?= $formAction ?>" enctype="multipart/form-data">
      <input type="hidden" name="asset_id" id="damageAssetId" value="<?= $hasAsset ? (int)$asset['id'] : 0 ?>">
      <div class="card-header fw-semibold">Data kejadian</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Tanggal kejadian</label>
            <input type="date" name="event_date" class="form-control" value="<?= html_escape($eventDate) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status baru</label>
            <select name="to_status" id="damageStatus" class="form-select" required>
              <option value="BROKEN" <?= $toStatus === 'BROKEN' ? 'selected' : '' ?>>Rusak</option>
              <option value="REPAIR" <?= $toStatus === 'REPAIR' ? 'selected' : '' ?>>Perlu perbaikan</option>
              <option value="LOST" <?= $toStatus === 'LOST' ? 'selected' : '' ?>>Hilang</option>
              <option value="RETIRED" <?= $toStatus === 'RETIRED' ? 'selected' : '' ?>>Pensiun</option>
              <option value="DISPOSED" <?= $toStatus === 'DISPOSED' ? 'selected' : '' ?>>Dibuang</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Kondisi setelah kejadian</label>
            <input type="number" name="condition_score_after" class="form-control" min="0" max="100" value="<?= (int)$conditionAfter ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Estimasi kerugian</label>
            <input type="number" name="estimated_loss_amount" id="damageLossAmount" class="form-control" min="0" step="0.01" value="<?= html_escape((string)$lossAmount) ?>">
            <div class="form-text">Default memakai nilai buku aset yang dipilih.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Bukti foto</label>
            <input type="file" name="evidence_file" class="form-control" accept="image/*" <?= $isEdit ? '' : 'required' ?>>
            <?php if ($isEdit && !empty($report['evidence_path'])): ?>
              <div class="form-text">Kosongkan jika tidak diganti. <a href="<?= html_escape(base_url($report['evidence_path'])) ?>" target="_blank" rel="noopener">Lihat bukti saat ini</a></div>
            <?php endif; ?>
          </div>
          <div class="col-12">
            <label class="form-label">Alasan / kronologi</label>
            <textarea name="reason" class="form-control" rows="5" required placeholder="Contoh: gelas pecah saat service, ditemukan retak, atau aset tidak ditemukan saat closing."><?= html_escape($reasonText) ?></textarea>
          </div>
        </div>
      </div>
      <div class="card-footer d-flex justify-content-end gap-2">
        <a href="<?= $isEdit ? site_url('asset-management/damage') : ($hasAsset ? site_url('asset-management/detail/' . (int)$asset['id']) : site_url('asset-management/damage')) ?>" class="btn btn-outline-secondary">Batal</a>
            <button type="submit" id="damageSubmit" class="btn btn-danger" <?= $hasAsset ? '' : 'disabled' ?>><i class="ri ri-save-line me-1"></i><?= $isEdit ? 'Update Laporan' : 'Simpan Laporan' ?></button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  var searchInput = document.getElementById('damageAssetSearch');
  var resultsBox = document.getElementById('damageAssetResults');
  var selectedBox = document.getElementById('damageSelectedAsset');
  var assetIdInput = document.getElementById('damageAssetId');
  var lossInput = document.getElementById('damageLossAmount');
  var submitButton = document.getElementById('damageSubmit');
  var endpoint = <?= json_encode(site_url('asset-management/damage/asset-search'), JSON_UNESCAPED_SLASHES) ?>;
  var baseUrl = <?= json_encode(rtrim(base_url(), '/') . '/', JSON_UNESCAPED_SLASHES) ?>;
  var timer = null;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function(ch){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch];
    });
  }

  function money(value) {
    var n = Number(value || 0);
    try {
      return 'Rp ' + n.toLocaleString('id-ID', {maximumFractionDigits:0});
    } catch (e) {
      return 'Rp ' + Math.round(n);
    }
  }

  function closeResults() {
    resultsBox.classList.remove('is-open');
  }

  function renderResults(rows) {
    if (!rows.length) {
      resultsBox.innerHTML = '<div class="damage-lookup-item text-muted">Tidak ada aset yang cocok.</div>';
      resultsBox.classList.add('is-open');
      return;
    }
    resultsBox.innerHTML = rows.map(function(row){
      return '<button type="button" class="damage-lookup-item" data-pick-asset data-row="' + esc(JSON.stringify(row)) + '">' +
        '<div class="fw-semibold">' + esc(row.label || '-') + '</div>' +
        '<div class="small text-muted">' + esc(row.meta || '-') + '</div>' +
        '<div class="small"><span class="badge bg-label-secondary">' + esc(row.status_label || row.status || '-') + '</span> <span class="text-muted">Kondisi ' + esc(row.condition_score || 0) + '%</span></div>' +
      '</button>';
    }).join('');
    resultsBox.classList.add('is-open');
  }

  function renderSelected(row) {
    var photo = row.photo_path
      ? '<img class="damage-selected-thumb" src="' + esc(baseUrl + row.photo_path) + '" alt="' + esc(row.asset_name || '') + '">'
      : '<span class="damage-selected-empty"><i class="ri ri-file-text-line"></i></span>';
    selectedBox.classList.remove('is-empty');
    selectedBox.innerHTML =
      '<div class="d-flex gap-2 align-items-center">' +
        photo +
        '<div>' +
          '<div class="fw-semibold">' + esc(row.asset_name || '-') + '</div>' +
          '<div class="small text-muted">' + esc(row.asset_code || '-') + '</div>' +
          '<div class="small text-muted">' + esc(row.meta || '-') + '</div>' +
        '</div>' +
      '</div>' +
      '<hr>' +
      '<div class="mb-2"><span class="text-muted">Nilai buku estimasi</span><div class="fw-semibold">' + money(row.book_value || 0) + '</div></div>' +
      '<div><span class="text-muted">Kondisi saat ini</span><div class="fw-semibold">' + esc(row.condition_score || 0) + '%</div></div>';
  }

  function searchAsset() {
    var q = (searchInput.value || '').trim();
    if (q.length < 2) {
      closeResults();
      return;
    }
    resultsBox.innerHTML = '<div class="damage-lookup-item text-muted">Mencari...</div>';
    resultsBox.classList.add('is-open');
    fetch(endpoint + '?q=' + encodeURIComponent(q), {
      headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
      .then(function(resp){ return resp.json(); })
      .then(function(json){ renderResults((json && json.rows) || []); })
      .catch(function(){ renderResults([]); });
  }

  if (searchInput) {
    searchInput.addEventListener('input', function(){
      clearTimeout(timer);
      timer = setTimeout(searchAsset, 260);
    });
  }

  if (resultsBox) {
    resultsBox.addEventListener('click', function(event){
      var btn = event.target.closest('[data-pick-asset]');
      if (!btn) return;
      var row = JSON.parse(btn.getAttribute('data-row') || '{}');
      assetIdInput.value = row.id || 0;
      searchInput.value = row.label || '';
      lossInput.value = row.book_value || 0;
      submitButton.disabled = !(row.id > 0);
      renderSelected(row);
      closeResults();
    });
  }

  document.addEventListener('click', function(event){
    if (!event.target.closest('.damage-lookup')) {
      closeResults();
    }
  });
})();
</script>
