<?php
$baseUrl = site_url('master/' . $entity);
$queryBase = [
    'q' => $q,
    'status' => $status,
    'per_page' => $per_page,
];
$listFilterDefinitions = is_array($list_filter_definitions ?? null) ? $list_filter_definitions : [];
$listFilterOptions = is_array($list_filter_options ?? null) ? $list_filter_options : [];
$listFilterValues = is_array($list_filter_values ?? null) ? $list_filter_values : [];
foreach ($listFilterValues as $filterName => $filterValue) {
  if ($filterValue !== null && $filterValue !== '') {
    $queryBase[$filterName] = $filterValue;
  }
}
$isPayrollMaster = in_array($entity, ['pay-component', 'pay-profile', 'pay-profile-line', 'pay-assignment'], true);
$useLargeActionIcons = in_array($entity, ['product', 'extra', 'extra-group', 'product-division', 'product-classification', 'product-category'], true);
$isProductHierarchyMaster = in_array($entity, ['product-division', 'product-classification', 'product-category'], true);
$isReorderableMaster = !empty($cfg['reorderable']);
$canDragReorder = $isReorderableMaster && $q === '' && (int)$total > 1 && (int)$total <= (int)$per_page;
$resetStatus = (string)($cfg['default_status'] ?? (!empty($has_active_flag) ? 'active' : 'all'));
$masterMutationCsrfToken = (string)($master_mutation_csrf_token ?? '');

$buildPageItems = static function (int $page, int $totalPages): array {
    if ($totalPages <= 7) {
        return range(1, $totalPages);
    }

    $items = [1];
    $start = max(2, $page - 1);
    $end = min($totalPages - 1, $page + 1);

    if ($start > 2) {
        $items[] = '...';
    }

    for ($i = $start; $i <= $end; $i++) {
        $items[] = $i;
    }

    if ($end < $totalPages - 1) {
        $items[] = '...';
    }

    $items[] = $totalPages;

    return $items;
};
?>

<?php if ($isPayrollMaster): ?>
<style>
  .master-index--payroll .master-title {
    font-size: 1.52rem;
    letter-spacing: 0.01em;
  }
  .master-index--payroll .master-filter .form-control,
  .master-index--payroll .master-filter .form-select,
  .master-index--payroll .master-filter .btn {
    min-height: 40px;
  }
  .master-index--payroll .master-card {
    border: 0;
    box-shadow: 0 0.25rem 0.9rem rgba(67, 30, 30, 0.08);
  }
  .master-index--payroll .master-table thead th {
    background: #f5f6f8 !important;
    color: #4a4458 !important;
    border-color: #ebe7ef !important;
    text-align: left !important;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.73rem;
  }
  .master-index--payroll .master-table th,
  .master-index--payroll .master-table td {
    padding: 0.72rem 0.88rem;
    vertical-align: middle;
  }
  .master-index--payroll .master-table td {
    font-size: 0.92rem;
  }
  .master-index--payroll .master-table td.number-cell {
    text-align: right !important;
  }
  .master-index--payroll .master-table td.action-cell {
    text-align: center !important;
  }
  .master-index--payroll .payroll-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: nowrap;
  }
  .master-index--payroll .payroll-actions .action-icon-btn {
    width: 34px;
    min-width: 34px;
    height: 34px;
    border-radius: 9px;
  }
  .master-index--payroll .master-footer {
    border-top: 1px solid #efe8ee;
    padding-top: 0.72rem;
    padding-bottom: 0.72rem;
  }
</style>
<?php endif; ?>

<?php if ($useLargeActionIcons): ?>
<style>
  .master-index--action-upgrade .master-action-group {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: nowrap;
  }
  .master-index--action-upgrade .master-action-group .action-icon-btn {
    width: 36px !important;
    min-width: 36px !important;
    height: 36px !important;
    border-radius: 10px !important;
  }
  .master-index--action-upgrade .master-action-group .action-icon-btn i,
  .master-index--action-upgrade .master-action-group .action-icon-btn [class^="ri-"],
  .master-index--action-upgrade .master-action-group .action-icon-btn [class*=" ri-"] {
    font-size: 1.05rem !important;
  }
  .master-index--action-upgrade .master-inline-pill {
    min-width: 94px;
    border-radius: 999px;
    font-weight: 600;
  }
</style>
<?php endif; ?>

<?php if ($isProductHierarchyMaster): ?>
<style>
  .master-index--product-hierarchy .master-card {
    border: 0;
    box-shadow: 0 0.3rem 1rem rgba(103, 73, 61, 0.08);
  }
  .master-index--product-hierarchy .master-table thead th {
    background: linear-gradient(90deg, #8d0f1d 0%, #b21829 100%) !important;
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.18) !important;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.73rem;
  }
  .master-index--product-hierarchy .master-table td {
    padding-top: 0.78rem;
    padding-bottom: 0.78rem;
    vertical-align: middle;
  }
  .master-index--product-hierarchy .master-name-cell {
    font-weight: 600;
    color: #46342b;
  }
  .master-index--product-hierarchy .master-count-badge {
    display: inline-flex;
    min-width: 42px;
    justify-content: center;
    align-items: center;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
    background: #fef3e8;
    border: 1px solid #f1d3b5;
    color: #9b4d16;
    font-weight: 600;
    font-size: 0.84rem;
  }
  .master-index--product-hierarchy .master-scope-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.55rem;
    border-radius: 999px;
    background: #eef6ff;
    border: 1px solid #cfe2ff;
    color: #2458a6;
    font-weight: 600;
    font-size: 0.8rem;
  }
  .master-index--product-hierarchy .master-action-group .action-icon-btn {
    width: 36px;
    min-width: 36px;
    height: 36px;
    border-radius: 10px;
  }
</style>
<?php endif; ?>

<?php if ($entity === 'product'): ?>
<style>
  .master-index--product-scroll .master-table-wrap {
    max-height: calc(100vh - 280px);
    overflow: auto;
  }
  .master-index--product-scroll .master-table-wrap table {
    margin-bottom: 0;
  }
  .master-index--product-scroll .master-table-wrap thead th {
    position: sticky;
    top: 0;
    z-index: 5;
    background: #fff;
    box-shadow: inset 0 -1px 0 rgba(222, 226, 230, 0.9);
  }
  .master-index--product-scroll .master-table-wrap thead th:last-child {
    z-index: 6;
  }
  @media (max-width: 991.98px) {
    .master-index--product-scroll .master-table-wrap {
      max-height: calc(100vh - 240px);
    }
  }
</style>
<?php endif; ?>

