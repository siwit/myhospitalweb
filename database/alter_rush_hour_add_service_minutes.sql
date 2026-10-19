-- เพิ่มคอลัมน์ *_service_minutes ต่อท้ายทุก *_avg_minutes ใน rush_hour
-- รันบน production หลังอัปโหลดไฟล์นี้

ALTER TABLE rush_hour
  ADD COLUMN exam_opd_service_minutes DECIMAL(6,1) DEFAULT NULL
    COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย'
    AFTER exam_opd_avg_minutes;

ALTER TABLE rush_hour
  ADD COLUMN screen_opd_service_minutes DECIMAL(6,1) DEFAULT NULL
    COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย'
    AFTER screen_opd_avg_minutes;

ALTER TABLE rush_hour
  ADD COLUMN pharmacy_opd_service_minutes DECIMAL(6,1) DEFAULT NULL
    COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย'
    AFTER pharmacy_opd_avg_minutes;

ALTER TABLE rush_hour
  ADD COLUMN pharmacy_ncd_service_minutes DECIMAL(6,1) DEFAULT NULL
    COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย'
    AFTER pharmacy_ncd_avg_minutes;

ALTER TABLE rush_hour
  ADD COLUMN cashier_service_minutes DECIMAL(6,1) DEFAULT NULL
    COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย'
    AFTER cashier_avg_minutes;

ALTER TABLE rush_hour
  ADD COLUMN screen_ncd_service_minutes DECIMAL(6,1) DEFAULT NULL
    COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย'
    AFTER screen_ncd_avg_minutes;

ALTER TABLE rush_hour
  ADD COLUMN exam_ncd_service_minutes DECIMAL(6,1) DEFAULT NULL
    COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย'
    AFTER exam_ncd_avg_minutes;

ALTER TABLE rush_hour
  ADD COLUMN lab_service_minutes DECIMAL(6,1) DEFAULT NULL
    COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย'
    AFTER lab_avg_minutes;
