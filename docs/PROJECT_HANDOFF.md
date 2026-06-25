# PROJECT_HANDOFF.md - SILAPPKASAL Project Handoff

> Status: Active Handoff  
> Last Updated: 2026-06-25  
> Current Milestone: Milestone 31-B2 Complete - Multi-Campus Reporter Registration Frontend  
> Next Milestone: TBD

---

## 1. Project Snapshot

SILAPPKASAL is a secure reporting and case-handling platform for prevention and response to sexual violence in a university environment. The repository is structured with a Laravel REST API backend in `backend/api` and a React frontend in `frontend/`.

The backend serves as the source of implemented business behavior, exposing secure REST APIs. The React frontend in `frontend/` includes:
- Authenticated dashboard integration for administrators and Satgas PPKS.
- Operational report/case screens with real-time detail tabs.
- Safe workflow action forms for status transitions, activity logging, recommendation editing, decision updates, recovery monitoring, and evidence metadata editing.
- A fully integrated **Reporter Portal** enabling students and reporters to register, log in, view their submitted reports, check safe status updates, read notifications, and manage their profiles.

Evidence upload, WhatsApp integration, and Flutter mobile work remain future work.

Milestone 31 expanded the system into a central multi-campus platform: one application and database can now serve multiple universities with campus master data, campus-aware reporter registration, pending/rejected registration states, correction/resubmission, admin registration review, reporter management, public registration, reporter report submission, and public tracking frontend flows.

### 1.1 Architecture Summary
The system follows a decoupled client-server architecture:
* **Backend:**
  * **Framework:** Laravel REST API (v12.x) with Sanctum token-based authentication.
  * **Database:** PostgreSQL for data persistence.
  * **Security:** Role-Based Access Control (RBAC) enforced via Laravel Policies and Middleware. Encrypted casts for sensitive data narrative fields at rest. Append-only Audit Trail logging for administrative actions.
  * **Queue/Workflow:** Database queue driver managing asynchronous tasks. Notifications system built using Laravel database channel. Role-aware My Work queues for Satgas PPKS (scoped to assignments) and Admin/Super Admin (global scopes).
* **Frontend:**
  * **Framework:** React with Vite.
  * **Routing:** TanStack Router for type-safe routing, role-based route guards, and contextual login redirects.
  * **Data Fetching:** TanStack Query (React Query) for caching, automatic invalidation, and state sync.
  * **UI Components:** Tailwind CSS, Radix UI, and shadcn/ui.

### 1.2 Reporter Portal Summary
The Reporter Portal provides a secure self-service area for reporter/student roles:
* **Overview/Dashboard:** Displays key metrics (total reports, active reports, completed reports) and unread notification counts.
* **My Reports:** List of reports submitted by the reporter with filter/search options, using public-facing registration numbers.
* **Report Detail:** Privacy-safe read-only detail view of a report. Statuses are abstracted to safe display labels (`Submitted`, `Under Review`, `In Process`, `Completed`) to prevent leaking internal workflow codes.
* **Notifications (read-only):** View list of notifications relevant to the reporter.
* **Account/Profile:** Self-service profile editing for the reporter's `name` and `phone_number` only.
* **Change Password:** Secure password update requiring validation of the current password, which invalidates other active tokens while preserving the current session.

---

## 2. Completed and Prepared Milestones

