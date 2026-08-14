# CMCPHP — Custom CMS + Content Platform
## Architecture Document (v1)

Stack: **Laravel 11 + Filament 3 + PostgreSQL + Redis + S3-compatible storage**, deployed on Nginx/PHP-FPM with queue workers and the scheduler. No WordPress, no PHP CMS packages, no Supabase.

---

## 1. Complete Architecture

```
                         ┌─────────────────────────────┐
                         │        Browser / SEO bots     │
                         └───────────────┬───────────────┘
                                          │ HTTPS
                     ┌────────────────────┴────────────────────┐
                     │                Nginx                     │
                     │  static assets · gzip/brotli · TLS        │
                     └───────┬───────────────────────┬──────────┘
                              │                        │
                  /admin/*    │                        │  public routes
                              ▼                        ▼
                     ┌─────────────────┐     ┌───────────────────────┐
                     │  Filament Admin  │     │   Public Web (Blade    │
                     │  (Livewire SPA-  │     │   + sprinkles of       │
                     │   like panel)    │     │   Livewire/Alpine)     │
                     └────────┬─────────┘     └───────────┬────────────┘
                              │                             │
                              └───────────┬─────────────────┘
                                          ▼
                              ┌───────────────────────┐
                              │   Laravel Application   │
                              │  Controllers/Livewire →  │
                              │  Form Requests → Actions │
                              │  → Services → Models     │
                              │  Policies · Events ·      │
                              │  Jobs · DTOs · Enums      │
                              └───────┬──────────┬────────┘
                                       │          │
                     ┌─────────────────┘          └─────────────────┐
                     ▼                                              ▼
          ┌────────────────────┐                         ┌───────────────────┐
          │     PostgreSQL       │                         │       Redis        │
          │  primary data store  │                         │ cache · sessions ·  │
          │  (normalized schema, │                         │ queues · rate limit │
          │   full-text search)  │                         └─────────┬──────────┘
          └──────────┬───────────┘                                   │
                     │                                                ▼
                     │                                    ┌────────────────────┐
                     │                                    │   Queue Workers      │
                     │                                    │ (horizon/queue:work) │
                     │                                    │ analytics agg, video │
                     │                                    │ metadata, images,    │
                     │                                    │ sitemap, mail        │
                     │                                    └─────────┬────────────┘
                     │                                                │
                     ▼                                                ▼
          ┌────────────────────┐                         ┌────────────────────┐
          │  Scheduler (cron)   │                         │  S3-Compatible Store │
          │ publish scheduled   │                         │ (R2 / AWS S3 / MinIO)│
          │ posts, cleanup,     │                         │ media/video originals │
          │ rollups, reports    │                         └────────────────────┘
          └────────────────────┘
```

**Design principles**
- The public site never loads Filament/Livewire admin JS — separate asset bundles (Vite entrypoints `app.css/app.js` for public, `admin` styling scoped to Filament panel).
- Write path for high-volume tracking events is decoupled from the read path: browser → thin ingestion endpoint → Redis-buffered → queued job → batched insert into Postgres. No per-second writes.
- Every "trust boundary" (video completion, article completion, ad render) is re-validated server-side before being persisted as authoritative.
- Multi-tenancy is not required (single site), but the schema keeps `settings` as key/value so behavior (thresholds, ad limits, delays) is runtime-configurable, never hard-coded.

---

## 2. Database ERD / Schema (PostgreSQL)

### Core content

