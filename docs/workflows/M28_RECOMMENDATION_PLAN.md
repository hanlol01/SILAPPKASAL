# Milestone 28 — Recommendation Workflow

> Status: 🔒 FROZEN — All Decisions FINAL  
> Date: 2026-06-23  
> Frozen: 2026-06-23  
> Scope: Recommendation workflow activation (backend + frontend)  
> Depends on: M8 (Recommendation Foundation), M27 (Investigation Workflow pattern)

> [!IMPORTANT]
> This document is frozen. All open questions have been resolved. Decisions are final.
> Do not modify this document. Implementation follows this plan exactly.

---

## 1. Phase Name

**Recommendation Workflow Activation**

---

## 2. Objectives

1. Expose a `status-options` endpoint for recommendations so the frontend can drive status transitions dynamically (mirroring the M27 investigation pattern).
2. Add a **Recommendation Create** action in the frontend, allowing assigned Satgas to create a recommendation for a case in `recommendation` status with a completed investigation.
3. Replace the `DisabledWorkflowAction` blocker for recommendation status updates with a working **Recommendation Status Transition** action in the frontend.
4. Integrate audit logging into `RecommendationService` for `recommendation.created`, `recommendation.updated`, and `recommendation.status_changed`.
5. Add targeted notification dispatch for recommendation lifecycle events.
6. Maintain strict RBAC: only assigned Satgas with `cases.recommend` may create/update recommendations; Admin/Super Admin remain metadata-read-only.

---

## 3. Business Workflow

```
┌─────────────────────────────────────────────────────────────────────┐
│                 RECOMMENDATION LIFECYCLE                            │
│                                                                     │
│  Precondition:                                                      │
│    Case status = "recommendation"                                   │
│    Investigation status = "completed"                               │
│    No existing recommendation on the case                           │
│                                                                     │
│  1. Assigned Satgas creates recommendation                          │
│     → status = "drafting"                                           │
│     → audit: recommendation.created                                 │
│     → notify: Admin + Super Admin (recommendation created)          │
│                                                                     │
│  2. Satgas edits recommendation content                             │
│     → allowed in: drafting, revised                                 │
│     → audit: recommendation.updated                                 │
│                                                                     │
│  3. Satgas transitions status                                       │
│     drafting → internal_review | submitted_to_leader                │
│     internal_review → submitted_to_leader | revised                 │
│     revised → internal_review | submitted_to_leader                 │
│                                                                     │
│     → audit: recommendation.status_changed                          │
│     → notify on submitted_to_leader: Admin + Super Admin            │
│     → notify on other transitions: all assigned Satgas              │
│                                                                     │
│  4. submitted_to_leader = TERMINAL for Satgas                       │
│     (Decision workflow in M29 will unlock accepted/rejected/etc)    │
│                                                                     │
│  5. accepted, partially_accepted, rejected = DECISION-ONLY          │
│     (Set by Decision workflow — NOT by Satgas recommendation)       │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 4. Actors Involved

| Actor | Role in M28 |
|---|---|
| **Satgas PPKS** (assigned, active) | Create recommendation, edit content, transition status (drafting → internal_review → submitted_to_leader) |
| **Admin** | Receive notification when recommendation is submitted to leader. Read-only metadata access. |
| **Super Admin** | Same as Admin. |
| **Reporter** | No access to recommendations. |

---

## 5. State/Status Transitions

### Master Data (from [MasterDataSeeder.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/database/seeders/MasterDataSeeder.php))

| Code | Name | Valid Transitions | Terminal? |
|---|---|---|---|
| `RECS-01` | `drafting` | `internal_review`, `submitted_to_leader` | No |
| `RECS-02` | `internal_review` | `submitted_to_leader`, `revised` | No |
| `RECS-03` | `submitted_to_leader` | `[]` | Yes (for Satgas) |
| `RECS-04` | `accepted` | `[]` | Yes (decision-only) |
| `RECS-05` | `partially_accepted` | `[]` | Yes (decision-only) |
| `RECS-06` | `rejected` | `[]` | Yes (decision-only) |
| `RECS-07` | `revised` | `internal_review`, `submitted_to_leader` | No |

### Transition Diagram

```
drafting ──→ internal_review ──→ submitted_to_leader (TERMINAL)
   │              │                       ↑
   │              └──→ revised ───────────┘
   │                     ↑                │
   └─────────────────────┼────────────────┘
                         │
                  internal_review
