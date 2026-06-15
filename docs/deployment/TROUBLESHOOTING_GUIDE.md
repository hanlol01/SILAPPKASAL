# TROUBLESHOOTING_GUIDE.md

> Quick Troubleshooting Reference
>
> Gunakan dokumen ini saat lupa prosedur atau terjadi masalah pada VPS, deployment, update, backup, dan recovery.

---

# 1. VPS Mati Total

## Gejala

* Tidak bisa SSH
* Tidak bisa Ping
* VPS suspended
* VPS dihapus provider
* Data VPS hilang

## Solusi

Beli VPS baru dan lakukan deployment ulang.

## Dokumen yang Digunakan

* VPS_DISASTER_RECOVERY.md
* DEPLOYMENT_DEMO_VPS.md
* ENV_TEMPLATE.md

## Prompt ke Agent

Saya kehilangan akses ke VPS lama.

Berikut dokumen:

* VPS_DISASTER_RECOVERY.md
* DEPLOYMENT_DEMO_VPS.md
* ENV_TEMPLATE.md

Detail VPS baru:

IP: xxx.xxx.xxx.xxx
OS: Ubuntu 22.04

Pandulah saya langkah demi langkah sampai SILAPPKASAL berjalan kembali.

---

# 2. Ingin Update Progress dari Local ke VPS

## Kondisi

Kode terbaru sudah:

* Git Commit
* Git Push

## Solusi

Login ke VPS.

Jalankan:

```bash
cd /var/www/silappkasal

git pull
```

Backend:

```bash
cd backend/api

composer install --no-dev --optimize-autoloader

php artisan migrate --force

php artisan optimize:clear

php artisan config:cache

php artisan route:cache

systemctl restart php8.3-fpm
```

Frontend:

```bash
cd /var/www/silappkasal/frontend

npm install

npm run build

systemctl restart silappkasal-frontend
```

## Dokumen yang Digunakan

* DEPLOYMENT_UPDATE.md

## Prompt ke Agent

Saya sudah push progress terbaru ke GitHub.

Tolong pandu proses update VPS berdasarkan DEPLOYMENT_UPDATE.md.

---

# 3. Frontend Tidak Bisa Dibuka

## Gejala

* Browser blank
* 502 Bad Gateway
* Connection refused

## Cek

```bash
systemctl status silappkasal-frontend
```

## Restart

```bash
systemctl restart silappkasal-frontend
```

## Cek Log

```bash
journalctl -u silappkasal-frontend -n 100 --no-pager
```

## Dokumen yang Digunakan

* DEPLOYMENT_DEMO_VPS.md

---

# 4. Backend API Error 500

## Cek Log Laravel

```bash
tail -n 100 storage/logs/laravel.log
```

## Cek PHP

```bash
systemctl status php8.3-fpm
```

## Restart

```bash
systemctl restart php8.3-fpm
```

## Dokumen yang Digunakan

* DEPLOYMENT_DEMO_VPS.md

---

# 5. Database Tidak Terkoneksi

## Gejala

* SQLSTATE error
* Login gagal
* Migration gagal

## Cek PostgreSQL

```bash
systemctl status postgresql
```

## Restart

```bash
systemctl restart postgresql
```

## Cek Konfigurasi

Periksa:

```env
DB_HOST
DB_DATABASE
DB_USERNAME
DB_PASSWORD
```

## Dokumen yang Digunakan

* ENV_TEMPLATE.md

---

# 6. Ingin Pindah Domain

## Kondisi

Aplikasi berjalan.

Domain berubah.

## Solusi

Update:

Backend:

```env
APP_URL
FRONTEND_URL
SANCTUM_STATEFUL_DOMAINS
```

Frontend:

```env
VITE_API_BASE_URL
```

Rebuild frontend.

Update DNS.

## Dokumen yang Digunakan

* DEPLOYMENT_DEMO_VPS.md

---

