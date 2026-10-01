<?php
$filters = is_array($filters ?? null) ? $filters : [];
$summary = is_array($summary ?? null) ? $summary : [];
$rows = is_array($rows ?? null) ? $rows : [];
$health = is_array($current_health ?? null) ? $current_health : [];
$pg = is_array($pg ?? null) ? $pg : [];
$canCreate = !empty($can_create);

$baseUrl = site_url('inventory/stock/periods');
$fmtQty = static fn($value): string => number_format((float)$value, 4, ',', '.');
$fmtMoney = static fn($value): string => 'Rp ' . number_format((float)$value, 2, ',', '.');
$statusClass = static function (string $status): string {
    return match (strtoupper($status)) {
        'OPEN' => 'period-chip green', 'CLOSING' => 'period-chip amber', 'CLOSED' => 'period-chip dark', 'REOPENED' => 'period-chip red', default => 'period-chip gray',
    };
};
$statusLabel = static function (string $status): string {
    return match (strtoupper($status)) {
        'OPEN' => 'Terbuka', 'CLOSING' => 'Sedang ditutup', 'CLOSED' => 'Terkunci', 'REOPENED' => 'Dibuka kembali', default => $status ?: '-',
    };
};
$healthCard = static function (array $domain, string $title) use ($fmtQty, $fmtMoney): string {
    $mismatch = (int)($domain['mismatch_rows'] ?? 0);
    $class = $mismatch > 0 ? 'warn' : 'ok';
    return '<div class="period-health ' . $class . '"><span class="period-health-title">' . html_escape($title) . '</span><strong>' . number_format($mismatch, 0, ',', '.') . '</strong><span>baris perlu dicek</span><small>qty ' . $fmtQty($domain['absolute_gap'] ?? 0) . ' | nilai ' . $fmtMoney($domain['absolute_value_gap'] ?? 0) . '</small></div>';
};
$query = [
    'status' => (string)($filters['status'] ?? ''),
    'stock_domain' => (string)($filters['stock_domain'] ?? ''),
    'month_from' => (string)($filters['month_from'] ?? ''),
    'month_to' => (string)($filters['month_to'] ?? ''),
    'per_page' => (int)($pg['per_page'] ?? 50),
];
$pageLink = static function (int $page) use ($baseUrl, $query): string { return $baseUrl . '?' . http_build_query($query + ['page' => $page]); };
$mutationCsrfName = (string)($inventory_control_mutation_csrf_name ?? '');
$mutationCsrfValue = (string)($inventory_control_mutation_csrf_value ?? '');
$hasMutationCsrf = $mutationCsrfName !== '' && $mutationCsrfValue !== '';
?>

