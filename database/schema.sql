CREATE DATABASE IF NOT EXISTS cityremedy
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE cityremedy;

CREATE TABLE departments (
  department_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(40) NOT NULL UNIQUE,
  department_name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE users (
  user_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('citizen', 'department_admin', 'system_admin') NOT NULL DEFAULT 'citizen',
  department_id INT UNSIGNED NULL,
  access_code_hash VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_department FOREIGN KEY (department_id)
    REFERENCES departments(department_id) ON DELETE RESTRICT,
  INDEX idx_users_department_role (department_id, role)
) ENGINE=InnoDB;

CREATE TABLE complaints (
  complaint_id VARCHAR(32) PRIMARY KEY,
  citizen_id BIGINT UNSIGNED NOT NULL,
  department_id INT UNSIGNED NOT NULL,
  category VARCHAR(60) NOT NULL,
  title VARCHAR(140) NOT NULL,
  description TEXT NOT NULL,
  location_address VARCHAR(255) NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM(
    'Submitted', 'Under Verification', 'Work Assigned',
    'In Progress', 'Resolved', 'Closed', 'Rejected'
  ) NOT NULL DEFAULT 'Submitted',
  validity_score TINYINT UNSIGNED NULL,
  vote_count INT UNSIGNED NOT NULL DEFAULT 0,
  community_validated TINYINT(1) NOT NULL DEFAULT 0,
  rejection_reason VARCHAR(500) NULL,
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_complaints_citizen FOREIGN KEY (citizen_id)
    REFERENCES users(user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_complaints_department FOREIGN KEY (department_id)
    REFERENCES departments(department_id) ON DELETE RESTRICT,
  INDEX idx_complaints_department_status (department_id, status, submitted_at),
  INDEX idx_complaints_category_location (category, location_address),
  INDEX idx_complaints_public_feed (status, submitted_at),
  CONSTRAINT chk_complaints_validity CHECK (validity_score IS NULL OR validity_score BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE evidence_files (
  evidence_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id VARCHAR(32) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(100) NOT NULL UNIQUE,
  mime_type VARCHAR(100) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_evidence_complaint FOREIGN KEY (complaint_id)
    REFERENCES complaints(complaint_id) ON DELETE CASCADE,
  INDEX idx_evidence_complaint (complaint_id)
) ENGINE=InnoDB;

CREATE TABLE community_votes (
  vote_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id VARCHAR(32) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  voted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_votes_complaint FOREIGN KEY (complaint_id)
    REFERENCES complaints(complaint_id) ON DELETE CASCADE,
  CONSTRAINT fk_votes_user FOREIGN KEY (user_id)
    REFERENCES users(user_id) ON DELETE CASCADE,
  UNIQUE KEY uq_vote_per_citizen (complaint_id, user_id),
  INDEX idx_votes_complaint (complaint_id)
) ENGINE=InnoDB;

CREATE TABLE verifications (
  verification_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id VARCHAR(32) NOT NULL,
  officer_id BIGINT UNSIGNED NOT NULL,
  validity_score TINYINT UNSIGNED NOT NULL,
  verification_status ENUM('Verified', 'Rejected') NOT NULL,
  remarks VARCHAR(500) NULL,
  verified_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_verifications_complaint FOREIGN KEY (complaint_id)
    REFERENCES complaints(complaint_id) ON DELETE CASCADE,
  CONSTRAINT fk_verifications_officer FOREIGN KEY (officer_id)
    REFERENCES users(user_id) ON DELETE RESTRICT,
  INDEX idx_verifications_complaint (complaint_id, verified_at),
  CONSTRAINT chk_verifications_score CHECK (validity_score BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE work_orders (
  work_order_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id VARCHAR(32) NOT NULL UNIQUE,
  assigned_by BIGINT UNSIGNED NOT NULL,
  field_crew VARCHAR(140) NULL,
  expected_completion DATE NULL,
  status ENUM('Assigned', 'In Progress', 'Completed') NOT NULL DEFAULT 'Assigned',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_work_orders_complaint FOREIGN KEY (complaint_id)
    REFERENCES complaints(complaint_id) ON DELETE CASCADE,
  CONSTRAINT fk_work_orders_officer FOREIGN KEY (assigned_by)
    REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE resolutions (
  resolution_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id VARCHAR(32) NOT NULL UNIQUE,
  officer_id BIGINT UNSIGNED NOT NULL,
  proof_stored_name VARCHAR(100) NOT NULL,
  proof_mime_type VARCHAR(100) NOT NULL,
  proof_original_name VARCHAR(255) NOT NULL,
  resolution_notes TEXT NOT NULL,
  resolved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_resolutions_complaint FOREIGN KEY (complaint_id)
    REFERENCES complaints(complaint_id) ON DELETE CASCADE,
  CONSTRAINT fk_resolutions_officer FOREIGN KEY (officer_id)
    REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE feedback (
  feedback_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id VARCHAR(32) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  star_rating TINYINT UNSIGNED NOT NULL,
  comments VARCHAR(1000) NULL,
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_feedback_complaint FOREIGN KEY (complaint_id)
    REFERENCES complaints(complaint_id) ON DELETE CASCADE,
  CONSTRAINT fk_feedback_user FOREIGN KEY (user_id)
    REFERENCES users(user_id) ON DELETE CASCADE,
  UNIQUE KEY uq_feedback_per_citizen (complaint_id, user_id),
  CONSTRAINT chk_feedback_stars CHECK (star_rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE notifications (
  notification_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  complaint_id VARCHAR(32) NULL,
  title VARCHAR(160) NOT NULL,
  message VARCHAR(500) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id)
    REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_complaint FOREIGN KEY (complaint_id)
    REFERENCES complaints(complaint_id) ON DELETE SET NULL,
  INDEX idx_notifications_user (user_id, is_read, created_at)
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
  log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id VARCHAR(32) NOT NULL,
  actor_id BIGINT UNSIGNED NULL,
  activity_type VARCHAR(80) NOT NULL,
  log_description VARCHAR(500) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_complaint FOREIGN KEY (complaint_id)
    REFERENCES complaints(complaint_id) ON DELETE CASCADE,
  CONSTRAINT fk_activity_actor FOREIGN KEY (actor_id)
    REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_activity_timeline (complaint_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE system_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_actor FOREIGN KEY (updated_by)
    REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO departments (slug, department_name) VALUES
  ('roads', 'Road Maintenance'),
  ('waste', 'Waste Management'),
  ('lighting', 'Electrical & Street Lighting'),
  ('water', 'Water Works'),
  ('drainage', 'Drainage'),
  ('general', 'General Services');

INSERT INTO system_settings (setting_key, setting_value) VALUES
  ('community_vote_threshold', '5'),
  ('validity_score_threshold', '3'),
  ('email_notifications_enabled', '0'),
  ('sms_gateway_enabled', '0');
