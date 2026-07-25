# PRIVACY_REVIEW.md — Milestone 26

**Project:** SILAPPKASAL  
**Milestone:** M26 — Security & Privacy Enhancement  
**Status:** REV-WF-03 R2 Emergency Access override active
**Created:** 2026-06-22  
**Frozen:** 2026-06-22  

> [!IMPORTANT]
> R2 preserves the masking conclusions and supersedes the M26 Break Glass ownership, projection,
> and TTL statements below.

---

## 1. Privacy Framework

### 1.1 Governing Principles

| Principle | Description |
|---|---|
| **Kerahasiaan** | Reporter identity and case details protected from unauthorized access |
| **Victim-Centered** | All decisions prioritize reporter/victim safety and autonomy |
| **Need-to-Know** | Data access restricted to minimum required per role |
| **Auditability** | All sensitive data access logged and reviewable |
| **Data Minimization** | Collect only necessary data; display only authorized data |

---

## 2. Reporter Identity Privacy (FINAL)

### 2.1 Report Types and Identity Exposure

| Report Type | `reporter_id` Stored | Visible to Admin | Visible to Satgas | Visible to Reporter |
|---|---|---|---|---|
| **Terbuka (Open)** | ✅ Always | ✅ Yes | ✅ Yes (assigned) | ✅ Yes (own) |
| **Rahasia (Confidential)** | ✅ Always | ✅ Yes | ✅ Yes (assigned) | ✅ Yes (own) |
| **Anonim (Anonymous)** | ✅ Always | ❌ Masked | ❌ Masked | ✅ Yes (own) |

### 2.2 Privacy Gaps and Remediation (FINAL)

| Gap | Remediation | Status |
|---|---|---|
| Anonymous reports store `reporter_id = null` | Always store `reporter_id = auth()->id()` | FINAL |
| `ReportMetadataResource` does not mask reporter | Return `reporter: { masked: true }` for anonymous reports | FINAL |
| `ownedReportsQuery` excludes anonymous reports | Remove `report_type != anonymous` filter | FINAL |
| No API-level identity masking | Implement in `ReportMetadataResource` | FINAL |
| Legacy anonymous reports (`reporter_id = null`) | Leave as-is. Cannot backfill. | FINAL |

---

## 3. Anonymous Report Handling (FINAL)

### 3.1 Target Flow (After M26)

```
Reporter (MUST be authenticated)
  → POST /v1/reports { report_type: "anonymous" }
    → auth:sanctum middleware enforces authentication
    → reporter_id = auth()->id() (ALWAYS stored)
    → tracking_code = generated (kept for backward compat)
  → Reporter sees report in portal ("My Reports")
  → Reporter receives notifications
  → Admin sees: report_type "anonymous", reporter: { masked: true }
  → Break-glass required to reveal identity
  → Assigned Satgas requests; same-campus Admin reviews without reveal authority
  → Requester-only reveal returns the allowlisted minimal identity projection
  → New grant starts on approval for 30/60/240/1440 minutes
```

### 3.2 API Masking Contract (FINAL)

For anonymous reports:
```json
{
  "reporter": { "masked": true },
  "is_anonymous": true
}
```

For non-anonymous reports:
```json
{
  "reporter": { "id": 5, "name": "Ahmad" },
  "is_anonymous": false
}
```

---

## 4. Evidence Privacy (FINAL — No Changes in M26)

| Aspect | Status |
|---|---|
| Access control via `EvidencePolicy` | ✅ No changes |
| Reporter `evidence.*` permissions | ✅ Retained for future use |
| File-level encryption | Post-M26 consideration |

---

## 5. Break-Glass Privacy Impact (FINAL)

| Aspect | Decision |
|---|---|
| Reveal scope | `name`, `nim`, `email`, `phone_number`, faculty, study program, university where present |
| Reveal duration | **30/60/240/1440 minutes from approval**; legacy rows retain bounded 8-hour compatibility |
| Reporter notification | **On approval only**, generic and without requester/reviewer/reason data |
| Audit visibility | **Super Admin only** — break-glass audit entries restricted |
| Audit entries | Immutable, severity: critical, retained indefinitely |

---

## 6. REV-FINAL-INTEGRATION-01 integrated workflow boundary — 2026-07-25

- Reporter projections remain limited to reporter-safe status and ownership data. They exclude
  withdrawal review reasons, signed-document storage details, internal assignees/reviewers, decision
  records/numbers, and Case Berita Acara narrative.
- Signed withdrawal documents are served only through authorized private routes; resources omit disk,
  path, checksum, raw filename, and storage URL. Super Admin monitoring is metadata-only.
- Case Berita Acara narrative is internal: assigned active Satgas and authorized same-campus Admin
  receive the anonymized internal projection, while Super Admin receives metadata only and Reporter
  has no BA route or resource projection.
- Common Case mutability checks run before integrated Case mutations, and stale-write/withdrawn
  guards prevent later workflow transitions from exposing or altering terminal records.
- The integration review was static plus automated test evidence. Browser UAT and target PostgreSQL
  runtime validation remain required before deployment; neither is claimed completed here.
