<?php
$detail = is_array($detail ?? null) ? $detail : [];
$header = is_array($detail['header'] ?? null) ? $detail['header'] : [];
$events = is_array($detail['events'] ?? null) ? $detail['events'] : [];
$productCauses = is_array($detail['product_causes'] ?? null) ? $detail['product_causes'] : [];
$settlements = is_array($detail['settlements'] ?? null) ? $detail['settlements'] : [];
$liveLots = is_array($detail['live_lots'] ?? null) ? $detail['live_lots'] : [];
$relatedProfileStock = is_array($detail['related_profile_stock'] ?? null) ? $detail['related_profile_stock'] : [];
$canWriteOff = !empty($can_write_off);
$writeOffSchemaReady = !empty($write_off_schema_ready);
$fmtQty = static fn($value): string => number_format((float)$value, 4, ',', '.');
$fmtMoney = static fn($value): string => 'Rp ' . number_format((float)$value, 2, ',', '.');
$isComponent = strtoupper((string)($header['stock_domain'] ?? '')) === 'COMPONENT';
$isDivisionMaterial = !$isComponent && (int)($header['division_id'] ?? 0) > 0;
$isWarehouseMaterial = !$isComponent && strtoupper((string)($header['location_scope'] ?? '')) === 'WAREHOUSE';
$profileKey = trim((string)($header['profile_key'] ?? ''));
$adjustmentUrl = $isComponent
    ? site_url('production/component-adjustments')
    : ($isWarehouseMaterial ? site_url('inventory/stock/adjustment/warehouse') : site_url('inventory/stock/adjustment/division'));
$canInlineRecon = !empty($can_inline_recon)
    && (($isDivisionMaterial && $profileKey !== '') || $isWarehouseMaterial || ($isComponent && (int)($header['division_id'] ?? 0) > 0));
$directReconUrl = '';
$directReconPayload = [];
if ($isDivisionMaterial) {
    $directReconUrl = site_url('inventory/stock/daily-recon/division/quick-adjust');
    $directReconPayload = [
        'opname_date' => date('Y-m-d'),
        'division_id' => (int)($header['division_id'] ?? 0),
        'destination_type' => (string)($header['destination_type'] ?? 'OTHER'),
        'identity_key' => $profileKey,
        'profile_key' => $profileKey,
        'item_id' => (int)($header['item_id'] ?? 0),
        'material_id' => (int)($header['material_id'] ?? 0),
        'buy_uom_id' => (int)($header['buy_uom_id'] ?? 0),
        'content_uom_id' => (int)($header['content_uom_id'] ?? 0),
        'input_mode' => 'PHYSICAL_COUNT',
        'adjustment_type' => 'ADJUSTMENT_PLUS',
        'reason_code' => 'other',
    ];
} elseif ($isWarehouseMaterial) {
    $directReconUrl = site_url('inventory/stock/daily-recon/warehouse/quick-adjust');
    $directReconPayload = [
        'opname_date' => date('Y-m-d'),
        'identity_key' => $profileKey,
        'profile_key' => $profileKey,
        'item_id' => (int)($header['item_id'] ?? 0),
        'material_id' => (int)($header['material_id'] ?? 0),
        'buy_uom_id' => (int)($header['buy_uom_id'] ?? 0),
        'content_uom_id' => (int)($header['content_uom_id'] ?? 0),
        'input_mode' => 'PHYSICAL_COUNT',
        'adjustment_type' => 'ADJUSTMENT_PLUS',
        'reason_code' => 'physical_count',
    ];
} elseif ($isComponent && (int)($header['division_id'] ?? 0) > 0) {
    $locationScope = strtoupper((string)($header['location_scope'] ?? ''));
    $directReconUrl = site_url('production/component-daily-recon/quick-adjust');
    $directReconPayload = [
        'opname_date' => date('Y-m-d'),
        'location_type' => substr($locationScope, -6) === '_EVENT' ? 'EVENT' : 'REGULER',
        'division_id' => (int)($header['division_id'] ?? 0),
        'component_id' => (int)($header['component_id'] ?? 0),
        'uom_id' => (int)($header['content_uom_id'] ?? 0),
        'input_mode' => 'PHYSICAL_COUNT',
        'adjustment_type' => 'ADJUSTMENT_PLUS',
        'reason_code' => 'other',
    ];
}
$status = strtoupper((string)($header['status'] ?? ''));
$statusLabel = $status === 'OPEN' ? 'Masih terbuka' : ($status === 'SETTLED' ? 'Sudah tertutup' : ($status === 'WRITTEN_OFF' ? 'Ditutup administratif' : 'Dibatalkan'));
$statusClass = $status === 'OPEN' ? 'text-bg-danger' : ($status === 'SETTLED' ? 'text-bg-success' : 'text-bg-secondary');
$uomCode = (string)($header['uom_code'] ?? '');
$activeMonth = (string)($header['active_stock_month'] ?? date('Y-m-01'));
$relatedProfileQty = array_reduce($relatedProfileStock, static function (float $total, array $row): float {
    return $total + (float)($row['system_qty'] ?? 0);
}, 0.0);
$relatedProfileReconUrl = $isDivisionMaterial && !empty($relatedProfileStock)
    ? site_url('inventory/stock/daily-recon/division') . '?' . http_build_query([
        'opname_date' => date('Y-m-d'),
        'division_id' => (int)($header['division_id'] ?? 0),
        'destination' => (string)($header['destination_type'] ?? ''),
        'q' => (string)($header['inventory_name'] ?? ''),
    ])
    : '';
