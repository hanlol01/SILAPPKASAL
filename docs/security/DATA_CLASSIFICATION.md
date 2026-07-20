# DATA_CLASSIFICATION.md — Milestone 26

**Project:** SILAPPKASAL  
**Milestone:** M26 — Security & Privacy Enhancement  
**Status:** REV-WF-03 R2 Emergency Access override active
**Created:** 2026-06-22  
**Frozen:** 2026-06-22  

> [!IMPORTANT]
> Classification remains unchanged. R2 controls below supersede conflicting M26 Break Glass
> ownership and projection statements.

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
| `reports.reporter_id` | Masked for anonymous reports. Dedicated requester-only reveal returns the allowlisted identity projection. |
| `reports.chronology` | `encrypted` cast |
| `reports.incident_location` | `encrypted` cast |
| `reports.respondent_name` | `encrypted` cast |
| `reports.respondent_details` | `encrypted` cast |
| `reports.witness_info` | `encrypted` cast |
| `reports.reporter_phone_encrypted` | `encrypted` cast |
| `users.password` | `hashed` cast |
| Evidence file content | Access control (future: file encryption) |
| `break_glass_requests.*` | Same-campus Admin review; requester-only Satgas reveal; Super Admin sees redacted audit oversight only. |
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

---

## REV-WF-03 R3 Finalization Data

| Entity / Field | Classification | Protection |
|---|---|---|
| `case_final_summaries` draft narratives | L2 RESTRICTED | Authenticated policy access; encrypted at rest; same-campus Admin mutation only |
| Published final-summary Reporter projection | L2 RESTRICTED | Owned Report only; explicit allowlist; no internal IDs or staff identity |
| `recoveries.discontinuation_reason` | L2 RESTRICTED | Encrypted at rest; internal authorized projection only; never Reporter-visible |
| Anonymous Reporter identity in final summary | L3 HIGHLY_SENSITIVE | Publication and closure reject detected identity; existing break-glass boundary remains unchanged |
| Finalization audit metadata | L1 INTERNAL | Allowlisted codes/references only; no narratives, identity, Evidence content, or filenames |

Published content is Reporter-safe by design, but it is not public: Reporter ownership and authentication remain required.
