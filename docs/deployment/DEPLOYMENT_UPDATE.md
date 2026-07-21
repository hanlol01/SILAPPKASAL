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

## REV-CONTENT-01 C1 Release Note (Not Yet Deployed)

C1 adds `2026_07_21_000000_create_content_publication_tables.php`,
`2026_07_21_010000_reconcile_content_permissions.php`, and repair migration
`2026_07_21_020000_harden_content_publication_constraints.php`; it also adds Symfony HtmlSanitizer
7.4.14.
No production migration, seed, deployment, storage symlink, or environment mutation is performed by
the implementation commit.

Before a future release: back up PostgreSQL, preserve `APP_KEY` because editorial/review/original-name
fields are encrypted, run `composer install --no-dev --optimize-autoloader`, migrate in maintenance
mode, and verify the 12 authenticated content routes. Do not run `ContentFoundationSeeder` in
production until the product owner explicitly approves the 41 Article and eight FAQ draft records.
The seeder never publishes content, but it still creates editorial work records.

The current runtime has no GD, Imagick, or EXIF extension. Image upload therefore fails closed by
default. `CONTENT_IMAGE_UPLOADS_ENABLED=true` must not be set until an approved runtime processor is
installed, bound through `ContentImageProcessor`, and verified to normalize orientation, remove
metadata, and re-encode output. JPG, JPEG, PNG, and WebP all remain fail-closed when runtime
capability is unavailable, including when the feature flag is enabled. PDF general attachments
remain supported.

Before any destructive test verification, run `test-database:verify` and inspect the printed
environment, driver, host, and database. The default suite must resolve to SQLite `:memory:`. Local
PostgreSQL verification may use only `silappkasal_test`, with
`TEST_DATABASE_CONFIRM=silappkasal_test` and `--confirm-database=silappkasal_test`. Never use
`silappkasal`. PostgreSQL verification remains a release gate when a disposable test database is not
available.

The project test base verifies the effective configuration after Laravel application bootstrap and
before database test traits run. Cached configuration that resolves to a non-testing environment,
SQLite file, non-local PostgreSQL host, non-empty `DB_URL`, or any PostgreSQL database other than the
explicitly confirmed `silappkasal_test` aborts setup. Ordinary test runs do not silently delete or
rewrite the developer's configuration cache; use the repository `composer test` script when an
explicit `config:clear` pre-step is intended.

Rollback of the publication-table migration removes all C1 content data. Prefer restoring the
verified pre-release database backup; never use a blind production rollback after editorial work has
begun.

## REV-CONTENT-01 C2 Release Note (Not Yet Deployed)

C2 adds no dependency and no database migration. It adds the campus Admin management page and
campus-scoped management list/detail/summary, eligible Consultation CTA, published-revision, and
editable-PDF-removal APIs. A future deployment must publish frontend assets and backend code from the
same verified commit, then confirm the management routes with `php artisan route:list
--path=content-management`.

Image uploads remain disabled and must not be enabled for C2. PDF attachments stay on the private
`content` disk; no public storage symlink is required. C2 automated verification must remain on
SQLite `:memory:`. No production migration, seed, push, or deployment is claimed by this note.

C2 integrity repair adds no dependency or migration. Deployment verification must additionally
confirm mandatory submit `lock_version`, 404 non-disclosure for out-of-campus management UUIDs,
archived read-only errors, private-query removal on auth changes, and rollback-safe private PDF
deletion failure behavior.

## REV-CONTENT-01 C3 Release Note (Not Yet Deployed)

C3 adds no dependency and no database migration. It adds Super Admin editorial governance APIs and
`/dashboard/content-governance`, enables global authoring through the existing management aggregate,
and adds featured placement governance. No production migration, seed, push, or deployment is
claimed by this note.

A future release must deploy backend and client/SSR artifacts from the same verified commit. Before
service restart, inspect routes with:

```text
php artisan route:list --path=content-governance
php artisan route:list --path=content-management
```

Smoke verification must use disposable non-production records and confirm:

- Campus Admin, Reporter, and Satgas cannot enter governance routes;
- Super Admin sees submitted campus content but cannot edit its body or attachments;
- revision/rejection/archive reasons are required and author feedback is campus-scoped;
- approval and publication are distinct and stale `lock_version` returns 409;
- global author self-review is denied and a different Super Admin can complete review;
- no direct global publication method remains and only the authoritative approved version publishes;
- Published Content and reader APIs retain the prior published pointer while a revision is rejected
  or approved but not published;
- governance PDFs are retrieved with authenticated bytes and temporary Blob URLs, not raw anchors;
- governance read requests propagate cancellation signals before private query caches are removed;
- published archive removes the item from authenticated reader APIs;
- featured update/removal rejects a stale opaque concurrency token;
- all governance responses remain `private, no-store`;
- logout/account replacement removes private management and governance query caches.

Keep image uploads disabled. C3 adds neither scheduled publication nor the Reporter carousel: featured
date windows affect only placement visibility after an Article is already published. Automated tests
must continue to run on SQLite `:memory:`; any later PostgreSQL verification is limited to the
explicitly confirmed local `silappkasal_test` database and remains a release gate.