$mutationCsrfName = (string)($inventory_control_mutation_csrf_name ?? '');
$mutationCsrfValue = (string)($inventory_control_mutation_csrf_value ?? '');
$hasMutationCsrf = $mutationCsrfName !== '' && $mutationCsrfValue !== '';
$eventStatusLabel = static function (string $value): string {
    return match (strtoupper($value)) {
        'OPEN' => 'Masih terbuka',
        'SETTLED' => 'Sudah tertutup',
        'WRITTEN_OFF' => 'Ditutup administratif',
        'VOID' => 'Dibatalkan',
        default => $value !== '' ? strtoupper($value) : '-',
    };
};
?>

<style>
.deficit-detail{--dd-ink:#3d2926;--dd-muted:#866a62;--dd-line:#ecdcd3;--dd-red:#8f1023}.deficit-detail .hero{border:1px solid var(--dd-line);border-radius:18px;background:linear-gradient(135deg,#fffdfa,#fff3ed);padding:1rem 1.15rem}.deficit-detail .metric{border:1px solid var(--dd-line);border-radius:14px;background:#fff;padding:.72rem .8rem;height:100%}.deficit-detail .metric .label{display:block;font-size:.65rem;color:var(--dd-muted);font-weight:800;text-transform:uppercase;letter-spacing:.05em}.deficit-detail .metric .value{display:block;font-size:1rem;font-weight:850;color:var(--dd-ink);margin-top:.25rem}.deficit-detail .detail-card{border:1px solid var(--dd-line);border-radius:16px;overflow:hidden}.deficit-detail .detail-card .card-header{background:#fffaf7;border-bottom:1px solid var(--dd-line)}.deficit-detail .detail-table-wrap{max-height:48vh;overflow:auto}.deficit-detail .detail-table{min-width:900px;margin:0}.deficit-detail .detail-table thead th{position:sticky;top:0;z-index:2;background:var(--dd-red);color:#fff;white-space:nowrap;font-size:.71rem}.deficit-detail .detail-table td{vertical-align:middle;border-color:#f0dfd7;font-size:.8rem}.deficit-detail .profile-key{font-size:.68rem;color:var(--dd-muted);word-break:break-all}.deficit-detail .snapshot-note{border:1px solid #d9e8df;background:#f2fbf5;color:#276746;border-radius:12px;padding:.72rem .85rem;font-size:.8rem}.deficit-detail .deficit-cause-card{border-color:#efcfbd;background:linear-gradient(135deg,#fffdf9,#fff3ea)}.deficit-detail .deficit-cause-card .card-header{background:transparent}.deficit-detail .deficit-cause-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem}.deficit-detail .deficit-cause-item{border:1px solid #ecd9cc;border-radius:13px;background:#fff;padding:.8rem}.deficit-detail .deficit-cause-type{color:#9b4c20;font-size:.64rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase}.deficit-detail .deficit-cause-name{display:block;color:var(--dd-ink);font-size:.92rem;line-height:1.3;margin:.16rem 0 .42rem}.deficit-detail .deficit-cause-orders{color:var(--dd-muted);font-size:.72rem;min-height:1.1rem;overflow-wrap:anywhere}.deficit-detail .deficit-cause-stats{display:grid;grid-template-columns:1fr 1fr;gap:.45rem;margin-top:.7rem}.deficit-detail .deficit-cause-stat{border-top:1px solid #f1e3dc;padding-top:.43rem}.deficit-detail .deficit-cause-stat span{display:block;color:var(--dd-muted);font-size:.63rem;text-transform:uppercase;font-weight:750;letter-spacing:.04em}.deficit-detail .deficit-cause-stat strong{display:block;color:var(--dd-ink);font-size:.8rem;margin-top:.08rem}.deficit-detail .deficit-cause-stat.danger strong{color:#b42318}.deficit-recon-modal .recon-summary{border:1px solid #ecdcd3;border-radius:14px;background:#fffaf7;padding:.75rem}.deficit-recon-modal .form-text{line-height:1.35}@media(max-width:767px){.deficit-detail .hero{padding:.9rem}.deficit-detail .metric .value{font-size:.9rem}}
</style>

<div class="deficit-detail">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
    <div><h4 class="mb-1"><i class="ri ri-file-search-line page-title-icon"></i><?php echo html_escape((string)($page_title ?? 'Rincian Defisit Stok')); ?></h4><small class="text-muted">Halaman ini memisahkan jejak kekurangan lama dari stok dan lot yang tersedia saat ini.</small></div>
    <a class="btn btn-outline-secondary btn-sm" href="<?php echo site_url('inventory/stock/deficits'); ?>">Kembali ke Defisit Stok</a>
  </div>

  <section class="hero mb-3">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap"><div><div class="text-muted small">Barang dan profil yang perlu ditelusuri</div><h5 class="mb-1"><?php echo html_escape((string)($header['inventory_name'] ?? '-')); ?></h5><div class="small text-muted"><?php echo html_escape((string)($header['profile_label'] ?? '-')); ?></div><div class="profile-key">Kunci profil: <?php echo html_escape($profileKey !== '' ? $profileKey : '-'); ?></div></div><span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></div>
    <div class="row g-2 mt-2">
      <div class="col-6 col-md-4 col-xl-2"><div class="metric"><span class="label">Sisa defisit</span><span class="value text-danger"><?php echo $fmtQty($header['qty_remaining'] ?? 0); ?> <?php echo html_escape($uomCode); ?></span></div></div>
      <div class="col-6 col-md-4 col-xl-2"><div class="metric"><span class="label">Stok sistem kini</span><span class="value"><?php echo $fmtQty($header['system_qty'] ?? 0); ?> <?php echo html_escape($uomCode); ?></span><small class="text-muted">Bulan <?php echo html_escape(date('m/Y', strtotime($activeMonth))); ?></small></div></div>
      <div class="col-6 col-md-4 col-xl-2"><div class="metric"><span class="label">Lot aktif kini</span><span class="value text-success"><?php echo $fmtQty($header['active_lot_qty'] ?? 0); ?> <?php echo html_escape($uomCode); ?></span><small class="text-muted"><?php echo number_format((int)($header['active_lot_count'] ?? 0), 0, ',', '.'); ?> lot tersedia</small></div></div>
      <div class="col-6 col-md-4 col-xl-2"><div class="metric"><span class="label">Kejadian sumber</span><span class="value"><?php echo number_format((int)($header['event_count'] ?? 0), 0, ',', '.'); ?></span></div></div>
      <div class="col-6 col-md-4 col-xl-2"><div class="metric"><span class="label">Perkiraan nilai defisit</span><span class="value" style="font-size:.9rem"><?php echo $fmtMoney($header['estimated_total_value'] ?? 0); ?></span></div></div>
      <div class="col-6 col-md-4 col-xl-2"><div class="metric"><span class="label">Aktivitas terakhir</span><span class="value" style="font-size:.8rem"><?php echo html_escape((string)($header['last_activity_at'] ?? '-')); ?></span></div></div>
    </div>
  </section>

  <?php if (!empty($productCauses)): ?>
    <section class="detail-card card deficit-cause-card mb-3">
      <div class="card-header d-flex align-items-start justify-content-between gap-2 flex-wrap">
        <div><strong>Produk Pemicu Defisit</strong><span class="d-block text-muted small mt-1">Produk POS atau extra yang memakai <?php echo html_escape((string)($header['inventory_name'] ?? 'stok ini')); ?> saat stok tidak cukup.</span></div>
        <span class="badge text-bg-light border"><?php echo number_format(count($productCauses), 0, ',', '.'); ?> produk / extra</span>
      </div>
      <div class="card-body">
        <div class="deficit-cause-grid">
          <?php foreach ($productCauses as $cause): ?>
            <?php
            $orderLabels = array_values((array)($cause['order_labels'] ?? []));
            $visibleOrderLabels = array_slice($orderLabels, 0, 3);
            $hiddenOrderCount = max(0, count($orderLabels) - count($visibleOrderLabels));
            ?>
            <article class="deficit-cause-item">
              <span class="deficit-cause-type"><?php echo html_escape((string)($cause['product_type'] ?? 'Produk POS')); ?></span>
              <strong class="deficit-cause-name"><?php echo html_escape((string)($cause['product_name'] ?? '-')); ?></strong>
              <div class="deficit-cause-orders">
                <?php if (!empty($visibleOrderLabels)): ?>
                  <i class="ri ri-shopping-bag-3-line"></i> <?php echo html_escape(implode(', ', $visibleOrderLabels)); ?><?php echo $hiddenOrderCount > 0 ? ' +' . $hiddenOrderCount . ' lainnya' : ''; ?>
                <?php else: ?>
                  <span>Referensi order tidak tersedia</span>
                <?php endif; ?>
              </div>
              <div class="deficit-cause-stats">
                <div class="deficit-cause-stat"><span>Qty diminta</span><strong><?php echo $fmtQty($cause['requested_qty'] ?? 0); ?> <?php echo html_escape($uomCode); ?></strong></div>
                <div class="deficit-cause-stat danger"><span>Masih defisit</span><strong><?php echo $fmtQty($cause['qty_remaining'] ?? 0); ?> <?php echo html_escape($uomCode); ?></strong></div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!empty($relatedProfileStock)): ?>
    <div class="alert alert-info mb-3 d-flex align-items-start justify-content-between gap-3 flex-wrap">
      <div><strong>Stok barang yang sama ditemukan di profil lain.</strong> Ada total <?php echo $fmtQty($relatedProfileQty); ?> <?php echo html_escape($uomCode); ?> pada profil berbeda. Angka ini tidak otomatis menutup defisit di atas, karena profil menyimpan jejak kemasan, harga, dan lot. Gunakan informasi di bawah untuk memeriksa apakah stok tersebut memang barang fisik yang sama, bukan untuk menutup defisit secara asal.</div>
      <?php if ($relatedProfileReconUrl !== ''): ?><a class="btn btn-outline-primary btn-sm text-nowrap" href="<?php echo html_escape($relatedProfileReconUrl); ?>">Lihat stok profil lain</a><?php endif; ?>
    </div>

    <div class="detail-card card mb-3">
      <div class="card-header">
        <strong>Stok Barang Sama di Profil Lain</strong>
        <span class="text-muted small ms-2">Informasi pembanding saja; belum berarti defisit boleh ditutup.</span>
      </div>
      <div class="detail-table-wrap">
        <table class="table table-sm detail-table align-middle">
          <thead>
            <tr>
              <th>Profil pembelian</th>
              <th class="text-end">Stok sistem aktif</th>
              <th class="text-end">Biaya / isi</th>
              <th class="text-end">Nilai stok</th>
            </tr>
          </thead>
          <tbody>
      <?php foreach ($relatedProfileStock as $related): ?>
        <?php $relatedKey = trim((string)($related['profile_key'] ?? '')); ?>
        <tr>
          <td><strong><?php echo html_escape((string)($related['profile_label'] ?? '-')); ?></strong><small class="d-block text-muted">Profil #<?php echo html_escape($relatedKey !== '' ? substr($relatedKey, 0, 12) : '-'); ?></small></td>
          <td class="text-end fw-semibold text-primary"><?php echo $fmtQty($related['system_qty'] ?? 0); ?> <?php echo html_escape($uomCode); ?></td>
          <td class="text-end"><?php echo $fmtMoney($related['system_avg_cost'] ?? 0); ?></td>
          <td class="text-end"><?php echo $fmtMoney($related['system_total_value'] ?? 0); ?></td>
        </tr>
      <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($status === 'OPEN'): ?>
    <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2"><span><strong>Recon di halaman ini adalah recon stok fisik terlebih dahulu.</strong> Masukkan jumlah fisik akhir. Jika sama dengan stok sistem, stok dan lot tidak berubah; Anda boleh menutup defisit bila kekurangan lama memang sudah teratasi. Jika berbeda, sistem membuat adjustment untuk menyamakan stok dan lot dengan hitungan fisik.</span><div class="d-flex gap-2 flex-wrap"><?php if ($canInlineRecon): ?><button type="button" class="btn btn-warning btn-sm" id="btnDeficitInlineRecon">Recon Stok Fisik &amp; Defisit</button><?php endif; ?><a class="btn btn-outline-secondary btn-sm" href="<?php echo $adjustmentUrl; ?>">Buka Adjustment</a><?php if ($canWriteOff && $writeOffSchemaReady): ?><button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deficitWriteOffModal">Tutup Administratif</button><?php endif; ?></div></div>
  <?php endif; ?>

  <div class="snapshot-note mb-3"><strong>Arti angka di atas:</strong> defisit dapat tetap terbuka walaupun stok sistem sudah positif, misalnya barang datang setelah POS sempat memotong ketika stok nol. Karena itu, jangan isi angka defisit sebagai stok fisik. Gunakan hasil hitung fisik di lokasi ini.</div>

  <?php if ($status === 'OPEN' && $canWriteOff && !$writeOffSchemaReady): ?>
    <div class="alert alert-secondary mb-3"><strong>Penutupan administratif belum siap.</strong> Jalankan SQL <code>2026-08-19a_inventory_deficit_administrative_writeoff.sql</code> terlebih dahulu. Fitur ini tidak mengubah stok, lot, mutasi, atau kas.</div>
  <?php endif; ?>

  <div class="row g-3 mb-3">
    <div class="col-lg-6"><div class="detail-card card h-100"><div class="card-header"><strong>Lokasi dan identitas</strong></div><div class="card-body small"><dl class="row mb-0"><dt class="col-5 text-muted">Divisi / area</dt><dd class="col-7"><?php echo html_escape((string)($header['division_name'] ?? $header['location_scope'] ?? '-')); ?><?php echo !empty($header['destination_type']) ? ' / ' . html_escape((string)$header['destination_type']) : ''; ?></dd><dt class="col-5 text-muted">Jenis stok</dt><dd class="col-7"><?php echo html_escape($isComponent ? 'Component' : 'Bahan baku'); ?></dd><dt class="col-5 text-muted">Rentang kejadian</dt><dd class="col-7"><?php echo html_escape((string)($header['first_deficit_date'] ?? '-')); ?> s/d <?php echo html_escape((string)($header['last_deficit_date'] ?? '-')); ?></dd><dt class="col-5 text-muted">Identitas penyelesaian</dt><dd class="col-7"><?php echo $isComponent ? 'Lokasi, divisi, component, dan UOM yang sama.' : 'Barang, area, UOM, dan profil pembelian yang sama.'; ?></dd></dl></div></div></div>
    <div class="col-lg-6"><div class="detail-card card h-100"><div class="card-header"><strong>Biaya dan sumber harga</strong></div><div class="card-body small"><dl class="row mb-0"><dt class="col-6 text-muted">Estimasi biaya defisit / isi</dt><dd class="col-6 text-end"><?php echo $fmtMoney($header['estimated_unit_cost'] ?? 0); ?></dd><dt class="col-6 text-muted">Biaya stok kini / isi</dt><dd class="col-6 text-end"><?php echo !empty($header['system_avg_cost']) ? $fmtMoney($header['system_avg_cost']) : '-'; ?></dd><dt class="col-6 text-muted">Referensi katalog</dt><dd class="col-6 text-end"><?php echo !empty($header['catalog_price_source']) ? html_escape((string)$header['catalog_price_source']) : '-'; ?></dd><dt class="col-6 text-muted">Harga katalog / isi</dt><dd class="col-6 text-end"><?php echo !empty($header['catalog_avg_cost_per_content']) ? $fmtMoney($header['catalog_avg_cost_per_content']) : '-'; ?></dd></dl></div></div></div>
  </div>

  <div class="detail-card card mb-3"><div class="card-header"><strong>Lot Aktif Sekarang</strong><span class="text-muted small ms-2">Lot yang benar-benar masih tersedia untuk identitas ini, bukan lot khusus milik defisit.</span></div><div class="detail-table-wrap"><table class="table table-sm detail-table align-middle"><thead><tr><th>Lot</th><th>Tgl masuk</th><th>Kedaluwarsa</th><th class="text-end">Saldo lot</th><th class="text-end">Biaya / isi</th><th class="text-end">Nilai</th><th>Sumber</th></tr></thead><tbody><?php if (empty($liveLots)): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada lot aktif untuk identitas ini pada saat halaman dibuka.</td></tr><?php else: foreach ($liveLots as $lot): ?><tr><td><strong><?php echo html_escape((string)($lot['lot_no'] ?? '-')); ?></strong><small class="d-block text-muted">#<?php echo (int)($lot['id'] ?? 0); ?></small></td><td><?php echo html_escape((string)($lot['receipt_date'] ?? '-')); ?></td><td><?php echo html_escape((string)($lot['expiry_date'] ?? '-')); ?></td><td class="text-end fw-semibold text-success"><?php echo $fmtQty($lot['qty_balance'] ?? 0); ?> <?php echo html_escape($uomCode); ?></td><td class="text-end"><?php echo $fmtMoney($lot['unit_cost'] ?? 0); ?></td><td class="text-end"><?php echo $fmtMoney($lot['total_value'] ?? 0); ?></td><td><?php echo html_escape((string)($lot['source_table'] ?? '-')); ?><?php echo !empty($lot['source_id']) ? ' #' . (int)$lot['source_id'] : ''; ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div>

  <div class="detail-card card mb-3"><div class="card-header"><strong>Riwayat Kejadian Defisit</strong><span class="text-muted small ms-2">Semua transaksi yang membentuk kekurangan untuk identitas yang sama.</span></div><div class="detail-table-wrap"><table class="table table-sm detail-table align-middle"><thead><tr><th>Tanggal / waktu</th><th>Produk / order</th><th class="text-end">Diminta</th><th class="text-end">Tertutup lot saat itu</th><th class="text-end">Sisa kejadian</th><th>Status</th><th>Sumber</th><th>Catatan</th></tr></thead><tbody><?php if (empty($events)): ?><tr><td colspan="8" class="text-center text-muted py-4">Belum ada jejak kejadian.</td></tr><?php else: foreach ($events as $event): ?><tr><td><div><?php echo html_escape((string)($event['deficit_date'] ?? '-')); ?></div><small class="text-muted"><?php echo html_escape((string)($event['created_at'] ?? '-')); ?></small></td><td><?php if (!empty($event['cause_product_name'])): ?><strong><?php echo html_escape((string)$event['cause_product_name']); ?></strong><small class="d-block text-muted"><?php echo html_escape((string)($event['cause_product_type'] ?? 'Produk POS')); ?><?php echo !empty($event['cause_reference_label']) ? ' · ' . html_escape((string)$event['cause_reference_label']) : ''; ?></small><?php else: ?><span class="text-muted"><?php echo html_escape((string)($event['cause_reference_label'] ?? '-')); ?></span><?php endif; ?></td><td class="text-end"><?php echo $fmtQty($event['requested_qty'] ?? 0); ?></td><td class="text-end"><?php echo $fmtQty($event['issued_qty'] ?? 0); ?></td><td class="text-end fw-semibold <?php echo strtoupper((string)($event['status'] ?? '')) === 'OPEN' ? 'text-danger' : ''; ?>"><?php echo $fmtQty($event['qty_remaining'] ?? 0); ?></td><td><?php echo html_escape($eventStatusLabel((string)($event['status'] ?? ''))); ?></td><td><div><?php echo html_escape((string)($event['source_module'] ?? '-')); ?></div><small class="text-muted"><?php echo html_escape((string)($event['source_table'] ?? '-')); ?><?php echo !empty($event['source_id']) ? ' #' . (int)$event['source_id'] : ''; ?></small></td><td><?php echo html_escape((string)($event['notes'] ?? '-')); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div>

  <div class="detail-card card"><div class="card-header"><strong>Riwayat Penyelesaian Defisit</strong><span class="text-muted small ms-2">Penerimaan atau adjustment tambah yang mengakui kekurangan ini akan muncul di sini.</span></div><div class="detail-table-wrap"><table class="table table-sm detail-table align-middle"><thead><tr><th>Tanggal</th><th class="text-end">Qty ditutup</th><th class="text-end">Biaya satuan</th><th class="text-end">Nilai</th><th>Sumber</th><th>Catatan</th></tr></thead><tbody><?php if (empty($settlements)): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada penerimaan atau adjustment yang menyelesaikan defisit ini.</td></tr><?php else: foreach ($settlements as $settlement): ?><tr><td><?php echo html_escape((string)($settlement['settlement_date'] ?? '-')); ?></td><td class="text-end"><?php echo $fmtQty($settlement['qty_settled'] ?? 0); ?></td><td class="text-end"><?php echo $fmtMoney($settlement['unit_cost'] ?? 0); ?></td><td class="text-end"><?php echo $fmtMoney($settlement['total_value'] ?? 0); ?></td><td><div><?php echo html_escape((string)($settlement['source_module'] ?? '-')); ?></div><small class="text-muted"><?php echo html_escape((string)($settlement['source_table'] ?? '-')); ?><?php echo !empty($settlement['source_id']) ? ' #' . (int)$settlement['source_id'] : ''; ?></small></td><td><?php echo html_escape((string)($settlement['notes'] ?? '-')); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
