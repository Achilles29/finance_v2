SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-24c_pos_print_configuration_single_source_foundation.sql
-- Tujuan :
-- 1) Memisahkan koneksi printer fisik, tampilan umum, layout, dan aturan cetak
-- 2) Menjadikan data database sebagai sumber aturan cetak POS yang eksplisit
-- 3) Menyalin konfigurasi printer lama sebagai titik awal tanpa menghapusnya
-- 4) Menyediakan fondasi monitor hasil kirim browser ke agent lokal
--
-- Catatan penting:
-- - Script ini TIDAK menghapus pos_printer, pos_printer_profile, template lama,
--   order POS, maupun riwayat transaksi.
-- - Setelah script dijalankan, aplikasi versi refactor membaca route baru bila
--   ada route aktif. Sebelum itu aplikasi tetap memakai fallback lama.
-- - Jalankan di lokal dan server sebelum deploy kode refactor printer.
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS pos_print_connection (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  connection_code VARCHAR(60) NOT NULL,
  connection_name VARCHAR(150) NOT NULL,
  outlet_id BIGINT UNSIGNED NULL,
  operational_division_id BIGINT UNSIGNED NULL,
  location_label VARCHAR(120) NULL,
  connection_type ENUM('LOCAL_AGENT','LAN','USB') NOT NULL DEFAULT 'LOCAL_AGENT',
  agent_os ENUM('WINDOWS','UBUNTU','OTHER') NOT NULL DEFAULT 'WINDOWS',
  agent_host VARCHAR(120) NULL,
  agent_printer_code VARCHAR(60) NULL,
  device_name VARCHAR(120) NULL,
  mac_address VARCHAR(32) NULL,
  python_port INT UNSIGNED NULL,
  ip_address VARCHAR(60) NULL,
  port INT UNSIGNED NULL,
  paper_width_mm TINYINT UNSIGNED NOT NULL DEFAULT 80,
  chars_per_line TINYINT UNSIGNED NOT NULL DEFAULT 48,
  default_copy_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
  cut_mode ENUM('NONE','PARTIAL','FULL') NOT NULL DEFAULT 'PARTIAL',
  open_drawer TINYINT(1) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  legacy_printer_id BIGINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_print_connection_code (connection_code),
  UNIQUE KEY uk_pos_print_connection_legacy_printer (legacy_printer_id),
  KEY idx_pos_print_connection_outlet_active (outlet_id, is_active),
  KEY idx_pos_print_connection_agent_port (agent_host, python_port),
  CONSTRAINT fk_pos_print_connection_outlet
    FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_print_connection_operational_division
    FOREIGN KEY (operational_division_id) REFERENCES mst_operational_division(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Koneksi fisik printer dan agent. Tidak menyimpan layout atau routing dokumen.';

CREATE TABLE IF NOT EXISTS pos_print_general_setting (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_code VARCHAR(60) NOT NULL,
  setting_name VARCHAR(150) NOT NULL,
  outlet_id BIGINT UNSIGNED NULL,
  general_payload LONGTEXT NULL,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_print_general_setting_code (setting_code),
  KEY idx_pos_print_general_setting_outlet (outlet_id, is_active),
  CONSTRAINT fk_pos_print_general_setting_outlet
    FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Branding dan data umum cetak. Layout menentukan apakah data ini ditampilkan atau tidak.';

CREATE TABLE IF NOT EXISTS pos_print_layout (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  layout_code VARCHAR(60) NOT NULL,
  layout_name VARCHAR(150) NOT NULL,
  document_type ENUM('RECEIPT','KITCHEN_TICKET','VOID_SLIP','REFUND_SLIP','DEPOSIT_RECEIPT','SHIFT_CLOSE') NOT NULL,
  layout_payload LONGTEXT NULL,
  description VARCHAR(255) NULL,
  legacy_template_id BIGINT UNSIGNED NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_print_layout_code (layout_code),
  UNIQUE KEY uk_pos_print_layout_legacy_template (legacy_template_id),
  KEY idx_pos_print_layout_document_active (document_type, is_active, is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Layout dokumen dan seluruh switch data tampil/sembunyi untuk cetak POS.';

CREATE TABLE IF NOT EXISTS pos_print_route (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  route_code VARCHAR(80) NOT NULL,
  route_name VARCHAR(180) NOT NULL,
  event_code VARCHAR(60) NOT NULL,
  document_type ENUM('RECEIPT','KITCHEN_TICKET','VOID_SLIP','REFUND_SLIP','DEPOSIT_RECEIPT','SHIFT_CLOSE') NOT NULL,
  outlet_id BIGINT UNSIGNED NULL,
  terminal_id BIGINT UNSIGNED NULL,
  operational_division_id BIGINT UNSIGNED NULL,
  product_division_id BIGINT UNSIGNED NULL,
  content_scope ENUM('MATCHED_DIVISION','ALL_ITEMS') NOT NULL DEFAULT 'ALL_ITEMS',
  connection_id BIGINT UNSIGNED NOT NULL,
  layout_id BIGINT UNSIGNED NOT NULL,
  copy_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  priority INT NOT NULL DEFAULT 100,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_print_route_code (route_code),
  KEY idx_pos_print_route_lookup (event_code, is_active, outlet_id, terminal_id, operational_division_id, product_division_id),
  KEY idx_pos_print_route_connection (connection_id, is_active),
  CONSTRAINT fk_pos_print_route_outlet
    FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_print_route_terminal
    FOREIGN KEY (terminal_id) REFERENCES pos_terminal(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_print_route_operational_division
    FOREIGN KEY (operational_division_id) REFERENCES mst_operational_division(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_print_route_product_division
    FOREIGN KEY (product_division_id) REFERENCES mst_product_division(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_print_route_connection
    FOREIGN KEY (connection_id) REFERENCES pos_print_connection(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pos_print_route_layout
    FOREIGN KEY (layout_id) REFERENCES pos_print_layout(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Aturan eksplisit event, sumber order, koneksi printer, layout, dan copy.';

CREATE TABLE IF NOT EXISTS pos_print_attempt (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  attempt_no VARCHAR(80) NOT NULL,
  event_code VARCHAR(60) NOT NULL,
  document_type VARCHAR(40) NOT NULL,
  attempt_kind ENUM('AUTO','REPRINT','TEST','PREVIEW') NOT NULL DEFAULT 'AUTO',
  status ENUM('GENERATED','SENT','FAILED','SKIPPED','VOID') NOT NULL DEFAULT 'GENERATED',
  route_id BIGINT UNSIGNED NULL,
  connection_id BIGINT UNSIGNED NULL,
  layout_id BIGINT UNSIGNED NULL,
  outlet_id BIGINT UNSIGNED NULL,
  terminal_id BIGINT UNSIGNED NULL,
  order_id BIGINT UNSIGNED NULL,
  payment_id BIGINT UNSIGNED NULL,
  void_id BIGINT UNSIGNED NULL,
  refund_id BIGINT UNSIGNED NULL,
  target_summary LONGTEXT NULL,
  agent_message VARCHAR(500) NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  acknowledged_at DATETIME NULL,
  acknowledged_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_print_attempt_no (attempt_no),
  KEY idx_pos_print_attempt_status_date (status, requested_at),
  KEY idx_pos_print_attempt_order_event (order_id, event_code, requested_at),
  KEY idx_pos_print_attempt_connection_date (connection_id, requested_at),
  CONSTRAINT fk_pos_print_attempt_route
    FOREIGN KEY (route_id) REFERENCES pos_print_route(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_print_attempt_connection
    FOREIGN KEY (connection_id) REFERENCES pos_print_connection(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_print_attempt_layout
    FOREIGN KEY (layout_id) REFERENCES pos_print_layout(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_print_attempt_outlet
    FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_print_attempt_terminal
    FOREIGN KEY (terminal_id) REFERENCES pos_terminal(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_print_attempt_acknowledged_by
    FOREIGN KEY (acknowledged_by) REFERENCES org_employee(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Jejak target cetak dari POS sampai acknowledgement agent browser.';

-- Seed konfigurasi awal hanya dibuat sekali. Menjalankan ulang migration ini tidak menimpa perubahan operator.
-- Pengaturan umum lama dipindahkan ke tabel yang khusus untuk tampilan umum.
INSERT INTO pos_print_general_setting (
  setting_code, setting_name, outlet_id, general_payload, notes, is_active
)
SELECT
  'GLOBAL',
  'Tampilan Umum Semua Outlet',
  NULL,
  COALESCE(NULLIF(TRIM(master_payload), ''), '{}'),
  'Dimigrasikan dari POS-GLOBAL di pos_printer_template_master.',
  1
FROM pos_printer_template_master
WHERE master_code = 'POS-GLOBAL'
ON DUPLICATE KEY UPDATE setting_code = setting_code;

INSERT INTO pos_print_general_setting (
  setting_code, setting_name, outlet_id, general_payload, notes, is_active
)
SELECT
  'GLOBAL',
  'Tampilan Umum Semua Outlet',
  NULL,
  '{}',
  'Dibuat sebagai fallback bila pengaturan umum printer lama belum ada.',
  1
WHERE NOT EXISTS (
  SELECT 1 FROM pos_print_general_setting WHERE setting_code = 'GLOBAL'
);

-- Koneksi lama disalin apa adanya. Kode agent dipertahankan agar agent Python
-- tetap mengenali printer yang sama setelah resolver pindah ke konfigurasi baru.
INSERT INTO pos_print_connection (
  connection_code, connection_name, outlet_id, operational_division_id, location_label,
  connection_type, agent_os, agent_host, agent_printer_code, device_name, mac_address,
  python_port, ip_address, port, paper_width_mm, chars_per_line, default_copy_count,
  cut_mode, open_drawer, notes, legacy_printer_id, is_active
)
SELECT
  CONCAT('PRN-', p.printer_code),
  p.printer_name,
  p.outlet_id,
  od.id,
  NULLIF(TRIM(p.printer_role), ''),
  p.connection_type,
  p.agent_os,
  p.agent_host,
  p.printer_code,
  p.device_name,
  p.mac_address,
  NULLIF(p.python_port, 0),
  p.ip_address,
  p.port,
  CASE WHEN COALESCE(pf.paper_width_mm, 80) = 58 THEN 58 ELSE 80 END,
  GREATEST(24, LEAST(64, COALESCE(pf.chars_per_line, CASE WHEN COALESCE(pf.paper_width_mm, 80) = 58 THEN 32 ELSE 48 END))),
  GREATEST(1, LEAST(10, COALESCE(pf.copies, pf.copy_count, 1))),
  COALESCE(pf.cut_mode, 'PARTIAL'),
  COALESCE(pf.open_drawer, 0),
  'Dimigrasikan dari pos_printer dan pos_printer_profile.',
  p.id,
  p.is_active
FROM pos_printer p
LEFT JOIN pos_printer_profile pf
  ON pf.id = (
    SELECT MAX(px.id)
    FROM pos_printer_profile px
    WHERE px.printer_id = p.id
  )
LEFT JOIN mst_operational_division od
  ON od.code = UPPER(TRIM(COALESCE(p.printer_role, '')))
ON DUPLICATE KEY UPDATE connection_code = connection_code;

-- Template lama menjadi layout. Payload lama tetap dipertahankan, tetapi runtime
-- baru akan selalu mengambil branding/master umum dari pos_print_general_setting.
INSERT INTO pos_print_layout (
  layout_code, layout_name, document_type, layout_payload, description,
  legacy_template_id, is_default, is_active
)
SELECT
  t.template_code,
  t.template_name,
  t.document_type,
  COALESCE(NULLIF(TRIM(t.template_payload), ''), '{}'),
  'Dimigrasikan dari pos_printer_template.',
  t.id,
  t.is_default,
  t.is_active
FROM pos_printer_template t
ON DUPLICATE KEY UPDATE layout_code = layout_code;

-- Layout bawaan hanya dibuat bila jenis dokumen tersebut memang belum punya
-- layout aktif pada data lama. Payload kosong akan dibaca sebagai default aman
-- oleh renderer, lalu dapat diedit dari halaman Layout Cetak.
INSERT INTO pos_print_layout (
  layout_code, layout_name, document_type, layout_payload, description, is_default, is_active
)
SELECT 'LAYOUT-REFUND-DEFAULT', 'Slip Refund Default', 'REFUND_SLIP', '{}',
       'Layout default refund yang dibuat karena template lama belum tersedia.', 1, 1
WHERE NOT EXISTS (
  SELECT 1 FROM pos_print_layout WHERE document_type = 'REFUND_SLIP' AND is_active = 1
);

INSERT INTO pos_print_layout (
  layout_code, layout_name, document_type, layout_payload, description, is_default, is_active
)
SELECT 'LAYOUT-SHIFT-CLOSE-DEFAULT', 'Ringkasan Tutup Kasir Default', 'SHIFT_CLOSE', '{}',
       'Layout default tutup kasir yang dibuat karena template lama belum tersedia.', 1, 1
WHERE NOT EXISTS (
  SELECT 1 FROM pos_print_layout WHERE document_type = 'SHIFT_CLOSE' AND is_active = 1
);

-- Aturan KOT per divisi. CHECKER sengaja memakai ALL_ITEMS sebagai salinan
-- pemeriksaan, sedangkan BAR dan KITCHEN hanya menerima item divisinya sendiri.
INSERT INTO pos_print_route (
  route_code, route_name, event_code, document_type, outlet_id,
  operational_division_id, content_scope, connection_id, layout_id,
  copy_count, priority, notes, is_active
)
SELECT
  CONCAT('ROUTE-ORDER-KOT-BAR-', c.id),
  CONCAT('KOT BAR ke ', c.connection_name),
  'ORDER_CONFIRM_KOT',
  'KITCHEN_TICKET',
  c.outlet_id,
  od.id,
  'MATCHED_DIVISION',
  c.id,
  COALESCE(
    (SELECT id FROM pos_print_layout WHERE layout_code = 'TPL-BAR' AND is_active = 1 LIMIT 1),
    (SELECT id FROM pos_print_layout WHERE document_type = 'KITCHEN_TICKET' AND is_active = 1 ORDER BY is_default DESC, id ASC LIMIT 1)
  ),
  0,
  100,
  'Migrasi route KOT BAR dari role printer lama.',
  CASE WHEN c.is_active = 1 AND COALESCE((SELECT es.auto_print FROM pos_printer_event_setting es WHERE es.event_code = 'ORDER_CONFIRM_KOT' AND es.is_active = 1 LIMIT 1), 1) = 1 THEN 1 ELSE 0 END
FROM pos_print_connection c
JOIN mst_operational_division od ON od.code = 'BAR'
WHERE UPPER(COALESCE(c.location_label, '')) = 'BAR'
ON DUPLICATE KEY UPDATE route_code = route_code;

INSERT INTO pos_print_route (
  route_code, route_name, event_code, document_type, outlet_id,
  operational_division_id, content_scope, connection_id, layout_id,
  copy_count, priority, notes, is_active
)
SELECT
  CONCAT('ROUTE-ORDER-KOT-KITCHEN-', c.id),
  CONCAT('KOT KITCHEN ke ', c.connection_name),
  'ORDER_CONFIRM_KOT',
  'KITCHEN_TICKET',
  c.outlet_id,
  od.id,
  'MATCHED_DIVISION',
  c.id,
  COALESCE(
    (SELECT id FROM pos_print_layout WHERE layout_code = 'TPL-KITCHEN' AND is_active = 1 LIMIT 1),
    (SELECT id FROM pos_print_layout WHERE document_type = 'KITCHEN_TICKET' AND is_active = 1 ORDER BY is_default DESC, id ASC LIMIT 1)
  ),
  0,
  100,
  'Migrasi route KOT KITCHEN dari role printer lama.',
  CASE WHEN c.is_active = 1 AND COALESCE((SELECT es.auto_print FROM pos_printer_event_setting es WHERE es.event_code = 'ORDER_CONFIRM_KOT' AND es.is_active = 1 LIMIT 1), 1) = 1 THEN 1 ELSE 0 END
FROM pos_print_connection c
JOIN mst_operational_division od ON od.code = 'KITCHEN'
WHERE UPPER(COALESCE(c.location_label, '')) = 'KITCHEN'
ON DUPLICATE KEY UPDATE route_code = route_code;

INSERT INTO pos_print_route (
  route_code, route_name, event_code, document_type, outlet_id,
  content_scope, connection_id, layout_id, copy_count, priority, notes, is_active
)
SELECT
  CONCAT('ROUTE-ORDER-KOT-CHECKER-', c.id),
  CONCAT('KOT CHECKER ke ', c.connection_name),
  'ORDER_CONFIRM_KOT',
  'KITCHEN_TICKET',
  c.outlet_id,
  'ALL_ITEMS',
  c.id,
  COALESCE(
    (SELECT id FROM pos_print_layout WHERE layout_code = 'TPL-CHECKER' AND is_active = 1 LIMIT 1),
    (SELECT id FROM pos_print_layout WHERE document_type = 'KITCHEN_TICKET' AND is_active = 1 ORDER BY is_default DESC, id ASC LIMIT 1)
  ),
  0,
  100,
  'Migrasi route KOT CHECKER dari scope ALL printer lama.',
  CASE WHEN c.is_active = 1 AND COALESCE((SELECT es.auto_print FROM pos_printer_event_setting es WHERE es.event_code = 'ORDER_CONFIRM_KOT' AND es.is_active = 1 LIMIT 1), 1) = 1 THEN 1 ELSE 0 END
FROM pos_print_connection c
WHERE UPPER(COALESCE(c.location_label, '')) = 'CHECKER'
ON DUPLICATE KEY UPDATE route_code = route_code;

-- Dokumen kasir memakai koneksi kasir. Tidak ada lagi pemilihan template dari
-- nama printer pada runtime; layout di route ini adalah sumbernya.
INSERT INTO pos_print_route (
  route_code, route_name, event_code, document_type, outlet_id,
  content_scope, connection_id, layout_id, copy_count, priority, notes, is_active
)
SELECT
  CONCAT('ROUTE-PAYMENT-RECEIPT-', c.id),
  CONCAT('Struk pembayaran ke ', c.connection_name),
  'ORDER_PAID_RECEIPT',
  'RECEIPT',
  c.outlet_id,
  'ALL_ITEMS',
  c.id,
  (SELECT id FROM pos_print_layout WHERE layout_code = 'TPL-KASIR' AND is_active = 1 LIMIT 1),
  0,
  100,
  'Migrasi struk pembayaran dari printer kasir lama.',
  CASE WHEN c.is_active = 1 AND COALESCE((SELECT es.auto_print FROM pos_printer_event_setting es WHERE es.event_code = 'ORDER_PAID_RECEIPT' AND es.is_active = 1 LIMIT 1), 1) = 1 THEN 1 ELSE 0 END
FROM pos_print_connection c
WHERE UPPER(COALESCE(c.location_label, '')) = 'KASIR'
ON DUPLICATE KEY UPDATE route_code = route_code;

INSERT INTO pos_print_route (
  route_code, route_name, event_code, document_type, outlet_id,
  content_scope, connection_id, layout_id, copy_count, priority, notes, is_active
)
SELECT
  CONCAT('ROUTE-PREBILL-', c.id),
  CONCAT('Bill sementara ke ', c.connection_name),
  'ORDER_PRE_BILL',
  'RECEIPT',
  c.outlet_id,
  'ALL_ITEMS',
  c.id,
  (SELECT id FROM pos_print_layout WHERE layout_code = 'TPL-KASIR' AND is_active = 1 LIMIT 1),
  0,
  100,
  'Migrasi bill sementara dari printer kasir lama.',
  CASE WHEN c.is_active = 1 AND COALESCE((SELECT es.auto_print FROM pos_printer_event_setting es WHERE es.event_code = 'ORDER_PRE_BILL' AND es.is_active = 1 LIMIT 1), 1) = 1 THEN 1 ELSE 0 END
FROM pos_print_connection c
WHERE UPPER(COALESCE(c.location_label, '')) = 'KASIR'
ON DUPLICATE KEY UPDATE route_code = route_code;

INSERT INTO pos_print_route (
  route_code, route_name, event_code, document_type, outlet_id,
  content_scope, connection_id, layout_id, copy_count, priority, notes, is_active
)
SELECT
  CONCAT('ROUTE-VOID-', c.id),
  CONCAT('Slip void ke ', c.connection_name),
  'VOID_SLIP',
  'VOID_SLIP',
  c.outlet_id,
  'ALL_ITEMS',
  c.id,
  (SELECT id FROM pos_print_layout WHERE document_type = 'VOID_SLIP' AND is_active = 1 ORDER BY is_default DESC, id ASC LIMIT 1),
  0,
  100,
  'Route void awal pada printer kasir.',
  CASE WHEN c.is_active = 1 AND COALESCE((SELECT es.auto_print FROM pos_printer_event_setting es WHERE es.event_code = 'VOID_SLIP' AND es.is_active = 1 LIMIT 1), 1) = 1 THEN 1 ELSE 0 END
FROM pos_print_connection c
WHERE UPPER(COALESCE(c.location_label, '')) = 'KASIR'
ON DUPLICATE KEY UPDATE route_code = route_code;

INSERT INTO pos_print_route (
  route_code, route_name, event_code, document_type, outlet_id,
  content_scope, connection_id, layout_id, copy_count, priority, notes, is_active
)
SELECT
  CONCAT('ROUTE-REFUND-', c.id),
  CONCAT('Slip refund ke ', c.connection_name),
  'REFUND_SLIP',
  'REFUND_SLIP',
  c.outlet_id,
  'ALL_ITEMS',
  c.id,
  (SELECT id FROM pos_print_layout WHERE document_type = 'REFUND_SLIP' AND is_active = 1 ORDER BY is_default DESC, id ASC LIMIT 1),
  0,
  100,
  'Route refund awal pada printer kasir.',
  CASE WHEN c.is_active = 1 AND COALESCE((SELECT es.auto_print FROM pos_printer_event_setting es WHERE es.event_code = 'REFUND_SLIP' AND es.is_active = 1 LIMIT 1), 1) = 1 THEN 1 ELSE 0 END
FROM pos_print_connection c
WHERE UPPER(COALESCE(c.location_label, '')) = 'KASIR'
ON DUPLICATE KEY UPDATE route_code = route_code;

INSERT INTO pos_print_route (
  route_code, route_name, event_code, document_type, outlet_id,
  content_scope, connection_id, layout_id, copy_count, priority, notes, is_active
)
SELECT
  CONCAT('ROUTE-SHIFT-CLOSE-', c.id),
  CONCAT('Ringkasan tutup kasir ke ', c.connection_name),
  'SHIFT_CLOSE_SUMMARY',
  'SHIFT_CLOSE',
  c.outlet_id,
  'ALL_ITEMS',
  c.id,
  (SELECT id FROM pos_print_layout WHERE document_type = 'SHIFT_CLOSE' AND is_active = 1 ORDER BY is_default DESC, id ASC LIMIT 1),
  0,
  100,
  'Route tutup kasir awal pada printer kasir.',
  CASE WHEN c.is_active = 1 AND COALESCE((SELECT es.auto_print FROM pos_printer_event_setting es WHERE es.event_code = 'SHIFT_CLOSE_SUMMARY' AND es.is_active = 1 LIMIT 1), 1) = 1 THEN 1 ELSE 0 END
FROM pos_print_connection c
WHERE UPPER(COALESCE(c.location_label, '')) = 'KASIR'
ON DUPLICATE KEY UPDATE route_code = route_code;

-- Halaman baru memakai permission lebih sempit daripada halaman printer lama.
INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('pos.printer.connection', 'Koneksi Printer POS', 'POS', 'Koneksi fisik printer dan agent lokal.', 1),
  ('pos.printer.general', 'Tampilan Umum Cetak POS', 'POS', 'Branding dan data umum cetak per outlet.', 1),
  ('pos.printer.layout', 'Layout Cetak POS', 'POS', 'Pengaturan data yang tampil atau disembunyikan pada dokumen.', 1),
  ('pos.printer.rule', 'Aturan Cetak POS', 'POS', 'Aturan event, sumber order, printer tujuan, layout, dan copy.', 1),
  ('pos.printer.monitor', 'Monitor Cetak POS', 'POS', 'Riwayat target cetak, hasil agent, dan kegagalan reprint.', 1),
  ('pos.printer.guide', 'Panduan Printer POS', 'POS', 'Panduan operator dan teknisi printer POS.', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  role.id,
  page.id,
  CASE
    WHEN page.page_code IN ('pos.printer.monitor', 'pos.printer.guide') THEN 1
    WHEN role.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_FIN','HOD') THEN 1
    ELSE 0
  END,
  CASE
    WHEN page.page_code = 'pos.printer.connection' AND role.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN') THEN 1
    WHEN page.page_code IN ('pos.printer.general','pos.printer.layout','pos.printer.rule') AND role.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN') THEN 1
    ELSE 0
  END,
  CASE
    WHEN page.page_code = 'pos.printer.connection' AND role.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN') THEN 1
    WHEN page.page_code IN ('pos.printer.general','pos.printer.layout','pos.printer.rule') AND role.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN') THEN 1
    ELSE 0
  END,
  CASE WHEN role.role_code IN ('SUPERADMIN','MGR','ADMIN') THEN 1 ELSE 0 END,
  CASE WHEN role.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_FIN') THEN 1 ELSE 0 END,
  NOW()
FROM auth_role role
JOIN sys_page page ON page.page_code IN (
  'pos.printer.connection','pos.printer.general','pos.printer.layout',
  'pos.printer.rule','pos.printer.monitor','pos.printer.guide'
)
WHERE role.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_FIN','HOD','KASIR','BARISTA')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

-- Kasir dan barista tetap dapat membuka hub printer lama untuk melihat panduan,
-- tetapi tidak lagi dapat membuat/mengubah device atau template melalui permission lama.
UPDATE auth_role_permission permission
JOIN auth_role role ON role.id = permission.role_id
JOIN sys_page page ON page.id = permission.page_id
SET
  permission.can_create = 0,
  permission.can_edit = 0,
  permission.can_delete = 0,
  permission.updated_at = CURRENT_TIMESTAMP
WHERE page.page_code = 'pos.printer.index'
  AND role.role_code IN ('KASIR','BARISTA');

COMMIT;

SELECT 'pos_print_connection' AS check_key, COUNT(*) AS total_rows FROM pos_print_connection
UNION ALL SELECT 'pos_print_general_setting', COUNT(*) FROM pos_print_general_setting
UNION ALL SELECT 'pos_print_layout', COUNT(*) FROM pos_print_layout
UNION ALL SELECT 'pos_print_route', COUNT(*) FROM pos_print_route
UNION ALL SELECT 'pos_print_attempt', COUNT(*) FROM pos_print_attempt
UNION ALL SELECT 'active_pos_print_route', COUNT(*) FROM pos_print_route WHERE is_active = 1;

SELECT
  route.route_code,
  route.event_code,
  route.document_type,
  COALESCE(outlet.outlet_name, 'GLOBAL') AS outlet_name,
  COALESCE(division.name, 'SEMUA DIVISI') AS source_division,
  route.content_scope,
  connection.connection_name,
  layout.layout_name,
  route.is_active
FROM pos_print_route route
JOIN pos_print_connection connection ON connection.id = route.connection_id
JOIN pos_print_layout layout ON layout.id = route.layout_id
LEFT JOIN pos_outlet outlet ON outlet.id = route.outlet_id
LEFT JOIN mst_operational_division division ON division.id = route.operational_division_id
ORDER BY route.event_code, route.priority, route.id;

