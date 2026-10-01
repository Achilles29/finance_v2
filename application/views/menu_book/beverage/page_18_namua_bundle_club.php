<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>NAMUA Bundle Club</title>

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
    background:url("<?= base_url('assets/menu-book/backgrounds/bg-bundle.png'); ?>") center/cover no-repeat;
    font-family:Georgia,"Times New Roman",serif;
    color:#2e170f;
}

.wrap{
    padding:6mm 8mm 9mm;
    height:100%;
}

.header{
    height:37mm;
    text-align:center;
}

.kicker{
    display:inline-block;
    background:#7d3917;
    color:#fff2dd;
    padding:1.5mm 7mm;
    border-radius:999px;
    font-family:Arial,sans-serif;
    font-size:8.5px;
    font-weight:900;
    letter-spacing:2px;
    text-transform:uppercase;
}

.title{
    margin-top:1.5mm;
    font-family:Impact,"Arial Black",Arial,sans-serif;
    font-size:41px;
    line-height:.82;
    letter-spacing:3px;
    color:#2b160f;
    text-transform:uppercase;
}

.title span{
    display:block;
    color:#8b1723;
    font-size:49px;
}

.subtitle{
    margin-top:1.5mm;
    font-size:11.5px;
    font-style:italic;
    color:#5a3827;
}

.products{
    height:216mm;
    display:grid;
    grid-template-columns:1fr 1fr;
    grid-template-rows:70mm 70mm 70mm;
    gap:4mm;
}

.card{
    position:relative;
    border-radius:16px;
    overflow:hidden;
    background:#ddd;
    border:2px solid rgba(255,239,209,.9);
    box-shadow:0 10px 22px rgba(45,20,10,.22);
}

.card img{
    width:100%;
    height:100%;
    object-fit:cover;
    object-position:center;
    display:block;
}

.card:after{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(180deg,transparent 48%,rgba(45,18,8,.85));
    pointer-events:none;
}

.card.tall img{
    object-fit:cover;
}

.card.wide{
    grid-column:1/3;
}

.info{
    position:absolute;
    left:3mm;
    right:3mm;
    bottom:3mm;
    z-index:3;
    color:#fff6e8;
}

.name{
    display:inline-block;
    background:#8b1723;
    padding:1.2mm 3.5mm;
    border-radius:999px;
    font-family:Impact,"Arial Black",Arial,sans-serif;
    font-size:12px;
    letter-spacing:.8px;
    line-height:1;
    text-transform:uppercase;
    margin-bottom:1.2mm;
}

.desc{
    font-family:Arial,sans-serif;
    font-size:7px;
    line-height:1.2;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.25px;
    max-width:85%;
}

.price{
    position:absolute;
    right:0;
    bottom:0;
    background:rgba(255,244,220,.96);
    color:#8b1723;
    border-radius:10px;
    padding:1.5mm 3.5mm;
    font-family:Impact,"Arial Black",Arial,sans-serif;
    font-size:21px;
    line-height:1;
    letter-spacing:1px;
}

.badge{
    position:absolute;
    right:3mm;
    top:3mm;
    z-index:4;
    background:#b98532;
    color:#fff8e8;
    border-radius:999px;
    padding:1.1mm 3mm;
    font-family:Arial,sans-serif;
    font-size:7px;
    font-weight:900;
    letter-spacing:1px;
    text-transform:uppercase;
}

.footer-strip{
    position:absolute;
    left:8mm;
    right:8mm;
    bottom:20mm;
    height:17mm;
    background:#8b1723;
    color:#fff6e7;
    border-radius:14px;
    display:grid;
    grid-template-columns:repeat(4,1fr);
    align-items:center;
    text-align:center;
    box-shadow:0 7px 14px rgba(70,25,20,.18);
}

.footer-strip div{
    padding:0 3mm;
    border-right:1px dotted rgba(255,255,255,.48);
    font-family:Arial,sans-serif;
    font-size:7.7px;
    line-height:1.2;
    font-weight:900;
    letter-spacing:.7px;
    text-transform:uppercase;
}

.footer-strip div:last-child{border-right:0}

.footer{
    position:absolute;
    left:8mm;
    right:8mm;
    bottom:5mm;
    border-top:1px solid rgba(139,78,35,.45);
    padding-top:2.3mm;
    display:flex;
    justify-content:center;
    gap:12mm;
    align-items:center;
    color:#7b1b24;
    font-size:11px;
}

.ig{font-family:Arial,sans-serif}
.hash{font-style:italic}
</style>
</head>

<body>
<div class="page">
<div class="wrap">

    <header class="header">
        <div class="kicker">NAMUA Coffee &amp; Eatery</div>
        <div class="title">Bundle <span>Club</span></div>
        <div class="subtitle">Satu paket, lebih hemat, lebih nikmat.</div>
    </header>

    <section class="products">

        <div class="card">
            <img src="<?= base_url('assets/menu-book/products/beverages/bundle/coffee-bites-combo.jpg'); ?>" alt="Coffee & Bites Combo">
            <div class="badge">Best Combo</div>
            <div class="info">
                <div class="name">Coffee &amp; Bites Combo</div>
                <div class="desc">3 pcs Kopsu Gula Aren + 2 porsi Dimsum</div>
                <div class="price">80 K</div>
            </div>
        </div>

        <div class="card tall">
            <img src="<?= base_url('assets/menu-book/products/beverages/bundle/double-johny.jpg'); ?>" alt="Double Johny">
            <div class="badge">2 Botol</div>
            <div class="info">
                <div class="name">Double Johny</div>
                <div class="desc">2 botol Johny Napalm</div>
                <div class="price">125 K</div>
            </div>
        </div>

        <div class="card">
            <img src="<?= base_url('assets/menu-book/products/beverages/bundle/kopsu-satu-basecamp.jpg'); ?>" alt="Kopsu Satu Basecamp">
            <div class="badge">10 PCS</div>
            <div class="info">
                <div class="name">Kopsu Satu Basecamp</div>
                <div class="desc">10 pcs Kopsu Gula Aren</div>
                <div class="price">170 K</div>
            </div>
        </div>

        <div class="card">
            <img src="<?= base_url('assets/menu-book/products/beverages/bundle/cobain-semua.jpg'); ?>" alt="Cobain Semua">
            <div class="badge">4 Rasa</div>
            <div class="info">
                <div class="name">Cobain Semua</div>
                <div class="desc">Kopsu Gula Aren, Namanya Kopi Susu, Kopi Susu Namua, Strawberry Latte</div>
                <div class="price">70 K</div>
            </div>
        </div>

        <div class="card wide">
            <img src="<?= base_url('assets/menu-book/products/beverages/bundle/si-paling-kopi-susu.jpg'); ?>" alt="Si Paling Kopi Susu">
            <div class="badge">3 PCS</div>
            <div class="info">
                <div class="name">Si Paling Kopi Susu</div>
                <div class="desc">3 pcs Namanya Kopi Susu</div>
                <div class="price">45 K</div>
            </div>
        </div>

    </section>

    <section class="footer-strip">
        <div>Rasa favorit<br>NAMUA</div>
        <div>Lebih hemat<br>satu paket</div>
        <div>Disajikan dingin<br>lebih segar</div>
        <div>Cocok buat<br>semua momen</div>
    </section>

</div>

<footer class="footer">
    <div class="ig">◎ @namuacoffee</div>
    <div class="hash">#kembalikenamua</div>
</footer>

</div>
</body>
</html>