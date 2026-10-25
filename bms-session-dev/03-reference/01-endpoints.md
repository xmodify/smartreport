# 01. เอกสารอ้างอิง Endpoint ทั้งหมด (All Endpoints Reference)

> **อ้างอิงจากบทเรียน:** [01-endpoints.md บน BMS Session Dev Portal](https://bms-session-dev.bmscloud.in.th/chapters/endpoints.html)  
> รวบรวมรายชื่อ Endpoint ทั้งหมดในระบบ BMS Ecosystem, HTTP Method, Headers ที่จำเป็น, Request Body, และ Response Format

---

## 1. หมวด Resolve Session (PasteJSON)

### `GET /phps/bms_session_paste.php`
* **Host:** `https://hosxp.net`
* **Query Params:** `?bms-session-id={session_id}`
* **Response Envelope:**
  ```json
  {
    "result": {
      "bms_url": "https://DEV99-admin-manoi-m7730.tunnel.hosxp.net",
      "bms_session_code": "eyAidHlwIjogIkpXVCIs...",
      "expired_second": 36000,
      "user_id": "1341800003078",
      "user_name": "นายศิริฤกษ์  คณาดี",
      "hospital_code": "10989",
      "hospital_name": "โรงพยาบาลหัวสะพาน",
      "department_id": "01",
      "department_name": "กลุ่มงานเทคโนโลยีสารสนเทศ"
    },
    "MessageCode": 200,
    "Message": "OK"
  }
  ```

---

## 2. หมวด HOSxP Data API (Hospital Tunnel / Gateway)

### `POST /api/sql`
* **Host:** `{bms_url}`
* **Headers:** `Authorization: Bearer {bms_session_code}`, `Content-Type: application/json`
* **Request Body:**
  ```json
  {
    "app": "smartdata",
    "sql": "SELECT * FROM patient WHERE hn = :hn",
    "params": {
      "hn": { "value": "0001234", "value_type": "string" }
    },
    "marketplace-token": "mkt_..."
  }
  ```
* **Response Envelope:**
  ```json
  {
    "result": [],
    "MessageCode": 200,
    "Message": "OK",
    "data": [ { ... } ],
    "field": [ 2, 6, 10 ],
    "field_name": [ "id", "name", "date" ],
    "record_count": 1
  }
  ```

---

### `GET /api/rest/{table}`
* **Host:** `{bms_url}`
* **Headers:** `Authorization: Bearer {bms_session_code}`
* **Query Params:** `filter={field}:{operator}:{value}`, `sort={field}:{asc|desc}`, `limit=100`, `offset=0`, `expand={relation}`

---

### `POST /api/rest/{table}`
* **Host:** `{bms_url}`
* **Headers:** `Authorization: Bearer {bms_session_code}`, `X-Marketplace-Token: {mkt_token}`, `Content-Type: application/json`
* **Request Body:** JSON Object ของแถวข้อมูลใหม่

---

### `PUT /api/rest/{table}/{id}`
* **Host:** `{bms_url}`
* **Headers:** `Authorization: Bearer {bms_session_code}`, `X-Marketplace-Token: {mkt_token}`, `Content-Type: application/json`
* **Request Body:** JSON Object ของคอลัมน์ที่ต้องการแก้ไข

---

### `DELETE /api/rest/{table}/{id}`
* **Host:** `{bms_url}`
* **Headers:** `Authorization: Bearer {bms_session_code}`, `X-Marketplace-Token: {mkt_token}`

---

### `POST /api/function?name={func_name}`
* **Host:** `{bms_url}`
* **Headers:** `Authorization: Bearer {bms_session_code}`, `Content-Type: application/json`
* **Supported Functions:**
  - `name=get_serialnumber`: Body `{ "serial_name": "...", "table_name": "...", "field_name": "..." }`
  - `name=get_hosvariable`: Body `{ "var_name": "..." }`
  - `name=set_hosvariable`: Body `{ "var_name": "...", "var_value": "..." }` *(ต้องมี mkt_ token)*

---

## 3. หมวด HOSxP Marketplace API

* **Host:** `https://hosxp-marketplace.bmscloud.in.th`

| Endpoint | Method | Headers | วัตถุประสงค์ |
| :--- | :--- | :--- | :--- |
| `/api/v1/addon/token` | `POST` | `X-Addon-Key: {hxp_...}` | ขอ Access Token `mkt_...` ชั่วคราว (อายุ 15 นาที) |
| `/api/v1/addon/token` | `GET` | `X-Addon-Key: {hxp_...}` | ตรวจสอบสถานะ Token `?token=mkt_...` |
| `/api/v1/addon/usage` | `POST` | `X-Addon-Key: {hxp_...}` | รายงานการใช้งานและหักหน่วยเครดิต |
| `/api/v1/addon/info` | `GET` | `X-Addon-Key: {hxp_...}` | ตรวจสอบการตั้งค่า Addon |
| `/api/v1/user/profile` | `GET` | `X-BMS-Session-Id`, `X-Marketplace-Token` | ตรวจสอบบทบาทและแผนกของผู้ใช้ |

---

## 4. หมวด User Data Storage (Cloud KV Store)

* **Host:** `https://hosxp-marketplace.bmscloud.in.th`

| Endpoint | Method | Headers | รายละเอียด |
| :--- | :--- | :--- | :--- |
| `/api/v1/addon/storage/{key}` | `GET` | `X-BMS-Session-Id`, `X-Marketplace-Token` | อ่านข้อมูล KV Store ของคีย์ที่ระบุ |
| `/api/v1/addon/storage/{key}` | `PUT` | `X-BMS-Session-Id`, `Content-Type: application/json` | บันทึก/อัปเดตข้อมูล พร้อม `expectedVersion` |
| `/api/v1/addon/storage/{key}` | `DELETE` | `X-BMS-Session-Id`, `X-Marketplace-Token` | ลบข้อมูล KV Store |

---

## 5. หมวด Central AI Gateway

* **Host:** `https://ai-api.kube.bmscloud.in.th`
* **Header ร่วม:** `Authorization: Bearer {bms-session-id}`

| Endpoint | Method | Content-Type | คำอธิบาย |
| :--- | :--- | :--- | :--- |
| `/v1/models` | `GET` | - | รายชื่อ AI Models ที่เปิดใช้งาน |
| `/v1/chat/completions` | `POST` | `application/json` | LLM Chat รูปแบบเดียวกับ OpenAI API |
| `/v1/audio/transcriptions`| `POST` | `multipart/form-data` | ถอดความเสียงภาษาไทย Thai ASR |
| `/v1/audio/speech` | `POST` | `application/json` | สังเคราะห์เสียงภาษาไทย Thai TTS (ได้ไฟล์ MP3) |
| `/v1/audio/normalize` | `POST` | `application/json` | แปลงตัวย่อ/หน่วยภาษาไทยเป็นคำอ่าน |
| `/v1/icd11/lookup` | `POST` | `application/json` | ค้นหารหัสโรค ICD-11 และแมป ICD-10 |
| `/v1/ocr/extract` | `POST` | `multipart/form-data` | สกัดข้อมูลเอกสารทางการแพทย์ |
