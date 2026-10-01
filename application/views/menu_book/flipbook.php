<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Menu Book — NAMUA Coffee &amp; Eatery</title>
    <link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= base_url('assets/img/favicon-32x32.png') ?>">
    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
        --top:    52px;
        --bot:    90px;
        --gold:   #C9A86A;
        --maroon: #7D1F1F;
        --dark:   #0A0604;
    }
    html, body {
        height: 100%; overflow: hidden;
        background: var(--dark);
        font-family: Arial, sans-serif;
        color: #F8F1E8;
        -webkit-tap-highlight-color: transparent;
        user-select: none;
    }

    /* ── TOPBAR ──────────────────────────────── */
    #topbar {
        position: fixed; top:0; left:0; right:0;
        height: var(--top);
        background: rgba(10,6,4,.95);
        backdrop-filter: blur(14px);
        border-bottom: 1px solid rgba(201,168,106,.14);
        display: flex; align-items: center;
        padding: 0 14px; gap: 10px; z-index: 900;
    }
    .tb-side { display:flex; align-items:center; gap:8px; flex:1; min-width:0; }
    .tb-side.right { justify-content:flex-end; }
    #tb-logo { height:27px; flex-shrink:0; filter:brightness(1.1); }
    .tb-brand {
        font-size:10px; letter-spacing:3.5px; text-transform:uppercase;
        color:var(--gold); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .tb-brand b { color:rgba(201,168,106,.45); font-weight:400; }
    .tb-center { display:flex; flex-direction:column; align-items:center; gap:2px; flex-shrink:0; }
    #page-section { font-size:8px; letter-spacing:2px; text-transform:uppercase; color:rgba(201,168,106,.42); }
    #page-counter { font-size:13px; font-weight:700; color:var(--gold); }
    .tb-btn {
        width:32px; height:32px; border-radius:7px;
        border:1px solid rgba(201,168,106,.18); background:rgba(201,168,106,.07);
        color:rgba(201,168,106,.7); cursor:pointer; flex-shrink:0;
        display:flex; align-items:center; justify-content:center;
        text-decoration:none; transition:.15s;
    }
    .tb-btn:hover { background:rgba(201,168,106,.18); color:var(--gold); border-color:rgba(201,168,106,.4); }
    #progress-line {
        position:absolute; bottom:0; left:0; height:2px;
        background:linear-gradient(to right, var(--maroon), var(--gold));
        width:0; transition:width .4s ease;
    }

    /* ── STAGE ───────────────────────────────── */
    #stage {
        position: fixed;
        top: var(--top); left:0; right:0; bottom: var(--bot);
        display: flex; align-items: center; justify-content: center;
        perspective: 2200px;
    }

    /* ── BOOK ────────────────────────────────── */
    #book {
        position: relative;
        transform-style: preserve-3d;
        /* dimensions set by JS */
    }
    /* Book drop-shadow */
    #book::after {
        content:'';
        position:absolute;
        bottom:-18px; left:5%; width:90%; height:30px;
        background:radial-gradient(ellipse at center, rgba(0,0,0,.6) 0%, transparent 72%);
        pointer-events:none;
    }

    /* ── LEFT PAGE ───────────────────────────── */
    #page-left {
        position: absolute;
        top:0; left:0; width:50%; height:100%;
        overflow: hidden;
        background: #fff;
        border-radius: 2px 0 0 2px;
    }
    /* spine shadow (falls on left page from center) */
    #page-left::after {
        content:''; position:absolute; top:0; right:0;
        width:18px; height:100%;
        background: linear-gradient(to left, rgba(0,0,0,.25) 0%, transparent 100%);
        pointer-events:none; z-index:3;
    }

    /* ── RIGHT PAGE ──────────────────────────── */
    #page-right {
        position: absolute;
        top:0; right:0; width:50%; height:100%;
        overflow: hidden;
        background: #fff;
        border-radius: 0 2px 2px 0;
    }
    /* spine shadow (falls on right page from center) */
    #page-right::before {
        content:''; position:absolute; top:0; left:0;
        width:18px; height:100%;
        background: linear-gradient(to right, rgba(0,0,0,.25) 0%, transparent 100%);
        pointer-events:none; z-index:3;
    }

    /* Iframes inside pages */
    .page-iframe {
        position:absolute; top:0; left:0;
        width:794px; height:1123px; border:none; display:block;
        opacity:0; transition:opacity .35s .08s;
        pointer-events:none;
        /* transform scale set by JS */
    }
    .page-iframe.ready { opacity:1; }

    /* Endpaper (decorative for spread 0 left page) */
    #endpaper {
        position:absolute; inset:0; z-index:2;
        background:
            radial-gradient(ellipse 70% 55% at 50% 80%, rgba(201,168,106,.1), transparent),
            linear-gradient(170deg, #200808 0%, #5A1515 45%, #7D1F1F 72%, #9E2D2D 100%);
        display:none; flex-direction:column; align-items:center; justify-content:center; gap:14px;
        pointer-events:none;
    }
    #endpaper img  { height:54px; filter:brightness(1.1) drop-shadow(0 2px 12px rgba(201,168,106,.38)); }
    #endpaper p    { font-family:Georgia,serif; font-size:9px; letter-spacing:5px;
                     text-transform:uppercase; color:var(--gold); opacity:.55; }
    #endpaper hr   { border:none; width:50px; height:1px; background:rgba(201,168,106,.3); }

    /* ── FLIP CARD (3D page turn element) ───── */
    #flip-card {
        position: absolute;
        top: 0; height: 100%;
        transform-style: preserve-3d;
        pointer-events: none;
        z-index: 20;
        display: none;
    }
    .flip-face {
        position: absolute; inset: 0;
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
        overflow: hidden;
        background: #3A2E22 center/cover no-repeat;
    }
    /* Back face starts pre-rotated 180° so it shows after card flips */
    #flip-back { transform: rotateY(180deg); }

    /* Shadow that slides across the flipping page */
    .flip-face::after {
        content:''; position:absolute; inset:0; pointer-events:none;
        background: linear-gradient(to right, transparent 60%, rgba(0,0,0,.3) 100%);
    }
    #flip-back::after {
        background: linear-gradient(to left, transparent 60%, rgba(0,0,0,.2) 100%);
    }

    /* ── NAV BUTTONS ─────────────────────────── */
    .nav-btn {
        position:absolute; top:50%; transform:translateY(-50%);
        z-index:30; width:44px; height:72px;
        background:rgba(10,6,4,.55);
        border:1px solid rgba(201,168,106,.18); border-radius:8px;
        color:var(--gold); font-size:26px; cursor:pointer; line-height:1;
        display:flex; align-items:center; justify-content:center;
        transition:background .18s, opacity .2s; backdrop-filter:blur(6px);
    }
    .nav-btn:hover    { background:rgba(100,25,25,.55); border-color:rgba(201,168,106,.4); }
    .nav-btn:disabled { opacity:.15; cursor:default; pointer-events:none; }
    #btn-prev { left:-56px; }
    #btn-next { right:-56px; }

    /* ── PAGE INFO ───────────────────────────── */
    #page-info {
        position:absolute; bottom:-50px;
        left:0; right:0; text-align:center; pointer-events:none;
    }
    #pi-title { font-family:Georgia,serif; font-size:12px; letter-spacing:2px;
                text-transform:uppercase; color:rgba(248,241,232,.8); }
    #pi-meta  { font-size:9px; letter-spacing:1.5px; text-transform:uppercase;
                color:rgba(201,168,106,.48); margin-top:3px; }

    /* ── THUMB STRIP ─────────────────────────── */
    #thumbstrip {
        position:fixed; bottom:0; left:0; right:0; height:var(--bot);
        background:rgba(8,5,3,.97);
        border-top:1px solid rgba(201,168,106,.1);
        display:flex; align-items:center; z-index:900; overflow:hidden;
    }
    #thumbrail {
        display:flex; align-items:center; gap:6px;
        padding:0 16px; overflow-x:auto; height:100%;
        scroll-behavior:smooth;
        -webkit-overflow-scrolling:touch; scrollbar-width:none;
    }
    #thumbrail::-webkit-scrollbar { display:none; }

    /* Each spread thumbnail = 2 mini A4 pages side by side */
    .spread-wrap { display:flex; flex-direction:column; align-items:center; flex-shrink:0; }

    .spread-thumb {
        display:flex; gap:1px; cursor:pointer;
        border:2px solid rgba(201,168,106,.14); border-radius:4px; overflow:hidden;
        transition:border-color .18s, transform .18s, box-shadow .18s;
    }
    .spread-thumb:hover  { transform:translateY(-3px); border-color:rgba(201,168,106,.45); }
    .spread-thumb.active {
        border-color:var(--gold); transform:translateY(-4px);
        box-shadow:0 0 0 1px var(--gold), 0 4px 14px rgba(0,0,0,.55);
    }

    .sthumb-half { width:28px; height:40px; background:#1A1108 center/cover no-repeat; }
    .sthumb-ep   { background:linear-gradient(135deg, #2C0F0F, #5A1515); }

    .spread-num {
        font-size:7px; letter-spacing:.5px; color:rgba(201,168,106,.38); margin-top:3px;
    }

    .section-sep {
        flex-shrink:0; font-size:7px; letter-spacing:2px; text-transform:uppercase;
        color:rgba(201,168,106,.32); writing-mode:vertical-rl; text-orientation:mixed;
        rotate:180deg; padding:0 4px; border-right:1px solid rgba(201,168,106,.1);
        height:46px; display:flex; align-items:center;
    }

    /* ── SPINNER ─────────────────────────────── */
    .spin-wrap {
        position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
        background:rgba(248,241,232,.92); z-index:1; pointer-events:none;
        transition:opacity .3s; border-radius:inherit;
    }
    .spin-wrap.gone { opacity:0; }
    .spinner {
        width:24px; height:24px;
        border:2.5px solid rgba(125,31,31,.15);
        border-top-color:var(--maroon); border-radius:50%;
        animation:spin .75s linear infinite;
    }
    @keyframes spin { to { transform:rotate(360deg); } }

    /* ── MOBILE ──────────────────────────────── */
    @media (max-width:680px) {
        /* Show only one page at a time */
        #page-left { display:none; }
        #page-right { left:0; width:100%; border-radius:2px; }
        #page-right::before { display:none; }
        #btn-prev { left:-48px; width:36px; }
        #btn-next { right:-48px; width:36px; }
        .tb-brand  { display:none; }
        .sthumb-half { width:22px; height:31px; }
    }

    @media print { body { display:none; } }
    </style>
</head>
<body>

<!-- ── TOPBAR ─────────────────────────────────────── -->
<header id="topbar">
    <div class="tb-side">
        <img id="tb-logo" src="<?= base_url('assets/menu-book/logo/logo.png') ?>"
             alt="NAMUA" onerror="this.style.display='none'">
        <span class="tb-brand">NAMUA <b>Menu Book</b></span>
    </div>
    <div class="tb-center">
        <div id="page-section">Opening Section</div>
        <div id="page-counter">1 / 11</div>
    </div>
    <div class="tb-side right">
        <button id="btn-fs" class="tb-btn" title="Layar Penuh (F)">
            <svg id="fsi-in"  viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M3 3h5v2H5v3H3V3zm9 0h5v5h-2V5h-3V3zM3 12h2v3h3v2H3v-5zm12 3h-3v2h5v-5h-2v3z"/></svg>
            <svg id="fsi-out" viewBox="0 0 20 20" fill="currentColor" width="14" height="14" style="display:none"><path d="M7 2H5v3H2v2h5V2zm6 0h2v3h3v2h-5V2zM2 13h3v3h2v-5H2v2zm11 3h2v-3h3v-2h-5v5z"/></svg>
        </button>
        <a href="<?= site_url('menu_book') ?>" class="tb-btn" title="Kembali ke Index (Esc)">
            <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M10 3L2 10h2v7h5v-4h2v4h5v-7h2L10 3z"/></svg>
        </a>
    </div>
    <div id="progress-line"></div>
</header>

<!-- ── STAGE ──────────────────────────────────────── -->
<div id="stage">
    <div id="book">

        <!-- Left page -->
        <div id="page-left">
            <!-- Endpaper shown on spread 0 -->
            <div id="endpaper">
                <img src="<?= base_url('assets/menu-book/logo/logo.png') ?>" alt="NAMUA" onerror="this.style.display='none'">
                <hr>
                <p>NAMUA Coffee &amp; Eatery</p>
            </div>
            <iframe id="iframe-left"  class="page-iframe" title="Halaman Kiri"></iframe>
            <div id="spin-left"  class="spin-wrap"><div class="spinner"></div></div>
        </div>

        <!-- Right page -->
        <div id="page-right">
            <iframe id="iframe-right" class="page-iframe" title="Halaman Kanan"></iframe>
            <div id="spin-right" class="spin-wrap"><div class="spinner"></div></div>
        </div>

        <!-- 3D Flip card — covers one half during page turn -->
        <div id="flip-card">
            <div class="flip-face" id="flip-front"></div>
            <div class="flip-face" id="flip-back"></div>
        </div>

        <button id="btn-prev" class="nav-btn" aria-label="Sebelumnya">&#8249;</button>
        <button id="btn-next" class="nav-btn" aria-label="Berikutnya">&#8250;</button>

        <div id="page-info">
            <div id="pi-title">Cover</div>
            <div id="pi-meta">Halaman 00 &middot; Opening Section</div>
        </div>
    </div>
</div>

<!-- ── THUMB STRIP ────────────────────────────────── -->
<div id="thumbstrip"><div id="thumbrail"></div></div>

<!-- ─────────────────────────────────────────────── -->
<script>
(function(){
'use strict';

/* ── DATA ──────────────────────────────────────────── */
const B  = '<?= base_url() ?>';
const S  = '<?= site_url() ?>';
const BG = B + 'assets/menu-book/backgrounds/';

const PAGES = [
    { n:'00', t:'Cover',                    s:'Opening Section',   url:S+'menu_book/page/cover',                     thumb:B+'assets/menu-book/cover-namua.png' },
    { n:'01', t:'Opening Words',            s:'Opening Section',   url:S+'menu_book/page/opening',                   thumb:BG+'bg-signatures.png' },
    { n:'02', t:'Main Character',           s:'Food Division',     url:S+'menu_book/food/main_character',            thumb:BG+'bg-main-character.png' },
    { n:'03', t:'Nusantara & Comfort Food', s:'Food Division',     url:S+'menu_book/food/nusantara_comfort',         thumb:BG+'bg-nusantara-comfort.png' },
    { n:'04', t:'Flame & Flavor',           s:'Food Division',     url:S+'menu_book/food/flame_flavor',              thumb:BG+'bg-flame-flavor.png' },
    { n:'05', t:'Asian Signatures',         s:'Food Division',     url:S+'menu_book/food/asian_signatures',          thumb:BG+'bg-asian.png' },
    { n:'06', t:'Bowl, Spice & Noodles',    s:'Food Division',     url:S+'menu_book/food/bowl_spice_noodles',        thumb:BG+'bg-bowl-spice-noodles.png' },
    { n:'07', t:'Bites of Joy',             s:'Food Division',     url:S+'menu_book/food/bites_of_joy',              thumb:BG+'bg-bites.png' },
    { n:'08', t:'Dessert Collection',       s:'Food Division',     url:S+'menu_book/food/dessert_collection',        thumb:BG+'bg-dessert-collection.png' },
    { n:'09', t:'Extras & Sides',           s:'Food Division',     url:S+'menu_book/food/extras_sides',              thumb:BG+'bg-extras.png' },
    { n:'10', t:'NAMUA Signatures',         s:'Beverage Division', url:S+'menu_book/beverage/namua_signatures',      thumb:BG+'bg-signatures.png' },
    { n:'11', t:'House Masterpieces',       s:'Beverage Division', url:S+'menu_book/beverage/house_masterpieces',    thumb:BG+'bg-masterpieces.png' },
    { n:'12', t:'The Coffee Atelier',       s:'Beverage Division', url:S+'menu_book/beverage/coffee_atelier',        thumb:BG+'bg-classics.png' },
    { n:'13', t:'Cold & Creamy Creations',  s:'Beverage Division', url:S+'menu_book/beverage/cold_creamy_creations', thumb:BG+'bg-cold.png' },
    { n:'14', t:'Spark & Refresh',          s:'Beverage Division', url:S+'menu_book/beverage/spark_refresh',         thumb:BG+'bg-spark.png' },
    { n:'15', t:'Blended Delights',         s:'Beverage Division', url:S+'menu_book/beverage/blended_delights',      thumb:BG+'bg-blend.png' },
    { n:'16', t:'Tea & Tradition',          s:'Beverage Division', url:S+'menu_book/beverage/tea_tradition',         thumb:BG+'bg-tea.png' },
    { n:'17', t:'Sweet Scoops',             s:'Beverage Division', url:S+'menu_book/beverage/sweet_scoops',          thumb:BG+'bg-ice.png' },
    { n:'18', t:'Extras & Enhancers',       s:'Beverage Division', url:S+'menu_book/beverage/extras_enhancers',      thumb:BG+'bg-extras.png' },
    { n:'19', t:'Kopi Susu Literan',        s:'Beverage Division', url:S+'menu_book/beverage/kopsu_literan',         thumb:BG+'bg-literan.png' },
    { n:'20', t:'Bundling Hemat',           s:'Beverage Division', url:S+'menu_book/beverage/namua_bundle_club',     thumb:BG+'bg-bundle.png' },
];

/* ── SPREAD LAYOUT ─────────────────────────────────
   Spread 0 : endpaper (left) | Cover/page[0] (right)
   Spread k : page[2k-1] (left) | page[2k] (right)
   MAX_SPREAD = 10  →  spreads 0…10
──────────────────────────────────────────────────── */
const MAX_SPREAD = Math.floor((PAGES.length - 1) / 2);  // = 10

function getLeft(sp)  { return (sp === 0) ? null : (sp*2-1 < PAGES.length ? sp*2-1 : null); }
function getRight(sp) { const i = sp*2; return i < PAGES.length ? i : null; }

function spreadLabel(sp) {
    const parts = [];
    const li = getLeft(sp);
    const ri = getRight(sp);
    if (li !== null) parts.push(PAGES[li].n);
    if (ri !== null) parts.push(PAGES[ri].n);
    return parts.join(' – ');
}
function spreadSection(sp) {
    const ri = getRight(sp), li = getLeft(sp);
    const p  = ri !== null ? PAGES[ri] : (li !== null ? PAGES[li] : null);
    return p ? p.s : 'Opening Section';
}
function spreadTitle(sp) {
    const ri = getRight(sp), li = getLeft(sp);
    const p  = ri !== null ? PAGES[ri] : (li !== null ? PAGES[li] : null);
    return p ? p.t : 'Cover';
}

/* ── DOM ───────────────────────────────────────────── */
const stage      = document.getElementById('stage');
const book       = document.getElementById('book');
const pageLeft   = document.getElementById('page-left');
const pageRight  = document.getElementById('page-right');
const endpaper   = document.getElementById('endpaper');
const ifrL       = document.getElementById('iframe-left');
const ifrR       = document.getElementById('iframe-right');
const spinL      = document.getElementById('spin-left');
const spinR      = document.getElementById('spin-right');
const flipCard   = document.getElementById('flip-card');
const flipFront  = document.getElementById('flip-front');
const flipBack   = document.getElementById('flip-back');
const btnPrev    = document.getElementById('btn-prev');
const btnNext    = document.getElementById('btn-next');
const piTitle    = document.getElementById('pi-title');
const piMeta     = document.getElementById('pi-meta');
const pSection   = document.getElementById('page-section');
const pCounter   = document.getElementById('page-counter');
const progLine   = document.getElementById('progress-line');
const thumbrail  = document.getElementById('thumbrail');
const btnFs      = document.getElementById('btn-fs');
const fsiIn      = document.getElementById('fsi-in');
const fsiOut     = document.getElementById('fsi-out');

/* ── STATE ─────────────────────────────────────────── */
let curSpread = 0;
let flipping  = false;
let scale     = 1;
let isMobile  = false;

/* ── SCALE ─────────────────────────────────────────── */
function calcScale() {
    const sw = stage.offsetWidth;
    const sh = stage.offsetHeight;
    isMobile  = sw < 700;

    const bookW = isMobile ? 794 : 1588;   // 1 or 2 A4 pages
    const bookH = 1123;
    const padW  = isMobile ? 96 : 130;     // space for nav buttons
    const padH  = 80;                       // space for page-info

    scale = Math.min((sw - padW) / bookW, (sh - padH) / bookH, 1);

    const dw = Math.floor(794  * scale);
    const dh = Math.floor(1123 * scale);
    const bw = isMobile ? dw : dw * 2;

    book.style.width  = bw + 'px';
    book.style.height = dh + 'px';

    /* Scale iframes (they're 794×1123, downscaled with transform) */
    [ifrL, ifrR].forEach(f => {
        f.style.transform       = 'scale(' + scale + ')';
        f.style.transformOrigin = 'top left';
    });

    /* On mobile show only one page */
    pageLeft.style.display = isMobile ? 'none' : '';
    if (isMobile) {
        pageRight.style.left   = '0';
        pageRight.style.width  = '100%';
        pageRight.style.borderRadius = '2px';
    } else {
        pageRight.style.left  = '';
        pageRight.style.width = '';
        pageRight.style.borderRadius = '';
    }
}

/* ── IFRAME HELPERS ────────────────────────────────── */
function loadFrame(ifrEl, spinEl, pageIdx) {
    if (pageIdx === null) {
        ifrEl.src = 'about:blank';
        ifrEl.removeAttribute('data-loaded');
        ifrEl.classList.remove('ready');
        spinEl.classList.remove('gone');
        return;
    }
    const url = PAGES[pageIdx].url;
    if (ifrEl.getAttribute('data-loaded') === url) return;
    ifrEl.removeAttribute('data-loaded');
    ifrEl.classList.remove('ready');
    spinEl.classList.remove('gone');
    ifrEl.onload = function() {
        ifrEl.setAttribute('data-loaded', url);
        ifrEl.classList.add('ready');
        spinEl.classList.add('gone');
    };
    ifrEl.src = url;
}

/* Preload into hidden off-screen iframes so they're cached */
const preCache = {};
function preloadPage(idx) {
    if (idx < 0 || idx >= PAGES.length || preCache[idx]) return;
    const h = document.createElement('iframe');
    h.src = PAGES[idx].url;
    h.style.cssText = 'position:absolute;left:-9999px;width:1px;height:1px;border:none;opacity:0;';
    document.body.appendChild(h);
    preCache[idx] = true;
}
function preloadSpread(sp) {
    if (sp < 0 || sp > MAX_SPREAD) return;
    const li = getLeft(sp), ri = getRight(sp);
    if (li !== null) preloadPage(li);
    if (ri !== null) preloadPage(ri);
}

/* ── SET FACE BACKGROUND ───────────────────────────── */
function setFaceBg(el, thumbUrl) {
    el.style.backgroundImage    = thumbUrl ? "url('" + thumbUrl + "')" : '';
    el.style.backgroundSize     = 'cover';
    el.style.backgroundPosition = 'center';
    el.style.backgroundColor    = thumbUrl ? '#1A1108' : '#3A2E22';
}

/* ── SHOW SPREAD (instant, no animation) ───────────── */
function showSpread(sp) {
    const li = getLeft(sp);
    const ri = getRight(sp);

    endpaper.style.display = (!isMobile && sp === 0) ? 'flex' : 'none';

    if (isMobile) {
        /* Mobile: show only right page (the main content page) */
        loadFrame(ifrR, spinR, ri);
    } else {
        loadFrame(ifrL, spinL, li);
        loadFrame(ifrR, spinR, ri);
    }

    preloadSpread(sp - 1);
    preloadSpread(sp + 1);
}

/* ── 3D FLIP ───────────────────────────────────────── */
function doFlip(fromSp, toSp) {
    if (flipping) return;
    flipping = true;

    const forward  = toSp > fromSp;
    const DURATION = 660;
    const MID      = Math.floor(DURATION * 0.46);

    const fromL = getLeft(fromSp),  fromR = getRight(fromSp);
    const toL   = getLeft(toSp),    toR   = getRight(toSp);

    /* ── Flip card appearance ──
       Forward: front = current right page, back = next left page
       Backward: front = current left page,  back = prev right page  */
    const frontIdx = forward ? fromR : fromL;
    const backIdx  = forward ? toL   : toR;

    setFaceBg(flipFront, frontIdx !== null ? PAGES[frontIdx].thumb : null);
    setFaceBg(flipBack,  backIdx  !== null ? PAGES[backIdx ].thumb : null);

    /* ── Position flip card ──
       Forward: starts over RIGHT half, hinges on LEFT edge of right half
       Backward: starts over LEFT half, hinges on RIGHT edge of left half  */
    if (isMobile) {
        flipCard.style.left  = '0';
        flipCard.style.width = '100%';
        flipCard.style.transformOrigin = forward ? 'left center' : 'right center';
    } else if (forward) {
        flipCard.style.left  = '50%';
        flipCard.style.width = '50%';
        flipCard.style.transformOrigin = 'left center';
    } else {
        flipCard.style.left  = '0';
        flipCard.style.width = '50%';
        flipCard.style.transformOrigin = 'right center';
    }

    /* ── Load strategy ──
       The half that is COVERED by the flip card at t=0 can be swapped immediately.
       The half that is VISIBLE at t=0 must only swap at or after the midpoint
       (when the flip card has moved over to cover it).

       Forward:
         - Right half covered at t=0 → load toRight immediately
         - Left half visible at t=0  → load toLeft at midpoint
       Backward:
         - Left half covered at t=0 → load toLeft immediately
         - Right half visible at t=0 → load toRight at midpoint        */

    if (forward) {
        loadFrame(ifrR, spinR, toR);
        endpaper.style.display = (!isMobile && toSp === 0) ? 'flex' : 'none';
        setTimeout(function() { loadFrame(ifrL, spinL, toL); }, MID);
    } else {
        loadFrame(ifrL, spinL, toL);
        endpaper.style.display = (!isMobile && toSp === 0) ? 'flex' : 'none';
        setTimeout(function() { loadFrame(ifrR, spinR, toR); }, MID);
    }

    /* ── Animate ── */
    flipCard.style.display    = 'block';
    flipCard.style.transition = 'none';
    flipCard.style.transform  = 'none';
    flipCard.offsetHeight;  /* force reflow */

    flipCard.style.transition = 'transform ' + DURATION + 'ms cubic-bezier(.25,.46,.45,.94)';
    flipCard.style.transform  = forward
        ? 'perspective(2200px) rotateY(-180deg)'
        : 'perspective(2200px) rotateY(180deg)';

    setTimeout(function() {
        flipCard.style.display    = 'none';
        flipCard.style.transition = 'none';
        flipCard.style.transform  = 'none';
        curSpread = toSp;
        flipping  = false;
        updateUI();
        preloadSpread(toSp - 1);
        preloadSpread(toSp + 1);
    }, DURATION + 40);
}

/* ── NAVIGATE ──────────────────────────────────────── */
function goTo(sp, animate) {
    if (flipping) return;
    sp = Math.max(0, Math.min(MAX_SPREAD, sp));
    if (!animate || sp === curSpread) {
        curSpread = sp;
        showSpread(sp);
        updateUI();
        return;
    }
    doFlip(curSpread, sp);
}

function next() { if (!flipping) goTo(curSpread + 1, true); }
function prev() { if (!flipping) goTo(curSpread - 1, true); }

/* ── UPDATE UI ─────────────────────────────────────── */
function updateUI() {
    piTitle.textContent  = spreadTitle(curSpread);
    const lbl = spreadLabel(curSpread);
    piMeta.innerHTML     = lbl ? 'Halaman ' + lbl + ' &middot; ' + spreadSection(curSpread) : spreadSection(curSpread);
    pSection.textContent = spreadSection(curSpread);
    pCounter.textContent = (curSpread + 1) + ' / ' + (MAX_SPREAD + 1);
    progLine.style.width = ((curSpread + 1) / (MAX_SPREAD + 1) * 100).toFixed(1) + '%';
    btnPrev.disabled = curSpread === 0;
    btnNext.disabled = curSpread === MAX_SPREAD;

    document.querySelectorAll('.spread-thumb').forEach(function(el, i) {
        el.classList.toggle('active', i === curSpread);
    });
    var active = document.getElementById('stw-' + curSpread);
    if (active) active.scrollIntoView({ behavior:'smooth', inline:'center', block:'nearest' });
}

/* ── BUILD THUMBS ──────────────────────────────────── */
var SECS = [
    { label:'Opening',  from:0, to:0 },
    { label:'Food',     from:1, to:5 },
    { label:'Beverage', from:6, to:MAX_SPREAD },
];
SECS.forEach(function(sec) {
    var sep = document.createElement('div');
    sep.className = 'section-sep';
    sep.textContent = sec.label;
    thumbrail.appendChild(sep);

    for (var sp = sec.from; sp <= sec.to; sp++) {
        var li = getLeft(sp), ri = getRight(sp);
        var wrap = document.createElement('div');
        wrap.className = 'spread-wrap';
        wrap.id = 'stw-' + sp;

        var st = document.createElement('div');
        st.className = 'spread-thumb';
        (function(s){ st.addEventListener('click', function(){ goTo(s, true); }); })(sp);

        var tL = document.createElement('div');
        tL.className = 'sthumb-half' + (li === null ? ' sthumb-ep' : '');
        if (li !== null) tL.style.backgroundImage = "url('" + PAGES[li].thumb + "')";

        var tR = document.createElement('div');
        tR.className = 'sthumb-half';
        if (ri !== null) tR.style.backgroundImage = "url('" + PAGES[ri].thumb + "')";

        st.append(tL, tR);

        var num = document.createElement('div');
        num.className = 'spread-num';
        num.textContent = spreadLabel(sp) || '00';

        wrap.append(st, num);
        thumbrail.appendChild(wrap);
    }
});

/* ── KEYBOARD ──────────────────────────────────────── */
document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT') return;
    switch(e.key) {
        case 'ArrowRight': case 'ArrowDown':  e.preventDefault(); next(); break;
        case 'ArrowLeft':  case 'ArrowUp':    e.preventDefault(); prev(); break;
        case 'Home': e.preventDefault(); goTo(0, true); break;
        case 'End':  e.preventDefault(); goTo(MAX_SPREAD, true); break;
        case 'Escape': location.href = S + 'menu_book'; break;
        case 'f': case 'F': toggleFS(); break;
    }
});

/* ── TOUCH SWIPE ───────────────────────────────────── */
var tx0 = 0, ty0 = 0;
stage.addEventListener('touchstart', function(e) {
    tx0 = e.touches[0].clientX;
    ty0 = e.touches[0].clientY;
}, {passive:true});
stage.addEventListener('touchend', function(e) {
    var dx = tx0 - e.changedTouches[0].clientX;
    var dy = ty0 - e.changedTouches[0].clientY;
    if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 44) {
        dx > 0 ? next() : prev();
    }
}, {passive:true});

/* Click on book edges for flip */
pageRight.addEventListener('click', function(e) {
    if (flipping) return;
    var x = e.clientX - pageRight.getBoundingClientRect().left;
    if (x > pageRight.offsetWidth * 0.65) next();
});
pageLeft.addEventListener('click', function(e) {
    if (flipping) return;
    var x = e.clientX - pageLeft.getBoundingClientRect().left;
    if (x < pageLeft.offsetWidth * 0.35) prev();
});

/* ── FULLSCREEN ────────────────────────────────────── */
function toggleFS() {
    if (!document.fullscreenElement)
        document.documentElement.requestFullscreen().catch(function(){});
    else
        document.exitFullscreen().catch(function(){});
}
btnFs.addEventListener('click', toggleFS);
document.addEventListener('fullscreenchange', function() {
    var fs = !!document.fullscreenElement;
    fsiIn.style.display  = fs ? 'none' : '';
    fsiOut.style.display = fs ? '' : 'none';
    setTimeout(function(){ calcScale(); }, 80);
});

/* ── NAV BUTTONS ───────────────────────────────────── */
btnPrev.addEventListener('click', prev);
btnNext.addEventListener('click', next);

/* ── RESIZE ────────────────────────────────────────── */
var rsTimer;
window.addEventListener('resize', function() {
    clearTimeout(rsTimer);
    rsTimer = setTimeout(function(){ calcScale(); }, 80);
});

/* ── INIT ──────────────────────────────────────────── */
calcScale();

var initSp = Math.min(MAX_SPREAD, Math.max(0,
    parseInt(new URLSearchParams(location.search).get('p') || '0', 10) || 0));
goTo(initSp, false);

})();
</script>
</body>
</html>
