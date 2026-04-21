<?php

namespace samuelreichor\insights\controllers;

use Craft;
use craft\web\Controller;
use samuelreichor\insights\Insights;
use yii\web\Response;

/**
 * Settings Controller
 *
 * Handles plugin settings page and related AJAX endpoints.
 */
class SettingsController extends Controller
{
    /**
     * Render the plugin settings page with tabs.
     */
    public function actionIndex(): Response
    {
        $this->requireAdmin();

        return $this->renderTemplate('insights/settings/index', [
            'plugin' => Insights::getInstance(),
            'settings' => Insights::getInstance()->getSettings(),
            'readOnly' => !Craft::$app->getConfig()->getGeneral()->allowAdminChanges,
            'config' => Craft::$app->getConfig()->getConfigFromFile('insights'),
        ]);
    }

    /**
     * Save the submitted plugin settings.
     */
    public function actionSaveSettings(): ?Response
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        $plugin = Insights::getInstance();
        $posted = $this->request->getBodyParam('settings', []);

        $settings = array_merge($plugin->getSettings()->toArray(), $posted);

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings)) {
            Craft::$app->getSession()->setError(Craft::t('insights', 'Couldn\'t save plugin settings.'));

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('insights', 'Plugin settings saved.'));

        return $this->redirectToPostedUrl();
    }

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

    /**
     * Send a one-off test email to the currently logged-in admin.
     */
    public function actionSendTestMail(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requireAdmin();

        $user = Craft::$app->getUser()->getIdentity();
        if ($user === null || empty($user->email)) {
            return $this->asFailure(Craft::t('insights', 'No email address on your account.'));
        }

        try {
            Insights::getInstance()->notifications->sendTestMail($user->email);
        } catch (\Throwable $e) {
            return $this->asFailure($e->getMessage());
        }

        return $this->asSuccess(Craft::t('insights', 'Test mail sent to {email}.', [
            'email' => $user->email,
        ]));
    }
}
