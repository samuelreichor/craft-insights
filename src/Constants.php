<?php

namespace samuelreichor\insights;

/**
 * Plugin constants
 *
 * Centralized storage for infrastructure and configuration values.
 * For typed value sets, see the enums in src/enums/.
 */
final class Constants
{
    // Database Tables
    public const TABLE_PAGEVIEWS = '{{%insights_pageviews}}';
    public const TABLE_REFERRERS = '{{%insights_referrers}}';
    public const TABLE_CAMPAIGNS = '{{%insights_campaigns}}';
    public const TABLE_DEVICES = '{{%insights_devices}}';
    public const TABLE_COUNTRIES = '{{%insights_countries}}';
    public const TABLE_REALTIME = '{{%insights_realtime}}';
    public const TABLE_EVENTS = '{{%insights_events}}';
    public const TABLE_OUTBOUND = '{{%insights_outbound}}';
    public const TABLE_SEARCHES = '{{%insights_searches}}';
    public const TABLE_SCROLL_DEPTH = '{{%insights_scroll_depth}}';
    public const TABLE_SESSIONS = '{{%insights_sessions}}';
    public const TABLE_NOTIFICATION_LOG = '{{%insights_notification_log}}';
    public const TABLE_LLM_REQUESTS = '{{%insights_llm_requests}}';
    public const TABLE_LLM_BOTS = '{{%insights_llm_bots}}';

    // Cache Key Prefixes
    public const CACHE_DAILY_SALT = 'insights_salt_';
    public const CACHE_VISITOR = 'insights_v_';
    public const CACHE_LAST_CLEANUP = 'insights_last_cleanup';
    public const CACHE_NOTIFICATION_CHECK = 'insights_notification_check';

    // Default Values
    public const DEFAULT_UNKNOWN = 'Unknown';
    public const DEFAULT_PATH = '/';

    // Limits
    public const MAX_URL_LENGTH = 500;
    public const MAX_TIME_ON_PAGE = 3600; // 1 hour in seconds
    public const CLEANUP_INTERVAL = 86400; // 24 hours in seconds
    public const SESSION_TIMEOUT = 1800; // 30 minutes in seconds
    public const NOTIFICATION_CHECK_INTERVAL = 3600; // 1 hour in seconds
    public const NOTIFICATION_PENDING_TTL = 86400; // 24 hours in seconds

    /**
     * Get all table names as array.
     *
     * @return string[]
     */
    public static function getAllTables(): array
    {
        return [
            self::TABLE_PAGEVIEWS,
            self::TABLE_REFERRERS,
            self::TABLE_CAMPAIGNS,
            self::TABLE_DEVICES,
            self::TABLE_COUNTRIES,
            self::TABLE_REALTIME,
            self::TABLE_EVENTS,
            self::TABLE_OUTBOUND,
            self::TABLE_SEARCHES,
            self::TABLE_SCROLL_DEPTH,
            self::TABLE_SESSIONS,
            self::TABLE_NOTIFICATION_LOG,
            self::TABLE_LLM_REQUESTS,
            self::TABLE_LLM_BOTS,
        ];
    }
}
