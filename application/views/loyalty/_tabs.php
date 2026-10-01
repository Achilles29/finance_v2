<?php
$activeTab = strtolower(trim((string)($promo_tab_active ?? 'member')));
$links = [
    ['key' => 'member', 'label' => 'Member', 'url' => site_url('loyalty/members')],
    ['key' => 'point-rule', 'label' => 'Poin', 'url' => site_url('loyalty/point-rules')],
    ['key' => 'stamp-campaign', 'label' => 'Stamp', 'url' => site_url('loyalty/stamp-campaigns')],
    ['key' => 'voucher-issue', 'label' => 'Voucher', 'url' => site_url('loyalty/vouchers')],
    ['key' => 'gowes', 'label' => 'GOWES VOL9', 'url' => site_url('loyalty/gowes-participants')],
    ['key' => 'voucher-usage', 'label' => 'Pemakaian Voucher', 'url' => site_url('loyalty/voucher-usages')],
    ['key' => 'voucher-campaign', 'label' => 'Promo Voucher', 'url' => site_url('loyalty/voucher-campaigns')],
    ['key' => 'redeem', 'label' => 'Redeem', 'url' => site_url('loyalty/redeem')],
    ['key' => 'redeem-rule', 'label' => 'Pengaturan Redeem', 'url' => site_url('loyalty/redeem-rules')],
];
?>
<style>
  .loyalty-tabs {
    display:grid; grid-template-columns:88px minmax(0,1fr); gap:.5rem; align-items:start; margin-bottom:1rem;
  }
  .loyalty-label {
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #7a6d62;
    padding-top: .42rem;
  }
  .loyalty-tab-list { display:flex; gap:.42rem; min-width:0; overflow-x:auto; padding:.08rem .08rem .35rem; scrollbar-width:thin; }
  .loyalty-pill {
    display:inline-flex; flex:0 0 auto; align-items:center; justify-content:center; min-height:38px; padding:.45rem .9rem;
    border-radius:10px; font-size:.92rem; font-weight:600; text-decoration:none; border:1px solid #cbb8aa;
    background:#fffaf6; color:#6a5c54;
  }
  .loyalty-pill.is-active {
    background:#7f2f33; border-color:#7f2f33; color:#fff;
  }
  .loyalty-pill:focus-visible { outline:3px solid rgba(127,47,51,.24); outline-offset:2px; }
  @media (max-width:575px) { .loyalty-tabs { grid-template-columns:1fr; gap:.1rem; } .loyalty-label { padding-top:0; } .loyalty-tab-list { padding-bottom:.45rem; } }
</style>
<nav class="loyalty-tabs" aria-label="Navigasi Loyalty">
  <div class="loyalty-label">Loyalty</div>
  <div class="loyalty-tab-list" role="list">
    <?php foreach ($links as $link): ?>
      <?php $isActive = $activeTab === $link['key']; ?>
      <a href="<?php echo html_escape((string)$link['url']); ?>" class="loyalty-pill <?php echo $isActive ? 'is-active' : ''; ?>"<?php echo $isActive ? ' aria-current="page"' : ''; ?>>
        <?php echo html_escape((string)$link['label']); ?>
      </a>
    <?php endforeach; ?>
  </div>
</nav>
