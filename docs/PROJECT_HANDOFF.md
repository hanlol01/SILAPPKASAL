# PROJECT_HANDOFF.md - SILAPPKASAL Project Handoff

> Status: Active Handoff  
> Last Updated: 2026-06-11  
> Current Backend Milestone: Milestone 18 Implementation Prepared - Work Queue Foundation  
> Next Milestone: Milestone 19 - WhatsApp Integration

---

## 1. Project Snapshot

SILAPPKASAL is a secure reporting and case-handling platform for prevention and response to sexual violence in a university environment. The repository is structured with a Laravel REST API backend in `backend/api` and a React frontend in `frontend/`.

The backend is the source of implemented business behavior. The React frontend in `frontend/` now has authenticated dashboard integration, operational report/case screens, and safe workflow action forms for admin/Satgas roles. Evidence upload, WhatsApp integration, reporter public flows, user/Satgas lookup pickers, and Flutter work remain future work unless explicitly promoted.

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
| 17 | Notification Foundation | PASS | Laravel native database notifications, queued database channel only, metadata-only payloads with mandatory notification_type_code, low-noise workflow triggers, own-user read/list APIs, no WhatsApp/Fonnte/email/push/frontend work. |
| 18 | Work Queue Foundation | Prepared, Pending Verification | Backend-only My Work queues for summary, cases, investigations, and recommendations; Satgas active-assignment scope; admin/super_admin global metadata queues; notification count summary; no frontend, migrations, new states, priority filter, or notification browsing duplication. |

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

Milestone 18 work queue foundation is prepared in code, but backend tests and route verification have not been run yet after the Milestone 18 changes.

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
| My Work | `GET /api/v1/my-work/summary`, `GET /api/v1/my-work/cases`, `GET /api/v1/my-work/investigations`, `GET /api/v1/my-work/recommendations` prepared, pending verification |

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
- Evidence file upload, download, preview, storage implementation, attachments, WhatsApp, advanced analytics, and Flutter integration are not implemented yet.
- Frontend workflow actions that require user/Satgas lookup APIs remain disabled until approved lookup endpoints exist.
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

Not yet implemented:

- evidence file upload/download/preview/storage
- WhatsApp/Fonnte notification delivery records
- messaging

---

## 7. Next Milestone

After Milestone 18 is verified and committed, the next backend milestone should be planned as:

```text
Milestone 19 - WhatsApp Integration
```

Expected focus:

- Fonnte service integration.
- WhatsApp queue job.
- Privacy-safe WhatsApp templates.
- Delivery tracking strategy.
- Retry/failure handling.
- No analytics dashboard expansion or frontend integration unless explicitly approved.

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
87 passed
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
87 passed (707 assertions)
```

Prepared Milestone 18 route additions:

```text
GET /api/v1/my-work/summary
GET /api/v1/my-work/cases
GET /api/v1/my-work/investigations
GET /api/v1/my-work/recommendations
```

Full Milestone 18 route verification and test run are pending.

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
- Do not add temporary numeric ID inputs for assignment, forwarding, or investigator/Satgas selection; keep dependent actions disabled until lookup APIs exist.
- Recommendation/decision/investigation status actions remain disabled until approved status option or transition sources are available.
