<?php

namespace App\Services\Bms;

use App\Models\BmsSession;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BmsDataService
{
    /**
     * Resolve bms_url and bms_session_code from BmsSession model or array.
     *
     * @param  BmsSession|array<string, mixed>  $session
     * @return array{bms_url: string, bms_session_code: string}
     *
     * @throws Exception
     */
    protected function extractCredentials(BmsSession|array $session): array
    {
        if ($session instanceof BmsSession) {
            if ($session->isExpired()) {
                throw new Exception("BMS Session has expired at {$session->expires_at}");
            }

            return [
                'bms_url' => rtrim($session->bms_url, '/'),
                'bms_direct_url' => ! empty($session->bms_direct_url) ? rtrim($session->bms_direct_url, '/') : null,
                'bms_session_code' => $session->bms_session_code,
            ];
        }

        if (empty($session['bms_url']) || empty($session['bms_session_code'])) {
            throw new Exception('Invalid session credentials: bms_url or bms_session_code is missing.');
        }

        return [
            'bms_url' => rtrim($session['bms_url'], '/'),
            'bms_direct_url' => ! empty($session['bms_direct_url']) ? rtrim($session['bms_direct_url'], '/') : null,
            'bms_session_code' => $session['bms_session_code'],
        ];
    }

    /**
     * Execute SQL Query via POST /api/sql (Read-Only)
     *
     * @param  BmsSession|array<string, mixed>  $session
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    /**
     * Format parameter array to conform to official BMS Session API value_type specification.
     * Supported types: string, text, integer, float, date, time, datetime.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, array{value: mixed, value_type: string}>
     */
    public function formatParams(array $params): array
    {
        $formatted = [];
        foreach ($params as $key => $val) {
            if (is_array($val) && isset($val['value_type'])) {
                $formatted[$key] = $val;

                continue;
            }

            $type = 'string';
            if (is_int($val)) {
                $type = 'integer';
            } elseif (is_float($val)) {
                $type = 'float';
            } elseif (is_string($val)) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                    $type = 'date';
                } elseif (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $val)) {
                    $type = 'time';
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/', $val)) {
                    $type = 'datetime';
                } elseif (mb_strlen($val) > 255) {
                    $type = 'text';
                }
            }

            $formatted[$key] = [
                'value' => $val,
                'value_type' => $type,
            ];
        }

        return $formatted;
    }

    /**
     * Execute SQL Query via POST /api/sql (Read-Only)
     *
     * @param  BmsSession|array<string, mixed>  $session
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function executeSql(
        BmsSession|array $session,
        string $sql,
        array $params = [],
        int $limit = 100,
        ?string $marketplaceToken = null
    ): array {
        $creds = $this->extractCredentials($session);
        $timeout = (int) Config::get('bms.timeouts.sql', 60);
        $appName = Config::get('bms.app_name', 'smartdata');
        $verifySsl = (bool) Config::get('bms.verify_ssl', false);

        // BMS HostLite SQL Validator rejects inline LIMIT, backticks, and semicolons in the SQL string
        $cleanSql = str_replace('`', '', trim($sql));
        $cleanSql = rtrim($cleanSql, ';');
        if (preg_match('/\s+LIMIT\s+(\d+)\s*$/i', $cleanSql, $matches)) {
            $limit = (int) $matches[1];
            $cleanSql = preg_replace('/\s+LIMIT\s+\d+\s*$/i', '', $cleanSql);
        }

        $payload = [
            'app' => $appName,
            'sql' => $cleanSql,
        ];

        if (! empty($params)) {
            $payload['params'] = $this->formatParams($params);
        }

        if ($limit > 0) {
            $payload['limit'] = $limit;
        }

        if (! empty($marketplaceToken)) {
            $payload['marketplace-token'] = $marketplaceToken;
        }

        $response = null;
        try {
            $response = Http::withOptions(['verify' => $verifySsl])
                ->withToken($creds['bms_session_code'])
                ->timeout($timeout)
                ->post("{$creds['bms_url']}/api/sql", $payload);
        } catch (\Throwable $e) {
            // Fallback: If direct local network URL is provided, try direct URL (aopod style)
            if (! empty($creds['bms_direct_url'])) {
                try {
                    $directUrl = rtrim($creds['bms_direct_url'], '/');
                    $directResponse = Http::withOptions(['verify' => $verifySsl])
                        ->withToken($creds['bms_session_code'])
                        ->timeout(min(10, $timeout))
                        ->post("{$directUrl}/api/sql", $payload);

                    if ($directResponse->successful()) {
                        $response = $directResponse;
                    }
                } catch (\Throwable $ex) {
                    throw $e;
                }
            }

            if (! $response) {
                throw $e;
            }
        }

        // If primary tunnel returned non-successful HTTP, also attempt direct fallback
        if ((! $response || ! $response->successful()) && ! empty($creds['bms_direct_url'])) {
            try {
                $directUrl = rtrim($creds['bms_direct_url'], '/');
                $directResponse = Http::withOptions(['verify' => $verifySsl])
                    ->withToken($creds['bms_session_code'])
                    ->timeout(min(10, $timeout))
                    ->post("{$directUrl}/api/sql", $payload);

                if ($directResponse->successful()) {
                    $response = $directResponse;
                }
            } catch (\Throwable $ex) {
                // Keep original response
            }
        }

        if (! $response || ! $response->successful()) {
            $status = $response ? $response->status() : 500;
            Log::error('BMS SQL Execution Error', [
                'status' => $status,
                'body' => $response ? $response->body() : 'No response',
            ]);
            throw new Exception("BMS SQL Query failed: HTTP {$status}");
        }

        $res = $response->json();

        // Check BMS MessageCode
        if (isset($res['MessageCode']) && $res['MessageCode'] !== 200) {
            $msg = $res['Message'] ?? 'Unknown SQL error';
            throw new Exception("BMS SQL Error [{$res['MessageCode']}]: {$msg}");
        }

        return ! empty($res['data']) ? $res['data'] : ($res['result'] ?? []);
    }

    /**
     * Query table via GET /api/rest/{table}
     *
     * @param  BmsSession|array<string, mixed>  $session
     * @param  array<string, mixed>  $queryParams
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function restGet(BmsSession|array $session, string $table, array $queryParams = []): array
    {
        $creds = $this->extractCredentials($session);
        $timeout = (int) Config::get('bms.timeouts.rest', 60);
        $verifySsl = (bool) Config::get('bms.verify_ssl', false);

        $response = Http::withOptions(['verify' => $verifySsl])
            ->withToken($creds['bms_session_code'])
            ->timeout($timeout)
            ->get("{$creds['bms_url']}/api/rest/{$table}", $queryParams);

        if (! $response->successful()) {
            throw new Exception("BMS REST GET failed for {$table}: HTTP {$response->status()}");
        }

        $res = $response->json();
        if (isset($res['MessageCode']) && $res['MessageCode'] !== 200) {
            $msg = $res['Message'] ?? 'Unknown REST error';
            throw new Exception("BMS REST GET Error [{$res['MessageCode']}]: {$msg}");
        }

        return ! empty($res['data']) ? $res['data'] : ($res['result'] ?? []);
    }

    /**
     * Insert table record via POST /api/rest/{table}
     *
     * @param  BmsSession|array<string, mixed>  $session
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function restPost(BmsSession|array $session, string $table, array $data, ?string $mktToken = null): array
    {
        $creds = $this->extractCredentials($session);
        $timeout = (int) Config::get('bms.timeouts.rest', 60);
        $verifySsl = (bool) Config::get('bms.verify_ssl', false);

        $client = Http::withOptions(['verify' => $verifySsl])
            ->withToken($creds['bms_session_code'])
            ->timeout($timeout);

        if (! empty($mktToken)) {
            $client = $client->withHeaders(['X-Marketplace-Token' => $mktToken]);
        }

        $response = $client->post("{$creds['bms_url']}/api/rest/{$table}", $data);

        if (! $response->successful()) {
            throw new Exception("BMS REST POST failed for {$table}: HTTP {$response->status()} - {$response->body()}");
        }

        $res = $response->json();
        if (isset($res['MessageCode']) && $res['MessageCode'] !== 200) {
            $msg = $res['Message'] ?? 'Unknown error';
            throw new Exception("BMS REST POST Error [{$res['MessageCode']}]: {$msg}");
        }

        return $res;
    }

    /**
     * Update table record via PUT /api/rest/{table}/{id}
     *
     * @param  BmsSession|array<string, mixed>  $session
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function restPut(BmsSession|array $session, string $table, string|int $id, array $data, ?string $mktToken = null): array
    {
        $creds = $this->extractCredentials($session);
        $timeout = (int) Config::get('bms.timeouts.rest', 60);
        $verifySsl = (bool) Config::get('bms.verify_ssl', false);

        $client = Http::withOptions(['verify' => $verifySsl])
            ->withToken($creds['bms_session_code'])
            ->timeout($timeout);

        if (! empty($mktToken)) {
            $client = $client->withHeaders(['X-Marketplace-Token' => $mktToken]);
        }

        $response = $client->put("{$creds['bms_url']}/api/rest/{$table}/{$id}", $data);

        if (! $response->successful()) {
            throw new Exception("BMS REST PUT failed for {$table}/{$id}: HTTP {$response->status()} - {$response->body()}");
        }

        $res = $response->json();
        if (isset($res['MessageCode']) && $res['MessageCode'] !== 200) {
            $msg = $res['Message'] ?? 'Unknown error';
            throw new Exception("BMS REST PUT Error [{$res['MessageCode']}]: {$msg}");
        }

        return $res;
    }

    /**
     * Delete table record via DELETE /api/rest/{table}/{id}
     *
     * @param  BmsSession|array<string, mixed>  $session
     *
     * @throws Exception
     */
    public function restDelete(BmsSession|array $session, string $table, string|int $id, ?string $mktToken = null): bool
    {
        $creds = $this->extractCredentials($session);
        $timeout = (int) Config::get('bms.timeouts.rest', 60);
        $verifySsl = (bool) Config::get('bms.verify_ssl', false);

        $client = Http::withOptions(['verify' => $verifySsl])
            ->withToken($creds['bms_session_code'])
            ->timeout($timeout);

        if (! empty($mktToken)) {
            $client = $client->withHeaders(['X-Marketplace-Token' => $mktToken]);
        }

        $response = $client->delete("{$creds['bms_url']}/api/rest/{$table}/{$id}");

        if (! $response->successful()) {
            throw new Exception("BMS REST DELETE failed for {$table}/{$id}: HTTP {$response->status()}");
        }

        return true;
    }

    /**
     * Request a new Primary Key via /api/function?name=get_serialnumber
     *
     * @param  BmsSession|array<string, mixed>  $session
     *
     * @throws Exception
     */
    public function getSerialNumber(BmsSession|array $session, string $tableName, string $fieldName): int
    {
        $creds = $this->extractCredentials($session);
        $timeout = (int) Config::get('bms.timeouts.function', 15);

        $response = Http::withToken($creds['bms_session_code'])
            ->timeout($timeout)
            ->post("{$creds['bms_url']}/api/function?name=get_serialnumber", [
                'serial_name' => $tableName,
                'table_name' => $tableName,
                'field_name' => $fieldName,
            ]);

        $res = $response->json();
        if (($res['MessageCode'] ?? null) !== 200 || ! isset($res['Value'])) {
            $msg = $res['Message'] ?? 'Unknown function error';
            throw new Exception("Unable to get serial number for {$tableName}: {$msg}");
        }

        return (int) $res['Value'];
    }

    /**
     * Retrieve system variable via /api/function?name=get_hosvariable
     *
     * @param  BmsSession|array<string, mixed>  $session
     *
     * @throws Exception
     */
    public function getHosVariable(BmsSession|array $session, string $name): ?string
    {
        $creds = $this->extractCredentials($session);
        $timeout = (int) Config::get('bms.timeouts.function', 15);

        $response = Http::withToken($creds['bms_session_code'])
            ->timeout($timeout)
            ->post("{$creds['bms_url']}/api/function?name=get_hosvariable", [
                'name' => $name,
            ]);

        $res = $response->json();

        return $res['Value'] ?? null;
    }

    /**
     * Retrieve database server time directly from HOSxP.
     *
     * @param  BmsSession|array<string, mixed>  $session
     * @return array{server_datetime: string, server_date: string, server_time: string}
     *
     * @throws Exception
     */
    public function getServerDateTime(BmsSession|array $session): array
    {
        $sql = 'SELECT NOW() AS server_datetime, CURRENT_DATE() AS server_date, CURRENT_TIME() AS server_time';
        $result = $this->executeSql($session, $sql, [], 1);

        return $result[0] ?? [
            'server_datetime' => date('Y-m-d H:i:s'),
            'server_date' => date('Y-m-d'),
            'server_time' => date('H:i:s'),
        ];
    }

    /**
     * Check if a HOSxP user belongs to the active administrator group via BMS Session /api/sql.
     *
     * @param  BmsSession|array<string, mixed>  $session
     */
    public function isHosxpAdmin(BmsSession|array $session, ?string $userId = null): bool
    {
        $targetUserId = $userId;
        if (empty($targetUserId)) {
            $targetUserId = $session instanceof BmsSession ? $session->user_id : ($session['user_id'] ?? null);
        }

        if (empty($targetUserId)) {
            return false;
        }

        $sql = "
            SELECT 
                o.officer_id,
                o.officer_name,
                o.officer_login_name,
                o.officer_active,
                ogl.officer_group_id,
                og.officer_group_name
            FROM officer_group_list ogl
            JOIN officer_group og ON og.officer_group_id = ogl.officer_group_id
            JOIN officer o ON o.officer_id = ogl.officer_id
            WHERE (og.officer_group_name = 'ผู้ดูแลระบบ' OR og.officer_group_id = 19)
              AND (o.officer_login_name = :user_id OR o.officer_cid = :user_id)
              AND o.officer_active = 'Y'
        ";

        try {
            $results = $this->executeSql($session, $sql, ['user_id' => $targetUserId], 1);

            return ! empty($results);
        } catch (\Throwable $e) {
            Log::warning('BMS Data Service: Failed to verify HOSxP admin status via /api/sql: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Check BMS tunnel availability (from aopod).
     *
     * @param  BmsSession|array<string, mixed>  $session
     */
    public function checkTunnelStatus(BmsSession|array $session): bool
    {
        try {
            $creds = $this->extractCredentials($session);
            $response = Http::withoutVerifying()->timeout(5)->get("{$creds['bms_url']}/api/Status");

            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Resolve clean user/doctor name avoiding HOSxP masking/scrambling (from aopod).
     *
     * @param  array<string, mixed>  $sessionData
     * @param  array<string, mixed>|null  $doctor
     */
    public function resolveCleanName(array $sessionData, ?array $doctor = null): string
    {
        $sessionName = trim($sessionData['name'] ?? ($sessionData['user_name'] ?? ''));
        $providerName = trim($doctor['provider_id_name'] ?? '');
        $doctorName = trim($doctor['name'] ?? '');

        if (! empty($sessionName) && $sessionName !== 'ผู้ใช้งาน HOSxP' && ! $this->isScrambledThai($sessionName)) {
            return $sessionName;
        }

        if (! empty($providerName) && ! $this->isScrambledThai($providerName)) {
            return $providerName;
        }

        if (! empty($doctorName) && ! $this->isScrambledThai($doctorName)) {
            return $doctorName;
        }

        return (! empty($sessionName) && ! $this->isScrambledThai($sessionName)) ? $sessionName : 'ผู้ใช้งาน HOSxP';
    }

    /**
     * Check if a Thai string contains masked/scrambled characters from HOSxP encryption (from aopod).
     */
    public function isScrambledThai(?string $text): bool
    {
        if (empty($text)) {
            return false;
        }
        $text = trim($text);

        if (preg_match('/[ฃฅฦๆ]/u', $text) || preg_match('/[เแโใไ]{2,}/u', $text)) {
            return true;
        }

        return false;
    }
}
