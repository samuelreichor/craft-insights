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

    // Session tracking
    var sessionId = getOrCreateSessionId();
    var isNewPage = true;

    // Scroll depth tracking - milestones reached on current page
    var scrollMilestonesReached = { 25: false, 50: false, 75: false, 100: false };

    /**
     * Generate a random session ID (32 characters)
     */
    function generateSessionId() {
        var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        var id = '';
        for (var i = 0; i < 32; i++) {
            id += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return id;
    }

    /**
     * Get or create session ID from sessionStorage
     */
    function getOrCreateSessionId() {
        try {
            var sid = sessionStorage.getItem('insights_sid');
            if (!sid) {
                sid = generateSessionId();
                sessionStorage.setItem('insights_sid', sid);
            }
            return sid;
        } catch (e) {
            // sessionStorage not available, generate a new one each page
            return generateSessionId();
        }
    }

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
            sc: getScreenCategory(),
            sid: sessionId,
            np: isNewPage
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
     * Track a custom event (Pro feature)
     *
     * @param {string} name - Event name (e.g., 'button_click', 'download')
     * @param {Object} options - Optional: { category: 'conversion' }
     */
    function trackEvent(name, options) {
        if (!name) return;

        var eventData = {
            name: name
        };

        if (options && options.category) {
            eventData.category = options.category;
        }

        track('ev', eventData);
    }

    /**
     * Setup automatic event tracking for elements with data-insights-event
     */
    function setupAutoEventTracking() {
        document.addEventListener('click', function(e) {
            var target = e.target;

            // Walk up the DOM to find element with data-insights-event
            while (target && target !== document) {
                var eventName = target.getAttribute('data-insights-event');
                if (eventName) {
                    var category = target.getAttribute('data-insights-category');
                    trackEvent(eventName, category ? { category: category } : null);
                    break;
                }
                target = target.parentElement;
            }
        });
    }

    /**
     * Track an outbound link click (Pro feature)
     *
     * @param {string} url - The external URL being clicked
     * @param {string} text - The link text (optional)
     */
    function trackOutbound(url, text) {
        if (!url) return;

        var outboundData = {
            target: url
        };

        if (text) {
            outboundData.text = text.substring(0, 255);
        }

        track('ob', outboundData);
    }

    /**
     * Check if a URL is external
     */
    function isExternalUrl(url) {
        try {
            var link = new URL(url, location.href);
            return link.hostname !== location.hostname &&
                   (link.protocol === 'http:' || link.protocol === 'https:');
        } catch (e) {
            return false;
        }
    }

    /**
     * Track a site search (Pro feature)
     *
     * @param {string} query - The search query
     * @param {number|null} resultsCount - Number of search results (optional)
     */
    function trackSearch(query, resultsCount) {
        if (!query || typeof query !== 'string') return;

        var searchTerm = query.trim();
        if (!searchTerm) return;

        var searchData = {
            query: searchTerm
        };

        if (typeof resultsCount === 'number' && resultsCount >= 0) {
            searchData.results = resultsCount;
        }

        track('sr', searchData);
    }

    /**
     * Setup automatic outbound link tracking
     */
    function setupOutboundTracking() {
        document.addEventListener('click', function(e) {
            var target = e.target;

            // Walk up the DOM to find an anchor element
            while (target && target !== document) {
                if (target.tagName === 'A' && target.href) {
                    // Check if it's an external link
                    if (isExternalUrl(target.href)) {
                        // Skip if data-insights-no-track is set
                        if (target.hasAttribute('data-insights-no-track')) {
                            break;
                        }

                        var linkText = target.textContent || target.innerText || '';
                        linkText = linkText.trim();

                        trackOutbound(target.href, linkText);
                    }
                    break;
                }
                target = target.parentElement;
            }
        });
    }

    /**
     * Track scroll depth (Pro feature)
     *
     * Tracks milestones at 25%, 50%, 75%, and 100%
     */
    function trackScrollDepth(percent) {
        // Determine which milestone this scroll depth corresponds to
        var milestones = [25, 50, 75, 100];
        for (var i = 0; i < milestones.length; i++) {
            var milestone = milestones[i];
            if (percent >= milestone && !scrollMilestonesReached[milestone]) {
                scrollMilestonesReached[milestone] = true;
                track('sd', { depth: milestone });
            }
        }
    }

    /**
     * Get current scroll percentage
     */
    function getScrollPercent() {
        var docHeight = Math.max(
            document.body.scrollHeight,
            document.body.offsetHeight,
            document.documentElement.clientHeight,
            document.documentElement.scrollHeight,
            document.documentElement.offsetHeight
        );
        var windowHeight = window.innerHeight || document.documentElement.clientHeight;
        var scrollTop = window.scrollY || document.documentElement.scrollTop;

        var scrollableHeight = docHeight - windowHeight;
        if (scrollableHeight <= 0) {
            return 100; // No scroll needed, page is shorter than viewport
        }

        return Math.round((scrollTop / scrollableHeight) * 100);
    }

    /**
     * Setup scroll depth tracking
     */
    function setupScrollDepthTracking() {
        var scrollHandler = function() {
            var percent = getScrollPercent();
            trackScrollDepth(percent);

            // Remove listener once all milestones reached
            if (scrollMilestonesReached[100]) {
                window.removeEventListener('scroll', scrollHandler);
            }
        };

        window.addEventListener('scroll', scrollHandler, { passive: true });

        // Also check initial scroll position (in case page loads scrolled)
        setTimeout(function() {
            var percent = getScrollPercent();
            trackScrollDepth(percent);
        }, 100);
    }

    /**
     * Reset scroll tracking for SPA navigation
     */
    function resetScrollTracking() {
        scrollMilestonesReached = { 25: false, 50: false, 75: false, 100: false };
        setupScrollDepthTracking();
    }

    /**
     * Initialize tracking
     */
    function init() {
        // Track initial pageview
        track('pv');

        // Mark as no longer a new page after first pageview
        isNewPage = false;

        // Setup automatic event tracking for data-insights-event elements
        setupAutoEventTracking();

        // Setup automatic outbound link tracking
        setupOutboundTracking();

        // Setup scroll depth tracking (Pro feature)
        setupScrollDepthTracking();

        // Track engagement on scroll (> 25% of page)
        var engageScrollHandler = function() {
            var scrollPercent = window.scrollY / (document.body.scrollHeight - window.innerHeight);
            if (scrollPercent > 0.25) {
                markEngaged();
                window.removeEventListener('scroll', engageScrollHandler);
            }
        };
        window.addEventListener('scroll', engageScrollHandler, { passive: true });

        // Track engagement on click
        window.addEventListener('click', function() {
            markEngaged();
        }, { once: true });

        // Track time on page when leaving, use multiple events for reliability
        var leaveSent = false;
        var sendLeaveEvent = function() {
            if (leaveSent) return;
            leaveSent = true;
            var timeOnPage = Math.min(Math.round((Date.now() - startTime) / 1000), 3600);
            track('lv', { tm: timeOnPage });
        };

        // visibilitychange - fires when tab becomes hidden (most reliable for tab switches)
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                sendLeaveEvent();
            }
        });

        // pagehide - fires when navigating away, more reliable on mobile Safari
        window.addEventListener('pagehide', sendLeaveEvent);

        // beforeunload - fallback for older browsers and hard closes
        window.addEventListener('beforeunload', sendLeaveEvent);

        // SPA support - track navigation
        if (window.history && window.history.pushState) {
            var originalPushState = history.pushState;
            history.pushState = function() {
                // Track leave of current page (bypass leaveSent for SPA navigation)
                var timeOnPage = Math.round((Date.now() - startTime) / 1000);
                track('lv', { tm: timeOnPage });

                // Call original
                originalPushState.apply(this, arguments);

                // Reset for new page
                startTime = Date.now();
                engaged = false;
                leaveSent = false;
                isNewPage = true;

                // Reset scroll depth tracking for new page
                resetScrollTracking();

                // Track new pageview after a short delay
                setTimeout(function() {
                    track('pv');
                    isNewPage = false;
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

    // Expose API for custom events
    window.insights = {
        track: track,
        markEngaged: markEngaged,
        trackEvent: trackEvent,
        trackOutbound: trackOutbound,
        trackSearch: trackSearch
    };
})();