| Milestone | Name | Status | Summary |
|---|---|---|---|
| 1 | Repository Foundation | PASS | Repository structure established; frontend and backend boundaries clarified. |
| 2 | Laravel Foundation | PASS | Laravel 12.62.0 API foundation, PostgreSQL config, Sanctum installed, database queue, private storage disk, health endpoint. |
| 3 | Authentication & RBAC | PASS | Sanctum token auth, login/logout/me, roles, permissions, RBAC middleware, policy foundation, seeders, tests. |
| 4 | Master Data Foundation | PASS | Read-only master data tables, models, endpoints, seeders, and tests. |
| 5 | Report Intake Foundation | PASS | Anonymous and identified report intake, tracking code, metadata-first report reads, privacy rules, tests. |
| 6 | Case Foundation | PASS | Forward report to case, case number, assignment history, status transition foundation, metadata-first case access, tests. |
| 7 | Investigation Foundation | PASS | Investigation model, activity records, master-data-driven investigation status transitions, assigned Satgas detail access, admin metadata-only access, tests. |
| 8 | Recommendation Foundation | PASS | Recommendation model, completed-investigation reference, master-data-driven recommendation status transitions, status history, assigned Satgas detail access, admin metadata-only access, tests. |
| 9 | Decision Foundation | PASS | Decision model, recommendation-owned decision records, decision status master data, outcome foundation, status history, admin/super admin decision authority, assigned Satgas read-only access. |
| 10 | Recovery and Monitoring Foundation | PASS | Recovery model, monitoring records, recovery status master data, status history, admin/super admin recovery lifecycle authority, assigned Satgas recovery read and monitoring creation. |
| 11 | Evidence Foundation | Prepared and Tested | Investigation-owned evidence metadata, evidence lifecycle constants, metadata-only resources, chain-of-custody foundation, assigned Satgas access only, no file upload/download/storage implementation. |
| 12 | Audit Trail Foundation | PASS | Append-only audit log model, audit taxonomy constants, privacy-safe redaction service, audit log read API, admin/super_admin RBAC, no export/SIEM/notifications/frontend work. |
| 13 | Dashboard & Analytics Foundation | Prepared, Pending Verification | Metadata-only dashboard analytics endpoints, live aggregate queries, statistics.view RBAC, global admin/super_admin scope, assigned-case Satgas scope, no migrations, no ETL, no exports, no frontend work. |
| 14 | Frontend Integration Foundation | PASS | React frontend API client, `VITE_API_BASE_URL`, centralized auth storage, backend login/me/logout integration, protected dashboard shell, AccessDenied, role-aware navigation, dashboard analytics integration, master data client foundation, lint/build verified. |
| 15 | Operational Screen Foundation | PASS | React report list/detail and case list/detail integration, read-only case detail sections for investigations, recommendations, decisions, recoveries, and evidence metadata, metadata-only response handling, disabled unavailable assignment/forwarding actions, lint/build verified. |
| 16 | Workflow Actions Foundation | PASS | Safe frontend workflow actions for case status, investigation activities, recommendation updates, decision updates, recovery monitoring, and evidence metadata/status; disabled blockers for lookup/status-option gaps; no backend changes; lint/build verified. |
| 17 | Notification Foundation | PASS | Laravel native database notifications, queued database channel only, metadata-only payloads, low-noise workflow triggers, own-user read/list APIs. |
| 18 | Work Queue Foundation | PASS | Backend-only My Work queues for summary, cases, investigations, and recommendations; Satgas active-assignment scope. |
| 19 | Reporter Registration Foundation | PASS | Backend-only reporter/student registration requests, public throttled self-registration, admin/super_admin approval/rejection. |
| 20 | Reporter Self-Service Foundation | PASS | Backend-only reporter-only self-service APIs for own profile, profile update, password change, and account-status metadata. |
| 21 | Reporter Portal Foundation (Backend) | PASS | Backend-only reporter portal APIs for summary, own reports, safe own report detail, and read-only own notifications. |
| 22 | Reporter Portal Frontend Integration | PASS | React frontend integration for the Reporter Portal (Overview, My Reports, Report Detail, read-only notifications, profile edits, and change password). |
| 23 | User Management Foundation | PASS | Backend-only safe user directory, picker-safe lookup, activation/deactivation, role assignment, and audit logging. |
| 24 | Workflow Activation Foundation | PASS | Frontend activation of report forwarding and case assignment using safe Satgas lookup, React Query invalidation, backend-authoritative validation, and QA verification. |
| 25 | Localization Foundation | PASS | Frontend localization foundation implemented with Reporter Portal bilingual support, Bahasa Indonesia default, and English optional. |
| 26 | Security & Privacy Enhancement | ✅ PASS | Security and Privacy policies finalized. Implemented Anonymous Reporting, Break Glass, Privacy Enforcement, and Audit Filtering. |
| 27 | Investigation Workflow | ✅ PASS | Investigation backend and frontend components implemented. Added status-options endpoint, lead investigator picker, plan summary validation, audit logs, and notifications. Only assigned Satgas can create/update investigations. |
| 28 | Recommendation Workflow | ✅ PASS | Recommendation backend and frontend components implemented. Added status-options endpoint, automatic most recent completed investigation selection, audit logs, and notifications. Only assigned Satgas can create/update recommendations. |
| 29 | Decision Workflow | ✅ PASS | Decision backend and frontend implemented. Pimpinan Kampus (Super Admin) authority enforced. Backend status-options endpoint added. Auto-updates parent recommendation status. Audit logging and targeted notifications (assigned Satgas only) added. |
| 30 | Recovery Workflow | ✅ PASS | Recovery backend and frontend implemented. Admin/Super Admin manage recoveries. Assigned Satgas has read-only access but can add monitoring. Soft warning advisory implemented for < 3 month monitoring completions. Auto-close rules not triggered by recovery completion. Audit and notifications configured. |
| 31A | Multi-Campus Master Data Foundation | PASS | Backend campus master data foundation with universities, faculties, study programs, user/registration campus relationships, university-scoped NIM uniqueness foundation, participating university seed data, and public read-only campus endpoints. |
| 31-B1 | Reporter Registration, Auth States, and Management Backend | PASS | Backend campus-aware registration validation, pending/rejected authentication states, correction/resubmission, campus-scoped admin reporter management, manual reporter creation, activation/deactivation, password reset, audit logging, and email-only pending/rejected registration login fallback. |
| 31-B2 | Reporter Registration and Portal Frontend | PASS with QA note | Frontend public registration, pending/rejected correction states, admin registration review, reporter management, reporter report submission, public tracking, portal navigation updates, and bilingual public/reporter flows. QA patch fixed a stray TypeScript brace and missing correction-page auth import. |

