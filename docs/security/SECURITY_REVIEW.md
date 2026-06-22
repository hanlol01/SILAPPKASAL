# SECURITY_REVIEW.md — Milestone 26

**Project:** SILAPPKASAL  
**Milestone:** M26 — Security & Privacy Enhancement  
**Status:** 🔒 FROZEN — All Decisions FINAL  
**Created:** 2026-06-22  
**Frozen:** 2026-06-22  

> [!IMPORTANT]
> This document is frozen. All open questions have been resolved. Decisions are final.
> Do not modify this document. Implementation follows M26_IMPLEMENTATION_PLAN.md.

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
| Break-glass audit | Permission `system.break_glass_access` seeded for Super Admin | ⚠️ Not enforced yet |

---

## 2. Role-Based Access Risks

### 2.1 Critical Risks

| Risk | Severity | Remediation (FINAL) |
|---|---|---|
| `POST /reports` has no `auth:sanctum` middleware | 🔴 HIGH | **Add `auth:sanctum`.** Anonymous = masked, not unauthenticated. |
| Anonymous reports store `reporter_id = null` | 🔴 HIGH | **Always store `reporter_id = auth()->id()`.** Remove null branch. |
| `system.break_glass_access` not enforced | 🟡 MEDIUM | **Implement break-glass workflow.** |

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
| 4 | Implement break-glass workflow with dedicated table + audit log | FINAL |
| 5 | Add 3 new privacy permissions | FINAL |
| 6 | Show anonymous reports in reporter's portal | FINAL |
| 7 | Break-glass reveal returns minimal profile (name + email only) | FINAL |
| 8 | Break-glass reveal has 8-hour TTL | FINAL |
| 9 | Break-glass audit entries visible to Super Admin only | FINAL |
