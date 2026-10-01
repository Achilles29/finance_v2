<?php
/**
 * _division_stock_generate_btn.php
 * Tombol Generate Opname + Stok Awal untuk semua halaman rumpun Bahan Baku Divisi.
 *
 * Variables (all optional):
 *   $division_action_params : array of filter params (month, division_id, destination_type)
 */
$params = is_array($division_action_params ?? null) ? $division_action_params : [];

$month       = (string)($params['month']       ?? date('Y-m'));
$divisionId  = (string)($params['division_id'] ?? '');
$destType    = (string)($params['destination_type'] ?? $params['destination'] ?? '');

$opnameUrl  = site_url('inventory/stock/opname/division/monthly')
    . '?' . http_build_query(array_filter(['month' => $month, 'division_id' => $divisionId]));
$openingUrl = site_url('inventory/stock/opening/division')
    . '?' . http_build_query(array_filter(['month' => $month, 'division_id' => $divisionId]));
$generateUrl = site_url('inventory/stock/opname/generate');
?>

<div class="d-flex flex-wrap gap-1 align-items-center mb-2 px-1" id="div-stock-action-bar">
  <span class="text-muted fw-semibold" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;min-width:70px">Aksi Cepat</span>
  <button type="button" class="btn btn-sm btn-outline-danger" id="btnDivStockGenerate"
          data-month="<?php echo html_escape($month); ?>"
          data-division-id="<?php echo html_escape($divisionId); ?>"
          data-destination="<?php echo html_escape($destType); ?>"
          data-bs-toggle="modal"
          data-bs-target="#divStockGenerateModal">
    <i class="ri ri-refresh-line me-1"></i>Generate Opname &amp; Stok Awal
  </button>
  <a href="<?php echo html_escape($opnameUrl); ?>" class="btn btn-sm btn-outline-secondary">
    <i class="ri ri-file-list-3-line me-1"></i>Lihat Opname
  </a>
  <a href="<?php echo html_escape($openingUrl); ?>" class="btn btn-sm btn-outline-secondary">
    <i class="ri ri-archive-drawer-line me-1"></i>Lihat Opening
  </a>
  <div id="divStockGenerateAlert" class="w-100" style="display:none"></div>
</div>

