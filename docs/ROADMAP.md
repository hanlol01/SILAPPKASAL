# ROADMAP.md - SILAPPKASAL Development Roadmap

> Status: Active  
> Last Updated: 2026-06-12  
> Current Position: Milestone 23 User Management Foundation complete  
> Next: Milestone 24 - Security Verification

---

## 1. Roadmap Overview

SILAPPKASAL development started backend-first. The Laravel API remains the source of business behavior, and the React web dashboard now has authenticated admin/Satgas dashboard integration, operational report/case screens, and safe workflow action forms backed by approved endpoints.

Current priorities:

1. Complete backend workflow foundations.
2. Preserve privacy and RBAC boundaries.
3. Add tests for each milestone.
4. Integrate frontend gradually after API behavior is stable.
5. Defer mobile until post-MVP API stability.

---

## 2. Completed and Prepared Milestones

### Milestone 1 - Repository Foundation

Status: PASS

Delivered:

- Repository boundaries established.
- Frontend and backend locations clarified.
- Development workflow aligned.

### Milestone 2 - Laravel Foundation

Status: PASS

Delivered:

- Laravel 12.62.0 in `backend/api`.
- PostgreSQL configuration.
- Sanctum installed.
- Database queue driver.
- Private storage foundation.
- `GET /api/v1/health`.
- Baseline tests.

### Milestone 3 - Authentication & RBAC

Status: PASS

Delivered:

- Sanctum Bearer token authentication.
- `POST /api/v1/auth/login`.
- `POST /api/v1/auth/logout`.
- `GET /api/v1/auth/me`.
- Roles, permissions, role-permission mapping.
- RBAC middleware and policy foundation.
- No dummy users seeded.

### Milestone 4 - Master Data Foundation

Status: PASS

Delivered:

- Master data migrations and models.
- Idempotent master data seeders.
- Read-only authenticated master data endpoints.
- `notification_types` kept internal only.
- No faculties/study programs added.

### Milestone 5 - Report Intake Foundation

Status: PASS

Delivered:

- Anonymous and identified report submission.
- Tracking codes for anonymous reports.
- Explicit `submitted_at`.
- Report status constants.
- Metadata-first report list/detail.
- Privacy-safe tracking endpoint.
- Reporter/admin RBAC behavior.

### Milestone 6 - Case Foundation

Status: PASS

Delivered:

- Forward report to case.
- Separate `case_number`.
- Case status via master data.
- Assignment foundation with history retention.
- Case list/detail APIs.
- Case status transition foundation.
- Metadata-first admin case access.
- Assigned Satgas sensitive detail access.

### Milestone 7 - Investigation Foundation

Status: PASS

Delivered:

- Investigation model and migration.
- Investigation activity records.
- Relationship from case to investigation.
- Investigation status via `investigation_statuses`.
- Status transitions from master data.
- Assigned-Satgas initiated investigation creation.
- Admin/super_admin metadata-only investigation responses.
- Assigned Satgas sensitive investigation detail.
- No evidence upload or attachments.

Verification:

```text
45 passed (348 assertions)
```

### Milestone 8 - Recommendation Foundation

Status: PASS

Delivered:

- Recommendation model and migration.
- Relationship from recommendation to case.
- Required completed-investigation reference.
- Recommendation status via `recommendation_statuses`.
- Status transitions from master data.
- `submitted_to_leader` terminal behavior for M8.
- Status history foundation.
- Admin/super_admin metadata-only recommendation responses.
- Assigned Satgas sensitive recommendation detail.
- No decision, recovery, evidence, notification, WhatsApp, analytics, or frontend work.

Verification:

```text
52 passed (395 assertions)
```

### Milestone 9 - Decision Foundation

Status: PASS

Delivered:

- Decision model and migration.
- Relationship from decision to recommendation.
- One decision per recommendation through unique `recommendation_id`.
- No direct `case_id` in `decisions`.
- Decision status via `decision_statuses`.
- Status transitions from master data.
- `finalized` terminal behavior.
- Outcome foundation: `accepted`, `partially_accepted`, `rejected`, `deferred`.
- Decision status history foundation.
- Admin/super_admin create, update, transition, and read decision content.
- Assigned Satgas read-only decision detail for assigned cases.
- Encrypted decision narrative content at rest.
- No case status mutation, case closing, recovery, evidence, notification, WhatsApp, analytics, or frontend work.

