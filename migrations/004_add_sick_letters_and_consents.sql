-- Surat Sakit
CREATE TABLE IF NOT EXISTS sick_letters (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  visit_id BIGINT NOT NULL,
  patient_id BIGINT NOT NULL,
  doctor_id INT NULL,
  letter_no VARCHAR(30) NOT NULL UNIQUE,
  diagnosis MEDIUMTEXT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  notes MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_sick_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  CONSTRAINT fk_sick_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  INDEX(visit_id),
  INDEX(patient_id),
  INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Informed Consent
CREATE TABLE IF NOT EXISTS consents (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  visit_id BIGINT NOT NULL,
  patient_id BIGINT NOT NULL,
  doctor_id INT NULL,
  consent_no VARCHAR(30) NOT NULL UNIQUE,
  procedure_name VARCHAR(255) NOT NULL,
  diagnosis MEDIUMTEXT NULL,
  risks MEDIUMTEXT NULL,
  benefits MEDIUMTEXT NULL,
  alternatives MEDIUMTEXT NULL,
  notes MEDIUMTEXT NULL,
  signer_name VARCHAR(255) NULL,
  signer_relation VARCHAR(255) NULL,
  signature_path VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_consent_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  CONSTRAINT fk_consent_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  INDEX(visit_id),
  INDEX(patient_id),
  INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
