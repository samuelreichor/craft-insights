<?php

namespace samuelreichor\insights\console\controllers;

use Craft;
use craft\console\Controller;
use craft\db\Query;
use craft\helpers\Console;
use samuelreichor\insights\Constants;
use samuelreichor\insights\Insights;
use yii\console\ExitCode;

/**
 * Database management commands for Insights plugin.
 */
class DatabaseController extends Controller
{
    /**
     * @var bool Whether to delete data from source after successful migration.
     */
    public bool $deleteSource = false;

    /**
     * @var bool Whether to clear target tables before migration.
     */
    public bool $force = false;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'migrate-data') {
            $options[] = 'deleteSource';
            $options[] = 'force';
        }

        return $options;
    }

    /**
     * Check if Pro edition is active.
     */
    private function requirePro(): bool
    {
        if (!Insights::getInstance()->isPro()) {
            $this->stderr("External database is a Pro feature.\n", Console::FG_RED);
            $this->stdout("Upgrade to Pro: https://plugins.craftcms.com/insights\n");
            return false;
        }
        return true;
    }

    /**
     * Test the external database connection (Pro).
     *
     * @return int
     */
    public function actionTest(): int
    {
        if (!$this->requirePro()) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Testing external database connection...\n\n", Console::FG_CYAN);

        $database = Insights::getInstance()->database;

        if (!$database->isExternalDatabaseConfigured()) {
            $this->stderr("External database is not configured.\n", Console::FG_YELLOW);
            $this->stdout("\nAdd 'insightsDb' component to config/app.php\n");
            $this->stdout("See: https://craftcms.com/knowledge-base/connecting-to-multiple-databases\n");

            return ExitCode::OK;
        }

        $result = $database->testConnection();

        if ($result['success']) {
            $this->stdout("Connection successful!\n\n", Console::FG_GREEN);
            $this->stdout("Details:\n");
            foreach ($result['details'] as $key => $value) {
                $this->stdout("  {$key}: {$value}\n");
            }
        } else {
            $this->stderr("Connection failed!\n\n", Console::FG_RED);
            $this->stderr("Error: {$result['message']}\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Show current database connection status (Pro).
     *
     * @return int
     */
    public function actionStatus(): int
    {
        if (!$this->requirePro()) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Insights Database Status\n", Console::FG_CYAN);
        $this->stdout(str_repeat("=", 40) . "\n\n");

        $database = Insights::getInstance()->database;
        $status = $database->getConnectionStatus();

        // Configuration status
        $this->stdout("Configuration:\n");
        $configuredText = $status['configured'] ? 'Yes' : 'No';
        $configuredColor = $status['configured'] ? Console::FG_GREEN : Console::FG_YELLOW;
        $this->stdout("  External DB Configured: ");
        $this->stdout($configuredText . "\n", $configuredColor);

        $enabledText = $status['enabled'] ? 'Yes' : 'No';
        $enabledColor = $status['enabled'] ? Console::FG_GREEN : Console::FG_YELLOW;
        $this->stdout("  External DB Enabled:    ");
        $this->stdout($enabledText . "\n", $enabledColor);

        // Connection status
        if ($status['configured']) {
            $this->stdout("\nConnection:\n");

            if ($status['enabled']) {
                $connectedText = $status['connected'] ? 'Yes' : 'No';
                $connectedColor = $status['connected'] ? Console::FG_GREEN : Console::FG_RED;
                $this->stdout("  Connected: ");
                $this->stdout($connectedText . "\n", $connectedColor);

                if (!$status['connected'] && $status['error']) {
                    $this->stdout("  Error: ");
                    $this->stderr($status['error'] . "\n", Console::FG_RED);
                }
            } else {
                $this->stdout("  (Enable external database in settings to connect)\n", Console::FG_YELLOW);
            }

            // Details
            $this->stdout("\nConfiguration Details:\n");
            foreach ($status['details'] as $key => $value) {
                $this->stdout("  {$key}: {$value}\n");
            }
        }

        // Active connection
        $this->stdout("\nActive Connection:\n");
        $usingExternal = $database->isUsingExternalDatabase();
        $activeDb = $usingExternal ? 'External Database' : 'Craft Database';
        $this->stdout("  Using: {$activeDb}\n");

        // Tables status
        $this->stdout("\nTables:\n");
        $tablesExist = $database->tablesExist();
        $tablesText = $tablesExist ? 'All tables exist' : 'Tables missing';
        $tablesColor = $tablesExist ? Console::FG_GREEN : Console::FG_RED;
        $this->stdout("  Status: ");
        $this->stdout($tablesText . "\n", $tablesColor);

        return ExitCode::OK;
    }

    /**
     * Create Insights tables in the external database (Pro).
     *
     * This runs the plugin's install migration against the external database.
     *
     * @return int
     */
    public function actionMigrate(): int
    {
        if (!$this->requirePro()) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Creating Insights tables...\n\n", Console::FG_CYAN);

        $database = Insights::getInstance()->database;
        $settings = Insights::getInstance()->getSettings();

        if (!$settings->useExternalDatabase) {
            $this->stderr("External database is not enabled in settings.\n", Console::FG_YELLOW);
            $this->stdout("Tables are managed by Craft's normal migration system.\n");

            return ExitCode::OK;
        }

        if (!$database->isExternalDatabaseConfigured()) {
            $this->stderr("External database is not configured.\n", Console::FG_RED);
            $this->stdout("Add 'insightsDb' component to config/app.php\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Test connection first
        $result = $database->testConnection();
        if (!$result['success']) {
            $this->stderr("Cannot connect to external database.\n", Console::FG_RED);
            $this->stderr("Error: {$result['message']}\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Connected to external database.\n", Console::FG_GREEN);

        // Check if tables already exist
        if ($database->tablesExist()) {
            $this->stdout("All tables already exist.\n", Console::FG_GREEN);

            return ExitCode::OK;
        }

        // Create tables using the external connection
        $db = $database->getConnection();
        $migration = new \samuelreichor\insights\migrations\Install();
        $migration->db = $db;

        $this->stdout("Creating tables...\n");

        try {
            if ($migration->safeUp()) {
                $this->stdout("\nTables created successfully!\n", Console::FG_GREEN);

                // List created tables
                $this->stdout("\nCreated tables:\n");
                foreach (Constants::getAllTables() as $table) {
                    $tableName = str_replace(['{{%', '}}'], '', $table);
                    $this->stdout("  - {$tableName}\n");
                }
            } else {
                $this->stderr("\nFailed to create tables.\n", Console::FG_RED);

                return ExitCode::UNSPECIFIED_ERROR;
            }
        } catch (\Throwable $e) {
            $this->stderr("\nError creating tables: {$e->getMessage()}\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Migrate data from Craft database to external database (Pro).
     *
     * Use --force to clear target tables before migration.
     * Use --delete-source to remove data from Craft DB after successful migration.
     *
     * @return int
     */
    public function actionMigrateData(): int
    {
        if (!$this->requirePro()) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Migrating Insights data to external database...\n\n", Console::FG_CYAN);

        $database = Insights::getInstance()->database;

        // Verify external DB is configured
        if (!$database->isExternalDatabaseConfigured()) {
            $this->stderr("External database is not configured.\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Verify external DB connection works
        $result = $database->testConnection();
        if (!$result['success']) {
            $this->stderr("Cannot connect to external database.\n", Console::FG_RED);
            $this->stderr("Error: {$result['message']}\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Get both connections
        $craftDb = Craft::$app->db;
        $externalDb = Craft::$app->get('insightsDb');

        // Check if tables exist in external DB
        $tables = Constants::getAllTables();
        foreach ($tables as $table) {
            if (!$externalDb->tableExists($table)) {
                $this->stderr("Tables missing in external database.\n", Console::FG_RED);
                $this->stdout("Run 'php craft insights/database/migrate' first.\n");

                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        // Check if target has existing data
        $hasExistingData = false;
        foreach ($tables as $table) {
            $count = (new Query())->from($table)->count('*', $externalDb);
            if ($count > 0) {
                $hasExistingData = true;
                break;
            }
        }

        if ($hasExistingData && !$this->force) {
            $this->stderr("External database already contains data.\n", Console::FG_RED);
            $this->stdout("Use --force to clear target tables before migration.\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Clear target tables if force is enabled
        if ($this->force && $hasExistingData) {
            $this->stdout("Clearing target tables...\n", Console::FG_YELLOW);
            foreach ($tables as $table) {
                $externalDb->createCommand()->delete($table)->execute();
            }
            $this->stdout("Target tables cleared.\n\n");
        }

        $this->stdout("Source: Craft Database\n");
        $this->stdout("Target: External Database\n\n");

        $totalMigrated = 0;

        foreach ($tables as $table) {
            $tableName = str_replace(['{{%', '}}'], '', $table);

            // Count records in source
            $count = (new Query())
                ->from($table)
                ->count('*', $craftDb);

            if ($count === 0) {
                $this->stdout("  {$tableName}: 0 records (skipped)\n");
                continue;
            }

            $this->stdout("  {$tableName}: {$count} records... ");

            try {
                // Fetch all data from Craft DB
                $rows = (new Query())
                    ->from($table)
                    ->all($craftDb);

                // Insert into external DB in batches
                $batchSize = 100;
                $inserted = 0;

                foreach (array_chunk($rows, $batchSize) as $batch) {
                    // Remove id column to let external DB auto-generate
                    $batchData = array_map(function($row) {
                        unset($row['id']);
                        return $row;
                    }, $batch);

                    $columns = array_keys($batchData[0]);
                    $externalDb->createCommand()
                        ->batchInsert($table, $columns, $batchData)
                        ->execute();
                    $inserted += count($batchData);
                }

                $this->stdout("migrated\n", Console::FG_GREEN);
                $totalMigrated += $inserted;
            } catch (\Throwable $e) {
                $this->stderr("failed\n", Console::FG_RED);
                $this->stderr("    Error: {$e->getMessage()}\n");

                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        $this->stdout("\nMigration complete! {$totalMigrated} records migrated.\n", Console::FG_GREEN);

        // Optionally delete source data
        if ($this->deleteSource && $totalMigrated > 0) {
            $this->stdout("\nDeleting data from Craft database...\n", Console::FG_YELLOW);

            foreach ($tables as $table) {
                $tableName = str_replace(['{{%', '}}'], '', $table);
                $deleted = $craftDb->createCommand()
                    ->delete($table)
                    ->execute();

                if ($deleted > 0) {
                    $this->stdout("  {$tableName}: {$deleted} records deleted\n");
                }
            }

            $this->stdout("Source data deleted.\n", Console::FG_GREEN);
        } elseif ($totalMigrated > 0 && !$this->deleteSource) {
            $this->stdout("\nNote: Source data was kept. Use --delete-source to remove it.\n", Console::FG_YELLOW);
        }

        return ExitCode::OK;
    }
}
