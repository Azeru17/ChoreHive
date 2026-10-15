-- =====================================================================
-- ChoreHive — New Features Migration
-- Adds: saved addresses, favorite workers, worker portfolio,
--       worker listing approval, cancellation reasons, and announcements.
-- Run this on an existing chorehive database after payment migrations.
-- Safe to run multiple times (uses IF NOT EXISTS / checks).
-- =====================================================================

-- 1. Cancellation reason column on bookings -----------------------------
ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS cancel_reason VARCHAR(255) NULL COMMENT 'Why the booking was cancelled/declined'
  AFTER payment_reference;

-- 2. Worker profile marketplace-listing approval ------------------------
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS worker_approved TINYINT(1) NOT NULL DEFAULT 0
  AFTER role;

-- 3. Client acknowledgement when a worker starts a job ------------------
ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS client_started_ack TINYINT(1) NOT NULL DEFAULT 0
  AFTER status;

-- 4. Announcement title; ordinary notifications may leave this NULL -----
ALTER TABLE notifications
  ADD COLUMN IF NOT EXISTS title VARCHAR(120) NULL
  AFTER user_id;

-- 5. Saved addresses (client address book) ------------------------------
CREATE TABLE IF NOT EXISTS saved_addresses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  label VARCHAR(60) NOT NULL,
  address VARCHAR(255) NOT NULL,
  address_lat DECIMAL(10,7) NULL,
  address_lng DECIMAL(10,7) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_savedaddr_client FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_savedaddr_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Favorite workers (client bookmarks workers) -----------------------
CREATE TABLE IF NOT EXISTS favorite_workers (
  client_id INT NOT NULL,
  worker_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (client_id, worker_id),
  CONSTRAINT fk_fav_client FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fav_worker FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_fav_worker (worker_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Worker portfolio gallery ------------------------------------------
CREATE TABLE IF NOT EXISTS worker_portfolio (
  id INT AUTO_INCREMENT PRIMARY KEY,
  worker_id INT NOT NULL,
  image VARCHAR(255) NOT NULL,
  caption VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_portfolio_worker FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_portfolio_worker (worker_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Notes:
--  - Upgrade databases by running payment migrations before this migration,
--    because cancel_reason is placed after payment_reference.
--  - Existing worker accounts require explicit administrator approval to
--    appear in the marketplace; this is not an identity/background check.
--  - Ordinary notifications may have no title; broadcasts can set one.
--  - Worker search/filter and CSV export require no schema changes.
-- =====================================================================
