-- AMS Migration 005: Dokter Perujuk + Klasifikasi Pemeriksaan (USG) + Rekapan
-- Aman untuk data lama: hanya menambah tabel/kolom; data lama dibiarkan.

START TRANSACTION;

-- 1) Master daftar dokter perujuk (diisi dari halaman setting)
CREATE TABLE IF NOT EXISTS referral_doctors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_referral_doctors_name (name),
  INDEX (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) Tambahan ke tabel visits: dokter perujuk + flag USG + tipe USG
ALTER TABLE visits
  ADD COLUMN referral_doctor_id INT NULL AFTER doctor_id,
  ADD COLUMN is_usg TINYINT(1) NOT NULL DEFAULT 0 AFTER referral_doctor_id,
  ADD COLUMN usg_type ENUM('diagnostic','interventional') NULL AFTER is_usg;

ALTER TABLE visits
  ADD INDEX idx_visits_referral_doctor_id (referral_doctor_id),
  ADD INDEX idx_visits_is_usg (is_usg),
  ADD INDEX idx_visits_usg_type (usg_type);

-- 3) Backfill untuk data lama: jika usg_report terisi, anggap sebagai USG diagnostik
UPDATE visits
SET is_usg = 1
WHERE usg_report IS NOT NULL AND TRIM(usg_report) <> '';

UPDATE visits
SET usg_type = 'diagnostic'
WHERE is_usg = 1 AND usg_type IS NULL;

COMMIT;
