<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Asian Signatures - NAMUA</title>

<style>
@page { size: A4 portrait; margin: 0; }

* { box-sizing: border-box; }

html, body {
    margin: 0;
    padding: 0;
    background: #2a211b;
}

.page {
    width: 210mm;
    height: 297mm;
    margin: 0 auto;
    overflow: hidden;
    position: relative;
    background: url("<?= base_url('assets/menu-book/backgrounds/bg-asian.png'); ?>") center/cover no-repeat;
    font-family: Georgia, "Times New Roman", serif;
    color: #8d1f28;
    padding: 5mm;
}

.inner {
    width: 100%;
    height: 100%;
    border: 1.2px solid #b98a43;
    padding: 4mm 5mm 3mm;
    position: relative;
}

.inner::before {
    content: "";
    position: absolute;
    inset: 2mm;
    border: .6px solid rgba(141,31,40,.45);
    pointer-events: none;
}

.header {
    height: 24mm;
    text-align: center;
    position: relative;
    z-index: 2;
}

.header .script {
    font-size: 20px;
    font-style: italic;
    color: #330202;
    margin-bottom: 1mm;
}

.header h1 {
    margin: 0;
    font-size: 41px;
    line-height: .92;
    letter-spacing: 2px;
}

.header p {
    margin: 1.5mm 0 0;
    font-size: 12px;
    font-weight: bold;
    color: #5b3d25;
}

.content {
    position: relative;
    z-index: 2;
}

.section {
    border: 1px solid rgba(185,138,67,.85);
    border-radius: 4mm;
    overflow: hidden;
    background: rgba(255,250,236,.76);
    margin-bottom: 2.4mm;
}

.section-title {
    height: 10mm;
    padding: 1.7mm 4mm;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: rgba(255,248,227,.68);
    border-bottom: 1px solid rgba(185,138,67,.55);
}

.section-title h2 {
    margin: 0;
    font-size: 17px;
    line-height: 1;
    letter-spacing: .7px;
}

.section-title span {
    font-size: 5.7px;
    letter-spacing: 1.5px;
    color: #8b672f;
    border: 1px solid rgba(185,138,67,.65);
    border-radius: 20px;
    padding: 1mm 3mm;
    background: rgba(255,255,245,.8);
}

.hero {
    height: 67mm;
    display: grid;
    grid-template-columns: 66% 34%;
}

.hero-img {
    height: 67mm;
    overflow: hidden;
}

.hero-img img,
.food-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.broth-img img { object-position: center 48%; }
.roll-img img { object-position: center 52%; }

.panel {
    padding: 5mm 4.5mm;
    background: linear-gradient(135deg, rgba(255,250,235,.96), rgba(244,229,195,.88));
    border-left: 1px solid rgba(185,138,67,.58);
}

.badge {
    display: inline-block;
    margin-bottom: 3mm;
    padding: 1.2mm 3.6mm;
    border-radius: 20px;
    background: #b18539;
    color: #fff7d7;
    font-size: 12px;
    font-weight: bold;
    letter-spacing: 1.5px;
}

.item {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 2.5mm;
    padding: 1.65mm 0;
    border-bottom: 1px dotted rgba(70,45,24,.55);
}

.item:last-child {
    border-bottom: 0;
}

.item b {
    display: block;
    font-size: 12px;
    line-height: 1.1;
    letter-spacing: .4px;
}

.item small {
    display: block;
    margin-top: .6mm;
    font-size: 8px;
    line-height: 1.2;
    color: #4d3925;
}

.item strong {
    align-self: center;
    font-size: 14.5px;
    line-height: 1;
}

.wok {
    height: 86mm;
}

.wok-layout {
    height: 76mm;
    display: grid;
    grid-template-columns: 38% 62%;
    gap: 2.5mm;
    padding: 2.5mm;
}

.feature-card {
    height: 70mm;
    border: 1px solid rgba(185,138,67,.7);
    border-radius: 3mm;
    overflow: hidden;
    position: relative;
    align-self: center;
}

