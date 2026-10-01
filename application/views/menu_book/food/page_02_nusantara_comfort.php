<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Nusantara & Comfort Food</title>

<style>
@import url('https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Montserrat:wght@600;700;800;900&family=Parisienne&display=swap');

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
    background:url("<?= base_url('assets/menu-book/backgrounds/bg-nusantara-comfort.png'); ?>") center/cover no-repeat;
    color:#2b160b;
}

.frame{
    position:absolute;
    inset:5.5mm;
    border:1px solid rgba(126,77,35,.34);
    border-radius:7mm;
    pointer-events:none;
    z-index:1;
}

.corner-art{
    position:absolute;
    right:6mm;
    bottom:14mm;
    width:48mm;
    height:42mm;
    z-index:1;
    opacity:.88;
    pointer-events:none;
}

.corner-art::before{
    content:"";
    position:absolute;
    right:0;
    bottom:0;
    width:43mm;
    height:24mm;
    border-radius:50% 45% 35% 45%;
    background:
        radial-gradient(circle at 70% 45%, rgba(115,55,20,.22) 0 16%, transparent 17%),
        radial-gradient(circle at 52% 65%, rgba(90,43,18,.20) 0 14%, transparent 15%),
        linear-gradient(135deg, rgba(120,76,37,.35), rgba(236,213,169,.18));
    transform:rotate(-8deg);
}

