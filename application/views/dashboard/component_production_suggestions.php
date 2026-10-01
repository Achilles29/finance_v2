<?php
$analysis = is_array($analysis ?? null) ? $analysis : ['summary' => [], 'rows' => [], 'division_options' => []];
$summary = is_array($analysis['summary'] ?? null) ? $analysis['summary'] : [];
$rows = is_array($analysis['rows'] ?? null) ? $analysis['rows'] : [];
$divisionOptions = is_array($analysis['division_options'] ?? null) ? $analysis['division_options'] : [];
$filterDivisionId = (int)($filter_division_id ?? 0);
$monthKey = (string)($analysis['month_key'] ?? date('Y-m-01'));
$monthLabel = date('F Y', strtotime($monthKey));
$productionSuggestionDivisions = [];
foreach ($rows as $productionRow) {
    $divisionId = (int)($productionRow['division_id'] ?? 0);
    $divisionKey = $divisionId > 0 ? 'division-' . $divisionId : 'division-other';
    if (!isset($productionSuggestionDivisions[$divisionKey])) {
        $productionSuggestionDivisions[$divisionKey] = [
            'label' => trim((string)($productionRow['division_name'] ?? '')) ?: 'Lainnya',
            'count' => 0,
        ];
    }
    $productionSuggestionDivisions[$divisionKey]['count']++;
}
uasort($productionSuggestionDivisions, static function (array $left, array $right): int {
    return strnatcasecmp((string)$left['label'], (string)$right['label']);
});
$initialProductionSuggestionTab = 'ALL';
if ($filterDivisionId > 0 && isset($productionSuggestionDivisions['division-' . $filterDivisionId])) {
    $initialProductionSuggestionTab = 'division-' . $filterDivisionId;
}
$fmtQty = static function ($value): string {
    $value = (float)$value;
    return abs($value - round($value)) < 0.0001
        ? number_format($value, 0, ',', '.')
        : number_format($value, 2, ',', '.');
};
?>

