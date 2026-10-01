<?php
$filters = is_array($filters ?? null) ? $filters : [];
$summary = is_array($summary ?? null) ? $summary : [];
$rows = is_array($rows ?? null) ? $rows : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$pg = is_array($pg ?? null) ? $pg : [];
$canMaterialRecon = !empty($can_material_recon);
$canWarehouseRecon = !empty($can_warehouse_recon);
$canComponentRecon = !empty($can_component_recon);

$baseUrl = site_url('inventory/stock/deficits');
$fmtQty = static fn($value): string => number_format((float)$value, 4, ',', '.');
$fmtMoney = static fn($value): string => 'Rp ' . number_format((float)$value, 2, ',', '.');
$statusClass = static function (string $status): string {
    return match (strtoupper($status)) {
        'OPEN' => 'stock-control-chip danger',
        'SETTLED' => 'stock-control-chip success',
        'WRITTEN_OFF' => 'stock-control-chip muted',
        'VOID' => 'stock-control-chip muted',
        default => 'stock-control-chip muted',
    };
};
$statusLabel = static function (string $status): string {
    return match (strtoupper($status)) {
        'OPEN' => 'Masih terbuka',
        'SETTLED' => 'Sudah tertutup',
        'WRITTEN_OFF' => 'Ditutup administratif',
        'VOID' => 'Dibatalkan',
        default => $status !== '' ? $status : '-',
    };
};
$query = [
    'q' => (string)($filters['q'] ?? ''),
    'status' => (string)($filters['status'] ?? ''),
    'stock_domain' => (string)($filters['stock_domain'] ?? ''),
    'date_from' => (string)($filters['date_from'] ?? ''),
    'date_to' => (string)($filters['date_to'] ?? ''),
    'division_id' => (int)($filters['division_id'] ?? 0) ?: '',
    'location_scope' => (string)($filters['location_scope'] ?? ''),
    'destination_type' => (string)($filters['destination_type'] ?? ''),
    'per_page' => (int)($pg['per_page'] ?? 50),
];
$pageLink = static function (int $page) use ($baseUrl, $query): string {
    return $baseUrl . '?' . http_build_query(array_filter($query + ['page' => $page], static fn($v) => $v !== ''));
};
?>

