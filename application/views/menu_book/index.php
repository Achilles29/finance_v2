<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Book — NAMUA Coffee &amp; Eatery</title>
    <link rel="shortcut icon"  href="<?= base_url('assets/img/favicon.ico') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= base_url('assets/img/favicon-32x32.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= base_url('assets/img/favicon-16x16.png') ?>">

    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --cream:       #F8F1E8;
        --maroon:      #7D1F1F;
        --maroon-dk:   #3D1010;
        --gold:        #C9A86A;
        --gold-lt:     #E8D5B0;
        --brown:       #3A2E22;
        --brown-mid:   #9E856A;
        --brown-lt:    #6A4E3A;
    }

    html { scroll-behavior: smooth; }

    body {
        font-family: Georgia, 'Times New Roman', serif;
        background: linear-gradient(160deg, #F0E6D3 0%, #F8F1E8 50%, #EDE0CC 100%);
        color: var(--brown);
        min-height: 100vh;
    }

    /* ── HERO ───────────────────────────────────────────── */
    .hero {
        background:
            radial-gradient(ellipse 80% 50% at 50% 110%, rgba(201,168,106,.14) 0%, transparent 60%),
            radial-gradient(ellipse 50% 80% at 10% 20%,  rgba(201,168,106,.06) 0%, transparent 55%),
            linear-gradient(170deg, #150404 0%, #3D1010 28%, #7D1F1F 65%, #9E2D2D 88%, #B84040 100%);
        color: #fff;
        text-align: center;
        padding: 64px 24px 56px;
        position: relative;
        overflow: hidden;
    }
    .hero::after {
        content: '';
        position: absolute;
        top: -30%;
        left: 50%;
        transform: translateX(-50%);
        width: 55%;
        height: 140%;
        background: radial-gradient(ellipse at center top, rgba(201,168,106,.09) 0%, transparent 65%);
        pointer-events: none;
    }

    .hero-inner { position: relative; z-index: 1; max-width: 620px; margin: 0 auto; }

    .hero-logo {
        height: 84px;
        margin-bottom: 20px;
        filter: brightness(1.15) drop-shadow(0 3px 16px rgba(201,168,106,.45));
    }

    .hero-eyebrow {
        font-family: Arial, sans-serif;
        font-size: 10px;
        letter-spacing: 5.5px;
        text-transform: uppercase;
        color: var(--gold);
        margin-bottom: 10px;
    }

    .hero-ornament {
        color: var(--gold);
        opacity: .38;
        font-size: 13px;
        letter-spacing: 5px;
        margin-bottom: 10px;
    }

    .hero-title {
        font-size: clamp(34px, 6vw, 58px);
        font-weight: 700;
        letter-spacing: 8px;
        text-transform: uppercase;
        color: #fff;
        text-shadow: 0 2px 24px rgba(0,0,0,.35);
        margin-bottom: 12px;
    }

    .hero-desc {
        font-size: 12.5px;
        color: rgba(255,255,255,.55);
        font-style: italic;
        letter-spacing: .4px;
        margin-bottom: 40px;
    }

    /* ── STATS ── */
    .stats-row {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
        background: rgba(255,255,255,.07);
        border: 1px solid rgba(201,168,106,.22);
        border-radius: 60px;
        padding: 14px 36px;
        margin-bottom: 30px;
        backdrop-filter: blur(10px);
    }
    .stat { text-align: center; }
    .stat-val {
        font-family: Arial, sans-serif;
        font-size: 22px;
        font-weight: 700;
        color: #fff;
        line-height: 1;
    }
    .stat-val.gold { color: var(--gold); }
    .stat-val.dim  { color: rgba(201,168,106,.45); }
    .stat-lbl {
        font-family: Arial, sans-serif;
        font-size: 8.5px;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: rgba(255,255,255,.38);
        margin-top: 4px;
    }
    .stat-sep { color: rgba(201,168,106,.25); font-size: 18px; }

    /* ── PROGRESS ── */
    .progress-wrap { max-width: 500px; margin: 0 auto; }
    .progress-labels {
        display: flex;
        justify-content: space-between;
        font-family: Arial, sans-serif;
        font-size: 9px;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: rgba(255,255,255,.35);
        margin-bottom: 8px;
    }
    .progress-track {
        height: 5px;
        background: rgba(255,255,255,.09);
        border-radius: 99px;
        overflow: hidden;
    }
    .progress-fill {
        height: 100%;
        width: 0;
        background: linear-gradient(to right, #C9A86A, #E8D5B0 80%, #C9A86A);
        border-radius: 99px;
        animation: fillBar 1.5s cubic-bezier(.22,1,.36,1) .6s forwards;
    }
    @keyframes fillBar { to { width: var(--progress-pct, 47%); } }

    .flipbook-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 26px;
        padding: 11px 28px;
        background: var(--gold);
        color: #1A0808;
        font-family: Arial, sans-serif;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 3px;
        text-transform: uppercase;
        text-decoration: none;
        border-radius: 99px;
        transition: background .18s, transform .18s, box-shadow .18s;
        box-shadow: 0 4px 20px rgba(201,168,106,.35);
    }
    .flipbook-btn:hover {
        background: #E8D5B0;
        transform: translateY(-2px);
        box-shadow: 0 8px 28px rgba(201,168,106,.45);
    }

    /* ── SECTION NAV ── */
    .section-nav {
        position: sticky;
        top: 0;
        z-index: 200;
        display: flex;
        justify-content: center;
        gap: 6px;
        padding: 10px 24px;
        background: rgba(248,241,232,.93);
        backdrop-filter: blur(14px);
        border-bottom: 1px solid rgba(201,168,106,.18);
        box-shadow: 0 2px 16px rgba(58,46,34,.07);
    }
    .section-nav a {
        font-family: Arial, sans-serif;
        font-size: 9.5px;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        text-decoration: none;
        color: var(--brown-lt);
        padding: 6px 16px;
        border-radius: 99px;
        border: 1px solid transparent;
        transition: background .18s, color .18s, border-color .18s;
    }
    .section-nav a:hover {
        background: var(--maroon);
        color: #fff;
        border-color: var(--maroon);
    }

    /* ── SECTION WRAPPER ── */
    .mb-section {
        max-width: 1280px;
        margin: 54px auto 0;
        padding: 0 28px;
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 22px;
    }
    .section-header-left {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }
    .section-icon { font-size: 18px; line-height: 1; }
    .section-label {
        font-size: 15px;
        color: var(--maroon);
        text-transform: uppercase;
        letter-spacing: 3px;
        font-weight: 700;
    }
    .section-range {
        font-family: Arial, sans-serif;
        font-size: 8.5px;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: var(--brown-mid);
        background: var(--gold-lt);
        padding: 4px 12px;
        border-radius: 99px;
        flex-shrink: 0;
    }
    .section-line {
        flex: 1;
        height: 1px;
        background: linear-gradient(to right, var(--gold), transparent);
    }

    /* ── GRID ── */
    .page-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
        gap: 18px;
    }
    .opening-grid {
        grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    }

    /* ── CARDS ── */
    .page-card {
        display: block;
        text-decoration: none;
        color: inherit;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
        min-height: 232px;
        background: var(--maroon-dk) center / cover no-repeat;
        transition: transform .24s, box-shadow .24s;
    }
    .page-card:hover {
        transform: translateY(-5px) scale(1.012);
        box-shadow: 0 18px 44px rgba(58,46,34,.22);
    }
    .page-card.plan {
        opacity: .48;
        pointer-events: none;
        filter: grayscale(.5);
    }

    .card-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(
            to bottom,
            rgba(25,6,6,.28) 0%,
            rgba(40,12,8,.72) 52%,
            rgba(20,4,4,.93) 100%
        );
        transition: background .24s;
    }
    .page-card.done .card-overlay {
        background: linear-gradient(
            to bottom,
            rgba(80,18,18,.22) 0%,
            rgba(80,20,10,.68) 52%,
            rgba(35,8,8,.92) 100%
        );
    }
    .page-card:hover .card-overlay {
        background: linear-gradient(
            to bottom,
            rgba(20,5,5,.12) 0%,
            rgba(35,10,6,.60) 52%,
            rgba(20,4,4,.88) 100%
        );
    }

    .card-body {
        position: relative;
        z-index: 2;
        display: flex;
        flex-direction: column;
        min-height: 232px;
        padding: 18px 18px 16px;
    }

    .card-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .page-num {
        font-family: Arial, sans-serif;
        font-size: 9px;
        letter-spacing: 2.5px;
        text-transform: uppercase;
        color: var(--gold);
        opacity: .75;
    }

    .status-badge {
        font-family: Arial, sans-serif;
        font-size: 8px;
        letter-spacing: 1.6px;
        text-transform: uppercase;
        padding: 3px 9px;
        border-radius: 99px;
    }
    .status-done { background: rgba(201,168,106,.2);  color: var(--gold);           border: 1px solid rgba(201,168,106,.38); }
    .status-wip  { background: rgba(255,255,255,.1);  color: rgba(255,255,255,.68); border: 1px solid rgba(255,255,255,.18); }
    .status-plan { background: rgba(255,255,255,.05); color: rgba(255,255,255,.28); border: 1px solid rgba(255,255,255,.08); }

    .card-main { flex: 1; margin: 24px 0 14px; }
    .page-title {
        font-size: 17px;
        font-weight: 700;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        text-shadow: 0 1px 10px rgba(0,0,0,.5);
        margin-bottom: 7px;
        line-height: 1.25;
    }
    .page-subtitle {
        font-size: 11px;
        color: rgba(255,255,255,.56);
        font-style: italic;
        line-height: 1.45;
    }

    .card-footer {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 8px;
    }
    .page-tags { display: flex; flex-wrap: wrap; gap: 5px; }
    .tag {
        font-family: Arial, sans-serif;
        font-size: 8px;
        letter-spacing: .8px;
        text-transform: uppercase;
        background: rgba(255,255,255,.09);
        border: 1px solid rgba(255,255,255,.14);
        color: rgba(255,255,255,.58);
        padding: 3px 8px;
        border-radius: 99px;
    }

    .open-link {
        font-family: Arial, sans-serif;
        font-size: 9.5px;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: var(--gold);
        flex-shrink: 0;
        opacity: 0;
        transform: translateX(-6px);
        transition: opacity .2s, transform .2s;
    }
    .page-card:hover .open-link {
        opacity: 1;
        transform: translateX(0);
    }

    /* ── FOOTER ── */
    .mb-footer {
        text-align: center;
        padding: 56px 24px 72px;
        margin-top: 64px;
    }
    .footer-ornament {
        color: var(--gold);
        opacity: .35;
        font-size: 13px;
        letter-spacing: 5px;
        margin-bottom: 14px;
    }
    .footer-brand {
        font-size: 14px;
        letter-spacing: 5px;
        text-transform: uppercase;
        color: var(--maroon);
        font-weight: 700;
        margin-bottom: 6px;
    }
    .footer-tag {
        font-family: Arial, sans-serif;
        font-size: 9.5px;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: var(--brown-mid);
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 680px) {
        .stats-row    { gap: 14px; padding: 12px 22px; }
        .stat-val     { font-size: 18px; }
        .page-grid    { grid-template-columns: 1fr 1fr; }
        .opening-grid { grid-template-columns: 1fr; }
        .section-nav a { font-size: 8.5px; padding: 6px 10px; }
    }
    @media (max-width: 420px) {
        .page-grid { grid-template-columns: 1fr; }
    }

    @media print { body { display: none; } }
    </style>
