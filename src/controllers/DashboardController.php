<?php

namespace samuelreichor\insights\controllers;

use Craft;
use craft\helpers\AdminTable;
use craft\web\Controller;
use samuelreichor\insights\enums\Permission;
use samuelreichor\insights\Insights;
use yii\web\Response;

/**
 * Dashboard Controller
 *
 * Handles the Control Panel dashboard and API endpoints.
 */
class DashboardController extends Controller
{
    /**
     * Main dashboard view.
     */
    public function actionIndex(): Response
    {
        $this->requireAnyDashboardPermission();

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = $this->resolveSiteId();
        $range = $request->getQueryParam('range', $settings->defaultDateRange);

        $stats = Insights::getInstance()->stats;
        $isPro = Insights::getInstance()->isPro();

        // Preview data for Lite users (row count only, content is placeholder)
        $proPreviews = !$isPro ? $stats->getProFeaturePreviews($siteId, $range) : null;

        return $this->renderTemplate('insights/_index', [
            'summary' => $stats->getSummary($siteId, $range),
            'chartData' => $stats->getChartData($siteId, $range),
            'topPages' => $stats->getTopPages($siteId, $range, 10),
            'topReferrers' => $stats->getTopReferrers($siteId, $range, 10),
            'topCampaigns' => $stats->getTopCampaigns($siteId, $range, 10),
            'topCountries' => $stats->getTopCountries($siteId, $range, 10),
            'topEvents' => $stats->getTopEvents($siteId, $range, 10),
            'topOutboundLinks' => $stats->getTopOutboundLinks($siteId, $range, 10),
            'topSearches' => $stats->getTopSearches($siteId, $range, 10),
            'devices' => $stats->getDeviceBreakdown($siteId, $range),
            'browsers' => $stats->getBrowserBreakdown($siteId, $range),
            'realtime' => $stats->getRealtimeVisitors($siteId),
            'selectedSiteId' => $siteId,
            'selectedRange' => $range,
            'settings' => $settings,
            'proPreviews' => $proPreviews,
        ]);
    }

    /**
     * Pages detail view.
     */
    public function actionPages(): Response
    {
        $this->requirePermission(Permission::ViewPages->value);

        $settings = Insights::getInstance()->getSettings();
        $siteId = $this->resolveSiteId();
        $range = Craft::$app->getRequest()->getQueryParam('range', $settings->defaultDateRange);

        return $this->renderTemplate('insights/pages/_index', [
            'selectedSiteId' => $siteId,
            'selectedRange' => $range,
        ]);
    }

    /**
     * Referrers detail view.
     */
    public function actionReferrers(): Response
    {
        $this->requirePermission(Permission::ViewReferrers->value);

        $settings = Insights::getInstance()->getSettings();
        $siteId = $this->resolveSiteId();
        $range = Craft::$app->getRequest()->getQueryParam('range', $settings->defaultDateRange);

        return $this->renderTemplate('insights/referrers/_index', [
            'selectedSiteId' => $siteId,
            'selectedRange' => $range,
        ]);
    }

    /**
     * Campaigns detail view.
     */
    public function actionCampaigns(): Response
    {
        $this->requirePermission(Permission::ViewCampaigns->value);

        $settings = Insights::getInstance()->getSettings();
        $siteId = $this->resolveSiteId();
        $range = Craft::$app->getRequest()->getQueryParam('range', $settings->defaultDateRange);

        return $this->renderTemplate('insights/campaigns/_index', [
            'selectedSiteId' => $siteId,
            'selectedRange' => $range,
        ]);
    }

    /**
     * Events detail view (Pro only).
     */
    public function actionEvents(): Response
    {
        $this->requirePermission(Permission::ViewEvents->value);

        if (!Insights::getInstance()->isPro()) {
            return $this->redirect('insights');
        }

        $settings = Insights::getInstance()->getSettings();
        $siteId = $this->resolveSiteId();
        $range = Craft::$app->getRequest()->getQueryParam('range', $settings->defaultDateRange);

        return $this->renderTemplate('insights/events/_index', [
            'selectedSiteId' => $siteId,
            'selectedRange' => $range,
        ]);
    }

