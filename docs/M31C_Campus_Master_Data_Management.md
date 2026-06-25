# M31C — Campus Master Data Management & Registration Stabilization

> **Phase**: M31C | **Type**: Backend + Frontend | **Status**: FROZEN
> **Builds on**: M31A (Multi-Campus Master Data Foundation), M31B (Reporter Portal & Registration)
> **Trigger**: Human smoke testing revealed operational blockers in registration flow

---

## 1. Objectives

Stabilize the reporter onboarding flow and provide Super Admin campus master-data management so that the registration flow is realistically usable end-to-end.

**Problems discovered during smoke test:**
1. Registration cannot be completed — faculty/study-program data not properly available
2. Faculty behavior does not match business rule (it must be optional, never required)
3. No campus master-data management UI exists — Super Admin cannot create universities/faculties/programs
4. Password fields lack show/hide toggle
5. Additional UI usability issues in registration form

**In scope:**
1. Backend CRUD APIs for universities, faculties, study programs (Super Admin only)
2. Active/inactive toggle support
3. Cross-entity validation (faculty→university, study_program→university/faculty)
4. Audit logging for all CRUD operations
5. Frontend: Campus Master Data management pages (list, search, filter, create, edit, activate/deactivate)
6. Frontend: Registration stabilization (faculty optional, cascading dropdowns fix, password show/hide)
7. Frontend: UX polish review on registration flow

**Explicitly out of scope:**
- ❌ SMTP / OTP
- ❌ Flutter
- ❌ Campus-scoped policy overhaul
- ❌ Dashboard full localization
- ❌ UAT
- ❌ Deployment
- ❌ Hard delete of any campus master data record

---

## 1B. Frozen Decisions

### FD-1: No Hard Delete for Campus Master Data

Campus master data supports **activate/deactivate only**. Hard delete is permanently excluded for:
- Universities
- Faculties
- Study Programs

**Reason:** Existing registrations, users, cases, audit logs, and FK references must remain valid. Deleting a university would orphan all associated records. Deactivation achieves the same operational effect (entity no longer appears in public endpoints or registration dropdowns) while preserving referential integrity.

> [!CAUTION]
> No delete endpoint, no delete button, no delete migration. This is a permanent architectural constraint, not a deferral.

### FD-2: Campus Master Data Management Is Super Admin Only

All campus-admin CRUD endpoints and the management UI are restricted to `super_admin` role only.

| Role | Public Read (active only) | Admin CRUD (list/create/edit/toggle) |
|---|---|---|
| `super_admin` | ✅ | ✅ |
| `admin` | ✅ | ❌ 403 |
| `satgas_ppks` | ✅ | ❌ 403 |
| `reporter` | ✅ (public endpoints) | ❌ 403 |
| Unauthenticated | ✅ (public endpoints) | ❌ 401 |

**Reason:** Campus master data affects all universities in the platform. Allowing per-university admin to modify shared master data would create governance conflicts. Admin role retains read-only access via existing public endpoints.

### FD-3: Registration Behavior When `university.has_faculties = false`

When the selected university has `has_faculties = false` (e.g., Sekolah Tinggi, Politeknik, Akademi):

| Aspect | Behavior |
|---|---|
| Faculty dropdown | **Hidden** — not rendered in the form |
| `faculty_id` validation | **Not validated** — backend rule is `nullable` |
| `faculty_id` submission | **Not submitted** — frontend sends `faculty_id: null` |
| Study Program dropdown | **Required** — loads all study programs for the university (no faculty filter) |

