ALTER TABLE app_notification_queue
  ADD COLUMN attachment_path varchar(500) NULL AFTER message_text,
  ADD COLUMN attachment_name varchar(190) NULL AFTER attachment_path;
