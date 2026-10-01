<?php
$stockSummaryCards = is_array($stock_summary_cards ?? null) ? $stock_summary_cards : [];
$stockSummaryLabel = trim((string)($stock_summary_label ?? 'Ringkasan stok'));
$allowedTones = ['violet', 'aqua', 'blue', 'amber', 'teal', 'danger'];
?>
<?php if ($stockSummaryCards !== []): ?>
<style>
  .stock-summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(155px,1fr)); gap:.65rem; margin:0 0 1rem; }
  .stock-summary-card { min-width:0; min-height:126px; position:relative; overflow:hidden; border:0; border-radius:14px; box-shadow:0 4px 18px rgba(0,0,0,.13); color:#fff; padding:1rem 1.1rem .9rem; }
  .stock-summary-card::before { content:''; position:absolute; right:-18px; bottom:-18px; width:80px; height:80px; border-radius:50%; background:rgba(255,255,255,.13); }
  .stock-summary-card::after { content:''; position:absolute; right:14px; top:-22px; width:56px; height:56px; border-radius:50%; background:rgba(255,255,255,.09); }
  .stock-summary-card.is-violet { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); }
  .stock-summary-card.is-aqua { background:linear-gradient(135deg,#0c7cba 0%,#0fcdba 100%); }
  .stock-summary-card.is-blue { background:linear-gradient(135deg,#1c7ed6 0%,#74c0fc 100%); }
  .stock-summary-card.is-amber { background:linear-gradient(135deg,#e06c00 0%,#f7b733 100%); }
  .stock-summary-card.is-teal { background:linear-gradient(135deg,#134e5e 0%,#38b2a3 100%); }
  .stock-summary-card.is-danger { background:linear-gradient(135deg,#b22222 0%,#e05252 100%); }
  .stock-summary-card__content { position:relative; z-index:1; }
  .stock-summary-card__icon { display:block; min-height:1.25rem; font-size:1.25rem; line-height:1; opacity:.82; }
  .stock-summary-card__label { display:block; margin-top:.45rem; font-size:.68rem; font-weight:800; letter-spacing:.06em; line-height:1.25; opacity:.84; text-transform:uppercase; }
  .stock-summary-card__value { display:block; margin-top:.18rem; font-size:1.42rem; font-weight:800; line-height:1.15; overflow-wrap:anywhere; }
  .stock-summary-card__detail { display:block; min-height:1em; margin-top:.22rem; font-size:.72rem; line-height:1.3; opacity:.78; }
  @media (max-width:575px) { .stock-summary-grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:.5rem; } .stock-summary-card { min-height:116px; padding:.8rem; } .stock-summary-card__value { font-size:1.18rem; } }
</style>
<section class="stock-summary-grid" aria-label="<?php echo html_escape($stockSummaryLabel); ?>">
  <?php foreach ($stockSummaryCards as $summaryCard): ?>
    <?php
      $tone = strtolower(trim((string)($summaryCard['tone'] ?? 'violet')));
      if (!in_array($tone, $allowedTones, true)) {
        $tone = 'violet';
      }
      $label = (string)($summaryCard['label'] ?? '-');
      $value = (string)($summaryCard['value'] ?? '0');
      $detail = trim((string)($summaryCard['detail'] ?? ''));
      $icon = trim((string)($summaryCard['icon'] ?? 'ri-bar-chart-box-line'));
    ?>
    <article class="stock-summary-card is-<?php echo html_escape($tone); ?>">
      <div class="stock-summary-card__content">
        <span class="stock-summary-card__icon" aria-hidden="true"><i class="ri <?php echo html_escape($icon); ?>"></i></span>
        <span class="stock-summary-card__label"><?php echo html_escape($label); ?></span>
        <strong class="stock-summary-card__value"><?php echo html_escape($value); ?></strong>
        <?php if ($detail !== ''): ?><span class="stock-summary-card__detail"><?php echo html_escape($detail); ?></span><?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</section>
<?php endif; ?>
