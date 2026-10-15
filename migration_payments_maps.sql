-- ChoreHive: payment methods, payment receipt metadata, and booking map pins.
-- Safe to import more than once on MySQL/MariaDB: each column is checked first.
USE chorehive;

SET @schema_name = DATABASE();

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='bookings' AND COLUMN_NAME='payment_method')=0,
  "ALTER TABLE bookings ADD COLUMN payment_method ENUM('cash','gcash','maya') NOT NULL DEFAULT 'cash' AFTER payment_status", 'SELECT 1');
PREPARE chorehive_stmt FROM @ddl; EXECUTE chorehive_stmt; DEALLOCATE PREPARE chorehive_stmt;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='bookings' AND COLUMN_NAME='payment_reference')=0,
  'ALTER TABLE bookings ADD COLUMN payment_reference VARCHAR(80) NULL AFTER payment_method', 'SELECT 1');
PREPARE chorehive_stmt FROM @ddl; EXECUTE chorehive_stmt; DEALLOCATE PREPARE chorehive_stmt;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='bookings' AND COLUMN_NAME='paid_at')=0,
  'ALTER TABLE bookings ADD COLUMN paid_at DATETIME NULL AFTER payment_reference', 'SELECT 1');
PREPARE chorehive_stmt FROM @ddl; EXECUTE chorehive_stmt; DEALLOCATE PREPARE chorehive_stmt;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='bookings' AND COLUMN_NAME='address_lat')=0,
  'ALTER TABLE bookings ADD COLUMN address_lat DECIMAL(10,7) NULL AFTER total_price', 'SELECT 1');
PREPARE chorehive_stmt FROM @ddl; EXECUTE chorehive_stmt; DEALLOCATE PREPARE chorehive_stmt;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='bookings' AND COLUMN_NAME='address_lng')=0,
  'ALTER TABLE bookings ADD COLUMN address_lng DECIMAL(10,7) NULL AFTER address_lat', 'SELECT 1');
PREPARE chorehive_stmt FROM @ddl; EXECUTE chorehive_stmt; DEALLOCATE PREPARE chorehive_stmt;

-- Existing bookings intentionally default to cash because no reliable method
-- can be inferred from their old payment_status values.

UPDATE bookings SET payment_reference=CONCAT('CH-LEGACY-',id), paid_at=COALESCE(paid_at,created_at) WHERE payment_status='paid' AND payment_reference IS NULL;
