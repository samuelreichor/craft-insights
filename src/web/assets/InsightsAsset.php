<?php

namespace samuelreichor\insights\web\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Insights Asset Bundle
 *
 * Loads the dashboard CSS and JavaScript for the Control Panel.
 */
class InsightsAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/src';

        $this->depends = [
            CpAsset::class,
        ];

        $this->css = [
            'css/dashboard.css',
        ];

        $this->js = [
            'js/dashboard.js',
        ];

        parent::init();
    }
}