### Milestone 10 - Recovery and Monitoring Foundation

Status: PASS

Delivered:

- Recovery model and migration.
- Recovery belongs to decision with no direct `case_id`.
- Recovery uses `recovery_types` master data.
- Recovery status via `recovery_statuses`.
- Status transitions from master data.
- Recovery statuses: `planned`, `ongoing`, `completed`, `discontinued`.
- `completed` and `discontinued` terminal behavior.
- Recovery status history foundation.
- Monitoring records belong to recovery and are append-only.
- Monitoring creation only for `ongoing` recovery.
- Admin/super_admin manage recovery lifecycle.
- Assigned Satgas may read recovery detail and create/read monitoring for assigned cases.
- Encrypted recovery and monitoring narrative content at rest.
- No case status mutation, case closing, decision status mutation, evidence, notification, WhatsApp, analytics, or frontend work.

### Milestone 11 - Evidence Foundation

Status: Implementation prepared and test-verified

Prepared:

- Evidence metadata model and migration.
- Evidence belongs to investigation with no direct `case_id`.
- Evidence uses existing `evidence_types` master data.
- Evidence classification centralized through enum/constants.
- Evidence status lifecycle centralized through enum/constants.
- Statuses: `registered`, `under_review`, `verified`, `rejected`, `archived`.
- `archived` terminal behavior.
- `verified` means metadata reviewed/admin complete only, not forensic authenticity.
- Chain-of-custody foundation with `registered`, `metadata_updated`, `status_changed`, and `reviewed`.
- Assigned Satgas can create/read/update evidence metadata for assigned investigation cases.
- Admin and Super Admin have no default evidence access in M11.
- Reporter has no investigation-owned evidence access.
- No file upload, download, preview, storage implementation, MinIO, S3, OCR, AI, notifications, WhatsApp, analytics, or frontend work.

Verification:

```text
42 API v1 routes
71 passed (566 assertions)
```

### Milestone 12 - Audit Trail Foundation

Status: PASS

Delivered:

- Append-only `audit_logs` migration.
- `created_at` only; no `updated_at` or `deleted_at`.
- Nullable `actor_id`, `request_id`, `subject_type`, and `subject_id`.
- JSON `metadata`, `before_changes`, and `after_changes`.
- Audit taxonomy constants for auth, report, case, investigation, recommendation, decision, recovery, evidence, security, and system.
- Explicit `security.access_denied` event.
- Privacy-safe `AuditLogService` redaction for passwords, tokens, token hashes, encrypted payloads, sensitive narratives, evidence content, and file contents.
- Safe field-level delta strategy only; no full object or request payload snapshots.
- `is_elevated_access` metadata placeholder with default false.
- Admin and Super Admin audit API access through `system.audit_log.view`.
- Satgas and reporter have no audit API access.
- Evidence custody remains separate and complementary.
- No export, SIEM, retention policy, notification, WhatsApp, analytics dashboard, or frontend work.

Verification:

```text
Completed, committed, pushed, and documented.
```

### Milestone 13 - Dashboard & Analytics Foundation

Status: Implementation prepared, pending verification

Prepared:

- Metadata-only dashboard analytics endpoints.
- No migrations and no new business tables.
- Live aggregate queries over existing workflow tables only.
- `DashboardController`, `DashboardService`, `DashboardPolicy`, request validation, and dashboard resource.
- Routes:
  - `GET /api/v1/dashboard/summary`
  - `GET /api/v1/dashboard/reports`
  - `GET /api/v1/dashboard/cases`
  - `GET /api/v1/dashboard/workflow`
  - `GET /api/v1/dashboard/evidence`
- Filters: `date_from`, `date_to`, and `granularity=day|week|month`.
- Default 30-day range and maximum 366-day range.
- RBAC uses `statistics.view` plus existing role scope.
- Admin and Super Admin receive global metadata aggregates.
- Satgas receives aggregates scoped to active assigned cases.
- Reporter is forbidden.
- Evidence analytics are count-based only.
- Workflow conversion metrics are descriptive only, not SLA, KPI, success-rate, or performance scoring.
- Audit logs are explicitly excluded from dashboard analytics.
- No frontend, export, notification, WhatsApp, AI analytics, predictive scoring, ETL, or materialized view work.

