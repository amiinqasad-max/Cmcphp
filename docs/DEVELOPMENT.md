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
php artisan db:seed   # creates roles + a super_admin user (admin@cmcphp.test / password)

npm install
npm run build   # or `npm run dev` while developing
```

Then serve the app (`php artisan serve` or your webserver of choice) and:
- Public site: `/`
- Admin panel: `/admin` — log in with the seeded super admin above (change the password immediately in any shared environment).

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

`FILESYSTEM_DISK=s3` is the default and works with AWS S3, Cloudflare R2, or any S3-compatible provider — set `AWS_ENDPOINT` (and `AWS_USE_PATH_STYLE_ENDPOINT=true` for R2/MinIO) alongside the usual `AWS_*` credentials. For local development without a bucket, set `FILESYSTEM_DISK=local`.

See `docs/ARCHITECTURE.md` for the full system design and phased roadmap.
