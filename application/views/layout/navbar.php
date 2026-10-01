<?php
/**
 * layout/navbar.php
 */
$username = htmlspecialchars($current_user['username'] ?? 'U');
$email    = htmlspecialchars($current_user['email'] ?? '');
$is_super = !empty($current_user['is_superadmin']);
$initials = mb_strtoupper(mb_substr($current_user['username'] ?? 'U', 0, 1));

$avatar_colors = ['#c0392b','#8e44ad','#2980b9','#16a085','#d35400','#7f8c8d','#c0392b'];
$avatar_bg     = $avatar_colors[ord($initials) % count($avatar_colors)];
?>
<nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme fin-navbar-hardfix" id="layout-navbar">

  <!-- Mobile: hamburger toggle -->
  <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
    <a class="nav-item nav-link px-0" href="javascript:void(0)">
      <i class="ri ri-menu-fill" style="font-size:1.35rem;color:#433c40;"></i>
    </a>
  </div>

  <!-- Spacer -->
  <div class="flex-grow-1"></div>

  <!-- Right side -->
  <ul class="navbar-nav flex-row align-items-center gap-1 pe-1">

    <!-- Notifikasi -->
    <li class="nav-item">
      <a class="nav-link p-2 position-relative" href="javascript:void(0)" title="Notifikasi">
        <i class="ri ri-notification-3-line" style="font-size:1.2rem;color:#897a75;"></i>
      </a>
    </li>

    <!-- User dropdown -->
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle hide-arrow d-flex align-items-center gap-2 p-1 pe-2"
         href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="navbar-user-avatar" style="background:<?= $avatar_bg ?>;"><?= $initials ?></span>
        <span class="d-none d-lg-flex flex-column" style="line-height:1.2;min-width:0;">
          <span style="font-size:.85rem;font-weight:600;color:#34282c;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= $username ?></span>
          <span style="font-size:.72rem;color:#897a75;"><?= $is_super ? 'Super Admin' : 'User' ?></span>
        </span>
        <i class="ri ri-arrow-down-s-line d-none d-lg-inline" style="color:#897a75;font-size:.9rem;flex-shrink:0;"></i>
      </a>

      <ul class="dropdown-menu dropdown-menu-end shadow-sm border" style="min-width:200px;margin-top:6px;border-color:rgba(228,217,208,.8)!important;">
        <!-- Header -->
        <li class="px-3 py-2 border-bottom" style="border-color:rgba(228,217,208,.6)!important;">
          <div class="d-flex align-items-center gap-2">
            <span style="width:36px;height:36px;border-radius:50%;background:<?= $avatar_bg ?>;
                         display:inline-flex;align-items:center;justify-content:center;
                         font-size:.95rem;font-weight:700;color:#fff;flex-shrink:0;"><?= $initials ?></span>
            <div style="min-width:0;">
              <div style="font-size:.85rem;font-weight:600;color:#34282c;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= $username ?></div>
              <div style="font-size:.72rem;color:#897a75;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= $email ?: 'Tidak ada email' ?></div>
              <?php if ($is_super): ?>
              <span class="badge mt-1" style="background:#fce8e6;color:#c0392b;font-size:.65rem;">Superadmin</span>
              <?php endif; ?>
            </div>
          </div>
        </li>
        <li>
          <a class="dropdown-item py-2" href="<?= base_url('my/profile') ?>">
            <i class="ri ri-user-3-line me-2 text-muted" style="font-size:.9rem;"></i>
            <span style="font-size:.875rem;">Kontrak Saya</span>
          </a>
        </li>
        <li>
          <a class="dropdown-item py-2" href="<?= base_url('settings') ?>">
            <i class="ri ri-settings-4-line me-2 text-muted" style="font-size:.9rem;"></i>
            <span style="font-size:.875rem;">Pengaturan</span>
          </a>
        </li>
        <li><hr class="dropdown-divider my-1" style="border-color:rgba(228,217,208,.6);"></li>
        <li>
          <a class="dropdown-item py-2 text-danger" href="<?= base_url('logout') ?>">
            <i class="ri ri-logout-box-r-line me-2" style="font-size:.9rem;"></i>
            <span style="font-size:.875rem;">Logout</span>
          </a>
        </li>
      </ul>
    </li>

  </ul>
</nav>
