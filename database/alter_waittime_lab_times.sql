-- เพิ่มเวลา Lab แยก (order / receive / report) ใน waittime_visit_anon
-- รันครั้งเดียวบนฐานเว็บ (local / production)

ALTER TABLE waittime_visit_anon
  ADD COLUMN lab_order_time DATETIME DEFAULT NULL AFTER lab_total_minutes;

ALTER TABLE waittime_visit_anon
  ADD COLUMN lab_receive_time DATETIME DEFAULT NULL AFTER lab_order_time;

ALTER TABLE waittime_visit_anon
  ADD COLUMN lab_report_time DATETIME DEFAULT NULL AFTER lab_receive_time;
