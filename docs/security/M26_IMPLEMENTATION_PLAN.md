# M26_IMPLEMENTATION_PLAN.md

**Project:** SILAPPKASAL  
**Milestone:** M26 — Security & Privacy Enhancement  
**Status:** APPROVED — Ready for Build  
**Created:** 2026-06-22  
**Product Owner Decisions:** ALL FINAL  

> [!IMPORTANT]
> All 12 open questions have been resolved. This is the authoritative implementation roadmap.
> Policy documents in `docs/security/` are frozen. Do not modify them.

---

## Implementation Phases

| Phase | Name | Scope | Dependencies |
|---|---|---|---|
| **M26-A** | Database & Permissions | Migration + seeder | None |
| **M26-B** | Anonymous Report Backend | Auth enforcement + identity storage + API masking | M26-A |
| **M26-C** | Break-Glass Backend | Request/approve/deny/reveal endpoints + audit | M26-A |
| **M26-D** | Anonymous Report Frontend | Portal visibility for anonymous reports | M26-B |
| **M26-E** | Break-Glass Frontend | Admin request UI + Super Admin approval UI | M26-C |
| **M26-F** | Audit & Policy Enforcement | Audit filtering + notification integration | M26-B, M26-C |

---

## Phase M26-A: Database & Permissions

### A1. Migration: `create_break_glass_requests_table`

Create migration file: `database/migrations/xxxx_xx_xx_create_break_glass_requests_table.php`

```
Table: break_glass_requests

Columns:
  id              bigint PK auto-increment
  requestor_id    bigint FK → users, NOT NULL
  approver_id     bigint FK → users, NULLABLE
  report_id       bigint FK → reports, NOT NULL
  reason_category varchar(50) NOT NULL
  reason          text NOT NULL
  status          varchar(20) NOT NULL DEFAULT 'pending'
  denial_reason   text NULLABLE
  requested_at    timestamp NOT NULL
  approved_at     timestamp NULLABLE
  denied_at       timestamp NULLABLE
  viewed_at       timestamp NULLABLE
  created_at      timestamp NOT NULL

Indexes:
  INDEX on (status) — for querying pending requests
  INDEX on (report_id) — for finding requests by report
  INDEX on (requestor_id) — for finding requests by user

Constraints:
  FOREIGN KEY requestor_id REFERENCES users(id)
  FOREIGN KEY approver_id REFERENCES users(id)
  FOREIGN KEY report_id REFERENCES reports(id)

Notes:
  - No updated_at column
  - No SoftDeletes
  - Records are immutable after status transition
```

### A2. Seeder: Update `RbacSeeder`

Add 3 new permissions to the `$permissions` array:

```
'privacy.request_break_glass'          → module: Privasi
'privacy.approve_break_glass'          → module: Privasi
'privacy.reveal_anonymous_identity'    → module: Privasi
```

Update `$rolePermissions`:

```
super_admin: add all 3 new permissions
admin: add 'privacy.request_break_glass' only
satgas_ppks: no changes
reporter: no changes
```

### A3. Model: `BreakGlassRequest`

Create model file: `app/Models/BreakGlassRequest.php`

```
Fillable:
  requestor_id, approver_id, report_id, reason_category,
  reason, status, denial_reason, requested_at, approved_at,
  denied_at, viewed_at

Casts:
  requested_at → datetime
  approved_at → datetime
  denied_at → datetime
  viewed_at → datetime

Relationships:
  requestor() → belongsTo(User)
  approver() → belongsTo(User)
  report() → belongsTo(Report)

Constants:
  STATUS_PENDING = 'pending'
  STATUS_APPROVED = 'approved'
  STATUS_DENIED = 'denied'
  STATUS_VIEWED = 'viewed'

  REASON_CATEGORIES = [
    'legal_requirement',
    'safety_emergency',
    'investigation_necessity',
    'institutional_compliance',
    'victim_consent',
  ]

Custom:
  UPDATED_AT = null (no updated_at column)
  No SoftDeletes

Methods:
  isPending(): bool
  isApproved(): bool
  isViewable(): bool → approved AND (viewed_at is null OR viewed_at + 8h > now())
  isExpired(): bool → viewed_at is not null AND viewed_at + 8h <= now()
```

