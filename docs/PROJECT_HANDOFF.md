# PROJECT_HANDOFF.md - SILAPPKASAL Project Handoff

> Status: Active Handoff  
> Last Updated: 2026-06-11  
> Current Backend Milestone: Milestone 12 Implementation Prepared - Audit Trail Foundation  
> Next Milestone: Milestone 13 - Notification Foundation

---

## 1. Project Snapshot

SILAPPKASAL is a secure reporting and case-handling platform for prevention and response to sexual violence in a university environment. The repository is structured with a Laravel REST API backend in `backend/api` and a React frontend in `frontend/`.

The backend is the current source of implemented business behavior. Frontend integration, evidence upload, notifications, analytics, WhatsApp integration, and Flutter work remain future work unless explicitly promoted.

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
| 12 | Audit Trail Foundation | Prepared, Pending Migration/Test Approval | Append-only audit log model, audit taxonomy constants, privacy-safe redaction service, audit log read API, admin/super_admin RBAC, no export/SIEM/notifications/frontend work. |

Latest fully verified checks after Milestone 11:

```text
php artisan route:list --path=api/v1
php artisan test

Routes: 42 API v1 routes
Tests: 71 passed (566 assertions)
```

Milestone 11 implementation has been test-verified locally. Commit status should be checked by the next operator before starting a new milestone.

Milestone 12 implementation is prepared in code and route discovery has been verified, but migrations and full tests have not been run pending explicit approval.

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
| Audit Logs | `GET /api/v1/audit-logs`, `GET /api/v1/audit-logs/{auditLog}` prepared, pending migration/test approval |

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
- Evidence file upload, download, preview, storage implementation, attachments, WhatsApp, notifications, analytics, and Flutter integration are not implemented yet.
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

Deferred security work:

- Strict security headers middleware.
- Audit trail foundation prepared, pending migration/test approval.
- Break-glass access.
- Break-glass evidence access and secure file streaming.
- Notification privacy review.
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
- `audit_logs` prepared by Milestone 12 migration

Not yet implemented:

- evidence file upload/download/preview/storage
- notification delivery records
- analytics/dashboard aggregates
- messaging

---

## 7. Next Milestone

After Milestone 12 is migrated, tested, and committed, the next milestone should be planned as:

```text
Milestone 13 - Notification Foundation
```

Expected focus:

- Internal notification persistence.
- Queue-backed notification jobs.
- Privacy-safe notification payloads.
- Relationship to reports, cases, investigations, recommendations, decisions, recoveries, and evidence metadata actions.
- No notifications, WhatsApp, analytics, or frontend integration unless explicitly approved.

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
71 passed
```

Prepared Milestone 12 route discovery:

```text
44 API v1 routes
```

Recommended verification commands:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan route:list --path=api/v1
php artisan test
```

---

## 9. Handoff Notes for Next Agent

- Read all relevant docs before planning a new milestone.
- Do not modify `frontend/` during backend milestones unless requested.
- Do not change Phase 1-4 docs unless explicitly approved.
- Before running migrations in a milestone implementation, show files created/modified, migration summary, route summary, and test summary when requested.
- Keep privacy and RBAC behavior conservative.
- Do not seed dummy users or business rows unless the milestone explicitly requires it.
- Keep business logic in services, access rules in policies, validation in form requests, and response shaping in resources.
