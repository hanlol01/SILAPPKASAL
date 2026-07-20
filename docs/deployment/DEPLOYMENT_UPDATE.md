# DEPLOYMENT_UPDATE.md

> SOP update SILAPPKASAL pada VPS yang sudah berjalan

## Prasyarat

Pastikan perubahan sudah:

- Commit
- Push ke GitHub

Jangan pernah mengubah kode langsung di VPS.

Flow wajib:

Local
↓
Git Commit
↓
Git Push
↓
VPS Git Pull

---

## REV-WF-03 R2 Migration Notes

R2 introduces two ordered production migrations:

1. `2026_07_20_000000_add_emergency_access_lifecycle_to_break_glass_requests.php`
2. `2026_07_20_010000_reconcile_r2_emergency_access_permissions.php`

Before running them, take and verify a PostgreSQL backup. The first migration preserves legacy
Break Glass history, backfills bounded grant windows, normalizes duplicate active rows
conservatively, and creates the active Report/requester partial unique index. The second migration
reconciles only Emergency Access permissions: Satgas request/reveal, Admin review, and no
operational permission for Super Admin or Reporter. Do not seed production.

After `php artisan migrate --force`, verify migration status, then smoke-check:

- Satgas assigned to an anonymous Case can submit a 30/60/240/1440-minute request;
- same-campus Admin sees and may approve the request but has no reveal action;
- requester reveal response has `Cache-Control: no-store` and `Pragma: no-cache`;
- another Satgas, another-campus Admin, and Super Admin receive denial;
- anonymous list/download filenames remain `supporting-file.{ext}` or
  `internal-evidence.{ext}` for internal readers;
- Reporter owner still sees the original Supporting File filename;
- R2 request/approve/reveal/revoke/expiry audit entries contain no identity or narratives.

Rollback is code-version coordinated. The schema rollback maps revoked grants to expired and
preserves legacy-compatible timestamps; do not roll back production without a verified backup and
an approved application rollback plan.

---

## Update Backend

cd /var/www/silappkasal

git pull

cd backend/api

composer install --no-dev --optimize-autoloader

php artisan migrate --force

php artisan optimize:clear

php artisan config:cache

php artisan route:cache

chown -R www-data:www-data storage bootstrap/cache

systemctl restart php8.3-fpm

---

## Update Frontend

cd /var/www/silappkasal/frontend

npm install

npm run build

systemctl restart silappkasal-frontend

---

## Verifikasi

Backend:

curl -X POST http://<SERVER_IP>/api/v1/auth/login

Frontend:

http://<APP_DOMAIN>

atau

http://<SERVER_IP>:8080

---

## Rollback

git log --oneline

git checkout <commit-id>

composer install

npm run build

systemctl restart php8.3-fpm

systemctl restart silappkasal-frontend

---

## REV-WF-03 Final Release Note (Not Yet Deployed)

Before deployment, take and verify a restorable PostgreSQL backup and confirm that the release
contains these migrations in order:

```text
2026_07_20_000000_add_emergency_access_lifecycle_to_break_glass_requests.php
2026_07_20_010000_reconcile_r2_emergency_access_permissions.php
2026_07_20_020000_add_final_case_closure.php
```

Run the migration while the application is in maintenance mode. Preserve the existing `APP_KEY`,
keep `SUPER_ADMIN_CROSS_CAMPUS_SENSITIVE_READ` at its approved production value, and do not run a
production seeder. The `010000` migration performs the required deployed RBAC reconciliation.

```bash
php artisan down
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan queue:restart
```

Restart the PHP/frontend services, then run `php artisan up` before public smoke testing. Verify:

- `case_final_summaries` exists with a unique `case_id`;
- `recoveries.discontinuation_reason` exists;
- the five final-summary/closure routes are registered;
- generic Case transition to `closed` is rejected;
- published Reporter projection and historical `legacy_completion` both load safely;
- frontend client and SSR artifacts were built from the same commit.

If rollback is required after any REV-WF-03 migration has run, keep the application in maintenance
mode and prefer restoring the verified pre-release PostgreSQL backup together with the pre-release
code checkpoint. The migration down path drops final-summary/discontinuation data and rewrites
Emergency Access lifecycle state, so a code-only rollback or blind production migration rollback
is not sufficient.

No production migration, deployment, seed, or environment change is performed as part of the R3 implementation commit.
