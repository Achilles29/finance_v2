<?php
$filters   = is_array($filters ?? null) ? $filters : [];
$rows      = is_array($rows ?? null) ? $rows : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$locationFilterOptions = ['' => 'Semua Lokasi', 'REGULER' => 'Reguler', 'EVENT' => 'Event'];
$locationGroupLabel = static function ($locationType): string {
  $value = strtoupper(trim((string)$locationType));
  if ($value === 'BAR' || $value === 'KITCHEN' || $value === 'ROASTERY') return 'Reguler';
  if ($value === 'BAR_EVENT' || $value === 'KITCHEN_EVENT' || $value === 'ROASTERY_EVENT') return 'Event';
  return $value !== '' ? $value : '-';
};
$divisionLabel = static function (array $row): string {
  $code = trim((string)($row['division_code'] ?? $row['code'] ?? ''));
  $name = trim((string)($row['division_name'] ?? $row['name'] ?? ''));
  if ($code !== '' && $name !== '') return $code . ' - ' . $name;
  return $name !== '' ? $name : ($code !== '' ? $code : '-');
};
$dateFrom  = (string)($filters['date_from'] ?? date('Y-m-01'));
$dateTo    = (string)($filters['date_to']   ?? date('Y-m-d'));
$perPage   = in_array((int)($filters['per_page'] ?? 25), [10, 25, 50, 100], true) ? (int)$filters['per_page'] : 25;
$selectedStatus = (string)($filters['status'] ?? 'OPEN');
$selectedLoc    = (string)($filters['location_type'] ?? '');
$selectedDiv    = (int)($filters['division_id'] ?? 0);
$searchVal      = (string)($filters['q'] ?? '');

// — Summary computations —
$totalRows    = count($rows);
$cntOpen      = 0; $cntClosed = 0; $cntVoid = 0;
$qtyInTotal   = 0.0; $qtyOutTotal = 0.0; $qtyBalance = 0.0;
$valueBalance = 0.0; $valueOut = 0.0;
$uniqueComponents = []; $byDivision = [];
foreach ($rows as $r) {
  $st  = strtoupper((string)($r['status'] ?? ''));
  $bal = (float)($r['qty_balance'] ?? 0);
  $uc  = (float)($r['unit_cost'] ?? 0);
  $qin = (float)($r['qty_in_total'] ?? 0);
  $qout= (float)($r['qty_out_total'] ?? 0);
  $cid = (string)($r['component_id'] ?? '');
  $dn  = (string)($r['division_name'] ?? '-');
  if ($st === 'OPEN')   { $cntOpen++;   $valueBalance += $bal * $uc; }
  elseif ($st === 'CLOSED') $cntClosed++;
  elseif ($st === 'VOID')   $cntVoid++;
  $qtyInTotal  += $qin;
  $qtyOutTotal += $qout;
  $qtyBalance  += $bal;
  $valueOut    += $qout * $uc;
  if ($cid !== '') $uniqueComponents[$cid] = true;
  $byDivision[$dn] = ($byDivision[$dn] ?? 0.0) + $bal * $uc;
}
$utilisasi = $qtyInTotal > 0 ? round(($qtyOutTotal / $qtyInTotal) * 100, 1) : 0;
arsort($byDivision);
$topDivisions = array_slice($byDivision, 0, 4, true);
$uniqueComponentCount = count($uniqueComponents);
?>