### A4. Files to Create/Modify

| Action | File | Description |
|---|---|---|
| CREATE | `database/migrations/xxxx_create_break_glass_requests_table.php` | New table |
| CREATE | `app/Models/BreakGlassRequest.php` | New model |
| MODIFY | `database/seeders/RbacSeeder.php` | Add 3 permissions, update role assignments |

---

## Phase M26-B: Anonymous Report Backend

### B1. Route Change: Add `auth:sanctum` to Report Submission

**File:** `routes/api.php`

```
BEFORE:
  Route::prefix('reports')->group(function () {
      Route::post('/', [ReportController::class, 'store'])
          ->middleware('throttle:reports.submit');

AFTER:
  Route::prefix('reports')->group(function () {
      Route::post('/', [ReportController::class, 'store'])
          ->middleware(['auth:sanctum', 'throttle:reports.submit']);
```

### B2. Service Change: Always Store `reporter_id`

**File:** `app/Services/ReportService.php`

Changes to `submit()` method:
1. Remove line: `$user = $reportType === 'anonymous' ? null : $this->resolveUserFromBearerToken($bearerToken);`
2. Replace with: `$user = auth()->user();` (guaranteed by middleware)
3. Remove unauthenticated check (middleware handles it)
4. Keep active/permission checks

Changes to `createReportWithUniqueIdentifiers()`:
1. Remove line: `'reporter_id' => $reportType === 'anonymous' ? null : $user?->id,`
2. Replace with: `'reporter_id' => $user->id,` (always non-null)
3. Keep tracking_code generation for anonymous (backward compat)

The `resolveUserFromBearerToken()` private method can be removed entirely.

### B3. API Resource: Mask Reporter Identity

**File:** `app/Http/Resources/ReportMetadataResource.php`

Add to `toArray()`:
```
'reporter' => $this->report_type === 'anonymous'
    ? ['masked' => true]
    : ($this->whenLoaded('reporter', fn() => [
        'id' => $this->reporter->id,
        'name' => $this->reporter->name,
      ])),
'is_anonymous' => $this->report_type === 'anonymous',
```

Ensure the `reporter` relationship is NOT eager-loaded for anonymous reports in admin endpoints.

### B4. Portal Query: Include Anonymous Reports

**File:** `app/Services/ReporterPortalService.php`

Change `ownedReportsQuery()`:
```
BEFORE:
  return Report::query()
      ->where('reporter_id', $user->id)
      ->whereNotNull('reporter_id')
      ->where('report_type', '!=', 'anonymous');

AFTER:
  return Report::query()
      ->where('reporter_id', $user->id)
      ->whereNotNull('reporter_id');
```

### B5. Files to Modify

| Action | File | Description |
|---|---|---|
| MODIFY | `routes/api.php` | Add `auth:sanctum` to `POST /reports` |
| MODIFY | `app/Services/ReportService.php` | Remove null branch, use `auth()->user()` |
| MODIFY | `app/Http/Resources/ReportMetadataResource.php` | Add identity masking for anonymous |
| MODIFY | `app/Services/ReporterPortalService.php` | Remove `report_type != anonymous` filter |

---

## Phase M26-C: Break-Glass Backend

### C1. Policy: `BreakGlassPolicy`

**File (CREATE):** `app/Policies/BreakGlassPolicy.php`

```
Methods:
  request(User): bool
    → allowPermission('privacy.request_break_glass')
       && allowRole('admin', 'super_admin')

  viewAny(User): bool
    → allowPermission('privacy.approve_break_glass')
       && allowRole('super_admin')

  view(User, BreakGlassRequest): bool
    → (user is requestor) OR viewAny(user)

  approve(User, BreakGlassRequest): bool
    → request.isPending()
       && allowPermission('privacy.approve_break_glass')
       && allowRole('super_admin')
       && (user.id !== request.requestor_id OR isSingleSuperAdmin())

  deny(User, BreakGlassRequest): bool
    → same as approve()

  reveal(User, BreakGlassRequest): bool
    → request.isApproved()
       && request.isViewable() (within 8h TTL)
       && allowPermission('privacy.reveal_anonymous_identity')
       && allowRole('super_admin')
       && (user.id === request.requestor_id OR user.id === request.approver_id)
```

