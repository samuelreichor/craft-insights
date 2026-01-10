<?php

namespace samuelreichor\insights\migrations;

use craft\db\Migration;
use samuelreichor\insights\Constants;

/**
 * m260107_140000_add_searches_table migration.
 */
class m260107_140000_add_searches_table extends Migration
{
    public function safeUp(): bool
    {
        // Site Searches table (Pro feature)
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

        // Indexes
        $this->createIndex(null, Constants::TABLE_SEARCHES, ['siteId', 'date']);
        $this->createIndex(null, Constants::TABLE_SEARCHES, ['siteId', 'date', 'hour', 'searchTerm'], true);
        $this->createIndex(null, Constants::TABLE_SEARCHES, ['searchTerm']);

        // Foreign key
        $this->addForeignKey(
            null,
            Constants::TABLE_SEARCHES,
            ['siteId'],
            '{{%sites}}',
            ['id'],
            'CASCADE'
        );

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(Constants::TABLE_SEARCHES);

        return true;
    }
}
