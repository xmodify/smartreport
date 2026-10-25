# 05. User Data Storage: Cloud Key-Value Store (User Storage Guide)

> **อ้างอิงจากบทเรียน:** [05-user-storage.md บน BMS Session Dev Portal](https://bms-session-dev.bmscloud.in.th/chapters/user-storage.html)  
> ครอบคลุมการจัดเก็บข้อมูลการตั้งค่าส่วนบุคคลของผู้ใช้บน Cloud Key-Value Store แยกตาม User, Hospital และ Addon

---

## 1. ขอบเขตและคุณสมบัติของ Cloud KV Storage

**BMS User Data Storage** เป็นบริการจัดเก็บข้อมูล Key-Value บนคลาวด์สำหรับ Addon โดยมีขอบเขตและข้อกำหนดดังนี้:
* **การแบ่งขอบเขต (Isolation):** ข้อมูลจะถูกแยกอิสระตาม `[hospital_code] + [addon_key] + [user_id]` ผู้ใช้คนอื่นหรือ Addon อื่นจะไม่สามารถมองเห็นข้อมูลนี้ได้
* **ความยาวของ Key:** สูงสุด 128 ตัวอักษร (แนะนำให้ใช้ภาษาอังกฤษ, ตัวเลข, ขีดกลาง หรือจุด เช่น `ui.theme`, `doctor.default_template`)
* **ขนาดข้อมูลและ Quota:** รองรับข้อมูลประเภท JSON, String, Number หรือ Array ขนาดรวมสูงสุด **5 MB ต่อผู้ใช้ต่อ Addon**
* **ระบบ Versioning (Optimistic Concurrency Control):** ป้องกันการเขียนทับข้อมูลจากหลายหน้าต่างด้วยฟิลด์ `expectedVersion`

---

## 2. การอ่านข้อมูล (`GET /api/v1/addon/storage/{key}`)

```http
GET https://hosxp-marketplace.bmscloud.in.th/api/v1/addon/storage/dashboard_config
X-BMS-Session-Id: A504F016-19D8-453F-B0AB-DEB5BADBB3E0
X-Marketplace-Token: hxp_1a2b3c4d5e6f7g8h
```

### การตอบกลับเมื่อพบข้อมูล
```json
{
  "success": true,
  "key": "dashboard_config",
  "value": {
    "theme": "dark",
    "refresh_interval": 30,
    "favorite_clinics": ["01", "03"]
  },
  "version": 3,
  "updated_at": "2026-10-25T20:30:00Z"
}
```
*(หากไม่พบคีย์ดังกล่าว ระบบจะตอบกลับด้วย `HTTP 404 Not Found`)*

---

## 3. การบันทึก/แก้ไขข้อมูล (`PUT /api/v1/addon/storage/{key}`)

```http
PUT https://hosxp-marketplace.bmscloud.in.th/api/v1/addon/storage/dashboard_config
X-BMS-Session-Id: A504F016-19D8-453F-B0AB-DEB5BADBB3E0
Content-Type: application/json

{
  "marketplace_token": "hxp_1a2b3c4d5e6f7g8h",
  "value": {
    "theme": "dark",
    "refresh_interval": 60,
    "favorite_clinics": ["01", "03", "05"]
  },
  "expectedVersion": 3
}
```

### การตอบกลับเมื่อบันทึกสำเร็จ
```json
{
  "success": true,
  "key": "dashboard_config",
  "version": 4,
  "message": "Storage saved successfully"
}
```

> [!TIP]
> หากส่ง `expectedVersion` ไม่ตรงกับเวอร์ชันปัจจุบันบนคลาวด์ (มีหน้าจออื่นแก้ไขไปก่อน) ระบบจะตอบกลับด้วย **`HTTP 409 Conflict`** เพื่อป้องกันข้อมูลสูญหาย

---

## 4. การลบข้อมูล (`DELETE /api/v1/addon/storage/{key}`)

```http
DELETE https://hosxp-marketplace.bmscloud.in.th/api/v1/addon/storage/dashboard_config
X-BMS-Session-Id: A504F016-19D8-453F-B0AB-DEB5BADBB3E0
X-Marketplace-Token: hxp_1a2b3c4d5e6f7g8h
```
