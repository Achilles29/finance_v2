<?php
$ok = !empty($dashboard['ok']);
$header = (array)($dashboard['header'] ?? []); $rows = (array)($dashboard['rows'] ?? []);
$summary = (array)($dashboard['summary'] ?? []); $accounts = (array)($dashboard['accounts'] ?? []);
$reconDate = (string)($dashboard['reconciliation_date'] ?? date('Y-m-d'));
$revenueDate = (string)($dashboard['revenue_date'] ?? date('Y-m-d', strtotime('-1 day')));
$headerId = (int)($header['id'] ?? 0); $canEdit = !empty($can_reconcile_edit);
$money = static fn($v) => 'Rp ' . number_format((float)$v, 2, ',', '.');
?>
<style>
  .revenue-recon{--wine:#9d252b;--ink:#172033;--line:#e8e1de}.rr-head{display:flex;justify-content:space-between;gap:1rem;align-items:end;margin-bottom:1rem}.rr-head h4{font-weight:850;color:var(--ink);margin:0}.rr-head p{font-size:.78rem;color:#6d7280;margin:.3rem 0 0}.rr-filter{display:flex;gap:.55rem;align-items:end;background:#fff;border:1px solid var(--line);padding:.7rem;border-radius:12px}.rr-filter label{display:block;font-size:.65rem;font-weight:800;color:#6c6260}.rr-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:.7rem;margin-bottom:1rem}.rr-stat{background:#fff;border:1px solid var(--line);border-radius:13px;padding:.8rem}.rr-stat span{font-size:.64rem;font-weight:800;text-transform:uppercase;color:#827975}.rr-stat strong{display:block;font-size:1.05rem;color:var(--ink);margin-top:.25rem}.rr-help{background:#fff8ef;border:1px solid #f2dcc0;color:#654f36;border-radius:12px;padding:.75rem .9rem;font-size:.74rem;margin-bottom:1rem}.rr-method{background:#fff;border:1px solid var(--line);border-radius:14px;margin-bottom:.7rem;overflow:hidden}.rr-method-head{display:flex;justify-content:space-between;align-items:center;padding:.75rem .9rem;background:#fffdfc;border-bottom:1px solid #f1e9e5}.rr-method-name{font-weight:850;color:var(--ink)}.rr-method-meta{font-size:.67rem;color:#7b7370}.rr-badge{font-size:.62rem;font-weight:850;padding:.25rem .45rem;border-radius:99px;background:#eef1f5}.rr-badge.MATCHED,.rr-badge.POSTED{color:#087452;background:#e6f7ef}.rr-badge.OPEN{color:#a45b00;background:#fff0da}.rr-grid{display:grid;grid-template-columns:1fr 1.15fr 1fr 2.2fr}.rr-cell{padding:.8rem;border-right:1px solid #f0e9e5}.rr-cell:last-child{border:0}.rr-label{font-size:.61rem;text-transform:uppercase;font-weight:850;color:#817773;margin-bottom:.3rem}.rr-value{font-weight:850;color:var(--ink)}.rr-note{font-size:.64rem;color:#858087;margin-top:.2rem}.rr-actions{display:grid;grid-template-columns:1.15fr .9fr 1.3fr auto;gap:.45rem;align-items:end}.rr-actions label{font-size:.61rem;font-weight:800;color:#746c69}.rr-actions label span{display:block;margin-bottom:.2rem}.rr-toast{position:fixed;right:1rem;bottom:1rem;background:#172033;color:#fff;padding:.75rem 1rem;border-radius:10px;display:none;z-index:1090}.rr-toast.error{background:#a61f28}.rr-history{margin-top:1rem;background:#fff;border:1px solid var(--line);border-radius:13px;padding:.8rem}.rr-history a{font-weight:750;color:var(--wine);text-decoration:none}@media(max-width:900px){.rr-head{align-items:stretch;flex-direction:column}.rr-filter{flex-wrap:wrap}.rr-summary{grid-template-columns:1fr 1fr}.rr-grid{display:block}.rr-cell{border-right:0;border-bottom:1px solid #f0e9e5}.rr-actions{grid-template-columns:1fr 1fr}.rr-actions .rr-note-input{grid-column:1/-1}}@media(max-width:520px){.rr-summary{grid-template-columns:1fr 1fr}.rr-filter>div{flex:1;min-width:135px}.rr-actions{grid-template-columns:1fr}.rr-actions .rr-note-input{grid-column:auto}}
</style>
<style>
  .rr-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .rr-grid > .rr-cell:last-child { grid-column: 1 / -1; border-top: 1px solid #f0e9e5; }
  .rr-actions { grid-template-columns: minmax(180px, 1.4fr) minmax(160px, 1fr) minmax(180px, 1.4fr) auto; }
  .rr-actions > div { display: flex; gap: .5rem; flex-wrap: wrap; }
  @media (max-width: 900px) { .rr-actions { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } }
  @media (max-width: 520px) { .rr-actions { grid-template-columns: minmax(0, 1fr); } }
</style>
<div class="page-wrapper revenue-recon"><div class="container-xl py-3">
  <?php $this->load->view('finance/_tabs', ['finance_tab_active'=>'revenue-reconciliation']); ?>
  <div class="rr-head"><div><h4><i class="ri-hand-coin-line text-danger"></i> Rekonsiliasi Pendapatan</h4><p>Cek penerimaan riil harian per metode pembayaran dan tindak lanjuti selisihnya ke rekening kas/bank.</p></div>
    <form class="rr-filter" method="get"><div><label>Tanggal rekonsiliasi</label><input class="form-control form-control-sm" type="date" name="date" max="<?= date('Y-m-d') ?>" value="<?= html_escape($reconDate) ?>"></div><div><label>Pendapatan tanggal</label><input class="form-control form-control-sm" type="date" name="revenue_date" max="<?= html_escape($reconDate) ?>" value="<?= html_escape($revenueDate) ?>"></div><button class="btn btn-sm btn-primary">Tampilkan</button><?php if($canEdit): ?><button class="btn btn-sm btn-outline-primary" type="button" id="rr-new">Sesi Baru</button><?php endif; ?></form>
  </div>
  <?php if(!$ok): ?><div class="alert alert-danger"><?= html_escape((string)($dashboard['message'] ?? 'Modul belum siap.')) ?></div><?php else: ?>
  <div class="rr-summary"><div class="rr-stat"><span>Penerimaan POS</span><strong><?= $money($summary['expected_total'] ?? 0) ?></strong></div><div class="rr-stat"><span>Penerimaan Riil Diinput</span><strong><?= $money($summary['actual_total'] ?? 0) ?></strong></div><div class="rr-stat"><span>Selisih Bersih</span><strong><?= $money($summary['difference_total'] ?? 0) ?></strong></div><div class="rr-stat"><span>Progres</span><strong><?= (int)($summary['checked'] ?? 0) ?>/<?= count($rows) ?> metode</strong></div></div>
  <div class="rr-help"><b>Alur:</b> pilih tanggal pendapatan, lalu isi penerimaan riil hari tersebut untuk setiap metode (bukan seluruh saldo rekening atau saldo awal kas). Angka POS mencakup pembayaran lunas dan deposit, dikurangi refund. Selisih boleh <b>dibiarkan terbuka</b>, <b>dimutasi masuk/keluar</b>, atau <b>ditransfer antar rekening</b>. Simpan hanya mencatat hasil cek; Posting baru mengubah saldo rekening pada tanggal rekonsiliasi, tanpa menggandakan penjualan.</div>
  <?php foreach($rows as $row): $posted=(string)$row['status']==='POSTED'; $actual=$row['actual_amount']; $diff=$row['difference_amount']; ?>
  <article class="rr-method"><div class="rr-method-head"><div><div class="rr-method-name"><?= html_escape((string)$row['method_name']) ?></div><div class="rr-method-meta"><?= html_escape((string)$row['method_type']) ?> · <?= (int)$row['transaction_count'] ?> transaksi · pendapatan <?= html_escape(date('d/m/Y', strtotime($revenueDate))) ?></div></div><span class="rr-badge <?= html_escape((string)$row['status']) ?>"><?= html_escape((string)$row['status']) ?></span></div>
    <form class="rr-line"><input type="hidden" name="reconciliation_id" value="<?= $headerId ?>"><input type="hidden" name="reconciliation_date" value="<?= html_escape($reconDate) ?>"><input type="hidden" name="revenue_date" value="<?= html_escape($revenueDate) ?>"><input type="hidden" name="payment_method_id" value="<?= (int)$row['payment_method_id'] ?>"><input type="hidden" name="line_id" value="<?= (int)$row['line_id'] ?>">
    <div class="rr-grid"><div class="rr-cell"><div class="rr-label">Penerimaan menurut POS</div><div class="rr-value"><?= $money($row['expected_amount']) ?></div><div class="rr-note">Pembayaran PAID: final + deposit − refund.</div></div>
    <div class="rr-cell"><div class="rr-label">Penerimaan riil hari tersebut</div><input class="form-control form-control-sm" name="actual_amount" inputmode="decimal" placeholder="0" value="<?= $actual===null?'':html_escape(number_format((float)$actual,2,',','.')) ?>" <?= (!$canEdit||$posted)?'disabled':'' ?>></div>
    <div class="rr-cell"><div class="rr-label">Sisa selisih harian</div><div class="rr-value"><?= $actual===null?'—':$money($diff) ?></div><div class="rr-note"><?= $actual===null?'Belum diperiksa':((float)$diff<0?'Potongan/kurang diterima':((float)$diff>0?'Lebih diterima':'Sesuai')) ?></div><?php if($posted): ?><div class="rr-note">Selisih saat posting: <?= $money($row['posted_difference_amount']??0) ?> (riwayat). <?= abs((float)$diff)>=.005?'Ada perubahan setelah posting; periksa melalui sesi baru.':'' ?></div><?php endif; ?></div>
    <div class="rr-cell"><div class="rr-actions"><label><span>Rekening yang disesuaikan</span><select class="form-select form-select-sm" name="account_id" <?= (!$canEdit||$posted)?'disabled':'' ?>><option value="">Pilih rekening</option><?php foreach($accounts as $account): ?><option value="<?= (int)$account['id'] ?>" <?= (int)$row['account_id']===(int)$account['id']?'selected':'' ?>><?= html_escape((string)$account['account_name']) ?></option><?php endforeach; ?></select></label><label><span>Tindakan</span><select class="form-select form-select-sm" name="resolution_type" <?= (!$canEdit||$posted)?'disabled':'' ?>><option value="NONE">Biarkan terbuka</option><option value="OUT" <?= (string)($row['resolution_type']??'')==='OUT'?'selected':'' ?>>Mutasi keluar</option><option value="IN" <?= (string)($row['resolution_type']??'')==='IN'?'selected':'' ?>>Mutasi masuk</option><?php if(!empty($dashboard['transfer_ready'])): ?><option value="TRANSFER" <?= (string)($row['resolution_type']??'')==='TRANSFER'?'selected':'' ?>>Transfer antar rekening</option><?php endif; ?></select></label><label class="rr-note-input"><span>Catatan selisih</span><input class="form-control form-control-sm" name="resolution_note" value="<?= html_escape((string)($row['resolution_note']??'')) ?>" placeholder="Alasan selisih / koreksi" <?= (!$canEdit||$posted)?'disabled':'' ?>></label><div><?php if($canEdit&&!$posted): ?><button class="btn btn-sm btn-outline-primary rr-save" type="button">Simpan</button><?php if((int)$row['line_id']>0 && abs((float)$diff)>=.005): ?><button class="btn btn-sm btn-primary rr-post" type="button">Posting</button><?php endif; ?><?php else: ?><span class="small text-success"><?= $posted?'Sudah dimutasi':'' ?></span><?php endif; ?></div></div></div></div></form>
    <div class="rr-cell">
      <?php if(!empty($dashboard['transfer_ready'])): ?>
      <div class="rr-counter-label mb-3">
        <label class="form-label d-block">Rekening lawan transfer
          <select class="form-select rr-counter-account" <?= (!$canEdit||$posted)?'disabled':'' ?>>
            <option value="">Pilih rekening lawan</option>
            <?php foreach($accounts as $account): ?><option value="<?= (int)$account['id'] ?>" <?= (int)($row['counter_account_id']??0)===(int)$account['id']?'selected':'' ?>><?= html_escape((string)$account['account_name']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <p class="rr-note">Riil kurang: rekening yang disesuaikan → rekening lawan. Riil lebih: rekening lawan → rekening yang disesuaikan. Kedua mutasi disimpan bersama; tidak perlu mengisi atau memasangkan baris metode lain.</p>
        <details <?= !empty($row['counter_payment_method_id'])?'open':'' ?>>
          <summary class="small">Salah pencatatan metode pembayaran? Kaitkan sisi lawan (opsional)</summary>
          <label class="form-label d-block mt-2">Metode pendapatan lawan
            <select class="form-select rr-counter" <?= (!$canEdit||$posted)?'disabled':'' ?>>
              <option value="">Tidak dikaitkan ke metode lain</option>
              <?php foreach($rows as $counterRow): if((int)$counterRow['payment_method_id']!==(int)$row['payment_method_id']): ?>
              <option value="<?= (int)$counterRow['payment_method_id'] ?>" data-account="<?= (int)$counterRow['company_account_id'] ?>" <?= (int)($row['counter_payment_method_id']??0)===(int)$counterRow['payment_method_id']?'selected':'' ?>><?= html_escape($counterRow['method_name'].' · '.($counterRow['account_name']??'')) ?></option>
              <?php endif; endforeach; ?>
            </select>
          </label>
          <p class="rr-note">Hanya untuk koreksi pembagian penerimaan antar metode pada hari yang sama. Penyesuaian sisi lawan ikut diperhitungkan agar tidak diposting dua kali, tetapi baris metode tersebut tidak otomatis ditutup. Kosongkan untuk transfer rekening biasa.</p>
        </details>
      </div>
      <?php elseif($canEdit): ?><p class="rr-note">Transfer antar rekening belum aktif: administrator perlu menerapkan migrasi 2026-09-14c.</p><?php endif; ?>
      <label class="form-label"><?= $posted ? 'Kategori saat posting (riwayat)' : 'Kategori penyesuaian untuk laporan' ?></label>
      <?php if ($posted): ?><div class="small text-muted mb-1">Koreksi kategori laporan terkini melalui <a href="<?= site_url('finance/mutations') ?>">Mutasi Rekening</a>; kategori saat posting tetap sebagai riwayat.</div><?php endif; ?>
      <select class="form-select form-select-sm rr-category" <?= (!$canEdit || $posted) ? 'disabled' : '' ?>>
        <option value="">Pilih kategori sebelum posting...</option>
        <?php foreach ((array)($report_categories ?? []) as $code => $option): ?>
          <option value="<?= html_escape($code) ?>" data-direction="<?= html_escape($option['direction']) ?>" <?= (string)($row['report_category'] ?? '') === $code ? 'selected' : '' ?>><?= html_escape($option['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="rr-note mt-2">Penyesuaian yang sudah diposting untuk pendapatan/metode ini: <strong><?= $money($row['adjusted_total'] ?? 0) ?></strong>. Sesi berikutnya hanya memposting sisa selisih. Kategori IN/OUT sama dengan Mutasi Kas; transfer tidak menambah pendapatan atau biaya. Jangan posting ulang penyesuaian yang sudah dicatat di modul lain. Dana belum cair bukan otomatis biaya; promo/biaya platform tetap memerlukan bukti settlement.</div>
    </div>
    <div class="rr-settlement"><?php $this->load->view('finance/_settlement_select',['settlement_options'=>$settlement_options??[],'settlement_selected'=>$row['settlement_control_id']??0,'settlement_charge_selected'=>$row['settlement_charge_id']??0,'settlement_disabled'=>!$canEdit||$posted]); ?></div>
  </article><?php endforeach; ?>
  <div class="rr-history"><b>Riwayat sesi</b><div class="d-flex flex-wrap gap-3 mt-2"><?php foreach((array)($dashboard['recent']??[]) as $item): ?><a href="?<?= html_escape(http_build_query(['date'=>$item['reconciliation_date'],'revenue_date'=>$item['revenue_date'],'reconciliation_id'=>$item['id']])) ?>"><?= html_escape((string)$item['reconciliation_no']) ?> (<?= html_escape((string)$item['status']) ?>)</a><?php endforeach; ?></div></div>
  <?php endif; ?>
</div></div><div class="rr-toast" id="rr-toast"></div>
<script>
(function () {
  const saveUrl = <?= json_encode($save_url) ?>, postUrl = <?= json_encode($post_url) ?>, roundUrl = <?= json_encode($round_create_url) ?>;
  const toast = (message, error) => {
    const el = document.getElementById('rr-toast'); el.textContent = message;
    el.className = 'rr-toast ' + (error ? 'error' : ''); el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', 5000);
  };
  const send = (url, data) => fetch(url, {method: 'POST', headers: {
    'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
    'X-Finance-Reconciliation-CSRF': <?= json_encode($reconciliation_csrf ?? '') ?>
  }, body: JSON.stringify(data)}).then(async response => {
    const json = await response.json(); if (!response.ok || !json.ok) throw Error(json.message || 'Proses gagal'); return json;
  });
  const payload = button => {
    const form=button.closest('form'), card=button.closest('article');
    const direction=form.elements.resolution_type.value;
    const adjustment=['IN','OUT'].includes(direction), transfer=direction==='TRANSFER';
    return Object.assign(Object.fromEntries(new FormData(form)), {
      report_category: adjustment ? card.querySelector('.rr-category').value : '',
      counter_account_id: transfer ? Number(card.querySelector('.rr-counter-account')?.value || 0) : 0,
      counter_payment_method_id: transfer ? Number(card.querySelector('.rr-counter')?.value || 0) : 0,
      settlement_control_id: adjustment ? Number(card.querySelector('[data-role="settlement-control"]')?.value || 0) : 0,
      settlement_charge_id: adjustment ? Number(card.querySelector('[data-role="settlement-charge"]')?.value || 0) : 0
    });
  };
  document.querySelectorAll('.rr-save, .rr-post').forEach(button => button.onclick = async () => {
    const posting = button.classList.contains('rr-post');
    const data = payload(button);
    if (posting && data.resolution_type==='NONE') { toast('Biarkan terbuka hanya perlu Simpan. Pilih IN, OUT, atau transfer untuk posting.', true); return; }
    if (data.resolution_type==='TRANSFER' && (!data.counter_account_id || data.counter_account_id===Number(data.account_id))) { toast('Pilih rekening lawan yang berbeda.', true); return; }
    if (posting && data.resolution_type!=='TRANSFER' && !data.report_category) { toast('Pilih kategori laporan sebelum posting.', true); return; }
    if (posting && !confirm(data.resolution_type==='TRANSFER'?'Saya sudah memeriksa penerimaan riil dan kedua rekening. Posting transfer IN/OUT sesuai arah selisih? Saldo kedua rekening berubah bersamaan, tanpa menambah pendapatan/biaya.':'Saya sudah memeriksa selisih harian dan kategorinya. Penyesuaian ini belum dicatat melalui POS, Mutasi Kas, atau Rekonsiliasi Kas. Posting sisa selisih ke rekening?')) return;
    const buttons = [...button.closest('form').querySelectorAll('button')]; buttons.forEach(b => b.disabled = true);
    try {
      // Persist current form values before posting, not an older saved category/amount.
      const saved = await send(saveUrl, data);
      const result = posting ? await send(postUrl, {line_id: saved.line_id}) : saved;
      toast(result.message); location.reload();
    } catch (error) { toast(error.message, true); buttons.forEach(b => b.disabled = false); }
  });
  document.querySelectorAll('.rr-method').forEach(card => {
    const type=card.querySelector('[name="resolution_type"]');
    const category=card.querySelector('.rr-category');
    const counter=card.querySelector('.rr-counter-label');
    const counterAccount=card.querySelector('.rr-counter-account');
    const counterMethod=card.querySelector('.rr-counter');
    const account=card.querySelector('[name="account_id"]');
    const settlement=card.querySelector('.rr-settlement');
    const update=()=>{
      if(counter)counter.hidden=type.value!=='TRANSFER';
      if(settlement)settlement.hidden=!['IN','OUT'].includes(type.value);
      if(!type.disabled){
        if(counterAccount){
          counterAccount.disabled=type.value!=='TRANSFER';
          for(const option of counterAccount.options) option.disabled=!!option.value && option.value===account.value;
          if(counterAccount.selectedOptions[0]?.disabled)counterAccount.value='';
        }
        if(counterMethod){
          counterMethod.disabled=type.value!=='TRANSFER';
          for(const option of counterMethod.options) option.disabled=!!option.value && option.dataset.account!==counterAccount.value;
          if(counterMethod.selectedOptions[0]?.disabled)counterMethod.value='';
        }
        category.disabled=!['IN','OUT'].includes(type.value);
        for(const option of category.options) option.disabled=!!option.value && ['IN','OUT'].includes(type.value) && option.dataset.direction!=='BOTH' && option.dataset.direction!==type.value;
        if(category.selectedOptions[0]?.disabled)category.value='';
      }
    };
    type.addEventListener('change',update);
    account.addEventListener('change',update);
    if(counterAccount)counterAccount.addEventListener('change',update);
    update();
  });
  const newRound = document.getElementById('rr-new');
  if (newRound) newRound.onclick = async () => {
    newRound.disabled = true;
    try {
      const result = await send(roundUrl, {reconciliation_date: document.querySelector('[name=date]').value, revenue_date: document.querySelector('[name=revenue_date]').value});
      location.href = result.redirect_url;
    } catch (error) { toast(error.message, true); newRound.disabled = false; }
  };
})();
</script>
