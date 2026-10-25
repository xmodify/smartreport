# 01. ภาพรวมระบบและ Token 4 ชนิด (System Overview & 4 Tokens)

> **อ้างอิงจากบทเรียน:** [01-overview.md บน BMS Session Dev Portal](https://bms-session-dev.bmscloud.in.th/chapters/overview.html)  
> ครอบคลุมภาพรวมระบบ HOSxP, BMS Session, HOSxP Marketplace, บริการ AI และการตั้งค่า Environment ทดสอบด้วย BMS Dev Portal

---

## 1. ขอบเขตและสถาปัตยกรรมของระบบ

**HOSxP** เป็นระบบบริหารจัดการโรงพยาบาล (Hospital Information System: HIS) ที่ครอบคลุมงานหลักทั้งหมด ได้แก่ งานเวชระเบียน, ผู้ป่วยนอก (OPD), ผู้ป่วยใน (IPD), ห้องปฏิบัติการ (Lab), งานเภสัชกรรม (Rx) และการเงิน โดยข้อมูลจะถูกจัดเก็บอยู่ในฐานข้อมูลของแต่ละโรงพยาบาล (MySQL, MariaDB หรือ PostgreSQL)

ในการพัฒนาเว็บแอปพลิเคชันหรือ Addon เชื่อมต่อกับ HOSxP:
* **ไม่ควรเชื่อมต่อตรงกับฐานข้อมูล MySQL ของโรงพยาบาล** (เพื่อความปลอดภัย, ความเข้ากันได้ของระบบเครือข่าย, และการปฏิบัติตามมาตรฐาน PDPA)
* **BMS Session API** ทำหน้าที่เป็นสะพานเชื่อมต่อผ่าน HTTP Gateway/Tunnel ที่ปลอดภัย โดยจะสร้าง `bms-session-id` ผูกกับเวิร์กสเตชันและผู้ใช้ที่ล็อกอินอยู่ในขณะนั้น

---

## 2. Token ทั้ง 4 ชนิดในระบบ

BMS Ecosystem แยกหน้าที่ของ Credential ออกเป็น 4 ชนิดอย่างชัดเจน:

```mermaid
classDiagram
    class BMS_Session_ID {
        +UUID/String
        +รับจาก URL: ?bms-session-id=...
        +ใช้ Resolve หา bms_url ผ่าน PasteJSON
        +ใช้เป็น Bearer ตรงกับ BMS Central AI Gateway
        +อายุตาม expired_second
    }
    class BMS_Session_Code {
        +Opaque Token
        +ได้จากผลลัพธ์ JSON ของ PasteJSON
        +ใช้เป็น Bearer สำหรับ {bms_url}/api/*
        +ห้ามแก้หรือ parse โครงสร้างภายใน
    }
    class HXP_API_Key {
        +String hxp_...
        +ได้จากการลงทะเบียน Marketplace Addon
        +ใช้ยืนยันสิทธิ์ Addon กับ Marketplace
        +ใช้ขอ mkt_ Token ชั่วคราว
    }
    class MKT_Access_Token {
        +String mkt_...
        +อายุสั้น ~15 นาที (900s)
        +ปลดล็อคสิทธิ์เขียนข้อมูล REST (Read-Write)
        +ปลดล็อค Data Masking ข้อมูลผู้ป่วย
    }
```

### รายละเอียด Token และข้อกำหนดการใช้งาน

| ชนิด Token | รูปแบบ / Prefix | แหล่งที่มา | ปลายทางที่นำไปใช้ | วัตถุประสงค์และขอบเขตสิทธิ์ |
| :--- | :--- | :--- | :--- | :--- |
| **1. `bms-session-id`** | String / UUID | HOSxP ส่งมาทาง Query String เมื่อเปิด Addon | • `https://hosxp.net/phps/bms_session_paste.php`<br>• `https://ai-api.kube.bmscloud.in.th` | • Resolve หา Tunnel Gateway ของโรงพยาบาล<br>• ยืนยันสิทธิ์เรียก BMS AI Gateway (LLM, TTS, ASR, ICD-11, OCR) |
| **2. `bms_session_code`** | Opaque String | ได้จาก JSON เมื่อ Resolve `bms-session-id` สำเร็จ | • `{bms_url}/api/sql`<br>• `{bms_url}/api/rest`<br>• `{bms_url}/api/function` | • Bearer Token ประจำตัวสำหรับเรียก Data API ทั้งหมดของโรงพยาบาล<br>• ส่งใน Header `Authorization: Bearer <bms_session_code>` |
| **3. `hxp_...`** | `hxp_` + Hex | ได้รับจาก HOSxP Marketplace Portal ประจำตัว Addon | • `https://hosxp-marketplace.bmscloud.in.th` | • ยืนยันตัวตน Addon Developer<br>• ใช้ขอ `mkt_...` Token และส่งรายงานสรุปการใช้งาน (Telemetry/Billing) |
| **4. `mkt_...`** | `mkt_` + Hex | ขอจาก Marketplace API (`POST /api/v1/addon/token`) | • `{bms_url}/api/rest` (Header `X-Marketplace-Token`) | • **ปลดล็อคสิทธิ์เขียนข้อมูล (Write/Update/Delete)**<br>• ปลด Mask ข้อมูลผู้ป่วย (แสดงข้อมูลจริง) |

> [!IMPORTANT]
> **อายุของ Session (`expired_second`):**  
> ห้าม Hardcode อายุของ Session ในโปรแกรม ให้ดึงค่าจากฟิลด์ `expired_second` ในผลลัพธ์ PasteJSON ของ Session ปัจจุบันเสมอ (โหมด Dev มักจะอยู่ที่ 36,000 วินาที / 10 ชั่วโมง ส่วน Production มักอยู่ที่ 2,592,000 วินาที / 30 วัน)

---

## 3. การเตรียม Environment ทดสอบด้วย BMS Dev Portal

หากยังไม่มีการติดตั้ง HOSxP ของโรงพยาบาลจริง นักพัฒนาสามารถใช้ **BMS Dev Portal – HOSxP mORMot API Test** สำหรับการพัฒนาและทดสอบบนเครื่อง Local:

* **ดาวน์โหลด:** [https://cloud3.hosxp.net/bms_app/BMSDevPortalHOSxPTest-Setup.zip](https://cloud3.hosxp.net/bms_app/BMSDevPortalHOSxPTest-Setup.zip)
* **การทำงาน:** เป็นโปรแกรมแบบ Portable ทำงานร่วมกับ Embedded MariaDB (`libmysqld`) และ mORMot API Server ใน Process เดียวกัน
* **สิ่งที่โปรแกรมจัดเตรียมให้ทันที:**
  1. Local API Server: `http://127.0.0.1:45011/api`
  2. Cloud Tunnel Gateway อัตโนมัติ: เช่น `https://DEV99-admin-manoi-m7730.tunnel.hosxp.net`
  3. `bms-session-id` และ `bms_session_code` สำหรับทดสอบ
  4. แท็บทดสอบ: `Authorization`, `HOSxP_GetDataSet`, `ExecuteSQL`, และ `portal_log.txt`

> [!TIP]
> **ข้อกำหนดการทำงาน:** ต้องเชื่อมต่ออินเทอร์เน็ตเพื่อให้โปรแกรมเชื่อมต่อไปยัง BMS Cloud และตั้งค่า Tunnel กลับมาที่เครื่องได้ หากไม่มีอินเทอร์เน็ต Local API ที่พอร์ต `45011` ยังทำงานได้ปกติ แต่จะไม่สามารถ Resolve ผ่าน `hosxp.net` ได้
