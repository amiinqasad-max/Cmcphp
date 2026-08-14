# Security

A checklist-style summary against `docs/ARCHITECTURE.md` §34, covering what's implemented, what's configuration-only (your responsibility at deploy time), and known upstream items to track.

## Implemented in code

| Area | How |
|---|---|
| **Authentication** | Laravel/Breeze session auth for the admin panel; login rate-limited (5 attempts, then a timed lockout) via `App\Http\Requests\Auth\LoginRequest`. |
| **Authorization** | Filament Shield policies on every resource; `PostPolicy`/`ViewPostAnalytics` additionally enforce author-owns-their-own-content ownership beyond the base permission check (Phase 2/8). |
| **CSRF** | Laravel's default CSRF middleware on all stateful routes. The only exclusion is `api/track/*` (engagement/ad/video tracking) — `fetch`/`sendBeacon` can't attach a CSRF header; those endpoints are instead defended by SameSite=Lax cookies (blocks cross-site delivery), per-session rate limiting, and strict payload validation (Phase 5/7). |
| **XSS** | Blade auto-escapes everywhere by default. The one place raw HTML is intentionally rendered is Post/Page `content` (`{!! !!}`) — authored through Filament's RichEditor, but **sanitized server-side on every save** via `mews/purifier` (`App\Models\Concerns\SanitizesContentHtml`), regardless of the RichEditor's own client-side restrictions or which role (including the lower-trust "author" role) saved it. `<script>`, inline event handlers, `javascript:` URLs, and arbitrary iframe sources are stripped; a curated tag/attribute allow-list plus a YouTube/Vimeo-only iframe allow-list covers everything the editor supports (§5). Comment bodies are rendered through `{{ }}` (always escaped) — never raw HTML. |
| **SQL injection** | Exclusively Eloquent/query builder parameter binding throughout; no raw string interpolation into SQL anywhere in the codebase. |
| **Rate limiting** | Login (Breeze default), tracking ingestion endpoints (120/min/session, §5/§7 config), comment submission (10/min/IP, §31). |
| **File uploads** | MIME allow-list + max size enforced in `MediaResource` (Phase 2); video/image metadata is probed server-side (getID3/`getimagesize`), never trusted from client-reported values. |
| **Tracking data integrity** | `EngagementEventBatchRequest`/`AdEventBatchRequest` validate every field (known event types only, referential integrity between post/video/ad IDs, percentage/second range checks); `EngagementTrackingService` clamps watched-seconds to the video's actual duration (+5% tolerance) and computes `is_completed` itself from persisted numbers — a client can never assert completion directly (Phase 5/6, tested explicitly). |
| **Security headers** | `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, a restrictive `Permissions-Policy` — applied globally (public site *and* admin panel; Filament runs its own middleware pipeline, so this uses the true global stack, not the `web` group). |
| **Self-deletion guard** | `UserResource` hides/blocks deleting your own account, on both the row action and bulk action. |
| **Open redirects** | The `redirects` table isn't a generic "redirect to whatever's in the query string" endpoint — every destination is a pre-configured value an authenticated, `admin`/`super_admin`-only user typed into Filament in advance (§30 permissions). There's no request-time input that controls where a redirect sends a visitor, so the classic open-redirect pattern (an attacker crafts a link with a malicious `?redirect_to=` value) doesn't apply here. `Redirect::wouldCreateLoop()` still validates every save against redirect loops (chained through other active redirects, not just a same-value check), and `RedirectResource`'s form rejects sources that collide with reserved app prefixes (`/admin`, `/api`, `/login`, ...). |
| **Menu/redirect/settings authorization** | `MenuResource`, `RedirectResource`, and the `ManageSettings` page all require Shield-generated permissions (`*_menu`, `*_redirect`, `page_ManageSettings`) that only `admin`/`super_admin` hold by default — `RoleSeeder` never grants them to `editor`/`author`, the same pattern already used for `UserResource`/`ActivityLogResource`. |
| **Settings can't inject script** | Every settings field is plain text/select/toggle/media-picker rendered through Blade's auto-escaping `{{ }}` on the public site (never `{!! !!}`) — there's no field that accepts or renders raw HTML/JS, so a compromised admin account can deface copy but can't inject a script tag through Settings. Image fields (logo/favicon/default social image) go through the same validated Media Library upload pipeline as every other image in the CMS (§13), never a bare file path. |
| **Menu custom-URL XSS** | A Custom-type menu item's URL is rendered as a raw `href` with no further sanitization, so `javascript:`/`data:`/protocol-relative (`//evil.example.com`) URIs could otherwise produce a stored-XSS link on the public site if an admin account were ever compromised. `MenuItem::isSafeUrl()` rejects anything other than an internal path (`/...`) or a real `http(s)://` URL, enforced both at save time (the Filament form rule) and at render time (`isBroken()` — an unsafe URL is treated the same as a broken reference and simply never rendered), found and fixed in the production-readiness audit. |
| **Environment-only AdSense credentials** | The rendered `<ins data-ad-client>` on every ad slot always reads `config('services.adsense.client_id')` (`ADSENSE_CLIENT_ID`) — never a per-`AdSlot`-row value. A site only ever has one AdSense publisher ID, so `AdSlotResource`'s form no longer exposes it as an admin-entered field at all (found and fixed in the production-readiness audit; the column is kept for backward compatibility and auto-filled from the same env value on create). |

## Configuration-only — your responsibility at deploy time

These are correct for local development but **must** be changed for a production deployment. None of them are code bugs; they're environment-specific by nature (see `docs/DEPLOYMENT.md`).

- `APP_DEBUG=false` and `APP_ENV=production` — a `true` debug flag leaks stack traces, `.env` values, and query bindings to any visitor who triggers an error.
- `SESSION_SECURE_COOKIE=true` — only send session cookies over HTTPS. Left `false` locally because local dev typically runs over plain HTTP.
- `APP_KEY` — must be a real, unique key generated with `php artisan key:generate` on first deploy and never committed or shared across environments.
- Database/Redis credentials — set real, non-default passwords; don't expose PostgreSQL/Redis ports publicly.
- `ADSENSE_CLIENT_ID` and any object-storage (S3/R2) credentials — real secrets belong in the server's `.env`, never in the repo.
- Rotate the seeded super admin password (`admin@cmcphp.test` / `password`) immediately in any shared or production environment.

## Known upstream item to track

`composer audit` currently flags the installed Laravel 11.55.1 framework against advisories `GHSA-crmm-hgp2-wgrp` (temporary signed URL path confusion) and `GHSA-5vg9-5847-vvmq` (CRLF injection in the default email validation rule). These are upstream framework issues, not something introduced by this codebase. Before production deployment: run `composer audit` for the current state, and check whether a patched 11.x release exists (the published fix versions reference 12.x/13.x, so confirm whether the 11.x line has received an equivalent backport by the time you deploy).

## Explicitly out of scope here

- **Content-Security-Policy**: intentionally not set globally. A strict CSP has to be tuned against the actual third-party script surface (Google AdSense, YouTube/Vimeo embeds) or it silently breaks them — this is a per-deployment tuning task, not a safe one-size-fits-all default.
- **Two-factor authentication** for admin accounts: not implemented. Consider `laravel/fortify`'s 2FA support as a follow-up if the admin panel will be internet-facing with sensitive content.
- **Full penetration test / dependency scan automation**: this document is a self-audit against the architecture's own checklist, not a substitute for an external security review before a production launch handling real user data.