Latest known fully verified baseline before Milestone 13 implementation:

```text
php artisan route:list --path=api/v1
php artisan test

Routes: 42 API v1 routes
Tests: 71 passed (566 assertions)
```

Milestone 12 has been completed, committed, pushed, and documented per project handoff.

Milestone 13 backend implementation is prepared in code, but backend tests and route verification have not been run yet after the Milestone 13 changes.

Milestone 16 workflow actions foundation has been implemented and verified with:

```text
npm run lint
npm run build
```

Result:

```text
Lint: PASS, 0 errors, 6 pre-existing shadcn/Lovable react-refresh warnings
Build: PASS
```

Milestone 17 notification foundation has been implemented and verified with:

```text
php artisan test
```

Result:

```text
87 passed (707 assertions)
```

Milestone 18 work queue foundation and Milestone 19 reporter account foundation have been verified in the latest backend test run.

Latest backend verification:

```text
php artisan test: PASS
102 passed (812 assertions)
```

Milestone 20 reporter self-service foundation and Milestone 21 reporter portal foundation (backend) have been verified in the latest backend test run.

Latest backend verification:

```text
php artisan test: PASS
116 passed (932 assertions)
```

Milestone 22 Reporter Portal Frontend Integration has been implemented and verified with frontend checks.

Latest frontend verification:

```text
npm run lint: PASS, 0 errors, 6 pre-existing shadcn/Lovable react-refresh warnings
npm run build: PASS
```

Additional Milestone 22 QA:

```text
Non-empty reporter demo verified
Frontend/backend contract aligned
Account metadata contract aligned
```

Milestone 23 User Management Foundation has been implemented and verified in the latest backend test run.

Latest backend verification:

```text
php artisan test: PASS
125 passed (1025 assertions)
```

Milestone 24 Workflow Activation Foundation has been implemented and QA verified.

Latest verification baseline:

```text
Backend: 125 tests, 1025 assertions
Frontend QA: PASS
```

Milestone 25 Localization Foundation has been implemented and verified.

Latest frontend baseline:

```text
Localization foundation implemented
Reporter Portal bilingual (ID default, EN optional)
npm run lint: PASS
npm run build: PASS
```

---

## 3. Current Backend State

Backend location:

```text
backend/api
```

Implemented or prepared API groups:

