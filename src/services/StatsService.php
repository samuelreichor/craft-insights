<?php

namespace samuelreichor\insights\services;

use craft\base\Component;
use craft\db\Query;
use DateTime;
use samuelreichor\insights\Constants;
use samuelreichor\insights\enums\DateRange;
use samuelreichor\insights\Insights;

/**
 * Stats Service
 *
 * Provides aggregated statistics for the dashboard.
 * All data is already DSGVO-compliant (aggregated, no PII).
 */
class StatsService extends Component
{
    /**
     * Get summary statistics for a date range.
     *
     * @return array{pageviews: int, uniqueVisitors: int, bounceRate: float, avgTimeOnPage: float, pageviewsTrend: float, visitorsTrend: float}
     */
    public function getSummary(int $siteId, string $range): array
    {
        [$startDate, $endDate] = $this->getDateRange($range);
        [$prevStartDate, $prevEndDate] = $this->getPreviousDateRange($range);

        // Current period stats
        $current = (new Query())
            ->select([
                'SUM([[views]]) as pageviews',
                'SUM([[uniqueVisitors]]) as uniqueVisitors',
                'SUM([[bounces]]) as bounces',
                'SUM([[totalTimeOnPage]]) as totalTime',
            ])
            ->from(Constants::TABLE_PAGEVIEWS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->one();

        // Previous period stats for trend calculation
        $previous = (new Query())
            ->select([
                'SUM([[views]]) as pageviews',
                'SUM([[uniqueVisitors]]) as uniqueVisitors',
            ])
            ->from(Constants::TABLE_PAGEVIEWS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $prevStartDate])
            ->andWhere(['<=', 'date', $prevEndDate])
            ->one();

        $pageviews = (int)($current['pageviews'] ?? 0);
        $uniqueVisitors = (int)($current['uniqueVisitors'] ?? 0);
        $bounces = (int)($current['bounces'] ?? 0);
        $totalTime = (int)($current['totalTime'] ?? 0);

        $bounceRate = $uniqueVisitors > 0 ? round(($bounces / $uniqueVisitors) * 100, 1) : 0;
        $avgTimeOnPage = $pageviews > 0 ? round($totalTime / $pageviews) : 0;

        $prevPageviews = (int)($previous['pageviews'] ?? 0);
        $prevVisitors = (int)($previous['uniqueVisitors'] ?? 0);

        $pageviewsTrend = $prevPageviews > 0 ? round((($pageviews - $prevPageviews) / $prevPageviews) * 100, 1) : 0;
        $visitorsTrend = $prevVisitors > 0 ? round((($uniqueVisitors - $prevVisitors) / $prevVisitors) * 100, 1) : 0;

        return [
            'pageviews' => $pageviews,
            'uniqueVisitors' => $uniqueVisitors,
            'bounceRate' => $bounceRate,
            'avgTimeOnPage' => $avgTimeOnPage,
            'pageviewsTrend' => $pageviewsTrend,
            'visitorsTrend' => $visitorsTrend,
        ];
    }

    /**
     * Get chart data for pageviews and visitors over time.
     *
     * @return array{labels: string[], pageviews: int[], visitors: int[]}
     */
    public function getChartData(int $siteId, string $range): array
    {
        [$startDate, $endDate] = $this->getDateRange($range);

        $query = (new Query())
            ->select([
                'date',
                'SUM([[views]]) as pageviews',
                'SUM([[uniqueVisitors]]) as visitors',
            ])
            ->from(Constants::TABLE_PAGEVIEWS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['date'])
            ->orderBy(['date' => SORT_ASC])
            ->all();

        // Fill in missing dates
        $data = [];
        foreach ($query as $row) {
            $data[$row['date']] = [
                'pageviews' => (int)$row['pageviews'],
                'visitors' => (int)$row['visitors'],
            ];
        }

        $labels = [];
        $pageviews = [];
        $visitors = [];

        $current = new DateTime($startDate);
        $end = new DateTime($endDate);

        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            $labels[] = $current->format('M j');
            $pageviews[] = $data[$date]['pageviews'] ?? 0;
            $visitors[] = $data[$date]['visitors'] ?? 0;
            $current->modify('+1 day');
        }

