<?php
$options = is_array($printer_options ?? null) ? $printer_options : [];
$outlets = (array)($options['outlets'] ?? []);
$divisions = (array)($options['operational_divisions'] ?? []);
$canCreate = !empty($can_create);
$canEdit = !empty($can_edit);
?>
<div class="container-xxl py-3 print-config-page">
  <div class="fin-page-header mb-3">
    <div><h4 class="fin-page-title mb-1">Koneksi Printer</h4><p class="fin-page-subtitle mb-0">Satu baris adalah satu printer fisik yang benar-benar dapat dipanggil oleh POS.</p></div>
    <?php if ($canCreate): ?><button class="btn btn-primary" id="new-connection"><?php $this->load->view('pos/_icon', ['name' => 'plus', 'label' => 'Tambah koneksi']); ?><span class="ms-1">Tambah Koneksi</span></button><?php endif; ?>
  </div>
  <?php $this->load->view('pos/_printer_config_common', ['printer_config_tab' => 'connections']); ?>
  <div class="print-config-note mb-3"><strong>Gunakan Local Agent.</strong> POS mengirim perintah ke aplikasi agent pada komputer di lokasi printer. Kode printer dan port harus sama dengan konfigurasi agent. Tombol test mengirim kertas uji ke printer ini dan hasilnya dicatat di Monitor Cetak.</div>
  <div class="card print-config-card"><div class="card-body">
    <div class="d-flex gap-2 flex-wrap mb-3" id="connection-status-tabs"><button class="btn btn-sm btn-primary" data-status="ACTIVE">Aktif</button><button class="btn btn-sm btn-outline-primary" data-status="INACTIVE">Nonaktif</button><button class="btn btn-sm btn-outline-primary" data-status="ALL">Semua</button></div>
    <div class="print-config-toolbar mb-3">
      <div class="form-group"><label>Cari koneksi</label><input id="connection-q" class="form-control" placeholder="Nama, lokasi, agent, atau kode printer"></div>
      <div class="form-group" style="max-width:130px"><label>Baris</label><select id="connection-limit" class="form-select"><option>10</option><option selected>25</option><option>50</option></select></div>
      <button class="btn btn-primary print-config-action" id="connection-filter"><?php $this->load->view('pos/_icon', ['name' => 'search', 'label' => '']); ?><span class="ms-1">Terapkan</span></button>
      <button class="btn btn-outline-secondary print-config-icon-btn" id="connection-clear" title="Clear filter" aria-label="Clear filter"><?php $this->load->view('pos/_icon', ['name' => 'clear', 'label' => 'Clear filter']); ?></button>
    </div>
    <div class="print-config-table-wrap"><table class="table table-hover print-config-table"><thead><tr><th>Koneksi</th><th>Lokasi</th><th>Agent / Perangkat</th><th>Kertas</th><th>Status</th><th class="text-center">Aksi</th></tr></thead><tbody id="connection-body"></tbody></table></div>
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mt-3" id="connection-pager"></div>
  </div></div>
</div>

