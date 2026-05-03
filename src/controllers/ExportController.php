<?php

namespace samuelreichor\insights\controllers;

use Craft;
use craft\web\Controller;
use samuelreichor\insights\enums\Permission;
use samuelreichor\insights\Insights;
use samuelreichor\insights\services\StatsService;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Export Controller
 *
 * Handles data export in CSV and PDF formats.
 */
class ExportController extends Controller
{
    public function actionPageviews(): Response
    {
        return $this->handleExport(
            fn(StatsService $stats, int $siteId, string $range) => $stats->getTopPages($siteId, $range, 1000),
            'pageviews',
            'Top Pages',
            [
                ['key' => 'url', 'label' => 'Page'],
                ['key' => 'views', 'label' => 'Views', 'numeric' => true],
                ['key' => 'uniqueVisitors', 'label' => 'Visitors', 'numeric' => true],
            ]
        );
    }

    public function actionReferrers(): Response
    {
        return $this->handleExport(
            fn(StatsService $stats, int $siteId, string $range) => $stats->getTopReferrers($siteId, $range, 1000),
            'referrers',
            'Traffic Sources',
            [
                ['key' => 'referrerDomain', 'label' => 'Source'],
                ['key' => 'referrerType', 'label' => 'Type'],
                ['key' => 'visits', 'label' => 'Visits', 'numeric' => true],
            ]
        );
    }

    public function actionCampaigns(): Response
    {
        return $this->handleExport(
            fn(StatsService $stats, int $siteId, string $range) => $stats->getTopCampaigns($siteId, $range, 1000),
            'campaigns',
            'Campaigns',
            [
                ['key' => 'utmSource', 'label' => 'Source'],
                ['key' => 'utmMedium', 'label' => 'Medium'],
                ['key' => 'utmCampaign', 'label' => 'Campaign'],
                ['key' => 'visits', 'label' => 'Visits', 'numeric' => true],
            ]
        );
    }

    public function actionCountries(): Response
    {
        return $this->handleExport(
            function(StatsService $stats, int $siteId, string $range): array {
                $rows = $stats->getTopCountries($siteId, $range, 1000);
                return Craft::$app->getRequest()->getQueryParam('format') === 'pdf'
                    ? Insights::getInstance()->pdf->enrichCountries($rows)
                    : $rows;
            },
            'countries',
            'Top Countries',
            [
                ['key' => 'countryCode', 'label' => 'Country'],
                ['key' => 'visits', 'label' => 'Visits', 'numeric' => true],
            ]
        );
    }

    public function actionEntryPages(): Response
    {
        return $this->handleExport(
            fn(StatsService $stats, int $siteId, string $range) => $stats->getTopEntryPages($siteId, $range, 1000),
            'entry-pages',
            'Entry Pages',
            [
                ['key' => 'url', 'label' => 'Page'],
                ['key' => 'sessions', 'label' => 'Sessions', 'numeric' => true],
            ]
        );
    }

    public function actionExitPages(): Response
    {
        return $this->handleExport(
            fn(StatsService $stats, int $siteId, string $range) => $stats->getTopExitPages($siteId, $range, 1000),
            'exit-pages',
            'Exit Pages',
            [
                ['key' => 'url', 'label' => 'Page'],
                ['key' => 'sessions', 'label' => 'Sessions', 'numeric' => true],
            ]
        );
    }

    public function actionScrollDepth(): Response
    {
        return $this->handleExport(
            fn(StatsService $stats, int $siteId, string $range) => $stats->getScrollDepth($siteId, $range, 1000),
            'scroll-depth',
            'Scroll Depth',
            [
                ['key' => 'url', 'label' => 'Page'],
                ['key' => 'milestone25', 'label' => '25%', 'numeric' => true],
                ['key' => 'milestone50', 'label' => '50%', 'numeric' => true],
                ['key' => 'milestone75', 'label' => '75%', 'numeric' => true],
                ['key' => 'milestone100', 'label' => '100%', 'numeric' => true],
            ]
        );
    }