Verification pending:

```text
Route verification and php artisan test have not been run after M13 changes.
```

### Milestone 14 - Frontend Integration Foundation

Status: PASS

Delivered:

- React frontend API client using `VITE_API_BASE_URL`.
- Centralized token/storage wrapper in `frontend/src/lib/auth-storage.ts`.
- Backend auth integration:
  - `POST /api/v1/auth/login`
  - `GET /api/v1/auth/me`
  - `POST /api/v1/auth/logout`
- Auth state management based on backend user data.
- Protected dashboard shell and simple `AccessDenied` component.
- Role-aware navigation using canonical `user.role.code`.
- Visible M14 navigation:
  - `super_admin`: Overview, Workflow, Analytics, Settings
  - `admin`: Overview, Workflow, Analytics, Settings
  - `satgas_ppks`: Overview, Workflow, Settings
- Users, Content, and Notifications navigation hidden; direct routes show AccessDenied for now.
- Dashboard analytics integration:
  - `/api/v1/dashboard/summary`
  - `/api/v1/dashboard/reports`
  - `/api/v1/dashboard/cases`
  - `/api/v1/dashboard/workflow`
  - `/api/v1/dashboard/evidence`
- Master data API client foundation for `/api/v1/master/{type}`.
- Loading/error state components for dashboard query states.
- ESLint config adjusted so formatting does not block M14 lint.
- Explicit ESLint ignores for generated/output/lockfile paths.
- No backend changes.
- No case-management table/detail API integration.
- No reporter public view, mobile user view, Flutter, student registration/account approval, public report submission UI, evidence upload/download, notification/WhatsApp, or user-management API work.

Verification:

```text
npm run lint: PASS, 0 errors, 6 pre-existing shadcn/Lovable react-refresh warnings
npm run build: PASS
```

### Milestone 15 - Operational Screen Foundation

Status: PASS

Delivered:

- React frontend operational read/browse screens for `super_admin`, `admin`, and `satgas_ppks`.
- Report list and detail integration:
  - `GET /api/v1/reports`
  - `GET /api/v1/reports/{report}`
- Case list and detail integration:
  - `GET /api/v1/cases`
  - `GET /api/v1/cases/{case}`
- Case detail read-only sections:
  - Investigations via `GET /api/v1/cases/{case}/investigations`
  - Recommendations via `GET /api/v1/cases/{case}/recommendations`
  - Decisions via `GET /api/v1/recommendations/{recommendation}/decisions`
  - Recoveries via `GET /api/v1/decisions/{decision}/recoveries`
  - Evidence metadata via `GET /api/v1/investigations/{investigation}/evidences`
- Operational APIs consumed through the M14 API client and React Query patterns.
- Existing dashboard shell/layout preserved.
- Role-aware navigation remains based on canonical `user.role.code`.
- Frontend renders only fields returned by the backend and respects metadata-only responses.
- Report forward and case assignment actions are disabled with explanatory UI because user/Satgas lookup APIs are unavailable.
- No temporary numeric ID inputs were introduced.
- No investigation, recommendation, decision, recovery, or evidence mutation forms.
- No evidence upload/download/preview.
- No reporter public view, mobile user view, Flutter, student registration/account approval, notification UI, WhatsApp, or backend changes.

Verification:

```text
npm run lint: PASS, 0 errors, 6 pre-existing shadcn/Lovable react-refresh warnings
npm run build: PASS
```

### Milestone 16 - Workflow Actions Foundation

Status: PASS

Delivered:

- React frontend workflow action foundation for `super_admin`, `admin`, and `satgas_ppks`.
- Mutation API functions added through the existing M14/M15 operations API client.
- Enabled safe backend-approved actions:
  - Case status update via `PATCH /api/v1/cases/{case}/status`.
  - Investigation activity creation via `POST /api/v1/investigations/{investigation}/activities`.
  - Recommendation content update via `PATCH /api/v1/recommendations/{recommendation}`.
  - Decision content/update via `PATCH /api/v1/decisions/{decision}`.
  - Recovery monitoring creation via `POST /api/v1/recoveries/{recovery}/monitoring`.
  - Evidence metadata and status updates via `PATCH /api/v1/evidences/{evidence}` and `PATCH /api/v1/evidences/{evidence}/status`.
