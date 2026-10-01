<?php
$mode = (string)($mode ?? 'create');
$header = $header ?? [];
$lines = $lines ?? [];
$divisionOptions = $division_options ?? [];
$destinationOptions = $destination_options ?? [];
$destinationGuardMap = $destination_guard_map ?? [];
$uomOptions = $uom_options ?? [];
$vendorOptions = $vendor_options ?? [];
$usagePurposeOptions = (array)($usage_purpose_options ?? [
  ['value' => 'BAHAN_BAKU', 'label' => 'Persediaan Produksi'],
  ['value' => 'OPERASIONAL', 'label' => 'Kebutuhan Operasional'],
]);
$isPurchaseScope = !empty($is_purchase_scope);
$canVerify = !empty($can_verify);
$requestId = (int)($request_id ?? 0);
$showVendorColumn = $canVerify;
$lineColumnCount = 7;
$defaultRequestDate = (string)($header['request_date'] ?? date('Y-m-d'));
$defaultNeededDate = (string)($header['needed_date'] ?? date('Y-m-d', strtotime('+1 day')));

$formAction = $mode === 'create'
    ? site_url('procurement/division-po-sr/create')
    : site_url('procurement/division-po-sr/edit/' . $requestId);
$submitLabel = 'Simpan Pengajuan';
if ($mode === 'edit') {
    $submitLabel = 'Perbarui Pengajuan';
} elseif ($mode === 'verify') {
    $submitLabel = 'Verifikasi & Bentuk Dokumen';
}
$divisionLocked = $mode !== 'create' || count($divisionOptions) <= 1 || !$isPurchaseScope;
$selectedDivisionId = (int)($header['division_id'] ?? 0);
$allowedDestinationValues = (array)($destinationGuardMap[$selectedDivisionId] ?? []);
$selectedDivisionLabel = '-';
foreach ($divisionOptions as $option) {
  if ((int)($option['id'] ?? 0) === $selectedDivisionId) {
    $selectedDivisionLabel = (string)($option['name'] ?? $option['division_name'] ?? ('DIV#' . $selectedDivisionId));
    break;
  }
}
$initialLines = [];
foreach ((array)$lines as $line) {
  $contentPerBuy = (float)($line['profile_content_per_buy'] ?? 1);
  if ($contentPerBuy <= 0) {
    $contentPerBuy = 1;
  }
  $qtyContentBalance = (float)($line['qty_content_available_snapshot'] ?? 0);
  $qtyContentToPo = (float)($line['qty_content_to_po'] ?? 0);
  $buyUomCode = (string)($line['profile_buy_uom_code'] ?? '');
  $contentUomCode = (string)($line['profile_content_uom_code'] ?? '');
  $storedRequestMode = strtoupper(trim((string)($line['request_uom_mode'] ?? '')));
  $itemId = !empty($line['item_id']) ? (int)$line['item_id'] : null;
  $materialId = !empty($line['material_id']) ? (int)$line['material_id'] : null;
  $profileName = (string)($line['profile_name'] ?? '');
  $storedSourceType = strtoupper(trim((string)($line['source_type'] ?? '')));
  if ($storedSourceType === '') {
    $storedSourceType = ($itemId === null && $materialId === null && trim($profileName) !== '')
      ? 'MANUAL'
      : ($qtyContentBalance > 0 ? 'WAREHOUSE' : 'CATALOG');
  }
  $storedLineKind = strtoupper(trim((string)($line['line_kind'] ?? '')));
  if ($storedLineKind === '') {
    $storedLineKind = $materialId !== null ? 'MATERIAL' : 'ITEM';
  }
    $initialLines[] = [
        'id' => (int)($line['id'] ?? 0),
        'line_no' => (int)($line['line_no'] ?? 0),
        'review_status' => (string)($line['review_status'] ?? 'PENDING'),
        'review_notes' => (string)($line['review_notes'] ?? ''),
        'line_kind' => $storedLineKind,
        'item_id' => $itemId,
        'material_id' => $materialId,
        'profile_key' => (string)($line['profile_key'] ?? ''),
        'profile_name' => (string)($line['profile_name'] ?? ''),
        'profile_brand' => (string)($line['profile_brand'] ?? ''),
        'profile_description' => (string)($line['profile_description'] ?? ''),
        'profile_expired_date' => (string)($line['profile_expired_date'] ?? ''),
        'expiry_policy' => (string)($line['expiry_policy'] ?? (!empty($line['required_expiry_date']) || !empty($line['profile_expired_date']) ? 'EXACT_DATE' : 'NONE')),
        'required_expiry_date' => (string)($line['required_expiry_date'] ?? ($line['profile_expired_date'] ?? '')),
        'min_remaining_days' => !empty($line['min_remaining_days']) ? (int)$line['min_remaining_days'] : null,
        'buy_uom_id' => (int)($line['buy_uom_id'] ?? 0),
        'content_uom_id' => (int)($line['content_uom_id'] ?? 0),
        'profile_content_per_buy' => $contentPerBuy,
        'profile_buy_uom_code' => $buyUomCode,
        'profile_content_uom_code' => $contentUomCode,
        'request_uom_mode' => in_array($storedRequestMode, ['BUY', 'CONTENT'], true) ? $storedRequestMode : '',
        'vendor_id' => !empty($line['vendor_id']) ? (int)$line['vendor_id'] : null,
        'vendor_name' => (string)($line['vendor_name'] ?? ''),
        'qty_buy_requested' => (float)($line['qty_buy_requested'] ?? 0),
        'qty_content_requested' => (float)($line['qty_content_requested'] ?? 0),
        'qty_buy_po_requested' => $qtyContentToPo > 0 ? ($qtyContentToPo / $contentPerBuy) : 0,
        'qty_content_po_requested' => $qtyContentToPo,
        'qty_buy_balance' => $qtyContentBalance / $contentPerBuy,
        'qty_content_balance' => $qtyContentBalance,
        'estimated_unit_price' => (float)($line['estimated_unit_price'] ?? 0),
        'default_usage_purpose' => (string)($line['default_usage_purpose'] ?? ($line['usage_purpose'] ?? 'BAHAN_BAKU')),
        'usage_purpose' => (string)($line['usage_purpose'] ?? ($line['default_usage_purpose'] ?? 'BAHAN_BAKU')),
        'source_type' => $storedSourceType,
        'notes' => (string)($line['notes'] ?? ''),
        'line_reviewed' => !$canVerify || !empty($line['line_reviewed']),
    ];
}
?>

<?php
if (!function_exists('finance_dreq_location_label')) {
  function finance_dreq_location_label($destinationType)
  {
    $destinationType = strtoupper(trim((string)$destinationType));
    if (strpos($destinationType, 'EVENT') !== false) {
      return 'Event';
    }
    if (in_array($destinationType, ['BAR', 'KITCHEN', 'OFFICE'], true)) {
      return 'Reguler';
    }
    return $destinationType !== '' ? $destinationType : '-';
  }
}
?>

