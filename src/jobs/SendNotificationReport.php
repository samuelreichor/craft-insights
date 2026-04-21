<?php

namespace samuelreichor\insights\jobs;

use Craft;
use craft\queue\BaseJob;
use samuelreichor\insights\enums\EmailFrequency;
use samuelreichor\insights\Insights;
use Throwable;

/**
 * Send Notification Report Job
 *
 * Delivers the analytics report email to the configured recipients.
 * Scheduled by NotificationService::checkAndSend when a frequency window has
 * elapsed.
 */
class SendNotificationReport extends BaseJob
{
    /**
     * Email frequency value (weekly|biweekly|monthly).
     */
    public string $frequency = '';

    public function execute($queue): void
    {
        $frequency = EmailFrequency::tryFrom($this->frequency);
        if ($frequency === null || $frequency === EmailFrequency::Never) {
            return;
        }

        try {
            Insights::getInstance()->notifications->sendReport($frequency);
        } catch (Throwable $e) {
            Insights::getInstance()->logger->error(
                "Failed to send notification report: {$e->getMessage()}",
                ['frequency' => $this->frequency],
            );
            throw $e;
        }
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('insights', 'Sending Insights analytics report');
    }

    /**
     * @inheritdoc
     */
    public function getTtr(): int
    {
        return Insights::getInstance()->getSettings()->queueJobTtr;
    }

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error): bool
    {
        $maxAttempts = Insights::getInstance()->getSettings()->maxRetryAttempts;
        return $attempt < $maxAttempts;
    }
}
