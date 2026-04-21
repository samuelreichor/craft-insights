<?php

namespace samuelreichor\insights\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use DateTime;
use samuelreichor\insights\Constants;
use samuelreichor\insights\enums\EmailFrequency;
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

        $cache = Craft::$app->getCache();
        if ($cache->get(Constants::CACHE_NOTIFICATION_CHECK) !== false) {
            $logger->debug('Notification check: throttled by hourly cache');
            return;
        }
        $cache->set(Constants::CACHE_NOTIFICATION_CHECK, time(), Constants::NOTIFICATION_CHECK_INTERVAL);

        $settings = $plugin->getSettings();
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

        $plugin->getQueue()->push(new SendNotificationReport([
            'frequency' => $frequency->value,
        ]));
        $logger->info('Notification check: queued SendNotificationReport job', [
            'frequency' => $frequency->value,
            'recipients' => count($settings->emailRecipients),
        ]);
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
            ->where(['frequency' => $frequency->value, 'status' => 'sent'])
            ->orderBy(['sentAt' => SORT_DESC])
            ->one();

        if ($record === null) {
            return null;
        }

        return new DateTime($record->sentAt);
    }

    /**
     * Build the report payload and deliver the email.
     *
     * Called by SendNotificationReport queue job.
     */
    public function sendReport(EmailFrequency $frequency): void
    {
        $plugin = Insights::getInstance();
        $settings = $plugin->getSettings();
        $recipients = array_values(array_filter($settings->emailRecipients));

        if (empty($recipients)) {
            return;
        }

        try {
            $html = $this->renderEmailHtml($frequency);
            $this->deliver(
                recipients: $recipients,
                subject: Craft::t('insights', 'Your Insights analytics report'),
                html: $html,
            );

            $this->logSend($frequency, count($recipients), 'sent');
            $plugin->logger->info('Insights notification sent', [
                'frequency' => $frequency->value,
                'recipients' => count($recipients),
            ]);
        } catch (Throwable $e) {
            $this->logSend($frequency, count($recipients), 'failed', $e->getMessage());
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
        );
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
     * @throws \RuntimeException When the mailer reports a failed send.
     */
    private function deliver(array $recipients, string $subject, string $html): void
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

        return [
            'frequency' => $frequency,
            'periodDays' => $frequency->intervalDays(),
            'summary' => $stats->getSummary($siteId, $range),
            'topPages' => $stats->getTopPages($siteId, $range, 3),
            'topReferrers' => $stats->getTopReferrers($siteId, $range, 3),
            'dashboardUrl' => UrlHelper::cpUrl('insights'),
        ];
    }

    /**
     * Persist a row in the audit table.
     */
    private function logSend(
        EmailFrequency $frequency,
        int $recipientCount,
        string $status,
        ?string $errorMessage = null,
    ): void {
        $record = new NotificationLogRecord();
        $record->frequency = $frequency->value;
        $record->sentAt = (new DateTime())->format('Y-m-d H:i:s');
        $record->recipientCount = $recipientCount;
        $record->status = $status;
        $record->errorMessage = $errorMessage;
        $record->save();
    }
}
