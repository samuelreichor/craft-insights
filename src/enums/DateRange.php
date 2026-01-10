<?php

namespace samuelreichor\insights\enums;

/**
 * Date range enum for dashboard filtering
 */
enum DateRange: string
{
    case Today = 'today';
    case Last7Days = '7d';
    case Last30Days = '30d';
    case Last90Days = '90d';
    case Last12Months = '12m';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Today => 'Today',
            self::Last7Days => 'Last 7 Days',
            self::Last30Days => 'Last 30 Days',
            self::Last90Days => 'Last 90 Days',
            self::Last12Months => 'Last 12 Months',
        };
    }

    /**
     * Get the start date for this range.
     */
    public function getStartDate(): string
    {
        return match ($this) {
            self::Today => date('Y-m-d'),
            self::Last7Days => date('Y-m-d', strtotime('-6 days')),
            self::Last30Days => date('Y-m-d', strtotime('-29 days')),
            self::Last90Days => date('Y-m-d', strtotime('-89 days')),
            self::Last12Months => date('Y-m-d', strtotime('-365 days')),
        };
    }

    /**
     * Get the end date (always today).
     */
    public function getEndDate(): string
    {
        return date('Y-m-d');
    }

    /**
     * Get start and end date as array.
     *
     * @return array{0: string, 1: string}
     */
    public function getDateRange(): array
    {
        return [$this->getStartDate(), $this->getEndDate()];
    }
}
