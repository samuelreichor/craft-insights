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
| `TrackingService` | Main processor for pageview, engagement, and leave events |
| `StatsService` | Analytics queries and aggregation for dashboard |
| `VisitorService` | GDPR-compliant visitor hash generation (daily rotation) |
| `GeoIpService` | IP-to-country lookup (IP discarded after lookup) |
| `CleanupService` | Data retention enforcement |
| `LoggerService` | Structured debug logging with performance timers |

Access services via: `Insights::getInstance()->tracking->processPageview(...)`

### Database Schema

6 tables store **aggregated data only** (no raw events):
- `insights_pageviews` - Page traffic with views, uniqueVisitors, bounces, totalTimeOnPage
- `insights_referrers` - Traffic sources classified by type (direct, search, social, referral)
- `insights_campaigns` - UTM parameter tracking
- `insights_devices` - Browser/OS/device type breakdown
- `insights_countries` - Geo-location (country codes only)
- `insights_realtime` - Active visitors with 5-min TTL

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

Event types: `pv` (pageview), `en` (engagement), `lv` (leave)

### Key Patterns

- **UPSERT Pattern:** Uses `Db::upsert()` for efficient aggregation
- **Daily Visitor Hash:** Combines daily salt + browser + screen category + language (no fingerprinting)
- **Deferred Initialization:** Auto-cleanup registered via `Craft::$app->onInit()`
- **Event-Driven:** URL routing, permissions, widgets registered via Craft events in `attachEventHandlers()`

### Controllers

- `TrackController` - Public tracking endpoint (unauthenticated)
- `DashboardController` - CP dashboard views and AJAX endpoints
- `ExportController` - CSV/JSON export

### Enums

Located in `src/enums/`:
- `EventType` - pv, en, lv
- `DateRange` - today, 7d, 30d, 90d, 12m (with date calculation methods)
- `DeviceType` - desktop, mobile, tablet
- `ReferrerType` - direct, search, social, referral
- `ScreenCategory` - s (<768px), m (768-1199px), l (≥1200px)

### Adding Features

- **New tracking events:** Add case to `EventType` + handler in `TrackingService`
- **New analytics:** Add method to `StatsService`
- **New permissions:** Add to `UserPermissions` event in `Insights::attachEventHandlers()`
- **Database changes:** Create migration + update `Constants.php` + create ActiveRecord in `src/records/`

## Code Quality

- **ECS:** Craft CMS 4 ruleset (`ecs.php`)
- **PHPStan:** Level 4 (`phpstan.neon`)
