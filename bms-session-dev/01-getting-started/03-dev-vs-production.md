# 03. โหมด Development กับ Production (Dev vs. Production)

> **อ้างอิงจากบทเรียน:** [03-dev-vs-prod.md บน BMS Session Dev Portal](https://bms-session-dev.bmscloud.in.th/chapters/dev-vs-prod.html)  
> อธิบายความแตกต่างของพฤติกรรมระบบระหว่างโหมดพัฒนาและโหมดใช้งานจริง, กฎ Read-Only และระบบปกปิดข้อมูลอ่อนไหว (Data Masking)

---

## 1. ตารางเปรียบเทียบความแตกต่าง

| มิติการทำงาน | Development Mode (ไม่มี Marketplace Token) | Production Mode (มี Marketplace Token `mkt_...`) |
| :--- | :--- | :--- |
| **สิทธิ์การอ่าน (Read)** | อ่านได้ผ่าน `/api/sql` และ `GET /api/rest` | อ่านได้ผ่านทุก Endpoint |
| **สิทธิ์การเขียน (Write)** | **ถูกปฏิเสธ (403 Forbidden)** | เขียนได้ (POST, PUT, DELETE บน `/api/rest`) ตามสิทธิ์ของ Addon |
| **การปกปิดข้อมูล (Data Masking)** | **เปิดใช้งานอัตโนมัติ** (ชื่อ, เบอร์โทร, CID ถูกแทนด้วย `[MASKED]`) | **แสดงข้อมูลจริง (Unmask)** สำหรับเจ้าหน้าที่ที่ได้รับอนุญาต |
| **อายุของ Session** | มักจะสั้น (~36,000 วินาที หรือ 10 ชั่วโมง) | ยาวตามที่ HOSxP กำหนด (สูงสุด 2,592,000 วินาที หรือ 30 วัน) |
| **การคิดค่าบริการ (Billing)** | ไม่มีการตัดเครดิต | ตัดเครดิตตาม Usage Telemetry ที่รายงานไปยัง Marketplace |

---

## 2. ระบบปกปิดข้อมูลอ่อนไหว (Automated Data Masking)

ในโหมด Development ระบบความปลอดภัยของ BMS Gateway จะตรวจสอบทุกฟิลด์ที่ส่งออกจากฐานข้อมูล หากพบว่าเป็นข้อมูลระบุตัวบุคคล (Personally Identifiable Information: PII) ระบบจะแปลงเป็น `[MASKED]` อัตโนมัติ:

```json
{
  "officer_id": 55,
  "officer_name": "[MASKED]",
  "officer_login_name": "1341800003078",
  "officer_active": "Y"
}
```

และใน Response Header/Payload จะมีส่วน metadata ด้าน Privacy กำกับ:
```json
"privacy": {
  "policy_version": "1",
  "masking_applied": true,
  "mask_token": "[MASKED]",
  "disclosure_status": "SENSITIVE_MASKED",
  "reason": "USER_AUTHORIZATION_REQUIRED"
}
```

> [!NOTE]
> ฟิลด์ที่เป็นรหัสอ้างอิง เช่น `hn`, `vn`, `officer_id`, `officer_login_name` จะไม่ถูก Mask เพื่อให้นักพัฒนาสามารถนำไปใช้เขียนตรรกะเชื่อมโยงข้อมูลในระบบได้ตามปกติ

---

## 3. การปลดล็อคเข้าสู่โหมด Production

ในการขึ้นระบบ Production เพื่อเขียนข้อมูลจริงและปลด Mask:
1. นำ Addon ไปลงทะเบียนใน **HOSxP Marketplace Portal** (`https://hosxp-marketplace.bmscloud.in.th`) เพื่อรับ API Key ประจำ Addon (`hxp_...`)
2. โรงพยาบาลทำการ Activate Addon ในระบบ
3. Addon ส่งคำขอแลกเปลี่ยน Token:
   ```http
   POST https://hosxp-marketplace.bmscloud.in.th/api/v1/addon/token
   X-Addon-Key: hxp_...
   Content-Type: application/json

   {
     "bms-session-id": "A504F016-19D8-453F-B0AB-DEB5BADBB3E0"
   }
   ```
4. ได้รับ Token ชั่วคราว `mkt_...` (อายุ 15 นาที / 900 วินาที)
5. ส่ง Token นี้ไปพร้อมกับ Request ที่ต้องการเขียนข้อมูลผ่าน Header `X-Marketplace-Token: mkt_...` หรือใน Request Body `{"marketplace-token": "mkt_..."}`
