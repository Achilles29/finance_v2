<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>NAMUA Signatures - Beverages</title>

<style>
@page{size:A4 portrait;margin:0}
*{box-sizing:border-box}
html,body{margin:0;padding:0;background:#2b1b15}

.page{
    width:210mm;
    height:297mm;
    margin:auto;
    position:relative;
    overflow:hidden;
    background:url("<?= base_url('assets/menu-book/backgrounds/bg-signatures.png'); ?>") center/cover no-repeat;
    font-family:Georgia,"Times New Roman",serif;
    color:#781924;
    padding:6mm;
}

.sheet{
    height:100%;
    border:1px solid rgba(158,107,42,.75);
    padding:5mm;
    position:relative;
}

.sheet:before{
    content:"";
    position:absolute;
    inset:2mm;
    border:.6px solid rgba(120,25,36,.38);
    pointer-events:none;
}

.header{
    height:30mm;
    text-align:center;
    position:relative;
    z-index:2;
}

.brand{
    font-size:43px;
    line-height:.82;
    letter-spacing:9px;
    font-weight:900;
}

.title{
    font-size:25px;
    letter-spacing:8px;
    color:#8b642b;
}

.subtitle{
    margin-top:2mm;
    font-size:13px;
    font-style:italic;
    color:#4d261d;
}

.content{
    position:relative;
    z-index:2;
}

.section-title{
    display:inline-block;
    background:#7d1722;
    color:#fff8e8;
    padding:2.5mm 8mm 2.5mm 5mm;
    font-size:18px;
    font-weight:900;
    letter-spacing:1.8px;
    text-transform:uppercase;
    border-radius:0 999px 999px 0;
    margin-bottom:3mm;
}

/* FEATURE COFFEE */
.coffee-wrap{
    border:1px solid rgba(171,124,47,.72);
    border-radius:16px;
    background:rgba(255,248,227,.72);
    overflow:hidden;
    padding:4mm;
    margin-bottom:4mm;
}

.coffee-layout{
    display:grid;
    grid-template-columns:50% 50%;
    gap:3mm;
}

.hero-drink{
    height:107mm;
    border-radius:15px;
    overflow:hidden;
    position:relative;
    border:1px solid rgba(171,124,47,.55);
    background:#eee;
}

.hero-drink img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

.hero-info{
    position:absolute;
    left:0;
    right:0;
    bottom:0;
    padding:16mm 4mm 4mm;
    background:linear-gradient(transparent,rgba(40,18,12,.93));
    color:#fff8e8;
}

.hero-info .tag{
    display:inline-block;
    background:#7d1722;
    border-radius:999px;
    padding:1.3mm 3mm;
    font-family:Arial,sans-serif;
    font-size:7px;
    letter-spacing:1px;
    text-transform:uppercase;
    margin-bottom:2mm;
}

.hero-info h2{
    margin:0;
    font-size:22px;
    line-height:1;
    text-transform:uppercase;
}

.hero-info p{
    margin:1.5mm 0 0;
    font-family:Arial,sans-serif;
    font-size:8.4px;
    line-height:1.3;
    text-transform:uppercase;
    letter-spacing:.4px;
}

.hero-info .price{
    text-align:right;
    font-size:27px;
    font-weight:900;
    color:#ffe4a0;
}

.mini-coffee{
    display:grid;
    grid-template-rows:1fr 1fr 1fr;
    gap:3mm;
}

.mini-card{
    height:33.7mm;
    border:1px solid rgba(171,124,47,.55);
    border-radius:14px;
    overflow:hidden;
    background:rgba(255,252,238,.9);
    display:grid;
    grid-template-columns:43% 57%;
}

.mini-photo{
    overflow:hidden;
    background:#ddd;
}

.mini-photo img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;

  }

.mini-body{
    padding:3mm 3mm 2mm;
    display:flex;
    flex-direction:column;
}

.mini-body h3{
    margin:0;
    font-size:12.2px;
    line-height:1;
    text-transform:uppercase;
}

.mini-body p{
    margin:1.3mm 0 0;
    font-family:Arial,sans-serif;
    font-size:6.8px;
    line-height:1.25;
    letter-spacing:.35px;
    color:#5c2d24;
    text-transform:uppercase;
}

.mini-body .price{
    margin-top:auto;
    font-size:18px;
    font-weight:900;
}

/* TEA */
.tea-wrap{
    height:79mm;
    border:1px solid rgba(171,124,47,.72);
    border-radius:16px;
    overflow:hidden;
    background:rgba(255,248,227,.72);
    display:grid;
    grid-template-columns:55% 45%;
}

.tea-photo{
    height:79mm;
    overflow:hidden;
    position:relative;
}

.tea-photo img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

.tea-photo:after{
    content:"ARTISAN TEA";
    position:absolute;
    left:4mm;
    bottom:4mm;
    color:#fff8e8;
    font-size:22px;
    font-weight:900;
    letter-spacing:1.2px;
    text-shadow:0 3px 12px rgba(0,0,0,.65);
}

.tea-menu{
    padding:7mm 6mm;
    background:linear-gradient(135deg,rgba(255,250,235,.96),rgba(239,231,196,.9));
}

