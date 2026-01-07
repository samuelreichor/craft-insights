<?php

namespace samuelreichor\insights\enums;

/**
 * Tracking event type enum
 */
enum EventType: string
{
    case Pageview = 'pv';
    case Engagement = 'en';
    case Leave = 'lv';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pageview => 'Pageview',
            self::Engagement => 'Engagement',
            self::Leave => 'Leave',
        };
    }
}