</div>

<?php if ($status === 'OPEN' && $canWriteOff && $writeOffSchemaReady && $hasMutationCsrf): ?>
<div class="modal fade" id="deficitWriteOffModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"><form method="post" action="<?php echo site_url('inventory/stock/deficits/write-off/' . (int)($header['id'] ?? 0)); ?>" class="modal-content">
    <div class="modal-header"><div><h5 class="modal-title mb-1">Tutup Defisit Secara Administratif</h5><small class="text-muted">Untuk barang yang dihentikan atau jejak historis yang tidak akan diselesaikan melalui stok masuk lagi.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body"><input type="hidden" name="<?php echo html_escape($mutationCsrfName); ?>" value="<?php echo html_escape($mutationCsrfValue); ?>"><div class="alert alert-danger small"><strong>Yang tidak akan berubah:</strong> stok sistem, lot FIFO, movement, dan kas. Aksi ini hanya menutup kewajiban defisit dan menyimpan alasan audit. Jangan gunakan bila stok fisik sebenarnya masih perlu diperbaiki.</div><div class="row g-3"><div class="col-md-6"><label class="form-label">Alasan penutupan</label><select class="form-select" name="written_off_reason_code" required><option value="">Pilih alasan</option><option value="DISCONTINUED">Barang sudah tidak digunakan lagi</option><option value="HISTORICAL_CUTOFF">Jejak historis telah dibereskan saat cut-off</option><option value="UNRECOVERABLE">Tidak dapat dipulihkan dari sumber transaksi</option><option value="OTHER">Lainnya</option></select></div><div class="col-md-6"><label class="form-label">Ketik konfirmasi</label><input class="form-control" name="confirmation" required placeholder="TUTUP"></div><div class="col-12"><label class="form-label">Catatan wajib</label><textarea class="form-control" name="written_off_notes" rows="3" required placeholder="Jelaskan mengapa defisit ini tidak lagi akan diselesaikan melalui stok masuk atau adjustment."></textarea></div></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-danger">Tutup Administratif</button></div>
  </form></div>
