<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Main Character - NAMUA</title>

<style>
@page { size: A4 portrait; margin: 0; }

* { box-sizing: border-box; }

body {
    margin: 0;
    background: #ddd;
}

.page {
    width: 210mm;
    height: 297mm;
    margin: auto;
    position: relative;
    overflow: hidden;
    color: #421d12;
    background:
        url("<?= base_url('assets/menu-book/backgrounds/bg-main-character.png'); ?>")
        center / cover no-repeat;
    font-family: Georgia, "Times New Roman", serif;
}

.main-wrap {
    position: absolute;
    inset: 12mm 10mm 15mm 10mm;
    display: grid;
    grid-template-columns: 41% 56%;
    gap: 7mm;
}

.photo-list {
    display: flex;
    flex-direction: column;
    gap: 3.2mm;
}

.photo-card {
    height: 38.3mm;
    position: relative;
    border-radius: 8mm 3mm 8mm 3mm;
    overflow: hidden;
    border: 1.4px solid #c58d22;
    box-shadow: 0 4px 10px rgba(70, 35, 15, .22);
    background: #fff;
}

.photo-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.photo-number {
    position: absolute;
    top: -1mm;
    left: -1mm;
    width: 12mm;
    height: 12mm;
    border-radius: 50%;
    background: #991d14;
    color: #fff;
    border: 1.4px solid #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17pt;
    font-weight: 800;
    box-shadow: 0 3px 7px rgba(0,0,0,.25);
}

.menu-area {
    padding-top: 4mm;
}

.title-main {
    margin: 0;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 48pt;
    line-height: .78;
    font-weight: 900;
    letter-spacing: 6px;
    color: #c89124;
}

.title-script {
    margin: -3mm 0 7mm 6mm;
    font-family: "Brush Script MT", "Segoe Script", "Lucida Handwriting", cursive;
    font-size: 42pt;
    line-height: .8;
    font-weight: 400;
    color: #3b160d;
}

.subtitle {
    font-size: 12pt;
    line-height: 1.35;
    margin-bottom: 5mm;
}

.ornament {
    width: 88mm;
    height: 1px;
    background: #c89124;
    margin-bottom: 5mm;
    position: relative;
}

.ornament::after {
    content: "◇◇◇";
    position: absolute;
    left: 50%;
    top: -3.4mm;
    transform: translateX(-50%);
    color: #c89124;
    background: #fffaf0;
    padding: 0 3mm;
    font-size: 9pt;
}

.item {
    position: relative;
    padding: 2.5mm 0 3.5mm;
    border-bottom: 1.2px dotted #c89124;
    min-height: 27mm;
}

.item.no-table {
    display: grid;
    grid-template-columns: 9mm 1fr 26mm;
    column-gap: 2.5mm;
}

.menu-number {
    color: #c89124;
    font-size: 22pt;
    font-weight: 900;
    line-height: .9;
}

.item-title {
    font-size: 14.5pt;
    line-height: 1.05;
    font-weight: 900;
    text-transform: uppercase;
}

.desc {
    margin-top: 1.7mm;
    font-size: 9.3pt;
    line-height: 1.28;
    font-style: italic;
    max-width: 72mm;
}

.price {
    font-size: 20pt;
    font-weight: 900;
    align-self: center;
    text-align: right;
    white-space: nowrap;
}

.price.dual {
    display: flex;
    gap: 8mm;
    justify-content: flex-end;
}

.badge {
    display: inline-block;
    margin-left: 2mm;
    padding: 1.1mm 3.2mm;
    background: #b71914;
    color: #fff;
    border-radius: 2mm;
    font-family: Georgia, serif;
    font-size: 7.5pt;
    font-weight: 900;
    vertical-align: middle;
}

.badge::before,
.badge::after {
    content: "★";
    font-size: 6pt;
    margin: 0 1mm;
}

.table-item {
    display: grid;
    grid-template-columns: 9mm 1fr;
    column-gap: 2.5mm;
}

.table-flex {
    display: grid;
    grid-template-columns: 48% 52%;
    gap: 3mm;
}

.size-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4mm;
    font-family: Georgia, serif;
}