```
users
  id (uuid, pk)
  name, email (unique), password
  role_id (fk roles, nullable if using spatie/permission instead)
  avatar_media_id (fk media, nullable)
  timezone, locale
  timestamps, soft deletes

roles            (Spatie permission or Filament Shield backed)
permissions
model_has_roles / model_has_permissions / role_has_permissions

media
  id (uuid, pk)
  disk (string)                  -- s3 / r2
  path, url
  type (enum: image, video, document)
  mime_type, size_bytes
  title, alt_text, caption, description
  -- video-only:
  duration_seconds (nullable, int)
  thumbnail_media_id (nullable, fk self)
  width, height (nullable)
  uploaded_by (fk users)
  timestamps, soft deletes
  INDEX(type), INDEX(created_at)

categories
  id (uuid, pk)
  parent_id (nullable, fk self)      -- hierarchical
  name, slug (unique)
  description
  image_media_id (nullable, fk media)
  seo_title, seo_description
  position (int)
  timestamps
  INDEX(parent_id), INDEX(slug)

tags
  id (uuid, pk)
  name, slug (unique)
  description
  seo_title, seo_description
  timestamps
  INDEX(slug)

posts
  id (uuid, pk)
  title, slug (unique)
  excerpt, content (jsonb — structured rich-text/TipTap doc; content_html generated/cached)
  category_id (fk categories, nullable)
  author_id (fk users)
  featured_image_media_id (fk media, nullable)
  status (enum: draft, scheduled, published, unpublished)   -- see PostStatus enum
  published_at (timestamptz, nullable)
  next_article_id (nullable, fk posts)          -- manual override, sec. 12
  next_article_mode (enum: manual, same_category, auto)
  reading_time_minutes (int, computed)
  -- completion overrides (nullable => fall back to global settings)
  completion_reading_threshold (smallint, nullable)
  completion_required_videos (smallint, nullable)
  timestamps, soft deletes
  INDEX(slug), INDEX(status), INDEX(published_at), INDEX(category_id)
  FULLTEXT/GIN(to_tsvector(title || excerpt || content_text))

post_tags (pivot: post_id, tag_id, PK(post_id,tag_id))

post_videos
  id (uuid, pk)
  post_id (fk posts, cascade)
  media_id (fk media)
  position (smallint: 1..3)
  duration_seconds (int, nullable -> filled by job on upload)
  is_required (bool, default true)
  completion_threshold (smallint, default 90)     -- % , overridable per video
  status (enum: active, inactive)
  timestamps
  UNIQUE(post_id, position)
  INDEX(post_id)

pages
  id (uuid, pk)
  title, slug (unique)
  content (jsonb)
  featured_image_media_id (fk media, nullable)
  status (enum: draft, published)
  published_at
  seo_title, seo_description, canonical_url
  timestamps, soft deletes
  INDEX(slug), INDEX(status)

seo_metadata                        -- polymorphic, one row per seo-able entity
  id (uuid, pk)
  seoable_type, seoable_id (morph)
  seo_title, meta_description, canonical_url
  robots_index (bool, default true)
  robots_follow (bool, default true)
  og_title, og_description, og_image_media_id
  twitter_card (enum)
  schema_type (enum: article, website, organization, none)
  schema_json (jsonb, nullable override)
  timestamps
  UNIQUE(seoable_type, seoable_id)

redirects
  id (uuid, pk)
  from_path (unique), to_path
  type (smallint: 301|302)
  hits_count (int, default 0)
  is_active (bool)
  timestamps
  INDEX(from_path)

menus
  id (uuid, pk)
  key (enum/string: header, footer, custom-*), name
  timestamps

menu_items
  id (uuid, pk)
  menu_id (fk menus)
  parent_id (nullable, fk self)
  linkable_type, linkable_id (nullable morph: post/page/category)
  custom_url (nullable)
  label
  position (int)
  is_enabled (bool)
  timestamps
  INDEX(menu_id, parent_id)
```

### Tracking / analytics (write-optimized, append-only, indexed for aggregation)

