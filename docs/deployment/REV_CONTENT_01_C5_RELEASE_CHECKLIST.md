# REV-CONTENT-01 C5 Release Checklist

Date: 2026-07-22

Branch: `feature/rev-content-01-information-center`

Verified starting baseline: `84138ada36b5f36ab8345aec5bc7870df003469f`

Deployment status: not deployed and not pushed.

Status values are `PASS`, `FAIL`, `BLOCKED`, `NOT RUN`, and `NOTE`.

## Release gates

| Gate | Status | Evidence or required action |
|---|---|---|
| Exact baseline and clean pre-verification worktree | PASS | Branch and 40-character baseline were verified before C5 completion work. |
| Test database guard | PASS | SQLite resolves to `testing / sqlite / :memory:`. PostgreSQL resolves only to `testing / pgsql / 127.0.0.1 / silappkasal_test` with matching explicit confirmation and empty `DB_URL`. |
| PostgreSQL migration, seed, rollback, and constraints | PASS | Guarded `content:verify-postgresql-migrations` completed against `silappkasal_test`; the development database was never targeted. |
| PostgreSQL focused regression suite | PASS | 113 tests, 936 assertions, one profile-specific skip. |
| PostgreSQL content suite | PASS | 61 tests, 564 assertions. |
| PostgreSQL full backend suite | PASS | 413 tests, 4,227 assertions, one profile-specific skip. |
| SQLite full backend suite | PASS | 413 tests, 4,230 assertions. |
| Frontend content tests | PASS | 27 tests. |
| TypeScript | PASS | `tsc --noEmit`. |
| ESLint | PASS | Zero errors; six existing Fast Refresh warnings. |
| Client and SSR production build | PASS | `npm run build` completed both build targets; the existing large-chunk warning remains. |
| Authenticated role and viewport smoke | PASS | Chrome 150 headless covered Reporter at 320/360, Satgas at 768, Admin at 1024, and Super Admin at 1440 with disposable PostgreSQL data. |
| Login, logout, account replacement, and cache isolation | PASS | Real sessions changed between roles; the private reader cache did not cross account boundaries. |
| Reader permission denial | PASS | Removing `content.read.published` hid Reporter navigation; direct UI access was denied and the API returned private/no-store 403. Permission was restored after the check. |
| Browser history and mobile filters | PASS | Back/Forward restored Information Center URL state; the mobile filter Sheet and associated labels worked. |
| Campus authoring to Super Admin publication | PASS | A disposable campus Article was created, saved, submitted, reviewed, approved, published, and became visible to Reporter. |
| Browser PDF popup/fallback | BLOCKED | No safe published PDF fixture was available for a browser-level run. Automated behavior and backend authorization tests pass. |
| Browser keyboard Select/Carousel/Accordion matrix | BLOCKED | Component behavior tests pass, but the complete real-keyboard matrix was not executed for every target viewport. |
| Post-fix browser overflow/touch recheck | BLOCKED | Initial run found a 1024 px editor overflow and sub-44 px controls. Scoped fixes build and lint successfully, but preview restart was unavailable, so rendered remeasurement is still required. |
| Composer validation | PASS | `composer validate --strict`. |
| Composer advisory audit | PASS | Guzzle security updates are locked; `composer audit --locked --no-interaction` reports no advisories. |
| Targeted formatting and PHP syntax | PASS | Changed PHP files pass Pint and `php -l`. |
| Route, locale, manifest, and diff inspection | PASS | Content/audit routes, ID/EN behavior tests, manifest JSON/no-service-worker boundary, and `git diff --check` pass. |
| Credential rotation review | BLOCKED | A key value was transiently present in the example environment file during workspace inspection and was removed before commit. If that value belongs to any active environment, rotate it before release; the value is not recorded here. |
| Frontend production runtime | BLOCKED | Cloudflare Workers is the intended candidate, but the build emits no `.wrangler/deploy/config.json` or generated output `wrangler.json`; direct Wrangler dry-run tries to bundle source virtual modules and fails. `vite preview` is QA-only. |
| Production environment confirmation | BLOCKED | Actual production environment values were intentionally not read or changed. |
| Restorable production backup evidence | BLOCKED | No production deployment was authorized; PostgreSQL and private-content backup creation/restore evidence was not supplied. |
| Deployment | NOT RUN | Explicitly prohibited. |

## Release decision

Do not deploy REV-CONTENT-01 yet. PostgreSQL, Composer, and the core authenticated role flow now pass,
but the supported frontend deployment artifact, remaining live browser matrix, actual production
environment confirmation, and restorable backup evidence are mandatory open gates.

## Pre-deployment operator checklist

- [ ] Rebuild and prove a supported Cloudflare Workers output configuration and successful local
  `wrangler deploy --dry-run`; do not deploy `src/server.ts` directly.
- [ ] Restart the built preview and remeasure the corrected 1024 px editor and all 44 px targets.
- [ ] Complete keyboard Select/Carousel/Accordion and authenticated PDF popup/fallback browser checks.
- [ ] Confirm `APP_ENV=production`, `APP_DEBUG=false`, HTTPS URLs, the exact CORS origin allowlist,
  `CONTENT_IMAGE_UPLOADS_ENABLED=false`, private storage permissions, worker health, and log retention.
- [ ] Capture matching PostgreSQL and `storage/app/private/content` backups and prove restoration in an
  isolated target. Preserve `APP_KEY` and audit fingerprint keys without exposing them.
- [ ] Deploy backend and frontend artifacts from the same reviewed commit only after every blocked gate
  above is closed.
- [ ] Smoke-check `/up`, `/api/v1/health`, login/logout, all four roles, the content lifecycle, reader
  visibility, private PDF access, featured ordering, and audit history.
