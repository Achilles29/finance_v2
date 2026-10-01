<?php
$editMode = !empty($edit_mode);
$detailData = (array)($detail ?? []);
$detailOrder = (array)($detailData['order'] ?? []);
$detailLines = (array)($detailData['lines'] ?? []);
$editability = (array)($editability ?? []);
$editabilityData = (array)($editability['data'] ?? []);
$editModeType = (string)($editabilityData['mode'] ?? ($editMode ? 'full' : 'create'));
$isPaymentOnlyEdit = $editMode && $editModeType === 'payment_only';
$purchaseMutationCsrfToken = (string)($purchase_mutation_csrf_token ?? '');

if (!function_exists('finance_po_usage_purpose_from_notes')) {
  function finance_po_usage_purpose_from_notes($notes)
  {
    $notes = (string)$notes;
    if (preg_match('/Tujuan\s+pemakaian\s*:\s*(Kebutuhan\s+Operasional|Persediaan\s+Produksi|Operasional|Bahan\s+Baku)/i', $notes, $matches)) {
      $label = strtoupper(str_replace(' ', '_', trim((string)$matches[1])));
      return in_array($label, ['KEBUTUHAN_OPERASIONAL', 'OPERASIONAL'], true) ? 'OPERASIONAL' : 'BAHAN_BAKU';
    }
    return 'BAHAN_BAKU';
  }
}

$storeUrl = $editMode
  ? site_url('purchase/order/update/' . (int)($detailOrder['id'] ?? 0))
  : site_url('purchase/order/store');
$catalogUrl = site_url('purchase/catalog/search');
$catalogUrlFallback = base_url('index.php/purchase/catalog/search');
$syncCatalogUrl = site_url('purchase/catalog/sync-core');
$syncSetupUrl = site_url('purchase/setup/sync-core');
$syncAllUrl = site_url('purchase/setup/sync-core-all');

$initialRequestDate = $editMode ? (string)($detailOrder['request_date'] ?? date('Y-m-d')) : date('Y-m-d');
$initialPurchaseTypeId = (int)($detailOrder['purchase_type_id'] ?? 0);
$initialDestinationType = strtoupper(trim((string)($detailOrder['destination_type'] ?? '')));
$initialDestinationDivisionId = (int)($detailOrder['destination_division_id'] ?? 0);
$initialVendorId = (int)($detailOrder['vendor_id'] ?? 0);
$initialPaymentAccountId = (int)($detailOrder['payment_account_id'] ?? 0);
$initialStatus = strtoupper(trim((string)($detailOrder['status'] ?? 'DRAFT')));
$initialNotes = (string)($detailOrder['notes'] ?? '');
$initialExternalRefNo = (string)($detailOrder['external_ref_no'] ?? '');
$purchaseTypeRows = array_values((array)($purchase_types ?? []));
$initialPurchaseTypeExists = false;
foreach ($purchaseTypeRows as $ptRow) {
  if ((int)($ptRow['id'] ?? 0) === $initialPurchaseTypeId) {
    $initialPurchaseTypeExists = true;
    break;
  }
}

