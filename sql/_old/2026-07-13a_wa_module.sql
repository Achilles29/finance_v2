SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-13a_wa_module.sql
-- Tujuan : Modul WhatsApp — tabel, halaman sidebar, role matrix
-- ============================================================

START TRANSACTION;

-- ─────────────────────────────────────────────────────────────
-- 1. TABLES
-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS wa_session (
    id        TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    phone_number   VARCHAR(30)  NULL,
    status         ENUM('CONNECTED','DISCONNECTED','WAITING_QR','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN',
    qr_data        TEXT         NULL,
    bot_api_url    VARCHAR(255) NOT NULL DEFAULT 'http://127.0.0.1:3070',
    bot_api_token  VARCHAR(100) NOT NULL DEFAULT 'local-dev-token',
    last_ping_at   DATETIME     NULL,
    connected_at   DATETIME     NULL,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO wa_session (id) VALUES (1);

CREATE TABLE IF NOT EXISTS wa_template (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    template_code   VARCHAR(80)     NOT NULL,
    name            VARCHAR(150)    NOT NULL,
    category        ENUM('BROADCAST','GROUP','PROMO','INFO','REMINDER','CUSTOM') NOT NULL DEFAULT 'BROADCAST',
    body            TEXT            NOT NULL COMMENT 'Gunakan {{variable}} untuk variabel dinamis',
    sample_variables JSON           NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_by      INT UNSIGNED    NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_template_code (template_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wa_broadcast (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(200)    NOT NULL,
    template_id     INT UNSIGNED    NULL,
    custom_message  TEXT            NULL COMMENT 'Pesan custom, override template',
    target_type     ENUM('MANUAL','SELECTED_MEMBERS','ALL_MEMBERS','MEMBER_ACTIVE','CUSTOM') NOT NULL DEFAULT 'MANUAL',
    status          ENUM('DRAFT','QUEUED','SENDING','DONE','FAILED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    scheduled_at    DATETIME        NULL,
    total_targets   INT UNSIGNED    NOT NULL DEFAULT 0,
    total_sent      INT UNSIGNED    NOT NULL DEFAULT 0,
    total_failed    INT UNSIGNED    NOT NULL DEFAULT 0,
    notes           TEXT            NULL,
    created_by      INT UNSIGNED    NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at      DATETIME        NULL,
    finished_at     DATETIME        NULL,
    KEY idx_status      (status),
    KEY idx_scheduled   (scheduled_at),
    KEY idx_created     (created_at),
    KEY idx_template    (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wa_broadcast_line (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    broadcast_id    INT UNSIGNED    NOT NULL,
    phone_number    VARCHAR(30)     NOT NULL,
    display_name    VARCHAR(100)    NULL,
    variables_json  JSON            NULL,
    resolved_message TEXT           NULL,
    status          ENUM('PENDING','SENT','FAILED','SKIPPED') NOT NULL DEFAULT 'PENDING',
    sent_at         DATETIME        NULL,
    error_msg       VARCHAR(500)    NULL,
    retry_count     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_broadcast   (broadcast_id),
    KEY idx_status      (status),
    KEY idx_phone       (phone_number),
    FOREIGN KEY (broadcast_id) REFERENCES wa_broadcast(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wa_group_map (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    group_key       VARCHAR(80)     NOT NULL,
    group_name      VARCHAR(150)    NOT NULL,
    group_jid       VARCHAR(100)    NULL COMMENT 'WhatsApp group JID, e.g. 120363147815009475@g.us',
    purpose         VARCHAR(60)     NULL COMMENT 'OMZET, HOD, TEAM, PROMO, dll',
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    notes           TEXT            NULL,
    last_sent_at    DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_group_key (group_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wa_send_log (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    broadcast_id    INT UNSIGNED    NULL,
    source          ENUM('BROADCAST','MANUAL','GROUP','SYSTEM','SCHEDULED') NOT NULL DEFAULT 'MANUAL',
    phone_number    VARCHAR(30)     NULL,
    group_jid       VARCHAR(100)    NULL,
    display_name    VARCHAR(100)    NULL,
    message_preview VARCHAR(500)    NULL,
    status          ENUM('SENT','FAILED','PENDING') NOT NULL DEFAULT 'PENDING',
    http_status     SMALLINT        NULL,
    error_detail    VARCHAR(500)    NULL,
    sent_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_broadcast   (broadcast_id),
    KEY idx_phone       (phone_number),
    KEY idx_sent_at     (sent_at),
    KEY idx_status      (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- Seed: template contoh
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO wa_template (template_code, name, category, body, sample_variables) VALUES
(
    'promo_umum',
    'Promo Umum',
    'PROMO',
    'Halo {{nama}}! 👋\n\nKami dari *Namua Coffee* ingin menyampaikan promo spesial untuk Anda:\n\n🎉 *{{judul_promo}}*\n📅 Berlaku: {{tanggal_promo}}\n\n{{deskripsi}}\n\nYuk segera kunjungi kami! ☕\n_Namua Coffee_',
    '{"nama":"Budi","judul_promo":"Diskon 20% Semua Minuman","tanggal_promo":"13-20 Juli 2026","deskripsi":"Nikmati diskon 20% untuk semua menu minuman setiap hari Senin-Jumat."}'
),
(
    'reminder_visit',
    'Pengingat Kunjungan',
    'REMINDER',
    'Halo {{nama}}! 😊\n\nKami kangen kamu di *Namua Coffee*! ☕\n\nSudah {{hari_terakhir}} hari nih sejak kunjungan terakhir kamu.\n\nKunjungi kami dan nikmati promo eksklusif member:\n✨ {{promo_member}}\n\nSampai jumpa! 🤗\n_Namua Coffee_',
    '{"nama":"Sari","hari_terakhir":"30","promo_member":"Free upgrade size untuk semua minuman"}'
),
(
    'info_menu_baru',
    'Info Menu Baru',
    'INFO',
    'Halo {{nama}}! 🍵\n\nAda yang baru di *Namua Coffee*!\n\n🆕 *{{nama_menu}}*\n💰 Harga: {{harga}}\n📝 {{deskripsi_menu}}\n\nJangan sampai kelewatan ya! Tersedia mulai {{tanggal_mulai}}.\n\nSampai jumpa di Namua Coffee! ☕\n_Namua Coffee_',
    '{"nama":"Andi","nama_menu":"Matcha Latte Premium","harga":"Rp 35.000","deskripsi_menu":"Matcha premium grade A dengan susu oat, rasa autentik dan creamy.","tanggal_mulai":"15 Juli 2026"}'
);

-- ─────────────────────────────────────────────────────────────
-- Seed: group dari wa-bot
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO wa_group_map (group_key, group_name, group_jid, purpose) VALUES
('omzet_harian',   'CAFE PUSAT',       '120363147815009475@g.us', 'OMZET'),
('hod_namua',      'HOD NAMUA',        '120363239042303962@g.us', 'HOD'),
('superteam_namua','SUPERTEAM NAMUA',  '120363221130631505@g.us', 'TEAM');

-- ─────────────────────────────────────────────────────────────
-- 2. SYS_PAGE — daftarkan semua halaman WA
-- ─────────────────────────────────────────────────────────────
INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
('wa.dashboard',  'WA Dashboard',       'WA', 'Dashboard status koneksi dan statistik WhatsApp Bot', 1),
('wa.broadcast',  'WA Broadcast',       'WA', 'Manajemen kampanye broadcast WhatsApp ke pelanggan',  1),
('wa.template',   'WA Template',        'WA', 'Kelola template pesan WhatsApp',                      1),
('wa.group',      'WA Grup',            'WA', 'Mapping dan manajemen grup WhatsApp',                 1),
('wa.log',        'WA Log Pengiriman',  'WA', 'Riwayat log pengiriman pesan WhatsApp',              1),
('wa.settings',   'WA Pengaturan',      'WA', 'Konfigurasi koneksi dan pengaturan WhatsApp Bot',    1)
ON DUPLICATE KEY UPDATE
    page_name   = VALUES(page_name),
    module      = VALUES(module),
    description = VALUES(description),
    is_active   = VALUES(is_active),
    updated_at  = CURRENT_TIMESTAMP;

-- ─────────────────────────────────────────────────────────────
-- 3. SYS_MENU — grup WA
-- ─────────────────────────────────────────────────────────────
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
VALUES ('grp.wa', 'WhatsApp', 'ri-whatsapp-line', '#', NULL, 95, 1, 'MAIN', NULL)
ON DUPLICATE KEY UPDATE
    menu_label  = VALUES(menu_label),
    icon        = VALUES(icon),
    sort_order  = VALUES(sort_order),
    is_active   = VALUES(is_active),
    updated_at  = CURRENT_TIMESTAMP;

-- ─────────────────────────────────────────────────────────────
-- 4. SYS_MENU — child items di bawah grp.wa
-- ─────────────────────────────────────────────────────────────
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'wa.dashboard', 'Dashboard', 'ri-dashboard-line', 'wa/dashboard',
    p.id, 1, 1, 'MAIN', g.id
FROM sys_page p, sys_menu g
WHERE p.page_code = 'wa.dashboard' AND g.menu_code = 'grp.wa'
ON DUPLICATE KEY UPDATE
    menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url),
    page_id = VALUES(page_id), parent_id = VALUES(parent_id),
    sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'wa.broadcast', 'Broadcast', 'ri-broadcast-line', 'wa/broadcast',
    p.id, 2, 1, 'MAIN', g.id
FROM sys_page p, sys_menu g
WHERE p.page_code = 'wa.broadcast' AND g.menu_code = 'grp.wa'
ON DUPLICATE KEY UPDATE
    menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url),
    page_id = VALUES(page_id), parent_id = VALUES(parent_id),
    sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'wa.template', 'Template Pesan', 'ri-file-text-line', 'wa/template',
    p.id, 3, 1, 'MAIN', g.id
FROM sys_page p, sys_menu g
WHERE p.page_code = 'wa.template' AND g.menu_code = 'grp.wa'
ON DUPLICATE KEY UPDATE
    menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url),
    page_id = VALUES(page_id), parent_id = VALUES(parent_id),
    sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'wa.group', 'Grup WA', 'ri-group-2-line', 'wa/group',
    p.id, 4, 1, 'MAIN', g.id
FROM sys_page p, sys_menu g
WHERE p.page_code = 'wa.group' AND g.menu_code = 'grp.wa'
ON DUPLICATE KEY UPDATE
    menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url),
    page_id = VALUES(page_id), parent_id = VALUES(parent_id),
    sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'wa.log', 'Log Pengiriman', 'ri-history-line', 'wa/log',
    p.id, 5, 1, 'MAIN', g.id
FROM sys_page p, sys_menu g
WHERE p.page_code = 'wa.log' AND g.menu_code = 'grp.wa'
ON DUPLICATE KEY UPDATE
    menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url),
    page_id = VALUES(page_id), parent_id = VALUES(parent_id),
    sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'wa.settings', 'Pengaturan', 'ri-settings-3-line', 'wa/settings',
    p.id, 6, 1, 'MAIN', g.id
FROM sys_page p, sys_menu g
WHERE p.page_code = 'wa.settings' AND g.menu_code = 'grp.wa'
ON DUPLICATE KEY UPDATE
    menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url),
    page_id = VALUES(page_id), parent_id = VALUES(parent_id),
    sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

-- ─────────────────────────────────────────────────────────────
-- 5. ROLE MATRIX — berikan akses SUPERADMIN full access
-- ─────────────────────────────────────────────────────────────
INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT r.id, p.id, 1, 1, 1, 1, 1, NOW()
FROM auth_role r
CROSS JOIN sys_page p
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'ADMIN')
  AND p.page_code IN ('wa.dashboard','wa.broadcast','wa.template','wa.group','wa.log','wa.settings')
  AND NOT EXISTS (
    SELECT 1 FROM auth_role_permission x
    WHERE x.role_id = r.id AND x.page_id = p.id
  );

-- Manager: hanya view + create broadcast
INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT r.id, p.id, 1, 1, 0, 0, 1, NOW()
FROM auth_role r
CROSS JOIN sys_page p
WHERE r.role_code IN ('MANAGER', 'GM')
  AND p.page_code IN ('wa.dashboard','wa.broadcast','wa.log')
  AND NOT EXISTS (
    SELECT 1 FROM auth_role_permission x
    WHERE x.role_id = r.id AND x.page_id = p.id
  );

COMMIT;

-- ─────────────────────────────────────────────────────────────
-- Verifikasi
-- SELECT p.page_code, m.menu_code, m.url FROM sys_page p JOIN sys_menu m ON m.page_id = p.id WHERE p.module = 'WA';
-- SELECT COUNT(*) FROM auth_role_permission rp JOIN sys_page p ON p.id = rp.page_id WHERE p.module = 'WA';
-- ─────────────────────────────────────────────────────────────
