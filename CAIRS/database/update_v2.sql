-- Run this ONCE if you already imported the first version of cairs.sql.
-- Adds map pins and the new settings. (Fresh installs do not need it.)
USE cairs;
ALTER TABLE reports
  ADD COLUMN latitude  DECIMAL(10,7) DEFAULT NULL AFTER location,
  ADD COLUMN longitude DECIMAL(10,7) DEFAULT NULL AFTER latitude;
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('map_lat','8.4542'),('map_lng','124.6319'),('sms_enabled','0'),('sms_api_key',''),('sms_sender','');
