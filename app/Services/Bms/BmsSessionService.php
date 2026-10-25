<?php

namespace App\Services\Bms;

use App\Models\BmsSession;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BmsSessionService
{
    /**
     * Standard BMS Cloud Endpoints (Hardcoded clean defaults across all hospitals).
     */
    protected string $pasteJsonUrl = 'https://hosxp.net/phapi/PasteJSON';

    protected string $legacyPasteUrl = 'https://hosxp.net/phps/bms_session_paste.php';

    protected string $marketplaceUrl = 'https://hosxp-marketplace.bmscloud.in.th';

    protected string $aiGatewayUrl = 'https://ai-api.kube.bmscloud.in.th';

    /**
     * Resolve a bms-session-id from hosxp.net PasteJSON service with caching.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function resolveSession(string $bmsSessionId): array
    {
        $sessionId = trim($bmsSessionId);
        if (empty($sessionId)) {
            throw new Exception('BMS Session ID ว่างเปล่า');
        }

        $cacheKey = "bms_paste_session_{$sessionId}";

        return Cache::remember($cacheKey, 3600, function () use ($sessionId): array {
            // 1. Try modern PasteJSON endpoint (Action=GET&code=...) like aopod
            try {
                $response = Http::withoutVerifying()
                    ->timeout(12)
                    ->get($this->pasteJsonUrl, [
                        'Action' => 'GET',
                        'code' => $sessionId,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (($data['MessageCode'] ?? null) === 200 || ($data['Message'] ?? '') === 'OK') {
                        $result = $data['result'] ?? [];
                        $userInfo = $result['user_info'] ?? $result;
                        $expiredSecond = (int) ($result['expired_second'] ?? 36000);

                        $sessionData = [
                            'bms_session_id' => $sessionId,
                            'bms_url' => rtrim($userInfo['bms_url'] ?? ($result['bms_url'] ?? ''), '/'),
                            'bms_direct_url' => ! empty($userInfo['bms_direct_url']) ? rtrim($userInfo['bms_direct_url'], '/') : null,
                            'bms_session_code' => $userInfo['bms_session_code'] ?? ($result['bms_session_code'] ?? ($result['key_value'] ?? '')),
                            'hospital_code' => $userInfo['hospital_code'] ?? ($result['hospital_code'] ?? null),
                            'hospital_name' => $userInfo['hospital_name'] ?? ($result['hospital_name'] ?? null),
                            'doctor_code' => $userInfo['doctor_code'] ?? null,
                            'user_id' => $userInfo['user_cid'] ?? ($userInfo['doctor_code'] ?? ($result['user_id'] ?? null)),
                            'user_name' => $userInfo['name'] ?? ($result['user_name'] ?? 'ผู้ใช้งาน HOSxP'),
                            'position' => $userInfo['position'] ?? null,
                            'department_id' => $userInfo['position_id'] ?? ($result['department_id'] ?? null),
                            'department_name' => $userInfo['department'] ?? ($result['department_name'] ?? null),
                            'location' => $userInfo['location'] ?? null,
                            'database_name' => $userInfo['bms_database_name'] ?? ($result['database_name'] ?? null),
                            'database_type' => $userInfo['bms_database_type'] ?? ($result['database_type'] ?? 'MySQL'),
                            'expired_second' => $expiredSecond,
                        ];

                        if (! empty($sessionData['hospital_code'])) {
                            $this->rememberHospitalSession($sessionData);
                        }

                        return $sessionData;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("BMS PasteJSON modern API failed, trying legacy: {$e->getMessage()}");
            }

            // 2. Fallback to legacy endpoint (bms-session-id=...)
            $response = Http::withoutVerifying()
                ->timeout(10)
                ->get($this->legacyPasteUrl, [
                    'bms-session-id' => $sessionId,
                ]);

            if (! $response->successful()) {
                throw new Exception("Unable to reach BMS PasteJSON service: HTTP {$response->status()}");
            }

            $data = $response->json();
            if (($data['MessageCode'] ?? null) !== 200 || empty($data['result']['bms_url'])) {
                $errorMsg = $data['Message'] ?? 'Unknown error while resolving session';
                throw new Exception("Resolve BMS Session failed: {$errorMsg}");
            }

            $result = $data['result'];
            $result['bms_session_id'] = $sessionId;
            if (! empty($result['hospital_code'])) {
                $this->rememberHospitalSession($result);
            }

            return $result;
        });
    }

    /**
     * Remember active hospital session in Cache pool (aopod style).
     *
     * @param  array<string, mixed>  $sessionData
     */
    public function rememberHospitalSession(array $sessionData): void
    {
        $hospCode = $sessionData['hospital_code'] ?? null;
        if (! $hospCode) {
            return;
        }

        $ttl = now()->addSeconds((int) ($sessionData['expired_second'] ?? 36000));
        Cache::put("bms_hospital_pool_{$hospCode}", $sessionData, $ttl);
    }

    /**
     * Retrieve active hospital session from Cache pool (aopod style).
     *
     * @return array<string, mixed>|null
     */
    public function getHospitalSession(?string $hospCode = null): ?array
    {
        if ($hospCode) {
            return Cache::get("bms_hospital_pool_{$hospCode}");
        }

        return null;
    }

    /**
     * Save or update session record in both Cache pool and database.
     *
     * @param  array<string, mixed>  $pasteResult
     */
    public function saveSession(
        string $deviceKey,
        string $bmsSessionId,
        array $pasteResult,
        ?string $hn = null,
        ?string $vn = null,
        ?Request $request = null
    ): BmsSession {
        $expiredSecond = (int) ($pasteResult['expired_second'] ?? 36000);
        $expiresAt = Carbon::now()->addSeconds($expiredSecond);

        $ipAddress = $request ? $request->ip() : request()->ip();
        $userAgent = $request ? $request->userAgent() : request()->userAgent();

        // 1. Remember in Cache pool for zero-friction access
        $this->rememberHospitalSession($pasteResult);

        // 2. Persist to database bms_sessions table
        return BmsSession::updateOrCreate(
            ['bms_session_id' => $bmsSessionId],
            [
                'device_key' => $deviceKey,
                'bms_url' => rtrim($pasteResult['bms_url'], '/'),
                'bms_direct_url' => ! empty($pasteResult['bms_direct_url']) ? rtrim($pasteResult['bms_direct_url'], '/') : null,
                'bms_session_code' => $pasteResult['bms_session_code'],
                'hospital_code' => $pasteResult['hospital_code'] ?? null,
                'hospital_name' => $pasteResult['hospital_name'] ?? null,
                'user_id' => $pasteResult['user_id'] ?? null,
                'user_name' => $pasteResult['user_name'] ?? null,
                'department_id' => $pasteResult['department_id'] ?? null,
                'department_name' => $pasteResult['department_name'] ?? null,
                'last_hn' => $hn,
                'last_vn' => $vn,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'expires_at' => $expiresAt,
                'last_active_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Get the best active session that has the MOST REMAINING TIME for this device/workstation.
     */
    public function getBestActiveSession(?string $deviceKey = null): ?BmsSession
    {
        $query = BmsSession::query()->longestRemaining();

        if ($deviceKey !== null) {
            $session = (clone $query)->where('device_key', $deviceKey)->first();
            if ($session !== null) {
                $session->touchActivity();

                return $session;
            }

            // Fallback 1: Match active session from the same client IP (workstation)
            $clientIp = request()->ip();
            if ($clientIp) {
                $sessionByIp = (clone $query)->where('ip_address', $clientIp)->first();
                if ($sessionByIp !== null) {
                    $sessionByIp->update(['device_key' => $deviceKey]);
                    $sessionByIp->touchActivity();

                    return $sessionByIp;
                }
            }

            // Fallback 2: On localhost or local environment, bind to active valid session
            if (request()->ip() === '127.0.0.1' || request()->ip() === '::1' || app()->environment('local')) {
                $localSession = (clone $query)->first();
                if ($localSession !== null) {
                    $localSession->update(['device_key' => $deviceKey]);
                    $localSession->touchActivity();

                    return $localSession;
                }
            }

            return null;
        }

        return (clone $query)->first();
    }

    /**
     * Extract or generate a client device key from the current HTTP Request/Cookie.
     */
    public function getOrCreateDeviceKey(?Request $request = null): string
    {
        $req = $request ?? request();
        $cookieName = Config::get('bms.device_cookie.name', 'bms_device_key');

        $deviceKey = $req->cookie($cookieName) ?? $req->header('X-Device-Key');

        if (empty($deviceKey)) {
            $deviceKey = 'dev_'.Str::random(40);
            $lifetime = (int) Config::get('bms.device_cookie.lifetime', 43200);
            Cookie::queue($cookieName, $deviceKey, $lifetime);
        }

        return $deviceKey;
    }

    /**
     * Prune all expired sessions from the database.
     */
    public function pruneExpiredSessions(): int
    {
        $now = Carbon::now();
        $deleted = BmsSession::where('expires_at', '<=', $now)->delete();

        if ($deleted > 0) {
            Log::info("BMS Session Service: Pruned {$deleted} expired sessions.");
        }

        return $deleted;
    }
}
