<?php

namespace samuelreichor\insights\migrations;

use craft\db\Migration;
use samuelreichor\insights\Constants;

/**
 * m260107_100000_add_outbound_table migration.
 */
class m260107_100000_add_outbound_table extends Migration
{
    public function safeUp(): bool
    {
        // Outbound Links table (Pro feature)
        // Uses urlHash for unique constraint to avoid MySQL key length limits
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

        // Indexes - use urlHash for unique constraint (MD5 of targetUrl + sourceUrl)
        $this->createIndex(null, Constants::TABLE_OUTBOUND, ['siteId', 'date']);
        $this->createIndex(null, Constants::TABLE_OUTBOUND, ['siteId', 'date', 'hour', 'urlHash'], true);
        $this->createIndex(null, Constants::TABLE_OUTBOUND, ['targetDomain']);

        // Foreign key
        $this->addForeignKey(
            null,
            Constants::TABLE_OUTBOUND,
            ['siteId'],
            '{{%sites}}',
            ['id'],
            'CASCADE'
        );

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(Constants::TABLE_OUTBOUND);

        return true;
    }
}
