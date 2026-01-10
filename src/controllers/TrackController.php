<?php

namespace samuelreichor\insights\controllers;

use Craft;
use craft\web\Controller;
use samuelreichor\insights\enums\EventType;
use samuelreichor\insights\Insights;
use samuelreichor\insights\jobs\ProcessTrackingJob;
use yii\web\Response;

/**
 * Track Controller
 *
 * Public API endpoint for receiving tracking data from the frontend.
 * This controller is accessible without authentication.
 */
class TrackController extends Controller
{
    /**
     * Allow anonymous access to the tracking endpoint
     */
    protected array|bool|int $allowAnonymous = ['index'];

    /**
     * Disable CSRF validation for tracking requests
     */
    public $enableCsrfValidation = false;

    /**
     * Process tracking data.
     */
    public function actionIndex(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();
        $logger = Insights::getInstance()->logger;

        $logger->debug('Track request received', [
            'method' => $request->getMethod(),
            'contentType' => $request->getContentType(),
        ]);

        // Check if tracking is enabled
        if (!$settings->enabled) {
            $logger->debug('Tracking disabled in settings');
            return $this->asJson(['status' => 'disabled']);
        }

        // Parse JSON body
        $rawBody = $request->getRawBody();
        $data = json_decode($rawBody, true);

        if (!$data) {
            $logger->warning('Invalid tracking data received', ['rawBody' => substr($rawBody, 0, 200)]);
            return $this->asJson(['status' => 'error', 'message' => 'Invalid data']);
        }

        $logger->debug('Tracking data parsed', ['type' => $data['t'] ?? 'unknown', 'url' => $data['u'] ?? '/']);

        // Bot filtering
        if ($this->isBot($request)) {
            $logger->debug('Bot detected, skipping tracking');
            return $this->asJson(['status' => 'bot']);
        }

        // Respect Do Not Track header
        if ($settings->respectDoNotTrack && $request->getHeaders()->has('DNT')) {
            $dnt = $request->getHeaders()->get('DNT');
            if ($dnt === '1') {
                $logger->debug('DNT header set, skipping tracking');
                return $this->asJson(['status' => 'dnt']);
            }
        }

        // Check for excluded paths
        $url = $data['u'] ?? '/';
        if ($this->isExcludedPath($url, $settings->excludedPaths)) {
            $logger->debug('Path excluded', ['url' => $url, 'excludedPaths' => $settings->excludedPaths]);
            return $this->asJson(['status' => 'excluded']);
        }

        // Check for excluded IP ranges (Pro feature only)
        $ip = $request->getUserIP() ?? '';
        if (Insights::getInstance()->isPro() && $this->isExcludedIp($ip, $settings->excludedIpRanges)) {
            $logger->debug('IP excluded', ['ip' => $ip]);
            return $this->asJson(['status' => 'excluded']);
        }

        // Check for logged in users
        if ($settings->excludeLoggedInUsers) {
            $user = Craft::$app->getUser()->getIdentity();
            if ($user !== null) {
                $logger->debug('Logged-in user excluded from tracking');
                return $this->asJson(['status' => 'logged_in']);
            }
        }

        // Note: All event types are processed for all users (Lite + Pro) so they
        // have historical data when upgrading. Display is restricted to Pro only.
        $eventType = EventType::tryFrom($data['t'] ?? EventType::Pageview->value);

        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        $userAgent = $request->getUserAgent() ?? '';
        $acceptLanguage = $request->getHeaders()->get('Accept-Language');

        // Process tracking (via Queue for performance or synchronously)
        if ($settings->useQueue) {
            Craft::$app->queue->push(new ProcessTrackingJob([
                'type' => $data['t'] ?? EventType::Pageview->value,
                'data' => $data,
                'userAgent' => $userAgent,
                'ip' => $ip, // Used only for GeoIP, not stored!
                'siteId' => $siteId,
                'acceptLanguage' => $acceptLanguage,
            ]));
            $logger->debug('Queue job created', ['type' => $data['t'] ?? 'pv', 'siteId' => $siteId]);
        } else {
            // Process synchronously for simpler setups
            $logger->debug('Processing synchronously', ['type' => $data['t'] ?? 'pv']);
            $tracking = Insights::getInstance()->tracking;

            try {
                match ($eventType) {
                    EventType::Pageview => $tracking->processPageview($data, $userAgent, $ip, $siteId, $acceptLanguage),
                    EventType::Engagement => $tracking->processEngagement($data, $siteId),
                    EventType::Leave => $tracking->processLeave($data, $siteId),
                    EventType::Event => $tracking->processEvent($data, $userAgent, $ip, $siteId),
                    EventType::Outbound => $tracking->processOutbound($data, $userAgent, $ip, $siteId),
                    EventType::Search => $tracking->processSearch($data, $userAgent, $ip, $siteId),
                    EventType::ScrollDepth => $tracking->processScrollDepth($data, $userAgent, $ip, $siteId),
                    default => null,
                };
                $logger->debug('Synchronous processing completed');
            } catch (\Throwable $e) {
                $logger->error("Tracking error: {$e->getMessage()}", ['exception' => $e->getTraceAsString()]);
            }
        }

        return $this->asJson(['status' => 'ok']);
    }

    /**
     * Check if the request is from a bot.
     */
    private function isBot(\craft\web\Request $request): bool
    {
        $ua = strtolower($request->getUserAgent() ?? '');

        $bots = [
            'bot',
            'crawl',
            'spider',
            'slurp',
            'lighthouse',
            'pagespeed',
            'gtmetrix',
            'pingdom',
            'uptimerobot',
            'headless',
            'phantom',
            'selenium',
            'puppeteer',
            'wget',
            'curl',
        ];

        foreach ($bots as $bot) {
            if (str_contains($ua, $bot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if URL is in excluded paths.
     *
     * @param string[] $excludedPaths
     */
    private function isExcludedPath(string $url, array $excludedPaths): bool
    {
        foreach ($excludedPaths as $path) {
            if (str_starts_with($url, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is in excluded ranges.
     *
     * @param string[] $excludedRanges
     */
    private function isExcludedIp(string $ip, array $excludedRanges): bool
    {
        if (empty($ip) || empty($excludedRanges)) {
            return false;
        }

        foreach ($excludedRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is in CIDR range.
     */
    private function ipInRange(string $ip, string $range): bool
    {
        // Simple IP match
        if (!str_contains($range, '/')) {
            return $ip === $range;
        }

        // CIDR match
        [$subnet, $bits] = explode('/', $range);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip = ip2long($ip);
            $subnet = ip2long($subnet);
            $mask = -1 << (32 - (int)$bits);
            $subnet &= $mask;
            return ($ip & $mask) === $subnet;
        }

        return false;
    }
}
