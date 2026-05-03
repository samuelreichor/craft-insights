<?php

namespace samuelreichor\insights\helpers;

use Craft;

class Utils
{
    /**
     * Checks if a plugin is installed and enabled
     *
     * @param string $pluginHandle
     * @return bool
     */
    public static function isPluginInstalledAndEnabled(string $pluginHandle): bool
    {
        $plugin = Craft::$app->plugins->getPlugin($pluginHandle);

        if ($plugin !== null && $plugin->isInstalled) {
            return true;
        }

        return false;
    }
}
