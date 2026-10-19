-- สถิติ Digital Twin รายชั่วโมง
-- หนึ่งแถว = หนึ่งจุดบริการ ในหนึ่งช่วงชั่วโมง เช่น 2026-10-18 06:00-06:59

CREATE TABLE IF NOT EXISTS opd_twin_hourly (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_date DATE NOT NULL,
  hour_slot TINYINT UNSIGNED NOT NULL COMMENT 'ชั่วโมงเริ่มช่วง 0-23',
  slot_label VARCHAR(13) NOT NULL COMMENT '06:00-06:59',
  zone_code VARCHAR(32) NOT NULL,
  waiting_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ผู้รอรับบริการ',
  avg_wait_minutes DECIMAL(6,1) NOT NULL DEFAULT 0 COMMENT 'ระยะเวลารอเฉลี่ย นาที/ราย',
  avg_service_minutes DECIMAL(6,1) NOT NULL DEFAULT 0 COMMENT 'ระยะเวลารับบริการเฉลี่ย นาที/ราย',
  served_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จำนวนที่ใช้ถ่วงน้ำหนักเวลาบริการ',
  capacity INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'ความจุสำหรับคำนวณความหนาแน่น',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_twin_hour_zone (service_date, hour_slot, zone_code),
  KEY idx_twin_date_hour (service_date, hour_slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
