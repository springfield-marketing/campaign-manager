# Campaign Tracker

Laravel + Filament admin app for managing real-estate marketing contacts (clients,
phone numbers, IVR & WhatsApp campaigns, imports/exports). Admin panel at `/admin`.

## Running the app

`composer dev` runs the stack, but the JS/realtime side (vite, reverb) has been removed —
the app is plain Laravel + Filament with assets prebuilt in `public/build`. Run:

```bash
php artisan serve                                              # web server -> /admin
php artisan queue:work --queue=imports-high,imports,default,analysis   # background jobs
php artisan pail                                               # logs (optional)
```

## Database & tests

- **Postgres** in all environments. Raw SQL must use `= true`/`= false` for booleans.
- The phpunit config points at SQLite `:memory:`, but several migrations are Postgres-only
  and unguarded, so the suite must run against a **dedicated Postgres test DB** — never the
  dev DB (`RefreshDatabase` wipes it). For manual checks against live data, wrap in
  `DB::beginTransaction()` / `DB::rollBack()`.

## Data rules — import/export edge cases

When fixing an import/export edge case (merge/dedup/normalization/suppression bug), document
it in **[`docs/data-rules/`](docs/data-rules/)** — don't just patch the code silently. Each
case gets a stable ID (`IMP-`/`EXP-`) tying together: a registry entry, a `// IMP-xxx`
breadcrumb comment at the code guard, and a regression test named after the ID. See
[`docs/data-rules/README.md`](docs/data-rules/README.md).

Relevant tooling for client-merge data quality:
- `php artisan clients:audit-data-quality` — detector for bad merges (incl. IMP-001 stub-name
  multi-number clients). Read-only.
- `php artisan clients:split-name-collisions [--apply]` — remediation: split a client that
  absorbed unrelated numbers back into one client per phone.

## Conventions

- Commit and push after each logical change (don't batch at end of session). Branch off `main`
  for PRs.
