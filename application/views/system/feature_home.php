<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="card p-4 mb-3">
  <h1 class="h4">Selamat datang</h1>
  <p>Paket Anda: <strong><?= html_escape($features->editionName()) ?></strong>. Pilih pekerjaan melalui sidebar sesuai hak akses akun Anda.</p>
  <p>Menu bertanda Upgrade tetap terlihat. Data dan tindakan di dalamnya tersedia setelah paket ditingkatkan.</p>
  <a class="btn btn-outline-primary align-self-start" href="<?= site_url('system/feature-access') ?>">Lihat fitur paket / informasi upgrade</a>
</section>
