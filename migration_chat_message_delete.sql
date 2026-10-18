USE chorehive;

ALTER TABLE chat_messages
  ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0;
