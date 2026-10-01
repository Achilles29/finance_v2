<?php
$this->load->view('layout/header', ['title' => $title ?? 'Kasir POS']);
$username = htmlspecialchars($current_user['username'] ?? 'U', ENT_QUOTES, 'UTF-8');
$initials = mb_strtoupper(mb_substr($current_user['username'] ?? 'U', 0, 1));
$activeSession = is_array($active_cashier_session ?? null) ? $active_cashier_session : null;
$_isSuper = !empty($current_user['is_superadmin']);
$canCashier = $_isSuper || !empty($user_perms['pos.cashier.index']['can_view']);
$canPaid = $_isSuper || !empty($user_perms['pos.order.paid.index']['can_view']) || !empty($user_perms['pos.order.draft.index']['can_view']);
$canPrinter = $_isSuper || !empty($user_perms['pos.printer.index']['can_view']);
$canDraft = $_isSuper || !empty($user_perms['pos.order.draft.index']['can_view']);
$canGlobalSelfOrderNotify = $_isSuper || !empty($user_perms['pos.self_order.index']['can_view']);
$canGlobalOnlineFoodNotify = $_isSuper || !empty($user_perms['pos.online_food.index']['can_view']);
$globalNotifierConfig = [
    'sound_url' => base_url('assets/sounds/notifikasi.mp3'),
    'notifiers' => [
        [
            'enabled' => $canGlobalSelfOrderNotify,
            'channel' => 'self_order',
            'poll_ms' => 12000,
            'current_path' => trim((string)uri_string(), '/'),
            'skip_paths' => ['pos/self-order/orders'],
            'endpoint' => site_url('pos/self-order/orders/data'),
            'title' => 'Self Order',
        ],
        [
            'enabled' => $canGlobalOnlineFoodNotify,
            'channel' => 'online_food',
            'poll_ms' => 12000,
            'current_path' => trim((string)uri_string(), '/'),
            'skip_paths' => ['pos/online-food/orders'],
            'endpoint' => site_url('pos/online-food/orders/data'),
            'title' => 'Online Food',
        ],
    ],
];
?>
<style>
  .cashier-layout {
    min-height:100vh;
    background:
      radial-gradient(circle at top left, rgba(210, 84, 60, .12), transparent 24%),
      linear-gradient(180deg,#f7efe8 0%, #f8f3ee 34%, #f4eee8 100%);
  }
  .cashier-app-shell {
    min-height:100vh;
    display:grid;
    grid-template-columns:90px minmax(0, 1fr);
  }
  .cashier-icon-rail {
    position:sticky;
    top:0;
    height:100vh;
    z-index:1045;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:.85rem;
    padding:1rem .75rem;
    background:linear-gradient(180deg, rgba(255,251,248,.95), rgba(247,240,233,.92));
    border-right:1px solid rgba(222, 209, 198, .75);
    backdrop-filter: blur(12px);
  }
  .cashier-icon-brand {
    width:54px; height:54px; border-radius:16px;
    display:inline-flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg,#8f3d33 0%, #cf624a 100%);
    color:#fff; font-size:.86rem; font-weight:900; letter-spacing:.08em;
    box-shadow:0 14px 28px rgba(143,61,51,.25);
  }
  .cashier-icon-nav {
    display:flex;
    flex-direction:column;
    gap:.65rem;
    width:100%;
    align-items:center;
  }
  .cashier-icon-link {
    width:58px; min-height:58px; border-radius:18px;
    display:inline-flex; flex-direction:column; align-items:center; justify-content:center; gap:.18rem;
    background:rgba(255,255,255,.88);
    border:1px solid rgba(224,209,198,.9);
    color:#6d5d58; text-decoration:none; font-size:1.02rem;
    box-shadow:0 10px 24px rgba(58,38,30,.06);
  }
  .cashier-icon-link-label {
    font-size:.56rem;
    font-weight:800;
    letter-spacing:.06em;
    text-transform:uppercase;
    line-height:1;
  }
  .cashier-icon-link.active {
    background:linear-gradient(135deg,#8f3d33 0%, #cf624a 100%);
    border-color:transparent;
    color:#fff;
  }
  .cashier-icon-link.logout {
    margin-top:auto;
    color:#b23a2d;
  }
  .cashier-topbar {
    position:sticky; top:0; z-index:1040;
    backdrop-filter: blur(12px);
    background:rgba(255, 251, 248, .92);
    border-bottom:1px solid rgba(222, 209, 198, .75);
  }
  .cashier-topbar-inner {
    display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:1rem;
    min-height:72px;
  }
  .cashier-brand { display:flex; align-items:center; gap:.85rem; }
  .cashier-topbar-chip {
    display:inline-flex; align-items:center; gap:.45rem; padding:.35rem .75rem; border-radius:999px;
    background:#fff; border:1px solid rgba(224,209,198,.9); color:#6d5d58; font-size:.78rem; font-weight:700;
  }
  .cashier-user-pill {
    display:inline-flex; align-items:center; gap:.65rem; padding:.3rem .5rem .3rem .35rem;
    border-radius:999px; background:#fff; border:1px solid rgba(224,209,198,.9);
  }
  .cashier-user-avatar {
    width:34px; height:34px; border-radius:50%;
    display:inline-flex; align-items:center; justify-content:center;
    background:#8f3d33; color:#fff; font-weight:800;
  }
  .cashier-stage-note { font-size:.74rem; color:#8b7c75; }
  @media (max-width: 991.98px) {
    .cashier-app-shell {
      grid-template-columns:1fr;
    }
    .cashier-icon-rail {
      height:auto;
      position:sticky;
      top:0;
      flex-direction:row;
      justify-content:space-between;
      padding:.75rem 1rem;
      border-right:0;
      border-bottom:1px solid rgba(222, 209, 198, .75);
    }
    .cashier-icon-nav {
      flex-direction:row;
      width:auto;
    }
    .cashier-icon-link.logout {
      margin-top:0;
    }
    .cashier-topbar-inner {
      grid-template-columns:1fr;
      align-items:start;
      padding-top:.75rem;
      padding-bottom:.75rem;
    }
  }
</style>

<div class="cashier-layout">
  <div class="cashier-app-shell">
    <aside class="cashier-icon-rail">
      <span class="cashier-icon-brand">NCE</span>
      <div class="cashier-icon-nav">
        <?php if ($canCashier): ?>
          <a href="<?= site_url('pos/cashier') ?>" class="cashier-icon-link<?= (($active_menu ?? '') === 'pos.cashier.index') ? ' active' : '' ?>" title="Kasir" target="_blank" rel="noopener noreferrer">
            <i class="ri-layout-grid-line"></i>
            <span class="cashier-icon-link-label">Kasir</span>
          </a>
        <?php endif; ?>
        <?php if ($canPaid): ?>
          <a href="<?= site_url('pos/orders/paid') ?>" class="cashier-icon-link<?= (($active_menu ?? '') === 'pos.order.paid.index') ? ' active' : '' ?>" title="Pesanan Terbayar" target="_blank" rel="noopener noreferrer">
            <i class="ri-wallet-3-line"></i>
            <span class="cashier-icon-link-label">Terbayar</span>
          </a>
        <?php endif; ?>
        <?php if ($canPrinter): ?>
          <a href="<?= site_url('pos/printers/templates') ?>" class="cashier-icon-link<?= (($active_menu ?? '') === 'pos.printer.index') ? ' active' : '' ?>" title="Printer" target="_blank" rel="noopener noreferrer">
            <i class="ri-printer-line"></i>
            <span class="cashier-icon-link-label">Print</span>
          </a>
        <?php endif; ?>
        <?php if ($canDraft): ?>
          <a href="<?= site_url('pos/orders/draft') ?>" class="cashier-icon-link<?= (($active_menu ?? '') === 'pos.order.draft.index') ? ' active' : '' ?>" title="Workbench" target="_blank" rel="noopener noreferrer">
            <i class="ri-file-list-3-line"></i>
            <span class="cashier-icon-link-label">Draft</span>
          </a>
        <?php endif; ?>
      </div>
      <a href="<?= site_url('logout') ?>" class="cashier-icon-link logout" title="Logout" target="_blank" rel="noopener noreferrer">
        <i class="ri-logout-box-r-line"></i>
        <span class="cashier-icon-link-label">Keluar</span>
      </a>
    </aside>

    <div>
      <div class="cashier-topbar">
        <div class="container-fluid px-3 px-lg-4">
          <div class="cashier-topbar-inner">
            <div class="cashier-brand">
              <div>
                <div class="fw-bold text-dark">Kasir POS</div>
              </div>
            </div>
            <div class="cashier-stage-note"></div>
            <div class="d-flex flex-wrap justify-content-end align-items-center gap-2">
              <?php if ($activeSession): ?>
                <span class="cashier-topbar-chip">
                  <i class="ri-store-2-line"></i>
                  <?= htmlspecialchars((string)($activeSession['outlet_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="cashier-topbar-chip">
                  <i class="ri-computer-line"></i>
                  <?= htmlspecialchars((string)($activeSession['terminal_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="cashier-topbar-chip">
                  <i class="ri-time-line"></i>
                  <?= htmlspecialchars((string)($activeSession['shift_no'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="cashier-topbar-chip">
                  <i class="ri-wallet-3-line"></i>
                  Modal Awal <?= 'Rp ' . number_format((float)($activeSession['opening_cash'] ?? 0), 0, ',', '.') ?>
                </span>
              <?php endif; ?>
              <div class="cashier-user-pill">
                <span class="cashier-user-avatar"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
                <div class="d-none d-md-block">
                  <div class="small fw-semibold text-dark"><?= $username ?></div>
                  <div class="small text-muted">Kasir Aktif</div>
                </div>
              </div>
              <a href="<?= site_url('dashboard') ?>" class="btn btn-sm btn-outline-secondary">Back Office</a>
            </div>
          </div>
        </div>
      </div>

      <div class="container-fluid px-3 px-lg-4 py-3">
        <?php if ($this->session->flashdata('success')): ?>
          <div class="alert alert-success border-0 shadow-sm"><?= htmlspecialchars($this->session->flashdata('success'), ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($this->session->flashdata('error')): ?>
          <div class="alert alert-danger border-0 shadow-sm"><?= $this->session->flashdata('error') ?></div>
        <?php endif; ?>

        <?php $this->load->view($content_view, $content_data ?? []); ?>
      </div>
    </div>
  </div>
</div>

<?php $this->load->view('layout/footer', ['global_notifier_config' => $globalNotifierConfig]); ?>
