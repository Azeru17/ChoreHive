# 🐝 ChoreHive — Web-Based Cleaning Service Booking System (Prototype)

A starter PHP/MySQL prototype implementing the features described in the ChoreHive startup document.

## Features

- **Three roles:** Client, Worker, Admin
- **Clients:** discover cleaner profiles, compare ratings/status, chat before hiring, create bookings with a custom checklist and optional map pin, choose cash-after-work or PayMongo GCash/Maya checkout, and print/save a payment receipt.
- **Workers:** job list with payment gates (online PayMongo checkout must be paid before starting), status flow (assigned → in progress → awaiting verification), map directions, checklist completion, before/after photo uploads, and reviews.
- **Admins:** dashboard stats, assign workers to bookings, manage users, **award/revoke skill badges**, manage services, and a **Reports & Performance Dashboard** (recorded paid booking value, per-service performance, worker ratings, worker schedule, and returning-customer summaries based on multiple completed bookings). Clients can manually rebook from booking history.

See [docs/SYSTEM_DESCRIPTION.md](docs/SYSTEM_DESCRIPTION.md) for the formal project description, user roles, feature behavior, workflow, screenshot descriptions, and current implementation limits.

## Tech Stack

PHP 8+ (mysqli), MySQL/MariaDB, no frameworks — runs on XAMPP exactly as described in the document.

## Branding & Theme

The colour palette is derived from `logo.jpg` — the logo's **navy blue** and **honey gold**:

| Token | Value | Used for |
|-------|-------|----------|
| `--navy` / `--navy-dark` | `#17427a` / `#0f2d55` | Brand blue — headings, nav text, admin sidebar, footer |
| `--honey` / `--honey-dark` | `#e0aa33` / `#c1861a` | Gold accent — primary buttons, active nav, links |
| `--bg` / `--card` | `#f4f7fc` / `#ffffff` | Page and card surfaces |
| `--ink` / `--muted` / `--line` | `#1f2c44` / `#6b7a90` / `#e3e9f2` | Body text, secondary text, borders |

### Logo assets

All assets live in `assets/` and are PNGs with a **transparent background** (the original white square was keyed out), so no white box ever appears behind the logo:

| File | Purpose |
|------|---------|
| `logo.png` | Circular ChoreHive mark in navy + gold — light backgrounds (main header, landing sidebar) |
| `logo-light.png` | Same mark with the navy artwork recolored to white — dark/navy surfaces (footer, welcome hero) |
| `logo-mark.png` | Tight-cropped mark without the circular padding — for larger placements |
| `favicon.png` | 64px favicon |

The mark is shown as a **circle beside the "ChoreHive" wordmark**; the circular shape and a subtle gold ring come from `border-radius` + `box-shadow` in CSS, keeping the PNG itself fully transparent. To regenerate the assets from `logo.jpg` after changing the logo, use PHP with the GD extension enabled (`php -d extension=gd`).

## Scheduling rules

Bookings can only be scheduled for **today or a future date**. This is enforced in two places:

- **Server-side** — `v_datetime()` in `config.php` rejects any schedule before `today 00:00:00`, so a crafted request cannot bypass it.
- **Client-side** — the `datetime-local` input carries a server-rendered `min` value (`min_datetime_attr()`), so the picker greys out earlier dates even before submitting.

## Cleaning up your account

All three roles can tidy up their own data. Deletions are **permanent**, confirmed with a dialog, recorded in the audit log, and cascade to related rows. Uploaded photo files are also removed from `uploads/` so nothing is left orphaned.

| | Client | Worker | Admin |
|---|---|---|---|
| Notifications | delete one / clear all | delete one / clear all | all accounts |
| Checklist tasks | remove individual tasks | — | via booking |
| Bookings / history | own finished bookings, or "Clear history" | own finished jobs, or "Clear history" | **any** booking, or "Purge finished" |
| Audit log | — | — | "Clear audit log" |
| Client reviews | — | "My Reviews" page — delete one or all | — |

**Guards:** active jobs (`pending`, `assigned`, `in_progress`, `awaiting_verification`) cannot be deleted by clients or workers — cancel them first. The checklist freezes once a job reaches `awaiting_verification`, `completed`, or `cancelled`, so tasks cannot be changed after verification or cancellation. Workers must upload at least one before photo and one after photo before marking a job done. Only admins may delete an active booking.

## Profiles

Every account has a profile page (`/client/profile.php`, `/worker/profile.php`, `/admin/profile.php`):

