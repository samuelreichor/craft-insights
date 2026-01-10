<?php

namespace samuelreichor\insights\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use samuelreichor\insights\Constants;
use samuelreichor\insights\Insights;

/**
 * Cleanup Service
 *
 * Handles data retention and cleanup to ensure DSGVO compliance.
 */
class CleanupService extends Component
{
    /**
     * Run cleanup for all tables based on data retention settings.
     *
     * @return array{pageviews: int, referrers: int, campaigns: int, devices: int, countries: int, realtime: int, events: int, outbound: int, searches: int, scrollDepth: int, sessions: int}
     */
    public function cleanup(): array
    {
        $logger = Insights::getInstance()->logger;
        $logger->beginFeature('Cleanup');

        $settings = Insights::getInstance()->getSettings();
        $cutoffDate = date('Y-m-d', strtotime("-{$settings->dataRetentionDays} days"));

        $logger->step('Cleanup', 'Settings loaded', [
            'retentionDays' => $settings->dataRetentionDays,
            'cutoffDate' => $cutoffDate,
        ]);

        $results = [
            'pageviews' => 0,
            'referrers' => 0,
            'campaigns' => 0,
            'devices' => 0,
            'countries' => 0,
            'realtime' => 0,
            'events' => 0,
            'outbound' => 0,
            'searches' => 0,
            'scrollDepth' => 0,
            'sessions' => 0,
        ];

        // Clean pageviews
        $logger->startTimer('cleanPageviews');
        $results['pageviews'] = Craft::$app->db->createCommand()
            ->delete(Constants::TABLE_PAGEVIEWS, ['<', 'date', $cutoffDate])
            ->execute();
        $logger->stopTimer('cleanPageviews', ['deleted' => $results['pageviews']]);

        // Clean referrers
        $logger->startTimer('cleanReferrers');
        $results['referrers'] = Craft::$app->db->createCommand()
            ->delete(Constants::TABLE_REFERRERS, ['<', 'date', $cutoffDate])
            ->execute();
        $logger->stopTimer('cleanReferrers', ['deleted' => $results['referrers']]);

        // Clean campaigns
        $logger->startTimer('cleanCampaigns');
        $results['campaigns'] = Craft::$app->db->createCommand()
            ->delete(Constants::TABLE_CAMPAIGNS, ['<', 'date', $cutoffDate])
            ->execute();
        $logger->stopTimer('cleanCampaigns', ['deleted' => $results['campaigns']]);

        // Clean devices
        $logger->startTimer('cleanDevices');
        $results['devices'] = Craft::$app->db->createCommand()
            ->delete(Constants::TABLE_DEVICES, ['<', 'date', $cutoffDate])
            ->execute();
        $logger->stopTimer('cleanDevices', ['deleted' => $results['devices']]);

        // Clean countries
        $logger->startTimer('cleanCountries');
        $results['countries'] = Craft::$app->db->createCommand()
            ->delete(Constants::TABLE_COUNTRIES, ['<', 'date', $cutoffDate])
            ->execute();
        $logger->stopTimer('cleanCountries', ['deleted' => $results['countries']]);

        // Clean realtime (always clean old entries)
        $realtimeCutoff = date('Y-m-d H:i:s', strtotime("-{$settings->realtimeTtl} seconds"));
        $logger->startTimer('cleanRealtime');
        $results['realtime'] = Craft::$app->db->createCommand()
            ->delete(Constants::TABLE_REALTIME, ['<', 'lastSeen', $realtimeCutoff])
            ->execute();
        $logger->stopTimer('cleanRealtime', ['deleted' => $results['realtime']]);

        // Clean Pro tables (events, outbound, searches)
        $logger->startTimer('cleanEvents');
        $results['events'] = Craft::$app->db->createCommand()
            ->delete(Constants::TABLE_EVENTS, ['<', 'date', $cutoffDate])
            ->execute();
        $logger->stopTimer('cleanEvents', ['deleted' => $results['events']]);

        $logger->startTimer('cleanOutbound');
        $results['outbound'] = Craft::$app->db->createCommand()
            ->delete(Constants::TABLE_OUTBOUND, ['<', 'date', $cutoffDate])
            ->execute();
        $logger->stopTimer('cleanOutbound', ['deleted' => $results['outbound']]);

        $logger->startTimer('cleanSearches');
        $results['searches'] = Craft::$app->db->createCommand()
            ->delete(Constants::TABLE_SEARCHES, ['<', 'date', $cutoffDate])
            ->execute();
        $logger->stopTimer('cleanSearches', ['deleted' => $results['searches']]);

        // Clean scroll depth
        $logger->startTimer('cleanScrollDepth');
        $results['scrollDepth'] = Craft::$app->db->createCommand()
            ->delete(Constants::TABLE_SCROLL_DEPTH, ['<', 'date', $cutoffDate])
            ->execute();
        $logger->stopTimer('cleanScrollDepth', ['deleted' => $results['scrollDepth']]);

        // Clean sessions
        $logger->startTimer('cleanSessions');
        $results['sessions'] = Craft::$app->db->createCommand()
            ->delete(Constants::TABLE_SESSIONS, ['<', 'date', $cutoffDate])
            ->execute();
        $logger->stopTimer('cleanSessions', ['deleted' => $results['sessions']]);

        $total = array_sum($results);
        $logger->endFeature('Cleanup', ['totalDeleted' => $total, 'results' => $results]);

        if ($total > 0) {
            Craft::info("Insights cleanup completed. Deleted {$total} records.", 'insights');
        }

        return $results;
    }

