<?php
// The caller has already checked request/division access. Never fetch stock in a view.
$current = $stock_line['current_stock'] ?? null;
$warehouseOnly = !empty($stock_warehouse_only);
$escapeStock = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div style="min-width:140px;white-space:normal;line-height:1.5">
<?php if ($current === null): ?>
  <span class="text-muted">Tidak terkait bahan baku</span>
<?php else: ?>
  <?php foreach (($warehouseOnly ? ['warehouse'=>'Gudang'] : ['division'=>'Divisi','warehouse'=>'Gudang']) as $scopeKey=>$label): $balance = $current[$scopeKey]; ?>
    <div><span><?= $label ?>:</span> <strong><?= $balance['qty'] === null ? 'Belum diketahui' : $escapeStock(rtrim(rtrim(number_format((float)$balance['qty'],4,',','.'),'0'),',').' '.$current['uom']) ?></strong></div>
  <?php endforeach; ?>
  <?php if (!empty($current['warning'])): ?><div style="color:#8a4800;font-size:.85em;font-weight:600"><?= $escapeStock($current['warning']) ?></div><?php endif; ?>
  <div class="text-muted" style="font-size:.8em">Dibaca <?= $escapeStock($stock_line['stock_checked_at'] ?? '') ?></div>
<?php endif; ?>
</div>
