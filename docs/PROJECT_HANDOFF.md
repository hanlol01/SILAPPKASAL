# PROJECT_HANDOFF.md - SILAPPKASAL Project Handoff

> Status: Active Handoff  
> Last Updated: 2026-07-20
> Current Milestone: REV-WF-03 R3 — Final outcome and workflow UX implemented locally
> Next Milestone: Independent review and release verification

---

## REV-WF-03 R2 Handoff

R2 changes Emergency Access from the legacy Admin/Super Admin Break Glass flow to a requester-
scoped workflow. An active assigned same-campus Satgas requests access from an anonymous Case and
selects 30 minutes, 1 hour, 4 hours, or 24 hours. The same-campus Admin reviews, approves/denies,
and may revoke. Only the requesting Satgas can explicitly reveal the minimal identity projection
during an active grant. Super Admin retains redacted Activity Log oversight but no operational
request, review, revoke, or reveal authority.

Implementation points:

- `break_glass_requests` stores grant start/expiry, revoke metadata, view count, and last view time;
- new grants start at approval, not first reveal; expired grants deny without a scheduler;
- legacy `viewed` rows and eight-hour grants are preserved without fabricated reveal audits;
- all lifecycle operations are backend policy/service enforced and critical-audited with redacted
  metadata;
- reveal is a dedicated non-cacheable `POST` endpoint and is never embedded in Report/Case data;
- Satgas uses a protected Case-detail dialog whose identity state is cleared on close;
- Admin uses a same-campus queue without any reveal control;
- anonymous Supporting File/Internal Evidence names are masked in internal lists and response
  headers while the Reporter owner retains original Supporting File names.