- **Profile picture** — JPG/PNG/WebP up to 2 MB (real MIME-sniffed, filename not trusted); falls back to initials
- **Name, phone, address** and a **bio** (workers)
- **Change password** — requires the current password, minimum 8 characters
- **Workers only:** recorded paid booking-value statistics (all-time, this month, last 7 days, today; simulation-only payments excluded) with a per-day breakdown, plus an administrator-managed badge showcase

Workers can also **decline** an assigned job from *My Jobs*; the booking is unassigned and both the client and admins are notified. New worker profiles require administrator approval before they appear in customer discovery or can be newly booked or assigned. This is a marketplace-listing review only, not an identity or background check. Badges are managed by administrators and do not certify identity or background screening.

Worker booking-value statistics sum recorded payments for completed bookings associated with the worker; simulation-only payments are excluded. They are not payout records or verified take-home earnings; ChoreHive does not currently calculate platform commission or worker payouts.

## Setup

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** + **MySQL**.
2. Copy the `chorehive` folder into `C:\xampp\htdocs\` (or your web root).
3. Import the database:
   - Open http://localhost/phpmyadmin → Import → select `db.sql` → Go.
   - Or via CLI: `mysql -u root < db.sql`
   - `db.sql` includes the current application schema for a fresh installation; do not run upgrade migrations on top of it.
4. Ensure `uploads/` folder exists inside `chorehive` (created automatically, but must be writable).
5. Copy `config.local.example.php` to `config.local.php` and add PayMongo keys (required for GCash/Maya) and SMTP settings (required for real email).
6. Open **http://localhost/chorehive/**

### Upgrading an existing installation

Back up both the project files and database first. Do **not** import `db.sql` into a database with existing data. Apply only migrations for schema features that the database does not already have, in this order:

1. `migration_marketplace_chat.sql`
2. `migration_chat_message_delete.sql`
3. `migration_payments_maps.sql`
4. `migration_paymongo.sql`
5. `migration_new_features.sql`
6. `migration_demo_payments_notifications.sql`
7. `migration_contact_messages.sql`
8. `migration_user_city.sql`
9. `migration_role_id_allocation.sql`
10. `migration_finance_and_worker_location.sql`
11. `migration_vercel_storage.sql` (only when deploying with database-backed sessions and image storage, or when those tables are missing)

The fifth migration adds cancellation reasons, client start acknowledgements, announcement titles, saved addresses, favorite workers, and worker portfolios. The sixth adds demo-only payment status and actionable notification URLs. The seventh creates the public contact-form inbox. The eighth adds the structured City / Municipality field. The ninth creates registration ID-allocation tables. The tenth adds the worker finance ledger and standardizes worker municipality to Polomolok while preserving each street address. The eleventh creates database-backed sessions and image storage for serverless deployments. These migrations preserve existing data; verify the database backup and schema before running them on production. The chat-message deletion migration adds a soft-delete marker; it does not run automatically.

### Local wallet-payment simulation

When PayMongo credentials are pending, use the GCash/Maya simulation only on a local host: set `PAYMENT_DEMO_MODE` to `true` in your uncommitted `config.local.php`. The simulation is enabled only for loopback requests on `localhost`, `*.localhost`, or `*.test` hosts and is automatically unavailable on production requests even if a client forges a local-looking Host header. The client follows three in-site demo steps: enter a test Philippine mobile number, verify a temporary code shown in the demo (no SMS is sent), then review and confirm the booking amount. Never enter a real wallet password, PIN, or OTP. Approving marks the booking `demo_paid` so the worker/client workflow can be tested, but does not open or connect to an actual GCash/Maya wallet, contact PayMongo, or transfer money. Demo records are labelled and excluded from recorded-payment totals and revenue reports. Leave the setting `false` when testing real PayMongo checkout.

If the application detects that `users.worker_approved` is missing, it shows a setup-required page rather than exposing a PHP database error. Apply only the missing migrations from the existing-installation sequence above; worker listings remain unavailable until the schema is current and an administrator has reviewed worker profiles.

## Local seed users (XAMPP only)

`db.sql` seeds three accounts for local development. **Do not import these users onto Hostinger.** Register real admin, client, and worker accounts after you deploy, using strong passwords.

| Role   | Email                | Local password |
|--------|----------------------|----------------|
| Admin  | admin@chorehive.test | password123    |
| Client | maria@client.test    | password123    |
| Worker | juan@worker.test     | password123    |

## Suggested walkthrough

1. Login as **client** → open **Find a Cleaner** → compare worker status, ratings, completed jobs and badges → open a profile.
2. Message the cleaner first. Use the chat page's **Hire this cleaner** button when ready.
3. Complete the guided booking form: choose service and schedule, type the address manually, optionally add a draggable map pin, complete the task checklist, and select a payment method.
4. For GCash or Maya, complete PayMongo checkout before the worker can start (test keys on localhost; live keys after PayMongo KYC). If credentials are pending, explicitly enable the local-only simulation above to test the workflow; the simulation is never a real payment. For cash, no payment is recorded until after the job is completed and reviewed.
5. An administrator assigns any unassigned booking from **Manage Bookings**. Login as **worker** → open the job → use its map/directions link when a pin exists, tick checklist items, upload before/after photos, and mark the job done.
6. Back as **client** → review the work. For cash, confirm only after you physically hand over the cash; for online payment, verify the work. Open the receipt to print it or save it as a PNG.
7. Login as **admin** → review marketplace metrics, payment statuses/methods, worker assignments, location pins, and audited read-only conversation transcripts.

Booking before/after photos are served only to the booking's client, currently assigned worker, and administrators. Keep the access-control rules in `uploads/.htaccess` and use `photo.php` to display booking photos.

Clients can self-cancel any active booking (`pending`, `assigned`, `in_progress`, or `awaiting_verification`) from its details page; the status-conditional update prevents cancelling a booking that has already completed or changed state. The worker is notified and must stop work. This also applies to GCash/Maya bookings, but ChoreHive does not automatically issue or process online refunds. A worker cannot decline an online-paid booking in the worker UI; an administrator must handle reassignment and any refund request outside the automated workflow. Customers should contact support for a refund request. If a client pays through an already-open checkout after cancelling, the payment is recorded against the cancelled booking and is not automatically refunded. Workers can decline only unpaid cash assignments.

## UI refinement

The shared UI refinement stylesheet is `assets/css/chorehive-ui.css`. It builds on the existing navy-and-honey-gold theme with consistent focus states, responsive cards/forms/tables, safer wrapping for long labels and buttons, map pin controls, payment choices, and receipt print styling. Public, client, worker, and admin layouts share the responsive control and spacing rules; the standalone worker directory has its own small-screen rules. Wide data tables remain readable in a contained horizontal scroll area on phones. The public homepage and authenticated layouts load the shared stylesheet with a file-version query so style changes are not hidden by browser caching.

## PayMongo (GCash and Maya)

Online checkout uses [PayMongo Checkout Sessions](https://developers.paymongo.com/). Put `PAYMONGO_SECRET_KEY` (and optionally `PAYMONGO_PUBLIC_KEY`, `PAYMONGO_WEBHOOK_SECRET`) in `config.local.php`.

- Customers are redirected to PayMongo, then to GCash or Maya.
- After payment, ChoreHive confirms via the success URL (retrieve session) and via `paymongo_webhook.php`.
- Use **test keys** (`sk_test_…`) until PayMongo KYC is approved, then swap to live keys. Same code.
- If keys are empty, checkout shows “payments are not configured” — there is no fake OTP fallback.
- Register the webhook URL on a public HTTPS host: `https://yourdomain/paymongo_webhook.php` (include the folder path if the app is not at the domain root).

