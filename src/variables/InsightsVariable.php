<?php

namespace samuelreichor\insights\variables;

use Craft;
use samuelreichor\insights\Insights;
use samuelreichor\insights\web\assets\TrackingAsset;
use Twig\Markup;

/**
 * Insights Variable
 *
 * Provides Twig functions for template integration.
 */
class InsightsVariable
{
    /**
     * Output the tracking script tag.
     *
     * Usage: {{ craft.insights.trackingScript() }}
     */
    public function trackingScript(): Markup
    {
        $settings = Insights::getInstance()->getSettings();

        if (!$settings->enabled || !$settings->trackPageviews) {
            return new Markup('', 'UTF-8');
        }

        // Register the asset bundle
        $view = Craft::$app->getView();
        $view->registerAssetBundle(TrackingAsset::class);

        return new Markup('', 'UTF-8');
    }

    /**
     * Get tracking script as inline HTML.
     *
     * Useful when you can't use asset bundles.
     */
    public function trackingScriptInline(): Markup
    {
        $settings = Insights::getInstance()->getSettings();

        if (!$settings->enabled || !$settings->trackPageviews) {
            return new Markup('', 'UTF-8');
        }

        $scriptPath = Craft::getAlias('@samuelreichor/insights/web/assets/src/js/insights.js');

        if ($scriptPath === false || !file_exists($scriptPath)) {
            return new Markup('', 'UTF-8');
        }

        $script = file_get_contents($scriptPath);
        $html = '<script>' . $script . '</script>';

        return new Markup($html, 'UTF-8');
    }

    /**
     * Get entry stats.
     *
     * Usage: {{ craft.insights.getEntryStats(entry.id, '30d') }}
     *
     * @return array{views: int, uniqueVisitors: int, avgTime: float, bounceRate: float}
     */
    public function getEntryStats(int $entryId, string $range = '30d'): array
    {
        return Insights::getInstance()->stats->getEntryStats($entryId, $range);
    }

    /**
     * Get GeoIP database info.
     *
     * @return array{exists: bool, path: string|null, size: int|null, modified: string|null}
     */
    public function getGeoIpDatabaseInfo(): array
    {
        return Insights::getInstance()->geoip->getDatabaseInfo();
    }

    /**
     * Get storage statistics.
     *
     * @return array{pageviews: int, referrers: int, campaigns: int, devices: int, countries: int, realtime: int, oldestDate: string|null, newestDate: string|null}
     */
    public function getStorageStats(): array
    {
        return Insights::getInstance()->cleanup->getStorageStats();
    }

    /**
     * Get country name from ISO code.
     */
    public function getCountryName(string $code): string
    {
        $countries = $this->getCountryList();
        return $countries[$code] ?? $code;
    }

    /**
     * Get country flag emoji from ISO code.
     */
    public function getCountryFlag(string $code): string
    {
        $code = strtoupper($code);
        if (strlen($code) !== 2) {
            return '';
        }

        // Convert country code to regional indicator symbols (flag emoji)
        $flagOffset = 0x1F1E6 - ord('A');
        $flag = mb_chr(ord($code[0]) + $flagOffset) . mb_chr(ord($code[1]) + $flagOffset);

        return $flag;
    }

    /**
     * Check if tracking is enabled.
     */
    public function isEnabled(): bool
    {
        return Insights::getInstance()->getSettings()->enabled;
    }

    /**
     * Get the current realtime visitor count.
     */
    public function getRealtimeCount(?int $siteId = null): int
    {
        if ($siteId === null) {
            $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        }

        $realtime = Insights::getInstance()->stats->getRealtimeVisitors($siteId);
        return $realtime['count'];
    }

    /**
     * Get summary stats for the current site.
     *
     * @return array{pageviews: int, uniqueVisitors: int, bounceRate: float, avgTimeOnPage: float, pageviewsTrend: float, visitorsTrend: float}
     */
    public function getSummary(string $range = '30d', ?int $siteId = null): array
    {
        if ($siteId === null) {
            $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        }

        return Insights::getInstance()->stats->getSummary($siteId, $range);
    }

    /**
     * Get country list for display.
     *
     * @return array<string, string>
     */
    private function getCountryList(): array
    {
        return [
            'AF' => 'Afghanistan',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AS' => 'American Samoa',
            'AD' => 'Andorra',
            'AO' => 'Angola',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BS' => 'Bahamas',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'BZ' => 'Belize',
            'BJ' => 'Benin',
            'BT' => 'Bhutan',
            'BO' => 'Bolivia',
            'BA' => 'Bosnia and Herzegovina',
            'BW' => 'Botswana',
            'BR' => 'Brazil',
            'BN' => 'Brunei',
            'BG' => 'Bulgaria',
            'KH' => 'Cambodia',
            'CM' => 'Cameroon',
            'CA' => 'Canada',
            'CL' => 'Chile',
            'CN' => 'China',
            'CO' => 'Colombia',
            'CR' => 'Costa Rica',
            'HR' => 'Croatia',
            'CU' => 'Cuba',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DK' => 'Denmark',
            'DO' => 'Dominican Republic',
            'EC' => 'Ecuador',
            'EG' => 'Egypt',
            'SV' => 'El Salvador',
            'EE' => 'Estonia',
            'ET' => 'Ethiopia',
            'FI' => 'Finland',
            'FR' => 'France',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GH' => 'Ghana',
            'GR' => 'Greece',
            'GT' => 'Guatemala',
            'HN' => 'Honduras',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IR' => 'Iran',
            'IQ' => 'Iraq',
            'IE' => 'Ireland',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'JP' => 'Japan',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KE' => 'Kenya',
            'KR' => 'South Korea',
            'KW' => 'Kuwait',
            'LV' => 'Latvia',
            'LB' => 'Lebanon',
            'LY' => 'Libya',
            'LI' => 'Liechtenstein',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MO' => 'Macau',
            'MK' => 'North Macedonia',
            'MY' => 'Malaysia',
            'MT' => 'Malta',
            'MX' => 'Mexico',
            'MD' => 'Moldova',
            'MC' => 'Monaco',
            'MN' => 'Mongolia',
            'ME' => 'Montenegro',
            'MA' => 'Morocco',
            'NP' => 'Nepal',
            'NL' => 'Netherlands',
            'NZ' => 'New Zealand',
            'NI' => 'Nicaragua',
            'NG' => 'Nigeria',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PA' => 'Panama',
            'PY' => 'Paraguay',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'PR' => 'Puerto Rico',
            'QA' => 'Qatar',
            'RO' => 'Romania',
            'RU' => 'Russia',
            'SA' => 'Saudi Arabia',
            'SN' => 'Senegal',
            'RS' => 'Serbia',
            'SG' => 'Singapore',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'ZA' => 'South Africa',
            'ES' => 'Spain',
            'LK' => 'Sri Lanka',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'TW' => 'Taiwan',
            'TH' => 'Thailand',
            'TN' => 'Tunisia',
            'TR' => 'Turkey',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VE' => 'Venezuela',
            'VN' => 'Vietnam',
            'YE' => 'Yemen',
            'ZW' => 'Zimbabwe',
        ];
    }
}