</head>

<body>

<?php
/* ── PAGE DATA ─────────────────────────────────────────────────────────── */

$bg  = 'assets/menu-book/backgrounds/';

$sections = [

    /* ── OPENING ── */
    [
        'id'    => 'opening',
        'label' => 'Opening Section',
        'range' => 'Page 00&ndash;01',
        'icon'  => '📖',
        'grid'  => 'opening-grid',
        'pages' => [
            [
                'num'    => '00',
                'url'    => site_url('menu_book/page/cover'),
                'title'  => 'Cover',
                'sub'    => 'Wajah depan buku menu NAMUA Coffee &amp; Eatery',
                'status' => 'done',
                'tags'   => ['Cover', 'Branding', 'A4'],
                'bg'     => base_url('assets/menu-book/cover-namua.png'),
            ],
            [
                'num'    => '01',
                'url'    => site_url('menu_book/page/opening'),
                'title'  => 'Opening Words',
                'sub'    => 'Kata sambutan &amp; kisah di balik NAMUA',
                'status' => 'done',
                'tags'   => ['Opening', 'Story', 'Brand Message'],
                'bg'     => base_url($bg . 'bg-signatures.png'),
            ],
        ],
    ],

    /* ── FOOD ── */
    [
        'id'    => 'food',
        'label' => 'Food Division',
        'range' => 'Page 02&ndash;09',
        'icon'  => '🍽',
        'grid'  => '',
        'pages' => [
            [
                'num'    => '02',
                'url'    => site_url('menu_book/food/main_character'),
                'title'  => 'Main Character',
                'sub'    => 'Hidangan paling ikonik — the icons of NAMUA',
                'status' => 'done',
                'tags'   => ['Main Character', '6 Produk', '6 Foto'],
                'bg'     => base_url($bg . 'bg-main-character.png'),
            ],
            [
                'num'    => '03',
                'url'    => site_url('menu_book/food/nusantara_comfort'),
                'title'  => 'Nusantara &amp; Comfort Food',
                'sub'    => 'Masakan Indonesia autentik bertemu cita rasa nostalgia',
                'status' => 'done',
                'tags'   => ['Indonesian Heritage', 'Crave Corner', '13 Produk'],
                'bg'     => base_url($bg . 'bg-nusantara-comfort.png'),
            ],
            [
                'num'    => '04',
                'url'    => site_url('menu_book/food/flame_flavor'),
                'title'  => 'Flame &amp; Flavor',
                'sub'    => 'Sajian panggang, western, bites &amp; pasta pilihan',
                'status' => 'done',
                'tags'   => ['Munch &amp; Meat', 'Classic Western', '12 Produk'],
                'bg'     => base_url($bg . 'bg-flame-flavor.png'),
            ],
            [
                'num'    => '05',
                'url'    => site_url('menu_book/food/asian_signatures'),
                'title'  => 'Asian Signatures',
                'sub'    => 'Cita rasa Asia yang kaya rempah dan otentik',
                'status' => 'done',
                'tags'   => ['Asian Course', '13 Produk'],
                'bg'     => base_url($bg . 'bg-asian.png'),
            ],
            [
                'num'    => '06',
                'url'    => site_url('menu_book/food/bowl_spice_noodles'),
                'title'  => 'Bowl, Spice &amp; Noodles',
                'sub'    => 'Pedas nampol, rice bowl hemat, mie favorit anak kos',
                'status' => 'done',
                'tags'   => ['Spicy', 'Rice Bowl', 'Anak Kos Core', '13 Produk'],
                'bg'     => base_url($bg . 'bg-bowl-spice-noodles.png'),
            ],
            [
                'num'    => '07',
                'url'    => site_url('menu_book/food/bites_of_joy'),
                'title'  => 'Bites of Joy',
                'sub'    => 'Dim sum, camilan crispy, dan sweet treats yang nagih',
                'status' => 'done',
                'tags'   => ['Dim Sum', 'Crispy Bites', 'Sweet Treats', '19 Produk'],
                'bg'     => base_url($bg . 'bg-bites.png'),
            ],
            [
                'num'    => '08',
                'url'    => site_url('menu_book/food/dessert_collection'),
                'title'  => 'Dessert Collection',
                'sub'    => 'Pemanis sempurna di penghujung makan',
                'status' => 'done',
                'tags'   => ['Dessert', '5 Produk', '5 Foto'],
                'bg'     => base_url($bg . 'bg-dessert-collection.png'),
            ],
            [
                'num'    => '09',
                'url'    => site_url('menu_book/food/extras_sides'),
                'title'  => 'Extras &amp; Sides',
                'sub'    => 'Pelengkap, carbo, dan sambal — makan makin lengkap',
                'status' => 'done',
                'tags'   => ['Carbo', 'Condiment', 'Sauce &amp; Sambal', '23 Produk'],
                'bg'     => base_url($bg . 'bg-extras.png'),
            ],
        ],
    ],

    /* ── BEVERAGE ── */
    [
        'id'    => 'beverage',
        'label' => 'Beverage Division',
        'range' => 'Page 10&ndash;20',
        'icon'  => '☕',
        'grid'  => '',
        'pages' => [
            [
                'num'    => '10',
                'url'    => site_url('menu_book/beverage/namua_signatures'),
                'title'  => 'NAMUA Signatures',
                'sub'    => 'Kopi dan teh artisan yang menjadi ikon rumah NAMUA',
                'status' => 'wip',
                'tags'   => ['Signature Coffee', 'Artisan Tea', '6 Produk'],
                'bg'     => base_url($bg . 'bg-signatures.png'),
            ],
            [
                'num'    => '11',
                'url'    => site_url('menu_book/beverage/house_masterpieces'),
                'title'  => 'House Masterpieces',
                'sub'    => 'Karya terbaik barista — dari masterpiece hingga favorit nenek',
                'status' => 'wip',
                'tags'   => ['Masterpiece Line', 'Favorite Grandma', '8 Produk'],
                'bg'     => base_url($bg . 'bg-masterpieces.png'),
            ],
            [
                'num'    => '12',
                'url'    => site_url('menu_book/beverage/coffee_atelier'),
                'title'  => 'The Coffee Atelier',
                'sub'    => 'Manual brew dan espresso klasik yang tak lekang waktu',
                'status' => 'wip',
                'tags'   => ['Manual Brew', 'Classic Coffee'],
                'bg'     => base_url($bg . 'bg-classics.png'),
            ],
            [
                'num'    => '13',
                'url'    => site_url('menu_book/beverage/cold_creamy_creations'),
                'title'  => 'Cold &amp; Creamy Creations',
                'sub'    => 'Cold brew segar dan latte creamy yang bikin adem',
                'status' => 'wip',
                'tags'   => ['Cold Brew Series', 'Latte Series'],
                'bg'     => base_url($bg . 'bg-cold.png'),
            ],
            [
                'num'    => '14',
                'url'    => site_url('menu_book/beverage/spark_refresh'),
                'title'  => 'Spark &amp; Refresh',
                'sub'    => 'Mocktail warna-warni dan minuman segar anti bosan',
                'status' => 'wip',
                'tags'   => ['Mocktail', 'Refreshing Drinks'],
                'bg'     => base_url($bg . 'bg-spark.png'),
            ],
            [
                'num'    => '15',
                'url'    => site_url('menu_book/beverage/blended_delights'),
                'title'  => 'Blended Delights',
                'sub'    => 'Blend creamy, smoothie buah segar, dan sweet milk series',
                'status' => 'wip',
                'tags'   => ['Blend &amp; Smoothies', 'Sweet &amp; Milk Series'],
                'bg'     => base_url($bg . 'bg-blend.png'),
            ],
            [
                'num'    => '16',
                'url'    => site_url('menu_book/beverage/tea_tradition'),
                'title'  => 'Tea &amp; Tradition',
                'sub'    => 'Teh tradisional dan wedangan hangat yang menenangkan',
                'status' => 'wip',
                'tags'   => ['Tea Series', 'Wedangan'],
                'bg'     => base_url($bg . 'bg-tea.png'),
            ],
            [
                'num'    => '17',
                'url'    => site_url('menu_book/beverage/sweet_scoops'),
                'title'  => 'Sweet Scoops',
                'sub'    => 'Es krim lembut dalam berbagai kreasi yang menyenangkan',
                'status' => 'wip',
                'tags'   => ['Ice Cream', '4 Produk'],
                'bg'     => base_url($bg . 'bg-ice.png'),
            ],
            [
                'num'    => '18',
                'url'    => site_url('menu_book/beverage/kopsu_literan'),
                'title'  => 'Kopi Susu Literan',
                'sub'    => 'Kopi susu segar ukuran 1 liter — beli banyak lebih hemat',
                'status' => 'wip',
                'tags'   => ['Kopi Susu', 'Literan', 'Ready to Drink'],
                'bg'     => base_url($bg . 'bg-literan.png'),
            ],
            [
                'num'    => '19',
                'url'    => site_url('menu_book/beverage/namua_bundle_club'),
                'title'  => 'Bundling Hemat',
                'sub'    => 'Paket bundling terbaik untuk nongkrong bareng lebih hemat',
                'status' => 'wip',
                'tags'   => ['Bundling', 'Hemat', 'Promo Spesial'],
                'bg'     => base_url($bg . 'bg-bundle.png'),
            ],
            [
                'num'    => '20',
                'url'    => site_url('menu_book/beverage/extras_enhancers'),
                'title'  => 'Extras &amp; Enhancers',
                'sub'    => 'Add-on &amp; syrup untuk mempersonalisasi setiap minumanmu',
                'status' => 'wip',
                'tags'   => ['Add-On Minuman', 'Top-Up', 'Syrup &amp; Extra'],
                'bg'     => base_url($bg . 'bg-extras.png'),
            ],
        ],
    ],
];

