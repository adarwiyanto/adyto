-- AMS Migration 008: DICOM Imaging Tables

START TRANSACTION;

CREATE TABLE IF NOT EXISTS imaging_studies (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id BIGINT NOT NULL,
  study_uid VARCHAR(128) NOT NULL,
  accession_number VARCHAR(64) DEFAULT NULL,
  study_date VARCHAR(16) DEFAULT NULL,
  modality VARCHAR(32) DEFAULT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_imaging_studies_study_uid (study_uid),
  KEY idx_imaging_studies_patient_id (patient_id),
  KEY idx_imaging_studies_study_uid (study_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS imaging_series (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  study_id BIGINT NOT NULL,
  series_uid VARCHAR(128) NOT NULL,
  modality VARCHAR(32) DEFAULT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_imaging_series_study_uid (study_id, series_uid),
  KEY idx_imaging_series_study_id (study_id),
  KEY idx_imaging_series_series_uid (series_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS imaging_instances (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  series_id BIGINT NOT NULL,
  sop_instance_uid VARCHAR(128) NOT NULL,
  instance_number INT DEFAULT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_size BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_imaging_instances_sop_uid (sop_instance_uid),
  KEY idx_imaging_instances_series_id (series_id),
  KEY idx_imaging_instances_sop_uid (sop_instance_uid),
  KEY idx_imaging_instances_instance_number (instance_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
