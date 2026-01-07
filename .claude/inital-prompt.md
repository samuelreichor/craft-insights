# Claude Code Prompt: DSGVO-konformes Analytics Plugin für Craft CMS 5

## Projektübersicht

Erstelle ein vollständiges Craft CMS 5 Plugin namens **"Craft Insights"** – ein DSGVO-konformes, cookieloses First-Party Analytics System. Das Plugin soll eine datenschutzfreundliche Alternative zu Google Analytics sein, die OHNE Cookie-Banner funktioniert.

## Technische Anforderungen

### Craft CMS Kompatibilität
- Craft CMS 5.0+
- PHP 8.2+
- MySQL 8.0+ / PostgreSQL 13+
- Composer-basierte Installation

### Plugin-Struktur
```
craft-insights/
├── src/
│   ├── Insights.php                    # Haupt-Plugin-Klasse
│   ├── controllers/
│   │   ├── TrackController.php         # Frontend Tracking API (public)
│   │   ├── DashboardController.php     # Control Panel Dashboard
│   │   └── ExportController.php        # Daten-Export (CSV/JSON)
│   ├── models/
│   │   ├── Settings.php                # Plugin-Einstellungen
│   │   └── PageviewModel.php
│   ├── records/
│   │   ├── PageviewRecord.php
│   │   ├── ReferrerRecord.php
│   │   ├── CampaignRecord.php
│   │   ├── DeviceRecord.php
│   │   ├── CountryRecord.php
│   │   └── RealtimeRecord.php
│   ├── services/
│   │   ├── TrackingService.php         # Tracking-Logik
│   │   ├── VisitorService.php          # Visitor-Hash-Generierung
│   │   ├── StatsService.php            # Statistik-Abfragen
│   │   ├── GeoIpService.php            # Land-Erkennung
│   │   └── CleanupService.php          # Daten-Bereinigung
│   ├── jobs/
│   │   └── ProcessTrackingJob.php      # Queue-Job für Tracking
│   ├── fields/
│   │   └── InsightsFieldType.php       # Entry-Stats Field
│   ├── widgets/
│   │   ├── OverviewWidget.php          # Dashboard Widget
│   │   └── RealtimeWidget.php          # Echtzeit-Widget
│   ├── migrations/
│   │   └── Install.php
│   ├── templates/
│   │   ├── _index.twig                 # Haupt-Dashboard
│   │   ├── pages/
│   │   │   ├── _index.twig             # Seiten-Übersicht
│   │   │   └── _detail.twig            # Einzelseiten-Statistik
│   │   ├── settings.twig
│   │   ├── _components/
│   │   │   ├── _chart.twig
│   │   │   ├── _kpi-card.twig
│   │   │   └── _table.twig
│   │   └── _fields/
│   │       └── _stats.twig
│   └── web/
│       └── assets/
│           ├── src/
│           │   ├── js/
│           │   │   ├── insights.js     # Frontend Tracker
│           │   │   └── dashboard.js    # CP Dashboard
│           │   └── css/
│           │       └── dashboard.css
│           └── InsightsAsset.php
├── composer.json
├── LICENSE.md
└── README.md
```

---

## DSGVO-Konformität (KRITISCH)

### Was NICHT gespeichert werden darf:
- ❌ IP-Adressen (auch nicht gehasht)
- ❌ Cookies jeglicher Art
- ❌ Browser-Fingerprints
- ❌ User-IDs oder Session-IDs
- ❌ Exakte Geolocation (Stadt/Region)
- ❌ Personenbezogene Daten (PII)
- ❌ Cross-Site-Tracking-Daten

### Was gespeichert werden darf (aggregiert):
- ✅ Pageviews (Zähler pro URL/Tag)
- ✅ Unique Visitors (täglicher, nicht-persistenter Hash)
- ✅ Referrer-Domain (nur Domain, keine vollständige URL)
- ✅ UTM-Parameter (Kampagnen-Tracking)
- ✅ Land (nur ISO-Code, keine Stadt)
- ✅ Gerätetyp (desktop/mobile/tablet)
- ✅ Browser-Familie (Chrome/Firefox/Safari/Edge)
- ✅ OS-Familie (Windows/macOS/iOS/Android/Linux)
- ✅ Verweildauer (aggregiert)
- ✅ Bounce-Rate