    /**
     * Clean only realtime table (for frequent cleanup).
     */
    public function cleanupRealtime(): int
    {
        $settings = Insights::getInstance()->getSettings();
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$settings->realtimeTtl} seconds"));

        return Craft::$app->db->createCommand()
            ->delete(Constants::TABLE_REALTIME, ['<', 'lastSeen', $cutoff])
            ->execute();
    }

    /**
     * Get statistics about stored data.
     *
     * @return array{pageviews: int, referrers: int, campaigns: int, devices: int, countries: int, realtime: int, events: int, outbound: int, searches: int, scrollDepth: int, sessions: int, oldestDate: string|null, newestDate: string|null}
     */
    public function getStorageStats(): array
    {
        $stats = [
            'pageviews' => (int)(new Query())
                ->from(Constants::TABLE_PAGEVIEWS)
                ->count(),
            'referrers' => (int)(new Query())
                ->from(Constants::TABLE_REFERRERS)
                ->count(),
            'campaigns' => (int)(new Query())
                ->from(Constants::TABLE_CAMPAIGNS)
                ->count(),
            'devices' => (int)(new Query())
                ->from(Constants::TABLE_DEVICES)
                ->count(),
            'countries' => (int)(new Query())
                ->from(Constants::TABLE_COUNTRIES)
                ->count(),
            'realtime' => (int)(new Query())
                ->from(Constants::TABLE_REALTIME)
                ->count(),
            'events' => (int)(new Query())
                ->from(Constants::TABLE_EVENTS)
                ->count(),
            'outbound' => (int)(new Query())
                ->from(Constants::TABLE_OUTBOUND)
                ->count(),
            'searches' => (int)(new Query())
                ->from(Constants::TABLE_SEARCHES)
                ->count(),
            'scrollDepth' => (int)(new Query())
                ->from(Constants::TABLE_SCROLL_DEPTH)
                ->count(),
            'sessions' => (int)(new Query())
                ->from(Constants::TABLE_SESSIONS)
                ->count(),
        ];

        // Get date range
        $oldest = (new Query())
            ->select(['MIN([[date]]) as oldest'])
            ->from(Constants::TABLE_PAGEVIEWS)
            ->scalar();

        $newest = (new Query())
            ->select(['MAX([[date]]) as newest'])
            ->from(Constants::TABLE_PAGEVIEWS)
            ->scalar();

        $stats['oldestDate'] = $oldest ?: null;
        $stats['newestDate'] = $newest ?: null;

        return $stats;
    }

    /**
     * Delete all data for a specific site.
     */
    public function deleteAllDataForSite(int $siteId): int
    {
        $total = 0;

        foreach (Constants::getAllTables() as $table) {
            $total += Craft::$app->db->createCommand()
                ->delete($table, ['siteId' => $siteId])
                ->execute();
        }

        Craft::info("Deleted all Insights data for site {$siteId}. Total: {$total} records.", 'insights');

        return $total;
    }
}
