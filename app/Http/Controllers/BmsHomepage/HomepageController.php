<?php

namespace App\Http\Controllers\BmsHomepage;

use App\Http\Controllers\Controller;
use App\Models\Homepage;
use App\Services\Hosxp\HosxpDatabase;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HomepageController extends Controller
{
    protected const DB_ERROR_MESSAGE = 'ไม่สามารถเชื่อมต่อฐานข้อมูล HOSxP';

    protected const ADMIN_SESSION_KEY = 'homepage_admin';

    public function __construct(protected HosxpDatabase $hosxp) {}

    /**
     * Display the BMS Homepage (One-Page Split Screen: Reports & Posters).
     */
    public function index(Request $request): Response
    {
        // 1. Resolve hospital directly from the HOSxP database
        $dbConnected = false;
        $dbHospitalCode = null;
        $dbHospitalName = null;

        try {
            $dbHospitalCode = $this->hosxp->hospitalCode();
            $dbHospitalName = $dbHospitalCode ? $this->hosxp->hospitalName($dbHospitalCode) : null;
            $dbConnected = true;
        } catch (\Throwable $e) {
            Log::warning('HOSxP database connection failed: '.$e->getMessage());
        }

        $hospitalCode = $this->resolveHospitalCode($request, $dbHospitalCode);
        $hospitalName = $dbHospitalName
            ?? config('homepage.default_hname')
            ?? ($dbConnected ? null : self::DB_ERROR_MESSAGE);

        $canManageAdmin = $this->isHomepageAdmin($request);

        // 2. Query ONLY active posters belonging to this hospital for the main carousel
        $posters = Homepage::where('is_active', true)
            ->forHospital($hospitalCode)
            ->orderBy('order_num')
            ->latest()
            ->get();

        // 3. Query all posters for this hospital if admin mode (for poster manager)
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

        // 4. Load HOSxP Clinic Service Stats & Real-time queue metrics
        $initialDate = now()->format('Y-m-d');
        if ($dbConnected) {
            $latestDate = $this->getLatestActiveOvstDate();
            if ($latestDate && ! $this->hasActiveVisitsOnDate($initialDate)) {
                $initialDate = $latestDate;
            }
        }

        $clinicStats = $this->fetchClinicStatsData($initialDate);
        $realtimeData = $this->fetchRealtimeCurDepData();
        $queueHeatmap = $this->fetchQueueHeatmapData($initialDate);

        // Hospital Metrics for left dashboard
        $hosxpData = [
            'hospital_code' => $hospitalCode,
            'hospital_name' => $hospitalName,
            'today_date' => now()->locale('th')->isoFormat('D MMMM GGGG'),
            'opd_total' => $clinicStats['total_patients'] ?? 0,
            'top_departments' => $clinicStats['departments'] ?? [],
        ];

        return Inertia::render('BmsHomepage/Index', [
            'posters' => $posters,
            'hospitalCode' => $hospitalCode,
            'hospitalName' => $hospitalName,
            'hosxpData' => $hosxpData,
            'wardCensus' => $this->fetchWardCensus(),
            'appointmentData' => $this->fetchAppointmentData(now()->format('Y-m-d')),
            'clinicStats' => $clinicStats,
            'realtimeData' => $realtimeData,
            'queueHeatmap' => $queueHeatmap,
            'canManageAdmin' => $canManageAdmin,
            'adminName' => $canManageAdmin ? $request->session()->get(self::ADMIN_SESSION_KEY.'.name') : null,
            'adminPosters' => $adminPosters,
            'adminStats' => $adminStats,
            'dbConnected' => $dbConnected,
            'dbError' => $dbConnected ? null : self::DB_ERROR_MESSAGE,
        ]);
    }

    /**
     * Hospital code from the request (?hcode=), else HOSxP opdconfig, else config default.
     */
    protected function resolveHospitalCode(Request $request, ?string $dbHospitalCode = null): ?string
    {
        $requested = $request->query('hcode') ?? $request->query('hospital_code') ?? $request->input('hcode');
        if (! empty($requested)) {
            return (string) $requested;
        }

        if ($dbHospitalCode === null) {
            try {
                $dbHospitalCode = $this->hosxp->hospitalCode();
            } catch (\Throwable $e) {
                Log::warning('HOSxP hospital code lookup failed: '.$e->getMessage());
            }
        }

        return $dbHospitalCode ?? config('homepage.default_hcode');
    }

    /**
     * Poster management is limited to HOSxP administrators who signed in on this page.
     */
    protected function isHomepageAdmin(Request $request): bool
    {
        return $request->session()->has(self::ADMIN_SESSION_KEY);
    }

    /**
     * Sign in with a HOSxP login; only active members of the HOSxP "ผู้ดูแลระบบ" group are accepted.
     */
    public function adminLogin(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login_name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        try {
            $administrator = $this->hosxp->authenticateAdministrator($credentials['login_name'], $credentials['password']);
        } catch (\Throwable $e) {
            Log::warning('HOSxP administrator login failed: '.$e->getMessage());

            return back()->withErrors(['login_name' => self::DB_ERROR_MESSAGE]);
        }

        if ($administrator === null) {
            return back()->withErrors(['login_name' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง หรือไม่ใช่ผู้ดูแลระบบ HOSxP']);
        }

        $request->session()->regenerate();
        $request->session()->put(self::ADMIN_SESSION_KEY, $administrator);

        return redirect()->route('homepage.index')->with('success', "เข้าสู่ระบบผู้ดูแล: {$administrator['name']}");
    }

    /**
     * Leave the administrator mode.
     */
    public function adminLogout(Request $request): RedirectResponse
    {
        $request->session()->forget(self::ADMIN_SESSION_KEY);

        return redirect()->route('homepage.index')->with('info', 'ออกจากโหมดผู้ดูแลระบบแล้ว');
    }

    /**
     * Count patients currently admitted (not yet discharged) in each monitored ward.
     *
     * @return array<int, array{ward: string, name: string, count: int}>
     */
    protected function fetchWardCensus(): array
    {
        /** @var array<int, string> $wardCodes */
        $wardCodes = array_values(config('homepage.census_wards', ['03', '01', '10']));

        $census = array_map(fn (string $code): array => [
            'ward' => $code,
            'name' => "Ward {$code}",
            'count' => 0,
        ], $wardCodes);

        $safeCodes = array_filter($wardCodes, fn (string $code): bool => preg_match('/^[A-Za-z0-9]+$/', $code) === 1);
        if ($safeCodes === []) {
            return $census;
        }
        $inList = "'".implode("','", $safeCodes)."'";

        $sql = "SELECT w.ward, w.name, COUNT(i.an) AS total
                FROM ward w
                LEFT JOIN ipt i ON i.ward = w.ward AND i.dchdate IS NULL
                WHERE w.ward IN ({$inList})
                GROUP BY w.ward, w.name";

        try {
            $rows = $this->hosxp->select($sql, [], count($safeCodes));
        } catch (\Throwable $e) {
            Log::warning('HOSxP ward census query failed: '.$e->getMessage());

            return $census;
        }

        $byWard = [];
        foreach ($rows as $row) {
            $byWard[(string) ($row['ward'] ?? '')] = $row;
        }

        return array_map(function (array $item) use ($byWard): array {
            $row = $byWard[$item['ward']] ?? null;
            if ($row !== null) {
                $item['name'] = (string) ($row['name'] ?? $item['name']);
                $item['count'] = (int) ($row['total'] ?? 0);
            }

            return $item;
        }, $census);
    }

    /**
     * API endpoint to fetch appointments (oapp) grouped by clinic and doctor for a date.
     */
    public function getAppointments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return response()->json($this->fetchAppointmentData($validated['date'] ?? now()->format('Y-m-d')));
    }

    /**
     * Fetch appointment counts per clinic and doctor for an appointment date (oapp.nextdate).
     *
     * @return array<string, mixed>
     */
    public function fetchAppointmentData(string $date): array
    {
        $thaiDate = Carbon::parse($date)->locale('th')->translatedFormat('j M ').((int) Carbon::parse($date)->format('Y') + 543);

        $sql = 'SELECT c.name AS clinic, d.name AS doctor, COUNT(o.vn) AS oapp
                FROM oapp o
                LEFT JOIN clinic c ON c.clinic = o.clinic
                LEFT JOIN doctor d ON d.code = o.doctor
                WHERE o.nextdate = :next_date
                GROUP BY o.clinic, o.doctor, c.name, d.name
                ORDER BY oapp DESC';

        try {
            $rows = $this->hosxp->select($sql, ['next_date' => $date], 1000);
        } catch (\Throwable $e) {
            Log::warning('HOSxP appointment query failed: '.$e->getMessage());

            return [
                'success' => false,
                'error' => self::DB_ERROR_MESSAGE,
                'selected_date' => $date,
                'thai_date' => $thaiDate,
                'total' => 0,
                'clinics' => [],
            ];
        }

        $clinics = [];
        $total = 0;
        foreach ($rows as $row) {
            $clinicName = (string) ($row['clinic'] ?? null ?: 'ไม่ระบุคลินิก');
            $count = (int) ($row['oapp'] ?? 0);

            $clinics[$clinicName] ??= ['name' => $clinicName, 'total' => 0, 'doctors' => []];
            $clinics[$clinicName]['doctors'][] = [
                'name' => (string) ($row['doctor'] ?? null ?: 'ไม่ระบุแพทย์'),
                'count' => $count,
            ];
            $clinics[$clinicName]['total'] += $count;
            $total += $count;
        }

        return [
            'success' => true,
            'selected_date' => $date,
            'thai_date' => $thaiDate,
            'total' => $total,
            'clinics' => array_values($clinics),
        ];
    }

    /**
     * API endpoint to fetch HOSxP Clinic Service Workload Statistics dynamically by date.
     */
    public function getClinicStats(Request $request): JsonResponse
    {
        $date = (string) $request->query('date', now()->format('Y-m-d'));

        return response()->json($this->fetchClinicStatsData($date));
    }

    /**
     * API endpoint to fetch OPD Queue & Real-time cur_dep dynamically.
     */
    public function getQueueHeatmap(Request $request): JsonResponse
    {
        $date = (string) $request->query('date', now()->format('Y-m-d'));

        return response()->json([
            'realtime' => $this->fetchRealtimeCurDepData(),
            'heatmap' => $this->fetchQueueHeatmapData($date),
        ]);
    }

    /**
     * Fetch real-time active waiting patients by department from ovst.cur_dep.
     *
     * @return array<string, mixed>
     */
    public function fetchRealtimeCurDepData(): array
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

        try {
            // 1. Live query for today: ovst.cur_dep
            $rows = $this->hosxp->select($sqlToday, [], 100);

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
                $rows = $this->hosxp->select($sqlLatest, [], 20);
            }
        } catch (\Throwable $e) {
            Log::warning('Real-time ovst.cur_dep query failed: '.$e->getMessage());

            return [
                'total_waiting' => 0,
                'departments' => [],
                'is_live' => false,
                'error' => self::DB_ERROR_MESSAGE,
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
                'name' => $this->displayDepartmentName($r['department'] ?? null, 'ไม่ระบุแผนก'),
                'depcode' => (string) ($r['depcode'] ?? ''),
                'count' => $count,
                'status' => $count >= 30 ? 'หนาแน่น' : ($count >= 15 ? 'ปานกลาง' : 'ปกติ'),
                'status_color' => $count >= 30 ? 'red' : ($count >= 15 ? 'amber' : 'emerald'),
            ];
        }

        return [
            'total_waiting' => $totalWaiting,
            'departments' => $formattedDepartments,
            'is_live' => true,
            'query_time' => now()->format('H:i:s'),
            'sql' => $sqlToday,
        ];
    }

    /**
     * Fetch and format OPD Queue Heatmap data from the HOSxP database.
     *
     * @return array<string, mixed>
     */
    public function fetchQueueHeatmapData(string $date): array
    {
        $rawRows = [];
        $isLive = false;

        try {
            $sql = "SELECT k.department, HOUR(q.queue_datetime) AS hr, COUNT(*) AS q
                    FROM opd_dep_queue q
                    LEFT JOIN kskdepartment k ON k.depcode = q.depcode
                    WHERE DATE(q.queue_datetime) = :selected_date
                      AND q.depcode <> '999'
                    GROUP BY k.department, HOUR(q.queue_datetime)
                    ORDER BY k.department, hr";

            $rawRows = $this->hosxp->select($sql, ['selected_date' => $date], 1000);
            if (! empty($rawRows)) {
                $isLive = true;
            }
        } catch (\Throwable $e) {
            Log::warning('HOSxP queue heatmap query failed: '.$e->getMessage());
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
            $dept = $this->displayDepartmentName($row['department'] ?? null, 'ไม่ระบุจุดบริการ');
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
    public function fetchClinicStatsData(string $date): array
    {
        try {
            $carbon = Carbon::parse($date);
            $thYear = (int) $carbon->format('Y') + 543;
            $thaiDate = $carbon->locale('th')->translatedFormat('j F ').$thYear;
        } catch (\Throwable $e) {
            $thaiDate = $date;
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
            $rawRows = $this->hosxp->select($sql, ['selected_date' => $date], 100);
            $totalRes = $this->hosxp->select($sqlTotal, ['selected_date' => $date], 1);
            $totalOpd = ! empty($totalRes[0]['total']) ? (int) $totalRes[0]['total'] : array_sum(array_column($rawRows, 'count'));

            $maxCount = ! empty($rawRows) ? max(array_column($rawRows, 'count')) : 1;

            $departments = array_map(function ($row) use ($maxCount, $totalOpd) {
                $count = (int) ($row['count'] ?? 0);

                return [
                    'name' => $this->displayDepartmentName($row['name'] ?? null, 'ไม่ระบุชื่อคลินิก'),
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
            Log::warning('HOSxP clinic stats query failed: '.$e->getMessage());

            return [
                'success' => false,
                'error' => self::DB_ERROR_MESSAGE,
                'selected_date' => $date,
                'thai_date' => $thaiDate,
                'is_live' => false,
                'total_patients' => 0,
                'departments' => [],
            ];
        }
    }

    /**
     * Department name shown on the page, applying homepage.department_aliases.
     */
    protected function displayDepartmentName(?string $name, string $fallback): string
    {
        if ($name === null || $name === '') {
            return $fallback;
        }

        return (string) (config('homepage.department_aliases', [])[$name] ?? $name);
    }

    /**
     * Check if a given date has active visits in ovst.
     */
    protected function hasActiveVisitsOnDate(string $date): bool
    {
        try {
            return ! empty($this->hosxp->select('SELECT vn FROM ovst WHERE vstdate = :vstdate LIMIT 1', ['vstdate' => $date], 1));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get latest active visit date from ovst.
     */
    protected function getLatestActiveOvstDate(): ?string
    {
        try {
            $rows = $this->hosxp->select('SELECT MAX(vstdate) AS latest_date FROM ovst', [], 1);

            return ! empty($rows[0]['latest_date']) ? (string) $rows[0]['latest_date'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Store new poster images uploaded by an administrator.
     */
    public function storePoster(Request $request): RedirectResponse
    {
        $this->authorizeHomepageAdmin($request);

        $request->validate([
            'image' => ['nullable', 'image', 'max:20480'],
            'images.*' => ['nullable', 'image', 'max:20480'],
        ]);

        $hcode = $this->resolveHospitalCode($request);

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
        $this->authorizeHomepageAdmin($request);

        $poster = Homepage::forHospital($this->resolveHospitalCode($request))->findOrFail($id);
        $poster->is_active = ! $poster->is_active;
        $poster->save();

        return back()->with('success', 'เปลี่ยนสถานะการแสดงผลเรียบร้อยแล้ว');
    }

    /**
     * Delete a poster record and physical file.
     */
    public function deletePoster(Request $request, int $id): RedirectResponse
    {
        $this->authorizeHomepageAdmin($request);

        $poster = Homepage::forHospital($this->resolveHospitalCode($request))->findOrFail($id);

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
        $this->authorizeHomepageAdmin($request);

        $poster = Homepage::forHospital($this->resolveHospitalCode($request))->findOrFail($id);

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
     * Abort with 403 unless the request comes from an authorized workstation.
     */
    protected function authorizeHomepageAdmin(Request $request): void
    {
        if (! $this->isHomepageAdmin($request)) {
            abort(403, 'สิทธิ์นี้สงวนไว้สำหรับผู้ดูแลระบบเท่านั้น');
        }
    }
}
