# Handoff Codex Finance — Batch 01 sampai 68

Tanggal: 2026-09-03

Status: berhenti setelah Batch 68 `PASS`. Batch 69 tidak dikerjakan.

Dokumen ini adalah ringkasan handoff dari rangkaian kerja `finance_auditor` dan
`finance_fixer`. Handoff ini tidak berarti Finance sudah siap dijual. Pekerjaan
yang tercatat di sini adalah hardening bertahap yang sudah mendapat review,
sedangkan DB live, UAT browser, packaging release, dan pemulihan object Git
tetap menjadi pekerjaan terpisah.

## 1. Status akhir

- Batch 68: `PASS` dari auditor.
- B68 mengubah dua file scope dan keduanya tetap staged tanpa commit.
- B68 smoke: 428 checks.
- Regression yang dijalankan: Extra Group checklist 344 checks, Extra Group
  mutation 185 checks, B66 preflight smoke 113 checks, production formula 191
  checks.
- PHP lint B68: lulus.
- Worktree B68: tidak memiliki unstaged overlay.
- Tidak ada SQL B68 yang mengubah schema atau data.
- Tidak ada SQL server yang dijalankan oleh workflow ini.
- Sesi `finance-codex` sudah dihentikan setelah B68; batch berikutnya tidak
  boleh dipilih otomatis.

## 2. Modul yang sudah diperbaiki

### Auth, security, dan RBAC

- Guard endpoint containment dan fail-closed.
- Perbaikan atomic role deletion.
- Division scope dan filter role nonaktif.
- Boundary secret/config production.
- Scoped CSRF dan POST-only untuk writer yang sudah diaudit.
- Login throttle atomik, audit session fail-closed, dan boundary microsecond.
- Catatan: ini belum berarti seluruh endpoint dan role matrix sudah lulus
  readiness; endpoint Master generik, scope lengkap, MFA, secure-cookie policy,
  dan browser/RBAC E2E masih memerlukan verifikasi lanjutan.

### Backup, runtime, dan Printer Agent

- Runner backup tidak lagi memakai Git sebagai media push otomatis.
- Boundary provisioning/config Printer Agent diperketat.
- Local Printer Agent memakai allowlist origin, key boundary, dan konfigurasi
  yang lebih fail-closed.
- Kontrak runtime/release dan smoke baseline didokumentasikan.
- Restore drill, service installer, dan release package nyata belum dibuktikan.

### POS, mobile, order, reservation, dan runtime job

- Authorization endpoint POS Mobile.
- Scoped CSRF untuk transaksi POS, reservation, self-order, online-food,
  cashier, stock-live, stock-commit, dan runtime job.
- Lifecycle Order Monitor dipisahkan dari read path; beberapa writer task dan
  bulk action diberi boundary HTTP/CSRF.
- Smoke test permission, payload, lifecycle, dan failure path diperluas.
- Authenticated HTTP/browser, DB transaction nyata, dan UAT device belum
  dijalankan.

### WhatsApp dan service integration

- Secret boundary dan environment handling.
- RBAC writer template/group.
- Engine log redaction.
- CSRF untuk engine control, env save, template/group, report schedule,
  broadcast, manual single-send, send-test, dan log retry.
- Scheduler dibuat CLI-only dan diberi atomic claim/lease.
- Service-auth untuk Finance ↔ `wa-engine` dan group command.
- Delivery tetap at-least-once; idempotency eksternal Bot dan runtime live
  belum dibuktikan.

### Dashboard, production, formula, recipe, bundle, dan HPP

- Dashboard component membedakan mismatch quantity dan mismatch nilai FIFO.
- Writer formula component, recipe, product-extra, dan bundle diberi RBAC,
  POST-only, scoped CSRF, payload boundary, dan smoke regression.
- Product-extra legacy diberi validasi eligibility, duplicate-key handling,
  prepared insert, dan redaksi error DB.
- B68 menambahkan serialisasi transaksi untuk legacy Product → Extra Group.
- Mismatch historis, repair data enam component, dan rekonsiliasi HPP end-to-end
  belum selesai.

### Extra Group mapping

- Filter checklist dibuat read-only saat terfilter.
- Validasi set Product ↔ Extra Group mencakup ID canonical, active state,
  duplicate, clear-all, dan division policy yang sudah memiliki bukti.
- B66 menambahkan read-only schema/data preflight.
- B67 menambahkan optimistic concurrency, revision, lock ordering, dan guard
  stale response.