<style>
.component-lot-filter-card { border:1px solid rgba(226,212,200,.88);border-radius:16px;box-shadow:0 4px 14px rgba(58,38,30,.05); }
.component-lot-table-card  { border:1px solid rgba(226,212,200,.88);border-radius:18px;box-shadow:0 14px 30px rgba(58,38,30,.06); }
.lot-tbl-wrap { overflow:auto;max-height:72vh; }
.lot-tbl { table-layout:fixed;min-width:1050px;margin-bottom:0;border-collapse:separate;border-spacing:0; }
.lot-tbl thead th {
  position:sticky;top:0;z-index:4;
  background:linear-gradient(180deg,#7c1f2d 0%,#9f2f3e 100%);
  color:#fff8f5;border-bottom:1px solid #7f2936;white-space:nowrap;font-size:.78rem;
}
.lot-tbl td,.lot-tbl th { vertical-align:middle;border-right:1px solid #efddd2;border-bottom:1px solid #f3e4da;font-size:.78rem; }
.lot-tbl tbody td { background:#fff; }
.lot-tbl tbody tr:nth-child(even) td { background:#fffaf6; }
.lot-link-btn { font-size:.66rem;padding:.1rem .45rem;border-radius:999px;white-space:nowrap; }
.utilbar { height:6px;border-radius:3px;background:#e5e5e5;overflow:hidden;margin-top:.3rem; }
.utilbar-fill { height:100%;border-radius:3px;background:linear-gradient(90deg,#b5cca0,#4a9e2e); }
</style>

<div class="mb-3">
  <h4 class="mb-1"><i class="ri ri-stack-line page-title-icon"></i><?php echo html_escape((string)($page_title ?? 'Lot FIFO Base/Prepare')); ?></h4>
  <small class="text-muted">Ledger lot output component FIFO — <?php echo html_escape($dateFrom); ?> s/d <?php echo html_escape($dateTo); ?>, <?php echo $totalRows; ?> lot ditemukan.</small>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'lot']); ?>
<?php $this->load->view('production/_component_type_tabs', [
  'component_type_base_url' => site_url('production/component-lots'),
  'component_type_filters'  => $filters,
  'component_type_active'   => (string)($filters['type'] ?? ''),
]); ?>
<?php $this->load->view('production/_component_action_buttons', [
  'component_action_params' => array_filter([
    'date_from'     => $dateFrom,
    'division_id'   => $selectedDiv ?: '',
    'location_type' => $selectedLoc,
  ], static fn($v) => $v !== '' && $v !== 0 && $v !== '0'),
]); ?>

<!-- Filter -->
<div class="card mb-3 component-lot-filter-card border-0">
  <div class="card-body py-2">
    <form method="get" action="<?php echo site_url('production/component-lots'); ?>" class="row g-2 align-items-end">
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Dari</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo html_escape($dateFrom); ?>">
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Sampai</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo html_escape($dateTo); ?>">
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Status</label>
        <select name="status" class="form-select form-select-sm" style="min-width:90px">
          <?php foreach (['OPEN' => 'Open', 'CLOSED' => 'Closed', 'VOID' => 'Void', 'ALL' => 'Semua'] as $k => $l): ?>
            <option value="<?php echo $k; ?>" <?php echo $selectedStatus === $k ? 'selected' : ''; ?>><?php echo $l; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Lokasi</label>
        <select name="location_type" class="form-select form-select-sm" style="min-width:100px">
          <?php foreach ($locationFilterOptions as $key => $label): ?>
            <option value="<?php echo html_escape($key); ?>" <?php echo $selectedLoc === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Divisi</label>
        <select name="division_id" class="form-select form-select-sm" style="min-width:100px">
          <option value="0">Semua</option>
          <?php foreach ($divisions as $division): ?>
            <?php $oid = (int)($division['id'] ?? 0); ?>
            <option value="<?php echo $oid; ?>" <?php echo $selectedDiv === $oid ? 'selected' : ''; ?>><?php echo html_escape($divisionLabel((array)$division)); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Per Hal</label>
        <select name="per_page" class="form-select form-select-sm" style="min-width:70px">
          <?php foreach ([10, 25, 50, 100] as $pp): ?>
            <option value="<?php echo $pp; ?>" <?php echo $perPage === $pp ? 'selected' : ''; ?>><?php echo $pp; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Cari</label>
        <input type="text" name="q" id="lot-search" class="form-control form-control-sm" value="<?php echo html_escape($searchVal); ?>" placeholder="Lot / batch / component" style="min-width:165px">
      </div>
      <div class="col-auto d-flex gap-1">
        <button type="submit" class="btn btn-outline-secondary btn-sm">Filter</button>
        <a href="<?php echo site_url('production/component-lots'); ?>" class="btn btn-outline-danger btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<?php
$topLotDivision = '-';
$topLotDivisionValue = 0.0;
if (!empty($topDivisions)) {
  $topLotDivision = (string)array_key_first($topDivisions);
  $topLotDivisionValue = (float)($topDivisions[$topLotDivision] ?? 0);
}
$this->load->view('layout/_stock_summary_cards', [
  'stock_summary_label' => 'Ringkasan lot FIFO komponen',
  'stock_summary_cards' => [
    ['label' => 'Lot Open', 'value' => number_format($cntOpen, 0, ',', '.'), 'detail' => $cntClosed . ' closed · ' . $cntVoid . ' void', 'tone' => 'violet', 'icon' => 'ri-stack-line'],
    ['label' => 'Saldo Qty', 'value' => number_format($qtyBalance, 2, ',', '.'), 'detail' => 'tersisa di lot open', 'tone' => 'amber', 'icon' => 'ri-scales-3-line'],
    ['label' => 'Nilai Saldo', 'value' => 'Rp ' . number_format($valueBalance, 0, ',', '.'), 'detail' => 'lot open × unit cost', 'tone' => 'teal', 'icon' => 'ri-money-dollar-circle-line'],
    ['label' => 'Sudah Terpakai', 'value' => number_format($qtyOutTotal, 2, ',', '.'), 'detail' => 'total qty_out semua lot', 'tone' => 'blue', 'icon' => 'ri-arrow-up-circle-line'],
    ['label' => 'Utilisasi', 'value' => number_format($utilisasi, 1, ',', '.') . '%', 'detail' => 'qty out / qty in', 'tone' => 'amber', 'icon' => 'ri-speed-up-line'],
    ['label' => 'Top Divisi', 'value' => $topLotDivision, 'detail' => $topLotDivisionValue > 0 ? 'Rp ' . number_format($topLotDivisionValue, 0, ',', '.') : 'belum ada nilai lot', 'tone' => 'aqua', 'icon' => 'ri-building-line'],
  ],
]); ?>

<!-- Table -->
<div class="card component-lot-table-card border-0">
  <div class="lot-tbl-wrap">
    <table class="table table-hover lot-tbl">
      <thead>
        <tr>
          <th style="width:162px">Lot No</th>
          <th style="width:88px">Tgl Masuk</th>
          <th style="width:162px">Component</th>
          <th style="width:100px">Divisi / Lokasi</th>
          <th style="width:82px" class="text-end">Qty In</th>
          <th style="width:82px" class="text-end">Qty Out</th>
          <th style="width:82px" class="text-end">Saldo</th>
          <th style="width:85px" class="text-end">Unit Cost</th>
          <th style="width:128px">Sumber</th>
          <th style="width:68px">Status</th>
        </tr>
      </thead>
      <tbody id="lot-tbody">
        <?php if (empty($rows)): ?>
          <tr class="lot-row"><td colspan="10" class="text-center text-muted py-4">Belum ada lot component pada filter ini.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
              $rowStatus  = strtoupper((string)($row['status'] ?? 'OPEN'));
              $searchStr  = strtolower(implode(' ', [
                $row['lot_no'] ?? '',
                $row['batch_no'] ?? '',
                $row['component_name'] ?? '',
                $row['component_code'] ?? '',
                $row['division_name'] ?? '',
                $locationGroupLabel((string)($row['location_type'] ?? '')),
                $row['source_module'] ?? '',
                $rowStatus,
              ]));
              $isOpen   = $rowStatus === 'OPEN';
              $balQty   = (float)($row['qty_balance'] ?? 0);
              $utilPct  = (float)($row['qty_in_total'] ?? 0) > 0
                ? min(100, round((float)($row['qty_out_total'] ?? 0) / (float)$row['qty_in_total'] * 100, 0))
                : 0;
            ?>
            <tr class="lot-row" data-search="<?php echo html_escape($searchStr); ?>">
              <td style="overflow:hidden">
                <div class="fw-bold" style="font-size:.77rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?php echo html_escape((string)($row['lot_no'] ?? '-')); ?>"><?php echo html_escape((string)($row['lot_no'] ?? '-')); ?></div>
                <?php if (!empty($row['batch_no'])): ?>
                  <div class="text-muted" style="font-size:.67rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo html_escape((string)$row['batch_no']); ?></div>
                <?php endif; ?>
              </td>
              <td style="font-size:.77rem;white-space:nowrap;overflow:hidden"><?php echo html_escape((string)($row['receipt_date'] ?? '-')); ?></td>
              <td style="overflow:hidden">
                <div class="fw-semibold" style="font-size:.77rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo html_escape((string)($row['component_name'] ?? '-')); ?>"><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></div>
                <div class="text-muted" style="font-size:.67rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo html_escape((string)($row['component_type'] ?? '')); ?><?php if (!empty($row['uom_code'])): ?> · <?php echo html_escape((string)$row['uom_code']); ?><?php endif; ?></div>
              </td>
              <td style="overflow:hidden">
                <div style="font-size:.77rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo html_escape((string)($row['division_name'] ?? '-')); ?>"><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></div>
                <div class="text-muted" style="font-size:.67rem;white-space:nowrap;overflow:hidden"><?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?></div>
              </td>
              <td class="text-end" style="font-size:.77rem;white-space:nowrap;overflow:hidden"><?php echo number_format((float)($row['qty_in_total'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end" style="font-size:.77rem;white-space:nowrap;overflow:hidden"><?php echo number_format((float)($row['qty_out_total'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end" style="overflow:hidden">
                <div class="fw-bold <?php echo $isOpen && $balQty > 0 ? 'text-success' : 'text-muted'; ?>" style="font-size:.8rem;white-space:nowrap"><?php echo number_format($balQty, 2, ',', '.'); ?></div>
                <?php if ($utilPct > 0): ?>
                  <div class="utilbar"><div class="utilbar-fill" style="width:<?php echo $utilPct; ?>%"></div></div>
                <?php endif; ?>
              </td>
              <td class="text-end" style="font-size:.77rem;white-space:nowrap;overflow:hidden"><?php echo number_format((float)($row['unit_cost'] ?? 0), 2, ',', '.'); ?></td>
              <td style="overflow:hidden">
                <div class="text-muted" style="font-size:.67rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo html_escape((string)($row['source_module'] ?? '-')); ?></div>
                <div class="d-flex gap-1 flex-wrap mt-1">
                  <a href="<?php echo html_escape(site_url('production/component-lots/usage/' . (int)($row['id'] ?? 0))); ?>" class="btn btn-outline-primary lot-link-btn">Pakai</a>
                  <?php if ((string)($row['source_table'] ?? '') === 'inv_component_batch' && !empty($row['source_id'])): ?>
                    <a href="<?php echo site_url('production/component-batches/detail/' . (int)$row['source_id']); ?>" class="btn btn-outline-secondary lot-link-btn"><?php echo html_escape(substr((string)($row['batch_no'] ?? ('B#' . (int)$row['source_id'])), 0, 14)); ?></a>
                  <?php endif; ?>
                </div>
              </td>
              <td><?php echo ui_status_badge($rowStatus); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="d-flex justify-content-between align-items-center px-3 py-2 flex-wrap gap-2">
    <div id="lot-info" class="text-muted" style="font-size:.78rem"></div>
    <div id="lot-pager" class="d-flex gap-1 flex-wrap"></div>
  </div>
</div>

<script>
(() => {
  const perPage = <?php echo (int)$perPage; ?>;

  function buildPager(pagerId, infoId, total, page, onClick) {
    const pager = document.getElementById(pagerId);
    const info  = document.getElementById(infoId);
    if (!pager) return;
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    if (info) {
      const s = total > 0 ? ((page - 1) * perPage + 1) : 0;
      const e = Math.min(page * perPage, total);
      info.textContent = total > 0
        ? ('Menampilkan ' + s.toLocaleString('id-ID') + '–' + e.toLocaleString('id-ID') + ' dari ' + total.toLocaleString('id-ID') + ' lot')
        : 'Tidak ada data';
    }
    if (totalPages <= 1) { pager.innerHTML = ''; return; }
    const pages = new Set([1]);
    for (let p = Math.max(2, page - 1); p <= Math.min(totalPages - 1, page + 1); p++) pages.add(p);
    pages.add(totalPages);
    let html = '', last = 0;
    for (const p of pages) {
      if (last > 0 && p - last > 1) html += '<span class="btn btn-sm btn-light disabled px-2">…</span>';
      html += '<button class="btn btn-sm ' + (p === page ? 'btn-dark' : 'btn-outline-secondary') + ' px-2" data-p="' + p + '">' + p + '</button>';
      last = p;
    }
    pager.innerHTML = html;
    pager.querySelectorAll('button[data-p]').forEach(btn => btn.addEventListener('click', () => onClick(+btn.dataset.p)));
  }

  function applyFilter(searchVal, page) {
    const allRows = Array.from(document.querySelectorAll('#lot-tbody tr.lot-row'));
    const needle  = searchVal.trim().toLowerCase();
    const visible = allRows.filter(r => !needle || (r.dataset.search || '').includes(needle));
    allRows.forEach(r => { r.style.display = 'none'; });
    const start = (page - 1) * perPage;
    visible.forEach((r, i) => { r.style.display = (i >= start && i < start + perPage) ? '' : 'none'; });
    buildPager('lot-pager', 'lot-info', visible.length, page, p => applyFilter(searchVal, p));
  }

  const searchInput = document.getElementById('lot-search');
  searchInput?.addEventListener('input', () => applyFilter(searchInput.value, 1));
  applyFilter(searchInput ? searchInput.value : '', 1);
})();
</script>
