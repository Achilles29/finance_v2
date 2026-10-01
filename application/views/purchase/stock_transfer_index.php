<?php
$baseUrl = site_url('inventory/stock/transfer/division');
$searchUrl = site_url('inventory/stock/transfer/item-search');
$storeUrl = site_url('inventory/stock/transfer/store');
$postBaseUrl = site_url('inventory/stock/transfer/post');
$voidBaseUrl = site_url('inventory/stock/transfer/void');
$deleteBaseUrl = site_url('inventory/stock/transfer/delete');
$stepUpUrl = site_url('inventory/stock/transfer/step-up/verify');
$stockTransferCsrfToken = (string)($stock_transfer_csrf_token ?? '');

$rows = is_array($rows ?? null) ? $rows : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$destinationGuardMap = is_array($destination_guard_map ?? null) ? $destination_guard_map : [];

$dateFrom = (string)($date_from ?? '');
$dateTo = (string)($date_to ?? '');
$q = (string)($q ?? '');
$fromDivisionId = (int)($from_division_id ?? 0);
$fromDestination = strtoupper(trim((string)($from_destination ?? 'ALL')));
$toDivisionId = (int)($to_division_id ?? 0);
$toDestination = strtoupper(trim((string)($to_destination ?? 'ALL')));
$limit = max(10, (int)($limit ?? 100));

$destinationOptions = [
  'BAR' => 'BAR / Reguler',
  'KITCHEN' => 'KITCHEN / Reguler',
  'BAR_EVENT' => 'BAR / Event',
  'KITCHEN_EVENT' => 'KITCHEN / Event',
  'OFFICE' => 'OFFICE',
  'OTHER' => 'OTHER',
];

