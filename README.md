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

## Status

Being built incrementally, phase by phase (see the roadmap in `docs/ARCHITECTURE.md` §8). Phase 1 (project setup: Laravel, PostgreSQL, Filament, auth, base public layout) is complete.