### C2. FormRequests

**File (CREATE):** `app/Http/Requests/BreakGlassStoreRequest.php`

```
Rules:
  report_id: required|exists:reports,id
  reason_category: required|in:legal_requirement,safety_emergency,...
  reason: required|string|min:50|max:2000
  acknowledgment: required|accepted

Custom validation:
  - Report must have report_type = 'anonymous'
  - No duplicate pending request by same user for same report
```

**File (CREATE):** `app/Http/Requests/BreakGlassDenyRequest.php`

```
Rules:
  denial_reason: required|string|min:10|max:2000
```

### C3. Controller: `BreakGlassController`

**File (CREATE):** `app/Http/Controllers/Api/V1/BreakGlassController.php`

```
Methods:

  request(BreakGlassStoreRequest): JsonResponse
    → Gate::authorize('request', BreakGlassRequest::class)
    → Create BreakGlassRequest with status=pending
    → Create AuditLog: break_glass.request, category=privacy, severity=critical
    → Notify all Super Admins
    → Return 201

  pending(Request): JsonResponse
    → Gate::authorize('viewAny', BreakGlassRequest::class)
    → Query where status=pending, ordered by requested_at
    → Return paginated list

  show(BreakGlassRequest): JsonResponse
    → Gate::authorize('view', $request)
    → Return BreakGlassRequestResource

  approve(BreakGlassRequest): JsonResponse
    → Gate::authorize('approve', $request)
    → Update status=approved, approved_at=now(), approver_id=auth()->id()
    → Create AuditLog: break_glass.approve, category=privacy, severity=critical
    → Notify requestor
    → Notify reporter: "Identitas Anda pada laporan [reg] telah diungkapkan"
    → Return 200

  deny(BreakGlassDenyRequest, BreakGlassRequest): JsonResponse
    → Gate::authorize('deny', $request)
    → Update status=denied, denied_at=now(), approver_id=auth()->id(), denial_reason
    → Create AuditLog: break_glass.deny, category=privacy, severity=critical
    → Notify requestor
    → Return 200

  reveal(BreakGlassRequest): JsonResponse
    → Gate::authorize('reveal', $request)
    → Check: isViewable() (within 8h TTL)
    → If first view: update viewed_at=now(), status=viewed
    → Create AuditLog: break_glass.view_identity, category=privacy, severity=critical
    → Return MINIMAL PROFILE: { name, email } only
    → Return 200

  history(Request): JsonResponse
    → Gate::authorize('viewAny', BreakGlassRequest::class)
    → Query all requests, ordered by requested_at desc
    → Return paginated list
```

### C4. API Resource: `BreakGlassRequestResource`

**File (CREATE):** `app/Http/Resources/BreakGlassRequestResource.php`

```
Fields:
  id, requestor (name, role), report (registration_number, report_type),
  reason_category, reason, status, denial_reason,
  requested_at, approved_at, denied_at, viewed_at,
  is_viewable (computed: within TTL), expires_at (computed: viewed_at + 8h)

Does NOT include:
  - Reporter identity (that's only in the reveal endpoint)
```

### C5. Routes

**File:** `routes/api.php`

Add inside `auth:sanctum` group:
```
Route::middleware('auth:sanctum')->prefix('break-glass')->group(function () {
    Route::post('/request', [BreakGlassController::class, 'request']);
    Route::get('/pending', [BreakGlassController::class, 'pending']);
    Route::get('/history', [BreakGlassController::class, 'history']);
    Route::get('/{breakGlassRequest}', [BreakGlassController::class, 'show']);
    Route::patch('/{breakGlassRequest}/approve', [BreakGlassController::class, 'approve']);
    Route::patch('/{breakGlassRequest}/deny', [BreakGlassController::class, 'deny']);
    Route::get('/{breakGlassRequest}/reveal', [BreakGlassController::class, 'reveal']);
});
```

### C6. Service: `BreakGlassService` (Optional)

**File (CREATE):** `app/Services/BreakGlassService.php`

