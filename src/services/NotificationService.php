<?php

namespace samuelreichor\insights\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use DateTime;
use samuelreichor\insights\Constants;
use samuelreichor\insights\enums\EmailFrequency;
use samuelreichor\insights\enums\NotificationStatus;
use samuelreichor\insights\helpers\Utils;
use samuelreichor\insights\Insights;
use samuelreichor\insights\jobs\SendNotificationReport;
use samuelreichor\insights\records\NotificationLogRecord;
use Throwable;

/**
 * Notification Service
 *
 * Sends aggregated analytics reports to configured recipients based on the
 * plugin's email frequency setting. Piggy-backs on regular request traffic
 * via an hourly-throttled check, so no cron is required.
 */
class NotificationService extends Component
{
    /**
     * Throttle the due-check to once per request window.
     *
     * Invoked from Insights::init via onInit. Returns early without DB work
     * when the hourly cache marker is still warm.
     */
    public function checkAndSend(): void
    {
        $plugin = Insights::getInstance();
        $logger = $plugin->logger;
        $settings = $plugin->getSettings();

        if ($settings->useCronForEmails) {
            return;
        }

        $cache = Craft::$app->getCache();
        if ($cache->get(Constants::CACHE_NOTIFICATION_CHECK) !== false) {
            $logger->debug('Notification check: throttled by hourly cache');
            return;
        }
        $cache->set(Constants::CACHE_NOTIFICATION_CHECK, time(), Constants::NOTIFICATION_CHECK_INTERVAL);

        $frequency = EmailFrequency::tryFrom($settings->emailFrequency) ?? EmailFrequency::Never;

        if ($frequency === EmailFrequency::Never) {
            $logger->debug('Notification check: frequency is never, skipping');
            return;
        }

        if (empty($settings->emailRecipients)) {
            $logger->debug('Notification check: no recipients configured, skipping');
            return;
        }

        if (!$this->isDue($frequency)) {
            $logger->debug('Notification check: not due yet', ['frequency' => $frequency->value]);
            return;
        }

        if ($this->hasPendingReport($frequency)) {
            $logger->debug('Notification check: a report job is already queued, skipping', [
                'frequency' => $frequency->value,
            ]);
            return;
        }

        $log = $this->createLog($frequency, NotificationStatus::Queued);

        $plugin->getQueue()->push(new SendNotificationReport([
            'frequency' => $frequency->value,
            'logId' => $log->id,
        ]));
        $logger->info('Notification check: queued SendNotificationReport job', [
            'frequency' => $frequency->value,
            'recipients' => count($settings->emailRecipients),
        ]);
    }

    /**
     * Whether a queued report job is still waiting to be picked up.
     *
     * Queued log rows older than NOTIFICATION_PENDING_TTL are treated as
     * stale (e.g. the job was manually deleted), so a lost job can never
     * block reports forever.
     */
    private function hasPendingReport(EmailFrequency $frequency): bool
    {
        $staleCutoff = (new DateTime())
            ->modify('-' . Constants::NOTIFICATION_PENDING_TTL . ' seconds')
            ->format('Y-m-d H:i:s');

        return NotificationLogRecord::find()
            ->where(['frequency' => $frequency->value, 'status' => NotificationStatus::Queued->value])
            ->andWhere(['>=', 'dateCreated', $staleCutoff])
            ->exists();
    }

    /**
     * Whether an email for the given frequency should go out now.
     *
     * Falls back to the plugin install date when there's no prior log entry,
     * so a fresh install doesn't fire an (empty) report right away — the
     * first email only goes out once the plugin has been collecting data for
     * at least the configured interval.
     */
    public function isDue(EmailFrequency $frequency): bool
    {
        if ($frequency === EmailFrequency::Never) {
            return false;
        }

        $reference = $this->getLastSentAt($frequency) ?? $this->getPluginInstallDate();
        if ($reference === null) {
            return true;
        }

        $diffSeconds = (new DateTime())->getTimestamp() - $reference->getTimestamp();
        $diffDays = (int)floor($diffSeconds / 86400);

        return $diffDays >= $frequency->intervalDays();
    }

