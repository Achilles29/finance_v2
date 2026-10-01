<?php
$filters   = is_array($filters ?? null) ? $filters : [];
$rows      = is_array($rows ?? null) ? $rows : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$generateUrl = isset($generate_url) ? $generate_url : site_url('production/component-openings/generate-monthly');
$perPage   = max(10, (int)($filters['per_page'] ?? 25));

$locationFilterOptions = ['' => 'Semua Lokasi', 'REGULER' => 'Reguler', 'EVENT' => 'Event'];

$fmtQty = static function ($v): string {
  $f = round((float)$v, 2);
  return $f == 0 ? '<span class="text-muted">-</span>' : number_format($f, 2, ',', '.');
};
$fmtVal = static function ($v): string {
  $f = round((float)$v, 0);
  return $f == 0 ? '<span class="text-muted">-</span>' : number_format($f, 0, ',', '.');
};
$fmtRp = static function ($v): string {
  $f = round((float)$v, 0);
  return 'Rp ' . number_format($f, 0, ',', '.');
};

$typeLabel = static function (string $t): string {
  return $t === 'BASE'
    ? '<span class="badge bg-primary-subtle text-primary">Base</span>'
    : ($t === 'PREPARE'
      ? '<span class="badge bg-warning-subtle text-warning">Prepare</span>'
      : '<span class="badge bg-secondary-subtle text-secondary">' . htmlspecialchars($t) . '</span>');
};
$jenisBadges = static function (string $locationType, string $componentType) use (&$typeLabel): string {
  $loc      = strtoupper($locationType);
  $isEvent  = strpos($loc, '_EVENT') !== false;
  $baseLoc  = $isEvent ? str_replace('_EVENT', '', $loc) : $loc;
  $baseColor = $baseLoc === 'BAR' ? 'info' : ($baseLoc === 'KITCHEN' ? 'success' : 'secondary');
  $catBadge  = $isEvent
    ? '<span class="badge bg-danger-subtle text-danger" style="font-size:.65rem">EVENT</span>'
    : '<span class="badge bg-light text-secondary border" style="font-size:.65rem">REGULER</span>';
  return '<span class="badge bg-' . $baseColor . '-subtle text-' . $baseColor . '">' . htmlspecialchars($baseLoc) . '</span>'
    . $catBadge
    . $typeLabel($componentType);
};

// ── Summary analytics ──────────────────────────────────────────
$totalNilai     = array_sum(array_column($rows, 'total_value'));
$totalClosingQty= array_sum(array_column($rows, 'closing_qty'));
$totalMasuk     = array_sum(array_column($rows, 'in_qty'));
$totalKeluar    = array_sum(array_column($rows, 'out_qty'));
$totalWaste     = array_sum(array_column($rows, 'waste_qty'));
$totalSpoil     = array_sum(array_column($rows, 'spoil_qty'));
$totalAdjPlus   = array_sum(array_column($rows, 'adjustment_plus_qty'));
$totalAdjMinus  = array_sum(array_column($rows, 'adjustment_minus_qty'));
$netAdj         = $totalAdjPlus - $totalAdjMinus;
$totalOut       = $totalKeluar + $totalWaste + $totalSpoil;
$totalIn        = array_sum(array_column($rows, 'opening_qty')) + $totalMasuk;
$utilisasi      = $totalIn > 0 ? min(100, round($totalOut / $totalIn * 100, 1)) : 0;
$wasteRatio     = $totalOut > 0 ? round(($totalWaste + $totalSpoil) / $totalOut * 100, 1) : 0;
$adaStok        = count(array_filter($rows, static fn($r) => (float)($r['closing_qty'] ?? 0) > 0));
$kosong         = count($rows) - $adaStok;
$pctAda         = count($rows) > 0 ? round($adaStok / count($rows) * 100) : 0;

// Nilai per lokasi
$nilaiByLoc = [];
foreach ($rows as $r) {
  $loc = (string)($r['location_type'] ?? 'OTHER');
  $nilaiByLoc[$loc] = ($nilaiByLoc[$loc] ?? 0) + (float)($r['total_value'] ?? 0);
}
arsort($nilaiByLoc);
$topLocName  = key($nilaiByLoc) ?? '-';
$topLocVal   = reset($nilaiByLoc) ?: 0;
$locSharePct = $totalNilai > 0 ? round($topLocVal / $totalNilai * 100) : 0;
?>

