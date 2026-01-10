<?php

namespace samuelreichor\insights\enums;

/**
 * Scroll depth milestone enum
 *
 * Represents the scroll depth milestones that are tracked (25%, 50%, 75%, 100%).
 */
enum ScrollDepthMilestone: int
{
    case Percent25 = 25;
    case Percent50 = 50;
    case Percent75 = 75;
    case Percent100 = 100;

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Percent25 => '25%',
            self::Percent50 => '50%',
            self::Percent75 => '75%',
            self::Percent100 => '100%',
        };
    }

    /**
     * Get the database column name for this milestone.
     */
    public function column(): string
    {
        return match ($this) {
            self::Percent25 => 'milestone25',
            self::Percent50 => 'milestone50',
            self::Percent75 => 'milestone75',
            self::Percent100 => 'milestone100',
        };
    }

    /**
     * Create from percentage value.
     */
    public static function fromPercent(int $percent): ?self
    {
        return match (true) {
            $percent >= 100 => self::Percent100,
            $percent >= 75 => self::Percent75,
            $percent >= 50 => self::Percent50,
            $percent >= 25 => self::Percent25,
            default => null,
        };
    }
}
