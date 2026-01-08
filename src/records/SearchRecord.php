<?php

namespace samuelreichor\insights\records;

use craft\db\ActiveRecord;
use samuelreichor\insights\Constants;

/**
 * Search record
 *
 * @property int $id
 * @property int $siteId
 * @property string $date
 * @property int|null $hour
 * @property string $searchTerm
 * @property int|null $resultsCount
 * @property int $searches
 * @property int $uniqueVisitors
 */
class SearchRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_SEARCHES;
    }
}
