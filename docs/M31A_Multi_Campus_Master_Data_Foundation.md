# M31A — Multi-Campus Master Data Foundation

> **Phase**: M31A | **Type**: Backend-only | **Status**: PLANNING
> **Prerequisite for**: M31B (Reporter Portal & Registration)
> **References**: [MASTER_DATA_GAP_ANALYSIS.md](file:///C:/Users/FARHAN/.gemini/antigravity-ide/brain/31934c07-2e76-469e-8b1d-ff585943887a/MASTER_DATA_GAP_ANALYSIS.md), [MULTI_CAMPUS_GOVERNANCE_REVIEW.md](file:///C:/Users/FARHAN/.gemini/antigravity-ide/brain/31934c07-2e76-469e-8b1d-ff585943887a/MULTI_CAMPUS_GOVERNANCE_REVIEW.md), [M31_READINESS_ASSESSMENT.md](file:///C:/Users/FARHAN/.gemini/antigravity-ide/brain/31934c07-2e76-469e-8b1d-ff585943887a/M31_READINESS_ASSESSMENT.md)

---

## 1. Objectives

Establish the multi-campus data foundation so that M31B (Reporter Portal & Registration) can build on structured, relational university/faculty/study program data instead of free-text fields.

**In scope:**
1. Create `universities` master data table, model, seeder
2. Create `faculties` master data table, model, seeder
3. Create `study_programs` master data table, model, seeder
4. Add `university_id` FK to `users` table
5. Add `university_id`, `faculty_id`, `study_program_id` to `reporter_registrations`
6. Change NIM uniqueness from global `UNIQUE(nim)` to university-scoped `UNIQUE(university_id, nim)`
7. Add 3 public read-only API endpoints for universities, faculties, study programs
8. Fix rejected registration flow — retain `password_hash` on rejection
9. Seed initial participating university data
10. Tests

**Explicitly out of scope:**
- ❌ Portal registration UI / frontend
- ❌ Reporter management UI
- ❌ Full campus-scoped admin policies (deferred to M31B)
- ❌ Alumni / transfer / suspended lifecycle statuses
- ❌ University onboarding automation
- ❌ SMTP / OTP flows
- ❌ Any frontend work
- ❌ Pending/rejected limited-access UI (deferred to M31B)

---

## 1B. Frozen Decisions

### FD-1: Pending / Rejected Registration Login Behavior

The following behavior is **agreed and frozen** for M31B implementation. M31A prepares the backend foundations (password retention, registration model) so M31B can implement the UI and access control.

| Applicant State | Login Behavior | Portal Access | Actions Allowed |
|---|---|---|---|
| **Pending** | ✅ Can authenticate using registration credentials | ❌ No full reporter portal | View message: *"Your registration is still under admin review."* |
| **Rejected** | ✅ Can authenticate using retained password | ❌ No full reporter portal | View rejection reason, edit allowed fields, resubmit corrected registration |
| **Approved** | ✅ Normal login as `reporter` user | ✅ Full reporter portal | Submit reports, track cases, messaging |

**Key rules:**
1. Pending applicants **must not** receive a generic "invalid credentials" login error. The system must recognize them and display a specific pending-review message.
2. Pending applicants **must not** access the full reporter portal or submit reports.
3. Rejected applicants **must not** need to re-create an account or re-enter a password. The existing `password_hash` is retained on the `reporter_registrations` record.
4. Rejected applicants enter a limited correction state where they can view the `rejection_reason`, edit correctable fields (name, NIM, phone_number, and future M31B fields like university/faculty/study_program), and resubmit.
5. Resubmission resets status to `pending` and notifies admin for re-review.
6. Approved applicants use normal Sanctum token auth and access the full reporter portal.

**M31A responsibility:**
- ✅ Retain `password_hash` on rejection (Phase 5a of this plan)
- ✅ Ensure `reporter_registrations` model has the fields needed for correction/resubmission
- ❌ Login endpoint changes, limited-access middleware, and UI are **deferred to M31B**

> [!IMPORTANT]
> **M31B must implement:** A login pathway that checks `reporter_registrations` for pending/rejected records when standard `users` login fails, and routes to the appropriate limited-access state. This is NOT in M31A scope.

### FD-2: NIM Duplicate Check Scope Transition

The NIM duplicate check transition follows a two-phase approach:

| Phase | DB Constraint | Service-Level Check | Status |
|---|---|---|---|
| **M31A** (this milestone) | `UNIQUE(university_id, nim)` — relaxed to allow same NIM across universities | **Global** — `ensureNoActiveUserDuplicate` and `ensureNoPendingRegistrationDuplicate` check NIM globally | Safe because no registration yet supplies `university_id` |
| **M31B** (next milestone) | Same | **University-scoped** — duplicate checks must filter by `university_id` | Required before public registration goes live |

> [!WARNING]
> **M31B gate:** Public registration **must not** go live until the service-level duplicate checks in [ReporterRegistrationService.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Services/ReporterRegistrationService.php) are updated to scope NIM/email uniqueness by `university_id`. The global check in M31A is a transitional state only.

---

## 2. Proposed Changes

### Phase 1 — New Master Data Tables (Migration + Models)

#### [NEW] `2026_06_24_010000_create_campus_master_data_tables.php`

A single migration file creating all 3 new tables, following the established pattern in [create_master_data_tables.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/database/migrations/2026_06_10_070000_create_master_data_tables.php).

##### `universities` table

| Column | Type | Constraint | Notes |
|---|---|---|---|
| `id` | `bigint` | PK, auto-increment | — |
| `code` | `varchar(20)` | UNIQUE, NOT NULL | e.g. `UNJ`, `UI`, `UGM` |
| `name` | `varchar(255)` | NOT NULL | Full name |
| `abbreviation` | `varchar(30)` | NULLABLE | Short name |
| `address` | `text` | NULLABLE | Campus address |
| `website` | `varchar(255)` | NULLABLE | Official website URL |
| `email` | `varchar(255)` | NULLABLE | Official email |
| `hotline` | `varchar(30)` | NULLABLE | Hotline number |
| `type` | `varchar(20)` | NOT NULL, DEFAULT `universitas` | `universitas`, `institut`, `sekolah_tinggi`, `politeknik`, `akademi` |
| `has_faculties` | `boolean` | NOT NULL, DEFAULT `true` | `false` for Sekolah Tinggi / Politeknik / Akademi |
| `is_active` | `boolean` | NOT NULL, DEFAULT `true` | — |
| `sort_order` | `integer` | NOT NULL, DEFAULT `0` | Display order |
| `created_at` | `timestamp` | — | — |
| `updated_at` | `timestamp` | — | — |

> **Design decision:** `universities` does NOT extend `MasterData` base model because it has more fields than the standard `code/name/description/is_active/sort_order` pattern. It uses its own Eloquent model with a similar interface.

##### `faculties` table

| Column | Type | Constraint | Notes |
|---|---|---|---|
| `id` | `bigint` | PK, auto-increment | — |
| `university_id` | `bigint` | FK → `universities.id`, NOT NULL | Parent university |
| `code` | `varchar(20)` | NOT NULL | Faculty code (unique within university) |
| `name` | `varchar(255)` | NOT NULL | Faculty name |
| `is_active` | `boolean` | NOT NULL, DEFAULT `true` | — |
| `sort_order` | `integer` | NOT NULL, DEFAULT `0` | — |
| `created_at` | `timestamp` | — | — |
| `updated_at` | `timestamp` | — | — |

Indexes:
- `UNIQUE(university_id, code)` — faculty code unique within university
- `INDEX(university_id)` — FK lookup

##### `study_programs` table

| Column | Type | Constraint | Notes |
|---|---|---|---|
| `id` | `bigint` | PK, auto-increment | — |
| `university_id` | `bigint` | FK → `universities.id`, NOT NULL | Parent university |
| `faculty_id` | `bigint` | FK → `faculties.id`, NULLABLE | Null for Sekolah Tinggi (no faculties) |
| `code` | `varchar(20)` | NOT NULL | Program code (unique within university) |
| `name` | `varchar(255)` | NOT NULL | Program name |
| `degree_level` | `varchar(10)` | NOT NULL, DEFAULT `S1` | `D3`, `D4`, `S1`, `S2`, `S3`, `profesi` |
| `is_active` | `boolean` | NOT NULL, DEFAULT `true` | — |
| `sort_order` | `integer` | NOT NULL, DEFAULT `0` | — |
| `created_at` | `timestamp` | — | — |
| `updated_at` | `timestamp` | — | — |

Indexes:
- `UNIQUE(university_id, code)` — program code unique within university
- `INDEX(university_id)` — FK + filter
- `INDEX(faculty_id)` — FK + filter
- `INDEX(university_id, faculty_id)` — cascading dropdown query

---

### Phase 2 — Modify Existing Tables (Migration)

#### [NEW] `2026_06_24_020000_add_university_columns_to_users_and_registrations.php`

##### Changes to `users` table

| Change | Detail |
|---|---|
| Add column | `university_id` — `bigint`, FK → `universities.id`, **NULLABLE** |
| Drop unique | Drop global `UNIQUE` on `nim` |
| Add unique | Add composite `UNIQUE(university_id, nim)` — uses partial/filtered index: only where both are NOT NULL |

> **Why `university_id` is nullable on `users`:** Existing admin/super_admin/satgas_ppks accounts predate the university system. They will be assigned a `university_id` via the seeder or manually. New reporter accounts created via M31B approval will always have `university_id` set. Making it nullable avoids a breaking migration on existing data.

##### Changes to `reporter_registrations` table

| Change | Detail |
|---|---|
| Add column | `university_id` — `bigint`, FK → `universities.id`, **NULLABLE** |
| Add column | `faculty_id` — `bigint`, FK → `faculties.id`, **NULLABLE** |
| Add column | `study_program_id` — `bigint`, FK → `study_programs.id`, **NULLABLE** |

> **Why nullable:** Existing registration records predate the university system. New registrations (M31B) will enforce these as required at the validation layer, not the DB layer. This follows the same pattern as `users.university_id`.

---

### Phase 3 — Models

#### [NEW] `app/Models/University.php`

```php
class University extends Model
{
    protected $fillable = [
        'code', 'name', 'abbreviation', 'address', 'website',
        'email', 'hotline', 'type', 'has_faculties',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'has_faculties' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // Relations: faculties(), studyPrograms(), users()
}
```

#### [NEW] `app/Models/Faculty.php`

```php
class Faculty extends Model
{
    protected $fillable = [
        'university_id', 'code', 'name', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // Relations: university(), studyPrograms()
}
```

#### [NEW] `app/Models/StudyProgram.php`

```php
class StudyProgram extends Model
{
    protected $table = 'study_programs';

    protected $fillable = [
        'university_id', 'faculty_id', 'code', 'name',
        'degree_level', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // Relations: university(), faculty()
}
```

#### [MODIFY] [User.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Models/User.php)

- Add `'university_id'` to `$fillable`
- Add `university()` BelongsTo relationship

#### [MODIFY] [ReporterRegistration.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Models/ReporterRegistration.php)

- Add `'university_id'`, `'faculty_id'`, `'study_program_id'` to `$fillable`
- Add `university()`, `faculty()`, `studyProgram()` BelongsTo relationships

---

### Phase 4 — API Endpoints

#### [NEW] `app/Http/Controllers/Api/V1/CampusMasterDataController.php`

A dedicated controller for the 3 new public endpoints. Separate from the existing [MasterDataController.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Http/Controllers/Api/V1/MasterDataController.php) because:
- These endpoints are **public** (no `auth:sanctum`), unlike existing master data endpoints
- They have query parameter filtering (`?university_id=`, `?faculty_id=`)
- The response shape includes relational context, not just flat `code/name/description`

##### Endpoint 1: `GET /api/v1/universities`

| Aspect | Detail |
|---|---|
| Auth | **None** (public) — needed for registration form before login |
| Rate limit | `throttle:30,1` |
| Query params | `?include_inactive=true` (admin/super_admin only, requires `auth:sanctum`) |
| Response | List of active universities |
| Sort | `sort_order ASC, name ASC` |

Response shape:
```json
{
  "success": true,
  "message": "Data retrieved successfully",
  "data": [
    {
      "id": 1,
      "code": "DEMO-UNIV",
      "name": "Universitas Demo SILAPPKASAL",
      "abbreviation": "UNIV-DEMO",
      "type": "universitas",
      "has_faculties": true,
      "website": "https://demo.ac.id"
    }
  ]
}
```

> **Design decision:** Response excludes `address`, `email`, `hotline` for public endpoint. These are campus admin details, not public info. Only `id`, `code`, `name`, `abbreviation`, `type`, `has_faculties`, `website` are returned publicly.

##### Endpoint 2: `GET /api/v1/faculties`

| Aspect | Detail |
|---|---|
| Auth | **None** (public) |
| Rate limit | `throttle:30,1` |
| Query params | `university_id` (**required**) |
| Validation | `university_id` must exist in `universities` and be active |
| Response | List of active faculties for the university |
| Sort | `sort_order ASC, name ASC` |

Response shape:
```json
{
  "success": true,
  "message": "Data retrieved successfully",
  "data": [
    {
      "id": 1,
      "code": "FT",
      "name": "Fakultas Teknik",
      "university_id": 1
    }
  ]
}
```

##### Endpoint 3: `GET /api/v1/study-programs`

| Aspect | Detail |
|---|---|
| Auth | **None** (public) |
| Rate limit | `throttle:30,1` |
| Query params | `university_id` (**required**), `faculty_id` (optional) |
| Validation | `university_id` must exist in `universities` and be active. `faculty_id` (if provided) must belong to the `university_id`. |
| Response | List of active study programs, filtered |
| Sort | `sort_order ASC, name ASC` |

Response shape:
```json
{
  "success": true,
  "message": "Data retrieved successfully",
  "data": [
    {
      "id": 1,
      "code": "TI",
      "name": "Teknik Informatika",
      "degree_level": "S1",
      "university_id": 1,
      "faculty_id": 1
    }
  ]
}
```

#### [NEW] `app/Http/Requests/FacultyIndexRequest.php`

Validates `university_id` is required, integer, exists in `universities`.

#### [NEW] `app/Http/Requests/StudyProgramIndexRequest.php`

Validates `university_id` is required. `faculty_id` is optional but if provided, must belong to the specified university.

#### [MODIFY] [api.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/routes/api.php)

Add 3 new public routes (no `auth:sanctum` middleware):

```php
Route::prefix('universities')->group(function (): void {
    Route::get('/', [CampusMasterDataController::class, 'universities'])
        ->middleware('throttle:30,1');
});

Route::prefix('faculties')->group(function (): void {
    Route::get('/', [CampusMasterDataController::class, 'faculties'])
        ->middleware('throttle:30,1');
});

Route::prefix('study-programs')->group(function (): void {
    Route::get('/', [CampusMasterDataController::class, 'studyPrograms'])
        ->middleware('throttle:30,1');
});
```

---

### Phase 5 — Fix Rejection Flow + Validation Changes

#### [MODIFY] [ReporterRegistrationService.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Services/ReporterRegistrationService.php)

##### 5a. Rejection fix — retain `password_hash`

Current code at [line 145](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Services/ReporterRegistrationService.php#L140-L146):
```diff
  $registration->forceFill([
      'status' => ReporterRegistrationStatus::Rejected,
      'reviewed_by' => $actor->id,
      'reviewed_at' => now(),
      'rejection_reason' => $data['rejection_reason'],
-     'password_hash' => null,
  ])->save();
```

Remove `'password_hash' => null` from the `reject()` method. The password hash is retained so that M31B can implement correction/resubmission without requiring the reporter to re-enter a password.

> **Note:** The `approve()` method at [line 104](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Services/ReporterRegistrationService.php#L99-L106) correctly clears `password_hash` after creating the user — this behavior stays unchanged.

##### 5b. NIM uniqueness scope update

Current duplicate check methods [ensureNoActiveUserDuplicate](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Services/ReporterRegistrationService.php#L174-L187) and [ensureNoPendingRegistrationDuplicate](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Services/ReporterRegistrationService.php#L189-L202) check NIM globally.

**M31A change:** No modification to these methods yet. The service-level duplicate checks remain global because M31A does not add `university_id` as a required registration field (that's M31B). The DB-level unique constraint change from `UNIQUE(nim)` to `UNIQUE(university_id, nim)` is sufficient for M31A — it relaxes the constraint to allow the same NIM across different universities.

> [!IMPORTANT]
> In M31B, when `university_id` becomes a required registration field, the duplicate check methods must be updated to scope by `university_id`. For M31A, the global check remains safe because no registration yet supplies `university_id`.

#### [MODIFY] [ReporterRegistrationStoreRequest.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Http/Requests/ReporterRegistrationStoreRequest.php)

No changes in M31A. The `university_id`, `faculty_id`, `study_program_id` fields become required/optional validation rules in M31B when the registration endpoint is updated.

---

### Phase 6 — Seeders

#### [NEW] `database/seeders/CampusMasterDataSeeder.php`

Seeds initial university, faculty, and study program data. Uses `updateOrCreate` (matching existing [MasterDataSeeder.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/database/seeders/MasterDataSeeder.php) pattern) for idempotent re-runs.

**Initial seed data — Demo University:**

Since the university document ([MASTER_DATA_SILAPPKASAL.md](file:///d:/PROJECT%20CODING/SILAPPKASAL/docs/MASTER_DATA_SILAPPKASAL.md)) does not provide specific university names, we seed a **demo university** with representative faculties and study programs that exercises all structural variations:

```
University: Universitas Demo SILAPPKASAL
├── Code: DEMO-UNIV
├── Type: universitas
├── has_faculties: true
├── Faculties:
│   ├── FT  — Fakultas Teknik
│   │   ├── TI  — Teknik Informatika (S1)
│   │   └── SI  — Sistem Informasi (S1)
│   ├── FMIPA — Fakultas MIPA
│   │   ├── MAT — Matematika (S1)
│   │   └── FIS — Fisika (S1)
│   └── FH  — Fakultas Hukum
│       └── HKM — Ilmu Hukum (S1)

University: Sekolah Tinggi Demo SILAPPKASAL
├── Code: DEMO-ST
├── Type: sekolah_tinggi
├── has_faculties: false
├── Study Programs (no faculty):
│   ├── MI  — Manajemen Informatika (D3)
│   └── TK  — Teknik Komputer (D3)
```

This exercises:
- ✅ University with faculties (`universitas`)
- ✅ University without faculties (`sekolah_tinggi`)
- ✅ Multiple faculties with study programs
- ✅ Study programs directly under university (no `faculty_id`)
- ✅ Different degree levels (`S1`, `D3`)

#### [MODIFY] [DatabaseSeeder.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/database/seeders/DatabaseSeeder.php)

Add `CampusMasterDataSeeder::class` to the seeder call chain, after `MasterDataSeeder`.

#### [MODIFY] [DemoDataSeeder.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/database/seeders/DemoDataSeeder.php)

Assign `university_id` to demo users created by the seeder. The demo super_admin, admin, and satgas_ppks users should be linked to the demo university.

---

## 3. Open Questions

> [!IMPORTANT]
> **Q1: Real university data or demo-only?**
> The university document does not list specific participating universities. Should M31A seed only demo data, or do you have real university names/codes/faculties to include? The plan currently seeds 2 demo universities.

> [!IMPORTANT]
> **Q2: Should existing users be backfilled?**
> The migration adds `university_id` as nullable to `users`. Should the DemoDataSeeder backfill existing demo users with the demo university ID? The current plan does this. If production data exists, a separate data migration script would be needed.

> [!NOTE]
> **Q3: `university_id` on `users` — should it become NOT NULL eventually?**
> For M31A it stays nullable (backward compatibility). In a future milestone, after all users have been assigned a university, the constraint could be tightened. This is a post-M31B decision.

---

## 4. Test Plan

#### [NEW] `tests/Feature/CampusMasterDataSeederTest.php`

| # | Test | Description |
|---|---|---|
| T1 | `test_seeder_creates_universities` | Verify 2 universities seeded with correct codes/types |
| T2 | `test_seeder_creates_faculties_for_universitas` | Verify 3 faculties under DEMO-UNIV |
| T3 | `test_seeder_creates_no_faculties_for_sekolah_tinggi` | Verify 0 faculties under DEMO-ST |
| T4 | `test_seeder_creates_study_programs_with_faculty` | Verify study programs linked to faculties |
| T5 | `test_seeder_creates_study_programs_without_faculty` | Verify study programs under DEMO-ST have `faculty_id = null` |
| T6 | `test_seeder_is_idempotent` | Run seeder twice, verify no duplicates |

#### [NEW] `tests/Feature/CampusMasterDataEndpointsTest.php`

| # | Test | Description |
|---|---|---|
| T7 | `test_universities_endpoint_is_public` | `GET /api/v1/universities` returns 200 without auth |
| T8 | `test_universities_returns_only_active` | Deactivate one university, verify it's excluded |
| T9 | `test_universities_response_excludes_sensitive_fields` | Response does not include `address`, `email`, `hotline` |
| T10 | `test_faculties_requires_university_id` | `GET /api/v1/faculties` without `university_id` → 422 |
| T11 | `test_faculties_returns_filtered_by_university` | Returns only faculties for specified university |
| T12 | `test_faculties_returns_empty_for_sekolah_tinggi` | DEMO-ST has no faculties → empty array |
| T13 | `test_study_programs_requires_university_id` | `GET /api/v1/study-programs` without `university_id` → 422 |
| T14 | `test_study_programs_returns_filtered_by_university` | Returns all study programs for university |
| T15 | `test_study_programs_filters_by_faculty_id` | With `faculty_id` → returns only that faculty's programs |
| T16 | `test_study_programs_validates_faculty_belongs_to_university` | `faculty_id` from wrong university → 422 |
| T17 | `test_universities_endpoint_is_rate_limited` | 31+ requests in 1 minute → 429 |

#### [MODIFY] [ReporterRegistrationFoundationTest.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/tests/Feature/ReporterRegistrationFoundationTest.php)

| # | Test | Description |
|---|---|---|
| T18 | `test_admin_can_reject_registration_and_password_hash_is_retained` | **Modify existing test** — change assertion from `assertNull($registration->password_hash)` to `assertNotNull($registration->password_hash)`. Verify password hash is retained on rejection. |

#### [NEW] `tests/Feature/CampusMasterDataModelTest.php`

| # | Test | Description |
|---|---|---|
| T19 | `test_university_has_many_faculties` | Verify `University::faculties()` relationship |
| T20 | `test_university_has_many_study_programs` | Verify `University::studyPrograms()` relationship |
| T21 | `test_faculty_belongs_to_university` | Verify `Faculty::university()` relationship |
| T22 | `test_study_program_belongs_to_university_and_faculty` | Verify both relationships |
| T23 | `test_user_belongs_to_university` | Verify `User::university()` relationship |
| T24 | `test_reporter_registration_belongs_to_university_faculty_study_program` | Verify 3 new relationships |
| T25 | `test_nim_uniqueness_is_scoped_by_university` | Same NIM in 2 different universities → no constraint violation |
| T26 | `test_nim_uniqueness_within_same_university` | Same NIM in same university → constraint violation |
| T27 | `test_faculty_code_uniqueness_within_university` | Same faculty code in 2 different universities → OK. Same code in same university → violation |

---

## 5. Risks

| # | Risk | Mitigation | Severity |
|---|---|---|---|
| R1 | Existing tests that create users without `university_id` may fail after migration | Migration adds `university_id` as **nullable** — no existing test should break | 🟡 Low |
| R2 | NIM unique constraint change could fail if duplicate NIM+NULL university exists | The migration drops the old `UNIQUE(nim)` and adds `UNIQUE(university_id, nim)`. PostgreSQL unique constraints ignore rows where any column is NULL, so existing users with `university_id = NULL` will not conflict | 🟡 Low |
| R3 | Demo seeder data does not match real universities | Demo data is explicitly labeled — real data can be added in M31B or via a separate seeder | ⚪ Minimal |
| R4 | Public API endpoints could be abused for scraping | Rate limiting (`throttle:30,1`) applied to all 3 endpoints | 🟡 Low |
| R5 | Rejection flow change could affect M19 test assertions | T18 explicitly updates the existing test to match the new behavior | 🟡 Low |
| R6 | NIM/email duplicate checks remain **global** in M31A while DB constraint is already university-scoped — a registrant could pass service-level checks but hit a DB constraint (or vice versa after M31B relaxes checks) | Acceptable transitional state: no registration supplies `university_id` in M31A, so global checks are equivalent. **M31B must update checks to university-scoped before public registration goes live** (see FD-2 and AC13). | 🟡 Medium |

---

## 6. Acceptance Criteria

| # | Criterion | Verification |
|---|---|---|
| AC1 | `php artisan migrate` runs without error | Manual |
| AC2 | `php artisan db:seed` creates 2 universities, 3 faculties, 7 study programs | T1–T6 |
| AC3 | `GET /api/v1/universities` returns active universities without auth | T7, T8 |
| AC4 | `GET /api/v1/faculties?university_id=X` returns filtered faculties | T10–T12 |
| AC5 | `GET /api/v1/study-programs?university_id=X` returns filtered programs | T13–T16 |
| AC6 | `users.university_id` column exists and is nullable | T23, migration |
| AC7 | `reporter_registrations` has `university_id`, `faculty_id`, `study_program_id` columns | T24, migration |
| AC8 | NIM uniqueness is university-scoped (same NIM, different universities → OK) | T25, T26 |
| AC9 | Rejected registration retains `password_hash` | T18 |
| AC10 | All existing tests pass without modification (except T18) | `php artisan test` |
| AC11 | Public endpoints exclude sensitive fields (`address`, `email`, `hotline`) | T9 |
| AC12 | Public endpoints are rate-limited | T17 |
| AC13 | **M31B gate:** Duplicate NIM/email checks in `ReporterRegistrationService` must be updated to university-scoped before M31B public registration goes live | M31B verification (documented here as forward commitment) |
| AC14 | **M31B gate:** Pending/rejected registration login pathway and limited-access UI must be implemented before M31B public registration goes live | M31B verification (documented here as forward commitment) |

---

## 7. Verification Plan

### Automated Tests

```bash
cd backend/api
php artisan test --filter=CampusMasterData
php artisan test --filter=ReporterRegistrationFoundationTest
php artisan test
```

### Manual Verification

```bash
php artisan migrate:fresh --seed
# Verify: 2 universities, 3 faculties, 7 study programs in DB
# Verify: Demo users have university_id set

# Test public endpoints:
curl http://localhost:8000/api/v1/universities
curl "http://localhost:8000/api/v1/faculties?university_id=1"
curl "http://localhost:8000/api/v1/study-programs?university_id=1"
curl "http://localhost:8000/api/v1/study-programs?university_id=1&faculty_id=1"
```

---

## 8. File Summary

### New Files (8)

| File | Type |
|---|---|
| `database/migrations/2026_06_24_010000_create_campus_master_data_tables.php` | Migration |
| `database/migrations/2026_06_24_020000_add_university_columns_to_users_and_registrations.php` | Migration |
| `app/Models/University.php` | Model |
| `app/Models/Faculty.php` | Model |
| `app/Models/StudyProgram.php` | Model |
| `app/Http/Controllers/Api/V1/CampusMasterDataController.php` | Controller |
| `database/seeders/CampusMasterDataSeeder.php` | Seeder |
| `tests/Feature/CampusMasterDataSeederTest.php` | Test |

### New Files (continued, 4)

| File | Type |
|---|---|
| `tests/Feature/CampusMasterDataEndpointsTest.php` | Test |
| `tests/Feature/CampusMasterDataModelTest.php` | Test |
| `app/Http/Requests/FacultyIndexRequest.php` | Form Request |
| `app/Http/Requests/StudyProgramIndexRequest.php` | Form Request |

### Modified Files (6)

| File | Change |
|---|---|
| [User.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Models/User.php) | Add `university_id` to fillable, add `university()` relation |
| [ReporterRegistration.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Models/ReporterRegistration.php) | Add 3 new FK fields to fillable, add 3 relations |
| [ReporterRegistrationService.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/app/Services/ReporterRegistrationService.php) | Remove `password_hash => null` from `reject()` |
| [api.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/routes/api.php) | Add 3 new public routes |
| [DatabaseSeeder.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/database/seeders/DatabaseSeeder.php) | Add CampusMasterDataSeeder call |
| [ReporterRegistrationFoundationTest.php](file:///d:/PROJECT%20CODING/SILAPPKASAL/backend/api/tests/Feature/ReporterRegistrationFoundationTest.php) | Update rejection test assertion |

**Total: 12 new files, 6 modified files**
