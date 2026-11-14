# Custospark Events — Production Deployment Guide

> Audience: the next agent or developer deploying Events. Read this before
> touching the server. Events has **one environment only**: production.
> There is no staging target.

## 0. TL;DR for the next agent

Normal deploy after source changes:

```bash
# Frontend (builds into the backend repo, then push)
cd C:/Dev/events/frontend
npm run deploy:web:production

# Server: pull API + web build, then copy the build WITHOUT wiping
ssh <host>   # or: python C:/Dev/events/backend/scripts/ssh_run.py "<cmd>"
cd /home/u214605677/domains/events-api.custospark.com
git pull --ff-only origin main
cd /home/u214605677/domains/custospark.com/public_html
mkdir -p events
cp -rT /home/u214605677/domains/events-api.custospark.com/public/production events
```

Backend-only changes:

```bash
cd /home/u214605677/domains/events-api.custospark.com
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction   # only if composer.lock changed
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan view:cache
[ -e public/storage ] || ln -s ../storage/app/public public/storage
```

**Do not delete server files.** Preserve placeholder folders and old builds.

## 1. Current production state (2026-11-14)

- Backend repo deployed at commit `588fde6` (`main`): source commit `c93be0c`
  plus the production web build.
- Event admins and platform admins can edit the fee bearer. Existing orders
  keep the fee bearer recorded at transaction time.
- Admin success actions use toast feedback; fee breakdown remains internal.
- Production DB `u214605677_events` contains the processing-fee migration.
  Existing seeded events were preserved; no production data was reset.
- Backup created before migration:
  `/home/u214605677/domains/backups/events-20261114-102637.sql.gz`.
- Production `.env` carries `EVENTS_GATEWAY_FEE_PERCENT=3.5`,
  `EVENTS_CUSTOSPARK_FEE_PERCENT=1.5` and
  `EVENTS_DEFAULT_FEE_BEARER=customer`.
- The public event API exposes only the combined `processing_fee_percent`
  and `processing_fee_bearer`; fee breakdown stays internal.
- Gateway descriptions include `PFB:CUSTOMER` or `PFB:ORGANIZER`.
- Frontend build deployed from this workspace; live assets include
  `assets/index-B-WEuK88.js` and `assets/index-BAu19NrL.css`.
- Production DB `u214605677_events` remains seeded with the original
  two tenants, three users, three events and six ticket types.
- Tenant `cocis`: `College of Computing and Information Science`.
- Tenant `gulu`: `Gulu City Events`.
- COCIS event: `CoCIS Class of 2027 Finalists' Dinner`, 20 March 2027,
  Arirang Hotel, single `120,000 UGX`, couple `200,000 UGX`.
- Gulu events: `Gulu City Marathon 2027` (upcoming) and
  `Gulu Business Expo 2026` (past).
- Platform admin: `oscar@custospark.com`.
- COCIS admin: `jimisaac8082@gmail.com`.
- Gulu admin: `admin@guluevents.ug`.
- Seed passwords are stored in the gitignored
  `C:\Dev\events\backend\.env.production`. **Do not print or commit them.**
- `public/storage` symlink exists and points to `../storage/app/public`.
- `public_html/events-api` is a symlink to the backend `public/` folder.
  The old placeholder was preserved as `events-api-placeholder-<timestamp>`.
- `public_html/events` contains the frontend build. `events/default.php` was
  intentionally preserved; `DirectoryIndex index.html` in the deployed
  `.htaccess` makes the SPA win.
- PesaPal production IPN registered for
  `https://events-api.custospark.com/api/v1/payments/ipn`; the id is in
  `Backend/.env.production` and the server `.env`.
- **Pending manual action:** add the hPanel cron for `schedule:run` (§8).
- `https://events-api.custospark.com/api/v1/health` returns `200` JSON.
- `https://events.custospark.com/` serves the SPA; deep links return the SPA.

## 2. Topology and paths

| Item | Value |
| --- | --- |
| Host / SSH | `147.79.103.136:65002`, user `u214605677` |
| Backend app dir | `/home/u214605677/domains/events-api.custospark.com` |
| Backend repo | `https://github.com/Custospark/events-backend.git` (`main`) |
| Backend URL | `https://events-api.custospark.com` |
| Frontend repo | `https://github.com/Custospark/events-frontend.git` (`main`) |
| Frontend URL | `https://events.custospark.com` |
| Shared docroot | `/home/u214605677/domains/custospark.com/public_html` |
| Frontend docroot | `.../public_html/events` |
| API docroot | `.../public_html/events-api` → symlink to the app `public/` |

The deployed frontend build lives in the **backend** repo under
`public/production`, so one `git pull` on the server ships both the API code and
the web build.

## 3. Hard rules

- **Never delete existing server content.** Preserve placeholders and backups by
  renaming (`mv`), not removing (`rm -rf`).
