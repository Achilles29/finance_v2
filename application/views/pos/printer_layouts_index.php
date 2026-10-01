<?php
$documentTypes = (array)($document_types ?? []);
$documentLabels = (array)($document_type_labels ?? []);
$canCreate = !empty($can_create);
$canEdit = !empty($can_edit);
?>
<div class="container-xxl py-3 print-config-page">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1">Layout Dokumen</h4>
      <p class="fin-page-subtitle mb-0">Pilih isi yang muncul di struk atau tiket, lalu periksa hasilnya langsung sebelum dipakai.</p>
    </div>
    <?php if ($canCreate): ?><a class="btn btn-primary" href="<?= site_url('pos/printers/layouts/create') ?>"><?php $this->load->view('pos/_icon', ['name' => 'plus', 'label' => 'Tambah layout']); ?><span class="ms-1">Tambah Layout</span></a><?php endif; ?>
  </div>
  <?php $this->load->view('pos/_printer_config_common', ['printer_config_tab' => 'layouts']); ?>
  <div class="print-config-note mb-3"><strong>Layout mengatur isi dokumen.</strong> Nama outlet, logo, Wi-Fi, dan pesan QR ulasan tetap dibaca dari <a href="<?= site_url('pos/printers/general') ?>">Tampilan Umum</a>. Klik ikon pensil untuk membuka editor lengkap dan preview langsung.</div>
  <div class="card print-config-card"><div class="card-body">
    <div class="d-flex gap-2 flex-wrap mb-3" id="layout-status-tabs">
      <button class="btn btn-sm btn-primary" data-status="ACTIVE">Aktif</button>
      <button class="btn btn-sm btn-outline-primary" data-status="INACTIVE">Nonaktif</button>
      <button class="btn btn-sm btn-outline-primary" data-status="ALL">Semua</button>
    </div>
    <div class="print-config-toolbar mb-3">
      <div class="form-group"><label>Cari layout</label><input id="layout-q" class="form-control" placeholder="Nama, kode, atau catatan"></div>
      <div class="form-group"><label>Jenis dokumen</label><select id="layout-document-type" class="form-select"><option value="ALL">Semua dokumen</option><?php foreach ($documentTypes as $type): ?><option value="<?= html_escape((string)$type) ?>"><?= html_escape((string)($documentLabels[$type] ?? $type)) ?></option><?php endforeach; ?></select></div>
      <div class="form-group" style="max-width:130px"><label>Baris</label><select id="layout-limit" class="form-select"><option>10</option><option selected>25</option><option>50</option></select></div>
      <button class="btn btn-primary print-config-action" id="layout-filter"><?php $this->load->view('pos/_icon', ['name' => 'search', 'label' => '']); ?><span class="ms-1">Terapkan</span></button>
      <button class="btn btn-outline-secondary print-config-icon-btn" id="layout-clear" title="Clear filter" aria-label="Clear filter"><?php $this->load->view('pos/_icon', ['name' => 'clear', 'label' => 'Clear filter']); ?></button>
    </div>
    <div class="print-config-table-wrap"><table class="table table-hover print-config-table"><thead><tr><th>Layout</th><th>Dokumen</th><th>Data Utama</th><th>Default</th><th>Status</th><th class="text-center">Aksi</th></tr></thead><tbody id="layout-body"></tbody></table></div>
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mt-3" id="layout-pager"></div>
  </div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const ui = window.PrinterConfigUI;
  const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
  const labels = <?= json_encode($documentLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const state = {q: '', status: 'ACTIVE', document_type: 'ALL', page: 1, limit: 25};
  const body = document.getElementById('layout-body');
  const endpoint = '<?= site_url('pos/printers/layouts') ?>';
  const editBase = '<?= site_url('pos/printers/layouts/edit') ?>';
  function query() { return new URLSearchParams({layout_q: state.q, layout_status: state.status, layout_document_type: state.document_type, layout_page: state.page, layout_limit: state.limit}).toString(); }
  function controls() {
    document.getElementById('layout-q').value = state.q;
    document.getElementById('layout-document-type').value = state.document_type;
    document.getElementById('layout-limit').value = state.limit;
    document.querySelectorAll('#layout-status-tabs [data-status]').forEach(function (button) { button.className = 'btn btn-sm ' + (button.dataset.status === state.status ? 'btn-primary' : 'btn-outline-primary'); });
  }
  function actionButtons(row) {
    if (!canEdit) return '<span class="muted">Baca saja</span>';
    const toggleClass = Number(row.is_active) ? 'is-danger' : 'is-success';
    const toggleTitle = Number(row.is_active) ? 'Nonaktifkan layout' : 'Aktifkan layout';
    return '<div class="d-flex justify-content-center gap-1">' +
      '<button class="btn btn-outline-primary print-config-icon-btn is-primary" data-edit="' + Number(row.id) + '" title="Edit layout" aria-label="Edit layout">' + ui.icon('edit', 'Edit layout') + '</button>' +
      '<button class="btn btn-outline-secondary print-config-icon-btn ' + toggleClass + '" data-toggle="' + Number(row.id) + '" title="' + toggleTitle + '" aria-label="' + toggleTitle + '">' + ui.icon('power', toggleTitle) + '</button>' +
    '</div>';
  }
  async function load() {
    controls();
    const json = await ui.get(endpoint + '/data?' + query());
    body.innerHTML = (json.rows || []).map(function (row) {
      const payload = row.payload || {};
      const data = [];
      if (payload.show_product_name) data.push('Produk');
      if (payload.show_price) data.push('Harga');
      if (payload.show_extra) data.push('Extra');
      if (payload.show_notes) data.push('Catatan');
      if (payload.show_grand_total) data.push('Total');
      if (payload.show_customer_review_qr) data.push('QR ulasan');
      return '<tr><td><strong>' + ui.escapeHtml(row.layout_name) + '</strong><div class="muted">' + ui.escapeHtml(row.layout_code) + '</div></td><td>' + ui.escapeHtml(labels[row.document_type] || row.document_type) + '</td><td>' + ui.escapeHtml(data.join(', ') || 'Data utama belum dipilih') + '</td><td>' + (Number(row.is_default) ? '<span class="print-config-status generated">Ya</span>' : '-') + '</td><td>' + ui.status(row.is_active) + '</td><td class="text-center">' + actionButtons(row) + '</td></tr>';
    }).join('') || '<tr><td colspan="6" class="print-config-empty">Belum ada layout. Tambahkan layout pertama untuk mulai mengatur tampilan cetak.</td></tr>';
    ui.pager(document.getElementById('layout-pager'), json.meta || {}, state, load);
  }
  document.getElementById('layout-filter').addEventListener('click', function () { state.q = document.getElementById('layout-q').value.trim(); state.document_type = document.getElementById('layout-document-type').value; state.limit = Number(document.getElementById('layout-limit').value); state.page = 1; load().catch(function (error) { ui.notice('error', 'Layout tidak dapat dimuat', error.message); }); });
  document.getElementById('layout-clear').addEventListener('click', function () { state.q = ''; state.status = 'ACTIVE'; state.document_type = 'ALL'; state.page = 1; load().catch(function (error) { ui.notice('error', 'Layout tidak dapat dimuat', error.message); }); });
  document.querySelectorAll('#layout-status-tabs [data-status]').forEach(function (button) { button.addEventListener('click', function () { state.status = button.dataset.status; state.page = 1; load().catch(function (error) { ui.notice('error', 'Layout tidak dapat dimuat', error.message); }); }); });
  body.addEventListener('click', async function (event) {
    const edit = event.target.closest('[data-edit]');
    const toggle = event.target.closest('[data-toggle]');
    if (edit) { window.location.href = editBase + '/' + Number(edit.dataset.edit); return; }
    if (!toggle) return;
    try { await ui.post(endpoint + '/toggle/' + Number(toggle.dataset.toggle), {}); await load(); ui.notice('success', 'Status layout diperbarui', 'Aturan cetak akan memakai status terbaru.'); } catch (error) { ui.notice('error', 'Status layout gagal diubah', error.message); }
  });
  load().catch(function (error) { body.innerHTML = '<tr><td colspan="6" class="print-config-empty">' + ui.escapeHtml(error.message) + '</td></tr>'; });
});
</script>
