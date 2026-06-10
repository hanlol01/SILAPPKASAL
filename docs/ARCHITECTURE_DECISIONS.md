# ARCHITECTURE_DECISIONS.md — SILAPPKASAL Decisions

> Status: Active  
> Last Updated: 2026-06-10  
> Covers: Milestone 1 through Milestone 7

---

## 1. Repository and Ownership

| Decision | Status | Rationale |
|---|---|---|
| Backend lives in `backend/api` | Accepted | Keeps Laravel API isolated from frontend work. |
| Frontend lives in `frontend/` | Accepted | Existing React frontend must not be rewritten during backend milestones. |
| Docs live in `docs/` | Accepted | Project documentation is the coordination source for agents and handoff. |
| Backend milestones must not modify frontend unless requested | Active Rule | Prevents accidental cross-scope churn. |

---

## 2. Backend Framework

| Decision | Status | Rationale |
|---|---|---|
| Laravel API backend | Accepted | Matches project stack and supports Sanctum, policies, queues, migrations, and tests. |
| Laravel 12.62.0 accepted | Accepted | Project owner approved Laravel 12.62.0 during Milestone 2. |
| PHP 8.3 local runtime | Accepted | Available local runtime and compatible with Laravel 12. |
| PostgreSQL primary database | Accepted | Required by project docs and current environment. |
| SQLite allowed for tests | Accepted | PHPUnit uses SQLite after local driver setup; tests run with `RefreshDatabase`. |

---

## 3. Authentication

| Decision | Status | Rationale |
|---|---|---|
| Laravel Sanctum Bearer tokens | Accepted | Suitable for stateless API, future SPA, and mobile clients. |
| Token name `web-login` | Accepted | Consistent auth token naming. |
| Token expiry via `SANCTUM_EXPIRATION` | Accepted | Allows environment-specific auth hardening. |
| No auth endpoints beyond login/logout/me yet | Active Scope | Registration, password reset, logout-all, and user CRUD are deferred. |
| Identifier login normalization | Accepted | Email is trimmed/lowercased; NIM/NIP are trimmed string identifiers. |

---

## 4. RBAC and Authorization

| Decision | Status | Rationale |
|---|---|---|
| Project-defined RBAC, no additional RBAC package | Accepted | Keeps authorization transparent and aligned to docs. |
| One role per user for MVP | Accepted | `users.role_id` is sufficient for current workflows. |
| Future multi-role compatibility note retained | Accepted | Can later add `role_user` pivot without changing role/permission core. |
| No `anonymous` role | Accepted | Anonymous is public unauthenticated access, not an RBAC identity. |
| Policies and Gates for resource access | Active Rule | Controllers delegate access checks; services enforce transactional/business invariants. |
| Middleware aliases `role` and `permission` exist | Accepted | Foundation for future route-level RBAC. |

Current seeded authenticated roles:

- `super_admin`
- `admin`
- `satgas_ppks`
- `reporter`

---

## 5. API Design

| Decision | Status | Rationale |
|---|---|---|
| Versioned API under `/api/v1` | Accepted | Protects future API compatibility. |
| Standard JSON shape | Active Rule | Responses use `{ success, message, data/errors }`; pagination adds `meta`. |
| Health endpoint remains | Accepted | `GET /api/v1/health` is kept as foundation endpoint. |
| Controllers stay thin | Active Rule | Controllers call form requests, services, policies, and resources. |
| Business logic in services | Active Rule | Transactional flows remain testable and centralized. |
| Response privacy in resources | Active Rule | Metadata-only and sensitive-detail responses are separated where needed. |

---

## 6. Database and Master Data

| Decision | Status | Rationale |
|---|---|---|
| Master data tables are seeded idempotently | Accepted | Enables repeatable setup across environments. |
| `notification_types` internal only | Accepted | No public endpoint exposure yet. |
| No faculties or study programs in Milestone 4 | Accepted | Not required by approved scope. |
| Report status uses centralized constants | Accepted | Avoids scattered hardcoded report statuses. |
| Case status uses master data references | Accepted | `cases.status_code` references `case_statuses.code`. |
| Investigation status uses master data transitions | Accepted | `investigation_statuses.valid_transitions` drives lifecycle foundation. |

