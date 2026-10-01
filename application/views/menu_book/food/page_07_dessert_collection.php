<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dessert Collection - NAMUA</title>
<style>
@page { size:A4 portrait; margin:0; }
*{box-sizing:border-box}

body{
    margin:0;
    background:#d8d8d8;
}

.page{
    width:210mm;
    height:297mm;
    margin:auto;
    position:relative;
    overflow:hidden;
    background:url("<?= base_url('assets/menu-book/backgrounds/bg-dessert.png'); ?>") center/cover no-repeat;
    font-family:Arial, sans-serif;
    color:#2b120b;
}

.wrap{
    height:100%;
    padding:14mm 12mm 9mm;
    position:relative;
}

.header{
    display:grid;
    grid-template-columns:42% 58%;
    gap:7mm;
    align-items:center;
    margin-bottom:7mm;
}

.title-main{
    font-family:Georgia, serif;
    font-size:43pt;
    line-height:.82;
    font-weight:900;
    letter-spacing:-2px;
}

.title-sub{
    font-family:Georgia, serif;
    font-size:40pt;
    line-height:.82;
    font-style:italic;
    color:#ad8540;
    margin-top:1mm;
}

.tagline{
    margin-top:7mm;
    font-size:9pt;
    letter-spacing:6px;
    font-weight:900;
}

.hero{
    position:relative;
    background:#fff8ea;
    padding:3mm;
    border-radius:2mm;
    box-shadow:0 9px 24px rgba(54,29,12,.18);
    transform:rotate(.7deg);
}

.hero::before{
    content:"";
    position:absolute;
    top:-3mm;
    left:50%;
    width:30mm;
    height:7mm;
    transform:translateX(-50%) rotate(-2deg);
    background:rgba(168,126,70,.42);
}

.img{
    width:100%;
    object-fit:cover;
    display:block;
    border-radius:1.5mm;
}

.hero .img{
    height:50mm;
}

.menu-info{
    position:relative;
    display:grid;
    grid-template-columns:1fr auto;
    gap:3mm;
    background:#fff3df;
    margin:-1mm 3mm 0;
    padding:4mm 5mm;
    border-radius:0 0 2mm 2mm;
}

.number{
    position:absolute;
    top:-13mm;
    left:-5mm;
    width:16mm;
    height:16mm;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(145deg,#c79943,#9f7431);
    color:#fffaf0;
    font-family:Georgia, serif;
    font-size:18pt;
    font-weight:900;
    border:1.5mm solid #fff4df;
    box-shadow:0 4px 12px rgba(40,18,8,.22);
}

.name{
    font-size:13pt;
    line-height:1.02;
    font-weight:950;
}

.desc{
    margin-top:2mm;
    font-size:8.4pt;
    line-height:1.3;
    text-transform:uppercase;
}

.price{
    font-family:Georgia, serif;
    font-size:21pt;
    font-weight:900;
    white-space:nowrap;
}

.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:6.5mm 7mm;
}

.card{
    position:relative;
    background:#fff8ea;
    padding:3mm;
    border-radius:4mm;
    box-shadow:0 9px 22px rgba(54,29,12,.15);
    min-height:74mm;
}

.card:nth-child(1){transform:rotate(-.6deg)}
.card:nth-child(2){transform:rotate(.5deg)}
.card:nth-child(3){transform:rotate(.55deg)}
.card:nth-child(4){transform:rotate(-.55deg)}

.card::before{
    content:"♡";
    position:absolute;
    top:6mm;
    right:7mm;
    color:#bd9148;
    font-size:22pt;
    font-family:Georgia, serif;
    opacity:.7;
    z-index:3;
}

.card .img{
    height:45mm;
}

.card .menu-info{
    margin:-1mm 1mm 0;
    padding:4mm 5mm 4.5mm;
}

.card .number{
    top:-12mm;
    left:-4mm;
    width:15mm;
    height:15mm;
    font-size:16pt;
}

.card .name{
    font-size:12pt;
}

.card .desc{
    font-size:8pt;
}

