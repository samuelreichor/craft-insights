<?php

namespace samuelreichor\insights\enums;

/**
 * Log level enum
 *
 * Defines the available logging levels for the Insights plugin.
 */
enum LogLevel: string
{
    /**
     * Default level: Only errors and warnings are logged.
     */
    case Default = 'default';

    /**
     * Debug level: All steps to follow happy paths, including performance timings.
     */
    case Debug = 'debug';

    /**
     * Check if debug logging is enabled.
     */
    public function isDebug(): bool
    {
        return $this === self::Debug;
    }

    /**
     * Get human-readable label for the log level.
     */
    public function label(): string
    {
        return match ($this) {
            self::Default => 'Default (Errors & Warnings only)',
            self::Debug => 'Debug (All steps with performance timings)',
        };
    }

    /**
     * Get all log levels as options for select fields.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }
}
