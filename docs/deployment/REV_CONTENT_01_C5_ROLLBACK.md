# REV-CONTENT-01 C5 Rollback Plan

Status: prepared, not executed.

Rule: never perform a destructive schema rollback without a verified pre-release backup.

## Required recovery set

Capture and verify these as one release set before maintenance begins:

- PostgreSQL dump with ownership/restore options appropriate to the target environment;
- private content bytes from `backend/api/storage/app/private/content`;
- reviewed application commit and the previously deployed commit;
- matching previous frontend artifacts and their build metadata;
- protected production environment/secret backup, especially `APP_KEY` and audit fingerprint keys;
- Nginx, PHP-FPM, queue, and frontend service definitions.

The database and private content storage must be restored from the same point in time. A database-only
restore can leave attachment rows without bytes; a storage-only restore can leave orphan bytes.

## Preferred rollback

1. Declare the incident and record the release commit, time, symptoms, and last known good commit.
2. Enter Laravel maintenance mode and stop user mutations. Stop queue workers after allowing or safely
   terminating current jobs according to the operations policy.
3. Capture a forensic backup of the failed state before changing code or data.
4. Roll application code and frontend artifacts back to the last reviewed release as one unit.
5. If no REV-CONTENT migration ran and no new content data was written, clear/cache configuration and
   routes, restart PHP-FPM, queue workers, and the approved frontend runtime, then verify health.
6. If content migrations ran or any content/editorial/audit data was written, restore the verified
   pre-release PostgreSQL backup and matching private-content backup. Do not use blind
   `migrate:rollback`.
7. Run `php artisan optimize:clear`, rebuild configuration/routes for the restored environment, restart
   services, then leave maintenance mode only after verification.
8. Verify `/up`, `/api/v1/health`, login/logout, operational reporting, role boundaries, audit access,
   and the absence/presence of Information Center routes expected by the restored release.
9. Monitor application, queue, database, storage, and reverse-proxy logs and document the incident.

## Why migration rollback is unsafe after use

`2026_07_21_000000_create_content_publication_tables.php` drops all content, version, decision,
attachment, category, section, and featured tables in `down()`. The RBAC reconciliation rollback also
removes the canonical content permissions. Once editorial or published data exists, these operations
destroy publication/audit history and disconnect private bytes. The constraint migration itself is
reversible, but rolling back only constraints does not provide an application-compatible rollback.

Therefore the supported data rollback after release is coordinated backup restoration, not a blind
migration step-down. Preserve audit and published-content history in the failed-state forensic backup.

## Verification after rollback

- Code and frontend artifacts report the intended last-known-good revision.
- Schema and application code are compatible.
- Queue workers run the same code revision as web workers.
- Cache/config/route state reflects the restored environment.
- PostgreSQL integrity checks and selected smoke tests pass.
- Private attachment rows and bytes reconcile; no private directory is publicly linked.
- Operational Report/Case/Evidence workflows remain unchanged.
- Audit history required by policy is present in the retained backup or restored database.