</div>
<?php endif; ?>

<?php if ($status === 'OPEN' && $canInlineRecon): ?>
<div class="modal fade deficit-recon-modal" id="deficitInlineReconModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><div><h5 class="modal-title mb-1">Recon Stok Fisik &amp; Defisit</h5><small class="text-muted">Catat angka fisik terlebih dahulu; penyelesaian defisit adalah pilihan kedua yang dapat Anda aktifkan.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div><div class="modal-body">
    <div class="alert alert-warning small mb-3"><strong>Contoh:</strong> jika stok sistem 10 dan stok fisik juga 10, isi 10. Stok serta lot tetap 10; dengan pilihan penyelesaian aktif, defisit yang cocok akan ditutup tanpa membuat adjustment baru.</div>
    <div class="recon-summary mb-3"><strong><?php echo html_escape((string)($header['inventory_name'] ?? '-')); ?></strong><div class="small text-muted"><?php echo html_escape((string)($header['profile_label'] ?? '-')); ?></div><div class="row g-2 mt-1"><div class="col-md-4"><div class="small text-muted">Defisit terbuka</div><strong class="text-danger"><?php echo $fmtQty($header['qty_remaining'] ?? 0); ?> <?php echo html_escape($uomCode); ?></strong></div><div class="col-md-4"><div class="small text-muted">Stok sistem bulan aktif</div><strong><?php echo $fmtQty($header['system_qty'] ?? 0); ?> <?php echo html_escape($uomCode); ?></strong></div><div class="col-md-4"><div class="small text-muted">Lot aktif sekarang</div><strong class="text-success"><?php echo $fmtQty($header['active_lot_qty'] ?? 0); ?> <?php echo html_escape($uomCode); ?> / <?php echo number_format((int)($header['active_lot_count'] ?? 0), 0, ',', '.'); ?> lot</strong></div></div></div>
    <div class="row g-3"><div class="col-md-5"><label class="form-label">Tanggal hitung fisik</label><input type="date" class="form-control" id="deficitReconDate" value="<?php echo date('Y-m-d'); ?>"></div><div class="col-md-7"><label class="form-label">Stok fisik sekarang (<?php echo html_escape($uomCode); ?>)</label><div class="input-group"><input type="number" class="form-control" id="deficitReconPhysicalQty" min="0" step="0.0001" placeholder="Masukkan hasil hitung fisik"><button type="button" class="btn btn-outline-secondary" id="btnDeficitReconUseSystem">Gunakan stok sistem</button></div><div class="form-text">Ini adalah stok akhir hasil hitung, bukan nilai defisit atau jumlah selisih.</div></div></div>
    <div class="alert alert-info small mt-3 mb-3" id="deficitReconOutcome">Masukkan hasil hitung fisik untuk melihat dampaknya sebelum disimpan.</div>
    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="deficitReconSettle" checked><label class="form-check-label" for="deficitReconSettle">Selesaikan defisit terbuka setelah stok fisik dicatat</label><div class="form-text" id="deficitReconSettleHint">Hanya berlaku untuk barang, area, UOM, dan profil yang sama. Lepaskan pilihan ini bila defisit lama belum terbukti selesai.</div></div>
    <div class="mb-0"><label class="form-label">Catatan</label><textarea class="form-control" id="deficitReconNotes" rows="2" placeholder="Contoh: stok fisik ditemukan saat cek rak kitchen"></textarea></div><div class="alert alert-danger d-none mt-3 mb-0" id="deficitReconError"></div>
  </div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-danger" id="btnDeficitReconSave" disabled>Simpan Recon</button></div></div></div>