| Area | Endpoint Coverage |
|---|---|
| Health | `GET /api/v1/health` |
| Auth | `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me` |
| Master Data | `GET /api/v1/master/{type}` |
| Campus Master Data | `GET /api/v1/universities`, `GET /api/v1/faculties`, `GET /api/v1/study-programs` |
| Reports | `POST /api/v1/reports`, `GET /api/v1/reports`, `GET /api/v1/reports/{report}`, `GET /api/v1/reports/track/{trackingCode}`, `POST /api/v1/reports/{report}/forward-to-case` |
| Cases | `GET /api/v1/cases`, `GET /api/v1/cases/{case}`, `PATCH /api/v1/cases/{case}/status`, `PATCH /api/v1/cases/{case}/assign` |
| Investigations | `POST /api/v1/cases/{case}/investigations`, `GET /api/v1/cases/{case}/investigations`, `GET /api/v1/investigations/{investigation}`, `PATCH /api/v1/investigations/{investigation}/status`, `POST /api/v1/investigations/{investigation}/activities` |
| Recommendations | `POST /api/v1/cases/{case}/recommendations`, `GET /api/v1/cases/{case}/recommendations`, `GET /api/v1/recommendations/{recommendation}`, `PATCH /api/v1/recommendations/{recommendation}`, `PATCH /api/v1/recommendations/{recommendation}/status` |
| Decisions | `POST /api/v1/recommendations/{recommendation}/decisions`, `GET /api/v1/recommendations/{recommendation}/decisions`, `GET /api/v1/decisions/{decision}`, `PATCH /api/v1/decisions/{decision}`, `PATCH /api/v1/decisions/{decision}/status` |
| Recoveries | `POST /api/v1/decisions/{decision}/recoveries`, `GET /api/v1/decisions/{decision}/recoveries`, `GET /api/v1/recoveries/{recovery}`, `PATCH /api/v1/recoveries/{recovery}`, `PATCH /api/v1/recoveries/{recovery}/status`, `POST /api/v1/recoveries/{recovery}/monitoring`, `GET /api/v1/recoveries/{recovery}/monitoring` |
| Evidences | `POST /api/v1/investigations/{investigation}/evidences`, `GET /api/v1/investigations/{investigation}/evidences`, `GET /api/v1/evidences/{evidence}`, `PATCH /api/v1/evidences/{evidence}`, `PATCH /api/v1/evidences/{evidence}/status`, `GET /api/v1/evidences/{evidence}/custody` |
| Audit Logs | `GET /api/v1/audit-logs`, `GET /api/v1/audit-logs/{auditLog}` |
| Dashboard | `GET /api/v1/dashboard/summary`, `GET /api/v1/dashboard/reports`, `GET /api/v1/dashboard/cases`, `GET /api/v1/dashboard/workflow`, `GET /api/v1/dashboard/evidence` prepared, pending verification |
| Notifications | `GET /api/v1/notifications`, `PATCH /api/v1/notifications/{notification}/read`, `PATCH /api/v1/notifications/read-all` |
| My Work | `GET /api/v1/my-work/summary`, `GET /api/v1/my-work/cases`, `GET /api/v1/my-work/investigations`, `GET /api/v1/my-work/recommendations` |
| Reporter Registrations | `POST /api/v1/reporter-registrations`, `PATCH /api/v1/reporter-registrations/correct`, `GET /api/v1/reporter-registrations`, `GET /api/v1/reporter-registrations/{reporterRegistration}`, `PATCH /api/v1/reporter-registrations/{reporterRegistration}/approve`, `PATCH /api/v1/reporter-registrations/{reporterRegistration}/reject` |
| User Management | `GET /api/v1/users`, `POST /api/v1/users/reporters`, `GET /api/v1/users/lookup`, `GET /api/v1/users/{user}`, `PATCH /api/v1/users/{user}/activate`, `PATCH /api/v1/users/{user}/deactivate`, `PATCH /api/v1/users/{user}/reset-password`, `PATCH /api/v1/users/{user}/role` |
| Reporter Self-Service | `GET /api/v1/me/profile`, `PATCH /api/v1/me/profile`, `PATCH /api/v1/me/change-password`, `GET /api/v1/me/account-status` |
| Reporter Portal | `GET /api/v1/portal/summary`, `GET /api/v1/portal/reports`, `GET /api/v1/portal/reports/{registrationNumber}`, `GET /api/v1/portal/notifications` |

