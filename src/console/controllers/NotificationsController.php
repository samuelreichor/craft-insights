<?php

namespace samuelreichor\insights\console\controllers;

use craft\console\Controller;
use samuelreichor\insights\enums\EmailFrequency;
use samuelreichor\insights\Insights;
use Throwable;
use yii\console\ExitCode;

/**
 * Notifications console command.
 *
 * Usage: ./craft insights/notifications/send
 */
class NotificationsController extends Controller
{
    /**
     * Override the configured email frequency for this run (never|weekly|biweekly|monthly).
     */
    public ?string $frequency = null;

    /**
     * Skip the "is due" check and the audit-log "already sent" guard — always send.
     */
    public bool $force = false;

    /**
     * @inheritdoc
     *
     * @return string[]
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'send') {
            $options[] = 'frequency';
            $options[] = 'force';
        }

        return $options;
    }

    /**
     * Send an analytics report email to the configured recipients immediately.
     *
     * ./craft insights/notifications/send
     * ./craft insights/notifications/send --force
     * ./craft insights/notifications/send --frequency=monthly --force
     */
    public function actionSend(): int
    {
        $plugin = Insights::getInstance();
        $settings = $plugin->getSettings();

        // Resolve frequency: CLI flag overrides the configured setting.
        $frequencyValue = $this->frequency ?? $settings->emailFrequency;
        $frequency = EmailFrequency::tryFrom($frequencyValue);

        if ($frequency === null) {
            $this->stderr("Unknown frequency: {$frequencyValue}\n");
            return ExitCode::USAGE;
        }

        if ($frequency === EmailFrequency::Never) {
            $this->stderr("Frequency is set to 'never'. Pass --frequency=weekly (or biweekly/monthly) to override.\n");
            return ExitCode::USAGE;
        }

        if (empty($settings->emailRecipients)) {
            $this->stderr("No recipients configured. Add at least one address in the plugin settings.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!$this->force && !$plugin->notifications->isDue($frequency)) {
            $lastSent = $plugin->notifications->getLastSentAt($frequency);
            $lastSentText = $lastSent !== null ? $lastSent->format('Y-m-d H:i:s') : 'never';
            $this->stdout("Not due yet (last sent: {$lastSentText}, interval: {$frequency->intervalDays()} days). Pass --force to send anyway.\n");
            return ExitCode::OK;
        }

        $recipients = count($settings->emailRecipients);
        $this->stdout("Sending {$frequency->value} report to {$recipients} recipient(s)...\n");

        try {
            $plugin->notifications->sendReport($frequency);
        } catch (Throwable $e) {
            $this->stderr("Failed: {$e->getMessage()}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Done. See the insights_notification_log table for the audit entry.\n");

        return ExitCode::OK;
    }
}