### Unique Visitor Tracking (ohne Cookies)

Implementiere einen täglichen, nicht-persistenten Visitor-Hash:

```php
public function generateDailyVisitorHash(Request $request): string
{
    // Täglicher Server-Salt (wird jeden Tag neu generiert)
    $salt = $this->getDailySalt();
    
    // Nur grobe, nicht-identifizierende Attribute
    $attributes = [
        $salt,
        date('Y-m-d'),                              // Nur heute gültig
        $this->getBrowserFamily($request),          // Chrome, Firefox, etc.
        $request->getAcceptableLanguages()[0] ?? 'en',
        $this->getScreenCategory($request),         // small/medium/large
    ];
    
    // WICHTIG: Kein IP, kein exakter User-Agent!
    return hash('sha256', implode('|', $attributes));
}

private function getDailySalt(): string
{
    $today = date('Y-m-d');
    $cacheKey = "insights_salt_{$today}";
    
    $salt = Craft::$app->cache->get($cacheKey);
    if (!$salt) {
        $salt = bin2hex(random_bytes(32));
        Craft::$app->cache->set($cacheKey, $salt, 86400);
    }
    
    return $salt;
}
```

### GeoIP-Verarbeitung (IP → Land → IP verwerfen)

```php
public function getCountryFromIp(string $ip): ?string
{
    // MaxMind GeoLite2-Country Datenbank (lokal)
    // NUR Land-Code extrahieren, IP wird NICHT gespeichert
    
    try {
        $reader = new \GeoIp2\Database\Reader($this->getGeoIpDbPath());
        $record = $reader->country($ip);
        return $record->country->isoCode; // z.B. "DE"
    } catch (\Exception $e) {
        return null;
    }
    
    // IP wird hier NICHT weitergegeben oder gespeichert!
}
```

---

## Datenbank-Schema

