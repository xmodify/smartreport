<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /**
     * Show the System Admin Login page.
     */
    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return Inertia::render('Auth/Login');
    }

    /**
     * Authenticate system admin credentials.
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginInput = trim($request->input('email'));
        $password = $request->input('password');
        $remember = $request->boolean('remember');

        // Check if input is a valid email
        $isEmail = filter_var($loginInput, FILTER_VALIDATE_EMAIL);

        $attempted = false;

        if ($isEmail) {
            $attempted = Auth::attempt(['email' => $loginInput, 'password' => $password], $remember);
        } else {
            // Try by username / name or fallback to {username}@bms.local
            $attempted = Auth::attempt(['name' => $loginInput, 'password' => $password], $remember);

            if (! $attempted) {
                $attempted = Auth::attempt(['email' => "{$loginInput}@bms.local", 'password' => $password], $remember);
            }
        }

        if ($attempted) {
            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard'))
                ->with('success', 'เข้าสู่ระบบผู้ดูแลระบบสำเร็จ');
        }

        return back()->withErrors([
            'email' => 'ชื่อผู้ใช้งาน/อีเมล หรือรหัสผ่านไม่ถูกต้อง',
        ])->onlyInput('email');
    }

    /**
     * Log out system admin.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'ออกจากระบบเรียบร้อยแล้ว');
    }
}
