<?php

namespace samuelreichor\insights\enums;

/**
 * Device type enum
 */
enum DeviceType: string
{
    case Desktop = 'desktop';
    case Mobile = 'mobile';
    case Tablet = 'tablet';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Desktop => 'Desktop',
            self::Mobile => 'Mobile',
            self::Tablet => 'Tablet',
        };
    }

    /**
     * Create from WhichBrowser device type string.
     */
    public static function fromBrowserType(?string $type): self
    {
        return match ($type) {
            'mobile' => self::Mobile,
            'tablet' => self::Tablet,
            default => self::Desktop,
        };
    }
}