        return [
            'labels' => $labels,
            'pageviews' => $pageviews,
            'visitors' => $visitors,
        ];
    }

    /**
     * Get top pages by views.
     *
     * @return array<int, array{url: string, views: int, uniqueVisitors: int, totalTime: int, bounces: int}>
     */
    public function getTopPages(int $siteId, string $range, int $limit = 10): array
    {
        [$startDate, $endDate] = $this->getDateRange($range);

        return (new Query())
            ->select([
                'url',
                'SUM([[views]]) as views',
                'SUM([[uniqueVisitors]]) as uniqueVisitors',
                'SUM([[totalTimeOnPage]]) as totalTime',
                'SUM([[bounces]]) as bounces',
            ])
            ->from(Constants::TABLE_PAGEVIEWS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['url'])
            ->orderBy(['views' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Get top referrers.
     *
     * @return array<int, array{referrerDomain: string|null, referrerType: string, visits: int}>
     */
    public function getTopReferrers(int $siteId, string $range, int $limit = 10): array
    {
        [$startDate, $endDate] = $this->getDateRange($range);

        return (new Query())
            ->select([
                'referrerDomain',
                'referrerType',
                'SUM([[visits]]) as visits',
            ])
            ->from(Constants::TABLE_REFERRERS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['referrerDomain', 'referrerType'])
            ->orderBy(['visits' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Get top countries.
     *
     * @return array<int, array{countryCode: string, visits: int}>
     */
    public function getTopCountries(int $siteId, string $range, int $limit = 10): array
    {
        // Pro feature only
        if (!Insights::getInstance()->isPro()) {
            return [];
        }

        [$startDate, $endDate] = $this->getDateRange($range);

        return (new Query())
            ->select([
                'countryCode',
                'SUM([[visits]]) as visits',
            ])
            ->from(Constants::TABLE_COUNTRIES)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['countryCode'])
            ->orderBy(['visits' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Get device breakdown.
     *
     * @return array<int, array{deviceType: string, visits: int}>
     */
    public function getDeviceBreakdown(int $siteId, string $range): array
    {
        [$startDate, $endDate] = $this->getDateRange($range);

        return (new Query())
            ->select([
                'deviceType',
                'SUM([[visits]]) as visits',
            ])
            ->from(Constants::TABLE_DEVICES)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['deviceType'])
            ->orderBy(['visits' => SORT_DESC])
            ->all();
    }

    /**
     * Get browser breakdown.
     *
     * @return array<int, array{browserFamily: string|null, visits: int}>
     */
    public function getBrowserBreakdown(int $siteId, string $range): array
    {
        [$startDate, $endDate] = $this->getDateRange($range);

        return (new Query())
            ->select([
                'browserFamily',
                'SUM([[visits]]) as visits',
            ])
            ->from(Constants::TABLE_DEVICES)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['browserFamily'])
            ->orderBy(['visits' => SORT_DESC])
            ->limit(10)
            ->all();
    }

    /**
     * Get OS breakdown.
     *
     * @return array<int, array{osFamily: string|null, visits: int}>
     */
    public function getOsBreakdown(int $siteId, string $range): array
    {
        [$startDate, $endDate] = $this->getDateRange($range);

        return (new Query())
            ->select([
                'osFamily',
                'SUM([[visits]]) as visits',
            ])
            ->from(Constants::TABLE_DEVICES)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['osFamily'])
            ->orderBy(['visits' => SORT_DESC])
            ->limit(10)
            ->all();
    }

    /**
     * Get top campaigns (Pro feature).
     *
     * @return array<int, array{utmSource: string|null, utmMedium: string|null, utmCampaign: string|null, visits: int}>
     */
    public function getTopCampaigns(int $siteId, string $range, int $limit = 10): array
    {
        // Pro feature only
        if (!Insights::getInstance()->isPro()) {
            return [];
        }

        [$startDate, $endDate] = $this->getDateRange($range);

        return (new Query())
            ->select([
                'utmSource',
                'utmMedium',
                'utmCampaign',
                'SUM([[visits]]) as visits',
            ])
            ->from(Constants::TABLE_CAMPAIGNS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['utmSource', 'utmMedium', 'utmCampaign'])
            ->orderBy(['visits' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Get realtime visitors.
     *
     * @return array{count: int, pages: array<int, array{url: string, count: int}>}
     */
    public function getRealtimeVisitors(int $siteId): array
    {
        $settings = Insights::getInstance()->getSettings();
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$settings->realtimeTtl} seconds"));

        $count = (new Query())
            ->from(Constants::TABLE_REALTIME)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'lastSeen', $cutoff])
            ->count();

        $pages = (new Query())
            ->select(['currentUrl as url', 'COUNT(*) as count'])
            ->from(Constants::TABLE_REALTIME)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'lastSeen', $cutoff])
            ->groupBy(['currentUrl'])
            ->orderBy(['count' => SORT_DESC])
            ->limit(10)
            ->all();

        return [
            'count' => (int)$count,
            'pages' => $pages,
        ];
    }

    /**
     * Get entry stats.
     *
     * @return array{views: int, uniqueVisitors: int, avgTime: float, bounceRate: float}
     */
    public function getEntryStats(int $entryId, string $range): array
    {
        [$startDate, $endDate] = $this->getDateRange($range);

        $result = (new Query())
            ->select([
                'SUM([[views]]) as views',
                'SUM([[uniqueVisitors]]) as uniqueVisitors',
                'SUM([[totalTimeOnPage]]) as totalTime',
                'SUM([[bounces]]) as bounces',
            ])
            ->from(Constants::TABLE_PAGEVIEWS)
            ->where(['entryId' => $entryId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->one();

        $views = (int)($result['views'] ?? 0);
        $uniqueVisitors = (int)($result['uniqueVisitors'] ?? 0);
        $bounces = (int)($result['bounces'] ?? 0);
        $totalTime = (int)($result['totalTime'] ?? 0);

        return [
            'views' => $views,
            'uniqueVisitors' => $uniqueVisitors,
            'avgTime' => $views > 0 ? round($totalTime / $views) : 0,
            'bounceRate' => $uniqueVisitors > 0 ? round(($bounces / $uniqueVisitors) * 100, 1) : 0,
        ];
    }

    /**
     * Get realtime visitor count for a specific URL.
     */
    public function getRealtimeCountForUrl(int $siteId, string $url): int
    {
        $settings = Insights::getInstance()->getSettings();
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$settings->realtimeTtl} seconds"));

        return (int)(new Query())
            ->from(Constants::TABLE_REALTIME)
            ->where(['siteId' => $siteId])
            ->andWhere(['currentUrl' => $url])
            ->andWhere(['>=', 'lastSeen', $cutoff])
            ->count();
    }

    /**
     * Get hourly breakdown for today.
     *
     * @return array<int, array{hour: int, views: int, visitors: int}>
     */
    public function getHourlyBreakdown(int $siteId): array
    {
        $today = date('Y-m-d');

        $data = (new Query())
            ->select([
                'hour',
                'SUM([[views]]) as views',
                'SUM([[uniqueVisitors]]) as visitors',
            ])
            ->from(Constants::TABLE_PAGEVIEWS)
            ->where(['siteId' => $siteId, 'date' => $today])
            ->groupBy(['hour'])
            ->orderBy(['hour' => SORT_ASC])
            ->all();

        // Fill all 24 hours
        $result = [];
        $dataByHour = [];
        foreach ($data as $row) {
            $dataByHour[(int)$row['hour']] = $row;
        }

        for ($h = 0; $h < 24; $h++) {
            $result[] = [
                'hour' => $h,
                'views' => (int)($dataByHour[$h]['views'] ?? 0),
                'visitors' => (int)($dataByHour[$h]['visitors'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Get date range from range string.
     *
     * @return array{0: string, 1: string}
     */
    public function getDateRange(string $range): array
    {
        $dateRange = DateRange::tryFrom($range) ?? DateRange::Last30Days;
        return $dateRange->getDateRange();
    }

    /**
     * Get previous date range for trend calculation.
     *
     * @return array{0: string, 1: string}
     */
    private function getPreviousDateRange(string $range): array
    {
        [$startDate, $endDate] = $this->getDateRange($range);
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = $start->diff($end);
        $days = $interval->days + 1;

        $prevEnd = (clone $start)->modify('-1 day')->format('Y-m-d');
        $prevStart = (clone $start)->modify("-{$days} days")->format('Y-m-d');

        return [$prevStart, $prevEnd];
    }

    /**
     * Get top custom events (Pro feature).
     *
     * @return array<int, array{eventName: string, eventCategory: string|null, count: int, uniqueVisitors: int}>
     */
    public function getTopEvents(int $siteId, string $range, int $limit = 10): array
    {
        // Pro feature only
        if (!Insights::getInstance()->isPro()) {
            return [];
        }

        [$startDate, $endDate] = $this->getDateRange($range);

        return (new Query())
            ->select([
                'eventName',
                'eventCategory',
                'SUM([[count]]) as count',
                'SUM([[uniqueVisitors]]) as uniqueVisitors',
            ])
            ->from(Constants::TABLE_EVENTS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['eventName', 'eventCategory'])
            ->orderBy(['count' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Get all events with URL breakdown (Pro feature).
     *
     * @return array<int, array{eventName: string, eventCategory: string|null, url: string, count: int, uniqueVisitors: int}>
     */
    public function getAllEvents(int $siteId, string $range): array
    {
        // Pro feature only
        if (!Insights::getInstance()->isPro()) {
            return [];
        }

        [$startDate, $endDate] = $this->getDateRange($range);

        return (new Query())
            ->select([
                'eventName',
                'eventCategory',
                'url',
                'SUM([[count]]) as count',
                'SUM([[uniqueVisitors]]) as uniqueVisitors',
            ])
            ->from(Constants::TABLE_EVENTS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['eventName', 'eventCategory', 'url'])
            ->orderBy(['count' => SORT_DESC])
            ->all();
    }

    /**
     * Get top outbound links by clicks (Pro feature).
     *
     * @return array<int, array{targetDomain: string, clicks: int, uniqueVisitors: int}>
     */
    public function getTopOutboundLinks(int $siteId, string $range, int $limit = 10): array
    {
        // Pro feature only
        if (!Insights::getInstance()->isPro()) {
            return [];
        }

        [$startDate, $endDate] = $this->getDateRange($range);

        return (new Query())
            ->select([
                'targetDomain',
                'SUM([[clicks]]) as clicks',
                'SUM([[uniqueVisitors]]) as uniqueVisitors',
            ])
            ->from(Constants::TABLE_OUTBOUND)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['targetDomain'])
            ->orderBy(['clicks' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Get all outbound links with full details (Pro feature).
     *
     * @return array<int, array{targetUrl: string, targetDomain: string, linkText: string|null, sourceUrl: string, clicks: int, uniqueVisitors: int}>
     */
    public function getAllOutboundLinks(int $siteId, string $range): array
    {
        // Pro feature only
        if (!Insights::getInstance()->isPro()) {
            return [];
        }

        [$startDate, $endDate] = $this->getDateRange($range);

        return (new Query())
            ->select([
                'targetUrl',
                'targetDomain',
                'linkText',
                'sourceUrl',
                'SUM([[clicks]]) as clicks',
                'SUM([[uniqueVisitors]]) as uniqueVisitors',
            ])
            ->from(Constants::TABLE_OUTBOUND)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['targetUrl', 'targetDomain', 'linkText', 'sourceUrl'])
            ->orderBy(['clicks' => SORT_DESC])
            ->all();
    }

    /**
     * Get top site searches by search count (Pro feature).
     *
     * @return array<int, array{searchTerm: string, searches: int, uniqueVisitors: int}>
     */
    public function getTopSearches(int $siteId, string $range, int $limit = 10): array
    {
        // Pro feature only
        if (!Insights::getInstance()->isPro()) {
            return [];
        }

        [$startDate, $endDate] = $this->getDateRange($range);

        return (new Query())
            ->select([
                'searchTerm',
                'SUM([[searches]]) as searches',
                'SUM([[uniqueVisitors]]) as uniqueVisitors',
            ])
            ->from(Constants::TABLE_SEARCHES)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['searchTerm'])
            ->orderBy(['searches' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Get all site searches with full details (Pro feature).
     *
     * @return array<int, array{searchTerm: string, resultsCount: int|null, searches: int, uniqueVisitors: int}>
     */
    public function getAllSearches(int $siteId, string $range): array
    {
        // Pro feature only
        if (!Insights::getInstance()->isPro()) {
            return [];
        }

        [$startDate, $endDate] = $this->getDateRange($range);

        return (new Query())
            ->select([
                'searchTerm',
                'resultsCount',
                'SUM([[searches]]) as searches',
                'SUM([[uniqueVisitors]]) as uniqueVisitors',
            ])
            ->from(Constants::TABLE_SEARCHES)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['searchTerm', 'resultsCount'])
            ->orderBy(['searches' => SORT_DESC])
            ->all();
    }

    /**
     * Get teaser preview data for Pro features (available to Lite users).
     * Returns limited preview data for blurred display.
     *
     * @return array{countries: array, campaigns: array, events: array, outbound: array, searches: array}
     */
    public function getProFeaturePreviews(int $siteId, string $range): array
    {
        [$startDate, $endDate] = $this->getDateRange($range);
        $limit = 3;

        return [
            'countries' => $this->getPreviewCountries($siteId, $startDate, $endDate, $limit),
            'campaigns' => $this->getPreviewCampaigns($siteId, $startDate, $endDate, $limit),
            'events' => $this->getPreviewEvents($siteId, $startDate, $endDate, $limit),
            'outbound' => $this->getPreviewOutbound($siteId, $startDate, $endDate, $limit),
            'searches' => $this->getPreviewSearches($siteId, $startDate, $endDate, $limit),
        ];
    }

    /**
     * Get preview countries data (no Pro check).
     *
     * @return array<int, array{countryCode: string, visits: int}>
     */
    private function getPreviewCountries(int $siteId, string $startDate, string $endDate, int $limit): array
    {
        return (new Query())
            ->select(['countryCode', 'SUM([[visits]]) as visits'])
            ->from(Constants::TABLE_COUNTRIES)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['countryCode'])
            ->orderBy(['visits' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Get preview campaigns data (no Pro check).
     *
     * @return array<int, array{utmSource: string|null, utmMedium: string|null, utmCampaign: string|null, visits: int}>
     */
    private function getPreviewCampaigns(int $siteId, string $startDate, string $endDate, int $limit): array
    {
        return (new Query())
            ->select(['utmSource', 'utmMedium', 'utmCampaign', 'SUM([[visits]]) as visits'])
            ->from(Constants::TABLE_CAMPAIGNS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['utmSource', 'utmMedium', 'utmCampaign'])
            ->orderBy(['visits' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Get preview events data (no Pro check).
     *
     * @return array<int, array{eventName: string, eventCategory: string|null, count: int, uniqueVisitors: int}>
     */
    private function getPreviewEvents(int $siteId, string $startDate, string $endDate, int $limit): array
    {
        return (new Query())
            ->select(['eventName', 'eventCategory', 'SUM([[count]]) as count', 'SUM([[uniqueVisitors]]) as uniqueVisitors'])
            ->from(Constants::TABLE_EVENTS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['eventName', 'eventCategory'])
            ->orderBy(['count' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Get preview outbound data (no Pro check).
     *
     * @return array<int, array{targetDomain: string, clicks: int, uniqueVisitors: int}>
     */
    private function getPreviewOutbound(int $siteId, string $startDate, string $endDate, int $limit): array
    {
        return (new Query())
            ->select(['targetDomain', 'SUM([[clicks]]) as clicks', 'SUM([[uniqueVisitors]]) as uniqueVisitors'])
            ->from(Constants::TABLE_OUTBOUND)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['targetDomain'])
            ->orderBy(['clicks' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Get preview searches data (no Pro check).
     *
     * @return array<int, array{searchTerm: string, searches: int, uniqueVisitors: int}>
     */
    private function getPreviewSearches(int $siteId, string $startDate, string $endDate, int $limit): array
    {
        return (new Query())
            ->select(['searchTerm', 'SUM([[searches]]) as searches', 'SUM([[uniqueVisitors]]) as uniqueVisitors'])
            ->from(Constants::TABLE_SEARCHES)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['searchTerm'])
            ->orderBy(['searches' => SORT_DESC])
            ->limit($limit)
            ->all();
    }
}
