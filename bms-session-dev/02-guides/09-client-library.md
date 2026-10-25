# 09. ไคลเอนต์และไลบรารีพัฒนา (Client Library & Patterns)

> **อ้างอิงจากบทเรียน:** [09-client-library.md บน BMS Session Dev Portal](https://bms-session-dev.bmscloud.in.th/chapters/client-library.html)  
> ครอบคลุมรูปแบบการเขียน Client Library สำหรับเชื่อมต่อ BMS Session API ทั้งในภาษา JavaScript/TypeScript และ PHP/Laravel

---

## 1. ฟังก์ชันหลัก 4 ประการที่ Client Library ต้องมี

ไม่ว่าจะพัฒนาด้วยภาษาใดก็ตาม ไคลเอนต์ที่ดีสำหรับ BMS Session ควรมีฟังก์ชันหลัก 4 ฟังก์ชัน:
1. **`validateSession(sessionId)`:** ส่งไป Resolve ที่ PasteJSON พร้อมตรวจสอบว่า Session ยังไม่หมดอายุ
2. **`executeQuery(sql, params, limit)`:** ห่อหุ้มคำขอ `POST /api/sql` พร้อมแนบ Header `Authorization: Bearer` และจัดการ `app: smartdata`
3. **`restGet(table, queryParams)`:** ห่อหุ้มคำขอ `GET /api/rest/{table}` พร้อมแปลงฟิลเตอร์ให้อยู่ในไวยากรณ์ที่ถูกต้อง
4. **`restWrite(table, data, marketplaceToken)`:** ห่อหุ้มคำขอ `POST/PUT /api/rest/{table}` พร้อมแนบ Token สิทธิ์เขียน

---

## 2. ตัวอย่างการเขียนใน JavaScript / TypeScript

```javascript
export class BmsClient {
  constructor(bmsUrl, sessionCode, appName = 'smartdata') {
    this.bmsUrl = bmsUrl.replace(/\/$/, '');
    this.sessionCode = sessionCode;
    this.appName = appName;
  }

  async executeSql(sql, params = {}, limit = 100) {
    const response = await fetch(`${this.bmsUrl}/api/sql`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${this.sessionCode}`,
      },
      body: JSON.stringify({
        app: this.appName,
        sql,
        params,
        limit,
      }),
    });

    const res = await response.json();
    if (res.MessageCode !== 200) {
      throw new Error(`BMS SQL Error (${res.MessageCode}): ${res.Message}`);
    }

    return res.data || res.result || [];
  }
}
```

---

## 3. รูปแบบการจัดการ Error Model สำคัญ

> [!IMPORTANT]
> **HTTP 200 แต่มี Error ใน MessageCode:**  
> ในหลายกรณี mORMot API Server ของ HOSxP จะตอบกลับมาด้วย HTTP Status `200 OK` แต่ภายใน JSON จะมี `"MessageCode": 400` หรือ `"MessageCode": 409` พร้อมข้อความแจ้งความผิดพลาดจาก MySQL  
> ดังนั้น Client ต้องตรวจสอบฟิลด์ `MessageCode === 200` เสมอ ห้ามตรวจสอบแค่ HTTP Status เพียงอย่างเดียว