```
article_sessions                         -- one row per (article, anonymous/auth session)
  id (uuid, pk)
  post_id (fk posts)
  session_id (string, 64)                -- anon session token (httponly cookie) or user-derived
  user_id (nullable, fk users)
  progress_percent (smallint, default 0)
  time_spent_seconds (int, default 0)
  bottom_reached (bool, default false)
  is_completed (bool, default false)
  started_at, last_activity_at, completed_at (nullable)
  UNIQUE(post_id, session_id)
  INDEX(post_id), INDEX(session_id), INDEX(created_at)

engagement_events                        -- raw milestone event log (short retention, rolled up then pruned)
  id (bigint identity, pk)               -- high volume: bigint, not uuid
  event_type (enum: article_open, article_25, article_50, article_75, article_90,
                     article_bottom, article_complete,
                     video_play, video_pause, video_resume, video_progress,
                     video_25, video_50, video_75, video_90, video_complete, video_end)
  post_id (fk posts)
  post_video_id (nullable, fk post_videos)
  session_id (string)
  user_id (nullable, fk users)
  event_uuid (uuid, unique)              -- client-generated idempotency key, dedupe
  payload (jsonb)                        -- watched_seconds, percent, etc. (validated)
  created_at (timestamptz)
  INDEX(event_type), INDEX(post_id), INDEX(session_id), INDEX(created_at)

video_progress                           -- one row per (post_video, session) — upserted, not per-second
  id (uuid, pk)
  post_id (fk posts)
  post_video_id (fk post_videos)
  session_id (string)
  user_id (nullable, fk users)
  watched_seconds (numeric, >=0, <= duration + tolerance)
  max_percent_reached (smallint, 0-100)
  play_count, pause_count (smallint)
  is_completed (bool, default false)
  started_at, last_watched_at, completed_at (nullable)
  UNIQUE(post_video_id, session_id)
  INDEX(post_id), INDEX(post_video_id), INDEX(session_id)

article_completions                       -- authoritative, server-validated final record
  id (uuid, pk)
  post_id (fk posts)
  session_id (string)
  user_id (nullable, fk users)
  reading_progress_percent (smallint)
  videos_completed_count (smallint)
  videos_required_count (smallint)
  completed_at (timestamptz)
  next_post_id (nullable, fk posts)       -- what was opened next, for funnel analysis
  UNIQUE(post_id, session_id)
  INDEX(post_id), INDEX(completed_at)

ad_slots                                  -- reusable placement templates (sec. 22)
  id (uuid, pk)
  name (unique), ad_client, ad_slot_code
  format (enum: auto, horizontal, vertical, rectangle, fluid)
  is_responsive (bool)
  status (enum: active, inactive)
  timestamps

ad_placements                             -- per-article or global-rule placements (sec. 23-24)
  id (uuid, pk)
  post_id (nullable, fk posts)            -- null = governed by automatic rules only
  ad_slot_id (fk ad_slots)
  placement_type (enum: top, after_paragraph, before_video, after_video, middle,
                        before_conclusion, bottom)
  paragraph_index (smallint, nullable)
  video_position (smallint, nullable)     -- 1..3, for before/after_video
  position_order (smallint)
  status (enum: active, inactive)
  timestamps
  INDEX(post_id)

ad_events
  id (bigint identity, pk)
  ad_slot_id (fk ad_slots)
  ad_placement_id (nullable, fk ad_placements)
  post_id (nullable, fk posts)
  session_id (string)
  event_type (enum: ad_requested, ad_loaded, ad_rendered, ad_viewable)
  created_at (timestamptz)
  INDEX(ad_slot_id), INDEX(post_id), INDEX(created_at)

comments
  id (uuid, pk)
  post_id (fk posts)
  user_id (nullable, fk users)
  author_name, author_email (nullable, for guest comments if enabled)
  parent_id (nullable, fk self)
  body
  status (enum: pending, approved, spam, rejected)
  timestamps
  INDEX(post_id), INDEX(status)

settings                                  -- key/value, typed, cached in Redis
  id (uuid, pk)
  group (string: general, reading, seo, social, analytics, ads, content, security, performance)
  key (unique per group)
  value (jsonb)
  timestamps
  UNIQUE(group, key)

activity_logs                             -- spatie/activitylog-style, or custom
  id (bigint identity, pk)
  user_id (nullable, fk users)
  action (string)                          -- e.g. post.published
  subject_type, subject_id (morph)
  properties (jsonb)
  created_at
  INDEX(subject_type, subject_id), INDEX(created_at)

daily_rollups (aggregation table, populated by scheduled jobs — keeps dashboards cheap)
  id (bigint identity, pk)
  date (date)
  post_id (nullable, fk posts)             -- null row = site-wide totals
  pageviews, sessions_count, visitors_count
  avg_progress_percent, avg_reading_seconds
  completions_count
  video_plays, video_completions           -- jsonb per position or 3 explicit columns
  ad_requests, ad_renders
  UNIQUE(date, post_id)
  INDEX(date)
```

