<?php
$filters = is_array($filters ?? null) ? $filters : [];
$filterOptions = is_array($filter_options ?? null) ? $filter_options : [];
$outlets = is_array($filterOptions['outlets'] ?? null) ? $filterOptions['outlets'] : [];
$terminals = is_array($filterOptions['terminals'] ?? null) ? $filterOptions['terminals'] : [];
$cashierBootstrap = is_array($cashier_bootstrap ?? null) ? $cashier_bootstrap : [];
$activeSession = is_array($cashierBootstrap['active_session'] ?? null) ? $cashierBootstrap['active_session'] : null;
$salesChannels = is_array($cashierBootstrap['sales_channels'] ?? null) ? $cashierBootstrap['sales_channels'] : [];
$orderReprintPrinters = is_array($cashierBootstrap['order_reprint_printers'] ?? null) ? $cashierBootstrap['order_reprint_printers'] : [];
$defaultSalesChannelId = !empty($cashierBootstrap['default_sales_channel_id']) ? (int)$cashierBootstrap['default_sales_channel_id'] : 0;
$defaultLaunchOutletId = !empty($cashierBootstrap['default_outlet_id']) ? (int)$cashierBootstrap['default_outlet_id'] : 0;
$defaultLaunchTerminalId = !empty($cashierBootstrap['default_terminal_id']) ? (int)$cashierBootstrap['default_terminal_id'] : 0;
$defaultLaunchOpeningCash = array_key_exists('default_opening_cash', $cashierBootstrap) ? (float)$cashierBootstrap['default_opening_cash'] : 300000;
$catalogFilters = is_array($catalog_filters ?? null) ? $catalog_filters : [];
$catalogDivisions = is_array($catalogFilters['divisions'] ?? null) ? $catalogFilters['divisions'] : [];
$reversalReasonOptions = is_array($filterOptions['reversal_reason_options'] ?? null) ? $filterOptions['reversal_reason_options'] : [];
$cashierCurrentUser = is_array($current_user ?? null) ? $current_user : [];
$cashierUserPerms = is_array($user_perms ?? null) ? $user_perms : [];
$cashierIsSuperadmin = !empty($cashierCurrentUser['is_superadmin']);
$incomingChannelDefinitions = [
  'self_order' => [
    'page_code' => 'pos.self_order.index',
    'label' => 'Self Order',
    'icon' => 'ri-qr-scan-2-line',
    'endpoint' => site_url('pos/self-order/orders/data'),
    'detail_endpoint' => site_url('pos/self-order/orders/detail') . '/',
    'module_url' => site_url('pos/self-order/orders'),
  ],
  'reservation' => [
    'page_code' => 'pos.reservation.index',
    'label' => 'Reservasi',
    'icon' => 'ri-calendar-check-line',
    'endpoint' => site_url('pos/reservations/data'),
    'detail_endpoint' => site_url('pos/reservations/detail') . '/',
    'module_url' => site_url('pos/reservations'),
  ],
  'online_food' => [
    'page_code' => 'pos.online_food.index',
    'label' => 'Online Food',
    'icon' => 'ri-smartphone-line',
    'endpoint' => site_url('pos/online-food/orders/data'),
    'detail_endpoint' => site_url('pos/online-food/orders/detail') . '/',
    'module_url' => site_url('pos/online-food/orders'),
  ],
];
$incomingChannelViewPermissions = [
  'self_order' => $cashierIsSuperadmin || !empty($cashierUserPerms['pos.self_order.index']['can_view']),
  'reservation' => $cashierIsSuperadmin || !empty($cashierUserPerms['pos.reservation.index']['can_view']),
  'online_food' => $cashierIsSuperadmin || !empty($cashierUserPerms['pos.online_food.index']['can_view']),
];
$incomingChannels = [];
foreach ($incomingChannelDefinitions as $channelCode => $channelDefinition) {
  if (!empty($incomingChannelViewPermissions[$channelCode])) {
    $incomingChannels[$channelCode] = $channelDefinition;
  }
}
$canViewPosPrinters = $cashierIsSuperadmin || !empty($cashierUserPerms['pos.printer.index']['can_view']);
$incomingCashierOutletId = is_array($activeSession) ? max(0, (int)($activeSession['outlet_id'] ?? 0)) : 0;
$incomingServerDate = date('Y-m-d');
?>