    /**
     * Timestamp of the plugin's installation, read from Craft's plugin store.
     */
    private function getPluginInstallDate(): ?DateTime
    {
        $info = Craft::$app->getPlugins()->getStoredPluginInfo('insights');
        if ($info === null || empty($info['installDate'])) {
            return null;
        }

        try {
            return new DateTime($info['installDate']);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Latest successful send timestamp for the given frequency.
     */
    public function getLastSentAt(EmailFrequency $frequency): ?DateTime
    {
        /** @var NotificationLogRecord|null $record */
        $record = NotificationLogRecord::find()
            ->where(['frequency' => $frequency->value, 'status' => NotificationStatus::Sent->value])
            ->orderBy(['sentAt' => SORT_DESC])
            ->one();

        if ($record === null) {
            return null;
        }

        return new DateTime($record->sentAt);
    }

    /**
     * Entry point for the SendNotificationReport queue job.
     *
     * Re-checks that the report is still due right before delivering, so
     * duplicate jobs that piled up while the queue wasn't running (#10)
     * collapse into a single email — every job after the first one sees a
     * fresh "sent" log row and skips itself.
     */
    public function sendQueuedReport(EmailFrequency $frequency, ?int $logId = null): void
    {
        $log = $logId !== null ? NotificationLogRecord::findOne($logId) : null;

        $settings = Insights::getInstance()->getSettings();
        $configured = EmailFrequency::tryFrom($settings->emailFrequency) ?? EmailFrequency::Never;
        if ($configured !== $frequency) {
            $this->skipLog($log, $frequency, 'configured frequency changed since the job was queued');
            return;
        }

        if (!$this->isDue($frequency)) {
            $this->skipLog($log, $frequency, 'report no longer due');
            return;
        }

        $this->sendReport($frequency, $log);
    }

    /**
     * Mark a pending log row as skipped and note why.
     */
    private function skipLog(?NotificationLogRecord $log, EmailFrequency $frequency, string $reason): void
    {
        if ($log !== null) {
            $log->status = NotificationStatus::Skipped->value;
            $log->save();
        }
        Insights::getInstance()->logger->info("Notification job skipped: {$reason}", [
            'frequency' => $frequency->value,
        ]);
    }

    /**
     * Build the report payload and deliver the email.
     *
     * When a pending log record is passed (queue path), its row is updated in
     * place; otherwise (console path) a fresh audit row is created.
     */
    public function sendReport(EmailFrequency $frequency, ?NotificationLogRecord $log = null): void
    {
        $plugin = Insights::getInstance();
        $settings = $plugin->getSettings();
        $recipients = array_values(array_filter($settings->emailRecipients));

        if (empty($recipients)) {
            return;
        }

        try {
            $html = $this->renderEmailHtml($frequency);
            $attachment = $this->buildPdfAttachment($frequency);
            $this->deliver(
                recipients: $recipients,
                subject: Craft::t('insights', 'Your Insights analytics report'),
                html: $html,
                attachment: $attachment,
            );

            $this->logSend($frequency, count($recipients), NotificationStatus::Sent, null, $log);
            $plugin->logger->info('Insights notification sent', [
                'frequency' => $frequency->value,
                'recipients' => count($recipients),
            ]);
        } catch (Throwable $e) {
            $this->logSend($frequency, count($recipients), NotificationStatus::Failed, $e->getMessage(), $log);
            $plugin->logger->error('Insights notification failed: ' . $e->getMessage(), [
                'frequency' => $frequency->value,
            ]);
            throw $e;
        }
    }

    /**
     * Send the regular report email to a single ad-hoc address.
     *
     * Mirrors the real send — same template, same subject — using the
     * configured frequency (fallback: Weekly) so the recipient sees exactly
     * what the scheduled email will look like. Does not write an audit-log
     * row, so it cannot block or affect the due-check.
     */
    public function sendTestMail(string $recipient): void
    {
        $configured = EmailFrequency::tryFrom(Insights::getInstance()->getSettings()->emailFrequency);
        $frequency = ($configured !== null && $configured !== EmailFrequency::Never)
            ? $configured
            : EmailFrequency::Weekly;

        $this->deliver(
            recipients: [$recipient],
            subject: Craft::t('insights', 'Your Insights analytics report'),
            html: $this->renderEmailHtml($frequency),
            attachment: $this->buildPdfAttachment($frequency),
        );
    }

    /**
     * Build the dashboard PDF attachment for a scheduled email, or return null
     * when the user disabled it. Errors are swallowed (and logged) so a broken
     * PDF render never blocks the regular HTML email going out.
     *
     * @return array{content: string, filename: string}|null
     */
    private function buildPdfAttachment(EmailFrequency $frequency): ?array
    {
        $plugin = Insights::getInstance();

        if (!$plugin->getSettings()->attachPdfReport) {
            return null;
        }

        try {
            $siteId = Craft::$app->getSites()->getPrimarySite()->id;
            $range = $frequency->statsRange();
            $variables = $plugin->pdf->buildDashboardData($siteId, $range);

            return [
                'content' => $plugin->pdf->generate('insights/_pdf/dashboard.twig', $variables),
                'filename' => "insights-dashboard-{$range}.pdf",
            ];
        } catch (Throwable $e) {
            $plugin->logger->warning('Insights PDF attachment failed: ' . $e->getMessage(), [
                'frequency' => $frequency->value,
            ]);
            return null;
        }
    }

    /**
     * Render the report HTML for the given frequency.
     */
    private function renderEmailHtml(EmailFrequency $frequency): string
    {
        $data = $this->buildReportData($frequency);
        return Craft::$app->getView()->renderTemplate(
            'insights/_emails/weekly-report',
            $data,
            Craft::$app->getView()::TEMPLATE_MODE_CP,
        );
    }

    /**
     * Send an email via Craft's mailer, respecting the system From settings.
     *
     * @param string[] $recipients
     * @param array{content: string, filename: string}|null $attachment Optional binary attachment.
     * @throws \RuntimeException When the mailer reports a failed send.
     */
    private function deliver(array $recipients, string $subject, string $html, ?array $attachment = null): void
    {
        $fromEmail = App::parseEnv(Craft::$app->getProjectConfig()->get('email.fromEmail')) ?? null;
        $fromName = App::parseEnv(Craft::$app->getProjectConfig()->get('email.fromName')) ?? 'Insights';

        $message = Craft::$app->getMailer()->compose()
            ->setSubject($subject)
            ->setHtmlBody($html)
            ->setTo($recipients);

        if ($fromEmail) {
            $message->setFrom([$fromEmail => $fromName]);
        }

        if ($attachment !== null) {
            $message->attachContent($attachment['content'], [
                'fileName' => $attachment['filename'],
                'contentType' => 'application/pdf',
            ]);
        }

        if (!$message->send()) {
            throw new \RuntimeException('Mailer returned false.');
        }
    }

    /**
     * Aggregate the data that the email template renders.
     *
     * @return array<string, mixed>
     */
    private function buildReportData(EmailFrequency $frequency): array
    {
        $plugin = Insights::getInstance();
        $stats = $plugin->stats;

        $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $range = $frequency->statsRange();

        $llmifyInstalled = Utils::isPluginInstalledAndEnabled('llmify');

        return [
            'frequency' => $frequency,
            'periodDays' => $frequency->intervalDays(),
            'summary' => $stats->getSummary($siteId, $range),
            'topPages' => $stats->getTopPages($siteId, $range, 3),
            'topReferrers' => $stats->getTopReferrers($siteId, $range, 3),
            'llmTotals' => $llmifyInstalled ? $stats->getLlmTotals($siteId, $range) : null,
            'llmTopPages' => $llmifyInstalled ? $stats->getLlmTopPages($siteId, $range, 3) : [],
            'dashboardUrl' => UrlHelper::cpUrl('insights'),
        ];
    }

    /**
     * Persist a row in the audit table, updating the pending row in place
     * when one was handed through from the queue path.
     */
    private function logSend(
        EmailFrequency $frequency,
        int $recipientCount,
        NotificationStatus $status,
        ?string $errorMessage = null,
        ?NotificationLogRecord $record = null,
    ): void {
        $record ??= new NotificationLogRecord();
        $record->frequency = $frequency->value;
        $record->sentAt = (new DateTime())->format('Y-m-d H:i:s');
        $record->recipientCount = $recipientCount;
        $record->status = $status->value;
        $record->errorMessage = $errorMessage;
        $record->save();
    }

    /**
     * Create a fresh audit row with the given status.
     */
    private function createLog(EmailFrequency $frequency, NotificationStatus $status): NotificationLogRecord
    {
        $record = new NotificationLogRecord();
        $record->frequency = $frequency->value;
        $record->sentAt = (new DateTime())->format('Y-m-d H:i:s');
        $record->recipientCount = 0;
        $record->status = $status->value;
        $record->save();

        return $record;
    }
}