Primary keys: **UUIDv7** for entity tables (posts, pages, media, users, ad_slots…) for URL-safety and merge-friendliness; **bigint identity** for pure event-log tables (`engagement_events`, `ad_events`, `activity_logs`) where volume and insert throughput dominate over global uniqueness needs.

---

## 3. Laravel Folder Structure

```
app/
  Actions/
    Posts/{CreatePost,PublishPost,DuplicatePost,SchedulePost}.php
    Videos/{AttachVideoToPost,DetectVideoDuration,ReorderVideos}.php
    Tracking/{RecordEngagementEvent,UpsertVideoProgress,EvaluateArticleCompletion,ResolveNextArticle}.php
    Ads/{ResolveAdPlacementsForPost,RecordAdEvent}.php
    Seo/{BuildSeoPayload,GenerateJsonLd}.php
  Console/Commands/
    PublishScheduledPosts.php
    AggregateDailyAnalytics.php
    PruneStaleTrackingEvents.php
    GenerateSitemap.php
  DTOs/
    EngagementEventData.php, VideoProgressData.php, SeoPayload.php, AdPlacementResolution.php
  Enums/
    PostStatus.php, VideoPosition.php, EventType.php, AdEventType.php,
    AdPlacementType.php, NextArticleMode.php, CommentStatus.php
  Events/
    PostPublished.php, ArticleCompleted.php, VideoCompleted.php
  Filament/
    Resources/  (see section 4)
    Widgets/
    Pages/
  Http/
    Controllers/
      Public/{HomeController,PostController,CategoryController,TagController,
               PageController,SearchController,SitemapController,RobotsController}.php
      Api/Tracking/{EngagementEventController,VideoProgressController,AdEventController}.php
    Livewire/
      Public/{ArticleReader,VideoPlayer,CompletionBanner,CommentThread,SearchBox}.php
    Middleware/
      EnsureAnonymousSession.php, TrackPageview.php
    Requests/
      Admin/{PostRequest,PageRequest,...}.php
      Tracking/{EngagementEventRequest,VideoProgressRequest,AdEventRequest}.php   -- strict validation, sec. 46
    Resources/  (JSON resources for tracking responses, if any)
  Jobs/
    ProcessEngagementEventBatch.php
    RecalculateArticleCompletion.php
    DetectVideoDurationJob.php
    GenerateImageVariants.php
    AggregateArticleAnalytics.php
    GenerateSitemapJob.php
  Listeners/
    LogActivity.php, InvalidatePostCache.php
  Models/
    Post.php, Page.php, Category.php, Tag.php, Media.php, PostVideo.php,
    ArticleSession.php, EngagementEvent.php, VideoProgress.php, ArticleCompletion.php,
    AdSlot.php, AdPlacement.php, AdEvent.php, Comment.php, Setting.php,
    Menu.php, MenuItem.php, Redirect.php, SeoMetadata.php, ActivityLog.php, User.php
  Policies/
    PostPolicy.php, PagePolicy.php, MediaPolicy.php, UserPolicy.php, SettingPolicy.php, ...
  Services/
    SettingsService.php        -- cached typed accessor over `settings` table
    SeoService.php              -- resolves effective SEO for any seoable + JSON-LD builder
    ArticleCompletionService.php
    NextArticleResolver.php
    AdPlacementResolver.php     -- merges manual + automatic rules, enforces max/spacing
    AnalyticsAggregationService.php
    SitemapService.php
    MediaStorageService.php     -- disk-agnostic (R2/S3) wrapper
  Support/
    AnonymousSession.php        -- cookie-based session id issuance/rotation
resources/
  css/{app.css, admin.css}
  js/{app.js, admin.js, tracking/{video-tracker.js, reading-tracker.js, event-buffer.js}}
  views/
    layouts/{public.blade.php, admin uses Filament panel}
    public/{home,posts/show,posts/index,category,tag,page,search,404}.blade.php
    components/... (blade components: seo-head, breadcrumbs, ad-slot, related-articles)
routes/
  web.php        -- public site
  admin.php      -- (Filament auto-registers panel; kept minimal)
  api.php        -- tracking ingestion endpoints (rate-limited, CSRF-exempt but signed)
database/
  migrations/
  factories/
  seeders/
tests/
  Feature/{Posts,Pages,Tracking,Completion,NextArticle,Ads,Seo,Security}
  Unit/{Services,Actions,DTOs}
```

