<?php
/**
 * Copy this file to config.local.php and fill in your values.
 * config.local.php is gitignored — never commit live keys.
 *
 * Hostinger typical SMTP: smtp.hostinger.com, port 465 (SSL) or 587 (STARTTLS).
 * PayMongo: dashboard.paymongo.com → Developers → API keys / Webhooks.
 */

// Optional Hostinger MySQL overrides (uncomment when deploying):
// define('DB_HOST', 'localhost');
// define('DB_PORT', 3306);
// define('DB_USER', 'your_db_user');
// define('DB_PASS', 'your_db_password');
// define('DB_NAME', 'chorehive');
// define('DB_SSL', true);
// Optional CA certificate file path when the system trust store is unavailable:
// define('DB_SSL_CA', __DIR__ . '/certs/isrg-root-x1.pem');
// Optional: database-backed session and image storage (requires migration_vercel_storage.sql):
// define('SESSION_DRIVER', 'database');
// define('MEDIA_STORAGE', 'database');

// Public site URL with no trailing slash, e.g. https://chorehive.yourdomain.com
define('APP_URL', '');

// ChoreHive uses a no-API-key Google Maps iframe embed for the booking location preview.

define('PAYMONGO_SECRET_KEY', '');
define('PAYMONGO_PUBLIC_KEY', '');
define('PAYMONGO_WEBHOOK_SECRET', '');

// Local-only wallet payment simulation (localhost / *.localhost / *.test only).
// This does not contact a wallet or transfer funds. Keep false outside local testing.
define('PAYMENT_DEMO_MODE', false);

define('SMTP_HOST', '');
define('SMTP_PORT', 465);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', '');
define('SMTP_FROM_NAME', 'ChoreHive');
define('ADMIN_EMAIL', '');
