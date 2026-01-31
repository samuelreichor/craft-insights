<?php

namespace samuelreichor\insights\services;

use craft\base\Component;
use craft\db\Connection;
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
     * Get the database connection for Insights data.
     */
    private function getDb(): Connection
    {
        return Insights::getInstance()->database->getConnection();
    }

    /**
     * Get summary statistics for a date range.
     *
     * @return array{pageviews: int, uniqueVisitors: int, bounceRate: float, avgTimeOnPage: float, pageviewsTrend: float, visitorsTrend: float}
     */
    public function getSummary(int $siteId, string $range): array
    {
        [$startDate, $endDate] = $this->getDateRange($range);
        [$prevStartDate, $prevEndDate] = $this->getPreviousDateRange($range);
        $db = $this->getDb();

        // Current period pageview stats
        $current = (new Query())
            ->select([
                'SUM([[views]]) as pageviews',
                'SUM([[totalTimeOnPage]]) as totalTime',
            ])
            ->from(Constants::TABLE_PAGEVIEWS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->one($db);

        // Count unique visitors from sessions table (distinct visitor hashes)
        $uniqueVisitors = (int)(new Query())
            ->select(['COUNT(DISTINCT [[visitorHash]])'])
            ->from(Constants::TABLE_SESSIONS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->scalar($db);

        // Count sessions and bounces (sessions with only 1 pageview)
        $sessionStats = (new Query())
            ->select([
                'COUNT(*) as totalSessions',
                'SUM(CASE WHEN [[pageCount]] = 1 THEN 1 ELSE 0 END) as bounces',
            ])
            ->from(Constants::TABLE_SESSIONS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->one($db);

        // Previous period pageview stats for trend calculation
        $previous = (new Query())
            ->select([
                'SUM([[views]]) as pageviews',
            ])
            ->from(Constants::TABLE_PAGEVIEWS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $prevStartDate])
            ->andWhere(['<=', 'date', $prevEndDate])
            ->one($db);

        // Previous period unique visitors
        $prevVisitors = (int)(new Query())
            ->select(['COUNT(DISTINCT [[visitorHash]])'])
            ->from(Constants::TABLE_SESSIONS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $prevStartDate])
            ->andWhere(['<=', 'date', $prevEndDate])
            ->scalar($db);

        $pageviews = (int)($current['pageviews'] ?? 0);
        $totalTime = (int)($current['totalTime'] ?? 0);
        $totalSessions = (int)($sessionStats['totalSessions'] ?? 0);
        $bounces = (int)($sessionStats['bounces'] ?? 0);

        // Bounce rate = sessions with 1 pageview / total sessions
        $bounceRate = $totalSessions > 0 ? round(($bounces / $totalSessions) * 100, 1) : 0;
        $avgTimeOnPage = $pageviews > 0 ? round($totalTime / $pageviews) : 0;

        $prevPageviews = (int)($previous['pageviews'] ?? 0);

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
        $db = $this->getDb();

        // Get pageviews per day
        $pageviewsQuery = (new Query())
            ->select([
                'date',
                'SUM([[views]]) as pageviews',
            ])
            ->from(Constants::TABLE_PAGEVIEWS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['date'])
            ->orderBy(['date' => SORT_ASC])
            ->all($db);

        // Get unique visitors per day from sessions table
        $visitorsQuery = (new Query())
            ->select([
                'date',
                'COUNT(DISTINCT [[visitorHash]]) as visitors',
            ])
            ->from(Constants::TABLE_SESSIONS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['date'])
            ->orderBy(['date' => SORT_ASC])
            ->all($db);

        // Build data arrays
        $pageviewsData = [];
        foreach ($pageviewsQuery as $row) {
            $pageviewsData[$row['date']] = (int)$row['pageviews'];
        }

        $visitorsData = [];
        foreach ($visitorsQuery as $row) {
            $visitorsData[$row['date']] = (int)$row['visitors'];
        }

        $labels = [];
        $pageviews = [];
        $visitors = [];

        $current = new DateTime($startDate);
        $end = new DateTime($endDate);

        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            $labels[] = $current->format('M j');
            $pageviews[] = $pageviewsData[$date] ?? 0;
            $visitors[] = $visitorsData[$date] ?? 0;
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
            ->all($this->getDb());
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
            ->all($this->getDb());
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
            ->all($this->getDb());
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
            ->all($this->getDb());
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
            ->all($this->getDb());
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
            ->all($this->getDb());
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
            ->all($this->getDb());
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
        $db = $this->getDb();

        $count = (new Query())
            ->from(Constants::TABLE_REALTIME)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'lastSeen', $cutoff])
            ->count('*', $db);

        $pages = (new Query())
            ->select(['currentUrl as url', 'COUNT(*) as count'])
            ->from(Constants::TABLE_REALTIME)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'lastSeen', $cutoff])
            ->groupBy(['currentUrl'])
            ->orderBy(['count' => SORT_DESC])
            ->limit(10)
            ->all($db);

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
            ->one($this->getDb());

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
            ->count('*', $this->getDb());
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
            ->all($this->getDb());

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
            ->all($this->getDb());
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
            ->all($this->getDb());
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
            ->all($this->getDb());
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
            ->all($this->getDb());
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
            ->all($this->getDb());
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
            ->all($this->getDb());
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
            ->all($this->getDb());
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
            ->all($this->getDb());
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
            ->all($this->getDb());
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
            ->all($this->getDb());
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
            ->all($this->getDb());
    }

    /**
     * Get scroll depth statistics (Pro feature).
     *
     * Returns aggregated scroll depth milestones for each URL.
     *
     * @return array<int, array{url: string, milestone25: int, milestone50: int, milestone75: int, milestone100: int}>
     */
    public function getScrollDepth(int $siteId, string $range, int $limit = 10): array
    {
        // Pro feature only
        if (!Insights::getInstance()->isPro()) {
            return [];
        }

        [$startDate, $endDate] = $this->getDateRange($range);

        return (new Query())
            ->select([
                'url',
                'SUM([[milestone25]]) as milestone25',
                'SUM([[milestone50]]) as milestone50',
                'SUM([[milestone75]]) as milestone75',
                'SUM([[milestone100]]) as milestone100',
            ])
            ->from(Constants::TABLE_SCROLL_DEPTH)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['url'])
            ->orderBy(['milestone25' => SORT_DESC])
            ->limit($limit)
            ->all($this->getDb());
    }

    /**
     * Get average scroll depth percentage for the site.
     *
     * Calculates weighted average across all milestones (Pro feature).
     *
     * @return array{avgScrollDepth: float, milestone25: int, milestone50: int, milestone75: int, milestone100: int}
     */
    public function getAverageScrollDepth(int $siteId, string $range): array
    {
        // Pro feature only
        if (!Insights::getInstance()->isPro()) {
            return [
                'avgScrollDepth' => 0,
                'milestone25' => 0,
                'milestone50' => 0,
                'milestone75' => 0,
                'milestone100' => 0,
            ];
        }

        [$startDate, $endDate] = $this->getDateRange($range);

        $result = (new Query())
            ->select([
                'SUM([[milestone25]]) as m25',
                'SUM([[milestone50]]) as m50',
                'SUM([[milestone75]]) as m75',
                'SUM([[milestone100]]) as m100',
            ])
            ->from(Constants::TABLE_SCROLL_DEPTH)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->one($this->getDb());

        $m25 = (int)($result['m25'] ?? 0);
        $m50 = (int)($result['m50'] ?? 0);
        $m75 = (int)($result['m75'] ?? 0);
        $m100 = (int)($result['m100'] ?? 0);

        // Calculate weighted average (each milestone contributes its percentage)
        $totalEvents = $m25 + $m50 + $m75 + $m100;
        $avgScrollDepth = 0;
        if ($totalEvents > 0) {
            $weightedSum = ($m25 * 25) + ($m50 * 50) + ($m75 * 75) + ($m100 * 100);
            $avgScrollDepth = round($weightedSum / $totalEvents, 1);
        }

        return [
            'avgScrollDepth' => $avgScrollDepth,
            'milestone25' => $m25,
            'milestone50' => $m50,
            'milestone75' => $m75,
            'milestone100' => $m100,
        ];
    }

    /**
     * Get pages per session statistics.
     *
     * Lite: Returns just the average number
     * Pro: Returns average with trend comparison
     *
     * @return array{avgPagesPerSession: float, avgPagesPerSessionTrend: float|null}
     */
    public function getPagesPerSession(int $siteId, string $range): array
    {
        [$startDate, $endDate] = $this->getDateRange($range);
        $db = $this->getDb();

        $current = (new Query())
            ->select(['AVG([[pageCount]]) as avg'])
            ->from(Constants::TABLE_SESSIONS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->scalar($db);

        $avgPagesPerSession = round((float)($current ?? 0), 2);

        // Calculate trend only for Pro
        $trend = null;
        if (Insights::getInstance()->isPro()) {
            [$prevStartDate, $prevEndDate] = $this->getPreviousDateRange($range);

            $previous = (new Query())
                ->select(['AVG([[pageCount]]) as avg'])
                ->from(Constants::TABLE_SESSIONS)
                ->where(['siteId' => $siteId])
                ->andWhere(['>=', 'date', $prevStartDate])
                ->andWhere(['<=', 'date', $prevEndDate])
                ->scalar($db);

            $prevAvg = (float)($previous ?? 0);
            if ($prevAvg > 0) {
                $trend = round((($avgPagesPerSession - $prevAvg) / $prevAvg) * 100, 1);
            } else {
                $trend = 0;
            }
        }

        return [
            'avgPagesPerSession' => $avgPagesPerSession,
            'avgPagesPerSessionTrend' => $trend,
        ];
    }

    /**
     * Get top entry pages (first page of session).
     *
     * Lite: Returns limited preview (3 rows)
     * Pro: Returns full data
     *
     * @return array<int, array{url: string, sessions: int}>
     */
    public function getTopEntryPages(int $siteId, string $range, int $limit = 10): array
    {
        [$startDate, $endDate] = $this->getDateRange($range);

        // Lite users get limited preview
        if (!Insights::getInstance()->isPro()) {
            $limit = 3;
        }

        return (new Query())
            ->select([
                'entryUrl as url',
                'COUNT(*) as sessions',
            ])
            ->from(Constants::TABLE_SESSIONS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['entryUrl'])
            ->orderBy(['sessions' => SORT_DESC])
            ->limit($limit)
            ->all($this->getDb());
    }

    /**
     * Get top exit pages (last page of session).
     *
     * Lite: Returns limited preview (3 rows)
     * Pro: Returns full data
     *
     * @return array<int, array{url: string, sessions: int}>
     */
    public function getTopExitPages(int $siteId, string $range, int $limit = 10): array
    {
        [$startDate, $endDate] = $this->getDateRange($range);

        // Lite users get limited preview
        if (!Insights::getInstance()->isPro()) {
            $limit = 3;
        }

        return (new Query())
            ->select([
                'exitUrl as url',
                'COUNT(*) as sessions',
            ])
            ->from(Constants::TABLE_SESSIONS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->andWhere(['not', ['exitUrl' => null]])
            ->groupBy(['exitUrl'])
            ->orderBy(['sessions' => SORT_DESC])
            ->limit($limit)
            ->all($this->getDb());
    }

    /**
     * Get total sessions count for a date range.
     *
     * @return int
     */
    public function getSessionsCount(int $siteId, string $range): int
    {
        [$startDate, $endDate] = $this->getDateRange($range);

        return (int)(new Query())
            ->from(Constants::TABLE_SESSIONS)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->count('*', $this->getDb());
    }

    /**
     * Get preview data for scroll depth (no Pro check, limited rows).
     *
     * @return array<int, array{url: string, milestone25: int, milestone50: int, milestone75: int, milestone100: int}>
     */
    public function getPreviewScrollDepth(int $siteId, string $range, int $limit = 3): array
    {
        [$startDate, $endDate] = $this->getDateRange($range);

        return (new Query())
            ->select([
                'url',
                'SUM([[milestone25]]) as milestone25',
                'SUM([[milestone50]]) as milestone50',
                'SUM([[milestone75]]) as milestone75',
                'SUM([[milestone100]]) as milestone100',
            ])
            ->from(Constants::TABLE_SCROLL_DEPTH)
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->groupBy(['url'])
            ->orderBy(['milestone25' => SORT_DESC])
            ->limit($limit)
            ->all($this->getDb());
    }
}
