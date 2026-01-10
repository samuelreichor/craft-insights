<?php

namespace samuelreichor\insights\controllers;

use Craft;
use craft\web\Controller;
use samuelreichor\insights\Insights;
use yii\web\Response;

/**
 * Export Controller
 *
 * Handles data export in CSV and JSON formats.
 */
class ExportController extends Controller
{
    /**
     * Export pageviews data.
     */
    public function actionPageviews(): Response
    {
        $this->requirePermission('insights:exportData');

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getQueryParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getQueryParam('range', $settings->defaultDateRange);
        $format = $request->getQueryParam('format', 'csv');

        $stats = Insights::getInstance()->stats;
        $data = $stats->getTopPages($siteId, $range, 1000);

        $filename = "insights-pageviews-{$range}";

        return $this->exportData($data, $filename, $format);
    }

    /**
     * Export referrers data.
     */
    public function actionReferrers(): Response
    {
        $this->requirePermission('insights:exportData');

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getQueryParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getQueryParam('range', $settings->defaultDateRange);
        $format = $request->getQueryParam('format', 'csv');

        $stats = Insights::getInstance()->stats;
        $data = $stats->getTopReferrers($siteId, $range, 1000);

        $filename = "insights-referrers-{$range}";

        return $this->exportData($data, $filename, $format);
    }

    /**
     * Export campaigns data.
     */
    public function actionCampaigns(): Response
    {
        $this->requirePermission('insights:exportData');

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getQueryParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getQueryParam('range', $settings->defaultDateRange);
        $format = $request->getQueryParam('format', 'csv');

        $stats = Insights::getInstance()->stats;
        $data = $stats->getTopCampaigns($siteId, $range, 1000);

        $filename = "insights-campaigns-{$range}";

        return $this->exportData($data, $filename, $format);
    }

    /**
     * Export countries data.
     */
    public function actionCountries(): Response
    {
        $this->requirePermission('insights:exportData');

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getQueryParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getQueryParam('range', $settings->defaultDateRange);
        $format = $request->getQueryParam('format', 'csv');

        $stats = Insights::getInstance()->stats;
        $data = $stats->getTopCountries($siteId, $range, 1000);

        $filename = "insights-countries-{$range}";

        return $this->exportData($data, $filename, $format);
    }

    /**
     * Export entry pages data.
     */
    public function actionEntryPages(): Response
    {
        $this->requirePermission('insights:exportData');

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getQueryParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getQueryParam('range', $settings->defaultDateRange);
        $format = $request->getQueryParam('format', 'csv');

        $stats = Insights::getInstance()->stats;
        $data = $stats->getTopEntryPages($siteId, $range, 1000);

        $filename = "insights-entry-pages-{$range}";

        return $this->exportData($data, $filename, $format);
    }

    /**
     * Export exit pages data.
     */
    public function actionExitPages(): Response
    {
        $this->requirePermission('insights:exportData');

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getQueryParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getQueryParam('range', $settings->defaultDateRange);
        $format = $request->getQueryParam('format', 'csv');

        $stats = Insights::getInstance()->stats;
        $data = $stats->getTopExitPages($siteId, $range, 1000);

        $filename = "insights-exit-pages-{$range}";

        return $this->exportData($data, $filename, $format);
    }

    /**
     * Export scroll depth data.
     */
    public function actionScrollDepth(): Response
    {
        $this->requirePermission('insights:exportData');

        $request = Craft::$app->getRequest();
        $settings = Insights::getInstance()->getSettings();

        $siteId = (int)($request->getQueryParam('siteId')
            ?? Craft::$app->getSites()->getCurrentSite()->id);
        $range = $request->getQueryParam('range', $settings->defaultDateRange);
        $format = $request->getQueryParam('format', 'csv');

        $stats = Insights::getInstance()->stats;
        $data = $stats->getScrollDepth($siteId, $range, 1000);

        $filename = "insights-scroll-depth-{$range}";

        return $this->exportData($data, $filename, $format);
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