- React Query mutations with targeted query invalidation.
- Toast/loading/error UX using existing frontend patterns.
- Laravel `422` field error mapping into form state.
- Role-aware action visibility using canonical `user.role.code`.
- Assigned Satgas action gating uses returned assignment data as a UX hint; backend RBAC remains authoritative.
- Disabled blocker UI for actions requiring unavailable lookup/status-option APIs:
  - Report forward-to-case.
  - Case assignment.
  - Investigation creation.
  - Investigation status update.
  - Recommendation status update.
  - Decision status update.
  - Recovery update/status beyond monitoring.
- No manual numeric user ID inputs.
- No evidence upload/download/preview or storage field mutation.
- No reporter public view, mobile user view, Flutter, student registration/account approval, notification UI, WhatsApp, or backend changes.

Verification:

```text
npm run lint: PASS, 0 errors, 6 pre-existing shadcn/Lovable react-refresh warnings
npm run build: PASS
```

### Milestone 17 - Notification Foundation

Status: PASS

Delivered:

- Laravel native database notifications.
- `notifications` table migration using Laravel database notification shape.
- `User` Notifiable behavior used for notification delivery.
- Queued database notification class using database channel only.
- Notification queue name set to `notifications`.
- Privacy-safe notification payloads with mandatory `notification_type_code`.
- Metadata-only notification payloads with no narratives, reporter/victim identity, anonymous hints, evidence details, recommendation content, decision content, recovery notes, tokens, or sensitive fields.
- Internal notification type seed updates for M17 trigger events.
- Trigger points:
  - `case_assigned`
  - `case_status_changed`
  - `recommendation_submitted_to_leader`
  - `decision_finalized`
- Notification read/list APIs:
  - `GET /api/v1/notifications`
  - `PATCH /api/v1/notifications/{notification}/read`
  - `PATCH /api/v1/notifications/read-all`
- Authenticated users can only list/read their own notifications.
- No role can browse another user’s notifications.
- No WhatsApp, Fonnte, email, push notification, analytics, or frontend changes.

Verification:

```text
php artisan test: PASS
87 passed (707 assertions)
```

### Milestone 18 - Work Queue Foundation

Status: PASS

Delivered:

- Backend-only My Work / Work Queue foundation.
- No migrations and no new workflow states.
- Role-aware operational work queues:
  - Satgas sees only active assigned work.
  - Admin and Super Admin see global metadata queues.
  - Reporter is forbidden.
- Routes:
  - `GET /api/v1/my-work/summary`
  - `GET /api/v1/my-work/cases`
  - `GET /api/v1/my-work/investigations`
  - `GET /api/v1/my-work/recommendations`
- Summary includes unread notification count from existing M17 notification data.
- Notification browsing remains only through existing `/api/v1/notifications` endpoints.
- Responses exclude sensitive narratives, report chronology, victim/reporter identity, anonymous hints, tracking codes, investigation findings, recommendation narratives, decision content, evidence details, `risk_level_code`, and priority filters.
- No frontend changes, WhatsApp/Fonnte, mobile/Flutter, notification UI, user-management expansion, dashboard analytics duplication, or new state machine work.

Verification:

```text
Included in latest backend full suite:
102 passed (812 assertions)
```

### Milestone 19 - Reporter Registration Foundation

Status: PASS

Delivered:

- Backend-only reporter/student registration request foundation.
- Separate `reporter_registrations` table; pending registrations are not stored in `users`.
- Public self-registration endpoint:
  - `POST /api/v1/reporter-registrations`
- Required public rate limiting with `throttle:5,1`.
- Registration number foundation through `registration_number`.
- Registration statuses: `pending`, `approved`, `rejected`.
- Pending registration may temporarily store password hash.
- Approval creates active `reporter` user and clears registration password hash.
- Rejection clears registration password hash and creates no user.
- Duplicate prevention for active user and pending registration email/NIM.
- Admin/super_admin review APIs:
  - `GET /api/v1/reporter-registrations`
  - `GET /api/v1/reporter-registrations/{reporterRegistration}`
  - `PATCH /api/v1/reporter-registrations/{reporterRegistration}/approve`
  - `PATCH /api/v1/reporter-registrations/{reporterRegistration}/reject`