.size-table th {
    font-size: 7pt;
    color: #b56f12;
    text-transform: uppercase;
    padding-bottom: 1mm;
    border-bottom: 1px solid rgba(194,137,36,.45);
}

.size-table td {
    font-size: 14pt;
    font-weight: 900;
    text-align: center;
    padding: 1.8mm 0;
    border-bottom: 1px solid rgba(194,137,36,.35);
}

.size-table td:first-child {
    font-size: 8pt;
    text-align: left;
}

.upgrade-box {
    position: absolute;
    left: 14mm;
    right: 14mm;
    bottom: 11mm;
    height: 18mm;
    display: grid;
    grid-template-columns: 41mm repeat(4, 1fr);
    border: 1.4px solid #b71914;
    border-radius: 3mm;
    overflow: hidden;
    background: rgba(255,255,255,.9);
    font-family: Georgia, serif;
}

.upgrade-title {
    background: #b71914;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16pt;
    font-weight: 900;
    text-transform: uppercase;
}

.upgrade-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #a61d13;
    border-left: 1px dashed #b71914;
    text-align: center;
}

.upgrade-item span {
    font-size: 7.3pt;
    font-weight: 900;
    text-transform: uppercase;
    margin-bottom: 1.3mm;
}

.upgrade-item strong {
    font-size: 16pt;
    line-height: 1;
}

.footer {
    position: absolute;
    bottom: 2.8mm;
    left: 0;
    right: 0;
    text-align: center;
    color: #3d1a10;
    font-size: 11.5pt;
}

.hashtag {
    font-style: italic;
    font-size: 14pt;
    margin-right: 8mm;
}

.ig {
    font-family: Arial, sans-serif;
    font-weight: bold;
    border: 1.5px solid #3d1a10;
    border-radius: 5px;
    padding: 0 4px;
    margin-right: 2mm;
}

.price-stack {
    align-self: center;
    text-align: center;
    min-width: 23mm;
}

.price-stack div {
    padding: 1mm 0;
}

.price-stack div + div {
    border-top: 1px dotted #c89124;
    margin-top: 1.5mm;
    padding-top: 1.7mm;
}

.price-stack small {
    display: block;
    font-size: 6.6pt;
    color: #b56f12;
    font-weight: 900;
    text-transform: uppercase;
    line-height: 1;
    margin-bottom: .8mm;
}

.price-stack strong {
    display: block;
    font-size: 20pt;
    line-height: .95;
    color: #421d12;
}
</style>
</head>

<body>