- B68 menyelaraskan legacy Product → Extra Group dengan protocol lock B67,
  menangani transaction/query failure, duplicate 1062, orphan cleanup, dan
  affected-row anomaly.
- Preflight DB live, contention/deadlock InnoDB, UAT dua browser, serta arah
  Extra → Group masih belum dibuktikan/ditutup.

## 3. File scope Batch 68

- `application/controllers/Master_relation.php`
- `tools/tests/master_relation_product_extra_mutation_csrf_smoke.php`

Perubahan B68 tidak mencakup route, view, schema, migration, data, config,
backup, upload, atau `wa-engine`.

## 4. Manifest file yang tercatat berubah pada Batch 01–68

Daftar berikut diambil dari bagian `File berubah` pada execution log. Daftar
ini adalah manifest historis, bukan perintah untuk memasukkan seluruh file ke
satu commit. Karena index repository kumulatif dan object Git rusak, packaging
wajib memakai whitelist batch yang disetujui.

```text
application/controllers/Master.php
application/controllers/Master_relation.php
application/models/Role_model.php
application/controllers/Purchase.php
application/controllers/Pos_mobile.php
tools/tests/pos_mobile_authorization_smoke.php
.gitignore
scripts/backup/.env
scripts/backup/.env.example
scripts/backup/backup_full.sh
scripts/backup/backup_full.bat
application/controllers/System_tools.php
application/views/system/dbtools.php
application/views/system/settings.php
application/views/system/backup_guide.php
tools/tests/backup_source_isolation_smoke.php
application/models/Auth_model.php
application/controllers/Auth.php
application/core/MY_Controller.php
tools/tests/auth_division_scope_smoke.php
tools/tests/auth_inactive_role_permission_smoke.php
application/libraries/DeploymentConfig.php
application/config/config.php
application/config/database.php
index.php
docs/deployment_secret_contract.md
tools/tests/deployment_secret_config_smoke.php
application/controllers/Pos.php
application/controllers/Pos_printer_agent.php
tools/pos_printer_agent/agent.py
tools/pos_printer_agent/check_saved_printers.py
tools/pos_printer_agent/config.example.json
tools/pos_printer_agent/README.md
tools/pos_printer_agent/requirements.txt
application/views/pos/printer_guide.php
application/views/pos/printer_guide_config.php
tools/tests/printer_agent_trust_smoke.py
tools/tests/web_runtime_boundary_smoke.php
application/controllers/Inventory_control.php
application/views/inventory/stock_deficit_detail.php
application/views/inventory/stock_period_detail.php
application/views/inventory/stock_period_index.php
application/views/inventory/stock_value_reconciliation_index.php
tools/tests/inventory_control_mutation_csrf_smoke.php
application/views/pos/stock_commit_audit_index.php
tools/tests/pos_stock_commit_repair_csrf_smoke.php
application/views/pos/cashier_index.php
application/views/pos/order_draft_index.php
application/views/pos/order_paid_index.php
tools/tests/pos_transaction_csrf_smoke.php
application/views/pos/reservation_index.php
application/views/pos/self_order_orders.php
application/views/pos/online_food_orders.php
application/views/pos/stock_live_index.php
tools/tests/pos_runtime_job_trigger_csrf_smoke.php
tools/tests/pos_stock_live_rebuild_csrf_smoke.php
application/views/purchase/stock_division_reconcile_index.php
tools/tests/pos_runtime_job_mutation_csrf_smoke.php
tools/tests/pos_runtime_failed_job_delete_draft_csrf_smoke.php
tools/tests/pos_runtime_failed_job_dismiss_csrf_smoke.php
tools/tests/pos_runtime_failed_snapshot_retry_csrf_smoke.php
tools/tests/pos_runtime_failed_snapshot_dismiss_csrf_smoke.php
application/views/pos/order_monitor_index.php
tools/tests/pos_order_monitor_task_csrf_smoke.php
application/models/Pos_order_monitor_model.php
tools/tests/pos_order_monitor_lifecycle_smoke.php
docs/README.md
docs/release_runtime_contract.md
tools/tests/release_runtime_contract_smoke.php
application/controllers/Whatsapp.php
application/views/wa/settings.php
tools/tests/whatsapp_settings_secret_boundary_smoke.php
tools/tests/whatsapp_template_group_action_rbac_smoke.php
tools/tests/whatsapp_engine_log_boundary_smoke.php
tools/tests/whatsapp_engine_control_csrf_smoke.php
tools/tests/whatsapp_env_save_csrf_smoke.php
application/views/wa/template.php
application/views/wa/group.php
tools/tests/whatsapp_template_group_mutation_csrf_smoke.php
application/views/wa/report_schedule.php
tools/tests/whatsapp_report_schedule_mutation_csrf_smoke.php
application/views/wa/broadcast.php
application/views/wa/broadcast_form.php
application/views/wa/broadcast_detail.php
application/views/wa/manual.php
tools/tests/whatsapp_broadcast_mutation_csrf_smoke.php
application/views/wa/dashboard.php
application/views/wa/log.php
tools/tests/whatsapp_log_retry_csrf_rbac_smoke.php
tools/tests/whatsapp_settings_mutation_csrf_smoke.php
tools/tests/whatsapp_manual_single_send_csrf_smoke.php
tools/tests/whatsapp_api_send_test_csrf_smoke.php
application/views/wa/guide.php
tools/tests/whatsapp_api_schedule_run_cli_smoke.php
sql/2026-09-02a_wa_report_schedule_claim_lease.sql
sql/2026-08-15b_wa_report_schedule.sql
sql/2026-08-17e_pos_whatsapp_runtime_schema_preflight.sql
tools/tests/whatsapp_report_schedule_claim_lease_smoke.php
application/controllers/Dashboard.php
application/views/dashboard/index.php
tools/tests/dashboard_component_value_mismatch_smoke.php
tools/tests/whatsapp_group_command_mutation_disabled_smoke.php
wa-engine/index.js
wa-engine/.env.example
docs/wa_group_command_service_auth_runbook.md
tools/tests/whatsapp_group_command_service_auth_smoke.php
tools/tests/wa_engine_group_command_service_auth_smoke.js
tools/tests/auth_login_throttle_smoke.php
sql/2026-09-03b_auth_session_log_login_at_microsecond_compatibility.sql
tools/db/apply_auth_session_log_login_at_microsecond.sh
application/controllers/Production.php
application/views/production/component_formula_edit.php
tools/tests/production_component_formula_mutation_csrf_smoke.php
application/views/master/relation_product_recipe_edit.php
application/views/master/relation_form.php
application/views/master/relation_list.php
tools/tests/master_relation_product_recipe_mutation_csrf_smoke.php
tools/tests/master_relation_component_formula_mutation_csrf_smoke.php
tools/tests/master_relation_product_extra_mutation_csrf_smoke.php
application/views/master/product_bundle_edit.php
application/views/master/product_bundle_hub.php
tools/tests/master_relation_product_bundle_mutation_csrf_smoke.php
application/views/master/index.php
tools/tests/master_relation_extra_group_mutation_csrf_smoke.php
application/views/master/extra_group_products.php
application/views/master/extra_item_groups.php
tools/tests/master_relation_extra_group_checklist_mutation_csrf_smoke.php
tools/sql/2026-09-03_extra_group_mapping_preflight.sql
tools/run_extra_group_mapping_preflight.sh
tools/tests/extra_group_mapping_preflight_smoke.sh
docs/2026-09-03_extra_group_mapping_preflight_runbook.md
```

