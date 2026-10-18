# Deploying ChoreHive to Vercel + TiDB Cloud

A step-by-step runbook for the production deployment. Follow it in order — step 3
and step 5 are the ones that silently break a fresh deploy if skipped.

---

## 0. What you need first

| Item | Why |
| --- | --- |
| A Vercel account | hosts the PHP app |
| A TiDB Cloud cluster | the database |
| `vercel` CLI installed | `npm i -g vercel` |
| MySQL client (or the bundled tools) | to apply the schema |

The app is plain PHP + MySQL. No build step, no npm dependencies.

---

## 1. Create the TiDB Cloud cluster

1. Sign in at <https://cloud.tidb.com>.
2. **Create Cluster → Starter** (the free tier; formerly "Serverless").
3. Pick a region close to your Vercel function region (both are usually
   `ap-southeast-1` / Singapore for the fastest round trip).
4. Copy the **Connect** string. It looks like:

```
mysql://<USER>:<PASSWORD>@<HOST>:4000/<DBNAME>?ssl-mode=VERIFY_IDENTITY
```

Write down the five values separately:

```
DB_HOST=<HOST>          e.g. gateway-xxxx.ap-southeast-1.prod.aws.tidbcloud.com
DB_PORT=4000
DB_USER=<USER>
DB_PASS=<PASSWORD>
DB_NAME=<DBNAME>
```

> TiDB Cloud **always requires TLS**. `DB_SSL` must be on.

### Notes on foreign keys
TiDB supports `FOREIGN KEY` from v6.6 onwards and it is **enabled by default**,
so `db.production.sql` applies unchanged. If your cluster rejects them (older
cluster), run `tools/migrate.php --check` — it reports every table and column so
you can see exactly what is missing.

---

## 2. Create the schema

### Fastest path — paste into the TiDB web SQL editor

Generate the file locally, then paste it into the **SQL Editor** in the TiDB
Cloud console:

```bash
php tools/export_sql.php --demo-only --out=tidb_demo_data.sql
```

Open the file, copy everything, paste it into the TiDB SQL editor, and run it.
One file, no extra tools, nothing to install.

- `tidb_demo_data.sql` — full schema **plus** sample data (recommended for a
  demo or defence, so the dashboards are not empty)
- `php tools/export_sql.php --out=tidb_schema.sql` — schema only, if you want a
  genuinely empty database

The export is read back out of a running database with `SHOW CREATE TABLE` and
ordered by foreign-key dependency, so it always matches the schema the app
actually runs against. Every statement is `DROP`/`CREATE`-based, so re-running
it just rebuilds the tables.

> **If the web editor reports something like
> `Table 'chorehive.chat_messages' doesn't exist`, stop.** The editor's
> statement splitter mis-parsed the paste and only ran part of the script, so
> the `CREATE TABLE` statements never executed. Split the file and paste the
> parts in order instead:
>
> ```bash
> php tools/export_sql.php --demo-only --out=tidb_import.sql --split=5
> ```
>
> That writes `tidb_import_part1.sql` … `tidb_import_part5.sql`. Paste
> **part1, then part2, … part5**, in that order. Every part is independently
> runnable and repeats `CREATE DATABASE` / `USE`, so you cannot end up in the
> wrong schema.

> TiDB Cloud's **Import** page only accepts CSV, and the **SQL Files** tab in
> the SQL editor is for saving your own queries, so neither can run a `.sql`
> file. Pasting is the only in-browser option, and if it misbehaves use the
> one-command route below instead.

### Recommended — one command from your machine

This is more reliable than pasting, because the runner executes each statement
individually and reports exactly what failed. Set the variables once:

```powershell
$env:DB_HOST="<HOST>"; $env:DB_PORT="4000"; $env:DB_USER="<USER>"
$env:DB_PASS="<PASSWORD>"; $env:DB_NAME="chorehive"; $env:DB_SSL="1"

php tools/migrate.php            # creates the schema
php tools/seed_demo_data.php     # loads the sample data, including photos
```

### Alternative — plain MySQL client

```bash
mysql --host=<HOST> --port=4000 --user=<USER> --password \
      --ssl-mode=VERIFY_IDENTITY --ssl-ca=ca.pem < db.production.sql
```

All three routes end with the same verification block:

```
--- schema check ---
OK  all 22 expected tables present
OK  all expected columns present
OK  role_id_counters: client=1, worker=1, admin=1
RESULT: ready
```

> `role_id_counters` / `role_id_freed` are **not optional**. `get_next_role_id()`
> reads them on every signup; without them registration fatals.

---

## 3. Load the demo data (optional, recommended for a defence/demo)

```bash
php tools/seed_demo_data.php
```

It prints the sign-in details at the end. Every seeded account shares one
password. To wipe and re-seed:

```bash
php tools/seed_demo_data.php --reset --force
```

To start from an empty database instead, skip this step — but then **the first
account you register becomes the one shown on the site**, and every dashboard
will be empty.

---

## 4. TLS certificate for PHP

