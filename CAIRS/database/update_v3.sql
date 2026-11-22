-- OPTIONAL. The app creates all of this by itself the first time a page loads
-- (see ensure_schema() in config/database.php). Run it by hand only if you prefer to.
USE cairs;

CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  body TEXT NOT NULL,
  level ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(120) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (email, created_at),
  INDEX (ip, created_at)
) ENGINE=InnoDB;

-- Run this line ONCE (it errors if the column already exists):
ALTER TABLE reports ADD COLUMN is_sos TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('emergency_hotline','911'),('hotline_fire',''),('hotline_police',''),('hotline_medical','');
