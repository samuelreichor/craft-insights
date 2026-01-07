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
 */
class VisitorService extends Component
{
    /**
     * Generate a daily, non-persistent visitor hash.
     *
     * This hash is DSGVO-compliant because:
     * - It changes daily (no persistent tracking)
     * - It doesn't include IP address
     * - It only uses general, non-identifying attributes
     * - It cannot be reversed to identify the user
     *
     * @param string $userAgent The user agent string
     * @param string $screenCategory Screen size category (s/m/l)
     * @param string|null $acceptLanguage The accept-language header
     */
    public function generateHash(string $userAgent, string $screenCategory = 'm', ?string $acceptLanguage = null): string
    {
        $salt = $this->getDailySalt();

        // Only coarse, non-identifying attributes
        $attributes = [
            $salt,
            date('Y-m-d'), // Only valid for today
            $this->getBrowserFamily($userAgent),
            $this->getPrimaryLanguage($acceptLanguage),
            $screenCategory, // small/medium/large
        ];

        // IMPORTANT: No IP, no exact User-Agent!
        return hash('sha256', implode('|', $attributes));
    }

    /**
     * Get the daily salt.
     *
     * The salt is regenerated every day to ensure visitor hashes
     * cannot be correlated across days.
     */
    public function getDailySalt(): string
    {
        $today = date('Y-m-d');
        $cacheKey = Constants::CACHE_DAILY_SALT . $today;

        $salt = Craft::$app->cache->get($cacheKey);
        if ($salt === false) {
            $salt = bin2hex(random_bytes(32));
            // Cache until midnight (max 24 hours)
            $ttl = strtotime('tomorrow') - time();
            Craft::$app->cache->set($cacheKey, $salt, $ttl);
        }

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