<style>
  .cashier-shell { display:grid; gap:1rem; height:calc(100vh - 106px); min-height:0; }
  .cashier-workbench {
    display:grid;
    grid-template-columns:minmax(280px, 324px) minmax(0, 1fr) minmax(400px, 500px);
    gap:1rem;
    align-items:start;
    height:100%;
    min-height:0;
    width:100%;
  }
  .cashier-column { display:grid; gap:1rem; min-width:0; min-height:0; height:100%; }
  .cashier-card {
    border:0; border-radius:26px; box-shadow:0 18px 38px rgba(58, 38, 30, .08);
    background:#fff;
    height:100%;
  }
  .cashier-panel-title { font-size:1rem; font-weight:900; color:#2f2628; }
  .cashier-panel-note { color:#7d6d67; font-size:.88rem; }
  .cashier-recent-status-bar {
    display:flex;
    gap:.4rem;
    flex-wrap:nowrap;
    overflow:hidden;
    width:100%;
  }
  .cashier-recent-status-bar .btn {
    flex:1 1 0;
    min-width:0;
    padding:.4rem .45rem;
    font-size:.76rem;
    border-radius:12px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .cashier-recent-status-bar .btn.active {
    background:#943f35;
    border-color:#943f35;
    color:#fff !important;
    box-shadow:0 10px 22px rgba(148,63,53,.16);
  }
  .cashier-search-mode .btn.active { background:#943f35; color:#fff; border-color:#943f35; }
  .cashier-search-result {
    min-height:0; flex:1; border:1px dashed rgba(191, 170, 157, .7); border-radius:22px; padding:.8rem;
    background:linear-gradient(135deg,#fff9f6 0%,#fff 100%);
    overflow:auto;
  }
  .cashier-product-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(132px,1fr)); gap:.68rem; }
  .cashier-product-card {
    border:1px solid rgba(225, 210, 199, .78); border-radius:18px; padding:.58rem; background:#fff;
    cursor:pointer; transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease;
  }
  .cashier-product-card:hover {
    transform:translateY(-2px);
    border-color:rgba(169, 77, 56, .45);
    box-shadow:0 14px 30px rgba(97, 56, 43, .12);
  }
  .cashier-product-media {
    height:92px; border-radius:14px; overflow:hidden; background:linear-gradient(135deg,#f8e8db 0%, #f2d3bf 100%);
    display:flex; align-items:center; justify-content:center; margin-bottom:.58rem;
  }
  .cashier-product-media img { width:100%; height:100%; object-fit:cover; display:block; }
  .cashier-product-fallback {
    width:100%; height:100%; display:flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg,#8f3d33 0%, #cf624a 100%); color:#fff; font-size:2rem; font-weight:900;
  }
  .cashier-product-name { font-weight:900; color:#33282a; font-size:.76rem; line-height:1.18; }
  .cashier-product-meta { font-size:.7rem; color:#7f6f67; line-height:1.35; }
  .cashier-chip {
    display:inline-flex; align-items:center; gap:.35rem; padding:.24rem .65rem; border-radius:999px;
    font-size:.68rem; font-weight:800; max-width:100%; white-space:nowrap;
  }
  .cashier-chip.ok { background:#e9f8ec; color:#1d7f45; }
  .cashier-chip.warn { background:#fff3de; color:#8d5a00; }
  .cashier-chip.out { background:#fde8e8; color:#b42318; }
  .cashier-chip.bundle { background:#fff0de; color:#9a4e0f; }
  .cashier-chip.info { background:#eef5ff; color:#2359a6; }
  .cashier-chip.stock { background:#f3f0ff; color:#5b43a8; }
  .cashier-member-box,
  .cashier-meta-box {
    border:1px solid rgba(225, 210, 199, .75); border-radius:18px; background:#fffdfb; padding:.9rem 1rem;
  }
  .cashier-meta-box { padding:.7rem .85rem; }
  .cashier-member-empty { font-size:.86rem; color:#85736b; }
  .cashier-member-title { font-weight:900; color:#33282a; font-size:.92rem; line-height:1.2; }
  .cashier-member-meta { font-size:.74rem; color:#7d6d67; line-height:1.25; }
  .cashier-search-wrap { position:relative; }
  .cashier-search-dropdown {
    position:absolute; top:calc(100% + .45rem); left:0; right:0; z-index:1050;
    background:#fff; border:1px solid rgba(201, 183, 168, .72); border-radius:18px;
    box-shadow:0 16px 36px rgba(61, 38, 27, .14); overflow:hidden;
  }
  .cashier-search-item { display:flex; justify-content:space-between; gap:.8rem; padding:.85rem 1rem; cursor:pointer; border-bottom:1px solid rgba(232,220,210,.9); }
  .cashier-search-item:last-child { border-bottom:0; }
  .cashier-search-item:hover { background:#fff7f2; }
  .cashier-cart-card { position:static; }
  .cashier-cart-list {
    display:grid; gap:.65rem;
    max-height:none;
    overflow:visible;
    padding-right:0;
  }
  .cashier-cart-item {
    border:1px solid rgba(225,210,199,.78); border-radius:16px; padding:.6rem .66rem;
    background:linear-gradient(135deg,#fffaf7 0%,#fff 100%);
  }
  .cashier-cart-item-head {
    display:flex; justify-content:space-between; align-items:flex-start; gap:.6rem;
  }
  .cashier-cart-item-title { font-weight:800; color:#2f2628; font-size:.88rem; line-height:1.12; }
  .cashier-cart-item-sub { font-size:.64rem; color:#8a7a72; line-height:1.08; }
  .cashier-cart-item-actions {
    display:flex; gap:.4rem; align-items:center; flex-wrap:wrap;
  }
  .cashier-qty-stepper {
    display:inline-grid;
    grid-template-columns:30px 34px 30px;
    align-items:center;
    border:1px solid rgba(225,210,199,.9);
    border-radius:10px;
    overflow:hidden;
    background:#fff;
    width:100%;
  }
  .cashier-qty-stepper button {
    border:0;
    background:#fff7f2;
    color:#7d5146;
    font-weight:900;
    min-height:30px;
    padding:0;
  }
  .cashier-qty-stepper input {
    border:0;
    text-align:center;
    min-height:30px;
    font-weight:800;
    background:#fff;
    width:100%;
    min-width:0;
    appearance:textfield;
    -moz-appearance:textfield;
  }
  .cashier-qty-stepper input::-webkit-outer-spin-button,
  .cashier-qty-stepper input::-webkit-inner-spin-button {
    -webkit-appearance:none;
    margin:0;
  }
  .cashier-inline-note {
    min-height:30px;
    font-size:.7rem;
    width:100%;
    padding:.28rem .42rem;
  }
  .cashier-cart-editor-row {
    display:grid;
    grid-template-columns:96px minmax(0, 1fr);
    gap:.42rem;
    align-items:start;
  }
  .cashier-cart-editor-row > div { min-width:0; }
  .cashier-cart-editor-row .form-label {
    display:block;
    min-height:16px;
    line-height:1.1;
  }
  .cashier-cart-extras {
    margin-top:.24rem;
    display:flex;
    gap:.22rem;
    flex-wrap:wrap;
  }
  .cashier-cart-extras .cashier-extra-pill {
    font-size:.62rem;
    padding:.14rem .42rem;
  }
  .cashier-cart-price-breakdown {
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:.42rem;
    margin-top:.42rem;
  }
  .cashier-cart-price-metric {
    border:1px solid rgba(225,210,199,.72);
    border-radius:12px;
    background:#fff;
    padding:.42rem .5rem;
    min-width:0;
  }
  .cashier-cart-price-metric .label {
    display:block;
    font-size:.6rem;
    line-height:1.1;
    color:#8a7a72;
    text-transform:uppercase;
    letter-spacing:.04em;
    margin-bottom:.14rem;
  }
  .cashier-cart-price-metric strong {
    display:block;
    color:#2f2628;
    font-size:.78rem;
    line-height:1.15;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .cashier-cart-price-metric.is-subtotal {
    background:linear-gradient(180deg,#fff7ea 0%,#fff 100%);
    border-color:rgba(239, 198, 112, .95);
  }
  .cashier-cart-price-metric.is-subtotal strong {
    color:#8f4c17;
    font-weight:900;
  }
  .cashier-cart-total { font-size:1.72rem; font-weight:900; color:#2e2527; }
  .cashier-cart-actions .btn { min-height:46px; }
  .cashier-mini-note { color:#85736b; font-size:.78rem; }
  .cashier-recent-list { display:grid; gap:.75rem; }
  .cashier-recent-item {
    border:1px solid rgba(225,210,199,.78); border-radius:18px; padding:.82rem .9rem; cursor:pointer; background:#fff;
    transition:border-color .15s ease, box-shadow .15s ease, background .15s ease;
  }
  .cashier-recent-item:hover { background:#fff7f2; }
  .cashier-recent-item.active {
    border-color:rgba(148,63,53,.58);
    background:#fff5ef;
    box-shadow:0 12px 28px rgba(148,63,53,.12);
  }
  .cashier-status-badges {
    display:flex;
    flex-wrap:wrap;
    justify-content:flex-end;
    gap:.32rem;
    margin-top:.32rem;
  }
  .cashier-status-chip {
    display:inline-flex;
    align-items:center;
    gap:.28rem;
    padding:.18rem .55rem;
    border-radius:999px;
    font-size:.66rem;
    font-weight:900;
    letter-spacing:.01em;
    white-space:nowrap;
  }
  .cashier-status-chip.order-draft { background:#fff3cd; color:#8a5700; }
  .cashier-status-chip.order-confirmed { background:#dcfce7; color:#166534; }
  .cashier-status-chip.commit-queued { background:#e0f2fe; color:#075985; }
  .cashier-status-chip.commit-processing { background:#ede9fe; color:#5b21b6; }
  .cashier-status-chip.commit-posted { background:#dcfce7; color:#166534; }
  .cashier-status-chip.commit-failed { background:#fee2e2; color:#b91c1c; }
  .cashier-status-chip.commit-reversed { background:#f1f5f9; color:#334155; }
  .cashier-empty {
    border:1px dashed rgba(189,170,154,.6); border-radius:18px; padding:1.3rem; text-align:center;
    color:#8b7a70; background:#fffaf6;
  }
  .cashier-launch-card {
    border:0; border-radius:30px; overflow:hidden;
    background:
      radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 32%),
      linear-gradient(135deg, #2d2526 0%, #5b312b 58%, #8e4537 100%);
    color:#fff;
    box-shadow:0 26px 54px rgba(44,29,29,.18);
  }
  .cashier-launch-card .form-control,
  .cashier-launch-card .form-select {
    background:rgba(255,255,255,.96);
    border:0;
    border-radius:16px;
    min-height:48px;
  }
  .cashier-quick-nav {
    display:flex; align-items:stretch; gap:.55rem; flex-wrap:wrap; padding:.65rem;
    border:1px solid rgba(225,210,199,.82); border-radius:18px;
    background:rgba(255,255,255,.88); box-shadow:0 12px 30px rgba(58,38,30,.07);
  }
  .cashier-quick-nav .cashier-quick-link,
  .cashier-quick-nav .cashier-quick-action {
    display:inline-flex; align-items:center; justify-content:center; gap:.42rem;
    min-height:40px; flex:1 1 130px; padding:.52rem .78rem;
    border:1px solid rgba(210,193,181,.92); border-radius:12px;
    background:#fffaf7; color:#624d45; font-size:.78rem; font-weight:800;
    text-decoration:none; white-space:nowrap;
  }
  .cashier-quick-nav .cashier-quick-action.is-primary { border-color:#943f35; background:#943f35; color:#fff; }
  .cashier-quick-nav .cashier-quick-action:disabled { cursor:not-allowed; opacity:.58; }
  .cashier-quick-badge { min-width:1.35rem; padding:.12rem .35rem; border-radius:999px; background:rgba(255,255,255,.2); font-size:.68rem; }
  .cashier-incoming-modal .modal-content { border:0; border-radius:24px; overflow:hidden; box-shadow:0 28px 70px rgba(45,29,28,.24); }
  .cashier-incoming-head { padding:1.2rem 1.3rem; background:linear-gradient(135deg,#2f2628,#7c3d35); color:#fff; }
  .cashier-incoming-icon { width:2.7rem; height:2.7rem; display:inline-flex; align-items:center; justify-content:center; border-radius:15px; background:rgba(255,255,255,.15); font-size:1.35rem; }
  .cashier-incoming-subtitle { color:rgba(255,255,255,.76); font-size:.8rem; }
  .cashier-incoming-status { color:#d9f5df; font-size:.72rem; font-weight:800; }
  .cashier-incoming-tabs { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:.55rem; padding:1rem 1.25rem 0; }
  .cashier-incoming-tab { border:1px solid #e1d2c7; border-radius:14px; padding:.7rem .8rem; background:#fffaf7; color:#674f46; text-align:left; font-weight:800; }
  .cashier-incoming-tab.is-active { border-color:#943f35; background:#fff0ea; color:#8a382f; }
  .cashier-incoming-tab-label { display:flex; align-items:center; justify-content:space-between; gap:.5rem; font-size:.82rem; }
  .cashier-incoming-tab-count { min-width:1.35rem; padding:.12rem .35rem; border-radius:999px; background:#eaded6; font-size:.68rem; text-align:center; }
  .cashier-incoming-tab.is-active .cashier-incoming-tab-count { background:#943f35; color:#fff; }
  .cashier-incoming-list { display:grid; gap:.65rem; min-height:220px; max-height:430px; overflow:auto; padding:1rem 1.25rem 1.25rem; }
  .cashier-incoming-row { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.8rem; align-items:center; padding:.85rem; border:1px solid #eaded6; border-radius:16px; background:#fff; }
  .cashier-incoming-row-title { color:#33282a; font-size:.88rem; font-weight:900; }
  .cashier-incoming-row-meta { color:#806e66; font-size:.72rem; line-height:1.45; }
  .cashier-incoming-row-amount { color:#943f35; font-size:.86rem; font-weight:900; white-space:nowrap; }
  .cashier-incoming-detail { margin:0 1.25rem 1.25rem; padding:1rem; border:1px solid #eaded6; border-radius:16px; background:#fffaf7; }
  .cashier-incoming-detail-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.55rem; }
  .cashier-incoming-detail-item { padding:.6rem .7rem; border-radius:12px; background:#fff; border:1px solid #eee3dc; }
  .cashier-incoming-detail-label { color:#8b786f; font-size:.68rem; font-weight:800; text-transform:uppercase; }
  .cashier-incoming-detail-value { color:#382c2e; font-size:.8rem; font-weight:700; overflow-wrap:anywhere; }
  .cashier-incoming-lines { display:grid; gap:.35rem; margin-top:.75rem; }
  .cashier-incoming-line { display:flex; justify-content:space-between; gap:.7rem; padding:.42rem .55rem; border-radius:9px; background:#fff; color:#594842; font-size:.74rem; }
  .cashier-incoming-empty { display:grid; place-items:center; min-height:180px; color:#917e75; font-size:.84rem; text-align:center; }
  @media (max-width:767.98px) {
    .cashier-incoming-row { grid-template-columns:1fr; }
    .cashier-incoming-row .btn { width:100%; }
    .cashier-incoming-detail-grid { grid-template-columns:1fr; }
  }
  .cashier-toast-stack {
    position:fixed;
    right:18px;
    bottom:18px;
    z-index:1085;
    display:grid;
    gap:.65rem;
    width:min(360px, calc(100vw - 24px));
  }
  .cashier-toast {
    border:1px solid rgba(225,210,199,.78);
    border-left:4px solid #b85c48;
    border-radius:16px;
    background:rgba(255,255,255,.98);
    box-shadow:0 20px 42px rgba(58, 38, 30, .14);
    padding:.82rem .95rem;
    display:grid;
    gap:.22rem;
    transform:translateY(0);
    opacity:1;
    transition:opacity .22s ease, transform .22s ease;
  }
  .cashier-toast.is-hiding {
    opacity:0;
    transform:translateY(8px);
  }
  .cashier-toast-title {
    font-size:.78rem;
    font-weight:900;
    color:#2f2628;
    text-transform:uppercase;
    letter-spacing:.04em;
  }
  .cashier-toast-body {
    font-size:.8rem;
    line-height:1.45;
    color:#6d5b54;
  }
  .cashier-toast.success { border-left-color:#1d7f45; }
  .cashier-toast.warn { border-left-color:#b66919; }
  .cashier-toast.error { border-left-color:#b42318; }
  .cashier-saving-overlay {
    position:fixed;
    inset:0;
    z-index:1088;
    display:none;
    align-items:center;
    justify-content:center;
    background:rgba(255,251,248,.42);
    backdrop-filter:blur(2px);
  }
  .cashier-saving-overlay.active {
    display:flex;
  }
  .cashier-saving-card {
    min-width:260px;
    max-width:min(92vw, 360px);
    border:1px solid rgba(225,210,199,.82);
    border-radius:22px;
    background:rgba(255,255,255,.98);
    box-shadow:0 24px 54px rgba(58,38,30,.16);
    padding:1rem 1.15rem;
    display:grid;
    gap:.35rem;
    text-align:center;
  }
  .cashier-saving-spinner {
    width:40px;
    height:40px;
    margin:0 auto .2rem;
    border:3px solid rgba(184,92,72,.16);
    border-top-color:#b85c48;
    border-radius:50%;
    animation:cashier-spin .8s linear infinite;
  }
  .cashier-saving-title {
    font-size:.9rem;
    font-weight:900;
    color:#2f2628;
  }
  .cashier-saving-body {
    font-size:.8rem;
    color:#7b675f;
    line-height:1.45;
  }
  @keyframes cashier-spin {
    from { transform:rotate(0deg); }
    to { transform:rotate(360deg); }
  }
  .cashier-startup-overlay {
    min-height:calc(100vh - 140px);
    display:flex; align-items:center; justify-content:center;
  }
  .cashier-startup-frame {
    width:min(1180px, 100%);
    background:linear-gradient(145deg, rgba(255,253,249,.96), rgba(248,240,231,.98));
    border:1px solid rgba(224,209,198,.9);
    border-radius:34px;
    box-shadow:0 30px 70px rgba(44,29,29,.16);
    padding:1rem;
  }
  .cashier-kicker {
    display:inline-flex; align-items:center; gap:.45rem;
    padding:.35rem .8rem; border-radius:999px;
    background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.14);
    font-size:.75rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em;
  }
  .cashier-session-ribbon {
    display:flex; gap:.6rem; flex-wrap:wrap;
  }
  .cashier-session-ribbon .cashier-chip {
    font-size:.78rem;
    padding:.4rem .75rem;
  }
  .cashier-side-note {
    border:1px solid rgba(225,210,199,.78);
    border-radius:20px;
    padding:1rem 1.1rem;
    background:linear-gradient(135deg,#fffaf7 0%,#fff 100%);
  }
  .cashier-product-stage {
    min-height:0;
    display:flex;
    flex-direction:column;
  }
  .cashier-stage-toolbar {
    display:grid;
    gap:.8rem;
    margin-bottom:1rem;
  }
  .cashier-filter-row {
    display:flex; gap:.6rem; flex-wrap:wrap;
  }
  .cashier-filter-pill {
    display:inline-flex; align-items:center; gap:.35rem;
    padding:.52rem .9rem; border-radius:999px; border:1px solid rgba(224,209,198,.9);
    background:#fffaf7; color:#6f5d57; font-size:.8rem; font-weight:800; cursor:pointer;
  }
  .cashier-filter-pill.active {
    background:linear-gradient(135deg,#8f3d33 0%, #cf624a 100%);
    border-color:transparent;
    color:#fff;
    box-shadow:0 12px 24px rgba(143,61,51,.18);
  }
  .cashier-filter-pill.muted {
    background:#f6f1ec;
    color:#8d7d76;
  }
  .cashier-order-scroll {
    min-height:0;
    overflow:auto;
    padding-right:.18rem;
    flex:1;
    display:flex;
    flex-direction:column;
  }
  .cashier-header-grid {
    display:grid;
    grid-template-columns:minmax(108px, 1.05fr) minmax(72px, 78px) minmax(86px, 108px);
    gap:.5rem;
    align-items:start;
  }
  .cashier-header-grid .cashier-customer-name-wrap,
  .cashier-header-grid .cashier-order-notes-wrap {
    grid-column:1 / -1;
  }
  .cashier-order-settings {
    border:1px solid rgba(225,210,199,.78);
    border-radius:18px;
    padding:.8rem;
    background:#fffdfb;
  }
  .cashier-order-settings .form-control,
  .cashier-order-settings .form-select {
    min-height:38px;
    font-size:.76rem;
    padding:.42rem .68rem;
  }
  .cashier-order-settings .form-label {
    font-size:.72rem;
  }
  .cashier-cart-footer {
    position:sticky;
    bottom:0;
    background:linear-gradient(180deg, rgba(255,255,255,.9) 0%, #fff 22%);
    padding-top:.72rem;
    margin-top:.72rem;
    border-top:1px solid rgba(228,216,207,.9);
  }
  .cashier-cart-footer .btn {
    min-height:42px;
    font-size:.87rem;
  }
  .cashier-cart-summary-line {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:.6rem;
  }
  .cashier-search-card,
  .cashier-header-card,
  .cashier-recent-card,
  .cashier-cart-card-shell {
    min-height:0;
  }
  .cashier-search-card .card-body,
  .cashier-recent-card .card-body,
  .cashier-cart-card-shell .card-body {
    display:flex;
    flex-direction:column;
    min-height:0;
  }
  #cashier_cart_empty.cashier-empty {
    flex:1;
    display:flex;
    align-items:center;
    justify-content:center;
  }
  .cashier-session-kpi {
    border:1px solid rgba(225,210,199,.78); border-radius:20px; padding:1rem 1.1rem;
    background:linear-gradient(135deg,#fffaf7 0%,#fff 100%);
    height:100%;
  }
  .cashier-session-summary {
    border:1px solid rgba(225,210,199,.78); border-radius:18px; padding:.85rem 1rem;
    background:#fffaf7;
  }
  .cashier-close-panel {
    border:1px solid rgba(225,210,199,.82);
    border-radius:20px;
    background:linear-gradient(180deg,#fffaf7 0%,#fff 100%);
    padding:1rem 1.05rem;
    height:100%;
  }
  .cashier-close-section-title {
    font-size:.78rem;
    font-weight:800;
    letter-spacing:.05em;
    text-transform:uppercase;
    color:#8a776d;
    margin-bottom:.55rem;
  }
  .cashier-close-summary-grid {
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:.7rem;
  }
  .cashier-close-summary-card {
    border:1px solid rgba(224,209,198,.76);
    border-radius:16px;
    background:#fffdfb;
    padding:.8rem .9rem;
  }
  .cashier-close-summary-card .label {
    display:block;
    font-size:.75rem;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:#8a776d;
    margin-bottom:.2rem;
  }
  .cashier-close-summary-card .value {
    font-size:1rem;
    font-weight:800;
    color:#3a2b2b;
  }
  .cashier-close-breakdown {
    border-top:1px solid rgba(229,216,206,.88);
    margin-top:1rem;
    padding-top:1rem;
  }
  .cashier-close-breakdown-list {
    display:grid;
    gap:.55rem;
  }
  .cashier-close-breakdown-row {
    border:1px solid rgba(224,209,198,.74);
    border-radius:14px;
    background:#fffdfb;
    padding:.68rem .8rem;
  }
  .cashier-close-breakdown-head {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:.8rem;
  }
  .cashier-close-breakdown-name {
    font-weight:700;
    color:#3a2b2b;
  }
  .cashier-close-breakdown-meta {
    display:flex;
    flex-wrap:wrap;
    gap:.45rem .7rem;
    margin-top:.35rem;
    font-size:.8rem;
    color:#7b6b63;
  }
  .cashier-close-denom-list {
    display:grid;
    gap:.55rem;
  }
  .cashier-close-denom-row {
    display:grid;
    grid-template-columns:minmax(0, 1fr) 96px 132px;
    gap:.65rem;
    align-items:center;
    border:1px solid rgba(224,209,198,.74);
    border-radius:14px;
    padding:.6rem .75rem;
    background:#fffdfb;
  }
  .cashier-close-denom-label {
    font-weight:700;
    color:#3a2b2b;
  }
  .cashier-close-denom-total {
    text-align:right;
    font-weight:700;
    color:#8f3d33;
  }
  .cashier-close-total-box {
    border:1px solid rgba(188,44,69,.14);
    border-radius:18px;
    background:#fff6f4;
    padding:.9rem 1rem;
  }
  .cashier-close-total-row {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:.75rem;
  }
  .cashier-close-total-row + .cashier-close-total-row {
    margin-top:.4rem;
  }
  .cashier-close-total-row strong {
    font-size:1rem;
    color:#3a2b2b;
  }
  .cashier-close-total-row.variance strong.negative { color:#b42318; }
  .cashier-close-total-row.variance strong.positive { color:#067647; }
  .cashier-close-empty {
    border:1px dashed rgba(224,209,198,.88);
    border-radius:14px;
    padding:.85rem .95rem;
    color:#8a776d;
    background:#fffdfb;
  }
  @media (max-width: 767.98px) {
    .cashier-close-summary-grid { grid-template-columns:1fr; }
    .cashier-close-denom-row { grid-template-columns:minmax(0, 1fr) 84px 110px; }
  }
  .cashier-readonly {
    background:#f7f3ef !important;
    border-color:#eadfd6 !important;
  }
  .cashier-reversal-line {
    border:1px solid rgba(224,209,198,.75); border-radius:14px; padding:.68rem .8rem; background:#fffaf7;
  }
  .cashier-reversal-line + .cashier-reversal-line { margin-top:.55rem; }
  .cashier-reversal-help {
    background:#fff7f2;
    border:1px solid rgba(224,209,198,.85);
    border-radius:14px;
    padding:.72rem .82rem;
    color:#755f56;
  }
  .cashier-reversal-toolbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:.6rem;
    flex-wrap:wrap;
  }
  .cashier-reversal-policy-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:.85rem; }
  .cashier-reversal-policy-card {
    border:1px solid rgba(224, 209, 198, .75);
    border-radius:16px;
    padding:.8rem .9rem;
    background:linear-gradient(135deg,#fffaf6 0%,#fff 100%);
    cursor:pointer;
  }
  .cashier-reversal-policy-card.active { border-color:#8f3d33; box-shadow:0 12px 24px rgba(143,61,51,.12); }
  .cashier-reversal-policy-title { font-weight:800; color:#3a2b2b; }
  .cashier-reversal-policy-note { font-size:.8rem; color:#7b6b63; }
  .cashier-reversal-section-title { font-size:.78rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:#8a776d; }
  .cashier-reversal-policy-wrap {
    border-top:1px solid rgba(229,216,206,.88);
    padding-top:1rem;
  }
  .cashier-reversal-policy-hint {
    border:1px solid rgba(224,209,198,.75);
    border-radius:14px;
    background:#fffdfb;
    padding:.75rem .9rem;
    font-size:.8rem;
    color:#7b6b63;
  }
  .cashier-reversal-item-head {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:.85rem;
  }
  .cashier-reversal-item-main {
    display:flex;
    align-items:center;
    gap:.6rem;
    min-width:0;
    flex:1;
  }
  .cashier-reversal-item-name {
    font-weight:800;
    color:#32292a;
    line-height:1.2;
  }
  .cashier-reversal-item-side {
    display:flex;
    align-items:center;
    gap:.45rem;
    flex:0 0 auto;
  }
  .cashier-reversal-item-side .form-control {
    width:88px;
    text-align:center;
    font-weight:700;
  }
  .cashier-reversal-extra-list {
    margin-top:.7rem;
    display:grid;
    gap:.48rem;
    padding-top:.7rem;
    border-top:1px dashed rgba(214,195,182,.9);
  }
  .cashier-reversal-extra-row {
    border:1px dashed rgba(214,195,182,.9);
    border-radius:14px;
    padding:.52rem .68rem;
    background:#fff;
  }
  .cashier-reversal-qty-input { width:78px; }
  .cashier-reversal-summary-note { font-size:.78rem; color:#8b7a70; }
  .cashier-extra-pill {
    display:inline-flex; align-items:center; gap:.35rem; margin:.2rem .35rem .2rem 0;
    padding:.24rem .58rem; border-radius:999px; background:#fff5ea; color:#8f4c17; font-size:.72rem; font-weight:700;
  }
  .cashier-cart-extra-summary {
    font-size:.74rem;
    color:#8e5d1d;
    margin-top:.2rem;
  }
  .cashier-extra-groups-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));
    gap:.75rem;
  }
  .cashier-extra-group {
    border:1px solid rgba(224,209,198,.75); border-radius:16px; padding:.75rem; background:#fffdfb;
  }
  .cashier-extra-group-head {
    display:flex; justify-content:space-between; align-items:flex-start; gap:.6rem; margin-bottom:.65rem;
  }
  .cashier-extra-group-title { font-weight:900; color:#34292b; font-size:.92rem; line-height:1.25; }
  .cashier-extra-group-meta { font-size:.72rem; color:#8a7a72; margin-top:.12rem; }
  .cashier-extra-group-badges {
    display:flex;
    gap:.35rem;
    flex-wrap:wrap;
    justify-content:flex-end;
  }
  .cashier-extra-item-list {
    display:grid;
    gap:.42rem;
  }
  .cashier-extra-item {
    border:1px solid rgba(234,223,214,.88); border-radius:14px; padding:.62rem .72rem; background:#fff;
    transition:border-color .15s ease, box-shadow .15s ease, background .15s ease;
  }
  .cashier-extra-item.selected {
    border-color:rgba(169, 77, 56, .55);
    box-shadow:0 10px 24px rgba(143,61,51,.1);
    background:#fff8f4;
  }
  .cashier-extra-item-row {
    display:grid;
    grid-template-columns:minmax(0, 1fr) 84px;
    gap:.55rem;
    align-items:start;
  }
  .cashier-extra-price-input {
    min-height:34px;
  }
  .cashier-extra-qty-note {
    font-size:.72rem;
    font-weight:700;
    color:#8e5d1d;
    background:#fff8eb;
    border:1px solid rgba(233, 205, 157, .9);
    border-radius:999px;
    padding:.18rem .55rem;
  }
  .cashier-extra-modal .modal-dialog {
    max-width:920px;
  }
  .cashier-extra-modal .modal-body {
    max-height:72vh;
    overflow:auto;
    padding-bottom:.4rem;
  }
  .cashier-extra-modal .modal-footer {
    position:sticky;
    bottom:0;
    background:#fff;
    border-top:1px solid rgba(225,210,199,.8);
    box-shadow:0 -10px 24px rgba(58,38,30,.06);
  }
  .cashier-recent-scroll {
    min-height:0;
    flex:1;
    overflow:auto;
    padding-right:.18rem;
  }
  @media (max-width: 991.98px) {
    .cashier-shell { height:auto; }
    .cashier-workbench { grid-template-columns:1fr; height:auto; }
    .cashier-cart-card { position:static; }
    .cashier-startup-overlay { min-height:auto; }
    .cashier-header-grid { grid-template-columns:1fr; }
    .cashier-product-grid { grid-template-columns:repeat(auto-fill,minmax(144px,1fr)); }
    .cashier-cart-editor-row { grid-template-columns:1fr; }
    .cashier-cart-price-breakdown { grid-template-columns:1fr; }
    .cashier-review-summary-grid { grid-template-columns:1fr 1fr; }
    .cashier-reversal-policy-grid { grid-template-columns:1fr; }
  }
  .cashier-catalog-chip-row {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:.45rem;
    margin-top:.4rem;
    flex-wrap:wrap;
  }
  .cashier-product-price {
    font-size:.84rem;
    font-weight:900;
    color:#2f2628;
  }
  .cashier-member-toolbar {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:.55rem;
  }
  .cashier-member-toolbar .btn {
    min-height:32px;
    font-size:.78rem;
    padding:.3rem .75rem;
  }
  .cashier-cart-line-bottom {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:.45rem;
    flex-wrap:nowrap;
    margin-top:.4rem;
  }
  .cashier-cart-line-left {
    display:flex;
    align-items:center;
    gap:.34rem;
    flex-wrap:wrap;
    min-width:0;
  }
  .cashier-cart-line-left .cashier-chip {
    font-size:.62rem;
    padding:.14rem .42rem;
  }
  .cashier-cart-line-left .btn {
    padding:.2rem .54rem;
    font-size:.72rem;
  }
  .cashier-cart-line-bottom .fw-bold {
    flex:0 0 auto;
    font-size:.88rem;
  }
  .cashier-cart-extra-count {
    font-size:.62rem;
    color:#875f2a;
    background:#fff7ea;
    border:1px solid rgba(239, 198, 112, .8);
    border-radius:999px;
    padding:.12rem .44rem;
    font-weight:800;
  }
  .cashier-recent-meta {
    display:grid;
    gap:.18rem;
    min-width:0;
  }
  .cashier-recent-top,
  .cashier-recent-bottom {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:.6rem;
    min-width:0;
  }
  .cashier-recent-bottom { margin-top:.42rem; }
  .cashier-recent-total {
    flex:0 0 auto;
    color:#2f2628;
    font-size:.88rem;
    font-weight:900;
    line-height:1.2;
    white-space:nowrap;
  }
  .cashier-recent-order-no {
    min-width:0;
    font-weight:800;
    color:#2f2628;
    font-size:.78rem;
    line-height:1.22;
    letter-spacing:-.015em;
    overflow-wrap:anywhere;
    word-break:break-word;
  }
  .cashier-recent-customer {
    font-size:.74rem;
    color:#4c3d3c;
    font-weight:700;
    line-height:1.2;
  }
  .cashier-recent-table {
    font-size:.72rem;
    color:#8a7a72;
    line-height:1.15;
  }
  @media (max-width:420px) {
    .cashier-recent-item { padding:.78rem .82rem; }
    .cashier-recent-top,
    .cashier-recent-bottom { gap:.45rem; }
    .cashier-recent-order-no { font-size:.74rem; }
    .cashier-recent-total { font-size:.82rem; }
  }
  .cashier-extra-modal .form-control,
  .cashier-extra-modal .form-select {
    min-height:38px;
    font-size:.86rem;
    padding:.45rem .68rem;
  }
  .cashier-extra-modal .form-label {
    font-size:.72rem;
  }
  .cashier-extra-modal .modal-title {
    font-size:1.1rem;
  }
  .cashier-extra-modal .modal-header {
    padding:.95rem 1rem .72rem;
  }
  .cashier-extra-modal .modal-footer .btn {
    min-height:40px;
    padding:.45rem 1rem;
    font-size:.9rem;
  }
  .cashier-extra-modal .cashier-order-settings {
    padding:.72rem;
    border-radius:16px;
  }
  .cashier-review-list {
    display:grid;
    gap:.7rem;
  }
  .cashier-review-item {
    border:1px solid rgba(225,210,199,.78);
    border-radius:16px;
    padding:.78rem .85rem;
    background:#fffaf7;
  }
  .cashier-review-extra {
    display:flex;
    gap:.32rem;
    flex-wrap:wrap;
    margin-top:.3rem;
  }
  .cashier-review-modal .modal-body {
    max-height:62vh;
    overflow:auto;
  }
  .cashier-review-modal .modal-footer {
    position:sticky;
    bottom:0;
    background:#fff;
    border-top:1px solid rgba(225,210,199,.8);
  }
  .cashier-review-summary {
    border:1px solid rgba(225,210,199,.78);
    border-radius:18px;
    background:#fffdfb;
    padding:.72rem .82rem;
  }
  .cashier-review-summary-grid {
    display:grid;
    grid-template-columns:repeat(5, minmax(0, 1fr));
    gap:.52rem;
  }
  .cashier-review-kpi-label {
    font-size:.68rem;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:#8a7a72;
    margin-bottom:.16rem;
  }
  .cashier-review-kpi-value {
    font-size:.78rem;
    font-weight:800;
    color:#33282a;
    line-height:1.25;
  }
  .cashier-info-modal .modal-dialog {
    max-width:480px;
  }
  .cashier-info-modal .modal-content {
    border-radius:22px;
  }
  .cashier-info-modal .modal-header,
  .cashier-info-modal .modal-footer {
    padding:.85rem 1rem;
  }
  .cashier-info-modal .modal-body {
    padding:.95rem 1rem .8rem;
  }
  .cashier-info-text {
    font-size:.92rem;
    color:#423536;
    line-height:1.45;
    white-space:pre-line;
  }
  .cashier-recon-modal .modal-dialog {
    max-width:720px;
  }
  .cashier-recon-modal .modal-content {
    border-radius:26px;
    overflow:hidden;
  }
  .cashier-recon-hero {
    border:1px solid rgba(165,15,36,.15);
    border-radius:20px;
    background:linear-gradient(135deg,#fff8f4 0%,#fff0dd 100%);
    padding:1rem;
  }
  .cashier-recon-icon {
    width:42px;
    height:42px;
    border-radius:16px;
    display:grid;
    place-items:center;
    color:#fff;
    background:linear-gradient(135deg,#a50f24,#f59e0b);
    box-shadow:0 10px 24px rgba(165,15,36,.18);
    flex:0 0 auto;
  }
  .cashier-recon-list {
    max-height:240px;
    overflow:auto;
    border:1px solid #f0dfd2;
    border-radius:18px;
    background:#fff;
  }
  .cashier-recon-item {
    display:grid;
    grid-template-columns: 1fr auto;
    gap:.75rem;
    padding:.72rem .9rem;
    border-bottom:1px solid #f5e7dc;
  }
  .cashier-recon-item:last-child {
    border-bottom:0;
  }
  .cashier-recon-item-title {
    font-weight:800;
    color:#37251f;
    line-height:1.2;
  }
  .cashier-recon-item-meta {
    color:#8b7569;
    font-size:.8rem;
  }
  .cashier-recon-reason {
    align-self:start;
    border-radius:999px;
    background:#fff3cd;
    color:#8a5b00;
    padding:.22rem .55rem;
    font-size:.72rem;
    font-weight:800;
    white-space:nowrap;
  }
  .cashier-print-failure-panel {
    border:1px solid rgba(194,69,52,.14);
    border-radius:18px;
    background:linear-gradient(180deg,#fff8f5 0%,#fff 100%);
    padding:1rem;
  }
  .cashier-print-failure-head {
    display:flex;
    gap:.75rem;
    align-items:flex-start;
    margin-bottom:.85rem;
  }
  .cashier-print-failure-icon {
    width:2.6rem;
    height:2.6rem;
    border-radius:999px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    background:#fde7e1;
    color:#bf4b38;
    font-size:1.2rem;
    flex:0 0 auto;
  }
  .cashier-print-failure-title {
    font-size:1rem;
    font-weight:800;
    color:#3f2d2a;
    margin-bottom:.15rem;
  }
  .cashier-print-failure-lead {
    font-size:.86rem;
    color:#7a625d;
    line-height:1.45;
  }
  .cashier-print-failure-list {
    display:grid;
    gap:.7rem;
  }
  .cashier-print-failure-item {
    border:1px solid rgba(191,167,160,.35);
    border-radius:14px;
    background:#fff;
    padding:.78rem .85rem;
  }
  .cashier-print-failure-name {
    font-size:.88rem;
    font-weight:800;
    color:#3b2b2b;
    margin-bottom:.18rem;
  }
  .cashier-print-failure-reason {
    font-size:.82rem;
    color:#735f5b;
    line-height:1.4;
  }
  .cashier-review-line-meta {
    display:flex;
    gap:.45rem;
    flex-wrap:wrap;
    margin-top:.2rem;
  }
  .cashier-review-line-meta .cashier-chip {
    font-size:.65rem;
    padding:.2rem .45rem;
  }
  .cashier-action-bar {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:.55rem;
  }
  .cashier-action-bar .cashier-action-wide {
    grid-column:1 / -1;
  }
  .payment-block { border:1px solid rgba(109,31,47,.10); border-radius:18px; background:#fff; padding:15px; box-shadow:0 12px 28px rgba(31,36,48,.05); }
  .payment-block-compact { padding:14px; }
  .payment-block-title { font-size:12px; letter-spacing:.08em; text-transform:uppercase; color:#8a7a72; margin-bottom:8px; font-weight:800; }
  .payment-inline-card { background:linear-gradient(180deg, #fff, #fbf7f1); border-color:rgba(109,31,47,.12) !important; box-shadow:0 8px 18px rgba(31,36,48,.05); }
  .selected-customer-card { border-radius:16px; }
  .cashier-payment-btn-primary {
    background:#943f35;
    border-color:#943f35;
    color:#fff;
    font-weight:800;
  }
  .cashier-payment-btn-primary:hover,
  .cashier-payment-btn-primary:focus {
    background:#7f342c;
    border-color:#7f342c;
    color:#fff;
  }
  .cashier-payment-btn-accent {
    background:#f8e7d9;
    border-color:#d39a73;
    color:#8a4722;
    font-weight:800;
  }
  .cashier-payment-btn-accent:hover,
  .cashier-payment-btn-accent:focus {
    background:#f1d8c3;
    border-color:#c7885b;
    color:#7a3d1b;
  }
  .cashier-payment-btn-neutral {
    background:#f4ece7;
    border-color:#d8c3b7;
    color:#5c4b45;
    font-weight:700;
  }
  .cashier-payment-btn-neutral:hover,
  .cashier-payment-btn-neutral:focus {
    background:#eadfd8;
    border-color:#ccb3a5;
    color:#4f403b;
  }
  .cashier-payment-btn-danger {
    background:#fff1f0;
    border-color:#ef8f87;
    color:#c53d32;
    font-weight:800;
  }
  .cashier-payment-btn-danger:hover,
  .cashier-payment-btn-danger:focus {
    background:#ffe4e2;
    border-color:#e27168;
    color:#a92e25;
  }
  .cashier-payment-btn-success {
    background:#39b54a;
    border-color:#39b54a;
    color:#fff;
    font-weight:800;
    box-shadow:0 10px 22px rgba(57,181,74,.18);
  }
  .cashier-payment-btn-success:hover,
  .cashier-payment-btn-success:focus {
    background:#2e9a3d;
    border-color:#2e9a3d;
    color:#fff;
  }
  .suggest-box { position:relative; }
  .suggest-box .list-group {
    position:absolute;
    top:100%;
    left:0;
    right:0;
    z-index:1060;
    max-height:300px;
    overflow:auto;
    background:#fff;
    border:1px solid rgba(201, 183, 168, .72);
    border-radius:16px;
    box-shadow:0 16px 36px rgba(61, 38, 27, .18);
  }
  .suggest-box .list-group-item {
    background:#fff;
    border:0;
    border-bottom:1px solid rgba(232,220,210,.9);
    padding:.9rem 1rem;
  }
  .suggest-box .list-group-item:last-child { border-bottom:0; }
  .suggest-box .list-group-item:hover,
  .suggest-box .list-group-item:focus { background:#fff7f2; }
  .existing-payment-chip { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; background:rgba(109,31,47,.10); color:#943f35; font-size:11px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
  .cashier-payment-layout-summary { display:flex; flex-direction:column; gap:12px; }
  .cashier-payment-summary-list { display:grid; gap:.32rem; font-size:.92rem; color:#5a4a45; }
  .cashier-payment-summary-row { display:flex; justify-content:space-between; gap:1rem; }
  .cashier-payment-summary-row.total { color:#943f35; font-size:1.15rem; font-weight:800; }
  .cashier-payment-summary-row.balance {
    margin-top:.45rem;
    padding-top:.65rem;
    border-top:1px solid rgba(223, 208, 198, .9);
    font-size:1rem;
    font-weight:800;
    color:#2f2628;
  }
  .cashier-payment-note {
    font-size:.76rem;
    color:#7b6761;
  }
  .cashier-payment-method-row { margin-bottom:.75rem; }
  .cashier-payment-method-row:last-child { margin-bottom:0; }
  .cashier-payment-method-row.is-active {
    border-color:rgba(148, 63, 53, .45) !important;
    box-shadow:0 0 0 2px rgba(148, 63, 53, .08), 0 8px 18px rgba(31,36,48,.05);
  }
  @media (max-width: 767.98px) {
    .cashier-payment-layout-summary { margin-top:1rem; }
  }
  .cashier-recent-footer {
    position:sticky;
    bottom:0;
    padding-top:.72rem;
    margin-top:.72rem;
    background:linear-gradient(180deg, rgba(255,255,255,.9) 0%, #fff 22%);
    border-top:1px solid rgba(228,216,207,.9);
  }
  .cashier-product-card .cashier-chip {
    font-size:.63rem;
    padding:.2rem .5rem;
  }
  .cashier-member-box {
    padding:.72rem .82rem;
  }
  .cashier-member-empty,
  .cashier-member-meta,
  .cashier-cart-item-sub {
    font-size:.76rem;
  }
</style>

<div class="container-fluid px-0 py-2">
  <?php if (!$activeSession): ?>
    <div class="cashier-startup-overlay">
      <div class="cashier-startup-frame">
        <div class="card cashier-launch-card">
          <div class="card-body p-4 p-lg-5">
        <div class="row justify-content-center">
          <div class="col-lg-8 col-xl-7">
            <div class="card border-0 shadow-lg" style="border-radius:24px;">
              <div class="card-body p-4">
                <div class="cashier-kicker mb-2"><i class="ri-door-open-line"></i>Buka Kasir Dulu</div>
                <div class="cashier-panel-title mb-1">Mulai sesi kasir</div>
                <div class="cashier-panel-note mb-3">Pilih outlet, device, lalu masukkan modal awal. Setelah sesi aktif, outlet dan device transaksi akan otomatis terkunci mengikuti sesi ini.</div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">Outlet</label>
                    <select class="form-select" id="cashier_launch_outlet" <?php echo empty($outlets) ? 'disabled' : ''; ?>>
                      <option value="">Pilih Outlet</option>
                      <?php foreach ($outlets as $outlet): ?>
                        <option value="<?php echo (int)$outlet['id']; ?>" <?php echo $defaultLaunchOutletId === (int)$outlet['id'] ? 'selected' : ''; ?>><?php echo html_escape((string)$outlet['outlet_name']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">Device / Terminal</label>
                    <select class="form-select" id="cashier_launch_terminal" <?php echo empty($outlets) ? 'disabled' : ''; ?>>
                      <option value="">Pilih Device</option>
                      <?php foreach ($terminals as $terminal): ?>
                        <option value="<?php echo (int)$terminal['id']; ?>" data-outlet-id="<?php echo (int)($terminal['outlet_id'] ?? 0); ?>" <?php echo $defaultLaunchTerminalId === (int)$terminal['id'] ? 'selected' : ''; ?>><?php echo html_escape((string)$terminal['terminal_name']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">Modal Awal</label>
                    <input type="number" min="0" step="1000" class="form-control" id="cashier_launch_opening_cash" placeholder="Mis. 200000" value="<?php echo (int)$defaultLaunchOpeningCash; ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">Catatan Buka Kasir</label>
                    <input type="text" class="form-control" id="cashier_launch_notes" placeholder="Opsional">
                  </div>
                  <div class="col-12 d-grid">
                    <button type="button" class="btn btn-primary btn-lg" id="cashier_open_btn" <?php echo empty($outlets) ? 'disabled' : ''; ?>>Buka Kasir</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (empty($outlets)): ?>
    <div class="alert alert-warning border-0 shadow-sm mt-3">
      Outlet POS lokal belum tersedia. Jalankan setup outlet POS lokal dulu sebelum mulai memakai layar kasir.
    </div>
  <?php endif; ?>

  <?php if ($incomingChannels !== [] || $canViewPosPrinters): ?>
  <div class="cashier-quick-nav mt-3" id="cashier_quick_nav" aria-label="Navigasi POS">
    <?php if ($incomingChannels !== []): ?>
      <button type="button" class="cashier-quick-action is-primary" id="cashier_incoming_open_btn" <?php echo $incomingCashierOutletId > 0 ? '' : 'disabled'; ?>>
        <i class="ri-notification-3-line" aria-hidden="true"></i>
        <span>Order Masuk</span>
        <span class="cashier-quick-badge" id="cashier_incoming_total_badge">0</span>
      </button>
      <button type="button" class="cashier-quick-action" id="cashier_incoming_sync_btn" <?php echo $incomingCashierOutletId > 0 ? '' : 'disabled'; ?>>
        <i class="ri-refresh-line" aria-hidden="true"></i>
        <span>Refresh Order</span>
      </button>
      <?php foreach ($incomingChannels as $channel): ?>
        <a class="cashier-quick-link" href="<?php echo html_escape((string)$channel['module_url']); ?>">
          <i class="<?php echo html_escape((string)$channel['icon']); ?>" aria-hidden="true"></i>
          <span><?php echo html_escape((string)$channel['label']); ?></span>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($canViewPosPrinters): ?>
      <a class="cashier-quick-link" href="<?php echo site_url('pos/printers'); ?>">
        <i class="ri-printer-line" aria-hidden="true"></i><span>Printer</span>
      </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="cashier-shell mt-3<?php echo $activeSession ? '' : ' d-none'; ?>" id="cashier_workspace">
    <div class="cashier-workbench">
      <div class="cashier-column">
        <div class="card cashier-card cashier-recent-card">
          <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
              <div>
                <div class="cashier-panel-title">Order Aktif Sesi Ini</div>
              </div>
              <div class="cashier-recent-status-bar">
                <button type="button" class="btn btn-sm btn-outline-primary cashier-status-tab" data-status="DRAFT">Draft</button>
                <button type="button" class="btn btn-sm btn-outline-primary cashier-status-tab" data-status="CONFIRMED">Confirmed</button>
                <button type="button" class="btn btn-sm btn-outline-primary cashier-status-tab active" data-status="ALL">Semua</button>
              </div>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-12">
                <input id="cashier_recent_q" class="form-control" placeholder="Cari kode transaksi / customer / meja">
              </div>
              <div class="col-12">
                <select id="cashier_recent_limit" class="form-select">
                  <option value="10">10</option>
                  <option value="20" selected>20</option>
                  <option value="50">50</option>
                </select>
              </div>
            </div>
            <div class="cashier-recent-scroll">
              <div id="cashier_recent_list" class="cashier-recent-list"></div>
              <div id="cashier_recent_empty" class="cashier-empty d-none">Belum ada order pada filter ini.</div>
            </div>
            <div class="cashier-recent-footer d-grid gap-2">
              <button type="button" class="btn btn-outline-primary w-100" id="cashier_reprint_order_btn">Cetak Ulang Order Dipilih</button>
              <button type="button" class="btn btn-outline-danger w-100" id="cashier_close_btn">Tutup Kasir</button>
            </div>
          </div>
        </div>
      </div>

      <div class="cashier-column">
        <div class="card cashier-card cashier-search-card cashier-product-stage">
          <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
              <div>
                <div class="cashier-panel-title">Produk POS</div>
              </div>
            </div>
            <input type="hidden" id="cashier_outlet_id" value="<?php echo (int)($activeSession['outlet_id'] ?? 0); ?>">
            <input type="hidden" id="cashier_terminal_id" value="<?php echo (int)($activeSession['terminal_id'] ?? 0); ?>">
            <div class="cashier-stage-toolbar">
              <div class="row g-2">
                <div class="col-lg-7">
                  <input type="text" class="form-control" id="cashier_product_search" placeholder="Cari produk, bundle, atau kode item di sini...">
                </div>
                <div class="col-lg-5">
                  <div class="cashier-meta-box h-100 d-flex align-items-center justify-content-between gap-3">
                    <div>
                      <div class="small text-muted">Katalog Aktif</div>
                      <div class="fw-bold" id="cashier_catalog_hint">Semua produk kasir</div>
                    </div>
                    <div class="cashier-chip info" id="cashier_catalog_count">0 item</div>
                  </div>
                </div>
              </div>
              <div>
                <div class="small text-muted mb-2">Filter Divisi</div>
                <div class="cashier-filter-row" id="cashier_division_filters"></div>
              </div>
            </div>
            <div class="cashier-search-result mt-2" id="cashier_catalog_result">
              <div class="cashier-empty">Katalog kasir akan tampil otomatis setelah sesi aktif.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="cashier-column">
        <div class="card cashier-card cashier-cart-card cashier-cart-card-shell">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
              <div>
                <div class="cashier-panel-title">Header Order & Keranjang</div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="cashier_reset_order">Reset</button>
            </div>
            <div class="cashier-order-scroll">
              <div class="cashier-order-settings mb-3">
                <div class="mb-3">
                  <label class="form-label small text-muted mb-1">Cari Member</label>
                  <div class="cashier-search-wrap">
                    <input type="text" class="form-control" id="cashier_member_search" placeholder="Cari customer, member, atau no HP...">
                    <div class="cashier-search-dropdown d-none" id="cashier_member_result"></div>
                  </div>
                </div>
                <div class="cashier-member-box mb-3" id="cashier_member_selected">
                  <div class="cashier-member-empty">Walk in customer. Transaksi ini belum memakai member.</div>
                </div>
                <div class="cashier-header-grid">
                  <div class="cashier-customer-name-wrap">
                    <label class="form-label small text-muted mb-1">Nama Customer</label>
                    <input type="text" class="form-control" id="cashier_customer_name" placeholder="Nama untuk walk in / cetak">
                    <div class="small text-muted mt-1">Dipakai untuk customer non-member dan tampilan cetak. Jika member dipilih, nama ini mengikuti data member.</div>
                  </div>
                  <div>
                    <label class="form-label small text-muted mb-1">Sales Channel</label>
                    <select class="form-select" id="cashier_sales_channel">
                      <?php if (empty($salesChannels)): ?>
                        <option value="">Walk In</option>
                      <?php else: ?>
                        <?php foreach ($salesChannels as $channel): ?>
                          <option
                            value="<?php echo (int)($channel['id'] ?? 0); ?>"
                            data-service-default="<?php echo html_escape((string)($channel['service_type_default'] ?? 'DINE_IN')); ?>"
                            data-allowed-types="<?php echo html_escape(implode(',', (array)($channel['allowed_service_type_list'] ?? []))); ?>"
                            <?php echo ((int)($channel['id'] ?? 0) === $defaultSalesChannelId) ? 'selected' : ''; ?>
                          >
                            <?php echo html_escape((string)($channel['channel_name'] ?? '-')); ?>
                          </option>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </select>
                  </div>
                  <div>
                    <label class="form-label small text-muted mb-1">Guest</label>
                    <input type="number" min="1" step="1" class="form-control" id="cashier_guest_count" value="1">
                  </div>
                  <div>
                    <label class="form-label small text-muted mb-1">Nomor Meja</label>
                    <input type="text" class="form-control" id="cashier_table_no" placeholder="A-01">
                  </div>
                  <div class="cashier-order-notes-wrap">
                    <label class="form-label small text-muted mb-1">Catatan Order</label>
                    <input type="text" class="form-control" id="cashier_notes" placeholder="Meja, label, request kasir">
                  </div>
                  <div class="d-none">
                    <label class="form-label small text-muted mb-1">Service Type</label>
                    <select class="form-select" id="cashier_service_type">
                      <option value="DINE_IN">Dine In</option>
                      <option value="TAKE_AWAY">Take Away</option>
                      <option value="DELIVERY">Delivery</option>
                      <option value="PICKUP">Pick Up</option>
                    </select>
                  </div>
                </div>
              </div>

              <div id="cashier_cart_list" class="cashier-cart-list"></div>
              <div id="cashier_cart_empty" class="cashier-empty">Belum ada item di keranjang. Pilih produk dari panel tengah.</div>
            </div>
            <div class="cashier-cart-footer">
              <div class="cashier-cart-summary-line mb-2">
                <div class="small text-muted">Grand Total</div>
                <div class="cashier-cart-total" id="cashier_grand_total">Rp 0</div>
              </div>
              <div class="cashier-mini-note mb-3" id="cashier_summary_info">Belum ada baris item</div>
              <div class="cashier-action-bar cashier-cart-actions">
                <button type="button" class="btn btn-outline-dark cashier-action-wide" id="cashier_preview_reversal" disabled>Preview Void</button>
                <button type="button" class="btn btn-outline-danger cashier-action-wide" id="cashier_delete_draft" disabled>Hapus Draft</button>
                <button type="button" class="btn btn-success cashier-action-wide" id="cashier_open_payment" disabled>Payment</button>
                <button type="button" class="btn btn-outline-primary" id="cashier_save_draft" <?php echo empty($activeSession) ? 'disabled' : ''; ?>>Simpan Draft</button>
                <button type="button" class="btn btn-primary" id="cashier_confirm_order" <?php echo empty($activeSession) ? 'disabled' : ''; ?>>Simpan Transaksi</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="cashierReversalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Preview Void POS</h5>
          <div class="small text-muted" id="cashier_reversal_meta">Order belum dipilih.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning border-0 d-none" id="cashier_reversal_empty_hint">Snapshot void belum tersedia untuk order ini.</div>
        <div class="cashier-reversal-help mb-3">
          <div class="fw-semibold mb-1">Void hanya untuk order POS yang belum dibayar.</div>
          <div class="small mb-0">Pilih produk untuk void penuh atau sebagian. Jika produk punya extra, pilih produk berarti extra ikut otomatis. Untuk void extra saja, kosongkan produk lalu centang extra yang ingin dibatalkan.</div>
        </div>
        <div class="cashier-reversal-toolbar mb-2">
          <div class="cashier-reversal-summary-note">Default kosong. Centang item yang memang akan di-void.</div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="cashier_reversal_check_all">Cek Semua</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="cashier_reversal_uncheck_all">Uncek Semua</button>
          </div>
        </div>
        <div id="cashier_reversal_lines" class="mb-3"></div>
        <div class="cashier-reversal-policy-wrap">
          <div class="cashier-reversal-section-title mb-2">Kebijakan Void</div>
          <div class="cashier-reversal-policy-grid mb-3">
            <label class="cashier-reversal-policy-card active" id="cashier_reversal_return_card">
              <input class="d-none" type="radio" name="cashier_reversal_policy" id="cashier_reversal_return" value="RETURN_TO_STOCK" checked>
              <div class="cashier-reversal-policy-title">Kembalikan ke stok</div>
            </label>
            <label class="cashier-reversal-policy-card" id="cashier_reversal_adjust_card">
              <input class="d-none" type="radio" name="cashier_reversal_policy" id="cashier_reversal_adjust" value="ADJUSTMENT_ONLY">
              <div class="cashier-reversal-policy-title">Jangan kembalikan ke stok</div>
            </label>
          </div>
          <div class="row g-3">
            <div class="col-md-6 d-none" id="cashier_reversal_adjustment_wrap">
              <label class="form-label small text-muted mb-1">Tipe Adjustment</label>
              <select class="form-select" id="cashier_reversal_adjustment">
                <option value="NONE">Pilih tipe adjustment...</option>
                <option value="AUTO_WASTE">Waste otomatis</option>
                <option value="AUTO_SPOIL">Spoil otomatis</option>
                <option value="AUTO_ADJUSTMENT">Penyesuaian otomatis</option>
              </select>
              <div class="small text-muted mt-1">Wajib dipilih jika stok bahan tidak dikembalikan.</div>
            </div>
            <div class="col-md-6 d-none" id="cashier_reversal_reason_wrap">
              <label class="form-label small text-muted mb-1">Alasan Void</label>
              <select class="form-select" id="cashier_reversal_reason_code">
                <option value="">Pilih alasan...</option>
              </select>
              <input type="text" class="form-control mt-2 d-none" id="cashier_reversal_reason_other" placeholder="Tulis alasan lainnya">
              <div class="small text-muted mt-1" id="cashier_reversal_reason_hint"></div>
            </div>
            <div class="col-12">
              <label class="form-label small text-muted mb-1">Catatan Audit</label>
              <textarea class="form-control" id="cashier_reversal_reason" rows="2" placeholder="Catatan tambahan untuk audit void ini (opsional)"></textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-danger" id="cashier_save_void">Simpan Void</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="cashierPaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Menyelesaikan Penjualan</h5>
          <div class="small text-muted" id="cashier_payment_meta">Pilih metode pembayaran untuk transaksi aktif.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 align-items-start">
          <div class="col-xl-7">
            <div class="d-flex flex-column gap-3">
              <div class="payment-block payment-block-compact">
                <div class="payment-block-title">Voucher &amp; Potongan</div>
                <div class="row g-2 align-items-start">
                  <div class="col-lg-7">
                    <label class="form-label">Voucher Database</label>
                    <div class="input-group">
                      <input type="text" class="form-control" id="cashier_payment_voucher_search" placeholder="Input kode / nama voucher lalu cek">
                      <button type="button" class="btn cashier-payment-btn-primary" id="cashier_payment_voucher_check">Cek</button>
                    </div>
                    <div class="small text-muted mt-1" id="cashier_payment_voucher_status">Input kode voucher lalu klik cek.</div>
                    <div class="suggest-box mt-1">
                      <div id="cashier_payment_voucher_suggestions" class="list-group mt-1" style="display:none;"></div>
                    </div>
                  </div>
                  <div class="col-lg-5">
                    <label class="form-label">Info Member</label>
                    <div class="selected-customer-card border rounded p-2 payment-inline-card">
                      <div class="fw-semibold" id="cashier_payment_customer_name">Walk in</div>
                      <div class="small text-muted mt-1" id="cashier_payment_member_balance">Point 0 | Stamp 0</div>
                      <div class="small text-muted" id="cashier_payment_voucher_count">0 voucher tersedia</div>
                    </div>
                    <div id="cashier_payment_deposit_card" class="selected-customer-card border rounded p-2 mt-2 payment-inline-card d-none">
                      <div class="fw-semibold">Deposit / DP Member</div>
                      <div class="small text-muted mt-1" id="cashier_payment_deposit_meta">Belum ada DP yang bisa dipakai.</div>
                      <div class="small mt-2" id="cashier_payment_deposit_rows"></div>
                    </div>
                  </div>
                </div>
                <div id="cashier_payment_selected_voucher" class="selected-customer-card border rounded p-2 mt-2 d-none payment-inline-card" data-selection="" data-discount="0">
                  <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                      <div class="fw-semibold" id="cashier_payment_selected_voucher_name"></div>
                      <div class="text-muted small" id="cashier_payment_selected_voucher_meta"></div>
                    </div>
                    <button type="button" class="btn btn-sm cashier-payment-btn-neutral" id="cashier_payment_clear_voucher">Hapus</button>
                  </div>
                  <div class="text-muted small mt-1" id="cashier_payment_selected_voucher_message"></div>
                </div>
              </div>

              <div class="payment-block">
                <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                  <div class="payment-block-title mb-0">Metode Pembayaran</div>
                  <button type="button" class="btn btn-sm cashier-payment-btn-accent" id="cashier_payment_add_row">+ Tambah Metode</button>
                </div>
                <div id="cashier_payment_rows"></div>
                <div class="d-flex flex-wrap gap-2 mt-2 mb-2">
                  <button type="button" class="btn btn-sm cashier-payment-btn-neutral payment-quick-amount" data-mode="remaining">Pas</button>
                  <button type="button" class="btn btn-sm cashier-payment-btn-neutral payment-quick-amount" data-amount="10000">10K</button>
                  <button type="button" class="btn btn-sm cashier-payment-btn-neutral payment-quick-amount" data-amount="20000">20K</button>
                  <button type="button" class="btn btn-sm cashier-payment-btn-neutral payment-quick-amount" data-amount="50000">50K</button>
                  <button type="button" class="btn btn-sm cashier-payment-btn-neutral payment-quick-amount" data-amount="100000">100K</button>
                </div>
                <div class="cashier-payment-note mt-2" id="cashier_payment_total_hint">Nominal pembayaran bisa kurang untuk simpan bayar sebagian. Kembalian hanya berlaku untuk tunai tunggal.</div>
              </div>

              <div class="payment-block payment-block-compact">
                <div class="payment-block-title">Catatan Payment</div>
                <textarea class="form-control" id="cashier_payment_notes" rows="2" placeholder="Opsional"></textarea>
              </div>
            </div>
          </div>

          <div class="col-xl-5">
            <div class="cashier-payment-layout-summary">
              <div class="payment-block payment-block-compact">
                <div class="payment-block-title">Ringkasan Pembayaran</div>
                <div class="cashier-payment-summary-list">
                  <div class="cashier-payment-summary-row"><span>Subtotal</span><strong id="cashier_payment_base_total">Rp 0</strong></div>
                  <div class="cashier-payment-summary-row"><span>Voucher / Potongan</span><strong id="cashier_payment_voucher_total">Rp 0</strong></div>
                  <div class="cashier-payment-summary-row"><span>Pakai DP</span><strong id="cashier_payment_deposit_total">Rp 0</strong></div>
                  <div class="cashier-payment-summary-row"><span>Sudah Dibayar</span><strong id="cashier_payment_paid_total">Rp 0</strong></div>
                  <div class="cashier-payment-summary-row total"><span>Total Tagihan</span><strong id="cashier_payment_grand_total">Rp 0</strong></div>
                  <div class="cashier-payment-summary-row"><span>Dibayar Sekarang</span><strong id="cashier_payment_entered_total">Rp 0</strong></div>
                  <div class="cashier-payment-summary-row"><span>Kembalian</span><strong id="cashier_payment_change_total">Rp 0</strong></div>
                  <div class="cashier-payment-summary-row balance"><span>Sisa Setelah Payment</span><strong id="cashier_payment_remaining_total">Rp 0</strong></div>
                </div>
              </div>

              <div class="payment-block payment-block-compact">
                <div class="payment-block-title">Informasi Order</div>
                <div class="cashier-payment-note mb-1">Order aktif</div>
                <div class="fw-semibold" id="cashier_payment_order_no">-</div>
                <div class="cashier-payment-note mt-2">Sisa tagihan saat ini</div>
                <div class="fw-bold fs-5" id="cashier_payment_due_total">Rp 0</div>
                <div class="cashier-payment-note mt-2" id="cashier_payment_voucher_hint">Belum ada voucher dipilih.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn cashier-payment-btn-neutral" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn cashier-payment-btn-success" id="cashier_submit_payment">Simpan Payment</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="cashierCloseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Tutup Kasir</h5>
          <div class="small text-muted">Preview pendapatan shift aktif, hitung pecahan tunai, lalu simpan dan cetak slip kasir.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-lg-7">
            <div class="cashier-close-panel">
              <div class="cashier-close-section-title">Preview Pendapatan</div>
              <div class="small text-muted mb-3" id="cashier_close_shift_meta">Memuat shift aktif...</div>
              <div class="cashier-close-summary-grid">
                <div class="cashier-close-summary-card"><span class="label">Total Order</span><div class="value" id="cashier_close_total_orders">0</div></div>
                <div class="cashier-close-summary-card"><span class="label">Penjualan Bersih</span><div class="value" id="cashier_close_net_sales">Rp 0</div></div>
                <div class="cashier-close-summary-card"><span class="label">Cash Sales</span><div class="value" id="cashier_close_cash_sales">Rp 0</div></div>
                <div class="cashier-close-summary-card"><span class="label">Non Cash</span><div class="value" id="cashier_close_non_cash_sales">Rp 0</div></div>
                <div class="cashier-close-summary-card"><span class="label">Deposit Receipt</span><div class="value" id="cashier_close_deposit_sales">Rp 0</div></div>
                <div class="cashier-close-summary-card"><span class="label">Refund</span><div class="value" id="cashier_close_refund_total">Rp 0</div></div>
                <div class="cashier-close-summary-card"><span class="label">Void</span><div class="value" id="cashier_close_void_total">Rp 0</div></div>
                <div class="cashier-close-summary-card"><span class="label">Expected Cash</span><div class="value" id="cashier_close_expected_cash">Rp 0</div></div>
              </div>

              <div class="cashier-close-breakdown">
                <div class="cashier-close-section-title">Per Metode Pembayaran</div>
                <div class="cashier-close-breakdown-list" id="cashier_close_method_summary">
                  <div class="cashier-close-empty">Belum ada data metode pembayaran.</div>
                </div>
              </div>

              <div class="cashier-close-breakdown">
                <div class="cashier-close-section-title">Per Rekening</div>
                <div class="cashier-close-breakdown-list" id="cashier_close_account_summary">
                  <div class="cashier-close-empty">Belum ada data rekening.</div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="cashier-close-panel">
              <div class="cashier-close-section-title">Hitung Pecahan Tunai</div>
              <div class="small text-muted mb-3">Isi jumlah lembar atau keping per pecahan. Kas aktual akan dihitung otomatis dari total pecahan.</div>
              <div class="cashier-close-denom-list" id="cashier_close_denom_rows"></div>

              <div class="cashier-close-total-box mt-3">
                <div class="cashier-close-total-row"><span>Kas aktual dari pecahan</span><strong id="cashier_close_actual_cash_text">Rp 0</strong></div>
                <div class="cashier-close-total-row"><span>Expected cash shift</span><strong id="cashier_close_expected_cash_text">Rp 0</strong></div>
                <div class="cashier-close-total-row variance"><span>Selisih</span><strong id="cashier_close_variance_text">Rp 0</strong></div>
              </div>

              <div class="mt-3">
                <label class="form-label small text-muted mb-1">Kas Aktual</label>
                <input type="number" min="0" step="500" class="form-control cashier-readonly" id="cashier_close_actual_cash" placeholder="Otomatis dari pecahan" readonly>
              </div>
              <div class="mt-3">
                <label class="form-label small text-muted mb-1">Catatan Penutupan</label>
                <textarea class="form-control" id="cashier_close_notes" rows="3" placeholder="Opsional"></textarea>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" id="cashier_submit_close">Tutup Shift</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade cashier-extra-modal" id="cashierExtraModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Atur Extra Produk</h5>
          <div class="small text-muted" id="cashier_extra_meta">Pilih extra yang ingin disertakan ke line produk ini.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="cashier-order-settings mb-3">
          <div class="row g-3 align-items-end">
            <div class="col-lg-6">
              <label class="form-label small text-muted mb-1">Produk</label>
              <input type="text" class="form-control cashier-readonly" id="cashier_extra_product_name" readonly>
            </div>
            <div class="col-lg-2 col-md-3">
              <label class="form-label small text-muted mb-1">Qty</label>
              <input type="number" min="1" step="1" class="form-control" id="cashier_extra_line_qty" value="1">
            </div>
            <div class="col-lg-2 col-md-3">
              <label class="form-label small text-muted mb-1">Harga Dasar</label>
              <input type="text" class="form-control cashier-readonly" id="cashier_extra_base_price" readonly>
            </div>
            <div class="col-lg-2 col-12">
              <label class="form-label small text-muted mb-1">Ringkas</label>
              <div class="cashier-chip info w-100 justify-content-center" id="cashier_extra_group_count">0 group</div>
            </div>
            <div class="col-12">
              <label class="form-label small text-muted mb-1">Catatan Line</label>
              <input type="text" class="form-control" id="cashier_extra_line_note" placeholder="Contoh: less ice, meja pojok, tanpa sedotan">
            </div>
          </div>
        </div>
        <div id="cashier_extra_groups"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="cashier_save_extra">Simpan ke Keranjang</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade cashier-review-modal" id="cashierReviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1" id="cashier_review_title">Review Order</h5>
          <div class="small text-muted" id="cashier_review_meta">Periksa order sebelum disimpan.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="cashier-review-summary mb-3">
          <div class="cashier-review-summary-grid">
            <div><div class="cashier-review-kpi-label">Channel</div><div class="cashier-review-kpi-value" id="cashier_review_channel">-</div></div>
            <div><div class="cashier-review-kpi-label">Guest</div><div class="cashier-review-kpi-value" id="cashier_review_guest">1</div></div>
            <div><div class="cashier-review-kpi-label">Meja</div><div class="cashier-review-kpi-value" id="cashier_review_table_no">-</div></div>
            <div><div class="cashier-review-kpi-label">Customer</div><div class="cashier-review-kpi-value" id="cashier_review_member">Walk in</div></div>
            <div><div class="cashier-review-kpi-label">Catatan</div><div class="cashier-review-kpi-value" id="cashier_review_notes">-</div></div>
          </div>
        </div>
        <div id="cashier_review_list" class="cashier-review-list"></div>
        <div class="cashier-review-summary mt-3">
          <div class="d-flex justify-content-between align-items-center gap-3">
            <div>
              <div class="small text-muted">Grand Total</div>
              <div class="fw-bold fs-4" id="cashier_review_total">Rp 0</div>
            </div>
            <div class="text-end small text-muted" id="cashier_review_hint">Draft akan disimpan setelah review.</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="cashier_review_submit">Simpan</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="cashierOrderReprintModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Cetak Ulang Order Aktif</h5>
          <div class="small text-muted" id="cashier_order_reprint_meta">Pilih satu order aktif dulu dari panel kiri.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small text-muted mb-1">Printer / Divisi Tujuan</label>
          <select class="form-select" id="cashier_order_reprint_printer">
            <option value="">Semua printer / divisi aktif</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small text-muted mb-1">Cakupan Item</label>
          <select class="form-select" id="cashier_order_reprint_scope">
            <option value="LATEST">Pesanan baru saja</option>
            <option value="ALL">Semua pesanan</option>
          </select>
        </div>
        <div class="small text-muted" id="cashier_order_reprint_hint">Gunakan mode item terbaru bila ingin mencetak ulang snapshot paling akhir tanpa mengulang seluruh order.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="cashier_order_reprint_submit">Kirim ke Printer</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade cashier-recon-modal" id="cashierReconGateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1" id="cashier_recon_title">Daily Recon belum lengkap</h5>
          <div class="small text-muted" id="cashier_recon_subtitle">Kasir belum bisa diproses sebelum checkpoint recon selesai.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="cashier-recon-hero d-flex gap-3 align-items-start mb-3">
          <div class="cashier-recon-icon"><i class="ri-shield-check-line"></i></div>
          <div>
            <div class="fw-bold text-danger mb-1">Gate Daily Recon aktif</div>
            <div class="text-muted" id="cashier_recon_message">Mohon selesaikan recon bahan baku dan component terlebih dahulu.</div>
          </div>
        </div>
        <div class="cashier-recon-list" id="cashier_recon_missing_list"></div>
      </div>
      <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-outline-danger" href="<?php echo site_url('inventory/stock/daily-recon/division'); ?>" target="_blank" rel="noopener noreferrer">
            Buka Recon Bahan Baku
          </a>
          <a class="btn btn-outline-danger" href="<?php echo site_url('production/component-daily-recon'); ?>" target="_blank" rel="noopener noreferrer">
            Buka Recon Component
          </a>
        </div>
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Saya mengerti</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade cashier-info-modal" id="cashierInfoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title mb-0" id="cashier_info_title">Informasi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="cashier-info-text" id="cashier_info_message">Transaksi berhasil diproses.</div>
        <div class="cashier-print-failure-panel d-none" id="cashier_print_failure_panel">
          <div class="cashier-print-failure-head">
            <div class="cashier-print-failure-icon"><i class="ri-printer-line"></i></div>
            <div>
              <div class="cashier-print-failure-title">Sebagian cetak belum terkirim</div>
              <div class="cashier-print-failure-lead" id="cashier_print_failure_lead">Transaksi sudah tersimpan, tetapi ada printer yang perlu dicek.</div>
            </div>
          </div>
          <div class="cashier-print-failure-list" id="cashier_print_failure_list"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<?php if ($incomingChannels !== []): ?>
<?php $firstIncomingChannel = (string)array_key_first($incomingChannels); ?>
<div class="modal fade cashier-incoming-modal" id="cashierIncomingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="cashier-incoming-head d-flex justify-content-between align-items-start gap-3">
        <div class="d-flex align-items-center gap-3">
          <div class="cashier-incoming-icon"><i class="ri-notification-3-line" aria-hidden="true"></i></div>
          <div>
            <h5 class="mb-1 fw-black">Order Masuk</h5>
            <div class="cashier-incoming-subtitle">Pantau channel order yang boleh Anda akses dari sesi kasir aktif.</div>
            <div class="cashier-incoming-status mt-2" id="cashier_incoming_status">
              <?php echo $incomingCashierOutletId > 0 ? 'Siap dimuat' : 'Buka sesi kasir untuk memuat order'; ?>
            </div>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="cashier-incoming-tabs" role="tablist" aria-label="Sumber order masuk">
        <?php foreach ($incomingChannels as $channelCode => $channel): ?>
          <?php $isFirstIncomingChannel = $channelCode === $firstIncomingChannel; ?>
          <button type="button" class="cashier-incoming-tab<?php echo $isFirstIncomingChannel ? ' is-active' : ''; ?>" data-incoming-channel="<?php echo html_escape((string)$channelCode); ?>" role="tab" aria-selected="<?php echo $isFirstIncomingChannel ? 'true' : 'false'; ?>">
            <span class="cashier-incoming-tab-label">
              <span><i class="<?php echo html_escape((string)$channel['icon']); ?> me-1" aria-hidden="true"></i><?php echo html_escape((string)$channel['label']); ?></span>
              <span class="cashier-incoming-tab-count" id="cashier_incoming_count_<?php echo html_escape((string)$channelCode); ?>">0</span>
            </span>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="cashier-incoming-list" id="cashier_incoming_list" role="tabpanel">
        <div class="cashier-incoming-empty"><div><?php echo $incomingCashierOutletId > 0 ? 'Pilih Refresh untuk memuat order terbaru.' : 'Belum ada sesi kasir aktif.'; ?></div></div>
      </div>
      <div class="cashier-incoming-detail d-none" id="cashier_incoming_detail"></div>
      <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
        <div class="small text-muted" id="cashier_incoming_updated">Belum dimuat</div>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary" id="cashier_incoming_refresh_btn" <?php echo $incomingCashierOutletId > 0 ? '' : 'disabled'; ?>><i class="ri-refresh-line me-1" aria-hidden="true"></i>Refresh</button>
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Tutup</button>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="cashier-toast-stack" id="cashier_toast_stack" aria-live="polite" aria-atomic="true"></div>
<div class="cashier-saving-overlay" id="cashier_saving_overlay" aria-hidden="true">
  <div class="cashier-saving-card">
    <div class="cashier-saving-spinner" aria-hidden="true"></div>
    <div class="cashier-saving-title" id="cashier_saving_title">Menyimpan Transaksi</div>
    <div class="cashier-saving-body" id="cashier_saving_body">Tunggu sebentar, pesanan sedang diproses.</div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const initialFilters = <?php echo json_encode($filters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const activeSession = <?php echo json_encode($activeSession, JSON_INVALID_UTF8_SUBSTITUTE); ?> || null;
  const orderReprintPrinters = <?php echo json_encode($orderReprintPrinters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); ?> || [];
  const launchDefaults = <?php echo json_encode([
    'outlet_id' => $defaultLaunchOutletId,
    'terminal_id' => $defaultLaunchTerminalId,
    'opening_cash' => $defaultLaunchOpeningCash,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); ?> || {};
  const cashierReconStatusUrl = '<?php echo site_url('pos/cashier/recon-status'); ?>';
  const catalogFiltersData = <?php echo json_encode([
    'divisions' => $catalogDivisions,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const siteBaseUrl = <?php echo json_encode(rtrim(base_url(), '/'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const recentState = {
    q: initialFilters.q || '',
    status: initialFilters.status || 'ALL',
    outlet_id: activeSession ? Number(activeSession.outlet_id || 0) : (parseInt(initialFilters.outlet_id || 0, 10) || 0),
    page: 1,
    limit: parseInt(initialFilters.limit || 20, 10) || 20
  };
  const catalogState = {
    q: '',
    division_id: 0,
    limit: 32
  };

  const order = {
    id: null,
    order_no: '',
    status: 'DRAFT',
    outlet_id: activeSession ? String(activeSession.outlet_id || '') : '',
    terminal_id: activeSession ? String(activeSession.terminal_id || '') : '',
    sales_channel_id: <?php echo json_encode($defaultSalesChannelId > 0 ? (string)$defaultSalesChannelId : ''); ?>,
    service_type: 'DINE_IN',
    guest_count: 1,
    table_no: '',
    member_id: null,
    member_no: '',
    member_name: '',
    customer_name: '',
    member_mobile_phone: '',
    member_point_balance: 0,
    member_stamp_balance: 0,
    notes: '',
    lines: []
  };
  let searchMode = 'PRODUCT';
  let reversalPreview = null;
  let productSearchTimer = null;
  let memberSearchTimer = null;
  let selectedRecentOrderId = null;
  let draftSaveInFlight = false;
  let confirmInFlight = false;
  let paymentContext = null;
  let paymentRows = [];
  let paymentActiveRowIndex = 0;
  let paymentSelectedVoucher = null;
  let paymentVoucherSearchTimer = null;
  let paymentPrepareInFlight = false;
  let paymentSubmitInFlight = false;
  let reversalSubmitInFlight = false;
  let closePreview = null;
  const closeDenominations = [500, 1000, 5000, 10000, 20000, 50000, 100000];
  const posTransactionCsrfToken = <?php echo json_encode((string)($pos_transaction_csrf_token ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

  const reversalModalEl = document.getElementById('cashierReversalModal');
  const reversalModal = reversalModalEl && window.bootstrap ? new bootstrap.Modal(reversalModalEl) : null;
  const reversalReasonOptions = <?php echo json_encode($reversalReasonOptions, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const paymentModalEl = document.getElementById('cashierPaymentModal');
  const paymentModal = paymentModalEl && window.bootstrap ? new bootstrap.Modal(paymentModalEl) : null;
  const closeModalEl = document.getElementById('cashierCloseModal');
  const closeModal = closeModalEl && window.bootstrap ? new bootstrap.Modal(closeModalEl) : null;
  const extraModalEl = document.getElementById('cashierExtraModal');
  const extraModal = extraModalEl && window.bootstrap ? new bootstrap.Modal(extraModalEl) : null;
  const reviewModalEl = document.getElementById('cashierReviewModal');
  const reviewModal = reviewModalEl && window.bootstrap ? new bootstrap.Modal(reviewModalEl) : null;
  const orderReprintModalEl = document.getElementById('cashierOrderReprintModal');
  const orderReprintModal = orderReprintModalEl && window.bootstrap ? new bootstrap.Modal(orderReprintModalEl) : null;
  const reconGateModalEl = document.getElementById('cashierReconGateModal');
  const reconGateModal = reconGateModalEl && window.bootstrap ? new bootstrap.Modal(reconGateModalEl) : null;
  const reconGateTitleEl = document.getElementById('cashier_recon_title');
  const reconGateSubtitleEl = document.getElementById('cashier_recon_subtitle');
  const reconGateMessageEl = document.getElementById('cashier_recon_message');
  const reconGateMissingListEl = document.getElementById('cashier_recon_missing_list');
  const infoModalEl = document.getElementById('cashierInfoModal');
  const infoModal = infoModalEl && window.bootstrap ? new bootstrap.Modal(infoModalEl) : null;
  const infoMessageEl = document.getElementById('cashier_info_message');
  const infoPrintPanelEl = document.getElementById('cashier_print_failure_panel');
  const infoPrintLeadEl = document.getElementById('cashier_print_failure_lead');
  const infoPrintListEl = document.getElementById('cashier_print_failure_list');
  const workspace = document.getElementById('cashier_workspace');
  const launchOutlet = document.getElementById('cashier_launch_outlet');
  const launchTerminal = document.getElementById('cashier_launch_terminal');
  const launchOpeningCash = document.getElementById('cashier_launch_opening_cash');
  const launchNotes = document.getElementById('cashier_launch_notes');
  const openButton = document.getElementById('cashier_open_btn');
  const closeButton = document.getElementById('cashier_close_btn');
  const orderReprintButton = document.getElementById('cashier_reprint_order_btn');
  const orderReprintMeta = document.getElementById('cashier_order_reprint_meta');
  const orderReprintPrinterSelect = document.getElementById('cashier_order_reprint_printer');
  const orderReprintScopeSelect = document.getElementById('cashier_order_reprint_scope');
  const orderReprintHint = document.getElementById('cashier_order_reprint_hint');
  const orderReprintSubmitButton = document.getElementById('cashier_order_reprint_submit');
  const closeDenomRows = document.getElementById('cashier_close_denom_rows');
  const closeShiftMeta = document.getElementById('cashier_close_shift_meta');
  const closeMethodSummary = document.getElementById('cashier_close_method_summary');
  const closeAccountSummary = document.getElementById('cashier_close_account_summary');
  const closeActualCashText = document.getElementById('cashier_close_actual_cash_text');
  const closeExpectedCashText = document.getElementById('cashier_close_expected_cash_text');
  const closeVarianceText = document.getElementById('cashier_close_variance_text');
  const outletSelect = document.getElementById('cashier_outlet_id');
  const terminalSelect = document.getElementById('cashier_terminal_id');
  const salesChannelSelect = document.getElementById('cashier_sales_channel');
  const serviceType = document.getElementById('cashier_service_type');
  const guestCount = document.getElementById('cashier_guest_count');
  const tableNoInput = document.getElementById('cashier_table_no');
  const notesInput = document.getElementById('cashier_notes');
  const memberSearchInput = document.getElementById('cashier_member_search');
  const memberResult = document.getElementById('cashier_member_result');
  const memberSelected = document.getElementById('cashier_member_selected');
  const customerNameInput = document.getElementById('cashier_customer_name');
  const productSearchInput = document.getElementById('cashier_product_search');
  const paymentButton = document.getElementById('cashier_open_payment');
  const paymentMeta = document.getElementById('cashier_payment_meta');
  const paymentOrderNo = document.getElementById('cashier_payment_order_no');
  const paymentBaseTotal = document.getElementById('cashier_payment_base_total');
  const paymentVoucherTotal = document.getElementById('cashier_payment_voucher_total');
  const paymentDepositTotal = document.getElementById('cashier_payment_deposit_total');
  const paymentPaidTotal = document.getElementById('cashier_payment_paid_total');
  const paymentGrandTotal = document.getElementById('cashier_payment_grand_total');
  const paymentEnteredTotal = document.getElementById('cashier_payment_entered_total');
  const paymentChangeTotal = document.getElementById('cashier_payment_change_total');
  const paymentRemainingTotal = document.getElementById('cashier_payment_remaining_total');
  const paymentDueTotal = document.getElementById('cashier_payment_due_total');
  const paymentCustomerName = document.getElementById('cashier_payment_customer_name');
  const paymentMemberBalance = document.getElementById('cashier_payment_member_balance');
  const paymentVoucherCount = document.getElementById('cashier_payment_voucher_count');
  const paymentDepositCard = document.getElementById('cashier_payment_deposit_card');
  const paymentDepositMeta = document.getElementById('cashier_payment_deposit_meta');
  const paymentDepositRows = document.getElementById('cashier_payment_deposit_rows');
  const paymentVoucherSearch = document.getElementById('cashier_payment_voucher_search');
  const paymentVoucherCheckButton = document.getElementById('cashier_payment_voucher_check');
  const paymentVoucherStatus = document.getElementById('cashier_payment_voucher_status');
  const paymentVoucherSuggestions = document.getElementById('cashier_payment_voucher_suggestions');
  const paymentSelectedVoucherCard = document.getElementById('cashier_payment_selected_voucher');
  const paymentSelectedVoucherName = document.getElementById('cashier_payment_selected_voucher_name');
  const paymentSelectedVoucherMeta = document.getElementById('cashier_payment_selected_voucher_meta');
  const paymentSelectedVoucherMessage = document.getElementById('cashier_payment_selected_voucher_message');
  const paymentClearVoucherButton = document.getElementById('cashier_payment_clear_voucher');
  const paymentVoucherHint = document.getElementById('cashier_payment_voucher_hint');
  const paymentRowsContainer = document.getElementById('cashier_payment_rows');
  const paymentAddRowButton = document.getElementById('cashier_payment_add_row');
  const paymentTotalHint = document.getElementById('cashier_payment_total_hint');
  const paymentNotes = document.getElementById('cashier_payment_notes');
  const paymentSubmitButton = document.getElementById('cashier_submit_payment');
  const paymentQuickAmountButtons = Array.from(document.querySelectorAll('.payment-quick-amount'));
  const catalogResult = document.getElementById('cashier_catalog_result');
  const catalogHint = document.getElementById('cashier_catalog_hint');
  const catalogCount = document.getElementById('cashier_catalog_count');
  const divisionFilters = document.getElementById('cashier_division_filters');
  const cartList = document.getElementById('cashier_cart_list');
  const cartEmpty = document.getElementById('cashier_cart_empty');
  const reversalButton = document.getElementById('cashier_preview_reversal');
  const deleteDraftButton = document.getElementById('cashier_delete_draft');
  const reversalCheckAllButton = document.getElementById('cashier_reversal_check_all');
  const reversalUncheckAllButton = document.getElementById('cashier_reversal_uncheck_all');
  const reversalAdjustmentWrap = document.getElementById('cashier_reversal_adjustment_wrap');
  const reversalReasonWrap = document.getElementById('cashier_reversal_reason_wrap');
  const reversalReasonCode = document.getElementById('cashier_reversal_reason_code');
  const reversalReasonOther = document.getElementById('cashier_reversal_reason_other');
  const reversalReasonHint = document.getElementById('cashier_reversal_reason_hint');
  const saveVoidButton = document.getElementById('cashier_save_void');
  const saveDraftButton = document.getElementById('cashier_save_draft');
  const confirmOrderButton = document.getElementById('cashier_confirm_order');
  const extraGroupsContainer = document.getElementById('cashier_extra_groups');
  const extraMeta = document.getElementById('cashier_extra_meta');
  const extraProductNameInput = document.getElementById('cashier_extra_product_name');
  const extraLineQtyInput = document.getElementById('cashier_extra_line_qty');
  const extraLineNoteInput = document.getElementById('cashier_extra_line_note');
  const extraBasePriceInput = document.getElementById('cashier_extra_base_price');
  const extraSaveButton = document.getElementById('cashier_save_extra');
  const extraGroupCount = document.getElementById('cashier_extra_group_count');
  const reviewSubmitButton = document.getElementById('cashier_review_submit');
  const toastStack = document.getElementById('cashier_toast_stack');
  const savingOverlay = document.getElementById('cashier_saving_overlay');
  const savingTitle = document.getElementById('cashier_saving_title');
  const savingBody = document.getElementById('cashier_saving_body');
  const defaultSaveDraftLabel = saveDraftButton ? saveDraftButton.textContent : 'Simpan Draft';
  const defaultSaveVoidLabel = saveVoidButton ? saveVoidButton.textContent : 'Simpan Void';
  const defaultConfirmOrderLabel = confirmOrderButton ? confirmOrderButton.textContent : 'Simpan Transaksi';
  const defaultOrderReprintLabel = orderReprintSubmitButton ? orderReprintSubmitButton.textContent : 'Kirim ke Printer';
  const submitCloseButton = document.getElementById('cashier_submit_close');
  const defaultCloseSubmitLabel = submitCloseButton ? submitCloseButton.textContent : 'Tutup Shift';
  const extraOptionCache = {};
  let activeExtraLineIndex = null;
  let activeExtraDraft = null;
  let pendingReviewAction = null;
  let closeSubmitInFlight = false;

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[m]));
  }
  function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v || 0));
  }
  function moneyCompact(v) {
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(v || 0));
  }
  function number(v, digits = 2) {
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(Number(v || 0));
  }
  function parsePaymentAmount(value) {
    const raw = String(value ?? '').trim();
    if (raw === '') {
      return 0;
    }
    const compact = raw.replace(/\s+/g, '');
    let normalized = compact;
    const lastComma = compact.lastIndexOf(',');
    const lastDot = compact.lastIndexOf('.');
    if (lastComma >= 0 && lastDot >= 0) {
      const decimalIndex = Math.max(lastComma, lastDot);
      const integerPart = compact.slice(0, decimalIndex).replace(/[.,]/g, '').replace(/[^\d-]/g, '');
      const decimalPart = compact.slice(decimalIndex + 1).replace(/[^\d]/g, '');
      normalized = `${integerPart || '0'}.${decimalPart}`;
    } else if (lastComma >= 0) {
      const parts = compact.split(',');
      if (parts.length === 2) {
        normalized = `${parts[0].replace(/[^\d-]/g, '') || '0'}.${parts[1].replace(/[^\d]/g, '')}`;
      } else {
        normalized = compact.replace(/,/g, '').replace(/[^\d-]/g, '');
      }
    } else {
      normalized = compact.replace(/[^\d.-]/g, '');
    }
    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : 0;
  }
  function normalizePhotoUrl(path) {
    const raw = String(path || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) return raw;
    if (raw.startsWith('/')) return siteBaseUrl + raw;
    return siteBaseUrl + '/' + raw.replace(/^\.?\//, '');
  }
  function productFallbackLabel(row) {
    const basis = String(row.product_name || row.bundle_name || row.product_division_code || 'POS').trim();
    return basis.substring(0, 2).toUpperCase();
  }
  function productMediaHtml(row) {
    const photoUrl = normalizePhotoUrl(row.photo_path || '');
    if (photoUrl) {
      return `<div class="cashier-product-media"><img src="${escapeHtml(photoUrl)}" alt="${escapeHtml(row.product_name || row.bundle_name || 'Produk')}"></div>`;
    }
    return `<div class="cashier-product-media"><div class="cashier-product-fallback">${escapeHtml(productFallbackLabel(row))}</div></div>`;
  }
  function availabilityChip(status) {
    const s = String(status || '').toUpperCase();
    if (s === 'AVAILABLE' || s === 'OK') return '<span class="cashier-chip ok">Tersedia</span>';
    if (s === 'OUT' || s === 'EMPTY') return '<span class="cashier-chip out">Kosong</span>';
    return '<span class="cashier-chip warn">Perlu Cek</span>';
  }
  function floorStock(v) {
    return Math.max(0, Math.floor(Number(v || 0)));
  }
  function stockChip(row) {
    const rawQty = row && row.estimated_available_qty;
    if (rawQty === null || rawQty === undefined || rawQty === '') {
      return '<span class="cashier-chip stock">Stok -</span>';
    }
    return `<span class="cashier-chip stock">Stok ${number(floorStock(rawQty), 0)}</span>`;
  }
  function currentDivisionLabel() {
    if (searchMode === 'BUNDLE') return 'Bundle aktif';
    if (catalogState.division_id <= 0) return 'Semua divisi';
    const row = (catalogFiltersData.divisions || []).find((item) => Number(item.id || 0) === Number(catalogState.division_id || 0));
    return row ? String(row.name || 'Semua divisi') : 'Semua divisi';
  }
  function renderDivisionFilters() {
    if (!divisionFilters) return;
    const rows = [{ id: 0, name: 'Semua', product_count: 0 }].concat(catalogFiltersData.divisions || []);
    const divisionButtons = rows.map((row) => `
      <button type="button" class="cashier-filter-pill${searchMode !== 'BUNDLE' && Number(row.id || 0) === Number(catalogState.division_id || 0) ? ' active' : ''}" data-division-id="${Number(row.id || 0)}">
        ${escapeHtml(row.name || 'Semua')}
        ${Number(row.product_count || 0) > 0 ? `<span class="opacity-75">${Number(row.product_count || 0)}</span>` : ''}
      </button>
    `).join('');
    const bundleButton = `
      <button type="button" class="cashier-filter-pill${searchMode === 'BUNDLE' ? ' active' : ''}" data-search-bundle="1">
        Bundle
      </button>
    `;
    divisionFilters.innerHTML = divisionButtons + bundleButton;
    divisionFilters.querySelectorAll('[data-division-id]').forEach((btn) => btn.addEventListener('click', () => {
      searchMode = 'PRODUCT';
      catalogState.division_id = Number(btn.dataset.divisionId || 0);
      renderDivisionFilters();
      loadCatalog().catch((e) => {
        catalogResult.innerHTML = `<div class="cashier-empty text-danger">${escapeHtml(e.message)}</div>`;
      });
    }));
    divisionFilters.querySelectorAll('[data-search-bundle]').forEach((btn) => btn.addEventListener('click', () => {
      searchMode = 'BUNDLE';
      // Bundles can contain products from several divisions. Clear a previous
      // food/bar filter so all active bundles remain visible.
      catalogState.division_id = 0;
      renderDivisionFilters();
      loadCatalog().catch((e) => {
        catalogResult.innerHTML = `<div class="cashier-empty text-danger">${escapeHtml(e.message)}</div>`;
      });
    }));
  }

  async function getJson(url) {
    const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const t = await r.text();
    let j = null;
    try { j = JSON.parse(t); } catch (e) {
      throw new Error('Response backend bukan JSON. ' + String(t || '').replace(/\s+/g, ' ').trim().slice(0, 240));
    }
    if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal memuat data');
    return j;
  }

  async function postJson(url, payload, scopedOptions) {
    const headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (scopedOptions && scopedOptions.headers && typeof scopedOptions.headers === 'object') {
      Object.assign(headers, scopedOptions.headers);
    }
    const r = await fetch(url, {
      method: 'POST',
      headers,
      body: JSON.stringify(payload)
    });
    const t = await r.text();
    let j = null;
    try { j = JSON.parse(t); } catch (e) {
      throw new Error('Response save bukan JSON. ' + String(t || '').replace(/\s+/g, ' ').trim().slice(0, 240));
    }
    if (!r.ok || !j.ok) {
      const err = new Error(j.message || 'Gagal menyimpan data');
      err.payload = j;
      throw err;
    }
    return j;
  }

  function postPosTransactionJson(url, payload) {
    return postJson(url, payload, {
      headers: { 'X-Pos-Transaction-CSRF': posTransactionCsrfToken }
    });
  }

  function showInfoModal(message, title = 'Informasi') {
    const titleEl = document.getElementById('cashier_info_title');
    if (infoPrintPanelEl) infoPrintPanelEl.classList.add('d-none');
    if (infoPrintListEl) infoPrintListEl.innerHTML = '';
    if (infoPrintLeadEl) infoPrintLeadEl.textContent = '';
    if (infoMessageEl) infoMessageEl.classList.remove('d-none');
    if (titleEl) titleEl.textContent = title;
    if (infoMessageEl) infoMessageEl.textContent = message || '-';
    if (infoModal) {
      infoModal.show();
      return;
    }
    alert(message || '-');
  }

  function formatPrintFailureReason(reason) {
    const normalized = String(reason || '').trim();
    if (normalized === '') {
      return 'Servis printer lokal menolak permintaan cetak. Cek koneksi printer dan agent desktop.';
    }
    if (/^HTTP 500$/i.test(normalized)) {
      return 'Servis printer lokal mengembalikan error internal (HTTP 500). Cek template runtime, nama device OS, dan status agent printer.';
    }
    if (/^HTTP 404$/i.test(normalized)) {
      return 'Servis printer lokal tidak menemukan endpoint cetak. Pastikan agent printer berjalan di port yang benar.';
    }
    if (/failed to fetch|networkerror|load failed/i.test(normalized)) {
      return 'Browser tidak bisa menjangkau servis printer lokal. Pastikan agent printer aktif dan port tidak diblokir.';
    }
    return normalized;
  }

  function normalizePrintFailureEntry(entry) {
    if (entry && typeof entry === 'object') {
      return {
        name: String(entry.name || entry.printer_name || 'Printer').trim() || 'Printer',
        reason: formatPrintFailureReason(entry.reason || entry.message || ''),
      };
    }
    const raw = String(entry || '').trim();
    const separatorIndex = raw.indexOf(':');
    if (separatorIndex > 0) {
      return {
        name: raw.slice(0, separatorIndex).trim() || 'Printer',
        reason: formatPrintFailureReason(raw.slice(separatorIndex + 1).trim()),
      };
    }
    return {
      name: 'Printer',
      reason: formatPrintFailureReason(raw),
    };
  }

  function showPrintFailureModal(actionLabel, failedPrinters) {
    const failed = Array.isArray(failedPrinters) ? failedPrinters.filter(Boolean) : [];
    if (!failed.length) return;
    if (!infoModal || !infoPrintPanelEl || !infoPrintListEl) {
      const prefix = actionLabel ? `${actionLabel} berhasil, tetapi printer berikut gagal:` : 'Ada printer yang gagal mencetak:';
      showInfoModal(prefix + '\n- ' + failed.join('\n- '), 'Printer');
      return;
    }
    const entries = failed.map(normalizePrintFailureEntry);
    const titleEl = document.getElementById('cashier_info_title');
    if (titleEl) titleEl.textContent = 'Printer perlu dicek';
    if (infoMessageEl) infoMessageEl.classList.add('d-none');
    if (infoPrintPanelEl) infoPrintPanelEl.classList.remove('d-none');
    if (infoPrintLeadEl) {
      infoPrintLeadEl.textContent = actionLabel
        ? `${actionLabel} sudah tersimpan, tetapi ada printer yang gagal menerima slip.`
        : 'Ada printer yang gagal menerima dokumen cetak.';
    }
    infoPrintListEl.innerHTML = entries.map((entry) => `
      <div class="cashier-print-failure-item">
        <div class="cashier-print-failure-name">${escapeHtml(entry.name || 'Printer')}</div>
        <div class="cashier-print-failure-reason">${escapeHtml(entry.reason || '-')}</div>
      </div>
    `).join('');
    infoModal.show();
  }

  function showToast(message, kind = 'info', title = 'Informasi', timeout = 3200) {
    if (!toastStack) {
      return;
    }
    const item = document.createElement('div');
    item.className = `cashier-toast ${kind}`;
    item.innerHTML = `
      <div class="cashier-toast-title">${escapeHtml(title || 'Informasi')}</div>
      <div class="cashier-toast-body">${escapeHtml(message || '-')}</div>
    `;
    toastStack.appendChild(item);
    window.setTimeout(() => {
      item.classList.add('is-hiding');
      window.setTimeout(() => item.remove(), 260);
    }, Math.max(1400, Number(timeout || 3200)));
  }

  function closePreviewSummary() {
    return closePreview && closePreview.report && closePreview.report.summary ? closePreview.report.summary : {};
  }

  function renderCloseDenominationRows() {
    if (!closeDenomRows) return;
    closeDenomRows.innerHTML = closeDenominations.map((denomination) => `
      <div class="cashier-close-denom-row" data-denomination="${denomination}">
        <div>
          <div class="cashier-close-denom-label">${money(denomination)}</div>
          <div class="small text-muted">Jumlah lembar / keping</div>
        </div>
        <input type="number" min="0" step="1" value="0" class="form-control cashier-close-denom-qty">
        <div class="cashier-close-denom-total">${money(0)}</div>
      </div>
    `).join('');
  }

  function closeCashBreakdownRows() {
    if (!closeDenomRows) return [];
    return Array.from(closeDenomRows.querySelectorAll('.cashier-close-denom-row')).map((row) => {
      const denomination = Number(row.dataset.denomination || 0);
      const qty = Number(row.querySelector('.cashier-close-denom-qty')?.value || 0);
      return {
        denomination,
        qty,
        amount: denomination * qty,
        row,
      };
    });
  }

  function syncCloseCashTotals() {
    const rows = closeCashBreakdownRows();
    let total = 0;
    rows.forEach((entry) => {
      const amount = Number(entry.amount || 0);
      total += amount;
      const totalEl = entry.row?.querySelector('.cashier-close-denom-total');
      if (totalEl) totalEl.textContent = money(amount);
    });
    const summary = closePreviewSummary();
    const expectedCash = Number(summary.expected_cash || 0);
    const variance = total - expectedCash;
    const actualCashInput = document.getElementById('cashier_close_actual_cash');
    if (actualCashInput) actualCashInput.value = String(total);
    if (closeActualCashText) closeActualCashText.textContent = money(total);
    if (closeExpectedCashText) closeExpectedCashText.textContent = money(expectedCash);
    if (closeVarianceText) {
      closeVarianceText.textContent = money(variance);
      closeVarianceText.classList.remove('negative', 'positive');
      if (variance < 0) {
        closeVarianceText.classList.add('negative');
      } else if (variance > 0) {
        closeVarianceText.classList.add('positive');
      }
    }
  }

  function renderCloseBreakdownRows(rows, emptyMessage, labelKey) {
    const items = Array.isArray(rows) ? rows : [];
    if (!items.length) {
      return `<div class="cashier-close-empty">${escapeHtml(emptyMessage)}</div>`;
    }
    return items.map((row) => `
      <div class="cashier-close-breakdown-row">
        <div class="cashier-close-breakdown-head">
          <div class="cashier-close-breakdown-name">${escapeHtml(row[labelKey] || '-')}</div>
          <div class="fw-bold">${money(row.net_amount || 0)}</div>
        </div>
        <div class="cashier-close-breakdown-meta">
          <span>Masuk ${money(row.gross_amount || 0)}</span>
          <span>Refund ${money(row.refund_amount || 0)}</span>
        </div>
      </div>
    `).join('');
  }

  function renderClosePreview(json) {
    closePreview = json;
    const report = json && json.report ? json.report : {};
    const shift = report.shift || {};
    const summary = report.summary || {};
    if (closeShiftMeta) {
      const shiftNo = shift.shift_no || '-';
      const outlet = shift.outlet_name || '-';
      const terminal = shift.terminal_name || '-';
      const openedAt = shift.opened_at ? new Date(String(shift.opened_at).replace(' ', 'T')).toLocaleString('id-ID') : '-';
      closeShiftMeta.textContent = `${shiftNo} • ${outlet} • ${terminal} • buka ${openedAt}`;
    }
    document.getElementById('cashier_close_total_orders').textContent = String(Number(summary.total_order_count || 0));
    document.getElementById('cashier_close_net_sales').textContent = money(summary.total_net_sales || 0);
    document.getElementById('cashier_close_cash_sales').textContent = money(summary.total_cash_sales || 0);
    document.getElementById('cashier_close_non_cash_sales').textContent = money(summary.total_non_cash_sales || 0);
    document.getElementById('cashier_close_deposit_sales').textContent = money(summary.total_deposit_receipts || 0);
    document.getElementById('cashier_close_refund_total').textContent = money(summary.total_refund || 0);
    document.getElementById('cashier_close_void_total').textContent = money(summary.total_void || 0);
    document.getElementById('cashier_close_expected_cash').textContent = money(summary.expected_cash || 0);
    if (closeMethodSummary) {
      closeMethodSummary.innerHTML = renderCloseBreakdownRows(report.by_method || [], 'Belum ada data metode pembayaran.', 'method_name');
    }
    if (closeAccountSummary) {
      closeAccountSummary.innerHTML = renderCloseBreakdownRows(report.by_account || [], 'Belum ada data rekening.', 'account_label');
    }
    syncCloseCashTotals();
  }

  function resetCloseForm() {
    if (closeDenomRows) {
      closeDenomRows.querySelectorAll('.cashier-close-denom-qty').forEach((input) => {
        input.value = '0';
      });
    }
    const notesCloseInput = document.getElementById('cashier_close_notes');
    if (notesCloseInput) notesCloseInput.value = '';
    syncCloseCashTotals();
  }

  function reconWarningText(status) {
    const missing = Array.isArray(status && status.missing) ? status.missing : [];
    const lines = missing.slice(0, 8).map((row) => {
      const division = row.division_code || row.division_name || '-';
      const line = row.line_label ? ` - ${row.line_label}` : '';
      const reason = row.reason ? ` (${row.reason})` : '';
      return `- ${division}: ${row.domain_label || row.domain || '-'}${line}${reason}`;
    });
    if (missing.length > 8) {
      lines.push(`- dan ${missing.length - 8} item lain`);
    }
    return [
      (status && status.message) || 'Daily recon belum lengkap.',
      '',
      lines.length ? 'Yang belum dikonfirmasi:' : '',
      ...lines,
      '',
      'Recon tetap dilakukan di halaman Daily Recon bahan baku dan Component.'
    ].filter(Boolean).join('\n');
  }

  function showDailyReconGateModal(status) {
    const stage = String(status && status.stage || '').toUpperCase();
    const stageLabel = stage === 'OPEN' ? 'buka kasir' : 'tutup kasir';
    const missing = Array.isArray(status && status.missing) ? status.missing : [];
    if (reconGateTitleEl) reconGateTitleEl.textContent = 'Daily Recon belum lengkap';
    if (reconGateSubtitleEl) reconGateSubtitleEl.textContent = 'Kasir belum bisa ' + stageLabel + ' sebelum checkpoint recon lengkap.';
    if (reconGateMessageEl) reconGateMessageEl.textContent = (status && status.message) || 'Mohon selesaikan recon bahan baku dan component terlebih dahulu.';
    if (reconGateMissingListEl) {
      if (!missing.length) {
        reconGateMissingListEl.innerHTML = '<div class="cashier-recon-item"><div><div class="cashier-recon-item-title">Checkpoint belum ditemukan</div><div class="cashier-recon-item-meta">Buka halaman daily recon bahan baku dan component, lalu konfirmasi recon.</div></div></div>';
      } else {
        reconGateMissingListEl.innerHTML = missing.slice(0, 30).map((row) => {
          const division = escapeHtml(row.division_code || row.division_name || '-');
          const domain = escapeHtml(row.domain_label || row.domain || 'Recon');
          const line = escapeHtml(row.line_label || '');
          const reason = escapeHtml(row.reason || 'belum konfirmasi');
          return '<div class="cashier-recon-item">'
            + '<div>'
            + '<div class="cashier-recon-item-title">' + domain + (line ? ' - ' + line : '') + '</div>'
            + '<div class="cashier-recon-item-meta">' + division + '</div>'
            + '</div>'
            + '<div class="cashier-recon-reason">' + reason + '</div>'
            + '</div>';
        }).join('') + (missing.length > 30
          ? '<div class="cashier-recon-item"><div><div class="cashier-recon-item-title">Dan ' + (missing.length - 30) + ' item lain</div><div class="cashier-recon-item-meta">Buka halaman recon untuk melihat semua item.</div></div></div>'
          : '');
      }
    }
    if (reconGateModal) {
      reconGateModal.show();
      return;
    }
    alert(reconWarningText(status));
  }

  async function confirmDailyReconGate(stage) {
    const json = await getJson(cashierReconStatusUrl + '?stage=' + encodeURIComponent(stage));
    const status = json.status || {};
    if (!status.enabled || status.complete) return true;
    showDailyReconGateModal(status);
    return false;
  }

  function handleCashierActionError(err) {
    const status = err && err.payload && err.payload.daily_recon_status ? err.payload.daily_recon_status : null;
    if (status && status.enabled && !status.complete) {
      showDailyReconGateModal(status);
      return;
    }
    alert((err && err.message) || 'Proses kasir gagal.');
  }

  async function openCloseModalPreview() {
    if (!(await confirmDailyReconGate('CLOSE'))) return;
    if (closeShiftMeta) closeShiftMeta.textContent = 'Memuat preview shift aktif...';
    const json = await getJson('<?php echo site_url('pos/cashier/close-preview'); ?>');
    resetCloseForm();
    renderClosePreview(json);
    if (closeModal) closeModal.show();
  }

  function buildCloseSuccessMessage(summary, printPrepareMessage = '', failedPrinters = []) {
    const parts = [
      'Kasir berhasil ditutup.',
      '',
      'Total Order: ' + Number(summary.total_order_count || 0),
      'Net Sales: ' + money(summary.total_net_sales || 0),
      'Cash Sales: ' + money(summary.total_cash_sales || 0),
      'Non Cash: ' + money(summary.total_non_cash_sales || 0),
      'Deposit: ' + money(summary.total_deposit_receipts || 0),
      'Refund: ' + money(summary.total_refund || 0),
      'Void: ' + money(summary.total_void || 0),
      'Expected Cash: ' + money(summary.expected_cash || 0),
      'Actual Cash: ' + money(summary.actual_cash || 0),
      'Variance: ' + money(summary.variance_cash || 0),
    ];
    if (printPrepareMessage) {
      parts.push('', 'Printer: ' + printPrepareMessage);
    }
    if (Array.isArray(failedPrinters) && failedPrinters.length) {
      parts.push('', 'Printer gagal:');
      failedPrinters.forEach((entry) => {
        const normalized = normalizePrintFailureEntry(entry);
        parts.push(`- ${normalized.name}: ${normalized.reason}`);
      });
    }
    return parts.join('\n');
  }

  function setCloseSubmitState(isBusy) {
    closeSubmitInFlight = !!isBusy;
    if (!submitCloseButton) return;
    if (closeSubmitInFlight) {
      submitCloseButton.disabled = true;
      submitCloseButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Menyimpan Tutup Shift...';
      return;
    }
    submitCloseButton.disabled = false;
    submitCloseButton.textContent = defaultCloseSubmitLabel;
  }

  function activeReversalKind() {
    return 'VOID';
  }

  function orderStatusLabel(status) {
    const value = String(status || '').toUpperCase();
    const map = {
      DRAFT: 'Draft',
      CONFIRMED: 'Terkonfirmasi',
      PAID: 'Lunas',
      VOID: 'Void penuh',
      PARTIAL: 'Sebagian',
      REFUNDED_FULL: 'Refund penuh',
      REFUNDED_PARTIAL: 'Refund sebagian',
      CANCELLED: 'Dibatalkan'
    };
    return map[value] || (value ? value.replace(/_/g, ' ') : '-');
  }

  function stockCommitStatusEntry(status) {
    const value = String(status || '').toUpperCase();
    const map = {
      PENDING: ['commit-queued', 'Sinkron stok menunggu'],
      QUEUED: ['commit-queued', 'Sinkron stok antre'],
      PROCESSING: ['commit-processing', 'Sinkron stok diproses'],
      POSTED: ['commit-posted', 'Stok sudah sinkron'],
      NOT_REQUIRED: ['commit-failed', 'Belum bisa stock commit'],
      FAILED: ['commit-failed', 'Sinkron stok gagal'],
      REVERSED: ['commit-reversed', 'Sinkron stok dibatalkan']
    };
    return map[value] || ['commit-queued', value ? ('Status stok: ' + value.replace(/_/g, ' ')) : '-'];
  }

  function reversalProcessingLabel(processStatus) {
    return String(processStatus || '').toUpperCase() === 'NOT_PROCESSED'
      ? 'Belum diproses'
      : 'Sudah diproses';
  }

  function orderStatusChip(status) {
    const value = String(status || '').toUpperCase();
    const label = orderStatusLabel(value);
    const kind = value === 'CONFIRMED' ? 'order-confirmed' : 'order-draft';
    return `<span class="cashier-status-chip ${kind}">${escapeHtml(label)}</span>`;
  }

  function stockCommitChip(status, orderStatus = '') {
    const value = String(status || '').toUpperCase();
    if (!value) return '';
    if (value === 'PENDING' && ['DRAFT', 'PENDING'].includes(String(orderStatus || '').toUpperCase())) {
      return '';
    }
    const entry = stockCommitStatusEntry(value);
    return `<span class="cashier-status-chip ${entry[0]}">${escapeHtml(entry[1])}</span>`;
  }

  function kickoffRuntimeJobSync(orderId, runtimeJobId) {
    const safeOrderId = Number(orderId || 0);
    const safeJobId = Number(runtimeJobId || 0);
    if (safeOrderId <= 0 || safeJobId <= 0) {
      return;
    }
    window.setTimeout(() => {
      postPosTransactionJson(`<?php echo site_url('pos/orders/runtime-jobs/trigger'); ?>/${safeOrderId}`, {
        job_id: safeJobId,
        limit: 1
      }).then((jobJson) => {
        const job = jobJson.job || {};
        const status = String(job.status || '').toUpperCase();
        if (status === 'SUCCESS') {
          showToast('Stok transaksi sudah diposting.', 'success', 'Sinkronisasi Stok', 2400);
          return;
        }
        if (status === 'FAILED') {
          showToast(job.last_error || 'Sinkronisasi stok gagal. Order tetap tersimpan untuk retry.', 'warn', 'Sinkronisasi Stok', 5200);
          return;
        }
        pollRuntimeJobStatus(safeOrderId, 0);
      }).catch(async (e) => {
        try {
          const statusJson = await getJson(`<?php echo site_url('pos/orders/runtime-jobs/status'); ?>/${safeOrderId}`);
          const latest = statusJson.job || {};
          const latestStatus = String(latest.status || '').toUpperCase();
          if (latestStatus === 'SUCCESS') {
            showToast('Stok transaksi sudah diposting.', 'success', 'Sinkronisasi Stok', 2400);
          } else if (latestStatus === 'FAILED') {
            showToast(latest.last_error || 'Sinkronisasi stok gagal. Order tetap tersimpan untuk retry.', 'warn', 'Sinkronisasi Stok', 5200);
          } else {
            showToast(e && e.message ? e.message : 'Sinkronisasi stok masih antre. Jalankan runner queue bila perlu.', 'warn', 'Sinkronisasi Stok', 4200);
            pollRuntimeJobStatus(safeOrderId, 0);
          }
        } catch (_statusError) {
          showToast(e && e.message ? e.message : 'Sinkronisasi stok masih antre. Jalankan runner queue bila perlu.', 'warn', 'Sinkronisasi Stok', 4200);
          pollRuntimeJobStatus(safeOrderId, 0);
        }
      });
    }, 450);
  }

  function pollRuntimeJobStatus(orderId, attempt = 0) {
    const safeOrderId = Number(orderId || 0);
    if (safeOrderId <= 0 || attempt >= 5) {
      return;
    }
    window.setTimeout(() => {
      getJson(`<?php echo site_url('pos/orders/runtime-jobs/status'); ?>/${safeOrderId}`).then((statusJson) => {
        const latest = statusJson.job || {};
        const latestStatus = String(latest.status || '').toUpperCase();
        if (latestStatus === 'SUCCESS') {
          showToast('Stok transaksi sudah diposting.', 'success', 'Sinkronisasi Stok', 2400);
          void loadRecents().catch(() => {});
          return;
        }
        if (latestStatus === 'FAILED') {
          showToast(latest.last_error || 'Sinkronisasi stok gagal. Order tetap tersimpan untuk retry.', 'warn', 'Sinkronisasi Stok', 5200);
          void loadRecents().catch(() => {});
          return;
        }
        pollRuntimeJobStatus(safeOrderId, attempt + 1);
      }).catch(() => {
        pollRuntimeJobStatus(safeOrderId, attempt + 1);
      });
    }, attempt === 0 ? 1200 : 2200);
  }

  function showSavingOverlay(mode) {
    const normalizedMode = String(mode || 'DRAFT').toUpperCase();
    if (savingTitle) {
      if (normalizedMode === 'CONFIRM') {
        savingTitle.textContent = 'Menyimpan Transaksi';
      } else if (normalizedMode === 'PAYMENT') {
        savingTitle.textContent = 'Menyimpan Payment';
      } else if (normalizedMode === 'VOID') {
        savingTitle.textContent = 'Menyimpan Void';
      } else if (normalizedMode === 'REFUND') {
        savingTitle.textContent = 'Menyimpan Refund';
      } else {
        savingTitle.textContent = 'Menyimpan Draft';
      }
    }
    if (savingBody) {
      if (normalizedMode === 'CONFIRM') {
        savingBody.textContent = 'Pesanan sedang diproses dan siap dicetak.';
      } else if (normalizedMode === 'PAYMENT') {
        savingBody.textContent = 'Pembayaran sedang disimpan, benefit member dihitung, dan receipt kasir sedang disiapkan.';
      } else if (normalizedMode === 'VOID') {
        savingBody.textContent = 'Void sedang disimpan dan slip printer sedang disiapkan.';
      } else if (normalizedMode === 'REFUND') {
        savingBody.textContent = 'Refund sedang disimpan dan slip printer sedang disiapkan.';
      } else {
        savingBody.textContent = 'Draft sedang disimpan.';
      }
    }
    if (savingOverlay) {
      savingOverlay.classList.add('active');
      savingOverlay.setAttribute('aria-hidden', 'false');
    }
  }

  function hideSavingOverlay() {
    if (savingOverlay) {
      savingOverlay.classList.remove('active');
      savingOverlay.setAttribute('aria-hidden', 'true');
    }
  }

  function lineGrandTotal(line) {
    const base = Number(line.qty || 0) * Number(line.unit_price || 0);
    const extraTotal = (Array.isArray(line.extras) ? line.extras : []).reduce((sum, extra) => {
      return sum + (Number(extra.qty || 0) * Number(extra.unit_price || 0));
    }, 0);
    return base + extraTotal;
  }

  function syncHeaderToOrder() {
    order.outlet_id = outletSelect ? (outletSelect.value || '') : order.outlet_id;
    order.terminal_id = terminalSelect ? (terminalSelect.value || '') : order.terminal_id;
    order.sales_channel_id = salesChannelSelect ? (salesChannelSelect.value || '') : order.sales_channel_id;
    order.service_type = serviceType ? (serviceType.value || 'DINE_IN') : 'DINE_IN';
    order.guest_count = Math.max(1, Number(guestCount ? (guestCount.value || 1) : 1));
    order.table_no = tableNoInput ? (tableNoInput.value || '') : '';
    order.customer_name = order.member_id
      ? String(order.member_name || order.customer_name || '').trim()
      : String(customerNameInput ? (customerNameInput.value || '') : order.customer_name || '').trim();
    order.notes = notesInput ? (notesInput.value || '') : '';
  }

  function customerDisplayName(source = order) {
    const value = source && typeof source === 'object'
      ? (source.customer_display_name || source.customer_name || source.member_name || '')
      : '';
    return String(value || '').trim() || 'Walk in';
  }

  function syncCustomerNameInputState() {
    if (!customerNameInput) {
      return;
    }
    const lockedToMember = !!order.member_id;
    const value = lockedToMember
      ? String(order.member_name || order.customer_name || '').trim()
      : String(order.customer_name || '').trim();
    if (customerNameInput.value !== value) {
      customerNameInput.value = value;
    }
    customerNameInput.readOnly = lockedToMember;
    customerNameInput.classList.toggle('cashier-readonly', lockedToMember);
    customerNameInput.placeholder = lockedToMember ? 'Mengikuti member terpilih' : 'Nama untuk walk in / cetak';
  }

  function selectedSalesChannelMeta() {
    if (!salesChannelSelect) {
      return { defaultService: 'DINE_IN', allowedTypes: [] };
    }
    const selected = salesChannelSelect.selectedOptions && salesChannelSelect.selectedOptions.length
      ? salesChannelSelect.selectedOptions[0]
      : null;
    const defaultService = String(selected ? (selected.dataset.serviceDefault || '') : '').trim().toUpperCase();
    const allowedTypesRaw = String(selected ? (selected.dataset.allowedTypes || '') : '').trim();
    const allowedTypes = allowedTypesRaw
      ? allowedTypesRaw.split(',').map((item) => String(item || '').trim().toUpperCase()).filter(Boolean)
      : [];
    return {
      defaultService: ['DINE_IN', 'TAKE_AWAY', 'DELIVERY', 'PICKUP'].includes(defaultService) ? defaultService : 'DINE_IN',
      allowedTypes
    };
  }

  function applyAllowedServiceTypes(allowedTypes, preferredType) {
    if (!serviceType) return;
    const normalizedAllowed = Array.isArray(allowedTypes) ? allowedTypes.filter((item) => ['DINE_IN', 'TAKE_AWAY', 'DELIVERY', 'PICKUP'].includes(String(item || '').toUpperCase())) : [];
    const enabledValues = [];
    Array.from(serviceType.options).forEach((option) => {
      const value = String(option.value || '').toUpperCase();
      const enabled = !normalizedAllowed.length || normalizedAllowed.includes(value);
      option.disabled = !enabled;
      option.hidden = !enabled;
      if (enabled) {
        enabledValues.push(value);
      }
    });
    const normalizedPreferred = String(preferredType || '').toUpperCase();
    const nextValue = enabledValues.includes(normalizedPreferred)
      ? normalizedPreferred
      : (enabledValues[0] || 'DINE_IN');
    serviceType.value = nextValue;
    order.service_type = nextValue;
  }

  function syncServiceTypeFromChannel(forceDefault = true) {
    if (!salesChannelSelect || !serviceType) return;
    const meta = selectedSalesChannelMeta();
    const preferredType = forceDefault ? meta.defaultService : (serviceType.value || order.service_type || meta.defaultService);
    applyAllowedServiceTypes(meta.allowedTypes, preferredType);
  }

  function filterLaunchTerminalOptions() {
    if (!launchOutlet || !launchTerminal) return;
    const outletId = Number(launchOutlet.value || 0);
    let visibleTerminalIds = [];
    Array.from(launchTerminal.options).forEach((opt, idx) => {
      if (idx === 0) {
        opt.hidden = false;
        return;
      }
      const optionOutletId = Number(opt.dataset.outletId || 0);
      const isVisible = outletId === 0 || optionOutletId === 0 || optionOutletId === outletId;
      opt.hidden = !isVisible;
      if (isVisible) {
        const optionId = Number(opt.value || 0);
        if (optionId > 0) {
          visibleTerminalIds.push(optionId);
        }
      }
    });
    const selectedOption = launchTerminal.selectedOptions.length ? launchTerminal.selectedOptions[0] : null;
    const currentTerminalId = Number(launchTerminal.value || 0);
    const hasVisibleCurrent = !!selectedOption && !selectedOption.hidden && currentTerminalId > 0;
    if (!hasVisibleCurrent) {
      const fallbackTerminalId = visibleTerminalIds.length ? Math.min.apply(null, visibleTerminalIds) : 0;
      launchTerminal.value = fallbackTerminalId > 0 ? String(fallbackTerminalId) : '';
    }
  }

  function applyLaunchDefaults() {
    if (activeSession) return;
    if (launchOutlet && !launchOutlet.value && Number(launchDefaults.outlet_id || 0) > 0) {
      launchOutlet.value = String(launchDefaults.outlet_id || '');
    }
    filterLaunchTerminalOptions();
    if (launchOpeningCash && !String(launchOpeningCash.value || '').trim()) {
      launchOpeningCash.value = String(Number(launchDefaults.opening_cash || 300000));
    }
  }

  function isConfirmedOrder() {
    return String(order.status || '').toUpperCase() === 'CONFIRMED';
  }

  function isLockedExistingLine(line) {
    return isConfirmedOrder() && Number(line && line.id ? line.id : 0) > 0;
  }

  function canEditCartLine(line) {
    return !isLockedExistingLine(line);
  }

  function canOpenPayment() {
    const normalizedStatus = String(order.status || '').toUpperCase();
    return !!order.id && ['CONFIRMED', 'PAID_PARTIAL', 'IN_KITCHEN', 'READY', 'SERVED'].includes(normalizedStatus);
  }

  function canDeleteDraft() {
    const normalizedStatus = String(order.status || '').toUpperCase();
    return !!order.id && ['DRAFT', 'PENDING'].includes(normalizedStatus);
  }

  function canReprintSelectedOrder() {
    return !!order.id && Array.isArray(order.lines) && order.lines.length > 0 && orderReprintPrinters.length > 0;
  }

  function updateActionState() {
    reversalButton.disabled = !order.id;
    const sessionReady = !!activeSession;
    const normalizedStatus = String(order.status || '').toUpperCase();
    const draftEditableOrder = !order.id || ['DRAFT', 'PENDING'].includes(normalizedStatus);
    const confirmEditableOrder = !order.id || ['DRAFT', 'PENDING', 'CONFIRMED'].includes(normalizedStatus);
    if (saveDraftButton) {
      saveDraftButton.disabled = !sessionReady || !draftEditableOrder || draftSaveInFlight || confirmInFlight;
      saveDraftButton.textContent = defaultSaveDraftLabel;
      saveDraftButton.title = isConfirmedOrder() ? 'Order confirmed tidak bisa disimpan sebagai draft. Gunakan Simpan Transaksi untuk append item baru atau ubah header.' : '';
    }
    if (confirmOrderButton) {
      confirmOrderButton.disabled = !sessionReady || !confirmEditableOrder || draftSaveInFlight || confirmInFlight;
      confirmOrderButton.textContent = isConfirmedOrder() ? 'Simpan Perubahan Transaksi' : defaultConfirmOrderLabel;
    }
    if (paymentButton) {
      paymentButton.disabled = !sessionReady || !canOpenPayment() || draftSaveInFlight || confirmInFlight || paymentPrepareInFlight || paymentSubmitInFlight;
    }
    if (deleteDraftButton) {
      deleteDraftButton.disabled = !sessionReady || !canDeleteDraft() || draftSaveInFlight || confirmInFlight || paymentPrepareInFlight || paymentSubmitInFlight;
    }
    if (orderReprintButton) {
      orderReprintButton.disabled = !sessionReady || !canReprintSelectedOrder() || draftSaveInFlight || confirmInFlight || paymentPrepareInFlight || paymentSubmitInFlight;
      if (!orderReprintPrinters.length) {
        orderReprintButton.title = 'Belum ada printer direct print aktif untuk sesi kasir ini.';
      } else if (!order.id || !order.lines.length) {
        orderReprintButton.title = 'Pilih satu order aktif dari panel kiri terlebih dulu.';
      } else {
        orderReprintButton.title = '';
      }
    }
  }

  function renderOrderReprintPrinterOptions() {
    if (!orderReprintPrinterSelect) return;
    const currentValue = String(orderReprintPrinterSelect.value || '');
    const options = ['<option value="">Semua printer / divisi aktif</option>'];
    orderReprintPrinters.forEach((printer) => {
      const printerId = Number(printer.id || 0);
      if (printerId <= 0) return;
      options.push(`<option value="${printerId}">${escapeHtml(printer.label || printer.printer_name || printer.printer_code || 'Printer POS')}</option>`);
    });
    orderReprintPrinterSelect.innerHTML = options.join('');
    const hasCurrent = orderReprintPrinters.some((printer) => String(printer.id || '') === currentValue);
    orderReprintPrinterSelect.value = hasCurrent ? currentValue : '';
  }

  function selectedOrderReprintPrinterMeta() {
    if (!orderReprintPrinterSelect) {
      return null;
    }
    const printerId = Number(orderReprintPrinterSelect.value || 0);
    if (printerId <= 0) {
      return null;
    }
    return orderReprintPrinters.find((printer) => Number(printer.id || 0) === printerId) || null;
  }

  function refreshOrderReprintModalState() {
    const selectedPrinter = selectedOrderReprintPrinterMeta();
    const isCashierBillPrinter = !!selectedPrinter && String(selectedPrinter.printer_role || '').toUpperCase() === 'KASIR';
    if (orderReprintMeta) {
      if (order.id) {
        const orderLabel = order.order_no || `Order #${Number(order.id || 0)}`;
        const customerLabel = String(order.customer_name || order.member_name || 'Walk in').trim() || 'Walk in';
        const statusLabel = String(order.status || 'DRAFT').toUpperCase();
        orderReprintMeta.textContent = `${orderLabel} • ${customerLabel} • ${statusLabel}${isCashierBillPrinter ? ' • BILL KASIR' : ''}`;
      } else {
        orderReprintMeta.textContent = 'Pilih satu order aktif dulu dari panel kiri.';
      }
    }
    if (orderReprintScopeSelect) {
      if (isCashierBillPrinter) {
        orderReprintScopeSelect.value = 'ALL';
        orderReprintScopeSelect.disabled = true;
      } else {
        orderReprintScopeSelect.disabled = false;
      }
    }
    if (orderReprintHint) {
      orderReprintHint.textContent = isCashierBillPrinter
        ? 'Printer kasir akan mencetak bill sementara seluruh order yang belum terbayar. Mode item terbaru tidak dipakai untuk bill kasir.'
        : (String(order.status || '').toUpperCase() === 'DRAFT'
        ? 'Mode "Pesanan baru saja" hanya bisa dipakai jika order ini sudah pernah confirm dan punya snapshot item terbaru.'
        : 'Mode "Pesanan baru saja" akan mengambil snapshot item paling akhir. Gunakan "Semua pesanan" untuk mengirim ulang seluruh line order.');
    }
  }

  function defaultPaymentRow(amount = '') {
    const defaultMethodId = Array.isArray(paymentContext && paymentContext.payment_methods ? paymentContext.payment_methods : [])
      ? Number(((paymentContext.payment_methods || [])[0] || {}).id || 0)
      : 0;
    return {
      payment_method_id: defaultMethodId > 0 ? String(defaultMethodId) : '',
      paid_amount: amount !== '' ? String(amount) : '',
      reference_no: ''
    };
  }

  function selectedPaymentVoucherOption() {
    return paymentSelectedVoucher;
  }

  function paymentSelectedVoucherKind(option = selectedPaymentVoucherOption()) {
    const explicitKind = String(option && (option.kind || option.source_type) ? (option.kind || option.source_type) : '').toUpperCase();
    if (explicitKind === 'ISSUE' || explicitKind === 'CAMPAIGN') {
      return explicitKind;
    }
    const selection = String(option && option.selection_value ? option.selection_value : '').trim().toUpperCase();
    if (selection.startsWith('ISSUE:')) return 'ISSUE';
    if (selection.startsWith('CAMPAIGN:')) return 'CAMPAIGN';
    return '';
  }

  function hasSelectedPaymentVoucher(option = selectedPaymentVoucherOption()) {
    if (!option) return false;
    const selection = String(option.selection_value || option.voucher_code || '').trim();
    const explicitlyInvalid = option.ok === false || Number(option.ok) === 0 || option.valid === false || Number(option.valid) === 0;
    return selection !== '' && !explicitlyInvalid && paymentSelectedVoucherKind(option) !== '';
  }

  function paymentMethodMetaById(methodId) {
    const safeId = Number(methodId || 0);
    if (safeId <= 0) {
      return null;
    }
    return (paymentContext && Array.isArray(paymentContext.payment_methods) ? paymentContext.payment_methods : []).find((row) => Number(row.id || 0) === safeId) || null;
  }

  function paymentDepositPreviewRows(targetAmount) {
    if (!paymentContext) return [];
    const sourceRows = Array.isArray(paymentContext.deposit_rows) ? paymentContext.deposit_rows : [];
    let remainingTarget = Math.max(0, Number(targetAmount || 0));
    return sourceRows.map((row) => {
      const remainingAmount = Math.max(0, Number(row.remaining_amount || 0));
      const previewAppliedAmount = Math.max(0, Math.min(remainingAmount, remainingTarget));
      remainingTarget = Math.max(0, remainingTarget - previewAppliedAmount);
      return {
        ...row,
        remaining_amount: remainingAmount,
        preview_applied_amount: previewAppliedAmount,
      };
    });
  }

  function paymentDraftTotals() {
    const baseTotal = Number(paymentContext && paymentContext.base_total ? paymentContext.base_total : 0);
    const paidTotal = Number(paymentContext && paymentContext.paid_total ? paymentContext.paid_total : 0);
    const canEditAdjustment = !!(paymentContext && paymentContext.can_edit_adjustment);
    const option = selectedPaymentVoucherOption();
    const optionKind = paymentSelectedVoucherKind(option);
    const optionDiscount = Math.max(
      0,
      Number(option && option.discount_amount ? option.discount_amount : 0),
      Number(option && option.estimated_discount ? option.estimated_discount : 0)
    );
    const hasVoucherSelection = hasSelectedPaymentVoucher(option);
    // A voucher may be worth more than the bill, but the applied value may never exceed it.
    let voucherAmount = canEditAdjustment && hasVoucherSelection && optionKind === 'ISSUE' ? Math.min(baseTotal, optionDiscount) : 0;
    let promoAmount = canEditAdjustment && hasVoucherSelection && optionKind === 'CAMPAIGN' ? Math.min(baseTotal, optionDiscount) : 0;
    const grandTotal = canEditAdjustment
      ? Math.max(0, baseTotal - voucherAmount - promoAmount)
      : Number(paymentContext && paymentContext.grand_total ? paymentContext.grand_total : baseTotal);
    const dueBeforeDeposit = Math.max(0, grandTotal - paidTotal);
    const depositRows = paymentDepositPreviewRows(dueBeforeDeposit);
    const depositAppliedTotal = depositRows.reduce((sum, row) => sum + Number(row.preview_applied_amount || 0), 0);
    const depositAvailableTotal = depositRows.reduce((sum, row) => sum + Number(row.remaining_amount || 0), 0);
    const dueTotal = Math.max(0, dueBeforeDeposit - depositAppliedTotal);
    let enteredTotal = 0;
    let appliedTotal = 0;
    let remainingDue = dueTotal;
    let changeTotal = 0;
    let invalidNonCashOverpay = false;
    const activeRows = paymentRows.map((row, index) => {
      const enteredAmount = Math.max(0, parsePaymentAmount(row.paid_amount || 0));
      const methodMeta = paymentMethodMetaById(row.payment_method_id || 0);
      return {
        index,
        enteredAmount,
        methodMeta,
        methodType: String(methodMeta && methodMeta.method_type ? methodMeta.method_type : '').toUpperCase(),
      };
    }).filter((row) => row.enteredAmount > 0 && row.methodMeta);

    activeRows.forEach((row) => {
      const appliedAmount = Math.max(0, Math.min(row.enteredAmount, remainingDue));
      enteredTotal += row.enteredAmount;
      appliedTotal += appliedAmount;
      if (row.methodType !== 'CASH' && row.enteredAmount > remainingDue + 0.009) {
        invalidNonCashOverpay = true;
      }
      remainingDue = Math.max(0, remainingDue - appliedAmount);
    });

    const changeEligible = activeRows.length === 1 && activeRows[0].methodType === 'CASH';
    if (changeEligible) {
      changeTotal = Math.max(0, enteredTotal - appliedTotal);
    }

    let guardMessage = '';
    if (invalidNonCashOverpay) {
      guardMessage = 'Nominal metode non tunai tidak boleh melebihi sisa tagihan.';
    } else if (activeRows.length > 1 && enteredTotal > dueTotal + 0.009) {
      guardMessage = 'Jika metode pembayaran lebih dari satu, total input tidak boleh melebihi sisa tagihan. Kembalian hanya berlaku untuk pembayaran tunai tunggal.';
    }

    const remainingTotal = Math.max(0, dueTotal - appliedTotal);
    return {
      baseTotal,
      paidTotal,
      voucherAmount,
      promoAmount,
      grandTotal,
      dueBeforeDeposit,
      depositAvailableTotal,
      depositAppliedTotal,
      depositRows,
      dueTotal,
      enteredTotal,
      appliedTotal,
      remainingTotal,
      changeTotal,
      activeRowCount: activeRows.length,
      canCashChange: changeEligible,
      hasVoucherSelection,
      guardMessage,
    };
  }

  function syncSinglePaymentAmountToDue() {
    if (paymentRows.length !== 1) {
      return;
    }
    const totals = paymentDraftTotals();
    paymentRows[0].paid_amount = totals.dueTotal > 0 ? String(totals.dueTotal) : '';
  }

  function renderPaymentSummary() {
    const totals = paymentDraftTotals();
    if (paymentBaseTotal) paymentBaseTotal.textContent = money(totals.baseTotal);
    if (paymentVoucherTotal) paymentVoucherTotal.textContent = money(totals.voucherAmount + totals.promoAmount);
    if (paymentDepositTotal) paymentDepositTotal.textContent = money(totals.depositAppliedTotal);
    if (paymentPaidTotal) paymentPaidTotal.textContent = money(totals.paidTotal);
    if (paymentGrandTotal) paymentGrandTotal.textContent = money(totals.grandTotal);
    if (paymentEnteredTotal) paymentEnteredTotal.textContent = money(totals.appliedTotal);
    if (paymentChangeTotal) paymentChangeTotal.textContent = money(totals.changeTotal);
    if (paymentRemainingTotal) paymentRemainingTotal.textContent = money(totals.remainingTotal);
    if (paymentDueTotal) paymentDueTotal.textContent = money(totals.dueTotal);
    if (paymentCustomerName) paymentCustomerName.textContent = String(paymentContext && paymentContext.customer_name ? paymentContext.customer_name : 'Walk in');
    if (paymentOrderNo) paymentOrderNo.textContent = String(paymentContext && paymentContext.order_no ? paymentContext.order_no : '-');
    if (paymentMemberBalance) {
      paymentMemberBalance.textContent = `Point ${number(paymentContext && paymentContext.member_point_balance ? paymentContext.member_point_balance : 0, 0)} | Stamp ${number(paymentContext && paymentContext.member_stamp_balance ? paymentContext.member_stamp_balance : 0, 0)}`;
    }
    if (paymentVoucherCount) {
      const voucherCount = Number(paymentContext && paymentContext.loyalty_summary ? paymentContext.loyalty_summary.open_voucher_count || 0 : 0);
      if (voucherCount > 0) {
        paymentVoucherCount.innerHTML = `<span class="badge bg-success text-white" style="font-size:.75rem;border-radius:6px;padding:3px 8px;">🎟 ${voucherCount} voucher aktif</span>`;
      } else {
        paymentVoucherCount.innerHTML = '<span class="text-muted">Tidak ada voucher aktif</span>';
      }
    }
    if (paymentDepositCard && paymentDepositMeta && paymentDepositRows) {
      if (totals.depositAvailableTotal > 0.009) {
        paymentDepositCard.classList.remove('d-none');
        const openCount = totals.depositRows.length;
        paymentDepositMeta.textContent = `${openCount} DP terbuka. Tersedia ${money(totals.depositAvailableTotal)} dan otomatis dipakai ${money(totals.depositAppliedTotal)}.`;
        paymentDepositRows.innerHTML = totals.depositRows.map((row) => {
          const chunks = [escapeHtml(row.payment_no || 'DP')];
          chunks.push('sisa ' + money(row.remaining_amount || 0));
          if (Number(row.preview_applied_amount || 0) > 0.009) {
            chunks.push('dipakai ' + money(row.preview_applied_amount || 0));
          }
          return `<div>${chunks.join(' • ')}</div>`;
        }).join('');
      } else {
        paymentDepositCard.classList.add('d-none');
        paymentDepositMeta.textContent = paymentContext && paymentContext.member_id
          ? 'Tidak ada DP terbuka untuk member ini.'
          : 'Pilih member agar DP bisa dipakai otomatis.';
        paymentDepositRows.innerHTML = '';
      }
    }
    if (paymentTotalHint) {
      if (totals.guardMessage) {
        paymentTotalHint.textContent = totals.guardMessage;
        paymentTotalHint.classList.add('text-danger');
      } else if ((totals.voucherAmount + totals.promoAmount) > 0.009 && totals.dueTotal <= 0.009) {
        paymentTotalHint.textContent = `Seluruh tagihan tertutup voucher sebesar ${money(totals.voucherAmount + totals.promoAmount)}. Tidak perlu memilih metode pembayaran tambahan.`;
        paymentTotalHint.classList.remove('text-danger');
      } else if (totals.depositAppliedTotal > 0.009 && totals.dueTotal <= 0.009) {
        paymentTotalHint.textContent = `Seluruh tagihan tertutup DP sebesar ${money(totals.depositAppliedTotal)}. Metode pembayaran tambahan tidak wajib.`;
        paymentTotalHint.classList.remove('text-danger');
      } else if (totals.depositAppliedTotal > 0.009) {
        paymentTotalHint.textContent = `DP member otomatis dipakai ${money(totals.depositAppliedTotal)}. Sisa yang perlu dibayar sekarang ${money(totals.dueTotal)}.`;
        paymentTotalHint.classList.remove('text-danger');
      } else if (totals.changeTotal > 0) {
        paymentTotalHint.textContent = `Uang diterima ${money(totals.enteredTotal)}. Yang disimpan ${money(totals.appliedTotal)} dan kembalian ${money(totals.changeTotal)}.`;
        paymentTotalHint.classList.remove('text-danger');
      } else if (totals.remainingTotal > 0 && totals.appliedTotal > 0) {
        paymentTotalHint.textContent = `Payment sebagian akan disimpan. Sisa tagihan setelah payment: ${money(totals.remainingTotal)}.`;
        paymentTotalHint.classList.remove('text-danger');
      } else {
        paymentTotalHint.textContent = `Input payment: ${money(totals.enteredTotal)} dari tagihan ${money(totals.dueTotal)}.`;
        paymentTotalHint.classList.remove('text-danger');
      }
    }
    if (paymentVoucherHint) {
      const option = selectedPaymentVoucherOption();
      paymentVoucherHint.textContent = option && option.message
        ? option.message
        : 'Pilih voucher member atau promo voucher aktif bila ada.';
      paymentVoucherHint.classList.toggle('text-danger', !!(option && !option.valid));
    }
    if (paymentSubmitButton) {
      if (paymentSubmitInFlight) {
        paymentSubmitButton.disabled = true;
        paymentSubmitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Menyimpan Payment...';
      } else {
        paymentSubmitButton.disabled = false;
        paymentSubmitButton.textContent = totals.dueTotal <= 0.009
          ? (totals.hasVoucherSelection ? 'Selesaikan Dengan Voucher' : 'Selesaikan Dengan DP')
          : (totals.remainingTotal > 0.009 ? 'Simpan Pembayaran Sebagian' : 'Selesaikan Pembayaran');
      }
    }
    if (paymentAddRowButton) {
      paymentAddRowButton.disabled = totals.dueTotal <= 0.009;
    }
    syncPaymentQuickButtons();
  }

  function renderPaymentRows() {
    if (!paymentRowsContainer) return;
    const totals = paymentDraftTotals();
    if (totals.dueTotal <= 0.009) {
      const closedByVoucher = totals.hasVoucherSelection;
      paymentRowsContainer.innerHTML = `<div class="small text-muted border rounded p-3">${closedByVoucher
        ? 'Tagihan sudah tertutup penuh oleh voucher. Tidak perlu input metode pembayaran tambahan.'
        : 'Tagihan sudah tertutup penuh oleh DP member. Tidak perlu input metode pembayaran tambahan.'}</div>`;
      return;
    }
    const methodOptions = ['<option value="">Pilih metode...</option>'].concat((paymentContext && paymentContext.payment_methods ? paymentContext.payment_methods : []).map((method) => `<option value="${Number(method.id || 0)}">${escapeHtml(method.method_name || '-')}</option>`));
    paymentRowsContainer.innerHTML = paymentRows.map((row, index) => `
      <div class="row g-2 payment-inline-card border rounded p-2 cashier-payment-method-row ${index === paymentActiveRowIndex ? 'is-active' : ''}" data-index="${index}">
        <div class="col-md-4">
          <label class="form-label small text-muted mb-1">Metode</label>
          <select class="form-select cashier-payment-method" data-index="${index}">${methodOptions.join('')}</select>
        </div>
        <div class="col-md-4">
          <label class="form-label small text-muted mb-1">Nominal</label>
          <input type="text" inputmode="decimal" autocomplete="off" class="form-control cashier-payment-amount" data-index="${index}" value="${escapeHtml(row.paid_amount || '')}" placeholder="Nominal bayar">
        </div>
        <div class="col-md-3">
          <label class="form-label small text-muted mb-1">Referensi</label>
          <input type="text" class="form-control cashier-payment-reference" data-index="${index}" value="${escapeHtml(row.reference_no || '')}" placeholder="Referensi">
        </div>
        <div class="col-md-1 d-flex align-items-end">
          <button type="button" class="btn cashier-payment-btn-danger cashier-payment-remove w-100" data-index="${index}" ${paymentRows.length <= 1 ? 'disabled' : ''}>x</button>
        </div>
      </div>
    `).join('');
    paymentRowsContainer.querySelectorAll('.cashier-payment-method-row').forEach((rowEl) => rowEl.addEventListener('click', (event) => {
      const interactiveTarget = event.target && event.target.closest('input, select, button, textarea, label');
      if (interactiveTarget) {
        return;
      }
      const nextIndex = Number(rowEl.dataset.index || 0);
      if (nextIndex === paymentActiveRowIndex) {
        return;
      }
      paymentActiveRowIndex = nextIndex;
      renderPaymentRows();
      renderPaymentSummary();
    }));
    paymentRowsContainer.querySelectorAll('.cashier-payment-method').forEach((input) => {
      input.value = String(paymentRows[Number(input.dataset.index || 0)]?.payment_method_id || '');
      input.addEventListener('change', (event) => {
        const idx = Number(event.target.dataset.index || 0);
        paymentActiveRowIndex = idx;
        paymentRows[idx].payment_method_id = event.target.value || '';
        renderPaymentSummary();
      });
    });
    paymentRowsContainer.querySelectorAll('.cashier-payment-amount').forEach((input) => {
      input.addEventListener('focus', (event) => {
        paymentActiveRowIndex = Number(event.target.dataset.index || 0);
      });
      input.addEventListener('input', (event) => {
        const idx = Number(event.target.dataset.index || 0);
        paymentActiveRowIndex = idx;
        paymentRows[idx].paid_amount = event.target.value || '';
        renderPaymentSummary();
      });
    });
    paymentRowsContainer.querySelectorAll('.cashier-payment-reference').forEach((input) => {
      input.addEventListener('focus', (event) => {
        paymentActiveRowIndex = Number(event.target.dataset.index || 0);
      });
      input.addEventListener('input', (event) => {
        const idx = Number(event.target.dataset.index || 0);
        paymentActiveRowIndex = idx;
        paymentRows[idx].reference_no = event.target.value || '';
      });
    });
    paymentRowsContainer.querySelectorAll('.cashier-payment-remove').forEach((btn) => btn.addEventListener('click', () => {
      const idx = Number(btn.dataset.index || 0);
      if (paymentRows.length <= 1) return;
      paymentRows.splice(idx, 1);
      paymentActiveRowIndex = Math.max(0, Math.min(paymentActiveRowIndex, paymentRows.length - 1));
      renderPaymentRows();
      renderPaymentSummary();
    }));
  }

  function syncPaymentQuickButtons() {
    if (!paymentQuickAmountButtons.length) {
      return;
    }
    const totals = paymentDraftTotals();
    const disabled = paymentRows.length <= 0 || totals.dueTotal <= 0.009;
    paymentQuickAmountButtons.forEach((button) => {
      button.disabled = disabled;
      button.classList.toggle('opacity-50', disabled);
    });
  }

  function paymentRemainingForQuickRow(targetIndex) {
    const totals = paymentDraftTotals();
    let remaining = totals.dueTotal;
    paymentRows.forEach((row, index) => {
      if (index === targetIndex) {
        return;
      }
      const methodMeta = paymentMethodMetaById(row.payment_method_id || 0);
      if (!methodMeta) {
        return;
      }
      const enteredAmount = Math.max(0, parsePaymentAmount(row.paid_amount || 0));
      if (enteredAmount <= 0) {
        return;
      }
      remaining = Math.max(0, remaining - Math.min(enteredAmount, remaining));
    });
    return remaining;
  }

  function applyQuickPaymentAmount(button) {
    if (!button) {
      return;
    }
    if (!paymentRows.length) {
      throw new Error('Belum ada baris metode pembayaran yang bisa diisi.');
    }
    const idx = Math.max(0, Math.min(paymentActiveRowIndex, paymentRows.length - 1));
    const row = paymentRows[idx] || null;
    if (!row) {
      return;
    }
    const methodMeta = paymentMethodMetaById(row.payment_method_id || 0);
    if (!methodMeta) {
      throw new Error('Pilih metode pembayaran dulu sebelum memakai tombol nominal cepat.');
    }
    const remainingForRow = paymentRemainingForQuickRow(idx);
    const isCash = String(methodMeta.method_type || '').toUpperCase() === 'CASH';
    let amount = button.dataset.mode === 'remaining'
      ? remainingForRow
      : Number(button.dataset.amount || 0);
    if (!isCash || paymentRows.length > 1) {
      amount = Math.min(amount, remainingForRow);
    }
    paymentRows[idx].paid_amount = amount > 0 ? String(amount) : '';
    renderPaymentRows();
    renderPaymentSummary();
  }

  function resetPaymentVoucherSelection() {
    paymentSelectedVoucher = null;
    if (paymentVoucherSearch) paymentVoucherSearch.value = '';
    if (paymentVoucherStatus) {
      paymentVoucherStatus.textContent = 'Input kode voucher lalu klik cek.';
      paymentVoucherStatus.className = 'small text-muted mt-1';
    }
    if (paymentSelectedVoucherCard) {
      paymentSelectedVoucherCard.classList.add('d-none');
      paymentSelectedVoucherCard.setAttribute('data-selection', '');
      paymentSelectedVoucherCard.setAttribute('data-discount', '0');
    }
    if (paymentVoucherSuggestions) {
      paymentVoucherSuggestions.innerHTML = '';
      paymentVoucherSuggestions.style.display = 'none';
    }
  }

  function renderPaymentVoucherSelection(row) {
    paymentSelectedVoucher = row;
    if (paymentSelectedVoucherCard) {
      paymentSelectedVoucherCard.classList.remove('d-none');
      paymentSelectedVoucherCard.setAttribute('data-selection', String(row.selection_value || ''));
      paymentSelectedVoucherCard.setAttribute('data-discount', String(Number(row.discount_amount || 0)));
    }
    if (paymentSelectedVoucherName) paymentSelectedVoucherName.textContent = row.label || row.voucher_code || 'Voucher';
    {
      const rvKind  = String(row.reward_kind || 'DISCOUNT').toUpperCase();
      const rvNotes = String(row.notes || '');
      let metaText  = '';
      if (rvKind === 'FREE_PRODUCT') {
        metaText = rvNotes || 'Gratis produk';
      } else if (rvKind === 'MERCHANDISE') {
        metaText = rvNotes || 'Merchandise / hadiah fisik (non-diskon)';
      } else if (rvKind === 'OTHER') {
        metaText = rvNotes || 'Reward khusus';
      } else {
        metaText = `Potongan preview ${money(row.discount_amount || 0)}`;
      }
      if (row.expired_at) metaText += ' - exp ' + row.expired_at;
      if (paymentSelectedVoucherMeta) paymentSelectedVoucherMeta.textContent = metaText;
    }
    if (paymentSelectedVoucherMessage) paymentSelectedVoucherMessage.textContent = row.message || '';
    if (paymentVoucherStatus) {
      paymentVoucherStatus.textContent = row.message || 'Voucher siap dipakai.';
      paymentVoucherStatus.className = row.ok ? 'small text-success mt-1' : 'small text-danger mt-1';
    }
    if (paymentVoucherSuggestions) {
      paymentVoucherSuggestions.innerHTML = '';
      paymentVoucherSuggestions.style.display = 'none';
    }
    syncSinglePaymentAmountToDue();
    renderPaymentRows();
    renderPaymentSummary();
  }

  function renderPaymentVoucherSuggestions(rows) {
    if (!paymentVoucherSuggestions) return;
    if (!rows.length) {
      if (paymentVoucherStatus) {
        paymentVoucherStatus.textContent = 'Voucher tidak ditemukan.';
        paymentVoucherStatus.className = 'small text-danger mt-1';
      }
      paymentVoucherSuggestions.innerHTML = '<div class="list-group-item text-muted">Voucher tidak ditemukan. Pastikan kode atau nama voucher benar.</div>';
      paymentVoucherSuggestions.style.display = 'block';
      return;
    }
    if (paymentVoucherStatus) {
      paymentVoucherStatus.textContent = 'Voucher ditemukan. Pilih voucher yang ingin dipakai.';
      paymentVoucherStatus.className = 'small text-success mt-1';
    }
    paymentVoucherSuggestions.innerHTML = rows.map((row, idx) => {
      const badge = row.ok
        ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Valid</span>'
        : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Tidak Valid</span>';
      const action = row.ok
        ? `<button type="button" class="btn btn-sm btn-primary" data-voucher-idx="${idx}">Pakai</button>`
        : '<button type="button" class="btn btn-sm cashier-payment-btn-neutral" disabled>Tidak Bisa</button>';
      const rewardKind  = String(row.reward_kind || 'DISCOUNT').toUpperCase();
      const voucherNotes = String(row.notes || '');
      let previewLine = '';
      if (rewardKind === 'FREE_PRODUCT') {
        previewLine = voucherNotes || 'Gratis produk';
      } else if (rewardKind === 'MERCHANDISE') {
        previewLine = voucherNotes || 'Merchandise / hadiah fisik';
      } else if (rewardKind === 'OTHER') {
        previewLine = voucherNotes || 'Reward khusus';
      } else {
        previewLine = `Preview potongan ${money(row.discount_amount || 0)}`;
      }
      return `<div class="list-group-item text-start">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="flex-fill min-w-0">
            <div class="d-flex justify-content-between gap-2"><strong>${escapeHtml(row.label || row.voucher_code || '-')}</strong>${badge}</div>
            <div class="small text-muted mt-1">${escapeHtml(row.message || previewLine)}</div>
            <div class="small text-muted">${escapeHtml(previewLine)}</div>
          </div>
          <div class="flex-shrink-0">${action}</div>
        </div>
      </div>`;
    }).join('');
    paymentVoucherSuggestions.style.display = 'block';
    paymentVoucherSuggestions.querySelectorAll('[data-voucher-idx]').forEach((btn) => btn.addEventListener('click', () => {
      const row = rows[Number(btn.getAttribute('data-voucher-idx') || 0)] || null;
      if (!row) return;
      if (!row.ok) {
        alert(row.message || 'Voucher tidak bisa dipakai untuk transaksi ini.');
        return;
      }
      renderPaymentVoucherSelection(row);
    }));
  }

  async function searchPaymentVouchers(options = {}) {
    const query = paymentVoucherSearch ? paymentVoucherSearch.value.trim() : '';
    if (!paymentContext || Number(paymentContext.order_id || 0) <= 0) {
      if (paymentVoucherStatus) {
        paymentVoucherStatus.textContent = 'Order belum siap untuk pengecekan voucher.';
        paymentVoucherStatus.className = 'small text-danger mt-1';
      }
      if (paymentVoucherSuggestions) {
        paymentVoucherSuggestions.innerHTML = '';
        paymentVoucherSuggestions.style.display = 'none';
      }
      return;
    }
    if (paymentVoucherStatus) {
      paymentVoucherStatus.textContent = 'Memeriksa voucher...';
      paymentVoucherStatus.className = 'small text-muted mt-1';
    }
    try {
      const snapshotOrderId = paymentContext.order_id;
      const payload = await getJson('<?php echo site_url('pos/orders/payment/voucher-search'); ?>?order_id=' + encodeURIComponent(snapshotOrderId) + '&q=' + encodeURIComponent(query));
      // Re-check: modal mungkin sudah ditutup saat await berlangsung
      if (!paymentContext || paymentContext.order_id !== snapshotOrderId) return;
      const rows = payload.rows || [];
      if (!rows.length && options.showIfEmpty) {
        renderPaymentVoucherSuggestions(rows);
        return;
      }
      if (!rows.length) {
        if (paymentVoucherStatus) {
          paymentVoucherStatus.textContent = 'Tidak ada voucher yang cocok.';
          paymentVoucherStatus.className = 'small text-muted mt-1';
        }
        if (paymentVoucherSuggestions) {
          paymentVoucherSuggestions.innerHTML = '';
          paymentVoucherSuggestions.style.display = 'none';
        }
        return;
      }
      if (options.preferExact && query !== '') {
        const exact = rows.find((row) => String(row.voucher_code || '').toUpperCase() === query.toUpperCase());
        if (exact) {
          if (!exact.ok) {
            if (paymentVoucherStatus) {
              paymentVoucherStatus.textContent = exact.message || 'Voucher tidak bisa dipakai.';
              paymentVoucherStatus.className = 'small text-danger mt-1';
            }
            renderPaymentVoucherSuggestions([exact]);
            return;
          }
          renderPaymentVoucherSelection(exact);
          return;
        }
        if (rows.length === 1 && rows[0].ok) {
          renderPaymentVoucherSelection(rows[0]);
          return;
        }
      }
      renderPaymentVoucherSuggestions(rows);
    } catch (error) {
      if (paymentVoucherStatus) {
        paymentVoucherStatus.textContent = error && error.message ? error.message : 'Gagal memeriksa voucher.';
        paymentVoucherStatus.className = 'small text-danger mt-1';
      }
      if (paymentVoucherSuggestions) {
        paymentVoucherSuggestions.innerHTML = '';
        paymentVoucherSuggestions.style.display = 'none';
      }
    }
  }

  async function openPaymentModal() {
    if (!canOpenPayment()) {
      throw new Error('Pilih order confirmed yang siap dibayar dulu.');
    }
    paymentPrepareInFlight = true;
    updateActionState();
    try {
      const json = await getJson('<?php echo site_url('pos/orders/payment/prepare'); ?>/' + Number(order.id || 0));
      paymentContext = json.payment || null;
      const seedAmount = Number(paymentContext && paymentContext.due_total ? paymentContext.due_total : 0);
      paymentRows = seedAmount > 0 ? [defaultPaymentRow(seedAmount)] : [];
      paymentActiveRowIndex = 0;
      if (paymentMeta) {
        paymentMeta.textContent = `${String(paymentContext && paymentContext.order_no ? paymentContext.order_no : '-')} | ${String(paymentContext && paymentContext.customer_name ? paymentContext.customer_name : 'Walk in')}`;
      }
      if (paymentNotes) paymentNotes.value = '';
      resetPaymentVoucherSelection();
      if (paymentVoucherSearch) paymentVoucherSearch.disabled = !(paymentContext && paymentContext.can_edit_adjustment);
      if (paymentVoucherCheckButton) paymentVoucherCheckButton.disabled = !(paymentContext && paymentContext.can_edit_adjustment);
      if (paymentClearVoucherButton) paymentClearVoucherButton.disabled = !(paymentContext && paymentContext.can_edit_adjustment);
      renderPaymentRows();
      renderPaymentSummary();
      if (paymentModal) paymentModal.show();
      // Auto-tampilkan voucher member jika ada voucher aktif
      const autoVoucherCount = Number(paymentContext && paymentContext.loyalty_summary ? paymentContext.loyalty_summary.open_voucher_count || 0 : 0);
      if (autoVoucherCount > 0 && paymentContext && paymentContext.can_edit_adjustment) {
        setTimeout(() => searchPaymentVouchers({ preferExact: false }), 120);
      }
    } finally {
      paymentPrepareInFlight = false;
      updateActionState();
    }
  }

  function collectPaymentPayload() {
    const rows = paymentRows.filter((row) => Number(row.payment_method_id || 0) > 0 && parsePaymentAmount(row.paid_amount || 0) > 0);
    return {
      order_id: Number(order.id || 0),
      voucher_selection: paymentSelectedVoucher ? String(paymentSelectedVoucher.selection_value || '') : '',
      voucher_code: paymentSelectedVoucher ? String(paymentSelectedVoucher.voucher_code || '') : '',
      notes: paymentNotes ? paymentNotes.value.trim() : '',
      payment_method_ids: rows.map((row) => Number(row.payment_method_id || 0)),
      paid_amounts: rows.map((row) => parsePaymentAmount(row.paid_amount || 0)),
      reference_nos: rows.map((row) => String(row.reference_no || '').trim())
    };
  }

  function buildPaymentSuccessMessage(result) {
    const loyalty = result && result.loyalty ? result.loyalty : {};
    const parts = [`Payment ${String(result && result.payment_no ? result.payment_no : '').trim()} tersimpan.`];
    if (Number(result && result.deposit_applied_amount ? result.deposit_applied_amount : 0) > 0) {
      parts.push(`DP terpakai ${money(result.deposit_applied_amount || 0)}.`);
    }
    if (String(result && result.order_status ? result.order_status : '').toUpperCase() === 'PAID_PARTIAL') {
      parts.push(`Pembayaran sebagian tercatat. Sisa tagihan ${money(result && result.remaining_due ? result.remaining_due : 0)}.`);
    }
    if (Number(result && result.change_total ? result.change_total : 0) > 0) {
      parts.push(`Kembalian ${money(result.change_total || 0)}.`);
    }
    if (Number(loyalty.point_earned || 0) > 0) {
      parts.push(`Poin bertambah ${number(loyalty.point_earned || 0, 0)}.`);
    }
    if (Number(loyalty.stamp_earned || 0) > 0) {
      parts.push(`Stamp bertambah ${number(loyalty.stamp_earned || 0, 0)}.`);
    }
    if (Array.isArray(loyalty.issued_vouchers) && loyalty.issued_vouchers.length) {
      parts.push(`Voucher baru: ${loyalty.issued_vouchers.join(', ')}.`);
    }
    return parts.join(' ');
  }

  async function submitPayment() {
    if (paymentSubmitInFlight) {
      throw new Error('Payment sedang diproses.');
    }
    const totals = paymentDraftTotals();
    const payload = collectPaymentPayload();
    if (!payload.payment_method_ids.length && totals.dueTotal > 0.009) {
      throw new Error('Pilih minimal satu metode pembayaran.');
    }
    if (totals.guardMessage) {
      throw new Error(totals.guardMessage);
    }
    // The backend recalculates the voucher itself. This only prevents the
    // browser from demanding a cash line after a selected voucher closes bill.
    const isVoucherOnlyPayment = totals.dueTotal <= 0.009 && totals.hasVoucherSelection;
    if (totals.appliedTotal <= 0 && totals.depositAppliedTotal <= 0 && !isVoucherOnlyPayment) {
      throw new Error('Masukkan nominal pembayaran yang valid.');
    }
    paymentSubmitInFlight = true;
    updateActionState();
    renderPaymentSummary();
    showSavingOverlay('PAYMENT');
    try {
      const result = await postPosTransactionJson('<?php echo site_url('pos/orders/payment/save'); ?>', payload);
      if (paymentModal) paymentModal.hide();
      if (Number(result.id || 0) > 0) {
        try {
          const payloadJson = await postJson(`<?php echo site_url('pos/orders/payment/print-targets'); ?>/${Number(result.id || 0)}`, {});
          const printResult = await directPrintTargets(payloadJson.direct_print_targets || []);
          showPrintFailureModal('Payment', printResult.failed || []);
        } catch (e) {
          showPrintFailureModal('Payment', [e && e.message ? e.message : 'Gagal menyiapkan direct print']);
        }
      }
      showInfoModal(buildPaymentSuccessMessage(result), 'Payment');
      resetOrder();
      await loadRecents();
    } finally {
      paymentSubmitInFlight = false;
      hideSavingOverlay();
      updateActionState();
      renderPaymentSummary();
    }
  }

  function renderMemberSelection() {
    if (!order.member_id) {
      memberSelected.innerHTML = '<div class="cashier-member-empty">Walk in customer. Transaksi ini belum memakai member.</div>';
      syncCustomerNameInputState();
      return;
    }
    memberSelected.innerHTML = `
      <div class="cashier-member-toolbar">
        <div>
          <div class="cashier-member-title">${escapeHtml(order.member_name || '-')}</div>
          <div class="cashier-member-meta">${escapeHtml(order.member_mobile_phone || '-')}</div>
          <div class="cashier-member-meta mt-1">Point ${number(order.member_point_balance || 0, 0)} | Stamp ${number(order.member_stamp_balance || 0, 0)}</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" id="cashier_clear_member">Lepas</button>
      </div>
    `;
    const btn = document.getElementById('cashier_clear_member');
    if (btn) btn.addEventListener('click', clearMemberSelection);
    syncCustomerNameInputState();
  }

  function clearMemberSelection() {
    const previousMemberName = String(order.member_name || '').trim();
    order.member_id = null;
    order.member_no = '';
    order.member_name = '';
    order.member_mobile_phone = '';
    order.member_point_balance = 0;
    order.member_stamp_balance = 0;
    if (String(order.customer_name || '').trim() === previousMemberName) {
      order.customer_name = '';
    }
    if (memberSearchInput) memberSearchInput.value = '';
    if (memberResult) memberResult.classList.add('d-none');
    renderMemberSelection();
  }

  function pickMember(row) {
    order.member_id = Number(row.id || 0) || null;
    order.member_no = row.member_no || '';
    order.member_name = row.member_name || '';
    order.customer_name = row.member_name || '';
    order.member_mobile_phone = row.mobile_phone || '';
    order.member_point_balance = Number(row.point_balance_cache || 0);
    order.member_stamp_balance = Number(row.stamp_balance_cache || 0);
    memberSearchInput.value = '';
    memberResult.classList.add('d-none');
    renderMemberSelection();
  }

  function recalcCart() {
    const total = order.lines.reduce((sum, line) => {
      const extraTotal = (Array.isArray(line.extras) ? line.extras : []).reduce((carry, extra) => carry + (Number(extra.qty || 0) * Number(extra.unit_price || 0)), 0);
      return sum + (Number(line.qty || 0) * Number(line.unit_price || 0)) + extraTotal;
    }, 0);
    const cartEntries = buildCartEntries();
    document.getElementById('cashier_grand_total').textContent = money(total);
    document.getElementById('cashier_summary_info').textContent = cartEntries.length
      ? `${cartEntries.length} item keranjang | ${order.lines.length} line simpan | Guest ${order.guest_count || 1} | DP diproses saat payment`
      : 'Belum ada baris item';
  }

  function normalizeBundleComponentQty(line) {
    const baseQty = Number(line && line.bundle_component_qty ? line.bundle_component_qty : 0);
    if (baseQty > 0) {
      return baseQty;
    }
    const currentQty = Number(line && line.qty ? line.qty : 0);
    return currentQty > 0 ? currentQty : 1;
  }

  function buildBundleEntry(lines, indexes, groupKey) {
    const first = lines[0] || {};
    const componentCount = lines.length;
    const bundleQty = lines.reduce((minQty, line) => {
      const componentQty = normalizeBundleComponentQty(line);
      const ratio = componentQty > 0 ? (Number(line.qty || 0) / componentQty) : 1;
      const normalizedRatio = ratio > 0 ? ratio : 1;
      return minQty === null ? normalizedRatio : Math.min(minQty, normalizedRatio);
    }, null);
    const extras = [];
    let productTotal = 0;
    let extraTotal = 0;
    let availabilityStatus = 'AVAILABLE';
    let lockedExistingLine = false;
    const notes = [];
    const components = lines.map((line) => {
      const componentQty = normalizeBundleComponentQty(line);
      const productAmount = Number(line.qty || 0) * Number(line.unit_price || 0);
      const lineExtras = Array.isArray(line.extras) ? line.extras : [];
      const lineExtraTotal = lineExtras.reduce((carry, extra) => carry + (Number(extra.qty || 0) * Number(extra.unit_price || 0)), 0);
      productTotal += productAmount;
      extraTotal += lineExtraTotal;
      lineExtras.forEach((extra) => {
        extras.push(Object.assign({}, extra, {
          product_name: line.product_name || '',
        }));
      });
      if (String(line.availability_status || '').toUpperCase() === 'OUT') {
        availabilityStatus = 'OUT';
      } else if (availabilityStatus !== 'OUT' && String(line.availability_status || '').toUpperCase() !== 'AVAILABLE') {
        availabilityStatus = 'CHECK';
      }
      if (isLockedExistingLine(line)) {
        lockedExistingLine = true;
      }
      const lineNote = String(line.notes || '').trim();
      if (lineNote !== '') {
        notes.push(lineNote);
      }
      return {
        product_code: line.product_code || '',
        product_name: line.product_name || '',
        component_qty: componentQty,
        line_qty: Number(line.qty || 0),
        unit_price: Number(line.unit_price || 0),
      };
    });
    return {
      kind: 'bundle',
      group_key: groupKey,
      line_indexes: indexes,
      lines,
      bundle_id: Number(first.bundle_id || 0) || null,
      bundle_name: first.bundle_name || 'Bundle',
      product_code: first.bundle_code || first.product_code || '',
      qty: Math.max(1, Math.round(Number(bundleQty || 1))),
      unit_price: Number(productTotal || 0) / Math.max(1, Number(bundleQty || 1)),
      product_total: productTotal,
      extra_total: extraTotal,
      subtotal: productTotal + extraTotal,
      extras,
      availability_status: availabilityStatus,
      locked: lockedExistingLine,
      notes: notes.length ? notes[0] : '',
      component_count: componentCount,
      components,
    };
  }

  function buildCartEntries() {
    const entries = [];
    const bundleGroups = {};
    order.lines.forEach((line, idx) => {
      const bundleId = Number(line.bundle_id || 0);
      if (bundleId > 0) {
        const rawKey = String(line.bundle_key || '').trim();
        const groupKey = rawKey !== '' ? rawKey : `bundle-saved-${bundleId}`;
        if (!bundleGroups[groupKey]) {
          bundleGroups[groupKey] = {
            lines: [],
            indexes: [],
          };
          entries.push({
            kind: 'bundle-group-ref',
            group_key: groupKey,
          });
        }
        bundleGroups[groupKey].lines.push(line);
        bundleGroups[groupKey].indexes.push(idx);
        return;
      }
      entries.push({
        kind: 'line',
        group_key: `line-${idx}`,
        line_indexes: [idx],
        line,
      });
    });
    return entries.map((entry) => {
      if (entry.kind !== 'bundle-group-ref') {
        return entry;
      }
      const group = bundleGroups[entry.group_key] || { lines: [], indexes: [] };
      return buildBundleEntry(group.lines, group.indexes, entry.group_key);
    });
  }

  function renderCart() {
    if (!order.lines.length) {
      cartList.innerHTML = '';
      cartEmpty.classList.remove('d-none');
      recalcCart();
      updateActionState();
      return;
    }
    cartEmpty.classList.add('d-none');
    const cartEntries = buildCartEntries();
    cartList.innerHTML = cartEntries.map((entry, entryIndex) => {
      const isBundle = entry.kind === 'bundle';
      const line = isBundle ? null : entry.line;
      const lockedExistingLine = !!entry.locked || isLockedExistingLine(line);
      const bundleChip = isBundle
        ? `<span class="cashier-chip bundle mt-2"><i class="ri-gift-2-line"></i>${escapeHtml(entry.bundle_name || 'Bundle')}</span>`
        : (line && line.bundle_id ? `<span class="cashier-chip bundle mt-2"><i class="ri-gift-2-line"></i>${escapeHtml(line.bundle_name || 'Bundle')}</span>` : '');
      const lineStateChip = lockedExistingLine
        ? '<span class="cashier-chip info mt-2">Item tersimpan</span>'
        : (isConfirmedOrder() ? '<span class="cashier-chip ok mt-2">Item baru</span>' : '');
      const extras = isBundle ? (Array.isArray(entry.extras) ? entry.extras : []) : (Array.isArray(line.extras) ? line.extras : []);
      const productTotal = isBundle ? Number(entry.product_total || 0) : (Number(line.qty || 0) * Number(line.unit_price || 0));
      const extraTotal = isBundle
        ? Number(entry.extra_total || 0)
        : extras.reduce((carry, extra) => carry + (Number(extra.qty || 0) * Number(extra.unit_price || 0)), 0);
      const lineSubtotal = isBundle ? Number(entry.subtotal || 0) : (productTotal + extraTotal);
      const extraSummary = extras.length
        ? `<div class="cashier-cart-extras">${extras.map((extra) => {
            const extraPrefix = extra.product_name ? `${escapeHtml(extra.product_name)}: ` : '';
            return `<span class="cashier-extra-pill">${extraPrefix}${escapeHtml(extra.extra_name || '-')} x${number(extra.qty || 0, 0)}${Number(extra.unit_price || 0) > 0 ? ' • ' + money((Number(extra.qty || 0) * Number(extra.unit_price || 0))) : ''}</span>`;
          }).join('')}</div>`
        : '';
      const bundleComponents = isBundle
        ? `<div class="cashier-cart-extras">${(entry.components || []).map((component) => `<span class="cashier-extra-pill">${escapeHtml(component.product_name || '-')} x${number(component.component_qty || 0, 0)}</span>`).join('')}</div>`
        : '';
      const extraCountChip = `<span class="cashier-cart-extra-count">Extra ${extras.length}</span>`;
      const removeButton = lockedExistingLine
        ? '<span class="cashier-chip warn">Void untuk ubah/hapus</span>'
        : `<button type="button" class="btn btn-sm btn-outline-danger cashier-remove-line" data-entry-index="${entryIndex}">Hapus</button>`;
      const displayTitle = isBundle ? (entry.bundle_name || 'Bundle') : (line.product_name || '-');
      const displaySub = isBundle
        ? `${entry.component_count || 0} produk`
        : (line.product_code || '-');
      const qtyValue = isBundle ? Number(entry.qty || 1) : Number(line.qty || 1);
      const noteValue = isBundle ? (entry.notes || '') : (line.notes || '');
      const aturButton = isBundle
        ? ''
        : `<button type="button" class="btn btn-sm btn-outline-warning cashier-edit-extra" data-index="${entry.line_indexes[0]}" ${lockedExistingLine ? 'disabled title="Item lama tidak bisa diubah dari kasir"' : ''}>Atur</button>`;
      return `
        <div class="cashier-cart-item${lockedExistingLine ? ' is-locked' : ''}">
          <div class="cashier-cart-item-head">
            <div>
              <div class="cashier-cart-item-title">${escapeHtml(displayTitle)}</div>
              <div class="cashier-cart-item-sub">${escapeHtml(displaySub)}</div>
              ${bundleChip}
              ${lineStateChip}
            </div>
            ${removeButton}
          </div>
          <div class="cashier-cart-editor-row mt-2">
            <div>
              <label class="form-label small text-muted mb-1">Qty</label>
              <div class="cashier-qty-stepper">
                <button type="button" class="cashier-line-qty-minus" data-entry-index="${entryIndex}" ${lockedExistingLine ? 'disabled' : ''}>-</button>
                <input type="number" min="1" step="1" class="cashier-line-qty" data-entry-index="${entryIndex}" value="${qtyValue}" ${lockedExistingLine ? 'readonly disabled' : ''}>
                <button type="button" class="cashier-line-qty-plus" data-entry-index="${entryIndex}" ${lockedExistingLine ? 'disabled' : ''}>+</button>
              </div>
            </div>
            <div>
              <label class="form-label small text-muted mb-1">Catatan</label>
              <input type="text" class="form-control form-control-sm cashier-line-note cashier-inline-note" data-entry-index="${entryIndex}" value="${escapeHtml(noteValue)}" placeholder="Catatan line" ${lockedExistingLine ? 'readonly' : ''}>
            </div>
          </div>
          ${bundleComponents}
          ${extraSummary}
          <div class="cashier-cart-price-breakdown">
            <div class="cashier-cart-price-metric">
              <span class="label">Harga Produk</span>
              <strong>${money(productTotal)}</strong>
            </div>
            <div class="cashier-cart-price-metric">
              <span class="label">Harga Extra</span>
              <strong>${money(extraTotal)}</strong>
            </div>
            <div class="cashier-cart-price-metric is-subtotal">
              <span class="label">Subtotal</span>
              <strong>${money(lineSubtotal)}</strong>
            </div>
          </div>
          <div class="cashier-cart-line-bottom">
            <div class="cashier-cart-line-left">
              ${extraCountChip}
              ${availabilityChip(isBundle ? entry.availability_status : line.availability_status)}
              ${aturButton}
            </div>
            <div class="fw-bold">${money(lineSubtotal)}</div>
          </div>
        </div>
      `;
    }).join('');

    function syncEntryQty(entryIndex, nextQty) {
      const entry = cartEntries[entryIndex];
      if (!entry) return;
      if (entry.kind === 'bundle') {
        const bundleQty = Math.max(1, Number(nextQty || 1));
        entry.line_indexes.forEach((lineIndex) => {
          const targetLine = order.lines[lineIndex];
          if (!targetLine || !canEditCartLine(targetLine)) {
            return;
          }
          const componentQty = normalizeBundleComponentQty(targetLine);
          targetLine.qty = Math.max(componentQty, componentQty * bundleQty);
          (Array.isArray(targetLine.extras) ? targetLine.extras : []).forEach((extra) => {
            extra.qty = Math.max(1, Number(targetLine.qty || 1));
          });
        });
        renderCart();
        return;
      }
      const lineIndex = Number(entry.line_indexes[0] || 0);
      if (!canEditCartLine(order.lines[lineIndex])) {
        return;
      }
      order.lines[lineIndex].qty = Math.max(1, Number(nextQty || 1));
      (Array.isArray(order.lines[lineIndex].extras) ? order.lines[lineIndex].extras : []).forEach((extra) => {
        extra.qty = Math.max(1, Number(order.lines[lineIndex].qty || 1));
      });
      renderCart();
    }
    cartList.querySelectorAll('.cashier-line-qty').forEach((el) => el.addEventListener('input', () => {
      const entryIndex = Number(el.dataset.entryIndex || 0);
      syncEntryQty(entryIndex, el.value || 1);
    }));
    cartList.querySelectorAll('.cashier-line-qty-minus').forEach((el) => el.addEventListener('click', () => {
      const entryIndex = Number(el.dataset.entryIndex || 0);
      const entry = cartEntries[entryIndex];
      const currentQty = entry ? Number(entry.qty || (entry.line ? entry.line.qty : 1) || 1) : 1;
      syncEntryQty(entryIndex, Math.max(1, currentQty - 1));
    }));
    cartList.querySelectorAll('.cashier-line-qty-plus').forEach((el) => el.addEventListener('click', () => {
      const entryIndex = Number(el.dataset.entryIndex || 0);
      const entry = cartEntries[entryIndex];
      const currentQty = entry ? Number(entry.qty || (entry.line ? entry.line.qty : 1) || 1) : 1;
      syncEntryQty(entryIndex, Math.max(1, currentQty + 1));
    }));
    cartList.querySelectorAll('.cashier-line-note').forEach((el) => el.addEventListener('input', () => {
      const entryIndex = Number(el.dataset.entryIndex || 0);
      const entry = cartEntries[entryIndex];
      if (!entry) return;
      const noteValue = el.value || '';
      entry.line_indexes.forEach((lineIndex) => {
        if (!canEditCartLine(order.lines[lineIndex])) {
          return;
        }
        order.lines[lineIndex].notes = noteValue;
      });
    }));
    cartList.querySelectorAll('.cashier-remove-line').forEach((el) => el.addEventListener('click', () => {
      const entryIndex = Number(el.dataset.entryIndex || 0);
      const entry = cartEntries[entryIndex];
      if (!entry) return;
      const removableIndexes = entry.line_indexes.filter((lineIndex) => canEditCartLine(order.lines[lineIndex]));
      if (!removableIndexes.length) {
        return;
      }
      order.lines = order.lines.filter((row, rowIndex) => removableIndexes.indexOf(rowIndex) === -1);
      renderCart();
    }));
    cartList.querySelectorAll('.cashier-edit-extra').forEach((el) => el.addEventListener('click', () => {
      openExtraChooser(Number(el.dataset.index || 0)).catch((e) => alert(e.message || 'Gagal memuat extra'));
    }));

    recalcCart();
    updateActionState();
  }

  async function fetchExtraGroups(productId) {
    const normalizedId = Number(productId || 0);
    if (normalizedId <= 0) return [];
    if (extraOptionCache[normalizedId]) {
      return extraOptionCache[normalizedId];
    }
    const params = new URLSearchParams();
    params.set('product_id', String(normalizedId));
    const json = await getJson('<?php echo site_url('pos/orders/draft/extra-options'); ?>?' + params.toString());
    extraOptionCache[normalizedId] = Array.isArray(json.groups) ? json.groups : [];
    return extraOptionCache[normalizedId];
  }

  function renderExtraGroups(groups, line) {
    if (extraGroupCount) {
      extraGroupCount.textContent = `${Array.isArray(groups) ? groups.length : 0} group`;
    }
    if (!Array.isArray(groups) || !groups.length) {
      extraGroupsContainer.innerHTML = '<div class="cashier-empty">Produk ini belum punya extra aktif yang ditampilkan di kasir.</div>';
      return;
    }
    const selectedMap = {};
    (Array.isArray(line.extras) ? line.extras : []).forEach((extra) => {
      selectedMap[Number(extra.extra_id || 0)] = extra;
    });
    extraGroupsContainer.innerHTML = `<div class="cashier-extra-groups-grid">${groups.map((group) => `
      <div class="cashier-extra-group" data-group-id="${Number(group.extra_group_id || 0)}" data-min-select="${Number(group.min_select || 0)}" data-max-select="${Number(group.max_select || 0)}" data-required="${Number(group.is_required || 0)}">
        <div class="cashier-extra-group-head">
          <div>
            <div class="cashier-extra-group-title">${escapeHtml(group.group_name || '-')}</div>
            <div class="cashier-extra-group-meta">${escapeHtml(group.group_code || '-')}</div>
          </div>
          <div class="cashier-extra-group-badges">
            ${Number(group.is_required || 0) === 1 ? '<span class="cashier-chip warn">Wajib pilih</span>' : '<span class="cashier-chip info">Opsional</span>'}
            <span class="cashier-chip info">Min ${Number(group.min_select || 0)}</span>
            <span class="cashier-chip info">Max ${Number(group.max_select || 0) || 1}</span>
          </div>
        </div>
        <div class="cashier-extra-item-list">
          ${(Array.isArray(group.items) ? group.items : []).map((item) => {
            const selected = selectedMap[Number(item.extra_id || 0)] || null;
            const inputType = (Number(group.is_required || 0) === 1 && Number(group.max_select || 0) === 1) ? 'radio' : 'checkbox';
            const inputName = inputType === 'radio'
              ? `cashier_extra_group_${Number(group.extra_group_id || 0)}`
              : `cashier_extra_group_${Number(group.extra_group_id || 0)}_${Number(item.extra_id || 0)}`;
            return `
              <div class="cashier-extra-item">
                <div class="cashier-extra-item-row">
                  <div>
                    <div class="form-check mb-1">
                      <input class="form-check-input cashier-extra-check" type="${inputType}" name="${inputName}" id="extra_${Number(item.extra_id || 0)}" data-extra-id="${Number(item.extra_id || 0)}" ${selected ? 'checked' : ''}>
                      <label class="form-check-label fw-semibold" for="extra_${Number(item.extra_id || 0)}">${escapeHtml(item.extra_name || '-')}</label>
                    </div>
                    <div class="cashier-mini-note">${escapeHtml(item.extra_code || '-')} | ${escapeHtml(item.extra_type || '-')}</div>
                    <div class="mt-2"><span class="cashier-extra-qty-note cashier-extra-qty-label" data-extra-id="${Number(item.extra_id || 0)}">Qty ikut produk: ${number(line.qty || 1, 0)}</span></div>
                  </div>
                  <div>
                    <label class="form-label small text-muted mb-1">Harga</label>
                    <input type="number" step="0.01" class="form-control form-control-sm cashier-extra-price cashier-extra-price-input" data-extra-id="${Number(item.extra_id || 0)}" value="${selected ? Number(selected.unit_price || 0) : Number(item.selling_price || 0)}">
                  </div>
                </div>
              </div>
            `;
          }).join('')}
        </div>
      </div>
    `).join('')}</div>`;
    extraGroupsContainer.querySelectorAll('.cashier-extra-check').forEach((input) => {
      const refreshSelectedState = () => {
        extraGroupsContainer.querySelectorAll('.cashier-extra-item').forEach((item) => item.classList.remove('selected'));
        extraGroupsContainer.querySelectorAll('.cashier-extra-check:checked').forEach((checked) => {
          checked.closest('.cashier-extra-item')?.classList.add('selected');
        });
      };
      input.addEventListener('change', refreshSelectedState);
      refreshSelectedState();
    });
  }

  function cloneLine(line) {
    return {
      id: Number(line.id || 0) || null,
      product_id: Number(line.product_id || 0),
      bundle_id: line.bundle_id ? Number(line.bundle_id) : null,
      bundle_key: line.bundle_key || '',
      bundle_name: line.bundle_name || '',
      bundle_code: line.bundle_code || '',
      bundle_component_qty: normalizeBundleComponentQty(line),
      product_code: line.product_code || '',
      product_name: line.product_name || '',
      product_division_name: line.product_division_name || '-',
      availability_status: line.availability_status || 'CHECK',
      qty: Math.max(1, Number(line.qty || 1)),
      unit_price: Number(line.unit_price || 0),
      hpp_live_snapshot: Number(line.hpp_live_snapshot || 0),
      notes: line.notes || '',
      extras: Array.isArray(line.extras) ? line.extras.map((extra) => ({
        id: Number(extra.id || 0) || null,
        extra_id: Number(extra.extra_id || 0),
        extra_name: extra.extra_name || '',
        extra_type: extra.extra_type || '',
        qty: Math.max(1, Number(extra.qty || 1)),
        unit_price: Number(extra.unit_price || 0),
        notes: extra.notes || ''
      })) : []
    };
  }

  function buildProductDraftLine(row) {
    return {
      id: null,
      product_id: Number(row.id || 0),
      bundle_id: null,
      bundle_key: '',
      bundle_name: '',
      bundle_code: '',
      bundle_component_qty: null,
      product_code: row.product_code || '',
      product_name: row.product_name || '',
      product_division_name: row.product_division_name || '-',
      availability_status: row.availability_status || 'CHECK',
      qty: 1,
      unit_price: Number(row.selling_price || 0),
      hpp_live_snapshot: Number(row.availability_hpp_live_snapshot || row.hpp_live_cache || row.hpp_standard || 0),
      notes: '',
      extras: []
    };
  }

  function hydrateExtraDraftFields(line, isEdit) {
    if (extraProductNameInput) {
      extraProductNameInput.value = `${line.product_name || '-'}${line.product_code ? ' | ' + line.product_code : ''}`;
    }
    if (extraLineQtyInput) {
      extraLineQtyInput.value = Math.max(1, Number(line.qty || 1));
    }
    if (extraLineNoteInput) {
      extraLineNoteInput.value = line.notes || '';
    }
    if (extraBasePriceInput) {
      extraBasePriceInput.value = money(line.unit_price || 0);
    }
    if (extraSaveButton) {
      extraSaveButton.textContent = isEdit ? 'Simpan Perubahan' : 'Simpan ke Keranjang';
    }
  }

  function syncExtraQtyLabels(qtyValue) {
    extraGroupsContainer.querySelectorAll('.cashier-extra-qty-label').forEach((el) => {
      el.textContent = `Qty ikut produk: ${number(qtyValue || 1, 0)}`;
    });
  }

  async function openExtraChooser(index) {
    const sourceLine = order.lines[index];
    if (!sourceLine) {
      throw new Error('Line order tidak ditemukan.');
    }
    if (!canEditCartLine(sourceLine)) {
      throw new Error('Item transaksi yang sudah tersimpan tidak bisa diubah dari kasir. Gunakan void untuk pengurangan atau pembatalan item.');
    }
    activeExtraLineIndex = index;
    activeExtraDraft = {
      mode: 'edit',
      lineIndex: index,
      line: cloneLine(sourceLine)
    };
    const line = activeExtraDraft.line;
    extraMeta.textContent = `${line.product_name || '-'} | ${line.product_code || '-'} | Atur qty, catatan, dan extra`;
    hydrateExtraDraftFields(line, true);
    if (extraGroupCount) extraGroupCount.textContent = 'Memuat...';
    extraGroupsContainer.innerHTML = '<div class="cashier-empty">Memuat extra produk...</div>';
    extraModal.show();
    const groups = await fetchExtraGroups(line.product_id);
    renderExtraGroups(groups, line);
    syncExtraQtyLabels(line.qty || 1);
  }

  async function openProductConfigurator(row) {
    activeExtraLineIndex = null;
    activeExtraDraft = {
      mode: 'create',
      lineIndex: null,
      line: buildProductDraftLine(row)
    };
    const line = activeExtraDraft.line;
    extraMeta.textContent = `${line.product_name || '-'} | ${line.product_code || '-'} | Pilih extra, qty, dan catatan sebelum masuk ke keranjang`;
    hydrateExtraDraftFields(line, false);
    if (extraGroupCount) extraGroupCount.textContent = 'Memuat...';
    extraGroupsContainer.innerHTML = '<div class="cashier-empty">Memuat extra produk...</div>';
    extraModal.show();
    const groups = await fetchExtraGroups(line.product_id);
    renderExtraGroups(groups, line);
    syncExtraQtyLabels(line.qty || 1);
  }

  function collectExtraSelection() {
    if (!activeExtraDraft || !activeExtraDraft.line) {
      throw new Error('Line extra tidak aktif.');
    }
    const line = activeExtraDraft.line;
    line.qty = Math.max(1, Number(extraLineQtyInput ? (extraLineQtyInput.value || 1) : 1));
    line.notes = extraLineNoteInput ? (extraLineNoteInput.value || '') : '';
    const groups = extraOptionCache[Number(line.product_id || 0)] || [];
    const selection = [];
    groups.forEach((group) => {
      let selectedCount = 0;
      (Array.isArray(group.items) ? group.items : []).forEach((item) => {
        const extraId = Number(item.extra_id || 0);
        const checked = extraGroupsContainer.querySelector(`.cashier-extra-check[data-extra-id="${extraId}"]`);
        if (!checked || !checked.checked) {
          return;
        }
        selectedCount += 1;
        const priceInput = extraGroupsContainer.querySelector(`.cashier-extra-price[data-extra-id="${extraId}"]`);
        selection.push({
          extra_id: extraId,
          extra_name: item.extra_name || '',
          extra_type: item.extra_type || '',
          qty: Math.max(1, Number(line.qty || 1)),
          unit_price: Number(priceInput ? priceInput.value || item.selling_price || 0 : item.selling_price || 0),
          notes: ''
        });
      });
      const minSelect = Number(group.min_select || 0);
      const maxSelect = Number(group.max_select || 0);
      if (Number(group.is_required || 0) === 1 && selectedCount === 0) {
        throw new Error(`Group extra ${group.group_name || '-'} wajib dipilih minimal ${minSelect || 1} item.`);
      }
      if (minSelect > 0 && selectedCount > 0 && selectedCount < minSelect) {
        throw new Error(`Group extra ${group.group_name || '-'} minimal harus memilih ${minSelect} item.`);
      }
      if (maxSelect > 0 && selectedCount > maxSelect) {
        throw new Error(`Group extra ${group.group_name || '-'} maksimal ${maxSelect} item.`);
      }
    });
    line.extras = selection;
    if (activeExtraDraft.mode === 'edit' && activeExtraDraft.lineIndex != null && order.lines[activeExtraDraft.lineIndex]) {
      order.lines[activeExtraDraft.lineIndex] = cloneLine(line);
      return;
    }
    order.lines.push(cloneLine(line));
  }

  function renderCatalogRows(rows) {
    if (!rows.length) {
      if (catalogCount) catalogCount.textContent = '0 item';
      if (catalogHint) {
        catalogHint.textContent = searchMode === 'BUNDLE' ? 'Belum ada bundle yang cocok' : currentDivisionLabel();
      }
      catalogResult.innerHTML = '<div class="cashier-empty">Tidak ada item yang cocok dengan filter katalog ini.</div>';
      return;
    }
    if (catalogCount) catalogCount.textContent = `${rows.length} item`;
    if (catalogHint) {
      const modeLabel = searchMode === 'BUNDLE' ? 'Bundle aktif' : currentDivisionLabel();
      catalogHint.textContent = catalogState.q ? `${modeLabel} | hasil pencarian` : modeLabel;
    }
    catalogResult.innerHTML = `<div class="cashier-product-grid">${
      rows.map((row) => {
        const isBundle = searchMode === 'BUNDLE';
      const title = isBundle ? (row.bundle_name || '-') : (row.product_name || '-');
      return `
          <div class="cashier-product-card" data-row="${encodeURIComponent(JSON.stringify(row))}">
            ${productMediaHtml(row)}
            <div class="cashier-product-name">${escapeHtml(title)}</div>
            <div class="cashier-catalog-chip-row">
              ${availabilityChip(row.availability_status || 'CHECK')}
              ${stockChip(row)}
              ${isBundle ? '<span class="cashier-chip bundle">Bundle</span>' : ''}
            </div>
            ${row.bottleneck_name_snapshot ? `<div class="cashier-mini-note mt-2">Bottleneck: ${escapeHtml(row.bottleneck_name_snapshot)}</div>` : ''}
            <div class="d-flex justify-content-between align-items-center mt-3">
              <div class="cashier-product-price">${money(row.selling_price || 0)}</div>
              <div class="cashier-mini-note">${isBundle ? 'Paket kasir' : 'Produk'}</div>
            </div>
          </div>
        `;
      }).join('')
    }</div>`;
    catalogResult.querySelectorAll('.cashier-product-card').forEach((item) => item.addEventListener('click', () => {
      const row = JSON.parse(decodeURIComponent(item.dataset.row));
      if (searchMode === 'BUNDLE') addBundleRows(row);
      else openProductConfigurator(row).catch((e) => alert(e.message || 'Gagal membuka konfigurasi produk'));
    }));
  }

  function addBundleRows(bundle) {
    const bundleId = Number(bundle.id || 0);
    const existing = order.lines.filter((line) => Number(line.bundle_id || 0) === bundleId && canEditCartLine(line));
    if (existing.length) {
      existing.forEach((line) => {
        const source = (bundle.items || []).find((item) => Number(item.product_id || 0) === Number(line.product_id || 0));
        if (source) line.qty = Number(line.qty || 0) + Number(source.qty || 0);
      });
    } else {
      const bundleKey = `bundle-${bundleId}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
      (bundle.items || []).forEach((item) => {
        order.lines.push({
          id: null,
          product_id: Number(item.product_id || 0),
          bundle_id: bundleId,
          bundle_key: bundleKey,
          bundle_name: bundle.bundle_name || '',
          bundle_code: bundle.bundle_code || '',
          bundle_component_qty: Number(item.qty || 0) > 0 ? Number(item.qty || 0) : 1,
          product_code: item.product_code || '',
          product_name: item.product_name || '',
          product_division_name: item.product_division_name || '-',
          availability_status: item.availability_status || bundle.availability_status || 'CHECK',
          qty: Number(item.qty || 0),
          unit_price: Number(item.unit_price || 0),
          hpp_live_snapshot: Number(item.hpp_live_snapshot || item.hpp_standard || 0),
          notes: bundle.bundle_name ? `[Bundle] ${bundle.bundle_name}` : '',
          extras: []
        });
      });
    }
    productSearchInput.value = '';
    renderCart();
  }

  async function loadCatalog() {
    const q = productSearchInput ? productSearchInput.value.trim() : '';
    catalogState.q = q;
    syncHeaderToOrder();
    if (!order.outlet_id) {
      catalogResult.innerHTML = '<div class="cashier-empty">Kasir belum aktif di outlet tertentu. Buka sesi kasir dulu.</div>';
      if (catalogCount) catalogCount.textContent = '0 item';
      return;
    }
    if (catalogHint) {
      catalogHint.textContent = searchMode === 'BUNDLE' ? 'Memuat bundle...' : 'Memuat katalog...';
    }
    catalogResult.innerHTML = '<div class="cashier-empty">Memuat katalog kasir...</div>';
    const endpoint = searchMode === 'BUNDLE'
      ? '<?php echo site_url('pos/cashier/bundles'); ?>'
      : '<?php echo site_url('pos/cashier/catalog'); ?>';
    const params = new URLSearchParams();
    params.set('q', q);
    params.set('outlet_id', order.outlet_id);
    // Bundle mode is cross-division by design. Do not inherit the last product
    // division selected by the cashier.
    params.set('division_id', String(searchMode === 'BUNDLE' ? 0 : (catalogState.division_id || 0)));
    params.set('limit', String(catalogState.limit || 32));
    const json = await getJson(endpoint + '?' + params.toString());
    renderCatalogRows(json.rows || []);
  }

  async function loadRecents() {
    const p = new URLSearchParams();
    p.set('q', recentState.q);
    p.set('status', recentState.status);
    p.set('workspace_mode', 'MIXED');
    p.set('cashier_recent', '1');
    p.set('outlet_id', recentState.outlet_id);
    p.set('page', recentState.page);
    p.set('limit', recentState.limit);
    const json = await getJson('<?php echo site_url('pos/orders/draft/data'); ?>?' + p.toString());
    const rows = json.rows || [];
    const list = document.getElementById('cashier_recent_list');
    const empty = document.getElementById('cashier_recent_empty');
    if (!rows.length) {
      list.innerHTML = '';
      empty.classList.remove('d-none');
      return;
    }
    empty.classList.add('d-none');
    list.innerHTML = rows.map((row) => `
      <div class="cashier-recent-item${Number(row.id || 0) === Number(selectedRecentOrderId || 0) ? ' active' : ''}" data-id="${Number(row.id || 0)}">
        <div class="cashier-recent-top">
          <div class="cashier-recent-order-no">${escapeHtml(row.order_no || '-')}</div>
          <div class="cashier-recent-total">${money(row.grand_total || 0)}</div>
        </div>
        <div class="cashier-recent-bottom">
          <div class="cashier-recent-meta">
            <div class="cashier-recent-customer">${escapeHtml(customerDisplayName(row))}</div>
            <div class="cashier-recent-table">Meja ${escapeHtml(row.table_no || '-')}</div>
          </div>
          <div class="cashier-status-badges">
            ${orderStatusChip(row.status || '-')}
            ${stockCommitChip(row.stock_commit_status || '', row.status || '')}
          </div>
        </div>
      </div>
    `).join('');
    list.querySelectorAll('.cashier-recent-item').forEach((el) => el.addEventListener('click', () => loadDraft(Number(el.dataset.id || 0))));
  }

  async function loadDraft(id) {
    const json = await getJson('<?php echo site_url('pos/orders/draft/load'); ?>/' + id);
    const header = json.header || {};
    const lines = Array.isArray(json.lines) ? json.lines : [];
    order.id = Number(header.id || 0) || null;
    order.order_no = header.order_no || '';
    order.status = header.status || 'DRAFT';
    order.outlet_id = String(header.outlet_id || order.outlet_id || '');
    order.terminal_id = String(header.terminal_id || order.terminal_id || '');
    order.sales_channel_id = String(header.sales_channel_id || order.sales_channel_id || '');
    order.service_type = header.service_type || 'DINE_IN';
    order.guest_count = Number(header.guest_count || 1);
    order.table_no = header.table_no || '';
    order.member_id = Number(header.member_id || 0) || null;
    order.member_no = header.member_no || '';
    order.member_name = header.member_name || '';
    order.customer_name = header.customer_name || header.customer_display_name || header.member_name || '';
    order.member_mobile_phone = header.member_mobile_phone || '';
    order.member_point_balance = 0;
    order.member_stamp_balance = 0;
    order.notes = header.notes || '';
    selectedRecentOrderId = Number(header.id || id || 0) || null;
    order.lines = lines.map((line) => ({
      id: Number(line.id || 0) || null,
      product_id: Number(line.product_id || 0),
      bundle_id: Number(line.bundle_id || 0) || null,
      bundle_key: Number(line.bundle_id || 0) > 0 ? `saved-bundle-${Number(line.bundle_id || 0)}` : '',
      bundle_name: line.bundle_name || '',
      bundle_code: line.bundle_code || '',
      bundle_component_qty: Number(line.bundle_component_qty || 0) > 0 ? Number(line.bundle_component_qty || 0) : null,
      product_code: line.product_code || '',
      product_name: line.product_name || '',
      product_division_name: line.product_division_name || '-',
      availability_status: 'CHECK',
      qty: Number(line.qty || 0),
      unit_price: Number(line.unit_price || 0),
      hpp_live_snapshot: Number(line.hpp_live_snapshot || line.hpp_standard_snapshot || 0),
      notes: line.notes || '',
      extras: Array.isArray(line.extras) ? line.extras.map((extra) => ({
        id: Number(extra.id || 0) || null,
        extra_id: Number(extra.extra_id || 0),
        extra_name: extra.extra_name || '',
        extra_type: extra.extra_type || '',
        qty: Number(extra.qty || 0),
        unit_price: Number(extra.unit_price || 0),
        notes: extra.notes || ''
      })) : []
    }));
    if (salesChannelSelect) salesChannelSelect.value = order.sales_channel_id || '<?php echo $defaultSalesChannelId > 0 ? (string)$defaultSalesChannelId : ''; ?>';
    syncServiceTypeFromChannel(false);
    if (guestCount) guestCount.value = order.guest_count || 1;
    if (tableNoInput) tableNoInput.value = order.table_no || '';
    if (notesInput) notesInput.value = order.notes || '';
    renderMemberSelection();
    renderCart();
    loadRecents().catch(() => {});
  }

  async function saveDraft(silent = false, refreshRecents = true, allowDuringConfirm = false) {
    if (draftSaveInFlight || (confirmInFlight && !allowDuringConfirm)) {
      throw new Error('Draft sedang diproses. Tunggu sebentar.');
    }
    if (isConfirmedOrder()) {
      throw new Error('Order confirmed tidak bisa disimpan sebagai draft. Gunakan Simpan Transaksi untuk append item baru atau ubah header.');
    }
    draftSaveInFlight = true;
    updateActionState();
    syncHeaderToOrder();
    try {
      if (!activeSession) throw new Error('Kasir belum dibuka. Buka sesi kasir dulu.');
      const payload = buildOrderPayload();
      const json = await postPosTransactionJson('<?php echo site_url('pos/orders/draft/save'); ?>', payload);
      order.id = Number(json.id || 0) || order.id;
      order.order_no = json.order_no || order.order_no;
      order.status = 'DRAFT';
      selectedRecentOrderId = Number(order.id || 0) || null;
      if (!silent) showInfoModal('Draft tersimpan.', 'Draft');
      if (refreshRecents) {
        await loadRecents();
      }
      return json;
    } finally {
      draftSaveInFlight = false;
      updateActionState();
    }
  }

  async function deleteDraft() {
    if (!canDeleteDraft()) {
      throw new Error('Pilih draft order dulu sebelum menghapusnya.');
    }
    if (!window.confirm(`Hapus draft ${order.order_no || 'transaksi ini'}?`)) {
      return;
    }
    await postPosTransactionJson(`<?php echo site_url('pos/orders/draft/delete'); ?>/${Number(order.id || 0)}`, {});
    resetOrder();
    await loadRecents();
    showToast('Draft berhasil dihapus.', 'success', 'Draft', 2400);
  }

  function buildOrderPayload() {
    return {
        id: order.id,
        outlet_id: order.outlet_id,
        terminal_id: order.terminal_id,
        sales_channel_id: order.sales_channel_id,
        service_type: order.service_type,
        guest_count: order.guest_count,
        table_no: order.table_no,
        member_id: order.member_id,
        customer_name: order.customer_name,
        notes: order.notes,
        require_active_session: 1,
        lines: order.lines.map((line) => ({
          id: line.id,
          product_id: line.product_id,
          bundle_id: line.bundle_id,
          qty: line.qty,
          unit_price: line.unit_price,
          hpp_live_snapshot: line.hpp_live_snapshot,
          notes: line.notes || '',
          extras: (Array.isArray(line.extras) ? line.extras : []).map((extra) => ({
            id: extra.id,
            extra_id: extra.extra_id,
            qty: extra.qty,
            unit_price: extra.unit_price,
            notes: extra.notes || ''
          }))
        }))
      };
  }

  async function acknowledgeDirectPrintTarget(target, status, message) {
    const attemptId = Number(target && target.print_attempt_id || 0);
    if (!attemptId) return;
    try {
      await fetch('<?= site_url('pos/printers/attempts/ack') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify({attempt_id: attemptId, status: status, message: message || ''})
      });
    } catch (error) { /* Status monitor tidak boleh menghambat kasir. */ }
  }

  async function directPrintTargets(targets) {
    const rows = Array.isArray(targets) ? targets : [];
    if (!rows.length) {
      return { successCount: 0, failed: [] };
    }
    const failed = [];
    let successCount = 0;
    const jobs = [];
    for (const target of rows) {
      const printMode = String(target.print_mode || 'AUTO').toUpperCase();
      if (printMode === 'OFF') {
        void acknowledgeDirectPrintTarget(target, 'SKIPPED', 'Aturan cetak sedang tidak aktif.');
        continue;
      }
      if (printMode === 'ASK') {
        const approved = await window.PosDirectPrintPrompt.ask(target);
        if (!approved) {
          void acknowledgeDirectPrintTarget(target, 'SKIPPED', 'Cetak dilewati oleh kasir.');
          continue;
        }
      }
      const copies = Math.max(1, Number(target.copies || 1));
      const pythonPort = Number(target.python_port || 0);
      if (!pythonPort) {
        const message = 'python port belum valid';
        failed.push(`${target.printer_name || target.printer_code || 'Printer'}: ${message}`);
        void acknowledgeDirectPrintTarget(target, 'FAILED', message);
        continue;
      }
      jobs.push(window.PosDirectAgentPrint.send(target).then((body) => {
        successCount += copies;
        void acknowledgeDirectPrintTarget(target, 'SENT', body.message || 'Perintah diterima Local Agent.');
      }).catch((e) => {
        const message = e && e.message ? e.message : 'gagal cetak';
        failed.push(`${target.printer_name || target.printer_code || 'Printer'}: ${message}`);
        void acknowledgeDirectPrintTarget(target, 'FAILED', message);
      }));
    }
    await Promise.all(jobs);
    return { successCount, failed };
  }

  function openOrderReprintModal() {
    if (!canReprintSelectedOrder()) {
      throw new Error(orderReprintPrinters.length ? 'Pilih 1 order aktif dulu sebelum cetak ulang.' : 'Belum ada printer direct print aktif untuk kasir ini.');
    }
    renderOrderReprintPrinterOptions();
    if (orderReprintScopeSelect) {
      orderReprintScopeSelect.value = String(order.status || '').toUpperCase() === 'DRAFT' ? 'ALL' : String(orderReprintScopeSelect.value || 'LATEST').toUpperCase();
    }
    refreshOrderReprintModalState();
    if (orderReprintModal) {
      orderReprintModal.show();
    }
  }

  async function submitOrderReprint() {
    if (!canReprintSelectedOrder()) {
      throw new Error(orderReprintPrinters.length ? 'Pilih 1 order aktif dulu sebelum cetak ulang.' : 'Belum ada printer direct print aktif untuk kasir ini.');
    }

    const lineScope = orderReprintScopeSelect ? String(orderReprintScopeSelect.value || 'ALL').toUpperCase() : 'ALL';
    const printerId = orderReprintPrinterSelect ? Number(orderReprintPrinterSelect.value || 0) : 0;
    if (orderReprintSubmitButton) {
      orderReprintSubmitButton.disabled = true;
      orderReprintSubmitButton.textContent = 'Menyiapkan...';
    }

    try {
      const payloadJson = await postJson(`<?php echo site_url('pos/orders/reprint-print-targets'); ?>/${Number(order.id || 0)}`, {
        line_scope: lineScope,
        printer_id: printerId,
      });
      const targets = Array.isArray(payloadJson.direct_print_targets) ? payloadJson.direct_print_targets : [];
      if (!targets.length) {
        throw new Error(lineScope === 'LATEST'
          ? 'Tidak ada target cetak untuk item terbaru di order ini.'
          : 'Tidak ada printer aktif yang cocok untuk order ini.');
      }
      const printResult = await directPrintTargets(targets);
      const selectedPrinter = selectedOrderReprintPrinterMeta();
      const isCashierBillPrinter = !!selectedPrinter && String(selectedPrinter.printer_role || '').toUpperCase() === 'KASIR';
      const successLabel = isCashierBillPrinter
        ? 'Bill sementara berhasil dikirim ke printer kasir.'
        : (lineScope === 'LATEST'
        ? 'Item terbaru berhasil dikirim ulang ke printer.'
        : 'Order berhasil dikirim ulang ke printer.');
      showToast(successLabel, 'success', 'Cetak Ulang', 2800);
      showPrintFailureModal('Cetak Ulang Order', printResult.failed || []);
      if (orderReprintModal) {
        orderReprintModal.hide();
      }
    } finally {
      if (orderReprintSubmitButton) {
        orderReprintSubmitButton.disabled = false;
        orderReprintSubmitButton.textContent = defaultOrderReprintLabel;
      }
    }
  }

  async function confirmDraft() {
    if (confirmInFlight) {
      throw new Error('Transaksi sedang diproses. Tunggu sebentar.');
    }
    confirmInFlight = true;
    updateActionState();
    try {
      if (!order.lines.length) throw new Error('Tambahkan minimal 1 item sebelum confirm.');
      const json = await postPosTransactionJson('<?php echo site_url('pos/orders/draft/save-confirm'); ?>', buildOrderPayload());
      const confirmedOrderId = Number(json.id || 0);
      const snapshotId = Number(json.snapshot_id || 0);
      const runtimeJobId = Number(json.runtime_job_id || 0);
      const isAppendMode = !!json.append_mode;
      const isHeaderOnlyUpdate = !!json.header_only_update;
      const appendedLineCount = Number(json.appended_line_count || 0);
      const warningMessage = String(json.warning_message || '').trim();
      const stockCommitStatus = String(json.stock_commit_status || '').toUpperCase();

      if (confirmedOrderId > 0) {
        void loadRecents().catch(() => {});
        void loadCatalog().catch(() => {});
      }

      if (isHeaderOnlyUpdate) {
        showToast('Perubahan header transaksi tersimpan. Tidak ada item baru yang dicetak.', 'success', 'Transaksi', 2600);
      } else if (stockCommitStatus === 'NOT_REQUIRED') {
        showToast('Pesanan tersimpan. Stock commit dilewati karena recipe product belum tersedia.', 'success', 'Transaksi', 3200);
      } else if (isAppendMode) {
        showToast(
          appendedLineCount > 0
            ? `Perubahan transaksi tersimpan. Hanya ${appendedLineCount} line baru yang dicetak.`
            : 'Perubahan transaksi tersimpan.',
          'success',
          'Transaksi',
          2800
        );
      } else {
        showToast('Pesanan tersimpan. Tiket dicetak, sinkron stok masuk antrean.', 'success', 'Transaksi', 2800);
      }
      if (warningMessage) {
        showInfoModal(warningMessage, 'Resep Belum Ada');
      }
      if (runtimeJobId > 0) {
        showToast('Sinkronisasi stok berjalan di background.', 'info', 'Sinkronisasi Stok', 2600);
      }

      if (confirmedOrderId > 0 && snapshotId > 0) {
        void postJson(`<?php echo site_url('pos/orders/confirm-print-targets'); ?>/${confirmedOrderId}`, { snapshot_id: snapshotId }).then((payloadJson) => {
          return directPrintTargets(payloadJson.direct_print_targets || []);
        }).then((printResult) => {
          showPrintFailureModal('Transaksi', printResult.failed || []);
        }).catch((e) => {
          showPrintFailureModal('Transaksi', [e && e.message ? e.message : 'Gagal menyiapkan direct print']);
        });
      }

      if (confirmedOrderId > 0 && runtimeJobId > 0) {
        kickoffRuntimeJobSync(confirmedOrderId, runtimeJobId);
      }
      resetOrder();
    } finally {
      confirmInFlight = false;
      updateActionState();
    }
  }

  function renderReviewModal(mode) {
    syncHeaderToOrder();
    const title = document.getElementById('cashier_review_title');
    const meta = document.getElementById('cashier_review_meta');
    const list = document.getElementById('cashier_review_list');
    const total = document.getElementById('cashier_review_total');
    const hint = document.getElementById('cashier_review_hint');
    const reviewChannel = document.getElementById('cashier_review_channel');
    const reviewGuest = document.getElementById('cashier_review_guest');
    const reviewTableNo = document.getElementById('cashier_review_table_no');
    const reviewMember = document.getElementById('cashier_review_member');
    const reviewNotes = document.getElementById('cashier_review_notes');
    const selectedChannel = salesChannelSelect && salesChannelSelect.selectedOptions.length ? salesChannelSelect.selectedOptions[0].textContent.trim() : 'Walk In';
    const isAppendReview = mode === 'CONFIRM' && isConfirmedOrder();
    const existingLines = order.lines.filter((line) => isLockedExistingLine(line));
    const newLines = order.lines.filter((line) => !isLockedExistingLine(line));
    const reviewLines = isAppendReview ? newLines : order.lines;
    if (title) title.textContent = mode === 'CONFIRM' ? 'Review Simpan Transaksi' : 'Review Simpan Draft';
    if (meta) meta.textContent = mode === 'CONFIRM'
      ? (isAppendReview
        ? (newLines.length
          ? `Review hanya menampilkan ${newLines.length} line transaksi terbaru. ${existingLines.length} line lama tetap tersimpan dan tidak dicetak ulang.`
          : `Tidak ada line baru. ${existingLines.length} line lama tetap tersimpan dan tidak dicetak ulang.`)
        : 'Cek order singkat sebelum transaksi disimpan.')
      : 'Cek order singkat sebelum draft disimpan.';
    if (hint) hint.textContent = mode === 'CONFIRM'
      ? (isAppendReview
        ? 'Review difokuskan ke item baru. Pengurangan item lama tetap harus lewat void.'
        : 'Stok dipotong dan tiket langsung dicetak.')
      : 'Draft disimpan tanpa potong stok.';
    if (reviewChannel) reviewChannel.textContent = selectedChannel || 'Walk In';
    if (reviewGuest) reviewGuest.textContent = String(order.guest_count || 1);
    if (reviewTableNo) reviewTableNo.textContent = order.table_no || '-';
    if (reviewMember) reviewMember.textContent = customerDisplayName(order);
    if (reviewNotes) reviewNotes.textContent = order.notes || '-';
    if (total) total.textContent = money(reviewLines.reduce((sum, line) => sum + lineGrandTotal(line), 0));
    if (list) {
      if (!reviewLines.length) {
        list.innerHTML = '<div class="cashier-empty">Tidak ada item baru pada transaksi ini. Sistem hanya akan menyimpan perubahan header.</div>';
        return;
      }
      list.innerHTML = reviewLines.map((line) => {
        const extras = Array.isArray(line.extras) ? line.extras : [];
        return `
          <div class="cashier-review-item">
            <div class="d-flex justify-content-between align-items-start gap-3">
              <div>
                <div class="fw-bold">${escapeHtml(line.product_name || '-')}</div>
                <div class="cashier-review-line-meta">
                  <span class="cashier-chip info">Qty ${number(line.qty || 0, 0)}</span>
                  ${line.bundle_name ? `<span class="cashier-chip bundle">${escapeHtml(line.bundle_name)}</span>` : ''}
                  ${isLockedExistingLine(line) ? '<span class="cashier-chip info">Tersimpan</span>' : (isAppendReview ? '<span class="cashier-chip ok">Baru</span>' : '')}
                  ${line.notes ? `<span class="cashier-chip warn">Catatan</span>` : ''}
                </div>
              </div>
              <div class="fw-bold">${money(lineGrandTotal(line))}</div>
            </div>
            ${line.notes ? `<div class="cashier-mini-note mt-2">Catatan: ${escapeHtml(line.notes)}</div>` : ''}
            ${extras.length ? `<div class="cashier-review-extra">${extras.map((extra) => `<span class="cashier-extra-pill">${escapeHtml(extra.extra_name || '-')} x${number(extra.qty || 0, 0)}${Number(extra.unit_price || 0) > 0 ? ' • ' + money(extra.unit_price || 0) : ''}</span>`).join('')}</div>` : ''}
          </div>
        `;
      }).join('');
    }
    if (reviewSubmitButton) {
      reviewSubmitButton.textContent = mode === 'CONFIRM' ? 'Simpan Transaksi Sekarang' : 'Simpan Draft Sekarang';
      reviewSubmitButton.classList.toggle('btn-primary', true);
      reviewSubmitButton.classList.toggle('btn-danger', false);
    }
  }

  function openReviewModal(mode) {
    if (mode === 'DRAFT' && isConfirmedOrder()) {
      throw new Error('Order confirmed tidak bisa disimpan sebagai draft. Gunakan Simpan Transaksi untuk append item baru atau ubah header.');
    }
    if (!order.lines.length) throw new Error('Tambahkan minimal 1 item dulu.');
    pendingReviewAction = mode;
    renderReviewModal(mode);
    if (reviewModal) reviewModal.show();
  }

  async function submitReviewedOrder() {
    const mode = pendingReviewAction || 'DRAFT';
    if ((mode === 'CONFIRM' && confirmInFlight) || (mode !== 'CONFIRM' && draftSaveInFlight)) {
      return;
    }
    if (reviewSubmitButton) {
      reviewSubmitButton.disabled = true;
      reviewSubmitButton.textContent = mode === 'CONFIRM' ? 'Menyimpan transaksi...' : 'Menyimpan draft...';
    }
    try {
      if (reviewModal) reviewModal.hide();
      showSavingOverlay(mode);
      if (mode === 'CONFIRM') {
        await confirmDraft();
      } else {
        await saveDraft(true);
        showToast('Draft berhasil disimpan.', 'success', 'Draft', 2200);
      }
    } finally {
      hideSavingOverlay();
      if (reviewSubmitButton) {
        reviewSubmitButton.disabled = false;
        reviewSubmitButton.textContent = mode === 'CONFIRM' ? 'Simpan Transaksi Sekarang' : 'Simpan Draft Sekarang';
      }
      pendingReviewAction = null;
    }
  }

  function resetOrder() {
    order.id = null;
    order.order_no = '';
    order.status = 'DRAFT';
    order.outlet_id = activeSession ? String(activeSession.outlet_id || '') : '';
    order.terminal_id = activeSession ? String(activeSession.terminal_id || '') : '';
    order.sales_channel_id = '<?php echo $defaultSalesChannelId > 0 ? (string)$defaultSalesChannelId : ''; ?>';
    order.service_type = 'DINE_IN';
    order.guest_count = 1;
    order.table_no = '';
    order.member_id = null;
    order.member_no = '';
    order.member_name = '';
    order.customer_name = '';
    order.member_mobile_phone = '';
    order.member_point_balance = 0;
    order.member_stamp_balance = 0;
    order.notes = '';
    order.lines = [];
    selectedRecentOrderId = null;
    reversalPreview = null;
    if (guestCount) guestCount.value = 1;
    if (tableNoInput) tableNoInput.value = '';
    if (notesInput) notesInput.value = '';
    clearMemberSelection();
    if (salesChannelSelect) salesChannelSelect.value = '<?php echo $defaultSalesChannelId > 0 ? (string)$defaultSalesChannelId : ''; ?>';
    syncServiceTypeFromChannel();
    renderCart();
  }

  function reversalUsesStockReturn() {
    return !!document.getElementById('cashier_reversal_return')?.checked;
  }

  function refreshReversalPolicyCards() {
    const usesReturn = reversalUsesStockReturn();
    const adjustmentSelect = document.getElementById('cashier_reversal_adjustment');
    document.getElementById('cashier_reversal_return_card')?.classList.toggle('active', usesReturn);
    document.getElementById('cashier_reversal_adjust_card')?.classList.toggle('active', !usesReturn);
    reversalAdjustmentWrap?.classList.toggle('d-none', usesReturn);
    reversalReasonWrap?.classList.toggle('d-none', usesReturn);
    if (adjustmentSelect) {
      adjustmentSelect.disabled = usesReturn;
      if (usesReturn) {
        adjustmentSelect.value = 'NONE';
      } else if (!adjustmentSelect.value || adjustmentSelect.value === 'NONE') {
        adjustmentSelect.value = 'AUTO_WASTE';
      }
    }
    refreshReversalReasonHint();
  }

  function refreshReversalReasonHint() {
    if (!reversalReasonHint) {
      return;
    }
    if (reversalUsesStockReturn()) {
      reversalReasonHint.textContent = '';
      return;
    }
    const adjustmentMode = String(document.getElementById('cashier_reversal_adjustment')?.value || 'AUTO_WASTE').toUpperCase();
    if (adjustmentMode === 'AUTO_WASTE') {
      reversalReasonHint.textContent = 'Alasan ini disimpan sebagai reason waste. Bahan baku: waste_reason_code. Komponen: waste_reason_code.';
      return;
    }
    if (adjustmentMode === 'AUTO_SPOIL') {
      reversalReasonHint.textContent = 'Alasan ini disimpan sebagai reason spoil. Bahan baku: spoil_reason_code. Komponen: spoil_reason_code.';
      return;
    }
    reversalReasonHint.textContent = 'Alasan ini disimpan sebagai reason penyesuaian minus. Bahan baku: variance_reason_code. Komponen: adjustment_minus_reason_code.';
  }

  function setReversalSubmitState(isLoading) {
    reversalSubmitInFlight = !!isLoading;
    if (!saveVoidButton) {
      return;
    }
    saveVoidButton.disabled = reversalSubmitInFlight;
    saveVoidButton.innerHTML = reversalSubmitInFlight
      ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Menyimpan...'
      : defaultSaveVoidLabel;
  }

  function fillReversalReasonOptions() {
    if (!reversalReasonCode) {
      return;
    }
    const rows = Array.isArray(reversalReasonOptions[activeReversalKind()]) ? reversalReasonOptions[activeReversalKind()] : [];
    reversalReasonCode.innerHTML = ['<option value="">Pilih alasan...</option>']
      .concat(rows.map((row) => `<option value="${escapeHtml(row.code || '')}">${escapeHtml(row.label || '')}</option>`))
      .join('');
    if (reversalReasonOther) {
      reversalReasonOther.value = '';
      reversalReasonOther.classList.add('d-none');
    }
  }

  function refreshReasonOtherVisibility() {
    if (!reversalReasonCode || !reversalReasonOther) {
      return;
    }
    reversalReasonOther.classList.toggle('d-none', String(reversalReasonCode.value || '').toUpperCase() !== 'OTHER');
  }

  function resetReversalForm() {
    document.getElementById('cashier_reversal_return').checked = true;
    document.getElementById('cashier_reversal_adjust').checked = false;
    document.getElementById('cashier_reversal_adjustment').value = 'NONE';
    document.getElementById('cashier_reversal_reason').value = '';
    if (reversalReasonCode) {
      reversalReasonCode.value = '';
    }
    if (reversalReasonOther) {
      reversalReasonOther.value = '';
      reversalReasonOther.classList.add('d-none');
    }
    fillReversalReasonOptions();
    refreshReversalPolicyCards();
  }

  function setReversalSelectionState(checked) {
    document.querySelectorAll('.cashier-reversal-line').forEach((card) => {
      const productToggle = card.querySelector('.cashier-reversal-product-toggle');
      if (productToggle) {
        productToggle.checked = checked;
      }
      card.querySelectorAll('.cashier-reversal-extra-row').forEach((row) => {
        const extraToggle = row.querySelector('.cashier-reversal-extra-toggle');
        if (extraToggle) {
          extraToggle.checked = checked;
        }
      });
    });
    syncReversalSelections();
  }

  function syncReversalSelections() {
    document.querySelectorAll('.cashier-reversal-line').forEach((card) => {
      const productToggle = card.querySelector('.cashier-reversal-product-toggle');
      const productQty = card.querySelector('.cashier-reversal-product-qty');
      const extraRows = card.querySelectorAll('.cashier-reversal-extra-row');
      if (!productToggle) {
        return;
      }
      const productSelected = productToggle.checked;
      if (productQty) {
        productQty.disabled = !productSelected;
      }
      extraRows.forEach((row) => {
        const extraToggle = row.querySelector('.cashier-reversal-extra-toggle');
        const extraQty = row.querySelector('.cashier-reversal-extra-qty');
        const autoHint = row.querySelector('.cashier-reversal-extra-auto-hint');
        if (!extraToggle || !extraQty) {
          return;
        }
        if (productSelected) {
          extraToggle.checked = true;
          extraToggle.disabled = true;
          extraQty.disabled = true;
          autoHint?.classList.remove('d-none');
        } else {
          extraToggle.disabled = false;
          extraQty.disabled = !extraToggle.checked;
          autoHint?.classList.add('d-none');
        }
      });
    });
  }

  function finalReversalReason() {
    const auditNote = String(document.getElementById('cashier_reversal_reason').value || '').trim();
    if (reversalUsesStockReturn()) {
      return auditNote;
    }
    const selectedCode = String(reversalReasonCode?.value || '').trim();
    if (selectedCode === '') {
      throw new Error('Pilih alasan void ketika stok tidak dikembalikan.');
    }
    const rows = Array.isArray(reversalReasonOptions[activeReversalKind()]) ? reversalReasonOptions[activeReversalKind()] : [];
    const matched = rows.find((row) => String(row.code || '') === selectedCode);
    let reasonText = matched && matched.label ? String(matched.label) : selectedCode;
    if (selectedCode === 'OTHER') {
      const otherText = String(reversalReasonOther?.value || '').trim();
      if (otherText === '') {
        throw new Error('Isi alasan lainnya untuk void ini.');
      }
      reasonText = otherText;
    }
    return auditNote ? `${reasonText} | ${auditNote}` : reasonText;
  }

  function renderReversalPreview(json) {
    reversalPreview = json;
    document.getElementById('cashier_reversal_meta').textContent = `${json.order?.header?.order_no || '-'} | ${orderStatusLabel(json.order?.header?.status || '')} | ${customerDisplayName(json.order?.header || {})}`;
    const lines = Array.isArray(json.order?.lines) ? json.order.lines : [];
    const container = document.getElementById('cashier_reversal_lines');
    const emptyHint = document.getElementById('cashier_reversal_empty_hint');
    if (!lines.length) {
      emptyHint?.classList.remove('d-none');
      container.innerHTML = '';
      return;
    }
    emptyHint?.classList.add('d-none');
    resetReversalForm();
    container.innerHTML = lines.map((line) => {
      const extras = Array.isArray(line.extras) ? line.extras : [];
      const processingLabel = reversalProcessingLabel(line.process_status || '');
      const isProcessed = String(line.process_status || 'NOT_PROCESSED').toUpperCase() !== 'NOT_PROCESSED';
      return `
        <div class="cashier-reversal-line" data-line-id="${Number(line.id || 0)}">
          <div class="cashier-reversal-item-head">
            <label class="cashier-reversal-item-main mb-0">
              <input class="form-check-input cashier-reversal-product-toggle" type="checkbox">
              <span class="cashier-reversal-item-name">${escapeHtml(line.product_name || '-')}</span>
            </label>
            <div class="cashier-reversal-item-side">
              <span class="small text-muted">Qty</span>
              <input type="number" class="form-control form-control-sm cashier-reversal-product-qty cashier-reversal-qty-input" min="0" step="0.01" value="${Number(line.qty || 0)}" disabled>
            </div>
          </div>
          <div class="small text-muted mt-1">Status proses fisik: <strong>${escapeHtml(processingLabel)}</strong>${isProcessed ? ' • Sistem tetap rollback stok dan LOT lebih dulu sebelum menentukan return/adjustment.' : ''}</div>
          ${extras.length ? `<div class="cashier-reversal-extra-list">${extras.map((extra) => `
            <div class="cashier-reversal-extra-row" data-extra-id="${Number(extra.id || 0)}">
              <div class="cashier-reversal-item-head">
                <label class="cashier-reversal-item-main mb-0">
                  <input class="form-check-input cashier-reversal-extra-toggle" type="checkbox">
                  <span class="cashier-reversal-item-name">${escapeHtml(extra.extra_name || '-')}</span>
                </label>
                <div class="cashier-reversal-item-side">
                  <span class="small text-muted">Qty</span>
                  <input type="number" class="form-control form-control-sm cashier-reversal-extra-qty cashier-reversal-qty-input" min="0" step="0.01" value="${Number(extra.qty || 0)}" disabled>
                </div>
                <span class="cashier-reversal-extra-auto-hint small text-success d-none">Auto</span>
              </div>
            </div>`).join('')}</div>` : ''}
        </div>
      `;
    }).join('');
    syncReversalSelections();
    container.querySelectorAll('.cashier-reversal-product-toggle, .cashier-reversal-extra-toggle').forEach((field) => field.addEventListener('change', syncReversalSelections));
  }

  function buildReversalPayload() {
    if (!reversalPreview || !reversalPreview.order || !reversalPreview.order.header) {
      throw new Error('Preview reversal belum dimuat.');
    }
    const returnToStock = reversalUsesStockReturn();
    const adjustmentMode = returnToStock ? 'NONE' : (document.getElementById('cashier_reversal_adjustment').value || 'NONE');
    if (!returnToStock && adjustmentMode === 'NONE') {
      throw new Error('Pilih tipe adjustment ketika stok tidak dikembalikan.');
    }
    const reasonCode = String(reversalReasonCode?.value || 'OTHER').trim() || 'OTHER';
    const reason = finalReversalReason();
    const orderLineMap = new Map((reversalPreview.order.lines || []).map((line) => [Number(line.id || 0), line]));
    const lines = [];

    document.querySelectorAll('.cashier-reversal-line').forEach((card) => {
      const orderLineId = Number(card.dataset.lineId || 0);
      const sourceLine = orderLineMap.get(orderLineId);
      if (!sourceLine || orderLineId <= 0) {
        return;
      }
      const productToggle = card.querySelector('.cashier-reversal-product-toggle');
      const productQty = card.querySelector('.cashier-reversal-product-qty');
      const productSelected = !!(productToggle && productToggle.checked);
      const extraSelections = [];

      card.querySelectorAll('.cashier-reversal-extra-row').forEach((row) => {
        const extraToggle = row.querySelector('.cashier-reversal-extra-toggle');
        const extraQty = row.querySelector('.cashier-reversal-extra-qty');
        const orderLineExtraId = Number(row.dataset.extraId || 0);
        if (!extraToggle || !extraToggle.checked || orderLineExtraId <= 0) {
          return;
        }
        extraSelections.push({
          order_line_extra_id: orderLineExtraId,
          qty: Math.max(0, Number(extraQty?.value || 0)),
          processed_state: String(sourceLine.process_status || 'NOT_PROCESSED').toUpperCase(),
          return_to_stock: returnToStock,
          notes: reason,
        });
      });

      const qty = productSelected ? Math.max(0, Number(productQty?.value || 0)) : 0;
      if (!productSelected && !extraSelections.length) {
        return;
      }
      lines.push({
        order_line_id: orderLineId,
        qty,
        processed_state: String(sourceLine.process_status || 'NOT_PROCESSED').toUpperCase(),
        return_to_stock: returnToStock,
        notes: reason,
        extras: extraSelections.filter((extra) => extra.order_line_extra_id > 0 && extra.qty > 0),
      });
    });

    return {
      order_id: Number(reversalPreview.order.header.id || 0),
      return_to_stock: returnToStock ? 1 : 0,
      adjustment_mode: adjustmentMode,
      reason_code: reasonCode,
      reason,
      lines
    };
  }

  async function openReversalPreview() {
    if (!order.id) throw new Error('Simpan atau pilih order dulu sebelum preview void.');
    const json = await getJson('<?php echo site_url('pos/orders/reversal-preview'); ?>/' + order.id);
    renderReversalPreview(json);
    if (reversalModal) reversalModal.show();
  }

  async function triggerReversalDirectPrint(mode, documentId) {
    const normalizedMode = String(mode || 'VOID').toUpperCase();
    const safeId = Number(documentId || 0);
    if (safeId <= 0) {
      return;
    }
    const endpoint = normalizedMode === 'REFUND'
      ? `<?php echo site_url('pos/orders/refund-print-targets'); ?>/${safeId}`
      : `<?php echo site_url('pos/orders/void-print-targets'); ?>/${safeId}`;
    const actionLabel = normalizedMode === 'REFUND' ? 'Refund' : 'Void';
    try {
      const payloadJson = await postJson(endpoint, {});
      const printResult = await directPrintTargets(payloadJson.direct_print_targets || []);
      showPrintFailureModal(actionLabel, printResult.failed || []);
    } catch (e) {
      showPrintFailureModal(actionLabel, [e && e.message ? e.message : 'Gagal menyiapkan direct print']);
    }
  }

  async function submitReversal() {
    if (reversalSubmitInFlight) {
      return;
    }
    const reversalMode = activeReversalKind();
    const saveUrl = '<?php echo site_url('pos/orders/void/save'); ?>';
    const successLabel = 'Void';
    let completed = false;
    setReversalSubmitState(true);
    showSavingOverlay(reversalMode);
    try {
      const payload = buildReversalPayload();
      if (!payload.lines.length) {
        throw new Error('Pilih minimal satu item atau extra yang ingin dibatalkan.');
      }
      const json = await postPosTransactionJson(saveUrl, payload);
      if (reversalModal) reversalModal.hide();
      await triggerReversalDirectPrint(reversalMode, Number(json.id || 0));
      let successMessage = `${successLabel} berhasil disimpan. No ${successLabel}: ${reversalMode === 'REFUND' ? (json.refund_no || '-') : (json.void_no || '-')}`;
      if (Number(json.adjustment_doc_count || 0) > 0) {
        successMessage += ` | Adjustment terposting: ${Number(json.adjustment_doc_count || 0)}`;
      }
      showToast(successMessage, 'success', successLabel, 3600);
      const latestOrderStatus = String(json.order_status || '').toUpperCase();
      if (latestOrderStatus === 'VOID' || latestOrderStatus === 'REFUND_FULL' || latestOrderStatus === 'REFUNDED_FULL') {
        resetOrder();
      } else if (order.id) {
        await loadDraft(order.id);
      }
      await loadRecents();
      await loadCatalog().catch(() => {});
      completed = true;
    } finally {
      hideSavingOverlay();
      if (!completed) {
        setReversalSubmitState(false);
      }
    }
  }

  async function openCashierSession() {
    if (!launchOutlet || !launchTerminal || !launchOpeningCash) return;
    if (!(await confirmDailyReconGate('OPEN'))) return;
    const payload = {
      outlet_id: Number(launchOutlet.value || 0),
      terminal_id: Number(launchTerminal.value || 0),
      opening_cash: Number(launchOpeningCash.value || 0),
      notes: launchNotes ? (launchNotes.value || '') : ''
    };
    const json = await postPosTransactionJson('<?php echo site_url('pos/cashier/open'); ?>', payload);
    if (json.already_open) {
      alert('Sesi kasir ini sudah aktif. Layar akan dimuat ulang.');
    } else {
      alert('Kasir berhasil dibuka. Layar kasir akan dimuat ulang.');
    }
    window.location.reload();
  }

  async function closeCashierSession() {
    const actualCashInput = document.getElementById('cashier_close_actual_cash');
    const notesCloseInput = document.getElementById('cashier_close_notes');
    const cashBreakdown = closeCashBreakdownRows().map((entry) => ({
      denomination: Number(entry.denomination || 0),
      qty: Number(entry.qty || 0)
    }));
    const payload = {
      actual_cash: Number(actualCashInput.value || 0),
      notes: notesCloseInput.value || '',
      cash_breakdown: cashBreakdown
    };
    setCloseSubmitState(true);
    try {
      const json = await postPosTransactionJson('<?php echo site_url('pos/cashier/close'); ?>', payload);
      const summary = json.summary || {};
      let failedPrinters = [];
      if (Number(json.shift_id || 0) > 0) {
        try {
          const printResult = await directPrintTargets(json.direct_print_targets || []);
          failedPrinters = Array.isArray(printResult.failed) ? printResult.failed : [];
        } catch (e) {
          failedPrinters = [e && e.message ? e.message : 'Gagal mengirim slip tutup kasir ke printer'];
        }
      }
      if (closeModal) closeModal.hide();
      alert(buildCloseSuccessMessage(summary, json.print_prepare_message || '', failedPrinters));
      window.location.reload();
    } finally {
      setCloseSubmitState(false);
    }
  }

  if (launchOutlet) launchOutlet.addEventListener('change', filterLaunchTerminalOptions);
  renderCloseDenominationRows();
  closeDenomRows?.addEventListener('input', (event) => {
    if (!event.target || !event.target.classList.contains('cashier-close-denom-qty')) return;
    syncCloseCashTotals();
  });
  if (openButton) openButton.addEventListener('click', async () => {
    try { await openCashierSession(); } catch (e) { handleCashierActionError(e); }
  });
  if (closeButton) closeButton.addEventListener('click', async () => {
    try { await openCloseModalPreview(); } catch (e) { handleCashierActionError(e); }
  });
  if (submitCloseButton) submitCloseButton.addEventListener('click', async () => {
    try { await closeCashierSession(); } catch (e) { handleCashierActionError(e); }
  });

  if (serviceType) serviceType.addEventListener('change', syncHeaderToOrder);
  if (guestCount) guestCount.addEventListener('input', () => { syncHeaderToOrder(); recalcCart(); });
  if (tableNoInput) tableNoInput.addEventListener('input', syncHeaderToOrder);
  if (customerNameInput) customerNameInput.addEventListener('input', syncHeaderToOrder);
  if (notesInput) notesInput.addEventListener('input', syncHeaderToOrder);
  if (reversalCheckAllButton) reversalCheckAllButton.addEventListener('click', () => setReversalSelectionState(true));
  if (reversalUncheckAllButton) reversalUncheckAllButton.addEventListener('click', () => setReversalSelectionState(false));

  if (memberSearchInput) memberSearchInput.addEventListener('input', () => {
    const q = memberSearchInput.value.trim();
    clearTimeout(memberSearchTimer);
    if (q.length < 2) {
      memberResult.classList.add('d-none');
      return;
    }
    memberSearchTimer = setTimeout(async () => {
      try {
        const json = await getJson('<?php echo site_url('pos/orders/draft/member-search'); ?>?q=' + encodeURIComponent(q));
        const rows = json.rows || [];
        if (!rows.length) {
          memberResult.innerHTML = '<div class="p-3 text-muted">Member tidak ditemukan.</div>';
          memberResult.classList.remove('d-none');
          return;
        }
        memberResult.innerHTML = rows.map((row) => `
          <div class="cashier-search-item" data-row="${encodeURIComponent(JSON.stringify(row))}">
            <div>
              <div class="fw-semibold">${escapeHtml(row.member_name || '-')}</div>
              <div class="cashier-mini-note">${escapeHtml(row.mobile_phone || '-')}</div>
            </div>
            <div class="text-end">
              <div class="fw-semibold">${escapeHtml(row.member_tier || '-')}</div>
              <div class="cashier-mini-note">P ${number(row.point_balance_cache || 0, 0)} | S ${number(row.stamp_balance_cache || 0, 0)}</div>
              <div class="cashier-mini-note">${escapeHtml(row.member_status || '-')}</div>
            </div>
          </div>
        `).join('');
        memberResult.classList.remove('d-none');
        memberResult.querySelectorAll('.cashier-search-item').forEach((item) => item.addEventListener('click', () => pickMember(JSON.parse(decodeURIComponent(item.dataset.row)))));
      } catch (e) {
        memberResult.innerHTML = `<div class="p-3 text-danger">${escapeHtml(e.message)}</div>`;
        memberResult.classList.remove('d-none');
      }
    }, 250);
  });

  if (productSearchInput) productSearchInput.addEventListener('input', () => {
    clearTimeout(productSearchTimer);
    productSearchTimer = setTimeout(async () => {
      try { await loadCatalog(); } catch (e) { catalogResult.innerHTML = `<div class="cashier-empty text-danger">${escapeHtml(e.message)}</div>`; }
    }, 250);
  });

  document.addEventListener('click', (e) => {
    if (memberResult && !memberResult.contains(e.target) && e.target !== memberSearchInput) {
      memberResult.classList.add('d-none');
    }
    if (paymentVoucherSuggestions && !paymentVoucherSuggestions.contains(e.target) && e.target !== paymentVoucherSearch) {
      paymentVoucherSuggestions.style.display = 'none';
    }
  });

  document.querySelectorAll('.cashier-status-tab').forEach((btn) => btn.addEventListener('click', () => {
    recentState.status = btn.dataset.status;
    document.querySelectorAll('.cashier-status-tab').forEach((rowBtn) => rowBtn.classList.toggle('active', rowBtn === btn));
    loadRecents().catch((e) => alert(e.message));
  }));
  if (salesChannelSelect) {
    salesChannelSelect.addEventListener('change', () => {
      syncServiceTypeFromChannel(true);
    });
  }
  const recentQ = document.getElementById('cashier_recent_q');
  const recentLimit = document.getElementById('cashier_recent_limit');
  if (recentQ) recentQ.addEventListener('input', (e) => { recentState.q = e.target.value; loadRecents().catch((err) => alert(err.message)); });
  if (recentLimit) recentLimit.addEventListener('change', (e) => { recentState.limit = Number(e.target.value || 20); loadRecents().catch((err) => alert(err.message)); });

  document.getElementById('cashier_reset_order').addEventListener('click', resetOrder);
  document.getElementById('cashier_save_extra').addEventListener('click', () => {
    try {
      collectExtraSelection();
      if (extraModal) extraModal.hide();
      activeExtraDraft = null;
      activeExtraLineIndex = null;
      if (productSearchInput) {
        productSearchInput.value = '';
      }
      renderCart();
    } catch (e) {
      alert(e.message || 'Gagal menyimpan extra');
    }
  });
  if (extraLineQtyInput) {
    extraLineQtyInput.addEventListener('input', () => {
      const nextQty = Math.max(1, Number(extraLineQtyInput.value || 1));
      extraLineQtyInput.value = nextQty;
      syncExtraQtyLabels(nextQty);
    });
  }
  if (extraModalEl) {
    extraModalEl.addEventListener('hidden.bs.modal', () => {
      activeExtraDraft = null;
      activeExtraLineIndex = null;
    });
  }
  if (reversalModalEl) {
    reversalModalEl.addEventListener('hidden.bs.modal', () => {
      setReversalSubmitState(false);
    });
  }
  if (reviewModalEl) {
    reviewModalEl.addEventListener('hidden.bs.modal', () => {
      pendingReviewAction = null;
    });
  }
  if (orderReprintModalEl) {
    orderReprintModalEl.addEventListener('hidden.bs.modal', () => {
      if (orderReprintSubmitButton) {
        orderReprintSubmitButton.disabled = false;
        orderReprintSubmitButton.textContent = defaultOrderReprintLabel;
      }
    });
  }
  if (paymentModalEl) {
    paymentModalEl.addEventListener('hidden.bs.modal', () => {
      paymentContext = null;
      paymentRows = [];
      paymentActiveRowIndex = 0;
      paymentSelectedVoucher = null;
      if (paymentNotes) paymentNotes.value = '';
      if (paymentVoucherSuggestions) {
        paymentVoucherSuggestions.innerHTML = '';
        paymentVoucherSuggestions.style.display = 'none';
      }
    });
  }
  if (saveDraftButton) saveDraftButton.addEventListener('click', async () => {
    try { openReviewModal('DRAFT'); } catch (e) { alert(e.message); }
  });
  if (deleteDraftButton) deleteDraftButton.addEventListener('click', async () => {
    try { await deleteDraft(); } catch (e) { alert(e.message); }
  });
  if (confirmOrderButton) confirmOrderButton.addEventListener('click', async () => {
    try { openReviewModal('CONFIRM'); } catch (e) { alert(e.message); }
  });
  if (orderReprintButton) orderReprintButton.addEventListener('click', () => {
    try { openOrderReprintModal(); } catch (e) { alert(e.message); }
  });
  if (orderReprintPrinterSelect) orderReprintPrinterSelect.addEventListener('change', refreshOrderReprintModalState);
  if (orderReprintSubmitButton) orderReprintSubmitButton.addEventListener('click', async () => {
    try { await submitOrderReprint(); } catch (e) { alert(e.message); }
  });
  if (paymentButton) paymentButton.addEventListener('click', async () => {
    try { await openPaymentModal(); } catch (e) { alert(e.message); }
  });
  if (paymentVoucherSearch) paymentVoucherSearch.addEventListener('input', () => {
    clearTimeout(paymentVoucherSearchTimer);
    paymentVoucherSearchTimer = setTimeout(() => {
      searchPaymentVouchers();
    }, 220);
  });
  if (paymentVoucherSearch) paymentVoucherSearch.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      searchPaymentVouchers({ preferExact: true, showIfEmpty: true });
    }
  });
  if (paymentVoucherSearch) paymentVoucherSearch.addEventListener('focus', () => {
    if ((paymentVoucherSearch.value || '').trim() !== '') {
      searchPaymentVouchers();
    }
  });
  if (paymentVoucherCheckButton) paymentVoucherCheckButton.addEventListener('click', () => {
    searchPaymentVouchers({ preferExact: true, showIfEmpty: true });
  });
  if (paymentClearVoucherButton) paymentClearVoucherButton.addEventListener('click', () => {
    resetPaymentVoucherSelection();
    syncSinglePaymentAmountToDue();
    renderPaymentRows();
    renderPaymentSummary();
  });
  paymentQuickAmountButtons.forEach((button) => button.addEventListener('click', () => {
    try { applyQuickPaymentAmount(button); } catch (e) { alert(e.message); }
  }));
  if (paymentAddRowButton) paymentAddRowButton.addEventListener('click', () => {
    paymentRows.push(defaultPaymentRow(''));
    paymentActiveRowIndex = paymentRows.length - 1;
    renderPaymentRows();
    renderPaymentSummary();
  });
  if (paymentSubmitButton) paymentSubmitButton.addEventListener('click', async () => {
    try { await submitPayment(); } catch (e) { alert(e.message); }
  });
  if (reviewSubmitButton) reviewSubmitButton.addEventListener('click', async () => {
    try { await submitReviewedOrder(); } catch (e) { alert(e.message); }
  });
  reversalButton.addEventListener('click', async () => {
    try { await openReversalPreview(); } catch (e) { alert(e.message); }
  });
  document.getElementById('cashier_reversal_return')?.addEventListener('change', refreshReversalPolicyCards);
  document.getElementById('cashier_reversal_adjust')?.addEventListener('change', refreshReversalPolicyCards);
  document.getElementById('cashier_reversal_adjustment')?.addEventListener('change', refreshReversalReasonHint);
  reversalReasonCode?.addEventListener('change', refreshReasonOtherVisibility);
  saveVoidButton?.addEventListener('click', async () => {
    try { await submitReversal(); } catch (e) { alert(e.message); }
  });

  if (activeSession && workspace) {
    workspace.classList.remove('d-none');
  }
  applyLaunchDefaults();
  fillReversalReasonOptions();
  refreshReversalPolicyCards();
  renderDivisionFilters();
  renderMemberSelection();
  renderCart();
  renderOrderReprintPrinterOptions();
  refreshOrderReprintModalState();
  syncServiceTypeFromChannel(true);
  syncHeaderToOrder();
  loadRecents().catch((e) => alert(e.message));
  if (activeSession) {
    loadCatalog().catch((e) => { catalogResult.innerHTML = `<div class="cashier-empty text-danger">${escapeHtml(e.message)}</div>`; });
  }
});
</script>
<?php if ($incomingChannels !== []): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const incomingModalEl = document.getElementById('cashierIncomingModal');
  const incomingOpenButton = document.getElementById('cashier_incoming_open_btn');
  const incomingSyncButton = document.getElementById('cashier_incoming_sync_btn');
  const incomingRefreshButton = document.getElementById('cashier_incoming_refresh_btn');
  const incomingListEl = document.getElementById('cashier_incoming_list');
  const incomingDetailEl = document.getElementById('cashier_incoming_detail');
  const incomingStatusEl = document.getElementById('cashier_incoming_status');
  const incomingUpdatedEl = document.getElementById('cashier_incoming_updated');
  const totalBadgeEl = document.getElementById('cashier_incoming_total_badge');
  const incomingOutletId = <?php echo (int)$incomingCashierOutletId; ?>;
  const incomingServerDate = <?php echo json_encode($incomingServerDate, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  const incomingChannels = <?php echo json_encode($incomingChannels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); ?> || {};
  const incomingChannelKeys = Object.keys(incomingChannels);
  const incomingModal = incomingModalEl && window.bootstrap ? new bootstrap.Modal(incomingModalEl) : null;
  const state = {
    active: <?php echo json_encode($firstIncomingChannel, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    rows: Object.fromEntries(incomingChannelKeys.map((channel) => [channel, []])),
    busy: false
  };

  function escapeIncoming(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
  }

  function moneyIncoming(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));
  }

  function displayIncomingDate(value) {
    if (!value) return 'Waktu belum tersedia';
    const parsed = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(parsed.getTime())
      ? String(value)
      : parsed.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
  }

  function incomingRowNumber(row, channel) {
    return row.reservation_no || row.reservation_code || row.order_no || row.reference_no
      || ((channel === 'reservation' ? 'RES-' : 'ORDER-') + (row.id || '-'));
  }

  function incomingRowCustomer(row) {
    return row.customer_name_display || row.customer_name || row.member_name || row.guest_name || 'Walk in';
  }

  function incomingRowAmount(row, channel) {
    return row.grand_total ?? row.total_amount ?? row.total ?? (channel === 'reservation' ? row.deposit_amount : 0) ?? 0;
  }

  function incomingRowStatus(row) {
    return row.flow_label || row.status_label || row.reservation_status || row.status || 'Menunggu ditinjau';
  }

  function updateIncomingCounts() {
    let total = 0;
    incomingChannelKeys.forEach((channel) => {
      const count = Array.isArray(state.rows[channel]) ? state.rows[channel].length : 0;
      total += count;
      const countElement = document.getElementById('cashier_incoming_count_' + channel);
      if (countElement) countElement.textContent = String(count);
    });
    if (totalBadgeEl) totalBadgeEl.textContent = String(total);
  }

  function incomingQuery(channel) {
    if (incomingOutletId <= 0) {
      throw new Error('Buka sesi kasir dengan outlet aktif sebelum memuat order.');
    }
    const query = new URLSearchParams();
    query.set('q', '');
    query.set('outlet_id', String(incomingOutletId));
    query.set('page', '1');
    query.set('limit', '20');
    if (channel === 'reservation') {
      query.set('status_tab', 'ACTIVE');
      query.set('date_from', '');
      query.set('date_to', '');
    } else {
      query.set('payment_tab', 'ALL');
      query.set('status_tab', 'NEEDS_VERIFY');
      query.set('date_from', incomingServerDate);
      query.set('date_to', incomingServerDate);
    }
    return query.toString();
  }

  async function getIncoming(url) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const json = await response.json().catch(() => ({}));
    if (!response.ok || !json || json.ok === false) {
      throw new Error(json.message || ('HTTP ' + response.status));
    }
    return json;
  }

  function renderIncomingRows() {
    if (!incomingListEl) return;
    const rows = state.rows[state.active] || [];
    if (!rows.length) {
      incomingListEl.innerHTML = '<div class="cashier-incoming-empty"><div><i class="ri-inbox-line fs-2 d-block mb-2" aria-hidden="true"></i>Belum ada order yang menunggu perhatian.</div></div>';
      return;
    }
    incomingListEl.innerHTML = rows.map((row) => {
      const id = Number(row.id || row.reservation_id || 0);
      const channel = state.active;
      const time = row.ordered_at || row.reservation_at || row.reserved_at || row.created_at || row.updated_at;
      return `<div class="cashier-incoming-row">
        <div>
          <div class="cashier-incoming-row-title">${escapeIncoming(incomingRowNumber(row, channel))}</div>
          <div class="cashier-incoming-row-meta">${escapeIncoming(incomingRowCustomer(row))} - ${escapeIncoming(displayIncomingDate(time))}</div>
          <div class="cashier-incoming-row-meta">${escapeIncoming(row.service_type || row.table_no || row.channel_label || 'Order masuk')} - ${escapeIncoming(incomingRowStatus(row))}</div>
        </div>
        <div class="text-md-end">
          <div class="cashier-incoming-row-amount">${escapeIncoming(moneyIncoming(incomingRowAmount(row, channel)))}</div>
          <button type="button" class="btn btn-sm btn-outline-primary mt-2 cashier-incoming-detail-btn" data-id="${id}" data-channel="${escapeIncoming(channel)}">Detail</button>
        </div>
      </div>`;
    }).join('');
    incomingListEl.querySelectorAll('.cashier-incoming-detail-btn').forEach((button) => {
      button.addEventListener('click', () => openIncomingDetail(button.dataset.channel, Number(button.dataset.id || 0)));
    });
  }

  async function loadIncomingChannel(channel) {
    const config = incomingChannels[channel];
    if (!config || incomingOutletId <= 0) return 0;
    const json = await getIncoming(config.endpoint + '?' + incomingQuery(channel));
    state.rows[channel] = Array.isArray(json.rows) ? json.rows : [];
    updateIncomingCounts();
    if (state.active === channel) renderIncomingRows();
    return state.rows[channel].length;
  }

  async function refreshIncoming() {
    if (state.busy) return;
    if (incomingOutletId <= 0) {
      if (incomingStatusEl) incomingStatusEl.textContent = 'Buka sesi kasir untuk memuat order';
      return;
    }
    state.busy = true;
    if (incomingStatusEl) incomingStatusEl.textContent = 'Memuat data terbaru...';
    try {
      const results = await Promise.allSettled(incomingChannelKeys.map((channel) => loadIncomingChannel(channel)));
      const failed = results.filter((result) => result.status === 'rejected');
      if (incomingStatusEl) incomingStatusEl.textContent = failed.length ? 'Sebagian sumber belum dapat dibaca' : 'Terhubung ke Finance';
      if (incomingUpdatedEl) incomingUpdatedEl.textContent = 'Diperbarui ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
      if (failed.length && !(state.rows[state.active] || []).length && incomingListEl) {
        const message = failed[0].reason && failed[0].reason.message ? failed[0].reason.message : 'Gagal memuat order masuk.';
        incomingListEl.innerHTML = `<div class="cashier-incoming-empty text-danger"><div>${escapeIncoming(message)}</div></div>`;
      }
    } finally {
      state.busy = false;
    }
  }

  async function openIncomingDetail(channel, id) {
    const config = incomingChannels[channel];
    if (!config || incomingOutletId <= 0 || id <= 0 || !incomingDetailEl) return;
    incomingDetailEl.classList.remove('d-none');
    incomingDetailEl.innerHTML = '<div class="text-muted"><i class="ri-loader-4-line ri-spin me-1" aria-hidden="true"></i>Memuat detail...</div>';
    try {
      const detailQuery = new URLSearchParams({ outlet_id: String(incomingOutletId) });
      const json = await getIncoming(config.detail_endpoint + id + '?' + detailQuery.toString());
      const payload = json.reservation || json;
      const header = payload.header || payload;
      const lines = Array.isArray(json.lines) ? json.lines : (Array.isArray(payload.lines) ? payload.lines : []);
      const items = [
        ['Nomor', incomingRowNumber(header, channel)],
        ['Customer', incomingRowCustomer(header)],
        ['Waktu', displayIncomingDate(header.ordered_at || header.reservation_at || header.created_at)],
        ['Status', incomingRowStatus(header)],
        ['Pembayaran', header.payment_mode || header.payment_status || '-'],
        ['Total', moneyIncoming(incomingRowAmount(header, channel))]
      ];
      incomingDetailEl.innerHTML = `<div class="d-flex justify-content-between align-items-start gap-2 mb-2"><strong>Ringkasan Detail</strong><button type="button" class="btn btn-sm btn-link p-0" id="cashier_incoming_detail_close">Tutup detail</button></div>
        <div class="cashier-incoming-detail-grid">${items.map((item) => `<div class="cashier-incoming-detail-item"><div class="cashier-incoming-detail-label">${escapeIncoming(item[0])}</div><div class="cashier-incoming-detail-value">${escapeIncoming(item[1])}</div></div>`).join('')}</div>
        <div class="cashier-incoming-lines">${lines.length ? lines.map((line) => `<div class="cashier-incoming-line"><span>${escapeIncoming(line.product_name || line.item_name || line.name || 'Item')}</span><strong>${escapeIncoming(String(line.qty || line.quantity || 1))}</strong></div>`).join('') : '<div class="small text-muted">Rincian item belum tersedia.</div>'}</div>
        <div class="mt-3"><a class="btn btn-sm btn-primary" href="${escapeIncoming(config.module_url)}" target="_blank" rel="noopener noreferrer"><i class="ri-external-link-line me-1" aria-hidden="true"></i>Buka Modul</a></div>`;
      document.getElementById('cashier_incoming_detail_close')?.addEventListener('click', () => incomingDetailEl.classList.add('d-none'));
    } catch (error) {
      incomingDetailEl.innerHTML = `<div class="text-danger">${escapeIncoming(error.message || 'Detail tidak dapat dimuat.')}</div>`;
    }
  }

  function activateIncomingTab(channel) {
    if (!Object.prototype.hasOwnProperty.call(incomingChannels, channel)) return;
    state.active = channel;
    document.querySelectorAll('[data-incoming-channel]').forEach((button) => {
      const active = button.dataset.incomingChannel === channel;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    if (incomingDetailEl) incomingDetailEl.classList.add('d-none');
    renderIncomingRows();
  }

  async function showIncomingModal() {
    if (!incomingModal || incomingOutletId <= 0) return;
    incomingModal.show();
    await refreshIncoming();
  }

  document.querySelectorAll('[data-incoming-channel]').forEach((button) => {
    button.addEventListener('click', () => activateIncomingTab(button.dataset.incomingChannel));
  });
  incomingOpenButton?.addEventListener('click', () => { showIncomingModal().catch(() => {}); });
  incomingSyncButton?.addEventListener('click', () => { showIncomingModal().catch(() => {}); });
  incomingRefreshButton?.addEventListener('click', () => { refreshIncoming().catch(() => {}); });
  updateIncomingCounts();
  renderIncomingRows();
});
</script>
<?php endif; ?>
<?php $this->load->view('pos/_direct_print_prompt'); ?>
