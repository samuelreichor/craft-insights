<?php

namespace samuelreichor\insights\variables;

use Craft;
use craft\helpers\Json;
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

        if (!$settings->enabled) {
            return new Markup('', 'UTF-8');
        }

        $view = Craft::$app->getView();

        // Generate site-specific action URL by combining site base path with action path
        $actionUrl = $this->getSiteActionUrl('insights/track');

        // Inject config before the tracking script loads
        $config = Json::encode(['endpoint' => $actionUrl]);
        $view->registerJs("window.insightsConfig = {$config};", $view::POS_HEAD);

        // Register the asset bundle
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

        if (!$settings->enabled) {
            return new Markup('', 'UTF-8');
        }

        $scriptPath = Craft::getAlias('@samuelreichor/insights/web/assets/src/js/insights.js');

        if ($scriptPath === false || !file_exists($scriptPath)) {
            return new Markup('', 'UTF-8');
        }

        // Generate site-specific action URL
        $actionUrl = $this->getSiteActionUrl('insights/track');
        $config = Json::encode(['endpoint' => $actionUrl]);

        $script = file_get_contents($scriptPath);
        $html = "<script>window.insightsConfig = {$config};</script>\n";
        $html .= '<script>' . $script . '</script>';

        return new Markup($html, 'UTF-8');
    }

    /**
     * Get GeoIP database info.
     * Available for all users since country data is collected for everyone.
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
     * Generate a site-specific action URL.
     *
     * Combines the current site's base path with the action path
     * so that Craft resolves the correct site context.
     */
    private function getSiteActionUrl(string $action): string
    {
        $site = Craft::$app->getSites()->getCurrentSite();
        $baseUrl = $site->getBaseUrl();

        // Parse the base URL to get the path prefix
        $parsedUrl = parse_url($baseUrl);
        $basePath = $parsedUrl['path'] ?? '';

        // Normalize: remove duplicate slashes and trailing slash
        $basePath = (string)preg_replace('#/+#', '/', $basePath);
        $basePath = rtrim($basePath, '/');

        // Build the action URL with the site's path prefix
        return $basePath . '/actions/' . $action;
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
