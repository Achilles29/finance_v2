<?php
$rows           = is_array($rows ?? null) ? $rows : [];
$uoms           = is_array($uoms ?? null) ? $uoms : [];
$divisions      = is_array($divisions ?? null) ? $divisions : [];
$locationOptions = is_array($location_options ?? null) ? $location_options : [];
$filterDivision  = (int)($filter_division ?? 0);
$filterLocation  = strtoupper(trim((string)($filter_location ?? '')));
$dateFrom        = (string)($date_from ?? date('Y-m-01'));
$dateTo          = (string)($date_to ?? date('Y-m-d'));

$locationGroupLabel = static function ($locationType): string {
    $value = strtoupper(trim((string)$locationType));
    if ($value === 'BAR_EVENT' || $value === 'KITCHEN_EVENT' || $value === 'ROASTERY_EVENT') {
        return 'EVENT';
    }
    if ($value === 'BAR' || $value === 'KITCHEN' || $value === 'ROASTERY') {
        return 'REGULER';
    }
    return $value !== '' ? $value : '-';
};

// ── Analytics ─────────────────────────────────────────────────────────────────
$today          = date('Y-m-d');
$totalBatch     = count($rows);
$draftCount     = 0;
$postedCount    = 0;
$totalCost      = 0.0;
$todayCount     = 0;
$todayCost      = 0.0;
$componentCounts = [];
$divisionCounts  = [];

foreach ($rows as $r) {
    $status = strtoupper((string)($r['status'] ?? ''));
    $cost   = (float)($r['total_input_cost'] ?? 0);
    if ($status === 'DRAFT')   $draftCount++;
    if ($status === 'POSTED')  $postedCount++;
    $totalCost += $cost;
    if (($r['batch_date'] ?? '') === $today) {
        $todayCount++;
        $todayCost += $cost;
    }
    $cn = (string)($r['component_name'] ?? '-');
    $dn = (string)($r['division_name'] ?? '-');
    $componentCounts[$cn] = ($componentCounts[$cn] ?? 0) + 1;
    $divisionCounts[$dn]  = ($divisionCounts[$dn] ?? 0) + 1;
}
arsort($componentCounts);
arsort($divisionCounts);
$topComponent      = !empty($componentCounts) ? array_key_first($componentCounts) : '-';
$topComponentCount = $componentCounts[$topComponent] ?? 0;
$topDivision       = !empty($divisionCounts) ? array_key_first($divisionCounts) : '-';
$topDivisionCount  = $divisionCounts[$topDivision] ?? 0;
$avgCost           = $totalBatch > 0 ? ($totalCost / $totalBatch) : 0;

$cbFmt = static function ($num): string {
    return 'Rp ' . number_format((float)$num, 0, ',', '.');
};
$cbTransactionTime = static function (array $row): string {
    $raw = trim((string)($row['created_at'] ?? ($row['posted_at'] ?? ($row['updated_at'] ?? ''))));
    if ($raw === '' || $raw === '0000-00-00 00:00:00') {
        return '';
    }
    $ts = strtotime($raw);
    return $ts ? date('H:i', $ts) : '';
};
?>

<style>
  /* ── Overlay ── */
  .component-batch-overlay {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(58, 38, 22, 0.22);
    backdrop-filter: blur(1px);
    z-index: 2000;
  }
  .component-batch-overlay.is-active {
    display: flex;
  }
  .component-batch-overlay-card {
    min-width: 280px;
    max-width: 420px;
    padding: 1rem 1.2rem;
    border-radius: 18px;
    background: #fffdf9;
    border: 1px solid #eadfce;
    box-shadow: 0 18px 45px rgba(76, 56, 39, 0.22);
    display: flex;
    align-items: center;
    gap: .85rem;
    color: #5d4636;
  }
  .component-batch-overlay-card .spinner-border {
    width: 1.35rem;
    height: 1.35rem;
    color: #8b2f33;
  }
  .component-batch-overlay-title {
    font-weight: 700;
  }
  .component-batch-overlay-subtitle {
    font-size: .86rem;
    color: #806553;
  }
  /* ── Summary cards ── */
  .cb-stat-card {
    border-radius: 14px;
    padding: .85rem 1rem;
    height: 100%;
    border: 1px solid transparent;
    transition: box-shadow .15s;
  }
  .cb-stat-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,.1);
  }
  .cb-stat-label {
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
    margin-bottom: .2rem;
    opacity: .7;
  }
  .cb-stat-value {
    font-size: 1.35rem;
    font-weight: 800;
    line-height: 1.2;
  }
  .cb-stat-sub {
    font-size: .75rem;
    margin-top: .25rem;
    opacity: .75;
  }
  /* ── Table ── */
  .cb-table-scroll {
    max-height: 62vh;
    overflow-y: auto;
    overflow-x: auto;
  }
  .cb-table-scroll thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f8fafc;
    box-shadow: 0 1px 0 #dee2e6;
  }
  /* ── Inline chips ── */
  .component-batch-summary {
    background: #f8f4ee;
    border: 1px solid #e6d8c8;
    border-radius: 12px;
    padding: .8rem 1rem;
  }
  .component-batch-summary strong {
    font-size: 1.05rem;
    color: #4c3827;
  }
  .component-batch-stage {
    min-width: 138px;
  }
  .component-batch-status {
    font-size: .74rem;
    letter-spacing: .02em;
  }
  .component-batch-inline-meta {
    margin-top: .35rem;
  }
  .component-batch-inline-label {
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #8a6a52;
  }
  .component-batch-inline-chip {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    margin: .15rem .3rem 0 0;
    padding: .15rem .45rem;
    border-radius: 999px;
    border: 1px solid #e6d8c8;
    background: #f8f4ee;
    font-size: .76rem;
    color: #5d4636;
  }
  /* ── Action buttons ── */
  .component-action-cell {
    white-space: nowrap;
    width: 1%;
    text-align: center;
  }
  .component-action-stack {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    flex-wrap: nowrap;
    justify-content: center;
  }
  .component-action-btn {
    flex-shrink: 0;
    width: 34px !important;
    min-width: 34px !important;
    height: 34px !important;
    padding: 0 !important;
    border-radius: 8px !important;
  }
  .component-action-btn i,
  .component-action-btn [class^="ri-"],
  .component-action-btn [class*=" ri-"] {
    font-size: 1rem !important;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  /* ── Pagination ── */
  #batchPagination .page-link {
    padding: .25rem .55rem;
    font-size: .8rem;
  }
  /* ── Batch form modal ── */
  #batchFormModal .modal-body {
    background: #fafaf8;
  }
</style>

<div class="mb-3">
  <h4 class="mb-1">Batch Produksi Base/Prepare</h4>
  <small class="text-muted">Patokan produksi mengikuti hasil 1x resep di master component. Pilih mode sesuai resep atau sesuai bahan acuan, lalu sistem menghitung hasil jadi dan kebutuhan input otomatis.</small>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'batch']); ?>
<?php $this->load->view('production/_component_type_tabs', [
    'component_type_base_url' => site_url('production/component-batches'),
    'component_type_filters'  => $filters ?? [],
    'component_type_active'   => (string)(($filters ?? [])['type'] ?? ''),
]); ?>
<?php $this->load->view('production/_component_action_buttons', [
    'component_action_params' => array_filter([
        'month'         => (string)(($filters ?? [])['month'] ?? ''),
        'division_id'   => !empty(($filters ?? [])['division_id']) ? (int)($filters ?? [])['division_id'] : '',
        'location_type' => (string)(($filters ?? [])['location_type'] ?? ''),
    ], static fn($v) => $v !== '' && $v !== 0 && $v !== '0'),
]); ?>

<div id="component-batch-alert" class="mt-3 mb-0"></div>

<div id="componentBatchOverlay" class="component-batch-overlay" aria-hidden="true">
  <div class="component-batch-overlay-card" role="status" aria-live="polite">
    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
    <div>
      <div class="component-batch-overlay-title" id="componentBatchOverlayTitle">Memproses batch...</div>
      <div class="component-batch-overlay-subtitle">Mohon tunggu, stok dan biaya sedang dihitung.</div>
    </div>
  </div>
</div>