Encapsulates business logic:
- TTL calculation (viewed_at + 8 hours)
- Single Super Admin detection
- Audit log creation helper
- Notification dispatch

### C7. Files to Create/Modify

| Action | File | Description |
|---|---|---|
| CREATE | `app/Policies/BreakGlassPolicy.php` | Authorization policy |
| CREATE | `app/Http/Requests/BreakGlassStoreRequest.php` | Request validation |
| CREATE | `app/Http/Requests/BreakGlassDenyRequest.php` | Deny validation |
| CREATE | `app/Http/Controllers/Api/V1/BreakGlassController.php` | Controller |
| CREATE | `app/Http/Resources/BreakGlassRequestResource.php` | API resource |
| CREATE | `app/Services/BreakGlassService.php` | Business logic |
| MODIFY | `routes/api.php` | Add break-glass routes |
| MODIFY | `app/Providers/AuthServiceProvider.php` | Register BreakGlassPolicy |

---

## Phase M26-D: Anonymous Report Frontend

### D1. Portal Types Update

**File:** `frontend/src/lib/portal-types.ts`

No changes needed — `PortalReport` already uses `report_type: string` which will now include `"anonymous"`.

### D2. Portal Report Card

**File:** `frontend/src/components/portal/portal-report-card.tsx`

Add visual indicator for anonymous reports:
- Show a small "Anonim" badge or icon when `report.report_type === "anonymous"`
- No functional changes — the card already works with all report types

### D3. Operations Types (Admin)

**File:** `frontend/src/lib/operations-types.ts` (or equivalent)

Add type for masked reporter:
```typescript
interface ReportReporter {
  masked: true;
} | {
  id: number;
  name: string;
}

// Add to report list/detail types:
reporter: ReportReporter | null;
is_anonymous: boolean;
```

### D4. Admin Report Views

Ensure admin report list and detail views handle `reporter: { masked: true }`:
- Show "Identitas disembunyikan" or "Pelapor anonim" label
- Show break-glass request button (if user has `privacy.request_break_glass` permission)
- Do NOT attempt to display reporter name/email

### D5. Localization Keys

Add to `frontend/src/locales/id/portal.json` and `en/portal.json`:

```
ID: "laporanAnonim": "Laporan Anonim"
EN: "anonymousReport": "Anonymous Report"

ID: "identitasDisembunyikan": "Identitas pelapor disembunyikan"
EN: "reporterIdentityHidden": "Reporter identity is hidden"
```

### D6. Files to Modify

| Action | File | Description |
|---|---|---|
| MODIFY | `src/components/portal/portal-report-card.tsx` | Anonymous indicator |
| MODIFY | `src/lib/operations-types.ts` | Masked reporter type |
| MODIFY | `src/locales/id/portal.json` | Anonymous labels |
| MODIFY | `src/locales/en/portal.json` | Anonymous labels |
| MODIFY | Admin report list/detail views | Handle masked reporter |

---

## Phase M26-E: Break-Glass Frontend

### E1. API Client

**File (CREATE):** `frontend/src/lib/break-glass-api.ts`

```typescript
Functions:
  requestBreakGlass(reportId, reasonCategory, reason)
  getPendingRequests()
  getBreakGlassRequest(id)
  approveBreakGlass(id)
  denyBreakGlass(id, denialReason)
  revealIdentity(id)
  getBreakGlassHistory()
```

### E2. Types

**File (CREATE):** `frontend/src/lib/break-glass-types.ts`

```typescript
BreakGlassRequest {
  id, requestor, report, reason_category, reason, status,
  denial_reason, requested_at, approved_at, denied_at,
  viewed_at, is_viewable, expires_at
}

BreakGlassReveal {
  name: string
  email: string
  break_glass_reference: string
  valid_until: string  // ISO timestamp (viewed_at + 8h)
}

ReasonCategory = 'legal_requirement' | 'safety_emergency' | ...
```

### E3. Admin UI Components

**Break-Glass Request Dialog** — shown on anonymous report detail page:
- Reason category selector
- Reason text area (min 50 chars)
- Acknowledgment checkbox
- Submit button
- Only visible to users with `privacy.request_break_glass` permission