# 7. Ingin Backup Database

## Jalankan

```bash
pg_dump -U <DB_USER> <DB_NAME> > backup-latest.sql
```

## Simpan

* Laptop
* Google Drive

## Dokumen yang Digunakan

* BACKUP_STRATEGY.md

---

# 8. Ingin Restore Database

## Buat Database

```sql
CREATE DATABASE silappkasal;
```

## Restore

```bash
psql silappkasal < backup-latest.sql
```

## Verifikasi

```bash
php artisan tinker
```

```php
App\Models\User::count();
```

## Dokumen yang Digunakan

* BACKUP_STRATEGY.md
* VPS_DISASTER_RECOVERY.md

---

# 9. Membuka Chat Baru dengan Agent AI

## Dokumen yang Wajib Dikirim

* PROJECT_HANDOFF.md
* ROADMAP.md
* AI_AGENT_BOOTSTRAP.md

## Jika Terkait Deployment

Tambahkan:

* DEPLOYMENT_DEMO_VPS.md
* DEPLOYMENT_UPDATE.md
* VPS_DISASTER_RECOVERY.md

## Prompt

Baca seluruh dokumen terlebih dahulu.

Gunakan dokumen sebagai source of truth.

Pandulah saya langkah demi langkah dan tunggu konfirmasi sebelum melanjutkan ke langkah berikutnya.

---

# 10. Ingin Isi Data Laporan Demo di VPS

## Kondisi

Akun demo sudah tersedia:

* `demo.superadmin@silappkasal.test`
* `demo.admin@silappkasal.test`
* `demo.satgas@silappkasal.test`
* `demo.reporter@silappkasal.test`

Password demo:

```text
DemoPass123!
```

## Tujuan

Mengisi laporan simulasi yang terlihat seperti progress nyata untuk rekap proyek:

* Superadmin dan admin dapat melihat laporan/kasus secara global.
* Satgas dapat melihat kasus yang sudah ditugaskan.
* Reporter dapat melihat laporan miliknya sendiri di portal.

## Jalankan di VPS

Masuk ke folder backend:

```bash
cd /var/www/silappkasal/backend/api
```

Backup database terlebih dahulu:

```bash
pg_dump -U <DB_USER> <DB_NAME> > ~/silappkasal-before-demo-report-seed.sql
```

Pastikan kode dan database terbaru:

```bash
git -C /var/www/silappkasal pull

composer install --no-dev --optimize-autoloader

php artisan migrate --force
```

Jalankan seed laporan demo:

```bash
SILAPPKASAL_ALLOW_DEMO_REPORT_SEED=true php artisan db:seed --class=DeploymentDemoReportSeeder --force
```

Bersihkan cache aplikasi:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
systemctl restart php8.3-fpm
```

## Verifikasi Cepat

```bash
php artisan tinker
```

Lalu ketik:

```php
App\Models\Report::where('registration_number', 'like', 'SLP-20260615-%')->count();
App\Models\CaseRecord::where('case_number', 'like', 'CASE-20260615-%')->count();
App\Models\User::where('email', 'demo.reporter@silappkasal.test')->first()?->reports()->count();
```

Hasil yang diharapkan:

* Jumlah laporan demo: `5`
* Jumlah kasus demo: `3`
* Reporter demo memiliki laporan: `5`

## Catatan Aman

Seeder ini tidak otomatis berjalan dari `DatabaseSeeder`.

Seeder hanya bisa dijalankan jika command memakai:

```bash
SILAPPKASAL_ALLOW_DEMO_REPORT_SEED=true
```

Jika command seed dijalankan ulang, data demo akan di-update, bukan digandakan.

---

# 11. Rule Penting

Source of Truth:

1. GitHub Repository
2. PostgreSQL Backup
3. Documentation

Jangan pernah menganggap VPS sebagai source of truth.

VPS boleh hilang.

Code, data, dan dokumentasi tidak boleh hilang.
