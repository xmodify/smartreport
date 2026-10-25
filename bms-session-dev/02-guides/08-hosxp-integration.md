# 08. การฝัง Addon ในหน้าจอ HOSxP (HOSxP UI Integration Guide)

> **อ้างอิงจากบทเรียน:** [08-hosxp-integration.md บน BMS Session Dev Portal](https://bms-session-dev.bmscloud.in.th/chapters/hosxp-integration.html)  
> ครอบคลุมรูปแบบ URL ที่ HOSxP ส่งมา, บริบทผู้ป่วย (`hn`, `vn`, `an`), หน้าจอไหนส่งอะไร, และกับดักเลข 0 นำหน้า

---

## 1. รูปแบบ URL ที่ HOSxP ส่งมายัง Addon

เมื่อผู้ใช้กดเรียก Addon จากภายในโปรแกรม HOSxP (ไม่ว่าจะเปิดผ่าน Internal Browser / Chromium Tab หรือเปิดผ่าน External Web Browser) HOSxP จะต่อ Query String อัตโนมัติ:

```http
https://your-addon-domain.com/?bms-session-id=A504F016-19D8-453F-B0AB-DEB5BADBB3E0&hn=0001234&vn=670101123456
```

---

## 2. กฎเหล็ก 2 ข้อในการรับค่าพารามิเตอร์

### ⚠️ กฎข้อที่ 1: ชื่อพารามิเตอร์เป็น "ตัวพิมพ์เล็ก" เสมอ
HOSxP จะส่งชื่อพารามิเตอร์มาเป็นตัวพิมพ์เล็กเสมอ:
* `bms-session-id` (หรือบางหน้าจออาจส่ง `bms_session_id`)
* `hn` (Hospital Number - รหัสผู้ป่วย)
* `vn` (Visit Number - รหัสการรับบริการ OPD)
* `an` (Admission Number - รหัสการรับบริการ IPD)

### ⚠️ กฎข้อที่ 2: กับดักเลข 0 นำหน้า (The Leading Zero Trap)
> [!CRITICAL]
> **ห้ามแปลง `hn`, `vn`, `an` เป็นตัวเลข (Integer) เด็ดขาด**  
> ในฐานข้อมูล HOSxP ฟิลด์ `hn` มักมีความยาว 7-9 หลัก และมักมีเลข 0 นำหน้าเสมอ เช่น `"0001234"` หรือ `"0543210"` หากคุณแปลงเป็น Integer (เช่น `(int) $request->hn`) ค่าจะกลายเป็น `1234` ซึ่งเมื่อนำไป Query จะหาผู้ป่วยไม่พบทันที **ต้องคงสถานะเป็น String เสมอ**

---

## 3. หน้าจอของ HOSxP และบริบทที่ส่งมา

| หน้าจอจุดเชื่อมต่อใน HOSxP | พารามิเตอร์ที่ HOSxP แนบมาใน URL | การนำไปใช้ |
| :--- | :--- | :--- |
| **หน้าจอต้อนรับ / หน้าหลัก (Welcome Form)** | `?bms-session-id=...` | แสดงแดชบอร์ดภาพรวม, ข่าวประชาสัมพันธ์, ปุ่มเข้าสู่ระบบ |
| **ห้องตรวจแพทย์ผู้ป่วยนอก (Doctor OPD)** | `?bms-session-id=...&hn=0001234&vn=670101123456` | แสดงประวัติการรักษา, สรุปผลแล็บ, ผู้ช่วย AI แนะนำรหัสโรค ICD-11 |
| **จุดคัดกรอง / ซักประวัติ (Nurse Screen)** | `?bms-session-id=...&hn=0001234&vn=670101123456` | บันทึกสัญญาณชีพ (Vital Signs), ซักประวัติอาการสำคัญ |
| **หอผู้ป่วยใน (IPD Nurse Ward)** | `?bms-session-id=...&hn=0001234&an=67000123` | แสดงสถานะเตียง, คำสั่งแพทย์ (Doctor Orders), บันทึกการให้ยา |
| **ห้องยาและการเงิน (Pharmacy & Cashier)** | `?bms-session-id=...&vn=670101123456&hn=0001234` | ตรวจสอบรายการยา, ยอดค่าใช้จ่าย, การพิมพ์ฉลาก |
