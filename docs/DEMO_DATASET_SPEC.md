# SILAPPKASAL Local Demo Dataset and Startup

**Version:** 2.1

**Status:** Active local-demo contract

**Last verified:** 22 July 2026

## 1. Purpose and boundary

This document defines the repository-owned dataset and startup procedure for local development,
demonstration, smoke testing, UAT, and developer onboarding. It is not a production deployment
runbook.

Use an explicitly selected local or disposable demo database. Never run destructive database
commands against production or against a database whose target has not been verified. Production
deployment, infrastructure, secrets, backup/restore rehearsal, and security hardening are deferred
to dedicated milestones.

## 2. Seeder composition

`DatabaseSeeder` runs the following foundation and demo seeders in order:

1. RBAC foundation.
2. Master data.
3. Campus master data.
4. Content foundation.
5. Transactional demo dataset.

The transactional demo dataset creates users, registration examples, reports, cases,
investigations, recommendations, decisions, recovery and monitoring data, Evidence metadata,
notifications, and audit records. The seeders use stable lookups and `updateOrCreate` patterns where
appropriate so reruns do not intentionally duplicate their records.

## 3. Demo accounts

Passwords are intentionally not recorded in this repository document. Obtain the current local
demo credential from the authorized project owner or approved local secret channel. Never include
it in screenshots, tutorials, commits, chat logs, or demo recordings.

Repository-owned account identifiers are:

- Super Admin: `superadmin@silappkasal.test`.
- Campus Admin: `admin.<campus-code>@silappkasal.test`.
- Assigned Satgas: `satgas.<campus-code>@silappkasal.test` and
  `satgas2.<campus-code>@silappkasal.test`.
- Reporter: `reporter.<campus-code>@silappkasal.test` and
  `reporter2.<campus-code>@silappkasal.test`.

The actual `<campus-code>` values come from seeded campus master data. Pending and rejected
registration examples are Reporter Registration records, not active login accounts.

Each campus receives one Admin, two Satgas users, and two active Reporter users. The dataset also
contains pending, approved, and rejected registration states so registration review can be
demonstrated separately from login.

## 4. Workflow coverage

The dataset supplies representative records across the application workflow, including:

- Reporter registration and campus review.
- Reporter report submission and tracking.
- Case assignment, assessment, and investigation.
- Recommendation review and Decision.
- Recovery, monitoring, and Case closure.
- Role-specific notifications, audit history, and dashboard data.

All demo Evidence is metadata or safe fixture data. Do not substitute real identities, incidents,
or sensitive files.

## 5. Information Center seed behavior

`ContentFoundationSeeder` creates four stable sections, ten storyboard categories, 41 global
Article drafts, and eight global FAQ drafts. These records require editorial review, have no
published pointer, and never auto-publish. Seeder reruns do not overwrite editorial changes.

No Consultation contact is seeded because institutional contact details must be verified rather
than fabricated. Consequently, a fresh seed alone does not guarantee populated featured Articles,
FAQ answers, or Consultation cards in the published reader.

Before an Information Center demonstration, use the normal application workflow on the selected
demo database:

1. A Campus Admin creates or completes safe campus content and submits it.
2. A different authorized Super Admin reviewer reviews and publishes it.
3. Super Admin adds an eligible published Article to featured placement when the carousel is part
   of the demonstration.
4. Consultation content is created only with verified demo-safe institutional contact details.
5. Reporter signs in again or refreshes the published reader and verifies the published result.

Do not publish by editing database pointers directly, bypass second-review rules, or invent contact
details merely to fill the page.

## 6. Local startup

Prerequisites must already be installed; this procedure does not install dependencies.

### Backend

1. In `backend/api`, configure `.env` for the intended local/demo database and local frontend
   origin. Inspect the effective database target without printing passwords, application keys, or
   tokens.
2. For a brand-new, explicitly disposable demo database only, run:

   ```text
   php artisan migrate:fresh --seed
   ```

   This command is destructive and must not be used on an existing database that must be retained.
3. For an existing intended demo database, apply the normal non-destructive migration and seeding
   path after reviewing the target:

   ```text
   php artisan migrate --seed
   ```

4. Start the local API:

   ```text
   php artisan serve --host=127.0.0.1 --port=8000
   ```

5. Confirm `GET http://127.0.0.1:8000/api/v1/health` succeeds.

### Frontend

1. In `frontend`, verify the local environment points `VITE_API_BASE_URL` to
   `http://127.0.0.1:8000/api/v1` or the equivalent canonical local API origin.
2. Start the development frontend:

   ```text
   npm run dev -- --host localhost --port 5173
   ```

3. Open `http://localhost:5173/login` and use an approved repository-owned demo account.

The Vite development runtime is the supported local demo runtime. Cloudflare Workers output is
optional future deployment readiness and is not required for this milestone.

## 7. Demo smoke sequence

Use the following short readiness check before presenting:

1. Confirm API and frontend health.
2. Log in as Reporter and verify the Reporter dashboard.
3. Open **Pusat Informasi** and verify featured Article, Article detail, FAQ, and available verified
   Consultation actions.
4. Log out and log in as Campus Admin; create or edit safe content and submit it.
5. Log out and log in as Super Admin; review and publish the submitted version.
6. Return as Reporter and verify that the authoritative published version is visible.
7. Verify an account without `content.read.published` cannot open the reader.

## 8. Known demo limitations and deferred work

- Image upload remains disabled because the audited PHP environment cannot safely process images.
- Seeded Article and FAQ records start as drafts; published demo content requires the normal review
  workflow described above.
- Consultation contacts are not seeded and must use verified demo-safe information.
- Notification delivery, comments, reactions, bookmarks, scheduled publication, Flutter, and
  public unauthenticated content remain deferred.
- Complete viewport, keyboard-interaction, and popup-blocked PDF browser matrices are deferred to
  `REV-QA-01`, including a permanent Playwright QA harness. Existing automated checks and completed
  role-based browser smoke tests remain the current demo evidence.
- Production environment verification, secret rotation, deployment, infrastructure, and
  backup/restore rehearsal are deferred to dedicated production milestones.
- Graphify remains deferred.

## 9. Demo acceptance

The local demo is ready when:

- Backend and frontend start successfully on the documented local origins.
- Repository-owned demo accounts can authenticate.
- Required seeded workflow data is available.
- Reporter, Campus Admin, Satgas, and Super Admin core role flows open without a fatal runtime error.
- Information Center content prepared through the normal lifecycle is visible only to authorized
  published readers.
- Current backend and frontend automated verification remains passing.