Deployment requires the two `2026_07_20_*` migrations in timestamp order. Back up PostgreSQL first,
do not seed production, and follow `docs/deployment/DEPLOYMENT_UPDATE.md`. R3 is not implemented by
this milestone.

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
- Admin has no default Internal Evidence access. Super Admin sensitive read remains feature-flagged read-only oversight. R2 Emergency Access is identity-only and does not grant Evidence mutation authority.
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
- REV-WF-03 R2 Emergency Access and R3 final outcome/closure are implemented locally; neither statement implies deployment.
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
GET /api/v1/portal/reports/{registrationNumber}/handling-progress
GET /api/v1/portal/notifications
```

REV-WF-03 R1 current behavior:

- The Reporter-owned report detail now projects all submitted form values plus current account identity/contact/campus data. Current account fields are explicitly current values, not immutable submission snapshots.
- The Reporter handling-progress endpoint is registration-number based and ownership scoped. R3 may add a published safe `final_summary`; drafts remain hidden, and historical closed Cases without one use `legacy_completion` without an invented outcome.
- Shared submitted-report detail cards are available to same-campus Admin and active assigned Satgas. Super Admin access requires the existing sensitive cross-campus-read feature flag. Anonymous Reporter identity remains masked in internal projections.
- Report priority display and dashboard aggregation derive from the linked Case priority. No linked Case is `unavailable`; a linked Case without assessment priority is `unassessed`.
- Reporter Portal titled data cards use the shared accessible collapsible-card behavior, including localized controls and mounted collapsed content.
- REV-WF-03 R2 Emergency Access and R3 final-outcome/recovery/closure UX are implemented locally. Activity Log was not broadly redesigned; R3 adds only narrow allowlisted finalization audit events.

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

## REV-WF-03 R1/R2/R3 Handoff

- R1 Reporter transparency is implemented: owned submitted details, safe handling progress, linked-Case priority semantics, and collapsible Reporter/internal projections.
- R2 Emergency Access lifecycle is implemented with same-campus review, requester-only reveal, privacy audit boundaries, and Super Admin oversight restrictions.
- R3 final outcome and workflow UX is implemented in repository code: encrypted final summaries, Recovery discontinuation reasons, backend-compatible final outcomes, Reporter-safe publication, explicit Satgas closure, historical `legacy_completion`, Decision action placement, Monitoring ownership messaging, and Super Admin Cases navigation hiding.
- R3 deliberately does not redesign Activity Log. It adds only allowlisted finalization audit events and metadata.
- R3 requires migration `2026_07_20_020000_add_final_case_closure.php` during a future release. No deployment is claimed here.
- Future changes must preserve: same-campus Admin summary/Recovery mutation, active assigned Satgas Monitoring/closure ownership, published-only Reporter narratives, anonymous identity exclusion, and rejection of generic `closed` transitions.

## REV-CONTENT-01 C1 Handoff

- The shared Laravel publication aggregate for Article, FAQ, and Consultation is implemented in the
  backend. Published visibility is pointer-based, versioned, immutable, archived-safe, and campus-safe.
- Four sections, ten storyboard categories, 41 Article drafts, and eight FAQ drafts are represented by
  one idempotent seeder definition. No Consultation contact is fabricated and nothing auto-publishes.
- Authenticated published-read routes and the C2 campus Admin management surface exist. C3 Super
  Admin review UI, C4 Reporter Pusat Informasi/PWA work, and C5 release scope remain deferred.
- Symfony HtmlSanitizer 7.4.14 is the only new dependency. Intervention Image is not installed because
  the audited PHP runtime has no GD, Imagick, or EXIF extension.
- Future C2/C3 work must call the existing policies/services, preserve append-only encrypted review
  decisions, keep management and reader queries separate, and never expose internal storage or review
  metadata.
- No production deployment or production seeding is claimed.
- C1 security repair makes images fail-closed without a verified re-encoder, clones revision
  attachments with rewritten references and cleanup, enforces active Consultation CTAs, uses
  public-ID-only Article detail, scopes Admin Content audit by campus, adds database constraints and
  stable ordering, and generates non-sensitive download names.
- PHPUnit now force-resolves to SQLite `:memory:`. Disposable PostgreSQL verification is limited to
  local `silappkasal_test` with explicit confirmation; the local development database `silappkasal`
  is prohibited for automated tests.

## REV-CONTENT-01 C2 Handoff

- Campus Admin navigation exposes `Manajemen Konten` only with
  `content.read.management.own_campus`; the route repeats the role/permission guard and every API is
  backend-authorized and campus-scoped.
- `/dashboard/content` provides status summaries, filters, pagination, responsive list/card views,
  create/edit/view/preview/submit/revision actions, controlled Article/FAQ editing, structured
  Consultation editing, revision feedback, and unsaved-change protection.
- The controlled block editor serializes C1 JSON directly and supports paragraphs, H2/H3, bold,
  italic, ordered/unordered lists, blockquotes, allowlisted links, callouts, and dividers. Arbitrary
  HTML is never authoritative.
- General PDF attachments remain private, use safe generated labels, report upload progress, and can
  be removed only from an editable authorized draft. Image controls remain disabled with an explicit
  capability notice.
- C2 repair requires `lock_version` on submit, refetches on stable conflicts without discarding local
  editor input, makes archived items read-only across direct APIs, returns 404 for foreign/global
  management UUIDs, and clears all private management queries when authentication changes.
- Structured-document round trips preserve mixed marks, nested/complex supported nodes, image
  references, and unknown safe nodes. Shapes not safely editable in the simple editor remain visible
  as read-only preserved blocks. The mobile action footer wraps at 320–360 px and respects safe-area
  insets.
- Private PDF removal commits metadata and audit only after storage deletion succeeds. A storage
  failure leaves both metadata and bytes available and returns a stable retryable error.
- C2 adds no dependency, migration, C3 review action, Reporter route, service worker, notification
  delivery, or production deployment.

## REV-CONTENT-01 C3 Handoff

- Super Admin navigation now exposes `/dashboard/content-governance` only for
  `content.read.management.all`. The page has Editorial Queue, Published Content, Global Content,
  and Featured Content workspaces with responsive table/card and Sheet/Dialog behavior.
- The governance backend provides server-filtered review and published lists, cross-campus safe
  category/campus choices, typed read-only detail, previous-version preview, PDF access, server
  capabilities, and an authoritative audit/decision timeline. Every response is private/no-store.
- Campus-authored content remains read-only to Super Admin. Revision request, rejection, approval,
  publication, and archive are lifecycle decisions only; direct body/contact/attachment mutation is
  not available. Campus Admin, Reporter, and Satgas cannot access governance routes.
- Super Admin global authoring reuses the controlled C2 editor with `scope=global`. It follows the
  full submit-review-approve-publish lifecycle and requires a different Super Admin reviewer because
  creator/author/editor self-review is rejected by policy.
- Editorial decisions use item `lock_version` after transaction locks. Stable 409 codes distinguish
  stale review, invalid transition, archived state, and active authoring version. Notes remain in
  the encrypted append-only decision domain and are preserved in the browser after recoverable
  failure.
- Featured governance supports published Articles only, exact global/campus scope, ranks 1-5,
  current/future/expired/inactive views, optional visibility windows, preview, and audited
  create/update/replace/remove. Update and removal use an opaque state-derived `concurrency_token` so
  timestamp resolution cannot permit stale writes.
- Private TanStack queries rooted at both `content-management` and `content-governance` are cancelled
  and removed on logout, authentication invalidation, and account replacement.
- C3 publication-integrity repair removes the executable direct-global publication path. Global
  authoring now has a domain-enforced submit, distinct-reviewer approval, and approved-only publish
  sequence. Creator/author/editor self-review and self-publication remain denied after row locking.
- Published Content now projects only `publishedVersion`, including while a later version is rejected
  or approved but not yet published. Review detail may show that later version and the prior published
  version separately; authenticated reader APIs continue to follow the published pointer.
- Governance PDF actions use authenticated Blob retrieval with temporary Object URL cleanup rather
  than raw private endpoint navigation. Governance and global-authoring read queries forward TanStack
  cancellation signals in addition to the existing private-query removal boundary.
- C3 adds no dependency or database migration. Reporter Information Center/cards, featured carousel,
  PWA/service worker, notification delivery, image upload, scheduled publication, comments,
  reactions, bookmarks, Flutter, PostgreSQL runtime verification, and production deployment remain
  outside this commit.

## REV-CONTENT-01 C4 Handoff

- `/dashboard/information-center` is the authenticated published-content reader for Reporter, Satgas,
  Campus Admin, and Super Admin with `content.read.published`. Reporter is admitted only to this
  dashboard subtree and retains `/portal` as the landing page.
- Reporter dashboard retains report creation, status summaries, recent reports, and safety messaging,
  and adds four supporting shortcuts plus an isolated featured-Article carousel.
- The Information Center provides server-driven Article/FAQ search, section/category filters,
  pagination, URL-restored filter state, published Article detail, controlled FAQ accordion rendering,
  and active published Consultation cards. It never displays seeded drafts or governance metadata.
- Article cards use semantic full-card TanStack links, controlled section visuals, and a complete
  no-image state. Remote/fabricated images and paid-reading concepts are absent. Legacy published cover
  bytes are fetched only through authenticated temporary Blob URLs.
- Private PDF preview/download now prevents duplicate work, falls back to authenticated download when
  a popup is blocked, and revokes active Object URLs on replacement, failure, close, and unmount.
- Published TanStack queries include account identity, consume AbortSignal, and join the private cache
  cleanup performed before logout/account replacement. No content or attachment bytes are persisted.
- C4 adds a project-owned web manifest and app-shell metadata but no service worker. Offline private
  content, iOS install icon expansion, and service-worker update behavior remain deferred pending an
  explicit cache-security design and approved project-owned PNG icons.
- C4 adds no dependency, migration, image upload, notification delivery, public reader route, Flutter,
  PostgreSQL verification, production deployment, push, comments, reactions, or bookmarks.
- The C4 reader repair gates every Reporter entry point with `content.read.published`, preserves
  user-driven filter/FAQ/page browser history, canonicalizes invalid URL state by replacement, and
  keeps category UUIDs consistent with the active section.
- Filter controls now remain usable at 320/360/768/1024 CSS widths through a bottom Sheet below `lg`,
  fluid desktop fields, stable Select labels, and 44 px PDF actions. Automated tests exercise the
  production state/security helpers; live multi-device browser QA remains a C5 release check.
- All `/api/v1/content*` exception responses are explicitly private/no-store. Published attachment
  download uses controller-level UUID lookup inside that boundary and returns the same 404 shape for
  unknown and unauthorized records.

## REV-CONTENT-01 C5 Handoff

- C5 verified the complete backend suite on guarded SQLite `:memory:`: 413 tests and 4,230 assertions
  pass. Guarded PostgreSQL `silappkasal_test` migration/seed/constraint/rollback verification passes,
  as do the 61-test content suite and full 413-test/4,227-assertion backend suite. The development
  database `silappkasal` was never targeted.
- PostgreSQL portability hardening removes unsupported aggregate row locking, uses an explicit
  wildcard escape across content queries, constrains audit route identifiers to UUIDs, and makes
  notification/constraint tests portable across SQLite and PostgreSQL.
- Frontend content tests pass 27 tests; TypeScript, ESLint, client+SSR build, Composer validation and
  advisory audit, targeted Pint, PHP syntax, route inspection, and manifest validation pass. Guzzle
  security updates are locked and the final advisory audit reports no known vulnerabilities.
- Auth login/logout/me responses now run through `private.no-store`, preventing bearer tokens and user
  permission projections from being cacheable. Executable CORS configuration uses an exact
  environment allowlist and exposes only the response headers needed by authenticated downloads and
  request correlation. CORS never replaces Sanctum authentication or backend authorization.
- Authenticated Chrome QA covered Reporter at 320/360 px, Satgas at 768 px, Campus Admin at 1024 px,
  and Super Admin at 1440 px, including permission denial and an Admin-to-reviewer-to-Reporter
  publication handoff. Scoped editor-overflow and 44 px touch-target fixes pass static/build gates.
  Rendered post-fix remeasurement, real-keyboard Select/Carousel/Accordion checks, and authenticated
  PDF popup/fallback remain open.
- C5-RUNTIME-01 restores the supported Cloudflare Workers artifact contract by explicitly enabling
  the Lovable wrapper's Nitro Cloudflare deployment path. The build emits the Worker entry, generated
  Wrangler config, redirected deploy config, and client-assets binding; Wrangler 4.98.0 completes the
  official dry-run command without credentials or deployment.
- Production deployment remains blocked by the remaining browser checks, credential rotation review,
  actual environment confirmation, and restorable backup evidence. The existing `vite preview`
  instructions remain QA/demo-only.
- Release checklist, PostgreSQL report, browser matrix, and coordinated backup-first rollback plan are
  in `docs/deployment/REV_CONTENT_01_C5_*.md`. No push or deployment occurred.

## REV-CONTENT-01 Demo Readiness Closure

- REV-CONTENT-01 is closed for local development and demonstration at commit
  `56d4915ed6b7562147e1bcfe432a650ea796034f`. This closure does not authorize or claim a production
  deployment.
- Local API and Vite startup, authentication, Reporter and Satgas published-reader access, Campus
  Admin authoring, Super Admin review/publication, account replacement, cache isolation, browser
  history, and the end-to-end Admin-to-reviewer-to-Reporter publication handoff have verified demo
  evidence. No known fatal application runtime defect remains.
- Automated evidence includes passing guarded SQLite and disposable PostgreSQL backend suites,
  content lifecycle and permission tests, frontend content tests, TypeScript, ESLint with only the
  existing warnings, client and SSR builds, Composer validation/audit, Pint, PHP syntax, and route
  inspection.
- A fresh content seed intentionally contains 41 Article drafts and eight FAQ drafts, not published
  content. It contains no fabricated Consultation contacts. Prepare visible Information Center
  records through the normal Campus Admin submission and distinct Super Admin review/publication
  workflow before a presentation.
- Local startup, safe demo-account patterns, content preparation, and the smoke sequence are recorded
  in `docs/DEMO_DATASET_SPEC.md`. Passwords are deliberately excluded from documentation and must be
  supplied through an approved local secret channel.
- The interrupted temporary CDP run did not conclusively reproduce an application defect. Full
  viewport, real-keyboard Select/Carousel/Accordion, and authenticated PDF popup/fallback matrices
  are deferred to `REV-QA-01`, together with a permanent Playwright QA harness; they are not local
  demo blockers under this milestone.
- Production environment and secret verification, backup/restore rehearsal, VPS or Cloudflare
  deployment, and additional production hardening are deferred to dedicated production milestones.
  Existing Cloudflare runtime output is optional future deployment readiness only.
- Image upload, notification delivery, public unauthenticated content, offline authenticated-content
  caching, Flutter, and Graphify remain deferred. No production push or deployment occurred.
- Demo-readiness verdict: `DEMO_READY_WITH_NOTES`.
