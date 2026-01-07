<?php

namespace samuelreichor\insights\services;

use Craft;
use craft\base\Component;
use samuelreichor\insights\enums\LogLevel;
use samuelreichor\insights\Insights;

/**
 * Logger Service
 *
 * Provides structured logging with performance timing for debugging.
 * - Default mode: Only errors and warnings
 * - Debug mode: All steps with performance timings
 */
class LoggerService extends Component
{
    private const CATEGORY = 'insights';

    /**
     * @var array<string, float> Active timers
     */
    private array $timers = [];

    /**
     * Log a debug message (only in debug mode).
     *
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     */
    public function debug(string $message, array $context = []): void
    {
        if (!$this->isDebugEnabled()) {
            return;
        }

        $formattedMessage = $this->formatMessage($message, $context);
        Craft::info($formattedMessage, self::CATEGORY);
    }

    /**
     * Log an info message (only in debug mode).
     *
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     */
    public function info(string $message, array $context = []): void
    {
        if (!$this->isDebugEnabled()) {
            return;
        }

        $formattedMessage = $this->formatMessage($message, $context);
        Craft::info($formattedMessage, self::CATEGORY);
    }

    /**
     * Log a warning message (always logged).
     *
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     */
    public function warning(string $message, array $context = []): void
    {
        $formattedMessage = $this->formatMessage($message, $context);
        Craft::warning($formattedMessage, self::CATEGORY);
    }

    /**
     * Log an error message (always logged).
     *
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     */
    public function error(string $message, array $context = []): void
    {
        $formattedMessage = $this->formatMessage($message, $context);
        Craft::error($formattedMessage, self::CATEGORY);
    }

    /**
     * Start a performance timer.
     *
     * @param string $name Timer name (e.g., 'processPageview', 'geoipLookup')
     */
    public function startTimer(string $name): void
    {
        if (!$this->isDebugEnabled()) {
            return;
        }

        $this->timers[$name] = microtime(true);
        $this->debug("Starting: {$name}");
    }

    /**
     * Stop a performance timer and log the duration.
     *
     * @param string $name Timer name
     * @param array<string, mixed> $context Additional context data
     * @return float|null Duration in milliseconds, or null if timer not found
     */
    public function stopTimer(string $name, array $context = []): ?float
    {
        if (!$this->isDebugEnabled()) {
            return null;
        }

        if (!isset($this->timers[$name])) {
            $this->warning("Timer '{$name}' was not started");
            return null;
        }

        $duration = (microtime(true) - $this->timers[$name]) * 1000;
        unset($this->timers[$name]);

        $context['duration_ms'] = round($duration, 2);
        $this->debug("Completed: {$name}", $context);

        return $duration;
    }

    /**
     * Log a step in a process (only in debug mode).
     *
     * @param string $process The process name (e.g., 'Tracking', 'GeoIP')
     * @param string $step The step description
     * @param array<string, mixed> $context Additional context data
     */
    public function step(string $process, string $step, array $context = []): void
    {
        if (!$this->isDebugEnabled()) {
            return;
        }

        $this->debug("[{$process}] {$step}", $context);
    }

    /**
     * Log the start of a feature/process (only in debug mode).
     *
     * @param string $feature Feature name
     * @param array<string, mixed> $context Additional context data
     */
    public function beginFeature(string $feature, array $context = []): void
    {
        $this->startTimer($feature);
        $this->step($feature, 'Started', $context);
    }

    /**
     * Log the end of a feature/process (only in debug mode).
     *
     * @param string $feature Feature name
     * @param array<string, mixed> $context Additional context data
     */
    public function endFeature(string $feature, array $context = []): void
    {
        $duration = $this->stopTimer($feature, $context);
        if ($duration !== null) {
            $context['total_duration_ms'] = round($duration, 2);
            $this->step($feature, 'Completed', $context);
        }
    }

    /**
     * Check if debug logging is enabled.
     */
    public function isDebugEnabled(): bool
    {
        return Insights::getInstance()->getSettings()->getLogLevelEnum() === LogLevel::Debug;
    }

    /**
     * Format a log message with context.
     *
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     */
    private function formatMessage(string $message, array $context = []): string
    {
        if (empty($context)) {
            return $message;
        }

        $contextStr = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return "{$message} | Context: {$contextStr}";
    }
}
