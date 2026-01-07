<?php

namespace samuelreichor\insights\widgets;

use Craft;
use craft\base\Widget;
use samuelreichor\insights\Insights;

/**
 * Realtime Widget
 *
 * Shows current active visitors on the Craft dashboard.
 */
class RealtimeWidget extends Widget
{
    public static function displayName(): string
    {
        return Craft::t('insights', 'Insights Realtime');
    }

    public static function icon(): ?string
    {
        return 'users';
    }

    public function getTitle(): ?string
    {
        return Craft::t('insights', 'Active Visitors');
    }

    public function getSubtitle(): ?string
    {
        return Craft::t('insights', 'Right now');
    }

    public function getBodyHtml(): ?string
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $realtime = Insights::getInstance()->stats->getRealtimeVisitors($siteId);

        return Craft::$app->getView()->renderTemplate('insights/_widgets/realtime', [
            'realtime' => $realtime,
            'siteId' => $siteId,
        ]);
    }
}
