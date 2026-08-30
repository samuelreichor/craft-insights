# Release Notes for Insights

## Unreleased

- Fix duplicate report emails when queued notification jobs piled up while the queue wasn't running ([#10](https://github.com/samuelreichor/craft-insights/issues/10))
- Add a pending state to the notification log so only one report job can be queued at a time
- Queued report jobs now re-check the configured frequency before sending, so switching to "never" also cancels already-queued jobs
- Add `useCronForEmails` setting to disable the request-based check and send reports exclusively via `./craft insights/notifications/send`

## 1.4.0 - 2026-05-16

- Add support for [LLMify](https://plugins.craftcms.com/llmify) when installed
- Fix broken permissions for exports
- Rename Referrers to traffic sources
- Fix queue job error with cached entries that are already deleted

## 1.3.1 - 2026-05-06

- Loosens `dompdf/dompdf` constraint to `^2.0.3 || ^3.0` to resolve dependency conflicts with plugins like `verbb/formie`

## 1.3.0 - 2026-05-01

- Adds export as PDF feature to all pages
- Adds the ability to include full PDF reports in scheduled emails
- Adds attachPdfReport notification setting
- Improves bot detection
- Installs dompdf
- Installs jaybizzle/crawler-detect

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