**Break-Glass Pending List** — Super Admin only:
- List of pending requests with requestor, report, reason, timestamp
- Approve / Deny buttons
- Deny requires reason text

**Break-Glass Reveal View** — after approval:
- Shows: name, email
- Shows: TTL countdown (expires in X hours)
- Audit warning banner

### E4. Localization Keys

Add break-glass labels in ID and EN for:
- Request form labels
- Reason category labels
- Status labels
- Approval/denial messages
- Reporter notification text
- TTL expiry warnings

### E5. Files to Create/Modify

| Action | File | Description |
|---|---|---|
| CREATE | `src/lib/break-glass-api.ts` | API client |
| CREATE | `src/lib/break-glass-types.ts` | TypeScript types |
| CREATE | `src/components/admin/break-glass-request-dialog.tsx` | Request form |
| CREATE | `src/components/admin/break-glass-pending-list.tsx` | Pending list |
| CREATE | `src/components/admin/break-glass-reveal-view.tsx` | Reveal display |
| MODIFY | Admin report detail page | Add break-glass trigger |
| MODIFY | Dashboard layout | Add pending break-glass indicator for Super Admin |
| MODIFY | Locales (id/en) | Add break-glass labels |

---

## Phase M26-F: Audit & Policy Enforcement

### F1. Audit Log Filtering (Q12=B)

**File:** `app/Policies/AuditLogPolicy.php`

Modify `viewAny()` to NOT change — both Admin and Super Admin can view audit logs.

**File:** `app/Http/Controllers/Api/V1/AuditLogController.php`

Modify `index()` query to filter:
```php
// If user is Admin (not Super Admin), exclude privacy category
if ($user->hasRole('admin') && !$user->hasRole('super_admin')) {
    $query->where('category', '!=', 'privacy');
}
```

### F2. Notification: Reporter on Break-Glass Approval

**File (CREATE):** `app/Notifications/BreakGlassApprovedNotification.php`

```
Channels: database (Laravel notification stored in notifications table)
Content:
  title: "Pemberitahuan Privasi"
  body: "Identitas Anda pada laporan [registration_number] telah diungkapkan
         melalui prosedur break-glass sesuai kebijakan privasi SILAPPKASAL."
  type: "privacy_notice"
```

Does NOT include: who requested, who approved, or why.

### F3. Notification: Super Admin on Break-Glass Request

**File (CREATE):** `app/Notifications/BreakGlassRequestedNotification.php`

```
Channels: database
Content:
  title: "Permintaan Break-Glass Baru"
  body: "Admin [name] meminta akses break-glass untuk laporan [reg_number].
         Alasan: [reason_category]. Tindakan diperlukan."
  type: "break_glass_request"
```

### F4. Notification: Requestor on Approval/Denial

**File (CREATE):** `app/Notifications/BreakGlassResolvedNotification.php`

```
Content (approved):
  title: "Permintaan Break-Glass Disetujui"
  body: "Permintaan Anda untuk laporan [reg_number] telah disetujui.
         Akses berlaku selama 8 jam."

Content (denied):
  title: "Permintaan Break-Glass Ditolak"
  body: "Permintaan Anda untuk laporan [reg_number] ditolak.
         Alasan: [denial_reason]"
```

### F5. Files to Create/Modify

| Action | File | Description |
|---|---|---|
| CREATE | `app/Notifications/BreakGlassApprovedNotification.php` | Reporter notification |
| CREATE | `app/Notifications/BreakGlassRequestedNotification.php` | Super Admin notification |
| CREATE | `app/Notifications/BreakGlassResolvedNotification.php` | Requestor notification |
| MODIFY | `app/Http/Controllers/Api/V1/AuditLogController.php` | Filter privacy category for Admin |

---

## Anonymous Report Migration Strategy (FINAL)

| Item | Decision |
|---|---|
| Existing `reporter_id = null` reports | **Leave as-is.** No migration. |
| New anonymous reports (post-M26) | `reporter_id = auth()->id()` always |
| Legacy portal visibility | Legacy anonymous reports remain invisible in portal |
| Legacy tracking code access | `GET /reports/track/{code}` continues to work |
| Data backfill | **Not possible.** Identity was never stored. |
| Schema migration | Only `break_glass_requests` table. No changes to `reports` table. |