---

## 4. Core Implementation Principles

- Backend code is scoped to `backend/api`.
- Existing project docs remain source of truth.
- Sensitive narrative fields are encrypted using Laravel encrypted casts.
- Admin and Super Admin access remains metadata-first unless a milestone explicitly grants sensitive access.
- Decision records are an explicit exception: Admin and Super Admin may read full decision content.
- Assigned Satgas access is required for sensitive case, investigation, recommendation, decision, recovery, and monitoring details.
- Anonymous report identity is not stored.
- Audit logs are append-only and must store safe metadata/deltas only, never raw sensitive content.
- Dashboard analytics are metadata-only and count-based; they must not expose narratives, anonymous identities, tracking codes, evidence details, filenames, checksums, custody events, audit log aggregates, SLA/KPI scoring, or predictive analytics.
- Frontend operational screens must render only fields returned by the backend and preserve metadata-only response behavior.
- Frontend workflow actions must use centralized operations API functions, React Query mutations, backend RBAC, and Laravel `422` field-error handling.
- Notifications are in-app Laravel database notifications only; WhatsApp, Fonnte, email, push, and frontend notification UI remain out of scope.
- My Work queues are metadata-only, role-aware, and assignment-scoped for Satgas; they must not expose narratives, report chronology, victim/reporter identity, anonymous hints, tracking codes, investigation findings, recommendation narratives, decision content, evidence details, `risk_level_code`, or priority filters.
- Reporter registration requests are stored separately from `users`; a reporter user is created only after admin/super_admin approval.
- Reporter registration is campus-aware. New registrations require university, study program, full name, NIM, email, phone number, and password; faculty is optional when the selected university has no faculties.
- NIM duplicate checks are university-scoped for reporter registration and manual reporter creation.
- Pending registration password hashes are temporary and are cleared after approval or rejection.
- Pending and rejected applicants authenticate only into limited registration states. In multi-campus mode, the pending/rejected registration fallback lookup is email-only; NIM is not accepted for that fallback.
- Public reporter registration is rate-limited and must not auto-login users.
- Reporter self-service endpoints are limited to role `reporter` only; admin, super_admin, and satgas_ppks are forbidden.
- Reporter self-service may edit only `name` and `phone_number`; email, NIM, NIP, role, permissions, active status, and approval/reviewer metadata are not self-editable.
- Reporter password change requires current password and confirmation, revokes other Sanctum tokens, and keeps the current token active.
- Reporter portal endpoints are limited to role `reporter` only and expose only own report metadata using `registration_number` instead of internal report IDs.
- Reporter portal report statuses are safe labels only: `Submitted`, `Under Review`, `In Process`, and `Completed`.
- Reporter portal notifications are read-only; notification mutation remains only through M17 notification endpoints.
- Public registration, correction/resubmission, public tracking, and reporter report submission are now integrated in the frontend through M31-B2.
- Evidence file upload, download, preview, storage implementation, attachments, WhatsApp, advanced analytics, and Flutter integration are not implemented yet.
- Tests are expected for each milestone before completion.

---

## 5. Security and Privacy State

Current security posture:

