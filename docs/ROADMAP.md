# ROADMAP.md - SILAPPKASAL Development Roadmap

> Status: Active  
> Last Updated: 2026-06-11  
> Current Position: Milestone 9 implementation prepared, pending verification  
> Next: Milestone 10 - Recovery and Monitoring Foundation

---

## 1. Roadmap Overview

SILAPPKASAL development is currently backend-first. The Laravel API is being built in small, test-backed milestones before frontend integration and mobile work.

Current priorities:

1. Complete backend workflow foundations.
2. Preserve privacy and RBAC boundaries.
3. Add tests for each milestone.
4. Integrate frontend only after API behavior is stable.
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

Status: Implementation prepared, pending migration/seeder/test verification

Prepared:

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

Prepared route verification:

```text
29 API v1 routes
```

Pending verification:

```text
php artisan migrate --force
php artisan db:seed --force
php artisan test
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

Not implemented yet:

- Recovery and monitoring
- Evidence upload/download
- Notifications
- WhatsApp integration
- Audit logs
- Dashboard analytics
- User CRUD/admin account management
- Frontend API integration
- Flutter mobile app

---

## 4. Next Milestone

### Milestone 10 - Recovery and Monitoring Foundation

Goal:

Create the first recovery and monitoring workflow foundation after finalized institutional decisions, without starting evidence, notification, WhatsApp, analytics, or frontend work.

Recommended scope:

- Recovery and monitoring data model.
- Relationship to case and finalized decision.
- Recovery service/status foundation.
- Monitoring follow-up foundation.
- Privacy and RBAC rules for recovery-sensitive data.
- Read/list/detail API foundation.
- Tests.

Potential endpoints:

```text
POST /api/v1/decisions/{decision}/recovery
GET /api/v1/cases/{case}/recovery
GET /api/v1/recovery/{recovery}
PATCH /api/v1/recovery/{recovery}
PATCH /api/v1/recovery/{recovery}/status
POST /api/v1/recovery/{recovery}/monitoring
GET /api/v1/recovery/{recovery}/monitoring
```

Planning constraints:

- Recovery should not bypass case or decision rules.
- Consider requiring decision status `finalized`.
- Do not automatically close cases unless explicitly approved.
- Do not implement evidence upload.
- Do not implement notification/WhatsApp.
- Do not implement analytics.
- Do not modify frontend unless explicitly requested.

---

## 5. Proposed Future Milestones

| Milestone | Name | Purpose |
|---|---|---|
| 10 | Recovery and Monitoring Foundation | Recovery services and post-decision monitoring. |
| 11 | Evidence Foundation | Secure upload/download, private storage, evidence policies. |
| 12 | Audit Log Foundation | Persistent audit trail for critical actions. |
| 13 | Notification Foundation | Internal notification persistence and queue jobs. |
| 14 | WhatsApp Integration | Fonnte integration with privacy-safe templates. |
| 15 | Dashboard Analytics Foundation | Metadata-safe dashboard aggregates. |
| 16 | User Management Foundation | Admin user CRUD, deactivate, role assignment. |
| 17 | Frontend Auth Integration | Connect React auth to backend Sanctum flow. |
| 18 | Frontend Report/Case Integration | Replace mock report/case data with API. |
| 19 | Frontend Investigation/Recommendation/Decision Integration | Connect Satgas and admin workflow APIs. |
| 20 | Security Verification | Headers, CORS, rate limits, privacy review, penetration-style checklist. |
| 21 | Production Readiness | Deployment, environment hardening, backup, observability. |
| 22 | Flutter Planning | Mobile scope after stable backend and web integration. |

---

## 6. Deferred Items

Deferred until explicitly approved:

- Evidence upload and file download.
- Case evidence access and chain-of-custody.
- Investigation attachments.
- Recovery service workflow verification.
- WhatsApp/Fonnte integration.
- Notification delivery tracking.
- Dashboard analytics.
- Frontend integration.
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
| Future workflow coupling | Milestones keep recovery, evidence, notification, and analytics separate. |
| Decision accidentally mutating case status | Milestone 9 service keeps decision transitions isolated from case status and case closing. |

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

---

## 9. Current Verification Baseline

Run from `backend/api`:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan route:list --path=api/v1
php artisan test
```

Expected verified baseline after Milestone 8:

```text
52 passed (395 assertions)
```

Milestone 9 implementation is prepared, but final verification is pending:

```text
29 API v1 routes prepared
migration/seed/test not yet run
```
