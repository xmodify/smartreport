<?php

namespace App\Services\Bms;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class BmsMarketplaceService
{
    /**
     * Build HTTP pending request with SSL verify settings.
     */
    protected function client(): PendingRequest
    {
        $verifySsl = (bool) Config::get('bms.verify_ssl', false);

        return Http::withOptions(['verify' => $verifySsl]);
    }

    /**
     * Get Marketplace Base URL.
     */
    protected function getBaseUrl(): string
    {
        return rtrim(Config::get('bms.endpoints.marketplace', 'https://hosxp-marketplace.bmscloud.in.th'), '/');
    }

    /**
     * Get Addon API Key (hxp_...).
     *
     * @throws Exception
     */
    protected function getAddonKey(): string
    {
        $key = Config::get('bms.addon_key');
        if (empty($key)) {
            throw new Exception('BMS Addon Key is not configured. Please set BMS_ADDON_KEY in your .env');
        }

        return $key;
    }

    /**
     * Request temporary write access token (mkt_...).
     *
     * @return array{token: string, expires_in: int, scope: array<string>, hospital_code: string}
     *
     * @throws Exception
     */
    public function getAccessToken(string $bmsSessionId): array
    {
        $addonKey = $this->getAddonKey();
        $timeout = (int) Config::get('bms.timeouts.marketplace', 15);

        $response = $this->client()->withHeaders(['X-Addon-Key' => $addonKey])
            ->timeout($timeout)
            ->post("{$this->getBaseUrl()}/api/v1/addon/token", [
                'bms-session-id' => $bmsSessionId,
            ]);

        if (! $response->successful()) {
            throw new Exception('Unable to get Marketplace write token: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Report usage metrics & credit consumption.
     *
     * @param  array<string, mixed>  $extra
     *
     * @throws Exception
     */
    public function reportUsage(string $bmsSessionId, string $eventType, int $units = 1, array $extra = []): array
    {
        $addonKey = $this->getAddonKey();
        $timeout = (int) Config::get('bms.timeouts.marketplace', 15);

        $payload = array_merge([
            'bms-session-id' => $bmsSessionId,
            'event_type' => $eventType,
            'units' => $units,
            'timestamp' => now()->toIso8601String(),
        ], $extra);

        $response = $this->client()->withHeaders(['X-Addon-Key' => $addonKey])
            ->timeout($timeout)
            ->post("{$this->getBaseUrl()}/api/v1/addon/usage", $payload);

        if (! $response->successful()) {
            throw new Exception('Usage reporting failed: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Read User/Hospital Key-Value Storage.
     *
     * @throws Exception
     */
    public function getStorage(string $bmsSessionId, string $key): mixed
    {
        $addonKey = $this->getAddonKey();
        $timeout = (int) Config::get('bms.timeouts.marketplace', 15);

        $response = $this->client()->withHeaders([
            'X-BMS-Session-Id' => $bmsSessionId,
            'X-Marketplace-Token' => $addonKey,
        ])
            ->timeout($timeout)
            ->get("{$this->getBaseUrl()}/api/v1/addon/storage/{$key}");

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new Exception("Failed to read BMS KV Storage [{$key}]: ".$response->body());
        }

        $res = $response->json();

        return $res['value'] ?? null;
    }

    /**
     * Write User/Hospital Key-Value Storage.
     *
     * @throws Exception
     */
    public function setStorage(string $bmsSessionId, string $key, mixed $value, int $expectedVersion = 1): array
    {
        $addonKey = $this->getAddonKey();
        $timeout = (int) Config::get('bms.timeouts.marketplace', 15);

        $response = $this->client()->withHeaders(['X-BMS-Session-Id' => $bmsSessionId])
            ->timeout($timeout)
            ->put("{$this->getBaseUrl()}/api/v1/addon/storage/{$key}", [
                'marketplace_token' => $addonKey,
                'value' => $value,
                'expectedVersion' => $expectedVersion,
            ]);

        if (! $response->successful()) {
            throw new Exception("Failed to save to BMS KV Storage [{$key}]: ".$response->body());
        }

        return $response->json();
    }

    /**
     * Delete Key-Value Storage entry.
     *
     * @throws Exception
     */
    public function deleteStorage(string $bmsSessionId, string $key): bool
    {
        $addonKey = $this->getAddonKey();
        $timeout = (int) Config::get('bms.timeouts.marketplace', 15);

        $response = $this->client()->withHeaders([
            'X-BMS-Session-Id' => $bmsSessionId,
            'X-Marketplace-Token' => $addonKey,
        ])
            ->timeout($timeout)
            ->delete("{$this->getBaseUrl()}/api/v1/addon/storage/{$key}");

        return $response->successful();
    }
}
