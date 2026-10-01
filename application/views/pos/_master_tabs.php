<?php
$activeTab = strtolower(trim((string)($pos_master_tab_active ?? 'payment-method')));
$links = [
    ['key' => 'sales-channel', 'label' => 'Sales Channel', 'url' => site_url('pos/sales-channels'), 'enabled' => true],
    ['key' => 'payment-method', 'label' => 'Payment Method', 'url' => site_url('pos/payment-methods'), 'enabled' => true],
    ['key' => 'deposit', 'label' => 'Deposit / DP', 'url' => site_url('pos/deposits'), 'enabled' => true],
    ['key' => 'self-order', 'label' => 'Self Order', 'url' => site_url('pos/self-order/orders'), 'enabled' => true],
    ['key' => 'reservation', 'label' => 'Reservasi', 'url' => site_url('pos/reservations'), 'enabled' => true],
    ['key' => 'online-food', 'label' => 'Online Food', 'url' => site_url('pos/online-food/settings'), 'enabled' => true],
    ['key' => 'stock-commit-audit', 'label' => 'Audit Commit', 'url' => site_url('pos/stock-commit-audit'), 'enabled' => true],
    ['key' => 'daily-recon-settings', 'label' => 'Gate Daily Recon', 'url' => site_url('pos/daily-recon-settings'), 'enabled' => true],
    ['key' => 'outlet-terminal', 'label' => 'Outlet + Terminal', 'url' => site_url('pos/outlets-terminals'), 'enabled' => true],
    ['key' => 'printer', 'label' => 'Printer', 'url' => site_url('pos/printers'), 'enabled' => true],
];
?>

<style>
  .pos-master-label {
    width: 100%;
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #7a6d62;
    padding-top: .1rem;
    margin-bottom: .2rem;
  }
  .pos-master-shell {
    display:flex;
    flex-direction:column;
    gap:.45rem;
    width:100%;
  }
  .pos-master-row {
    display:flex;
    flex-wrap:wrap;
    gap:.55rem;
    align-items:center;
  }
  .pos-master-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: .45rem .9rem;
    border-radius: 10px;
    font-size: .92rem;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid #b7ab9e;
    background: #fffaf6;
    color: #6a5c54;
  }
  .pos-master-pill.is-active {
    background: #34325e;
    border-color: #34325e;
    color: #fff;
  }
  .pos-master-pill.is-disabled {
    opacity: .55;
    cursor: default;
  }
</style>

<div class="pos-master-shell mb-3">
  <div class="pos-master-label">Master POS</div>
  <div class="pos-master-row">
    <?php foreach ($links as $link): ?>
      <?php if (!empty($link['enabled'])): ?>
        <a href="<?php echo $link['url']; ?>" class="pos-master-pill <?php echo $activeTab === $link['key'] ? 'is-active' : ''; ?>">
          <?php echo html_escape((string)$link['label']); ?>
        </a>
      <?php else: ?>
        <span class="pos-master-pill is-disabled"><?php echo html_escape((string)$link['label']); ?></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>

