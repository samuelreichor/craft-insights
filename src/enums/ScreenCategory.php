<?php

namespace samuelreichor\insights\enums;

/**
 * Screen category enum for visitor identification
 */
enum ScreenCategory: string
{
    case Small = 's';
    case Medium = 'm';
    case Large = 'l';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Small => 'Small (Mobile)',
            self::Medium => 'Medium (Tablet)',
            self::Large => 'Large (Desktop)',
        };
    }

    /**
     * Create from screen width in pixels.
     */
    public static function fromWidth(int $width): self
    {
        if ($width < 768) {
            return self::Small;
        }
        if ($width < 1200) {
            return self::Medium;
        }
        return self::Large;
    }
}
