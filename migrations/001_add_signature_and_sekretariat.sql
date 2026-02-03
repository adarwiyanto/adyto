-- 1) Tambah signature per user dokter
ALTER TABLE users
  ADD COLUMN signature_path VARCHAR(255) NULL AFTER email;

-- 2) Tambah role sekretariat
ALTER TABLE users
  MODIFY role ENUM('admin','dokter','perawat','sekretariat') NOT NULL DEFAULT 'dokter';
