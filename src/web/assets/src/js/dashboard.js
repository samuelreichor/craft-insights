/**
 * Insights Dashboard JavaScript
 *
 * Handles the Control Panel dashboard functionality including:
 * - Chart rendering
 * - Realtime updates
 * - Live data refresh
 * - Interactive elements
 */
(function() {
    'use strict';

    var InsightsDashboard = {
        chart: null,
        refreshInterval: null,
        siteId: null,
        range: null,
        refreshRate: 15000, // 15 seconds

        /**
         * Initialize the dashboard
         */
        init: function(config) {
            this.siteId = config.siteId;
            this.range = config.range;

            this.initChart(config.chartData);
            this.initLiveRefresh();
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
         * Initialize live refresh for all dashboard data
         */
        initLiveRefresh: function() {
            var self = this;

            // Update all data periodically
            this.refreshInterval = setInterval(function() {
                self.fetchDashboardData();
            }, this.refreshRate);
        },

        /**
         * Fetch all dashboard data from server
         */
        fetchDashboardData: function() {
            var self = this;

            fetch('/actions/insights/dashboard/dashboard-data?siteId=' + this.siteId + '&range=' + this.range, {
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
                self.updateRealtime(data.realtime);
                self.updateKpis(data.summary);
                self.updateChart(data.chartData);
                self.updateTopPages(data.topPages);
                self.updateTopReferrers(data.topReferrers);
                self.updateDevices(data.devices, data.browsers);
                self.updateTopCampaigns(data.topCampaigns);
                self.updateTopCountries(data.topCountries);
                self.updateTopEvents(data.topEvents);
                self.updateTopOutbound(data.topOutboundLinks);
                self.updateTopSearches(data.topSearches);
            })
            .catch(function(error) {
                console.warn('Failed to fetch dashboard data:', error);
            });
        },

        /**
         * Update realtime widget
         */
        updateRealtime: function(data) {
            if (!data) return;

            var realtimeEl = document.getElementById('realtime-count');
            var realtimePagesEl = document.getElementById('realtime-pages');

            if (realtimeEl) {
                realtimeEl.textContent = data.count;
            }

            if (realtimePagesEl && data.pages) {
                var self = this;
                var html = '';
                data.pages.slice(0, 6).forEach(function(page) {
                    html += '<li class="realtime-page">';
                    html += '<span class="count">' + page.count + '</span>';
                    html += '<span class="url">' + self.escapeHtml(page.url) + '</span>';
                    html += '</li>';
                });
                realtimePagesEl.innerHTML = html;
            }
        },

        /**
         * Update KPI cards
         */
        updateKpis: function(data) {
            if (!data) return;

            var kpisEl = document.getElementById('insights-kpis');
            if (!kpisEl) return;

            // Pageviews
            var pageviewsValue = kpisEl.querySelector('[data-kpi="pageviews"] .insights-kpi-value');
            var pageviewsTrend = kpisEl.querySelector('[data-kpi="pageviews"] .insights-kpi-trend');
            if (pageviewsValue) {
                pageviewsValue.textContent = this.formatNumber(data.pageviews);
            }
            if (pageviewsTrend) {
                var trendClass = data.pageviewsTrend >= 0 ? 'positive' : 'negative';
                var arrow = data.pageviewsTrend >= 0 ? '↑' : '↓';
                pageviewsTrend.className = 'insights-kpi-trend ' + trendClass;
                pageviewsTrend.innerHTML = '<span>' + arrow + '</span> ' + Math.abs(data.pageviewsTrend) + '% vs previous period';
            }

            // Unique Visitors
            var visitorsValue = kpisEl.querySelector('[data-kpi="visitors"] .insights-kpi-value');
            var visitorsTrend = kpisEl.querySelector('[data-kpi="visitors"] .insights-kpi-trend');
            if (visitorsValue) {
                visitorsValue.textContent = this.formatNumber(data.uniqueVisitors);
            }
            if (visitorsTrend) {
                var trendClass = data.visitorsTrend >= 0 ? 'positive' : 'negative';
                var arrow = data.visitorsTrend >= 0 ? '↑' : '↓';
                visitorsTrend.className = 'insights-kpi-trend ' + trendClass;
                visitorsTrend.innerHTML = '<span>' + arrow + '</span> ' + Math.abs(data.visitorsTrend) + '% vs previous period';
            }

            // Avg Time on Page
            var avgTimeValue = kpisEl.querySelector('[data-kpi="avgTime"] .insights-kpi-value');
            if (avgTimeValue) {
                var minutes = Math.floor(data.avgTimeOnPage / 60);
                var seconds = data.avgTimeOnPage % 60;
                avgTimeValue.textContent = minutes + 'm ' + seconds + 's';
            }

            // Bounce Rate
            var bounceValue = kpisEl.querySelector('[data-kpi="bounceRate"] .insights-kpi-value');
            if (bounceValue) {
                bounceValue.textContent = data.bounceRate + '%';
            }
        },

        /**
         * Update chart with new data
         */
        updateChart: function(data) {
            if (!data || !this.chart) return;

            this.chart.data.labels = data.labels;
            this.chart.data.datasets[0].data = data.pageviews;
            this.chart.data.datasets[1].data = data.visitors;
            this.chart.update('none'); // 'none' for no animation on update
        },

        /**
         * Update top pages table
         */
        updateTopPages: function(data) {
            if (!data) return;

            var tbody = document.querySelector('#table-top-pages tbody');
            if (!tbody) return;

            var html = '';
            var self = this;
            data.forEach(function(page) {
                html += '<tr>';
                html += '<td class="url" title="' + self.escapeHtml(page.url) + '">' + self.escapeHtml(page.url) + '</td>';
                html += '<td class="number">' + self.formatNumber(page.views) + '</td>';
                html += '<td class="number">' + self.formatNumber(page.uniqueVisitors) + '</td>';
                html += '</tr>';
            });
            tbody.innerHTML = html;
        },

        /**
         * Update top referrers table
         */
        updateTopReferrers: function(data) {
            if (!data) return;

            var tbody = document.querySelector('#table-top-referrers tbody');
            if (!tbody) return;

            var html = '';
            var self = this;
            data.forEach(function(ref) {
                var domain = ref.referrerDomain || 'Direct';
                html += '<tr>';
                html += '<td>' + self.escapeHtml(domain) + '</td>';
                html += '<td><span class="insights-badge ' + ref.referrerType + '">' + ref.referrerType + '</span></td>';
                html += '<td class="number">' + self.formatNumber(ref.visits) + '</td>';
                html += '</tr>';
            });
            tbody.innerHTML = html;
        },

        /**
         * Update devices & browsers tables
         */
        updateDevices: function(devices, browsers) {
            if (devices) {
                var devicesTbody = document.querySelector('#table-devices tbody');
                if (devicesTbody) {
                    var html = '';
                    var self = this;
                    devices.forEach(function(device) {
                        html += '<tr>';
                        html += '<td>' + self.capitalize(device.deviceType) + '</td>';
                        html += '<td class="number">' + self.formatNumber(device.visits) + '</td>';
                        html += '</tr>';
                    });
                    devicesTbody.innerHTML = html;
                }
            }

            if (browsers) {
                var browsersTbody = document.querySelector('#table-browsers tbody');
                if (browsersTbody) {
                    var html = '';
                    var self = this;
                    browsers.slice(0, 5).forEach(function(browser) {
                        html += '<tr>';
                        html += '<td>' + (browser.browserFamily || 'Unknown') + '</td>';
                        html += '<td class="number">' + self.formatNumber(browser.visits) + '</td>';
                        html += '</tr>';
                    });
                    browsersTbody.innerHTML = html;
                }
            }
        },

        /**
         * Update top campaigns table
         */
        updateTopCampaigns: function(data) {
            if (!data) return;

            var tbody = document.querySelector('#table-top-campaigns tbody');
            if (!tbody) return;

            var html = '';
            var self = this;
            data.forEach(function(campaign) {
                html += '<tr>';
                html += '<td>' + self.escapeHtml(campaign.utmSource || '-') + '</td>';
                html += '<td>' + self.escapeHtml(campaign.utmMedium || '-') + '</td>';
                html += '<td>' + self.escapeHtml(campaign.utmCampaign || '-') + '</td>';
                html += '<td class="number">' + self.formatNumber(campaign.visits) + '</td>';
                html += '</tr>';
            });
            tbody.innerHTML = html;
        },

        /**
         * Update top countries table
         */
        updateTopCountries: function(data) {
            if (!data) return;

            var tbody = document.querySelector('#table-top-countries tbody');
            if (!tbody) return;

            var html = '';
            var self = this;
            data.forEach(function(country) {
                html += '<tr>';
                html += '<td class="insights-country">';
                html += '<span class="insights-country-flag">' + country.flag + '</span>';
                html += '<span>' + self.escapeHtml(country.name) + '</span>';
                html += '</td>';
                html += '<td class="number">' + self.formatNumber(country.visits) + '</td>';
                html += '</tr>';
            });
            tbody.innerHTML = html;
        },

        /**
         * Update top events table
         */
        updateTopEvents: function(data) {
            if (!data) return;

            var tbody = document.querySelector('#table-top-events tbody');
            if (!tbody) return;

            var html = '';
            var self = this;
            data.forEach(function(event) {
                html += '<tr>';
                html += '<td>' + self.escapeHtml(event.eventName) + '</td>';
                html += '<td>';
                if (event.eventCategory) {
                    html += '<span class="insights-badge-category">' + self.escapeHtml(event.eventCategory) + '</span>';
                } else {
                    html += '<span class="insights-muted">-</span>';
                }
                html += '</td>';
                html += '<td class="number">' + self.formatNumber(event.count) + '</td>';
                html += '<td class="number">' + self.formatNumber(event.uniqueVisitors) + '</td>';
                html += '</tr>';
            });
            tbody.innerHTML = html;
        },

        /**
         * Update top outbound links table
         */
        updateTopOutbound: function(data) {
            if (!data) return;

            var tbody = document.querySelector('#table-top-outbound tbody');
            if (!tbody) return;

            var html = '';
            var self = this;
            data.forEach(function(link) {
                html += '<tr>';
                html += '<td>' + self.escapeHtml(link.targetDomain) + '</td>';
                html += '<td class="number">' + self.formatNumber(link.clicks) + '</td>';
                html += '<td class="number">' + self.formatNumber(link.uniqueVisitors) + '</td>';
                html += '</tr>';
            });
            tbody.innerHTML = html;
        },

        /**
         * Update top searches table
         */
        updateTopSearches: function(data) {
            if (!data) return;

            var tbody = document.querySelector('#table-top-searches tbody');
            if (!tbody) return;

            var html = '';
            var self = this;
            data.forEach(function(search) {
                html += '<tr>';
                html += '<td>' + self.escapeHtml(search.searchTerm) + '</td>';
                html += '<td class="number">' + self.formatNumber(search.searches) + '</td>';
                html += '<td class="number">' + self.formatNumber(search.uniqueVisitors) + '</td>';
                html += '</tr>';
            });
            tbody.innerHTML = html;
        },

        /**
         * Capitalize first letter
         */
        capitalize: function(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1);
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
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
            }
            if (this.chart) {
                this.chart.destroy();
            }
        }
    };

    // Expose to global scope
    window.InsightsDashboard = InsightsDashboard;
})();