$destinationLabel = static function (string $value) use ($destinationOptions): string {
  $key = strtoupper(trim($value));
  if ($key === 'ALL' || $key === '') {
    return 'Semua';
  }
  return (string)($destinationOptions[$key] ?? $key);
};
$divisionNameById = [];
foreach ($divisions as $division) {
  $divisionNameById[(int)($division['id'] ?? 0)] = (string)($division['division_name'] ?? ($division['name'] ?? ('Divisi #' . (int)($division['id'] ?? 0))));
}
$divisionLabel = static function (int $divisionId) use ($divisionNameById): string {
  return $divisionId > 0 ? (string)($divisionNameById[$divisionId] ?? ('Divisi #' . $divisionId)) : 'Semua Divisi';
};
$fmtQty = static function ($value): string { return number_format((float)$value, 2, ',', '.'); };
$fmtMoney = static function ($value): string { return 'Rp ' . number_format((float)$value, 2, ',', '.'); };
$destinationGuardMapJson = json_encode($destinationGuardMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$destinationOptionsJson = json_encode($destinationOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>

<style>
.transfer-hero {
  border: 1px solid rgba(148, 24, 43, .10);
  border-radius: 22px;
  padding: 1.1rem 1.2rem;
  background: radial-gradient(circle at top right, rgba(255,215,180,.45), transparent 33%), linear-gradient(135deg, #fffaf5, #fffdfb);
  box-shadow: 0 18px 35px rgba(113, 36, 20, .08);
}
.transfer-filter-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
@media (max-width: 991px) { .transfer-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 575px) { .transfer-filter-grid { grid-template-columns: 1fr; } }
.transfer-kpi-grid { display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: .8rem; margin-top: 1rem; }
@media (max-width: 991px) { .transfer-kpi-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
.transfer-kpi { border-radius: 18px; padding: 1rem 1.1rem; color: #fff; position: relative; overflow: hidden; box-shadow: 0 12px 30px rgba(17,24,39,.12); }
.transfer-kpi::after { content: ''; position: absolute; right: -18px; bottom: -24px; width: 92px; height: 92px; border-radius: 50%; background: rgba(255,255,255,.10); }
.transfer-kpi h6 { font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; opacity: .82; margin-bottom: .35rem; }
.transfer-kpi .value { font-size: 1.45rem; font-weight: 800; line-height: 1.05; }
.transfer-kpi .sub { font-size: .80rem; opacity: .85; margin-top: .25rem; }
.transfer-kpi.k1 { background: linear-gradient(135deg, #7f1d1d, #b91c1c); }
.transfer-kpi.k2 { background: linear-gradient(135deg, #0f766e, #14b8a6); }
.transfer-kpi.k3 { background: linear-gradient(135deg, #92400e, #f59e0b); }
.transfer-kpi.k4 { background: linear-gradient(135deg, #312e81, #4f46e5); }
.transfer-table-wrap { overflow: auto; max-height: 72vh; border-radius: 18px; border: 1px solid rgba(148, 24, 43, .10); }
.transfer-table-wrap table thead th { position: sticky; top: 0; z-index: 2; background: #fff7f2; white-space: nowrap; }
.transfer-lines { display:grid; gap:.45rem; }
.transfer-line-chip { border:1px solid rgba(148,24,43,.10); border-radius: 14px; padding:.55rem .75rem; background:linear-gradient(180deg,#fff,#fff8f5); }
.transfer-search-result { cursor:pointer; border:1px solid rgba(15,23,42,.08); border-radius: 14px; padding:.7rem .85rem; background:#fff; }
.transfer-search-result:hover { border-color: rgba(148,24,43,.24); background:#fff7f3; }
.transfer-search-result.is-disabled { opacity:.55; pointer-events:none; }
.transfer-selected-table td, .transfer-selected-table th { vertical-align: middle; }
.transfer-selected-table td:nth-child(2), .transfer-selected-table th:nth-child(2) { white-space: nowrap; }
.transfer-selected-table .js-transfer-qty { min-width: 124px; text-align: right; }
.transfer-selected-table .js-transfer-note { min-width: 120px; }
.transfer-route-pill { display:inline-flex; align-items:center; gap:.35rem; border-radius:999px; padding:.3rem .7rem; background:#fff2ea; color:#9a3412; font-weight:700; font-size:.76rem; }
.transfer-status-pill { display:inline-flex; align-items:center; border-radius:999px; padding:.28rem .7rem; font-size:.72rem; font-weight:700; }
.transfer-status-pill.draft { background:#fff7d6; color:#9a6700; }
.transfer-status-pill.posted { background:#dcfce7; color:#166534; }
.transfer-status-pill.void { background:#e5e7eb; color:#374151; }
.transfer-modal-shell { border-radius: 28px; overflow: hidden; background: linear-gradient(180deg, #fffdfc, #fff9f6); }
.transfer-modal-head { padding: 1.15rem 1.35rem 1rem; background: radial-gradient(circle at top right, rgba(255, 214, 170, .55), transparent 32%), linear-gradient(135deg, #fff8f3, #fffefd); border-bottom: 1px solid rgba(148,24,43,.08); }
.transfer-modal-title { font-size: 1.25rem; font-weight: 800; color: #1f2937; }
.transfer-modal-subtitle { color: #6b7280; font-size: .88rem; }
.transfer-modal-close { width: 36px; height: 36px; border-radius: 999px; border: 1px solid rgba(148,24,43,.14); background:#fff; color:#7f1d1d; display:inline-flex; align-items:center; justify-content:center; }
.transfer-modal-close:hover { background:#fff4ef; }
.transfer-modal-body { padding: 1.2rem 1.35rem 1.25rem; }
.transfer-modal-grid { display:grid; grid-template-columns: minmax(280px, 360px) minmax(0, 1fr); gap: 1rem; }
@media (max-width: 991px) { .transfer-modal-grid { grid-template-columns: 1fr; } }
.transfer-panel { border:1px solid rgba(148,24,43,.10); border-radius: 20px; background:#fff; box-shadow: 0 10px 25px rgba(17,24,39,.05); }
.transfer-panel-head { padding: .9rem 1rem .25rem; }
.transfer-panel-body { padding: .7rem 1rem 1rem; }
.transfer-route-card { border: 1px dashed rgba(148,24,43,.18); border-radius: 18px; padding: .9rem 1rem; background: linear-gradient(180deg, #fffaf6, #fff); }
.transfer-route-arrow { font-size: 1rem; color: #b45309; }
.transfer-results-wrap { min-height: 200px; max-height: 320px; overflow:auto; padding-right: .2rem; }
.transfer-empty-state { border:1px dashed rgba(15,23,42,.12); border-radius: 16px; padding: 1rem; text-align:center; color:#6b7280; background:#fffcfa; }
.transfer-loading { display:inline-flex; align-items:center; gap:.5rem; color:#6b7280; font-size:.88rem; }
.transfer-spinner { width: 16px; height: 16px; border-radius:50%; border:2px solid rgba(148,24,43,.16); border-right-color:#e11d48; border-top-color:#b91c1c; animation: transferSpin .65s linear infinite; flex:0 0 auto; }
.transfer-spinner.sm { width: 14px; height: 14px; border-width: 2px; }
.transfer-btn-busy { pointer-events:none; opacity:.88; }
.transfer-btn-loader { display:inline-flex; align-items:center; gap:.45rem; }
.transfer-inline-loader { display:inline-flex; align-items:center; gap:.4rem; font-size:.8rem; color:#6b7280; }
@keyframes transferSpin { to { transform: rotate(360deg); } }
</style>

<div class="mb-3">
  <?php $tab_scope = 'DIVISION'; $active_tab = 'transfer'; include APPPATH . 'views/purchase/_stock_group_tabs.php'; ?>
</div>

<div class="transfer-hero mb-4">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
      <div class="text-uppercase small font-weight-bold text-muted">Workspace Transfer</div>
      <h3 class="mb-1">Mutasi Bahan Baku Antar Divisi / Lokasi</h3>
      <div class="text-muted">Pindahkan stok live antar BAR, Kitchen, Event, atau Office dengan jejak lot, profil, movement log, dan dukungan VOID rollback.</div>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-danger" id="btnOpenTransferModal"><i class="ri-share-forward-2-line mr-1"></i>Buat Transfer</button>
    </div>
  </div>

  <form method="get" action="<?php echo $baseUrl; ?>" class="transfer-filter-grid">
    <div><label class="small font-weight-bold text-uppercase text-muted">Dari Tanggal</label><input type="date" name="date_from" value="<?php echo html_escape($dateFrom); ?>" class="form-control"></div>
    <div><label class="small font-weight-bold text-uppercase text-muted">Sampai Tanggal</label><input type="date" name="date_to" value="<?php echo html_escape($dateTo); ?>" class="form-control"></div>
    <div><label class="small font-weight-bold text-uppercase text-muted">Divisi Sumber</label><select name="from_division_id" class="form-control"><option value="0">Semua Divisi</option><?php foreach ($divisions as $division): $divId = (int)($division['id'] ?? 0); ?><option value="<?php echo $divId; ?>" <?php echo $fromDivisionId === $divId ? 'selected' : ''; ?>><?php echo html_escape((string)($division['division_name'] ?? ($division['name'] ?? ('Divisi #' . $divId)))); ?></option><?php endforeach; ?></select></div>
    <div><label class="small font-weight-bold text-uppercase text-muted">Lokasi Sumber</label><select name="from_destination" id="filterFromDestination" class="form-control"><option value="ALL">Semua</option><?php foreach ($destinationOptions as $key => $label): ?><option value="<?php echo $key; ?>" <?php echo $fromDestination === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option><?php endforeach; ?></select></div>
    <div><label class="small font-weight-bold text-uppercase text-muted">Divisi Tujuan</label><select name="to_division_id" class="form-control"><option value="0">Semua Divisi</option><?php foreach ($divisions as $division): $divId = (int)($division['id'] ?? 0); ?><option value="<?php echo $divId; ?>" <?php echo $toDivisionId === $divId ? 'selected' : ''; ?>><?php echo html_escape((string)($division['division_name'] ?? ($division['name'] ?? ('Divisi #' . $divId)))); ?></option><?php endforeach; ?></select></div>
    <div><label class="small font-weight-bold text-uppercase text-muted">Lokasi Tujuan</label><select name="to_destination" id="filterToDestination" class="form-control"><option value="ALL">Semua</option><?php foreach ($destinationOptions as $key => $label): ?><option value="<?php echo $key; ?>" <?php echo $toDestination === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option><?php endforeach; ?></select></div>
    <div><label class="small font-weight-bold text-uppercase text-muted">Cari</label><input type="text" name="q" value="<?php echo html_escape($q); ?>" class="form-control" placeholder="No transfer, profil, bahan, catatan"></div>
    <div><label class="small font-weight-bold text-uppercase text-muted">Limit</label><input type="number" min="10" max="500" step="10" name="limit" value="<?php echo (int)$limit; ?>" class="form-control"></div>
    <div class="d-flex align-items-end gap-2"><button type="submit" class="btn btn-danger"><i class="ri-filter-3-line mr-1"></i>Terapkan</button><a href="<?php echo $baseUrl; ?>" class="btn btn-outline-secondary">Reset</a></div>
  </form>

  <?php $docCount = count($rows); $draftCount = 0; $postedCount = 0; $voidCount = 0; $qtyTotal = 0.0; $valueTotal = 0.0; foreach ($rows as $row) { $status = strtoupper((string)($row['status'] ?? 'DRAFT')); if ($status === 'POSTED') { $postedCount++; } elseif ($status === 'VOID') { $voidCount++; } else { $draftCount++; } $qtyTotal += (float)($row['total_qty_transfer_content'] ?? 0); $valueTotal += (float)($row['total_transfer_value'] ?? 0); } ?>
  <div class="transfer-kpi-grid">
    <div class="transfer-kpi k1"><h6>Dokumen</h6><div class="value"><?php echo $docCount; ?></div><div class="sub"><?php echo $draftCount; ?> draft / <?php echo $postedCount; ?> posted / <?php echo $voidCount; ?> void</div></div>
    <div class="transfer-kpi k2"><h6>Total Qty</h6><div class="value"><?php echo $fmtQty($qtyTotal); ?></div><div class="sub">satuan isi yang dipindahkan</div></div>
    <div class="transfer-kpi k3"><h6>Total Nilai</h6><div class="value"><?php echo $fmtMoney($valueTotal); ?></div><div class="sub">berdasarkan HPP profil saat transfer</div></div>
    <div class="transfer-kpi k4"><h6>Filter Aktif</h6><div class="value"><?php echo html_escape($divisionLabel($fromDivisionId)); ?></div><div class="sub"><?php echo html_escape($destinationLabel($fromDestination)); ?> menuju <?php echo html_escape($divisionLabel($toDivisionId)); ?> / <?php echo html_escape($destinationLabel($toDestination)); ?></div></div>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body pb-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div><div class="text-uppercase small font-weight-bold text-muted">Daftar Dokumen</div><h5 class="mb-0">Transfer Bahan Baku</h5></div>
      <div class="text-muted small">Stok sumber akan berkurang dan stok tujuan akan bertambah saat dokumen diposting.</div>
    </div>
  </div>
  <div class="transfer-table-wrap">
    <table class="table table-hover mb-0">
      <thead><tr><th>No Transfer</th><th>Tanggal</th><th>Rute</th><th>Rincian</th><th>Qty</th><th>Nilai</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php if (empty($rows)): ?><tr><td colspan="8" class="text-center text-muted py-4">Belum ada dokumen transfer pada filter ini.</td></tr><?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <?php $status = strtoupper((string)($row['status'] ?? 'DRAFT')); $statusClass = $status === 'POSTED' ? 'posted' : ($status === 'VOID' ? 'void' : 'draft'); $lineRows = $this->Purchase_model->get_stock_transfer_lines((int)($row['id'] ?? 0)); ?>
          <tr id="stock-transfer-<?php echo (int)($row['id'] ?? 0); ?>">
            <td><div class="font-weight-bold"><?php echo html_escape((string)($row['transfer_no'] ?? '-')); ?></div><div class="small text-muted"><?php echo (int)($row['line_count'] ?? 0); ?> baris</div></td>
            <td><?php echo html_escape((string)($row['transfer_date'] ?? '-')); ?></td>
            <td><div class="transfer-route-pill mb-1"><?php echo html_escape((string)($row['from_division_name'] ?? ('Divisi #' . (int)($row['from_division_id'] ?? 0)))); ?> / <?php echo html_escape($destinationLabel((string)($row['from_destination_type'] ?? ''))); ?></div><div class="small text-muted">ke <?php echo html_escape((string)($row['to_division_name'] ?? ('Divisi #' . (int)($row['to_division_id'] ?? 0)))); ?> / <?php echo html_escape($destinationLabel((string)($row['to_destination_type'] ?? ''))); ?></div></td>
            <td style="min-width:280px;"><div class="transfer-lines"><?php foreach ($lineRows as $line): ?><div class="transfer-line-chip"><div class="font-weight-bold"><?php echo html_escape((string)($line['material_name'] ?? ($line['item_name'] ?? '-'))); ?></div><div class="small text-muted"><?php echo html_escape(trim((string)($line['profile_name'] ?? ''))); ?><?php echo !empty($line['profile_brand']) ? ' | ' . html_escape((string)$line['profile_brand']) : ''; ?></div><div class="small text-muted">Qty <?php echo $fmtQty((float)($line['qty_transfer_content'] ?? 0)); ?> <?php echo html_escape((string)($line['profile_content_uom_code'] ?? '-')); ?></div></div><?php endforeach; ?></div></td>
            <td><?php echo $fmtQty((float)($row['total_qty_transfer_content'] ?? 0)); ?></td>
            <td><?php echo $fmtMoney((float)($row['total_transfer_value'] ?? 0)); ?></td>
            <td><span class="transfer-status-pill <?php echo $statusClass; ?>"><?php echo html_escape($status); ?></span></td>
            <td><div class="d-flex flex-wrap gap-1"><?php if ($status === 'DRAFT'): ?><button type="button" class="btn btn-sm btn-danger js-transfer-post" data-id="<?php echo (int)($row['id'] ?? 0); ?>">Post</button><button type="button" class="btn btn-sm btn-outline-danger js-transfer-delete" data-id="<?php echo (int)($row['id'] ?? 0); ?>">Hapus</button><?php elseif ($status === 'POSTED'): ?><button type="button" class="btn btn-sm btn-outline-secondary js-transfer-void" data-id="<?php echo (int)($row['id'] ?? 0); ?>">Void</button><?php else: ?><span class="text-muted small">Terkunci</span><?php endif; ?></div></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="stockTransferModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
    <div class="modal-content border-0 shadow-lg transfer-modal-shell">
      <div class="transfer-modal-head d-flex justify-content-between align-items-start gap-3">
        <div>
          <div class="text-uppercase small font-weight-bold text-muted">Draft Transfer</div>
          <div class="transfer-modal-title">Mutasi Bahan Baku Antar Divisi</div>
          <div class="transfer-modal-subtitle">Pilih sumber dan tujuan, lalu ambil stok live dari sumber terpilih secara real-time.</div>
        </div>
        <button type="button" class="transfer-modal-close" id="btnCloseTransferModal" aria-label="Close"><i class="ri-close-line"></i></button>
      </div>
      <div class="transfer-modal-body">
        <div class="alert alert-danger d-none" id="transferModalError"></div>
        <div class="transfer-modal-grid">
          <div class="transfer-panel">
            <div class="transfer-panel-head">
              <div class="text-uppercase small font-weight-bold text-muted">Rute Transfer</div>
            </div>
            <div class="transfer-panel-body">
              <div class="form-group"><label>Tanggal Transfer</label><input type="date" id="transferDate" class="form-control" value="<?php echo html_escape($dateTo !== '' ? $dateTo : date('Y-m-d')); ?>"></div>
              <div class="form-group"><label>Divisi Sumber</label><select id="transferFromDivision" class="form-control"><option value="">Pilih divisi</option><?php foreach ($divisions as $division): $divId = (int)($division['id'] ?? 0); ?><option value="<?php echo $divId; ?>"><?php echo html_escape((string)($division['division_name'] ?? ($division['name'] ?? ('Divisi #' . $divId)))); ?></option><?php endforeach; ?></select></div>
              <div class="form-group"><label>Lokasi Sumber</label><select id="transferFromDestination" class="form-control" disabled><option value="">Pilih lokasi</option></select></div>
              <div class="form-group"><label>Divisi Tujuan</label><select id="transferToDivision" class="form-control"><option value="">Pilih divisi</option><?php foreach ($divisions as $division): $divId = (int)($division['id'] ?? 0); ?><option value="<?php echo $divId; ?>"><?php echo html_escape((string)($division['division_name'] ?? ($division['name'] ?? ('Divisi #' . $divId)))); ?></option><?php endforeach; ?></select></div>
              <div class="form-group"><label>Lokasi Tujuan</label><select id="transferToDestination" class="form-control" disabled><option value="">Pilih lokasi</option></select></div>
              <div class="form-group"><label>Catatan Header</label><input type="text" id="transferHeaderNotes" class="form-control" placeholder="Opsional"></div>
              <div class="transfer-route-card">
                <div class="small text-uppercase font-weight-bold text-muted mb-2">Preview Rute</div>
                <div class="d-flex align-items-center justify-content-between gap-2">
                  <div>
                    <div class="small text-muted">Sumber</div>
                    <div class="font-weight-bold" id="transferRouteFromLabel">Belum dipilih</div>
                  </div>
                  <div class="transfer-route-arrow"><i class="ri-arrow-right-line"></i></div>
                  <div class="text-right">
                    <div class="small text-muted">Tujuan</div>
                    <div class="font-weight-bold" id="transferRouteToLabel">Belum dipilih</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="transfer-panel">
            <div class="transfer-panel-head">
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                  <div class="text-uppercase small font-weight-bold text-muted">Ambil Dari Sumber Terpilih</div>
                  <div class="small text-muted">Pencarian AJAX akan otomatis menyesuaikan divisi dan lokasi sumber.</div>
                </div>
                <div class="input-group" style="max-width:360px;">
                  <input type="text" id="transferSearchInput" class="form-control" placeholder="Cari material, profil, merk" disabled>
                  <div class="input-group-append"><button class="btn btn-outline-secondary" type="button" id="btnTransferSearch" disabled>Cari</button></div>
                </div>
              </div>
            </div>
            <div class="transfer-panel-body">
              <div id="transferSearchResults" class="transfer-results-wrap mb-3">
                <div class="transfer-empty-state">Pilih divisi dan lokasi sumber dulu, lalu pencarian stok akan aktif otomatis.</div>
              </div>
              <div class="table-responsive border rounded">
                <table class="table table-sm mb-0 transfer-selected-table">
                  <thead class="thead-light"><tr><th style="min-width:220px;">Bahan / Profil</th><th style="width:120px;">Avail</th><th style="width:150px;">Qty Transfer</th><th style="width:135px;">Catatan</th><th style="width:60px;">#</th></tr></thead>
                  <tbody id="transferSelectedBody"><tr data-empty="1"><td colspan="5" class="text-center text-muted py-3">Belum ada baris transfer.</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="btnCancelTransferModal">Batal</button>
        <button type="button" class="btn btn-outline-danger" id="btnSaveTransferDraft"><span class="transfer-btn-label">Simpan Draft</span><span class="transfer-btn-loader d-none"><span class="transfer-spinner"></span><span class="transfer-btn-loader-text">Menyimpan...</span></span></button>
        <button type="button" class="btn btn-danger" id="btnSaveTransferPost"><span class="transfer-btn-label">Simpan &amp; Verifikasi</span><span class="transfer-btn-loader d-none"><span class="transfer-spinner"></span><span class="transfer-btn-loader-text">Menyimpan...</span></span></button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="stockTransferStepUpModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0 shadow-lg transfer-modal-shell">
      <div class="transfer-modal-head">
        <div class="text-uppercase small font-weight-bold text-muted">Verifikasi Ulang</div>
        <div class="transfer-modal-title" id="stockTransferStepUpTitle">Post Transfer Stok</div>
        <div class="transfer-modal-subtitle" id="stockTransferStepUpDescription">Masukkan password akun Anda untuk melanjutkan mutasi stok ini.</div>
      </div>
      <div class="transfer-modal-body">
        <div class="alert alert-danger d-none" id="stockTransferStepUpError"></div>
        <div class="form-group mb-0">
          <label for="stockTransferStepUpPassword">Password akun</label>
          <input type="password" class="form-control" id="stockTransferStepUpPassword" autocomplete="current-password" placeholder="Masukkan password Anda">
          <small class="form-text text-muted">Password hanya dipakai untuk verifikasi sekali dan tidak disimpan.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="btnStockTransferStepUpCancel">Batal</button>
        <button type="button" class="btn btn-danger" id="btnStockTransferStepUpSubmit">Verifikasi &amp; Lanjutkan</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const modalEl = document.getElementById('stockTransferModal');
  const errorEl = document.getElementById('transferModalError');
  const searchResultsEl = document.getElementById('transferSearchResults');
  const selectedBodyEl = document.getElementById('transferSelectedBody');
  const searchInputEl = document.getElementById('transferSearchInput');
  const searchButtonEl = document.getElementById('btnTransferSearch');
  const draftButtonEl = document.getElementById('btnSaveTransferDraft');
  const postButtonEl = document.getElementById('btnSaveTransferPost');
  const fromDivisionEl = document.getElementById('transferFromDivision');
  const fromDestinationEl = document.getElementById('transferFromDestination');
  const toDivisionEl = document.getElementById('transferToDivision');
  const toDestinationEl = document.getElementById('transferToDestination');
  const stepUpModalEl = document.getElementById('stockTransferStepUpModal');
  const stepUpPasswordEl = document.getElementById('stockTransferStepUpPassword');
  const stepUpErrorEl = document.getElementById('stockTransferStepUpError');
  const stepUpTitleEl = document.getElementById('stockTransferStepUpTitle');
  const stepUpDescriptionEl = document.getElementById('stockTransferStepUpDescription');
  const stepUpSubmitButtonEl = document.getElementById('btnStockTransferStepUpSubmit');
  const stockTransferCsrfToken = <?php echo json_encode($stockTransferCsrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const stockTransferStepUpUrl = <?php echo json_encode($stepUpUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const selectedLines = [];
  const destinationGuardMap = <?php echo $destinationGuardMapJson ?: '{}'; ?>;
  const destinationOptions = <?php echo $destinationOptionsJson ?: '{}'; ?>;
  let searchTimer = null;
  let submitLocked = false;
  let pendingTransferStepUp = null;

  function showToast(message, type) { if (window.Swal) { Swal.fire({ toast:true, position:'top-end', timer:2200, showConfirmButton:false, icon:type || 'success', title:message }); return; } alert(message); }
  function showError(message) { errorEl.textContent = message || 'Terjadi kesalahan.'; errorEl.classList.remove('d-none'); }
  function clearError() { errorEl.textContent = ''; errorEl.classList.add('d-none'); }
  function showStepUpError(message) { stepUpErrorEl.textContent = message || 'Verifikasi tidak dapat diproses.'; stepUpErrorEl.classList.remove('d-none'); }
  function clearStepUpError() { stepUpErrorEl.textContent = ''; stepUpErrorEl.classList.add('d-none'); }
  function getValue(id) { const el = document.getElementById(id); return el ? String(el.value || '').trim() : ''; }
  function rowKey(row) { return [row.item_id || 0, row.material_id || 0, row.buy_uom_id || 0, row.content_uom_id || 0, row.profile_key || ''].join('|'); }
  function escapeHtml(str) { return String(str || '').replace(/[&<>"']/g, function(m){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]; }); }
  function escapeAttr(str) { return escapeHtml(str).replace(/`/g, '&#096;'); }
  function formatQty(value) { return Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function findButtonLabelNode(button) {
    return button ? button.querySelector('.transfer-btn-label') : null;
  }
  function findButtonLoaderNode(button) {
    return button ? button.querySelector('.transfer-btn-loader') : null;
  }
  function setButtonBusy(button, busy, loadingText) {
    if (!button) return;
    button.classList.toggle('transfer-btn-busy', !!busy);
    button.disabled = !!busy;
    const label = findButtonLabelNode(button);
    const loader = findButtonLoaderNode(button);
    if (loader && loadingText) {
      const loaderText = loader.querySelector('.transfer-btn-loader-text');
      if (loaderText) loaderText.textContent = loadingText;
    }
    if (label) label.classList.toggle('d-none', !!busy);
    if (loader) loader.classList.toggle('d-none', !busy);
  }
  function setSubmitBusy(busy, mode) {
    submitLocked = !!busy;
    setButtonBusy(draftButtonEl, busy && mode === 'draft', 'Menyimpan...');
    setButtonBusy(postButtonEl, busy && mode === 'post', 'Posting...');
    if (!busy) {
      draftButtonEl.disabled = false;
      postButtonEl.disabled = false;
    } else {
      draftButtonEl.disabled = true;
      postButtonEl.disabled = true;
    }
  }
  function stockTransferMutationHeaders() {
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-Stock-Transfer-Csrf': stockTransferCsrfToken
    };
  }
  async function postStockTransferJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: stockTransferMutationHeaders(),
      body: JSON.stringify(payload || {})
    });
    let data = {};
    try { data = await response.json(); } catch (error) { throw new Error('Respons server transfer stok tidak valid.'); }
    if (!response.ok || !data.ok) throw new Error(data.message || 'Aksi transfer stok gagal diproses.');
    return data;
  }
  function closeTransferModal() {
    if (window.jQuery) {
      window.jQuery(modalEl).modal('hide');
    }
  }
  function closeTransferStepUpModal() {
    if (window.jQuery) window.jQuery(stepUpModalEl).modal('hide');
  }
  function openTransferStepUp(transferId, operation) {
    pendingTransferStepUp = { id: Number(transferId || 0), operation: String(operation || '').toUpperCase() };
    const isVoid = pendingTransferStepUp.operation === 'VOID';
    stepUpTitleEl.textContent = isVoid ? 'VOID Transfer Stok' : 'Post Transfer Stok';
    stepUpDescriptionEl.textContent = isVoid
      ? 'Masukkan password akun Anda untuk membatalkan transfer dan mengembalikan stok sumber/tujuan.'
      : 'Masukkan password akun Anda untuk memposting transfer dan memutasi stok sumber ke tujuan.';
    clearStepUpError();
    stepUpPasswordEl.value = '';
    if (window.jQuery) window.jQuery(stepUpModalEl).modal('show');
    window.setTimeout(function(){ stepUpPasswordEl.focus(); }, 180);
  }
  function getSelectedDivisionLabel(selectId) {
    const el = document.getElementById(selectId);
    return el && el.selectedIndex >= 0 ? String(el.options[el.selectedIndex].text || '').trim() : 'Belum dipilih';
  }
  function getDestinationChoices(divisionId) {
    const key = String(parseInt(divisionId || '0', 10) || 0);
    const fallback = Object.keys(destinationOptions);
    const allowed = Array.isArray(destinationGuardMap[key]) ? destinationGuardMap[key] : fallback;
    return allowed.filter(function(code){ return Object.prototype.hasOwnProperty.call(destinationOptions, code); });
  }
  function refillDestinationSelect(selectEl, divisionId, selectedValue, includeAllOption) {
    if (!selectEl) return;
    const choices = divisionId ? getDestinationChoices(divisionId) : [];
    const currentValue = String(selectedValue || '');
    const placeholder = includeAllOption ? 'Semua' : 'Pilih lokasi';
    let html = '<option value="' + (includeAllOption ? 'ALL' : '') + '">' + placeholder + '</option>';
    choices.forEach(function(code){
      const isSelected = currentValue !== '' && String(code) === currentValue;
      html += '<option value="' + code + '"' + (isSelected ? ' selected' : '') + '>' + escapeHtml(destinationOptions[code] || code) + '</option>';
    });
    selectEl.innerHTML = html;
    selectEl.disabled = !divisionId;
    if (!choices.length) {
      selectEl.value = includeAllOption ? 'ALL' : '';
      return;
    }
    if (currentValue && choices.indexOf(currentValue) >= 0) {
      selectEl.value = currentValue;
    } else {
      selectEl.value = includeAllOption ? 'ALL' : '';
    }
  }
  function updateRoutePreview() {
    const fromText = getValue('transferFromDivision') && getValue('transferFromDestination')
      ? getSelectedDivisionLabel('transferFromDivision') + ' / ' + getSelectedDivisionLabel('transferFromDestination')
      : 'Belum dipilih';
    const toText = getValue('transferToDivision') && getValue('transferToDestination')
      ? getSelectedDivisionLabel('transferToDivision') + ' / ' + getSelectedDivisionLabel('transferToDestination')
      : 'Belum dipilih';
    document.getElementById('transferRouteFromLabel').textContent = fromText;
    document.getElementById('transferRouteToLabel').textContent = toText;
  }
  function setSearchEnabled(enabled) {
    searchInputEl.disabled = !enabled;
    searchButtonEl.disabled = !enabled;
    if (!enabled) {
      searchResultsEl.innerHTML = '<div class="transfer-empty-state">Pilih divisi dan lokasi sumber dulu, lalu pencarian stok akan aktif otomatis.</div>';
    }
  }
  function resetSearchState() {
    searchInputEl.value = '';
    setSearchEnabled(!!(getValue('transferFromDivision') && getValue('transferFromDestination')));
  }
  function debounceSearch() {
    if (searchTimer) window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(function(){ doSearch(); }, 300);
  }

  function renderSelectedLines() {
    if (!selectedLines.length) { selectedBodyEl.innerHTML = '<tr data-empty="1"><td colspan="5" class="text-center text-muted py-3">Belum ada baris transfer.</td></tr>'; return; }
    selectedBodyEl.innerHTML = selectedLines.map((row, index) => {
      const materialName = row.material_name || row.item_name || '-';
      const profileLabel = [row.profile_name || '', row.profile_brand || ''].filter(Boolean).join(' | ');
      const uom = row.default_content_uom_code || row.profile_content_uom_code || '';
      return '<tr>'
        + '<td><div class="font-weight-bold">' + escapeHtml(materialName) + '</div><div class="small text-muted">' + escapeHtml(profileLabel || '-') + '</div></td>'
        + '<td>' + formatQty(row.available_qty_content) + ' ' + escapeHtml(uom) + '</td>'
        + '<td><input type="number" min="0.0001" step="0.0001" class="form-control form-control-sm js-transfer-qty" data-index="' + index + '" value="' + Number(row.qty_transfer_content || 0).toFixed(4) + '"></td>'
        + '<td><input type="text" class="form-control form-control-sm js-transfer-note" data-index="' + index + '" value="' + escapeAttr(row.note || '') + '" placeholder="Opsional"></td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-danger js-transfer-remove" data-index="' + index + '">&times;</button></td>'
        + '</tr>';
    }).join('');
  }

  function collectPayload() {
    return {
      transfer_date: getValue('transferDate'),
      from_division_id: parseInt(getValue('transferFromDivision') || '0', 10) || null,
      from_destination_type: getValue('transferFromDestination'),
      to_division_id: parseInt(getValue('transferToDivision') || '0', 10) || null,
      to_destination_type: getValue('transferToDestination'),
      notes: getValue('transferHeaderNotes'),
      auto_post: false,
      lines: selectedLines.map(function(row){ return {
        item_id: row.item_id || null,
        material_id: row.material_id || null,
        buy_uom_id: row.buy_uom_id || null,
        content_uom_id: row.content_uom_id || null,
        profile_key: row.profile_key || null,
        profile_name: row.profile_name || null,
        profile_brand: row.profile_brand || null,
        profile_description: row.profile_description || null,
        profile_expired_date: row.profile_expired_date || null,
        profile_content_per_buy: row.default_content_per_buy || row.profile_content_per_buy || 1,
        profile_buy_uom_code: row.default_buy_uom_code || row.profile_buy_uom_code || null,
        profile_content_uom_code: row.default_content_uom_code || row.profile_content_uom_code || null,
        qty_transfer_content: Number(row.qty_transfer_content || 0),
        note: row.note || ''
      }; })
    };
  }

  async function confirmTransferAction(confirmText) {
    if (!confirmText) return true;
    if (window.Swal) {
      const result = await Swal.fire({
        icon: 'warning',
        title: 'Konfirmasi',
        text: confirmText,
        showCancelButton: true,
        confirmButtonText: 'Lanjutkan',
        cancelButtonText: 'Batal',
      });
      return !!result.isConfirmed;
    }
    return window.confirm(confirmText);
  }

  async function runAction(url, method, message, options) {
    const opts = options || {};
    const button = opts.button || null;
    const busyText = opts.busyText || 'Memproses...';
    const confirmText = opts.confirmText || '';
    if (!(await confirmTransferAction(confirmText))) return;
    if (button) {
      const originalHtml = button.innerHTML;
      button.dataset.originalHtml = originalHtml;
      button.innerHTML = '<span class="transfer-inline-loader"><span class="transfer-spinner sm"></span>' + escapeHtml(busyText) + '</span>';
      button.disabled = true;
    }
    if (window.Swal) {
      Swal.fire({
        title: busyText,
        html: 'Mohon tunggu sebentar...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: function(){ Swal.showLoading(); }
      });
    }
    try {
      const response = await fetch(url, {
        method: method || 'POST',
        credentials: 'same-origin',
        headers: stockTransferMutationHeaders(),
        body: JSON.stringify({})
      });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || 'Aksi gagal diproses.');
      if (window.Swal) Swal.close();
      showToast(message, 'success');
      window.location.reload();
    } catch (error) {
      if (window.Swal) Swal.close();
      throw error;
    } finally {
      if (button) {
        button.innerHTML = button.dataset.originalHtml || button.innerHTML;
        button.disabled = false;
      }
    }
  }

  async function submitTransferStepUp() {
    const action = pendingTransferStepUp;
    if (!action || action.id <= 0 || !['POST', 'VOID'].includes(action.operation)) {
      showStepUpError('Dokumen transfer yang akan diverifikasi tidak valid. Tutup formulir lalu coba lagi.');
      return;
    }
    const password = String(stepUpPasswordEl.value || '');
    if (!password) {
      showStepUpError('Password akun wajib diisi untuk melanjutkan.');
      stepUpPasswordEl.focus();
      return;
    }
    clearStepUpError();
    stepUpSubmitButtonEl.disabled = true;
    const originalLabel = stepUpSubmitButtonEl.textContent;
    stepUpSubmitButtonEl.textContent = 'Memverifikasi...';
    stepUpPasswordEl.value = '';
    try {
      const verify = await postStockTransferJson(stockTransferStepUpUrl, {
        transfer_id: action.id,
        operation: action.operation,
        password: password
      });
      const writerBaseUrl = action.operation === 'VOID' ? '<?php echo $voidBaseUrl; ?>' : '<?php echo $postBaseUrl; ?>';
      await postStockTransferJson(writerBaseUrl + '/' + action.id, { step_up_proof: verify.step_up_proof });
      pendingTransferStepUp = null;
      closeTransferStepUpModal();
      showToast(action.operation === 'VOID' ? 'Transfer berhasil di-void.' : 'Transfer berhasil diposting.', 'success');
      window.location.reload();
    } catch (error) {
      showStepUpError(error.message || 'Verifikasi transfer stok gagal.');
    } finally {
      stepUpSubmitButtonEl.disabled = false;
      stepUpSubmitButtonEl.textContent = originalLabel;
    }
  }

  async function doSearch() {
    clearError();
    const divisionId = getValue('transferFromDivision');
    const destination = getValue('transferFromDestination');
    const q = getValue('transferSearchInput');
    if (!divisionId || !destination) { setSearchEnabled(false); return; }
    searchResultsEl.innerHTML = '<div class="transfer-loading"><span class="transfer-spinner"></span>Mencari stok sumber...</div>';
    const params = new URLSearchParams({ division_id: divisionId, destination: destination, q: q, limit: '20' });
    const response = await fetch('<?php echo $searchUrl; ?>?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await response.json();
    if (!response.ok || !data.ok) { searchResultsEl.innerHTML = ''; showError(data.message || 'Gagal mencari stok sumber.'); return; }
    const items = Array.isArray(data.items) ? data.items : [];
    if (!items.length) { searchResultsEl.innerHTML = '<div class="transfer-empty-state">Tidak ada stok sumber yang cocok untuk sumber terpilih.</div>'; return; }
    searchResultsEl.innerHTML = items.map(function(item, index){
      const materialName = item.material_name || item.item_name || '-';
      const profileLabel = [item.profile_name || '', item.profile_brand || ''].filter(Boolean).join(' | ');
      const uom = item.default_content_uom_code || item.profile_content_uom_code || '';
      return '<div class="transfer-search-result" data-index="' + index + '"><div class="d-flex justify-content-between gap-2"><div><div class="font-weight-bold">' + escapeHtml(materialName) + '</div><div class="small text-muted">' + escapeHtml(profileLabel || '-') + '</div></div><div class="text-right"><div class="font-weight-bold text-danger">' + formatQty(item.available_qty_content) + ' ' + escapeHtml(uom) + '</div><div class="small text-muted">stok sumber</div></div></div></div>';
    }).join('');
    searchResultsEl.querySelectorAll('.transfer-search-result').forEach(function(node, index){
      node.addEventListener('click', function(){
        const item = items[index];
        const candidate = {
          item_id: item.id || item.item_id || null,
          item_name: item.item_name || '',
          material_id: item.material_id || null,
          material_name: item.material_name || '',
          buy_uom_id: item.default_buy_uom_id || null,
          content_uom_id: item.default_content_uom_id || null,
          profile_key: item.profile_key || null,
          profile_name: item.profile_name || '',
          profile_brand: item.profile_brand || '',
          profile_description: item.profile_description || '',
          profile_expired_date: item.profile_expired_date || null,
          default_content_per_buy: item.default_content_per_buy || 1,
          default_buy_uom_code: item.default_buy_uom_code || '',
          default_content_uom_code: item.default_content_uom_code || '',
          available_qty_content: Number(item.available_qty_content || 0),
          qty_transfer_content: 0,
          note: ''
        };
        if (selectedLines.find(function(row){ return rowKey(row) === rowKey(candidate); })) { showToast('Baris ini sudah ada di draft transfer.', 'info'); return; }
        selectedLines.push(candidate);
        renderSelectedLines();
      });
    });
  }

  document.querySelector('select[name="from_division_id"]')?.addEventListener('change', function(){
    refillDestinationSelect(document.getElementById('filterFromDestination'), this.value, document.getElementById('filterFromDestination')?.value || 'ALL', true);
  });
  document.querySelector('select[name="to_division_id"]')?.addEventListener('change', function(){
    refillDestinationSelect(document.getElementById('filterToDestination'), this.value, document.getElementById('filterToDestination')?.value || 'ALL', true);
  });
  document.getElementById('btnOpenTransferModal')?.addEventListener('click', function(){
    clearError();
    refillDestinationSelect(fromDestinationEl, fromDivisionEl.value, fromDestinationEl.value, false);
    refillDestinationSelect(toDestinationEl, toDivisionEl.value, toDestinationEl.value, false);
    resetSearchState();
    updateRoutePreview();
    if (window.jQuery) { window.jQuery(modalEl).modal('show'); }
  });
  document.getElementById('btnTransferSearch')?.addEventListener('click', doSearch);
  document.getElementById('transferSearchInput')?.addEventListener('keydown', function(event){ if (event.key === 'Enter') { event.preventDefault(); doSearch(); } });
  document.getElementById('transferSearchInput')?.addEventListener('input', function(){ if (!searchInputEl.disabled) debounceSearch(); });
  ['transferFromDivision','transferFromDestination','transferToDivision','transferToDestination'].forEach(function(id){
    document.getElementById(id)?.addEventListener('change', function(){
      if (id === 'transferFromDivision') {
        refillDestinationSelect(fromDestinationEl, fromDivisionEl.value, '', false);
      }
      if (id === 'transferToDivision') {
        refillDestinationSelect(toDestinationEl, toDivisionEl.value, '', false);
      }
      updateRoutePreview();
      if (id === 'transferFromDivision' || id === 'transferFromDestination') {
        resetSearchState();
        if (!searchInputEl.disabled) debounceSearch();
      }
    });
  });
  document.getElementById('btnCancelTransferModal')?.addEventListener('click', closeTransferModal);
  document.getElementById('btnCloseTransferModal')?.addEventListener('click', closeTransferModal);

  selectedBodyEl.addEventListener('input', function(event){
    const target = event.target;
    const index = parseInt(target.getAttribute('data-index') || '-1', 10);
    if (index < 0 || !selectedLines[index]) return;
    if (target.classList.contains('js-transfer-qty')) selectedLines[index].qty_transfer_content = Number(target.value || 0);
    if (target.classList.contains('js-transfer-note')) selectedLines[index].note = target.value || '';
  });
  selectedBodyEl.addEventListener('click', function(event){
    const button = event.target.closest('.js-transfer-remove');
    if (!button) return;
    const index = parseInt(button.getAttribute('data-index') || '-1', 10);
    if (index < 0) return;
    selectedLines.splice(index, 1);
    renderSelectedLines();
  });

  async function saveTransfer(withVerification) {
    clearError();
    if (submitLocked) return;
    if (!selectedLines.length) { showError('Tambahkan minimal satu baris transfer.'); return; }
    const payload = collectPayload();
    setSubmitBusy(true, withVerification ? 'post' : 'draft');
    try {
      const data = await postStockTransferJson('<?php echo $storeUrl; ?>', payload);
      if (withVerification) {
        closeTransferModal();
        showToast('Draft transfer tersimpan. Lanjutkan dengan verifikasi password.', 'success');
        openTransferStepUp(data.id, 'POST');
        return;
      }
      showToast('Draft transfer berhasil disimpan.', 'success');
      window.location.reload();
    } catch (error) {
      showError(error.message || 'Gagal menyimpan transfer.');
    } finally {
      setSubmitBusy(false);
    }
  }

  document.getElementById('btnSaveTransferDraft')?.addEventListener('click', function(){ saveTransfer(false); });
  document.getElementById('btnSaveTransferPost')?.addEventListener('click', function(){ saveTransfer(true); });

  document.getElementById('btnStockTransferStepUpCancel')?.addEventListener('click', function(){ pendingTransferStepUp = null; closeTransferStepUpModal(); });
  stepUpSubmitButtonEl?.addEventListener('click', submitTransferStepUp);
  stepUpPasswordEl?.addEventListener('keydown', function(event){ if (event.key === 'Enter') { event.preventDefault(); submitTransferStepUp(); } });

  document.querySelectorAll('.js-transfer-post').forEach(function(button){ button.addEventListener('click', async function(){ if (await confirmTransferAction('Post transfer ini? Stok sumber akan berkurang dan stok tujuan akan bertambah.')) openTransferStepUp(button.getAttribute('data-id'), 'POST'); }); });
  document.querySelectorAll('.js-transfer-delete').forEach(function(button){ button.addEventListener('click', function(){ runAction('<?php echo $deleteBaseUrl; ?>/' + button.getAttribute('data-id'), 'POST', 'Draft transfer berhasil dihapus.', { button: button, busyText: 'Menghapus...', confirmText: 'Hapus draft transfer ini?' }).catch(function(error){ showToast(error.message, 'error'); }); }); });
  document.querySelectorAll('.js-transfer-void').forEach(function(button){ button.addEventListener('click', async function(){ if (await confirmTransferAction('VOID transfer ini dan rollback stok sumber/tujuan?')) openTransferStepUp(button.getAttribute('data-id'), 'VOID'); }); });
  refillDestinationSelect(document.getElementById('filterFromDestination'), document.querySelector('select[name="from_division_id"]')?.value || '', '<?php echo html_escape($fromDestination); ?>', true);
  refillDestinationSelect(document.getElementById('filterToDestination'), document.querySelector('select[name="to_division_id"]')?.value || '', '<?php echo html_escape($toDestination); ?>', true);
  refillDestinationSelect(fromDestinationEl, fromDivisionEl.value, fromDestinationEl.value, false);
  refillDestinationSelect(toDestinationEl, toDivisionEl.value, toDestinationEl.value, false);
  updateRoutePreview();
})();
</script>
