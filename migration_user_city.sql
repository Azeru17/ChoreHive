-- ChoreHive migration: add a structured City / Municipality to every user.
-- Used to match clients with cleaners in the same area (location-aware marketplace).
-- Safe to run once on an existing database.

ALTER TABLE users
  ADD COLUMN city VARCHAR(100) NULL AFTER address;
