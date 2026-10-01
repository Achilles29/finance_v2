<?php
$detail = is_array($detail ?? null) ? $detail : [];
$component = is_array($detail['component'] ?? null) ? $detail['component'] : [];
$rows = is_array($detail['rows'] ?? null) ? $detail['rows'] : [];
$summary = is_array($detail['summary'] ?? null) ? $detail['summary'] : [];

$baseRows = [];
$prepareRows = [];
$productRows = [];

foreach ($rows as $usageRow) {
    $usageType = strtoupper((string)($usageRow['usage_type'] ?? ''));
    $targetKind = strtoupper((string)($usageRow['target_kind'] ?? ''));
    if ($usageType === 'PRODUCT') {
        $productRows[] = $usageRow;
        continue;
    }
    if ($targetKind === 'PREPARE') {
        $prepareRows[] = $usageRow;
        continue;
    }
    $baseRows[] = $usageRow;
}

$sections = [
    [
        'title' => 'Dipakai Oleh Base',
        'subtitle' => 'Formula BASE yang memakai component ini.',
        'empty' => 'Belum ada BASE yang memakai component ini.',
        'rows' => $baseRows,
        'type' => 'COMPONENT',
    ],
    [
        'title' => 'Dipakai Oleh Prepare',
        'subtitle' => 'Formula PREPARE yang memakai component ini.',
        'empty' => 'Belum ada PREPARE yang memakai component ini.',
        'rows' => $prepareRows,
        'type' => 'COMPONENT',
    ],
    [
        'title' => 'Dipakai Oleh Produk',
        'subtitle' => 'Recipe produk yang memakai component ini langsung.',
        'empty' => 'Belum ada produk yang memakai component ini langsung.',
        'rows' => $productRows,
        'type' => 'PRODUCT',
    ],
];

$usageCount = (int)($summary['usage_count'] ?? 0);
$componentName = (string)($component['component_name'] ?? '-');
$componentType = strtoupper((string)($component['component_type'] ?? '-'));
$componentUom = (string)($component['uom_name'] ?? '-');
?>

<div class="container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1"><?php echo html_escape($componentName); ?></h4>
      <p class="fin-page-subtitle mb-0">Pemakaian component ini dipisah ringkas ke BASE, PREPARE, dan PRODUK agar lebih cepat dibaca.</p>
      <div class="d-flex gap-2 flex-wrap mt-2">
        <span class="badge bg-light text-dark border"><?php echo html_escape($componentType); ?></span>
        <span class="badge bg-light text-dark border"><?php echo html_escape($componentUom); ?></span>
        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><?php echo number_format($usageCount, 0, ',', '.'); ?> pemakai unik</span>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="<?php echo site_url('production/component-masters'); ?>">Kembali</a>
      <a class="btn btn-outline-info btn-sm" href="<?php echo site_url('production/component-formulas/detail/' . (int)($component['id'] ?? 0)); ?>"><i class="ri ri-eye-line me-1"></i>Detail Formula</a>
    </div>
  </div>

  <?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'master']); ?>

  <?php foreach ($sections as $section): ?>
    <?php $sectionRows = is_array($section['rows'] ?? null) ? $section['rows'] : []; ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body p-0">
        <div class="d-flex justify-content-between align-items-start gap-2 px-3 pt-3 pb-2 border-bottom">
          <div>
            <h5 class="mb-1"><?php echo html_escape((string)$section['title']); ?></h5>
            <div class="small text-muted"><?php echo html_escape((string)$section['subtitle']); ?></div>
          </div>
          <span class="badge bg-light text-dark border"><?php echo number_format(count($sectionRows), 0, ',', '.'); ?></span>
        </div>

        <?php if (empty($sectionRows)): ?>
          <div class="px-3 py-4 text-muted"><?php echo html_escape((string)$section['empty']); ?></div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Nama</th>
                  <th style="width:180px;">Divisi</th>
                  <th class="text-end" style="width:110px;">Line</th>
                  <th class="component-action-cell">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($sectionRows as $row): ?>
                  <?php $targetUrl = strtoupper((string)($section['type'] ?? 'COMPONENT')) === 'PRODUCT'
                      ? site_url('master/relation/product-recipe/' . (int)($row['target_id'] ?? 0))
                      : site_url('production/component-formulas/detail/' . (int)($row['target_id'] ?? 0)); ?>
                  <tr>
                    <td class="fw-semibold"><?php echo html_escape((string)($row['target_name'] ?? '-')); ?></td>
                    <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></td>
                    <td class="text-end"><?php echo number_format((int)($row['usage_line_count'] ?? 0), 0, ',', '.'); ?></td>
                    <td class="component-action-cell">
                      <a class="btn btn-outline-info action-icon-btn component-action-btn" href="<?php echo $targetUrl; ?>" title="Lihat detail" aria-label="Lihat detail"><i class="ri ri-eye-line"></i></a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>