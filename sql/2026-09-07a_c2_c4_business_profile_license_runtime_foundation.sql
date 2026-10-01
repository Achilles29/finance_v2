-- C2/C4 commercial foundation. This migration is deliberately metadata-only:
-- it creates local profile/licensing registries and RBAC navigation, but never
-- alters sales, inventory, accounting, employee, or historical transaction data.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `sys_business_profile` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Canonical local business identity; outlet and document settings may override it';

CREATE TABLE IF NOT EXISTS `sys_business_profile_audit` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Append-only audit for business profile changes';

CREATE TABLE IF NOT EXISTS `lic_installation` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One local installation identity; no issuer secret is stored here';

CREATE TABLE IF NOT EXISTS `lic_license_cache` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Signed entitlement cache only; local rows never grant features by themselves';

CREATE TABLE IF NOT EXISTS `lic_feature` (
  `feature_code` varchar(100) NOT NULL,
  `feature_name` varchar(190) NOT NULL,
  `category_name` varchar(100) DEFAULT NULL,
  `depends_on_json` longtext DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `source_manifest_version` varchar(80) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`feature_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Read-only mirror of signed product feature catalog';

CREATE TABLE IF NOT EXISTS `lic_feature_cache` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Features parsed from a verified signed entitlement';

CREATE TABLE IF NOT EXISTS `lic_device_activation` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Terminal/device registry; activation will be controlled by signed entitlement';

CREATE TABLE IF NOT EXISTS `lic_activation_audit` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Append-only activation and replacement audit';

CREATE TABLE IF NOT EXISTS `lic_runtime_audit` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Privacy-minimal FeatureGate audit; no request body or entitlement secret';

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
('system.business_profile', 'Profil Usaha & Tampilan', 'SYSTEM', 'Identitas usaha, lokalitas, logo, dan fallback dokumen customer', 1),
('system.license.index', 'Lisensi & Aktivasi', 'SYSTEM', 'Status entitlement lokal dan kesiapan aktivasi tanpa secret penerbit', 1)
ON DUPLICATE KEY UPDATE page_name = VALUES(page_name), module = VALUES(module),
  description = VALUES(description), is_active = 1, updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT seed.menu_code, seed.menu_label, seed.icon, seed.url, page.id, seed.sort_order, 1, 'MAIN', parent.id
FROM (
  SELECT 'system.business_profile' AS menu_code, 'Profil Usaha & Tampilan' AS menu_label, 'ri-building-2-line' AS icon, 'system/business-profile' AS url, 5 AS sort_order, 'system.business_profile' AS page_code
  UNION ALL SELECT 'system.license', 'Lisensi & Aktivasi', 'ri-key-2-line', 'system/license', 7, 'system.license.index'
) seed
JOIN sys_page page ON page.page_code = seed.page_code
JOIN sys_menu parent ON parent.menu_code = 'grp.system'
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url),
  page_id = VALUES(page_id), sort_order = VALUES(sort_order), is_active = 1,
  sidebar_type = 'MAIN', parent_id = VALUES(parent_id), updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission
  (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT role.id, page.id, 1, 1, 1, 1, 1, NOW()
FROM auth_role role
JOIN sys_page page ON page.page_code IN ('system.business_profile','system.license.index')
WHERE role.role_code = 'SUPERADMIN'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1,
  can_delete = 1, can_export = 1, updated_at = CURRENT_TIMESTAMP;

COMMIT;
