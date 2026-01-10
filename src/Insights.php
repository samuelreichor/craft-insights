<?php

namespace samuelreichor\insights;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Dashboard;
use craft\services\Fields;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use samuelreichor\insights\enums\Permission;
use samuelreichor\insights\fields\InsightsField;
use samuelreichor\insights\models\Settings;
use samuelreichor\insights\services\CleanupService;
use samuelreichor\insights\services\GeoIpService;
use samuelreichor\insights\services\LoggerService;
use samuelreichor\insights\services\StatsService;
use samuelreichor\insights\services\TrackingService;
use samuelreichor\insights\services\VisitorService;
use samuelreichor\insights\variables\InsightsVariable;
use samuelreichor\insights\widgets\OverviewWidget;
use samuelreichor\insights\widgets\RealtimeWidget;
use yii\base\Event;
use yii\log\FileTarget;

/**
 * Insights plugin
 *
 * DSGVO-compliant analytics for Craft CMS.
 * No cookies, no fingerprinting, no PII stored.
 *
 * @method static Insights getInstance()
 * @method Settings getSettings()
 * @property-read LoggerService $logger
 * @property-read VisitorService $visitor
 * @property-read GeoIpService $geoip
 * @property-read TrackingService $tracking
 * @property-read StatsService $stats
 * @property-read CleanupService $cleanup
 * @author Samuel Reichör <samuelreichor@gmail.com>
 * @copyright Samuel Reichör
 * @license https://craftcms.github.io/license/ Craft License
 */
class Insights extends Plugin
{
    public const EDITION_LITE = 'lite';
    public const EDITION_PRO = 'pro';

    public string $schemaVersion = '1.0.1';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public static function editions(): array
    {
        return [
            self::EDITION_LITE,
            self::EDITION_PRO,
        ];
    }

    public function isPro(): bool
    {
        return $this->is(self::EDITION_PRO);
    }

    public static function config(): array
    {
        return [
            'components' => [
                'logger' => LoggerService::class,
                'visitor' => VisitorService::class,
                'geoip' => GeoIpService::class,
                'tracking' => TrackingService::class,
                'stats' => StatsService::class,
                'cleanup' => CleanupService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->initLogger();
        $this->attachEventHandlers();

        // Deferred initialization
        Craft::$app->onInit(function() {
            $this->registerAutoCleanup();
        });
    }

    /**
     * Initialize dedicated log file for Insights.
     */
    private function initLogger(): void
    {
        $logFileTarget = new FileTarget([
            'logFile' => '@storage/logs/insights.log',
            'maxLogFiles' => 10,
            'categories' => ['insights'],
            'logVars' => [],
        ]);
        Craft::getLogger()->dispatcher->targets[] = $logFileTarget;
    }

    public function getCpNavItem(): ?array
    {
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user) {
            return null;
        }

        $allowedPages = [];

        // Dashboard: visible if user has at least one dashboard permission
        if ($this->userHasAnyDashboardPermission($user)) {
            $allowedPages['dashboard'] = [
                'label' => Craft::t('insights', 'Dashboard'),
                'url' => 'insights',
            ];
        }

        // Detail pages (non-Pro)
        if ($user->can(Permission::ViewPages->value)) {
            $allowedPages['pages'] = [
                'label' => Craft::t('insights', 'Pages'),
                'url' => 'insights/pages',
            ];
        }

        if ($user->can(Permission::ViewReferrers->value)) {
            $allowedPages['referrers'] = [
                'label' => Craft::t('insights', 'Referrers'),
                'url' => 'insights/referrers',
            ];
        }

        // Pro-only detail pages (require Pro edition + permission)
        if ($this->isPro()) {
            if ($user->can(Permission::ViewCampaigns->value)) {
                $allowedPages['campaigns'] = [
                    'label' => Craft::t('insights', 'Campaigns'),
                    'url' => 'insights/campaigns',
                ];
            }

            if ($user->can(Permission::ViewCountries->value)) {
                $allowedPages['countries'] = [
                    'label' => Craft::t('insights', 'Countries'),
                    'url' => 'insights/countries',
                ];
            }

            if ($user->can(Permission::ViewEvents->value)) {
                $allowedPages['events'] = [
                    'label' => Craft::t('insights', 'Events'),
                    'url' => 'insights/events',
                ];
            }

            if ($user->can(Permission::ViewOutbound->value)) {
                $allowedPages['outbound'] = [
                    'label' => Craft::t('insights', 'Outbound Links'),
                    'url' => 'insights/outbound',
                ];
            }

            if ($user->can(Permission::ViewSearches->value)) {
                $allowedPages['searches'] = [
                    'label' => Craft::t('insights', 'Site Searches'),
                    'url' => 'insights/searches',
                ];
            }

            if ($user->can(Permission::ViewEntryExitPages->value)) {
                $allowedPages['entry-exit-pages'] = [
                    'label' => Craft::t('insights', 'Entry & Exit Pages'),
                    'url' => 'insights/entry-exit-pages',
                ];
            }

            if ($user->can(Permission::ViewScrollDepth->value)) {
                $allowedPages['scroll-depth'] = [
                    'label' => Craft::t('insights', 'Scroll Depth'),
                    'url' => 'insights/scroll-depth',
                ];
            }
        }

        // No pages allowed → no menu
        if (empty($allowedPages)) {
            return null;
        }

        $item = parent::getCpNavItem();
        if ($item === null) {
            return null;
        }

        $item['label'] = Craft::t('insights', 'Insights');

        // Only 1 page → no subnav, link directly to that page
        if (count($allowedPages) === 1) {
            $singlePage = reset($allowedPages);
            $item['url'] = $singlePage['url'];
            return $item;
        }

        // Multiple pages → normal subnav
        $item['subnav'] = $allowedPages;
        return $item;
    }

    /**
     * Check if user has at least one dashboard permission.
     */
    private function userHasAnyDashboardPermission(\craft\elements\User $user): bool
    {
        // Check parent permission first
        if ($user->can(Permission::ViewDashboard->value)) {
            return true;
        }

        // Check individual card permissions
        foreach (Permission::dashboardPermissions() as $permission) {
            // Skip Pro-only permissions if not Pro edition
            if ($permission->isPro() && !$this->isPro()) {
                continue;
            }

            if ($user->can($permission->value)) {
                return true;
            }
        }

        return false;
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('insights/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function attachEventHandlers(): void
    {
        // Register CP URL rules
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['insights'] = 'insights/dashboard/index';
                $event->rules['insights/pages'] = 'insights/dashboard/pages';
                $event->rules['insights/referrers'] = 'insights/dashboard/referrers';
                $event->rules['insights/campaigns'] = 'insights/dashboard/campaigns';
                $event->rules['insights/events'] = 'insights/dashboard/events';
                $event->rules['insights/outbound'] = 'insights/dashboard/outbound';
                $event->rules['insights/searches'] = 'insights/dashboard/searches';
                $event->rules['insights/countries'] = 'insights/dashboard/countries';
                $event->rules['insights/entry-exit-pages'] = 'insights/dashboard/entry-exit-pages';
                $event->rules['insights/scroll-depth'] = 'insights/dashboard/scroll-depth';
            }
        );

        // Register Twig variable
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('insights', InsightsVariable::class);
            }
        );

