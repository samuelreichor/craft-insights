<?php

namespace samuelreichor\insights\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Entry;
use samuelreichor\insights\Insights;

/**
 * Insights Field
 *
 * Shows entry statistics in the entry editor.
 */
class InsightsField extends Field
{
    public string $range = '30d';

    public static function displayName(): string
    {
        return Craft::t('insights', 'Insights Statistics');
    }

    public static function icon(): string
    {
        return 'chart-line';
    }

    public static function hasContentColumn(): bool
    {
        return false;
    }

    public function getInputHtml(mixed $value, ?ElementInterface $element = null): string
    {
        if (!$element instanceof Entry || !$element->id) {
            return '<p class="light">' . Craft::t('insights', 'Save the entry to see statistics.') . '</p>';
        }

        $stats = Insights::getInstance()->stats->getEntryStats($element->id, $this->range);

        return Craft::$app->getView()->renderTemplate('insights/_fields/stats', [
            'stats' => $stats,
            'range' => $this->range,
            'entryId' => $element->id,
        ]);
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('insights/_fields/stats-settings', [
            'field' => $this,
        ]);
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['range'], 'in', 'range' => ['7d', '30d', '90d', '12m']];
        return $rules;
    }
}
