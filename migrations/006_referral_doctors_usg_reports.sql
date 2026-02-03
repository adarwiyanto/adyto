/*
  AMS Patch v1.3 - Referral Doctors + USG Classification + Exam Reports
  Safe migration: only ADD tables/columns. Old data is preserved.

  Notes:
  - MariaDB 10.4 supports ADD COLUMN IF NOT EXISTS.
  - If your server does not support it, apply manually by checking existing columns first.
*/

CREATE TABLE IF NOT EXISTS referral_doctors (
  id BIGINT(20) NOT NULL AUTO_INCREMENT,
  doctor_name VARCHAR(150) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY is_active (is_active),
  KEY doctor_name (doctor_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/* Add new columns to visits (non-breaking) */
ALTER TABLE visits
  ADD COLUMN IF NOT EXISTS referral_doctor_id BIGINT(20) NULL AFTER doctor_id,
  ADD COLUMN IF NOT EXISTS is_usg TINYINT(1) NOT NULL DEFAULT 0 AFTER referral_doctor_id,
  ADD COLUMN IF NOT EXISTS usg_type ENUM('diagnostic','intervention') NULL AFTER is_usg;

/* Helpful indexes */
ALTER TABLE visits
  ADD INDEX IF NOT EXISTS idx_visits_is_usg (is_usg),
  ADD INDEX IF NOT EXISTS idx_visits_usg_type (usg_type),
  ADD INDEX IF NOT EXISTS idx_visits_referral_doctor_id (referral_doctor_id),
  ADD INDEX IF NOT EXISTS idx_visits_visit_date (visit_date);

/* Backfill: treat existing visits that already have USG report as USG diagnostic */
UPDATE visits
SET is_usg = 1,
    usg_type = COALESCE(usg_type, 'diagnostic')
WHERE (usg_report IS NOT NULL AND TRIM(usg_report) <> '')
  AND (is_usg IS NULL OR is_usg = 0);