        // Register widgets
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = OverviewWidget::class;
                $event->types[] = RealtimeWidget::class;
            }
        );

        // Register field type
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = InsightsField::class;
            }
        );

        // Register permissions
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                // Build nested dashboard permissions
                $dashboardNested = [];
                foreach (Permission::dashboardPermissions() as $permission) {
                    $dashboardNested[$permission->value] = [
                        'label' => Craft::t('insights', $permission->label()),
                    ];
                }

                // Build page permissions
                $pagePermissions = [];
                foreach (Permission::pagePermissions() as $permission) {
                    $pagePermissions[$permission->value] = [
                        'label' => Craft::t('insights', $permission->label()),
                    ];
                }

                $event->permissions[] = [
                    'heading' => Craft::t('insights', 'Insights'),
                    'permissions' => array_merge(
                        [
                            Permission::ViewDashboard->value => [
                                'label' => Craft::t('insights', Permission::ViewDashboard->label()),
                                'nested' => $dashboardNested,
                            ],
                        ],
                        $pagePermissions,
                        [
                            Permission::ViewEntryStats->value => [
                                'label' => Craft::t('insights', Permission::ViewEntryStats->label()),
                            ],
                        ]
                    ),
                ];
            }
        );

        // Register entry sidebar widget
        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_SIDEBAR_HTML,
            function(DefineHtmlEvent $event) {
                $this->handleEntrySidebar($event);
            }
        );
    }

    /**
     * Handle entry sidebar HTML event.
     */
    private function handleEntrySidebar(DefineHtmlEvent $event): void
    {
        // Check if setting is enabled
        $settings = $this->getSettings();
        if (!$settings->showEntrySidebar) {
            return;
        }

        // Check user permission
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user || !$user->can(Permission::ViewEntryStats->value)) {
            return;
        }

        /** @var Entry $entry */
        $entry = $event->sender;

        // Only show for saved entries with URLs
        if ($entry->id === null || !$entry->uri) {
            return;
        }

        // Get entry URL path (without domain)
        $url = '/' . ltrim($entry->uri, '/');

        // Get stats
        $range = $settings->defaultDateRange;
        $stats = $this->stats->getEntryStats($entry->id, $range);
        $realtimeCount = $this->stats->getRealtimeCountForUrl($entry->siteId, $url);

        // Render sidebar template
        $event->html .= Craft::$app->getView()->renderTemplate('insights/_entry-sidebar.twig', [
            'entry' => $entry,
            'stats' => $stats,
            'realtimeCount' => $realtimeCount,
            'range' => $range,
        ]);
    }

    private function registerAutoCleanup(): void
    {
        $settings = $this->getSettings();

        if (!$settings->autoCleanup) {
            return;
        }

        // Check if plugin is installed (tables exist)
        if (!Craft::$app->db->tableExists(Constants::TABLE_PAGEVIEWS)) {
            return;
        }

        // Run cleanup once per day using cache
        $lastCleanup = Craft::$app->cache->get(Constants::CACHE_LAST_CLEANUP);

        if ($lastCleanup === false) {
            $this->cleanup->cleanup();
            Craft::$app->cache->set(Constants::CACHE_LAST_CLEANUP, time(), Constants::CLEANUP_INTERVAL);
        }
    }
}
