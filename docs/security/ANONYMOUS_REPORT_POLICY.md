# ANONYMOUS_REPORT_POLICY.md — Milestone 26

**Project:** SILAPPKASAL  
**Milestone:** M26 — Security & Privacy Enhancement  
**Status:** 🔒 FROZEN — All Decisions FINAL  
**Created:** 2026-06-22  
**Frozen:** 2026-06-22  

> [!IMPORTANT]
> This document is frozen. All open questions have been resolved. Decisions are final.
> Do not modify this document. Implementation follows M26_IMPLEMENTATION_PLAN.md.

---

## 1. Core Principle (FINAL)

> **Anonymous reporting requires authentication.**  
> Anonymous ≠ unauthenticated.  
> Anonymous means the reporter's identity is stored internally but masked from all admin and Satgas views by default.

---

## 2. Submission Flow (FINAL)

```
1. Reporter MUST be authenticated (auth:sanctum on POST /reports)
2. POST /v1/reports { report_type: "anonymous" }
3. ReportService::submit()
   → reporter_id = auth()->id()     (ALWAYS — null branch removed)
   → tracking_code = generated       (kept for backward compat)
4. Reporter sees report in portal ("My Reports")
5. Reporter receives notifications on status changes
6. Admin/Satgas see:
   → report_type: "anonymous"
   → reporter: { masked: true }
7. Break-glass required to reveal identity
```

---

## 3. Identity Masking Rules (FINAL)

| Field | Stored? | Reporter Sees? | Admin Sees? | Satgas Sees? |
|---|---|---|---|---|
| `reporter_id` | ✅ Always | N/A | ❌ Masked | ❌ Masked |
| Reporter `name` | ✅ | ✅ Own profile | ❌ Masked | ❌ Masked |
| Reporter `email` | ✅ | ✅ Own profile | ❌ Masked | ❌ Masked |
| Reporter `nim`/`nip` | ✅ | ✅ Own profile | ❌ Masked | ❌ Masked |
| Reporter `phone` | ✅ | ✅ Own profile | ❌ Masked | ❌ Masked |
| `report_type = "anonymous"` | ✅ | ✅ | ✅ Visible | ✅ Visible |
| `tracking_code` | ✅ | ✅ To reporter only | ❌ Hidden | ❌ Hidden |
| Report content | ✅ Encrypted | ✅ Own report | ✅ Visible | ✅ Visible (assigned) |

---

## 4. API Masking Contract (FINAL)

For anonymous reports in `ReportMetadataResource`:

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

## 5. Portal Query Change (FINAL)

```php
// BEFORE:
Report::query()
    ->where('reporter_id', $user->id)
    ->whereNotNull('reporter_id')
    ->where('report_type', '!=', 'anonymous');    // ← REMOVE this

// AFTER:
Report::query()
    ->where('reporter_id', $user->id)
    ->whereNotNull('reporter_id');
    // Anonymous reports now visible to their own reporter
```

---

## 6. Tracking and Communication (FINAL)

| Method | Status |
|---|---|
| Portal "My Reports" | ✅ Primary tracking method |
| Tracking code URL | ✅ Kept as fallback (backward compat) |
| Status notifications | ✅ Delivered to reporter's account |
| Direct contact by admin | ❌ Not possible (identity masked) |
| System notification to reporter | ✅ Via notification system |

---

## 7. Legacy Data (FINAL — Q1=A)

| Decision | Detail |
|---|---|
| Existing `reporter_id = null` reports | **Leave as-is.** Cannot backfill. |
| Legacy portal visibility | These reports will NOT appear in any portal (no `reporter_id` to match) |
| Tracking code access | Continues to work unchanged |
| No migration required | ✅ Confirmed |

---

## 8. Break-Glass Reveal Scope (FINAL — Q7=B)

When identity is revealed via break-glass, the API returns **minimal profile only**:

| Field | Revealed? |
|---|---|
| `name` | ✅ Yes |
| `email` | ✅ Yes |
| `nim` | ❌ No |
| `nip` | ❌ No |
| `phone_number` | ❌ No |

Reveal expires after **8 hours** (TTL from first view).
