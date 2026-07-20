# PERMISSION_MATRIX.md — Milestone 26

**Project:** SILAPPKASAL  
**Milestone:** M26 — Security & Privacy Enhancement  
**Status:** REV-WF-03 R2 override active; M26 content retained as legacy context
**Created:** 2026-06-22  
**Frozen:** 2026-06-22  

> [!IMPORTANT]
> The R2 override and executable RBAC are authoritative for Emergency Access. Historical M26
> assignments below must not be used when they conflict with the override.

---

## REV-WF-03 R2 Emergency Access Override

The executable R2 policy supersedes every M26 Break Glass assignment later in this historical
document. Other role capabilities are outside this R2 update and remain governed by the RBAC
seeder and deployed-data reconciliation migrations.

| Role | Request own assigned Case | View request metadata | Approve/deny/revoke | Reveal identity | Audit oversight |
|---|---:|---:|---:|---:|---:|
| `reporter` | No | No | No | No | No |
| `satgas_ppks` | Yes | Own only | No | Own active grant only | No |
| `admin` | No | Same campus | Same campus | No | No |
| `super_admin` | No | No operational access | No | No | Existing redacted oversight only |

Managed permission assignment:

- Satgas: `privacy.request_break_glass`, `privacy.reveal_anonymous_identity`.
- Admin: `privacy.approve_break_glass`.
- Super Admin and Reporter: none of the R2 operational permissions.
- `system.break_glass_access` remains a legacy permission code and is not assigned by R2.

## 1. Roles

| Code | Display Name | Description |
|---|---|---|
| `super_admin` | Super Admin | Cross-campus read-only oversight and redacted Emergency Access audit oversight; no operational Emergency Access authority |
| `admin` | Admin Kampus | Same-campus operations and Emergency Access review/approval/denial/revocation |
| `satgas_ppks` | Satgas PPKS | Assigned-case investigation and requester-scoped Emergency Access |
| `reporter` | Pelapor | Report submission, portal access |

---

## 2. Emergency Access Permissions (REV-WF-03 R2)

| Code | Name | Module | Assigned To |
|---|---|---|---|
| `privacy.reveal_anonymous_identity` | Reveal Anonymous Identity | Privasi | Assigned Satgas; exclusive own active grant |
| `privacy.approve_break_glass` | Review Emergency Access | Privasi | Same-campus Admin |
| `privacy.request_break_glass` | Request Emergency Access | Privasi | Assigned Satgas |
| `system.break_glass_access` | Legacy operational Break Glass | Sistem | No R2 role assignment |

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
| Reveal anonymous Reporter | 🔒 | 🔑 own active grant | 🔒 | 🔒 |
| Request Emergency Access | 🔒 | ✏️ active assigned Case | 🔒 | 🔒 |
| View request metadata | 🔒 | 📖 own | 📖 same campus | Oversight audit only |
| Approve/deny/revoke | 🔒 | 🔒 | ✏️ same campus | 🔒 |
| View redacted Emergency Access audit | 🔒 | 🔒 | 🔒 | 📖 |

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

## 4. Legacy General Assignment Snapshot with R2 Overrides

### Super Admin

```
system.configure, system.audit_log.view,
users.create, users.read, users.update, users.deactivate, users.assign_role,
reports.read.all, reports.verify, reports.reject, reports.forward, reports.request_info,
cases.read.metadata, cases.read.all, cases.assign_satgas, cases.record_decision, cases.monitor,
dashboard.admin, statistics.view, statistics.export,
```

Super Admin retains audit oversight permissions but has none of the R2 operational Emergency
Access permissions above.

### Admin

```
system.audit_log.view,
users.create, users.read, users.update, users.deactivate,
reports.read.all, reports.verify, reports.reject, reports.forward, reports.request_info,
cases.read.metadata, cases.assign_satgas, cases.record_decision, cases.monitor,
dashboard.admin, statistics.view, statistics.export,
privacy.approve_break_glass              ← REV-WF-03 R2
```

### Satgas PPKS

```
cases.read.assigned, cases.assess_risk, cases.investigate, cases.recommend,
cases.monitor, cases.close, cases.escalate,
messages.send, messages.read.case,
dashboard.satgas, statistics.view,
evidence.upload, evidence.view.case, evidence.download,
privacy.request_break_glass, privacy.reveal_anonymous_identity
```

### Reporter (legacy general snapshot)

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

---

## 6. REV-WF-03 R3 Finalization Authorization

| Action | Reporter | Satgas PPKS | Same-campus Admin | Super Admin |
|---|---:|---:|---:|---:|
| View draft final summary | No | Assigned, read-only | Yes | Only when sensitive oversight permits |
| View published final summary | Owned safe projection | Assigned, read-only | Yes | Read-only |
| Create/update/publish final summary | No | No | `cases.monitor` | No |
| Add Recovery Monitoring | No | Active assigned + `cases.monitor` | No | No |
| Complete/discontinue Recovery | No | No | `cases.monitor` | No |
| Close Case through dedicated endpoint | No | Active assigned + `cases.close` | No | No |
| Close Case through generic status endpoint | No | Rejected | Rejected | Rejected |

The Super Admin Cases sidebar link is hidden in R3. This navigation change does not revoke direct read-only Case authorization, Report-to-Case links, or Activity Log references.