<div class="modal fade" id="divStockGenerateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Generate Opname Bahan Baku Divisi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="divStockGenerateMonth" class="form-label">Bulan Sumber</label>
          <input type="month" class="form-control" id="divStockGenerateMonth" value="<?php echo html_escape($month); ?>">
          <small class="text-muted">Pilih bulan sumber opname. Closing bulan ini akan menjadi stok awal bulan berikutnya.</small>
        </div>
        <div id="divStockGenerateContext" class="small text-muted mb-3"></div>
        <div class="alert alert-light border small mb-0">
          <div><strong>Hasil generate:</strong></div>
          <div>1. Snapshot opname divisi untuk bulan sumber yang dipilih.</div>
          <div>2. Stok awal bulan berikutnya dibuat hanya untuk saldo akhir yang tidak sama dengan 0.</div>
          <div>3. Jika generate diulang, data generate sebelumnya akan ditimpa.</div>
          <div>4. Source of truth mengikuti stock bulanan final.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-danger" id="btnDivStockGenerateSubmit">
          <i class="ri ri-refresh-line me-1"></i>Generate Sekarang
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const btn     = document.getElementById('btnDivStockGenerate');
  const alertEl = document.getElementById('divStockGenerateAlert');
  const modalEl = document.getElementById('divStockGenerateModal');
  const monthEl = document.getElementById('divStockGenerateMonth');
  const submitBtn = document.getElementById('btnDivStockGenerateSubmit');
  const contextEl = document.getElementById('divStockGenerateContext');
  if (!btn || !modalEl || !monthEl || !submitBtn) return;

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function nextMonth(month) {
    const parts = String(month || '').split('-');
    if (parts.length !== 2) return '';
    let year = Number(parts[0]);
    let mon = Number(parts[1]);
    if (!year || !mon || mon < 1 || mon > 12) return '';
    mon += 1;
    if (mon > 12) {
      mon = 1;
      year += 1;
    }
    return String(year).padStart(4, '0') + '-' + String(mon).padStart(2, '0');
  }

  function reloadToMonth(month) {
    const url = new URL(window.location.href);
    if (url.searchParams.has('month') || document.querySelector('input[name="month"]')) {
      url.searchParams.set('month', month);
    }
    if (url.searchParams.has('date_from') || document.querySelector('input[name="date_from"]')) {
      url.searchParams.set('date_from', month + '-01');
    }
    if (url.searchParams.has('date_to') || document.querySelector('input[name="date_to"]')) {
      const monthEnd = new Date(Number(month.slice(0, 4)), Number(month.slice(5, 7)), 0);
      url.searchParams.set('date_to', monthEnd.getFullYear() + '-' + String(monthEnd.getMonth() + 1).padStart(2, '0') + '-' + String(monthEnd.getDate()).padStart(2, '0'));
    }
    if (btn.dataset.divisionId && btn.dataset.divisionId !== '0') {
      url.searchParams.set('division_id', btn.dataset.divisionId);
    }
    if (btn.dataset.destination && btn.dataset.destination !== '' && btn.dataset.destination !== 'ALL') {
      url.searchParams.set('destination', btn.dataset.destination);
      url.searchParams.set('destination_type', btn.dataset.destination);
    }
    window.location.href = url.toString();
  }

  function showAlert(type, msg) {
    if (!alertEl) return;
    alertEl.style.display = '';
    alertEl.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible mb-1 py-2 small"><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>' + msg + '</div>';
  }

  function renderContext(month) {
    if (!contextEl) return;
    const parts = [];
    if (btn.dataset.divisionId && btn.dataset.divisionId !== '0') {
      parts.push('Divisi ID: <strong>' + escapeHtml(btn.dataset.divisionId) + '</strong>');
    }
    if (btn.dataset.destination && btn.dataset.destination !== '' && btn.dataset.destination !== 'ALL') {
      parts.push('Tujuan: <strong>' + escapeHtml(btn.dataset.destination) + '</strong>');
    }
    parts.push('Stok awal target: <strong>' + escapeHtml(nextMonth(month) || '-') + '</strong>');
    contextEl.innerHTML = parts.join(' <span class="text-muted">•</span> ');
  }

  modalEl.addEventListener('show.bs.modal', function () {
    const month = btn.dataset.month || monthEl.value || '';
    if (month) monthEl.value = month;
    renderContext(monthEl.value || month);
  });

  monthEl.addEventListener('input', function () {
    renderContext(monthEl.value || '');
  });

  submitBtn.addEventListener('click', async () => {
    const month      = monthEl.value || '';
    const divisionId = btn.dataset.divisionId || '';
    const destination = btn.dataset.destination || '';
    if (!month) { showAlert('warning', 'Pilih bulan sumber terlebih dahulu.'); return; }

    const origHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';
    if (alertEl) alertEl.style.display = 'none';

    try {
      const body = { stock_scope: 'DIVISION', month };
      if (divisionId && divisionId !== '0') body.division_id = divisionId;
      if (destination && destination !== 'ALL') body.destination_type = destination;

      const res  = await fetch(<?php echo json_encode($generateUrl); ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(body),
      });
      const raw = await res.text();
      let data;
      try {
        data = JSON.parse(raw);
      } catch (err) {
        throw new Error(raw && raw.trim() !== '' ? raw.trim().slice(0, 240) : 'Respons server bukan JSON valid.');
      }
      if (!res.ok || !data.ok) {
        let errHtml = 'Gagal: ' + escapeHtml(data.message || res.statusText);
        const negSamples = data.data && data.data.negative_samples;
        if (negSamples && negSamples.length) {
          errHtml += '<ul class="mb-0 mt-1 ps-3" style="font-size:.8em">'
            + negSamples.map(s => '<li>' + escapeHtml(s) + '</li>').join('') + '</ul>';
        }
        const posSamples = data.data && data.data.pending_pos_samples;
        if (posSamples && posSamples.length) {
          errHtml += '<div class="mt-1" style="font-size:.8em">Order: ' + posSamples.map(escapeHtml).join(', ') + '</div>';
        }
        showAlert('danger', errHtml);
        submitBtn.disabled = false;
        submitBtn.innerHTML = origHtml;
        return;
      }

      btn.dataset.month = month;
      showAlert('success', 'Generate selesai untuk bulan <strong>' + escapeHtml(month) + '</strong>. '
        + (data.message || '') + ' <a href="' + <?php echo json_encode(site_url('inventory/stock/opname/division/monthly')); ?> + '?month=' + encodeURIComponent(month) + '" class="alert-link ms-1">Lihat Opname</a>'
        + ' <a href="' + <?php echo json_encode(site_url('inventory/stock/opening/division')); ?> + '?month=' + encodeURIComponent(nextMonth(month)) + '" class="alert-link ms-1">Lihat Opening</a>');
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
      window.setTimeout(function () {
        reloadToMonth(month);
      }, 400);
    } catch (err) {
      showAlert('danger', 'Error: ' + escapeHtml(err.message));
    }

    submitBtn.disabled = false;
    submitBtn.innerHTML = origHtml;
  });
})();
</script>
