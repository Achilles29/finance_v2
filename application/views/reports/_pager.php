<div class="rw-pager">
  <span><?= $number($r['pager']['count']) ?> baris sesuai filter. Halaman <?= (int)$r['pager']['page'] ?> / <?= (int)$r['pager']['pages'] ?>. Ringkasan menghitung seluruh hasil, bukan hanya halaman ini.</span>
  <div>
    <?php if($r['pager']['page']>1): ?><a class="rw-button" href="<?= $esc($url(['view'=>in_array($f['view'],['sales','waste'],true)?$f['view']:'detail','page'=>$r['pager']['page']-1])) ?>">Sebelumnya</a><?php endif ?>
    <?php if($r['pager']['page']<$r['pager']['pages']): ?><a class="rw-button" href="<?= $esc($url(['view'=>in_array($f['view'],['sales','waste'],true)?$f['view']:'detail','page'=>$r['pager']['page']+1])) ?>">Berikutnya</a><?php endif ?>
  </div>
</div>