---

## 4. Filament Resource Structure

```
app/Filament/Resources/
  PostResource.php
    Pages: ListPosts, CreatePost, EditPost, ViewPostAnalytics (custom page)
    RelationManagers: none (videos/ads are embedded panels, not separate CRUD)
    Form tabs: Content | SEO | Videos | Advertisements | Completion & Next Article
  PageResource.php
  CategoryResource.php        (tree/nested via parent select)
  TagResource.php
  MediaResource.php           (custom upload UX, video duration probe on upload)
  MenuResource.php             (builder page with drag/drop items, not table-only)
  RedirectResource.php
  CommentResource.php
  AdSlotResource.php
  UserResource.php
  RoleResource.php             (Filament Shield)
  ActivityLogResource.php      (read-only)

app/Filament/Pages/
  Dashboard.php                 (custom widgets grid, section 3)
  SeoSettingsPage.php
  AdvertisementSettingsPage.php  (max ads, spacing, exclusions)
  GeneralSettingsPage.php / ReadingSettingsPage.php / SecuritySettingsPage.php / ...
  SystemPage.php                 (queue/cache/storage health)

app/Filament/Widgets/
  StatsOverview.php              (visitors, pageviews, sessions, published/draft, completion rate, avg reading time)
  VideoCompletionWidget.php
  AdPerformanceWidget.php
  TopArticlesTable.php
  TopCategoriesChart.php
  RecentActivityTable.php

app/Filament/Resources/PostResource/RelationManagers/  -- if needed for comments per post
```

Per-article **Analytics** is a Filament custom page (`ViewPostAnalytics`) reading from `daily_rollups` + live aggregates, matching section 40's layout (views, unique sessions, avg progress, completion, per-video stats, ad stats).

Permissions are enforced through Filament's `shield()`/Policy integration — every Resource checks `PostPolicy`, `PagePolicy`, etc., mapped to the four roles (Super Admin, Admin, Editor, Author) with granular abilities (view/create/edit/delete/publish/manage-seo/manage-ads/view-analytics/manage-users/manage-settings).

---

## 5. Tracking Architecture

**Client side** (`resources/js/tracking/`):
- `AnonymousSession`: reads/creates a signed, httponly-adjacent session cookie (issued server-side on first request via `EnsureAnonymousSession` middleware) — JS never generates the identity itself, only reads a public session token to attach to payloads.
- `event-buffer.js`: a small queue that batches milestone events client-side, persists unsent events to `localStorage`, and flushes on: milestone reached, `visibilitychange`, `beforeunload` (via `sendBeacon`), and a light periodic flush (~5s) — never per-second/per-frame.
- `reading-tracker.js`: IntersectionObserver + scroll-percent computation → emits `article_25/50/75/90/bottom/complete` once each (deduped client-side by a `Set` of fired milestones), each event tagged with a UUID for idempotency.
- `video-tracker.js`: listens to `timeupdate`, computes percent = currentTime/duration, fires `video_25/50/75/90/complete` once per milestone per video element, plus `play/pause/resume/end`.

**Ingestion** (`POST /api/track/events`, `/api/track/video-progress`, `/api/track/ad-events`):
- Rate-limited per session/IP, validated by dedicated Form Requests (section 46: reject negative seconds, watched > duration + tolerance, out-of-range percentages, unknown post/video ids).
- Each event carries `event_uuid`; a unique index on `engagement_events.event_uuid` makes replays/duplicate flushes no-ops (insert-or-ignore).
- Controller pushes validated batches onto a Redis-backed queue (`ProcessEngagementEventBatch` job) rather than writing synchronously — keeps the request fast and decouples spikes from Postgres.

