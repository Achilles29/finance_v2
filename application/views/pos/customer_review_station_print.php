<?php
$station = is_array($station ?? null) ? $station : [];
$stationUrl = trim((string)($station_url ?? ''));
$businessProfile = is_array($business_profile ?? null) ? $business_profile : [];
$businessName = trim((string)($businessProfile['display_name'] ?? ''));
$businessName = $businessName !== '' ? $businessName : 'Finance';
$outletName = trim((string)($station['outlet_name'] ?? $businessName));
$outletName = $outletName !== '' ? $outletName : $businessName;
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cetak QR Ulasan - <?= html_escape((string)($station['station_name'] ?? '')) ?></title>
  <style>
    *{box-sizing:border-box}body{margin:0;background:#292b30;font-family:"Trebuchet MS",Arial,sans-serif;color:#352529}.controls{position:sticky;top:0;z-index:5;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.8rem 1.1rem;background:#15161a;color:#fff}.controls small{color:#c7c2c5}.controls button{border:0;border-radius:9px;padding:.62rem .95rem;background:#ce4037;color:#fff;font-weight:800;cursor:pointer}.sheet{width:210mm;min-height:297mm;margin:1.25rem auto;background:#fff;padding:16mm;box-shadow:0 10px 40px rgba(0,0,0,.42);display:grid;place-items:center}.poster{width:158mm;min-height:245mm;border:1.1mm solid #a80e27;border-radius:10mm;padding:15mm 14mm;display:flex;flex-direction:column;align-items:center;text-align:center;background:radial-gradient(circle at 50% 0,rgba(222,94,68,.15),transparent 29%),linear-gradient(180deg,#fffdf9,#fff7f0)}.eyebrow{margin-top:2mm;color:#b33a31;font-size:11pt;letter-spacing:.2em;font-weight:900;text-transform:uppercase}.poster h1{margin:6mm 0 4mm;font-size:32pt;line-height:1.03;letter-spacing:-.055em}.poster p{max-width:115mm;margin:0;color:#735e58;font-size:13pt;line-height:1.5}.qr-frame{width:100mm;height:100mm;margin:13mm 0 8mm;padding:5mm;background:#fff;border:1mm solid #edc7bd;border-radius:7mm;box-shadow:0 3mm 8mm rgba(118,57,40,.09);display:grid;place-items:center}.poster-qr{width:100%;height:100%;display:grid;place-items:center}.poster-qr img,.poster-qr canvas{display:none!important}.poster-qr [data-pos-qr-visual="true"]{display:block!important;width:100%!important;height:100%!important;image-rendering:pixelated}.qr-render-fallback{font-size:10pt;line-height:1.45;color:#9a3b32;text-align:center;padding:8mm}.scan{display:inline-block;margin-top:2mm;padding:4mm 7mm;border-radius:999px;background:#a80e27;color:#fff;font-size:15pt;font-weight:900}.station{margin-top:auto;border-top:.35mm dashed #d9b7ab;padding-top:7mm;width:100%;font-size:11pt;color:#7e6962}.station strong{display:block;color:#4c3432;font-size:15pt;margin-bottom:1.5mm}.tiny{font-size:8pt!important;word-break:break-all;color:#a18a82!important;margin-top:4mm!important}@media print{@page{size:A4 portrait;margin:0}body{background:#fff}.controls{display:none}.sheet{width:210mm;min-height:297mm;margin:0;padding:16mm;box-shadow:none}}
  </style>
</head>
<body>
<div class="controls"><div><strong>QR Ulasan Pelanggan</strong><br><small><?= html_escape((string)($station['station_name'] ?? '')) ?></small></div><button onclick="window.print()">Cetak QR</button></div>
<main class="sheet"><article class="poster"><div class="eyebrow"><?= html_escape($businessName) ?></div><h1>Bagaimana pengalaman Anda hari ini?</h1><p>Scan QR ini untuk memberi ulasan dan bergabung sebagai member <?= html_escape($businessName) ?> agar tidak ketinggalan poin dan voucher.</p><div class="qr-frame"><div class="poster-qr" id="station-review-qr" data-qr-url="<?= html_escape($stationUrl) ?>" role="img" aria-label="QR ulasan pelanggan"></div></div><div class="scan">SCAN UNTUK ULASAN & MEMBER</div><div class="station"><strong><?= html_escape((string)($station['station_name'] ?? 'QR Ulasan')) ?></strong><?= html_escape($outletName) ?><p class="tiny"><?= html_escape($stationUrl) ?></p></div></article></main>
<script src="<?= base_url('assets/vendor/qrcodejs/qrcode.min.js') ?>"></script>
<script src="<?= base_url('assets/js/pos-local-qr.js') ?>?v=20260825g"></script>
<script>
window.addEventListener('load', function () {
  var target = document.getElementById('station-review-qr');
  if (!target) return;
  if (window.PosLocalQr) {
    window.PosLocalQr.render(target, target.getAttribute('data-qr-url'), {size: 720, label: 'QR ulasan pelanggan'});
  } else {
    target.classList.add('qr-render-fallback');
    target.textContent = 'QR belum dapat dimuat. Muat ulang halaman.';
  }
});
</script>
</body>
</html>
