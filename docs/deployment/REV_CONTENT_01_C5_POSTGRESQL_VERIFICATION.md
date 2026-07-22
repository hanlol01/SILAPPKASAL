# REV-CONTENT-01 C5 PostgreSQL Verification

Date: 2026-07-22

Status: `PASS`

Target: local disposable `silappkasal_test` only.

Development database touched: no.

## Safety boundary

Before every destructive database command, the effective test configuration was confirmed as:

```text
APP_ENV=testing
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=silappkasal_test
DB_URL=<empty>
TEST_DATABASE_CONFIRM=silappkasal_test
```

`test-database:verify --env=testing --confirm-database=silappkasal_test` passed. The main local
environment remained `APP_ENV=local` with database `silappkasal` and was never used for verification.
No password, application key, bearer token, or other secret was printed or recorded.

## Migration and database result

`content:verify-postgresql-migrations --env=testing` passed. It exercised the complete migration
chain, idempotent content seeding, publication constraints and triggers, repair rollback, and repair
re-application against the disposable target. A separate guarded `migrate:fresh --seed` also
completed the full Foundation and demo seed chain.

The run exposed and repaired PostgreSQL portability defects that SQLite did not surface:

- aggregate version-number allocation no longer applies unsupported `FOR UPDATE` to `MAX(...)`;
  the content item row remains the transaction lock;
- safe wildcard escaping uses an explicit `!` escape character across reader, management,
  governance, and featured queries;
- audit-log route binding rejects non-UUID identifiers before PostgreSQL receives the query;
- test notification assertions decode the framework's text-backed JSON projection portably;
- expected constraint failures use nested savepoints so they do not poison the outer test
  transaction.

## Test results

| Suite | Result |
|---|---|
| Focused PostgreSQL regression | PASS — 113 tests, 936 assertions, one alternate-profile skip |
| PostgreSQL C1-C4 content suite | PASS — 61 tests, 564 assertions |
| Full PostgreSQL backend suite | PASS — 413 tests, 4,227 assertions, one alternate-profile skip |
| Full SQLite backend suite after repairs | PASS — 413 tests, 4,230 assertions |

The skip is the intentional assertion that PHPUnit defaults to SQLite; it is not applicable while
the explicitly selected PostgreSQL profile is active.

## Release use

This report proves local PostgreSQL compatibility only. It does not authorize production migration,
seeding, or deployment. Production still requires a verified backup, maintenance window, reviewed
environment, and the coordinated rollback procedure.
