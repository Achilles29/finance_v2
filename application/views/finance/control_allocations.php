<?php
$esc=static fn($v)=>html_escape((string)$v);
$money=static fn($v)=>'Rp '.number_format((float)$v,2,',','.');
$json=static fn($v)=>html_escape(json_encode($v,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT));
$r=(array)$result;
?>
<?php if($tab==='settlement' && !empty($r['case'])): ?>
<section class="fc-panel"><h3>Bagi satu transfer ke beberapa rekap</h3>
<p class="fc-muted">Catat transfer satu kali di rekap asal. Pilih rekap lain pada rekening yang sama, lalu bagi nominalnya. Sisa tidak dianggap biaya. Simpan pembagian kosong untuk melepas seluruh alokasi. Saldo bank tidak berubah; periksa ulang konfirmasi lengkap pada setiap rekap setelah koreksi.</p>
<?php foreach($operation_details['incoming_allocations']??[] as $incoming): ?><p>Alokasi dari <a href="?<?= $esc(http_build_query(['tab'=>'settlement','date'=>$incoming['revenue_date'],'method_id'=>$incoming['payment_method_id']])) ?>"><?= $esc($incoming['reference_no']) ?></a>: <?= $money($incoming['amount']) ?> · <?= $esc($incoming['status']) ?><?= $incoming['status']==='VOID'?' (tidak dihitung)':'' ?>. Koreksi/batalkan melalui rekap asal.</p><?php endforeach; ?>
<?php foreach($operation_details['receipts']??[] as $receipt):
 $parts=$receipt['allocations']??[];
 if(empty($receipt['distribution'])) $parts=[['settlement_id'=>$r['case']['id'],'amount'=>$receipt['amount'],'revenue_date'=>$r['case']['revenue_date'],'method_name'=>'Rekap asal']];
 $allocated=array_sum(array_column($parts,'amount'));
