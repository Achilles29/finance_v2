<?php
$base=$kind==='sr'?'store-requests/report':'procurement/reports/division-materials';
require __DIR__.'/_setup.php';
$r=$report; $isSr=$kind==='sr';
$this->load->view('purchase/_po_sr_tabs',['po_sr_active'=>$isSr?'report-sr':'report-materials']);
?>
<section class="report-workspace">
  <header class="rw-hero">
    <div><div class="rw-eyebrow">Namu / Procurement intelligence</div><h1><?= $esc($page_title) ?></h1>
    <p><?= $isSr?'Dari permintaan sampai realisasi. Telusuri pengiriman gudang per divisi, tanggal, kategori, dan profil barang.':'Satu pandangan untuk seluruh bahan baku yang masuk ke divisi: purchase langsung + pengiriman SR dari gudang.' ?></p></div>
    <div class="rw-actions"><?php if($can_export && !$error): ?><a class="rw-button" href="<?= $esc($url(['export'=>'csv'])) ?>">Unduh Excel / CSV</a><?php endif ?><button class="rw-button" type="button" data-report-print>Cetak</button></div>
  </header>
  <form class="rw-filter" method="get" action="<?= $esc(site_url($base)) ?>">
    <label>Bulan<input type="month" name="month" value="<?= $esc($f['month']) ?>" required></label>
    <label>Bandingkan dengan<input type="month" name="compare" value="<?= $esc($f['compare']) ?>" required></label>
    <label>Divisi<select name="division_id"><option value="0">Semua divisi</option><?php foreach($divisions as $d): ?><option value="<?= (int)$d['id'] ?>"<?= $selected($f['division_id'],$d['id']) ?>><?= $esc($d['name']) ?></option><?php endforeach ?></select></label>
    <label>Tujuan<select name="destination"><option value="">Semua tujuan</option><?php foreach(['BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER'] as $destination): ?><option<?= $selected($f['destination'],$destination) ?>><?= $esc($destination) ?></option><?php endforeach ?></select></label>
    <?php if($isSr): ?><label>Dasar tanggal<select name="basis"><option value="fulfillment"<?= $selected($f['basis'],'fulfillment') ?>>Realisasi pengiriman</option><option value="request"<?= $selected($f['basis'],'request') ?>>Permintaan SR</option></select></label>
    <label>Status SR<select name="status"><option value="">Semua aktif</option><?php foreach(['DRAFT','SUBMITTED','APPROVED','PARTIAL_FULFILLED','FULFILLED','REJECTED','VOID'] as $status): ?><option<?= $selected($f['status'],$status) ?>><?= $esc($status) ?></option><?php endforeach ?></select></label>
    <?php else: ?><label>Sumber<select name="source"><option value="">Purchase + SR</option><option value="PURCHASE"<?= $selected($f['source'],'PURCHASE') ?>>Purchase langsung</option><option value="SR"<?= $selected($f['source'],'SR') ?>>SR gudang</option></select></label><?php endif ?>
    <label class="rw-search">Cari dokumen / material / merk<input name="q" value="<?= $esc($f['q']) ?>" maxlength="150" placeholder="Nomor SR, PO, nama material..."></label>
    <input type="hidden" name="view" value="<?= $esc($f['view']) ?>">
    <button class="rw-button primary">Terapkan</button><a class="rw-button" href="<?= $esc(site_url($base)) ?>">Reset</a>
  </form>
  <?php if($error): ?><div class="rw-notice error" role="alert"><?= $esc($error) ?></div><?php else: $t=$r['totals']; ?>
  <?php if($f['day']!=='' || $f['category']!=='' || $f['profile']!==''): ?><div class="rw-notice info">Rincian dipersempit: <?= $esc($f['day']?:'') ?> <?= $f['category']!==''?' / kategori #'.$esc($f['category']):'' ?> <?= $f['profile']!==''?' / profil terpilih':'' ?>. <a href="<?= $esc($url(['day'=>'','category'=>'','profile'=>'','page'=>1])) ?>">Lihat seluruh bulan</a></div><?php endif ?>
  <?php if($r['request_basis']): ?><div class="rw-notice info">Dasar: tanggal permintaan SR. Nilai adalah realisasi pengiriman POSTED sampai saat laporan dibuka, termasuk pengiriman di bulan lain; bukan estimasi harga permintaan. Qty belum terpenuhi ditampilkan per baris dan satuan.</div><?php else: ?><div class="rw-notice info">Dasar: tanggal penerimaan / pengiriman yang sudah POSTED. Nilai mengikuti biaya stok historis, bukan pengeluaran kas atau HPP pemakaian. Dokumen VOID tidak dihitung.</div><?php endif ?>
  <div class="rw-kpis">
    <div class="rw-kpi"><span><?= $r['request_basis']?'Nilai realisasi SR terpilih':'Nilai bahan / barang tersalurkan' ?></span><strong>Rp <?= $money($t['value']) ?></strong><small><?= $esc($f['month']) ?><?= $f['month']===date('Y-m')?' / bulan masih berjalan':'' ?></small></div>
    <div class="rw-kpi teal"><span><?= $isSr?'Dokumen SR':'Purchase langsung' ?></span><strong><?= $isSr?number_format($t['documents'],0,',','.'):'Rp '.$money($t['purchase']) ?></strong><small><?= $isSr?'Dokumen unik, bukan jumlah pengiriman':'Hanya receipt persediaan langsung ke divisi' ?></small></div>
    <div class="rw-kpi gold"><span><?= $isSr?'Baris detail':'SR dari gudang' ?></span><strong><?= $isSr?number_format($t['lines'],0,',','.'):'Rp '.$money($t['sr']) ?></strong><small><?= $isSr?'Qty tetap dipisah menurut satuan':'Nilai barang, tidak dihitung sebagai kas keluar' ?></small></div>
    <div class="rw-kpi"><span>Pembanding <?= $esc($f['compare']) ?></span><strong>Rp <?= $money($r['previous']) ?></strong><small>Bulan penuh dengan filter yang sama, tanpa filter tanggal rincian.<?= $f['month']===date('Y-m')?' Bulan berjalan belum sebanding bulan penuh.':'' ?></small></div>
  </div>
  <?php if($t['zero_cost'] || $t['foreign_currency']): ?><div class="rw-notice"><?= (int)$t['zero_cost'] ?> baris memiliki biaya nol; jangan menganggapnya gratis sebelum verifikasi. <?= (int)$t['foreign_currency'] ?> baris berasal dari purchase non-IDR; nilai yang ditampilkan mengikuti ledger stok, tanpa konversi kurs tambahan.</div><?php endif ?>
  <nav class="rw-tabs" aria-label="Tampilan laporan"><?php foreach(['overview'=>'Ringkasan & Grafik','matrix'=>'Matrix Harian','detail'=>'Rincian Transaksi'] as $key=>$label): ?><a class="<?= $f['view']===$key?'active':'' ?>" href="<?= $esc($url(['view'=>$key,'page'=>1])) ?>"><?= $esc($label) ?></a><?php endforeach ?></nav>
  <?php if($f['view']==='overview'):
    $monthly=$r['monthly']; foreach($monthly as &$row){$row['url']=$url(['month'=>$row['label'],'day'=>'','page'=>1]);} unset($row);
    $daily=$r['daily']; foreach($daily as &$row){$row['url']=$url(['day'=>$row['label'],'view'=>'detail','page'=>1]);} unset($row);
    $series=[['key'=>'sr','label'=>'SR','color'=>'#11786d']]; if(!$isSr)$series[]=['key'=>'purchase','label'=>'Purchase langsung','color'=>'#781d36'];
  ?>
  <div class="rw-grid">
    <article class="rw-panel"><h2>Pergerakan antar bulan</h2><p class="rw-sub">Enam bulan terakhir dan bulan pembanding. Klik batang untuk membuka bulan tersebut.</p><div class="rw-chart" data-report-chart="proc-month-chart"></div><div class="rw-legend"><span><i></i>SR</span><?php if(!$isSr): ?><span><i class="out"></i>Purchase langsung</span><?php endif ?></div></article>
    <article class="rw-panel"><h2>Aktivitas harian</h2><p class="rw-sub">Klik tanggal untuk melihat dokumen dan material penyusunnya.</p><div class="rw-chart" data-report-chart="proc-day-chart"></div></article>
  </div>
  <script type="application/json" id="proc-month-chart"><?= $json(['title'=>'Nilai stok per bulan','rows'=>$monthly,'series'=>$series]) ?></script>
  <script type="application/json" id="proc-day-chart"><?= $json(['title'=>'Nilai stok per tanggal','rows'=>$daily,'series'=>$series]) ?></script>
  <div class="rw-grid">
    <article class="rw-panel"><h2>Distribusi per divisi</h2><p class="rw-sub">Klik divisi untuk menelusuri rincian transaksi.</p><div class="rw-table-wrap"><table class="rw-summary-table"><thead><tr><th>Divisi</th><th>SR</th><?php if(!$isSr): ?><th>Purchase</th><?php endif ?><th>Total</th></tr></thead><tbody><?php foreach($r['groups'] as $g): ?><tr><td><a href="<?= $esc($url(['division_id'=>$g['id'],'view'=>'detail','page'=>1])) ?>"><?= $esc($g['label']) ?></a></td><td class="rw-num"><?= $money($g['sr']) ?></td><?php if(!$isSr): ?><td class="rw-num"><?= $money($g['purchase']) ?></td><?php endif ?><td class="rw-num"><strong><?= $money($g['value']) ?></strong></td></tr><?php endforeach ?></tbody></table></div></article>
    <article class="rw-panel"><h2>Komposisi kategori</h2><p class="rw-sub">Kategori mengikuti master item. Klik untuk membuka materialnya.</p><div class="rw-table-wrap"><table class="rw-summary-table"><thead><tr><th>Kategori</th><th>Baris</th><th>Nilai</th></tr></thead><tbody><?php foreach($r['categories'] as $g): ?><tr><td><a href="<?= $esc($url(['category'=>(string)$g['id'],'view'=>'detail','page'=>1])) ?>"><?= $esc($g['label']) ?></a><span class="rw-progress"><b style="width:<?= min(100,max(0,$t['value']?100*$g['value']/$t['value']:0)) ?>%"></b></span></td><td class="rw-num"><?= (int)$g['lines'] ?></td><td class="rw-num"><?= $money($g['value']) ?></td></tr><?php endforeach ?></tbody></table></div></article>
  </div>
  <?php elseif($f['view']==='matrix'): ?>
  <article class="rw-panel"><h2>Matrix nilai harian / divisi & tujuan</h2><p class="rw-sub">Nilai rupiah. Klik sel untuk membuka rincian pada tanggal dan divisi tersebut.</p><div class="rw-table-wrap"><table class="rw-matrix"><thead><tr><th>Divisi / Tujuan</th><?php for($day=1;$day<=(int)substr($f['to'],8,2);$day++): ?><th><?= sprintf('%02d',$day) ?></th><?php endfor ?><th>Total</th></tr></thead><tbody><?php foreach($r['matrix'] as $g): ?><tr><td><strong><?= $esc($g['label']) ?></strong></td><?php for($day=1;$day<=(int)substr($f['to'],8,2);$day++): $date=$f['month'].'-'.sprintf('%02d',$day); ?><td class="rw-num <?= $date===date('Y-m-d')?'rw-today':'' ?>"><?php if(isset($g['cells'][$date])): ?><a href="<?= $esc($url(['day'=>$date,'division_id'=>$g['division_id'],'destination'=>$g['destination'],'view'=>'detail','page'=>1])) ?>"><?= $money($g['cells'][$date]) ?></a><?php else: ?>-<?php endif ?></td><?php endfor ?><td class="rw-num"><strong><?= $money($g['value']) ?></strong></td></tr><?php endforeach ?></tbody></table></div></article>
  <?php else: ?>
  <article class="rw-panel"><h2>Rincian yang dapat ditelusuri</h2><p class="rw-sub">Setiap profil dan pengiriman dipisahkan. Jangan menjumlahkan GR, ML, PCS, dan satuan beli berbeda menjadi satu angka.</p>
  <div class="rw-table-wrap"><table class="rw-detail-table"><thead><tr><th>Tanggal / Dokumen</th><th>Divisi / Tujuan</th><th>Kategori / Material</th><th>Qty beli</th><th>Qty isi</th><?php if($r['request_basis']): ?><th>Terpenuhi / Sisa</th><?php endif ?><th>Biaya / isi</th><th>Nilai</th><th>Detail</th></tr></thead><tbody>
  <?php foreach($r['pager']['rows'] as $row): ?><tr>
    <td><small><?= $esc($row['event_date']) ?></small><span class="rw-pill <?= $row['source']==='PURCHASE'?'purchase':'' ?>"><?= $esc($row['source']) ?></span> <?php if(($row['source']==='SR' && $can_sr)||($row['source']==='PURCHASE' && $can_purchase)): ?><a href="<?= $esc(site_url(($row['source']==='SR'?'store-requests/detail/':'purchase-orders/detail/').(int)$row['document_id'])) ?>"><?= $esc($row['document_no']) ?></a><?php else: ?><?= $esc($row['document_no']) ?><?php endif ?><small><?= $esc($row['delivery_no']) ?></small></td>
    <td><?= $esc($row['division_name']) ?><small><?= $esc($row['destination']) ?> / <?= $esc($row['status']) ?></small></td>
    <td><small><?= $esc($row['category_name']) ?></small><strong><?= $esc($row['display_name']) ?></strong><small><?= $esc($row['brand']?:'-') ?> / <?= $esc($row['description']) ?></small></td>
    <td class="rw-num"><?= $number($row['qty_buy']) ?><small><?= $esc($row['buy_unit']) ?></small></td><td class="rw-num"><?= $number($row['qty_content']) ?><small><?= $esc($row['content_unit']) ?></small></td>
    <?php if($r['request_basis']): ?><td class="rw-num"><?= $number($row['fulfilled_qty']) ?><small>Sisa <?= $number($row['pending_qty']) ?> <?= $esc($row['content_unit']) ?></small><small><?= $esc($row['line_status']) ?></small></td><?php endif ?>
    <td class="rw-num"><?= $number($row['unit_cost']) ?></td><td class="rw-num"><strong><?= $money($row['value_cents']) ?></strong></td>
    <td><details><summary>Profil</summary><small><?= $esc($row['profile_key']) ?></small><small><?= $esc($row['usage_purpose']) ?></small><a href="<?= $esc($url(['profile'=>$row['profile_key'],'page'=>1])) ?>">Telusuri profil</a></details></td>
  </tr><?php endforeach ?></tbody></table></div><?php require __DIR__.'/_pager.php'; ?></article>
  <?php endif ?>
  <?php if(!$t['lines']): ?><div class="rw-empty">Tidak ada transaksi sesuai filter. Pilih bulan sebelumnya atau ubah filter.</div><?php endif ?>
  <details class="rw-guide"><summary>Cara membaca laporan & sumber data</summary><p>Realisasi SR menggunakan tanggal fulfillment POSTED dan biaya per isi yang tersimpan saat pengiriman. Pengiriman sebagian dihitung masing-masing; permintaan belum dikirim tidak dinilai dengan harga master hari ini.</p><p>Laporan bahan baku hanya mencakup persediaan dengan tujuan BAHAN_BAKU: purchase yang diterima langsung ke divisi dan SR dari gudang. Pembelian ke gudang, transfer antar divisi, opening, adjustment, barang konsumabel, serta component tidak termasuk.</p><p>Ini laporan penyaluran persediaan, bukan pemakaian bahan, laba-rugi, atau pembayaran. CSV memuat seluruh rincian sesuai filter dan dapat dibuka di Excel. Izin ekspor terpisah dari izin lihat. Kategori/divisi mengikuti nama master saat laporan dibuka; qty, biaya, dan profil mengikuti dokumen historis.</p></details>
  <?php endif ?>
</section>
