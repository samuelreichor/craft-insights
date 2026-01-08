<?php

namespace samuelreichor\insights\enums;

/**
 * Permission enum for Insights plugin
 */
enum Permission: string
{
    // Dashboard Parent Permission
    case ViewDashboard = 'insights:viewDashboard';

    // Dashboard Card Permissions (nested under ViewDashboard)
    case ViewDashboardKpis = 'insights:viewDashboardKpis';
    case ViewDashboardRealtime = 'insights:viewDashboardRealtime';
    case ViewDashboardChart = 'insights:viewDashboardChart';
    case ViewDashboardPages = 'insights:viewDashboardPages';
    case ViewDashboardReferrers = 'insights:viewDashboardReferrers';
    case ViewDashboardDevices = 'insights:viewDashboardDevices';
    case ViewDashboardCampaigns = 'insights:viewDashboardCampaigns';
    case ViewDashboardCountries = 'insights:viewDashboardCountries';
    case ViewDashboardEvents = 'insights:viewDashboardEvents';
    case ViewDashboardOutbound = 'insights:viewDashboardOutbound';
    case ViewDashboardSearches = 'insights:viewDashboardSearches';

    // Detail Page Permissions
    case ViewPages = 'insights:viewPages';
    case ViewReferrers = 'insights:viewReferrers';
    case ViewCampaigns = 'insights:viewCampaigns';
    case ViewCountries = 'insights:viewCountries';
    case ViewEvents = 'insights:viewEvents';
    case ViewOutbound = 'insights:viewOutbound';
    case ViewSearches = 'insights:viewSearches';

    // Entry Sidebar
    case ViewEntryStats = 'insights:viewEntryStats';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            // Dashboard Parent
            self::ViewDashboard => 'View Dashboard',
            // Dashboard Cards
            self::ViewDashboardKpis => 'View Dashboard KPIs',
            self::ViewDashboardRealtime => 'View Dashboard Realtime',
            self::ViewDashboardChart => 'View Dashboard Chart',
            self::ViewDashboardPages => 'View Dashboard Pages',
            self::ViewDashboardReferrers => 'View Dashboard Referrers',
            self::ViewDashboardDevices => 'View Dashboard Devices',
            self::ViewDashboardCampaigns => 'View Dashboard Campaigns',
            self::ViewDashboardCountries => 'View Dashboard Countries',
            self::ViewDashboardEvents => 'View Dashboard Events',
            self::ViewDashboardOutbound => 'View Dashboard Outbound',
            self::ViewDashboardSearches => 'View Dashboard Searches',
            // Detail Pages
            self::ViewPages => 'View Pages',
            self::ViewReferrers => 'View Referrers',
            self::ViewCampaigns => 'View Campaigns',
            self::ViewCountries => 'View Countries',
            self::ViewEvents => 'View Events',
            self::ViewOutbound => 'View Outbound Links',
            self::ViewSearches => 'View Site Searches',
            // Entry Sidebar
            self::ViewEntryStats => 'View Entry Stats',
        };
    }

    /**
     * Get all permissions.
     *
     * @return Permission[]
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * Get all dashboard card permissions.
     *
     * @return Permission[]
     */
    public static function dashboardPermissions(): array
    {
        return [
            self::ViewDashboardKpis,
            self::ViewDashboardRealtime,
            self::ViewDashboardChart,
            self::ViewDashboardPages,
            self::ViewDashboardReferrers,
            self::ViewDashboardDevices,
            self::ViewDashboardCampaigns,
            self::ViewDashboardCountries,
            self::ViewDashboardEvents,
            self::ViewDashboardOutbound,
            self::ViewDashboardSearches,
        ];
    }

    /**
     * Get all detail page permissions.
     *
     * @return Permission[]
     */
    public static function pagePermissions(): array
    {
        return [
            self::ViewPages,
            self::ViewReferrers,
            self::ViewCampaigns,
            self::ViewCountries,
            self::ViewEvents,
            self::ViewOutbound,
            self::ViewSearches,
        ];
    }

    /**
     * Get all Pro-only permissions.
     *
     * @return Permission[]
     */
    public static function proPermissions(): array
    {
        return [
            // Dashboard Pro
            self::ViewDashboardCampaigns,
            self::ViewDashboardCountries,
            self::ViewDashboardEvents,
            self::ViewDashboardOutbound,
            self::ViewDashboardSearches,
            // Pages Pro
            self::ViewCampaigns,
            self::ViewCountries,
            self::ViewEvents,
            self::ViewOutbound,
            self::ViewSearches,
        ];
    }

    /**
     * Check if this permission is Pro-only.
     */
    public function isPro(): bool
    {
        return in_array($this, self::proPermissions(), true);
    }
}
