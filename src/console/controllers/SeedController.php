<?php

namespace samuelreichor\insights\console\controllers;

use Craft;
use craft\console\Controller;
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

        $db = Craft::$app->getDb();
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
                            [
                                'siteId' => $siteId,
                                'date' => $date,
                                'hour' => $h,
                                'url' => $url,
                                'entryId' => null,
                            ],
                            [
                                'views' => $hourViews,
                                'uniqueVisitors' => (int)round($hourViews * 0.75),
                                'bounces' => (int)round($hourViews * 0.4),
                                'totalTimeOnPage' => $hourViews * mt_rand(30, 120),
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

        $db = Craft::$app->getDb();
        $count = 0;

        for ($d = $this->days; $d >= 0; $d--) {
            $date = date('Y-m-d', strtotime("-{$d} days"));

            foreach ($referrers as $ref) {
                $visits = (int)round($ref['weight'] * (mt_rand(80, 120) / 100));

                if ($visits > 0) {
                    $db->createCommand()->upsert(
                        Constants::TABLE_REFERRERS,
                        [
                            'siteId' => $siteId,
                            'date' => $date,
                            'referrerDomain' => $ref['domain'],
                            'referrerType' => $ref['type'],
                        ],
                        [
                            'visits' => $visits,
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

        $db = Craft::$app->getDb();
        $count = 0;

        for ($d = $this->days; $d >= 0; $d--) {
            $date = date('Y-m-d', strtotime("-{$d} days"));

            foreach ($campaigns as $c) {
                $visits = (int)round($c['weight'] * (mt_rand(70, 130) / 100));

                if ($visits > 0) {
                    $db->createCommand()->upsert(
                        Constants::TABLE_CAMPAIGNS,
                        [
                            'siteId' => $siteId,
                            'date' => $date,
                            'utmSource' => $c['source'],
                            'utmMedium' => $c['medium'],
                            'utmCampaign' => $c['campaign'],
                        ],
                        [
                            'visits' => $visits,
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
            ['type' => 'desktop', 'browser' => 'Chrome', 'os' => 'macOS', 'weight' => 15],
            ['type' => 'desktop', 'browser' => 'Firefox', 'os' => 'Windows', 'weight' => 10],
            ['type' => 'desktop', 'browser' => 'Safari', 'os' => 'macOS', 'weight' => 12],
            ['type' => 'desktop', 'browser' => 'Edge', 'os' => 'Windows', 'weight' => 8],
            ['type' => 'mobile', 'browser' => 'Safari', 'os' => 'iOS', 'weight' => 25],
            ['type' => 'mobile', 'browser' => 'Chrome', 'os' => 'Android', 'weight' => 18],
            ['type' => 'tablet', 'browser' => 'Safari', 'os' => 'iOS', 'weight' => 5],
        ];

        $db = Craft::$app->getDb();
        $count = 0;

        for ($d = $this->days; $d >= 0; $d--) {
            $date = date('Y-m-d', strtotime("-{$d} days"));

            foreach ($devices as $dev) {
                $visits = (int)round($dev['weight'] * (mt_rand(80, 120) / 100));

                if ($visits > 0) {
                    $db->createCommand()->upsert(
                        Constants::TABLE_DEVICES,
                        [
                            'siteId' => $siteId,
                            'date' => $date,
                            'deviceType' => $dev['type'],
                            'browserFamily' => $dev['browser'],
                            'osFamily' => $dev['os'],
                        ],
                        [
                            'visits' => $visits,
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

        $db = Craft::$app->getDb();
        $count = 0;

        for ($d = $this->days; $d >= 0; $d--) {
            $date = date('Y-m-d', strtotime("-{$d} days"));

            foreach ($countries as $c) {
                $visits = (int)round($c['weight'] * (mt_rand(80, 120) / 100));

                if ($visits > 0) {
                    $db->createCommand()->upsert(
                        Constants::TABLE_COUNTRIES,
                        [
                            'siteId' => $siteId,
                            'date' => $date,
                            'countryCode' => $c['code'],
                        ],
                        [
                            'visits' => $visits,
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

        $db = Craft::$app->getDb();
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
                                [
                                    'siteId' => $siteId,
                                    'date' => $date,
                                    'hour' => $h,
                                    'eventName' => $event['name'],
                                    'eventCategory' => $event['category'],
                                    'url' => $event['url'],
                                ],
                                [
                                    'count' => $hourCount,
                                    'uniqueVisitors' => (int)round($hourCount * 0.7),
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
}
