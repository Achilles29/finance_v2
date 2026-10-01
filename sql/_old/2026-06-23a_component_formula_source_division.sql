-- Add per-line source division to mst_component_formula.
-- Enables cross-division stock deduction/rollback during batch production.
-- Each formula line can now specify which operational division supplies the material/component,
-- overriding the component's default operational_division_id.

ALTER TABLE mst_component_formula
  ADD COLUMN source_division_id BIGINT UNSIGNED NULL DEFAULT NULL
    AFTER sub_component_id,
  ADD CONSTRAINT fk_mcf_source_div
    FOREIGN KEY (source_division_id)
    REFERENCES mst_operational_division(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;