$initialLines = [];
foreach ($detailLines as $ln) {
  $lineKind = strtoupper(trim((string)($ln['line_kind'] ?? 'ITEM')));
  $itemName = trim((string)($ln['snapshot_item_name'] ?? ''));
  $materialName = trim((string)($ln['snapshot_material_name'] ?? ''));
  $catalogName = $itemName !== '' ? $itemName : $materialName;

  $lineBrand = trim((string)($ln['brand_name'] ?? ''));
  if ($lineBrand === '') {
    $lineBrand = trim((string)($ln['snapshot_brand_name'] ?? ''));
  }

  $lineDesc = trim((string)($ln['line_description'] ?? ''));
  if ($lineDesc === '') {
    $lineDesc = trim((string)($ln['snapshot_line_description'] ?? ''));
  }

  $contentUomId = (int)($ln['content_uom_id'] ?? 0);
  $initialLines[] = [
    'line_kind' => $lineKind,
    'item_id' => !empty($ln['item_id']) ? (int)$ln['item_id'] : null,
    'material_id' => !empty($ln['material_id']) ? (int)$ln['material_id'] : null,
    'item_name' => $itemName,
    'material_name' => $materialName,
    'catalog_name' => $catalogName,
    'brand_name' => $lineBrand,
    'line_description' => $lineDesc,
    'buy_uom_id' => !empty($ln['buy_uom_id']) ? (int)$ln['buy_uom_id'] : null,
    'content_uom_id' => $contentUomId > 0 ? $contentUomId : null,
    'buy_uom_code' => (string)($ln['snapshot_buy_uom_code'] ?? ''),
    'content_uom_code' => (string)($ln['snapshot_content_uom_code'] ?? ''),
    'material_content_uom_id' => $lineKind === 'MATERIAL' && $contentUomId > 0 ? $contentUomId : null,
    'material_content_uom_code' => $lineKind === 'MATERIAL' ? (string)($ln['snapshot_content_uom_code'] ?? '') : '',
    'content_per_buy' => (float)($ln['content_per_buy'] ?? 0),
    'conversion_factor_to_content' => (float)($ln['content_per_buy'] ?? ($ln['conversion_factor_to_content'] ?? 0)),
    'unit_price' => (float)($ln['unit_price'] ?? 0),
    'qty_buy' => (float)($ln['qty_buy'] ?? 0),
    'discount_percent' => (float)($ln['discount_percent'] ?? 0),
    'tax_percent' => (float)($ln['tax_percent'] ?? 0),
    'expired_date' => (string)($ln['expired_date'] ?? ($ln['snapshot_expired_date'] ?? '')),
    'expiry_policy' => (string)($ln['expiry_policy'] ?? (!empty($ln['required_expiry_date']) || !empty($ln['expired_date']) ? 'EXACT_DATE' : 'NONE')),
    'required_expiry_date' => (string)($ln['required_expiry_date'] ?? ($ln['expired_date'] ?? ($ln['snapshot_expired_date'] ?? ''))),
    'min_remaining_days' => !empty($ln['min_remaining_days']) ? (int)$ln['min_remaining_days'] : null,
    'usage_purpose' => (string)($ln['usage_purpose'] ?? finance_po_usage_purpose_from_notes((string)($ln['notes'] ?? ''))),
    'notes' => (string)($ln['notes'] ?? ''),
  ];
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-shopping-cart-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <?php if ($editMode): ?>
      <small class="text-muted d-block">Edit data Purchase Order (header dan line).</small>
      <div class="small mt-1">PO: <strong><?php echo html_escape((string)($detailOrder['po_no'] ?? '-')); ?></strong> | Status saat ini: <strong><?php echo html_escape($initialStatus); ?></strong></div>
    <?php endif; ?>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo site_url('purchase-orders'); ?>" class="btn btn-outline-secondary">Kembali ke Purchase Orders</a>
  </div>
</div>

<div id="alert-area"></div>

<style>
  .catalog-preview-anchor {
    position: relative;
    z-index: 20;
  }
  .catalog-preview-dropdown {
    position: absolute;
    left: 0;
    top: 0;
    width: 280px;
    z-index: 1040;
    background: #fff;
    border: 1px solid #d7dce1;
    border-radius: 8px;
    max-height: 280px;
    overflow: auto;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
  }
  .catalog-preview-item {
    display: block;
    width: 100%;
    border: 0;
    border-bottom: 1px solid #ebeef2;
    background: #fff;
    text-align: left;
    padding: 10px 12px;
    font-family: inherit;
    cursor: pointer;
  }
  .catalog-preview-item:hover {
    background: #f7fafc;
  }
  .catalog-preview-item:last-child {
    border-bottom: 0;
  }
  .catalog-preview-name {
    font-size: 14px;
    line-height: 1.3;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 2px;
  }
  .catalog-preview-meta {
    font-size: 12px;
    line-height: 1.35;
    color: #6b7280;
  }
  .po-header-card,
  .po-catalog-card {
    overflow: visible !important;
  }
  .po-vendor-inline { display: flex; gap: .5rem; align-items: stretch; }
  .po-vendor-select { flex: 1 1 auto; min-width: 0; }
  .po-vendor-add { flex: 0 0 auto; white-space: nowrap; }
  .po-vendor-search-wrap { flex: 1 1 auto; min-width: 0; position: relative; }
  .po-vendor-dropdown { display: none; position: absolute; left: 0; right: 0; top: 100%; z-index: 50; background: #fff; border: 1px solid #dee2e6; border-radius: .375rem; box-shadow: 0 4px 12px rgba(0,0,0,.12); max-height: 260px; overflow-y: auto; }
  .po-vendor-item { padding: .5rem .75rem; cursor: pointer; border-bottom: 1px solid #f1e4db; font-size: .9rem; }
  .po-vendor-item:last-child { border-bottom: 0; }
  .po-vendor-item:hover { background: #fff7f1; }
  #po-form .form-select {
    background-image: none !important;
    padding-right: .85rem;
    appearance: auto;
  }
  .po-status-select {
    background-image: none !important;
    min-height: calc(1.5em + .75rem + 2px);
  }
  .po-status-note {
    display: block;
    margin-top: .35rem;
  }
  #po-line-table .line-usage-col { min-width: 140px; }
  .po-suggestion-panel {
    margin-top: .45rem;
    padding: .45rem .55rem;
    border: 1px solid #eadfc8;
    border-radius: .65rem;
    background: #fff9ef;
  }
  .po-suggestion-list {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
  }
  .po-suggestion-chip {
    margin: 0;
    width: 220px;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    text-align: left;
    line-height: 1.25;
  }
  .po-suggestion-chip strong,
  .po-suggestion-chip small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .po-catalog-draft-modal .modal-content {
    border: 0;
    border-radius: 20px;
    overflow: hidden;
    background: #fffdf9;
    box-shadow: 0 20px 60px rgba(74, 28, 38, 0.18);
    position: relative;
    pointer-events: auto;
  }
  .po-catalog-draft-modal {
    z-index: 1065;
  }
  .po-catalog-draft-modal .modal-dialog {
    max-width: 880px;
    position: relative;
    z-index: 1;
  }
  .po-catalog-draft-head {
    background: linear-gradient(135deg, #6a1f2f, #9c3248);
    color: #fff;
    padding: 1.1rem 1.2rem 1rem;
  }
  .po-catalog-draft-title {
    font-size: 1.08rem;
    font-weight: 700;
    line-height: 1.35;
  }
  .po-catalog-draft-subtitle {
    font-size: .82rem;
    opacity: .82;
  }
  .po-catalog-draft-body {
    background: linear-gradient(180deg, #fffdf9 0%, #fff8f1 100%);
    padding: 1.15rem 1.2rem 1rem;
  }
  .po-catalog-draft-info {
    border: 1px solid #eadfc8;
    border-radius: 14px;
    background: #fffaf2;
    color: #6c5246;
    padding: .8rem .95rem;
    line-height: 1.45;
  }
  .po-catalog-draft-note {
    font-size: .82rem;
    color: #8b5e55;
    background: #fff3e6;
    border: 1px solid #efd6c1;
    border-radius: 999px;
    padding: .45rem .8rem;
    white-space: nowrap;
  }
  .po-catalog-draft-fields {
    border: 1px solid #efdfcf;
    border-radius: 16px;
    background: #ffffff;
    padding: 1rem;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.7);
  }
  .po-catalog-draft-fields .form-label {
    font-size: .76rem;
    font-weight: 700;
    color: #7b4d43;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  .po-catalog-draft-modal .modal-footer {
    background: #fff7ef;
    border-top: 1px solid #efdfcf;
    padding: .95rem 1.2rem 1.15rem;
    position: relative;
    z-index: 3;
    pointer-events: auto;
  }
  .po-catalog-draft-modal .modal-footer .btn {
    position: relative;
    z-index: 4;
    pointer-events: auto;
  }
  .po-review-modal .modal-content {
    border: 0;
    border-radius: 18px;
    overflow: hidden;
    background: #ffffff;
  }
  .po-review-modal {
    z-index: 1065;
  }
  .po-review-modal .modal-dialog {
    max-width: 860px;
  }
  .po-review-modal .modal-body {
    background: #ffffff;
    padding: 1.1rem 1.2rem 1rem;
  }
  .po-review-head {
    background: linear-gradient(135deg, #6a1f2f, #9c3248);
    color: #fff;
    padding: 1rem 1.15rem;
  }
  .po-review-head h5 {
    line-height: 1.25;
  }
  .po-review-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: .75rem;
    margin-bottom: 1rem;
    align-items: stretch;
  }
  .po-review-card {
    border: 1px solid #eadfc8;
    border-radius: 14px;
    background: #fffaf5;
    padding: .8rem .9rem;
    min-width: 0;
    min-height: 72px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .po-review-card-label {
    display: block;
    font-size: .74rem;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #8b5e55;
    margin-bottom: .2rem;
  }
  .po-review-card > div {
    min-width: 0;
    line-height: 1.35;
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
  }
  .po-review-list {
    margin: 0;
    padding-left: 1.1rem;
    max-height: 240px;
    overflow: auto;
  }
  .po-review-list li + li {
    margin-top: .35rem;
  }
  .po-review-modal .modal-footer {
    gap: .75rem;
    flex-wrap: wrap;
    justify-content: flex-end;
    padding: .95rem 1.2rem 1.15rem;
    background: #fffdfb;
  }
  .po-review-modal .modal-footer .btn {
    min-width: 190px;
  }
  @media (max-width: 767.98px) {
    .po-review-modal .modal-body,
    .po-review-modal .modal-footer {
      padding-left: 1rem;
      padding-right: 1rem;
    }
    .po-review-modal .modal-footer .btn {
      width: 100%;
      min-width: 0;
    }
  }
  #po-line-table {
    min-width: 1280px;
  }
  #po-line-table th,
  #po-line-table td {
    white-space: nowrap;
    vertical-align: middle;
  }
  #po-line-table .line-name { min-width: 220px; }
  #po-line-table .line-material-name { min-width: 180px; }
  #po-line-table .line-brand { min-width: 120px; }
  #po-line-table .line-desc { min-width: 220px; }
  #po-line-table .line-usage { min-width: 140px; }
  #po-line-table .line-buy-uom,
  #po-line-table .line-content-uom { min-width: 120px; }
  #po-line-table .line-qty,
  #po-line-table .line-content { min-width: 140px; }
  #po-line-table .line-price { min-width: 150px; }
</style>

<div class="card mb-3 po-header-card">
  <div class="card-body">
    <h6 class="mb-3">Header Purchase Order</h6>
    <form id="po-form" class="row g-3" autocomplete="off">
      <div class="col-lg-2 col-md-4">
        <label class="form-label">Tanggal PO</label>
      <input type="date" class="form-control" id="request_date" value="<?php echo html_escape($initialRequestDate); ?>" required <?php echo $isPaymentOnlyEdit ? 'disabled' : ''; ?>>
      </div>
      <div class="col-lg-3 col-md-4">
        <label class="form-label">Purchase Type</label>
        <select id="purchase_type_id" class="form-select" required <?php echo $isPaymentOnlyEdit ? 'disabled' : ''; ?>>
          <option value="">Pilih Purchase Type...</option>
          <?php if ($editMode && $initialPurchaseTypeId > 0 && !$initialPurchaseTypeExists): ?>
            <option value="<?php echo $initialPurchaseTypeId; ?>" selected>
              <?php echo html_escape((string)($detailOrder['purchase_type_name'] ?? ('TYPE #' . $initialPurchaseTypeId))); ?> (non-aktif)
            </option>
          <?php endif; ?>
          <?php foreach ($purchaseTypeRows as $t): ?>
            <option
              value="<?php echo (int)$t['id']; ?>"
              <?php echo ((int)$t['id'] === $initialPurchaseTypeId) ? 'selected' : ''; ?>
              data-default-destination="<?php echo html_escape((string)($t['default_destination'] ?? '')); ?>"
              data-destination-behavior="<?php echo html_escape((string)($t['destination_behavior'] ?? 'REQUIRED')); ?>"
              data-affects-inventory="<?php echo (int)($t['affects_inventory'] ?? 0); ?>"
            >
              <?php echo html_escape((string)$t['type_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-3 col-md-6">
        <label class="form-label">Vendor</label>
        <div class="po-vendor-inline">
          <div class="po-vendor-search-wrap">
            <input type="text" id="vendor_display_input" class="form-control" placeholder="Cari vendor..." autocomplete="off" <?php echo $isPaymentOnlyEdit ? 'readonly' : ''; ?>>
            <div id="vendor_search_result" class="po-vendor-dropdown"></div>
          </div>
          <!-- Hidden select as data store (value + QuickVendor compatibility) -->
          <select id="vendor_id" style="display:none;" <?php echo $isPaymentOnlyEdit ? 'disabled' : ''; ?>>
            <option value=""></option>
            <?php foreach (($vendors ?? []) as $v): ?>
              <option value="<?php echo (int)$v['id']; ?>" <?php echo ((int)$v['id'] === $initialVendorId) ? 'selected' : ''; ?>><?php echo html_escape((string)$v['vendor_code'] . ' - ' . (string)$v['vendor_name']); ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-outline-primary po-vendor-add" id="btn-po-add-vendor" <?php echo $isPaymentOnlyEdit ? 'disabled' : ''; ?>>+ Vendor</button>
        </div>
        <small class="text-muted d-block mt-1">Ketik nama/kode untuk cari vendor. Jika belum ada, klik + Vendor.</small>
      </div>
      <div class="col-lg-3 col-md-6">
        <label class="form-label">Metode Pembayaran (Rekening)</label>
        <select id="payment_account_id" class="form-select">
          <option value="">(opsional)</option>
          <?php foreach (($payment_accounts ?? []) as $a): ?>
            <?php
              $bank = trim((string)($a['bank_name'] ?? ''));
              $accNo = trim((string)($a['account_no'] ?? ''));
              $baseLabel = (string)($a['account_code'] ?? '') . ' - ' . (string)($a['account_name'] ?? '');
              if ($bank !== '') {
                $baseLabel .= ' | ' . $bank;
              }
              if ($accNo !== '') {
                $baseLabel .= ' (' . $accNo . ')';
              }
            ?>
            <option value="<?php echo (int)$a['id']; ?>" <?php echo ((int)$a['id'] === $initialPaymentAccountId) ? 'selected' : ''; ?>><?php echo html_escape($baseLabel); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-2 col-md-4">
        <label class="form-label">Status</label>
        <select id="status" class="form-select po-status-select" <?php echo (!$editMode || $isPaymentOnlyEdit) ? 'disabled' : ''; ?>>
          <?php foreach (($status_options ?? ['DRAFT']) as $st): ?>
            <?php $stValue = strtoupper((string)$st); ?>
            <option value="<?php echo html_escape($stValue); ?>" <?php echo $stValue === $initialStatus ? 'selected' : ''; ?>><?php echo html_escape($stValue); ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($editMode): ?>
          <small class="text-muted po-status-note">Perubahan status tetap wajib review buyer sebelum simpan.</small>
        <?php else: ?>
          <small class="text-muted po-status-note">Create awal disimpan sebagai DRAFT.</small>
        <?php endif; ?>
      </div>
      <div class="col-lg-3 col-md-4">
        <label class="form-label">Tujuan</label>
        <select id="destination_type" class="form-select" disabled>
          <option value="">(opsional)</option>
          <option value="GUDANG" <?php echo $initialDestinationType === 'GUDANG' ? 'selected' : ''; ?>>GUDANG</option>
          <option value="BAR" <?php echo $initialDestinationType === 'BAR' ? 'selected' : ''; ?>>BAR</option>
          <option value="KITCHEN" <?php echo $initialDestinationType === 'KITCHEN' ? 'selected' : ''; ?>>KITCHEN</option>
          <option value="ROASTERY" <?php echo $initialDestinationType === 'ROASTERY' ? 'selected' : ''; ?>>ROASTERY</option>
          <option value="BAR_EVENT" <?php echo $initialDestinationType === 'BAR_EVENT' ? 'selected' : ''; ?>>BAR_EVENT</option>
          <option value="KITCHEN_EVENT" <?php echo $initialDestinationType === 'KITCHEN_EVENT' ? 'selected' : ''; ?>>KITCHEN_EVENT</option>
          <option value="ROASTERY_EVENT" <?php echo $initialDestinationType === 'ROASTERY_EVENT' ? 'selected' : ''; ?>>ROASTERY_EVENT</option>
          <option value="OFFICE" <?php echo $initialDestinationType === 'OFFICE' ? 'selected' : ''; ?>>OFFICE</option>
          <option value="OTHER" <?php echo $initialDestinationType === 'OTHER' ? 'selected' : ''; ?>>OTHER</option>
        </select>
      </div>
      <div class="col-lg-3 col-md-4">
        <label class="form-label">Divisi Tujuan</label>
        <input type="text" id="destination_division_label" class="form-control" value="-" readonly>
        <input type="hidden" id="destination_division_id" value="<?php echo $initialDestinationDivisionId > 0 ? (int)$initialDestinationDivisionId : ''; ?>">
      </div>
      <div class="col-12">
        <label class="form-label">Notes</label>
        <input type="text" id="po_notes" class="form-control" value="<?php echo html_escape($initialNotes); ?>" <?php echo $isPaymentOnlyEdit ? 'disabled' : ''; ?>>
      </div>
    </form>
  </div>
</div>

<div class="card mb-3 po-catalog-card">
  <div class="card-body">
    <h6 class="mb-3">Tambah Line dari Catalog</h6>
    <div class="row g-2">
      <div class="col-md-8 catalog-preview-anchor">
        <label class="form-label">Cari Catalog (Ajax Preview)</label>
        <input type="text" id="catalog_keyword" class="form-control" placeholder="Ketik nama item/material/merk/keterangan, lalu pilih dari preview..." <?php echo $isPaymentOnlyEdit ? 'disabled' : ''; ?>>
        <div id="catalog-preview-wrap" class="catalog-preview-dropdown" style="display:none;">
          <div id="catalog-preview-list"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="mb-0">Line Purchase Order</h6>
      <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-line" <?php echo $isPaymentOnlyEdit ? 'disabled' : ''; ?>>Tambah Baris</button>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-striped" id="po-line-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Nama</th>
            <th>Stok sekarang<br><small>Tujuan / Gudang • satuan isi</small></th>
            <th>Merk</th>
            <th>Keterangan</th>
            <th class="line-usage-col">Pemakaian</th>
            <th class="line-inventory-col">UOM Beli</th>
            <th class="line-inventory-col">UOM Isi</th>
            <th class="text-end line-inventory-col">Qty Beli</th>
            <th class="text-end line-inventory-col">Isi/Beli</th>
            <th class="line-inventory-col">Expired (opsional)</th>
            <th class="text-end">Harga</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="13" class="text-center text-muted py-3">Belum ada line.</td></tr>
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-end mt-3 gap-2">
      <button class="btn btn-primary" type="button" id="btn-save-po"><?php echo $isPaymentOnlyEdit ? 'Update Metode Pembayaran' : ($editMode ? 'Update Purchase Order' : 'Simpan Purchase Order'); ?></button>
    </div>
  </div>
</div>

<?php $this->load->view('purchase/_vendor_quick_create_modal'); ?>

<div class="modal fade po-catalog-draft-modal" id="catalogDraftModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content shadow-lg">
      <div class="po-catalog-draft-head">
        <div class="small opacity-75">Detail catalog terpilih</div>
        <div class="po-catalog-draft-title" id="catalog-draft-title">-</div>
        <div class="po-catalog-draft-subtitle" id="catalog-draft-subtitle">Belum ada catalog dipilih.</div>
      </div>
      <div class="modal-body po-catalog-draft-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
          <div class="po-catalog-draft-info mb-0 flex-grow-1">
            Klik hasil pencarian hanya membuka modal detail ini. Buyer bisa cek dan isi detail dulu sebelum baris benar-benar masuk ke tabel line PO.
          </div>
          <div class="po-catalog-draft-note" id="catalog-draft-target-note">Baris belum ditambahkan ke PO.</div>
        </div>
        <div class="po-catalog-draft-fields">
          <div class="row g-3">
            <div class="col-md-2">
              <label class="form-label mb-1">Jenis Line</label>
              <input type="text" class="form-control" id="catalog-draft-kind" readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label mb-1">Pemakaian</label>
              <select class="form-select" id="catalog-draft-usage-purpose"></select>
            </div>
            <div class="col-md-3">
              <label class="form-label mb-1">Merk</label>
              <input type="text" class="form-control" id="catalog-draft-brand">
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1">Keterangan</label>
              <input type="text" class="form-control" id="catalog-draft-desc">
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">UOM Beli</label>
              <select class="form-select" id="catalog-draft-buy-uom"></select>
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">UOM Isi</label>
              <select class="form-select" id="catalog-draft-content-uom"></select>
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">Qty Beli</label>
              <input type="number" class="form-control text-end" id="catalog-draft-qty" min="0" step="0.01">
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">Isi/Beli</label>
              <input type="number" class="form-control text-end" id="catalog-draft-content" min="0" step="0.01">
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">Expired</label>
              <input type="date" class="form-control" id="catalog-draft-expired">
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">Harga</label>
              <input type="number" class="form-control text-end" id="catalog-draft-price" min="0" step="0.01">
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="btn-catalog-draft-cancel" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-catalog-draft-apply">Tambahkan ke Line PO</button>
      </div>
    </div>
  </div>
</div>

<?php $this->load->view('procurement/_manual_stock_toolbar'); ?>
<div class="modal fade po-review-modal" id="poReviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content shadow-lg">
      <div class="po-review-head">
        <h5 class="mb-1">Review Purchase Order Sebelum Simpan</h5>
        <div class="small opacity-75">Buyer wajib cek status, vendor, merk, satuan, dan harga sebelum ubah status PO.</div>
      </div>
      <div class="modal-body">
        <div id="po-review-body"></div>
        <div id="po-review-alert" class="mt-3"></div>
        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" value="1" id="po-review-confirm">
          <label class="form-check-label" for="po-review-confirm">
            Saya sudah review line, merk, UOM, harga, dan yakin status PO ini benar.
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kembali Cek Lagi</button>
        <button type="button" class="btn btn-primary" id="po-review-submit">Simpan Setelah Review</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var editMode = <?php echo $editMode ? 'true' : 'false'; ?>;
  var suggestionEnabled = !editMode;
  var orderId = <?php echo (int)($detailOrder['id'] ?? 0); ?>;
  var initialStatus = <?php echo json_encode($initialStatus); ?>;
  var initialExternalRefNo = <?php echo json_encode($initialExternalRefNo); ?>;
  var isDivisionRequestPo = editMode && /^DREQ/i.test(String(initialExternalRefNo || '').trim());
  var skipEditReviewModal = isPaymentOnlyEditMode;
  var detailUrl = <?php echo json_encode(site_url('purchase-orders/detail/' . (int)($detailOrder['id'] ?? 0))); ?>;
  var initialLines = <?php echo json_encode(array_values($initialLines)); ?>;
  var divisions = <?php echo json_encode(array_values((array)($divisions ?? []))); ?>;
  var uoms = <?php echo json_encode(array_values((array)($uoms ?? []))); ?>;
  var catalogUrl = <?php echo json_encode($catalogUrl); ?>;
  var catalogUrlFallback = <?php echo json_encode($catalogUrlFallback); ?>;
  var storeUrl = <?php echo json_encode($storeUrl); ?>;
  var catalogPreviewWrap = document.getElementById('catalog-preview-wrap');
  var catalogPreviewList = document.getElementById('catalog-preview-list');
  var catalogKeywordEl = document.getElementById('catalog_keyword');
  var catalogPreviewAnchorEl = document.querySelector('.catalog-preview-anchor');
  var catalogDraftModalEl = document.getElementById('catalogDraftModal');
  var catalogDraftTitleEl = document.getElementById('catalog-draft-title');
  var catalogDraftSubtitleEl = document.getElementById('catalog-draft-subtitle');
  var catalogDraftTargetNoteEl = document.getElementById('catalog-draft-target-note');
  var catalogDraftKindEl = document.getElementById('catalog-draft-kind');
  var catalogDraftUsagePurposeEl = document.getElementById('catalog-draft-usage-purpose');
  var catalogDraftBrandEl = document.getElementById('catalog-draft-brand');
  var catalogDraftDescEl = document.getElementById('catalog-draft-desc');
  var catalogDraftBuyUomEl = document.getElementById('catalog-draft-buy-uom');
  var catalogDraftContentUomEl = document.getElementById('catalog-draft-content-uom');
  var catalogDraftQtyEl = document.getElementById('catalog-draft-qty');
  var catalogDraftContentEl = document.getElementById('catalog-draft-content');
  var catalogDraftExpiredEl = document.getElementById('catalog-draft-expired');
  var catalogDraftPriceEl = document.getElementById('catalog-draft-price');
  var catalogDraftCancelBtn = document.getElementById('btn-catalog-draft-cancel');
  var catalogDraftApplyBtn = document.getElementById('btn-catalog-draft-apply');
  var lineTbody = document.querySelector('#po-line-table tbody');
  function stockControlInput() { return {lines:lines,division:document.getElementById('destination_division_id').value,destination:document.getElementById('destination_type').value}; }
  var stockControl = window.ProcurementCurrentStock.create({
    url: <?php echo json_encode(site_url('purchase-orders/stock-preview')); ?>,
    csrfHeader:'X-Purchase-Mutation-Csrf', csrf:<?php echo json_encode($purchaseMutationCsrfToken); ?>,
    payload: function () { return {header:{division_id:Number(document.getElementById('destination_division_id').value),
      destination_type:document.getElementById('destination_type').value || 'WAREHOUSE'}, lines:lines.map(function (line) {
        return {item_id:line.item_id,material_id:line.material_id,content_uom_id:line.content_uom_id,
          usage_purpose:line.usage_purpose || line.default_usage_purpose || 'OPERASIONAL',
          profile_name:line.item_name || line.material_name || line.name || line.custom_name || 'Barang',
          qty_buy:line.qty_buy,content_per_buy:line.content_per_buy};
      })}; }
  });
  document.addEventListener('change', function () { queueMicrotask(function () { stockControl.changed(); }); });
  document.addEventListener('input', function () { queueMicrotask(function () { stockControl.changed(); }); });
  var alertArea = document.getElementById('alert-area');
  var reviewModalEl = document.getElementById('poReviewModal');
  var reviewBodyEl = document.getElementById('po-review-body');
  var reviewAlertEl = document.getElementById('po-review-alert');
  var reviewConfirmEl = document.getElementById('po-review-confirm');
  var reviewSubmitBtn = document.getElementById('po-review-submit');

  function moveModalToBody(modalEl) {
    if (!modalEl || !document.body || modalEl.parentNode === document.body) {
      return modalEl;
    }
    document.body.appendChild(modalEl);
    return modalEl;
  }

  catalogDraftModalEl = moveModalToBody(catalogDraftModalEl);
  reviewModalEl = moveModalToBody(reviewModalEl);

  var catalogDraftModal = (catalogDraftModalEl && window.bootstrap && window.bootstrap.Modal) ? window.bootstrap.Modal.getOrCreateInstance(catalogDraftModalEl) : null;
  var reviewModal = (reviewModalEl && window.bootstrap && window.bootstrap.Modal) ? window.bootstrap.Modal.getOrCreateInstance(reviewModalEl) : null;
  var lines = [];
  var isInventoryType = true;
  var isPaymentOnlyEditMode = <?php echo $isPaymentOnlyEdit ? 'true' : 'false'; ?>;
  var divisionCodeToId = {};
  var uomById = {};
  var previewTimer = null;
  var previewAbortController = null;
  var activeLineIdx = 0;
  var pendingSubmitPayload = null;
  var catalogDraftLine = null;
  var catalogDraftMeta = null;

  function showReviewModal() {
    if (!reviewModalEl) {
      return;
    }
    hideCatalogPreview();
    if (reviewModal) {
      reviewModal.show();
      return;
    }
    reviewModalEl.style.display = 'block';
    reviewModalEl.classList.add('show');
    reviewModalEl.removeAttribute('aria-hidden');
    reviewModalEl.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');
    if (!document.querySelector('[data-po-review-backdrop="1"]')) {
      var backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop fade show';
      backdrop.setAttribute('data-po-review-backdrop', '1');
      document.body.appendChild(backdrop);
    }
  }

  function hideReviewModal(clearPending) {
    if (!reviewModalEl) {
      return;
    }
    if (clearPending !== false) {
      pendingSubmitPayload = null;
    }
    if (reviewModal) {
      reviewModal.hide();
      return;
    }
    reviewModalEl.classList.remove('show');
    reviewModalEl.style.display = 'none';
    reviewModalEl.setAttribute('aria-hidden', 'true');
    reviewModalEl.removeAttribute('aria-modal');
    document.body.classList.remove('modal-open');
    var backdrop = document.querySelector('[data-po-review-backdrop="1"]');
    if (backdrop && backdrop.parentNode) {
      backdrop.parentNode.removeChild(backdrop);
    }
  }

  function showReviewAlert(message) {
    if (!reviewAlertEl) {
      return;
    }
    if (!message) {
      reviewAlertEl.innerHTML = '';
      return;
    }
    reviewAlertEl.innerHTML = '<div class="alert alert-warning py-2 mb-0">' + esc(message) + '</div>';
  }

  function showCatalogDraftModal() {
    if (!catalogDraftModalEl) {
      return;
    }
    if (catalogPreviewAnchorEl) {
      catalogPreviewAnchorEl.style.visibility = 'hidden';
      catalogPreviewAnchorEl.style.pointerEvents = 'none';
    }
    if (catalogDraftModal) {
      catalogDraftModal.show();
      return;
    }
    catalogDraftModalEl.style.display = 'block';
    catalogDraftModalEl.classList.add('show');
    catalogDraftModalEl.removeAttribute('aria-hidden');
    catalogDraftModalEl.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');
    if (!document.querySelector('[data-po-catalog-backdrop="1"]')) {
      var backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop fade show';
      backdrop.setAttribute('data-po-catalog-backdrop', '1');
      document.body.appendChild(backdrop);
    }
  }

  function hideCatalogDraftModal() {
    if (!catalogDraftModalEl) {
      return;
    }
    if (catalogPreviewAnchorEl) {
      catalogPreviewAnchorEl.style.visibility = '';
      catalogPreviewAnchorEl.style.pointerEvents = '';
    }
    if (catalogDraftModal) {
      catalogDraftModal.hide();
      return;
    }
    catalogDraftModalEl.classList.remove('show');
    catalogDraftModalEl.style.display = 'none';
    catalogDraftModalEl.setAttribute('aria-hidden', 'true');
    catalogDraftModalEl.removeAttribute('aria-modal');
    document.body.classList.remove('modal-open');
    var backdrop = document.querySelector('[data-po-catalog-backdrop="1"]');
    if (backdrop && backdrop.parentNode) {
      backdrop.parentNode.removeChild(backdrop);
    }
  }

  function isCatalogDraftActive() {
    return !!catalogDraftLine
      || !!(catalogDraftModalEl && catalogDraftModalEl.classList.contains('show'));
  }

  function hideCatalogPreview() {
    if (previewTimer) {
      window.clearTimeout(previewTimer);
      previewTimer = null;
    }
    if (previewAbortController) {
      previewAbortController.abort();
      previewAbortController = null;
    }
    if (catalogPreviewWrap) {
      catalogPreviewWrap.style.display = 'none';
    }
    if (catalogPreviewList) {
      catalogPreviewList.innerHTML = '';
    }
  }

  if (catalogPreviewWrap && catalogPreviewWrap.parentNode !== document.body) {
    document.body.appendChild(catalogPreviewWrap);
  }

  divisions.forEach(function (d) {
    var id = Number(d && d.id ? d.id : 0);
    var code = String((d && d.code ? d.code : d && d.name ? d.name : '') || '').trim().toUpperCase();
    if (id > 0 && code) {
      divisionCodeToId[code] = id;
    }
  });

  uoms.forEach(function (u) {
    var id = Number(u && u.id ? u.id : 0);
    if (id > 0) {
      uomById[id] = {
        id: id,
        code: String(u && u.code ? u.code : ''),
        name: String(u && u.name ? u.name : '')
      };
    }
  });

  function esc(v) {
    var d = document.createElement('div');
    d.textContent = v == null ? '' : String(v);
    return d.innerHTML;
  }

  function num(v) {
    var n = Number(v || 0);
    return Number.isFinite(n) ? n : 0;
  }

  function dateVal(v) {
    var t = String(v || '').trim();
    return /^\d{4}-\d{2}-\d{2}$/.test(t) ? t : '';
  }

  function normalizeStatus(v) {
    return String(v || 'DRAFT').trim().toUpperCase() || 'DRAFT';
  }

  function lineDisplayName(line) {
    return String(line && (line.catalog_name || line.item_name || line.material_name || '') || '').trim();
  }

  function lineHasMasterLink(line) {
    return num(line && line.item_id) > 0 || num(line && line.material_id) > 0;
  }

  function lineIsInventoryTracked(line) {
    var kind = String(line && line.line_kind || 'ITEM').trim().toUpperCase();
    return ['ITEM', 'MATERIAL'].indexOf(kind) !== -1;
  }

  function lineRequiresMasterLink(line) {
    return isInventoryType && lineIsInventoryTracked(line);
  }

  function lineUsesUsagePurpose(line) {
    return lineIsInventoryTracked(line);
  }

  function normalizeUsagePurpose(value) {
    return String(value || '').trim().toUpperCase() === 'OPERASIONAL'
      ? 'OPERASIONAL'
      : 'BAHAN_BAKU';
  }

  function lineHasProductionMaterial(line) {
    var lineKind = String(line && line.line_kind || '').trim().toUpperCase();
    return lineKind === 'MATERIAL' || num(line && line.material_id) > 0;
  }

  function defaultUsagePurposeForLine(line) {
    return isInventoryType && lineHasProductionMaterial(line)
      ? 'BAHAN_BAKU'
      : 'OPERASIONAL';
  }

  function applyLineUsagePurposeDefault(line, preserveManualChoice) {
    if (!line || (preserveManualChoice !== false && line.usage_purpose_is_manual)) {
      return;
    }

    var usagePurpose = defaultUsagePurposeForLine(line);
    line.default_usage_purpose = usagePurpose;
    line.usage_purpose = usagePurpose;
    line.usage_purpose_is_manual = false;
  }

  function buildUsagePurposeOptions(selectedValue) {
    var selected = normalizeUsagePurpose(selectedValue);
    return '<option value="BAHAN_BAKU"' + (selected === 'BAHAN_BAKU' ? ' selected' : '') + '>Persediaan Produksi</option>'
      + '<option value="OPERASIONAL"' + (selected === 'OPERASIONAL' ? ' selected' : '') + '>Kebutuhan Operasional</option>';
  }

  function lineSuggestionKeyword(line) {
    var name = lineDisplayName(line);
    if (name !== '') {
      return name;
    }
    return String(line && line.line_description || '').trim();
  }

  function normalizeToken(v) {
    return String(v || '').trim().toUpperCase();
  }

  function suggestionScore(line, suggestion) {
    var score = 0;
    var lineName = normalizeToken(lineDisplayName(line));
    var lineBrand = normalizeToken(line && line.brand_name);
    var lineDesc = normalizeToken(line && line.line_description);
    var suggestionName = normalizeToken(suggestion && (suggestion.catalog_name || suggestion.item_name || suggestion.material_name));
    var suggestionBrand = normalizeToken(suggestion && suggestion.brand_name);
    var suggestionDesc = normalizeToken(suggestion && suggestion.line_description);

    if (lineBrand !== '') {
      if (suggestionBrand === lineBrand) {
        score += 1200;
      } else if (suggestionBrand.indexOf(lineBrand) >= 0 || lineBrand.indexOf(suggestionBrand) >= 0) {
        score += 700;
      }
    } else if (suggestionBrand !== '') {
      score += 160;
    }

    if (lineName !== '') {
      if (suggestionName === lineName) {
        score += 500;
      } else if (suggestionName.indexOf(lineName) >= 0 || lineName.indexOf(suggestionName) >= 0) {
        score += 260;
      }
    }

    if (lineDesc !== '') {
      if (suggestionDesc === lineDesc) {
        score += 180;
      } else if (suggestionDesc.indexOf(lineDesc) >= 0 || lineDesc.indexOf(suggestionDesc) >= 0) {
        score += 90;
      }
    }

    if (lineHasMasterLink(line)) {
      if (num(line.item_id) > 0 && num(suggestion && suggestion.item_id) === num(line.item_id)) {
        score += 800;
      }
      if (num(line.material_id) > 0 && num(suggestion && suggestion.material_id) === num(line.material_id)) {
        score += 800;
      }
    }

    return score;
  }

  function alertMsg(type, msg) {
    alertArea.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">'
      + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
  }

  function syncVendorOption(vendor) {
    if (!window.FinanceQuickVendor) {
      return;
    }
    var sel = document.getElementById('vendor_id');
    window.FinanceQuickVendor.upsertSelectOption(sel, vendor);
    var disp = document.getElementById('vendor_display_input');
    if (disp) disp.value = window.FinanceQuickVendor.vendorLabel(vendor) || '';
    if (sel) sel.dispatchEvent(new Event('change'));
  }

  function buildUomOptions(selectedId) {
    var sid = Number(selectedId || 0);
    var html = '<option value="">-</option>';
    Object.keys(uomById).forEach(function (k) {
      var id = Number(k);
      var row = uomById[id] || {};
      var code = String(row.code || '').trim();
      var name = String(row.name || '').trim();
      var label = code || name;
      html += '<option value="' + id + '"' + (id === sid ? ' selected' : '') + '>' + esc(label) + '</option>';
    });
    return html;
  }

  function firstUomId() {
    var ids = Object.keys(uomById)
      .map(function (k) { return Number(k); })
      .filter(function (id) { return id > 0; })
      .sort(function (a, b) { return a - b; });
    return ids.length ? ids[0] : null;
  }

  function applyLineTypeDefaults(line) {
    if (!line || isInventoryType) {
      return;
    }

    var defaultUom = firstUomId();
    if (!num(line.buy_uom_id) && defaultUom) {
      line.buy_uom_id = defaultUom;
      line.buy_uom_code = uomById[defaultUom] ? String(uomById[defaultUom].code || '') : '';
    }
    if (!num(line.content_uom_id) && defaultUom) {
      line.content_uom_id = defaultUom;
      line.content_uom_code = uomById[defaultUom] ? String(uomById[defaultUom].code || '') : '';
    }
    if (num(line.qty_buy) <= 0) {
      line.qty_buy = 1;
    }
    if (num(line.content_per_buy) <= 0) {
      line.content_per_buy = 1;
      line.conversion_factor_to_content = 1;
    }
    line.expired_date = '';
  }

  function buildCatalogLine(it, existing) {
    var lineKind = String(it.line_kind || 'ITEM').toUpperCase();
    var current = Object.assign(createEmptyLine(), existing || {});
    var selectedName = it.catalog_name || it.item_name || it.material_name || '';
    var brandName = String(it.brand_name || it.profile_brand || it.snapshot_brand_name || '').trim();
    var lineDescription = String(it.line_description || it.profile_description || it.snapshot_line_description || '').trim();
    var next = {
      source_catalog_id: it.catalog_id || null,
      source_profile_key: it.profile_key || '',
      source_unit_price: num(it.last_unit_price || it.standard_price || 0),
      line_kind: lineKind,
      item_id: it.item_id || null,
      material_id: it.material_id || null,
      selected_display_name: selectedName,
      item_name: it.item_name || '',
      material_name: it.material_name || '',
      catalog_name: selectedName,
      brand_name: brandName,
      line_description: lineDescription,
      buy_uom_id: it.buy_uom_id || null,
      content_uom_id: it.content_uom_id || null,
      buy_uom_code: it.buy_uom_code || '',
      content_uom_code: it.content_uom_code || '',
      material_content_uom_id: lineKind === 'MATERIAL' ? (it.content_uom_id || null) : null,
      material_content_uom_code: lineKind === 'MATERIAL' ? (it.content_uom_code || '') : '',
      content_per_buy: num(it.content_per_buy || 0),
      conversion_factor_to_content: num(it.content_per_buy || it.conversion_factor_to_content || 0),
      unit_price: num(it.last_unit_price || it.standard_price || 0),
      qty_buy: num(current.qty_buy || 0),
      discount_percent: num(current.discount_percent || 0),
      tax_percent: num(current.tax_percent || 0),
      expired_date: dateVal(it.expired_date || current.expired_date || ''),
      expiry_policy: String(it.expiry_policy || current.expiry_policy || ((it.expired_date || current.expired_date) ? 'EXACT_DATE' : 'NONE')),
      required_expiry_date: dateVal(it.required_expiry_date || it.expired_date || current.required_expiry_date || current.expired_date || ''),
      min_remaining_days: num(it.min_remaining_days || current.min_remaining_days || 0) || null,
      default_usage_purpose: normalizeUsagePurpose(current.default_usage_purpose || current.usage_purpose),
      usage_purpose: normalizeUsagePurpose(current.usage_purpose || current.default_usage_purpose),
      usage_purpose_is_manual: !!current.usage_purpose_is_manual,
      notes: current.notes || '',
      catalog_suggestions: [],
      suggestion_loading: false,
      suggestion_query: ''
    };
    if (num(next.qty_buy) <= 0) {
      next.qty_buy = 1;
    }
    if (num(next.content_per_buy) <= 0) {
      next.content_per_buy = 1;
      next.conversion_factor_to_content = 1;
    }
    applyLineTypeDefaults(next);
    applyLineUsagePurposeDefault(next);
    return next;
  }

  function applyLineModeByPurchaseType() {
    var table = document.getElementById('po-line-table');
    if (!table) {
      return;
    }

    if (isInventoryType) {
      table.classList.remove('line-mode-noninventory');
    } else {
      table.classList.add('line-mode-noninventory');
    }

    lines.forEach(function (line) {
      applyLineTypeDefaults(line);
      applyLineUsagePurposeDefault(line);
    });
    if (catalogDraftLine) {
      applyLineTypeDefaults(catalogDraftLine);
      applyLineUsagePurposeDefault(catalogDraftLine);
      renderCatalogDraft();
    }
    refreshLines();
  }

  function createEmptyLine() {
    return {
      source_catalog_id: null,
      source_profile_key: '',
      source_unit_price: 0,
      line_kind: 'ITEM',
      item_id: null,
      material_id: null,
      selected_display_name: '',
      item_name: '',
      material_name: '',
      catalog_name: '',
      brand_name: '',
      line_description: '',
      buy_uom_id: null,
      content_uom_id: null,
      buy_uom_code: '',
      content_uom_code: '',
      material_content_uom_id: null,
      material_content_uom_code: '',
      content_per_buy: 0,
      conversion_factor_to_content: 0,
      unit_price: 0,
      qty_buy: 0,
      discount_percent: 0,
      tax_percent: 0,
      expired_date: '',
      expiry_policy: 'NONE',
      required_expiry_date: '',
      min_remaining_days: null,
      default_usage_purpose: 'OPERASIONAL',
      usage_purpose: 'OPERASIONAL',
      usage_purpose_is_manual: false,
      notes: '',
      catalog_suggestions: [],
      suggestion_loading: false,
      suggestion_query: ''
    };
  }

  function addEmptyLine(focusNewRow) {
    lines.push(createEmptyLine());
    applyLineTypeDefaults(lines[lines.length - 1]);
    refreshLines();
    activeLineIdx = lines.length - 1;
    if (focusNewRow) {
      window.setTimeout(function () {
        var tr = lineTbody.querySelector('tr[data-idx="' + activeLineIdx + '"]');
        var nameInput = tr ? tr.querySelector('.line-name') : null;
        if (nameInput) {
          nameInput.focus();
        }
      }, 0);
    }
  }

  function isLineEffectivelyEmpty(line) {
    return !num(line.item_id)
      && !num(line.material_id)
      && String(line.catalog_name || '').trim() === ''
      && String(line.brand_name || '').trim() === ''
      && String(line.line_description || '').trim() === ''
      && num(line.unit_price) <= 0;
  }

  function findFirstEmptyLineIndex() {
    for (var i = 0; i < lines.length; i++) {
      if (isLineEffectivelyEmpty(lines[i])) {
        return i;
      }
    }
    return -1;
  }

  function applyCatalogToLine(idx, it) {
    lines[idx] = buildCatalogLine(it, lines[idx] || createEmptyLine());
  }

  function resolveCatalogTargetIndex() {
    if (activeLineIdx >= 0 && lines[activeLineIdx] && isLineEffectivelyEmpty(lines[activeLineIdx])) {
      return activeLineIdx;
    }
    return findFirstEmptyLineIndex();
  }

  function clearCatalogDraft(keepKeyword) {
    catalogDraftLine = null;
    catalogDraftMeta = null;
    hideCatalogDraftModal();
    if (!keepKeyword && catalogKeywordEl) {
      catalogKeywordEl.value = '';
    }
  }

  function renderCatalogDraft() {
    if (!catalogDraftModalEl || !catalogDraftLine) {
      if (catalogDraftModalEl) {
        hideCatalogDraftModal();
      }
      return;
    }

    var kind = String(catalogDraftLine.line_kind || 'ITEM').toUpperCase();
    var materialLocked = kind === 'MATERIAL';
    var lineInputsDisabled = isPaymentOnlyEditMode;
    var expiryDisabled = !isInventoryType || isPaymentOnlyEditMode;
    var masterText = [];
    if (num(catalogDraftLine.item_id) > 0) {
      masterText.push('Item ID #' + num(catalogDraftLine.item_id));
    }
    if (num(catalogDraftLine.material_id) > 0) {
      masterText.push('Material ID #' + num(catalogDraftLine.material_id));
    }
    if (masterText.length === 0) {
      masterText.push('Belum terhubung ke master');
    }

    var targetIdx = resolveCatalogTargetIndex();
    var targetNote = targetIdx >= 0
      ? 'Siap mengisi baris kosong #' + (targetIdx + 1) + ' setelah dikonfirmasi.'
      : 'Semua baris terisi. Konfirmasi akan menambah baris baru di bawah.';

    if (catalogDraftTitleEl) {
      catalogDraftTitleEl.textContent = lineDisplayName(catalogDraftLine) || '-';
    }
    if (catalogDraftSubtitleEl) {
      catalogDraftSubtitleEl.textContent = masterText.join(' | ');
    }
    if (catalogDraftTargetNoteEl) {
      catalogDraftTargetNoteEl.textContent = targetNote;
    }
    if (catalogDraftKindEl) {
      catalogDraftKindEl.value = kind;
    }
    if (catalogDraftUsagePurposeEl) {
      catalogDraftUsagePurposeEl.innerHTML = buildUsagePurposeOptions(catalogDraftLine.usage_purpose || catalogDraftLine.default_usage_purpose);
      catalogDraftUsagePurposeEl.disabled = lineInputsDisabled;
    }
    if (catalogDraftBrandEl) {
      catalogDraftBrandEl.value = String(catalogDraftLine.brand_name || '');
    }
    if (catalogDraftDescEl) {
      catalogDraftDescEl.value = String(catalogDraftLine.line_description || '');
    }
    if (catalogDraftBuyUomEl) {
      catalogDraftBuyUomEl.innerHTML = buildUomOptions(num(catalogDraftLine.buy_uom_id || 0));
      catalogDraftBuyUomEl.disabled = lineInputsDisabled;
    }
    if (catalogDraftContentUomEl) {
      var contentId = materialLocked
        ? num(catalogDraftLine.material_content_uom_id || catalogDraftLine.content_uom_id || 0)
        : num(catalogDraftLine.content_uom_id || 0);
      catalogDraftContentUomEl.innerHTML = buildUomOptions(contentId);
      catalogDraftContentUomEl.disabled = lineInputsDisabled || materialLocked;
    }
    if (catalogDraftQtyEl) {
      catalogDraftQtyEl.value = num(catalogDraftLine.qty_buy || 0).toFixed(2);
      catalogDraftQtyEl.disabled = lineInputsDisabled;
    }
    if (catalogDraftContentEl) {
      catalogDraftContentEl.value = num(catalogDraftLine.content_per_buy || 0).toFixed(2);
      catalogDraftContentEl.disabled = lineInputsDisabled;
    }
    if (catalogDraftExpiredEl) {
      catalogDraftExpiredEl.value = dateVal(catalogDraftLine.expired_date || '');
      catalogDraftExpiredEl.disabled = expiryDisabled;
    }
    if (catalogDraftPriceEl) {
      catalogDraftPriceEl.value = num(catalogDraftLine.unit_price || 0).toFixed(2);
    }

    showCatalogDraftModal();
  }

  function startCatalogDraft(it) {
    hideCatalogPreview();
    catalogDraftLine = buildCatalogLine(it, createEmptyLine());
    catalogDraftMeta = {
      selected_name: lineDisplayName(catalogDraftLine) || (it.catalog_name || it.item_name || it.material_name || '')
    };
    if (catalogKeywordEl) {
      catalogKeywordEl.value = catalogDraftMeta.selected_name || '';
    }
    renderCatalogDraft();
    if (catalogDraftBrandEl) {
      window.setTimeout(function () {
        catalogDraftBrandEl.focus();
        catalogDraftBrandEl.select();
      }, 0);
    }
  }

  function applyCatalogDraftToLines() {
    if (!catalogDraftLine) {
      alertMsg('warning', 'Detail catalog belum siap ditambahkan. Pilih ulang catalog terlebih dahulu.');
      return;
    }
    if (!lineTbody) {
      alertMsg('danger', 'Tabel line PO tidak ditemukan. Refresh halaman lalu coba lagi.');
      return;
    }
    if (lineRequiresMasterLink(catalogDraftLine) && !lineHasMasterLink(catalogDraftLine)) {
      alertMsg('warning', 'Catalog ini belum terhubung ke item/bahan master yang valid. Pilih ulang dari hasil pencarian catalog.');
      return;
    }

    try {
      var targetIdx = resolveCatalogTargetIndex();
      var nextLine = Object.assign(createEmptyLine(), catalogDraftLine);
      applyLineTypeDefaults(nextLine);
      applyLineUsagePurposeDefault(nextLine);
      if (targetIdx >= 0) {
        lines[targetIdx] = nextLine;
      } else {
        lines.push(nextLine);
        targetIdx = lines.length - 1;
      }

      activeLineIdx = targetIdx;
      refreshLines();
      clearCatalogDraft(false);
      hideCatalogPreview();
      alertMsg('success', 'Catalog berhasil dimasukkan ke baris #' + (targetIdx + 1) + '.');

      window.setTimeout(function () {
        var tr = lineTbody.querySelector('tr[data-idx="' + targetIdx + '"]');
        if (tr && typeof tr.scrollIntoView === 'function') {
          tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        var qtyInput = tr ? tr.querySelector('.line-qty') : null;
        if (qtyInput) {
          qtyInput.focus();
          qtyInput.select();
        }
      }, 0);
    } catch (error) {
      alertMsg('danger', 'Gagal menambahkan catalog ke line PO: ' + (error && error.message ? error.message : 'error tidak dikenal'));
    }
  }

  function renderSuggestionButtons(idx, suggestions) {
    return suggestions.map(function (it, suggestionIdx) {
      var name = String(it.catalog_name || it.item_name || it.material_name || '-');
      var brand = String(it.brand_name || '-');
      var desc = String(it.line_description || '-');
      var meta = [brand, desc].filter(function (part) {
        return String(part || '').trim() !== '' && String(part || '-') !== '-';
      }).join(' | ');
      if (meta === '') {
        meta = 'Tanpa merk/keterangan';
      }
      return '<button type="button" class="btn btn-sm btn-outline-secondary po-suggestion-chip btn-apply-line-suggestion" data-suggestion-idx="' + suggestionIdx + '">'
        + '<strong>' + esc(name) + '</strong>'
        + '<small>' + esc(meta) + '</small>'
        + '</button>';
    }).join('');
  }

  function fetchLineSuggestionsWithUrl(idx, urlBase, retryOnFallback) {
    if (!suggestionEnabled || !lines[idx]) {
      return;
    }

    var line = lines[idx];
    var keyword = lineSuggestionKeyword(line);
    if (keyword.length < 2) {
      line.catalog_suggestions = [];
      line.suggestion_loading = false;
      line.suggestion_query = '';
      return;
    }

    var vendorId = document.getElementById('vendor_id').value || '';
    var queryKey = [keyword.toUpperCase(), String(vendorId), String(line.line_kind || '').toUpperCase()].join('|');
    line.suggestion_loading = true;
    line.suggestion_query = queryKey;
    refreshLines();

    var url = urlBase + '?q=' + encodeURIComponent(keyword)
      + '&vendor_id=' + encodeURIComponent(vendorId)
      + '&limit=5';

    fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(parseJsonResponse)
      .then(function (res) {
        if (!lines[idx] || lines[idx].suggestion_query !== queryKey) {
          return;
        }
        if (res.status >= 400 || !res.json || !res.json.ok) {
          throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal mengambil saran catalog');
        }

        var kind = String(line.line_kind || '').toUpperCase();
        var items = Array.isArray(res.json.items) ? res.json.items : [];
        var filtered = items.filter(function (it) {
          var itemKind = String(it.line_kind || 'ITEM').toUpperCase();
          return kind === '' || itemKind === kind;
        }).map(function (it, listIdx) {
          return {
            row: it,
            score: suggestionScore(line, it),
            idx: listIdx
          };
        }).sort(function (a, b) {
          if (b.score !== a.score) {
            return b.score - a.score;
          }
          return a.idx - b.idx;
        }).slice(0, 3).map(function (entry) {
          return entry.row;
        });

        lines[idx].catalog_suggestions = filtered;
        lines[idx].suggestion_loading = false;
        refreshLines();
      })
      .catch(function () {
        if (!lines[idx] || lines[idx].suggestion_query !== queryKey) {
          return;
        }
        if (retryOnFallback) {
          fetchLineSuggestionsWithUrl(idx, catalogUrlFallback, false);
          return;
        }
        lines[idx].catalog_suggestions = [];
        lines[idx].suggestion_loading = false;
        refreshLines();
      });
  }

  function refreshLineSuggestion(idx, force) {
    if (!suggestionEnabled || !lines[idx]) {
      return;
    }

    var line = lines[idx];
    var keyword = lineSuggestionKeyword(line);
    var vendorId = document.getElementById('vendor_id').value || '';
    var queryKey = [keyword.toUpperCase(), String(vendorId), String(line.line_kind || '').toUpperCase()].join('|');
    if (!force && line.suggestion_query === queryKey) {
      return;
    }

    fetchLineSuggestionsWithUrl(idx, catalogUrl, true);
  }

  function refreshAllLineSuggestions(force) {
    if (!suggestionEnabled) {
      return;
    }
    lines.forEach(function (line, idx) {
      if (!line) {
        return;
      }
      var keyword = lineSuggestionKeyword(line);
      if (keyword.length >= 2) {
        refreshLineSuggestion(idx, force);
      }
    });
  }

  function buildReviewPayload(header, submitLines) {
    var warnings = [];
    submitLines.forEach(function (line, idx) {
      var lineName = lineDisplayName(line) || ('Baris #' + (idx + 1));
      if (String(line.brand_name || '').trim() === '') {
        warnings.push('Baris #' + (idx + 1) + ' ' + lineName + ': merk masih kosong.');
      }
      if (num(line.unit_price) <= 0) {
        warnings.push('Baris #' + (idx + 1) + ' ' + lineName + ': harga masih 0.');
      }
      if (lineRequiresMasterLink(line) && !lineHasMasterLink(line)) {
        warnings.push('Baris #' + (idx + 1) + ' ' + lineName + ': belum terhubung ke item/bahan dari catalog.');
      }
    });

    return {
      header: header,
      lines: submitLines,
      warnings: warnings
    };
  }

  function openReviewModal(reviewData, btnSave) {
    if (!reviewBodyEl || !reviewConfirmEl || !reviewSubmitBtn) {
      return false;
    }

    reviewConfirmEl.checked = false;
    showReviewAlert('');
    pendingSubmitPayload = {
      header: Object.assign({}, reviewData.header || {}),
      lines: reviewData.lines || [],
      button: btnSave
    };

    var statusTarget = normalizeStatus((reviewData.header || {}).status || initialStatus);
    var vendorDisplayEl = document.getElementById('vendor_display_input');
    var vendorLabel = vendorDisplayEl ? String(vendorDisplayEl.value || '-') : '-';
    var destinationType = String((reviewData.header || {}).destination_type || '-');
    var warningList = (reviewData.warnings || []).map(function (row) {
      return '<li>' + esc(row) + '</li>';
    }).join('');

    reviewBodyEl.innerHTML = ''
      + '<div class="po-review-summary">'
      + '  <div class="po-review-card"><span class="po-review-card-label">Status</span><div><strong>' + esc(initialStatus) + '</strong> -> <strong>' + esc(statusTarget) + '</strong></div></div>'
      + '  <div class="po-review-card"><span class="po-review-card-label">Vendor</span><div>' + esc(vendorLabel) + '</div></div>'
      + '  <div class="po-review-card"><span class="po-review-card-label">Tujuan</span><div>' + esc(destinationType || '-') + '</div></div>'
      + '  <div class="po-review-card"><span class="po-review-card-label">Line Ditinjau</span><div>' + esc(String((reviewData.lines || []).length)) + ' baris</div></div>'
      + '</div>'
      + ((reviewData.warnings || []).length
        ? '<div class="alert alert-warning mb-0"><div class="fw-semibold mb-2">Poin yang masih perlu perhatian buyer</div><ul class="po-review-list">' + warningList + '</ul></div>'
        : '<div class="alert alert-success mb-0">Tidak ada warning besar yang terdeteksi. Buyer tetap wajib cek detail sebelum simpan.</div>');

    showReviewModal();
    return true;
  }

  async function submitPurchase(header, submitLines, btnSave) {
    var origHtml = btnSave.innerHTML;
    btnSave.disabled = true;
    btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Menyimpan...';
    var stockInput = JSON.stringify(stockControlInput());
    if (!isPaymentOnlyEditMode && !(await stockControl.confirmBeforeSave())) { btnSave.disabled=false; btnSave.innerHTML=origHtml; return; }
    if (stockInput !== JSON.stringify(stockControlInput())) { alertMsg('warning','Barang atau tujuan berubah; periksa dan simpan ulang.'); btnSave.disabled=false; btnSave.innerHTML=origHtml; return; }
    fetch(storeUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Purchase-Mutation-CSRF': <?php echo json_encode($purchaseMutationCsrfToken); ?> },
      body: JSON.stringify({ header: header, lines: submitLines })
    })
    .then(parseJsonResponse)
    .then(function (res) {
      if (res.status >= 400 || !res.json || !res.json.ok) {
        throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal simpan PO');
      }
      var d = res.json.data || {};
      if (editMode) {
        alertMsg('success', 'PO berhasil diperbarui. No: ' + esc(d.po_no || '-') + ', line: ' + esc(d.line_count || 0));
        window.setTimeout(function () {
          window.location.href = detailUrl;
        }, 700);
      } else {
        alertMsg('success', 'PO berhasil disimpan. No: ' + esc(d.po_no || '-') + ', line: ' + esc(d.line_count || 0));
        lines = [];
        addEmptyLine(false);
      }
    })
    .catch(function (err) {
      alertMsg('danger', err.message || 'Gagal simpan PO.');
    })
    .finally(function () {
      btnSave.disabled = false;
      btnSave.innerHTML = origHtml;
    });
  }

  function parseJsonResponse(response) {
    return response.text().then(function (txt) {
      var parsed = null;
      try {
        parsed = JSON.parse(txt);
      } catch (e) {
        var firstBrace = (txt || '').indexOf('{');
        var lastBrace = (txt || '').lastIndexOf('}');
        if (firstBrace >= 0 && lastBrace > firstBrace) {
          try {
            parsed = JSON.parse((txt || '').slice(firstBrace, lastBrace + 1));
          } catch (ignored) {
            parsed = null;
          }
        }
      }

      if (parsed === null) {
        var plainText = (txt || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        var messageMatch = plainText.match(/Message:\s*([^\r\n]+)/i);
        var snippet = (messageMatch && messageMatch[1] ? messageMatch[1] : plainText).trim().slice(0, 160);
        throw new Error('Respons server bukan JSON valid (HTTP ' + response.status + '). ' + snippet);
      }

      return { status: response.status, json: parsed };
    });
  }

  function refreshLines() {
    if (!lines.length) {
      lineTbody.innerHTML = '<tr><td colspan="13" class="text-center text-muted py-3">Belum ada line.</td></tr>';
      stockControl.changed();
      return;
    }

    var html = [];
    lines.forEach(function (l, idx) {
      var name = l.catalog_name || l.item_name || l.material_name || '-';
      var kind = String(l.line_kind || '-').toUpperCase();
      var brand = (l.brand_name || '-');
      var desc = (l.line_description || '-');
      var buyUom = l.buy_uom_code || '-';
      var contentUom = l.content_uom_code || '-';
      var nameInput = (l.catalog_name || l.item_name || l.material_name || '');
      var buyUomId = Number(l.buy_uom_id || 0);
      var contentUomId = Number(l.content_uom_id || 0);
      var expiredDate = dateVal(l.expired_date);
      var materialLocked = (kind === 'MATERIAL');
      var lineInputsDisabled = isPaymentOnlyEditMode;
      var expiryDisabled = !isInventoryType || isPaymentOnlyEditMode;
      var usageHtml = lineUsesUsagePurpose(l)
        ? '<select class="form-select form-select-sm line-usage line-usage-purpose" title="Nilai awal mengikuti tipe belanja dan material katalog; tetap dapat diubah"' + (lineInputsDisabled ? ' disabled' : '') + '>' + buildUsagePurposeOptions(l.usage_purpose || l.default_usage_purpose) + '</select>'
        : '<div class="text-muted small">-</div>';
      var materialNameInput = (l.material_name || '');
      var showMaterialForm = materialLocked || (Number(l.material_id || 0) > 0);
      var suggestionHtml = '';
      if (suggestionEnabled) {
        if (l.suggestion_loading) {
          suggestionHtml = '<div class="po-suggestion-panel"><span class="small text-muted">Mencari saran catalog...</span></div>';
        } else if (Array.isArray(l.catalog_suggestions) && l.catalog_suggestions.length) {
          suggestionHtml = '<div class="po-suggestion-panel"><div class="small text-muted mb-1">Saran catalog terdekat</div>'
            + '<div class="po-suggestion-list">' + renderSuggestionButtons(idx, l.catalog_suggestions) + '</div>'
            + '</div>';
        }
      }
      if (materialLocked && Number(l.material_content_uom_id || 0) > 0) {
        contentUomId = Number(l.material_content_uom_id || 0);
      }
      html.push('<tr data-idx="' + idx + '">' +
        '<td>' + (idx + 1) + '</td>' +
        '<td><div class="d-flex gap-1 align-items-center"><input type="text" class="form-control form-control-sm line-name" value="' + esc(nameInput) + '"' + (lineInputsDisabled ? ' disabled' : '') + '>' +
        '<input type="text" class="form-control form-control-sm line-material-name" value="' + esc(materialNameInput) + '" placeholder="Profil Persediaan Produksi" readonly' + (showMaterialForm ? '' : ' style="display:none;"') + '></div>' + suggestionHtml + '</td>' +
        '<td data-live-stock-line="' + (idx + 1) + '">Menunggu cek stok…</td>' +
        '<td><input type="text" class="form-control form-control-sm line-brand" value="' + esc(brand === '-' ? '' : brand) + '"' + (lineInputsDisabled ? ' disabled' : '') + '></td>' +
        '<td><input type="text" class="form-control form-control-sm line-desc" value="' + esc(desc === '-' ? '' : desc) + '"' + (lineInputsDisabled ? ' disabled' : '') + '></td>' +
        '<td class="line-usage-col">' + usageHtml + '</td>' +
        '<td class="line-inventory-col"><select class="form-select form-select-sm line-buy-uom"' + (lineInputsDisabled ? ' disabled' : '') + '>' + buildUomOptions(buyUomId) + '</select></td>' +
        '<td class="line-inventory-col"><select class="form-select form-select-sm line-content-uom"' + ((materialLocked || lineInputsDisabled) ? ' disabled' : '') + '>' + buildUomOptions(contentUomId) + '</select></td>' +
        '<td class="line-inventory-col"><input type="number" class="form-control form-control-sm line-qty text-end" min="0" step="0.01" value="' + num(l.qty_buy || 0).toFixed(2) + '"' + (lineInputsDisabled ? ' disabled' : '') + '></td>' +
        '<td class="line-inventory-col"><input type="number" class="form-control form-control-sm line-content text-end" min="0" step="0.01" value="' + num(l.content_per_buy || 0).toFixed(2) + '"' + (lineInputsDisabled ? ' disabled' : '') + '></td>' +
        '<td class="line-inventory-col"><input type="date" class="form-control form-control-sm line-expired" value="' + esc(expiredDate) + '"' + (expiryDisabled ? ' disabled' : '') + '></td>' +
        '<td><input type="number" class="form-control form-control-sm line-price text-end" min="0" step="0.01" value="' + num(l.unit_price || l.last_unit_price || 0).toFixed(2) + '"' + (lineInputsDisabled ? ' disabled' : '') + '></td>' +
        '<td class="action-cell"><div class="d-flex gap-1 flex-nowrap justify-content-end"><button type="button" class="btn btn-sm btn-outline-danger action-icon-btn btn-remove-line" title="Hapus" aria-label="Hapus"' + (isPaymentOnlyEditMode ? ' disabled' : '') + '><i class="ri ri-delete-bin-line"></i></button></div></td>' +
      '</tr>');
    });

    lineTbody.innerHTML = html.join('');
    stockControl.changed();
  }

  function renderCatalogPreview(items) {
    if (isCatalogDraftActive()) {
      hideCatalogPreview();
      return;
    }

    window.requestAnimationFrame(positionCatalogPreview);

    if (!items.length) {
      catalogPreviewList.innerHTML = '<div class="p-2 text-muted">Data tidak ditemukan.</div>';
      catalogPreviewWrap.style.display = 'block';
      return;
    }

    var rows = [];
    items.forEach(function (it, idx) {
      var name = it.catalog_name || it.item_name || it.material_name || '-';
      var brandName = String(it.brand_name || it.profile_brand || it.snapshot_brand_name || '').trim();
      var lineDescription = String(it.line_description || it.profile_description || it.snapshot_line_description || '').trim();
      var profile = 'Merk: ' + (brandName || '-') + ' | Ket: ' + (lineDescription || '-');
      var buyUom = String(it.buy_uom_code || '-');
      var contentUom = String(it.content_uom_code || '-');
      var uom = buyUom === contentUom ? buyUom : (buyUom + ' -> ' + contentUom);
      rows.push('<button type="button" class="catalog-preview-item" data-idx="' + idx + '">' +
        '<div class="catalog-preview-name">' + esc(name) + '</div>' +
        '<div class="catalog-preview-meta">' + esc(profile + ' | ' + uom + ' | Harga: ' + num(it.last_unit_price || it.standard_price || 0).toFixed(2)) + '</div>' +
      '</button>');
    });
    catalogPreviewList.innerHTML = rows.join('');
    catalogPreviewWrap.style.display = 'block';

    catalogPreviewList.querySelectorAll('.catalog-preview-item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var idx = Number(btn.getAttribute('data-idx') || -1);
        if (idx >= 0 && items[idx]) {
          startCatalogDraft(items[idx]);
        }
      });
    });
  }

  function positionCatalogPreview() {
    if (!catalogPreviewWrap || !catalogKeywordEl) {
      return;
    }

    var rect = catalogKeywordEl.getBoundingClientRect();
    if (!rect || rect.width <= 0 || rect.height <= 0) {
      return;
    }

    var left = Math.max(8, Math.round(window.pageXOffset + rect.left));
    var top = Math.round(window.pageYOffset + rect.bottom + 4);
    var width = Math.max(280, Math.round(rect.width));

    catalogPreviewWrap.style.left = left + 'px';
    catalogPreviewWrap.style.top = top + 'px';
    catalogPreviewWrap.style.width = width + 'px';
  }

  function fetchCatalogPreviewWithUrl(urlBase, retryOnFallback) {
    if (isCatalogDraftActive()) {
      hideCatalogPreview();
      return;
    }

    var q = catalogKeywordEl.value || '';
    var vendorId = document.getElementById('vendor_id').value || '';

    if (q.trim().length < 2) {
      catalogPreviewWrap.style.display = 'none';
      catalogPreviewList.innerHTML = '';
      return;
    }

    var url = urlBase + '?q=' + encodeURIComponent(q)
      + '&vendor_id=' + encodeURIComponent(vendorId)
      + '&limit=15';

    if (previewAbortController) {
      previewAbortController.abort();
    }
    previewAbortController = new AbortController();

    fetch(url, { headers: { 'Accept': 'application/json' }, signal: previewAbortController.signal })
      .then(parseJsonResponse)
      .then(function (res) {
        if (isCatalogDraftActive()) {
          hideCatalogPreview();
          return;
        }
        if (res.status >= 400 || !res.json || !res.json.ok) {
          throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal cari catalog');
        }
        var items = Array.isArray(res.json.items) ? res.json.items : [];
        renderCatalogPreview(items);
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') {
          return;
        }
        if (retryOnFallback) {
          var pathname = String(window.location.pathname || '');
          var rootBase = pathname;
          if (rootBase.indexOf('/index.php/') >= 0) {
            rootBase = rootBase.split('/index.php/')[0];
          } else if (rootBase.indexOf('/purchase-orders') >= 0) {
            rootBase = rootBase.split('/purchase-orders')[0];
          } else if (rootBase.indexOf('/purchase') >= 0) {
            rootBase = rootBase.split('/purchase')[0];
          }
          if (rootBase.length > 1 && rootBase[rootBase.length - 1] === '/') {
            rootBase = rootBase.slice(0, -1);
          }

          var candidates = [catalogUrlFallback];
          if (rootBase) {
            candidates.push(rootBase + '/index.php/purchase/catalog/search');
            candidates.push(rootBase + '/purchase/catalog/search');
          }

          var tried = {};
          tried[urlBase] = true;
          for (var i = 0; i < candidates.length; i++) {
            var candidate = String(candidates[i] || '');
            if (candidate && !tried[candidate]) {
              fetchCatalogPreviewWithUrl(candidate, false);
              return;
            }
          }

          return;
        }
        catalogPreviewWrap.style.display = 'none';
        catalogPreviewList.innerHTML = '';
        alertMsg('danger', err.message || 'Gagal cari catalog.');
      });
  }

  function fetchCatalogPreview() {
    if (isCatalogDraftActive()) {
      hideCatalogPreview();
      return;
    }
    fetchCatalogPreviewWithUrl(catalogUrl, true);
  }

  catalogKeywordEl.addEventListener('input', function () {
    if (previewTimer) {
      window.clearTimeout(previewTimer);
    }
    previewTimer = window.setTimeout(fetchCatalogPreview, 250);
  });

  catalogKeywordEl.addEventListener('focus', function () {
    if (isCatalogDraftActive()) {
      hideCatalogPreview();
      return;
    }
    if ((catalogPreviewList.innerHTML || '').trim() === '') {
      return;
    }
    window.requestAnimationFrame(positionCatalogPreview);
    catalogPreviewWrap.style.display = 'block';
  });

  window.addEventListener('resize', positionCatalogPreview);
  window.addEventListener('scroll', function () {
    if (catalogPreviewWrap.style.display !== 'none') {
      positionCatalogPreview();
    }
  }, true);

  if (catalogDraftModalEl) {
    catalogDraftModalEl.addEventListener('input', function (e) {
      if (!catalogDraftLine) {
        return;
      }

      if (e.target === catalogDraftBrandEl) {
        catalogDraftLine.brand_name = e.target.value || '';
      }
      if (e.target === catalogDraftDescEl) {
        catalogDraftLine.line_description = e.target.value || '';
      }
      if (e.target === catalogDraftQtyEl) {
        catalogDraftLine.qty_buy = num(e.target.value);
      }
      if (e.target === catalogDraftContentEl) {
        catalogDraftLine.content_per_buy = num(e.target.value);
        catalogDraftLine.conversion_factor_to_content = num(e.target.value);
      }
      if (e.target === catalogDraftExpiredEl) {
        catalogDraftLine.expired_date = dateVal(e.target.value || '');
      }
      if (e.target === catalogDraftPriceEl) {
        catalogDraftLine.unit_price = num(e.target.value);
      }
    });

    catalogDraftModalEl.addEventListener('change', function (e) {
      if (!catalogDraftLine) {
        return;
      }

      if (e.target === catalogDraftBuyUomEl) {
        var buyId = num(e.target.value);
        catalogDraftLine.buy_uom_id = buyId > 0 ? buyId : null;
        catalogDraftLine.buy_uom_code = (buyId > 0 && uomById[buyId]) ? (uomById[buyId].code || '') : '';
      }

      if (e.target === catalogDraftContentUomEl) {
        if (String(catalogDraftLine.line_kind || '').toUpperCase() === 'MATERIAL') {
          var lockId = num(catalogDraftLine.material_content_uom_id || catalogDraftLine.content_uom_id || 0);
          if (lockId > 0) {
            e.target.value = String(lockId);
          }
          alertMsg('warning', 'UOM isi untuk MATERIAL harus mengikuti master material.');
          return;
        }
        var contentId = num(e.target.value);
        catalogDraftLine.content_uom_id = contentId > 0 ? contentId : null;
        catalogDraftLine.content_uom_code = (contentId > 0 && uomById[contentId]) ? (uomById[contentId].code || '') : '';
      }

      if (e.target === catalogDraftUsagePurposeEl) {
        catalogDraftLine.usage_purpose = normalizeUsagePurpose(e.target.value);
        catalogDraftLine.default_usage_purpose = catalogDraftLine.usage_purpose;
        catalogDraftLine.usage_purpose_is_manual = true;
      }
    });
  }

  if (catalogDraftCancelBtn) {
    catalogDraftCancelBtn.addEventListener('click', function () {
      clearCatalogDraft(true);
      if (catalogKeywordEl) {
        catalogKeywordEl.focus();
      }
    });
  }

  if (catalogDraftApplyBtn) {
    catalogDraftApplyBtn.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      applyCatalogDraftToLines();
    });
  }

  if (catalogDraftModalEl) {
    catalogDraftModalEl.addEventListener('click', function (event) {
      if (event.target === catalogDraftModalEl) {
        clearCatalogDraft(true);
      }
    });
    catalogDraftModalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (button) {
      button.addEventListener('click', function () {
        clearCatalogDraft(true);
      });
    });
  }

  lineTbody.addEventListener('click', function (e) {
    if (e.target.classList.contains('btn-apply-line-suggestion')) {
      var trSuggest = e.target.closest('tr');
      var idxSuggest = Number(trSuggest ? trSuggest.getAttribute('data-idx') : -1);
      var suggestionIdx = Number(e.target.getAttribute('data-suggestion-idx') || -1);
      if (idxSuggest >= 0 && lines[idxSuggest] && Array.isArray(lines[idxSuggest].catalog_suggestions) && lines[idxSuggest].catalog_suggestions[suggestionIdx]) {
        applyCatalogToLine(idxSuggest, lines[idxSuggest].catalog_suggestions[suggestionIdx]);
        refreshLines();
      }
      return;
    }
    if (!e.target.classList.contains('btn-remove-line')) return;
    var tr = e.target.closest('tr');
    var idx = Number(tr ? tr.getAttribute('data-idx') : -1);
    if (idx >= 0) {
      lines.splice(idx, 1);
      if (!lines.length) {
        addEmptyLine(false);
      } else if (activeLineIdx >= lines.length) {
        activeLineIdx = lines.length - 1;
      }
      refreshLines();
    }
  });

  lineTbody.addEventListener('input', function (e) {
    var tr = e.target.closest('tr');
    var idx = Number(tr ? tr.getAttribute('data-idx') : -1);
    if (idx < 0 || !lines[idx]) return;
    activeLineIdx = idx;

    if (e.target.classList.contains('line-name')) lines[idx].catalog_name = e.target.value || '';
    if (e.target.classList.contains('line-brand')) lines[idx].brand_name = e.target.value || '';
    if (e.target.classList.contains('line-desc')) lines[idx].line_description = e.target.value || '';
    if (e.target.classList.contains('line-qty')) lines[idx].qty_buy = num(e.target.value);
    if (e.target.classList.contains('line-content')) {
      lines[idx].content_per_buy = num(e.target.value);
      lines[idx].conversion_factor_to_content = num(e.target.value);
    }
    if (e.target.classList.contains('line-expired')) lines[idx].expired_date = dateVal(e.target.value || '');
    if (e.target.classList.contains('line-price')) lines[idx].unit_price = num(e.target.value);
  });

  lineTbody.addEventListener('focusin', function (e) {
    var tr = e.target.closest('tr');
    var idx = Number(tr ? tr.getAttribute('data-idx') : -1);
    if (idx >= 0) {
      activeLineIdx = idx;
    }
  });

  lineTbody.addEventListener('change', function (e) {
    var tr = e.target.closest('tr');
    var idx = Number(tr ? tr.getAttribute('data-idx') : -1);
    if (idx < 0 || !lines[idx]) return;

    if (e.target.classList.contains('line-name')) {
      var typedName = String(e.target.value || '').trim();
      var linkedName = String(lines[idx].selected_display_name || '').trim();
      if (
        linkedName !== '' &&
        typedName.toUpperCase() !== linkedName.toUpperCase()
      ) {
        lines[idx].catalog_name = typedName;
        lines[idx].source_catalog_id = null;
        lines[idx].source_profile_key = '';
        lines[idx].source_unit_price = 0;
        lines[idx].line_kind = 'ITEM';
        lines[idx].item_id = null;
        lines[idx].material_id = null;
        lines[idx].item_name = '';
        lines[idx].material_name = '';
        lines[idx].material_content_uom_id = null;
        lines[idx].material_content_uom_code = '';
        lines[idx].selected_display_name = '';
        applyLineUsagePurposeDefault(lines[idx]);

        if (num(lines[idx].qty_buy) <= 0) {
          lines[idx].qty_buy = 1;
        }
        if (num(lines[idx].content_per_buy) <= 0) {
          lines[idx].content_per_buy = 1;
          lines[idx].conversion_factor_to_content = 1;
        }

        refreshLines();
        var trUpdated = lineTbody.querySelector('tr[data-idx="' + idx + '"]');
        var nameInput = trUpdated ? trUpdated.querySelector('.line-name') : null;
        if (nameInput) {
          nameInput.focus();
          nameInput.value = typedName;
        }

        alertMsg('warning', 'Nama baris diubah manual. Link item/material sebelumnya dilepas agar tidak mismatch data.');
        refreshLineSuggestion(idx, true);
      }
      return;
    }

    if (e.target.classList.contains('line-brand')) {
      refreshLineSuggestion(idx, true);
      return;
    }

    if (e.target.classList.contains('line-buy-uom')) {
      if (isPaymentOnlyEditMode) {
        return;
      }
      var prevBuyId = num(lines[idx].buy_uom_id || 0);
      var buyId = num(e.target.value);
      if (prevBuyId > 0 && buyId > 0 && prevBuyId !== buyId) {
        e.target.value = String(prevBuyId);
        if (window.FinanceUI && typeof window.FinanceUI.confirm === 'function') {
          Promise.resolve(window.FinanceUI.confirm('Yakin merubah UOM beli? Perubahan ini dapat membuat profile baru.', {
            title: 'Konfirmasi Perubahan UOM',
            okText: 'Ya, Ubah',
            cancelText: 'Batal'
          })).then(function (yes) {
            if (!yes) {
              return;
            }
            lines[idx].buy_uom_id = buyId > 0 ? buyId : null;
            lines[idx].buy_uom_code = (buyId > 0 && uomById[buyId]) ? (uomById[buyId].code || '') : '';
            refreshLines();
          });
        } else if (window.FinanceUI && typeof window.FinanceUI.alert === 'function') {
          window.FinanceUI.alert('Modal konfirmasi tidak tersedia. Muat ulang halaman lalu coba lagi.', { title: 'UI Belum Siap' });
        }
        return;
      }
      lines[idx].buy_uom_id = buyId > 0 ? buyId : null;
      lines[idx].buy_uom_code = (buyId > 0 && uomById[buyId]) ? (uomById[buyId].code || '') : '';
    }
    if (e.target.classList.contains('line-content-uom')) {
      if (isPaymentOnlyEditMode) {
        return;
      }
      var kind = String(lines[idx].line_kind || '').toUpperCase();
      if (kind === 'MATERIAL') {
        var lockId = num(lines[idx].material_content_uom_id || lines[idx].content_uom_id || 0);
        if (lockId > 0) {
          e.target.value = String(lockId);
        }
        alertMsg('warning', 'UOM isi untuk MATERIAL harus mengikuti master material.');
        return;
      }
      var contentId = num(e.target.value);
      lines[idx].content_uom_id = contentId > 0 ? contentId : null;
      lines[idx].content_uom_code = (contentId > 0 && uomById[contentId]) ? (uomById[contentId].code || '') : '';
    }
    if (e.target.classList.contains('line-usage-purpose')) {
      lines[idx].usage_purpose = normalizeUsagePurpose(e.target.value);
      lines[idx].default_usage_purpose = lines[idx].usage_purpose;
      lines[idx].usage_purpose_is_manual = true;
    }
  });

  function divisionCodeByDestination(dest) {
    var d = String(dest || '').toUpperCase();
    if (d === 'BAR' || d === 'BAR_EVENT') return 'BAR';
    if (d === 'KITCHEN' || d === 'KITCHEN_EVENT') return 'KITCHEN';
    if (d === 'ROASTERY' || d === 'ROASTERY_EVENT') return 'ROASTERY';
    if (d === 'OFFICE') return 'MANAJEMEN';
    return '';
  }

  function applyPurchaseTypeRules(preserveDestinationSelection) {
    var typeEl = document.getElementById('purchase_type_id');
    var destEl = document.getElementById('destination_type');
    var divLabelEl = document.getElementById('destination_division_label');
    var divIdEl = document.getElementById('destination_division_id');
    var destWrap = destEl ? destEl.closest('.col-md-3') : null;
    var divWrap = divLabelEl ? divLabelEl.closest('.col-md-3') : null;
    var opt = typeEl.options[typeEl.selectedIndex];

    var behavior = opt ? String(opt.getAttribute('data-destination-behavior') || 'REQUIRED').toUpperCase() : 'REQUIRED';
    var defaultDest = opt ? String(opt.getAttribute('data-default-destination') || '').toUpperCase() : '';
    var affectsInventory = opt ? Number(opt.getAttribute('data-affects-inventory') || 0) : 0;
    isInventoryType = affectsInventory === 1 && behavior !== 'NONE';

    if (affectsInventory !== 1 || behavior === 'NONE') {
      if (destWrap) destWrap.style.display = 'none';
      if (divWrap) divWrap.style.display = 'none';
      destEl.value = '';
      divLabelEl.value = '-';
      divIdEl.value = '';
      applyLineModeByPurchaseType();
      return;
    }

    if (destWrap) destWrap.style.display = '';
    if (divWrap) divWrap.style.display = '';

    var currentDest = String(destEl.value || '').toUpperCase();
    if (preserveDestinationSelection && currentDest !== '') {
      destEl.value = currentDest;
    } else {
      destEl.value = defaultDest || 'GUDANG';
    }

    var divisionCode = divisionCodeByDestination(destEl.value);
    var divisionId = divisionCode ? Number(divisionCodeToId[divisionCode] || 0) : 0;
    var currentDivisionId = Number(divIdEl.value || 0);
    if (preserveDestinationSelection && currentDivisionId > 0) {
      divisionId = currentDivisionId;
    }
    divLabelEl.value = divisionCode || '-';
    divIdEl.value = divisionId > 0 ? String(divisionId) : '';
    applyLineModeByPurchaseType();
  }

  document.getElementById('purchase_type_id').addEventListener('change', function () {
    applyPurchaseTypeRules(false);
  });

  var btnAddVendor = document.getElementById('btn-po-add-vendor');
  if (btnAddVendor) {
    btnAddVendor.addEventListener('click', function () {
      if (!window.FinanceQuickVendor) {
        return;
      }
      window.FinanceQuickVendor.open({
        title: 'Tambah Vendor Purchase Order',
        onCreated: function (vendor) {
          syncVendorOption(vendor);
          alertMsg('success', 'Vendor siap dipakai: ' + esc(window.FinanceQuickVendor.vendorLabel(vendor)) + '.');
        }
      });
    });
  }

  var vendorEl = document.getElementById('vendor_id');
  var vendorDisplayEl = document.getElementById('vendor_display_input');
  if (vendorEl) {
    vendorEl.addEventListener('change', function () {
      if (vendorDisplayEl) vendorDisplayEl.classList.remove('is-invalid');
      refreshAllLineSuggestions(true);
    });
  }

  document.getElementById('btn-save-po').addEventListener('click', function () {
    var btnSave = this;
    var submitLines = lines.filter(function (line) {
      return !isLineEffectivelyEmpty(line);
    });

    if (!submitLines.length) {
      alertMsg('warning', 'Tambahkan minimal 1 line PO.');
      return;
    }

    for (var i = 0; i < submitLines.length; i++) {
      var lk = String(submitLines[i].line_kind || '').toUpperCase();
      if (!num(submitLines[i].buy_uom_id)) {
        alertMsg('warning', 'Baris #' + (i + 1) + ': UOM beli wajib dipilih.');
        return;
      }
      if (lk === 'MATERIAL' && !num(submitLines[i].material_id)) {
        alertMsg('warning', 'Baris #' + (i + 1) + ': bahan baku baru harus dibuat dulu di master bahan baku, lalu pilih ulang dari catalog.');
        return;
      }
      if (lk === 'MATERIAL') {
        var matContentId = num(submitLines[i].material_content_uom_id || 0);
        if (matContentId > 0) {
          submitLines[i].content_uom_id = matContentId;
          submitLines[i].content_uom_code = (uomById[matContentId] && uomById[matContentId].code) ? uomById[matContentId].code : (submitLines[i].content_uom_code || '');
        }
      }
      var lineExpired = dateVal(submitLines[i].expired_date || '');
      if (!isInventoryType) {
        submitLines[i].expired_date = null;
        submitLines[i].required_expiry_date = null;
        submitLines[i].expiry_policy = 'NONE';
        submitLines[i].min_remaining_days = null;
      } else {
        submitLines[i].expired_date = lineExpired !== '' ? lineExpired : null;
        submitLines[i].required_expiry_date = lineExpired !== '' ? lineExpired : null;
        submitLines[i].expiry_policy = lineExpired !== '' ? 'EXACT_DATE' : 'NONE';
        submitLines[i].min_remaining_days = null;
      }
    }

    var header = {
      request_date: document.getElementById('request_date').value,
      purchase_type_id: num(document.getElementById('purchase_type_id').value),
      destination_type: document.getElementById('destination_type').value || null,
      destination_division_id: num(document.getElementById('destination_division_id').value) || null,
      vendor_id: num(document.getElementById('vendor_id').value),
      payment_account_id: num(document.getElementById('payment_account_id').value) || null,
      status: document.getElementById('status').value || 'DRAFT',
      notes: document.getElementById('po_notes').value || null
    };

    if (!header.purchase_type_id || !header.request_date) {
      alertMsg('warning', 'Header belum lengkap: request date dan purchase type wajib diisi.');
      return;
    }
    if (!header.vendor_id) {
      var vendorField = document.getElementById('vendor_display_input');
      if (vendorField) {
        vendorField.classList.add('is-invalid');
        vendorField.focus();
      }
      alertMsg('warning', 'Vendor wajib dipilih sebelum menyimpan purchase order.');
      return;
    }

    if (!skipEditReviewModal && editMode && normalizeStatus(header.status || initialStatus) !== normalizeStatus(initialStatus)) {
      var reviewOpened = openReviewModal(buildReviewPayload(header, submitLines), btnSave);
      if (reviewOpened) {
        return;
      }
    }

    submitPurchase(header, submitLines, btnSave);
  });

  applyPurchaseTypeRules(editMode);

  document.getElementById('btn-add-line').addEventListener('click', function () {
    addEmptyLine(true);
  });

  if (editMode && Array.isArray(initialLines) && initialLines.length > 0) {
    lines = initialLines.map(function (row) {
      var mapped = Object.assign(createEmptyLine(), row || {});
      mapped.selected_display_name = String(mapped.catalog_name || mapped.item_name || mapped.material_name || '');
      mapped.qty_buy = num(mapped.qty_buy);
      mapped.content_per_buy = num(mapped.content_per_buy);
      mapped.conversion_factor_to_content = num(mapped.content_per_buy || mapped.conversion_factor_to_content);
      mapped.unit_price = num(mapped.unit_price);
      mapped.discount_percent = num(mapped.discount_percent);
      mapped.tax_percent = num(mapped.tax_percent);
      mapped.expired_date = dateVal(mapped.expired_date);
      mapped.required_expiry_date = dateVal(mapped.required_expiry_date || mapped.expired_date);
      mapped.expiry_policy = String(mapped.expiry_policy || (mapped.required_expiry_date ? 'EXACT_DATE' : 'NONE'));
      mapped.min_remaining_days = num(mapped.min_remaining_days || 0) || null;
      mapped.default_usage_purpose = normalizeUsagePurpose(mapped.default_usage_purpose || mapped.usage_purpose);
      mapped.usage_purpose = normalizeUsagePurpose(mapped.usage_purpose || mapped.default_usage_purpose);
      mapped.usage_purpose_is_manual = true;
      mapped.catalog_suggestions = [];
      mapped.suggestion_loading = false;
      mapped.suggestion_query = '';
      applyLineTypeDefaults(mapped);
      return mapped;
    });
    refreshLines();
    activeLineIdx = 0;
    refreshAllLineSuggestions(true);
  } else {
    addEmptyLine(false);
  }

  if (reviewConfirmEl) {
    reviewConfirmEl.addEventListener('change', function () {
      if (reviewConfirmEl.checked) {
        showReviewAlert('');
      }
    });
  }

  if (reviewSubmitBtn) {
    reviewSubmitBtn.addEventListener('click', function () {
      if (!pendingSubmitPayload) {
        return;
      }
      if (!reviewConfirmEl || !reviewConfirmEl.checked) {
        showReviewAlert('Centang konfirmasi review buyer dulu sebelum menyimpan.');
        if (reviewConfirmEl && typeof reviewConfirmEl.focus === 'function') {
          reviewConfirmEl.focus();
        }
        return;
      }
      var submitPayload = {
        header: Object.assign({}, pendingSubmitPayload.header || {}, { review_confirmed: 1 }),
        lines: pendingSubmitPayload.lines || [],
        button: pendingSubmitPayload.button
      };
      hideReviewModal(false);
      submitPurchase(submitPayload.header, submitPayload.lines, submitPayload.button);
      pendingSubmitPayload = null;
    });
  }

  if (reviewModalEl) {
    reviewModalEl.addEventListener('click', function (event) {
      if (event.target === reviewModalEl) {
        hideReviewModal();
      }
    });
    reviewModalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (button) {
      button.addEventListener('click', function () {
        hideReviewModal();
      });
    });
  }

  document.addEventListener('click', function (e) {
    if (!catalogPreviewWrap.contains(e.target) && e.target !== catalogKeywordEl) {
      catalogPreviewWrap.style.display = 'none';
    }
  });
})();
</script>

