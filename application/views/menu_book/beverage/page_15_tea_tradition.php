<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Tea & Tradition - NAMUA</title>

<style>
@page{size:A4 portrait;margin:0}
*{box-sizing:border-box}
body{margin:0;background:#ddd}

.page{
    width:210mm;
    height:297mm;
    margin:auto;
    position:relative;
    overflow:hidden;
    background:url("<?= base_url('assets/menu-book/backgrounds/bg-tea.png');?>") center/cover no-repeat;
    font-family:Arial,sans-serif;
    color:#1f1a12;
}

.page::before{
    content:"";
    position:absolute;
    inset:0;
    background:rgba(245,242,232,.78);
    backdrop-filter:grayscale(1);
    filter:grayscale(1);
}

.wrap{
    position:relative;
    z-index:2;
    padding:10mm 10mm 13mm;
}

.header{
    text-align:center;
    margin-bottom:8mm;
}

.kicker{
    font-size:7pt;
    letter-spacing:4px;
    color:#a87b25;
    font-weight:900;
}

h1{
    margin:2mm 0 1mm;
    font-family:Georgia,serif;
    font-size:37pt;
    line-height:.9;
    font-weight:900;
    letter-spacing:1px;
}

h1 .green{color:#234323}
h1 .brown{color:#35180f}
h1 .gold{color:#a87b25}

.subtitle{
    font-family:Georgia,serif;
    font-style:italic;
    font-size:12pt;
    color:#234323;
}

.ornament{
    margin:4mm auto 0;
    width:52mm;
    border-top:1px solid #a87b25;
}

.main{
    display:grid;
    grid-template-columns:48% 52%;
    gap:4mm;
    margin-bottom:5mm;
}

.panel{
    background:rgba(255,255,255,.72);
    border:1.2px solid rgba(168,123,37,.65);
    border-radius:6mm;
    padding:6mm;
}

.section-title{
    display:inline-flex;
    align-items:center;
    gap:3mm;
    background:#284b27;
    color:#fff;
    border:1px solid #a87b25;
    border-radius:99px;
    padding:2mm 6mm;
    margin-bottom:4mm;
    font-family:Georgia,serif;
    font-size:16pt;
    font-weight:900;
    text-transform:uppercase;
}

.section-title span{
    font-family:Arial,sans-serif;
    font-size:8pt;
    background:rgba(255,255,255,.18);
    width:8mm;
    height:8mm;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
}

.tea-row{
    display:grid;
    grid-template-columns:1fr 14mm 14mm;
    gap:3mm;
    padding:2.1mm 0;
    border-bottom:1px dotted rgba(60,45,25,.45);
}

.tea-head{
    color:#8a6420;
    font-size:7pt;
    font-weight:900;
    text-align:center;
    text-transform:uppercase;
}

.name{
    font-size:9.3pt;
    font-weight:900;
    text-transform:uppercase;
}

.desc{
    font-size:6.3pt;
    line-height:1.25;
    margin-top:.7mm;
    text-transform:uppercase;
    color:#453629;
}

.price{
    font-family:Georgia,serif;
    font-size:13pt;
    font-weight:900;
    text-align:center;
    align-self:center;
    color:#284b27;
}

.hot{color:#8a5423}

.gallery{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:3mm;
}

.photo{
    height:55mm;
    border-radius:4mm;
    overflow:hidden;
    border:1.2px solid #a87b25;
    background:white;
    position:relative;
}

.photo.wide{
    grid-column:span 2;
    height:50mm;
}

.photo img{
    width:100%;
    height:80%;
    object-fit:cover;
}

.caption{
    position:absolute;
    left:0;
    right:0;
    bottom:0;
    background:rgba(40,75,39,.92);
    color:#fff;
    text-align:center;
    padding:2mm;
    font-family:Georgia,serif;
    font-size:11pt;
    font-weight:900;
    text-transform:uppercase;
}

.wedang{
    height:78mm;
    display:grid;
    grid-template-columns:52% 48%;
    gap:4mm;
    background:rgba(255,255,255,.72);
    border:1.2px solid rgba(168,123,37,.65);
    border-radius:6mm;
    padding:5mm 6mm;
    overflow:hidden;
}

.wedang-list .menu-row{
    display:grid;
    grid-template-columns:1fr 16mm;
    gap:4mm;
    padding:2mm 0;
    border-bottom:1px dotted rgba(60,45,25,.45);
}

.wedang-img{
    border-radius:4mm;
    overflow:hidden;
    background:linear-gradient(
        135deg,
        rgba(40,75,39,.15),
        rgba(168,123,37,.15)
    );
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
}

/* Atur ukuran gambar di sini */
.wedang-img img{
    width:auto;
    height:100%;
    object-fit:contain;
    transform:translateX(-85px);
}

/* Optional label */
.wedang-label{
    position:absolute;
    top:3mm;
    right:3mm;
    background:rgba(40,75,39,.85);
    color:white;
    padding:1mm 3mm;
    border-radius:99px;
    font-size:7pt;
    font-weight:700;
    letter-spacing:1px;
}

.wedang-img::before{
    content:"WEDANG";
    font-family:Georgia,serif;
    font-size:26pt;
    color:rgba(40,75,39,.16);
    font-weight:900;
    letter-spacing:2px;
}

.footer{
    position:absolute;
    left:0;
    right:0;
    bottom:4mm;
    text-align:center;
    z-index:4;
    font-family:Georgia,serif;
    color:#21100a;
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
<div class="wrap">

    <div class="header">
        <div class="kicker">NAMUA BEVERAGES</div>
        <h1><span class="green">TEA</span> <span class="gold">&</span><br><span class="brown">TRADITION</span></h1>
        <div class="subtitle">From modern tea refreshers to Indonesian warmth.</div>
        <div class="ornament"></div>
    </div>

    <div class="main">

        <div class="panel">
            <div class="section-title"><span>01</span> Tea Series</div>

            <div class="tea-row">
                <div></div>
                <div class="tea-head">Hot</div>
                <div class="tea-head">Ice</div>
            </div>

            <div class="tea-row">
                <div>
                    <div class="name">Black Tea</div>
                    <div class="desc">Based tea with sugar</div>
                </div>
                <div class="price hot">9K</div>
                <div class="price">10K</div>
            </div>

            <div class="tea-row">
                <div>
                    <div class="name">Blue Pea Tea</div>
                    <div class="desc">Based bluepea tea with lemon juice</div>
                </div>
                <div class="price hot">17K</div>
                <div class="price">18K</div>
            </div>

            <div class="tea-row">
                <div>
                    <div class="name">Lemon Tea</div>
                    <div class="desc">Based tea with lemon juice</div>
                </div>
                <div class="price hot">15K</div>
                <div class="price">16K</div>
            </div>

            <div class="tea-row">
                <div>
                    <div class="name">Lemongrass Tea</div>
                    <div class="desc">Based tea with lemongrass juice</div>
                </div>
                <div class="price hot">16K</div>
                <div class="price">17K</div>
            </div>

            <div class="tea-row">
                <div>
                    <div class="name">Lychee Tea</div>
                    <div class="desc">Based tea, lychee flavour and lychee fruit on top</div>
                </div>
                <div class="price hot">-</div>
                <div class="price">17K</div>
            </div>

            <div class="tea-row">
                <div>
                    <div class="name">Strawberry Tea</div>
                    <div class="desc">Based tea, strawberry flavour and strawberry fruit on top</div>
                </div>
                <div class="price hot">-</div>
                <div class="price">17K</div>
            </div>
        </div>

        <div class="gallery">
            <div class="photo">
                <img src="<?= base_url('assets/menu-book/products/beverages/tea/blue-pea-tea.png');?>">
                <div class="caption">Blue Pea Tea</div>
            </div>

            <div class="photo">
                <img src="<?= base_url('assets/menu-book/products/beverages/tea/lemongrass-tea.png');?>">
                <div class="caption">Lemongrass Tea</div>
            </div>

            <div class="photo wide">
                <img src="<?= base_url('assets/menu-book/products/beverages/tea/strawberry-tea.png');?>">
                <div class="caption">Strawberry Tea</div>
            </div>
        </div>

    </div>

    <div class="wedang">
        <div class="wedang-list">
            <div class="section-title" style="font-size:15pt;margin-bottom:3mm;"><span>02</span> Wedangan</div>

            <div class="menu-row">
                <div class="name">Adu Ramu</div>
                <div class="price hot">18K</div>
            </div>

            <div class="menu-row">
                <div class="name">Wedang Uwuh</div>
                <div class="price hot">15K</div>
            </div>

            <div class="menu-row">
                <div class="name">Wedang Jahe</div>
                <div class="price hot">14K</div>
            </div>

            <div class="menu-row">
                <div class="name">Wedang Jahe Susu</div>
                <div class="price hot">15K</div>
            </div>
        </div>

        <div class="wedang-img">
            <span class="wedang-label">WEDANGAN</span>

            <img
                src="<?= base_url('assets/menu-book/products/beverages/tea/wedang.png');?>"
                alt="Wedang"
            >
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