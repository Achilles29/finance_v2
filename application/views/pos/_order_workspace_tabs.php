<?php
$orderWorkspaceActive = strtolower(trim((string)($order_workspace_active ?? '')));
if ($orderWorkspaceActive === '') {
    $activeMenuValue = strtolower(trim((string)($active_menu ?? '')));
    $map = [
        'pos.cashier.index'        => 'cashier',
        'pos.order.draft.index'    => 'draft',
        'pos.order.paid.index'     => 'paid',
        'pos.order.monitor.index'  => 'monitor',
    ];
    $orderWorkspaceActive = $map[$activeMenuValue] ?? '';
}

$orderWorkspaceTabs = [
    ['key' => 'cashier', 'label' => 'Kasir POS',          'hint' => 'Input Order',    'url' => site_url('pos/cashier'),       'icon' => 'ri-shopping-bag-3-line'],
    ['key' => 'draft',   'label' => 'Draft Order',         'hint' => 'Belum Bayar',    'url' => site_url('pos/orders/draft'),  'icon' => 'ri-clipboard-line'],
    ['key' => 'paid',    'label' => 'Pesanan Terbayar',    'hint' => 'Refund / Audit', 'url' => site_url('pos/orders/paid'),   'icon' => 'ri-wallet-3-line'],
    ['key' => 'monitor', 'label' => 'Monitor Dapur / Bar', 'hint' => 'Kitchen Board',  'url' => site_url('pos/order-monitor'), 'icon' => 'ri-restaurant-2-line'],
];
?>

<style>
  .pos-order-workspace-nav {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    margin-bottom: 1rem;
  }
  .pos-order-workspace-nav .pos-ow-link {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    border-radius: 10px;
    font-weight: 700;
    padding: .55rem .85rem;
    border: 1px solid #dfd5cb;
    background: #fff;
    color: #51453d;
    text-decoration: none;
    transition: background .15s, border-color .15s, box-shadow .15s;
  }
  .pos-order-workspace-nav .pos-ow-link:hover {
    color: #3f342d;
    border-color: #d9c8bc;
    background: #fff8f3;
  }
  .pos-order-workspace-nav .pos-ow-link.is-active {
    background: #9f2141;
    border-color: #9f2141;
    color: #fff;
    box-shadow: 0 8px 18px rgba(159, 33, 65, .18);
  }
  .pos-ow-dot {
    width: .58rem;
    height: .58rem;
    border-radius: .18rem;
    background: #6a5c54;
    display: inline-block;
    flex: 0 0 .58rem;
  }
  .pos-order-workspace-nav .pos-ow-link.is-active .pos-ow-dot {
    background: #fff;
    opacity: .95;
  }
  .pos-ow-copy {
    display: flex;
    flex-direction: column;
    line-height: 1.05;
  }
  .pos-ow-copy small {
    font-size: .69rem;
    font-weight: 600;
    opacity: .72;
  }
  @media (max-width: 575.98px) {
    .pos-order-workspace-nav .pos-ow-link {
      width: 100%;
      justify-content: flex-start;
    }
  }
</style>

<div class="pos-order-workspace-nav" role="navigation" aria-label="Tab Workspace Order POS">
  <?php foreach ($orderWorkspaceTabs as $tab): ?>
    <a href="<?php echo $tab['url']; ?>"
       class="pos-ow-link<?php echo $orderWorkspaceActive === $tab['key'] ? ' is-active' : ''; ?>">
      <span class="pos-ow-dot" aria-hidden="true"></span>
      <span class="pos-ow-copy">
        <span><?php echo html_escape($tab['label']); ?></span>
        <small><?php echo html_escape($tab['hint']); ?></small>
      </span>
    </a>
  <?php endforeach; ?>
</div>
