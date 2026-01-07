<?php

namespace samuelreichor\insights\records;

use craft\db\ActiveRecord;
use samuelreichor\insights\Constants;

/**
 * Country record
 *
 * @property int $id
 * @property int $siteId
 * @property string $date
 * @property string $countryCode
 * @property int $visits
 */
class CountryRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_COUNTRIES;
    }
}
