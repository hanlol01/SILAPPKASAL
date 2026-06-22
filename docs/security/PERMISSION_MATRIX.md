# PERMISSION_MATRIX.md — Milestone 26

**Project:** SILAPPKASAL  
**Milestone:** M26 — Security & Privacy Enhancement  
**Status:** 🔒 FROZEN — All Decisions FINAL  
**Created:** 2026-06-22  
**Frozen:** 2026-06-22  

> [!IMPORTANT]
> This document is frozen. All open questions have been resolved. Decisions are final.
> Do not modify this document. Implementation follows M26_IMPLEMENTATION_PLAN.md.

---

## 1. Roles

| Code | Display Name | Description |
|---|---|---|
| `super_admin` | Super Admin | Full system control, break-glass approver, break-glass audit viewer |
| `admin` | Admin | Report management, user management, break-glass requestor |
| `satgas_ppks` | Satgas PPKS | Case investigation, evidence, recommendations |
| `reporter` | Pelapor | Report submission, portal access |

---

## 2. New Permissions (M26 — FINAL)

| Code | Name | Module | Assigned To |
|---|---|---|---|
| `privacy.reveal_anonymous_identity` | Reveal Anonymous Identity | Privasi | Super Admin |
| `privacy.approve_break_glass` | Approve Break-Glass | Privasi | Super Admin |
| `privacy.request_break_glass` | Request Break-Glass | Privasi | Admin, Super Admin |

---

## 3. Role × Resource Permission Matrix (FINAL)

Legend: ✅ Full · 📖 Read · ✏️ Write · 👤 Own only · 🔒 Denied · 🔑 Break-glass

### 3.1 Reports

| Operation | Reporter | Satgas PPKS | Admin | Super Admin |
|---|---|---|---|---|
| Create report | ✅ | 🔒 | 🔒 | 🔒 |
| View own reports | 📖👤 | 🔒 | 🔒 | 🔒 |
| View all reports | 🔒 | 🔒 | 📖 | 📖 |
| Verify/reject/forward | 🔒 | 🔒 | ✏️ | ✏️ |

### 3.2 Anonymous Report Identity

| Operation | Reporter | Satgas PPKS | Admin | Super Admin |
|---|---|---|---|---|
| View own identity | ✅ | — | — | — |
| View anonymous reporter | 🔒 | 🔒 | 🔒 | 🔑 (minimal: name+email) |
| Request break-glass | 🔒 | 🔒 | ✏️ | ✏️ |
| Approve break-glass | 🔒 | 🔒 | 🔒 | ✏️ |
| View break-glass audit | 🔒 | 🔒 | 🔒 | 📖 |

### 3.3 Cases

| Operation | Reporter | Satgas PPKS | Admin | Super Admin |
|---|---|---|---|---|
| View assigned cases | 🔒 | 📖 | 🔒 | 🔒 |
| View case metadata (all) | 🔒 | 🔒 | 📖 | 📖 |
| Assign Satgas | 🔒 | 🔒 | ✏️ | ✏️ |
| Update case status | 🔒 | ✏️ (assigned) | 🔒 | 🔒 |

### 3.4 Investigations, Recommendations, Decisions, Recovery

| Operation | Reporter | Satgas PPKS | Admin | Super Admin |
|---|---|---|---|---|
| Create/update (assigned) | 🔒 | ✏️ | 🔒 | 🔒 |
| View (assigned) | 🔒 | 📖 | 📖 metadata | 📖 metadata |
| Record decision | 🔒 | 🔒 | ✏️ | ✏️ |

### 3.5 Evidence

| Operation | Reporter | Satgas PPKS | Admin | Super Admin |
|---|---|---|---|---|
| Upload/view/download | 🔒 (future) | ✏️📖 (assigned) | 🔒 | 🔒 |

### 3.6 System

| Operation | Reporter | Satgas PPKS | Admin | Super Admin |
|---|---|---|---|---|
| Notifications | 📖👤 | 📖👤 | 📖👤 | 📖👤 |
| User management | 🔒 | 🔒 | ✏️📖 | ✅ |
| Audit logs | 🔒 | 🔒 | 📖 (general only) | 📖 (all including break-glass) |
| System settings | 🔒 | 🔒 | 🔒 | ✏️📖 |

---

## 4. Complete Role → Permission Assignments (FINAL)

### Super Admin (23 permissions)

```
system.configure, system.audit_log.view, system.break_glass_access,
users.create, users.read, users.update, users.deactivate, users.assign_role,
reports.read.all, reports.verify, reports.reject, reports.forward, reports.request_info,
cases.read.metadata, cases.read.all, cases.assign_satgas, cases.record_decision, cases.monitor,
dashboard.admin, statistics.view, statistics.export,
privacy.reveal_anonymous_identity,       ← M26 NEW
privacy.approve_break_glass,             ← M26 NEW
privacy.request_break_glass              ← M26 NEW
```

### Admin (19 permissions)

```
system.audit_log.view,
users.create, users.read, users.update, users.deactivate,
reports.read.all, reports.verify, reports.reject, reports.forward, reports.request_info,
cases.read.metadata, cases.assign_satgas, cases.record_decision, cases.monitor,
dashboard.admin, statistics.view, statistics.export,
privacy.request_break_glass              ← M26 NEW
```

### Satgas PPKS (14, unchanged)

```
cases.read.assigned, cases.assess_risk, cases.investigate, cases.recommend,
cases.monitor, cases.close, cases.escalate,
messages.send, messages.read.case,
dashboard.satgas, statistics.view,
evidence.upload, evidence.view.case, evidence.download
```

### Reporter (6, unchanged)

```
reports.create, reports.read.own,
messages.send, messages.read.case,       ← future capability
evidence.upload, evidence.view.case      ← future capability
```

---

## 5. Audit Log Visibility Change (FINAL — Q12=B)

| Audit Category | Admin | Super Admin |
|---|---|---|
| General audit logs | 📖 Visible | 📖 Visible |
| Break-glass audit logs (`category: "privacy"`) | 🔒 **Hidden** | 📖 Visible |

**Implementation:** `AuditLogPolicy` must filter break-glass entries for Admin role. Admin with `system.audit_log.view` sees all audit logs EXCEPT those with `category = "privacy"`.
