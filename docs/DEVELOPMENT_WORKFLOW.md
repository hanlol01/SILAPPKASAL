# DEVELOPMENT_WORKFLOW.md — Development Workflow & Standards

> **Sistem Informasi Laporan Pencegahan dan Penanganan Kekerasan Seksual (SILAPPKASAL)**
> Versi: 1.0.1-patch | Terakhir Diperbarui: 2026-06-10 | Status: BERLAKU — AUDIT PATCH | Tier: 2 (GOVERNED)

---

## Daftar Isi

1. [Development Principles](#1-development-principles)
2. [Project Structure](#2-project-structure)
3. [Agent Responsibilities](#3-agent-responsibilities)
4. [Backend Development Workflow](#4-backend-development-workflow)
5. [Web Development Workflow](#5-web-development-workflow)
6. [Mobile Development Workflow](#6-mobile-development-workflow)
7. [Branching & Git Workflow](#7-branching--git-workflow)
8. [Environment Setup](#8-environment-setup)
9. [Testing Strategy](#9-testing-strategy)
10. [Security Checklist Before Merge](#10-security-checklist-before-merge)
11. [Definition of Done](#11-definition-of-done)
12. [Phase 4 Preparation](#12-phase-4-preparation)

---

## 1. Development Principles

### 1.1 Prinsip Utama

| # | Prinsip | Detail |
|---|---------|--------|
| 1 | **Backend First** | Backend API dibangun dan diuji terlebih dahulu. Frontend mengikuti setelah API stabil. |
| 2 | **API Contract First** | `API_SPECIFICATION.md` menjadi kontrak. Frontend dan backend berkembang berdasarkan kontrak ini. |
| 3 | **Frontend Tidak Rewrite Total** | Frontend React dari Lovable sudah ada. Integrasikan secara bertahap — ganti mock data ke API. |
| 4 | **Mobile Ditunda** | Flutter mobile app dikerjakan setelah API stabil dan teruji. |
| 5 | **Security First** | Setiap fitur harus melewati security checklist sebelum merge. |
| 6 | **Small Commits** | Commit kecil dan deskriptif. Satu fitur per branch. |
| 7 | **Document Every Change** | Setiap perubahan signifikan harus didokumentasikan di `BUILD_NOTES.md` atau docs terkait. |
| 8 | **No Premature Optimization** | Fokus pada fungsionalitas yang benar terlebih dahulu, optimasi setelah fitur stabil. |
| 9 | **Immutable Docs Phase 1 & 2** | Dokumen Phase 1 dan Phase 2 sudah PASS. Tidak boleh diubah kecuali kontradiksi kritis yang disetujui. |
| 10 | **Local Storage Default** | Development menggunakan local private storage. S3-compatible hanya opsional untuk future/production. |

### 1.2 Urutan Prioritas Pengembangan

```
Prioritas Pengembangan:

1. Backend Foundation (Laravel setup, Sanctum, DB)
2. Backend Auth (login, logout, me, register)
3. Backend RBAC (roles, permissions, policies)
4. Backend Master Data (seeders, endpoints)
5. Backend Report Workflow (CRUD, status transitions)
6. Backend Case Workflow (7 tahap penanganan)
7. Backend Evidence (upload, download, storage)
8. Backend Queue + Notifications (Fonnte WA)
9. Backend Audit Logs
10. Frontend Integration — Auth
11. Frontend Integration — Dashboard
12. Frontend Integration — Report/Case workflow
13. Frontend Integration — Evidence
14. Testing & Security Review
15. Mobile (Post-MVP)
```

### 1.3 Shared Article Editor Guardrails

- Article authoring must reuse
  `frontend/src/components/content/article-wysiwyg-editor.tsx`; do not introduce a second rich-text
  editor or a parallel body format.
- `frontend/package.json` and its npm lockfile are the dependency source of truth. New Tiptap
  extensions require a dependency, schema, security, bundle, renderer, and backend-allowlist audit.
- REV-MEDIA-01 permits Article cover and inline JPEG/PNG/WebP only through the authenticated,
  same-version upload API when the server advertises the image capability. Inline PDF, remote image
  URLs, Base64, persistent blob URLs, raw HTML, iframe/video, tables, arbitrary style, collaboration,
  and AI writing remain disabled. FAQ remains media-free.
- `imageReference` must not have an HTML/clipboard parser. A new reference may be inserted only from
  a successful server upload response and stores only `attachment_public_id` plus alt text. Cover
  and PDF controls remain outside the rich-text body.
- Article and FAQ use distinct backend allowlists. Article may use the approved Tiptap marks,
  Article-only blocks, and `tel:` links; FAQ retains its narrower pre-REV-ED-01 block/mark/link
  contract. A shared validator implementation must receive an explicit document-type policy.
- Stored document JSON must follow the backend allowlist in `API_SPECIFICATION.md`. Frontend schema
  restrictions are UX controls and never replace server validation.
- Before legacy Article JSON enters Tiptap, the frontend compatibility adapter must fail closed on
  the backend resource limits: 500,000 serialized bytes, 1,000 nodes, depth 12, 200,000 text
  characters, and the corresponding per-node mark/link limits. Over-limit or unknown documents
  remain read-only and must not be rewritten through save.
- Editor changes require focused Content tests, TypeScript, ESLint, production build, backend
  Content tests when the JSON contract changes, PHP lint, and dependency-tree verification.

---

## 2. Project Structure

### 2.1 Recommended Repository Structure

```
silappkasal/
├── docs/                          ← Project documentation (Phase 1-3+)
│   ├── AGENTS.md                  ← Phase 1: Agent rules (FROZEN)
│   ├── PROJECT_MASTER.md          ← Phase 1: Project blueprint (FROZEN)
│   ├── PRD.md                     ← Phase 1: Requirements (FROZEN)
│   ├── MASTER_DATA.md             ← Phase 2: Master data (PASSED)
│   ├── AUTH_FLOW.md               ← Phase 2: Auth strategy (PASSED)
│   ├── SECURITY_POLICY.md         ← Phase 2: Security rules (PASSED)
│   ├── DATABASE_SCHEMA.md         ← Phase 3: DB design
│   ├── API_SPECIFICATION.md       ← Phase 3: API contract
│   ├── DEVELOPMENT_WORKFLOW.md    ← Phase 3: This document
│   └── BUILD_NOTES.md             ← Phase 4+: Development log
│
├── backend/                       ← Laravel REST API
│   └── api/
│       ├── app/
│       │   ├── Http/
│       │   │   ├── Controllers/
│       │   │   │   └── Api/V1/    ← Versioned API controllers
│       │   │   ├── Middleware/
│       │   │   └── Requests/      ← Form request validation
│       │   ├── Models/
│       │   ├── Policies/          ← Laravel Policies (RBAC)
│       │   ├── Services/          ← Business logic layer
│       │   ├── Enums/             ← PHP enums
│       │   └── Jobs/              ← Queue jobs
│       ├── database/
│       │   ├── migrations/
│       │   ├── seeders/
│       │   └── factories/
│       ├── routes/
│       │   └── api.php            ← API routes (versioned)
│       ├── config/
│       ├── tests/
│       │   ├── Feature/
│       │   └── Unit/
│       ├── .env.example
│       ├── composer.json
│       └── artisan
│
├── apps/
│   └── web-admin/                 ← React + TypeScript frontend (from Lovable)
│       ├── src/
│       │   ├── api/               ← API client layer
│       │   ├── components/
│       │   ├── contexts/          ← Auth context, etc.
│       │   ├── hooks/             ← TanStack Query hooks
│       │   ├── pages/
│       │   ├── types/             ← TypeScript types (matching API)
│       │   └── utils/
│       ├── package.json
│       ├── vite.config.ts
│       └── tsconfig.json
│
├── apps/
│   └── mobile/                    ← Flutter (Phase 5+, NOT NOW)
│       └── (ditunda)
│
├── .gitignore
├── README.md
└── docker-compose.yml             ← Development environment (opsional)
```

### 2.2 Catatan Penting

- **`backend/api/`**: Laravel project baru, dibuat dari scratch. Jangan copy dari template lain.
- **`apps/web-admin/`**: Frontend React yang sudah ada dari Lovable. JANGAN rewrite total. Migrasi bertahap.
- **`apps/mobile/`**: Folder placeholder. Flutter dikerjakan setelah API stabil.
- **`docs/`**: Semua markdown project docs. Phase 1 dan 2 sudah FROZEN/PASSED.

---

## 3. Agent Responsibilities

### 3.1 Backend Agent

| Tanggung Jawab | Detail |
|----------------|--------|
| **Scope** | Laravel REST API, database, queue, notifications |
| **Utama** | Membuat migration, model, controller, policy, service, seeder, test |
| **Referensi Wajib** | `DATABASE_SCHEMA.md`, `API_SPECIFICATION.md`, `SECURITY_POLICY.md`, `MASTER_DATA.md`, `AUTH_FLOW.md` |
| **Output** | API yang lulus semua endpoint tests dan security checklist |
| **Dilarang** | Mengubah frontend, mengubah docs Phase 1/2, skip audit log, skip policy |

### 3.2 Web Agent

| Tanggung Jawab | Detail |
|----------------|--------|
| **Scope** | React + TypeScript frontend (apps/web-admin) |
| **Utama** | Integrasi API, auth context, API client layer, dashboard, workflow UI |
| **Referensi Wajib** | `API_SPECIFICATION.md`, `AUTH_FLOW.md`, `PRD.md` (UI requirements) |
| **Output** | Frontend yang terintegrasi dengan API tanpa mock data |
| **Dilarang** | Rewrite total UI, direct DB access, bypass API, menyimpan token di localStorage |

### 3.3 Mobile Agent

| Tanggung Jawab | Detail |
|----------------|--------|
| **Scope** | Flutter mobile app (apps/mobile) |
| **Status** | **DITUNDA** — tunggu API stabil |
| **Referensi Wajib** | `API_SPECIFICATION.md`, `AUTH_FLOW.md` |
| **Output** | Flutter app yang menggunakan API yang sama |
| **Dilarang** | Mulai sebelum API stabil, membuat API endpoint sendiri |

### 3.4 Reviewer Agent

| Tanggung Jawab | Detail |
|----------------|--------|
| **Scope** | Code review, security audit, documentation review |
| **Utama** | Memastikan kode sesuai standar AGENTS.md, security policy, API spec |
| **Checklist** | Security checklist (Section 10), DoD (Section 11) |
| **Output** | Review report: approved / changes requested |
| **Fokus** | Auth, policy, audit log, data masking, evidence access, rate limiting |

### 3.5 Documentation Agent

| Tanggung Jawab | Detail |
|----------------|--------|
| **Scope** | Dokumentasi teknis, BUILD_NOTES.md, docs updates |
| **Utama** | Menjaga konsistensi dokumentasi, update build notes, changelog |
| **Referensi Wajib** | Semua docs Phase 1-3 |
| **Output** | Dokumentasi yang akurat dan up-to-date |
| **Dilarang** | Mengubah docs Phase 1 (FROZEN), mengubah docs Phase 2 (PASSED) tanpa justifikasi |

---

## 4. Backend Development Workflow

### 4.1 Urutan Kerja Backend

```
Step 1: Setup Laravel Project
├── laravel new backend/api
├── Configure PostgreSQL connection
├── Install Laravel Sanctum
├── Configure CORS
├── Configure rate limiting
└── Setup .env.example

Step 2: Database Foundation
├── Run DATABASE_SCHEMA.md migration order (Phase 1 — Foundation)
├── Create roles table
├── Create permissions table
├── Create role_permissions table
├── Create users table (with Sanctum)
└── Create personal_access_tokens table

Step 3: Master Data Migrations + Seeders
├── Run DATABASE_SCHEMA.md migration order (Phase 2 — Master Data)
├── Create all master data tables
├── Create RoleSeeder (5 roles)
├── Create PermissionSeeder (30+ permissions)
├── Create RolePermissionSeeder (matriks mapping)
├── Create MasterDataSeeder (categories, types, statuses)
├── Create SystemSettingSeeder
└── Create SuperAdminSeeder

Step 4: Auth Endpoints
├── Create AuthController
├── Implement login (identifier-based: email/NIM/NIP)
├── Implement logout / logout-all
├── Implement GET /auth/me
├── Implement register (reporter only)
├── Implement forgot-password / reset-password
├── Setup Sanctum middleware
├── Write auth feature tests
└── Verify: token lifecycle, rate limiting, audit logging

Step 5: User & Role Management
├── Create UserController
├── Create UserPolicy
├── CRUD users (admin/super_admin only)
├── Deactivate user (revoke tokens)
├── Assign role (super_admin only)
├── Write user management tests
└── Verify: RBAC enforcement, audit logging

Step 6: Report Workflow
├── Create ReportController
├── Create ReportPolicy
├── Implement create report (authenticated + anonymous)
├── Implement list/detail reports (role-scoped)
├── Implement verify / reject / request-info / forward
├── Implement tracking by code (anonymous)
├── Auto-generate registration_number dan tracking_code
├── Case creation on forward
├── Write report workflow tests
└── Verify: anonymous privacy, audit logging, status transitions

Step 7: Case Workflow (7 tahap)
├── Create CaseController
├── Create CasePolicy
├── Implement list/detail cases (role-scoped: metadata vs full)
├── Implement assign-satgas
├── Implement risk-assessment
├── Implement investigation + activities
├── Implement recommendations
├── Implement decisions
├── Implement recovery-monitoring
├── Implement close / escalate
├── Status transition validation
├── Write case workflow tests
└── Verify: data isolation, RBAC, audit logging

Step 8: Evidence Management
├── Create EvidenceController
├── Create EvidencePolicy
├── Implement upload (multipart, validated MIME types)
├── Implement download (stream via controller, not public URL)
├── File encryption (if configured)
├── UUID filename, checksum
├── Private storage disk
├── Write evidence tests
└── Verify: no public URL, policy check, Super Admin ≠ auto-access

Step 9: Queue + Notifications
├── Configure queue (database driver)
├── Create SendWhatsAppNotification job
├── Create FonnteService
├── Delivery tracking via notifications.delivery_status, notifications.provider_response
├── Template-based messages (no freeform)
├── Retry policy (3x, exponential backoff)
├── Write notification tests
└── Verify: fail gracefully, no PII in WA messages

Step 10: Audit Logs
├── Create AuditLogController (read-only)
├── Implement audit logging service
├── Data masking for sensitive fields
├── Immutable (no update/delete)
├── Role-scoped access (super_admin full, admin filtered)
├── Write audit log tests
└── Verify: all CRUD operations logged, masking correct

Step 11: Break-Glass Protocol
├── Create BreakGlassController
├── Create BreakGlassPolicy
├── Implement activate / revoke / list
├── Session expiry (4 hours)
├── CRITICAL audit logging
├── Notification to other super admins
├── Write break-glass tests
└── Verify: justification min 50 chars, audit trail

Step 12: Dashboard Endpoints
├── Create DashboardController
├── Admin dashboard aggregations
├── Satgas dashboard (my cases)
├── Reporter dashboard (my reports)
├── SLA alerts
└── Write dashboard tests

Step 13: System Settings
├── Create SystemSettingController
├── Super Admin only
├── Audit on change
└── Write tests
```

### 4.2 Laravel Configuration Checklist

| Konfigurasi | File | Detail |
|-------------|------|--------|
| Database | `config/database.php` | PostgreSQL, SSL mode |
| Auth | `config/auth.php` | Sanctum guard |
| Sanctum | `config/sanctum.php` | Expiration: 1440 (24 jam) |
| CORS | `config/cors.php` | Whitelist frontend URL |
| Hashing | `config/hashing.php` | Argon2id |
| Filesystems | `config/filesystems.php` | `evidence` disk (private) |
| Queue | `config/queue.php` | Database driver (dev), Redis (prod) |
| Logging | `config/logging.php` | Daily channel, sensitive data excluded |
| Rate Limit | `app/Providers/RouteServiceProvider.php` | Per-endpoint rate limits |

---

## 5. Web Development Workflow

### 5.1 Prinsip Integrasi Frontend

```
PENTING: Frontend React dari Lovable sudah ada.

Aturan:
├── JANGAN rewrite total UI
├── JANGAN hapus komponen yang sudah berjalan
├── Ganti mock data ke API secara bertahap
├── Buat API client layer terlebih dahulu
├── Buat auth context
├── Test setiap integrasi sebelum lanjut
└── Token disimpan IN-MEMORY (bukan localStorage)
```

### 5.2 Urutan Integrasi

```
Step 1: API Client Layer
├── Buat axios instance dengan base URL dan interceptors
├── Request interceptor: attach Bearer token
├── Response interceptor: handle 401 → redirect login
├── Error handler: map API error format ke UI feedback
└── Types: generate TypeScript types matching API response

Step 2: Auth Context
├── Buat AuthContext provider
├── In-memory token storage
├── Login flow: POST /auth/login → store token → GET /auth/me
├── Logout flow: POST /auth/logout → clear token → redirect
├── Auto-check: on mount, GET /auth/me (jika token ada)
├── Protected routes: TanStack Router beforeLoad
└── Role-based routes: check user.role.code

Step 3: Dashboard Integration
├── Admin dashboard → GET /dashboard/admin
├── Satgas dashboard → GET /dashboard/satgas
├── Reporter dashboard → GET /dashboard/reporter
├── TanStack Query: useQuery with auto-refresh
└── Loading states dan error handling

Step 4: Report Workflow Integration
├── Report list → GET /reports (filtered by role)
├── Report detail → GET /reports/{id}
├── Create report form → POST /reports
├── Anonymous report → POST /reports/anonymous
├── Report actions (verify, reject, forward) → PATCH endpoints
├── Track report → GET /reports/tracking/{code}
└── Evidence upload → POST /reports/{id}/evidences

Step 5: Case Workflow Integration
├── Case list → GET /cases (scoped by role)
├── Case detail → GET /cases/{id}
├── Case actions → PATCH/POST endpoints per stage
├── Message thread → GET/POST /cases/{id}/messages
└── Evidence in case context

Step 6: Notifications & Settings
├── Notification list → GET /notifications
├── Mark read → PATCH /notifications/{id}/read
├── System settings (super_admin) → GET/PUT /system-settings
└── Audit log viewer (admin/super_admin) → GET /audit-logs
```

### 5.3 API Client Example

```typescript
// src/api/client.ts
import axios from 'axios';

let inMemoryToken: string | null = null;

export const setToken = (token: string | null) => {
  inMemoryToken = token;
};

export const getToken = () => inMemoryToken;

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1',
  headers: { 'Content-Type': 'application/json' },
});

apiClient.interceptors.request.use((config) => {
  const token = getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      setToken(null);
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default apiClient;
```

---

## 6. Mobile Development Workflow

### 6.1 Status: DITUNDA

```
Flutter mobile app TIDAK dikerjakan pada fase ini.

Alasan:
├── API belum stabil (masih dalam pembangunan)
├── Backend harus selesai dan teruji terlebih dahulu
├── Frontend web integrasi memvalidasi API contract
└── Mobile menggunakan API yang sama setelah stabil
```

### 6.2 Persiapan

| Aspek | Detail |
|-------|--------|
| **Desain** | Google Stitch / Figma hanya sebagai reference UI |
| **API Contract** | Gunakan `API_SPECIFICATION.md` yang sama |
| **Auth** | Sama: POST /auth/login → Bearer token → flutter_secure_storage |
| **Mulai Kapan** | Setelah API stabil (semua endpoint tested), estimasi Phase 5+ |
| **HTTP Client** | Dio + Retrofit (recommended) |

### 6.3 Checklist Sebelum Mulai Mobile

- [ ] Semua backend endpoint terimplementasi
- [ ] Semua endpoint memiliki feature tests yang PASS
- [ ] Frontend web sudah terintegrasi dan stabil
- [ ] `API_SPECIFICATION.md` sudah final (tidak ada breaking changes)
- [ ] Token auth flow teruji end-to-end
- [ ] File upload/download teruji end-to-end

---

## 7. Branching & Git Workflow

### 7.1 Branch Structure

```
main              ← Production-ready, always stable
│
└── develop        ← Integration branch, staging
    │
    ├── feature/backend-auth
    ├── feature/backend-reports
    ├── feature/backend-cases
    ├── feature/web-auth-integration
    ├── feature/web-dashboard
    ├── fix/report-validation
    └── docs/update-api-spec
```

### 7.2 Branch Naming Convention

| Prefix | Gunakan Untuk | Contoh |
|--------|--------------|--------|
| `feature/` | Fitur baru | `feature/backend-auth` |
| `fix/` | Bug fix | `fix/report-status-validation` |
| `docs/` | Dokumentasi | `docs/update-api-spec` |
| `refactor/` | Refactoring | `refactor/case-policy` |
| `test/` | Test tambahan | `test/evidence-upload` |

### 7.3 Commit Message Convention

```
Format: <type>(<scope>): <description>

Types:
├── feat     → fitur baru
├── fix      → bug fix
├── docs     → dokumentasi
├── test     → test
├── refactor → refactoring
├── chore    → maintenance
└── security → perubahan keamanan

Contoh:
├── feat(backend): implement auth login endpoint
├── fix(backend): fix report status transition validation
├── docs(api): update report endpoint response format
├── test(backend): add policy tests for CasePolicy
├── security(backend): add rate limiting to anonymous endpoints
└── chore(backend): update composer dependencies
```

### 7.4 Workflow

```
1. Buat branch dari develop:
   git checkout develop
   git pull origin develop
   git checkout -b feature/backend-auth

2. Develop & commit (small commits):
   git add .
   git commit -m "feat(backend): implement login endpoint"

3. Push & create PR/MR ke develop:
   git push origin feature/backend-auth

4. Code review (Reviewer Agent):
   - Security checklist ✅
   - Tests pass ✅
   - Docs updated ✅

5. Merge ke develop:
   Squash merge recommended

6. Periodically: develop → main (setelah stable milestone)
```

### 7.5 Backup Rules

```
PENTING: Backup sebelum refactor besar.

├── Sebelum restructure folder → commit & push terlebih dahulu
├── Sebelum merge besar → pastikan develop sudah di-push
├── Sebelum ubah migration → pastikan tidak ada data penting di dev DB
└── Database backup sebelum migration destructive
```

---

## 8. Environment Setup

### 8.1 Laravel Backend

```env
# .env.example — Backend
APP_NAME=SILAPPKASAL
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=silappkasal
DB_USERNAME=silappkasal_dev
DB_PASSWORD=secret

# Sanctum
SANCTUM_TOKEN_EXPIRATION=1440

# Hashing
HASH_DRIVER=argon2id

# Queue
QUEUE_CONNECTION=database

# File Storage
FILESYSTEM_DISK=local
EVIDENCE_DISK=evidence

# Private Article media (requires PHP GD with JPEG/PNG/WebP support)
CONTENT_IMAGE_UPLOADS_ENABLED=false
CONTENT_ORPHAN_MEDIA_RETENTION_HOURS=168

# Fonnte (WhatsApp)
FONNTE_API_TOKEN=your-fonnte-token
FONNTE_DEVICE_ID=your-device-id

# CORS
FRONTEND_URL=http://localhost:5173

# Super Admin (seed)
SUPER_ADMIN_EMAIL=superadmin@silappkasal.ac.id
SUPER_ADMIN_PASSWORD=ChangeMe!2026

# Redis (opsional, untuk production queue)
# REDIS_HOST=127.0.0.1
# REDIS_PASSWORD=null
# REDIS_PORT=6379
```

### 8.2 PostgreSQL

```
Setup PostgreSQL lokal:

1. Install PostgreSQL 16+
2. Buat database:
   CREATE DATABASE silappkasal;
3. Buat user:
   CREATE USER silappkasal_dev WITH PASSWORD 'secret';
   GRANT ALL PRIVILEGES ON DATABASE silappkasal TO silappkasal_dev;
4. Alternatif: gunakan Docker
   docker run -d --name silappkasal-db \
     -e POSTGRES_DB=silappkasal \
     -e POSTGRES_USER=silappkasal_dev \
     -e POSTGRES_PASSWORD=secret \
     -p 5432:5432 \
     postgres:16
```

### 8.3 Redis (Opsional)

```
Redis opsional untuk development. Default menggunakan database queue driver.

Untuk production, Redis direkomendasikan:
├── Queue driver: redis
├── Cache driver: redis
├── Session driver: redis (jika butuh)
└── Install: apt install redis-server / docker run redis
```

### 8.4 Queue Worker

```bash
# Development: jalankan queue worker secara manual
php artisan queue:work --queue=default,notifications,uploads --tries=3

# Atau untuk single run (testing):
php artisan queue:work --once

# Production: gunakan Supervisor (lihat SECURITY_POLICY.md Section 19)
```

### 8.5 React Frontend

```env
# apps/web-admin/.env
VITE_API_URL=http://localhost:8000/api/v1
VITE_APP_NAME=SILAPPKASAL
```

```bash
# Jalankan frontend
cd apps/web-admin
npm install
npm run dev
# → http://localhost:5173
```

### 8.6 Local Private Storage

```
Development menggunakan local private storage:

├── Evidence files: storage/app/private/evidence/
├── Disk config: 'evidence' → local, private visibility
├── BUKAN public disk
├── Akses hanya via controller (StreamedResponse)
└── S3-compatible storage → OPSIONAL, untuk future/production

JANGAN menggunakan S3 sejak awal development.
```

---

## 9. Testing Strategy

### 9.1 Backend Tests (Wajib)

| Kategori | Detail | Lokasi |
|----------|--------|--------|
| **Feature Tests** | End-to-end API endpoint tests | `tests/Feature/Api/V1/` |
| **Policy Tests** | Laravel Policy unit tests | `tests/Unit/Policies/` |
| **Auth Tests** | Login, logout, token lifecycle | `tests/Feature/Api/V1/Auth/` |
| **File Upload Tests** | Evidence upload, validation, storage | `tests/Feature/Api/V1/Evidence/` |
| **API Contract Tests** | Response format sesuai API_SPECIFICATION.md | Inline di feature tests |
| **Workflow Tests** | Status transition validation | `tests/Feature/Api/V1/Cases/` |

### 9.2 Test Structure

```
tests/
├── Feature/
│   └── Api/
│       └── V1/
│           ├── Auth/
│           │   ├── LoginTest.php
│           │   ├── LogoutTest.php
│           │   ├── RegisterTest.php
│           │   └── MeTest.php
│           ├── Users/
│           │   ├── UserCrudTest.php
│           │   └── UserRoleTest.php
│           ├── Reports/
│           │   ├── CreateReportTest.php
│           │   ├── AnonymousReportTest.php
│           │   ├── ReportVerificationTest.php
│           │   └── TrackReportTest.php
│           ├── Cases/
│           │   ├── CaseListTest.php
│           │   ├── CaseWorkflowTest.php
│           │   ├── RiskAssessmentTest.php
│           │   └── InvestigationTest.php
│           ├── Evidence/
│           │   ├── EvidenceUploadTest.php
│           │   ├── EvidenceDownloadTest.php
│           │   └── EvidenceAccessTest.php
│           ├── BreakGlass/
│           │   └── BreakGlassTest.php
│           ├── Dashboard/
│           │   └── DashboardTest.php
│           └── AuditLog/
│               └── AuditLogTest.php
├── Unit/
│   ├── Policies/
│   │   ├── ReportPolicyTest.php
│   │   ├── CasePolicyTest.php
│   │   └── EvidencePolicyTest.php
│   ├── Services/
│   │   ├── AuditLogServiceTest.php
│   │   └── FonnteServiceTest.php
│   └── Models/
│       └── ReportTest.php (registration number generation, etc.)
└── TestCase.php
```

### 9.3 Test Examples

```php
// tests/Feature/Api/V1/Auth/LoginTest.php
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['token', 'token_type', 'expires_in', 'user'],
            ]);
    }

    public function test_login_rate_limited(): void
    {
        // 5 attempts → 429
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'identifier' => 'wrong@email.com',
                'password' => 'wrong',
            ]);
        }

        $response->assertStatus(429);
    }
}
```

```php
// tests/Unit/Policies/CasePolicyTest.php
class CasePolicyTest extends TestCase
{
    public function test_admin_can_only_view_case_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $case = Case::factory()->create();

        $this->assertTrue($admin->can('viewMetadata', $case));
        $this->assertFalse($admin->can('viewFull', $case));
    }

    public function test_super_admin_cannot_view_evidence_without_break_glass(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $evidence = Evidence::factory()->create();

        $this->assertFalse($superAdmin->can('view', $evidence));
    }
}
```

### 9.4 Frontend Tests (Minimal MVP)

| Kategori | Detail |
|----------|--------|
| **Smoke Tests** | Halaman utama render tanpa error |
| **Auth Flow Test** | Login → dashboard → logout |
| **API Integration** | Verify API client sends correct headers |

### 9.5 Running Tests

```bash
# Backend
cd backend/api
php artisan test                           # All tests
php artisan test --filter=LoginTest        # Specific test
php artisan test --testsuite=Feature       # Feature tests only
php artisan test --coverage                # With coverage

# Frontend
cd apps/web-admin
npm run test                               # (jika ada test runner)
```

---

## 10. Security Checklist Before Merge

Setiap PR/MR WAJIB memenuhi checklist ini sebelum di-merge:

### 10.1 Authentication & Authorization

- [ ] Endpoint memerlukan auth (`middleware('auth:sanctum')`)
- [ ] Policy terdaftar di `AuthServiceProvider`
- [ ] Policy digunakan di controller (`$this->authorize(...)`)
- [ ] Role check sesuai `MASTER_DATA.md` Section 2.2
- [ ] Super Admin tidak otomatis akses evidence (break-glass required)
- [ ] Admin hanya akses metadata kasus (bukan detail investigasi)
- [ ] Satgas hanya akses kasus yang ditugaskan

### 10.2 Validation

- [ ] Form Request class digunakan (bukan validasi inline)
- [ ] Semua input divalidasi di backend
- [ ] File upload: MIME type validated, max size enforced
- [ ] Status transition: valid transitions checked

### 10.3 Rate Limiting

- [ ] Rate limiting sesuai `SECURITY_POLICY.md` Section 6
- [ ] Anonymous endpoints memiliki rate limit yang lebih ketat
- [ ] Login endpoint: max 5/menit

### 10.4 Audit & Logging

- [ ] Aksi yang mengubah data dicatat di audit log
- [ ] Data sensitif di-mask dalam audit log
- [ ] `actor_ip = NULL` untuk aksi anonim
- [ ] Audit log immutable (tidak ada update/delete)

### 10.5 Evidence & File Security

- [ ] Evidence file tidak di-public folder
- [ ] Download via controller (bukan direct URL)
- [ ] UUID filename (bukan original filename)
- [ ] MIME type validated server-side
- [ ] Checksum computed dan disimpan

### 10.6 Data Protection

- [ ] Kolom sensitif menggunakan `encrypted` cast
- [ ] Tidak ada data sensitif di log file
- [ ] Tidak ada hardcoded secrets
- [ ] `.env` tidak masuk version control
- [ ] Password hashing: Argon2id

### 10.7 Anonymous Privacy

- [ ] `reporter_id = NULL` untuk laporan anonim
- [ ] IP tidak disimpan pada report/case/audit bisnis anonim
- [ ] Device fingerprint tidak disimpan
- [ ] Tracking code adalah satu-satunya cara akses

---

## 11. Definition of Done

### 11.1 Backend Feature

| # | Kriteria | Detail |
|---|----------|--------|
| 1 | **Code complete** | Controller, Model, Policy, Service, Form Request |
| 2 | **Migration exists** | Database migration sesuai `DATABASE_SCHEMA.md` |
| 3 | **Tests pass** | Feature tests + policy tests + edge cases |
| 4 | **Security checklist** | Semua item di Section 10 checked |
| 5 | **API spec match** | Response format sesuai `API_SPECIFICATION.md` |
| 6 | **Audit logged** | Semua aksi tercatat di audit log dengan masking |
| 7 | **Documentation** | Endpoint terdokumentasi, `BUILD_NOTES.md` updated |

### 11.2 Frontend Feature

| # | Kriteria | Detail |
|---|----------|--------|
| 1 | **Integrated with API** | Mock data diganti dengan API call |
| 2 | **Error handling** | 401, 403, 404, 422, 429, 500 ditangani |
| 3 | **Loading states** | Loading indicator saat fetch data |
| 4 | **No token in localStorage** | Token hanya in-memory |
| 5 | **Role-based UI** | Menu/aksi sesuai role user |
| 6 | **Responsive** | Berfungsi di desktop dan tablet |

### 11.3 Documentation Update

| # | Kriteria |
|---|----------|
| 1 | Perubahan signifikan didokumentasikan di `BUILD_NOTES.md` |
| 2 | API changes di-reflect di `API_SPECIFICATION.md` (jika breaking) |
| 3 | New config/env variables didokumentasikan di `.env.example` |

### 11.4 Security Review

| # | Kriteria |
|---|----------|
| 1 | Reviewer Agent telah memeriksa kode |
| 2 | Security checklist (Section 10) 100% checked |
| 3 | No `composer audit` / `npm audit` vulnerabilities |
| 4 | No hardcoded credentials |
| 5 | Policy tests cover semua access control scenarios |

---

## 12. Phase 4 Preparation

### 12.1 Apa yang Terjadi Setelah Phase 3 PASS

```
Phase 3 (sekarang) = Dokumen teknis fondasi:
├── DATABASE_SCHEMA.md      ✅
├── API_SPECIFICATION.md    ✅
└── DEVELOPMENT_WORKFLOW.md ✅

Phase 4 (berikutnya) = Mulai coding backend:
├── Setup repository structure sesuai Section 2
├── Setup Laravel project (backend/api/)
├── Setup PostgreSQL database
├── Run migrations sesuai DATABASE_SCHEMA.md
├── Run seeders (roles, permissions, master data, super admin)
├── Implementasi auth endpoints
├── Implementasi report endpoints
├── Mulai coding backend MVP
└── TDD: write tests as you build

Phase 5+ (setelah backend stabil):
├── Frontend integration (apps/web-admin/)
├── End-to-end testing
├── Mobile development (apps/mobile/)
└── Production deployment
```

### 12.2 Checklist Sebelum Memulai Phase 4

- [ ] Phase 3 docs telah di-review dan disetujui
- [ ] `DATABASE_SCHEMA.md` disetujui sebagai dasar migration
- [ ] `API_SPECIFICATION.md` disetujui sebagai kontrak API
- [ ] `DEVELOPMENT_WORKFLOW.md` dipahami oleh semua agent
- [ ] PostgreSQL tersedia dan tested
- [ ] Environment `.env` sudah disiapkan
- [ ] Git repository sudah siap dengan branch structure

### 12.3 First Commit Phase 4

```bash
# Phase 4 dimulai dengan:
git checkout -b feature/backend-setup

# 1. Buat Laravel project
composer create-project laravel/laravel backend/api

# 2. Configure PostgreSQL

# 3. Install Sanctum
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# 4. Run foundation migrations
php artisan migrate

# 5. Run seeders
php artisan db:seed

# 6. Verify
php artisan tinker
>>> User::count()  // → 1 (Super Admin)
>>> Role::count()  // → 5
>>> Permission::count() // → 30+
```

---

> **Catatan**: Dokumen ini adalah Tier 2 (GOVERNED). Perubahan memerlukan persetujuan Project Owner. Workflow ini menjadi referensi wajib bagi semua agent sebelum dan selama coding.

## REV-WITHDRAW-01A deployment note

The withdrawal foundation migrations are normal additive migrations. Do not use `migrate:fresh` on
development or production data. Before enabling direct cancellation, deploy the code and migrations,
reconcile RBAC, then explicitly set:

```dotenv
REPORT_EARLY_CANCELLATION_ENABLED=false
REPORT_FORMAL_WITHDRAWAL_ENABLED=false
```

Both flags default to false. Enable `REPORT_EARLY_CANCELLATION_ENABLED` only after product approval
and operational communication. Keep `REPORT_FORMAL_WITHDRAWAL_ENABLED=false` until the later formal
withdrawal submilestone supplies its mutation, private document flow, and Admin review workflow.
Database notifications are written, but this revision does not activate or redesign the Dashboard
notification inbox.