    public function actionEvents(): Response
    {
        return $this->handleExport(
            fn(StatsService $stats, int $siteId, string $range) => $stats->getTopEvents($siteId, $range, 1000),
            'events',
            'Top Events',
            [
                ['key' => 'eventName', 'label' => 'Event'],
                ['key' => 'eventCategory', 'label' => 'Category'],
                ['key' => 'count', 'label' => 'Count', 'numeric' => true],
                ['key' => 'uniqueVisitors', 'label' => 'Visitors', 'numeric' => true],
            ]
        );
    }

    public function actionOutbound(): Response
    {
        return $this->handleExport(
            fn(StatsService $stats, int $siteId, string $range) => $stats->getTopOutboundLinks($siteId, $range, 1000),
            'outbound',
            'Outbound Links',
            [
                ['key' => 'targetDomain', 'label' => 'Domain'],
                ['key' => 'clicks', 'label' => 'Clicks', 'numeric' => true],
                ['key' => 'uniqueVisitors', 'label' => 'Visitors', 'numeric' => true],
            ]
        );
    }

    public function actionSearches(): Response
    {
        return $this->handleExport(
            fn(StatsService $stats, int $siteId, string $range) => $stats->getTopSearches($siteId, $range, 1000),
            'searches',
            'Site Searches',
            [
                ['key' => 'searchTerm', 'label' => 'Search Term'],
                ['key' => 'searches', 'label' => 'Searches', 'numeric' => true],
                ['key' => 'uniqueVisitors', 'label' => 'Visitors', 'numeric' => true],
            ]
        );
    }

    /**
     * Handle export with common boilerplate.
     *
     * @param callable(StatsService, int, string): array<int, array<string, mixed>> $dataFetcher
     * @param array<int, array{key: string, label: string, numeric?: bool}> $columns
     * @throws ForbiddenHttpException
     */
    private function handleExport(callable $dataFetcher, string $type, string $sectionLabel, array $columns): Response
    {
        $this->requirePermission(Permission::ExportData->value);

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();
        $pdf = Insights::getInstance()->pdf;

        $siteId = (int)($request->getQueryParam('siteId') ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getQueryParam('range', $settings->defaultDateRange);
        $format = $request->getQueryParam('format', 'csv');

        $stats = Insights::getInstance()->stats;
        $data = $dataFetcher($stats, $siteId, $range);

        $filename = "insights-{$type}-{$range}";

        if ($format === 'pdf') {
            return $pdf->render('insights/_pdf/section.twig', [
                'title' => Craft::t('insights', $sectionLabel),
                'sectionLabel' => $sectionLabel,
                'columns' => $columns,
                'rows' => $data,
                'siteName' => $pdf->getSiteName($siteId),
                'rangeLabel' => $pdf->getRangeLabel($range),
                'rangePeriod' => $pdf->getRangePeriod($range),
                'exportedAt' => date('Y-m-d H:i'),
            ], $filename);
        }

        return $this->exportCsv($data, $filename);
    }

    /**
     * Export the dashboard as a multi-section PDF report.
     * @throws ForbiddenHttpException
     */
    public function actionDashboard(): Response
    {
        $this->requirePermission(Permission::ExportData->value);

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getQueryParam('siteId') ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getQueryParam('range', $settings->defaultDateRange);

        $user = Craft::$app->getUser()->getIdentity();
        $pdf = Insights::getInstance()->pdf;

        return $pdf->render(
            'insights/_pdf/dashboard.twig',
            $pdf->buildDashboardData($siteId, $range, $user),
            "insights-dashboard-{$range}",
        );
    }

    /**
     * Stream the rows out as a CSV download.
     *
     * @param array<int, array<string, mixed>> $data
     */
    private function exportCsv(array $data, string $filename): Response
    {
        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->setDownloadHeaders($filename . '.csv', 'text/csv');

        $output = fopen('php://temp', 'r+');

        if ($output === false) {
            return $this->asJson(['error' => 'Failed to create output']);
        }

        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
        }

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $response->data = $csv;

        return $response;
    }
}
