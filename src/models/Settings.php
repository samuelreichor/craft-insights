<?php

namespace samuelreichor\insights\models;

use craft\base\Model;
use samuelreichor\insights\enums\DateRange;
use samuelreichor\insights\enums\LogLevel;

/**
 * Insights settings
 */
class Settings extends Model
{
    // External Database
    public bool $useExternalDatabase = false;

    // Tracking
    public bool $enabled = true;

    // Privacy (DSGVO)
    public bool $respectDoNotTrack = true;
    public bool $excludeLoggedInUsers = false;

    /** @var string[] */
    public array $excludedIpRanges = [];

    /** @var string[] */
    public array $excludedPaths = ['/admin', '/cpresources', '/actions'];

    // GeoIP
    public string $geoIpDatabasePath = '@storage/geoip/GeoLite2-Country.mmdb';

    // Data Retention
    public int $dataRetentionDays = 365;
    public bool $autoCleanup = true;

    // Performance
    public bool $useQueue = true;
    public int $realtimeTtl = 300; // 5 minutes

    // Queue Job Settings
    public int $queueJobTtr = 300; // Time to reserve in seconds
    public int $processTrackingJobPriority = 20; // Lower number = higher priority (default: 1024)
    public int $maxRetryAttempts = 3;

    // Dashboard
    public string $defaultDateRange = '30d';
    public bool $showRealtimeWidget = true;

    // Entry Sidebar
    public bool $showEntrySidebar = true;

    // Logging
    public string $logLevel = 'default';

    /**
     * Get the log level as enum.
     */
    public function getLogLevelEnum(): LogLevel
    {
        return LogLevel::tryFrom($this->logLevel) ?? LogLevel::Default;
    }

    /**
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true): void
    {
        // Transform editable table data from [{path: '/admin'}] to ['/admin']
        if (isset($values['excludedPaths']) && is_array($values['excludedPaths'])) {
            $values['excludedPaths'] = $this->flattenTableData($values['excludedPaths'], 'path');
        }

        if (isset($values['excludedIpRanges']) && is_array($values['excludedIpRanges'])) {
            $values['excludedIpRanges'] = $this->flattenTableData($values['excludedIpRanges'], 'ip');
        }

        parent::setAttributes($values, $safeOnly);
    }

    /**
     * Flatten editable table data to simple array.
     *
     * @param array<int|string, array<string, string>|string> $data
     * @return string[]
     */
    private function flattenTableData(array $data, string $key): array
    {
        $result = [];
        foreach ($data as $row) {
            if (is_array($row) && isset($row[$key]) && !empty($row[$key])) {
                $result[] = $row[$key];
            } elseif (is_string($row) && !empty($row)) {
                $result[] = $row;
            }
        }
        return $result;
    }

    public function rules(): array
    {
        return [
            [['enabled', 'useExternalDatabase'], 'boolean'],
            [['respectDoNotTrack', 'excludeLoggedInUsers'], 'boolean'],
            [['autoCleanup', 'useQueue', 'showRealtimeWidget', 'showEntrySidebar'], 'boolean'],
            [['dataRetentionDays'], 'integer', 'min' => 1, 'max' => 730],
            [['realtimeTtl'], 'integer', 'min' => 60, 'max' => 900],
            [['queueJobTtr'], 'integer', 'min' => 60, 'max' => 3600],
            [['processTrackingJobPriority'], 'integer', 'min' => 1, 'max' => 10000],
            [['maxRetryAttempts'], 'integer', 'min' => 0, 'max' => 10],
            [['excludedPaths', 'excludedIpRanges'], 'each', 'rule' => ['string']],
            [['geoIpDatabasePath', 'defaultDateRange', 'logLevel'], 'string'],
            [['defaultDateRange'], 'in', 'range' => array_column(DateRange::cases(), 'value')],
            [['logLevel'], 'in', 'range' => array_column(LogLevel::cases(), 'value')],
        ];
    }
}
