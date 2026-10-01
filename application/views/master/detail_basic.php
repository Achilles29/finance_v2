<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="card p-4">
  <h1 class="h4"><?= html_escape($title) ?></h1>
  <dl><?php foreach ($row as $key=>$value): ?><dt><?= html_escape(ucwords(str_replace('_',' ', $key))) ?></dt><dd><?= html_escape((string)$value) ?></dd><?php endforeach; ?></dl>
  <a href="<?= site_url('master/'.$entity) ?>">Kembali ke daftar</a>
</section>
