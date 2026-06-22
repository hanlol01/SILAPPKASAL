# DATA_CLASSIFICATION.md — Milestone 26

**Project:** SILAPPKASAL  
**Milestone:** M26 — Security & Privacy Enhancement  
**Status:** 🔒 FROZEN — All Decisions FINAL  
**Created:** 2026-06-22  
**Frozen:** 2026-06-22  

> [!IMPORTANT]
> This document is frozen. All open questions have been resolved. Decisions are final.
> Do not modify this document. Implementation follows M26_IMPLEMENTATION_PLAN.md.

---

## 1. Classification Scheme (FINAL)

| Level | Code | Label | Description |
|---|---|---|---|
| **L0** | `PUBLIC` | Publik | Reference data. All authenticated users. |
| **L1** | `INTERNAL` | Internal | Operational data. Role-based access. |
| **L2** | `RESTRICTED` | Terbatas | Sensitive case data. Role + permission + ownership. |
| **L3** | `HIGHLY_SENSITIVE` | Sangat Sensitif | Reporter identity (anonymous), encrypted PII, evidence files. Encrypted at rest + audit + break-glass. |

---

## 2. Entity Classification Map (FINAL)

### L3 — HIGHLY_SENSITIVE

| Entity / Field | Protection |
|---|---|
| `reports.reporter_id` | Masked for anonymous reports. Break-glass reveals **name + email only**. |
| `reports.chronology` | `encrypted` cast |
| `reports.incident_location` | `encrypted` cast |
| `reports.respondent_name` | `encrypted` cast |
| `reports.respondent_details` | `encrypted` cast |
| `reports.witness_info` | `encrypted` cast |
| `reports.reporter_phone_encrypted` | `encrypted` cast |
| `users.password` | `hashed` cast |
| Evidence file content | Access control (future: file encryption) |
| `break_glass_requests.*` | Immutable records. **Super Admin audit only.** |
| Break-glass audit entries | `category: "privacy"`, `severity: "critical"`. **Super Admin only.** |

### L2 — RESTRICTED

| Entity / Field | Protection |
|---|---|
| Reporter PII (name, email, nim, nip, phone) | Auth + portal ownership |
| `reports.tracking_code` | Reporter secret |
| `reports.admin_notes`, `reports.rejection_reason` | Auth + admin role |
| Case detail, assignments, investigations | Auth + assignment |
| Recommendations, decisions, recoveries | Auth + assignment/role |
| Evidence metadata, custody chain | Auth + `EvidencePolicy` |
| General audit logs | Auth + `system.audit_log.view` |

### L1 — INTERNAL

| Entity / Field | Protection |
|---|---|
| Report operational fields (reg number, status, type, priority, timestamps) | Auth + policy |
| Case operational fields (case number, status, priority) | Auth + policy |
| User non-PII (role, is_active) | Auth + admin |
| Notifications | Auth + ownership |
| System configuration | Auth + `system.configure` |
| Dashboard aggregates | Auth + dashboard permission |

### L0 — PUBLIC

| Entity / Field | Protection |
|---|---|
| Master data (categories, statuses, types, etc.) | Auth only |
| Health endpoint | None |
