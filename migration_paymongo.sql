-- ChoreHive: PayMongo checkout session ids on bookings.
-- Safe to import more than once.
USE chorehive;

SET @schema_name = DATABASE();

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='bookings' AND COLUMN_NAME='paymongo_checkout_id')=0,
  'ALTER TABLE bookings ADD COLUMN paymongo_checkout_id VARCHAR(80) NULL AFTER paid_at', 'SELECT 1');
PREPARE chorehive_stmt FROM @ddl; EXECUTE chorehive_stmt; DEALLOCATE PREPARE chorehive_stmt;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='bookings' AND COLUMN_NAME='paymongo_payment_id')=0,
  'ALTER TABLE bookings ADD COLUMN paymongo_payment_id VARCHAR(80) NULL AFTER paymongo_checkout_id', 'SELECT 1');
PREPARE chorehive_stmt FROM @ddl; EXECUTE chorehive_stmt; DEALLOCATE PREPARE chorehive_stmt;