    /**
     * Countries detail view (Pro only).
     */
    public function actionCountries(): Response
    {
        $this->requirePermission(Permission::ViewCountries->value);

        if (!Insights::getInstance()->isPro()) {
            return $this->redirect('insights');
        }

        $settings = Insights::getInstance()->getSettings();
        $siteId = $this->resolveSiteId();
        $range = Craft::$app->getRequest()->getQueryParam('range', $settings->defaultDateRange);

        return $this->renderTemplate('insights/countries/_index', [
            'selectedSiteId' => $siteId,
            'selectedRange' => $range,
        ]);
    }

    /**
     * Outbound links detail view (Pro only).
     */
    public function actionOutbound(): Response
    {
        $this->requirePermission(Permission::ViewOutbound->value);

        if (!Insights::getInstance()->isPro()) {
            return $this->redirect('insights');
        }

        $settings = Insights::getInstance()->getSettings();
        $siteId = $this->resolveSiteId();
        $range = Craft::$app->getRequest()->getQueryParam('range', $settings->defaultDateRange);

        return $this->renderTemplate('insights/outbound/_index', [
            'selectedSiteId' => $siteId,
            'selectedRange' => $range,
        ]);
    }

    /**
     * Get realtime data (AJAX endpoint).
     */
    public function actionRealtimeData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission(Permission::ViewDashboardRealtime->value);

        $siteId = (int)(Craft::$app->getRequest()->getQueryParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);

        $realtime = Insights::getInstance()->stats->getRealtimeVisitors($siteId);

