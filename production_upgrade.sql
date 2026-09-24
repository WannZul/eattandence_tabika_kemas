-- ONE-TIME additive upgrade from the exact pre-REVIEW production-modernization schema.
-- REQUIRED BASELINE: login_attempt already exists; student already has face_status/face_validated_at/
-- face_validation_message/usable_face_samples; telegram_outbox already exists in webhook-only form with
-- fk_telegram_outbox_update and uq_telegram_outbox_update. The new REVIEW fixes below do not yet exist.
-- MySQL 8.0+ / MariaDB 10.4+. Stop writes/kiosk/webhook/workers and take verified DB + face-storage backups.
-- DDL auto-commits and this script is intentionally not rerunnable. A different or partially upgraded baseline
-- requires an INFORMATION_SCHEMA inventory and a DBA-reviewed subset, not a blind rerun.
-- Face paths become deterministic immutable legacy paths. During the same maintenance window, move each
-- faces/<student_id>/01..06.jpg into faces/<student_id>/<face_dataset_version>/ before deploying the new PHP.
SET NAMES utf8mb4;
SET time_zone = '+08:00';

ALTER TABLE users
 ADD COLUMN credential_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER is_active,
 ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER credential_version;

ALTER TABLE login_attempt
 ADD KEY idx_login_attempt_pair_time(username_key,network_key,attempted_at);

ALTER TABLE student
 ADD COLUMN face_dataset_version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER class;

ALTER TABLE face_image
 ADD COLUMN dataset_version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER student_id,
 MODIFY COLUMN relative_path VARCHAR(220) NOT NULL;
UPDATE student s SET s.face_dataset_version=CONCAT('legacy-',SUBSTRING(SHA2(s.student_id,256),1,24))
 WHERE s.face_dataset_version IS NULL AND EXISTS(SELECT 1 FROM face_image f WHERE f.student_id=s.student_id);
UPDATE face_image f JOIN student s ON s.student_id=f.student_id
 SET f.dataset_version=s.face_dataset_version,
     f.relative_path=CONCAT(f.student_id,'/',s.face_dataset_version,'/',SUBSTRING_INDEX(REPLACE(f.relative_path,'\\','/'),'/',-1));
ALTER TABLE face_image
 MODIFY COLUMN dataset_version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 ADD KEY idx_face_image_student_version(student_id,dataset_version,id);

ALTER TABLE kiosk_event
 ADD COLUMN dataset_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER attendance_date,
 ADD COLUMN outcome ENUM('accepted','rejected') NOT NULL DEFAULT 'accepted' AFTER dataset_fingerprint,
 ADD COLUMN rejection_reason VARCHAR(50) NULL AFTER outcome,
 ADD KEY idx_kiosk_event_outcome(received_at,outcome);
-- Historical rows deliberately retain dataset_fingerprint=NULL; never fabricate their trained model.

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
-- Existing snapshots get no invented audit rows. After business review, legacy manual absences whose time was
-- merely the old action clock may be corrected with: UPDATE attendance SET time=NULL WHERE source='manual' AND status='Tidak Hadir';

ALTER TABLE notification_log
 MODIFY COLUMN status ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
 MODIFY COLUMN attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 MODIFY COLUMN attempted_at DATETIME NULL,
 ADD KEY idx_notification_delivery_v2(attendance_date,status,attempted_at);

-- Generalize the EXISTING webhook-only outbox without dropping its rows or update_id relationship.
ALTER TABLE telegram_outbox
 ADD COLUMN source ENUM('webhook','absence') NULL AFTER id,
 ADD COLUMN dedupe_key VARCHAR(191) NULL AFTER source,
 MODIFY COLUMN update_id BIGINT NULL,
 ADD COLUMN notification_log_id BIGINT UNSIGNED NULL AFTER update_id,
 MODIFY COLUMN status ENUM('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending';
UPDATE telegram_outbox SET source='webhook',dedupe_key=CONCAT('update:',update_id)
 WHERE source IS NULL AND update_id IS NOT NULL;
ALTER TABLE telegram_outbox
 MODIFY COLUMN source ENUM('webhook','absence') NOT NULL,
 MODIFY COLUMN dedupe_key VARCHAR(191) NOT NULL,
 ADD UNIQUE KEY uq_telegram_outbox_source_dedupe(source,dedupe_key),
 ADD UNIQUE KEY uq_telegram_outbox_notification(notification_log_id),
 ADD KEY idx_telegram_outbox_delivery_v2(status,next_attempt_at,id),
 ADD CONSTRAINT fk_telegram_outbox_notification FOREIGN KEY(notification_log_id) REFERENCES notification_log(id) ON UPDATE CASCADE ON DELETE CASCADE,
 ADD CONSTRAINT chk_telegram_outbox_reference CHECK ((source='webhook' AND update_id IS NOT NULL AND notification_log_id IS NULL) OR (source='absence' AND update_id IS NULL AND notification_log_id IS NOT NULL));

-- Verify current image counts, move legacy files, then run the reconciliation dry-run before deployment:
-- SELECT s.student_id,s.face_dataset_version,COUNT(f.id) images FROM student s LEFT JOIN face_image f ON f.student_id=s.student_id AND f.dataset_version=s.face_dataset_version GROUP BY s.student_id,s.face_dataset_version HAVING s.face_dataset_version IS NOT NULL AND COUNT(f.id)<>6;
-- php app/reconcile_face_versions.php
