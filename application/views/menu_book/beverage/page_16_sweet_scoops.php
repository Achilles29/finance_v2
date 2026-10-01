<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>SWEET SCOOPS - NAMUA</title>

<!-- SWEET SCOOPS - A4 PORTRAIT -->
<style>
@page{size:A4 portrait;margin:0}
*{box-sizing:border-box}
body{margin:0;background:#2b1b15}

  * { box-sizing: border-box; }

  body {
    margin: 0;
    background: #eee7dc;
    font-family: 'Montserrat', sans-serif;
  }

  /* .page{
    width:210mm;
    height:297mm;
    margin:auto;
    position:relative;
    overflow:hidden;
    background:url("<?= base_url('assets/menu-book/backgrounds/bg-ice.png');?>") center/cover no-repeat;
    font-family:Arial,sans-serif;
    color:#1f1a12;
} */


  .menu-page {
    width: 210mm;
    height: 297mm;
    margin: 0 auto;
    position: relative;
    overflow: hidden;
    color: #3a2416;
    padding: 13mm 13mm 9mm;
    background:url("<?= base_url('assets/menu-book/backgrounds/bg-ice.png');?>") center/cover no-repeat;
  }

  .menu-page::before {
    content: "";
    position: absolute;
    inset: 7mm;
    border: 1.5px solid rgba(178, 121, 55, .48);
    border-radius: 18px;
    pointer-events: none;
  }

  .menu-page::after {
    content: "";
    position: absolute;
    inset: 10mm;
    border: 1px solid rgba(178, 121, 55, .24);
    border-radius: 14px;
    pointer-events: none;
  }

  .header {
    position: relative;
    z-index: 2;
    text-align: center;
    margin-bottom: 7mm;
  }

  .title {
    font-family: 'Cinzel', serif;
    font-size: 52px;
    line-height: .88;
    letter-spacing: 7px;
    margin: 0;
    color: #3a2112;
  }

  .mini-icon {
    font-size: 21px;
    margin: 3mm 0 1.5mm;
    color: #b27532;
  }

  .subtitle {
    font-family: 'Cormorant Garamond', serif;
    font-style: italic;
    font-size: 22px;
    line-height: 1.05;
    color: #684020;
  }

  .section-title {
    position: relative;
    z-index: 2;
    width: max-content;
    margin: 5mm auto 5mm;
    padding: 2mm 17mm;
    border: 1.5px solid rgba(178, 121, 55, .55);
    border-radius: 999px;
    background: rgba(255,255,255,.82);
    font-family: 'Cormorant Garamond', serif;
    font-style: italic;
    font-size: 27px;
    color: #684020;
    box-shadow: 0 5px 15px rgba(94, 55, 22, .08);
  }

  .grid {
    position: relative;
    z-index: 2;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 5mm;
  }

  .card {
    overflow: hidden;
    border-radius: 16px;
    background: rgba(255, 252, 247, .91);
    border: 1.4px solid rgba(178, 121, 55, .50);
    box-shadow: 0 10px 22px rgba(70, 42, 18, .10);
  }

  .photo {
    width: 100%;
    height: 62mm;
    background: #f4eadf center/cover no-repeat;
    border-bottom: 1.2px solid rgba(178, 121, 55, .35);
  }

  .info {
    padding: 3mm 4mm 3.6mm;
    text-align: center;
    min-height: 25mm;
  }

  .name {
    font-family: 'Cinzel', serif;
    font-size: 18px;
    line-height: 1.02;
    letter-spacing: .5px;
    font-weight: 800;
    color: #3a2416;
    min-height: 37px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .price {
    margin-top: 1.5mm;
    padding-top: 1.8mm;
    border-top: 1px dashed rgba(178, 121, 55, .42);
    font-family: 'Cinzel', serif;
    font-size: 26px;
    line-height: 1;
    font-weight: 800;
    color: #ad6e2e;
  }

  .footer {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 13mm;
    z-index: 3;
    text-align: center;
    color: #4c2c18;
  }

  .hashtag {
    font-family: 'Cormorant Garamond', serif;
    font-style: italic;
    font-size: 19px;
    letter-spacing: 5px;
  }

  .instagram {
    margin-top: 1mm;
    font-size: 16px;
    letter-spacing: 1.5px;
    font-weight: 600;
  }

  .ig-icon {
    display: inline-flex;
    width: 18px;
    height: 18px;
    border: 1.8px solid #4c2c18;
    border-radius: 5px;
    vertical-align: -3px;
    margin-right: 5px;
    position: relative;
  }

  .ig-icon::before {
    content: "";
    width: 7px;
    height: 7px;
    border: 1.6px solid #4c2c18;
    border-radius: 50%;
    position: absolute;
    top: 4px;
    left: 4px;
  }

  .ig-icon::after {
    content: "";
    width: 3px;
    height: 3px;
    background: #4c2c18;
    border-radius: 50%;
    position: absolute;
    top: 3px;
    right: 3px;
  }

  .caramel { background-image: url("<?= base_url('assets/menu-book/products/beverages/ice/caramel-ice-cream.png'); ?>"); }
  .choco-oreo { background-image: url("<?= base_url('assets/menu-book/products/beverages/ice/choco-oreo-ice-cream.png'); ?>"); }
  .deluxe-choco { background-image: url("<?= base_url('assets/menu-book/products/beverages/ice/deluxe-choco-almond.png'); ?>"); }
  .strawberry { background-image: url("<?= base_url('assets/menu-book/products/beverages/ice/strawberry-ice-cream.png'); ?>"); }
</style>

<div class="menu-page">

  <div class="header">
    <h1 class="title">SWEET<br>SCOOPS</h1>
    <div class="mini-icon">◦ ✦ 🍨 ✦ ◦</div>
    <div class="subtitle">
      Ice cream creations.<br>
      Simple, playful, dessert corner.
    </div>
  </div>

  <div class="section-title">Ice Cream</div>

  <div class="grid">
    <div class="card">
      <div class="photo caramel"></div>
      <div class="info">
        <div class="name">CARAMEL ICE CREAM</div>
        <div class="price">20 K</div>
      </div>
    </div>

    <div class="card">
      <div class="photo choco-oreo"></div>
      <div class="info">
        <div class="name">CHOCO OREO<br>ICE CREAM</div>
        <div class="price">20 K</div>
      </div>
    </div>

    <div class="card">
      <div class="photo deluxe-choco"></div>
      <div class="info">
        <div class="name">DELUXE<br>CHOCO ALMOND</div>
        <div class="price">20 K</div>
      </div>
    </div>

    <div class="card">
      <div class="photo strawberry"></div>
      <div class="info">
        <div class="name">STRAWBERRY<br>ICE CREAM</div>
        <div class="price">20 K</div>
      </div>
    </div>
  </div>

  <div class="footer">
    <div class="hashtag">#kembalikenamua</div>
    <div class="instagram"><span class="ig-icon"></span>@namuacoffee</div>
  </div>

</div>