<?php

namespace samuelreichor\insights\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\StringHelper;
use samuelreichor\insights\Constants;
use samuelreichor\insights\Insights;
use yii\console\ExitCode;

/**
 * Seed console command for generating demo data.
 *
 * Usage: ./craft insights/seed
 */
class SeedController extends Controller
{
    /**
     * Number of days to generate data for.
     */
    public int $days = 30;

    /**
     * Site ID to seed data for.
     */
    public ?int $siteId = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'days',
            'siteId',
        ]);
    }

    /**
     * Seed all demo data.
     *
     * ./craft insights/seed
     * ./craft insights/seed --days=60
     * ./craft insights/seed --siteId=1
     */
    public function actionIndex(): int
    {
        $siteId = $this->siteId ?? Craft::$app->getSites()->getPrimarySite()->id;

        $this->stdout("\nSeeding Insights demo data...\n");
        $this->stdout("  Site ID: {$siteId}\n");
        $this->stdout("  Days: {$this->days}\n\n");

        $this->seedPageviews($siteId);
        $this->seedReferrers($siteId);
        $this->seedCampaigns($siteId);
        $this->seedDevices($siteId);
        $this->seedCountries($siteId);
        $this->seedEvents($siteId);
        $this->seedOutbound($siteId);
        $this->seedSearches($siteId);
        $this->seedScrollDepth($siteId);
        $this->seedSessions($siteId);

        $this->stdout("\nDemo data seeded successfully!\n\n");

        return ExitCode::OK;
    }

    /**
     * Seed only events data.
     *
     * ./craft insights/seed/events
     * ./craft insights/seed/events --days=60
     */
    public function actionEvents(): int
    {
        $siteId = $this->siteId ?? Craft::$app->getSites()->getPrimarySite()->id;

        $this->stdout("\nSeeding Insights events demo data...\n");
        $this->stdout("  Site ID: {$siteId}\n");
        $this->stdout("  Days: {$this->days}\n\n");

        $this->seedEvents($siteId);

        $this->stdout("\nEvents demo data seeded successfully!\n\n");

        return ExitCode::OK;
    }

    private function seedPageviews(int $siteId): void
    {
        $this->stdout("  Seeding pageviews...");

        $pages = [
            '/' => 100,
            '/about' => 60,
            '/contact' => 40,
            '/blog' => 80,
            '/blog/article-1' => 35,
            '/blog/article-2' => 28,
            '/blog/article-3' => 22,
            '/products' => 55,
            '/products/item-1' => 18,
            '/products/item-2' => 15,
            '/pricing' => 45,
            '/faq' => 25,
        ];

        $db = Insights::getInstance()->database->getConnection();
        $count = 0;

        for ($d = $this->days; $d >= 0; $d--) {
            $date = date('Y-m-d', strtotime("-{$d} days"));
            $weekendFactor = in_array(date('N', strtotime($date)), [6, 7]) ? 0.6 : 1.0;

            foreach ($pages as $url => $baseViews) {
                $randomFactor = mt_rand(70, 130) / 100;
                $views = (int)round($baseViews * $weekendFactor * $randomFactor);
                $uniqueVisitors = (int)round($views * (mt_rand(60, 85) / 100));
                $bounces = (int)round($uniqueVisitors * (mt_rand(30, 60) / 100));
                $totalTime = $views * mt_rand(30, 180);

                for ($h = 0; $h < 24; $h++) {
                    $hourFactor = $this->getHourFactor($h);
                    $hourViews = (int)round($views * $hourFactor);

                    if ($hourViews > 0) {
                        $db->createCommand()->upsert(
                            Constants::TABLE_PAGEVIEWS,
                            array_merge([
                                'siteId' => $siteId,
                                'date' => $date,
                                'hour' => $h,
                                'url' => $url,
                                'entryId' => null,
                            ], $this->getTimestampFields()),
                            [
                                'views' => $hourViews,
                                'uniqueVisitors' => (int)round($hourViews * 0.75),
                                'bounces' => (int)round($hourViews * 0.4),
                                'totalTimeOnPage' => $hourViews * mt_rand(30, 120),
                                'dateUpdated' => date('Y-m-d H:i:s'),
                            ]
                        )->execute();
                        $count++;
                    }
                }
            }
        }

        $this->stdout(" {$count} records\n");
    }

    private function seedReferrers(int $siteId): void
    {
        $this->stdout("  Seeding referrers...");

        $referrers = [
            ['domain' => null, 'type' => 'direct', 'weight' => 40],
            ['domain' => 'google.com', 'type' => 'search', 'weight' => 30],
            ['domain' => 'bing.com', 'type' => 'search', 'weight' => 8],
            ['domain' => 'duckduckgo.com', 'type' => 'search', 'weight' => 5],
            ['domain' => 'facebook.com', 'type' => 'social', 'weight' => 10],
            ['domain' => 'twitter.com', 'type' => 'social', 'weight' => 6],
            ['domain' => 'linkedin.com', 'type' => 'social', 'weight' => 4],
            ['domain' => 'reddit.com', 'type' => 'social', 'weight' => 3],
            ['domain' => 'example-blog.com', 'type' => 'referral', 'weight' => 5],
            ['domain' => 'partner-site.com', 'type' => 'referral', 'weight' => 3],
        ];

        $db = Insights::getInstance()->database->getConnection();
        $count = 0;

        for ($d = $this->days; $d >= 0; $d--) {
            $date = date('Y-m-d', strtotime("-{$d} days"));

            foreach ($referrers as $ref) {
                $visits = (int)round($ref['weight'] * (mt_rand(80, 120) / 100));

                if ($visits > 0) {
                    $db->createCommand()->upsert(
                        Constants::TABLE_REFERRERS,
                        array_merge([
                            'siteId' => $siteId,
                            'date' => $date,
                            'referrerDomain' => $ref['domain'],
                            'referrerType' => $ref['type'],
                        ], $this->getTimestampFields()),
                        [
                            'visits' => $visits,
                            'dateUpdated' => date('Y-m-d H:i:s'),
                        ]
                    )->execute();
                    $count++;
                }
            }
        }

        $this->stdout(" {$count} records\n");
    }

    private function seedCampaigns(int $siteId): void
    {
        $this->stdout("  Seeding campaigns...");

        $campaigns = [
            ['source' => 'google', 'medium' => 'cpc', 'campaign' => 'brand', 'weight' => 25],
            ['source' => 'google', 'medium' => 'cpc', 'campaign' => 'generic', 'weight' => 20],
            ['source' => 'facebook', 'medium' => 'paid', 'campaign' => 'retargeting', 'weight' => 15],
            ['source' => 'newsletter', 'medium' => 'email', 'campaign' => 'weekly', 'weight' => 18],
            ['source' => 'newsletter', 'medium' => 'email', 'campaign' => 'promo-jan', 'weight' => 12],
            ['source' => 'linkedin', 'medium' => 'social', 'campaign' => 'b2b-outreach', 'weight' => 8],
        ];

        $db = Insights::getInstance()->database->getConnection();
        $count = 0;

        for ($d = $this->days; $d >= 0; $d--) {
            $date = date('Y-m-d', strtotime("-{$d} days"));

            foreach ($campaigns as $c) {
                $visits = (int)round($c['weight'] * (mt_rand(70, 130) / 100));

                if ($visits > 0) {
                    $db->createCommand()->upsert(
                        Constants::TABLE_CAMPAIGNS,
                        array_merge([
                            'siteId' => $siteId,
                            'date' => $date,
                            'utmSource' => $c['source'],
                            'utmMedium' => $c['medium'],
                            'utmCampaign' => $c['campaign'],
                        ], $this->getTimestampFields()),
                        [
                            'visits' => $visits,
                            'dateUpdated' => date('Y-m-d H:i:s'),
                        ]
                    )->execute();
                    $count++;
                }
            }
        }

        $this->stdout(" {$count} records\n");
    }

    private function seedDevices(int $siteId): void
    {
        $this->stdout("  Seeding devices...");

        $devices = [
            ['type' => 'desktop', 'browser' => 'Chrome', 'os' => 'Windows', 'weight' => 35],
            ['type' => 'desktop', 'browser' => 'Chrome', 'os' => 'OS X', 'weight' => 15],
            ['type' => 'desktop', 'browser' => 'Chrome', 'os' => 'Linux', 'weight' => 6],
            ['type' => 'desktop', 'browser' => 'Firefox', 'os' => 'Windows', 'weight' => 10],
            ['type' => 'desktop', 'browser' => 'Firefox', 'os' => 'Linux', 'weight' => 4],
            ['type' => 'desktop', 'browser' => 'Safari', 'os' => 'OS X', 'weight' => 12],
            ['type' => 'desktop', 'browser' => 'Edge', 'os' => 'Windows', 'weight' => 8],
            ['type' => 'mobile', 'browser' => 'Safari', 'os' => 'iOS', 'weight' => 25],
            ['type' => 'mobile', 'browser' => 'Chrome', 'os' => 'Android', 'weight' => 18],
            ['type' => 'mobile', 'browser' => 'Samsung Internet', 'os' => 'Android', 'weight' => 5],
            ['type' => 'tablet', 'browser' => 'Safari', 'os' => 'iOS', 'weight' => 5],
            ['type' => 'tablet', 'browser' => 'Chrome', 'os' => 'Android', 'weight' => 3],
        ];

        $db = Insights::getInstance()->database->getConnection();
        $count = 0;

        for ($d = $this->days; $d >= 0; $d--) {
            $date = date('Y-m-d', strtotime("-{$d} days"));

            foreach ($devices as $dev) {
                $visits = (int)round($dev['weight'] * (mt_rand(80, 120) / 100));

                if ($visits > 0) {
                    $db->createCommand()->upsert(
                        Constants::TABLE_DEVICES,
                        array_merge([
                            'siteId' => $siteId,
                            'date' => $date,
                            'deviceType' => $dev['type'],
                            'browserFamily' => $dev['browser'],
                            'osFamily' => $dev['os'],
                        ], $this->getTimestampFields()),
                        [
                            'visits' => $visits,
                            'dateUpdated' => date('Y-m-d H:i:s'),
                        ]
                    )->execute();
                    $count++;
                }
            }
        }

        $this->stdout(" {$count} records\n");
    }

    private function seedCountries(int $siteId): void
    {
        $this->stdout("  Seeding countries...");

        $countries = [
            ['code' => 'DE', 'weight' => 35],
            ['code' => 'AT', 'weight' => 15],
            ['code' => 'CH', 'weight' => 12],
            ['code' => 'US', 'weight' => 10],
            ['code' => 'GB', 'weight' => 8],
            ['code' => 'NL', 'weight' => 6],
            ['code' => 'FR', 'weight' => 5],
            ['code' => 'IT', 'weight' => 4],
            ['code' => 'ES', 'weight' => 3],
            ['code' => 'PL', 'weight' => 2],
        ];

        $db = Insights::getInstance()->database->getConnection();
        $count = 0;

        for ($d = $this->days; $d >= 0; $d--) {
            $date = date('Y-m-d', strtotime("-{$d} days"));

            foreach ($countries as $c) {
                $visits = (int)round($c['weight'] * (mt_rand(80, 120) / 100));

                if ($visits > 0) {
                    $db->createCommand()->upsert(
                        Constants::TABLE_COUNTRIES,
                        array_merge([
                            'siteId' => $siteId,
                            'date' => $date,
                            'countryCode' => $c['code'],
                        ], $this->getTimestampFields()),
                        [
                            'visits' => $visits,
                            'dateUpdated' => date('Y-m-d H:i:s'),
                        ]
                    )->execute();
                    $count++;
                }
            }
        }

        $this->stdout(" {$count} records\n");
    }

    private function seedEvents(int $siteId): void
    {
        $this->stdout("  Seeding events...");

        $events = [
            ['name' => 'cta_click', 'category' => 'conversion', 'url' => '/', 'weight' => 25],
            ['name' => 'cta_click', 'category' => 'conversion', 'url' => '/pricing', 'weight' => 18],
            ['name' => 'download', 'category' => 'engagement', 'url' => '/resources', 'weight' => 15],
            ['name' => 'download', 'category' => 'engagement', 'url' => '/blog/article-1', 'weight' => 8],
            ['name' => 'newsletter_signup', 'category' => 'conversion', 'url' => '/blog', 'weight' => 12],
            ['name' => 'newsletter_signup', 'category' => 'conversion', 'url' => '/', 'weight' => 10],
            ['name' => 'video_play', 'category' => 'engagement', 'url' => '/about', 'weight' => 20],
            ['name' => 'video_play', 'category' => 'engagement', 'url' => '/products', 'weight' => 14],
            ['name' => 'share_click', 'category' => 'social', 'url' => '/blog/article-1', 'weight' => 6],
            ['name' => 'share_click', 'category' => 'social', 'url' => '/blog/article-2', 'weight' => 5],
            ['name' => 'contact_form_start', 'category' => 'conversion', 'url' => '/contact', 'weight' => 22],
            ['name' => 'contact_form_submit', 'category' => 'conversion', 'url' => '/contact', 'weight' => 8],
            ['name' => 'pricing_toggle', 'category' => 'interaction', 'url' => '/pricing', 'weight' => 30],
            ['name' => 'faq_expand', 'category' => 'interaction', 'url' => '/faq', 'weight' => 16],
            ['name' => 'product_zoom', 'category' => 'interaction', 'url' => '/products/item-1', 'weight' => 12],
            ['name' => 'add_to_cart', 'category' => 'conversion', 'url' => '/products/item-1', 'weight' => 7],
            ['name' => 'add_to_cart', 'category' => 'conversion', 'url' => '/products/item-2', 'weight' => 5],
        ];

        $db = Insights::getInstance()->database->getConnection();
        $count = 0;

        for ($d = $this->days; $d >= 0; $d--) {
            $date = date('Y-m-d', strtotime("-{$d} days"));
            $weekendFactor = in_array(date('N', strtotime($date)), [6, 7]) ? 0.7 : 1.0;

            foreach ($events as $event) {
                $randomFactor = mt_rand(60, 140) / 100;
                $eventCount = (int)round($event['weight'] * $weekendFactor * $randomFactor);
                $uniqueVisitors = (int)round($eventCount * (mt_rand(50, 80) / 100));

                if ($eventCount > 0) {
                    for ($h = 0; $h < 24; $h++) {
                        $hourFactor = $this->getHourFactor($h);
                        $hourCount = (int)round($eventCount * $hourFactor);

                        if ($hourCount > 0) {
                            $db->createCommand()->upsert(
                                Constants::TABLE_EVENTS,
                                array_merge([
                                    'siteId' => $siteId,
                                    'date' => $date,
                                    'hour' => $h,
                                    'eventName' => $event['name'],
                                    'eventCategory' => $event['category'],
                                    'url' => $event['url'],
                                ], $this->getTimestampFields()),
                                [
                                    'count' => $hourCount,
                                    'uniqueVisitors' => (int)round($hourCount * 0.7),
                                    'dateUpdated' => date('Y-m-d H:i:s'),
                                ]
                            )->execute();
                            $count++;
                        }
                    }
                }
            }
        }

        $this->stdout(" {$count} records\n");
    }

    private function seedOutbound(int $siteId): void
    {
        $this->stdout("  Seeding outbound links...");

        $outboundLinks = [
            ['domain' => 'github.com', 'url' => 'https://github.com/craftcms', 'source' => '/about', 'weight' => 20],
            ['domain' => 'github.com', 'url' => 'https://github.com/craftcms/cms', 'source' => '/docs', 'weight' => 15],
            ['domain' => 'twitter.com', 'url' => 'https://twitter.com/craftcms', 'source' => '/', 'weight' => 12],
            ['domain' => 'youtube.com', 'url' => 'https://youtube.com/watch?v=abc123', 'source' => '/blog/article-1', 'weight' => 18],
            ['domain' => 'stackoverflow.com', 'url' => 'https://stackoverflow.com/questions/craft', 'source' => '/faq', 'weight' => 10],
            ['domain' => 'packagist.org', 'url' => 'https://packagist.org/packages/craftcms', 'source' => '/docs', 'weight' => 8],
            ['domain' => 'docs.craftcms.com', 'url' => 'https://docs.craftcms.com', 'source' => '/resources', 'weight' => 25],
            ['domain' => 'plugins.craftcms.com', 'url' => 'https://plugins.craftcms.com', 'source' => '/products', 'weight' => 14],
        ];

        $db = Insights::getInstance()->database->getConnection();
        $count = 0;

        for ($d = $this->days; $d >= 0; $d--) {
            $date = date('Y-m-d', strtotime("-{$d} days"));
            $weekendFactor = in_array(date('N', strtotime($date)), [6, 7]) ? 0.6 : 1.0;

            foreach ($outboundLinks as $link) {
                $randomFactor = mt_rand(60, 140) / 100;
                $clicks = (int)round($link['weight'] * $weekendFactor * $randomFactor);
                $uniqueVisitors = (int)round($clicks * (mt_rand(60, 85) / 100));

                if ($clicks > 0) {
                    for ($h = 0; $h < 24; $h++) {
                        $hourFactor = $this->getHourFactor($h);
                        $hourClicks = (int)round($clicks * $hourFactor);

                        if ($hourClicks > 0) {
                            $urlHash = md5($link['url'] . $link['source']);
                            $db->createCommand()->upsert(
                                Constants::TABLE_OUTBOUND,
                                array_merge([
                                    'siteId' => $siteId,
                                    'date' => $date,
                                    'hour' => $h,
                                    'urlHash' => $urlHash,
                                    'targetUrl' => $link['url'],
                                    'targetDomain' => $link['domain'],
                                    'sourceUrl' => $link['source'],
                                    'clicks' => $hourClicks,
                                    'uniqueVisitors' => (int)round($hourClicks * 0.75),
                                ], $this->getTimestampFields()),
                                [
                                    'clicks' => $hourClicks,
                                    'uniqueVisitors' => (int)round($hourClicks * 0.75),
                                    'dateUpdated' => date('Y-m-d H:i:s'),
                                ]
                            )->execute();
                            $count++;
                        }
                    }
                }
            }
        }

        $this->stdout(" {$count} records\n");
    }

    private function seedSearches(int $siteId): void
    {
        $this->stdout("  Seeding site searches...");

        $searches = [
            ['term' => 'pricing', 'weight' => 30],
            ['term' => 'contact', 'weight' => 25],
            ['term' => 'documentation', 'weight' => 22],
            ['term' => 'api', 'weight' => 18],
            ['term' => 'getting started', 'weight' => 20],
            ['term' => 'installation', 'weight' => 15],
            ['term' => 'plugins', 'weight' => 16],
            ['term' => 'support', 'weight' => 12],
            ['term' => 'login', 'weight' => 14],
            ['term' => 'upgrade', 'weight' => 10],
            ['term' => 'tutorial', 'weight' => 8],
            ['term' => 'examples', 'weight' => 9],
        ];

        $db = Insights::getInstance()->database->getConnection();
        $count = 0;

        for ($d = $this->days; $d >= 0; $d--) {
            $date = date('Y-m-d', strtotime("-{$d} days"));
            $weekendFactor = in_array(date('N', strtotime($date)), [6, 7]) ? 0.5 : 1.0;

            foreach ($searches as $search) {
                $randomFactor = mt_rand(50, 150) / 100;
                $searchCount = (int)round($search['weight'] * $weekendFactor * $randomFactor);
                $uniqueVisitors = (int)round($searchCount * (mt_rand(70, 95) / 100));

                if ($searchCount > 0) {
                    for ($h = 0; $h < 24; $h++) {
                        $hourFactor = $this->getHourFactor($h);
                        $hourSearches = (int)round($searchCount * $hourFactor);

                        if ($hourSearches > 0) {
                            $db->createCommand()->upsert(
                                Constants::TABLE_SEARCHES,
                                array_merge([
                                    'siteId' => $siteId,
                                    'date' => $date,
                                    'hour' => $h,
                                    'searchTerm' => $search['term'],
                                    'searches' => $hourSearches,
                                    'uniqueVisitors' => (int)round($hourSearches * 0.8),
                                    'resultsCount' => mt_rand(1, 50),
                                ], $this->getTimestampFields()),
                                [
                                    'searches' => $hourSearches,
                                    'uniqueVisitors' => (int)round($hourSearches * 0.8),
                                    'dateUpdated' => date('Y-m-d H:i:s'),
                                ]
                            )->execute();
                            $count++;
                        }
                    }
                }
            }
        }

        $this->stdout(" {$count} records\n");
    }

    private function seedScrollDepth(int $siteId): void
    {
        $this->stdout("  Seeding scroll depth...");

        $pages = [
            ['url' => '/', 'base25' => 90, 'base50' => 70, 'base75' => 50, 'base100' => 30],
            ['url' => '/about', 'base25' => 85, 'base50' => 65, 'base75' => 45, 'base100' => 25],
            ['url' => '/blog', 'base25' => 80, 'base50' => 55, 'base75' => 35, 'base100' => 20],
            ['url' => '/blog/article-1', 'base25' => 95, 'base50' => 80, 'base75' => 60, 'base100' => 40],
            ['url' => '/blog/article-2', 'base25' => 92, 'base50' => 75, 'base75' => 55, 'base100' => 35],
            ['url' => '/blog/article-3', 'base25' => 88, 'base50' => 70, 'base75' => 48, 'base100' => 28],
            ['url' => '/products', 'base25' => 85, 'base50' => 60, 'base75' => 40, 'base100' => 22],
            ['url' => '/products/item-1', 'base25' => 90, 'base50' => 72, 'base75' => 55, 'base100' => 38],
            ['url' => '/pricing', 'base25' => 95, 'base50' => 85, 'base75' => 70, 'base100' => 55],
            ['url' => '/contact', 'base25' => 88, 'base50' => 75, 'base75' => 60, 'base100' => 45],
            ['url' => '/faq', 'base25' => 82, 'base50' => 65, 'base75' => 50, 'base100' => 35],
        ];

        $db = Insights::getInstance()->database->getConnection();
        $count = 0;

        for ($d = $this->days; $d >= 0; $d--) {
            $date = date('Y-m-d', strtotime("-{$d} days"));
            $weekendFactor = in_array(date('N', strtotime($date)), [6, 7]) ? 0.6 : 1.0;

            foreach ($pages as $page) {
                $randomFactor = mt_rand(70, 130) / 100;

                for ($h = 0; $h < 24; $h++) {
                    $hourFactor = $this->getHourFactor($h);

                    $milestone25 = (int)round($page['base25'] * $weekendFactor * $randomFactor * $hourFactor);
                    $milestone50 = (int)round($page['base50'] * $weekendFactor * $randomFactor * $hourFactor);
                    $milestone75 = (int)round($page['base75'] * $weekendFactor * $randomFactor * $hourFactor);
                    $milestone100 = (int)round($page['base100'] * $weekendFactor * $randomFactor * $hourFactor);

                    if ($milestone25 > 0) {
                        $db->createCommand()->upsert(
                            Constants::TABLE_SCROLL_DEPTH,
                            array_merge([
                                'siteId' => $siteId,
                                'date' => $date,
                                'hour' => $h,
                                'url' => $page['url'],
                                'milestone25' => $milestone25,
                                'milestone50' => $milestone50,
                                'milestone75' => $milestone75,
                                'milestone100' => $milestone100,
                            ], $this->getTimestampFields()),
                            [
                                'milestone25' => $milestone25,
                                'milestone50' => $milestone50,
                                'milestone75' => $milestone75,
                                'milestone100' => $milestone100,
                                'dateUpdated' => date('Y-m-d H:i:s'),
                            ]
                        )->execute();
                        $count++;
                    }
                }
            }
        }

        $this->stdout(" {$count} records\n");
    }

    private function seedSessions(int $siteId): void
    {
        $this->stdout("  Seeding sessions...");

        $entryPages = [
            ['url' => '/', 'weight' => 40],
            ['url' => '/blog', 'weight' => 20],
            ['url' => '/blog/article-1', 'weight' => 12],
            ['url' => '/products', 'weight' => 15],
            ['url' => '/pricing', 'weight' => 8],
            ['url' => '/about', 'weight' => 5],
        ];

        $exitPages = [
            ['url' => '/', 'weight' => 15],
            ['url' => '/contact', 'weight' => 25],
            ['url' => '/pricing', 'weight' => 20],
            ['url' => '/blog/article-1', 'weight' => 12],
            ['url' => '/products/item-1', 'weight' => 18],
            ['url' => '/faq', 'weight' => 10],
        ];

        $db = Insights::getInstance()->database->getConnection();
        $count = 0;

        for ($d = $this->days; $d >= 0; $d--) {
            $date = date('Y-m-d', strtotime("-{$d} days"));
            $weekendFactor = in_array(date('N', strtotime($date)), [6, 7]) ? 0.6 : 1.0;

            // Generate multiple sessions per day
            $sessionsPerDay = (int)round(80 * $weekendFactor * (mt_rand(70, 130) / 100));

            for ($s = 0; $s < $sessionsPerDay; $s++) {
                $visitorHash = md5("visitor_{$date}_{$s}_" . mt_rand(1000, 9999));
                $sessionId = substr(md5("session_{$date}_{$s}_" . mt_rand()), 0, 32);

                // Pick random entry and exit pages based on weights
                $entryPage = $this->pickWeightedRandom($entryPages);
                $exitPage = $this->pickWeightedRandom($exitPages);

                $pageCount = mt_rand(1, 8);
                $hour = $this->pickWeightedHour();
                $startTime = date('Y-m-d H:i:s', strtotime("{$date} {$hour}:00:00") + mt_rand(0, 3599));
                $lastActivityTime = date('Y-m-d H:i:s', strtotime($startTime) + ($pageCount * mt_rand(30, 180)));

                $db->createCommand()->upsert(
                    Constants::TABLE_SESSIONS,
                    array_merge([
                        'siteId' => $siteId,
                        'visitorHash' => $visitorHash,
                        'sessionId' => $sessionId,
                        'date' => $date,
                        'pageCount' => $pageCount,
                        'entryUrl' => $entryPage['url'],
                        'exitUrl' => $exitPage['url'],
                        'startTime' => $startTime,
                        'lastActivityTime' => $lastActivityTime,
                    ], $this->getTimestampFields()),
                    [
                        'pageCount' => $pageCount,
                        'exitUrl' => $exitPage['url'],
                        'lastActivityTime' => $lastActivityTime,
                        'dateUpdated' => date('Y-m-d H:i:s'),
                    ]
                )->execute();
                $count++;
            }
        }

        $this->stdout(" {$count} records\n");
    }

    /**
     * Pick a random item based on weights.
     *
     * @param array<int, array{url: string, weight: int}> $items
     * @return array{url: string, weight: int}
     */
    private function pickWeightedRandom(array $items): array
    {
        $totalWeight = array_sum(array_column($items, 'weight'));
        $random = mt_rand(1, $totalWeight);

        foreach ($items as $item) {
            $random -= $item['weight'];
            if ($random <= 0) {
                return $item;
            }
        }

        return $items[0];
    }

    /**
     * Pick a random hour weighted by traffic distribution.
     */
    private function pickWeightedHour(): int
    {
        $factors = [];
        for ($h = 0; $h < 24; $h++) {
            $factors[$h] = $this->getHourFactor($h);
        }

        $totalWeight = array_sum($factors);
        $random = mt_rand(1, (int)($totalWeight * 100)) / 100;

        foreach ($factors as $hour => $factor) {
            $random -= $factor;
            if ($random <= 0) {
                return $hour;
            }
        }

        return 12;
    }

    /**
     * Get hour factor for realistic traffic distribution.
     */
    private function getHourFactor(int $hour): float
    {
        $factors = [
            0 => 0.02, 1 => 0.01, 2 => 0.01, 3 => 0.01,
            4 => 0.01, 5 => 0.02, 6 => 0.03, 7 => 0.04,
            8 => 0.06, 9 => 0.08, 10 => 0.09, 11 => 0.08,
            12 => 0.07, 13 => 0.07, 14 => 0.08, 15 => 0.07,
            16 => 0.06, 17 => 0.05, 18 => 0.04, 19 => 0.04,
            20 => 0.03, 21 => 0.03, 22 => 0.02, 23 => 0.02,
        ];

        return $factors[$hour] ?? 0.04;
    }

    /**
     * Get timestamp fields for database inserts.
     *
     * @return array{dateCreated: string, dateUpdated: string, uid: string}
     */
    private function getTimestampFields(): array
    {
        $now = date('Y-m-d H:i:s');
        return [
            'dateCreated' => $now,
            'dateUpdated' => $now,
            'uid' => StringHelper::UUID(),
        ];
    }
}