<div class="mb-3">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
    <div>
      <h4 class="mb-1"><i class="ri ri-file-list-3-line page-title-icon"></i><?php echo html_escape($page_title ?? 'Stok Opname Bulanan Component'); ?></h4>
      <small class="text-muted">Snapshot stok component hasil generate opname bulanan — posisi akhir bulan per lokasi &amp; divisi.</small>
    </div>
    <button
      type="button"
      class="btn btn-sm btn-outline-danger js-component-generate-trigger"
      id="btn-generate-opname"
      data-month="<?php echo html_escape((string)($filters['month'] ?? date('Y-m'))); ?>"
      data-location-type="<?php echo html_escape((string)($filters['location_type'] ?? '')); ?>"
      data-division-id="<?php echo html_escape((string)($filters['division_id'] ?? '')); ?>"
      data-bs-toggle="modal"
      data-bs-target="#componentGenerateModal">
      <i class="ri ri-refresh-line me-1"></i>Generate Opname + Stok Awal
    </button>
  </div>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'opname']); ?>
<?php $this->load->view('production/_component_type_tabs', [
  'component_type_base_url' => site_url('production/component-opname'),
  'component_type_filters'  => $filters,
  'component_type_active'   => (string)($filters['type'] ?? ''),
]); ?>
<?php $this->load->view('production/_component_action_buttons', [
  'component_action_params' => array_filter([
    'month'         => (string)($filters['month'] ?? ''),
    'division_id'   => !empty($filters['division_id']) ? (int)$filters['division_id'] : '',
    'location_type' => (string)($filters['location_type'] ?? ''),
  ], static fn($v) => $v !== '' && $v !== 0 && $v !== '0'),
]); ?>

