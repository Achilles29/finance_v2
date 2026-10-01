-- Add per-line source division to inv_component_batch_input.
-- Enables cross-division stock deduction/rollback during batch posting.
-- Each input line now records which division the material/component was drawn from,
-- which may differ from the batch output component's division (cross-division formula).

ALTER TABLE inv_component_batch_input
  ADD COLUMN division_id BIGINT(20) UNSIGNED NULL DEFAULT NULL
    AFTER source_kind;