- Sanctum Bearer token auth is active.
- Token expiry is configurable through `SANCTUM_EXPIRATION`.
- Inactive users are rejected by auth flows.
- RBAC is project-defined through roles, permissions, policies, and middleware.
- Reports support anonymous and identified intake.
- Anonymous reports do not store reporter identity, reporter phone, IP, or device data in report business fields.
- Admin report/case/investigation/recommendation reads are metadata-first.
- Admin and Super Admin decision reads include full decision content because Milestone 9 records institutional decision output.
- Admin and Super Admin manage recovery lifecycle in Milestone 10.
- Assigned Satgas can read sensitive case, investigation, recommendation, decision, recovery, and monitoring detail only for active assignments.
- Assigned Satgas remains read-only for decision records.
- Assigned Satgas may create monitoring entries for assigned cases, but cannot complete or discontinue recovery.
- Evidence metadata and chain-of-custody foundation is implemented for assigned Satgas only.
- Admin and Super Admin have no default evidence access in Milestone 11; future break-glass access remains possible but is not implemented.
- Evidence file upload/download/storage is not implemented.
- Frontend auth stores bearer tokens through the centralized `frontend/src/lib/auth-storage.ts` wrapper only.
- Frontend authorization logic must use `user.role.code` as canonical; role display names are display-only.
- Frontend operational screens respect backend RBAC and must not assume hidden sensitive fields exist.
- Frontend workflow mutations avoid optimistic updates and refresh from backend after success.
- Evidence actions remain metadata/status only; upload, download, preview, and storage fields are still out of scope.
- Notification payloads are metadata-only, include `notification_type_code`, and must not include narratives, reporter/victim identity, anonymous hints, evidence details, recommendation content, decision content, recovery notes, tokens, or sensitive fields.
- Reporter/student accounts cannot login until a registration is approved and an active reporter user is created.
- Reporter registration review data is limited to admin/super_admin; Satgas and reporter roles have no review API access.
- Reporter self-service is not a general profile API; admin, super_admin, and satgas_ppks profile APIs remain out of scope.
- Reporter portal is not public case browsing and must not expose raw workflow status codes, tracking codes, report narratives, respondent details, investigation findings, recommendation content, decision content, recovery notes, evidence details, audit data, assignments, staff identities, or admin notes.

Deferred security work:

- Strict security headers middleware.
- Audit trail foundation implemented.
- Break-glass access.
- Break-glass evidence access and secure file streaming.
- WhatsApp/Fonnte notification privacy review.
- Frontend token/session hardening.

---

## 6. Current Data Model Coverage

Implemented or prepared domain tables include:

- `users`
- `roles`
- `permissions`
- `role_permissions`
- `personal_access_tokens`
- master data tables
- `reports`
- `cases`
- `case_assignments`
- `investigations`
- `investigation_activities`
- `recommendations`
- `recommendation_status_histories`
- `decision_statuses`
- `decisions`
- `decision_status_histories`
- `recovery_statuses`
- `recoveries`
- `recovery_status_histories`
- `recovery_monitorings`
- `evidences` prepared by Milestone 11 migration
- `evidence_status_histories` prepared by Milestone 11 migration
- `evidence_custody_events` prepared by Milestone 11 migration
- `audit_logs`
- `notifications`
- `reporter_registrations`
- `universities`
- `faculties`
- `study_programs`

Not yet implemented:

- evidence file upload/download/preview/storage
- WhatsApp/Fonnte notification delivery records
- messaging

---

## 7. Remaining Planned Milestones

The remaining planned milestones for the project are:

### Future Candidate Areas

- Security Verification
- Frontend Workflow Completion
- Production Readiness
- Flutter Mobile Foundation

---

## 8. Recommended Handoff Commands

Run from `backend/api`:

```bash
php artisan about
php artisan migrate --force
php artisan db:seed --force
php artisan route:list --path=api/v1
php artisan test
```

Expected verified test baseline:

```text
Backend: run `php artisan test` from `backend/api`
Frontend: run `npm.cmd run build` from `frontend`
```

Prepared Milestone 13 route additions:

```text
GET /api/v1/dashboard/summary
GET /api/v1/dashboard/reports
GET /api/v1/dashboard/cases
GET /api/v1/dashboard/workflow
GET /api/v1/dashboard/evidence
```

