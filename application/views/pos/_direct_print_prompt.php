<style>
  .pos-print-ask-modal .modal-content{border:0;border-radius:22px;overflow:hidden;box-shadow:0 24px 55px rgba(42,27,28,.28)}
  .pos-print-ask-modal .modal-header{border-bottom:1px solid #eeded6;background:linear-gradient(135deg,#fff9f5,#fff)}
  .pos-print-ask-icon{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:#fff0ec;color:#a80e27;flex:0 0 auto}
  .pos-print-ask-icon svg{width:25px;height:25px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
  .pos-print-ask-target{border:1px solid #ead9d0;background:#fffaf7;border-radius:14px;padding:.8rem .9rem;color:#6f5b53;font-size:.88rem;line-height:1.45}
</style>
<div class="modal fade pos-print-ask-modal" id="pos-direct-print-ask-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><div class="d-flex align-items-center gap-3"><div class="pos-print-ask-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9V3h12v6"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v7H6z"></path></svg></div><div><h5 class="modal-title mb-1">Cetak dokumen?</h5><div class="small text-muted">Transaksi sudah tersimpan. Pilih apakah dokumen perlu dicetak.</div></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div><div class="modal-body"><div class="pos-print-ask-target" id="pos-direct-print-ask-target">Printer tujuan</div></div><div class="modal-footer"><button type="button" class="btn btn-light" id="pos-direct-print-skip">Tidak usah cetak</button><button type="button" class="btn btn-primary" id="pos-direct-print-confirm">Cetak sekarang</button></div></div></div></div>
<script>
window.PosDirectPrintPrompt = window.PosDirectPrintPrompt || (function () {
  let modal = null;
  let root = null;
  let resolvePending = null;
  function finish(answer) {
    const resolve = resolvePending;
    resolvePending = null;
    if (modal) modal.hide();
    if (resolve) resolve(Boolean(answer));
  }
  function ensure() {
    if (root) return true;
    root = document.getElementById('pos-direct-print-ask-modal');
    if (!root || !window.bootstrap || !window.bootstrap.Modal) return false;
    modal = new window.bootstrap.Modal(root);
    document.getElementById('pos-direct-print-confirm').addEventListener('click', function () { finish(true); });
    document.getElementById('pos-direct-print-skip').addEventListener('click', function () { finish(false); });
    root.addEventListener('hidden.bs.modal', function () { if (resolvePending) finish(false); });
    return true;
  }
  function documentLabel(target) {
    const type = String((target || {}).document_type || '').toUpperCase();
    const labels = {KITCHEN_TICKET: 'KOT / tiket produksi', RECEIPT: 'Struk pembayaran', PRE_BILL: 'Bill sementara', VOID_SLIP: 'Slip void', REFUND_SLIP: 'Slip refund', SHIFT_CLOSE: 'Ringkasan tutup kasir'};
    return labels[type] || 'Dokumen POS';
  }
  return {
    ask: function (target) {
      if (!ensure()) return Promise.resolve(window.confirm('Cetak ' + documentLabel(target) + ' ke ' + String((target || {}).printer_name || 'printer tujuan') + '?'));
      const destination = document.getElementById('pos-direct-print-ask-target');
      destination.textContent = documentLabel(target) + ' akan dikirim ke ' + String((target || {}).printer_name || (target || {}).printer_code || 'printer tujuan') + '.';
      return new Promise(function (resolve) { resolvePending = resolve; modal.show(); });
    }
  };
})();

window.PosDirectAgentPrint = window.PosDirectAgentPrint || {
  send: async function (target, timeoutMs) {
    const port = Number((target || {}).python_port || 0);
    if (!port) throw new Error('Port Local Agent belum valid.');
    // Satu printer fisik bisa menerima beberapa slip sekaligus dari BAR,
    // KITCHEN, dan CHECKER. Beri agent waktu cukup untuk mengantrekan cetak
    // tersebut; batas lama 8 detik membuat slip yang sudah tercetak tercatat
    // salah sebagai gagal di halaman kasir.
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
};
</script>
