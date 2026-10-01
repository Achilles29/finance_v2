<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title><?php echo html_escape((string)($title ?? 'Print QR Meja')); ?></title>
<style>
*,*::before,*::after{
  box-sizing:border-box;
  margin:0;
  padding:0;
}

body{
  font-family:'Segoe UI',Arial,sans-serif;
}

/* CONTROL BAR */
#ctrl{
  position:sticky;
  top:0;
  z-index:999;
  background:#18181b;
  color:#fff;
  padding:8px 16px;
  display:flex;
  align-items:center;
  flex-wrap:wrap;
  gap:8px 14px;
  border-bottom:2px solid #b91c1c;
}

#ctrl .c-title{
  font-size:13px;
  font-weight:800;
  white-space:nowrap;
}

#ctrl .c-sep{
  width:1px;
  background:#3f3f46;
  align-self:stretch;
}

#ctrl label{
  display:flex;
  align-items:center;
  gap:6px;
  font-size:12px;
  color:#d4d4d8;
}

#ctrl input[type=text]{
  width:200px;
  background:#27272a;
  border:1px solid #52525b;
  color:#fff;
  padding:6px 10px;
  border-radius:7px;
  font-size:12px;
}

#ctrl input[type=checkbox]{
  accent-color:#dc2626;
}

#ctrl .c-count{
  margin-left:auto;
  font-size:12px;
  color:#a1a1aa;
}

#ctrl .btn-print{
  background:#dc2626;
  color:#fff;
  border:none;
  padding:8px 18px;
  border-radius:8px;
  font-size:13px;
  font-weight:800;
  cursor:pointer;
}

#ctrl .btn-print:hover{
  background:#991b1b;
}

/* SCREEN */
@media screen{
  body{
    background:#525659;
  }

  .page-wrap{
    width:297mm;
    min-height:210mm;
    background:#fff;
    margin:16px auto 32px;
    padding:10mm;
    box-shadow:0 4px 28px rgba(0,0,0,.45);
  }
}

/* PRINT */
@media print{
  @page{
    size:A4 landscape;
    margin:10mm;
  }

  body{
    background:#fff;
  }

  #ctrl{
    display:none !important;
  }

  .page-wrap{
    width:277mm;
    min-height:190mm;
    margin:0;
    padding:0;
    box-shadow:none;
  }
}

/* GRID */
.card-grid{
  width:100%;
  display:grid;
  grid-template-columns:repeat(4, 1fr);
  column-gap:5mm;
  row-gap:7mm;
  align-items:start;
}

/* Pecah halaman tiap 8 kartu */
.qr-card:nth-child(8n){
  break-after:page;
  page-break-after:always;
}

/* CARD */
.qr-card{
  width:100%;
  height:84mm;
  background:#fff;
  border:.35mm solid #fecaca;
  border-radius:5mm;
  overflow:hidden;
  display:flex;
  flex-direction:column;
  page-break-inside:avoid;
  break-inside:avoid;
  box-shadow:0 1mm 3mm rgba(127,29,29,.08);
}