</div>
<script>
(function () {
    function initDeficitDetailRecon() {
        const openButton = document.getElementById('btnDeficitInlineRecon');
        const modalNode = document.getElementById('deficitInlineReconModal');
        const saveButton = document.getElementById('btnDeficitReconSave');
        if (!openButton || !modalNode || !saveButton) return;

        const basePayload = <?php echo json_encode($directReconPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const postUrl = <?php echo json_encode($directReconUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const isComponent = <?php echo $isComponent ? 'true' : 'false'; ?>;
        const deficitId = <?php echo (int)($header['id'] ?? 0); ?>;
        const systemQty = <?php echo json_encode((float)($header['system_qty'] ?? 0)); ?>;
        const lotQty = <?php echo json_encode((float)($header['active_lot_qty'] ?? 0)); ?>;
        const uomCode = <?php echo json_encode($uomCode, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const errorBox = document.getElementById('deficitReconError');
        const physicalInput = document.getElementById('deficitReconPhysicalQty');
        const settleInput = document.getElementById('deficitReconSettle');
        const settleHint = document.getElementById('deficitReconSettleHint');
        const outcomeBox = document.getElementById('deficitReconOutcome');
        let bootstrapModal = null;
        let fallbackBackdrop = null;
        let fallbackOpen = false;
        const formatQty = function (value) { return Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 4 }); };
        const withUom = function (value) { return formatQty(value) + (uomCode ? ' ' + uomCode : ''); };
        function setSaveEnabled(enabled) { saveButton.disabled = !enabled; }
        function setSettlementEnabled(enabled, hint) {
            const wasDisabled = settleInput.disabled;
            settleInput.disabled = !enabled;
            const group = settleInput.closest('.form-check');
            if (group) group.classList.toggle('opacity-50', !enabled);
            if (!enabled) settleInput.checked = false;
            if (enabled && wasDisabled) settleInput.checked = true;
            settleHint.textContent = hint;
        }
        function showError(message) { errorBox.textContent = message; errorBox.classList.remove('d-none'); }
        function hideError() { errorBox.textContent = ''; errorBox.classList.add('d-none'); }
        function plainMessage(text) { const holder = document.createElement('div'); holder.innerHTML = text || ''; return (holder.textContent || holder.innerText || '').replace(/\s+/g, ' ').trim(); }
        function showModal() {
            if (window.bootstrap && window.bootstrap.Modal) { bootstrapModal = window.bootstrap.Modal.getOrCreateInstance(modalNode); bootstrapModal.show(); return; }
            fallbackOpen = true; modalNode.style.display = 'block'; modalNode.classList.add('show'); modalNode.removeAttribute('aria-hidden'); modalNode.setAttribute('aria-modal', 'true'); document.body.classList.add('modal-open'); fallbackBackdrop = document.createElement('div'); fallbackBackdrop.className = 'modal-backdrop fade show'; fallbackBackdrop.addEventListener('click', hideModal); document.body.appendChild(fallbackBackdrop);
        }
        function hideModal() {
            if (!fallbackOpen && bootstrapModal) { bootstrapModal.hide(); return; }
            fallbackOpen = false; modalNode.classList.remove('show'); modalNode.style.display = 'none'; modalNode.setAttribute('aria-hidden', 'true'); document.body.classList.remove('modal-open'); if (fallbackBackdrop) { fallbackBackdrop.remove(); fallbackBackdrop = null; }
        }
        modalNode.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (button) { button.addEventListener('click', function () { if (fallbackOpen) hideModal(); }); });
        function updateOutcome() {
            const physicalQty = Number(physicalInput.value);
            if (!Number.isFinite(physicalQty) || physicalInput.value === '') { setSaveEnabled(false); setSettlementEnabled(false, 'Masukkan hasil hitung fisik terlebih dahulu. Pilihan ini baru tersedia bila ada stok fisik yang dapat membuktikan defisit telah selesai.'); outcomeBox.textContent = 'Masukkan hasil hitung fisik. Stok sistem saat ini ' + withUom(systemQty) + '; lot aktif ' + withUom(lotQty) + '.'; return; }
            if (Math.abs(physicalQty - systemQty) <= 0.0001) {
                if (physicalQty <= 0.0001) { setSaveEnabled(false); setSettlementEnabled(false, 'Tidak dapat diselesaikan dari stok nol. Cari barang fisik yang belum tercatat, tunggu penerimaan/batch yang sesuai, atau batalkan sumber pemakaian yang memang salah.'); outcomeBox.textContent = 'Stok sistem dan lot tetap nol. Defisit tidak dapat ditutup dari angka nol; masukkan hasil fisik yang benar bila barang memang ada, atau biarkan defisit terbuka untuk ditelusuri.'; return; }
                setSettlementEnabled(true, 'Dicentang: hanya jejak defisit yang cocok akan ditutup tanpa mengubah stok. Tidak dicentang: tidak ada perubahan yang disimpan.'); setSaveEnabled(settleInput.checked);
                outcomeBox.textContent = settleInput.checked ? 'Stok sistem dan lot tidak berubah. Defisit akan ditutup tanpa membuat adjustment baru.' : 'Stok sistem dan lot tidak berubah. Defisit tetap terbuka karena pilihan penyelesaian tidak dicentang.'; return;
            }
            const delta = physicalQty - systemQty;
            if (delta < -0.0001) { setSaveEnabled(true); setSettlementEnabled(false, 'Pengurangan stok tidak dapat menutup defisit. Simpan hanya untuk menyamakan stok dan lot dengan hitungan fisik.'); outcomeBox.textContent = 'Stok sistem akan dikurangi dari ' + withUom(systemQty) + ' menjadi ' + withUom(physicalQty) + ' (' + formatQty(delta) + '). Lot akan mengikuti adjustment ini. Defisit tidak dapat ditutup melalui pengurangan stok.'; return; }
            setSaveEnabled(true); setSettlementEnabled(true, 'Dicentang: stok/lot akan disesuaikan lalu defisit yang cocok dicoba diselesaikan. Tidak dicentang: hanya stok dan lot yang disesuaikan.');
            outcomeBox.textContent = 'Stok sistem akan disesuaikan dari ' + withUom(systemQty) + ' menjadi ' + withUom(physicalQty) + ' (' + (delta > 0 ? '+' : '') + formatQty(delta) + '). Lot akan mengikuti adjustment ini' + (settleInput.checked ? ', lalu defisit yang cocok akan dicoba diselesaikan.' : '. Defisit tetap terbuka.');
        }
        openButton.addEventListener('click', function () { hideError(); physicalInput.value = ''; document.getElementById('deficitReconNotes').value = ''; settleInput.checked = true; updateOutcome(); showModal(); setTimeout(function () { physicalInput.focus(); }, 180); });
        physicalInput.addEventListener('input', updateOutcome); settleInput.addEventListener('change', updateOutcome);
        document.getElementById('btnDeficitReconUseSystem').addEventListener('click', function () { physicalInput.value = String(systemQty); updateOutcome(); physicalInput.focus(); });
        saveButton.addEventListener('click', async function () {
            const physicalQty = Number(physicalInput.value);
            if (!Number.isFinite(physicalQty) || physicalQty < 0) { showError('Isi stok fisik dengan angka nol atau lebih.'); return; }
            const canAdjust = Math.abs(physicalQty - systemQty) > 0.0001;
            const canSettle = physicalQty > 0.0001 && physicalQty + 0.0001 >= systemQty;
            if (!canAdjust && !(settleInput.checked && canSettle)) { showError('Tidak ada perubahan yang perlu disimpan. Masukkan hasil hitung fisik yang berbeda, atau centang penyelesaian defisit bila stok fisik positif sudah sesuai.'); return; }
            hideError(); const original = saveButton.innerHTML; saveButton.disabled = true; saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Menyimpan';
            const payload = Object.assign({}, basePayload, { opname_date: document.getElementById('deficitReconDate').value, input_mode: 'PHYSICAL_COUNT', deficit_id: deficitId, settle_open_deficit: settleInput.checked && canSettle ? 1 : 0, notes: 'Recon stok fisik & defisit #' + deficitId + (document.getElementById('deficitReconNotes').value.trim() ? ' | ' + document.getElementById('deficitReconNotes').value.trim() : '') });
            if (isComponent) payload.physical_qty = physicalQty; else payload.physical_qty_content = physicalQty;
            try { const response = await fetch(postUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(payload) }); const raw = await response.text(); let data; try { data = JSON.parse(raw); } catch (error) { throw new Error(plainMessage(raw) || 'Server tidak mengembalikan hasil recon yang dapat dibaca.'); } if (!response.ok || data.ok === false || data.success === false) throw new Error(data.message || 'Recon stok fisik gagal diposting.'); hideModal(); window.location.reload(); } catch (error) { showError(error && error.message ? error.message : 'Recon stok fisik gagal diproses.'); } finally { saveButton.innerHTML = original; updateOutcome(); }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initDeficitDetailRecon); else initDeficitDetailRecon();
})();
</script>
<?php endif; ?>
