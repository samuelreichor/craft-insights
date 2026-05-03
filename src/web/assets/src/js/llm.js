/**
 * LLM Bot Analytics page
 *
 * Renders the crawl-activity chart (Chart.js) and wires the
 * Total / By Bot / By Type tab toggle + range select.
 */
(function() {
    'use strict';

    var palette = [
        '#3B82F6', '#10B981', '#F59E0B', '#8B5CF6',
        '#EC4899', '#06B6D4', '#F43F5E', '#84CC16',
        '#A855F7', '#14B8A6'
    ];

    // Stable, semantic colors per request type.
    var typeColors = {
        direct: '#3B82F6',
        negotiated: '#10B981'
    };

    window.InsightsLlm = {
        chart: null,
        siteId: null,
        range: null,
        groupBy: 'bot',
        typeLabels: {},

        init: function(config) {
            this.siteId = config.siteId;
            this.range = config.range;
            this.groupBy = config.groupBy || 'bot';
            this.typeLabels = config.typeLabels || {};

            this.bindControls();
            this.render(config.series);
        },

        bindControls: function() {
            var self = this;

            // Range changes are handled by the template via a full page
            // reload so KPIs (which are server-rendered) refresh too.
            document.querySelectorAll('[data-llm-tabs] button').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('[data-llm-tabs] button').forEach(function(b) {
                        b.classList.remove('is-active');
                    });
                    btn.classList.add('is-active');
                    self.groupBy = btn.dataset.group;
                    self.reload();
                });
            });
        },

        buildDatasets: function(series) {
            var self = this;
            var lastIndex = series.series.length - 1;

            return series.series.map(function(s, i) {
                var color = (self.groupBy === 'type' && typeColors[s.label])
                    ? typeColors[s.label]
                    : palette[i % palette.length];
                var label = (self.groupBy === 'type' && self.typeLabels[s.label])
                    ? self.typeLabels[s.label]
                    : s.label;
                var isTop = i === lastIndex;

                return {
                    label: label,
                    data: s.data,
                    backgroundColor: color,
                    borderColor: color,
                    borderWidth: 0,
                    // Only the topmost stack segment gets rounded top corners
                    // so middle and bottom segments butt up flush.
                    borderRadius: isTop
                        ? { topLeft: 4, topRight: 4, bottomLeft: 0, bottomRight: 0 }
                        : 0,
                    borderSkipped: false,
                    stack: 'requests',
                    maxBarThickness: 48
                };
            });
        },

        render: function(series) {
            var canvas = document.getElementById('llm-chart');
            if (!canvas || typeof Chart === 'undefined') return;

            if (this.chart) {
                this.chart.data.labels = series.labels;
                this.chart.data.datasets = this.buildDatasets(series);
                this.chart.update('none');
                return;
            }

            this.chart = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: series.labels,
                    datasets: this.buildDatasets(series)
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, padding: 20 }
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
                            stacked: true,
                            grid: { display: false }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        }
                    }
                }
            });
        },

        reload: function() {
            var self = this;
            var url = Craft.getActionUrl('insights/dashboard/llm-series', {
                siteId: this.siteId,
                range: this.range,
                groupBy: this.groupBy
            });

            fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function(r) { return r.json(); })
                .then(function(data) { self.render(data); })
                .catch(function(err) { console.warn('LLM series fetch failed', err); });
        },

        toRgba: function(hex, alpha) {
            var n = parseInt(hex.replace('#', ''), 16);
            return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + alpha + ')';
        }
    };
})();