.corner-art::after{
    content:"";
    position:absolute;
    right:4mm;
    bottom:5mm;
    width:34mm;
    height:24mm;
    background:
        radial-gradient(ellipse at 15% 60%, #7b1d1d 0 9%, transparent 10%),
        radial-gradient(ellipse at 32% 65%, #9c2a21 0 8%, transparent 9%),
        radial-gradient(ellipse at 49% 62%, #7b1d1d 0 9%, transparent 10%),
        radial-gradient(ellipse at 68% 58%, #9c2a21 0 8%, transparent 9%),
        radial-gradient(ellipse at 84% 52%, #7b1d1d 0 9%, transparent 10%),
        linear-gradient(70deg, transparent 0 55%, rgba(47,92,42,.9) 56% 62%, transparent 63%),
        linear-gradient(115deg, transparent 0 54%, rgba(47,92,42,.85) 55% 61%, transparent 62%);
    filter:drop-shadow(0 4px 4px rgba(70,30,10,.22));
}

.header{
    position:relative;
    z-index:3;
    text-align:center;
    margin-bottom:2mm;
}

.header h1{
    margin:0;
    font-family:'Anton',sans-serif;
    font-size:33pt;
    line-height:.95;
    letter-spacing:.6px;
    text-transform:uppercase;
    white-space:nowrap;
    color:#2a1207;
}

.header h1 span{color:#982126}

.header p{
    margin:1.4mm 0 0;
    font-family:'Parisienne',cursive;
    font-size:17pt;
    color:#7a4a26;
}

.spark{
    color:#c28a2a;
    font-size:16pt;
    padding:0 7mm;
}

.section{
    position:relative;
    z-index:3;
    margin-bottom:2mm;
}

.section-head{
    display:flex;
    align-items:center;
    gap:6mm;
    margin-bottom:1mm;
}

.badge{
    font-family:'Bebas Neue',sans-serif;
    color:#fff7df;
    font-size:22pt;
    line-height:1;
    letter-spacing:1px;
    padding:3mm 8mm 2.3mm;
    border-radius:7px;
    background:#9b2026;
    box-shadow:0 4px 9px rgba(61,27,9,.16);
}

.crave .badge{background:#526b3e}

.note{
    font-size:7pt;
    font-weight:900;
    letter-spacing:2.2px;
    color:#54341d;
    text-transform:uppercase;
}

.note::after{
    content:"";
    display:inline-block;
    width:23mm;
    border-top:1px dotted rgba(85,55,30,.45);
    margin-left:4mm;
    vertical-align:middle;
}

.grid{
    display:grid;
    gap:3mm;
}

.heritage{
    grid-template-columns:repeat(3,1fr);
}

.crave-grid{
    grid-template-columns:repeat(4,1fr);
}

.card{
    background:#fff8eb;
    border:1px solid rgba(123,76,35,.28);
    border-radius:7px;
    overflow:hidden;
    box-shadow:0 4px 10px rgba(77,40,16,.13);
}

.card img{
    width:100%;
    height:38mm;
    object-fit:cover;
    display:block;
}

.crave-grid .card img{
    height:23mm;
}

.info{
    text-align:center;
    padding:1.8mm 2mm 1.5mm;
    background:#fff9ee;
}

.name{
    font-family:'Bebas Neue',sans-serif;
    font-size:13pt;
    line-height:.9;
    letter-spacing:.35px;
    text-transform:uppercase;
    min-height:7mm;
    display:flex;
    align-items:center;
    justify-content:center;
}

.crave-grid .name{
    font-size:9.5pt;
    min-height:7mm;
}

.price-row{
    display:grid;
    grid-template-columns:1fr 1fr;
    margin-top:1mm;
    padding-top:.8mm;
    border-top:1px solid rgba(123,76,35,.28);
}

.price-row.single{
    display:block;
}

.price-box + .price-box{
    border-left:1px solid rgba(123,76,35,.28);
}

.price-label{
    font-size:4pt;
    font-weight:900;
    text-transform:uppercase;
    color:#4b2b16;
    line-height:1;
}

.price{
    font-family:'Bebas Neue',sans-serif;
    font-size:14.5pt;
    line-height:.95;
    color:#982126;
}

.upgrade{
    position:relative;
    z-index:3;
    margin-top:3mm;
    width:150mm;
    display:grid;
    grid-template-columns:32mm repeat(4,1fr);
    border:1px solid rgba(130,61,28,.42);
    border-radius:8px;
    overflow:hidden;
    background:#fff7e8;
    box-shadow:0 4px 10px rgba(77,40,16,.1);
}

.upgrade-title{
    background:#9b2026;
    color:#fff7df;
    font-family:'Bebas Neue',sans-serif;
    font-size:20pt;
    line-height:.85;
    letter-spacing:1px;
    display:flex;
    align-items:center;
    justify-content:center;
    text-align:center;
    padding:2.3mm;
}

.upgrade-item{
    text-align:center;
    padding:2.3mm 1mm 1.8mm;
    border-left:1px dashed rgba(130,61,28,.45);
}

.upgrade-item b{
    display:block;
    font-size:5.8pt;
    text-transform:uppercase;
    color:#4b2b16;
    margin-bottom:.6mm;
}

.upgrade-item span{
    font-family:'Bebas Neue',sans-serif;
    font-size:17pt;
    line-height:1;
    color:#982126;
}

.footer{
    position:absolute;
    left:0;
    right:0;
    bottom:3.2mm;
    z-index:4;
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
<div class="corner-art"></div>

<header class="header">
    <h1>Nusantara & <span>Comfort Food</span></h1>
    <p><span class="spark">✦</span>Rasa Asli, Warisan Nusantara<span class="spark">✦</span></p>
</header>

<section class="section">
    <div class="section-head">
        <div class="badge">Indonesian Heritage</div>
        <div class="note">Authentic Local Classics</div>
    </div>

    <div class="grid heritage">
        <div class="card">
            <img src="<?= base_url('assets/menu-book/products/foods/indonesian-heritage/sup-iga-rempah.png'); ?>">
            <div class="info">
                <div class="name">Sup Iga Rempah</div>
                <div class="price-row">
                    <div class="price-box"><div class="price-label">Ala Carte</div><div class="price">46 K</div></div>
                    <div class="price-box"><div class="price-label">Rice Set</div><div class="price">48 K</div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <img src="<?= base_url('assets/menu-book/products/foods/indonesian-heritage/iga-bakar-madu.png'); ?>">
            <div class="info">
                <div class="name">Iga Bakar Madu</div>
                <div class="price-row">
                    <div class="price-box"><div class="price-label">Ala Carte</div><div class="price">50 K</div></div>
                    <div class="price-box"><div class="price-label">Rice Set</div><div class="price">52 K</div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <img src="<?= base_url('assets/menu-book/products/foods/indonesian-heritage/mie-aceh-beef-shortplate.png'); ?>">
            <div class="info">
                <div class="name">Mie Aceh Beef Shortplate</div>
                <div class="price-row single"><div class="price-box"><div class="price">43 K</div></div></div>
            </div>
        </div>

        <div class="card">
            <img src="<?= base_url('assets/menu-book/products/foods/indonesian-heritage/soto-betawi.png'); ?>">
            <div class="info">
                <div class="name">Soto Betawi</div>
                <div class="price-row">
                    <div class="price-box"><div class="price-label">Ala Carte</div><div class="price">48 K</div></div>
                    <div class="price-box"><div class="price-label">Rice Set</div><div class="price">50 K</div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <img src="<?= base_url('assets/menu-book/products/foods/indonesian-heritage/nasi-campur-bali.png'); ?>">
            <div class="info">
                <div class="name">Nasi Campur Bali</div>
                <div class="price-row single"><div class="price-box"><div class="price">29 K</div></div></div>
            </div>
        </div>

        <div class="card">
            <img src="<?= base_url('assets/menu-book/products/foods/indonesian-heritage/nasi-goreng-kampoeng.png'); ?>">
            <div class="info">
                <div class="name">Nasi Goreng Kampoeng</div>
                <div class="price-row single"><div class="price-box"><div class="price">23 K</div></div></div>
            </div>
        </div>
    </div>
</section>

<section class="section crave">
    <div class="section-head">
        <div class="badge">Crave Corner</div>
        <div class="note">Favorit yang Bikin Ketagihan</div>
    </div>

    <div class="grid crave-grid">
        <div class="card">
            <img src="<?= base_url('assets/menu-book/products/foods/crave-corner/bebek-goreng-bumbu-rempah.png'); ?>">
            <div class="info">
                <div class="name">Bebek Goreng Bumbu Rempah</div>
                <div class="price-row">
                    <div class="price-box"><div class="price-label">Ala Carte</div><div class="price">41 K</div></div>
                    <div class="price-box"><div class="price-label">Rice Set</div><div class="price">43 K</div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <img src="<?= base_url('assets/menu-book/products/foods/crave-corner/beef-lombok-ijo.png'); ?>">
            <div class="info">
                <div class="name">Beef Lombok Ijo</div>
                <div class="price-row single"><div class="price-box"><div class="price">32 K</div></div></div>
            </div>
        </div>

        <div class="card">
            <img src="<?= base_url('assets/menu-book/products/foods/crave-corner/grill-beef-shortplate-parape.png'); ?>">
            <div class="info">
                <div class="name">Grill Beef Shortplate Parape</div>
                <div class="price-row single"><div class="price-box"><div class="price">31 K</div></div></div>
            </div>
        </div>

        <div class="card">
            <img src="<?= base_url('assets/menu-book/products/foods/crave-corner/grill-seafood-platter-pantura.png'); ?>">
            <div class="info">
                <div class="name">Grill Seafood Platter Pantura</div>
                <div class="price-row single"><div class="price-box"><div class="price">31 K</div></div></div>
            </div>
        </div>

        <div class="card">
            <img src="<?= base_url('assets/menu-book/products/foods/crave-corner/ayam-bumbu-ireng.png'); ?>">
            <div class="info">
                <div class="name">Ayam Bumbu Ireng</div>
                <div class="price-row">
                    <div class="price-box"><div class="price-label">Ala Carte</div><div class="price">31 K</div></div>
                    <div class="price-box"><div class="price-label">Rice Set</div><div class="price">33 K</div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <img src="<?= base_url('assets/menu-book/products/foods/crave-corner/ayam-bakar-sambal-dabu-dabu.png'); ?>">
            <div class="info">
                <div class="name">Ayam Bakar Sambal Dabu-Dabu</div>
                <div class="price-row">
                    <div class="price-box"><div class="price-label">Ala Carte</div><div class="price">26 K</div></div>
                    <div class="price-box"><div class="price-label">Rice Set</div><div class="price">28 K</div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <img src="<?= base_url('assets/menu-book/products/foods/crave-corner/ayam-goreng-rempah-kremes.png'); ?>">
            <div class="info">
                <div class="name">Ayam Goreng Rempah Kremes</div>
                <div class="price-row">
                    <div class="price-box"><div class="price-label">Ala Carte</div><div class="price">23 K</div></div>
                    <div class="price-box"><div class="price-label">Rice Set</div><div class="price">25 K</div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="upgrade">
    <div class="upgrade-title">Upgrade<br>Rice</div>
    <div class="upgrade-item"><b>Base Gede Rice</b><span>+5K</span></div>
    <div class="upgrade-item"><b>Garlic Kemangi</b><span>+3K</span></div>
    <div class="upgrade-item"><b>Yakimezhi Rice</b><span>+10K</span></div>
    <div class="upgrade-item"><b>Arabian Rice</b><span>+9K</span></div>
</div>

<footer class="footer">
    <div class="hashtag">#kembalikenamua</div>
    <div class="ig">@namuacoffee</div>
</footer>

</div>

</body>
</html>