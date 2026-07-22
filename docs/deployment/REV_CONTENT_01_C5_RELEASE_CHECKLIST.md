# REV-CONTENT-01 C5 Release Checklist

Date: 2026-07-22

Branch: `feature/rev-content-01-information-center`

Verified baseline: `a563ef3b2222097f6a20a9741249af41e66cd88b`

C5 commit: the commit containing this checklist; resolve with `git rev-parse HEAD` after review.

Deployment status: not deployed and not pushed.

Status values are `PASS`, `FAIL`, `BLOCKED`, `NOT RUN`, and `NOTE`.

## Release gates

| Gate | Status | Evidence or required action |
|---|---|---|
| Exact baseline and clean pre-C5 worktree | PASS | Branch and 40-character baseline were verified before edits. |
| SQLite database guard | PASS | Effective target was `testing / sqlite / :memory:`. |
| Focused backend content tests | PASS | 61 tests, 564 assertions. |
| Full backend suite after C5 hardening | PASS | 413 tests, 4,230 assertions. |
| SQLite migrate, seed, repair rollback/re-apply | PASS | Guarded `content:verify-sqlite-migrations` completed. |
| Frontend content tests | PASS | 27 tests. |
| TypeScript | PASS | `tsc --noEmit`. |
| ESLint | PASS | Zero errors; six existing Fast Refresh warnings. |
| Client and SSR production build | PASS | Both build targets completed. |
| Manifest | PASS | Valid JSON, `/login` start URL, project-owned icon exists, no service worker. |
| Backend health | PASS | Guarded local `/api/v1/health` returned 200 and `status=ok`. |
| Frontend artifact smoke | PASS | Local preview returned 200 for the manifest and `/login`. This does not qualify as authenticated browser QA. |
| CORS and auth response privacy | PASS | Configured origin preflight works; untrusted origin is not echoed; login and `/auth/me` are private/no-store. |
| PostgreSQL migration and content suite | BLOCKED | Local server has no `silappkasal_test`; no database was created and `silappkasal` was never targeted. |
| Authenticated browser QA | BLOCKED | No browser automation harness and no persistent disposable application database were available. |
| 320/360/768/1024/desktop live viewport QA | NOT RUN | Must be completed with disposable accounts before deployment. |
| Permission matrix smoke | PASS | Backend policy/service tests plus frontend permission tests pass; live UI role smoke remains part of browser gate. |
| Campus content lifecycle smoke | PASS | Automated tests cover draft, revision request, resubmission, approval, publication, prior-pointer preservation, rejection, archive, and audit history. |
| Global second-review smoke | PASS | Automated tests enforce distinct author/reviewer and approved-only publication. |
| Featured-content smoke | PASS | Automated tests cover eligibility, ranks, windows, scope, conflicts, stale tokens, order, fallback, and removal. |
| Private attachment integrity | PASS | Automated tests cover PDF validation, authorization, safe projection/name, audit, clone integrity, deletion failure, and non-disclosing denial. |
| Composer validation | PASS | `composer validate --strict`. |
| Composer advisory audit | BLOCKED | Packagist was unreachable in the restricted environment. Rerun with release-network access. |
| Targeted REV-CONTENT Pint | PASS | All 99 REV-CONTENT PHP files pass. |
| Whole-repository Pint | NOTE | Existing formatting debt outside REV-CONTENT remains; no unrelated mass rewrite was made. |
| PHP syntax | PASS | All 99 REV-CONTENT PHP files pass `php -l`. |
| Secret/generated-file scan | PASS | No C5 secret, database, dependency directory, Graphify file, or service worker was added. |
| Production environment confirmation | BLOCKED | Real production values and secrets were intentionally not read or changed. |
| Production database backup | NOT RUN | No deployment was authorized. Backup must include PostgreSQL and private content bytes. |
| Frontend production runtime selection | BLOCKED | The VPS guide uses `vite preview` for demo only; approve a supported production runtime/adapter before production deployment. |
| Rollback readiness | PASS | Procedure is documented in `REV_CONTENT_01_C5_ROLLBACK.md`; backup creation/restore rehearsal remains an operator gate. |
| Deployment | NOT RUN | Explicitly prohibited for C5 preparation. |

## Release decision

Do not deploy REV-CONTENT-01 until all `BLOCKED` gates above are closed. In particular, a disposable
PostgreSQL run, authenticated multi-role browser QA, production environment confirmation, restorable
backup evidence, and an approved frontend production runtime are mandatory.

## Pre-deployment operator checklist

- [ ] Review the final local C5 commit and confirm the branch has not diverged.
- [ ] Run Composer advisory audit with approved network access.
- [ ] Create or receive an explicitly disposable local `silappkasal_test`; run the guarded PostgreSQL
  report procedure without using the development database.
- [ ] Complete every browser matrix row in `REV_CONTENT_01_C5_BROWSER_QA.md`.
- [ ] Confirm `APP_ENV=production`, `APP_DEBUG=false`, HTTPS URLs, exact CORS origin allowlist,
  `CONTENT_IMAGE_UPLOADS_ENABLED=false`, private storage permissions, queue worker, and log retention.
- [ ] Select and rehearse the frontend production runtime. Do not promote `vite preview` as a
  production server.
- [ ] Capture a verified PostgreSQL backup and a matching backup of `storage/app/private/content`.
- [ ] Preserve `APP_KEY` and audit fingerprint keys; confirm backup restoration in an isolated target.
- [ ] Enable maintenance mode, deploy reviewed code and matching client/SSR artifacts, install locked
  dependencies, migrate, cache configuration/routes, restart PHP/queue/frontend services, and then
  leave maintenance mode.
- [ ] Smoke-check `/up`, `/api/v1/health`, login/logout, all four roles, the full content lifecycle,
  reader visibility, PDF access, featured ordering, and audit history.
- [ ] Monitor 401/403/404/409/422/429/5xx rates, queue failures, PHP/frontend service logs, storage
  errors, and database health after release.
