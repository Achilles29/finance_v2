<?php
$current = function ($name, $default = '') use ($row) {
    if (set_value($name) !== '') {
        return set_value($name);
    }
    if (!empty($row) && array_key_exists($name, $row)) {
        return $row[$name];
    }
    return $default;
};

$relationType = $relation_type;
$isProductRecipe = $relationType === 'product-recipe';
$isComponentFormula = $relationType === 'component-formula';
$isProductExtra = $relationType === 'product-extra';
$productVariableCost = is_array($product_variable_cost ?? null) ? $product_variable_cost : [];

if ($isProductRecipe) {
    $backUrl = site_url('master/relation/product-recipe/' . (int)$parent['id']);
} elseif ($isComponentFormula) {
    $backUrl = site_url('production/component-formulas/edit/' . (int)$parent['id']);
} else {
    $backUrl = site_url('master/relation/product-extra/' . (int)$parent['id']);
}

$defaultSourceDivisionId = (int)($options['default_source_division_id'] ?? 0);
$defaultSourceDivisionName = (string)($options['default_source_division_name'] ?? '-');
$ingredientRoleOptions = is_array($options['ingredient_roles'] ?? null) ? $options['ingredient_roles'] : [];
?>

<?php if ($isProductRecipe): ?>
<style>
  .product-recipe-form .recipe-form-card {
    border: 0;
  }
  .product-recipe-form .recipe-form-summary strong {
    font-size: 1rem;
  }
</style>