- Satgas and reporter have no registration review access.
- Audit events for submitted, approved, and rejected registration actions.
- No auto-login after registration.
- No email, WhatsApp/Fonnte, verification links, mobile UI, notification UI, frontend changes, or dummy reporter seeding.

Verification:

```text
ReporterRegistrationFoundationTest: 9 passed (64 assertions)
php artisan test: 102 passed (812 assertions)
```

### Milestone 20 - Reporter Self-Service Foundation

Status: PASS

Delivered:

- Backend-only authenticated reporter self-service foundation.
- No migrations and no frontend changes.
- Reporter-only endpoints:
  - `GET /api/v1/me/profile`
  - `PATCH /api/v1/me/profile`
  - `PATCH /api/v1/me/change-password`
  - `GET /api/v1/me/account-status`
- Authorization is intentionally narrow:
  - `reporter`: allowed
  - `admin`: forbidden
  - `super_admin`: forbidden
  - `satgas_ppks`: forbidden
- Editable fields are limited to `name` and `phone_number`.
- Non-editable fields include email, NIM, NIP, role, role ID, permissions, active status, and approval/reviewer metadata.
- Password change requires current password and confirmation.
- Password change revokes other Sanctum tokens while keeping the current token active.
- Reporter self-service audit actions are prepared for profile update and password change.
- No email verification, password reset by email, WhatsApp/Fonnte, notifications UI, uploads, user search, public profile browsing, Flutter, or frontend work.

Verification:

```text
Included in latest backend full suite:
116 passed (932 assertions)
```

### Milestone 21 - Reporter Portal Foundation (Backend)

Status: PASS

Delivered:

- Backend-only reporter-facing portal APIs.
- No migrations and no frontend changes.
- Reporter-only endpoints:
  - `GET /api/v1/portal/summary`
  - `GET /api/v1/portal/reports`
  - `GET /api/v1/portal/reports/{registrationNumber}`
  - `GET /api/v1/portal/notifications`
- Authorization is intentionally narrow:
  - `reporter`: allowed
  - `admin`: forbidden
  - `super_admin`: forbidden
  - `satgas_ppks`: forbidden
- Portal report responses use `registration_number` as reporter-facing identifier and do not expose internal report IDs.
- Portal report responses exclude `reviewed_at`.
- Reporter-facing status is abstracted to safe labels only:
  - `Submitted`
  - `Under Review`
  - `In Process`
  - `Completed`
- Raw report status, case status, workflow status codes, tracking codes, narratives, respondent details, investigation findings, recommendation content, decision content, recovery notes, evidence details, audit data, assignments, staff identities, and admin notes are not exposed.
- Portal summary includes total reports, active reports, completed reports, and unread notifications.
- Completion is based on linked case status `closed`; `report.forwarded` is not treated as completed.
- Portal notifications are read-only; mutation remains only through existing M17 notification endpoints.
- No public case browsing, analytics, messaging/chat, uploads, email, WhatsApp/Fonnte, Flutter, or frontend work.

Verification:

```text
ReporterPortalFoundationTest: 6 passed (54 assertions)
php artisan test: 116 passed (932 assertions)
```

### Milestone 22 - Reporter Portal Frontend Integration

Status: PASS

Delivered:

- React frontend integration for the Reporter Portal.
- Navigation/routes structured under `/portal` with appropriate layout.
- Portal Overview dashboard displaying total/active/completed reports and unread notifications.
- My Reports browse view with registration number search and filter capabilities.
- Report Detail view displaying safe status labels and metadata details.
- Read-only Portal Notifications list view.
- Self-service Profile page for editing name and phone number.
- Change Password form verifying current password and revoking other active Sanctum tokens.
- Bug fixes: Role-aware login redirect, root `/` route mapping, contextual 404 access-denied links, and cache clearing on logout.
- Frontend/backend contract aligned for reporter portal payloads.
- Account metadata contract aligned for `GET /api/v1/me/account-status`.

Verification:

