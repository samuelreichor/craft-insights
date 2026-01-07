<?php

namespace samuelreichor\insights\web\assets;

use craft\web\AssetBundle;

/**
 * Tracking Asset Bundle
 *
 * Loads the minimal tracking script for the frontend.
 */
class TrackingAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/src';

        $this->js = [
            'js/insights.js',
        ];

        parent::init();
    }
}
