<?php
$dashboard = (array)($dashboard ?? []);
$isReady = !empty($dashboard['ok']);
$rows = (array)($dashboard['accounts'] ?? []);
$summary = (array)($dashboard['summary'] ?? []);
$recentRows = (array)($dashboard['recent'] ?? []);
$header = (array)($dashboard['header'] ?? []);
$reconciliationDate = (string)($dashboard['reconciliation_date'] ?? $reconciliation_date ?? date('Y-m-d'));
$canEdit = !empty($can_reconcile_edit);
$roundCreateUrl = (string)($round_create_url ?? '');
$saveUrl = (string)($save_url ?? '');
$postUrl = (string)($post_url ?? '');
$mutationUrl = (string)($mutation_url ?? '');
$currentHeaderId = (int)($header['id'] ?? 0);
$currentRoundNo = (int)($header['round_no'] ?? 0);
$currentHeaderStatus = strtoupper((string)($header['status'] ?? ''));
$isTodayReconciliation = $reconciliationDate === date('Y-m-d');
$systemSummaryLabel = $isTodayReconciliation ? 'Saldo Sistem Live' : 'Saldo Sistem per Tanggal';
$systemSummaryNote = $isTodayReconciliation
    ? 'Akumulasi saldo sistem aktif saat halaman ini dimuat.'
    : 'Posisi sistem efektif pada tanggal yang dipilih.';
$systemCellNote = $isTodayReconciliation
    ? 'Saldo sistem live saat halaman dimuat; dibaca ulang setiap simpan atau posting.'
    : 'Posisi sistem efektif per tanggal yang dipilih.';

$formatMoney = static function ($value): string {
    return 'Rp ' . number_format((float)$value, 2, ',', '.');
};
$formatInputMoney = static function ($value): string {
    return number_format((float)$value, 2, ',', '.');
};
$typeMeta = static function (string $type): array {
    switch (strtoupper($type)) {
        case 'CASH':
            return ['label' => 'Kas', 'icon' => 'ri-money-dollar-circle-line', 'class' => 'cash'];
        case 'BANK':
            return ['label' => 'Bank', 'icon' => 'ri-bank-line', 'class' => 'bank'];
        case 'EWALLET':
            return ['label' => 'E-Wallet', 'icon' => 'ri-wallet-3-line', 'class' => 'wallet'];
        default:
            return ['label' => 'Rekening', 'icon' => 'ri-safe-line', 'class' => 'other'];
    }
};
$statusMeta = static function (string $status): array {
    switch (strtoupper($status)) {
        case 'MATCHED': return ['label' => 'Cocok', 'class' => 'matched', 'icon' => 'ri-checkbox-circle-line'];
        case 'OPEN': return ['label' => 'Selisih terbuka', 'class' => 'open', 'icon' => 'ri-error-warning-line'];
        case 'POSTED': return ['label' => 'Disesuaikan', 'class' => 'posted', 'icon' => 'ri-shield-check-line'];
        default: return ['label' => 'Belum dihitung', 'class' => 'unchecked', 'icon' => 'ri-time-line'];
    }
};
$roundStatusMeta = static function (string $status): array {
    switch (strtoupper($status)) {
        case 'COMPLETED': return ['label' => 'Selesai', 'class' => 'COMPLETED'];
        case 'REVIEWED': return ['label' => 'Ditinjau', 'class' => 'REVIEWED'];
        default: return ['label' => 'Rekon Berjalan', 'class' => 'OPEN'];
    }
};
$currentRoundStatus = $roundStatusMeta($currentHeaderStatus);
$accountOptions = $rows;
?>

