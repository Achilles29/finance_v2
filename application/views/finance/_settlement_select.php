<?php $selected=(int)($settlement_selected??0);$chargeSelected=(int)($settlement_charge_selected??0);$disabled=!empty($settlement_disabled); ?>
<div class="finance-settlement-picker" data-lookup="<?= html_escape(site_url('finance-reports/control/lookup/')) ?>" data-selected="<?= $selected ?>" data-charge-selected="<?= $chargeSelected ?>">
<label class="form-label small mt-2">Referensi Kontrol Settlement</label>
<?php if(!$disabled): ?><details class="small mb-2"><summary>Cari seluruh riwayat settlement</summary>
<input class="form-control form-control-sm mt-1" data-search="q" placeholder="Nomor, metode, atau nama rekap" aria-label="Cari settlement" maxlength="80">
<div class="d-flex gap-1 mt-1"><input class="form-control form-control-sm" type="date" data-search="from" aria-label="Dari tanggal settlement"><input class="form-control form-control-sm" type="date" data-search="to" aria-label="Sampai tanggal settlement"></div>
<div class="d-flex gap-2 align-items-center mt-1"><button class="btn btn-sm btn-outline-secondary" type="button" data-page="-1">Sebelumnya</button><span data-pages></span><button class="btn btn-sm btn-outline-secondary" type="button" data-page="1">Berikutnya</button></div></details><?php endif; ?>
<select class="form-select form-select-sm settlement-control-select" data-role="settlement-control" <?= !empty($settlement_disabled)?'disabled':'' ?>>
  <option value="">Tidak terkait settlement</option>
  <?php if($selected && !in_array($selected,array_map('intval',array_column((array)($settlement_options??[]),'id')),true)): ?><option value="<?= $selected ?>" selected>Settlement #<?= $selected ?></option><?php endif; ?>
  <?php foreach ((array)($settlement_options??[]) as $sc): ?>
    <option value="<?= (int)$sc['id'] ?>" <?= $selected===(int)$sc['id']?'selected':'' ?>><?= html_escape('#'.$sc['id'].' · '.$sc['revenue_date'].' · '.$sc['method_name'].' · '.$sc['provider_reference']) ?></option>
  <?php endforeach; ?>
</select>
<label class="small mt-2">Dokumen / baris biaya</label><select class="form-select form-select-sm" data-role="settlement-charge" <?= $disabled?'disabled':'' ?>><option value="">Pilih rincian biaya jika terkait settlement</option><?php if($chargeSelected): ?><option value="<?= $chargeSelected ?>" selected>Rincian #<?= $chargeSelected ?></option><?php endif; ?></select>
<div class="small text-muted" data-picker-status aria-live="polite"></div>
<div class="small text-muted">Promo/platform wajib tertaut. Pilih rincian dengan nominal, kategori, arah, dan tanggal yang sama. <a href="<?= site_url('finance-reports/control?tab=settlement') ?>" target="_blank" rel="noopener">Buat / periksa settlement dan rincian biaya</a>. Pencarian mencakup seluruh riwayat, 25 per halaman.</div>
</div><script src="<?= html_escape(base_url('assets/js/finance-settlement-picker.js')) ?>" defer></script>
