<?php
$baseUrl = site_url((string)($base_url_opening ?? 'inventory/stock/opening'));
$storeUrl = site_url('inventory/stock/opening/store');
$stepUpUrl = site_url('inventory/stock/opening/step-up/verify');
$voidUrlBase = site_url('inventory/stock/opening/void');
$itemSearchUrl = site_url('inventory/stock/opening/item-search');
$stockOpeningExportUrl = (string)($stock_opening_export_url ?? site_url('inventory/stock/opening/division/export-template'));
$stockOpeningExportExistingUrl = (string)($stock_opening_export_existing_url ?? site_url('inventory/stock/opening/division/export-existing'));
$stockOpeningImportUrl = (string)($stock_opening_import_url ?? site_url('inventory/stock/opening/division/import'));
$stockOpeningCsrfToken = (string)($stock_opening_csrf_token ?? '');
$stockScope = strtoupper(trim((string)($stock_scope ?? 'WAREHOUSE')));
if (!in_array($stockScope, ['WAREHOUSE', 'DIVISION'], true)) {
  $stockScope = 'WAREHOUSE';
}
$isDivisionScope = (bool)($is_division_scope ?? false);
$selectedDivisionId = (int)($division_id ?? 0);
$selectedDestination = strtoupper(trim((string)($destination ?? 'ALL')));
if ($selectedDestination === '') {
  $selectedDestination = $isDivisionScope ? 'OTHER' : 'ALL';
}
$selectedMonthValue = $month !== '' ? substr((string)$month, 0, 7) : date('Y-m');
$selectedMonthDate = $selectedMonthValue . '-01';
$nextGenerateMonthValue = date('Y-m', strtotime('+1 month', strtotime($selectedMonthDate)));
$nextGenerateOpeningUrl = site_url('inventory/stock/stok-awal/warehouse?month=' . rawurlencode($nextGenerateMonthValue));
$tableColspan = $isDivisionScope ? 10 : 8;
$rowsData = is_array($rows ?? null) ? $rows : [];
$summaryRows = count($rowsData);
$summaryQtyContent = 0.0;
$summaryValue = 0.0;
foreach ($rowsData as $row) {
  $summaryQtyContent += (float)($row['opening_qty_content'] ?? 0);
  $summaryValue += (float)($row['opening_total_value'] ?? 0);
}
?>

<style>
  .opening-row-label {
    min-height: 2.4em;
    display: flex;
    align-items: flex-end;
    line-height: 1.2;
  }
  .opening-bulk-card {
    border: 1px solid #eadfce;
    border-radius: 18px;
    background: linear-gradient(135deg, #fffaf3 0%, #f7efe4 100%);
    box-shadow: 0 0.75rem 1.8rem rgba(102, 73, 35, 0.08);
  }
  .opening-bulk-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  .opening-bulk-actions .btn {
    min-width: 170px;
  }
  .opening-bulk-upload {
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid #eadfce;
    border-radius: 16px;
    padding: 1rem;
  }
  .opening-scroll-wrap {
    max-height: 72vh;
    overflow-y: auto;
    overflow-x: auto;
  }
  .opening-scroll-wrap table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #fff;
    border-bottom: 2px solid rgba(0,0,0,.08);
  }
</style>

<div class="mb-2">
  <h4 class="mb-1"><i class="ri ri-archive-stack-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
  <small class="text-muted">Input opening stok per profile. Tanggal posting otomatis tanggal 1 dari Snapshot Month.</small>
</div>
<div class="d-flex flex-wrap gap-2 mb-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => $isDivisionScope ? 'DIVISION' : 'WAREHOUSE', 'active_tab' => 'opening']); ?>
</div>
<?php if ($isDivisionScope): ?>
<?php $this->load->view('purchase/_division_stock_generate_btn', [
  'division_action_params' => ['month' => $month !== '' ? substr((string)$month, 0, 7) : date('Y-m'), 'division_id' => (string)(int)$selectedDivisionId, 'destination_type' => $selectedDestination],
]); ?>
<?php else: ?>
<?php $this->load->view('purchase/_warehouse_stock_generate_btn', [
  'warehouse_action_params' => ['month' => $selectedMonthValue],
]); ?>
<?php endif; ?>

<div id="alert-area"></div>

<?php if ($this->session->flashdata('success')): ?>
  <div class="alert alert-success mt-3"><?php echo html_escape((string)$this->session->flashdata('success')); ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('warning')): ?>
  <div class="alert alert-warning mt-3"><?php echo html_escape((string)$this->session->flashdata('warning')); ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('error')): ?>
  <div class="alert alert-danger mt-3"><?php echo html_escape((string)$this->session->flashdata('error')); ?></div>
<?php endif; ?>
<?php if (!$isDivisionScope): ?>
  <div class="alert alert-info mt-3">
    Generate dari bulan <strong><?php echo html_escape($selectedMonthValue); ?></strong> akan membuat stok awal otomatis untuk bulan
    <strong><?php echo html_escape($nextGenerateMonthValue); ?></strong>.
    <a href="<?php echo html_escape($nextGenerateOpeningUrl); ?>" class="ms-2">Lihat halaman stok awal</a>
  </div>
<?php endif; ?>

