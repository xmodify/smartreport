# คู่มือการติดตั้ง SmartReport

SmartReport คือหน้าแรกของโรงพยาบาลที่เปิดจาก HOSxP (Welcome Screen) แสดงจำนวนผู้ป่วยใน (Admit) ตาม ward, สถิติผู้ป่วยนอกตามคลินิก และโปสเตอร์ประชาสัมพันธ์ ระบบ **อ่านข้อมูลจากฐานข้อมูล HOSxP (MySQL) โดยตรง** ไม่ต้องใช้ BMS Session

- 1 เซิร์ฟเวอร์ต่อ 1 ฐานข้อมูล HOSxP (ถ้ามีหลายโรงพยาบาล ให้ติดตั้งแยกชุดกัน)
- ฐานข้อมูลของตัวแอปเอง (โปสเตอร์, ผู้ดูแลระบบ) เป็น SQLite ในไฟล์ `database/database.sqlite`
- ระบบ **อ่านอย่างเดียว** ไม่เขียนอะไรลงฐาน HOSxP

---

## 1. สิ่งที่ต้องมีบนเซิร์ฟเวอร์

| รายการ | เวอร์ชัน/หมายเหตุ |
|---|---|
| PHP | 8.3 ขึ้นไป พร้อม extension: `pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `curl`, `xml`, `ctype`, `tokenizer`, `gd` หรือ `imagick` (ใช้ตรวจรูปที่อัปโหลด) |
| Web server | Apache (มี `mod_rewrite`) หรือ Nginx ชี้เข้าโฟลเดอร์ `public/` |
| Composer 2 | ใช้เฉพาะวิธี git clone และกรณีไม่ copy โฟลเดอร์ `vendor` ไป |
| Node.js 20+ และ npm | ใช้เฉพาะตอน build หน้าเว็บ (`npm run build`) ไม่จำเป็นบนเครื่อง production ถ้า build มาแล้ว |
| Git | เฉพาะวิธี git clone |
| เครือข่าย | เซิร์ฟเวอร์ต้องต่อ MySQL ของ HOSxP ได้ (ปกติพอร์ต 3306) |

> ตัวอย่างในคู่มือนี้ใช้ Windows + XAMPP (โฟลเดอร์ `C:\xampp\htdocs`) ส่วน Linux ให้เปลี่ยน path ตามเครื่อง

---

## 2. เตรียมผู้ใช้ฐานข้อมูล HOSxP (ทำครั้งเดียว)

แนะนำให้สร้าง user ที่มีสิทธิ์ **อ่านอย่างเดียว** สำหรับระบบนี้ ที่ MySQL ของ HOSxP:

```sql
CREATE USER 'smartreport'@'IP_เซิร์ฟเวอร์SmartReport' IDENTIFIED BY 'รหัสผ่านที่ปลอดภัย';
GRANT SELECT ON ชื่อฐานHOSxP.* TO 'smartreport'@'IP_เซิร์ฟเวอร์SmartReport';
FLUSH PRIVILEGES;
```

ถ้า SmartReport อยู่เครื่องเดียวกับ MySQL ให้ใช้ `'smartreport'@'127.0.0.1'` และตรวจว่าไฟร์วอลล์/`bind-address` ของ MySQL อนุญาตให้เชื่อมต่อได้

ตารางที่ระบบอ่าน: `opdconfig`, `hospcode`, `ovst`, `kskdepartment`, `opd_dep_queue`, `ipt`, `ward`

---

## 3. วิธี A: ติดตั้งด้วย git clone

```bat
cd C:\xampp\htdocs
git clone https://github.com/xmodify/smartreport.git
cd smartreport

composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

> `public/build` (ไฟล์หน้าเว็บที่ build แล้ว) **ไม่อยู่ใน git** ต้องรัน `npm run build` เอง ถ้าเซิร์ฟเวอร์ไม่มี Node ให้ build บนเครื่องอื่นแล้ว copy โฟลเดอร์ `public/build` ไปวางที่เซิร์ฟเวอร์

จากนั้นไปทำ **ขั้นตอนที่ 5 (ตั้งค่า `.env`)** และขั้นตอนถัดๆ ไป

### อัปเดตเวอร์ชันภายหลัง

```bat
cd C:\xampp\htdocs\smartreport
git pull
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
```

---

## 4. วิธี B: copy โฟลเดอร์ไปติดตั้ง

ใช้เมื่อเซิร์ฟเวอร์ปลายทางไม่มีอินเทอร์เน็ต หรือไม่มี Git/Composer/Node

**บนเครื่องต้นทาง (เครื่องพัฒนา) ให้เตรียมให้พร้อม:**

