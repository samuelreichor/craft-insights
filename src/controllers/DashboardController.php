<?php

namespace samuelreichor\insights\controllers;

use Craft;
use craft\helpers\AdminTable;
use craft\web\Controller;
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
        $this->requirePermission('insights:viewDashboard');

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = $request->getQueryParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id;
        $siteId = (int)$siteId;

        $range = $request->getQueryParam('range', $settings->defaultDateRange);

        $stats = Insights::getInstance()->stats;

        return $this->renderTemplate('insights/_index', [
            'summary' => $stats->getSummary($siteId, $range),
            'chartData' => $stats->getChartData($siteId, $range),
            'topPages' => $stats->getTopPages($siteId, $range, 10),
            'topReferrers' => $stats->getTopReferrers($siteId, $range, 10),
            'topCountries' => $stats->getTopCountries($siteId, $range, 10),
            'devices' => $stats->getDeviceBreakdown($siteId, $range),
            'browsers' => $stats->getBrowserBreakdown($siteId, $range),
            'realtime' => $stats->getRealtimeVisitors($siteId),
            'selectedSiteId' => $siteId,
            'selectedRange' => $range,
            'sites' => Craft::$app->getSites()->getAllSites(),
            'settings' => $settings,
        ]);
    }

    /**
     * Pages detail view.
     */
    public function actionPages(): Response
    {
        $this->requirePermission('insights:viewDashboard');

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getQueryParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getQueryParam('range', $settings->defaultDateRange);

        return $this->renderTemplate('insights/pages/_index', [
            'selectedSiteId' => $siteId,
            'selectedRange' => $range,
            'sites' => Craft::$app->getSites()->getAllSites(),
        ]);
    }

    /**
     * Referrers detail view.
     */
    public function actionReferrers(): Response
    {
        $this->requirePermission('insights:viewDashboard');

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getQueryParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getQueryParam('range', $settings->defaultDateRange);

        return $this->renderTemplate('insights/referrers/_index', [
            'selectedSiteId' => $siteId,
            'selectedRange' => $range,
            'sites' => Craft::$app->getSites()->getAllSites(),
        ]);
    }

    /**
     * Campaigns detail view.
     */
    public function actionCampaigns(): Response
    {
        $this->requirePermission('insights:viewDashboard');

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getQueryParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getQueryParam('range', $settings->defaultDateRange);

        return $this->renderTemplate('insights/campaigns/_index', [
            'selectedSiteId' => $siteId,
            'selectedRange' => $range,
            'sites' => Craft::$app->getSites()->getAllSites(),
        ]);
    }

    /**
     * Get realtime data (AJAX endpoint).
     */
    public function actionRealtimeData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission('insights:viewDashboard');

        $siteId = (int)(Craft::$app->getRequest()->getQueryParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);

        $realtime = Insights::getInstance()->stats->getRealtimeVisitors($siteId);

        return $this->asJson($realtime);
    }

    /**
     * Get chart data (AJAX endpoint).
     */
    public function actionChartData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission('insights:viewDashboard');

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
        $this->requirePermission('insights:viewDashboard');

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
        $this->requirePermission('insights:viewDashboard');

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
        $this->requirePermission('insights:viewDashboard');

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
        $this->requirePermission('insights:viewDashboard');

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
}
