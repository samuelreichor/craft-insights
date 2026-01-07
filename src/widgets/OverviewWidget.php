<?php

namespace samuelreichor\insights\widgets;

use Craft;
use craft\base\Widget;
use samuelreichor\insights\Insights;

/**
 * Overview Widget
 *
 * Shows key metrics on the Craft dashboard.
 */
class OverviewWidget extends Widget
{
    public string $range = '7d';

    public static function displayName(): string
    {
        return Craft::t('insights', 'Insights Overview');
    }

    public static function icon(): ?string
    {
        return 'chart-line';
    }

    public function getTitle(): ?string
    {
        return Craft::t('insights', 'Analytics Overview');
    }

    public function getSubtitle(): ?string
    {
        return match ($this->range) {
            'today' => Craft::t('insights', 'Today'),
            '7d' => Craft::t('insights', 'Last 7 Days'),
            '30d' => Craft::t('insights', 'Last 30 Days'),
            '90d' => Craft::t('insights', 'Last 90 Days'),
            default => Craft::t('insights', 'Last 7 Days'),
        };
    }

    public function getBodyHtml(): ?string
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $summary = Insights::getInstance()->stats->getSummary($siteId, $this->range);

        return Craft::$app->getView()->renderTemplate('insights/_widgets/overview', [
            'summary' => $summary,
            'range' => $this->range,
        ]);
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('insights/_widgets/overview-settings', [
            'widget' => $this,
        ]);
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['range'], 'in', 'range' => ['today', '7d', '30d', '90d']];
        return $rules;
    }
}
