<?php

namespace samuelreichor\insights\records;

use craft\db\ActiveRecord;
use samuelreichor\insights\Constants;

/**
 * Campaign record
 *
 * @property int $id
 * @property int $siteId
 * @property string $date
 * @property string|null $utmSource
 * @property string|null $utmMedium
 * @property string|null $utmCampaign
 * @property string|null $utmTerm
 * @property string|null $utmContent
 * @property int $visits
 */
class CampaignRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_CAMPAIGNS;
    }
}
