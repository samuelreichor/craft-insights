/**
 * Insights Dashboard JavaScript
 *
 * Handles the Control Panel dashboard functionality including:
 * - Chart rendering
 * - Realtime updates
 * - Interactive elements
 */
(function() {
    'use strict';

    var InsightsDashboard = {
        chart: null,
        realtimeInterval: null,
        siteId: null,
        range: null,

        /**
         * Initialize the dashboard
         */
        init: function(config) {
            this.siteId = config.siteId;
            this.range = config.range;

            this.initChart(config.chartData);
            this.initRealtime();
            this.initFilters();
            this.initTooltips();
        },

        /**
         * Initialize the main chart
         */
        initChart: function(data) {
            var ctx = document.getElementById('insights-chart');
            if (!ctx || typeof Chart === 'undefined') return;

            this.chart = new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Pageviews',
                            data: data.pageviews,
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'Visitors',
                            data: data.visitors,
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            fill: true,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: { size: 14 },
                            bodyFont: { size: 13 }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        }
                    }
                }
            });
        },

        /**
         * Initialize realtime updates
         */
        initRealtime: function() {
            var self = this;
            var realtimeEl = document.getElementById('realtime-count');

            if (!realtimeEl) return;

            // Update every 30 seconds
            this.realtimeInterval = setInterval(function() {
                self.fetchRealtimeData();
            }, 30000);
        },

        /**
         * Fetch realtime data from server
         */
        fetchRealtimeData: function() {
            var self = this;
            var realtimeEl = document.getElementById('realtime-count');
            var realtimePagesEl = document.getElementById('realtime-pages');

            fetch('/actions/insights/dashboard/realtime-data?siteId=' + this.siteId, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (realtimeEl) {
                    realtimeEl.textContent = data.count;
                }

                if (realtimePagesEl && data.pages) {
                    var html = '';
                    data.pages.slice(0, 6).forEach(function(page) {
                        html += '<li class="realtime-page">';
                        html += '<span class="count">' + page.count + '</span>';
                        html += '<span class="url">' + self.escapeHtml(page.url) + '</span>';
                        html += '</li>';
                    });
                    realtimePagesEl.innerHTML = html;
                }
            })
            .catch(function(error) {
                console.warn('Failed to fetch realtime data:', error);
            });
        },

        /**
         * Initialize filter controls
         */
        initFilters: function() {
            var self = this;

            // Range selector
            var rangeSelect = document.getElementById('range-select');
            if (rangeSelect) {
                rangeSelect.addEventListener('change', function() {
                    self.updateUrl({ range: this.value });
                });
            }

            // Site selector
            var siteSelect = document.getElementById('site-select');
            if (siteSelect) {
                siteSelect.addEventListener('change', function() {
                    self.updateUrl({ siteId: this.value });
                });
            }
        },

        /**
         * Update URL with new parameters
         */
        updateUrl: function(params) {
            var url = new URL(window.location);
            for (var key in params) {
                if (params.hasOwnProperty(key)) {
                    url.searchParams.set(key, params[key]);
                }
            }
            window.location = url.toString();
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            var tooltips = document.querySelectorAll('[data-tooltip]');
            tooltips.forEach(function(el) {
                el.addEventListener('mouseenter', function() {
                    var tip = document.createElement('div');
                    tip.className = 'insights-tooltip';
                    tip.textContent = this.getAttribute('data-tooltip');
                    document.body.appendChild(tip);

                    var rect = this.getBoundingClientRect();
                    tip.style.left = rect.left + (rect.width / 2) - (tip.offsetWidth / 2) + 'px';
                    tip.style.top = rect.top - tip.offsetHeight - 8 + 'px';
                });

                el.addEventListener('mouseleave', function() {
                    var tips = document.querySelectorAll('.insights-tooltip');
                    tips.forEach(function(t) { t.remove(); });
                });
            });
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Format number with locale
         */
        formatNumber: function(num) {
            return new Intl.NumberFormat().format(num);
        },

        /**
         * Format duration in seconds to human readable
         */
        formatDuration: function(seconds) {
            if (seconds < 60) return seconds + 's';
            var mins = Math.floor(seconds / 60);
            var secs = seconds % 60;
            return mins + 'm ' + secs + 's';
        },

        /**
         * Cleanup on page unload
         */
        destroy: function() {
            if (this.realtimeInterval) {
                clearInterval(this.realtimeInterval);
            }
            if (this.chart) {
                this.chart.destroy();
            }
        }
    };

    // Expose to global scope
    window.InsightsDashboard = InsightsDashboard;
})();
