<?php

namespace samuelreichor\insights\records;

use craft\db\ActiveRecord;
use samuelreichor\insights\Constants;

/**
 * Outbound link record
 *
 * @property int $id
 * @property int $siteId
 * @property string $date
 * @property int|null $hour
 * @property string $targetUrl
 * @property string $targetDomain
 * @property string|null $linkText
 * @property string $sourceUrl
 * @property string $urlHash
 * @property int $clicks
 * @property int $uniqueVisitors
 */
class OutboundRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_OUTBOUND;
    }
}