<div class="modal fade print-config-modal" id="connection-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><div><h5 class="modal-title" id="connection-title">Tambah Koneksi Printer</h5><div class="small text-muted">Isi data printer fisik dan komputer agent. Aturan kapan mencetak dibuat terpisah di Aturan Cetak.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><form id="connection-form" class="row g-3"><input type="hidden" name="id"><input type="hidden" name="connection_code">
    <div class="col-md-8"><label class="form-label">Nama koneksi</label><input class="form-control" name="connection_name" required placeholder="Contoh: Printer BAR Reguler"></div>
    <div class="col-md-4"><label class="form-label">Status</label><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="is_active" checked><label class="form-check-label">Koneksi aktif</label></div></div>
    <div class="col-md-4"><label class="form-label">Outlet</label><select class="form-select" name="outlet_id"><option value="0">Semua outlet</option><?php foreach ($outlets as $row): ?><option value="<?= (int)$row['id'] ?>"><?= html_escape((string)$row['outlet_name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label">Divisi / lokasi kerja</label><select class="form-select" name="operational_division_id"><option value="0">Tidak dikunci divisi</option><?php foreach ($divisions as $row): ?><option value="<?= (int)$row['id'] ?>"><?= html_escape((string)$row['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label">Label lokasi</label><input class="form-control" name="location_label" placeholder="KASIR, BAR, KITCHEN"></div>
    <input type="hidden" name="connection_type" value="LOCAL_AGENT">
    <div class="col-md-4"><label class="form-label">Metode terhubung</label><div class="form-control bg-light">Local Agent</div><div class="form-text">Metode yang sudah dipakai engine POS.</div></div>
    <div class="col-md-4"><label class="form-label">OS komputer agent</label><select class="form-select" name="agent_os"><option value="WINDOWS">Windows</option><option value="UBUNTU">Ubuntu</option><option value="OTHER">Lainnya</option></select></div>
    <div class="col-md-4"><label class="form-label">Kode printer di agent</label><input class="form-control" name="agent_printer_code" required placeholder="Mis. BAR-01"></div>
    <div class="col-md-4"><label class="form-label">Host agent</label><input class="form-control" name="agent_host" placeholder="Mis. POS-BAR-01 / IP lokal"></div>
    <div class="col-md-4"><label class="form-label">Port agent</label><input type="number" min="1" class="form-control" name="python_port" value="9101"></div>
    <div class="col-md-4"><label class="form-label">Nama perangkat (opsional)</label><input class="form-control" name="device_name" placeholder="Nama Windows printer"></div>
    <div class="col-md-4"><label class="form-label">MAC perangkat (opsional)</label><input class="form-control" name="mac_address" placeholder="00:00:00:00:00:00"></div>
    <div class="col-md-4"><label class="form-label">IP LAN (catatan perangkat)</label><input class="form-control" name="ip_address"></div>
    <div class="col-md-3"><label class="form-label">Lebar kertas</label><select class="form-select" name="paper_width_mm"><option value="80">80 mm</option><option value="58">58 mm</option></select></div>
    <div class="col-md-3"><label class="form-label">Karakter / baris</label><input type="number" class="form-control" name="chars_per_line" value="48" readonly aria-readonly="true"><div class="form-text" id="chars-per-line-help">Ditentukan otomatis dari lebar kertas.</div></div>
    <div class="col-md-3"><label class="form-label">Copy default</label><input type="number" min="1" max="10" class="form-control" name="default_copy_count" value="1"></div>
    <div class="col-md-3"><label class="form-label">Potong kertas</label><select class="form-select" name="cut_mode"><option value="PARTIAL">Sebagian</option><option value="FULL">Penuh</option><option value="NONE">Tidak potong</option></select></div>
    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="open_drawer"><label class="form-check-label">Buka laci kas saat mencetak dari koneksi ini</label></div></div>
    <div class="col-12"><label class="form-label">Catatan</label><textarea class="form-control" rows="2" name="notes" placeholder="Contoh: printer ada di meja bar dekat mesin espresso."></textarea></div>
  </form></div>
  <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Batal</button><?php if ($canCreate || $canEdit): ?><button class="btn btn-primary" id="connection-save"><?php $this->load->view('pos/_icon', ['name' => 'save', 'label' => '']); ?><span class="ms-1">Simpan Koneksi</span></button><?php endif; ?></div>
</div></div></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const ui = window.PrinterConfigUI;
  const canCreate = <?= $canCreate ? 'true' : 'false' ?>;
  const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
  const state = {q: '', status: 'ACTIVE', page: 1, limit: 25};
  const body = document.getElementById('connection-body');
  const endpoint = '<?= site_url('pos/printers/connections') ?>';
  const modal = new bootstrap.Modal(document.getElementById('connection-modal'));
  const form = document.getElementById('connection-form');
  let rows = {};
  function query() { return new URLSearchParams({connection_q: state.q, connection_status: state.status, connection_page: state.page, connection_limit: state.limit}).toString(); }
  function controls() {
    document.getElementById('connection-q').value = state.q;
    document.getElementById('connection-limit').value = state.limit;
    document.querySelectorAll('#connection-status-tabs [data-status]').forEach(function (button) { button.className = 'btn btn-sm ' + (button.dataset.status === state.status ? 'btn-primary' : 'btn-outline-primary'); });
  }
  function actionButtons(row) {
    if (!canEdit) return '<span class="muted">Baca saja</span>';
    const toggleClass = Number(row.is_active) ? 'is-danger' : 'is-success';
    const toggleTitle = Number(row.is_active) ? 'Nonaktifkan koneksi' : 'Aktifkan koneksi';
    return '<div class="d-flex justify-content-center gap-1">' +
      '<button class="btn btn-outline-secondary print-config-icon-btn is-neutral" data-test="' + Number(row.id) + '" title="Test cetak" aria-label="Test cetak">' + ui.icon('test', 'Test cetak') + '</button>' +
      '<button class="btn btn-outline-primary print-config-icon-btn is-primary" data-edit="' + Number(row.id) + '" title="Edit koneksi" aria-label="Edit koneksi">' + ui.icon('edit', 'Edit koneksi') + '</button>' +
      '<button class="btn btn-outline-secondary print-config-icon-btn ' + toggleClass + '" data-toggle="' + Number(row.id) + '" title="' + toggleTitle + '" aria-label="' + toggleTitle + '">' + ui.icon('power', toggleTitle) + '</button>' +
    '</div>';
  }
  function paperSpec(row) { const width = Number(row && row.paper_width_mm || 80) === 58 ? 58 : 80; return {width: width, chars: width === 58 ? 32 : 48}; }
  function syncPaperSpec() { const spec = paperSpec({paper_width_mm: form.elements.paper_width_mm.value}); form.elements.chars_per_line.value = spec.chars; document.getElementById('chars-per-line-help').textContent = spec.width + ' mm memakai ' + spec.chars + ' karakter per baris agar teks tidak melewati lebar kertas.'; }
  async function load() {
    controls();
    const json = await ui.get(endpoint + '/data?' + query());
    rows = {};
    body.innerHTML = (json.rows || []).map(function (row) {
      rows[row.id] = row;
      const spec = paperSpec(row); return '<tr><td><strong>' + ui.escapeHtml(row.connection_name) + '</strong><div class="muted">' + ui.escapeHtml(row.connection_code) + '</div></td><td>' + ui.escapeHtml(row.outlet_name || 'Semua outlet') + '<div class="muted">' + ui.escapeHtml(row.operational_division_name || row.location_label || 'Tidak dikunci divisi') + '</div></td><td><strong>' + ui.escapeHtml(row.agent_printer_code || '-') + '</strong><div class="muted">' + ui.escapeHtml(row.agent_host || '-') + ' : ' + ui.escapeHtml(row.python_port || '-') + '</div></td><td>' + ui.escapeHtml(spec.width) + ' mm<div class="muted">' + ui.escapeHtml(spec.chars) + ' karakter</div></td><td>' + ui.status(row.is_active) + '</td><td class="text-center">' + actionButtons(row) + '</td></tr>';
    }).join('') || '<tr><td colspan="6" class="print-config-empty">Belum ada koneksi printer.</td></tr>';
    ui.pager(document.getElementById('connection-pager'), json.meta || {}, state, load);
  }
  function open(row) {
    ui.setForm(form, row || {is_active: 1, connection_type: 'LOCAL_AGENT', agent_os: 'WINDOWS', python_port: 9101, paper_width_mm: 80, chars_per_line: 48, default_copy_count: 1, cut_mode: 'PARTIAL'});
    syncPaperSpec();
    document.getElementById('connection-title').textContent = row ? 'Edit Koneksi Printer' : 'Tambah Koneksi Printer';
    modal.show();
  }
  async function acknowledge(target, status, message) { const id = Number(target && target.print_attempt_id || 0); if (!id) return; try { await ui.post('<?= site_url('pos/printers/attempts/ack') ?>', {attempt_id: id, status: status, message: message || ''}); } catch (ignored) {} }
  async function testConnection(id) {
    let target = null;
    try {
      const json = await ui.post(endpoint + '/test/' + id, {});
      target = json.target || {};
      const port = Number(target.python_port || 0);
      if (!port) throw new Error('Port Local Agent belum valid.');
      const result = await ui.agentPrint(target);
      await acknowledge(target, 'SENT', result.message || 'Test koneksi dikirim ke Local Agent.');
      ui.notice('success', 'Test cetak dikirim', (result.message || 'Perintah diterima.') + ' Periksa kertas pada ' + String(target.printer_name || 'printer tujuan') + '. Hasil juga tercatat di Monitor Cetak.');
    } catch (error) {
      const message = error.message || 'Tidak dapat menghubungi Local Agent.';
      if (target) await acknowledge(target, 'FAILED', message);
      ui.notice('error', 'Test printer gagal', message);
    }
  }
  const newButton = document.getElementById('new-connection'); if (newButton) newButton.addEventListener('click', function () { open(null); });
  form.elements.paper_width_mm.addEventListener('change', syncPaperSpec);
  document.getElementById('connection-filter').addEventListener('click', function () { state.q = document.getElementById('connection-q').value.trim(); state.limit = Number(document.getElementById('connection-limit').value); state.page = 1; load().catch(function (error) { ui.notice('error', 'Koneksi tidak dapat dimuat', error.message); }); });
  document.getElementById('connection-clear').addEventListener('click', function () { state.q = ''; state.status = 'ACTIVE'; state.page = 1; load().catch(function (error) { ui.notice('error', 'Koneksi tidak dapat dimuat', error.message); }); });
  document.querySelectorAll('#connection-status-tabs [data-status]').forEach(function (button) { button.addEventListener('click', function () { state.status = button.dataset.status; state.page = 1; load().catch(function (error) { ui.notice('error', 'Koneksi tidak dapat dimuat', error.message); }); }); });
  body.addEventListener('click', async function (event) {
    const edit = event.target.closest('[data-edit]'); const test = event.target.closest('[data-test]'); const toggle = event.target.closest('[data-toggle]');
    if (edit) { open(rows[edit.dataset.edit]); return; }
    if (test) { await testConnection(Number(test.dataset.test)); return; }
    if (!toggle) return;
    try { await ui.post(endpoint + '/toggle/' + Number(toggle.dataset.toggle), {}); await load(); ui.notice('success', 'Status koneksi diperbarui', 'Koneksi printer memakai status terbaru.'); } catch (error) { ui.notice('error', 'Status koneksi gagal diubah', error.message); }
  });
  const saveButton = document.getElementById('connection-save'); if (saveButton) saveButton.addEventListener('click', async function () { if (!form.reportValidity()) return; saveButton.disabled = true; try { await ui.post(endpoint + '/save', ui.formObject(form)); modal.hide(); await load(); ui.notice('success', 'Koneksi disimpan', 'Koneksi siap dipakai oleh Aturan Cetak.'); } catch (error) { ui.notice('error', 'Koneksi belum tersimpan', error.message); } finally { saveButton.disabled = false; } });
  load().catch(function (error) { body.innerHTML = '<tr><td colspan="6" class="print-config-empty">' + ui.escapeHtml(error.message) + '</td></tr>'; });
});
</script>