/* ── COUNTS ── */
$total = 0; $done_ct = 0; $wip_ct = 0;
foreach ($sections as $s) {
    foreach ($s['pages'] as $p) {
        $total++;
        if ($p['status'] === 'done') $done_ct++;
        elseif ($p['status'] === 'wip') $wip_ct++;
    }
}
$pct = round($done_ct / $total * 100, 1);
?>

<!-- ── HERO ──────────────────────────────────────────────── -->
<header class="hero">
    <div class="hero-inner">
        <img src="<?= base_url('assets/menu-book/logo/logo.png') ?>"
             class="hero-logo" alt="NAMUA"
             onerror="this.style.display='none'">

        <div class="hero-eyebrow">NAMUA Coffee &amp; Eatery</div>
        <div class="hero-ornament">&#9670; &mdash;&mdash;&mdash;&mdash;&mdash;&mdash;&mdash;&mdash;&mdash; &#9670;</div>
        <h1 class="hero-title">Menu Book</h1>
        <p class="hero-desc">Dashboard visual buku menu digital &amp; cetak — klik halaman aktif untuk membuka preview</p>

        <div class="stats-row">
            <div class="stat">
                <div class="stat-val"><?= $total ?></div>
                <div class="stat-lbl">Total Halaman</div>
            </div>
            <div class="stat-sep">&middot;</div>
            <div class="stat">
                <div class="stat-val gold"><?= $done_ct ?></div>
                <div class="stat-lbl">Selesai</div>
            </div>
            <div class="stat-sep">&middot;</div>
            <div class="stat">
                <div class="stat-val dim"><?= $wip_ct ?></div>
                <div class="stat-lbl">Dalam Proses</div>
            </div>
        </div>

        <div class="progress-wrap">
            <div class="progress-labels">
                <span>Progress Buku Menu</span>
                <span><?= $done_ct ?> / <?= $total ?> Halaman Selesai &mdash; <?= $pct ?>%</span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" style="--progress-pct: <?= $pct ?>%"></div>
            </div>
        </div>

        <a href="<?= site_url('menu_book/flipbook') ?>" class="flipbook-btn">
            &#9654;&nbsp;&nbsp;Buka Flipbook
        </a>
    </div>
