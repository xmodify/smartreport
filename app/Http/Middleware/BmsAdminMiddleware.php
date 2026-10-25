<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Bms\Bms;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BmsAdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. If already authenticated via standard Laravel session, allow
        if (Auth::check()) {
            return $next($request);
        }

        // 2. Check active BMS Session with HOSxP Admin rights via /api/sql
        $session = Bms::current();

        if ($session && Bms::isAdmin($session)) {
            $email = ($session->user_id ?? 'admin').'@hosxp.local';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $session->user_name ?? 'HOSxP Admin',
                    'password' => bcrypt(Str::random(32)),
                ]
            );

            Auth::login($user);

            return $next($request);
        }

        // 3. Unauthorized: Redirect to login
        return redirect()->route('login')->withErrors([
            'email' => 'สิทธิ์นี้สงวนไว้สำหรับผู้ใช้งาน HOSxP ในกลุ่ม "ผู้ดูแลระบบ" ที่เปิดใช้งานเท่านั้น',
        ]);
    }
}
