-- Tambah fitur Rujukan (tidak mengubah tabel lama, hanya menambah tabel baru)
CREATE TABLE IF NOT EXISTS referrals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visit_id INT NULL,
  patient_id INT NOT NULL,
  sender_doctor_id INT NOT NULL,
  referral_no VARCHAR(32) NOT NULL UNIQUE,
  referred_to_doctor VARCHAR(120) NOT NULL,
  referred_to_specialty VARCHAR(120) NOT NULL,
  diagnosis TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,

  KEY idx_patient_id (patient_id),
  KEY idx_visit_id (visit_id),
  KEY idx_sender_doctor_id (sender_doctor_id),

  CONSTRAINT fk_ref_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_ref_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL,
  CONSTRAINT fk_ref_sender FOREIGN KEY (sender_doctor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
