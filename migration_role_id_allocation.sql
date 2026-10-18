-- ChoreHive registration ID allocation tables. Safe for fresh and existing databases.
CREATE TABLE IF NOT EXISTS role_id_counters (
  role ENUM('client','worker','admin') NOT NULL PRIMARY KEY,
  next_id INT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_id_freed (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role ENUM('client','worker','admin') NOT NULL,
  freed_id INT NOT NULL,
  UNIQUE KEY unique_freed (role, freed_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO role_id_counters (role, next_id) VALUES ('client', 1), ('worker', 1), ('admin', 1);
