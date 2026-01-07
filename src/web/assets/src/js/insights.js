/**
 * Insights - DSGVO-compliant tracking script
 */
(function() {
    'use strict';

    // Configuration - can be overridden via window.insightsConfig
    var config = window.insightsConfig || {};
    var API = config.endpoint || '/actions/insights/track';
    var startTime = Date.now();
    var engaged = false;

    /**
     * Get URL parameter by name
     */
    function getParam(name) {
        try {
            return new URLSearchParams(location.search).get(name);
        } catch (e) {
            return null;
        }
    }

    /**
     * Get screen category (small/medium/large)
     */
    function getScreenCategory() {
        var w = window.innerWidth || document.documentElement.clientWidth || 768;
        if (w < 768) return 's';
        if (w < 1200) return 'm';
        return 'l';
    }

    /**
     * Get referrer domain only (privacy-friendly)
     */
    function getReferrerDomain() {
        try {
            if (!document.referrer) return null;
            var url = new URL(document.referrer);
            // Don't track internal referrers
            if (url.hostname === location.hostname) return null;
            return url.hostname;
        } catch (e) {
            return null;
        }
    }

    /**
     * Collect base tracking data (no PII)
     */
    function getData() {
        return {
            u: location.pathname,
            r: getReferrerDomain(),
            utm: {
                s: getParam('utm_source'),
                m: getParam('utm_medium'),
                c: getParam('utm_campaign'),
                t: getParam('utm_term'),
                n: getParam('utm_content')
            },
            sc: getScreenCategory()
        };
    }

    /**
     * Send tracking data to server
     */
    function track(type, extra) {
        var data = { t: type };
        var base = getData();

        // Merge base data
        for (var key in base) {
            if (base.hasOwnProperty(key)) {
                data[key] = base[key];
            }
        }

        // Merge extra data
        if (extra) {
            for (var key in extra) {
                if (extra.hasOwnProperty(key)) {
                    data[key] = extra[key];
                }
            }
        }

        var payload = JSON.stringify(data);

        // Use Beacon API for reliable delivery
        if (navigator.sendBeacon) {
            navigator.sendBeacon(API, payload);
        } else {
            // Fallback to fetch
            try {
                fetch(API, {
                    method: 'POST',
                    body: payload,
                    keepalive: true,
                    headers: { 'Content-Type': 'application/json' }
                }).catch(function() {});
            } catch (e) {
                // Silently fail
            }
        }
    }

    /**
     * Mark user as engaged (for bounce rate calculation)
     */
    function markEngaged() {
        if (!engaged) {
            engaged = true;
            track('en');
        }
    }

    /**
     * Initialize tracking
     */
    function init() {
        // Track initial pageview
        track('pv');

        // Track engagement on scroll (> 25% of page)
        var scrollHandler = function() {
            var scrollPercent = window.scrollY / (document.body.scrollHeight - window.innerHeight);
            if (scrollPercent > 0.25) {
                markEngaged();
                window.removeEventListener('scroll', scrollHandler);
            }
        };
        window.addEventListener('scroll', scrollHandler, { passive: true });

        // Track engagement on click
        window.addEventListener('click', function() {
            markEngaged();
        }, { once: true });

        // Track time on page when leaving
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                var timeOnPage = Math.min(Math.round((Date.now() - startTime) / 1000), 3600);
                track('lv', { tm: timeOnPage });
            }
        });

        // SPA support - track navigation
        if (window.history && window.history.pushState) {
            var originalPushState = history.pushState;
            history.pushState = function() {
                // Track leave of current page
                var timeOnPage = Math.round((Date.now() - startTime) / 1000);
                track('lv', { tm: timeOnPage });

                // Call original
                originalPushState.apply(this, arguments);

                // Reset for new page
                startTime = Date.now();
                engaged = false;

                // Track new pageview after a short delay
                setTimeout(function() {
                    track('pv');
                }, 10);
            };
        }
    }

    // Start tracking when DOM is ready
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }

    // Expose API for custom events (optional)
    window.insights = {
        track: track,
        markEngaged: markEngaged
    };
})();
