<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Welcome to NAMUA</title>

<style>
@page{
    size:A4 portrait;
    margin:0;
}

*{
    box-sizing:border-box;
}

body{
    margin:0;
    background:#ddd;
    font-family:Georgia, serif;
}

.page{
    width:210mm;
    height:297mm;
    margin:auto;
    position:relative;
    overflow:hidden;
    background:
        linear-gradient(rgba(250,247,241,.12),rgba(250,247,241,.12)),
        url("<?= base_url('assets/menu-book/backgrounds/bg-opening.jpg');?>")
        center/cover no-repeat;
}

/* ===== AREA FOTO ===== */

.hero{
    position:absolute;
    top:0;
    left:0;
    right:0;
    height:44%;
    overflow:hidden;
}

.hero img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

/* overlay tipis agar foto menyatu */
.hero::after{
    content:"";
    position:absolute;
    inset:0;
    background:
        linear-gradient(
            to bottom,
            rgba(255,255,255,.05),
            rgba(255,255,255,.08),
            rgba(255,255,255,.35)
        );
}

/* ===== PANEL CERITA ===== */

.story{
    position:absolute;
    left:0;
    right:0;
    bottom:0;
    height:58%;

    background:
        linear-gradient(
            rgba(25,20,17,.78),
            rgba(25,20,17,.82)
        );

    backdrop-filter:blur(10px);
    color:#fff;

    border-top-left-radius:28px;
    border-top-right-radius:28px;

    box-shadow:
        0 -15px 40px rgba(0,0,0,.25);
}

/* ===== LOGO ===== */

.logo-wrap{
    position:absolute;
    top:-55px;
    left:50%;
    transform:translateX(-50%);
}

.logo-circle{
    width:110px;
    height:110px;
    border-radius:50%;

    background:
        rgba(255,255,255,.12);

    backdrop-filter:blur(12px);

    border:4px solid rgba(255,255,255,.28);

    display:flex;
    align-items:center;
    justify-content:center;
}

.logo-circle img{
    width:74px;
}

/* ===== CONTENT ===== */

.content{
    padding:
        75px
        18mm
        10mm;
}

.title{
    text-align:center;
    margin-bottom:6mm;
}

.title h2{
    margin:0;
    font-size:13pt;
    letter-spacing:4px;
    text-transform:uppercase;
    color:#e7d6b2;
}

.line{
    width:90px;
    height:2px;
    background:#b98a52;
    margin:8px auto;
}

.story-text{
    font-size:12pt;
    line-height:1.85;
    text-align:justify;
    color:#f7f3ea;
}

.story-text::first-letter{
    font-size:42pt;
    float:left;
    line-height:34px;
    padding-right:8px;
    color:#d4a86a;
}

.quote{
    margin-top:8mm;
    text-align:center;
    font-style:italic;
    color:#e9c98c;
    font-size:15pt;
}

.quote::before,
.quote::after{
    content:"—";
    margin:0 10px;
}

/* ===== FOOTER ===== */

.footer{
    position:absolute;
    left:0;
    right:0;
    bottom:12mm;

    display:flex;
    justify-content:center;
    gap:30px;

    color:#fff;
    font-size:12pt;
}

.footer span{
    display:flex;
    align-items:center;
    gap:7px;
}

/* ===== ORNAMEN ===== */

.leaf{
    position:absolute;
    opacity:.08;
}

.leaf1{
    top:20px;
    left:-20px;
    width:180px;
}

.leaf2{
    right:-30px;
    bottom:80px;
    width:220px;
}

.gold-dot{
    position:absolute;
    width:220px;
    height:220px;
    border-radius:50%;

    background:
        radial-gradient(
            rgba(214,170,96,.18),
            transparent 70%
        );
}

.dot1{
    right:-80px;
    top:45%;
}

.dot2{
    left:-90px;
    bottom:10%;
}
</style>
</head>
<body>

<div class="page">

    <!-- FOTO CAFE -->
    <div class="hero">
        <img src="<?= base_url('assets/menu-book/opening/cafe.png');?>" alt="">
    </div>

    <div class="gold-dot dot1"></div>
    <div class="gold-dot dot2"></div>

    <!-- PANEL CERITA -->
    <div class="story">

        <div class="logo-wrap">
            <div class="logo-circle">
                <img src="<?= base_url('assets/menu-book/logo/logo.png');?>">
            </div>
        </div>

        <div class="content">

            <div class="title">
                <h2>WELCOME TO NAMUA</h2>
                <div class="line"></div>
            </div>

            <div class="story-text">
                Kami percaya bahwa bertamu atau bersilaturahmi merupakan hal baik dan akan mendatangkan hal-hal baik.

                Tempat ini diberi nama <strong>Namua</strong> (yang dalam bahasa Jawa dapat diartikan sebagai <em>Ayo Bertamu</em> atau <em>Ayo Bersilaturahmi</em>) dengan harapan akan banyak hal baik lahir dari tempat ini.

                Kami sadar bahwa menemani masyarakat Rembang tidak cukup hanya menjadi tempat makan dan minum. Kami ingin menghadirkan ruang yang nyaman, aman, dan hangat seperti rumah seorang teman.

                Tempat ini berdiri di atas tanah yang dahulu menjadi sumber penghidupan beberapa generasi. Karena itu, setiap sudutnya kami bangun dengan rasa syukur, harapan, dan keinginan untuk terus bertumbuh bersama masyarakat sekitar.

                Kami ingin cerita ini terus berlanjut, dan Namua menjadi tempat lahirnya hal-hal baik tanpa ujung.
            </div>

            <div class="quote">
                Terima kasih telah menjadi bagian dari perjalanan kami
            </div>

        </div>

        <div class="footer">
            <span>📞 0851-5073-7377</span>
            <span>📷 @namuacoffee</span>
        </div>

    </div>

</div>

</body>
</html>