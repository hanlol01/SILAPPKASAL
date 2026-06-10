# AGENT_PLAYBOOK.md — SILAPPKASAL Agent Playbook

> Status: Active  
> Last Updated: 2026-06-10  
> Audience: Backend, Web, Mobile, Reviewer, and Documentation Agents

---

## 1. Operating Rules

1. Read project documentation before planning or implementing a milestone.
2. Respect milestone scope exactly.
3. Do not modify `frontend/` during backend work unless explicitly requested.
4. Do not modify application code during documentation-only tasks.
5. Do not modify frozen or passed docs unless the user explicitly approves it.
6. Before migrations, show planned files, migration summary, route summary, and test summary when requested.
7. Do not seed dummy users or business data unless explicitly required.
8. Keep privacy behavior conservative.
9. Run tests before declaring a milestone complete.
10. Report any warning, skipped verification, or failure plainly.

---

## 2. Current Project State

Completed:

- Milestone 1 — Repository Foundation
- Milestone 2 — Laravel Foundation
- Milestone 3 — Authentication & RBAC
- Milestone 4 — Master Data Foundation
- Milestone 5 — Report Intake Foundation
- Milestone 6 — Case Foundation
- Milestone 7 — Investigation Foundation

Next:

- Milestone 8 — Recommendation Foundation

Current backend baseline:

```text
backend/api
Laravel 12.62.0
Sanctum token auth
PostgreSQL primary DB
SQLite test DB
Database queue driver
Private evidence storage foundation
45 tests passing after Milestone 7
```

---

## 3. Required Reading by Task Type

For backend milestone planning:

- `docs/PROJECT_MASTER.md`
- `docs/PRD.md`
- `docs/MASTER_DATA.md`
- `docs/AUTH_FLOW.md`
- `docs/SECURITY_POLICY.md`
- `docs/DATABASE_SCHEMA.md`
- `docs/API_SPECIFICATION.md`
- `docs/DEVELOPMENT_WORKFLOW.md`
- `docs/PHASE_4_PLANNING.md`
- current backend implementation under `backend/api`

For implementation:

- All planning docs above.
- Current migrations, seeders, models, services, policies, routes, and tests.

For documentation:

- Current completed milestone state.
- Latest verification results.
- Existing architecture and API decisions.

---

## 4. Backend Agent Workflow

Use this sequence for every backend milestone:

1. Review docs and current implementation.
2. Produce implementation plan when requested.
3. Wait for approval if user asks planning-only.
4. Audit existing code before adding new code.
5. Keep changes inside `backend/api` unless explicitly approved.
6. Create migrations, models, requests, services, policies, resources, controllers, routes, seeders, and tests as needed.
7. Before running migrations, show:
   - files created
   - files modified
   - migration summary
   - route summary
   - test summary
8. Run approved commands.
9. Patch failures only within scope.
10. Final response must include:
   - migration result
   - seeder result
   - route list summary
   - test result
   - changed files
   - warnings/errors

---

## 5. Laravel Implementation Patterns

Follow established backend patterns:

| Concern | Pattern |
|---|---|
| Validation | `app/Http/Requests/*Request.php` |
| HTTP entrypoint | `app/Http/Controllers/Api/V1/*Controller.php` |
| Business rules | `app/Services/*Service.php` |
| Authorization | Policies plus RBAC helpers |
| Response shape | API Resources |
| Status constants | PHP enums/constants plus master data where appropriate |
| DB schema | Laravel migrations |
| Reference data | Idempotent seeders |
| Tests | Feature and unit tests with `RefreshDatabase` |

Controllers should remain thin. Services should own transactions and business invariants. Policies should gate access. Resources should prevent accidental sensitive-field exposure.

---

## 6. Privacy Rules

Never weaken these without explicit approval:

- Anonymous reports do not require authentication.
- Anonymous report identity must not be stored.
- Anonymous report phone, IP, or device data must not be stored in report business fields.
- Tracking endpoint must not expose soft-deleted reports.
- Admin and Super Admin reads are metadata-first unless a milestone explicitly expands access.
- Assigned Satgas can access sensitive case/investigation detail only for active assignments.
- Super Admin does not automatically access evidence.
- Evidence files must not be publicly served.
- Decrypted investigation content must not be returned to admin/super_admin metadata endpoints.

---

## 7. RBAC Rules

Current authenticated roles:

- `super_admin`
- `admin`
- `satgas_ppks`
- `reporter`

Rules:

- Do not add `anonymous` role.
- Use project-defined RBAC; do not install third-party RBAC packages.
- Keep seeders idempotent.
- Add permissions only when the milestone needs them.
- Audit permission matrix before implementing workflows that depend on permissions.
- Keep future multi-role compatibility in mind, but MVP remains one role per user.

---

## 8. Milestone-Specific Guardrails

### Reports

- Use status constants/enums.
- Use explicit `submitted_at`.
- Tracking code must include at least 16 random characters excluding separators.
- `reporter_phone` is only stored for confidential reports when supplied.
- Public tracking returns safe metadata only.

### Cases

- `case_number` is separate from report `registration_number`.
- Forwarding must be transactional.
- Report status becomes `forwarded` only after case creation succeeds.
- Case status is source of truth after forwarding.
- Assignment history must not be deleted.
- `closed` is terminal.

### Investigations

- Investigation belongs to a case.
- Creation is assigned-Satgas initiated.
- Case must already be in `investigation` status.
- Status transitions come from `investigation_statuses.valid_transitions`.
- `activity_type` is app-level string validation, not DB enum.
- Prefer neutral activity names like `document_review`.
- No evidence upload or attachments.

---

## 9. Verification Commands

Run from `backend/api`:

```bash
php artisan about
php artisan migrate --force
php artisan db:seed --force
php artisan route:list --path=api/v1
php artisan test
```

Current expected test baseline after Milestone 7:

```text
45 passed (348 assertions)
```

---

## 10. Milestone 8 Starting Point

Milestone 8 should plan and implement Recommendation Foundation.

Recommended planning questions:

- Does recommendation belong one-to-one with case or investigation?
- Which statuses from `recommendation_statuses` are required?
- Who can create and update recommendations?
- Should recommendation creation require a completed investigation?
- What fields are sensitive and encrypted?
- What is admin metadata-only shape?
- Which permissions already exist and which need idempotent seeding?
- Which endpoints are needed without starting decision workflow?

Out of scope unless approved:

- Decision workflow
- Recovery workflow
- Evidence upload
- Notifications
- WhatsApp
- Analytics
- Frontend integration

---

## 11. Final Response Checklist

For implementation tasks, final response should include:

- What changed.
- Commands run.
- Test result.
- Files created/modified.
- Any errors or warnings.
- Any work intentionally deferred.

For planning-only tasks, final response should include implementation plan only and must not mention code changes as completed.