```

### Decision-Only Statuses (Out of Scope for M28)

`accepted`, `partially_accepted`, `rejected` are set exclusively by the Decision workflow (M29). The existing `decisionOnlyValues()` guard in [RecommendationService](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Services/RecommendationService.php#L114-L116) blocks Satgas from setting these.

---

## 6. Backend Scope

### M28-A: Backend

#### 6.1 Add `statusOptions` endpoint to `RecommendationController`

Mirror the M27 `InvestigationController.statusOptions()` pattern:

- New method: `statusOptions(Request $request, Recommendation $recommendation)`
- Gate: `Gate::authorize('view', $recommendation)`
- Returns `current_status` + `valid_transitions` from master data
- Filter out `decisionOnlyValues()` from `valid_transitions` (Satgas cannot see/select decision-reserved statuses)

#### 6.2 Add audit logging to `RecommendationService`

Currently `RecommendationService` has **no audit logging**. Add calls to `AuditLogService`:

| Method | Audit Action | When |
|---|---|---|
| `createForCase()` | `recommendation.created` | After recommendation created |
| `update()` | `recommendation.updated` | After recommendation content edited |
| `updateStatus()` | `recommendation.status_changed` | After status transition |

Redaction: Use existing `AuditLogService` redaction for recommendation narrative fields (`conclusion`, `recommended_actions`, `sanction_recommendation`, `recovery_recommendation`, `prevention_recommendation`).

#### 6.3 Add notification dispatch to `RecommendationService`

| Event | Recipients | When |
|---|---|---|
| `recommendation.created` | Admin + Super Admin | After recommendation created |
| `recommendation.status_changed` | All assigned Satgas on the case | After any status transition (except `submitted_to_leader`) |
| `recommendation_submitted_to_leader` | Admin + Super Admin | Already implemented in `NotificationService` — no change needed |

#### 6.4 Register route

```
GET /api/v1/recommendations/{recommendation}/status-options
```

Policy: `view` (same as show endpoint).

#### 6.5 Remove M8 blocker message

In [RecommendationService.php line 200](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Services/RecommendationService.php#L197-L202), `ensureRecommendationOpen()` currently blocks `submitted_to_leader` with message "Submitted recommendations cannot transition in Milestone 8". This guard is correct behavior but the message should be updated to reflect it as a permanent terminal state (for Satgas), not an M8 limitation.

---

## 7. Frontend Scope

### M28-B: Frontend

#### 7.1 New component: `recommendation-create-action.tsx`

- Dialog to create a recommendation for a case
- Visible only to assigned Satgas with `cases.recommend` permission
- Gate: case status === `recommendation` AND no existing recommendation AND has completed investigation
- Form fields:
  - `investigation_id` — auto-selected (the completed investigation)
  - `conclusion` — required textarea
  - `recommended_actions` — required textarea
  - `sanction_recommendation` — optional textarea
  - `recovery_recommendation` — optional textarea
  - `prevention_recommendation` — optional textarea
- Uses `POST /api/v1/cases/{case}/recommendations`
- React Query invalidation: case, recommendations, dashboard, my-work

#### 7.2 New component: `recommendation-status-action.tsx`

- Replace `DisabledWorkflowAction` in `RecommendationsSection`
- Fetch valid transitions from `GET /api/v1/recommendations/{recommendation}/status-options`
- Dropdown selector for next status
- Uses `PATCH /api/v1/recommendations/{recommendation}/status`
- Disabled when no valid transitions (e.g., `submitted_to_leader`)
- React Query invalidation: case, recommendations, status-options, dashboard, my-work

#### 7.3 Update `operations-api.ts`

- Add `getRecommendationStatusOptions(id)` function
- Add `recommendationStatusOptions` query key to `operationsQueryKeys`

#### 7.4 Update `operations-types.ts`

- Add `RecommendationStatusOptions` type
- Add `RecommendationStatusOption` type
- Add `RecommendationCreatePayload` type (with all form fields)

#### 7.5 Update `dashboard.cases.$id.tsx`

- Add `canRecommend` permission check (mirrors `canInvestigate` pattern)
- Add `canCreateRecommendation` guard (case status + no existing recommendation + has completed investigation)
- Wire `RecommendationCreateAction` into case detail
- Replace `DisabledWorkflowAction` with `RecommendationStatusAction`

---

## 8. Database Changes

**None.** No new migrations. All tables already exist from M8 (Recommendation Foundation).

---

## 9. API Endpoints

### New Endpoint

| Method | Path | Description | Auth |
|---|---|---|---|
| `GET` | `/api/v1/recommendations/{recommendation}/status-options` | Returns current status + valid transitions (excluding decision-only statuses) | `view` policy |

### Existing Endpoints (unchanged)

| Method | Path | Description |
|---|---|---|
| `POST` | `/api/v1/cases/{case}/recommendations` | Create recommendation |
| `GET` | `/api/v1/cases/{case}/recommendations` | List recommendations for case |
| `GET` | `/api/v1/recommendations/{recommendation}` | Show recommendation detail |
| `PATCH` | `/api/v1/recommendations/{recommendation}` | Update recommendation content |
| `PATCH` | `/api/v1/recommendations/{recommendation}/status` | Update recommendation status |

---

## 10. Permissions Required

| Permission | Role | Usage |
|---|---|---|
| `cases.recommend` | Satgas PPKS | Create recommendation, update content, transition status |
| `cases.read.metadata` | Admin, Super Admin | View recommendation metadata |
| `cases.read.all` | Super Admin | View recommendation metadata |
| `cases.record_decision` | Admin, Super Admin | Receive `submitted_to_leader` notification (existing) |

No new permissions required for M28. All permissions already exist.

---

## 11. Notifications

### New Notifications (M28)

| Event | Recipients | Payload |
|---|---|---|
| `recommendation_created` | Admin + Super Admin | `notification_type_code`, `event`, `title`, `body`, `subject_type: recommendation`, `case_id`, `recommendation_id`, `status_code` |
| `recommendation_status_changed` | All assigned Satgas on the case | Same payload structure |

### Existing Notification (Unchanged)

| Event | Recipients | Payload |
|---|---|---|
| `recommendation_submitted_to_leader` | Admin + Super Admin with `cases.record_decision` | Already implemented in [NotificationService](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Services/NotificationService.php#L62-L81) |

### Notification Rules

- No sensitive recommendation narratives in notification payload
- Only metadata: `case_id`, `recommendation_id`, `status_code`
- Notification type codes must be registered in `notification_types` master data

---

## 12. Audit Requirements

### Audit Actions (Already defined in [AuditAction.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Enums/AuditAction.php#L33-L35))

| Action | When | Metadata |
|---|---|---|
| `recommendation.created` | After recommendation created | `recommendation_id`, `case_id`, `investigation_id`, `status_code` |
| `recommendation.updated` | After content edited | `recommendation_id`, field-level deltas (redacted narratives) |
| `recommendation.status_changed` | After status transition | `recommendation_id`, `from_status`, `to_status` |

### Redaction Rules

- `conclusion`, `recommended_actions`, `sanction_recommendation`, `recovery_recommendation`, `prevention_recommendation` must be redacted using existing `AuditLogService` redaction patterns
- Only safe deltas (field names, status codes, IDs) stored in audit metadata

---

## 13. Acceptance Criteria

### Backend (M28-A)

| # | Criterion |
|---|---|
| 1 | `GET /recommendations/{recommendation}/status-options` returns `current_status` + `valid_transitions` |
| 2 | `status-options` excludes `accepted`, `partially_accepted`, `rejected` from `valid_transitions` |
| 3 | `status-options` returns empty `valid_transitions` for `submitted_to_leader` |
| 4 | Audit log dispatched for `recommendation.created` |
| 5 | Audit log dispatched for `recommendation.updated` |
| 6 | Audit log dispatched for `recommendation.status_changed` |
| 7 | Notification dispatched to Admin + Super Admin on recommendation creation |
| 8 | Notification dispatched to assigned Satgas on non-terminal status change |
| 9 | `submitted_to_leader` notification unchanged (existing) |
| 10 | No sensitive narratives leaked in notifications or audit logs |
| 11 | No new migrations |
| 12 | No new permissions |
| 13 | Existing tests still pass |

### Frontend (M28-B)

| # | Criterion |
|---|---|
| 1 | Create recommendation action visible only to assigned Satgas with `cases.recommend` |
| 2 | Admin and Super Admin remain read-only |
| 3 | Reporter gains no recommendation access |
| 4 | Create recommendation requires case in `recommendation` status |
| 5 | Create recommendation requires completed investigation |
| 6 | Create recommendation blocked if case already has a recommendation |
| 7 | `conclusion` and `recommended_actions` required in create form |
| 8 | Status transition uses `status-options` endpoint |
| 9 | `submitted_to_leader` recommendations cannot transition further |
| 10 | `DisabledWorkflowAction` for recommendation status is removed |
| 11 | No decision workflow introduced |
| 12 | No recovery workflow introduced |
| 13 | No backend files modified |
| 14 | Build passes (`npm run build`) |

---

## 14. Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Audit logging adds latency to recommendation operations | Low | Use existing `AuditLogService` pattern; synchronous is acceptable for now |
| `decisionOnlyValues()` filter must stay in sync with master data | Medium | Filter uses enum values, not hardcoded strings; enum is source of truth |
| Frontend auto-selecting `investigation_id` may fail if multiple investigations exist | Low | **RESOLVED (Q1):** Always auto-select most recent completed investigation. No picker. |
| `submitted_to_leader` M8 blocker message is misleading | Low | Update message text in M28-A |
| Notification type codes for new events may not exist in master data | Medium | Check seeder; add if missing |
| Concurrent recommendation creation could violate one-per-case constraint | Low | Existing `DB::transaction` + `lockForUpdate` + `ensureCaseCanReceiveRecommendation()` handles this |

---

## 15. Frozen Decisions

> [!IMPORTANT]
> All decisions below are FINAL. Do not revisit during implementation.

| # | Question | Decision | Rationale |
|---|---|---|---|
| Q1 | Recommendation create — investigation picker | **A: Auto-select most recent completed investigation.** Do not introduce an investigation picker. | Simplest approach. Edge case of multiple completed investigations is rare. Backend `investigation_id` is sent automatically. |
| Q2 | Recommendation status change notifications — scope | **A: Notify all assigned Satgas on the case.** Consistent with M27 Investigation Workflow pattern. | Maintains consistency across milestones. All assigned Satgas should be aware of recommendation progress. |
| Q3 | Recommendation creation notifications — recipients | **A: Notify Admin + Super Admin only.** Assigned Satgas do not require a creation notification. | Admin/SA need awareness for upcoming decision. The creating Satgas already knows. Other Satgas learn via status change notifications. |
| Q4 | Status-options endpoint access | **A: Follow M27 pattern. Use existing `view` policy.** Admin/SA see current status with empty transitions. Satgas see real transitions. | Consistency with M27. No new policy needed. |
| Q5 | Notification type codes | **Continue existing numbering convention.** `NOTIF-16` for `recommendation_created`, `NOTIF-17` for `recommendation_status_changed`. | Consistent with `NOTIF-12` through `NOTIF-15` already in NotificationService. Register in master data seeder. |

### Implementation Rules Derived from Frozen Decisions

1. **Investigation selection (Q1):** Frontend queries completed investigations for the case, sorts by `completed_at DESC`, and auto-selects the first. No picker UI.
2. **Status change notifications (Q2):** `recommendationStatusChanged()` in NotificationService sends to `activeAssignedSatgas(case)`. Exception: `submitted_to_leader` uses existing `recommendationSubmittedToLeader()` which notifies Admin/SA.
3. **Creation notification (Q3):** `recommendationCreated()` in NotificationService sends to Admin + Super Admin using the `decisionManagers()` helper (same pattern as `recommendationSubmittedToLeader`).
4. **Status-options access (Q4):** `Gate::authorize('view', $recommendation)` on the new endpoint. Returns `valid_transitions` filtered by `decisionOnlyValues()` exclusion.
5. **Type codes (Q5):** Add `NOTIF-16` and `NOTIF-17` to `notification_types` master data seeder. Use constants `TYPE_RECOMMENDATION_CREATED = 'NOTIF-16'` and `TYPE_RECOMMENDATION_STATUS_CHANGED = 'NOTIF-17'` in NotificationService.

---

## 16. Files Expected to be Modified

### Backend (M28-A)

| File | Change |
|---|---|
| [RecommendationController.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Http/Controllers/Api/V1/RecommendationController.php) | Add `statusOptions()` method |
| [RecommendationService.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Services/RecommendationService.php) | Add audit logging + notification dispatch + update M8 blocker message |
| [NotificationService.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Services/NotificationService.php) | Add `recommendationCreated()` + `recommendationStatusChanged()` methods + `NOTIF-16`/`NOTIF-17` constants |
| [api.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/routes/api.php) | Register `status-options` route |
| [MasterDataSeeder.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/database/seeders/MasterDataSeeder.php) | Add `NOTIF-16` and `NOTIF-17` notification type seeds |

### Frontend (M28-B)

| File | Change |
|---|---|
| `frontend/src/components/workflow-actions/recommendation-create-action.tsx` | **NEW** — Create recommendation dialog |
| `frontend/src/components/workflow-actions/recommendation-status-action.tsx` | **NEW** — Status transition dialog |
| [operations-api.ts](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/lib/operations-api.ts) | Add `getRecommendationStatusOptions()`, `createRecommendation()`, query key |
| [operations-types.ts](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/lib/operations-types.ts) | Add `RecommendationStatusOptions`, `RecommendationCreatePayload` types |
| [dashboard.cases.$id.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.cases.$id.tsx) | Add `canRecommend`, wire create/status actions, remove DisabledWorkflowAction |

### Test File

| File | Change |
|---|---|
| `backend/api/tests/Feature/RecommendationWorkflowTest.php` | **NEW or extend existing** — Test status-options, audit, notifications |
