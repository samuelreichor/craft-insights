<?php

namespace samuelreichor\insights\services;

use Craft;
use craft\base\Component;
use craft\web\View;
use Dompdf\Dompdf;
use Dompdf\Options;
use samuelreichor\insights\enums\DateRange;
use samuelreichor\insights\Insights;
use samuelreichor\insights\variables\InsightsVariable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\web\Response;

/**
 * PDF Service
 *
 * Renders Twig templates to PDF using dompdf.
 *
 * @author Samuel Reichör <samuelreichor@gmail.com>
 * @since 1.3.0
 */
class PdfService extends Component
{
    /**
     * Render a CP Twig template to a PDF download response.
     *
     * @param string $template Template path under the plugin templates directory.
     * @param array<string, mixed> $variables Twig variables.
     * @param string $filename Filename without extension.
     */
    /**
     * Render a simple inline-SVG line chart with two series (pageviews + visitors).
     *
     * @param array{labels: string[], pageviews: int[], visitors: int[]} $chartData
     */
    public function renderLineChartSvg(array $chartData, int $width = 720, int $height = 200): string
    {
        $labels = $chartData['labels'];
        $pageviews = $chartData['pageviews'];
        $visitors = $chartData['visitors'];

        $count = count($labels);
        if ($count < 2) {
            return '';
        }

        $padTop = 12;
        $padBottom = 26;
        $padLeft = 36;
        $padRight = 12;

        $plotW = $width - $padLeft - $padRight;
        $plotH = $height - $padTop - $padBottom;

        $max = max(max($pageviews ?: [0]), max($visitors ?: [0]), 1);
        // Round up to a "nice" tick value so the gridlines look clean.
        $magnitude = 10 ** max(0, (int)floor(log10($max)));
        $max = (int)(ceil($max / $magnitude) * $magnitude);

        $xStep = $plotW / ($count - 1);

        $pointPath = static function(array $values) use ($padLeft, $padTop, $plotH, $xStep, $max): string {
            $cmd = 'M';
            $segments = [];
            foreach ($values as $i => $v) {
                $x = $padLeft + ($i * $xStep);
                $y = $padTop + $plotH - (($v / $max) * $plotH);
                $segments[] = $cmd . ' ' . round($x, 2) . ' ' . round($y, 2);
                $cmd = 'L';
            }
            return implode(' ', $segments);
        };

        $pageviewsPath = $pointPath($pageviews);
        $visitorsPath = $pointPath($visitors);

        // Y-axis grid (4 lines) + labels
        $gridSvg = '';
        $yLabels = '';
        for ($i = 0; $i <= 4; $i++) {
            $y = $padTop + ($plotH * $i / 4);
            $value = (int)round($max - ($max * $i / 4));
            $gridSvg .= sprintf(
                '<line x1="%d" y1="%.2f" x2="%d" y2="%.2f" stroke="#F3F4F6" stroke-width="1" />',
                $padLeft,
                $y,
                $padLeft + $plotW,
                $y
            );
            $yLabels .= sprintf(
                '<text x="%d" y="%.2f" font-size="8" fill="#9CA3AF" text-anchor="end" font-family="DejaVu Sans">%s</text>',
                $padLeft - 4,
                $y + 3,
                number_format($value)
            );
        }

        // X-axis labels (max 6 evenly spaced)
        $xLabels = '';
        $stepLabel = max(1, (int)ceil($count / 6));
        for ($i = 0; $i < $count; $i += $stepLabel) {
            $x = $padLeft + ($i * $xStep);
            $xLabels .= sprintf(
                '<text x="%.2f" y="%d" font-size="8" fill="#9CA3AF" text-anchor="middle" font-family="DejaVu Sans">%s</text>',
                $x,
                $height - 10,
                htmlspecialchars((string)$labels[$i], ENT_QUOTES, 'UTF-8')
            );
        }

        // dompdf does not render inline <svg> elements reliably; embed as a
        // base64 data URI image instead — this routes the SVG through dompdf's
        // image pipeline (php-svg-lib), which handles paths, text, and lines.
        $svg = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '" '
            . 'width="' . $width . '" height="' . $height . '">'
            . $gridSvg
            . '<path d="' . $visitorsPath . '" stroke="#10B981" stroke-width="1.5" fill="none" />'
            . '<path d="' . $pageviewsPath . '" stroke="#3B82F6" stroke-width="1.8" fill="none" />'
            . $yLabels
            . $xLabels
            . '</svg>';

        return '<img src="data:image/svg+xml;base64,' . base64_encode($svg)
            . '" style="display:block;width:100%;height:auto;" alt="" />';
    }

