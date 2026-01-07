<?php

namespace samuelreichor\insights\jobs;

use Craft;
use craft\queue\BaseJob;
use samuelreichor\insights\enums\EventType;
use samuelreichor\insights\Insights;

/**
 * Process Tracking Job
 *
 * Processes tracking data asynchronously via the queue.
 * This ensures tracking doesn't affect page load performance.
 */
class ProcessTrackingJob extends BaseJob
{
    /**
     * Tracking event type (pv = pageview, en = engagement, lv = leave)
     */
    public string $type = 'pv';

    /**
     * Tracking data from frontend
     *
     * @var array<string, mixed>
     */
    public array $data = [];

    /**
     * User agent string
     */
    public string $userAgent = '';

    /**
     * IP address (used only for GeoIP lookup, not stored)
     */
    public string $ip = '';

    /**
     * Site ID
     */
    public int $siteId = 0;

    /**
     * Accept-Language header
     */
    public ?string $acceptLanguage = null;

    public function execute($queue): void
    {
        $logger = Insights::getInstance()->logger;
        $tracking = Insights::getInstance()->tracking;
        $eventType = EventType::tryFrom($this->type);

        $logger->debug('Queue job executing', [
            'type' => $this->type,
            'siteId' => $this->siteId,
            'url' => $this->data['u'] ?? '/',
        ]);

        try {
            match ($eventType) {
                EventType::Pageview => $tracking->processPageview(
                    $this->data,
                    $this->userAgent,
                    $this->ip,
                    $this->siteId,
                    $this->acceptLanguage
                ),
                EventType::Engagement => $tracking->processEngagement($this->data, $this->siteId),
                EventType::Leave => $tracking->processLeave($this->data, $this->siteId),
                default => $logger->warning("Unknown tracking type: {$this->type}"),
            };
            $logger->debug('Queue job completed successfully', ['type' => $this->type]);
        } catch (\Throwable $e) {
            $logger->error("Failed to process tracking: {$e->getMessage()}", [
                'type' => $this->type,
                'exception' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('insights', 'Processing Insights tracking data');
    }
}
