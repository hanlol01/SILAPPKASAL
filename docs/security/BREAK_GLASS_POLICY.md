# BREAK_GLASS_POLICY.md — Milestone 26

**Project:** SILAPPKASAL  
**Milestone:** M26 — Security & Privacy Enhancement  
**Status:** 🔒 FROZEN — All Decisions FINAL  
**Created:** 2026-06-22  
**Frozen:** 2026-06-22  

> [!IMPORTANT]
> This document is frozen. All open questions have been resolved. Decisions are final.
> Do not modify this document. Implementation follows M26_IMPLEMENTATION_PLAN.md.

---

## Approved Product Owner Decisions

| Q# | Decision | Detail |
|---|---|---|
| Q1 | A | Legacy `reporter_id=null` reports: leave as-is, no migration |
| Q2 | A | Keep tracking code for new anonymous reports |
| Q3 | B | API returns `reporter: { masked: true }` for anonymous reports |
| Q4 | C | Notify reporter only on break-glass approval |
| Q5 | C | Both dedicated `break_glass_requests` table + audit log entries |
| Q6 | B | Break-glass reveal expires after **8-hour TTL** |
| Q7 | B | Reveal returns **minimal profile only** (name + email) |
| Q8 | A | Remove `reporter_id = null` branch in `ReportService::submit()` |
| Q9 | C | SLA: 4 hours for `safety_emergency`, 24 hours for others |
| Q10 | A | No limit on break-glass requests per report |
| Q11 | A | Denied requests retryable with new justification |
| Q12 | B | Break-glass audit entries visible to **Super Admin only** |

---

## 1. Definition

**Break-glass access** is an exceptional, audited procedure to reveal the identity of an anonymous reporter. It is never routine, never casual, always audited, always intentional.

---

## 2. Who May Request (FINAL)

| Role | May Request? | Permission |
|---|---|---|
| Reporter | ❌ | — |
| Satgas PPKS | ❌ | Must escalate to Admin |
| Admin | ✅ | `privacy.request_break_glass` |
| Super Admin | ✅ | `privacy.request_break_glass` |

---

## 3. Who May Approve (FINAL)

| Role | May Approve? | Permission |
|---|---|---|
| Admin | ❌ | Cannot approve, even own requests |
| Super Admin | ✅ | `privacy.approve_break_glass` |

Self-approval: Super Admin cannot approve their own request if a second Super Admin exists. Single Super Admin: self-approval allowed with `metadata.single_approver_override: true`.

---

## 4. Request Workflow (FINAL)

```
Step 1: REQUEST (Admin or Super Admin)
  → report_id, reason_category, reason (min 50 chars), acknowledgment
  → Creates break_glass_requests { status: "pending" }
  → AuditLog: break_glass.request, severity: critical
  → Notification: all Super Admins

Step 2: REVIEW (Super Admin)
  → Sees: requestor, report, reason, chronology for context
  → Reporter identity NOT yet revealed

Step 3a: APPROVE
  → status: "approved", approved_at, approver_id
  → AuditLog: break_glass.approve, severity: critical
  → Notification: requestor notified
  → Notification: reporter notified ("Your identity was revealed via break-glass")

Step 3b: DENY
  → status: "denied", denied_at, denial_reason
  → AuditLog: break_glass.deny, severity: critical
  → Notification: requestor notified (no reporter notification)

Step 4: VIEW IDENTITY (approved only)
  → Returns MINIMAL PROFILE: { name, email } only
  → 8-hour TTL from first view (viewed_at + 8h)
  → AuditLog: break_glass.view_identity, severity: critical
  → After TTL expires: re-access returns 403
  → Subsequent API calls still show masked identity
```

---

## 5. Reveal Scope (FINAL — Q7=B)

| Field | Revealed? |
|---|---|
| `name` | ✅ |
| `email` | ✅ |
| `nim` | ❌ |
| `nip` | ❌ |
| `phone_number` | ❌ |

---

## 6. Reveal Duration (FINAL — Q6=B)

| Rule | Value |
|---|---|
| TTL | **8 hours** from `viewed_at` timestamp |
| Expiry check | `viewed_at + 8 hours > now()` → 403 Forbidden |
| Multiple views within TTL | ✅ Allowed (each view logged) |
| After TTL | Must submit new break-glass request |

---

## 7. Reason Categories (FINAL)

