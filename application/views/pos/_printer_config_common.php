<?php
$printerConfigTab = (string)($printer_config_tab ?? 'overview');
?>
<style>
  .print-config-page{max-width:1540px;margin:0 auto}
  .print-config-card{border:1px solid #eadbd3;border-radius:18px;background:#fffdfb;box-shadow:0 10px 28px rgba(81,49,39,.06)}
  .print-config-card .card-body{padding:1.15rem}
  .print-config-kicker{font-size:.72rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#a16d60}
  .print-config-note{border:1px solid #f0d5bc;border-radius:14px;background:#fff8ed;color:#755f54;padding:.8rem .95rem;line-height:1.5}
  .print-config-table-wrap{max-height:calc(100vh - 330px);min-height:260px;overflow:auto;border:1px solid #ecdcd3;border-radius:14px}
  .print-config-table{margin:0;min-width:920px}
  .print-config-table thead th{position:sticky;top:0;z-index:2;background:#a80e27;color:#fff;border:0;white-space:nowrap;font-size:.76rem;letter-spacing:.02em}
  .print-config-table td{vertical-align:middle;border-color:#f0e1d9}
  .print-config-table .muted{font-size:.78rem;color:#947e73}
  .print-config-status{display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;padding:.24rem .58rem;font-size:.74rem;font-weight:800}
  .print-config-status.active,.print-config-status.sent{background:#e9f8ef;color:#087443}
  .print-config-status.inactive,.print-config-status.failed{background:#fff0ef;color:#b42318}
  .print-config-status.generated{background:#fff7df;color:#9a6700}
  .print-config-status.skipped{background:#eef2f6;color:#53616e}
  .print-config-toolbar{display:flex;gap:.65rem;align-items:end;flex-wrap:wrap}
  .print-config-toolbar .form-group{min-width:170px;flex:1 1 170px}
  .print-config-toolbar label{font-size:.76rem;font-weight:800;margin-bottom:.28rem;color:#5b423a}
  .print-config-action{white-space:nowrap}
  .print-config-icon-btn{width:38px;height:38px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:10px}
  .print-config-icon-btn svg{width:17px;height:17px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
  .print-config-inline-icon{width:17px;height:17px;vertical-align:-.2em;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
  .print-config-icon-btn.is-danger{color:#b42318;border-color:#f1b2ae;background:#fff8f7}
  .print-config-icon-btn.is-success{color:#087443;border-color:#a8dfbd;background:#f5fff8}
  .print-config-icon-btn.is-primary{color:#9f1531;border-color:#e7bdc8;background:#fff8fa}
  .print-config-icon-btn.is-neutral{color:#5e6773;border-color:#d9dee5;background:#fff}
  .print-config-icon-btn:hover{transform:translateY(-1px);box-shadow:0 6px 14px rgba(67,43,37,.12)}
  .print-config-notice-stack{position:fixed;right:1rem;bottom:1rem;z-index:1090;width:min(390px,calc(100vw - 2rem));display:grid;gap:.65rem;pointer-events:none}
  .print-config-notice{pointer-events:auto;display:flex;gap:.75rem;align-items:flex-start;padding:.85rem .95rem;border-radius:14px;border:1px solid;background:#fff;box-shadow:0 14px 30px rgba(50,34,30,.18);animation:printNoticeIn .2s ease-out}
  .print-config-notice .notice-icon{flex:0 0 auto;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center}
  .print-config-notice .notice-icon svg{width:17px;height:17px;stroke:currentColor;stroke-width:2.5;fill:none;stroke-linecap:round;stroke-linejoin:round}
  .print-config-notice .notice-title{font-weight:800;color:#352b29;line-height:1.15}
  .print-config-notice .notice-text{font-size:.83rem;color:#745f57;line-height:1.4;margin-top:.15rem}
  .print-config-notice.success{border-color:#a8dfbd}.print-config-notice.success .notice-icon{color:#087443;background:#e9f8ef}
  .print-config-notice.error{border-color:#f0b7b1}.print-config-notice.error .notice-icon{color:#b42318;background:#fff0ef}
  .print-config-notice.info{border-color:#bfd4f3}.print-config-notice.info .notice-icon{color:#175ea9;background:#eef5ff}
  @keyframes printNoticeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
  .print-config-empty{padding:1.35rem;text-align:center;color:#8e786e}
  .print-config-stat{border:1px solid #efdfd5;border-radius:15px;background:linear-gradient(135deg,#fff 0%,#fff7f3 100%);padding:.85rem 1rem}
  .print-config-stat .value{font-size:1.55rem;font-weight:800;color:#3e2730;line-height:1.1}
  .print-config-stat .label{font-size:.72rem;letter-spacing:.06em;text-transform:uppercase;font-weight:800;color:#9b776d}
  .print-config-modal .modal-content{border:0;border-radius:20px;overflow:hidden}
  .print-config-modal .modal-header{border-bottom:1px solid #efdfd5}
  .print-config-modal .modal-footer{border-top:1px solid #efdfd5}
  .print-config-checkboxes{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:.45rem .8rem}
  .print-config-checkboxes .form-check{border:1px solid #edddd4;border-radius:10px;padding:.45rem .5rem .45rem 2rem;background:#fff}
  .print-config-pre{white-space:pre-wrap;margin:0;min-height:300px;background:#fff;color:#2e2323;border:1px solid #eadbd3;border-radius:12px;padding:1rem;font-family:Consolas,monospace;font-size:.79rem;line-height:1.45}
  @media(max-width:767.98px){.print-config-page{padding:0 .25rem}.print-config-table-wrap{max-height:none}.print-config-toolbar .form-group{flex-basis:100%}}
</style>
<?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'printer']); ?>
<?php $this->load->view('pos/_printer_tabs', ['printer_tab_active' => $printerConfigTab]); ?>
<div class="print-config-notice-stack" id="print-config-notice-stack" aria-live="polite" aria-atomic="true"></div>
<script>
window.PrinterConfigUI = window.PrinterConfigUI || (function () {
  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
    });
  }
  async function request(url, options) {
    const response = await fetch(url, Object.assign({headers: {'X-Requested-With': 'XMLHttpRequest'}}, options || {}));
    const raw = await response.text();
    let json;
    try { json = JSON.parse(raw); } catch (error) {
      throw new Error('Server tidak mengembalikan data yang dapat dibaca. Cek login, hak akses, atau log aplikasi.');
    }
    if (!response.ok || !json.ok) throw new Error(json.message || 'Permintaan tidak berhasil diproses.');
    return json;
  }
  function get(url) { return request(url); }
  function post(url, payload) {
    return request(url, {
      method: 'POST',
      headers: {'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json'},
      body: JSON.stringify(payload || {})
    });
  }
  async function agentPrint(target, timeoutMs) {
    const port = Number((target || {}).python_port || 0);
    if (!port) throw new Error('Port Local Agent belum valid.');
    // Test cetak memakai jalur agent yang sama dengan transaksi. Waktu tunggu
    // perlu mengakomodasi antrean bila beberapa role memakai printer fisik sama.
    const requestedTimeoutMs = Number((target || {}).response_timeout_ms || timeoutMs || 30000);
    const responseTimeoutMs = Math.max(10000, Math.min(60000, requestedTimeoutMs));
    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    const timeout = window.setTimeout(function () {
      if (controller) controller.abort();
    }, responseTimeoutMs);
    try {
      const response = await fetch('http://127.0.0.1:' + port + '/cetak', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        signal: controller ? controller.signal : undefined,
        body: JSON.stringify({
          text: String((target || {}).text || ''),
          printer_code: String((target || {}).printer_code || ''),
          printer_name: String((target || {}).printer_name || ''),
          paper_width_mm: Number((target || {}).paper_width_mm || 80),
          chars_per_line: Number((target || {}).chars_per_line || 48),
          copies: Math.max(1, Number((target || {}).copies || 1)),
          cut_mode: String((target || {}).cut_mode || 'PARTIAL'),
          open_drawer: Number((target || {}).open_drawer || 0) === 1
        })
      });
      const raw = await response.text();
      let result = {};
      try { result = raw ? JSON.parse(raw) : {}; } catch (error) {
        throw new Error('Local Agent mengembalikan jawaban yang tidak dapat dibaca.');
      }
      if (!response.ok || String(result.status || '').toLowerCase() !== 'success') {
        throw new Error(result.message || ('Local Agent menolak cetak (HTTP ' + response.status + ').'));
      }
      return result;
    } catch (error) {
      if (error && error.name === 'AbortError') {
        throw new Error('Local Agent belum memberi jawaban dalam ' + Math.ceil(responseTimeoutMs / 1000) + ' detik. Cetak mungkin masih diproses; cek Monitor Cetak sebelum mengirim ulang.');
      }
      if (error && /failed to fetch|networkerror/i.test(String(error.message || ''))) {
        throw new Error('Tidak dapat menghubungi Local Agent. Pastikan aplikasi agent sedang berjalan di komputer ini dan port printer sudah sesuai.');
      }
      throw error;
    } finally {
      window.clearTimeout(timeout);
    }
  }
  function status(flag, labels) {
    const active = Number(flag || 0) === 1;
    const text = active ? ((labels && labels.active) || 'Aktif') : ((labels && labels.inactive) || 'Nonaktif');
    return '<span class="print-config-status ' + (active ? 'active' : 'inactive') + '">' + escapeHtml(text) + '</span>';
  }
  function attemptStatus(value) {
    const status = String(value || 'GENERATED').toUpperCase();
    const css = ['SENT', 'FAILED', 'SKIPPED'].indexOf(status) >= 0 ? status.toLowerCase() : 'generated';
    return '<span class="print-config-status ' + css + '">' + escapeHtml(status) + '</span>';
  }
  function icon(name, label) {
    const paths = {
      clear: '<path d="M3 3l18 18"></path><path d="M16 3h5v5"></path><path d="M21 3l-5.5 5.5"></path><path d="M3 8h5"></path><path d="M3 13h4"></path><path d="M3 18h9"></path>',
      search: '<circle cx="11" cy="11" r="6"></circle><path d="m16 16 4 4"></path>',
      edit: '<path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path>',
      test: '<path d="M6 9V3h12v6"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v7H6z"></path><path d="M9 18h6"></path>',
      power: '<path d="M12 2v10"></path><path d="M18.4 5.6a8 8 0 1 1-12.8 0"></path>',
      eye: '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path><circle cx="12" cy="12" r="2.5"></circle>',
      plus: '<path d="M12 5v14"></path><path d="M5 12h14"></path>',
      check: '<path d="m5 12 4 4L19 6"></path>',
      close: '<path d="M6 6l12 12"></path><path d="m18 6-12 12"></path>',
      info: '<circle cx="12" cy="12" r="9"></circle><path d="M12 11v5"></path><path d="M12 8h.01"></path>'
    };
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' + (paths[name] || paths.info) + '</svg><span class="visually-hidden">' + escapeHtml(label || name) + '</span>';
  }
  function notice(type, title, message, timeout) {
    const stack = document.getElementById('print-config-notice-stack');
    if (!stack) return;
    const kind = ['success','error','info'].indexOf(String(type)) >= 0 ? String(type) : 'info';
    const symbol = kind === 'success' ? 'check' : (kind === 'error' ? 'close' : 'info');
    const item = document.createElement('div');
    item.className = 'print-config-notice ' + kind;
    item.innerHTML = '<div class="notice-icon">' + icon(symbol, '') + '</div><div class="flex-grow-1"><div class="notice-title">' + escapeHtml(title || 'Informasi') + '</div><div class="notice-text">' + escapeHtml(message || '') + '</div></div><button type="button" class="btn btn-sm p-0 text-muted" aria-label="Tutup notifikasi">' + icon('close', 'Tutup') + '</button>';
    const close = function () { item.remove(); };
    item.querySelector('button').addEventListener('click', close);
    stack.appendChild(item);
    window.setTimeout(close, Number(timeout || (kind === 'error' ? 8000 : 5200)));
  }
  function pager(element, meta, state, load) {
    const total = Number((meta || {}).total || 0);
    const page = Number((meta || {}).page || 1);
    const pages = Number((meta || {}).total_pages || 1);
    const limit = Number((meta || {}).limit || state.limit || 25);
    const start = total ? ((page - 1) * limit) + 1 : 0;
    const end = total ? Math.min(total, page * limit) : 0;
    element.innerHTML = '<div class="small text-muted">' + (total ? ('Menampilkan ' + start + '-' + end + ' dari ' + total + ' data') : 'Belum ada data') + '</div>' +
      '<div class="btn-group btn-group-sm">' +
      '<button class="btn btn-outline-secondary" data-page="' + Math.max(1, page - 1) + '" ' + (page <= 1 ? 'disabled' : '') + '>Sebelumnya</button>' +
      '<button class="btn btn-light" disabled>Hal ' + page + '/' + pages + '</button>' +
      '<button class="btn btn-outline-secondary" data-page="' + Math.min(pages, page + 1) + '" ' + (page >= pages ? 'disabled' : '') + '>Berikutnya</button>' +
      '</div>';
    element.querySelectorAll('[data-page]').forEach(function (button) {
      button.addEventListener('click', function () {
        state.page = Number(button.dataset.page || 1);
        load().catch(function (error) { notice('error', 'Data tidak dapat dimuat', error.message); });
      });
    });
  }
  function formObject(form) {
    const result = {};
    new FormData(form).forEach(function (value, key) {
      if (Object.prototype.hasOwnProperty.call(result, key)) {
        result[key] = Array.isArray(result[key]) ? result[key].concat([value]) : [result[key], value];
      } else {
        result[key] = value;
      }
    });
    form.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
      result[input.name] = input.checked ? 1 : 0;
    });
    return result;
  }
  function setForm(form, row) {
    form.reset();
    Object.keys(row || {}).forEach(function (key) {
      const field = form.elements.namedItem(key);
      if (!field) return;
      if (field instanceof RadioNodeList) return;
      if (field.type === 'checkbox') field.checked = Number(row[key] || 0) === 1;
      else field.value = row[key] ?? '';
    });
    form.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
      if (!Object.prototype.hasOwnProperty.call(row || {}, input.name)) input.checked = false;
    });
  }
  return {escapeHtml: escapeHtml, get: get, post: post, agentPrint: agentPrint, status: status, attemptStatus: attemptStatus, icon: icon, notice: notice, pager: pager, formObject: formObject, setForm: setForm};
})();
</script>