```php
// migrations/Install.php

public function safeUp(): bool
{
    // Aggregierte Pageviews (keine PII)
    $this->createTable('{{%insights_pageviews}}', [
        'id' => $this->primaryKey(),
        'siteId' => $this->integer()->notNull(),
        'date' => $this->date()->notNull(),
        'hour' => $this->tinyInteger()->unsigned(),
        'url' => $this->string(500)->notNull(),
        'entryId' => $this->integer()->null(),
        'views' => $this->integer()->unsigned()->defaultValue(0),
        'uniqueVisitors' => $this->integer()->unsigned()->defaultValue(0),
        'bounces' => $this->integer()->unsigned()->defaultValue(0),
        'totalTimeOnPage' => $this->integer()->unsigned()->defaultValue(0),
        'dateCreated' => $this->dateTime()->notNull(),
        'dateUpdated' => $this->dateTime()->notNull(),
        'uid' => $this->uid(),
    ]);
    
    // Referrer (nur Domains)
    $this->createTable('{{%insights_referrers}}', [
        'id' => $this->primaryKey(),
        'siteId' => $this->integer()->notNull(),
        'date' => $this->date()->notNull(),
        'referrerDomain' => $this->string(255)->null(),
        'referrerType' => $this->string(20)->defaultValue('direct'),
        'visits' => $this->integer()->unsigned()->defaultValue(0),
        'dateCreated' => $this->dateTime()->notNull(),
        'dateUpdated' => $this->dateTime()->notNull(),
        'uid' => $this->uid(),
    ]);
    
    // UTM Kampagnen
    $this->createTable('{{%insights_campaigns}}', [
        'id' => $this->primaryKey(),
        'siteId' => $this->integer()->notNull(),
        'date' => $this->date()->notNull(),
        'utmSource' => $this->string(100)->null(),
        'utmMedium' => $this->string(100)->null(),
        'utmCampaign' => $this->string(100)->null(),
        'utmTerm' => $this->string(100)->null(),
        'utmContent' => $this->string(100)->null(),
        'visits' => $this->integer()->unsigned()->defaultValue(0),
        'dateCreated' => $this->dateTime()->notNull(),
        'dateUpdated' => $this->dateTime()->notNull(),
        'uid' => $this->uid(),
    ]);
    
    // Geräte & Browser
    $this->createTable('{{%insights_devices}}', [
        'id' => $this->primaryKey(),
        'siteId' => $this->integer()->notNull(),
        'date' => $this->date()->notNull(),
        'deviceType' => $this->string(20)->notNull(),
        'browserFamily' => $this->string(50)->null(),
        'osFamily' => $this->string(50)->null(),
        'visits' => $this->integer()->unsigned()->defaultValue(0),
        'dateCreated' => $this->dateTime()->notNull(),
        'dateUpdated' => $this->dateTime()->notNull(),
        'uid' => $this->uid(),
    ]);
    
    // Länder (nur Land-Code)
    $this->createTable('{{%insights_countries}}', [
        'id' => $this->primaryKey(),
        'siteId' => $this->integer()->notNull(),
        'date' => $this->date()->notNull(),
        'countryCode' => $this->char(2)->notNull(),
        'visits' => $this->integer()->unsigned()->defaultValue(0),
        'dateCreated' => $this->dateTime()->notNull(),
        'dateUpdated' => $this->dateTime()->notNull(),
        'uid' => $this->uid(),
    ]);
    
    // Echtzeit (temporär, 5 Min TTL)
    $this->createTable('{{%insights_realtime}}', [
        'id' => $this->primaryKey(),
        'siteId' => $this->integer()->notNull(),
        'visitorHash' => $this->string(64)->notNull(),
        'currentUrl' => $this->string(500)->notNull(),
        'lastSeen' => $this->dateTime()->notNull(),
    ]);
    
    // Indizes
    $this->createIndex(null, '{{%insights_pageviews}}', ['siteId', 'date']);
    $this->createIndex(null, '{{%insights_pageviews}}', ['siteId', 'date', 'url']);
    $this->createIndex(null, '{{%insights_pageviews}}', ['entryId']);
    $this->createIndex(null, '{{%insights_referrers}}', ['siteId', 'date']);
    $this->createIndex(null, '{{%insights_campaigns}}', ['siteId', 'date']);
    $this->createIndex(null, '{{%insights_devices}}', ['siteId', 'date']);
    $this->createIndex(null, '{{%insights_countries}}', ['siteId', 'date']);
    $this->createIndex(null, '{{%insights_realtime}}', ['lastSeen']);
    
    // Foreign Keys
    $this->addForeignKey(null, '{{%insights_pageviews}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE');
    $this->addForeignKey(null, '{{%insights_pageviews}}', ['entryId'], '{{%entries}}', ['id'], 'SET NULL');
    
    return true;
}
```

---

## Frontend Tracking Script

Erstelle ein minimales Tracking-Script (< 1KB gzipped):

```javascript
// web/assets/src/js/insights.js

(function() {
    'use strict';
    
    const API = '/actions/insights/track';
    const startTime = Date.now();
    let engaged = false;
    
    // Basis-Daten (keine PII)
    const getData = () => ({
        u: location.pathname,                    // URL (path only)
        r: document.referrer 
            ? new URL(document.referrer).hostname 
            : null,                              // Referrer Domain
        utm: {
            s: getParam('utm_source'),
            m: getParam('utm_medium'),
            c: getParam('utm_campaign'),
            t: getParam('utm_term'),
            n: getParam('utm_content')
        },
        sc: innerWidth < 768 ? 's' : innerWidth < 1200 ? 'm' : 'l'
    });
    
    const getParam = (n) => new URLSearchParams(location.search).get(n);
    
    // Tracking via Beacon API
    const track = (type, extra = {}) => {
        const data = { t: type, ...getData(), ...extra };
        
        if (navigator.sendBeacon) {
            navigator.sendBeacon(API, JSON.stringify(data));
        } else {
            fetch(API, {
                method: 'POST',
                body: JSON.stringify(data),
                keepalive: true,
                headers: { 'Content-Type': 'application/json' }
            }).catch(() => {});
        }
    };
    
    // Initial Pageview
    track('pv');
    
    // Engagement (für Bounce-Rate)
    const markEngaged = () => {
        if (!engaged) {
            engaged = true;
            track('en');
        }
    };
    
    // Scroll > 25% = Engaged
    addEventListener('scroll', function onScroll() {
        if (scrollY > document.body.scrollHeight * 0.25) {
            markEngaged();
            removeEventListener('scroll', onScroll);
        }
    }, { passive: true });
    
    // Click = Engaged
    addEventListener('click', markEngaged, { once: true });
    
    // Time on Page beim Verlassen
    addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            track('lv', { 
                tm: Math.min(Math.round((Date.now() - startTime) / 1000), 3600)
            });
        }
    });
    
    // SPA Support
    const _push = history.pushState;
    history.pushState = function() {
        track('lv', { tm: Math.round((Date.now() - startTime) / 1000) });
        _push.apply(this, arguments);
        setTimeout(() => track('pv'), 10);
    };
})();
```

