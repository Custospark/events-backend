# Custospark Events — Production Deployment

Events has **one environment only**: production. There is no staging target.

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
`public/production`, so one `git pull` on the server ships both the API code
and the web build.

## Non-negotiable server rules

- **Never delete existing server content.** Preserve placeholder folders and
  backups by renaming them, not removing them.
- `exec()` is disabled on this host → `php artisan storage:link` fails. Ensure
  the link manually: `[ -e public/storage ] || ln -s ../storage/app/public public/storage`.
- Never run `php artisan key:generate` again after the first deploy. `push_env.py`
  reads the live `APP_KEY` from the server and preserves it.
- Copy the web build with `cp -rT` (carries `.htaccess`); never `cp source/*`.
- `crontab` is not installed. Scheduler cron is added in **hPanel** only.
- Do not commit `.env` or `.env.production`.

## Local inputs (never committed)

`Backend/.env` must contain the deployment SSH keys:

```dotenv
SSH_DEPLOY_HOST=...
SSH_DEPLOY_PORT=...
SSH_DEPLOY_USER=...
SSH_DEPLOY_PASSWORD=...
```

`Backend/.env.production` (gitignored) supplies the production DB and SMTP
values. Copy from `.env.production.example`, then fill `DB_*` and `MAIL_*`.

`push_env.py` composes the server `.env` from:

1. production DB values in `Backend/.env.production` (or the `#Production`
   commented DB block in `Backend/.env`);
2. carried secrets from `Backend/.env` (`MAIL_*`, `PESAPAL_*`,
   `CUSTOSPARK_*`, tenant/admin settings);
3. forced production URLs, CORS, Sanctum, HTTPS, and PESAPAL settings;
4. the live server `APP_KEY`.

## First-time setup (already completed)

```bash
APP=/home/u214605677/domains/events-api.custospark.com
git clone https://github.com/Custospark/events-backend.git "$APP"
cd "$APP"
composer install --no-dev --optimize-autoloader --no-interaction
```

Upload `.env` and finish the first-time steps:

```bash
cd C:/Dev/events/backend
python scripts/push_env.py production

cd "$APP"
php artisan key:generate --force   # FIRST DEPLOY ONLY
php artisan migrate --force
[ -e public/storage ] || ln -s ../storage/app/public public/storage
php artisan optimize:clear && php artisan config:cache && php artisan view:cache
```

Wire the API docroot **without deleting** the placeholder:

```bash
cd /home/u214605677/domains/custospark.com/public_html
[ -d events-api ] && [ ! -L events-api ] && \
  mv events-api "events-api-placeholder-$(date +%Y%m%d-%H%M%S)"
[ -L events-api ] || ln -s "$APP/public" events-api
```

Verify: `https://events-api.custospark.com/up` and
`https://events-api.custospark.com/api/v1/health`.

## Frontend deploy

```bash
cd C:/Dev/events/frontend
npm run deploy:web:production
```

This builds with `--mode production --base /`, copies to
`Backend/public/production`, adds `deploy/htaccess.production`, commits only
that folder in the backend repo, and pushes `main`.

On the server (no wipe):

```bash
cd /home/u214605677/domains/events-api.custospark.com
git pull --ff-only origin main
cd /home/u214605677/domains/custospark.com/public_html
mkdir -p events
cp -rT /home/u214605677/domains/events-api.custospark.com/public/production events
```

`events/default.php` is intentionally left in place; `DirectoryIndex index.html`
in the deployed `.htaccess` makes the SPA win.

Verify the asset hash changed and deep links return `index.html`:

```bash
curl -sI https://events.custospark.com/ | head
curl -s https://events.custospark.com/ | grep -o 'assets/index-[^"]*'
curl -s -o /dev/null -w '%{http_code}\n' https://events.custospark.com/orders/TEST
```

## Backend deploy (API changes only)

```bash
cd /home/u214605677/domains/events-api.custospark.com
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan view:cache
[ -e public/storage ] || ln -s ../storage/app/public public/storage
```

If `Backend/.env` inputs change, re-run `python scripts/push_env.py production`,
then clear/rebuild the config cache.

## hPanel cron (one entry, every minute)

```
/usr/bin/php /home/u214605677/domains/events-api.custospark.com/artisan schedule:run
```

All five cron fields `*`. Do not add per-task crons.

Current schedule:

- `orders:expire-pending` — every minute
- `payments:reconcile` — every five minutes

## PesaPal production IPN

After the first deploy and with the production consumer key/secret in place:

```bash
cd /home/u214605677/domains/events-api.custospark.com
php artisan pesapal:register-ipn
```

Copy the returned `PESAPAL_IPN_ID=...` into the local `Backend/.env.production`,
re-run `push_env.py production`, then
`php artisan config:cache`. If the ID is empty the gateway registers it
automatically at payment time and caches it.

## Verification checklist

- [ ] `https://events-api.custospark.com/api/v1/health` → `200` JSON, version OK
- [ ] `https://events.custospark.com/` → SPA title and current hashed assets
- [ ] `https://events.custospark.com/orders/TEST` → `200` SPA shell
- [ ] API CORS allows `Origin: https://events.custospark.com`
- [ ] `php artisan schedule:list` shows both scheduled commands
- [ ] `storage/logs/laravel.log` has no new errors after traffic
- [ ] hPanel cron exists for `schedule:run`
- [ ] `public/storage` symlink exists
- [ ] `APP_KEY` unchanged on redeploy
- [ ] `events/default.php` still present (not deleted) and not served

## Rollback

- Frontend: check out the previous backend commit and copy the previous
  `public/production` content over `public_html/events` (do not wipe).
- API code: `git reset --hard <previous-commit>` then run the backend deploy
  steps above.
- Never roll back via `key:generate`; it invalidates sessions and encrypted data.
