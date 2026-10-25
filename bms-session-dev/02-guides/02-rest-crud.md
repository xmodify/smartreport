# 02. REST CRUD ผ่าน `/api/rest` แบบสมบูรณ์ (REST CRUD Guide)

> **อ้างอิงจากบทเรียน:** [02-rest-crud.md บน BMS Session Dev Portal](https://bms-session-dev.bmscloud.in.th/chapters/rest-crud.html)  
> ครอบคลุมการอ่านข้อมูลระดับตาราง, ตัวดำเนินการ Filter ทั้ง 9 แบบ, การ Join/Expand, และการเขียนข้อมูลอย่างปลอดภัยด้วย Marketplace Token

---

## 1. ขอบเขตและหน้าที่ของ `/api/rest`

`/api/rest` เป็น Table-level CRUD API ที่สร้างขึ้นสำหรับเข้าถึงและจัดการข้อมูลในตาราง HOSxP โดยตรง
* **Endpoint Pattern:** `{bms_url}/api/rest/{table}[/{id}]`
* **Headers พื้นฐาน:**
  - `Authorization: Bearer {bms_session_code}`
  - `Content-Type: application/json`
  - `X-Marketplace-Token: {mkt_token}` *(จำเป็นเมื่อต้องการเขียนข้อมูล)*

---

## 2. การอ่านข้อมูล (GET Request)

### Query Parameters ที่รองรับ:
* **`filter`:** เงื่อนไขการกรองข้อมูล
* **`sort`:** จัดเรียงข้อมูล เช่น `vstdate:desc,vsttime:desc`
* **`limit`:** จำนวนแถวสูงสุดต่อรอบ (ค่าเริ่มต้น 50 หรือ 100)
* **`offset`:** ลำดับเริ่มต้น (สำหรับการทำ Pagination)
* **`expand` / `join`:** รวมข้อมูลจากตารางสัมพันธ์

---

## 3. ตัวดำเนินการ Filter ครบทั้ง 9 แบบ

รูปแบบไวยากรณ์: `filter={field}:{operator}:{value}` (คั่นหลายเงื่อนไขด้วยเครื่องหมาย `,`)

| ตัวดำเนินการ (Operator) | ความหมาย | ตัวอย่างการใช้งาน |
| :--- | :--- | :--- |
| **`eq`** | เท่ากับ (Equal) | `filter=hn:eq:0001234` |
| **`ne`** | ไม่เท่ากับ (Not Equal) | `filter=an:ne:null` |
| **`gt`** | มากกว่า (Greater Than) | `filter=age_y:gt:60` |
| **`gte`** | มากกว่าหรือเท่ากับ (>=) | `filter=vstdate:gte:2026-10-01` |
| **`lt`** | น้อยกว่า (Less Than) | `filter=bw:lt:2500` |
| **`lte`** | น้อยกว่าหรือเท่ากับ (<=) | `filter=vstdate:lte:2026-10-25` |
| **`like`** | ค้นหาคล้ายคลึง (Wildcard) | `filter=fname:like:สม%` |
| **`in`** | อยู่ในชุดรายการ (IN List) | `filter=main_dep:in:01,02,03` |
| **`is_null`** | ค่าว่าง (IS NULL) | `filter=death_date:is_null` |

---

## 4. การ Join / Expand ข้อมูลตารางสัมพันธ์

เพื่อลดจำนวนรอบของ HTTP Request (N+1 query problem) สามารถใช้พารามิเตอร์ `expand` หรือ `join` เพื่อดึงข้อมูลตารางลูกมาพร้อมกันในคำขอเดียว:

```http
GET {bms_url}/api/rest/patient?filter=hn:eq:0001234&expand=ovst
```

### ผลลัพธ์ที่ได้:
```json
{
  "data": [
    {
      "hn": "0001234",
      "pname": "นาย",
      "fname": "[MASKED]",
      "lname": "[MASKED]",
      "ovst": [
        {
          "vn": "670101123456",
          "vstdate": "2026-10-25",
          "vsttime": "08:30:00"
        }
      ]
    }
  ],
  "MessageCode": 200,
  "Message": "OK"
}
```

---

## 5. การเขียนข้อมูล (POST, PUT, DELETE)

> [!WARNING]
> **การเขียนข้อมูลต้องมี Marketplace Token เสมอ:**  
> หากส่งคำขอ `POST`, `PUT` หรือ `DELETE` โดยไม่มี Token `mkt_...` ระบบจะปฏิเสธคำขอทันทีด้วย **`HTTP 403 Forbidden`** (`Write access requires active marketplace token`)

### 5.1 การเพิ่มข้อมูลใหม่ (POST)
```http
POST {bms_url}/api/rest/patient_note
Authorization: Bearer {bms_session_code}
X-Marketplace-Token: mkt_987654321abcdef
Content-Type: application/json

{
  "hn": "0001234",
  "note_text": "ผู้ป่วยมีประวัติแพ้ยาเพนิซิลลิน",
  "record_date": "2026-10-25"
}
```

### 5.2 การอัปเดตข้อมูลเดิม (PUT)
```http
PUT {bms_url}/api/rest/patient_note/105
Authorization: Bearer {bms_session_code}
X-Marketplace-Token: mkt_987654321abcdef
Content-Type: application/json

{
  "note_text": "อัปเดต: ผู้ป่วยมีประวัติแพ้ยาเพนิซิลลินและซัลฟา"
}
```

### 5.3 การลบข้อมูล (DELETE)
```http
DELETE {bms_url}/api/rest/patient_note/105
Authorization: Bearer {bms_session_code}
X-Marketplace-Token: mkt_987654321abcdef
```

---

## 6. ตารางบันทึกประวัติการเขียนข้อมูล (`bms_ops_usage_log`)

ทุกครั้งที่มีการเขียนข้อมูลผ่าน `/api/rest` ระบบ Gateway ของโรงพยาบาลจะทำการบันทึก Audit Log ลงในตาราง `bms_ops_usage_log` อัตโนมัติ เพื่อให้ผู้ดูแลระบบโรงพยาบาลสามารถตรวจสอบย้อนหลังได้ว่า Addon ตัวใด ผู้ใช้คนใด ทำการแก้ไขข้อมูลอะไร ในเวลาใด
