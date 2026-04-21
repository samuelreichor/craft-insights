<?php

namespace samuelreichor\insights\migrations;

use craft\db\Migration;
use samuelreichor\insights\Constants;
use samuelreichor\insights\Insights;

/**
 * m260421_100000_add_notification_log migration.
 *
 * Creates the notification log on whichever DB the Insights plugin is
 * currently using (Craft's DB by default, external DB if Pro is configured).
 */
class m260421_100000_add_notification_log extends Migration
{
    public function safeUp(): bool
    {
        $this->db = Insights::getInstance()->database->getConnection();

        if ($this->db->tableExists(Constants::TABLE_NOTIFICATION_LOG)) {
            return true;
        }

        $this->createTable(Constants::TABLE_NOTIFICATION_LOG, [
            'id' => $this->primaryKey(),
            'frequency' => $this->string(20)->notNull(),
            'sentAt' => $this->dateTime()->notNull(),
            'recipientCount' => $this->integer()->unsigned()->defaultValue(0),
            'status' => $this->string(20)->notNull()->defaultValue('sent'),
            'errorMessage' => $this->text()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, Constants::TABLE_NOTIFICATION_LOG, ['frequency', 'sentAt']);

        return true;
    }

    public function safeDown(): bool
    {
        $this->db = Insights::getInstance()->database->getConnection();
        $this->dropTableIfExists(Constants::TABLE_NOTIFICATION_LOG);

        return true;
    }
}
