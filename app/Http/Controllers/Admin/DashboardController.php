<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BmsSession;
use App\Models\Homepage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the new clean System Admin Dashboard.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'adminUser' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
            'systems' => [
                [
                    'id' => 'homepage',
                    'name' => 'SmartReport Homepage',
                    'title' => 'ระบบประชาสัมพันธ์และสถิติ HOSxP (SmartReport)',
                    'path' => '/bms-homepage',
                    'status' => 'active',
                    'version' => '1.0.0',
                    'description' => 'แสดงโปสเตอร์ประชาสัมพันธ์และตัวชี้วัดโรงพยาบาล HOSxP แบบเรียลไทม์ พร้อมระบบจัดการโปสเตอร์สำหรับผู้ดูแลระบบ HOSxP',
                ],
            ],
            'stats' => [
                'active_sessions' => BmsSession::where('expires_at', '>', now())->count(),
                'total_posters' => Homepage::count(),
            ],
        ]);
    }
}
