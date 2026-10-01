<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Blended Delights - NAMUA</title>

<style>
@page{size:A4 portrait;margin:0}
*{box-sizing:border-box}
body{margin:0;background:#2b1b15}

.page{
    width:210mm;
    height:297mm;
    margin:auto;
    position:relative;
    overflow:hidden;
    background:url("<?= base_url('assets/menu-book/backgrounds/bg-blend.png'); ?>") center/cover no-repeat;
    font-family:Georgia,"Times New Roman",serif;
    color:#35190f;
}

.wrap{
    padding:7mm 8mm 10mm;
    height:100%;
}

.header{
    height:51mm;
    position:relative;
}

.title{
    font-family:Impact,"Arial Black",Arial,sans-serif;
    font-size:49px;
    line-height:.85;
    letter-spacing:3px;
    color:#8b1822;
    text-transform:uppercase;
}

.title span{
    display:block;
    color:#31180f;
}

.subtitle{
    margin-top:3mm;
    font-size:15px;
    font-style:italic;
    color:#5a3827;
}

.nuance{
    margin-top:2mm;
    font-size:12px;
    color:#5a3827;
}

.nuance b{
    color:#8b1822;
}

.hero-drink{
    position:absolute;
    right:-2mm;
    top:-3mm;
    width:72mm;
    height:55mm;
    border-radius:0 0 0 22px;
    overflow:hidden;
}

.hero-drink img{
    width:100%;
    height:120%;
    object-fit:cover;
}

.panel{
    border:1px solid rgba(139,78,35,.55);
    border-radius:17px;
    background:rgba(255,247,229,.84);
    padding:4mm;
    margin-bottom:3.5mm;
}

.blend-panel{
    height:135mm;
}

.milk-panel{
    height:80mm;
}

.section-label{
    display:inline-block;
    background:#8b1822;
    color:#fff7e8;
    padding:2.4mm 7mm;
    border-radius:999px;
    font-size:18px;
    font-weight:900;
    letter-spacing:2px;
    text-transform:uppercase;
    margin-bottom:3mm;
}

.blend-layout{
    display:grid;
    grid-template-columns:36% 64%;
    gap:4mm;
}

.menu-list{
    padding-right:1mm;
}

.item{
    margin-bottom:2.35mm;
}

.row{
    display:grid;
    grid-template-columns:auto 1fr auto;
    gap:2mm;
    align-items:end;
}

.name{
    font-family:Arial,sans-serif;
    font-size:10.2px;
    font-weight:900;
    text-transform:uppercase;
    color:#7f1720;
    white-space:nowrap;
}

.dots{
    border-bottom:1px dotted rgba(111,67,31,.75);
    transform:translateY(-1.4mm);
}

.price{
    font-size:14px;
    font-weight:900;
    color:#7f1720;
    white-space:nowrap;
}

.desc{
    margin-top:.8mm;
    font-family:Arial,sans-serif;
    font-size:6.4px;
    line-height:1.23;
    color:#3f261b;
    text-transform:uppercase;
    letter-spacing:.3px;
}

.photo-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:3mm;
    align-content:start;
}

.drink-card{
    border-radius:12px;
    overflow:hidden;
    background:#f5e2c8;
}

.drink-card img{
    width:100%;
    height:48mm;
    object-fit:cover;
    display:block;
}

.drink-card b{
    display:block;
    text-align:center;
    padding:1.7mm 1mm;
    font-family:Arial,sans-serif;
    font-size:7.2px;
    color:#7f1720;
    text-transform:uppercase;
    background:#ead4b9;
}

.drink-card.small img{
    height:43mm;
}

.milk-layout{
    display:grid;
    grid-template-columns:42% 58%;
    gap:4mm;
}

.hotice-row{
    display:grid;
    grid-template-columns:auto 1fr auto auto auto auto;
    gap:1.5mm;
    align-items:end;
}

.hotice-row .name{
    min-width:28mm;
}

