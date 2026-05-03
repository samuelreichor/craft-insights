<?php

namespace samuelreichor\insights\migrations;

use craft\db\Migration;
use samuelreichor\insights\Constants;
use samuelreichor\insights\Insights;

/**
 * Adds tables for LLM-related request tracking, fed by LLMify's
 * EVENT_LLM_REQUEST.
 *
 * Final schema: `botName` and `elementType` are NOT NULL with empty-string
 * defaults so the unique index can deduplicate via UPSERT (MySQL treats NULL
 * as distinct in unique indexes). `urlHash` (MD5 of `url`) is part of the
 * unique key so we stay under the 3072-byte key-length limit.
 *
 * Idempotent — skips work that's already been done so re-running on a
 * partially seeded database is safe.
 */
class m260503_120000_add_llm_tracking extends Migration
{
    public function safeUp(): bool
    {
        $this->db = Insights::getInstance()->database->getConnection();

        if (!$this->db->tableExists(Constants::TABLE_LLM_REQUESTS)) {
            $this->createTable(Constants::TABLE_LLM_REQUESTS, [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->notNull(),
                'date' => $this->date()->notNull(),
                'botName' => $this->string(100)->notNull()->defaultValue(''),
                'requestType' => $this->string(16)->notNull(),
                'elementType' => $this->string(255)->notNull()->defaultValue(''),
                'url' => $this->string(Constants::MAX_URL_LENGTH)->null(),
                'urlHash' => $this->char(32)->notNull()->defaultValue(''),
                'count' => $this->integer()->unsigned()->notNull()->defaultValue(0),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, Constants::TABLE_LLM_REQUESTS, ['siteId', 'date']);
            $this->createIndex(
                'idx_insights_llm_requests_unique',
                Constants::TABLE_LLM_REQUESTS,
                ['siteId', 'date', 'botName', 'requestType', 'elementType', 'urlHash'],
                true
            );
            $this->createIndex(null, Constants::TABLE_LLM_REQUESTS, ['requestType']);
            $this->createIndex(null, Constants::TABLE_LLM_REQUESTS, ['botName']);
            $this->createIndex('idx_insights_llm_requests_url', Constants::TABLE_LLM_REQUESTS, ['url']);
        }

        if (!$this->db->tableExists(Constants::TABLE_LLM_BOTS)) {
            $this->createTable(Constants::TABLE_LLM_BOTS, [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->notNull(),
                'botName' => $this->string(100)->notNull(),
                'lastSeen' => $this->dateTime()->notNull(),
                'totalRequests' => $this->integer()->unsigned()->notNull()->defaultValue(0),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, Constants::TABLE_LLM_BOTS, ['siteId', 'botName'], true);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->db = Insights::getInstance()->database->getConnection();

        $this->dropTableIfExists(Constants::TABLE_LLM_BOTS);
        $this->dropTableIfExists(Constants::TABLE_LLM_REQUESTS);

        return true;
    }
}
