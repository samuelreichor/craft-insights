<?php

namespace samuelreichor\insights\services;

use craft\base\Component;
use craft\helpers\Db;
use samuelreichor\insights\Constants;
use samuelreichor\insights\Insights;
use Throwable;
use yii\db\Expression;

/**
 * LLM Tracking Service
 *
 * Listener for `Llmify::EVENT_LLM_REQUEST`. Aggregates LLM-targeted requests
 * (`.md`, `llms.txt`, `llms-full.txt`, content-negotiated) into the
 * `insights_llm_requests` and `insights_llm_bots` tables.
 *
 * Designed to be cheap and side-effect-free: an unrecognized event is
 * silently ignored, errors are logged but never propagate.
 */
class LlmTrackingService extends Component
{
    /**
     * Yii event handler — duck-types the event so we don't hard-require LLMify.
     *
     * Expects the event to expose:
     *   - `requestType` (BackedEnum with string `value`)
     *   - `siteId` (int|null)
     *   - `botName` (string|null)
     *   - `elementType` (string|null)
     */
    public function processRequest(object $event): void
    {
        try {
            $this->handle($event);
        } catch (Throwable $e) {
            Insights::getInstance()->logger->error(
                'LlmTrackingService failed: ' . $e->getMessage()
            );
        }
    }

    private function handle(object $event): void
    {
        $requestType = $this->extractRequestType($event);
        if ($requestType === null) {
            return;
        }

        $siteId = $this->extractInt($event, 'siteId');
        if ($siteId === null) {
            return;
        }

        $botName = $this->extractString($event, 'botName') ?? '';
        $elementType = $this->extractString($event, 'elementType') ?? '';
        $rawPath = $this->extractString($event, 'markdownPath')
            ?? $this->extractString($event, 'url');
        $url = $this->normalizeUrl($rawPath);
        $urlHash = md5($url ?? '');
        $date = date('Y-m-d');

        $db = Insights::getInstance()->database->getConnection();

        Db::upsert(Constants::TABLE_LLM_REQUESTS, $this->withTimestamps([
            'siteId' => $siteId,
            'date' => $date,
            'botName' => $botName,
            'requestType' => $requestType,
            'elementType' => $elementType,
            'url' => $url,
            'urlHash' => $urlHash,
            'count' => 1,
        ]), [
            'count' => new Expression('[[count]] + 1'),
        ], [], true, $db);

        if ($botName !== '') {
            Db::upsert(Constants::TABLE_LLM_BOTS, $this->withTimestamps([
                'siteId' => $siteId,
                'botName' => $botName,
                'lastSeen' => date('Y-m-d H:i:s'),
                'totalRequests' => 1,
            ]), [
                'lastSeen' => date('Y-m-d H:i:s'),
                'totalRequests' => new Expression('[[totalRequests]] + 1'),
            ], [], true, $db);
        }
    }

    private function extractRequestType(object $event): ?string
    {
        if (!property_exists($event, 'requestType')) {
            return null;
        }

        $value = $event->requestType;

        if ($value instanceof \BackedEnum) {
            return (string)$value->value;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    private function extractInt(object $event, string $property): ?int
    {
        if (!property_exists($event, $property)) {
            return null;
        }

        $value = $event->{$property};
        return is_int($value) ? $value : null;
    }

    /**
     * Reduce an absolute URL to its path so the same page lands on the same row
     * regardless of host, protocol, or query string.
     */
    private function normalizeUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $parts = parse_url($url);
        $path = is_array($parts) && isset($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/';

        if (strlen($path) > Constants::MAX_URL_LENGTH) {
            $path = substr($path, 0, Constants::MAX_URL_LENGTH);
        }

        return $path;
    }

    private function extractString(object $event, string $property): ?string
    {
        if (!property_exists($event, $property)) {
            return null;
        }

        $value = $event->{$property};
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function withTimestamps(array $data): array
    {
        $now = date('Y-m-d H:i:s');
        $data['dateCreated'] = $now;
        $data['dateUpdated'] = $now;
        $data['uid'] = bin2hex(random_bytes(16));

        return $data;
    }
}
