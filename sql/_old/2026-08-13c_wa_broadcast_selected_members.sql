START TRANSACTION;

-- Tambah tipe target broadcast untuk memilih beberapa member tertentu.
ALTER TABLE wa_broadcast
  MODIFY target_type ENUM('MANUAL','SELECTED_MEMBERS','ALL_MEMBERS','MEMBER_ACTIVE','CUSTOM') NOT NULL DEFAULT 'MANUAL';

COMMIT;