**Processing** (queued jobs):
- `ProcessEngagementEventBatch`: inserts raw rows into `engagement_events`, then upserts the relevant summary row(s) — `article_sessions` (progress/time/bottom) or `video_progress` (watched_seconds/percent/play&pause counts), each idempotent via `UNIQUE` constraints + `updated_at`-guarded upserts.
- `RecalculateArticleCompletion`: dispatched whenever a video reaches `is_completed=true` or article progress crosses the configured threshold; re-evaluates **server-side** using `ArticleCompletionService` (reads current `video_progress` + `article_sessions` rows — never trusts a client-sent `completed=true`), writes `article_completions` once eligible, fires `ArticleCompleted` event.
- `AggregateArticleAnalytics` (scheduled, hourly/daily): rolls raw events into `daily_rollups` for cheap dashboard reads, then a separate `PruneStaleTrackingEvents` scheduled command trims `engagement_events` beyond the retention window (raw detail not needed once rolled up).

**Reliability** (section 45): local buffering + retry-with-backoff in `event-buffer.js`; idempotency via `event_uuid`; `sendBeacon` on unload so in-flight progress isn't lost; sessions resume naturally on return because state lives server-side keyed by `session_id`, not client memory.

---

## 6. Ad Architecture

- **Ad Slots** (`ad_slots`) are the reusable AdSense unit definitions (client id, slot id, format, responsive) — configured once in admin, never hard-coded in Blade/TipTap content.
- **Ad Placements** (`ad_placements`) bind a slot to a *position* — either explicitly per-article (manual, section 23) or left to the **automatic placement engine** (section 24) when no per-article placement exists for a given type.
- `AdPlacementResolver` service, given a rendered post's paragraph count and video positions, produces the final ordered list of ad slots to render:
  1. Start from manual placements for the post (if any), respecting `position_order`.
  2. If automatic placement is enabled and under the per-article max, fill remaining eligible gaps according to configured minimum-paragraph-spacing, skipping excluded categories/articles/pages and short articles below a configured paragraph count.
  3. Hard cap at `settings.ads.max_ads_per_article`.
- Rendering: a single Blade component `<x-ad-slot :slot="$slot" />` emits the AdSense `<ins>` tag from the resolved slot's client/slot IDs — no per-article hard-coded snippets, satisfying section 25/22. The component is visually bordered/labeled to stay clearly distinguishable from content (policy safeguard).
- **Ad analytics** (`ad_events`) are fired client-side (`ad_requested` on mount, `ad_loaded`/`ad_rendered` on AdSense callback, `ad_viewable` via IntersectionObserver where measurable) through the same batched ingestion pipeline as engagement events — explicitly labeled in the admin UI as **"Internal Ad Analytics"**, never conflated with official AdSense reporting (section 26/27). A future `AdSenseReportSync` job/service is stubbed to pull official metrics server-side via the AdSense Management API using credentials stored only in server env/config — never exposed to the frontend.

---

## 7. Security Architecture

