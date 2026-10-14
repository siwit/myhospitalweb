# การศึกษาการคำนวณระยะเวลารอคอย OPD

**แหล่งข้อมูล:** `192.168.20.252` / database `myhpt_slave`  
**โรงพยาบาล (opdconfig.hospitalcode):** `11602`  
**ช่วงวันที่ที่รันทดสอบ:** `2026-10-01` ถึง `2026-10-26`  
**จำนวน visit ใน `service_time`:** 2,649 รายการ

---

## 1. แนวคิดของ Query

Query นี้ดึง timeline การรับบริการจากตาราง `service_time` แล้วแปลงเวลาแต่ละจุดเป็น datetime  
จากนั้นคำนวณนาทีระหว่างจุดสำคัญ และต่อด้วย Lab / X-ray ต่อ visit (`vn`)

### จุดเวลาหลักจาก `service_time`

| ฟิลด์ | ชื่อใน query | ความหมายที่ใช้คำนวณ |
|---|---|---|
| `service3` | `dt_arrive_screening` | ถึงจุดคัดกรอง / เริ่มเส้นทาง OPD |
| `service4` | `dt_start_screening` | เริ่มคัดกรอง |
| `service11` | `dt_end_screening` | จบคัดกรอง |
| `service5` | `dt_call_doctor` | เรียกเข้าห้องตรวจ |
| `service12` | `dt_end_doctor` | จบตรวจแพทย์ |
| `service6` | `dt_arrive_pharmacy` | ถึงห้องยา |
| `service16` | `dt_receive_drug` | รับยา |

### กฎแปลงเวลาพิเศษ
- ถ้าเวลาเป็น `24:00:00` ให้ถือเป็น `00:00:00` ของวันถัดไป  
- รวมวันที่ด้วย `STR_TO_DATE(CONCAT(vstdate, ' ', serviceX), ...)`

### ตัวแปรธง
- `is_appointment_visit` = มีใน `oapp` ที่ `hn` + `nextdate = vstdate`
- `is_er_visit` = มีใน `er_regist` ตาม `vn`

---

## 2. สูตรเวลารอคอยหลัก

### 2.1 เวลารวม OPD (`total_opd_minutes`)

```
total_opd_minutes = TIMESTAMPDIFF(
  MINUTE,
  dt_arrive_screening (service3),
  COALESCE(dt_receive_drug, dt_arrive_pharmacy)  -- service16 ถ้ามี ไม่งั้นใช้ service6
)
```

เงื่อนไข:
- ต้องมี `service3`
- ต้องมีอย่างน้อยหนึ่งใน `service16` หรือ `service6`
- ถ้าขาดจุดใดจุดหนึ่งผลเป็น `NULL`

### 2.2 Lab

จาก `lab_head` ต่อ `vn`:
- `lab_order_time` = `MIN(order)`
- `lab_receive_time` = `MIN(receive)`
- `lab_report_time` = `MAX(report)`

| ตัวแปร | สูตร |
|---|---|
| `lab_wait_receive_minutes` | order → receive |
| `lab_process_minutes` | receive → report |
| `lab_total_minutes` | order → report |

### 2.3 X-ray

จาก `xray_report` ต่อ `vn`:
- order = `MIN(COALESCE(order_datetime, request date+time))`
- exam = `MIN(examined)`
- report = `MAX(confirm_read)`

| ตัวแปร | สูตร |
|---|---|
| `xray_wait_minutes` | order → exam |
| `xray_process_minutes` | exam → report |
| `xray_total_minutes` | order → report |

---

## 3. ผลรันจริงในช่วง 1–26 ต.ค. 2026

### 3.1 ความครบของจุดเวลา

| จุดเวลา | จำนวนที่มีค่า | สัดส่วนโดยประมาณ |
|---|---:|---:|
| ถึงคัดกรอง (`service3`) | 2,642 | ~99.7% |
| เริ่มคัดกรอง (`service4`) | 2,319 | ~87.5% |
| จบคัดกรอง (`service11`) | 2,274 | ~85.8% |
| เรียกแพทย์ (`service5`) | 1,423 | ~53.7% |
| จบแพทย์ (`service12`) | 1,993 | ~75.2% |
| ถึงห้องยา (`service6`) | 1,363 | ~51.5% |
| รับยา (`service16`) | **37** | **~1.4%** |
| คำนวณ `total_opd_minutes` ได้ | 1,357 | ~51.2% |

ข้อสังเกตสำคัญ:
- `service16` (รับยา) ถูกบันทึกน้อยมาก  
- ดังนั้น `total_opd_minutes` ส่วนใหญ่จบที่ **ถึงห้องยา** ไม่ใช่ **รับยาจริง**

### 3.2 ค่าเฉลี่ยรายช่วง (นาที)

| ช่วง | ค่าเฉลี่ย (นาที) |
|---|---:|
| ถึงคัดกรอง → เริ่มคัดกรอง | 29.5 |
| ระยะเวลาคัดกรอง | 2.9 |
| จบคัดกรอง → เรียกแพทย์ | 86.0 |
| ระยะเวลาตรวจแพทย์ | 1.5 |
| จบแพทย์ → ถึงห้องยา | 26.0 |
| ถึงห้องยา → รับยา | 11.9 *(จากกลุ่มที่มี service16 น้อย)* |

### 3.3 เวลารวม OPD

