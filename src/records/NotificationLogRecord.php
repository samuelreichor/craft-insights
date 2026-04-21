<?php

namespace samuelreichor\insights\records;

use craft\db\ActiveRecord;
use craft\db\Connection;
use samuelreichor\insights\Constants;
use samuelreichor\insights\Insights;

/**
 * Notification log record
 *
 * @property int $id
 * @property string $frequency
 * @property string $sentAt
 * @property int $recipientCount
 * @property string $status
 * @property string|null $errorMessage
 */
class NotificationLogRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_NOTIFICATION_LOG;
    }

    /**
     * @inheritdoc
     *
     * Routes reads/writes to the Insights database connection, which may be
     * the external DB when the Pro option is enabled.
     */
    public static function getDb(): Connection
    {
        return Insights::getInstance()->database->getConnection();
    }
}
