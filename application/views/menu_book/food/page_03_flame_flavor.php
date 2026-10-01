<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Flame & Flavor - NAMUA</title>

<style>
@page { size:A4 portrait; margin:0; }
*{box-sizing:border-box}
body{margin:0;background:#ddd}

.page{
    width:210mm;
    height:297mm;
    margin:auto;
    position:relative;
    overflow:hidden;
    background:
        url("<?= base_url('assets/menu-book/backgrounds/bg-flame-flavor.png'); ?>") center/cover no-repeat,
        linear-gradient(135deg,#fff8eb,#efe1c9);
    font-family:Arial,sans-serif;
    color:#21100a;
}

.wrap{
    position:relative;
    z-index:2;
    padding:7mm 10mm 15mm;
}

.header{
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    margin-bottom:3mm;
    border-bottom:1px solid #c08a2b;
    padding-bottom:2mm;
}

.kicker{
    font-size:7pt;
    letter-spacing:3px;
    color:#b98220;
    font-weight:900;
}

h1{
    margin:0;
    font-family:Georgia,serif;
    font-size:31pt;
    line-height:.85;
}

h1 span{color:#c89124}

.subtitle{
    font-family:Georgia,serif;
    font-style:italic;
    font-size:10pt;
    color:#6d4028;
}

.note{
    font-family:"Brush Script MT",cursive;
    font-size:16pt;
    color:#a32116;
    transform:rotate(-5deg);
}

.section-title{
    font-family:Georgia,serif;
    font-weight:900;
    font-size:12.5pt;
    text-transform:uppercase;
    margin:0 0 1.7mm;
    display:flex;
    align-items:center;
    gap:3mm;
}

.section-title span{color:#c89124}

.section-title:after{
    content:"";
    flex:1;
    border-top:1px dotted #ba8427;
}

/* HERO TRAY */
.tray{
    height:75mm;
    border-radius:8mm;
    border:1px solid rgba(224,173,69,.35);
    overflow:hidden;
    display:grid;
    grid-template-columns:60% 40%;
    margin-bottom:3.5mm;
    box-shadow:0 10px 22px rgba(60,28,12,.18);
    background:
        linear-gradient(
            135deg,
            rgba(22,8,4,.90) 0%,
            rgba(46,22,14,.82) 55%,
            rgba(70,35,20,.76) 100%
        );
    backdrop-filter:blur(2px);
}

.tray-img{
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;

    background:
        radial-gradient(circle at center,
        rgba(255,180,80,.12),
        transparent 55%),
        rgba(24,10,6,.82);
}
.tray-img img{
    width:175%;
    height:175%;
    object-fit:contain;
    margin-top:-5mm;
    filter:drop-shadow(0 10px 15px rgba(0,0,0,.5));
}

.tray-menu{
    color:#fff4df;
    padding:7mm 6mm;
    display:flex;
    flex-direction:column;
    justify-content:center;

    background:rgba(53,27,17,.78);
    backdrop-filter:blur(2px);
}
.tray-menu h2{
    font-family:Georgia,serif;
    font-size:19pt;
    line-height:.95;
    margin:0 0 3mm;
    text-transform:uppercase;
}

.tray-menu h2 span{color:#e0ad45}

/* WESTERN */
.classics{
    height:89mm;
    margin-bottom:2.5mm;
}

.western-showcase{
    display:grid;
    grid-template-columns:34% 66%;
    gap:3mm;
}

.highlight-card{
    height:66mm;
    border:1.4px solid #c08a2b;
    border-radius:5mm;
    background:rgba(255,255,255,.82);
    position:relative;
    overflow:hidden;
    display:flex;
    align-items:center;
    justify-content:center;
}

.highlight-card img{
    width:150%;
    height:150%;
    object-fit:contain;
    padding:1mm;
}

.highlight-card small,
.mini-card small{
    position:absolute;
    top:1.7mm;
    left:1.7mm;
    background:#9d2016;
    color:#fff;
    border-radius:99px;
    padding:.9mm 2.3mm;
    font-size:5.5pt;
    font-weight:900;
}

.mini-area{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:2mm;
    margin-bottom:2.4mm;
}

.mini-card{
    height:29mm;
    border:1px solid #c08a2b;
    border-radius:4mm;
    background:rgba(255,255,255,.78);
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
    overflow:hidden;
}

.mini-card img{
    width:180%;
    height:180%;
    object-fit:contain;
    padding:1mm;
}

.classic-menu{
    background:rgba(255,255,255,.72);
    border-radius:5mm;
    padding:2.5mm 4mm;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:.5mm 8mm;
}

/* PASTA */
.pasta{
    height:60mm;
    border:1px solid #c08a2b;
    border-radius:7mm;
    overflow:hidden;
    display:grid;
    grid-template-columns:52% 48%;
    background:rgba(255,255,255,.74);
}

.pasta-img{
    overflow:hidden;
    display:flex;
    align-items:center;
    justify-content:center;
}

.pasta-img img{
    width:100%;
    height:100%;
    object-fit:contain;
    object-position:center;
    padding:2mm;
}

.pasta-menu{
    padding:4.5mm 6mm;
}

.pasta-menu h2{
    font-family:Georgia,serif;
    font-size:17pt;
    line-height:.92;
    margin:0 0 2.5mm;
    text-transform:uppercase;
}

.pasta-menu h2 span{color:#c89124}

/* ROW */
.menu-row{
    display:grid;
    grid-template-columns:1fr 14mm;
    gap:3mm;
    padding:.75mm 0;
    border-bottom:1px dotted rgba(160,110,35,.55);
}

.name{
    font-size:7pt;
    font-weight:900;
    text-transform:uppercase;
    line-height:1;
}

.desc{
    font-size:4.8pt;
    line-height:1.12;
    text-transform:uppercase;
    margin-top:.35mm;
    color:#6a3b28;
}

.tray-menu .desc{color:rgba(255,255,255,.72)}

.price{
    text-align:right;
    font-family:Georgia,serif;
    font-size:11pt;
    font-weight:900;
    align-self:center;
}

.footer{
    position:absolute;
    left:0;
    right:0;
    bottom:2mm;
    z-index:5;
    text-align:center;
    font-family:Georgia,serif;
    font-size:11pt;
}

.hashtag{
    font-style:italic;
    font-size:14pt;
    margin-right:8mm;
}

.ig{
    font-family:Arial;
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
        <div>
            <div class="kicker">NAMUA FOOD COLLECTION</div>
            <h1><span>FLAME</span> & FLAVOR</h1>
            <div class="subtitle">Western classics, steaks, burgers & pasta.</div>
        </div>
        <div class="note">grill · crisp · creamy</div>
    </div>

    <div class="section-title"><b>Signature Tray</b></div>

    <section class="tray">
        <div class="tray-img">
            <img src="<?= base_url('assets/menu-book/products/foods/flame-flavor/tray-combo.png'); ?>">
        </div>
        <div class="tray-menu">
            <h2><span>Hot Grill</span><br>Tray Combo</h2>

            <div class="menu-row"><div><div class="name">Dry-Rub Papper Steak</div><div class="desc">Sirloin meltique, hand cut fries, mac n cheese, cole slaw, BBQ sauce</div></div><div class="price">65K</div></div>
            <div class="menu-row"><div><div class="name">US Smoked Brisket</div><div class="desc">Beef shortplate, hand cut fries, mac n cheese, cole slaw, BBQ sauce</div></div><div class="price">50K</div></div>
            <div class="menu-row"><div><div class="name">Southern Style Fried Chicken</div><div class="desc">Chicken breast, mix flour, hand cut fries, mac n cheese, cole slaw, BBQ sauce</div></div><div class="price">40K</div></div>
        </div>
    </section>

    <section class="classics">
        <div class="section-title"><b>Western Classics</b></div>

        <div class="western-showcase">

            <div class="highlight-card">
                <small>STEAK</small>
                <img src="<?= base_url('assets/menu-book/products/foods/flame-flavor/sirloin-steak-meltique.png'); ?>">
            </div>

            <div>
                <div class="mini-area">
                    <div class="mini-card">
                        <small>CCB</small>
                        <img src="<?= base_url('assets/menu-book/products/foods/flame-flavor/chicken-cordon-bleu.png'); ?>">
                    </div>

                    <div class="mini-card">
                        <small>BURGER</small>
                        <img src="<?= base_url('assets/menu-book/products/foods/flame-flavor/namua-signature-burger.png'); ?>">
                    </div>

                    <div class="mini-card">
                        <small>CRUNCH</small>
                        <img src="<?= base_url('assets/menu-book/products/foods/flame-flavor/namua-crunch-burger.png'); ?>">
                    </div>

                    <div class="mini-card">
                        <small>FISH</small>
                        <img src="<?= base_url('assets/menu-book/products/foods/flame-flavor/fish-and-chips.png'); ?>">
                    </div>
                </div>

                <div class="classic-menu">
                    <div class="menu-row"><div><div class="name">Namua Signature Burger</div><div class="desc">Beef patty with original French fries, chili sauce & tomato sauce</div></div><div class="price">30K</div></div>

                    <div class="menu-row"><div><div class="name">Namua Crunch Burger</div><div class="desc">Chicken katsu with original French fries, chilli sauce & tomato sauce</div></div><div class="price">28K</div></div>

                    <div class="menu-row"><div><div class="name">Chicken Cordon Bleu</div><div class="desc">Chicken breast roll fill smoked beef & mozzarella cheese, mix salad, barbeque sauce & cheese sauce</div></div><div class="price">32K</div></div>

                    <div class="menu-row"><div><div class="name">Fish & Chips</div><div class="desc">Deep fry dory fish, hand cut fries & tar-tar mayo</div></div><div class="price">28K</div></div>

                    <div class="menu-row"><div><div class="name">Sirloin Steak Meltique</div><div class="desc">Sirloin meltique blue label, hand cut fries, salad & dressing, barbeque sauce</div></div><div class="price">90K</div></div>
                </div>
            </div>

        </div>
    </section>

    <div class="section-title"><b>Pasta Affair</b></div>

    <section class="pasta">
        <div class="pasta-img">
            <img src="<?= base_url('assets/menu-book/products/foods/flame-flavor/spageti.png'); ?>">
        </div>
        <div class="pasta-menu">
            <h2><span>Spaghetti</span><br>Selection</h2>

            <div class="menu-row"><div><div class="name">Spaghetti Aglio e Olio Con Pollo</div><div class="desc">Garlic & paprika aromatic, deep fry chicken</div></div><div class="price">25K</div></div>

            <div class="menu-row"><div><div class="name">Spaghetti Alfredo di Crema</div><div class="desc">Creamy & cheese base with smoked beef</div></div><div class="price">32K</div></div>

            <div class="menu-row"><div><div class="name">Spaghetti Bolognese</div><div class="desc">Mix beef ragu bolognese & cheese on top</div></div><div class="price">28K</div></div>

            <div class="menu-row"><div><div class="name">Spaghetti Dory Balinese</div><div class="desc">Spaghetti, chilli flakes, garlic, white pepper, dory, kemangi, garlic chips</div></div><div class="price">25K</div></div>
        </div>
    </section>

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