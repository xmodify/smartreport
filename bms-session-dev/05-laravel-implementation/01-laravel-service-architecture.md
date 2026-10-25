# 01. สถาปัตยกรรม Service Layer ใน Laravel (SmartData Implementation)

> **การประยุกต์ใช้งานจริง:** คู่มือการออกแบบและโค้ดต้นแบบของ Service Layer ใน Laravel (PHP 8.2+) ที่นำหลักการของ BMS Session Dev Portal ไปสร้างระบบจริงในโครงการนี้

---

## 1. ผังโครงสร้างโฟลเดอร์ใน Laravel

```
app/
├── Http/
│   ├── Controllers/
│   │   └── HomepageController.php       # รับ bms-session-id, ประเมินสิทธิ์ และส่งข้อมูลให้ Vue
│   └── Middleware/
│       └── BmsAdminMiddleware.php       # ป้องกัน Route /admin/* ด้วยสิทธิ์ BMS Session /api/sql
├── Models/
│   └── BmsSession.php                   # Model จัดการตาราง bms_sessions พร้อม Longest Remaining Scope
└── Services/
    └── Bms/
        ├── Bms.php                      # Unified Gateway Facade (Bms::session(), Bms::data(), Bms::ai())
        ├── BmsSessionService.php        # Resolve Session, Device Key และจัดการวงจรชีวิต
        ├── BmsDataService.php           # ส่ง Query ผ่าน /api/sql, /api/rest, /api/function, ตรวจสอบ Admin
        ├── BmsAiService.php             # เชื่อมต่อ Central AI Gateway (LLM, TTS, ASR, ICD-11, OCR)
        └── BmsMarketplaceService.php    # จัดการ mkt_ Token, Cloud KV Store และ Telemetry Billing
config/
└── bms.php                              # กำหนดค่า Endpoints, Timeouts, และโมเดล AI เริ่มต้น
```

---

## 2. การเรียกใช้งานผ่าน `Bms` Facade Gateway

เราสามารถเรียกใช้บริการทั้งหมดของ BMS ได้ผ่าน `App\Services\Bms\Bms`:

```php
use App\Services\Bms\Bms;

// 1. ดึง Active Session ของเครื่องที่เหลือเวลามากที่สุด
$session = Bms::current();

// 2. ตรวจสอบสิทธิ์ว่าผู้ใช้นี้เป็นผู้ดูแลระบบใน HOSxP หรือไม่ (กลุ่ม 19 สถานะเปิดใช้งาน)
$isAdmin = Bms::isAdmin($session);

// 3. รันคำสั่ง SQL ผ่าน HOSxP Gateway
$results = Bms::data()->executeSql($session, "SELECT COUNT(*) as total FROM patient");

// 4. ค้นหารหัสโรค ICD-11 อัตโนมัติด้วย AI
$icd11 = Bms::ai()->lookupIcd11($session->bms_session_id, "ผู้ป่วยมีอาการไข้สูง ปวดศีรษะ");

// 5. ปรับภาษาพูดแพทย์เป็นคำอ่านเต็ม
$cleanText = Bms::ai()->normalizeText($session->bms_session_id, "รับยา 2 เม็ด วันละ 3 ครั้ง");
```

---

## 3. การเลือก Session ที่เหลือเวลาเยอะที่สุด (`longestRemaining`)

ใน `app/Models/BmsSession.php` ได้สร้าง Query Scope พิเศษเพื่อรองรับกรณีที่ผู้ใช้เปิดใช้งานจากหลายเบราว์เซอร์:

```php
public function scopeLongestRemaining(Builder $query): Builder
{
    return $query->where('expires_at', '>', Carbon::now())
        ->orderByDesc('expires_at');
}
```

---

## 4. ระบบตรวจสอบสิทธิ์ Admin แบบ Zero-Trust ผ่าน `/api/sql`

ใน `app/Services/Bms/BmsDataService.php` ฟังก์ชัน `isHosxpAdmin` จะส่งคำสั่ง SQL ไปยัง Gateway ของโรงพยาบาลเพื่อตรวจเช็คสิทธิ์แบบ Real-time โดยไม่เปิดพอร์ตหรือเชื่อมต่อฐานข้อมูลตรง:

```php
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
      AND (o.officer_login_name = '{$escapedUserId}' OR o.officer_cid = '{$escapedUserId}')
      AND o.officer_active = 'Y'
    LIMIT 1
";
```

---

## 5. การตั้งเวลาทำความสะอาด Session หมดอายุอัตโนมัติ (Scheduler)

ใน `routes/console.php`:
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('bms:prune-sessions')->everyTenMinutes();
Schedule::command('model:prune', ['--model' => [App\Models\BmsSession::class]])->hourly();
```
และสั่งรันได้ผ่านคำสั่ง Artisan:
```bash
php artisan bms:prune-sessions
```