<?php if (!empty($rows)): ?>
<!-- ── Summary Cards ─────────────────────────────────────────── -->
<div class="row g-2 mb-3">

  <!-- Total Nilai Stok -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body p-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="rounded-2 p-1" style="background:#e8f4fd"><i class="ri ri-money-dollar-circle-line text-primary" style="font-size:1.1rem"></i></span>
          <span class="text-muted" style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Nilai Stok</span>
        </div>
        <div class="fw-bold" style="font-size:1.05rem;color:#1565c0"><?php echo 'Rp ' . number_format(round($totalNilai / 1000000, 2), 2, ',', '.') . ' jt'; ?></div>
        <div class="text-muted" style="font-size:.72rem"><?php echo count($rows); ?> baris opname</div>
      </div>
    </div>
  </div>

  <!-- Coverage Stok -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body p-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="rounded-2 p-1" style="background:#e8f5e9"><i class="ri ri-checkbox-circle-line text-success" style="font-size:1.1rem"></i></span>
          <span class="text-muted" style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Ada Stok</span>
        </div>
        <div class="fw-bold" style="font-size:1.05rem;color:#2e7d32"><?php echo $adaStok; ?> <span class="fw-normal text-muted" style="font-size:.78rem">/ <?php echo count($rows); ?></span></div>
        <div style="height:4px;background:#e8f5e9;border-radius:2px;margin:4px 0 2px">
          <div style="height:4px;background:#43a047;border-radius:2px;width:<?php echo $pctAda; ?>%"></div>
        </div>
        <div class="text-muted" style="font-size:.72rem"><?php echo $pctAda; ?>% — <?php echo $kosong; ?> kosong</div>
      </div>
    </div>
  </div>

  <!-- Utilisasi Bulan -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body p-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="rounded-2 p-1" style="background:#fff8e1"><i class="ri ri-speed-up-line text-warning" style="font-size:1.1rem"></i></span>
          <span class="text-muted" style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Utilisasi</span>
        </div>
        <div class="fw-bold" style="font-size:1.05rem;color:#f57f17"><?php echo $utilisasi; ?>%</div>
        <div class="text-muted" style="font-size:.72rem">dari stok tersedia</div>
      </div>
    </div>
  </div>

  <!-- Waste + Spoil ratio -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body p-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="rounded-2 p-1" style="background:#fce4ec"><i class="ri ri-delete-bin-3-line text-danger" style="font-size:1.1rem"></i></span>
          <span class="text-muted" style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Waste + Spoil</span>
        </div>
        <div class="fw-bold" style="font-size:1.05rem;color:<?php echo $wasteRatio > 10 ? '#c62828' : ($wasteRatio > 5 ? '#e53935' : '#546e7a'); ?>">
          <?php echo $wasteRatio; ?>%
        </div>
        <div class="text-muted" style="font-size:.72rem"><?php echo number_format($totalWaste + $totalSpoil, 2, ',', '.'); ?> unit dari total out</div>
      </div>
    </div>
  </div>

  <!-- Net Adjustment -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body p-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="rounded-2 p-1" style="background:#f3e5f5"><i class="ri ri-equalizer-2-line" style="font-size:1.1rem;color:#7b1fa2"></i></span>
          <span class="text-muted" style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Net Adj</span>
        </div>
        <div class="fw-bold" style="font-size:1.05rem;color:<?php echo $netAdj >= 0 ? '#2e7d32' : '#c62828'; ?>">
          <?php echo ($netAdj >= 0 ? '+' : '') . number_format($netAdj, 2, ',', '.'); ?>
        </div>
        <div class="text-muted" style="font-size:.72rem">+<?php echo number_format($totalAdjPlus, 2, ',', '.'); ?> / −<?php echo number_format($totalAdjMinus, 2, ',', '.'); ?></div>
      </div>
    </div>
  </div>

  <!-- Lokasi Dominan -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body p-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="rounded-2 p-1" style="background:#e3f2fd"><i class="ri ri-map-pin-2-line" style="font-size:1.1rem;color:#1565c0"></i></span>
          <span class="text-muted" style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Top Lokasi</span>
        </div>
        <div class="fw-bold" style="font-size:1rem;color:#37474f"><?php echo html_escape($topLocName); ?></div>
        <div class="text-muted" style="font-size:.72rem"><?php echo $locSharePct; ?>% dari total nilai</div>
        <?php if (count($nilaiByLoc) > 1): $bars = []; $i = 0; foreach ($nilaiByLoc as $ln => $lv): if ($i++ > 2) break;
          $bars[] = '<span class="fw-semibold" style="font-size:.68rem">' . html_escape($ln) . '</span> <span class="text-muted" style="font-size:.68rem">' . round($lv/1e6,1) . 'jt</span>';
        endforeach; ?>
        <div class="mt-1" style="font-size:.68rem"><?php echo implode(' · ', $bars); ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>
<?php endif; ?>

<!-- ── Filter ─────────────────────────────────────────────────── -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="get" action="<?php echo site_url('production/component-opname'); ?>" class="row g-2 align-items-end">
      <div class="col-6 col-md-2">
        <label class="form-label mb-1 small">Bulan</label>
        <input type="month" name="month" class="form-control form-control-sm" value="<?php echo html_escape((string)($filters['month'] ?? date('Y-m'))); ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1 small">Divisi</label>
        <select name="division_id" class="form-select form-select-sm">
          <option value="0">Semua</option>
          <?php foreach ($divisions as $div): ?>
            <option value="<?php echo (int)($div['id'] ?? 0); ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)($div['id'] ?? 0)) ? 'selected' : ''; ?>>
              <?php echo html_escape(trim((string)($div['division_code'] ?? $div['code'] ?? '')) . ' - ' . trim((string)($div['division_name'] ?? $div['name'] ?? ''))); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1 small">Lokasi</label>
        <select name="location_type" class="form-select form-select-sm">
          <?php foreach ($locationFilterOptions as $key => $label): ?>
            <option value="<?php echo html_escape($key); ?>" <?php echo ((string)($filters['location_type'] ?? '') === $key) ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label mb-1 small">Cari</label>
        <input type="text" id="opname-q" name="q" class="form-control form-control-sm" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama component…" autocomplete="off">
      </div>
      <div class="col-6 col-md-1">
        <label class="form-label mb-1 small">Per hal</label>
        <select name="per_page" class="form-select form-select-sm" id="opname-per-page">
          <?php foreach ([10, 25, 50, 100] as $pp): ?>
            <option value="<?php echo $pp; ?>" <?php echo $perPage === $pp ? 'selected' : ''; ?>><?php echo $pp; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
      </div>
    </form>
  </div>
