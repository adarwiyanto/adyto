-- AMS Migration 007: Public Verify Link + QR + Access Tracking
-- Aman untuk data lama: hanya menambah tabel & key settings baru. Data lama dibiarkan.

START TRANSACTION;

-- 1) Token untuk dokumen publik
CREATE TABLE IF NOT EXISTS public_documents (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  token CHAR(32) NOT NULL,
  doc_type VARCHAR(40) NOT NULL,
  doc_id BIGINT NOT NULL,
  doc_no VARCHAR(40) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  revoked TINYINT(1) NOT NULL DEFAULT 0,
  revoked_at DATETIME DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  UNIQUE KEY uq_public_documents_token (token),
  UNIQUE KEY uq_public_documents_doc (doc_type, doc_id),
  INDEX idx_public_documents_doc_type (doc_type),
  INDEX idx_public_documents_revoked (revoked)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) Log akses (tracking) untuk verifikasi
CREATE TABLE IF NOT EXISTS public_document_access_logs (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  public_document_id BIGINT NOT NULL,
  accessed_at DATETIME NOT NULL,
  ip VARCHAR(80) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  INDEX idx_pd_access_doc (public_document_id),
  INDEX idx_pd_access_time (accessed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3) Setting QR provider (opsional) - safe insert
INSERT IGNORE INTO settings(`key`,`value`) VALUES
  ('qr_provider', 'qrserver'),
  ('qr_size', '180');

COMMIT;
