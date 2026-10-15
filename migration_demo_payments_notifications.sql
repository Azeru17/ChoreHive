-- ChoreHive: simulated wallet payments and actionable notifications.
-- Run once on existing installations after the prior migrations.
USE chorehive;

ALTER TABLE bookings
  MODIFY COLUMN payment_status ENUM('unpaid','paid','demo_paid') NOT NULL DEFAULT 'unpaid';

SET @schema_name = DATABASE();
SET @ddl = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='notifications' AND COLUMN_NAME='action_url')=0,
  'ALTER TABLE notifications ADD COLUMN action_url VARCHAR(255) NULL AFTER message',
  'SELECT 1'
);
PREPARE chorehive_stmt FROM @ddl;
EXECUTE chorehive_stmt;
DEALLOCATE PREPARE chorehive_stmt;
