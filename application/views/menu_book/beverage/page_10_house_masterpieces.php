<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>House Of Masterpiece</title>

<style>
@page{size:A4 portrait;margin:0}
*{box-sizing:border-box}
body{margin:0;background:#2b1b15}

  * {
    box-sizing: border-box;
  }

  body {
    margin: 0;
    background: #e9e1d3;
    font-family: 'Montserrat', sans-serif;
  }


  .menu-page {
    width: 210mm;
    height: 297mm;
    margin: 0 auto;
    position: relative;
    overflow: hidden;
    background:
      url("<?= base_url('assets/menu-book/backgrounds/bg-masterpieces.png'); ?>") center/cover no-repeat;
    color: #2d2016;
    padding: 12mm 9mm 9mm;
  }

  .menu-page::before {
    content: "";
    position: absolute;
    inset: 7mm;
    border: 1.4px solid rgba(142, 92, 38, .42);
    border-radius: 18px;
    pointer-events: none;
  }

  .menu-page::after {
    content: "";
    position: absolute;
    inset: 10mm;
    border: 1px solid rgba(191, 143, 73, .24);
    border-radius: 14px;
    pointer-events: none;
  }

  .header {
    position: relative;
    z-index: 2;
    text-align: center;
    margin-bottom: 7mm;
  }

  .eyebrow {
    font-family: 'Cormorant Garamond', serif;
    font-style: italic;
    font-size: 15px;
    letter-spacing: 3px;
    color: #8d5b27;
    margin-bottom: 1mm;
  }

  .title {
    font-family: 'Cinzel', serif;
    font-size: 42px;
    line-height: .92;
    letter-spacing: 1.5px;
    color: #3a2515;
    margin: 0;
  }

  .subtitle {
    font-family: 'Cormorant Garamond', serif;
    font-style: italic;
    font-size: 20px;
    color: #7a4e24;
    margin-top: 3mm;
  }

  .section {
    position: relative;
    z-index: 2;
    margin-top: 4mm;
  }

  .section-title {
    width: max-content;
    margin: 0 auto 4mm;
    padding: 2mm 10mm;
    border: 1.4px solid rgba(142, 92, 38, .62);
    border-radius: 999px;
    background: rgba(255, 255, 255, .78);
    font-family: 'Cinzel', serif;
    font-size: 20px;
    letter-spacing: 1.5px;
    color: #3a2515;
  }

  .grid-masterpiece {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1mm;
  }

  .grid-grandma {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2mm;
  }

  .card {
    border: 1.2px solid rgba(142, 92, 38, .55);
    border-radius: 13px;
    background: rgba(255, 252, 246, .86);
    overflow: hidden;
    box-shadow: 0 8px 20px rgba(70, 42, 20, .10);
  }

  .photo {
    width: 100%;
    height: 47mm;
    background: #f3eadc center/cover no-repeat;
    border-bottom: 1px solid rgba(142, 92, 38, .35);
  }

  .grandma .photo {
    height: 49mm;
  }

  .info {
    padding: 2.8mm 2.5mm 3mm;
    text-align: center;
    min-height: 31mm;
  }

  .grandma .info {
    min-height: 34mm;
  }

  .name {
    font-family: 'Cinzel', serif;
    font-size: 12.5px;
    line-height: 1.05;
    font-weight: 800;
    letter-spacing: .4px;
    color: #332013;
    min-height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .price {
    margin: 1.2mm 0;
    font-family: 'Cinzel', serif;
    font-size: 18px;
    font-weight: 800;
    color: #9b6526;
  }

  .desc {
    padding-top: 1.5mm;
    border-top: 1px dashed rgba(142, 92, 38, .38);
    font-size: 7.6px;
    line-height: 1.42;
    letter-spacing: .45px;
    text-transform: uppercase;
    color: #5a412f;
  }

  .grandma .name {
    font-size: 16px;
  }

  .grandma .desc {
    font-size: 8.6px;
  }

  .footer {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 8mm;
    z-index: 3;
    text-align: center;
    color: #5b3920;
  }

  .hashtag {
    font-family: 'Cormorant Garamond', serif;
    font-style: italic;
    font-size: 18px;
    letter-spacing: 5px;
  }

  .instagram {
    margin-top: 1mm;
    font-size: 15px;
    letter-spacing: 1.5px;
    font-weight: 500;
  }

  .ig-icon {
    display: inline-flex;
    width: 17px;
    height: 17px;
    border: 1.8px solid #5b3920;
    border-radius: 5px;
    vertical-align: -3px;
    margin-right: 5px;
    position: relative;
  }

  .ig-icon::before {
    content: "";
    width: 6px;
    height: 6px;
    border: 1.6px solid #5b3920;
    border-radius: 50%;
    position: absolute;
    top: 4px;
    left: 4px;
  }

  .ig-icon::after {
    content: "";
    width: 3px;
    height: 3px;
    background: #5b3920;
    border-radius: 50%;
    position: absolute;
    top: 3px;
    right: 3px;
  }

  /* GANTI FILE FOTO SESUAI NAMA ASSET */
  .dirty-latte { background-image: url("<?= base_url('assets/menu-book/products/beverages/masterpieces/dirty-latte.png'); ?>"); }
  .dirty-matcha { background-image: url("<?= base_url('assets/menu-book/products/beverages/masterpieces/dirty-matcha-latte.png'); ?>"); }
  .amber-berry { background-image: url("<?= base_url('assets/menu-book/products/beverages/masterpieces/amber-berry-shot.png'); ?>"); }
  .jagung-manis { background-image: url("<?= base_url('assets/menu-book/products/beverages/masterpieces/jagung-manis-brew.png'); ?>"); }
  .palm-district { background-image: url("<?= base_url('assets/menu-book/products/beverages/masterpieces/palm-district.png'); ?>"); }
  .rum-dmc { background-image: url("<?= base_url('assets/menu-book/products/beverages/masterpieces/rum-dmc.png'); ?>"); }
  .st-by-lis { background-image: url("<?= base_url('assets/menu-book/products/beverages/masterpieces/st-by-lis.png'); ?>"); }
  .kopi-aren { background-image: url("<?= base_url('assets/menu-book/products/beverages/masterpieces/kopi-susu-gula-aren.png'); ?>"); }
</style>

<div class="menu-page">

  <div class="header">
    <div class="eyebrow">Premium Experimental Artisan Beverages</div>
    <h1 class="title">HOUSE<br>MASTERPIECES</h1>
    <div class="subtitle">Crafted beyond ordinary coffee.</div>
  </div>

  <section class="section">
    <div class="section-title">Masterpiece Line</div>

    <div class="grid-masterpiece">
      <div class="card">
        <div class="photo dirty-latte"></div>
        <div class="info">
          <div class="name">DIRTY LATTE</div>
          <div class="price">30 K</div>
          <div class="desc">Full Arabica, oat fusion milk distillation</div>
        </div>
      </div>

      <div class="card">
        <div class="photo dirty-matcha"></div>
        <div class="info">
          <div class="name">DIRTY MATCHA LATTE</div>
          <div class="price">30 K</div>
          <div class="desc">Matcha, double shot full Arabica, oat fusion milk distillation</div>
        </div>
      </div>

      <div class="card">
        <div class="photo amber-berry"></div>
        <div class="info">
          <div class="name">AMBER BERRY SHOT</div>
          <div class="price">23 K</div>
          <div class="desc">Double shot espresso, honey, strawberry jam</div>
        </div>
      </div>

      <div class="card">
        <div class="photo jagung-manis"></div>
        <div class="info">
          <div class="name">JAGUNG MANIS BREW</div>
          <div class="price">23 K</div>
          <div class="desc">Full Arabica double shot, caramel, oat fusion milk distillation, sea salt cream cheese, pop corn on top</div>
        </div>
      </div>

      <div class="card">
        <div class="photo palm-district"></div>
        <div class="info">
          <div class="name">PALM DISTRICT</div>
          <div class="price">23 K</div>
          <div class="desc">Double shot espresso, hazelnut, palm sugar</div>
        </div>
      </div>
    </div>
  </section>

  <section class="section" style="margin-top: 8mm;">
    <div class="section-title">Favorite Grandma</div>

    <div class="grid-grandma">
      <div class="card grandma">
        <div class="photo rum-dmc"></div>
        <div class="info">
          <div class="name">RUM DMC</div>
          <div class="price">20 K</div>
          <div class="desc">Espresso, rum, fresh milk, cream cheese</div>
        </div>
      </div>

      <div class="card grandma">
        <div class="photo st-by-lis"></div>
        <div class="info">
          <div class="name">ST BY LIS</div>
          <div class="price">22 K</div>
          <div class="desc">Single espresso, rum, strawberry, fresh milk, cream cheese</div>
        </div>
      </div>

      <div class="card grandma">
        <div class="photo kopi-aren"></div>
        <div class="info">
          <div class="name">KOPI SUSU<br>GULA AREN</div>
          <div class="price">19 K</div>
          <div class="desc">Single espresso, milk, aren sugar</div>
        </div>
      </div>
    </div>
  </section>

  <div class="footer">
    <div class="hashtag">#kembalikenamua</div>
    <div class="instagram"><span class="ig-icon"></span>@namuacoffee</div>
  </div>

</div>