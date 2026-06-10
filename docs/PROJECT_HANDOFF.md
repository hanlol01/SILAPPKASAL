# PROJECT_HANDOFF.md — SILAPPKASAL Project Handoff

> Status: Active Handoff  
> Last Updated: 2026-06-11  
> Current Backend Milestone: Milestone 8 PASS — Recommendation Foundation  
> Next Milestone: Milestone 9 — Decision Foundation

---

## 1. Project Snapshot

SILAPPKASAL is a secure reporting and case-handling platform for prevention and response to sexual violence in a university environment. The repository is currently structured with a Laravel REST API backend in `backend/api` and a React frontend in `frontend/`.

The backend is the current source of implemented business behavior. Frontend integration, evidence upload, notifications, analytics, WhatsApp integration, and Flutter work remain future work unless explicitly promoted.

---

## 2. Completed Milestones

| Milestone | Name | Status | Summary |
|---|---|---|---|
| 1 | Repository Foundation | PASS | Repository structure established; frontend and backend boundaries clarified. |
| 2 | Laravel Foundation | PASS | Laravel 12.62.0 API foundation, PostgreSQL config, Sanctum installed, database queue, private storage disk, health endpoint. |
| 3 | Authentication & RBAC | PASS | Sanctum token auth, login/logout/me, roles, permissions, RBAC middleware, policies foundation, seeders, tests. |
| 4 | Master Data Foundation | PASS | Read-only master data tables, models, endpoints, seeders, and tests. |
| 5 | Report Intake Foundation | PASS | Anonymous and identified report intake, tracking code, metadata-first report reads, privacy rules, tests. |
| 6 | Case Foundation | PASS | Forward report to case, case number, assignment history, status transition foundation, metadata-first case access, tests. |
| 7 | Investigation Foundation | PASS | Investigation model, activity records, master-data-driven investigation status transitions, assigned Satgas detail access, admin metadata-only access, tests. |
| 8 | Recommendation Foundation | PASS | Recommendation model, completed-investigation reference, master-data-driven recommendation status transitions, status history, assigned Satgas detail access, admin metadata-only access, tests. |

Latest verification after Milestone 8:

```text
php artisan migrate --force
php artisan db:seed --force
php artisan route:list --path=api/v1
php artisan test

Routes: 24 API v1 routes
Tests: 52 passed (395 assertions)
```

---

## 3. Current Backend State

Backend location:

```text
backend/api
```

Implemented API groups:

| Area | Endpoint Coverage |
|---|---|
| Health | `GET /api/v1/health` |
| Auth | `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me` |
| Master Data | `GET /api/v1/master/{type}` |
| Reports | `POST /api/v1/reports`, `GET /api/v1/reports`, `GET /api/v1/reports/{report}`, `GET /api/v1/reports/track/{trackingCode}`, `POST /api/v1/reports/{report}/forward-to-case` |
| Cases | `GET /api/v1/cases`, `GET /api/v1/cases/{case}`, `PATCH /api/v1/cases/{case}/status`, `PATCH /api/v1/cases/{case}/assign` |
| Investigations | `POST /api/v1/cases/{case}/investigations`, `GET /api/v1/cases/{case}/investigations`, `GET /api/v1/investigations/{investigation}`, `PATCH /api/v1/investigations/{investigation}/status`, `POST /api/v1/investigations/{investigation}/activities` |
| Recommendations | `POST /api/v1/cases/{case}/recommendations`, `GET /api/v1/cases/{case}/recommendations`, `GET /api/v1/recommendations/{recommendation}`, `PATCH /api/v1/recommendations/{recommendation}`, `PATCH /api/v1/recommendations/{recommendation}/status` |

---

## 4. Core Implementation Principles

- Backend code is scoped to `backend/api`.
- Existing project docs remain source of truth.
- Sensitive narrative fields are encrypted using Laravel encrypted casts.
- Admin and Super Admin access remains metadata-first unless a milestone explicitly grants sensitive access.
- Assigned Satgas access is required for sensitive case, investigation, and recommendation details.
- Anonymous report identity is not stored.
- Evidence upload, attachments, WhatsApp, notifications, analytics, and Flutter integration are not implemented yet.
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
- Assigned Satgas can read sensitive case, investigation, and recommendation detail only for active assignments.
- Private evidence storage disk exists as foundation, but evidence workflow is not implemented.

Deferred security work:

- Strict security headers middleware.
- Persistent audit log implementation.
- Break-glass access.
- Evidence access policy and secure file streaming.
- Notification privacy review.
- Frontend token/session hardening.

---

## 6. Current Data Model Coverage

Implemented domain tables include:

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

Not yet implemented:

- evidence records and file upload metadata
- decisions
- recovery workflow
- notification delivery records
- audit logs
- analytics/dashboard aggregates
- messaging

---

## 7. Next Milestone

Milestone 9 should be planned and implemented as:

```text
Milestone 9 — Decision Foundation
```

Expected focus:

- Decision data model.
- Relationship to cases and submitted recommendations.
- Decision status/recording foundation.
- Institutional decision metadata and sensitive decision content privacy.
- Metadata-first admin visibility.
- Assigned Satgas workflow boundaries.
- No recovery workflow yet unless explicitly approved.

Milestone 9 should not implement evidence upload, recovery workflow, notifications, WhatsApp, analytics, or frontend integration unless explicitly approved.

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

Expected current test baseline:

```text
52 passed
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