<!-- ── Filter bar ──────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mt-3 mb-3">
  <div class="card-body py-2 px-3">
    <form method="get" id="frmFilter" class="d-flex flex-wrap gap-2 align-items-end">
      <div>
        <label class="form-label small mb-1 fw-semibold">Dari</label>
        <input type="date" name="date_from" class="form-control form-control-sm"
               value="<?php echo html_escape($dateFrom); ?>" onchange="this.form.submit()">
      </div>
      <div>
        <label class="form-label small mb-1 fw-semibold">Sampai</label>
        <input type="date" name="date_to" class="form-control form-control-sm"
               value="<?php echo html_escape($dateTo); ?>" onchange="this.form.submit()">
      </div>
      <div>
        <label class="form-label small mb-1 fw-semibold">Divisi</label>
        <select name="division_id" class="form-select form-select-sm" style="min-width:130px" onchange="this.form.submit()">
          <option value="">Semua Divisi</option>
          <?php foreach ($divisions as $d): ?>
            <option value="<?php echo (int)$d['id']; ?>"
              <?php echo $filterDivision === (int)$d['id'] ? 'selected' : ''; ?>>
              <?php echo html_escape((string)$d['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label small mb-1 fw-semibold">Lokasi</label>
        <select name="location_type" class="form-select form-select-sm" style="min-width:110px" onchange="this.form.submit()">
          <option value="">Semua Lokasi</option>
          <option value="REGULER" <?php echo $filterLocation === 'REGULER' ? 'selected' : ''; ?>>Reguler</option>
          <option value="EVENT"   <?php echo $filterLocation === 'EVENT'   ? 'selected' : ''; ?>>Event</option>
        </select>
      </div>
      <div style="flex:1 1 160px;min-width:160px;max-width:280px">
        <label class="form-label small mb-1 fw-semibold">Cari</label>
        <input type="text" id="searchBatch" class="form-control form-control-sm"
               placeholder="No batch / nama component…" autocomplete="off">
      </div>
      <div>
        <label class="form-label small mb-1 fw-semibold">Per Halaman</label>
        <select id="perPage" class="form-select form-select-sm" style="width:85px">
          <option value="25" selected>25</option>
          <option value="50">50</option>
          <option value="100">100</option>
          <option value="0">Semua</option>
        </select>
      </div>
      <input type="hidden" name="type" value="<?php echo html_escape((string)(($filters ?? [])['type'] ?? '')); ?>">
      <div class="ms-auto d-flex flex-column">
        <label class="form-label small mb-1 fw-semibold" style="visibility:hidden">Aksi</label>
        <div class="d-flex gap-2">
          <a href="<?php echo site_url('production/component-batches'); ?>"
             class="btn btn-sm btn-outline-warning">
            <i class="ri-refresh-line"></i> Reset
          </a>
          <button type="button" class="btn btn-sm btn-primary" id="btnTambahBatch"
                  data-bs-toggle="modal" data-bs-target="#batchFormModal">
            <i class="ri-add-line"></i> Tambah Batch
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ── Summary cards ──────────────────────────────────────────────────────── -->
<div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3 mb-3">

  <div class="col">
    <div class="cb-stat-card" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af;">
      <div class="cb-stat-label">Total Batch</div>
      <div class="cb-stat-value"><?php echo $totalBatch; ?></div>
      <div class="cb-stat-sub">
        <span class="badge text-bg-warning"><?php echo $draftCount; ?> Draft</span>
        <span class="badge text-bg-success ms-1"><?php echo $postedCount; ?> Posted</span>
      </div>
    </div>
  </div>

  <div class="col">
    <div class="cb-stat-card" style="background:#f0fdf4;border-color:#bbf7d0;color:#166534;">
      <div class="cb-stat-label">Hari Ini</div>
      <div class="cb-stat-value"><?php echo $todayCount; ?></div>
      <div class="cb-stat-sub"><?php echo $todayCount > 0 ? $cbFmt($todayCost) : '—'; ?></div>
    </div>
  </div>

  <div class="col">
    <div class="cb-stat-card" style="background:#fff7ed;border-color:#fed7aa;color:#9a3412;">
      <div class="cb-stat-label">Total Nilai Input</div>
      <div class="cb-stat-value" style="font-size:1.05rem;"><?php echo $cbFmt($totalCost); ?></div>
      <div class="cb-stat-sub">dari <?php echo $totalBatch; ?> batch</div>
    </div>
  </div>

  <div class="col">
    <div class="cb-stat-card" style="background:#faf5ff;border-color:#e9d5ff;color:#6b21a8;">
      <div class="cb-stat-label">Avg Cost/Batch</div>
      <div class="cb-stat-value" style="font-size:1.05rem;"><?php echo $cbFmt($avgCost); ?></div>
      <div class="cb-stat-sub">rata-rata input</div>
    </div>
  </div>

  <div class="col">
    <div class="cb-stat-card" style="background:#fefce8;border-color:#fde68a;color:#92400e;">
      <div class="cb-stat-label">Component Terbanyak</div>
      <div class="cb-stat-value" style="font-size:.95rem;word-break:break-word;"
           title="<?php echo html_escape($topComponent); ?>">
        <?php echo html_escape(mb_strimwidth($topComponent, 0, 22, '…')); ?>
      </div>
      <div class="cb-stat-sub"><?php echo $topComponentCount; ?> batch</div>
    </div>
  </div>

  <div class="col">
    <div class="cb-stat-card" style="background:#f0fdfa;border-color:#99f6e4;color:#134e4a;">
      <div class="cb-stat-label">Divisi Terproduksi</div>
      <div class="cb-stat-value" style="font-size:.95rem;word-break:break-word;"
           title="<?php echo html_escape($topDivision); ?>">
        <?php echo html_escape(mb_strimwidth($topDivision, 0, 22, '…')); ?>
      </div>
      <div class="cb-stat-sub"><?php echo $topDivisionCount; ?> batch</div>
    </div>
  </div>

</div>

<!-- ── Table ──────────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
  <div class="cb-table-scroll">
    <table class="table table-hover table-sm align-middle mb-0" id="batchTable">
      <thead>
        <tr>
          <th class="ps-3" style="width:140px;">No Batch</th>
          <th style="width:100px;">Tanggal</th>
          <th style="width:80px;">Lokasi</th>
          <th>Output Component</th>
          <th style="width:120px;" class="text-end">Qty Output</th>
          <th style="width:120px;" class="text-end">Total Cost</th>
          <th style="width:70px;" class="text-center">Status</th>
          <th style="width:110px;" class="text-center">Aksi</th>
        </tr>
      </thead>
      <tbody id="batchTbody">
        <?php if (empty($rows)): ?>
          <tr class="cb-no-rows"><td colspan="8" class="text-center text-muted py-4">Belum ada batch pada periode ini.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row):
            $locGroup     = $locationGroupLabel((string)($row['location_type'] ?? ''));
            $rowStatus    = strtoupper((string)($row['status'] ?? 'DRAFT'));
            $inlineSummary = is_array($row['inline_summary'] ?? null) ? $row['inline_summary'] : [];
            $inlineOutputs = is_array($inlineSummary['outputs'] ?? null) ? $inlineSummary['outputs'] : [];
            $inlineUsages  = is_array($inlineSummary['usages'] ?? null) ? $inlineSummary['usages'] : [];
          ?>
          <tr id="component-batch-<?php echo (int)$row['id']; ?>"
              data-batch-no="<?php echo html_escape(strtolower((string)($row['batch_no'] ?? ''))); ?>"
              data-component="<?php echo html_escape(strtolower((string)($row['component_name'] ?? ''))); ?>">
            <td class="ps-3 fw-semibold small"><?php echo html_escape((string)($row['batch_no'] ?? '')); ?></td>
            <td class="small">
              <div><?php echo html_escape((string)($row['batch_date'] ?? '')); ?></div>
              <?php $batchTime = $cbTransactionTime((array)$row); ?>
              <?php if ($batchTime !== ''): ?>
                <div class="text-muted" style="font-size:.72rem;line-height:1.15">Jam <?php echo html_escape($batchTime); ?></div>
              <?php endif; ?>
            </td>
            <td class="small">
              <span class="badge <?php echo $locGroup === 'EVENT' ? 'text-bg-info' : 'text-bg-secondary'; ?>">
                <?php echo html_escape($locGroup); ?>
              </span>
            </td>
            <td>
              <div class="fw-medium"><?php echo html_escape((string)($row['component_name'] ?? '')); ?></div>
              <div class="small text-muted"><?php echo html_escape((string)($row['division_name'] ?? '')); ?></div>
              <?php if (!empty($inlineSummary['has_inline'])): ?>
                <div class="component-batch-inline-meta">
                  <?php if (!empty($inlineOutputs)): ?>
                    <div>
                      <span class="component-batch-inline-label">Inline Output</span>
                      <?php foreach ($inlineOutputs as $ir): ?>
                        <span class="component-batch-inline-chip">
                          <?php echo html_escape((string)($ir['component_name'] ?? '-')); ?>
                          <span class="text-muted"><?php echo number_format((float)($ir['qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($ir['uom_code'] ?? '')); ?></span>
                        </span>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($inlineUsages)): ?>
                    <div>
                      <span class="component-batch-inline-label">Inline Pakai</span>
                      <?php foreach ($inlineUsages as $ir): ?>
                        <span class="component-batch-inline-chip">
                          <?php echo html_escape((string)($ir['component_name'] ?? '-')); ?>
                          <span class="text-muted"><?php echo number_format((float)($ir['qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($ir['uom_code'] ?? '')); ?></span>
                        </span>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </td>
            <td class="text-end small">
              <?php echo number_format((float)($row['output_qty'] ?? 0), 2, ',', '.'); ?>
              <?php echo html_escape((string)($row['uom_code'] ?? '')); ?>
            </td>
            <td class="text-end small">
              <?php $tc = (float)($row['total_input_cost'] ?? 0); ?>
              <?php echo $tc > 0 ? $cbFmt($tc) : '<span class="text-muted">—</span>'; ?>
            </td>
            <td class="text-center">
              <?php echo ui_status_badge($rowStatus); ?>
              <?php if ($rowStatus === 'POSTED'): ?>
                <div class="mt-1">
                  <?php if (!empty($row['can_void'])): ?>
                    <span class="badge text-bg-success" title="Batch ini belum terdeteksi dipakai dokumen lain.">Siap Void</span>
                    <?php
                      $voidProjection = is_array($row['void_projection'] ?? null) ? $row['void_projection'] : [];
                      $voidWillGoNegative = !empty($voidProjection['available']) && !empty($voidProjection['would_go_negative']);
                    ?>
                    <?php if ($voidWillGoNegative): ?>
                      <div class="small text-warning-emphasis mt-1" title="Void tetap boleh, tetapi saldo global component akan minus sementara sampai drift stock vs movement dibereskan.">
                        Void bikin saldo global minus sementara:
                        <?php echo number_format((float)($voidProjection['projected_global_qty_after_void'] ?? 0), 2, ',', '.'); ?>
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="badge text-bg-warning" title="<?php echo html_escape((string)($row['void_block_reason'] ?? 'Batch ini sudah dipakai.')); ?>">Tidak Bisa Void</span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </td>
            <td class="component-action-cell">
              <?php if ($rowStatus === 'DRAFT'): ?>
                <div class="component-action-stack">
                  <button type="button" class="btn btn-outline-success action-icon-btn component-action-btn btn-post"
                          data-id="<?php echo (int)$row['id']; ?>" title="Post" aria-label="Post">
                    <i class="ri ri-checkbox-circle-line"></i>
                  </button>
                  <button type="button" class="btn btn-outline-danger action-icon-btn component-action-btn btn-del"
                          data-id="<?php echo (int)$row['id']; ?>" title="Delete" aria-label="Delete">
                    <i class="ri ri-delete-bin-line"></i>
                  </button>
                </div>
              <?php elseif ($rowStatus === 'POSTED'): ?>
                <div class="component-action-stack">
                  <?php
                    $voidProjection = is_array($row['void_projection'] ?? null) ? $row['void_projection'] : [];
                    $voidWillGoNegative = !empty($row['can_void']) && !empty($voidProjection['available']) && !empty($voidProjection['would_go_negative']);
                    $voidTitle = !empty($row['can_void'])
                      ? ($voidWillGoNegative
                          ? 'Void tetap boleh, tetapi saldo global akan minus sementara: ' . number_format((float)($voidProjection['projected_global_qty_after_void'] ?? 0), 2, ',', '.')
                          : 'Void')
                      : (string)($row['void_block_reason'] ?? 'Tidak bisa di-void');
                  ?>
                  <a href="<?php echo site_url('production/component-batches/detail/' . (int)$row['id']); ?>"
                     class="btn btn-outline-info action-icon-btn component-action-btn"
                     title="Pemakaian &amp; Trace Batch" aria-label="Pemakaian dan Trace">
                    <i class="ri ri-flow-chart"></i>
                  </a>
                  <button type="button" class="btn btn-outline-warning action-icon-btn component-action-btn btn-void"
                          data-id="<?php echo (int)$row['id']; ?>"
                          title="<?php echo html_escape($voidTitle); ?>"
                          aria-label="Void"
                          <?php echo !empty($row['can_void']) ? '' : 'disabled'; ?>>
                    <i class="ri ri-close-circle-line"></i>
                  </button>
                </div>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination footer -->
  <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top bg-white">
    <small class="text-muted" id="batchInfo"></small>
    <nav aria-label="Pagination batch">
      <ul class="pagination pagination-sm mb-0" id="batchPagination"></ul>
    </nav>
  </div>
</div>

<!-- ── Batch Form Modal ────────────────────────────────────────────────────── -->
<div class="modal fade" id="batchFormModal" tabindex="-1" aria-labelledby="batchFormModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title" id="batchFormModalLabel"><i class="ri-add-box-line me-1"></i>Tambah Batch Produksi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">Pilih output component, tentukan lokasi, lalu pilih mode produksi. Sistem akan menghitung hasil jadi berdasarkan resep, bukan dari qty output manual.</p>

        <form id="frmBatch" autocomplete="off">
          <div class="row g-2 mb-3">
            <div class="col-md-2">
              <label class="form-label">Tanggal Batch</label>
              <input type="date" class="form-control" name="batch_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Output Component</label>
              <input type="hidden" name="component_id" id="batch-output-component-id" value="">
              <input type="text" class="form-control" id="batch-output-component-search"
                     placeholder="Ketik nama component..." autocomplete="off" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Divisi</label>
              <input type="hidden" name="division_id" id="batch-division-id" value="">
              <input type="text" class="form-control" id="batch-division-name" value="Ikuti output component" readonly>
              <div class="form-text" id="batch-division-help">Divisi otomatis mengikuti output component.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Lokasi</label>
              <input type="hidden" name="location_type" id="batch-location-type" value="">
              <select class="form-select" id="batch-location-group" required>
                <option value="">Pilih lokasi...</option>
                <option value="REGULER">Reguler</option>
                <option value="EVENT">Event</option>
              </select>
              <div class="form-text" id="batch-location-help">Pilih output component dulu agar lokasi bisa diturunkan otomatis.</div>
            </div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-2">
              <label class="form-label">Mode Produksi</label>
              <select class="form-select" name="scaling_mode" id="batch-scaling-mode">
                <option value="BATCH">Sesuai Resep</option>
                <option value="REFERENCE">Sesuai Bahan Acuan</option>
              </select>
            </div>
            <div class="col-md-2" id="batch-count-wrap">
              <label class="form-label">Jumlah Batch</label>
              <input type="number" min="0.01" step="0.01" class="form-control text-end"
                     name="batch_count" id="batch-batch-count" value="1.00">
              <div class="form-text">1 = 1 kali produksi sesuai resep dasar.</div>
            </div>
            <div class="col-md-3 d-none" id="batch-reference-line-wrap">
              <label class="form-label">Bahan Acuan</label>
              <select class="form-select" name="reference_line_no" id="batch-reference-line-no">
                <option value="">Pilih bahan acuan...</option>
              </select>
            </div>
            <div class="col-md-2 d-none" id="batch-reference-qty-wrap">
              <label class="form-label">Qty Aktual Acuan</label>
              <input type="number" min="0.01" step="0.01" class="form-control text-end"
                     name="reference_actual_qty" id="batch-reference-actual-qty" placeholder="0.00">
            </div>
            <div class="col-md-3">
              <label class="form-label">UOM Output</label>
              <input type="hidden" name="output_uom_id" id="batch-output-uom" value="">
              <input type="text" class="form-control" id="batch-output-uom-label" value="Ikuti output component" readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Hasil Produksi</label>
              <input type="hidden" name="output_qty" id="batch-output-qty" value="">
              <input type="text" class="form-control text-end" id="batch-output-qty-label" value="0,00" readonly>
              <div class="form-text" id="batch-output-help">Hasil jadi dihitung otomatis dari mode produksi yang dipilih.</div>
            </div>
            <div class="col-md-12">
              <label class="form-label">Catatan Header</label>
              <input type="text" class="form-control" name="notes" placeholder="Contoh: batch prep sore hari">
            </div>
          </div>

          <div class="card border-0 bg-light mb-3">
            <div class="card-body">
              <div class="row g-3 mb-3">
                <div class="col-md-3">
                  <div class="component-batch-summary h-100">
                    <div class="small text-muted">Output Resep Dasar</div>
                    <strong id="batch-base-output">-</strong>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="component-batch-summary h-100">
                    <div class="small text-muted">Mode / Skala</div>
                    <strong id="batch-scaling-summary">-</strong>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="component-batch-summary h-100">
                    <div class="small text-muted">Total Input Cost</div>
                    <strong id="batch-total-input-cost">Rp 0</strong>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="component-batch-summary h-100">
                    <div class="small text-muted">Estimasi Cost / Output</div>
                    <strong id="batch-estimated-unit-cost">Rp 0</strong>
                  </div>
                </div>
              </div>

              <div id="batch-preview-issues" class="d-none mb-3"></div>
              <div id="batch-preview-empty" class="text-muted">Pilih output component, lokasi, dan mode produksi untuk melihat preview produksi.</div>

              <div class="row g-3 mb-3 d-none" id="batch-live-preview">
                <div class="col-md-4">
                  <div class="component-batch-summary h-100">
                    <div class="small text-muted">Preview Output</div>
                    <strong id="batch-live-output">-</strong>
                    <div class="small text-muted mt-2" id="batch-live-output-note">Hasil jadi akan tampil otomatis setelah parameter produksi diisi.</div>
                  </div>
                </div>
                <div class="col-md-8">
                  <div class="component-batch-summary h-100">
                    <div class="small text-muted">Preview Pemakaian Bahan</div>
                    <div id="batch-live-usage" class="small text-body-secondary">Belum ada pemakaian bahan yang bisa dihitung.</div>
                  </div>
                </div>
              </div>

              <div class="table-responsive d-none" id="batch-preview-wrap">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th style="width:150px;">Tahap</th>
                      <th style="width:140px;">Role</th>
                      <th>Sumber</th>
                      <th style="width:120px;" class="text-end">Qty</th>
                      <th style="width:120px;" class="text-end">Tersedia</th>
                      <th style="width:140px;" class="text-end">Unit Cost</th>
                      <th style="width:140px;" class="text-end">Total</th>
                      <th style="width:120px;" class="text-center">Status</th>
                      <th>Catatan</th>
                    </tr>
                  </thead>
                  <tbody id="batch-preview-body"></tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="component-batch-summary d-flex flex-wrap gap-4">
              <div><div class="small text-muted">Component Langsung</div><strong id="batch-direct-component-count">0</strong></div>
              <div><div class="small text-muted">Bahan Langsung</div><strong id="batch-direct-material-count">0</strong></div>
              <div><div class="small text-muted">Variable Cost</div><strong id="batch-variable-cost">Rp 0</strong></div>
            </div>
            <button type="submit" class="btn btn-primary" id="batch-save-btn" disabled>Simpan DRAFT</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ── Usage Modal ─────────────────────────────────────────────────────────── -->
<div class="modal fade" id="componentBatchUsageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Pemakaian Output Batch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="componentBatchUsageBody">
        <div class="text-muted">Memuat detail pemakaian batch...</div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="componentBatchStepUpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Verifikasi Post Batch</h5>
          <div class="small text-muted">Masukkan password akun Anda sebelum stok bahan, component, lot, dan biaya batch diubah.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label for="component_batch_step_up_password" class="form-label">Password akun Anda</label>
        <input type="password" class="form-control" id="component_batch_step_up_password" autocomplete="current-password" maxlength="72">
        <div class="form-text">Password hanya dipakai untuk verifikasi ini dan tidak disimpan pada batch.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-success" id="btn-component-batch-step-up-post">Verifikasi &amp; Post</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="componentBatchVoidStepUpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Verifikasi VOID Batch</h5>
          <div class="small text-muted">Masukkan password akun Anda sebelum lot, stok, dan biaya batch dibalik.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label for="component_batch_void_step_up_password" class="form-label">Password akun Anda</label>
        <input type="password" class="form-control" id="component_batch_void_step_up_password" autocomplete="current-password" maxlength="72">
        <div class="form-text">Password hanya dipakai untuk verifikasi ini dan tidak disimpan pada batch.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-warning" id="btn-component-batch-void-step-up-post">Verifikasi &amp; VOID</button>
      </div>
    </div>
  </div>
</div>

<?php $this->load->view('production/_ajax_picker_helper'); ?>

<script>
(() => {
  const previewUrl = '<?php echo site_url('production/component-batches/preview'); ?>';
  const saveUrl = '<?php echo site_url('production/component-batches/save'); ?>';
  const componentBatchStepUpUrl = '<?php echo site_url('production/component-batches/step-up/verify'); ?>';
  const componentBatchVoidStepUpUrl = '<?php echo site_url('production/component-batches/void-step-up/verify'); ?>';
  const postBaseUrl = '<?php echo site_url('production/component-batches/post'); ?>';
  const statusBaseUrl = '<?php echo site_url('production/component-batches/status'); ?>';
  const usageBaseUrl = '<?php echo site_url('production/component-batches/usage'); ?>';
  const usageDetailBaseUrl = '<?php echo site_url('production/component-batches/detail'); ?>';
  const voidBaseUrl = '<?php echo site_url('production/component-batches/void'); ?>';
  const deleteBaseUrl = '<?php echo site_url('production/component-batches/delete'); ?>';
  const componentBatchCsrfToken = <?php echo json_encode((string)($component_batch_csrf_token ?? ''), JSON_INVALID_UTF8_SUBSTITUTE); ?>;

  const overlayEl = document.getElementById('componentBatchOverlay');
  const overlayTitleEl = document.getElementById('componentBatchOverlayTitle');
  const alertHost = document.getElementById('component-batch-alert');
  const previewWrap = document.getElementById('batch-preview-wrap');
  const previewBody = document.getElementById('batch-preview-body');
  const previewEmpty = document.getElementById('batch-preview-empty');
  const previewIssues = document.getElementById('batch-preview-issues');
  const livePreviewWrap = document.getElementById('batch-live-preview');
  const liveOutput = document.getElementById('batch-live-output');
  const liveOutputNote = document.getElementById('batch-live-output-note');
  const liveUsage = document.getElementById('batch-live-usage');
  const form = document.getElementById('frmBatch');
  const outputComponentId = document.getElementById('batch-output-component-id');
  const outputComponentSearch = document.getElementById('batch-output-component-search');
  const batchDateInput = form ? form.querySelector('input[name="batch_date"]') : null;
  const outputQty = document.getElementById('batch-output-qty');
  const outputQtyLabel = document.getElementById('batch-output-qty-label');
  const outputUom = document.getElementById('batch-output-uom');
  const outputUomLabel = document.getElementById('batch-output-uom-label');
  const scalingMode = document.getElementById('batch-scaling-mode');
  const batchCount = document.getElementById('batch-batch-count');
  const referenceLineNo = document.getElementById('batch-reference-line-no');
  const referenceActualQty = document.getElementById('batch-reference-actual-qty');
  const batchCountWrap = document.getElementById('batch-count-wrap');
  const referenceLineWrap = document.getElementById('batch-reference-line-wrap');
  const referenceQtyWrap = document.getElementById('batch-reference-qty-wrap');
  const divisionIdInput = document.getElementById('batch-division-id');
  const divisionNameInput = document.getElementById('batch-division-name');
  const divisionHelp = document.getElementById('batch-division-help');
  const locationGroupInput = document.getElementById('batch-location-group');
  const locationTypeInput = document.getElementById('batch-location-type');
  const locationHelp = document.getElementById('batch-location-help');
  const outputHelp = document.getElementById('batch-output-help');
  const saveButton = document.getElementById('batch-save-btn');
  const usageModalEl = document.getElementById('componentBatchUsageModal');
  const usageModalBody = document.getElementById('componentBatchUsageBody');
  const usageModal = usageModalEl && window.bootstrap ? new window.bootstrap.Modal(usageModalEl) : null;
  const batchFormModalEl = document.getElementById('batchFormModal');
  const batchFormModal = batchFormModalEl && window.bootstrap ? new window.bootstrap.Modal(batchFormModalEl) : null;
  const batchStepUpModalEl = document.getElementById('componentBatchStepUpModal');
  const batchStepUpPassword = document.getElementById('component_batch_step_up_password');
  const btnBatchStepUpPost = document.getElementById('btn-component-batch-step-up-post');
  const batchVoidStepUpModalEl = document.getElementById('componentBatchVoidStepUpModal');
  const batchVoidStepUpPassword = document.getElementById('component_batch_void_step_up_password');
  const btnBatchVoidStepUpPost = document.getElementById('btn-component-batch-void-step-up-post');

  let outputDivisionCode = '';
  let outputDivisionName = '';
  let currentPreview = null;
  let previewTimer = null;
  let previewRequestController = null;
  let previewRequestToken = 0;
  let pendingBatchPostId = 0;
  let pendingBatchPostButton = null;
  let batchStepUpSubmitting = false;
  let pendingBatchVoidId = 0;
  let pendingBatchVoidButton = null;
  let batchVoidStepUpSubmitting = false;

  // ── Helpers ───────────────────────────────────────────────────────────────
  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function renderAlert(type, message) {
    if (alertHost) {
      alertHost.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show mt-3 mb-0" role="alert">' +
        escapeHtml(message) +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
  }

  function clearAlert() {
    if (alertHost) alertHost.innerHTML = '';
  }

  // ── Post-reload notification ───────────────────────────────────────────────
  (function showPostReloadNotif() {
    let notif = null;
    try {
      const raw = sessionStorage.getItem('batchPostNotif');
      if (raw) { notif = JSON.parse(raw); sessionStorage.removeItem('batchPostNotif'); }
    } catch(e) { return; }
    if (!notif || !alertHost) return;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (notif.type === 'success' || !notif.warnings || notif.warnings.length === 0) {
      alertHost.innerHTML = '<div class="alert alert-success alert-dismissible fade show mt-3 mb-0" role="alert">' +
        '<i class="ri-checkbox-circle-line"></i> <strong>Batch berhasil diposting.</strong>' +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
      return;
    }
    const lines = notif.warnings.map(function(w) {
      const before = Number(w.qty_before||0).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2});
      const after  = Number(w.qty_after ||0).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2});
      const prod   = Number(w.qty_produced||0).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2});
      const uom    = escapeHtml(String(w.uom_code||''));
      const name   = escapeHtml(String(w.component_name||''));
      const still  = parseFloat(w.qty_after) < 0;
      return '<li><strong>' + name + '</strong>: sebelum <span style="color:#c62828;font-weight:700">' +
        before + ' ' + uom + '</span> &rarr; +' + prod + ' ' + uom + ' = <strong>' + after + ' ' + uom + '</strong> ' +
        (still ? '<span class="badge bg-danger">Masih minus</span>' : '<span class="badge bg-success">Deficit terpulihkan</span>') + '</li>';
    });
    alertHost.innerHTML = '<div class="alert alert-warning alert-dismissible fade show mt-3 mb-0" role="alert">' +
      '<i class="ri-error-warning-line"></i> <strong>Batch berhasil diposting.</strong> Ada komponen yang sebelumnya minus:' +
      '<ul class="mt-2 mb-1 ps-3">' + lines.join('') + '</ul>' +
      '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
  })();

  // ── HTTP helpers ──────────────────────────────────────────────────────────
  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-Production-Component-Batch-Csrf': componentBatchCsrfToken
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });
    const text = await response.text();
    let json;
    try { json = JSON.parse(text); } catch(e) { throw new Error('Server mengembalikan respons non-JSON. Data mungkin sudah tersimpan; muat ulang halaman untuk memastikan.'); }
    if (!response.ok || !json.ok) throw new Error(json.message || 'Permintaan gagal diproses.');
    return json;
  }

  async function getJson(url) {
    const response = await fetch(url, {method:'GET', headers:{'X-Requested-With':'XMLHttpRequest'}});
    const text = await response.text();
    let json;
    try { json = JSON.parse(text); } catch(e) { throw new Error('Server mengembalikan respons non-JSON. Muat ulang halaman lalu coba kembali.'); }
    if (!response.ok || !json.ok) throw new Error(json.message || 'Permintaan gagal diproses.');
    return json;
  }

  function delay(ms) { return new Promise((r) => window.setTimeout(r, ms)); }

  async function waitForBatchPosted(batchId, options = {}) {
    const startDelayMs = Math.max(0, Number(options.startDelayMs || 8000));
    const intervalMs   = Math.max(1000, Number(options.intervalMs || 3000));
    const maxMs        = Math.max(intervalMs, Number(options.maxMs || 120000));
    const startedAt    = Date.now();
    if (startDelayMs > 0) await delay(startDelayMs);
    while ((Date.now() - startedAt) < maxMs) {
      try {
        const p = await getJson(statusBaseUrl + '/' + encodeURIComponent(String(batchId || '0')));
        if (String(p.status || '').toUpperCase() === 'POSTED') return true;
      } catch(e) {}
      await delay(intervalMs);
    }
    return false;
  }

  function uiConfirm(message, options) {
    if (window.FinanceUI && typeof window.FinanceUI.confirm === 'function')
      return window.FinanceUI.confirm(message, options || {});
    if (window.FinanceUI && typeof window.FinanceUI.alert === 'function')
      return window.FinanceUI.alert('Modal konfirmasi tidak tersedia. Muat ulang halaman lalu coba lagi.', {title:'UI Belum Siap'}).then(() => false);
    return Promise.resolve(false);
  }

  function pickerLabel(row) { return String(row.name || row.code || ''); }
  function pickerSubLabel(row) {
    return [row.entity_type||'', row.division_name||row.division_code||'', row.uom_name||row.uom_code||''].filter(Boolean).join(' | ');
  }

  function resolveLocationType(divisionCode, locationGroup) {
    const d = String(divisionCode || '').trim().toUpperCase();
    const g = String(locationGroup || '').trim().toUpperCase();
    if (!d || !g) return '';
    if (d === 'BAR')     return g === 'EVENT' ? 'BAR_EVENT'     : 'BAR';
    if (d === 'KITCHEN') return g === 'EVENT' ? 'KITCHEN_EVENT' : 'KITCHEN';
    if (d === 'ROASTERY') return g === 'EVENT' ? 'ROASTERY_EVENT' : 'ROASTERY';
    return '';
  }

  function syncBatchDivisionState() {
    const divisionId = String(divisionIdInput?.value || '');
    divisionNameInput.value = divisionId ? [outputDivisionCode, outputDivisionName].filter(Boolean).join(' - ') : 'Ikuti output component';
    divisionHelp.textContent = divisionId
      ? 'Input component akan dibatasi ke divisi yang sama dengan output.'
      : 'Divisi otomatis mengikuti output component.';
    locationTypeInput.value = resolveLocationType(outputDivisionCode, locationGroupInput?.value || '');
    locationHelp.textContent = divisionId
      ? (locationTypeInput.value ? 'Lokasi akan disimpan sebagai ' + locationTypeInput.value + '.' : 'Pilih Reguler atau Event untuk menentukan lokasi ledger.')
      : 'Pilih output component dulu agar lokasi bisa diturunkan otomatis.';
  }

  function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR', maximumFractionDigits:2}).format(value || 0);
  }
  function formatQty(value) {
    return new Intl.NumberFormat('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2}).format(Number(value || 0));
  }

  function syncScalingMode() {
    const mode = String(scalingMode.value || 'BATCH').toUpperCase();
    batchCountWrap.classList.toggle('d-none', mode !== 'BATCH');
    referenceLineWrap.classList.toggle('d-none', mode !== 'REFERENCE');
    referenceQtyWrap.classList.toggle('d-none', mode !== 'REFERENCE');
    batchCount.disabled = mode !== 'BATCH';
    referenceLineNo.disabled = mode !== 'REFERENCE';
    referenceActualQty.disabled = mode !== 'REFERENCE';
    outputHelp.textContent = mode === 'REFERENCE'
      ? 'Hasil jadi dihitung otomatis dari bahan acuan yang dipilih.'
      : 'Hasil jadi dihitung otomatis dari jumlah batch resep dasar.';
  }

  function renderReferenceOptions(options, selectedLineNo) {
    const rows = Array.isArray(options) ? options : [];
    referenceLineNo.innerHTML = '<option value="">Pilih bahan acuan...</option>' + rows.map((row) =>
      '<option value="' + escapeHtml(row.line_no) + '"' + (String(selectedLineNo||'') === String(row.line_no) ? ' selected' : '') + '>' +
        escapeHtml((row.label||'-') + ' • ' + formatQty(row.base_qty||0) + ' ' + (row.uom_code||'')) +
      '</option>'
    ).join('');
  }

  function renderLivePreviewShell(outputText, outputNoteText, usageHtml) {
    livePreviewWrap.classList.remove('d-none');
    liveOutput.textContent = outputText || '-';
    liveOutputNote.textContent = outputNoteText || 'Hasil jadi akan tampil otomatis setelah parameter produksi diisi.';
    liveUsage.innerHTML = usageHtml || 'Belum ada pemakaian bahan yang bisa dihitung.';
  }

  function renderLiveUsage(lines) {
    const usages = (Array.isArray(lines) ? lines : []).filter((l) => {
      const role = String(l.plan_role || '').toUpperCase();
      return role === 'MATERIAL_USAGE' || role === 'COMPONENT_USAGE';
    });
    if (!usages.length) return 'Belum ada pemakaian bahan yang bisa dihitung.';
    return usages.map((l) =>
      '<span class="badge text-bg-light border me-1 mb-1">' +
        escapeHtml(l.source_label||'-') + ': ' +
        escapeHtml(formatQty(l.required_qty||0)) + ' ' +
        escapeHtml(l.uom_code||'') +
      '</span>'
    ).join('');
  }

  function resetPreview(message) {
    currentPreview = null;
    previewBody.innerHTML = '';
    previewWrap.classList.add('d-none');
    previewEmpty.classList.remove('d-none');
    previewEmpty.textContent = message;
    previewIssues.classList.add('d-none');
    previewIssues.innerHTML = '';
    document.getElementById('batch-base-output').textContent = '-';
    document.getElementById('batch-scaling-summary').textContent = '-';
    document.getElementById('batch-total-input-cost').textContent = formatCurrency(0);
    document.getElementById('batch-estimated-unit-cost').textContent = formatCurrency(0);
    document.getElementById('batch-direct-component-count').textContent = '0';
    document.getElementById('batch-direct-material-count').textContent = '0';
    document.getElementById('batch-variable-cost').textContent = formatCurrency(0);
    outputQty.value = '';
    outputQtyLabel.value = '0,00';
    renderLivePreviewShell('-', message || 'Hasil jadi akan tampil otomatis setelah parameter produksi diisi.', 'Belum ada pemakaian bahan yang bisa dihitung.');
    saveButton.disabled = true;
  }

  function roleBadge(role) {
    const l = String(role || '').toUpperCase();
    if (l === 'INLINE_OUTPUT')           return '<span class="badge text-bg-warning component-batch-status">INLINE OUTPUT</span>';
    if (l === 'INLINE_COMPONENT_USAGE')  return '<span class="badge text-bg-info component-batch-status">INLINE USE</span>';
    if (l === 'COMPONENT_USAGE')         return '<span class="badge text-bg-primary component-batch-status">COMPONENT</span>';
    return '<span class="badge text-bg-secondary component-batch-status">MATERIAL</span>';
  }

  function statusBadge(isShort, label) {
    return '<span class="badge ' + (isShort ? 'text-bg-danger' : 'text-bg-success') + ' component-batch-status">' + escapeHtml(label || (isShort ? 'KURANG' : 'READY')) + '</span>';
  }

  function renderPreview(preview) {
    currentPreview = preview;
    const summary   = preview.summary || {};
    const component = preview.component || {};
    const lines     = Array.isArray(preview.lines) ? preview.lines : [];
    document.getElementById('batch-base-output').textContent = formatQty(preview.base_output_qty||0) + ' ' + escapeHtml(component.uom_code||'-');
    document.getElementById('batch-scaling-summary').textContent = String(preview.scaling_mode||'BATCH').toUpperCase() === 'REFERENCE'
      ? ('Acuan ' + formatQty((preview.reference||{}).actual_qty||0) + ' ' + escapeHtml((preview.reference||{}).uom_code||''))
      : (formatQty(preview.batch_count||0) + ' batch');
    document.getElementById('batch-total-input-cost').textContent = formatCurrency(summary.total_input_cost||0);
    document.getElementById('batch-estimated-unit-cost').textContent = formatCurrency(summary.unit_cost||0);
    document.getElementById('batch-direct-component-count').textContent = String(summary.direct_component_count||0);
    document.getElementById('batch-direct-material-count').textContent = String(summary.direct_material_count||0);
    document.getElementById('batch-variable-cost').textContent = formatCurrency(summary.variable_cost_total||0);
    outputUom.value = String(component.uom_id||'');
    outputUomLabel.value = [component.uom_code||'', component.uom_name||''].filter(Boolean).join(' - ') || 'Ikuti output component';
    outputQty.value = String(preview.output_qty||'');
    outputQtyLabel.value = formatQty(preview.output_qty||0) + ' ' + (component.uom_code||'');
    renderLivePreviewShell(
      formatQty(preview.output_qty||0) + ' ' + (component.uom_code||''),
      String(preview.scaling_mode||'BATCH').toUpperCase() === 'REFERENCE'
        ? 'Output dihitung dari qty bahan acuan aktual.'
        : 'Output dihitung dari kelipatan batch resep dasar.',
      renderLiveUsage(lines)
    );
    renderReferenceOptions(preview.reference_options||[], (preview.reference||{}).line_no||'');
    if (Array.isArray(preview.issues) && preview.issues.length) {
      previewIssues.classList.remove('d-none');
      previewIssues.innerHTML = '<div class="alert alert-danger mb-0"><strong>Batch tertolak jika diposting.</strong><ul class="mb-0 mt-2">' +
        preview.issues.map((i) => '<li>' + escapeHtml(i) + '</li>').join('') + '</ul></div>';
    } else {
      previewIssues.classList.add('d-none');
      previewIssues.innerHTML = '';
    }
    previewBody.innerHTML = lines.map((line) => {
      const stageName   = String(line.stage_component_name || component.component_name || '-');
      const stagePrefix = Number(line.depth||0) > 0 ? 'Inline' : 'Output';
      return '<tr>' +
        '<td><span class="badge text-bg-light border component-batch-stage">' + escapeHtml(stagePrefix + ' ' + stageName) + '</span></td>' +
        '<td>' + roleBadge(line.plan_role) + '</td>' +
        '<td>' + escapeHtml(line.source_label||'-') + '</td>' +
        '<td class="text-end">' + escapeHtml(formatQty(line.required_qty||0)) + ' ' + escapeHtml(line.uom_code||'') + '</td>' +
        '<td class="text-end">' + escapeHtml(formatQty(line.available_qty||0)) + ' ' + escapeHtml(line.uom_code||'') + '</td>' +
        '<td class="text-end">' + escapeHtml(formatCurrency(line.unit_cost||0)) + '</td>' +
        '<td class="text-end fw-semibold">' + escapeHtml(formatCurrency(line.total_cost||0)) + '</td>' +
        '<td class="text-center">' + statusBadge(Boolean(line.is_short), line.status_label) + '</td>' +
        '<td class="small text-muted">' + escapeHtml(line.notes||'') + '</td>' +
      '</tr>';
    }).join('');
    previewWrap.classList.toggle('d-none', !lines.length);
    previewEmpty.classList.toggle('d-none', !!lines.length);
    previewEmpty.textContent = lines.length ? '' : 'Belum ada plan produksi yang bisa ditampilkan.';
    saveButton.disabled = !lines.length || Boolean(summary.has_shortage);
  }

  async function loadPreview() {
    const requestToken           = ++previewRequestToken;
    const componentId            = String(outputComponentId.value || '');
    const locationType           = String(locationTypeInput.value || '');
    const mode                   = String(scalingMode.value || 'BATCH').toUpperCase();
    const batchCountValue        = parseFloat(batchCount.value || '0') || 0;
    const referenceActualQtyValue = parseFloat(referenceActualQty.value || '0') || 0;
    if (!componentId) { resetPreview('Pilih output component terlebih dahulu.'); return; }
    if (!locationType) { resetPreview('Pilih lokasi Reguler atau Event agar plan produksi bisa dihitung.'); return; }
    if (mode === 'REFERENCE') {
      if (!String(referenceLineNo.value||'')) { resetPreview('Pilih bahan acuan untuk melihat preview.'); return; }
      if (!(referenceActualQtyValue > 0)) { resetPreview('Isi qty aktual bahan acuan.'); return; }
    } else if (!(batchCountValue > 0)) {
      resetPreview('Isi jumlah batch untuk melihat preview.'); return;
    }
    try {
      if (previewRequestController) previewRequestController.abort();
      previewRequestController = new AbortController();
      const query = new URLSearchParams({
        component_id: componentId, location_type: locationType,
        batch_date: String(batchDateInput?.value || ''),
        scaling_mode: mode,
        batch_count: batchCountValue > 0 ? String(batchCountValue) : '',
        reference_line_no: String(referenceLineNo.value||''),
        reference_actual_qty: referenceActualQtyValue > 0 ? String(referenceActualQtyValue) : ''
      });
      const response = await fetch(previewUrl + '?' + query.toString(), {
        headers: {'X-Requested-With': 'XMLHttpRequest'}, signal: previewRequestController.signal
      });
      const text = await response.text();
      let json;
      try { json = JSON.parse(text); } catch(e) { throw new Error('Server mengembalikan respons preview non-JSON. Muat ulang halaman lalu coba kembali.'); }
      if (!response.ok || !json.ok) throw new Error(json.message || 'Preview batch gagal dimuat.');
      if (requestToken !== previewRequestToken) return;
      renderPreview(json);
      clearAlert();
    } catch(error) {
      if (error && error.name === 'AbortError') return;
      if (requestToken !== previewRequestToken) return;
      resetPreview(error.message || 'Gagal memuat preview batch.');
      renderAlert('danger', error.message || 'Gagal memuat preview batch.');
    } finally {
      if (requestToken === previewRequestToken) previewRequestController = null;
    }
  }

  function schedulePreview() {
    window.clearTimeout(previewTimer);
    previewTimer = window.setTimeout(loadPreview, 180);
  }

  function bindOutputPicker() {
    window.ProductionAjaxPicker.bind(outputComponentSearch, {
      entity: 'COMPONENT',
      params: () => ({
        batch_date: String(batchDateInput?.value || ''),
        location_type: String(locationTypeInput?.value || ''),
        division_id: String(divisionIdInput?.value || ''),
      }),
      renderLabel: pickerLabel,
      renderSubLabel: pickerSubLabel,
      onType: () => {
        outputComponentId.value = ''; outputUom.value = '';
        outputUomLabel.value = 'Ikuti output component';
        outputQty.value = ''; outputQtyLabel.value = '0,00';
        divisionIdInput.value = ''; outputDivisionCode = ''; outputDivisionName = '';
        renderReferenceOptions([], '');
        syncBatchDivisionState();
        resetPreview('Pilih output component terlebih dahulu.');
      },
      onSelect: (result) => {
        outputComponentId.value = String(result.id||'');
        outputComponentSearch.value = pickerLabel(result);
        outputUom.value = String(result.uom_id||'');
        outputUomLabel.value = [result.uom_code||'', result.uom_name||''].filter(Boolean).join(' - ') || 'Ikuti output component';
        divisionIdInput.value = String(result.operational_division_id||'');
        outputDivisionCode = String(result.division_code||'');
        outputDivisionName = String(result.division_name||'');
        syncBatchDivisionState();
        schedulePreview();
      }
    });
  }

  scalingMode?.addEventListener('change', () => { syncScalingMode(); schedulePreview(); });
  batchCount?.addEventListener('input', schedulePreview);
  batchCount?.addEventListener('change', schedulePreview);
  batchDateInput?.addEventListener('change', schedulePreview);
  referenceLineNo?.addEventListener('change', schedulePreview);
  referenceActualQty?.addEventListener('input', schedulePreview);
  referenceActualQty?.addEventListener('change', schedulePreview);
  locationGroupInput?.addEventListener('change', () => { syncBatchDivisionState(); schedulePreview(); });

  // ── Button state helpers ──────────────────────────────────────────────────
  function setButtonBusy(button, label) {
    if (!button) return;
    if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') {
      window.FinanceUI.setButtonLoading(button, label); return;
    }
    button.disabled = true;
  }
  function clearButtonBusy(button) {
    if (!button) return;
    if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
      window.FinanceUI.clearButtonLoading(button); return;
    }
    button.disabled = false;
  }
  function showPostingOverlay(message) {
    if (!overlayEl) return;
    if (overlayTitleEl) overlayTitleEl.textContent = message || 'Memproses batch...';
    overlayEl.classList.add('is-active');
    overlayEl.setAttribute('aria-hidden', 'false');
  }
  function hidePostingOverlay() {
    if (!overlayEl) return;
    overlayEl.classList.remove('is-active');
    overlayEl.setAttribute('aria-hidden', 'true');
  }

  // ── Form submit ───────────────────────────────────────────────────────────
  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submitButton = event.submitter || form.querySelector('button[type="submit"]');
    const formData = new FormData(form);
    const payload = {
      batch_date: String(formData.get('batch_date')||''),
      location_type: String(formData.get('location_type')||''),
      division_id: String(formData.get('division_id')||''),
      component_id: String(formData.get('component_id')||''),
      output_qty: String(formData.get('output_qty')||''),
      output_uom_id: String(formData.get('output_uom_id')||''),
      scaling_mode: String(formData.get('scaling_mode')||'BATCH'),
      batch_count: String(formData.get('batch_count')||''),
      reference_line_no: String(formData.get('reference_line_no')||''),
      reference_actual_qty: String(formData.get('reference_actual_qty')||''),
      notes: String(formData.get('notes')||'')
    };
    if (!payload.component_id) { renderAlert('warning', 'Pilih output component melalui pencarian terlebih dahulu.'); return; }
    if (!payload.division_id)  { renderAlert('warning', 'Divisi batch belum terbentuk. Pilih output component yang valid.'); return; }
    if (!payload.location_type){ renderAlert('warning', 'Pilih lokasi Reguler atau Event terlebih dahulu.'); return; }
    if (String(payload.scaling_mode||'BATCH').toUpperCase() === 'REFERENCE') {
      if (!payload.reference_line_no) { renderAlert('warning', 'Pilih bahan acuan terlebih dahulu.'); return; }
      if (!(parseFloat(payload.reference_actual_qty||'0') > 0)) { renderAlert('warning', 'Qty aktual bahan acuan harus lebih dari 0.'); return; }
    } else if (!(parseFloat(payload.batch_count||'0') > 0)) {
      renderAlert('warning', 'Jumlah batch harus lebih dari 0.'); return;
    }
    if (!currentPreview) { renderAlert('warning', 'Preview batch belum siap. Lengkapi output component, lokasi, dan mode produksi terlebih dahulu.'); return; }
    if (currentPreview.summary && currentPreview.summary.has_shortage) { renderAlert('warning', 'Batch masih memiliki shortage. Perbaiki ketersediaan bahan terlebih dahulu.'); return; }
    window.clearTimeout(previewTimer);
    if (previewRequestController) { previewRequestController.abort(); previewRequestController = null; }
    setButtonBusy(submitButton, 'Menyimpan batch...');
    showPostingOverlay('Menyimpan batch...');
    try {
      await postJson(saveUrl, payload);
      window.location.reload();
    } catch(error) {
      hidePostingOverlay();
      renderAlert('danger', error.message || 'Gagal menyimpan batch.');
      clearButtonBusy(submitButton);
    }
  });

  // ── Reset form when modal is closed ──────────────────────────────────────
  batchFormModalEl?.addEventListener('hidden.bs.modal', () => {
    form?.reset();
    outputComponentId.value = ''; outputUom.value = '';
    outputUomLabel.value = 'Ikuti output component';
    outputQty.value = ''; outputQtyLabel.value = '0,00';
    divisionIdInput.value = ''; outputDivisionCode = ''; outputDivisionName = '';
    renderReferenceOptions([], '');
    syncBatchDivisionState();
    syncScalingMode();
    resetPreview('Pilih output component, lokasi, dan mode produksi untuk melihat preview produksi.');
  });

  function getStepUpModal(element) {
    return element && window.bootstrap && window.bootstrap.Modal
      ? window.bootstrap.Modal.getOrCreateInstance(element)
      : null;
  }

  function setStepUpDismissDisabled(element, disabled) {
    element?.querySelectorAll('[data-bs-dismiss="modal"]').forEach((dismissButton) => {
      dismissButton.disabled = disabled;
    });
  }

  async function postVerifiedBatch(button, batchId, proof) {
    window.clearTimeout(previewTimer);
    if (previewRequestController) { previewRequestController.abort(); previewRequestController = null; }
    setButtonBusy(button, 'Posting...');
    showPostingOverlay('Posting batch produksi...');
    const requestPromise = postJson(postBaseUrl + '/' + batchId, {step_up_proof: proof});
    const postedViaPolling = await Promise.race([
      requestPromise.then(() => false),
      waitForBatchPosted(batchId, {startDelayMs:4000, intervalMs:2500, maxMs:60000})
    ]);
    let postResult = null;
    if (postedViaPolling === true) {
      try { postResult = await Promise.race([requestPromise, new Promise((r) => setTimeout(() => r(null), 8000))]); } catch(e) {}
    } else {
      postResult = await requestPromise;
    }
    const recoveryWarnings = (postResult && postResult.data && Array.isArray(postResult.data.recovery_warnings))
      ? postResult.data.recovery_warnings : [];
    try {
      sessionStorage.setItem('batchPostNotif', JSON.stringify({
        type: recoveryWarnings.length > 0 ? 'warning' : 'success',
        warnings: recoveryWarnings
      }));
    } catch(e) {}
    window.location.reload();
  }

  // ── Post ──────────────────────────────────────────────────────────────────
  document.querySelectorAll('.btn-post').forEach((button) => {
    button.addEventListener('click', async () => {
      button.blur();
      if (!(await uiConfirm('Posting batch akan mengurangi input dan menambah stok output component.', {
        title: 'Post Batch Produksi', okText: 'Post Batch', cancelText: 'Batal'
      }))) return;
      const batchId = Number(button.dataset.id || 0);
      const modal = getStepUpModal(batchStepUpModalEl);
      if (!(batchId > 0) || !modal) {
        renderAlert('danger', 'Verifikasi posting belum siap. Muat ulang halaman lalu coba kembali.');
        return;
      }
      pendingBatchPostId = batchId;
      pendingBatchPostButton = button;
      if (batchStepUpPassword) batchStepUpPassword.value = '';
      modal.show();
      window.setTimeout(() => batchStepUpPassword?.focus(), 150);
    });
  });

  btnBatchStepUpPost?.addEventListener('click', async () => {
    if (batchStepUpSubmitting) return;
    const batchId = pendingBatchPostId;
    const button = pendingBatchPostButton;
    try {
      if (!(batchId > 0) || !button) throw new Error('Dokumen batch tidak valid. Tutup modal lalu coba lagi.');
      const password = String(batchStepUpPassword?.value || '');
      if (password === '') throw new Error('Masukkan password Anda untuk memverifikasi posting batch.');
      if (batchStepUpPassword) batchStepUpPassword.value = '';
      batchStepUpSubmitting = true;
      setStepUpDismissDisabled(batchStepUpModalEl, true);
      setButtonBusy(btnBatchStepUpPost, 'Memverifikasi...');
      const stepUp = await postJson(componentBatchStepUpUrl, {batch_id: batchId, password});
      if (!/^[0-9a-f]{64}$/.test(String(stepUp.step_up_proof || ''))) throw new Error('Bukti verifikasi ulang tidak valid. Coba lagi.');
      await postVerifiedBatch(button, String(batchId), String(stepUp.step_up_proof));
    } catch (error) {
      hidePostingOverlay();
      renderAlert('danger', error.message || 'Gagal post batch.');
      batchStepUpSubmitting = false;
      setStepUpDismissDisabled(batchStepUpModalEl, false);
      clearButtonBusy(button);
      clearButtonBusy(btnBatchStepUpPost);
    }
  });

  batchStepUpModalEl?.addEventListener('hidden.bs.modal', () => {
    if (batchStepUpSubmitting) return;
    if (batchStepUpPassword) batchStepUpPassword.value = '';
    clearButtonBusy(btnBatchStepUpPost);
    if (pendingBatchPostButton) clearButtonBusy(pendingBatchPostButton);
    pendingBatchPostId = 0;
    pendingBatchPostButton = null;
  });

  // ── Delete ────────────────────────────────────────────────────────────────
  document.querySelectorAll('.btn-del').forEach((button) => {
    button.addEventListener('click', async () => {
      button.blur();
      if (!(await uiConfirm('Draft batch ini akan dihapus permanen.', {
        title: 'Hapus Draft Batch', okText: 'Hapus Draft', cancelText: 'Batal'
      }))) return;
      setButtonBusy(button, 'Menghapus...');
      showPostingOverlay('Menghapus draft batch...');
      try {
        await postJson(deleteBaseUrl + '/' + button.dataset.id, {});
        window.location.reload();
      } catch(error) {
        hidePostingOverlay();
        renderAlert('danger', error.message || 'Gagal menghapus batch.');
        clearButtonBusy(button);
      }
    });
  });

  // ── Void ──────────────────────────────────────────────────────────────────
  document.querySelectorAll('.btn-void').forEach((button) => {
    button.addEventListener('click', async () => {
      button.blur();
      if (!(await uiConfirm('VOID hanya bisa dilakukan jika output batch belum dipakai. Lanjutkan?', {
        title: 'Void Batch Produksi', okText: 'Void Batch', cancelText: 'Batal'
      }))) return;
      const batchId = Number(button.dataset.id || 0);
      const modal = getStepUpModal(batchVoidStepUpModalEl);
      if (!(batchId > 0) || !modal) {
        renderAlert('danger', 'Verifikasi VOID belum siap. Muat ulang halaman lalu coba kembali.');
        return;
      }
      pendingBatchVoidId = batchId;
      pendingBatchVoidButton = button;
      if (batchVoidStepUpPassword) batchVoidStepUpPassword.value = '';
      modal.show();
      window.setTimeout(() => batchVoidStepUpPassword?.focus(), 150);
    });
  });

  btnBatchVoidStepUpPost?.addEventListener('click', async () => {
    if (batchVoidStepUpSubmitting) return;
    const batchId = pendingBatchVoidId;
    const button = pendingBatchVoidButton;
    try {
      if (!(batchId > 0) || !button) throw new Error('Dokumen batch tidak valid. Tutup modal lalu coba lagi.');
      const password = String(batchVoidStepUpPassword?.value || '');
      if (password === '') throw new Error('Masukkan password Anda untuk memverifikasi VOID batch.');
      if (batchVoidStepUpPassword) batchVoidStepUpPassword.value = '';
      batchVoidStepUpSubmitting = true;
      setStepUpDismissDisabled(batchVoidStepUpModalEl, true);
      setButtonBusy(btnBatchVoidStepUpPost, 'Memverifikasi...');
      const stepUp = await postJson(componentBatchVoidStepUpUrl, {batch_id: batchId, password});
      if (!/^[0-9a-f]{64}$/.test(String(stepUp.step_up_proof || ''))) throw new Error('Bukti verifikasi ulang tidak valid. Coba lagi.');
      setButtonBusy(button, 'VOID...');
      showPostingOverlay('Melakukan VOID batch...');
      await postJson(voidBaseUrl + '/' + batchId, {step_up_proof: String(stepUp.step_up_proof)});
      window.location.reload();
    } catch(error) {
      hidePostingOverlay();
      renderAlert('danger', error.message || 'Gagal VOID batch.');
      batchVoidStepUpSubmitting = false;
      setStepUpDismissDisabled(batchVoidStepUpModalEl, false);
      clearButtonBusy(button);
      clearButtonBusy(btnBatchVoidStepUpPost);
    }
  });

  batchVoidStepUpModalEl?.addEventListener('hidden.bs.modal', () => {
    if (batchVoidStepUpSubmitting) return;
    if (batchVoidStepUpPassword) batchVoidStepUpPassword.value = '';
    clearButtonBusy(btnBatchVoidStepUpPost);
    if (pendingBatchVoidButton) clearButtonBusy(pendingBatchVoidButton);
    pendingBatchVoidId = 0;
    pendingBatchVoidButton = null;
  });

  // ── Usage detail ──────────────────────────────────────────────────────────
  function renderUsageDetail(detail) {
    const traceRows     = Array.isArray(detail.trace_rows)     ? detail.trace_rows     : [];
    const materialInputs = Array.isArray(detail.material_inputs) ? detail.material_inputs : [];
    const movementUsages = Array.isArray(detail.movement_usages) ? detail.movement_usages : [];
    const batchUsages   = Array.isArray(detail.batch_usages)   ? detail.batch_usages   : [];
    const lotIssueUsages = Array.isArray(detail.lot_issue_usages) ? detail.lot_issue_usages : [];
    const header       = detail.header || {};
    const blockReason  = String(detail.block_reason || '');
    const voidProjection = detail.void_projection || {};
    const detailUrl    = usageDetailBaseUrl + '/' + String(header.id || '0');
    const summaryBadge = detail.can_void
      ? '<span class="badge text-bg-success">Batch masih bisa di-void</span>'
      : '<span class="badge text-bg-warning" title="' + escapeHtml(blockReason) + '">Tidak bisa di-void</span>';
    const voidProjectionAlert = detail.can_void && voidProjection.available && voidProjection.would_go_negative
      ? '<div class="alert alert-warning mt-2 mb-0">' +
          'Void tetap boleh, tetapi saldo global component akan minus sementara. ' +
          'Stok global saat ini <strong>' + escapeHtml(formatQty(voidProjection.current_global_qty || 0)) + '</strong>, ' +
          'rollback batch <strong>' + escapeHtml(formatQty(voidProjection.rollback_qty || 0)) + '</strong>, ' +
          'proyeksi setelah void <strong>' + escapeHtml(formatQty(voidProjection.projected_global_qty_after_void || 0)) + '</strong>.' +
        '</div>'
      : '';

    usageModalBody.innerHTML =
      '<div class="mb-3">' +
        '<div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">' +
          '<div>' +
            '<div class="fw-semibold">' + escapeHtml(header.batch_no||'-') + ' • ' + escapeHtml(header.component_name||'-') + '</div>' +
            '<div class="small text-muted">Tanggal ' + escapeHtml(header.batch_date||'-') + ' • Qty ' + escapeHtml(formatQty(header.output_qty||0)) + ' ' + escapeHtml(header.uom_code||'') + '</div>' +
            '<div class="mt-2">' + summaryBadge + '</div>' +
          '</div>' +
          '<div><a href="' + escapeHtml(detailUrl) + '" class="btn btn-sm btn-outline-info"><i class="ri ri-eye-line me-1"></i>Buka Detail</a></div>' +
        '</div>' +
        voidProjectionAlert +
        (blockReason ? '<div class="alert alert-warning mt-2 mb-0">' + escapeHtml(blockReason) + '</div>' : '') +
      '</div>' +
      '<div class="mb-3"><h6 class="mb-2">Input Bahan Baku Batch Ini</h6>' +
        (materialInputs.length
          ? '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Line</th><th>Bahan</th><th class="text-end">Qty</th><th class="text-end">Total Cost</th><th>No FIFO</th></tr></thead><tbody>' +
            materialInputs.map((r) => '<tr><td>' + escapeHtml(String(r.line_no||0)) + '</td><td>' + escapeHtml(r.material_label||'-') + '</td><td class="text-end">' + escapeHtml(formatQty(r.qty||0)) + ' ' + escapeHtml(r.uom_code||'') + '</td><td class="text-end">' + escapeHtml(formatCurrency(r.total_cost||0)) + '</td><td>' + escapeHtml(r.fifo_issue_no||'-') + '</td></tr>').join('') +
            '</tbody></table></div>'
          : '<div class="text-muted small">Batch ini tidak memakai bahan baku langsung atau trace input bahan belum tersedia.</div>') +
      '</div>' +
      '<div class="mb-3"><h6 class="mb-2">Trace Produksi Batch Ini</h6>' +
        (traceRows.length
          ? '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Tahap</th><th>Komponen</th><th>Jenis</th><th class="text-end">Qty In</th><th class="text-end">Qty Out</th></tr></thead><tbody>' +
            traceRows.map((r) => '<tr><td>' + escapeHtml(r.trace_label||'-') + '</td><td>' + escapeHtml(r.component_name||'-') + '</td><td>' + escapeHtml(r.movement_type_label||r.movement_type||'-') + '</td><td class="text-end">' + escapeHtml(formatQty(r.qty_in||0)) + ' ' + escapeHtml(r.uom_code||'') + '</td><td class="text-end">' + escapeHtml(formatQty(r.qty_out||0)) + ' ' + escapeHtml(r.uom_code||'') + '</td></tr>').join('') +
            '</tbody></table></div>'
          : '<div class="text-muted small">Belum ada trace posting batch yang tersimpan.</div>') +
      '</div>' +
      '<div class="mb-3"><h6 class="mb-2">Dokumen yang memakai output batch</h6>' +
        (batchUsages.length
          ? '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Batch</th><th>Tanggal</th><th>Output</th><th class="text-end">Qty Pakai</th></tr></thead><tbody>' +
            batchUsages.map((r) => '<tr><td>' + escapeHtml(r.batch_no||'-') + '</td><td>' + escapeHtml(r.batch_date||'-') + '</td><td>' + escapeHtml(r.output_component_name||'-') + '</td><td class="text-end">' + escapeHtml(formatQty(r.qty||0)) + ' ' + escapeHtml(r.uom_code||'') + '</td></tr>').join('') +
            '</tbody></table></div>'
          : '<div class="text-muted small">Belum ada batch lain yang memakai output component ini sebagai input.</div>') +
      '</div>' +
      '<div class="mb-3"><h6 class="mb-2">Movement keluar setelah batch ini</h6>' +
        (movementUsages.length
          ? '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>No Movement</th><th>Tanggal</th><th>Jenis</th><th>Sumber</th><th class="text-end">Qty Out</th></tr></thead><tbody>' +
            movementUsages.map((r) => '<tr><td>' + escapeHtml(r.movement_no||'-') + '</td><td>' + escapeHtml(r.movement_date||'-') + '</td><td>' + escapeHtml(r.movement_type_label||r.movement_type||'-') + '</td><td>' + escapeHtml((r.source_module||'-') + (r.source_id ? (' #' + r.source_id) : '')) + '</td><td class="text-end">' + escapeHtml(formatQty(r.qty_out||0)) + '</td></tr>').join('') +
            '</tbody></table></div>'
          : '<div class="text-muted small">Belum ada movement keluar yang memakai output batch ini.</div>') +
      '</div>' +
      '<div><h6 class="mb-2">Issue FIFO dari lot output batch ini</h6>' +
        (lotIssueUsages.length
          ? '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>No Issue</th><th>Tanggal</th><th>Sumber</th><th>Catatan</th><th class="text-end">Qty Out</th></tr></thead><tbody>' +
            lotIssueUsages.map((r) => '<tr><td>' + escapeHtml(r.issue_no||'-') + '</td><td>' + escapeHtml(r.issue_date||'-') + '</td><td>' + escapeHtml((r.source_module||'-') + (r.source_id ? (' #' + r.source_id) : '')) + '</td><td>' + escapeHtml(r.notes||'-') + '</td><td class="text-end">' + escapeHtml(formatQty(r.qty_out||0)) + '</td></tr>').join('') +
            '</tbody></table></div>'
          : '<div class="text-muted small">Belum ada issue FIFO yang mengambil lot output batch ini.</div>') +
      '</div>';
  }

  document.querySelectorAll('.btn-usage').forEach((button) => {
    button.addEventListener('click', async () => {
      button.blur();
      if (!usageModal || !usageModalBody) return;
      usageModalBody.innerHTML = '<div class="text-muted">Memuat detail pemakaian batch...</div>';
      usageModal.show();
      try {
        const response = await fetch(usageBaseUrl + '/' + button.dataset.id, {headers:{'X-Requested-With':'XMLHttpRequest'}});
        const text = await response.text();
        let json;
        try { json = JSON.parse(text); } catch(e) { throw new Error('Respons detail usage bukan JSON valid.'); }
        if (!response.ok || !json.ok) throw new Error(json.message || 'Gagal memuat detail usage batch.');
        renderUsageDetail(json);
      } catch(error) {
        usageModalBody.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(error.message||'Gagal memuat detail usage batch.') + '</div>';
      }
    });
  });

  // ── Highlight from hash ───────────────────────────────────────────────────
  const hash = String(window.location.hash || '').trim();
  if (hash.indexOf('#component-batch-') === 0) {
    const target = document.querySelector(hash);
    if (target) { target.classList.add('table-warning'); target.scrollIntoView({behavior:'smooth', block:'center'}); }
  }

  // ── Bind picker + init ────────────────────────────────────────────────────
  bindOutputPicker();
  syncBatchDivisionState();
  syncScalingMode();
  resetPreview('Pilih output component, lokasi, dan mode produksi untuk melihat preview produksi.');

  // ── Client-side search + pagination ───────────────────────────────────────
  const tbody       = document.getElementById('batchTbody');
  const searchInput = document.getElementById('searchBatch');
  const perPageSel  = document.getElementById('perPage');
  const infoEl      = document.getElementById('batchInfo');
  const paginEl     = document.getElementById('batchPagination');

  let currentPage = 1;

  function allRows() {
    return Array.from(tbody.querySelectorAll('tr[data-batch-no]'));
  }

  function filteredRows() {
    const term = (searchInput?.value || '').trim().toLowerCase();
    if (!term) return allRows();
    return allRows().filter((tr) => {
      return tr.dataset.batchNo.includes(term) || tr.dataset.component.includes(term);
    });
  }

  function renderPage() {
    const rows    = filteredRows();
    const perPage = parseInt(perPageSel?.value || '25', 10);
    const total   = rows.length;
    const pages   = perPage > 0 ? Math.max(1, Math.ceil(total / perPage)) : 1;
    if (currentPage > pages) currentPage = pages;

    allRows().forEach((tr) => tr.style.display = 'none');

    const noRowsEl = tbody.querySelector('.cb-no-rows');
    if (noRowsEl) noRowsEl.style.display = total === 0 ? '' : 'none';

    if (total > 0) {
      const start = perPage > 0 ? (currentPage - 1) * perPage : 0;
      const end   = perPage > 0 ? Math.min(start + perPage, total) : total;
      rows.slice(start, end).forEach((tr) => tr.style.display = '');

      if (infoEl) {
        infoEl.textContent = perPage > 0
          ? 'Menampilkan ' + (start + 1) + '–' + end + ' dari ' + total + ' batch'
          : 'Semua ' + total + ' batch ditampilkan';
      }
    } else {
      if (infoEl) infoEl.textContent = 'Tidak ada batch yang cocok.';
    }

    // Render pagination
    if (!paginEl) return;
    paginEl.innerHTML = '';
    if (perPage <= 0 || pages <= 1) return;

    const mkBtn = (label, page, disabled, active) => {
      const li = document.createElement('li');
      li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
      const a = document.createElement('a');
      a.className = 'page-link';
      a.href = '#';
      a.innerHTML = label;
      if (!disabled && !active) {
        a.addEventListener('click', (e) => { e.preventDefault(); currentPage = page; renderPage(); });
      }
      li.appendChild(a);
      return li;
    };

    paginEl.appendChild(mkBtn('&laquo;', currentPage - 1, currentPage <= 1, false));

    // Show at most 7 page buttons with ellipsis
    const rangeStart = Math.max(1, currentPage - 2);
    const rangeEnd   = Math.min(pages, currentPage + 2);
    if (rangeStart > 1) {
      paginEl.appendChild(mkBtn('1', 1, false, false));
      if (rangeStart > 2) paginEl.appendChild(mkBtn('…', null, true, false));
    }
    for (let p = rangeStart; p <= rangeEnd; p++) {
      paginEl.appendChild(mkBtn(String(p), p, false, p === currentPage));
    }
    if (rangeEnd < pages) {
      if (rangeEnd < pages - 1) paginEl.appendChild(mkBtn('…', null, true, false));
      paginEl.appendChild(mkBtn(String(pages), pages, false, false));
    }

    paginEl.appendChild(mkBtn('&raquo;', currentPage + 1, currentPage >= pages, false));
  }

  searchInput?.addEventListener('input', () => { currentPage = 1; renderPage(); });
  perPageSel?.addEventListener('change', () => { currentPage = 1; renderPage(); });

  renderPage();
})();
</script>