## 5. Risiko dan pekerjaan tersisa

- Object Git repository rusak: `HEAD` tree/blob dan cache-tree memiliki object
  yang hilang. Jangan menjalankan recovery destructive atau commit dari index
  kumulatif tanpa backup dan prosedur repository recovery.
- SQL migration dan preflight belum dibuktikan terhadap database live.
- B66 live preflight, UAT dua browser/session, POS web/mobile probe, dan
  contention/deadlock InnoDB masih wajib sebelum release.
- Repair mismatch historis, item-centric cleanup, migration runner global,
  installer/updater, package manifest, tenant branding, FeatureGate, License
  Hub, Product Control Center, pilot, dan SOP komersial belum selesai.
- Jangan klaim Finance siap dijual hanya karena Batch 68 `PASS`.

## 6. Handoff operator

1. Review file ini dan [server SQL runbook](2026-09-03_codex_server_sql_runbook_b01-b68.md).
2. Buat backup terverifikasi sebelum migration apa pun.
3. Jalankan hanya SQL yang berstatus `RUN` atau `CONDITIONAL RUN` setelah
   preflight schema dan approval operator.
4. Jalankan B66 preflight memakai akun read-only eksternal.
5. Jangan menjalankan SQL di `sql/_old/` untuk deployment baru.
6. Jangan commit index kumulatif sebelum object Git dipulihkan dan manifest
   release ditinjau.