<style>
  .cash-recon-page { --cr-ink:#20263b; --cr-muted:#7b8294; --cr-line:#eadfd9; --cr-paper:#fffdfb; --cr-wine:#a8232b; --cr-wine-deep:#7f1d25; --cr-green:#0c7a51; --cr-blue:#2563b8; --cr-amber:#a96500; }
  .cash-recon-page .finance-tabs { margin-bottom:1rem; }
  .recon-topbar { display:flex; align-items:flex-end; justify-content:space-between; gap:1rem; border-bottom:1px solid var(--cr-line); padding:0 0 1rem; margin-bottom:1rem; }
  .recon-eyebrow { color:var(--cr-wine); font-size:.68rem; font-weight:800; letter-spacing:.12em; text-transform:uppercase; margin-bottom:.25rem; }
  .recon-title { color:var(--cr-ink); font-size:1.45rem; font-weight:800; letter-spacing:-.035em; margin:0; }
  .recon-subtitle { color:var(--cr-muted); font-size:.83rem; margin:.28rem 0 0; max-width:650px; }
  .recon-date-form { display:flex; gap:.5rem; align-items:end; padding:.55rem; background:#fff; border:1px solid var(--cr-line); border-radius:14px; min-width:295px; }
  .recon-date-form label { display:block; color:#635e68; font-size:.65rem; font-weight:800; letter-spacing:.07em; margin:0 0 .22rem; text-transform:uppercase; }
  .recon-date-form .form-control { min-width:145px; border-color:#e6d9d3; font-size:.83rem; }
  .recon-document { display:inline-flex; gap:.38rem; align-items:center; color:#5d5662; background:#f7f4f1; border:1px solid #ebe3df; border-radius:999px; padding:.32rem .62rem; font-size:.72rem; font-weight:700; }
  .recon-document i { color:var(--cr-wine); }
  .recon-round-status { display:inline-flex; align-items:center; gap:.28rem; border-radius:999px; padding:.31rem .57rem; font-size:.67rem; font-weight:800; }.recon-round-status.OPEN { color:#a56100; background:#fff2df; }.recon-round-status.REVIEWED { color:#4c5f9d; background:#eef2ff; }.recon-round-status.COMPLETED { color:#087452; background:#e8f6ed; }
  .recon-round-action { display:inline-flex; align-items:center; justify-content:center; gap:.35rem; font-size:.72rem; font-weight:750; white-space:nowrap; }.recon-round-action small { font-size:.62rem; font-weight:600; opacity:.82; }

  .recon-summary { display:grid; grid-template-columns:1.25fr 1.25fr 1fr 1fr 1fr; gap:.72rem; margin-bottom:1rem; }
  .recon-stat { background:var(--cr-paper); border:1px solid var(--cr-line); border-radius:15px; padding:.8rem .9rem; min-height:103px; position:relative; overflow:hidden; }
  .recon-stat::before { content:''; display:block; width:34px; height:3px; background:#b4aaa6; border-radius:5px; margin-bottom:.55rem; }
  .recon-stat.system::before { background:var(--cr-blue); }.recon-stat.actual::before { background:var(--cr-green); }.recon-stat.delta::before { background:var(--cr-wine); }.recon-stat.open::before { background:#c58115; }.recon-stat.progress::before { background:#7a5aa5; }
  .recon-stat-label { color:#827974; font-size:.66rem; font-weight:800; letter-spacing:.075em; text-transform:uppercase; }
  .recon-stat-value { color:var(--cr-ink); font-size:1.1rem; font-weight:800; letter-spacing:-.025em; margin-top:.3rem; white-space:nowrap; }
  .recon-stat-value.negative { color:var(--cr-wine); }.recon-stat-value.positive { color:var(--cr-green); }
  .recon-stat-note { color:#918782; font-size:.69rem; line-height:1.3; margin-top:.22rem; }

  .recon-guidance { display:flex; align-items:flex-start; gap:.8rem; background:linear-gradient(90deg,#fff8f4,#fffdfb); border:1px solid #efdfd5; border-left:4px solid var(--cr-wine); border-radius:13px; padding:.75rem .9rem; margin-bottom:1rem; }
  .recon-guidance > i { color:var(--cr-wine); font-size:1.15rem; margin-top:.08rem; }
  .recon-guidance strong { display:block; color:#493a3d; font-size:.79rem; margin-bottom:.1rem; }
  .recon-guidance span { color:#786d6a; font-size:.73rem; line-height:1.4; }

  .recon-section-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin:1.15rem 0 .65rem; }
  .recon-section-head h5 { color:var(--cr-ink); font-size:.95rem; font-weight:800; margin:0; }
  .recon-section-head p { color:var(--cr-muted); font-size:.72rem; margin:.16rem 0 0; }
  .recon-count { border-radius:999px; background:#f0ebe8; color:#655b5d; padding:.25rem .55rem; font-size:.68rem; font-weight:800; white-space:nowrap; }

  .recon-account-stack { display:grid; gap:.7rem; }
  .recon-account { background:#fff; border:1px solid var(--cr-line); border-radius:17px; overflow:hidden; box-shadow:0 5px 18px rgba(70,36,30,.025); }
  .recon-account.is-posted { border-color:#c8dfd4; }.recon-account.is-open { border-color:#ecd7bc; }.recon-account.is-matched { border-color:#d5e7dd; }
  .recon-account-header { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.72rem .9rem; background:#fffdfc; border-bottom:1px solid #f0e7e2; }
  .recon-account-id { display:flex; align-items:center; min-width:0; gap:.65rem; }
  .recon-account-icon { width:33px; height:33px; display:grid; place-items:center; flex:none; border-radius:10px; background:#f1efed; color:#63575a; font-size:1rem; }
  .recon-account-icon.cash { background:#e7f6f0; color:#087452; }.recon-account-icon.bank { background:#ebf1ff; color:#1c5aad; }.recon-account-icon.wallet { background:#f1ebff; color:#7945c8; }
  .recon-account-name { color:#282b38; font-weight:800; font-size:.86rem; line-height:1.2; }.recon-account-meta { color:#918681; font-size:.68rem; margin-top:.15rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .recon-account-code { color:#8c2a31; font-size:.65rem; letter-spacing:.07em; font-weight:800; margin-left:.3rem; }
  .recon-status { display:inline-flex; align-items:center; gap:.28rem; padding:.29rem .53rem; border-radius:999px; font-weight:800; font-size:.65rem; white-space:nowrap; }
  .recon-status.unchecked { background:#eef0f4; color:#657080; }.recon-status.matched { background:#e8f6ed; color:#087452; }.recon-status.open { background:#fff2df; color:#a56100; }.recon-status.posted { background:#e6f2ef; color:#087452; }
  .recon-account-grid { display:grid; grid-template-columns:1.05fr 1.15fr 1fr 1.7fr; gap:0; }
  .recon-cell { min-width:0; padding:.86rem .9rem; border-right:1px solid #f0e7e2; }.recon-cell:last-child { border-right:0; }
  .recon-cell-label { color:#8c827d; font-size:.62rem; font-weight:800; letter-spacing:.085em; text-transform:uppercase; margin-bottom:.38rem; }.recon-cell-value { color:#30303c; font-weight:800; font-size:.96rem; letter-spacing:-.015em; }.recon-cell-small { color:#918681; font-size:.67rem; line-height:1.35; margin-top:.27rem; }
  .recon-actual-wrap { flex-wrap:nowrap; }.recon-money-prefix { color:#7f7470; background:#fbf7f5; border-color:#ded7d3; border-right:0; font-size:.74rem; font-weight:800; padding-left:.62rem; padding-right:.48rem; }.recon-money-input { border-color:#ded7d3; border-left:0; font-size:.88rem; font-weight:750; padding-left:.42rem; }.recon-actual-wrap:focus-within .recon-money-prefix,.recon-actual-wrap:focus-within .recon-money-input { border-color:#b6383f; }.recon-money-input:focus { box-shadow:0 0 0 .16rem rgba(168,35,43,.12); }
  .recon-save-state { min-height:16px; color:#89817d; font-size:.63rem; margin-top:.28rem; }.recon-save-state.saving { color:#93611c; }.recon-save-state.saved { color:#087452; }.recon-save-state.error { color:#b4232a; }
  .recon-diff { border-radius:10px; padding:.52rem .58rem; background:#f7f5f3; }.recon-diff.neutral { background:#f1f8f3; }.recon-diff.positive { background:#eef7f2; }.recon-diff.negative { background:#fff0ef; }.recon-diff.empty { background:#f4f5f7; }
  .recon-diff .amount { color:#45404a; font-size:.94rem; font-weight:800; }.recon-diff.positive .amount { color:#087452; }.recon-diff.negative .amount { color:#ad2931; }.recon-diff .meaning { display:block; color:#827772; font-size:.63rem; line-height:1.25; margin-top:.16rem; }
  .recon-action-layout { display:grid; grid-template-columns:minmax(138px,.8fr) minmax(155px,1fr) auto; gap:.42rem; align-items:end; }
  .recon-action-layout .form-label { color:#817773; font-size:.61rem; font-weight:800; letter-spacing:.06em; margin:0 0 .23rem; text-transform:uppercase; }.recon-action-layout .form-select,.recon-action-layout .form-control { border-color:#e0d6d1; font-size:.74rem; min-width:0; }
  .recon-note-wrap { grid-column:1 / span 2; }.recon-counter-wrap { display:none; }.recon-counter-wrap.show { display:block; }
  .recon-action-buttons { display:flex; gap:.35rem; align-items:end; }.recon-action-buttons .btn { font-size:.7rem; white-space:nowrap; padding:.43rem .55rem; }.recon-action-buttons .btn-post { background:var(--cr-wine); border-color:var(--cr-wine); }.recon-action-buttons .btn-post:hover { background:var(--cr-wine-deep); border-color:var(--cr-wine-deep); }
  .recon-action-hint { color:#89807c; font-size:.62rem; line-height:1.3; margin-top:.36rem; grid-column:1 / -1; }.recon-action-hint strong { color:#695b5a; }
  .recon-posted-note { display:flex; align-items:center; gap:.35rem; color:#0a7953; font-size:.67rem; font-weight:750; margin-top:.4rem; }.recon-posted-note a { color:#0c6d9d; text-decoration:none; }
  .recon-locked { opacity:.78; }.recon-locked .recon-money-input,.recon-locked .form-select,.recon-locked .form-control { background:#f7f6f5; }
  .recon-empty { border:1px dashed #d8cec9; padding:2.4rem 1rem; text-align:center; border-radius:14px; color:#877c78; background:#fffdfb; }.recon-empty i { display:block; color:#b3a7a1; font-size:1.6rem; margin-bottom:.45rem; }

  .recon-history { border:1px solid var(--cr-line); border-radius:15px; overflow:hidden; margin-top:1.25rem; background:#fff; }.recon-history-head { padding:.72rem .9rem; background:#fffdfc; border-bottom:1px solid #f0e7e2; }.recon-history-head h6 { margin:0; color:var(--cr-ink); font-size:.82rem; font-weight:800; }.recon-history-head span { color:#8d837e; font-size:.67rem; }.recon-history .table { margin:0; font-size:.72rem; }.recon-history .table th { background:#faf6f3; color:#7b706b; font-size:.61rem; letter-spacing:.055em; text-transform:uppercase; border-bottom-color:#ede3de; }.recon-history .table td { color:#625a59; vertical-align:middle; border-color:#f1e8e3; }.recon-history-row-current td { background:#fffaf7; }.recon-history-link { color:#8c2a31; font-weight:800; text-decoration:none; }.recon-history-link:hover { color:#64171e; text-decoration:underline; }.recon-mini-status { display:inline-block; border-radius:99px; padding:.2rem .43rem; font-size:.61rem; font-weight:800; }.recon-mini-status.OPEN { color:#a56100; background:#fff2df; }.recon-mini-status.REVIEWED { color:#4c5f9d; background:#eef2ff; }.recon-mini-status.COMPLETED { color:#087452; background:#e8f6ed; }
  .recon-toast { position:fixed; right:1.1rem; bottom:1.1rem; z-index:1085; max-width:390px; display:none; padding:.75rem .85rem; border-radius:11px; color:#fff; background:#313442; box-shadow:0 12px 28px rgba(25,26,35,.22); font-size:.77rem; }.recon-toast.show { display:block; animation:recon-toast-in .18s ease-out; }.recon-toast.error { background:#b4232a; }.recon-toast.success { background:#087452; }@keyframes recon-toast-in { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
  .recon-modal-delta { padding:.68rem .75rem; border-radius:10px; background:#f8f4f1; color:#4c4240; font-size:.82rem; }.recon-modal-delta strong { color:var(--cr-wine); }

  @media (max-width: 1399px) { .recon-account-grid { grid-template-columns:1fr 1.15fr 1fr 1.8fr; }.recon-action-layout { grid-template-columns:1fr 1fr; }.recon-action-buttons { grid-column:1 / -1; }.recon-note-wrap { grid-column:1 / -1; } }
  @media (max-width: 991px) { .recon-topbar { align-items:flex-start; flex-direction:column; }.recon-date-form { width:100%; max-width:430px; }.recon-summary { grid-template-columns:repeat(3,1fr); }.recon-account-grid { grid-template-columns:repeat(2,1fr); }.recon-cell:nth-child(2) { border-right:0; }.recon-cell:nth-child(-n+2) { border-bottom:1px solid #f0e7e2; } }
  @media (max-width: 575px) { .cash-recon-page .container-xl { padding-left:.75rem; padding-right:.75rem; }.recon-summary { grid-template-columns:repeat(2,1fr); gap:.5rem; }.recon-stat { padding:.68rem; min-height:92px; }.recon-stat-value { font-size:.91rem; }.recon-account-header { align-items:flex-start; }.recon-account-grid { display:block; }.recon-cell { border-right:0; border-bottom:1px solid #f0e7e2; }.recon-cell:last-child { border-bottom:0; }.recon-action-layout { grid-template-columns:1fr; }.recon-counter-wrap,.recon-note-wrap,.recon-action-buttons { grid-column:auto; }.recon-date-form { min-width:0; }.recon-date-form > div { flex:1; }.recon-history .table { min-width:550px; } }
</style>

<div class="page-wrapper cash-recon-page">
  <div class="container-xl py-3">
    <div class="finance-tabs"><?php $this->load->view('finance/_tabs', ['finance_tab_active' => 'cash-reconciliation']); ?></div>

    <div class="recon-topbar">
      <div>
        <div class="recon-eyebrow">Finance Control</div>
        <h4 class="recon-title"><i class="ri-scales-3-line me-1 text-danger"></i>Rekonsiliasi Kas</h4>
        <p class="recon-subtitle">Cocokkan saldo rekening sistem dengan saldo riil. Simpan hasil hitung lebih dulu, lalu putuskan apakah selisih perlu dibiarkan, dijurnal masuk/keluar, atau dipindahkan antar rekening.</p>
      </div>
      <form method="get" class="recon-date-form">
        <div>
          <label for="recon-date">Tanggal Rekonsiliasi</label>
          <input id="recon-date" type="date" name="date" class="form-control form-control-sm" value="<?= html_escape($reconciliationDate) ?>">
        </div>
        <button class="btn btn-sm btn-primary px-3" type="submit"><i class="ri-refresh-line me-1"></i>Tampilkan</button>
      </form>
    </div>

    <?php if (!$isReady): ?>
      <div class="alert alert-danger border-0 shadow-sm">
        <div class="d-flex gap-2 align-items-start"><i class="ri-alert-line fs-5"></i><div><strong>Modul belum siap.</strong><br><?= html_escape((string)($dashboard['message'] ?? 'Migration rekonsiliasi kas belum dijalankan.')) ?></div></div>
      </div>
    <?php else: ?>
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <span class="recon-document"><i class="ri-file-list-3-line"></i><span data-role="document-no"><?= !empty($header['reconciliation_no']) ? html_escape((string)$header['reconciliation_no']) : 'Belum ada sesi pengecekan' ?></span></span>
          <span class="recon-round-status <?= html_escape($currentRoundStatus['class']) ?>" data-role="round-status"><i class="ri-pulse-line"></i><span><?= html_escape($currentRoundStatus['label']) ?></span></span>
          <span class="small text-muted" data-role="round-label"><?= $currentRoundNo > 0 ? 'Sesi cek ' . $currentRoundNo . (!empty($header['reconciled_at']) ? ' · mulai ' . html_escape(date('d/m/Y H:i', strtotime((string)$header['reconciled_at']))) : '') : 'Belum ada sesi cek; simpan hasil cek atau mulai cek baru.' ?></span>
        </div>
        <?php if ($canEdit): ?>
          <button type="button" class="btn btn-outline-primary recon-round-action" id="recon-new-round"><i class="ri-add-circle-line"></i><span>Mulai Cek Baru</span><small>cek saldo live</small></button>
        <?php endif; ?>
      </div>

      <div class="small text-muted mb-3"><i class="ri-history-line me-1"></i>Sesi pengecekan bukan tutup kas. Saldo sistem tetap bergerak mengikuti transaksi; hanya baris yang sudah membuat mutasi penyesuaian yang dikunci agar tidak diposting dua kali.</div>

      <section class="recon-summary" aria-label="Ringkasan rekonsiliasi">
        <div class="recon-stat system"><div class="recon-stat-label"><?= html_escape($systemSummaryLabel) ?></div><div class="recon-stat-value" data-summary="system_total"><?= $formatMoney($summary['system_total'] ?? 0) ?></div><div class="recon-stat-note"><?= html_escape($systemSummaryNote) ?></div></div>
        <div class="recon-stat actual"><div class="recon-stat-label">Saldo Riil Terhitung</div><div class="recon-stat-value" data-summary="actual_total"><?= $formatMoney($summary['actual_total'] ?? 0) ?></div><div class="recon-stat-note"><span data-summary="counted_accounts"><?= (int)($summary['counted_accounts'] ?? 0) ?></span> dari <?= count($rows) ?> rekening telah diinput.</div></div>
        <?php $netDelta = (float)($summary['difference_total'] ?? 0); ?>
        <div class="recon-stat delta"><div class="recon-stat-label">Selisih Bersih</div><div class="recon-stat-value <?= $netDelta < 0 ? 'negative' : ($netDelta > 0 ? 'positive' : '') ?>" data-summary="difference_total"><?= $formatMoney($netDelta) ?></div><div class="recon-stat-note">Saldo riil terakhir dikurangi saldo sistem yang sedang terbaca.</div></div>
        <div class="recon-stat open"><div class="recon-stat-label">Butuh Keputusan</div><div class="recon-stat-value" data-summary="open_accounts"><?= (int)($summary['open_accounts'] ?? 0) ?> rekening</div><div class="recon-stat-note">Masuk <?= $formatMoney($summary['incoming_adjustment_total'] ?? 0) ?> · keluar <?= $formatMoney($summary['outgoing_adjustment_total'] ?? 0) ?>.</div></div>
        <div class="recon-stat progress"><div class="recon-stat-label">Status Hitung</div><div class="recon-stat-value"><span data-summary="matched_accounts"><?= (int)($summary['matched_accounts'] ?? 0) ?></span> cocok</div><div class="recon-stat-note"><span data-summary="posted_accounts"><?= (int)($summary['posted_accounts'] ?? 0) ?></span> penyesuaian telah diposting.</div></div>
      </section>

      <div class="recon-guidance">
        <i class="ri-shield-check-line"></i>
        <div><strong>Kontrol sebelum saldo berubah</strong><span>Input saldo riil menyimpan hasil cek terakhir. Saldo sistem dibaca ulang saat <b>Simpan</b> dan tepat sebelum <b>Posting</b>, sehingga transaksi harian tidak pernah terkunci. Tombol Posting tetap menjadi satu-satunya tindakan yang mengubah saldo rekening dan membuat mutasi audit.</span></div>
      </div>

      <div class="recon-section-head">
        <div><h5>Rekening Aktif</h5><p>Kerjakan satu rekening hingga selesai. Selisih positif berarti riil lebih besar dari sistem; selisih negatif berarti riil lebih kecil.</p></div>
        <span class="recon-count"><?= count($rows) ?> rekening</span>
      </div>

      <div class="recon-account-stack" id="recon-account-stack">
        <?php if (empty($rows)): ?>
          <div class="recon-empty"><i class="ri-bank-line"></i>Belum ada rekening perusahaan aktif untuk direkonsiliasi.</div>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <?php
          $type = $typeMeta((string)($row['account_type'] ?? 'OTHER'));
          $status = $statusMeta((string)($row['status'] ?? 'UNCHECKED'));
          $actual = $row['actual_balance'] ?? null;
          $difference = $row['difference_amount'] ?? null;
          $differenceClass = $actual === null ? 'empty' : (abs((float)$difference) < .005 ? 'neutral' : ((float)$difference > 0 ? 'positive' : 'negative'));
          $differenceLabel = $actual === null ? 'Masukkan saldo riil' : (abs((float)$difference) < .005 ? 'Saldo sudah cocok' : ((float)$difference > 0 ? 'Riil lebih besar dari sistem' : 'Riil lebih kecil dari sistem'));
          $isPosted = strtoupper((string)($row['status'] ?? '')) === 'POSTED';
          $isTransfer = strtoupper((string)($row['resolution_type'] ?? 'NONE')) === 'TRANSFER';
          $lineId = (int)($row['id'] ?? 0);
          $canPostThisLine = $canEdit && $isTodayReconciliation && !$isPosted;
          $initialActionHint = $actual === null
              ? 'Simpan saldo riil terlebih dahulu. Anda boleh membiarkan selisih tetap terbuka tanpa membuat mutasi.'
              : ($isPosted
                  ? 'Penyesuaian sudah direkam. Gunakan riwayat mutasi untuk menelusuri dampaknya.'
                  : (!$isTodayReconciliation
                      ? 'Pengecekan tanggal lampau hanya untuk telaah. Mulai cek hari ini sebelum memposting penyesuaian.'
                      : 'Pilih tindakan bila selisih telah terverifikasi. Transfer tidak mengubah total dana perusahaan.'));
          $mutationQuery = http_build_query(['account_id' => (int)$row['account_id'], 'date_from' => $reconciliationDate, 'date_to' => $reconciliationDate]);
          ?>
          <article class="recon-account is-<?= html_escape($status['class']) ?> <?= $isPosted ? 'recon-locked' : '' ?>" data-account-id="<?= (int)$row['account_id'] ?>" data-line-id="<?= $lineId ?>" data-status="<?= html_escape((string)($row['status'] ?? 'UNCHECKED')) ?>" data-difference="<?= $difference === null ? '' : html_escape((string)$difference) ?>">
            <div class="recon-account-header">
              <div class="recon-account-id">
                <span class="recon-account-icon <?= html_escape($type['class']) ?>"><i class="<?= html_escape($type['icon']) ?>"></i></span>
                <div class="min-width-0">
                  <div class="recon-account-name"><?= html_escape((string)$row['account_name']) ?><span class="recon-account-code"><?= html_escape((string)$row['account_code']) ?></span></div>
                  <div class="recon-account-meta"><?= html_escape((string)$type['label']) ?><?= !empty($row['bank_name']) ? ' · ' . html_escape((string)$row['bank_name']) : '' ?><?= !empty($row['account_no']) ? ' · ' . html_escape((string)$row['account_no']) : '' ?></div>
                </div>
              </div>
              <span class="recon-status <?= html_escape($status['class']) ?>" data-role="status"><i class="<?= html_escape($status['icon']) ?>"></i><span><?= html_escape($status['label']) ?></span></span>
            </div>
            <form class="recon-line-form" novalidate>
              <input type="hidden" name="reconciliation_date" value="<?= html_escape($reconciliationDate) ?>">
              <input type="hidden" name="reconciliation_id" value="<?= $currentHeaderId ?>">
              <input type="hidden" name="account_id" value="<?= (int)$row['account_id'] ?>">
              <div class="recon-account-grid">
                <div class="recon-cell">
                  <div class="recon-cell-label">Saldo Sistem</div>
                  <div class="recon-cell-value" data-role="system-balance"><?= $formatMoney($row['system_balance'] ?? 0) ?></div>
                  <div class="recon-cell-small"><?= html_escape($systemCellNote) ?></div>
                </div>
                <div class="recon-cell">
                  <div class="recon-cell-label">Saldo Riil di Rekening</div>
                  <div class="input-group input-group-sm recon-actual-wrap"><span class="input-group-text recon-money-prefix">Rp</span><input class="form-control recon-money-input" data-role="actual-balance" inputmode="decimal" autocomplete="off" placeholder="0,00" value="<?= $actual === null ? '' : html_escape($formatInputMoney($actual)) ?>" <?= (!$canEdit || $isPosted) ? 'disabled' : '' ?>></div>
                  <div class="recon-save-state <?= $lineId > 0 ? 'saved' : '' ?>" data-role="save-state"><?= $isPosted ? 'Terkunci setelah penyesuaian diposting.' : ($lineId > 0 ? 'Tersimpan.' : 'Masukkan lalu simpan hasil cek fisik.') ?></div>
                </div>
                <div class="recon-cell">
                  <div class="recon-cell-label">Selisih Riil − Sistem</div>
                  <div class="recon-diff <?= $differenceClass ?>" data-role="difference-box"><div class="amount" data-role="difference-amount"><?= $actual === null ? '—' : $formatMoney($difference) ?></div><span class="meaning" data-role="difference-label"><?= html_escape($differenceLabel) ?></span></div>
                  <div class="recon-cell-small" data-role="difference-note"><?= $isPosted ? 'Selisih sudah diposting sebagai mutasi audit.' : 'Tidak ada saldo yang berubah sampai tindakan diposting.' ?></div>
                </div>
                <div class="recon-cell">
                  <div class="recon-cell-label">Tindak Lanjut Selisih</div>
                  <div class="recon-action-layout">
                    <div>
                      <label class="form-label">Keputusan</label>
                      <select class="form-select form-select-sm" data-role="resolution-type" <?= (!$canEdit || $isPosted) ? 'disabled' : '' ?>>
                        <option value="NONE" <?= strtoupper((string)($row['resolution_type'] ?? 'NONE')) === 'NONE' ? 'selected' : '' ?>>Biarkan terbuka</option>
                        <option value="IN" <?= strtoupper((string)($row['resolution_type'] ?? '')) === 'IN' ? 'selected' : '' ?>>Mutasi masuk</option>
                        <option value="OUT" <?= strtoupper((string)($row['resolution_type'] ?? '')) === 'OUT' ? 'selected' : '' ?>>Mutasi keluar</option>
                        <option value="TRANSFER" <?= $isTransfer ? 'selected' : '' ?>>Transfer antar rekening</option>
                      </select>
                    </div>
                    <div class="recon-counter-wrap <?= $isTransfer ? 'show' : '' ?>" data-role="counter-wrap">
                      <label class="form-label">Rekening Lawan</label>
                      <select class="form-select form-select-sm" data-role="counter-account" <?= (!$canEdit || $isPosted) ? 'disabled' : '' ?>>
                        <option value="">Pilih rekening</option>
                        <?php foreach ($accountOptions as $option): ?>
                          <?php if ((int)$option['account_id'] === (int)$row['account_id']) continue; ?>
                          <option value="<?= (int)$option['account_id'] ?>" <?= (int)($row['counter_account_id'] ?? 0) === (int)$option['account_id'] ? 'selected' : '' ?>><?= html_escape((string)$option['account_code'] . ' - ' . (string)$option['account_name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="recon-action-buttons">
                      <?php if ($canEdit && !$isPosted): ?>
                        <button type="button" class="btn btn-outline-primary btn-save" data-role="save"><i class="ri-save-3-line"></i><span class="d-none d-xl-inline ms-1">Simpan</span></button>
                        <button type="button" class="btn btn-primary btn-post" data-role="post" <?= (!$canPostThisLine || $lineId <= 0 || $actual === null || abs((float)$difference) < .005 || strtoupper((string)($row['resolution_type'] ?? 'NONE')) === 'NONE') ? 'disabled' : '' ?>><i class="ri-checkbox-circle-line"></i><span class="d-none d-xl-inline ms-1">Posting</span></button>
                      <?php elseif ($isPosted): ?>
                        <a class="btn btn-outline-success" href="<?= html_escape($mutationUrl . '?' . $mutationQuery) ?>"><i class="ri-external-link-line"></i><span class="d-none d-xl-inline ms-1">Mutasi</span></a>
                      <?php else: ?>
                        <span class="small text-muted">Hanya lihat</span>
                      <?php endif; ?>
                    </div>
                    <div class="recon-note-wrap">
                      <label class="form-label"><?= $isPosted ? 'Kategori saat posting (riwayat)' : 'Kategori laporan (untuk mutasi masuk/keluar)' ?></label>
                      <?php if ($isPosted): ?><div class="small text-muted mb-1">Koreksi kategori laporan terkini melalui <a href="<?= site_url('finance/mutations') ?>">Mutasi Rekening</a>; kategori saat posting tetap sebagai riwayat.</div><?php endif; ?>
                      <select class="form-select form-select-sm" data-role="report-category" <?= (!$canEdit || $isPosted) ? 'disabled' : '' ?>>
                        <option value="">Pilih kategori sebelum posting...</option>
                        <?php foreach ((array)($report_categories ?? []) as $code => $option): ?>
                          <option value="<?= html_escape($code) ?>" data-direction="<?= html_escape($option['direction']) ?>" <?= (string)($row['report_category'] ?? '') === $code ? 'selected' : '' ?>><?= html_escape($option['label']) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <small class="text-muted">Modal/prive/koreksi saldo saja tidak menjadi hasil operasional. Jangan catat biaya yang sudah dipotong di POS atau rekonsiliasi pendapatan.</small>
                      <?php $this->load->view('finance/_settlement_select',['settlement_options'=>$settlement_options??[],'settlement_selected'=>$row['settlement_control_id']??0,'settlement_charge_selected'=>$row['settlement_charge_id']??0,'settlement_disabled'=>!$canEdit||$isPosted]); ?>
                    </div>
                    <div class="recon-note-wrap">
                      <label class="form-label">Catatan / Alasan</label>
                      <input class="form-control form-control-sm" data-role="resolution-note" maxlength="255" placeholder="Opsional, tetapi dianjurkan untuk selisih." value="<?= html_escape((string)($row['resolution_note'] ?? '')) ?>" <?= (!$canEdit || $isPosted) ? 'disabled' : '' ?>>
                    </div>
                    <div class="recon-action-hint" data-role="action-hint"><?= html_escape($initialActionHint) ?></div>
                  </div>
                  <?php if ($isPosted): ?>
                    <div class="recon-posted-note" data-role="posted-note"><i class="ri-shield-check-line"></i>Diposting <?= html_escape((string)($row['resolved_at'] ?? '')) ?><?= !empty($row['mutation_no']) ? ' · ' . html_escape((string)$row['mutation_no']) : '' ?></div>
                  <?php else: ?>
                    <div class="recon-posted-note d-none" data-role="posted-note"></div>
                  <?php endif; ?>
                </div>
              </div>
            </form>
          </article>
        <?php endforeach; ?>
      </div>

      <section class="recon-history">
        <div class="recon-history-head d-flex align-items-center justify-content-between gap-2"><div><h6>Jejak Sesi Pengecekan</h6><span>Setiap sesi menyimpan hasil cek saldo riil. Saldo sistem tetap live dan tidak dikunci oleh sesi sebelumnya.</span></div><i class="ri-history-line text-muted"></i></div>
        <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th class="ps-3">Tanggal</th><th>Sesi</th><th>Dokumen / Mulai</th><th class="text-center">Diperiksa</th><th class="text-center">Terbuka</th><th class="text-center">Diposting</th><th class="text-center pe-3">Status</th></tr></thead><tbody>
          <?php if (empty($recentRows)): ?><tr><td colspan="7" class="text-center text-muted py-3">Belum ada rekonsiliasi tersimpan.</td></tr><?php endif; ?>
          <?php foreach ($recentRows as $recent): ?>
            <?php
            $recentRoundStatus = $roundStatusMeta((string)($recent['status'] ?? 'OPEN'));
            $recentUrl = site_url('finance-reports/cash-reconciliation') . '?' . http_build_query([
                'date' => (string)($recent['reconciliation_date'] ?? ''),
                'reconciliation_id' => (int)($recent['id'] ?? 0),
            ]);
            $isCurrentRound = (int)($recent['id'] ?? 0) === $currentHeaderId;
            ?>
            <tr class="<?= $isCurrentRound ? 'recon-history-row-current' : '' ?>">
              <td class="ps-3 fw-semibold"><?= html_escape(date('d/m/Y', strtotime((string)$recent['reconciliation_date']))) ?></td>
              <td><a class="recon-history-link" href="<?= html_escape($recentUrl) ?>">Sesi <?= max(1, (int)($recent['round_no'] ?? 1)) ?></a></td>
              <td><a class="recon-history-link" href="<?= html_escape($recentUrl) ?>"><?= html_escape((string)$recent['reconciliation_no']) ?></a><?php if (!empty($recent['reconciled_at'])): ?><div class="small text-muted mt-1"><i class="ri-time-line me-1"></i><?= html_escape(date('d/m/Y H:i', strtotime((string)$recent['reconciled_at']))) ?></div><?php endif; ?></td>
              <td class="text-center"><?= (int)($recent['counted_count'] ?? 0) ?> rekening</td>
              <td class="text-center"><?= (int)($recent['open_count'] ?? 0) ?></td>
              <td class="text-center"><?= (int)($recent['posted_count'] ?? 0) ?></td>
              <td class="text-center pe-3"><span class="recon-mini-status <?= html_escape($recentRoundStatus['class']) ?>"><?= html_escape($recentRoundStatus['label']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table></div>
      </section>
    <?php endif; ?>
  </div>
</div>

<div class="modal fade" id="recon-post-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow">
    <div class="modal-header"><h5 class="modal-title"><i class="ri-shield-check-line text-danger me-1"></i>Posting Penyesuaian</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><p class="mb-2 text-muted small">Tindakan ini akan membaca ulang saldo sistem live, lalu memperbarui saldo rekening dan membuat mutasi audit. Hanya baris penyesuaian ini yang dikunci setelah diposting; sesi cek dan transaksi rekening tetap dapat berlanjut.</p><div class="recon-modal-delta" id="recon-post-modal-detail"></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-primary" id="recon-post-confirm"><i class="ri-checkbox-circle-line me-1"></i>Ya, Posting</button></div>
  </div></div>
</div>
<div class="recon-toast" id="recon-toast"></div>

<?php if ($isReady && $canEdit): ?>
<script>
(() => {
  const roundCreateUrl = <?= json_encode($roundCreateUrl, JSON_UNESCAPED_SLASHES) ?>;
  const saveUrl = <?= json_encode($saveUrl, JSON_UNESCAPED_SLASHES) ?>;
  const postUrl = <?= json_encode($postUrl, JSON_UNESCAPED_SLASHES) ?>;
  const reconciliationDate = <?= json_encode($reconciliationDate) ?>;
  const canPostLiveAdjustment = <?= json_encode($isTodayReconciliation) ?>;
  const csrfName = <?= json_encode($this->security->get_csrf_token_name()) ?>;
  const csrfHash = <?= json_encode($this->security->get_csrf_hash()) ?>;
  const toast = document.getElementById('recon-toast');
  const postModalEl = document.getElementById('recon-post-modal');
  const postDetail = document.getElementById('recon-post-modal-detail');
  const postConfirm = document.getElementById('recon-post-confirm');
  const newRoundButton = document.getElementById('recon-new-round');
  let pendingPostCard = null;
  let toastTimer = null;
  let modal = window.bootstrap && postModalEl ? new window.bootstrap.Modal(postModalEl) : null;

  const money = value => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
  const inputMoney = value => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
  const parseMoney = input => {
    let value = String(input || '').replace(/[^0-9,.-]/g, '');
    if (!value || value === '-') return null;
    const comma = value.lastIndexOf(',');
    const dot = value.lastIndexOf('.');
    if (comma > -1 && dot > -1) {
      value = comma > dot ? value.replace(/\./g, '').replace(',', '.') : value.replace(/,/g, '');
    } else if (comma > -1) {
      const tail = value.length - comma - 1;
      value = tail > 0 && tail <= 2 ? value.replace(',', '.') : value.replace(/,/g, '');
    } else if (dot > -1 && /^-?\d{1,3}(\.\d{3})+$/.test(value)) {
      value = value.replace(/\./g, '');
    }
    const result = Number(value);
    return Number.isFinite(result) ? Math.round(result * 100) / 100 : null;
  };
  const numberAttr = value => Number(value || 0);
  const showToast = (message, type = 'success') => {
    if (!toast) return;
    toast.textContent = message;
    toast.className = `recon-toast ${type} show`;
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => { toast.className = 'recon-toast'; }, 4200);
  };
  const request = async (url, payload) => {
    const body = { ...payload };
    if (csrfName && csrfHash) body[csrfName] = csrfHash;
    const response = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Finance-Reconciliation-CSRF': <?= json_encode($reconciliation_csrf ?? '') ?> }, body: JSON.stringify(body) });
    const data = await response.json().catch(() => ({ ok: false, message: 'Respons server tidak dapat dibaca.' }));
    if (!response.ok || !data.ok) throw new Error(data.message || 'Permintaan rekonsiliasi gagal.');
    return data;
  };
  const statusInfo = status => ({
    UNCHECKED: ['Belum dihitung', 'unchecked', 'ri-time-line'],
    MATCHED: ['Cocok', 'matched', 'ri-checkbox-circle-line'],
    OPEN: ['Selisih terbuka', 'open', 'ri-error-warning-line'],
    POSTED: ['Disesuaikan', 'posted', 'ri-shield-check-line'],
  }[status] || ['Belum dihitung', 'unchecked', 'ri-time-line']);
  const roundStatusInfo = status => ({
    OPEN: ['Rekon Berjalan', 'OPEN', 'ri-pulse-line'],
    REVIEWED: ['Ditinjau', 'REVIEWED', 'ri-eye-line'],
    COMPLETED: ['Selesai', 'COMPLETED', 'ri-checkbox-circle-line'],
  }[status] || ['Rekon Berjalan', 'OPEN', 'ri-pulse-line']);
  const stateText = (card, message, type = '') => {
    const el = card.querySelector('[data-role="save-state"]');
    if (!el) return;
    el.textContent = message;
    el.className = `recon-save-state ${type}`;
  };
  const currentDifference = card => {
    const input = card.querySelector('[data-role="actual-balance"]');
    const systemText = card.querySelector('[data-role="system-balance"]');
    const actual = parseMoney(input ? input.value : '');
    const system = parseMoney(systemText ? systemText.textContent : '');
    return actual === null || system === null ? null : Math.round((actual - system) * 100) / 100;
  };
  const updateActionUi = card => {
    const difference = currentDifference(card);
    const type = card.querySelector('[data-role="resolution-type"]');
    const counter = card.querySelector('[data-role="counter-wrap"]');
    const post = card.querySelector('[data-role="post"]');
    const hint = card.querySelector('[data-role="action-hint"]');
    const posted = card.dataset.status === 'POSTED';
    if (type) {
      const optionIn = type.querySelector('option[value="IN"]');
      const optionOut = type.querySelector('option[value="OUT"]');
      if (optionIn) optionIn.disabled = difference !== null && difference <= 0;
      if (optionOut) optionOut.disabled = difference !== null && difference >= 0;
      if (type.selectedOptions[0]?.disabled) type.value = 'NONE';
    }
    if (counter) counter.classList.toggle('show', type && type.value === 'TRANSFER');
    const category=card.querySelector('[data-role="report-category"]');
    if(category && type && !posted){
      category.disabled=type.value==='TRANSFER';
      for(const option of category.options) option.disabled=!!option.value && ['IN','OUT'].includes(type.value) && option.dataset.direction!=='BOTH' && option.dataset.direction!==type.value;
      if(category.selectedOptions[0]?.disabled)category.value='';
    }
    if (post) post.disabled = !canPostLiveAdjustment || posted || !card.dataset.lineId || difference === null || Math.abs(difference) < .005 || !type || type.value === 'NONE';
    if (!hint) return;
    if (posted) { hint.textContent = 'Penyesuaian sudah direkam. Gunakan riwayat mutasi untuk menelusuri dampaknya.'; return; }
    if (!canPostLiveAdjustment) { hint.textContent = 'Pengecekan tanggal lampau hanya untuk telaah. Mulai cek hari ini sebelum memposting penyesuaian.'; return; }
    if (difference === null) { hint.textContent = 'Simpan saldo riil terlebih dahulu. Anda boleh membiarkan selisih tetap terbuka tanpa membuat mutasi.'; return; }
    if (Math.abs(difference) < .005) { hint.textContent = 'Saldo cocok. Tidak ada mutasi yang perlu dibuat.'; return; }
    if (!type || type.value === 'NONE') { hint.textContent = 'Selisih disimpan sebagai temuan terbuka; saldo sistem belum akan diubah.'; return; }
    if (type.value === 'TRANSFER') { hint.textContent = difference > 0 ? 'Rekening lawan akan keluar dan rekening ini akan masuk. Total dana perusahaan tidak berubah.' : 'Rekening ini akan keluar dan rekening lawan akan masuk. Total dana perusahaan tidak berubah.'; return; }
    hint.textContent = type.value === 'IN' ? 'Posting membuat mutasi masuk ke rekening ini.' : 'Posting membuat mutasi keluar dari rekening ini.';
  };
  const applyLine = line => {
    const card = document.querySelector(`.recon-account[data-account-id="${line.account_id}"]`);
    if (!card) return;
    card.dataset.lineId = line.id || '';
    card.dataset.status = line.status || 'UNCHECKED';
    card.dataset.difference = line.difference_amount == null ? '' : line.difference_amount;
    card.classList.remove('is-unchecked', 'is-matched', 'is-open', 'is-posted', 'recon-locked');
    const info = statusInfo(line.status);
    card.classList.add(`is-${info[1]}`);
    if (line.status === 'POSTED') card.classList.add('recon-locked');
    const system = card.querySelector('[data-role="system-balance"]'); if (system) system.textContent = money(line.system_balance);
    const actual = card.querySelector('[data-role="actual-balance"]'); if (actual && line.actual_balance !== null) actual.value = inputMoney(line.actual_balance);
    const diff = Number(line.difference_amount || 0);
    const diffBox = card.querySelector('[data-role="difference-box"]');
    const diffAmount = card.querySelector('[data-role="difference-amount"]');
    const diffLabel = card.querySelector('[data-role="difference-label"]');
    if (diffBox) { diffBox.classList.remove('empty', 'neutral', 'positive', 'negative'); diffBox.classList.add(Math.abs(diff) < .005 ? 'neutral' : (diff > 0 ? 'positive' : 'negative')); }
    if (diffAmount) diffAmount.textContent = money(diff);
    if (diffLabel) diffLabel.textContent = Math.abs(diff) < .005 ? 'Saldo sudah cocok' : (diff > 0 ? 'Riil lebih besar dari sistem' : 'Riil lebih kecil dari sistem');
    const status = card.querySelector('[data-role="status"]'); if (status) status.className = `recon-status ${info[1]}`, status.innerHTML = `<i class="${info[2]}"></i><span>${info[0]}</span>`;
    const type = card.querySelector('[data-role="resolution-type"]'); if (type) type.value = line.resolution_type || 'NONE';
    const counter = card.querySelector('[data-role="counter-account"]'); if (counter) counter.value = line.counter_account_id || '';
    const note = card.querySelector('[data-role="resolution-note"]'); if (note) note.value = line.resolution_note || '';
    const category = card.querySelector('[data-role="report-category"]'); if (category) category.value = line.report_category || '';
    const save = card.querySelector('[data-role="save"]'); const post = card.querySelector('[data-role="post"]');
    if (line.status === 'POSTED') {
      [actual, type, counter, note, category, save, post].filter(Boolean).forEach(el => { el.disabled = true; });
      const postedNote = card.querySelector('[data-role="posted-note"]');
      if (postedNote) { postedNote.classList.remove('d-none'); postedNote.innerHTML = `<i class="ri-shield-check-line"></i>Diposting ${line.resolved_at || ''}${line.mutation_no ? ` · ${line.mutation_no}` : ''}`; }
      stateText(card, 'Terkunci setelah penyesuaian diposting.', 'saved');
    } else {
      stateText(card, 'Tersimpan.', 'saved');
    }
    updateActionUi(card);
  };
  const applyDashboard = dashboard => {
    if (!dashboard || !dashboard.ok) return;
    Object.entries(dashboard.summary || {}).forEach(([key, value]) => {
      document.querySelectorAll(`[data-summary="${key}"]`).forEach(el => {
        if (['system_total', 'actual_total', 'difference_total'].includes(key)) el.textContent = money(value);
        else if (key === 'open_accounts') el.textContent = `${Number(value || 0)} rekening`;
        else el.textContent = Number(value || 0);
      });
    });
    (dashboard.accounts || []).forEach(line => {
      const card = document.querySelector(`.recon-account[data-account-id="${line.account_id}"]`);
      const actual = card?.querySelector('[data-role="actual-balance"]');
      if (actual && document.activeElement === actual) return;
      applyLine(line);
    });
    const doc = document.querySelector('[data-role="document-no"]');
    if (!dashboard.header) return;
    if (doc && dashboard.header.reconciliation_no) doc.textContent = dashboard.header.reconciliation_no;
    document.querySelectorAll('[name="reconciliation_id"]').forEach(input => { input.value = Number(dashboard.header.id || 0); });
    const round = Number(dashboard.header.round_no || 0);
    const roundLabel = document.querySelector('[data-role="round-label"]');
    if (roundLabel) roundLabel.textContent = round > 0
      ? `Sesi cek ${round}${dashboard.header.reconciled_at ? ` · mulai ${dashboard.header.reconciled_at}` : ''}`
      : 'Belum ada sesi cek; simpan hasil cek atau mulai cek baru.';
    const roundStatus = document.querySelector('[data-role="round-status"]');
    if (roundStatus) {
      const info = roundStatusInfo(dashboard.header.status || 'OPEN');
      roundStatus.className = `recon-round-status ${info[1]}`;
      roundStatus.innerHTML = `<i class="${info[2]}"></i><span>${info[0]}</span>`;
    }
  };
  const payloadFor = card => ({
    reconciliation_date: card.querySelector('[name="reconciliation_date"]').value,
    reconciliation_id: Number(card.querySelector('[name="reconciliation_id"]')?.value || 0),
    account_id: Number(card.dataset.accountId),
    actual_balance: parseMoney(card.querySelector('[data-role="actual-balance"]').value),
    resolution_type: card.querySelector('[data-role="resolution-type"]').value,
    counter_account_id: Number(card.querySelector('[data-role="counter-account"]')?.value || 0),
    resolution_note: card.querySelector('[data-role="resolution-note"]')?.value || '',
    report_category: card.querySelector('[data-role="report-category"]')?.value || '',
    settlement_control_id: Number(card.querySelector('[data-role="settlement-control"]')?.value || 0),
    settlement_charge_id: Number(card.querySelector('[data-role="settlement-charge"]')?.value || 0),
  });
  const saveCard = async (card, silent = false) => {
    const payload = payloadFor(card);
    const revision = Number(card.dataset.editRevision || 0);
    if (payload.actual_balance === null || payload.actual_balance < 0) {
      if (!silent) showToast('Masukkan saldo riil dengan nilai nol atau lebih.', 'error');
      return false;
    }
    stateText(card, 'Menyimpan...', 'saving');
    try {
      const data = await request(saveUrl, payload);
      if (revision !== Number(card.dataset.editRevision || 0)) {
        stateText(card, 'Perubahan terbaru belum tersimpan.', '');
        return false;
      }
      card.dataset.dirty = '0';
      applyLine(data.line || {});
      applyDashboard(data.dashboard);
      if (!silent) showToast(data.message || 'Saldo riil tersimpan.');
      return true;
    } catch (error) {
      stateText(card, error.message, 'error');
      if (!silent) showToast(error.message, 'error');
      return false;
    }
  };
  document.querySelectorAll('.recon-account').forEach(card => {
    const actual = card.querySelector('[data-role="actual-balance"]');
    const type = card.querySelector('[data-role="resolution-type"]');
    const counter = card.querySelector('[data-role="counter-account"]');
    const note = card.querySelector('[data-role="resolution-note"]');
    const save = card.querySelector('[data-role="save"]');
    const post = card.querySelector('[data-role="post"]');
    const markDirty = () => {
      card.dataset.editRevision = String(Number(card.dataset.editRevision || 0) + 1);
    };
    actual?.addEventListener('focus', () => { const value = parseMoney(actual.value); if (value !== null) actual.value = String(value); });
    actual?.addEventListener('blur', event => {
      const value = parseMoney(actual.value);
      if (value !== null) actual.value = inputMoney(value);
      updateActionUi(card);
      const next = event.relatedTarget;
      if (next && card.contains(next) && (next.matches('[data-role="save"]') || next.matches('[data-role="post"]'))) return;
      if (card.dataset.dirty === '1') saveCard(card, true);
    });
    actual?.addEventListener('input', () => {
      card.dataset.dirty = '1';
      markDirty();
      stateText(card, 'Belum tersimpan. Pindah dari field atau tekan Simpan.', '');
      updateActionUi(card);
    });
    [type, counter, note, card.querySelector('[data-role="report-category"]'), card.querySelector('[data-role="settlement-control"]'), card.querySelector('[data-role="settlement-charge"]')].filter(Boolean).forEach(el => el.addEventListener('change', () => {
      markDirty();
      updateActionUi(card);
      if (parseMoney(actual?.value) !== null) saveCard(card, true);
    }));
    save?.addEventListener('click', () => saveCard(card));
    post?.addEventListener('click', async () => {
      const saved = await saveCard(card, true);
      if (!saved) return;
      const difference = currentDifference(card);
      if (difference === null || Math.abs(difference) < .005) return;
      const action = type?.options[type.selectedIndex]?.text || 'Penyesuaian';
      postDetail.innerHTML = `<div><b>${action}</b> sebesar <strong>${money(Math.abs(difference))}</strong>.</div><div class="small text-muted mt-1">${difference > 0 ? 'Saldo riil lebih besar dari sistem.' : 'Saldo riil lebih kecil dari sistem.'}</div>`;
      pendingPostCard = card;
      if (modal) modal.show();
      else if (window.confirm(`Posting ${action} sebesar ${money(Math.abs(difference))}?`)) postConfirmed();
    });
    updateActionUi(card);
  });
  newRoundButton?.addEventListener('click', async () => {
    const prompt = 'Mulai cek baru? Ini membuka sesi cek baru tanpa mengunci saldo sistem. '
      + 'Sesi sebelumnya tetap tersimpan dan transaksi rekening tetap berjalan.';
    if (!window.confirm(prompt)) return;
    newRoundButton.disabled = true;
    try {
      const data = await request(roundCreateUrl, { reconciliation_date: reconciliationDate });
      if (data.redirect_url) {
        window.location.assign(data.redirect_url);
        return;
      }
      showToast(data.message || 'Sesi pengecekan baru dibuat.');
    } catch (error) {
      showToast(error.message, 'error');
    } finally {
      newRoundButton.disabled = false;
    }
  });
  const postConfirmed = async () => {
    const card = pendingPostCard;
    if (!card) return;
    const button = card.querySelector('[data-role="post"]');
    if (button) button.disabled = true;
    try {
      const data = await request(postUrl, { line_id: Number(card.dataset.lineId) });
      applyLine(data.line || {});
      applyDashboard(data.dashboard);
      if (modal) modal.hide();
      showToast(data.message || 'Penyesuaian berhasil diposting.');
    } catch (error) {
      if (button) button.disabled = false;
      showToast(error.message, 'error');
    } finally { pendingPostCard = null; }
  };
  postConfirm?.addEventListener('click', postConfirmed);
})();
</script>
<?php endif; ?>
