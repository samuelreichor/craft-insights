<?php

namespace samuelreichor\insights\records;

use craft\db\ActiveRecord;
use samuelreichor\insights\Constants;

/**
 * LLM bot summary record.
 *
 * @property int $id
 * @property int $siteId
 * @property string $botName
 * @property string $lastSeen
 * @property int $totalRequests
 */
class LlmBotRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_LLM_BOTS;
    }
}
