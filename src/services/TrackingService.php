<?php

namespace samuelreichor\insights\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\helpers\Db;
use samuelreichor\insights\Constants;
use samuelreichor\insights\enums\DeviceType;
use samuelreichor\insights\enums\ReferrerType;
use samuelreichor\insights\enums\ScreenCategory;
use samuelreichor\insights\Insights;
use WhichBrowser\Parser;
use yii\db\Expression;

/**
 * Tracking Service
 *
 * Handles all tracking logic. Ensures DSGVO compliance by:
 * - Never storing IP addresses
 * - Using aggregated data only
 * - Storing only non-identifying attributes
 */
class TrackingService extends Component
{
    /**
     * Process a pageview event.
     *
     * @param array<string, mixed> $data Tracking data from frontend
     * @param string $userAgent User agent string
     * @param string $ip IP address (used for GeoIP lookup only, NOT stored)
     * @param int $siteId Site ID
     * @param string|null $acceptLanguage Accept-Language header
     */
    public function processPageview(array $data, string $userAgent, string $ip, int $siteId, ?string $acceptLanguage = null): void
    {
        $logger = Insights::getInstance()->logger;
        $logger->beginFeature('Pageview', ['siteId' => $siteId]);

        $date = date('Y-m-d');
        $hour = (int)date('H');
        $url = $this->sanitizeUrl($data['u'] ?? Constants::DEFAULT_PATH);
        $screenCategory = $data['sc'] ?? ScreenCategory::Medium->value;

        $logger->step('Pageview', 'URL sanitized', ['url' => $url, 'screenCategory' => $screenCategory]);

        // Generate visitor hash
        $logger->startTimer('visitorHash');
        $visitorHash = Insights::getInstance()->visitor->generateHash($userAgent, $screenCategory, $acceptLanguage, $ip);
        $logger->stopTimer('visitorHash');

        // Find entry ID for this URL
        $logger->startTimer('findEntry');
        $entryId = $this->findEntryByUrl($url, $siteId);
        $logger->stopTimer('findEntry', ['entryId' => $entryId]);

        // Check if this is a new visitor for this URL today
        $isNew = $this->isNewVisitor($visitorHash, $url, $siteId, $date);
        $logger->step('Pageview', 'Visitor check', ['isNew' => $isNew]);

        // Aggregate pageview (UPSERT)
        // First array = INSERT values (used when row doesn't exist)
        // Second array = UPDATE values (used when row exists)
        $logger->startTimer('upsertPageview');
        Db::upsert(Constants::TABLE_PAGEVIEWS, [
            'siteId' => $siteId,
            'date' => $date,
            'hour' => $hour,
            'url' => $url,
            'entryId' => $entryId,
            'views' => 1,
            'uniqueVisitors' => $isNew ? 1 : 0,
            'bounces' => $isNew ? 1 : 0,
        ], [
            'views' => new Expression('[[views]] + 1'),
            'uniqueVisitors' => $isNew
                ? new Expression('[[uniqueVisitors]] + 1')
                : new Expression('[[uniqueVisitors]]'),
            'bounces' => $isNew
                ? new Expression('[[bounces]] + 1')
                : new Expression('[[bounces]]'),
        ]);
        $logger->stopTimer('upsertPageview');

        // Track referrer
        if (!empty($data['r'])) {
            $logger->startTimer('trackReferrer');
            $this->trackReferrer($data['r'], $siteId, $date);
            $logger->stopTimer('trackReferrer', ['domain' => $data['r']]);
        }

        // Track UTM parameters (Pro feature)
        if (Insights::getInstance()->isPro() && !empty($data['utm']['s'])) {
            $logger->startTimer('trackCampaign');
            $this->trackCampaign($data['utm'], $siteId, $date);
            $logger->stopTimer('trackCampaign', ['source' => $data['utm']['s']]);
        }

        // Track device info
        $logger->startTimer('trackDevice');
        $this->trackDevice($userAgent, $siteId, $date);
        $logger->stopTimer('trackDevice');

        // Track country (IP -> Country -> IP discarded)
        $logger->startTimer('geoipLookup');
        $countryCode = Insights::getInstance()->geoip->getCountry($ip);
        $logger->stopTimer('geoipLookup', ['countryCode' => $countryCode]);

        if ($countryCode) {
            $this->trackCountry($countryCode, $siteId, $date);
        }

        // Update realtime
        $logger->startTimer('updateRealtime');
        $this->updateRealtime($visitorHash, $url, $siteId);
        $logger->stopTimer('updateRealtime');

        $logger->endFeature('Pageview', ['url' => $url, 'isNew' => $isNew]);
    }

