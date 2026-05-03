<?php

namespace samuelreichor\insights\records;

use craft\db\ActiveRecord;
use samuelreichor\insights\Constants;

/**
 * LLM request record.
 *
 * @property int $id
 * @property int $siteId
 * @property string $date
 * @property string $botName
 * @property string $requestType
 * @property string $elementType
 * @property string|null $url
 * @property string $urlHash
 * @property int $count
 */
class LlmRequestRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_LLM_REQUESTS;
    }
}