.pill{
    font-family:Arial,sans-serif;
    font-size:5.5px;
    font-weight:900;
    color:#fff;
    background:#8b1822;
    border-radius:5px;
    padding:.5mm 1.1mm;
    text-transform:uppercase;
}

.pill.ice{
    background:#6b9ac4;
}

.milk-photos{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:4mm;
    align-items:start;
}

.feature-photo{
    border-radius:13px;
    overflow:hidden;
    background:#f5e2c8;
}

.feature-photo img{
    width:100%;
    height:48mm;
    object-fit:cover;
    display:block;
}

.feature-photo b{
    display:block;
    text-align:center;
    padding:1.7mm 1mm;
    font-size:8px;
    color:#7f1720;
    text-transform:uppercase;
    background:#ead4b9;
    font-family:Arial,sans-serif;
}

.footer{
    position:absolute;
    left:8mm;
    right:8mm;
    bottom:4.5mm;
    border-top:1px solid rgba(139,78,35,.45);
    padding-top:2.3mm;
    display:flex;
    justify-content:center;
    gap:12mm;
    align-items:center;
    color:#7b1b24;
    font-size:12px;
}

.ig{font-family:Arial,sans-serif}
.hash{font-style:italic}
</style>
</head>

<body>
<div class="page">
<div class="wrap">

    <header class="header">
        <div class="title">
            Blended
            <span>Delights</span>
        </div>
        <div class="subtitle">Smooth, creamy and irresistibly sweet.</div>
        <div class="nuance"><b>Nuansa:</b> Dessert drinks</div>

        <div class="hero-drink">
            <img src="<?= base_url('assets/menu-book/products/beverages/blended/cookies-and-cream.png'); ?>" alt="Blended Delights">
        </div>
    </header>

    <section class="panel blend-panel">
        <div class="section-label">Blend &amp; Smoothies</div>

        <div class="blend-layout">
            <div class="menu-list">

                <div class="item">
                    <div class="row"><div class="name">Banana Creme Brule</div><div class="dots"></div><div class="price">23 K</div></div>
                    <div class="desc">Banana fruit blend, caramel crumb, brown sugar</div>
                </div>

                <div class="item">
                    <div class="row"><div class="name">Banana King</div><div class="dots"></div><div class="price">23 K</div></div>
                    <div class="desc">Banana fruit blend with espresso, caramel flavour, whipping cream on top</div>
                </div>

                <div class="item">
                    <div class="row"><div class="name">Cheese Snow</div><div class="dots"></div><div class="price">20 K</div></div>
                </div>

                <div class="item">
                    <div class="row"><div class="name">Cookies and Cream</div><div class="dots"></div><div class="price">22 K</div></div>
                    <div class="desc">Oreo crumb, vanilla flavour, whipping cream on top</div>
                </div>

                <div class="item">
                    <div class="row"><div class="name">Green Savanna</div><div class="dots"></div><div class="price">23 K</div></div>
                    <div class="desc">Mix pokcoy and lychee fruit</div>
                </div>

                <div class="item">
                    <div class="row"><div class="name">Havana Cookies</div><div class="dots"></div><div class="price">23 K</div></div>
                    <div class="desc">Oreo crumb, vanilla flavour, espresso, whipping cream on top</div>
                </div>

                <div class="item">
                    <div class="row"><div class="name">Strawberry Cream Cheese</div><div class="dots"></div><div class="price">22 K</div></div>
                    <div class="desc">Strawberry flavour, strawberry, cheese whipping cream on top</div>
                </div>

                <div class="item">
                    <div class="row"><div class="name">Strawberry Smoothies</div><div class="dots"></div><div class="price">21 K</div></div>
                    <div class="desc">Strawberry fruit, fresh milk, strawberry fruit on top</div>
                </div>

                <div class="item">
                    <div class="row"><div class="name">Strawpineapple</div><div class="dots"></div><div class="price">20 K</div></div>
                    <div class="desc">Mix strawberry and pineapple fruit</div>
                </div>

            </div>

            <div class="photo-grid">
                <div class="drink-card">
                    <img src="<?= base_url('assets/menu-book/products/beverages/blended/banana-king.png'); ?>" alt="Banana King">
                    <b>Banana King</b>
                </div>

                <div class="drink-card">
                    <img src="<?= base_url('assets/menu-book/products/beverages/blended/cookies-and-cream.png'); ?>" alt="Cookies and Cream">
                    <b>Cookies and Cream</b>
                </div>

                <div class="drink-card">
                    <img src="<?= base_url('assets/menu-book/products/beverages/blended/green-savanna.png'); ?>" alt="Green Savanna">
                    <b>Green Savanna</b>
                </div>

                <div class="drink-card">
                    <img src="<?= base_url('assets/menu-book/products/beverages/blended/havana-cookies.png'); ?>" alt="Havana Cookies">
                    <b>Havana Cookies</b>
                </div>

                <div class="drink-card small">
                    <img src="<?= base_url('assets/menu-book/products/beverages/blended/strawberry-cream-cheese.png'); ?>" alt="Strawberry Cream Cheese">
                    <b>Strawberry Cream Cheese</b>
                </div>

                <div class="drink-card small">
                    <img src="<?= base_url('assets/menu-book/products/beverages/blended/strawberry-smoothies.png'); ?>" alt="Strawberry Smoothies">
                    <b>Strawberry Smoothies</b>
                </div>

                <div class="drink-card small">
                    <img src="<?= base_url('assets/menu-book/products/beverages/blended/strawpineapple.png'); ?>" alt="Strawpineapple">
                    <b>Strawpineapple</b>
                </div>
            </div>
        </div>
    </section>

    <section class="panel milk-panel">
        <div class="section-label">Sweet &amp; Milk Series</div>

        <div class="milk-layout">
            <div class="menu-list">

                <div class="item">
                    <div class="row"><div class="name">Choco Berry</div><div class="dots"></div><div class="price">20 K</div></div>
                </div>

                <div class="item">
                    <div class="hotice-row">
                        <div class="name">Chocolate</div><div class="dots"></div>
                        <span class="pill">Hot</span><span class="price">19 K</span>
                        <span class="pill ice">Ice</span><span class="price">20 K</span>
                    </div>
                </div>

                <div class="item">
                    <div class="hotice-row">
                        <div class="name">Matcha</div><div class="dots"></div>
                        <span class="pill">Hot</span><span class="price">19 K</span>
                        <span class="pill ice">Ice</span><span class="price">20 K</span>
                    </div>
                </div>

                <div class="item">
                    <div class="row"><div class="name">Prestige Choco Inch</div><div class="dots"></div><div class="price">22 K</div></div>
                    <div class="desc">Dark coco, almond, hazelnut, oat milk, whipping cream</div>
                </div>

                <div class="item">
                    <div class="hotice-row">
                        <div class="name">Red Velvet</div><div class="dots"></div>
                        <span class="pill">Hot</span><span class="price">19 K</span>
                        <span class="pill ice">Ice</span><span class="price">20 K</span>
                    </div>
                </div>

                <div class="item">
                    <div class="hotice-row">
                        <div class="name">Tiramisu</div><div class="dots"></div>
                        <span class="pill">Hot</span><span class="price">19 K</span>
                        <span class="pill ice">Ice</span><span class="price">20 K</span>
                    </div>
                </div>

            </div>

            <div class="milk-photos">
                <div class="feature-photo">
                    <img src="<?= base_url('assets/menu-book/products/beverages/blended/choco-berry.png'); ?>" alt="Choco Berry">
                    <b>Choco Berry</b>
                </div>

                <div class="feature-photo">
                    <img src="<?= base_url('assets/menu-book/products/beverages/blended/prestige-choco-inch.png'); ?>" alt="Prestige Choco Inch">
                    <b>Prestige Choco Inch</b>
                </div>
            </div>
        </div>
    </section>

</div>

<footer class="footer">
    <div class="ig">◎ @namuacoffee</div>
    <div class="hash">#kembalikenamua</div>
</footer>

</div>
</body>
</html>