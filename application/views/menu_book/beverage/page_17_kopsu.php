<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kopi Susu Literan - NAMUA</title>

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
    background:#222;
}

.page{
    width:210mm;
    height:297mm;
    margin:auto;
    overflow:hidden;
    position:relative;
    background:#fff;
}

.full-image{
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
        class="full-image"
        src="<?= base_url('assets/menu-book/products/beverages/literan/kopi-susu-literan.png'); ?>"
        alt="Kopi Susu Literan">
</div>

</body>
</html>