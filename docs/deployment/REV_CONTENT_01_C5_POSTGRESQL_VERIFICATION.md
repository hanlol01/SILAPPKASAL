# REV-CONTENT-01 C5 PostgreSQL Verification

Date: 2026-07-22

Status: `BLOCKED`

Production database touched: no.

## Observed result

The safety guard resolved exactly:

```text
APP_ENV=testing
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=silappkasal_test
DB_URL=<empty>
TEST_DATABASE_CONFIRM=silappkasal_test
```

`test-database:verify --confirm-database=silappkasal_test` passed. The guarded migration command then
stopped before migration because PostgreSQL reported that `silappkasal_test` does not exist. C5 did
not create a database, try unrelated credentials, weaken the guard, or fall back to `silappkasal`.

Consequently PostgreSQL `migrate:fresh`, seed verification, constraint/trigger inspection, repair
rollback/re-apply, migration rollback, and the PostgreSQL content suite are blocked release gates.

## Authorized rerun procedure

An operator must first provision an explicitly disposable local database named exactly
`silappkasal_test` and supply its credentials through a secure local environment. Never copy the
commands below into a production shell without first inspecting the effective target.

1. Set only these non-secret safety values in the test process:

   ```text
   APP_ENV=testing
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_DATABASE=silappkasal_test
   DB_URL=
   TEST_DATABASE_CONFIRM=silappkasal_test
   ```

2. Load the dedicated test username/password from an approved secret source. Do not print them.
3. Run `php artisan test-database:verify --confirm-database=silappkasal_test`.
4. Stop immediately unless every printed safe target value matches this document.
5. Run `php artisan content:verify-postgresql-migrations`.
6. Run `php artisan test --configuration phpunit.postgresql.xml`.
7. Record migration, seed, constraints, indexes, triggers, rollback/re-apply, and suite results.
8. Confirm again that no connection or command targeted `silappkasal`.

`phpunit.postgresql.xml` includes all C1-C4 content suites: audit visibility, foundation, foundation
repair, fail-closed images, management, management repair, and governance.

## Acceptance criteria

- Guard and explicit confirmation pass.
- Full migration chain applies on PostgreSQL.
- Seed counts are exact and seeded reader visibility remains zero.
- PostgreSQL constraints and partial unique indexes are present and enforce expected failures.
- Repair migration rollback/re-apply passes.
- All configured PostgreSQL content tests pass.
- No production/development database was contacted or modified.