?>
<details class="fc-panel"><summary><?= $esc($receipt['reference_no']) ?> · <?= $money($receipt['amount']) ?> · Sisa belum dialokasikan <?= $money((float)$receipt['amount']-$allocated) ?> · <?= $esc($receipt['status']) ?></summary>
<?php if($can_edit && $receipt['status']==='ACTIVE'): ?>
<form class="fc-allocation-form" data-account="<?= (int)$receipt['account_id'] ?>" data-parts="<?= $json($parts) ?>">
<input type="hidden" name="receipt_id" value="<?= (int)$receipt['id'] ?>"><input type="hidden" name="revision" value="<?= (int)($receipt['distribution']['revision']??0) ?>"><input type="hidden" name="request_key" value="<?= bin2hex(random_bytes(16)) ?>">
<div class="fc-filter mt-3"><label>Cari rekap (referensi / nomor)<input class="form-control" data-role="allocation-search" maxlength="80"></label><button type="button" class="btn btn-outline-secondary" data-role="search">Cari</button><button type="button" class="btn btn-outline-secondary" data-role="next" disabled>Hasil berikutnya</button></div>
<div class="fc-filter"><label>Rekap rekening yang sama<select class="form-select" data-role="choices"><option value="">Cari rekap terlebih dahulu</option></select></label><button type="button" class="btn btn-outline-primary" data-role="add">Tambah rekap</button></div>
<div data-role="parts"></div><label class="d-block my-3">Alasan pembagian/koreksi<input class="form-control" name="notes" maxlength="255" required></label><button class="btn btn-primary" type="submit">Simpan pembagian, tanpa mutasi kas</button>
</form>
<?php else: foreach($parts as $part): ?><p>Rekap #<?= (int)$part['settlement_id'] ?>: <?= $money($part['amount']) ?></p><?php endforeach; endif; ?>
</details><?php endforeach; ?></section>
<?php elseif($tab==='bank-review'): ?>
<section class="fc-panel"><h3>Cocokkan rekening koran dengan pencatatan aplikasi</h3>
<div class="fc-note">Impor hanya menyimpan baris pembanding. Tidak membuat pemasukan, pengeluaran, biaya atau transfer. Saran bukan bukti kecocokan; periksa referensi sebelum konfirmasi. Versi ini mencocokkan satu baris bank dengan satu mutasi bertanggal, rekening, arah dan nominal yang sama.</div>
<form method="get" class="fc-filter mt-3"><input type="hidden" name="tab" value="bank-review"><label>Rekening<select name="account_id" class="form-select"><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$r['account_id']===(int)$a['id']?'selected':'' ?>><?= $esc($a['account_name']) ?></option><?php endforeach; ?></select></label><button class="btn btn-outline-primary">Tampilkan</button></form>
<?php if($can_edit && $r['account_id']): ?><details><summary>Impor CSV — pratinjau dahulu</summary><p class="fc-muted mt-2">UTF-8, maksimal 1 MB / 500 transaksi. Baris pertama harus judul kolom. Nomor kolom dimulai dari 1. Pilih kolom tanggal, referensi, uang masuk dan uang keluar yang berbeda; referensi wajib diisi. Contoh: Tanggal;Referensi;Masuk;Keluar.</p>
<form id="fc-bank-import" class="fc-form"><input type="hidden" name="account_id" value="<?= (int)$r['account_id'] ?>"><label class="wide">File CSV<input type="file" class="form-control" data-role="csv" accept=".csv,text/csv" required></label>
<label>Pemisah kolom<select class="form-select" name="delimiter"><option value=";">Titik koma (;)</option><option value=",">Koma (,)</option></select></label>
<label>Format tanggal<select class="form-select" name="date_format"><option value="d/m/Y">14/09/2026</option><option value="Y-m-d">2026-09-14</option></select></label>
<label>Format nominal<select class="form-select" name="number_format"><option value="id">1.234,56 (Indonesia)</option><option value="en">1,234.56 (Inggris)</option></select></label>
<?php foreach(['date'=>'Tanggal','reference'=>'Referensi','in'=>'Uang masuk','out'=>'Uang keluar'] as $key=>$label): ?><label>Nomor kolom <?= $esc($label) ?><input class="form-control" name="column_<?= $key ?>" type="number" min="1" max="30" value="<?= array_search($key,['date','reference','in','out'],true)+1 ?>" required></label><?php endforeach; ?>
<div class="wide"><button class="btn btn-outline-primary" type="submit">Pratinjau CSV</button> <button class="btn btn-primary" type="button" data-role="confirm" disabled>Konfirmasi impor</button></div></form>
<div class="fc-scroll mt-3" id="fc-bank-preview" aria-live="polite"></div></details><?php endif; ?>
</section>
<section class="fc-panel"><h3>Baris bank dan saran kecocokan</h3>
<?php if(!$r['rows']): ?><p>Belum ada baris rekening koran untuk rekening ini.</p><?php endif; ?>
<?php foreach($r['rows'] as $row): ?><details class="fc-panel"><summary><?= $esc($row['statement_date'].' · '.$row['reference_no'].' · '.$row['direction']) ?> <?= $money($row['amount']) ?> — <?= $row['effective']?'Cocok':($row['active_mutation_id']?'Mutasi berubah/VOID — periksa ulang':'Belum cocok') ?></summary>
<?php if($row['active_mutation_id']): ?><p><?= $esc($row['mutation_no']) ?> · Tidak membuat mutasi tambahan.</p><?php endif; ?>
<?php if($can_edit): ?><form data-save="statement-match" class="fc-form" data-confirm="Pastikan referensi, tanggal dan nominal sesuai bukti bank. Ini hanya penautan, tidak mengubah kas."><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="revision" value="<?= (int)$row['revision'] ?>">
<?php if($row['active_mutation_id']): ?><input type="hidden" name="mutation_id" value="0"><?php else: ?><label>Saran (periksa referensinya)<select class="form-select" name="mutation_id" required><option value="">Pilih mutasi yang benar</option><?php foreach($row['candidates'] as $candidate): ?><option value="<?= (int)$candidate['id'] ?>"><?= $esc($candidate['mutation_no'].' · '.$candidate['ref_no'].' · '.$candidate['ref_module']) ?></option><?php endforeach; ?></select><small>Tidak ada saran? Periksa tanggal dan pencatatan di Mutasi Rekening. Jangan membuat transaksi ulang hanya agar terlihat cocok.</small></label><?php endif; ?>
<label>Alasan / hasil pemeriksaan<input class="form-control" name="reason" maxlength="255" required></label><div><button class="btn btn-outline-primary" <?= !$row['active_mutation_id']&&!$row['candidates']?'disabled':'' ?>><?= $row['active_mutation_id']?'Lepas kecocokan':'Konfirmasi kecocokan' ?></button></div></form><?php endif; ?></details><?php endforeach; ?>
<div class="d-flex gap-3"><span>Halaman <?= (int)$r['page'] ?></span><?php if($r['page']>1): ?><a href="?tab=bank-review&account_id=<?= (int)$r['account_id'] ?>&page=<?= (int)$r['page']-1 ?>">Sebelumnya</a><?php endif; ?><?php if($r['more']): ?><a href="?tab=bank-review&account_id=<?= (int)$r['account_id'] ?>&page=<?= (int)$r['page']+1 ?>">Berikutnya</a><?php endif; ?></div>
</section>
<?php endif; ?>