---

## Backend Controllers

### TrackController (Public API)

```php
// controllers/TrackController.php

<?php
namespace your\namespace\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

class TrackController extends Controller
{
    protected array|bool|int $allowAnonymous = ['index'];
    public $enableCsrfValidation = false;
    
    public function actionIndex(): Response
    {
        $this->requirePostRequest();
        
        $request = Craft::$app->getRequest();
        $data = json_decode($request->getRawBody(), true);
        
        if (!$data) {
            return $this->asJson(['status' => 'error']);
        }
        
        // Bot-Filterung
        if ($this->isBot($request)) {
            return $this->asJson(['status' => 'bot']);
        }
        
        // Do Not Track respektieren
        $settings = Insights::getInstance()->getSettings();
        if ($settings->respectDoNotTrack && $request->getHeaders()->has('DNT')) {
            return $this->asJson(['status' => 'dnt']);
        }
        
        // Tracking verarbeiten (via Queue für Performance)
        Craft::$app->queue->push(new ProcessTrackingJob([
            'type' => $data['t'] ?? 'pv',
            'data' => $data,
            'userAgent' => $request->getUserAgent(),
            'ip' => $request->getUserIP(), // Wird nur für GeoIP verwendet, nicht gespeichert!
            'siteId' => Craft::$app->getSites()->getCurrentSite()->id,
        ]));
        
        return $this->asJson(['status' => 'ok']);
    }
    
    private function isBot(Request $request): bool
    {
        $ua = strtolower($request->getUserAgent() ?? '');
        $bots = ['bot', 'crawl', 'spider', 'slurp', 'lighthouse', 'pagespeed', 'gtmetrix'];
        
        foreach ($bots as $bot) {
            if (str_contains($ua, $bot)) return true;
        }
        
        return false;
    }
}
```

### DashboardController

```php
// controllers/DashboardController.php

<?php
namespace your\namespace\controllers;

use Craft;
use craft\web\Controller;

class DashboardController extends Controller
{
    public function actionIndex(): \yii\web\Response
    {
        $this->requirePermission('insights:viewDashboard');
        
        $siteId = Craft::$app->getRequest()->getQueryParam('siteId') 
            ?? Craft::$app->getSites()->getCurrentSite()->id;
        $range = Craft::$app->getRequest()->getQueryParam('range', '7d');
        
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
        ]);
    }
    
    public function actionRealtimeData(): \yii\web\Response
    {
        $this->requireAcceptsJson();
        
        $siteId = Craft::$app->getRequest()->getQueryParam('siteId');
        $realtime = Insights::getInstance()->stats->getRealtimeVisitors($siteId);
        
        return $this->asJson($realtime);
    }
}
```

---

## Services

### TrackingService

