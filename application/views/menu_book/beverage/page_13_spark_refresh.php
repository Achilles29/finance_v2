<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>SPARK & REFRESH</title>

<style>
@page{size:A4 portrait;margin:0}

*{box-sizing:border-box}

body{
    margin:0;
    background:#ddd;
    font-family:Arial,sans-serif;
}

.page{
    width:210mm;
    height:297mm;
    margin:auto;
    position:relative;
    overflow:hidden;

    background:url("<?= base_url('assets/menu-book/backgrounds/bg-spark.png');?>")
    center/cover no-repeat;
}

.overlay{
    position:absolute;
    inset:0;
    background:
    linear-gradient(
        rgba(255,255,255,.82),
        rgba(255,255,255,.90)
    );
}

.wrap{
    position:relative;
    z-index:2;
    padding:8mm;
}

/* HEADER */

.hero{
    text-align:center;
    margin-bottom:5mm;
}

.kicker{
    color:#ff6f61;
    font-size:8pt;
    font-weight:900;
    letter-spacing:4px;
    text-transform:uppercase;
}

.hero h1{
    margin:1mm 0;
    font-size:34pt;
    font-weight:900;
    line-height:.9;
    color:#0d8b8f;
}

.hero h1 span{
    color:#ff6f61;
}

.subtitle{
    font-family:Georgia,serif;
    font-style:italic;
    font-size:11pt;
    color:#5d5d5d;
}

.tagline{
    margin-top:1mm;
    color:#f2a800;
    font-weight:700;
    font-size:9pt;
}

/* SECTION */

.section-title{
    display:flex;
    align-items:center;
    gap:3mm;
    margin-bottom:3mm;
}

.section-title span{
    background:#0d8b8f;
    color:white;
    padding:2mm 4mm;
    border-radius:999px;
    font-weight:900;
    font-size:8pt;
}

.section-title h2{
    margin:0;
    color:#0d8b8f;
    font-size:18pt;
    font-family:Georgia,serif;
}

.line{
    flex:1;
    border-top:2px dashed #ffbe55;
}

/* MOCKTAIL GRID */

.mocktail-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:3mm;
    margin-bottom:5mm;
}

.card{
    background:white;
    border-radius:5mm;
    overflow:hidden;
    box-shadow:0 5px 15px rgba(0,0,0,.08);
}

.card img{
    width:100%;
    height:48mm;
    object-fit:cover;
}

.card-body{
    padding:3mm;
}

.card-title{
    font-weight:900;
    font-size:9pt;
    color:#222;
}

.card-price{
    color:#ff6f61;
    font-size:14pt;
    font-weight:900;
    margin:1mm 0;
}

.card-desc{
    font-size:6pt;
    line-height:1.25;
    color:#666;
}

/* LOWER AREA */

/* LOWER AREA - FIXED */

.bottom{
    display:grid;
    grid-template-columns:55% 45%;
    gap:4mm;
    align-items:start;
}

.menu-list{
    background:rgba(255,255,255,.82);
    border-radius:6mm;
    padding:4mm;
}

.menu-row{
    display:grid;
    grid-template-columns:1fr 16mm;
    gap:3mm;
    padding:1.35mm 0;
    border-bottom:1px dotted #ccc;
}


.name{
    font-weight:900;
    font-size:8pt;
}

.desc{
    font-size:6pt;
    color:#666;
    margin-top:.5mm;
}

.price{
    text-align:right;
    font-weight:900;
    font-size:13pt;
    color:#0d8b8f;
}

/* RIGHT GALLERY */

.gallery{
    height:118mm;
    display:grid;
    grid-template-rows:58mm 58mm;
    gap:3mm;
}


.gallery img{
    width:100%;
    height:100%;
    object-fit:cover;
    border-radius:6mm;
    box-shadow:0 5px 10px rgba(0,0,0,.1);
}


/* FOOTER */

.footer{
    position:absolute;
    left:0;
    right:0;
    bottom:4mm;
    text-align:center;
    z-index:3;
    font-family:Georgia,serif;
}

.footer .hash{
    font-style:italic;
    font-size:14pt;
}

.footer .ig{
    margin-left:8mm;
}

</style>
</head>

<body>

<div class="page">

<div class="overlay"></div>

<div class="wrap">