---

## Complete File Manifest

### New Files (13)

| # | Path | Phase |
|---|---|---|
| 1 | `database/migrations/xxxx_create_break_glass_requests_table.php` | A |
| 2 | `app/Models/BreakGlassRequest.php` | A |
| 3 | `app/Policies/BreakGlassPolicy.php` | C |
| 4 | `app/Http/Requests/BreakGlassStoreRequest.php` | C |
| 5 | `app/Http/Requests/BreakGlassDenyRequest.php` | C |
| 6 | `app/Http/Controllers/Api/V1/BreakGlassController.php` | C |
| 7 | `app/Http/Resources/BreakGlassRequestResource.php` | C |
| 8 | `app/Services/BreakGlassService.php` | C |
| 9 | `app/Notifications/BreakGlassApprovedNotification.php` | F |
| 10 | `app/Notifications/BreakGlassRequestedNotification.php` | F |
| 11 | `app/Notifications/BreakGlassResolvedNotification.php` | F |
| 12 | `frontend/src/lib/break-glass-api.ts` | E |
| 13 | `frontend/src/lib/break-glass-types.ts` | E |

### Modified Files (10)

| # | Path | Phase |
|---|---|---|
| 1 | `database/seeders/RbacSeeder.php` | A |
| 2 | `routes/api.php` | B, C |
| 3 | `app/Services/ReportService.php` | B |
| 4 | `app/Http/Resources/ReportMetadataResource.php` | B |
| 5 | `app/Services/ReporterPortalService.php` | B |
| 6 | `app/Http/Controllers/Api/V1/AuditLogController.php` | F |
| 7 | `frontend/src/components/portal/portal-report-card.tsx` | D |
| 8 | `frontend/src/locales/id/portal.json` | D |
| 9 | `frontend/src/locales/en/portal.json` | D |
| 10 | `app/Providers/AuthServiceProvider.php` | C |

### Frontend New Components (3)

| # | Path | Phase |
|---|---|---|
| 1 | `frontend/src/components/admin/break-glass-request-dialog.tsx` | E |
| 2 | `frontend/src/components/admin/break-glass-pending-list.tsx` | E |
| 3 | `frontend/src/components/admin/break-glass-reveal-view.tsx` | E |

---

## QA Checklist

### Phase A — Database & Permissions

- [ ] Migration runs without errors: `php artisan migrate`
- [ ] Seeder runs without errors: `php artisan db:seed --class=RbacSeeder`
- [ ] 3 new permissions exist in `permissions` table
- [ ] Super Admin has all 3 new permissions
- [ ] Admin has `privacy.request_break_glass` only
- [ ] Satgas and Reporter have no new permissions
- [ ] `break_glass_requests` table exists with correct schema

### Phase B — Anonymous Report Backend

- [ ] `POST /v1/reports` returns 401 without Bearer token
- [ ] `POST /v1/reports` with `report_type: "anonymous"` stores `reporter_id = auth()->id()`
- [ ] `reporter_id` is never null for new reports
- [ ] Tracking code is still generated for anonymous reports
- [ ] `GET /v1/reports` (admin) returns `reporter: { masked: true }` for anonymous reports
- [ ] `GET /v1/reports` (admin) returns `reporter: { id, name }` for non-anonymous reports
- [ ] `GET /v1/reports/{id}` (admin) does NOT eager-load reporter for anonymous
- [ ] `GET /v1/portal/reports` (reporter) includes anonymous reports
- [ ] `GET /v1/portal/reports/{reg}` (reporter) works for anonymous reports
- [ ] Legacy anonymous reports (`reporter_id = null`) do NOT appear in any portal
- [ ] Legacy tracking code access still works

### Phase C — Break-Glass Backend

