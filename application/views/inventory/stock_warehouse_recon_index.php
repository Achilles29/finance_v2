<?php
$snapshot = is_array($snapshot ?? null) ? $snapshot : [];
$context = is_array($context ?? null) ? $context : [];
$ready = !empty($snapshot['ok']);
$reconDate = (string)($recon_date ?? date('Y-m-d'));
$fmtQty = static fn($value): string => number_format((float)$value, 4, ',', '.');
$fmtMoney = static fn($value): string => 'Rp ' . number_format((float)$value, 2, ',', '.');
$fmtCost = static fn($value): string => 'Rp ' . number_format((float)$value, 6, ',', '.');
$systemQty = (float)($snapshot['system_qty_content'] ?? 0);
$lotQty = (float)($snapshot['lot_qty_content'] ?? 0);
$lotGap = round($lotQty - $systemQty, 4);
$lotsNeedRecon = abs($lotGap) > 0.0001;
?>

<style>
.warehouse-recon{--wr-ink:#3f2925;--wr-muted:#896a61;--wr-line:#ecd9d0;--wr-red:#ad1024;--wr-dark:#850a19;--wr-bg:#fffaf7}.warehouse-recon .wr-hero{border:1px solid var(--wr-line);border-radius:18px;background:linear-gradient(135deg,#fffefa,#fff1ea);padding:1rem 1.1rem}.warehouse-recon .wr-hero p{margin:.35rem 0 0;color:var(--wr-muted);font-size:.82rem;max-width:850px}.warehouse-recon .wr-card{border:1px solid var(--wr-line);border-radius:18px;background:#fff;overflow:hidden}.warehouse-recon .wr-card-head{padding:.9rem 1rem;background:var(--wr-bg);border-bottom:1px solid var(--wr-line)}.warehouse-recon .wr-card-head h5{font-size:1rem;margin:0;color:var(--wr-ink)}.warehouse-recon .wr-card-head small{color:var(--wr-muted)}.warehouse-recon .wr-card-body{padding:1rem}.warehouse-recon .wr-kpi{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.warehouse-recon .wr-kpi-item{border:1px solid var(--wr-line);border-radius:14px;background:#fffdfb;padding:.78rem .85rem}.warehouse-recon .wr-kpi-item.is-alert{background:#fff4f3;border-color:#f1c4bc}.warehouse-recon .wr-kpi-item.is-alert strong{color:#b42318}.warehouse-recon .wr-kpi-item span{display:block;font-size:.67rem;font-weight:850;text-transform:uppercase;letter-spacing:.04em;color:var(--wr-muted)}.warehouse-recon .wr-kpi-item strong{display:block;margin-top:.23rem;font-size:1.06rem;color:var(--wr-ink)}.warehouse-recon .wr-kpi-item small{font-size:.72rem;color:var(--wr-muted)}.warehouse-recon .wr-callout{border:1px solid #f2d9aa;border-radius:14px;background:#fff9e8;padding:.8rem .9rem;color:#80541f;font-size:.8rem;line-height:1.45}.warehouse-recon .wr-result{display:none;border-radius:13px;padding:.75rem .85rem;font-size:.82rem}.warehouse-recon .wr-result.ok{display:block;background:#ecf8ef;border:1px solid #c7e9d0;color:#17623d}.warehouse-recon .wr-result.error{display:block;background:#fff0ef;border:1px solid #f3c8c3;color:#a9261d}@media(max-width:900px){.warehouse-recon .wr-kpi{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){.warehouse-recon .wr-kpi{grid-template-columns:1fr}}
</style>

<div class="warehouse-recon">
  <section class="wr-hero mb-3 d-flex justify-content-between align-items-start gap-3 flex-wrap">
    <div><h4 class="mb-1"><i class="ri ri-clipboard-line page-title-icon"></i><?php echo html_escape((string)($page_title ?? 'Recon Stok Fisik Gudang')); ?></h4><p>Masukkan jumlah barang yang benar-benar Anda hitung di gudang. Bila jumlahnya berbeda, sistem membuat Adjustment Gudang. Bila jumlah stok sudah sama tetapi lot masih berbeda, sistem hanya menyelaraskan lot aktif tanpa mengubah stok.</p></div>
    <div class="d-flex gap-2"><a class="btn btn-outline-secondary btn-sm" href="<?php echo html_escape((string)($deficit_url ?? site_url('inventory/stock/deficits'))); ?>">Defisit Stok</a><a class="btn btn-outline-danger btn-sm" href="<?php echo html_escape((string)($health_url ?? site_url('inventory/stock/health'))); ?>">Kembali ke Stock Health</a></div>
  </section>

  <?php if (!$ready): ?>
    <div class="alert alert-danger"><strong>Profil belum bisa direkon.</strong> <?php echo html_escape((string)($snapshot['message'] ?? 'Buka halaman ini dari baris Stock Health atau Defisit Stok agar profil barangnya tepat.')); ?></div>
  <?php else: ?>
    <section class="wr-card">
      <div class="wr-card-head"><h5><?php echo html_escape((string)($snapshot['inventory_name'] ?? 'Bahan baku')); ?></h5><small>Gudang | Profil <?php echo html_escape(substr((string)($snapshot['profile_key'] ?? '-'), 0, 18)); ?><?php if (!empty($snapshot['profile_key'])): ?>...<?php endif; ?></small></div>
      <div class="wr-card-body">
        <div class="wr-callout mb-3"><strong>Bedakan dua pilihan:</strong> hasil hitung fisik selalu mengatur stok dan lot ke angka nyata. Centang penyelesaian defisit hanya bila kekurangan lama dari profil yang sama memang sudah tertutup oleh barang yang tersedia sekarang. Bila stok fisik nol, defisit tidak dapat ditutup dari angka nol.<?php if ($lotsNeedRecon): ?> <strong>Untuk profil ini, stok dan lot belum sama. Simpan recon tetap diperlukan walaupun Anda memasukkan angka stok sistem yang sama.</strong><?php endif; ?></div>
        <div class="wr-kpi mb-3">
          <div class="wr-kpi-item"><span>Stok sistem saat ini</span><strong id="systemQtyText"><?php echo $fmtQty($systemQty); ?></strong><small><?php echo html_escape((string)($snapshot['profile_content_uom_code'] ?? '')); ?></small></div>
          <div class="wr-kpi-item <?php echo $lotsNeedRecon ? 'is-alert' : ''; ?>"><span>Lot aktif saat ini</span><strong><?php echo $fmtQty($lotQty); ?></strong><small><?php echo (int)($snapshot['lot_count'] ?? 0); ?> lot OPEN<?php if ($lotsNeedRecon): ?> | selisih <?php echo $fmtQty($lotGap); ?><?php endif; ?></small></div>
          <div class="wr-kpi-item"><span>Biaya yang dipakai bila stok bertambah</span><strong><?php echo $fmtCost($snapshot['avg_cost_per_content'] ?? 0); ?></strong><small>Diambil dari stok aktif, defisit, atau katalog yang cocok</small></div>
          <div class="wr-kpi-item"><span>Nilai stok / lot</span><strong><?php echo $fmtMoney($systemQty * (float)($snapshot['avg_cost_per_content'] ?? 0)); ?></strong><small>Lot aktif: <?php echo $fmtMoney($snapshot['lot_value'] ?? 0); ?></small></div>
        </div>

        <form id="warehouseReconForm" novalidate>
          <input type="hidden" name="item_id" value="<?php echo (int)($snapshot['item_id'] ?? 0); ?>">
          <input type="hidden" name="material_id" value="<?php echo (int)($snapshot['material_id'] ?? 0); ?>">
          <input type="hidden" name="buy_uom_id" value="<?php echo (int)($snapshot['buy_uom_id'] ?? 0); ?>">
          <input type="hidden" name="content_uom_id" value="<?php echo (int)($snapshot['content_uom_id'] ?? 0); ?>">
          <input type="hidden" name="profile_key" value="<?php echo html_escape((string)($snapshot['profile_key'] ?? '')); ?>">
          <input type="hidden" name="input_mode" value="PHYSICAL_COUNT">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label small fw-bold">Tanggal hitung fisik</label><input required class="form-control" type="date" name="opname_date" value="<?php echo html_escape($reconDate); ?>"></div>
            <div class="col-md-5"><label class="form-label small fw-bold">Stok fisik sekarang (<?php echo html_escape((string)($snapshot['profile_content_uom_code'] ?? '')); ?>)</label><div class="input-group"><input required min="0" step="0.0001" class="form-control" type="number" name="physical_qty_content" id="physicalQty" placeholder="Masukkan hasil hitung"><button class="btn btn-outline-secondary" type="button" id="useSystemQty">Gunakan stok sistem</button></div><small class="text-muted">Masukkan jumlah akhir yang benar-benar terlihat, bukan selisih tambah atau kurang.</small></div>
            <div class="col-md-3 d-flex align-items-end"><div class="form-check pb-2"><input class="form-check-input" type="checkbox" name="settle_open_deficit" value="1" id="settleDeficit"><label class="form-check-label small" for="settleDeficit">Selesaikan defisit terbuka yang profilnya sama</label></div></div>
            <div class="col-12"><label class="form-label small fw-bold">Catatan pemeriksaan</label><textarea class="form-control" rows="2" name="notes" placeholder="Contoh: hitung rak freezer gudang oleh admin, stok fisik sudah sesuai."></textarea></div>
          </div>
          <div id="warehouseReconResult" class="wr-result mt-3"></div>
          <div class="text-end mt-3"><button class="btn btn-danger" type="submit" id="warehouseReconSubmit"><i class="ri ri-save-3-line"></i> Simpan Recon Gudang</button></div>
        </form>
      </div>
    </section>
  <?php endif; ?>
</div>

<script>
(function () {
  var form = document.getElementById('warehouseReconForm');
  if (!form) return;
  var physical = document.getElementById('physicalQty');
  var useSystem = document.getElementById('useSystemQty');
  var submit = document.getElementById('warehouseReconSubmit');
  var result = document.getElementById('warehouseReconResult');
  var endpoint = <?php echo json_encode(site_url('inventory/stock/daily-recon/warehouse/quick-adjust')); ?>;
  var systemQty = <?php echo json_encode((float)($snapshot['system_qty_content'] ?? 0)); ?>;

  function show(message, error) {
    result.textContent = message || '';
    result.className = 'wr-result mt-3 ' + (error ? 'error' : 'ok');
  }
  function parseResponse(response) {
    return response.text().then(function (raw) {
      try { return JSON.parse(raw); }
      catch (err) {
        var summary = raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 200);
        throw new Error('Server tidak mengirim respons Recon Gudang yang valid (HTTP ' + response.status + ').' + (summary ? ' Ringkasan: ' + summary : ' Periksa log server bila berulang.'));
      }
    });
  }
  useSystem.addEventListener('click', function () {
    physical.value = systemQty;
    physical.focus();
  });
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!form.reportValidity()) return;
    if (!window.confirm('Simpan hasil hitung fisik gudang? Stok dan lot aktif akan disamakan dengan angka yang Anda masukkan. Bila stok sudah sama, sistem tetap akan memeriksa dan menyelaraskan lot aktif.')) return;
    var payload = {};
    new FormData(form).forEach(function (value, key) { payload[key] = value; });
    if (!payload.settle_open_deficit) payload.settle_open_deficit = 0;
    submit.disabled = true;
    submit.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memproses';
    fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    }).then(parseResponse).then(function (data) {
      if (!data || !data.ok) throw new Error((data && data.message) || 'Recon gudang ditolak oleh server.');
      var text = data.message || 'Recon gudang berhasil diposting.';
      if (data.data && data.data.warning) text += ' ' + data.data.warning;
      show(text + ' Muat ulang Stock Health untuk melihat kondisi terbaru.', false);
    }).catch(function (error) {
      show(error.message || 'Recon gudang gagal diproses.', true);
    }).finally(function () {
      submit.disabled = false;
      submit.innerHTML = '<i class="ri ri-save-3-line"></i> Simpan Recon Gudang';
    });
  });
}());
</script>
