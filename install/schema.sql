-- Users & roles
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','dokter','perawat') NOT NULL DEFAULT 'dokter',
  full_name VARCHAR(120) DEFAULT NULL,
  sip VARCHAR(80) DEFAULT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  email VARCHAR(120) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(80) NOT NULL UNIQUE,
  `value` MEDIUMTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL,
  user_id INT NULL,
  action VARCHAR(60) NOT NULL,
  meta MEDIUMTEXT NULL,
  INDEX (created_at),
  INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Patients
CREATE TABLE IF NOT EXISTS patients (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  mrn VARCHAR(30) NOT NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  dob DATE NULL,
  gender ENUM('L','P') NOT NULL,
  address VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Visits
CREATE TABLE IF NOT EXISTS visits (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  patient_id BIGINT NOT NULL,
  visit_no VARCHAR(30) NOT NULL UNIQUE,
  visit_date DATETIME NOT NULL,
  anamnesis MEDIUMTEXT NULL,
  physical_exam MEDIUMTEXT NULL,
  usg_report MEDIUMTEXT NULL,
  therapy MEDIUMTEXT NULL,
  doctor_id INT NULL,
  signature_path VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  INDEX(patient_id),
  INDEX(visit_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Prescriptions
CREATE TABLE IF NOT EXISTS prescriptions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  visit_id BIGINT NOT NULL,
  rx_no VARCHAR(30) NOT NULL UNIQUE,
  content MEDIUMTEXT NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  INDEX(visit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
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
  FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  INDEX(visit_id),
  INDEX(patient_id),
  INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;