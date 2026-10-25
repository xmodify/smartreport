# 03. Server Functions ผ่าน `/api/function` (Functions Guide)

> **อ้างอิงจากบทเรียน:** [03-functions.md บน BMS Session Dev Portal](https://bms-session-dev.bmscloud.in.th/chapters/functions.html)  
> ครอบคลุมการเรียกใช้ Built-in Function ของ HOSxP Gateway โดยเฉพาะ `get_serialnumber`, `get_hosvariable`, และ `set_hosvariable`

---

## 1. ขอบเขตและหน้าที่ของ `/api/function`

`/api/function` เป็น Endpoint พิเศษสำหรับเรียกใช้ฟังก์ชันฝั่ง Server ของ HOSxP ที่ทำงานซับซ้อนเกินกว่า SQL ทั่วไป หรือฟังก์ชันที่ต้องอาศัย Lock กลไกภายในของ HOSxP
* **Endpoint Pattern:** `{bms_url}/api/function?name={function_name}`
* **Header:** `Authorization: Bearer {bms_session_code}`
* **HTTP Method:** `POST`

---

## 2. ฟังก์ชัน `get_serialnumber` (สร้าง Primary Key)

> [!CRITICAL]
> **ทำไมต้องใช้ `get_serialnumber`:**  
> ตารางส่วนใหญ่ในระบบฐานข้อมูล HOSxP **ไม่มีคอลัมน์ `AUTO_INCREMENT`** (เนื่องจากต้องรองรับการ Replication ข้ามสาขาและรักษารูปแบบลำดับเอกสาร)  
> ดังนั้น เมื่อคุณต้องการ `INSERT` ข้อมูลลงในตาราง HOSxP **คุณต้องขอค่า Primary Key ถัดไปผ่าน `get_serialnumber` เสมอ** ห้ามสุ่มหรือใช้ `MAX(id) + 1` เด็ดขาด

### ตัวอย่างการเรียกขอ Serial Number
```http
POST {bms_url}/api/function?name=get_serialnumber
Authorization: Bearer {bms_session_code}
Content-Type: application/json

{
  "serial_name": "lab_order_number",
  "table_name": "lab_order",
  "field_name": "lab_order_number"
}
```

### Response ที่ได้กลับมา
```json
{
  "result": {},
  "MessageCode": 200,
  "Message": "OK",
  "Value": 104285,
  "RequestTime": "2026-10-25T21:00:00.000Z"
}
```
* **`Value`:** ตัวเลข Primary Key ใหม่ที่ถูกจองและเพิ่มลำดับในระบบเรียบร้อยแล้ว นำไปใช้ในคำสั่ง `INSERT` ได้ทันที

---

## 3. ฟังก์ชัน `get_hosvariable` และ `set_hosvariable`

ใช้สำหรับการอ่านและแก้ไขค่าตัวแปรระบบของ HOSxP (จากตาราง `sys_var`):

### 3.1 อ่านค่าตัวแปร (`get_hosvariable`)
```http
POST {bms_url}/api/function?name=get_hosvariable
Authorization: Bearer {bms_session_code}
Content-Type: application/json

{
  "var_name": "HOSPCODE"
}
```
Response:
```json
{
  "Value": "10989",
  "MessageCode": 200,
  "Message": "OK"
}
```

### 3.2 ตั้งค่าตัวแปร (`set_hosvariable`)
*(ต้องใช้ Marketplace Token ร่วมด้วย)*
```http
POST {bms_url}/api/function?name=set_hosvariable
Authorization: Bearer {bms_session_code}
X-Marketplace-Token: mkt_987654321abcdef
Content-Type: application/json

{
  "var_name": "START_WELCOME_URL_WITH_SESSION_ID",
  "var_value": "Y"
}
```

---

## 4. โครงสร้าง Function Error Envelope

หากฟังก์ชันทำงานผิดพลาดหรือไม่พบฟังก์ชัน จะได้รับ Response ในรูปแบบ:
```json
{
  "result": {},
  "MessageCode": 404,
  "Message": "Function not found: get_unknown_func",
  "RequestTime": "2026-10-25T21:00:00.000Z"
}
```