<style>
.stock-control-shell { --sc-ink:#3d2926; --sc-muted:#876d65; --sc-line:#ecdcd3; --sc-paper:#fffdfa; --sc-red:#a90e25; --sc-red-dark:#780918; --sc-green:#19764f; }
.stock-control-intro { border:1px solid var(--sc-line); border-radius:18px; background:linear-gradient(135deg,#fffdf9 0%,#fff5ef 100%); padding:1rem 1.1rem; box-shadow:0 8px 25px rgba(91,39,29,.06); }.stock-control-intro h4{color:var(--sc-ink);font-weight:850}.stock-control-intro p{color:var(--sc-muted);margin:0;max-width:980px}
.stock-control-note { border-radius:12px; border:1px solid #f3dac6; background:#fff9ee; color:#805420; padding:.68rem .82rem; font-size:.79rem; }
.stock-control-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.65rem;margin:1rem 0}.stock-control-kpi{min-height:86px;border:1px solid var(--sc-line);border-radius:14px;background:var(--sc-paper);padding:.7rem .85rem;box-shadow:0 5px 14px rgba(75,42,32,.04)}.stock-control-kpi .label{display:block;color:var(--sc-muted);font-size:.65rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase}.stock-control-kpi .value{display:block;color:var(--sc-ink);font-weight:900;font-size:1.15rem;line-height:1.25;margin-top:.28rem}.stock-control-kpi.is-danger{background:#fff4f2;border-color:#f6c8c0}.stock-control-kpi.is-danger .value{color:#b42318}.stock-control-kpi.is-green{background:#f1faf5;border-color:#ccead8}.stock-control-kpi.is-green .value{color:var(--sc-green)}
.stock-control-filter{border:1px solid var(--sc-line);border-radius:16px;background:#fff;padding:.8rem .9rem}.stock-control-filter-grid{display:grid;grid-template-columns:minmax(190px,1.45fr) 130px 130px 132px 132px 150px 92px auto;gap:.55rem;align-items:end}.stock-control-filter label{font-size:.7rem;font-weight:800;color:#77584f;margin:0 0 .25rem}
.stock-control-table-card{border:1px solid var(--sc-line);border-radius:18px;overflow:hidden;background:#fff;box-shadow:0 10px 26px rgba(75,42,32,.06)}.stock-control-table-wrap{overflow:auto;max-height:66vh}.stock-control-table{min-width:1280px;margin:0;border-collapse:separate;border-spacing:0}.stock-control-table thead th{position:sticky;top:0;z-index:3;background:linear-gradient(180deg,var(--sc-red) 0%,var(--sc-red-dark) 100%);color:#fff7f3;font-size:.7rem;letter-spacing:.04em;white-space:nowrap;border-color:#8e0d20}.stock-control-table td{vertical-align:middle;border-color:#f0dfd7;font-size:.8rem;color:var(--sc-ink)}.stock-control-table tbody tr:nth-child(even) td{background:#fffaf7}.stock-control-table tbody tr:hover td{background:#fff2ec}.stock-control-name{font-weight:850;color:#402620}.stock-control-sub{font-size:.68rem;color:#9b7c71;margin-top:.14rem}.stock-control-chip{display:inline-flex;align-items:center;border-radius:999px;padding:.22rem .55rem;font-size:.65rem;font-weight:850;white-space:nowrap}.stock-control-chip.danger{background:#fde9e8;color:#b42318}.stock-control-chip.success{background:#e8f7ee;color:#19764f}.stock-control-chip.muted{background:#f0eeed;color:#766d69}.stock-control-pagination{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;padding:.8rem .9rem;border-top:1px solid var(--sc-line)}.stock-control-snapshot{display:grid;gap:.18rem;min-width:145px}.stock-control-snapshot b{font-weight:850;color:#3d2926}.stock-control-snapshot .lot{color:#19764f}.stock-control-snapshot.empty{color:#9b7c71;font-size:.72rem;line-height:1.35}
@media(max-width:1199px){.stock-control-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.stock-control-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:640px){.stock-control-kpis,.stock-control-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.stock-control-filter-grid .wide{grid-column:span 2}}
</style>

<div class="stock-control-shell">
  <section class="stock-control-intro mb-3">
    <h4 class="mb-1"><i class="ri ri-error-warning-line page-title-icon"></i><?php echo html_escape((string)($page_title ?? 'Defisit Stok')); ?></h4>
    <p>Defisit adalah jejak kekurangan saat barang pernah dipakai atau keluar ketika lot belum cukup. Saldo lot tidak dibuat minus. Karena stok bisa datang setelah kejadian itu, halaman ini juga menampilkan stok sistem dan lot aktif saat ini sebelum Anda memutuskan perlu tidaknya recon.</p>
  </section>

  <div class="stock-control-note mb-3"><strong>Cara membaca:</strong> satu baris adalah sisa defisit untuk satu barang, area, UOM, dan profil yang sama. Kolom <strong>Stok sistem / lot aktif</strong> adalah keadaan hari ini, bukan angka defisit. Bila keduanya sudah ada, Anda dapat melakukan <strong>Recon Stok Fisik &amp; Defisit</strong> langsung dari halaman ini.</div>
  <?php if (($filters['location_scope'] ?? '') !== '' || ($filters['destination_type'] ?? '') !== ''): ?>
    <?php
      $locationLabel = trim(implode(' / ', array_filter([
          (string)($filters['location_scope'] ?? ''),
          (string)($filters['destination_type'] ?? ''),
      ])));
      $clearLocationQuery = $query;
      $clearLocationQuery['location_scope'] = '';
      $clearLocationQuery['destination_type'] = '';
      $clearLocationUrl = $baseUrl . '?' . http_build_query(array_filter($clearLocationQuery, static fn($value) => $value !== ''));
    ?>
    <div class="alert alert-info py-2 px-3 small mb-3">Menampilkan defisit untuk lokasi <strong><?php echo html_escape($locationLabel); ?></strong> dari dashboard. <a href="<?php echo html_escape($clearLocationUrl); ?>">Tampilkan semua lokasi</a></div>
  <?php endif; ?>

  <div class="stock-control-kpis">
    <div class="stock-control-kpi is-danger"><span class="label">Profil defisit pada daftar</span><span class="value"><?php echo number_format((int)($summary['group_count'] ?? 0), 0, ',', '.'); ?></span></div>
    <div class="stock-control-kpi"><span class="label">Jejak transaksi sumber</span><span class="value"><?php echo number_format((int)($summary['total_rows'] ?? 0), 0, ',', '.'); ?></span></div>
    <div class="stock-control-kpi is-danger"><span class="label">Kejadian terbuka</span><span class="value"><?php echo number_format((int)($summary['open_rows'] ?? 0), 0, ',', '.'); ?></span></div>
    <div class="stock-control-kpi is-danger"><span class="label">Sisa qty terbuka</span><span class="value"><?php echo $fmtQty($summary['open_qty'] ?? 0); ?></span></div>
    <div class="stock-control-kpi"><span class="label">Perkiraan nilai terbuka</span><span class="value" style="font-size:.96rem"><?php echo $fmtMoney($summary['open_value'] ?? 0); ?></span></div>
  </div>

  <form method="get" action="<?php echo $baseUrl; ?>" class="stock-control-filter mb-3">
    <?php if (($filters['location_scope'] ?? '') !== ''): ?><input type="hidden" name="location_scope" value="<?php echo html_escape((string)$filters['location_scope']); ?>"><?php endif; ?>
    <?php if (($filters['destination_type'] ?? '') !== ''): ?><input type="hidden" name="destination_type" value="<?php echo html_escape((string)$filters['destination_type']); ?>"><?php endif; ?>
    <div class="stock-control-filter-grid">
      <div class="wide"><label>Cari barang / profil / sumber</label><input type="text" class="form-control form-control-sm" name="q" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama barang, component, profil, atau sumber transaksi"></div>
      <div><label>Status</label><select class="form-select form-select-sm" name="status"><option value="">Semua</option><?php foreach (['OPEN' => 'Masih terbuka', 'SETTLED' => 'Sudah tertutup', 'WRITTEN_OFF' => 'Ditutup administratif', 'VOID' => 'Dibatalkan'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo (($filters['status'] ?? '') === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
      <div><label>Jenis stok</label><select class="form-select form-select-sm" name="stock_domain"><option value="">Semua</option><option value="MATERIAL" <?php echo (($filters['stock_domain'] ?? '') === 'MATERIAL') ? 'selected' : ''; ?>>Bahan baku</option><option value="COMPONENT" <?php echo (($filters['stock_domain'] ?? '') === 'COMPONENT') ? 'selected' : ''; ?>>Component</option></select></div>
      <div><label>Dari tanggal</label><input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo html_escape((string)($filters['date_from'] ?? '')); ?>"></div>
      <div><label>Sampai tanggal</label><input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo html_escape((string)($filters['date_to'] ?? '')); ?>"></div>
      <div><label>Divisi</label><select class="form-select form-select-sm" name="division_id"><option value="0">Semua divisi</option><?php foreach ($divisions as $division): ?><option value="<?php echo (int)($division['id'] ?? 0); ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)($division['id'] ?? 0)) ? 'selected' : ''; ?>><?php echo html_escape((string)($division['name'] ?? $division['code'] ?? '-')); ?></option><?php endforeach; ?></select></div>
      <div><label>Baris</label><select class="form-select form-select-sm" name="per_page"><?php foreach ([25,50,100] as $per): ?><option value="<?php echo $per; ?>" <?php echo ((int)($pg['per_page'] ?? 50) === $per) ? 'selected' : ''; ?>><?php echo $per; ?></option><?php endforeach; ?></select></div>
      <div class="d-flex gap-2"><button class="btn btn-danger btn-sm px-3" type="submit">Terapkan</button><a class="btn btn-outline-secondary btn-sm" href="<?php echo $baseUrl; ?>">Reset</a></div>
    </div>
  </form>

  <div class="stock-control-table-card">
    <div class="stock-control-table-wrap">
      <table class="table table-sm stock-control-table align-middle">
        <thead><tr><th>Aktivitas terakhir</th><th>Barang / profil</th><th>Area</th><th>Stok sistem / lot aktif</th><th class="text-end">Sisa defisit</th><th class="text-center">Jejak</th><th class="text-end">Perkiraan nilai</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada defisit stok untuk filter ini.</td></tr>
        <?php else: foreach ($rows as $row): ?>
          <?php
          $isMaterial = strtoupper((string)($row['stock_domain'] ?? '')) === 'MATERIAL';
          $isComponent = strtoupper((string)($row['stock_domain'] ?? '')) === 'COMPONENT';
          $isDivision = (int)($row['division_id'] ?? 0) > 0;
          $isOpen = strtoupper((string)($row['status'] ?? '')) === 'OPEN';
          $profileKey = trim((string)($row['profile_key'] ?? ''));
          $canInlineRecon = false;
          $inlineReconUrl = '';
          $inlineReconPayload = [];
          if ($canMaterialRecon && $isOpen && $isMaterial && $isDivision && $profileKey !== '') {
              $canInlineRecon = true;
              $inlineReconUrl = site_url('inventory/stock/daily-recon/division/quick-adjust');
              $inlineReconPayload = [
                  'division_id' => (int)($row['division_id'] ?? 0),
                  'destination_type' => (string)($row['destination_type'] ?? 'OTHER'),
                  'identity_key' => $profileKey,
                  'profile_key' => $profileKey,
                  'item_id' => (int)($row['item_id'] ?? 0),
                  'material_id' => (int)($row['material_id'] ?? 0),
                  'buy_uom_id' => (int)($row['buy_uom_id'] ?? 0),
                  'content_uom_id' => (int)($row['content_uom_id'] ?? 0),
              ];
          } elseif ($canWarehouseRecon && $isOpen && $isMaterial && !$isDivision
              && strtoupper((string)($row['location_scope'] ?? '')) === 'WAREHOUSE') {
              $canInlineRecon = true;
              $inlineReconUrl = site_url('inventory/stock/daily-recon/warehouse/quick-adjust');
              $inlineReconPayload = [
                  'identity_key' => $profileKey,
                  'profile_key' => $profileKey,
                  'item_id' => (int)($row['item_id'] ?? 0),
                  'material_id' => (int)($row['material_id'] ?? 0),
                  'buy_uom_id' => (int)($row['buy_uom_id'] ?? 0),
                  'content_uom_id' => (int)($row['content_uom_id'] ?? 0),
              ];
          } elseif ($canComponentRecon && $isOpen && $isComponent && $isDivision) {
              $locationScope = strtoupper((string)($row['location_scope'] ?? ''));
              $canInlineRecon = true;
              $inlineReconUrl = site_url('production/component-daily-recon/quick-adjust');
              $inlineReconPayload = [
                  'location_type' => substr($locationScope, -6) === '_EVENT' ? 'EVENT' : 'REGULER',
                  'division_id' => (int)($row['division_id'] ?? 0),
                  'component_id' => (int)($row['component_id'] ?? 0),
                  'uom_id' => (int)($row['content_uom_id'] ?? 0),
                  'lot_id' => 0,
              ];
          }
          ?>
          <tr>
            <td><div><?php echo html_escape((string)($row['last_deficit_date'] ?? '-')); ?></div><div class="stock-control-sub">Update <?php echo html_escape((string)($row['last_activity_at'] ?? '-')); ?></div></td>
            <td><div class="stock-control-name"><?php echo html_escape((string)($row['inventory_name'] ?? '-')); ?></div><div class="stock-control-sub"><?php echo html_escape((string)($row['profile_label'] ?? '-')); ?></div><div class="stock-control-sub"><?php echo strtoupper((string)($row['stock_domain'] ?? '')); ?><?php echo !empty($row['uom_code']) ? ' | ' . html_escape((string)$row['uom_code']) : ''; ?></div></td>
            <td><div><?php echo html_escape((string)($row['division_name'] ?? $row['location_scope'] ?? '-')); ?></div><div class="stock-control-sub"><?php echo html_escape((string)($row['destination_type'] ?? $row['location_scope'] ?? '-')); ?></div></td>
            <td><?php if (!empty($row['live_snapshot_found'])): ?><div class="stock-control-snapshot"><div>Stok: <b><?php echo $fmtQty($row['system_qty'] ?? 0); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></b></div><div class="lot">Lot: <b><?php echo $fmtQty($row['active_lot_qty'] ?? 0); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></b> / <?php echo number_format((int)($row['active_lot_count'] ?? 0), 0, ',', '.'); ?> lot</div><div class="stock-control-sub">Bulan aktif <?php echo html_escape(date('m/Y', strtotime((string)($row['active_stock_month'] ?? date('Y-m-01'))))); ?></div></div><?php else: ?><div class="stock-control-snapshot empty">Belum ada saldo atau lot aktif untuk identitas ini pada bulan berjalan.</div><?php endif; ?></td>
            <td class="text-end fw-bold text-danger"><?php echo $fmtQty($row['qty_remaining'] ?? 0); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></td>
            <td class="text-center"><span class="badge text-bg-light border"><?php echo number_format((int)($row['event_count'] ?? 0), 0, ',', '.'); ?> kejadian</span><div class="stock-control-sub"><?php echo html_escape((string)($row['first_deficit_date'] ?? '-')); ?> s/d <?php echo html_escape((string)($row['last_deficit_date'] ?? '-')); ?></div></td>
            <td class="text-end"><?php echo $fmtMoney($row['estimated_total_value'] ?? 0); ?></td>
            <td><span class="<?php echo $statusClass((string)($row['status'] ?? '')); ?>"><?php echo $statusLabel((string)($row['status'] ?? '')); ?></span></td>
            <td class="text-center"><div class="d-flex justify-content-center gap-1 flex-wrap"><a class="btn btn-outline-danger btn-sm" href="<?php echo site_url('inventory/stock/deficits/detail/' . (int)($row['id'] ?? 0)); ?>">Rincian</a><?php if ($canInlineRecon): ?><button type="button" class="btn btn-outline-secondary btn-sm js-deficit-inline-recon" data-recon-url="<?php echo html_escape($inlineReconUrl); ?>" data-recon-payload="<?php echo html_escape((string)json_encode($inlineReconPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>" data-deficit-id="<?php echo (int)($row['id'] ?? 0); ?>" data-inventory-name="<?php echo html_escape((string)($row['inventory_name'] ?? '-')); ?>" data-profile-label="<?php echo html_escape((string)($row['profile_label'] ?? '-')); ?>" data-uom-code="<?php echo html_escape((string)($row['uom_code'] ?? '')); ?>" data-deficit-qty="<?php echo html_escape((string)($row['qty_remaining'] ?? 0)); ?>" data-system-qty="<?php echo html_escape((string)($row['system_qty'] ?? 0)); ?>" data-lot-qty="<?php echo html_escape((string)($row['active_lot_qty'] ?? 0)); ?>" data-lot-count="<?php echo (int)($row['active_lot_count'] ?? 0); ?>" data-active-month="<?php echo html_escape((string)($row['active_stock_month'] ?? '')); ?>" data-is-component="<?php echo $isComponent ? '1' : '0'; ?>">Recon</button><?php endif; ?></div></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="stock-control-pagination">
      <span class="small text-muted me-2">Menampilkan <?php echo number_format(count($rows), 0, ',', '.'); ?> dari <?php echo number_format((int)($pg['total'] ?? 0), 0, ',', '.'); ?> profil defisit.</span>
      <?php if ((int)($pg['page'] ?? 1) > 1): ?><a class="btn btn-outline-secondary btn-sm" href="<?php echo $pageLink((int)$pg['page'] - 1); ?>">Sebelumnya</a><?php endif; ?>
      <span class="small fw-semibold">Halaman <?php echo (int)($pg['page'] ?? 1); ?> / <?php echo (int)($pg['total_pages'] ?? 1); ?></span>
      <?php if ((int)($pg['page'] ?? 1) < (int)($pg['total_pages'] ?? 1)): ?><a class="btn btn-outline-secondary btn-sm" href="<?php echo $pageLink((int)$pg['page'] + 1); ?>">Berikutnya</a><?php endif; ?>
    </div>
  </div>
</div>

<div class="modal fade" id="deficitInlineReconModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><div><h5 class="modal-title mb-1">Recon Stok Fisik &amp; Defisit</h5><small class="text-muted">Catat hasil hitung fisik dahulu, lalu pilih apakah defisit yang sama juga memang selesai.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body">
      <div class="alert alert-warning small mb-3"><strong>Yang dilakukan layar ini:</strong> stok fisik akan disamakan dengan hasil hitungan Anda. Jika angkanya sama dengan stok sistem, tidak ada perubahan stok atau lot. Centang penyelesaian defisit hanya bila kekurangan lama memang sudah tertutup oleh barang yang sekarang tersedia.</div>
      <div class="border rounded-3 p-3 bg-light mb-3"><strong id="deficitReconName">-</strong><div class="small text-muted" id="deficitReconProfile">-</div><div class="row g-2 mt-1"><div class="col-md-4"><div class="small text-muted">Defisit terbuka</div><strong class="text-danger" id="deficitReconRemaining">-</strong></div><div class="col-md-4"><div class="small text-muted">Stok sistem bulan aktif</div><strong id="deficitReconSystemQty">-</strong></div><div class="col-md-4"><div class="small text-muted">Lot aktif sekarang</div><strong id="deficitReconLotQty">-</strong></div></div></div>
      <div class="row g-3"><div class="col-md-5"><label class="form-label">Tanggal hitung fisik</label><input type="date" class="form-control" id="deficitReconDate" value="<?php echo date('Y-m-d'); ?>"></div><div class="col-md-7"><label class="form-label">Stok fisik sekarang <span id="deficitReconUom"></span></label><div class="input-group"><input type="number" class="form-control" id="deficitReconPhysicalQty" min="0" step="0.0001" placeholder="Masukkan hasil hitung fisik"><button type="button" class="btn btn-outline-secondary" id="btnDeficitReconUseSystem">Gunakan stok sistem</button></div><div class="form-text">Masukkan jumlah akhir yang benar-benar dihitung, bukan selisih tambah atau kurang.</div></div></div>
      <div class="alert alert-info small mt-3 mb-3" id="deficitReconOutcome">Masukkan hasil hitung fisik untuk melihat dampaknya sebelum disimpan.</div>
      <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="deficitReconSettle" checked><label class="form-check-label" for="deficitReconSettle">Selesaikan defisit terbuka setelah stok fisik dicatat</label><div class="form-text" id="deficitReconSettleHint">Defisit hanya dicocokkan pada barang, area, UOM, dan profil yang sama. Hilangkan centang jika selisih lama masih perlu ditelusuri.</div></div>
      <div class="mb-0"><label class="form-label">Catatan</label><textarea class="form-control" id="deficitReconNotes" rows="2" placeholder="Contoh: stok fisik ditemukan saat cek rak kitchen"></textarea></div>
      <div class="alert alert-danger d-none mt-3 mb-0" id="deficitReconError"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-danger" id="btnDeficitReconSave" disabled>Simpan Recon</button></div>
  </div></div>
</div>
<script>
(function () {
    function initDeficitRecon() {
        const modalNode = document.getElementById('deficitInlineReconModal');
        const saveButton = document.getElementById('btnDeficitReconSave');
        if (!modalNode || !saveButton) return;

        const errorBox = document.getElementById('deficitReconError');
        const physicalInput = document.getElementById('deficitReconPhysicalQty');
        const settleInput = document.getElementById('deficitReconSettle');
        const settleHint = document.getElementById('deficitReconSettleHint');
        const outcomeBox = document.getElementById('deficitReconOutcome');
        const useSystemButton = document.getElementById('btnDeficitReconUseSystem');
        let activeRecon = null;
        let bootstrapModal = null;
        let fallbackBackdrop = null;
        let fallbackOpen = false;

        const plainMessage = function (text) {
            const holder = document.createElement('div');
            holder.innerHTML = text || '';
            return (holder.textContent || holder.innerText || '').replace(/\s+/g, ' ').trim();
        };
        const showError = function (message) { errorBox.textContent = message; errorBox.classList.remove('d-none'); };
        const hideError = function () { errorBox.textContent = ''; errorBox.classList.add('d-none'); };
        const formatQty = function (value) { return Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 4 }); };
        const withUom = function (value) { return formatQty(value) + (activeRecon && activeRecon.uomCode ? ' ' + activeRecon.uomCode : ''); };
        const setSaveEnabled = function (enabled) { saveButton.disabled = !enabled; };
        const setSettlementEnabled = function (enabled, hint) {
            const wasDisabled = settleInput.disabled;
            settleInput.disabled = !enabled;
            const group = settleInput.closest('.form-check');
            if (group) group.classList.toggle('opacity-50', !enabled);
            if (!enabled) settleInput.checked = false;
            if (enabled && wasDisabled) settleInput.checked = true;
            settleHint.textContent = hint;
        };

        const showModal = function () {
            if (window.bootstrap && window.bootstrap.Modal) {
                bootstrapModal = window.bootstrap.Modal.getOrCreateInstance(modalNode);
                bootstrapModal.show();
                return;
            }
            fallbackOpen = true;
            modalNode.style.display = 'block';
            modalNode.classList.add('show');
            modalNode.removeAttribute('aria-hidden');
            modalNode.setAttribute('aria-modal', 'true');
            document.body.classList.add('modal-open');
            fallbackBackdrop = document.createElement('div');
            fallbackBackdrop.className = 'modal-backdrop fade show';
            fallbackBackdrop.addEventListener('click', hideModal);
            document.body.appendChild(fallbackBackdrop);
        };
        const hideModal = function () {
            if (!fallbackOpen && bootstrapModal) {
                bootstrapModal.hide();
                return;
            }
            fallbackOpen = false;
            modalNode.classList.remove('show');
            modalNode.style.display = 'none';
            modalNode.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            if (fallbackBackdrop) {
                fallbackBackdrop.remove();
                fallbackBackdrop = null;
            }
        };
        modalNode.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (fallbackOpen) hideModal();
            });
        });

        const updateOutcome = function () {
            if (!activeRecon) return;
            const physicalQty = Number(physicalInput.value);
            const systemQty = Number(activeRecon.systemQty || 0);
            if (!Number.isFinite(physicalQty) || physicalInput.value === '') {
                setSaveEnabled(false);
                setSettlementEnabled(false, 'Masukkan hasil hitung fisik terlebih dahulu. Pilihan ini baru tersedia bila ada stok fisik yang dapat membuktikan defisit telah selesai.');
                outcomeBox.textContent = 'Masukkan hasil hitung fisik. Stok sistem saat ini ' + withUom(systemQty) + '; lot aktif ' + withUom(activeRecon.lotQty) + '.';
                return;
            }
            if (Math.abs(physicalQty - systemQty) <= 0.0001) {
                if (physicalQty <= 0.0001) {
                    setSaveEnabled(false);
                    setSettlementEnabled(false, 'Tidak dapat diselesaikan dari stok nol. Cari barang fisik yang belum tercatat, tunggu penerimaan/batch yang sesuai, atau batalkan sumber pemakaian yang memang salah.');
                    outcomeBox.textContent = 'Stok sistem dan lot tetap nol. Defisit tidak dapat ditutup dari angka nol; masukkan hasil fisik yang benar bila barang memang ada, atau biarkan defisit terbuka untuk ditelusuri.';
                    return;
                }
                setSettlementEnabled(true, 'Dicentang: hanya jejak defisit yang cocok akan ditutup tanpa mengubah stok. Tidak dicentang: tidak ada perubahan yang disimpan.');
                setSaveEnabled(settleInput.checked);
                outcomeBox.textContent = settleInput.checked
                    ? 'Stok sistem dan lot tidak berubah. Karena hasil fisik sama, defisit akan ditutup tanpa membuat adjustment baru.'
                    : 'Stok sistem dan lot tidak berubah. Defisit tetap terbuka karena pilihan penyelesaian tidak dicentang.';
                return;
            }
            const delta = physicalQty - systemQty;
            if (delta < -0.0001) {
                setSaveEnabled(true);
                setSettlementEnabled(false, 'Pengurangan stok tidak dapat menutup defisit. Simpan hanya untuk menyamakan stok dan lot dengan hitungan fisik.');
                outcomeBox.textContent = 'Stok sistem akan dikurangi dari ' + withUom(systemQty) + ' menjadi ' + withUom(physicalQty) + ' (' + formatQty(delta) + '). Lot akan mengikuti adjustment ini. Defisit tidak dapat ditutup melalui pengurangan stok.';
                return;
            }
            setSaveEnabled(true);
            setSettlementEnabled(true, 'Dicentang: stok/lot akan disesuaikan lalu defisit yang cocok dicoba diselesaikan. Tidak dicentang: hanya stok dan lot yang disesuaikan.');
            outcomeBox.textContent = 'Stok sistem akan disesuaikan dari ' + withUom(systemQty) + ' menjadi ' + withUom(physicalQty) + ' (' + (delta > 0 ? '+' : '') + formatQty(delta) + '). Lot akan mengikuti adjustment ini' + (settleInput.checked ? ', lalu defisit yang cocok akan dicoba diselesaikan.' : '. Defisit tetap terbuka.');
        };

        document.querySelectorAll('.js-deficit-inline-recon').forEach(function (button) {
            button.addEventListener('click', function () {
                try {
                    activeRecon = {
                        url: button.dataset.reconUrl,
                        payload: JSON.parse(button.dataset.reconPayload || '{}'),
                        deficitId: Number(button.dataset.deficitId || 0),
                        isComponent: button.dataset.isComponent === '1',
                        uomCode: button.dataset.uomCode || '',
                        systemQty: Number(button.dataset.systemQty || 0),
                        lotQty: Number(button.dataset.lotQty || 0),
                        lotCount: Number(button.dataset.lotCount || 0),
                        activeMonth: button.dataset.activeMonth || ''
                    };
                } catch (error) {
                    showError('Data barang untuk recon tidak dapat dibaca. Muat ulang halaman lalu coba lagi.');
                    return;
                }
                document.getElementById('deficitReconName').textContent = button.dataset.inventoryName || '-';
                document.getElementById('deficitReconProfile').textContent = button.dataset.profileLabel || '-';
                document.getElementById('deficitReconRemaining').textContent = withUom(button.dataset.deficitQty || 0);
                document.getElementById('deficitReconSystemQty').textContent = withUom(activeRecon.systemQty) + (activeRecon.activeMonth ? ' (' + activeRecon.activeMonth.slice(0, 7) + ')' : '');
                document.getElementById('deficitReconLotQty').textContent = withUom(activeRecon.lotQty) + ' / ' + activeRecon.lotCount + ' lot';
                document.getElementById('deficitReconUom').textContent = activeRecon.uomCode ? '(' + activeRecon.uomCode + ')' : '';
                physicalInput.value = '';
                document.getElementById('deficitReconNotes').value = '';
                settleInput.checked = true;
                hideError();
                updateOutcome();
                showModal();
                setTimeout(function () { physicalInput.focus(); }, 180);
            });
        });
        physicalInput.addEventListener('input', updateOutcome);
        settleInput.addEventListener('change', updateOutcome);
        useSystemButton.addEventListener('click', function () {
            if (!activeRecon) return;
            physicalInput.value = String(activeRecon.systemQty || 0);
            updateOutcome();
            physicalInput.focus();
        });

        saveButton.addEventListener('click', async function () {
            const physicalQty = Number(physicalInput.value);
            if (!activeRecon || !activeRecon.url) { showError('Pilih kembali baris defisit yang ingin direkon.'); return; }
            if (!Number.isFinite(physicalQty) || physicalQty < 0) { showError('Isi stok fisik dengan angka nol atau lebih.'); return; }
            const systemQty = Number(activeRecon.systemQty || 0);
            const canAdjust = Math.abs(physicalQty - systemQty) > 0.0001;
            const canSettle = physicalQty > 0.0001 && physicalQty + 0.0001 >= systemQty;
            if (!canAdjust && !(settleInput.checked && canSettle)) {
                showError('Tidak ada perubahan yang perlu disimpan. Masukkan hasil hitung fisik yang berbeda, atau centang penyelesaian defisit bila stok fisik positif sudah sesuai.');
                return;
            }
            hideError();
            const original = saveButton.innerHTML;
            saveButton.disabled = true;
            saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Menyimpan';
            const payload = Object.assign({}, activeRecon.payload, {
                opname_date: document.getElementById('deficitReconDate').value,
                input_mode: 'PHYSICAL_COUNT',
                deficit_id: activeRecon.deficitId,
                settle_open_deficit: settleInput.checked && canSettle ? 1 : 0,
                notes: 'Recon stok fisik & defisit #' + activeRecon.deficitId + (document.getElementById('deficitReconNotes').value.trim() ? ' | ' + document.getElementById('deficitReconNotes').value.trim() : '')
            });
            if (activeRecon.isComponent) payload.physical_qty = physicalQty;
            else payload.physical_qty_content = physicalQty;
            try {
                const response = await fetch(activeRecon.url, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(payload)
                });
                const raw = await response.text();
                let data;
                try { data = JSON.parse(raw); } catch (error) { throw new Error(plainMessage(raw) || 'Server tidak mengembalikan hasil recon yang dapat dibaca.'); }
                if (!response.ok || data.ok === false || data.success === false) throw new Error(data.message || 'Recon stok fisik gagal diposting.');
                hideModal();
                window.location.reload();
            } catch (error) {
                showError(error && error.message ? error.message : 'Recon stok fisik gagal diproses.');
            } finally {
                saveButton.innerHTML = original;
                updateOutcome();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDeficitRecon);
    } else {
        initDeficitRecon();
    }
})();
</script>
