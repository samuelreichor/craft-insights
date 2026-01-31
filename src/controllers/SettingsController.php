<?php

namespace samuelreichor\insights\controllers;

use craft\web\Controller;
use samuelreichor\insights\Insights;
use yii\web\Response;

/**
 * Settings Controller
 *
 * Handles AJAX endpoints for plugin settings.
 */
class SettingsController extends Controller
{
    /**
     * Test the external database connection.
     */
    public function actionTestConnection(): Response
    {
        $this->requireAcceptsJson();
        $this->requireAdmin();

        $result = Insights::getInstance()->database->testConnection();

        return $this->asJson($result);
    }
}