```php
// services/TrackingService.php

<?php
namespace your\namespace\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use yii\db\Expression;

class TrackingService extends Component
{
    public function processPageview(array $data, string $userAgent, string $ip, int $siteId): void
    {
        $date = date('Y-m-d');
        $hour = (int) date('H');
        $url = $this->sanitizeUrl($data['u'] ?? '/');
        
        // Visitor-Hash generieren
        $visitorHash = Insights::getInstance()->visitor->generateHash($userAgent, $data['sc'] ?? 'm');
        
        // Entry-ID ermitteln
        $entryId = $this->findEntryByUrl($url, $siteId);
        
        // Ist dies ein neuer Besucher für diese URL heute?
        $isNew = $this->isNewVisitor($visitorHash, $url, $siteId, $date);
        
        // Pageview aggregieren (UPSERT)
        Db::upsert('{{%insights_pageviews}}', [
            'siteId' => $siteId,
            'date' => $date,
            'hour' => $hour,
            'url' => $url,
            'entryId' => $entryId,
        ], [
            'views' => new Expression('[[views]] + 1'),
            'uniqueVisitors' => $isNew 
                ? new Expression('[[uniqueVisitors]] + 1') 
                : new Expression('[[uniqueVisitors]]'),
            'bounces' => $isNew 
                ? new Expression('[[bounces]] + 1') 
                : new Expression('[[bounces]]'),
        ]);
        
        // Referrer tracken
        if (!empty($data['r'])) {
            $this->trackReferrer($data['r'], $siteId, $date);
        }
        
        // UTM tracken
        if (!empty($data['utm']['s'])) {
            $this->trackCampaign($data['utm'], $siteId, $date);
        }
        
        // Gerät tracken
        $this->trackDevice($userAgent, $data['sc'] ?? 'm', $siteId, $date);
        
        // Land tracken (IP → Land → IP verwerfen)
        $countryCode = Insights::getInstance()->geoip->getCountry($ip);
        if ($countryCode) {
            $this->trackCountry($countryCode, $siteId, $date);
        }
        
        // Echtzeit aktualisieren
        $this->updateRealtime($visitorHash, $url, $siteId);
    }
    
    public function processEngagement(array $data, int $siteId): void
    {
        $url = $this->sanitizeUrl($data['u'] ?? '/');
        $date = date('Y-m-d');
        
        // Bounce dekrementieren
        Craft::$app->db->createCommand()
            ->update('{{%insights_pageviews}}', [
                'bounces' => new Expression('GREATEST([[bounces]] - 1, 0)')
            ], [
                'siteId' => $siteId,
                'date' => $date,
                'url' => $url,
            ])
            ->execute();
    }
    
    public function processLeave(array $data, int $siteId): void
    {
        $url = $this->sanitizeUrl($data['u'] ?? '/');
        $time = min((int) ($data['tm'] ?? 0), 3600); // Max 1h
        
        if ($time > 0) {
            Craft::$app->db->createCommand()
                ->update('{{%insights_pageviews}}', [
                    'totalTimeOnPage' => new Expression("[[totalTimeOnPage]] + {$time}")
                ], [
                    'siteId' => $siteId,
                    'date' => date('Y-m-d'),
                    'url' => $url,
                ])
                ->execute();
        }
    }
    
    private function sanitizeUrl(string $url): string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        
        // Normalisieren
        $path = rtrim($path, '/') ?: '/';
        
        return substr($path, 0, 500);
    }
    
    private function isNewVisitor(string $hash, string $url, int $siteId, string $date): bool
    {
        $key = "insights_v_{$siteId}_{$date}_{$hash}_" . md5($url);
        
        if (Craft::$app->cache->exists($key)) {
            return false;
        }
        
        // Bis Mitternacht cachen
        $ttl = strtotime('tomorrow') - time();
        Craft::$app->cache->set($key, 1, $ttl);
        
        return true;
    }
    
    private function trackReferrer(string $domain, int $siteId, string $date): void
    {
        $type = $this->classifyReferrer($domain);
        
        Db::upsert('{{%insights_referrers}}', [
            'siteId' => $siteId,
            'date' => $date,
            'referrerDomain' => $domain,
            'referrerType' => $type,
        ], [
            'visits' => new Expression('[[visits]] + 1'),
        ]);
    }
    
    private function classifyReferrer(string $domain): string
    {
        $searchEngines = ['google', 'bing', 'yahoo', 'duckduckgo', 'baidu', 'yandex'];
        $social = ['facebook', 'twitter', 'linkedin', 'instagram', 'pinterest', 'youtube', 'tiktok'];
        
        $domain = strtolower($domain);
        
        foreach ($searchEngines as $se) {
            if (str_contains($domain, $se)) return 'search';
        }
        
        foreach ($social as $s) {
            if (str_contains($domain, $s)) return 'social';
        }
        
        return 'referral';
    }
    
    private function trackCampaign(array $utm, int $siteId, string $date): void
    {
        Db::upsert('{{%insights_campaigns}}', [
            'siteId' => $siteId,
            'date' => $date,
            'utmSource' => substr($utm['s'] ?? '', 0, 100) ?: null,
            'utmMedium' => substr($utm['m'] ?? '', 0, 100) ?: null,
            'utmCampaign' => substr($utm['c'] ?? '', 0, 100) ?: null,
            'utmTerm' => substr($utm['t'] ?? '', 0, 100) ?: null,
            'utmContent' => substr($utm['n'] ?? '', 0, 100) ?: null,
        ], [
            'visits' => new Expression('[[visits]] + 1'),
        ]);
    }
    
    private function trackDevice(string $userAgent, string $screenCat, int $siteId, string $date): void
    {
        $parser = new \WhichBrowser\Parser($userAgent);
        
        $deviceType = match ($parser->device->type) {
            'mobile' => 'mobile',
            'tablet' => 'tablet',
            default => 'desktop'
        };
        
        $browserFamily = $parser->browser->name ?? 'Unknown';
        $osFamily = $parser->os->name ?? 'Unknown';
        
        Db::upsert('{{%insights_devices}}', [
            'siteId' => $siteId,
            'date' => $date,
            'deviceType' => $deviceType,
            'browserFamily' => substr($browserFamily, 0, 50),
            'osFamily' => substr($osFamily, 0, 50),
        ], [
            'visits' => new Expression('[[visits]] + 1'),
        ]);
    }
    
    private function trackCountry(string $countryCode, int $siteId, string $date): void
    {
        Db::upsert('{{%insights_countries}}', [
            'siteId' => $siteId,
            'date' => $date,
            'countryCode' => $countryCode,
        ], [
            'visits' => new Expression('[[visits]] + 1'),
        ]);
    }
    
    private function updateRealtime(string $hash, string $url, int $siteId): void
    {
        // Alte Einträge löschen (> 5 Min)
        Craft::$app->db->createCommand()
            ->delete('{{%insights_realtime}}', [
                'and',
                ['<', 'lastSeen', date('Y-m-d H:i:s', strtotime('-5 minutes'))]
            ])
            ->execute();
        
        // Aktuellen Besucher updaten/einfügen
        Db::upsert('{{%insights_realtime}}', [
            'siteId' => $siteId,
            'visitorHash' => $hash,
        ], [
            'currentUrl' => $url,
            'lastSeen' => date('Y-m-d H:i:s'),
        ]);
    }
}
```

