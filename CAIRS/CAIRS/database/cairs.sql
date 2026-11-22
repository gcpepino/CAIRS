-- CAIRS: Community Alert and Incident Reporting System
CREATE DATABASE IF NOT EXISTS cairs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cairs;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('resident','admin','volunteer') NOT NULL DEFAULT 'resident',
  phone VARCHAR(30) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  resident_id INT NOT NULL,
  category_id INT NOT NULL,
  volunteer_id INT DEFAULT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  location VARCHAR(255) NOT NULL,
  latitude DECIMAL(10,7) DEFAULT NULL,
  longitude DECIMAL(10,7) DEFAULT NULL,
  photo VARCHAR(255) DEFAULT NULL,
  priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  status ENUM('pending','assigned','in_progress','resolved','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (resident_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (volunteer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE report_updates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NOT NULL,
  user_id INT NOT NULL,
  status VARCHAR(30) NOT NULL,
  remark VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  report_id INT DEFAULT NULL,
  message VARCHAR(255) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE settings (
  setting_key VARCHAR(50) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

INSERT INTO categories (name) VALUES
('Fire'),('Flood'),('Theft / Crime'),('Road accident'),('Medical emergency'),
('Noise disturbance'),('Garbage / Sanitation'),('Broken streetlight / Road'),('Other');

INSERT INTO settings VALUES
('site_name','CAIRS'),('barangay_name','Barangay Sample'),('contact_phone','0912-345-6789'),
('map_lat','8.4542'),('map_lng','124.6319'),
('sms_enabled','0'),('sms_api_key',''),('sms_sender','');

-- Demo accounts (passwords: admin123 / volunteer123 / resident123)
INSERT INTO users (full_name,email,password,role,phone,address) VALUES
('System Admin','admin@cairs.local','$2y$10$a8QNCH1Ie.k/jPlb52Pb8eZL0AOjVo94FKGRjBwU1dtT2EINCp4Nq','admin','0900-000-0001','Barangay Hall'),
('Vince Volunteer','volunteer@cairs.local','$2y$10$A9bkOYx3jKtRqXYcAdS8WOMcbYl0aAqRRxtWYczLru588nCmThxKW','volunteer','0900-000-0002','Purok 2'),
('Rita Resident','resident@cairs.local','$2y$10$9I9qeHEpNweq7RvX4YaHUOe0ZfrIMbcEoKTyd5uws6m4dqc6v2EWS','resident','0900-000-0003','Purok 5');