- `exec()` is disabled on this host → `php artisan storage:link` fails. Ensure
  the link manually:
  `[ -e public/storage ] || ln -s ../storage/app/public public/storage`.
- Never run `php artisan key:generate` again after first setup. `push_env.py`
  reads the live server `APP_KEY` and preserves it.
- Copy the web build with `cp -rT` (carries `.htaccess`); never `cp source/*`.
- `crontab` is not installed. Scheduler cron is added in **hPanel** only.
- Do not commit `.env` or `.env.production`.
- Do not run `php artisan db:seed` while the config is cached. Laravel `env()`
  calls in the seeder return `null` when config is cached (see §7).

## 4. Local inputs (never committed)

`Backend/.env` contains the deployment SSH keys:

```dotenv
SSH_DEPLOY_HOST=...
SSH_DEPLOY_PORT=...
SSH_DEPLOY_USER=...
SSH_DEPLOY_PASSWORD=...
```

These are the same Hostinger account credentials used by Custospark Academy.
`Backend/.env.production` (gitignored) is the source of truth for production
DB, SMTP, seed passwords and the PesaPal IPN id. Copy from
`.env.production.example` and fill `DB_*` / `MAIL_*`.

`scripts/push_env.py production` composes the server `.env` from:

1. production DB values in `Backend/.env.production` (or the `#Production`
   commented DB block in `Backend/.env`);
2. carried secrets from `Backend/.env` (`MAIL_*`, `PESAPAL_*`,
   `CUSTOSPARK_*`, tenant/admin settings);
3. production overrides from `Backend/.env.production` (`SEED_*`,
   `PESAPAL_IPN_ID`, SMTP, etc.);
4. forced production URLs, CORS, Sanctum, HTTPS and PESAPAL settings;
5. the live server `APP_KEY`.

It never prints credential values. Use `--render <path>` to inspect locally
with masked output.

## 5. Frontend deploy

```bash
cd C:/Dev/events/frontend
npm run deploy:web:production
```

What the script does (`scripts/deploy-to-backend.mjs`):

1. Builds with `tsc -b` and `vite build --mode production --base / --outDir dist/web`.
2. Copies `dist/web` → `Backend/public/production`.
3. Copies `deploy/htaccess.production` → `public/production/.htaccess`.
4. Commits **only** `public/production` in the backend repo and pushes `main`.
5. Prints the exact server copy command.

On the server (no wipe):

```bash
cd /home/u214605677/domains/events-api.custospark.com
git pull --ff-only origin main
cd /home/u214605677/domains/custospark.com/public_html
mkdir -p events
cp -rT /home/u214605677/domains/events-api.custospark.com/public/production events
```

Why `cp -rT`: it copies the contents of the source directory into the
destination (including `.htaccess`) without nesting the source folder name.
Never use `cp source/*`; the dotfile is skipped. Never wipe `events/`; old
hashed assets are harmless and `default.php` must be preserved.

Verify:

```bash
curl -s https://events.custospark.com/ | grep -o 'assets/index-[^"]*'
curl -s -o /dev/null -w '%{http_code}\n' https://events.custospark.com/orders/TEST
```

## 6. Backend deploy (API changes only)

```bash
cd /home/u214605677/domains/events-api.custospark.com
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan view:cache
[ -e public/storage ] || ln -s ../storage/app/public public/storage
```

If `composer.lock` did not change, the composer step can be skipped. On memory
errors use:

```bash
/usr/bin/php -d memory_limit=512M /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction
```

After changing **server `.env` values** (DB, mail, PesaPal, CORS, seed values):

```bash
cd C:/Dev/events/backend
python scripts/push_env.py production
```

Then on the server:

```bash
cd /home/u214605677/domains/events-api.custospark.com
php artisan optimize:clear && php artisan config:cache && php artisan view:cache
```

## 7. Seeding (already done; how to redo safely)

`DatabaseSeeder` creates the platform admin, both tenants, their admins, three
events and their ticket types. Passwords come from these variables in
`Backend/.env.production`:

- `SEED_PLATFORM_PASSWORD`
- `SEED_TENANT_ADMIN_PASSWORD` (COCIS admin)
- `SEED_GULU_ADMIN_PASSWORD` (Gulu admin)
- Optional emails: `SEED_PLATFORM_EMAIL`, `SEED_COCIS_ADMIN_EMAIL`,
  `SEED_GULU_ADMIN_EMAIL`

> **Important:** `DatabaseSeeder` calls `env()` directly. With a cached config,
> `.env` is not loaded, so those calls return `null` and the seeder falls back
> to its local defaults. Always clear the config cache before seeding.

Normal reseed, without deleting existing data:

```bash
cd /home/u214605677/domains/events-api.custospark.com
php artisan optimize:clear          # MUST run before db:seed
php artisan db:seed --force
php artisan config:cache
php artisan view:cache
```

