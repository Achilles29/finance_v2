ALTER TABLE inv_stock_movement_log
  MODIFY COLUMN movement_type ENUM(
    'PURCHASE_IN',
    'TRANSFER_IN',
    'TRANSFER_OUT',
    'USAGE_OUT',
    'DISCARDED_OUT',
    'SPOIL_OUT',
    'WASTE_OUT',
    'PROCESS_LOSS_OUT',
    'VARIANCE_OUT',
    'ADJUSTMENT',
    'ADJUSTMENT_IN',
    'VOID_REVERSE'
  ) NOT NULL;
