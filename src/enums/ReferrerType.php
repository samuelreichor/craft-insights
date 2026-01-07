<?php

namespace samuelreichor\insights\enums;

/**
 * Referrer type enum
 */
enum ReferrerType: string
{
    case Direct = 'direct';
    case Search = 'search';
    case Social = 'social';
    case Referral = 'referral';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Direct',
            self::Search => 'Search Engine',
            self::Social => 'Social Media',
            self::Referral => 'Referral',
        };
    }

    /**
     * Classify a domain into a referrer type.
     */
    public static function fromDomain(string $domain): self
    {
        $domain = strtolower($domain);

        $searchEngines = ['google', 'bing', 'yahoo', 'duckduckgo', 'baidu', 'yandex', 'ecosia'];
        foreach ($searchEngines as $engine) {
            if (str_contains($domain, $engine)) {
                return self::Search;
            }
        }

        $socialNetworks = ['facebook', 'twitter', 'linkedin', 'instagram', 'pinterest', 'youtube', 'tiktok', 'reddit', 'x.com'];
        foreach ($socialNetworks as $network) {
            if (str_contains($domain, $network)) {
                return self::Social;
            }
        }

        return self::Referral;
    }
}
