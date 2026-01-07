<?php

namespace samuelreichor\insights\records;

use craft\db\ActiveRecord;
use samuelreichor\insights\Constants;

/**
 * Realtime record
 *
 * @property int $id
 * @property int $siteId
 * @property string $visitorHash
 * @property string $currentUrl
 * @property string $lastSeen
 */
class RealtimeRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_REALTIME;
    }
}
