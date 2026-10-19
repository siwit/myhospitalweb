-- ความจุต่อโซน สำหรับคำนวณความแออัดใน Digital Twin
-- แก้ค่า capacity ในตารางนี้ได้โดยตรง ระบบจะอ่านมาใช้ตอนสรุป/แสดง twin

CREATE TABLE IF NOT EXISTS capacity (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  zone_code VARCHAR(32) NOT NULL COMMENT 'รหัสโซน เช่น exam_opd, lab',
  zone_name VARCHAR(100) NOT NULL COMMENT 'ชื่อแสดงผล',
  capacity INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'ความจุสำหรับคำนวณความแออัด',
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_capacity_zone (zone_code),
  KEY idx_capacity_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- seed ครั้งแรกเท่านั้น: ถ้า zone มีอยู่แล้วจะไม่ทับค่า capacity ที่ลุงแก้ไว้
INSERT INTO capacity (zone_code, zone_name, capacity, sort_order) VALUES
  ('exam_opd', 'ห้องตรวจ OPD', 8, 10),
  ('screen_opd', 'จุดซักประวัติ OPD', 6, 20),
  ('lab', 'ห้องเจาะเลือด LAB', 6, 30),
  ('pharmacy_opd', 'ห้องจ่ายยา OPD', 4, 40),
  ('cashier', 'ห้องจ่ายเงิน', 3, 50),
  ('screen_ncd', 'จุดซักประวัติ NCD', 4, 60),
  ('exam_ncd', 'ห้องตรวจ NCD', 4, 70),
  ('pharmacy_ncd', 'ห้องจ่ายยา NCD', 3, 80)
ON DUPLICATE KEY UPDATE
  zone_name = VALUES(zone_name),
  sort_order = VALUES(sort_order);
