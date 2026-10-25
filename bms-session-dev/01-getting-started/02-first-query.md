# 02. บทเรียน 15 นาที: Query แรกสู่ HOSxP (First Query)

> **อ้างอิงจากบทเรียน:** [02-first-query.md บน BMS Session Dev Portal](https://bms-session-dev.bmscloud.in.th/chapters/first-query.html)  
> แนะนำขั้นตอนทีละก้าว ตั้งแต่การรับค่า `bms-session-id`, การ Resolve หา Tunnel URL, ไปจนถึงการยิงคำสั่ง SQL แรกสำเร็จ

---

## ขั้นตอนที่ 1: การรับค่า `bms-session-id` จาก URL

เมื่อโปรแกรม HOSxP หรือผู้ใช้เปิดเว็บ Addon ขึ้นมา ระบบจะส่งค่ามาในรูปแบบ:
```http
https://your-addon-domain.com/?bms-session-id=A504F016-19D8-453F-B0AB-DEB5BADBB3E0&hn=0001234&vn=670101123456
```

---

## ขั้นตอนที่ 2: Resolve Session ผ่าน PasteJSON (`hosxp.net`)

ส่งคำขอแบบ HTTP GET ไปยังศูนย์กลาง PasteJSON เพื่อแปลง `bms-session-id` เป็น URL Gateway และ Credential ของโรงพยาบาล:

```http
GET https://hosxp.net/phps/bms_session_paste.php?bms-session-id=A504F016-19D8-453F-B0AB-DEB5BADBB3E0
```

### ตัวอย่างผลลัพธ์ (Response JSON)
```json
{
  "result": {
    "bms_url": "https://DEV99-admin-manoi-m7730.tunnel.hosxp.net",
    "bms_session_code": "eyAidHlwIjogIkpXVCIsICJhbGciOiAiSFMyNTYiIH0...",
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

> [!NOTE]
> ในโหมดทดสอบบนเครื่อง Local หากเปิดผ่าน BMS Dev Portal หรือ HOSxP เครื่องเดียวกัน โดยไม่ได้ออกผ่านคลาวด์ `{bms_url}` จะชี้ไปยัง `http://127.0.0.1:45011`

---

## ขั้นตอนที่ 3: ส่งคำสั่ง SQL แรกผ่าน `/api/sql`

เมื่อได้ `{bms_url}` และ `{bms_session_code}` แล้ว ให้ยิง HTTP POST ไปยัง `{bms_url}/api/sql`:

### รูปแบบคำขอ (Request)
```http
POST {bms_url}/api/sql
Authorization: Bearer {bms_session_code}
Content-Type: application/json

{
  "app": "smartdata",
  "sql": "SELECT 1 AS test, NOW() AS server_time"
}
```

> [!IMPORTANT]
> **ฟิลด์ `app` เป็นฟิลด์บังคับ:** หากไม่ส่งฟิลด์ `app` (เช่น `"app": "smartdata"`) mORMot API Server จะตอบกลับ `400 Bad Request` พร้อมข้อความ `"Missing app parameter"` ทันที

### รูปแบบการตอบกลับ (Response)
```json
{
  "result": [],
  "MessageCode": 200,
  "Message": "OK",
  "RequestTime": "2026-10-25T21:00:00.000Z",
  "data": [
    {
      "test": 1,
      "server_time": "2026-10-25 21:00:00"
    }
  ],
  "field": [2, 10],
  "field_name": ["test", "server_time"],
  "record_count": 1,
  "truncated": false
}
```

### การตรวจสอบความสำเร็จ:
* ตรวจสอบว่า HTTP Status = `200`
* ตรวจสอบว่า `MessageCode` = `200`
* ดึงข้อมูลผลลัพธ์จากฟิลด์ `data` (หรือ `result`)