<div class="product-recipe-form container-xxl py-3 px-0">
  <div class="fin-page-header mb-3">
    <div>
      <div class="fin-breadcrumb">
        <a href="<?php echo site_url('master/product'); ?>">Master Product</a>
        <span>/</span>
        <a href="<?php echo $backUrl; ?>">Recipe Produk</a>
        <span>/</span>
        <span><?php echo !empty($row) ? 'Edit Line' : 'Tambah Line'; ?></span>
      </div>
      <h4 class="fin-page-title mb-1"><?php echo html_escape($title); ?></h4>
      <p class="fin-page-subtitle mb-0">
        Produk: <?php echo html_escape((string)($parent['product_name'] ?? '-')); ?>
        • Divisi Produk: <?php echo html_escape((string)($parent['product_division_name'] ?? '-')); ?>
      </p>
    </div>
    <div class="fin-page-actions">
      <a href="<?php echo $backUrl; ?>" class="btn btn-outline-secondary">Kembali</a>
      <a href="<?php echo site_url('master/product/edit/' . (int)$parent['id']); ?>" class="btn btn-outline-primary">Edit Product / Variable Cost</a>
    </div>
  </div>

  <div class="card recipe-form-card shadow-sm mb-3">
    <div class="card-body row g-2 recipe-form-summary">
      <div class="col-md-4"><small class="text-muted d-block">Produk</small><strong><?php echo html_escape((string)($parent['product_name'] ?? '-')); ?></strong></div>
      <div class="col-md-4"><small class="text-muted d-block">Divisi Produk</small><strong><?php echo html_escape((string)($parent['product_division_name'] ?? '-')); ?></strong></div>
      <div class="col-md-4"><small class="text-muted d-block">Default Source Bahan Baku</small><strong id="recipe-default-division-label"><?php echo html_escape($defaultSourceDivisionName); ?></strong></div>
      <div class="col-md-3"><small class="text-muted d-block">Harga Jual</small><strong>Rp <?php echo number_format((float)($productVariableCost['selling_price'] ?? 0), 2, ',', '.'); ?></strong></div>
      <div class="col-md-3"><small class="text-muted d-block">Mode Variable Cost</small><strong><?php echo html_escape((string)($productVariableCost['mode'] ?? '-')); ?></strong></div>
      <div class="col-md-3"><small class="text-muted d-block">Effective Variable Cost</small><strong><?php echo number_format((float)($productVariableCost['effective_percent'] ?? 0), 2, ',', '.'); ?>%</strong></div>
      <div class="col-md-3"><small class="text-muted d-block">HPP Live Cache</small><strong>Rp <?php echo number_format((float)($productVariableCost['hpp_live_cache'] ?? 0), 4, ',', '.'); ?></strong></div>
    </div>
  </div>

  <div class="card recipe-form-card shadow-sm">
    <div class="card-body">
      <div class="alert alert-light border py-2 mb-3">
        <strong>Aturan input:</strong> line <strong>Component</strong> otomatis mengikuti divisi operasional dan UOM component terpilih. Line <strong>Bahan Baku</strong> otomatis memakai source division default divisi produk dan UOM content bahan baku.
      </div>

      <form method="post" action="<?php echo site_url($form_action); ?>" id="productRecipeForm">
        <input type="hidden" name="master_relation_product_recipe_mutation_csrf" value="<?php echo html_escape((string)($master_relation_product_recipe_mutation_csrf ?? '')); ?>">
        <input type="hidden" name="product_recipe_revision" value="<?php echo html_escape((string)($product_recipe_revision ?? '')); ?>">
        <input type="hidden" name="source_division_id" id="source_division_id" value="<?php echo html_escape((string)$current('source_division_id', (string)$defaultSourceDivisionId)); ?>">
        <input type="hidden" name="uom_id" id="uom_id" value="<?php echo html_escape((string)$current('uom_id', '')); ?>">

        <div class="row g-3">
          <div class="col-md-2">
            <label class="form-label mb-1">Line No</label>
            <input type="number" class="form-control" name="line_no" value="<?php echo html_escape((string)$current('line_no', 1)); ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Tipe Line</label>
            <select class="form-select" name="line_type" id="line_type">
              <option value="MATERIAL" <?php echo $current('line_type', 'MATERIAL') === 'MATERIAL' ? 'selected' : ''; ?>>Bahan Baku</option>
              <option value="COMPONENT" <?php echo $current('line_type', 'MATERIAL') === 'COMPONENT' ? 'selected' : ''; ?>>Component</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Qty</label>
            <input type="number" step="0.0001" class="form-control" name="qty" value="<?php echo html_escape((string)$current('qty', '0')); ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Role Resep</label>
            <select class="form-select" name="ingredient_role">
              <?php foreach ($ingredientRoleOptions as $opt): ?>
                <option value="<?php echo html_escape((string)$opt['value']); ?>" <?php echo (string)$current('ingredient_role', 'MAIN') === (string)$opt['value'] ? 'selected' : ''; ?>>
                  <?php echo html_escape((string)$opt['label']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Sort Order</label>
            <input type="number" class="form-control" name="sort_order" value="<?php echo html_escape((string)$current('sort_order', '0')); ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">UOM</label>
            <input type="text" class="form-control" id="uom_label" value="<?php echo html_escape((string)$current('uom_name', '')); ?>" readonly>
          </div>

          <div class="col-md-6 type-material">
            <label class="form-label mb-1">Item Bahan Baku</label>
            <select class="form-select" name="material_item_id" id="material_item_id">
              <option value="">- pilih bahan baku -</option>
              <?php foreach (($options['materials'] ?? []) as $opt): ?>
                <option
                  value="<?php echo html_escape((string)$opt['value']); ?>"
                  data-uom-id="<?php echo html_escape((string)($opt['uom_id'] ?? 0)); ?>"
                  data-uom-label="<?php echo html_escape((string)($opt['uom_label'] ?? '-')); ?>"
                  data-source-division-id="<?php echo html_escape((string)($opt['source_division_id'] ?? 0)); ?>"
                  data-source-division-name="<?php echo html_escape((string)($opt['source_division_name'] ?? '-')); ?>"
                  <?php echo (string)$current('material_item_id', '') === (string)$opt['value'] ? 'selected' : ''; ?>
                >
                  <?php echo html_escape((string)$opt['label']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6 type-component">
            <label class="form-label mb-1">Component</label>
            <select class="form-select" name="component_id" id="component_id">
              <option value="">- pilih component -</option>
              <?php foreach (($options['components'] ?? []) as $opt): ?>
                <option
                  value="<?php echo html_escape((string)$opt['value']); ?>"
                  data-uom-id="<?php echo html_escape((string)($opt['uom_id'] ?? 0)); ?>"
                  data-uom-label="<?php echo html_escape((string)($opt['uom_label'] ?? '-')); ?>"
                  data-source-division-id="<?php echo html_escape((string)($opt['source_division_id'] ?? 0)); ?>"
                  data-source-division-name="<?php echo html_escape((string)($opt['source_division_name'] ?? '-')); ?>"
                  <?php echo (string)$current('component_id', '') === (string)$opt['value'] ? 'selected' : ''; ?>
                >
                  <?php echo html_escape((string)$opt['label']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label mb-1">Source Divisi Operasional</label>
            <input type="text" class="form-control" id="source_division_label" value="<?php echo html_escape($defaultSourceDivisionName); ?>" readonly>
            <small class="text-muted" id="source_division_help">Line bahan baku memakai default divisi produk.</small>
          </div>

          <div class="col-12">
            <label class="form-label mb-1">Catatan</label>
            <textarea class="form-control" rows="3" name="notes"><?php echo html_escape((string)$current('notes', '')); ?></textarea>
          </div>
        </div>

        <div class="mt-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary">Simpan</button>
          <a href="<?php echo $backUrl; ?>" class="btn btn-outline-secondary">Batal</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  var lineType = document.getElementById('line_type');
  var materialWrap = document.querySelector('.type-material');
  var componentWrap = document.querySelector('.type-component');
  var materialSelect = document.getElementById('material_item_id');
  var componentSelect = document.getElementById('component_id');
  var sourceDivisionId = document.getElementById('source_division_id');
  var sourceDivisionLabel = document.getElementById('source_division_label');
  var sourceDivisionHelp = document.getElementById('source_division_help');
  var uomId = document.getElementById('uom_id');
  var uomLabel = document.getElementById('uom_label');
  var defaultDivisionId = <?php echo json_encode($defaultSourceDivisionId); ?>;
  var defaultDivisionName = <?php echo json_encode($defaultSourceDivisionName); ?>;

  if (!lineType) {
    return;
  }

  function selectedOption(selectEl) {
    if (!selectEl || !selectEl.options || selectEl.selectedIndex < 0) {
      return null;
    }
    return selectEl.options[selectEl.selectedIndex] || null;
  }

  function syncFromOption(option, fallbackHelp) {
    var divisionId = option ? String(option.getAttribute('data-source-division-id') || '') : '';
    var divisionName = option ? String(option.getAttribute('data-source-division-name') || '') : '';
    var resolvedDivisionId = divisionId !== '' && divisionId !== '0' ? divisionId : String(defaultDivisionId || '');
    var resolvedDivisionName = divisionName !== '' && divisionName !== '-' ? divisionName : defaultDivisionName;
    sourceDivisionId.value = resolvedDivisionId;
    sourceDivisionLabel.value = resolvedDivisionName || '-';
    sourceDivisionHelp.textContent = fallbackHelp;

    var nextUomId = option ? String(option.getAttribute('data-uom-id') || '') : '';
    var nextUomLabel = option ? String(option.getAttribute('data-uom-label') || '') : '';
    uomId.value = nextUomId;
    uomLabel.value = nextUomLabel;
  }

  function syncType() {
    var isMaterial = lineType.value === 'MATERIAL';
    if (materialWrap) {
      materialWrap.style.display = isMaterial ? '' : 'none';
    }
    if (componentWrap) {
      componentWrap.style.display = isMaterial ? 'none' : '';
    }
    if (isMaterial) {
      syncFromOption(selectedOption(materialSelect), 'Line bahan baku memakai default divisi produk.');
    } else {
      syncFromOption(selectedOption(componentSelect), 'Line component mengikuti divisi operasional component.');
    }
  }

  materialSelect && materialSelect.addEventListener('change', function () {
    if (lineType.value === 'MATERIAL') {
      syncFromOption(selectedOption(materialSelect), 'Line bahan baku memakai default divisi produk.');
    }
  });

  componentSelect && componentSelect.addEventListener('change', function () {
    if (lineType.value === 'COMPONENT') {
      syncFromOption(selectedOption(componentSelect), 'Line component mengikuti divisi operasional component.');
    }
  });

  lineType.addEventListener('change', syncType);
  syncType();
})();
</script>
<?php return; ?>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><?php echo html_escape($title); ?></h4>
  <a href="<?php echo $backUrl; ?>" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="card">
  <div class="card-body">
    <form method="post" action="<?php echo site_url($form_action); ?>">
      <?php if ($isProductExtra): ?>
        <input type="hidden" name="master_relation_product_extra_mutation_csrf" value="<?php echo html_escape((string)($master_relation_product_extra_mutation_csrf ?? '')); ?>">
      <?php endif; ?>
      <?php if ($isProductRecipe || $isComponentFormula): ?>
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label mb-1">Line No</label>
            <input type="number" class="form-control" name="line_no" value="<?php echo html_escape((string)$current('line_no', 1)); ?>">
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label mb-1">Line Type</label>
            <select class="form-control" name="line_type" id="line_type">
              <option value="MATERIAL" <?php echo $current('line_type', 'MATERIAL') === 'MATERIAL' ? 'selected' : ''; ?>>BAHAN BAKU</option>
              <option value="COMPONENT" <?php echo $current('line_type', 'MATERIAL') === 'COMPONENT' ? 'selected' : ''; ?>>COMPONENT</option>
            </select>
          </div>
          <div class="col-md-3 mb-3 type-material">
            <label class="form-label mb-1">Item Bahan Baku</label>
            <select class="form-control" name="material_item_id">
              <option value="">- pilih -</option>
              <?php foreach (($options['materials'] ?? []) as $opt): ?>
                <option value="<?php echo html_escape((string)$opt['value']); ?>" <?php echo (string)$current('material_item_id', '') === (string)$opt['value'] ? 'selected' : ''; ?>>
                  <?php echo html_escape((string)$opt['label']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3 mb-3 type-component">
            <label class="form-label mb-1"><?php echo $isProductRecipe ? 'Component' : 'Sub Component'; ?></label>
            <select class="form-control" name="<?php echo $isProductRecipe ? 'component_id' : 'sub_component_id'; ?>">
              <option value="">- pilih -</option>
              <?php foreach (($options['components'] ?? []) as $opt): ?>
                <option value="<?php echo html_escape((string)$opt['value']); ?>" <?php echo (string)$current($isProductRecipe ? 'component_id' : 'sub_component_id', '') === (string)$opt['value'] ? 'selected' : ''; ?>>
                  <?php echo html_escape((string)$opt['label']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <?php if ($isProductRecipe): ?>
            <div class="col-md-3 mb-3">
              <label class="form-label mb-1">Source Divisi Operasional</label>
              <select class="form-control" name="source_division_id">
                <option value="">- default product -</option>
                <?php foreach (($options['source_divisions'] ?? []) as $opt): ?>
                  <option value="<?php echo html_escape((string)$opt['value']); ?>" <?php echo (string)$current('source_division_id', '') === (string)$opt['value'] ? 'selected' : ''; ?>>
                    <?php echo html_escape((string)$opt['label']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">Gunakan divisi operasional agar alur stok bahan baku konsisten.</small>
            </div>
          <?php endif; ?>

          <div class="col-md-3 mb-3">
            <label class="form-label mb-1">Qty</label>
            <input type="number" step="0.0001" class="form-control" name="qty" value="<?php echo html_escape((string)$current('qty', '0')); ?>">
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label mb-1">UOM</label>
            <select class="form-control" name="uom_id">
              <option value="">- pilih -</option>
              <?php foreach (($options['uoms'] ?? []) as $opt): ?>
                <option value="<?php echo html_escape((string)$opt['value']); ?>" <?php echo (string)$current('uom_id', '') === (string)$opt['value'] ? 'selected' : ''; ?>>
                  <?php echo html_escape((string)$opt['label']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label mb-1">Sort Order</label>
            <input type="number" class="form-control" name="sort_order" value="<?php echo html_escape((string)$current('sort_order', '0')); ?>">
          </div>
          <div class="col-md-12 mb-3">
            <label class="form-label mb-1">Catatan</label>
            <textarea class="form-control" rows="3" name="notes"><?php echo html_escape((string)$current('notes', '')); ?></textarea>
          </div>
        </div>
      <?php else: ?>
        <div class="row">
          <div class="col-md-8 mb-3">
            <label class="form-label mb-1">Extra Group</label>
            <select class="form-control" name="extra_group_id">
              <option value="">- pilih -</option>
              <?php foreach (($options['extra_groups'] ?? []) as $opt): ?>
                <option value="<?php echo html_escape((string)$opt['value']); ?>" <?php echo (string)$current('extra_group_id', '') === (string)$opt['value'] ? 'selected' : ''; ?>>
                  <?php echo html_escape((string)$opt['label']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label mb-1">Sort Order</label>
            <input type="number" class="form-control" name="sort_order" value="<?php echo html_escape((string)$current('sort_order', '0')); ?>">
          </div>
        </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary">Simpan</button>
      <a href="<?php echo $backUrl; ?>" class="btn btn-outline-secondary">Batal</a>
    </form>
  </div>
</div>

<?php if ($isProductRecipe || $isComponentFormula): ?>
<script>
(function () {
  var lineType = document.getElementById('line_type');
  var materialFields = document.querySelectorAll('.type-material');
  var componentFields = document.querySelectorAll('.type-component');
  if (!lineType) return;

  function syncType() {
    var isMaterial = lineType.value === 'MATERIAL';
    materialFields.forEach(function (el) { el.style.display = isMaterial ? '' : 'none'; });
    componentFields.forEach(function (el) { el.style.display = isMaterial ? 'none' : ''; });
  }

  lineType.addEventListener('change', syncType);
  syncType();
})();
</script>
<?php endif; ?>
