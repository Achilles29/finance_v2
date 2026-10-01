<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Manual Brew & Classic Coffee</title>

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
    background:url("<?= base_url('assets/menu-book/backgrounds/bg-classics.png'); ?>") center/cover no-repeat;
    font-family:Georgia,"Times New Roman",serif;
    color:#2f1710;
}

.wrap{
    padding:8mm 9mm 10mm;
    height:100%;
}

/* MANUAL BREW - NEW LAYOUT */
.manual-box{
    height:96mm;
    display:grid;
    grid-template-columns:43% 57%;
    gap:4mm;
}

.manual-menu{
    border:1px solid rgba(126,82,37,.58);
    border-radius:16px;
    background:rgba(255,247,224,.86);
    padding:5mm;
}

.manual-title{
    font-size:27px;
    line-height:1;
    color:#821b25;
    font-weight:900;
    letter-spacing:1.5px;
    text-transform:uppercase;
}

.note{
    display:inline-block;
    margin:3mm 0 5mm;
    padding:2mm 4mm;
    border-radius:999px;
    background:#a67943;
    color:#fff5dc;
    font-family:Arial,sans-serif;
    font-size:8px;
    font-weight:900;
    letter-spacing:1.2px;
    text-transform:uppercase;
}

.row{
    display:grid;
    grid-template-columns:auto 1fr auto;
    gap:2mm;
    align-items:end;
    padding:2.45mm 0;
}

.name{
    font-family:Arial,sans-serif;
    font-size:10.7px;
    font-weight:900;
    color:#2b160f;
    text-transform:uppercase;
    white-space:nowrap;
}

.dots{
    border-bottom:1px dotted rgba(117,76,34,.8);
    transform:translateY(-1.5mm);
}

.price{
    font-size:15px;
    color:#821b25;
    font-weight:900;
    white-space:nowrap;
}

.photo-stack{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:3mm;
}

.photo-main{
    grid-row:1/3;
    height:96mm;
}

.photo-side{
    height:46.5mm;
}

.slot{
    overflow:hidden;
    border-radius:16px;
    border:1px solid rgba(126,82,37,.35);
    background:rgba(255,255,255,.42);
}

.slot img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

/* TITLE */
.title-area{
    height:51mm;
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:0 5mm;
}

.big-title{
    font-family:Impact,"Arial Black",Arial,sans-serif;
    font-size:56px;
    line-height:.8;
    letter-spacing:5px;
    color:#8a2025;
    text-transform:uppercase;
}

.big-title span{
    display:block;
    color:#2b160f;
}

.caption{
    width:60mm;
    text-align:right;
    font-size:11.5px;
    line-height:1.35;
    font-style:italic;
    color:#7b542b;
}

/* CLASSIC MENU */
.classic-box{
    height:116mm;
    border:1px solid rgba(126,82,37,.58);
    border-radius:16px;
    background:rgba(255,247,224,.86);
    padding:5mm 6mm;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:7mm;
    overflow:hidden;
}

.col:first-child{
    border-right:1px dotted rgba(117,76,34,.65);
    padding-right:7mm;
}

.group{
    margin-bottom:3mm;
}

.group-title{
    font-family:Arial,sans-serif;
    font-size:12.4px;
    line-height:1;
    color:#2b160f;
    font-weight:900;
    text-transform:uppercase;
}

.hotice{
    display:grid;
    grid-template-columns:14mm 1fr auto;
    align-items:end;
    gap:2mm;
    margin-top:1.1mm;
    font-family:Arial,sans-serif;
    font-size:8.2px;
    font-weight:900;
    color:#2b160f;
    text-transform:uppercase;
}

.single{
    display:grid;
    grid-template-columns:1fr auto;
    align-items:end;
    gap:2mm;
    margin-top:1.6mm;
}

.single .dots,
.hotice .dots{
    transform:translateY(-1.3mm);
}

/* FOOTER */
.footer{
    position:absolute;
    left:9mm;
    right:9mm;
    bottom:4.5mm;
    border-top:1px solid rgba(126,82,37,.48);
    padding-top:2.2mm;
    display:flex;
    justify-content:space-between;
    align-items:center;
    color:#7b1b24;
    font-size:10.5px;
}

.ig{font-family:Arial,sans-serif}
.mid{
    color:#8a642c;
    letter-spacing:5px;
    font-size:6px;
    text-transform:uppercase;
}
.hash{font-style:italic}
</style>
</head>

<body>
<div class="page">