/* HEADER */
.qr-card__hd{
  height:17mm;
  flex:0 0 17mm;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  text-align:center;
  padding:1mm 2mm;
  color:#fff;
  background:
    radial-gradient(circle at 20% 0%,rgba(255,255,255,.22),transparent 28%),
    linear-gradient(135deg,#7f1d1d 0%,#b91c1c 46%,#dc2626 100%);
}

.qr-card__brand{
  width:100%;
  font-size:6.5pt;
  font-weight:800;
  line-height:1;
  letter-spacing:.14em;
  text-transform:uppercase;
  opacity:.86;
  margin-bottom:1.1mm;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

.qr-card__name{
  width:100%;
  font-size:20pt;
  font-weight:950;
  line-height:.92;
  letter-spacing:-.5pt;
  text-transform:uppercase;
  color:#fff;
  text-shadow:0 .4mm 1mm rgba(0,0,0,.28);
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

.qr-card__sublabel{
  width:100%;
  font-size:6pt;
  font-weight:800;
  line-height:1;
  margin-top:.9mm;
  opacity:.92;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

/* BODY */
.qr-card__bd{
    flex:1;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    gap:3mm;
    padding:2mm;
}

/* QR */
.qr-card__qr{
  background:#fff;
  border:.45mm solid #fee2e2;
  border-radius:3mm;
  padding:1.5mm;
  line-height:0;
  box-shadow:0 1mm 4mm rgba(0,0,0,.06);
}

.qr-card__qr img{
  width:42mm;
  height:42mm;
  display:block;
}

/* INSTRUCTION */
.qr-card__instr{
  width:100%;
  text-align:center;
  line-height:1.15;
}

.qr-card__instr-main{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:1.5mm;

    font-size:13pt;
    font-weight:800;
    color:#111827;
    line-height:1.15;
}


.qr-card__instr-sub{
    margin-top:1.2mm;

    font-size:9pt;
    font-weight:500;
    color:#4b5563;
    line-height:1.25;
}



/* CAPACITY */
.qr-card__cap{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:1mm;
  background:#fef2f2;
  border:.3mm solid #fecaca;
  color:#991b1b;
  border-radius:999px;
  padding:.8mm 3mm;
  font-size:6.5pt;
  font-weight:900;
  white-space:nowrap;
}

/* FOOTER URL */
.qr-card__ft{
  flex:0 0 6mm;
  height:6mm;
  background:#fff7f7;
  border-top:.25mm solid #fee2e2;
  padding:1.2mm 2mm;
  text-align:center;
  overflow:hidden;
}

.qr-card__url{
  display:block;
  width:100%;
  font-family:'Courier New',monospace;
  font-size:5.5pt;
  line-height:1;
  color:#b91c1c;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

/* Saat URL disembunyikan */
.ctrl-url-el[style*="display: none"]{
  display:none !important;
}

/* Print lebih aman */
@media print{
  .card-grid{
    grid-template-columns:repeat(4, 1fr);
    column-gap:5mm;
    row-gap:7mm;
  }

  .qr-card{
    height:84mm;
    box-shadow:none;
  }
}
</style>
</head>
<body>

<!-- ── Control bar ────────────────────────────────────────────── -->
<div id="ctrl">
  <span class="c-title">&#128438; Print QR Meja &mdash; A4 Landscape · 4×2</span>
  <div class="c-sep"></div>

  <label>
    <span>Nama Tempat</span>
    <input type="text" id="ctrl-brand" placeholder="Nama restoran / café" maxlength="50">
  </label>

  <div class="c-sep"></div>

  <label>
    <input type="checkbox" id="ctrl-url" checked>
    <span>Tampilkan URL</span>
  </label>
  <label>
    <input type="checkbox" id="ctrl-cap">
    <span>Kapasitas</span>
  </label>

  <span class="c-count" id="ctrl-count"></span>

  <button class="btn-print" onclick="window.print()">Cetak</button>
</div>

<!-- ── A4 Landscape page wrapper ─────────────────────────────── -->
<div class="page-wrap">
  <div class="card-grid" id="card-grid">

  <?php
  $rows     = is_array($rows ?? null) ? $rows : [];
  $settings = is_array($settings ?? null) ? $settings : [];
  foreach ($rows as $row):
    $nama     = (string)($row['nama_meja'] ?? 'Meja');
    $label    = (string)($row['qr_label']  ?? '');
    $url      = (string)($row['qr_url']    ?? '');
    $capacity = (int)($row['capacity']     ?? 0);
    /* Request 300×300 — high quality, CSS scales to 33mm */
    $qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&ecc=H&data=' . rawurlencode($url);
  ?>

  <div class="qr-card"
       data-capacity="<?php echo $capacity; ?>"
       data-url="<?php echo html_escape($url); ?>">

    <div class="qr-card__hd">
      <div class="qr-card__brand ctrl-brand-txt">Scan &amp; Order</div>
      <div class="qr-card__name"><?php echo html_escape($nama); ?></div>
      <?php if ($label !== ''): ?>
      <div class="qr-card__sublabel"><?php echo html_escape($label); ?></div>
      <?php endif; ?>
    </div>

    <div class="qr-card__bd">
      <div class="qr-card__qr">
        <img src="<?php echo $qrSrc; ?>"
             alt="QR <?php echo html_escape($nama); ?>"
             loading="lazy">
      </div>

      <div class="qr-card__instr">
        <div class="qr-card__instr-main">📷 Scan untuk memesan</div>
        <div class="qr-card__instr-sub">Arahkan kamera HP ke kode di atas</div>
      </div>

      <?php if ($capacity > 0): ?>
      <div class="qr-card__cap ctrl-cap-el">
        👥 <?php echo $capacity; ?> orang
      </div>
      <?php endif; ?>
    </div>

    <div class="qr-card__ft ctrl-url-el">
      <div class="qr-card__url"><?php echo html_escape($url); ?></div>
    </div>

  </div>

  <?php endforeach; ?>

  <?php if (empty($rows)): ?>
  <div style="grid-column:1/-1;padding:30mm 0;text-align:center;color:#6b7280;font-size:12pt">
    Belum ada meja aktif untuk dicetak.
  </div>
  <?php endif; ?>

  </div><!-- /card-grid -->
</div><!-- /page-wrap -->

<script>
(function(){
  const LS = 'pos_qr_print_';

  const ctrlBrand = document.getElementById('ctrl-brand');
  const ctrlUrl   = document.getElementById('ctrl-url');
  const ctrlCap   = document.getElementById('ctrl-cap');
  const countEl   = document.getElementById('ctrl-count');

  // ── Card count ────────────────────────────────────────────────────────────
  const total = document.querySelectorAll('.qr-card').length;
  countEl.textContent = total + ' meja aktif';

  // ── Restore saved preferences ─────────────────────────────────────────────
  ctrlBrand.value  = localStorage.getItem(LS + 'brand') || '';
  ctrlUrl.checked  = localStorage.getItem(LS + 'url')   !== 'false';
  ctrlCap.checked  = localStorage.getItem(LS + 'cap')   === 'true';

  // ── Apply: brand name ─────────────────────────────────────────────────────
  function applyBrand(){
    const v = ctrlBrand.value.trim();
    localStorage.setItem(LS + 'brand', v);
    document.querySelectorAll('.ctrl-brand-txt').forEach(el =>
      el.textContent = v || 'Scan & Order'
    );
  }

  // ── Apply: show/hide URL footer ───────────────────────────────────────────
  function applyUrl(){
    const show = ctrlUrl.checked;
    localStorage.setItem(LS + 'url', show);
    document.querySelectorAll('.ctrl-url-el').forEach(el =>
      el.style.display = show ? '' : 'none'
    );
  }

  // ── Apply: show/hide capacity ─────────────────────────────────────────────
  function applyCap(){
    const show = ctrlCap.checked;
    localStorage.setItem(LS + 'cap', show);
    document.querySelectorAll('.ctrl-cap-el').forEach(el =>
      el.style.display = show ? '' : 'none'
    );
  }

  // ── Initial render ────────────────────────────────────────────────────────
  applyBrand();
  applyUrl();
  applyCap();

  // ── Listeners ─────────────────────────────────────────────────────────────
  ctrlBrand.addEventListener('input',  applyBrand);
  ctrlUrl.addEventListener('change',   applyUrl);
  ctrlCap.addEventListener('change',   applyCap);
})();
</script>
</body>
</html>
