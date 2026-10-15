-- Add persistent PHP sessions and image storage for serverless hosting.
-- Run once on an existing ChoreHive database. Existing local uploads are not
-- copied automatically; this creates the storage tables for new uploads.
USE chorehive;

CREATE TABLE IF NOT EXISTS app_sessions (
  session_id VARCHAR(128) PRIMARY KEY,
  session_data MEDIUMBLOB NOT NULL,
  expires_at INT UNSIGNED NOT NULL,
  INDEX idx_app_sessions_expiry (expires_at)
);

CREATE TABLE IF NOT EXISTS media_files (
  filename VARCHAR(255) PRIMARY KEY,
  mime_type VARCHAR(40) NOT NULL,
  file_data MEDIUMBLOB NOT NULL,
  visibility ENUM('public','private') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
