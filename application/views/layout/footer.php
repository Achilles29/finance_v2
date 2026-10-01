<!-- ApexCharts -->
<script src="<?= base_url('assets/vendor/libs/apex-charts/apexcharts.js') ?>"></script>
<!-- jQuery (Materio bundled) -->
<script src="<?= base_url('assets/vendor/libs/jquery/jquery.min.js?v=20260510a') ?>"></script>
<!-- Bootstrap JS (Materio) -->
<script src="<?= base_url('assets/vendor/js/bootstrap.js') ?>"></script>
<!-- Materio Menu & Helpers -->
<script src="<?= base_url('assets/vendor/js/menu.js') ?>"></script>
<!-- Perfect Scrollbar -->
<script src="<?= base_url('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') ?>"></script>
<!-- Materio Main -->
<script src="<?= base_url('assets/js/main.js') ?>"></script>
<!-- Base URL untuk JS -->
<script>const BASE_URL = '<?= base_url() ?>';</script>
<?php
$globalNotifierConfig = is_array($global_notifier_config ?? null) ? $global_notifier_config : [];
if (!$globalNotifierConfig) {
    $CI = &get_instance();
    $sessionUser = [];
    $sessionPerms = [];
    if (isset($CI->session)) {
        $sessionUser = (array)($CI->session->userdata('auth_user') ?? []);
        $sessionPerms = (array)($CI->session->userdata('user_perms') ?? []);
    }
    $viewUser = is_array($current_user ?? null) ? $current_user : $sessionUser;
    $viewPerms = is_array($user_perms ?? null) ? $user_perms : $sessionPerms;
    $canGlobalSelfOrderNotify = !empty($viewUser['is_superadmin']) || !empty($viewPerms['pos.self_order.index']['can_view']);
    $canGlobalOnlineFoodNotify = !empty($viewUser['is_superadmin']) || !empty($viewPerms['pos.online_food.index']['can_view']);
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
}
?>
<script>
window.FINANCE_GLOBAL_NOTIFIER_CONFIG = <?= json_encode($globalNotifierConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.FINANCE_SIDEBAR_FAVORITE_CSRF = <?= json_encode((string)($sidebar_favorite_csrf_token ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<!-- Finance App JS -->
<script src="<?= base_url('assets/js/app.js?v=20260925inventory-matrix-sheet-diagnostics-2') ?>"></script>
</body>
</html>
