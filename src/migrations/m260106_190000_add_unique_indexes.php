<?php

namespace samuelreichor\insights\migrations;

use craft\db\Migration;

/**
 * m260106_190000_add_unique_indexes migration.
 *
 * Adds unique indexes required for UPSERT operations to work correctly.
 */
class m260106_190000_add_unique_indexes extends Migration
{
    public function safeUp(): bool
    {
        // First, clean up duplicate rows by keeping only the one with highest values
        $this->cleanupDuplicates();

        // Create unique indexes for UPSERT operations
        // Using raw SQL to handle "IF NOT EXISTS" properly
        $this->execute("
            CREATE UNIQUE INDEX idx_pageviews_unique
            ON " . $this->db->tablePrefix . "insights_pageviews (siteId, date, hour, url)
        ");

        $this->execute("
            CREATE UNIQUE INDEX idx_referrers_unique
            ON " . $this->db->tablePrefix . "insights_referrers (siteId, date, referrerDomain, referrerType)
        ");

        $this->execute("
            CREATE UNIQUE INDEX idx_campaigns_unique
            ON " . $this->db->tablePrefix . "insights_campaigns (siteId, date, utmSource, utmMedium, utmCampaign)
        ");

        $this->execute("
            CREATE UNIQUE INDEX idx_devices_unique
            ON " . $this->db->tablePrefix . "insights_devices (siteId, date, deviceType, browserFamily, osFamily)
        ");

        $this->execute("
            CREATE UNIQUE INDEX idx_countries_unique
            ON " . $this->db->tablePrefix . "insights_countries (siteId, date, countryCode)
        ");

        return true;
    }

    public function safeDown(): bool
    {
        $this->execute("DROP INDEX idx_pageviews_unique ON " . $this->db->tablePrefix . "insights_pageviews");
        $this->execute("DROP INDEX idx_referrers_unique ON " . $this->db->tablePrefix . "insights_referrers");
        $this->execute("DROP INDEX idx_campaigns_unique ON " . $this->db->tablePrefix . "insights_campaigns");
        $this->execute("DROP INDEX idx_devices_unique ON " . $this->db->tablePrefix . "insights_devices");
        $this->execute("DROP INDEX idx_countries_unique ON " . $this->db->tablePrefix . "insights_countries");

        return true;
    }

    /**
     * Clean up duplicate rows before adding unique constraints.
     */
    private function cleanupDuplicates(): void
    {
        $prefix = $this->db->tablePrefix;

        // Pageviews: Keep row with highest ID, aggregate values into it, delete others
        $this->execute("
            DELETE p1 FROM {$prefix}insights_pageviews p1
            INNER JOIN {$prefix}insights_pageviews p2
            WHERE p1.id < p2.id
            AND p1.siteId = p2.siteId
            AND p1.date = p2.date
            AND COALESCE(p1.hour, 0) = COALESCE(p2.hour, 0)
            AND p1.url = p2.url
        ");

        // Referrers: Remove duplicates keeping the one with highest ID
        $this->execute("
            DELETE p1 FROM {$prefix}insights_referrers p1
            INNER JOIN {$prefix}insights_referrers p2
            WHERE p1.id < p2.id
            AND p1.siteId = p2.siteId
            AND p1.date = p2.date
            AND COALESCE(p1.referrerDomain, '') = COALESCE(p2.referrerDomain, '')
            AND p1.referrerType = p2.referrerType
        ");

        // Campaigns: Remove duplicates
        $this->execute("
            DELETE p1 FROM {$prefix}insights_campaigns p1
            INNER JOIN {$prefix}insights_campaigns p2
            WHERE p1.id < p2.id
            AND p1.siteId = p2.siteId
            AND p1.date = p2.date
            AND COALESCE(p1.utmSource, '') = COALESCE(p2.utmSource, '')
            AND COALESCE(p1.utmMedium, '') = COALESCE(p2.utmMedium, '')
            AND COALESCE(p1.utmCampaign, '') = COALESCE(p2.utmCampaign, '')
        ");

        // Devices: Remove duplicates
        $this->execute("
            DELETE p1 FROM {$prefix}insights_devices p1
            INNER JOIN {$prefix}insights_devices p2
            WHERE p1.id < p2.id
            AND p1.siteId = p2.siteId
            AND p1.date = p2.date
            AND p1.deviceType = p2.deviceType
            AND COALESCE(p1.browserFamily, '') = COALESCE(p2.browserFamily, '')
            AND COALESCE(p1.osFamily, '') = COALESCE(p2.osFamily, '')
        ");

        // Countries: Remove duplicates
        $this->execute("
            DELETE p1 FROM {$prefix}insights_countries p1
            INNER JOIN {$prefix}insights_countries p2
            WHERE p1.id < p2.id
            AND p1.siteId = p2.siteId
            AND p1.date = p2.date
            AND p1.countryCode = p2.countryCode
        ");
    }
}