## Email (Hostinger SMTP)

Set `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM`, and `ADMIN_EMAIL` in `config.local.php`. Hostinger mailboxes typically use `smtp.hostinger.com` on port 465. Password resets and notifications are sent by SMTP when these values are set. If SMTP is empty (local XAMPP), messages are written to `email_outbox.log` and in-app notifications still work. Reset links are never shown on the website.

Set `APP_URL` to the public HTTPS origin (no trailing slash) so PayMongo return links and reset emails use the correct domain.

## Notes

- Passwords are hashed with `password_hash()` and the application uses prepared statements for its database queries.
- CSRF tokens are implemented for protected form/actions in the current PHP version; continue testing all state-changing actions before deployment.
- Cash confirmation is recorded by the customer after job verification. Online receipts show the PayMongo payment reference.
- `ch_base()` derives the app folder from the request, so the project can live at `/chorehive` on XAMPP or at the domain root on Hostinger.

## Hostinger production

1. Create hosting, MySQL, SSL, and an email mailbox (for example `noreply@yourdomain`).
2. Upload the project. For a fresh database, import `db.sql` but do not use its local `.test` seed accounts in production. For an existing database, apply the required migrations in the order listed above.
3. Place `config.local.php` on the server only (never in git). Fill DB overrides, `APP_URL`, PayMongo keys, and SMTP.
4. Make `uploads/` writable. Do not re-import `db.sql` over an existing database.
5. Add the PayMongo webhook and switch to live keys only after KYC.

