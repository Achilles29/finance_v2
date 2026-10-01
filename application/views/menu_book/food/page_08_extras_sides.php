<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Extras & Condiments - NAMUA</title>

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
    padding:11mm 13mm 12mm;
}

.header{
    text-align:center;
    margin-bottom:9mm;
}

.kicker{
    font-size:7pt;
    letter-spacing:3.5px;
    color:#b98220;
    font-weight:900;
}

h1{
    margin:2mm 0 1mm;
    font-family:Georgia,serif;
    font-size:34pt;
    line-height:.9;
    font-weight:900;
    letter-spacing:1px;
}

h1 span{color:#c89124}

.subtitle{
    font-family:Georgia,serif;
    font-style:italic;
    font-size:11pt;
    color:#6d4028;
}

.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:7mm;
}

.panel{
    min-height:76mm;
    background:rgba(255,255,255,.76);
    border:1px solid rgba(192,138,43,.65);
    border-radius:7mm;
    padding:7mm 7mm 6mm;
    box-shadow:0 8px 18px rgba(70,35,15,.09);
    position:relative;
    overflow:hidden;
}

.panel.large{
    min-height:105mm;
}

.panel::after{
    content:"";
    position:absolute;
    right:-12mm;
    bottom:-12mm;
    width:38mm;
    height:38mm;
    border:1px solid rgba(192,138,43,.18);
    border-radius:50%;
}

.panel-title{
    font-family:Georgia,serif;
    font-size:15pt;
    line-height:1;
    font-weight:900;
    text-transform:uppercase;
    margin-bottom:5mm;
    color:#21100a;
    display:flex;
    align-items:center;
    gap:2mm;
}

.panel-title span{
    color:#c89124;
    font-size:17pt;
}

.item{
    display:grid;
    grid-template-columns:1fr 17mm;
    gap:4mm;
    padding:2.15mm 0;
    border-bottom:1px dotted rgba(160,110,35,.55);
    position:relative;
    z-index:2;
}

.name{
    font-size:10pt;
    font-weight:900;
    text-transform:uppercase;
    line-height:1.1;
}

.price{
    font-family:Georgia,serif;
    font-size:13pt;
    font-weight:900;
    text-align:right;
    color:#2b140d;
}

.plus .price::before{
    content:"+";
}

.footer{
    position:absolute;
    left:0;
    right:0;
    bottom:6mm;
    text-align:center;
    font-family:Georgia,serif;
    font-size:11pt;
    z-index:4;
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
        <div class="kicker">NAMUA FOOD COLLECTION</div>
        <h1><span>EXTRAS</span><br>& CONDIMENTS</h1>
        <div class="subtitle">Additional carbo, upgrades, sauces & side condiments.</div>
    </div>

    <div class="grid">

        <div class="panel">
            <div class="panel-title"><span>01</span> Additional Carbo</div>

            <div class="item">
                <div class="name">Arabian Rice</div>
                <div class="price">15K</div>
            </div>
            <div class="item">
                <div class="name">Base Gede Rice</div>
                <div class="price">11K</div>
            </div>
            <div class="item">
                <div class="name">Garlic Kemangi Rice</div>
                <div class="price">9K</div>
            </div>
            <div class="item">
                <div class="name">Steam Rice</div>
                <div class="price">6K</div>
            </div>
            <div class="item">
                <div class="name">Yakimeshi Rice</div>
                <div class="price">16K</div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-title"><span>02</span> Upgrade Carbo</div>

            <div class="item plus">
                <div class="name">Arabian Rice</div>
                <div class="price">9K</div>
            </div>
            <div class="item plus">
                <div class="name">Base Gede Rice</div>
                <div class="price">5K</div>
            </div>
            <div class="item plus">
                <div class="name">Garlic Kemangi Rice</div>
                <div class="price">9K</div>
            </div>
            <div class="item plus">
                <div class="name">Yakimezhi Rice</div>
                <div class="price">10K</div>
            </div>
        </div>

        <div class="panel large">
            <div class="panel-title"><span>03</span> Sauce, Sambal & Mayo</div>

            <div class="item"><div class="name">Barbeque Sauce</div><div class="price">7K</div></div>
            <div class="item"><div class="name">Cheese Sauce</div><div class="price">8K</div></div>
            <div class="item"><div class="name">Sambal Bawang Geprek</div><div class="price">5K</div></div>
            <div class="item"><div class="name">Sambal Dabu-Dabu</div><div class="price">5K</div></div>
            <div class="item"><div class="name">Sambal Idjo</div><div class="price">5K</div></div>
            <div class="item"><div class="name">Sambal Kecap</div><div class="price">7K</div></div>
            <div class="item"><div class="name">Sambal Matah</div><div class="price">6K</div></div>
            <div class="item"><div class="name">Sauce Bangkok</div><div class="price">5K</div></div>
            <div class="item"><div class="name">Tar-Tar Mayo</div><div class="price">7K</div></div>
            <div class="item"><div class="name">Teriyaki Sauce</div><div class="price">6K</div></div>
        </div>

        <div class="panel large">
            <div class="panel-title"><span>04</span> Other Condiment</div>

            <div class="item">
                <div class="name">Additional Fried Wonton</div>
                <div class="price">5K</div>
            </div>
            <div class="item">
                <div class="name">Crackers</div>
                <div class="price">5K</div>
            </div>
            <div class="item">
                <div class="name">Emping Crackers</div>
                <div class="price">5K</div>
            </div>
            <div class="item">
                <div class="name">Telor</div>
                <div class="price">5K</div>
            </div>
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