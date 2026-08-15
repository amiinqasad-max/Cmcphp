# CMCPHP

A custom CMS and content publishing platform built from scratch on **Laravel + Filament + PostgreSQL** — a WordPress alternative purpose-built for article publishing, with a three-video engagement system, first-party analytics, an AdSense-ready advertisement manager, admin-managed navigation menus, and a full SEO system (canonical URLs, dynamic sitemap/robots, Open Graph/Twitter cards, JSON-LD).

No WordPress, no PHP CMS packages, no Supabase SDK/PostgREST/Auth — the app talks to Postgres directly via Eloquent. (Postgres itself may be hosted on Supabase's infrastructure; see `.env.example`.)

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

**The full 12-phase roadmap is complete** (see `docs/ARCHITECTURE.md` §8): Phase 1 (project setup), Phase 2 (CMS core: Posts/Pages/Categories/Tags/Media), Phase 3 (menus, full SEO system, sitemap.xml/robots.txt, redirects, general settings), Phase 4 (three-video article system), Phase 5 (reading + video engagement tracking), Phase 6 (completion engine + auto-next), Phase 7 (advertisement manager), Phase 8 (analytics dashboard), Phase 9 (Users/Roles/Permissions/Comments/Activity log), Phase 10 (performance: Redis caching, queues, index audit), Phase 11 (security audit + automated tests), Phase 12 (production deployment config).

## Phase 3 highlights

- **Menus**: admin-managed navigation (`/admin/menus`) with unlimited-depth drag-and-drop nesting, arbitrary locations (Primary Navigation/Header/Footer/Mobile Navigation out of the box, more without a code change), and internal-page/post/category or custom-URL items. A menu item pointing at unpublished or deleted content simply stops rendering rather than producing a dead link.
- **SEO**: a single `SeoService` resolves title/description/canonical/robots/OG/Twitter metadata for every content type through one four-tier fallback (explicit value → content-specific fallback → global setting → safe default), so nothing downstream reimplements fallback logic. Article/breadcrumb/website/organization JSON-LD is generated from real content, never fabricated fields.
- **Sitemap & robots**: `/sitemap.xml` and `/robots.txt` are real, cached, auto-invalidating routes — never static files an admin has to remember to update.
- **Redirects**: admin-managed 301/302/307/308 redirects (`/admin/redirects`) with loop detection, a single cached lookup (no per-request DB query), and hit tracking — implemented as a route fallback that structurally cannot shadow the admin panel, API, or auth routes.
- **Settings**: a centralized `/admin/manage-settings` page (site identity, contact, social links, SEO defaults) built on the same `settings` key/value table the rest of the app already used, not a parallel config store.