## Vercel preview deployment status

ChoreHive uses Vercel's community PHP runtime configured in `vercel.json`; PHP is not an official Vercel runtime. The runtime requires PHP function entry points under `api/`, so `api/index.php` dispatches PHP page requests to the existing app files while static assets continue to use Vercel's filesystem routing. The project uses TiDB Cloud for MySQL-compatible data. Set the Vercel environment variables `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, and `DB_NAME`; set `DB_SSL=true` to require TLS and server-certificate verification. `DB_SSL_CA` is optional when the runtime's trust store is sufficient. The runtime needs a PHP version with `mysqli`, `mysqlnd`, and `openssl`.

Vercel functions do not have durable local files or shared file sessions. The app automatically selects database sessions and database-backed images when `VERCEL` is present (override with `SESSION_DRIVER` and `MEDIA_STORAGE` only if needed). Before deploying, apply `migration_vercel_storage.sql` to the already-created TiDB database; a fresh database bootstrap already includes the `app_sessions` and `media_files` tables. This migration only creates two new tables and preserves existing data. `media_files` stores images in TiDB, including private booking photos served only through the authorization checks in `photo.php`. Images therefore count toward the TiDB storage quota; keep the 3 MB upload limit and review storage usage as the app grows.

Use `APP_URL` for the public site origin when configuring email or PayMongo return/webhook URLs. Configure SMTP before relying on email workflows; the local email-log fallback is not persistent on Vercel. Keep PayMongo credentials only in Vercel environment variables, and leave payment demo mode disabled. `.vercelignore` excludes local secrets, uploads, SQL files, migrations, and development-only scripts. Before deploying the contact inbox changes, run `migration_contact_messages.sql` once in TiDB SQL Editor so the public contact form can save messages and admins can view them at `/admin/contact.php`. This deployment setup still requires a successful Vercel build plus live smoke tests for TLS database connectivity, sessions, uploads, and protected photos before the preview can be considered working.


## Marketplace discovery and chat workflow

Customer dashboards prioritize worker discovery. Customers can browse cleaner profiles, compare ratings/reviews and availability, message a worker before committing, and select **Hire this cleaner** from the profile or chat sidebar. Hiring opens a guided job-details form with the selected worker locked in, the saved address prefilled, optional map pin, service-price summary, editable checklist, and payment method.

Cash is confirmed only after the worker marks the job complete and the customer has reviewed the work and physically paid the worker. GCash/Maya checkout happens before work starts through PayMongo; the server prevents workers from starting an online booking while it remains unpaid. A receipt with the PayMongo/ChoreHive reference can be printed or saved to PNG. The booking location uses a Google Maps iframe preview without an API key. The customer must type the full address manually; the map preview follows the address, and the “Use my location” button can save device coordinates after permission. The iframe itself cannot return clicked-pin coordinates. Workers/admins open saved coordinates in Google Maps.

The chat UI refreshes messages by polling every three seconds (near-real-time polling, not WebSockets). Worker presence is updated from authenticated worker pages and chat activity. Clients and workers can delete their own sent messages; administrators can remove a message from the audited conversation-review screen. Deleted content is replaced with a “Message deleted” marker for participants and administrators, and deletion actions are audited. The database needs `migration_chat_message_delete.sql` before this feature can be used.

### Database update for existing installations

If you already imported `db.sql` before these features, back up your database and apply only missing migrations in the order listed under **Upgrading an existing installation** above. In particular, `migration_payments_maps.sql` must run before the booking pages that rely on its payment and map fields, and the final `migration_new_features.sql` adds listing approval, cancellation reasons, client start acknowledgements, announcement titles, saved addresses, favorites, and worker portfolios. For a fresh hosted database, use `db.production.sql` to avoid local demo accounts; `db.sql` includes local-only test users and sample bookings.

### Chat behavior

The chat interface polls the server every three seconds for new messages; this is near-real-time polling, not a persistent WebSocket connection. Worker presence is marked as recently active when the worker's authenticated pages or chat endpoint update their heartbeat. For the map, the project uses a no-key Google Maps iframe preview rather than the Google Maps JavaScript API. The preview follows the typed address; device coordinates can be saved using browser geolocation. The iframe cannot report clicks or dragged pins back to ChoreHive. Saved coordinates open Google Maps. On Hostinger, enable HTTPS, backups, and PayMongo live keys only after KYC.
