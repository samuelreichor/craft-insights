<?php

namespace samuelreichor\insights\records;

use craft\db\ActiveRecord;
use samuelreichor\insights\Constants;

/**
 * Pageview record
 *
 * @property int $id
 * @property int $siteId
 * @property string $date
 * @property int|null $hour
 * @property string $url
 * @property int|null $entryId
 * @property int $views
 * @property int $uniqueVisitors
 * @property int $bounces
 * @property int $totalTimeOnPage
 */
class PageviewRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_PAGEVIEWS;
    }
}
