<?php

namespace samuelreichor\insights\console\controllers;

use Craft;
use craft\console\Controller;
use samuelreichor\insights\Insights;
use yii\console\ExitCode;

/**
 * GeoIP console command.
 *
 * Usage: ./craft insights/geoip
 */
class GeoipController extends Controller
{
    /**
     * Check GeoIP database status.
     *
     * ./craft insights/geoip
     */
    public function actionIndex(): int
    {
        $info = Insights::getInstance()->geoip->getDatabaseInfo();

        $this->stdout("\nGeoIP Database Status:\n");
        $this->stdout("======================\n\n");

        if ($info['exists']) {
            $size = round(($info['size'] ?? 0) / 1024 / 1024, 2);
            $this->stdout("  Status:   Available\n");
            $this->stdout("  Path:     {$info['path']}\n");
            $this->stdout("  Size:     {$size} MB\n");
            $this->stdout("  Modified: {$info['modified']}\n\n");
        } else {
            $this->stdout("  Status: NOT FOUND\n");
            $this->stdout("  Path:   {$info['path']}\n\n");
            $this->stdout("  To enable country tracking, download the GeoLite2-Country database:\n");
            $this->stdout("  https://dev.maxmind.com/geoip/geoip2/geolite2/\n\n");
        }

        return ExitCode::OK;
    }

    /**
     * Test GeoIP lookup with an IP address.
     *
     * ./craft insights/geoip/test <ip>
     */
    public function actionTest(string $ip = '8.8.8.8'): int
    {
        $this->stdout("\nTesting GeoIP lookup for: {$ip}\n");
        $this->stdout("================================\n\n");

        $country = Insights::getInstance()->geoip->getCountry($ip);

        if ($country) {
            $variable = new \samuelreichor\insights\variables\InsightsVariable();
            $name = $variable->getCountryName($country);
            $flag = $variable->getCountryFlag($country);

            $this->stdout("  Country Code: {$country}\n");
            $this->stdout("  Country Name: {$name}\n");
            $this->stdout("  Flag:         {$flag}\n\n");
        } else {
            $this->stdout("  Could not determine country for this IP.\n");
            $this->stdout("  This may be due to:\n");
            $this->stdout("  - GeoIP database not installed\n");
            $this->stdout("  - IP not found in database\n");
            $this->stdout("  - Invalid IP address\n\n");
        }

        return ExitCode::OK;
    }

    /**
     * Show instructions for installing the GeoIP database.
     *
     * ./craft insights/geoip/install
     */
    public function actionInstall(): int
    {
        $settings = Insights::getInstance()->getSettings();
        $dbPath = Craft::getAlias($settings->geoIpDatabasePath);

        $this->stdout("\nGeoIP Database Installation:\n");
        $this->stdout("============================\n\n");

        $this->stdout("1. Create a free MaxMind account:\n");
        $this->stdout("   https://www.maxmind.com/en/geolite2/signup\n\n");

        $this->stdout("2. Download GeoLite2-Country database:\n");
        $this->stdout("   https://www.maxmind.com/en/accounts/current/geoip/downloads\n\n");

        $this->stdout("3. Extract the .mmdb file and place it at:\n");
        $this->stdout("   {$dbPath}\n\n");

        $this->stdout("4. Run './craft insights/geoip' to verify the installation.\n\n");

        $this->stdout("Note: The GeoLite2 database is updated weekly.\n");
        $this->stdout("Consider setting up automatic updates using geoipupdate.\n\n");

        return ExitCode::OK;
    }
}
