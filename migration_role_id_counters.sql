-- ChoreHive: per-role ID allocation tables.
--
-- Why this is required:
--   get_next_role_id() in config.php reads `role_id_counters` and `role_id_freed`
--   on EVERY registration. Both tables were missing from db.sql / db.production.sql,
--   so a fresh deployment from the schema would fatal on the first signup.
--
-- Run once on an existing database:
--   mysql -u USER -p DBNAME < migration_role_id_counters.sql

CREATE TABLE IF NOT EXISTS role_id_counters (
  role ENUM('client','worker','admin') NOT NULL PRIMARY KEY,
  next_id INT UNSIGNED NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS role_id_freed (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role ENUM('client','worker','admin') NOT NULL,
  freed_id INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_role_id_freed (role, freed_id)
);

-- Seed each counter above the highest ID already used for that role.
INSERT INTO role_id_counters (role, next_id)
SELECT 'client', COALESCE(MAX(id), 0) + 1 FROM users WHERE role = 'client'
ON DUPLICATE KEY UPDATE next_id = GREATEST(next_id, VALUES(next_id));
INSERT INTO role_id_counters (role, next_id)
SELECT 'worker', COALESCE(MAX(id), 0) + 1 FROM users WHERE role = 'worker'
ON DUPLICATE KEY UPDATE next_id = GREATEST(next_id, VALUES(next_id));
INSERT INTO role_id_counters (role, next_id)
SELECT 'admin', COALESCE(MAX(id), 0) + 1 FROM users WHERE role = 'admin'
ON DUPLICATE KEY UPDATE next_id = GREATEST(next_id, VALUES(next_id));