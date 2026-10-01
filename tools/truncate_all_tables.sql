-- ============================================================
-- TRUNCATE ALL TABLES — db_finance
-- Generated: 2026-05-18
--
-- Catatan:
--   • SET FOREIGN_KEY_CHECKS=0 menonaktifkan FK sementara
--     sehingga TRUNCATE bisa dijalankan tanpa mempedulikan urutan.
--   • TRUNCATE otomatis mereset AUTO_INCREMENT ke 1.
--   • SET FOREIGN_KEY_CHECKS=1 mengaktifkan kembali FK setelah selesai.
--
-- PERINGATAN: Script ini menghapus SELURUH data secara permanen.
--             Pastikan sudah backup sebelum menjalankan!
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── Attendance ──────────────────────────────────────────────
TRUNCATE TABLE att_attendance_policy;
TRUNCATE TABLE att_daily;
TRUNCATE TABLE att_employee_ph_ledger;
TRUNCATE TABLE att_holiday_calendar;
TRUNCATE TABLE att_location;
TRUNCATE TABLE att_overtime_entry;
TRUNCATE TABLE att_overtime_standard;
TRUNCATE TABLE att_pending_request;
TRUNCATE TABLE att_pending_request_approval;
TRUNCATE TABLE att_pending_submitter_position;
TRUNCATE TABLE att_pending_verifier_position;
TRUNCATE TABLE att_ph_eligibility;
TRUNCATE TABLE att_presence;
TRUNCATE TABLE att_shift;
TRUNCATE TABLE att_shift_schedule;

-- ── Audit ────────────────────────────────────────────────────
TRUNCATE TABLE aud_transaction_log;

-- ── Auth / RBAC ──────────────────────────────────────────────
TRUNCATE TABLE auth_role;
TRUNCATE TABLE auth_role_permission;
TRUNCATE TABLE auth_session_log;
TRUNCATE TABLE auth_user;
TRUNCATE TABLE auth_user_permission_override;
TRUNCATE TABLE auth_user_role;

-- ── CodeIgniter ──────────────────────────────────────────────
TRUNCATE TABLE ci_sessions;

-- ── Cost ─────────────────────────────────────────────────────
TRUNCATE TABLE cost_recalc_queue;

-- ── Finance ──────────────────────────────────────────────────
TRUNCATE TABLE fin_account_mutation_log;
TRUNCATE TABLE fin_company_account;

-- ── HR / Contract ────────────────────────────────────────────
TRUNCATE TABLE hr_contract;
TRUNCATE TABLE hr_contract_approval;
TRUNCATE TABLE hr_contract_comp_snapshot;
TRUNCATE TABLE hr_contract_comp_snapshot_line;
TRUNCATE TABLE hr_contract_signature;
TRUNCATE TABLE hr_contract_template;

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

-- ── Master Data ──────────────────────────────────────────────
TRUNCATE TABLE mst_bank;
TRUNCATE TABLE mst_component;
TRUNCATE TABLE mst_component_category;
TRUNCATE TABLE mst_component_formula;
TRUNCATE TABLE mst_extra;
TRUNCATE TABLE mst_extra_group;
TRUNCATE TABLE mst_extra_group_item;
TRUNCATE TABLE mst_item;
TRUNCATE TABLE mst_item_category;
TRUNCATE TABLE mst_material;
TRUNCATE TABLE mst_operational_division;
TRUNCATE TABLE mst_posting_type;
TRUNCATE TABLE mst_product;
TRUNCATE TABLE mst_product_category;
TRUNCATE TABLE mst_product_classification;
TRUNCATE TABLE mst_product_division;
TRUNCATE TABLE mst_product_extra_map;
TRUNCATE TABLE mst_product_recipe;
TRUNCATE TABLE mst_purchase_catalog;
TRUNCATE TABLE mst_purchase_type;
TRUNCATE TABLE mst_uom;
TRUNCATE TABLE mst_uom_conversion;
TRUNCATE TABLE mst_variable_cost_default;
TRUNCATE TABLE mst_vendor;
TRUNCATE TABLE mst_vendor_item;

-- ── Organisation ─────────────────────────────────────────────
TRUNCATE TABLE org_division;
TRUNCATE TABLE org_employee;
TRUNCATE TABLE org_position;

-- ── Payroll ──────────────────────────────────────────────────
TRUNCATE TABLE pay_basic_salary_standard;
TRUNCATE TABLE pay_cash_advance;
TRUNCATE TABLE pay_cash_advance_installment;
TRUNCATE TABLE pay_manual_adjustment;
TRUNCATE TABLE pay_meal_disbursement;
TRUNCATE TABLE pay_meal_disbursement_line;
TRUNCATE TABLE pay_objective_override;
TRUNCATE TABLE pay_payroll_period;
TRUNCATE TABLE pay_payroll_result;
TRUNCATE TABLE pay_payroll_result_line;
TRUNCATE TABLE pay_salary_assignment;
TRUNCATE TABLE pay_salary_component;
TRUNCATE TABLE pay_salary_disbursement;
TRUNCATE TABLE pay_salary_disbursement_line;
TRUNCATE TABLE pay_salary_profile;
TRUNCATE TABLE pay_salary_profile_line;

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

-- ── System / UI ──────────────────────────────────────────────
TRUNCATE TABLE sys_menu;
TRUNCATE TABLE sys_page;
TRUNCATE TABLE sys_sidebar_favorite;

SET FOREIGN_KEY_CHECKS = 1;
