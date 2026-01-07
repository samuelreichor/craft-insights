<?php

namespace samuelreichor\insights\records;

use craft\db\ActiveRecord;
use samuelreichor\insights\Constants;

/**
 * Referrer record
 *
 * @property int $id
 * @property int $siteId
 * @property string $date
 * @property string|null $referrerDomain
 * @property string $referrerType
 * @property int $visits
 */
class ReferrerRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_REFERRERS;
    }
}
