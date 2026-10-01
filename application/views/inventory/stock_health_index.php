<?php
$filters = is_array($filters ?? null) ? $filters : [];
$summary = is_array($summary ?? null) ? $summary : [];
$rows = is_array($rows ?? null) ? $rows : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$pg = is_array($pg ?? null) ? $pg : [];
$baseUrl = site_url('inventory/stock/health');
$fmtQty = static fn($value): string => number_format((float)$value, 4, ',', '.');
$fmtMoney = static fn($value): string => 'Rp ' . number_format((float)$value, 2, ',', '.');
$month = (string)($filters['month'] ?? date('Y-m-01'));
$asOfDate = (string)($summary['as_of_date'] ?? date('Y-m-d'));
$isActiveCalendarMonth = $month === date('Y-m-01');
$query = [
    'month' => substr($month, 0, 7),
    'stock_domain' => (string)($filters['stock_domain'] ?? ''),
    'division_id' => (int)($filters['division_id'] ?? 0) ?: '',
    'q' => (string)($filters['q'] ?? ''),
    'per_page' => (int)($pg['per_page'] ?? 50),
];
$activeMonthQuery = $query;
$activeMonthQuery['month'] = date('Y-m');
$activeMonthUrl = $baseUrl . '?' . http_build_query(array_filter($activeMonthQuery, static fn($value) => $value !== ''));
$pageLink = static function (int $page) use ($baseUrl, $query): string {
    return $baseUrl . '?' . http_build_query(array_filter($query + ['page' => $page], static fn($value) => $value !== ''));
};
$healthCard = static function (array $item, string $label) use ($fmtQty, $fmtMoney): string {
    $mismatch = (int)($item['mismatch_rows'] ?? 0);
    return '<div class="health-kpi ' . ($mismatch > 0 ? 'is-alert' : 'is-good') . '">'
        . '<span>' . html_escape($label) . '</span>'
        . '<strong>' . number_format($mismatch, 0, ',', '.') . ' perhatian</strong>'
        . '<small>' . number_format((int)($item['checked_rows'] ?? 0), 0, ',', '.') . ' stok diperiksa | '
        . $fmtQty($item['absolute_gap'] ?? 0) . ' selisih qty | '
        . $fmtMoney($item['absolute_value_gap'] ?? 0) . ' selisih nilai</small>'
        . '</div>';
};
$issueLabel = static function (string $issue): string {
    return match ($issue) {
        'LOT_TANPA_STOK_BULANAN' => 'Lot belum punya stok bulanan',
        'QTY_DAN_NILAI' => 'Qty dan nilai berbeda',
        'SELISIH_QTY' => 'Jumlah berbeda',
        'SELISIH_NILAI' => 'Nilai berbeda',
        default => $issue,
    };
};
$issueClass = static function (string $issue): string {
    return $issue === 'SELISIH_NILAI' ? 'value' : ($issue === 'LOT_TANPA_STOK_BULANAN' ? 'orphan' : 'qty');
};
$reconcileUrl = static function (array $row) use ($asOfDate): string {
    $domain = strtoupper((string)($row['stock_domain'] ?? ''));
    $divisionId = (int)($row['division_id'] ?? 0);
    $locationType = (string)($row['location_type'] ?? '');
    $name = (string)($row['inventory_name'] ?? '');
    if ($domain === 'COMPONENT') {
        return site_url('production/component-reconcile') . '?' . http_build_query([
            'as_of_date' => $asOfDate,
            'division_id' => $divisionId ?: '',
            'location_type' => $locationType,
            'q' => $name,
            'per_page' => 25,
        ]);
    }
    if (strtoupper((string)($row['location_scope'] ?? '')) === 'WAREHOUSE') {
        if (strtoupper((string)($row['source_state'] ?? '')) === 'LOT_ONLY') {
            return site_url('inventory/stock/warehouse/lot') . '?' . http_build_query(['q' => $name]);
        }
        return site_url('inventory/stock/daily-recon/warehouse') . '?' . http_build_query([
            'opname_date' => $asOfDate,
            'item_id' => (int)($row['item_id'] ?? 0) ?: '',
            'material_id' => (int)($row['material_id'] ?? 0) ?: '',
            'buy_uom_id' => (int)($row['buy_uom_id'] ?? 0) ?: '',
            'content_uom_id' => (int)($row['uom_id'] ?? 0) ?: '',
            'profile_key' => (string)($row['profile_key'] ?? ''),
        ]);
    }
    return site_url('inventory/stock/division/reconcile') . '?' . http_build_query([
        'as_of_date' => $asOfDate,
        'division_id' => $divisionId ?: '',
        // The reconcile controller reads `destination`, not `destination_type`.
        // Keeping this exact area avoids opening a wider, unrelated result set.
        'destination' => $locationType,
        'q' => $name,
        'per_page' => 25,
    ]);
};
$valueReconcileUrl = static function (array $row) use ($month): string {
    return site_url('inventory/stock/value-reconciliation') . '?' . http_build_query([
        'month' => $month,
        'stock_domain' => strtoupper((string)($row['stock_domain'] ?? '')),
        'location_scope' => strtoupper((string)($row['location_scope'] ?? '')),
        'location_type' => strtoupper((string)($row['location_type'] ?? '')),
        'division_id' => (int)($row['division_id'] ?? 0) ?: '',
        'item_id' => (int)($row['item_id'] ?? 0) ?: '',
        'material_id' => (int)($row['material_id'] ?? 0) ?: '',
        'component_id' => (int)($row['component_id'] ?? 0) ?: '',
        'buy_uom_id' => (int)($row['buy_uom_id'] ?? 0) ?: '',
        'uom_id' => (int)($row['uom_id'] ?? 0) ?: '',
        'profile_key' => (string)($row['profile_key'] ?? ''),
        'monthly_stock_id' => (int)($row['monthly_stock_id'] ?? 0),
    ]);
};
?>

