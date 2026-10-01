<?php
$activeTab = strtolower(trim((string)($online_food_tab_active ?? 'orders')));
$tabs = [
    [
        'key' => 'orders',
        'label' => 'Orderan',
        'hint' => 'Verifikasi + cetak',
        'url' => site_url('pos/online-food/orders'),
    ],
    [
        'key' => 'settings',
        'label' => 'Settings',
        'hint' => 'Jam, bayar, ongkir',
        'url' => site_url('pos/online-food/settings'),
    ],
    [
        'key' => 'locations',
        'label' => 'Alamat',
        'hint' => 'Gratis ongkir',
        'url' => site_url('pos/online-food/locations'),
    ],
];
?>

<style>
  .online-food-nav-label {
    min-width: 88px;
    padding-top: .45rem;
    color: #6c7167;
    font-size: .74rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
  }
  .online-food-nav-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: .65rem;
  }
  .online-food-nav-tab {
    display: inline-flex;
    flex-direction: column;
    justify-content: center;
    min-width: 148px;
    min-height: 50px;
    padding: .62rem .9rem;
    border-radius: 10px;
    border: 1px solid #cbd8cb;
    background: #fff;
    color: #4f5b4e;
    text-decoration: none;
    transition: all .18s ease;
  }
  .online-food-nav-tab:hover {
    background: #f5fbf4;
    border-color: #aec8ad;
    color: #3f513e;
  }
  .online-food-nav-tab.is-active {
    background: #1f6f58;
    border-color: #1f6f58;
    color: #fff;
    box-shadow: 0 12px 24px rgba(31, 111, 88, .16);
  }
  .online-food-nav-title {
    font-size: .92rem;
    font-weight: 700;
    line-height: 1.15;
  }
  .online-food-nav-hint {
    margin-top: .18rem;
    font-size: .7rem;
    font-weight: 600;
    opacity: .86;
    line-height: 1.15;
  }
</style>

<div class="d-flex flex-wrap gap-2 align-items-start mb-3">
  <div class="online-food-nav-label">Online Food</div>
  <div class="online-food-nav-wrap">
    <?php foreach ($tabs as $tab): ?>
      <a href="<?php echo $tab['url']; ?>" class="online-food-nav-tab <?php echo $activeTab === $tab['key'] ? 'is-active' : ''; ?>">
        <span class="online-food-nav-title"><?php echo html_escape($tab['label']); ?></span>
        <span class="online-food-nav-hint"><?php echo html_escape($tab['hint']); ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
