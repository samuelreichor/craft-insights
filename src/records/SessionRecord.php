<?php

namespace samuelreichor\insights\records;

use craft\db\ActiveRecord;
use samuelreichor\insights\Constants;

/**
 * Session record
 *
 * @property int $id
 * @property int $siteId
 * @property string $date
 * @property string $visitorHash
 * @property string $sessionId
 * @property int $pageCount
 * @property string $entryUrl
 * @property int|null $entryEntryId
 * @property string|null $exitUrl
 * @property int|null $exitEntryId
 * @property string $startTime
 * @property string $lastActivityTime
 */
class SessionRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_SESSIONS;
    }
}
