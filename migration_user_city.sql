-- ChoreHive structured worker/client municipality field. Safe for existing installations.
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL AFTER address;