```text
npm run lint: PASS, 0 errors, 6 pre-existing shadcn/Lovable react-refresh warnings
npm run build: PASS
```

Additional QA:

```text
Non-empty reporter demo verified
Frontend/backend contract aligned
Account metadata contract aligned
```

### Milestone 23 - User Management Foundation

Status: PASS

Delivered:

- Backend-only safe user management and lookup APIs.
- User directory and safe user detail for admin and super_admin.
- Role-filtered picker-safe lookup returning only `id`, `name`, `role.code`, and `role.name`.
- Activation and deactivation flows with Sanctum token revocation.
- Role assignment for `admin`, `satgas_ppks`, and `reporter` only.
- Super Admin promotion remains out of scope.
- Last active Super Admin protection.
- Audit logging for activation, deactivation, and role changes.
- No migrations, no frontend changes, and no user creation or invitation system.

Verification:

```text
125 passed (1025 assertions)
```

---

## 3. Current API Surface

Implemented or prepared API areas:

- Health
- Auth
- Master data
- Reports
- Cases
- Investigations
- Recommendations
- Decisions
- Recovery and monitoring
- Evidence metadata
- Audit logs
- Dashboard analytics prepared
- Notifications
- My Work / Work Queue
- Reporter registrations
- Reporter self-service
- Reporter portal
- User management
- Frontend dashboard, operational screen, and workflow action foundations

Not implemented yet:

- Evidence upload/download
- WhatsApp integration
- Frontend user/Satgas lookup picker integration for assignment and forwarding
- Frontend workflow status actions that require unavailable status option APIs
- Public/reporter frontend flows
- Flutter mobile app

---

## 4. Next Milestone

### Milestone 24 - Security Verification

Goal:

Finalize security hardening, privacy verification, and operational readiness checks for the backend and integrated frontend.

---

## 5. Future Candidate Areas

- Frontend Workflow Completion
- Security Verification
- Production Readiness
- Flutter Mobile Foundation

---

## 6. Deferred Items

Deferred until explicitly approved:

- Evidence upload and file download.
- Evidence file access/download and full chain-of-custody audit expansion.
- Investigation attachments.
- Evidence file upload/download verification.
- WhatsApp/Fonnte integration.
- Notification delivery tracking.
- Advanced dashboard analytics beyond M13 metadata aggregates.
- Frontend user/Satgas lookup picker integration.
- Frontend workflow status actions that require unavailable status option APIs.
- Public/reporter frontend flows.
- Flutter mobile app.
- Social login.
- Multi-tenant support.
- AI-assisted risk assessment.

---

## 7. Risks

