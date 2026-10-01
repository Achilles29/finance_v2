SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-01c_pos_order_monitor.sql
-- Tujuan : menambahkan task monitor dapur/bar/checker untuk POS
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS pos_order_monitor_task (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  order_line_id BIGINT UNSIGNED NOT NULL,
  station_role ENUM('BAR','KITCHEN') NOT NULL,
  ack_at DATETIME NULL,
  ack_by_employee_id BIGINT UNSIGNED NULL,
  ready_at DATETIME NULL,
  ready_by_employee_id BIGINT UNSIGNED NULL,
  checker_done_at DATETIME NULL,
  checker_done_by_employee_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_order_monitor_line_station (order_line_id, station_role),
  KEY idx_pos_order_monitor_order (order_id),
  KEY idx_pos_order_monitor_station (station_role),
  KEY idx_pos_order_monitor_ack (ack_at),
  KEY idx_pos_order_monitor_ready (ready_at),
  KEY idx_pos_order_monitor_checker (checker_done_at),
  CONSTRAINT fk_pos_order_monitor_order FOREIGN KEY (order_id) REFERENCES pos_order(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_order_monitor_line FOREIGN KEY (order_line_id) REFERENCES pos_order_line(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_order_monitor_ack_by FOREIGN KEY (ack_by_employee_id) REFERENCES org_employee(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_order_monitor_ready_by FOREIGN KEY (ready_by_employee_id) REFERENCES org_employee(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_order_monitor_checker_by FOREIGN KEY (checker_done_by_employee_id) REFERENCES org_employee(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;