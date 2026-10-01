<?php
$activeTab = strtolower(trim((string)($self_order_tab_active ?? 'orders')));
$tabs = [
    [
        'key' => 'orders',
        'label' => 'Orderan',
        'hint' => 'Verifikasi + cetak',
        'url' => site_url('pos/self-order/orders'),
    ],
    [
        'key' => 'tables',
        'label' => 'QR Meja',
        'hint' => 'QR, label, print',
        'url' => site_url('pos/self-order/tables'),
    ],
    [
        'key' => 'settings',
        'label' => 'Settings',
        'hint' => 'Aktivasi + Midtrans',
        'url' => site_url('pos/self-order/settings'),
    ],
];
?>

<style>
  .self-order-nav-label {
    min-width: 88px;
    padding-top: .45rem;
    color: #7a6d62;
    font-size: .74rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
  }
  .self-order-nav-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: .65rem;
  }
  .self-order-nav-tab {
    display: inline-flex;
    flex-direction: column;
    justify-content: center;
    min-width: 148px;
    min-height: 50px;
    padding: .62rem .9rem;
    border-radius: 10px;
    border: 1px solid #d8c7bb;
    background: #fff;
    color: #5f5149;
    text-decoration: none;
    transition: all .18s ease;
  }
  .self-order-nav-tab:hover {
    background: #fff7f2;
    border-color: #cdb6a7;
    color: #4e4039;
  }
  .self-order-nav-tab.is-active {
    background: #9f2141;
    border-color: #9f2141;
    color: #fff;
    box-shadow: 0 12px 24px rgba(159, 33, 65, .16);
  }
  .self-order-nav-title {
    font-size: .92rem;
    font-weight: 700;
    line-height: 1.15;
  }
  .self-order-nav-hint {
    margin-top: .18rem;
    font-size: .7rem;
    font-weight: 600;
    opacity: .86;
    line-height: 1.15;
  }
  @media (max-width: 767.98px) {
    .self-order-nav-tab {
      min-width: 128px;
      min-height: 46px;
      padding: .55rem .78rem;
    }
  }
</style>

<div class="d-flex flex-wrap gap-2 align-items-start mb-3">
  <div class="self-order-nav-label">Self Order</div>
  <div class="self-order-nav-wrap">
    <?php foreach ($tabs as $tab): ?>
      <a href="<?= $tab['url'] ?>" class="self-order-nav-tab <?= $activeTab === $tab['key'] ? 'is-active' : '' ?>">
        <span class="self-order-nav-title"><?= html_escape($tab['label']) ?></span>
        <span class="self-order-nav-hint"><?= html_escape($tab['hint']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