</div>

<?php if (empty($rows)): ?>
  <div class="alert alert-info">
    Belum ada data opname untuk bulan <?php echo html_escape((string)($filters['month'] ?? date('Y-m'))); ?>.
    Klik <strong>Generate Opname + Stok Awal</strong> untuk membuat snapshot.
  </div>
<?php else: ?>

<!-- ── Table ──────────────────────────────────────────────────── -->
<div class="card">
  <div class="card-body p-0">
    <div id="opname-table-wrap" style="overflow:auto;max-height:70vh">
      <table class="table table-sm table-hover align-middle small mb-0" style="table-layout:fixed;min-width:950px">
        <thead class="table-light" style="position:sticky;top:0;z-index:4">
          <tr>
            <th style="width:160px">Jenis</th>
            <th style="width:180px">Component</th>
            <th style="width:52px">UOM</th>
            <th class="text-end" style="width:76px">Opening</th>
            <th class="text-end" style="width:76px">Masuk</th>
            <th class="text-end" style="width:76px">Keluar</th>
            <th class="text-end" style="width:62px">Waste</th>
            <th class="text-end" style="width:62px">Spoil</th>
            <th class="text-end" style="width:62px">Adj+</th>
            <th class="text-end" style="width:62px">Adj-</th>
            <th class="text-end" style="width:80px">Closing</th>
            <th class="text-end" style="width:96px">Total Nilai</th>
            <th style="width:110px">Di-generate</th>
          </tr>
        </thead>
        <tbody id="opname-tbody">
          <?php foreach ($rows as $row):
            $search = implode(' ', [
              strtolower((string)($row['component_name'] ?? '')),
              strtolower((string)($row['division_name'] ?? '')),
              strtolower((string)($row['location_type'] ?? '')),
              strtolower((string)($row['component_type'] ?? '')),
            ]);
            $closingQty = (float)($row['closing_qty'] ?? 0);
          ?>
          <tr data-search="<?php echo html_escape($search); ?>">
            <td>
              <div class="d-flex flex-column gap-1">
                <?php echo $jenisBadges((string)($row['location_type'] ?? ''), (string)($row['component_type'] ?? '')); ?>
              </div>
            </td>
            <td>
              <div class="fw-semibold" style="font-size:.82rem;line-height:1.3"><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></div>
            </td>
            <td class="text-muted"><?php echo html_escape((string)($row['uom_code'] ?? $row['uom_name'] ?? '-')); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['opening_qty'] ?? 0); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['in_qty'] ?? 0); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['out_qty'] ?? 0); ?></td>
            <td class="text-end <?php echo (float)($row['waste_qty'] ?? 0) > 0 ? 'text-danger' : ''; ?>"><?php echo $fmtQty($row['waste_qty'] ?? 0); ?></td>
            <td class="text-end <?php echo (float)($row['spoil_qty'] ?? 0) > 0 ? 'text-warning' : ''; ?>"><?php echo $fmtQty($row['spoil_qty'] ?? 0); ?></td>
            <td class="text-end text-success"><?php echo $fmtQty($row['adjustment_plus_qty'] ?? 0); ?></td>
            <td class="text-end text-danger"><?php echo $fmtQty($row['adjustment_minus_qty'] ?? 0); ?></td>
            <td class="text-end fw-semibold <?php echo $closingQty < 0 ? 'text-danger' : ($closingQty == 0 ? 'text-muted' : ''); ?>">
              <?php echo $fmtQty($closingQty); ?>
            </td>
            <td class="text-end"><?php echo $fmtVal($row['total_value'] ?? 0); ?></td>
            <td class="text-muted" style="font-size:.72rem;white-space:nowrap">
              <?php if (!empty($row['generated_by_name'])): ?>
                <div><?php echo html_escape((string)$row['generated_by_name']); ?></div>
              <?php endif; ?>
              <?php if (!empty($row['generated_at'])): ?>
                <div style="font-size:.68rem"><?php echo html_escape(substr((string)$row['generated_at'], 0, 16)); ?></div>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light" style="position:sticky;bottom:0;z-index:3">
          <tr>
            <td colspan="3" class="fw-semibold small">Total</td>
            <td class="text-end fw-semibold small"><?php echo number_format($totalIn - $totalMasuk, 2, ',', '.'); ?></td>
            <td class="text-end fw-semibold small"><?php echo number_format($totalMasuk, 2, ',', '.'); ?></td>
            <td class="text-end fw-semibold small"><?php echo number_format($totalKeluar, 2, ',', '.'); ?></td>
            <td class="text-end fw-semibold small text-danger"><?php echo number_format($totalWaste, 2, ',', '.'); ?></td>
            <td class="text-end fw-semibold small text-warning"><?php echo number_format($totalSpoil, 2, ',', '.'); ?></td>
            <td class="text-end fw-semibold small text-success"><?php echo number_format($totalAdjPlus, 2, ',', '.'); ?></td>
            <td class="text-end fw-semibold small text-danger"><?php echo number_format($totalAdjMinus, 2, ',', '.'); ?></td>
            <td class="text-end fw-semibold small"><?php echo number_format($totalClosingQty, 2, ',', '.'); ?></td>
            <td class="text-end fw-semibold small"><?php echo number_format($totalNilai, 0, ',', '.'); ?></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
  <div class="text-muted small" id="opname-info"></div>
  <nav><ul class="pagination pagination-sm mb-0" id="opname-pager"></ul></nav>
