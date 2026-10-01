<?php
$this->load->view('assets/_nav', ['asset_nav_active' => $asset_nav_active ?? '']);
$type = strtoupper((string)($type ?? ''));
$config = $config ?? [];
$asset = $asset ?? null;
$hasAsset = is_array($asset) && !empty($asset['id']);
$action = site_url(($config['url'] ?? 'asset-management') . '/store');
$backUrl = site_url($config['url'] ?? 'asset-management');
$assetMeta = $hasAsset
  ? trim((string)($asset['category_name'] ?? '') . ' | ' . (string)($asset['division_name'] ?? '') . ' | ' . (string)($asset['current_location'] ?? ''), ' |')
  : '';
$fmtMoney = static function ($value): string {
  return 'Rp ' . number_format((float)$value, 0, ',', '.');
};
?>
<style>
.asset-work-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
.asset-work-form-grid .grid-span{grid-column:1/-1}
.asset-lookup{position:relative}
.asset-lookup-results{position:absolute;z-index:20;left:0;right:0;top:100%;margin-top:4px;background:#fff;border:1px solid #d8c9bd;border-radius:8px;box-shadow:0 14px 34px rgba(35,24,18,.16);max-height:330px;overflow:auto;display:none}
.asset-lookup-results.is-open{display:block}
.asset-lookup-item{width:100%;border:0;background:#fff;text-align:left;padding:.75rem .9rem;border-bottom:1px solid #f0e4db}
.asset-lookup-item:last-child{border-bottom:0}
.asset-lookup-item:hover{background:#fff7f2}
.asset-selected{border:1px solid #e7d8ce;border-radius:8px;background:#fff;padding:1rem}
.asset-selected.is-empty{border-style:dashed;color:#8a817b;background:#fafafa}
.asset-selected-thumb{width:64px;height:64px;border-radius:8px;object-fit:cover;background:#f8fafc;border:1px solid #e5e7eb}
.asset-selected-empty{width:64px;height:64px;border-radius:8px;display:grid;place-items:center;background:#f8fafc;color:#94a3b8;border:1px dashed #cbd5e1}
.asset-help-box{border:1px solid #e7d8ce;border-radius:8px;background:#fffdfb}
@media (max-width: 767.98px){.asset-work-form-grid{grid-template-columns:1fr}}
</style>

<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
  <div>
    <h4 class="mb-1">Tambah <?= html_escape($config['short_label'] ?? 'Workflow') ?></h4>
    <div class="text-muted">
      <?php if ($type === 'TRANSFER'): ?>Ajukan perpindahan aset. Data aset baru berubah setelah workflow disetujui dan diposting.
      <?php elseif ($type === 'HANDOVER'): ?>Pindahkan tanggung jawab aset dari PIC lama ke PIC baru.
      <?php elseif ($type === 'MAINTENANCE'): ?>Buat jadwal maintenance dan lengkapi biaya realisasi saat pekerjaan selesai.
      <?php else: ?>Ajukan penghapusan/pensiun aset dengan alasan dan bukti visual.
      <?php endif; ?>
    </div>
  </div>
  <a href="<?= $backUrl ?>" class="btn btn-outline-secondary"><i class="ri ri-arrow-left-line me-1"></i>Kembali</a>
</div>

<form method="post" action="<?= $action ?>" enctype="multipart/form-data" autocomplete="off">
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header fw-semibold">Aset</div>
        <div class="card-body">
          <div class="asset-lookup mb-3">
            <label class="form-label">Cari aset</label>
            <input type="search" id="workflowAssetSearch" class="form-control" placeholder="Kode, nama, serial, lokasi, kategori" autocomplete="off" value="<?= $hasAsset ? html_escape(($asset['asset_code'] ?? '') . ' - ' . ($asset['asset_name'] ?? '')) : '' ?>">
            <div id="workflowAssetResults" class="asset-lookup-results"></div>
            <div class="form-text">Minimal 2 karakter. Aset dipilih per unit fisik.</div>
          </div>
          <input type="hidden" name="asset_id" id="workflowAssetId" value="<?= $hasAsset ? (int)$asset['id'] : 0 ?>">
          <div id="workflowSelectedAsset" class="asset-selected <?= $hasAsset ? '' : 'is-empty' ?>">
            <?php if ($hasAsset): ?>
              <div class="d-flex gap-2 align-items-center">
                <?php if (!empty($asset['photo_path'])): ?>
                  <img class="asset-selected-thumb" src="<?= html_escape(base_url($asset['photo_path'])) ?>" alt="<?= html_escape($asset['asset_name'] ?? '') ?>">
                <?php else: ?>
                  <span class="asset-selected-empty"><i class="ri ri-file-text-line"></i></span>
                <?php endif; ?>
                <div>
                  <div class="fw-semibold"><?= html_escape($asset['asset_name'] ?? '-') ?></div>
                  <div class="small text-muted"><?= html_escape($asset['asset_code'] ?? '-') ?></div>
                  <div class="small text-muted"><?= html_escape($assetMeta ?: '-') ?></div>
                </div>
              </div>
              <hr>
              <div class="mb-2"><span class="text-muted">Status</span><div class="fw-semibold"><?= html_escape($asset['status_label'] ?? ($asset['status'] ?? '-')) ?></div></div>
              <div><span class="text-muted">Nilai buku</span><div class="fw-semibold"><?= $fmtMoney($asset['book_value'] ?? 0) ?></div></div>
            <?php else: ?>
              <div class="text-center py-4">
                <i class="ri ri-search-line d-block mb-2" style="font-size:2rem"></i>
                <div class="fw-semibold">Belum ada aset dipilih</div>
                <div class="small">Cari aset dulu untuk membuka tombol simpan.</div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header fw-semibold">Data workflow</div>
        <div class="card-body">
          <div class="asset-work-form-grid">
            <div>
              <label class="form-label">Tanggal</label>
              <input type="date" name="workflow_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <?php if ($type === 'MAINTENANCE'): ?>
              <div>
                <label class="form-label">Target selesai</label>
                <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d') ?>">
              </div>
              <div>
                <label class="form-label">Jenis maintenance</label>
                <select name="maintenance_type" class="form-select">
                  <option value="Preventive">Preventive</option>
                  <option value="Corrective">Corrective</option>
                  <option value="Kalibrasi">Kalibrasi</option>
                  <option value="Cleaning besar">Cleaning besar</option>
                </select>
              </div>
              <div>
                <label class="form-label">Prioritas</label>
                <select name="priority" class="form-select">
                  <?php foreach (($priority_labels ?? []) as $key => $label): ?>
                    <option value="<?= html_escape($key) ?>" <?= $key === 'NORMAL' ? 'selected' : '' ?>><?= html_escape($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="form-label">Vendor / teknisi</label>
                <input type="text" name="vendor_name" class="form-control" placeholder="Kosongkan jika internal">
              </div>
              <div>
                <label class="form-label">Estimasi biaya</label>
                <input type="number" name="estimated_cost" class="form-control" min="0" step="0.01" value="0">
              </div>
            <?php elseif ($type === 'TRANSFER'): ?>
              <div>
                <label class="form-label">Divisi tujuan</label>
                <select name="to_division_id" class="form-select">
                  <option value="0">Tidak berubah</option>
                  <?php foreach (($divisions ?? []) as $div): ?>
                    <option value="<?= (int)$div['id'] ?>"><?= html_escape($div['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="form-label">Outlet tujuan</label>
                <select name="to_outlet_id" class="form-select">
                  <option value="0">Tidak berubah</option>
                  <?php foreach (($outlets ?? []) as $outlet): ?>
                    <option value="<?= (int)$outlet['id'] ?>"><?= html_escape($outlet['outlet_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="form-label">Lokasi detail tujuan</label>
                <input type="text" name="to_location" class="form-control" placeholder="Contoh: Rak bar kiri outlet baru">
              </div>
              <div>
                <label class="form-label">PIC tujuan</label>
                <select name="to_employee_id" class="form-select">
                  <option value="0">Tidak berubah</option>
                  <?php foreach (($employees ?? []) as $emp): ?>
                    <option value="<?= (int)$emp['id'] ?>"><?= html_escape($emp['employee_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php elseif ($type === 'HANDOVER'): ?>
              <div>
                <label class="form-label">PIC penerima</label>
                <select name="to_employee_id" class="form-select" required>
                  <option value="0">Pilih penerima</option>
                  <?php foreach (($employees ?? []) as $emp): ?>
                    <option value="<?= (int)$emp['id'] ?>"><?= html_escape($emp['employee_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php elseif ($type === 'DISPOSAL'): ?>
              <div>
                <label class="form-label">Jenis disposal</label>
                <select name="disposal_type" class="form-select">
                  <option value="RETIRED">Pensiun / tidak dipakai</option>
                  <option value="DISPOSED">Dibuang</option>
                  <option value="SOLD">Dijual</option>
                  <option value="DONATED">Donasi</option>
                </select>
              </div>
              <div>
                <label class="form-label">Nilai realisasi</label>
                <input type="number" name="disposal_value" class="form-control" min="0" step="0.01" value="0">
              </div>
              <div>
                <label class="form-label">Biaya disposal</label>
                <input type="number" name="estimated_cost" class="form-control" min="0" step="0.01" value="0">
              </div>
            <?php endif; ?>
            <div class="<?= in_array($type, ['HANDOVER','DISPOSAL'], true) ? '' : 'grid-span' ?>">
              <label class="form-label">Bukti foto</label>
              <input type="file" name="evidence_file" class="form-control" accept="image/*" <?= $type === 'DISPOSAL' ? 'required' : '' ?>>
              <div class="form-text"><?= $type === 'DISPOSAL' ? 'Wajib untuk disposal.' : 'Opsional, berguna untuk kondisi sebelum/sesudah.' ?></div>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Alasan / catatan</label>
            <textarea name="reason" class="form-control" rows="5" required placeholder="Tulis alasan, konteks, atau instruksi penting untuk approver."></textarea>
          </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
          <a href="<?= $backUrl ?>" class="btn btn-outline-secondary">Batal</a>
          <button type="submit" id="workflowSubmit" class="btn btn-primary" <?= $hasAsset ? '' : 'disabled' ?>><i class="ri ri-save-line me-1"></i>Simpan Workflow</button>
        </div>
      </div>

      <div class="asset-help-box p-3">
        <div class="fw-semibold mb-1"><i class="ri ri-flow-chart me-1"></i>Alur kontrol</div>
        <div class="small text-muted">
          Workflow disimpan sebagai pending. Setelah approve, mutasi/serah-terima/disposal perlu diposting agar data master aset berubah. Maintenance selesai akan membuat audit trail biaya dan bukti realisasi.
        </div>
      </div>
    </div>
  </div>
</form>

<script>
(function(){
  var searchInput = document.getElementById('workflowAssetSearch');
  var resultsBox = document.getElementById('workflowAssetResults');
  var selectedBox = document.getElementById('workflowSelectedAsset');
  var assetIdInput = document.getElementById('workflowAssetId');
  var submitButton = document.getElementById('workflowSubmit');
  var endpoint = <?= json_encode(site_url('asset-management/search-assets'), JSON_UNESCAPED_SLASHES) ?>;
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
    if (resultsBox) resultsBox.classList.remove('is-open');
  }

  function renderResults(rows) {
    if (!resultsBox) return;
    if (!rows.length) {
      resultsBox.innerHTML = '<div class="asset-lookup-item text-muted">Tidak ada aset yang cocok.</div>';
      resultsBox.classList.add('is-open');
      return;
    }
    resultsBox.innerHTML = rows.map(function(row){
      return '<button type="button" class="asset-lookup-item" data-pick-asset data-row="' + esc(JSON.stringify(row)) + '">' +
        '<div class="fw-semibold">' + esc(row.label || '-') + '</div>' +
        '<div class="small text-muted">' + esc(row.meta || '-') + '</div>' +
        '<div class="small"><span class="badge bg-label-secondary">' + esc(row.status_label || row.status || '-') + '</span> <span class="text-muted">Nilai buku ' + esc(money(row.book_value || 0)) + '</span></div>' +
      '</button>';
    }).join('');
    resultsBox.classList.add('is-open');
  }

  function renderSelected(row) {
    var photo = row.photo_path
      ? '<img class="asset-selected-thumb" src="' + esc(baseUrl + row.photo_path) + '" alt="' + esc(row.asset_name || '') + '">'
      : '<span class="asset-selected-empty"><i class="ri ri-file-text-line"></i></span>';
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
      '<div class="mb-2"><span class="text-muted">Status</span><div class="fw-semibold">' + esc(row.status_label || row.status || '-') + '</div></div>' +
      '<div><span class="text-muted">Nilai buku</span><div class="fw-semibold">' + esc(money(row.book_value || 0)) + '</div></div>';
  }

  function searchAsset() {
    var q = (searchInput.value || '').trim();
    if (q.length < 2) {
      closeResults();
      return;
    }
    resultsBox.innerHTML = '<div class="asset-lookup-item text-muted">Mencari...</div>';
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
      submitButton.disabled = !(row.id > 0);
      renderSelected(row);
      closeResults();
    });
  }

  document.addEventListener('click', function(event){
    if (!event.target.closest('.asset-lookup')) {
      closeResults();
    }
  });
})();
</script>
