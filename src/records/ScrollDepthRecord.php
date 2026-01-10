<?php

namespace samuelreichor\insights\records;

use craft\db\ActiveRecord;
use samuelreichor\insights\Constants;

/**
 * Scroll Depth record
 *
 * @property int $id
 * @property int $siteId
 * @property string $date
 * @property int|null $hour
 * @property string $url
 * @property int|null $entryId
 * @property int $milestone25
 * @property int $milestone50
 * @property int $milestone75
 * @property int $milestone100
 */
class ScrollDepthRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_SCROLL_DEPTH;
    }
}
