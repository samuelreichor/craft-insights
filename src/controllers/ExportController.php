<?php

namespace samuelreichor\insights\controllers;

use Craft;
use craft\web\Controller;
use samuelreichor\insights\Insights;
use samuelreichor\insights\services\StatsService;
use yii\web\Response;

/**
 * Export Controller
 *
 * Handles data export in CSV and JSON formats.
 */
class ExportController extends Controller
{
    public function actionPageviews(): Response
    {
        return $this->handleExport(fn(StatsService $stats, int $siteId, string $range) => $stats->getTopPages($siteId, $range, 1000), 'pageviews');
    }

    public function actionReferrers(): Response
    {
        return $this->handleExport(fn(StatsService $stats, int $siteId, string $range) => $stats->getTopReferrers($siteId, $range, 1000), 'referrers');
    }

    public function actionCampaigns(): Response
    {
        return $this->handleExport(fn(StatsService $stats, int $siteId, string $range) => $stats->getTopCampaigns($siteId, $range, 1000), 'campaigns');
    }

    public function actionCountries(): Response
    {
        return $this->handleExport(fn(StatsService $stats, int $siteId, string $range) => $stats->getTopCountries($siteId, $range, 1000), 'countries');
    }

    public function actionEntryPages(): Response
    {
        return $this->handleExport(fn(StatsService $stats, int $siteId, string $range) => $stats->getTopEntryPages($siteId, $range, 1000), 'entry-pages');
    }

    public function actionExitPages(): Response
    {
        return $this->handleExport(fn(StatsService $stats, int $siteId, string $range) => $stats->getTopExitPages($siteId, $range, 1000), 'exit-pages');
    }

    public function actionScrollDepth(): Response
    {
        return $this->handleExport(fn(StatsService $stats, int $siteId, string $range) => $stats->getScrollDepth($siteId, $range, 1000), 'scroll-depth');
    }

    public function actionEvents(): Response
    {
        return $this->handleExport(fn(StatsService $stats, int $siteId, string $range) => $stats->getTopEvents($siteId, $range, 1000), 'events');
    }

    public function actionOutbound(): Response
    {
        return $this->handleExport(fn(StatsService $stats, int $siteId, string $range) => $stats->getTopOutboundLinks($siteId, $range, 1000), 'outbound');
    }

    public function actionSearches(): Response
    {
        return $this->handleExport(fn(StatsService $stats, int $siteId, string $range) => $stats->getTopSearches($siteId, $range, 1000), 'searches');
    }

    /**
     * Handle export with common boilerplate.
     *
     * @param callable(StatsService, int, string): array<int, array<string, mixed>> $dataFetcher
     */
    private function handleExport(callable $dataFetcher, string $type): Response
    {
        $this->requirePermission('insights:exportData');

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getQueryParam('siteId') ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getQueryParam('range', $settings->defaultDateRange);
        $format = $request->getQueryParam('format', 'csv');

        $stats = Insights::getInstance()->stats;
        $data = $dataFetcher($stats, $siteId, $range);

        return $this->exportData($data, "insights-{$type}-{$range}", $format);
    }

    /**
     * Export all data as a combined report.
     */
    public function actionAll(): Response
    {
        $this->requirePermission('insights:exportData');

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getQueryParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getQueryParam('range', $settings->defaultDateRange);

        $stats = Insights::getInstance()->stats;

        $data = [
            'summary' => $stats->getSummary($siteId, $range),
            'pages' => $stats->getTopPages($siteId, $range, 100),
            'referrers' => $stats->getTopReferrers($siteId, $range, 100),
            'campaigns' => $stats->getTopCampaigns($siteId, $range, 100),
            'countries' => $stats->getTopCountries($siteId, $range, 100),
            'devices' => $stats->getDeviceBreakdown($siteId, $range),
            'browsers' => $stats->getBrowserBreakdown($siteId, $range),
            'exportedAt' => date('Y-m-d H:i:s'),
            'range' => $range,
            'siteId' => $siteId,
        ];

        $filename = "insights-report-{$range}";

        return $this->asJson($data)
            ->setDownloadHeaders($filename . '.json');
    }

    /**
     * Export data in specified format.
     *
     * @param array<int, array<string, mixed>> $data
     */
    private function exportData(array $data, string $filename, string $format): Response
    {
        if ($format === 'json') {
            return $this->asJson($data)
                ->setDownloadHeaders($filename . '.json');
        }

        // CSV export
        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->setDownloadHeaders($filename . '.csv', 'text/csv');

        $output = fopen('php://temp', 'r+');

        if ($output === false) {
            return $this->asJson(['error' => 'Failed to create output']);
        }

        // Write header row
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
        }

        // Write data rows
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
