<?php

namespace samuelreichor\insights\records;

use craft\db\ActiveRecord;
use samuelreichor\insights\Constants;

/**
 * Device record
 *
 * @property int $id
 * @property int $siteId
 * @property string $date
 * @property string $deviceType
 * @property string|null $browserFamily
 * @property string|null $osFamily
 * @property int $visits
 */
class DeviceRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_DEVICES;
    }
}