| ตัวชี้วัด | ค่า |
|---|---:|
| ค่าเฉลี่ยรวม (ดิบ) | 142.6 นาที |
| ค่าต่ำสุด / สูงสุด | -1,379 / 846 |
| ค่าติดลบ | 1 ราย |
| เกิน 8 ชั่วโมง (>480) | 7 ราย |
| ค่าเฉลี่ยหลังกรอง 0–480 นาที | **141.5 นาที** |
| Non-ER (0–480) | **180.7 นาที** (n=854) |
| ER (0–480) | **73.8 นาที** (n=495) |

### 3.4 Lab / X-ray

| รายการ | ผล |
|---|---|
| Visit ที่มี lab ในช่วงนี้ | 1,090 vn |
| Lab เฉลี่ยหลังกรองข้อมูลสมเหตุสมผล (0–1440 นาที) | รอรับสิ่งส่งตรวจ ~90.7, process ~179.1, รวม ~270.3 |
| Lab เฉลี่ยแบบไม่กรอง | เพี้ยนมาก (ติดลบหลักแสน) เพราะวันที่/เวลาบางแถวไม่ครบหรือผิดปกติ |
| X-ray ทั้งช่วง (จริง) | **207 vn** |
| X-ray ที่ query เดิมต่อได้ | **1 vn** เพราะมีเงื่อนไข `vn = '691005082805'` ค้างอยู่ใน subquery |

ตัวอย่าง vn ที่ค้างใน query: `691005082805`  
- order `2026-10-05 14:49`  
- exam `2026-10-05 14:55`  
- report `NULL`

### 3.5 ธง visit

| ธง | จำนวน |
|---|---:|
| ER visit | 773 |
| Appointment visit (จาก oapp) | 19 |

ตัวเลขนัดหมายต่ำผิดปกติ อาจเพราะเงื่อนไขจับคู่ `oapp.nextdate = service_time.vstdate` ยังไม่ครอบคลุมรูปแบบนัดจริงทั้งหมด

---

## 4. Flow ที่ Query พยายามวัด

```text
[ถึงคัดกรอง service3]
        ↓  wait_to_start_screening
[เริ่มคัดกรอง service4]
        ↓  screening_duration
[จบคัดกรอง service11]
        ↓  wait_to_doctor
[เรียกแพทย์ service5]
        ↓  doctor_duration
[จบแพทย์ service12]
        ↓  wait_to_pharmacy
[ถึงห้องยา service6]
        ↓  pharmacy_duration
[รับยา service16]   ← ข้อมูลน้อยมาก

ควบคู่ไปด้วย (ถ้ามี):
  Lab:  order → receive → report
  Xray: order → exam → report
```

---

## 5. จุดที่ควรระวังตอนนำไปใช้รายงาน

1. **`total_opd_minutes` ส่วนใหญ่ไม่ใช่เวลารับยาจริง**  
   เพราะ `service16` มีแค่ ~1.4% ควรระบุในรายงานว่า endpoint คือ `COALESCE(receive_drug, arrive_pharmacy)`

2. **ลำดับเวลาบาง visit ไม่เรียง**  
   เช่น มีเคส `end_doctor` เกิดก่อน `call_doctor` ทำให้ค่าเฉลี่ยรายช่วงอาจเพี้ยน ควรกรอง `diff >= 0` และอาจจำกัดช่วง เช่น 0–480 นาที

3. **เงื่อนไข X-ray ใน query เดิมเป็น debug filter**  
   ควรเอา `AND vn = "691005082805"` ออก ถ้าต้องการวิเคราะห์ทุกราย

4. **Lab ต้องทำความสะอาดข้อมูลก่อน aggregate**  
   กรองวันที่ว่าง / วันที่ผิดปกติ / นาทีติดลบ / นาทีเกินเกณฑ์

5. **การนับนัดหมายอาจ under-count**  
   ควรทบทวนนิยามจากตาราง `oapp` หรือแหล่งนัดอื่นของ HOSxP

6. **ER กับ OPD ควรแยกวิเคราะห์**  
   ค่าเฉลี่ยรวมถูกดึงลงเพราะ ER สั้นกว่าชัดเจน (~74 นาที เทียบ Non-ER ~181 นาที)

---

## 6. สูตรที่แนะนำสำหรับรายงานจริง (ร่าง)

- Non-ER completed OPD:
  - มี `service3` และ `COALESCE(service16, service6)`
  - ไม่มีใน `er_regist`
  - `total_opd_minutes BETWEEN 0 AND 480`
- แยกรายงานตามแผนกแพทย์ (`service5_dep` / `doctor_department`)
- Lab/X-ray รายงานแยก ไม่บวกรวมตรง ๆ เข้า `total_opd_minutes` เพราะซ้อนในช่วงรอแพทย์/ก่อนห้องยาได้

---

## 7. สรุปสั้น ๆ

Query นี้ใช้ได้เป็นโครงคำนวณเวลารอคอย OPD จาก `service_time` โดยวัดจากจุดคัดกรองถึงห้องยา/รับยา และเสริม Lab/X-ray  
จากข้อมูลจริงช่วงต้น–ปลายตุลาคม 2026 ค่าเฉลี่ย Non-ER ที่กรองแล้วอยู่ราว **3 ชั่วโมง** และช่วงรอเข้าแพทย์หลังคัดกรองเป็นคอขวดหลัก (~86 นาที)

ก่อนขึ้นแดชบอร์ดควรแก้จุด X-ray filter, นิยาม endpoint รับยา, และกฎทำความสะอาดค่าติดลบ/เกินเกณฑ์