---

## Plugin Settings

```php
// models/Settings.php

<?php
namespace your\namespace\models;

use craft\base\Model;

class Settings extends Model
{
    // Tracking
    public bool $enabled = true;
    public bool $trackPageviews = true;
    public bool $trackOutboundLinks = false;
    public bool $trackFileDownloads = false;
    
    // Privacy (DSGVO)
    public bool $respectDoNotTrack = true;
    public bool $excludeLoggedInUsers = false;
    public bool $excludeAdmins = true;
    public array $excludedIpRanges = [];
    public array $excludedPaths = ['/admin', '/cpresources', '/actions'];
    
    // GeoIP
    public bool $trackCountry = true;
    public string $geoIpDatabasePath = '@storage/geoip/GeoLite2-Country.mmdb';
    
    // Data Retention
    public int $dataRetentionDays = 365;
    public bool $autoCleanup = true;
    
    // Performance
    public bool $useQueue = true;
    public int $realtimeTtl = 300; // 5 Minuten
    
    // Dashboard
    public string $defaultDateRange = '30d';
    public bool $showRealtimeWidget = true;
    
    public function rules(): array
    {
        return [
            [['dataRetentionDays'], 'integer', 'min' => 1, 'max' => 730],
            [['realtimeTtl'], 'integer', 'min' => 60, 'max' => 900],
            [['excludedPaths', 'excludedIpRanges'], 'each', 'rule' => ['string']],
        ];
    }
}
```

