<?php

namespace App\Services\Bms;

use App\Models\BmsSession;
use Illuminate\Http\Request;

/**
 * @method static BmsSessionService session()
 * @method static BmsDataService data()
 * @method static BmsAiService ai()
 * @method static BmsMarketplaceService marketplace()
 * @method static BmsSession|null current(?string $deviceKey = null)
 * @method static int prune()
 */
class Bms
{
    /**
     * Get BmsSessionService instance.
     */
    public static function session(): BmsSessionService
    {
        return app(BmsSessionService::class);
    }

    /**
     * Get BmsDataService instance.
     */
    public static function data(): BmsDataService
    {
        return app(BmsDataService::class);
    }

    /**
     * Get BmsAiService instance.
     */
    public static function ai(): BmsAiService
    {
        return app(BmsAiService::class);
    }

    /**
     * Get BmsMarketplaceService instance.
     */
    public static function marketplace(): BmsMarketplaceService
    {
        return app(BmsMarketplaceService::class);
    }

    /**
     * Get the active session with the LONGEST REMAINING TIME for current device/workstation.
     */
    public static function current(?string $deviceKey = null): ?BmsSession
    {
        $sessionService = static::session();
        $key = $deviceKey ?? $sessionService->getOrCreateDeviceKey();

        return $sessionService->getBestActiveSession($key);
    }

    /**
     * Prune all expired sessions from the database.
     */
    public static function prune(): int
    {
        return static::session()->pruneExpiredSessions();
    }

    /**
     * Check if the current BMS session user has active HOSxP Administrator rights.
     */
    public static function isAdmin(?BmsSession $session = null): bool
    {
        $currentSession = $session ?? static::current();
        if (! $currentSession) {
            return false;
        }

        return static::data()->isHosxpAdmin($currentSession);
    }

    /**
     * Get current hospital code (matching opdconfig.hospitalcode).
     * Resolves from session, request query (hcode/hospital_code), active session, or config default.
     */
    public static function hospitalCode(?BmsSession $session = null, ?Request $request = null): ?string
    {
        if ($request) {
            $reqCode = $request->query('hcode') ?? $request->query('hospital_code') ?? $request->input('hcode');
            if (! empty($reqCode)) {
                return (string) $reqCode;
            }
        }

        if ($session && ! empty($session->hospital_code)) {
            return (string) $session->hospital_code;
        }

        $currentSession = static::current();
        if ($currentSession && ! empty($currentSession->hospital_code)) {
            return (string) $currentSession->hospital_code;
        }

        return config('bms.default_hcode');
    }

    /**
     * Get current hospital name.
     */
    public static function hospitalName(?BmsSession $session = null): ?string
    {
        if ($session && ! empty($session->hospital_name)) {
            return (string) $session->hospital_name;
        }

        $currentSession = static::current();
        if ($currentSession && ! empty($currentSession->hospital_name)) {
            return (string) $currentSession->hospital_name;
        }

        $pooled = static::session()->getHospitalSession();
        if ($pooled && ! empty($pooled['hospital_name'])) {
            return (string) $pooled['hospital_name'];
        }

        return config('bms.default_hname');
    }
}
