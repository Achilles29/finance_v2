
SET FOREIGN_KEY_CHECKS = 0;

-- ── Attendance ──────────────────────────────────────────────
TRUNCATE TABLE att_daily;
TRUNCATE TABLE att_employee_ph_ledger;
TRUNCATE TABLE att_overtime_entry;
TRUNCATE TABLE att_pending_request;
TRUNCATE TABLE att_pending_request_approval;
TRUNCATE TABLE att_presence;

-- ── Audit ────────────────────────────────────────────────────
TRUNCATE TABLE aud_transaction_log;

-- ── Cost ─────────────────────────────────────────────────────
TRUNCATE TABLE cost_recalc_queue;

-- ── Finance ──────────────────────────────────────────────────
TRUNCATE TABLE fin_account_mutation_log;

-- ── Inventory ────────────────────────────────────────────────
TRUNCATE TABLE inv_division_daily_rollup;
TRUNCATE TABLE inv_division_monthly_opname;
TRUNCATE TABLE inv_division_stock_balance;
TRUNCATE TABLE inv_division_stock_opening_snapshot;
TRUNCATE TABLE inv_item_material_source_map;
TRUNCATE TABLE inv_item_material_txn;
TRUNCATE TABLE inv_item_source_balance;
TRUNCATE TABLE inv_material_source_balance;
TRUNCATE TABLE inv_stock_movement_log;
TRUNCATE TABLE inv_stock_opening_snapshot;
TRUNCATE TABLE inv_warehouse_daily_rollup;
TRUNCATE TABLE inv_warehouse_monthly_opname;
TRUNCATE TABLE inv_warehouse_stock_balance;
TRUNCATE TABLE inv_warehouse_stock_opening_snapshot;

TRUNCATE TABLE inv_component_adjustment;
TRUNCATE TABLE inv_component_adjustment_line;
TRUNCATE TABLE inv_component_batch;
TRUNCATE TABLE inv_component_batch_input;
TRUNCATE TABLE inv_component_daily_rollup;
TRUNCATE TABLE inv_component_monthly_opening;
TRUNCATE TABLE inv_component_monthly_opname;
TRUNCATE TABLE inv_component_movement_log;
TRUNCATE TABLE inv_component_opening;
TRUNCATE TABLE inv_component_opening_line;
TRUNCATE TABLE inv_component_stock_balance;
TRUNCATE TABLE inv_component_stock_opening_snapshot;
TRUNCATE TABLE inv_component_lot;
TRUNCATE TABLE inv_component_lot_issue_log;
TRUNCATE TABLE inv_material_source_balance;
TRUNCATE TABLE inv_material_fifo_lot;
TRUNCATE TABLE inv_material_fifo_issue_log;
TRUNCATE TABLE inv_material_fifo_issue_line;

-- ── Payroll ──────────────────────────────────────────────────
TRUNCATE TABLE pay_cash_advance;
TRUNCATE TABLE pay_cash_advance_installment;
TRUNCATE TABLE pay_manual_adjustment;
TRUNCATE TABLE pay_meal_disbursement;
TRUNCATE TABLE pay_meal_disbursement_line;
TRUNCATE TABLE pay_payroll_period;
TRUNCATE TABLE pay_payroll_result;
TRUNCATE TABLE pay_payroll_result_line;
TRUNCATE TABLE pay_salary_disbursement;
TRUNCATE TABLE pay_salary_disbursement_line;

-- ── Purchase ─────────────────────────────────────────────────
TRUNCATE TABLE pur_division_request;
TRUNCATE TABLE pur_division_request_line;
TRUNCATE TABLE pur_division_request_link;
TRUNCATE TABLE pur_purchase_order;
TRUNCATE TABLE pur_purchase_order_line;
TRUNCATE TABLE pur_purchase_payment_plan;
TRUNCATE TABLE pur_purchase_receipt;
TRUNCATE TABLE pur_purchase_receipt_line;
TRUNCATE TABLE pur_purchase_txn_log;
TRUNCATE TABLE pur_store_request;
TRUNCATE TABLE pur_store_request_approval;
TRUNCATE TABLE pur_store_request_fulfillment;
TRUNCATE TABLE pur_store_request_fulfillment_line;
TRUNCATE TABLE pur_store_request_line;
TRUNCATE TABLE pur_store_request_po_link;


TRUNCATE TABLE pos_order;
TRUNCATE TABLE pos_order_line;
TRUNCATE TABLE pos_order_line_extra;
TRUNCATE TABLE pos_order_state_log;
TRUNCATE TABLE pos_payment;
TRUNCATE TABLE pos_payment_deposit_apply;
TRUNCATE TABLE pos_payment_line;
TRUNCATE TABLE pos_refund;
TRUNCATE TABLE pos_refund_line;
TRUNCATE TABLE pos_void;
TRUNCATE TABLE pos_void_line;


SET FOREIGN_KEY_CHECKS = 1;





SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE pos_order;
TRUNCATE TABLE pos_order_line;
TRUNCATE TABLE pos_order_line_extra;
TRUNCATE TABLE pos_order_state_log;
TRUNCATE TABLE pos_payment;
TRUNCATE TABLE pos_payment_deposit_apply;
TRUNCATE TABLE pos_payment_line;
TRUNCATE TABLE pos_refund;
TRUNCATE TABLE pos_refund_line;
TRUNCATE TABLE pos_void;
TRUNCATE TABLE pos_void_line;
TRUNCATE TABLE pos_void_line_extra;