.feature-card .food-img {
    height: 100%;
}

.feature-card img {
    object-position: center center;
}

.feature-caption {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 8mm 3.5mm 2.5mm;
    color: #fff8e6;
    background: linear-gradient(transparent, rgba(30,22,13,.92));
}

.feature-caption h3 {
    margin: 0;
    font-size: 15px;
    letter-spacing: .4px;
}

.feature-caption p {
    margin: .8mm 0 0;
    font-size: 10px;
    line-height: 1.2;
}

.feature-caption .price {
    text-align: right;
    font-size: 17px;
    font-weight: bold;
    color: #ffe39b;
}

.mini-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2.5mm;
}

.mini-card {
    height: 33mm;
    overflow: hidden;
    border: 1px solid rgba(185,138,67,.7);
    border-radius: 3mm;
    background: rgba(255,250,238,.9);
}

.mini-card .food-img {
    height: 18mm;
}

.mini-card img {
    object-position: center 50%;
}

.mini-text {
    height: 15mm;
    padding: 1.6mm 2mm 1mm;
    position: relative;
}

.mini-text h3 {
    margin: 0;
    padding-right: 12mm;
    font-size: 12px;
    line-height: 1.05;
}

.mini-text p {
    margin: .6mm 0 0;
    padding-right: 11mm;
    font-size: 9px;
    line-height: 1.1;
    color: #4d3925;
}

.mini-price {
    position: absolute;
    right: 2mm;
    bottom: 1mm;
    font-size: 13px;
    font-weight: bold;
}

.footer {
    height: 10mm;
    text-align: center;
    color: #8d1f28;
    padding-top: .8mm;
}

.footer .hash {
    font-size: 13.5px;
    font-weight: bold;
    font-style: italic;
    line-height: 1;
}

.footer .ig {
    margin-top: .8mm;
    font-size: 8px;
    color: #5c3e25;
}

.footer svg {
    width: 9px;
    height: 9px;
    fill: #8d1f28;
    vertical-align: -1.5px;
    margin-right: 1mm;
}
</style>
</head>

<body>