| Code | Label (ID) | Label (EN) |
|---|---|---|
| `legal_requirement` | Perintah hukum atau pengadilan | Legal or court order |
| `safety_emergency` | Keselamatan darurat | Safety emergency |
| `investigation_necessity` | Kebutuhan investigasi kritis | Critical investigation need |
| `institutional_compliance` | Kepatuhan regulasi institusi | Institutional compliance |
| `victim_consent` | Persetujuan korban/pelapor | Victim/reporter consent |

---

## 8. Audit Requirements (FINAL)

| Action | Category | Severity | Visible To |
|---|---|---|---|
| `break_glass.request` | `privacy` | `critical` | Super Admin only |
| `break_glass.approve` | `privacy` | `critical` | Super Admin only |
| `break_glass.deny` | `privacy` | `critical` | Super Admin only |
| `break_glass.view_identity` | `privacy` | `critical` | Super Admin only |
| `break_glass.emergency_override` | `privacy` | `emergency` | Super Admin only |

All break-glass audit entries are **immutable** and **retained indefinitely**.

Admin with `system.audit_log.view` can see general audit logs but **NOT** entries with `category = "privacy"`.

---

## 9. SLA (FINAL — Q9=C)

| Reason Category | SLA |
|---|---|
| `safety_emergency` | **4 hours** |
| All others | **24 hours** |

SLA is a policy guideline, not automated enforcement in M26.

---

## 10. Retryability (FINAL — Q10=A, Q11=A)

| Rule | Value |
|---|---|
| Max requests per report | **No limit** |
| Retry after denial | **Allowed** with new justification |
| Previous denials visible to approver | ✅ Yes, for context |

---

## 11. Reporter Notification (FINAL — Q4=C)

| Event | Reporter Notified? |
|---|---|
| Break-glass requested | ❌ No |
| Break-glass denied | ❌ No |
| Break-glass approved | ✅ **Yes** — "Identitas Anda pada laporan [reg_number] telah diungkapkan melalui prosedur break-glass" |

Notification does NOT disclose who requested or why.

---

## 12. Emergency Access (FINAL)

| Step | Action |
|---|---|
| 1 | Admin invokes emergency with `safety_emergency` |
| 2 | If Super Admin available → normal flow |
| 3 | If no Super Admin available → emergency override |
| 4 | AuditLog: `break_glass.emergency_override`, `severity: "emergency"` |
| 5 | All Super Admins notified |
| 6 | Mandatory post-incident review within 24 hours |

---

## 13. Forbidden Uses (FINAL)

1. ❌ Routine identity lookup
2. ❌ Satisfying curiosity
3. ❌ Retaliation against reporter
4. ❌ Sharing with unauthorized parties
5. ❌ Revealing to respondent (accused)
6. ❌ Batch/bulk reveals
7. ❌ Circumventing investigation workflow
8. ❌ Pre-emptive "just in case" reveals
9. ❌ Contacting reporter outside system
10. ❌ Storing/screenshotting revealed identity

---

## 14. Database Schema (FINAL)

### `break_glass_requests` Table

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint PK | No | Auto-increment |
| `requestor_id` | bigint FK → users | No | Who requested |
| `approver_id` | bigint FK → users | Yes | Who approved/denied |
| `report_id` | bigint FK → reports | No | Which anonymous report |
| `reason_category` | varchar(50) | No | Enum code |
| `reason` | text | No | Justification (min 50 chars) |
| `status` | varchar(20) | No | pending/approved/denied/viewed/expired |
| `denial_reason` | text | Yes | If denied |
| `requested_at` | timestamp | No | When requested |
| `approved_at` | timestamp | Yes | When approved |
| `denied_at` | timestamp | Yes | When denied |
| `viewed_at` | timestamp | Yes | When identity first accessed |
| `created_at` | timestamp | No | Record creation |

Constraints: No `updated_at`. No `SoftDeletes`. Immutable after status transition.

---

## 15. API Endpoints (FINAL)

| Method | Path | Permission | Description |
|---|---|---|---|
| `POST` | `/v1/break-glass/request` | `privacy.request_break_glass` | Create request |
| `GET` | `/v1/break-glass/pending` | `privacy.approve_break_glass` | List pending (Super Admin) |
| `GET` | `/v1/break-glass/{id}` | `privacy.request_break_glass` | View request detail |
| `PATCH` | `/v1/break-glass/{id}/approve` | `privacy.approve_break_glass` | Approve |
| `PATCH` | `/v1/break-glass/{id}/deny` | `privacy.approve_break_glass` | Deny |
| `GET` | `/v1/break-glass/{id}/reveal` | `privacy.reveal_anonymous_identity` | View identity (8h TTL) |
| `GET` | `/v1/break-glass/history` | `privacy.approve_break_glass` | Break-glass history |