    /**
     * Render a CP Twig template through dompdf and return the raw PDF bytes.
     *
     * @param array<string, mixed> $variables
     * @throws SyntaxError
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function generate(string $template, array $variables): string
    {
        $view = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        try {
            $html = $view->renderTemplate($template, $variables);
        } finally {
            $view->setTemplateMode($oldMode);
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string)$dompdf->output();
    }

    /**
     * Render a CP Twig template to a PDF download response.
     *
     * @param array<string, mixed> $variables
     * @throws SyntaxError
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function render(string $template, array $variables, string $filename): Response
    {
        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->setDownloadHeaders($filename . '.pdf', 'application/pdf');
        $response->data = $this->generate($template, $variables);

        return $response;
    }

    /**
     * Build the variables for the multi-section dashboard PDF
     * (`insights/_pdf/dashboard.twig`).
     *
     * Used by the Export controller for manual downloads, and by the
     * NotificationService when attaching the report to scheduled emails.
     *
     * @return array<string, mixed>
     */
    public function buildDashboardData(int $siteId, string $range): array
    {
        $plugin = Insights::getInstance();
        $stats = $plugin->stats;
        $isPro = $plugin->isPro();
        $chartData = $stats->getChartData($siteId, $range);

        return [
            'title' => Craft::t('insights', 'Analytics Report'),
            'siteName' => $this->getSiteName($siteId),
            'rangeLabel' => $this->getRangeLabel($range),
            'rangePeriod' => $this->getRangePeriod($range),
            'exportedAt' => date('Y-m-d H:i'),
            'isPro' => $isPro,
            'summary' => $stats->getSummary($siteId, $range),
            'chartSvg' => $this->renderLineChartSvg($chartData),
            'topPages' => $stats->getTopPages($siteId, $range, 20),
            'topReferrers' => $stats->getTopReferrers($siteId, $range, 20),
            'devices' => $stats->getDeviceBreakdown($siteId, $range),
            'browsers' => $stats->getBrowserBreakdown($siteId, $range),
            'topCountries' => $isPro ? $this->enrichCountries($stats->getTopCountries($siteId, $range, 20)) : [],
            'topCampaigns' => $isPro ? $stats->getTopCampaigns($siteId, $range, 20) : [],
            'topEvents' => $isPro ? $stats->getTopEvents($siteId, $range, 20) : [],
            'topOutboundLinks' => $isPro ? $stats->getTopOutboundLinks($siteId, $range, 20) : [],
            'topSearches' => $isPro ? $stats->getTopSearches($siteId, $range, 20) : [],
            'topEntryPages' => $isPro ? $stats->getTopEntryPages($siteId, $range, 20) : [],
            'topExitPages' => $isPro ? $stats->getTopExitPages($siteId, $range, 20) : [],
            'scrollDepth' => $isPro ? $stats->getScrollDepth($siteId, $range, 20) : [],
        ];
    }

    /**
     * Get site name for PDF header.
     */
    public function getSiteName(int $siteId): string
    {
        $site = Craft::$app->getSites()->getSiteById($siteId);
        return $site?->name ?? '';
    }

    /**
     * Human-readable label for a date range value (e.g. "Last 7 Days").
     */
    public function getRangeLabel(string $range): string
    {
        return DateRange::tryFrom($range)?->label() ?? $range;
    }

    /**
     * Absolute "from – to" span for the given range (e.g. "Apr 25 – May 1, 2026").
     */
    public function getRangePeriod(string $range): string
    {
        $enum = DateRange::tryFrom($range);
        if ($enum === null) {
            return '';
        }

        $start = strtotime($enum->getStartDate());
        $end = strtotime($enum->getEndDate());

        if ($start === false || $end === false) {
            return '';
        }

        if ($start === $end) {
            return date('M j, Y', $start);
        }

        $sameYear = date('Y', $start) === date('Y', $end);
        $startFormat = $sameYear ? 'M j' : 'M j, Y';

        return date($startFormat, $start) . ' – ' . date('M j, Y', $end);
    }

    /**
     * Replace country codes with the human-readable country name. Flag emojis
     * are intentionally omitted because dompdf's default font does not render
     * regional indicator symbols.
     *
     * @param array<int, array<string, mixed>> $countries
     * @return array<int, array<string, mixed>>
     */
    public function enrichCountries(array $countries): array
    {
        $variable = new InsightsVariable();
        return array_map(static function(array $country) use ($variable): array {
            $code = (string)($country['countryCode'] ?? '');
            $country['countryCode'] = $variable->getCountryName($code) ?: $code;
            return $country;
        }, $countries);
    }
}