SET FOREIGN_KEY_CHECKS = 1;




SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE att_daily;
TRUNCATE TABLE att_employee_ph_ledger;
TRUNCATE TABLE att_overtime_entry;
TRUNCATE TABLE att_pending_request;
TRUNCATE TABLE att_pending_request_approval;
TRUNCATE TABLE att_presence;
TRUNCATE TABLE aud_transaction_log;
TRUNCATE TABLE fin_account_mutation_log;

TRUNCATE TABLE inv_component_adjustment;
TRUNCATE TABLE inv_component_adjustment_line;
TRUNCATE TABLE inv_component_batch;
TRUNCATE TABLE inv_component_batch_input;
TRUNCATE TABLE inv_component_lot;
TRUNCATE TABLE inv_component_lot_issue_line;
TRUNCATE TABLE inv_component_lot_issue_log;
TRUNCATE TABLE inv_component_monthly_opening;
TRUNCATE TABLE inv_component_monthly_opname;
TRUNCATE TABLE inv_component_monthly_stock;
TRUNCATE TABLE inv_component_movement_log;
TRUNCATE TABLE inv_component_opening;
TRUNCATE TABLE inv_component_opening_line;
TRUNCATE TABLE inv_component_stock_balance;
TRUNCATE TABLE inv_division_monthly_opening;
TRUNCATE TABLE inv_division_monthly_opname;
TRUNCATE TABLE inv_division_monthly_stock;
TRUNCATE TABLE inv_division_stock_opening_snapshot;
TRUNCATE TABLE inv_item_material_source_map;
TRUNCATE TABLE inv_item_material_txn;
TRUNCATE TABLE inv_item_source_balance;
TRUNCATE TABLE inv_material_fifo_issue_line;
TRUNCATE TABLE inv_material_fifo_issue_log;
TRUNCATE TABLE inv_material_fifo_lot;
TRUNCATE TABLE inv_material_source_balance;
TRUNCATE TABLE inv_stock_adjustment;
TRUNCATE TABLE inv_stock_adjustment_line;
TRUNCATE TABLE inv_stock_movement_log;
TRUNCATE TABLE inv_stock_opening_snapshot;
TRUNCATE TABLE inv_warehouse_monthly_opening;
TRUNCATE TABLE inv_warehouse_monthly_opname;
TRUNCATE TABLE inv_warehouse_monthly_stock;
TRUNCATE TABLE inv_warehouse_stock_opening_snapshot;

TRUNCATE TABLE pay_cash_advance;
TRUNCATE TABLE pay_cash_advance_installment;
TRUNCATE TABLE pay_manual_adjustment;
TRUNCATE TABLE pay_meal_disbursement;
TRUNCATE TABLE pay_meal_disbursement_line;
TRUNCATE TABLE pay_payroll_period;
TRUNCATE TABLE pay_payroll_result;
TRUNCATE TABLE pay_payroll_result_line;

TRUNCATE TABLE pos_cashier_session;
TRUNCATE TABLE pos_order;
TRUNCATE TABLE pos_order_line;
TRUNCATE TABLE pos_order_line_extra;
truncate TABLE pos_order_monitor_task;
TRUNCATE TABLE pos_order_state_log;
TRUNCATE TABLE pos_payment;
TRUNCATE TABLE pos_payment_deposit_apply;
TRUNCATE TABLE pos_payment_line;
TRUNCATE TABLE pos_printer_job;
TRUNCATE TABLE pos_printer_job_log;

TRUNCATE TABLE pos_product_availability_cache;
TRUNCATE TABLE pos_product_availability_override;
TRUNCATE TABLE pos_product_availability_probe;
TRUNCATE TABLE pos_product_availability_probe_line;
TRUNCATE TABLE pos_product_availability_rebuild_log;

TRUNCATE TABLE pos_refund;
TRUNCATE TABLE pos_refund_line;
TRUNCATE TABLE pos_runtime_job;

TRUNCATE TABLE pos_shift;
TRUNCATE TABLE pos_shift_account_summary;
TRUNCATE TABLE pos_shift_cash_denomination;
TRUNCATE TABLE pos_shift_summary;

TRUNCATE TABLE pos_stock_commit;
TRUNCATE TABLE pos_stock_commit_line;

TRUNCATE TABLE pos_void;
TRUNCATE TABLE pos_void_line;
TRUNCATE TABLE pos_void_line_extra;

TRUNCATE TABLE pur_division_request;
TRUNCATE TABLE pur_division_request_line;
TRUNCATE TABLE pur_division_request_link;
TRUNCATE TABLE pur_purchase_order;
TRUNCATE TABLE pur_purchase_order_line;
TRUNCATE TABLE pur_purchase_payment_plan;
TRUNCATE TABLE pur_purchase_receipt;
TRUNCATE TABLE pur_purchase_receipt_line;
TRUNCATE TABLE pur_purchase_txn_log;

TRUNCATE TABLE pur_store_request;
TRUNCATE TABLE pur_store_request_approval;
TRUNCATE TABLE pur_store_request_fulfillment;
TRUNCATE TABLE pur_store_request_fulfillment_line;
TRUNCATE TABLE pur_store_request_line;
TRUNCATE TABLE pur_store_request_po_link;


SET FOREIGN_KEY_CHECKS = 1;



