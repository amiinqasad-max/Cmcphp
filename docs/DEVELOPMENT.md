# Local Development

## Requirements
- PHP 8.3+ (developed against 8.4) with `pdo_pgsql`, `pgsql`, `redis` extensions
- PostgreSQL 16+
- Redis 6+
- Node 20+ / npm
- Composer 2

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Create the database (adjust to your local Postgres setup)
createdb cmcphp

php artisan migrate
php artisan storage:link   # only needed if FILESYSTEM_DISK=public (local dev without S3/R2 credentials)
php artisan db:seed   # regenerates Filament Shield permissions, roles, and a super_admin user (admin@cmcphp.test / password)

npm install
npm run build   # or `npm run dev` while developing
```

Then serve the app (`php artisan serve` or your webserver of choice) and:
- Public site: `/`
- Admin panel: `/admin` — log in with the seeded super admin above (change the password immediately in any shared environment).

Re-run `php artisan db:seed --class=PermissionSeeder` after adding new Filament resources, so their generated permissions actually exist in the database (see `docs/ARCHITECTURE.md`'s Phase 2 implementation notes for why this is a separate step from `shield:generate`).

## Queues & Scheduler

Tracking ingestion, analytics aggregation, and video/image processing run through queued jobs (`QUEUE_CONNECTION=redis`):

```bash
php artisan queue:work --queue=default
php artisan schedule:work   # local equivalent of the production cron entry
```

## Testing

Tests run against a real PostgreSQL database (`cmcphp_test`) rather than SQLite, to keep parity with production (Postgres-specific features like full-text search indexes are part of the schema).

```bash
createdb cmcphp_test
php artisan test
```

## Object storage

Set `FILESYSTEM_DISK=s3` (with `AWS_ENDPOINT` + the usual `AWS_*` credentials — add `AWS_USE_PATH_STYLE_ENDPOINT=true` for R2/MinIO) for staging/production; it works with AWS S3, Cloudflare R2, or any S3-compatible provider. Locally without bucket credentials, `.env` defaults to `FILESYSTEM_DISK=public`, which writes to `storage/app/public` (served via the `php artisan storage:link` symlink).

See `docs/ARCHITECTURE.md` for the full system design and phased roadmap.
