-- ChoreHive finance ledger. Run once on an existing database.
CREATE TABLE IF NOT EXISTS worker_finance_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  worker_id INT NULL,
  booking_id INT NULL,
  entry_type ENUM('payout','bonus','deduction','discount','expense') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  note VARCHAR(255) NOT NULL,
  created_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_finance_worker_date (worker_id, created_at),
  INDEX idx_finance_type_date (entry_type, created_at),
  CONSTRAINT fk_finance_worker FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_finance_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_finance_admin FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure the structured municipality column exists even when this file is run by the alphabetical CLI runner.
ALTER TABLE users ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL AFTER address;

-- Standardize worker municipality; keep each worker's street address distinct.
UPDATE users SET city='Polomolok' WHERE role='worker';