</header>

<!-- ── SECTION NAV ──────────────────────────────────────── -->
<nav class="section-nav">
    <?php foreach ($sections as $s): ?>
    <a href="#<?= $s['id'] ?>">
        <?= $s['icon'] ?>&nbsp;&nbsp;<?= $s['label'] ?>
    </a>
    <?php endforeach; ?>
</nav>

<!-- ── SECTIONS ─────────────────────────────────────────── -->
<?php foreach ($sections as $section): ?>
<section id="<?= $section['id'] ?>" class="mb-section">

    <div class="section-header">
        <div class="section-header-left">
            <span class="section-icon"><?= $section['icon'] ?></span>
            <h2 class="section-label"><?= $section['label'] ?></h2>
        </div>
        <span class="section-range"><?= $section['range'] ?></span>
        <div class="section-line"></div>
    </div>

    <div class="page-grid <?= $section['grid'] ?>">
    <?php foreach ($section['pages'] as $pg):
        $is_plan  = ($pg['status'] === 'plan');
        $bg_attr  = !empty($pg['bg'])
            ? ' style="background-image:url(\'' . $pg['bg'] . '\')"'
            : '';
        $badge    = match($pg['status']) {
            'done'  => 'Done',
            'wip'   => 'WIP',
            default => 'Soon',
        };
    ?>
        <a href="<?= $pg['url'] ?>"
           <?= !$is_plan ? 'target="_blank"' : '' ?>
           class="page-card <?= $pg['status'] ?>"<?= $bg_attr ?>>

            <div class="card-overlay"></div>
            <div class="card-body">

                <div class="card-top">
                    <div class="page-num">Halaman <?= $pg['num'] ?></div>
                    <span class="status-badge status-<?= $pg['status'] ?>"><?= $badge ?></span>
                </div>

                <div class="card-main">
                    <div class="page-title"><?= $pg['title'] ?></div>
                    <div class="page-subtitle"><?= $pg['sub'] ?></div>
                </div>

                <div class="card-footer">
                    <div class="page-tags">
                        <?php foreach ($pg['tags'] as $tag): ?>
                        <span class="tag"><?= $tag ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!$is_plan): ?>
                    <div class="open-link">&#8599; Buka</div>
                    <?php endif; ?>
                </div>

            </div>
        </a>
    <?php endforeach; ?>
    </div>

</section>
<?php endforeach; ?>

<!-- ── FOOTER ───────────────────────────────────────────── -->
<footer class="mb-footer">
    <div class="footer-ornament">&#9670; &mdash;&mdash;&mdash;&mdash;&mdash;&mdash;&mdash;&mdash;&mdash; &#9670;</div>
    <div class="footer-brand">NAMUA Coffee &amp; Eatery</div>
    <div class="footer-tag">#kembalikenamua</div>
</footer>

</body>
</html>
