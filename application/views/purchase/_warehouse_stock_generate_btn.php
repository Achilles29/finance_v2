<?php
/**
 * _warehouse_stock_generate_btn.php
 * Quick action generate opname + stok awal untuk semua halaman rumpun gudang.
 *
 * Variables:
 *   $warehouse_action_params : array
 *     - month
 *     - date_from
 *     - back_url
 */
$params = is_array($warehouse_action_params ?? null) ? $warehouse_action_params : [];

$monthValue = trim((string)($params['month'] ?? ''));
if ($monthValue === '' && !empty($params['date_from'])) {
    $monthValue = date('Y-m', strtotime((string)$params['date_from']));
}
if (!preg_match('/^\d{4}-\d{2}$/', $monthValue)) {
    $monthValue = date('Y-m');
}

$generateUrl = site_url('inventory/stock/opname/generate');
$opnameBaseUrl = site_url('inventory/stock/opname/warehouse/monthly');
$openingBaseUrl = site_url('inventory/stock/stok-awal/warehouse');
?>

<div class="d-flex flex-wrap gap-2 align-items-center mb-2 px-1" id="wh-stock-action-bar">
  <span class="text-muted fw-semibold" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;min-width:70px">Aksi Cepat</span>
  <button
    type="button"
    class="btn btn-sm btn-outline-danger"
    id="btnWhStockGenerate"
    data-month="<?php echo html_escape($monthValue); ?>"
    data-bs-toggle="modal"
    data-bs-target="#whStockGenerateModal">
    <i class="ri ri-refresh-line me-1"></i>Generate Opname &amp; Stok Awal
  </button>
  <a href="<?php echo html_escape($opnameBaseUrl . '?month=' . rawurlencode($monthValue)); ?>" class="btn btn-sm btn-outline-secondary" id="whStockOpnameLink">
    <i class="ri ri-file-list-3-line me-1"></i>Lihat Opname
  </a>
  <a href="<?php echo html_escape($openingBaseUrl . '?month=' . rawurlencode(date('Y-m', strtotime($monthValue . '-01 +1 month')))); ?>" class="btn btn-sm btn-outline-secondary" id="whStockOpeningLink">
    <i class="ri ri-archive-drawer-line me-1"></i>Lihat Stok Awal
  </a>
  <div id="whStockGenerateAlert" class="w-100" style="display:none"></div>
</div>

<div class="modal fade" id="whStockGenerateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Generate Opname Gudang</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="whStockGenerateMonth" class="form-label">Bulan Sumber</label>
          <input type="month" class="form-control" id="whStockGenerateMonth" value="<?php echo html_escape($monthValue); ?>">
          <small class="text-muted">Generate opname membaca seluruh keluar-masuk stok dalam 1 bulan.</small>
        </div>
        <div class="alert alert-light border small mb-0">
          <div><strong>Hasil generate:</strong></div>
          <div>1. Opname bulanan gudang untuk bulan sumber yang dipilih.</div>
          <div>2. Stok awal otomatis dibuat untuk bulan berikutnya, hanya untuk saldo akhir yang tidak sama dengan 0.</div>
          <div>3. Jika generate diulang, data generate sebelumnya akan ditimpa.</div>
          <div>4. Nilai sisa opname dan stok awal bulan berikutnya harus sama.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-danger" id="btnWhStockGenerateSubmit">
          <i class="ri ri-refresh-line me-1"></i>Generate Sekarang
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const modalEl = document.getElementById('whStockGenerateModal');
  const monthEl = document.getElementById('whStockGenerateMonth');
  const submitBtn = document.getElementById('btnWhStockGenerateSubmit');
  const triggerBtn = document.getElementById('btnWhStockGenerate');
  const alertEl = document.getElementById('whStockGenerateAlert');
  const opnameLink = document.getElementById('whStockOpnameLink');
  const openingLink = document.getElementById('whStockOpeningLink');
  if (!modalEl || !monthEl || !submitBtn || !triggerBtn) return;

  function escapeHtml(str) {
    return String(str)
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

  function renderLinks(month) {
    const openingMonth = nextMonth(month);
    if (opnameLink) {
      opnameLink.href = <?php echo json_encode($opnameBaseUrl); ?> + '?month=' + encodeURIComponent(month);
    }
    if (openingLink) {
      openingLink.href = <?php echo json_encode($openingBaseUrl); ?> + '?month=' + encodeURIComponent(openingMonth);
    }
  }

  function showAlert(type, msg) {
    if (!alertEl) return;
    alertEl.style.display = '';
    alertEl.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible mb-1 py-2 small">'
      + '<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>'
      + msg + '</div>';
  }

  modalEl.addEventListener('show.bs.modal', function () {
    const month = triggerBtn.dataset.month || monthEl.value || '';
    if (month) monthEl.value = month;
  });

  submitBtn.addEventListener('click', async function () {
    const month = monthEl.value || '';
    if (!month) {
      showAlert('warning', 'Pilih bulan yang ingin digenerate terlebih dahulu.');
      return;
    }

    const targetMonth = nextMonth(month);
    if (!confirm('Generate opname gudang bulan sumber ' + month + ' dan timpa stok awal bulan ' + targetMonth + ' jika sudah ada?')) {
      return;
    }

    const originalHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';
    if (alertEl) alertEl.style.display = 'none';

    try {
      const res = await fetch(<?php echo json_encode($generateUrl); ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ stock_scope: 'WAREHOUSE', month: month })
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        showAlert('danger', 'Gagal: ' + escapeHtml(data.message || res.statusText));
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;
        return;
      }

      triggerBtn.dataset.month = month;
      renderLinks(month);
      const resultData = data.data || {};
      showAlert(
        'success',
        'Generate selesai untuk bulan <strong>' + escapeHtml(month) + '</strong>. '
        + 'Opname: <strong>' + escapeHtml(String(resultData.opname_rows || 0)) + '</strong> baris, '
        + 'stok awal snapshot: <strong>' + escapeHtml(String(resultData.opening_rows || 0)) + '</strong> baris, '
        + 'stok awal monthly: <strong>' + escapeHtml(String(resultData.opening_monthly_rows || 0)) + '</strong> baris.'
      );
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
    } catch (err) {
      showAlert('danger', 'Error: ' + escapeHtml(err.message));
    }

    submitBtn.disabled = false;
    submitBtn.innerHTML = originalHtml;
  });

  renderLinks(triggerBtn.dataset.month || monthEl.value || <?php echo json_encode($monthValue); ?>);
})();
</script>
