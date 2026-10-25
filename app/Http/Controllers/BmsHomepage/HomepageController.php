<?php

namespace App\Http\Controllers\BmsHomepage;

use App\Http\Controllers\Controller;
use App\Models\BmsSession;
use App\Models\Homepage;
use App\Services\Bms\Bms;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HomepageController extends Controller
{
    /**
     * Display the BMS Homepage (One-Page Split Screen: Reports & Posters).
     */
    public function index(Request $request): Response
    {
        Log::info('HOSxP BMS-Homepage Request', [
            'full_url' => $request->fullUrl(),
            'query' => $request->query(),
            'client_ip' => $request->ip(),
        ]);

        // 1. Resolve and store session if opened with BMS Session ID (query string, post, or server QUERY_STRING)
        $bmsSession = null;
        $gatewayError = null;
        $sessionId = $this->extractSessionId($request);

        if (! empty($sessionId)) {
            $deviceKey = Bms::session()->getOrCreateDeviceKey($request);
            try {
                $resolved = Bms::session()->resolveSession($sessionId);
                $bmsSession = Bms::session()->saveSession(
                    $deviceKey,
                    $sessionId,
                    $resolved,
                    $request->query('hn'),
                    $request->query('vn'),
                    $request
                );
                session(['bms_session_id' => $sessionId]);
            } catch (\Throwable $e) {
                Log::warning('BMS session resolve failed: '.$e->getMessage());
            }
        }

        // 2. Fallback to active session with longest remaining time
        if (! $bmsSession) {
            $bmsSession = Bms::current();
        }

        if (! $bmsSession) {
            $gatewayError = 'ไม่สามารถเชื่อมต่อ BMS-Session-Gateway';
        }

        // 3. Verify HOSxP Admin permission via BMS Session /api/sql
        $canManageAdmin = false;
        if ($bmsSession && Bms::isAdmin($bmsSession)) {
            $canManageAdmin = true;
        } elseif (app()->environment('testing') || (app()->environment('local') && $request->has('admin'))) {
            $canManageAdmin = true;
        }

        // 4. Resolve hospital code and name directly from HOSxP via BMS Gateway
        $hospitalCode = Bms::hospitalCode($bmsSession, $request);
        $hospitalName = $bmsSession ? ($bmsSession->hospital_name ?? Bms::hospitalName($bmsSession)) : 'ไม่สามารถเชื่อมต่อ BMS-Session-Gateway';

        if ($bmsSession) {
            try {
                $hospQuery = 'SELECT name, hospcode FROM hospcode WHERE hospcode = :hospcode';
                $hospRows = Bms::data()->executeSql($bmsSession, $hospQuery, ['hospcode' => $hospitalCode], 1);
                if (! empty($hospRows[0]['name'])) {
                    $hospitalName = (string) $hospRows[0]['name'];
                    if (empty($bmsSession->hospital_name)) {
                        $bmsSession->update(['hospital_name' => $hospitalName]);
                    }
                }
                if (! $request->filled('hcode') && ! empty($hospRows[0]['hospcode'])) {
                    $hospitalCode = (string) $hospRows[0]['hospcode'];
                }
            } catch (\Throwable $e) {
                Log::warning('HOSxP hospcode query failed: '.$e->getMessage());
            }
        }

        // 5. Query ONLY active posters belonging to this hospital for the main carousel
        $posters = Homepage::where('is_active', true)
            ->forHospital($hospitalCode)
            ->orderBy('order_num')
            ->latest()
            ->get();

        // 6. Query all posters for this hospital if admin mode (for poster manager)
        $adminPosters = $canManageAdmin
            ? Homepage::forHospital($hospitalCode)->orderBy('order_num')->latest()->get()
            : [];

        $totalCount = Homepage::forHospital($hospitalCode)->count();
        $maxLimit = Homepage::MAX_POSTERS_PER_HOSPITAL;

        $adminStats = $canManageAdmin ? [
            'total' => $totalCount,
            'active' => Homepage::forHospital($hospitalCode)->where('is_active', true)->count(),
            'inactive' => Homepage::forHospital($hospitalCode)->where('is_active', false)->count(),
            'max' => $maxLimit,
            'remaining' => max(0, $maxLimit - $totalCount),
        ] : null;

        // 7. Load HOSxP Clinic Service Stats & Real-time queue metrics
        $initialDate = now()->format('Y-m-d');
        if ($bmsSession) {
            $latestDate = $this->getLatestActiveOvstDate($bmsSession);
            if ($latestDate && ! $this->hasActiveVisitsOnDate($bmsSession, $initialDate)) {
                $initialDate = $latestDate;
            }
        }

        $clinicStats = $this->fetchClinicStatsData($bmsSession, $initialDate);
        $realtimeData = $this->fetchRealtimeCurDepData($bmsSession);
        $queueHeatmap = $this->fetchQueueHeatmapData($bmsSession, $initialDate);

        // Hospital Metrics for left dashboard
        $hosxpData = [
            'hospital_code' => $hospitalCode,
            'hospital_name' => $hospitalName,
            'today_date' => now()->locale('th')->isoFormat('D MMMM GGGG'),
            'opd_total' => $clinicStats['total_patients'] ?? 0,
            'ipd_occupied' => 0,
            'ipd_total' => 0,
            'ipd_rate' => 0,
            'er_urgent' => 0,
            'avg_wait_minutes' => 0,
            'top_departments' => $clinicStats['departments'] ?? [],
        ];

        if ($bmsSession) {
            try {
                $iptRes = Bms::data()->executeSql($bmsSession, 'SELECT COUNT(*) AS total FROM ipt WHERE dchdate IS NULL', [], 1);
                $hosxpData['ipd_occupied'] = ! empty($iptRes[0]['total']) ? (int) $iptRes[0]['total'] : 0;
            } catch (\Throwable $e) {
            }

            try {
                $erRes = Bms::data()->executeSql($bmsSession, 'SELECT COUNT(*) AS total FROM er_regist WHERE vstdate = CURDATE()', [], 1);
                $hosxpData['er_urgent'] = ! empty($erRes[0]['total']) ? (int) $erRes[0]['total'] : 0;
            } catch (\Throwable $e) {
            }
        }

        return Inertia::render('BmsHomepage/Index', [
            'posters' => $posters,
            'hospitalCode' => $hospitalCode,
            'hospitalName' => $hospitalName,
            'hosxpData' => $hosxpData,
            'clinicStats' => $clinicStats,
            'realtimeData' => $realtimeData,
            'queueHeatmap' => $queueHeatmap,
            'canManageAdmin' => $canManageAdmin,
            'adminPosters' => $adminPosters,
            'adminStats' => $adminStats,
            'hasSession' => (bool) $bmsSession,
            'gatewayError' => $gatewayError,
            'bmsSessionId' => $bmsSession ? $bmsSession->bms_session_id : null,
            'bmsUser' => $bmsSession ? [
                'user_id' => $bmsSession->user_id,
                'user_name' => $bmsSession->user_name,
                'hospital_code' => $bmsSession->hospital_code ?? $hospitalCode,
                'hospital_name' => $bmsSession->hospital_name ?? $hospitalName,
            ] : null,
        ]);
    }

    /**
     * API endpoint to fetch HOSxP Clinic Service Workload Statistics dynamically by date.
     */
    public function getClinicStats(Request $request): JsonResponse
    {
        $date = (string) $request->query('date', now()->format('Y-m-d'));
        $bmsSession = Bms::current();
        if (! $bmsSession) {
            $sessionId = $this->extractSessionId($request);
            if (! empty($sessionId)) {
                $bmsSession = BmsSession::where('bms_session_id', $sessionId)->first();
            }
        }

        $stats = $this->fetchClinicStatsData($bmsSession, $date);

        return response()->json($stats);
    }

    /**
     * API endpoint to fetch OPD Queue & Real-time cur_dep dynamically.
     */
    public function getQueueHeatmap(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->format('Y-m-d'));
        $bmsSession = Bms::current();
        if (! $bmsSession) {
            $sessionId = $this->extractSessionId($request);
            if (! empty($sessionId)) {
                $bmsSession = BmsSession::where('bms_session_id', $sessionId)->first();
            }
        }

        $realtimeData = $this->fetchRealtimeCurDepData($bmsSession);
        $heatmapData = $this->fetchQueueHeatmapData($bmsSession, $date);

        return response()->json([
            'realtime' => $realtimeData,
            'heatmap' => $heatmapData,
        ]);
    }

    /**
     * Fetch real-time active waiting patients by department from ovst.cur_dep.
     *
     * @return array<string, mixed>
     */
    public function fetchRealtimeCurDepData(?BmsSession $bmsSession): array
    {
        $sqlToday = "SELECT 
                        k.department,
                        k.depcode,
                        COUNT(*) AS waiting_count
                    FROM ovst o
                    LEFT JOIN kskdepartment k ON k.depcode = o.cur_dep
                    WHERE o.vstdate = CURDATE()
                      AND o.cur_dep IS NOT NULL
                      AND o.cur_dep <> ''
                      AND o.cur_dep <> '999'
                    GROUP BY k.department, k.depcode
                    ORDER BY waiting_count DESC";

        if (! $bmsSession) {
            return [
                'total_waiting' => 0,
                'departments' => [],
                'is_live' => false,
                'error' => 'ไม่สามารถเชื่อมต่อ BMS-Session-Gateway',
                'query_time' => now()->format('H:i:s'),
                'sql' => $sqlToday,
            ];
        }

        $rows = [];
        $isLive = false;

        try {
            // 1. Live query for today: ovst.cur_dep
            $rows = Bms::data()->executeSql($bmsSession, $sqlToday, [], 100);

            // 2. If today is empty/holiday, check latest active date
            if (empty($rows)) {
                $sqlLatest = "SELECT 
                                k.department,
                                k.depcode,
                                COUNT(*) AS waiting_count
                            FROM ovst o
                            LEFT JOIN kskdepartment k ON k.depcode = o.cur_dep
                            WHERE o.vstdate = (SELECT MAX(vstdate) FROM ovst)
                              AND o.cur_dep IS NOT NULL
                              AND o.cur_dep <> ''
                              AND o.cur_dep <> '999'
                            GROUP BY k.department, k.depcode
                            ORDER BY waiting_count DESC";
                $rows = Bms::data()->executeSql($bmsSession, $sqlLatest, [], 20);
            }

            $isLive = true;
        } catch (\Throwable $e) {
            Log::warning('Real-time ovst.cur_dep query failed: '.$e->getMessage());

            return [
                'total_waiting' => 0,
                'departments' => [],
                'is_live' => false,
                'error' => 'ไม่สามารถเชื่อมต่อ BMS-Session-Gateway',
                'query_time' => now()->format('H:i:s'),
                'sql' => $sqlToday,
            ];
        }

        $totalWaiting = 0;
        $formattedDepartments = [];
        foreach ($rows as $r) {
            $count = (int) ($r['waiting_count'] ?? 0);
            $totalWaiting += $count;
            $formattedDepartments[] = [
                'name' => (string) ($r['department'] ?? 'ไม่ระบุแผนก'),
                'depcode' => (string) ($r['depcode'] ?? ''),
                'count' => $count,
                'status' => $count >= 30 ? 'หนาแน่น' : ($count >= 15 ? 'ปานกลาง' : 'ปกติ'),
                'status_color' => $count >= 30 ? 'red' : ($count >= 15 ? 'amber' : 'emerald'),
            ];
        }

        return [
            'total_waiting' => $totalWaiting,
            'departments' => $formattedDepartments,
            'is_live' => $isLive,
            'query_time' => now()->format('H:i:s'),
            'sql' => $sqlToday,
        ];
    }

    /**
     * Fetch and format OPD Queue Heatmap data from HOSxP via BMS Session.
     *
     * @return array<string, mixed>
     */
    public function fetchQueueHeatmapData(?BmsSession $bmsSession, string $date): array
    {
        $rawRows = [];
        $isLive = false;

        if ($bmsSession) {
            try {
                $sql = "SELECT k.department, HOUR(q.queue_datetime) AS hr, COUNT(*) AS q
                        FROM opd_dep_queue q
                        LEFT JOIN kskdepartment k ON k.depcode = q.depcode
                        WHERE DATE(q.queue_datetime) = :selected_date
                          AND q.depcode <> '999'
                        GROUP BY k.department, HOUR(q.queue_datetime)
                        ORDER BY k.department, hr";
                $params = ['selected_date' => $date];

                $rawRows = Bms::data()->executeSql($bmsSession, $sql, $params, 1000);
                if (! empty($rawRows)) {
                    $isLive = true;
                }
            } catch (\Throwable $e) {
                Log::warning('BMS Queue Heatmap Query note: '.$e->getMessage());
            }
        }

        return $this->formatQueueHeatmap($rawRows, $date, $isLive);
    }

    /**
     * Format raw queue rows into pivoted department x hour matrix.
     *
     * @param  array<int, array<string, mixed>>  $rawRows
     * @return array<string, mixed>
     */
    protected function formatQueueHeatmap(array $rawRows, string $date, bool $isLive): array
    {
        $hours = [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
        $matrix = [];
        $deptTotals = [];
        $hourTotals = array_fill_keys($hours, 0);
        $grandTotal = 0;
        $maxCell = 0;

        foreach ($rawRows as $row) {
            $dept = (string) ($row['department'] ?? 'ไม่ระบุจุดบริการ');
            $hr = (int) ($row['hr'] ?? 0);
            $q = (int) ($row['q'] ?? 0);

            if (! isset($matrix[$dept])) {
                $matrix[$dept] = array_fill_keys($hours, 0);
                $deptTotals[$dept] = 0;
            }

            if (in_array($hr, $hours, true)) {
                $matrix[$dept][$hr] = $q;
                $hourTotals[$hr] = ($hourTotals[$hr] ?? 0) + $q;
            }

            $deptTotals[$dept] += $q;
            $grandTotal += $q;

            if ($q > $maxCell) {
                $maxCell = $q;
            }
        }

        arsort($deptTotals);

        $departments = [];
        foreach ($deptTotals as $deptName => $total) {
            $departments[] = [
                'name' => $deptName,
                'total' => $total,
                'hours' => $matrix[$deptName] ?? array_fill_keys($hours, 0),
            ];
        }

        arsort($hourTotals);
        $peakHour = ! empty($hourTotals) ? (int) array_key_first($hourTotals) : 10;
        $peakHourCount = ! empty($hourTotals) ? reset($hourTotals) : 0;

        $sqlUsed = "SELECT k.department, HOUR(q.queue_datetime) AS hr, COUNT(*) AS q\nFROM opd_dep_queue q\nLEFT JOIN kskdepartment k ON k.depcode = q.depcode\nWHERE DATE(q.queue_datetime) = '{$date}'\n  AND q.depcode <> '999'\nGROUP BY k.department, HOUR(q.queue_datetime)\nORDER BY k.department, hr;";

        return [
            'hours' => $hours,
            'departments' => $departments,
            'total_queues' => $grandTotal,
            'peak_hour' => $peakHour,
            'peak_hour_count' => $peakHourCount,
            'max_cell' => $maxCell,
            'selected_date' => $date,
            'is_live' => $isLive,
            'sql' => $sqlUsed,
        ];
    }

    /**
     * Fetch HOSxP Clinic Service Workload statistics for a given date.
     *
     * @return array<string, mixed>
     */
    public function fetchClinicStatsData(?BmsSession $bmsSession, string $date): array
    {
        try {
            $carbon = Carbon::parse($date);
            $thYear = (int) $carbon->format('Y') + 543;
            $thaiDate = $carbon->locale('th')->translatedFormat('j F ').$thYear;
        } catch (\Throwable $e) {
            $thaiDate = $date;
        }

        if (! $bmsSession) {
            return [
                'success' => false,
                'error' => 'ไม่สามารถเชื่อมต่อ BMS-Session-Gateway',
                'selected_date' => $date,
                'thai_date' => $thaiDate,
                'is_live' => false,
                'total_patients' => 0,
                'departments' => [],
            ];
        }

        $sql = "SELECT 
                    k.department AS name,
                    COUNT(DISTINCT o.vn) AS count
                FROM ovst o
                LEFT JOIN kskdepartment k ON k.depcode = o.main_dep
                WHERE o.vstdate = :selected_date
                  AND o.main_dep IS NOT NULL
                  AND o.main_dep <> ''
                  AND o.main_dep <> '999'
                GROUP BY k.department
                ORDER BY count DESC";

        $sqlTotal = 'SELECT COUNT(*) AS total FROM ovst WHERE vstdate = :selected_date';

        try {
            $rawRows = Bms::data()->executeSql($bmsSession, $sql, ['selected_date' => $date], 100);
            $totalRes = Bms::data()->executeSql($bmsSession, $sqlTotal, ['selected_date' => $date], 1);
            $totalOpd = ! empty($totalRes[0]['total']) ? (int) $totalRes[0]['total'] : array_sum(array_column($rawRows, 'count'));

            $maxCount = ! empty($rawRows) ? max(array_column($rawRows, 'count')) : 1;

            $departments = array_map(function ($row) use ($maxCount, $totalOpd) {
                $count = (int) ($row['count'] ?? 0);

                return [
                    'name' => (string) ($row['name'] ?? 'ไม่ระบุชื่อคลินิก'),
                    'count' => $count,
                    'percent' => $maxCount > 0 ? (int) round(($count / $maxCount) * 100) : 0,
                    'share' => $totalOpd > 0 ? round(($count / $totalOpd) * 100, 1) : 0,
                ];
            }, $rawRows);

            return [
                'success' => true,
                'selected_date' => $date,
                'thai_date' => $thaiDate,
                'is_live' => true,
                'total_patients' => $totalOpd,
                'departments' => $departments,
            ];
        } catch (\Throwable $e) {
            Log::warning('BMS Clinic Stats query failed: '.$e->getMessage());

            return [
                'success' => false,
                'error' => 'ไม่สามารถเชื่อมต่อ BMS-Session-Gateway',
                'selected_date' => $date,
                'thai_date' => $thaiDate,
                'is_live' => false,
                'total_patients' => 0,
                'departments' => [],
            ];
        }
    }

    /**
     * Check if a given date has active visits in ovst.
     */
    protected function hasActiveVisitsOnDate(?BmsSession $bmsSession, string $date): bool
    {
        if (! $bmsSession) {
            return false;
        }

        $sql = 'SELECT vn FROM ovst WHERE vstdate = :vstdate';
        try {
            $rows = Bms::data()->executeSql($bmsSession, $sql, ['vstdate' => $date], 1);

            return ! empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get latest active visit date from ovst.
     */
    protected function getLatestActiveOvstDate(?BmsSession $bmsSession): ?string
    {
        if (! $bmsSession) {
            return null;
        }

        $sql = 'SELECT MAX(vstdate) AS latest_date FROM ovst';
        try {
            $rows = Bms::data()->executeSql($bmsSession, $sql, [], 1);
            if (! empty($rows[0]['latest_date'])) {
                return (string) $rows[0]['latest_date'];
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    /**
     * Connect or store BMS Session manually from prompt.
     */
    public function connectSession(Request $request): RedirectResponse
    {
        $request->validate([
            'bms_session_id' => ['required', 'string'],
        ]);

        $sessionId = trim($request->input('bms_session_id'));
        $deviceKey = Bms::session()->getOrCreateDeviceKey($request);

        try {
            $resolved = Bms::session()->resolveSession($sessionId);
        } catch (\Throwable $e) {
            Log::warning('BMS session resolve failed: '.$e->getMessage());

            return back()->withErrors([
                'bms_session_id' => 'ไม่สามารถเชื่อมต่อ BMS-Session-Gateway: รหัส Session ไม่ถูกต้องหรือหมดอายุ',
            ]);
        }

        $session = Bms::session()->saveSession(
            $deviceKey,
            $sessionId,
            $resolved,
            $request->input('hn'),
            $request->input('vn'),
            $request
        );

        $hName = $session->hospital_name ? "{$session->hospital_name} " : '';
        $hCode = $session->hospital_code ? "(รหัส {$session->hospital_code})" : '';

        return redirect()->route('homepage.index')->with('success', "เชื่อมต่อ BMS Session สำเร็จ {$hName}{$hCode}");
    }

    /**
     * Disconnect active BMS Session for current device.
     */
    public function disconnectSession(Request $request): RedirectResponse
    {
        $deviceKey = Bms::session()->getOrCreateDeviceKey($request);
        BmsSession::forDevice($deviceKey)->delete();
        Cache::forget('bms_latest_active_session');

        return redirect()->route('homepage.index')->with('info', 'ตัดการเชื่อมต่อ BMS Session เรียบร้อยแล้ว');
    }

    /**
     * Store new poster images uploaded by HOSxP Admin.
     */
    public function storePoster(Request $request): RedirectResponse
    {
        $this->authorizeHosxpAdmin($request);

        $request->validate([
            'image' => ['nullable', 'image', 'max:20480'],
            'images.*' => ['nullable', 'image', 'max:20480'],
        ]);

        $bmsSession = Bms::current();
        $hcode = Bms::hospitalCode($bmsSession, $request);

        $currentCount = Homepage::forHospital($hcode)->count();
        $maxLimit = Homepage::MAX_POSTERS_PER_HOSPITAL;

        $incomingCount = 0;
        if ($request->hasFile('images')) {
            $incomingCount = count($request->file('images'));
        } elseif ($request->hasFile('image')) {
            $incomingCount = 1;
        }

        if ($incomingCount === 0) {
            return back()->withErrors(['image' => 'กรุณาเลือกไฟล์รูปภาพที่ต้องการอัปโหลด']);
        }

        // Enforce max 8 poster limit per hospital
        if ($currentCount + $incomingCount > $maxLimit) {
            $available = max(0, $maxLimit - $currentCount);

            return back()->withErrors([
                'image' => "จำกัดการเพิ่มโปสเตอร์ข่าวไม่เกิน {$maxLimit} รูป (ปัจจุบันโรงพยาบาลรหัส {$hcode} มีแล้ว {$currentCount} รูป สามารถเพิ่มได้อีก {$available} รูป)",
            ]);
        }

        $now = now();
        $datePrefix = $now->format('Ymd_His');
        $autoTitle = $now->format('Y-m-d H:i');

        // Batch upload
        if ($request->hasFile('images')) {
            $order = Homepage::forHospital($hcode)->max('order_num') ?? 0;
            $index = 1;
            foreach ($request->file('images') as $file) {
                $filename = "{$datePrefix}_{$index}_".substr(uniqid(), -4).'.'.$file->getClientOriginalExtension();
                $path = Storage::disk('uploads')->putFileAs('homepage', $file, $filename);

                Homepage::create([
                    'hcode' => $hcode,
                    'title' => $autoTitle.($index > 1 ? " (#{$index})" : ''),
                    'image' => $path,
                    'order_num' => ++$order,
                    'is_active' => true,
                ]);
                $index++;
            }

            return back()->with('success', "อัปโหลดรูปโปสเตอร์สำเร็จเรียบร้อยแล้ว ({$incomingCount} รูป)");
        }

        // Single upload
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = "{$datePrefix}_".substr(uniqid(), -4).'.'.$file->getClientOriginalExtension();
            $path = Storage::disk('uploads')->putFileAs('homepage', $file, $filename);

            $order = Homepage::forHospital($hcode)->max('order_num') ?? 0;

            Homepage::create([
                'hcode' => $hcode,
                'title' => $autoTitle,
                'image' => $path,
                'order_num' => $order + 1,
                'is_active' => true,
            ]);

            return back()->with('success', 'อัปโหลดรูปโปสเตอร์สำเร็จเรียบร้อยแล้ว');
        }

        return back()->withErrors(['image' => 'กรุณาเลือกไฟล์รูปภาพที่ต้องการอัปโหลด']);
    }

    /**
     * Toggle active/inactive status of a poster.
     */
    public function togglePoster(Request $request, int $id): RedirectResponse
    {
        $this->authorizeHosxpAdmin($request);

        $bmsSession = Bms::current();
        $hcode = Bms::hospitalCode($bmsSession, $request);

        $poster = Homepage::forHospital($hcode)->findOrFail($id);
        $poster->is_active = ! $poster->is_active;
        $poster->save();

        return back()->with('success', 'เปลี่ยนสถานะการแสดงผลเรียบร้อยแล้ว');
    }

    /**
     * Delete a poster record and physical file.
     */
    public function deletePoster(Request $request, int $id): RedirectResponse
    {
        $this->authorizeHosxpAdmin($request);

        $bmsSession = Bms::current();
        $hcode = $request->input('hcode') ?? $request->query('hcode') ?? Bms::hospitalCode($bmsSession, $request);

        $poster = Homepage::forHospital($hcode)->findOrFail($id);

        // Delete physical file
        if ($poster->image && ! str_starts_with($poster->image, 'samples/')) {
            Storage::disk('uploads')->delete($poster->image);

            $fullPath = public_path('uploads/'.$poster->image);
            if (file_exists($fullPath) && is_file($fullPath)) {
                @unlink($fullPath);
            }
            $directPath = public_path($poster->image);
            if (file_exists($directPath) && is_file($directPath)) {
                @unlink($directPath);
            }
        }

        $poster->delete();

        return back()->with('success', 'ลบรูปโปสเตอร์เรียบร้อยแล้ว');
    }

    /**
     * Update poster details / ordering.
     */
    public function updatePoster(Request $request, int $id): RedirectResponse
    {
        $this->authorizeHosxpAdmin($request);

        $bmsSession = Bms::current();
        $hcode = $request->input('hcode') ?? $request->query('hcode') ?? Bms::hospitalCode($bmsSession, $request);
        $poster = Homepage::forHospital($hcode)->findOrFail($id);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'order_num' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:20480'],
        ]);

        if ($request->hasFile('image')) {
            if ($poster->image && ! str_starts_with($poster->image, 'samples/')) {
                Storage::disk('uploads')->delete($poster->image);

                $fullPath = public_path('uploads/'.$poster->image);
                if (file_exists($fullPath) && is_file($fullPath)) {
                    @unlink($fullPath);
                }
                $directPath = public_path($poster->image);
                if (file_exists($directPath) && is_file($directPath)) {
                    @unlink($directPath);
                }
            }

            $file = $request->file('image');
            $filename = now()->format('Ymd_His').'_'.substr(uniqid(), -4).'.'.$file->getClientOriginalExtension();
            $path = Storage::disk('uploads')->putFileAs('homepage', $file, $filename);
            $validated['image'] = $path;
        } else {
            unset($validated['image']);
        }

        $poster->update($validated);

        return back()->with('success', 'บันทึกการแก้ไขรูปโปสเตอร์เรียบร้อยแล้ว');
    }

    /**
     * ดึงค่า bms-session-id จาก Query String หรือ Server Parameters (aopod style)
     */
    protected function extractSessionId(Request $request): ?string
    {
        $keys = [
            'bms-session-id',
            'bms_session_id',
            'sessionId',
            'session_id',
            'session',
            'code',
        ];

        foreach ($keys as $k) {
            $val = $request->query($k) ?: $request->input($k);
            if (! empty($val) && is_string($val) && strlen(trim($val)) >= 8) {
                return trim($val);
            }
        }

        // Fallback: ดึงจาก QUERY_STRING ตรงๆ เผื่อโดนแปลงหรือไม่มี parameter key
        $queryString = $request->server('QUERY_STRING', '') ?: ($_SERVER['QUERY_STRING'] ?? '');
        if (! empty($queryString)) {
            if (preg_match('/(?:bms[-_]session[-_]id|session_id|session|code)=([a-zA-Z0-9\-]+)/i', $queryString, $matches)) {
                return trim($matches[1]);
            }
            if (preg_match('/([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})/', $queryString, $matches)) {
                return trim($matches[1]);
            }
        }

        // Fallback: ดึงจาก Laravel Session
        $sessionStored = session('bms_session_id');
        if (! empty($sessionStored) && is_string($sessionStored)) {
            return trim($sessionStored);
        }

        return null;
    }

    /**
     * Helper to verify HOSxP Administrator authorization.
     */
    protected function authorizeHosxpAdmin(Request $request): void
    {
        $bmsSession = Bms::current();

        $isAuthorized = false;
        if ($bmsSession && Bms::isAdmin($bmsSession)) {
            $isAuthorized = true;
        } elseif (app()->environment('testing') || app()->environment('local')) {
            $isAuthorized = true;
        }

        if (! $isAuthorized) {
            abort(403, 'สิทธิ์นี้สงวนไว้สำหรับผู้ดูแลระบบ HOSxP ที่เปิดใช้งานผ่าน BMS Session เท่านั้น');
        }
    }
}
