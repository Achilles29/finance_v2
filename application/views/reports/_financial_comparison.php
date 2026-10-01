<?php
$sources=$r['matrix'];
if($s['available']){
    $values=array_column($r['monthly'],'omzet','label');
    array_unshift($sources,['key'=>'OMZET','code'=>'OMZET','label'=>'Omzet POS neto','flow'=>'revenue','direction'=>'NETO','values'=>$values,'total'=>array_sum($values)]);
}
$sourceCharts=[];
foreach($sources as $source){
    $chartRows=[];
    foreach($source['values'] as $month=>$amount){
        $link=$source['key']==='OMZET'?['view'=>'sales']:['view'=>'detail','category'=>$source['code'],'direction'=>$source['direction']];
        $chartRows[]=['label'=>$month,'amount'=>$amount,'url'=>$url(array_replace($link,['month'=>$month,'day'=>'','page'=>1])), 'note'=>$month===date('Y-m')?'Realisasi sampai '.date('d-m-Y'):''];
    }
    $sourceCharts[$source['key']]=['type'=>'line','title'=>$source['label'].' / '.$source['direction'],'currency'=>$f['currency'],'rows'=>$chartRows,'series'=>[['key'=>'amount','label'=>$source['label'].' / '.$source['direction'],'color'=>$source['direction']==='OUT'?'#8d2945':'#11786d']]];
}
$initial=$sources?array_key_first($sourceCharts):null;
?>
<article class="rw-panel rw-comparison" id="financial-comparison"><div class="rw-section-head"><div><span class="rw-eyebrow">Perbandingan lintas bulan</span><h2>Urai sampai sumbernya</h2><p class="rw-sub">Satu sumber, satu arah, berjajar setiap bulan. Kategori bulan lalu tetap terlihat meskipun bulan ini nol.</p></div><span class="rw-period-tag"><?= count($months) ?> bulan / <?= $esc($f['currency']) ?></span></div>
  <?php if($initial!==null): ?>
  <label class="rw-source-control">Grafik sumber<select id="finance-source-select" data-report-source="finance-source-chart" data-report-source-options="finance-source-data"><?php foreach($sources as $source): ?><option value="<?= $esc($source['key']) ?>"><?= $esc($source['label'].' / '.$source['direction']) ?></option><?php endforeach ?></select></label>
  <div class="rw-chart" data-report-chart="finance-source-chart"></div>
  <script type="application/json" id="finance-source-chart"><?= $json($sourceCharts[$initial]) ?></script><script type="application/json" id="finance-source-data"><?= $json($sourceCharts) ?></script>
  <p class="rw-sub">Klik nominal untuk rincian bulan itu, atau tombol tren untuk mengganti grafik. Selisih = bulan fokus <?= $esc($f['month']) ?> dikurangi <?= $esc($f['compare']) ?>; bulan berjalan belum penuh. Baris omzet adalah basis penjualan, tidak ditambahkan lagi ke total kas.</p>
  <div class="rw-table-wrap"><table class="rw-compare-table"><thead><tr><th>Sumber / arah</th><?php foreach($months as $month): ?><th class="<?= $month===$f['month']?'rw-focus-month':'' ?>"><a href="<?= $esc($url(['month'=>$month,'day'=>'','page'=>1])) ?>"><?= $esc($month) ?></a><?php if($month===date('Y-m')): ?><small>s.d. <?= date('d/m') ?></small><?php endif ?></th><?php endforeach ?><th>Total rentang</th><th>Selisih bulan fokus</th><th>Tren</th></tr></thead><tbody>
    <?php foreach($sources as $source): ?>
    <tr class="<?= $source['key']==='OMZET'?'rw-revenue-row':'' ?>"><td><strong><?= $esc($source['label']) ?></strong><small><?= $esc($source['key']==='OMZET'?'Penjualan / bukan kas':$r['flow_options'][$source['flow']]) ?> <span class="rw-pill <?= $source['direction']==='OUT'?'out':'' ?>"><?= $source['direction']==='IN'?'MASUK':($source['direction']==='OUT'?'KELUAR':'NETO') ?></span></small></td>
      <?php foreach($source['values'] as $month=>$amount): $link=$source['key']==='OMZET'?['view'=>'sales']:['view'=>'detail','category'=>$source['code'],'direction'=>$source['direction']]; ?><td class="rw-num <?= $month===$f['month']?'rw-focus-month':'' ?>"><?php if($amount!==0): ?><a href="<?= $esc($url(array_replace($link,['month'=>$month,'day'=>'','page'=>1]))) ?>"><?= $money($amount) ?></a><?php else: ?><span class="rw-zero">0,00</span><?php endif ?></td><?php endforeach ?>
      <td class="rw-num"><strong><?= $money($source['total']) ?></strong></td><td class="rw-num"><?php if(array_key_exists($f['compare'],$source['values'])): $delta=$source['values'][$f['month']]-$source['values'][$f['compare']]; ?><?= $delta>0?'+':'' ?><?= $money($delta) ?><small><?= $esc(Report_workspace::change($source['values'][$f['month']],$source['values'][$f['compare']])) ?></small><?php else: ?><small>Pembanding di luar rentang</small><?php endif ?></td><td><button type="button" class="rw-spark-button" data-source-pick="<?= $esc($source['key']) ?>" aria-label="Lihat tren <?= $esc($source['label'].' / '.$source['direction']) ?>"><span data-report-spark="<?= $esc($json(array_values($source['values']))) ?>"></span><span>Tren</span></button></td>
    </tr><?php endforeach ?>
  </tbody><tfoot><?php foreach(['in'=>'Total kas masuk (termasuk transfer)','out'=>'Total kas keluar (termasuk transfer)'] as $direction=>$label): $total=0; ?><tr><td><?= $label ?></td><?php foreach($r['monthly'] as $month): $amount=$month[$direction]+$month['transfer_'.$direction];$total+=$amount; ?><td class="rw-num"><?= $money($amount) ?></td><?php endforeach ?><td class="rw-num"><?= $money($total) ?></td><td colspan="2">Omzet tidak dijumlahkan kembali</td></tr><?php endforeach ?></tfoot></table></div>
  <?php else: ?><p class="rw-empty">Belum ada sumber transaksi pada rentang ini.</p><?php endif ?>
</article>
