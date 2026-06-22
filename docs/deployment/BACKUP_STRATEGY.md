# BACKUP_STRATEGY.md

> Strategi Backup SILAPPKASAL

## Source of Truth

Code:
- GitHub Repository

Data:
- PostgreSQL

Knowledge:
- Documentation

---

## Daily Backup

Jalankan:

pg_dump -U silappkasal_user silappkasal > backup-YYYY-MM-DD.sql

Contoh:

pg_dump -U silappkasal_user silappkasal > backup-2026-06-14.sql

---

## Lokasi Backup

Minimal dua lokasi:

1. Laptop
2. Cloud Storage

Contoh:

- Google Drive
- OneDrive
- Dropbox

---

## Weekly Backup

Simpan:

- Database dump terbaru
- Copy file .env
- Copy deployment docs

---

## Monthly Restore Test

Minimal sebulan sekali:

1. Buat database kosong
2. Restore backup

psql silappkasal_test < backup.sql

3. Verifikasi data

---

## Restore Procedure

Create database:

CREATE DATABASE silappkasal;

Restore:

psql silappkasal < backup-latest.sql

Verifikasi:

php artisan tinker

App\Models\User::count();

---

## Backup Checklist

Daily

[ ] PostgreSQL dump dibuat

[ ] Backup tersimpan lokal

[ ] Backup tersimpan cloud

Weekly

[ ] Verifikasi file backup dapat dibuka

Monthly

[ ] Restore test berhasil