<?php if ($entity === 'extra-group'): ?>
<style>
  #extraGroupRelationModal .modal-dialog {
    max-width: min(1280px, calc(100vw - 2rem));
    margin: 1rem auto;
  }
  #extraGroupRelationModal .modal-content {
    max-height: calc(100vh - 2rem);
    overflow: hidden;
  }
  #extraGroupRelationModal .modal-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    min-height: 0;
    overflow: hidden;
  }
  #extraGroupRelationModal #extraGroupRelationTableWrap {
    max-height: calc(100vh - 18rem);
    overflow: auto;
  }
  #extraGroupRelationModal #extraGroupRelationTableWrap table {
    margin-bottom: 0;
  }
</style>
<?php endif; ?>

<?php if ($isReorderableMaster): ?>
<style>
  .master-reorder-note {
    border: 1px dashed #d7c7c2;
    border-radius: 12px;
    background: #fffaf7;
    padding: 0.85rem 1rem;
    margin-bottom: 1rem;
  }
  .master-reorder-handle {
    width: 34px;
    color: #8c7a73;
    cursor: grab;
    text-align: center;
    user-select: none;
  }
  .master-reorder-handle i {
    font-size: 1rem;
  }
  .master-sort-row.is-dragging {
    opacity: 0.55;
  }
  .master-sort-row.drag-over-top {
    box-shadow: inset 0 3px 0 #c0392b;
  }
  .master-sort-row.drag-over-bottom {
    box-shadow: inset 0 -3px 0 #c0392b;
  }
</style>
<?php endif; ?>

<div data-master-root data-master-entity="<?php echo html_escape($entity); ?>" data-master-reorder-url="<?php echo html_escape(site_url('master/' . $entity . '/reorder')); ?>" data-master-mutation-csrf="<?php echo html_escape($masterMutationCsrfToken); ?>" class="master-index <?php echo $isPayrollMaster ? 'master-index--payroll' : ''; ?> <?php echo $useLargeActionIcons ? 'master-index--action-upgrade' : ''; ?> <?php echo $isProductHierarchyMaster ? 'master-index--product-hierarchy' : ''; ?> <?php echo $entity === 'product' ? 'master-index--product-scroll' : ''; ?>">
<?php if ($entity === 'extra' || $entity === 'extra-group'): ?>
  <?php $extraTabActive = $entity === 'extra' ? 'master-extra' : 'extra-group'; ?>
  <?php $this->load->view('master/_extra_tabs', compact('extraTabActive')); ?>
<?php endif; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h4 class="mb-1 master-title"><?php echo html_escape($cfg['title']); ?></h4>
    <small class="text-muted" id="masterTotalText">Total data: <?php echo (int)$total; ?></small>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-primary" href="<?php echo site_url('master/' . $entity . '/create'); ?>">
      <i class="ri-add-line me-1"></i>Tambah
    </a>
    <?php if ($entity === 'product'): ?>
      <a class="btn btn-outline-info" href="<?php echo site_url('master/relation/product-extra-workspace'); ?>">Workspace Extra</a>
      <a class="btn btn-outline-info" href="<?php echo site_url('master/relation/product-recipe'); ?>">Halaman Resep Produk</a>
      <a class="btn btn-outline-info" href="<?php echo site_url('master/relation/product-extra'); ?>">Halaman Mapping Extra</a>
    <?php endif; ?>
    <?php if ($entity === 'component'): ?>
      <a class="btn btn-outline-info" href="<?php echo site_url('production/component-formulas'); ?>">Halaman Formula Component</a>
    <?php endif; ?>
    <?php if ($entity === 'product' || $entity === 'component'): ?>
      <a class="btn btn-outline-secondary" href="<?php echo site_url('master/variable-cost-default'); ?>">Pengaturan Variable Cost</a>
    <?php endif; ?>
    <?php if ($entity === 'extra-group'): ?>
      <a class="btn btn-outline-info" href="<?php echo site_url('master/relation/product-extra-workspace'); ?>">Workspace Extra</a>
      <a class="btn btn-outline-info" href="<?php echo site_url('master/relation/extra-item-group'); ?>">Hubungkan Extra ke Group</a>
      <a class="btn btn-outline-info" href="<?php echo site_url('master/relation/extra-group'); ?>">Checklist Produk per Group</a>
    <?php endif; ?>
    <?php if ($entity === 'extra'): ?>
      <a class="btn btn-outline-info" href="<?php echo site_url('master/relation/product-extra-workspace'); ?>">Workspace Extra</a>
      <a class="btn btn-outline-info" href="<?php echo site_url('master/relation/extra-item-group'); ?>">Hubungkan Extra ke Group</a>
    <?php endif; ?>
    <?php if ($entity === 'att-overtime-standard'): ?>
      <a class="btn btn-outline-secondary" href="<?php echo site_url('attendance/overtime-entries'); ?>">
        <i class="ri-arrow-left-line me-1"></i>Kembali ke Input Lembur
      </a>
    <?php endif; ?>
    <?php if ($entity === 'att-holiday'): ?>
      <form method="post" action="<?php echo site_url('master/att-holiday/generate-year'); ?>" class="d-flex align-items-center gap-2">
        <input type="hidden" name="master_mutation_csrf" value="<?php echo html_escape($masterMutationCsrfToken); ?>">
        <input
          type="number"
          name="year"
          class="form-control form-control-sm"
          min="2000"
          max="2100"
          value="<?php echo (int)date('Y'); ?>"
          style="width:96px;"
        >
        <button type="submit" class="btn btn-outline-primary btn-sm">
          <i class="ri-calendar-check-line me-1"></i>Generate 1 Tahun
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($entity === 'product' && !empty($can_export_product_sheet)): ?>
<div class="card mb-3" data-product-spreadsheet data-sync-url="<?php echo html_escape(site_url('master/product/spreadsheet/sync')); ?>" data-csrf="<?php echo html_escape($masterMutationCsrfToken); ?>">
  <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
    <div>
      <div class="fw-semibold"><i class="ri-file-excel-2-line me-1"></i>Data Produk untuk Spreadsheet</div>
      <small class="text-muted">Semua produk aktif/nonaktif, bukan hanya halaman ini. HPP mengikuti master; profit bukan laba bersih usaha.</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="<?php echo html_escape($product_spreadsheet_settings_url ?? site_url('master/product/spreadsheet/settings')); ?>">Pengaturan &amp; Panduan</a>
      <?php if (!empty($product_spreadsheet_url)): ?>
      <button type="button" class="btn btn-success btn-sm" data-product-sheet-sync>Perbarui Spreadsheet</button>
      <a class="btn btn-outline-success btn-sm" href="<?php echo html_escape($product_spreadsheet_url); ?>" target="_blank" rel="noopener noreferrer">Buka Spreadsheet</a>
      <?php else: ?>
      <small class="text-muted">Koneksi Google Sheets belum dikonfigurasi.</small>
      <?php endif; ?>
      <a class="btn btn-outline-secondary btn-sm" href="<?php echo site_url('master/product/spreadsheet/export'); ?>">Unduh Excel</a>
    </div>
  </div>
  <div class="px-3 pb-3 small" role="status" aria-live="polite" data-product-sheet-status>Tab data sistem akan diperbarui; kerjakan analisis manual di tab lain agar tidak tertimpa.</div>
