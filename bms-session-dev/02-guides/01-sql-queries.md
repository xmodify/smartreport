# 01. SQL Query ผ่าน `/api/sql` แบบสมบูรณ์ (SQL Queries Guide)

> **อ้างอิงจากบทเรียน:** [01-sql-queries.md บน BMS Session Dev Portal](https://bms-session-dev.bmscloud.in.th/chapters/sql-queries.html)  
> ครอบคลุมโครงสร้าง Request ครบทั้ง 4 ฟิลด์, Named Parameter Binding, ชนิดข้อมูล 7 แบบ, ตาราง Blacklist และข้อควรระวังข้ามระบบฐานข้อมูล

---

## 1. ขอบเขตและหน้าที่ของ `/api/sql`

`/api/sql` ถูกออกแบบให้เป็น **Read-Only API** สำหรับการอ่านข้อมูล ดึงสรุปรายงาน และประมวลผล Query ซับซ้อน
* **คำสั่ง SQL ที่อนุญาต:** `SELECT`, `SHOW`, `DESCRIBE`, `EXPLAIN`, `WITH`
* **คำสั่งที่ไม่อนุญาต:** `INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER`, `CREATE` (Gateway จะปฏิเสธทันทีด้วยรหัสข้อผิดพลาด)

---

## 2. โครงสร้าง Request ครบทั้ง 4 ฟิลด์

การส่งคำขอแบบ HTTP POST ไปยัง `{bms_url}/api/sql` ประกอบด้วย 4 ฟิลด์หลักใน JSON Body:

```json
{
  "sql": "SELECT p.hn, p.pname, p.fname, p.lname, o.vstdate FROM ovst o JOIN patient p ON p.hn = o.hn WHERE o.vstdate = :vstdate AND o.main_dep = :dep ORDER BY o.vsttime DESC LIMIT 100",
  "app": "smartdata",
  "params": {
    "vstdate": {
      "value": "2026-10-25",
      "value_type": "date"
    },
    "dep": {
      "value": "01",
      "value_type": "string"
    }
  },
  "marketplace-token": "mkt_987654321abcdef"
}
```

### รายละเอียดแต่ละฟิลด์:
1. **`sql` (String, บังคับ):** คำสั่ง SQL ที่ต้องการรัน
2. **`app` (String, บังคับ):** ชื่อ Addon หรือแอปพลิเคชัน เช่น `"smartdata"` หรือ `"BMS.MyAddon"` (หากไม่ส่งจะติดข้อผิดพลาด `"Missing app parameter"`)
3. **`params` (Object, ไม่บังคับ):** Object ของพารามิเตอร์ที่ต้องการ Bind เข้ากับ named placeholder (`:param_name`)
4. **`marketplace-token` (String, ไม่บังคับ):** Token `mkt_...` ส่งมาเมื่อต้องการปลด Mask ข้อมูลอ่อนไหว

---

## 3. การ Bind พารามิเตอร์อย่างปลอดภัย (`:param_name`)

> [!CAUTION]
> **ห้ามต่อ String ค่าพารามิเตอร์เข้าไปในคำสั่ง SQL โดยตรงเด็ดขาด** เพราะเสี่ยงต่อ SQL Injection ให้ใช้ Named Placeholder รูปแบบ `:param_name` เสมอ

### ชนิดข้อมูล `value_type` ทั้ง 7 แบบที่รองรับ:

| `value_type` | รูปแบบข้อมูล (Format) | ตัวอย่างค่า (Example) | หมายเหตุ |
| :--- | :--- | :--- | :--- |
| **`string`** | ตัวอักษรความยาวสั้น-ปานกลาง | `"0001234"` | เหมาะสำหรับรหัส HN, VN, ID |
| **`text`** | ข้อความขนาดยาว | `"ประวัติการแพ้ยาและอาการเบื้องต้น..."` | ใช้สำหรับ Note และคำบรรยาย |
| **`integer`** | ตัวเลขจำนวนเต็ม | `42` หรือ `"42"` | ตัวนับ, รหัสกลุ่ม, สถานะ |
| **`float`** | ตัวเลขทศนิยม | `3.14` หรือ `75.5` | น้ำหนัก, ส่วนสูง, ค่ายา |
| **`date`** | `YYYY-MM-DD` | `"2026-10-25"` | วันที่รับบริการ, วันเกิด |
| **`time`** | `HH:mm:ss` | `"14:30:00"` | เวลาที่มารับบริการ |
| **`datetime`** | `YYYY-MM-DDTHH:mm:ss` | `"2026-10-25T14:30:00"` | วันและเวลาสมบูรณ์ |

> [!TIP]
> **การจัดการค่า NULL อัตโนมัติ:** หากส่งค่าพารามิเตอร์เป็นค่าว่าง (`""`, `null` หรือไม่ได้ส่งคีย์มา) BMS Gateway จะทำการแปลงเป็น `SQL NULL` ให้โดยอัตโนมัติ ไม่จำเป็นต้องเขียนโค้ดตรวจสอบ `null/undefined` เอง

---

## 4. โครงสร้าง Response ของ `/api/sql`

```json
{
  "result": [],
  "MessageCode": 200,
  "Message": "OK",
  "RequestTime": "2026-10-25T21:00:00.000Z",
  "data": [
    {
      "hn": "0001234",
      "pname": "นาย",
      "fname": "[MASKED]",
      "lname": "[MASKED]",
      "vstdate": "2026-10-25"
    }
  ],
  "field": [6, 6, 6, 6, 10],
  "field_name": ["hn", "pname", "fname", "lname", "vstdate"],
  "record_count": 1,
  "truncated": false,
  "result_limit_bytes": 52428800,
  "field_masked": [false, false, true, true, false]
}
```

* **`data`:** Array ของ Object ข้อมูลที่ได้จากการรันคำสั่ง (แนะนำให้อ่านจาก `data` เป็นหลัก)
* **`field_name`:** รายชื่อคอลัมน์ทั้งหมดตามลำดับ
* **`field`:** รหัสประเภทข้อมูลของคอลัมน์ (เช่น 2=integer, 6=string, 10=date/datetime)
* **`field_masked`:** Boolean Array บอกสถานะว่าคอลัมน์ใดกำลังถูก Mask ข้อมูลอยู่
* **`record_count`:** จำนวนแถวทั้งหมดในผลลัพธ์รอบนี้

---

## 5. ตารางต้องห้าม (Blacklisted Tables)

เพื่อความปลอดภัยสูงสุดของระบบโรงพยาบาล BMS Gateway จะทำการบล็อกคำสั่ง SQL ที่มีการอ้างอิงถึงตารางที่มีข้อมูลความลับหรือรหัสผ่านระบบทันที (จะได้รับ `MessageCode: 400` หรือ `"SQL Validation Failed"`):

* ❌ `opduser` (ตารางผู้ใช้งานระบบและรหัสผ่าน)
* ❌ `doctor_password`, `user_password`
* ❌ ตารางบันทึกการเข้ารหัสภายใน

> [!IMPORTANT]
> **การตรวจสอบสิทธิ์ผู้ใช้งาน:** หากต้องการตรวจสอบข้อมูลผู้ใช้หรือสิทธิ์ผู้ดูแลระบบ ให้ใช้ตาราง **`officer`**, **`officer_group_list`**, และ **`officer_group`** แทนตาราง `opduser` เสมอ

---

## 6. แนวทางเขียน SQL ข้ามระบบฐานข้อมูล (Cross-Database Compatibility)

เนื่องจาก HOSxP สามารถติดตั้งได้ทั้งบน **MySQL**, **MariaDB** และ **PostgreSQL**:
1. **หลีกเลี่ยงการใช้ Backticks (\`):** อย่าครอบชื่อตารางหรือคอลัมน์ด้วย backtick เพราะ PostgreSQL ไม่รองรับ (ให้ใช้ ANSI Standard หรือเขียนชื่อตรงๆ)
2. **ใช้ฟังก์ชันวันที่มาตรฐาน:** เช่น `CURRENT_DATE()` หรือ `NOW()` ที่ทำงานได้ตรงกันทุกระบบ
3. **ใส่ `LIMIT` ทุกครั้ง:** จำกัดจำนวนแถวผลลัพธ์ที่ต้องการเสมอ (เช่น `LIMIT 100`) เพื่อป้องกัน Query ขนาดใหญ่ทำให้ Gateway หรือเครือข่ายของโรงพยาบาลหมดเวลา (Timeout)
