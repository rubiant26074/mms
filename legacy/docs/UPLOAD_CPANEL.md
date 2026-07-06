# Setup Upload di cPanel

1. Salin `.user.ini.example` menjadi `.user.ini` di root aplikasi (`public_html/mms`).
2. Ganti `<cpanel_user>` dengan username cPanel.
3. Pastikan folder berikut ada dan writable:
   - `uploads/company`
   - `uploads/avatars`
   - `uploads/signatures`
   - `tmp_upload`
4. Jika upload masih gagal, cek `error_log` PHP dan cari baris dengan tag `MMS_UPLOAD_FAIL`.
5. Jika ada ModSecurity, minta whitelist untuk:
   - `index.php?page=admin-company`
   - `index.php?page=user-settings`
6. Pastikan file `.htaccess` di root ikut ter-upload (hardening production).
7. Folder `install/` diblok oleh `install/.htaccess` di production.
   Jika butuh reinstall, hapus/rename sementara lalu kembalikan lagi.
8. Jika ingin menstandarkan path upload lama (`assets/uploads/...`) ke path baru (`uploads/...`),
   jalankan migration: `database/migrations/20260214_09_normalize_upload_paths.sql`.