<div class="hero">
    <div class="kicker">NAMUA BEVERAGES</div>
    <h1>SPARK & <span>REFRESH</span></h1>
    <div class="subtitle">Mocktails, fizz and refreshing creations.</div>
    <div class="tagline">Colorful • Fresh • Summer Vibes</div>
</div>

<div class="section-title">
    <span>01</span>
    <h2>Mocktail Highlights</h2>
    <div class="line"></div>
</div>

<div class="mocktail-grid">

<div class="card">
<img src="<?= base_url('assets/menu-book/products/beverages/mocktail/black-and-yellow.png');?>">
<div class="card-body">
<div class="card-title">BLACK AND YELLOW</div>
<div class="card-price">21 K</div>
<div class="card-desc">
ORANGE SYRUP, SPARKLING SODA, ESPRESSO
</div>
</div>
</div>

<div class="card">
<img src="<?= base_url('assets/menu-book/products/beverages/mocktail/koohi.png');?>">
<div class="card-body">
<div class="card-title">KOOHI</div>
<div class="card-price">20 K</div>
<div class="card-desc">
ESPRESSO, KAWISTA SYRUP, SPARKLING SODA
</div>
</div>
</div>

<div class="card">
<img src="<?= base_url('assets/menu-book/products/beverages/mocktail/old-yellow-brick.png');?>">
<div class="card-body">
<div class="card-title">OLD YELLOW BRICK</div>
<div class="card-price">20 K</div>
<div class="card-desc">
PINEAPPLE BLEND, SPARKLING SODA, CREAM
</div>
</div>
</div>

<div class="card">
<img src="<?= base_url('assets/menu-book/products/beverages/mocktail/pink-lady.png');?>">
<div class="card-body">
<div class="card-title">PINK LADY</div>
<div class="card-price">21 K</div>
<div class="card-desc">
WATERMELON BLEND, WATER, ESPRESSO
</div>
</div>
</div>

</div>

<div class="bottom">

<div class="menu-list">

<div class="section-title" style="margin-top:0">
<span>02</span>
<h2>Refreshing Drinks</h2>
</div>

<div class="menu-row">
<div>
<div class="name">SHOCK PRESSO</div>
<div class="desc">DOUBLE ESPRESSO, LIME JUICE, VANILLA</div>
</div>
<div class="price">20 K</div>
</div>

<div class="menu-row">
<div>
<div class="name">CLASSIC MOJITOS</div>
<div class="desc">EXTRACT BROWN SUGAR, LYCHEE FLAVOUR, LIME JUICE WITH SPARKLING SODA</div>
</div>
<div class="price">18 K</div>
</div>

<div class="menu-row">
<div>
<div class="name">LIME MINT HONEY</div>
<div class="desc">LIME, MINT FLAVOUR, HONEY</div>
</div>
<div class="price">20 K</div>
</div>

<div class="menu-row">
<div>
<div class="name">PERFECT SENSE</div>
<div class="desc">WATERMELON, LYCHEE, LIME JUICE, BLUE PEA TEA, MOJITO</div>
</div>
<div class="price">22 K</div>
</div>

<div class="menu-row">
<div>
<div class="name">SPARKLING LYCHEE</div>
<div class="desc">LYCHEE FLAVOUR, SPARKLING SODA & LYCHEE FRUIT</div>
</div>
<div class="price">18 K</div>
</div>

<div class="menu-row">
<div>
<div class="name">SPARKLING STRAWBERRY</div>
<div class="desc">SPARKLING WATER, SODA, STRAWBERRY FLAVOUR</div>
</div>
<div class="price">18 K</div>
</div>

<div class="menu-row">
<div>
<div class="name">VIRGIN MOJITOS</div>
<div class="desc">SPARKLING WATER, SODA, LIME WEDGES</div>
</div>
<div class="price">18 K</div>
</div>

</div>

<div class="gallery">
<img src="<?= base_url('assets/menu-book/products/beverages/mocktail/lime-mint-honey.png');?>">
<img src="<?= base_url('assets/menu-book/products/beverages/mocktail/sparkling-strawberry.png');?>">
</div>

</div>

</div>

<div class="footer">
<span class="hash">#kembalikenamua</span>
<span style="margin:0 8mm;">|</span>
<span class="ig">◎ @namuacoffee</span>
</div>

</div>

</body>
</html>