<div class="page">
    <div class="inner">

        <div class="header">
            <div class="script">Asian</div>
            <h1>SIGNATURES</h1>
            <p>Bowls, rolls & wok favourites.</p>
        </div>

        <div class="content">

            <div class="section">
                <div class="section-title">
                    <h2>✤ BROTH & BOWL</h2>
                    <span>RAMEN · RAMYEON · GYUDON</span>
                </div>

                <div class="hero">
                    <div class="hero-img broth-img">
                        <img src="<?= base_url('assets/menu-book/products/foods/asian-signature/broth-bowl-combo.png'); ?>" alt="Broth Bowl Combo">
                    </div>

                    <div class="panel">
                        <div class="badge">BEST PICKS</div>

                        <div class="item">
                            <div><b>SOYU RAMEN</b><small>Classic soy ramen with chicken, egg, and scallions.</small></div>
                            <strong>38K</strong>
                        </div>

                        <div class="item">
                            <div><b>TORI PAITAN RAMEN</b><small>Creamy chicken broth ramen with tender chicken.</small></div>
                            <strong>35K</strong>
                        </div>

                        <div class="item">
                            <div><b>SPICY RAMYEON</b><small>Spicy Korean-style ramen with rich flavours.</small></div>
                            <strong>32K</strong>
                        </div>

                        <div class="item">
                            <div><b>GYU DON</b><small>Beef donburi with savoury sweet sauce.</small></div>
                            <strong>32K</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="section-title">
                    <h2>✤ ROLLS & KIMBAP</h2>
                    <span>SUSHI ROLLS · KOREAN WRAP</span>
                </div>

                <div class="hero">
                    <div class="hero-img roll-img">
                        <img src="<?= base_url('assets/menu-book/products/foods/asian-signature/rolls-kimbap-combo.png'); ?>" alt="Rolls Kimbap Combo">
                    </div>

                    <div class="panel">
                        <div class="badge">ROLL SELECTION</div>

                        <div class="item">
                            <div><b>KOREAN BEEF KIMBAB</b><small>Korean beef, pickled radish, carrot, spinach, egg.</small></div>
                            <strong>31K</strong>
                        </div>

                        <div class="item">
                            <div><b>CALIFORNIA ROLL</b><small>Crab stick, avocado, cucumber, mayonnaise.</small></div>
                            <strong>24K</strong>
                        </div>

                        <div class="item">
                            <div><b>VOLCANO ROLL</b><small>Spicy tuna, tempura crunch, avocado, spicy mayo.</small></div>
                            <strong>26K</strong>
                        </div>

                        <div class="item">
                            <div><b>YAKINIKU SUSHI ROLL</b><small>Grilled beef, lettuce, cucumber, teriyaki sauce.</small></div>
                            <strong>29K</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section wok">
                <div class="section-title">
                    <h2>✤ WOK & ASIAN PLATES</h2>
                    <span>NOODLES · FRIED RICE · THAI PLATE</span>
                </div>

                <div class="wok-layout">

                    <div class="feature-card">
                        <div class="food-img">
                            <img src="<?= base_url('assets/menu-book/products/foods/asian-signature/mie-ayam-namua.png'); ?>" alt="Mie Ayam Namua">
                        </div>
                        <div class="feature-caption">
                            <h3>MIE AYAM NAMUA</h3>
                            <p>Chicken noodles with sweet soy sauce, vegetables, and crispy wonton.</p>
                            <div class="price">28K</div>
                        </div>
                    </div>

                    <div class="mini-grid">

                        <div class="mini-card">
                            <div class="food-img">
                                <img src="<?= base_url('assets/menu-book/products/foods/asian-signature/chicken-nori-yakimeshi.png'); ?>" alt="Chicken Nori Yakimeshi">
                            </div>
                            <div class="mini-text">
                                <h3>CHICKEN NORI YAKIMESHI</h3>
                                <p>Japanese Fried Rice</p>
                                <div class="mini-price">28K</div>
                            </div>
                        </div>

                        <div class="mini-card">
                            <div class="food-img">
                                <img src="<?= base_url('assets/menu-book/products/foods/asian-signature/chinese-fried-noodles.png'); ?>" alt="Chinese Fried Noodles">
                            </div>
                            <div class="mini-text">
                                <h3>CHINESE FRIED NOODLES</h3>
                                <p>Wok Fried Noodles</p>
                                <div class="mini-price">25K</div>
                            </div>
                        </div>

                        <div class="mini-card">
                            <div class="food-img">
                                <img src="<?= base_url('assets/menu-book/products/foods/asian-signature/yang-chow-fried-rice.png'); ?>" alt="Yang Chow Fried Rice">
                            </div>
                            <div class="mini-text">
                                <h3>YANG CHOW FRIED RICE</h3>
                                <p>Chinese Style Fried Rice</p>
                                <div class="mini-price">25K</div>
                            </div>
                        </div>

                        <div class="mini-card">
                            <div class="food-img">
                                <img src="<?= base_url('assets/menu-book/products/foods/asian-signature/crispy-dory-ala-thai.png'); ?>" alt="Crispy Dory Ala Thai">
                            </div>
                            <div class="mini-text">
                                <h3>CRISPY DORY ALA THAI</h3>
                                <p>Thai Inspired Dory Plate</p>
                                <div class="mini-price">25K</div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="footer">
                <div class="hash">#kembalikenamua</div>
                <div class="ig">
                    <svg viewBox="0 0 24 24">
                        <path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3H7zm5 3.5A5.5 5.5 0 1 1 12 18.5 5.5 5.5 0 0 1 12 7.5zm0 2A3.5 3.5 0 1 0 12 16.5 3.5 3.5 0 0 0 12 9.5zM18 6.4a1.2 1.2 0 1 1-1.2 1.2A1.2 1.2 0 0 1 18 6.4z"/>
                    </svg>
                    @namuacoffee
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>