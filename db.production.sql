-- ChoreHive production/development database bootstrap.
-- Import only into a new, empty database. This file creates the current schema
-- and service catalog, but intentionally contains no demo users or bookings.

CREATE DATABASE IF NOT EXISTS chorehive
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE chorehive;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('client','worker','admin') NOT NULL DEFAULT 'client',
  worker_approved TINYINT(1) NOT NULL DEFAULT 0,
  phone VARCHAR(30),
  address VARCHAR(255),
  city VARCHAR(100) NULL,
  bio TEXT,
  avatar VARCHAR(255) NULL,
  is_new TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  base_price DECIMAL(10,2) NOT NULL,
  duration_hours INT DEFAULT 2
);

CREATE TABLE badges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  worker_id INT NOT NULL,
  name VARCHAR(80) NOT NULL,
  description VARCHAR(255),
  earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  worker_id INT NULL,
  service_id INT NOT NULL,
  schedule DATETIME NOT NULL,
  address VARCHAR(255) NOT NULL,
  notes TEXT,
  status ENUM('pending','assigned','in_progress','awaiting_verification','completed','cancelled') DEFAULT 'pending',
  payment_status ENUM('unpaid','paid','demo_paid') NOT NULL DEFAULT 'unpaid',
  payment_method ENUM('cash','gcash','maya') NOT NULL DEFAULT 'cash',
  payment_reference VARCHAR(80) NULL,
  paid_at DATETIME NULL,
  paymongo_checkout_id VARCHAR(80) NULL,
  paymongo_payment_id VARCHAR(80) NULL,
  client_started_ack TINYINT(1) NOT NULL DEFAULT 0,
  cancel_reason VARCHAR(255) NULL,
  address_lat DECIMAL(10,7) NULL,
  address_lng DECIMAL(10,7) NULL,
  total_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES users(id),
  FOREIGN KEY (worker_id) REFERENCES users(id),
  FOREIGN KEY (service_id) REFERENCES services(id)
);

CREATE TABLE checklist_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  task VARCHAR(255) NOT NULL,
  done TINYINT(1) DEFAULT 0,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

CREATE TABLE booking_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  stage ENUM('before','after') NOT NULL,
  filepath VARCHAR(255) NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

CREATE TABLE feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL UNIQUE,
  client_id INT NOT NULL,
  rating TINYINT NOT NULL,
  comment TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(120) NULL,
  message VARCHAR(500) NOT NULL,
  action_url VARCHAR(255) NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE contact_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  INDEX idx_contact_messages_unread_created (is_read, created_at),
  INDEX idx_contact_messages_created (created_at)
);

CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  actor_name VARCHAR(100) NOT NULL,
  action VARCHAR(80) NOT NULL,
  details VARCHAR(500) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE saved_addresses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  label VARCHAR(60) NOT NULL,
  address VARCHAR(255) NOT NULL,
  address_lat DECIMAL(10,7) NULL,
  address_lng DECIMAL(10,7) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_savedaddr_client FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_savedaddr_client (client_id)
);

CREATE TABLE favorite_workers (
  client_id INT NOT NULL,
  worker_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (client_id, worker_id),
  CONSTRAINT fk_fav_client FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fav_worker FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_fav_worker (worker_id)
);

CREATE TABLE worker_portfolio (
  id INT AUTO_INCREMENT PRIMARY KEY,
  worker_id INT NOT NULL,
  image VARCHAR(255) NOT NULL,
  caption VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_portfolio_worker FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_portfolio_worker (worker_id)
);

CREATE TABLE login_attempts (
  attempt_key VARCHAR(255) PRIMARY KEY,
  attempts INT DEFAULT 1,
  last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE worker_presence (
  worker_id INT PRIMARY KEY,
  last_seen DATETIME NOT NULL,
  FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE
);



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

CREATE TABLE chat_threads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  worker_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_client_worker (client_id, worker_id),
  FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE chat_messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  thread_id INT NOT NULL,
  sender_id INT NOT NULL,
  message TEXT NOT NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_thread_message (thread_id, id),
  FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE app_sessions (
  session_id VARCHAR(128) PRIMARY KEY,
  session_data MEDIUMBLOB NOT NULL,
  expires_at INT UNSIGNED NOT NULL,
  INDEX idx_app_sessions_expiry (expires_at)
);

CREATE TABLE media_files (
  filename VARCHAR(255) PRIMARY KEY,
  mime_type VARCHAR(40) NOT NULL,
  file_data MEDIUMBLOB NOT NULL,
  visibility ENUM('public','private') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO services (name,description,base_price,duration_hours) VALUES
('Basic Home Cleaning','Sweeping, mopping, dusting, trash disposal.',350,2),
('Deep Cleaning','Thorough cleaning of kitchens, bathrooms, and hard-to-reach areas.',700,4),
('Move-in/Move-out Cleaning','Full unit cleaning for tenants and landlords.',900,5),
('Laundry & Ironing','Washing, drying, folding, and ironing.',300,3);
