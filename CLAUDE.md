# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Craft CMS 5.x plugin called "Insights" - a DSGVO (GDPR) compliant analytics plugin that tracks pageviews, referrers, campaigns, and devices without storing PII or IP addresses.

- **Namespace:** `samuelreichor\insights`
- **Plugin Handle:** `insights`
- **PHP Version:** 8.2+
- **Craft CMS Version:** 5.0.0+

## Common Commands

```bash
# Code style checking
composer run check-cs

# Auto-fix code style issues
composer run fix-cs

# Static analysis (PHPStan level 4)
composer run phpstan
```

## Architecture

### Service Layer

The plugin registers 6 core services as components in `src/Insights.php`:

| Service | Purpose |
|---------|---------|
| `TrackingService` | Main processor for all tracking events (pageview, engagement, leave, custom events, outbound, search, scroll depth) |
| `StatsService` | Analytics queries and aggregation for dashboard |
| `VisitorService` | GDPR-compliant visitor hash generation (daily rotation) |
| `GeoIpService` | IP-to-country lookup (IP discarded after lookup) |
| `CleanupService` | Data retention enforcement |
| `LoggerService` | Structured debug logging with performance timers |

Access services via: `Insights::getInstance()->tracking->processPageview(...)`

### Database Schema

11 tables store **aggregated data only** (no raw events):

**Core tables (Lite + Pro):**
- `insights_pageviews` - Page traffic with views, uniqueVisitors, bounces, totalTimeOnPage
- `insights_referrers` - Traffic sources classified by type (direct, search, social, referral)
- `insights_campaigns` - UTM parameter tracking
- `insights_devices` - Browser/OS/device type breakdown
- `insights_countries` - Geo-location (country codes only)
- `insights_realtime` - Active visitors (TTL enforced via CleanupService)
- `insights_sessions` - Session tracking with entry/exit pages and page counts

**Pro tables:**
- `insights_events` - Custom event tracking (pv, en, lv counters)
- `insights_outbound` - Outbound link clicks with target URL tracking
- `insights_searches` - Site search tracking with query terms and result counts
- `insights_scroll_depth` - Scroll depth milestones (25%, 50%, 75%, 100%)

Table names are centralized in `src/Constants.php`.

### Tracking Pipeline

```
Frontend (insights.js) → POST /actions/insights/track
                              ↓
                      TrackController filters:
                      - Bot detection
                      - DNT header
                      - Excluded IPs/paths
                      - Admin/logged-in users
                              ↓
              ProcessTrackingJob (queue) OR sync
                              ↓
                   TrackingService processes:
                   - Visitor hash generation
                   - Entry ID resolution
                   - UPSERT aggregations
```

Event types: `pv` (pageview), `en` (engagement), `lv` (leave), `ev` (custom event), `ob` (outbound), `sr` (search), `sd` (scroll depth)

### Key Patterns

- **UPSERT Pattern:** Uses `Db::upsert()` for efficient aggregation
- **Daily Visitor Hash:** SHA256(dailySalt + domain + IP + userAgent) - IP is discarded after hash generation
- **Deferred Initialization:** Auto-cleanup registered via `Craft::$app->onInit()`
- **Event-Driven:** URL routing, permissions, widgets registered via Craft events in `attachEventHandlers()`

### Controllers

- `TrackController` - Public tracking endpoint (unauthenticated)
- `DashboardController` - CP dashboard views and AJAX endpoints
- `ExportController` - CSV/JSON export

### Enums

Located in `src/enums/`:
- `EventType` - pv (pageview), en (engagement), lv (leave), ev (event), ob (outbound), sr (search), sd (scroll depth)
- `DateRange` - today, 7d, 30d, 90d, 12m (with `getStartDate()`, `getEndDate()`, `getDateRange()` methods)
- `DeviceType` - desktop, mobile, tablet
- `ReferrerType` - direct, search, social, referral
- `ScreenCategory` - s (<768px), m (768-1199px), l (≥1200px)
- `ScrollDepthMilestone` - Percent25, Percent50, Percent75, Percent100
- `LogLevel` - Default, Debug
- `Permission` - Centralized permission management for dashboard and page access

### Adding Features

- **New tracking events:** Add case to `EventType` + handler in `TrackingService`
- **New analytics:** Add method to `StatsService`
- **New permissions:** Add to `UserPermissions` event in `Insights::attachEventHandlers()`
- **Database changes:** Create migration + update `Constants.php` + create ActiveRecord in `src/records/`

## Code Quality

- **ECS:** Craft CMS 4 ruleset (`ecs.php`)
- **PHPStan:** Level 4 (`phpstan.neon`)