        return $this->asJson($realtime);
    }

    /**
     * Get all dashboard data (AJAX endpoint for live updates).
     */
    public function actionDashboardData(): Response
    {
        $this->requireAcceptsJson();
        $this->requireAnyDashboardPermission();

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();
        $user = Craft::$app->getUser()->getIdentity();

        $siteId = (int)($request->getQueryParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getQueryParam('range', $settings->defaultDateRange);

        $stats = Insights::getInstance()->stats;
        $isPro = Insights::getInstance()->isPro();

        $data = [];

        // Summary/KPIs
        if ($user->can(Permission::ViewDashboardKpis->value)) {
            $data['summary'] = $stats->getSummary($siteId, $range);
        }

        // Chart data
        if ($user->can(Permission::ViewDashboardChart->value)) {
            $data['chartData'] = $stats->getChartData($siteId, $range);
        }

        // Realtime
        if ($user->can(Permission::ViewDashboardRealtime->value)) {
            $data['realtime'] = $stats->getRealtimeVisitors($siteId);
        }

        // Top Pages
        if ($user->can(Permission::ViewDashboardPages->value)) {
            $data['topPages'] = $stats->getTopPages($siteId, $range, 10);
        }

        // Top Referrers
        if ($user->can(Permission::ViewDashboardReferrers->value)) {
            $data['topReferrers'] = $stats->getTopReferrers($siteId, $range, 10);
        }

        // Devices & Browsers
        if ($user->can(Permission::ViewDashboardDevices->value)) {
            $data['devices'] = $stats->getDeviceBreakdown($siteId, $range);
            $data['browsers'] = $stats->getBrowserBreakdown($siteId, $range);
        }

        // Pro features
        if ($isPro) {
            if ($user->can(Permission::ViewDashboardCampaigns->value)) {
                $data['topCampaigns'] = $stats->getTopCampaigns($siteId, $range, 10);
            }
            if ($user->can(Permission::ViewDashboardCountries->value)) {
                $topCountries = $stats->getTopCountries($siteId, $range, 10);
                $variable = new \samuelreichor\insights\variables\InsightsVariable();
                $data['topCountries'] = array_map(function($country) use ($variable) {
                    return [
                        'countryCode' => $country['countryCode'],
                        'visits' => $country['visits'],
                        'flag' => $variable->getCountryFlag($country['countryCode']),
                        'name' => $variable->getCountryName($country['countryCode']),
                    ];
                }, $topCountries);
            }
            if ($user->can(Permission::ViewDashboardEvents->value)) {
                $data['topEvents'] = $stats->getTopEvents($siteId, $range, 10);
            }
            if ($user->can(Permission::ViewDashboardOutbound->value)) {
                $data['topOutboundLinks'] = $stats->getTopOutboundLinks($siteId, $range, 10);
            }
            if ($user->can(Permission::ViewDashboardSearches->value)) {
                $data['topSearches'] = $stats->getTopSearches($siteId, $range, 10);
            }
        } else {
            // Preview data for Lite users (row count only, content is placeholder)
            $data['proPreviews'] = $stats->getProFeaturePreviews($siteId, $range);
        }

        return $this->asJson($data);
    }

    /**
     * Get chart data (AJAX endpoint).
     */
    public function actionChartData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission(Permission::ViewDashboardChart->value);

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getQueryParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getQueryParam('range', $settings->defaultDateRange);

        $chartData = Insights::getInstance()->stats->getChartData($siteId, $range);

        return $this->asJson($chartData);
    }

    /**
     * Get hourly breakdown (AJAX endpoint).
     */
    public function actionHourlyData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission(Permission::ViewDashboardChart->value);

        $siteId = (int)(Craft::$app->getRequest()->getQueryParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);

        $hourly = Insights::getInstance()->stats->getHourlyBreakdown($siteId);

        return $this->asJson($hourly);
    }

    /**
     * Get pages table data (API endpoint for Vue Admin Table).
     */
    public function actionPagesTableData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission(Permission::ViewPages->value);

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getParam('range', $settings->defaultDateRange);
        $page = (int)$request->getParam('page', 1);
        $limit = (int)$request->getParam('per_page', 100);
        $search = $request->getParam('search');
        $sort = $request->getParam('sort');

        $stats = Insights::getInstance()->stats;
        $allPages = $stats->getTopPages($siteId, $range, 1000);

        // Apply search filter
        if ($search) {
            $allPages = array_filter($allPages, function($pageData) use ($search) {
                return stripos($pageData['url'], $search) !== false;
            });
            $allPages = array_values($allPages);
        }

        // Apply sorting
        if (!empty($sort) && is_array($sort) && isset($sort[0]['field'])) {
            $sortField = $sort[0]['field'];
            $sortDir = $sort[0]['direction'] ?? 'desc';

            usort($allPages, function($a, $b) use ($sortField, $sortDir) {
                $aVal = $this->getPageSortValue($a, $sortField);
                $bVal = $this->getPageSortValue($b, $sortField);

                if ($aVal === $bVal) {
                    return 0;
                }

                $result = $aVal <=> $bVal;
                return $sortDir === 'asc' ? $result : -$result;
            });
        }

        $total = count($allPages);
        $offset = ($page - 1) * $limit;
        $pages = array_slice($allPages, $offset, $limit);

        // Format data for the table
        $tableData = [];
        foreach ($pages as $pageData) {
            $views = (int)$pageData['views'];
            $uniqueVisitors = (int)$pageData['uniqueVisitors'];
            $totalTime = (int)$pageData['totalTime'];
            $bounces = (int)$pageData['bounces'];

            $avgTime = $views > 0 ? (int)round($totalTime / $views) : 0;
            $bounceRate = $uniqueVisitors > 0 ? round(($bounces / $uniqueVisitors) * 100, 1) : 0;

            $minutes = (int)floor($avgTime / 60);
            $seconds = $avgTime % 60;

            $tableData[] = [
                'id' => md5($pageData['url']),
                'title' => $pageData['url'],
                'url' => $pageData['url'],
                'views' => number_format($views),
                'uniqueVisitors' => number_format($uniqueVisitors),
                'avgTime' => "{$minutes}m {$seconds}s",
                'bounceRate' => "{$bounceRate}%",
                // Raw values for sorting
                '__sort' => [
                    'views' => $views,
                    'uniqueVisitors' => $uniqueVisitors,
                    'avgTime' => $avgTime,
                    'bounceRate' => $bounceRate,
                ],
            ];
        }

        return $this->asSuccess(data: [
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $tableData,
        ]);
    }

    /**
     * Get sort value for pages.
     */
    private function getPageSortValue(array $page, string $field): mixed
    {
        return match ($field) {
            'url' => strtolower($page['url']),
            'views' => (int)$page['views'],
            'uniqueVisitors' => (int)$page['uniqueVisitors'],
            'avgTime' => (int)$page['views'] > 0 ? (int)$page['totalTime'] / (int)$page['views'] : 0,
            'bounceRate' => (int)$page['uniqueVisitors'] > 0 ? (int)$page['bounces'] / (int)$page['uniqueVisitors'] : 0,
            default => 0,
        };
    }

    /**
     * Get referrers table data (API endpoint for Vue Admin Table).
     */
    public function actionReferrersTableData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission(Permission::ViewReferrers->value);

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getParam('range', $settings->defaultDateRange);
        $page = (int)$request->getParam('page', 1);
        $limit = (int)$request->getParam('per_page', 100);
        $search = $request->getParam('search');
        $sort = $request->getParam('sort');

        $stats = Insights::getInstance()->stats;
        $allReferrers = $stats->getTopReferrers($siteId, $range, 1000);

        // Apply search filter
        if ($search) {
            $allReferrers = array_filter($allReferrers, function($ref) use ($search) {
                $domain = $ref['referrerDomain'] ?? 'Direct';
                return stripos($domain, $search) !== false ||
                       stripos($ref['referrerType'], $search) !== false;
            });
            $allReferrers = array_values($allReferrers);
        }

        // Apply sorting
        if (!empty($sort) && is_array($sort) && isset($sort[0]['field'])) {
            $sortField = $sort[0]['field'];
            $sortDir = $sort[0]['direction'] ?? 'desc';

            usort($allReferrers, function($a, $b) use ($sortField, $sortDir) {
                $aVal = match ($sortField) {
                    'source' => strtolower($a['referrerDomain'] ?? 'direct'),
                    'type' => strtolower($a['referrerType']),
                    'visits' => (int)$a['visits'],
                    default => 0,
                };
                $bVal = match ($sortField) {
                    'source' => strtolower($b['referrerDomain'] ?? 'direct'),
                    'type' => strtolower($b['referrerType']),
                    'visits' => (int)$b['visits'],
                    default => 0,
                };

                if ($aVal === $bVal) {
                    return 0;
                }

                $result = $aVal <=> $bVal;
                return $sortDir === 'asc' ? $result : -$result;
            });
        }

        $total = count($allReferrers);
        $offset = ($page - 1) * $limit;
        $referrers = array_slice($allReferrers, $offset, $limit);

        // Format data for the table
        $tableData = [];
        foreach ($referrers as $ref) {
            $domain = $ref['referrerDomain'] ?? 'Direct';
            $tableData[] = [
                'id' => md5($domain . $ref['referrerType']),
                'title' => $domain,
                'source' => $domain,
                'type' => $ref['referrerType'],
                'visits' => number_format((int)$ref['visits']),
            ];
        }

        return $this->asSuccess(data: [
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $tableData,
        ]);
    }

    /**
     * Get campaigns table data (API endpoint for Vue Admin Table).
     */
    public function actionCampaignsTableData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission(Permission::ViewCampaigns->value);

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getParam('range', $settings->defaultDateRange);
        $page = (int)$request->getParam('page', 1);
        $limit = (int)$request->getParam('per_page', 100);
        $search = $request->getParam('search');
        $sort = $request->getParam('sort');

        $stats = Insights::getInstance()->stats;
        $allCampaigns = $stats->getTopCampaigns($siteId, $range, 1000);

        // Apply search filter
        if ($search) {
            $allCampaigns = array_filter($allCampaigns, function($campaign) use ($search) {
                return stripos($campaign['utmSource'] ?? '', $search) !== false ||
                       stripos($campaign['utmMedium'] ?? '', $search) !== false ||
                       stripos($campaign['utmCampaign'] ?? '', $search) !== false;
            });
            $allCampaigns = array_values($allCampaigns);
        }

        // Apply sorting
        if (!empty($sort) && is_array($sort) && isset($sort[0]['field'])) {
            $sortField = $sort[0]['field'];
            $sortDir = $sort[0]['direction'] ?? 'desc';

            usort($allCampaigns, function($a, $b) use ($sortField, $sortDir) {
                $aVal = match ($sortField) {
                    'source' => strtolower($a['utmSource'] ?? '-'),
                    'medium' => strtolower($a['utmMedium'] ?? '-'),
                    'campaign' => strtolower($a['utmCampaign'] ?? '-'),
                    'visits' => (int)$a['visits'],
                    default => 0,
                };
                $bVal = match ($sortField) {
                    'source' => strtolower($b['utmSource'] ?? '-'),
                    'medium' => strtolower($b['utmMedium'] ?? '-'),
                    'campaign' => strtolower($b['utmCampaign'] ?? '-'),
                    'visits' => (int)$b['visits'],
                    default => 0,
                };

                if ($aVal === $bVal) {
                    return 0;
                }

                $result = $aVal <=> $bVal;
                return $sortDir === 'asc' ? $result : -$result;
            });
        }

        $total = count($allCampaigns);
        $offset = ($page - 1) * $limit;
        $campaigns = array_slice($allCampaigns, $offset, $limit);

        // Format data for the table
        $tableData = [];
        foreach ($campaigns as $campaign) {
            $source = $campaign['utmSource'] ?? '-';
            $medium = $campaign['utmMedium'] ?? '-';
            $campaignName = $campaign['utmCampaign'] ?? '-';

            $tableData[] = [
                'id' => md5($source . $medium . $campaignName),
                'title' => $source,
                'source' => $source,
                'medium' => $medium,
                'campaign' => $campaignName,
                'visits' => number_format((int)$campaign['visits']),
            ];
        }

        return $this->asSuccess(data: [
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $tableData,
        ]);
    }

    /**
     * Get events table data (API endpoint for Vue Admin Table).
     */
    public function actionEventsTableData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission(Permission::ViewEvents->value);

        if (!Insights::getInstance()->isPro()) {
            return $this->asSuccess(data: [
                'pagination' => AdminTable::paginationLinks(1, 0, 50),
                'data' => [],
            ]);
        }

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getParam('range', $settings->defaultDateRange);
        $page = (int)$request->getParam('page', 1);
        $limit = (int)$request->getParam('per_page', 100);
        $search = $request->getParam('search');
        $sort = $request->getParam('sort');

        $stats = Insights::getInstance()->stats;
        $allEvents = $stats->getAllEvents($siteId, $range);

        // Apply search filter
        if ($search) {
            $allEvents = array_filter($allEvents, function($event) use ($search) {
                return stripos($event['eventName'], $search) !== false ||
                       stripos($event['eventCategory'] ?? '', $search) !== false ||
                       stripos($event['url'], $search) !== false;
            });
            $allEvents = array_values($allEvents);
        }

        // Apply sorting
        if (!empty($sort) && is_array($sort) && isset($sort[0]['field'])) {
            $sortField = $sort[0]['field'];
            $sortDir = $sort[0]['direction'] ?? 'desc';

            usort($allEvents, function($a, $b) use ($sortField, $sortDir) {
                $aVal = match ($sortField) {
                    'eventName' => strtolower($a['eventName']),
                    'eventCategory' => strtolower($a['eventCategory'] ?? ''),
                    'url' => strtolower($a['url']),
                    'count' => (int)$a['count'],
                    'uniqueVisitors' => (int)$a['uniqueVisitors'],
                    default => 0,
                };
                $bVal = match ($sortField) {
                    'eventName' => strtolower($b['eventName']),
                    'eventCategory' => strtolower($b['eventCategory'] ?? ''),
                    'url' => strtolower($b['url']),
                    'count' => (int)$b['count'],
                    'uniqueVisitors' => (int)$b['uniqueVisitors'],
                    default => 0,
                };

                if ($aVal === $bVal) {
                    return 0;
                }

                $result = $aVal <=> $bVal;
                return $sortDir === 'asc' ? $result : -$result;
            });
        }

        $total = count($allEvents);
        $offset = ($page - 1) * $limit;
        $events = array_slice($allEvents, $offset, $limit);

        // Format data for the table
        $tableData = [];
        foreach ($events as $event) {
            $tableData[] = [
                'id' => md5($event['eventName'] . ($event['eventCategory'] ?? '') . $event['url']),
                'title' => $event['eventName'],
                'eventName' => $event['eventName'],
                'eventCategory' => $event['eventCategory'] ?? '-',
                'url' => $event['url'],
                'count' => number_format((int)$event['count']),
                'uniqueVisitors' => number_format((int)$event['uniqueVisitors']),
            ];
        }

        return $this->asSuccess(data: [
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $tableData,
        ]);
    }

    /**
     * Get countries table data (API endpoint for Vue Admin Table).
     */
    public function actionCountriesTableData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission(Permission::ViewCountries->value);

        if (!Insights::getInstance()->isPro()) {
            return $this->asSuccess(data: [
                'pagination' => AdminTable::paginationLinks(1, 0, 50),
                'data' => [],
            ]);
        }

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getParam('range', $settings->defaultDateRange);
        $page = (int)$request->getParam('page', 1);
        $limit = (int)$request->getParam('per_page', 100);
        $search = $request->getParam('search');
        $sort = $request->getParam('sort');

        $stats = Insights::getInstance()->stats;
        $allCountries = $stats->getTopCountries($siteId, $range, 1000);

        // Get country names for search and display
        $variable = new \samuelreichor\insights\variables\InsightsVariable();

        // Apply search filter
        if ($search) {
            $allCountries = array_filter($allCountries, function($country) use ($search, $variable) {
                $countryName = $variable->getCountryName($country['countryCode']);
                return stripos($countryName, $search) !== false ||
                       stripos($country['countryCode'], $search) !== false;
            });
            $allCountries = array_values($allCountries);
        }

        // Apply sorting
        if (!empty($sort) && is_array($sort) && isset($sort[0]['field'])) {
            $sortField = $sort[0]['field'];
            $sortDir = $sort[0]['direction'] ?? 'desc';

            usort($allCountries, function($a, $b) use ($sortField, $sortDir, $variable) {
                $aVal = match ($sortField) {
                    'country' => strtolower($variable->getCountryName($a['countryCode'])),
                    'visits' => (int)$a['visits'],
                    default => 0,
                };
                $bVal = match ($sortField) {
                    'country' => strtolower($variable->getCountryName($b['countryCode'])),
                    'visits' => (int)$b['visits'],
                    default => 0,
                };

                if ($aVal === $bVal) {
                    return 0;
                }

                $result = $aVal <=> $bVal;
                return $sortDir === 'asc' ? $result : -$result;
            });
        }

        $total = count($allCountries);
        $offset = ($page - 1) * $limit;
        $countries = array_slice($allCountries, $offset, $limit);

        // Format data for the table
        $tableData = [];
        foreach ($countries as $country) {
            $flag = $variable->getCountryFlag($country['countryCode']);
            $name = $variable->getCountryName($country['countryCode']);

            $tableData[] = [
                'id' => $country['countryCode'],
                'title' => $name,
                'country' => $flag . ' ' . $name,
                'visits' => number_format((int)$country['visits']),
            ];
        }

        return $this->asSuccess(data: [
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $tableData,
        ]);
    }

    /**
     * Get outbound links table data (API endpoint for Vue Admin Table).
     */
    public function actionOutboundTableData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission(Permission::ViewOutbound->value);

        if (!Insights::getInstance()->isPro()) {
            return $this->asSuccess(data: [
                'pagination' => AdminTable::paginationLinks(1, 0, 50),
                'data' => [],
            ]);
        }

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getParam('range', $settings->defaultDateRange);
        $page = (int)$request->getParam('page', 1);
        $limit = (int)$request->getParam('per_page', 100);
        $search = $request->getParam('search');
        $sort = $request->getParam('sort');

        $stats = Insights::getInstance()->stats;
        $allOutbound = $stats->getAllOutboundLinks($siteId, $range);

        // Apply search filter
        if ($search) {
            $allOutbound = array_filter($allOutbound, function($link) use ($search) {
                return stripos($link['targetUrl'], $search) !== false ||
                       stripos($link['targetDomain'], $search) !== false ||
                       stripos($link['linkText'] ?? '', $search) !== false ||
                       stripos($link['sourceUrl'], $search) !== false;
            });
            $allOutbound = array_values($allOutbound);
        }

        // Apply sorting
        if (!empty($sort) && is_array($sort) && isset($sort[0]['field'])) {
            $sortField = $sort[0]['field'];
            $sortDir = $sort[0]['direction'] ?? 'desc';

            usort($allOutbound, function($a, $b) use ($sortField, $sortDir) {
                $aVal = match ($sortField) {
                    'targetDomain' => strtolower($a['targetDomain']),
                    'targetUrl' => strtolower($a['targetUrl']),
                    'linkText' => strtolower($a['linkText'] ?? ''),
                    'sourceUrl' => strtolower($a['sourceUrl']),
                    'clicks' => (int)$a['clicks'],
                    'uniqueVisitors' => (int)$a['uniqueVisitors'],
                    default => 0,
                };
                $bVal = match ($sortField) {
                    'targetDomain' => strtolower($b['targetDomain']),
                    'targetUrl' => strtolower($b['targetUrl']),
                    'linkText' => strtolower($b['linkText'] ?? ''),
                    'sourceUrl' => strtolower($b['sourceUrl']),
                    'clicks' => (int)$b['clicks'],
                    'uniqueVisitors' => (int)$b['uniqueVisitors'],
                    default => 0,
                };

                if ($aVal === $bVal) {
                    return 0;
                }

                $result = $aVal <=> $bVal;
                return $sortDir === 'asc' ? $result : -$result;
            });
        }

        $total = count($allOutbound);
        $offset = ($page - 1) * $limit;
        $outbound = array_slice($allOutbound, $offset, $limit);

        // Format data for the table
        $tableData = [];
        foreach ($outbound as $link) {
            $tableData[] = [
                'id' => md5($link['targetUrl'] . $link['sourceUrl']),
                'title' => $link['targetDomain'],
                'targetDomain' => $link['targetDomain'],
                'targetUrl' => $link['targetUrl'],
                'linkText' => $link['linkText'] ?? '-',
                'sourceUrl' => $link['sourceUrl'],
                'clicks' => number_format((int)$link['clicks']),
                'uniqueVisitors' => number_format((int)$link['uniqueVisitors']),
            ];
        }

        return $this->asSuccess(data: [
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $tableData,
        ]);
    }

    /**
     * Site searches detail view (Pro only).
     */
    public function actionSearches(): Response
    {
        $this->requirePermission(Permission::ViewSearches->value);

        if (!Insights::getInstance()->isPro()) {
            return $this->redirect('insights');
        }

        $settings = Insights::getInstance()->getSettings();
        $siteId = $this->resolveSiteId();
        $range = Craft::$app->getRequest()->getQueryParam('range', $settings->defaultDateRange);

        return $this->renderTemplate('insights/searches/_index', [
            'selectedSiteId' => $siteId,
            'selectedRange' => $range,
        ]);
    }

    /**
     * Get searches table data (API endpoint for Vue Admin Table).
     */
    public function actionSearchesTableData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission(Permission::ViewSearches->value);

        if (!Insights::getInstance()->isPro()) {
            return $this->asSuccess(data: [
                'pagination' => AdminTable::paginationLinks(1, 0, 50),
                'data' => [],
            ]);
        }

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getParam('range', $settings->defaultDateRange);
        $page = (int)$request->getParam('page', 1);
        $limit = (int)$request->getParam('per_page', 100);
        $search = $request->getParam('search');
        $sort = $request->getParam('sort');

        $stats = Insights::getInstance()->stats;
        $allSearches = $stats->getAllSearches($siteId, $range);

        // Apply search filter
        if ($search) {
            $allSearches = array_filter($allSearches, function($searchData) use ($search) {
                return stripos($searchData['searchTerm'], $search) !== false;
            });
            $allSearches = array_values($allSearches);
        }

        // Apply sorting
        if (!empty($sort) && is_array($sort) && isset($sort[0]['field'])) {
            $sortField = $sort[0]['field'];
            $sortDir = $sort[0]['direction'] ?? 'desc';

            usort($allSearches, function($a, $b) use ($sortField, $sortDir) {
                $aVal = match ($sortField) {
                    'searchTerm' => strtolower($a['searchTerm']),
                    'resultsCount' => (int)($a['resultsCount'] ?? 0),
                    'searches' => (int)$a['searches'],
                    'uniqueVisitors' => (int)$a['uniqueVisitors'],
                    default => 0,
                };
                $bVal = match ($sortField) {
                    'searchTerm' => strtolower($b['searchTerm']),
                    'resultsCount' => (int)($b['resultsCount'] ?? 0),
                    'searches' => (int)$b['searches'],
                    'uniqueVisitors' => (int)$b['uniqueVisitors'],
                    default => 0,
                };

                if ($aVal === $bVal) {
                    return 0;
                }

                $result = $aVal <=> $bVal;
                return $sortDir === 'asc' ? $result : -$result;
            });
        }

        $total = count($allSearches);
        $offset = ($page - 1) * $limit;
        $searches = array_slice($allSearches, $offset, $limit);

        // Format data for the table
        $tableData = [];
        foreach ($searches as $searchData) {
            $tableData[] = [
                'id' => md5($searchData['searchTerm'] . ($searchData['resultsCount'] ?? '')),
                'title' => $searchData['searchTerm'],
                'searchTerm' => $searchData['searchTerm'],
                'resultsCount' => $searchData['resultsCount'] !== null ? number_format((int)$searchData['resultsCount']) : '-',
                'searches' => number_format((int)$searchData['searches']),
                'uniqueVisitors' => number_format((int)$searchData['uniqueVisitors']),
            ];
        }

        return $this->asSuccess(data: [
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $tableData,
        ]);
    }

    /**
     * Resolve site ID from request parameters.
     *
     * Supports both `site` (handle) and `siteId` parameters.
     * Falls back to current site if neither is provided.
     */
    private function resolveSiteId(): int
    {
        $request = Craft::$app->getRequest();

        // Check for site handle first (native Craft pattern)
        $siteHandle = $request->getQueryParam('site');
        if ($siteHandle) {
            $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
            if ($site) {
                return $site->id;
            }
        }

        // Fall back to siteId parameter
        $siteId = $request->getQueryParam('siteId');
        if ($siteId) {
            return (int)$siteId;
        }

        // Default to current site
        return Craft::$app->getSites()->getCurrentSite()->id;
    }

    /**
     * Require that the user has at least one dashboard permission.
     *
     * @throws \yii\web\ForbiddenHttpException
     */
    private function requireAnyDashboardPermission(): void
    {
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user) {
            throw new \yii\web\ForbiddenHttpException('User is not logged in.');
        }

        // Check parent permission first
        if ($user->can(Permission::ViewDashboard->value)) {
            return;
        }

        $isPro = Insights::getInstance()->isPro();

        // Check individual card permissions
        foreach (Permission::dashboardPermissions() as $permission) {
            // Skip Pro-only permissions if not Pro edition
            if ($permission->isPro() && !$isPro) {
                continue;
            }

            if ($user->can($permission->value)) {
                return;
            }
        }

        throw new \yii\web\ForbiddenHttpException('You do not have permission to view the dashboard.');
    }
}
