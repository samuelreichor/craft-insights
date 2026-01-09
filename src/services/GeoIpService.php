<?php

namespace samuelreichor\insights\services;

use Craft;
use craft\base\Component;
use GeoIp2\Database\Reader;
use samuelreichor\insights\Insights;

/**
 * GeoIP Service
 *
 * Extracts country information from IP addresses.
 * IMPORTANT: The IP address is NEVER stored - only the country code is retained.
 */
class GeoIpService extends Component
{
    private ?Reader $reader = null;

    /**
     * Get country code from IP address.
     *
     * DSGVO-compliant: IP is used only for lookup, never stored.
     * Only the ISO country code (e.g., "DE") is returned and stored.
     *
     * @param string $ip The IP address to look up
     * @return string|null Two-letter ISO country code or null if not found
     */
    public function getCountry(string $ip): ?string
    {
        // Note: Data is collected for all users (Lite + Pro) so they have
        // historical data when upgrading. Display is restricted to Pro only.
        $logger = Insights::getInstance()->logger;

        // Private/local IPs cannot be geolocated
        if ($this->isPrivateIp($ip)) {
            $logger->debug('GeoIP: Skipping private IP', ['ip' => $ip]);
            return null;
        }

        try {
            $reader = $this->getReader();
            if ($reader === null) {
                return null;
            }

            $record = $reader->country($ip);
            $countryCode = $record->country->isoCode;
            $logger->debug('GeoIP: Country resolved', ['country' => $countryCode]);
            return $countryCode; // e.g., "DE", "US", "AT"
        } catch (\Throwable $e) {
            // IP not found in database or invalid - this is normal
            $logger->debug('GeoIP: Lookup failed', ['error' => $e->getMessage()]);
            return null;
        }

        // IMPORTANT: IP is NOT passed on or stored anywhere!
    }

    /**
     * Check if IP is a private/local address.
     */
    private function isPrivateIp(string $ip): bool
    {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Get the GeoIP database reader.
     */
    private function getReader(): ?Reader
    {
        if ($this->reader !== null) {
            return $this->reader;
        }

        $dbPath = $this->getDatabasePath();

        if ($dbPath === null || !file_exists($dbPath)) {
            Craft::warning('GeoIP database not found at: ' . ($dbPath ?? 'null'), 'insights');
            return null;
        }

        try {
            $this->reader = new Reader($dbPath);
            return $this->reader;
        } catch (\Throwable $e) {
            Craft::error('Failed to open GeoIP database: ' . $e->getMessage(), 'insights');
            return null;
        }
    }

    /**
     * Get the path to the GeoIP database.
     */
    public function getDatabasePath(): ?string
    {
        $settings = Insights::getInstance()->getSettings();
        $path = Craft::getAlias($settings->geoIpDatabasePath);

        if ($path === false) {
            return null;
        }

        return $path;
    }

    /**
     * Check if the GeoIP database exists and is valid.
     */
    public function isDatabaseAvailable(): bool
    {
        $path = $this->getDatabasePath();
        return $path !== null && file_exists($path);
    }

    /**
     * Get database info for the settings page.
     *
     * @return array{exists: bool, path: string|null, size: int|null, modified: string|null}
     */
    public function getDatabaseInfo(): array
    {
        $path = $this->getDatabasePath();

        if ($path === null || !file_exists($path)) {
            return [
                'exists' => false,
                'path' => $path,
                'size' => null,
                'modified' => null,
            ];
        }

        return [
            'exists' => true,
            'path' => $path,
            'size' => filesize($path),
            'modified' => date('Y-m-d H:i:s', filemtime($path) ?: 0),
        ];
    }
}