</div>

<?php endif; ?>

<script>
(function () {
  /* ── Client-side pagination + live search ─────────────────── */
  const tbody   = document.getElementById('opname-tbody');
  const qInput  = document.getElementById('opname-q');
  const ppSel   = document.getElementById('opname-per-page');
  const info    = document.getElementById('opname-info');
  const pager   = document.getElementById('opname-pager');

  if (!tbody) return;

  const allRows = Array.from(tbody.querySelectorAll('tr'));
  let currentPage = 1;
  let perPage     = <?php echo (int)$perPage; ?>;
  let visibleRows = allRows;

  function applyFilter() {
    const q = (qInput?.value || '').toLowerCase().trim();
    visibleRows = allRows.filter(tr => !q || (tr.dataset.search || '').includes(q));
    currentPage = 1;
    render();
  }

  function render() {
    const total = visibleRows.length;
    const pages = Math.max(1, Math.ceil(total / perPage));
    currentPage = Math.min(currentPage, pages);
    const start = (currentPage - 1) * perPage;
    allRows.forEach(tr => tr.style.display = 'none');
    visibleRows.slice(start, start + perPage).forEach(tr => tr.style.display = '');
    if (info) info.textContent = 'Menampilkan ' + Math.min(start + 1, total) + '–' + Math.min(start + perPage, total) + ' dari ' + total + ' baris';
    buildPager(pages);
  }

  function buildPager(pages) {
    if (!pager) return;
    pager.innerHTML = '';
    if (pages <= 1) return;
    const mkLi = (label, page, disabled, active) => {
      const li = document.createElement('li');
      li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
      li.innerHTML = '<a class="page-link" href="#">' + label + '</a>';
      if (!disabled && !active) li.querySelector('a').addEventListener('click', e => { e.preventDefault(); currentPage = page; render(); });
      return li;
    };
    pager.appendChild(mkLi('‹', currentPage - 1, currentPage === 1));
    const win = 2;
    for (let p = 1; p <= pages; p++) {
      if (p === 1 || p === pages || (p >= currentPage - win && p <= currentPage + win)) {
        pager.appendChild(mkLi(p, p, false, p === currentPage));
      } else if (p === currentPage - win - 1 || p === currentPage + win + 1) {
        pager.appendChild(mkLi('…', p, true));
      }
    }
    pager.appendChild(mkLi('›', currentPage + 1, currentPage === pages));
  }

  qInput?.addEventListener('input', applyFilter);
  ppSel?.addEventListener('change', () => { perPage = parseInt(ppSel.value) || 25; applyFilter(); });

  applyFilter();
})();
</script>
