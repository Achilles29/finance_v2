-- ============================================================
-- Seed awal master operasional dari database core -> db_finance
-- Jalankan saat koneksi aktif ke db_finance
-- ============================================================
SET NAMES utf8mb4;

-- Set default operational division for imported production entities.
-- Change this value if needed (must exist in mst_operational_division.code).
SET @default_operational_division_code = 'MANAJEMEN';
-- Set default product division for component mapping when scope code is not found.
SET @default_product_division_code = 'GENERAL';

START TRANSACTION;

-- 1) Material
INSERT INTO mst_material (
    material_code,
    material_name,
    item_category_id,
    content_uom_id,
    hpp_standard,
    is_active
)
SELECT
    m.material_code,
    m.material_name,
    ic.id,
    tu.id,
    m.hpp_standard,
    m.is_active
FROM core.m_material m
LEFT JOIN core.m_material_category mc ON mc.id = m.material_category_id
LEFT JOIN mst_item_category ic ON ic.code = mc.category_code
JOIN core.m_uom su ON su.id = m.base_uom_id
JOIN mst_uom tu ON tu.code = su.code
ON DUPLICATE KEY UPDATE
    material_name = VALUES(material_name),
    item_category_id = VALUES(item_category_id),
    content_uom_id = VALUES(content_uom_id),
    hpp_standard = VALUES(hpp_standard),
    is_active = VALUES(is_active);

-- 2) Item
INSERT INTO mst_item (
    item_code,
    item_name,
    item_category_id,
    buy_uom_id,
    content_uom_id,
    content_per_buy,
    min_stock_content,
    last_buy_price,
    is_material,
    material_id,
    notes,
    is_active
)
SELECT
    i.item_code,
    i.item_name,
    COALESCE(ic.id, (SELECT id FROM mst_item_category ORDER BY id LIMIT 1)) AS item_category_id,
    tu.id AS buy_uom_id,
    tu.id AS content_uom_id,
    1.000000 AS content_per_buy,
    0.0000 AS min_stock_content,
    NULL AS last_buy_price,
    IF(i.material_id IS NULL, 0, 1) AS is_material,
    tm.id AS material_id,
    i.notes,
    i.is_active
FROM core.m_item i
JOIN core.m_uom su ON su.id = i.base_uom_id
JOIN mst_uom tu ON tu.code = su.code
LEFT JOIN core.m_material cm ON cm.id = i.material_id
LEFT JOIN core.m_material_category cmc ON cmc.id = cm.material_category_id
LEFT JOIN mst_item_category ic ON ic.code = cmc.category_code
LEFT JOIN mst_material tm ON tm.material_code = cm.material_code
ON DUPLICATE KEY UPDATE
    item_name = VALUES(item_name),
    item_category_id = VALUES(item_category_id),
    buy_uom_id = VALUES(buy_uom_id),
    content_uom_id = VALUES(content_uom_id),
    content_per_buy = VALUES(content_per_buy),
    min_stock_content = VALUES(min_stock_content),
    is_material = VALUES(is_material),
    material_id = VALUES(material_id),
    notes = VALUES(notes),
    is_active = VALUES(is_active);

-- 3) Component
-- Note: current schema mst_component has no product_division_id column.
-- Component business grouping still follows product/category hierarchy in recipes/formula.
INSERT INTO mst_component (
    component_code,
    component_name,
    component_type,
    product_division_id,
    operational_division_id,
    component_category_id,
    uom_id,
    yield_qty,
    hpp_standard,
    variable_cost_mode,
    variable_cost_percent,
    min_stock,
    notes,
    is_active
)
SELECT
    c.component_code,
    c.component_name,
    c.component_kind,
    COALESCE(pd.id, (SELECT id FROM mst_product_division ORDER BY id LIMIT 1)) AS product_division_id,
    COALESCE(od.id, (SELECT id FROM mst_operational_division ORDER BY id LIMIT 1)) AS operational_division_id,
    COALESCE(cc.id, (SELECT id FROM mst_component_category ORDER BY id LIMIT 1)) AS component_category_id,
    tu.id AS uom_id,
    c.standard_yield_qty,
    c.hpp_standard,
    c.variable_cost_mode,
    c.variable_cost_percent,
    0.0000 AS min_stock,
    c.notes,
    c.is_active
FROM core.prd_component c
LEFT JOIN core.prd_component_category scc ON scc.id = c.category_id
LEFT JOIN mst_component_category cc ON cc.code = scc.category_code
LEFT JOIN mst_product_division pd ON pd.code = COALESCE(c.division_scope, scc.division_scope, @default_product_division_code)
LEFT JOIN mst_operational_division od ON od.code = @default_operational_division_code
JOIN core.m_uom su ON su.id = c.base_uom_id
JOIN mst_uom tu ON tu.code = su.code
ON DUPLICATE KEY UPDATE
    component_name = VALUES(component_name),
    component_type = VALUES(component_type),
    product_division_id = VALUES(product_division_id),
    operational_division_id = VALUES(operational_division_id),
    component_category_id = VALUES(component_category_id),
    uom_id = VALUES(uom_id),
    yield_qty = VALUES(yield_qty),
    hpp_standard = VALUES(hpp_standard),
    variable_cost_mode = VALUES(variable_cost_mode),
    variable_cost_percent = VALUES(variable_cost_percent),
    min_stock = VALUES(min_stock),
    notes = VALUES(notes),
    is_active = VALUES(is_active);

