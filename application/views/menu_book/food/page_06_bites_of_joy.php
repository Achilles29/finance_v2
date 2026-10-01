<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Bites of Joy - NAMUA</title>

<style>
@page{size:A4 portrait;margin:0}
*{box-sizing:border-box}
body{margin:0;background:#2b1b15}

.page{
    width:210mm;
    height:297mm;
    margin:auto;
    overflow:hidden;
    position:relative;
    background:url("<?= base_url('assets/menu-book/backgrounds/bg-bites.png'); ?>") center/cover no-repeat;
    font-family:Georgia,"Times New Roman",serif;
    color:#30160f;
}

.wrap{
    padding:6mm 9mm 8mm;
    height:100%;
}

.header{
    text-align:center;
    height:25mm;
}

.kicker{
    font-size:24px;
    line-height:.8;
    color:#c09a50;
    font-style:italic;
}

.title{
    font-size:46px;
    line-height:.78;
    color:#841b25;
    font-weight:900;
    letter-spacing:2px;
    text-transform:uppercase;
}

.subtitle{
    margin-top:2.5mm;
    font-size:12px;
    font-style:italic;
    color:#5c4637;
}

.section{
    border:1px solid rgba(166,121,48,.6);
    border-radius:13px;
    background:rgba(255,249,229,.76);
    padding:2.4mm;
    margin-bottom:2.5mm;
}

.section-head{
    height:8mm;
    display:flex;
    justify-content:space-between;
    align-items:center;
    border-bottom:1px solid rgba(166,121,48,.35);
    padding-bottom:1.2mm;
    margin-bottom:2mm;
}

.section-title{
    font-size:20px;
    line-height:1;
    color:#8d1721;
    font-weight:900;
    letter-spacing:.6px;
    text-transform:uppercase;
}

.tag{
    font-size:10px;
    letter-spacing:1.2px;
    text-transform:uppercase;
    color:#8b6428;
    border:1px solid rgba(166,121,48,.42);
    border-radius:999px;
    padding:.8mm 2.4mm;
    background:rgba(255,255,246,.75);
    font-family:Arial,sans-serif;
}

.grid{
    display:grid;
    grid-template-columns:48% 52%;
    gap:2.5mm;
}

.grid.reverse{
    grid-template-columns:45% 55%;
}

.photo{
    position:relative;
    overflow:hidden;
    border-radius:10px;
    background:#ddd;
}

.photo img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

.photo.dim{height:51mm}
.photo.crispy{height:82mm}
.photo.sweet{height:51mm}

.badge{
    position:absolute;
    left:2.5mm;
    bottom:2.5mm;
    color:#fff;
    padding:1.7mm 2.4mm;
    border-radius:10px;
    background:linear-gradient(135deg,rgba(80,0,0,.78),rgba(125,24,35,.55));
    text-shadow:0 2px 5px rgba(0,0,0,.55);
}

.badge small{
    display:block;
    font-size:10px;
    letter-spacing:1.2px;
    color:#f9d66c;
    text-transform:uppercase;
}

.badge b{
    display:block;
    font-size:14px;
    line-height:1.02;
    text-transform:uppercase;
}

.badge span{
    display:block;
    font-size:14px;
    line-height:1;
    font-weight:900;
    color:#ffe18b;
}

.menu{
    padding:.5mm;
}

.menu.feature{
    border:1px solid rgba(166,121,48,.35);
    border-radius:10px;
    padding:2.4mm;
    background:linear-gradient(180deg,rgba(255,253,239,.92),rgba(255,246,217,.58));
}

.feature-label{
    display:inline-block;
    font-size:9px;
    letter-spacing:1.2px;
    color:#8d6324;
    font-weight:bold;
    text-transform:uppercase;
    background:#f7e5ab;
    border-radius:999px;
    padding:.75mm 2.2mm;
    margin-bottom:1.3mm;
    font-family:Arial,sans-serif;
}

.feature-title{
    font-size:16px;
    line-height:1;
    color:#8d1721;
    font-weight:900;
    text-transform:uppercase;
}

.desc{
    font-size:12px;
    color:#5c4333;
    line-height:1.25;
    margin:.7mm 0 2mm;
    font-family:Arial,sans-serif;
}

.item{
    display:grid;
    grid-template-columns:1fr auto;
    gap:2mm;
    border-bottom:1px dotted rgba(110,78,38,.45);
    padding:.85mm 0;
}

.item h4{
    margin:0;
    font-size:14px;
    line-height:1.08;
    color:#8d1721;
    font-weight:900;
    text-transform:uppercase;
}

.item p{
    margin:.45mm 0 0;
    font-size:10px;
    line-height:1.18;
    color:#654a39;
    font-family:Arial,sans-serif;
}

.price{
    font-size:10.5px;
    color:#8d1721;
    font-weight:900;
    white-space:nowrap;
    line-height:1;
}

.best{
    font-size:9px;
    color:#7f551a;
    background:#f3dc95;
    border-radius:999px;
    padding:.25mm 1mm;
    margin-left:.7mm;
    vertical-align:middle;
    letter-spacing:.5px;
    font-family:Arial,sans-serif;
}

.cols{
    display:grid;
    grid-template-columns:1fr 1fr;
    column-gap:2.6mm;
}

.crispy .item{
    padding:.72mm 0;
}

.crispy .item h4{
    font-size:12px;
}

.crispy .item p{
    font-size:8px;
    line-height:1.12;
}

.crispy .price{
    font-size:9.2px;
}

.footer{
    position:absolute;
    left:9mm;
    right:9mm;
    bottom:3.8mm;
    display:flex;
    justify-content:space-between;
    align-items:center;
    border-top:1px solid rgba(166,121,48,.45);
    padding-top:1.4mm;
    font-size:6px;
    letter-spacing:1.3px;
    color:#8d6427;
    text-transform:uppercase;
}

.ig{
    font-family:Arial,sans-serif;
    text-transform:none;
    letter-spacing:.5px;
}

.hash{font-style:italic}
</style>
</head>

<body>
<div class="page">
<div class="wrap">

    <div class="header">
        <div class="kicker">Bites of</div>
        <div class="title">Joy</div>
        <div class="subtitle">Crunchy bites, dim sum &amp; sweet treats.</div>
    </div>

    <section class="section">
        <div class="section-head">
            <div class="section-title">Dim Sum</div>
            <div class="tag">steam craft / mentai / mozzarella</div>
        </div>

        <div class="grid">
            <div class="photo dim">
                <img src="<?= base_url('assets/menu-book/products/foods/bites-of-joy/dimsum.png'); ?>" alt="Dim Sum">
                <div class="badge">
                    <small>Best Seller</small>
                    <b>Dimsum Mentai</b>
                    <span>28K</span>
                </div>
            </div>

            <div class="menu feature">
                <div class="feature-label">soft, juicy, sauce bangkok</div>
                <div class="feature-title">Dimsum Mentai</div>
                <div class="desc">Dimsum fill, kulit pangsit, red ginger pickled, sauce mentai with nori.</div>

                <div class="item">
                    <div><h4>Dim Sum</h4><p>Chinese steam wonton fill chicken inside, sauce bangkok.</p></div>
                    <div class="price">19K</div>
                </div>

                <div class="item">
                    <div><h4>Dimsum Mozarella Cheese</h4><p>Dimsum fill kulit lumpia, red onion pickle, leek, mozzarella.</p></div>
                    <div class="price">21K</div>
                </div>

                <div class="item">
                    <div><h4>Dimsum Mentai <span class="best">BEST</span></h4><p>Dimsum fill, kulit pangsit, red ginger pickled, sauce mentai with nori.</p></div>
                    <div class="price">28K</div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <div class="section-title">Crispy Bites</div>
            <div class="tag">platters / fries / tofu / crunch</div>
        </div>

        <div class="grid reverse">
            <div class="menu crispy">
                <div class="feature-label">big refry, shareable plates</div>

                <div class="cols">
                    <div>
                        <div class="item"><div><h4>Mix Platter Namua <span class="best">BEST</span></h4><p>Chicken skin, french fries, cireng, enoky, onion ring with tartar mayo &amp; hot volcano mayo.</p></div><div class="price">35K</div></div>
                        <div class="item"><div><h4>Duo Platter</h4><p>Crispy chicken leg, onion ring with seaweed powder &amp; tartar mayo.</p></div><div class="price">18K</div></div>
                        <div class="item"><div><h4>Enoki Crispy</h4><p>Enoky, mix flour, sauce bangkok.</p></div><div class="price">18K</div></div>
                        <div class="item"><div><h4>Tahu Cabe Garam <span class="best">BEST</span></h4><p>Crispy tofu fries tossed with jalapeno Chinese style.</p></div><div class="price">18K</div></div>
                        <div class="item"><div><h4>Mendoan Magnolia <span class="best">BEST</span></h4><p>Tempe, adonan mendoan, leek, kemangi, cabe rawit merah, sambal kecap, abon sapi.</p></div><div class="price">18K</div></div>
                        <div class="item"><div><h4>Chikuwa Cheese <span class="best">BEST</span></h4><p>Chikuwa bread crumb, mozzarella cheese, leek, sauce bangkok, cheese sauce.</p></div><div class="price">26K</div></div>
                    </div>

                    <div>
                        <div class="item"><div><h4>Fried Tofu With Chicken Inside <span class="best">BEST</span></h4><p>Tofu fill chicken inside, sauce bangkok, sambal kecap.</p></div><div class="price">18K</div></div>
                        <div class="item"><div><h4>French Fries Bolognese <span class="best">BEST</span></h4><p>Hand cut fries, cheese sauce &amp; bolognese on top.</p></div><div class="price">25K</div></div>
                        <div class="item"><div><h4>French Fries Ori</h4><p>Hand cut fries with original seasoning.</p></div><div class="price">15K</div></div>
                        <div class="item"><div><h4>Cireng</h4><p>Cireng frozen, chili sauce.</p></div><div class="price">15K</div></div>
                        <div class="item"><div><h4>Wonton Nachos <span class="best">BEST</span></h4><p>Crispy fried wonton, honey mustard &amp; bolognese on top.</p></div><div class="price">18K</div></div>
                    </div>
                </div>
            </div>

            <div class="photo crispy">
                <img src="<?= base_url('assets/menu-book/products/foods/bites-of-joy/crispy-bites.png'); ?>" alt="Crispy Bites">
                <div class="badge">
                    <small>Best Seller Focus</small>
                    <b>Mix Platter Namua</b>
                    <span>35K</span>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <div class="section-title">Sweet Treats</div>
            <div class="tag">banana / toast / chocolate / cheese</div>
        </div>

        <div class="grid">
            <div class="photo sweet">
                <img src="<?= base_url('assets/menu-book/products/foods/bites-of-joy/sweet-treats.png'); ?>" alt="Sweet Treats">
                <div class="badge">
                    <small>Dessert Spotlight</small>
                    <b>Pikameel &amp; Toast</b>
                    <span>15K - 18K</span>
                </div>
            </div>

            <div class="menu feature">
                <div class="feature-label">warm bites, caramel comfort</div>
                <div class="feature-title">Pikameel &amp; Toast Pairing</div>
                <div class="desc">Toasted chocolate, cheese and banana-based comfort bites.</div>

                <div class="cols">
                    <div>
                        <div class="item"><div><h4>Pinokio</h4><p>Banana spring roll with Nutella sauce on side dish.</p></div><div class="price">22K</div></div>
                        <div class="item"><div><h4>Roti Bakar Coklat</h4></div><div class="price">16K</div></div>
                        <div class="item"><div><h4>Roti Bakar Keju</h4></div><div class="price">17K</div></div>
                    </div>

                    <div>
                        <div class="item"><div><h4>Pikameel <span class="best">HIGHLIGHT</span></h4><p>Banana crumbling bread crumb with caramel.</p></div><div class="price">15K</div></div>
                        <div class="item"><div><h4>Roti Bakar Coklat + Keju</h4></div><div class="price">18K</div></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</div>

<div class="footer">
    <div class="ig">◎ @namuacoffee</div>
    <div>Bites of Joy</div>
    <div class="hash">#kembalikenamua</div>
</div>

</div>
</body>
</html>