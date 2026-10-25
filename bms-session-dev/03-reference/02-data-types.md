# 02. ชนิดข้อมูล, รหัสข้อผิดพลาด และข้อจำกัด (Data Types & Error Reference)

> **อ้างอิงจากบทเรียน:** [02-data-types.md บน BMS Session Dev Portal](https://bms-session-dev.bmscloud.in.th/chapters/data-types.html)  
> ครอบคลุมรหัสประเภทข้อมูลใน Field Array, ชนิดข้อมูลสำหรับ Parameter Binding, ความหมายของ MessageCode และรายการตารางต้องห้าม

---

## 1. รหัสประเภทข้อมูลในคอลัมน์ (Field Type Codes)

ในผลลัพธ์ของ `/api/sql` จะมี Array `field` ที่ระบุประเภทข้อมูลของแต่ละคอลัมน์ใน `field_name`:

| รหัสตัวเลข | ชนิดข้อมูลจริง (SQL Data Type) | ตัวอย่างการแสดงผลใน JSON |
| :--- | :--- | :--- |
| **`1`** | `BOOLEAN` | `true`, `false` |
| **`2`** | `INTEGER` / `SMALLINT` | `55`, `104` |
| **`3`** | `BIGINT` | `1341800003078` |
| **`4`** | `FLOAT` / `DOUBLE` | `72.5` |
| **`5`** | `DECIMAL` / `CURRENCY` | `150.00` |
| **`6`** | `VARCHAR` / `CHAR` / `TEXT` | `"สมชาย"`, `"0001234"` |
| **`10`** | `DATE` / `DATETIME` | `"2026-10-25"`, `"2026-10-25 08:30:00"` |
| **`11`** | `TIME` | `"08:30:00"` |
| **`12`** | `BLOB` / `LONGTEXT` | ข้อมูลไบนารีเข้ารหัส Base64 หรือข้อความยาว |

---

## 2. ชนิดข้อมูลสำหรับ Parameter Binding (`value_type`)

เมื่อส่ง Parameter ในฟิลด์ `params` ของ `/api/sql` ให้ระบุ `value_type` ให้ตรงกับ 1 ใน 7 ชนิดนี้:
1. **`string`:** ข้อความทั่วไป, รหัส HN, รหัส VN, เลขบัตรประชาชน
2. **`text`:** ข้อความบรรยายยาวหลายบรรทัด
3. **`integer`:** ตัวเลขจำนวนเต็ม
4. **`float`:** ตัวเลขทศนิยม
5. **`date`:** วันที่ในรูปแบบ `YYYY-MM-DD`
6. **`time`:** เวลาในรูปแบบ `HH:mm:ss`
7. **`datetime`:** วันเวลาในรูปแบบ `YYYY-MM-DDTHH:mm:ss`

---

## 3. ความหมายของ MessageCode และ HTTP Status

| HTTP Status | MessageCode | ความหมาย (Description) | สาเหตุและแนวทางแก้ไข |
| :--- | :--- | :--- | :--- |
| **200 OK** | **200** | สำเร็จ (Success) | คำขอทำงานเรียบร้อย ดึงข้อมูลได้จากฟิลด์ `data` |
| **400 Bad Request** | **400** | Missing parameter / SQL Validation Failed | • ลืมส่งฟิลด์ `app`<br>• อ้างอิงตารางต้องห้าม (Blacklist)<br>• ไวยากรณ์ SQL ไม่ถูกต้อง |
| **401 Unauthorized** | **401** | Invalid authorization | • Header `Authorization: Bearer` ไม่ถูกต้องหรือว่างเปล่า<br>• Session ID หมดอายุ |
| **403 Forbidden** | **403** | Permission denied | • พยายามส่งคำสั่งเขียน (POST/PUT/DELETE) โดยไม่มี Token `mkt_...` |
| **404 Not Found** | **404** | Endpoint / Table not found | • ชื่อตารางหรือฟังก์ชันไม่มีอยู่ในระบบ |
| **409 Conflict** | **409** | Database error / Storage version conflict | • MySQL แจ้ง Error เช่น คอลัมน์ไม่มีอยู่จริง (`#42S22Unknown column`)<br>• Version ชนกันใน Cloud KV Store |
| **500 Error** | **500** | Internal server error | • การเชื่อมต่อฐานข้อมูล HOSxP ภายในขัดข้อง |
| **503 Unavailable**| - | Service Temporarily Unavailable | • PasteJSON `hosxp.net` กำลังปิดปรับปรุง (ให้สลับใช้ Local Gateway) |

---

## 4. รายการตารางต้องห้าม (Blacklisted Tables)

Gateway จะปฏิเสธการ Query ทันทีหากตรวจพบชื่อตารางต่อไปนี้ในคำสั่ง SQL:
* `opduser` (ตารางผู้ใช้งานระบบและ Hash รหัสผ่าน)
* `doctor_password`
* `user_password`
* `sys_security_key`
