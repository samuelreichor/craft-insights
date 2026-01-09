<?php

namespace samuelreichor\insights\services;

use Craft;
use craft\base\Component;
use samuelreichor\insights\Constants;
use samuelreichor\insights\enums\ScreenCategory;
use WhichBrowser\Parser;

/**
 * Visitor Service
 *
 * Generates DSGVO-compliant daily visitor hashes without storing any PII.
 * The hash is based on non-identifying attributes and changes daily.
 *
 * Uses the same approach as Plausible and Fathom Analytics:
 * - IP address used transiently for hash generation only
 * - Website domain prevents cross-site tracking
 * - Daily salt rotation prevents cross-day correlation
 */
class VisitorService extends Component
{
    /**
     * Generate a daily, non-persistent visitor hash.
     *
     * This hash is GDPR/DSGVO-compliant because:
     * - IP address is used transiently and NEVER stored
     * - SHA256 is irreversible - no PII can be recovered
     * - It changes daily (no persistent tracking)
     * - Website domain prevents cross-site correlation
     *
     * Formula: SHA256(daily_salt | domain | ip | user_agent)
     * This matches the approach used by Plausible and Fathom.
     *
     * @param string $userAgent The user agent string
     * @param string $ip IP address (used transiently for hash, NOT stored)
     * @param int $siteId Site ID to extract domain
     */
    public function generateHash(string $userAgent, string $ip, int $siteId): string
    {
        $salt = $this->getDailySalt();

        // Extract domain from site's base URL (prevents cross-site tracking)
        $site = Craft::$app->getSites()->getSiteById($siteId);
        $domain = parse_url($site?->getBaseUrl() ?? '', PHP_URL_HOST) ?? 'localhost';

        // Plausible/Fathom-style hash
        // IP is used ONLY for hash generation and is NEVER stored
        $attributes = [
            $salt,
            $domain,
            $ip,
            $userAgent,
        ];

        return hash('sha256', implode('|', $attributes));
    }

    /**
     * Get the daily salt.
     *
     * The salt changes daily to ensure visitor hashes cannot be correlated across days.
     * Uses deterministic generation based on Craft's security key to ensure consistency
     * even if the cache is cleared mid-day (e.g., server restart).
     */
    public function getDailySalt(): string
    {
        $today = date('Y-m-d');
        $cacheKey = Constants::CACHE_DAILY_SALT . $today;

        $salt = Craft::$app->cache->get($cacheKey);
        if ($salt !== false) {
            return $salt;
        }

        // Generate deterministic salt based on date + Craft's security key
        // This ensures the same salt is generated even after cache clear
        $salt = hash('sha256', Craft::$app->security->hashData('insights_daily_salt_' . $today));

        // Cache until midnight for performance
        $ttl = strtotime('tomorrow') - time();
        Craft::$app->cache->set($cacheKey, $salt, $ttl);

        return $salt;
    }

    /**
     * Extract browser family from user agent.
     *
     * Returns only the browser family name, not version or specific details.
     */
    public function getBrowserFamily(string $userAgent): string
    {
        try {
            $parser = new Parser($userAgent);
            return $parser->browser->name ?: Constants::DEFAULT_UNKNOWN;
        } catch (\Throwable) {
            return Constants::DEFAULT_UNKNOWN;
        }
    }

    /**
     * Extract the primary language from Accept-Language header.
     */
    public function getPrimaryLanguage(?string $acceptLanguage): string
    {
        if (empty($acceptLanguage)) {
            return 'en';
        }

        // Get first language preference
        $languages = explode(',', $acceptLanguage);
        $primary = $languages[0] ?? 'en';

        // Extract just the language code (e.g., "de" from "de-DE")
        $parts = explode('-', trim(explode(';', $primary)[0]));
        return strtolower($parts[0]) ?: 'en';
    }

    /**
     * Parse the screen category from width.
     */
    public function parseScreenCategory(int $width): ScreenCategory
    {
        return ScreenCategory::fromWidth($width);
    }
}