<?php if ($isDivisionScope): ?>
<div class="card opening-bulk-card mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
      <div>
        <h6 class="mb-1">Import / Export Opening Massal</h6>
        <small class="text-muted">Template mengikuti divisi, tujuan, dan snapshot month yang sedang dipilih di form opening. Isi nilai opening di Excel lalu upload kembali dalam format XLSX.</small>
      </div>
      <div class="opening-bulk-actions">
        <form method="get" action="<?php echo html_escape($stockOpeningExportUrl); ?>" id="stock-opening-export-form" class="d-inline">
          <input type="hidden" name="division_id" value="<?php echo (int)$selectedDivisionId; ?>">
          <input type="hidden" name="destination" value="<?php echo html_escape($selectedDestination); ?>">
          <input type="hidden" name="month" value="<?php echo html_escape($month !== '' ? substr((string)$month, 0, 7) : date('Y-m')); ?>">
          <button type="submit" class="btn btn-outline-secondary btn-sm">Export Template Excel</button>
        </form>
        <form method="get" action="<?php echo html_escape($stockOpeningExportExistingUrl); ?>" id="stock-opening-export-existing-form" class="d-inline">
          <input type="hidden" name="division_id" value="<?php echo (int)$selectedDivisionId; ?>">
          <input type="hidden" name="destination" value="<?php echo html_escape($selectedDestination); ?>">
          <input type="hidden" name="month" value="<?php echo html_escape($month !== '' ? substr((string)$month, 0, 7) : date('Y-m')); ?>">
          <input type="hidden" name="q" value="<?php echo html_escape((string)$q); ?>">
          <button type="submit" class="btn btn-outline-dark btn-sm">Export Data Existing</button>
        </form>
      </div>
    </div>
    <div class="opening-bulk-upload">
      <form method="post" action="<?php echo html_escape($stockOpeningImportUrl); ?>" enctype="multipart/form-data" class="row g-3 align-items-end" id="stock-opening-import-form">
        <input type="hidden" name="stock_opening_csrf" value="<?php echo html_escape($stockOpeningCsrfToken); ?>">
        <input type="hidden" name="division_id" value="<?php echo (int)$selectedDivisionId; ?>">
        <input type="hidden" name="destination" value="<?php echo html_escape($selectedDestination); ?>">
        <input type="hidden" name="month" value="<?php echo html_escape($month !== '' ? substr((string)$month, 0, 7) : date('Y-m')); ?>">
        <div class="col-lg-9">
          <label class="form-label mb-1">File Import Excel</label>
          <input type="file" name="import_file" class="form-control" accept=".xlsx" required>
          <small class="text-muted">Satu file hanya untuk divisi yang dipilih. Kolom utama template: item_code atau material_code, buy_uom_code, content_uom_code, opening_qty_buy, opening_avg_cost_per_content.</small>
        </div>
        <div class="col-lg-3 d-grid">
          <button type="submit" class="btn btn-primary">Import Opening Excel</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="mb-3">Form Opening Stok</h6>
        <form id="opening-form" class="row g-2" autocomplete="off">
          <?php if ($isDivisionScope): ?>
          <div class="col-6">
            <label class="form-label">Divisi</label>
            <select class="form-select" id="division_id">
              <option value="">Pilih divisi...</option>
              <?php foreach (($divisions ?? []) as $d): ?>
                <?php
                  $id = (int)($d['id'] ?? 0);
                  $code = trim((string)($d['code'] ?? ''));
                  $name = trim((string)($d['name'] ?? ''));
                  $label = $code !== '' ? ($code . ' - ' . $name) : ($name !== '' ? $name : (string)$id);
                  $destinationDefault = strtoupper(trim((string)($d['destination_default'] ?? 'OTHER')));
                  $destinationAllowed = trim((string)($d['destination_allowed'] ?? 'BAR,KITCHEN,ROASTERY,BAR_EVENT,KITCHEN_EVENT,ROASTERY_EVENT,OFFICE,OTHER'));
                ?>
                <option value="<?php echo $id; ?>"
                  data-code="<?php echo html_escape($code); ?>"
                  data-name="<?php echo html_escape($name); ?>"
                  data-destination-default="<?php echo html_escape($destinationDefault); ?>"
                  data-destination-allowed="<?php echo html_escape($destinationAllowed); ?>"
                  <?php echo $selectedDivisionId === $id ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Tujuan</label>
            <select class="form-select" id="destination_type">
              <option value="BAR" <?php echo $selectedDestination === 'BAR' ? 'selected' : ''; ?>>BAR (Reguler)</option>
              <option value="KITCHEN" <?php echo $selectedDestination === 'KITCHEN' ? 'selected' : ''; ?>>KITCHEN (Reguler)</option>
              <option value="ROASTERY" <?php echo $selectedDestination === 'ROASTERY' ? 'selected' : ''; ?>>ROASTERY (Reguler)</option>
              <option value="BAR_EVENT" <?php echo $selectedDestination === 'BAR_EVENT' ? 'selected' : ''; ?>>BAR_EVENT</option>
              <option value="KITCHEN_EVENT" <?php echo $selectedDestination === 'KITCHEN_EVENT' ? 'selected' : ''; ?>>KITCHEN_EVENT</option>
              <option value="ROASTERY_EVENT" <?php echo $selectedDestination === 'ROASTERY_EVENT' ? 'selected' : ''; ?>>ROASTERY_EVENT</option>
              <option value="OFFICE" <?php echo $selectedDestination === 'OFFICE' ? 'selected' : ''; ?>>OFFICE</option>
              <option value="OTHER" <?php echo ($selectedDestination === 'OTHER' || $selectedDestination === 'ALL') ? 'selected' : ''; ?>>OTHER</option>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-6">
            <label class="form-label">Snapshot Month</label>
            <input type="month" class="form-control" id="snapshot_month" value="<?php echo html_escape($month !== '' ? substr((string)$month, 0, 7) : date('Y-m')); ?>" required>
          </div>
          <div class="col-6">
            <label class="form-label">Tanggal Posting (otomatis)</label>
            <input type="date" class="form-control" id="movement_date_preview" value="<?php echo html_escape($month !== '' ? (substr((string)$month, 0, 7) . '-01') : date('Y-m-01')); ?>" readonly disabled>
          </div>
          <div class="col-12">
            <label class="form-label">Item</label>
            <input type="hidden" id="item_id" value="">
            <input type="hidden" id="profile_key" value="">
            <input type="text" class="form-control" id="item_search" placeholder="Ketik kode/nama item minimal 2 huruf..." autocomplete="off" required>
            <div id="item_search_preview" class="list-group mt-1" style="max-height:220px; overflow:auto;"></div>
            <small id="item_search_hint" class="text-muted d-block mt-1">Belum ada item dipilih.</small>
          </div>
          <div class="col-6">
            <label class="form-label">UOM Beli</label>
            <select class="form-select" id="buy_uom_id" required>
              <option value="">Pilih...</option>
              <?php foreach (($uoms ?? []) as $u): ?>
                <option value="<?php echo (int)$u['id']; ?>"><?php echo html_escape((string)$u['code'] . ' - ' . (string)$u['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">UOM Isi</label>
            <select class="form-select" id="content_uom_id" required>
              <option value="">Pilih...</option>
              <?php foreach (($uoms ?? []) as $u): ?>
                <option value="<?php echo (int)$u['id']; ?>"><?php echo html_escape((string)$u['code'] . ' - ' . (string)$u['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-3">
            <label class="form-label opening-row-label">Qty Beli (UOM Beli)</label>
            <input type="number" class="form-control" id="opening_qty_buy" min="0" step="0.01" value="0">
          </div>
          <div class="col-3">
            <label class="form-label opening-row-label">Konversi Isi per 1 Beli</label>
            <input type="number" class="form-control" id="profile_content_per_buy" min="0.01" step="0.01" value="1">
          </div>
          <div class="col-3">
            <label class="form-label opening-row-label">Qty Isi (otomatis)</label>
            <input type="number" class="form-control" id="opening_qty_content" min="0" step="0.01" value="0" required readonly>
          </div>
          <div class="col-3">
            <label class="form-label opening-row-label" id="cost_label"><?php echo $stockScope === 'WAREHOUSE' ? 'Harga Satuan/Beli' : 'Avg Cost/Isi'; ?></label>
            <input type="number" class="form-control" id="opening_avg_cost_per_content" min="0" step="0.01" value="0">
          </div>
          <div class="col-12">
            <label class="form-label">Profile Name</label>
            <input type="text" class="form-control" id="profile_name" placeholder="Contoh: AIR MINERAL BOTOL">
          </div>
          <div class="col-6">
            <label class="form-label">Merk</label>
            <input type="text" class="form-control" id="profile_brand" placeholder="Contoh: CLEO">
          </div>
          <div class="col-6">
            <label class="form-label">Exp Date</label>
            <input type="date" class="form-control" id="profile_expired_date">
          </div>
          <div class="col-12">
            <label class="form-label">Keterangan</label>
            <input type="text" class="form-control" id="profile_description" placeholder="Opsional">
          </div>
          <div class="col-12">
            <label class="form-label">Mode Posting</label>
            <select class="form-select" id="replace_mode">
              <option value="1">Set saldo live persis ke angka opening (disarankan)</option>
              <option value="0">Tambah ke saldo live yang sekarang (mode koreksi)</option>
            </select>
            <small class="text-muted">Mode ini menentukan apakah angka opening menjadi saldo final, atau hanya ditambahkan sebagai delta.</small>
          </div>
          <div class="col-12">
            <label class="form-label">Catatan</label>
            <textarea class="form-control" id="notes" rows="2"></textarea>
          </div>
          <div class="col-12 d-grid">
            <button type="button" class="btn btn-primary" id="btn-save-opening">Simpan Opening</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-body py-3">
        <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
          <?php if ($isDivisionScope): ?>
          <div class="col-md-3">
            <label class="form-label mb-1">Divisi</label>
            <select class="form-select" name="division_id">
              <option value="">Semua Divisi</option>
              <?php foreach (($divisions ?? []) as $d): ?>
                <?php
                  $id = (int)($d['id'] ?? 0);
                  $code = trim((string)($d['code'] ?? ''));
                  $name = trim((string)($d['name'] ?? ''));
                  $label = $code !== '' ? ($code . ' - ' . $name) : ($name !== '' ? $name : (string)$id);
                ?>
                <option value="<?php echo $id; ?>" <?php echo $selectedDivisionId === $id ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Tujuan</label>
            <select class="form-select" name="destination">
              <option value="ALL" <?php echo $selectedDestination === 'ALL' ? 'selected' : ''; ?>>Semua</option>
              <option value="REGULER" <?php echo $selectedDestination === 'REGULER' ? 'selected' : ''; ?>>Reguler</option>
              <option value="EVENT" <?php echo $selectedDestination === 'EVENT' ? 'selected' : ''; ?>>Event</option>
              <option value="BAR" <?php echo $selectedDestination === 'BAR' ? 'selected' : ''; ?>>BAR</option>
              <option value="KITCHEN" <?php echo $selectedDestination === 'KITCHEN' ? 'selected' : ''; ?>>KITCHEN</option>
              <option value="ROASTERY" <?php echo $selectedDestination === 'ROASTERY' ? 'selected' : ''; ?>>ROASTERY</option>
              <option value="BAR_EVENT" <?php echo $selectedDestination === 'BAR_EVENT' ? 'selected' : ''; ?>>BAR_EVENT</option>
              <option value="KITCHEN_EVENT" <?php echo $selectedDestination === 'KITCHEN_EVENT' ? 'selected' : ''; ?>>KITCHEN_EVENT</option>
              <option value="ROASTERY_EVENT" <?php echo $selectedDestination === 'ROASTERY_EVENT' ? 'selected' : ''; ?>>ROASTERY_EVENT</option>
              <option value="OFFICE" <?php echo $selectedDestination === 'OFFICE' ? 'selected' : ''; ?>>OFFICE</option>
              <option value="OTHER" <?php echo $selectedDestination === 'OTHER' ? 'selected' : ''; ?>>OTHER</option>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-md-3">
            <label class="form-label mb-1">Bulan</label>
            <input type="month" class="form-control" name="month" value="<?php echo html_escape($month !== '' ? substr((string)$month, 0, 7) : date('Y-m')); ?>">
          </div>
          <div class="col-md-<?php echo $isDivisionScope ? '4' : '5'; ?>">
            <label class="form-label mb-1">Cari</label>
            <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="Item / profile / merk / keterangan">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Limit</label>
            <input type="number" class="form-control" name="limit" min="1" max="500" value="<?php echo (int)$limit; ?>">
          </div>
          <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Filter</button>
          </div>
          <div class="col-md-2 d-grid">
            <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-danger">Clear</a>
          </div>
        </form>
      </div>
    </div>

    <div class="row g-2 mb-3">
      <div class="col-6 col-md-4"><div class="card"><div class="card-body py-2"><div class="small text-muted">Snapshot</div><div class="h5 mb-0"><?php echo number_format($summaryRows); ?></div></div></div></div>
      <div class="col-6 col-md-4"><div class="card"><div class="card-body py-2"><div class="small text-muted">Qty Isi Total</div><div class="h5 mb-0"><?php echo number_format($summaryQtyContent, 2, ',', '.'); ?></div></div></div></div>
      <div class="col-6 col-md-4"><div class="card"><div class="card-body py-2"><div class="small text-muted">Total Value</div><div class="h5 mb-0"><?php echo number_format($summaryValue, 2, ',', '.'); ?></div></div></div></div>
    </div>

    <div class="card">
      <div class="table-responsive opening-scroll-wrap">
        <table class="table table-striped table-hover mb-0">
          <thead>
            <tr>
              <th>Bulan</th>
              <?php if ($isDivisionScope): ?><th>Divisi/Tujuan</th><?php endif; ?>
              <th>Item</th>
              <th>Profile</th>
              <th class="text-end">Qty Beli</th>
              <th class="text-end">Qty Isi</th>
              <th class="text-end">Avg Cost</th>
              <th class="text-end">Total Value</th>
              <th>Sumber</th>
              <?php if ($isDivisionScope): ?><th style="width:84px;">Aksi</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="<?php echo $tableColspan; ?>" class="text-center text-muted py-4">Belum ada opening stok.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?php echo html_escape((string)$r['snapshot_month']); ?></td>
                  <?php if ($isDivisionScope): ?>
                  <td>
                      <?php
                        $divCode = trim((string)($r['division_code'] ?? ''));
                        $divName = trim((string)($r['division_name'] ?? ''));
                        $divLabel = $divCode !== '' ? ($divCode . ' - ' . $divName) : ($divName !== '' ? $divName : (string)($r['division_id'] ?? '-'));
                        $dest = strtoupper(trim((string)($r['destination_type'] ?? 'OTHER')));
                      ?>
                      <?php echo html_escape($divLabel); ?><br>
                      <small class="text-muted"><?php echo html_escape($dest); ?></small>
                  </td>
                  <?php endif; ?>
                  <td>
                    <?php
                      $stockDomainRow = strtoupper(trim((string)($r['stock_domain'] ?? 'ITEM')));
                      $itemText = trim((string)($r['item_code'] ?? '') . ' - ' . (string)($r['item_name'] ?? ''));
                      $materialText = trim((string)($r['material_code'] ?? '') . ' - ' . (string)($r['material_name'] ?? ''));
                      $preferMaterial = $stockDomainRow === 'MATERIAL' || (int)($r['material_id'] ?? 0) > 0;
                      if ($preferMaterial) {
                        $objText = $materialText !== ' -' && $materialText !== '' ? $materialText : ($itemText !== ' -' && $itemText !== '' ? $itemText : '-');
                      } else {
                        $objText = $itemText !== ' -' && $itemText !== '' ? $itemText : ($materialText !== ' -' && $materialText !== '' ? $materialText : '-');
                      }
                    ?>
                    <?php echo html_escape($objText); ?>
                  </td>
                  <td>
                    <?php echo html_escape((string)($r['profile_name'] ?? '-')); ?><br>
                    <small class="text-muted"><?php echo html_escape((string)($r['profile_brand'] ?? '-')); ?> | <?php echo html_escape((string)($r['profile_description'] ?? '-')); ?></small>
                  </td>
                  <td class="text-end"><?php echo ui_num((float)$r['opening_qty_buy']); ?></td>
                  <td class="text-end"><?php echo ui_num((float)$r['opening_qty_content']); ?></td>
                  <td class="text-end"><?php echo ui_num((float)$r['opening_avg_cost_per_content']); ?></td>
                  <td class="text-end"><?php echo number_format((float)$r['opening_total_value'], 2, ',', '.'); ?></td>
                  <td><?php echo html_escape((string)($r['source_type'] ?? '-')); ?></td>
                  <?php if ($isDivisionScope): ?>
                  <td>
                    <button type="button" class="btn btn-sm btn-outline-warning btn-void-opening" data-id="<?php echo (int)($r['id'] ?? 0); ?>">Void</button>
                  </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var storeUrl = <?php echo json_encode($storeUrl); ?>;
  var stockOpeningStepUpUrl = <?php echo json_encode($stepUpUrl); ?>;
  var voidUrlBase = <?php echo json_encode($voidUrlBase); ?>;
  var itemSearchUrl = <?php echo json_encode($itemSearchUrl); ?>;
  var stockOpeningCsrfToken = <?php echo json_encode($stockOpeningCsrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var alertArea = document.getElementById('alert-area');
  var stockScope = <?php echo json_encode($stockScope); ?>;
  var divisionEl = document.getElementById('division_id');
  var destinationEl = document.getElementById('destination_type');
  var snapshotEl = document.getElementById('snapshot_month');
  var movementDatePreviewEl = document.getElementById('movement_date_preview');
  var itemIdEl = document.getElementById('item_id');
  var profileKeyEl = document.getElementById('profile_key');
  var itemSearchEl = document.getElementById('item_search');
  var itemPreviewEl = document.getElementById('item_search_preview');
  var itemHintEl = document.getElementById('item_search_hint');
  var profileNameEl = document.getElementById('profile_name');
  var profileBrandEl = document.getElementById('profile_brand');
  var profileExpiredEl = document.getElementById('profile_expired_date');
  var profileDescriptionEl = document.getElementById('profile_description');
  var buyUomEl = document.getElementById('buy_uom_id');
  var contentUomEl = document.getElementById('content_uom_id');
  var qtyBuyEl = document.getElementById('opening_qty_buy');
  var qtyContentEl = document.getElementById('opening_qty_content');
  var ratioEl = document.getElementById('profile_content_per_buy');
  var costInputEl = document.getElementById('opening_avg_cost_per_content');
  var costLabelEl = document.getElementById('cost_label');
  var saveBtnEl = document.getElementById('btn-save-opening');
  var openingExportForm = document.getElementById('stock-opening-export-form');
  var openingExportExistingForm = document.getElementById('stock-opening-export-existing-form');
  var openingImportForm = document.getElementById('stock-opening-import-form');

  var itemSearchTimer = null;
  var itemLastQuery = '';
  var selectedItemMeta = null;
  var destinationLabels = {
    BAR: 'BAR (Reguler)',
    KITCHEN: 'KITCHEN (Reguler)',
    BAR_EVENT: 'BAR_EVENT',
    KITCHEN_EVENT: 'KITCHEN_EVENT',
    ROASTERY: 'ROASTERY (Reguler)',
    ROASTERY_EVENT: 'ROASTERY_EVENT',
    OFFICE: 'OFFICE',
    OTHER: 'OTHER'
  };

  function stockOpeningMutationHeaders() {
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Stock-Opening-Csrf': stockOpeningCsrfToken
    };
  }

  function postStockOpeningJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: stockOpeningMutationHeaders(),
      body: JSON.stringify(payload)
    })
    .then(function (r) {
      return r.text().then(function (text) {
        var parsed = null;
        try { parsed = text ? JSON.parse(text) : null; } catch (e) { parsed = null; }
        if (r.status >= 400 || !parsed || !parsed.ok) {
          var fallbackMessage = (text || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
          throw new Error((parsed && parsed.message) ? parsed.message : (fallbackMessage || 'Permintaan opening gagal.'));
        }
        return parsed;
      });
    });
  }

  function requestOpeningStepUp(operation, payload) {
    if (!window.Swal) {
      return Promise.reject(new Error('Dialog verifikasi ulang belum tersedia. Muat ulang halaman lalu coba kembali.'));
    }
    var isVoid = operation === 'VOID';
    var isImport = operation === 'IMPORT';
    return window.Swal.fire({
      title: isVoid ? 'Verifikasi VOID Opening' : (isImport ? 'Verifikasi Import Opening' : 'Verifikasi Simpan Opening'),
      text: isVoid
        ? 'Masukkan password akun untuk membatalkan opening dan menjalankan rollback stok.'
        : (isImport
          ? 'Masukkan password akun untuk mengimpor opening pada satu divisi yang dipilih.'
          : 'Masukkan password akun untuk menyimpan dan memposting opening stok.'),
      input: 'password',
      inputAttributes: { autocomplete: 'current-password', autocapitalize: 'off' },
      inputPlaceholder: 'Password akun Anda',
      showCancelButton: true,
      confirmButtonText: 'Verifikasi & Lanjutkan',
      cancelButtonText: 'Batal',
      focusConfirm: false,
      preConfirm: function (password) {
        if (!String(password || '').trim()) {
          window.Swal.showValidationMessage('Password akun wajib diisi.');
          return false;
        }
        return String(password);
      }
    }).then(function (dialog) {
      if (!dialog.isConfirmed) return null;
      return postStockOpeningJson(stockOpeningStepUpUrl, {
        operation: operation,
        stock_scope: stockScope,
        division_id: Number(payload.division_id || 0),
        snapshot_id: Number(payload.snapshot_id || 0),
        password: String(dialog.value || '')
      }).then(function (result) {
        return result.step_up_proof || null;
      });
    });
  }

  function bindOpeningImportStepUp(form) {
    if (!form) return;
    form.addEventListener('submit', function (event) {
      if (form.dataset.stepUpReady === '1') {
        form.dataset.stepUpReady = '';
        return;
      }
      event.preventDefault();
      var divisionField = form.querySelector('[name="division_id"]');
      var divisionId = Number(divisionField ? divisionField.value : 0);
      if (!divisionId) {
        showAlert('warning', 'Pilih satu divisi aktif sebelum import opening.');
        return;
      }
      requestOpeningStepUp('IMPORT', { division_id: divisionId })
        .then(function (proof) {
          if (!proof) return;
          var proofField = form.querySelector('[name="step_up_proof"]');
          if (!proofField) {
            proofField = document.createElement('input');
            proofField.type = 'hidden';
            proofField.name = 'step_up_proof';
            form.appendChild(proofField);
          }
          proofField.value = proof;
          form.dataset.stepUpReady = '1';
          form.submit();
        })
        .catch(function (error) {
          showAlert('danger', error.message || 'Verifikasi import opening gagal.');
        });
    });
  }

  bindOpeningImportStepUp(openingImportForm);

  function sanitizeDestinationList(rawList) {
    var fallback = ['BAR', 'KITCHEN', 'ROASTERY', 'BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT', 'OFFICE', 'OTHER'];
    var seen = {};
    var allowed = [];
    String(rawList || '')
      .split(',')
      .forEach(function (part) {
        var code = String(part || '').trim().toUpperCase();
        if (!destinationLabels[code] || seen[code]) {
          return;
        }
        seen[code] = true;
        allowed.push(code);
      });

    if (allowed.length === 0) {
      return fallback;
    }

    return allowed;
  }

  function applyDivisionDestinationRule(forceDefault) {
    if (!destinationEl) {
      return;
    }

    var previous = String(destinationEl.value || '').toUpperCase();
    var allowed = ['BAR', 'KITCHEN', 'ROASTERY', 'BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT', 'OFFICE', 'OTHER'];
    var defaultDestination = 'OTHER';

    if (divisionEl && divisionEl.value) {
      var selectedOption = divisionEl.options[divisionEl.selectedIndex] || null;
      if (selectedOption) {
        allowed = sanitizeDestinationList(selectedOption.getAttribute('data-destination-allowed'));
        var preset = String(selectedOption.getAttribute('data-destination-default') || '').trim().toUpperCase();
        if (preset && allowed.indexOf(preset) !== -1) {
          defaultDestination = preset;
        } else if (allowed.length > 0) {
          defaultDestination = allowed[0];
        }
      }
    }

    destinationEl.innerHTML = allowed.map(function (code) {
      return '<option value="' + code + '">' + destinationLabels[code] + '</option>';
    }).join('');

    var nextValue = defaultDestination;
    if (!forceDefault && previous && allowed.indexOf(previous) !== -1) {
      nextValue = previous;
    }
    destinationEl.value = nextValue;
  }

  if (divisionEl && destinationEl) {
    divisionEl.addEventListener('change', function () {
      applyDivisionDestinationRule(true);
      syncMassOpeningForms();
    });
    applyDivisionDestinationRule(true);
  }

  function refreshQtyContent() {
    var qtyBuy = Number(qtyBuyEl.value || 0);
    var ratio = Number(ratioEl.value || 0);
    if (ratio <= 0) {
      ratio = 1;
      ratioEl.value = '1';
    }
    qtyContentEl.value = String(Math.max(0, qtyBuy * ratio));
  }

  qtyBuyEl.addEventListener('input', refreshQtyContent);
  ratioEl.addEventListener('input', refreshQtyContent);
  refreshQtyContent();

  function setCostMode() {
    if (stockScope === 'WAREHOUSE') {
      costLabelEl.textContent = 'Harga Satuan/Beli';
      return;
    }

    costLabelEl.textContent = 'Avg Cost/Isi';
  }

  setCostMode();

  function monthStartDate(monthValue) {
    var raw = String(monthValue || '').trim();
    if (!/^\d{4}-\d{2}$/.test(raw)) {
      var now = new Date();
      var m = String(now.getMonth() + 1).padStart(2, '0');
      return String(now.getFullYear()) + '-' + m + '-01';
    }
    return raw + '-01';
  }

  function syncMovementDatePreview() {
    if (!movementDatePreviewEl || !snapshotEl) {
      return;
    }
    movementDatePreviewEl.value = monthStartDate(snapshotEl.value);
  }

  if (snapshotEl) {
    snapshotEl.addEventListener('change', syncMovementDatePreview);
    snapshotEl.addEventListener('input', syncMovementDatePreview);
    snapshotEl.addEventListener('change', syncMassOpeningForms);
    snapshotEl.addEventListener('input', syncMassOpeningForms);
  }
  syncMovementDatePreview();

  function syncMassOpeningForms() {
    var monthValue = snapshotEl ? String(snapshotEl.value || '') : '';
    var divisionValue = divisionEl ? String(divisionEl.value || '') : '';
    var destinationValue = destinationEl ? String(destinationEl.value || '') : 'OTHER';
    [openingExportForm, openingExportExistingForm, openingImportForm].forEach(function (form) {
      if (!form) {
        return;
      }
      var monthInput = form.querySelector('input[name="month"]');
      var divisionInput = form.querySelector('input[name="division_id"]');
      var destinationInput = form.querySelector('input[name="destination"]');
      if (monthInput) monthInput.value = monthValue;
      if (divisionInput) divisionInput.value = divisionValue;
      if (destinationInput) destinationInput.value = destinationValue;
    });
  }

  syncMassOpeningForms();

  [openingExportForm, openingExportExistingForm, openingImportForm].forEach(function (form) {
    if (!form) {
      return;
    }
    form.addEventListener('submit', function (event) {
      syncMassOpeningForms();
      if (stockScope === 'DIVISION' && divisionEl && !divisionEl.value) {
        event.preventDefault();
        showAlert('warning', 'Pilih divisi dulu sebelum export atau import opening massal.');
      }
    });
  });

  function setSavingState(isSaving) {
    if (!saveBtnEl) {
      return;
    }
    saveBtnEl.disabled = !!isSaving;
    saveBtnEl.innerHTML = isSaving
      ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Menyimpan...'
      : 'Simpan Opening';
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatSuggestedPrice(value, decimals) {
    var number = Number(value || 0);
    if (!isFinite(number) || number <= 0) {
      return '';
    }
    return number.toLocaleString('id-ID', {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals
    });
  }

  function renderItemPreview(items) {
    if (!Array.isArray(items) || items.length === 0) {
      itemPreviewEl.innerHTML = '<div class="list-group-item text-muted">Item tidak ditemukan.</div>';
      return;
    }

    var html = '';
    items.forEach(function (it) {
      var id = Number(it.id || 0);
      var name = (it.item_name || '').toString();
      var sourceType = String(it.source_type || 'MASTER').toUpperCase();
      var brand = (it.profile_brand || '').toString();
      var buyUomCode = (it.default_buy_uom_code || '').toString();
      var contentUomCode = (it.default_content_uom_code || '').toString();
      var label = name;
      var profileName = (it.profile_name || '').toString();
      var profileDescription = (it.profile_description || '').toString();
      var suggestedUnitPrice = Number(it.suggested_unit_price || 0);
      var suggestedAvgCost = Number(it.suggested_avg_cost_per_content || 0);
      var contentPerBuy = Number(it.default_content_per_buy || 0);
      var unitPrice = stockScope === 'WAREHOUSE' ? suggestedUnitPrice : suggestedAvgCost;
      var unitPriceText = unitPrice > 0
        ? ('Rp ' + formatSuggestedPrice(unitPrice, stockScope === 'WAREHOUSE' ? 2 : 6))
        : '-';
      var satuanText = buyUomCode || contentUomCode || '-';
      var isiText = (contentPerBuy > 0 ? formatSuggestedPrice(contentPerBuy, 4) : '-') + (contentUomCode ? ' ' + contentUomCode : '');
      var detailRows = [
        ['Nama', name || '-'],
        ['Merk', brand || '-'],
        ['Satuan', satuanText],
        ['Isi', isiText],
        ['Harga Satuan', unitPriceText],
        ['Keterangan', profileDescription || '-']
      ];
      var detailHtml = detailRows.map(function (pair) {
        return '<div class="small text-muted"><span class="fw-semibold text-dark">' + escapeHtml(pair[0]) + ':</span> ' + escapeHtml(pair[1]) + '</div>';
      }).join('');

      html += '<button type="button" class="list-group-item list-group-item-action"'
        + ' data-item-id="' + id + '"'
        + ' data-item-name="' + escapeHtml(name) + '"'
        + ' data-item-label="' + escapeHtml(label) + '"'
        + ' data-source-type="' + escapeHtml(sourceType) + '"'
        + ' data-profile-key="' + escapeHtml(it.profile_key || '') + '"'
        + ' data-profile-name="' + escapeHtml(profileName) + '"'
        + ' data-profile-brand="' + escapeHtml(brand) + '"'
        + ' data-profile-description="' + escapeHtml(profileDescription) + '"'
        + ' data-profile-expired-date="' + escapeHtml(it.profile_expired_date || '') + '"'
        + ' data-buy-uom-id="' + Number(it.default_buy_uom_id || 0) + '"'
        + ' data-content-uom-id="' + Number(it.default_content_uom_id || 0) + '"'
        + ' data-content-per-buy="' + Number(it.default_content_per_buy || 1) + '"'
        + ' data-is-material="' + Number(it.is_material || 0) + '"'
        + ' data-material-id="' + Number(it.material_id || 0) + '"'
        + ' data-suggested-unit-price="' + Number(it.suggested_unit_price || 0) + '"'
        + ' data-suggested-avg-cost="' + Number(it.suggested_avg_cost_per_content || 0) + '"'
        + ' data-suggested-price-source="' + escapeHtml(it.suggested_price_source || '') + '">'
        + '<div class="fw-semibold mb-1">' + escapeHtml(label || '-') + '</div>'
        + detailHtml
        + '</button>';
    });
    itemPreviewEl.innerHTML = html;
  }

  function searchItems() {
    var q = (itemSearchEl.value || '').trim();
    itemIdEl.value = '';
    if (profileKeyEl) {
      profileKeyEl.value = '';
    }
    selectedItemMeta = null;
    contentUomEl.disabled = false;
    setCostMode();
    itemHintEl.textContent = 'Belum ada item dipilih.';

    if (q.length < 2) {
      itemPreviewEl.innerHTML = '';
      return;
    }

    itemLastQuery = q;
    fetch(itemSearchUrl + '?q=' + encodeURIComponent(q) + '&limit=20', {
      headers: { 'Accept': 'application/json' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (q !== itemLastQuery) return;
      renderItemPreview((res && res.items) ? res.items : []);
    })
    .catch(function () {
      itemPreviewEl.innerHTML = '<div class="list-group-item text-danger">Gagal mengambil data item.</div>';
    });
  }

  itemSearchEl.addEventListener('input', function () {
    if (itemSearchTimer) {
      window.clearTimeout(itemSearchTimer);
    }
    itemSearchTimer = window.setTimeout(searchItems, 250);
  });

  itemPreviewEl.addEventListener('click', function (ev) {
    var target = ev.target;
    while (target && target !== itemPreviewEl && !target.getAttribute('data-item-id')) {
      target = target.parentElement;
    }
    if (!target || target === itemPreviewEl) return;

    var id = Number(target.getAttribute('data-item-id') || 0);
    var sourceType = String(target.getAttribute('data-source-type') || 'MASTER').toUpperCase();
    var label = target.getAttribute('data-item-label') || '';
    if (id <= 0) return;

    selectedItemMeta = {
      id: id,
      source_type: sourceType,
      profile_key: String(target.getAttribute('data-profile-key') || ''),
      profile_name: String(target.getAttribute('data-profile-name') || ''),
      profile_brand: String(target.getAttribute('data-profile-brand') || ''),
      profile_description: String(target.getAttribute('data-profile-description') || ''),
      profile_expired_date: String(target.getAttribute('data-profile-expired-date') || ''),
      item_name: String(target.getAttribute('data-item-name') || ''),
      default_buy_uom_id: Number(target.getAttribute('data-buy-uom-id') || 0),
      default_content_uom_id: Number(target.getAttribute('data-content-uom-id') || 0),
      default_content_per_buy: Number(target.getAttribute('data-content-per-buy') || 1),
      is_material: Number(target.getAttribute('data-is-material') || 0),
      material_id: Number(target.getAttribute('data-material-id') || 0),
      suggested_unit_price: Number(target.getAttribute('data-suggested-unit-price') || 0),
      suggested_avg_cost_per_content: Number(target.getAttribute('data-suggested-avg-cost') || 0),
      suggested_price_source: String(target.getAttribute('data-suggested-price-source') || '')
    };

    if (selectedItemMeta.default_buy_uom_id > 0) {
      buyUomEl.value = String(selectedItemMeta.default_buy_uom_id);
    }
    if (selectedItemMeta.default_content_uom_id > 0) {
      contentUomEl.value = String(selectedItemMeta.default_content_uom_id);
    }
    if (selectedItemMeta.default_content_per_buy > 0) {
      ratioEl.value = String(selectedItemMeta.default_content_per_buy);
      refreshQtyContent();
    }

    if (costInputEl) {
      var suggestedCost = stockScope === 'WAREHOUSE'
        ? Number(selectedItemMeta.suggested_unit_price || 0)
        : Number(selectedItemMeta.suggested_avg_cost_per_content || 0);
      if (suggestedCost > 0) {
        costInputEl.value = stockScope === 'WAREHOUSE'
          ? suggestedCost.toFixed(2)
          : suggestedCost.toFixed(6);
      }
    }

    var isMaterial = selectedItemMeta.is_material === 1 || selectedItemMeta.material_id > 0;
    contentUomEl.disabled = isMaterial;
    setCostMode();

    if (profileNameEl) {
      profileNameEl.value = selectedItemMeta.profile_name || selectedItemMeta.item_name || '';
    }
    if (profileBrandEl) {
      profileBrandEl.value = selectedItemMeta.profile_brand || '';
    }
    if (profileDescriptionEl) {
      profileDescriptionEl.value = selectedItemMeta.profile_description || '';
    }
    if (profileExpiredEl) {
      profileExpiredEl.value = selectedItemMeta.profile_expired_date || '';
    }
    if (profileKeyEl) {
      profileKeyEl.value = selectedItemMeta.profile_key || '';
    }

    itemIdEl.value = String(id);
    itemSearchEl.value = label;
    itemPreviewEl.innerHTML = '';
    itemHintEl.textContent = 'Item terpilih: ' + label + (selectedItemMeta.suggested_price_source ? ' | harga saran tersedia' : '');
  });

  function clearProfileKeyForManualProfileChange() {
    if (profileKeyEl && profileKeyEl.value) {
      profileKeyEl.value = '';
    }
  }

  [profileNameEl, profileBrandEl, profileDescriptionEl, profileExpiredEl, buyUomEl, contentUomEl, ratioEl].forEach(function (el) {
    if (!el) {
      return;
    }
    el.addEventListener('input', clearProfileKeyForManualProfileChange);
    el.addEventListener('change', clearProfileKeyForManualProfileChange);
  });

  function showAlert(type, msg) {
    alertArea.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">'
      + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
  }

  function uiConfirm(message, options) {
    if (window.FinanceUI && typeof window.FinanceUI.confirm === 'function') {
      return window.FinanceUI.confirm(message, options || {});
    }
    if (window.FinanceUI && typeof window.FinanceUI.alert === 'function') {
      return window.FinanceUI.alert('Modal konfirmasi tidak tersedia. Muat ulang halaman lalu coba lagi.', { title: 'UI Belum Siap' })
        .then(function () { return false; });
    }
    return Promise.resolve(false);
  }

  saveBtnEl.addEventListener('click', function () {
    if (saveBtnEl.disabled) {
      return;
    }

    var snapshotMonth = snapshotEl ? String(snapshotEl.value || '') : '';
    var movementDate = monthStartDate(snapshotMonth);
    var selectedMaterialId = selectedItemMeta ? Number(selectedItemMeta.material_id || 0) : 0;
    var selectedIsMaterial = selectedItemMeta ? (Number(selectedItemMeta.is_material || 0) === 1 || selectedMaterialId > 0) : false;

    var payload = {
      stock_scope: stockScope,
      stock_domain: selectedIsMaterial ? 'MATERIAL' : 'ITEM',
      snapshot_month: snapshotMonth,
      movement_date: movementDate,
      item_id: Number(document.getElementById('item_id').value || 0),
      material_id: selectedMaterialId,
      division_id: Number((divisionEl && divisionEl.value) || 0),
      destination_type: (destinationEl && destinationEl.value) || 'OTHER',
      buy_uom_id: Number(document.getElementById('buy_uom_id').value || 0),
      content_uom_id: Number(document.getElementById('content_uom_id').value || 0),
      opening_qty_buy: Number(document.getElementById('opening_qty_buy').value || 0),
      opening_qty_content: Number(document.getElementById('opening_qty_content').value || 0),
      opening_avg_cost_per_content: 0,
      profile_key: profileKeyEl ? (profileKeyEl.value || null) : null,
      profile_name: document.getElementById('profile_name').value || null,
      profile_brand: document.getElementById('profile_brand').value || null,
      profile_description: document.getElementById('profile_description').value || null,
      profile_expired_date: (profileExpiredEl && profileExpiredEl.value) ? profileExpiredEl.value : null,
      profile_content_per_buy: Number(document.getElementById('profile_content_per_buy').value || 1),
      replace_mode: Number(document.getElementById('replace_mode').value || 1),
      notes: document.getElementById('notes').value || null
    };

    var rawCostInput = Number(document.getElementById('opening_avg_cost_per_content').value || 0);
    var ratioValue = Number(payload.profile_content_per_buy || 1);
    if (stockScope === 'WAREHOUSE') {
      payload.opening_avg_cost_per_content = ratioValue > 0 ? (rawCostInput / ratioValue) : rawCostInput;
    } else {
      payload.opening_avg_cost_per_content = rawCostInput;
    }

    if (!payload.item_id || !payload.buy_uom_id || !payload.content_uom_id || !payload.snapshot_month) {
      showAlert('warning', 'Field wajib belum lengkap.');
      return;
    }
    if (payload.stock_scope === 'DIVISION' && !payload.division_id) {
      showAlert('warning', 'Pilih divisi dulu untuk opening DIVISION.');
      return;
    }

    setSavingState(true);
    requestOpeningStepUp('POST', payload)
    .then(function (proof) {
      if (!proof) return null;
      payload.step_up_proof = proof;
      return postStockOpeningJson(storeUrl, payload);
    })
    .then(function (res) {
      if (!res) return;
      showAlert('success', 'Opening berhasil diposting. Halaman akan dimuat ulang.');
      window.setTimeout(function () { window.location.reload(); }, 700);
    })
    .catch(function (err) {
      showAlert('danger', err.message || 'Gagal simpan opening.');
      setSavingState(false);
    });
  });

  Array.prototype.forEach.call(document.querySelectorAll('.btn-void-opening'), function (button) {
    button.addEventListener('click', function () {
      if (button.disabled) {
        return;
      }
      button.blur();
      uiConfirm('VOID opening ini akan menghapus snapshot opening, rollback lot awal, dan rebuild histori stok. Lanjutkan?', {
        title: 'Void Opening',
        okText: 'Void',
        cancelText: 'Batal'
      })
      .then(function (ok) {
        if (!ok) {
          return null;
        }

        if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') {
          window.FinanceUI.setButtonLoading(button, 'Void...');
        } else {
          button.disabled = true;
        }

        return requestOpeningStepUp('VOID', {
          stock_scope: stockScope,
          snapshot_id: Number(button.getAttribute('data-id') || 0)
        });
      })
      .then(function (proof) {
        if (!proof) return null;
        return postStockOpeningJson(voidUrlBase + '/' + encodeURIComponent(button.getAttribute('data-id') || ''), {
          stock_scope: stockScope,
          step_up_proof: proof
        });
      })
      .then(function (res) {
        if (!res) return;
        showAlert('success', 'Opening berhasil di-void. Halaman akan dimuat ulang.');
        window.setTimeout(function () { window.location.reload(); }, 700);
      })
      .catch(function (err) {
        showAlert('danger', err.message || 'Gagal void opening.');
      })
      .finally(function () {
        if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
          window.FinanceUI.clearButtonLoading(button);
        } else {
          button.disabled = false;
        }
      });
    });
  });
})();
</script>
