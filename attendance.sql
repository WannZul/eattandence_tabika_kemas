-- eAttendance TABIKA KEMAS application schema
-- PHP 8.1+, MySQL 8.0+ / MariaDB 10.4+, utf8mb4; no sample data or credentials.
-- DESTRUCTIVE CLEAN INSTALL ONLY. Existing installations must follow MIGRATION.md.
SET NAMES utf8mb4;
SET time_zone = '+08:00';
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS telegram_outbox;
DROP TABLE IF EXISTS telegram_update_log;
DROP TABLE IF EXISTS notification_log;
DROP TABLE IF EXISTS manual_attendance_event;
DROP TABLE IF EXISTS kiosk_event;
DROP TABLE IF EXISTS absence_notice;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS face_image;
DROP TABLE IF EXISTS student;
DROP TABLE IF EXISTS login_attempt;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT, username VARCHAR(50) NOT NULL, password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','teacher') NOT NULL DEFAULT 'teacher', is_active TINYINT(1) NOT NULL DEFAULT 1,
 credential_version INT UNSIGNED NOT NULL DEFAULT 1, must_change_password TINYINT(1) NOT NULL DEFAULT 0,
 last_login_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), UNIQUE KEY uq_users_username(username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempt (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, username_key CHAR(64) NOT NULL, network_key CHAR(64) NOT NULL, attempted_at DATETIME NOT NULL,
 PRIMARY KEY(id), KEY idx_login_attempt_pair_time(username_key,network_key,attempted_at),
 KEY idx_login_attempt_username_time(username_key,attempted_at), KEY idx_login_attempt_network_time(network_key,attempted_at), KEY idx_login_attempt_cleanup(attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin;

CREATE TABLE student (
 student_id VARCHAR(30) NOT NULL, name VARCHAR(100) NOT NULL, class VARCHAR(50) NOT NULL,
 face_dataset_version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 face_status ENUM('pending','ready','invalid') NOT NULL DEFAULT 'pending', face_validated_at DATETIME NULL,
 face_validation_message VARCHAR(255) NULL, usable_face_samples SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 telegram_chat_id VARCHAR(50) NULL, telegram_link_token_hash CHAR(64) NULL, telegram_link_expires_at DATETIME NULL,
 telegram_linked_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(student_id), KEY idx_student_name(name), KEY idx_student_class(class), KEY idx_student_face_status(face_status),
 UNIQUE KEY uq_student_link_token(telegram_link_token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE face_image (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT, student_id VARCHAR(30) NOT NULL,
 dataset_version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, relative_path VARCHAR(220) NOT NULL,
 sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, width SMALLINT UNSIGNED NOT NULL, height SMALLINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), UNIQUE KEY uq_face_image_path(relative_path),
 KEY idx_face_image_student_version(student_id,dataset_version,id),
 CONSTRAINT fk_face_image_student FOREIGN KEY(student_id) REFERENCES student(student_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, student_id VARCHAR(30) NOT NULL, name VARCHAR(100) NOT NULL, date DATE NOT NULL,
 time TIME NULL COMMENT 'Effective arrival time; never the manual action time', status ENUM('Hadir','Tidak Hadir') NOT NULL,
 source ENUM('manual','kiosk') NOT NULL DEFAULT 'manual', recorded_by INT UNSIGNED NULL, observed_at DATETIME NULL,
 kiosk_id VARCHAR(50) NULL, recognition_confidence DECIMAL(6,2) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY(id),
 UNIQUE KEY uq_attendance_student_date(student_id,date), KEY idx_attendance_date_status(date,status), KEY idx_attendance_recorded_by(recorded_by),
 CONSTRAINT fk_attendance_student FOREIGN KEY(student_id) REFERENCES student(student_id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_attendance_user FOREIGN KEY(recorded_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE manual_attendance_event (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, attendance_id BIGINT UNSIGNED NOT NULL, student_id VARCHAR(30) NOT NULL,
 attendance_date DATE NOT NULL, prior_status ENUM('Hadir','Tidak Hadir') NULL, prior_effective_time TIME NULL,
 prior_source ENUM('manual','kiosk') NULL, prior_recorded_by INT UNSIGNED NULL,
 new_status ENUM('Hadir','Tidak Hadir') NOT NULL, new_effective_time TIME NULL, action_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 acting_user_id INT UNSIGNED NULL, acting_username VARCHAR(50) NOT NULL, reason_note VARCHAR(255) NULL, PRIMARY KEY(id),
 KEY idx_manual_event_student_date(student_id,attendance_date,action_at), KEY idx_manual_event_actor(acting_user_id,action_at),
 CONSTRAINT fk_manual_event_attendance FOREIGN KEY(attendance_id) REFERENCES attendance(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_manual_event_student FOREIGN KEY(student_id) REFERENCES student(student_id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_manual_event_prior_user FOREIGN KEY(prior_recorded_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
 CONSTRAINT fk_manual_event_actor FOREIGN KEY(acting_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kiosk_event (
 event_id CHAR(36) NOT NULL, student_id VARCHAR(30) NOT NULL, kiosk_id VARCHAR(50) NOT NULL, observed_at DATETIME NOT NULL,
 received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, confidence DECIMAL(6,2) NOT NULL, attendance_date DATE NOT NULL,
 dataset_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 outcome ENUM('accepted','rejected') NOT NULL DEFAULT 'accepted', rejection_reason VARCHAR(50) NULL,
 PRIMARY KEY(event_id), KEY idx_kiosk_event_student_time(student_id,observed_at), KEY idx_kiosk_event_kiosk_time(kiosk_id,observed_at),
 KEY idx_kiosk_event_outcome(received_at,outcome), CONSTRAINT fk_kiosk_event_student FOREIGN KEY(student_id) REFERENCES student(student_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE absence_notice (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, student_id VARCHAR(30) NOT NULL, absence_date DATE NOT NULL, reason TEXT NOT NULL,
 created_by INT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id),
 KEY idx_notice_student_date(student_id,absence_date), KEY idx_notice_created_by(created_by),
 CONSTRAINT fk_notice_student FOREIGN KEY(student_id) REFERENCES student(student_id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_notice_user FOREIGN KEY(created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_log (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, student_id VARCHAR(30) NOT NULL, attendance_date DATE NOT NULL,
 notification_type ENUM('absence') NOT NULL DEFAULT 'absence', telegram_chat_id VARCHAR(50) NOT NULL,
 status ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending', telegram_message_id VARCHAR(50) NULL,
 error_message VARCHAR(255) NULL, attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0, attempted_at DATETIME NULL, sent_at DATETIME NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_notification_once(student_id,attendance_date,notification_type),
 KEY idx_notification_delivery(attendance_date,status,attempted_at),
 CONSTRAINT fk_notification_student FOREIGN KEY(student_id) REFERENCES student(student_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE telegram_update_log (update_id BIGINT NOT NULL, processed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(update_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE telegram_outbox (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, source ENUM('webhook','absence') NOT NULL, dedupe_key VARCHAR(191) NOT NULL,
 update_id BIGINT NULL, notification_log_id BIGINT UNSIGNED NULL, telegram_chat_id VARCHAR(50) NOT NULL, message_text TEXT NOT NULL,
 status ENUM('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending', attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, locked_at DATETIME NULL, last_error VARCHAR(255) NULL,
 telegram_message_id VARCHAR(50) NULL, sent_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY(id),
 UNIQUE KEY uq_telegram_outbox_source_dedupe(source,dedupe_key), UNIQUE KEY uq_telegram_outbox_update(update_id),
 UNIQUE KEY uq_telegram_outbox_notification(notification_log_id), KEY idx_telegram_outbox_delivery(status,next_attempt_at,id),
 CONSTRAINT fk_telegram_outbox_update FOREIGN KEY(update_id) REFERENCES telegram_update_log(update_id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_telegram_outbox_notification FOREIGN KEY(notification_log_id) REFERENCES notification_log(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT chk_telegram_outbox_reference CHECK ((source='webhook' AND update_id IS NOT NULL AND notification_log_id IS NULL) OR (source='absence' AND update_id IS NULL AND notification_log_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
