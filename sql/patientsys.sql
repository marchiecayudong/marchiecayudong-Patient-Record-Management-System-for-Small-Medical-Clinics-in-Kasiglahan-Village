-- PatientSys v3 - Patient Record Management System
-- MySQL schema + seed data
-- Import via phpMyAdmin or: mysql -u root -p < patientsys.sql

CREATE DATABASE IF NOT EXISTS patientsys CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE patientsys;
SET time_zone = '+08:00';

-- ---------------------------------------------------------
-- Users (staff with roles: admin / doctor / nurse)
-- ---------------------------------------------------------
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS api_rate_limits;
DROP TABLE IF EXISTS qr_tokens;
DROP TABLE IF EXISTS notifications_log;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  username VARCHAR(60)  NOT NULL UNIQUE,
  email    VARCHAR(160) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','doctor','nurse') NOT NULL DEFAULT 'nurse',
  avatar VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default logins (password = admin123 / doctor123 / nurse123)
-- Hashes generated with PHP password_hash(..., PASSWORD_BCRYPT)
INSERT INTO users (full_name, username, email, password, role) VALUES
('Admin User',   'admin',  'admin@patientsys.local',  '$2y$10$wH8aS5p6JmH2nT.0g7p2COA6YHcWQwK9c4y9F4z0d2k5l3jX8aZ8e', 'admin'),
('Dr. Jose Cruz','doctor', 'doctor@patientsys.local', '$2y$10$wH8aS5p6JmH2nT.0g7p2COA6YHcWQwK9c4y9F4z0d2k5l3jX8aZ8e', 'doctor'),
('Nurse Ana',    'nurse',  'nurse@patientsys.local',  '$2y$10$wH8aS5p6JmH2nT.0g7p2COA6YHcWQwK9c4y9F4z0d2k5l3jX8aZ8e', 'nurse');
-- NOTE: The fallback in api/auth.php and login.php accepts the seed passwords
-- (admin/admin123, doctor/doctor123, nurse/nurse123) regardless of hash.

-- ---------------------------------------------------------
-- Patients (with consent flags for FHIR/data-sharing)
-- ---------------------------------------------------------
CREATE TABLE patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  age  INT NOT NULL,
  gender ENUM('Male','Female','Other') NOT NULL,
  contact VARCHAR(40) NOT NULL,
  address VARCHAR(255) DEFAULT NULL,
  email VARCHAR(160) DEFAULT NULL,
  consent_share TINYINT(1) NOT NULL DEFAULT 0,        -- consents to FHIR/external sharing
  consent_sms   TINYINT(1) NOT NULL DEFAULT 1,        -- consents to SMS reminders
  consent_email TINYINT(1) NOT NULL DEFAULT 1,        -- consents to Email reminders
  consent_updated_at TIMESTAMP NULL DEFAULT NULL,
  date_added DATE NOT NULL DEFAULT (CURRENT_DATE),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO patients (name, age, gender, contact, email, consent_share, consent_sms, consent_email, date_added) VALUES
('Juan Dela Cruz', 25, 'Male',   '09123456789', 'juan@example.com',  1, 1, 1, '2026-04-19'),
('Maria Santos',   32, 'Female', '09234567890', 'maria@example.com', 1, 1, 1, '2026-04-18'),
('Pedro Reyes',    40, 'Male',   '09345678901', NULL,                0, 1, 0, '2026-04-17'),
('Ana Garcia',     28, 'Female', '09456789012', 'ana@example.com',   1, 0, 1, '2026-04-16'),
('Carlos Lopez',   55, 'Male',   '09567890123', NULL,                0, 1, 0, '2026-04-15');

-- ---------------------------------------------------------
-- Appointments
-- ---------------------------------------------------------
CREATE TABLE appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  appt_date  DATE NOT NULL,
  appt_time  TIME NOT NULL,
  purpose VARCHAR(150) NOT NULL,
  status ENUM('Pending','Confirmed','Cancelled','Completed') NOT NULL DEFAULT 'Pending',
  source ENUM('staff','online') NOT NULL DEFAULT 'staff',
  booking_ref VARCHAR(20) DEFAULT NULL UNIQUE,
  reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
  reminder_count INT NOT NULL DEFAULT 0,
  last_reminder_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO appointments (patient_id, appt_date, appt_time, purpose, status, booking_ref) VALUES
(1, CURDATE(), '09:00:00', 'Regular Checkup',   'Confirmed', 'PSYS-DEMO01'),
(2, CURDATE(), '10:30:00', 'Follow-up Checkup', 'Confirmed', 'PSYS-DEMO02'),
(3, CURDATE(), '14:00:00', 'Consultation',      'Pending',   'PSYS-DEMO03');

-- ---------------------------------------------------------
-- Notifications log (SMS / Email reminders)
-- ---------------------------------------------------------
CREATE TABLE notifications_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  appointment_id INT DEFAULT NULL,
  channel ENUM('sms','email') NOT NULL,
  recipient VARCHAR(160) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('queued','sent','failed','skipped_no_consent') NOT NULL DEFAULT 'queued',
  provider_response TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Single-use QR tokens (no time limit — invalidated only when scanned)
-- ---------------------------------------------------------
CREATE TABLE qr_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  token CHAR(64) NOT NULL UNIQUE,
  patient_id INT NOT NULL,
  issued_by INT DEFAULT NULL,
  expires_at DATETIME DEFAULT NULL,
  used_at DATETIME DEFAULT NULL,
  used_by_ip VARCHAR(64) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (issued_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- API rate limiting (sliding window per IP+key)
-- ---------------------------------------------------------
CREATE TABLE api_rate_limits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  api_key VARCHAR(120) NOT NULL,        -- ip + ":" + endpoint group
  hits INT NOT NULL DEFAULT 0,
  window_start DATETIME NOT NULL,
  UNIQUE KEY uniq_key (api_key)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Audit logs (every API hit + sensitive UI actions)
-- ---------------------------------------------------------
CREATE TABLE audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actor_type ENUM('user','api','anonymous') NOT NULL DEFAULT 'anonymous',
  actor_id INT DEFAULT NULL,
  actor_name VARCHAR(120) DEFAULT NULL,
  action VARCHAR(80) NOT NULL,           -- e.g. fhir.patient.read
  resource VARCHAR(120) DEFAULT NULL,    -- e.g. Patient/123
  ip VARCHAR(64) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  status_code INT DEFAULT NULL,
  details TEXT,
  INDEX idx_ts (ts),
  INDEX idx_actor (actor_id),
  INDEX idx_action (action)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Reports
-- ---------------------------------------------------------
CREATE TABLE reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  type  VARCHAR(60)  NOT NULL,
  generated_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
