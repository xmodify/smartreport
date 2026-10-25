# 03. พจนานุกรมตาราง HOSxP ที่ใช้บ่อย (HOSxP Frequent Tables Reference)

> **อ้างอิงจากบทเรียน:** [03-hosxp-tables.md บน BMS Session Dev Portal](https://bms-session-dev.bmscloud.in.th/chapters/hosxp-tables.html)  
> รวบรวมตารางมาตรฐานในระบบฐานข้อมูล HOSxP ที่นักพัฒนา Addon ใช้งานบ่อยที่สุด พร้อม Primary Key และคอลัมน์สำคัญ

---

## 1. หมวดเวชระเบียนและข้อมูลผู้ป่วย (Patient & Registry)

### ตาราง `patient` (ข้อมูลทะเบียนประวัติผู้ป่วย)
* **Primary Key:** `hn` (VARCHAR 9 หลัก มีเลข 0 นำหน้าได้)
* **คอลัมน์สำคัญ:**
  - `hn`: รหัสประจำตัวผู้ป่วย
  - `pname`, `fname`, `lname`: คำนำหน้า, ชื่อ, นามสกุล
  - `cid`: เลขประจำตัวประชาชน 13 หลัก
  - `birthday`: วันเดือนปีเกิด
  - `sex`: เพศ (`1` = ชาย, `2` = หญิง)
  - `bloodgrp`: หมู่เลือด
  - `drugallergy`: ประวัติการแพ้ยา

---

## 2. หมวดผู้ป่วยนอก (Outpatient Department: OPD)

### ตาราง `ovst` (การมารับบริการผู้ป่วยนอก)
* **Primary Key:** `vn` (VARCHAR 12 หลัก เช่น `670101123456`)
* **คอลัมน์สำคัญ:**
  - `vn`: รหัสการรับบริการ
  - `hn`: รหัสผู้ป่วย
  - `vstdate`, `vsttime`: วันที่และเวลาที่มาถึงโรงพยาบาล
  - `main_dep`, `cur_dep`: แผนกหลักและแผนกปัจจุบันที่รับบริการ
  - `oqueue`: หมายเลขคิวตรวจ

### ตาราง `opdscreen` (ข้อมูลการซักประวัติและสัญญาณชีพ)
* **Primary Key:** `vn`
* **คอลัมน์สำคัญ:**
  - `bps`, `bpd`: ความดันโลหิตตัวบนและตัวล่าง (Systolic/Diastolic BP)
  - `pulse`: อัตราการเต้นของชีพจร
  - `bw`, `height`, `bmi`: น้ำหนัก, ส่วนสูง, ดัชนีมวลกาย
  - `temperature`: อุณหภูมิร่างกาย
  - `cc`: อาการสำคัญ (Chief Complaint)
  - `hpi`: ประวัติการเจ็บป่วยปัจจุบัน (History of Present Illness)

### ตาราง `ovstdiag` (การวินิจฉัยโรคผู้ป่วยนอก)
* **Primary Key:** `ovst_diag_id` (ขอผ่าน `get_serialnumber`)
* **คอลัมน์สำคัญ:**
  - `vn`, `hn`: รหัสการรับบริการและผู้ป่วย
  - `icd10`: รหัสโรคตามมาตรฐาน ICD-10
  - `diagtype`: ประเภทการวินิจฉัย (`1` = Principal Dx, `2` = Comorbidity, `3` = Complication, `4` = Other)
  - `doctor`: รหัสแพทย์ผู้ให้การวินิจฉัย

---

## 3. หมวดคลังยาและรายการค่าบริการ (Pharmacy & Billing)

### ตาราง `opitemrece` (รายการยาและค่ารักษาพยาบาลที่สั่ง)
* **Primary Key:** `hos_guid` หรือ `hos_guid_ext`
* **คอลัมน์สำคัญ:**
  - `vn`, `hn`: รหัสการรับบริการและผู้ป่วย
  - `icode`: รหัสรายการยาหรือค่าบริการ
  - `qty`: จำนวนที่สั่ง
  - `unitprice`, `sum_price`: ราคาต่อหน่วยและราคารวม
  - `rxdate`, `rxtime`: วันและเวลาที่สั่งยา
  - `paidst`: สถานะการชำระเงิน

---

## 4. หมวดผู้ป่วยใน (Inpatient Department: IPD)

### ตาราง `ipt` (ข้อมูลการครองเตียงผู้ป่วยใน)
* **Primary Key:** `an` (VARCHAR 8 หลัก เช่น `67000123`)
* **คอลัมน์สำคัญ:**
  - `an`: รหัสการรับเข้าเป็นผู้ป่วยใน
  - `vn`, `hn`: รหัส OPD ต้นทางและรหัสผู้ป่วย
  - `regdate`, `regtime`: วันและเวลาที่ Admit
  - `dchdate`, `dchtime`: วันและเวลาที่จำหน่าย (Discharge)
  - `ward`: รหัสหอผู้ป่วย

---

## 5. หมวดเจ้าหน้าที่และสิทธิ์การใช้งาน (Staff & Permissions)

> [!NOTE]
> ใช้ตารางหมวดนี้ในการตรวจสอบสิทธิ์แทนตาราง `opduser` เสมอ

### ตาราง `officer` (ข้อมูลบุคลากร)
* **Primary Key:** `officer_id`
* **คอลัมน์สำคัญ:**
  - `officer_id`: รหัสเจ้าหน้าที่
  - `officer_name`: ชื่อ-นามสกุล
  - `officer_login_name`: รหัสผู้ใช้เข้าสู่ระบบ (เช่น เลข 13 หลัก)
  - `officer_cid`: เลขบัตรประชาชน
  - `officer_active`: สถานะเปิดใช้งาน (`'Y'` หรือ `'N'`)

### ตาราง `officer_group_list` (การจับคู่เจ้าหน้าที่กับกลุ่มสิทธิ์)
* **Primary Key:** `officer_group_list_id`
* **คอลัมน์สำคัญ:**
  - `officer_id`: รหัสเจ้าหน้าที่
  - `officer_group_id`: รหัสกลุ่มสิทธิ์

### ตาราง `officer_group` (รายชื่อกลุ่มสิทธิ์)
* **Primary Key:** `officer_group_id`
* **คอลัมน์สำคัญ:**
  - `officer_group_id`: รหัสกลุ่ม (เช่น `19` = ผู้ดูแลระบบ)
  - `officer_group_name`: ชื่อกลุ่ม (เช่น `"ผู้ดูแลระบบ"`)
