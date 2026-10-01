<?php
$filters   = is_array($filters ?? null) ? $filters : [];
$rows      = is_array($rows ?? null) ? $rows : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$perPage   = max(10, (int)($filters['per_page'] ?? 25));

$locationFilterOptions = ['' => 'Semua Lokasi', 'REGULER' => 'Reguler', 'EVENT' => 'Event'];

$fmtQty = static function ($v): string {
  $f = round((float)$v, 4);
  return $f == 0 ? '<span class="text-muted">-</span>' : number_format($f, 4, ',', '.');
};
$fmtVal = static function ($v): string {
  $f = round((float)$v, 0);
  return $f == 0 ? '<span class="text-muted">-</span>' : number_format($f, 0, ',', '.');
};
$fmtHpp = static function ($v): string {
  $f = round((float)$v, 2);
  return $f == 0 ? '<span class="text-muted">-</span>' : number_format($f, 2, ',', '.');
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
$sourceBadge = static function (string $s): string {
  switch (strtoupper($s)) {
    case 'OPNAME':               return '<span class="badge bg-success-subtle text-success">Opname</span>';
    case 'MANUAL':               return '<span class="badge bg-warning-subtle text-warning">Manual</span>';
    case 'AUTO_CARRY_FORWARD':   return '<span class="badge bg-info-subtle text-info">Auto Carry</span>';
    case 'AUTO_REBUILD':         return '<span class="badge bg-secondary-subtle text-secondary">Auto Rebuild</span>';
    default:                     return '<span class="badge bg-secondary-subtle text-secondary">' . htmlspecialchars($s) . '</span>';
  }
};

// ── Summary analytics ──────────────────────────────────────────
$totalNilai    = array_sum(array_column($rows, 'total_value'));
$totalQty      = array_sum(array_column($rows, 'opening_qty'));
$adaQty        = count(array_filter($rows, static fn($r) => (float)($r['opening_qty'] ?? 0) > 0));
$totalRows     = count($rows);
$pctAda        = $totalRows > 0 ? round($adaQty / $totalRows * 100) : 0;
$uniqueComp    = count(array_unique(array_column($rows, 'component_id')));

$srcBreak = [];
foreach ($rows as $r) {
  $s = strtoupper((string)($r['source_type'] ?? 'OTHER'));
  $srcBreak[$s] = ($srcBreak[$s] ?? 0) + 1;
}
arsort($srcBreak);

$monthKeys = array_unique(array_column($rows, 'month_key'));
$activeBulan = count($monthKeys);

$nilaiByLoc = [];
foreach ($rows as $r) {
  $loc = (string)($r['location_type'] ?? 'OTHER');
  $nilaiByLoc[$loc] = ($nilaiByLoc[$loc] ?? 0) + (float)($r['total_value'] ?? 0);
}
arsort($nilaiByLoc);
$topLocName  = key($nilaiByLoc) ?? '-';
$topLocPct   = $totalNilai > 0 ? round((reset($nilaiByLoc) ?: 0) / $totalNilai * 100) : 0;
?>

<div class="mb-3">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
    <div>
      <h4 class="mb-1"><i class="ri ri-archive-line page-title-icon"></i><?php echo html_escape($page_title ?? 'Opening Stok Bulanan Component'); ?></h4>
      <small class="text-muted">Data stok awal bulanan component — sumber carry-forward dari opname, rebuild, atau entri manual.</small>
    </div>
  </div>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'opening_monthly']); ?>
