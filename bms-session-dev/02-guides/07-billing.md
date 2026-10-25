# 07. เครดิตและการเรียกเก็บเงิน (Billing & Credit Management)

> **อ้างอิงจากบทเรียน:** [07-billing.md บน BMS Session Dev Portal](https://bms-session-dev.bmscloud.in.th/chapters/billing.html)  
> ครอบคลุมการจัดการเครดิต, การรายงานปริมาณการใช้งาน Addon, และการรับมือข้อผิดพลาดเมื่อโควตาเครดิตไม่เพียงพอ

---

## 1. แบบจำลองเครดิตและการคิดค่าบริการ

ในการใช้งานบริการระดับพรีเมียมบน BMS Ecosystem (เช่น AI Gateway, Medical OCR, หรือบริการเฉพาะของ Addon):
* โรงพยาบาลจะเติมเครดิตไว้ในบัญชีกลางของโรงพยาบาลผ่าน BMS Portal
* Addon สามารถรายงานการใช้ทรัพยากรกลับไปยัง Marketplace ผ่าน API
* หากเครดิตของโรงพยาบาลหมด ระบบ AI Gateway จะส่งกลับข้อผิดพลาด **`HTTP 402 Insufficient Balance`**

---

## 2. การส่งรายงานปริมาณการใช้งาน (`POST /api/v1/addon/usage`)

เมื่อ Addon ดำเนินการให้บริการที่มีค่าใช้จ่ายเสร็จสิ้น ให้รายงานข้อมูลผ่าน Marketplace API:

```http
POST https://hosxp-marketplace.bmscloud.in.th/api/v1/addon/usage
X-Addon-Key: hxp_1a2b3c4d5e6f7g8h
Content-Type: application/json

{
  "bms-session-id": "A504F016-19D8-453F-B0AB-DEB5BADBB3E0",
  "event_type": "ai_inference_tokens",
  "units": 1500,
  "timestamp": "2026-10-25T21:00:00Z",
  "metadata": {
    "module": "opd_summary",
    "doctor_code": "001"
  }
}
```

### การตอบกลับเมื่อสำเร็จ
```json
{
  "success": true,
  "transaction_id": "txn_884920410",
  "remaining_credits": 4520,
  "recorded_at": "2026-10-25T21:00:01Z"
}
```