- **AuthN**: Laravel's built-in auth (session-based for admin, since Filament runs server-rendered Livewire) + Fortify/Breeze scaffolding for login, password reset, optional 2FA.
- **AuthZ**: Laravel Policies per model + Filament Shield roles/permissions (Super Admin, Admin, Editor, Author) — every Filament Resource `can()` check delegates to a Policy method, so authorization is enforced identically whether hit via UI or a future API.
- **CSRF**: default Laravel CSRF middleware on all stateful routes; tracking API endpoints use a lightweight signed-token/rate-limit scheme instead of CSRF (they're called cross-navigation via `sendBeacon`), validated by origin + session cookie + per-IP/session rate limiting.
- **Input validation**: dedicated Form Request classes for every write path, including strict tracking payload validation (section 46) — range checks on percentages/seconds, existence checks on post/video ids, rejecting client-asserted `completed=true`.
- **XSS**: Blade auto-escaping everywhere; rich-text content stored as structured JSON (TipTap doc) and rendered through a whitelist-based renderer (not raw `{!! !!}` of user HTML); any legacy HTML import sanitized with a strict allow-list (e.g. `HTMLPurifier`) before persisting.
- **SQLi**: exclusively Eloquent/query builder parameter binding; no raw string interpolation into SQL.
- **Rate limiting**: Laravel's rate limiter (Redis-backed) on auth endpoints, tracking ingestion, search, and comment submission.
- **Sessions/cookies**: `secure`, `httponly`, `samesite=lax` cookies; anonymous session id is a signed random token, rotated on privilege escalation (login), and linked to the user record at that point without retroactively exposing PII.
- **File uploads**: MIME + extension allow-list per media type, max size enforced both client and server side, video/image processing done in isolated queued jobs (never trusts client-reported duration/dimensions — probed server-side via `ffprobe`/image library).
- **Server-side completion validation**: as above — `article_completed` is only ever set by `ArticleCompletionService` running server-side against persisted `video_progress`/`article_sessions` rows.
- **Secrets**: AdSense/API credentials, S3 keys, DB credentials all via `.env`/server config, never shipped to the frontend bundle.

---

## 8. Implementation Roadmap

| Phase | Scope |
|---|---|
| 1 | Laravel + PostgreSQL + Filament install, auth scaffolding, base public layout, env config |
| 2 | CMS core: Posts, Pages, Categories, Tags, Media (+ S3-compatible storage) |
| 3 | Menus, SEO system, Redirects, Settings |
| 4 | Three-video article system: post_videos, duration detection, TipTap video embedding |
| 5 | Reading + video tracking pipeline (client trackers, ingestion API, batching jobs) |
| 6 | Article completion engine + automatic next-article |
| 7 | Advertisement manager: slots, placements, automatic rules, policy safeguards |
| 8 | Analytics dashboard: site-wide + per-article, ad analytics |
| 9 | Users/Roles/Permissions (Shield), Comments, Activity log |
| 10 | Performance: Redis caching, queue tuning, query/index audit |
| 11 | Security audit + automated test suite (section 51) |
| 12 | Production deployment: Nginx/PHP-FPM/Postgres/Redis/workers/scheduler, SSL, backups |

This document is the reference for all subsequent phases; settings referenced throughout (`completion thresholds`, `auto-next delay`, `max ads per article`, `spacing`, etc.) are implemented as rows in `settings`, never hard-coded, per section 54.

---

## Implementation notes (updated as phases land)

**Phase 2 (CMS core):**
- `posts.content` / `pages.content` are stored as sanitized **HTML** (produced by Filament's RichEditor, which is TipTap-based) rather than a raw TipTap JSON document. The three-video-in-content system (Phase 4) will use lightweight position markers tied to `post_videos.position` rather than a custom JSON schema — simpler and equally robust for a single-author-tool editing model.
- `seo_metadata` (originally scoped to Phase 3) was pulled forward into Phase 2 because Posts/Pages need a working SEO panel to be usable at all. Phase 3 builds the *system* around it: sitemap/robots generation, redirects, and a shared `SeoService` for resolving effective metadata with defaults.
- Filament Shield's `shield:generate` writes Policy **files** to disk (committed to git) but Permission **rows** only exist in whatever database you ran it against. `database/seeders/PermissionSeeder.php` regenerates those rows (`--option=permissions`, so it never touches the hand-tuned Policy classes) — it's part of `db:seed`, so permissions exist reproducibly in every environment (fresh dev DB, CI, production). Re-run `db:seed --class=PermissionSeeder` (or full `db:seed`) after any phase adds new Filament resources.
- PostgreSQL note: a chained `$table->uuid('id')->primary()` compiles to a *trailing* `ALTER TABLE ADD PRIMARY KEY`, which runs after `foreign()` commands — this breaks self-referencing foreign keys (`media.thumbnail_media_id`, `categories.parent_id`, `posts.next_article_id`) with "no unique constraint matching given keys". Fix used throughout: declare the column with `$table->uuid('id')` (no chained `->primary()`), then call `$table->primary('id')` explicitly *before* the self-referencing `foreign()` call in the same migration.