<style>
.stock-health{--sh-ink:#3f2925;--sh-muted:#916f65;--sh-line:#efdcd3;--sh-paper:#fffdfb;--sh-red:#ad1024;--sh-red-dark:#820817;--sh-green:#147447}.stock-health .health-intro{border:1px solid var(--sh-line);border-radius:18px;background:linear-gradient(135deg,#fffdfa,#fff1eb);padding:1rem 1.15rem}.stock-health .health-intro p{margin:.3rem 0 0;color:var(--sh-muted);font-size:.82rem;max-width:900px}.stock-health .health-kpis{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem;margin:1rem 0}.stock-health .health-kpi{border:1px solid var(--sh-line);border-radius:15px;background:var(--sh-paper);padding:.78rem .9rem}.stock-health .health-kpi span{display:block;font-size:.67rem;text-transform:uppercase;letter-spacing:.05em;font-weight:850;color:var(--sh-muted)}.stock-health .health-kpi strong{display:block;font-size:1.08rem;color:var(--sh-ink);margin:.2rem 0}.stock-health .health-kpi small{display:block;color:var(--sh-muted);font-size:.7rem;line-height:1.35}.stock-health .health-kpi.is-alert{background:#fff6f4;border-color:#f0c3bb}.stock-health .health-kpi.is-alert strong{color:#b42318}.stock-health .health-kpi.is-good{background:#f2fbf5;border-color:#cfe9d7}.stock-health .health-kpi.is-good strong{color:var(--sh-green)}.stock-health .health-note{border:1px solid #f1d8b5;border-radius:13px;background:#fff9ed;padding:.7rem .85rem;color:#80541f;font-size:.78rem}.stock-health .health-filter{border:1px solid var(--sh-line);border-radius:16px;background:#fff;padding:.8rem .9rem}.stock-health .health-filter-grid{display:grid;grid-template-columns:minmax(180px,1.4fr) 145px 140px 180px 120px auto;gap:.55rem;align-items:end}.stock-health .health-filter label{display:block;font-size:.68rem;font-weight:850;color:#76564d;margin:0 0 .25rem}.stock-health .health-table-card{border:1px solid var(--sh-line);border-radius:18px;overflow:hidden;background:#fff;box-shadow:0 10px 26px rgba(75,42,32,.06)}.stock-health .health-table-wrap{overflow:auto;max-height:66vh}.stock-health .health-table{min-width:1320px;margin:0;border-collapse:separate;border-spacing:0}.stock-health .health-table thead th{position:sticky;top:0;z-index:3;background:linear-gradient(180deg,var(--sh-red),var(--sh-red-dark));color:#fff7f3;font-size:.69rem;letter-spacing:.04em;white-space:nowrap;border-color:#8e0d20}.stock-health .health-table td{vertical-align:middle;border-color:#f0dfd7;font-size:.79rem;color:var(--sh-ink)}.stock-health .health-table tbody tr:nth-child(even) td{background:#fffaf7}.stock-health .health-name{font-weight:850;color:#402620}.stock-health .health-sub{font-size:.67rem;color:var(--sh-muted);margin-top:.12rem;line-height:1.35}.stock-health .health-chip{display:inline-flex;border-radius:999px;padding:.22rem .54rem;font-size:.64rem;font-weight:850;white-space:nowrap}.stock-health .health-chip.qty{background:#fde9e8;color:#b42318}.stock-health .health-chip.value{background:#fff3d9;color:#9b5b00}.stock-health .health-chip.orphan{background:#eee8ff;color:#593b9a}.stock-health .health-pagination{display:flex;align-items:center;gap:.45rem;flex-wrap:wrap;padding:.8rem .9rem;border-top:1px solid var(--sh-line)}
.stock-health .health-table-card{position:relative}.stock-health .health-table-wrap{height:min(66vh,720px);max-height:none;position:relative;isolation:isolate}.stock-health .health-table thead,.stock-health .health-table thead tr,.stock-health .health-table thead th{position:static !important}.stock-health .health-sticky-head{display:none;position:absolute;z-index:20;overflow:hidden;pointer-events:none;background:var(--sh-red-dark);box-shadow:0 4px 10px rgba(76,8,20,.22)}.stock-health .health-sticky-head .health-table{margin:0;border-collapse:separate;border-spacing:0;transform-origin:top left}.stock-health .health-sticky-head .health-table thead th{background:linear-gradient(180deg,var(--sh-red),var(--sh-red-dark));color:#fff7f3;box-shadow:0 2px 0 rgba(81,8,19,.22)}
@media(max-width:992px){.stock-health .health-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:640px){.stock-health .health-kpis,.stock-health .health-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.stock-health .health-filter-grid .wide{grid-column:span 2}}
</style>

<div class="stock-health">
  <section class="health-intro mb-3 d-flex justify-content-between align-items-start gap-3 flex-wrap">
    <div><h4 class="mb-1"><i class="ri ri-heart-pulse-line page-title-icon"></i><?php echo html_escape((string)($page_title ?? 'Kesehatan Stok Aktif')); ?></h4><p>Daftar ini membandingkan stok bulan yang dipilih dengan lot yang benar-benar masih tercatat, termasuk nilainya. Selisih jumlah diselesaikan dengan hitung fisik; selisih nilai diselesaikan dengan Koreksi Nilai Stok setelah jumlah sudah sama.</p></div>
    <a class="btn btn-outline-danger btn-sm" href="<?php echo site_url('inventory/stock/periods'); ?>"><i class="ri ri-calendar-check-line"></i> Tutup Periode</a>
  </section>

  <div class="health-kpis">
    <?php echo $healthCard((array)($summary['material'] ?? []), 'Bahan baku'); ?>
    <?php echo $healthCard((array)($summary['component'] ?? []), 'Component'); ?>
  </div>
  <div class="health-note mb-3"><strong>Cara pakai:</strong> fokus pada bulan aktif dan perbaiki hanya setelah mengecek stok fisik. Selisih jumlah berarti stok bulanan dan lot tidak sama. Selisih nilai berarti jumlah mungkin sama, tetapi harga/nilai lot tidak sama. <strong>Lot tanpa stok bulanan</strong> berarti ada lot yang perlu ditelusuri sebelum cut-off.</div>
  <?php if (!$isActiveCalendarMonth): ?><div class="health-note mb-3"><strong>Bulan investigasi:</strong> Anda sedang melihat bulan lama. Angka ini dipakai untuk menelusuri sumber masalah, bukan untuk diposting sebagai koreksi, karena saldo lot berjalan berubah setelah tanggal tersebut. Untuk menyelesaikan temuan, buka kembali <a href="<?php echo html_escape($activeMonthUrl); ?>">bulan aktif</a>; cut-off lama tetap aman dan tidak berubah diam-diam.</div><?php endif; ?>

  <form method="get" action="<?php echo $baseUrl; ?>" class="health-filter mb-3"><div class="health-filter-grid">
    <div class="wide"><label>Cari barang, profil, atau divisi</label><input class="form-control form-control-sm" name="q" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Contoh: NORI, BAR, atau kode profil"></div>
    <div><label>Bulan aktif</label><input class="form-control form-control-sm" type="month" name="month" value="<?php echo html_escape(substr($month, 0, 7)); ?>"></div>
    <div><label>Jenis stok</label><select class="form-select form-select-sm" name="stock_domain"><option value="">Semua</option><option value="MATERIAL" <?php echo (($filters['stock_domain'] ?? '') === 'MATERIAL') ? 'selected' : ''; ?>>Bahan baku</option><option value="COMPONENT" <?php echo (($filters['stock_domain'] ?? '') === 'COMPONENT') ? 'selected' : ''; ?>>Component</option></select></div>
    <div><label>Divisi</label><select class="form-select form-select-sm" name="division_id"><option value="0">Semua divisi / gudang</option><?php foreach($divisions as $division): ?><option value="<?php echo (int)($division['id'] ?? 0); ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)($division['id'] ?? 0)) ? 'selected' : ''; ?>><?php echo html_escape((string)($division['name'] ?? $division['code'] ?? '-')); ?></option><?php endforeach; ?></select></div>
    <div><label>Baris</label><select class="form-select form-select-sm" name="per_page"><?php foreach([25,50,100] as $per): ?><option value="<?php echo $per; ?>" <?php echo ((int)($pg['per_page'] ?? 50) === $per) ? 'selected' : ''; ?>><?php echo $per; ?></option><?php endforeach; ?></select></div>
    <div class="d-flex gap-2"><button class="btn btn-danger btn-sm px-3" type="submit">Terapkan</button><a class="btn btn-outline-secondary btn-sm" href="<?php echo $baseUrl; ?>">Reset</a></div>
  </div></form>

  <div class="health-table-card"><div id="stockHealthStickyHead" class="health-sticky-head" aria-hidden="true"></div><div id="stockHealthTableWrap" class="health-table-wrap"><table id="stockHealthTable" class="table table-sm health-table align-middle"><thead><tr><th>Jenis</th><th>Area</th><th>Barang / profil</th><th class="text-end">Stok sistem</th><th class="text-end">Lot aktif</th><th class="text-end">Selisih qty</th><th class="text-end">Nilai stok</th><th class="text-end">Nilai lot</th><th class="text-end">Selisih nilai</th><th>Temuan</th><th>Jejak terakhir</th><th class="text-center">Aksi aman</th></tr></thead><tbody>
  <?php if (empty($rows)): ?><tr><td colspan="12" class="text-center text-muted py-4">Tidak ada selisih stok, lot, atau nilai untuk filter ini.</td></tr><?php else: foreach($rows as $row): ?>
    <?php $issue = (string)($row['issue_type'] ?? ''); $profile = trim((string)($row['profile_key'] ?? '')); ?>
    <tr>
      <td><strong><?php echo strtoupper((string)($row['stock_domain'] ?? '')) === 'COMPONENT' ? 'Component' : 'Bahan baku'; ?></strong><div class="health-sub"><?php echo html_escape((string)($row['location_scope'] ?? '-')); ?></div></td>
      <td><strong><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></strong><div class="health-sub"><?php echo html_escape((string)($row['location_type'] ?? '-')); ?></div></td>
      <td><div class="health-name"><?php echo html_escape((string)($row['inventory_name'] ?? '-')); ?></div><?php if($profile !== ''): ?><div class="health-sub">Profil: <?php echo html_escape(substr($profile, 0, 14)); ?>...</div><?php endif; ?></td>
      <td class="text-end"><?php echo $fmtQty($row['stock_qty'] ?? 0); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></td>
      <td class="text-end"><?php echo $fmtQty($row['lot_qty'] ?? 0); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?><div class="health-sub"><?php echo number_format((int)($row['lot_count'] ?? 0), 0, ',', '.'); ?> lot</div></td>
      <td class="text-end <?php echo abs((float)($row['qty_gap'] ?? 0)) > 0.0001 ? 'text-danger fw-bold' : ''; ?>"><?php echo $fmtQty($row['qty_gap'] ?? 0); ?></td>
      <td class="text-end"><?php echo $fmtMoney($row['stock_value'] ?? 0); ?></td>
      <td class="text-end"><?php echo $fmtMoney($row['lot_value'] ?? 0); ?></td>
      <td class="text-end <?php echo abs((float)($row['value_gap'] ?? 0)) > 1 ? 'text-danger fw-bold' : ''; ?>"><?php echo $fmtMoney($row['value_gap'] ?? 0); ?></td>
      <td><span class="health-chip <?php echo $issueClass($issue); ?>"><?php echo html_escape($issueLabel($issue)); ?></span></td>
      <td><div><?php echo html_escape((string)($row['last_activity_at'] ?? '-')); ?></div><div class="health-sub"><?php echo html_escape((string)($row['last_movement_table'] ?? 'Lot / stok aktif')); ?><?php if(!empty($row['last_movement_id'])): ?> #<?php echo (int)$row['last_movement_id']; ?><?php endif; ?></div></td>
      <td class="text-center"><?php if (!$isActiveCalendarMonth): ?><a class="btn btn-outline-secondary btn-sm" href="<?php echo html_escape($activeMonthUrl); ?>">Buka Bulan Aktif</a><?php elseif ($issue === 'SELISIH_NILAI' && (int)($row['monthly_stock_id'] ?? 0) > 0): ?><a class="btn btn-outline-warning btn-sm" href="<?php echo html_escape($valueReconcileUrl($row)); ?>">Koreksi Nilai</a><?php else: ?><a class="btn btn-outline-danger btn-sm" href="<?php echo html_escape($reconcileUrl($row)); ?>"><?php echo in_array($issue, ['SELISIH_QTY', 'QTY_DAN_NILAI'], true) ? 'Hitung Fisik' : 'Telusuri Lot'; ?></a><?php endif; ?></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody></table></div><div class="health-pagination"><span class="small text-muted me-2">Menampilkan <?php echo number_format(count($rows),0,',','.'); ?> dari <?php echo number_format((int)($pg['total'] ?? 0),0,',','.'); ?> temuan pada <?php echo html_escape($asOfDate); ?>.</span><?php if((int)($pg['page'] ?? 1)>1): ?><a class="btn btn-outline-secondary btn-sm" href="<?php echo $pageLink((int)$pg['page']-1); ?>">Sebelumnya</a><?php endif; ?><span class="small fw-semibold">Halaman <?php echo (int)($pg['page'] ?? 1); ?> / <?php echo (int)($pg['total_pages'] ?? 1); ?></span><?php if((int)($pg['page'] ?? 1)<(int)($pg['total_pages'] ?? 1)): ?><a class="btn btn-outline-secondary btn-sm" href="<?php echo $pageLink((int)$pg['page']+1); ?>">Berikutnya</a><?php endif; ?></div></div>
</div>

<script>
(function () {
  var wrapper = document.getElementById('stockHealthTableWrap');
  var sourceTable = document.getElementById('stockHealthTable');
  var host = document.getElementById('stockHealthStickyHead');
  if (!wrapper || !sourceTable || !sourceTable.tHead || !host) return;

  var cloneTable = sourceTable.cloneNode(false);
  var cloneHead = sourceTable.tHead.cloneNode(true);
  cloneTable.appendChild(cloneHead);
  host.appendChild(cloneTable);

  function syncHeader() {
    var sourceCells = sourceTable.tHead.querySelectorAll('th');
    var cloneCells = cloneHead.querySelectorAll('th');
    var tableWidth = sourceTable.getBoundingClientRect().width;

    host.style.left = wrapper.offsetLeft + 'px';
    host.style.top = wrapper.offsetTop + 'px';
    host.style.width = wrapper.clientWidth + 'px';
    cloneTable.style.width = tableWidth + 'px';
    cloneTable.style.minWidth = tableWidth + 'px';
    cloneTable.style.transform = 'translateX(' + (-wrapper.scrollLeft) + 'px)';

    for (var i = 0; i < sourceCells.length; i++) {
      var width = sourceCells[i].getBoundingClientRect().width + 'px';
      cloneCells[i].style.width = width;
      cloneCells[i].style.minWidth = width;
      cloneCells[i].style.maxWidth = width;
    }

    host.style.display = wrapper.scrollTop > 0 ? 'block' : 'none';
  }

  wrapper.addEventListener('scroll', syncHeader, { passive: true });
  window.addEventListener('resize', syncHeader);
  if (window.ResizeObserver) {
    var observer = new ResizeObserver(syncHeader);
    observer.observe(wrapper);
    observer.observe(sourceTable);
  }
  syncHeader();
}());
</script>