<?php $this->load->view('production/_component_type_tabs', [
  'component_type_base_url' => site_url('production/component-opening-monthly'),
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

  <!-- Total Nilai Opening -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body p-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="rounded-2 p-1" style="background:#e8f4fd"><i class="ri ri-money-dollar-circle-line text-primary" style="font-size:1.1rem"></i></span>
          <span class="text-muted" style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Nilai Opening</span>
        </div>
        <div class="fw-bold" style="font-size:1.05rem;color:#1565c0"><?php echo 'Rp ' . number_format(round($totalNilai / 1000000, 2), 2, ',', '.') . ' jt'; ?></div>
        <div class="text-muted" style="font-size:.72rem"><?php echo $totalRows; ?> entri opening</div>
      </div>
    </div>
  </div>

  <!-- Total Qty -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body p-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="rounded-2 p-1" style="background:#e8f5e9"><i class="ri ri-scales-3-line text-success" style="font-size:1.1rem"></i></span>
          <span class="text-muted" style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Total Qty</span>
        </div>
        <div class="fw-bold" style="font-size:1.05rem;color:#2e7d32"><?php echo number_format($totalQty, 2, ',', '.'); ?></div>
        <div class="text-muted" style="font-size:.72rem"><?php echo $uniqueComp; ?> component unik</div>
      </div>
    </div>
  </div>

  <!-- Coverage -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body p-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="rounded-2 p-1" style="background:#fff8e1"><i class="ri ri-checkbox-circle-line text-warning" style="font-size:1.1rem"></i></span>
          <span class="text-muted" style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Ada Opening</span>
        </div>
        <div class="fw-bold" style="font-size:1.05rem;color:#f57f17"><?php echo $adaQty; ?> <span class="fw-normal text-muted" style="font-size:.78rem">/ <?php echo $totalRows; ?></span></div>
        <div style="height:4px;background:#fff8e1;border-radius:2px;margin:4px 0 2px">
          <div style="height:4px;background:#ffa000;border-radius:2px;width:<?php echo $pctAda; ?>%"></div>
        </div>
        <div class="text-muted" style="font-size:.72rem"><?php echo $pctAda; ?>% qty > 0</div>
      </div>
    </div>
  </div>

  <!-- Sumber breakdown -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body p-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="rounded-2 p-1" style="background:#f3e5f5"><i class="ri ri-git-branch-line" style="font-size:1.1rem;color:#7b1fa2"></i></span>
          <span class="text-muted" style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Sumber</span>
        </div>
        <?php $si = 0; foreach ($srcBreak as $sk => $sv): if ($si++ > 3) break;
          $srcColor = ['OPNAME' => 'success', 'MANUAL' => 'warning', 'AUTO_CARRY_FORWARD' => 'info', 'AUTO_REBUILD' => 'secondary'][$sk] ?? 'secondary';
          $srcLabel = ['OPNAME' => 'Opname', 'MANUAL' => 'Manual', 'AUTO_CARRY_FORWARD' => 'Auto CF', 'AUTO_REBUILD' => 'Rebuild'][$sk] ?? $sk;
        ?>
        <div class="d-flex justify-content-between align-items-center" style="font-size:.73rem">
          <span class="badge bg-<?php echo $srcColor; ?>-subtle text-<?php echo $srcColor; ?>"><?php echo html_escape($srcLabel); ?></span>
          <span class="fw-bold"><?php echo $sv; ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Komponen unik -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body p-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="rounded-2 p-1" style="background:#fce4ec"><i class="ri ri-list-check text-danger" style="font-size:1.1rem"></i></span>
          <span class="text-muted" style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Komponen</span>
        </div>
        <div class="fw-bold" style="font-size:1.05rem;color:#c62828"><?php echo $uniqueComp; ?></div>
        <div class="text-muted" style="font-size:.72rem">dalam <?php echo $totalRows; ?> entri (<?php echo $totalRows > 0 ? round($totalRows/$uniqueComp,1) : 0; ?>× avg per comp)</div>
      </div>
    </div>
  </div>

  <!-- Top Lokasi -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body p-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="rounded-2 p-1" style="background:#e3f2fd"><i class="ri ri-map-pin-2-line" style="font-size:1.1rem;color:#1565c0"></i></span>
          <span class="text-muted" style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Top Lokasi</span>
        </div>
        <div class="fw-bold" style="font-size:1rem;color:#37474f"><?php echo html_escape($topLocName); ?></div>
        <div class="text-muted" style="font-size:.72rem"><?php echo $topLocPct; ?>% dari total nilai</div>
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
    <form method="get" action="<?php echo site_url('production/component-opening-monthly'); ?>" class="row g-2 align-items-end">
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
        <input type="text" id="opening-q" name="q" class="form-control form-control-sm" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama component…" autocomplete="off">
      </div>
      <div class="col-6 col-md-1">
        <label class="form-label mb-1 small">Per hal</label>
        <select name="per_page" class="form-select form-select-sm" id="opening-per-page">
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
    Belum ada data opening untuk bulan <?php echo html_escape((string)($filters['month'] ?? date('Y-m'))); ?>.
    Generate opname bulan sebelumnya untuk membuat stok awal otomatis.
  </div>
<?php else: ?>

<!-- ── Table ──────────────────────────────────────────────────── -->
<div class="card">
  <div class="card-body p-0">
    <div id="opening-table-wrap" style="overflow:auto;max-height:70vh">
      <table class="table table-sm table-hover align-middle small mb-0" style="table-layout:fixed;min-width:850px">
        <thead class="table-light" style="position:sticky;top:0;z-index:4">
          <tr>
            <th style="width:160px">Jenis</th>
            <th style="width:180px">Component</th>
            <th style="width:52px">UOM</th>
            <th class="text-end" style="width:96px">Qty Opening</th>
            <th class="text-end" style="width:90px">Avg Cost</th>
            <th class="text-end" style="width:100px">Total Nilai</th>
            <th style="width:100px">Sumber</th>
            <th style="width:90px">Bln Sumber</th>
            <th style="width:110px">Di-generate</th>
          </tr>
        </thead>
        <tbody id="opening-tbody">
          <?php foreach ($rows as $row):
            $search = implode(' ', [
              strtolower((string)($row['component_name'] ?? '')),
              strtolower((string)($row['division_name'] ?? '')),
              strtolower((string)($row['location_type'] ?? '')),
              strtolower((string)($row['component_type'] ?? '')),
              strtolower((string)($row['source_type'] ?? '')),
            ]);
            $openingQty = (float)($row['opening_qty'] ?? 0);
          ?>
          <tr data-search="<?php echo html_escape($search); ?>">
            <td>
              <div class="d-flex flex-column gap-1">
                <?php echo $jenisBadges((string)($row['location_type'] ?? ''), (string)($row['component_type'] ?? '')); ?>
              </div>
            </td>
            <td>
              <div class="fw-semibold" style="font-size:.82rem;line-height:1.3"><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></div>
              <div class="text-muted" style="font-size:.7rem"><?php echo html_escape((string)($row['component_code'] ?? '')); ?></div>
            </td>
            <td class="text-muted"><?php echo html_escape((string)($row['uom_code'] ?? $row['uom_name'] ?? '-')); ?></td>
            <td class="text-end fw-semibold <?php echo $openingQty == 0 ? 'text-muted' : ''; ?>"><?php echo $fmtQty($openingQty); ?></td>
            <td class="text-end"><?php echo $fmtHpp($row['hpp_live'] ?? 0); ?></td>
            <td class="text-end"><?php echo $fmtVal($row['total_value'] ?? 0); ?></td>
            <td><?php echo $sourceBadge((string)($row['source_type'] ?? '')); ?></td>
            <td class="text-muted" style="font-size:.78rem"><?php echo html_escape((string)($row['source_month'] ?? '-')); ?></td>
            <td class="text-muted" style="font-size:.72rem;white-space:nowrap">
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
            <td class="text-end fw-semibold small"><?php echo number_format($totalQty, 4, ',', '.'); ?></td>
            <td></td>
            <td class="text-end fw-semibold small"><?php echo number_format($totalNilai, 0, ',', '.'); ?></td>
            <td colspan="3"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
  <div class="text-muted small" id="opening-info"></div>
  <nav><ul class="pagination pagination-sm mb-0" id="opening-pager"></ul></nav>
</div>

<?php endif; ?>

<script>
(function () {
  const tbody  = document.getElementById('opening-tbody');
  const qInput = document.getElementById('opening-q');
  const ppSel  = document.getElementById('opening-per-page');
  const info   = document.getElementById('opening-info');
  const pager  = document.getElementById('opening-pager');

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
