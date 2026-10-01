<?php
// FINANCE_FEATURE_BOUNDARY_V1. Exact audited methods, never runtime prefix inference.
// New public actions are denied until explicitly reviewed here. RBAC remains independent.
return [
    'contract' => 'FINANCE_FEATURE_BOUNDARY_V1',
    'groups' => [
        'application_update' => [
            '' => 'index confirm',
        ],
        'activity_audit' => [
            'PERIOD_AUDIT' => 'index',
        ],
        'assets' => [
            'ASSET_MANAGEMENT' => 'index create store edit update delete detail group group_quantity lock_bulk lock_asset changes change_create change_store change_detail change_approve change_reject change_post change_cancel damage_index damage_create damage damage_asset_search asset_search damage_store damage_edit damage_update damage_delete labels transfer transfer_create transfer_store transfer_approve transfer_reject transfer_cancel transfer_post handover handover_create handover_store handover_approve handover_reject handover_cancel handover_post maintenance maintenance_create maintenance_store maintenance_approve maintenance_reject maintenance_cancel maintenance_complete disposal disposal_create disposal_store disposal_approve disposal_reject disposal_cancel disposal_post recon recon_generate recon_detail recon_save recon_post recon_cancel depreciation depreciation_generate depreciation_post depreciation_cancel',
        ],
        'attendance' => [
            'ATTENDANCE' => 'settings daily logs schedules schedules_v2 schedule_v2_save schedule_store schedule_update schedule_delete schedule_bulk_store pending_requests overtime_entries overtime_entry_store overtime_entry_update overtime_entry_delete pending_request_action pending_request_bulk_action anomalies master_health estimate estimate_detail meal_calendar ph_assignments ph_assignment_save ph_assignment_delete ph_ledger ph_ledger_store ph_ledger_update ph_ledger_delete ph_ledger_sync_grants ph_recap',
        ],
        'audit' => [
            'RBAC_CORE' => 'roadmap',
        ],
        'auth' => [
            '' => 'index do_login logout',
        ],
        'business_profile' => [
            'BUSINESS_PROFILE' => 'index',
        ],
        'customer_reviews' => [
            'POS_WEB' => 'index submit station station_submit',
        ],
        'dashboard' => [
            '' => 'index',
            'COMPONENT_PRODUCTION' => 'production_suggestions',
            'HPP_CONTROL' => 'product_recipe_stock save_prod_live_cats',
        ],
        'finance' => [
            'FINANCE_ADVANCED' => 'parties party_save party_toggle party_delete party_search member_search utang piutang utang_store piutang_store utang_update piutang_update utang_payment piutang_payment utang_void piutang_void utang_delete piutang_delete',
        ],
        'finance_accounting' => [
            'FINANCE_ADVANCED' => 'index post save_setup',
        ],
        'financial_analysis' => [
            'FINANCE_ADVANCED' => 'index',
        ],
        'procurement_reports' => [
            'PROCUREMENT' => 'sr',
            'PROCUREMENT INVENTORY_WAREHOUSE' => 'materials',
        ],
        'finance_insights' => [
            'FINANCE_ADVANCED' => 'index save lookup evidence_upload evidence_download',
        ],
        'finance_reports' => [
            'FINANCE_ADVANCED' => 'cash_reconciliation cash_reconciliation_line_save cash_reconciliation_line_post cash_reconciliation_round_create revenue_reconciliation revenue_reconciliation_line_save revenue_reconciliation_line_post revenue_reconciliation_round_create cash_vault_daily cash_position financial_estimation bank_daily_recap daily_overview targets target_realize_bulk target_detail target_store target_generate_daily_range target_realize target_update target_lines_save',
            'PERIOD_AUDIT' => 'period_close period_close_detail period_close_store period_close_process period_close_reopen',
        ],
        'hr_contract_verify' => [
            'ATTENDANCE' => 'index',
        ],
        'hr_contracts' => [
            'ATTENDANCE' => 'index templates template_edit template_delete template_preview generate detail view print_view create_draft generate_contract approve sign transition',
        ],
        'inventory' => [
            'INVENTORY_WAREHOUSE' => 'index',
            'INVENTORY_RECON' => 'fifo_audit lot_audit',
        ],
        'inventory_control' => [
            'INVENTORY_RECON' => 'deficits deficit_detail deficit_write_off periods health integrity_audit value_reconciliation value_reconciliation_post value_reconciliation_void period_detail period_open period_close period_cutoff_post period_reopen',
        ],
        'inventory_matrix_spreadsheet' => [
            'INVENTORY_WAREHOUSE' => 'settings discover save sync',
        ],
        'inventory_division' => [
            'INVENTORY_WAREHOUSE' => 'index opening transfer daily compare movement stok_awal material_matrix matrix_view lot',
            'INVENTORY_RECON' => 'adjustment reconcile_audit reconcile_repair reconcile_lot_repair reconcile_lot_profile_sync reconcile_lot_repair_all reconcile_gap_repair_all reconcile_lot_only_adjust reconcile_log_repair reconcile_repair_material_id reconcile_profile_repair reconcile_profile_merge opname opname_data opname_save_physical opname_quick_adjust opname_confirm_recon opname_monthly',
        ],
        'inventory_tools' => [
            'INVENTORY_RECON' => 'repost_pos_skipped_commit_lines audit_pos_cross_division_commit_lines audit_active_month_integrity sync_warehouse_aggregate_lots preview_period_cutoff preflight_period_cutoff repair_pos_cross_division_commit_lines repair_unpaired_pos_void_reversal_artifacts rebuild_void_stock_adjustment_history smoke_test normalize_purchase_catalog_profile_keys audit_purchase_catalog_expiry_phase1 normalize_purchase_catalog_expiry_phase1 reconcile_purchase_catalog_exact_profiles post_core_warehouse_opening_stage post_core_material_division_opening post_core_component_opening rebuild_purchase_impact repair_posted_receipt_profile_keys',
        ],
        'inventory_warehouse' => [
            'INVENTORY_WAREHOUSE' => 'index opening stok_awal daily movement daily_matrix matrix_view lot',
            'INVENTORY_RECON' => 'adjustment recon recon_quick_adjust opname_monthly',
        ],
        'landing_page' => [
            'ONLINE_ORDER' => 'index config_update menu_store menu_update menu_delete menu_toggle menu_reorder gallery_store gallery_update gallery_delete gallery_toggle gallery_reorder embed_store embed_update embed_delete embed_toggle links_store links_update links_delete links_toggle links_reorder',
        ],
        'license' => [
            '' => 'index',
        ],
        'loyalty' => [
            'CUSTOMER_LOYALTY' => 'members members_data member_save member_toggle member_delete member_detail member_redeem_rules member_redeem_process member_redeem_history member_orders member_points member_stamps member_vouchers point_rules point_rules_data point_rule_save point_rule_toggle point_rule_delete stamp_campaigns stamp_campaigns_data stamp_campaign_save stamp_campaign_toggle stamp_campaign_delete redeem_index redeem_data redeem_member_info redeem_point_process redeem_stamp_process redeem_voucher_process redeem_rules redeem_rules_data redeem_rule_save redeem_rule_toggle redeem_rule_delete product_search member_search',
            'PROMOTION_VOUCHER' => 'voucher_campaigns voucher_campaigns_data voucher_campaign_save voucher_campaign_toggle voucher_campaign_delete vouchers vouchers_data voucher_save voucher_toggle voucher_delete voucher_usages voucher_usages_data voucher_usage_detail',
        ],
        'master' => [
            '@entity' => 'index create lookup_search store edit detail update toggle stock_mode reorder',
            'HPP_CONTROL' => 'product_sheet_sync product_sheet_export product_sheet_settings product_sheet_settings_save',
            'INVENTORY_WAREHOUSE' => 'material_usage',
            'ATTENDANCE' => 'att_holiday_generate_year',
        ],
        'master_relation' => [
            'RECIPE_PRODUCT' => 'product_recipe_hub product_recipe product_recipe_bulk_edit product_recipe_bulk_save product_recipe_source_lookup product_recipe_create product_recipe_store product_recipe_edit product_recipe_update product_recipe_delete',
            'HPP_CONTROL' => 'product_availability product_hpp_stock',
            'COMPONENT_PRODUCTION' => 'component_formula_hub component_formula component_formula_create component_formula_store component_formula_edit component_formula_update component_formula_delete',
            'POS_WEB' => 'product_extra product_extra_hub extra_workspace extra_group_hub extra_group_products extra_group_products_save extra_group_items_ajax extra_group_items_save_ajax extra_group_products_ajax extra_group_products_save_ajax extra_item_group_hub extra_item_groups extra_item_groups_save product_extra_create product_extra_store product_extra_delete product_bundle_hub product_bundle product_bundle_create product_bundle_store product_bundle_edit product_bundle_update product_bundle_toggle product_bundle_product_search',
        ],
        'menu_book' => [
            'POS_WEB' => 'index page food beverage flipbook',
        ],
        'my' => [
            'ATTENDANCE PAYROLL' => 'index profile',
            'ATTENDANCE' => 'attendance attendance_mark profile_contract_sign profile_contract_print schedule leave_requests leave_request_cancel leave_request_schedule overtime ph_ledger',
            'PAYROLL' => 'payroll payroll_slip meal_ledger manual_adjustments cash_advance bonus bonus_daily_detail bonus_peer_submit',
        ],
        'payroll' => [
            'PAYROLL' => 'preview_thp manual_adjustments manual_adjustment_store manual_adjustment_update manual_adjustment_delete meal_disbursements meal_disbursement_generate meal_disbursement_mark_paid meal_disbursement_void salary_disbursements payroll_periods payroll_period_void payroll_period_delete payroll_period_generate salary_disbursement_generate salary_disbursement_mark_paid salary_disbursement_slip salary_disbursement_void salary_disbursement_delete cash_advances cash_advance_store cash_advance_update cash_advance_pay_installment cash_advance_void cash_advance_delete bonus bonus_daily_detail bonus_pool_generate bonus_auto_penalty_sync bonus_pool_approve bonus_pool_void bonus_pool_delete bonus_pool_bulk_delete bonus_monthly_detail bonus_service_metric_generate bonus_service_metric_generate_month bonus_monthly_summary_generate bonus_config_save bonus_config_delete bonus_rule_save bonus_rule_delete bonus_weight_save bonus_weight_delete bonus_penalty_type_save bonus_penalty_type_delete bonus_penalty_event_save bonus_penalty_event_void bonus_peer_moderate',
        ],
        'pos' => [
            'CUSTOMER_LOYALTY' => 'members members_data member_save member_toggle',
            'BUSINESS_PROFILE' => 'payment_methods sales_channels sales_channels_data sales_channel_save sales_channel_toggle sales_channel_delete payment_methods_data payment_method_save payment_method_toggle outlets_terminals outlets_data outlet_save outlet_toggle terminals_data terminal_save terminal_toggle',
            'HPP_CONTROL' => 'stock_commit_audit stock_commit_audit_repair_material_mismatches stock_commit_audit_repair_component_mismatches stock_commit_audit_repair_material_drift stock_commit_audit_repair_component_drift stock_live stock_live_data stock_live_probe stock_live_rebuild_all stock_live_rebuild report_cost_control',
            'RESERVATION' => 'deposits deposits_data reservations reservations_data reservation_products_data reservation_detail reservation_catalog reservation_bundle_catalog reservation_extra_options reservation_member_search reservation_save reservation_deposit reservation_verify reservation_refund_step_up_verify reservation_reject reservation_cancel deposit_member_search deposit_save deposit_void',
            'SELF_ORDER' => 'self_order self_order_settings self_order_tables self_order_tables_data self_order_table_save self_order_table_bulk_save self_order_table_delete self_order_tables_print self_order_orders self_order_orders_data self_order_order_detail self_order_order_verify self_order_order_reject',
            'ONLINE_ORDER' => 'online_food online_food_orders online_food_orders_data online_food_order_detail online_food_order_verify online_food_order_reject online_food_settings online_food_locations online_food_locations_data online_food_location_member_search online_food_location_save online_food_location_delete',
            'POS_PRINTER' => 'printers printer_templates printer_profiles printer_devices printer_workspace_legacy printer_settings printer_templates_data printer_connections printer_connections_data printer_connection_save printer_connection_toggle printer_connection_test printer_general printer_layouts printer_layout_editor printer_layouts_data printer_layout_save printer_layout_preview printer_layout_test printer_layout_toggle printer_rules printer_rules_data printer_rule_save printer_rule_toggle printer_preview_live printer_preview_live_data printer_monitor printer_monitor_data printer_attempt_ack printer_guide_config printer_template_create printer_template_edit printer_template_preview printer_template_live_preview printer_template_save printer_template_toggle printer_profiles_data printer_profile_save printer_profile_toggle printer_devices_data printer_device_save printer_device_toggle printer_preview printer_test printer_guide printer_download order_confirm_print_targets order_reprint_print_targets order_reprint_step_up_verify order_void_print_targets order_refund_print_targets order_payment_print_targets order_receipt_print_targets',
            'POS_WEB' => 'customer_reviews customer_reviews_data customer_review_visibility customer_review_settings customer_review_station_save customer_review_station_toggle customer_review_station_print order_draft order_paid cashier cashier_open cashier_recon_status cashier_close cashier_close_preview cashier_session_status order_monitor order_monitor_data order_monitor_ack_task order_monitor_ready_task order_monitor_checker_task order_monitor_ack_order_station order_monitor_ready_order_station order_monitor_checker_order order_draft_data order_draft_load order_draft_delete order_draft_member_search order_draft_product_search cashier_catalog cashier_bundle_catalog order_draft_bundle_search order_draft_extra_options order_draft_save order_draft_confirm order_draft_save_confirm order_runtime_sync order_runtime_job_trigger order_runtime_job_status order_runtime_failed_jobs order_runtime_active_jobs order_runtime_job_retry order_runtime_failed_job_delete_draft order_runtime_failed_job_dismiss order_runtime_failed_snapshot_retry order_runtime_failed_snapshot_dismiss order_runtime_jobs_process_all order_runtime_failed_jobs_retry_all runtime_jobs_run availability_queue_run order_reversal_preview order_reversal_step_up_verify order_payment_prepare order_payment_save order_void_save order_refund_save',
            'INVENTORY_RECON' => 'daily_recon_settings daily_recon_settings_save availability_queue availability_queue_process availability_queue_retry',
            'PROMOTION_VOUCHER' => 'order_payment_voucher_search',
            'SALES_REPORTING' => 'report_sales report_sales_detail report_sales_extra report_sales_audit report_sales_transaction report_sales_document_print report_sales_payment_line_update report_payments report_daily_sales report_daily_sales_print report_payment_detail report_payment_methods report_payment_accounts report_refunds report_refund_detail report_voids report_cashier_close report_cashier_close_detail report_void_detail',
            'SALES_REPORTING AUTOMATION_MESSAGING' => 'report_daily_sales_notify',
        ],
        'pos_mobile' => [
            'POS_MOBILE_APK' => 'ping login logout bootstrap catalog member_search extra_options orders order_load order_reversal_preview order_reversal_step_up_verify cashier_close_step_up_verify order_void_save order_refund_save order_save order_confirm payment_prepare payment_save session_status cashier_open cashier_close_preview cashier_close orders_push',
            'POS_MOBILE_APK POS_PRINTER' => 'printers printer_test order_reprint_step_up_verify order_void_print_targets order_refund_print_targets order_reprint_targets order_confirm_print_targets payment_print_targets',
            'POS_MOBILE_APK RESERVATION' => 'reservations reservation_products reservation_detail reservation_verify reservation_reject_step_up_verify reservation_reject',
            'POS_MOBILE_APK SELF_ORDER' => 'self_order_inbox self_order_inbox_detail self_order_inbox_verify self_order_inbox_reject',
            'POS_MOBILE_APK ONLINE_ORDER' => 'online_food_inbox online_food_inbox_detail online_food_inbox_verify online_food_inbox_reject',
            'POS_MOBILE_APK PROMOTION_VOUCHER' => 'voucher_search',
        ],
        'pos_printer_agent' => [
            'POS_PRINTER' => 'bootstrap',
        ],
        'procurement' => [
            'PROCUREMENT AUTOMATION_MESSAGING' => 'division_po_sr_notify',
            'PROCUREMENT' => 'workbench division_requests purchasing_desk store_requests store_request_create store_request_edit store_request_detail division_po_sr division_po_sr_print division_po_sr_pdf division_po_sr_create division_po_sr_edit division_stock_preview division_po_sr_profile_search division_po_sr_store division_po_sr_detail division_po_sr_verify division_po_sr_action store_request_profile_search store_request_stock_preview store_request_store store_request_update store_request_action store_request_split_preview store_request_fulfill store_request_repair_history store_request_generate_po',
        ],
        'production' => [
            'COMPONENT_PRODUCTION' => 'component_stock component_stock_data component_movements component_movements_data component_daily component_daily_data component_monthly component_reconcile component_reconcile_audit component_reconcile_repair component_reconcile_repair_all component_lot_repair component_lot_sync_to_stock component_movement_log_fix_to_stock component_lot_sync_all component_lot_only_adjust component_lots component_lot_usage component_openings component_opening_export_template component_opening_export_existing component_opening_import component_opening_save component_opening_post component_opening_detail component_opening_delete component_opening_void component_opening_reopen component_opening_generate_monthly component_adjustments component_adjustment_save component_stock_snapshot component_adjustment_post component_adjustment_step_up_verify component_adjustment_void component_adjustment_void_step_up_verify component_adjustment_delete component_batches component_batch_save component_batch_preview component_batch_post component_batch_step_up_verify component_batch_status component_batch_delete component_batch_void component_batch_void_step_up_verify component_batch_usage component_batch_usage_page component_picker_search component_categories component_category_save component_category_toggle component_category_quick_map component_masters component_masters_data component_master_save component_master_toggle component_master_usage component_formulas component_formulas_data component_formula_detail component_formula_source_search component_formula_show component_formula_edit component_formula_save component_formula_save_bulk component_formula_restore_step_up_verify component_formula_restore component_formula_delete component_cost_variables component_cost_variable_save component_daily_recon component_daily_recon_data component_daily_recon_step_up_verify component_daily_recon_save component_daily_recon_adjust component_daily_recon_confirm component_opname component_opening_monthly',
        ],
        'purchase' => [
            'PROCUREMENT' => 'index order_detail order_log_index report_index report_detail_index report_detail_data rebuild_impact_index rebuild_impact_run reclassify_profile_domain_index reclassify_profile_domain_run order_create order_edit order_stock_preview vendor_quick_store receipt_index receipt_po_lines receipt_store catalog_search catalog_sync_core setup_sync_core setup_sync_core_all order_store order_status_update order_update payment_apply item_price_history item_price_history_item_search item_price_history_data',
            'INVENTORY_RECON' => 'repair_inventory_opening_history_cli stock_adjustment_index stock_adjustment_warehouse_index stock_adjustment_division_index stock_adjustment_item_search stock_adjustment_store stock_adjustment_post stock_adjustment_delete stock_adjustment_void stock_adjustment_step_up_verify stock_opname_generate stock_division_reconcile_index stock_division_reconcile_audit stock_division_reconcile_repair stock_division_reconcile_lot_repair stock_division_reconcile_lot_profile_sync stock_division_reconcile_lot_repair_all stock_division_reconcile_lot_only_adjust stock_division_reconcile_log_repair stock_division_reconcile_gap_repair_all stock_division_reconcile_repair_material_id stock_division_reconcile_profile_repair stock_division_reconcile_profile_merge fifo_audit_index lot_audit_index warehouse_lot_audit_index division_lot_audit_index material_lot_usage stock_warehouse_opname_monthly stock_division_opname_monthly',
            'BUSINESS_PROFILE' => 'account_index',
            'FINANCE_ADVANCED' => 'finance_mutation_index finance_mutation_classify finance_mutation_store',
            'INVENTORY_WAREHOUSE' => 'stock_warehouse_index stock_opening_index stock_opening_warehouse_index stock_opening_warehouse_generated stock_opening_division_index stock_opening_division_generated stock_opening_division_export_template stock_opening_division_export_existing stock_opening_division_import stock_opening_item_search stock_opening_store stock_opening_void stock_opening_step_up_verify stock_transfer_division_index stock_transfer_item_search stock_transfer_store stock_transfer_post stock_transfer_delete stock_transfer_void stock_transfer_step_up_verify stock_warehouse_daily_index stock_warehouse_daily_matrix stock_warehouse_movement_index stock_division_index stock_division_movement_index stock_division_daily_index inventory_warehouse_daily_index inventory_material_daily_index stock_daily_cell_detail stock_material_daily_matrix',
        ],
        'roast_connect' => [
            'INTEGRATION_API' => 'health catalog material',
        ],
        'roast_integrations' => [
            'INTEGRATION_API' => 'index save rotate',
        ],
        'roastery' => [
            'COMPONENT_PRODUCTION' => 'packaging_labels packaging_label_save packaging_label_template_save packaging_label_template_delete packaging_label_duplicate packaging_label_print packaging_label_delete packaging_label_activate',
        ],
        'roles' => [
            'RBAC_CORE' => 'index create store edit update delete matrix save_matrix users save_users matrix_groups quick_register_menu deactivate_menu_item deactivate_page_item save_page_matrix_group toggle_page_active',
        ],
        'settings' => [
            '' => 'index change_password',
        ],
        'sidebar' => [
            'RBAC_CORE' => 'pin unpin reorder manage save_structure menu_store menu_update menu_delete menu_toggle_active',
        ],
        'system_tools' => [
            'RBAC_CORE' => 'index backup_guide replication_guide settings settings_save action_list_tables action_run_backup action_test_db action_apply_mysql_config action_setup_master action_check_replication action_initial_sync action_compare_data action_failover action_restart_replication backup_status replication_status',
        ],
        'telegram' => [
            'AUTOMATION_MESSAGING' => 'index guide target_save delivery schedule_save log resolve_unknown settings notification_settings setup_check_bot setup_discover_targets setup_save_discovered_target setup_install_webhook setup_check_webhook test_send run_due process_queue',
        ],
        'telegram_webhook' => [
            'AUTOMATION_MESSAGING' => 'index',
        ],
        'user_guide' => [
            '' => 'index',
        ],
        'users' => [
            'RBAC_CORE' => 'index create store edit detail update toggle permissions save_override',
            'PERIOD_AUDIT' => 'access_audit',
        ],
        'welcome' => [
            '' => 'index',
        ],
        'whatsapp' => [
            'AUTOMATION_MESSAGING' => 'dashboard broadcast broadcast_create broadcast_edit broadcast_detail broadcast_delete broadcast_deactivate template report_schedules group log manual settings notification_settings api_status api_send_test api_log_retry api_member_search api_member_picker api_broadcast_start api_template_preview api_schedule_run api_group_command api_qr api_engine_status api_engine_start api_engine_stop api_engine_logs api_env_read api_env_save api_session_reset guide',
        ],
        'feature_access' => [
            '' => 'index upgrade',
        ],
    ],
    'entities' => [
        'bank' => 'BUSINESS_PROFILE',
        'uom' => 'POS_WEB',
        'operational-division' => 'BUSINESS_PROFILE',
        'org-division' => 'RBAC_CORE',
        'org-position' => 'RBAC_CORE',
        'org-employee' => 'RBAC_CORE',
        'product-division' => 'POS_WEB',
        'product-classification' => 'POS_WEB',
        'product-category' => 'POS_WEB',
        'product' => 'POS_WEB',
        'extra' => 'POS_WEB',
        'extra-group' => 'POS_WEB',
        'company-account' => 'BUSINESS_PROFILE',
        'item-category' => 'INVENTORY_WAREHOUSE',
        'material' => 'INVENTORY_WAREHOUSE',
        'item' => 'INVENTORY_WAREHOUSE',
        'component-category' => 'COMPONENT_PRODUCTION',
        'component' => 'COMPONENT_PRODUCTION',
        'vendor' => 'PROCUREMENT',
        'posting-type' => 'PROCUREMENT',
        'purchase-type' => 'PROCUREMENT',
        'purchase-catalog' => 'PROCUREMENT',
        'purchase-catalog-vendor' => 'PROCUREMENT',
        'att-shift' => 'ATTENDANCE',
        'att-location' => 'ATTENDANCE',
        'att-overtime-standard' => 'ATTENDANCE',
        'att-holiday' => 'ATTENDANCE',
        'pay-component' => 'PAYROLL',
        'pay-profile' => 'PAYROLL',
        'pay-assignment' => 'PAYROLL',
        'pay-basic-salary' => 'PAYROLL',
        'pay-objective-override' => 'PAYROLL',
        'pay-profile-line' => 'PAYROLL',
        'hr-contract-template' => 'ATTENDANCE',
        'hr-contract' => 'ATTENDANCE',
        'variable-cost-default' => 'HPP_CONTROL',
    ],
    'pages' => [
        'activity_audit' => 'index',
        'assets' => 'index store edit update delete detail group group_quantity lock_bulk lock_asset changes change_create change_store change_detail damage_index damage_create damage damage_store damage_edit damage_update damage_delete labels recon recon_generate recon_detail recon_save recon_post recon_cancel depreciation depreciation_generate depreciation_post depreciation_cancel',
        'attendance' => 'settings daily logs schedules schedules_v2 schedule_store schedule_update schedule_delete schedule_bulk_store pending_requests overtime_entries overtime_entry_store overtime_entry_update overtime_entry_delete pending_request_action pending_request_bulk_action anomalies master_health estimate estimate_detail meal_calendar ph_assignments ph_assignment_save ph_assignment_delete ph_ledger ph_ledger_store ph_ledger_update ph_ledger_delete ph_ledger_sync_grants ph_recap',
        'audit' => 'roadmap',
        'auth' => 'index do_login logout',
        'business_profile' => 'index',
        'customer_reviews' => 'index station submit station_submit',
        'dashboard' => 'index production_suggestions',
        'finance' => 'parties utang piutang party_toggle party_delete',
        'finance_accounting' => 'index',
        'financial_analysis' => 'index',
        'procurement_reports' => 'sr materials',
        'finance_insights' => 'index',
        'finance_reports' => 'cash_reconciliation revenue_reconciliation cash_vault_daily cash_position financial_estimation bank_daily_recap daily_overview period_close period_close_detail period_close_store period_close_process period_close_reopen targets target_realize_bulk target_detail target_store target_generate_daily_range target_realize target_update target_lines_save',
        'hr_contracts' => 'index templates template_edit template_delete generate view create_draft generate_contract approve sign transition',
        'inventory' => 'index fifo_audit lot_audit',
        'inventory_control' => 'deficits deficit_detail deficit_write_off periods health integrity_audit value_reconciliation value_reconciliation_post value_reconciliation_void period_detail period_open period_close period_cutoff_post period_reopen',
        'inventory_division' => 'index opening adjustment transfer daily compare movement stok_awal matrix_view lot opname opname_monthly',
        'inventory_warehouse' => 'index opening stok_awal adjustment daily movement matrix_view lot recon opname_monthly',
        'inventory_matrix_spreadsheet' => 'settings discover save sync',
        'landing_page' => 'index config_update',
        'license' => 'index',
        'loyalty' => 'members member_detail point_rules stamp_campaigns voucher_campaigns vouchers voucher_usages redeem_index redeem_rules',
        'master' => 'index create edit detail',
        'master_relation' => 'product_recipe_hub product_availability component_formula_hub product_recipe product_hpp_stock product_recipe_bulk_edit product_recipe_bulk_save product_recipe_create product_recipe_store product_recipe_edit product_recipe_update product_recipe_delete component_formula component_formula_create product_extra product_extra_hub extra_workspace extra_group_hub extra_group_products extra_group_products_save extra_item_group_hub extra_item_groups extra_item_groups_save product_extra_create product_extra_store product_extra_delete product_bundle_hub product_bundle product_bundle_create product_bundle_store product_bundle_edit product_bundle_update product_bundle_toggle',
        'menu_book' => 'index page food beverage flipbook',
        'my' => 'index attendance attendance_mark profile profile_contract_sign schedule payroll leave_requests leave_request_cancel meal_ledger overtime ph_ledger manual_adjustments cash_advance bonus bonus_daily_detail bonus_peer_submit',
        'payroll' => 'preview_thp manual_adjustments manual_adjustment_store manual_adjustment_update manual_adjustment_delete meal_disbursements meal_disbursement_generate meal_disbursement_mark_paid meal_disbursement_void salary_disbursements payroll_periods payroll_period_void payroll_period_delete payroll_period_generate salary_disbursement_generate salary_disbursement_mark_paid salary_disbursement_void salary_disbursement_delete cash_advances cash_advance_store cash_advance_update cash_advance_pay_installment cash_advance_void cash_advance_delete bonus bonus_daily_detail bonus_pool_generate bonus_auto_penalty_sync bonus_pool_approve bonus_pool_void bonus_pool_delete bonus_pool_bulk_delete bonus_monthly_detail bonus_service_metric_generate bonus_service_metric_generate_month bonus_monthly_summary_generate bonus_config_save bonus_config_delete bonus_rule_save bonus_rule_delete bonus_weight_save bonus_weight_delete bonus_penalty_type_save bonus_penalty_type_delete bonus_penalty_event_save bonus_penalty_event_void bonus_peer_moderate',
        'pos' => 'members payment_methods stock_commit_audit deposits self_order reservations self_order_settings self_order_tables self_order_orders online_food online_food_orders online_food_settings online_food_locations sales_channels outlets_terminals printers printer_templates printer_profiles printer_devices printer_workspace_legacy printer_settings printer_connections printer_general printer_layouts printer_layout_editor printer_rules customer_reviews printer_preview_live printer_monitor printer_guide_config printer_template_create printer_template_edit printer_template_preview printer_preview printer_guide order_draft order_paid cashier daily_recon_settings daily_recon_settings_save order_monitor stock_live availability_queue availability_queue_process availability_queue_retry report_sales report_cost_control report_sales_detail report_sales_extra report_sales_audit report_sales_transaction report_payments report_daily_sales report_payment_detail report_payment_methods report_payment_accounts report_refunds report_refund_detail report_voids report_cashier_close report_cashier_close_detail report_void_detail',
        'procurement' => 'workbench division_requests purchasing_desk store_requests store_request_create store_request_edit store_request_detail division_po_sr division_po_sr_create division_po_sr_edit division_po_sr_detail division_po_sr_action',
        'production' => 'component_stock component_movements component_daily component_monthly component_reconcile component_lots component_lot_usage component_openings component_opening_import component_opening_detail component_adjustments component_batches component_batch_usage_page component_categories component_masters component_master_usage component_formulas component_formula_show component_formula_edit component_cost_variables component_daily_recon component_opname component_opening_monthly',
        'purchase' => 'index order_detail order_log_index report_index report_detail_index rebuild_impact_index reclassify_profile_domain_index order_create order_edit account_index finance_mutation_index stock_warehouse_index stock_opening_index stock_opening_warehouse_index stock_opening_warehouse_generated stock_opening_division_index stock_opening_division_generated stock_opening_division_export_template stock_opening_division_import stock_adjustment_index stock_adjustment_warehouse_index stock_adjustment_division_index stock_transfer_division_index stock_opname_generate stock_warehouse_daily_index stock_warehouse_movement_index stock_division_index stock_division_movement_index stock_division_daily_index stock_division_reconcile_index inventory_warehouse_daily_index inventory_material_daily_index fifo_audit_index division_lot_audit_index material_lot_usage receipt_index item_price_history stock_warehouse_opname_monthly stock_division_opname_monthly',
        'roast_integrations' => 'index',
        'roastery' => 'packaging_labels packaging_label_save packaging_label_template_save packaging_label_template_delete packaging_label_duplicate packaging_label_print packaging_label_delete packaging_label_activate',
        'roles' => 'index create store edit update delete matrix save_matrix users save_users matrix_groups',
        'settings' => 'index',
        'sidebar' => 'manage menu_store menu_update menu_delete',
        'system_tools' => 'index backup_guide replication_guide settings',
        'telegram' => 'index guide target_save delivery schedule_save log resolve_unknown settings setup_check_bot setup_discover_targets setup_save_discovered_target setup_install_webhook setup_check_webhook test_send',
        'user_guide' => 'index',
        'users' => 'index create store edit detail update toggle permissions save_override access_audit',
        'whatsapp' => 'dashboard broadcast broadcast_create broadcast_edit broadcast_detail broadcast_delete broadcast_deactivate template report_schedules group log manual settings guide',
        'feature_access' => 'index upgrade',
    ],
];
