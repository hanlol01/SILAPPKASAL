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

## REV-WF-03 R3 Release Note (Not Yet Deployed)

Before an R3 deployment, back up the database and verify that the release contains migration:

```text
2026_07_20_020000_add_final_case_closure.php
```

After the standard `php artisan migrate --force` step, verify:

- `case_final_summaries` exists with a unique `case_id`;
- `recoveries.discontinuation_reason` exists;
- the five final-summary/closure routes are registered;
- generic Case transition to `closed` is rejected;
- published Reporter projection and historical `legacy_completion` both load safely;
- frontend client and SSR artifacts were built from the same commit.

No production migration, deployment, seed, or environment change is performed as part of the R3 implementation commit.
