<?php

namespace samuelreichor\insights\console\controllers;

use craft\console\Controller;
use samuelreichor\insights\Insights;
use yii\console\ExitCode;

/**
 * Cleanup console command.
 *
 * Usage: ./craft insights/cleanup
 */
class CleanupController extends Controller
{
    /**
     * Run data cleanup based on retention settings.
     *
     * ./craft insights/cleanup
     */
    public function actionIndex(): int
    {
        $this->stdout("Starting Insights data cleanup...\n");

        $results = Insights::getInstance()->cleanup->cleanup();

        $total = array_sum($results);

        $this->stdout("\nCleanup completed:\n");
        $this->stdout("  - Pageviews: {$results['pageviews']} records deleted\n");
        $this->stdout("  - Referrers: {$results['referrers']} records deleted\n");
        $this->stdout("  - Campaigns: {$results['campaigns']} records deleted\n");
        $this->stdout("  - Devices: {$results['devices']} records deleted\n");
        $this->stdout("  - Countries: {$results['countries']} records deleted\n");
        $this->stdout("  - Realtime: {$results['realtime']} records deleted\n");
        $this->stdout("\nTotal: {$total} records deleted.\n");

        return ExitCode::OK;
    }

    /**
     * Show storage statistics.
     *
     * ./craft insights/cleanup/stats
     */
    public function actionStats(): int
    {
        $stats = Insights::getInstance()->cleanup->getStorageStats();
        $settings = Insights::getInstance()->getSettings();

        $this->stdout("\nInsights Storage Statistics:\n");
        $this->stdout("============================\n\n");
        $this->stdout("  Pageviews:  {$stats['pageviews']} records\n");
        $this->stdout("  Referrers:  {$stats['referrers']} records\n");
        $this->stdout("  Campaigns:  {$stats['campaigns']} records\n");
        $this->stdout("  Devices:    {$stats['devices']} records\n");
        $this->stdout("  Countries:  {$stats['countries']} records\n");
        $this->stdout("  Realtime:   {$stats['realtime']} records\n");

        if ($stats['oldestDate']) {
            $this->stdout("\n  Data range: {$stats['oldestDate']} to {$stats['newestDate']}\n");
        } else {
            $this->stdout("\n  No data stored yet.\n");
        }

        $this->stdout("\n  Data retention: {$settings->dataRetentionDays} days\n");
        $this->stdout("  Auto cleanup: " . ($settings->autoCleanup ? 'enabled' : 'disabled') . "\n\n");

        return ExitCode::OK;
    }

    /**
     * Delete all data for a specific site.
     *
     * ./craft insights/cleanup/site <siteId>
     */
    public function actionSite(int $siteId): int
    {
        $this->stdout("Are you sure you want to delete ALL Insights data for site {$siteId}? (yes/no): ");

        $input = trim(fgets(STDIN));

        if ($input !== 'yes') {
            $this->stdout("Aborted.\n");
            return ExitCode::OK;
        }

        $count = Insights::getInstance()->cleanup->deleteAllDataForSite($siteId);

        $this->stdout("\nDeleted {$count} records for site {$siteId}.\n");

        return ExitCode::OK;
    }
}