    /**
     * Process an engagement event (user scrolled or clicked).
     *
     * @param array<string, mixed> $data Tracking data
     * @param int $siteId Site ID
     */
    public function processEngagement(array $data, int $siteId): void
    {
        $logger = Insights::getInstance()->logger;
        $logger->beginFeature('Engagement', ['siteId' => $siteId]);

        $url = $this->sanitizeUrl($data['u'] ?? Constants::DEFAULT_PATH);
        $date = date('Y-m-d');

        // Decrement bounce count (use CASE to avoid UNSIGNED underflow)
        $logger->startTimer('updateBounce');
        Craft::$app->db->createCommand()
            ->update(Constants::TABLE_PAGEVIEWS, [
                'bounces' => new Expression('CASE WHEN [[bounces]] > 0 THEN [[bounces]] - 1 ELSE 0 END'),
            ], [
                'siteId' => $siteId,
                'date' => $date,
                'url' => $url,
            ])
            ->execute();
        $logger->stopTimer('updateBounce');

        $logger->endFeature('Engagement', ['url' => $url]);
    }

    /**
     * Process a leave event (user left the page).
     *
     * @param array<string, mixed> $data Tracking data including time on page
     * @param int $siteId Site ID
     */
    public function processLeave(array $data, int $siteId): void
    {
        $logger = Insights::getInstance()->logger;
        $logger->beginFeature('Leave', ['siteId' => $siteId]);

        $url = $this->sanitizeUrl($data['u'] ?? Constants::DEFAULT_PATH);
        $time = min((int)($data['tm'] ?? 0), Constants::MAX_TIME_ON_PAGE);

        $logger->step('Leave', 'Time on page', ['seconds' => $time, 'url' => $url]);

        if ($time > 0) {
            $logger->startTimer('updateTimeOnPage');
            Craft::$app->db->createCommand()
                ->update(Constants::TABLE_PAGEVIEWS, [
                    'totalTimeOnPage' => new Expression("[[totalTimeOnPage]] + :time", [':time' => $time]),
                ], [
                    'siteId' => $siteId,
                    'date' => date('Y-m-d'),
                    'url' => $url,
                ])
                ->execute();
            $logger->stopTimer('updateTimeOnPage');
        }

        $logger->endFeature('Leave', ['url' => $url, 'timeOnPage' => $time]);
    }

    /**
     * Sanitize URL to only include path.
     */
    public function sanitizeUrl(string $url): string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? Constants::DEFAULT_PATH;

        // Normalize
        $path = rtrim($path, '/') ?: Constants::DEFAULT_PATH;