Full reset, intentionally destructive:

```bash
cd /home/u214605677/domains/events-api.custospark.com
php artisan optimize:clear
php artisan migrate:fresh --seed --force
php artisan config:cache
php artisan view:cache
```

`migrate:fresh` drops every table and deletes all orders, tickets and payments.
Never run it on production unless a full data reset is explicitly intended.

## 8. hPanel cron (manual, still pending)

`crontab` is not available on the host. Add **one** cron in Hostinger hPanel:

```
/usr/bin/php /home/u214605677/domains/events-api.custospark.com/artisan schedule:run
```

All five fields `*` (every minute). Do not add per-task crons.

Current schedule (`php artisan schedule:list`):

- `orders:expire-pending` — every minute
- `payments:reconcile` — every five minutes

Without this cron, pending orders never expire and pending payments are not
reconciled.

## 9. PesaPal production IPN

Already registered. To register again (only if the URL or keys change):

```bash
cd /home/u214605677/domains/events-api.custospark.com
php artisan pesapal:register-ipn
```

Copy the printed ID into `Backend/.env.production` as `PESAPAL_IPN_ID=...`,
then:

```bash
cd C:/Dev/events/backend
python scripts/push_env.py production
```

and re-cache config on the server (§6). If the id is blank, the gateway
registers one automatically at payment time and caches it.

## 10. Verification checklist

- [ ] `https://events-api.custospark.com/api/v1/health` → `200` JSON
- [ ] `https://events.custospark.com/` → SPA title + current hashed assets
- [ ] `https://events.custospark.com/orders/TEST` → `200` SPA shell
- [ ] API CORS allows `Origin: https://events.custospark.com`
- [ ] `GET /api/v1/public/events` returns seeded events
- [ ] Platform admin login works; `GET /api/v1/tenants` returns `1`
- [ ] Tenant admin login works; `GET /api/v1/events` returns `1`
- [ ] `php artisan schedule:list` shows both commands
- [ ] `storage/logs/laravel.log` has no new unexpected errors
- [ ] hPanel cron exists for `schedule:run`
- [ ] `public/storage` symlink exists and resolves
- [ ] `public_html/events-api` symlink resolves to the app `public/`
- [ ] `APP_KEY` unchanged on redeploy
- [ ] `events/default.php` still present and not served
- [ ] `events-api-placeholder-*` still present (do not remove)

## 11. Troubleshooting

| Symptom | Cause / fix |
| --- | --- |
| `Call to undefined function ... exec()` | `storage:link` is blocked. Use `ln -s ../storage/app/public public/storage`. |
| `The [public/storage] link already exists` | Expected on redeploy; the link is fine. |
| `Invalid email or password` after seeding | Config was cached while seeding; run `optimize:clear`, reseed, then `config:cache`. |
| API 500 after env change | Stale config cache. `php artisan optimize:clear && php artisan config:cache`. |
| API `/api/v1/*` 404 | API docroot symlink missing/broken. Check `ls -ld public_html/events-api`; recreate symlink, do not delete the placeholder backup. |
| Frontend shows Hostinger placeholder | `.htaccess` missing `DirectoryIndex index.html`, or build not copied. Check `public_html/events/index.html` and `.htaccess`. |
| Frontend assets 404 | Build was copied with `cp source/*` (missing dotfiles) or wrong base. Use `cp -rT`; base must be `/`. |
| CORS error in browser | `CORS_ALLOWED_ORIGINS` wrong or config cached. Check `.env` and re-cache. |
| `git pull` not fast-forward | Server has a local commit. Inspect with `git status`/`git log`; only reset if there are no intentional local changes. |
| `APP_KEY` empty | First-time setup only: `php artisan key:generate --force`. Never regenerate on an existing DB. |
| `composer install` memory error | Prefix with `/usr/bin/php -d memory_limit=512M ...`. |

## 12. Rollback

- **Frontend:** check out the previous backend commit and copy that
  `public/production` over `public_html/events` (no wipe). Older hashed assets
  may remain; that is harmless.
- **API code:** `git reset --hard <previous-commit>`, then run the backend
  deploy steps in §6.
- **Database:** migrations are forward-only. Restore a dump only if necessary;
  never run `migrate:fresh` in production.
- Never “roll back” by regenerating `APP_KEY`; it invalidates sessions and
  encrypted data.

## 13. Related files

- `C:\Dev\events\backend\scripts\push_env.py` — compose/upload production `.env`
- `C:\Dev\events\backend\scripts\ssh_run.py` — run a server command over SSH
- `C:\Dev\events\backend\.env.production.example` — production env template
- `C:\Dev\events\frontend\scripts\deploy-to-backend.mjs` — frontend deploy
- `C:\Dev\events\frontend\deploy\htaccess.production` — SPA Apache config
- `C:\Dev\events\frontend\package.json` — `deploy:web:production` script