Recommended backend verification commands:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan route:list --path=api/v1
php artisan test
```

Run from `frontend/`:

```bash
npm run lint
npm run build
```

Latest frontend verification:

```text
npm run lint: PASS, 0 errors, 6 pre-existing shadcn/Lovable react-refresh warnings
npm run build: PASS
```

Latest backend verification:

```text
php artisan test: PASS
125 passed (1025 assertions)
```

Latest M31 backend verification:

```text
M31-A and M31-B1 backend completed and patched.
Pending/rejected registration fallback login is email-only.
```

Latest QA verification:

```text
Frontend QA: PASS
```

Latest localization verification:

```text
Milestone 25 Localization Foundation: PASS
Localization foundation implemented
Reporter Portal bilingual (ID default, EN optional)
npm run lint: PASS
npm run build: PASS
```

Latest M31-B2 frontend QA:

```text
npx.cmd tsc --noEmit: FAIL - existing workflow/dashboard TypeScript errors outside the targeted QA patch
npm.cmd run build: PASS
```

Verified Milestone 23 route additions:

```text
GET /api/v1/users
GET /api/v1/users/lookup
GET /api/v1/users/{user}
PATCH /api/v1/users/{user}/activate
PATCH /api/v1/users/{user}/deactivate
PATCH /api/v1/users/{user}/reset-password
PATCH /api/v1/users/{user}/role
POST /api/v1/users/reporters
```

Verified Milestone 18 route additions:

```text
GET /api/v1/my-work/summary
GET /api/v1/my-work/cases
GET /api/v1/my-work/investigations
GET /api/v1/my-work/recommendations
```

Verified Milestone 19 route additions:

```text
POST /api/v1/reporter-registrations
PATCH /api/v1/reporter-registrations/correct
GET /api/v1/reporter-registrations
GET /api/v1/reporter-registrations/{reporterRegistration}
PATCH /api/v1/reporter-registrations/{reporterRegistration}/approve
PATCH /api/v1/reporter-registrations/{reporterRegistration}/reject
```

Verified Milestone 31-A campus route additions:

```text
GET /api/v1/universities
GET /api/v1/faculties
GET /api/v1/study-programs
```

Verified Milestone 20 route additions:

```text
GET /api/v1/me/profile
PATCH /api/v1/me/profile
PATCH /api/v1/me/change-password
GET /api/v1/me/account-status
```

Verified Milestone 21 route additions:

```text
GET /api/v1/portal/summary
GET /api/v1/portal/reports
GET /api/v1/portal/reports/{registrationNumber}
GET /api/v1/portal/notifications
```

---

## 9. Handoff Notes for Next Agent

- Read all relevant docs before planning a new milestone.
- Do not modify `frontend/` during backend milestones unless requested.
- Do not modify `backend/api` during frontend milestones unless explicitly approved.
- Do not change Phase 1-4 docs unless explicitly approved.
- Before running migrations in a milestone implementation, show files created/modified, migration summary, route summary, and test summary when requested.
- Keep privacy and RBAC behavior conservative.
- Do not seed dummy users or business rows unless the milestone explicitly requires it.
- Keep business logic in services, access rules in policies, validation in form requests, and response shaping in resources.
- Keep frontend token persistence centralized in `frontend/src/lib/auth-storage.ts`.
- Use `user.role.code` for frontend authorization decisions; never use role display names for logic.
- Milestone 16 enabled selected workflow mutations only through approved backend endpoints and centralized operations API helpers.
- Do not add temporary numeric ID inputs for assignment, forwarding, or investigator/Satgas selection.
- Recommendation/decision/investigation status actions remain disabled until approved status option or transition sources are available.
- Reporter Portal and Self-Service are fully integrated in the React frontend (Milestone 22).
- Milestone 22 additional QA verified a non-empty reporter demo, frontend/backend contract alignment, and account metadata contract alignment.
- Milestone 23 added backend-only user management and lookup APIs that now support assignment pickers and admin operations.
- Milestone 24 activated report forwarding and case assignment in the frontend using the approved Satgas lookup endpoint and backend-authoritative validation.
- Milestone 25 implemented the frontend localization foundation and Reporter Portal bilingual behavior with Bahasa Indonesia as the default and English optional.
- Public reporter registration, correction/resubmission UI, admin registration review, reporter management UI, reporter report submission, and public tracking page are now integrated in the frontend as part of M31-B2.
- M31-B2 QA patch modified only `frontend/src/lib/api-types.ts` and `frontend/src/routes/registration.correction.tsx`.

## Verification Baseline

Backend:
- php artisan test
- latest stored baseline before M31: 125 passed, 1025 assertions
- M31-A and M31-B1 completed and patched

Frontend:
- Localization foundation implemented
- Reporter Portal bilingual (ID default, EN optional)
- QA
- PASS
- npm.cmd run build
- PASS after M31-B2 QA patch
- npx.cmd tsc --noEmit
- FAIL due to existing workflow/dashboard TypeScript errors outside the QA patch scope

Milestone 22 Additional QA:
- Non-empty reporter demo verified
- Frontend/backend contract aligned
- Account metadata contract aligned