| Risk | Current Mitigation |
|---|---|
| Sensitive data leakage through admin endpoints | Metadata-first resources and tests, except decision records where full admin read is intentional. |
| Workflow status drift | Centralized enums plus master data status transitions. |
| Unauthorized Satgas access | Active assignment checks in policies/services. |
| Anonymous reporter identity leakage | Anonymous reports do not store identity, phone, IP, or device data in business fields. |
| Seeder side effects | Seeders are idempotent and tests assert no business rows. |
| Future workflow coupling | Milestones keep recovery, evidence, dashboard analytics, notification, and WhatsApp separate. |
| Decision accidentally mutating case status | Milestone 9 service keeps decision transitions isolated from case status and case closing. |
| Recovery accidentally closing cases | Milestone 10 service keeps recovery and monitoring isolated from case closure. |
| Evidence access leakage | Milestone 11 keeps evidence metadata assigned-Satgas only; Admin and Super Admin have no default evidence access. |
| Audit log sensitive data leakage | Milestone 12 redaction service stores safe metadata and field-level deltas only. |
| Dashboard privacy leakage | Milestone 13 analytics are metadata-only, count-based, RBAC-scoped, and exclude narratives, anonymous identity, tracking codes, evidence details, and audit log aggregates. |
| Frontend authorization drift | Milestone 14 uses backend `user.role.code` as the canonical source for navigation and route access. |
| Frontend token persistence sprawl | Milestone 14 centralizes browser storage access through `frontend/src/lib/auth-storage.ts`. |
| Operational screen sensitive data leakage | Milestone 15 renders only backend-returned fields and preserves metadata-only responses. |
| Incomplete assignment/forwarding UI | Milestone 15 disables actions that require unavailable user/Satgas lookup APIs instead of introducing temporary numeric ID inputs. |
| Frontend workflow mutation drift | Milestone 16 routes all mutations through the centralized operations API client, maps Laravel 422 errors, avoids optimistic updates, and refreshes from backend after success. |
| Unsafe picker substitutes | Milestone 16 keeps forward-to-case, assignment, and investigation creation disabled until approved user/Satgas lookup APIs exist. |
| Notification payload leakage | Milestone 17 uses metadata-only database notifications with mandatory `notification_type_code`, own-user access only, and no sensitive narrative or identity fields. |
| Work queue privacy leakage | Milestone 18 queues are metadata-only, RBAC-scoped, assignment-scoped for Satgas, and exclude narratives, identities, tracking codes, evidence details, `risk_level_code`, and priority filters. |
| Unapproved reporter account access | Milestone 19 keeps pending registrations outside `users`; login becomes possible only after admin/super_admin approval creates an active reporter account. |
| Duplicate reporter accounts | Milestone 19 checks active user and pending registration email/NIM before submission and rechecks active users before approval. |
| Reporter self-service privilege escalation | Milestone 20 only allows role `reporter`, blocks admin/super_admin/satgas, and prohibits email, NIM, NIP, role, permissions, active status, and approval metadata edits. |
| Reporter portal sensitive data leakage | Milestone 21 uses portal-specific resources with `registration_number` identifiers, safe status labels, own-report scoping, read-only notifications, and no narratives, raw workflow codes, tracking codes, staff identities, assignments, evidence, audit data, or admin notes. |
| User management lookup exposure | Milestone 23 uses picker-safe lookup fields only, blocks `super_admin` lookup and promotion, and preserves last active Super Admin protection. |

---

## 8. Definition of Done for Future Milestones

A future backend milestone is done only when:

- Scope is implemented inside `backend/api`.
- Migrations are created and run successfully.
- Seeders are idempotent.
- Routes are listed and verified.
- Tests cover success, failure, RBAC, privacy, and out-of-scope protections.
- `php artisan test` passes.
- Final summary includes changed files, commands, results, and warnings.

A future frontend milestone is done only when:

- Scope is implemented inside `frontend/`.
- Backend API contracts are consumed through centralized API clients.
- Auth/token persistence remains centralized.
- Role authorization uses backend role codes, not display labels.
- Mock data replacement is explicitly scoped and documented.
- `npm run lint` and `npm run build` pass, with warnings documented.

---

## 9. Current Verification Baseline

Run from `backend/api`:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan route:list --path=api/v1
php artisan test
```

Latest known fully verified baseline before Milestone 13 implementation:

```text
71 passed (566 assertions)
```

Prepared Milestone 13 route additions:

```text
GET /api/v1/dashboard/summary
GET /api/v1/dashboard/reports
GET /api/v1/dashboard/cases
GET /api/v1/dashboard/workflow
GET /api/v1/dashboard/evidence
```

Full Milestone 13 route verification and test run are pending.

Latest frontend verification after Milestone 22:

```text
npm run lint: PASS, 0 errors, 6 pre-existing shadcn/Lovable react-refresh warnings
npm run build: PASS
```

Latest Milestone 22 QA:

```text
Non-empty reporter demo verified
Frontend/backend contract aligned
Account metadata contract aligned
```

Latest backend verification after Milestone 22:

```text
php artisan test: PASS
116 passed (932 assertions)
```

Milestone 23 User Management Foundation has been implemented and verified in the latest backend test run.

Latest backend verification after Milestone 23:

```text
php artisan test: PASS
125 passed (1025 assertions)
```

Verified Milestone 23 route additions:

```text
GET /api/v1/users
GET /api/v1/users/lookup
GET /api/v1/users/{user}
PATCH /api/v1/users/{user}/activate
PATCH /api/v1/users/{user}/deactivate
PATCH /api/v1/users/{user}/role
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
GET /api/v1/reporter-registrations
GET /api/v1/reporter-registrations/{reporterRegistration}
PATCH /api/v1/reporter-registrations/{reporterRegistration}/approve
PATCH /api/v1/reporter-registrations/{reporterRegistration}/reject
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