---

## Control Panel Templates

### Dashboard (templates/_index.twig)

Erstelle ein modernes, responsives Dashboard mit:

1. **Header**
    - Plugin-Name & Logo
    - Site-Switcher (für Multi-Site)
    - Zeitraum-Auswahl (Heute, 7 Tage, 30 Tage, 90 Tage, 12 Monate, Custom)

2. **KPI-Karten**
    - Seitenaufrufe (mit Trend-Pfeil)
    - Unique Visitors (mit Trend)
    - Durchschnittliche Verweildauer
    - Absprungrate

3. **Echtzeit-Sektion**
    - Aktuelle Besucher (pulsierender Indikator)
    - Liste der aktuell besuchten Seiten

4. **Chart**
    - Liniendiagramm mit Pageviews & Visitors
    - Chart.js oder ApexCharts

5. **Tabellen**
    - Top Seiten
    - Traffic-Quellen (mit Icons für Typ)
    - Top Länder (mit Flaggen-Emojis)
    - Geräte & Browser (Donut-Chart)

6. **Footer**
    - Privacy Badge: "🔒 100% DSGVO-konform"

---

## Twig Integration

```twig
{# Tracking-Script einbinden #}
{{ craft.insights.trackingScript() }}

{# Entry-Stats abrufen #}
{% set stats = craft.insights.getEntryStats(entry.id, '30d') %}
<p>{{ stats.views }} Aufrufe</p>

{# Custom Event tracken #}
{{ craft.insights.trackEvent('download', 'pdf', entry.title) }}
```

---

## Console Commands

```php
// Daten bereinigen
./craft insights/cleanup

// GeoIP-Datenbank aktualisieren
./craft insights/geoip/update

// Statistiken aggregieren
./craft insights/aggregate
```

---

## Permissions

Erstelle folgende Berechtigungen:
- `insights:viewDashboard` - Dashboard ansehen
- `insights:viewDetailedStats` - Detaillierte Statistiken
- `insights:exportData` - Daten exportieren
- `insights:manageSettings` - Einstellungen verwalten

---

## Zusätzliche Anforderungen

1. **Performance**: Tracking darf die Seitenladung nicht beeinflussen (async, Queue)
2. **Multi-Site**: Vollständige Multi-Site-Unterstützung
3. **Caching**: Aggressive Caching der Dashboard-Daten
4. **Export**: CSV und JSON Export
5. **Widget**: Dashboard-Widget für Craft CP
6. **Field Type**: Entry-Stats-Feld für den Eintrag-Editor
7. **Events**: Craft-Events für Erweiterbarkeit

---

## Composer Dependencies

```json
{
    "require": {
        "craftcms/cms": "^5.0",
        "geoip2/geoip2": "^3.0",
        "whichbrowser/parser": "^2.1"
    }
}
```

---

## Tests

Erstelle PHPUnit-Tests für:
- Visitor-Hash-Generierung (muss täglich unterschiedlich sein)
- Tracking-Service (keine PII in Datenbank)
- GeoIP-Service (IP wird nicht gespeichert)
- Stats-Service (korrekte Aggregation)

---

Bitte implementiere dieses Plugin vollständig mit allen oben genannten Komponenten. Achte besonders auf:

1. **DSGVO-Konformität** - Keine PII, keine Cookies, kein Cross-Site-Tracking
2. **Performance** - Minimales Script, Queue-basiertes Tracking
3. **Code-Qualität** - PSR-12, Type Hints, Dokumentation
4. **Craft Best Practices** - Services, Records, Migrations, Events
