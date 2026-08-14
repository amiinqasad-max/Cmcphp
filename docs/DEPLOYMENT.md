# Deployment

Production deployment guide for CMCPHP (§55). The `deploy/` directory at the
repo root has the actual config file examples this document references —
every one of them ends in `.example` and needs paths/domains/credentials
filled in for your server before use; none are meant to be copied verbatim.

## Server requirements

- **PHP 8.4** (matches the dev environment this app was built and tested
  against; `composer.json` requires `^8.2` as a floor) with extensions:
  `pgsql`, `redis` (phpredis), `mbstring`, `bcmath`, `gd` or `imagick`,
  `intl`, `zip`, `fileinfo`, `curl`. `opcache` enabled, with
  `opcache.validate_timestamps=0` for production (see the deploy script's
  OPcache-reset step — this is *why* that step exists).
- **PostgreSQL 16** (or newer 16.x/17.x — nothing here depends on
  version-specific features beyond what Laravel's `pgsql` driver already
  requires).
- **Redis 7+** — used for cache, sessions, and queues, each on its own
  logical DB number (`REDIS_DB`/`REDIS_CACHE_DB`/`REDIS_QUEUE_DB` — see
  `config/database.php`'s `redis.default` / `redis.cache` / `redis.queue`
  connections) so a `queue:flush` or cache eviction can never touch the
  others' keys.
- **nginx** (or another PHP-FPM-fronting web server — the example config
  is nginx specifically).
- **Node.js 22+** — build-time only, for `npm run build` (Vite). Not
  needed at runtime; the built `public/build/` assets are static files.
- **Composer 2**.
- A process supervisor for queue workers (**supervisor** or **systemd**
  — both examples provided, pick one) and a scheduler trigger
  (**cron** or a **systemd timer** — same choice).

## Environment configuration for production

Start from `.env.example` and change at minimum:

| Variable | Production value | Why |
|---|---|---|
| `APP_ENV` | `production` | Changes Laravel's internal defaults (error verbosity, etc). |
| `APP_DEBUG` | `false` | A `true` debug flag leaks stack traces, `.env` values, and raw query bindings to any visitor who triggers a 500 — see `docs/SECURITY.md`. |
| `APP_KEY` | generate fresh | Run `php artisan key:generate` **once** on first deploy. Never commit it, never reuse a dev/staging key in production. |
| `APP_URL` | your real HTTPS origin | Used for generated absolute URLs (sitemap links, email links, etc). |
| `DB_*` | real credentials | Non-default password; don't expose port 5432 publicly — bind Postgres to localhost or a private network and connect over that. |
| `SESSION_SECURE_COOKIE` | `true` | Only send the session cookie over HTTPS. Left `false` in `.env.example` because local dev is plain HTTP. |
| `REDIS_*` | real credentials | Set `REDIS_PASSWORD`; don't expose port 6379 publicly. |
| `ADSENSE_CLIENT_ID` | your real AdSense publisher ID | Blank locally; the ad manager (§7) won't render real ad units without it. |
| `AWS_*` / `FILESYSTEM_DISK=s3` | real object storage credentials | See "Media storage" below — recommended over the local disk for anything beyond a single small server. |
| `MAIL_*` | a real transport (Postfix relay, SES, Postmark, etc.) | `.env.example` defaults to `log`, which just writes mail to the log file. |

Also rotate the seeded super-admin credentials
(`admin@cmcphp.test` / `password`, from `database/seeders`) immediately —
either edit the seeder before first production seed, or change the
password through the admin panel right after first login. Never leave the
seeded password live on a public deployment.

## First deploy