<div class="wrap">

    <section class="manual-box">
        <div class="manual-menu">
            <div class="manual-title">Manual Brew</div>
            <div class="note">Ask Our Barista For Bean Choice</div>

            <div class="row"><div class="name">Manual Brew V60</div><div class="dots"></div><div class="price">20 K</div></div>
            <div class="row"><div class="name">Manual Brew V60 Japanese</div><div class="dots"></div><div class="price">21 K</div></div>
            <div class="row"><div class="name">Manual Brew Kalita</div><div class="dots"></div><div class="price">20 K</div></div>
            <div class="row"><div class="name">Manual Brew Aeropress</div><div class="dots"></div><div class="price">20 K</div></div>
            <div class="row"><div class="name">French Press Coffee</div><div class="dots"></div><div class="price">20 K</div></div>
            <div class="row"><div class="name">Vietnam Drip</div><div class="dots"></div><div class="price">17 K</div></div>
        </div>

        <div class="photo-stack">
            <div class="slot photo-main">
                <img src="<?= base_url('assets/menu-book/products/beverages/classics/manual-brew-main.png'); ?>" alt="Manual Brew">
            </div>

            <div class="slot photo-side">
                <img src="<?= base_url('assets/menu-book/products/beverages/classics/hot-coffee.png'); ?>" alt="Hot Coffee">
            </div>

            <div class="slot photo-side">
                <img src="<?= base_url('assets/menu-book/products/beverages/classics/iced-coffee.png'); ?>" alt="Iced Coffee">
            </div>
        </div>
    </section>

    <section class="title-area">
        <div class="big-title">
            Classic
            <span>Coffee</span>
        </div>

        <div class="caption">
            Daily coffee essentials, from clean black coffee to creamy milk-based classics.
        </div>
    </section>

    <section class="classic-box">
        <div class="col">
            <div class="group">
                <div class="group-title">Americano</div>
                <div class="hotice"><span>Hot</span><span class="dots"></span><span class="price">20 K</span></div>
                <div class="hotice"><span>Ice</span><span class="dots"></span><span class="price">21 K</span></div>
            </div>

            <div class="group">
                <div class="group-title">Long Black</div>
                <div class="hotice"><span>Hot</span><span class="dots"></span><span class="price">20 K</span></div>
                <div class="hotice"><span>Ice</span><span class="dots"></span><span class="price">21 K</span></div>
            </div>

            <div class="group">
                <div class="group-title">Cafe Latte</div>
                <div class="hotice"><span>Hot</span><span class="dots"></span><span class="price">20 K</span></div>
                <div class="hotice"><span>Ice</span><span class="dots"></span><span class="price">21 K</span></div>
            </div>

            <div class="group">
                <div class="group-title">Magic Latte</div>
                <div class="single"><span class="dots"></span><span class="price">23 K</span></div>
            </div>

            <div class="group">
                <div class="group-title">Cappucino</div>
                <div class="hotice"><span>Hot</span><span class="dots"></span><span class="price">20 K</span></div>
                <div class="hotice"><span>Ice</span><span class="dots"></span><span class="price">21 K</span></div>
            </div>

            <div class="group">
                <div class="group-title">Flat White</div>
                <div class="single"><span class="dots"></span><span class="price">20 K</span></div>
            </div>
        </div>

        <div class="col">
            <div class="group"><div class="group-title">Doppio</div><div class="single"><span class="dots"></span><span class="price">19 K</span></div></div>
            <div class="group"><div class="group-title">Single Espresso</div><div class="single"><span class="dots"></span><span class="price">15 K</span></div></div>
            <div class="group"><div class="group-title">Avogato</div><div class="single"><span class="dots"></span><span class="price">20 K</span></div></div>
            <div class="group"><div class="group-title">Kopi Tubruk</div><div class="single"><span class="dots"></span><span class="price">18 K</span></div></div>
            <div class="group"><div class="group-title">Kopi Tubruk Susu</div><div class="single"><span class="dots"></span><span class="price">17 K</span></div></div>
            <div class="group"><div class="group-title">Kopi Lelet</div><div class="single"><span class="dots"></span><span class="price">10 K</span></div></div>
            <div class="group"><div class="group-title">Kopi Lelet Susu</div><div class="single"><span class="dots"></span><span class="price">12 K</span></div></div>
        </div>
    </section>

</div>

<footer class="footer">
    <div class="ig">◎ @namuacoffee</div>
    <div class="mid">Drink Your Moment</div>
    <div class="hash">#kembalikenamua</div>
</footer>

</div>
</body>
</html>