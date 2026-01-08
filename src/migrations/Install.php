<?php

namespace samuelreichor\insights\migrations;

use craft\db\Migration;
use samuelreichor\insights\Constants;

/**
 * Install migration.
 */
class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(Constants::TABLE_SEARCHES);
        $this->dropTableIfExists(Constants::TABLE_OUTBOUND);
        $this->dropTableIfExists(Constants::TABLE_EVENTS);
        $this->dropTableIfExists(Constants::TABLE_REALTIME);
        $this->dropTableIfExists(Constants::TABLE_COUNTRIES);
        $this->dropTableIfExists(Constants::TABLE_DEVICES);
        $this->dropTableIfExists(Constants::TABLE_CAMPAIGNS);
        $this->dropTableIfExists(Constants::TABLE_REFERRERS);
        $this->dropTableIfExists(Constants::TABLE_PAGEVIEWS);

        return true;
    }

    private function createTables(): void
    {
        // Aggregated Pageviews (no PII)
        $this->createTable(Constants::TABLE_PAGEVIEWS, [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'date' => $this->date()->notNull(),
            'hour' => $this->tinyInteger()->unsigned(),
            'url' => $this->string(500)->notNull(),
            'entryId' => $this->integer()->null(),
            'views' => $this->integer()->unsigned()->defaultValue(0),
            'uniqueVisitors' => $this->integer()->unsigned()->defaultValue(0),
            'bounces' => $this->integer()->unsigned()->defaultValue(0),
            'totalTimeOnPage' => $this->integer()->unsigned()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Referrers (domain only)
        $this->createTable(Constants::TABLE_REFERRERS, [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'date' => $this->date()->notNull(),
            'referrerDomain' => $this->string(255)->null(),
            'referrerType' => $this->string(20)->defaultValue('direct'),
            'visits' => $this->integer()->unsigned()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // UTM Campaigns
        $this->createTable(Constants::TABLE_CAMPAIGNS, [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'date' => $this->date()->notNull(),
            'utmSource' => $this->string(100)->null(),
            'utmMedium' => $this->string(100)->null(),
            'utmCampaign' => $this->string(100)->null(),
            'utmTerm' => $this->string(100)->null(),
            'utmContent' => $this->string(100)->null(),
            'visits' => $this->integer()->unsigned()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Devices & Browsers
        $this->createTable(Constants::TABLE_DEVICES, [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'date' => $this->date()->notNull(),
            'deviceType' => $this->string(20)->notNull(),
            'browserFamily' => $this->string(50)->null(),
            'osFamily' => $this->string(50)->null(),
            'visits' => $this->integer()->unsigned()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Countries (country code only)
        $this->createTable(Constants::TABLE_COUNTRIES, [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'date' => $this->date()->notNull(),
            'countryCode' => $this->char(2)->notNull(),
            'visits' => $this->integer()->unsigned()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Realtime (temporary, 5 min TTL)
        $this->createTable(Constants::TABLE_REALTIME, [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'visitorHash' => $this->string(64)->notNull(),
            'currentUrl' => $this->string(500)->notNull(),
            'lastSeen' => $this->dateTime()->notNull(),
        ]);

        // Custom Events (Pro feature)
        $this->createTable(Constants::TABLE_EVENTS, [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'date' => $this->date()->notNull(),
            'hour' => $this->tinyInteger()->unsigned(),
            'eventName' => $this->string(100)->notNull(),
            'eventCategory' => $this->string(50)->null(),
            'url' => $this->string(500)->notNull(),
            'count' => $this->integer()->unsigned()->defaultValue(0),
            'uniqueVisitors' => $this->integer()->unsigned()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Outbound Links (Pro feature)
        $this->createTable(Constants::TABLE_OUTBOUND, [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'date' => $this->date()->notNull(),
            'hour' => $this->tinyInteger()->unsigned(),
            'targetUrl' => $this->string(500)->notNull(),
            'targetDomain' => $this->string(255)->notNull(),
            'linkText' => $this->string(255)->null(),
            'sourceUrl' => $this->string(500)->notNull(),
            'urlHash' => $this->char(32)->notNull(),
            'clicks' => $this->integer()->unsigned()->defaultValue(0),
            'uniqueVisitors' => $this->integer()->unsigned()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Site Searches (Pro feature)
        $this->createTable(Constants::TABLE_SEARCHES, [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'date' => $this->date()->notNull(),
            'hour' => $this->tinyInteger()->unsigned(),
            'searchTerm' => $this->string(255)->notNull(),
            'resultsCount' => $this->integer()->unsigned()->null(),
            'searches' => $this->integer()->unsigned()->defaultValue(0),
            'uniqueVisitors' => $this->integer()->unsigned()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    private function createIndexes(): void
    {
        // Pageviews indexes - UNIQUE index required for UPSERT
        $this->createIndex(null, Constants::TABLE_PAGEVIEWS, ['siteId', 'date']);
        $this->createIndex(null, Constants::TABLE_PAGEVIEWS, ['siteId', 'date', 'hour', 'url'], true);
        $this->createIndex(null, Constants::TABLE_PAGEVIEWS, ['entryId']);

        // Referrers indexes - UNIQUE index required for UPSERT
        $this->createIndex(null, Constants::TABLE_REFERRERS, ['siteId', 'date']);
        $this->createIndex(null, Constants::TABLE_REFERRERS, ['siteId', 'date', 'referrerDomain', 'referrerType'], true);

        // Campaigns indexes - UNIQUE index required for UPSERT
        $this->createIndex(null, Constants::TABLE_CAMPAIGNS, ['siteId', 'date']);
        $this->createIndex(null, Constants::TABLE_CAMPAIGNS, ['siteId', 'date', 'utmSource', 'utmMedium', 'utmCampaign'], true);

        // Devices indexes - UNIQUE index required for UPSERT
        $this->createIndex(null, Constants::TABLE_DEVICES, ['siteId', 'date']);
        $this->createIndex(null, Constants::TABLE_DEVICES, ['siteId', 'date', 'deviceType', 'browserFamily', 'osFamily'], true);

        // Countries indexes - UNIQUE index required for UPSERT
        $this->createIndex(null, Constants::TABLE_COUNTRIES, ['siteId', 'date']);
        $this->createIndex(null, Constants::TABLE_COUNTRIES, ['siteId', 'date', 'countryCode'], true);

        // Realtime indexes
        $this->createIndex(null, Constants::TABLE_REALTIME, ['lastSeen']);
        $this->createIndex(null, Constants::TABLE_REALTIME, ['siteId', 'visitorHash'], true);

        // Events indexes - UNIQUE index required for UPSERT
        $this->createIndex(null, Constants::TABLE_EVENTS, ['siteId', 'date']);
        $this->createIndex(null, Constants::TABLE_EVENTS, ['siteId', 'date', 'hour', 'eventName', 'eventCategory', 'url'], true);
        $this->createIndex(null, Constants::TABLE_EVENTS, ['eventName']);

        // Outbound indexes - UNIQUE index required for UPSERT (uses urlHash to avoid key length issues)
        $this->createIndex(null, Constants::TABLE_OUTBOUND, ['siteId', 'date']);
        $this->createIndex(null, Constants::TABLE_OUTBOUND, ['siteId', 'date', 'hour', 'urlHash'], true);
        $this->createIndex(null, Constants::TABLE_OUTBOUND, ['targetDomain']);

        // Searches indexes - UNIQUE index required for UPSERT
        $this->createIndex(null, Constants::TABLE_SEARCHES, ['siteId', 'date']);
        $this->createIndex(null, Constants::TABLE_SEARCHES, ['siteId', 'date', 'hour', 'searchTerm'], true);
        $this->createIndex(null, Constants::TABLE_SEARCHES, ['searchTerm']);
    }

    private function addForeignKeys(): void
    {
        $this->addForeignKey(
            null,
            Constants::TABLE_PAGEVIEWS,
            ['siteId'],
            '{{%sites}}',
            ['id'],
            'CASCADE'
        );
        $this->addForeignKey(
            null,
            Constants::TABLE_PAGEVIEWS,
            ['entryId'],
            '{{%entries}}',
            ['id'],
            'SET NULL'
        );
        $this->addForeignKey(
            null,
            Constants::TABLE_REFERRERS,
            ['siteId'],
            '{{%sites}}',
            ['id'],
            'CASCADE'
        );
        $this->addForeignKey(
            null,
            Constants::TABLE_CAMPAIGNS,
            ['siteId'],
            '{{%sites}}',
            ['id'],
            'CASCADE'
        );
        $this->addForeignKey(
            null,
            Constants::TABLE_DEVICES,
            ['siteId'],
            '{{%sites}}',
            ['id'],
            'CASCADE'
        );
        $this->addForeignKey(
            null,
            Constants::TABLE_COUNTRIES,
            ['siteId'],
            '{{%sites}}',
            ['id'],
            'CASCADE'
        );
        $this->addForeignKey(
            null,
            Constants::TABLE_REALTIME,
            ['siteId'],
            '{{%sites}}',
            ['id'],
            'CASCADE'
        );
        $this->addForeignKey(
            null,
            Constants::TABLE_EVENTS,
            ['siteId'],
            '{{%sites}}',
            ['id'],
            'CASCADE'
        );
        $this->addForeignKey(
            null,
            Constants::TABLE_OUTBOUND,
            ['siteId'],
            '{{%sites}}',
            ['id'],
            'CASCADE'
        );
        $this->addForeignKey(
            null,
            Constants::TABLE_SEARCHES,
            ['siteId'],
            '{{%sites}}',
            ['id'],
            'CASCADE'
        );
    }
}
