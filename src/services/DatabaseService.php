<?php

namespace samuelreichor\insights\services;

use Craft;
use craft\base\Component;
use craft\db\Connection;
use samuelreichor\insights\Constants;
use samuelreichor\insights\Insights;

/**
 * Database Service
 *
 * Manages database connections for the Insights plugin.
 * Supports external database configuration via Craft's app.php component system.
 *
 * To use an external database, configure it in config/app.php:
 *
 * ```php
 * return [
 *     'components' => [
 *         'insightsDb' => [
 *             'class' => \craft\db\Connection::class,
 *             'dsn' => App::env('INSIGHTS_DB_DSN'),
 *             'username' => App::env('INSIGHTS_DB_USER'),
 *             'password' => App::env('INSIGHTS_DB_PASSWORD'),
 *             'tablePrefix' => App::env('INSIGHTS_DB_TABLE_PREFIX') ?: '',
 *         ],
 *     ],
 * ];
 * ```
 */
class DatabaseService extends Component
{
    public const COMPONENT_ID = 'insightsDb';

    /**
     * Get the database connection to use for Insights data.
     *
     * Returns the external database connection if configured and enabled (Pro only),
     * otherwise falls back to Craft's default database.
     */
    public function getConnection(): Connection
    {
        $plugin = Insights::getInstance();
        $settings = $plugin->getSettings();

        // External database is a Pro feature
        if (!$plugin->isPro()) {
            return Craft::$app->db;
        }

        // If external database is not enabled, use Craft's DB
        if (!$settings->useExternalDatabase) {
            return Craft::$app->db;
        }

        // If external DB is enabled but not configured, fall back with warning
        if (!$this->isExternalDatabaseConfigured()) {
            $plugin->logger->warning('External database enabled but not configured, using Craft DB');
            return Craft::$app->db;
        }

        // Get the configured external connection
        try {
            $connection = Craft::$app->get(self::COMPONENT_ID);
            if ($connection instanceof Connection) {
                return $connection;
            }
        } catch (\Throwable $e) {
            $plugin->logger->error('Failed to get external database connection: ' . $e->getMessage());
        }

        return Craft::$app->db;
    }

    /**
     * Check if external database component is configured in app.php.
     */
    public function isExternalDatabaseConfigured(): bool
    {
        return Craft::$app->has(self::COMPONENT_ID);
    }

    /**
     * Test the external database connection.
     *
     * @return array{success: bool, message: string, details: array<string, mixed>}
     */
    public function testConnection(): array
    {
        if (!$this->isExternalDatabaseConfigured()) {
            return [
                'success' => false,
                'message' => 'External database component "insightsDb" is not configured in config/app.php.',
                'details' => [],
            ];
        }

        try {
            /** @var Connection $connection */
            $connection = Craft::$app->get(self::COMPONENT_ID);
            $connection->open();

            $serverVersion = $connection->getServerVersion();
            $driverName = $connection->getDriverName();

            return [
                'success' => true,
                'message' => 'Connection successful!',
                'details' => [
                    'driver' => $driverName,
                    'serverVersion' => $serverVersion,
                    'dsn' => $this->maskDsn($connection->dsn),
                    'tablePrefix' => $connection->tablePrefix ?: '(none)',
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'details' => [
                    'errorCode' => $e->getCode(),
                ],
            ];
        }
    }

    /**
     * Check if all required Insights tables exist in the database.
     */
    public function tablesExist(): bool
    {
        try {
            $connection = $this->getConnection();

            foreach (Constants::getAllTables() as $table) {
                if (!$connection->tableExists($table)) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get the current connection status for display.
     *
     * @return array{configured: bool, enabled: bool, connected: bool, usingExternal: bool, tablesExist: bool, error: string|null, details: array<string, mixed>}
     */
    public function getConnectionStatus(): array
    {
        $settings = Insights::getInstance()->getSettings();
        $configured = $this->isExternalDatabaseConfigured();
        $enabled = $settings->useExternalDatabase;
        $connected = false;
        $usingExternal = false;
        $tablesExist = false;
        $error = null;
        $details = [
            'driver' => '(unknown)',
            'dsn' => '(not set)',
            'tablePrefix' => '(none)',
        ];

        if ($configured) {
            try {
                /** @var Connection $connection */
                $connection = Craft::$app->get(self::COMPONENT_ID);
                $details = [
                    'driver' => $connection->getDriverName() ?: '(unknown)',
                    'dsn' => $this->maskDsn($connection->dsn),
                    'tablePrefix' => $connection->tablePrefix ?: '(none)',
                ];
            } catch (\Throwable $e) {
                $error = 'Configuration error: ' . $e->getMessage();
            }

            if ($enabled && $error === null) {
                $testResult = $this->testConnection();
                $connected = $testResult['success'];
                $error = $testResult['success'] ? null : $testResult['message'];
                $usingExternal = $connected;

                if ($connected) {
                    $tablesExist = $this->tablesExist();
                }
            }
        }

        return [
            'configured' => $configured,
            'enabled' => $enabled,
            'connected' => $connected,
            'usingExternal' => $usingExternal,
            'tablesExist' => $tablesExist,
            'error' => $error,
            'details' => $details,
        ];
    }

    /**
     * Check if we're currently using an external database.
     */
    public function isUsingExternalDatabase(): bool
    {
        $plugin = Insights::getInstance();

        return $plugin->isPro()
            && $plugin->getSettings()->useExternalDatabase
            && $this->isExternalDatabaseConfigured();
    }

    /**
     * Mask sensitive parts of DSN for display.
     */
    private function maskDsn(?string $dsn): string
    {
        if ($dsn === null || $dsn === '') {
            return '(not set)';
        }

        // Mask password if present in DSN
        return (string)preg_replace('/password=[^;]+/', 'password=***', $dsn);
    }
}