- [ ] `POST /v1/break-glass/request` — Admin can create request
- [ ] `POST /v1/break-glass/request` — Satgas/Reporter get 403
- [ ] `POST /v1/break-glass/request` — validates: report must be anonymous
- [ ] `POST /v1/break-glass/request` — validates: reason min 50 chars
- [ ] `POST /v1/break-glass/request` — validates: reason_category is valid enum
- [ ] `POST /v1/break-glass/request` — creates AuditLog with severity: critical
- [ ] `GET /v1/break-glass/pending` — Super Admin sees pending requests
- [ ] `GET /v1/break-glass/pending` — Admin gets 403
- [ ] `PATCH /v1/break-glass/{id}/approve` — Super Admin can approve
- [ ] `PATCH /v1/break-glass/{id}/approve` — Admin gets 403
- [ ] `PATCH /v1/break-glass/{id}/approve` — creates AuditLog
- [ ] `PATCH /v1/break-glass/{id}/deny` — requires denial_reason
- [ ] `PATCH /v1/break-glass/{id}/deny` — creates AuditLog
- [ ] `GET /v1/break-glass/{id}/reveal` — returns name + email only (not NIM, phone)
- [ ] `GET /v1/break-glass/{id}/reveal` — sets viewed_at on first view
- [ ] `GET /v1/break-glass/{id}/reveal` — works within 8-hour TTL
- [ ] `GET /v1/break-glass/{id}/reveal` — returns 403 after 8-hour TTL expires
- [ ] `GET /v1/break-glass/{id}/reveal` — creates AuditLog on each view
- [ ] Denied requests: new request for same report is allowed
- [ ] No limit on requests per report

### Phase D — Anonymous Report Frontend

- [ ] Reporter's "My Reports" shows anonymous reports
- [ ] Anonymous report card shows "Anonim" indicator
- [ ] Anonymous report detail page works correctly
- [ ] Reporter receives notifications for anonymous report status changes

### Phase E — Break-Glass Frontend

- [ ] Admin sees break-glass request button on anonymous report detail
- [ ] Admin does NOT see button on non-anonymous reports
- [ ] Break-glass request form validates all fields
- [ ] Super Admin sees pending requests list
- [ ] Super Admin can approve/deny from the list
- [ ] Reveal view shows name + email only
- [ ] Reveal view shows TTL countdown
- [ ] Satgas/Reporter cannot see any break-glass UI

### Phase F — Audit & Notifications

- [ ] Admin audit log view does NOT show break-glass entries (category: privacy)
- [ ] Super Admin audit log view DOES show break-glass entries
- [ ] Reporter receives notification on break-glass approval
- [ ] Reporter does NOT receive notification on request or denial
- [ ] Super Admins receive notification when break-glass is requested
- [ ] Requestor receives notification when approved/denied

### Build Verification

- [ ] `php artisan test` passes
- [ ] `npm run lint` passes (frontend)
- [ ] `npm run build` passes (frontend)

---

## Rollback Strategy

### Phase-Level Rollback

| Phase | Rollback Method |
|---|---|
| M26-A | `php artisan migrate:rollback` — drops `break_glass_requests` table. Re-run `RbacSeeder` with old permissions removed. |
| M26-B | Revert `routes/api.php` (remove auth:sanctum from POST /reports). Revert `ReportService.php` (restore null branch). Revert `ReportMetadataResource.php`. Revert `ReporterPortalService.php`. |
| M26-C | Remove all break-glass controller, policy, service, request files. Remove break-glass routes from `api.php`. |
| M26-D | Revert frontend portal components. Remove localization keys. |
| M26-E | Delete break-glass frontend components and API client. |
| M26-F | Revert `AuditLogController.php` filter. Delete notification classes. |

### Data Rollback

| Concern | Strategy |
|---|---|
| `break_glass_requests` data | Table dropped on migration rollback. Data is lost (acceptable — no production break-glass data before M26). |
| `reporter_id` changes | New reports created post-M26 with `reporter_id` set will retain the value even after rollback. This is safe — having an identity stored is never worse than not having one. |
| Audit logs | Break-glass audit entries remain in `audit_logs` table even after rollback. They are harmless. |
| Permissions | Re-running `RbacSeeder` with old code removes new permissions and re-syncs role assignments. |

### Emergency Rollback

If critical production issue after deploy:
1. Revert all M26 code changes (git revert)
2. Run `php artisan migrate:rollback` for break_glass_requests
3. Re-run `php artisan db:seed --class=RbacSeeder`
4. Rebuild and redeploy frontend
5. Reports created during M26 window retain `reporter_id` (safe)