.green-title{
    display:inline-block;
    background:#4e5a25;
    color:#fff8e8;
    padding:2.3mm 6mm 2.3mm 4mm;
    font-size:17px;
    font-weight:900;
    letter-spacing:1.3px;
    text-transform:uppercase;
    border-radius:0 999px 999px 0;
    margin-bottom:8mm;
}

.tea-item{
    display:grid;
    grid-template-columns:1fr auto;
    gap:4mm;
    padding:4mm 0;
    border-bottom:1px dotted rgba(79,84,37,.55);
}

.tea-item:last-child{border-bottom:0}

.tea-item h3{
    margin:0;
    font-size:15px;
    color:#4d5a24;
    text-transform:uppercase;
    line-height:1;
}

.tea-item p{
    margin:1.3mm 0 0;
    font-family:Arial,sans-serif;
    font-size:8.5px;
    line-height:1.3;
    color:#424126;
    text-transform:uppercase;
    letter-spacing:.5px;
}

.tea-price{
    font-size:21px;
    font-weight:900;
    color:#4d5a24;
}

.oat{
    width:120mm;
    margin:4mm auto 0;
    text-align:center;
    border:1px solid rgba(171,124,47,.68);
    border-radius:999px;
    padding:2.5mm 8mm;
    color:#714d21;
    font-family:Arial,sans-serif;
    font-size:12px;
    letter-spacing:3px;
    font-weight:900;
    text-transform:uppercase;
    background:rgba(255,244,217,.86);
}

.footer{
    position:absolute;
    left:11mm;
    right:11mm;
    bottom:5mm;
    border-top:1px solid rgba(132,91,42,.5);
    padding-top:2mm;
    display:flex;
    justify-content:space-between;
    align-items:center;
    color:#7b1b24;
    font-size:10px;
    z-index:3;
}

.ig{font-family:Arial,sans-serif}
.mid{color:#8a642c;letter-spacing:4px;font-size:6.5px;text-transform:uppercase}
.hash{font-style:italic}
</style>
</head>

<body>
<div class="page">
<div class="sheet">

    <header class="header">
        <div class="brand">NAMUA</div>
        <div class="title">SIGNATURES</div>
        <div class="subtitle">The drinks that define us.</div>
    </header>

    <main class="content">

        <section class="coffee-wrap">
            <div class="section-title">Signature Coffee</div>

            <div class="coffee-layout">

                <div class="hero-drink">
                    <img src="<?= base_url('assets/menu-book/products/beverages/signatures/kopi-susu-namua.png'); ?>" alt="Kopi Susu Namua">
                    <div class="hero-info">
                        <div class="tag">Best Seller</div>
                        <h2>Kopi Susu Namua</h2>
                        <p>Single espresso with kawista flavour, milk &amp; jelly chocolate</p>
                        <div class="price">22 K</div>
                    </div>
                </div>

                <div class="mini-coffee">

                    <div class="mini-card">
                        <div class="mini-photo">
                            <img src="<?= base_url('assets/menu-book/products/beverages/signatures/jazzy-almond.png'); ?>" alt="Jazzy Almond">
                        </div>
                        <div class="mini-body">
                            <h3>Jazzy Almond</h3>
                            <p>Espresso, fresh milk, hazelnut, cream cheese, almond</p>
                            <div class="price">22 K</div>
                        </div>
                    </div>

                    <div class="mini-card">
                        <div class="mini-photo">
                            <img src="<?= base_url('assets/menu-book/products/beverages/signatures/namanya-kopi-susu.png'); ?>" alt="Namanya Kopi Susu">
                        </div>
                        <div class="mini-body">
                            <h3>Namanya Kopi Susu</h3>
                            <p>Espresso, oat milk, and sweet creamy flavour</p>
                            <div class="price">19 K</div>
                        </div>
                    </div>

                    <div class="mini-card">
                        <div class="mini-photo">
                            <img src="<?= base_url('assets/menu-book/products/beverages/signatures/bts-coffee.png'); ?>" alt="BTS Coffee">
                        </div>
                        <div class="mini-body">
                            <h3>BTS Coffee</h3>
                            <p>Espresso, fresh milk, vanilla ice cream, butterscotch</p>
                            <div class="price">24 K</div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <section class="tea-wrap">
            <div class="tea-photo">
                <img src="<?= base_url('assets/menu-book/products/beverages/artisan-tea/artisan-tea.png'); ?>" alt="Artisan Tea">
            </div>

            <div class="tea-menu">
                <div class="green-title">Artisan Tea</div>

                <div class="tea-item">
                    <div>
                        <h3>Nirvana Bloom</h3>
                        <p>Chamomile flowers, goji berry, dried lime</p>
                    </div>
                    <div class="tea-price">18 K</div>
                </div>

                <div class="tea-item">
                    <div>
                        <h3>Sattva White</h3>
                        <p>White tea, dried apple fuji</p>
                    </div>
                    <div class="tea-price">18 K</div>
                </div>

            </div>
        </section>

    </main>

    <footer class="footer">
        <div class="ig">◎ @namuacoffee</div>
        <div class="mid">Drink Your Moment</div>
        <div class="hash">#kembalikenamua</div>
    </footer>

</div>
</div>
</body>
</html>