ALTER TABLE inv_component_adjustment_line
  ADD COLUMN IF NOT EXISTS spoil_reason_code VARCHAR(50) NULL AFTER qty_spoil,
  ADD COLUMN IF NOT EXISTS waste_reason_code VARCHAR(50) NULL AFTER qty_waste,
  ADD COLUMN IF NOT EXISTS adjustment_plus_reason_code VARCHAR(50) NULL AFTER qty_adjust_pos,
  ADD COLUMN IF NOT EXISTS adjustment_minus_reason_code VARCHAR(50) NULL AFTER qty_adjust_neg;
