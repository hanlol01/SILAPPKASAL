# REV-CONTENT-01 C5 Authenticated Browser QA

Date: 2026-07-22

Status: `PARTIAL — RELEASE BLOCKERS REMAIN`

Browser: Chrome 150 headless through the DevTools protocol.

Data: disposable identities and content in guarded local PostgreSQL `silappkasal_test` only.

## Executed matrix

| Actor / width | Result | Verified behavior |
|---|---|---|
| Reporter / 320 px | PASS | Login, Information Center navigation, mobile filter Sheet, labels, search/filter URL history, no horizontal reader overflow. |
| Reporter / 360 px | PASS | Login, reader navigation, mobile controls, no horizontal reader overflow. |
| Satgas / 768 px | PASS | Published-content permission and Information Center access. |
| Campus Admin / 1024 px | PASS WITH FIX | Campus Article create, save, and submit. Initial editor overflow was identified and corrected in source. |
| Super Admin / 1440 px | PASS | Governance queue, review start, approval, publication, and desktop reader access. |
| Reporter without published permission | PASS | Menu hidden, direct route denied, API 403 with `private, no-store`; permission restored after test. |

The publication handoff was verified end to end: a disposable campus Article was authored and
submitted by Admin, approved and published by Super Admin, and then appeared to Reporter. Logout,
login as another role, and account replacement were exercised with real authenticated sessions.
Back/Forward restored selected Information Center state.

## Findings and scoped fixes

The first rendered run found two responsive defects:

- the structured-document link row expanded the Admin editor to 1,328 px at a 1,024 px viewport;
- several mobile header and pagination controls were smaller than the 44 px target.

The editor containers and URL input now use `min-w-0`, while navigation, language, theme, user,
sidebar, and pagination controls use 44 px mobile targets. Frontend behavior tests, TypeScript,
ESLint, and client/SSR builds pass after these changes. A built-preview restart was unavailable in
the execution environment, so rendered post-fix remeasurement remains a release gate.

## Remaining live checks

| Scenario | Status | Required evidence |
|---|---|---|
| Post-fix 320/360/768/1024/1440 overflow and touch remeasurement | BLOCKED | Restart the built preview and record final dimensions. |
| Keyboard Select, Carousel, and Accordion across target widths | BLOCKED | Execute real keyboard navigation, focus order, activation, and Escape behavior. |
| Authenticated PDF preview and popup-blocked fallback | BLOCKED | Publish a disposable safe PDF fixture and verify bearer fetch, fallback download, and Object URL cleanup. |
| Rejected and archived visibility in rendered reader | NOTE | PostgreSQL lifecycle tests pass; add browser evidence before deployment. |
| iOS/Safari manifest and responsive smoke | NOTE | Not available in this local Windows/Chrome run. |

## Automated supporting evidence

- frontend content behavior tests: 27 passed;
- TypeScript: passed;
- ESLint: zero errors and six pre-existing Fast Refresh warnings;
- client and SSR build: passed with the pre-existing large-chunk warning;
- backend reader, authorization, publication-pointer, rejection, archive, attachment, and cache
  boundaries pass on both PostgreSQL and SQLite.

Automated evidence does not replace the remaining rendered keyboard/PDF/remeasurement checks. No
production data, production credential, production database, push, or deployment was used.
