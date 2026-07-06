# Project Master Context - MMS

Dokumen ini adalah referensi utama untuk memahami konteks proyek `mms` secara cepat dan konsisten, terutama untuk onboarding, maintenance, debugging, dan deployment.

## 1. Ringkasan Proyek

- Nama sistem: `MMS` (Manufacturing Management System)
- Tipe aplikasi: PHP monolith (server-rendered), berbasis modul per domain bisnis.
- Entry point utama: `index.php`
- Stack utama:
  - PHP + PDO (MySQL/MariaDB)
  - Bootstrap (UI)
  - JavaScript vanilla (interaksi frontend)
- Fokus domain:
  - Sales, Engineering, PPIC, Purchasing, Produksi, QC, Warehouse, Finance, Accounting, HRD, Admin

## 2. Struktur Aplikasi (High-Level)

- `index.php`
  - Router utama berdasarkan `?page=` dan `?action=`.
  - Melakukan auth check dan permission check.
- `config/database.php`
  - Koneksi database.
- `config/functions.php`
  - Helper global (render, auth, upload helper, notification helper, dsb).
- `modules/`
  - Berisi modul per domain:
    - `modules/admin/...`
    - `modules/users/...`
    - `modules/sales/...`
    - `modules/engineering/...`
    - dst.
- `assets/`
  - CSS/JS statis dan upload company assets (`assets/uploads/company`).
- `uploads/`
  - Upload user assets seperti avatar (`uploads/avatars`) dan dokumen lain.
- `database/migrations/`
  - SQL migration tambahan.
- `docs/`
  - Dokumentasi teknis.

## 3. Routing Penting

- Dashboard: `index.php?page=dashboard`
- Identitas perusahaan: `index.php?page=admin-company`
- User setting (avatar/signature/password): `index.php?page=user-settings`

Mapping saat ini untuk admin company:
- `index.php?page=admin-company` -> `modules/admin/company/index.php`

## 4. Konsep Permission & Role

- Permission check dilakukan lewat helper `check_access(...)` dan `has_permission(...)`.
- Role + permission dimuat saat login ke session.
- Admin memiliki akses override pada banyak area.

## 5. Konteks Upload File (KrusiaI)

### 5.1 Lokasi upload aktif

- Logo perusahaan:
  - Folder: `assets/uploads/company`
  - Dikelola di: `modules/admin/company/index.php`
- Avatar user:
  - Folder: `uploads/avatars`
  - Dikelola di: `modules/users/settings.php`
- Tanda tangan user:
  - Folder: `assets/uploads/signatures`
  - Dikelola di: `modules/users/settings.php`

### 5.2 Helper upload universal

Helper utama ada di `config/functions.php`:

- `mms_project_root()`
- `mms_abs_path($relative_path)`
- `mms_upload_error_message($code)`
- `mms_upload_runtime_info($relative_dir)`
- `mms_log_upload_failure($stage, $detail)`
- `mms_ensure_writable_dir($relative_dir, &$error_message)`
- `mms_store_uploaded_image($file_info, $relative_dir, $prefix, &$error_message, $allowed_ext)`
- `mms_asset_url($path, $cache_bust=true)` untuk URL logo + cache busting

Tujuan helper:
- Konsisten lintas environment (XAMPP Windows dan cPanel Linux).
- Menyediakan diagnosa runtime upload.
- Menangani fallback pemindahan file (move/rename/copy) untuk kasus hosting tertentu.
- Mencatat log teknis saat gagal (`MMS_UPLOAD_FAIL` di `error_log`).

### 5.3 Diagnosa upload di UI

Halaman `admin-company` menampilkan blok diagnosa:
- Status folder upload (exists/writable)
- `upload_tmp_dir` (ini + effective)
- `file_uploads`, `upload_max_filesize`, `post_max_size`, dll
- Status logo aktif:
  - `logo_path (DB)`
  - `logo_url aktif`
  - `logo_file_exists`, `logo_file_size`, `logo_file_mtime`
  - status update logo saat submit

## 6. Known Issue Historis dan Status

### 6.1 Tabel `workflow_notification_events` tidak ada

