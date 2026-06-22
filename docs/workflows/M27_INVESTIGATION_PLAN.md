# Milestone 27: Investigation Workflow Completion

## Status: ✅ Planning Complete — Awaiting Approval

---

## Overview

Activate the full investigation lifecycle in the frontend and close backend gaps (audit logging, notifications, status-options API). All investigation data models, statuses, transitions, and core service logic already exist from M7.

---

## Frozen Decisions (ALL FINAL)

| Q# | Question | Decision | Rationale |
|---|---|---|---|
| Q1 | Status-options endpoint scope | **Investigation only** | Recommendation, Decision, and Recovery status-options belong to future milestones. |
| Q2 | Notification audience for status changes | **All assigned Satgas on the case** | Admin/SA are NOT notified for status changes. |
| Q3 | Lead Investigator picker source | **Active Satgas assigned to the case only** | Frontend picker filters to case assignment IDs. Backend `ensureAssignedSatgas()` validates independently. |
| Q4 | Investigation creation authority | **Assigned Satgas with `cases.investigate` only** | Admin and Super Admin cannot create investigations. No backend policy change. |
| Q5 | Localization | **Follow existing admin dashboard pattern** | English-only for admin views. No localization expansion in M27. |
| Q6 | Plan Summary requirement | **Required, minimum 50 characters** | Validated by backend `StoreInvestigationRequest`. Frontend enforces matching constraint. |

---

## Implementation Phases

| Phase | Name | Scope | Type |
|---|---|---|---|
| M27-A | Backend: Status Options + Audit + Notifications | 1 new endpoint, audit dispatch, notification dispatch | Backend |
| M27-B | Frontend: Investigation Creation + Status Transition | 2 form components replacing disabled blockers | Frontend |

---

## Phase M27-A: Backend

### New Endpoint

| Method | Path | Auth | Description |
|---|---|---|---|
| `GET` | `/api/v1/investigations/{investigation}/status-options` | `auth:sanctum` | Returns current status code/name + list of valid next statuses with code/name |

**Response shape:**
```json
{
  "success": true,
  "data": {
    "current_status": { "code": "INVS-01", "name": "planning" },
    "valid_transitions": [
      { "code": "INVS-02", "name": "evidence_collection" },
      { "code": "INVS-03", "name": "victim_interview" }
    ]
  }
}
```

**Access:** Same policy as `view` — any user who can view the investigation can see its status options.

### Audit Log Dispatch

Add `AuditLogService::log()` calls to `InvestigationService`:

| Method | Audit Action | Category |
|---|---|---|
| `createForCase()` | `AuditAction::InvestigationCreated` | `AuditCategory::Investigation` |
| `addActivity()` | `AuditAction::InvestigationActivityCreated` | `AuditCategory::Investigation` |
| `updateStatus()` | `AuditAction::InvestigationStatusChanged` | `AuditCategory::Investigation` |

