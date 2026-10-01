<!doctype html>

<html lang="en" class="layout-menu-fixed layout-compact" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="robots" content="noindex, nofollow" />

    <title><?=  $title . ' - MPP' ?? 'MPP' ?></title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" href="<?= is_file(FCPATH . 'assets/img/favicon/logo.ico') ? base_url('assets/img/favicon/logo.ico') : base_url('assets/img/business-placeholder.svg') ?>" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;display=swap"
        rel="stylesheet" />

    <link rel="stylesheet" href="<?= base_url('assets/fonts/iconify-icons.css'); ?>" />


    <!-- Core CSS -->
    <!-- build:css assets/vendor/css/theme.css -->

    <!-- <link rel="stylesheet" href="../assets/vendor/libs/node-waves/node-waves.css" /> -->
    <link rel="stylesheet" href="<?= base_url('assets/libs/node-waves/node-waves.css'); ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/core.css'); ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/theme-custom.css?v=20260601a'); ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/custom.css'); ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css'); ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/portal-shell.css'); ?>">

    <link rel="stylesheet" href="<?= base_url('assets/css/demo.css'); ?>">
    <link rel="stylesheet" href="<?= base_url('assets/libs/perfect-scrollbar/perfect-scrollbar.css'); ?>">
    <link rel="stylesheet" href="<?= base_url('assets/libs/apex-charts/apex-charts.css'); ?>">
    <script src="<?= base_url('assets/js/helpers.js'); ?>"></script>
    <script src="<?= base_url('assets/js/config.js'); ?>"></script>


    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap Bundle (with Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body class="portal-admin-app">
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