- Gejala: error dashboard admin `SQLSTATE[42S02] ... workflow_notification_events doesn't exist`.
- Penanganan:
  - Dashboard dibuat defensif (cek tabel dulu).
  - Helper ensure table tersedia di runtime notification.

### 6.2 Upload sukses palsu di cPanel

- Gejala:
  - Data “berhasil diperbarui” tapi logo tidak berubah.
  - `logo_path` tidak berganti atau file upload tidak terkirim saat submit.
- Penyebab yang sering:
  - file part tidak lolos ke PHP (ModSecurity/WAF/proxy)
  - path folder salah/permission mismatch
  - cache asset lama
- Mitigasi yang sudah ditambahkan:
  - debug upload request di halaman admin-company
  - status update logo eksplisit (`berubah` / `tidak_berubah`)
  - cache busting URL aset
  - logging `MMS_UPLOAD_FAIL`

## 7. Konfigurasi cPanel (Rekomendasi Operasional)

File referensi:
- `.user.ini.example`
- `docs/UPLOAD_CPANEL.md`

Contoh konfigurasi `.user.ini`:

```ini
file_uploads=On
upload_tmp_dir=/home/<cpanel_user>/public_html/mms/tmp_upload
upload_max_filesize=10M
post_max_size=12M
max_file_uploads=20
memory_limit=128M
```

Catatan:
- Sesuaikan path dengan docroot aktual domain (mis. `/home/promindo/mms.promindolaser.com/tmp_upload`).
- Pastikan folder target writable.
- Flush OPcache setelah deploy perubahan inti.

## 8. Observability & Logging

### 8.1 Log aplikasi

- Error teknis upload dicatat ke PHP `error_log` dengan tag:
  - `MMS_UPLOAD_FAIL`

### 8.2 Cara baca cepat

- Jika upload gagal:
  1. Cek blok diagnosa di halaman admin-company
  2. Cek `error_log` cari `MMS_UPLOAD_FAIL`
  3. Korelasikan `stage` (mis. `tmp_missing`, `move_failed`, `target_dir`)

## 9. Checklist Deployment Aman

1. Pull/upload kode terbaru (terutama `config/functions.php`, `modules/admin/company/index.php`, `modules/users/settings.php`).
2. Pastikan folder ini ada + writable:
   - `assets/uploads/company`
   - `uploads/avatars`
   - `assets/uploads/signatures`
   - `tmp_upload` (jika dipakai `upload_tmp_dir` custom)
3. Set konfigurasi PHP upload (`file_uploads`, size limit, tmp dir).
4. Flush OPcache.
5. Hard refresh browser.
6. Uji upload:
   - logo
   - avatar
   - tanda tangan
7. Verifikasi path DB dan file mtime berubah.

## 10. Checklist Debug Cepat (Saat Ada Insiden)

Jika user bilang: “upload berhasil tapi file tidak berubah”

1. Cek `logo_path (DB)` berubah atau tidak.
2. Cek `logo_file_mtime` berubah atau tidak.
3. Cek `logo_update_status`.
4. Cek debug request:
   - `error`
   - `result`
   - `saved_path`
   - apakah key `$_FILES['logo']` ada
5. Cek `MMS_UPLOAD_FAIL` di `error_log`.
6. Jika `$_FILES` kosong tapi user sudah pilih file:
   - indikasi WAF/ModSecurity/proxy stripping upload part.

## 11. Konvensi Teknis

- Relative path upload disimpan ke DB (bukan absolute server path).
- Render aset pakai helper URL (`mms_asset_url`) untuk cache busting.
- Hapus file lama hanya setelah file baru sukses disimpan.
- Hindari hardcode path OS/environment.

## 12. Backlog Rekomendasi

1. Tambah halaman “System Diagnostics” khusus admin (gabungkan upload/db/cache/status).
2. Tambah integration test sederhana untuk upload (minimal smoke test).
3. Standarisasi semua modul print agar logo resolve konsisten via helper yang sama.
4. Tambah mekanisme audit siapa terakhir mengganti logo/avatar/signature.

---

Dokumen ini harus diperbarui setiap ada perubahan besar pada:
- route utama
- helper global
- strategi upload file
- struktur folder upload
- proses deployment hosting
