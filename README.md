# CMCPHP

A custom CMS and content publishing platform built from scratch on **Laravel + Filament + PostgreSQL** — a WordPress alternative purpose-built for article publishing, with a three-video engagement system, first-party analytics, and an AdSense-ready advertisement manager.

No WordPress, no PHP CMS packages, no Supabase.

## Stack

- **Backend**: Laravel 11, PHP 8.3+, PostgreSQL
- **Admin**: Filament 3, Livewire, Alpine.js
- **Public site**: Blade + Tailwind CSS, Livewire where interactive
- **Cache/Queues**: Redis
- **Storage**: S3-compatible object storage (AWS S3, Cloudflare R2, MinIO, ...)
- **Infra**: Nginx, PHP-FPM, queue workers, scheduler

## Docs

- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — full system architecture, database ERD, folder structure, tracking/ad/security architecture, and the phased implementation roadmap.
- [`docs/DEVELOPMENT.md`](docs/DEVELOPMENT.md) — local setup.
- [`docs/SECURITY.md`](docs/SECURITY.md) — security audit against the architecture's own checklist: what's implemented, what's configuration-only at deploy time, known upstream items.
- [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — production deployment: server requirements, env config, nginx/PHP-FPM/queue-worker/scheduler setup (`deploy/`), SSL, backups, zero-ish-downtime deploys.

## Status

Being built incrementally, phase by phase (see the roadmap in `docs/ARCHITECTURE.md` §8). Complete: Phase 1 (project setup), Phase 2 (CMS core: Posts/Pages/Categories/Tags/Media), Phase 4 (three-video article system), Phase 5 (reading + video engagement tracking), Phase 6 (completion engine + auto-next), Phase 7 (advertisement manager), Phase 8 (analytics dashboard), Phase 9 (Users/Roles/Permissions/Comments/Activity log), Phase 10 (performance: Redis caching, queues, index audit), Phase 11 (security audit + automated tests), Phase 12 (production deployment config).

Not yet built: **Phase 3** (Menus, full SEO system — sitemap.xml/robots.txt/redirects, Settings admin UI). A minimal `SettingsService` and per-post SEO metadata panel were pulled forward into Phase 2 since Posts/Pages need those to be usable at all, but the dedicated Phase 3 system (menu builder, sitemap/robots generation, redirect manager, and a general settings UI) is still outstanding.