<script>
(function () {
  'use strict';
  var VENDOR_SEARCH_URL = '<?php echo site_url('master/lookup-search/purchase-catalog-vendor/vendor_id'); ?>';
  var dispInput = document.getElementById('vendor_display_input');
  var hiddenSel = document.getElementById('vendor_id');
  var resultBox = document.getElementById('vendor_search_result');
  if (!dispInput || !hiddenSel || !resultBox) return;

  // Initialize display input from hidden select on page load
  if (hiddenSel.value) {
    var selOpt = hiddenSel.options[hiddenSel.selectedIndex];
    if (selOpt && selOpt.value) dispInput.value = selOpt.text || '';
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m];
    });
  }

  function selectVendor(id, label) {
    // Update hidden select (find or create option)
    var found = false;
    for (var i = 0; i < hiddenSel.options.length; i++) {
      if (String(hiddenSel.options[i].value) === String(id)) {
        hiddenSel.value = String(id);
        found = true;
        break;
      }
    }
    if (!found) {
      var opt = document.createElement('option');
      opt.value = String(id);
      opt.textContent = label;
      hiddenSel.appendChild(opt);
      hiddenSel.value = String(id);
    }
    // Update display
    dispInput.value = label;
    dispInput.classList.remove('is-invalid');
    resultBox.style.display = 'none';
    resultBox.innerHTML = '';
    // Dispatch change to trigger line suggestion refresh
    hiddenSel.dispatchEvent(new Event('change'));
  }

  function renderVendorResults(rows) {
    if (!rows.length) {
      resultBox.innerHTML = '<div class="po-vendor-item" style="color:#888;">Tidak ada vendor ditemukan.</div>';
      resultBox.style.display = 'block';
      return;
    }
    resultBox.innerHTML = rows.map(function (row) {
      return '<div class="po-vendor-item" data-val="' + esc(String(row.value)) + '" data-lbl="' + esc(String(row.label)) + '">' + esc(String(row.label)) + '</div>';
    }).join('');
    resultBox.style.display = 'block';
    Array.prototype.forEach.call(resultBox.querySelectorAll('.po-vendor-item[data-val]'), function (item) {
      item.addEventListener('mousedown', function (e) {
        e.preventDefault();
        selectVendor(item.getAttribute('data-val'), item.getAttribute('data-lbl'));
      });
    });
  }

  var vendorTimer = null;
  function doVendorSearch(q) {
    fetch(VENDOR_SEARCH_URL + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (json) { renderVendorResults(Array.isArray(json.rows) ? json.rows : []); })
      .catch(function () {});
  }

  dispInput.addEventListener('input', function () {
    hiddenSel.value = '';
    clearTimeout(vendorTimer);
    vendorTimer = setTimeout(function () { doVendorSearch(dispInput.value.trim()); }, 280);
  });

  dispInput.addEventListener('focus', function () {
    if (resultBox.innerHTML.trim()) {
      resultBox.style.display = 'block';
    } else if (!hiddenSel.value) {
      doVendorSearch('');
    }
  });

  dispInput.addEventListener('blur', function () {
    setTimeout(function () { resultBox.style.display = 'none'; }, 200);
  });

  document.addEventListener('click', function (e) {
    if (!dispInput.contains(e.target) && !resultBox.contains(e.target)) {
      resultBox.style.display = 'none';
    }
  });
})();
</script>