.card .price{
    font-size:19pt;
}

.footer{
    position:absolute;
    left:0;
    right:0;
    bottom:7mm;
    text-align:center;
    font-family:Georgia, serif;
    color:#2b120b;
}

.footer-bg{
    display:inline-block;
    min-width:65mm;
    padding:2mm 7mm 3mm;
    background:rgba(190,147,78,.22);
    border-radius:50%;
}

.hashtag{
    font-size:14pt;
    font-style:italic;
}

.ig{
    margin-top:1mm;
    font-size:13pt;
    font-style:italic;
}

.ig-icon{
    display:inline-block;
    width:14px;
    height:14px;
    border:2px solid #2b120b;
    border-radius:4px;
    position:relative;
    top:2px;
    margin-right:5px;
}

.ig-icon::before{
    content:"";
    position:absolute;
    width:5px;
    height:5px;
    border:1.8px solid #2b120b;
    border-radius:50%;
    left:2.5px;
    top:2.5px;
}

.ig-icon::after{
    content:"";
    position:absolute;
    width:2px;
    height:2px;
    background:#2b120b;
    border-radius:50%;
    right:2px;
    top:2px;
}

@media print{
    body{background:#fff}
    .page{margin:0}
}
</style>
</head>

<body>
<div class="page">
<div class="wrap">

    <div class="header">
        <div>
            <div class="title-main">DESSERT</div>
            <div class="title-sub">Collection</div>
            <div class="tagline">SWEET MOMENTS</div>
        </div>

        <div class="hero">
            <img class="img" src="<?= base_url('assets/menu-book/products/foods/dessert/choco-banana-crepes.png'); ?>">
            <div class="menu-info">
                <div class="number">01</div>
                <div>
                    <div class="name">CHOCO BANANA CREPES</div>
                    <div class="desc">Roll crepes fill banana crush inside, ting-ting crumble & ice cream</div>
                </div>
                <div class="price">22 K</div>
            </div>
        </div>
    </div>

    <div class="grid">

        <div class="card">
            <img class="img" src="<?= base_url('assets/menu-book/products/foods/dessert/choco-sweet-bread.png'); ?>">
            <div class="menu-info">
                <div class="number">02</div>
                <div>
                    <div class="name">CHOCO SWEET BREAD</div>
                    <div class="desc">Bread coated Nutella, sweet cream, choco ball & ice cream</div>
                </div>
                <div class="price">26 K</div>
            </div>
        </div>

        <div class="card">
            <img class="img" src="<?= base_url('assets/menu-book/products/foods/dessert/namua-signature-sweet-bread.png'); ?>">
            <div class="menu-info">
                <div class="number">03</div>
                <div>
                    <div class="name">NAMUA SIGNATURE SWEET BREAD</div>
                    <div class="desc">Bread toast, sweet cream redvelvet, Oreo crumble, biscuit & ice cream</div>
                </div>
                <div class="price">28 K</div>
            </div>
        </div>

        <div class="card">
            <img class="img" src="<?= base_url('assets/menu-book/products/foods/dessert/vellova-mille.png'); ?>">
            <div class="menu-info">
                <div class="number">04</div>
                <div>
                    <div class="name">VELLOVA MILLE</div>
                    <div class="desc">Crepes red velvet with wippy cream & ice cream</div>
                </div>
                <div class="price">27 K</div>
            </div>
        </div>

        <div class="card">
            <img class="img" src="<?= base_url('assets/menu-book/products/foods/dessert/choco-mille.png'); ?>">
            <div class="menu-info">
                <div class="number">05</div>
                <div>
                    <div class="name">CHOCO MILLE</div>
                    <div class="desc">Crepes chocolate with wippy cream & ice cream</div>
                </div>
                <div class="price">25 K</div>
            </div>
        </div>

    </div>

    <div class="footer">
        <div class="footer-bg">
            <div class="hashtag">#kembalikenamua</div>
            <div class="ig"><span class="ig-icon"></span>@namuacoffee</div>
        </div>
    </div>

</div>
</div>
</body>
</html>