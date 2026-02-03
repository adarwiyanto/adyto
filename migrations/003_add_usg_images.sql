-- AMS add-on: tabel foto hasil USG per kunjungan (tidak mengubah tabel lama)

CREATE TABLE IF NOT EXISTS usg_images (
  id BIGINT(20) NOT NULL AUTO_INCREMENT,
  visit_id BIGINT(20) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  caption VARCHAR(120) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_visit_id (visit_id),
  CONSTRAINT fk_usg_images_visit
    FOREIGN KEY (visit_id)
    REFERENCES visits(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
