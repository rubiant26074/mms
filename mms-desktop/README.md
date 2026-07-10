# MMS Desktop Wrapper (Electron)

Aplikasi desktop Windows wrapper untuk Manufacturing Management System (MMS).

## Cara Menjalankan untuk Development:

1. Pastikan Anda sudah menginstal **Node.js** di komputer Anda.
2. Buka terminal/cmd di folder `mms-desktop` ini.
3. Jalankan perintah untuk menginstal dependencies:
   ```bash
   npm install
   ```
4. Jalankan aplikasi desktop:
   ```bash
   npm start
   ```

## Cara Konfigurasi URL:
Ubah alamat server target di berkas `config.json`:
- Untuk uji coba lokal, gunakan: `"http://127.0.0.1:8000"`
- Untuk production, arahkan ke URL cPanel Anda: `"https://mms-yourdomain.com"`

## Cara Memaketkan menjadi `.exe` (Build Executable):
Jalankan perintah berikut:
```bash
npm run package
```
Aplikasi yang siap didistribusikan akan terbentuk di dalam folder `dist/MMS-Desktop-win32-x64/`. Anda dapat langsung membuat shortcut untuk file `MMS-Desktop.exe` di sana.
