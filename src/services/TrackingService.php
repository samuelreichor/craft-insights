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
use samuelreichor\insights\enums\ScrollDepthMilestone;
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
     * Add timestamp fields for external database compatibility.
     *
     * Craft's internal DB handles these automatically via behaviors,
     * but external databases need them explicitly.
     *
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

        // Generate visitor hash (Plausible/Fathom-style with IP)
        $logger->startTimer('visitorHash');
        $visitorHash = Insights::getInstance()->visitor->generateHash($userAgent, $ip, $siteId);
        $logger->stopTimer('visitorHash');

        // Find entry ID for this URL
        $logger->startTimer('findEntry');
        $entryId = $this->findEntryByUrl($url, $siteId);
        $logger->stopTimer('findEntry', ['entryId' => $entryId]);

        // Check if this is a new visitor for this URL today
        $isNew = $this->isNewVisitorFor('pv', $visitorHash, $url, $siteId, $date);
        $logger->step('Pageview', 'Visitor check', ['isNew' => $isNew]);

        // Aggregate pageview (UPSERT)
        // First array = INSERT values (used when row doesn't exist)
        // Second array = UPDATE values (used when row exists)
        $logger->startTimer('upsertPageview');
        $db = Insights::getInstance()->database->getConnection();
        Db::upsert(Constants::TABLE_PAGEVIEWS, $this->withTimestamps([
            'siteId' => $siteId,
            'date' => $date,
            'hour' => $hour,
            'url' => $url,
            'entryId' => $entryId,
            'views' => 1,
            'uniqueVisitors' => $isNew ? 1 : 0,
            'bounces' => $isNew ? 1 : 0,
        ]), [
            'views' => new Expression('[[views]] + 1'),
            'uniqueVisitors' => $isNew
                ? new Expression('[[uniqueVisitors]] + 1')
                : new Expression('[[uniqueVisitors]]'),
            'bounces' => $isNew
                ? new Expression('[[bounces]] + 1')
                : new Expression('[[bounces]]'),
        ], [], true, $db);
        $logger->stopTimer('upsertPageview');

        // Track referrer
        if (!empty($data['r'])) {
            $logger->startTimer('trackReferrer');
            $this->trackReferrer($data['r'], $siteId, $date);
            $logger->stopTimer('trackReferrer', ['domain' => $data['r']]);
        }

        // Track UTM parameters (collected for all users, displayed in Pro only)
        if (!empty($data['utm']['s'])) {
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

        // Track session (for pages per session, entry/exit pages)
        // Sessions are tracked server-side using visitor hash + 30-minute timeout
        $logger->startTimer('trackSession');
        $this->trackSession($url, $entryId, $visitorHash, $siteId);
        $logger->stopTimer('trackSession');

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
        $db = Insights::getInstance()->database->getConnection();
        $db->createCommand()
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
            $db = Insights::getInstance()->database->getConnection();
            $db->createCommand()
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
     * Check if this is a new visitor for the given type and identifier today.
     *
     * Uses atomic cache->add() to prevent race conditions where concurrent
     * requests could both return true before either sets the key.
     *
     * @param string $type Type prefix for cache key (e.g., 'pv', 'ev', 'ob', 'sr', 'sd')
     * @param string $hash Visitor hash
     * @param string $identifier Unique identifier (URL, event name, etc.)
     * @param int $siteId Site ID
     * @param string $date Date string (Y-m-d)
     */
    private function isNewVisitorFor(string $type, string $hash, string $identifier, int $siteId, string $date): bool
    {
        $key = Constants::CACHE_VISITOR . "{$type}_{$siteId}_{$date}_{$hash}_" . md5($identifier);

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
        $db = Insights::getInstance()->database->getConnection();

        Db::upsert(Constants::TABLE_REFERRERS, $this->withTimestamps([
            'siteId' => $siteId,
            'date' => $date,
            'referrerDomain' => substr($domain, 0, 255),
            'referrerType' => $type->value,
            'visits' => 1,
        ]), [
            'visits' => new Expression('[[visits]] + 1'),
        ], [], true, $db);
    }

    /**
     * Track UTM campaign.
     *
     * @param array<string, string|null> $utm UTM parameters
     */
    private function trackCampaign(array $utm, int $siteId, string $date): void
    {
        $db = Insights::getInstance()->database->getConnection();

        Db::upsert(Constants::TABLE_CAMPAIGNS, $this->withTimestamps([
            'siteId' => $siteId,
            'date' => $date,
            'utmSource' => !empty($utm['s']) ? substr($utm['s'], 0, 100) : null,
            'utmMedium' => !empty($utm['m']) ? substr($utm['m'], 0, 100) : null,
            'utmCampaign' => !empty($utm['c']) ? substr($utm['c'], 0, 100) : null,
            'utmTerm' => !empty($utm['t']) ? substr($utm['t'], 0, 100) : null,
            'utmContent' => !empty($utm['n']) ? substr($utm['n'], 0, 100) : null,
            'visits' => 1,
        ]), [
            'visits' => new Expression('[[visits]] + 1'),
        ], [], true, $db);
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
            $db = Insights::getInstance()->database->getConnection();

            Db::upsert(Constants::TABLE_DEVICES, $this->withTimestamps([
                'siteId' => $siteId,
                'date' => $date,
                'deviceType' => $deviceType->value,
                'browserFamily' => substr($browserFamily, 0, 50),
                'osFamily' => substr($osFamily, 0, 50),
                'visits' => 1,
            ]), [
                'visits' => new Expression('[[visits]] + 1'),
            ], [], true, $db);
        } catch (\Throwable) {
            // Silently ignore parsing errors
        }
    }

    /**
     * Track country.
     */
    private function trackCountry(string $countryCode, int $siteId, string $date): void
    {
        $db = Insights::getInstance()->database->getConnection();

        Db::upsert(Constants::TABLE_COUNTRIES, $this->withTimestamps([
            'siteId' => $siteId,
            'date' => $date,
            'countryCode' => $countryCode,
            'visits' => 1,
        ]), [
            'visits' => new Expression('[[visits]] + 1'),
        ], [], true, $db);
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
        $db = Insights::getInstance()->database->getConnection();

        // Throttle cleanup to once every 60 seconds using atomic add()
        $cleanupKey = Constants::CACHE_VISITOR . 'realtime_cleanup';
        if (Craft::$app->cache->add($cleanupKey, 1, 60)) {
            // We got the lock, perform cleanup
            $cutoff = date('Y-m-d H:i:s', strtotime("-{$settings->realtimeTtl} seconds"));
            $db->createCommand()
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
        ], [], true, $db);
    }

    /**
     * Process a custom event.
     *
     * Data is collected for all users (Lite + Pro) so they have historical
     * data when upgrading. Display is restricted to Pro only.
     *
     * @param array<string, mixed> $data Tracking data from frontend
     * @param string $userAgent User agent string
     * @param string $ip IP address (used transiently for hash, NOT stored)
     * @param int $siteId Site ID
     */
    public function processEvent(array $data, string $userAgent, string $ip, int $siteId): void
    {
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

        // Generate visitor hash for unique counting (Plausible/Fathom-style)
        $visitorHash = Insights::getInstance()->visitor->generateHash($userAgent, $ip, $siteId);

        // Check if this is a new visitor for this event today
        $isNew = $this->isNewVisitorFor('ev', $visitorHash, $eventName, $siteId, $date);

        // Aggregate event (UPSERT)
        $logger->startTimer('upsertEvent');
        $db = Insights::getInstance()->database->getConnection();
        Db::upsert(Constants::TABLE_EVENTS, $this->withTimestamps([
            'siteId' => $siteId,
            'date' => $date,
            'hour' => $hour,
            'eventName' => $eventName,
            'eventCategory' => $eventCategory,
            'url' => $url,
            'count' => 1,
            'uniqueVisitors' => $isNew ? 1 : 0,
        ]), [
            'count' => new Expression('[[count]] + 1'),
            'uniqueVisitors' => $isNew
                ? new Expression('[[uniqueVisitors]] + 1')
                : new Expression('[[uniqueVisitors]]'),
        ], [], true, $db);
        $logger->stopTimer('upsertEvent');

        $logger->endFeature('CustomEvent', ['name' => $eventName, 'isNew' => $isNew]);
    }

    /**
     * Process an outbound link click.
     *
     * Data is collected for all users (Lite + Pro) so they have historical
     * data when upgrading. Display is restricted to Pro only.
     *
     * @param array<string, mixed> $data Tracking data from frontend
     * @param string $userAgent User agent string
     * @param string $ip IP address (used transiently for hash, NOT stored)
     * @param int $siteId Site ID
     */
    public function processOutbound(array $data, string $userAgent, string $ip, int $siteId): void
    {
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

        // Generate visitor hash for unique counting (Plausible/Fathom-style)
        $visitorHash = Insights::getInstance()->visitor->generateHash($userAgent, $ip, $siteId);

        // Check if this is a new visitor for this outbound link today
        $isNew = $this->isNewVisitorFor('ob', $visitorHash, $targetUrl, $siteId, $date);

        // Generate URL hash for unique constraint (to avoid MySQL key length limits)
        $urlHash = md5($targetUrl . $sourceUrl);

        // Aggregate outbound click (UPSERT)
        $logger->startTimer('upsertOutbound');
        $db = Insights::getInstance()->database->getConnection();
        Db::upsert(Constants::TABLE_OUTBOUND, $this->withTimestamps([
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
        ]), [
            'clicks' => new Expression('[[clicks]] + 1'),
            'uniqueVisitors' => $isNew
                ? new Expression('[[uniqueVisitors]] + 1')
                : new Expression('[[uniqueVisitors]]'),
            'linkText' => $linkText,
        ], [], true, $db);
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
     * Process a site search.
     *
     * Data is collected for all users (Lite + Pro) so they have historical
     * data when upgrading. Display is restricted to Pro only.
     *
     * @param array<string, mixed> $data Tracking data from frontend
     * @param string $userAgent User agent string
     * @param string $ip IP address (used transiently for hash, NOT stored)
     * @param int $siteId Site ID
     */
    public function processSearch(array $data, string $userAgent, string $ip, int $siteId): void
    {
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

        // Generate visitor hash for unique counting (Plausible/Fathom-style)
        $visitorHash = Insights::getInstance()->visitor->generateHash($userAgent, $ip, $siteId);

        // Check if this is a new visitor for this search term today
        $isNew = $this->isNewVisitorFor('sr', $visitorHash, $searchTerm, $siteId, $date);

        // Aggregate search (UPSERT)
        $logger->startTimer('upsertSearch');
        $db = Insights::getInstance()->database->getConnection();
        Db::upsert(Constants::TABLE_SEARCHES, $this->withTimestamps([
            'siteId' => $siteId,
            'date' => $date,
            'hour' => $hour,
            'searchTerm' => $searchTerm,
            'resultsCount' => $resultsCount,
            'searches' => 1,
            'uniqueVisitors' => $isNew ? 1 : 0,
        ]), [
            'searches' => new Expression('[[searches]] + 1'),
            'uniqueVisitors' => $isNew
                ? new Expression('[[uniqueVisitors]] + 1')
                : new Expression('[[uniqueVisitors]]'),
            'resultsCount' => $resultsCount,
        ], [], true, $db);
        $logger->stopTimer('upsertSearch');

        $logger->endFeature('SiteSearch', ['searchTerm' => $searchTerm, 'isNew' => $isNew]);
    }

    /**
     * Process a scroll depth event (Pro feature).
     *
     * Tracks scroll milestones at 25%, 50%, 75%, and 100%.
     * Data is collected for all users but display is Pro-only.
     *
     * @param array<string, mixed> $data Tracking data from frontend
     * @param string $userAgent User agent string
     * @param string $ip IP address (used transiently for hash, NOT stored)
     * @param int $siteId Site ID
     */
    public function processScrollDepth(array $data, string $userAgent, string $ip, int $siteId): void
    {
        $logger = Insights::getInstance()->logger;
        $logger->beginFeature('ScrollDepth', ['siteId' => $siteId]);

        $date = date('Y-m-d');
        $hour = (int)date('H');
        $url = $this->sanitizeUrl($data['u'] ?? Constants::DEFAULT_PATH);
        $percent = (int)($data['depth'] ?? 0);

        // Validate scroll depth percentage
        if ($percent < 25 || $percent > 100) {
            $logger->debug('Invalid scroll depth percentage', ['percent' => $percent]);
            return;
        }

        $milestone = ScrollDepthMilestone::fromPercent($percent);
        if (!$milestone) {
            $logger->debug('No milestone matched for percent', ['percent' => $percent]);
            return;
        }

        $logger->step('ScrollDepth', 'Processing', [
            'url' => $url,
            'percent' => $percent,
            'milestone' => $milestone->label(),
        ]);

        // Generate visitor hash
        $visitorHash = Insights::getInstance()->visitor->generateHash($userAgent, $ip, $siteId);

        // Check if this visitor already reached this milestone today
        if (!$this->isNewVisitorFor("sd_{$milestone->value}", $visitorHash, $url, $siteId, $date)) {
            $logger->debug('Milestone already recorded for this visitor');
            return;
        }

        // Find entry ID for this URL
        $entryId = $this->findEntryByUrl($url, $siteId);

        // Build UPSERT data - increment the specific milestone column
        $column = $milestone->column();

        $logger->startTimer('upsertScrollDepth');
        $db = Insights::getInstance()->database->getConnection();
        Db::upsert(Constants::TABLE_SCROLL_DEPTH, $this->withTimestamps([
            'siteId' => $siteId,
            'date' => $date,
            'hour' => $hour,
            'url' => $url,
            'entryId' => $entryId,
            'milestone25' => $milestone === ScrollDepthMilestone::Percent25 ? 1 : 0,
            'milestone50' => $milestone === ScrollDepthMilestone::Percent50 ? 1 : 0,
            'milestone75' => $milestone === ScrollDepthMilestone::Percent75 ? 1 : 0,
            'milestone100' => $milestone === ScrollDepthMilestone::Percent100 ? 1 : 0,
        ]), [
            $column => new Expression("[[{$column}]] + 1"),
        ], [], true, $db);
        $logger->stopTimer('upsertScrollDepth');

        $logger->endFeature('ScrollDepth', ['url' => $url, 'milestone' => $milestone->label()]);
    }

    /**
     * Track or update a visitor's session (server-side, Plausible-style).
     *
     * Sessions are detected purely server-side using visitor hash + 30-minute timeout.
     * No client-side storage (cookies, sessionStorage) is used.
     *
     * Logic:
     * - Find most recent session for this visitor on this site
     * - If session exists AND last activity < 30 minutes ago → update session
     * - If no session OR timed out → create new session
     *
     * @param string $url Current page URL
     * @param int|null $entryId Entry ID for the URL (if resolved)
     * @param string $visitorHash Visitor hash (already generated in processPageview)
     * @param int $siteId Site ID
     */
    public function trackSession(string $url, ?int $entryId, string $visitorHash, int $siteId): void
    {
        $logger = Insights::getInstance()->logger;
        $logger->beginFeature('Session', ['siteId' => $siteId, 'visitorHash' => substr($visitorHash, 0, 8) . '...']);

        $now = date('Y-m-d H:i:s');
        $date = date('Y-m-d');
        $timeout = Constants::SESSION_TIMEOUT;
        $cutoffTime = date('Y-m-d H:i:s', time() - $timeout);

        // Find most recent active session for this visitor (within timeout window)
        $logger->startTimer('findSession');
        $db = Insights::getInstance()->database->getConnection();
        $existingSession = (new \craft\db\Query())
            ->select(['id', 'sessionId', 'pageCount', 'entryUrl', 'entryEntryId', 'lastActivityTime'])
            ->from(Constants::TABLE_SESSIONS)
            ->where([
                'siteId' => $siteId,
                'visitorHash' => $visitorHash,
            ])
            ->andWhere(['>=', 'lastActivityTime', $cutoffTime])
            ->orderBy(['lastActivityTime' => SORT_DESC])
            ->one($db);
        $logger->stopTimer('findSession');

        if ($existingSession) {
            // Session is still active - update it
            $logger->startTimer('updateSession');
            $db->createCommand()
                ->update(Constants::TABLE_SESSIONS, [
                    'pageCount' => new Expression('[[pageCount]] + 1'),
                    'exitUrl' => $url,
                    'exitEntryId' => $entryId,
                    'lastActivityTime' => $now,
                ], ['id' => $existingSession['id']])
                ->execute();
            $logger->stopTimer('updateSession');

            $logger->step('Session', 'Updated existing session', [
                'sessionId' => $existingSession['sessionId'],
                'pageCount' => $existingSession['pageCount'] + 1,
            ]);
        } else {
            // No active session found - create new one
            $this->createNewSession($siteId, $visitorHash, $url, $entryId, $date, $now);
        }

        $logger->endFeature('Session', ['url' => $url]);
    }

    /**
     * Create a new session record with server-generated session ID.
     */
    private function createNewSession(
        int $siteId,
        string $visitorHash,
        string $entryUrl,
        ?int $entryId,
        string $date,
        string $now,
    ): void {
        $logger = Insights::getInstance()->logger;
        $logger->startTimer('createSession');

        // Generate a unique session ID server-side
        $sessionId = bin2hex(random_bytes(16));
        $db = Insights::getInstance()->database->getConnection();

        $db->createCommand()->insert(Constants::TABLE_SESSIONS, $this->withTimestamps([
            'siteId' => $siteId,
            'date' => $date,
            'visitorHash' => $visitorHash,
            'sessionId' => $sessionId,
            'pageCount' => 1,
            'entryUrl' => $entryUrl,
            'entryEntryId' => $entryId,
            'exitUrl' => $entryUrl,
            'exitEntryId' => $entryId,
            'startTime' => $now,
            'lastActivityTime' => $now,
        ]))->execute();

        $logger->stopTimer('createSession');
        $logger->step('Session', 'Created new session', ['sessionId' => $sessionId]);
    }
}
