<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cold & Creamy Creations</title>

<style>
@import url('https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Montserrat:wght@500;600;700;800;900&family=Parisienne&display=swap');

@page{size:A4 portrait;margin:0}
*{box-sizing:border-box}

body{
    margin:0;
    background:#ddd;
    font-family:'Montserrat',sans-serif;
}

.page{
    width:210mm;
    height:297mm;
    margin:auto;
    position:relative;
    overflow:hidden;
    padding:8mm 9mm 6mm;
    background:url("<?= base_url('assets/menu-book/backgrounds/bg-cold.png'); ?>") center/cover no-repeat;
    color:#2b1711;
}

.frame{
    position:absolute;
    inset:5.5mm;
    border:1px solid rgba(106,63,35,.25);
    border-radius:6mm;
    pointer-events:none;
}

.header{
    position:relative;
    z-index:2;
    text-align:center;
    margin-bottom:5mm;
}

.header h1{
    margin:0;
    font-family:'Anton',sans-serif;
    font-size:30pt;
    line-height:.95;
    text-transform:uppercase;
    white-space:nowrap;
    color:#2b1711;
}

.header h1 span{color:#8b1e25}

.header p{
    margin:1.2mm 0 0;
    font-size:9pt;
    letter-spacing:1.2px;
    color:#6a4a35;
    font-weight:700;
}

.hero{
    position:relative;
    z-index:2;
    display:grid;
    grid-template-columns:1.25fr 1fr;
    gap:3mm;
    margin-bottom:4mm;
}

.hero-main{
    height:70mm;
    border-radius:9px;
    overflow:hidden;
    position:relative;
    box-shadow:0 7px 18px rgba(45,23,12,.18);
}

.hero-main img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

.hero-main::after{
    content:"RTD COLD BREW";
    position:absolute;
    left:0;
    bottom:0;
    width:100%;
    padding:16mm 5mm 3mm;
    background:linear-gradient(transparent,rgba(20,12,8,.86));
    color:#fff8e8;
    font-family:'Bebas Neue',sans-serif;
    font-size:25pt;
    letter-spacing:1px;
}

.hero-grid{
    height:70mm;
    display:grid;
    grid-template-columns:1fr 1fr;
    grid-template-rows:1fr 1fr 1fr;
    gap:2.5mm;
}

.mini-photo{
    position:relative;
    border-radius:8px;
    overflow:hidden;
    box-shadow:0 5px 12px rgba(45,23,12,.14);
    background:#eee;
}

.mini-photo img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

.mini-photo span{
    position:absolute;
    left:1.5mm;
    right:1.5mm;
    bottom:1.5mm;
    padding:.9mm 1mm;
    background:rgba(255,248,232,.92);
    color:#541f18;
    border-radius:4px;
    font-family:'Bebas Neue',sans-serif;
    font-size:8.7pt;
    text-align:center;
    line-height:1;
}

.section-title{
    position:relative;
    z-index:2;
    display:flex;
    align-items:center;
    gap:4mm;
    margin:2mm 0 2.2mm;
}

.section-title .badge{
    font-family:'Bebas Neue',sans-serif;
    font-size:19pt;
    line-height:1;
    letter-spacing:1px;
    color:#fff6e5;
    background:#8b1e25;
    padding:2.3mm 7mm 1.9mm;
    border-radius:7px;
}

.section-title.latte .badge{background:#4b3428}

.section-title .note{
    font-size:6.3pt;
    text-transform:uppercase;
    letter-spacing:2px;
    font-weight:900;
    color:#6d4a2c;
}

.menu-list{
    position:relative;
    z-index:2;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:2.1mm 5mm;
    background:rgba(255,249,237,.84);
    border:1px solid rgba(128,82,42,.22);
    border-radius:8px;
    padding:2.8mm 3mm;
    margin-bottom:3mm;
}

.item{
    border-bottom:1px dashed rgba(96,55,31,.28);
    padding-bottom:1mm;
}

.item-top{
    display:grid;
    grid-template-columns:1fr auto;
    gap:2mm;
    align-items:end;
}

.item-name{
    font-family:'Bebas Neue',sans-serif;
    font-size:12.3pt;
    line-height:.95;
    letter-spacing:.4px;
    text-transform:uppercase;
    color:#2b1711;
}

.item-price{
    font-family:'Bebas Neue',sans-serif;
    font-size:14pt;
    line-height:1;
    color:#8b1e25;
    white-space:nowrap;
}

.item-desc{
    margin-top:.5mm;
    font-size:5pt;
    line-height:1.15;
    color:#5b4031;
    font-weight:700;
    text-transform:uppercase;
}

.latte-highlight{
    position:relative;
    z-index:2;
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:3mm;
    margin-bottom:3mm;
}

.photo-card{
    background:#fff8ea;
    border:1px solid rgba(128,82,42,.24);
    border-radius:8px;
    overflow:hidden;
    box-shadow:0 5px 12px rgba(45,23,12,.13);
}

.photo-card img{
    width:100%;
    height:30mm;
    object-fit:cover;
    display:block;
}

.photo-card .cap{
    padding:1.4mm 2mm 1.1mm;
    text-align:center;
}

.photo-card .cap b{
    display:block;
    font-family:'Bebas Neue',sans-serif;
    font-size:11.5pt;
    line-height:.95;
    color:#2b1711;
}

.photo-card .cap span{
    display:block;
    margin-top:.5mm;
    font-family:'Bebas Neue',sans-serif;
    font-size:13pt;
    color:#8b1e25;
}

.latte-list{
    position:relative;
    z-index:2;
    display:grid;
    grid-template-columns:1fr 1fr 1fr;
    gap:2.2mm;
    padding-bottom:9mm;
}

.latte-item{
    background:rgba(255,249,237,.86);
    border:1px solid rgba(128,82,42,.22);
    border-radius:7px;
    padding:1.7mm 2mm;
    min-height:12mm;
}

.latte-item b{
    display:block;
    font-family:'Bebas Neue',sans-serif;
    font-size:11pt;
    line-height:.95;
    color:#2b1711;
}

.latte-item span{
    display:block;
    margin-top:.6mm;
    font-family:'Bebas Neue',sans-serif;
    font-size:12.5pt;
    color:#8b1e25;
    line-height:1;
}

.footer{
    position:absolute;
    left:0;
    right:0;
    bottom:3.2mm;
    z-index:3;
    display:flex;
    justify-content:center;
    align-items:center;
    gap:9mm;
    color:#3e3227;
}

.hashtag{
    font-family:'Parisienne',cursive;
    font-size:16pt;
    font-style:italic;
}

.ig{
    font-size:8.5pt;
    font-weight:900;
}

.ig::before{
    content:"◎";
    margin-right:1.3mm;
    font-size:12pt;
    vertical-align:-1px;
}

@media print{
    body{background:#fff}
    .page{margin:0}
}
</style>
</head>

<body>

<div class="page">
<div class="frame"></div>

<header class="header">
    <h1>Cold & Creamy <span>Creations</span></h1>
    <p>Cold brew elegance and espresso indulgence.</p>
</header>

<div class="hero">
    <div class="hero-main">
        <img src="<?= base_url('assets/menu-book/products/beverages/cold-creamy/rtd-cold-brew.png'); ?>">
    </div>

    <div class="hero-grid">
        <div class="mini-photo">
            <img src="<?= base_url('assets/menu-book/products/beverages/cold-creamy/golden-nut-elixir.png'); ?>">
            <span>Golden Nut Elixir</span>
        </div>
        <div class="mini-photo">
            <img src="<?= base_url('assets/menu-book/products/beverages/cold-creamy/lime-be-brew.png'); ?>">
            <span>Lime Be Brew</span>
        </div>
        <div class="mini-photo">
            <img src="<?= base_url('assets/menu-book/products/beverages/cold-creamy/orange-zest-cold-brew.png'); ?>">
            <span>Orange Zest</span>
        </div>
        <div class="mini-photo">
            <img src="<?= base_url('assets/menu-book/products/beverages/cold-creamy/spice-cloud-elixir.png'); ?>">
            <span>Spice Cloud</span>
        </div>
        <div class="mini-photo">
            <img src="<?= base_url('assets/menu-book/products/beverages/cold-creamy/sweet-dreams.png'); ?>">
            <span>Sweet Dreams</span>
        </div>
        <div class="mini-photo">
            <img src="<?= base_url('assets/menu-book/products/beverages/cold-creamy/mochacino.png'); ?>">
            <span>Mochacino</span>
        </div>
    </div>
</div>

<div class="section-title">
    <div class="badge">Cold Brew Series</div>
    <div class="note">Modern Café Drinks</div>
</div>

<div class="menu-list">
    <div class="item"><div class="item-top"><div class="item-name">Cold Brew</div><div class="item-price">20 K</div></div></div>
    <div class="item"><div class="item-top"><div class="item-name">RTD Cold Brew</div><div class="item-price">22 K</div></div></div>

    <div class="item">
        <div class="item-top"><div class="item-name">Golden Nut Elixir</div><div class="item-price">22 K</div></div>
        <div class="item-desc">Cold brew, hazelnut, lemon juice</div>
    </div>

    <div class="item">
        <div class="item-top"><div class="item-name">Lime Be Brew</div><div class="item-price">22 K</div></div>
        <div class="item-desc">Cold brew, honey, lime juice</div>
    </div>

    <div class="item">
        <div class="item-top"><div class="item-name">Orange Zest Cold Brew</div><div class="item-price">22 K</div></div>
        <div class="item-desc">Cold brew, orange juice, tonic water</div>
    </div>

    <div class="item">
        <div class="item-top"><div class="item-name">Spice Cloud Elixir</div><div class="item-price">22 K</div></div>
        <div class="item-desc">Cold brew, cinnamon, oat fusion milk distillation, sea salt hazelnut</div>
    </div>

    <div class="item">
        <div class="item-top"><div class="item-name">Sweet Dreams</div><div class="item-price">22 K</div></div>
        <div class="item-desc">Cold brew, sweet cream vanilla, caramel crumb</div>
    </div>

    <div class="item">
        <div class="item-top"><div class="item-name">Cold Brew Latte Ice</div><div class="item-price">20 K</div></div>
        <div class="item-desc">Cold brew coffee with milk</div>
    </div>
</div>

<div class="section-title latte">
    <div class="badge">Latte Series</div>
    <div class="note">Espresso Indulgence</div>
</div>

<div class="latte-highlight">
    <div class="photo-card">
        <img src="<?= base_url('assets/menu-book/products/beverages/cold-creamy/caramel-macchiato.png'); ?>">
        <div class="cap"><b>Caramel Macchiato</b><span>25 K</span></div>
    </div>

    <div class="photo-card">
        <img src="<?= base_url('assets/menu-book/products/beverages/cold-creamy/creme-brule.png'); ?>">
        <div class="cap"><b>Creme Brule</b><span>21 K</span></div>
    </div>

    <div class="photo-card">
        <img src="<?= base_url('assets/menu-book/products/beverages/cold-creamy/redpresso.png'); ?>">
        <div class="cap"><b>Redpresso</b><span>23 K</span></div>
    </div>
</div>

<div class="latte-list">
    <div class="latte-item"><b>Mochacino</b><span>Hot 21 K / Ice 22 K</span></div>
    <div class="latte-item"><b>Vanilla Latte</b><span>Hot 21 K / Ice 23 K</span></div>
    <div class="latte-item"><b>Caramel Latte</b><span>Hot 21 K / Ice 23 K</span></div>
    <div class="latte-item"><b>Matcha Coffee</b><span>24 K</span></div>
    <div class="latte-item"><b>Strawberry Latte</b><span>21 K</span></div>
    <div class="latte-item"><b>Rumbullion Rose</b><span>20 K</span></div>
</div>

<footer class="footer">
    <div class="hashtag">#kembalikenamua</div>
    <div class="ig">@namuacoffee</div>
</footer>

</div>

</body>
</html>