TiDB Cloud's certificate is issued by a public CA. PHP's `mysqlnd` can verify it
from the system trust store, but the Vercel PHP runtime may not ship one.
To be safe, download the CA and commit it:

```bash
mkdir -p certs
curl -o certs/isrg-root-x1.pem https://www.isletest.com/bundle.crt
```

Then set `DB_SSL_CA` to its absolute path in the function (see step 6). A public
root certificate is not a secret and is safe to commit.

---

## 5. Vercel environment variables

Set these in **Vercel → Project → Settings → Environment Variables** (all
environments, and redeploy afterwards).

### Required

| Variable | Value |
| --- | --- |
| `DB_HOST` | TiDB host from step 1 |
| `DB_PORT` | `4000` |
| `DB_USER` | TiDB user |
| `DB_PASS` | TiDB password |
| `DB_NAME` | TiDB database |
| `DB_SSL` | `1` |
| `DB_SSL_CA` | `/var/task/certs/isrg-root-x1.pem` |
| `SESSION_DRIVER` | `database` |
| `MEDIA_STORAGE` | `database` |
| `APP_URL` | `https://your-app.vercel.app` (no trailing slash) |

> **`SESSION_DRIVER=database` and `MEDIA_STORAGE=database` are mandatory on
> Vercel.** Serverless filesystems are read-only and are not shared between
> invocations. With the defaults you would be logged out on every page load and
> every uploaded avatar/photo would fail to save.

They are defaulted automatically when `VERCEL` is set, but setting them
explicitly makes the deployment self-documenting.

### Optional

| Variable | When |
| --- | --- |
| `PAYMONGO_SECRET_KEY` | real GCash/Maya payments |
| `PAYMONGO_PUBLIC_KEY` | real payments |
| `PAYMONGO_WEBHOOK_SECRET` | webhook signature check |
| `SMTP_HOST` / `SMTP_USER` / `SMTP_PASS` / `SMTP_FROM` | real email for password resets |
| `ADMIN_EMAIL` | where admin notices go |

Leave payments and SMTP unset for a demo — the app degrades gracefully and shows
a clear "not configured" message rather than failing.

---

## 6. Deploy

```bash
vercel login
vercel link                 # first time only
vercel env pull .env.local  # optional
vercel --prod
```

The repository already contains everything needed:

- `vercel.json` — routes every `*.php` request through the `api/index.php`
  PHP function and 404s the internal helpers (`config.php`, `layout.php`,
  `_run_migration.php`, `uploads/`, `.git`, …).
- `.vercelignore` — keeps `config.local.php`, `db*.sql`, `migration_*.sql`,
  the `.docx` and local uploads out of the bundle.
- `.gitignore` — `config.local.php` is never committed.

---

## 7. Verify after deploying

Open the deployed URL and walk through:

1. **Homepage** loads (this proves the DB connection + TLS work — the page calls
   `require_marketplace_schema()`, which fails loudly if `users.city` is missing).
2. Register a brand-new account.
3. Log in as the demo admin, open **Admin → Bookings**, and assign the pending
   booking to a worker.
4. Log in as that worker, open the job, and tick the checklist.
5. Log back in as the client and confirm the job.

If step 1 is blank, the cause is almost always the database: run
`php tools/migrate.php --check` against TiDB and read the output.

---

## 8. Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
| Blank page / "DB connection failed" | TLS or credentials | check `DB_SSL=1`, `DB_PORT=4000`, and that the TiDB cluster is awake |
| `DB_SSL_CA must point to a readable CA certificate file` | wrong path | it must be an absolute path inside the function (`/var/task/...`) |
| Logged out on every page | `SESSION_DRIVER` not `database` | set the env var, redeploy |
| Avatars/photos will not save | `MEDIA_STORAGE` not `database` | set the env var, redeploy |
| "Duplicate entry" on registration | schema missing `role_id_counters` | `php tools/migrate.php` |
| 404 on `admin/contact.php` | file was untracked in git | `git add -A && git commit` |
| `migration_*` files 404 on Vercel | intentional | they are blocked from routing; run them locally instead |

---

## 9. Rolling back

```bash
vercel rollback            # previous deployment
```

Database migrations are **not** rolled back automatically. Restore from a TiDB
backup (TiDB Cloud keeps automatic backups) if a migration must be undone.

---

## 10. Local commands reference

```bash
php tools/migrate.php --list             # available migrations
php tools/migrate.php                    # apply all (idempotent)
php tools/migrate.php --check            # verify schema, change nothing
php tools/seed_demo_data.php             # add demo data
php tools/seed_demo_data.php --reset     # remove demo data (asks to confirm)
php tools/seed_demo_data.php --reset --force
php tools/export_sql.php                 # write tidb_schema.sql
php tools/export_sql.php --demo-only     # write tidb_demo_data.sql (schema + sample rows)
php tools/export_sql.php --data          # write every row in the database
php tools/export_sql.php --split=5       # write tidb_import_part1..5.sql for pasting
```

`export_sql.php` output is a local artefact: it is gitignored and excluded from
the Vercel bundle, because it contains password hashes and sample data.