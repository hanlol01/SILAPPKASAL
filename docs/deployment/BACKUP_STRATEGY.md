# BACKUP_STRATEGY.md

> Strategi Backup SILAPPKASAL

## Source of Truth

Code:
- GitHub Repository

Data:
- PostgreSQL
- Private content and Evidence storage

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
- Encrypted copy of `backend/api/storage/app/private/content`
- Encrypted copy of other required private storage according to the Evidence retention policy

---

## Monthly Restore Test

Minimal sebulan sekali:

1. Buat database kosong
2. Restore backup

psql silappkasal_test < backup.sql

3. Verifikasi data

---

## Restore Procedure

Never restore directly over production as the first verification. Restore the database into an
isolated disposable target, restore the matching private-storage snapshot, and verify integrity before
an approved production recovery window. Preserve the failed-state database and files for audit and
incident review.

Create database:

CREATE DATABASE <DISPOSABLE_RESTORE_DATABASE>;

Restore:

psql <DISPOSABLE_RESTORE_DATABASE> < backup-latest.sql

Verifikasi:

php artisan tinker

App\Models\User::count();

For REV-CONTENT-01, also reconcile content attachment rows with
`storage/app/private/content`. Database and private bytes must come from the same recovery point.
Only after this isolated restore passes may an authorized incident procedure restore the exact
production target while the application remains in maintenance mode.

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

[ ] PostgreSQL and private-content backups share one recovery point

[ ] Content attachment rows and private bytes reconcile