<style>
  .dreq-line-table th, .dreq-line-table td { vertical-align: middle; }
  .dreq-line-table th { padding: .55rem .45rem; white-space: nowrap; }
  .dreq-line-table td { padding: .45rem .4rem; }
  .dreq-line-table { min-width: 1890px; }
  .dreq-line-table.is-verify {
    min-width: 1200px;
    table-layout: fixed;
  }
  .dreq-line-table.is-verify th,
  .dreq-line-table.is-verify td {
    white-space: normal;
    word-break: break-word;
  }
  .dreq-line-table.is-verify th:nth-child(1),
  .dreq-line-table.is-verify td:nth-child(1) { width: 20%; }
  .dreq-line-table.is-verify th:nth-child(2),
  .dreq-line-table.is-verify td:nth-child(2) { width: 6%; }
  .dreq-line-table.is-verify th:nth-child(3),
  .dreq-line-table.is-verify td:nth-child(3) { width: 7%; }
  .dreq-line-table.is-verify th:nth-child(4),
  .dreq-line-table.is-verify td:nth-child(4) { width: 11%; }
  .dreq-line-table.is-verify th:nth-child(5),
  .dreq-line-table.is-verify td:nth-child(5) { width: 8%; }
  .dreq-line-table.is-verify th:nth-child(6),
  .dreq-line-table.is-verify td:nth-child(6) { width: 7%; }
  .dreq-line-table.is-verify th:nth-child(7),
  .dreq-line-table.is-verify td:nth-child(7) { width: 7%; }
  .dreq-line-table.is-verify th:nth-child(8),
  .dreq-line-table.is-verify td:nth-child(8) { width: 8%; }
  .dreq-line-table.is-verify th:nth-child(9),
  .dreq-line-table.is-verify td:nth-child(9) { width: 9%; }
  .dreq-line-table.is-verify th:nth-child(10),
  .dreq-line-table.is-verify td:nth-child(10) { width: 5%; }
  .dreq-line-table.is-verify th:nth-child(11),
  .dreq-line-table.is-verify td:nth-child(11) { width: 7%; }
  .dreq-line-table.is-verify th:nth-child(12),
  .dreq-line-table.is-verify td:nth-child(12) { width: 5%; }
  .dreq-search-scroll { max-height: 260px; overflow: auto; }
  .dreq-manual-card { display: none; }
  .dreq-search-table th, .dreq-search-table td { vertical-align: middle; }
  .dreq-profile-cell { min-width: 360px; }
  .dreq-uom-cell { min-width: 140px; }
  .dreq-vendor-cell { min-width: 240px; }
  .dreq-usage-cell { min-width: 150px; }
  .dreq-usage-select { min-width: 150px; }
  .dreq-stock-cell { min-width: 95px; }
  .dreq-request-cell { min-width: 180px; }
  .dreq-qty-input { min-width: 110px; }
  .dreq-po-input { min-width: 120px; }
  .dreq-qty-readonly { min-width: 110px; background: #f8f9fa; }
  .dreq-notes-input { min-width: 160px; }
  .dreq-action-btn { min-width: 72px; white-space: nowrap; }
  .dreq-line-table.is-verify th:last-child,
  .dreq-line-table.is-verify td:last-child {
    min-width: 124px;
    width: 124px;
  }
  .dreq-line-table.is-verify .dreq-action-btn {
    min-width: 108px;
    padding: .32rem .45rem;
    font-size: .78rem;
    line-height: 1.2;
  }
  .dreq-line-table.is-verify td:last-child .d-grid {
    gap: .35rem !important;
  }
  .dreq-line-table.is-verify .dreq-profile-cell,
  .dreq-line-table.is-verify .dreq-vendor-cell,
  .dreq-line-table.is-verify .dreq-uom-cell,
  .dreq-line-table.is-verify .dreq-request-cell {
    min-width: 0;
  }
  .dreq-line-table.is-verify .dreq-profile-meta {
    gap: .18rem .35rem;
  }
  .dreq-vendor-title {
    line-height: 1.3;
  }
  .dreq-vendor-caption {
    display: inline-flex;
    align-items: center;
    padding: .14rem .48rem;
    border-radius: 999px;
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .02em;
  }
  .dreq-vendor-caption.is-required {
    background: rgba(138, 21, 56, .12);
    color: #8a1538;
  }
  .dreq-vendor-caption.is-optional {
    background: rgba(25, 135, 84, .12);
    color: #1d6f46;
  }
  .dreq-qty-input.is-overstock { border-color: #f0ad4e; background: #fff8ea; }
  .dreq-po-input.is-active-po { border-color: #7f5af0; background: #f7f2ff; }
  .dreq-stock-split { color: #9a6700; font-weight: 600; }
  .dreq-guidance {
    border: 1px solid #f0dfcf;
    background: linear-gradient(180deg, #fffaf4 0%, #fff6ed 100%);
    border-radius: 14px;
    padding: .8rem 1rem;
  }
  .dreq-vendor-wrap { display: flex; align-items: center; gap: .45rem; }
  .dreq-vendor-select { min-width: 0; flex: 1 1 auto; }
  .dreq-vendor-add { min-width: 42px; flex: 0 0 auto; border-radius: 10px; }
  .dreq-vendor-meta { display: none; }
  .dreq-request-mode { min-width: 130px; }
  .dreq-request-stack { display: grid; gap: .35rem; }
  .dreq-profile-editor { border-top: 1px dashed #dfd2c8; padding-top: .55rem; }
  .dreq-profile-title { line-height: 1.35; }
  .dreq-profile-meta { display: flex; flex-wrap: wrap; gap: .25rem .45rem; align-items: center; margin-top: .25rem; }
  .dreq-profile-hint { font-size: .73rem; line-height: 1.35; color: #7b6a61; }
  .dreq-profile-editor {
    border-top: 1px dashed #dfd2c8;
    padding-top: .45rem;
    background: #fffaf4;
    border-radius: 12px;
    padding: .45rem;
  }
  .dreq-profile-editor-grid { display: grid; gap: .35rem; }
  .dreq-buyer-row {
    display: grid;
    grid-template-columns: minmax(105px, .95fr) minmax(155px, 1.35fr);
    gap: .35rem;
  }
  .dreq-est-row {
    display: grid;
    grid-template-columns: 44px minmax(150px, 1fr) auto;
    gap: .35rem;
    align-items: center;
  }
  .dreq-est-label {
    font-size: .74rem;
    font-weight: 700;
    color: #8a1538;
    text-transform: uppercase;
    letter-spacing: .04em;
    text-align: center;
  }
  .dreq-est-input {
    min-width: 0;
    text-align: right;
  }
  .dreq-suggest-btn {
    min-width: 92px;
    white-space: nowrap;
  }
  .dreq-suggestion-list { display: flex; flex-direction: column; gap: .35rem; }
  .dreq-suggestion-chip { text-align: left; white-space: normal; border-radius: 12px; padding: .5rem .65rem; }
  .dreq-suggestion-chip strong,
  .dreq-suggestion-chip small { display: block; }
  .dreq-suggestion-chip small { font-size: .72rem; line-height: 1.35; color: #6c757d; }
  .dreq-draft-modal .modal-content {
    border: 0;
    border-radius: 20px;
    overflow: hidden;
  }
  .dreq-draft-head {
    padding: 1rem 1.15rem;
    background: linear-gradient(135deg, #8a1538 0%, #b43c5c 100%);
    color: #fff9f6;
  }
  .dreq-draft-title {
    font-size: 1.1rem;
    font-weight: 700;
    line-height: 1.35;
  }
  .dreq-draft-subtitle {
    margin-top: .2rem;
    opacity: .88;
    font-size: .84rem;
  }
  .dreq-draft-body {
    padding: 1rem 1.15rem 1.1rem;
    background: linear-gradient(180deg, #fffaf5 0%, #ffffff 100%);
  }
  .dreq-draft-note {
    padding: .5rem .7rem;
    border-radius: 12px;
    background: #fff3db;
    color: #8a5a00;
    font-size: .82rem;
    font-weight: 600;
  }
  .dreq-draft-summary {
    border: 1px solid #ead9cc;
    border-radius: 14px;
    background: #fff;
    padding: .75rem .85rem;
  }
  .dreq-review-badge {
    font-size: .71rem;
    font-weight: 700;
    letter-spacing: .02em;
  }
  .dreq-review-summary {
    margin-top: .45rem;
    border-top: 1px dashed #dfd2c8;
    padding-top: .45rem;
  }
  .dreq-verify-modal .modal-content {
    border: 0;
    border-radius: 22px;
    overflow: hidden;
  }
  .dreq-verify-head {
    padding: 1rem 1.15rem;
    background: linear-gradient(135deg, #7f1020 0%, #b43c5c 100%);
    color: #fff7f2;
  }
  .dreq-verify-body {
    padding: 1rem 1.15rem 1.1rem;
    background: linear-gradient(180deg, #fff9f4 0%, #ffffff 100%);
  }
  .dreq-verify-note {
    padding: .5rem .7rem;
    border-radius: 12px;
    background: #fef1d8;
    color: #8a5a00;
    font-size: .82rem;
    font-weight: 600;
  }
  .dreq-verify-suggestions {
    display: flex;
    flex-direction: column;
    gap: .35rem;
  }
  @media (max-width: 991.98px) {
    .dreq-buyer-row,
    .dreq-est-row {
      grid-template-columns: 1fr;
    }
    .dreq-est-label {
      text-align: left;
    }
    .dreq-suggest-btn {
      width: 100%;
    }
  }
  .dreq-line-table, .dreq-line-table.is-verify { width:100%; min-width:0; table-layout:fixed; }
  .dreq-line-table th, .dreq-line-table td,
  .dreq-line-table.is-verify th:nth-child(n), .dreq-line-table.is-verify td:nth-child(n) {
    width:auto; min-width:0; padding:.8rem .6rem; text-align:left; vertical-align:top;
    white-space:normal; overflow-wrap:anywhere;
  }
  .dreq-line-table .form-control, .dreq-line-table .form-select { width:100%; min-width:0; }
  .dreq-line-table .badge { white-space:normal; line-height:1.4; }
  .dreq-line-table [data-live-stock-line] { min-width:0 !important; }
  .dreq-row-actions { display:flex; flex-direction:column; gap:.4rem; }
  .dreq-row-actions .btn { padding:.35rem .3rem; white-space:normal; }
  .dreq-row-rejected { background:#fff3f1; }
  .dreq-row-verified { background:#f2faf5; }
  .dreq-search-table th, .dreq-search-table td { text-align:left; white-space:normal; padding:.65rem; }
  .dreq-search-scroll { overflow:auto; }
  .dreq-search-table { min-width:680px; }
  @media (max-width:1100px) {
    .dreq-line-table colgroup, .dreq-line-table thead { display:none; }
    .dreq-line-table, .dreq-line-table tbody { display:block; }
    .dreq-line-table tbody tr { display:grid; grid-template-columns:1fr 1fr; border:1px solid #eaded7; border-radius:12px; margin-bottom:1rem; overflow:hidden; }
    .dreq-line-table td::before { content:attr(data-label); display:block; font-size:.7rem; font-weight:700; color:#85675e; margin-bottom:.35rem; text-transform:uppercase; }
    .dreq-line-table td:first-child, .dreq-line-table td:last-child { grid-column:1 / -1; }
    .dreq-row-actions { flex-direction:row; flex-wrap:wrap; align-items:center; }
    .dreq-row-actions .btn { padding:.4rem .8rem; }
  }
  @media (max-width:540px) { .dreq-line-table tbody tr { grid-template-columns:1fr; } }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h4 class="mb-0"><i class="ri-file-list-3-line page-title-icon me-1"></i><?php echo html_escape($title ?? 'Form Pengajuan Divisi'); ?></h4>
    <small class="text-muted">
      <?php echo $mode === 'verify'
        ? 'Purchase dapat menyesuaikan item, merk, keterangan, dan harga estimasi sebelum sistem membentuk SR/PO final. Split stok final dihitung ulang saat verifikasi disimpan.'
        : 'Pencarian berjalan via AJAX: stok gudang lebih dulu, lalu katalog, lalu input manual bila tidak ditemukan.'; ?>
    </small>
  </div>
  <a href="<?php echo $requestId > 0 ? site_url('procurement/division-po-sr/detail/' . $requestId) : site_url('procurement/division-po-sr'); ?>" class="btn btn-outline-secondary">Kembali</a>
</div>

<?php $this->load->view('purchase/_po_sr_tabs', ['po_sr_active' => 'division-po-sr']); ?>

<?php if ($this->session->flashdata('success')): ?>
  <div class="alert alert-success"><?php echo html_escape((string)$this->session->flashdata('success')); ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('error')): ?>
  <div class="alert alert-danger"><?php echo html_escape((string)$this->session->flashdata('error')); ?></div>
<?php endif; ?>

<form method="post" action="<?php echo $formAction; ?>" id="divisionRequestForm">
  <div class="card mb-3">
    <div class="card-body">
      <fieldset <?= $canVerify ? 'disabled' : '' ?>><div class="row g-3">
        <div class="col-md-2">
          <label class="form-label mb-1">No Request</label>
          <input type="text" class="form-control" value="<?php echo html_escape((string)($header['request_no'] ?? 'AUTO')); ?>" readonly>
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1">Tgl Request</label>
          <input type="date" name="request_date" class="form-control" value="<?php echo html_escape($defaultRequestDate); ?>" required>
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1">Tgl Butuh</label>
          <input type="date" name="needed_date" class="form-control" value="<?php echo html_escape($defaultNeededDate); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label mb-1">Divisi</label>
          <?php if ($divisionLocked): ?>
            <input type="text" class="form-control" value="<?php echo html_escape($selectedDivisionLabel); ?>" readonly>
            <input type="hidden" name="division_id" value="<?php echo (int)($header['division_id'] ?? 0); ?>">
          <?php else: ?>
            <select name="division_id" id="fieldDivisionId" class="form-select">
              <?php foreach ($divisionOptions as $option): ?>
                <option value="<?php echo (int)$option['id']; ?>" <?php echo ((int)($header['division_id'] ?? 0) === (int)$option['id']) ? 'selected' : ''; ?>>
                  <?php echo html_escape((string)($option['name'] ?? $option['division_name'] ?? ('DIV#' . $option['id']))); ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1">Lokasi Stok</label>
          <select name="destination_type" id="fieldDestinationType" class="form-select">
            <?php foreach ($destinationOptions as $option): ?>
              <?php if (!empty($allowedDestinationValues) && !in_array((string)($option['value'] ?? ''), $allowedDestinationValues, true)) { continue; } ?>
              <option value="<?php echo html_escape((string)($option['value'] ?? '')); ?>" <?php echo ((string)($header['destination_type'] ?? '') === (string)($option['value'] ?? '')) ? 'selected' : ''; ?>>
                <?php echo html_escape(finance_dreq_location_label((string)($option['value'] ?? ''))); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Catatan</label>
          <input type="text" name="notes" class="form-control" value="<?php echo html_escape((string)($header['notes'] ?? '')); ?>" placeholder="Opsional">
        </div>
      </div></fieldset>
    </div>
  </div>

  <div id="dreqFormAlert" role="status"></div>
  <div class="card mb-3" <?= $canVerify ? 'hidden' : '' ?>>
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h6 class="mb-0">Cari Barang</h6>
        <small class="text-muted">Ketik nama barang. Sistem cek stok gudang dulu, lalu fallback ke katalog, lalu input manual bila kosong.</small>
      </div>
      <div class="dreq-guidance small text-muted mb-3">
        Jika stok gudang ada, daftar pencarian hanya menampilkan stok gudang agar pilihan utama tetap jelas. Setelah barang dipilih, tiap line bisa diinput memakai <strong>UOM Beli</strong> atau <strong>UOM Isi</strong>. Bila kebutuhan melebihi stok snapshot, isi saja <strong>Qty Request</strong> sesuai kebutuhan total; sistem akan pecah otomatis: stok yang ada masuk <strong>SR</strong>, sisanya diarahkan ke <strong>PO</strong> saat simpan/verifikasi.
      </div>
      <div id="dreqSearchMeta" class="small text-muted mb-2"></div>
      <div class="row g-2 mb-2">
        <div class="col-md-10">
          <input type="text" id="searchProfileInput" class="form-control" placeholder="Nama profile / item / material">
        </div>
        <div class="col-md-2 d-grid">
          <button type="button" class="btn btn-outline-primary" id="btnSearchProfile">Cari</button>
        </div>
      </div>
      <div class="dreq-search-scroll border rounded">
        <table class="table table-sm mb-0 dreq-search-table">
          <thead>
            <tr>
              <th>Barang / Jenis</th>
              <th>Satuan</th>
              <th class="text-end">Stok Gudang</th>
              <th class="text-end">Harga Satuan</th>
              <th>Expired / Pembelian</th>
              <th style="width:90px">Aksi</th>
            </tr>
          </thead>
          <tbody id="searchResultRows">
            <tr><td colspan="6" class="text-center text-muted py-3">Belum ada pencarian.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="border rounded p-3 mt-3 bg-light dreq-manual-card" id="manualEntryCard">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div>
            <strong>Input Manual</strong>
            <div class="small text-muted">Dipakai hanya saat stok gudang dan katalog sama-sama tidak menemukan barang. Line ini akan diarahkan ke PO, lalu mode inputnya tetap bisa diubah ke UOM beli atau isi di tabel bawah.</div>
          </div>
        </div>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label mb-1">Nama Barang</label>
            <input type="text" class="form-control" id="manualNameInput" placeholder="Nama barang manual">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Deskripsi</label>
            <input type="text" class="form-control" id="manualDescriptionInput" placeholder="Spesifikasi opsional untuk PO">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">UOM Beli</label>
            <select class="form-select" id="manualBuyUomId">
              <option value="">Pilih</option>
              <?php foreach ($uomOptions as $uom): ?>
                <option value="<?php echo (int)($uom['id'] ?? 0); ?>" data-code="<?php echo html_escape((string)($uom['code'] ?? '')); ?>"><?php echo html_escape((string)($uom['name'] ?? $uom['code'] ?? '')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">UOM Isi</label>
            <select class="form-select" id="manualContentUomId">
              <option value="">Pilih</option>
              <?php foreach ($uomOptions as $uom): ?>
                <option value="<?php echo (int)($uom['id'] ?? 0); ?>" data-code="<?php echo html_escape((string)($uom['code'] ?? '')); ?>"><?php echo html_escape((string)($uom['name'] ?? $uom['code'] ?? '')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Isi per Beli</label>
            <input type="number" min="0.01" step="0.01" class="form-control" id="manualContentPerBuy" value="1.00">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Qty Beli</label>
            <input type="number" min="0.01" step="0.01" class="form-control" id="manualQtyBuy" value="1.00">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Qty Isi</label>
            <input type="number" step="0.01" class="form-control" id="manualQtyContent" value="1.00" readonly>
          </div>
          <div class="col-md-2 d-grid align-items-end">
            <button type="button" class="btn btn-outline-primary" id="btnAddManualLine">Tambah Manual</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h6 class="mb-0">Line Pengajuan</h6>
        <small class="text-muted"><?php echo $canVerify
          ? 'Verifikasi atau tolak setiap rincian melalui kolom aksi. Keputusan langsung disimpan; rincian lainnya tetap menunggu.'
          : 'Pilih mode input per line. `Qty Request`, `Ke SR`, `Qty Tambahan PO`, dan `Ke PO` akan mengikuti UOM beli atau isi yang dipilih, sementara sistem tetap menyimpan konversi buy-content yang konsisten.'; ?></small>
      </div>
      <?php if ($canVerify): $reviewCounts = array_count_values(array_column($initialLines, 'review_status')); ?>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="badge bg-warning text-dark"><?= (int)($reviewCounts['PENDING'] ?? 0) ?> menunggu</span>
        <span class="badge bg-success"><?= (int)($reviewCounts['VERIFIED'] ?? 0) ?> diverifikasi</span>
        <span class="badge bg-danger"><?= (int)($reviewCounts['REJECTED'] ?? 0) ?> ditolak</span>
      </div>
      <?php endif; ?>
      <div class="table-responsive">
        <table class="table table-striped table-sm dreq-line-table<?php echo $canVerify ? ' is-verify' : ''; ?> mb-0">
          <colgroup><col style="width:23%"><col style="width:18%"><col style="width:15%"><col style="width:12%"><col style="width:14%"><col style="width:10%"><col style="width:8%"></colgroup>
          <thead>
            <tr>
              <th>Barang / Jenis</th>
              <th>Stok</th>
              <th>Jumlah / Satuan</th>
              <th>Pemakaian</th>
              <th>Alokasi SR / PO</th>
              <th>Harga / Catatan</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody id="requestLineRows">
            <tr><td colspan="<?php echo (int)$lineColumnCount; ?>" class="text-center text-muted py-3">Belum ada line pengajuan.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <input type="hidden" name="lines_json" id="fieldLinesJson" value="">
  <?php if (!$canVerify) $this->load->view('procurement/_stock_review_panel', ['stock_review_csrf'=>$stock_review_csrf ?? '', 'request_id'=>$requestId, 'can_verify'=>false]); ?>

  <div class="d-flex justify-content-end gap-2 mb-4">
    <a href="<?php echo $requestId > 0 ? site_url('procurement/division-po-sr/detail/' . $requestId) : site_url('procurement/division-po-sr'); ?>" class="btn btn-light">Batal</a>
    <?php if (!$canVerify): ?><button type="submit" class="btn btn-primary" id="btnSubmitDivisionRequest"><?php echo html_escape($submitLabel); ?></button><?php else: ?><span class="small text-muted align-self-center">Keputusan disimpan dari aksi setiap rincian.</span><?php endif; ?>
  </div>
</form>

<div class="modal fade dreq-draft-modal" id="dreqDraftModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content shadow-lg">
      <div class="dreq-draft-head">
        <div class="small opacity-75">Draft barang terpilih</div>
        <div class="dreq-draft-title" id="dreqDraftTitle">-</div>
        <div class="dreq-draft-subtitle" id="dreqDraftSubtitle">Klik hasil pencarian untuk isi detail dulu sebelum line masuk ke tabel pengajuan.</div>
      </div>
      <div class="modal-body dreq-draft-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
          <div class="small text-muted mb-0 flex-grow-1">Pola ini mengikuti form PO: buyer/divisi cek data inti dulu, lalu baru tambahkan ke line pengajuan.</div>
          <div class="dreq-draft-note" id="dreqDraftSourceNote">Baris belum ditambahkan.</div>
        </div>
        <div id="dreqDraftAlert"></div>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label mb-1">Arah Stok</label>
            <input type="text" class="form-control" id="dreqDraftKind" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Merk</label>
            <input type="text" class="form-control" id="dreqDraftBrand" placeholder="Merk final">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Keterangan</label>
            <input type="text" class="form-control" id="dreqDraftDescription" placeholder="Keterangan/spec final">
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">UOM Beli</label>
            <input type="text" class="form-control" id="dreqDraftBuyUom" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">UOM Isi</label>
            <input type="text" class="form-control" id="dreqDraftContentUom" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Isi per Beli</label>
            <input type="text" class="form-control" id="dreqDraftPack" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Stok Snapshot</label>
            <input type="text" class="form-control" id="dreqDraftStock" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Mode Input</label>
            <select class="form-select" id="dreqDraftRequestMode">
              <option value="">Pilih mode input</option>
              <option value="BUY">PACK / UOM Beli</option>
              <option value="CONTENT">ISI</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Qty Request</label>
            <input type="number" class="form-control text-end" id="dreqDraftQty" min="0.01" step="0.01" placeholder="0.00">
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Expired</label>
            <input type="date" class="form-control" id="dreqDraftExpired">
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Harga Estimasi</label>
            <input type="number" class="form-control text-end" id="dreqDraftPrice" min="0" step="0.01" placeholder="0.00">
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Pemakaian</label>
            <select class="form-select" id="dreqDraftUsagePurpose">
              <?php foreach ($usagePurposeOptions as $option): ?>
                <option value="<?php echo html_escape((string)($option['value'] ?? '')); ?>"><?php echo html_escape((string)($option['label'] ?? $option['value'] ?? '')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label mb-1">Catatan Line</label>
            <input type="text" class="form-control" id="dreqDraftNotes" placeholder="Opsional">
          </div>
        </div>
        <div class="dreq-draft-summary mt-3">
          <div class="small text-muted mb-1">Preview route</div>
          <div class="fw-semibold" id="dreqDraftRouteLabel">Pilih mode input dan qty request dulu.</div>
          <div class="small text-muted mt-1" id="dreqDraftRouteNote">Baris baru akan ditambahkan setelah data wajib diisi.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btnApplyDreqDraft">Tambahkan ke Pengajuan</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade dreq-verify-modal" id="dreqVerifyLineModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content shadow-lg">
      <div class="dreq-verify-head">
        <div class="small opacity-75">Review line pengajuan</div>
        <div class="dreq-draft-title" id="dreqVerifyTitle">-</div>
        <div class="dreq-draft-subtitle" id="dreqVerifySubtitle">Verifikasi line satu per satu sebelum bentuk SR atau PO hasil review.</div>
      </div>
      <div class="modal-body dreq-verify-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
          <div class="small text-muted mb-0 flex-grow-1">Modal ini dipakai purchase untuk cek line pengajuan, menyesuaikan data final buyer, dan memakai cari saran bila perlu.</div>
          <div class="dreq-verify-note" id="dreqVerifyNote">Line belum direview.</div>
        </div>
        <div id="dreqVerifyAlert"></div>
        <div class="border rounded p-3 mb-3 bg-white">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <label class="form-label mb-0">Cari Saran Profile Buyer</label>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnDreqVerifySuggest">Cari Saran</button>
          </div>
          <div class="dreq-verify-suggestions" id="dreqVerifySuggestionList">
            <div class="small text-muted">Belum ada saran. Klik Cari Saran untuk melihat kandidat buyer.</div>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label mb-1">Arah Stok</label>
            <input type="text" class="form-control" id="dreqVerifyKind" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Merk Final</label>
            <input type="text" class="form-control" id="dreqVerifyBrand" placeholder="Merk final">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Keterangan Final</label>
            <input type="text" class="form-control" id="dreqVerifyDescription" placeholder="Keterangan/spec final">
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">UOM Beli</label>
            <input type="text" class="form-control" id="dreqVerifyBuyUom" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">UOM Isi</label>
            <input type="text" class="form-control" id="dreqVerifyContentUom" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Isi per Beli</label>
            <input type="text" class="form-control" id="dreqVerifyPack" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Stok Snapshot</label>
            <input type="text" class="form-control" id="dreqVerifyStock" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Mode Input</label>
            <select class="form-select" id="dreqVerifyRequestMode">
              <option value="">Pilih mode input</option>
              <option value="BUY">PACK / UOM Beli</option>
              <option value="CONTENT">ISI</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Qty Request</label>
            <input type="number" class="form-control text-end" id="dreqVerifyQty" min="0.01" step="0.01" placeholder="0.00">
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Qty Tambahan PO</label>
            <input type="number" class="form-control text-end" id="dreqVerifyPoQty" min="0.00" step="0.01" placeholder="0.00">
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Harga Estimasi</label>
            <input type="number" class="form-control text-end" id="dreqVerifyPrice" min="0" step="0.01" placeholder="0.00">
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Pemakaian</label>
            <select class="form-select" id="dreqVerifyUsagePurpose">
              <?php foreach ($usagePurposeOptions as $option): ?>
                <option value="<?php echo html_escape((string)($option['value'] ?? '')); ?>"><?php echo html_escape((string)($option['label'] ?? $option['value'] ?? '')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if ($showVendorColumn): ?>
          <div class="col-md-6">
            <label class="form-label mb-1">Vendor PO</label>
            <div class="dreq-vendor-wrap">
              <select class="form-select" id="dreqVerifyVendor"></select>
              <button type="button" class="btn btn-outline-primary dreq-vendor-add" id="btnDreqVerifyVendorAdd" title="Tambah vendor baru">+</button>
            </div>
          </div>
          <?php endif; ?>
          <div class="col-12">
            <label class="form-label mb-1">Catatan Line</label>
            <input type="text" class="form-control" id="dreqVerifyNotes" placeholder="Opsional">
          </div>
        </div>
        <div class="dreq-draft-summary mt-3">
          <div class="small text-muted mb-1">Preview hasil review</div>
          <div class="fw-semibold" id="dreqVerifyRouteLabel">Pilih mode input dan qty request dulu.</div>
          <div class="small text-muted mt-1" id="dreqVerifyRouteNote">Route SR/PO dan kebutuhan vendor akan dihitung dari data review ini.</div>
        </div>
        <?php if ($canVerify) $this->load->view('procurement/_stock_review_panel', ['stock_review_csrf'=>$stock_review_csrf ?? '', 'request_id'=>$requestId, 'can_verify'=>true, 'line_review'=>true]); ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btnApplyDreqVerify">Verifikasi &amp; Buat SR / PO Rincian Ini</button>
      </div>
    </div>
  </div>
</div>

<?php $this->load->view('purchase/_vendor_quick_create_modal'); ?>

<div class="modal fade" id="dreqDecisionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="dreqDecisionTitle">Keputusan Rincian</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body"><div id="dreqDecisionName" class="fw-semibold mb-3"></div>
      <label for="dreqDecisionReason" class="form-label">Alasan</label><textarea id="dreqDecisionReason" class="form-control" rows="3" maxlength="1000"></textarea>
      <div id="dreqDecisionError" role="alert" class="text-danger small mt-2"></div>
    </div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="button" id="dreqDecisionSave" class="btn btn-danger">Simpan keputusan</button></div>
  </div></div>
</div>

<script>
(function () {
  'use strict';

  var searchUrl = <?php echo json_encode(site_url('procurement/division-po-sr/profile-search')); ?>;
  var catalogSearchUrl = <?php echo json_encode(site_url('purchase/catalog/search')); ?>;
  var destinationGuardMap = <?php echo json_encode($destinationGuardMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || {};
  var destinationOptionMeta = <?php echo json_encode($destinationOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || [];
  var vendorOptions = <?php echo json_encode($vendorOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || [];
  var usagePurposeOptions = <?php echo json_encode($usagePurposeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || [];
  var canAssignVendor = <?php echo $showVendorColumn ? 'true' : 'false'; ?>;
  var isVerifyMode = <?php echo $canVerify ? 'true' : 'false'; ?>;
  var lineColumnCount = <?php echo (int)$lineColumnCount; ?>;
  var requestLines = <?php echo json_encode($initialLines, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || [];
  var draftModalEl = document.getElementById('dreqDraftModal');
  var draftModal = (draftModalEl && window.bootstrap && window.bootstrap.Modal) ? window.bootstrap.Modal.getOrCreateInstance(draftModalEl) : null;
  var draftTitleEl = document.getElementById('dreqDraftTitle');
  var draftSubtitleEl = document.getElementById('dreqDraftSubtitle');
  var draftSourceNoteEl = document.getElementById('dreqDraftSourceNote');
  var draftKindEl = document.getElementById('dreqDraftKind');
  var draftBrandEl = document.getElementById('dreqDraftBrand');
  var draftDescriptionEl = document.getElementById('dreqDraftDescription');
  var draftBuyUomEl = document.getElementById('dreqDraftBuyUom');
  var draftContentUomEl = document.getElementById('dreqDraftContentUom');
  var draftPackEl = document.getElementById('dreqDraftPack');
  var draftStockEl = document.getElementById('dreqDraftStock');
  var draftModeEl = document.getElementById('dreqDraftRequestMode');
  var draftQtyEl = document.getElementById('dreqDraftQty');
  var draftExpiredEl = document.getElementById('dreqDraftExpired');
  var draftPriceEl = document.getElementById('dreqDraftPrice');
  var draftUsagePurposeEl = document.getElementById('dreqDraftUsagePurpose');
  var draftNotesEl = document.getElementById('dreqDraftNotes');
  var draftRouteLabelEl = document.getElementById('dreqDraftRouteLabel');
  var draftRouteNoteEl = document.getElementById('dreqDraftRouteNote');
  var draftAlertEl = document.getElementById('dreqDraftAlert');
  var draftApplyBtn = document.getElementById('btnApplyDreqDraft');
  var pendingDraftRow = null;
  var verifyModalEl = document.getElementById('dreqVerifyLineModal');
  var verifyTitleEl = document.getElementById('dreqVerifyTitle');
  var verifySubtitleEl = document.getElementById('dreqVerifySubtitle');
  var verifyNoteEl = document.getElementById('dreqVerifyNote');
  var verifyAlertEl = document.getElementById('dreqVerifyAlert');
  var verifyKindEl = document.getElementById('dreqVerifyKind');
  var verifyBrandEl = document.getElementById('dreqVerifyBrand');
  var verifyDescriptionEl = document.getElementById('dreqVerifyDescription');
  var verifyBuyUomEl = document.getElementById('dreqVerifyBuyUom');
  var verifyContentUomEl = document.getElementById('dreqVerifyContentUom');
  var verifyPackEl = document.getElementById('dreqVerifyPack');
  var verifyStockEl = document.getElementById('dreqVerifyStock');
  var verifyModeEl = document.getElementById('dreqVerifyRequestMode');
  var verifyQtyEl = document.getElementById('dreqVerifyQty');
  var verifyPoQtyEl = document.getElementById('dreqVerifyPoQty');
  var verifyPriceEl = document.getElementById('dreqVerifyPrice');
  var verifyUsagePurposeEl = document.getElementById('dreqVerifyUsagePurpose');
  var verifyVendorEl = document.getElementById('dreqVerifyVendor');
  var verifyNotesEl = document.getElementById('dreqVerifyNotes');
  var verifySuggestionListEl = document.getElementById('dreqVerifySuggestionList');
  var verifyRouteLabelEl = document.getElementById('dreqVerifyRouteLabel');
  var verifyRouteNoteEl = document.getElementById('dreqVerifyRouteNote');
  var verifySuggestBtn = document.getElementById('btnDreqVerifySuggest');
  var verifyVendorAddBtn = document.getElementById('btnDreqVerifyVendorAdd');
  var verifyApplyBtn = document.getElementById('btnApplyDreqVerify');
  var activeVerifyLineIdx = -1;
  var decisionIdx = -1, decisionAction = '', savingDecision = false;
  var decisionBaseUrl = <?= json_encode(site_url('procurement/division-po-sr/'.$requestId.'/lines/')) ?>;
  var decisionCsrf = <?= json_encode((string)($stock_review_csrf ?? '')) ?>;
  function cleanLine(row) {
    var payload = Object.assign({},row);
    delete payload.catalog_suggestions; delete payload.suggestion_loading; delete payload.suggestion_query;
    return payload;
  }
  window.DivisionLineReview = {
    getLines: function () { var row = buildVerifyModalRow(); return row ? [cleanLine(row)] : requestLines.map(cleanLine); },
    getIndex: function () { return activeVerifyLineIdx; }
  };
  async function saveDecision(row, action, extra) {
    if (savingDecision) throw new Error('Keputusan sedang disimpan.');
    savingDecision = true;
    try {
      var response = await fetch(decisionBaseUrl + row.id + '/decision', {
        method:'POST', credentials:'same-origin',
        headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-Procurement-Mutation-Csrf':decisionCsrf},
        body:JSON.stringify(Object.assign({action:action},extra || {}))
      });
      var result;
      try { result = await response.json(); } catch (_) { throw new Error('Respons server tidak terbaca. Muat ulang untuk memeriksa status rincian.'); }
      if (!response.ok || !result.ok) throw new Error(result.message || 'Keputusan gagal disimpan.');
      window.location.reload();
    } finally { savingDecision = false; }
  }

  function esc(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char];
    });
  }

  function num(value) {
    var parsed = Number(value || 0);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function roundQty(value) { return Math.round(num(value) * 10000) / 10000; }

  function quantityInput(value) { return String(roundQty(value)); }

  function round2(value) {
    return Math.round(num(value) * 100) / 100;
  }

  function selectedRequestMode(value) {
    var mode = String(value || '').toUpperCase().trim();
    return mode === 'BUY' || mode === 'CONTENT' ? mode : '';
  }

  function requestMode(value) {
    return selectedRequestMode(value) === 'CONTENT' ? 'CONTENT' : 'BUY';
  }

  function normalizeUsagePurpose(value) {
    return String(value || '').toUpperCase().trim() === 'OPERASIONAL' ? 'OPERASIONAL' : 'BAHAN_BAKU';
  }

  function usagePurposeLabel(value) {
    return normalizeUsagePurpose(value) === 'OPERASIONAL' ? 'Kebutuhan Operasional' : 'Persediaan Produksi';
  }

  function defaultUsagePurposeForRow(row) {
    var lineKind = String(row && row.line_kind || '').toUpperCase().trim();
    return lineKind === 'MATERIAL' || num(row && row.material_id) > 0
      ? 'BAHAN_BAKU'
      : 'OPERASIONAL';
  }

  function resolvedUsagePurposeForRow(row) {
    if (row && String(row.usage_purpose || '').trim() !== '') {
      return normalizeUsagePurpose(row.usage_purpose);
    }
    return defaultUsagePurposeForRow(row);
  }

  function canonicalLineKind(row) {
    if (num(row && row.item_id) > 0) {
      return 'ITEM';
    }
    if (num(row && row.material_id) > 0) {
      return 'MATERIAL';
    }
    var legacyKind = String(row && row.line_kind || '').toUpperCase().trim();
    return legacyKind === 'MATERIAL' ? 'MATERIAL' : 'ITEM';
  }

  function effectiveLineKind(row) {
    var usagePurpose = normalizeUsagePurpose(row && (row.usage_purpose || row.default_usage_purpose));
    if (usagePurpose !== 'OPERASIONAL' && num(row && row.material_id) > 0) {
      return 'MATERIAL';
    }
    return canonicalLineKind(row);
  }

  function effectiveLineKindLabel(row) {
    return effectiveLineKind(row) === 'MATERIAL' ? 'Stok Material' : 'Stok Item';
  }

  function renderUsagePurposeOptions(selectedValue) {
    var normalized = normalizeUsagePurpose(selectedValue);
    var html = '';
    var rows = usagePurposeOptions.length ? usagePurposeOptions : [
      { value: 'BAHAN_BAKU', label: 'Persediaan Produksi' },
      { value: 'OPERASIONAL', label: 'Kebutuhan Operasional' }
    ];
    rows.forEach(function (option) {
      var value = normalizeUsagePurpose(option.value);
      html += '<option value="' + esc(value) + '"' + (value === normalized ? ' selected' : '') + '>' + esc(option.label || value) + '</option>';
    });
    return html;
  }

  function defaultRequestModeForRow(row) {
    var plan = routePlan(row || {});
    return (plan.label === 'SR' || plan.label === 'SR + PO') ? 'BUY' : '';
  }

  function hasRequestModeSelection(row) {
    return selectedRequestMode(row && row.request_uom_mode) !== '';
  }

  function fixed2(value) {
    return round2(value).toFixed(2);
  }

  function qtyWithCode(value, code) {
    return num(value).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:4}) + ' ' + String(code || '-').trim();
  }

  function normalizeToken(value) {
    return String(value || '').trim().toUpperCase();
  }

  function flash(type, message) {
    var el = document.getElementById('dreqFormAlert');
    if (!el) {
      return;
    }
    el.innerHTML = '<div class="alert alert-' + type + ' py-2 mb-3">' + message + '</div>';
  }

  function setSearchMeta(message) {
    var el = document.getElementById('dreqSearchMeta');
    if (el) {
      el.textContent = message || '';
    }
  }

  function toggleManualCard(show, defaultName) {
    var card = document.getElementById('manualEntryCard');
    if (!card) {
      return;
    }
    card.style.display = show ? 'block' : 'none';
    if (show) {
      var nameInput = document.getElementById('manualNameInput');
      if (nameInput && !String(nameInput.value || '').trim()) {
        nameInput.value = defaultName || '';
      }
    }
  }

  function lineKey(row) {
    return [row.profile_key || '', row.profile_name || '', row.item_id || 0, row.material_id || 0, row.buy_uom_id || 0, row.content_uom_id || 0].join('|');
  }

  function hasExistingLineKey(key) {
    for (var i = 0; i < requestLines.length; i++) {
      if (lineKey(requestLines[i]) === key) {
        return true;
      }
    }
    return false;
  }

  function normalizeRow(row) {
    var contentPerBuy = num(row.profile_content_per_buy);
    if (contentPerBuy <= 0) {
      contentPerBuy = 1;
    }
    var qtyContentBalance = num(row.qty_content_balance || row.qty_content_available_snapshot);
    var qtyBuyBalance = num(row.qty_buy_balance);
    if (qtyBuyBalance <= 0 && qtyContentBalance > 0) {
      qtyBuyBalance = qtyContentBalance / contentPerBuy;
    }
    var normalized = {
      id: num(row.id), line_no: num(row.line_no),
      review_status: row.review_status || 'PENDING', review_notes: row.review_notes || '',
      line_kind: canonicalLineKind(row),
      item_id: num(row.item_id) > 0 ? num(row.item_id) : null,
      material_id: num(row.material_id) > 0 ? num(row.material_id) : null,
      profile_key: row.profile_key || '',
      profile_name: row.profile_name || '',
      profile_brand: row.profile_brand || '',
      profile_description: row.profile_description || '',
      profile_expired_date: row.profile_expired_date || '',
      expiry_policy: row.expiry_policy || ((row.required_expiry_date || row.profile_expired_date) ? 'EXACT_DATE' : 'NONE'),
      required_expiry_date: row.required_expiry_date || row.profile_expired_date || '',
      min_remaining_days: num(row.min_remaining_days || 0) || null,
      buy_uom_id: num(row.buy_uom_id),
      content_uom_id: num(row.content_uom_id),
      profile_content_per_buy: contentPerBuy,
      profile_buy_uom_code: row.profile_buy_uom_code || '',
      profile_content_uom_code: row.profile_content_uom_code || '',
      request_uom_mode: selectedRequestMode(row.request_uom_mode || defaultRequestModeForRow(row)),
      vendor_id: num(row.vendor_id) > 0 ? num(row.vendor_id) : null,
      vendor_name: row.vendor_name || '',
      qty_buy_requested: roundQty(num(row.qty_buy_requested) > 0 ? num(row.qty_buy_requested) : 0),
      qty_content_requested: roundQty(num(row.qty_content_requested) > 0 ? num(row.qty_content_requested) : 0),
      qty_buy_po_requested: roundQty(num(row.qty_buy_po_requested)),
      qty_content_po_requested: roundQty(num(row.qty_content_po_requested)),
      qty_buy_balance: roundQty(qtyBuyBalance),
      qty_content_balance: roundQty(qtyContentBalance),
      standard_price: round2(num(row.standard_price || 0)),
      last_unit_price: round2(num(row.last_unit_price || row.standard_price || 0)),
      last_purchase_date: row.last_purchase_date || '',
      catalog_id: num(row.catalog_id) > 0 ? num(row.catalog_id) : null,
      estimated_unit_price: round2(num(row.estimated_unit_price || row.last_unit_price || row.standard_price || 0)),
      default_usage_purpose: defaultUsagePurposeForRow(row),
      usage_purpose: resolvedUsagePurposeForRow(row),
      catalog_suggestions: Array.isArray(row.catalog_suggestions) ? row.catalog_suggestions : [],
      suggestion_loading: !!row.suggestion_loading,
      suggestion_query: row.suggestion_query || '',
      source_type: row.source_type || row.search_source || (num(row.qty_content_balance || row.qty_content_available_snapshot) > 0 ? 'WAREHOUSE' : 'CATALOG'),
      notes: row.notes || '',
      line_reviewed: isVerifyMode ? !!row.line_reviewed : true
    };

    if (!hasRequestModeSelection(normalized)) {
      normalized.qty_buy_po_requested = 0;
      normalized.qty_content_po_requested = 0;
    } else if (String(normalized.source_type || '').toUpperCase() !== 'WAREHOUSE') {
      normalized.qty_buy_po_requested = roundQty(normalized.qty_buy_requested);
      normalized.qty_content_po_requested = roundQty(normalized.qty_content_requested);
    } else if (normalized.qty_buy_po_requested <= 0 && normalized.qty_content_po_requested <= 0) {
      normalized.qty_content_po_requested = roundQty(Math.max(0, normalized.qty_content_requested - Math.min(normalized.qty_content_requested, normalized.qty_content_balance)));
      normalized.qty_buy_po_requested = roundQty(normalized.qty_content_po_requested / contentPerBuy);
    } else if (normalized.qty_content_po_requested <= 0 && normalized.qty_buy_po_requested > 0) {
      normalized.qty_content_po_requested = roundQty(normalized.qty_buy_po_requested * contentPerBuy);
    }

    return normalized;
  }

  function requestUnitCode(row) {
    return requestMode(row && row.request_uom_mode) === 'CONTENT'
      ? (row.profile_content_uom_code || row.profile_buy_uom_code || '-')
      : (row.profile_buy_uom_code || row.profile_content_uom_code || '-');
  }

  function requestQtyValue(row) {
    return requestMode(row && row.request_uom_mode) === 'CONTENT'
      ? roundQty(num(row.qty_content_requested))
      : roundQty(num(row.qty_buy_requested));
  }

  function requestPoQtyValue(row) {
    return requestMode(row && row.request_uom_mode) === 'CONTENT'
      ? roundQty(num(row.qty_content_po_requested))
      : roundQty(num(row.qty_buy_po_requested));
  }

  function requestPlanQty(plan, row, target) {
    var mode = requestMode(row && row.request_uom_mode);
    if (target === 'SR') {
      return mode === 'CONTENT' ? roundQty(num(plan.toSrContent)) : roundQty(num(plan.toSrBuy));
    }
    return mode === 'CONTENT' ? roundQty(num(plan.toPoContent)) : roundQty(num(plan.toPoBuy));
  }

  function requestSecondarySummary(row) {
    var mode = requestMode(row && row.request_uom_mode);
    var primaryCode = requestUnitCode(row);
    var secondaryCode = mode === 'CONTENT'
      ? String(row.profile_buy_uom_code || '').trim()
      : String(row.profile_content_uom_code || '').trim();
    if (!secondaryCode || secondaryCode === primaryCode) {
      return '';
    }
    var secondaryValue = mode === 'CONTENT'
      ? roundQty(num(row.qty_buy_requested))
      : roundQty(num(row.qty_content_requested));
    return qtyWithCode(secondaryValue, secondaryCode);
  }

  function poSecondarySummary(row) {
    var mode = requestMode(row && row.request_uom_mode);
    var primaryCode = requestUnitCode(row);
    var secondaryCode = mode === 'CONTENT'
      ? String(row.profile_buy_uom_code || '').trim()
      : String(row.profile_content_uom_code || '').trim();
    if (!secondaryCode || secondaryCode === primaryCode) {
      return '';
    }
    var secondaryValue = mode === 'CONTENT'
      ? roundQty(num(row.qty_buy_po_requested))
      : roundQty(num(row.qty_content_po_requested));
    return qtyWithCode(secondaryValue, secondaryCode);
  }

  function requestInputCaption(row, plan) {
    if (!hasRequestModeSelection(row)) {
      return 'Pilih dulu mode input: per beli atau per isi.';
    }
    var unitCode = requestUnitCode(row);
    if (num(plan && plan.toSrContent) <= 0.00001 && num(plan && plan.toPoContent) > 0.00001) {
      return 'Input kebutuhan PO langsung dalam ' + unitCode + '.';
    }
    return 'Input kebutuhan total dalam ' + unitCode + '.';
  }

  function vendorNameById(vendorId) {
    var id = num(vendorId);
    if (id <= 0) {
      return '';
    }
    for (var i = 0; i < vendorOptions.length; i++) {
      if (num(vendorOptions[i] && vendorOptions[i].id) === id) {
        return String(vendorOptions[i].vendor_name || vendorOptions[i].vendor_code || '');
      }
    }
    return '';
  }

  function vendorLabel(vendor) {
    var code = String(vendor && vendor.vendor_code ? vendor.vendor_code : '').trim();
    var name = String(vendor && vendor.vendor_name ? vendor.vendor_name : '').trim();
    if (code && name) {
      return code + ' - ' + name;
    }
    return name || code || ('Vendor #' + String(vendor && vendor.id ? vendor.id : ''));
  }

  function upsertVendorOption(vendor) {
    var vendorId = num(vendor && vendor.id);
    if (vendorId <= 0) {
      return;
    }

    var normalized = {
      id: vendorId,
      vendor_code: String(vendor && vendor.vendor_code ? vendor.vendor_code : ''),
      vendor_name: String(vendor && vendor.vendor_name ? vendor.vendor_name : '')
    };
    var replaced = false;
    for (var i = 0; i < vendorOptions.length; i++) {
      if (num(vendorOptions[i] && vendorOptions[i].id) === vendorId) {
        vendorOptions[i] = normalized;
        replaced = true;
        break;
      }
    }
    if (!replaced) {
      vendorOptions.push(normalized);
    }
    vendorOptions.sort(function (left, right) {
      return vendorLabel(left).localeCompare(vendorLabel(right));
    });
  }

  function requiresVendor(row, plan) {
    return canAssignVendor && num(plan && plan.toPoContent) > 0.00001;
  }

  function vendorInputCaption(row, plan) {
    if (!canAssignVendor) {
      return '';
    }
    if (!requiresVendor(row, plan)) {
      return 'Tidak diperlukan bila line hanya ke SR.';
    }
    return 'Wajib dipilih Purchase untuk line yang masuk PO.';
  }

  function vendorVerifyCaption(row, plan) {
    if (!canAssignVendor) {
      return '';
    }
    return requiresVendor(row, plan) ? 'PO wajib' : 'SR saja';
  }

  function renderVendorOptions(selectedId) {
    var html = ['<option value="">Pilih vendor</option>'];
    vendorOptions.forEach(function (vendor) {
      var vendorId = num(vendor && vendor.id);
      var selected = vendorId > 0 && vendorId === num(selectedId) ? ' selected' : '';
      var label = vendorLabel(vendor);
      html.push('<option value="' + vendorId + '"' + selected + '>' + esc(label) + '</option>');
    });
    return html.join('');
  }

  function poInputCaption(row, plan) {
    if (!hasRequestModeSelection(row)) {
      return 'Mode input belum dipilih.';
    }
    var unitCode = requestUnitCode(row);
    if (num(plan && plan.toSrContent) <= 0.00001 && num(plan && plan.toPoContent) > 0.00001) {
      return 'Seluruh qty akan dibuat PO dalam ' + unitCode + '.';
    }
    return 'Porsi shortage yang masuk PO dalam ' + unitCode + '.';
  }

  function sourceLabel(sourceType) {
    if (sourceType === 'WAREHOUSE') {
      return 'Gudang';
    }
    if (sourceType === 'MASTER') {
      return 'Master';
    }
    if (sourceType === 'MANUAL') {
      return 'Manual';
    }
    return 'Katalog';
  }

  function packSummary(row) {
    var factor = round2(num(row && row.profile_content_per_buy) || 1);
    var buyCode = String(row && row.profile_buy_uom_code || '').trim() || '-';
    var contentCode = String(row && row.profile_content_uom_code || '').trim() || '-';
    return '1 ' + buyCode + ' = ' + fixed2(factor) + ' ' + contentCode;
  }

  function locationLabel(destinationType) {
    var value = String(destinationType || '').toUpperCase();
    if (value.indexOf('EVENT') !== -1) {
      return 'Event';
    }
    if (value === 'BAR' || value === 'KITCHEN' || value === 'OFFICE') {
      return 'Reguler';
    }
    return value || '-';
  }

  function setDraftAlert(type, message) {
    if (!draftAlertEl) {
      return;
    }
    if (!message) {
      draftAlertEl.innerHTML = '';
      return;
    }
    draftAlertEl.innerHTML = '<div class="alert alert-' + type + ' py-2 mb-3">' + message + '</div>';
  }

  function removeDraftBackdrop() {
    document.querySelectorAll('.modal-backdrop.dreq-fallback-backdrop').forEach(function (el) {
      if (el && el.parentNode) {
        el.parentNode.removeChild(el);
      }
    });
  }

  function showModalElement(modalEl) {
    if (!modalEl) {
      return;
    }
    try {
      if (window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        return;
      }
    } catch (error) {}
    try {
      if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
        window.jQuery(modalEl).modal('show');
        return;
      }
    } catch (error) {}

    modalEl.style.display = 'block';
    modalEl.classList.add('show');
    modalEl.removeAttribute('aria-hidden');
    modalEl.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');
    document.body.style.overflow = 'hidden';
    removeDraftBackdrop();
    var backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade show dreq-fallback-backdrop';
    document.body.appendChild(backdrop);
  }

  function hideModalElement(modalEl, onManualHide) {
    if (!modalEl) {
      if (typeof onManualHide === 'function') {
        onManualHide();
      }
      return;
    }
    try {
      if (window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        return;
      }
    } catch (error) {}
    try {
      if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
        window.jQuery(modalEl).modal('hide');
        return;
      }
    } catch (error) {}

    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.removeAttribute('aria-modal');
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    removeDraftBackdrop();
    if (typeof onManualHide === 'function') {
      onManualHide();
    }
  }

  function showDraftModal() {
    showModalElement(draftModalEl);
  }

  function hideDraftModal() {
    hideModalElement(draftModalEl, clearDraftState);
  }

  function clearDraftState() {
    pendingDraftRow = null;
    setDraftAlert('', '');
    if (draftTitleEl) {
      draftTitleEl.textContent = '-';
    }
    if (draftSubtitleEl) {
      draftSubtitleEl.textContent = 'Klik hasil pencarian untuk isi detail dulu sebelum line masuk ke tabel pengajuan.';
    }
    if (draftSourceNoteEl) {
      draftSourceNoteEl.textContent = 'Baris belum ditambahkan.';
    }
    if (draftKindEl) {
      draftKindEl.value = '';
    }
    if (draftBrandEl) {
      draftBrandEl.value = '';
    }
    if (draftDescriptionEl) {
      draftDescriptionEl.value = '';
    }
    if (draftBuyUomEl) {
      draftBuyUomEl.value = '';
    }
    if (draftContentUomEl) {
      draftContentUomEl.value = '';
    }
    if (draftPackEl) {
      draftPackEl.value = '';
    }
    if (draftStockEl) {
      draftStockEl.value = '';
    }
    if (draftModeEl) {
      draftModeEl.value = '';
    }
    if (draftQtyEl) {
      draftQtyEl.value = '';
    }
    if (draftExpiredEl) {
      draftExpiredEl.value = '';
    }
    if (draftPriceEl) {
      draftPriceEl.value = '';
    }
    if (draftNotesEl) {
      draftNotesEl.value = '';
    }
    if (draftRouteLabelEl) {
      draftRouteLabelEl.textContent = 'Pilih mode input dan qty request dulu.';
    }
    if (draftRouteNoteEl) {
      draftRouteNoteEl.textContent = 'Baris baru akan ditambahkan setelah data wajib diisi.';
    }
  }

  function draftStockSummary(row) {
    var sourceType = String(row && row.source_type || '').toUpperCase();
    if (sourceType !== 'WAREHOUSE') {
      return 'Fallback katalog/manual';
    }
    return fixed2(num(row && row.qty_buy_balance)) + ' ' + String(row && row.profile_buy_uom_code || '-').trim()
      + ' | ' + fixed2(num(row && row.qty_content_balance)) + ' ' + String(row && row.profile_content_uom_code || '-').trim();
  }

  function reviewBadgeHtml(row) {
    if (!isVerifyMode) {
      return '';
    }
    return row && row.line_reviewed
      ? '<span class="badge bg-success dreq-review-badge">Sudah direview</span>'
      : '<span class="badge bg-warning text-dark dreq-review-badge">Belum direview</span>';
  }

  function setVerifyAlert(type, message) {
    if (!verifyAlertEl) {
      return;
    }
    if (!message) {
      verifyAlertEl.innerHTML = '';
      return;
    }
    verifyAlertEl.innerHTML = '<div class="alert alert-' + type + ' py-2 mb-3">' + message + '</div>';
  }

  function clearVerifyState() {
    activeVerifyLineIdx = -1;
    setVerifyAlert('', '');
    if (verifyTitleEl) {
      verifyTitleEl.textContent = '-';
    }
    if (verifySubtitleEl) {
      verifySubtitleEl.textContent = 'Verifikasi line satu per satu sebelum bentuk SR atau PO hasil review.';
    }
    if (verifyNoteEl) {
      verifyNoteEl.textContent = 'Line belum direview.';
    }
    if (verifyKindEl) {
      verifyKindEl.value = '';
    }
    if (verifyBrandEl) {
      verifyBrandEl.value = '';
    }
    if (verifyDescriptionEl) {
      verifyDescriptionEl.value = '';
    }
    if (verifyBuyUomEl) {
      verifyBuyUomEl.value = '';
    }
    if (verifyContentUomEl) {
      verifyContentUomEl.value = '';
    }
    if (verifyPackEl) {
      verifyPackEl.value = '';
    }
    if (verifyStockEl) {
      verifyStockEl.value = '';
    }
    if (verifyModeEl) {
      verifyModeEl.value = '';
    }
    if (verifyQtyEl) {
      verifyQtyEl.value = '';
    }
    if (verifyPoQtyEl) {
      verifyPoQtyEl.value = '';
      verifyPoQtyEl.disabled = false;
    }
    if (verifyPriceEl) {
      verifyPriceEl.value = '';
    }
    if (verifyVendorEl) {
      verifyVendorEl.innerHTML = renderVendorOptions('');
      verifyVendorEl.disabled = false;
    }
    if (verifyNotesEl) {
      verifyNotesEl.value = '';
    }
    if (verifySuggestionListEl) {
      verifySuggestionListEl.innerHTML = '<div class="small text-muted">Belum ada saran. Klik Cari Saran untuk melihat kandidat buyer.</div>';
    }
    if (verifyRouteLabelEl) {
      verifyRouteLabelEl.textContent = 'Pilih mode input dan qty request dulu.';
    }
    if (verifyRouteNoteEl) {
      verifyRouteNoteEl.textContent = 'Route SR/PO dan kebutuhan vendor akan dihitung dari data review ini.';
    }
  }

  function hideVerifyModal() {
    hideModalElement(verifyModalEl, clearVerifyState);
  }

  function showVerifyModal() {
    showModalElement(verifyModalEl);
  }

  function markLineNeedsReview(idx) {
    if (!isVerifyMode || idx < 0 || !requestLines[idx]) {
      return;
    }
    requestLines[idx].line_reviewed = false;
  }

  function renderVerifySuggestionList() {
    if (!verifySuggestionListEl) {
      return;
    }
    if (activeVerifyLineIdx < 0 || !requestLines[activeVerifyLineIdx]) {
      verifySuggestionListEl.innerHTML = '<div class="small text-muted">Belum ada saran. Klik Cari Saran untuk melihat kandidat buyer.</div>';
      return;
    }
    var row = requestLines[activeVerifyLineIdx];
    if (row.suggestion_loading) {
      verifySuggestionListEl.innerHTML = '<div class="small text-muted">Memuat saran...</div>';
      return;
    }
    if (Array.isArray(row.catalog_suggestions) && row.catalog_suggestions.length) {
      verifySuggestionListEl.innerHTML = renderProfileSuggestionButtons(activeVerifyLineIdx, row.catalog_suggestions);
      return;
    }
    verifySuggestionListEl.innerHTML = '<div class="small text-muted">Belum ada saran. Klik Cari Saran untuk melihat kandidat buyer.</div>';
  }

  function buildVerifyModalRow() {
    if (activeVerifyLineIdx < 0 || !requestLines[activeVerifyLineIdx]) {
      return null;
    }
    var row = normalizeRow(Object.assign({}, requestLines[activeVerifyLineIdx], {
      profile_brand: verifyBrandEl ? String(verifyBrandEl.value || '').trim() : '',
      profile_description: verifyDescriptionEl ? String(verifyDescriptionEl.value || '').trim() : '',
      request_uom_mode: verifyModeEl ? selectedRequestMode(verifyModeEl.value) : '',
      vendor_id: verifyVendorEl ? (num(verifyVendorEl.value) > 0 ? num(verifyVendorEl.value) : null) : null,
      estimated_unit_price: verifyPriceEl ? round2(Math.max(0, num(verifyPriceEl.value))) : 0,
      usage_purpose: verifyUsagePurposeEl ? normalizeUsagePurpose(verifyUsagePurposeEl.value) : normalizeUsagePurpose(requestLines[activeVerifyLineIdx].usage_purpose),
      notes: verifyNotesEl ? String(verifyNotesEl.value || '').trim() : '',
      line_reviewed: false
    }));
    if (hasRequestModeSelection(row)) {
      syncRowTotalsFromRequest(row, verifyQtyEl ? verifyQtyEl.value : 0);
      if (verifyPoQtyEl && !verifyPoQtyEl.disabled && String(verifyPoQtyEl.value || '').trim() !== '') {
        syncRowTotalsFromPo(row, verifyPoQtyEl.value, routePlan(row));
      }
    } else {
      row.qty_buy_requested = 0;
      row.qty_content_requested = 0;
      row.qty_buy_po_requested = 0;
      row.qty_content_po_requested = 0;
    }
    row.vendor_name = vendorNameById(row.vendor_id);
    return row;
  }

  function updateVerifyModalPreview() {
    var row = buildVerifyModalRow();
    if (!row) {
      return;
    }
    var plan = routePlan(row);
    if (verifyPoQtyEl) {
      verifyPoQtyEl.disabled = !(String(row.source_type || '').toUpperCase() === 'WAREHOUSE' && num(row.qty_content_balance) > 0.00001 && hasRequestModeSelection(row));
      if (verifyPoQtyEl.disabled) {
        verifyPoQtyEl.value = hasRequestModeSelection(row) ? quantityInput(requestPoQtyValue(row)) : '';
      }
    }
    if (verifyVendorEl) {
      verifyVendorEl.disabled = !requiresVendor(row, plan);
      if (verifyVendorEl.disabled) {
        verifyVendorEl.value = '';
      }
    }
    if (!hasRequestModeSelection(row)) {
      if (verifyRouteLabelEl) {
        verifyRouteLabelEl.textContent = 'Pilih mode input dulu.';
      }
      if (verifyRouteNoteEl) {
        verifyRouteNoteEl.textContent = 'Line ini belum bisa diverifikasi sebelum mode input dipilih.';
      }
      return;
    }
    if (num(requestQtyValue(row)) <= 0) {
      if (verifyRouteLabelEl) {
        verifyRouteLabelEl.textContent = 'Isi qty request lebih dulu.';
      }
      if (verifyRouteNoteEl) {
        verifyRouteNoteEl.textContent = 'Qty request dipakai untuk hitung split SR dan PO.';
      }
      return;
    }
    if (verifyRouteLabelEl) {
      verifyRouteLabelEl.textContent = 'Route: ' + routeLabel(row) + ' | Request ' + qtyWithCode(requestQtyValue(row), requestUnitCode(row));
    }
    if (verifyRouteNoteEl) {
      var note = routeNote(row);
      if (requiresVendor(row, plan) && num(row.vendor_id) <= 0) {
        note += ' Vendor PO wajib dipilih.';
      }
      verifyRouteNoteEl.textContent = note;
    }
  }

  function populateVerifyModal(idx) {
    if (idx < 0 || !requestLines[idx]) {
      return;
    }
    activeVerifyLineIdx = idx;
    var row = normalizeRow(requestLines[idx]);
    requestLines[idx] = row;
    setVerifyAlert('', '');
    if (verifyTitleEl) {
      verifyTitleEl.textContent = row.profile_name || '-';
    }
    if (verifySubtitleEl) {
      verifySubtitleEl.textContent = sourceLabel(String(row.source_type || '').toUpperCase()) + ' | ' + packSummary(row);
    }
    if (verifyNoteEl) {
      verifyNoteEl.textContent = 'Keputusan langsung disimpan untuk rincian ini. SR/PO dibuat setelah verifikasi berhasil.';
    }
    if (verifyKindEl) {
      verifyKindEl.value = effectiveLineKindLabel(row);
    }
    if (verifyBrandEl) {
      verifyBrandEl.value = row.profile_brand || '';
    }
    if (verifyDescriptionEl) {
      verifyDescriptionEl.value = row.profile_description || '';
    }
    if (verifyBuyUomEl) {
      verifyBuyUomEl.value = row.profile_buy_uom_code || '-';
    }
    if (verifyContentUomEl) {
      verifyContentUomEl.value = row.profile_content_uom_code || '-';
    }
    if (verifyPackEl) {
      verifyPackEl.value = packSummary(row);
    }
    if (verifyStockEl) {
      verifyStockEl.value = draftStockSummary(row);
    }
    if (verifyModeEl) {
      verifyModeEl.value = selectedRequestMode(row.request_uom_mode);
    }
    if (verifyQtyEl) {
      verifyQtyEl.value = hasRequestModeSelection(row) ? quantityInput(requestQtyValue(row)) : '';
    }
    if (verifyPoQtyEl) {
      verifyPoQtyEl.value = hasRequestModeSelection(row) ? quantityInput(requestPoQtyValue(row)) : '';
    }
    if (verifyPriceEl) {
      verifyPriceEl.value = fixed2(row.estimated_unit_price || 0);
    }
    if (verifyUsagePurposeEl) {
      verifyUsagePurposeEl.value = normalizeUsagePurpose(row.usage_purpose || row.default_usage_purpose);
    }
    if (verifyVendorEl) {
      verifyVendorEl.innerHTML = renderVendorOptions(row.vendor_id || '');
      verifyVendorEl.value = row.vendor_id ? String(row.vendor_id) : '';
    }
    if (verifyNotesEl) {
      verifyNotesEl.value = row.notes || '';
    }
    renderVerifySuggestionList();
    updateVerifyModalPreview();
  }

  function openVerifyModal(idx) {
    if (!requestLines[idx] || requestLines[idx].review_status !== 'PENDING' || savingDecision) return;
    populateVerifyModal(idx);
    showVerifyModal();
    document.dispatchEvent(new CustomEvent('procurement-lines-changed'));
  }

  function buildDraftRow() {
    if (!pendingDraftRow) {
      return null;
    }
    var row = normalizeRow(Object.assign({}, pendingDraftRow, {
      profile_brand: draftBrandEl ? String(draftBrandEl.value || '').trim() : '',
      profile_description: draftDescriptionEl ? String(draftDescriptionEl.value || '').trim() : '',
      required_expiry_date: draftExpiredEl ? String(draftExpiredEl.value || '').trim() : '',
      expiry_policy: (draftExpiredEl && String(draftExpiredEl.value || '').trim() !== '') ? 'EXACT_DATE' : 'NONE',
      estimated_unit_price: draftPriceEl ? round2(Math.max(0, num(draftPriceEl.value))) : 0,
      usage_purpose: draftUsagePurposeEl ? normalizeUsagePurpose(draftUsagePurposeEl.value) : normalizeUsagePurpose((pendingDraftRow || {}).usage_purpose),
      notes: draftNotesEl ? String(draftNotesEl.value || '').trim() : '',
      request_uom_mode: draftModeEl ? selectedRequestMode(draftModeEl.value) : ''
    }));
    if (hasRequestModeSelection(row)) {
      syncRowTotalsFromRequest(row, draftQtyEl ? draftQtyEl.value : 0);
    } else {
      row.qty_buy_requested = 0;
      row.qty_content_requested = 0;
      row.qty_buy_po_requested = 0;
      row.qty_content_po_requested = 0;
    }
    return row;
  }

  function updateDraftPreview() {
    var row = buildDraftRow();
    if (!row) {
      return;
    }
    if (!hasRequestModeSelection(row)) {
      if (draftRouteLabelEl) {
        draftRouteLabelEl.textContent = 'Pilih mode input dulu.';
      }
      if (draftRouteNoteEl) {
        draftRouteNoteEl.textContent = 'Baru setelah itu sistem bisa menghitung arah SR/PO.';
      }
      return;
    }
    if (num(requestQtyValue(row)) <= 0) {
      if (draftRouteLabelEl) {
        draftRouteLabelEl.textContent = 'Isi qty request lebih dulu.';
      }
      if (draftRouteNoteEl) {
        draftRouteNoteEl.textContent = 'Qty dipakai untuk menghitung split SR dan PO.';
      }
      return;
    }
    if (draftRouteLabelEl) {
      draftRouteLabelEl.textContent = 'Route: ' + routeLabel(row) + ' | Qty ' + qtyWithCode(requestQtyValue(row), requestUnitCode(row));
    }
    if (draftRouteNoteEl) {
      draftRouteNoteEl.textContent = routeNote(row);
    }
  }

  function openDraftModal(row) {
    var normalized = normalizeRow(row || {});
    if (hasExistingLineKey(lineKey(normalized))) {
      flash('warning', 'Profile ini sudah ada di line pengajuan.');
      return;
    }
    pendingDraftRow = normalized;
    setDraftAlert('', '');
    if (draftTitleEl) {
      draftTitleEl.textContent = normalized.profile_name || '-';
    }
    if (draftSubtitleEl) {
      draftSubtitleEl.textContent = sourceLabel(String(normalized.source_type || '').toUpperCase()) + ' | ' + packSummary(normalized);
    }
    if (draftSourceNoteEl) {
      draftSourceNoteEl.textContent = String(normalized.source_type || '').toUpperCase() === 'WAREHOUSE'
        ? 'Stok gudang ditemukan. Isi kebutuhan total dulu sebelum line ditambahkan.'
        : 'Fallback katalog/master. Line ini akan cenderung masuk PO jika stok gudang tidak ada saat proses.';
    }
    if (draftKindEl) {
      draftKindEl.value = effectiveLineKindLabel(normalized);
    }
    if (draftBrandEl) {
      draftBrandEl.value = normalized.profile_brand || '';
    }
    if (draftDescriptionEl) {
      draftDescriptionEl.value = normalized.profile_description || '';
    }
    if (draftBuyUomEl) {
      draftBuyUomEl.value = normalized.profile_buy_uom_code || '-';
    }
    if (draftContentUomEl) {
      draftContentUomEl.value = normalized.profile_content_uom_code || '-';
    }
    if (draftPackEl) {
      draftPackEl.value = packSummary(normalized);
    }
    if (draftStockEl) {
      draftStockEl.value = draftStockSummary(normalized);
    }
    if (draftModeEl) {
      draftModeEl.value = selectedRequestMode(normalized.request_uom_mode || defaultRequestModeForRow(normalized));
    }
    if (draftQtyEl) {
      draftQtyEl.value = hasRequestModeSelection(normalized) ? fixed2(requestQtyValue(normalized)) : '';
    }
    if (draftExpiredEl) {
      draftExpiredEl.value = normalized.required_expiry_date || normalized.profile_expired_date || '';
    }
    if (draftPriceEl) {
      draftPriceEl.value = fixed2(normalized.estimated_unit_price || 0);
    }
    if (draftUsagePurposeEl) {
      draftUsagePurposeEl.value = normalizeUsagePurpose(normalized.usage_purpose || normalized.default_usage_purpose);
    }
    if (draftNotesEl) {
      draftNotesEl.value = normalized.notes || '';
    }
    updateDraftPreview();
    showDraftModal();
  }

  function applyDivisionDestinationGuard() {
    var divisionEl = document.getElementById('fieldDivisionId');
    var destinationEl = document.getElementById('fieldDestinationType');
    if (!divisionEl || !destinationEl) {
      return;
    }

    var divisionId = String(divisionEl.value || '');
    var allowed = destinationGuardMap[divisionId] || [];
    if (!Array.isArray(allowed) || !allowed.length) {
      return;
    }

    var current = String(destinationEl.value || '');
    var html = [];
    destinationOptionMeta.forEach(function (option) {
      if (allowed.indexOf(option.value) !== -1) {
        html.push('<option value="' + esc(option.value) + '">' + esc(locationLabel(option.value)) + '</option>');
      }
    });
    destinationEl.innerHTML = html.join('');
    if (allowed.indexOf(current) !== -1) {
      destinationEl.value = current;
    } else {
      destinationEl.value = String(allowed[0] || '');
    }
  }

  function routeLabel(row) {
    return routePlan(row).label;
  }

  function routeBadgeClass(row) {
    var label = routeLabel(row);
    if (label === 'SR') {
      return 'success';
    }
    if (label === 'SR + PO') {
      return 'info text-dark';
    }
    return 'warning text-dark';
  }

  function syncRowTotalsFromRequest(row, requestQty) {
    var factor = num(row.profile_content_per_buy) || 1;
    var mode = requestMode(row && row.request_uom_mode);
    var safeQty = roundQty(Math.max(0, num(requestQty)));
    if (mode === 'CONTENT') {
      row.qty_content_requested = safeQty;
      row.qty_buy_requested = roundQty(safeQty / factor);
    } else {
      row.qty_buy_requested = safeQty;
      row.qty_content_requested = roundQty(safeQty * factor);
    }
    if (String(row.source_type || '').toUpperCase() !== 'WAREHOUSE') {
      row.qty_buy_po_requested = roundQty(row.qty_buy_requested);
      row.qty_content_po_requested = roundQty(row.qty_content_requested);
      return;
    }
    row.qty_content_po_requested = roundQty(Math.max(0, row.qty_content_requested - Math.min(row.qty_content_requested, num(row.qty_content_balance))));
    row.qty_buy_po_requested = roundQty(row.qty_content_po_requested / factor);
  }

  function syncRowTotalsFromPo(row, poQty) {
    var factor = num(row.profile_content_per_buy) || 1;
    var total = num(row.qty_content_requested);
    var content = requestMode(row.request_uom_mode)==='CONTENT' ? num(poQty) : num(poQty)*factor;
    // PO is a portion of the requested total, never an implicit quantity edit.
    var minimum = Math.max(0,total-num(row.qty_content_balance));
    if (row.source_type !== 'WAREHOUSE') minimum = total;
    row.qty_content_po_requested = roundQty(Math.min(total,Math.max(minimum,content)));
    row.qty_buy_po_requested = roundQty(row.qty_content_po_requested/factor);
  }

  function routePlan(row) {
    var sourceType = String(row.source_type || 'WAREHOUSE').toUpperCase();
    var requestedContent = roundQty(num(row.qty_content_requested));
    var availableContent = roundQty(num(row.qty_content_balance));
    var factor = num(row.profile_content_per_buy) || 1;
    var explicitPoContent = roundQty(num(row.qty_content_po_requested) || (num(row.qty_buy_po_requested) * factor));

    if (requestedContent <= 0) {
      requestedContent = roundQty(num(row.qty_buy_requested) * factor);
    }

    if (sourceType !== 'WAREHOUSE' || availableContent <= 0) {
      return {
        label: 'PO',
        toSrContent: 0,
        toPoContent: requestedContent,
        toSrBuy: 0,
        toPoBuy: roundQty(requestedContent / factor)
      };
    }

    if (explicitPoContent > 0) {
      var poContentExplicit = Math.min(requestedContent, explicitPoContent);
      var srContentExplicit = Math.min(Math.max(0, requestedContent - poContentExplicit), availableContent);
      var remainingContent = Math.max(0, requestedContent - srContentExplicit - poContentExplicit);
      if (remainingContent > 0) {
        poContentExplicit = roundQty(poContentExplicit + remainingContent);
      }
      return {
        label: poContentExplicit > 0 && srContentExplicit > 0 ? 'SR + PO' : (srContentExplicit > 0 ? 'SR' : 'PO'),
        toSrContent: srContentExplicit,
        toPoContent: poContentExplicit,
        toSrBuy: roundQty(srContentExplicit / factor),
        toPoBuy: roundQty(poContentExplicit / factor)
      };
    }

    if (requestedContent <= availableContent + 0.00001) {
      return {
        label: 'SR',
        toSrContent: requestedContent,
        toPoContent: 0,
        toSrBuy: roundQty(requestedContent / factor),
        toPoBuy: 0
      };
    }

    return {
      label: 'SR + PO',
      toSrContent: availableContent,
      toPoContent: roundQty(requestedContent - availableContent),
      toSrBuy: roundQty(availableContent / factor),
      toPoBuy: roundQty((requestedContent - availableContent) / factor)
    };
  }

  function routeNote(row) {
    var plan = routePlan(row);
    var sourceType = String(row.source_type || 'WAREHOUSE').toUpperCase();
    var unitCode = requestUnitCode(row);
    var srQty = requestPlanQty(plan, row, 'SR');
    var poQty = requestPlanQty(plan, row, 'PO');
    if (sourceType !== 'WAREHOUSE') {
      return 'Fallback katalog/manual: seluruh kebutuhan diarahkan ke PO (' + qtyWithCode(poQty, unitCode) + ').';
    }
    if (plan.label === 'SR + PO') {
      return 'Stok kurang. SR ' + qtyWithCode(srQty, unitCode) + ', sisa PO ' + qtyWithCode(poQty, unitCode) + '.';
    }
    return 'Semua kebutuhan masih tertutup stok gudang, jadi masuk SR ' + qtyWithCode(srQty, unitCode) + '.';
  }

  function lineSuggestionKeyword(row) {
    var name = String(row && row.profile_name || '').trim();
    if (name !== '') {
      return name;
    }
    return String(row && row.profile_description || '').trim();
  }

  function suggestionScore(row, suggestion) {
    var score = 0;
    var lineName = normalizeToken(row && row.profile_name);
    var lineBrand = normalizeToken(row && row.profile_brand);
    var lineDesc = normalizeToken(row && row.profile_description);
    var suggestionName = normalizeToken(suggestion && (suggestion.catalog_name || suggestion.item_name || suggestion.material_name));
    var suggestionBrand = normalizeToken(suggestion && suggestion.brand_name);
    var suggestionDesc = normalizeToken(suggestion && suggestion.line_description);

    if (lineBrand !== '') {
      if (suggestionBrand === lineBrand) {
        score += 1200;
      } else if ((suggestionBrand !== '' && suggestionBrand.indexOf(lineBrand) >= 0) || (suggestionBrand !== '' && lineBrand.indexOf(suggestionBrand) >= 0)) {
        score += 700;
      }
    } else if (suggestionBrand !== '') {
      score += 160;
    }

    if (lineName !== '') {
      if (suggestionName === lineName) {
        score += 500;
      } else if ((suggestionName !== '' && suggestionName.indexOf(lineName) >= 0) || (suggestionName !== '' && lineName.indexOf(suggestionName) >= 0)) {
        score += 260;
      }
    }

    if (lineDesc !== '') {
      if (suggestionDesc === lineDesc) {
        score += 180;
      } else if ((suggestionDesc !== '' && suggestionDesc.indexOf(lineDesc) >= 0) || (suggestionDesc !== '' && lineDesc.indexOf(suggestionDesc) >= 0)) {
        score += 90;
      }
    }

    if (num(row && row.item_id) > 0 && num(suggestion && suggestion.item_id) === num(row.item_id)) {
      score += 800;
    }
    if (num(row && row.material_id) > 0 && num(suggestion && suggestion.material_id) === num(row.material_id)) {
      score += 800;
    }

    return score;
  }

  function renderProfileSuggestionButtons(idx, suggestions) {
    return suggestions.map(function (it, suggestionIdx) {
      var name = String(it.catalog_name || it.item_name || it.material_name || '-');
      var brand = String(it.brand_name || '-');
      var desc = String(it.line_description || '-');
      var price = fixed2(num(it.last_unit_price || it.standard_price || 0));
      var meta = [brand, desc].filter(function (part) {
        return String(part || '').trim() !== '' && String(part || '-') !== '-';
      }).join(' | ');
      if (meta === '') {
        meta = 'Tanpa merk/keterangan';
      }
      meta += ' | Est ' + price;
      return '<button type="button" class="btn btn-sm btn-outline-secondary dreq-suggestion-chip btn-apply-profile-suggestion" data-idx="' + idx + '" data-suggestion-idx="' + suggestionIdx + '">'
        + '<strong>' + esc(name) + '</strong>'
        + '<small>' + esc(meta) + '</small>'
        + '</button>';
    }).join('');
  }

  function fetchLineProfileSuggestions(idx) {
    if (!isVerifyMode || !requestLines[idx]) {
      return;
    }

    var row = requestLines[idx];
    var keyword = lineSuggestionKeyword(row);
    if (keyword.length < 2) {
      row.catalog_suggestions = [];
      row.suggestion_loading = false;
      row.suggestion_query = '';
      renderLines();
      return;
    }

    var queryKey = [
      keyword.toUpperCase(),
      String(num(row.vendor_id) || ''),
      effectiveLineKind(row),
      normalizeToken(row.profile_brand),
      normalizeToken(row.profile_description)
    ].join('|');

    row.suggestion_loading = true;
    row.suggestion_query = queryKey;
    renderLines();

    var url = catalogSearchUrl + '?q=' + encodeURIComponent(keyword)
      + '&line_kind=' + encodeURIComponent(effectiveLineKind(row))
      + '&limit=8';
    if (num(row.vendor_id) > 0) {
      url += '&vendor_id=' + encodeURIComponent(String(num(row.vendor_id)));
    }

    fetchJson(url)
      .then(function (response) {
        if (!requestLines[idx] || requestLines[idx].suggestion_query !== queryKey) {
          return;
        }
        if (!response || response.ok === false) {
          throw new Error((response && response.message) ? response.message : 'Gagal memuat saran profile.');
        }

        var kind = effectiveLineKind(row);
        var items = Array.isArray(response.items) ? response.items : [];
        var filtered = items.filter(function (it) {
          var itemKind = String(it.line_kind || 'ITEM').toUpperCase();
          return (kind === '' || itemKind === kind) && String(it.profile_key || '').trim() !== '';
        }).map(function (it, listIdx) {
          return {
            row: it,
            score: suggestionScore(row, it),
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

        requestLines[idx].catalog_suggestions = filtered;
        requestLines[idx].suggestion_loading = false;
        if (activeVerifyLineIdx === idx) {
          renderVerifySuggestionList();
          updateVerifyModalPreview();
        }
        renderLines();
      })
      .catch(function (error) {
        if (!requestLines[idx] || requestLines[idx].suggestion_query !== queryKey) {
          return;
        }
        requestLines[idx].catalog_suggestions = [];
        requestLines[idx].suggestion_loading = false;
        if (activeVerifyLineIdx === idx) {
          renderVerifySuggestionList();
        }
        renderLines();
        flash('warning', error && error.message ? error.message : 'Gagal memuat saran profile.');
      });
  }

  function applyProfileSuggestion(idx, suggestion) {
    if (!requestLines[idx] || !suggestion) {
      return;
    }

    var row = requestLines[idx];
    var requestPrimaryQty = hasRequestModeSelection(row) ? requestQtyValue(row) : 0;
    var poPrimaryQty = hasRequestModeSelection(row) ? requestPoQtyValue(row) : 0;
    var currentMode = requestMode(row && row.request_uom_mode);

    row.item_id = num(suggestion.item_id) > 0 ? num(suggestion.item_id) : null;
    row.material_id = num(suggestion.material_id) > 0 ? num(suggestion.material_id) : null;
    row.line_kind = canonicalLineKind(row);
    row.profile_key = String(suggestion.profile_key || row.profile_key || '');
    row.profile_name = String(suggestion.catalog_name || suggestion.item_name || suggestion.material_name || row.profile_name || '');
    row.profile_brand = String(suggestion.brand_name || '');
    row.profile_description = String(suggestion.line_description || '');
    row.profile_expired_date = String(suggestion.expired_date || row.profile_expired_date || '');
    row.required_expiry_date = String(suggestion.required_expiry_date || suggestion.expired_date || row.required_expiry_date || row.profile_expired_date || '');
    row.expiry_policy = row.required_expiry_date ? 'EXACT_DATE' : (row.expiry_policy || 'NONE');
    row.min_remaining_days = num(suggestion.min_remaining_days || row.min_remaining_days || 0) || null;
    row.buy_uom_id = num(suggestion.buy_uom_id) > 0 ? num(suggestion.buy_uom_id) : row.buy_uom_id;
    row.content_uom_id = num(suggestion.content_uom_id) > 0 ? num(suggestion.content_uom_id) : row.content_uom_id;
    row.profile_content_per_buy = num(suggestion.content_per_buy || suggestion.conversion_factor_to_content || row.profile_content_per_buy || 1);
    if (row.profile_content_per_buy <= 0) {
      row.profile_content_per_buy = 1;
    }
    row.profile_buy_uom_code = String(suggestion.buy_uom_code || row.profile_buy_uom_code || '');
    row.profile_content_uom_code = String(suggestion.content_uom_code || row.profile_content_uom_code || '');
    row.estimated_unit_price = round2(num(suggestion.last_unit_price || suggestion.standard_price || row.estimated_unit_price));
    row.catalog_suggestions = [];
    row.suggestion_loading = false;
    row.suggestion_query = '';
    row.line_reviewed = false;

    if (num(row.vendor_id) <= 0 && num(suggestion.vendor_id) > 0) {
      row.vendor_id = num(suggestion.vendor_id);
      row.vendor_name = vendorNameById(row.vendor_id);
    }

    if (hasRequestModeSelection(row)) {
      row.request_uom_mode = currentMode;
      syncRowTotalsFromRequest(row, requestPrimaryQty);
      if (poPrimaryQty > 0) {
        syncRowTotalsFromPo(row, poPrimaryQty, routePlan(row));
      }
    }

    renderLines();
    if (activeVerifyLineIdx === idx) {
      populateVerifyModal(idx);
    }
    flash('success', 'Profile buyer dipakai. Split SR/PO final akan dihitung ulang saat verifikasi disimpan.');
  }

  function setLinesJson() {
    var el = document.getElementById('fieldLinesJson');
    if (el) {
      el.value = JSON.stringify(requestLines.map(function (row) {
        var payload = Object.assign({}, row || {});
        delete payload.catalog_suggestions;
        delete payload.suggestion_loading;
        delete payload.suggestion_query;
        return payload;
      }));
      document.dispatchEvent(new CustomEvent('procurement-lines-changed'));
    }
  }

  function renderLines() {
    var tbody = document.getElementById('requestLineRows');
    if (!tbody) return;
    if (!requestLines.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Belum ada rincian pengajuan.</td></tr>';
      setLinesJson(); return;
    }
    tbody.innerHTML = requestLines.map(function (row, idx) {
      var plan = routePlan(row), mode = selectedRequestMode(row.request_uom_mode);
      var status = row.review_status || 'PENDING';
      var label = {PENDING:'Menunggu', VERIFIED:'Terverifikasi', REJECTED:'Ditolak', VOID:'Void'}[status] || status;
      var badge = status === 'VERIFIED' ? 'success' : (status === 'REJECTED' ? 'danger' : 'warning text-dark');
      var unit = requestUnitCode(row);
      var quantity = isVerifyMode
        ? '<strong>' + esc(qtyWithCode(requestQtyValue(row), unit)) + '</strong>'
        : '<select aria-label="Satuan request" class="form-select form-select-sm line-request-mode" data-idx="'+idx+'"><option value="">Pilih satuan</option><option value="BUY"'+(mode==='BUY'?' selected':'')+'>Per beli ('+esc(row.profile_buy_uom_code)+')</option><option value="CONTENT"'+(mode==='CONTENT'?' selected':'')+'>Per isi ('+esc(row.profile_content_uom_code)+')</option></select>'
          + '<input aria-label="Jumlah request" type="number" min="0.01" step="0.01" class="form-control form-control-sm line-request mt-1" data-idx="'+idx+'" value="'+(mode?quantityInput(requestQtyValue(row)):'')+'"'+(mode?'':' disabled')+'>';
      var usage = isVerifyMode ? esc(usagePurposeLabel(row.usage_purpose))
        : '<select aria-label="Pemakaian" class="form-select form-select-sm line-usage-purpose" data-idx="'+idx+'">'+renderUsagePurposeOptions(row.usage_purpose)+'</select>';
      var allocation = '<div><span class="badge bg-'+routeBadgeClass(row)+'">'+esc(routeLabel(row))+'</span></div>'
        + '<div class="mt-1">SR <strong>'+esc(qtyWithCode(requestPlanQty(plan,row,'SR'),unit))+'</strong></div>'
        + '<div>PO <strong>'+esc(qtyWithCode(requestPlanQty(plan,row,'PO'),unit))+'</strong></div>';
      if (!isVerifyMode && row.source_type === 'WAREHOUSE') {
        allocation += '<label class="small text-muted mt-1">Porsi PO ('+esc(unit)+')</label><input aria-label="Porsi PO" type="number" min="0" step="0.01" class="form-control form-control-sm line-po" data-idx="'+idx+'" value="'+quantityInput(requestPoQtyValue(row))+'"'+(mode?'':' disabled')+'>';
      }
      if (isVerifyMode && requiresVendor(row,plan)) allocation += '<div class="small text-muted mt-1">Vendor: '+esc(row.vendor_name || vendorNameById(row.vendor_id) || 'Belum dipilih')+'</div>';
      var actions = '<button type="button" class="btn btn-sm btn-outline-danger line-del" data-idx="'+idx+'">Hapus</button>';
      if (isVerifyMode) {
        actions = '<span class="badge bg-'+badge+'">'+esc(label)+'</span>';
        if (status==='PENDING') actions += '<button type="button" class="btn btn-sm btn-outline-success line-verify" data-idx="'+idx+'">Verifikasi</button><button type="button" class="btn btn-sm btn-outline-danger line-decision" data-action="REJECT" data-idx="'+idx+'">Tolak</button>';
        if (status==='REJECTED') actions += '<button type="button" class="btn btn-sm btn-outline-secondary line-decision" data-action="REOPEN" data-idx="'+idx+'">Buka ulang</button>';
      }
      return '<tr id="dreq-line-'+row.id+'" class="dreq-row-'+status.toLowerCase()+'">'
        + '<td data-label="Barang / Jenis"><strong>'+esc(row.profile_name || '-')+'</strong><div class="dreq-profile-meta"><span class="badge bg-light text-dark border">'+esc(effectiveLineKindLabel(row))+'</span><span class="small text-muted">'+esc(sourceLabel(row.source_type))+'</span></div><div class="small text-muted mt-1">'+esc([row.profile_brand,row.profile_description].filter(Boolean).join(' / '))+'</div>'+(row.review_notes?'<div class="small text-danger mt-1">'+esc(row.review_notes)+'</div>':'')+'</td>'
        + '<td data-label="Stok"><div data-live-stock-line="'+(idx+1)+'" class="small">Menunggu cek stok...</div><details class="small mt-2"><summary>Snapshot pengajuan</summary>'+esc(qtyWithCode(row.qty_buy_balance,row.profile_buy_uom_code))+'<br>'+esc(qtyWithCode(row.qty_content_balance,row.profile_content_uom_code))+'</details></td>'
        + '<td data-label="Jumlah / Satuan">'+quantity+'<div class="small text-muted mt-1">'+esc(requestSecondarySummary(row))+'</div><div class="small text-muted">'+esc(packSummary(row))+'</div></td>'
        + '<td data-label="Pemakaian">'+usage+'</td>'
        + '<td data-label="Alokasi SR / PO">'+allocation+'</td>'
        + '<td data-label="Harga / Catatan"><strong>Rp '+num(row.estimated_unit_price).toLocaleString('id-ID',{maximumFractionDigits:2})+'</strong><div class="small text-muted">per '+esc(row.profile_buy_uom_code || '-')+'</div>'
          +(isVerifyMode?'<div class="small mt-2">'+esc(row.notes || '-')+'</div>':'<input aria-label="Catatan rincian" class="form-control form-control-sm line-notes mt-2" data-idx="'+idx+'" value="'+esc(row.notes)+'" placeholder="Catatan">')+'</td>'
        + '<td data-label="Aksi"><div class="dreq-row-actions">'+actions+'</div></td></tr>';
    }).join('');
    setLinesJson();
  }

  function renderSearchResults(rows, source, allowManual, query) {
    var tbody = document.getElementById('searchResultRows');
    if (!tbody) {
      return;
    }
    toggleManualCard(allowManual, query);
    if (!rows || !rows.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Tidak ada data.</td></tr>';
      if (allowManual) {
        setSearchMeta('Stok gudang dan katalog tidak menemukan barang. Lanjut input manual untuk diarahkan ke PO.');
      } else {
        setSearchMeta('Tidak ada data untuk pencarian ini.');
      }
      return;
    }

    if (source === 'WAREHOUSE') {
      setSearchMeta('Menampilkan stok gudang saja. Jika kebutuhan total melebihi stok snapshot, isi Qty Request lebih besar dari stok dan sistem akan split otomatis: SR sesuai stok, sisanya ke PO.');
    } else if (source === 'COMBINED') {
      setSearchMeta('Menampilkan stok gudang terlebih dulu, lalu tambahan kandidat dari katalog/master. Jika barang yang dicari tidak ada persis di gudang, pilih kandidat katalog yang paling sesuai agar line diarahkan ke PO bila perlu.');
    } else {
      setSearchMeta('Stok gudang kosong untuk kata kunci ini, jadi sistem menampilkan fallback katalog/master. Line ini akan diarahkan ke PO bila tidak ada stok gudang saat proses.');
    }

    var html = '';
    rows.forEach(function (row) {
      var normalized = normalizeRow(row);
      var sourceType = String(row.source_type || source || 'CATALOG').toUpperCase();
      var badgeClass = sourceType === 'WAREHOUSE' ? 'success' : 'info text-dark';
      var buyCode = esc(normalized.profile_buy_uom_code || '-');
      var contentCode = esc(normalized.profile_content_uom_code || '-');
      var brandText = normalized.profile_brand ? '<div class="small text-muted">Brand: ' + esc(normalized.profile_brand) + '</div>' : '';
      var descriptionText = normalized.profile_description ? esc(normalized.profile_description) : '<span class="text-muted">-</span>';
      var stockCell = sourceType === 'WAREHOUSE'
        ? '<div class="fw-semibold">' + fixed2(normalized.qty_buy_balance) + ' ' + buyCode + '</div><div class="small text-muted">' + fixed2(normalized.qty_content_balance) + ' ' + contentCode + '</div>'
        : '<span class="text-muted small">Fallback katalog</span>';
      var priceValue = num(normalized.last_unit_price || normalized.standard_price || normalized.estimated_unit_price || 0);
      var expDateText = normalized.profile_expired_date ? esc(normalized.profile_expired_date) : '-';
      var lastPurchaseDate = normalized.last_purchase_date ? esc(normalized.last_purchase_date) : '-';
      html += '<tr>'
        + '<td><strong>' + esc(normalized.profile_name || '-') + '</strong>' + brandText + '<div class="small">' + descriptionText + '</div><div class="small mt-1"><span class="badge bg-' + badgeClass + '">' + esc(sourceLabel(sourceType)) + '</span> ' + esc(effectiveLineKindLabel(normalized)) + '</div></td>'
        + '<td class="dreq-uom-cell"><div class="fw-semibold">' + buyCode + ' -> ' + contentCode + '</div><div class="small text-muted">' + esc(packSummary(normalized)) + '</div></td>'
        + '<td class="text-end dreq-stock-cell">' + stockCell + '</td>'
        + '<td class="text-end"><div class="fw-semibold">' + fixed2(priceValue) + '</div><div class="small text-muted">/ ' + buyCode + '</div></td>'
        + '<td><div>Exp: ' + expDateText + '</div><div class="small text-muted">Beli: ' + lastPurchaseDate + '</div></td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-primary dreq-action-btn search-pick" data-row="' + esc(JSON.stringify(normalized)) + '">Pilih</button></td>'
        + '</tr>';
    });
    tbody.innerHTML = html;
  }

  function addLine(row) {
    var normalized = normalizeRow(row);
    var key = lineKey(normalized);
    if (hasExistingLineKey(key)) {
      flash('warning', 'Profile ini sudah ada di line pengajuan.');
      return false;
    }
    requestLines.push(normalized);
    renderLines();
    return true;
  }

  function selectCode(selectId) {
    var select = document.getElementById(selectId);
    if (!select || !select.options || select.selectedIndex < 0) {
      return '';
    }
    return select.options[select.selectedIndex].getAttribute('data-code') || '';
  }

  function addManualLine() {
    var name = String((document.getElementById('manualNameInput') || {}).value || '').trim();
    var description = String((document.getElementById('manualDescriptionInput') || {}).value || '').trim();
    var buyUomId = num((document.getElementById('manualBuyUomId') || {}).value);
    var contentUomId = num((document.getElementById('manualContentUomId') || {}).value);
    var contentPerBuy = num((document.getElementById('manualContentPerBuy') || {}).value);
    var qtyBuy = num((document.getElementById('manualQtyBuy') || {}).value);

    if (!name) {
      flash('warning', 'Nama barang manual wajib diisi.');
      return;
    }
    if (buyUomId <= 0 || contentUomId <= 0) {
      flash('warning', 'UOM beli dan UOM isi wajib dipilih untuk input manual.');
      return;
    }
    if (contentPerBuy <= 0 || qtyBuy <= 0) {
      flash('warning', 'Isi per beli dan qty beli manual harus lebih dari 0.');
      return;
    }

    addLine({
      source_type: 'MANUAL',
      line_kind: 'ITEM',
      item_id: null,
      material_id: null,
      profile_key: '',
      profile_name: name,
      profile_brand: '',
      profile_description: description,
      profile_expired_date: '',
      expiry_policy: 'NONE',
      required_expiry_date: '',
      min_remaining_days: null,
      buy_uom_id: buyUomId,
      content_uom_id: contentUomId,
      profile_content_per_buy: contentPerBuy,
      profile_buy_uom_code: selectCode('manualBuyUomId'),
      profile_content_uom_code: selectCode('manualContentUomId'),
      qty_buy_requested: round2(qtyBuy),
      qty_content_requested: round2(qtyBuy * contentPerBuy),
      qty_buy_po_requested: round2(qtyBuy),
      qty_content_po_requested: round2(qtyBuy * contentPerBuy),
      qty_buy_balance: 0,
      qty_content_balance: 0,
      default_usage_purpose: 'OPERASIONAL',
      usage_purpose: 'OPERASIONAL',
      notes: ''
    });
  }

  function syncManualQtyContent() {
    var qtyBuy = num((document.getElementById('manualQtyBuy') || {}).value);
    var contentPerBuy = num((document.getElementById('manualContentPerBuy') || {}).value);
    var qtyContent = document.getElementById('manualQtyContent');
    if (!qtyContent) {
      return;
    }
    if (contentPerBuy <= 0) {
      contentPerBuy = 1;
    }
    qtyContent.value = fixed2(qtyBuy * contentPerBuy);
  }

  function fetchJson(url) {
    return fetch(url, { credentials: 'same-origin' }).then(function (response) {
      return response.text().then(function (text) {
        var data = {};
        try {
          data = text ? JSON.parse(text) : {};
        } catch (error) {
          data = {};
        }
        if (!response.ok) {
          data.ok = false;
          data.message = data.message || ('Request gagal (' + response.status + ')');
        }
        return data;
      });
    });
  }

  var searchTimer = null;
  function runSearch() {
    var q = (document.getElementById('searchProfileInput') || {}).value || '';
    q = String(q).trim();
    if (!q) {
      renderSearchResults([], 'EMPTY', false, '');
      setSearchMeta('Masukkan kata kunci untuk mulai mencari barang.');
      return;
    }
    setSearchMeta('Memuat kandidat barang...');
    fetchJson(searchUrl + '?q=' + encodeURIComponent(q) + '&limit=20')
      .then(function (response) {
        if (!response || response.ok === false) {
          flash('danger', (response && response.message) ? response.message : 'Gagal memuat kandidat barang.');
          return;
        }
        renderSearchResults(response.rows || [], String(response.source || 'EMPTY').toUpperCase(), !!response.allow_manual, q);
      })
      .catch(function () {
        flash('danger', 'Gagal memuat kandidat barang.');
      });
  }

  var btnSearch = document.getElementById('btnSearchProfile');
  if (btnSearch) {
    btnSearch.addEventListener('click', function () {
      runSearch();
    });
  }

  var searchInput = document.getElementById('searchProfileInput');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      if (searchTimer) {
        clearTimeout(searchTimer);
      }
      searchTimer = setTimeout(runSearch, 250);
    });
    searchInput.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        runSearch();
      }
    });
  }

  var divisionSelect = document.getElementById('fieldDivisionId');
  if (divisionSelect) {
    divisionSelect.addEventListener('change', applyDivisionDestinationGuard);
  }

  var btnManual = document.getElementById('btnAddManualLine');
  if (btnManual) {
    btnManual.addEventListener('click', function () {
      addManualLine();
    });
  }

  ['manualQtyBuy', 'manualContentPerBuy'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) {
      el.addEventListener('input', syncManualQtyContent);
    }
  });

  document.addEventListener('click', function (event) {
    var decision = event.target.closest('.line-decision');
    if (decision && !savingDecision) {
      decisionIdx = Number(decision.dataset.idx);
      decisionAction = decision.dataset.action;
      document.getElementById('dreqDecisionTitle').textContent = decisionAction==='REJECT'?'Tolak rincian':'Buka ulang rincian';
      document.getElementById('dreqDecisionName').textContent = requestLines[decisionIdx].profile_name;
      document.getElementById('dreqDecisionReason').value = '';
      document.getElementById('dreqDecisionError').textContent = '';
      showModalElement(document.getElementById('dreqDecisionModal'));
      return;
    }
    var pick = event.target.closest('.search-pick');
    if (pick) {
      event.preventDefault();
      try {
        openDraftModal(JSON.parse(pick.getAttribute('data-row') || '{}'));
      } catch (error) {
        flash('danger', 'Data barang tidak valid.');
      }
      return;
    }

    var del = event.target.closest('.line-del');
    if (del) {
      event.preventDefault();
      var idx = parseInt(del.getAttribute('data-idx') || '-1', 10);
      if (idx >= 0) {
        requestLines.splice(idx, 1);
        renderLines();
      }
      return;
    }

    var verifyLine = event.target.closest('.line-verify');
    if (verifyLine) {
      event.preventDefault();
      var verifyIdx = parseInt(verifyLine.getAttribute('data-idx') || '-1', 10);
      if (verifyIdx >= 0 && requestLines[verifyIdx]) {
        openVerifyModal(verifyIdx);
      }
      return;
    }

    var addVendor = event.target.closest('.line-vendor-add');
    if (addVendor) {
      event.preventDefault();
      var vendorLineIdx = parseInt(addVendor.getAttribute('data-idx') || '-1', 10);
      if (vendorLineIdx >= 0 && requestLines[vendorLineIdx] && window.FinanceQuickVendor) {
        window.FinanceQuickVendor.open({
          title: 'Tambah Vendor PO',
          onCreated: function (vendor) {
            upsertVendorOption(vendor);
            requestLines[vendorLineIdx].vendor_id = num(vendor.id);
            requestLines[vendorLineIdx].vendor_name = String(vendor.vendor_name || vendor.vendor_code || '');
            renderLines();
            flash('success', 'Vendor baru siap dipakai untuk line PO ini.');
          }
        });
        return;
      }
    }

    var suggestProfile = event.target.closest('.line-profile-suggest');
    if (suggestProfile) {
      event.preventDefault();
      var suggestIdx = parseInt(suggestProfile.getAttribute('data-idx') || '-1', 10);
      if (suggestIdx >= 0) {
        fetchLineProfileSuggestions(suggestIdx);
      }
      return;
    }

    var applyProfile = event.target.closest('.btn-apply-profile-suggestion');
    if (applyProfile) {
      event.preventDefault();
      var applyIdx = parseInt(applyProfile.getAttribute('data-idx') || '-1', 10);
      var suggestionIdx = parseInt(applyProfile.getAttribute('data-suggestion-idx') || '-1', 10);
      if (applyIdx >= 0 && requestLines[applyIdx] && Array.isArray(requestLines[applyIdx].catalog_suggestions) && requestLines[applyIdx].catalog_suggestions[suggestionIdx]) {
        applyProfileSuggestion(applyIdx, requestLines[applyIdx].catalog_suggestions[suggestionIdx]);
      }
      return;
    }
  });

  if (verifyModalEl) {
    ['input', 'change'].forEach(function (eventName) {
      verifyModalEl.addEventListener(eventName, function (event) {
        if (event.target && event.target.closest('#dreqVerifyBrand, #dreqVerifyDescription, #dreqVerifyRequestMode, #dreqVerifyQty, #dreqVerifyPoQty, #dreqVerifyPrice, #dreqVerifyUsagePurpose, #dreqVerifyVendor, #dreqVerifyNotes')) {
          setVerifyAlert('', '');
          updateVerifyModalPreview();
        }
      });
    });
    verifyModalEl.addEventListener('hidden.bs.modal', function () {
      clearVerifyState();
    });
    verifyModalEl.addEventListener('click', function (event) {
      if (event.target === verifyModalEl || (event.target && event.target.closest('[data-bs-dismiss="modal"]'))) {
        hideVerifyModal();
      }
    });
  }

  if (verifySuggestBtn) {
    verifySuggestBtn.addEventListener('click', function () {
      if (activeVerifyLineIdx >= 0) {
        fetchLineProfileSuggestions(activeVerifyLineIdx);
        renderVerifySuggestionList();
      }
    });
  }

  if (verifyVendorAddBtn) {
    verifyVendorAddBtn.addEventListener('click', function () {
      if (activeVerifyLineIdx >= 0 && window.FinanceQuickVendor) {
        window.FinanceQuickVendor.open({
          title: 'Tambah Vendor PO',
          onCreated: function (vendor) {
            upsertVendorOption(vendor);
            if (verifyVendorEl) {
              verifyVendorEl.innerHTML = renderVendorOptions(vendor.id);
              verifyVendorEl.value = String(num(vendor.id));
            }
            updateVerifyModalPreview();
            flash('success', 'Vendor baru siap dipakai untuk line review ini.');
          }
        });
      }
    });
  }

  if (verifyApplyBtn) {
    verifyApplyBtn.addEventListener('click', async function () {
      var reviewedRow = buildVerifyModalRow();
      if (!reviewedRow) {
        setVerifyAlert('danger', 'Line review tidak ditemukan. Tutup modal lalu buka lagi.');
        return;
      }
      if (!hasRequestModeSelection(reviewedRow)) {
        setVerifyAlert('warning', 'Pilih mode input dulu untuk line ini.');
        return;
      }
      if (num(requestQtyValue(reviewedRow)) <= 0) {
        setVerifyAlert('warning', 'Qty request line harus lebih dari 0.');
        return;
      }
      var reviewedPlan = routePlan(reviewedRow);
      if (requiresVendor(reviewedRow, reviewedPlan) && num(reviewedRow.vendor_id) <= 0) {
        setVerifyAlert('warning', 'Vendor wajib dipilih untuk line yang masuk PO.');
        return;
      }
      verifyApplyBtn.disabled = true;
      try {
        if (!window.ProcurementStockReview) throw new Error('Pemeriksaan stok belum siap.');
        var confirmation = window.ProcurementStockReview.confirmation();
        await saveDecision(reviewedRow,'VERIFY',{line:cleanLine(reviewedRow),stock_review:confirmation});
      } catch (error) { setVerifyAlert('danger',esc(error.message || 'Koneksi gagal. Periksa status rincian dengan memuat ulang halaman.')); }
      finally { verifyApplyBtn.disabled = false; }
    });
  }
  document.getElementById('dreqDecisionSave').addEventListener('click',async function () {
    var button = this, reason = document.getElementById('dreqDecisionReason').value.trim();
    var errorEl = document.getElementById('dreqDecisionError');
    if (reason.length<3) { errorEl.textContent='Isi alasan minimal 3 karakter.'; return; }
    button.disabled=true;
    try { await saveDecision(requestLines[decisionIdx],decisionAction,{reason:reason}); }
    catch (error) { errorEl.textContent=error.message || 'Koneksi gagal. Muat ulang untuk memeriksa status.'; }
    finally { button.disabled=false; }
  });
  if (verifyModalEl) {
    ['input','change'].forEach(function (name) {
      verifyModalEl.addEventListener(name,function (event) {
        if (!event.target.closest('#procurementStockReview')) document.dispatchEvent(new CustomEvent('procurement-lines-changed'));
      });
    });
  }

  if (draftModalEl) {
    ['input', 'change'].forEach(function (eventName) {
      draftModalEl.addEventListener(eventName, function (event) {
        if (event.target && event.target.closest('#dreqDraftBrand, #dreqDraftDescription, #dreqDraftRequestMode, #dreqDraftQty, #dreqDraftExpired, #dreqDraftPrice, #dreqDraftUsagePurpose, #dreqDraftNotes')) {
          setDraftAlert('', '');
          updateDraftPreview();
        }
      });
    });
    draftModalEl.addEventListener('hidden.bs.modal', function () {
      clearDraftState();
    });
    draftModalEl.addEventListener('click', function (event) {
      if (event.target === draftModalEl || (event.target && event.target.closest('[data-bs-dismiss="modal"]'))) {
        hideDraftModal();
      }
    });
  }

  if (draftApplyBtn) {
    draftApplyBtn.addEventListener('click', function () {
      var row = buildDraftRow();
      if (!row) {
        setDraftAlert('danger', 'Draft barang belum siap. Ulangi pilih dari preview.');
        return;
      }
      if (!hasRequestModeSelection(row)) {
        setDraftAlert('warning', 'Pilih mode input dulu sebelum menambahkan line.');
        return;
      }
      if (num(requestQtyValue(row)) <= 0) {
        setDraftAlert('warning', 'Qty request harus lebih dari 0.');
        return;
      }
      if (!addLine(row)) {
        setDraftAlert('warning', 'Profile ini sudah ada di line pengajuan.');
        return;
      }
      hideDraftModal();
      flash('success', 'Barang ditambahkan ke line pengajuan.');
    });
  }

  document.addEventListener('input', function (event) {
    var requestInput = event.target.closest('.line-request');
    if (requestInput) {
      var requestIdx = parseInt(requestInput.getAttribute('data-idx') || '-1', 10);
      if (requestIdx >= 0 && requestLines[requestIdx] && hasRequestModeSelection(requestLines[requestIdx])) {
        syncRowTotalsFromRequest(requestLines[requestIdx], requestInput.value);
        markLineNeedsReview(requestIdx);
        setLinesJson();
      }
      return;
    }

    var po = event.target.closest('.line-po');
    if (po) {
      var poIdx = parseInt(po.getAttribute('data-idx') || '-1', 10);
      if (poIdx >= 0 && requestLines[poIdx] && hasRequestModeSelection(requestLines[poIdx])) {
        var previousPlan = routePlan(requestLines[poIdx]);
        syncRowTotalsFromPo(requestLines[poIdx], po.value, previousPlan);
        markLineNeedsReview(poIdx);
        setLinesJson();
      }
      return;
    }

    var vendor = event.target.closest('.line-vendor');
    if (vendor) {
      var vendorIdx = parseInt(vendor.getAttribute('data-idx') || '-1', 10);
      if (vendorIdx >= 0 && requestLines[vendorIdx]) {
        requestLines[vendorIdx].vendor_id = num(vendor.value) > 0 ? num(vendor.value) : null;
        requestLines[vendorIdx].vendor_name = vendorNameById(vendor.value);
        markLineNeedsReview(vendorIdx);
        setLinesJson();
      }
      return;
    }

    var notes = event.target.closest('.line-notes');
    if (notes) {
      var notesIdx = parseInt(notes.getAttribute('data-idx') || '-1', 10);
      if (notesIdx >= 0 && requestLines[notesIdx]) {
        requestLines[notesIdx].notes = notes.value || '';
        markLineNeedsReview(notesIdx);
        setLinesJson();
      }
      return;
    }

    var profileBrand = event.target.closest('.line-profile-brand');
    if (profileBrand) {
      var brandIdx = parseInt(profileBrand.getAttribute('data-idx') || '-1', 10);
      if (brandIdx >= 0 && requestLines[brandIdx]) {
        requestLines[brandIdx].profile_brand = profileBrand.value || '';
        markLineNeedsReview(brandIdx);
        setLinesJson();
      }
      return;
    }

    var profileDescription = event.target.closest('.line-profile-description');
    if (profileDescription) {
      var descIdx = parseInt(profileDescription.getAttribute('data-idx') || '-1', 10);
      if (descIdx >= 0 && requestLines[descIdx]) {
        requestLines[descIdx].profile_description = profileDescription.value || '';
        markLineNeedsReview(descIdx);
        setLinesJson();
      }
      return;
    }

    var estimatedPrice = event.target.closest('.line-estimated-price');
    if (estimatedPrice) {
      var priceIdx = parseInt(estimatedPrice.getAttribute('data-idx') || '-1', 10);
      if (priceIdx >= 0 && requestLines[priceIdx]) {
        requestLines[priceIdx].estimated_unit_price = round2(Math.max(0, num(estimatedPrice.value)));
        markLineNeedsReview(priceIdx);
        setLinesJson();
      }
    }
  });

  document.addEventListener('change', function (event) {
    var usage = event.target.closest('.line-usage-purpose');
    if (usage) {
      var usageIdx = parseInt(usage.getAttribute('data-idx') || '-1', 10);
      if (usageIdx >= 0 && requestLines[usageIdx]) {
        requestLines[usageIdx].usage_purpose = normalizeUsagePurpose(usage.value);
        markLineNeedsReview(usageIdx);
        setLinesJson();
      }
      return;
    }

    var mode = event.target.closest('.line-request-mode');
    if (mode) {
      var idx = parseInt(mode.getAttribute('data-idx') || '-1', 10);
      if (idx >= 0 && requestLines[idx]) {
        requestLines[idx].request_uom_mode = selectedRequestMode(mode.value);
        markLineNeedsReview(idx);
        renderLines();
      }
      return;
    }

    var requestInput = event.target.closest('.line-request');
    if (requestInput) {
      var requestIdx = parseInt(requestInput.getAttribute('data-idx') || '-1', 10);
      if (requestIdx >= 0 && requestLines[requestIdx]) {
        if (!hasRequestModeSelection(requestLines[requestIdx])) {
          renderLines();
          return;
        }
        syncRowTotalsFromRequest(requestLines[requestIdx], requestInput.value);
        markLineNeedsReview(requestIdx);
        renderLines();
      }
      return;
    }

    var po = event.target.closest('.line-po');
    if (po) {
      var poIdx = parseInt(po.getAttribute('data-idx') || '-1', 10);
      if (poIdx >= 0 && requestLines[poIdx]) {
        if (!hasRequestModeSelection(requestLines[poIdx])) {
          renderLines();
          return;
        }
        var previousPlan = routePlan(requestLines[poIdx]);
        syncRowTotalsFromPo(requestLines[poIdx], po.value, previousPlan);
        markLineNeedsReview(poIdx);
        renderLines();
      }
      return;
    }

    var vendor = event.target.closest('.line-vendor');
    if (vendor) {
      var vendorIdx = parseInt(vendor.getAttribute('data-idx') || '-1', 10);
      if (vendorIdx >= 0 && requestLines[vendorIdx]) {
        requestLines[vendorIdx].vendor_id = num(vendor.value) > 0 ? num(vendor.value) : null;
        requestLines[vendorIdx].vendor_name = vendorNameById(vendor.value);
        markLineNeedsReview(vendorIdx);
        setLinesJson();
      }
    }
  });

  var form = document.getElementById('divisionRequestForm');
  if (form) {
    form.addEventListener('submit', function (event) {
      if (isVerifyMode) { event.preventDefault(); return; }
      if (!requestLines.length) {
        event.preventDefault();
        flash('warning', 'Minimal 1 line pengajuan wajib diisi.');
        return;
      }
      for (var i = 0; i < requestLines.length; i++) {
        if (isVerifyMode && !requestLines[i].line_reviewed) {
          event.preventDefault();
          flash('warning', 'Review dan simpan dulu setiap line lewat tombol Verifikasi sebelum bentuk dokumen akhir.');
          openVerifyModal(i);
          return;
        }
        if (!hasRequestModeSelection(requestLines[i])) {
          event.preventDefault();
          flash('warning', 'Pilih mode input dulu untuk semua line pengajuan.');
          return;
        }
        if (canAssignVendor) {
          var currentPlan = routePlan(requestLines[i]);
          if (requiresVendor(requestLines[i], currentPlan) && num(requestLines[i].vendor_id) <= 0) {
            event.preventDefault();
            flash('warning', 'Pilih vendor untuk semua line yang masuk PO.');
            return;
          }
        }
      }
      setLinesJson();
      var submitButton = document.getElementById('btnSubmitDivisionRequest');
      if (submitButton) {
        submitButton.disabled = true;
      }
    });
  }

  requestLines = requestLines.map(function (row) {
    return normalizeRow(row || {});
  });
  setSearchMeta('Masukkan kata kunci untuk mulai mencari barang.');
  toggleManualCard(false, '');
  applyDivisionDestinationGuard();
  syncManualQtyContent();
  renderLines();
  if (isVerifyMode && /^#dreq-line-\d+$/.test(window.location.hash)) {
    var focusId = Number(window.location.hash.replace('#dreq-line-',''));
    var focusIdx = requestLines.findIndex(function (row) { return row.id===focusId; });
    if (focusIdx>=0) setTimeout(function () { openVerifyModal(focusIdx); },0);
  }
})();
</script>
