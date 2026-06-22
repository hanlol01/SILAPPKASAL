# PRIVACY_REVIEW.md — Milestone 26

**Project:** SILAPPKASAL  
**Milestone:** M26 — Security & Privacy Enhancement  
**Status:** 🔒 FROZEN — All Decisions FINAL  
**Created:** 2026-06-22  
**Frozen:** 2026-06-22  

> [!IMPORTANT]
> This document is frozen. All open questions have been resolved. Decisions are final.
> Do not modify this document. Implementation follows M26_IMPLEMENTATION_PLAN.md.

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
  → Reveal returns: name + email only (minimal profile)
  → Reveal expires after 8 hours (TTL)
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
| Reveal scope | **Minimal profile only** (name + email) |
| Reveal duration | **8-hour TTL** from first view |
| Reporter notification | **On approval only** — reporter learns identity was revealed |
| Audit visibility | **Super Admin only** — break-glass audit entries restricted |
| Audit entries | Immutable, severity: critical, retained indefinitely |
