-- ============================================================
-- Tahap 1 — Auth, RBAC & Sidebar
-- File    : 2026-05-01a_auth_rbac_schema.sql
-- DB      : db_finance
-- Catatan : Fondasi seluruh aplikasi. Jalankan ini pertama kali.
-- ============================================================

SET NAMES utf8mb4;

-- ============================================================
-- ci_sessions — Tabel session CI3 (database-backed session)
-- ============================================================
CREATE TABLE IF NOT EXISTS ci_sessions (
  id          VARCHAR(128) NOT NULL,
  ip_address  VARCHAR(45)  NOT NULL,
  timestamp   INT(10) UNSIGNED NOT NULL DEFAULT 0,
  data        BLOB         NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ci_sessions_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='CI3 database session storage';

-- ============================================================
-- 1. auth_user — Akun login
-- ============================================================
CREATE TABLE IF NOT EXISTS auth_user (
  id              BIGINT UNSIGNED    NOT NULL AUTO_INCREMENT,
  employee_id     BIGINT UNSIGNED    NULL,           -- diisi setelah Tahap 3 (org_employee)
  username        VARCHAR(60)        NOT NULL,
  email           VARCHAR(150)       NULL,
  password_hash   VARCHAR(255)       NOT NULL,
  is_active       TINYINT(1)         NOT NULL DEFAULT 1,
  last_login_at   DATETIME           NULL,
  created_at      DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME           NULL     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_auth_user_username (username),
  UNIQUE KEY uk_auth_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Akun login pengguna aplikasi';

-- ============================================================
-- 2. auth_session_log — Log sesi login/logout (audit trail)
-- ============================================================
CREATE TABLE IF NOT EXISTS auth_session_log (
  id          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED  NOT NULL,
  ip_address  VARCHAR(45)      NOT NULL,
  user_agent  VARCHAR(255)     NULL,
  login_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  logout_at   DATETIME         NULL,
  created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_auth_session_user (user_id),
  KEY idx_auth_session_login (login_at),
  CONSTRAINT fk_auth_session_user FOREIGN KEY (user_id) REFERENCES auth_user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Log sesi login dan logout';

-- ============================================================
-- 3. auth_role — Role / grup izin
-- ============================================================
CREATE TABLE IF NOT EXISTS auth_role (
  id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  role_code    VARCHAR(50)      NOT NULL,
  role_name    VARCHAR(100)     NOT NULL,
  description  VARCHAR(255)     NULL,
  is_active    TINYINT(1)       NOT NULL DEFAULT 1,
  created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME         NULL     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_auth_role_code (role_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Role / grup izin akses';

-- ============================================================
-- 4. sys_page — Daftar halaman yang dikontrol aksesnya
-- ============================================================
CREATE TABLE IF NOT EXISTS sys_page (
  id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  page_code    VARCHAR(100)     NOT NULL,   -- format: modul.controller.aksi
  page_name    VARCHAR(150)     NOT NULL,   -- nama tampilan di matrix izin
  module       VARCHAR(60)      NOT NULL,   -- pengelompokan: POS, HR, Finance, dll.
  description  VARCHAR(255)     NULL,
  is_active    TINYINT(1)       NOT NULL DEFAULT 1,
  created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME         NULL     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_sys_page_code (page_code),
  KEY idx_sys_page_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Daftar halaman/fitur yang bisa dikontrol aksesnya';

-- ============================================================
-- 5. auth_role_permission — Izin role per halaman + CRUD
-- ============================================================
CREATE TABLE IF NOT EXISTS auth_role_permission (
  id          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  role_id     BIGINT UNSIGNED  NOT NULL,
  page_id     BIGINT UNSIGNED  NOT NULL,
  can_view    TINYINT(1)       NOT NULL DEFAULT 0,  -- boleh buka halaman
  can_create  TINYINT(1)       NOT NULL DEFAULT 0,  -- boleh tambah data
  can_edit    TINYINT(1)       NOT NULL DEFAULT 0,  -- boleh ubah data
  can_delete  TINYINT(1)       NOT NULL DEFAULT 0,  -- boleh hapus data
  can_export  TINYINT(1)       NOT NULL DEFAULT 0,  -- boleh export laporan
  created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME         NULL     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_role_page (role_id, page_id),
  KEY idx_auth_role_perm_role (role_id),
  KEY idx_auth_role_perm_page (page_id),
  CONSTRAINT fk_auth_role_perm_role FOREIGN KEY (role_id) REFERENCES auth_role(id),
  CONSTRAINT fk_auth_role_perm_page FOREIGN KEY (page_id) REFERENCES sys_page(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Izin CRUD per role per halaman';

-- ============================================================
-- 6. auth_user_role — Assignment role ke user
-- ============================================================
CREATE TABLE IF NOT EXISTS auth_user_role (
  id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED  NOT NULL,
  role_id      BIGINT UNSIGNED  NOT NULL,
  assigned_by  BIGINT UNSIGNED  NULL,      -- user_id yang melakukan assign
  assigned_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_user_role (user_id, role_id),
  KEY idx_auth_user_role_user (user_id),
  KEY idx_auth_user_role_role (role_id),
  CONSTRAINT fk_auth_user_role_user    FOREIGN KEY (user_id) REFERENCES auth_user(id),
  CONSTRAINT fk_auth_user_role_role    FOREIGN KEY (role_id) REFERENCES auth_role(id),
  CONSTRAINT fk_auth_user_role_assigner FOREIGN KEY (assigned_by) REFERENCES auth_user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Assignment role ke user';

-- ============================================================
-- 7. auth_user_permission_override — Override izin per user
-- ============================================================
CREATE TABLE IF NOT EXISTS auth_user_permission_override (
  id             BIGINT UNSIGNED       NOT NULL AUTO_INCREMENT,
  user_id        BIGINT UNSIGNED       NOT NULL,
  page_id        BIGINT UNSIGNED       NOT NULL,
  override_type  ENUM('GRANT','REVOKE') NOT NULL,  -- GRANT=tambah, REVOKE=cabut
  can_view       TINYINT(1)            NOT NULL DEFAULT 0,
  can_create     TINYINT(1)            NOT NULL DEFAULT 0,
  can_edit       TINYINT(1)            NOT NULL DEFAULT 0,
  can_delete     TINYINT(1)            NOT NULL DEFAULT 0,
  can_export     TINYINT(1)            NOT NULL DEFAULT 0,
  reason         VARCHAR(255)          NULL,
  overridden_by  BIGINT UNSIGNED       NULL,       -- user_id admin yang buat override
  created_at     DATETIME              NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME              NULL     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_user_page_override (user_id, page_id),
  KEY idx_auth_override_user (user_id),
  CONSTRAINT fk_auth_override_user      FOREIGN KEY (user_id)       REFERENCES auth_user(id),
  CONSTRAINT fk_auth_override_page      FOREIGN KEY (page_id)       REFERENCES sys_page(id),
  CONSTRAINT fk_auth_override_by        FOREIGN KEY (overridden_by) REFERENCES auth_user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Override izin khusus per user (tambah/cabut dari role)';

-- ============================================================
-- 8. sys_menu — Struktur sidebar menu
-- ============================================================
CREATE TABLE IF NOT EXISTS sys_menu (
  id           BIGINT UNSIGNED           NOT NULL AUTO_INCREMENT,
  parent_id    BIGINT UNSIGNED           NULL,       -- NULL = item level atas (heading/root)
  menu_code    VARCHAR(80)               NOT NULL,
  menu_label   VARCHAR(100)              NOT NULL,
  icon         VARCHAR(80)               NULL,       -- nama ikon CSS (misal: fa-shopping-cart)
  url          VARCHAR(255)              NULL,       -- NULL jika hanya heading/group
  page_id      BIGINT UNSIGNED           NULL,       -- NULL = selalu tampil (tidak perlu izin)
  sort_order   INT                       NOT NULL DEFAULT 0,
  is_active    TINYINT(1)               NOT NULL DEFAULT 1,
  sidebar_type ENUM('MAIN','MY')         NOT NULL DEFAULT 'MAIN',
  created_at   DATETIME                  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME                  NULL     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_sys_menu_code (menu_code),
  KEY idx_sys_menu_parent (parent_id),
  KEY idx_sys_menu_sidebar (sidebar_type, sort_order),
  CONSTRAINT fk_sys_menu_parent FOREIGN KEY (parent_id) REFERENCES sys_menu(id),
  CONSTRAINT fk_sys_menu_page   FOREIGN KEY (page_id)   REFERENCES sys_page(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Struktur item sidebar menu (MAIN=operasional, MY=pribadi)';

-- ============================================================
-- 9. sys_sidebar_favorite — Favorit sidebar per user
-- ============================================================
CREATE TABLE IF NOT EXISTS sys_sidebar_favorite (
  id          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED  NOT NULL,
  menu_id     BIGINT UNSIGNED  NOT NULL,
  sort_order  INT              NOT NULL DEFAULT 0,
  created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_sidebar_fav (user_id, menu_id),
  KEY idx_sidebar_fav_user (user_id),
  CONSTRAINT fk_sidebar_fav_user FOREIGN KEY (user_id) REFERENCES auth_user(id),
  CONSTRAINT fk_sidebar_fav_menu FOREIGN KEY (menu_id) REFERENCES sys_menu(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Favorit menu sidebar per user';

-- ============================================================
-- SEED DATA — Role default
-- ============================================================
INSERT INTO auth_role (role_code, role_name, description, is_active) VALUES
  ('SUPERADMIN', 'Super Admin',         'Bypass semua izin, akses penuh', 1),
  ('CEO',        'CEO / Pemilik',       'Akses semua modul operasional', 1),
  ('MGR',        'Manajer',             'Akses operasional + laporan', 1),
  ('ADMIN',      'Admin',               'Akses operasional umum', 1),
  ('KASIR',      'Kasir',               'Akses POS kasir', 1),
  ('BARISTA',    'Barista',             'Akses POS + produksi komponen', 1),
  ('CHEF',       'Chef / Dapur',        'Akses produksi + stok dapur', 1),
  ('ADM_GDG',    'Admin Gudang',        'Akses gudang + pembelian', 1),
  ('ADM_HR',     'Admin HR',            'Akses HR + absensi + payroll', 1),
  ('ADM_FIN',    'Admin Keuangan',      'Akses modul keuangan', 1),
  ('STAFF',      'Staff Umum',          'Hanya akses My (data pribadi)', 1)
ON DUPLICATE KEY UPDATE
  role_name   = VALUES(role_name),
  description = VALUES(description),
  updated_at  = CURRENT_TIMESTAMP;

-- ============================================================
-- SEED DATA — sys_menu sidebar MAIN (heading awal, tanpa page_id)
-- Halaman spesifik di-seed saat tiap modul dibuat
-- ============================================================
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id) VALUES
  ('dashboard',       'Dashboard',       'ri-home-smile-line',          '/',     NULL, 1,  1, 'MAIN', NULL),
  ('grp.pos',         'POS & Kasir',     'ri-store-2-line',             NULL,    NULL, 10, 1, 'MAIN', NULL),
  ('grp.inventory',   'Gudang',          'ri-archive-2-line',           NULL,    NULL, 20, 1, 'MAIN', NULL),
  ('grp.material',    'Bahan Baku',      'ri-flask-line',               NULL,    NULL, 25, 1, 'MAIN', NULL),
  ('grp.production',  'Produksi',        'ri-tools-line',               NULL,    NULL, 30, 1, 'MAIN', NULL),
  ('grp.purchase',    'Pembelian',       'ri-shopping-cart-2-line',     NULL,    NULL, 40, 1, 'MAIN', NULL),
  ('grp.hr',          'Karyawan & HR',   'ri-group-line',               NULL,    NULL, 50, 1, 'MAIN', NULL),
  ('grp.payroll',     'Penggajian',      'ri-money-dollar-circle-line', NULL,    NULL, 60, 1, 'MAIN', NULL),
  ('grp.finance',     'Keuangan',        'ri-bank-line',                NULL,    NULL, 70, 1, 'MAIN', NULL),
  ('grp.reports',     'Laporan',         'ri-bar-chart-2-line',         NULL,    NULL, 80, 1, 'MAIN', NULL),
  ('grp.master',      'Master Data',     'ri-database-2-line',          NULL,    NULL, 90, 1, 'MAIN', NULL),
  ('grp.system',      'Sistem',          'ri-settings-3-line',          NULL,    NULL, 99, 1, 'MAIN', NULL)
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  sort_order = VALUES(sort_order),
  updated_at = CURRENT_TIMESTAMP;

-- Sub-menu Sistem (Auth)
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  code, label, icon, url, NULL, sort_order, 1, 'MAIN',
  (SELECT id FROM sys_menu WHERE menu_code = 'grp.system')
FROM (
  ('sys.users',              'Manajemen User',    'ri-user-settings-line',  '/users',           1 sort_order UNION ALL
  SELECT 'sys.roles',              'Manajemen Role',   'ri-shield-keyhole-line', '/roles',                         2            UNION ALL
  SELECT 'sys.access_control',     'Kontrol Akses',    'ri-key-2-line',          '/access-control',                3
) t
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  sort_order = VALUES(sort_order),
  updated_at = CURRENT_TIMESTAMP;

-- ============================================================
-- SEED DATA — sys_menu sidebar MY (data pribadi karyawan)
-- Tidak perlu page_id karena semua karyawan login boleh akses
-- ============================================================
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id) VALUES
  ('my.home',         'Beranda Saya',       'ri-home-3-line',              '/my',                    NULL, 1, 1, 'MY', NULL),
  ('my.attendance',   'Absensi Saya',       'ri-time-line',                '/my/attendance',         NULL, 2, 1, 'MY', NULL),
  ('my.leave',        'Pengajuan Izin',     'ri-hotel-bed-line',           '/my/leave-requests',     NULL, 3, 1, 'MY', NULL),
  ('my.payroll',      'Slip Gaji',          'ri-file-list-3-line',         '/my/payroll',            NULL, 4, 1, 'MY', NULL),
  ('my.meal',         'Uang Makan',         'ri-restaurant-line',          '/my/meal-ledger',        NULL, 5, 1, 'MY', NULL),
  ('my.cash_advance', 'Kasbon Saya',        'ri-hand-coin-line',           '/my/cash-advance',       NULL, 6, 1, 'MY', NULL),
  ('my.profile',      'Profil & Data Diri', 'ri-user-3-line',              '/my/profile',            NULL, 7, 1, 'MY', NULL)
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  sort_order = VALUES(sort_order),
  updated_at = CURRENT_TIMESTAMP;

-- ============================================================
-- SEED DATA — Superadmin user default
-- Password: 'admin123' (GANTI SEGERA setelah install!)
-- Hash bcrypt dari 'admin123'
-- ============================================================
INSERT INTO auth_user (username, email, password_hash, is_active)
VALUES (
  'superadmin',
  'admin@finance.local',
  '$2y$12$eSkvMrWgqdGWAHUZG6NN1usViIILMEAnUVNl18Qyzt0bTdFKXoK5C',  -- password: admin123
  1
)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Assign SUPERADMIN role ke user superadmin
INSERT INTO auth_user_role (user_id, role_id, assigned_at)
SELECT u.id, r.id, NOW()
FROM auth_user u, auth_role r
WHERE u.username = 'superadmin' AND r.role_code = 'SUPERADMIN'
ON DUPLICATE KEY UPDATE assigned_at = assigned_at;

-- ============================================================
-- SEED DATA — sys_page untuk modul AUTH / SISTEM
-- ============================================================
INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
  ('auth.users.index',       'Daftar User',           'SISTEM', 'Lihat daftar semua akun user',          1),
  ('auth.users.manage',      'Kelola User',            'SISTEM', 'Buat/edit/nonaktifkan user',             1),
  ('auth.users.permissions', 'Override Izin User',     'SISTEM', 'Set override izin per user',             1),
  ('auth.roles.index',       'Daftar Role',            'SISTEM', 'Lihat daftar semua role',                1),
  ('auth.roles.manage',      'Kelola Role',            'SISTEM', 'Buat/edit/hapus role',                   1),
  ('auth.roles.matrix',      'Matrix Izin Role',       'SISTEM', 'Atur izin CRUD per halaman untuk role',  1)
ON DUPLICATE KEY UPDATE
  page_name   = VALUES(page_name),
  description = VALUES(description),
  updated_at  = CURRENT_TIMESTAMP;

-- Update sys_menu untuk link ke halaman auth (tambahkan page_id)
UPDATE sys_menu m
INNER JOIN sys_page p ON p.page_code = 'auth.users.index'
SET m.page_id = p.id
WHERE m.menu_code = 'sys.users';

UPDATE sys_menu m
INNER JOIN sys_page p ON p.page_code = 'auth.roles.index'
SET m.page_id = p.id
WHERE m.menu_code = 'sys.roles';

UPDATE sys_menu m
INNER JOIN sys_page p ON p.page_code = 'auth.users.permissions'
SET m.page_id = p.id
WHERE m.menu_code = 'sys.access_control';