<div class="page">

    <div class="main-wrap">

        <div class="photo-list">
            <div class="photo-card">
                <span class="photo-number">01</span>
                <img src="<?= base_url('assets/menu-book/products/foods/main-character/namua-sultan-rice.png'); ?>">
            </div>

            <div class="photo-card">
                <span class="photo-number">02</span>
                <img src="<?= base_url('assets/menu-book/products/foods/main-character/bebek-bumbu-ireng-madura.png'); ?>">
            </div>

            <div class="photo-card">
                <span class="photo-number">03</span>
                <img src="<?= base_url('assets/menu-book/products/foods/main-character/bebek-songkem-namua.png'); ?>">
            </div>

            <div class="photo-card">
                <span class="photo-number">04</span>
                <img src="<?= base_url('assets/menu-book/products/foods/main-character/ayam-songkem-namua.png'); ?>">
            </div>

            <div class="photo-card">
                <span class="photo-number">05</span>
                <img src="<?= base_url('assets/menu-book/products/foods/main-character/arabian-grill-lamb-chop.png'); ?>">
            </div>

            <div class="photo-card">
                <span class="photo-number">06</span>
                <img src="<?= base_url('assets/menu-book/products/foods/main-character/spicy-tongseng-lamb-chop.png'); ?>">
            </div>
        </div>

        <div class="menu-area">

            <h1 class="title-main">MAIN</h1>
            <h2 class="title-script">Character</h2>

            <div class="subtitle">
                Signature dishes, crafted with passion<br>
                to make every bite unforgettable.
            </div>

            <div class="ornament"></div>

            <div class="item no-table">
                <div class="menu-number">01</div>
                <div>
                    <div class="item-title">
                        NAMUA SULTAN RICE
                        <span class="badge">BEST SELLER</span>
                    </div>
                    <div class="desc">
                        Grillprawn, chicken satelilit, rendang beef shortplate,
                        garlic kemangi rice, mendoan, abon & sambal matah.
                    </div>
                </div>
                <div class="price">58K</div>
            </div>

            <div class="item table-item">
                <div class="menu-number">02</div>
                <div class="table-flex">
                    <div>
                        <div class="item-title">
                            BEBEK BUMBU<br>IRENG MADURA
                            <span class="badge">BEST SELLER</span>
                        </div>
                        <div class="desc">
                            Slow cook 6 hour duck boneless,
                            bumbu ireng paste madura,
                            sambal matah.
                        </div>
                    </div>

                    <table class="size-table">
                        <thead>
                            <tr>
                                <th>Size</th>
                                <th>A La Carte</th>
                                <th>Rice Set</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>JUMBO</td>
                                <td>58K</td>
                                <td>60K</td>
                            </tr>
                            <tr>
                                <td>REGULER</td>
                                <td>41K</td>
                                <td>43K</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="item no-table price-vertical">
                <div class="menu-number">03</div>
                <div>
                    <div class="item-title">
                        BEBEK SONGKEM NAMUA
                        <span class="badge">BEST SELLER</span>
                    </div>
                    <div class="desc">
                        Slow steamed 6 hour duck, with banana leaves and secret spices,
                        combined with base gede seasoning.
                    </div>
                </div>
                <div class="price-stack">
                    <div>
                        <small>A LA CARTE</small>
                        <strong>41K</strong>
                    </div>
                    <div>
                        <small>RICE SET</small>
                        <strong>43K</strong>
                    </div>
                </div>
            </div>

            <div class="item no-table price-vertical">
                <div class="menu-number">04</div>
                <div>
                    <div class="item-title">AYAM SONGKEM NAMUA</div>
                    <div class="desc">
                        Slow steamed 6 hour chicken, with banana leaves and secret spices,
                        combined with base gede seasoning.
                    </div>
                </div>
                <div class="price-stack">
                    <div>
                        <small>A LA CARTE</small>
                        <strong>30K</strong>
                    </div>
                    <div>
                        <small>RICE SET</small>
                        <strong>32K</strong>
                    </div>
                </div>
            </div>
            <div class="item no-table">
                <div class="menu-number">05</div>
                <div>
                    <div class="item-title">ARABIAN GRILL<br>LAMB CHOP</div>
                    <div class="desc">
                        Middle east cuisine pan grill lamb chop,
                        tar-tar mayo & fresh salad onion.
                    </div>
                </div>
                <div class="price">68K</div>
            </div>


            <div class="item no-table price-vertical">
                <div class="menu-number">06</div>
                <div>
                    <div class="item-title">SPICY TONGSENG<br>LAMB CHOP</div>
                    <div class="desc">
                        Pan grill lamb chop, spicy tongseng broth,
                        cabbage served with rice & condiment side dish.
                    </div>
                </div>
                <div class="price-stack">
                    <div>
                        <small>A LA CARTE</small>
                        <strong>66K</strong>
                    </div>
                    <div>
                        <small>RICE SET</small>
                        <strong>68K</strong>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="upgrade-box">
        <div class="upgrade-title">UPGRADE RICE</div>
        <div class="upgrade-item">
            <span>Base Gede Rice</span>
            <strong>+5K</strong>
        </div>
        <div class="upgrade-item">
            <span>Garlic Kemangi</span>
            <strong>+3K</strong>
        </div>
        <div class="upgrade-item">
            <span>Yakimezhi Rice</span>
            <strong>+10K</strong>
        </div>
        <div class="upgrade-item">
            <span>Arabian Rice</span>
            <strong>+9K</strong>
        </div>
    </div>

    <div class="footer">
        <span class="hashtag">#kembalikenamua</span>
        <span style="margin-right: 8mm;">|</span>
        <span class="ig">◎</span>
        @namuacoffee
    </div>

</div>

</body>
</html>