<?php
$eventLabels = (array)($event_type_labels ?? []);
$documentLabels = (array)($document_type_labels ?? []);
?>
<div class="container-xxl py-3 print-config-page">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1">Monitor Cetak</h4>
      <p class="fin-page-subtitle mb-0">Jejak target cetak yang dibuat POS dan jawaban dari komputer agent.</p>
    </div>
  </div>
  <?php $this->load->view('pos/_printer_config_common', ['printer_config_tab' => 'monitor']); ?>
  <div class="print-config-note mb-3"><strong>Cara membaca status:</strong> <em>Disiapkan</em> berarti POS telah membuat target cetak. <em>Terkirim ke agent</em> berarti browser berhasil meneruskannya ke komputer printer. <em>Gagal</em> berarti agent atau jaringan perlu diperiksa. Ini adalah jejak teknis; operator tetap perlu memastikan kertas benar-benar keluar.</div>
  <div class="card print-config-card"><div class="card-body">
    <div class="print-config-toolbar mb-3">
      <div class="form-group"><label>Cari cetakan</label><input id="monitor-q" class="form-control" placeholder="Nota, event, printer, atau aturan"></div>
      <div class="form-group"><label>Status</label><select id="monitor-status" class="form-select"><option value="ALL">Semua status</option><option value="GENERATED">Disiapkan</option><option value="SENT">Terkirim ke agent</option><option value="FAILED">Gagal</option><option value="SKIPPED">Dilewati</option></select></div>
      <div class="form-group" style="max-width:130px"><label>Baris</label><select id="monitor-limit" class="form-select"><option>10</option><option selected>25</option><option>50</option></select></div>
      <button class="btn btn-primary print-config-action" id="monitor-filter"><?php $this->load->view('pos/_icon', ['name' => 'search', 'label' => '']); ?><span class="ms-1">Terapkan</span></button>
      <button class="btn btn-outline-secondary print-config-icon-btn" id="monitor-clear" title="Clear filter" aria-label="Clear filter"><?php $this->load->view('pos/_icon', ['name' => 'clear', 'label' => 'Clear filter']); ?></button>
    </div>
    <div class="print-config-table-wrap"><table class="table table-hover print-config-table"><thead><tr><th>Waktu</th><th>Event / Dokumen</th><th>Order</th><th>Aturan / Printer</th><th>Status</th><th>Pesan Agent</th></tr></thead><tbody id="monitor-body"></tbody></table></div>
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mt-3" id="monitor-pager"></div>
  </div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const ui = window.PrinterConfigUI;
  const eventLabels = <?= json_encode($eventLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const documentLabels = <?= json_encode($documentLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const statusLabels = {GENERATED:'Disiapkan', SENT:'Terkirim ke agent', FAILED:'Gagal', SKIPPED:'Dilewati'};
  const state = {q:'', status:'ALL', page:1, limit:25};
  const body = document.getElementById('monitor-body');
  const endpoint = '<?= site_url('pos/printers/monitor') ?>';

  function query() {
    return new URLSearchParams({monitor_q:state.q, monitor_status:state.status, monitor_page:state.page, monitor_limit:state.limit}).toString();
  }
  function controls() {
    document.getElementById('monitor-q').value = state.q;
    document.getElementById('monitor-status').value = state.status;
    document.getElementById('monitor-limit').value = state.limit;
  }
  async function load() {
    controls();
    const json = await ui.get(endpoint + '/data?' + query());
    body.innerHTML = (json.rows || []).map(function (row) {
      const eventLabel = eventLabels[row.event_code] || row.event_code || '-';
      const documentLabel = documentLabels[row.document_type] || row.document_type || '-';
      const statusLabel = statusLabels[row.status] || row.status || '-';
      return '<tr><td>' + ui.escapeHtml(row.requested_at || '-') + '<div class="muted">' + ui.escapeHtml(row.attempt_no || '-') + '</div></td>'
        + '<td><strong>' + ui.escapeHtml(eventLabel) + '</strong><div class="muted">' + ui.escapeHtml(documentLabel) + '</div></td>'
        + '<td>' + ui.escapeHtml(row.order_no || '-') + '</td>'
        + '<td><strong>' + ui.escapeHtml(row.route_name || '-') + '</strong><div class="muted">' + ui.escapeHtml(row.connection_name || '-') + '</div></td>'
        + '<td><span class="print-config-status ' + String(row.status || 'generated').toLowerCase() + '">' + ui.escapeHtml(statusLabel) + '</span></td>'
        + '<td class="small">' + ui.escapeHtml(row.agent_message || '-') + '</td></tr>';
    }).join('') || '<tr><td colspan="6" class="print-config-empty">Belum ada jejak cetak yang sesuai filter.</td></tr>';
    ui.pager(document.getElementById('monitor-pager'), json.meta || {}, state, load);
  }

  document.getElementById('monitor-filter').onclick = function () {
    state.q = document.getElementById('monitor-q').value.trim();
    state.status = document.getElementById('monitor-status').value;
    state.limit = Number(document.getElementById('monitor-limit').value);
    state.page = 1;
    load().catch(function (error) { ui.notice('error', 'Monitor tidak dapat dimuat', error.message); });
  };
  document.getElementById('monitor-clear').onclick = function () {
    state.q = '';
    state.status = 'ALL';
    state.page = 1;
    load().catch(function (error) { ui.notice('error', 'Monitor tidak dapat dimuat', error.message); });
  };
  load().catch(function (error) {
    body.innerHTML = '<tr><td colspan="6" class="print-config-empty">' + ui.escapeHtml(error.message) + '</td></tr>';
  });
});
</script>