-- 4) Product
INSERT INTO mst_product (
    product_code,
    product_name,
    product_division_id,
    default_operational_division_id,
    classification_id,
    product_category_id,
    uom_id,
    selling_price,
    hpp_standard,
    variable_cost_mode,
    variable_cost_percent,
    stock_mode,
    show_pos,
    show_member,
    show_landing,
    photo_path,
    is_active
)
SELECT
    p.product_code,
    p.product_name,
    COALESCE(pd.id, (SELECT id FROM mst_product_division ORDER BY id LIMIT 1)) AS product_division_id,
    COALESCE(od.id, (SELECT id FROM mst_operational_division ORDER BY id LIMIT 1)) AS default_operational_division_id,
    COALESCE(pc.id, (SELECT id FROM mst_product_classification ORDER BY id LIMIT 1)) AS classification_id,
    COALESCE(pg.id, (SELECT id FROM mst_product_category ORDER BY id LIMIT 1)) AS product_category_id,
    tu.id AS uom_id,
    p.selling_price,
    p.hpp_standard,
    p.variable_cost_mode,
    p.variable_cost_percent,
    CASE
        WHEN p.availability_mode = 'MANUAL' AND p.manual_availability_status = 'SOLD_OUT' THEN 'MANUAL_OUT'
        WHEN p.availability_mode = 'MANUAL' THEN 'MANUAL_AVAILABLE'
        ELSE 'AUTO'
    END AS stock_mode,
    p.is_sellable AS show_pos,
    0 AS show_member,
    0 AS show_landing,
    p.photo_path,
    p.is_active
FROM core.prd_product p
LEFT JOIN core.prd_product_division spd ON spd.id = p.product_division_id
LEFT JOIN core.prd_product_classification spc ON spc.id = p.classification_id
LEFT JOIN core.prd_product_category spg ON spg.id = p.category_id
LEFT JOIN mst_product_division pd ON pd.code = spd.division_code
LEFT JOIN mst_product_classification pc ON pc.code = spc.classification_code
LEFT JOIN mst_product_category pg ON pg.code = spg.category_code
LEFT JOIN mst_operational_division od ON od.code = @default_operational_division_code
JOIN core.m_uom su ON su.id = p.sale_uom_id
JOIN mst_uom tu ON tu.code = su.code
ON DUPLICATE KEY UPDATE
    product_name = VALUES(product_name),
    product_division_id = VALUES(product_division_id),
    default_operational_division_id = VALUES(default_operational_division_id),
    classification_id = VALUES(classification_id),
    product_category_id = VALUES(product_category_id),
    uom_id = VALUES(uom_id),
    selling_price = VALUES(selling_price),
    hpp_standard = VALUES(hpp_standard),
    variable_cost_mode = VALUES(variable_cost_mode),
    variable_cost_percent = VALUES(variable_cost_percent),
    stock_mode = VALUES(stock_mode),
    show_pos = VALUES(show_pos),
    show_member = VALUES(show_member),
    show_landing = VALUES(show_landing),
    photo_path = VALUES(photo_path),
    is_active = VALUES(is_active);

-- 5) Extra
INSERT INTO mst_extra (
    extra_code,
    extra_name,
    uom_name,
    extra_type,
    selling_price,
    cost_amount,
    source_kind,
    source_product_id,
    source_component_id,
    source_material_id,
    source_qty,
    replacement_kind,
    replacement_product_id,
    replacement_component_id,
    replacement_material_id,
    replacement_qty,
    show_in_cashier,
    show_in_self_order,
    show_in_landing,
    is_active
)
SELECT
    e.extra_code,
    e.extra_name,
    e.uom_name,
    e.extra_type,
    e.selling_price,
    e.cost_amount,
    e.source_kind,
    tsp.id AS source_product_id,
    tsc.id AS source_component_id,
    tsm.id AS source_material_id,
    e.source_qty,
    e.replacement_kind,
    trp.id AS replacement_product_id,
    trc.id AS replacement_component_id,
    trm.id AS replacement_material_id,
    e.replacement_qty,
    e.show_in_cashier,
    e.show_in_self_order,
    0 AS show_in_landing,
    e.is_active
FROM core.pos_extra e
LEFT JOIN core.prd_product sp ON sp.id = e.source_product_id
LEFT JOIN core.prd_component sc ON sc.id = e.source_component_id
LEFT JOIN core.m_material sm ON sm.id = e.source_material_id
LEFT JOIN core.prd_product rp ON rp.id = e.replacement_product_id
LEFT JOIN core.prd_component rc ON rc.id = e.replacement_component_id
LEFT JOIN core.m_material rm ON rm.id = e.replacement_material_id
LEFT JOIN mst_product tsp ON tsp.product_code = sp.product_code
LEFT JOIN mst_component tsc ON tsc.component_code = sc.component_code
LEFT JOIN mst_material tsm ON tsm.material_code = sm.material_code
LEFT JOIN mst_product trp ON trp.product_code = rp.product_code
LEFT JOIN mst_component trc ON trc.component_code = rc.component_code
LEFT JOIN mst_material trm ON trm.material_code = rm.material_code
ON DUPLICATE KEY UPDATE
    extra_name = VALUES(extra_name),
    uom_name = VALUES(uom_name),
    extra_type = VALUES(extra_type),
    selling_price = VALUES(selling_price),
    cost_amount = VALUES(cost_amount),
    source_kind = VALUES(source_kind),
    source_product_id = VALUES(source_product_id),
    source_component_id = VALUES(source_component_id),
    source_material_id = VALUES(source_material_id),
    source_qty = VALUES(source_qty),
    replacement_kind = VALUES(replacement_kind),
    replacement_product_id = VALUES(replacement_product_id),
    replacement_component_id = VALUES(replacement_component_id),
    replacement_material_id = VALUES(replacement_material_id),
    replacement_qty = VALUES(replacement_qty),
    show_in_cashier = VALUES(show_in_cashier),
    show_in_self_order = VALUES(show_in_self_order),
    show_in_landing = VALUES(show_in_landing),
    is_active = VALUES(is_active);

COMMIT;
