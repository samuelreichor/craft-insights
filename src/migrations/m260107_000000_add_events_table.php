<?php

namespace samuelreichor\insights\migrations;

use craft\db\Migration;
use samuelreichor\insights\Constants;

/**
 * m260107_000000_add_events_table migration.
 */
class m260107_000000_add_events_table extends Migration
{
    public function safeUp(): bool
    {
        // Custom Events table (Pro feature)
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

        // Indexes
        $this->createIndex(null, Constants::TABLE_EVENTS, ['siteId', 'date']);
        $this->createIndex(null, Constants::TABLE_EVENTS, ['siteId', 'date', 'hour', 'eventName', 'eventCategory', 'url'], true);
        $this->createIndex(null, Constants::TABLE_EVENTS, ['eventName']);

        // Foreign key
        $this->addForeignKey(
            null,
            Constants::TABLE_EVENTS,
            ['siteId'],
            '{{%sites}}',
            ['id'],
            'CASCADE'
        );

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(Constants::TABLE_EVENTS);

        return true;
    }
}