</div>
<?php endif; ?>

<?php if ($isReorderableMaster): ?>
<div class="master-reorder-note">
  <strong>Urutan tampilan:</strong> field <strong>Urutan</strong> di database dipakai sebagai urutan tampilan di POS dan halaman produk terkait.
  <?php if ($canDragReorder): ?>
    Drag & drop baris pada halaman ini untuk menyimpan urutan baru.
  <?php else: ?>
    Drag & drop aktif jika semua data sedang tampil dalam satu halaman tanpa filter pencarian.
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($has_active_flag)): ?>
<div class="card mb-3">
  <div class="card-body py-2">
    <div class="btn-group" role="group" aria-label="Status filter">
      <?php
        $statusTabs = [
          'active' => 'Aktif',
          'inactive' => 'Nonaktif',
          'all' => 'Semua',
        ];
      ?>
      <?php foreach ($statusTabs as $statusKey => $statusLabel): ?>
        <?php $statusQuery = http_build_query(array_merge($queryBase, ['status' => $statusKey, 'page' => 1])); ?>
        <a
          href="<?php echo $baseUrl . '?' . $statusQuery; ?>"
          class="btn btn-sm <?php echo $status === $statusKey ? 'btn-primary' : 'btn-outline-secondary'; ?>"
          data-master-ajax="1"
        >
          <?php echo html_escape($statusLabel); ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card mb-3 master-card">
  <div class="card-body py-3 master-filter">
    <form id="filterForm" method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-4 mb-2">
        <label class="form-label mb-1">Pencarian</label>
        <input type="text" name="q" class="form-control" placeholder="Cari data..." value="<?php echo html_escape($q); ?>">
      </div>
      <?php foreach ($listFilterDefinitions as $filterDefinition): ?>
        <?php
          $filterName = trim((string)($filterDefinition['name'] ?? ''));
          if ($filterName === '') {
              continue;
          }
          $filterOptions = (array)($listFilterOptions[$filterName] ?? []);
          $filterValue = $listFilterValues[$filterName] ?? null;
        ?>
        <div class="col-md-2 mb-2">
          <label class="form-label mb-1"><?php echo html_escape((string)($filterDefinition['label'] ?? ucfirst(str_replace('_', ' ', $filterName)))); ?></label>
          <select class="form-select" name="<?php echo html_escape($filterName); ?>">
            <option value="">Semua</option>
            <?php foreach ($filterOptions as $option): ?>
              <option value="<?php echo (int)($option['value'] ?? 0); ?>" <?php echo (int)$filterValue === (int)($option['value'] ?? 0) ? 'selected' : ''; ?>>
                <?php echo html_escape((string)($option['label'] ?? '')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endforeach; ?>
      <div class="col-md-2 mb-2">
        <label class="form-label mb-1">Per Halaman</label>
        <select class="form-select" name="per_page" id="perPageSelect">
          <?php foreach ([10,25,50,100,500] as $pp): ?>
            <option value="<?php echo $pp; ?>" <?php echo $pp == $per_page ? 'selected' : ''; ?>><?php echo $pp; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 mb-2 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary mr-2">Filter</button>
        <a href="<?php echo $baseUrl . '?status=' . rawurlencode($resetStatus); ?>" class="btn btn-outline-secondary" data-master-ajax="1">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card master-card" id="masterListCard">
  <div class="table-responsive <?php echo $entity === 'product' ? 'master-table-wrap' : ''; ?>">
    <table class="table table-striped table-hover mb-0 master-table">
      <thead>
        <tr>
          <?php if ($isReorderableMaster): ?><th width="48"></th><?php endif; ?>
          <th width="60">No</th>
          <?php foreach ($cfg['columns'] as $col): ?>
            <th><?php echo html_escape($col['label']); ?></th>
          <?php endforeach; ?>
          <th width="190">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="<?php echo count($cfg['columns']) + 2 + ($isReorderableMaster ? 1 : 0); ?>" class="text-center text-muted py-4">Belum ada data.</td>
          </tr>
        <?php else: ?>
          <?php $no = ($page - 1) * $per_page + 1; ?>
          <?php foreach ($rows as $r): ?>
            <tr class="<?php echo $canDragReorder ? 'master-sort-row' : ''; ?>" <?php echo $canDragReorder ? 'data-master-sort-row="1" draggable="true" data-row-id="' . (int)$r['id'] . '"' : ''; ?>>
              <?php if ($isReorderableMaster): ?>
                <td class="master-reorder-handle" <?php echo $canDragReorder ? 'data-master-sort-handle="1"' : ''; ?>><?php if ($canDragReorder): ?><i class="ri ri-drag-move-2-line"></i><?php endif; ?></td>
              <?php endif; ?>
              <td class="number-cell"><?php echo $no++; ?></td>
              <?php foreach ($cfg['columns'] as $col): ?>
                <?php
                  $val = $r[$col['key']] ?? '';
                  $type = $col['type'] ?? 'text';
                  $key = strtolower((string)($col['key'] ?? ''));
                  $isNumeric = is_numeric($val);
                  $isDecimal = $isNumeric && (strpos((string)$val, '.') !== false || strpos((string)$val, ',') !== false);
                  $isTextLeft = (strpos($key, 'code') !== false) || (strpos($key, 'name') !== false);
                  $cellClasses = [];
                  if ($isNumeric) {
                    $cellClasses[] = 'number-cell';
                  }
                  if ($isTextLeft) {
                    $cellClasses[] = 'text-cell';
                  }
                ?>
                <td class="<?php echo html_escape(implode(' ', $cellClasses)); ?>">
                  <?php if ($entity === 'product' && $type === 'product_taxonomy'): ?>
                    <div class="d-flex flex-column gap-0" style="line-height:1.3;">
                      <span class="fw-semibold"><?php echo html_escape((string)($r['product_division_id_label'] ?? '-')); ?></span>
                      <small class="text-muted"><?php echo html_escape((string)($r['classification_id_label'] ?? '-')); ?> &middot; <?php echo html_escape((string)($r['product_category_id_label'] ?? '-')); ?></small>
                    </div>
                  <?php elseif ($entity === 'product' && $type === 'product_hpp_profit'): ?>
                    <?php
                      $hppPct  = (float)($r['hpp_live_percent_total'] ?? 0);
                      $profit  = (float)($r['estimated_profit'] ?? 0);
                      $profitClass = $profit >= 0 ? 'text-success' : 'text-danger';
                    ?>
                    <div class="d-flex flex-column gap-0" style="line-height:1.3;">
                      <span class="fw-semibold"><?php echo number_format($hppPct, 2, ',', '.'); ?>%</span>
                      <small class="<?php echo $profitClass; ?>">Rp <?php echo number_format($profit, 0, ',', '.'); ?></small>
                    </div>
                  <?php elseif ($entity === 'product' && $type === 'product_stock_status'): ?>
                    <div class="d-flex flex-column gap-1" style="line-height:1.3;">
                      <button
                        type="button"
                        class="btn btn-xs master-inline-pill <?php echo html_escape((string)($r['stock_mode_button_class'] ?? 'btn-outline-primary')); ?>"
                        style="font-size:0.72rem;padding:1px 7px;"
                        data-master-stock-mode-url="<?php echo site_url('master/' . $entity . '/stock-mode/' . (int)$r['id']); ?>"
                        title="Klik untuk ubah mode stok"
                      ><?php echo html_escape((string)($r['stock_mode_label'] ?? (string)($r['stock_mode'] ?? '-'))); ?></button>
                      <button
                        type="button"
                        class="btn btn-xs master-inline-pill <?php echo (int)($r['is_active'] ?? 0) === 1 ? 'btn-success' : 'btn-outline-secondary'; ?>"
                        style="font-size:0.72rem;padding:1px 7px;"
                        data-master-status-toggle-url="<?php echo site_url('master/' . $entity . '/toggle/' . (int)$r['id']); ?>"
                        title="Klik untuk ubah status"
                      ><?php echo (int)($r['is_active'] ?? 0) === 1 ? 'Aktif' : 'Nonaktif'; ?></button>
                    </div>
                  <?php elseif ($entity === 'product' && $key === 'stock_mode'): ?>
                    <button
                      type="button"
                      class="btn btn-sm master-inline-pill <?php echo html_escape((string)($r['stock_mode_button_class'] ?? 'btn-outline-primary')); ?>"
                      data-master-stock-mode-url="<?php echo site_url('master/' . $entity . '/stock-mode/' . (int)$r['id']); ?>"
                      title="Klik untuk ubah mode stok"
                    >
                      <?php echo html_escape((string)($r['stock_mode_label'] ?? (string)$val)); ?>
                    </button>
                  <?php elseif ($type === 'status'): ?>
                    <?php if ($entity === 'product'): ?>
                      <button
                        type="button"
                        class="btn btn-sm master-inline-pill <?php echo (int)$val === 1 ? 'btn-success' : 'btn-outline-secondary'; ?>"
                        data-master-status-toggle-url="<?php echo site_url('master/' . $entity . '/toggle/' . (int)$r['id']); ?>"
                        title="Klik untuk ubah status"
                      >
                        <?php echo (int)$val === 1 ? 'Aktif' : 'Nonaktif'; ?>
                      </button>
                    <?php elseif ((int)$val === 1): ?>
                      <span class="badge badge-success">Aktif</span>
                    <?php else: ?>
                      <span class="badge badge-secondary">Nonaktif</span>
                    <?php endif; ?>
                  <?php elseif ($type === 'count'): ?>
                    <span class="master-count-badge"><?php echo number_format((int)$val, 0, ',', '.'); ?></span>
                  <?php elseif ($type === 'bool'): ?>
                    <?php echo (int)$val === 1 ? 'Ya' : 'Tidak'; ?>
                  <?php elseif ($type === 'money'): ?>
                    <?php echo 'Rp ' . number_format((float)$val, 2, ',', '.'); ?>
                  <?php elseif ($type === 'percent'): ?>
                    <?php echo number_format((float)$val, 2, ',', '.') . '%'; ?>
                  <?php elseif ($type === 'image'): ?>
                    <?php
                      $imgPath = trim((string)$val);
                      $imgSrc = '';
                      if ($imgPath !== '') {
                        if (preg_match('#^https?://#i', $imgPath)) {
                          $imgSrc = $imgPath;
                        } else {
                          $imgSrc = base_url(ltrim($imgPath, '/'));
                        }
                      }
                    ?>
                    <?php if ($imgSrc !== ''): ?>
                      <img src="<?php echo html_escape($imgSrc); ?>" alt="Foto" style="width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <?php if ($isProductHierarchyMaster && ($col['key'] ?? '') === 'name'): ?>
                      <span class="master-name-cell"><?php echo html_escape((string)$val); ?></span>
                    <?php elseif ($isProductHierarchyMaster && ($col['key'] ?? '') === 'pos_scope'): ?>
                      <span class="master-scope-badge"><?php echo html_escape((string)$val); ?></span>
                    <?php elseif ($isDecimal): ?>
                      <span data-decimal="1" data-value="<?php echo html_escape((string)$val); ?>"><?php echo html_escape((string)$val); ?></span>
                    <?php else: ?>
                      <?php echo html_escape((string)$val); ?>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
              <td class="action-cell" <?php echo $entity === 'product' ? 'style="white-space:nowrap;width:120px;"' : ''; ?>>
                <?php if ($entity === 'product'): ?>
                  <div class="d-flex flex-column gap-1">
                    <div class="d-flex gap-1">
                      <a class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Detail" aria-label="Detail" href="<?php echo site_url('master/product/detail/' . (int)$r['id']); ?>"><i class="ri ri-eye-line"></i></a>
                      <a class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Edit" aria-label="Edit" href="<?php echo site_url('master/product/edit/' . (int)$r['id']); ?>"><i class="ri ri-edit-line"></i></a>
                      <a class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Recipe" aria-label="Recipe" href="<?php echo site_url('master/relation/product-recipe/' . (int)$r['id']); ?>"><i class="ri ri-file-list-3-line"></i></a>
                    </div>
                    <div class="d-flex gap-1">
                      <a class="btn btn-sm btn-outline-success action-icon-btn" data-bs-toggle="tooltip" title="COGS Stok" aria-label="COGS Stok" href="<?php echo site_url('master/product/hpp-stock/' . (int)$r['id']); ?>"><i class="ri ri-bar-chart-box-line"></i></a>
                      <a class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Extra" aria-label="Extra" href="<?php echo site_url('master/relation/product-extra/' . (int)$r['id']); ?>"><i class="ri ri-links-line"></i></a>
                    </div>
                  </div>
                <?php else: ?>
                <div class="<?php echo $isPayrollMaster ? 'payroll-actions' : 'master-action-group'; ?>">
                  <?php if (in_array($entity, ['org-employee'], true)): ?>
                    <a class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Detail" aria-label="Detail" href="<?php echo site_url('master/' . $entity . '/detail/' . (int)$r['id']); ?>"><i class="ri ri-eye-line"></i></a>
                  <?php endif; ?>
                  <a class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Edit" aria-label="Edit" href="<?php echo site_url('master/' . $entity . '/edit/' . (int)$r['id']); ?>"><i class="ri ri-edit-line"></i></a>
                  <?php if (false): // product handled above
                  endif; ?>
                  <?php if ($entity === 'component'): ?>
                    <a class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Formula" aria-label="Formula" href="<?php echo site_url('production/component-formulas/detail/' . (int)$r['id']); ?>"><i class="ri ri-function-line"></i></a>
                  <?php endif; ?>
                  <?php if ($entity === 'extra-group'): ?>
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-info action-icon-btn js-extra-group-modal-trigger"
                      data-bs-toggle="modal"
                      data-bs-target="#extraGroupRelationModal"
                      title="Hubungkan Extra"
                      aria-label="Hubungkan Extra"
                      data-modal-kind="extra"
                      data-group-id="<?php echo (int)$r['id']; ?>"
                      data-group-name="<?php echo html_escape((string)($r['group_name'] ?? '')); ?>"
                    ><i class="ri ri-links-line"></i></button>
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-info action-icon-btn js-extra-group-modal-trigger"
                      data-bs-toggle="modal"
                      data-bs-target="#extraGroupRelationModal"
                      title="Hubungkan Produk"
                      aria-label="Hubungkan Produk"
                      data-modal-kind="product"
                      data-group-id="<?php echo (int)$r['id']; ?>"
                      data-group-name="<?php echo html_escape((string)($r['group_name'] ?? '')); ?>"
                    ><i class="ri ri-checkbox-multiple-line"></i></button>
                  <?php endif; ?>
                  <?php if ($entity === 'extra'): ?>
                    <a class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Hubungkan ke Group" aria-label="Hubungkan ke Group" href="<?php echo site_url('master/relation/extra-item-group/' . (int)$r['id']); ?>"><i class="ri ri-links-line"></i></a>
                  <?php endif; ?>
                  <?php if (!empty($cfg['toggle']) && $entity !== 'product'): ?>
                    <form method="post" action="<?php echo site_url('master/' . $entity . '/toggle/' . (int)$r['id']); ?>" class="d-inline" onsubmit="return confirm('Ubah status data ini?')">
                      <input type="hidden" name="master_mutation_csrf" value="<?php echo html_escape($masterMutationCsrfToken); ?>">
                      <button type="submit" class="btn btn-sm btn-outline-warning action-icon-btn" data-bs-toggle="tooltip" title="Toggle Status" aria-label="Toggle Status"><i class="ri ri-refresh-line"></i></button>
                    </form>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($total_pages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2 master-footer">
      <small>Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?></small>
      <div class="btn-group">
        <?php
          $prev = max(1, $page - 1);
          $next = min($total_pages, $page + 1);
          $prevQuery = http_build_query(array_merge($queryBase, ['page' => $prev]));
          $nextQuery = http_build_query(array_merge($queryBase, ['page' => $next]));
          $items = $buildPageItems((int)$page, (int)$total_pages);
        ?>
        <a class="btn btn-sm btn-outline-secondary <?php echo $page <= 1 ? 'disabled' : ''; ?>" data-master-ajax="1" href="<?php echo $page <= 1 ? '#' : ($baseUrl . '?' . $prevQuery); ?>">&lt;</a>
        <?php foreach ($items as $item): ?>
          <?php if ($item === '...'): ?>
            <span class="btn btn-sm btn-outline-secondary disabled">...</span>
          <?php else: ?>
            <?php $itemQuery = http_build_query(array_merge($queryBase, ['page' => (int)$item])); ?>
            <a class="btn btn-sm <?php echo (int)$item === (int)$page ? 'btn-primary' : 'btn-outline-secondary'; ?>" data-master-ajax="1" href="<?php echo $baseUrl . '?' . $itemQuery; ?>"><?php echo (int)$item; ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
        <a class="btn btn-sm btn-outline-secondary <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" data-master-ajax="1" href="<?php echo $page >= $total_pages ? '#' : ($baseUrl . '?' . $nextQuery); ?>">&gt;</a>
      </div>
    </div>
  <?php endif; ?>
</div>
</div>

<?php if ($entity === 'extra-group'): ?>
<button
  type="button"
  id="extraGroupRelationModalOpener"
  class="d-none"
  data-bs-toggle="modal"
  data-bs-target="#extraGroupRelationModal"
  data-modal-kind=""
  data-group-id="0"
  data-group-name=""
></button>
<div class="modal fade" id="extraGroupRelationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1" id="extraGroupRelationModalTitle">Relasi Group Extra</h5>
          <div class="small text-muted" id="extraGroupRelationModalSubtitle"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 align-items-end">
          <div class="col-md-9">
            <label class="form-label mb-1" id="extraGroupRelationSearchLabel">Pencarian</label>
            <input type="text" class="form-control" id="extraGroupRelationSearch" placeholder="">
          </div>
          <div class="col-md-3 text-md-end">
            <div class="small text-muted mb-1">Terpilih</div>
            <div class="fw-semibold" id="extraGroupRelationSelectedCount">0 item</div>
          </div>
        </div>
        <div class="border rounded-3 overflow-hidden">
          <div class="table-responsive" id="extraGroupRelationTableWrap">
            <div class="p-4 text-center text-muted">Memuat data...</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="extraGroupRelationSaveBtn">Simpan Checklist</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
(function () {
  var rootSelector = '[data-master-root]';
  var modalBootTimer = null;

  function applyUiEnhancements(scope) {
    var host = scope || document;
    host.querySelectorAll('[data-decimal="1"]').forEach(function (el) {
      var raw = (el.getAttribute('data-value') || el.textContent || '').trim();
      if (!raw) return;
      var num = Number(raw.replace(/,/g, ''));
      if (!Number.isFinite(num)) return;
      el.textContent = num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      var td = el.closest('td');
      if (td) td.classList.add('number-cell');
    });

    if (window.bootstrap && window.bootstrap.Tooltip) {
      host.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        window.bootstrap.Tooltip.getOrCreateInstance(el);
      });
    }
  }

  function initExtraGroupRelationModal(root) {
    var modalEl = document.getElementById('extraGroupRelationModal');
    if (!modalEl || !window.bootstrap || !window.bootstrap.Modal) {
      return;
    }

    if (modalEl.getAttribute('data-init-ready') === '1') {
      window.openExtraGroupRelationModal = function (kind, groupId, groupName) {
        var opener = document.getElementById('extraGroupRelationModalOpener');
        if (opener) {
          opener.setAttribute('data-modal-kind', kind || '');
          opener.setAttribute('data-group-id', String(groupId || 0));
          opener.setAttribute('data-group-name', groupName || '');
          opener.click();
          return;
        }
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        return;
      };
      return;
    }

    modalEl.setAttribute('data-init-ready', '1');

    var modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
    var titleEl = document.getElementById('extraGroupRelationModalTitle');
    var subtitleEl = document.getElementById('extraGroupRelationModalSubtitle');
    var searchLabelEl = document.getElementById('extraGroupRelationSearchLabel');
    var searchInput = document.getElementById('extraGroupRelationSearch');
    var selectedCountEl = document.getElementById('extraGroupRelationSelectedCount');
    var tableWrap = document.getElementById('extraGroupRelationTableWrap');
    var saveBtn = document.getElementById('extraGroupRelationSaveBtn');
    var state = {
      kind: '',
      groupId: 0,
      groupName: '',
      q: '',
      selectedIds: new Set(),
      mutationCsrf: '',
      mappingRevision: '',
      seedLoaded: false,
      modalEpoch: 0,
      requestSequence: 0,
      latestRequestSequence: 0,
      querySequence: 0,
      pendingRows: null,
      timer: null
    };

    function escapeHtml(value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function requestJson(url, options) {
      return fetch(url, options || {})
        .then(function (response) {
          return response.text().then(function (text) {
            var payload = null;
            try {
              payload = text ? JSON.parse(text) : null;
            } catch (error) {
              var plain = String(text || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
              throw new Error(plain || 'Respons backend bukan JSON yang valid.');
            }
            if (!response.ok) {
              var requestError = new Error((payload && payload.message) || ('HTTP ' + response.status));
              requestError.status = response.status;
              throw requestError;
            }
            return payload;
          });
        });
    }

    function endpointBase(kind, groupId) {
      return kind === 'extra'
        ? '<?php echo site_url('master/relation/extra-group'); ?>/' + groupId + '/extras'
        : '<?php echo site_url('master/relation/extra-group'); ?>/' + groupId + '/products';
    }

    function saveEndpoint() {
      return state.kind === 'extra'
        ? '<?php echo site_url('master/relation/extra-group'); ?>/' + state.groupId + '/extras/save'
        : '<?php echo site_url('master/relation/extra-group'); ?>/' + state.groupId + '/products/save';
    }

    function selectedArray() {
      return Array.from(state.selectedIds).map(function (value) { return Number(value); }).filter(function (value) { return value > 0; });
    }

    function updateSelectedCounter() {
      selectedCountEl.textContent = state.selectedIds.size + ' item';
    }

    function isValidRevision(value) {
      return typeof value === 'string' && /^[0-9a-f]{64}$/.test(value);
    }

    function updateSaveAvailability() {
      var hasMutationCsrf = isValidRevision(state.mutationCsrf);
      var hasMappingRevision = state.kind !== 'product' || isValidRevision(state.mappingRevision);
      saveBtn.disabled = !hasMutationCsrf || !hasMappingRevision;
    }

    function attachRowHandlers() {
      tableWrap.querySelectorAll('.js-extra-group-check').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
          var id = Number(checkbox.getAttribute('data-id') || '0');
          if (id <= 0) {
            return;
          }
          if (checkbox.checked) {
            state.selectedIds.add(id);
          } else {
            state.selectedIds.delete(id);
          }
          updateSelectedCounter();
        });
      });
    }

    function renderRows(rows) {
      if (!Array.isArray(rows) || !rows.length) {
        tableWrap.innerHTML = '<div class="p-4 text-center text-muted">Tidak ada data sesuai filter.</div>';
        updateSelectedCounter();
        return;
      }

      var head = state.kind === 'extra'
        ? '<tr><th width="70">Pilih</th><th>Kode</th><th>Nama Extra</th><th>Tipe</th><th>Sumber</th></tr>'
        : '<tr><th width="70">Pilih</th><th>Divisi</th><th>Klasifikasi</th><th>Kategori</th><th>Kode</th><th>Nama Produk</th></tr>';

      var body = rows.map(function (row) {
        var id = Number(row.id || 0);
        var checked = state.selectedIds.has(id) ? ' checked' : '';
        if (state.kind === 'extra') {
          return '<tr>'
            + '<td><input type="checkbox" class="form-check-input js-extra-group-check" data-id="' + id + '"' + checked + '></td>'
            + '<td class="text-cell">' + escapeHtml(row.extra_code || '') + '</td>'
            + '<td class="text-cell">' + escapeHtml(row.extra_name || '') + '</td>'
            + '<td>' + escapeHtml(row.extra_type || '-') + '</td>'
            + '<td>' + escapeHtml(row.source_kind || '-') + '</td>'
            + '</tr>';
        }
        return '<tr>'
          + '<td><input type="checkbox" class="form-check-input js-extra-group-check" data-id="' + id + '"' + checked + '></td>'
          + '<td>' + escapeHtml(row.product_division_name || '-') + '</td>'
          + '<td>' + escapeHtml(row.classification_name || '-') + '</td>'
          + '<td>' + escapeHtml(row.product_category_name || '-') + '</td>'
          + '<td class="text-cell">' + escapeHtml(row.product_code || '') + '</td>'
          + '<td class="text-cell">' + escapeHtml(row.product_name || '') + '</td>'
          + '</tr>';
      }).join('');

      tableWrap.innerHTML = '<table class="table table-striped table-hover mb-0"><thead>' + head + '</thead><tbody>' + body + '</tbody></table>';
      updateSelectedCounter();
      attachRowHandlers();
    }

    function isCurrentModalContext(context) {
      return context.epoch === state.modalEpoch
        && context.kind === state.kind
        && context.groupId === state.groupId;
    }

    function isLatestQueryResponse(context, requestSequence) {
      return isCurrentModalContext(context)
        && requestSequence === state.latestRequestSequence
        && context.querySequence === state.querySequence;
    }

    function renderPendingRows() {
      var pending = state.pendingRows;
      if (!pending || !isLatestQueryResponse(pending.context, pending.requestSequence)) {
        return false;
      }
      state.pendingRows = null;
      renderRows(pending.rows);
      return true;
    }

    function fetchRows(initialFetch) {
      if (state.groupId <= 0 || !state.kind) {
        return;
      }
      var requestContext = {
        epoch: state.modalEpoch,
        kind: state.kind,
        groupId: state.groupId,
        querySequence: state.querySequence
      };
      var requestSequence = ++state.requestSequence;
      var requestQuery = initialFetch ? '' : state.q;
      state.latestRequestSequence = requestSequence;
      tableWrap.innerHTML = '<div class="p-4 text-center text-muted">Memuat data...</div>';
      requestJson(endpointBase(requestContext.kind, requestContext.groupId) + '?q=' + encodeURIComponent(requestQuery || ''), {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(function (payload) {
        if (!isCurrentModalContext(requestContext)) {
          return;
        }
        if (!payload || payload.ok !== true) {
          throw new Error((payload && payload.message) || 'Gagal memuat data relasi.');
        }
        if (initialFetch && !state.seedLoaded) {
          state.selectedIds = new Set((payload.selected_ids || []).map(function (value) { return Number(value); }).filter(function (value) { return value > 0; }));
          state.mappingRevision = requestContext.kind === 'product' && isValidRevision(payload.mapping_revision)
            ? payload.mapping_revision
            : '';
          state.mutationCsrf = typeof payload.mutation_csrf === 'string' ? payload.mutation_csrf : '';
          state.seedLoaded = true;
          updateSelectedCounter();
          updateSaveAvailability();
          if (renderPendingRows()) {
            return;
          }
        }
        if (!isLatestQueryResponse(requestContext, requestSequence)) {
          return;
        }
        if (!state.seedLoaded) {
          state.pendingRows = {
            context: requestContext,
            requestSequence: requestSequence,
            rows: payload.rows || []
          };
          return;
        }
        state.pendingRows = null;
        renderRows(payload.rows || []);
      })
      .catch(function (error) {
        if (!isLatestQueryResponse(requestContext, requestSequence)) {
          return;
        }
        state.pendingRows = null;
        tableWrap.innerHTML = '<div class="p-4 text-center text-danger">' + escapeHtml(error && error.message ? error.message : 'Gagal memuat data.') + '</div>';
      });
    }

    function openModal(kind, groupId, groupName) {
      if (state.timer) {
        clearTimeout(state.timer);
        state.timer = null;
      }
      state.modalEpoch += 1;
      state.querySequence += 1;
      state.kind = kind;
      state.groupId = Number(groupId || 0);
      state.groupName = groupName || '';
      state.q = '';
      state.selectedIds = new Set();
      state.mutationCsrf = '';
      state.mappingRevision = '';
      state.seedLoaded = false;
      state.pendingRows = null;
      saveBtn.disabled = true;
      searchInput.value = '';
      titleEl.textContent = kind === 'extra' ? 'Hubungkan Master Extra ke Group' : 'Hubungkan Produk ke Group';
      subtitleEl.textContent = 'Group: ' + state.groupName;
      searchLabelEl.textContent = kind === 'extra' ? 'Cari Master Extra' : 'Cari Produk';
      searchInput.placeholder = kind === 'extra'
        ? 'Cari kode / nama master extra...'
        : 'Cari kode / nama produk...';
      updateSelectedCounter();
      fetchRows(true);
    }

    window.openExtraGroupRelationModal = function (kind, groupId, groupName) {
      var opener = document.getElementById('extraGroupRelationModalOpener');
      if (opener) {
        opener.setAttribute('data-modal-kind', kind || '');
        opener.setAttribute('data-group-id', String(groupId || 0));
        opener.setAttribute('data-group-name', groupName || '');
        opener.click();
        return;
      }
      openModal(kind, groupId, groupName);
      modal.show();
    };

    modalEl.addEventListener('show.bs.modal', function (event) {
      var trigger = event.relatedTarget || document.getElementById('extraGroupRelationModalOpener');
      if (!trigger) {
        return;
      }
      openModal(
        trigger.getAttribute('data-modal-kind') || '',
        trigger.getAttribute('data-group-id') || '0',
        trigger.getAttribute('data-group-name') || ''
      );
    });

    modalEl.addEventListener('shown.bs.modal', function () {
      window.setTimeout(function () {
        if (searchInput) {
          searchInput.focus();
          searchInput.select();
        }
      }, 60);
    });

    searchInput.addEventListener('input', function () {
      state.q = searchInput.value || '';
      state.querySequence += 1;
      if (state.timer) {
        clearTimeout(state.timer);
      }
      state.timer = setTimeout(function () { fetchRows(false); }, 250);
    });

    saveBtn.addEventListener('click', function () {
      if (!isValidRevision(state.mutationCsrf)) {
        window.alert('Anda tidak memiliki izin untuk menyimpan relasi group extra.');
        return;
      }
      if (state.kind === 'product' && !isValidRevision(state.mappingRevision)) {
        window.alert('Snapshot mapping produk tidak tersedia. Muat ulang halaman sebelum menyimpan.');
        return;
      }
      var fieldName = state.kind === 'extra' ? 'extra_ids[]' : 'product_ids[]';
      var formData = new FormData();
      selectedArray().forEach(function (id) {
        formData.append(fieldName, String(id));
      });
      if (state.kind === 'product') {
        formData.append('mapping_revision', state.mappingRevision);
      }
      saveBtn.disabled = true;
      requestJson(saveEndpoint(), {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-Master-Extra-Group-Csrf': state.mutationCsrf
        },
        body: formData
      })
      .then(function (payload) {
        if (!payload || payload.ok !== true) {
          throw new Error((payload && payload.message) || 'Gagal menyimpan relasi.');
        }
        modal.hide();
        window.alert(payload.message || 'Relasi berhasil disimpan.');
        window.location.reload();
      })
      .catch(function (error) {
        if (error && error.status === 409) {
          state.mappingRevision = '';
          window.alert('Mapping produk telah berubah. Muat ulang halaman sebelum menyimpan kembali.');
          return;
        }
        window.alert(error && error.message ? error.message : 'Gagal menyimpan relasi.');
      })
      .finally(function () {
        updateSaveAvailability();
      });
    });
  }

  function initMasterAjax() {
    var root = document.querySelector(rootSelector);
    if (!root) return;
    var masterMutationCsrf = root.getAttribute('data-master-mutation-csrf') || '';
    applyUiEnhancements(root);
    initExtraGroupRelationModal(root);

    var form = document.getElementById('filterForm');
    var input = form ? form.querySelector('input[name="q"]') : null;
    var perPage = document.getElementById('perPageSelect');

    function fetchAndSwap(url) {
      if (!url || url === '#') return;
      fetch(url, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(function (res) { return res.text(); })
      .then(function (html) {
        var parser = new DOMParser();
        var doc = parser.parseFromString(html, 'text/html');
        var nextRoot = doc.querySelector(rootSelector);
        if (!nextRoot) return;
        root.replaceWith(nextRoot);
        history.replaceState({}, '', url);
        initMasterAjax();
        applyUiEnhancements(nextRoot);
      });
    }

    function postRowAction(url, button) {
      if (!url || !button) return;
      var wasDisabled = !!button.disabled;
      button.disabled = true;
      fetch(url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-Master-Mutation-CSRF': masterMutationCsrf
        }
      })
      .then(function (res) { return res.json(); })
      .then(function (payload) {
        if (!payload || payload.ok !== true) {
          throw new Error((payload && payload.message) || 'Gagal memperbarui data.');
        }
        fetchAndSwap(window.location.href);
      })
      .catch(function (err) {
        button.disabled = wasDisabled;
        window.alert(err && err.message ? err.message : 'Gagal memperbarui data.');
      });
    }

    function initMasterReorder() {
      var reorderUrl = root.getAttribute('data-master-reorder-url') || '';
      var rows = Array.prototype.slice.call(root.querySelectorAll('[data-master-sort-row]'));
      if (!reorderUrl || rows.length <= 1) {
        return;
      }

      var tbody = root.querySelector('.master-table tbody');
      if (!tbody) {
        return;
      }

      var draggedRow = null;
      var startOrder = [];
      var saving = false;
      var dropSaved = false;

      function currentOrder() {
        return Array.prototype.slice.call(tbody.querySelectorAll('[data-master-sort-row]')).map(function (row) {
          return row.getAttribute('data-row-id');
        }).filter(Boolean);
      }

      function clearDragMarkers() {
        tbody.querySelectorAll('.drag-over-top, .drag-over-bottom').forEach(function (row) {
          row.classList.remove('drag-over-top', 'drag-over-bottom');
        });
      }

      function saveOrder(ids) {
        if (saving) {
          return;
        }
        saving = true;
        fetch(reorderUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-Master-Mutation-CSRF': masterMutationCsrf
          },
          body: JSON.stringify({ ids: ids })
        })
        .then(function (res) { return res.json(); })
        .then(function (payload) {
          if (!payload || payload.ok !== true) {
            throw new Error((payload && payload.message) || 'Gagal menyimpan urutan.');
          }
          fetchAndSwap(window.location.href);
        })
        .catch(function (err) {
          window.alert(err && err.message ? err.message : 'Gagal menyimpan urutan.');
          fetchAndSwap(window.location.href);
        })
        .finally(function () {
          saving = false;
        });
      }

      function finalizeReorder() {
        var newOrder = currentOrder();
        if (newOrder.join(',') !== startOrder.join(',')) {
          saveOrder(newOrder);
        }
      }

      rows.forEach(function (row) {
        var handle = row.querySelector('[data-master-sort-handle]');
        if (handle) {
          handle.setAttribute('draggable', 'true');
          handle.addEventListener('dragstart', function (e) {
            if (saving) {
              e.preventDefault();
              return;
            }
            dropSaved = false;
            draggedRow = row;
            startOrder = currentOrder();
            row.classList.add('is-dragging');
            if (e.dataTransfer) {
              e.dataTransfer.effectAllowed = 'move';
              e.dataTransfer.setData('text/plain', row.getAttribute('data-row-id') || '');
            }
          });
        }

        row.addEventListener('dragstart', function (e) {
          if (saving) {
            e.preventDefault();
            return;
          }
          dropSaved = false;
          draggedRow = row;
          startOrder = currentOrder();
          row.classList.add('is-dragging');
          if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', row.getAttribute('data-row-id') || '');
          }
        });

        row.addEventListener('dragover', function (e) {
          if (!draggedRow || draggedRow === row || saving) {
            return;
          }
          e.preventDefault();
          clearDragMarkers();
          var rect = row.getBoundingClientRect();
          var insertAfter = (e.clientY - rect.top) > (rect.height / 2);
          row.classList.add(insertAfter ? 'drag-over-bottom' : 'drag-over-top');
        });

        row.addEventListener('dragleave', function () {
          row.classList.remove('drag-over-top', 'drag-over-bottom');
        });

        row.addEventListener('drop', function (e) {
          if (!draggedRow || draggedRow === row || saving) {
            return;
          }
          e.preventDefault();
          var rect = row.getBoundingClientRect();
          var insertAfter = (e.clientY - rect.top) > (rect.height / 2);
          if (insertAfter) {
            tbody.insertBefore(draggedRow, row.nextSibling);
          } else {
            tbody.insertBefore(draggedRow, row);
          }
          clearDragMarkers();
          dropSaved = true;
          finalizeReorder();
        });

        row.addEventListener('dragend', function () {
          row.classList.remove('is-dragging');
          clearDragMarkers();
          if (!dropSaved) {
            finalizeReorder();
          }
          draggedRow = null;
          dropSaved = false;
        });
      });
    }

    initMasterReorder();

    root.querySelectorAll('[data-master-status-toggle-url]').forEach(function (button) {
      button.addEventListener('click', function () {
        postRowAction(button.getAttribute('data-master-status-toggle-url') || '', button);
      });
    });

    root.querySelectorAll('[data-master-stock-mode-url]').forEach(function (button) {
      button.addEventListener('click', function () {
        postRowAction(button.getAttribute('data-master-stock-mode-url') || '', button);
      });
    });

    root.querySelectorAll('a[data-master-ajax="1"]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        if (a.classList.contains('disabled')) {
          e.preventDefault();
          return;
        }
        e.preventDefault();
        fetchAndSwap(a.href);
      });
    });

    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(form);
        fd.set('page', '1');
        var qs = new URLSearchParams(fd).toString();
        fetchAndSwap(form.getAttribute('action') + '?' + qs);
      });
    }

    if (perPage && form) {
      perPage.addEventListener('change', function () {
        var fd = new FormData(form);
        fd.set('page', '1');
        var qs = new URLSearchParams(fd).toString();
        fetchAndSwap(form.getAttribute('action') + '?' + qs);
      });
    }

    if (input && form) {
      var t;
      input.addEventListener('input', function () {
        clearTimeout(t);
        t = setTimeout(function () {
          var fd = new FormData(form);
          fd.set('page', '1');
          var qs = new URLSearchParams(fd).toString();
          fetchAndSwap(form.getAttribute('action') + '?' + qs);
        }, 450);
      });
    }
  }

  function bootMasterPage() {
    if (!window.bootstrap || !window.bootstrap.Modal) {
      if (modalBootTimer) {
        window.clearTimeout(modalBootTimer);
      }
      modalBootTimer = window.setTimeout(bootMasterPage, 120);
      return;
    }
    initMasterAjax();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootMasterPage);
  } else {
    bootMasterPage();
  }
})();
</script>