<style>
  .production-suggestions { color:#48312b; }
  .production-suggestions .ps-hero { position:relative; overflow:hidden; border:1px solid #ead8cd; border-radius:20px; padding:1.25rem 1.35rem; background:linear-gradient(135deg,#fffaf4 0%,#fff 55%,#f5fbf4 100%); box-shadow:0 10px 30px rgba(88,55,40,.06); }
  .production-suggestions .ps-hero:after { content:""; position:absolute; width:210px; height:210px; border-radius:50%; right:-78px; top:-116px; background:radial-gradient(circle,rgba(79,136,79,.16),rgba(79,136,79,0)); pointer-events:none; }
  .production-suggestions .ps-kicker { color:#8f2d23; font-size:.72rem; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
  .production-suggestions h1 { margin:.22rem 0 .35rem; color:#60241c; font-size:1.5rem; font-weight:900; }
  .production-suggestions .ps-desc { max-width:850px; margin:0; color:#7e6860; font-size:.9rem; line-height:1.5; }
  .production-suggestions .ps-note { margin-top:1rem; border:1px solid #f1dfaa; border-radius:14px; background:#fff9e8; color:#796132; padding:.68rem .82rem; font-size:.82rem; line-height:1.45; }
  .production-suggestions .ps-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; margin:1rem 0; }
  .production-suggestions .ps-stat { min-height:94px; border:1px solid #eaded6; border-radius:16px; background:#fff; padding:.8rem .9rem; box-shadow:0 5px 16px rgba(88,55,40,.045); }
  .production-suggestions .ps-stat b { display:block; font-size:1.45rem; line-height:1; color:#60241c; }
  .production-suggestions .ps-stat b.red { color:#c62828; }
  .production-suggestions .ps-stat b.orange { color:#e66d00; }
  .production-suggestions .ps-stat b.green { color:#2e7d32; }
  .production-suggestions .ps-stat span { display:block; margin-top:.35rem; color:#917b72; font-size:.72rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
  .production-suggestions .ps-card { border:1px solid #eaded6; border-radius:20px; background:#fff; box-shadow:0 10px 25px rgba(88,55,40,.05); overflow:hidden; }
  .production-suggestions .ps-card-head { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; padding:1rem 1.1rem .82rem; border-bottom:1px solid #f1e6e0; }
  .production-suggestions .ps-card-head h2 { margin:0; color:#60241c; font-size:1rem; font-weight:900; }
  .production-suggestions .ps-card-head p { margin:.2rem 0 0; color:#907a72; font-size:.78rem; }
  .production-suggestions .ps-tabs { display:flex; gap:.45rem; flex-wrap:wrap; padding:.76rem 1.1rem; border-bottom:1px solid #f1e6e0; background:#fffaf7; }
  .production-suggestions .ps-tab { border:1px solid #dfbeb4; border-radius:999px; background:#fff; color:#8f2d23; padding:.38rem .7rem; font-size:.75rem; font-weight:900; cursor:pointer; }
  .production-suggestions .ps-tab:hover { background:#fff0ea; }
  .production-suggestions .ps-tab.active { border-color:#aa1224; background:#aa1224; color:#fff; box-shadow:0 4px 12px rgba(170,18,36,.18); }
  .production-suggestions .ps-tab span { font-size:.68rem; opacity:.88; }
  .production-suggestions .ps-btn { display:inline-flex; justify-content:center; align-items:center; gap:.35rem; border:1px solid #bc3b30; border-radius:10px; background:#c94236; color:#fff; padding:.48rem .76rem; font-size:.78rem; font-weight:900; text-decoration:none; white-space:nowrap; }
  .production-suggestions .ps-btn:hover { background:#a72c24; border-color:#a72c24; color:#fff; }
  .production-suggestions .ps-btn.light { border-color:#d7bbb0; background:#fff; color:#8f2d23; }
  .production-suggestions .ps-btn.light:hover { background:#fff0ea; color:#8f2d23; }
  .production-suggestions .ps-table-scroll { max-height:68vh; overflow:auto; }
  .production-suggestions table { min-width:1050px; width:100%; border-collapse:separate; border-spacing:0; }
  .production-suggestions th { position:sticky; top:0; z-index:3; padding:.7rem .75rem; background:#aa1224; color:#fff; font-size:.71rem; font-weight:900; letter-spacing:.035em; text-align:left; text-transform:uppercase; }
  .production-suggestions td { padding:.82rem .75rem; border-bottom:1px solid #efe2db; vertical-align:top; font-size:.82rem; }
  .production-suggestions tr:last-child td { border-bottom:0; }
  .production-suggestions tbody tr:hover td { background:#fffaf6; }
  .production-suggestions .ps-name { color:#53241e; font-size:.9rem; font-weight:900; }
  .production-suggestions .ps-meta { margin-top:.18rem; color:#927d75; font-size:.72rem; line-height:1.42; }
  .production-suggestions .ps-badge { display:inline-flex; align-items:center; gap:.25rem; border-radius:999px; padding:.17rem .48rem; background:#fff0ea; color:#8f2d23; font-size:.65rem; font-weight:900; text-transform:uppercase; }
  .production-suggestions .ps-badge.empty { background:#fff0f0; color:#c62828; }
  .production-suggestions .ps-badge.ready { background:#effaf0; color:#287035; }
  .production-suggestions .ps-badge.waiting { background:#fff4df; color:#b45c00; }
  .production-suggestions .ps-ingredient { display:block; padding:.38rem .48rem; border:1px solid #e8ded7; border-radius:10px; background:#fffdfb; color:#5b3930; font-size:.75rem; line-height:1.35; }
  .production-suggestions .ps-ingredient + .ps-ingredient { margin-top:.34rem; }
  .production-suggestions .ps-ingredient b { font-weight:900; }
  .production-suggestions .ps-output { color:#2e7d32; font-size:.78rem; font-weight:900; line-height:1.4; }
  .production-suggestions .ps-output.waiting { color:#b65c00; }
  .production-suggestions .ps-actions { display:flex; flex-wrap:wrap; gap:.35rem; }
  .production-suggestions .ps-empty { padding:2.1rem 1rem; color:#8d7770; font-size:.88rem; text-align:center; }
  @media (max-width:991.98px) { .production-suggestions .ps-stats { grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media (max-width:575.98px) { .production-suggestions .ps-stats { grid-template-columns:1fr; } }
</style>

<main class="production-suggestions">
  <section class="ps-hero">
    <div class="ps-kicker"><i class="ri-restaurant-2-line"></i> Kontrol Produksi</div>
    <h1>Analisa &amp; Saran Produksi</h1>
    <p class="ps-desc">Halaman ini mencari base dan prepare yang stoknya kosong atau kritis, lalu menghubungkannya dengan bahan resep yang masih tersedia di lokasi kerja yang sama.</p>
    <div class="ps-note"><strong>Ini hanya saran, bukan posting batch.</strong> Sistem tidak menambah, mengurangi, atau memindahkan stok dari halaman ini. Status <em>Siap diproses</em> baru muncul jika semua bahan resep saat ini mencukupi.</div>
  </section>

  <section class="ps-stats">
    <article class="ps-stat"><b class="red"><?= number_format((int)($summary['empty_component_count'] ?? 0), 0, ',', '.') ?></b><span>Component kosong</span></article>
    <article class="ps-stat"><b class="orange"><?= number_format((int)($summary['critical_component_count'] ?? 0), 0, ',', '.') ?></b><span>Di bawah minimum</span></article>
    <article class="ps-stat"><b class="green"><?= number_format((int)($summary['ready_count'] ?? 0), 0, ',', '.') ?></b><span>Siap diproses</span></article>
    <article class="ps-stat"><b><?= number_format((int)($summary['waiting_count'] ?? 0), 0, ',', '.') ?></b><span>Masih menunggu bahan</span></article>
  </section>

  <section class="ps-card">
    <header class="ps-card-head">
      <div>
        <h2>Saran produksi bulan aktif: <?= htmlspecialchars($monthLabel) ?></h2>
        <p>Bahan disebut menumpuk bila sendiri sudah cukup untuk minimal dua batch. Stok gudang atau lokasi lain tidak disamakan dengan stok divisi/lokasi formula.</p>
      </div>
      <span class="ps-badge"><?= number_format((int)($analysis['total_rows'] ?? 0), 0, ',', '.') ?> saran</span>
    </header>

    <?php if (empty($rows)): ?>
      <div class="ps-empty">Belum ada saran produksi pada pilihan ini. Component kosong/kritis akan muncul jika bahan resep di lokasi yang sama cukup untuk minimal dua batch.</div>
    <?php else: ?>
      <div class="ps-tabs" id="productionSuggestionPageTabs" aria-label="Pilih divisi saran produksi">
        <button type="button" class="ps-tab <?= $initialProductionSuggestionTab === 'ALL' ? 'active' : '' ?>" data-production-suggestion-page-tab="ALL" aria-pressed="<?= $initialProductionSuggestionTab === 'ALL' ? 'true' : 'false' ?>">
          Semua <span>(<?= count($rows) ?>)</span>
        </button>
        <?php foreach ($productionSuggestionDivisions as $divisionKey => $divisionSummary): ?>
          <button type="button" class="ps-tab <?= $initialProductionSuggestionTab === $divisionKey ? 'active' : '' ?>" data-production-suggestion-page-tab="<?= htmlspecialchars((string)$divisionKey, ENT_QUOTES) ?>" aria-pressed="<?= $initialProductionSuggestionTab === $divisionKey ? 'true' : 'false' ?>">
            <?= htmlspecialchars((string)$divisionSummary['label']) ?> <span>(<?= (int)$divisionSummary['count'] ?>)</span>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="ps-table-scroll">
        <table>
          <thead>
            <tr>
              <th>Component</th>
              <th>Stok Component</th>
              <th>Bahan yang Tersedia</th>
              <th>Kesiapan Formula</th>
              <th>Saran</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <?php
              $isReady = (string)($row['production_state'] ?? '') === 'READY';
              $piledIngredients = array_slice((array)($row['piled_ingredients'] ?? []), 0, 3);
              $blockingIngredients = array_slice((array)($row['blocking_ingredients'] ?? []), 0, 3);
              $blockingNames = array_values(array_filter(array_map(static function (array $ingredient): string {
                  return trim((string)($ingredient['name'] ?? ''));
              }, $blockingIngredients)));
              $batchUrl = site_url('production/component-batches')
                  . '?q=' . rawurlencode((string)($row['component_name'] ?? ''))
                  . '&division_id=' . (int)($row['division_id'] ?? 0);
               $formulaUrl = site_url('production/component-formulas/detail/' . (int)($row['component_id'] ?? 0));
               $divisionId = (int)($row['division_id'] ?? 0);
               $divisionTabKey = $divisionId > 0 ? 'division-' . $divisionId : 'division-other';
               ?>
              <tr data-production-suggestion-page-division="<?= htmlspecialchars($divisionTabKey, ENT_QUOTES) ?>" <?= $initialProductionSuggestionTab !== 'ALL' && $initialProductionSuggestionTab !== $divisionTabKey ? 'hidden' : '' ?>>
                <td>
                  <span class="ps-badge <?= (string)($row['stock_state'] ?? '') === 'EMPTY' ? 'empty' : '' ?>"><?= htmlspecialchars((string)($row['component_type'] ?? 'COMPONENT')) ?> &middot; <?= (string)($row['stock_state'] ?? '') === 'EMPTY' ? 'Kosong' : 'Kritis' ?></span>
                  <div class="ps-name" style="margin-top:.35rem"><?= htmlspecialchars((string)($row['component_name'] ?? '-')) ?></div>
                  <div class="ps-meta"><?= htmlspecialchars((string)($row['division_name'] ?? '-')) ?> &middot; <?= htmlspecialchars((string)($row['location_label'] ?? '-')) ?></div>
                </td>
                <td>
                  <div class="ps-name" style="font-size:.85rem"><?= $fmtQty($row['current_qty'] ?? 0) ?> <?= htmlspecialchars((string)($row['uom_code'] ?? '')) ?></div>
                  <div class="ps-meta">Minimum <?= $fmtQty($row['min_stock'] ?? 0) ?><?= $row['uom_code'] !== '' ? ' ' . htmlspecialchars((string)$row['uom_code']) : '' ?><br>Hasil satu batch <?= $fmtQty($row['yield_qty'] ?? 0) ?> <?= htmlspecialchars((string)($row['uom_code'] ?? '')) ?></div>
                </td>
                <td>
                  <?php foreach ($piledIngredients as $ingredient): ?>
                    <span class="ps-ingredient"><b><?= htmlspecialchars((string)($ingredient['name'] ?? '-')) ?></b><br><?= $fmtQty($ingredient['stock_qty'] ?? 0) ?> <?= htmlspecialchars((string)($ingredient['uom_code'] ?? '')) ?> tersedia &middot; cukup <?= number_format((int)($ingredient['available_batches'] ?? 0), 0, ',', '.') ?> batch</span>
                  <?php endforeach; ?>
                </td>
                <td>
                  <span class="ps-badge <?= $isReady ? 'ready' : 'waiting' ?>"><?= $isReady ? 'Siap diproses' : 'Butuh bahan lain' ?></span>
                  <div class="ps-meta" style="margin-top:.4rem">
                    <?= number_format((int)($row['ready_line_count'] ?? 0), 0, ',', '.') ?>/<?= number_format((int)($row['formula_line_count'] ?? 0), 0, ',', '.') ?> baris formula tersedia.
                    <?php if (!$isReady): ?><br>Kurang: <?= htmlspecialchars(implode(', ', $blockingNames) ?: 'bahan pendukung') ?>.<?php endif; ?>
                  </div>
                </td>
                <td>
                  <?php if ($isReady): ?>
                    <div class="ps-output">Proses <?= number_format((int)($row['recommended_batches'] ?? 1), 0, ',', '.') ?> batch dulu.</div>
                    <div class="ps-meta">Kapasitas bahan saat ini sampai <?= number_format((int)($row['max_possible_batches'] ?? 0), 0, ',', '.') ?> batch. Jangan langsung produksi semua tanpa cek kebutuhan penjualan.</div>
                  <?php else: ?>
                    <div class="ps-output waiting">Siapkan bahan yang kurang terlebih dahulu.</div>
                    <div class="ps-meta">Ada bahan yang menumpuk, tetapi batch belum aman diproses penuh.</div>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="ps-actions">
                    <a class="ps-btn light" href="<?= htmlspecialchars($formulaUrl) ?>">Formula</a>
                    <a class="ps-btn" href="<?= htmlspecialchars($batchUrl) ?>">Buka Batch</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const tabs = document.getElementById('productionSuggestionPageTabs');
  if (!tabs) return;

  const rows = document.querySelectorAll('[data-production-suggestion-page-division]');
  tabs.querySelectorAll('[data-production-suggestion-page-tab]').forEach(function (button) {
    button.addEventListener('click', function () {
      const selectedDivision = button.dataset.productionSuggestionPageTab || 'ALL';
      tabs.querySelectorAll('[data-production-suggestion-page-tab]').forEach(function (tab) {
        const isActive = tab === button;
        tab.classList.toggle('active', isActive);
        tab.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });
      rows.forEach(function (row) {
        row.hidden = selectedDivision !== 'ALL'
          && row.dataset.productionSuggestionPageDivision !== selectedDivision;
      });
    });
  });
});
</script>