```bat
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

**copy โฟลเดอร์โปรเจกต์ทั้งหมดไปที่ปลายทาง** (เช่น `C:\xampp\htdocs\smartreport`) โดย **ไม่ต้อง copy**:

| ไม่ต้อง copy | เหตุผล |
|---|---|
| `.env` | แต่ละเครื่องต้องมีค่าของตัวเอง (มีรหัสผ่านฐานข้อมูล) |
| `node_modules/` | ไม่ใช้บน production (หน้าเว็บ build แล้วอยู่ใน `public/build`) |
| `.git/` | ไม่จำเป็น |
| `database/database.sqlite` | จะสร้างใหม่ด้วย migrate (ข้อมูลโปสเตอร์/ผู้ใช้ของเครื่องต้นทางไม่ควรติดไป) |
| `storage/logs/*.log` | log ของเครื่องต้นทาง |
| `public/uploads/homepage/*` | รูปโปสเตอร์ของเครื่องต้นทาง |

**ต้อง copy:** โฟลเดอร์ `vendor/` และ `public/build/` ด้วย (ตัวอย่างเช่น zip ทั้งโฟลเดอร์แล้วลบรายการข้างบนก่อน)

> เวอร์ชัน PHP ของปลายทางต้องเป็น 8.3 ขึ้นไป และมี extension ตามหัวข้อ 1 ถ้า PHP เวอร์ชันต่างจากต้นทางมาก ให้รัน `composer install --no-dev` ที่ปลายทางแทนการ copy `vendor`

จากนั้นไปทำ **ขั้นตอนที่ 5** เป็นต้นไป

---

## 5. ตั้งค่า `.env` (ทั้งสองวิธี)

```bat
cd C:\xampp\htdocs\smartreport
copy .env.example .env
php artisan key:generate
```

เปิดไฟล์ `.env` แก้ค่าต่อไปนี้:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=http://IP_หรือชื่อเซิร์ฟเวอร์/smartreport

# ฐานข้อมูล HOSxP (อ่านอย่างเดียว)
DB_HOST_2=192.168.x.x
DB_PORT_2=3306
DB_DATABASE_2=ชื่อฐานHOSxP
DB_USERNAME_2=smartreport
DB_PASSWORD_2=รหัสผ่านที่ตั้งในขั้นตอนที่ 2

# ผู้ที่จัดการโปสเตอร์ได้ (IP ของเครื่อง เว้นด้วยจุลภาค)
HOMEPAGE_ADMIN_IPS=127.0.0.1,::1,192.168.x.x

# รหัส ward ที่แสดงเป็นการ์ด 3 ใบ เรียงจากซ้ายไปขวา (เขียว เหลือง แดง)
HOMEPAGE_CENSUS_WARDS=03,01,10
```

| ตัวแปร | อธิบาย |
|---|---|
| `APP_URL` | ที่อยู่ที่ผู้ใช้เปิดจริง (ต้องมี `/smartreport` ถ้าติดตั้งแบบ Alias ตามหัวข้อ 7) |
| `DB_*_2` | การเชื่อมต่อ MySQL ของ HOSxP |
| `HOMEPAGE_ADMIN_IPS` | ปุ่ม "จัดการโปสเตอร์" จะขึ้นเฉพาะเครื่องที่มี IP อยู่ในรายการ (ค่าเริ่มต้นคือเฉพาะตัวเซิร์ฟเวอร์เอง) |
| `HOMEPAGE_CENSUS_WARDS` | รหัส `ward.ward` ของ ward ที่ต้องการแสดง แต่ละ รพ. รหัสไม่เหมือนกัน ตรวจด้วย `SELECT ward, name FROM ward` |
| `HOMEPAGE_DEFAULT_HCODE`, `HOMEPAGE_DEFAULT_HNAME` | (ไม่บังคับ) รหัสและชื่อ รพ. สำรอง ใช้เมื่อต่อฐาน HOSxP ไม่ได้ ปกติระบบอ่านจาก `opdconfig` เอง |

> หลังแก้ `.env` ทุกครั้ง ให้รัน `php artisan config:clear`

---

## 6. สร้างฐานข้อมูลของแอปและผู้ดูแลระบบ

```bat
type nul > database\database.sqlite
php artisan migrate --force --seed
```

- สร้างตารางโปสเตอร์/ผู้ใช้ และผู้ดูแลระบบเริ่มต้น `admin@bms.local` / `admin1234`
- **ต้องเปลี่ยนรหัสผ่านนี้ทันที** ที่ production (เข้าที่ `/login`)
- ไม่มีโปสเตอร์ตัวอย่างติดมา โปสเตอร์จะเพิ่มผ่านปุ่ม "จัดการโปสเตอร์" ในหน้าแรก

**สิทธิ์การเขียนไฟล์** (ให้ผู้ใช้ที่รัน Apache/PHP เขียนได้):

- `storage/` (และโฟลเดอร์ย่อยทั้งหมด)
- `bootstrap/cache/`
- `database/` (ทั้งโฟลเดอร์และไฟล์ `database.sqlite`)
- `public/uploads/homepage/` (รูปโปสเตอร์ที่อัปโหลด)

เมื่อทุกอย่างเรียบร้อย ให้เพิ่มความเร็วด้วย:

```bat
php artisan optimize
```

---

## 7. ตั้งค่า Web server

### Apache (XAMPP) แบบ Alias

เพิ่มใน `httpd.conf` หรือไฟล์ vhost แล้วรีสตาร์ท Apache:

```apache
Alias /smartreport "C:/xampp/htdocs/smartreport/public"
<Directory "C:/xampp/htdocs/smartreport/public">
    AllowOverride All
    Require all granted
</Directory>
```

หน้าเว็บจะอยู่ที่ `http://IP/smartreport/bms-homepage` (ตรงกับ `APP_URL` ข้างบน)

### Nginx

ชี้ `root` ไปที่โฟลเดอร์ `public` ตามคู่มือมาตรฐานของ Laravel (`try_files $uri $uri/ /index.php?$query_string;`) และถ้าต้องการให้อยู่ใต้ path `/smartreport` ให้ตั้ง `location /smartreport` ให้สอดคล้องกับ `APP_URL`

---

## 8. ตั้งค่าใน HOSxP ให้เปิดหน้านี้

ที่ HOSxP ตั้ง URL ของ Welcome Screen (หน้า DashboardURLSettings) เป็น:

```
http://IP_เซิร์ฟเวอร์/smartreport/bms-homepage
```

ไม่ต้องแนบ `bms-session-id` ระบบนี้ไม่ใช้ BMS Session แล้ว

---

## 9. ตรวจสอบหลังติดตั้ง

1. เปิด `http://IP_เซิร์ฟเวอร์/smartreport/bms-homepage` ต้องเห็นชื่อโรงพยาบาล, ป้ายเขียว **"เชื่อมต่อฐานข้อมูล HOSxP"**, การ์ด ward 3 ใบ และสถิติคลินิก
2. จากเครื่องที่อยู่ใน `HOMEPAGE_ADMIN_IPS` ต้องเห็นปุ่ม "จัดการโปสเตอร์" ลองอัปโหลดรูป 1 รูป
3. เปิดจากเครื่องอื่น (ที่ไม่อยู่ใน `HOMEPAGE_ADMIN_IPS`) ต้องไม่เห็นปุ่มจัดการโปสเตอร์
4. เปิดจาก HOSxP จริง ต้องแสดงผลเหมือนกัน

---

## 10. แก้ปัญหาที่พบบ่อย

| อาการ | สาเหตุ / วิธีแก้ |
|---|---|
| ขึ้น "ไม่สามารถเชื่อมต่อฐานข้อมูล HOSxP" | ตรวจ `DB_*_2` ใน `.env`, ไฟร์วอลล์/พอร์ต 3306, สิทธิ์ของ user ต่อ IP ของเซิร์ฟเวอร์, และรัน `php artisan config:clear` ดูสาเหตุจริงใน `storage/logs/laravel.log` (บรรทัด `HOSxP database connection failed`) |
| ขึ้น `could not find driver` | ยังไม่เปิด `pdo_mysql` หรือ `pdo_sqlite` ใน `php.ini` (ตัด `;` หน้า `extension=pdo_mysql`, `extension=pdo_sqlite`) แล้วรีสตาร์ท Apache |
| หน้าขาว / ไม่มี CSS และปุ่มไม่ทำงาน | ยังไม่มีโฟลเดอร์ `public/build` (ดูวิธีสร้างในหัวข้อ 3) หรือ `APP_URL` ไม่ตรงกับที่เปิดจริง |
| ขึ้น `Unable to locate file in Vite manifest` | เหมือนข้างบน ให้รัน `npm run build` |
| 500 Server Error | ดู `storage/logs/laravel.log` และตรวจสิทธิ์เขียน `storage/`, `bootstrap/cache/`, `database/` |
| ไม่เห็นปุ่มจัดการโปสเตอร์ | IP ของเครื่องไม่อยู่ใน `HOMEPAGE_ADMIN_IPS` (ถ้าผ่านพร็อกซี/Load balancer ต้องตั้ง trusted proxy ของ Laravel เพื่อให้ได้ IP จริง) |
| อัปโหลดรูปไม่ได้ | ตรวจสิทธิ์เขียน `public/uploads/homepage/` และค่า `upload_max_filesize` / `post_max_size` ใน `php.ini` (รูปสูงสุด 20 MB ต่อไฟล์) |
| การ์ด ward ขึ้น "Ward 03" และเป็น 0 | รหัส ward ใน `HOMEPAGE_CENSUS_WARDS` ไม่มีในตาราง `ward` ของ รพ. นี้ |
| ข้อมูลภาษาไทยเป็นเครื่องหมาย ? | ตั้ง `DB_CHARSET_2` ให้ตรงกับ charset ของฐาน HOSxP (เช่น `tis620` หรือ `utf8mb4`) แล้ว `php artisan config:clear` |

---

## 11. สำรองข้อมูล

ข้อมูลที่ควรสำรองเป็นประจำ:

- `database/database.sqlite` (รายการโปสเตอร์, ผู้ดูแลระบบ)
- `public/uploads/homepage/` (รูปโปสเตอร์)
- `.env`