---

## 7. Reports

| Decision | Status | Rationale |
|---|---|---|
| Anonymous report submission is public | Accepted | Anonymous reports must not require auth. |
| Anonymous identity is not stored | Active Privacy Rule | Protects reporter privacy. |
| Tracking code has at least 16 random characters excluding separators | Accepted | Improves entropy and tracking safety. |
| Tracking endpoint excludes soft-deleted reports | Active Privacy Rule | Prevents deleted reports from leaking through public tracking. |
| `submitted_at` explicit field | Accepted | Makes business submission time independent from row timestamps. |
| `reporter_phone` only for confidential reports when supplied | Accepted | Limits unnecessary sensitive data. |

---

## 8. Cases

| Decision | Status | Rationale |
|---|---|---|
| `case_number` is separate from `registration_number` | Accepted | Distinguishes report intake identity from case lifecycle identity. |
| Report status becomes `forwarded` only after case transaction succeeds | Active Invariant | Prevents report/case mismatch. |
| Case status becomes source of truth after forwarding | Accepted | Report does not mirror later case lifecycle changes. |
| Assignment history is retained | Active Rule | Reassignment marks old active rows inactive instead of deleting history. |
| `closed` is terminal | Active Rule | Closed cases reject status transitions and reassignment. |
| Admin case reads are metadata-first | Active Privacy Rule | Sensitive report content is limited to assigned Satgas. |

---

## 9. Investigations

| Decision | Status | Rationale |
|---|---|---|
| Investigation belongs to a case | Accepted | Case is the parent workflow entity. |
| One investigation per case foundation | Accepted | Current schema uses unique `case_id`. |
| Investigation creation is assigned-Satgas initiated | Accepted | Admin/super_admin do not initiate investigations in Milestone 7. |
| Case must be in `investigation` status to start investigation | Active Invariant | Investigation cannot bypass case lifecycle rules. |
| Investigation statuses use `investigation_statuses.valid_transitions` | Active Rule | Avoids hardcoded lifecycle sequence in service/controller. |
| Activity type is app-level string validation, not DB enum | Accepted | Keeps activity vocabulary flexible. |
| Neutral activity naming preferred | Accepted | `document_review` is used instead of upload-oriented terminology. |
| Admin/super_admin investigation reads are metadata-only | Active Privacy Rule | Decrypted investigation content is only for assigned Satgas. |

---

## 10. Storage, Queue, and Deferred Integrations

| Decision | Status | Rationale |
|---|---|---|
| Database queue driver | Accepted | Foundation queue driver for local/dev and future jobs. |
| Private evidence storage disk prepared | Accepted | Evidence files must not be public when implemented. |
| Evidence workflow deferred | Active Scope | No upload, download, attachment, or evidence metadata business logic yet. |
| WhatsApp/Fonnte deferred | Active Scope | Notifications remain future integration. |
| Analytics deferred | Active Scope | No dashboard aggregate implementation yet. |
| Flutter deferred | Active Scope | Mobile integration waits for stable API. |

---

## 11. Testing Decisions

| Decision | Status | Rationale |
|---|---|---|
| Feature tests per milestone | Active Rule | Guards business behavior and privacy rules. |
| `RefreshDatabase` for DB-dependent tests | Accepted | Ensures schema is rebuilt consistently during tests. |
| Seed reference data in test setup | Accepted | Tests use RBAC and master data seeders, not dummy default users. |
| Current baseline | PASS | `45 passed (348 assertions)` after Milestone 7. |

---

## 12. Known Deferred Decisions

- Persistent audit log schema and event coverage.
- Evidence access policy details and secure streaming strategy.
- Recommendation workflow shape.
- Decision workflow and institutional approval flow.
- Recovery/monitoring workflow.
- Notification persistence and Fonnte payload privacy.
- Frontend auth integration pattern.
- Production storage provider.
- Deployment topology.
