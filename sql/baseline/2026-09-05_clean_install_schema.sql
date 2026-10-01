-- Finance clean-install schema baseline.
-- Schema only: no customer data, users, credentials, or operational backup tables.
-- Seed/bootstrap data is intentionally managed separately.
/*M!999999\- enable the sandbox mode */ 
SET FOREIGN_KEY_CHECKS=0;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_category` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_code` varchar(40) NOT NULL,
  `category_name` varchar(120) NOT NULL,
  `default_depreciation_method` enum('NONE','STRAIGHT_LINE') NOT NULL DEFAULT 'STRAIGHT_LINE',
  `default_useful_life_months` smallint(5) unsigned NOT NULL DEFAULT 36,
  `default_residual_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_asset_category_code` (`category_code`) USING BTREE,
  KEY `idx_asset_category_active` (`is_active`,`sort_order`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kategori master aset fisik';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_depreciation_run` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_no` varchar(60) NOT NULL,
  `period_month` char(7) NOT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('DRAFT','POSTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  `total_assets` int(11) NOT NULL DEFAULT 0,
  `total_depreciation` decimal(18,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_asset_dep_run_no` (`run_no`) USING BTREE,
  UNIQUE KEY `uk_asset_dep_run_scope` (`period_month`,`division_id`) USING BTREE,
  KEY `idx_asset_dep_run_status` (`status`,`period_month`) USING BTREE,
  KEY `idx_asset_dep_run_division` (`division_id`) USING BTREE,
  CONSTRAINT `fk_asset_dep_run_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Header staging jurnal penyusutan aset';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_depreciation_run_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` bigint(20) unsigned NOT NULL,
  `asset_id` bigint(20) unsigned NOT NULL,
  `acquisition_cost` decimal(18,2) NOT NULL DEFAULT 0.00,
  `book_value_before` decimal(18,2) NOT NULL DEFAULT 0.00,
  `depreciation_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `book_value_after` decimal(18,2) NOT NULL DEFAULT 0.00,
  `expense_account_code` varchar(60) DEFAULT NULL,
  `accumulated_account_code` varchar(60) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_asset_dep_line_asset` (`run_id`,`asset_id`) USING BTREE,
  KEY `idx_asset_dep_line_asset` (`asset_id`) USING BTREE,
  CONSTRAINT `fk_asset_dep_line_asset` FOREIGN KEY (`asset_id`) REFERENCES `asset_item` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asset_dep_line_run` FOREIGN KEY (`run_id`) REFERENCES `asset_depreciation_run` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detail staging jurnal penyusutan aset';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_event` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint(20) unsigned NOT NULL,
  `event_type` enum('ACQUIRE','UPDATE','DAMAGE','REPAIR','TRANSFER','RECON','RETIRED','LOST','DISPOSED','ADJUSTMENT') NOT NULL,
  `event_date` date NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) DEFAULT NULL,
  `from_division_id` bigint(20) unsigned DEFAULT NULL,
  `to_division_id` bigint(20) unsigned DEFAULT NULL,
  `condition_score_before` tinyint(3) unsigned DEFAULT NULL,
  `condition_score_after` tinyint(3) unsigned DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `reason` text DEFAULT NULL,
  `evidence_path` varchar(255) DEFAULT NULL,
  `evidence_mime` varchar(80) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_asset_event_asset_date` (`asset_id`,`event_date`) USING BTREE,
  KEY `idx_asset_event_type_date` (`event_type`,`event_date`) USING BTREE,
  KEY `idx_asset_event_created_by` (`created_by`) USING BTREE,
  CONSTRAINT `fk_asset_event_asset` FOREIGN KEY (`asset_id`) REFERENCES `asset_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail perubahan dan bukti aset';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_item` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_code` varchar(50) NOT NULL,
  `asset_name` varchar(160) NOT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model_name` varchar(120) DEFAULT NULL,
  `serial_no` varchar(120) DEFAULT NULL,
  `batch_no` varchar(80) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `acquisition_date` date DEFAULT NULL,
  `acquisition_cost` decimal(18,2) NOT NULL DEFAULT 0.00,
  `residual_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `useful_life_months` smallint(5) unsigned NOT NULL DEFAULT 36,
  `depreciation_method` enum('NONE','STRAIGHT_LINE') NOT NULL DEFAULT 'STRAIGHT_LINE',
  `depreciation_start_month` char(7) DEFAULT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `outlet_id` bigint(20) unsigned DEFAULT NULL,
  `current_location` varchar(160) DEFAULT NULL,
  `custodian_employee_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('ACTIVE','BROKEN','REPAIR','LOST','RETIRED','DISPOSED') NOT NULL DEFAULT 'ACTIVE',
  `master_lock_status` enum('OPEN','LOCKED') NOT NULL DEFAULT 'OPEN',
  `master_locked_by` bigint(20) unsigned DEFAULT NULL,
  `master_locked_at` datetime DEFAULT NULL,
  `condition_score` tinyint(3) unsigned NOT NULL DEFAULT 100,
  `photo_path` varchar(255) DEFAULT NULL,
  `photo_mime` varchar(80) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_asset_item_code` (`asset_code`) USING BTREE,
  KEY `idx_asset_item_category` (`category_id`) USING BTREE,
  KEY `idx_asset_item_status` (`status`) USING BTREE,
  KEY `idx_asset_item_division` (`division_id`) USING BTREE,
  KEY `idx_asset_item_outlet` (`outlet_id`) USING BTREE,
  KEY `idx_asset_item_custodian` (`custodian_employee_id`) USING BTREE,
  KEY `idx_asset_item_purchase_date` (`purchase_date`) USING BTREE,
  KEY `idx_asset_item_batch` (`batch_no`) USING BTREE,
  KEY `idx_asset_item_master_lock` (`master_lock_status`,`division_id`,`id`) USING BTREE,
  CONSTRAINT `fk_asset_item_category` FOREIGN KEY (`category_id`) REFERENCES `asset_category` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_asset_item_custodian` FOREIGN KEY (`custodian_employee_id`) REFERENCES `org_employee` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_asset_item_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_asset_item_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Unit aset fisik satu per satu';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_master_change_request` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_no` varchar(60) NOT NULL,
  `asset_id` bigint(20) unsigned NOT NULL,
  `status` enum('PENDING','APPROVED','REJECTED','POSTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `before_snapshot` longtext NOT NULL,
  `requested_snapshot` longtext NOT NULL,
  `change_summary` text DEFAULT NULL,
  `reason` text NOT NULL,
  `evidence_path` varchar(255) DEFAULT NULL,
  `evidence_mime` varchar(80) DEFAULT NULL,
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_by` bigint(20) unsigned DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_asset_master_change_request_no` (`request_no`) USING BTREE,
  KEY `idx_asset_master_change_asset_status` (`asset_id`,`status`,`created_at`) USING BTREE,
  KEY `idx_asset_master_change_status_date` (`status`,`created_at`) USING BTREE,
  KEY `idx_asset_master_change_requested_by` (`requested_by`) USING BTREE,
  CONSTRAINT `fk_asset_master_change_asset` FOREIGN KEY (`asset_id`) REFERENCES `asset_item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pengajuan perubahan data master aset setelah aset dikunci';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_recon` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recon_no` varchar(50) NOT NULL,
  `period_month` char(7) NOT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('DRAFT','POSTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  `notes` text DEFAULT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `posted_at` datetime DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_asset_recon_no` (`recon_no`) USING BTREE,
  UNIQUE KEY `uk_asset_recon_scope` (`period_month`,`division_id`) USING BTREE,
  KEY `idx_asset_recon_status` (`status`,`period_month`) USING BTREE,
  KEY `idx_asset_recon_division` (`division_id`) USING BTREE,
  CONSTRAINT `fk_asset_recon_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Header rekon aset bulanan';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_recon_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recon_id` bigint(20) unsigned NOT NULL,
  `asset_id` bigint(20) unsigned NOT NULL,
  `expected_status` varchar(30) NOT NULL,
  `physical_status` enum('NOT_CHECKED','OK','BROKEN','MISSING','NEED_REPAIR','EXTRA_FOUND') NOT NULL DEFAULT 'NOT_CHECKED',
  `condition_score` tinyint(3) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `evidence_path` varchar(255) DEFAULT NULL,
  `evidence_mime` varchar(80) DEFAULT NULL,
  `checked_by` bigint(20) unsigned DEFAULT NULL,
  `checked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_asset_recon_line_asset` (`recon_id`,`asset_id`) USING BTREE,
  KEY `idx_asset_recon_line_asset` (`asset_id`) USING BTREE,
  KEY `idx_asset_recon_line_status` (`physical_status`) USING BTREE,
  CONSTRAINT `fk_asset_recon_line_asset` FOREIGN KEY (`asset_id`) REFERENCES `asset_item` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asset_recon_line_recon` FOREIGN KEY (`recon_id`) REFERENCES `asset_recon` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detail hasil cek fisik rekon aset';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_workflow` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workflow_type` enum('TRANSFER','HANDOVER','MAINTENANCE','DISPOSAL') NOT NULL,
  `workflow_no` varchar(60) NOT NULL,
  `asset_id` bigint(20) unsigned NOT NULL,
  `workflow_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('PENDING','APPROVED','REJECTED','POSTED','DONE','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `from_division_id` bigint(20) unsigned DEFAULT NULL,
  `to_division_id` bigint(20) unsigned DEFAULT NULL,
  `from_outlet_id` bigint(20) unsigned DEFAULT NULL,
  `to_outlet_id` bigint(20) unsigned DEFAULT NULL,
  `from_location` varchar(160) DEFAULT NULL,
  `to_location` varchar(160) DEFAULT NULL,
  `from_employee_id` bigint(20) unsigned DEFAULT NULL,
  `to_employee_id` bigint(20) unsigned DEFAULT NULL,
  `maintenance_type` varchar(80) DEFAULT NULL,
  `priority` enum('LOW','NORMAL','HIGH','URGENT') NOT NULL DEFAULT 'NORMAL',
  `vendor_name` varchar(160) DEFAULT NULL,
  `disposal_type` enum('RETIRED','DISPOSED','SOLD','DONATED') DEFAULT NULL,
  `estimated_cost` decimal(18,2) NOT NULL DEFAULT 0.00,
  `actual_cost` decimal(18,2) NOT NULL DEFAULT 0.00,
  `disposal_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `reason` text DEFAULT NULL,
  `evidence_path` varchar(255) DEFAULT NULL,
  `evidence_mime` varchar(80) DEFAULT NULL,
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `completed_by` bigint(20) unsigned DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_asset_workflow_no` (`workflow_no`) USING BTREE,
  KEY `idx_asset_workflow_type_status` (`workflow_type`,`status`) USING BTREE,
  KEY `idx_asset_workflow_asset` (`asset_id`) USING BTREE,
  KEY `idx_asset_workflow_date` (`workflow_date`) USING BTREE,
  KEY `idx_asset_workflow_due` (`due_date`) USING BTREE,
  KEY `idx_asset_workflow_to_division` (`to_division_id`) USING BTREE,
  KEY `idx_asset_workflow_to_employee` (`to_employee_id`) USING BTREE,
  CONSTRAINT `fk_asset_workflow_asset` FOREIGN KEY (`asset_id`) REFERENCES `asset_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Workflow mutasi, handover, maintenance, dan disposal aset';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_attendance_policy` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `policy_code` varchar(40) NOT NULL,
  `policy_name` varchar(120) NOT NULL,
  `checkin_open_minutes_before` int(10) unsigned NOT NULL DEFAULT 30,
  `checkout_close_minutes_after` int(10) unsigned NOT NULL DEFAULT 180,
  `enforce_geofence` tinyint(1) NOT NULL DEFAULT 1,
  `require_photo` tinyint(1) NOT NULL DEFAULT 0,
  `pending_request_scope` enum('SELF_ONLY','POSITION_ONLY','SELF_AND_POSITION') NOT NULL DEFAULT 'SELF_ONLY',
  `attendance_revision_window_mode` enum('OFF','ON','BY_DAYS') NOT NULL DEFAULT 'ON',
  `attendance_revision_window_days` int(10) unsigned NOT NULL DEFAULT 7,
  `pending_approval_levels` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `late_deduction_per_minute` decimal(18,2) NOT NULL DEFAULT 0.00,
  `enable_late_deduction` tinyint(1) NOT NULL DEFAULT 1,
  `alpha_deduction_per_day` decimal(18,2) NOT NULL DEFAULT 0.00,
  `enable_alpha_deduction` tinyint(1) NOT NULL DEFAULT 1,
  `use_basic_salary_daily_rate` tinyint(1) NOT NULL DEFAULT 1,
  `default_work_days_per_month` int(10) unsigned NOT NULL DEFAULT 26,
  `attendance_calc_mode` enum('DAILY','MONTHLY') NOT NULL DEFAULT 'DAILY',
  `payroll_late_deduction_scope` enum('BASIC_ONLY','THP_TOTAL') NOT NULL DEFAULT 'BASIC_ONLY',
  `prorate_deduction_scope` enum('BASIC_ONLY','THP_TOTAL') NOT NULL DEFAULT 'BASIC_ONLY',
  `allowance_late_treatment` enum('FULL_IF_PRESENT','DEDUCT_IF_LATE') NOT NULL DEFAULT 'FULL_IF_PRESENT',
  `meal_calc_mode` enum('MONTHLY','CUSTOM') NOT NULL DEFAULT 'MONTHLY',
  `overtime_calc_mode` enum('AUTO','MANUAL') NOT NULL DEFAULT 'AUTO',
  `default_overtime_standard_id` bigint(20) unsigned DEFAULT NULL,
  `operation_start_time` time DEFAULT NULL,
  `operation_end_time` time DEFAULT NULL,
  `night_shift_checkout_credit_after` time DEFAULT NULL,
  `night_shift_checkout_credit_to_operation_end` tinyint(1) NOT NULL DEFAULT 1,
  `ph_attendance_mode` enum('AUTO_PRESENT','MANUAL_CLOCK') NOT NULL DEFAULT 'AUTO_PRESENT',
  `ph_grant_mode` enum('SHIFT_ONLY','HOLIDAY_ONLY','SHIFT_OR_HOLIDAY') NOT NULL DEFAULT 'HOLIDAY_ONLY',
  `ph_grant_holiday_type` enum('ANY','NATIONAL','COMPANY','SPECIAL') NOT NULL DEFAULT 'ANY',
  `ph_grant_requires_checkout` tinyint(1) NOT NULL DEFAULT 1,
  `ph_grant_qty_per_day` decimal(6,2) NOT NULL DEFAULT 1.00,
  `ph_auto_presence_on_open` tinyint(1) NOT NULL DEFAULT 1,
  `ph_requires_clock_in_out` tinyint(1) NOT NULL DEFAULT 0,
  `ph_expiry_months` int(10) unsigned NOT NULL DEFAULT 3,
  `ph_gets_meal_allowance` tinyint(1) NOT NULL DEFAULT 0,
  `ph_gets_bonus` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_attendance_policy_code` (`policy_code`) USING BTREE,
  KEY `idx_att_policy_default_overtime_standard` (`default_overtime_standard_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_daily` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `attendance_date` date NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `checkin_at` datetime DEFAULT NULL,
  `checkout_at` datetime DEFAULT NULL,
  `attendance_status` enum('PRESENT','LATE','ALPHA','SICK','LEAVE','OFF','HOLIDAY') NOT NULL DEFAULT 'OFF',
  `work_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `late_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `early_leave_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `overtime_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `overtime_pay` decimal(18,2) NOT NULL DEFAULT 0.00,
  `manual_addition_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `manual_deduction_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `manual_adjustment_net_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `daily_salary_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_type` enum('AUTO','MANUAL','PENDING_APPROVAL') NOT NULL DEFAULT 'AUTO',
  `policy_snapshot_id` bigint(20) unsigned DEFAULT NULL,
  `policy_snapshot_code` varchar(40) DEFAULT NULL,
  `policy_snapshot_name` varchar(120) DEFAULT NULL,
  `attendance_mode_snapshot` varchar(20) DEFAULT NULL,
  `meal_mode_snapshot` varchar(20) DEFAULT NULL,
  `prorate_scope_snapshot` varchar(20) DEFAULT NULL,
  `overtime_mode_snapshot` varchar(20) DEFAULT NULL,
  `allowance_late_treatment_snapshot` varchar(30) DEFAULT NULL,
  `enable_late_deduction_snapshot` tinyint(1) NOT NULL DEFAULT 1,
  `enable_alpha_deduction_snapshot` tinyint(1) NOT NULL DEFAULT 1,
  `late_deduction_per_minute_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `alpha_deduction_per_day_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `work_days_snapshot` int(10) unsigned NOT NULL DEFAULT 26,
  `snapshot_basic_salary` decimal(18,2) DEFAULT NULL,
  `snapshot_position_allowance` decimal(18,2) DEFAULT NULL,
  `snapshot_objective_allowance` decimal(18,2) DEFAULT NULL,
  `snapshot_meal_rate` decimal(18,2) DEFAULT NULL,
  `snapshot_overtime_rate` decimal(18,2) DEFAULT NULL,
  `compensation_contract_id` bigint(20) unsigned DEFAULT NULL,
  `compensation_snapshot_id` bigint(20) unsigned DEFAULT NULL,
  `compensation_source` varchar(30) DEFAULT NULL,
  `compensation_resolved_at` datetime DEFAULT NULL,
  `basic_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `allowance_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `meal_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `late_deduction_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `alpha_deduction_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `gross_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_daily_employee_date` (`employee_id`,`attendance_date`) USING BTREE,
  KEY `idx_att_daily_date` (`attendance_date`) USING BTREE,
  KEY `idx_att_daily_status` (`attendance_status`) USING BTREE,
  KEY `fk_att_daily_shift` (`shift_id`) USING BTREE,
  KEY `idx_att_daily_compensation_contract` (`compensation_contract_id`,`attendance_date`) USING BTREE,
  CONSTRAINT `fk_att_daily_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_att_daily_shift` FOREIGN KEY (`shift_id`) REFERENCES `att_shift` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_employee_ph_ledger` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `tx_date` date NOT NULL,
  `tx_type` enum('GRANT','USE','EXPIRE','ADJUST','VOID') NOT NULL,
  `qty_days` decimal(8,2) NOT NULL DEFAULT 0.00,
  `expired_at` date DEFAULT NULL,
  `ref_table` varchar(40) NOT NULL DEFAULT '',
  `ref_id` bigint(20) unsigned DEFAULT NULL,
  `entry_mode` enum('AUTO','MANUAL','MIGRATION') NOT NULL DEFAULT 'AUTO',
  `notes` varchar(255) DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `voided_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_employee_ph_ledger_grant_ref` (`employee_id`,`tx_type`,`ref_table`,`ref_id`) USING BTREE,
  KEY `idx_att_employee_ph_ledger_emp_date` (`employee_id`,`tx_date`) USING BTREE,
  KEY `idx_att_employee_ph_ledger_type_date` (`tx_type`,`tx_date`) USING BTREE,
  KEY `idx_att_employee_ph_ledger_ref` (`ref_table`,`ref_id`) USING BTREE,
  KEY `fk_att_employee_ph_ledger_created_by` (`created_by`) USING BTREE,
  KEY `idx_att_employee_ph_ledger_type_date_v2` (`tx_type`,`tx_date`) USING BTREE,
  CONSTRAINT `fk_att_employee_ph_ledger_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_att_employee_ph_ledger_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_holiday_calendar` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `holiday_date` date NOT NULL,
  `holiday_name` varchar(150) NOT NULL,
  `holiday_type` enum('NATIONAL','COMPANY','SPECIAL') NOT NULL DEFAULT 'NATIONAL',
  `source_ref` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_holiday_date_name` (`holiday_date`,`holiday_name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_location` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `location_code` varchar(40) NOT NULL,
  `location_name` varchar(120) NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `radius_meter` decimal(10,2) NOT NULL DEFAULT 100.00,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_location_code` (`location_code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_overtime_entry` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `overtime_standard_id` bigint(20) unsigned DEFAULT NULL,
  `overtime_date` date NOT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime NOT NULL,
  `overtime_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `overtime_rate` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_overtime_pay` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_att_overtime_employee_date` (`employee_id`,`overtime_date`) USING BTREE,
  KEY `idx_att_overtime_status` (`status`) USING BTREE,
  KEY `fk_att_overtime_approver` (`approved_by`) USING BTREE,
  KEY `fk_att_overtime_entry_standard` (`overtime_standard_id`) USING BTREE,
  CONSTRAINT `fk_att_overtime_approver` FOREIGN KEY (`approved_by`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_att_overtime_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_att_overtime_entry_standard` FOREIGN KEY (`overtime_standard_id`) REFERENCES `att_overtime_standard` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_overtime_standard` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `standard_code` varchar(40) NOT NULL,
  `standard_name` varchar(120) NOT NULL,
  `hourly_rate` decimal(18,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_overtime_standard_code` (`standard_code`) USING BTREE,
  KEY `idx_att_overtime_standard_active_rate` (`is_active`,`hourly_rate`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_pending_request` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `request_date` date NOT NULL,
  `request_type` enum('MISSING_CHECKIN','MISSING_CHECKOUT','STATUS_CORRECTION','OVERTIME','LEAVE','SICK') NOT NULL,
  `requested_checkin_at` datetime DEFAULT NULL,
  `requested_checkout_at` datetime DEFAULT NULL,
  `requested_status` enum('PRESENT','LATE','ALPHA','SICK','LEAVE','OFF','HOLIDAY') DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('PENDING','APPROVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_att_pending_employee_date` (`employee_id`,`request_date`) USING BTREE,
  KEY `idx_att_pending_status` (`status`) USING BTREE,
  KEY `fk_att_pending_approver` (`approved_by`) USING BTREE,
  CONSTRAINT `fk_att_pending_approver` FOREIGN KEY (`approved_by`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_att_pending_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_pending_request_approval` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pending_request_id` bigint(20) unsigned NOT NULL,
  `approval_level` tinyint(3) unsigned NOT NULL,
  `approver_employee_id` bigint(20) unsigned DEFAULT NULL,
  `action` enum('APPROVED','REJECTED') NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `acted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_pending_req_approval_level` (`pending_request_id`,`approval_level`) USING BTREE,
  KEY `idx_att_pending_req_approval_pending` (`pending_request_id`) USING BTREE,
  KEY `idx_att_pending_req_approval_actor` (`approver_employee_id`) USING BTREE,
  CONSTRAINT `fk_att_pending_req_approval_actor` FOREIGN KEY (`approver_employee_id`) REFERENCES `org_employee` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_att_pending_req_approval_pending` FOREIGN KEY (`pending_request_id`) REFERENCES `att_pending_request` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_pending_submitter_position` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `policy_id` bigint(20) unsigned NOT NULL,
  `position_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_pending_submitter_policy_pos` (`policy_id`,`position_id`) USING BTREE,
  KEY `idx_att_pending_submitter_pos` (`position_id`) USING BTREE,
  CONSTRAINT `fk_att_pending_submitter_policy` FOREIGN KEY (`policy_id`) REFERENCES `att_attendance_policy` (`id`),
  CONSTRAINT `fk_att_pending_submitter_position` FOREIGN KEY (`position_id`) REFERENCES `org_position` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_pending_verifier_position` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `policy_id` bigint(20) unsigned NOT NULL,
  `verify_level` tinyint(3) unsigned NOT NULL,
  `position_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_pending_verifier_policy_level_pos` (`policy_id`,`verify_level`,`position_id`) USING BTREE,
  KEY `idx_att_pending_verifier_pos` (`position_id`) USING BTREE,
  CONSTRAINT `fk_att_pending_verifier_policy` FOREIGN KEY (`policy_id`) REFERENCES `att_attendance_policy` (`id`),
  CONSTRAINT `fk_att_pending_verifier_position` FOREIGN KEY (`position_id`) REFERENCES `org_position` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_ph_cutover_migration_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `migration_code` varchar(80) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `finance_ledger_id` bigint(20) unsigned DEFAULT NULL,
  `source_finance_daily_id` bigint(20) unsigned DEFAULT NULL,
  `source_core_employee_id` bigint(20) unsigned DEFAULT NULL,
  `source_core_ledger_id` bigint(20) unsigned DEFAULT NULL,
  `before_snapshot` text DEFAULT NULL,
  `after_snapshot` text DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `executed_by` bigint(20) unsigned DEFAULT NULL,
  `executed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_ph_cutover_audit_finance` (`migration_code`,`action_type`,`finance_ledger_id`) USING BTREE,
  UNIQUE KEY `uk_att_ph_cutover_audit_core` (`migration_code`,`action_type`,`source_core_ledger_id`) USING BTREE,
  KEY `idx_att_ph_cutover_audit_employee` (`employee_id`,`executed_at`) USING BTREE,
  KEY `idx_att_ph_cutover_audit_core_employee` (`source_core_employee_id`,`source_core_ledger_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Jejak migrasi saldo awal PH dari core pada cutover Finance 2026-06-01';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_ph_cutover_v3_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `migration_code` varchar(80) NOT NULL,
  `action_type` varchar(60) NOT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `finance_ledger_id` bigint(20) unsigned DEFAULT NULL,
  `related_finance_ledger_id` bigint(20) unsigned DEFAULT NULL,
  `source_core_employee_id` bigint(20) unsigned DEFAULT NULL,
  `source_core_ledger_id` bigint(20) unsigned DEFAULT NULL,
  `source_use_ledger_id` bigint(20) unsigned DEFAULT NULL,
  `before_snapshot` text DEFAULT NULL,
  `after_snapshot` text DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `executed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_ph_cutover_v3_audit_action_ledger` (`migration_code`,`action_type`,`finance_ledger_id`) USING BTREE,
  KEY `idx_att_ph_cutover_v3_audit_employee` (`employee_id`,`executed_at`) USING BTREE,
  KEY `idx_att_ph_cutover_v3_audit_source_core` (`source_core_employee_id`,`source_core_ledger_id`) USING BTREE,
  KEY `idx_att_ph_cutover_v3_audit_source_use` (`source_use_ledger_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Audit migrasi saldo pembuka PH V3 dari core.org_employee.ph';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_ph_eligibility` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `is_eligible` tinyint(1) NOT NULL DEFAULT 1,
  `effective_date` date NOT NULL,
  `expiry_months_override` int(10) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_ph_eligibility_employee` (`employee_id`) USING BTREE,
  KEY `idx_att_ph_eligibility_active` (`is_eligible`,`effective_date`) USING BTREE,
  KEY `fk_att_ph_eligibility_created_by` (`created_by`) USING BTREE,
  CONSTRAINT `fk_att_ph_eligibility_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_att_ph_eligibility_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_ph_ledger_reconciliation_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reconciliation_code` varchar(80) NOT NULL,
  `action_type` enum('RECLASSIFY_GRANT_TO_USE','INSERT_GRANT','INSERT_USE') NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `source_daily_id` bigint(20) unsigned NOT NULL,
  `ledger_id` bigint(20) unsigned DEFAULT NULL,
  `before_snapshot` text DEFAULT NULL,
  `after_snapshot` text DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `executed_by` bigint(20) unsigned DEFAULT NULL,
  `executed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_ph_reconciliation_source_action` (`reconciliation_code`,`action_type`,`source_daily_id`) USING BTREE,
  KEY `idx_att_ph_reconciliation_employee` (`employee_id`,`executed_at`) USING BTREE,
  KEY `idx_att_ph_reconciliation_ledger` (`ledger_id`) USING BTREE,
  KEY `fk_att_ph_reconciliation_executed_by` (`executed_by`) USING BTREE,
  CONSTRAINT `fk_att_ph_reconciliation_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_att_ph_reconciliation_executed_by` FOREIGN KEY (`executed_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Audit rekonsiliasi ledger PH historis';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_presence` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `attendance_time` time NOT NULL,
  `attendance_at` datetime NOT NULL,
  `event_type` enum('CHECKIN','CHECKOUT') NOT NULL,
  `source_type` enum('GPS','DEVICE','MANUAL') NOT NULL DEFAULT 'GPS',
  `location_id` bigint(20) unsigned DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_att_presence_employee_date` (`employee_id`,`attendance_date`) USING BTREE,
  KEY `idx_att_presence_shift` (`shift_id`) USING BTREE,
  KEY `idx_att_presence_event_at` (`attendance_at`) USING BTREE,
  KEY `fk_att_presence_location` (`location_id`) USING BTREE,
  CONSTRAINT `fk_att_presence_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_att_presence_location` FOREIGN KEY (`location_id`) REFERENCES `att_location` (`id`),
  CONSTRAINT `fk_att_presence_shift` FOREIGN KEY (`shift_id`) REFERENCES `att_shift` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_schedule_monthly_override` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `month_start` date NOT NULL,
  `base_limit_days` int(10) unsigned NOT NULL,
  `approved_limit_days` int(10) unsigned NOT NULL,
  `reason` varchar(255) NOT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_schedule_monthly_override_employee_month` (`employee_id`,`month_start`) USING BTREE,
  KEY `idx_att_schedule_monthly_override_month` (`month_start`,`employee_id`) USING BTREE,
  KEY `fk_att_schedule_monthly_override_approved_by` (`approved_by`) USING BTREE,
  CONSTRAINT `fk_att_schedule_monthly_override_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_att_schedule_monthly_override_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Persetujuan otoritas untuk jadwal pegawai melampaui batas hari kerja bulanan';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_schedule_monthly_override_position` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `policy_id` bigint(20) unsigned NOT NULL,
  `position_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_schedule_monthly_override_position` (`policy_id`,`position_id`) USING BTREE,
  KEY `idx_att_schedule_monthly_override_position_position` (`position_id`) USING BTREE,
  CONSTRAINT `fk_att_schedule_monthly_override_position_policy` FOREIGN KEY (`policy_id`) REFERENCES `att_attendance_policy` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_att_schedule_monthly_override_position_position` FOREIGN KEY (`position_id`) REFERENCES `org_position` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Jabatan yang boleh mengoverride batas jadwal bulanan';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_schedule_monthly_override_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `policy_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_schedule_monthly_override_user` (`policy_id`,`user_id`) USING BTREE,
  KEY `idx_att_schedule_monthly_override_user_user` (`user_id`) USING BTREE,
  CONSTRAINT `fk_att_schedule_monthly_override_user_policy` FOREIGN KEY (`policy_id`) REFERENCES `att_attendance_policy` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_att_schedule_monthly_override_user_user` FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='User spesifik yang boleh mengoverride batas jadwal bulanan';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_shift` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shift_code` varchar(40) NOT NULL,
  `shift_name` varchar(120) NOT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_overnight` tinyint(1) NOT NULL DEFAULT 0,
  `grace_late_minute` int(10) unsigned NOT NULL DEFAULT 0,
  `overtime_after_minute` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_shift_code` (`shift_code`) USING BTREE,
  KEY `idx_att_shift_division` (`division_id`) USING BTREE,
  CONSTRAINT `fk_att_shift_division` FOREIGN KEY (`division_id`) REFERENCES `org_division` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `att_shift_schedule` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `shift_id` bigint(20) unsigned NOT NULL,
  `schedule_date` date NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_att_shift_schedule_unique` (`employee_id`,`schedule_date`) USING BTREE,
  KEY `idx_att_shift_schedule_date` (`schedule_date`) USING BTREE,
  KEY `idx_att_shift_schedule_shift` (`shift_id`) USING BTREE,
  KEY `fk_att_shift_schedule_created_by` (`created_by`) USING BTREE,
  CONSTRAINT `fk_att_shift_schedule_created_by` FOREIGN KEY (`created_by`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_att_shift_schedule_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_att_shift_schedule_shift` FOREIGN KEY (`shift_id`) REFERENCES `att_shift` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `aud_transaction_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `module_code` varchar(40) NOT NULL,
  `action_code` varchar(40) NOT NULL,
  `entity_table` varchar(80) NOT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `transaction_no` varchar(80) DEFAULT NULL,
  `ref_table` varchar(80) DEFAULT NULL,
  `ref_id` bigint(20) unsigned DEFAULT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `source_ip` varchar(45) DEFAULT NULL,
  `before_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `after_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_aud_transaction_module` (`module_code`) USING BTREE,
  KEY `idx_aud_transaction_entity` (`entity_table`,`entity_id`) USING BTREE,
  KEY `idx_aud_transaction_no` (`transaction_no`) USING BTREE,
  KEY `idx_aud_transaction_ref` (`ref_table`,`ref_id`) USING BTREE,
  KEY `idx_aud_transaction_actor` (`actor_user_id`) USING BTREE,
  KEY `idx_aud_transaction_created` (`created_at`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_login_failure` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `failed_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_auth_login_failure_user_time` (`user_id`,`failed_at`),
  KEY `idx_auth_login_failure_ip_time` (`ip_address`,`failed_at`),
  CONSTRAINT `fk_auth_login_failure_user` FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Append-only failed web login audit for bounded throttling';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_role` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_code` varchar(50) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `division_scope_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Opsional: scope role ke divisi tertentu (kitchen, bar, dll). NULL = lintas divisi.',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `permissions_updated_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_auth_role_code` (`role_code`) USING BTREE,
  KEY `idx_auth_role_division` (`division_scope_id`) USING BTREE,
  CONSTRAINT `fk_auth_role_division` FOREIGN KEY (`division_scope_id`) REFERENCES `org_division` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Role / grup izin akses';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_role_permission` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned NOT NULL,
  `page_id` bigint(20) unsigned NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT 0,
  `can_create` tinyint(1) NOT NULL DEFAULT 0,
  `can_edit` tinyint(1) NOT NULL DEFAULT 0,
  `can_delete` tinyint(1) NOT NULL DEFAULT 0,
  `can_export` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_role_page` (`role_id`,`page_id`) USING BTREE,
  KEY `idx_auth_role_perm_role` (`role_id`) USING BTREE,
  KEY `idx_auth_role_perm_page` (`page_id`) USING BTREE,
  CONSTRAINT `fk_auth_role_perm_page` FOREIGN KEY (`page_id`) REFERENCES `sys_page` (`id`),
  CONSTRAINT `fk_auth_role_perm_role` FOREIGN KEY (`role_id`) REFERENCES `auth_role` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Izin CRUD per role per halaman';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_session_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `login_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `logout_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_auth_session_user` (`user_id`) USING BTREE,
  KEY `idx_auth_session_login` (`login_at`) USING BTREE,
  CONSTRAINT `fk_auth_session_user` FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Log sesi login dan logout';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `username` varchar(60) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `permissions_updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_auth_user_username` (`username`) USING BTREE,
  UNIQUE KEY `uk_auth_user_email` (`email`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Akun login pengguna aplikasi';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `aud_access_event` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `session_log_id` bigint(20) unsigned DEFAULT NULL,
  `page_code` varchar(100) DEFAULT NULL,
  `route_path` varchar(255) NOT NULL,
  `request_method` varchar(10) NOT NULL DEFAULT 'GET',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `device_label` varchar(80) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_aud_access_event_created` (`created_at`,`id`) USING BTREE,
  KEY `idx_aud_access_event_user_created` (`user_id`,`created_at`) USING BTREE,
  KEY `idx_aud_access_event_page_created` (`page_code`,`created_at`) USING BTREE,
  KEY `idx_aud_access_event_session_created` (`session_log_id`,`created_at`) USING BTREE,
  CONSTRAINT `fk_aud_access_event_session` FOREIGN KEY (`session_log_id`) REFERENCES `auth_session_log` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_aud_access_event_user` FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Metadata-only authenticated web page access audit';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_user_permission_override` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `page_id` bigint(20) unsigned NOT NULL,
  `override_type` enum('GRANT','REVOKE') NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT 0,
  `can_create` tinyint(1) NOT NULL DEFAULT 0,
  `can_edit` tinyint(1) NOT NULL DEFAULT 0,
  `can_delete` tinyint(1) NOT NULL DEFAULT 0,
  `can_export` tinyint(1) NOT NULL DEFAULT 0,
  `reason` varchar(255) DEFAULT NULL,
  `overridden_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_user_page_override` (`user_id`,`page_id`) USING BTREE,
  KEY `idx_auth_override_user` (`user_id`) USING BTREE,
  KEY `fk_auth_override_page` (`page_id`) USING BTREE,
  KEY `fk_auth_override_by` (`overridden_by`) USING BTREE,
  CONSTRAINT `fk_auth_override_by` FOREIGN KEY (`overridden_by`) REFERENCES `auth_user` (`id`),
  CONSTRAINT `fk_auth_override_page` FOREIGN KEY (`page_id`) REFERENCES `sys_page` (`id`),
  CONSTRAINT `fk_auth_override_user` FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Override izin khusus per user (tambah/cabut dari role)';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_user_role` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `assigned_by` bigint(20) unsigned DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_user_role` (`user_id`,`role_id`) USING BTREE,
  KEY `idx_auth_user_role_user` (`user_id`) USING BTREE,
  KEY `idx_auth_user_role_role` (`role_id`) USING BTREE,
  KEY `fk_auth_user_role_assigner` (`assigned_by`) USING BTREE,
  CONSTRAINT `fk_auth_user_role_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `auth_user` (`id`),
  CONSTRAINT `fk_auth_user_role_role` FOREIGN KEY (`role_id`) REFERENCES `auth_role` (`id`),
  CONSTRAINT `fk_auth_user_role_user` FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Assignment role ke user';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ci_sessions` (
  `id` varchar(128) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `timestamp` int(10) unsigned NOT NULL DEFAULT 0,
  `data` blob NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_ci_sessions_timestamp` (`timestamp`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='CI3 database session storage';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `coffee_packaging_label` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `label_code` varchar(40) NOT NULL,
  `label_name` varchar(160) NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `coffee_name` varchar(160) NOT NULL,
  `origin` varchar(160) DEFAULT NULL,
  `process_method` varchar(120) DEFAULT NULL,
  `roast_level` varchar(80) DEFAULT NULL,
  `body_level` varchar(80) DEFAULT NULL,
  `elevation_text` varchar(120) DEFAULT NULL,
  `bean_type` varchar(80) DEFAULT NULL,
  `weight_text` varchar(40) DEFAULT NULL,
  `tasting_notes` text DEFAULT NULL,
  `brew_suggestion` varchar(180) DEFAULT NULL,
  `batch_no` varchar(80) DEFAULT NULL,
  `roast_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `footer_note` varchar(180) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `canvas_width_mm` smallint(5) unsigned NOT NULL DEFAULT 90,
  `canvas_height_mm` smallint(5) unsigned NOT NULL DEFAULT 140,
  `theme_preset` varchar(60) NOT NULL DEFAULT 'heritage-cream',
  `design_json` mediumtext DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_coffee_packaging_label_code` (`label_code`) USING BTREE,
  KEY `idx_coffee_packaging_label_active` (`is_active`,`updated_at`) USING BTREE,
  KEY `idx_coffee_packaging_label_name` (`coffee_name`) USING BTREE,
  KEY `idx_coffee_packaging_label_origin` (`origin`) USING BTREE,
  KEY `idx_coffee_packaging_label_product` (`product_id`) USING BTREE,
  KEY `idx_coffee_packaging_label_label_name` (`label_name`) USING BTREE,
  CONSTRAINT `fk_coffee_packaging_label_product` FOREIGN KEY (`product_id`) REFERENCES `mst_product` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Draft desain label packaging kopi roastery';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `coffee_packaging_label_template` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `template_key` varchar(80) NOT NULL,
  `template_name` varchar(160) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `design_json` mediumtext NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_coffee_packaging_label_template_key` (`template_key`) USING BTREE,
  KEY `idx_coffee_packaging_label_template_active` (`is_active`,`is_system`,`template_name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reusable design templates for Roastery Label Studio';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cost_recalc_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `reason` varchar(50) NOT NULL,
  `status` enum('PENDING','PROCESSING','DONE','FAILED') NOT NULL DEFAULT 'PENDING',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_cost_recalc_queue_status` (`status`) USING BTREE,
  KEY `idx_cost_recalc_queue_product` (`product_id`) USING BTREE,
  CONSTRAINT `fk_cost_recalc_queue_product` FOREIGN KEY (`product_id`) REFERENCES `mst_product` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_member` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_no` varchar(40) NOT NULL,
  `member_name` varchar(150) NOT NULL,
  `mobile_phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('L','P') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `emergency_contact_name` varchar(120) DEFAULT NULL,
  `emergency_contact_phone` varchar(30) DEFAULT NULL,
  `member_tier` varchar(50) DEFAULT NULL,
  `joined_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expired_at` datetime DEFAULT NULL,
  `point_balance_cache` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `stamp_balance_cache` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_spending` decimal(18,2) NOT NULL DEFAULT 0.00,
  `member_status` enum('ACTIVE','SUSPENDED','CLOSED') NOT NULL DEFAULT 'ACTIVE',
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_crm_member_no` (`member_no`) USING BTREE,
  KEY `idx_crm_member_name` (`member_name`) USING BTREE,
  KEY `idx_crm_member_phone` (`mobile_phone`) USING BTREE,
  KEY `idx_crm_member_email` (`email`) USING BTREE,
  KEY `idx_crm_member_status` (`member_status`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_member_delivery_location` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) unsigned NOT NULL,
  `label` varchar(80) NOT NULL DEFAULT 'Rumah',
  `recipient_name` varchar(150) DEFAULT NULL,
  `recipient_phone` varchar(32) DEFAULT NULL,
  `address` varchar(255) NOT NULL,
  `address_note` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `location_accuracy` decimal(10,2) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `free_delivery_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `free_delivery_reason` varchar(120) DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_crm_member_delivery_location_member` (`member_id`,`is_default`) USING BTREE,
  KEY `idx_crm_member_delivery_location_latlng` (`latitude`,`longitude`) USING BTREE,
  CONSTRAINT `fk_crm_member_delivery_location_member` FOREIGN KEY (`member_id`) REFERENCES `crm_member` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_account_mutation_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mutation_no` varchar(60) NOT NULL,
  `mutation_date` date NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `mutation_type` enum('IN','OUT') NOT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `balance_before` decimal(18,2) NOT NULL DEFAULT 0.00,
  `balance_after` decimal(18,2) NOT NULL DEFAULT 0.00,
  `ref_module` varchar(40) DEFAULT NULL,
  `ref_table` varchar(80) DEFAULT NULL,
  `ref_id` bigint(20) unsigned DEFAULT NULL,
  `reversal_of_mutation_id` bigint(20) unsigned DEFAULT NULL,
  `ref_no` varchar(80) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_account_mutation_no` (`mutation_no`) USING BTREE,
  KEY `idx_fin_account_mutation_date` (`mutation_date`) USING BTREE,
  KEY `idx_fin_account_mutation_account` (`account_id`) USING BTREE,
  KEY `idx_fin_account_mutation_ref` (`ref_module`,`ref_table`,`ref_id`) USING BTREE,
  KEY `idx_fin_account_mutation_reversal` (`reversal_of_mutation_id`,`mutation_date`) USING BTREE,
  CONSTRAINT `fk_fin_account_mutation_account` FOREIGN KEY (`account_id`) REFERENCES `fin_company_account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_account_period_snapshot` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `period_close_id` bigint(20) unsigned NOT NULL,
  `company_account_id` bigint(20) unsigned NOT NULL,
  `account_code_snapshot` varchar(60) NOT NULL,
  `account_name_snapshot` varchar(150) NOT NULL,
  `account_type_snapshot` varchar(40) DEFAULT NULL,
  `bank_name_snapshot` varchar(120) DEFAULT NULL,
  `opening_balance_physical` decimal(18,2) NOT NULL DEFAULT 0.00,
  `mutation_in_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `mutation_out_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `closing_balance_physical` decimal(18,2) NOT NULL DEFAULT 0.00,
  `receivable_outstanding` decimal(18,2) NOT NULL DEFAULT 0.00,
  `payable_outstanding` decimal(18,2) NOT NULL DEFAULT 0.00,
  `cash_advance_outstanding` decimal(18,2) NOT NULL DEFAULT 0.00,
  `payroll_pending` decimal(18,2) NOT NULL DEFAULT 0.00,
  `historical_keep_balance_net` decimal(18,2) NOT NULL DEFAULT 0.00,
  `closing_balance_real` decimal(18,2) NOT NULL DEFAULT 0.00,
  `pos_in_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `pos_refund_out_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `purchase_out_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `payroll_out_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `cash_advance_out_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `payable_in_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `payable_payment_out_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `receivable_out_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `receivable_payment_in_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `transfer_in_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `transfer_out_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `manual_in_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `manual_out_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_account_period_snapshot_unique` (`period_close_id`,`company_account_id`) USING BTREE,
  KEY `idx_fin_account_period_snapshot_account` (`company_account_id`) USING BTREE,
  CONSTRAINT `fk_fin_account_period_snapshot_account` FOREIGN KEY (`company_account_id`) REFERENCES `fin_company_account` (`id`),
  CONSTRAINT `fk_fin_account_period_snapshot_period` FOREIGN KEY (`period_close_id`) REFERENCES `fin_period_close` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_cash_reconciliation` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reconciliation_no` varchar(60) NOT NULL,
  `reconciliation_date` date NOT NULL,
  `round_no` int(10) unsigned NOT NULL DEFAULT 1,
  `reconciled_at` datetime DEFAULT NULL,
  `status` enum('OPEN','REVIEWED','COMPLETED') NOT NULL DEFAULT 'OPEN',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_cash_recon_no` (`reconciliation_no`) USING BTREE,
  UNIQUE KEY `uk_fin_cash_recon_date_round` (`reconciliation_date`,`round_no`) USING BTREE,
  KEY `idx_fin_cash_recon_status_date` (`status`,`reconciliation_date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Header rekonsiliasi saldo riil rekening perusahaan';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_cash_reconciliation_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reconciliation_id` bigint(20) unsigned NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `system_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `actual_balance` decimal(18,2) DEFAULT NULL,
  `difference_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `resolution_type` enum('NONE','IN','OUT','TRANSFER') NOT NULL DEFAULT 'NONE',
  `counter_account_id` bigint(20) unsigned DEFAULT NULL,
  `resolution_note` varchar(255) DEFAULT NULL,
  `status` enum('UNCHECKED','MATCHED','OPEN','POSTED') NOT NULL DEFAULT 'UNCHECKED',
  `mutation_id` bigint(20) unsigned DEFAULT NULL,
  `counter_mutation_id` bigint(20) unsigned DEFAULT NULL,
  `entered_by` bigint(20) unsigned DEFAULT NULL,
  `entered_at` datetime DEFAULT NULL,
  `resolved_by` bigint(20) unsigned DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_cash_recon_line_account` (`reconciliation_id`,`account_id`) USING BTREE,
  KEY `idx_fin_cash_recon_line_account` (`account_id`) USING BTREE,
  KEY `idx_fin_cash_recon_line_status` (`status`) USING BTREE,
  KEY `idx_fin_cash_recon_line_counter` (`counter_account_id`) USING BTREE,
  KEY `idx_fin_cash_recon_line_mutation` (`mutation_id`) USING BTREE,
  KEY `idx_fin_cash_recon_line_counter_mutation` (`counter_mutation_id`) USING BTREE,
  CONSTRAINT `fk_fin_cash_recon_line_account` FOREIGN KEY (`account_id`) REFERENCES `fin_company_account` (`id`),
  CONSTRAINT `fk_fin_cash_recon_line_counter_account` FOREIGN KEY (`counter_account_id`) REFERENCES `fin_company_account` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fin_cash_recon_line_counter_mutation` FOREIGN KEY (`counter_mutation_id`) REFERENCES `fin_account_mutation_log` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fin_cash_recon_line_header` FOREIGN KEY (`reconciliation_id`) REFERENCES `fin_cash_reconciliation` (`id`),
  CONSTRAINT `fk_fin_cash_recon_line_mutation` FOREIGN KEY (`mutation_id`) REFERENCES `fin_account_mutation_log` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Snapshot saldo sistem, saldo riil, dan keputusan penyesuaian per rekening';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_company_account` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_code` varchar(40) NOT NULL,
  `account_name` varchar(150) NOT NULL,
  `account_type` enum('BANK','EWALLET','CASH','OTHER') NOT NULL DEFAULT 'BANK',
  `bank_id` bigint(20) unsigned DEFAULT NULL,
  `bank_name` varchar(120) DEFAULT NULL,
  `account_no` varchar(80) DEFAULT NULL,
  `account_holder` varchar(120) DEFAULT NULL,
  `currency_code` varchar(10) NOT NULL DEFAULT 'IDR',
  `opening_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_company_account_code` (`account_code`) USING BTREE,
  KEY `idx_fin_company_account_type` (`account_type`) USING BTREE,
  KEY `idx_fin_company_account_active` (`is_active`) USING BTREE,
  KEY `fk_fin_company_account_bank` (`bank_id`) USING BTREE,
  CONSTRAINT `fk_fin_company_account_bank` FOREIGN KEY (`bank_id`) REFERENCES `mst_bank` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_management_period_metric` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `period_close_id` bigint(20) unsigned NOT NULL,
  `scope_type` enum('GLOBAL','DIVISION','ACCOUNT') NOT NULL DEFAULT 'GLOBAL',
  `scope_ref_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `metric_group` varchar(60) NOT NULL,
  `metric_code` varchar(80) NOT NULL,
  `metric_label` varchar(150) NOT NULL,
  `metric_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `metric_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `source_ref` varchar(120) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_management_period_metric_unique` (`period_close_id`,`scope_type`,`scope_ref_id`,`metric_code`) USING BTREE,
  KEY `idx_fin_management_period_metric_group` (`metric_group`,`metric_code`) USING BTREE,
  CONSTRAINT `fk_fin_management_period_metric_period` FOREIGN KEY (`period_close_id`) REFERENCES `fin_period_close` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_metric_catalog` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `metric_code` varchar(80) NOT NULL,
  `metric_group` varchar(60) NOT NULL,
  `metric_label` varchar(150) NOT NULL,
  `metric_unit` enum('AMOUNT','QTY','PERCENT','DAYS','COUNT') NOT NULL DEFAULT 'AMOUNT',
  `metric_scope` enum('GLOBAL','DIVISION','ACCOUNT','PERIOD') NOT NULL DEFAULT 'GLOBAL',
  `comparator_hint` enum('MIN','MAX','RANGE','EQUAL') NOT NULL DEFAULT 'MAX',
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_metric_catalog_code` (`metric_code`) USING BTREE,
  KEY `idx_fin_metric_catalog_group` (`metric_group`) USING BTREE,
  KEY `idx_fin_metric_catalog_active` (`is_active`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_payable` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payable_no` varchar(60) NOT NULL,
  `party_id` bigint(20) unsigned NOT NULL,
  `payable_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `payable_title` varchar(200) NOT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `outstanding_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `account_impact_mode` enum('APPLY_ACCOUNT','KEEP_BALANCE') NOT NULL DEFAULT 'APPLY_ACCOUNT',
  `company_account_id` bigint(20) unsigned DEFAULT NULL,
  `initial_mutation_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('OPEN','PARTIAL','SETTLED','VOID') NOT NULL DEFAULT 'OPEN',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_payable_no` (`payable_no`) USING BTREE,
  KEY `idx_fin_payable_party` (`party_id`) USING BTREE,
  KEY `idx_fin_payable_date` (`payable_date`) USING BTREE,
  KEY `idx_fin_payable_status` (`status`) USING BTREE,
  KEY `idx_fin_payable_account` (`company_account_id`) USING BTREE,
  KEY `idx_fin_payable_mutation` (`initial_mutation_id`) USING BTREE,
  CONSTRAINT `fk_fin_payable_account` FOREIGN KEY (`company_account_id`) REFERENCES `fin_company_account` (`id`),
  CONSTRAINT `fk_fin_payable_mutation` FOREIGN KEY (`initial_mutation_id`) REFERENCES `fin_account_mutation_log` (`id`),
  CONSTRAINT `fk_fin_payable_party` FOREIGN KEY (`party_id`) REFERENCES `fin_relation_party` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_payable_payment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payable_id` bigint(20) unsigned NOT NULL,
  `payment_no` varchar(60) NOT NULL,
  `payment_date` date NOT NULL,
  `company_account_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `account_impact_mode` enum('APPLY_ACCOUNT','KEEP_BALANCE') NOT NULL DEFAULT 'APPLY_ACCOUNT',
  `transfer_ref_no` varchar(80) DEFAULT NULL,
  `mutation_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_payable_payment_no` (`payment_no`) USING BTREE,
  KEY `idx_fin_payable_payment_header` (`payable_id`) USING BTREE,
  KEY `idx_fin_payable_payment_date` (`payment_date`) USING BTREE,
  KEY `idx_fin_payable_payment_account` (`company_account_id`) USING BTREE,
  KEY `idx_fin_payable_payment_mutation` (`mutation_id`) USING BTREE,
  CONSTRAINT `fk_fin_payable_payment_account` FOREIGN KEY (`company_account_id`) REFERENCES `fin_company_account` (`id`),
  CONSTRAINT `fk_fin_payable_payment_header` FOREIGN KEY (`payable_id`) REFERENCES `fin_payable` (`id`),
  CONSTRAINT `fk_fin_payable_payment_mutation` FOREIGN KEY (`mutation_id`) REFERENCES `fin_account_mutation_log` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_period_close` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `period_code` varchar(30) NOT NULL,
  `period_type` enum('MONTHLY','YEARLY') NOT NULL DEFAULT 'MONTHLY',
  `period_year` smallint(5) unsigned NOT NULL,
  `period_month` tinyint(3) unsigned DEFAULT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `snapshot_version` int(10) unsigned NOT NULL DEFAULT 1,
  `close_mode` enum('AUTO_REBUILD','MANUAL_LOCK') NOT NULL DEFAULT 'AUTO_REBUILD',
  `status` enum('OPEN','CLOSED','REOPENED','VOID') NOT NULL DEFAULT 'OPEN',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `closed_by` bigint(20) unsigned DEFAULT NULL,
  `reopened_by` bigint(20) unsigned DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_period_close_code_version` (`period_code`,`snapshot_version`) USING BTREE,
  KEY `idx_fin_period_close_type_status` (`period_type`,`status`) USING BTREE,
  KEY `idx_fin_period_close_range` (`period_start`,`period_end`) USING BTREE,
  KEY `fk_fin_period_close_created_by` (`created_by`) USING BTREE,
  KEY `fk_fin_period_close_closed_by` (`closed_by`) USING BTREE,
  KEY `fk_fin_period_close_reopened_by` (`reopened_by`) USING BTREE,
  CONSTRAINT `fk_fin_period_close_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fin_period_close_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fin_period_close_reopened_by` FOREIGN KEY (`reopened_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_receivable` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `receivable_no` varchar(60) NOT NULL,
  `party_id` bigint(20) unsigned NOT NULL,
  `receivable_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `receivable_title` varchar(200) NOT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `outstanding_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `account_impact_mode` enum('APPLY_ACCOUNT','KEEP_BALANCE') NOT NULL DEFAULT 'APPLY_ACCOUNT',
  `company_account_id` bigint(20) unsigned DEFAULT NULL,
  `initial_mutation_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('OPEN','PARTIAL','SETTLED','VOID') NOT NULL DEFAULT 'OPEN',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_receivable_no` (`receivable_no`) USING BTREE,
  KEY `idx_fin_receivable_party` (`party_id`) USING BTREE,
  KEY `idx_fin_receivable_date` (`receivable_date`) USING BTREE,
  KEY `idx_fin_receivable_status` (`status`) USING BTREE,
  KEY `idx_fin_receivable_account` (`company_account_id`) USING BTREE,
  KEY `idx_fin_receivable_mutation` (`initial_mutation_id`) USING BTREE,
  CONSTRAINT `fk_fin_receivable_account` FOREIGN KEY (`company_account_id`) REFERENCES `fin_company_account` (`id`),
  CONSTRAINT `fk_fin_receivable_mutation` FOREIGN KEY (`initial_mutation_id`) REFERENCES `fin_account_mutation_log` (`id`),
  CONSTRAINT `fk_fin_receivable_party` FOREIGN KEY (`party_id`) REFERENCES `fin_relation_party` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_receivable_payment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `receivable_id` bigint(20) unsigned NOT NULL,
  `payment_no` varchar(60) NOT NULL,
  `payment_date` date NOT NULL,
  `company_account_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `account_impact_mode` enum('APPLY_ACCOUNT','KEEP_BALANCE') NOT NULL DEFAULT 'APPLY_ACCOUNT',
  `transfer_ref_no` varchar(80) DEFAULT NULL,
  `mutation_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_receivable_payment_no` (`payment_no`) USING BTREE,
  KEY `idx_fin_receivable_payment_header` (`receivable_id`) USING BTREE,
  KEY `idx_fin_receivable_payment_date` (`payment_date`) USING BTREE,
  KEY `idx_fin_receivable_payment_account` (`company_account_id`) USING BTREE,
  KEY `idx_fin_receivable_payment_mutation` (`mutation_id`) USING BTREE,
  CONSTRAINT `fk_fin_receivable_payment_account` FOREIGN KEY (`company_account_id`) REFERENCES `fin_company_account` (`id`),
  CONSTRAINT `fk_fin_receivable_payment_header` FOREIGN KEY (`receivable_id`) REFERENCES `fin_receivable` (`id`),
  CONSTRAINT `fk_fin_receivable_payment_mutation` FOREIGN KEY (`mutation_id`) REFERENCES `fin_account_mutation_log` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_relation_party` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `party_code` varchar(60) NOT NULL,
  `party_name` varchar(180) NOT NULL,
  `party_type` enum('PERSON','BUSINESS','MEMBER','OTHER') NOT NULL DEFAULT 'BUSINESS',
  `linked_member_id` bigint(20) unsigned DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `mobile_phone` varchar(40) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_relation_party_code` (`party_code`) USING BTREE,
  KEY `idx_fin_relation_party_name` (`party_name`) USING BTREE,
  KEY `idx_fin_relation_party_member` (`linked_member_id`) USING BTREE,
  KEY `idx_fin_relation_party_active` (`is_active`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_revenue_reconciliation` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reconciliation_no` varchar(70) NOT NULL,
  `reconciliation_date` date NOT NULL,
  `revenue_date` date NOT NULL,
  `round_no` int(10) unsigned NOT NULL DEFAULT 1,
  `status` enum('OPEN','COMPLETED') NOT NULL DEFAULT 'OPEN',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_revenue_recon_no` (`reconciliation_no`) USING BTREE,
  UNIQUE KEY `uk_fin_revenue_recon_dates_round` (`reconciliation_date`,`revenue_date`,`round_no`) USING BTREE,
  KEY `idx_fin_revenue_recon_revenue_date` (`revenue_date`,`status`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_revenue_reconciliation_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reconciliation_id` bigint(20) unsigned NOT NULL,
  `payment_method_id` bigint(20) unsigned NOT NULL,
  `account_id` bigint(20) unsigned DEFAULT NULL,
  `expected_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `actual_amount` decimal(18,2) DEFAULT NULL,
  `difference_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `transaction_count` int(10) unsigned NOT NULL DEFAULT 0,
  `resolution_type` enum('NONE','IN','OUT') NOT NULL DEFAULT 'NONE',
  `resolution_note` varchar(255) DEFAULT NULL,
  `status` enum('UNCHECKED','MATCHED','OPEN','POSTED') NOT NULL DEFAULT 'UNCHECKED',
  `mutation_id` bigint(20) unsigned DEFAULT NULL,
  `entered_by` bigint(20) unsigned DEFAULT NULL,
  `entered_at` datetime DEFAULT NULL,
  `resolved_by` bigint(20) unsigned DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_revenue_recon_line_method` (`reconciliation_id`,`payment_method_id`) USING BTREE,
  KEY `idx_fin_revenue_recon_line_account` (`account_id`) USING BTREE,
  KEY `idx_fin_revenue_recon_line_mutation` (`mutation_id`) USING BTREE,
  KEY `fk_fin_revenue_recon_line_method` (`payment_method_id`) USING BTREE,
  CONSTRAINT `fk_fin_revenue_recon_line_account` FOREIGN KEY (`account_id`) REFERENCES `fin_company_account` (`id`),
  CONSTRAINT `fk_fin_revenue_recon_line_header` FOREIGN KEY (`reconciliation_id`) REFERENCES `fin_revenue_reconciliation` (`id`),
  CONSTRAINT `fk_fin_revenue_recon_line_method` FOREIGN KEY (`payment_method_id`) REFERENCES `pos_payment_method` (`id`),
  CONSTRAINT `fk_fin_revenue_recon_line_mutation` FOREIGN KEY (`mutation_id`) REFERENCES `fin_account_mutation_log` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_revenue_reconciliation_method` (
  `payment_method_id` bigint(20) unsigned NOT NULL,
  `settlement_delay_days` int(10) unsigned NOT NULL DEFAULT 1,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`payment_method_id`) USING BTREE,
  CONSTRAINT `fk_fin_revenue_recon_setting_method` FOREIGN KEY (`payment_method_id`) REFERENCES `pos_payment_method` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_target_plan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `target_code` varchar(40) NOT NULL,
  `target_name` varchar(180) NOT NULL,
  `target_scope` enum('DAILY','MONTHLY','YEARLY') NOT NULL DEFAULT 'MONTHLY',
  `target_year` smallint(5) unsigned NOT NULL,
  `target_month` tinyint(3) unsigned DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `date_start` date NOT NULL,
  `date_end` date NOT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `company_account_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('DRAFT','ACTIVE','LOCKED','VOID') NOT NULL DEFAULT 'DRAFT',
  `bonus_gate_mode` enum('NONE','ALL_REQUIRED','WEIGHTED_SCORE') NOT NULL DEFAULT 'WEIGHTED_SCORE',
  `min_bonus_score` decimal(7,2) NOT NULL DEFAULT 100.00,
  `bonus_pool_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `bonus_percent_of_profit` decimal(9,4) NOT NULL DEFAULT 0.0000,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_target_plan_code` (`target_code`) USING BTREE,
  KEY `idx_fin_target_plan_scope_period` (`target_scope`,`target_year`,`target_month`,`target_date`) USING BTREE,
  KEY `idx_fin_target_plan_status` (`status`) USING BTREE,
  KEY `idx_fin_target_plan_division` (`division_id`) USING BTREE,
  KEY `idx_fin_target_plan_account` (`company_account_id`) USING BTREE,
  KEY `fk_fin_target_plan_created_by` (`created_by`) USING BTREE,
  KEY `fk_fin_target_plan_approved_by` (`approved_by`) USING BTREE,
  CONSTRAINT `fk_fin_target_plan_account` FOREIGN KEY (`company_account_id`) REFERENCES `fin_company_account` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fin_target_plan_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fin_target_plan_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fin_target_plan_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_target_plan_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `target_plan_id` bigint(20) unsigned NOT NULL,
  `metric_group` varchar(60) NOT NULL,
  `metric_code` varchar(80) NOT NULL,
  `metric_label` varchar(150) NOT NULL,
  `comparator` enum('MIN','MAX','RANGE','EQUAL') NOT NULL DEFAULT 'MIN',
  `target_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `minimum_value` decimal(18,2) DEFAULT NULL,
  `maximum_value` decimal(18,2) DEFAULT NULL,
  `warning_value` decimal(18,2) DEFAULT NULL,
  `weight_percent` decimal(9,4) NOT NULL DEFAULT 0.0000,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_target_plan_line_unique` (`target_plan_id`,`metric_code`) USING BTREE,
  KEY `idx_fin_target_plan_line_group` (`metric_group`) USING BTREE,
  CONSTRAINT `fk_fin_target_plan_line_header` FOREIGN KEY (`target_plan_id`) REFERENCES `fin_target_plan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_target_realization` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `target_plan_id` bigint(20) unsigned NOT NULL,
  `target_plan_line_id` bigint(20) unsigned NOT NULL,
  `period_close_id` bigint(20) unsigned DEFAULT NULL,
  `realization_date` date NOT NULL,
  `metric_code` varchar(80) NOT NULL,
  `target_value_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `actual_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `score_percent` decimal(9,2) NOT NULL DEFAULT 0.00,
  `is_passed` tinyint(1) NOT NULL DEFAULT 0,
  `bonus_gate_passed` tinyint(1) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_fin_target_realization_unique` (`target_plan_line_id`,`realization_date`) USING BTREE,
  KEY `idx_fin_target_realization_plan` (`target_plan_id`,`realization_date`) USING BTREE,
  KEY `idx_fin_target_realization_period` (`period_close_id`) USING BTREE,
  CONSTRAINT `fk_fin_target_realization_line` FOREIGN KEY (`target_plan_line_id`) REFERENCES `fin_target_plan_line` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fin_target_realization_period` FOREIGN KEY (`period_close_id`) REFERENCES `fin_period_close` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fin_target_realization_plan` FOREIGN KEY (`target_plan_id`) REFERENCES `fin_target_plan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_contract` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_number` varchar(60) NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `template_id` bigint(20) unsigned DEFAULT NULL,
  `previous_contract_id` bigint(20) unsigned DEFAULT NULL,
  `contract_type` enum('K1','K2','K3','CUSTOM') NOT NULL DEFAULT 'K1',
  `status` enum('DRAFT','GENERATED','SIGNED','ACTIVE','EXPIRED','TERMINATED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  `position_snapshot` varchar(120) DEFAULT NULL,
  `division_snapshot` varchar(120) DEFAULT NULL,
  `basic_salary` decimal(18,2) NOT NULL DEFAULT 0.00,
  `position_allowance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `other_allowance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `meal_rate` decimal(18,2) NOT NULL DEFAULT 0.00,
  `overtime_rate` decimal(18,2) NOT NULL DEFAULT 0.00,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `verification_token` varchar(80) DEFAULT NULL,
  `final_document_hash` char(64) DEFAULT NULL,
  `document_issued_at` datetime DEFAULT NULL,
  `body_html` longtext DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `generated_by` bigint(20) unsigned DEFAULT NULL,
  `generated_at` datetime DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_hr_contract_number` (`contract_number`) USING BTREE,
  UNIQUE KEY `uk_hr_contract_verification_token` (`verification_token`) USING BTREE,
  KEY `idx_hr_contract_employee` (`employee_id`) USING BTREE,
  KEY `idx_hr_contract_template` (`template_id`) USING BTREE,
  KEY `idx_hr_contract_previous` (`previous_contract_id`) USING BTREE,
  KEY `idx_hr_contract_status_dates` (`status`,`start_date`,`end_date`) USING BTREE,
  KEY `fk_hr_contract_generated_by` (`generated_by`) USING BTREE,
  KEY `fk_hr_contract_created_by` (`created_by`) USING BTREE,
  CONSTRAINT `fk_hr_contract_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_contract_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_hr_contract_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_contract_previous` FOREIGN KEY (`previous_contract_id`) REFERENCES `hr_contract` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_contract_template` FOREIGN KEY (`template_id`) REFERENCES `hr_contract_template` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_contract_approval` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` bigint(20) unsigned NOT NULL,
  `approver_role` enum('EMPLOYEE','COMPANY') NOT NULL,
  `approval_status` enum('APPROVED','REVOKED') NOT NULL DEFAULT 'APPROVED',
  `approver_name` varchar(150) NOT NULL,
  `approver_user_id` bigint(20) unsigned DEFAULT NULL,
  `approval_note` varchar(255) DEFAULT NULL,
  `approved_at` datetime NOT NULL DEFAULT current_timestamp(),
  `revoked_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_hr_contract_approval_contract_role` (`contract_id`,`approver_role`) USING BTREE,
  KEY `idx_hr_contract_approval_status_time` (`approval_status`,`approved_at`) USING BTREE,
  KEY `fk_hr_contract_approval_user` (`approver_user_id`) USING BTREE,
  CONSTRAINT `fk_hr_contract_approval_contract` FOREIGN KEY (`contract_id`) REFERENCES `hr_contract` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_contract_approval_user` FOREIGN KEY (`approver_user_id`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_contract_comp_snapshot` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `effective_start` date NOT NULL,
  `effective_end` date DEFAULT NULL,
  `basic_salary_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `position_allowance_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `other_allowance_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `meal_rate_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `overtime_rate_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `fixed_total_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_hr_contract_comp_snapshot_contract` (`contract_id`) USING BTREE,
  KEY `idx_hr_contract_comp_snapshot_employee` (`employee_id`) USING BTREE,
  KEY `idx_hr_contract_comp_snapshot_effective` (`effective_start`,`effective_end`) USING BTREE,
  CONSTRAINT `fk_hr_contract_comp_snapshot_contract` FOREIGN KEY (`contract_id`) REFERENCES `hr_contract` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_contract_comp_snapshot_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_contract_comp_snapshot_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `snapshot_id` bigint(20) unsigned NOT NULL,
  `component_code_snapshot` varchar(60) NOT NULL,
  `component_name_snapshot` varchar(120) NOT NULL,
  `component_type` enum('EARNING','DEDUCTION') NOT NULL DEFAULT 'EARNING',
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_hr_contract_snapshot_line_snapshot` (`snapshot_id`,`sort_order`) USING BTREE,
  CONSTRAINT `fk_hr_contract_snapshot_line_snapshot` FOREIGN KEY (`snapshot_id`) REFERENCES `hr_contract_comp_snapshot` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_contract_signature` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` bigint(20) unsigned NOT NULL,
  `signer_role` enum('EMPLOYEE','COMPANY') NOT NULL,
  `signer_name` varchar(150) NOT NULL,
  `signer_user_id` bigint(20) unsigned DEFAULT NULL,
  `signature_data` longtext NOT NULL,
  `signed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_hr_contract_signature_contract` (`contract_id`) USING BTREE,
  KEY `fk_hr_contract_signature_user` (`signer_user_id`) USING BTREE,
  CONSTRAINT `fk_hr_contract_signature_contract` FOREIGN KEY (`contract_id`) REFERENCES `hr_contract` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_contract_signature_user` FOREIGN KEY (`signer_user_id`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_contract_template` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `template_code` varchar(30) NOT NULL,
  `template_name` varchar(120) NOT NULL,
  `contract_type` enum('K1','K2','K3','CUSTOM') NOT NULL DEFAULT 'K1',
  `duration_months` smallint(5) unsigned NOT NULL DEFAULT 3,
  `body_html` longtext DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_hr_contract_template_code` (`template_code`) USING BTREE,
  KEY `idx_hr_contract_template_active` (`is_active`) USING BTREE,
  KEY `fk_hr_contract_template_created_by` (`created_by`) USING BTREE,
  CONSTRAINT `fk_hr_contract_template_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_component_adjustment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `adjustment_no` varchar(60) NOT NULL,
  `adjustment_date` date NOT NULL,
  `location_type` enum('BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT') NOT NULL DEFAULT 'KITCHEN',
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('DRAFT','POSTED','VOID') NOT NULL DEFAULT 'DRAFT',
  `notes` varchar(255) DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_component_adjustment_no` (`adjustment_no`) USING BTREE,
  KEY `idx_inv_component_adjustment_date` (`adjustment_date`) USING BTREE,
  KEY `idx_inv_component_adjustment_scope` (`location_type`,`division_id`) USING BTREE,
  KEY `fk_inv_component_adjustment_division` (`division_id`) USING BTREE,
  KEY `fk_inv_component_adjustment_created_by` (`created_by`) USING BTREE,
  KEY `fk_inv_component_adjustment_posted_by` (`posted_by`) USING BTREE,
  CONSTRAINT `fk_inv_component_adjustment_created_by` FOREIGN KEY (`created_by`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_inv_component_adjustment_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_inv_component_adjustment_posted_by` FOREIGN KEY (`posted_by`) REFERENCES `org_employee` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_component_adjustment_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `adjustment_id` bigint(20) unsigned NOT NULL,
  `line_no` int(11) NOT NULL,
  `component_id` bigint(20) unsigned NOT NULL,
  `uom_id` bigint(20) unsigned NOT NULL,
  `selected_lot_id` bigint(20) unsigned DEFAULT NULL,
  `input_mode` enum('DELTA','PHYSICAL_COUNT') NOT NULL DEFAULT 'DELTA',
  `available_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `system_qty_snapshot` decimal(18,4) DEFAULT NULL,
  `physical_qty_snapshot` decimal(18,4) DEFAULT NULL,
  `settle_open_deficit` tinyint(1) NOT NULL DEFAULT 0,
  `deficit_settled_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_spoil` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `spoil_reason_code` enum('expired','temperature_abuse','contamination','improper_storage','overstock','other') DEFAULT NULL,
  `qty_waste` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `waste_reason_code` enum('cancel_order','kitchen_error','overproduction','spillage','expired_opened','other') DEFAULT NULL,
  `qty_adjust_pos` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_plus_reason_code` enum('opening_correction','stock_found','manual_reclass','other') DEFAULT NULL,
  `qty_adjust_neg` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_minus_reason_code` enum('counting_error','system_mismatch','unrecorded_usage','process_loss','theft_suspected','other') DEFAULT NULL,
  `unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_inv_component_adjustment_line_adjustment` (`adjustment_id`) USING BTREE,
  KEY `idx_inv_component_adjustment_line_component` (`component_id`) USING BTREE,
  KEY `fk_inv_component_adjustment_line_uom` (`uom_id`) USING BTREE,
  CONSTRAINT `fk_inv_component_adjustment_line_adjustment` FOREIGN KEY (`adjustment_id`) REFERENCES `inv_component_adjustment` (`id`),
  CONSTRAINT `fk_inv_component_adjustment_line_component` FOREIGN KEY (`component_id`) REFERENCES `mst_component` (`id`),
  CONSTRAINT `fk_inv_component_adjustment_line_uom` FOREIGN KEY (`uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_component_batch` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_no` varchar(60) NOT NULL,
  `batch_date` date NOT NULL,
  `location_type` enum('BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT') NOT NULL DEFAULT 'KITCHEN',
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `component_id` bigint(20) unsigned NOT NULL,
  `output_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `output_uom_id` bigint(20) unsigned NOT NULL,
  `total_input_cost` decimal(18,2) NOT NULL DEFAULT 0.00,
  `unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `status` enum('DRAFT','POSTED','VOID') NOT NULL DEFAULT 'DRAFT',
  `notes` varchar(255) DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_component_batch_no` (`batch_no`) USING BTREE,
  KEY `idx_inv_component_batch_main` (`batch_date`,`location_type`,`division_id`,`component_id`) USING BTREE,
  KEY `idx_inv_component_batch_status` (`status`) USING BTREE,
  KEY `fk_inv_component_batch_division` (`division_id`) USING BTREE,
  KEY `fk_inv_component_batch_component` (`component_id`) USING BTREE,
  KEY `fk_inv_component_batch_uom` (`output_uom_id`) USING BTREE,
  KEY `fk_inv_component_batch_created_by` (`created_by`) USING BTREE,
  KEY `fk_inv_component_batch_posted_by` (`posted_by`) USING BTREE,
  CONSTRAINT `fk_inv_component_batch_component` FOREIGN KEY (`component_id`) REFERENCES `mst_component` (`id`),
  CONSTRAINT `fk_inv_component_batch_created_by` FOREIGN KEY (`created_by`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_inv_component_batch_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_inv_component_batch_posted_by` FOREIGN KEY (`posted_by`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_inv_component_batch_uom` FOREIGN KEY (`output_uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_component_batch_input` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` bigint(20) unsigned NOT NULL,
  `line_no` int(11) NOT NULL,
  `plan_role` varchar(40) DEFAULT NULL,
  `source_kind` enum('MATERIAL','COMPONENT') NOT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `component_id` bigint(20) unsigned DEFAULT NULL,
  `qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `uom_id` bigint(20) unsigned NOT NULL,
  `unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `total_cost` decimal(18,2) NOT NULL DEFAULT 0.00,
  `fifo_issue_id` bigint(20) unsigned DEFAULT NULL,
  `fifo_issue_no` varchar(60) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_inv_component_batch_input_batch` (`batch_id`) USING BTREE,
  KEY `idx_inv_component_batch_input_material` (`material_id`) USING BTREE,
  KEY `idx_inv_component_batch_input_component` (`component_id`) USING BTREE,
  KEY `fk_inv_component_batch_input_uom` (`uom_id`) USING BTREE,
  CONSTRAINT `fk_inv_component_batch_input_batch` FOREIGN KEY (`batch_id`) REFERENCES `inv_component_batch` (`id`),
  CONSTRAINT `fk_inv_component_batch_input_component` FOREIGN KEY (`component_id`) REFERENCES `mst_component` (`id`),
  CONSTRAINT `fk_inv_component_batch_input_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`),
  CONSTRAINT `fk_inv_component_batch_input_uom` FOREIGN KEY (`uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_component_lot` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `location_type` varchar(20) NOT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `component_id` bigint(20) unsigned NOT NULL,
  `uom_id` bigint(20) unsigned NOT NULL,
  `lot_no` varchar(64) NOT NULL,
  `receipt_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `qty_in_total` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_out_total` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_balance` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `source_module` varchar(50) DEFAULT NULL,
  `source_table` varchar(100) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `source_line_id` bigint(20) unsigned DEFAULT NULL,
  `parent_lot_id` bigint(20) unsigned DEFAULT NULL,
  `last_issue_at` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'OPEN',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_component_lot_scope` (`location_type`,`division_id`,`component_id`,`uom_id`,`lot_no`) USING BTREE,
  KEY `idx_inv_component_lot_source` (`source_table`,`source_id`,`source_line_id`) USING BTREE,
  KEY `idx_inv_component_lot_open` (`location_type`,`division_id`,`component_id`,`uom_id`,`status`,`receipt_date`) USING BTREE,
  KEY `idx_inv_component_lot_component` (`component_id`,`receipt_date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_component_lot_issue_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `issue_id` bigint(20) unsigned NOT NULL,
  `lot_id` bigint(20) unsigned NOT NULL,
  `qty_out` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `total_cost` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_balance_before` decimal(18,4) DEFAULT NULL,
  `source_balance_after` decimal(18,4) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_inv_component_lot_issue_line_issue` (`issue_id`) USING BTREE,
  KEY `idx_inv_component_lot_issue_line_lot` (`lot_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_component_lot_issue_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `issue_no` varchar(32) NOT NULL,
  `issue_date` date NOT NULL,
  `issue_datetime` datetime NOT NULL,
  `location_type` varchar(20) NOT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `component_id` bigint(20) unsigned NOT NULL,
  `uom_id` bigint(20) unsigned NOT NULL,
  `issue_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_cost` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_module` varchar(50) DEFAULT NULL,
  `source_table` varchar(100) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `source_line_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'POSTED',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_component_lot_issue_no` (`issue_no`) USING BTREE,
  KEY `idx_inv_component_lot_issue_source` (`source_table`,`source_id`,`source_line_id`) USING BTREE,
  KEY `idx_inv_component_lot_issue_main` (`location_type`,`division_id`,`component_id`,`uom_id`,`issue_date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_component_monthly_opening` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `month_key` date NOT NULL,
  `location_type` enum('BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT') NOT NULL DEFAULT 'KITCHEN',
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `component_id` bigint(20) unsigned NOT NULL,
  `uom_id` bigint(20) unsigned NOT NULL,
  `opening_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_avg_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `opening_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_type` enum('MANUAL','AUTO_CARRY_FORWARD','AUTO_REBUILD','OPNAME') NOT NULL DEFAULT 'MANUAL',
  `source_month_key` date DEFAULT NULL,
  `source_ref_table` varchar(80) DEFAULT NULL,
  `source_ref_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `generated_by` bigint(20) unsigned DEFAULT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_component_monthly_opening` (`month_key`,`location_type`,`division_id`,`component_id`,`uom_id`) USING BTREE,
  KEY `idx_inv_component_monthly_opening_month` (`month_key`) USING BTREE,
  KEY `idx_inv_component_monthly_opening_scope` (`location_type`,`division_id`,`month_key`) USING BTREE,
  KEY `idx_inv_component_monthly_opening_component` (`component_id`) USING BTREE,
  KEY `fk_inv_component_monthly_opening_live_division` (`division_id`) USING BTREE,
  KEY `fk_inv_component_monthly_opening_live_uom` (`uom_id`) USING BTREE,
  KEY `fk_inv_component_monthly_opening_live_by` (`generated_by`) USING BTREE,
  CONSTRAINT `fk_inv_component_monthly_opening_live_by` FOREIGN KEY (`generated_by`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_inv_component_monthly_opening_live_component` FOREIGN KEY (`component_id`) REFERENCES `mst_component` (`id`),
  CONSTRAINT `fk_inv_component_monthly_opening_live_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_inv_component_monthly_opening_live_uom` FOREIGN KEY (`uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_component_monthly_opname` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `month_key` date NOT NULL,
  `location_type` enum('BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT') NOT NULL DEFAULT 'KITCHEN',
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `component_id` bigint(20) unsigned NOT NULL,
  `uom_id` bigint(20) unsigned NOT NULL,
  `opening_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `in_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `in_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `out_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `out_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `waste_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `waste_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `spoil_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `spoil_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `adjustment_plus_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_plus_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `adjustment_minus_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_minus_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `closing_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `avg_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `movement_day_count` int(10) unsigned NOT NULL DEFAULT 0,
  `mutation_count` int(10) unsigned NOT NULL DEFAULT 0,
  `generated_by` bigint(20) unsigned DEFAULT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_component_monthly_opname` (`month_key`,`location_type`,`division_id`,`component_id`,`uom_id`) USING BTREE,
  KEY `idx_inv_component_monthly_opname_month` (`month_key`) USING BTREE,
  KEY `idx_inv_component_monthly_opname_scope` (`location_type`,`division_id`,`month_key`) USING BTREE,
  KEY `idx_inv_component_monthly_opname_component` (`component_id`) USING BTREE,
  KEY `fk_inv_component_monthly_opname_live_division` (`division_id`) USING BTREE,
  KEY `fk_inv_component_monthly_opname_live_uom` (`uom_id`) USING BTREE,
  KEY `fk_inv_component_monthly_opname_live_by` (`generated_by`) USING BTREE,
  CONSTRAINT `fk_inv_component_monthly_opname_live_by` FOREIGN KEY (`generated_by`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_inv_component_monthly_opname_live_component` FOREIGN KEY (`component_id`) REFERENCES `mst_component` (`id`),
  CONSTRAINT `fk_inv_component_monthly_opname_live_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_inv_component_monthly_opname_live_uom` FOREIGN KEY (`uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_component_monthly_stock` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `month_key` date NOT NULL,
  `location_type` enum('BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT') NOT NULL DEFAULT 'KITCHEN',
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `component_id` bigint(20) unsigned NOT NULL,
  `uom_id` bigint(20) unsigned NOT NULL,
  `opening_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `in_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `in_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `out_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `out_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `waste_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `waste_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `spoil_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `spoil_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `adjustment_plus_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_plus_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `adjustment_minus_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_minus_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `closing_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `avg_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `movement_day_count` int(10) unsigned NOT NULL DEFAULT 0,
  `mutation_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_movement_date` date DEFAULT NULL,
  `last_movement_at` datetime DEFAULT NULL,
  `last_movement_table` varchar(80) DEFAULT NULL,
  `last_movement_id` bigint(20) unsigned DEFAULT NULL,
  `source_mode` enum('LIVE','REBUILD','OPNAME_GENERATE','OPENING_CARRY_FORWARD') NOT NULL DEFAULT 'LIVE',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_component_monthly_stock_scope` (`month_key`,`location_type`,`division_id`,`component_id`,`uom_id`) USING BTREE,
  KEY `idx_inv_component_monthly_stock_month` (`month_key`) USING BTREE,
  KEY `idx_inv_component_monthly_stock_scope_month` (`location_type`,`division_id`,`month_key`) USING BTREE,
  KEY `idx_inv_component_monthly_stock_component` (`component_id`) USING BTREE,
  KEY `fk_inv_component_monthly_stock_division` (`division_id`) USING BTREE,
  KEY `fk_inv_component_monthly_stock_uom` (`uom_id`) USING BTREE,
  CONSTRAINT `fk_inv_component_monthly_stock_component` FOREIGN KEY (`component_id`) REFERENCES `mst_component` (`id`),
  CONSTRAINT `fk_inv_component_monthly_stock_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_inv_component_monthly_stock_uom` FOREIGN KEY (`uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_component_movement_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `movement_no` varchar(60) NOT NULL,
  `movement_date` date NOT NULL,
  `movement_datetime` datetime NOT NULL,
  `location_type` enum('BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT') NOT NULL DEFAULT 'KITCHEN',
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `component_id` bigint(20) unsigned NOT NULL,
  `uom_id` bigint(20) unsigned NOT NULL,
  `movement_type` enum('OPENING','PRODUCTION_IN','PRODUCTION_OUT','TRANSFER_IN','TRANSFER_OUT','USAGE','WASTE','SPOIL','ADJUSTMENT_PLUS','ADJUSTMENT_MINUS','VOID_REVERSE','VOID_OUT') NOT NULL,
  `qty_in` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_out` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `total_cost` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_module` varchar(50) NOT NULL,
  `source_table` varchar(80) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `source_line_id` bigint(20) unsigned DEFAULT NULL,
  `reversal_of_movement_id` bigint(20) unsigned DEFAULT NULL,
  `lot_no_snapshot` varchar(80) DEFAULT NULL,
  `received_date_snapshot` date DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_component_movement_no` (`movement_no`) USING BTREE,
  KEY `idx_inv_component_movement_main` (`movement_date`,`location_type`,`division_id`,`component_id`) USING BTREE,
  KEY `idx_inv_component_movement_component_date` (`component_id`,`movement_date`) USING BTREE,
  KEY `idx_inv_component_movement_source` (`source_module`,`source_table`,`source_id`) USING BTREE,
  KEY `idx_inv_component_movement_location_component` (`location_type`,`division_id`,`component_id`) USING BTREE,
  KEY `fk_inv_component_movement_division` (`division_id`) USING BTREE,
  KEY `fk_inv_component_movement_uom` (`uom_id`) USING BTREE,
  KEY `fk_inv_component_movement_by` (`created_by`) USING BTREE,
  KEY `idx_inv_component_movement_reversal` (`reversal_of_movement_id`,`movement_date`) USING BTREE,
  CONSTRAINT `fk_inv_component_movement_by` FOREIGN KEY (`created_by`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_inv_component_movement_component` FOREIGN KEY (`component_id`) REFERENCES `mst_component` (`id`),
  CONSTRAINT `fk_inv_component_movement_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_inv_component_movement_uom` FOREIGN KEY (`uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_component_opening` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `opening_no` varchar(60) NOT NULL,
  `opening_date` date NOT NULL,
  `location_type` enum('BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT') NOT NULL DEFAULT 'KITCHEN',
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('DRAFT','POSTED','VOID') NOT NULL DEFAULT 'DRAFT',
  `notes` varchar(255) DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_component_opening_no` (`opening_no`) USING BTREE,
  KEY `idx_inv_component_opening_date` (`opening_date`) USING BTREE,
  KEY `idx_inv_component_opening_scope` (`location_type`,`division_id`) USING BTREE,
  KEY `fk_inv_component_opening_division` (`division_id`) USING BTREE,
  KEY `fk_inv_component_opening_created_by` (`created_by`) USING BTREE,
  KEY `fk_inv_component_opening_posted_by` (`posted_by`) USING BTREE,
  CONSTRAINT `fk_inv_component_opening_created_by` FOREIGN KEY (`created_by`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_inv_component_opening_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_inv_component_opening_posted_by` FOREIGN KEY (`posted_by`) REFERENCES `org_employee` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_component_opening_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `opening_id` bigint(20) unsigned NOT NULL,
  `line_no` int(11) NOT NULL,
  `component_id` bigint(20) unsigned NOT NULL,
  `uom_id` bigint(20) unsigned NOT NULL,
  `opening_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_inv_component_opening_line_opening` (`opening_id`) USING BTREE,
  KEY `idx_inv_component_opening_line_component` (`component_id`) USING BTREE,
  KEY `fk_inv_component_opening_line_uom` (`uom_id`) USING BTREE,
  CONSTRAINT `fk_inv_component_opening_line_component` FOREIGN KEY (`component_id`) REFERENCES `mst_component` (`id`),
  CONSTRAINT `fk_inv_component_opening_line_opening` FOREIGN KEY (`opening_id`) REFERENCES `inv_component_opening` (`id`),
  CONSTRAINT `fk_inv_component_opening_line_uom` FOREIGN KEY (`uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_component_stock_opname` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `opname_date` date NOT NULL,
  `location_type` varchar(20) NOT NULL DEFAULT 'REGULER' COMMENT 'REGULER atau EVENT (grup dari inv_component_monthly_stock)',
  `division_id` int(10) unsigned DEFAULT NULL,
  `component_id` int(10) unsigned NOT NULL,
  `uom_id` int(10) unsigned NOT NULL,
  `lot_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `system_qty` decimal(18,4) DEFAULT NULL COMMENT 'Qty sistem saat opname diambil',
  `physical_qty` decimal(18,4) DEFAULT NULL COMMENT 'Qty fisik hasil hitung',
  `notes` text DEFAULT NULL,
  `adjustment_id` int(10) unsigned DEFAULT NULL COMMENT 'ID adjustment yang dibuat dari opname ini',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_cmp_opname` (`opname_date`,`location_type`,`division_id`,`component_id`,`uom_id`) USING BTREE,
  UNIQUE KEY `uk_component_stock_opname` (`opname_date`,`location_type`,`division_id`,`component_id`,`uom_id`,`lot_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_daily_recon_checkpoint` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `checkpoint_date` date NOT NULL,
  `recon_domain` enum('MATERIAL','COMPONENT') NOT NULL,
  `division_id` bigint(20) unsigned NOT NULL,
  `checkpoint_stage` enum('OPEN','CLOSE') NOT NULL,
  `source_page` varchar(120) NOT NULL DEFAULT '',
  `notes` varchar(255) DEFAULT NULL,
  `confirmed_by` bigint(20) unsigned DEFAULT NULL,
  `confirmed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_inv_daily_recon_checkpoint` (`checkpoint_date`,`recon_domain`,`division_id`,`checkpoint_stage`) USING BTREE,
  KEY `idx_inv_daily_recon_checkpoint_date_stage` (`checkpoint_date`,`checkpoint_stage`) USING BTREE,
  KEY `idx_inv_daily_recon_checkpoint_division` (`division_id`) USING BTREE,
  KEY `idx_inv_daily_recon_checkpoint_user` (`confirmed_by`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_daily_recon_checkpoint_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `checkpoint_date` date NOT NULL,
  `recon_domain` enum('MATERIAL','COMPONENT') NOT NULL,
  `division_id` bigint(20) unsigned NOT NULL,
  `checkpoint_stage` enum('OPEN','CLOSE') NOT NULL,
  `line_key` varchar(191) NOT NULL,
  `line_label` varchar(180) NOT NULL DEFAULT '',
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `profile_key` varchar(80) DEFAULT NULL,
  `component_id` bigint(20) unsigned DEFAULT NULL,
  `uom_id` bigint(20) unsigned DEFAULT NULL,
  `lot_id` bigint(20) unsigned DEFAULT NULL,
  `required_reason` varchar(120) DEFAULT NULL,
  `source_page` varchar(120) NOT NULL DEFAULT '',
  `notes` varchar(255) DEFAULT NULL,
  `confirmed_by` bigint(20) unsigned DEFAULT NULL,
  `confirmed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_inv_daily_recon_checkpoint_line` (`checkpoint_date`,`recon_domain`,`division_id`,`checkpoint_stage`,`line_key`) USING BTREE,
  KEY `idx_inv_daily_recon_checkpoint_line_scope` (`checkpoint_date`,`recon_domain`,`division_id`,`checkpoint_stage`) USING BTREE,
  KEY `idx_inv_daily_recon_checkpoint_line_material` (`material_id`) USING BTREE,
  KEY `idx_inv_daily_recon_checkpoint_line_component` (`component_id`) USING BTREE,
  KEY `idx_inv_daily_recon_checkpoint_line_user` (`confirmed_by`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_division_monthly_opening` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `month_key` date NOT NULL,
  `division_id` bigint(20) unsigned NOT NULL,
  `destination_type` enum('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  `identity_key` char(64) NOT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `profile_key` char(64) DEFAULT NULL,
  `profile_name` varchar(150) DEFAULT NULL,
  `profile_brand` varchar(120) DEFAULT NULL,
  `profile_description` varchar(255) DEFAULT NULL,
  `profile_expired_date` date DEFAULT NULL,
  `profile_content_per_buy` decimal(18,6) DEFAULT NULL,
  `profile_buy_uom_code` varchar(40) DEFAULT NULL,
  `profile_content_uom_code` varchar(40) DEFAULT NULL,
  `opening_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_avg_cost_per_content` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `opening_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_type` enum('MANUAL','AUTO_CARRY_FORWARD','AUTO_REBUILD','OPNAME') NOT NULL DEFAULT 'MANUAL',
  `source_month_key` date DEFAULT NULL,
  `source_ref_table` varchar(80) DEFAULT NULL,
  `source_ref_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `generated_by` bigint(20) unsigned DEFAULT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_div_monthly_opening_identity` (`month_key`,`division_id`,`destination_type`,`identity_key`) USING BTREE,
  KEY `idx_inv_div_monthly_opening_month` (`month_key`) USING BTREE,
  KEY `idx_inv_div_monthly_opening_scope` (`division_id`,`destination_type`,`month_key`) USING BTREE,
  KEY `idx_inv_div_monthly_opening_item` (`item_id`) USING BTREE,
  KEY `idx_inv_div_monthly_opening_material` (`material_id`) USING BTREE,
  KEY `fk_inv_div_monthly_opening_buy_uom` (`buy_uom_id`) USING BTREE,
  KEY `fk_inv_div_monthly_opening_content_uom` (`content_uom_id`) USING BTREE,
  KEY `fk_inv_div_monthly_opening_by` (`generated_by`) USING BTREE,
  CONSTRAINT `fk_inv_div_monthly_opening_buy_uom` FOREIGN KEY (`buy_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_inv_div_monthly_opening_by` FOREIGN KEY (`generated_by`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_inv_div_monthly_opening_content_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_inv_div_monthly_opening_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_inv_div_monthly_opening_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_inv_div_monthly_opening_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_division_monthly_opname` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `month_key` date NOT NULL,
  `division_id` bigint(20) unsigned NOT NULL,
  `destination_type` enum('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `profile_key` char(64) DEFAULT NULL,
  `profile_name` varchar(150) DEFAULT NULL,
  `profile_brand` varchar(120) DEFAULT NULL,
  `profile_description` varchar(255) DEFAULT NULL,
  `profile_expired_date` date DEFAULT NULL,
  `profile_content_per_buy` decimal(18,6) DEFAULT NULL,
  `profile_buy_uom_code` varchar(40) DEFAULT NULL,
  `profile_content_uom_code` varchar(40) DEFAULT NULL,
  `opening_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `in_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `in_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `out_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `out_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `discarded_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `discarded_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `spoil_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `spoil_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `waste_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `waste_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `process_loss_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `process_loss_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `variance_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `variance_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_plus_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_plus_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `closing_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `closing_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `avg_cost_per_content` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `waste_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `spoilage_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `process_loss_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `variance_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `adjustment_plus_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `movement_day_count` int(10) unsigned NOT NULL DEFAULT 0,
  `mutation_count` int(10) unsigned NOT NULL DEFAULT 0,
  `generated_by` bigint(20) unsigned DEFAULT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_div_opname_profile_month` (`month_key`,`division_id`,`destination_type`,`item_id`,`material_id`,`buy_uom_id`,`content_uom_id`,`profile_key`) USING BTREE,
  KEY `idx_inv_div_opname_month` (`month_key`) USING BTREE,
  KEY `idx_inv_div_opname_division` (`division_id`) USING BTREE,
  KEY `idx_inv_div_opname_destination` (`destination_type`) USING BTREE,
  KEY `idx_inv_div_opname_item` (`item_id`) USING BTREE,
  KEY `idx_inv_div_opname_material` (`material_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_division_monthly_stock` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `month_key` date NOT NULL,
  `division_id` bigint(20) unsigned NOT NULL,
  `destination_type` enum('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  `identity_key` char(64) NOT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `profile_key` char(64) DEFAULT NULL,
  `profile_name` varchar(150) DEFAULT NULL,
  `profile_brand` varchar(120) DEFAULT NULL,
  `profile_description` varchar(255) DEFAULT NULL,
  `profile_expired_date` date DEFAULT NULL,
  `profile_content_per_buy` decimal(18,6) DEFAULT NULL,
  `profile_buy_uom_code` varchar(40) DEFAULT NULL,
  `profile_content_uom_code` varchar(40) DEFAULT NULL,
  `opening_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `in_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `in_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `in_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `out_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `out_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `out_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discarded_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `discarded_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `discarded_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `spoil_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `spoil_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `spoilage_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `waste_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `waste_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `waste_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `process_loss_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `process_loss_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `process_loss_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `variance_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `variance_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `variance_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `adjustment_plus_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_plus_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_plus_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `adjustment_minus_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_minus_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_minus_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `closing_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `closing_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `avg_cost_per_content` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `movement_day_count` int(10) unsigned NOT NULL DEFAULT 0,
  `mutation_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_movement_date` date DEFAULT NULL,
  `last_movement_at` datetime DEFAULT NULL,
  `last_movement_table` varchar(80) DEFAULT NULL,
  `last_movement_id` bigint(20) unsigned DEFAULT NULL,
  `source_mode` enum('LIVE','REBUILD') NOT NULL DEFAULT 'LIVE',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_div_monthly_stock_identity` (`month_key`,`division_id`,`destination_type`,`identity_key`) USING BTREE,
  KEY `idx_inv_div_monthly_stock_month` (`month_key`) USING BTREE,
  KEY `idx_inv_div_monthly_stock_scope` (`division_id`,`destination_type`,`month_key`) USING BTREE,
  KEY `idx_inv_div_monthly_stock_item` (`item_id`) USING BTREE,
  KEY `idx_inv_div_monthly_stock_material` (`material_id`) USING BTREE,
  KEY `idx_inv_div_monthly_stock_profile` (`profile_key`) USING BTREE,
  KEY `fk_inv_div_monthly_stock_buy_uom` (`buy_uom_id`) USING BTREE,
  KEY `fk_inv_div_monthly_stock_content_uom` (`content_uom_id`) USING BTREE,
  CONSTRAINT `fk_inv_div_monthly_stock_buy_uom` FOREIGN KEY (`buy_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_inv_div_monthly_stock_content_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_inv_div_monthly_stock_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_inv_div_monthly_stock_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_inv_div_monthly_stock_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_division_stock_opening_snapshot` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `snapshot_month` date NOT NULL,
  `division_id` bigint(20) unsigned NOT NULL,
  `destination_type` enum('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `profile_key` char(64) NOT NULL,
  `profile_name` varchar(200) DEFAULT NULL,
  `profile_brand` varchar(120) DEFAULT NULL,
  `profile_description` varchar(255) DEFAULT NULL,
  `profile_expired_date` date DEFAULT NULL,
  `profile_content_per_buy` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `profile_buy_uom_code` varchar(30) DEFAULT NULL,
  `profile_content_uom_code` varchar(30) DEFAULT NULL,
  `opening_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_avg_cost_per_content` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `opening_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_type` enum('MANUAL','AUTO_REBUILD','OPNAME') NOT NULL DEFAULT 'MANUAL',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_div_opening_profile_month` (`snapshot_month`,`division_id`,`destination_type`,`item_id`,`material_id`,`buy_uom_id`,`content_uom_id`,`profile_key`) USING BTREE,
  KEY `idx_inv_div_opening_month` (`snapshot_month`) USING BTREE,
  KEY `idx_inv_div_opening_division` (`division_id`,`destination_type`) USING BTREE,
  KEY `idx_inv_div_opening_item` (`item_id`) USING BTREE,
  KEY `idx_inv_div_opening_material` (`material_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_division_stock_opname` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `opname_date` date NOT NULL,
  `division_id` bigint(20) unsigned NOT NULL,
  `destination_type` enum('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `profile_key` char(64) NOT NULL DEFAULT '',
  `identity_key` varchar(255) NOT NULL DEFAULT '',
  `profile_name` varchar(150) DEFAULT NULL,
  `profile_content_per_buy` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `profile_buy_uom_code` varchar(40) DEFAULT NULL,
  `profile_content_uom_code` varchar(40) DEFAULT NULL,
  `system_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `physical_qty_content` decimal(18,4) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `adjustment_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_opname_identity` (`opname_date`,`division_id`,`destination_type`,`identity_key`) USING BTREE,
  KEY `idx_opname_date` (`opname_date`) USING BTREE,
  KEY `idx_opname_division` (`division_id`) USING BTREE,
  KEY `idx_opname_adjustment` (`adjustment_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sesi opname stok fisik bahan baku divisi harian';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_item_material_source_map` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint(20) unsigned NOT NULL,
  `material_id` bigint(20) unsigned NOT NULL,
  `source_division_id` bigint(20) unsigned NOT NULL,
  `qty_material_per_item` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_item_material_source_map` (`item_id`,`material_id`,`source_division_id`) USING BTREE,
  KEY `idx_inv_item_material_source_map_item` (`item_id`) USING BTREE,
  KEY `idx_inv_item_material_source_map_material` (`material_id`) USING BTREE,
  KEY `idx_inv_item_material_source_map_source_div` (`source_division_id`) USING BTREE,
  CONSTRAINT `fk_inv_item_material_source_map_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_inv_item_material_source_map_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`),
  CONSTRAINT `fk_inv_item_material_source_map_source_div` FOREIGN KEY (`source_division_id`) REFERENCES `mst_operational_division` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_item_material_txn` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trx_no` varchar(40) NOT NULL,
  `trx_date` date NOT NULL,
  `source_division_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `material_id` bigint(20) unsigned NOT NULL,
  `qty_item` decimal(18,4) NOT NULL,
  `qty_material` decimal(18,4) NOT NULL,
  `conversion_factor` decimal(18,6) NOT NULL,
  `ref_type` varchar(30) NOT NULL DEFAULT 'MANUAL',
  `ref_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_item_material_txn_no` (`trx_no`) USING BTREE,
  KEY `idx_inv_item_material_txn_date` (`trx_date`) USING BTREE,
  KEY `idx_inv_item_material_txn_source_div` (`source_division_id`) USING BTREE,
  KEY `idx_inv_item_material_txn_item` (`item_id`) USING BTREE,
  KEY `idx_inv_item_material_txn_material` (`material_id`) USING BTREE,
  CONSTRAINT `fk_inv_item_material_txn_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_inv_item_material_txn_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`),
  CONSTRAINT `fk_inv_item_material_txn_source_div` FOREIGN KEY (`source_division_id`) REFERENCES `mst_operational_division` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_item_source_balance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_division_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `qty_balance` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_item_source_balance` (`source_division_id`,`item_id`) USING BTREE,
  KEY `idx_inv_item_source_balance_item` (`item_id`) USING BTREE,
  CONSTRAINT `fk_inv_item_source_balance_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_inv_item_source_balance_source_div` FOREIGN KEY (`source_division_id`) REFERENCES `mst_operational_division` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_material_fifo_issue_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `issue_id` bigint(20) unsigned NOT NULL,
  `lot_id` bigint(20) unsigned NOT NULL,
  `target_lot_id` bigint(20) unsigned DEFAULT NULL,
  `qty_out` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `total_cost` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_balance_before` decimal(18,4) DEFAULT NULL,
  `source_balance_after` decimal(18,4) DEFAULT NULL,
  `target_balance_before` decimal(18,4) DEFAULT NULL,
  `target_balance_after` decimal(18,4) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_inv_material_fifo_issue_line_issue` (`issue_id`) USING BTREE,
  KEY `idx_inv_material_fifo_issue_line_lot` (`lot_id`) USING BTREE,
  KEY `idx_inv_material_fifo_issue_line_target` (`target_lot_id`) USING BTREE,
  CONSTRAINT `fk_inv_material_fifo_issue_line_issue` FOREIGN KEY (`issue_id`) REFERENCES `inv_material_fifo_issue_log` (`id`),
  CONSTRAINT `fk_inv_material_fifo_issue_line_lot` FOREIGN KEY (`lot_id`) REFERENCES `inv_material_fifo_lot` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_material_fifo_issue_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `issue_no` varchar(60) NOT NULL,
  `issue_date` date NOT NULL,
  `issue_datetime` datetime NOT NULL,
  `location_scope` enum('WAREHOUSE','DIVISION') NOT NULL DEFAULT 'WAREHOUSE',
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `destination_type` enum('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') DEFAULT NULL,
  `target_scope` enum('WAREHOUSE','DIVISION') DEFAULT NULL,
  `target_division_id` bigint(20) unsigned DEFAULT NULL,
  `target_destination_type` enum('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') DEFAULT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `profile_key` char(64) DEFAULT NULL,
  `issue_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_cost` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_module` varchar(50) NOT NULL,
  `source_table` varchar(80) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `source_line_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `status` enum('POSTED','VOID') NOT NULL DEFAULT 'POSTED',
  `voided_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_material_fifo_issue_no` (`issue_no`) USING BTREE,
  KEY `idx_inv_material_fifo_issue_scope` (`division_id`,`item_id`,`issue_date`) USING BTREE,
  KEY `fk_inv_material_fifo_issue_item` (`item_id`) USING BTREE,
  KEY `fk_inv_material_fifo_issue_material` (`material_id`) USING BTREE,
  KEY `fk_inv_material_fifo_issue_uom` (`content_uom_id`) USING BTREE,
  KEY `idx_inv_material_fifo_issue_source` (`source_table`,`source_id`,`source_line_id`,`status`) USING BTREE,
  CONSTRAINT `fk_inv_material_fifo_issue_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_inv_material_fifo_issue_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_inv_material_fifo_issue_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`),
  CONSTRAINT `fk_inv_material_fifo_issue_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_material_fifo_lot` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lot_no` varchar(80) NOT NULL,
  `location_scope` enum('WAREHOUSE','DIVISION') NOT NULL DEFAULT 'WAREHOUSE',
  `receipt_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `destination_type` enum('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') DEFAULT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `profile_key` char(64) DEFAULT NULL,
  `qty_in` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_out` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_balance` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `source_table` varchar(80) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `source_line_id` bigint(20) unsigned DEFAULT NULL,
  `receipt_id` bigint(20) unsigned DEFAULT NULL,
  `receipt_line_id` bigint(20) unsigned DEFAULT NULL,
  `parent_lot_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_material_fifo_scope_lot` (`location_scope`,`division_id`,`destination_type`,`item_id`,`material_id`,`content_uom_id`,`profile_key`,`lot_no`) USING BTREE,
  KEY `idx_inv_material_fifo_pick` (`division_id`,`item_id`,`status`,`receipt_date`,`id`) USING BTREE,
  KEY `idx_inv_material_fifo_material` (`material_id`) USING BTREE,
  KEY `fk_inv_material_fifo_item` (`item_id`) USING BTREE,
  KEY `fk_inv_material_fifo_uom` (`content_uom_id`) USING BTREE,
  KEY `idx_inv_material_fifo_pick_scope` (`location_scope`,`division_id`,`destination_type`,`item_id`,`material_id`,`content_uom_id`,`profile_key`,`status`,`receipt_date`,`id`) USING BTREE,
  KEY `idx_inv_material_fifo_source` (`source_table`,`source_id`,`source_line_id`) USING BTREE,
  KEY `idx_inv_material_fifo_receipt_line` (`receipt_line_id`) USING BTREE,
  KEY `idx_inv_material_fifo_parent` (`parent_lot_id`) USING BTREE,
  CONSTRAINT `fk_inv_material_fifo_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_inv_material_fifo_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_inv_material_fifo_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`),
  CONSTRAINT `fk_inv_material_fifo_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_material_source_balance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_division_id` bigint(20) unsigned NOT NULL,
  `material_id` bigint(20) unsigned NOT NULL,
  `qty_balance` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_material_source_balance` (`source_division_id`,`material_id`) USING BTREE,
  KEY `idx_inv_material_source_balance_material` (`material_id`) USING BTREE,
  CONSTRAINT `fk_inv_material_source_balance_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`),
  CONSTRAINT `fk_inv_material_source_balance_source_div` FOREIGN KEY (`source_division_id`) REFERENCES `mst_operational_division` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_adjustment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `adjustment_no` varchar(60) NOT NULL,
  `adjustment_date` date NOT NULL,
  `stock_scope` enum('WAREHOUSE','DIVISION') NOT NULL DEFAULT 'WAREHOUSE',
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `destination_type` enum('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  `status` enum('DRAFT','POSTED','VOID') NOT NULL DEFAULT 'DRAFT',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_stock_adjustment_no` (`adjustment_no`) USING BTREE,
  KEY `idx_inv_stock_adjustment_scope_date` (`stock_scope`,`adjustment_date`) USING BTREE,
  KEY `idx_inv_stock_adjustment_division` (`division_id`,`destination_type`) USING BTREE,
  KEY `idx_inv_stock_adjustment_status` (`status`) USING BTREE,
  CONSTRAINT `fk_inv_stock_adjustment_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_adjustment_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `adjustment_id` bigint(20) unsigned NOT NULL,
  `line_no` int(11) NOT NULL,
  `input_mode` enum('DELTA','PHYSICAL_COUNT') NOT NULL DEFAULT 'DELTA',
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `profile_key` char(64) DEFAULT NULL,
  `profile_name` varchar(150) DEFAULT NULL,
  `profile_brand` varchar(120) DEFAULT NULL,
  `profile_description` varchar(255) DEFAULT NULL,
  `profile_expired_date` date DEFAULT NULL,
  `profile_content_per_buy` decimal(18,6) DEFAULT NULL,
  `profile_buy_uom_code` varchar(40) DEFAULT NULL,
  `profile_content_uom_code` varchar(40) DEFAULT NULL,
  `available_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `available_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `system_qty_snapshot_content` decimal(18,4) DEFAULT NULL,
  `physical_qty_snapshot_content` decimal(18,4) DEFAULT NULL,
  `settle_open_deficit` tinyint(1) NOT NULL DEFAULT 0,
  `settle_open_deficit_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `deficit_settled_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `qty_waste_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `waste_reason_code` varchar(64) DEFAULT NULL,
  `qty_spoil_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `spoil_reason_code` varchar(64) DEFAULT NULL,
  `qty_process_loss_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `process_loss_reason_code` varchar(64) DEFAULT NULL,
  `qty_variance_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `variance_reason_code` varchar(64) DEFAULT NULL,
  `qty_adjustment_plus_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_plus_reason_code` varchar(64) DEFAULT NULL,
  `inbound_lot_no` varchar(80) DEFAULT NULL,
  `inbound_expiry_date` date DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `waste_issue_id` bigint(20) unsigned DEFAULT NULL,
  `spoil_issue_id` bigint(20) unsigned DEFAULT NULL,
  `process_loss_issue_id` bigint(20) unsigned DEFAULT NULL,
  `variance_issue_id` bigint(20) unsigned DEFAULT NULL,
  `adjustment_plus_lot_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_stock_adjustment_line_no` (`adjustment_id`,`line_no`) USING BTREE,
  KEY `idx_inv_stock_adjustment_line_item` (`item_id`) USING BTREE,
  KEY `idx_inv_stock_adjustment_line_material` (`material_id`) USING BTREE,
  KEY `idx_inv_stock_adjustment_line_profile` (`profile_key`) USING BTREE,
  KEY `idx_inv_stock_adjustment_line_waste_issue` (`waste_issue_id`) USING BTREE,
  KEY `idx_inv_stock_adjustment_line_spoil_issue` (`spoil_issue_id`) USING BTREE,
  KEY `idx_inv_stock_adjustment_line_process_loss_issue` (`process_loss_issue_id`) USING BTREE,
  KEY `idx_inv_stock_adjustment_line_variance_issue` (`variance_issue_id`) USING BTREE,
  KEY `idx_inv_stock_adjustment_line_lot` (`adjustment_plus_lot_id`) USING BTREE,
  KEY `fk_inv_stock_adjustment_line_buy_uom` (`buy_uom_id`) USING BTREE,
  KEY `fk_inv_stock_adjustment_line_content_uom` (`content_uom_id`) USING BTREE,
  CONSTRAINT `fk_inv_stock_adjustment_line_adjustment` FOREIGN KEY (`adjustment_id`) REFERENCES `inv_stock_adjustment` (`id`),
  CONSTRAINT `fk_inv_stock_adjustment_line_buy_uom` FOREIGN KEY (`buy_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_inv_stock_adjustment_line_content_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_inv_stock_adjustment_line_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_inv_stock_adjustment_line_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_cutoff_event` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stock_domain` enum('MATERIAL','COMPONENT') NOT NULL,
  `event_date` date NOT NULL,
  `period_month` date NOT NULL,
  `location_scope` varchar(30) NOT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `destination_type` varchar(30) DEFAULT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `component_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned DEFAULT NULL,
  `profile_key` char(64) DEFAULT NULL,
  `lot_id` bigint(20) unsigned DEFAULT NULL,
  `direction` enum('IN','OUT') NOT NULL,
  `qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_table` varchar(80) NOT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `source_line_id` bigint(20) unsigned DEFAULT NULL,
  `movement_table` varchar(80) DEFAULT NULL,
  `movement_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_inv_stock_cutoff_event_period` (`stock_domain`,`period_month`,`event_date`) USING BTREE,
  KEY `idx_inv_stock_cutoff_event_identity` (`stock_domain`,`item_id`,`material_id`,`component_id`,`content_uom_id`,`profile_key`) USING BTREE,
  KEY `idx_inv_stock_cutoff_event_source` (`source_table`,`source_id`,`source_line_id`) USING BTREE,
  KEY `fk_inv_stock_cutoff_event_division` (`division_id`) USING BTREE,
  KEY `fk_inv_stock_cutoff_event_created_by` (`created_by`) USING BTREE,
  CONSTRAINT `fk_inv_stock_cutoff_event_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_stock_cutoff_event_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_cutoff_run` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cutoff_no` varchar(80) NOT NULL,
  `period_id` bigint(20) unsigned NOT NULL,
  `stock_domain` enum('MATERIAL','COMPONENT') NOT NULL,
  `period_month` date NOT NULL,
  `opening_month` date NOT NULL,
  `status` enum('RUNNING','POSTED','FAILED','PARTIAL') NOT NULL DEFAULT 'RUNNING',
  `attempt_no` int(10) unsigned NOT NULL DEFAULT 1,
  `preview_source_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `preview_candidate_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `preview_zero_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `preview_negative_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `preview_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `generated_opname_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `generated_opening_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `generated_monthly_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `result_payload` longtext DEFAULT NULL,
  `error_message` varchar(1000) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `started_by` bigint(20) unsigned DEFAULT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `finished_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_stock_cutoff_run_no` (`cutoff_no`) USING BTREE,
  UNIQUE KEY `uk_inv_stock_cutoff_run_attempt` (`period_id`,`attempt_no`) USING BTREE,
  KEY `idx_inv_stock_cutoff_run_period_status` (`period_id`,`status`,`started_at`) USING BTREE,
  KEY `idx_inv_stock_cutoff_run_domain_month` (`stock_domain`,`period_month`,`status`) USING BTREE,
  KEY `idx_inv_stock_cutoff_run_started_by` (`started_by`) USING BTREE,
  CONSTRAINT `fk_inv_stock_cutoff_run_period` FOREIGN KEY (`period_id`) REFERENCES `inv_stock_period` (`id`),
  CONSTRAINT `fk_inv_stock_cutoff_run_started_by` FOREIGN KEY (`started_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_deficit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `deficit_key` char(64) NOT NULL,
  `stock_domain` enum('MATERIAL','COMPONENT') NOT NULL,
  `deficit_date` date NOT NULL,
  `location_scope` varchar(30) NOT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `destination_type` varchar(30) DEFAULT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `component_id` bigint(20) unsigned DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned DEFAULT NULL,
  `profile_key` char(64) DEFAULT NULL,
  `requested_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `issued_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `settled_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `reversed_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `written_off_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_remaining` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `estimated_unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `estimated_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `written_off_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('OPEN','SETTLED','VOID','WRITTEN_OFF') NOT NULL DEFAULT 'OPEN',
  `source_module` varchar(50) NOT NULL,
  `source_table` varchar(80) NOT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `source_line_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `written_off_reason_code` varchar(50) DEFAULT NULL,
  `written_off_notes` varchar(255) DEFAULT NULL,
  `written_off_by` bigint(20) unsigned DEFAULT NULL,
  `written_off_at` datetime DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `voided_by` bigint(20) unsigned DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_stock_deficit_key` (`deficit_key`) USING BTREE,
  KEY `idx_inv_stock_deficit_open_scope` (`stock_domain`,`status`,`deficit_date`,`location_scope`,`division_id`) USING BTREE,
  KEY `idx_inv_stock_deficit_identity` (`stock_domain`,`item_id`,`material_id`,`component_id`,`content_uom_id`,`profile_key`) USING BTREE,
  KEY `idx_inv_stock_deficit_source` (`source_table`,`source_id`,`source_line_id`,`status`) USING BTREE,
  KEY `fk_inv_stock_deficit_division` (`division_id`) USING BTREE,
  KEY `fk_inv_stock_deficit_item` (`item_id`) USING BTREE,
  KEY `fk_inv_stock_deficit_material` (`material_id`) USING BTREE,
  KEY `fk_inv_stock_deficit_component` (`component_id`) USING BTREE,
  KEY `fk_inv_stock_deficit_created_by` (`created_by`) USING BTREE,
  KEY `fk_inv_stock_deficit_voided_by` (`voided_by`) USING BTREE,
  KEY `idx_inv_stock_deficit_written_off` (`status`,`written_off_at`) USING BTREE,
  CONSTRAINT `fk_inv_stock_deficit_component` FOREIGN KEY (`component_id`) REFERENCES `mst_component` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_stock_deficit_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_stock_deficit_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_stock_deficit_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_stock_deficit_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_stock_deficit_voided_by` FOREIGN KEY (`voided_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_deficit_cogs_adjustment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `deficit_id` bigint(20) unsigned NOT NULL,
  `deficit_settlement_id` bigint(20) unsigned NOT NULL,
  `stock_domain` enum('MATERIAL','COMPONENT') NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `order_line_id` bigint(20) unsigned DEFAULT NULL,
  `stock_commit_id` bigint(20) unsigned DEFAULT NULL,
  `stock_commit_line_id` bigint(20) unsigned DEFAULT NULL,
  `operational_division_id` bigint(20) unsigned DEFAULT NULL,
  `sale_date` date NOT NULL,
  `settlement_date` date NOT NULL,
  `recognition_date` date NOT NULL,
  `recognition_period_month` date NOT NULL,
  `recognition_policy` enum('SALE_MONTH_OPEN','SETTLEMENT_MONTH') NOT NULL DEFAULT 'SETTLEMENT_MONTH',
  `qty_adjusted` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `provisional_unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `provisional_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `actual_unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `actual_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `variance_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('POSTED','VOID') NOT NULL DEFAULT 'POSTED',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `voided_by` bigint(20) unsigned DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_deficit_cogs_settlement` (`deficit_settlement_id`) USING BTREE,
  KEY `idx_inv_deficit_cogs_recognition` (`status`,`recognition_date`,`operational_division_id`) USING BTREE,
  KEY `idx_inv_deficit_cogs_order` (`order_id`,`order_line_id`) USING BTREE,
  KEY `idx_inv_deficit_cogs_commit_line` (`stock_commit_id`,`stock_commit_line_id`) USING BTREE,
  KEY `fk_inv_deficit_cogs_deficit` (`deficit_id`) USING BTREE,
  KEY `fk_inv_deficit_cogs_order_line` (`order_line_id`) USING BTREE,
  KEY `fk_inv_deficit_cogs_commit_line` (`stock_commit_line_id`) USING BTREE,
  KEY `fk_inv_deficit_cogs_division` (`operational_division_id`) USING BTREE,
  KEY `fk_inv_deficit_cogs_created_by` (`created_by`) USING BTREE,
  KEY `fk_inv_deficit_cogs_voided_by` (`voided_by`) USING BTREE,
  CONSTRAINT `fk_inv_deficit_cogs_commit` FOREIGN KEY (`stock_commit_id`) REFERENCES `pos_stock_commit` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_deficit_cogs_commit_line` FOREIGN KEY (`stock_commit_line_id`) REFERENCES `pos_stock_commit_line` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_deficit_cogs_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_deficit_cogs_deficit` FOREIGN KEY (`deficit_id`) REFERENCES `inv_stock_deficit` (`id`),
  CONSTRAINT `fk_inv_deficit_cogs_division` FOREIGN KEY (`operational_division_id`) REFERENCES `mst_operational_division` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_deficit_cogs_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_deficit_cogs_order_line` FOREIGN KEY (`order_line_id`) REFERENCES `pos_order_line` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_deficit_cogs_settlement` FOREIGN KEY (`deficit_settlement_id`) REFERENCES `inv_stock_deficit_settlement` (`id`),
  CONSTRAINT `fk_inv_deficit_cogs_voided_by` FOREIGN KEY (`voided_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_deficit_cogs_reversal` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reversal_key` char(64) NOT NULL,
  `cogs_adjustment_id` bigint(20) unsigned NOT NULL,
  `deficit_id` bigint(20) unsigned NOT NULL,
  `reversal_date` date NOT NULL,
  `qty_reversed` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `provisional_amount_reversed` decimal(18,2) NOT NULL DEFAULT 0.00,
  `actual_amount_reversed` decimal(18,2) NOT NULL DEFAULT 0.00,
  `variance_amount_reversed` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_document_type` varchar(30) NOT NULL,
  `source_document_id` bigint(20) unsigned DEFAULT NULL,
  `source_document_no` varchar(80) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_deficit_cogs_reversal_key` (`reversal_key`) USING BTREE,
  KEY `idx_inv_deficit_cogs_reversal_adjustment` (`cogs_adjustment_id`,`reversal_date`) USING BTREE,
  KEY `idx_inv_deficit_cogs_reversal_source` (`source_document_type`,`source_document_id`) USING BTREE,
  KEY `fk_inv_deficit_cogs_reversal_deficit` (`deficit_id`) USING BTREE,
  KEY `fk_inv_deficit_cogs_reversal_created_by` (`created_by`) USING BTREE,
  CONSTRAINT `fk_inv_deficit_cogs_reversal_adjustment` FOREIGN KEY (`cogs_adjustment_id`) REFERENCES `inv_stock_deficit_cogs_adjustment` (`id`),
  CONSTRAINT `fk_inv_deficit_cogs_reversal_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_deficit_cogs_reversal_deficit` FOREIGN KEY (`deficit_id`) REFERENCES `inv_stock_deficit` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_deficit_settlement` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `deficit_id` bigint(20) unsigned NOT NULL,
  `settlement_date` date NOT NULL,
  `qty_settled` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_module` varchar(50) NOT NULL,
  `source_table` varchar(80) NOT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `source_line_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_inv_stock_deficit_settlement_deficit` (`deficit_id`,`settlement_date`) USING BTREE,
  KEY `idx_inv_stock_deficit_settlement_source` (`source_table`,`source_id`,`source_line_id`) USING BTREE,
  KEY `fk_inv_stock_deficit_settlement_created_by` (`created_by`) USING BTREE,
  CONSTRAINT `fk_inv_stock_deficit_settlement_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_stock_deficit_settlement_deficit` FOREIGN KEY (`deficit_id`) REFERENCES `inv_stock_deficit` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_movement_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `movement_no` varchar(60) NOT NULL,
  `movement_date` date NOT NULL,
  `movement_scope` enum('WAREHOUSE','DIVISION') NOT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `destination_type` enum('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  `movement_type` enum('PURCHASE_IN','TRANSFER_IN','TRANSFER_OUT','USAGE_OUT','DISCARDED_OUT','SPOIL_OUT','WASTE_OUT','PROCESS_LOSS_OUT','VARIANCE_OUT','ADJUSTMENT','ADJUSTMENT_IN','VOID_REVERSE') NOT NULL,
  `adjustment_category` enum('WASTE','SPOILAGE','PROCESS_LOSS','VARIANCE','ADJUSTMENT_PLUS') DEFAULT NULL,
  `adjustment_reason_code` varchar(64) DEFAULT NULL,
  `ref_table` varchar(80) DEFAULT NULL,
  `ref_id` bigint(20) unsigned DEFAULT NULL,
  `reversal_of_movement_id` bigint(20) unsigned DEFAULT NULL,
  `receipt_id` bigint(20) unsigned DEFAULT NULL,
  `receipt_line_id` bigint(20) unsigned DEFAULT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `qty_buy_delta` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_content_delta` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_buy_after` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_content_after` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `profile_key` char(64) DEFAULT NULL,
  `profile_name` varchar(150) DEFAULT NULL,
  `profile_brand` varchar(120) DEFAULT NULL,
  `profile_description` varchar(255) DEFAULT NULL,
  `profile_expired_date` date DEFAULT NULL,
  `profile_content_per_buy` decimal(18,6) DEFAULT NULL,
  `profile_buy_uom_code` varchar(40) DEFAULT NULL,
  `profile_content_uom_code` varchar(40) DEFAULT NULL,
  `unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_stock_movement_no` (`movement_no`) USING BTREE,
  KEY `idx_inv_stock_movement_date` (`movement_date`) USING BTREE,
  KEY `idx_inv_stock_movement_scope` (`movement_scope`,`division_id`) USING BTREE,
  KEY `idx_inv_stock_movement_ref` (`ref_table`,`ref_id`) USING BTREE,
  KEY `idx_inv_stock_movement_receipt` (`receipt_id`,`receipt_line_id`) USING BTREE,
  KEY `idx_inv_stock_movement_item` (`item_id`) USING BTREE,
  KEY `idx_inv_stock_movement_material` (`material_id`) USING BTREE,
  KEY `fk_inv_stock_movement_division` (`division_id`) USING BTREE,
  KEY `fk_inv_stock_movement_receipt_line` (`receipt_line_id`) USING BTREE,
  KEY `fk_inv_stock_movement_buy_uom` (`buy_uom_id`) USING BTREE,
  KEY `fk_inv_stock_movement_content_uom` (`content_uom_id`) USING BTREE,
  KEY `idx_inv_stock_movement_destination` (`movement_scope`,`division_id`,`destination_type`,`movement_date`) USING BTREE,
  KEY `idx_inv_movement_adjustment_category` (`adjustment_category`) USING BTREE,
  KEY `idx_inv_movement_adjustment_reason` (`adjustment_reason_code`) USING BTREE,
  KEY `idx_inv_stock_movement_reversal` (`reversal_of_movement_id`,`movement_date`) USING BTREE,
  CONSTRAINT `fk_inv_stock_movement_buy_uom` FOREIGN KEY (`buy_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_inv_stock_movement_content_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_inv_stock_movement_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_inv_stock_movement_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_inv_stock_movement_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`),
  CONSTRAINT `fk_inv_stock_movement_receipt` FOREIGN KEY (`receipt_id`) REFERENCES `pur_purchase_receipt` (`id`),
  CONSTRAINT `fk_inv_stock_movement_receipt_line` FOREIGN KEY (`receipt_line_id`) REFERENCES `pur_purchase_receipt_line` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_opening_snapshot` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `snapshot_month` date NOT NULL,
  `stock_scope` enum('WAREHOUSE','DIVISION') NOT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `destination_type` enum('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `profile_key` char(64) DEFAULT NULL,
  `profile_name` varchar(150) DEFAULT NULL,
  `profile_brand` varchar(120) DEFAULT NULL,
  `profile_description` varchar(255) DEFAULT NULL,
  `profile_expired_date` date DEFAULT NULL,
  `profile_content_per_buy` decimal(18,6) DEFAULT NULL,
  `profile_buy_uom_code` varchar(40) DEFAULT NULL,
  `profile_content_uom_code` varchar(40) DEFAULT NULL,
  `opening_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_avg_cost_per_content` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `opening_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_type` enum('AUTO_REBUILD','OPNAME','MANUAL') NOT NULL DEFAULT 'AUTO_REBUILD',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_opening_scope_profile_month` (`snapshot_month`,`stock_scope`,`division_id`,`destination_type`,`item_id`,`material_id`,`buy_uom_id`,`content_uom_id`,`profile_key`) USING BTREE,
  KEY `idx_inv_opening_month` (`snapshot_month`) USING BTREE,
  KEY `idx_inv_opening_scope` (`stock_scope`,`division_id`) USING BTREE,
  KEY `idx_inv_opening_item` (`item_id`) USING BTREE,
  KEY `idx_inv_opening_material` (`material_id`) USING BTREE,
  KEY `fk_inv_opening_division` (`division_id`) USING BTREE,
  KEY `fk_inv_opening_buy_uom` (`buy_uom_id`) USING BTREE,
  KEY `fk_inv_opening_content_uom` (`content_uom_id`) USING BTREE,
  KEY `idx_inv_opening_destination` (`stock_scope`,`division_id`,`destination_type`,`snapshot_month`) USING BTREE,
  CONSTRAINT `fk_inv_opening_buy_uom` FOREIGN KEY (`buy_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_inv_opening_content_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_inv_opening_division` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_inv_opening_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_inv_opening_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_period` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stock_domain` enum('MATERIAL','COMPONENT') NOT NULL,
  `period_month` date NOT NULL,
  `status` enum('OPEN','CLOSING','CLOSED','REOPENED') NOT NULL DEFAULT 'OPEN',
  `close_mode` enum('MONTHLY_OPNAME','MANUAL') NOT NULL DEFAULT 'MONTHLY_OPNAME',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `closed_by` bigint(20) unsigned DEFAULT NULL,
  `reopened_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `closed_at` datetime DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_stock_period_domain_month` (`stock_domain`,`period_month`) USING BTREE,
  KEY `idx_inv_stock_period_status_month` (`status`,`period_month`) USING BTREE,
  KEY `fk_inv_stock_period_created_by` (`created_by`) USING BTREE,
  KEY `fk_inv_stock_period_closed_by` (`closed_by`) USING BTREE,
  KEY `fk_inv_stock_period_reopened_by` (`reopened_by`) USING BTREE,
  CONSTRAINT `fk_inv_stock_period_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_stock_period_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_stock_period_reopened_by` FOREIGN KEY (`reopened_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_transfer` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_no` varchar(60) NOT NULL,
  `transfer_date` date NOT NULL,
  `from_division_id` bigint(20) unsigned NOT NULL,
  `from_destination_type` enum('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NOT NULL,
  `to_division_id` bigint(20) unsigned NOT NULL,
  `to_destination_type` enum('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NOT NULL,
  `status` enum('DRAFT','POSTED','VOID') NOT NULL DEFAULT 'DRAFT',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `voided_by` bigint(20) unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_stock_transfer_no` (`transfer_no`) USING BTREE,
  KEY `idx_inv_stock_transfer_date` (`transfer_date`) USING BTREE,
  KEY `idx_inv_stock_transfer_status` (`status`) USING BTREE,
  KEY `idx_inv_stock_transfer_from` (`from_division_id`,`from_destination_type`) USING BTREE,
  KEY `idx_inv_stock_transfer_to` (`to_division_id`,`to_destination_type`) USING BTREE,
  CONSTRAINT `fk_inv_stock_transfer_from_division` FOREIGN KEY (`from_division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_inv_stock_transfer_to_division` FOREIGN KEY (`to_division_id`) REFERENCES `mst_operational_division` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_transfer_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` bigint(20) unsigned NOT NULL,
  `line_no` int(11) NOT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned NOT NULL,
  `buy_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `profile_key` char(64) DEFAULT NULL,
  `profile_name` varchar(150) DEFAULT NULL,
  `profile_brand` varchar(120) DEFAULT NULL,
  `profile_description` varchar(255) DEFAULT NULL,
  `profile_expired_date` date DEFAULT NULL,
  `profile_content_per_buy` decimal(18,6) DEFAULT NULL,
  `profile_buy_uom_code` varchar(40) DEFAULT NULL,
  `profile_content_uom_code` varchar(40) DEFAULT NULL,
  `available_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `available_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `target_existing_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `qty_transfer_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_cost` decimal(18,2) NOT NULL DEFAULT 0.00,
  `transfer_issue_id` bigint(20) unsigned DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_stock_transfer_line_no` (`transfer_id`,`line_no`) USING BTREE,
  KEY `idx_inv_stock_transfer_line_item` (`item_id`) USING BTREE,
  KEY `idx_inv_stock_transfer_line_material` (`material_id`) USING BTREE,
  KEY `idx_inv_stock_transfer_line_profile` (`profile_key`) USING BTREE,
  KEY `idx_inv_stock_transfer_line_issue` (`transfer_issue_id`) USING BTREE,
  KEY `fk_inv_stock_transfer_line_buy_uom` (`buy_uom_id`) USING BTREE,
  KEY `fk_inv_stock_transfer_line_content_uom` (`content_uom_id`) USING BTREE,
  CONSTRAINT `fk_inv_stock_transfer_line_buy_uom` FOREIGN KEY (`buy_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_inv_stock_transfer_line_content_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_inv_stock_transfer_line_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_inv_stock_transfer_line_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`),
  CONSTRAINT `fk_inv_stock_transfer_line_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `inv_stock_transfer` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_value_reconciliation` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `revaluation_no` varchar(80) NOT NULL,
  `revaluation_date` date NOT NULL,
  `period_month` date NOT NULL,
  `stock_domain` enum('MATERIAL','COMPONENT') NOT NULL,
  `stock_scope` varchar(30) NOT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `location_type` varchar(30) DEFAULT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `component_id` bigint(20) unsigned DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned DEFAULT NULL,
  `profile_key` char(64) DEFAULT NULL,
  `monthly_stock_id` bigint(20) unsigned NOT NULL,
  `stock_qty_snapshot` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `lot_qty_snapshot` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `stock_value_before` decimal(18,2) NOT NULL DEFAULT 0.00,
  `lot_value_before` decimal(18,2) NOT NULL DEFAULT 0.00,
  `stock_value_after` decimal(18,2) NOT NULL DEFAULT 0.00,
  `lot_value_after` decimal(18,2) NOT NULL DEFAULT 0.00,
  `resolution_mode` enum('LOT_TO_STOCK','STOCK_TO_LOT','MANUAL_TOTAL_VALUE') NOT NULL,
  `reason` varchar(120) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `status` enum('POSTED','VOID') NOT NULL DEFAULT 'POSTED',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `voided_by` bigint(20) unsigned DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `void_notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_stock_value_reconciliation_no` (`revaluation_no`) USING BTREE,
  KEY `idx_inv_stock_value_reconciliation_period` (`period_month`,`stock_domain`,`status`) USING BTREE,
  KEY `idx_inv_stock_value_reconciliation_identity` (`stock_domain`,`stock_scope`,`division_id`,`location_type`,`item_id`,`material_id`,`component_id`,`content_uom_id`,`profile_key`) USING BTREE,
  KEY `idx_inv_stock_value_reconciliation_monthly_stock` (`monthly_stock_id`) USING BTREE,
  KEY `idx_inv_stock_value_reconciliation_created_by` (`created_by`) USING BTREE,
  KEY `fk_inv_stock_value_reconciliation_posted_by` (`posted_by`) USING BTREE,
  KEY `fk_inv_stock_value_reconciliation_voided_by` (`voided_by`) USING BTREE,
  CONSTRAINT `fk_inv_stock_value_reconciliation_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_stock_value_reconciliation_posted_by` FOREIGN KEY (`posted_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_stock_value_reconciliation_voided_by` FOREIGN KEY (`voided_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_value_reconciliation_lot` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `revaluation_id` bigint(20) unsigned NOT NULL,
  `lot_id` bigint(20) unsigned NOT NULL,
  `qty_balance_snapshot` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `old_unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `new_unit_cost` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `old_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `new_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_inv_stock_value_reconciliation_lot_header` (`revaluation_id`,`id`) USING BTREE,
  KEY `idx_inv_stock_value_reconciliation_lot_lot` (`lot_id`) USING BTREE,
  CONSTRAINT `fk_inv_stock_value_reconciliation_lot_header` FOREIGN KEY (`revaluation_id`) REFERENCES `inv_stock_value_reconciliation` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_warehouse_monthly_opening` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `month_key` date NOT NULL,
  `identity_key` char(64) NOT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `profile_key` char(64) DEFAULT NULL,
  `profile_name` varchar(150) DEFAULT NULL,
  `profile_brand` varchar(120) DEFAULT NULL,
  `profile_description` varchar(255) DEFAULT NULL,
  `profile_expired_date` date DEFAULT NULL,
  `profile_content_per_buy` decimal(18,6) DEFAULT NULL,
  `profile_buy_uom_code` varchar(40) DEFAULT NULL,
  `profile_content_uom_code` varchar(40) DEFAULT NULL,
  `opening_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_avg_cost_per_content` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `opening_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_type` enum('MANUAL','AUTO_CARRY_FORWARD','AUTO_REBUILD','OPNAME') NOT NULL DEFAULT 'MANUAL',
  `source_month_key` date DEFAULT NULL,
  `source_ref_table` varchar(80) DEFAULT NULL,
  `source_ref_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `generated_by` bigint(20) unsigned DEFAULT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_wh_monthly_opening_identity` (`month_key`,`identity_key`) USING BTREE,
  KEY `idx_inv_wh_monthly_opening_month` (`month_key`) USING BTREE,
  KEY `idx_inv_wh_monthly_opening_item` (`item_id`) USING BTREE,
  KEY `idx_inv_wh_monthly_opening_material` (`material_id`) USING BTREE,
  KEY `fk_inv_wh_monthly_opening_buy_uom` (`buy_uom_id`) USING BTREE,
  KEY `fk_inv_wh_monthly_opening_content_uom` (`content_uom_id`) USING BTREE,
  KEY `fk_inv_wh_monthly_opening_by` (`generated_by`) USING BTREE,
  CONSTRAINT `fk_inv_wh_monthly_opening_buy_uom` FOREIGN KEY (`buy_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_inv_wh_monthly_opening_by` FOREIGN KEY (`generated_by`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_inv_wh_monthly_opening_content_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_inv_wh_monthly_opening_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_inv_wh_monthly_opening_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_warehouse_monthly_opname` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `month_key` date NOT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `profile_key` char(64) DEFAULT NULL,
  `profile_name` varchar(150) DEFAULT NULL,
  `profile_brand` varchar(120) DEFAULT NULL,
  `profile_description` varchar(255) DEFAULT NULL,
  `profile_expired_date` date DEFAULT NULL,
  `profile_content_per_buy` decimal(18,6) DEFAULT NULL,
  `profile_buy_uom_code` varchar(40) DEFAULT NULL,
  `profile_content_uom_code` varchar(40) DEFAULT NULL,
  `opening_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `in_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `in_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `out_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `out_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `discarded_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `discarded_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `spoil_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `spoil_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `waste_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `waste_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `process_loss_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `process_loss_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `variance_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `variance_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_plus_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_plus_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `closing_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `closing_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `avg_cost_per_content` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `waste_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `spoilage_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `process_loss_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `variance_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `adjustment_plus_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `movement_day_count` int(10) unsigned NOT NULL DEFAULT 0,
  `mutation_count` int(10) unsigned NOT NULL DEFAULT 0,
  `generated_by` bigint(20) unsigned DEFAULT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_wh_opname_profile_month` (`month_key`,`item_id`,`material_id`,`buy_uom_id`,`content_uom_id`,`profile_key`) USING BTREE,
  KEY `idx_inv_wh_opname_month` (`month_key`) USING BTREE,
  KEY `idx_inv_wh_opname_item` (`item_id`) USING BTREE,
  KEY `idx_inv_wh_opname_material` (`material_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_warehouse_monthly_stock` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `month_key` date NOT NULL,
  `identity_key` char(64) NOT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `profile_key` char(64) DEFAULT NULL,
  `profile_name` varchar(150) DEFAULT NULL,
  `profile_brand` varchar(120) DEFAULT NULL,
  `profile_description` varchar(255) DEFAULT NULL,
  `profile_expired_date` date DEFAULT NULL,
  `profile_content_per_buy` decimal(18,6) DEFAULT NULL,
  `profile_buy_uom_code` varchar(40) DEFAULT NULL,
  `profile_content_uom_code` varchar(40) DEFAULT NULL,
  `opening_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `in_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `in_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `in_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `out_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `out_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `out_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discarded_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `discarded_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `discarded_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `spoil_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `spoil_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `spoilage_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `waste_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `waste_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `waste_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `process_loss_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `process_loss_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `process_loss_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `variance_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `variance_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `variance_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `adjustment_plus_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_plus_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_plus_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `adjustment_minus_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_minus_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `adjustment_minus_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `closing_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `closing_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `avg_cost_per_content` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `movement_day_count` int(10) unsigned NOT NULL DEFAULT 0,
  `mutation_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_movement_date` date DEFAULT NULL,
  `last_movement_at` datetime DEFAULT NULL,
  `last_movement_table` varchar(80) DEFAULT NULL,
  `last_movement_id` bigint(20) unsigned DEFAULT NULL,
  `source_mode` enum('LIVE','REBUILD') NOT NULL DEFAULT 'LIVE',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_wh_monthly_stock_identity` (`month_key`,`identity_key`) USING BTREE,
  KEY `idx_inv_wh_monthly_stock_month` (`month_key`) USING BTREE,
  KEY `idx_inv_wh_monthly_stock_item` (`item_id`) USING BTREE,
  KEY `idx_inv_wh_monthly_stock_material` (`material_id`) USING BTREE,
  KEY `idx_inv_wh_monthly_stock_profile` (`profile_key`) USING BTREE,
  KEY `fk_inv_wh_monthly_stock_buy_uom` (`buy_uom_id`) USING BTREE,
  KEY `fk_inv_wh_monthly_stock_content_uom` (`content_uom_id`) USING BTREE,
  CONSTRAINT `fk_inv_wh_monthly_stock_buy_uom` FOREIGN KEY (`buy_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_inv_wh_monthly_stock_content_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_inv_wh_monthly_stock_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_inv_wh_monthly_stock_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_warehouse_stock_opening_snapshot` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `snapshot_month` date NOT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `profile_key` char(64) NOT NULL,
  `profile_name` varchar(200) DEFAULT NULL,
  `profile_brand` varchar(120) DEFAULT NULL,
  `profile_description` varchar(255) DEFAULT NULL,
  `profile_expired_date` date DEFAULT NULL,
  `profile_content_per_buy` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `profile_buy_uom_code` varchar(30) DEFAULT NULL,
  `profile_content_uom_code` varchar(30) DEFAULT NULL,
  `opening_qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `opening_avg_cost_per_content` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `opening_total_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_type` enum('MANUAL','AUTO_REBUILD','OPNAME') NOT NULL DEFAULT 'MANUAL',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_inv_wh_opening_profile_month` (`snapshot_month`,`item_id`,`material_id`,`buy_uom_id`,`content_uom_id`,`profile_key`) USING BTREE,
  KEY `idx_inv_wh_opening_month` (`snapshot_month`) USING BTREE,
  KEY `idx_inv_wh_opening_item` (`item_id`) USING BTREE,
  KEY `idx_inv_wh_opening_material` (`material_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lp_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hero_title` text DEFAULT NULL,
  `hero_subtitle` text DEFAULT NULL,
  `hero_badges` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array: ["badge1","badge2",...]',
  `hero_image` varchar(500) DEFAULT NULL,
  `about_title` varchar(255) DEFAULT NULL,
  `about_text` text DEFAULT NULL,
  `about_points` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array: ["poin1","poin2",...]',
  `about_image` varchar(500) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `whatsapp` varchar(30) DEFAULT NULL COMMENT 'Format internasional tanpa +, mis: 6285150737377',
  `order_url` varchar(500) DEFAULT NULL,
  `member_url` varchar(500) DEFAULT NULL,
  `instagram_url` varchar(500) DEFAULT NULL,
  `linktree_url` varchar(500) DEFAULT NULL,
  `map_url` varchar(500) DEFAULT NULL,
  `cta_title` varchar(255) DEFAULT NULL,
  `cta_text` text DEFAULT NULL,
  `footer_text` text DEFAULT NULL,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` varchar(320) DEFAULT NULL,
  `seo_canonical_url` varchar(500) DEFAULT NULL,
  `seo_share_image` varchar(500) DEFAULT NULL,
  `seo_indexing` enum('index','noindex') NOT NULL DEFAULT 'index',
  `seo_google_verification` varchar(255) DEFAULT NULL,
  `menu_source` enum('manual','produk') NOT NULL DEFAULT 'produk',
  `menu_limit` tinyint(3) unsigned NOT NULL DEFAULT 8,
  `menu_best_seller_top` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `menu_kategori_ids` varchar(255) DEFAULT NULL COMMENT 'Comma-separated kategori_id untuk filter produk POS',
  `gallery_source` enum('manual','produk') NOT NULL DEFAULT 'manual',
  `gallery_limit` tinyint(3) unsigned NOT NULL DEFAULT 6,
  `gallery_kategori_ids` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Konfigurasi umum landing page Namua';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lp_embed` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `embed_type` enum('reel','photo') NOT NULL DEFAULT 'photo',
  `embed_html` text NOT NULL COMMENT 'Kode HTML embed dari Instagram',
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_active_type_sort` (`is_active`,`embed_type`,`sort_order`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kode embed Instagram landing page';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lp_gallery` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `image` varchar(500) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_active_sort` (`is_active`,`sort_order`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Foto gallery landing page';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lp_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(100) NOT NULL COMMENT 'Teks tombol',
  `url` varchar(500) NOT NULL COMMENT 'URL tujuan',
  `icon` varchar(50) DEFAULT NULL COMMENT 'Emoji atau teks singkat untuk ikon',
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_active_sort` (`is_active`,`sort_order`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tombol halaman links (Linktree) Namua';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lp_menu` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(500) DEFAULT NULL,
  `price` decimal(12,0) DEFAULT NULL COMMENT 'NULL = tidak ditampilkan',
  `is_best_seller` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_active_sort` (`is_active`,`sort_order`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Item menu carousel landing page';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_bank` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bank_code` varchar(10) NOT NULL,
  `bank_name` varchar(150) NOT NULL,
  `bank_alias` varchar(60) DEFAULT NULL,
  `is_sharia` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_bank_code` (`bank_code`) USING BTREE,
  KEY `idx_mst_bank_active_name` (`is_active`,`bank_name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_component` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `component_code` varchar(50) NOT NULL,
  `component_name` varchar(150) NOT NULL,
  `component_type` enum('BASE','PREPARE') NOT NULL,
  `product_division_id` bigint(20) unsigned NOT NULL,
  `operational_division_id` bigint(20) unsigned NOT NULL,
  `component_category_id` bigint(20) unsigned NOT NULL,
  `uom_id` bigint(20) unsigned NOT NULL,
  `yield_qty` decimal(18,4) NOT NULL DEFAULT 1.0000,
  `hpp_standard` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `variable_cost_mode` enum('DEFAULT','NONE','CUSTOM') NOT NULL DEFAULT 'DEFAULT',
  `variable_cost_percent` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `min_stock` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `description` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_component_code` (`component_code`) USING BTREE,
  KEY `idx_mst_component_division` (`operational_division_id`) USING BTREE,
  KEY `idx_mst_component_category` (`component_category_id`) USING BTREE,
  KEY `idx_mst_component_uom` (`uom_id`) USING BTREE,
  KEY `idx_mst_component_product_division` (`product_division_id`) USING BTREE,
  CONSTRAINT `fk_mst_component_category` FOREIGN KEY (`component_category_id`) REFERENCES `mst_component_category` (`id`),
  CONSTRAINT `fk_mst_component_division` FOREIGN KEY (`operational_division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_mst_component_product_division` FOREIGN KEY (`product_division_id`) REFERENCES `mst_product_division` (`id`),
  CONSTRAINT `fk_mst_component_uom` FOREIGN KEY (`uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_component_category` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `scope_type` enum('BASE','PREPARE','ALL') NOT NULL DEFAULT 'ALL',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_component_category_code` (`code`) USING BTREE,
  KEY `idx_mst_component_category_scope_type` (`scope_type`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_component_formula` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `component_id` bigint(20) unsigned NOT NULL,
  `line_no` int(11) NOT NULL DEFAULT 1,
  `line_type` enum('MATERIAL','COMPONENT') NOT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `material_item_id` bigint(20) unsigned DEFAULT NULL,
  `sub_component_id` bigint(20) unsigned DEFAULT NULL,
  `source_division_id` bigint(20) unsigned DEFAULT NULL,
  `qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `notes` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_mst_component_formula_component` (`component_id`) USING BTREE,
  KEY `idx_mst_component_formula_material_item` (`material_item_id`) USING BTREE,
  KEY `idx_mst_component_formula_sub_component` (`sub_component_id`) USING BTREE,
  KEY `idx_mst_component_formula_material` (`material_id`) USING BTREE,
  KEY `fk_mcf_source_div` (`source_division_id`) USING BTREE,
  CONSTRAINT `fk_mcf_source_div` FOREIGN KEY (`source_division_id`) REFERENCES `mst_operational_division` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mst_component_formula_component` FOREIGN KEY (`component_id`) REFERENCES `mst_component` (`id`),
  CONSTRAINT `fk_mst_component_formula_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`),
  CONSTRAINT `fk_mst_component_formula_material_item` FOREIGN KEY (`material_item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_mst_component_formula_sub_component` FOREIGN KEY (`sub_component_id`) REFERENCES `mst_component` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_component_formula_version` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `component_id` bigint(20) unsigned NOT NULL,
  `version_no` int(10) unsigned NOT NULL,
  `change_action` enum('BASELINE','REPLACE','RESTORE') NOT NULL,
  `formula_revision` char(64) NOT NULL,
  `line_count` int(10) unsigned NOT NULL DEFAULT 0,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `source_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mcfv_component_version` (`component_id`,`version_no`) USING BTREE,
  KEY `idx_mcfv_component_created` (`component_id`,`created_at`,`id`) USING BTREE,
  KEY `idx_mcfv_actor` (`actor_user_id`) USING BTREE,
  CONSTRAINT `fk_mcfv_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mcfv_component` FOREIGN KEY (`component_id`) REFERENCES `mst_component` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_component_formula_version_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `formula_version_id` bigint(20) unsigned NOT NULL,
  `original_line_id` bigint(20) unsigned DEFAULT NULL,
  `line_no` int(10) unsigned NOT NULL,
  `line_type` enum('MATERIAL','COMPONENT') NOT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `material_item_id` bigint(20) unsigned DEFAULT NULL,
  `sub_component_id` bigint(20) unsigned DEFAULT NULL,
  `source_division_id` bigint(20) unsigned DEFAULT NULL,
  `uom_id` bigint(20) unsigned DEFAULT NULL,
  `qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `notes` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_mcfvl_version_line` (`formula_version_id`,`line_no`,`id`) USING BTREE,
  KEY `idx_mcfvl_material` (`material_id`) USING BTREE,
  KEY `idx_mcfvl_component` (`sub_component_id`) USING BTREE,
  CONSTRAINT `fk_mcfvl_version` FOREIGN KEY (`formula_version_id`) REFERENCES `mst_component_formula_version` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_extra` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `extra_code` varchar(40) NOT NULL,
  `extra_name` varchar(120) NOT NULL,
  `uom_name` varchar(50) DEFAULT NULL,
  `extra_type` enum('ADD','REMOVE','CHOICE','INFO') NOT NULL DEFAULT 'ADD',
  `selling_price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `cost_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `source_kind` enum('NONE','PRODUCT','COMPONENT','MATERIAL') NOT NULL DEFAULT 'NONE',
  `source_product_id` bigint(20) unsigned DEFAULT NULL,
  `source_component_id` bigint(20) unsigned DEFAULT NULL,
  `source_material_id` bigint(20) unsigned DEFAULT NULL,
  `source_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `replacement_kind` enum('NONE','PRODUCT','COMPONENT','MATERIAL') NOT NULL DEFAULT 'NONE',
  `replacement_product_id` bigint(20) unsigned DEFAULT NULL,
  `replacement_component_id` bigint(20) unsigned DEFAULT NULL,
  `replacement_material_id` bigint(20) unsigned DEFAULT NULL,
  `replacement_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `show_in_cashier` tinyint(1) NOT NULL DEFAULT 1,
  `show_in_self_order` tinyint(1) NOT NULL DEFAULT 1,
  `show_online_food` tinyint(1) NOT NULL DEFAULT 1,
  `show_in_landing` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_extra_code` (`extra_code`) USING BTREE,
  KEY `idx_mst_extra_source_product` (`source_product_id`) USING BTREE,
  KEY `idx_mst_extra_source_component` (`source_component_id`) USING BTREE,
  KEY `idx_mst_extra_source_material` (`source_material_id`) USING BTREE,
  KEY `fk_mst_extra_replacement_product` (`replacement_product_id`) USING BTREE,
  KEY `fk_mst_extra_replacement_component` (`replacement_component_id`) USING BTREE,
  KEY `fk_mst_extra_replacement_material` (`replacement_material_id`) USING BTREE,
  KEY `idx_mst_extra_show_online_food` (`show_online_food`) USING BTREE,
  CONSTRAINT `fk_mst_extra_replacement_component` FOREIGN KEY (`replacement_component_id`) REFERENCES `mst_component` (`id`),
  CONSTRAINT `fk_mst_extra_replacement_material` FOREIGN KEY (`replacement_material_id`) REFERENCES `mst_material` (`id`),
  CONSTRAINT `fk_mst_extra_replacement_product` FOREIGN KEY (`replacement_product_id`) REFERENCES `mst_product` (`id`),
  CONSTRAINT `fk_mst_extra_source_component` FOREIGN KEY (`source_component_id`) REFERENCES `mst_component` (`id`),
  CONSTRAINT `fk_mst_extra_source_material` FOREIGN KEY (`source_material_id`) REFERENCES `mst_material` (`id`),
  CONSTRAINT `fk_mst_extra_source_product` FOREIGN KEY (`source_product_id`) REFERENCES `mst_product` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_extra_group` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group_code` varchar(40) NOT NULL,
  `group_name` varchar(120) NOT NULL,
  `product_division_id` bigint(20) unsigned DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `min_select` int(11) NOT NULL DEFAULT 0,
  `max_select` int(11) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_extra_group_code` (`group_code`) USING BTREE,
  KEY `idx_mst_extra_group_division` (`product_division_id`) USING BTREE,
  CONSTRAINT `fk_mst_extra_group_division` FOREIGN KEY (`product_division_id`) REFERENCES `mst_product_division` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_extra_group_item` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `extra_group_id` bigint(20) unsigned NOT NULL,
  `extra_id` bigint(20) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_extra_group_item` (`extra_group_id`,`extra_id`) USING BTREE,
  KEY `fk_mst_extra_group_item_extra` (`extra_id`) USING BTREE,
  CONSTRAINT `fk_mst_extra_group_item_extra` FOREIGN KEY (`extra_id`) REFERENCES `mst_extra` (`id`),
  CONSTRAINT `fk_mst_extra_group_item_group` FOREIGN KEY (`extra_group_id`) REFERENCES `mst_extra_group` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_item` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `item_code` varchar(50) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `item_category_id` bigint(20) unsigned NOT NULL,
  `buy_uom_id` bigint(20) unsigned NOT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `content_per_buy` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `min_stock_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `last_buy_price` decimal(18,2) DEFAULT NULL,
  `is_material` tinyint(1) NOT NULL DEFAULT 0,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `default_usage_purpose` varchar(20) NOT NULL DEFAULT 'BAHAN_BAKU',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_item_code` (`item_code`) USING BTREE,
  KEY `idx_mst_item_category` (`item_category_id`) USING BTREE,
  KEY `idx_mst_item_buy_uom` (`buy_uom_id`) USING BTREE,
  KEY `idx_mst_item_content_uom` (`content_uom_id`) USING BTREE,
  KEY `idx_mst_item_material_id` (`material_id`) USING BTREE,
  CONSTRAINT `fk_mst_item_buy_uom` FOREIGN KEY (`buy_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_mst_item_category` FOREIGN KEY (`item_category_id`) REFERENCES `mst_item_category` (`id`),
  CONSTRAINT `fk_mst_item_content_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_mst_item_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_item_category` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_item_category_code` (`code`) USING BTREE,
  KEY `idx_mst_item_category_parent` (`parent_id`) USING BTREE,
  CONSTRAINT `fk_mst_item_category_parent` FOREIGN KEY (`parent_id`) REFERENCES `mst_item_category` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_material` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `material_code` varchar(50) NOT NULL,
  `material_name` varchar(150) NOT NULL,
  `item_category_id` bigint(20) unsigned DEFAULT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `hpp_standard` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `shelf_life_days` int(10) unsigned DEFAULT NULL,
  `reorder_level_content` decimal(18,4) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_material_code` (`material_code`) USING BTREE,
  KEY `idx_mst_material_category` (`item_category_id`) USING BTREE,
  KEY `idx_mst_material_uom` (`content_uom_id`) USING BTREE,
  CONSTRAINT `fk_mst_material_category` FOREIGN KEY (`item_category_id`) REFERENCES `mst_item_category` (`id`),
  CONSTRAINT `fk_mst_material_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_operational_division` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_operational_division_code` (`code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_posting_type` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type_code` varchar(40) NOT NULL,
  `type_name` varchar(120) NOT NULL,
  `affects_inventory` tinyint(1) NOT NULL DEFAULT 0,
  `affects_service` tinyint(1) NOT NULL DEFAULT 0,
  `affects_asset` tinyint(1) NOT NULL DEFAULT 0,
  `affects_payroll` tinyint(1) NOT NULL DEFAULT 0,
  `affects_expense` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_posting_type_code` (`type_code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_product` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `product_division_id` bigint(20) unsigned NOT NULL,
  `default_operational_division_id` bigint(20) unsigned NOT NULL,
  `classification_id` bigint(20) unsigned NOT NULL,
  `product_category_id` bigint(20) unsigned NOT NULL,
  `uom_id` bigint(20) unsigned NOT NULL,
  `selling_price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `online_food_price` decimal(18,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `hpp_standard` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `hpp_live_cache` decimal(18,6) DEFAULT NULL,
  `hpp_live_at` datetime DEFAULT NULL,
  `hpp_dirty` tinyint(1) NOT NULL DEFAULT 1,
  `variable_cost_mode` enum('DEFAULT','NONE','CUSTOM') NOT NULL DEFAULT 'DEFAULT',
  `variable_cost_percent` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `stock_mode` enum('MANUAL_AVAILABLE','MANUAL_OUT','AUTO') NOT NULL DEFAULT 'AUTO',
  `show_pos` tinyint(1) NOT NULL DEFAULT 1,
  `show_member` tinyint(1) NOT NULL DEFAULT 0,
  `show_online_food` tinyint(1) NOT NULL DEFAULT 0,
  `show_landing` tinyint(1) NOT NULL DEFAULT 0,
  `photo_path` varchar(255) DEFAULT NULL,
  `photo_mime` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_product_code` (`product_code`) USING BTREE,
  KEY `idx_mst_product_division` (`product_division_id`) USING BTREE,
  KEY `idx_mst_product_classification` (`classification_id`) USING BTREE,
  KEY `idx_mst_product_category` (`product_category_id`) USING BTREE,
  KEY `idx_mst_product_default_operational_division` (`default_operational_division_id`) USING BTREE,
  KEY `fk_mst_product_uom` (`uom_id`) USING BTREE,
  KEY `idx_mst_product_online_food` (`show_online_food`) USING BTREE,
  CONSTRAINT `fk_mst_product_category` FOREIGN KEY (`product_category_id`) REFERENCES `mst_product_category` (`id`),
  CONSTRAINT `fk_mst_product_classification` FOREIGN KEY (`classification_id`) REFERENCES `mst_product_classification` (`id`),
  CONSTRAINT `fk_mst_product_default_operational_division` FOREIGN KEY (`default_operational_division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_mst_product_division` FOREIGN KEY (`product_division_id`) REFERENCES `mst_product_division` (`id`),
  CONSTRAINT `fk_mst_product_uom` FOREIGN KEY (`uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_product_category` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_division_id` bigint(20) unsigned NOT NULL,
  `classification_id` bigint(20) unsigned NOT NULL,
  `code` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_product_category_code` (`code`) USING BTREE,
  KEY `idx_mst_product_category_division` (`product_division_id`) USING BTREE,
  KEY `idx_mst_product_category_classification` (`classification_id`) USING BTREE,
  CONSTRAINT `fk_mst_product_category_classification` FOREIGN KEY (`classification_id`) REFERENCES `mst_product_classification` (`id`),
  CONSTRAINT `fk_mst_product_category_division` FOREIGN KEY (`product_division_id`) REFERENCES `mst_product_division` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_product_classification` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_division_id` bigint(20) unsigned NOT NULL,
  `code` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_product_classification_code` (`code`) USING BTREE,
  KEY `idx_mst_product_classification_division` (`product_division_id`) USING BTREE,
  CONSTRAINT `fk_mst_product_classification_division` FOREIGN KEY (`product_division_id`) REFERENCES `mst_product_division` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_product_division` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `pos_scope` enum('REGULAR','EVENT','ALL') NOT NULL DEFAULT 'REGULAR',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `default_operational_division_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_product_division_code` (`code`) USING BTREE,
  KEY `idx_mst_product_division_default_operational` (`default_operational_division_id`) USING BTREE,
  CONSTRAINT `fk_mst_product_division_default_operational` FOREIGN KEY (`default_operational_division_id`) REFERENCES `mst_operational_division` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_product_extra_map` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `extra_group_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_product_extra_map` (`extra_group_id`,`product_id`) USING BTREE,
  KEY `fk_mst_product_extra_map_product` (`product_id`) USING BTREE,
  CONSTRAINT `fk_mst_product_extra_map_group` FOREIGN KEY (`extra_group_id`) REFERENCES `mst_extra_group` (`id`),
  CONSTRAINT `fk_mst_product_extra_map_product` FOREIGN KEY (`product_id`) REFERENCES `mst_product` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_product_recipe` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `line_no` int(11) NOT NULL DEFAULT 1,
  `line_type` enum('MATERIAL','COMPONENT') NOT NULL,
  `ingredient_role` enum('MAIN','SUPPORT','GARNISH','OPTIONAL','SAUCE','TOPPING','OTHER') NOT NULL DEFAULT 'MAIN',
  `material_item_id` bigint(20) unsigned DEFAULT NULL,
  `component_id` bigint(20) unsigned DEFAULT NULL,
  `source_division_id` bigint(20) unsigned DEFAULT NULL,
  `qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `uom_id` bigint(20) unsigned NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_mst_product_recipe_product` (`product_id`) USING BTREE,
  KEY `idx_mst_product_recipe_material_item` (`material_item_id`) USING BTREE,
  KEY `idx_mst_product_recipe_component` (`component_id`) USING BTREE,
  KEY `idx_mst_product_recipe_source_division` (`source_division_id`) USING BTREE,
  KEY `fk_mst_product_recipe_uom` (`uom_id`) USING BTREE,
  CONSTRAINT `fk_mst_product_recipe_component` FOREIGN KEY (`component_id`) REFERENCES `mst_component` (`id`),
  CONSTRAINT `fk_mst_product_recipe_material_item` FOREIGN KEY (`material_item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_mst_product_recipe_product` FOREIGN KEY (`product_id`) REFERENCES `mst_product` (`id`),
  CONSTRAINT `fk_mst_product_recipe_source_division` FOREIGN KEY (`source_division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_mst_product_recipe_uom` FOREIGN KEY (`uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_purchase_catalog` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `profile_key` char(64) NOT NULL,
  `line_kind` enum('ITEM','MATERIAL','SERVICE','ASSET') DEFAULT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `catalog_name` varchar(150) NOT NULL,
  `brand_name` varchar(120) DEFAULT NULL,
  `line_description` varchar(255) DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned NOT NULL,
  `content_uom_id` bigint(20) unsigned DEFAULT NULL,
  `content_per_buy` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `conversion_factor_to_content` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `standard_price` decimal(18,2) DEFAULT NULL,
  `last_unit_price` decimal(18,2) DEFAULT NULL,
  `last_purchase_date` date DEFAULT NULL,
  `last_purchase_order_id` bigint(20) unsigned DEFAULT NULL,
  `last_purchase_line_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_purchase_catalog_profile_key` (`profile_key`) USING BTREE,
  KEY `idx_mst_purchase_catalog_item` (`item_id`) USING BTREE,
  KEY `idx_mst_purchase_catalog_material` (`material_id`) USING BTREE,
  KEY `idx_mst_purchase_catalog_buy_uom` (`buy_uom_id`) USING BTREE,
  KEY `idx_mst_purchase_catalog_content_uom` (`content_uom_id`) USING BTREE,
  KEY `idx_mst_purchase_catalog_active` (`is_active`) USING BTREE,
  KEY `fk_mst_purchase_catalog_last_po` (`last_purchase_order_id`) USING BTREE,
  KEY `fk_mst_purchase_catalog_last_line` (`last_purchase_line_id`) USING BTREE,
  CONSTRAINT `fk_mst_purchase_catalog_buy_uom` FOREIGN KEY (`buy_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_mst_purchase_catalog_content_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_mst_purchase_catalog_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_mst_purchase_catalog_last_line` FOREIGN KEY (`last_purchase_line_id`) REFERENCES `pur_purchase_order_line` (`id`),
  CONSTRAINT `fk_mst_purchase_catalog_last_po` FOREIGN KEY (`last_purchase_order_id`) REFERENCES `pur_purchase_order` (`id`),
  CONSTRAINT `fk_mst_purchase_catalog_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_purchase_catalog_vendor` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `catalog_id` bigint(20) unsigned NOT NULL,
  `vendor_id` bigint(20) unsigned NOT NULL,
  `standard_price` decimal(18,2) DEFAULT NULL,
  `last_unit_price` decimal(18,2) DEFAULT NULL,
  `last_purchase_date` date DEFAULT NULL,
  `last_purchase_order_id` bigint(20) unsigned DEFAULT NULL,
  `last_purchase_line_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_purchase_catalog_vendor_catalog_vendor` (`catalog_id`,`vendor_id`) USING BTREE,
  KEY `idx_mst_purchase_catalog_vendor_vendor` (`vendor_id`) USING BTREE,
  KEY `idx_mst_purchase_catalog_vendor_active` (`is_active`) USING BTREE,
  KEY `fk_mst_purchase_catalog_vendor_last_po` (`last_purchase_order_id`) USING BTREE,
  KEY `fk_mst_purchase_catalog_vendor_last_line` (`last_purchase_line_id`) USING BTREE,
  CONSTRAINT `fk_mst_purchase_catalog_vendor_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `mst_purchase_catalog` (`id`),
  CONSTRAINT `fk_mst_purchase_catalog_vendor_last_line` FOREIGN KEY (`last_purchase_line_id`) REFERENCES `pur_purchase_order_line` (`id`),
  CONSTRAINT `fk_mst_purchase_catalog_vendor_last_po` FOREIGN KEY (`last_purchase_order_id`) REFERENCES `pur_purchase_order` (`id`),
  CONSTRAINT `fk_mst_purchase_catalog_vendor_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `mst_vendor` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_purchase_type` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type_code` varchar(40) NOT NULL,
  `type_name` varchar(120) NOT NULL,
  `posting_type_id` bigint(20) unsigned NOT NULL,
  `destination_behavior` enum('REQUIRED','NONE') NOT NULL DEFAULT 'REQUIRED',
  `default_destination` enum('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_purchase_type_code` (`type_code`) USING BTREE,
  KEY `idx_mst_purchase_type_posting` (`posting_type_id`) USING BTREE,
  CONSTRAINT `fk_mst_purchase_type_posting` FOREIGN KEY (`posting_type_id`) REFERENCES `mst_posting_type` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_uom` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_uom_code` (`code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_uom_conversion` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `from_uom_id` bigint(20) unsigned NOT NULL,
  `to_uom_id` bigint(20) unsigned NOT NULL,
  `factor` decimal(18,6) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_uom_pair` (`from_uom_id`,`to_uom_id`) USING BTREE,
  KEY `fk_mst_uom_conv_to` (`to_uom_id`) USING BTREE,
  CONSTRAINT `fk_mst_uom_conv_from` FOREIGN KEY (`from_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_mst_uom_conv_to` FOREIGN KEY (`to_uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_variable_cost_default` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scope_code` enum('PRODUCT','COMPONENT') NOT NULL,
  `default_percent` decimal(10,4) NOT NULL DEFAULT 20.0000,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_mst_variable_cost_default_scope` (`scope_code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_vendor` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vendor_code` varchar(50) NOT NULL,
  `vendor_name` varchar(150) NOT NULL,
  `contact_name` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `tax_no` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `payment_terms` int(10) unsigned NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_vendor_code` (`vendor_code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mst_vendor_item` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vendor_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `vendor_sku` varchar(50) DEFAULT NULL,
  `last_price` decimal(18,2) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mst_vendor_item` (`vendor_id`,`item_id`) USING BTREE,
  KEY `fk_mst_vendor_item_item` (`item_id`) USING BTREE,
  CONSTRAINT `fk_mst_vendor_item_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_mst_vendor_item_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `mst_vendor` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_division` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `division_code` varchar(40) NOT NULL,
  `division_name` varchar(120) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_org_division_code` (`division_code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_employee` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_code` varchar(50) NOT NULL,
  `employee_nip` varchar(24) DEFAULT NULL,
  `employee_name` varchar(150) NOT NULL,
  `gender` enum('L','P') DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `join_date` date DEFAULT NULL,
  `mobile_phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `position_id` bigint(20) unsigned DEFAULT NULL,
  `employment_status` enum('PERMANENT','CONTRACT','PROBATION','DAILY','RESIGNED') NOT NULL DEFAULT 'CONTRACT',
  `bank_id` bigint(20) unsigned DEFAULT NULL,
  `bank_name` varchar(120) DEFAULT NULL,
  `bank_account_no` varchar(60) DEFAULT NULL,
  `bank_account_name` varchar(150) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_org_employee_code` (`employee_code`) USING BTREE,
  UNIQUE KEY `uk_org_employee_nip` (`employee_nip`) USING BTREE,
  KEY `idx_org_employee_division` (`division_id`) USING BTREE,
  KEY `idx_org_employee_position` (`position_id`) USING BTREE,
  KEY `idx_org_employee_status` (`employment_status`) USING BTREE,
  KEY `fk_org_employee_bank` (`bank_id`) USING BTREE,
  CONSTRAINT `fk_org_employee_bank` FOREIGN KEY (`bank_id`) REFERENCES `mst_bank` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_org_employee_division` FOREIGN KEY (`division_id`) REFERENCES `org_division` (`id`),
  CONSTRAINT `fk_org_employee_position` FOREIGN KEY (`position_id`) REFERENCES `org_position` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_employee_role_assignment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `assigned_by` bigint(20) unsigned DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_org_employee_role_assignment` (`employee_id`,`role_id`) USING BTREE,
  KEY `idx_org_employee_role_employee` (`employee_id`) USING BTREE,
  KEY `idx_org_employee_role_role` (`role_id`) USING BTREE,
  KEY `fk_org_employee_role_assigner` (`assigned_by`) USING BTREE,
  CONSTRAINT `fk_org_employee_role_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `auth_user` (`id`),
  CONSTRAINT `fk_org_employee_role_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_org_employee_role_role` FOREIGN KEY (`role_id`) REFERENCES `auth_role` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Role akses default per pegawai';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_position` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `division_id` bigint(20) unsigned NOT NULL,
  `position_code` varchar(40) NOT NULL,
  `position_name` varchar(120) NOT NULL,
  `default_role_id` bigint(20) unsigned DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_org_position_code` (`position_code`) USING BTREE,
  KEY `idx_org_position_division` (`division_id`) USING BTREE,
  CONSTRAINT `fk_org_position_division` FOREIGN KEY (`division_id`) REFERENCES `org_division` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_basic_salary_standard` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `standard_code` varchar(60) NOT NULL,
  `standard_name` varchar(120) NOT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `position_id` bigint(20) unsigned DEFAULT NULL,
  `employment_type` varchar(50) DEFAULT NULL,
  `effective_start` date NOT NULL,
  `effective_end` date DEFAULT NULL,
  `start_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `annual_increment` decimal(18,2) NOT NULL DEFAULT 0.00,
  `year_cap` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_basic_salary_standard_code` (`standard_code`) USING BTREE,
  KEY `idx_pay_basic_salary_standard_scope` (`position_id`,`division_id`,`employment_type`) USING BTREE,
  KEY `idx_pay_basic_salary_standard_effective` (`effective_start`,`effective_end`) USING BTREE,
  KEY `fk_pay_basic_salary_standard_division` (`division_id`) USING BTREE,
  CONSTRAINT `fk_pay_basic_salary_standard_division` FOREIGN KEY (`division_id`) REFERENCES `org_division` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_basic_salary_standard_position` FOREIGN KEY (`position_id`) REFERENCES `org_position` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_bonus_config` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `config_code` varchar(40) NOT NULL,
  `config_name` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `company_account_id` bigint(20) unsigned DEFAULT NULL,
  `distribution_scope` enum('GLOBAL','OUTLET','DIVISION') NOT NULL DEFAULT 'GLOBAL',
  `pool_source_mode` enum('FIXED','PERCENT_REVENUE','PERCENT_PROFIT','TARGET_LINKED','MANUAL') NOT NULL DEFAULT 'TARGET_LINKED',
  `pool_source_value` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `payout_percent` decimal(9,4) NOT NULL DEFAULT 100.0000,
  `point_penalty_currency_mode` enum('NONE','PERCENT_SHARE','FIXED_RUPIAH') NOT NULL DEFAULT 'PERCENT_SHARE',
  `point_penalty_currency_value` decimal(12,4) NOT NULL DEFAULT 5.0000,
  `linked_target_required` tinyint(1) NOT NULL DEFAULT 1,
  `include_shift_revenue_factor` tinyint(1) NOT NULL DEFAULT 1,
  `include_service_time_factor` tinyint(1) NOT NULL DEFAULT 1,
  `include_peer_review_factor` tinyint(1) NOT NULL DEFAULT 1,
  `include_attendance_factor` tinyint(1) NOT NULL DEFAULT 1,
  `include_manual_penalty_factor` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('DRAFT','ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_bonus_config_code` (`config_code`) USING BTREE,
  KEY `idx_pay_bonus_config_status` (`status`) USING BTREE,
  KEY `idx_pay_bonus_config_account` (`company_account_id`) USING BTREE,
  KEY `fk_pay_bonus_config_created_by` (`created_by`) USING BTREE,
  KEY `fk_pay_bonus_config_approved_by` (`approved_by`) USING BTREE,
  CONSTRAINT `fk_pay_bonus_config_account` FOREIGN KEY (`company_account_id`) REFERENCES `fin_company_account` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_config_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_config_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_bonus_employee_daily` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pool_id` bigint(20) unsigned NOT NULL,
  `pool_shift_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `attendance_date` date NOT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `attendance_status` varchar(30) DEFAULT NULL,
  `division_weight` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `position_weight` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `employee_weight` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `shift_weight` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `attendance_weight` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `target_weight` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `service_weight` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `peer_weight` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `revenue_in_shift` decimal(18,2) NOT NULL DEFAULT 0.00,
  `raw_point` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `raw_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `penalty_point` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `penalty_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `final_point` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `final_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `approval_status` enum('DRAFT','APPROVED','VOID') NOT NULL DEFAULT 'DRAFT',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_bonus_employee_daily_unique` (`pool_id`,`employee_id`,`shift_id`) USING BTREE,
  KEY `idx_pay_bonus_employee_daily_employee` (`employee_id`,`attendance_date`) USING BTREE,
  KEY `idx_pay_bonus_employee_daily_shift` (`shift_id`) USING BTREE,
  KEY `fk_pay_bonus_employee_daily_pool_shift` (`pool_shift_id`) USING BTREE,
  CONSTRAINT `fk_pay_bonus_employee_daily_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_bonus_employee_daily_pool` FOREIGN KEY (`pool_id`) REFERENCES `pay_bonus_pool_daily` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_bonus_employee_daily_pool_shift` FOREIGN KEY (`pool_shift_id`) REFERENCES `pay_bonus_pool_shift` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_employee_daily_shift` FOREIGN KEY (`shift_id`) REFERENCES `att_shift` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_bonus_employee_time_slice` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pool_id` bigint(20) unsigned NOT NULL,
  `pool_time_slice_id` bigint(20) unsigned NOT NULL,
  `employee_daily_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `attendance_date` date NOT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `slice_started_at` datetime NOT NULL,
  `slice_label` varchar(80) DEFAULT NULL,
  `active_employee_count` int(11) NOT NULL DEFAULT 0,
  `division_weight` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `position_weight` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `employee_weight` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `shift_weight` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `raw_point` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `raw_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `attributable_revenue` decimal(18,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pay_bonus_employee_time_slice_emp` (`employee_id`,`attendance_date`,`slice_started_at`) USING BTREE,
  KEY `idx_pay_bonus_employee_time_slice_daily` (`employee_daily_id`) USING BTREE,
  KEY `idx_pay_bonus_employee_time_slice_pool` (`pool_id`,`pool_time_slice_id`) USING BTREE,
  KEY `fk_pay_bonus_employee_time_slice_slice` (`pool_time_slice_id`) USING BTREE,
  KEY `fk_pay_bonus_employee_time_slice_shift` (`shift_id`) USING BTREE,
  CONSTRAINT `fk_pay_bonus_employee_time_slice_daily` FOREIGN KEY (`employee_daily_id`) REFERENCES `pay_bonus_employee_daily` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_employee_time_slice_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_bonus_employee_time_slice_pool` FOREIGN KEY (`pool_id`) REFERENCES `pay_bonus_pool_daily` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_bonus_employee_time_slice_shift` FOREIGN KEY (`shift_id`) REFERENCES `att_shift` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_employee_time_slice_slice` FOREIGN KEY (`pool_time_slice_id`) REFERENCES `pay_bonus_pool_time_slice` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_bonus_manual_adjustment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bonus_month` char(7) NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `adjustment_kind` enum('ADD','DEDUCT') NOT NULL DEFAULT 'ADD',
  `adjustment_basis` enum('POINT','AMOUNT') NOT NULL DEFAULT 'POINT',
  `adjustment_value` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `reason_text` varchar(255) NOT NULL,
  `source_type` enum('SUPERADMIN','PEER_REVIEW','AUDIT','OTHER') NOT NULL DEFAULT 'SUPERADMIN',
  `status` enum('DRAFT','APPROVED','VOID') NOT NULL DEFAULT 'APPROVED',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pay_bonus_manual_adjustment_month_employee` (`bonus_month`,`employee_id`) USING BTREE,
  KEY `fk_pay_bonus_manual_adjustment_employee` (`employee_id`) USING BTREE,
  KEY `fk_pay_bonus_manual_adjustment_created_by` (`created_by`) USING BTREE,
  KEY `fk_pay_bonus_manual_adjustment_approved_by` (`approved_by`) USING BTREE,
  CONSTRAINT `fk_pay_bonus_manual_adjustment_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_manual_adjustment_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_manual_adjustment_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_bonus_monthly_summary` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `summary_month` char(7) NOT NULL,
  `config_id` bigint(20) unsigned NOT NULL,
  `rule_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `outlet_id` bigint(20) unsigned DEFAULT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `total_raw_point` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_penalty_point` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_final_point` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_raw_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_penalty_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_final_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `ph_taken_count` int(11) NOT NULL DEFAULT 0,
  `late_count` int(11) NOT NULL DEFAULT 0,
  `alpha_count` int(11) NOT NULL DEFAULT 0,
  `peer_avg_star` decimal(5,2) NOT NULL DEFAULT 0.00,
  `service_avg_score` decimal(7,2) NOT NULL DEFAULT 0.00,
  `target_avg_score` decimal(7,2) NOT NULL DEFAULT 0.00,
  `payout_status` enum('DRAFT','APPROVED','POSTED','VOID') NOT NULL DEFAULT 'DRAFT',
  `posted_manual_adjustment_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_bonus_monthly_summary_unique` (`summary_month`,`config_id`,`employee_id`) USING BTREE,
  KEY `idx_pay_bonus_monthly_summary_scope` (`summary_month`,`outlet_id`,`division_id`) USING BTREE,
  KEY `idx_pay_bonus_monthly_summary_status` (`payout_status`) USING BTREE,
  KEY `fk_pay_bonus_monthly_summary_config` (`config_id`) USING BTREE,
  KEY `fk_pay_bonus_monthly_summary_rule` (`rule_id`) USING BTREE,
  KEY `fk_pay_bonus_monthly_summary_employee` (`employee_id`) USING BTREE,
  KEY `fk_pay_bonus_monthly_summary_outlet` (`outlet_id`) USING BTREE,
  KEY `fk_pay_bonus_monthly_summary_division` (`division_id`) USING BTREE,
  KEY `fk_pay_bonus_monthly_summary_manual_adj` (`posted_manual_adjustment_id`) USING BTREE,
  CONSTRAINT `fk_pay_bonus_monthly_summary_config` FOREIGN KEY (`config_id`) REFERENCES `pay_bonus_config` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_bonus_monthly_summary_division` FOREIGN KEY (`division_id`) REFERENCES `org_division` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_monthly_summary_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_bonus_monthly_summary_manual_adj` FOREIGN KEY (`posted_manual_adjustment_id`) REFERENCES `pay_manual_adjustment` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_monthly_summary_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_monthly_summary_rule` FOREIGN KEY (`rule_id`) REFERENCES `pay_bonus_rule` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_bonus_penalty_event` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `penalty_date` date NOT NULL,
  `rule_id` bigint(20) unsigned DEFAULT NULL,
  `penalty_type_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `penalty_scope` enum('PERSONAL','TEAM') NOT NULL DEFAULT 'PERSONAL',
  `source_type` enum('MANUAL','AUTO_ATTENDANCE','AUTO_SERVICE','AUTO_TARGET','AUTO_PEER') NOT NULL DEFAULT 'MANUAL',
  `points_deducted` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `amount_deducted` decimal(18,2) NOT NULL DEFAULT 0.00,
  `reason_text` varchar(255) DEFAULT NULL,
  `status` enum('DRAFT','APPROVED','REJECTED','VOID') NOT NULL DEFAULT 'APPROVED',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pay_bonus_penalty_event_date` (`penalty_date`,`status`) USING BTREE,
  KEY `idx_pay_bonus_penalty_event_employee` (`employee_id`) USING BTREE,
  KEY `idx_pay_bonus_penalty_event_division` (`division_id`) USING BTREE,
  KEY `idx_pay_bonus_penalty_event_shift` (`shift_id`) USING BTREE,
  KEY `fk_pay_bonus_penalty_event_rule` (`rule_id`) USING BTREE,
  KEY `fk_pay_bonus_penalty_event_type` (`penalty_type_id`) USING BTREE,
  KEY `fk_pay_bonus_penalty_event_created_by` (`created_by`) USING BTREE,
  KEY `fk_pay_bonus_penalty_event_approved_by` (`approved_by`) USING BTREE,
  CONSTRAINT `fk_pay_bonus_penalty_event_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_penalty_event_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_penalty_event_division` FOREIGN KEY (`division_id`) REFERENCES `org_division` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_penalty_event_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_penalty_event_rule` FOREIGN KEY (`rule_id`) REFERENCES `pay_bonus_rule` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_penalty_event_shift` FOREIGN KEY (`shift_id`) REFERENCES `att_shift` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_penalty_event_type` FOREIGN KEY (`penalty_type_id`) REFERENCES `pay_bonus_penalty_type` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_bonus_penalty_type` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `penalty_code` varchar(40) NOT NULL,
  `penalty_name` varchar(120) NOT NULL,
  `category` enum('ATTENDANCE','DISCIPLINE','PERFORMANCE','SERVICE','PROPERTY','SOCIAL_MEDIA','HYGIENE','OTHER') NOT NULL DEFAULT 'OTHER',
  `deduction_mode` enum('FIXED_POINT','FIXED_AMOUNT','VARIABLE') NOT NULL DEFAULT 'FIXED_POINT',
  `default_points_deducted` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `default_amount_deducted` decimal(18,2) NOT NULL DEFAULT 0.00,
  `service_target_minute` decimal(10,2) NOT NULL DEFAULT 15.00,
  `service_step_minute` decimal(10,2) NOT NULL DEFAULT 5.00,
  `peer_star_4_points` decimal(10,2) NOT NULL DEFAULT 1.00,
  `peer_star_3_points` decimal(10,2) NOT NULL DEFAULT 2.00,
  `peer_star_2_points` decimal(10,2) NOT NULL DEFAULT 3.00,
  `peer_star_1_points` decimal(10,2) NOT NULL DEFAULT 4.00,
  `applies_scope` enum('PERSONAL','TEAM','BOTH') NOT NULL DEFAULT 'BOTH',
  `is_manual_only` tinyint(1) NOT NULL DEFAULT 0,
  `behavior_mode` enum('AUTO','MANUAL','SEMI_MANUAL') NOT NULL DEFAULT 'MANUAL',
  `auto_source` enum('ATTENDANCE','SERVICE','TARGET','PEER','SOCIAL_MEDIA','AUDIT','CHECKLIST','OTHER') DEFAULT NULL,
  `attendance_trigger` varchar(60) DEFAULT NULL,
  `verification_cycle` enum('PER_EVENT','DAILY','MONTHLY','UNTIL_CHANGED') NOT NULL DEFAULT 'PER_EVENT',
  `approval_required` tinyint(1) NOT NULL DEFAULT 1,
  `requires_evidence` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_bonus_penalty_type_code` (`penalty_code`) USING BTREE,
  KEY `idx_pay_bonus_penalty_type_active` (`is_active`,`sort_order`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_bonus_pool_daily` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bonus_date` date NOT NULL,
  `config_id` bigint(20) unsigned NOT NULL,
  `rule_id` bigint(20) unsigned NOT NULL,
  `outlet_id` bigint(20) unsigned DEFAULT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `target_plan_id` bigint(20) unsigned DEFAULT NULL,
  `target_score_percent` decimal(7,2) NOT NULL DEFAULT 0.00,
  `target_gate_passed` tinyint(1) NOT NULL DEFAULT 0,
  `gross_sales_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `net_sales_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `refund_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `service_score_percent` decimal(7,2) NOT NULL DEFAULT 0.00,
  `pool_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `payout_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_employee_point` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_employee_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `approval_status` enum('DRAFT','APPROVED','VOID') NOT NULL DEFAULT 'DRAFT',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_bonus_pool_daily_unique` (`bonus_date`,`rule_id`,`outlet_id`,`division_id`) USING BTREE,
  KEY `idx_pay_bonus_pool_daily_scope` (`bonus_date`,`outlet_id`,`division_id`) USING BTREE,
  KEY `idx_pay_bonus_pool_daily_status` (`approval_status`) USING BTREE,
  KEY `fk_pay_bonus_pool_daily_config` (`config_id`) USING BTREE,
  KEY `fk_pay_bonus_pool_daily_rule` (`rule_id`) USING BTREE,
  KEY `fk_pay_bonus_pool_daily_outlet` (`outlet_id`) USING BTREE,
  KEY `fk_pay_bonus_pool_daily_division` (`division_id`) USING BTREE,
  KEY `fk_pay_bonus_pool_daily_target` (`target_plan_id`) USING BTREE,
  KEY `fk_pay_bonus_pool_daily_created_by` (`created_by`) USING BTREE,
  KEY `fk_pay_bonus_pool_daily_approved_by` (`approved_by`) USING BTREE,
  CONSTRAINT `fk_pay_bonus_pool_daily_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_pool_daily_config` FOREIGN KEY (`config_id`) REFERENCES `pay_bonus_config` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_bonus_pool_daily_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_pool_daily_division` FOREIGN KEY (`division_id`) REFERENCES `org_division` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_pool_daily_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_pool_daily_rule` FOREIGN KEY (`rule_id`) REFERENCES `pay_bonus_rule` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_bonus_pool_daily_target` FOREIGN KEY (`target_plan_id`) REFERENCES `fin_target_plan` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_bonus_pool_shift` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pool_id` bigint(20) unsigned NOT NULL,
  `shift_id` bigint(20) unsigned NOT NULL,
  `shift_start` time DEFAULT NULL,
  `shift_end` time DEFAULT NULL,
  `gross_sales_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `net_sales_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_orders` int(11) NOT NULL DEFAULT 0,
  `avg_service_minutes` decimal(10,2) NOT NULL DEFAULT 0.00,
  `service_score_percent` decimal(7,2) NOT NULL DEFAULT 0.00,
  `shift_point_weight` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `shift_pool_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `employee_count` int(11) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_bonus_pool_shift_unique` (`pool_id`,`shift_id`) USING BTREE,
  KEY `idx_pay_bonus_pool_shift_shift` (`shift_id`) USING BTREE,
  CONSTRAINT `fk_pay_bonus_pool_shift_header` FOREIGN KEY (`pool_id`) REFERENCES `pay_bonus_pool_daily` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_bonus_pool_shift_shift` FOREIGN KEY (`shift_id`) REFERENCES `att_shift` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_bonus_pool_time_slice` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pool_id` bigint(20) unsigned NOT NULL,
  `source_order_id` bigint(20) unsigned DEFAULT NULL,
  `source_shift_id` bigint(20) unsigned DEFAULT NULL,
  `slice_started_at` datetime NOT NULL,
  `slice_ended_at` datetime DEFAULT NULL,
  `slice_label` varchar(80) DEFAULT NULL,
  `gross_sales_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `net_sales_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `payout_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `active_employee_count` int(11) NOT NULL DEFAULT 0,
  `total_point_weight` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_bonus_pool_time_slice_order` (`pool_id`,`source_order_id`) USING BTREE,
  KEY `idx_pay_bonus_pool_time_slice_pool` (`pool_id`,`slice_started_at`) USING BTREE,
  KEY `idx_pay_bonus_pool_time_slice_shift` (`source_shift_id`,`slice_started_at`) USING BTREE,
  CONSTRAINT `fk_pay_bonus_pool_time_slice_pool` FOREIGN KEY (`pool_id`) REFERENCES `pay_bonus_pool_daily` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_bonus_rule` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `config_id` bigint(20) unsigned NOT NULL,
  `rule_code` varchar(40) NOT NULL,
  `rule_name` varchar(120) NOT NULL,
  `outlet_id` bigint(20) unsigned DEFAULT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `linked_target_plan_id` bigint(20) unsigned DEFAULT NULL,
  `daily_target_plan_id` bigint(20) unsigned DEFAULT NULL,
  `active_start_date` date DEFAULT NULL,
  `active_end_date` date DEFAULT NULL,
  `threshold_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `pool_formula_type` enum('PERCENTAGE','FIXED_STEP') NOT NULL DEFAULT 'PERCENTAGE',
  `pool_formula_value` decimal(12,4) NOT NULL DEFAULT 3.0000,
  `min_shift_base_pct` decimal(9,2) NOT NULL DEFAULT 30.00,
  `min_target_score` decimal(7,2) NOT NULL DEFAULT 100.00,
  `target_gate_mode` enum('NONE','ALL_REQUIRED','WEIGHTED_SCORE') NOT NULL DEFAULT 'WEIGHTED_SCORE',
  `ph_bonus_mode` enum('ALLOW','EXCLUDE','REDUCE') NOT NULL DEFAULT 'EXCLUDE',
  `ph_point_deduction` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `holiday_bonus_mode` enum('IGNORE','NEUTRAL') NOT NULL DEFAULT 'IGNORE',
  `late_penalty_mode` enum('NONE','REDUCE_POINT','REDUCE_AMOUNT') NOT NULL DEFAULT 'REDUCE_POINT',
  `late_penalty_value` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `alpha_penalty_value` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `service_time_target_minute` decimal(10,2) NOT NULL DEFAULT 0.00,
  `service_time_weight` decimal(9,4) NOT NULL DEFAULT 0.0000,
  `shift_revenue_weight` decimal(9,4) NOT NULL DEFAULT 1.0000,
  `peer_review_weight` decimal(9,4) NOT NULL DEFAULT 0.0000,
  `attendance_weight` decimal(9,4) NOT NULL DEFAULT 1.0000,
  `manual_penalty_weight` decimal(9,4) NOT NULL DEFAULT 1.0000,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_bonus_rule_code` (`rule_code`) USING BTREE,
  KEY `idx_pay_bonus_rule_config` (`config_id`) USING BTREE,
  KEY `idx_pay_bonus_rule_outlet` (`outlet_id`) USING BTREE,
  KEY `idx_pay_bonus_rule_division` (`division_id`) USING BTREE,
  KEY `idx_pay_bonus_rule_target` (`linked_target_plan_id`) USING BTREE,
  KEY `fk_pay_bonus_rule_created_by` (`created_by`) USING BTREE,
  KEY `fk_pay_bonus_rule_approved_by` (`approved_by`) USING BTREE,
  KEY `idx_pay_bonus_rule_daily_target_plan` (`daily_target_plan_id`) USING BTREE,
  CONSTRAINT `fk_pay_bonus_rule_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_rule_config` FOREIGN KEY (`config_id`) REFERENCES `pay_bonus_config` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_bonus_rule_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_rule_daily_target_plan` FOREIGN KEY (`daily_target_plan_id`) REFERENCES `fin_target_plan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pay_bonus_rule_division` FOREIGN KEY (`division_id`) REFERENCES `org_division` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_rule_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_rule_target` FOREIGN KEY (`linked_target_plan_id`) REFERENCES `fin_target_plan` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_bonus_service_metric_daily` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `metric_date` date NOT NULL,
  `outlet_id` bigint(20) unsigned DEFAULT NULL,
  `division_id` bigint(20) unsigned DEFAULT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `total_orders` int(11) NOT NULL DEFAULT 0,
  `served_orders` int(11) NOT NULL DEFAULT 0,
  `ontime_orders` int(11) NOT NULL DEFAULT 0,
  `late_orders` int(11) NOT NULL DEFAULT 0,
  `avg_service_minutes` decimal(10,2) NOT NULL DEFAULT 0.00,
  `score_percent` decimal(7,2) NOT NULL DEFAULT 0.00,
  `source_notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_bonus_service_metric_daily` (`metric_date`,`outlet_id`,`division_id`,`shift_id`) USING BTREE,
  KEY `idx_pay_bonus_service_metric_scope` (`metric_date`,`outlet_id`,`division_id`) USING BTREE,
  KEY `fk_pay_bonus_service_metric_outlet` (`outlet_id`) USING BTREE,
  KEY `fk_pay_bonus_service_metric_division` (`division_id`) USING BTREE,
  KEY `fk_pay_bonus_service_metric_shift` (`shift_id`) USING BTREE,
  CONSTRAINT `fk_pay_bonus_service_metric_division` FOREIGN KEY (`division_id`) REFERENCES `org_division` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_service_metric_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_bonus_service_metric_shift` FOREIGN KEY (`shift_id`) REFERENCES `att_shift` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_bonus_weight_rule` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rule_id` bigint(20) unsigned DEFAULT NULL,
  `weight_scope` enum('DIVISION','POSITION','EMPLOYEE','SHIFT') NOT NULL,
  `scope_id` bigint(20) unsigned NOT NULL,
  `target_frequency` enum('ALL','DAILY','MONTHLY') NOT NULL DEFAULT 'ALL',
  `point_weight` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `pool_weight` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_bonus_weight_rule_unique` (`rule_id`,`weight_scope`,`scope_id`) USING BTREE,
  KEY `idx_pay_bonus_weight_rule_scope` (`weight_scope`,`scope_id`) USING BTREE,
  KEY `idx_pay_bonus_weight_rule_target_frequency` (`target_frequency`,`weight_scope`,`scope_id`) USING BTREE,
  CONSTRAINT `fk_pay_bonus_weight_rule_header` FOREIGN KEY (`rule_id`) REFERENCES `pay_bonus_rule` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_cash_advance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `advance_no` varchar(60) NOT NULL,
  `request_date` date NOT NULL,
  `approved_date` date DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `company_account_id` bigint(20) unsigned DEFAULT NULL,
  `tenor_month` int(10) unsigned NOT NULL DEFAULT 1,
  `monthly_deduction_plan` decimal(18,2) NOT NULL DEFAULT 0.00,
  `outstanding_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('DRAFT','APPROVED','REJECTED','SETTLED','VOID') NOT NULL DEFAULT 'DRAFT',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_cash_advance_no` (`advance_no`) USING BTREE,
  KEY `idx_pay_cash_advance_employee` (`employee_id`) USING BTREE,
  KEY `fk_pay_cash_advance_account` (`company_account_id`) USING BTREE,
  CONSTRAINT `fk_pay_cash_advance_account` FOREIGN KEY (`company_account_id`) REFERENCES `fin_company_account` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_cash_advance_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_cash_advance_installment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cash_advance_id` bigint(20) unsigned NOT NULL,
  `installment_no` int(10) unsigned NOT NULL,
  `due_period` char(7) NOT NULL,
  `plan_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('OPEN','PARTIAL','PAID') NOT NULL DEFAULT 'OPEN',
  `payment_method` enum('CASH','TRANSFER','SALARY_CUT') NOT NULL DEFAULT 'CASH',
  `company_account_id` bigint(20) unsigned DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `salary_cut_period` char(7) DEFAULT NULL,
  `salary_cut_date` date DEFAULT NULL,
  `transfer_ref_no` varchar(100) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_cash_advance_inst_unique` (`cash_advance_id`,`installment_no`) USING BTREE,
  KEY `idx_pay_cash_advance_inst_period` (`due_period`) USING BTREE,
  KEY `fk_pay_cash_advance_inst_account` (`company_account_id`) USING BTREE,
  CONSTRAINT `fk_pay_cash_advance_inst_account` FOREIGN KEY (`company_account_id`) REFERENCES `fin_company_account` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_cash_advance_inst_parent` FOREIGN KEY (`cash_advance_id`) REFERENCES `pay_cash_advance` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_manual_adjustment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `adjustment_date` date NOT NULL,
  `adjustment_kind` enum('ADDITION','DEDUCTION') NOT NULL DEFAULT 'ADDITION',
  `adjustment_name` varchar(120) NOT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'APPROVED',
  `notes` varchar(255) DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pay_manual_adj_employee_date` (`employee_id`,`adjustment_date`) USING BTREE,
  KEY `idx_pay_manual_adj_kind_status` (`adjustment_kind`,`status`) USING BTREE,
  KEY `idx_pay_manual_adj_date` (`adjustment_date`) USING BTREE,
  KEY `fk_pay_manual_adj_approved_by` (`approved_by`) USING BTREE,
  KEY `fk_pay_manual_adj_created_by` (`created_by`) USING BTREE,
  CONSTRAINT `fk_pay_manual_adj_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_manual_adj_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_manual_adj_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_meal_disbursement` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `disbursement_no` varchar(60) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `disbursement_date` date NOT NULL,
  `company_account_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('DRAFT','POSTED','PAID','VOID') NOT NULL DEFAULT 'DRAFT',
  `total_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_meal_disbursement_no` (`disbursement_no`) USING BTREE,
  KEY `idx_pay_meal_disbursement_period` (`period_start`,`period_end`) USING BTREE,
  KEY `idx_pay_meal_disbursement_status` (`status`) USING BTREE,
  KEY `fk_pay_meal_disbursement_created_by` (`created_by`) USING BTREE,
  CONSTRAINT `fk_pay_meal_disbursement_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_meal_disbursement_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `disbursement_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `attendance_date` date NOT NULL,
  `att_daily_id` bigint(20) unsigned DEFAULT NULL,
  `meal_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `transfer_status` enum('PENDING','PAID','FAILED','VOID') NOT NULL DEFAULT 'PENDING',
  `transfer_ref_no` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_meal_line_employee_date` (`employee_id`,`attendance_date`) USING BTREE,
  UNIQUE KEY `uk_pay_meal_line_disbursement_date` (`disbursement_id`,`employee_id`,`attendance_date`) USING BTREE,
  KEY `idx_pay_meal_line_disbursement` (`disbursement_id`) USING BTREE,
  KEY `idx_pay_meal_line_att_daily` (`att_daily_id`) USING BTREE,
  CONSTRAINT `fk_pay_meal_line_att_daily` FOREIGN KEY (`att_daily_id`) REFERENCES `att_daily` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_meal_line_disbursement` FOREIGN KEY (`disbursement_id`) REFERENCES `pay_meal_disbursement` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_meal_line_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_objective_override` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `override_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `effective_start` date NOT NULL,
  `effective_end` date DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pay_objective_override_employee_effective` (`employee_id`,`effective_start`,`effective_end`) USING BTREE,
  KEY `idx_pay_objective_override_active` (`is_active`) USING BTREE,
  KEY `fk_pay_objective_override_created_by` (`created_by`) USING BTREE,
  CONSTRAINT `fk_pay_objective_override_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_objective_override_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_payroll_period` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `period_code` char(7) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `rounding_mode` enum('NONE','UP_1000') NOT NULL DEFAULT 'NONE',
  `status` enum('DRAFT','CALCULATED','FINALIZED','PAID','CLOSED') NOT NULL DEFAULT 'DRAFT',
  `finalized_at` datetime DEFAULT NULL,
  `finalized_by` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_payroll_period_code` (`period_code`) USING BTREE,
  KEY `fk_pay_payroll_period_finalizer` (`finalized_by`) USING BTREE,
  CONSTRAINT `fk_pay_payroll_period_finalizer` FOREIGN KEY (`finalized_by`) REFERENCES `org_employee` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_payroll_result` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payroll_period_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `employee_code_snapshot` varchar(50) NOT NULL,
  `employee_name_snapshot` varchar(150) NOT NULL,
  `work_days` decimal(10,2) NOT NULL DEFAULT 0.00,
  `present_days` decimal(10,2) NOT NULL DEFAULT 0.00,
  `alpha_days` decimal(10,2) NOT NULL DEFAULT 0.00,
  `late_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `overtime_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `basic_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `allowance_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `meal_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `overtime_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `manual_addition_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `late_deduction_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `alpha_deduction_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `manual_deduction_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `cash_advance_cut_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `gross_pay` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_deduction` decimal(18,2) NOT NULL DEFAULT 0.00,
  `net_pay_raw` decimal(18,2) NOT NULL DEFAULT 0.00,
  `rounding_adjustment` decimal(18,2) NOT NULL DEFAULT 0.00,
  `net_pay` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('DRAFT','FINALIZED','PAID') NOT NULL DEFAULT 'DRAFT',
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_payroll_result_unique` (`payroll_period_id`,`employee_id`) USING BTREE,
  KEY `idx_pay_payroll_result_status` (`status`) USING BTREE,
  KEY `fk_pay_payroll_result_employee` (`employee_id`) USING BTREE,
  CONSTRAINT `fk_pay_payroll_result_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_pay_payroll_result_period` FOREIGN KEY (`payroll_period_id`) REFERENCES `pay_payroll_period` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_payroll_result_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payroll_result_id` bigint(20) unsigned NOT NULL,
  `component_id` bigint(20) unsigned DEFAULT NULL,
  `line_code` varchar(60) NOT NULL,
  `line_name` varchar(150) NOT NULL,
  `line_type` enum('EARNING','DEDUCTION') NOT NULL,
  `qty` decimal(18,4) NOT NULL DEFAULT 1.0000,
  `rate` decimal(18,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pay_payroll_result_line_result` (`payroll_result_id`) USING BTREE,
  KEY `idx_pay_payroll_result_line_component` (`component_id`) USING BTREE,
  CONSTRAINT `fk_pay_payroll_result_line_component` FOREIGN KEY (`component_id`) REFERENCES `pay_salary_component` (`id`),
  CONSTRAINT `fk_pay_payroll_result_line_result` FOREIGN KEY (`payroll_result_id`) REFERENCES `pay_payroll_result` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_salary_assignment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `profile_id` bigint(20) unsigned NOT NULL,
  `effective_start` date NOT NULL,
  `effective_end` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pay_salary_assignment_employee` (`employee_id`) USING BTREE,
  KEY `idx_pay_salary_assignment_profile` (`profile_id`) USING BTREE,
  KEY `idx_pay_salary_assignment_effective` (`effective_start`,`effective_end`) USING BTREE,
  CONSTRAINT `fk_pay_salary_assignment_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_pay_salary_assignment_profile` FOREIGN KEY (`profile_id`) REFERENCES `pay_salary_profile` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_salary_component` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `component_code` varchar(50) NOT NULL,
  `component_name` varchar(150) NOT NULL,
  `component_type` enum('EARNING','DEDUCTION') NOT NULL,
  `calc_method` enum('FIXED','PER_DAY','PER_HOUR','PER_MINUTE','FORMULA') NOT NULL DEFAULT 'FIXED',
  `default_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `affects_attendance` tinyint(1) NOT NULL DEFAULT 0,
  `affects_bpjs_base` tinyint(1) NOT NULL DEFAULT 0,
  `is_taxable` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_salary_component_code` (`component_code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_salary_disbursement` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payroll_period_id` bigint(20) unsigned NOT NULL,
  `disbursement_no` varchar(60) NOT NULL,
  `disbursement_date` date NOT NULL,
  `company_account_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('DRAFT','POSTED','PAID','VOID') NOT NULL DEFAULT 'DRAFT',
  `total_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_salary_disbursement_no` (`disbursement_no`) USING BTREE,
  KEY `idx_pay_salary_disbursement_period` (`payroll_period_id`) USING BTREE,
  KEY `idx_pay_salary_disbursement_company_account` (`company_account_id`) USING BTREE,
  KEY `fk_pay_salary_disbursement_created_by` (`created_by`) USING BTREE,
  CONSTRAINT `fk_pay_salary_disbursement_created_by` FOREIGN KEY (`created_by`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_pay_salary_disbursement_period` FOREIGN KEY (`payroll_period_id`) REFERENCES `pay_payroll_period` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_salary_disbursement_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `disbursement_id` bigint(20) unsigned NOT NULL,
  `payroll_result_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `company_account_id` bigint(20) unsigned DEFAULT NULL,
  `basic_total_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `allowance_total_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `meal_total_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `overtime_total_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `manual_addition_total_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `late_deduction_total_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `alpha_deduction_total_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `manual_deduction_total_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `cash_advance_cut_total_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `gross_pay_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_deduction_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `net_pay_raw_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `rounding_adjustment_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `net_pay_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `employee_bank_name` varchar(120) DEFAULT NULL,
  `employee_bank_account_no` varchar(60) DEFAULT NULL,
  `employee_bank_account_name` varchar(150) DEFAULT NULL,
  `transfer_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `transfer_status` enum('PENDING','PAID','FAILED','VOID') NOT NULL DEFAULT 'PENDING',
  `transfer_ref_no` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_salary_disb_line_unique` (`disbursement_id`,`payroll_result_id`) USING BTREE,
  KEY `idx_pay_salary_disb_line_employee` (`employee_id`) USING BTREE,
  KEY `fk_pay_salary_disb_line_result` (`payroll_result_id`) USING BTREE,
  KEY `idx_pay_salary_disb_line_company_account` (`company_account_id`) USING BTREE,
  CONSTRAINT `fk_pay_salary_disb_line_company_account` FOREIGN KEY (`company_account_id`) REFERENCES `fin_company_account` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_salary_disb_line_disbursement` FOREIGN KEY (`disbursement_id`) REFERENCES `pay_salary_disbursement` (`id`),
  CONSTRAINT `fk_pay_salary_disb_line_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_pay_salary_disb_line_result` FOREIGN KEY (`payroll_result_id`) REFERENCES `pay_payroll_result` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_salary_profile` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `profile_code` varchar(50) NOT NULL,
  `profile_name` varchar(150) NOT NULL,
  `effective_start` date DEFAULT NULL,
  `effective_end` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_salary_profile_code` (`profile_code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_salary_profile_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` bigint(20) unsigned NOT NULL,
  `component_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `formula_expr` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pay_salary_profile_line_unique` (`profile_id`,`component_id`) USING BTREE,
  KEY `fk_pay_salary_profile_line_component` (`component_id`) USING BTREE,
  CONSTRAINT `fk_pay_salary_profile_line_component` FOREIGN KEY (`component_id`) REFERENCES `pay_salary_component` (`id`),
  CONSTRAINT `fk_pay_salary_profile_line_profile` FOREIGN KEY (`profile_id`) REFERENCES `pay_salary_profile` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `perf_peer_feedback` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `feedback_date` date NOT NULL,
  `from_employee_id` bigint(20) unsigned NOT NULL,
  `to_employee_id` bigint(20) unsigned NOT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `star_rating` tinyint(3) unsigned NOT NULL,
  `reason_text` varchar(255) DEFAULT NULL,
  `status` enum('SUBMITTED','APPROVED','REJECTED','VOID') NOT NULL DEFAULT 'SUBMITTED',
  `moderator_id` bigint(20) unsigned DEFAULT NULL,
  `moderation_notes` varchar(255) DEFAULT NULL,
  `bonus_adjustment_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_perf_peer_feedback_unique` (`feedback_date`,`from_employee_id`,`to_employee_id`) USING BTREE,
  KEY `idx_perf_peer_feedback_to` (`to_employee_id`,`feedback_date`,`status`) USING BTREE,
  KEY `idx_perf_peer_feedback_from` (`from_employee_id`,`feedback_date`) USING BTREE,
  KEY `fk_perf_peer_feedback_shift` (`shift_id`) USING BTREE,
  KEY `fk_perf_peer_feedback_moderator` (`moderator_id`) USING BTREE,
  KEY `fk_perf_peer_feedback_bonus_adjustment` (`bonus_adjustment_id`) USING BTREE,
  CONSTRAINT `fk_perf_peer_feedback_bonus_adjustment` FOREIGN KEY (`bonus_adjustment_id`) REFERENCES `pay_bonus_manual_adjustment` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_perf_peer_feedback_from_employee` FOREIGN KEY (`from_employee_id`) REFERENCES `org_employee` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_perf_peer_feedback_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_perf_peer_feedback_shift` FOREIGN KEY (`shift_id`) REFERENCES `att_shift` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_perf_peer_feedback_to_employee` FOREIGN KEY (`to_employee_id`) REFERENCES `org_employee` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_cashier_session` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_key` varchar(120) NOT NULL,
  `outlet_id` bigint(20) unsigned NOT NULL,
  `terminal_id` bigint(20) unsigned NOT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `session_status` enum('OPEN','LOCKED','CLOSED') NOT NULL DEFAULT 'OPEN',
  `login_at` datetime NOT NULL,
  `logout_at` datetime DEFAULT NULL,
  `last_ping_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_cashier_session_key` (`session_key`) USING BTREE,
  KEY `idx_pos_cashier_session_terminal_status` (`terminal_id`,`session_status`) USING BTREE,
  KEY `idx_pos_cashier_session_shift` (`shift_id`) USING BTREE,
  KEY `idx_pos_cashier_session_employee` (`employee_id`) USING BTREE,
  KEY `fk_pos_cashier_session_outlet` (`outlet_id`) USING BTREE,
  CONSTRAINT `fk_pos_cashier_session_employee` FOREIGN KEY (`employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_pos_cashier_session_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`),
  CONSTRAINT `fk_pos_cashier_session_shift` FOREIGN KEY (`shift_id`) REFERENCES `pos_shift` (`id`),
  CONSTRAINT `fk_pos_cashier_session_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `pos_terminal` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_customer_review` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `review_token` char(64) NOT NULL,
  `review_source` enum('RECEIPT','STATION') NOT NULL DEFAULT 'RECEIPT',
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `outlet_id` bigint(20) unsigned DEFAULT NULL,
  `station_id` bigint(20) unsigned DEFAULT NULL,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `order_no_snapshot` varchar(60) DEFAULT NULL,
  `customer_name_snapshot` varchar(150) DEFAULT NULL,
  `visitor_phone_snapshot` varchar(30) DEFAULT NULL,
  `rating` tinyint(3) unsigned DEFAULT NULL,
  `review_text` text DEFAULT NULL,
  `review_status` enum('OPEN','SUBMITTED','HIDDEN') NOT NULL DEFAULT 'OPEN',
  `submitted_at` datetime DEFAULT NULL,
  `hidden_by` bigint(20) unsigned DEFAULT NULL,
  `hidden_at` datetime DEFAULT NULL,
  `hidden_reason` varchar(255) DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_customer_review_token` (`review_token`) USING BTREE,
  UNIQUE KEY `uk_pos_customer_review_order` (`order_id`) USING BTREE,
  KEY `idx_pos_customer_review_status_date` (`review_status`,`submitted_at`) USING BTREE,
  KEY `idx_pos_customer_review_outlet_date` (`outlet_id`,`submitted_at`) USING BTREE,
  KEY `fk_pos_customer_review_member` (`member_id`) USING BTREE,
  KEY `fk_pos_customer_review_hidden_by` (`hidden_by`) USING BTREE,
  KEY `idx_pos_customer_review_source_date` (`review_source`,`submitted_at`) USING BTREE,
  KEY `idx_pos_customer_review_station_date` (`station_id`,`submitted_at`) USING BTREE,
  CONSTRAINT `fk_pos_customer_review_hidden_by` FOREIGN KEY (`hidden_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_customer_review_member` FOREIGN KEY (`member_id`) REFERENCES `crm_member` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_customer_review_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`),
  CONSTRAINT `fk_pos_customer_review_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ulasan pelanggan dari QR unik pada struk pembayaran POS.';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_customer_review_station` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `station_code` varchar(60) NOT NULL,
  `station_name` varchar(150) NOT NULL,
  `outlet_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_customer_review_station_code` (`station_code`) USING BTREE,
  KEY `idx_pos_customer_review_station_active` (`is_active`,`outlet_id`,`station_name`) USING BTREE,
  KEY `fk_pos_customer_review_station_outlet` (`outlet_id`) USING BTREE,
  CONSTRAINT `fk_pos_customer_review_station_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='QR ulasan umum yang dapat ditempel di area outlet.';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_mobile_auth_token` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `token_hash` char(64) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `terminal_device_key` varchar(120) DEFAULT NULL,
  `device_label` varchar(160) DEFAULT NULL,
  `issued_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `last_seen_at` datetime DEFAULT NULL,
  `step_up_failure_window_at` datetime DEFAULT NULL,
  `step_up_failure_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `step_up_locked_until` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_pos_mobile_auth_token_hash` (`token_hash`) USING BTREE,
  KEY `idx_pos_mobile_auth_token_user` (`user_id`) USING BTREE,
  KEY `idx_pos_mobile_auth_token_employee` (`employee_id`) USING BTREE,
  KEY `idx_pos_mobile_auth_token_device` (`terminal_device_key`) USING BTREE,
  KEY `idx_pos_mobile_auth_token_expires` (`expires_at`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_mobile_sensitive_action_proof` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `proof_hash` char(64) NOT NULL,
  `mobile_token_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `terminal_id` bigint(20) unsigned NOT NULL,
  `action` enum('VOID','REFUND','ORDER_REPRINT','CASHIER_CLOSE','RESERVATION_DEPOSIT_REFUND') NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `cashier_session_id` bigint(20) unsigned DEFAULT NULL,
  `reservation_id` bigint(20) unsigned DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_pos_mobile_sensitive_action_proof_hash` (`proof_hash`) USING BTREE,
  KEY `idx_pos_mobile_sensitive_action_proof_consume` (`mobile_token_id`,`user_id`,`terminal_id`,`action`,`order_id`,`expires_at`,`consumed_at`) USING BTREE,
  KEY `idx_pos_mobile_sensitive_action_proof_expiry` (`expires_at`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One-use reauthentication proofs for POS Mobile sensitive actions.';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_mobile_sync_event` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_event_id` varchar(80) NOT NULL,
  `local_uuid` varchar(80) NOT NULL,
  `event_type` varchar(40) NOT NULL,
  `event_status` varchar(24) NOT NULL DEFAULT 'PENDING',
  `server_order_id` bigint(20) unsigned DEFAULT NULL,
  `request_json` longtext DEFAULT NULL,
  `response_json` longtext DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `requested_at` datetime DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_pos_mobile_sync_event_client_event` (`client_event_id`) USING BTREE,
  KEY `idx_pos_mobile_sync_event_local_uuid` (`local_uuid`) USING BTREE,
  KEY `idx_pos_mobile_sync_event_status` (`event_status`) USING BTREE,
  KEY `idx_pos_mobile_sync_event_server_order` (`server_order_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_online_food_delivery_order` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `saved_location_id` bigint(20) unsigned DEFAULT NULL,
  `delivery_status` enum('PENDING','ASSIGNED','PICKED_UP','DELIVERED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `delivery_provider` enum('OJEK_ONLINE','INTERNAL','OTHER') NOT NULL DEFAULT 'OJEK_ONLINE',
  `fee_charge_mode` enum('CUSTOMER_TO_DRIVER','RECORD_ONLY','MERCHANT_COLLECT') NOT NULL DEFAULT 'CUSTOMER_TO_DRIVER',
  `fee_paid_by` enum('CUSTOMER','MERCHANT','FREE') NOT NULL DEFAULT 'CUSTOMER',
  `fee_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `estimated_fee_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `distance_km` decimal(10,3) DEFAULT NULL,
  `straight_distance_km` decimal(10,3) DEFAULT NULL,
  `route_distance_km` decimal(10,3) DEFAULT NULL,
  `duration_min` decimal(10,2) DEFAULT NULL,
  `route_source` varchar(30) DEFAULT NULL,
  `recipient_name` varchar(150) DEFAULT NULL,
  `recipient_phone` varchar(32) DEFAULT NULL,
  `delivery_address` varchar(255) DEFAULT NULL,
  `address_note` varchar(255) DEFAULT NULL,
  `customer_lat` decimal(10,7) DEFAULT NULL,
  `customer_lng` decimal(10,7) DEFAULT NULL,
  `customer_location_accuracy` decimal(10,2) DEFAULT NULL,
  `free_reason` varchar(120) DEFAULT NULL,
  `courier_ref` varchar(80) DEFAULT NULL,
  `courier_notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_online_food_delivery_order` (`order_id`) USING BTREE,
  KEY `idx_pos_online_food_delivery_order_member` (`member_id`) USING BTREE,
  KEY `idx_pos_online_food_delivery_order_location` (`customer_lat`,`customer_lng`) USING BTREE,
  KEY `idx_pos_online_food_delivery_order_saved_location` (`saved_location_id`) USING BTREE,
  CONSTRAINT `fk_pos_online_food_delivery_order_member` FOREIGN KEY (`member_id`) REFERENCES `crm_member` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_online_food_delivery_order_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pos_online_food_delivery_order_saved_location` FOREIGN KEY (`saved_location_id`) REFERENCES `crm_member_delivery_location` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_online_food_payment_method` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `setting_id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `payment_method_id` bigint(20) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_online_food_payment_method` (`setting_id`,`payment_method_id`) USING BTREE,
  KEY `idx_pos_online_food_payment_method_active` (`is_active`) USING BTREE,
  KEY `fk_pos_online_food_payment_method_method` (`payment_method_id`) USING BTREE,
  CONSTRAINT `fk_pos_online_food_payment_method_method` FOREIGN KEY (`payment_method_id`) REFERENCES `pos_payment_method` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pos_online_food_payment_method_setting` FOREIGN KEY (`setting_id`) REFERENCES `pos_online_food_setting` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_online_food_setting` (
  `id` tinyint(3) unsigned NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `open_mode` enum('MANUAL','SCHEDULE') NOT NULL DEFAULT 'MANUAL',
  `manual_status` enum('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN',
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Jakarta',
  `open_time` time DEFAULT '08:00:00',
  `close_time` time DEFAULT '22:00:00',
  `schedule_days` varchar(32) NOT NULL DEFAULT '1,2,3,4,5,6,0',
  `allow_cod` tinyint(1) NOT NULL DEFAULT 1,
  `allow_qris` tinyint(1) NOT NULL DEFAULT 0,
  `payment_default` enum('AUTO','MANUAL') NOT NULL DEFAULT 'MANUAL',
  `payment_auto_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `payment_manual_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `auto_payment_provider` enum('MIDTRANS') NOT NULL DEFAULT 'MIDTRANS',
  `midtrans_server_key` varchar(255) DEFAULT NULL,
  `midtrans_client_key` varchar(255) DEFAULT NULL,
  `midtrans_is_production` tinyint(1) NOT NULL DEFAULT 0,
  `qris_payment_method_id` bigint(20) unsigned DEFAULT NULL,
  `delivery_fee_mode` enum('FLAT','DISTANCE') NOT NULL DEFAULT 'DISTANCE',
  `delivery_fee_charge_mode` enum('CUSTOMER_TO_DRIVER','RECORD_ONLY','MERCHANT_COLLECT') NOT NULL DEFAULT 'CUSTOMER_TO_DRIVER',
  `delivery_flat_fee` decimal(18,2) NOT NULL DEFAULT 0.00,
  `delivery_base_fee` decimal(18,2) NOT NULL DEFAULT 5000.00,
  `delivery_base_km` decimal(10,2) NOT NULL DEFAULT 2.00,
  `delivery_per_km_fee` decimal(18,2) NOT NULL DEFAULT 2500.00,
  `delivery_min_fee` decimal(18,2) NOT NULL DEFAULT 5000.00,
  `delivery_max_distance_km` decimal(10,2) NOT NULL DEFAULT 10.00,
  `free_delivery_min_order` decimal(18,2) NOT NULL DEFAULT 0.00,
  `free_delivery_distance_km` decimal(10,2) NOT NULL DEFAULT 0.00,
  `packaging_fee_default` decimal(18,2) NOT NULL DEFAULT 0.00,
  `min_order_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `manual_whatsapp_number` varchar(32) DEFAULT NULL,
  `manual_whatsapp_template` varchar(255) DEFAULT NULL,
  `manual_payment_instructions` text DEFAULT NULL,
  `outlet_lat` decimal(10,7) DEFAULT NULL,
  `outlet_lng` decimal(10,7) DEFAULT NULL,
  `member_base_url` varchar(255) NOT NULL DEFAULT 'http://localhost/member/',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pos_online_food_setting_qris` (`qris_payment_method_id`) USING BTREE,
  CONSTRAINT `fk_pos_online_food_setting_qris_method` FOREIGN KEY (`qris_payment_method_id`) REFERENCES `pos_payment_method` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_order` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(40) NOT NULL,
  `order_channel` enum('CASHIER','SELF_ORDER','RESERVATION','DELIVERY') NOT NULL DEFAULT 'CASHIER',
  `order_scope` enum('REGULAR','EVENT') NOT NULL DEFAULT 'REGULAR',
  `service_type` enum('DINE_IN','TAKE_AWAY','DELIVERY','PICKUP') NOT NULL DEFAULT 'DINE_IN',
  `sales_channel_id` bigint(20) unsigned DEFAULT NULL,
  `outlet_id` bigint(20) unsigned NOT NULL,
  `terminal_id` bigint(20) unsigned DEFAULT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `cashier_session_id` bigint(20) unsigned DEFAULT NULL,
  `cashier_employee_id` bigint(20) unsigned NOT NULL,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `status` enum('DRAFT','PENDING','CONFIRMED','PAID_PARTIAL','PAID','IN_KITCHEN','READY','SERVED','VOID','REFUND_PARTIAL','REFUND_FULL') NOT NULL DEFAULT 'DRAFT',
  `kitchen_status` enum('PENDING','SENT','IN_PROGRESS','READY','SERVED','VOID') NOT NULL DEFAULT 'PENDING',
  `stock_commit_status` enum('PENDING','QUEUED','PROCESSING','POSTED','FAILED','REVERSED') NOT NULL DEFAULT 'PENDING',
  `ordered_at` datetime NOT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `stock_committed_at` datetime DEFAULT NULL,
  `stock_reversed_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `served_at` datetime DEFAULT NULL,
  `guest_count` int(10) unsigned NOT NULL DEFAULT 1,
  `table_no` varchar(40) DEFAULT NULL,
  `subtotal_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `promo_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `voucher_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `point_redeem_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `compliment_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `service_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `rounding_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `paid_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `change_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_order_no` (`order_no`) USING BTREE,
  KEY `idx_pos_order_status_date` (`status`,`ordered_at`) USING BTREE,
  KEY `idx_pos_order_outlet` (`outlet_id`) USING BTREE,
  KEY `idx_pos_order_shift` (`shift_id`) USING BTREE,
  KEY `idx_pos_order_member` (`member_id`) USING BTREE,
  KEY `fk_pos_order_terminal` (`terminal_id`) USING BTREE,
  KEY `fk_pos_order_session` (`cashier_session_id`) USING BTREE,
  KEY `fk_pos_order_cashier` (`cashier_employee_id`) USING BTREE,
  KEY `idx_pos_order_sales_channel` (`sales_channel_id`) USING BTREE,
  CONSTRAINT `fk_pos_order_cashier` FOREIGN KEY (`cashier_employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_pos_order_member` FOREIGN KEY (`member_id`) REFERENCES `crm_member` (`id`),
  CONSTRAINT `fk_pos_order_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`),
  CONSTRAINT `fk_pos_order_sales_channel` FOREIGN KEY (`sales_channel_id`) REFERENCES `pos_sales_channel` (`id`),
  CONSTRAINT `fk_pos_order_session` FOREIGN KEY (`cashier_session_id`) REFERENCES `pos_cashier_session` (`id`),
  CONSTRAINT `fk_pos_order_shift` FOREIGN KEY (`shift_id`) REFERENCES `pos_shift` (`id`),
  CONSTRAINT `fk_pos_order_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `pos_terminal` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_order_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `line_no` int(11) NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `bundle_id` bigint(20) unsigned DEFAULT NULL,
  `line_type` enum('PRODUCT','BUNDLE_HEADER','BUNDLE_ITEM') NOT NULL DEFAULT 'PRODUCT',
  `product_division_id_snapshot` bigint(20) unsigned DEFAULT NULL,
  `operational_division_id` bigint(20) unsigned DEFAULT NULL,
  `uom_id` bigint(20) unsigned DEFAULT NULL,
  `qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `hpp_standard_snapshot` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `hpp_live_snapshot` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `cogs_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `availability_mode_snapshot` enum('AUTO','FORCE_AVAILABLE','FORCE_OUT','MANUAL_ALLOWED') NOT NULL DEFAULT 'AUTO',
  `line_status` enum('OPEN','SENT','READY','SERVED','VOID','REFUNDED_PARTIAL','REFUNDED_FULL') NOT NULL DEFAULT 'OPEN',
  `process_status` enum('NOT_PROCESSED','PROCESSED','SERVED') NOT NULL DEFAULT 'NOT_PROCESSED',
  `processed_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_order_line_no` (`order_id`,`line_no`) USING BTREE,
  KEY `idx_pos_order_line_product` (`product_id`) USING BTREE,
  KEY `idx_pos_order_line_bundle` (`bundle_id`) USING BTREE,
  KEY `idx_pos_order_line_process` (`process_status`) USING BTREE,
  KEY `fk_pos_order_line_division` (`product_division_id_snapshot`) USING BTREE,
  KEY `fk_pos_order_line_oper_div` (`operational_division_id`) USING BTREE,
  KEY `fk_pos_order_line_uom` (`uom_id`) USING BTREE,
  CONSTRAINT `fk_pos_order_line_bundle` FOREIGN KEY (`bundle_id`) REFERENCES `pos_product_bundle` (`id`),
  CONSTRAINT `fk_pos_order_line_division` FOREIGN KEY (`product_division_id_snapshot`) REFERENCES `mst_product_division` (`id`),
  CONSTRAINT `fk_pos_order_line_oper_div` FOREIGN KEY (`operational_division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_pos_order_line_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`),
  CONSTRAINT `fk_pos_order_line_product` FOREIGN KEY (`product_id`) REFERENCES `mst_product` (`id`),
  CONSTRAINT `fk_pos_order_line_uom` FOREIGN KEY (`uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_order_line_extra` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `order_line_id` bigint(20) unsigned NOT NULL,
  `line_no` int(11) NOT NULL,
  `extra_id` bigint(20) unsigned NOT NULL,
  `qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `cost_amount_snapshot` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_order_line_extra_no` (`order_line_id`,`line_no`) USING BTREE,
  KEY `idx_pos_order_line_extra_extra` (`extra_id`) USING BTREE,
  KEY `fk_pos_order_line_extra_order` (`order_id`) USING BTREE,
  CONSTRAINT `fk_pos_order_line_extra_extra` FOREIGN KEY (`extra_id`) REFERENCES `mst_extra` (`id`),
  CONSTRAINT `fk_pos_order_line_extra_line` FOREIGN KEY (`order_line_id`) REFERENCES `pos_order_line` (`id`),
  CONSTRAINT `fk_pos_order_line_extra_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_order_monitor_task` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `order_line_id` bigint(20) unsigned NOT NULL,
  `station_role` enum('BAR','KITCHEN') NOT NULL,
  `ack_at` datetime DEFAULT NULL,
  `ack_by_employee_id` bigint(20) unsigned DEFAULT NULL,
  `ready_at` datetime DEFAULT NULL,
  `ready_by_employee_id` bigint(20) unsigned DEFAULT NULL,
  `checker_done_at` datetime DEFAULT NULL,
  `checker_done_by_employee_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_order_monitor_line_station` (`order_line_id`,`station_role`) USING BTREE,
  KEY `idx_pos_order_monitor_order` (`order_id`) USING BTREE,
  KEY `idx_pos_order_monitor_station` (`station_role`) USING BTREE,
  KEY `idx_pos_order_monitor_ack` (`ack_at`) USING BTREE,
  KEY `idx_pos_order_monitor_ready` (`ready_at`) USING BTREE,
  KEY `idx_pos_order_monitor_checker` (`checker_done_at`) USING BTREE,
  KEY `fk_pos_order_monitor_ack_by` (`ack_by_employee_id`) USING BTREE,
  KEY `fk_pos_order_monitor_ready_by` (`ready_by_employee_id`) USING BTREE,
  KEY `fk_pos_order_monitor_checker_by` (`checker_done_by_employee_id`) USING BTREE,
  CONSTRAINT `fk_pos_order_monitor_ack_by` FOREIGN KEY (`ack_by_employee_id`) REFERENCES `org_employee` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_order_monitor_checker_by` FOREIGN KEY (`checker_done_by_employee_id`) REFERENCES `org_employee` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_order_monitor_line` FOREIGN KEY (`order_line_id`) REFERENCES `pos_order_line` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pos_order_monitor_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pos_order_monitor_ready_by` FOREIGN KEY (`ready_by_employee_id`) REFERENCES `org_employee` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_order_state_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) NOT NULL,
  `event_code` varchar(40) NOT NULL,
  `actor_employee_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pos_order_state_log_order` (`order_id`) USING BTREE,
  KEY `idx_pos_order_state_log_actor` (`actor_employee_id`) USING BTREE,
  CONSTRAINT `fk_pos_order_state_log_actor` FOREIGN KEY (`actor_employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_pos_order_state_log_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_outlet` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_code` varchar(40) NOT NULL,
  `outlet_name` varchar(120) NOT NULL,
  `outlet_scope` enum('REGULAR','EVENT','MIXED','ALL') NOT NULL DEFAULT 'REGULAR',
  `product_division_id` bigint(20) unsigned DEFAULT NULL,
  `operational_division_id` bigint(20) unsigned DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_outlet_code` (`outlet_code`) USING BTREE,
  KEY `idx_pos_outlet_product_division` (`product_division_id`) USING BTREE,
  KEY `idx_pos_outlet_operational_division` (`operational_division_id`) USING BTREE,
  CONSTRAINT `fk_pos_outlet_operational_division` FOREIGN KEY (`operational_division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_pos_outlet_product_division` FOREIGN KEY (`product_division_id`) REFERENCES `mst_product_division` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_payment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_no` varchar(40) NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `cashier_session_id` bigint(20) unsigned DEFAULT NULL,
  `cashier_employee_id` bigint(20) unsigned DEFAULT NULL,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `payment_type` enum('FINAL','DEPOSIT','REFUND') NOT NULL DEFAULT 'FINAL',
  `payment_status` enum('PENDING','PAID','FAILED','VOID') NOT NULL DEFAULT 'PENDING',
  `paid_at` datetime DEFAULT NULL,
  `gross_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `promo_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `voucher_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `point_redeem_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `compliment_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `deposit_applied_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `change_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_payment_no` (`payment_no`) USING BTREE,
  KEY `idx_pos_payment_order` (`order_id`) USING BTREE,
  KEY `idx_pos_payment_shift` (`shift_id`) USING BTREE,
  KEY `idx_pos_payment_status_date` (`payment_status`,`paid_at`) USING BTREE,
  KEY `idx_pos_payment_member` (`member_id`) USING BTREE,
  KEY `fk_pos_payment_session` (`cashier_session_id`) USING BTREE,
  KEY `fk_pos_payment_cashier` (`cashier_employee_id`) USING BTREE,
  CONSTRAINT `fk_pos_payment_cashier` FOREIGN KEY (`cashier_employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_pos_payment_member` FOREIGN KEY (`member_id`) REFERENCES `crm_member` (`id`),
  CONSTRAINT `fk_pos_payment_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`),
  CONSTRAINT `fk_pos_payment_session` FOREIGN KEY (`cashier_session_id`) REFERENCES `pos_cashier_session` (`id`),
  CONSTRAINT `fk_pos_payment_shift` FOREIGN KEY (`shift_id`) REFERENCES `pos_shift` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_payment_deposit_apply` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `deposit_payment_id` bigint(20) unsigned NOT NULL,
  `applied_payment_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `applied_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `apply_status` enum('APPLIED','VOID') NOT NULL DEFAULT 'APPLIED',
  `notes` varchar(255) DEFAULT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  `voided_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_deposit_apply_pair` (`deposit_payment_id`,`applied_payment_id`) USING BTREE,
  KEY `idx_pos_deposit_apply_order` (`order_id`) USING BTREE,
  KEY `idx_pos_deposit_apply_status` (`apply_status`) USING BTREE,
  KEY `fk_pos_deposit_apply_applied_payment` (`applied_payment_id`) USING BTREE,
  CONSTRAINT `fk_pos_deposit_apply_applied_payment` FOREIGN KEY (`applied_payment_id`) REFERENCES `pos_payment` (`id`),
  CONSTRAINT `fk_pos_deposit_apply_deposit_payment` FOREIGN KEY (`deposit_payment_id`) REFERENCES `pos_payment` (`id`),
  CONSTRAINT `fk_pos_deposit_apply_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_payment_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint(20) unsigned NOT NULL,
  `line_no` int(11) NOT NULL,
  `payment_method_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `reference_no` varchar(100) DEFAULT NULL,
  `gateway_txn_id` varchar(100) DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `status` enum('PENDING','PAID','FAILED','VOID') NOT NULL DEFAULT 'PAID',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_payment_line_no` (`payment_id`,`line_no`) USING BTREE,
  KEY `idx_pos_payment_line_method` (`payment_method_id`) USING BTREE,
  CONSTRAINT `fk_pos_payment_line_method` FOREIGN KEY (`payment_method_id`) REFERENCES `pos_payment_method` (`id`),
  CONSTRAINT `fk_pos_payment_line_payment` FOREIGN KEY (`payment_id`) REFERENCES `pos_payment` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_payment_method` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `method_code` varchar(40) NOT NULL,
  `method_name` varchar(120) NOT NULL,
  `method_type` enum('CASH','BANK','EWALLET','QRIS','COMPLIMENT','DEPOSIT','OTHER') NOT NULL DEFAULT 'CASH',
  `company_account_id` bigint(20) unsigned DEFAULT NULL,
  `allows_change` tinyint(1) NOT NULL DEFAULT 0,
  `requires_reference_no` tinyint(1) NOT NULL DEFAULT 0,
  `show_in_cashier` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_payment_method_code` (`method_code`) USING BTREE,
  KEY `idx_pos_payment_method_account` (`company_account_id`) USING BTREE,
  KEY `idx_pos_payment_method_type` (`method_type`) USING BTREE,
  CONSTRAINT `fk_pos_payment_method_account` FOREIGN KEY (`company_account_id`) REFERENCES `fin_company_account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_point_ledger` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `rule_id` bigint(20) unsigned DEFAULT NULL,
  `ledger_type` enum('EARN','REDEEM','ADJUST','EXPIRE','REVERSE') NOT NULL DEFAULT 'EARN',
  `points_in` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `points_out` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `balance_after` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `expired_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pos_point_ledger_member` (`member_id`) USING BTREE,
  KEY `idx_pos_point_ledger_order` (`order_id`) USING BTREE,
  KEY `idx_pos_point_ledger_payment` (`payment_id`) USING BTREE,
  KEY `idx_pos_point_ledger_rule` (`rule_id`) USING BTREE,
  CONSTRAINT `fk_pos_point_ledger_member` FOREIGN KEY (`member_id`) REFERENCES `crm_member` (`id`),
  CONSTRAINT `fk_pos_point_ledger_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`),
  CONSTRAINT `fk_pos_point_ledger_payment` FOREIGN KEY (`payment_id`) REFERENCES `pos_payment` (`id`),
  CONSTRAINT `fk_pos_point_ledger_rule` FOREIGN KEY (`rule_id`) REFERENCES `pos_point_rule` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_point_rule` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rule_code` varchar(40) NOT NULL,
  `rule_name` varchar(120) NOT NULL,
  `earn_mode` enum('AMOUNT','PRODUCT','FLAT') NOT NULL DEFAULT 'AMOUNT',
  `spend_basis` enum('NET','GROSS') NOT NULL DEFAULT 'NET',
  `amount_per_point` decimal(18,2) NOT NULL DEFAULT 0.00,
  `flat_point` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `required_product_id` bigint(20) unsigned DEFAULT NULL,
  `min_spend_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `point_expiry_days` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_point_rule_code` (`rule_code`) USING BTREE,
  KEY `idx_pos_point_rule_product` (`required_product_id`) USING BTREE,
  CONSTRAINT `fk_pos_point_rule_product` FOREIGN KEY (`required_product_id`) REFERENCES `mst_product` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_print_attempt` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `attempt_no` varchar(80) NOT NULL,
  `event_code` varchar(60) NOT NULL,
  `document_type` varchar(40) NOT NULL,
  `attempt_kind` enum('AUTO','REPRINT','TEST','PREVIEW') NOT NULL DEFAULT 'AUTO',
  `status` enum('GENERATED','SENT','FAILED','SKIPPED','VOID') NOT NULL DEFAULT 'GENERATED',
  `route_id` bigint(20) unsigned DEFAULT NULL,
  `connection_id` bigint(20) unsigned DEFAULT NULL,
  `layout_id` bigint(20) unsigned DEFAULT NULL,
  `outlet_id` bigint(20) unsigned DEFAULT NULL,
  `terminal_id` bigint(20) unsigned DEFAULT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `void_id` bigint(20) unsigned DEFAULT NULL,
  `refund_id` bigint(20) unsigned DEFAULT NULL,
  `target_summary` longtext DEFAULT NULL,
  `agent_message` varchar(500) DEFAULT NULL,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `acknowledged_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_print_attempt_no` (`attempt_no`) USING BTREE,
  KEY `idx_pos_print_attempt_status_date` (`status`,`requested_at`) USING BTREE,
  KEY `idx_pos_print_attempt_order_event` (`order_id`,`event_code`,`requested_at`) USING BTREE,
  KEY `idx_pos_print_attempt_connection_date` (`connection_id`,`requested_at`) USING BTREE,
  KEY `fk_pos_print_attempt_route` (`route_id`) USING BTREE,
  KEY `fk_pos_print_attempt_layout` (`layout_id`) USING BTREE,
  KEY `fk_pos_print_attempt_outlet` (`outlet_id`) USING BTREE,
  KEY `fk_pos_print_attempt_terminal` (`terminal_id`) USING BTREE,
  KEY `fk_pos_print_attempt_acknowledged_by` (`acknowledged_by`) USING BTREE,
  CONSTRAINT `fk_pos_print_attempt_acknowledged_by` FOREIGN KEY (`acknowledged_by`) REFERENCES `org_employee` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_print_attempt_connection` FOREIGN KEY (`connection_id`) REFERENCES `pos_print_connection` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_print_attempt_layout` FOREIGN KEY (`layout_id`) REFERENCES `pos_print_layout` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_print_attempt_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_print_attempt_route` FOREIGN KEY (`route_id`) REFERENCES `pos_print_route` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_print_attempt_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `pos_terminal` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Jejak target cetak dari POS sampai acknowledgement agent browser.';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_print_connection` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `connection_code` varchar(60) NOT NULL,
  `connection_name` varchar(150) NOT NULL,
  `outlet_id` bigint(20) unsigned DEFAULT NULL,
  `operational_division_id` bigint(20) unsigned DEFAULT NULL,
  `location_label` varchar(120) DEFAULT NULL,
  `connection_type` enum('LOCAL_AGENT','LAN','USB') NOT NULL DEFAULT 'LOCAL_AGENT',
  `agent_os` enum('WINDOWS','UBUNTU','OTHER') NOT NULL DEFAULT 'WINDOWS',
  `agent_host` varchar(120) DEFAULT NULL,
  `agent_printer_code` varchar(60) DEFAULT NULL,
  `device_name` varchar(120) DEFAULT NULL,
  `mac_address` varchar(32) DEFAULT NULL,
  `python_port` int(10) unsigned DEFAULT NULL,
  `ip_address` varchar(60) DEFAULT NULL,
  `port` int(10) unsigned DEFAULT NULL,
  `paper_width_mm` tinyint(3) unsigned NOT NULL DEFAULT 80,
  `chars_per_line` tinyint(3) unsigned NOT NULL DEFAULT 48,
  `default_copy_count` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `cut_mode` enum('NONE','PARTIAL','FULL') NOT NULL DEFAULT 'PARTIAL',
  `open_drawer` tinyint(1) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `legacy_printer_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_print_connection_code` (`connection_code`) USING BTREE,
  UNIQUE KEY `uk_pos_print_connection_legacy_printer` (`legacy_printer_id`) USING BTREE,
  KEY `idx_pos_print_connection_outlet_active` (`outlet_id`,`is_active`) USING BTREE,
  KEY `idx_pos_print_connection_agent_port` (`agent_host`,`python_port`) USING BTREE,
  KEY `fk_pos_print_connection_operational_division` (`operational_division_id`) USING BTREE,
  CONSTRAINT `fk_pos_print_connection_operational_division` FOREIGN KEY (`operational_division_id`) REFERENCES `mst_operational_division` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_print_connection_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Koneksi fisik printer dan agent. Tidak menyimpan layout atau routing dokumen.';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_print_general_setting` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `setting_code` varchar(60) NOT NULL,
  `setting_name` varchar(150) NOT NULL,
  `outlet_id` bigint(20) unsigned DEFAULT NULL,
  `general_payload` longtext DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_print_general_setting_code` (`setting_code`) USING BTREE,
  KEY `idx_pos_print_general_setting_outlet` (`outlet_id`,`is_active`) USING BTREE,
  CONSTRAINT `fk_pos_print_general_setting_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Branding dan data umum cetak. Layout menentukan apakah data ini ditampilkan atau tidak.';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_print_layout` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `layout_code` varchar(60) NOT NULL,
  `layout_name` varchar(150) NOT NULL,
  `document_type` enum('RECEIPT','KITCHEN_TICKET','VOID_SLIP','REFUND_SLIP','DEPOSIT_RECEIPT','SHIFT_CLOSE') NOT NULL,
  `layout_payload` longtext DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `legacy_template_id` bigint(20) unsigned DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_print_layout_code` (`layout_code`) USING BTREE,
  UNIQUE KEY `uk_pos_print_layout_legacy_template` (`legacy_template_id`) USING BTREE,
  KEY `idx_pos_print_layout_document_active` (`document_type`,`is_active`,`is_default`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Layout dokumen dan seluruh switch data tampil/sembunyi untuk cetak POS.';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_print_route` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `route_code` varchar(80) NOT NULL,
  `route_name` varchar(180) NOT NULL,
  `event_code` varchar(60) NOT NULL,
  `document_type` enum('RECEIPT','KITCHEN_TICKET','VOID_SLIP','REFUND_SLIP','DEPOSIT_RECEIPT','SHIFT_CLOSE') NOT NULL,
  `outlet_id` bigint(20) unsigned DEFAULT NULL,
  `terminal_id` bigint(20) unsigned DEFAULT NULL,
  `operational_division_id` bigint(20) unsigned DEFAULT NULL,
  `product_division_id` bigint(20) unsigned DEFAULT NULL,
  `content_scope` enum('MATCHED_DIVISION','ALL_ITEMS') NOT NULL DEFAULT 'ALL_ITEMS',
  `connection_id` bigint(20) unsigned NOT NULL,
  `layout_id` bigint(20) unsigned NOT NULL,
  `copy_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `priority` int(11) NOT NULL DEFAULT 100,
  `notes` varchar(255) DEFAULT NULL,
  `print_mode` enum('OFF','AUTO','ASK') NOT NULL DEFAULT 'AUTO',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_print_route_code` (`route_code`) USING BTREE,
  KEY `idx_pos_print_route_lookup` (`event_code`,`is_active`,`outlet_id`,`terminal_id`,`operational_division_id`,`product_division_id`) USING BTREE,
  KEY `idx_pos_print_route_connection` (`connection_id`,`is_active`) USING BTREE,
  KEY `fk_pos_print_route_outlet` (`outlet_id`) USING BTREE,
  KEY `fk_pos_print_route_terminal` (`terminal_id`) USING BTREE,
  KEY `fk_pos_print_route_operational_division` (`operational_division_id`) USING BTREE,
  KEY `fk_pos_print_route_product_division` (`product_division_id`) USING BTREE,
  KEY `fk_pos_print_route_layout` (`layout_id`) USING BTREE,
  KEY `idx_pos_print_route_event_mode` (`event_code`,`print_mode`,`outlet_id`,`terminal_id`) USING BTREE,
  CONSTRAINT `fk_pos_print_route_connection` FOREIGN KEY (`connection_id`) REFERENCES `pos_print_connection` (`id`),
  CONSTRAINT `fk_pos_print_route_layout` FOREIGN KEY (`layout_id`) REFERENCES `pos_print_layout` (`id`),
  CONSTRAINT `fk_pos_print_route_operational_division` FOREIGN KEY (`operational_division_id`) REFERENCES `mst_operational_division` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_print_route_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_print_route_product_division` FOREIGN KEY (`product_division_id`) REFERENCES `mst_product_division` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_print_route_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `pos_terminal` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Aturan eksplisit event, sumber order, koneksi printer, layout, dan copy.';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_product_availability_cache` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `availability_status` enum('AVAILABLE','LIMITED','OUT','HIDDEN') NOT NULL DEFAULT 'AVAILABLE',
  `source_mode` enum('AUTO','OVERRIDE_AVAILABLE','OVERRIDE_OUT') NOT NULL DEFAULT 'AUTO',
  `estimated_available_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `uom_id` bigint(20) unsigned DEFAULT NULL,
  `bottleneck_kind` enum('NONE','MATERIAL','COMPONENT') NOT NULL DEFAULT 'NONE',
  `bottleneck_material_id` bigint(20) unsigned DEFAULT NULL,
  `bottleneck_component_id` bigint(20) unsigned DEFAULT NULL,
  `bottleneck_name_snapshot` varchar(150) DEFAULT NULL,
  `main_missing_count` int(10) unsigned NOT NULL DEFAULT 0,
  `optional_missing_count` int(10) unsigned NOT NULL DEFAULT 0,
  `override_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `hpp_live_snapshot` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `stock_reference_at` datetime DEFAULT NULL,
  `last_commit_event` varchar(50) DEFAULT NULL,
  `computed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_dirty` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_product_availability_cache` (`outlet_id`,`product_id`) USING BTREE,
  KEY `idx_pos_product_availability_status` (`availability_status`) USING BTREE,
  KEY `fk_pos_prod_avail_cache_product` (`product_id`) USING BTREE,
  KEY `fk_pos_prod_avail_cache_uom` (`uom_id`) USING BTREE,
  KEY `fk_pos_prod_avail_cache_material` (`bottleneck_material_id`) USING BTREE,
  KEY `fk_pos_prod_avail_cache_component` (`bottleneck_component_id`) USING BTREE,
  CONSTRAINT `fk_pos_prod_avail_cache_component` FOREIGN KEY (`bottleneck_component_id`) REFERENCES `mst_component` (`id`),
  CONSTRAINT `fk_pos_prod_avail_cache_material` FOREIGN KEY (`bottleneck_material_id`) REFERENCES `mst_material` (`id`),
  CONSTRAINT `fk_pos_prod_avail_cache_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`),
  CONSTRAINT `fk_pos_prod_avail_cache_product` FOREIGN KEY (`product_id`) REFERENCES `mst_product` (`id`),
  CONSTRAINT `fk_pos_prod_avail_cache_uom` FOREIGN KEY (`uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_product_availability_override` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `override_mode` enum('AUTO','FORCE_AVAILABLE','FORCE_OUT') NOT NULL DEFAULT 'AUTO',
  `override_note` varchar(255) DEFAULT NULL,
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_product_availability_override` (`outlet_id`,`product_id`) USING BTREE,
  KEY `idx_pos_product_availability_override_mode` (`override_mode`) USING BTREE,
  KEY `fk_pos_prod_avail_override_product` (`product_id`) USING BTREE,
  KEY `fk_pos_prod_avail_override_created_by` (`created_by`) USING BTREE,
  KEY `fk_pos_prod_avail_override_updated_by` (`updated_by`) USING BTREE,
  CONSTRAINT `fk_pos_prod_avail_override_created_by` FOREIGN KEY (`created_by`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_pos_prod_avail_override_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`),
  CONSTRAINT `fk_pos_prod_avail_override_product` FOREIGN KEY (`product_id`) REFERENCES `mst_product` (`id`),
  CONSTRAINT `fk_pos_prod_avail_override_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `org_employee` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_product_availability_probe` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `cache_status` varchar(20) DEFAULT NULL,
  `cache_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `live_status` varchar(20) DEFAULT NULL,
  `live_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `mismatch_flag` tinyint(1) NOT NULL DEFAULT 0,
  `trigger_context` varchar(60) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_ppa_probe_outlet_product` (`outlet_id`,`product_id`) USING BTREE,
  KEY `idx_ppa_probe_mismatch` (`mismatch_flag`) USING BTREE,
  KEY `idx_ppa_probe_created_at` (`created_at`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_product_availability_probe_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `probe_id` bigint(20) unsigned NOT NULL,
  `line_no` int(10) unsigned NOT NULL DEFAULT 1,
  `source_kind` varchar(20) NOT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `source_name_snapshot` varchar(180) DEFAULT NULL,
  `source_role` varchar(20) NOT NULL DEFAULT 'MAIN',
  `required_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `available_qty_live` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `short_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `is_bottleneck` tinyint(1) NOT NULL DEFAULT 0,
  `cost_source` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_ppa_probe_line_probe` (`probe_id`,`line_no`) USING BTREE,
  KEY `idx_ppa_probe_line_source` (`source_kind`,`source_id`) USING BTREE,
  CONSTRAINT `fk_ppa_probe_line_probe` FOREIGN KEY (`probe_id`) REFERENCES `pos_product_availability_probe` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_product_availability_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `status` enum('QUEUED','PROCESSING','SUCCESS','FAILED','CANCELLED') NOT NULL DEFAULT 'QUEUED',
  `revision` bigint(20) unsigned NOT NULL DEFAULT 1,
  `event_count` bigint(20) unsigned NOT NULL DEFAULT 1,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `max_attempts` int(10) unsigned NOT NULL DEFAULT 3,
  `run_after` datetime NOT NULL DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `event_source` varchar(80) DEFAULT NULL,
  `event_table` varchar(80) DEFAULT NULL,
  `event_id` bigint(20) unsigned DEFAULT NULL,
  `actor_employee_id` bigint(20) unsigned DEFAULT NULL,
  `result_json` longtext DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_product_availability_queue_target` (`outlet_id`,`product_id`) USING BTREE,
  KEY `idx_pos_product_availability_queue_ready` (`status`,`run_after`,`id`) USING BTREE,
  KEY `idx_pos_product_availability_queue_product` (`product_id`,`status`) USING BTREE,
  KEY `fk_pos_product_availability_queue_actor` (`actor_employee_id`) USING BTREE,
  CONSTRAINT `fk_pos_product_availability_queue_actor` FOREIGN KEY (`actor_employee_id`) REFERENCES `org_employee` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_product_availability_queue_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`),
  CONSTRAINT `fk_pos_product_availability_queue_product` FOREIGN KEY (`product_id`) REFERENCES `mst_product` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_product_availability_rebuild_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_source` varchar(60) NOT NULL,
  `event_table` varchar(80) DEFAULT NULL,
  `event_id` bigint(20) unsigned DEFAULT NULL,
  `outlet_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `cache_status_before` varchar(20) DEFAULT NULL,
  `cache_qty_before` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `cache_status_after` varchar(20) DEFAULT NULL,
  `cache_qty_after` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `live_status` varchar(20) DEFAULT NULL,
  `live_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `live_hpp` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `mismatch_flag` tinyint(1) NOT NULL DEFAULT 0,
  `mismatch_note` varchar(255) DEFAULT NULL,
  `actor_employee_id` bigint(20) unsigned DEFAULT NULL,
  `rebuilt_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_ppa_rebuild_outlet_product` (`outlet_id`,`product_id`) USING BTREE,
  KEY `idx_ppa_rebuild_event` (`event_source`,`event_table`,`event_id`) USING BTREE,
  KEY `idx_ppa_rebuild_mismatch` (`mismatch_flag`) USING BTREE,
  KEY `idx_ppa_rebuild_at` (`rebuilt_at`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_product_bundle` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bundle_code` varchar(40) NOT NULL,
  `bundle_name` varchar(150) NOT NULL,
  `product_division_id` bigint(20) unsigned DEFAULT NULL,
  `pos_scope` enum('REGULAR','EVENT','ALL') NOT NULL DEFAULT 'REGULAR',
  `selling_price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_product_bundle_code` (`bundle_code`) USING BTREE,
  KEY `idx_pos_product_bundle_division` (`product_division_id`) USING BTREE,
  CONSTRAINT `fk_pos_product_bundle_division` FOREIGN KEY (`product_division_id`) REFERENCES `mst_product_division` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_product_bundle_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bundle_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `qty` decimal(18,4) NOT NULL DEFAULT 1.0000,
  `unit_price_override` decimal(18,2) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_product_bundle_line_product` (`bundle_id`,`product_id`) USING BTREE,
  KEY `idx_pos_product_bundle_line_product` (`product_id`) USING BTREE,
  CONSTRAINT `fk_pos_product_bundle_line_bundle` FOREIGN KEY (`bundle_id`) REFERENCES `pos_product_bundle` (`id`),
  CONSTRAINT `fk_pos_product_bundle_line_product` FOREIGN KEY (`product_id`) REFERENCES `mst_product` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_redeem_rule` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rule_code` varchar(60) NOT NULL COMMENT 'Kode unik internal, format RR-NAMA',
  `rule_name` varchar(150) NOT NULL COMMENT 'Nama reward (tampil ke operator)',
  `description` text DEFAULT NULL COMMENT 'Penjelasan detail reward ini',
  `cost_type` enum('POINT','STAMP','BOTH') NOT NULL DEFAULT 'POINT' COMMENT 'Aset yang digunakan untuk redeem',
  `point_cost` decimal(14,4) DEFAULT NULL COMMENT 'Jumlah poin yang dibutuhkan (jika POINT atau BOTH)',
  `stamp_campaign_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Campaign stamp yang berlaku (jika STAMP atau BOTH)',
  `stamp_cost` decimal(14,4) DEFAULT NULL COMMENT 'Jumlah stamp yang dibutuhkan (jika STAMP atau BOTH)',
  `reward_type` enum('VOUCHER','PRODUCT','MERCHANDISE','DISCOUNT_AMOUNT','DISCOUNT_PERCENT','FREE_PRODUCT','OTHER') NOT NULL DEFAULT 'DISCOUNT_AMOUNT' COMMENT 'Jenis benefit/hadiah yang diterima member',
  `voucher_campaign_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Campaign voucher yang diterbitkan (jika reward_type = VOUCHER)',
  `product_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Produk reward atau produk gratis (FK ke mst_product)',
  `product_qty` decimal(10,4) DEFAULT NULL COMMENT 'Jumlah produk yang diberikan',
  `discount_amount` decimal(14,2) DEFAULT NULL COMMENT 'Nilai diskon dalam rupiah (jika DISCOUNT_AMOUNT)',
  `discount_percent` decimal(8,4) DEFAULT NULL COMMENT 'Persen diskon 0–100 (jika DISCOUNT_PERCENT)',
  `reward_notes` varchar(255) DEFAULT NULL COMMENT 'Deskripsi reward untuk tipe MERCHANDISE / OTHER',
  `min_spend_amount` decimal(14,2) DEFAULT NULL COMMENT 'Minimal nominal transaksi agar bisa redeem ini (opsional)',
  `stock_qty` int(11) DEFAULT NULL COMMENT 'Stok tersedia; NULL = tidak terbatas',
  `valid_days` int(11) DEFAULT NULL COMMENT 'Jumlah hari berlaku sejak diterbitkan; NULL = selamanya',
  `redeemed_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Sudah berapa kali ditebus (diupdate otomatis)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_rr_code` (`rule_code`) USING BTREE,
  KEY `idx_rr_cost_type` (`cost_type`) USING BTREE,
  KEY `idx_rr_reward_type` (`reward_type`) USING BTREE,
  KEY `idx_rr_active` (`is_active`) USING BTREE,
  KEY `idx_rr_stamp_campaign` (`stamp_campaign_id`) USING BTREE,
  KEY `idx_rr_voucher_campaign` (`voucher_campaign_id`) USING BTREE,
  CONSTRAINT `fk_rr_stamp_campaign` FOREIGN KEY (`stamp_campaign_id`) REFERENCES `pos_stamp_campaign` (`id`),
  CONSTRAINT `fk_rr_voucher_campaign` FOREIGN KEY (`voucher_campaign_id`) REFERENCES `pos_voucher_campaign` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Katalog reward yang bisa diperoleh member melalui proses redeem (poin/stamp)';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_redeem_transaction` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `redeem_no` varchar(30) NOT NULL COMMENT 'Nomor unik transaksi redeem, format RDM-YYYYMMDD-NNNN',
  `member_id` bigint(20) unsigned NOT NULL,
  `redeem_type` enum('POINT','STAMP','VOUCHER') NOT NULL COMMENT 'Jenis aset yang ditebus',
  `rule_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK ke pos_redeem_rule',
  `point_ledger_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK ke pos_point_ledger',
  `points_used` decimal(14,4) DEFAULT NULL COMMENT 'Jumlah poin yang dikurangi',
  `stamp_ledger_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK ke pos_stamp_ledger',
  `stamps_used` decimal(14,4) DEFAULT NULL COMMENT 'Jumlah stamp yang dikurangi',
  `voucher_issue_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK ke pos_voucher_issue',
  `voucher_code` varchar(80) DEFAULT NULL COMMENT 'Kode voucher snapshot saat ditebus',
  `reward_type` enum('DISCOUNT_AMOUNT','DISCOUNT_PERCENT','FREE_PRODUCT','VOUCHER_ISSUED','CUSTOM') DEFAULT NULL,
  `reward_desc` varchar(255) DEFAULT NULL COMMENT 'Deskripsi singkat reward',
  `reward_amount` decimal(14,2) DEFAULT NULL COMMENT 'Nilai moneter reward bila ada',
  `notes` text DEFAULT NULL,
  `redeemed_by` bigint(20) unsigned DEFAULT NULL COMMENT 'FK ke auth_user (operator yang proses)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_redeem_no` (`redeem_no`) USING BTREE,
  KEY `idx_prt_member` (`member_id`) USING BTREE,
  KEY `idx_prt_type` (`redeem_type`) USING BTREE,
  KEY `idx_prt_created` (`created_at`) USING BTREE,
  KEY `idx_prt_voucher` (`voucher_issue_id`) USING BTREE,
  CONSTRAINT `fk_prt_member` FOREIGN KEY (`member_id`) REFERENCES `crm_member` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Log terpusat semua transaksi redeem loyalty member (poin/stamp/voucher)';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_refund` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `refund_no` varchar(40) NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `payment_method_id` bigint(20) unsigned DEFAULT NULL,
  `company_account_id` bigint(20) unsigned DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `refund_status` enum('POSTED','VOID') NOT NULL DEFAULT 'POSTED',
  `processed_state` enum('NOT_PROCESSED','PROCESSED') NOT NULL DEFAULT 'NOT_PROCESSED',
  `return_to_stock` tinyint(1) NOT NULL DEFAULT 0,
  `adjustment_mode` enum('NONE','AUTO_WASTE','AUTO_SPOIL','AUTO_ADJUSTMENT') NOT NULL DEFAULT 'NONE',
  `refund_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `reason` text DEFAULT NULL,
  `refunded_by` bigint(20) unsigned DEFAULT NULL,
  `refunded_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_refund_no` (`refund_no`) USING BTREE,
  KEY `idx_pos_refund_order` (`order_id`) USING BTREE,
  KEY `fk_pos_refund_payment` (`payment_id`) USING BTREE,
  KEY `fk_pos_refund_member` (`member_id`) USING BTREE,
  KEY `fk_pos_refund_method` (`payment_method_id`) USING BTREE,
  KEY `fk_pos_refund_account` (`company_account_id`) USING BTREE,
  KEY `fk_pos_refund_by` (`refunded_by`) USING BTREE,
  CONSTRAINT `fk_pos_refund_account` FOREIGN KEY (`company_account_id`) REFERENCES `fin_company_account` (`id`),
  CONSTRAINT `fk_pos_refund_by` FOREIGN KEY (`refunded_by`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_pos_refund_member` FOREIGN KEY (`member_id`) REFERENCES `crm_member` (`id`),
  CONSTRAINT `fk_pos_refund_method` FOREIGN KEY (`payment_method_id`) REFERENCES `pos_payment_method` (`id`),
  CONSTRAINT `fk_pos_refund_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`),
  CONSTRAINT `fk_pos_refund_payment` FOREIGN KEY (`payment_id`) REFERENCES `pos_payment` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_refund_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `refund_id` bigint(20) unsigned NOT NULL,
  `line_no` int(11) NOT NULL,
  `line_type` enum('PRODUCT','EXTRA') NOT NULL DEFAULT 'PRODUCT',
  `order_line_id` bigint(20) unsigned DEFAULT NULL,
  `order_extra_line_id` bigint(20) unsigned DEFAULT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `extra_id` bigint(20) unsigned DEFAULT NULL,
  `qty_refunded` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `amount_refunded` decimal(18,2) NOT NULL DEFAULT 0.00,
  `gross_amount_refunded` decimal(18,2) NOT NULL DEFAULT 0.00,
  `cost_reversed` decimal(18,2) NOT NULL DEFAULT 0.00,
  `line_process_state` enum('NOT_PROCESSED','PROCESSED') NOT NULL DEFAULT 'NOT_PROCESSED',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_refund_line_no` (`refund_id`,`line_no`) USING BTREE,
  KEY `idx_pos_refund_line_order_line` (`order_line_id`) USING BTREE,
  KEY `fk_pos_refund_line_extra` (`order_extra_line_id`) USING BTREE,
  KEY `fk_pos_refund_line_product` (`product_id`) USING BTREE,
  KEY `fk_pos_refund_line_extra_master` (`extra_id`) USING BTREE,
  CONSTRAINT `fk_pos_refund_line_extra` FOREIGN KEY (`order_extra_line_id`) REFERENCES `pos_order_line_extra` (`id`),
  CONSTRAINT `fk_pos_refund_line_extra_master` FOREIGN KEY (`extra_id`) REFERENCES `mst_extra` (`id`),
  CONSTRAINT `fk_pos_refund_line_order_line` FOREIGN KEY (`order_line_id`) REFERENCES `pos_order_line` (`id`),
  CONSTRAINT `fk_pos_refund_line_product` FOREIGN KEY (`product_id`) REFERENCES `mst_product` (`id`),
  CONSTRAINT `fk_pos_refund_line_refund` FOREIGN KEY (`refund_id`) REFERENCES `pos_refund` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_reservation` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reservation_no` varchar(50) NOT NULL,
  `status` enum('PENDING','VERIFIED_ACTIVE','VERIFIED_PAID','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `reservation_at` datetime NOT NULL,
  `reservation_end_at` datetime DEFAULT NULL,
  `outlet_id` bigint(20) unsigned NOT NULL,
  `sales_channel_id` bigint(20) unsigned DEFAULT NULL,
  `service_type` enum('DINE_IN','TAKE_AWAY','DELIVERY','PICKUP') NOT NULL DEFAULT 'DINE_IN',
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `customer_name` varchar(150) NOT NULL,
  `customer_phone` varchar(30) DEFAULT NULL,
  `customer_email` varchar(150) DEFAULT NULL,
  `guest_count` int(10) unsigned NOT NULL DEFAULT 1,
  `table_no` varchar(40) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `subtotal_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `service_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `deposit_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `deposit_applied_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `settlement_payment_id` bigint(20) unsigned DEFAULT NULL,
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `verified_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `rejected_by` bigint(20) unsigned DEFAULT NULL,
  `rejected_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `updated_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_reservation_no` (`reservation_no`) USING BTREE,
  UNIQUE KEY `uk_pos_reservation_order` (`order_id`) USING BTREE,
  UNIQUE KEY `uk_pos_reservation_settlement_payment` (`settlement_payment_id`) USING BTREE,
  KEY `idx_pos_reservation_status_time` (`status`,`reservation_at`) USING BTREE,
  KEY `idx_pos_reservation_outlet_time` (`outlet_id`,`reservation_at`) USING BTREE,
  KEY `idx_pos_reservation_member` (`member_id`) USING BTREE,
  KEY `idx_pos_reservation_created_by` (`created_by`) USING BTREE,
  KEY `idx_pos_reservation_created_by_user` (`created_by_user_id`) USING BTREE,
  KEY `idx_pos_reservation_updated_by_user` (`updated_by_user_id`) USING BTREE,
  KEY `fk_pos_reservation_sales_channel` (`sales_channel_id`) USING BTREE,
  KEY `fk_pos_reservation_verified_by` (`verified_by`) USING BTREE,
  KEY `fk_pos_reservation_rejected_by` (`rejected_by`) USING BTREE,
  KEY `fk_pos_reservation_cancelled_by` (`cancelled_by`) USING BTREE,
  CONSTRAINT `fk_pos_reservation_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `org_employee` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_reservation_created_by` FOREIGN KEY (`created_by`) REFERENCES `org_employee` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_reservation_member` FOREIGN KEY (`member_id`) REFERENCES `crm_member` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_reservation_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`),
  CONSTRAINT `fk_pos_reservation_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`),
  CONSTRAINT `fk_pos_reservation_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `org_employee` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_reservation_sales_channel` FOREIGN KEY (`sales_channel_id`) REFERENCES `pos_sales_channel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_reservation_settlement_payment` FOREIGN KEY (`settlement_payment_id`) REFERENCES `pos_payment` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_reservation_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `org_employee` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Reservasi customer sebelum diverifikasi dan dibentuk menjadi order POS';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_reservation_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` bigint(20) unsigned NOT NULL,
  `line_no` int(11) NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `bundle_id` bigint(20) unsigned DEFAULT NULL,
  `product_division_id_snapshot` bigint(20) unsigned DEFAULT NULL,
  `operational_division_id` bigint(20) unsigned DEFAULT NULL,
  `uom_id` bigint(20) unsigned DEFAULT NULL,
  `qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `hpp_standard_snapshot` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `hpp_live_snapshot` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `cogs_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `availability_mode_snapshot` enum('AUTO','FORCE_AVAILABLE','FORCE_OUT','MANUAL_ALLOWED') NOT NULL DEFAULT 'AUTO',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_reservation_line_no` (`reservation_id`,`line_no`) USING BTREE,
  KEY `idx_pos_reservation_line_product` (`product_id`) USING BTREE,
  KEY `idx_pos_reservation_line_bundle` (`bundle_id`) USING BTREE,
  KEY `idx_pos_reservation_line_operational_division` (`operational_division_id`) USING BTREE,
  KEY `fk_pos_reservation_line_product_division` (`product_division_id_snapshot`) USING BTREE,
  KEY `fk_pos_reservation_line_uom` (`uom_id`) USING BTREE,
  CONSTRAINT `fk_pos_reservation_line_bundle` FOREIGN KEY (`bundle_id`) REFERENCES `pos_product_bundle` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_reservation_line_header` FOREIGN KEY (`reservation_id`) REFERENCES `pos_reservation` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pos_reservation_line_operational_division` FOREIGN KEY (`operational_division_id`) REFERENCES `mst_operational_division` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_reservation_line_product` FOREIGN KEY (`product_id`) REFERENCES `mst_product` (`id`),
  CONSTRAINT `fk_pos_reservation_line_product_division` FOREIGN KEY (`product_division_id_snapshot`) REFERENCES `mst_product_division` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_reservation_line_uom` FOREIGN KEY (`uom_id`) REFERENCES `mst_uom` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Snapshot produk reservasi sebelum dibentuk menjadi order POS';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_reservation_line_extra` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` bigint(20) unsigned NOT NULL,
  `reservation_line_id` bigint(20) unsigned NOT NULL,
  `line_no` int(11) NOT NULL,
  `extra_id` bigint(20) unsigned NOT NULL,
  `qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `cost_amount_snapshot` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_reservation_line_extra_no` (`reservation_line_id`,`line_no`) USING BTREE,
  KEY `idx_pos_reservation_line_extra_header` (`reservation_id`) USING BTREE,
  KEY `idx_pos_reservation_line_extra_extra` (`extra_id`) USING BTREE,
  CONSTRAINT `fk_pos_reservation_line_extra_extra` FOREIGN KEY (`extra_id`) REFERENCES `mst_extra` (`id`),
  CONSTRAINT `fk_pos_reservation_line_extra_header` FOREIGN KEY (`reservation_id`) REFERENCES `pos_reservation` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pos_reservation_line_extra_line` FOREIGN KEY (`reservation_line_id`) REFERENCES `pos_reservation_line` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Snapshot extra reservasi';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_reservation_payment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` bigint(20) unsigned NOT NULL,
  `payment_id` bigint(20) unsigned NOT NULL,
  `link_status` enum('OPEN','PARTIAL','APPLIED','VOID') NOT NULL DEFAULT 'OPEN',
  `linked_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `applied_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `voided_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_reservation_payment_payment` (`payment_id`) USING BTREE,
  UNIQUE KEY `uk_pos_reservation_payment_pair` (`reservation_id`,`payment_id`) USING BTREE,
  KEY `idx_pos_reservation_payment_header` (`reservation_id`,`link_status`) USING BTREE,
  CONSTRAINT `fk_pos_reservation_payment_header` FOREIGN KEY (`reservation_id`) REFERENCES `pos_reservation` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pos_reservation_payment_payment` FOREIGN KEY (`payment_id`) REFERENCES `pos_payment` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tautan DP POS ke reservasi; sumber kas tetap pos_payment';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_reservation_state_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` bigint(20) unsigned NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) NOT NULL,
  `event_code` varchar(60) NOT NULL,
  `actor_employee_id` bigint(20) unsigned DEFAULT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pos_reservation_state_log_header` (`reservation_id`,`created_at`) USING BTREE,
  KEY `idx_pos_reservation_state_log_actor` (`actor_employee_id`) USING BTREE,
  KEY `idx_pos_reservation_state_log_user` (`actor_user_id`) USING BTREE,
  CONSTRAINT `fk_pos_reservation_state_log_actor` FOREIGN KEY (`actor_employee_id`) REFERENCES `org_employee` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_reservation_state_log_header` FOREIGN KEY (`reservation_id`) REFERENCES `pos_reservation` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Jejak perubahan reservasi, DP, verifikasi, penolakan, dan pembatalan';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_runtime_job` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_code` varchar(40) NOT NULL,
  `job_type` enum('ORDER_CONFIRM_STOCK_COMMIT') NOT NULL DEFAULT 'ORDER_CONFIRM_STOCK_COMMIT',
  `status` enum('QUEUED','PROCESSING','SUCCESS','FAILED','CANCELLED') NOT NULL DEFAULT 'QUEUED',
  `order_id` bigint(20) unsigned NOT NULL,
  `snapshot_id` bigint(20) unsigned NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `max_attempts` int(10) unsigned NOT NULL DEFAULT 3,
  `run_after` datetime NOT NULL DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `created_by_employee_id` bigint(20) unsigned DEFAULT NULL,
  `payload_json` longtext DEFAULT NULL,
  `result_json` longtext DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_runtime_job_code` (`job_code`) USING BTREE,
  KEY `idx_pos_runtime_job_queue` (`job_type`,`status`,`run_after`) USING BTREE,
  KEY `idx_pos_runtime_job_order` (`order_id`,`id`) USING BTREE,
  KEY `idx_pos_runtime_job_snapshot` (`snapshot_id`) USING BTREE,
  KEY `fk_pos_runtime_job_actor` (`created_by_employee_id`) USING BTREE,
  CONSTRAINT `fk_pos_runtime_job_actor` FOREIGN KEY (`created_by_employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_pos_runtime_job_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`),
  CONSTRAINT `fk_pos_runtime_job_snapshot` FOREIGN KEY (`snapshot_id`) REFERENCES `pos_stock_commit` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_sales_channel` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `channel_code` varchar(40) NOT NULL,
  `channel_name` varchar(120) NOT NULL,
  `service_type_default` enum('DINE_IN','TAKE_AWAY','DELIVERY','PICKUP') NOT NULL DEFAULT 'DINE_IN',
  `allowed_service_types` varchar(120) DEFAULT NULL,
  `marketplace_fee_percent` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_sales_channel_code` (`channel_code`) USING BTREE,
  KEY `idx_pos_sales_channel_active_sort` (`is_active`,`sort_order`,`channel_name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_self_order_qris_setting` (
  `id` tinyint(3) unsigned NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `midtrans_server_key` varchar(255) DEFAULT NULL,
  `midtrans_client_key` varchar(255) DEFAULT NULL,
  `midtrans_is_production` tinyint(1) NOT NULL DEFAULT 0,
  `payment_method_id` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pos_self_order_qris_payment_method` (`payment_method_id`) USING BTREE,
  CONSTRAINT `fk_pos_self_order_qris_payment_method` FOREIGN KEY (`payment_method_id`) REFERENCES `pos_payment_method` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_self_order_qr_secret` (
  `id` tinyint(3) unsigned NOT NULL,
  `secret` varchar(128) NOT NULL,
  `enforce` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_self_order_setting` (
  `id` tinyint(3) unsigned NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `member_base_url` varchar(255) NOT NULL DEFAULT 'http://localhost/member/',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_self_order_table` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nama_meja` varchar(100) NOT NULL,
  `qr_label` varchar(120) DEFAULT NULL,
  `capacity` int(10) unsigned NOT NULL DEFAULT 0,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pos_self_order_table_active` (`is_active`) USING BTREE,
  KEY `idx_pos_self_order_table_sort` (`sort_order`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_shift` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shift_no` varchar(40) NOT NULL,
  `outlet_id` bigint(20) unsigned NOT NULL,
  `terminal_id` bigint(20) unsigned DEFAULT NULL,
  `cashier_open_employee_id` bigint(20) unsigned NOT NULL,
  `cashier_close_employee_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('OPEN','CLOSED','VOID') NOT NULL DEFAULT 'OPEN',
  `opened_at` datetime NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  `opening_cash` decimal(18,2) NOT NULL DEFAULT 0.00,
  `expected_cash` decimal(18,2) NOT NULL DEFAULT 0.00,
  `actual_cash` decimal(18,2) NOT NULL DEFAULT 0.00,
  `variance_cash` decimal(18,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_shift_no` (`shift_no`) USING BTREE,
  KEY `idx_pos_shift_outlet_status` (`outlet_id`,`status`) USING BTREE,
  KEY `idx_pos_shift_terminal` (`terminal_id`) USING BTREE,
  KEY `fk_pos_shift_open_emp` (`cashier_open_employee_id`) USING BTREE,
  KEY `fk_pos_shift_close_emp` (`cashier_close_employee_id`) USING BTREE,
  CONSTRAINT `fk_pos_shift_close_emp` FOREIGN KEY (`cashier_close_employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_pos_shift_open_emp` FOREIGN KEY (`cashier_open_employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_pos_shift_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`),
  CONSTRAINT `fk_pos_shift_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `pos_terminal` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_shift_account_summary` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shift_id` bigint(20) unsigned NOT NULL,
  `company_account_id` bigint(20) unsigned DEFAULT NULL,
  `account_code` varchar(100) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `account_label` varchar(255) DEFAULT NULL,
  `gross_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `refund_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pos_shift_account_summary_shift` (`shift_id`) USING BTREE,
  KEY `idx_pos_shift_account_summary_account` (`company_account_id`) USING BTREE,
  CONSTRAINT `fk_pos_shift_account_summary_account` FOREIGN KEY (`company_account_id`) REFERENCES `fin_company_account` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_shift_account_summary_shift` FOREIGN KEY (`shift_id`) REFERENCES `pos_shift` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_shift_cash_denomination` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shift_id` bigint(20) unsigned NOT NULL,
  `denomination_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `qty_count` int(10) unsigned NOT NULL DEFAULT 0,
  `total_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_shift_cash_denomination_shift_denom` (`shift_id`,`denomination_amount`) USING BTREE,
  KEY `idx_pos_shift_cash_denom_shift` (`shift_id`) USING BTREE,
  CONSTRAINT `fk_pos_shift_cash_denom_shift` FOREIGN KEY (`shift_id`) REFERENCES `pos_shift` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_shift_summary` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shift_id` bigint(20) unsigned NOT NULL,
  `total_order_count` int(10) unsigned NOT NULL DEFAULT 0,
  `total_gross_sales` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_discount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_promo` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_net_sales` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_cash_sales` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_non_cash_sales` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_refund` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_void` decimal(18,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_shift_summary_shift` (`shift_id`) USING BTREE,
  CONSTRAINT `fk_pos_shift_summary_shift` FOREIGN KEY (`shift_id`) REFERENCES `pos_shift` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_stamp_campaign` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `campaign_code` varchar(40) NOT NULL,
  `campaign_name` varchar(120) NOT NULL,
  `earn_mode` enum('TXN','AMOUNT','PRODUCT') NOT NULL DEFAULT 'TXN',
  `amount_step` decimal(18,2) NOT NULL DEFAULT 0.00,
  `stamp_per_earn` decimal(18,4) NOT NULL DEFAULT 1.0000,
  `required_product_id` bigint(20) unsigned DEFAULT NULL,
  `redeem_required_stamp` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `stamp_expiry_days` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_stamp_campaign_code` (`campaign_code`) USING BTREE,
  KEY `idx_pos_stamp_campaign_product` (`required_product_id`) USING BTREE,
  CONSTRAINT `fk_pos_stamp_campaign_product` FOREIGN KEY (`required_product_id`) REFERENCES `mst_product` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_stamp_ledger` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `campaign_id` bigint(20) unsigned DEFAULT NULL,
  `ledger_type` enum('EARN','REDEEM','ADJUST','EXPIRE','REVERSE') NOT NULL DEFAULT 'EARN',
  `stamp_in` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `stamp_out` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `balance_after` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `expired_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pos_stamp_ledger_member` (`member_id`) USING BTREE,
  KEY `idx_pos_stamp_ledger_order` (`order_id`) USING BTREE,
  KEY `idx_pos_stamp_ledger_payment` (`payment_id`) USING BTREE,
  KEY `idx_pos_stamp_ledger_campaign` (`campaign_id`) USING BTREE,
  CONSTRAINT `fk_pos_stamp_ledger_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `pos_stamp_campaign` (`id`),
  CONSTRAINT `fk_pos_stamp_ledger_member` FOREIGN KEY (`member_id`) REFERENCES `crm_member` (`id`),
  CONSTRAINT `fk_pos_stamp_ledger_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`),
  CONSTRAINT `fk_pos_stamp_ledger_payment` FOREIGN KEY (`payment_id`) REFERENCES `pos_payment` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_stock_commit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `commit_no` varchar(40) NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `outlet_id` bigint(20) unsigned DEFAULT NULL,
  `terminal_id` bigint(20) unsigned DEFAULT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `cashier_session_id` bigint(20) unsigned DEFAULT NULL,
  `actor_employee_id` bigint(20) unsigned DEFAULT NULL,
  `commit_status` enum('DRAFT','QUEUED','PROCESSING','COMMITTED','FAILED','PARTIAL_REVERSED','REVERSED','VOID') NOT NULL DEFAULT 'DRAFT',
  `commit_reason` enum('ORDER_CONFIRM','VOID_REVERSAL','REFUND_REVERSAL','MANUAL') NOT NULL DEFAULT 'ORDER_CONFIRM',
  `process_state_snapshot` enum('NONE','PARTIAL','FULL') NOT NULL DEFAULT 'NONE',
  `committed_at` datetime DEFAULT NULL,
  `reversed_at` datetime DEFAULT NULL,
  `last_rebuild_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_stock_commit_no` (`commit_no`) USING BTREE,
  KEY `idx_pos_stock_commit_order` (`order_id`) USING BTREE,
  KEY `idx_pos_stock_commit_status` (`commit_status`) USING BTREE,
  KEY `fk_pos_stock_commit_outlet` (`outlet_id`) USING BTREE,
  KEY `fk_pos_stock_commit_terminal` (`terminal_id`) USING BTREE,
  KEY `fk_pos_stock_commit_shift` (`shift_id`) USING BTREE,
  KEY `fk_pos_stock_commit_session` (`cashier_session_id`) USING BTREE,
  KEY `fk_pos_stock_commit_actor` (`actor_employee_id`) USING BTREE,
  CONSTRAINT `fk_pos_stock_commit_actor` FOREIGN KEY (`actor_employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_pos_stock_commit_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`),
  CONSTRAINT `fk_pos_stock_commit_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`),
  CONSTRAINT `fk_pos_stock_commit_session` FOREIGN KEY (`cashier_session_id`) REFERENCES `pos_cashier_session` (`id`),
  CONSTRAINT `fk_pos_stock_commit_shift` FOREIGN KEY (`shift_id`) REFERENCES `pos_shift` (`id`),
  CONSTRAINT `fk_pos_stock_commit_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `pos_terminal` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_stock_commit_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `commit_id` bigint(20) unsigned NOT NULL,
  `line_no` int(11) NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `order_line_id` bigint(20) unsigned DEFAULT NULL,
  `order_line_extra_id` bigint(20) unsigned DEFAULT NULL,
  `line_type` enum('PRODUCT','EXTRA') NOT NULL DEFAULT 'PRODUCT',
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `extra_id` bigint(20) unsigned DEFAULT NULL,
  `source_kind` enum('MATERIAL','COMPONENT') NOT NULL,
  `source_role` enum('MAIN','SUPPORT','COMPLEMENT','OPTIONAL') NOT NULL DEFAULT 'MAIN',
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `component_id` bigint(20) unsigned DEFAULT NULL,
  `resolved_source_division_id` bigint(20) unsigned DEFAULT NULL,
  `resolved_source_division_code` varchar(30) DEFAULT NULL,
  `resolved_source_division_name` varchar(100) DEFAULT NULL,
  `source_name_snapshot` varchar(150) DEFAULT NULL,
  `required_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `required_uom_id` bigint(20) unsigned DEFAULT NULL,
  `committed_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `reversed_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_cost_live` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `total_cost_live` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `cost_source` enum('FIFO','LAST_LIVE','STANDARD_FALLBACK','MANUAL','DEFICIT_PENDING') NOT NULL DEFAULT 'FIFO',
  `movement_ref_type` enum('MATERIAL_LEDGER','COMPONENT_LEDGER','NONE','INVENTORY_DEFICIT') DEFAULT NULL,
  `movement_ref_id` bigint(20) unsigned DEFAULT NULL,
  `return_policy` enum('RETURN_TO_STOCK','ADJUSTMENT_ONLY','NO_RETURN') NOT NULL DEFAULT 'RETURN_TO_STOCK',
  `reversal_status` enum('NONE','RETURNED','ADJUSTED','SKIPPED') NOT NULL DEFAULT 'NONE',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_stock_commit_line_no` (`commit_id`,`line_no`) USING BTREE,
  KEY `idx_pos_stock_commit_line_order` (`order_id`) USING BTREE,
  KEY `idx_pos_stock_commit_line_order_line` (`order_line_id`) USING BTREE,
  KEY `idx_pos_stock_commit_line_extra` (`order_line_extra_id`) USING BTREE,
  KEY `idx_pos_stock_commit_line_material` (`material_id`) USING BTREE,
  KEY `idx_pos_stock_commit_line_component` (`component_id`) USING BTREE,
  KEY `fk_pos_stock_commit_line_product` (`product_id`) USING BTREE,
  KEY `fk_pos_stock_commit_line_extra_master` (`extra_id`) USING BTREE,
  KEY `fk_pos_stock_commit_line_uom` (`required_uom_id`) USING BTREE,
  KEY `idx_pos_stock_commit_line_source_division` (`resolved_source_division_id`) USING BTREE,
  KEY `idx_pos_stock_commit_line_commit_source` (`commit_id`,`resolved_source_division_id`,`material_id`,`component_id`) USING BTREE,
  CONSTRAINT `fk_pos_stock_commit_line_commit` FOREIGN KEY (`commit_id`) REFERENCES `pos_stock_commit` (`id`),
  CONSTRAINT `fk_pos_stock_commit_line_component` FOREIGN KEY (`component_id`) REFERENCES `mst_component` (`id`),
  CONSTRAINT `fk_pos_stock_commit_line_extra_master` FOREIGN KEY (`extra_id`) REFERENCES `mst_extra` (`id`),
  CONSTRAINT `fk_pos_stock_commit_line_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`),
  CONSTRAINT `fk_pos_stock_commit_line_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`),
  CONSTRAINT `fk_pos_stock_commit_line_order_extra` FOREIGN KEY (`order_line_extra_id`) REFERENCES `pos_order_line_extra` (`id`),
  CONSTRAINT `fk_pos_stock_commit_line_order_line` FOREIGN KEY (`order_line_id`) REFERENCES `pos_order_line` (`id`),
  CONSTRAINT `fk_pos_stock_commit_line_product` FOREIGN KEY (`product_id`) REFERENCES `mst_product` (`id`),
  CONSTRAINT `fk_pos_stock_commit_line_uom` FOREIGN KEY (`required_uom_id`) REFERENCES `mst_uom` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_terminal` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` bigint(20) unsigned NOT NULL,
  `terminal_code` varchar(40) NOT NULL,
  `terminal_name` varchar(120) NOT NULL,
  `device_key` varchar(120) DEFAULT NULL,
  `os_type` enum('WINDOWS','UBUNTU','ANDROID','IOS','WEB','OTHER') NOT NULL DEFAULT 'WEB',
  `app_platform` enum('DESKTOP','WEB','ANDROID','IOS','OTHER') NOT NULL DEFAULT 'DESKTOP',
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_terminal_code` (`terminal_code`) USING BTREE,
  UNIQUE KEY `uk_pos_terminal_device_key` (`device_key`) USING BTREE,
  KEY `idx_pos_terminal_outlet` (`outlet_id`) USING BTREE,
  CONSTRAINT `fk_pos_terminal_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_void` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `void_no` varchar(40) NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `cashier_session_id` bigint(20) unsigned DEFAULT NULL,
  `outlet_id` bigint(20) unsigned DEFAULT NULL,
  `terminal_id` bigint(20) unsigned DEFAULT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `actor_employee_id` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `void_scope` enum('FULL','PARTIAL') NOT NULL DEFAULT 'PARTIAL',
  `processed_state` enum('NOT_PROCESSED','PROCESSED') NOT NULL DEFAULT 'NOT_PROCESSED',
  `return_to_stock` tinyint(1) NOT NULL DEFAULT 0,
  `adjustment_mode` enum('NONE','AUTO_WASTE','AUTO_SPOIL','AUTO_ADJUSTMENT') NOT NULL DEFAULT 'NONE',
  `order_status_before` varchar(30) NOT NULL DEFAULT 'PENDING',
  `order_status_after` varchar(30) NOT NULL DEFAULT 'VOID',
  `order_no_snapshot` varchar(40) NOT NULL,
  `member_name_snapshot` varchar(160) DEFAULT NULL,
  `service_type_snapshot` varchar(30) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `line_count` int(10) unsigned NOT NULL DEFAULT 0,
  `extra_count` int(10) unsigned NOT NULL DEFAULT 0,
  `total_qty_void` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `amount_void` decimal(18,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_void_no` (`void_no`) USING BTREE,
  KEY `idx_pos_void_order` (`order_id`) USING BTREE,
  KEY `fk_pos_void_payment` (`payment_id`) USING BTREE,
  KEY `fk_pos_void_session` (`cashier_session_id`) USING BTREE,
  KEY `fk_pos_void_outlet` (`outlet_id`) USING BTREE,
  KEY `fk_pos_void_terminal` (`terminal_id`) USING BTREE,
  KEY `fk_pos_void_shift` (`shift_id`) USING BTREE,
  KEY `fk_pos_void_member` (`member_id`) USING BTREE,
  KEY `fk_pos_void_actor` (`actor_employee_id`) USING BTREE,
  KEY `fk_pos_void_approved_by` (`approved_by`) USING BTREE,
  CONSTRAINT `fk_pos_void_actor` FOREIGN KEY (`actor_employee_id`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_pos_void_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `org_employee` (`id`),
  CONSTRAINT `fk_pos_void_member` FOREIGN KEY (`member_id`) REFERENCES `crm_member` (`id`),
  CONSTRAINT `fk_pos_void_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`),
  CONSTRAINT `fk_pos_void_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`),
  CONSTRAINT `fk_pos_void_payment` FOREIGN KEY (`payment_id`) REFERENCES `pos_payment` (`id`),
  CONSTRAINT `fk_pos_void_session` FOREIGN KEY (`cashier_session_id`) REFERENCES `pos_cashier_session` (`id`),
  CONSTRAINT `fk_pos_void_shift` FOREIGN KEY (`shift_id`) REFERENCES `pos_shift` (`id`),
  CONSTRAINT `fk_pos_void_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `pos_terminal` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_void_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `void_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `order_line_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `line_no_snapshot` int(11) NOT NULL DEFAULT 0,
  `item_name_snapshot` varchar(255) NOT NULL,
  `qty_before` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_void` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_after` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `subtotal_void` decimal(18,2) NOT NULL DEFAULT 0.00,
  `hpp_live_snapshot` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `line_process_state` enum('NOT_PROCESSED','PROCESSED') NOT NULL DEFAULT 'NOT_PROCESSED',
  `line_status_after` varchar(20) NOT NULL DEFAULT 'OPEN',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pos_void_line_void` (`void_id`) USING BTREE,
  KEY `fk_pos_void_line_order` (`order_id`) USING BTREE,
  KEY `fk_pos_void_line_order_line` (`order_line_id`) USING BTREE,
  KEY `fk_pos_void_line_product` (`product_id`) USING BTREE,
  CONSTRAINT `fk_pos_void_line_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`),
  CONSTRAINT `fk_pos_void_line_order_line` FOREIGN KEY (`order_line_id`) REFERENCES `pos_order_line` (`id`),
  CONSTRAINT `fk_pos_void_line_product` FOREIGN KEY (`product_id`) REFERENCES `mst_product` (`id`),
  CONSTRAINT `fk_pos_void_line_void` FOREIGN KEY (`void_id`) REFERENCES `pos_void` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_void_line_extra` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `void_id` bigint(20) unsigned NOT NULL,
  `void_line_id` bigint(20) unsigned DEFAULT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `order_line_id` bigint(20) unsigned NOT NULL,
  `order_line_extra_id` bigint(20) unsigned NOT NULL,
  `extra_id` bigint(20) unsigned DEFAULT NULL,
  `extra_name_snapshot` varchar(255) NOT NULL,
  `qty_per_unit` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `line_qty_affected` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `subtotal_void` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status_after` varchar(20) NOT NULL DEFAULT 'OPEN',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pos_void_extra_void` (`void_id`) USING BTREE,
  KEY `fk_pos_void_line_extra_void_line` (`void_line_id`) USING BTREE,
  KEY `fk_pos_void_line_extra_order` (`order_id`) USING BTREE,
  KEY `fk_pos_void_line_extra_line` (`order_line_id`) USING BTREE,
  KEY `fk_pos_void_line_extra_line_extra` (`order_line_extra_id`) USING BTREE,
  KEY `fk_pos_void_line_extra_extra` (`extra_id`) USING BTREE,
  CONSTRAINT `fk_pos_void_line_extra_extra` FOREIGN KEY (`extra_id`) REFERENCES `mst_extra` (`id`),
  CONSTRAINT `fk_pos_void_line_extra_line` FOREIGN KEY (`order_line_id`) REFERENCES `pos_order_line` (`id`),
  CONSTRAINT `fk_pos_void_line_extra_line_extra` FOREIGN KEY (`order_line_extra_id`) REFERENCES `pos_order_line_extra` (`id`),
  CONSTRAINT `fk_pos_void_line_extra_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`),
  CONSTRAINT `fk_pos_void_line_extra_void` FOREIGN KEY (`void_id`) REFERENCES `pos_void` (`id`),
  CONSTRAINT `fk_pos_void_line_extra_void_line` FOREIGN KEY (`void_line_id`) REFERENCES `pos_void_line` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_voucher_campaign` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `campaign_code` varchar(40) NOT NULL,
  `campaign_name` varchar(120) NOT NULL,
  `issue_mode` enum('PUBLIC','AUTO_FROM_TXN','MEMBER_TARGETED','MANUAL') NOT NULL DEFAULT 'PUBLIC',
  `voucher_type` enum('AMOUNT','PERCENT','FREE_PRODUCT') NOT NULL DEFAULT 'AMOUNT',
  `discount_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `max_discount_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `min_spend_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `trigger_product_id` bigint(20) unsigned DEFAULT NULL,
  `free_product_id` bigint(20) unsigned DEFAULT NULL,
  `free_qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `valid_day_count` int(10) unsigned NOT NULL DEFAULT 0,
  `point_cost` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `stamp_cost` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_voucher_campaign_code` (`campaign_code`) USING BTREE,
  KEY `idx_pos_voucher_campaign_trigger_product` (`trigger_product_id`) USING BTREE,
  KEY `idx_pos_voucher_campaign_free_product` (`free_product_id`) USING BTREE,
  CONSTRAINT `fk_pos_voucher_campaign_free_product` FOREIGN KEY (`free_product_id`) REFERENCES `mst_product` (`id`),
  CONSTRAINT `fk_pos_voucher_campaign_trigger_product` FOREIGN KEY (`trigger_product_id`) REFERENCES `mst_product` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_voucher_issue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `voucher_issue_no` varchar(40) NOT NULL,
  `campaign_id` bigint(20) unsigned DEFAULT NULL,
  `redeem_rule_id` bigint(20) unsigned DEFAULT NULL,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `source_order_id` bigint(20) unsigned DEFAULT NULL,
  `source_payment_id` bigint(20) unsigned DEFAULT NULL,
  `voucher_code` varchar(60) NOT NULL,
  `voucher_status` enum('OPEN','REDEEMED','EXPIRED','VOID') NOT NULL DEFAULT 'OPEN',
  `amount_snapshot` decimal(18,2) NOT NULL DEFAULT 0.00,
  `percent_snapshot` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `min_spend_amount` decimal(14,2) DEFAULT NULL,
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expired_at` datetime DEFAULT NULL,
  `redeemed_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_voucher_issue_no` (`voucher_issue_no`) USING BTREE,
  UNIQUE KEY `uk_pos_voucher_issue_code` (`voucher_code`) USING BTREE,
  KEY `idx_pos_voucher_issue_member` (`member_id`) USING BTREE,
  KEY `idx_pos_voucher_issue_campaign` (`campaign_id`) USING BTREE,
  KEY `idx_pos_voucher_issue_status` (`voucher_status`) USING BTREE,
  KEY `fk_pos_voucher_issue_order` (`source_order_id`) USING BTREE,
  KEY `fk_pos_voucher_issue_payment` (`source_payment_id`) USING BTREE,
  CONSTRAINT `fk_pos_voucher_issue_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `pos_voucher_campaign` (`id`),
  CONSTRAINT `fk_pos_voucher_issue_member` FOREIGN KEY (`member_id`) REFERENCES `crm_member` (`id`),
  CONSTRAINT `fk_pos_voucher_issue_order` FOREIGN KEY (`source_order_id`) REFERENCES `pos_order` (`id`),
  CONSTRAINT `fk_pos_voucher_issue_payment` FOREIGN KEY (`source_payment_id`) REFERENCES `pos_payment` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_voucher_redemption` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `voucher_issue_id` bigint(20) unsigned NOT NULL,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `redeem_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `redeemed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pos_voucher_redemption_issue` (`voucher_issue_id`) USING BTREE,
  KEY `idx_pos_voucher_redemption_member` (`member_id`) USING BTREE,
  KEY `idx_pos_voucher_redemption_order` (`order_id`) USING BTREE,
  KEY `idx_pos_voucher_redemption_payment` (`payment_id`) USING BTREE,
  CONSTRAINT `fk_pos_voucher_redemption_issue` FOREIGN KEY (`voucher_issue_id`) REFERENCES `pos_voucher_issue` (`id`),
  CONSTRAINT `fk_pos_voucher_redemption_member` FOREIGN KEY (`member_id`) REFERENCES `crm_member` (`id`),
  CONSTRAINT `fk_pos_voucher_redemption_order` FOREIGN KEY (`order_id`) REFERENCES `pos_order` (`id`),
  CONSTRAINT `fk_pos_voucher_redemption_payment` FOREIGN KEY (`payment_id`) REFERENCES `pos_payment` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_voucher_usage` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_key` varchar(100) NOT NULL,
  `voucher_kind` enum('ISSUE','CAMPAIGN') NOT NULL DEFAULT 'ISSUE',
  `voucher_issue_id` bigint(20) unsigned DEFAULT NULL,
  `campaign_id` bigint(20) unsigned DEFAULT NULL,
  `voucher_code` varchar(60) DEFAULT NULL,
  `voucher_label` varchar(150) DEFAULT NULL,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `cashier_employee_id` bigint(20) unsigned DEFAULT NULL,
  `face_value_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `face_value_percent` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `applied_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `usage_status` enum('APPLIED','REVERSED','VOID') NOT NULL DEFAULT 'APPLIED',
  `used_at` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pos_voucher_usage_payment_source` (`payment_id`,`source_key`) USING BTREE,
  KEY `idx_pos_voucher_usage_used_at` (`used_at`,`usage_status`) USING BTREE,
  KEY `idx_pos_voucher_usage_issue` (`voucher_issue_id`,`used_at`) USING BTREE,
  KEY `idx_pos_voucher_usage_campaign` (`campaign_id`,`used_at`) USING BTREE,
  KEY `idx_pos_voucher_usage_order` (`order_id`) USING BTREE,
  KEY `idx_pos_voucher_usage_member` (`member_id`) USING BTREE,
  KEY `idx_pos_voucher_usage_cashier` (`cashier_employee_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pr_meja` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nama_meja` varchar(100) NOT NULL,
  `qr_label` varchar(120) DEFAULT NULL,
  `capacity` int(10) unsigned NOT NULL DEFAULT 0,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pr_meja_active` (`is_active`) USING BTREE,
  KEY `idx_pr_meja_sort` (`sort_order`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pr_qris_setting` (
  `id` tinyint(3) unsigned NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `midtrans_server_key` varchar(255) DEFAULT NULL,
  `midtrans_client_key` varchar(255) DEFAULT NULL,
  `midtrans_is_production` tinyint(1) NOT NULL DEFAULT 0,
  `payment_method_id` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pr_qris_payment_method` (`payment_method_id`) USING BTREE,
  CONSTRAINT `fk_pr_qris_payment_method` FOREIGN KEY (`payment_method_id`) REFERENCES `pos_payment_method` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pr_table_qr_secret` (
  `id` tinyint(3) unsigned NOT NULL,
  `secret` varchar(128) NOT NULL,
  `enforce` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pur_division_request` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_no` varchar(60) NOT NULL,
  `request_date` date NOT NULL,
  `needed_date` date DEFAULT NULL,
  `division_id` bigint(20) unsigned NOT NULL,
  `destination_type` enum('BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  `status` enum('DRAFT','SUBMITTED','VERIFIED','REJECTED','VOID') NOT NULL DEFAULT 'SUBMITTED',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pur_division_request_no` (`request_no`) USING BTREE,
  KEY `idx_pur_division_request_date` (`request_date`) USING BTREE,
  KEY `idx_pur_division_request_status` (`status`) USING BTREE,
  KEY `idx_pur_division_request_div` (`division_id`) USING BTREE,
  KEY `fk_pur_division_request_user` (`created_by`) USING BTREE,
  KEY `idx_pur_division_request_dest` (`destination_type`) USING BTREE,
  CONSTRAINT `fk_pur_division_request_div` FOREIGN KEY (`division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_pur_division_request_user` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pur_division_request_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned NOT NULL,
  `line_no` int(10) unsigned NOT NULL,
  `line_kind` enum('ITEM','MATERIAL') DEFAULT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `profile_key` char(64) NOT NULL,
  `profile_name` varchar(150) DEFAULT NULL,
  `profile_brand` varchar(120) DEFAULT NULL,
  `profile_description` varchar(255) DEFAULT NULL,
  `profile_expired_date` date DEFAULT NULL,
  `expiry_policy` varchar(32) DEFAULT NULL,
  `required_expiry_date` date DEFAULT NULL,
  `min_remaining_days` int(11) DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned NOT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `profile_content_per_buy` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `profile_buy_uom_code` varchar(40) DEFAULT NULL,
  `profile_content_uom_code` varchar(40) DEFAULT NULL,
  `request_uom_mode` enum('BUY','CONTENT') NOT NULL DEFAULT 'BUY',
  `vendor_id` bigint(20) unsigned DEFAULT NULL,
  `qty_buy_requested` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_content_requested` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_content_available_snapshot` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `routed_to` enum('SR','PO','MIXED') NOT NULL DEFAULT 'PO',
  `qty_content_to_sr` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_content_to_po` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `usage_purpose` varchar(20) NOT NULL DEFAULT 'BAHAN_BAKU',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pur_division_request_line_no` (`request_id`,`line_no`) USING BTREE,
  KEY `idx_pur_division_request_line_item` (`item_id`) USING BTREE,
  KEY `idx_pur_division_request_line_material` (`material_id`) USING BTREE,
  KEY `fk_pur_division_request_line_buy_uom` (`buy_uom_id`) USING BTREE,
  KEY `fk_pur_division_request_line_content_uom` (`content_uom_id`) USING BTREE,
  KEY `idx_pur_division_request_line_vendor` (`vendor_id`) USING BTREE,
  CONSTRAINT `fk_pur_division_request_line_buy_uom` FOREIGN KEY (`buy_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_pur_division_request_line_content_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_pur_division_request_line_header` FOREIGN KEY (`request_id`) REFERENCES `pur_division_request` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pur_division_request_line_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_pur_division_request_line_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`),
  CONSTRAINT `fk_pur_division_request_line_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `mst_vendor` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pur_division_request_link` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned NOT NULL,
  `doc_type` enum('SR','PO') NOT NULL,
  `doc_id` bigint(20) unsigned NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pur_div_req_link_req` (`request_id`) USING BTREE,
  KEY `idx_pur_div_req_link_doc` (`doc_type`,`doc_id`) USING BTREE,
  CONSTRAINT `fk_pur_div_req_link_req` FOREIGN KEY (`request_id`) REFERENCES `pur_division_request` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pur_purchase_order` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `po_no` varchar(50) NOT NULL,
  `request_date` date NOT NULL,
  `expected_date` date DEFAULT NULL,
  `purchase_type_id` bigint(20) unsigned NOT NULL,
  `destination_type` enum('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  `destination_division_id` bigint(20) unsigned DEFAULT NULL,
  `vendor_id` bigint(20) unsigned DEFAULT NULL,
  `payment_account_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('DRAFT','APPROVED','ORDERED','REJECTED','PARTIAL_RECEIVED','RECEIVED','PAID','VOID') NOT NULL DEFAULT 'DRAFT',
  `currency_code` varchar(10) NOT NULL DEFAULT 'IDR',
  `exchange_rate` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `external_ref_no` varchar(80) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pur_purchase_order_no` (`po_no`) USING BTREE,
  KEY `idx_pur_purchase_order_date` (`request_date`) USING BTREE,
  KEY `idx_pur_purchase_order_type` (`purchase_type_id`) USING BTREE,
  KEY `idx_pur_purchase_order_vendor` (`vendor_id`) USING BTREE,
  KEY `idx_pur_purchase_order_status` (`status`) USING BTREE,
  KEY `idx_pur_purchase_order_destination_div` (`destination_division_id`) USING BTREE,
  KEY `idx_pur_purchase_order_payment_account` (`payment_account_id`) USING BTREE,
  CONSTRAINT `fk_pur_purchase_order_destination_div` FOREIGN KEY (`destination_division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_pur_purchase_order_payment_account` FOREIGN KEY (`payment_account_id`) REFERENCES `fin_company_account` (`id`),
  CONSTRAINT `fk_pur_purchase_order_type` FOREIGN KEY (`purchase_type_id`) REFERENCES `mst_purchase_type` (`id`),
  CONSTRAINT `fk_pur_purchase_order_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `mst_vendor` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pur_purchase_order_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint(20) unsigned NOT NULL,
  `line_no` int(10) unsigned NOT NULL,
  `line_kind` enum('ITEM','MATERIAL','SERVICE','ASSET') DEFAULT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `line_description` varchar(255) DEFAULT NULL,
  `expired_date` date DEFAULT NULL,
  `expiry_policy` varchar(32) DEFAULT NULL,
  `required_expiry_date` date DEFAULT NULL,
  `min_remaining_days` int(11) DEFAULT NULL,
  `brand_name` varchar(120) DEFAULT NULL,
  `qty_buy` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `buy_uom_id` bigint(20) unsigned NOT NULL,
  `content_per_buy` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `qty_content` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `content_uom_id` bigint(20) unsigned DEFAULT NULL,
  `conversion_factor_to_content` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `unit_price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(9,4) NOT NULL DEFAULT 0.0000,
  `tax_percent` decimal(9,4) NOT NULL DEFAULT 0.0000,
  `line_subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `snapshot_item_name` varchar(150) DEFAULT NULL,
  `snapshot_material_name` varchar(150) DEFAULT NULL,
  `snapshot_brand_name` varchar(120) DEFAULT NULL,
  `snapshot_line_description` varchar(255) DEFAULT NULL,
  `snapshot_expired_date` date DEFAULT NULL,
  `snapshot_buy_uom_code` varchar(40) DEFAULT NULL,
  `snapshot_content_uom_code` varchar(40) DEFAULT NULL,
  `profile_key` char(64) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `usage_purpose` varchar(20) NOT NULL DEFAULT 'BAHAN_BAKU',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pur_purchase_order_line_no` (`purchase_order_id`,`line_no`) USING BTREE,
  KEY `idx_pur_purchase_order_line_po` (`purchase_order_id`) USING BTREE,
  KEY `idx_pur_purchase_order_line_item` (`item_id`) USING BTREE,
  KEY `idx_pur_purchase_order_line_material` (`material_id`) USING BTREE,
  KEY `idx_pur_purchase_order_line_buy_uom` (`buy_uom_id`) USING BTREE,
  KEY `idx_pur_purchase_order_line_content_uom` (`content_uom_id`) USING BTREE,
  KEY `idx_pur_purchase_order_line_profile_key` (`profile_key`) USING BTREE,
  CONSTRAINT `fk_pur_purchase_order_line_buy_uom` FOREIGN KEY (`buy_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_pur_purchase_order_line_content_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_pur_purchase_order_line_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_pur_purchase_order_line_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`),
  CONSTRAINT `fk_pur_purchase_order_line_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `pur_purchase_order` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pur_purchase_payment_plan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint(20) unsigned NOT NULL,
  `payment_method_id` bigint(20) unsigned DEFAULT NULL,
  `paid_from_account_id` bigint(20) unsigned DEFAULT NULL,
  `plan_type` enum('DP','PARTIAL','FULL') NOT NULL DEFAULT 'FULL',
  `terms_days` int(10) unsigned NOT NULL DEFAULT 0,
  `due_date` date DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `planned_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('PLANNED','PARTIAL','PAID','VOID') NOT NULL DEFAULT 'PLANNED',
  `reference_no` varchar(80) DEFAULT NULL,
  `transaction_no` varchar(80) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pur_payment_plan_po` (`purchase_order_id`) USING BTREE,
  KEY `idx_pur_payment_plan_method` (`payment_method_id`) USING BTREE,
  KEY `idx_pur_payment_plan_paid_account` (`paid_from_account_id`) USING BTREE,
  KEY `idx_pur_payment_plan_txn_no` (`transaction_no`) USING BTREE,
  KEY `idx_pur_payment_plan_due` (`due_date`) USING BTREE,
  KEY `idx_pur_payment_plan_status` (`status`) USING BTREE,
  CONSTRAINT `fk_pur_payment_plan_paid_account` FOREIGN KEY (`paid_from_account_id`) REFERENCES `fin_company_account` (`id`),
  CONSTRAINT `fk_pur_payment_plan_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `pur_purchase_order` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pur_purchase_receipt` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(50) NOT NULL,
  `purchase_order_id` bigint(20) unsigned NOT NULL,
  `receipt_date` date NOT NULL,
  `destination_type` enum('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  `destination_division_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('DRAFT','POSTED','VOID') NOT NULL DEFAULT 'DRAFT',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pur_purchase_receipt_no` (`receipt_no`) USING BTREE,
  KEY `idx_pur_purchase_receipt_po` (`purchase_order_id`) USING BTREE,
  KEY `idx_pur_purchase_receipt_date` (`receipt_date`) USING BTREE,
  KEY `idx_pur_purchase_receipt_destination_div` (`destination_division_id`) USING BTREE,
  KEY `idx_pur_purchase_receipt_status` (`status`) USING BTREE,
  CONSTRAINT `fk_pur_purchase_receipt_destination_div` FOREIGN KEY (`destination_division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_pur_purchase_receipt_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `pur_purchase_order` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pur_purchase_receipt_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_receipt_id` bigint(20) unsigned NOT NULL,
  `purchase_order_line_id` bigint(20) unsigned NOT NULL,
  `line_kind` enum('ITEM','MATERIAL','SERVICE','ASSET') DEFAULT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `qty_buy_received` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `buy_uom_id` bigint(20) unsigned NOT NULL,
  `qty_content_received` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `content_uom_id` bigint(20) unsigned DEFAULT NULL,
  `conversion_factor_to_content` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `brand_name` varchar(120) DEFAULT NULL,
  `line_description` varchar(255) DEFAULT NULL,
  `expired_date` date DEFAULT NULL,
  `profile_key` char(64) DEFAULT NULL,
  `lot_id` bigint(20) unsigned DEFAULT NULL,
  `lot_no` varchar(80) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `usage_purpose` varchar(20) NOT NULL DEFAULT 'BAHAN_BAKU',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pur_purchase_receipt_line_po_line` (`purchase_receipt_id`,`purchase_order_line_id`) USING BTREE,
  KEY `idx_pur_purchase_receipt_line_receipt` (`purchase_receipt_id`) USING BTREE,
  KEY `idx_pur_purchase_receipt_line_po_line` (`purchase_order_line_id`) USING BTREE,
  KEY `idx_pur_purchase_receipt_line_item` (`item_id`) USING BTREE,
  KEY `idx_pur_purchase_receipt_line_material` (`material_id`) USING BTREE,
  KEY `idx_pur_purchase_receipt_line_profile` (`profile_key`) USING BTREE,
  KEY `fk_pur_purchase_receipt_line_buy_uom` (`buy_uom_id`) USING BTREE,
  KEY `fk_pur_purchase_receipt_line_content_uom` (`content_uom_id`) USING BTREE,
  CONSTRAINT `fk_pur_purchase_receipt_line_buy_uom` FOREIGN KEY (`buy_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_pur_purchase_receipt_line_content_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_pur_purchase_receipt_line_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_pur_purchase_receipt_line_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`),
  CONSTRAINT `fk_pur_purchase_receipt_line_po_line` FOREIGN KEY (`purchase_order_line_id`) REFERENCES `pur_purchase_order_line` (`id`),
  CONSTRAINT `fk_pur_purchase_receipt_line_receipt` FOREIGN KEY (`purchase_receipt_id`) REFERENCES `pur_purchase_receipt` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pur_purchase_txn_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint(20) unsigned NOT NULL,
  `purchase_receipt_id` bigint(20) unsigned DEFAULT NULL,
  `payment_plan_id` bigint(20) unsigned DEFAULT NULL,
  `action_code` varchar(40) NOT NULL,
  `status_before` varchar(30) DEFAULT NULL,
  `status_after` varchar(30) DEFAULT NULL,
  `transaction_no` varchar(80) DEFAULT NULL,
  `ref_table` varchar(80) DEFAULT NULL,
  `ref_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(18,2) DEFAULT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_purchase_txn_po` (`purchase_order_id`) USING BTREE,
  KEY `idx_purchase_txn_receipt` (`purchase_receipt_id`) USING BTREE,
  KEY `idx_purchase_txn_payment` (`payment_plan_id`) USING BTREE,
  KEY `idx_purchase_txn_action` (`action_code`) USING BTREE,
  KEY `idx_purchase_txn_created` (`created_at`) USING BTREE,
  KEY `idx_purchase_txn_ref` (`ref_table`,`ref_id`) USING BTREE,
  CONSTRAINT `fk_purchase_txn_payment` FOREIGN KEY (`payment_plan_id`) REFERENCES `pur_purchase_payment_plan` (`id`),
  CONSTRAINT `fk_purchase_txn_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `pur_purchase_order` (`id`),
  CONSTRAINT `fk_purchase_txn_receipt` FOREIGN KEY (`purchase_receipt_id`) REFERENCES `pur_purchase_receipt` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pur_store_request` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sr_no` varchar(50) NOT NULL,
  `request_date` date NOT NULL,
  `needed_date` date DEFAULT NULL,
  `request_division_id` bigint(20) unsigned NOT NULL,
  `destination_type` enum('BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  `status` enum('DRAFT','SUBMITTED','APPROVED','REJECTED','PARTIAL_FULFILLED','FULFILLED','VOID') NOT NULL DEFAULT 'DRAFT',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `voided_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pur_store_request_no` (`sr_no`) USING BTREE,
  KEY `idx_pur_store_request_date` (`request_date`) USING BTREE,
  KEY `idx_pur_store_request_status` (`status`) USING BTREE,
  KEY `idx_pur_store_request_div_dest` (`request_division_id`,`destination_type`) USING BTREE,
  KEY `fk_pur_store_request_created_by` (`created_by`) USING BTREE,
  KEY `fk_pur_store_request_approved_by` (`approved_by`) USING BTREE,
  KEY `fk_pur_store_request_voided_by` (`voided_by`) USING BTREE,
  CONSTRAINT `fk_pur_store_request_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pur_store_request_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pur_store_request_division` FOREIGN KEY (`request_division_id`) REFERENCES `mst_operational_division` (`id`),
  CONSTRAINT `fk_pur_store_request_voided_by` FOREIGN KEY (`voided_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pur_store_request_approval` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_request_id` bigint(20) unsigned NOT NULL,
  `action` enum('SUBMIT','APPROVE','REJECT','OVERRIDE_APPROVE','VOID') NOT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `actor_name_snapshot` varchar(150) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_pur_store_request_approval_req_time` (`store_request_id`,`created_at`) USING BTREE,
  KEY `fk_pur_store_request_approval_actor` (`actor_user_id`) USING BTREE,
  CONSTRAINT `fk_pur_store_request_approval_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pur_store_request_approval_header` FOREIGN KEY (`store_request_id`) REFERENCES `pur_store_request` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pur_store_request_fulfillment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_request_id` bigint(20) unsigned NOT NULL,
  `fulfillment_no` varchar(60) NOT NULL,
  `fulfillment_date` date NOT NULL,
  `status` enum('DRAFT','POSTED','VOID') NOT NULL DEFAULT 'DRAFT',
  `notes` varchar(255) DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `voided_by` bigint(20) unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pur_store_request_fulfillment_no` (`fulfillment_no`) USING BTREE,
  KEY `idx_pur_store_request_fulfillment_req` (`store_request_id`) USING BTREE,
  KEY `fk_pur_store_request_fulfillment_posted_by` (`posted_by`) USING BTREE,
  KEY `fk_pur_store_request_fulfillment_voided_by` (`voided_by`) USING BTREE,
  CONSTRAINT `fk_pur_store_request_fulfillment_header` FOREIGN KEY (`store_request_id`) REFERENCES `pur_store_request` (`id`),
  CONSTRAINT `fk_pur_store_request_fulfillment_posted_by` FOREIGN KEY (`posted_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pur_store_request_fulfillment_voided_by` FOREIGN KEY (`voided_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pur_store_request_fulfillment_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fulfillment_id` bigint(20) unsigned NOT NULL,
  `store_request_line_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `profile_key` char(64) NOT NULL,
  `profile_name` varchar(150) DEFAULT NULL,
  `profile_brand` varchar(120) DEFAULT NULL,
  `profile_description` varchar(255) DEFAULT NULL,
  `profile_expired_date` date DEFAULT NULL,
  `expiry_policy` varchar(32) DEFAULT NULL,
  `required_expiry_date` date DEFAULT NULL,
  `min_remaining_days` int(11) DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned NOT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `profile_content_per_buy` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `profile_buy_uom_code` varchar(40) DEFAULT NULL,
  `profile_content_uom_code` varchar(40) DEFAULT NULL,
  `qty_buy_posted` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_content_posted` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_cost_snapshot` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `fifo_issue_id` bigint(20) unsigned DEFAULT NULL,
  `fifo_issue_no` varchar(60) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `usage_purpose` varchar(20) NOT NULL DEFAULT 'BAHAN_BAKU',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pur_sr_fulfillment_line` (`fulfillment_id`,`store_request_line_id`) USING BTREE,
  KEY `idx_pur_sr_fulfillment_line_profile` (`profile_key`,`buy_uom_id`,`content_uom_id`) USING BTREE,
  KEY `fk_pur_sr_fulfillment_line_sr_line` (`store_request_line_id`) USING BTREE,
  KEY `fk_pur_sr_fulfillment_line_item` (`item_id`) USING BTREE,
  KEY `fk_pur_sr_fulfillment_line_material` (`material_id`) USING BTREE,
  KEY `fk_pur_sr_fulfillment_line_buy_uom` (`buy_uom_id`) USING BTREE,
  KEY `fk_pur_sr_fulfillment_line_content_uom` (`content_uom_id`) USING BTREE,
  CONSTRAINT `fk_pur_sr_fulfillment_line_buy_uom` FOREIGN KEY (`buy_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_pur_sr_fulfillment_line_content_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_pur_sr_fulfillment_line_header` FOREIGN KEY (`fulfillment_id`) REFERENCES `pur_store_request_fulfillment` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pur_sr_fulfillment_line_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_pur_sr_fulfillment_line_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`),
  CONSTRAINT `fk_pur_sr_fulfillment_line_sr_line` FOREIGN KEY (`store_request_line_id`) REFERENCES `pur_store_request_line` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pur_store_request_line` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_request_id` bigint(20) unsigned NOT NULL,
  `line_no` int(10) unsigned NOT NULL,
  `line_kind` enum('ITEM','MATERIAL') DEFAULT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `profile_key` char(64) NOT NULL,
  `profile_name` varchar(150) DEFAULT NULL,
  `profile_brand` varchar(120) DEFAULT NULL,
  `profile_description` varchar(255) DEFAULT NULL,
  `profile_expired_date` date DEFAULT NULL,
  `expiry_policy` varchar(32) DEFAULT NULL,
  `required_expiry_date` date DEFAULT NULL,
  `min_remaining_days` int(11) DEFAULT NULL,
  `buy_uom_id` bigint(20) unsigned NOT NULL,
  `content_uom_id` bigint(20) unsigned NOT NULL,
  `profile_content_per_buy` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `profile_buy_uom_code` varchar(40) DEFAULT NULL,
  `profile_content_uom_code` varchar(40) DEFAULT NULL,
  `qty_buy_requested` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_content_requested` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_buy_approved` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_content_approved` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_buy_fulfilled` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `qty_content_fulfilled` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `line_status` enum('OPEN','PARTIAL','DONE','CANCELLED') NOT NULL DEFAULT 'OPEN',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `usage_purpose` varchar(20) NOT NULL DEFAULT 'BAHAN_BAKU',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pur_store_request_line_no` (`store_request_id`,`line_no`) USING BTREE,
  KEY `idx_pur_store_request_line_item` (`item_id`) USING BTREE,
  KEY `idx_pur_store_request_line_material` (`material_id`) USING BTREE,
  KEY `idx_pur_store_request_line_profile_uom` (`profile_key`,`buy_uom_id`,`content_uom_id`) USING BTREE,
  KEY `fk_pur_store_request_line_buy_uom` (`buy_uom_id`) USING BTREE,
  KEY `fk_pur_store_request_line_content_uom` (`content_uom_id`) USING BTREE,
  CONSTRAINT `fk_pur_store_request_line_buy_uom` FOREIGN KEY (`buy_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_pur_store_request_line_content_uom` FOREIGN KEY (`content_uom_id`) REFERENCES `mst_uom` (`id`),
  CONSTRAINT `fk_pur_store_request_line_header` FOREIGN KEY (`store_request_id`) REFERENCES `pur_store_request` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pur_store_request_line_item` FOREIGN KEY (`item_id`) REFERENCES `mst_item` (`id`),
  CONSTRAINT `fk_pur_store_request_line_material` FOREIGN KEY (`material_id`) REFERENCES `mst_material` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pur_store_request_po_link` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_request_id` bigint(20) unsigned NOT NULL,
  `purchase_order_id` bigint(20) unsigned NOT NULL,
  `link_type` enum('SHORTAGE','MANUAL') NOT NULL DEFAULT 'SHORTAGE',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_pur_sr_po_link_once` (`store_request_id`,`purchase_order_id`,`link_type`) USING BTREE,
  KEY `idx_pur_sr_po_link_po` (`purchase_order_id`) USING BTREE,
  KEY `fk_pur_sr_po_link_user` (`created_by`) USING BTREE,
  CONSTRAINT `fk_pur_sr_po_link_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `pur_purchase_order` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pur_sr_po_link_sr` FOREIGN KEY (`store_request_id`) REFERENCES `pur_store_request` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pur_sr_po_link_user` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sys_app_config` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `config_group` varchar(50) NOT NULL DEFAULT 'general',
  `config_key` varchar(120) NOT NULL,
  `config_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_app_config_key` (`config_key`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sys_matrix_group` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_code` varchar(50) NOT NULL,
  `group_label` varchar(100) NOT NULL,
  `icon` varchar(100) NOT NULL DEFAULT 'ri-apps-line',
  `color` varchar(20) NOT NULL DEFAULT '#64748b',
  `bg_color` varchar(20) NOT NULL DEFAULT '#f8fafc',
  `sort_order` int(11) NOT NULL DEFAULT 999,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_group_code` (`group_code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sys_menu` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `menu_code` varchar(80) NOT NULL,
  `menu_label` varchar(100) NOT NULL,
  `icon` varchar(80) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `page_id` bigint(20) unsigned DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sidebar_type` enum('MAIN','MY') NOT NULL DEFAULT 'MAIN',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_sys_menu_code` (`menu_code`) USING BTREE,
  KEY `idx_sys_menu_parent` (`parent_id`) USING BTREE,
  KEY `idx_sys_menu_sidebar` (`sidebar_type`,`sort_order`) USING BTREE,
  KEY `fk_sys_menu_page` (`page_id`) USING BTREE,
  CONSTRAINT `fk_sys_menu_page` FOREIGN KEY (`page_id`) REFERENCES `sys_page` (`id`),
  CONSTRAINT `fk_sys_menu_parent` FOREIGN KEY (`parent_id`) REFERENCES `sys_menu` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Struktur item sidebar menu (MAIN=operasional, MY=pribadi)';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sys_page` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `page_code` varchar(100) NOT NULL,
  `page_name` varchar(150) NOT NULL,
  `module` varchar(60) NOT NULL,
  `matrix_group` varchar(50) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_sys_page_code` (`page_code`) USING BTREE,
  KEY `idx_sys_page_module` (`module`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Daftar halaman/fitur yang bisa dikontrol aksesnya';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sys_page_alias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `alias_code` varchar(100) NOT NULL,
  `page_id` bigint(20) unsigned NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sys_page_alias_code` (`alias_code`),
  KEY `idx_sys_page_alias_page` (`page_id`),
  KEY `idx_sys_page_alias_active` (`is_active`),
  CONSTRAINT `fk_sys_page_alias_page` FOREIGN KEY (`page_id`) REFERENCES `sys_page` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Alias page code menuju permission page kanonis';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sys_schema_migration` (
  `migration_id` varchar(128) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `checksum_sha256` char(64) NOT NULL,
  `catalog_version` smallint(5) unsigned NOT NULL,
  `tool_version` varchar(32) NOT NULL,
  `classification` varchar(32) NOT NULL,
  `policies` varchar(255) NOT NULL,
  `batch_id` varchar(64) DEFAULT NULL,
  `applied_by` varchar(128) DEFAULT NULL,
  `execution_ms` int(10) unsigned DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `applied_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`migration_id`),
  UNIQUE KEY `uq_sys_schema_migration_filename` (`filename`),
  KEY `idx_sys_schema_migration_applied_at` (`applied_at`),
  KEY `idx_sys_schema_migration_batch_id` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sys_sidebar_favorite` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `menu_id` bigint(20) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_sidebar_fav` (`user_id`,`menu_id`) USING BTREE,
  KEY `idx_sidebar_fav_user` (`user_id`) USING BTREE,
  KEY `fk_sidebar_fav_menu` (`menu_id`) USING BTREE,
  CONSTRAINT `fk_sidebar_fav_menu` FOREIGN KEY (`menu_id`) REFERENCES `sys_menu` (`id`),
  CONSTRAINT `fk_sidebar_fav_user` FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Favorit menu sidebar per user';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tg_delivery_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue_id` bigint(20) unsigned NOT NULL,
  `target_id` bigint(20) unsigned NOT NULL,
  `attempt_no` smallint(5) unsigned NOT NULL,
  `delivery_status` enum('SENT','FAILED','UNKNOWN') NOT NULL,
  `http_code` smallint(5) unsigned NOT NULL DEFAULT 0,
  `telegram_message_id` bigint(20) DEFAULT NULL,
  `message_preview` varchar(500) DEFAULT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tg_log_created` (`created_at`,`id`),
  KEY `idx_tg_log_queue` (`queue_id`),
  KEY `idx_tg_log_target` (`target_id`),
  CONSTRAINT `fk_tg_log_queue` FOREIGN KEY (`queue_id`) REFERENCES `tg_delivery_queue` (`id`),
  CONSTRAINT `fk_tg_log_target` FOREIGN KEY (`target_id`) REFERENCES `tg_target` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Telegram delivery attempt audit log';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tg_delivery_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `idempotency_key` varchar(191) NOT NULL,
  `source_type` enum('COMMAND','SCHEDULE','TEST','RESEND') NOT NULL,
  `source_ref` varchar(64) DEFAULT NULL,
  `target_id` bigint(20) unsigned NOT NULL,
  `report_type` enum('MENU','OMZET_TODAY','PURCHASE_TODAY') DEFAULT NULL,
  `report_date` date DEFAULT NULL,
  `message_text` text DEFAULT NULL,
  `status` enum('PENDING','PROCESSING','SENT','FAILED','UNKNOWN') NOT NULL DEFAULT 'PENDING',
  `attempt_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `max_attempts` smallint(5) unsigned NOT NULL DEFAULT 3,
  `available_at` datetime NOT NULL DEFAULT current_timestamp(),
  `lease_token` char(32) DEFAULT NULL,
  `leased_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `http_code` smallint(5) unsigned NOT NULL DEFAULT 0,
  `telegram_message_id` bigint(20) DEFAULT NULL,
  `last_error` varchar(500) DEFAULT NULL,
  `resolution_action` enum('CONFIRM_SENT','CLOSE_FAILED','RESEND') DEFAULT NULL,
  `resolution_reason` varchar(500) DEFAULT NULL,
  `resolved_by` bigint(20) unsigned DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolution_queue_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tg_queue_idempotency` (`idempotency_key`),
  KEY `idx_tg_queue_claim` (`status`,`available_at`,`leased_at`,`id`),
  KEY `idx_tg_queue_target` (`target_id`),
  KEY `idx_tg_queue_resolution` (`resolution_action`,`resolved_at`),
  KEY `idx_tg_queue_resolution_queue` (`resolution_queue_id`),
  KEY `fk_tg_queue_resolved_by` (`resolved_by`),
  CONSTRAINT `fk_tg_queue_resolution_queue` FOREIGN KEY (`resolution_queue_id`) REFERENCES `tg_delivery_queue` (`id`),
  CONSTRAINT `fk_tg_queue_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `auth_user` (`id`),
  CONSTRAINT `fk_tg_queue_target` FOREIGN KEY (`target_id`) REFERENCES `tg_target` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Idempotent leased Telegram delivery queue';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tg_schedule` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `target_id` bigint(20) unsigned NOT NULL,
  `schedule_name` varchar(120) NOT NULL,
  `report_type` enum('OMZET_TODAY','PURCHASE_TODAY') NOT NULL,
  `send_time` time NOT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Jakarta',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_enqueued_date` date DEFAULT NULL,
  `last_queue_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tg_schedule_due` (`is_active`,`send_time`,`last_enqueued_date`),
  KEY `idx_tg_schedule_target` (`target_id`),
  KEY `fk_tg_schedule_created_by` (`created_by`),
  KEY `fk_tg_schedule_updated_by` (`updated_by`),
  CONSTRAINT `fk_tg_schedule_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`),
  CONSTRAINT `fk_tg_schedule_target` FOREIGN KEY (`target_id`) REFERENCES `tg_target` (`id`),
  CONSTRAINT `fk_tg_schedule_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `auth_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Jadwal laporan Telegram harian';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tg_setting` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`),
  KEY `fk_tg_setting_updated_by` (`updated_by`),
  CONSTRAINT `fk_tg_setting_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `auth_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Non-secret Telegram module settings';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tg_target` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` varchar(32) NOT NULL,
  `target_type` enum('GROUP','SUPERGROUP','CHANNEL') NOT NULL,
  `title` varchar(150) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tg_target_chat` (`chat_id`),
  KEY `idx_tg_target_active` (`is_active`),
  KEY `fk_tg_target_created_by` (`created_by`),
  KEY `fk_tg_target_updated_by` (`updated_by`),
  CONSTRAINT `fk_tg_target_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_user` (`id`),
  CONSTRAINT `fk_tg_target_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `auth_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Allowlist group/channel internal Telegram';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tg_webhook_update` (
  `update_id` bigint(20) NOT NULL,
  `target_id` bigint(20) unsigned NOT NULL,
  `queue_id` bigint(20) unsigned DEFAULT NULL,
  `chat_id` varchar(32) NOT NULL,
  `command_name` varchar(32) NOT NULL,
  `payload_sha256` char(64) NOT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`update_id`),
  KEY `idx_tg_webhook_target` (`target_id`,`received_at`),
  KEY `idx_tg_webhook_queue` (`queue_id`),
  CONSTRAINT `fk_tg_webhook_queue` FOREIGN KEY (`queue_id`) REFERENCES `tg_delivery_queue` (`id`),
  CONSTRAINT `fk_tg_webhook_target` FOREIGN KEY (`target_id`) REFERENCES `tg_target` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Deduplication record for accepted Telegram updates';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wa_broadcast` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `template_id` int(10) unsigned DEFAULT NULL,
  `custom_message` text DEFAULT NULL COMMENT 'Pesan custom, override template',
  `media_path` varchar(500) DEFAULT NULL,
  `media_url` varchar(500) DEFAULT NULL,
  `media_mime` varchar(100) DEFAULT NULL,
  `media_name` varchar(255) DEFAULT NULL,
  `target_type` enum('MANUAL','SELECTED_MEMBERS','ALL_MEMBERS','MEMBER_ACTIVE','CUSTOM') NOT NULL DEFAULT 'MANUAL',
  `status` enum('DRAFT','QUEUED','SENDING','DONE','FAILED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  `scheduled_at` datetime DEFAULT NULL,
  `delay_pattern_json` text DEFAULT NULL,
  `total_targets` int(10) unsigned NOT NULL DEFAULT 0,
  `total_sent` int(10) unsigned NOT NULL DEFAULT 0,
  `total_failed` int(10) unsigned NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE,
  KEY `idx_scheduled` (`scheduled_at`) USING BTREE,
  KEY `idx_created` (`created_at`) USING BTREE,
  KEY `idx_template` (`template_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wa_broadcast_line` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `broadcast_id` int(10) unsigned NOT NULL,
  `phone_number` varchar(30) NOT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `variables_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `resolved_message` text DEFAULT NULL,
  `status` enum('PENDING','SENT','FAILED','SKIPPED') NOT NULL DEFAULT 'PENDING',
  `sent_at` datetime DEFAULT NULL,
  `error_msg` varchar(500) DEFAULT NULL,
  `retry_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_broadcast` (`broadcast_id`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE,
  KEY `idx_phone` (`phone_number`) USING BTREE,
  CONSTRAINT `wa_broadcast_line_ibfk_1` FOREIGN KEY (`broadcast_id`) REFERENCES `wa_broadcast` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wa_group_map` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_key` varchar(80) NOT NULL,
  `group_name` varchar(150) NOT NULL,
  `group_jid` varchar(100) DEFAULT NULL COMMENT 'WhatsApp group JID, e.g. 120363147815009475@g.us',
  `purpose` varchar(60) DEFAULT NULL COMMENT 'OMZET, HOD, TEAM, PROMO, dll',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `last_sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_group_key` (`group_key`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wa_report_schedule` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `report_type` enum('OMZET_TODAY','PURCHASE_TODAY','ADJUSTMENT_TODAY','PO_SR_TODAY') NOT NULL,
  `template_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `send_time` time NOT NULL,
  `date_offset_days` smallint(6) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `last_run_at` datetime DEFAULT NULL,
  `last_sent_at` datetime DEFAULT NULL,
  `last_sent_date` date DEFAULT NULL,
  `last_status` enum('SENT','FAILED','SKIPPED') DEFAULT NULL,
  `last_error` varchar(500) DEFAULT NULL,
  `run_claim_token` char(32) DEFAULT NULL,
  `run_claimed_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_active_time` (`is_active`,`send_time`) USING BTREE,
  KEY `idx_last_sent_date` (`last_sent_date`) USING BTREE,
  KEY `idx_template` (`template_id`) USING BTREE,
  KEY `idx_group` (`group_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wa_send_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `broadcast_id` int(10) unsigned DEFAULT NULL,
  `source` enum('BROADCAST','MANUAL','GROUP','SYSTEM','SCHEDULED') NOT NULL DEFAULT 'MANUAL',
  `phone_number` varchar(30) DEFAULT NULL,
  `group_jid` varchar(100) DEFAULT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `message_preview` varchar(500) DEFAULT NULL,
  `status` enum('SENT','FAILED','PENDING') NOT NULL DEFAULT 'PENDING',
  `http_status` smallint(6) DEFAULT NULL,
  `error_detail` varchar(500) DEFAULT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_broadcast` (`broadcast_id`) USING BTREE,
  KEY `idx_phone` (`phone_number`) USING BTREE,
  KEY `idx_sent_at` (`sent_at`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wa_session` (
  `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `phone_number` varchar(30) DEFAULT NULL,
  `status` enum('CONNECTED','DISCONNECTED','WAITING_QR','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN',
  `qr_data` text DEFAULT NULL,
  `bot_api_url` varchar(255) NOT NULL DEFAULT 'http://127.0.0.1:3070',
  `bot_api_token` varchar(100) NOT NULL DEFAULT '',
  `last_ping_at` datetime DEFAULT NULL,
  `connected_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `node_path` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wa_template` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `template_code` varchar(80) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` enum('BROADCAST','GROUP','PROMO','INFO','REMINDER','CUSTOM') NOT NULL DEFAULT 'BROADCAST',
  `body` text NOT NULL COMMENT 'Gunakan {{variable}} untuk variabel dinamis',
  `sample_variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_template_code` (`template_code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sys_business_profile` (
  `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `legal_name` varchar(190) DEFAULT NULL,
  `display_name` varchar(190) NOT NULL DEFAULT 'Finance POS',
  `short_name` varchar(80) DEFAULT NULL,
  `tax_id` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Jakarta',
  `locale` varchar(20) NOT NULL DEFAULT 'id_ID',
  `currency_code` char(3) NOT NULL DEFAULT 'IDR',
  `logo_url` varchar(255) DEFAULT NULL,
  `document_footer` varchar(500) DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_sys_business_profile_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sys_business_profile_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `event_code` varchar(50) NOT NULL,
  `before_json` longtext DEFAULT NULL,
  `after_json` longtext DEFAULT NULL,
  `request_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_sys_business_profile_audit_created` (`created_at`,`id`),
  KEY `idx_sys_business_profile_audit_actor` (`actor_user_id`,`created_at`),
  CONSTRAINT `fk_sys_business_profile_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lic_installation` (
  `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `installation_id` char(36) DEFAULT NULL,
  `installation_public_key` varchar(255) DEFAULT NULL,
  `activation_status` enum('UNACTIVATED','ACTIVE','SUSPENDED','REVOKED') NOT NULL DEFAULT 'UNACTIVATED',
  `control_center_url` varchar(255) DEFAULT NULL,
  `control_center_key_id` varchar(100) DEFAULT NULL,
  `last_contact_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lic_installation_id` (`installation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lic_license_cache` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `license_id` varchar(100) NOT NULL,
  `payload_json` longtext NOT NULL,
  `payload_sha256` char(64) NOT NULL,
  `signature_b64` varchar(255) NOT NULL,
  `signing_key_id` varchar(100) NOT NULL,
  `verification_status` enum('UNVERIFIED','VERIFIED','REJECTED','EXPIRED','REVOKED') NOT NULL DEFAULT 'UNVERIFIED',
  `edition_code` varchar(80) DEFAULT NULL,
  `rights_model` enum('PERPETUAL','TERM') NOT NULL DEFAULT 'PERPETUAL',
  `not_before_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `maintenance_ends_at` datetime DEFAULT NULL,
  `grace_until_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lic_license_cache_license_payload` (`license_id`,`payload_sha256`),
  KEY `idx_lic_license_cache_current` (`is_current`,`verification_status`,`received_at`),
  KEY `idx_lic_license_cache_license` (`license_id`,`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lic_feature` (
  `feature_code` varchar(100) NOT NULL,
  `feature_name` varchar(190) NOT NULL,
  `category_name` varchar(100) DEFAULT NULL,
  `depends_on_json` longtext DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `source_manifest_version` varchar(80) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`feature_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lic_feature_cache` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `license_cache_id` bigint(20) unsigned NOT NULL,
  `feature_code` varchar(100) NOT NULL,
  `access_mode` enum('ENABLED','DISABLED') NOT NULL DEFAULT 'DISABLED',
  `limit_json` longtext DEFAULT NULL,
  `depends_on_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lic_feature_cache_license_feature` (`license_cache_id`,`feature_code`),
  KEY `idx_lic_feature_cache_feature` (`feature_code`,`access_mode`),
  CONSTRAINT `fk_lic_feature_cache_license` FOREIGN KEY (`license_cache_id`) REFERENCES `lic_license_cache` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lic_feature_cache_feature` FOREIGN KEY (`feature_code`) REFERENCES `lic_feature` (`feature_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lic_device_activation` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `device_id` char(36) NOT NULL,
  `device_public_key` varchar(255) NOT NULL,
  `device_label` varchar(120) NOT NULL,
  `device_type` enum('POS_WEB','POS_MOBILE','PRINTER_AGENT','OTHER') NOT NULL DEFAULT 'OTHER',
  `outlet_id` bigint(20) unsigned DEFAULT NULL,
  `activation_status` enum('PENDING','ACTIVE','REPLACED','REVOKED') NOT NULL DEFAULT 'PENDING',
  `activated_at` datetime DEFAULT NULL,
  `deactivated_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lic_device_activation_device` (`device_id`),
  KEY `idx_lic_device_activation_status` (`activation_status`,`device_type`),
  KEY `idx_lic_device_activation_outlet` (`outlet_id`,`activation_status`),
  CONSTRAINT `fk_lic_device_activation_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `pos_outlet` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lic_activation_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `device_activation_id` bigint(20) unsigned DEFAULT NULL,
  `event_code` varchar(60) NOT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_lic_activation_audit_created` (`created_at`,`id`),
  KEY `idx_lic_activation_audit_device` (`device_activation_id`,`created_at`),
  CONSTRAINT `fk_lic_activation_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lic_activation_audit_device` FOREIGN KEY (`device_activation_id`) REFERENCES `lic_device_activation` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lic_runtime_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `feature_code` varchar(100) DEFAULT NULL,
  `route_path` varchar(255) DEFAULT NULL,
  `decision_mode` enum('AUDIT_ONLY','ENFORCE') NOT NULL DEFAULT 'AUDIT_ONLY',
  `decision_code` varchar(60) NOT NULL,
  `context_sha256` char(64) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_lic_runtime_audit_created` (`created_at`,`id`),
  KEY `idx_lic_runtime_audit_feature` (`feature_code`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET FOREIGN_KEY_CHECKS=1;
