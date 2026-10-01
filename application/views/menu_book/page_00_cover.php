<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cover Menu Book - NAMUA</title>

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
    background:#fff;
}

.cover-img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}
</style>
</head>

<body>
<div class="page">
    <img
        class="cover-img"
        src="<?= base_url('assets/menu-book/covers/cover-namua.png'); ?>"
        alt="NAMUA Coffee & Eatery">
</div>
</body>
</html>