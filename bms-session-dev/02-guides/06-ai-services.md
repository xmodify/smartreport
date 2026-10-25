# 06. บริการ AI ของ BMS Cloud (Central AI Services Guide)

> **อ้างอิงจากบทเรียน:** [06-ai-services.md บน BMS Session Dev Portal](https://bms-session-dev.bmscloud.in.th/chapters/ai-services.html)  
> ครอบคลุมการเชื่อมต่อ Central AI Gateway สำหรับการแพทย์: LLM Chat, การถอดเสียงไทย (ASR), การสังเคราะห์เสียง (TTS), การแปลงคำอ่าน, การค้นหารหัสโรค ICD-11 และ Medical OCR

---

## 1. ขอบเขตและการยืนยันตัวตน (Authentication)

บริการ AI ทั้งหมดของ BMS ให้บริการผ่าน **Central AI Gateway**:
* **Base URL:** `https://ai-api.kube.bmscloud.in.th`
* **Header ยืนยันตัวตน:** `Authorization: Bearer {bms-session-id}`
* **จุดเด่น:** **ส่ง `bms-session-id` ดิบที่ได้รับจาก HOSxP ได้โดยตรง** โดยไม่ต้อง Resolve เป็น `bms_session_code` ก่อน

---

## 2. ตรวจสอบรายชื่อ AI Model ที่พร้อมใช้งาน (`GET /v1/models`)

```http
GET https://ai-api.kube.bmscloud.in.th/v1/models
Authorization: Bearer {bms-session-id}
```
Response จะคืนรายชื่อโมเดลล่าสุด เช่น `kimi-k2.6`, `glm-5`, `k3` เป็นต้น

---

## 3. LLM Chat Completions (`POST /v1/chat/completions`)

รองรับรูปแบบมาตรฐานเดียวกับ OpenAI API ทำให้นำไปใช้กับไลบรารี LLM ทั่วไปได้ทันที:

```http
POST https://ai-api.kube.bmscloud.in.th/v1/chat/completions
Authorization: Bearer {bms-session-id}
Content-Type: application/json

{
  "model": "deepseek-chat",
  "messages": [
    {
      "role": "system",
      "content": "คุณคือผู้ช่วยแพทย์อัจฉริยะในระบบโรงพยาบาล HOSxP"
    },
    {
      "role": "user",
      "content": "ช่วยสรุปคำแนะนำการดูแลผู้ป่วยโรคความดันโลหิตสูงในผู้สูงอายุ"
    }
  ],
  "temperature": 0.3,
  "max_tokens": 1000
}
```

---

## 4. ระบบปรับภาษาพูดแพทย์ Thai Audio Normalize (`POST /v1/audio/normalize`)

ใช้สำหรับแปลงตัวย่อทางการแพทย์, วันที่, เวลา, และหน่วยยาภาษาไทย เป็นคำอ่านเต็มที่ถูกต้องก่อนนำไปเข้า Text-to-Speech:

```http
POST https://ai-api.kube.bmscloud.in.th/v1/audio/normalize
Authorization: Bearer {bms-session-id}
Content-Type: application/json

{
  "text": "คนไข้รับยาพารา 2 เม็ด ทุก 4-6 ชม. เวลา 09.00 น."
}
```

### Response ที่ได้
```json
{
  "original": "คนไข้รับยาพารา 2 เม็ด ทุก 4-6 ชม. เวลา 09.00 น.",
  "normalized": "คนไข้รับยาพารา สอง เม็ด ทุก สี่ถึงหก ชั่วโมง เวลา เก้านาฬิกา"
}
```

---

## 5. การสังเคราะห์เสียงภาษาไทย Thai TTS (`POST /v1/audio/speech`)

```http
POST https://ai-api.kube.bmscloud.in.th/v1/audio/speech
Authorization: Bearer {bms-session-id}
Content-Type: application/json

{
  "model": "voxcpm-thai",
  "input": "คนไข้รับยาพารา สอง เม็ด ทุก สี่ถึงหก ชั่วโมง",
  "voice": "female",
  "response_format": "mp3"
}
```
* **ผลลัพธ์:** ไฟล์ Binary Audio ในรูปแบบ MP3 นำไปเล่นผ่าน `<audio>` บนเว็บเบราว์เซอร์ได้ทันที

---

## 6. การถอดความเสียงภาษาไทย Thai ASR (`POST /v1/audio/transcriptions`)

สำหรับรับไฟล์เสียงบันทึกการตรวจของแพทย์หรือการซักประวัติ แล้วแปลงเป็นข้อความ:
* **HTTP Method:** `POST`
* **Content-Type:** `multipart/form-data`
* **ฟิลด์:**
  - `file`: ไฟล์เสียง (WAV, MP3, M4A)
  - `model`: เช่น `Qwen/Qwen3-ASR-1.7B`
  - `language`: `th`

---

## 7. ระบบค้นหารหัสโรคอัตโนมัติ ICD-11 Semantic Lookup (`POST /v1/icd11/lookup`)

ช่วยแพทย์ค้นหารหัสโรค ICD-11 จากการพิมพ์บันทึกอาการทางคลินิก (Clinical Notes):

```http
POST https://ai-api.kube.bmscloud.in.th/v1/icd11/lookup
Authorization: Bearer {bms-session-id}
Content-Type: application/json

{
  "symptom": "เบาหวานชนิดที่ 2 ควบคุมไม่ได้ น้ำตาลในเลือดสูง",
  "limit": 3
}
```

### ตัวอย่างผลลัพธ์ที่ได้
```json
{
  "results": [
    {
      "code": "MA18.0Z",
      "name": "Hyperglycaemia, unspecified",
      "tname": "ระดับน้ำตาลในเลือดสูง ไม่ระบุรายละเอียด",
      "chapter_code": "21",
      "score": 0.045,
      "icd10_code": ["R73"],
      "has_icd10": true
    },
    {
      "code": "5A11",
      "name": "Type 2 diabetes mellitus",
      "tname": "โรคเบาหวานชนิดที่ 2",
      "chapter_code": "05",
      "score": 0.041,
      "icd10_code": ["E11"],
      "has_icd10": true
    }
  ]
}
```

---

## 8. ระบบสกัดข้อมูลเอกสารทางการแพทย์ Medical OCR (`POST /v1/ocr/extract`)

สำหรับส่งรูปถ่ายใบส่งตัว ผลแล็บ หรือเอกสารประวัติการรักษา แล้วสกัดเป็นข้อมูล JSON โครงสร้างมาตรฐาน:
* **HTTP Method:** `POST`
* **Content-Type:** `multipart/form-data`
* **ฟิลด์:**
  - `file`: รูปถ่ายหรือไฟล์ PDF
  - `type`: เช่น `medical_lab`, `refer_slip`, `prescription`
