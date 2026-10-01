<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Extras & Enhancers - NAMUA</title>

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
    background:
        url("<?= base_url('assets/menu-book/backgrounds/bg-extras.png');?>") center/cover no-repeat,
        linear-gradient(135deg,#fff8eb,#efe1c9);
    color:#21100a;
}

.overlay{
    position:absolute;
    inset:0;
    background:rgba(255,255,255,.84);
}

.wrap{
    position:relative;
    z-index:2;
    padding:15mm 18mm;
}

.header{
    text-align:center;
    margin-bottom:14mm;
}

.kicker{
    font-size:7pt;
    letter-spacing:4px;
    font-weight:900;
    color:#0d8b8f;
}

h1{
    margin:2mm 0 1mm;
    font-family:Georgia,serif;
    font-size:36pt;
    line-height:.9;
    font-weight:900;
    letter-spacing:1px;
}

h1 span{
    color:#c89124;
}

.subtitle{
    font-family:Georgia,serif;
    font-style:italic;
    font-size:12pt;
    color:#6d4028;
}

.panel{
    width:125mm;
    margin:0 auto;
    background:rgba(255,255,255,.82);
    border:1.5px solid rgba(192,138,43,.65);
    border-radius:8mm;
    padding:10mm 11mm;
    box-shadow:0 8px 20px rgba(70,35,15,.08);
}

.panel-title{
    display:flex;
    align-items:center;
    gap:3mm;
    margin-bottom:7mm;
    font-family:Georgia,serif;
    font-size:16pt;
    font-weight:900;
    text-transform:uppercase;
}

.panel-title span{
    width:10mm;
    height:10mm;
    background:#0d8b8f;
    color:white;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    font-family:Arial,sans-serif;
    font-size:8pt;
}

.item{
    display:grid;
    grid-template-columns:1fr 18mm;
    gap:5mm;
    padding:2.7mm 0;
    border-bottom:1px dotted rgba(160,110,35,.55);
}

.name{
    font-size:11pt;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.2px;
}

.price{
    text-align:right;
    font-family:Georgia,serif;
    font-size:15pt;
    font-weight:900;
    color:#0d8b8f;
}

.footer{
    position:absolute;
    left:0;
    right:0;
    bottom:7mm;
    text-align:center;
    z-index:3;
    font-family:Georgia,serif;
    font-size:11.5pt;
}

.hashtag{
    font-style:italic;
    font-size:14pt;
    margin-right:8mm;
}

.ig{
    font-family:Arial,sans-serif;
    border:1.5px solid #21100a;
    border-radius:5px;
    padding:0 4px;
    margin-right:2mm;
    font-weight:bold;
}
</style>
</head>

<body>

<div class="page">
<div class="overlay"></div>

<div class="wrap">

    <div class="header">
        <div class="kicker">NAMUA BEVERAGES</div>
        <h1><span>EXTRAS</span><br>& ENHANCERS</h1>
        <div class="subtitle">Customize your perfect drink.</div>
    </div>

    <div class="panel">
        <div class="panel-title">
            Beverage Add-On
        </div>

        <div class="item">
            <div class="name">Add Full Arabica Espresso</div>
            <div class="price">12K</div>
        </div>

        <div class="item">
            <div class="name">Add Ice Cream Chocolate</div>
            <div class="price">9K</div>
        </div>

        <div class="item">
            <div class="name">Add Ice Cream Vanilla</div>
            <div class="price">9K</div>
        </div>

        <div class="item">
            <div class="name">Extra Espresso Arabika</div>
            <div class="price">8K</div>
        </div>

        <div class="item">
            <div class="name">Extra Espresso Houseblend</div>
            <div class="price">6K</div>
        </div>

        <div class="item">
            <div class="name">Milk Life</div>
            <div class="price">20K</div>
        </div>

        <div class="item">
            <div class="name">Mineral Water</div>
            <div class="price">4K</div>
        </div>

        <div class="item">
            <div class="name">Oatside Upgrade(Additional Oatmilk)</div>
            <div class="price">7K</div>
        </div>

        <div class="item">
            <div class="name">RTD Oatside (OATMILK)</div>
            <div class="price">10K</div>
        </div>
    </div>

</div>

<div class="footer">
    <span class="hashtag">#kembalikenamua</span>
    <span style="margin-right:8mm;">|</span>
    <span class="ig">◎</span>
    @namuacoffee
</div>

</div>

</body>
</html>