All 3 enum values already exist in [AuditAction.php:29-31](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Enums/AuditAction.php#L29-L31). Only dispatch calls are missing.

### Notifications

| Event | Recipients | Type Code | Payload |
|---|---|---|---|
| Investigation created | Admin + Super Admin | `investigation_created` | `case_number`, `investigation_id`, `lead_investigator_name` |
| Investigation completed | Admin + Super Admin | `investigation_completed` | `case_number`, `investigation_id` |
| Investigation status changed | **All assigned Satgas on the case** | `investigation_status_changed` | `case_number`, `investigation_id`, `from_status`, `to_status` |

Implementation: inline `WorkflowDatabaseNotification` in `InvestigationService` (same pattern as M26-C `BreakGlassService`).

### Plan Summary Validation

Update `StoreInvestigationRequest`:
- `plan_summary` → `required|string|min:50|max:5000`

Currently `plan_summary` is nullable in the model. The form request must enforce `required` + `min:50`.

### Files to Modify (Backend)

| # | File | Change |
|---|---|---|
| 1 | `app/Services/InvestigationService.php` | Add audit log dispatch + notification dispatch |
| 2 | `app/Http/Controllers/Api/V1/InvestigationController.php` | Add `statusOptions()` method |
| 3 | `app/Http/Requests/StoreInvestigationRequest.php` | Make `plan_summary` required, min:50 |
| 4 | `routes/api.php` | Add `GET investigations/{investigation}/status-options` route |

### Files to Create (Backend)

None.

---

## Phase M27-B: Frontend

### Investigation Creation Form

**Replaces:** `DisabledWorkflowAction` at [dashboard.cases.$id.tsx:231-234](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.cases.$id.tsx#L231-L234)

**Form fields:**
- Lead Investigator — select picker from assigned Satgas on case (`case.assignments` where `is_active`)
- Plan Summary — textarea, required, min 50 chars, max 5000

**Visibility:** Only when:
- `roleCode === 'satgas_ppks'`
- User is actively assigned to the case
- Case status is `investigation`
- Case has no existing investigation

**API:** `POST /api/v1/cases/{case}/investigations`

### Investigation Status Transition

**Replaces:** `DisabledWorkflowAction` at [dashboard.cases.$id.tsx:322-325](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.cases.$id.tsx#L322-L325)

**UI:** Select dropdown populated from `GET /api/v1/investigations/{investigation}/status-options`. Shows valid transitions only. Submit calls `PATCH /api/v1/investigations/{investigation}/status`.

**Visibility:** Only when:
- `roleCode === 'satgas_ppks'`
- User is actively assigned to the case
- Investigation status is NOT `completed`

### Files to Create (Frontend)

| # | File | Description |
|---|---|---|
| 1 | `frontend/src/components/workflow-actions/investigation-create-action.tsx` | Investigation creation dialog with lead picker + plan summary |
| 2 | `frontend/src/components/workflow-actions/investigation-status-action.tsx` | Investigation status transition dialog with valid-transition selector |

### Files to Modify (Frontend)

| # | File | Change |
|---|---|---|
| 1 | `frontend/src/routes/dashboard.cases.$id.tsx` | Replace 2 `DisabledWorkflowAction` blocks with new components |
| 2 | `frontend/src/lib/operations-api.ts` | Add `createInvestigation()`, `getInvestigationStatusOptions()` API functions |
| 3 | `frontend/src/lib/operations-types.ts` | Add `InvestigationStatusOption` type |

---

## Acceptance Criteria

| # | Criterion |
|---|---|
| 1 | Assigned Satgas can create investigation from case detail page |
| 2 | Lead investigator picker shows only active Satgas assigned to the case |
| 3 | Plan summary is required (min 50 chars) |
| 4 | Investigation creation calls `POST /api/v1/cases/{case}/investigations` |
| 5 | Case must be in `investigation` status to create investigation |
| 6 | Case with existing investigation blocks duplicate creation (backend enforced) |
| 7 | Assigned Satgas can transition investigation status via the UI |
| 8 | Status selector shows only valid transitions from current status |
| 9 | `completed` is terminal — no further transitions shown |
| 10 | Admin/SA cannot see create or status-transition actions |
| 11 | Audit logs dispatched for create, activity, and status change |
| 12 | Notifications dispatched on investigation create and complete (to Admin/SA) |
| 13 | Status change notifications sent to all assigned Satgas on the case |
| 14 | `php artisan test` passes |
| 15 | `npm run build` passes |
| 16 | No localization expansion beyond existing admin pattern |

---

## Risks

| Risk | Mitigation |
|---|---|
| Lead investigator picker shows wrong users | Picker uses case `assignments` array filtered to `is_active`. Backend `ensureAssignedSatgas()` validates independently. |
| Status transition allows invalid jumps | Backend `InvestigationService::updateStatus()` validates against `valid_transitions` from master data. |
| Investigation created on wrong case status | Backend `ensureCaseCanStartInvestigation()` checks `case.status === 'investigation'`. |
| Duplicate investigation on same case | Backend `ensureCaseCanStartInvestigation()` checks `case.investigation()->exists()`. |
| Audit log missing after refactor | Test should assert audit log count after create/status/activity operations. |
| Frontend optimistic update drift | Continue existing pattern: no optimistic updates, invalidate queries after mutation success. |
| Plan summary validation mismatch | Both frontend (min 50) and backend (`required|min:50`) enforce the same constraint. |

---

## Out of Scope

| Item | Note |
|---|---|
| Recommendation/Decision/Recovery status-options API | Deferred to future milestones per Q1 |
| Admin/SA investigation creation | Blocked per Q4 |
| Localization of investigation labels | Deferred per Q5 |
| Investigation findings/conclusion edit form | Existing M7 model supports these fields but no edit UI is planned for M27 |
| Evidence upload/download | Remains deferred |
| Mediation workflow activation | Separate milestone |

---

## Verification Plan

### Automated Tests
```bash
# Backend
cd backend/api
php artisan test

# Frontend
cd frontend
npm run build
```

### Manual Verification
- Assigned Satgas creates investigation on case in `investigation` status
- Picker shows only assigned Satgas
- Plan summary validation works (reject < 50 chars)
- Status transitions work through the full chain to `completed`
- Admin/SA cannot see create or status actions
- Audit logs appear for all 3 actions
- Notifications arrive for correct recipients
