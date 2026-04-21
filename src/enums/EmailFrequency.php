<?php

namespace samuelreichor\insights\enums;

/**
 * Email frequency enum
 *
 * Defines how often weekly update emails are sent to recipients.
 */
enum EmailFrequency: string
{
    case Never = 'never';
    case Weekly = 'weekly';
    case BiWeekly = 'biweekly';
    case Monthly = 'monthly';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Never => 'Never',
            self::Weekly => 'Weekly',
            self::BiWeekly => 'Every two weeks',
            self::Monthly => 'Monthly',
        };
    }

    /**
     * Get all email frequencies as options for select fields.
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

    /**
     * Number of days between sends for this frequency.
     *
     * Used both for the due-check (gate) and the report period (date range).
     */
    public function intervalDays(): int
    {
        return match ($this) {
            self::Never => 0,
            self::Weekly => 7,
            self::BiWeekly => 14,
            self::Monthly => 28,
        };
    }

    /**
     * Closest matching DateRange enum value for fetching stats.
     *
     * StatsService accepts fixed ranges (7d, 30d). BiWeekly approximates to 7d
     * (same weekly snapshot, delivered less often). Monthly rounds to 30d.
     */
    public function statsRange(): string
    {
        return match ($this) {
            self::Weekly, self::BiWeekly => '7d',
            self::Monthly => '30d',
            self::Never => '7d',
        };
    }
}
