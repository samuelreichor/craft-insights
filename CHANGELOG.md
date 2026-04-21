# Release Notes for Insights

## 1.2.0 - 2026-04-21

- Adds scheduled email reports with configurable frequency (weekly, every two weeks, monthly) and freely editable recipient list
- Adds `Send Test Mail` button in settings to deliver the real report to the logged-in admin's address
- Adds `insights/notifications/send` console command with `--frequency` and `--force` flags
- Adds `insights_notification_log` audit table, mirrored to the external database when configured
- Adds breadcrumbs on plugin pages
- Refactors plugin settings into a tabbed General / Notifications / Advanced layout on a dedicated CP page
- Adds a warning when a setting is overridden by `config/insights.php`

## 1.1.0 - 2026-02-01
- Adds support for custom queue (`insightsQueue`) to separate analytics jobs from main queue
- Adds configurable queue job settings (`queueJobTtr`, `processTrackingJobPriority`, `maxRetryAttempts`)
- Adds Operating System breakdown to dashboard (Technology widget)
- Adds support for external database (`insightsDb`) for Pro edition

## 1.0.0 - 2026-01-11
- Initial release