```bash
git clone <repo> /var/www/cmcphp
cd /var/www/cmcphp
cp .env.example .env
# edit .env per the table above
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force   # seeds roles/permissions + the super-admin account — rotate its password immediately after
php artisan storage:link      # only relevant if FILESYSTEM_DISK=public rather than s3
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Then wire up nginx, PHP-FPM, the queue worker supervisor, and the
scheduler trigger — see the sections below.

## Web server (nginx + PHP-FPM)

- `deploy/nginx/cmcphp.conf.example` — server block: HTTP→HTTPS redirect,
  TLS, `client_max_body_size` sized for video/media uploads through the
  Filament admin, static-asset caching for Vite's fingerprinted build
  output, and an HSTS header (the only header nginx needs to add — the
  app's own `SetSecurityHeaders` middleware, §34, already sets
  `X-Content-Type-Options`/`X-Frame-Options`/`Referrer-Policy`/
  `Permissions-Policy` on every response, admin and public alike, so
  don't duplicate those in nginx).
- `deploy/php-fpm/cmcphp.conf.example` — a dedicated pool (not the
  distro's default `www` pool), `pm.max_children` etc. sized for a
  small/medium VPS — tune to your actual RAM and traffic.

The nginx example's TLS server block also sets `fastcgi_param HTTPS on;` —
nginx's stock `fastcgi_params` does **not** set this on its own, so without
it PHP has no way to know the request arrived over TLS even though this
whole server block is HTTPS-only. `App\Providers\AppServiceProvider` backs
this up app-side with `URL::forceScheme()` derived from `APP_URL`, so
generated URLs (canonical links, sitemap entries, OG/Twitter tags — §3)
are correct either way, but set the nginx line too rather than relying on
just one half of the fix.

Test and reload after installing:

```bash
nginx -t && systemctl reload nginx
systemctl reload php8.4-fpm
```

**A note on `/sitemap.xml` and `/robots.txt`**: both are dynamic Laravel
routes (`App\Http\Controllers\Public\SitemapController`/`RobotsController`,
§4/§5), not static files. `try_files $uri $uri/ /index.php?$query_string;`
in the example config serves any *literal file* under `public/` before it
ever reaches Laravel — so if a `public/robots.txt` or `public/sitemap.xml`
file ever gets committed (Laravel's own installer ships a stub
`public/robots.txt`, since deleted from this repo for exactly this
reason), it silently shadows the real route forever, on both nginx and
`php artisan serve`'s dev router. Don't add either file back.

## Queue workers

Three jobs run on the queue (`app/Jobs/`):
`ProcessEngagementEventBatch` and `ProcessAdEventBatch` (tracking
ingestion, §5/§7 — kept off the request path so a burst of visitor
tracking beacons can't slow down page responses) and
`DetectVideoDurationJob` (probes uploaded video duration server-side via
getID3, §4 — never trusts client-reported duration). All three run on the
`redis` queue connection's dedicated `queue` Redis DB.

Pick **one**:

- **supervisor**: `deploy/supervisor/cmcphp-worker.conf.example` →
  `/etc/supervisor/conf.d/cmcphp-worker.conf`, then
  `supervisorctl reread && supervisorctl update && supervisorctl start cmcphp-worker:*`.
- **systemd**: `deploy/systemd/cmcphp-worker.service.example` → template
  unit, `systemctl enable --now cmcphp-worker@1 cmcphp-worker@2`.

Both run `queue:work` (not `queue:listen` — `work` doesn't reboot the
framework per job, so it's faster, but it means code changes need
`php artisan queue:restart` to take effect, which the deploy script
already does) with `--tries=3` so a job that keeps failing lands in the
failed-jobs table instead of looping forever, and `--max-time=3600` to
recycle worker processes hourly.

Check failed jobs with `php artisan queue:failed`; retry with
`php artisan queue:retry <id>` or `--all`.

## Scheduler

`routes/console.php` registers three scheduled commands: nightly
`analytics:aggregate` (rolls the previous day's raw tracking events into
`daily_rollups`, §39), nightly `tracking:prune` (deletes raw events
already captured in rollups, keeping the append-only tables bounded), and
`posts:publish-scheduled` every five minutes (flips `PostStatus::Scheduled`
posts to `Published` once their `published_at` arrives, §4/§6).

All three are driven by a single scheduler tick — don't cron them
individually. Pick **one**:

- **cron**: `deploy/cmcphp-crontab.example` — one line, `* * * * *`,
  running `php artisan schedule:run`.
- **systemd timer**: `deploy/systemd/cmcphp-scheduler.service.example` +
  `.timer.example`, same one-minute cadence.

## Media storage

`.env.example` supports `FILESYSTEM_DISK=public` (local disk,
`storage/app/public` symlinked via `php artisan storage:link`) or
`FILESYSTEM_DISK=s3` (any S3-compatible object store — AWS S3, Cloudflare
R2, MinIO — via the `AWS_*` variables). For anything beyond a single
small VPS, prefer `s3`: local-disk media doesn't survive a server
rebuild, doesn't scale past one app server if you ever add a second, and
every deploy script in `deploy/` assumes the app root itself is
disposable/replaceable.

## SSL

Use certbot for Let's Encrypt certificates:

```bash
apt install certbot python3-certbot-nginx
certbot --nginx -d example.com -d www.example.com
```

Certbot installs its own renewal timer/cron automatically
(`certbot renew` via a systemd timer on modern distros) — verify it
exists (`systemctl list-timers | grep certbot`) rather than assuming.

## Backups

Not automated by anything in this repo — set up independently:

- **Database**: nightly `pg_dump` (or your hosting provider's managed
  Postgres backup/PITR if you're not self-hosting Postgres) retained on a
  rolling window, stored off-server.
- **Media**: if `FILESYSTEM_DISK=s3`, versioning/cross-region replication
  on the bucket covers this. If `FILESYSTEM_DISK=public` (local disk),
  back up `storage/app/public` alongside the database — it's the only
  copy of uploaded media.
- **`.env`**: back up separately and securely (it holds `APP_KEY`, DB/Redis
  credentials, AdSense/S3 secrets) — never in the same place as the
  application code repo.

Test restores periodically; an untested backup is not a backup.

## Monitoring

Nothing here is wired to an external APM/alerting product by design (it's
a per-deployment choice) — pointers only:

- **Health check**: `bootstrap/app.php` registers Laravel's built-in
  `/up` health route — point an uptime monitor (UptimeRobot, Pingdom, a
  load-balancer health check, etc.) at it.
- **Application errors**: `LOG_CHANNEL`/`LOG_LEVEL` in `.env` control
  Laravel's own logging; wire `config/logging.php` to a `sentry`,
  `bugsnag`, or syslog-forwarding channel if you want off-server error
  aggregation — none is installed by default.
- **Queue health**: `php artisan queue:failed` count and supervisor's/
  systemd's own process-restart counters are the two things worth
  alerting on — a queue worker that's crash-looping silently stops
  processing tracking events and video-duration detection.
- **First-party analytics** (§8 dashboard, site-wide/per-article/ad) is a
  product feature for editors, not an ops monitoring tool — don't
  conflate the two.

## Zero(ish)-downtime deploys

`deploy/deploy.sh.example` is a straight-line script covering: maintenance
mode → pull → `composer install --no-dev --optimize-autoloader` →
`npm ci && npm run build` → `migrate --force` → rebuild `config`/`route`/
`view`/`event` caches → `queue:restart` (workers finish their current job,
then pick up new code on the next one) → OPcache reset (needed because
production should run with `opcache.validate_timestamps=0`, so PHP-FPM
workers won't otherwise notice the code changed) → maintenance mode off.

It's called "zero-ish" deliberately: `php artisan down` shows a
maintenance page for the roughly-few-seconds migrations take, which is
not literally zero downtime. True zero-downtime needs a second release
directory plus an atomic symlink swap (Deployer/Envoyer-style, or a
blue/green setup behind a load balancer) — out of scope for a single-app-
root example, but the script is the right base to extend if you get
there.

Run it from CI (GitHub Actions/whatever) over SSH, or by hand for a
smaller setup — nothing here assumes a specific CI provider.

## What's explicitly out of scope here

Consistent with `docs/SECURITY.md`'s own "out of scope" section:

- **Content-Security-Policy** headers — needs tuning against your actual
  AdSense/YouTube/Vimeo embed surface; a wrong default silently breaks
  ads and embeds, so it's a deploy-time decision, not a shipped default.
- **Multi-server / load-balanced deployment** — everything above assumes
  one app server. Scaling to more needs session/cache already being on
  Redis (✅ already true here) plus moving media off local disk to `s3`
  (✅ documented above) plus a shared load balancer in front — the pieces
  are in place, but wiring them together is a per-infrastructure choice.
- **CI/CD pipeline definition** — `deploy/deploy.sh.example` is written to
  be *callable from* CI, not a GitHub Actions workflow itself.
