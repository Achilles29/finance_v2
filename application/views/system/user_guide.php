<?php
$esc = static function ($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
$guideUrl = site_url('guide');
$url = static function (array $changes = []) use ($params, $guideUrl): string {
    $query = array_filter(array_merge($params, $changes), static function ($v): bool { return $v !== ''; });
    return $guideUrl.($query ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
};
$ids = array_keys($chapters);
$position = array_search($selected, $ids, true);
?>
<link rel="stylesheet" href="<?= $esc(base_url('assets/css/finance-user-guide.css')) ?>">
<main class="ug-shell" id="finance-user-guide">
  <header class="ug-hero">
    <div class="ug-eyebrow">PUSAT BANTUAN · FINANCE</div>
    <h1>Panduan Aplikasi</h1>
    <p>Dari menyiapkan usaha hingga menutup bulan. Pilih tugas Anda, lalu ikuti langkahnya satu per satu.</p>
    <div class="ug-meta">Versi sumber: <?= $esc($release_label) ?> · Panduan edisi <?= $esc($guide_edition) ?> · Ditinjau <?= $esc($guide_reviewed) ?></div>
  </header>

  <nav class="ug-tabs ug-no-print" aria-label="Topik panduan">
    <a href="<?= $esc($url(['category'=>'','article'=>'','q'=>''])) ?>" <?= $params['category']==='' ? 'aria-current="page"' : '' ?>>Semua topik</a>
    <?php foreach ($categories as $key=>$label): ?>
    <a href="<?= $esc($url(['category'=>$key,'article'=>'','q'=>'','audience'=>''])) ?>" <?= $params['category']===$key ? 'aria-current="page"' : '' ?>><?= $esc($label) ?></a>
    <?php endforeach; ?>
  </nav>

  <form action="<?= $esc($guideUrl) ?>" method="get" class="ug-filters ug-no-print" role="search">
    <input type="hidden" name="category" value="<?= $esc($params['category']) ?>">
    <div><label for="ug-search">Cari kebutuhan Anda</label><input id="ug-search" type="search" name="q" value="<?= $esc($params['q']) ?>" maxlength="160" placeholder="Contoh: logo, selisih kas, PH…"></div>
    <div><label for="ug-audience">Saya bertugas sebagai</label><select id="ug-audience" name="audience"><option value="">Semua peran</option><?php foreach ($audiences as $key=>$label): ?><option value="<?= $esc($key) ?>" <?= $params['audience']===$key ? 'selected' : '' ?>><?= $esc($label) ?></option><?php endforeach; ?></select></div>
    <button type="submit" class="ug-button ug-primary">Cari panduan</button>
    <a class="ug-reset" href="<?= $esc($guideUrl) ?>">Reset</a>
  </form>

  <?php if (!$article): ?>
  <section class="ug-empty" role="status"><h2>Belum ada bab yang cocok</h2><p>Coba kata yang lebih singkat atau pilih semua peran/topik.</p><a href="<?= $esc($guideUrl) ?>" class="ug-button">Tampilkan semua panduan</a></section>
  <?php else: ?>
  <div class="ug-layout">
    <aside class="ug-navigation ug-no-print" aria-label="Daftar bab">
      <div class="ug-nav-title"><?= count($chapters) ?> bab tersedia</div>
      <nav class="ug-desktop-chapters" aria-label="Pilih bab">
        <?php foreach ($chapters as $id=>$title): ?><a href="<?= $esc($url(['article'=>$id])) ?>" <?= $selected===$id ? 'aria-current="page"' : '' ?>><?= $esc($title) ?></a><?php endforeach; ?>
      </nav>
      <form action="<?= $esc($guideUrl) ?>" method="get" class="ug-mobile-chapters">
        <?php foreach (['category','audience','q'] as $key): ?><input type="hidden" name="<?= $key ?>" value="<?= $esc($params[$key]) ?>"><?php endforeach; ?>
        <label for="ug-chapter">Pilih bab</label>
        <select name="article" id="ug-chapter"><?php foreach ($chapters as $id=>$title): ?><option value="<?= $esc($id) ?>" <?= $selected===$id ? 'selected' : '' ?>><?= $esc($title) ?></option><?php endforeach; ?></select>
        <button class="ug-button" type="submit">Buka bab</button>
      </form>
    </aside>

    <article class="ug-article" aria-labelledby="ug-article-title">
      <div class="ug-article-meta"><span class="ug-badge"><?= $esc($categories[$article['category']]) ?></span><span>Bab <?= $position + 1 ?> dari <?= count($chapters) ?> hasil</span><button type="button" class="ug-print ug-no-print" data-guide-print hidden>Cetak bab ini</button></div>
      <h2 id="ug-article-title"><?= $esc($article['title']) ?></h2>
      <p class="ug-lead"><?= $esc($article['summary']) ?></p>
      <p class="ug-for">Untuk: <?= $esc(implode(', ', array_intersect_key($audiences, array_flip($article['audiences'])))) ?></p>
      <?php if ($article['category']==='server'): ?><div class="ug-server-note">Khusus admin server · Petunjuk dibaca dan dikerjakan terpisah oleh admin. Tidak ada perintah yang dijalankan dari halaman ini.</div><?php endif; ?>
      <h3>Langkah yang dilakukan</h3>
      <ol class="ug-steps"><?php foreach ($article['steps'] as $step): ?><li><?= $esc($step) ?></li><?php endforeach; ?></ol>
      <?php foreach ($article['commands'] as $i=>$command): ?>
      <section class="ug-command"><h3><?= $esc($command['label']) ?></h3><pre tabindex="0" aria-label="<?= $esc($command['label']) ?>"><code id="ug-code-<?= $i ?>"><?= $esc($command['code']) ?></code></pre><button type="button" class="ug-button ug-no-print" data-guide-copy="ug-code-<?= $i ?>" hidden>Salin contoh</button></section>
      <?php endforeach; ?>
      <section class="ug-result"><h3>✓ Hasil yang perlu Anda cek</h3><p><?= $esc($article['check']) ?></p></section>
      <?php if ($article['warning']!==''): ?><section class="ug-warning"><h3>Perhatikan sebelum melanjutkan</h3><p><?= $esc($article['warning']) ?></p></section><?php endif; ?>
      <?php if ($article['links']): ?><section class="ug-related ug-no-print"><h3>Halaman terkait</h3><div><?php foreach ($article['links'] as $item): ?>
        <?php if ($item['allowed']): ?><a class="ug-button" href="<?= $esc(site_url($item['path'])) ?>">Buka <?= $esc($item['label']) ?> →</a><?php else: ?><span class="ug-unavailable"><?= $esc($item['label']) ?> — minta akses pengelola</span><?php endif; ?>
      <?php endforeach; ?></div></section><?php endif; ?>
      <nav class="ug-pager ug-no-print" aria-label="Urutan bab">
        <?php if ($position > 0): ?><a class="ug-button" href="<?= $esc($url(['article'=>$ids[$position-1]])) ?>">← Sebelumnya</a><?php endif; ?>
        <?php if ($position < count($ids)-1): ?><a class="ug-button ug-primary" href="<?= $esc($url(['article'=>$ids[$position+1]])) ?>">Bab berikutnya →</a><?php endif; ?>
      </nav>
    </article>
  </div>
  <?php endif; ?>
  <p class="ug-footnote">Panduan bukan status instalasi atau tanda otomatis lulus uji. Menu/fitur mengikuti izin, paket dan versi yang terpasang. <?php if (!$server_access): ?>Bab admin server memerlukan izin tersendiri.<?php endif; ?></p>
  <p class="ug-copy-status ug-no-print" data-guide-status role="status" aria-live="polite"></p>
</main>
<script src="<?= $esc(base_url('assets/js/finance-user-guide.js')) ?>" defer></script>
