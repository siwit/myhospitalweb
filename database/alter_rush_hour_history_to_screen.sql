-- เปลี่ยนชื่อคอลัมน์ history_* → screen_* ใน rush_hour
-- รันครั้งเดียวบนฐานเว็บ (local / production)

ALTER TABLE rush_hour
  CHANGE COLUMN history_opd_waiting screen_opd_waiting INT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'ซักประวัติ/screen OPD cur_dep 002,022,024';

ALTER TABLE rush_hour
  CHANGE COLUMN history_opd_avg_minutes screen_opd_avg_minutes DECIMAL(6,1) DEFAULT NULL;

ALTER TABLE rush_hour
  CHANGE COLUMN history_ncd_waiting screen_ncd_waiting INT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'ซักประวัติ/screen NCD cur_dep 023';

ALTER TABLE rush_hour
  CHANGE COLUMN history_ncd_avg_minutes screen_ncd_avg_minutes DECIMAL(6,1) DEFAULT NULL;

-- mirror zone_code ใน opd_twin_hourly (ถ้ามีข้อมูลเก่า)
UPDATE opd_twin_hourly SET zone_code = 'screen_opd' WHERE zone_code = 'history_opd';
UPDATE opd_twin_hourly SET zone_code = 'screen_ncd' WHERE zone_code = 'history_ncd';
