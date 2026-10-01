<?php
$dateFrom = (string)($date_from ?? date('Y-m-01'));
$dateTo = (string)($date_to ?? date('Y-m-d'));
$status = strtoupper(trim((string)($status ?? 'ALL')));
$purchaseTypeId = (int)($purchase_type_id ?? 0);
$detailDate = (string)($detail_date ?? '');
$detailPurchaseTypeId = (int)($detail_purchase_type_id ?? 0);
$reportTab = (string)($report_tab ?? 'ringkasan');
$overview = (array)($overview ?? []);
$monthlyRows = (array)($monthly_rows ?? []);
$dailyRows = (array)($daily_rows ?? []);
$matrixDates = (array)($matrix_dates ?? []);
$matrixRows = (array)($matrix_rows ?? []);
$matrixDetailMap = (array)($matrix_detail_map ?? []);
$detailRows = (array)($detail_rows ?? []);
$reportDetailBaseUrl = site_url('purchase-orders/report/detail');
?>

<style>
  .pur-report-card { border: 0; box-shadow: 0 8px 24px rgba(67, 89, 113, .08); border-radius: 16px; }
  .pur-report-kpi { font-size: 1.25rem; font-weight: 800; color: #233243; line-height: 1.1; }
  .pur-report-kpi-label { font-size: .75rem; color: #6c7a89; text-transform: uppercase; letter-spacing: .06em; }
  .pur-report-table th { white-space: nowrap; font-size: .74rem; }
  .pur-report-table td { font-size: .78rem; vertical-align: middle; }
  .pur-filter-row .form-label { font-size: .78rem; font-weight: 700; margin-bottom: .35rem; }
  .pur-filter-actions { display: flex; gap: .5rem; align-items: end; justify-content: flex-end; }
  .pur-summary-card { height: 100%; }
  .pur-summary-table-wrap {
    max-height: 520px;
    min-height: 520px;
    overflow: auto;
    border: 1px solid #eadfd8;
    border-radius: 12px;
    background: #fff;
  }
  .pur-summary-table thead th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: #fff;
    box-shadow: inset 0 -1px 0 #eadfd8;
  }
  .pur-report-link { color: inherit; text-decoration: none; font-weight: 600; }
  .pur-report-link:hover { text-decoration: underline; }
  .pur-daily-pager {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    flex-wrap: wrap;
    margin-top: .85rem;
  }
  .pur-daily-pager-buttons {
    display: flex;
    align-items: center;
    gap: .35rem;
    flex-wrap: wrap;
  }
  .pur-daily-pager-buttons .btn { min-width: 40px; }
  .pur-matrix th, .pur-matrix td { white-space: nowrap; font-size: .74rem; }
  .pur-matrix {
    border-collapse: separate;
    border-spacing: 0;
    margin-bottom: 0;
  }
  .pur-matrix-wrap {
    height: 68vh;
    min-height: 420px;
    max-height: 760px;
    overflow: auto;
    position: relative;
    border: 1px solid #eadfd8;
    border-radius: 12px;
    background: #fff;
    overscroll-behavior: contain;
  }
  .pur-matrix thead {
    position: sticky;
    top: 0;
    z-index: 20;
  }
  .pur-matrix thead th {
    position: sticky !important;
    top: 0 !important;
    z-index: 20;
    background: #b80f28;
    background-clip: padding-box;
    color: #fff;
    box-shadow: inset 0 -1px 0 #87091d;
  }
  .pur-matrix .type-col {
    min-width: 260px;
    position: sticky;
    left: 0;
    z-index: 10;
    background: #fff;
    box-shadow: inset -1px 0 0 #d8cbc5, 5px 0 8px rgba(70, 45, 40, .08);
  }
  .pur-matrix tbody tr:nth-of-type(odd) .type-col { background: #fbf8f6; }
  .pur-matrix thead .type-col {
    top: 0 !important;
    left: 0 !important;
    z-index: 30 !important;
    background: #a90d24;
    color: #fff;
    box-shadow: inset -1px -1px 0 #780719, 5px 0 8px rgba(70, 15, 25, .18);
  }
  .pur-matrix .date-cell { text-align: right; min-width: 100px; }
  .pur-matrix details summary { cursor: pointer; font-size: .72rem; color: #7a6a62; }
  .pur-matrix .expand-list { font-size: .72rem; margin-top: .3rem; max-height: 120px; overflow: auto; }
  @media (max-width: 767.98px) {
    .pur-matrix-wrap { height: 65vh; min-height: 360px; }
  }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-file-chart-line page-title-icon"></i>Laporan Purchase</h4>
    <small class="text-muted">Ringkasan bulanan, harian, dan akses cepat ke rincian belanja per tipe purchase.</small>
  </div>
</div>

<?php $this->load->view('purchase/_po_sr_tabs', ['po_sr_active' => 'report-purchase']); ?>

<div class="card pur-report-card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('purchase-orders/report'); ?>" class="row g-2 pur-filter-row">
      <div class="col-xl-2 col-md-3">
        <label class="form-label">Dari</label>
        <input type="date" name="date_from" class="form-control" value="<?php echo html_escape($dateFrom); ?>">
      </div>
      <div class="col-xl-2 col-md-3">
        <label class="form-label">Sampai</label>
        <input type="date" name="date_to" class="form-control" value="<?php echo html_escape($dateTo); ?>">
      </div>
      <div class="col-xl-2 col-md-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php foreach (($status_options ?? ['ALL']) as $st): $val = strtoupper((string)$st); ?>
            <option value="<?php echo html_escape($val); ?>" <?php echo $val === $status ? 'selected' : ''; ?>><?php echo html_escape($val); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-xl-4 col-md-4">
        <label class="form-label">Tipe Purchase</label>
        <select name="purchase_type_id" class="form-select">
          <option value="0">Semua Tipe</option>
          <?php foreach (($purchase_types ?? []) as $pt): ?>
            <option value="<?php echo (int)($pt['id'] ?? 0); ?>" <?php echo (int)($pt['id'] ?? 0) === $purchaseTypeId ? 'selected' : ''; ?>>
              <?php echo html_escape((string)($pt['type_name'] ?? '-')); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-xl-2 col-md-12">
        <div class="pur-filter-actions">
          <a class="btn btn-outline-secondary" href="<?php echo site_url('purchase-orders/report') . '?' . http_build_query([
            'report_tab' => $reportTab,
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-t'),
            'status' => 'PAID',
            'purchase_type_id' => 0,
            'detail_date' => '',
            'detail_purchase_type_id' => 0,
          ]); ?>">Clear Filter</a>
          <button type="submit" class="btn btn-primary">Terapkan</button>
        </div>
      </div>
      <input type="hidden" name="report_tab" value="<?php echo html_escape($reportTab); ?>">
      <input type="hidden" name="detail_date" value="<?php echo html_escape($detailDate); ?>">
      <input type="hidden" name="detail_purchase_type_id" value="<?php echo (int)$detailPurchaseTypeId; ?>">
    </form>
  </div>
</div>

<?php
$workspace_tabs = [
  [
    'label' => 'Ringkasan',
    'active' => $reportTab === 'ringkasan',
    'url' => site_url('purchase-orders/report') . '?' . http_build_query([
      'report_tab' => 'ringkasan',
      'date_from' => $dateFrom,
      'date_to' => $dateTo,
      'status' => $status,
      'purchase_type_id' => $purchaseTypeId,
      'detail_date' => $detailDate,
      'detail_purchase_type_id' => $detailPurchaseTypeId,
    ]),
  ],
  [
    'label' => 'Matrix',
    'active' => $reportTab === 'matrix',
    'url' => site_url('purchase-orders/report') . '?' . http_build_query([
      'report_tab' => 'matrix',
      'date_from' => $dateFrom,
      'date_to' => $dateTo,
      'status' => $status,
      'purchase_type_id' => $purchaseTypeId,
      'detail_date' => $detailDate,
      'detail_purchase_type_id' => $detailPurchaseTypeId,
    ]),
  ],
];
$workspace_tab_label = 'Tampilan';
$workspace_tab_aria_label = 'Pilih tampilan laporan purchase';
$this->load->view('layout/_workspace_tabs', get_defined_vars());
?>

<?php if ($reportTab === 'ringkasan'): ?>
<div class="row g-2 mb-3">
  <div class="col-md-3"><div class="card pur-report-card"><div class="card-body"><div class="pur-report-kpi-label">Total PO</div><div class="pur-report-kpi"><?php echo number_format((int)($overview['total_po'] ?? 0)); ?></div></div></div></div>
  <div class="col-md-3"><div class="card pur-report-card"><div class="card-body"><div class="pur-report-kpi-label">Total Baris</div><div class="pur-report-kpi"><?php echo number_format((int)($overview['total_line'] ?? 0)); ?></div></div></div></div>
  <div class="col-md-3"><div class="card pur-report-card"><div class="card-body"><div class="pur-report-kpi-label">Qty Beli</div><div class="pur-report-kpi"><?php echo number_format((float)($overview['total_qty_buy'] ?? 0), 2, ',', '.'); ?></div></div></div></div>
  <div class="col-md-3"><div class="card pur-report-card"><div class="card-body"><div class="pur-report-kpi-label">Nilai Purchase</div><div class="pur-report-kpi">Rp <?php echo number_format((float)($overview['total_value'] ?? 0), 2, ',', '.'); ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card pur-report-card pur-summary-card">
      <div class="card-body">
        <h6 class="mb-3">Ringkasan Bulanan</h6>
        <div class="pur-summary-table-wrap">
          <table class="table table-sm table-striped pur-report-table pur-summary-table mb-0">
            <thead><tr><th>Bulan</th><th>Tipe</th><th class="text-end">PO</th><th class="text-end">Qty</th><th class="text-end">Nilai</th></tr></thead>
            <tbody>
            <?php if (empty($monthlyRows)): ?>
              <tr><td colspan="5" class="text-center text-muted">Belum ada data.</td></tr>
            <?php else: foreach ($monthlyRows as $r): ?>
              <?php
                $monthKey = (string)($r['month_key'] ?? '');
                $monthStart = preg_match('/^\d{4}-\d{2}$/', $monthKey) ? ($monthKey . '-01') : $dateFrom;
                $monthEnd = $monthStart !== '' ? date('Y-m-t', strtotime($monthStart)) : $dateTo;
                $detailUrl = $reportDetailBaseUrl . '?' . http_build_query([
                  'date_from' => $monthStart,
                  'date_to' => $monthEnd,
                  'status' => $status,
                  'purchase_type_id' => (int)($r['purchase_type_id'] ?? 0),
                  'per_page' => 50,
                  'page' => 1,
                ]);
              ?>
              <tr>
                <td><?php echo html_escape((string)($r['month_key'] ?? '-')); ?></td>
                <td><a class="pur-report-link" href="<?php echo html_escape($detailUrl); ?>"><?php echo html_escape((string)($r['purchase_type_name'] ?? '-')); ?></a></td>
                <td class="text-end"><?php echo number_format((int)($r['total_po'] ?? 0)); ?></td>
                <td class="text-end"><?php echo number_format((float)($r['total_qty_buy'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><a class="pur-report-link" href="<?php echo html_escape($detailUrl); ?>">Rp <?php echo number_format((float)($r['total_value'] ?? 0), 2, ',', '.'); ?></a></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card pur-report-card pur-summary-card">
      <div class="card-body">
        <h6 class="mb-3">Ringkasan Harian</h6>
        <div class="pur-summary-table-wrap">
          <table class="table table-sm table-striped pur-report-table pur-summary-table mb-0">
            <thead><tr><th>Tanggal</th><th>Tipe</th><th class="text-end">PO</th><th class="text-end">Qty</th><th class="text-end">Nilai</th></tr></thead>
            <tbody id="purDailyRows"></tbody>
          </table>
        </div>
        <div class="pur-daily-pager">
          <div class="text-muted small" id="purDailyPagerText">Memuat data...</div>
          <div class="pur-daily-pager-buttons" id="purDailyPagerButtons"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php else: ?>
<div class="card pur-report-card mb-3">
  <div class="card-body">
    <h6 class="mb-3">Matrix Purchase (Tanggal ke Kanan, Tipe ke Bawah)</h6>
    <div class="pur-matrix-wrap">
      <table class="table table-sm table-bordered align-middle pur-matrix">
        <thead>
          <tr>
            <th class="type-col">Tipe Purchase</th>
            <?php foreach ($matrixDates as $d): ?>
              <th class="date-cell"><?php echo html_escape(date('d/m', strtotime($d))); ?></th>
            <?php endforeach; ?>
            <th class="date-cell">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($matrixRows)): ?>
            <tr><td colspan="<?php echo (int)(count($matrixDates) + 2); ?>" class="text-center text-muted">Belum ada data matrix.</td></tr>
          <?php else: foreach ($matrixRows as $row): ?>
            <?php
              $typeId = (int)($row['purchase_type_id'] ?? 0);
              $typeName = (string)($row['purchase_type_name'] ?? ('TYPE #' . $typeId));
              $cells = (array)($row['cells'] ?? []);
            ?>
            <tr>
              <td class="type-col">
                <div class="fw-semibold"><?php echo html_escape($typeName); ?></div>
                <details>
                  <summary>Expand tanggal</summary>
                  <div class="expand-list">
                    <?php
                      $hasExpand = false;
                      foreach ($matrixDates as $d) {
                        $c = (array)($cells[$d] ?? []);
                        $v = (float)($c['total_value'] ?? 0);
                        if ($v <= 0) { continue; }
                        $hasExpand = true;
                        $link = $reportDetailBaseUrl . '?' . http_build_query([
                          'date_from' => $d,
                          'date_to' => $d,
                          'status' => $status,
                          'purchase_type_id' => $typeId,
                          'per_page' => 50,
                          'page' => 1,
                        ]);
                        echo '<div><a href="' . html_escape($link) . '">' . html_escape($d) . '</a> | PO ' . number_format((int)($c['total_po'] ?? 0)) . ' | Rp ' . number_format($v, 2, ',', '.') . '</div>';
                        $productRows = (array)($matrixDetailMap[$typeId][$d] ?? []);
                        if (!empty($productRows)) {
                          echo '<div style="padding-left:10px;">';
                          foreach ($productRows as $pr) {
                            echo '<div>- ' . html_escape((string)($pr['name'] ?? '-')) . ' | '
                              . number_format((float)($pr['qty_buy'] ?? 0), 2, ',', '.') . ' '
                              . html_escape((string)($pr['buy_uom_code'] ?? '-')) . ' | Rp '
                              . number_format((float)($pr['line_subtotal'] ?? 0), 2, ',', '.') . '</div>';
                          }
                          echo '</div>';
                        }
                      }
                      if (!$hasExpand) {
                        echo '<div class="text-muted">Tidak ada transaksi di rentang tanggal.</div>';
                      }
                    ?>
                  </div>
                </details>
              </td>
              <?php foreach ($matrixDates as $d): ?>
                <?php $c = (array)($cells[$d] ?? []); $v = (float)($c['total_value'] ?? 0); ?>
                <td class="date-cell"><?php echo $v > 0 ? number_format($v, 0, ',', '.') : '-'; ?></td>
              <?php endforeach; ?>
              <td class="date-cell fw-semibold"><?php echo number_format((float)($row['total_value'] ?? 0), 0, ',', '.'); ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($reportTab === 'ringkasan'): ?>
<script>
(function () {
  const rows = <?php echo json_encode(array_values($dailyRows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || [];
  const tbody = document.getElementById('purDailyRows');
  const pagerText = document.getElementById('purDailyPagerText');
  const pagerButtons = document.getElementById('purDailyPagerButtons');
  const detailBaseUrl = <?php echo json_encode($reportDetailBaseUrl); ?>;
  const state = { page: 1, perPage: 15 };
  const numberFmt = new Intl.NumberFormat('id-ID');
  const decimalFmt = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function buildDetailUrl(row) {
    const params = new URLSearchParams();
    params.set('date_from', row.request_date || '');
    params.set('date_to', row.request_date || '');
    params.set('status', <?php echo json_encode($status); ?> || 'ALL');
    params.set('purchase_type_id', String(Number(row.purchase_type_id || 0)));
    params.set('per_page', '50');
    params.set('page', '1');
    return detailBaseUrl + '?' + params.toString();
  }

  function renderRows() {
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Belum ada data.</td></tr>';
      pagerText.textContent = 'Tidak ada data';
      pagerButtons.innerHTML = '';
      return;
    }

    const totalPages = Math.max(1, Math.ceil(rows.length / state.perPage));
    if (state.page > totalPages) {
      state.page = totalPages;
    }
    const start = (state.page - 1) * state.perPage;
    const end = Math.min(rows.length, start + state.perPage);
    const pageRows = rows.slice(start, end);

    tbody.innerHTML = pageRows.map(function (row) {
      const detailUrl = buildDetailUrl(row);
      return '<tr>'
        + '<td><a class="pur-report-link" href="' + escapeHtml(detailUrl) + '">' + escapeHtml(row.request_date || '-') + '</a></td>'
        + '<td><a class="pur-report-link" href="' + escapeHtml(detailUrl) + '">' + escapeHtml(row.purchase_type_name || '-') + '</a></td>'
        + '<td class="text-end">' + numberFmt.format(Number(row.total_po || 0)) + '</td>'
        + '<td class="text-end">' + decimalFmt.format(Number(row.total_qty_buy || 0)) + '</td>'
        + '<td class="text-end"><a class="pur-report-link" href="' + escapeHtml(detailUrl) + '">Rp ' + decimalFmt.format(Number(row.total_value || 0)) + '</a></td>'
        + '</tr>';
    }).join('');

    pagerText.textContent = 'Menampilkan ' + numberFmt.format(start + 1) + '-' + numberFmt.format(end) + ' dari ' + numberFmt.format(rows.length) + ' baris';
    const buttons = [];
    buttons.push('<button type="button" class="btn btn-sm btn-outline-secondary" data-page="' + Math.max(1, state.page - 1) + '" ' + (state.page <= 1 ? 'disabled' : '') + '>Prev</button>');
    const firstPage = Math.max(1, state.page - 2);
    const lastPage = Math.min(totalPages, state.page + 2);
    for (let p = firstPage; p <= lastPage; p += 1) {
      buttons.push('<button type="button" class="btn btn-sm ' + (p === state.page ? 'btn-primary' : 'btn-outline-secondary') + '" data-page="' + p + '">' + p + '</button>');
    }
    buttons.push('<button type="button" class="btn btn-sm btn-outline-secondary" data-page="' + Math.min(totalPages, state.page + 1) + '" ' + (state.page >= totalPages ? 'disabled' : '') + '>Next</button>');
    pagerButtons.innerHTML = buttons.join('');
    pagerButtons.querySelectorAll('button[data-page]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (btn.disabled) {
          return;
        }
        state.page = Number(btn.getAttribute('data-page') || 1);
        renderRows();
      });
    });
  }

  renderRows();
})();
</script>
<?php endif; ?>
