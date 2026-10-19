-- สรุปรายชั่วโมงจาก waittime_visit_anon (wide: หนึ่งแถวต่อวัน+ชั่วโมง)
-- โซนตาม cur_depcode / LAB ตาม lab_order_time มีค่า แต่ lab_report_time ว่าง
-- *_avg_minutes = ระยะเวลารอคอยเฉลี่ย (นาที/ราย)
-- *_service_minutes = เวลารับบริการเฉลี่ย (นาที/ราย)

CREATE TABLE IF NOT EXISTS rush_hour (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_date DATE NOT NULL COMMENT 'วันรับบริการ (vstdate)',
  hour_slot TINYINT UNSIGNED NOT NULL COMMENT 'ชั่วโมงเริ่มช่วง 0-23',
  slot_label VARCHAR(20) NOT NULL COMMENT 'เช่น 06:00-06:59',

  exam_opd_waiting INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ห้องตรวจ OPD cur_dep 003,004,005,029,042',
  exam_opd_avg_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'ระยะเวลารอคอยเฉลี่ย นาที/ราย',
  exam_opd_service_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย',

  screen_opd_waiting INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ซักประวัติ/screen OPD cur_dep 002,022,024',
  screen_opd_avg_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'ระยะเวลารอคอยเฉลี่ย นาที/ราย',
  screen_opd_service_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย',

  pharmacy_opd_waiting INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จ่ายยา OPD cur_dep 007',
  pharmacy_opd_avg_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'ระยะเวลารอคอยเฉลี่ย นาที/ราย',
  pharmacy_opd_service_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย',

  pharmacy_ncd_waiting INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จ่ายยา NCD cur_dep 098',
  pharmacy_ncd_avg_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'ระยะเวลารอคอยเฉลี่ย นาที/ราย',
  pharmacy_ncd_service_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย',

  cashier_waiting INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ห้องจ่ายเงิน cur_dep 006',
  cashier_avg_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'ระยะเวลารอคอยเฉลี่ย นาที/ราย',
  cashier_service_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย',

  screen_ncd_waiting INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ซักประวัติ/screen NCD cur_dep 023',
  screen_ncd_avg_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'ระยะเวลารอคอยเฉลี่ย นาที/ราย',
  screen_ncd_service_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย',

  exam_ncd_waiting INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ห้องตรวจ NCD cur_dep 062',
  exam_ncd_avg_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'ระยะเวลารอคอยเฉลี่ย นาที/ราย',
  exam_ncd_service_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย',

  lab_waiting INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'เจาะเลือด LAB: มี lab_order_time แต่ lab_report_time ว่าง',
  lab_avg_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'ระยะเวลารอคอยเฉลี่ย นาที/ราย',
  lab_service_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย',

  avg_total_service_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'เวลารับบริการทั้งหมดเฉลี่ยต่อราย (total_opd)',
  total_recipients INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จำนวนผู้รับบริการทั้งหมดในโซน cur_dep ที่กำหนด',

  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rush_hour_date_slot (service_date, hour_slot),
  KEY idx_rush_hour_date (service_date),
  KEY idx_rush_hour_slot (hour_slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
