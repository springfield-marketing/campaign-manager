# Deployment (Laravel Forge + single server)

How to host Campaign Tracker on a single Forge-managed server (app + PostgreSQL + Redis +
queue workers + scheduler all on one box) and ship updates with a git push.

The app's runtime needs: **PostgreSQL**, **Redis** (queue/cache/sessions), **queue workers**, a
**scheduler**, and local-disk storage for import/export files. PHP `^8.3` (8.3 or 8.4 is fine).

## 1. Server (Forge)

- Provision a DigitalOcean droplet, **≥ 4 GB RAM** (data is ~1M contacts / ~5M WhatsApp messages,
  and import jobs use 512 MB–1 GB each). PHP 8.3 or 8.4.
- Install **PostgreSQL** and **Redis** on the same server (Forge does both in a click).
- Install the **phpredis** PHP extension (faster than predis for cache/session/queue at this volume).
- One server, one filesystem — this is why the import pipeline (which reads uploaded CSVs from
  the local disk) works without changes.

## 2. PostgreSQL

- Create the database and a least-privilege app user.
- Tune `postgresql.conf` to the droplet: `shared_buffers` ≈ 25% RAM, `effective_cache_size` ≈ 75%,
  and a healthy `work_mem` — the analytics pages do large GROUP BYs over millions of rows.
- **Leave autovacuum on.** The WhatsApp analytics indexes only give their index-only-scan speedup
  (Template Performance ~340 ms vs ~2.1 s) when the visibility map is fresh, which autovacuum
  maintains. After a very large import, a manual `VACUUM ANALYZE whatsapp_messages` warms it.

## 3. Redis

- Enable Redis; the app uses it for `QUEUE_CONNECTION`, `CACHE_STORE`, and `SESSION_DRIVER`.

## 4. `.env` (production)

Key values (see `.env.example` for the full list):

```
APP_ENV=production
APP_DEBUG=false                 # never true in prod — leaks stack traces + PII
APP_URL=https://your-domain
APP_KEY=...                     # php artisan key:generate

DB_CONNECTION=pgsql             # MySQL is NOT supported (Postgres-only SQL)
DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD

QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_QUEUE_RETRY_AFTER=7800    # MUST exceed the longest job timeout (imports: 7200s)

FILESYSTEM_DISK=local
```

## 5. Queue workers (Forge → Daemons or Queue)

Run a worker over the four queues, with a timeout that matches the longest job:

```
php artisan queue:work redis \
  --queue=imports-high,imports,default,analysis \
  --timeout=7200 --memory=1024 --tries=1 --sleep=3 --max-time=3600
```

- `--timeout=7200` matches the import jobs' `$timeout`; `retry_after` (7800) stays above it so a
  long import is never double-dispatched.
- `--max-time`/`--memory` recycle the worker so long-lived processes don't leak.
- 1–2 worker processes is plenty for an internal app; `imports-high` is the priority lane.

## 6. Scheduler (Forge → Scheduler)

Add the standard entry: `php artisan schedule:run` every minute. It drives `backup:database`
(daily), `clients:audit-data-quality` (weekly), and `ivr:check-budget` (daily).

## 7. Deploys ("push a patch from here")

- Turn on **Quick Deploy** for the `main` branch and paste **`deploy.sh`** as the deploy script
  (or run it over SSH). A push to `main` then deploys: pull → `composer install --no-dev` →
  `migrate --force` → `optimize` + `filament:optimize` → `queue:restart`.
- Optionally gate auto-deploy on the GitHub Actions test suite (`.github/workflows/tests.yml`) so
  a red build never reaches production.

## 8. Go-live data migration (one time)

1. **Dump** the current working database: `pg_dump --no-owner --no-acl campaign_tracker > prod.sql`
   (time it on a copy first — ~5M message rows is not instant).
2. **Restore** into the production Postgres: `psql "$PROD_DSN" < prod.sql`.
3. **Never run `migrate:fresh`** — several migrations are Postgres-only/unguarded and can't rebuild
   from scratch (see CLAUDE.md). The initial prod DB = restore the dump, then `migrate --force`
   for any newer migrations.
4. **Verify**: row counts match the source for the big tables (`clients`, `client_phone_numbers`,
   `whatsapp_messages`, `ivr_call_records`, `ownerships`), and the settings/seed rows exist
   (`ivr_settings`, `whatsapp_settings`, locations/areas/projects, tags).
5. `VACUUM ANALYZE` the big tables so the planner has fresh stats + visibility maps.

## 9. Post-deploy smoke test

- Log in to `/admin`.
- Upload a small campaign-results CSV → confirm a worker processes it (status reaches completed).
- Open a campaign page, the Template Performance and Failure Analysis pages, run a numbers export.
- Confirm a database notification appears (bell icon).

## 10. Ongoing

- Backups: keep `backup:database` **and** enable the provider's DB backups/snapshots; periodically
  test a restore.
- Watch `failed_jobs`; wire alerting once error monitoring (Sentry) is added.
- Point an uptime monitor at the built-in `/up` health endpoint.

## 11. Security checklist (see also the panel hardening in code)

- `APP_DEBUG=false`, `APP_ENV=production`.
- SSL via Let's Encrypt (Forge one-click); HTTP→HTTPS is forced in production.
- Lock `/admin`: enable 2FA, keep self-registration disabled, and consider an IP allowlist
  (office/VPN) since it's an internal panel over real contact data.
- Least-privilege DB user; `.env` never committed; fresh `APP_KEY`.
