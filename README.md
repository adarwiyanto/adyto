# adyto

Aplikasi praktek mandiri berbasis PHP (tanpa framework besar) untuk manajemen pasien, kunjungan, resep, dan dokumen klinis.

## Modul baru: DICOM Viewer (MVP)

### Fitur utama
- Menu sidebar **DICOM Viewer** (`/dicom_viewer.php`).
- Tombol **Imaging (DICOM)** dari detail pasien (`/patient_detail.php?id=...`).
- Upload ZIP berisi file DICOM per pasien.
- Listing Study → Series → Instance.
- Viewer 2D berbasis Cornerstone (tanpa build system) dengan:
  - Pan
  - Zoom
  - WL/WW
  - Slice scroll (mouse wheel + slider)
  - Reset view
  - Overlay data dasar pasien/studi

### Keamanan
- Endpoint DICOM wajib login (mengikuti guard/session aplikasi).
- File DICOM disimpan di `storage/uploads/dicom` dan diberi `.htaccess` `Deny from all`.
- Akses file hanya lewat `dicom_api.php?action=wadouri&instance_id=...`.
- Upload hanya menerima ZIP, dibatasi ukuran maksimum dan validasi sederhana file DICOM.
- Ekstraksi ZIP memblokir path traversal (`../`) / zip-slip.

### Konfigurasi
Atur pada `app/config.php` (atau gunakan default di `app/config.sample.php`):

```php
'uploads' => [
  'dicom_dir' => __DIR__ . '/../storage/uploads/dicom',
  'dicom_max_upload_mb' => 100,
]
```

### Struktur tabel
Migration: `migrations/008_add_dicom_imaging_tables.sql`
- `imaging_studies`
- `imaging_series`
- `imaging_instances`

### Cara uji manual
1. Login sebagai user aplikasi.
2. Buka `patients.php` → detail pasien.
3. Klik **Imaging (DICOM)**.
4. Upload ZIP DICOM.
5. Pastikan study muncul di panel kiri.
6. Klik series dan cek gambar tampil.
7. Coba WL/WW, pan, zoom, slice wheel/slider, lalu reset.
8. Saat logout, akses `dicom_api.php?action=list_studies&patient_id=1` harus ditolak (redirect login).
