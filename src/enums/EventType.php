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
    case Event = 'ev';
    case Outbound = 'ob';
    case Search = 'sr';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pageview => 'Pageview',
            self::Engagement => 'Engagement',
            self::Leave => 'Leave',
            self::Event => 'Custom Event',
            self::Outbound => 'Outbound Link',
            self::Search => 'Site Search',
        };
    }
}
