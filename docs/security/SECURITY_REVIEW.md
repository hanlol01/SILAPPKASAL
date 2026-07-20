# SECURITY_REVIEW.md — Milestone 26

**Project:** SILAPPKASAL  
**Milestone:** M26 — Security & Privacy Enhancement  
**Status:** Historical M26 review with REV-WF-03 R2 Emergency Access override
**Created:** 2026-06-22  
**Frozen:** 2026-06-22  

> [!IMPORTANT]
> Findings remain historical. For current Emergency Access behavior use `BREAK_GLASS_POLICY.md`,
> executable RBAC, policies, services, migrations, and tests.

---

## 1. Current Security Posture

### 1.1 Authentication

| Mechanism | Implementation | Status |
|---|---|---|
| Auth framework | Laravel Sanctum (token-based SPA/API) | ✅ Active |
| Login throttle | `throttle:5,1` on `POST /auth/login` | ✅ Active |
| Token storage (frontend) | `localStorage` or `sessionStorage` via `auth-storage.ts` | ⚠️ XSS risk if CSP is weak |
| Token format | Sanctum `PersonalAccessToken` with Bearer header | ✅ Standard |
| Token expiration | Not explicitly configured — Sanctum default (no expiry) | ⚠️ Tokens may live indefinitely |
| Password hashing | User model `password` cast as `hashed` (bcrypt) | ✅ Adequate |
| Session management | Stateless API tokens, no server-side sessions | ✅ Adequate |

### 1.2 Authorization (RBAC)

| Layer | Implementation | Status |
|---|---|---|
| Role model | `Role` with `code` — 4 roles seeded | ✅ Active |
| Permission model | `Permission` with `code` — 33 permissions seeded | ✅ Active |
| Role-permission mapping | `RolePermission` pivot, synced via `RbacSeeder` | ✅ Active |
| Policy enforcement | `BasePolicy` with `allowPermission()` and `allowRole()` | ✅ Active |
| Gate registration | Laravel Gates mapped to Policies | ✅ Active |
| Middleware | `PermissionMiddleware` and `RoleMiddleware` available | ✅ Available |
| Frontend RBAC | `auth-roles.ts` with `hasDashboardAccess()` / `hasPortalAccess()` | ✅ Active |

### 1.3 Encryption at Rest

| Field | Model | Cast | Status |
|---|---|---|---|
| `chronology` | Report | `encrypted` | ✅ |
| `incident_location` | Report | `encrypted` | ✅ |
| `respondent_name` | Report | `encrypted` | ✅ |
| `respondent_details` | Report | `encrypted` | ✅ |
| `witness_info` | Report | `encrypted` | ✅ |
| `reporter_phone_encrypted` | Report | `encrypted` | ✅ |
| `password` | User | `hashed` (bcrypt) | ✅ |

### 1.4 Audit Logging

| Feature | Implementation | Status |
|---|---|---|
| Audit model | `AuditLog` with actor, action, category, severity, metadata, before/after diffs | ✅ Foundation |
| Audit policy | `AuditLogPolicy` — Admin and Super Admin with `system.audit_log.view` | ✅ Active |
| Emergency Access audit | Six redacted lifecycle events; Super Admin oversight only | ✅ R2 active |

---

## 2. Role-Based Access Risks

### 2.1 Critical Risks

| Risk | Severity | Remediation (FINAL) |
|---|---|---|
| `POST /reports` has no `auth:sanctum` middleware | 🔴 HIGH | **Add `auth:sanctum`.** Anonymous = masked, not unauthenticated. |
| Anonymous reports store `reporter_id = null` | 🔴 HIGH | **Always store `reporter_id = auth()->id()`.** Remove null branch. |
| Legacy `system.break_glass_access` | Resolved | **R2 does not assign it; Satgas request/reveal and Admin review use explicit privacy permissions.** |

### 2.2 Permission Inconsistencies

| Issue | Decision (FINAL) |
|---|---|
| Reporter has `evidence.upload`, `evidence.view.case` | **Keep.** Future capabilities. |
| Reporter has `messages.send`, `messages.read.case` | **Keep.** Future messaging milestone. |
| Admin lacks `users.assign_role` | **Intentional.** Super Admin only. |
| Admin has no evidence access permissions | **Intentional.** Admin manages metadata, not evidence. |

### 2.3 API Exposure Risks

| Endpoint | Risk | Remediation (FINAL) |
|---|---|---|
| `POST /v1/reports` | 🔴 No auth | **Add `auth:sanctum` middleware** |
| `GET /v1/reports/track/{trackingCode}` | 🟡 No auth (by design) | **Keep.** Tracking codes continue to be generated. |
| `GET /v1/users/lookup` | 🟡 No explicit permission | **Post-M26.** |

---

## 3. Recommendations (FINAL)

### M26 Scope

| # | Action | Status |
|---|---|---|
| 1 | Add `auth:sanctum` middleware to `POST /v1/reports` | FINAL |
| 2 | Always store `reporter_id` (remove null branch) | FINAL |
| 3 | Mask reporter identity in API resources for anonymous reports using `{ masked: true }` | FINAL |
| 4 | Reuse `break_glass_requests` with requester-scoped grant lifecycle + audit log | R2 IMPLEMENTED |
| 5 | Add 3 new privacy permissions | FINAL |
| 6 | Show anonymous reports in reporter's portal | FINAL |
| 7 | Requester-only reveal returns the R2 allowlisted identity projection | R2 IMPLEMENTED |
| 8 | New grants last 30/60/240/1440 minutes from approval; legacy rows use bounded 8-hour compatibility | R2 IMPLEMENTED |
| 9 | Break-glass audit entries visible to Super Admin only | FINAL |
