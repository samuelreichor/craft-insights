<?php

namespace samuelreichor\insights\migrations;

use craft\db\Migration;
use samuelreichor\insights\Constants;

/**
 * m260110_000000_add_scroll_depth_and_sessions migration.
 *
 * Adds scroll depth tracking (Pro feature) and session tracking (Lite/Pro).
 */
class m260110_000000_add_scroll_depth_and_sessions extends Migration
{
    public function safeUp(): bool
    {
        // Scroll Depth table (Pro feature) - tracks 25%, 50%, 75%, 100% milestones
        $this->createTable(Constants::TABLE_SCROLL_DEPTH, [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'date' => $this->date()->notNull(),
            'hour' => $this->tinyInteger()->unsigned(),
            'url' => $this->string(500)->notNull(),
            'entryId' => $this->integer()->null(),
            'milestone25' => $this->integer()->unsigned()->defaultValue(0),
            'milestone50' => $this->integer()->unsigned()->defaultValue(0),
            'milestone75' => $this->integer()->unsigned()->defaultValue(0),
            'milestone100' => $this->integer()->unsigned()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Scroll depth indexes - UNIQUE index required for UPSERT
        $this->createIndex(null, Constants::TABLE_SCROLL_DEPTH, ['siteId', 'date']);
        $this->createIndex(null, Constants::TABLE_SCROLL_DEPTH, ['siteId', 'date', 'hour', 'url'], true);
        $this->createIndex(null, Constants::TABLE_SCROLL_DEPTH, ['entryId']);

        // Foreign keys for scroll depth
        $this->addForeignKey(
            null,
            Constants::TABLE_SCROLL_DEPTH,
            ['siteId'],
            '{{%sites}}',
            ['id'],
            'CASCADE'
        );
        $this->addForeignKey(
            null,
            Constants::TABLE_SCROLL_DEPTH,
            ['entryId'],
            '{{%entries}}',
            ['id'],
            'SET NULL'
        );

        // Sessions table - tracks pages per session, entry/exit pages
        $this->createTable(Constants::TABLE_SESSIONS, [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'date' => $this->date()->notNull(),
            'visitorHash' => $this->string(64)->notNull(),
            'sessionId' => $this->string(32)->notNull(),
            'pageCount' => $this->integer()->unsigned()->defaultValue(1),
            'entryUrl' => $this->string(500)->notNull(),
            'entryEntryId' => $this->integer()->null(),
            'exitUrl' => $this->string(500)->null(),
            'exitEntryId' => $this->integer()->null(),
            'startTime' => $this->dateTime()->notNull(),
            'lastActivityTime' => $this->dateTime()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Sessions indexes - UNIQUE index on session identifier
        $this->createIndex(null, Constants::TABLE_SESSIONS, ['siteId', 'date']);
        $this->createIndex(null, Constants::TABLE_SESSIONS, ['siteId', 'visitorHash', 'sessionId'], true);
        $this->createIndex(null, Constants::TABLE_SESSIONS, ['entryEntryId']);
        $this->createIndex(null, Constants::TABLE_SESSIONS, ['exitEntryId']);
        $this->createIndex(null, Constants::TABLE_SESSIONS, ['lastActivityTime']);

        // Foreign keys for sessions
        $this->addForeignKey(
            null,
            Constants::TABLE_SESSIONS,
            ['siteId'],
            '{{%sites}}',
            ['id'],
            'CASCADE'
        );
        $this->addForeignKey(
            null,
            Constants::TABLE_SESSIONS,
            ['entryEntryId'],
            '{{%entries}}',
            ['id'],
            'SET NULL'
        );
        $this->addForeignKey(
            null,
            Constants::TABLE_SESSIONS,
            ['exitEntryId'],
            '{{%entries}}',
            ['id'],
            'SET NULL'
        );

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(Constants::TABLE_SESSIONS);
        $this->dropTableIfExists(Constants::TABLE_SCROLL_DEPTH);

        return true;
    }
}