<style>
.period-control { --pc-ink:#3d2926; --pc-muted:#876d65; --pc-line:#ecdcd3; --pc-red:#a90e25; --pc-dark:#730b19; }
.period-intro{border:1px solid var(--pc-line);border-radius:18px;background:linear-gradient(135deg,#fffdf9,#fff5ef);padding:1rem 1.1rem;}.period-intro h4{color:var(--pc-ink);font-weight:850;}.period-intro p{color:var(--pc-muted);margin:0;max-width:960px;}
.period-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem;margin:1rem 0;}.period-box,.period-health{border:1px solid var(--pc-line);border-radius:14px;background:#fff;padding:.7rem .85rem;min-height:78px;}.period-box span,.period-health span{display:block;font-size:.64rem;color:var(--pc-muted);font-weight:800;text-transform:uppercase;letter-spacing:.05em;}.period-box strong,.period-health strong{display:block;margin-top:.22rem;font-size:1.16rem;color:var(--pc-ink);}.period-box.open strong{color:#19764f;}.period-box.closed strong{color:#58616b;}
.period-health-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem;margin-bottom:1rem;}.period-health{position:relative;overflow:hidden;}.period-health.warn{border-color:#f0cda1;background:#fffaf0;}.period-health.warn strong{color:#b55a00;}.period-health.ok{border-color:#c8e7d2;background:#f2fbf5;}.period-health.ok strong{color:#19764f;}.period-health small{display:block;color:var(--pc-muted);font-size:.72rem;margin-top:.15rem;}
.period-filter{border:1px solid var(--pc-line);border-radius:16px;background:#fff;padding:.8rem .9rem;}.period-filter-grid{display:grid;grid-template-columns:140px 140px 150px 150px 96px auto;gap:.55rem;align-items:end;}.period-filter label{display:block;font-size:.7rem;font-weight:800;color:#77584f;margin:0 0 .25rem;}
.period-table-card{border:1px solid var(--pc-line);border-radius:18px;overflow:hidden;background:#fff;box-shadow:0 10px 26px rgba(75,42,32,.06);}.period-table-wrap{overflow:auto;max-height:63vh;}.period-table{min-width:1040px;margin:0;border-collapse:separate;border-spacing:0;}.period-table thead th{position:sticky;top:0;z-index:3;background:linear-gradient(180deg,var(--pc-red),var(--pc-dark));color:#fff7f3;border-color:#8e0d20;font-size:.7rem;letter-spacing:.04em;white-space:nowrap;}.period-table td{vertical-align:middle;border-color:#f0dfd7;font-size:.8rem;color:var(--pc-ink);}.period-table tbody tr:nth-child(even) td{background:#fffaf7;}.period-chip{display:inline-flex;padding:.22rem .55rem;border-radius:999px;font-size:.65rem;font-weight:850;white-space:nowrap;}.period-chip.green{color:#19764f;background:#e7f7ed;}.period-chip.amber{color:#a65b00;background:#fff2d9;}.period-chip.dark{color:#4e5560;background:#e9ecef;}.period-chip.red{color:#b42318;background:#fde9e8;}.period-chip.gray{color:#707070;background:#f0f0f0;}.period-sub{font-size:.68rem;color:#9b7c71;margin-top:.13rem;}.period-pagination{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;padding:.8rem .9rem;border-top:1px solid var(--pc-line);}
@media(max-width:991px){.period-summary{grid-template-columns:repeat(2,minmax(0,1fr));}.period-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr));}}@media(max-width:575px){.period-health-grid,.period-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
</style>

<div class="period-control">
  <section class="period-intro mb-3 d-flex align-items-start justify-content-between gap-3 flex-wrap">
    <div><h4 class="mb-1"><i class="ri ri-calendar-check-line page-title-icon"></i><?php echo html_escape((string)($page_title ?? 'Tutup Periode Stok')); ?></h4><p>Siapkan periode, periksa kondisi stok aktif, lalu lakukan Posting Cut-off Resmi. Proses resmi akan membentuk opname dan stok awal bulan berikutnya terlebih dahulu, baru mengunci periode sumber agar laporan bulan tersebut tetap rapi.</p></div>
    <?php if ($canCreate): ?><button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#periodOpenModal"><i class="ri ri-add-line"></i> Siapkan Periode</button><?php endif; ?>
  </section>

  <div class="period-summary"><div class="period-box"><span>Total periode</span><strong><?php echo number_format((int)($summary['total_rows'] ?? 0),0,',','.'); ?></strong></div><div class="period-box open"><span>Masih terbuka</span><strong><?php echo number_format((int)($summary['open_rows'] ?? 0),0,',','.'); ?></strong></div><div class="period-box closed"><span>Terkunci</span><strong><?php echo number_format((int)($summary['closed_rows'] ?? 0),0,',','.'); ?></strong></div><div class="period-box"><span>Pernah dibuka kembali</span><strong><?php echo number_format((int)($summary['reopened_rows'] ?? 0),0,',','.'); ?></strong></div></div>

  <div class="period-health-grid">
    <?php echo $healthCard((array)($health['material'] ?? []), 'Pemeriksaan bahan baku'); ?>
    <?php echo $healthCard((array)($health['component'] ?? []), 'Pemeriksaan component'); ?>
  </div>
  <div class="alert alert-light border small mb-3"><strong>Pemeriksaan di atas hanya alarm ringan.</strong> Jika ada selisih, buka <a href="<?php echo site_url('inventory/stock/health'); ?>">Kesehatan Stok Aktif</a> untuk melihat barang, lot, dan nilai yang perlu ditelusuri. Anda masih dapat menutup periode dengan catatan dan konfirmasi sadar; sistem tidak akan melakukan perbaikan stok secara otomatis.</div>

  <form method="get" action="<?php echo $baseUrl; ?>" class="period-filter mb-3"><div class="period-filter-grid">
    <div><label>Status</label><select class="form-select form-select-sm" name="status"><option value="">Semua</option><?php foreach (['OPEN'=>'Terbuka','CLOSING'=>'Sedang ditutup','CLOSED'=>'Terkunci','REOPENED'=>'Dibuka kembali'] as $value=>$label): ?><option value="<?php echo $value; ?>" <?php echo (($filters['status'] ?? '') === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
    <div><label>Jenis stok</label><select class="form-select form-select-sm" name="stock_domain"><option value="">Semua</option><option value="MATERIAL" <?php echo (($filters['stock_domain'] ?? '') === 'MATERIAL') ? 'selected' : ''; ?>>Bahan baku</option><option value="COMPONENT" <?php echo (($filters['stock_domain'] ?? '') === 'COMPONENT') ? 'selected' : ''; ?>>Component</option></select></div>
    <div><label>Dari bulan</label><input class="form-control form-control-sm" type="month" name="month_from" value="<?php echo html_escape(substr((string)($filters['month_from'] ?? ''),0,7)); ?>"></div>
    <div><label>Sampai bulan</label><input class="form-control form-control-sm" type="month" name="month_to" value="<?php echo html_escape(substr((string)($filters['month_to'] ?? ''),0,7)); ?>"></div>
    <div><label>Baris</label><select class="form-select form-select-sm" name="per_page"><?php foreach([25,50,100] as $per): ?><option value="<?php echo $per; ?>" <?php echo ((int)($pg['per_page'] ?? 50)===$per)?'selected':''; ?>><?php echo $per; ?></option><?php endforeach; ?></select></div>
    <div class="d-flex gap-2"><button type="submit" class="btn btn-danger btn-sm px-3">Terapkan</button><a class="btn btn-outline-secondary btn-sm" href="<?php echo $baseUrl; ?>">Reset</a></div>
  </div></form>

  <div class="period-table-card"><div class="period-table-wrap"><table class="table table-sm period-table align-middle"><thead><tr><th>Bulan</th><th>Jenis stok</th><th>Status</th><th class="text-end">Defisit terbuka</th><th class="text-end">Qty defisit</th><th class="text-end">Jejak cut-off</th><th>Dibuat</th><th>Ditutup / dibuka lagi</th><th class="text-center">Aksi</th></tr></thead><tbody>
  <?php if(empty($rows)): ?><tr><td colspan="9" class="text-center text-muted py-4">Belum ada periode pada rentang bulan ini. Gunakan tombol <strong>Siapkan Periode</strong> untuk membuat bulan aktif.</td></tr><?php else: foreach($rows as $row): ?><tr><td><strong><?php echo html_escape(date('F Y',strtotime((string)($row['period_month'] ?? 'now')))); ?></strong><div class="period-sub"><?php echo html_escape((string)($row['period_month'] ?? '-')); ?></div></td><td><?php echo strtoupper((string)($row['stock_domain'] ?? '')) === 'MATERIAL' ? 'Bahan baku' : 'Component'; ?></td><td><span class="<?php echo $statusClass((string)($row['status'] ?? '')); ?>"><?php echo $statusLabel((string)($row['status'] ?? '')); ?></span></td><td class="text-end <?php echo ((int)($row['open_deficit_count'] ?? 0)>0)?'text-danger fw-bold':''; ?>"><?php echo number_format((int)($row['open_deficit_count'] ?? 0),0,',','.'); ?></td><td class="text-end"><?php echo $fmtQty($row['open_deficit_qty'] ?? 0); ?></td><td class="text-end"><?php echo number_format((int)($row['cutoff_event_count'] ?? 0),0,',','.'); ?></td><td><div><?php echo html_escape((string)($row['created_at'] ?? '-')); ?></div></td><td><div><?php echo html_escape((string)($row['closed_at'] ?? '-')); ?></div><?php if(!empty($row['reopened_at'])): ?><div class="period-sub">Buka lagi: <?php echo html_escape((string)$row['reopened_at']); ?></div><?php endif; ?></td><td class="text-center"><a class="btn btn-outline-danger btn-sm" href="<?php echo site_url('inventory/stock/periods/detail/' . (int)($row['id'] ?? 0)); ?>">Rincian</a></td></tr><?php endforeach; endif; ?>
  </tbody></table></div><div class="period-pagination"><span class="small text-muted me-2">Menampilkan <?php echo number_format(count($rows),0,',','.'); ?> dari <?php echo number_format((int)($pg['total'] ?? 0),0,',','.'); ?> periode.</span><?php if((int)($pg['page'] ?? 1)>1): ?><a class="btn btn-outline-secondary btn-sm" href="<?php echo $pageLink((int)$pg['page']-1); ?>">Sebelumnya</a><?php endif; ?><span class="small fw-semibold">Halaman <?php echo (int)($pg['page'] ?? 1); ?> / <?php echo (int)($pg['total_pages'] ?? 1); ?></span><?php if((int)($pg['page'] ?? 1)<(int)($pg['total_pages'] ?? 1)): ?><a class="btn btn-outline-secondary btn-sm" href="<?php echo $pageLink((int)$pg['page']+1); ?>">Berikutnya</a><?php endif; ?></div></div>
</div>

<?php if ($canCreate && $hasMutationCsrf): ?><div class="modal fade" id="periodOpenModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post" action="<?php echo site_url('inventory/stock/periods/open'); ?>"><div class="modal-header"><h5 class="modal-title">Siapkan Periode Stok</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="<?php echo html_escape($mutationCsrfName); ?>" value="<?php echo html_escape($mutationCsrfValue); ?>"><p class="small text-muted">Menyiapkan periode hanya membuat penjaga transaksi untuk bulan ini. Tidak ada stok, lot, atau movement yang diubah.</p><div class="mb-3"><label class="form-label">Bulan</label><input type="month" required class="form-control" name="period_month" value="<?php echo html_escape(date('Y-m')); ?>"></div><div class="mb-3"><label class="form-label">Jenis stok</label><select required class="form-select" name="stock_domain"><option value="BOTH">Bahan baku dan component</option><option value="MATERIAL">Bahan baku saja</option><option value="COMPONENT">Component saja</option></select></div><div><label class="form-label">Catatan (opsional)</label><textarea class="form-control" name="notes" rows="2" placeholder="Contoh: Periode aktif untuk transaksi Agustus"></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-danger" type="submit">Siapkan Periode</button></div></form></div></div><?php endif; ?>