        return substr($path, 0, Constants::MAX_URL_LENGTH);
    }

    /**
     * Check if this is a new visitor for this URL today.
     *
     * Uses atomic cache->add() to prevent race conditions where concurrent
     * requests could both return true before either sets the key.
     */
    private function isNewVisitor(string $hash, string $url, int $siteId, string $date): bool
    {
        $key = Constants::CACHE_VISITOR . "{$siteId}_{$date}_{$hash}_" . md5($url);

        // Cache until midnight - add() is atomic and returns false if key exists
        $ttl = strtotime('tomorrow') - time();

        return Craft::$app->cache->add($key, 1, $ttl);
    }

    /**
     * Find entry by URL with caching.
     *
     * Caches URL-to-entryId mappings to avoid repeated database queries
     * for the same URL within a short time period.
     */
    private function findEntryByUrl(string $url, int $siteId): ?int
    {
        $cacheKey = Constants::CACHE_VISITOR . "entry_{$siteId}_" . md5($url);

        // Check cache first (1 hour TTL)
        $cachedId = Craft::$app->cache->get($cacheKey);
        if ($cachedId !== false) {
            // Return null for cached "not found" (stored as 0)
            return $cachedId === 0 ? null : $cachedId;
        }

        // Try to find an entry matching this URL
        $entry = Entry::find()
            ->site('*')
            ->siteId($siteId)
            ->uri(ltrim($url, '/'))
            ->status(null)
            ->one();

        $entryId = $entry?->id;

        // Cache the result (use 0 for null to distinguish from cache miss)
        Craft::$app->cache->set($cacheKey, $entryId ?? 0, 3600);

        return $entryId;
    }

    /**
     * Track referrer domain.
     */
    private function trackReferrer(string $domain, int $siteId, string $date): void
    {
        $type = ReferrerType::fromDomain($domain);

        Db::upsert(Constants::TABLE_REFERRERS, [
            'siteId' => $siteId,
            'date' => $date,
            'referrerDomain' => substr($domain, 0, 255),
            'referrerType' => $type->value,
            'visits' => 1,
        ], [
            'visits' => new Expression('[[visits]] + 1'),
        ]);
    }

    /**
     * Track UTM campaign.
     *
     * @param array<string, string|null> $utm UTM parameters
     */
    private function trackCampaign(array $utm, int $siteId, string $date): void
    {
        Db::upsert(Constants::TABLE_CAMPAIGNS, [
            'siteId' => $siteId,
            'date' => $date,
            'utmSource' => !empty($utm['s']) ? substr($utm['s'], 0, 100) : null,
            'utmMedium' => !empty($utm['m']) ? substr($utm['m'], 0, 100) : null,
            'utmCampaign' => !empty($utm['c']) ? substr($utm['c'], 0, 100) : null,
            'utmTerm' => !empty($utm['t']) ? substr($utm['t'], 0, 100) : null,
            'utmContent' => !empty($utm['n']) ? substr($utm['n'], 0, 100) : null,
            'visits' => 1,
        ], [
            'visits' => new Expression('[[visits]] + 1'),
        ]);
    }

    /**
     * Track device and browser info.
     */
    private function trackDevice(string $userAgent, int $siteId, string $date): void
    {
        try {
            $parser = new Parser($userAgent);

            $deviceType = DeviceType::fromBrowserType($parser->device->type);
            $browserFamily = $parser->browser->name ?: Constants::DEFAULT_UNKNOWN;
            $osFamily = $parser->os->name ?: Constants::DEFAULT_UNKNOWN;

            Db::upsert(Constants::TABLE_DEVICES, [
                'siteId' => $siteId,
                'date' => $date,
                'deviceType' => $deviceType->value,
                'browserFamily' => substr($browserFamily, 0, 50),
                'osFamily' => substr($osFamily, 0, 50),
                'visits' => 1,
            ], [
                'visits' => new Expression('[[visits]] + 1'),
            ]);
        } catch (\Throwable) {
            // Silently ignore parsing errors
        }
    }

    /**
     * Track country.
     */
    private function trackCountry(string $countryCode, int $siteId, string $date): void
    {
        Db::upsert(Constants::TABLE_COUNTRIES, [
            'siteId' => $siteId,
            'date' => $date,
            'countryCode' => $countryCode,
            'visits' => 1,
        ], [
            'visits' => new Expression('[[visits]] + 1'),
        ]);
    }

    /**
     * Update realtime visitor tracking.
     *
     * Cleanup of old entries is throttled to run at most once every 30 seconds
     * to prevent lock contention on high-traffic sites.
     */
    private function updateRealtime(string $hash, string $url, int $siteId): void
    {
        $settings = Insights::getInstance()->getSettings();

        // Throttle cleanup to once every 60 seconds using atomic add()
        $cleanupKey = Constants::CACHE_VISITOR . 'realtime_cleanup';
        if (Craft::$app->cache->add($cleanupKey, 1, 60)) {
            // We got the lock, perform cleanup
            $cutoff = date('Y-m-d H:i:s', strtotime("-{$settings->realtimeTtl} seconds"));
            Craft::$app->db->createCommand()
                ->delete(Constants::TABLE_REALTIME, ['<', 'lastSeen', $cutoff])
                ->execute();
        }

        $now = date('Y-m-d H:i:s');

        // Update/insert current visitor
        Db::upsert(Constants::TABLE_REALTIME, [
            'siteId' => $siteId,
            'visitorHash' => $hash,
            'currentUrl' => $url,
            'lastSeen' => $now,
        ], [
            'currentUrl' => $url,
            'lastSeen' => $now,
        ]);
    }

    /**
     * Process a custom event (Pro feature).
     *
     * @param array<string, mixed> $data Tracking data from frontend
     * @param string $userAgent User agent string
     * @param string $ip IP address (used for visitor hash only, never stored)
     * @param int $siteId Site ID
     * @param string|null $acceptLanguage Accept-Language header
     */
    public function processEvent(array $data, string $userAgent, string $ip, int $siteId, ?string $acceptLanguage = null): void
    {
        // Pro feature only
        if (!Insights::getInstance()->isPro()) {
            return;
        }

        $logger = Insights::getInstance()->logger;
        $logger->beginFeature('CustomEvent', ['siteId' => $siteId]);

        $date = date('Y-m-d');
        $hour = (int)date('H');
        $url = $this->sanitizeUrl($data['u'] ?? Constants::DEFAULT_PATH);
        $eventName = substr($data['name'] ?? 'unknown', 0, 100);
        $eventCategory = !empty($data['category']) ? substr($data['category'], 0, 50) : null;
        $screenCategory = $data['sc'] ?? ScreenCategory::Medium->value;

        $logger->step('CustomEvent', 'Event parsed', [
            'name' => $eventName,
            'category' => $eventCategory,
            'url' => $url,
        ]);

        // Generate visitor hash for unique counting
        $visitorHash = Insights::getInstance()->visitor->generateHash($userAgent, $screenCategory, $acceptLanguage, $ip);

        // Check if this is a new visitor for this event today
        $isNew = $this->isNewEventVisitor($visitorHash, $eventName, $siteId, $date);

        // Aggregate event (UPSERT)
        $logger->startTimer('upsertEvent');
        Db::upsert(Constants::TABLE_EVENTS, [
            'siteId' => $siteId,
            'date' => $date,
            'hour' => $hour,
            'eventName' => $eventName,
            'eventCategory' => $eventCategory,
            'url' => $url,
            'count' => 1,
            'uniqueVisitors' => $isNew ? 1 : 0,
        ], [
            'count' => new Expression('[[count]] + 1'),
            'uniqueVisitors' => $isNew
                ? new Expression('[[uniqueVisitors]] + 1')
                : new Expression('[[uniqueVisitors]]'),
        ]);
        $logger->stopTimer('upsertEvent');

        $logger->endFeature('CustomEvent', ['name' => $eventName, 'isNew' => $isNew]);
    }

    /**
     * Check if this is a new visitor for this event today.
     *
     * Uses atomic cache->add() to prevent race conditions.
     */
    private function isNewEventVisitor(string $hash, string $eventName, int $siteId, string $date): bool
    {
        $key = Constants::CACHE_VISITOR . "ev_{$siteId}_{$date}_{$hash}_" . md5($eventName);

        // Cache until midnight - add() is atomic and returns false if key exists
        $ttl = strtotime('tomorrow') - time();

        return Craft::$app->cache->add($key, 1, $ttl);
    }

    /**
     * Process an outbound link click (Pro feature).
     *
     * @param array<string, mixed> $data Tracking data from frontend
     * @param string $userAgent User agent string
     * @param string $ip IP address (used for visitor hash only, never stored)
     * @param int $siteId Site ID
     * @param string|null $acceptLanguage Accept-Language header
     */
    public function processOutbound(array $data, string $userAgent, string $ip, int $siteId, ?string $acceptLanguage = null): void
    {
        // Pro feature only
        if (!Insights::getInstance()->isPro()) {
            return;
        }

        $logger = Insights::getInstance()->logger;
        $logger->beginFeature('OutboundLink', ['siteId' => $siteId]);

        $date = date('Y-m-d');
        $hour = (int)date('H');
        $sourceUrl = $this->sanitizeUrl($data['u'] ?? Constants::DEFAULT_PATH);
        $targetUrl = substr($data['target'] ?? '', 0, 500);
        $targetDomain = $this->extractDomain($targetUrl);
        $linkText = !empty($data['text']) ? substr($data['text'], 0, 255) : null;
        $screenCategory = $data['sc'] ?? ScreenCategory::Medium->value;

        if (empty($targetUrl) || empty($targetDomain)) {
            $logger->warning('Invalid outbound link data', ['target' => $targetUrl]);
            return;
        }

        $logger->step('OutboundLink', 'Link parsed', [
            'targetUrl' => $targetUrl,
            'targetDomain' => $targetDomain,
            'sourceUrl' => $sourceUrl,
        ]);

        // Generate visitor hash for unique counting
        $visitorHash = Insights::getInstance()->visitor->generateHash($userAgent, $screenCategory, $acceptLanguage, $ip);

        // Check if this is a new visitor for this outbound link today
        $isNew = $this->isNewOutboundVisitor($visitorHash, $targetUrl, $siteId, $date);

        // Generate URL hash for unique constraint (to avoid MySQL key length limits)
        $urlHash = md5($targetUrl . $sourceUrl);

        // Aggregate outbound click (UPSERT)
        $logger->startTimer('upsertOutbound');
        Db::upsert(Constants::TABLE_OUTBOUND, [
            'siteId' => $siteId,
            'date' => $date,
            'hour' => $hour,
            'targetUrl' => $targetUrl,
            'targetDomain' => $targetDomain,
            'linkText' => $linkText,
            'sourceUrl' => $sourceUrl,
            'urlHash' => $urlHash,
            'clicks' => 1,
            'uniqueVisitors' => $isNew ? 1 : 0,
        ], [
            'clicks' => new Expression('[[clicks]] + 1'),
            'uniqueVisitors' => $isNew
                ? new Expression('[[uniqueVisitors]] + 1')
                : new Expression('[[uniqueVisitors]]'),
            'linkText' => $linkText,
        ]);
        $logger->stopTimer('upsertOutbound');

        $logger->endFeature('OutboundLink', ['targetDomain' => $targetDomain, 'isNew' => $isNew]);
    }

    /**
     * Extract domain from URL.
     */
    private function extractDomain(string $url): string
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? '';
    }

    /**
     * Check if this is a new visitor for this outbound link today.
     *
     * Uses atomic cache->add() to prevent race conditions.
     */
    private function isNewOutboundVisitor(string $hash, string $targetUrl, int $siteId, string $date): bool
    {
        $key = Constants::CACHE_VISITOR . "ob_{$siteId}_{$date}_{$hash}_" . md5($targetUrl);

        // Cache until midnight - add() is atomic and returns false if key exists
        $ttl = strtotime('tomorrow') - time();

        return Craft::$app->cache->add($key, 1, $ttl);
    }

    /**
     * Process a site search (Pro feature).
     *
     * @param array<string, mixed> $data Tracking data from frontend
     * @param string $userAgent User agent string
     * @param string $ip IP address (used for visitor hash only, never stored)
     * @param int $siteId Site ID
     * @param string|null $acceptLanguage Accept-Language header
     */
    public function processSearch(array $data, string $userAgent, string $ip, int $siteId, ?string $acceptLanguage = null): void
    {
        // Pro feature only
        if (!Insights::getInstance()->isPro()) {
            return;
        }

        $logger = Insights::getInstance()->logger;
        $logger->beginFeature('SiteSearch', ['siteId' => $siteId]);

        $date = date('Y-m-d');
        $hour = (int)date('H');
        $searchTerm = trim($data['query'] ?? '');
        $resultsCount = isset($data['results']) ? (int)$data['results'] : null;
        $screenCategory = $data['sc'] ?? ScreenCategory::Medium->value;

        // Validate search term
        if (empty($searchTerm)) {
            $logger->warning('Empty search term');
            return;
        }

        // Normalize and limit search term length
        $searchTerm = mb_strtolower($searchTerm);
        $searchTerm = substr($searchTerm, 0, 255);

        $logger->step('SiteSearch', 'Search parsed', [
            'searchTerm' => $searchTerm,
            'resultsCount' => $resultsCount,
        ]);

        // Generate visitor hash for unique counting
        $visitorHash = Insights::getInstance()->visitor->generateHash($userAgent, $screenCategory, $acceptLanguage, $ip);

        // Check if this is a new visitor for this search term today
        $isNew = $this->isNewSearchVisitor($visitorHash, $searchTerm, $siteId, $date);

        // Aggregate search (UPSERT)
        $logger->startTimer('upsertSearch');
        Db::upsert(Constants::TABLE_SEARCHES, [
            'siteId' => $siteId,
            'date' => $date,
            'hour' => $hour,
            'searchTerm' => $searchTerm,
            'resultsCount' => $resultsCount,
            'searches' => 1,
            'uniqueVisitors' => $isNew ? 1 : 0,
        ], [
            'searches' => new Expression('[[searches]] + 1'),
            'uniqueVisitors' => $isNew
                ? new Expression('[[uniqueVisitors]] + 1')
                : new Expression('[[uniqueVisitors]]'),
            'resultsCount' => $resultsCount,
        ]);
        $logger->stopTimer('upsertSearch');

        $logger->endFeature('SiteSearch', ['searchTerm' => $searchTerm, 'isNew' => $isNew]);
    }

    /**
     * Check if this is a new visitor for this search term today.
     *
     * Uses atomic cache->add() to prevent race conditions.
     */
    private function isNewSearchVisitor(string $hash, string $searchTerm, int $siteId, string $date): bool
    {
        $key = Constants::CACHE_VISITOR . "sr_{$siteId}_{$date}_{$hash}_" . md5($searchTerm);

        // Cache until midnight - add() is atomic and returns false if key exists
        $ttl = strtotime('tomorrow') - time();

        return Craft::$app->cache->add($key, 1, $ttl);
    }
}