This is already structurally correct in backend validation ([ReporterRegistrationStoreRequest](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Http/Requests/ReporterRegistrationStoreRequest.php) has `faculty_id` as `nullable`) and frontend logic ([register.tsx line 161](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/register.tsx#L161) conditionally renders faculty when `hasFaculties === true`). This FD confirms the behavior is intentional and frozen.

### FD-4: Password Show/Hide Toggle — Full Scope

The `PasswordInput` component must be applied to **every** password field across the entire application:

| Page | File | Password Fields |
|---|---|---|
| Login | [login.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/login.tsx) | 1: login password |
| Registration | [register.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/register.tsx) | 2: password, password_confirmation |
| Registration Correction | [registration.correction.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/registration.correction.tsx) | 3: current_password, new_password, new_password_confirmation |
| Portal — Change Password | [portal.account.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/portal.account.tsx) | 3: current_password, new_password, confirm_password |
| Dashboard — Manual Reporter Creation | [dashboard.users.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.users.tsx) | 1: temporary password |

**Total: 10 password fields across 5 files.**

---

## 2. Backend Scope

### Phase 1 — Campus Master Data CRUD Service

#### [NEW] `app/Services/CampusMasterDataService.php`

A service class handling all CRUD operations for universities, faculties, and study programs. Follows the established service pattern in [ReporterRegistrationService.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Services/ReporterRegistrationService.php).

**Methods:**

| Method | Description |
|---|---|
| `listUniversities(filters)` | Paginated list with search/filter, includes counts of faculties and study programs |
| `createUniversity(data, actor)` | Create university + audit log |
| `updateUniversity(university, data, actor)` | Update university + audit log |
| `toggleUniversityActive(university, actor)` | Activate/deactivate + audit log. Deactivating cascades `is_active=false` to child faculties and study programs |
| `listFaculties(filters)` | Paginated list with search, filtered by `university_id`, includes study program count |
| `createFaculty(data, actor)` | Create faculty + validation (university exists & is active) + audit log |
| `updateFaculty(faculty, data, actor)` | Update faculty + audit log |
| `toggleFacultyActive(faculty, actor)` | Activate/deactivate + audit log. Deactivating cascades to child study programs |
| `listStudyPrograms(filters)` | Paginated list with search, filtered by `university_id` and optionally `faculty_id` |
| `createStudyProgram(data, actor)` | Create study program + validation (university & optional faculty exist and are active) + audit log |
| `updateStudyProgram(studyProgram, data, actor)` | Update study program + audit log |
| `toggleStudyProgramActive(studyProgram, actor)` | Activate/deactivate + audit log |

**Cascade deactivation rules:**

```
University deactivated
  └→ All child faculties → is_active = false
       └→ All child study_programs → is_active = false
  └→ All study_programs (faculty_id = null, same university) → is_active = false

Faculty deactivated
  └→ All child study_programs → is_active = false
```

> [!NOTE]
> Activation does NOT cascade upward or downward. Reactivating a university does not automatically reactivate its children. Each must be individually reactivated. This prevents accidentally exposing stale data.

---

### Phase 2 — Campus Master Data CRUD Controller & Routes

#### [MODIFY] [CampusMasterDataController.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Http/Controllers/Api/V1/CampusMasterDataController.php)

Extend the existing controller with CRUD endpoints. The 3 existing public read-only methods (`universities()`, `faculties()`, `studyPrograms()`) remain unchanged.

**New endpoints:**

##### Universities CRUD

| Endpoint | Method | Auth | Description |
|---|---|---|---|
| `GET /api/v1/campus-admin/universities` | `indexAdmin` | `auth:sanctum` | Paginated list with inactive, search, counts |
| `POST /api/v1/campus-admin/universities` | `storeUniversity` | `auth:sanctum` | Create university |
| `PUT /api/v1/campus-admin/universities/{university}` | `updateUniversity` | `auth:sanctum` | Update university |
| `PATCH /api/v1/campus-admin/universities/{university}/toggle-active` | `toggleUniversityActive` | `auth:sanctum` | Activate/deactivate |

##### Faculties CRUD

| Endpoint | Method | Auth | Description |
|---|---|---|---|
| `GET /api/v1/campus-admin/faculties` | `indexFacultiesAdmin` | `auth:sanctum` | Paginated, filtered by `university_id` |
| `POST /api/v1/campus-admin/faculties` | `storeFaculty` | `auth:sanctum` | Create faculty |
| `PUT /api/v1/campus-admin/faculties/{faculty}` | `updateFaculty` | `auth:sanctum` | Update faculty |
| `PATCH /api/v1/campus-admin/faculties/{faculty}/toggle-active` | `toggleFacultyActive` | `auth:sanctum` | Activate/deactivate |

##### Study Programs CRUD

| Endpoint | Method | Auth | Description |
|---|---|---|---|
| `GET /api/v1/campus-admin/study-programs` | `indexStudyProgramsAdmin` | `auth:sanctum` | Paginated, filtered by `university_id`, `faculty_id` |
| `POST /api/v1/campus-admin/study-programs` | `storeStudyProgram` | `auth:sanctum` | Create study program |
| `PUT /api/v1/campus-admin/study-programs/{studyProgram}` | `updateStudyProgram` | `auth:sanctum` | Update study program |
| `PATCH /api/v1/campus-admin/study-programs/{studyProgram}/toggle-active` | `toggleStudyProgramActive` | `auth:sanctum` | Activate/deactivate |

#### [MODIFY] [api.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/routes/api.php)

Add new route group under `auth:sanctum`:

```php
Route::middleware('auth:sanctum')->prefix('campus-admin')->group(function (): void {
    // Universities
    Route::get('/universities', [CampusMasterDataController::class, 'indexAdmin']);
    Route::post('/universities', [CampusMasterDataController::class, 'storeUniversity']);
    Route::put('/universities/{university}', [CampusMasterDataController::class, 'updateUniversity']);
    Route::patch('/universities/{university}/toggle-active', [CampusMasterDataController::class, 'toggleUniversityActive']);

    // Faculties
    Route::get('/faculties', [CampusMasterDataController::class, 'indexFacultiesAdmin']);
    Route::post('/faculties', [CampusMasterDataController::class, 'storeFaculty']);
    Route::put('/faculties/{faculty}', [CampusMasterDataController::class, 'updateFaculty']);
    Route::patch('/faculties/{faculty}/toggle-active', [CampusMasterDataController::class, 'toggleFacultyActive']);

    // Study Programs
    Route::get('/study-programs', [CampusMasterDataController::class, 'indexStudyProgramsAdmin']);
    Route::post('/study-programs', [CampusMasterDataController::class, 'storeStudyProgram']);
    Route::put('/study-programs/{studyProgram}', [CampusMasterDataController::class, 'updateStudyProgram']);
    Route::patch('/study-programs/{studyProgram}/toggle-active', [CampusMasterDataController::class, 'toggleStudyProgramActive']);
});
```

> **Design decision:** Using `/campus-admin/` prefix rather than extending `/master/` because these are CRUD management endpoints (Super Admin only), not read-only lookups. The existing `/universities`, `/faculties`, `/study-programs` public endpoints remain untouched.

---

### Phase 3 — Policy, Form Requests, Audit

#### [NEW] `app/Policies/CampusMasterDataPolicy.php`

Extends [BasePolicy](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Policies/BasePolicy.php).

```php
class CampusMasterDataPolicy extends BasePolicy
{
    public function manage(User $user): bool
    {
        return $this->allowRole($user, 'super_admin');
    }
}
```

Single `manage` gate applied to all CRUD operations. Only `super_admin` can manage campus master data.

**RBAC Matrix:**

| Role | View public endpoints | CRUD campus-admin | Activate/deactivate |
|---|---|---|---|
| `super_admin` | ✅ | ✅ | ✅ |
| `admin` | ✅ | ❌ | ❌ |
| `satgas_ppks` | ✅ | ❌ | ❌ |
| `reporter` | ✅ (public endpoints) | ❌ | ❌ |
| Unauthenticated | ✅ (public endpoints) | ❌ | ❌ |

#### New Form Requests

##### [NEW] `app/Http/Requests/StoreUniversityRequest.php`

| Field | Rules |
|---|---|
| `code` | required, string, max:20, unique:universities,code |
| `name` | required, string, max:255 |
| `abbreviation` | nullable, string, max:30 |
| `address` | nullable, string |
| `website` | nullable, url, max:255 |
| `email` | nullable, email, max:255 |
| `hotline` | nullable, string, max:30 |
| `type` | required, in:universitas,institut,sekolah_tinggi,politeknik,akademi |
| `has_faculties` | required, boolean |

##### [NEW] `app/Http/Requests/UpdateUniversityRequest.php`

Same rules as Store, but `code` unique rule ignores current record.

##### [NEW] `app/Http/Requests/StoreFacultyRequest.php`

| Field | Rules |
|---|---|
| `university_id` | required, integer, exists:universities,id (must be active) |
| `code` | required, string, max:20, unique within university |
| `name` | required, string, max:255 |

##### [NEW] `app/Http/Requests/UpdateFacultyRequest.php`

Same rules, `code` unique rule ignores current record within university.

##### [NEW] `app/Http/Requests/StoreStudyProgramRequest.php`

| Field | Rules |
|---|---|
| `university_id` | required, integer, exists:universities,id (must be active) |
| `faculty_id` | nullable, integer, exists:faculties,id (must be active, must belong to `university_id`) |
| `code` | required, string, max:20, unique within university |
| `name` | required, string, max:255 |
| `degree_level` | required, in:D3,D4,S1,S2,S3,profesi |

Uses the existing [ValidatesCampusSelection](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Http/Requests/Concerns/ValidatesCampusSelection.php) trait for faculty→university validation.

##### [NEW] `app/Http/Requests/UpdateStudyProgramRequest.php`

Same rules, `code` unique rule ignores current record within university.

#### Audit Logging

##### [MODIFY] `app/Enums/AuditAction.php`

Add new enum cases:

```php
case CampusUniversityCreated = 'campus.university_created';
case CampusUniversityUpdated = 'campus.university_updated';
case CampusUniversityActivated = 'campus.university_activated';
case CampusUniversityDeactivated = 'campus.university_deactivated';
case CampusFacultyCreated = 'campus.faculty_created';
case CampusFacultyUpdated = 'campus.faculty_updated';
case CampusFacultyActivated = 'campus.faculty_activated';
case CampusFacultyDeactivated = 'campus.faculty_deactivated';
case CampusStudyProgramCreated = 'campus.study_program_created';
case CampusStudyProgramUpdated = 'campus.study_program_updated';
case CampusStudyProgramActivated = 'campus.study_program_activated';
case CampusStudyProgramDeactivated = 'campus.study_program_deactivated';
```

Each CRUD operation records an audit log with:
- `actor`: the Super Admin performing the action
- `subject`: the affected entity (university/faculty/study_program)
- `beforeChanges` / `afterChanges`: for update and toggle operations
- `metadata`: entity code and name for quick identification

---

## 3. Frontend Scope

### Phase 4 — Campus Master Data Management Pages

#### Sidebar Navigation

##### [MODIFY] [dashboard-layout.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/layouts/dashboard-layout.tsx)

Add a new sidebar nav item visible to `super_admin` only:

```typescript
{
  title: "Master Data",
  url: "/dashboard/master-data",
  icon: Database, // from lucide-react
  roles: ["super_admin"],
}
```

Inserted after "Users" and before "Break-glass" in the nav array.

#### [NEW] `frontend/src/lib/campus-admin-api.ts`

API client for all 12 campus-admin CRUD endpoints. Follows the pattern in [registration-api.ts](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/lib/registration-api.ts).

#### [NEW] Route: `dashboard.master-data.tsx`

Layout route for the master-data section. Contains tab navigation between Universities, Faculties, and Study Programs.

#### [NEW] Route: `dashboard.master-data.universities.tsx`

**Features:**
- Table listing all universities (including inactive, shown with dimmed styling)
- Columns: Code, Name, Abbreviation, Type, Has Faculties, Status (active/inactive badge), Actions
- Search by name/code
- Filter by type, status (active/inactive/all)
- **Create dialog**: form with all university fields
- **Edit dialog**: pre-filled form, update on submit
- **Activate/Deactivate button**: toggle with confirmation dialog
- Pagination

#### [NEW] Route: `dashboard.master-data.faculties.tsx`

**Features:**
- University filter dropdown (required — select university first)
- Table: Code, Name, University, Status, Actions
- Search by name/code
- Create dialog: university_id (pre-filled if filtered), code, name
- Edit dialog
- Activate/Deactivate toggle
- Pagination

#### [NEW] Route: `dashboard.master-data.study-programs.tsx`

**Features:**
- University filter dropdown (required)
- Faculty filter dropdown (optional, filtered by selected university)
- Table: Code, Name, Degree Level, Faculty, University, Status, Actions
- Search by name/code
- Create dialog: university_id, faculty_id (optional), code, name, degree_level
- Edit dialog
- Activate/Deactivate toggle
- Pagination

**UI pattern for all 3 pages:** Follow the established pattern in [dashboard.users.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.users.tsx) — table with search, filter, action buttons, dialog forms.

---

### Phase 5 — Registration Stabilization

#### [MODIFY] [register.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/register.tsx)

##### Fix 1: Faculty truly optional (see also FD-3)

Current state: Faculty field shows when `hasFaculties === true` but has no explicit "optional" indicator.

Changes:
- Add "(Opsional)" label suffix when faculty field is displayed
- Ensure `faculty_id` is sent as `null` (not omitted) when faculty not selected
- When `university.has_faculties = false`: faculty dropdown **hidden**, `faculty_id` not validated, not submitted (see FD-3)
- Study programs must load correctly **regardless** of whether faculty is selected:
  - If no faculty selected → load ALL study programs for the university
  - If faculty selected → load only that faculty's study programs

Current code already does this correctly at [line 62](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/register.tsx#L60-L64):
```typescript
queryFn: () => getStudyPrograms(Number(form.university_id), form.faculty_id ? Number(form.faculty_id) : null),
enabled: Boolean(form.university_id),
```

**Verify:** The backend [CampusMasterDataController::studyPrograms()](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Http/Controllers/Api/V1/CampusMasterDataController.php#L61-L84) already uses `when($request->filled('faculty_id'))` so passing no `faculty_id` returns all programs for the university. ✅ No backend change needed.

**Remaining fix:** Ensure the `study_program_id` dropdown **resets** when `university_id` changes but **does not reset** when `faculty_id` is cleared (to allow browsing all programs). Verify current `useEffect` behavior at [lines 66-72](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/register.tsx#L66-L72).

##### Fix 2: Password show/hide toggle

Current state: Password fields use `<Input type="password" />` with no visibility toggle.

Changes:
- Create a reusable `PasswordInput` component
- Add an eye/eye-off icon button inside each password field
- Toggle between `type="password"` and `type="text"`
- Apply to **all 10 password fields across 5 files** (see FD-4 for full inventory):
  - [register.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/register.tsx) — 2 fields
  - [registration.correction.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/registration.correction.tsx) — 3 fields
  - [login.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/login.tsx) — 1 field
  - [portal.account.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/portal.account.tsx) — 3 fields (Change Password section)
  - [dashboard.users.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.users.tsx) — 1 field (Manual Reporter Creation dialog)

##### Fix 3: Empty state handling

- When universities list is loading, show a skeleton/spinner in the dropdown
- When universities list is empty, show a message: "Belum ada universitas terdaftar"
- When faculties or study programs return empty for a university, show appropriate empty state message
- Disable the study_program dropdown if university is not yet selected

##### Fix 4: Form validation UX

- Disable submit button when any required field is empty (client-side pre-validation)
- Show loading state during university/faculty/study_program API fetches in dropdowns
- Clear server-side errors when user modifies the errored field

#### [MODIFY] [registration.correction.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/registration.correction.tsx)

Apply the same fixes:
- Password show/hide toggle for password and optional new_password fields
- Faculty optional indicator
- Cascading dropdown stabilization

#### [MODIFY] [login.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/login.tsx)

- Add password show/hide toggle to login password field

---

### Phase 6 — Reusable Components

#### [NEW] `frontend/src/components/ui/password-input.tsx`

A reusable password input component wrapping the existing [Input](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/components/ui/input.tsx) component:

```tsx
// Renders Input with an eye/eye-off toggle button
// Props: same as Input, plus optional `showToggle` (default: true)
// Uses Eye and EyeOff icons from lucide-react
```

#### [NEW] `frontend/src/components/admin/campus-master-data-dialog.tsx`

Reusable dialog component for create/edit forms across all 3 campus master data types. Uses the existing [Dialog](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/components/ui/dialog.tsx) component.

---

## 4. Validation Rules Summary

### Backend Validation

| Entity | Create | Update | Toggle Active |
|---|---|---|---|
| **University** | code unique globally, name required, type in enum | code unique (ignore self), name required | No additional validation |
| **Faculty** | code unique within university, university must be active | code unique within university (ignore self) | Cannot activate if parent university is inactive |
| **Study Program** | code unique within university, university must be active, faculty (if set) must belong to university and be active | code unique within university (ignore self) | Cannot activate if parent university is inactive; cannot activate if parent faculty (when set) is inactive |

### Registration Validation (existing, verified)

| Field | Current Rule | Business Rule | Status |
|---|---|---|---|
| `university_id` | required, exists | Required | ✅ Correct |
| `faculty_id` | nullable, exists | Optional, NEVER required | ✅ Correct |
| `study_program_id` | required, exists, belongs to university | Required | ✅ Correct |
| Cross-validation | `ValidatesCampusSelection` trait | Faculty→University, StudyProgram→University/Faculty | ✅ Correct |

> [!NOTE]
> Backend validation rules are already correct from M31B. The problems discovered in smoke testing are **frontend-only** issues (cascading dropdown behavior, UX).

---

## 5. RBAC Requirements

#### [MODIFY] `app/Providers/AuthServiceProvider.php` (or wherever Gate/Policy mapping exists)

Register `CampusMasterDataPolicy`:

```php
Gate::define('manage-campus-master-data', [CampusMasterDataPolicy::class, 'manage']);
```

All 12 CRUD endpoints call `Gate::authorize('manage-campus-master-data')` before performing any operation.

| Role | Public Read | Admin List (with inactive) | Create | Update | Toggle Active |
|---|---|---|---|---|---|
| `super_admin` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `admin` | ✅ | ❌ 403 | ❌ 403 | ❌ 403 | ❌ 403 |
| `satgas_ppks` | ✅ | ❌ 403 | ❌ 403 | ❌ 403 | ❌ 403 |
| `reporter` | ✅ | ❌ 403 | ❌ 403 | ❌ 403 | ❌ 403 |

---

## 6. Test Plan

### Backend Tests

#### [NEW] `tests/Feature/CampusMasterDataCrudTest.php`

##### Universities CRUD

| # | Test | Description |
|---|---|---|
| T1 | `test_super_admin_can_list_universities_with_inactive` | Returns all universities including inactive, with faculty/program counts |
| T2 | `test_admin_cannot_access_campus_admin_endpoints` | `admin` role → 403 on all CRUD endpoints |
| T3 | `test_super_admin_can_create_university` | Creates university with all fields, verifies DB + audit log |
| T4 | `test_create_university_validates_required_fields` | Missing name/code/type → 422 |
| T5 | `test_create_university_rejects_duplicate_code` | Duplicate code → 422 |
| T6 | `test_super_admin_can_update_university` | Updates name/abbreviation, verifies audit log with before/after |
| T7 | `test_super_admin_can_deactivate_university_cascades_children` | Deactivate university → all faculties and study programs become inactive |
| T8 | `test_activate_university_does_not_cascade` | Activate university → children remain inactive |

##### Faculties CRUD

| # | Test | Description |
|---|---|---|
| T9 | `test_super_admin_can_list_faculties_filtered_by_university` | Returns faculties with study program count |
| T10 | `test_super_admin_can_create_faculty` | Creates faculty, verifies university_id FK, audit log |
| T11 | `test_create_faculty_rejects_inactive_university` | Parent university inactive → 422 |
| T12 | `test_create_faculty_rejects_duplicate_code_within_university` | Same code in same university → 422 |
| T13 | `test_same_faculty_code_different_universities_allowed` | Same code in different university → 201 |
| T14 | `test_deactivate_faculty_cascades_to_study_programs` | All child programs become inactive |
| T15 | `test_cannot_activate_faculty_if_university_inactive` | University inactive → 422 |

##### Study Programs CRUD

| # | Test | Description |
|---|---|---|
| T16 | `test_super_admin_can_list_study_programs_filtered` | Filter by university_id and optionally faculty_id |
| T17 | `test_super_admin_can_create_study_program_with_faculty` | Creates program with faculty, verifies relationships |
| T18 | `test_super_admin_can_create_study_program_without_faculty` | Creates program with `faculty_id=null` (Sekolah Tinggi) |
| T19 | `test_create_study_program_validates_faculty_belongs_to_university` | Faculty from wrong university → 422 |
| T20 | `test_create_study_program_validates_degree_level_enum` | Invalid degree_level → 422 |
| T21 | `test_cannot_activate_study_program_if_university_inactive` | → 422 |
| T22 | `test_cannot_activate_study_program_if_faculty_inactive` | Faculty inactive → 422 |

##### RBAC

| # | Test | Description |
|---|---|---|
| T23 | `test_satgas_cannot_access_campus_admin` | `satgas_ppks` → 403 on all CRUD |
| T24 | `test_reporter_cannot_access_campus_admin` | `reporter` → 403 on all CRUD |
| T25 | `test_unauthenticated_cannot_access_campus_admin` | No token → 401 on all CRUD |

##### Audit

| # | Test | Description |
|---|---|---|
| T26 | `test_create_university_creates_audit_log` | Verify audit log entry with correct action and metadata |
| T27 | `test_toggle_active_creates_audit_log_with_before_after` | Verify before/after changes in audit |

### Frontend Tests (Manual + Acceptance)

| # | Test | Description |
|---|---|---|
| T28 | Registration completes end-to-end | Select university → skip faculty → select study program → fill fields → submit → pending |
| T29 | Faculty optional behavior | University with faculties: faculty dropdown shows but is not required. Submit without faculty succeeds |
| T30 | University without faculties | Select Sekolah Tinggi → faculty dropdown hidden → study programs load directly |
| T31 | Cascading dropdown reset | Change university → faculty and study_program reset. Change faculty → study_program reloads |
| T32 | Password show/hide works | Toggle on both password fields shows/hides text |
| T33 | Empty university list | No active universities → message displayed instead of empty dropdown |
| T34 | Master Data page access | Super Admin sees "Master Data" in sidebar. Admin does not |
| T35 | Create university via UI | Create → appears in list. Verify in DB |
| T36 | Deactivate university via UI | Toggle → confirmation → university grayed out. Child faculties/programs also inactive |
| T37 | Create study program without faculty | Select university (Sekolah Tinggi, no faculties) → faculty field hidden → create study program → success |

---

## 7. Risks

| # | Risk | Mitigation | Severity |
|---|---|---|---|
| R1 | Cascade deactivation could affect active registrations referencing now-inactive university/faculty/program | Deactivation only affects future registrations. Existing approved users retain their university_id FK. Registration form validates against `is_active=true` at submission time | 🟡 Medium |
| R2 | Study program dropdown shows wrong programs when faculty is changed | Already handled by React Query cache key `[university_id, faculty_id]` — changing faculty_id triggers a refetch | 🟡 Low |
| R3 | New sidebar item may clutter navigation for Super Admin | Single "Master Data" item with tab sub-navigation keeps sidebar clean | ⚪ Minimal |
| R4 | Existing public endpoints return only active records — no change needed | Public endpoints remain read-only and filter `is_active=true`. Admin list endpoints separately include inactive | ⚪ Minimal |
| R5 | Additional UX issues discovered during implementation | Phase 5 includes a UX polish review pass. Issues can be fixed inline without scope change | 🟡 Low |

---

## 8. Open Questions

**All questions resolved. Open questions = 0.**

| # | Question | Resolution | Frozen Decision |
|---|---|---|---|
| Q1 | Should deactivated entities be deletable? | **No.** Activate/deactivate only. No hard delete. | FD-1 |
| Q2 | Should Admin be able to view campus-admin list endpoints? | **No.** Super Admin only. Admin uses public active-only endpoints. | FD-2 |
| Q3 | Should the correction page apply the same registration fixes? | **Yes.** Same cascading dropdown and password toggle treatment. | Included in Phase 5 |

---

## 9. Acceptance Criteria

| # | Criterion | Verification |
|---|---|---|
| AC1 | Super Admin can create a university via API | T3 |
| AC2 | Super Admin can create faculties and study programs under a university | T10, T17, T18 |
| AC3 | Admin, Satgas, Reporter cannot access campus-admin endpoints | T2, T23, T24 |
| AC4 | Deactivating a university cascades to children | T7 |
| AC5 | Activating a university does NOT cascade | T8 |
| AC6 | Cannot activate child if parent is inactive | T15, T21, T22 |
| AC7 | All CRUD operations produce audit logs | T26, T27 |
| AC8 | Registration completes end-to-end with faculty skipped | T28, T29 |
| AC9 | University without faculties hides faculty dropdown | T30 |
| AC10 | Password show/hide toggle works on all password fields | T32 |
| AC11 | Master Data sidebar item visible to Super Admin only | T34 |
| AC12 | Campus Master Data management pages (list/create/edit/toggle) work for all 3 entity types | T35, T36, T37 |
| AC13 | All existing tests continue to pass | `php artisan test` |
| AC14 | Public read-only endpoints remain unchanged and functional | T28 (uses public endpoints for cascading dropdowns) |
| AC15 | Super Admin creates a new active university/faculty/study_program → it is **immediately available** on the public registration page without additional configuration or deployment | Manual: create via campus-admin → open /register → verify new record appears in dropdown |

---

## 10. Verification Plan

### Automated Tests

```bash
cd backend/api
php artisan test --filter=CampusMasterDataCrud
php artisan test
```

### Manual Verification

```bash
# 1. Seed fresh database
php artisan migrate:fresh --seed

# 2. Login as super_admin
# 3. Navigate to /dashboard/master-data/universities
# 4. Create a new university
# 5. Create faculties under it
# 6. Create study programs under faculties
# 7. Deactivate the university → verify cascade
# 8. Navigate to /register
# 9. Select the remaining active university
# 10. Skip faculty, select study program, fill all fields
# 11. Submit registration → verify success
# 12. Verify password show/hide on all password fields
```

---

## 11. File Summary

### New Files (14)

| File | Type | Layer |
|---|---|---|
| `app/Services/CampusMasterDataService.php` | Service | Backend |
| `app/Policies/CampusMasterDataPolicy.php` | Policy | Backend |
| `app/Http/Requests/StoreUniversityRequest.php` | Form Request | Backend |
| `app/Http/Requests/UpdateUniversityRequest.php` | Form Request | Backend |
| `app/Http/Requests/StoreFacultyRequest.php` | Form Request | Backend |
| `app/Http/Requests/UpdateFacultyRequest.php` | Form Request | Backend |
| `app/Http/Requests/StoreStudyProgramRequest.php` | Form Request | Backend |
| `app/Http/Requests/UpdateStudyProgramRequest.php` | Form Request | Backend |
| `tests/Feature/CampusMasterDataCrudTest.php` | Test | Backend |
| `frontend/src/lib/campus-admin-api.ts` | API Client | Frontend |
| `frontend/src/components/ui/password-input.tsx` | Component | Frontend |
| `frontend/src/components/admin/campus-master-data-dialog.tsx` | Component | Frontend |
| `frontend/src/routes/dashboard.master-data.tsx` | Route (layout) | Frontend |
| `frontend/src/routes/dashboard.master-data.universities.tsx` | Route (page) | Frontend |

> [!NOTE]
> Faculty and Study Program pages may be implemented as separate route files (`dashboard.master-data.faculties.tsx`, `dashboard.master-data.study-programs.tsx`) or as tab panels within the layout route. Final decision during implementation.

### Modified Files (10)

| File | Change | Layer |
|---|---|---|
| [CampusMasterDataController.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Http/Controllers/Api/V1/CampusMasterDataController.php) | Add 12 CRUD methods | Backend |
| [api.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/routes/api.php) | Add `campus-admin` route group | Backend |
| [AuditAction.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Enums/AuditAction.php) | Add 12 new audit action cases | Backend |
| [AuthServiceProvider.php or equivalent] | Register CampusMasterDataPolicy | Backend |
| [dashboard-layout.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/layouts/dashboard-layout.tsx) | Add "Master Data" nav item for super_admin | Frontend |
| [register.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/register.tsx) | Faculty optional indicator, password toggle, dropdown UX | Frontend |
| [registration.correction.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/registration.correction.tsx) | Same fixes as register.tsx (3 password fields) | Frontend |
| [login.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/login.tsx) | Password show/hide toggle (1 field) | Frontend |
| [portal.account.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/portal.account.tsx) | Password show/hide toggle on Change Password section (3 fields) | Frontend |
| [dashboard.users.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.users.tsx) | Password show/hide toggle on Manual Reporter Creation dialog (1 field) | Frontend |

**Total: ~14 new files, ~10 modified files**
