<?php

namespace samuelreichor\insights\records;

use craft\db\ActiveRecord;
use samuelreichor\insights\Constants;

/**
 * Event record
 *
 * @property int $id
 * @property int $siteId
 * @property string $date
 * @property int|null $hour
 * @property string $eventName
 * @property string|null $eventCategory
 * @property string $url
 * @property int $count
 * @property int $uniqueVisitors
 */
class EventRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_EVENTS;
    }
}
