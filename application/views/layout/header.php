<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
<?php
$businessProfile = is_array($business_profile ?? null) ? $business_profile : [];
$businessName = trim((string)($businessProfile['display_name'] ?? ''));
$businessName = $businessName !== '' ? $businessName : 'Finance';
$pageTitle = trim((string)($title ?? ''));
$pageTitle = $pageTitle !== '' ? $pageTitle : $businessName;
?>
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($businessName, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="icon" href="<?= (is_file(FCPATH . 'assets/img/favicon.ico') ? base_url('assets/img/favicon.ico') : base_url('assets/img/business-placeholder.svg')) ?>">

<!-- Google Fonts: Plus Jakarta Sans (400-800) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,500&display=swap">

<!-- Remix Icons (full set) -->
<link rel="stylesheet" href="<?= base_url('assets/vendor/fonts/iconify-icons.css?v=20260530a') ?>">

<!-- Materio Core CSS (Bootstrap included) -->
<link rel="stylesheet" href="<?= base_url('assets/vendor/css/core.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') ?>">

<!-- ApexCharts -->
<link rel="stylesheet" href="<?= base_url('assets/vendor/libs/apex-charts/apex-charts.css') ?>">
<!-- Finance Theme (merah + cream) -->
<link rel="stylesheet" href="<?= base_url('assets/css/theme-custom.css?v=20260925-navbar-clearance') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/app.css?v=20260520a') ?>">

<!-- Materio Helpers (sebelum body) -->
<script src="<?= base_url('assets/vendor/js/helpers.js') ?>"></script>
<script src="<?= base_url('assets/js/config.js') ?>"></script>
</head>
<body>
