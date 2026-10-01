<?php $reviewHistory = is_array($stock_review_history ?? null) ? $stock_review_history : []; ?>
<?php if ($reviewHistory): ?>
<section class="card mb-3"><div class="card-body">
 <h6>Bukti cek stok saat verifikasi pengajuan divisi</h6>
 <p class="small text-muted">Angka di bawah adalah bukti pada saat disetujui, bukan saldo saat ini. Bukti yang sama diteruskan ke SR/PO terkait; tidak membuat pencatatan stok kedua.</p>
 <?php foreach ($reviewHistory as $review): ?>
 <?php $snapshot = json_decode((string)($review['snapshot_json'] ?? ''),true); ?>
 <details class="border rounded p-3 mb-2">
  <summary><?= html_escape((string)($review['request_no'] ?? '-')) ?> · <?= html_escape((string)($review['reviewed_at'] ?? '-')) ?> · <?= html_escape((string)($review['reviewer_name'] ?? $review['reviewed_by'] ?? '-')) ?></summary>
  <p class="mt-2 mb-1">Konfirmasi ke: <?= html_escape((string)(($review['confirmed_with'] ?? '') ?: 'Tidak diperlukan: saldo divisi diketahui dan tidak tersisa')) ?></p>
  <p>Alasan: <?= html_escape((string)(($review['reason'] ?? '') ?: '-')) ?></p>
  <?php foreach ((array)($snapshot['rows'] ?? []) as $row): ?>
   <div class="border-top py-2">
    <strong><?= html_escape((string)($row['name'] ?? '-')) ?></strong>
    <div>Pengajuan: <?= number_format((float)($row['requested'] ?? 0),4,',','.') ?> <?= html_escape((string)($row['uom'] ?? '?')) ?></div>
    <?php foreach (['division'=>'Divisi peminta','warehouse'=>'Gudang'] as $key=>$label): ?>
     <?php $balance = (array)($row[$key] ?? []); ?>
     <div><?= $label ?>: <?= isset($balance['qty']) ? number_format((float)$balance['qty'],4,',','.').' '.html_escape((string)($row['uom'] ?? '?')) : 'Belum diketahui' ?> — <?= html_escape((string)($balance['message'] ?? '')) ?></div>
    <?php endforeach; ?>
   </div>
  <?php endforeach; ?>
 </details>
 <?php endforeach; ?>
</div></section>
<?php endif; ?>
