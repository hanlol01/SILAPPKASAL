# API_SPECIFICATION.md — REST API Contract

> **Sistem Informasi Laporan Pencegahan dan Penanganan Kekerasan Seksual (SILAPPKASAL)**
> Versi: 1.0.1-patch | Terakhir Diperbarui: 2026-06-10 | Status: BERLAKU — AUDIT PATCH | Tier: 2 (GOVERNED)

---

## Daftar Isi

1. [API Principles](#1-api-principles)
2. [Standard Response Format](#2-standard-response-format)
3. [Authentication Endpoints](#3-authentication-endpoints)
4. [User & Role Endpoints](#4-user--role-endpoints)
5. [Master Data Endpoints](#5-master-data-endpoints)
6. [Report Endpoints](#6-report-endpoints)
7. [Case Endpoints](#7-case-endpoints)
8. [Evidence Endpoints](#8-evidence-endpoints)
9. [Message Endpoints](#9-message-endpoints)
10. [Notification Endpoints](#10-notification-endpoints)
11. [Dashboard Endpoints](#11-dashboard-endpoints)
12. [Audit Log Endpoints](#12-audit-log-endpoints)
13. [Break-Glass Endpoints](#13-break-glass-endpoints)
14. [System Settings Endpoints](#14-system-settings-endpoints)
15. [Endpoint Priority Tiers](#15-endpoint-priority-tiers)
16. [Rate Limiting](#16-rate-limiting)
17. [API Versioning](#17-api-versioning)
18. [Integration Notes](#18-integration-notes)

---

## 1. API Principles

| # | Prinsip | Detail |
|---|---------|--------|
| 1 | **REST API** | Standard HTTP methods (GET, POST, PUT, PATCH, DELETE). Resource-oriented URLs. |
| 2 | **JSON Only** | Request `Content-Type: application/json`. Response `Content-Type: application/json`. Exception: file upload/download menggunakan `multipart/form-data` dan `application/octet-stream`. |
| 3 | **Versioning** | Semua endpoint menggunakan prefix `/api/v1/`. |
| 4 | **Backend = Source of Truth** | Semua validasi, authorization, dan business logic dijalankan di backend. Frontend hanya melakukan validasi UX. |
| 5 | **RBAC + Policy Wajib** | Setiap endpoint dilindungi oleh middleware auth dan Laravel Policy. Tidak ada endpoint yang bypass authorization. |
| 6 | **Audit Trail** | Setiap aksi yang mengubah data (POST, PUT, PATCH, DELETE) dicatat di audit log. |
| 7 | **Stateless** | Tidak ada session state di server. Autentikasi via Bearer token (Laravel Sanctum). |
| 8 | **Pagination** | List endpoints menggunakan cursor-based atau offset pagination. Default: 15 items per page. |
| 9 | **Filtering & Sorting** | List endpoints mendukung query parameters: `?status=`, `?sort=`, `?search=`, `?per_page=`. |
| 10 | **No Sensitive Data in URL** | Data sensitif (kronologi, identitas) tidak boleh ada di URL atau query parameter. |

---

## 2. Standard Response Format

### 2.1 Success Response

```json
{
  "success": true,
  "message": "Data retrieved successfully",
  "data": { /* resource object or array */ },
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 42,
    "last_page": 3
  }
}
```

> `meta` hanya ada untuk paginated responses. Single resource response tidak memiliki `meta`.

### 2.2 Success — Single Resource

```json
{
  "success": true,
  "message": "Report created successfully",
  "data": {
    "id": 1,
    "registration_number": "SLP-2026-0610-0001",
    "status": "submitted",
    "created_at": "2026-06-10T00:00:00Z"
  }
}
```

### 2.3 Validation Error (422)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "chronology": ["The chronology field is required."],
    "incident_date": ["The incident date must be a date before or equal to today."]
  }
}
```

### 2.4 Authentication Error (401)

```json
{
  "success": false,
  "message": "Unauthenticated",
  "errors": null
}
```

### 2.5 Authorization Error (403)

```json
{
  "success": false,
  "message": "You do not have permission to perform this action",
  "errors": null
}
```

### 2.6 Not Found (404)

```json
{
  "success": false,
  "message": "Resource not found",
  "errors": null
}
```

### 2.7 Rate Limited (429)

```json
{
  "success": false,
  "message": "Too many requests. Please try again later.",
  "errors": null,
  "retry_after": 60
}
```

### 2.8 Server Error (500)

```json
{
  "success": false,
  "message": "An unexpected error occurred",
  "errors": null
}
```

> **Catatan**: Stack trace TIDAK ditampilkan di production. `APP_DEBUG=false`.

---

## 3. Authentication Endpoints

Base path: `/api/v1/auth`

### 3.1 Login

```
POST /api/v1/auth/login
Auth: None
Rate Limit: 5 requests per menit per IP
Audit Event: AUD-AUTH-01 (success) / AUD-AUTH-03 (failure)
```

**Request Body:**

```json
{
  "identifier": "user@university.ac.id",
  "password": "securePassword123"
}
```

> `identifier` bisa email, NIM, atau NIP. Backend mendeteksi format dan mencari di kolom yang sesuai.

**Success Response (200):**

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "1|abc123def456...",
    "token_type": "Bearer",
    "expires_in": 86400,
    "user": {
      "id": 1,
      "name": "Admin PPKS",
      "email": "admin@university.ac.id",
      "role": {
        "code": "admin",
        "name": "Admin"
      },
      "permissions": ["reports.read.all", "reports.verify", "..."]
    }
  }
}
```

**Error Cases:**

| Status | Kondisi |
|--------|---------|
| 401 | Kredensial salah |
| 403 | Akun nonaktif (`is_active = false`) |
| 422 | Validasi gagal (identifier/password kosong) |
| 429 | Rate limit exceeded |

### 3.2 Logout

```
POST /api/v1/auth/logout
Auth: Bearer Token
Audit Event: AUD-AUTH-02
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Logged out successfully",
  "data": null
}
```

### 3.3 Logout All Devices

```
POST /api/v1/auth/logout-all
Auth: Bearer Token
Audit Event: AUD-AUTH-02
```

Menghapus semua token user dari `personal_access_tokens`.

**Success Response (200):**

```json
{
  "success": true,
  "message": "Logged out from all devices",
  "data": {
    "tokens_revoked": 3
  }
}
```

### 3.4 Get Current User

```
GET /api/v1/auth/me
Auth: Bearer Token
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "User retrieved successfully",
  "data": {
    "id": 1,
    "name": "Admin PPKS",
    "email": "admin@university.ac.id",
    "nim": null,
    "nip": "198507202015041001",
    "phone_number": "6281234567890",
    "role": {
      "code": "admin",
      "name": "Admin"
    },
    "permissions": ["reports.read.all", "reports.verify", "..."],
    "is_active": true,
    "email_verified_at": "2026-01-01T00:00:00Z"
  }
}
```

### 3.5 Forgot Password

```
POST /api/v1/auth/forgot-password
Auth: None
Rate Limit: 3 requests per menit per IP
```

**Request Body:**

```json
{
  "email": "user@university.ac.id"
}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "If a matching account was found, a password reset link has been sent",
  "data": null
}
```

> Response selalu sama (200) terlepas dari apakah email ditemukan, untuk mencegah user enumeration.

### 3.6 Reset Password

```
POST /api/v1/auth/reset-password
Auth: None
Rate Limit: 3 requests per menit per IP
```

**Request Body:**

```json
{
  "token": "reset-token-from-email",
  "email": "user@university.ac.id",
  "password": "newSecurePassword123",
  "password_confirmation": "newSecurePassword123"
}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Password has been reset successfully",
  "data": null
}
```

### 3.7 Register

```
POST /api/v1/auth/register
Auth: None
Rate Limit: 3 requests per menit per IP
```

> Registrasi publik hanya untuk role `reporter`. Admin dan Satgas dibuat oleh Admin/Super Admin via user management.

**Request Body:**

```json
{
  "name": "Mahasiswa Pelapor",
  "email": "mahasiswa@university.ac.id",
  "nim": "2023123456",
  "phone_number": "6281234567890",
  "password": "securePassword123",
  "password_confirmation": "securePassword123"
}
```

**Success Response (201):**

```json
{
  "success": true,
  "message": "Registration successful. Please verify your email.",
  "data": {
    "id": 10,
    "name": "Mahasiswa Pelapor",
    "email": "mahasiswa@university.ac.id",
    "role": {
      "code": "reporter",
      "name": "Pelapor"
    }
  }
}
```

---

## 4. User & Role Endpoints

Base path: `/api/v1/users`

### 4.1 List Users

```
GET /api/v1/users
Auth: Bearer Token
Roles: super_admin, admin
Permission: users.read
Query Params: ?role=admin&is_active=true&search=john&page=1&per_page=15
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Users retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Admin PPKS",
      "email": "admin@university.ac.id",
      "nim": null,
      "nip": "198507202015041001",
      "role": { "code": "admin", "name": "Admin" },
      "is_active": true,
      "created_at": "2026-01-01T00:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 8, "last_page": 1 }
}
```

### 4.2 Create User

```
POST /api/v1/users
Auth: Bearer Token
Roles: super_admin, admin
Permission: users.create
Audit Event: AUD-USER-01
```

**Request Body:**

```json
{
  "name": "Satgas Baru",
  "email": "satgas@university.ac.id",
  "nip": "199001012020041001",
  "phone_number": "6281987654321",
  "role_code": "satgas_ppks",
  "password": "initialPassword123"
}
```

### 4.3 Get User Detail

```
GET /api/v1/users/{id}
Auth: Bearer Token
Roles: super_admin, admin
Permission: users.read
```

### 4.4 Update User

```
PUT /api/v1/users/{id}
Auth: Bearer Token
Roles: super_admin, admin
Permission: users.update
Audit Event: AUD-USER-02
```

### 4.5 Deactivate User

```
PATCH /api/v1/users/{id}/deactivate
Auth: Bearer Token
Roles: super_admin, admin
Permission: users.deactivate
Audit Event: AUD-USER-03
```

**Request Body:**

```json
{
  "reason": "User resigned from university"
}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "User deactivated successfully",
  "data": {
    "id": 5,
    "is_active": false,
    "deactivated_at": "2026-06-10T00:00:00Z"
  }
}
```

> Deactivasi melakukan soft-deactivate (set `is_active = false`), BUKAN delete. Semua token aktif user direvoke.

### 4.6 Assign Role

```
PATCH /api/v1/users/{id}/role
Auth: Bearer Token
Roles: super_admin
Permission: users.assign_role
Audit Event: AUD-USER-04
```

**Request Body:**

```json
{
  "role_code": "admin"
}
```

---

## 5. Master Data Endpoints

Base path: `/api/v1/master`

Semua master data endpoints bersifat **read-only** dan **public-authenticated** (auth required tapi tidak perlu role tertentu).

### 5.1 Endpoints

| Method | Path | Auth | Deskripsi |
|--------|------|:----:|-----------|
| GET | `/api/v1/master/report-categories` | ✅ | Daftar kategori laporan |
| GET | `/api/v1/master/report-types` | ✅ | Daftar tipe laporan |
| GET | `/api/v1/master/evidence-types` | ✅ | Daftar tipe bukti |
| GET | `/api/v1/master/case-statuses` | ✅ | Daftar status kasus |
| GET | `/api/v1/master/risk-levels` | ✅ | Daftar level risiko |
| GET | `/api/v1/master/priority-levels` | ✅ | Daftar level prioritas |
| GET | `/api/v1/master/campus-statuses` | ✅ | Daftar status kampus |
| GET | `/api/v1/master/relations` | ✅ | Daftar jenis relasi |
| GET | `/api/v1/master/location-types` | ✅ | Daftar tipe lokasi |
| GET | `/api/v1/master/escalation-types` | ✅ | Daftar tipe eskalasi |
| GET | `/api/v1/master/recovery-types` | ✅ | Daftar tipe pemulihan |

**Response Format (semua seragam):**

```json
{
  "success": true,
  "message": "Data retrieved successfully",
  "data": [
    {
      "code": "RCAT-01",
      "name": "Pelecehan Verbal",
      "description": "Ucapan, komentar, atau candaan...",
      "is_active": true,
      "sort_order": 1
    }
  ]
}
```

> Master data **tidak dipaginasi** (jumlah data kecil) dan bisa di-cache di frontend.

---

## 6. Report Endpoints

Base path: `/api/v1/reports`

### 6.1 Create Report (Authenticated)

```
POST /api/v1/reports
Auth: Bearer Token
Roles: reporter
Permission: reports.create
Audit Event: AUD-RPT-01
```

**Request Body:**

```json
{
  "report_type": "confidential",
  "category_code": "RCAT-01",
  "chronology": "Deskripsi lengkap kejadian...",
  "incident_date": "2026-06-09",
  "incident_time": "14:30",
  "incident_location": "Gedung A, Lantai 3",
  "location_type": "LOC-01",
  "respondent_name": "Nama Terlapor",
  "respondent_campus_status": "CAMP-01",
  "respondent_relation": "REL-02",
  "respondent_details": "Detail tambahan...",
  "witness_info": "Nama saksi...",
  "reporter_phone": "6281234567890"
}
```

**Success Response (201):**

```json
{
  "success": true,
  "message": "Report submitted successfully",
  "data": {
    "id": 1,
    "registration_number": "SLP-2026-0610-0001",
    "report_type": "confidential",
    "status": "submitted",
    "submitted_at": "2026-06-10T00:00:00Z"
  }
}
```

### 6.2 Create Anonymous Report

```
POST /api/v1/reports/anonymous
Auth: None (public endpoint)
Rate Limit: 3 requests per menit per IP (in-memory only)
Audit Event: AUD-RPT-01 (actor_id = NULL, actor_ip = NULL)
```

> **PENTING**: IP tidak dicatat di audit log bisnis. Rate limiter berjalan in-memory.

**Request Body:**

```json
{
  "category_code": "RCAT-03",
  "chronology": "Deskripsi kejadian anonim...",
  "incident_date": "2026-06-08",
  "incident_location": "Lokasi kampus",
  "respondent_name": "Nama terlapor jika diketahui",
  "respondent_campus_status": "CAMP-02"
}
```

**Success Response (201):**

```json
{
  "success": true,
  "message": "Anonymous report submitted successfully. Please save your tracking code.",
  "data": {
    "registration_number": "SLP-2026-0610-0002",
    "tracking_code": "A7X9-K2M4-P8Q3-R1W5",
    "status": "submitted",
    "important_notice": "Simpan tracking code ini. Tidak dapat dipulihkan jika hilang."
  }
}
```

### 6.3 List Reports

```
GET /api/v1/reports
Auth: Bearer Token
Roles: super_admin, admin (all reports); reporter (own reports)
Permission: reports.read.all / reports.read.own
Query Params: ?status=submitted&type=anonymous&category=RCAT-01&search=SLP-2026&sort=-submitted_at&page=1
```

> **Role-based filtering**:
> - Admin/Super Admin → sees all reports
> - Reporter → sees only own reports (`reporter_id = auth()->id()`)
> - Satgas → does NOT have direct report access (accesses via case)

**Success Response (200):**

```json
{
  "success": true,
  "message": "Reports retrieved successfully",
  "data": [
    {
      "id": 1,
      "registration_number": "SLP-2026-0610-0001",
      "report_type": "confidential",
      "category": { "code": "RCAT-01", "name": "Pelecehan Verbal" },
      "status": "submitted",
      "priority": null,
      "submitted_at": "2026-06-10T00:00:00Z",
      "has_case": false
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 }
}
```

> **Catatan**: Kolom sensitif (chronology, respondent_name, dll.) TIDAK ditampilkan di list response. Hanya di detail response.

### 6.4 Get Report Detail

```
GET /api/v1/reports/{id}
Auth: Bearer Token
Roles: super_admin, admin (any report); reporter (own only)
Permission: reports.read.all / reports.read.own
Policy: ReportPolicy@view
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Report retrieved successfully",
  "data": {
    "id": 1,
    "registration_number": "SLP-2026-0610-0001",
    "report_type": "confidential",
    "category": { "code": "RCAT-01", "name": "Pelecehan Verbal" },
    "chronology": "Deskripsi lengkap kejadian...",
    "incident_date": "2026-06-09",
    "incident_time": "14:30",
    "incident_location": "Gedung A, Lantai 3",
    "location_type": { "code": "LOC-01", "name": "Di Dalam Kampus" },
    "respondent": {
      "name": "Nama Terlapor",
      "campus_status": { "code": "CAMP-01", "name": "Mahasiswa Aktif" },
      "relation": { "code": "REL-02", "name": "Dosen" },
      "details": "Detail tambahan..."
    },
    "witness_info": "Nama saksi...",
    "status": "submitted",
    "priority": null,
    "admin_notes": null,
    "submitted_at": "2026-06-10T00:00:00Z",
    "evidences": [
      {
        "id": 1,
        "evidence_type": { "code": "EVID-01", "name": "Dokumen" },
        "original_filename": "screenshot.png",
        "mime_type": "image/png",
        "file_size": 245760,
        "created_at": "2026-06-10T00:01:00Z"
      }
    ],
    "case": null
  }
}
```

### 6.5 Review Report (Verify)

```
PATCH /api/v1/reports/{id}/verify
Auth: Bearer Token
Roles: super_admin, admin
Permission: reports.verify
Policy: ReportPolicy@verify
Audit Event: AUD-RPT-02
```

> **Catatan (Audit Patch v1.0.1)**: Endpoint ini menandai laporan sebagai sudah di-review oleh admin (`under_review`). Ini bukan status final — admin masih harus melakukan salah satu aksi: forward-to-satgas, request-info, atau reject. Status `verified` **TIDAK** digunakan di sistem ini.

**Request Body:**

```json
{
  "priority": "PRIO-02",
  "admin_notes": "Laporan lengkap dan valid."
}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Report reviewed successfully",
  "data": {
    "id": 1,
    "status": "under_review",
    "priority": "PRIO-02",
    "reviewed_at": "2026-06-10T10:00:00Z"
  }
}
```

> **Rekomendasi alur admin**: Setelah review, admin langsung melakukan `forward-to-satgas` (jika valid), `request-info` (jika perlu info tambahan), atau `reject` (jika tidak memenuhi kriteria). Tidak ada tahap menunggu setelah review.

### 6.6 Reject Report

```
PATCH /api/v1/reports/{id}/reject
Auth: Bearer Token
Roles: super_admin, admin
Permission: reports.reject
Policy: ReportPolicy@reject
Audit Event: AUD-RPT-03
```

**Request Body:**

```json
{
  "rejection_reason": "Laporan tidak termasuk kekerasan seksual. Disarankan melapor ke..."
}
```

### 6.7 Request Additional Info

```
PATCH /api/v1/reports/{id}/request-info
Auth: Bearer Token
Roles: super_admin, admin
Permission: reports.request_info
Policy: ReportPolicy@requestInfo
Audit Event: AUD-RPT-05
```

**Request Body:**

```json
{
  "admin_notes": "Mohon tambahkan informasi tanggal dan waktu kejadian yang lebih spesifik."
}
```

### 6.8 Forward to Satgas

```
PATCH /api/v1/reports/{id}/forward-to-satgas
Auth: Bearer Token
Roles: super_admin, admin
Permission: reports.forward
Policy: ReportPolicy@forward
Audit Event: AUD-RPT-04
```

> Endpoint ini membuat `Case` baru dari `Report`. Status report → `forwarded`. Case otomatis dibuat.

**Request Body:**

```json
{
  "satgas_ids": [3, 5],
  "lead_satgas_id": 3,
  "notes": "Prioritas tinggi. Segera lakukan asesmen risiko."
}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Report forwarded to Satgas. Case created.",
  "data": {
    "report": {
      "id": 1,
      "status": "forwarded",
      "forwarded_at": "2026-06-10T12:00:00Z"
    },
    "case": {
      "id": 1,
      "registration_number": "SLP-2026-0610-0001",
      "status": "forwarded",
      "assignments": [
        { "satgas_id": 3, "is_lead": true },
        { "satgas_id": 5, "is_lead": false }
      ]
    }
  }
}
```

### 6.9 Track Report (Anonymous)

```
GET /api/v1/reports/tracking/{tracking_code}
Auth: None (public, rate-limited)
Rate Limit: 10 requests per menit per IP
```

> **PENTING**: Hanya mengembalikan data minimal (status). TIDAK mengembalikan kronologi, identitas, atau data sensitif lainnya.

**Success Response (200):**

```json
{
  "success": true,
  "message": "Report status retrieved",
  "data": {
    "registration_number": "SLP-2026-0610-0002",
    "status": "investigation",
    "status_label": "Sedang Diinvestigasi",
    "submitted_at": "2026-06-10T00:00:00Z",
    "last_updated_at": "2026-06-11T09:00:00Z",
    "has_messages": true
  }
}
```

**Error Cases:**

| Status | Kondisi |
|--------|---------|
| 404 | Tracking code tidak ditemukan |
| 429 | Rate limit exceeded |

---

## 7. Case Endpoints

Base path: `/api/v1/cases`

### 7.1 List Cases

```
GET /api/v1/cases
Auth: Bearer Token
Role-based behavior:
  - super_admin: metadata semua kasus (via cases.read.all) → nomor registrasi, status, SLA, assignment
  - admin: metadata semua kasus (via cases.read.metadata) → nomor registrasi, status, SLA, assignment
  - satgas_ppks: kasus yang ditugaskan (via cases.read.assigned) → full data
Query Params: ?status=investigation&risk_level=high&sort=-forwarded_at&page=1
```

**Success Response — Admin/Super Admin (metadata only):**

```json
{
  "success": true,
  "message": "Cases retrieved successfully",
  "data": [
    {
      "id": 1,
      "registration_number": "SLP-2026-0610-0001",
      "status": "investigation",
      "status_label": "Investigasi",
      "risk_level": "high",
      "priority": "PRIO-01",
      "current_stage": 3,
      "current_stage_label": "Investigasi",
      "forwarded_at": "2026-06-10T12:00:00Z",
      "assignments": [
        { "satgas_id": 3, "satgas_name": "Satgas A", "is_lead": true }
      ]
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 5, "last_page": 1 }
}
```

> **PENTING**: Admin dan Super Admin TIDAK menerima field: `chronology`, `respondent`, `witness_info`, `investigation`, `findings` di response list/detail. Mereka hanya melihat metadata. Data sensitif hanya untuk Satgas assigned (via `cases.read.assigned`).

**Success Response — Satgas (assigned, full data):**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "registration_number": "SLP-2026-0610-0001",
      "status": "investigation",
      "risk_level": "high",
      "report": {
        "chronology": "Deskripsi lengkap...",
        "incident_date": "2026-06-09",
        "respondent": { "name": "...", "details": "..." }
      },
      "risk_assessment": { "...": "..." },
      "investigation": { "...": "..." }
    }
  ]
}
```

### 7.2 Get Case Detail

```
GET /api/v1/cases/{id}
Auth: Bearer Token
Policy: CasePolicy@view
```

> Response scope bergantung pada role dan permission (metadata vs full data).

### 7.3 Assign Satgas

```
PATCH /api/v1/cases/{id}/assign-satgas
Auth: Bearer Token
Roles: super_admin, admin
Permission: cases.assign_satgas
Policy: CasePolicy@assignSatgas
Audit Event: AUD-CASE-09
```

**Request Body:**

```json
{
  "satgas_ids": [3, 5],
  "lead_satgas_id": 3
}
```

### 7.4 Update Case Status

```
PATCH /api/v1/cases/{id}/status
Auth: Bearer Token
Roles: satgas_ppks (assigned)
Policy: CasePolicy@updateStatus
Audit Event: AUD-CASE-06
```

**Request Body:**

```json
{
  "status": "investigation",
  "notes": "Memulai investigasi setelah asesmen risiko selesai."
}
```

> Status transitions divalidasi di backend sesuai `case_statuses.valid_transitions`. Contoh: `assessment` → `investigation` (valid), `forwarded` → `closed` (invalid).

### 7.5 Risk Assessment

```
POST /api/v1/cases/{id}/risk-assessment
Auth: Bearer Token
Roles: satgas_ppks (assigned)
Permission: cases.assess_risk
Policy: CasePolicy@assessRisk
Audit Event: AUD-CASE-01
```

**Request Body:**

```json
{
  "risk_level": "high",
  "justification": "Korban mengalami ancaman langsung...",
  "protection_steps": "1. Pindahkan kelas korban...",
  "emergency_protection_needed": true,
  "emergency_notes": "Segera pisahkan korban dari terlapor"
}
```

### 7.6 Add Investigation Activity

```
POST /api/v1/cases/{id}/investigation-activities
Auth: Bearer Token
Roles: satgas_ppks (assigned)
Permission: cases.investigate
Policy: CasePolicy@investigate
Audit Event: AUD-CASE-03
```

**Request Body:**

```json
{
  "activity_type": "victim_interview",
  "activity_date": "2026-06-11",
  "description": "Wawancara dengan korban di ruang konseling...",
  "findings": "Korban menyatakan kejadian terjadi berulang...",
  "notes": "Korban memerlukan pendampingan psikologis."
}
```

### 7.7 Submit Recommendation

```
POST /api/v1/cases/{id}/recommendations
Auth: Bearer Token
Roles: satgas_ppks (assigned)
Permission: cases.recommend
Policy: CasePolicy@recommend
Audit Event: AUD-CASE-04
```

**Request Body:**

```json
{
  "conclusion": "Berdasarkan investigasi, terbukti terjadi...",
  "recommended_actions": "1. Sanksi akademis... 2. Konseling wajib...",
  "sanction_recommendation": "Skorsing 1 semester (SANC-03)",
  "recovery_recommendation": "Pendampingan psikologis 6 bulan",
  "prevention_recommendation": "Sosialisasi anti-kekerasan seksual"
}
```

### 7.8 Record Decision

```
POST /api/v1/cases/{id}/decisions
Auth: Bearer Token
Roles: satgas_ppks (assigned)
Permission: cases.record_decision
Policy: CasePolicy@recordDecision
Audit Event: AUD-CASE-05
```

**Request Body:**

```json
{
  "decision_number": "SK/PPKS/2026/001",
  "decision_date": "2026-06-20",
  "decision_content": "Berdasarkan rekomendasi Satgas PPKS..."
}
```

### 7.9 Add Recovery Monitoring

```
POST /api/v1/cases/{id}/recovery-monitoring
Auth: Bearer Token
Roles: satgas_ppks (assigned)
Permission: cases.monitor
Policy: CasePolicy@monitor
Audit Event: AUD-CASE-07 (when complete → case closed)
```

**Request Body:**

```json
{
  "recovery_type": "psychological",
  "activity_date": "2026-06-25",
  "description": "Sesi konseling ke-3 dengan psikolog...",
  "status": "ongoing",
  "notes": "Korban menunjukkan perkembangan positif."
}
```

### 7.10 Close Case

```
PATCH /api/v1/cases/{id}/close
Auth: Bearer Token
Roles: satgas_ppks (assigned)
Permission: cases.close
Policy: CasePolicy@close
Audit Event: AUD-CASE-07
```

**Request Body:**

```json
{
  "closing_notes": "Semua tahap penanganan telah selesai. Monitoring pemulihan selesai."
}
```

### 7.11 Escalate Case

```
PATCH /api/v1/cases/{id}/escalate
Auth: Bearer Token
Roles: satgas_ppks (assigned)
Permission: cases.escalate
Policy: CasePolicy@escalate
Audit Event: AUD-CASE-08
```

**Request Body:**

```json
{
  "escalation_type": "ESC-01",
  "escalation_notes": "Kasus memerlukan penanganan pihak berwajib..."
}
```

---

## 8. Evidence Endpoints

### 8.1 Upload Evidence (Report Context)

```
POST /api/v1/reports/{id}/evidences
Auth: Bearer Token (reporter); OR None (anonymous, via tracking_code header)
Permission: evidence.upload
Content-Type: multipart/form-data
Max File Size: 25 MB
Audit Event: AUD-EVID-01
```

**Request (multipart):**

```
file: (binary)
evidence_type: EVID-01
description: Screenshot percakapan WhatsApp
tracking_code: A7X9-K2M4-P8Q3-R1W5  (header, untuk anonim)
```

**Success Response (201):**

```json
{
  "success": true,
  "message": "Evidence uploaded successfully",
  "data": {
    "id": 1,
    "evidence_type": { "code": "EVID-01", "name": "Dokumen" },
    "original_filename": "screenshot.png",
    "mime_type": "image/png",
    "file_size": 245760,
    "created_at": "2026-06-10T00:01:00Z"
  }
}
```

**Validation:**

| Rule | Detail |
|------|--------|
| Allowed MIME types | `image/*`, `video/*`, `audio/*`, `application/pdf`, `application/msword`, `application/vnd.openxmlformats-*` |
| Max file size | 25 MB |
| Max files per report | 10 |
| Filename sanitization | Original name stored in DB, file saved as UUID |

### 8.2 Upload Evidence (Case Context)

```
POST /api/v1/cases/{id}/evidences
Auth: Bearer Token
Roles: satgas_ppks (assigned)
Permission: evidence.upload
Content-Type: multipart/form-data
Audit Event: AUD-EVID-01
```

### 8.3 View Evidence Metadata

```
GET /api/v1/evidences/{id}
Auth: Bearer Token
Policy: EvidencePolicy@view
Permission: evidence.view.case (satgas assigned, reporter own)
```

> **PENTING**: Super Admin TIDAK memiliki akses default ke evidence. Harus via break-glass access.

### 8.4 Download Evidence File

```
GET /api/v1/evidences/{id}/download
Auth: Bearer Token
Policy: EvidencePolicy@download
Permission: evidence.download (satgas assigned only)
Audit Event: AUD-EVID-03
```

**Response**: Binary file stream dengan correct headers:

```
Content-Type: image/png
Content-Disposition: attachment; filename="screenshot.png"
Content-Length: 245760
```

> File didekripsi di server dan dikirim sebagai stream. Tidak ada public URL.

### 8.5 Delete Evidence

```
DELETE /api/v1/evidences/{id}
Auth: Bearer Token
Roles: satgas_ppks (assigned), reporter (own, sebelum case terbentuk)
Policy: EvidencePolicy@delete
Audit Event: AUD-EVID-04
```

---

## 9. Message Endpoints

Base path: `/api/v1/cases/{id}/messages`

### 9.1 List Messages

```
GET /api/v1/cases/{id}/messages
Auth: Bearer Token; OR tracking_code header (anonymous)
Roles: satgas_ppks (assigned), reporter (own case)
Permission: messages.read.case
Query Params: ?page=1&per_page=30
```

**Success Response (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "sender": {
        "name": "Satgas A",
        "role": "satgas_ppks"
      },
      "is_anonymous": false,
      "content": "Kami memerlukan informasi tambahan...",
      "has_attachment": false,
      "created_at": "2026-06-11T09:00:00Z"
    },
    {
      "id": 2,
      "sender": null,
      "is_anonymous": true,
      "content": "Berikut informasi tambahannya...",
      "has_attachment": false,
      "created_at": "2026-06-11T10:30:00Z"
    }
  ]
}
```

### 9.2 Send Message

```
POST /api/v1/cases/{id}/messages
Auth: Bearer Token; OR tracking_code header (anonymous)
Roles: satgas_ppks (assigned), reporter (own case)
Permission: messages.send
Audit Event: AUD-MSG-01
```

**Request Body:**

```json
{
  "content": "Kami memerlukan informasi tambahan tentang saksi...",
  "tracking_code": "A7X9-K2M4-P8Q3-R1W5"
}
```

> `tracking_code` hanya diperlukan untuk pelapor anonim.

---

## 10. Notification Endpoints

Base path: `/api/v1/notifications`

### 10.1 List Notifications

```
GET /api/v1/notifications
Auth: Bearer Token
Query Params: ?unread=true&page=1
```

**Success Response (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "type": "NOTIF-02",
      "title": "Status Laporan Diperbarui",
      "body": "Laporan Anda SLP-2026-0610-0001 telah diverifikasi.",
      "data": { "report_id": 1, "registration_number": "SLP-2026-0610-0001" },
      "read_at": null,
      "created_at": "2026-06-10T10:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 3, "last_page": 1 }
}
```

### 10.2 Mark as Read

```
PATCH /api/v1/notifications/{id}/read
Auth: Bearer Token
```

### 10.3 Mark All as Read

```
PATCH /api/v1/notifications/read-all
Auth: Bearer Token
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "All notifications marked as read",
  "data": { "updated_count": 5 }
}
```

---

## 11. Dashboard Endpoints

Base path: `/api/v1/dashboard`

### 11.1 Admin Dashboard

```
GET /api/v1/dashboard/admin
Auth: Bearer Token
Roles: super_admin, admin
Permission: dashboard.admin
```

**Success Response (200):**

```json
{
  "success": true,
  "data": {
    "summary": {
      "total_reports": 42,
      "pending_review": 5,
      "under_review": 8,
      "need_info": 2,
      "rejected": 7,
      "forwarded": 25,
      "active_cases": 15,
      "closed_cases": 10
    },
    "reports_by_category": [
      { "category": "RCAT-01", "name": "Pelecehan Verbal", "count": 12 }
    ],
    "reports_by_type": [
      { "type": "open", "count": 20 },
      { "type": "confidential", "count": 15 },
      { "type": "anonymous", "count": 7 }
    ],
    "recent_reports": [
      {
        "id": 42,
        "registration_number": "SLP-2026-0610-0042",
        "status": "submitted",
        "submitted_at": "2026-06-10T23:00:00Z"
      }
    ],
    "sla_alerts": [
      {
        "case_id": 3,
        "registration_number": "SLP-2026-0605-0003",
        "status": "assessment",
        "days_in_current_stage": 5,
        "sla_days": 3,
        "is_overdue": true
      }
    ]
  }
}
```

### 11.2 Satgas Dashboard

```
GET /api/v1/dashboard/satgas
Auth: Bearer Token
Roles: satgas_ppks
Permission: dashboard.satgas
```

**Success Response (200):**

```json
{
  "success": true,
  "data": {
    "my_cases": {
      "total": 5,
      "active": 3,
      "completed": 2
    },
    "cases_by_stage": [
      { "stage": 2, "stage_name": "Asesmen Risiko", "count": 1 },
      { "stage": 3, "stage_name": "Investigasi", "count": 2 }
    ],
    "urgent_cases": [
      {
        "case_id": 1,
        "registration_number": "SLP-2026-0610-0001",
        "risk_level": "high",
        "status": "investigation",
        "is_lead": true
      }
    ],
    "recent_activities": [
      {
        "case_id": 1,
        "activity": "investigation_activity_added",
        "timestamp": "2026-06-11T14:00:00Z"
      }
    ]
  }
}
```

### 11.3 Reporter Dashboard

```
GET /api/v1/dashboard/reporter
Auth: Bearer Token
Roles: reporter
```

**Success Response (200):**

```json
{
  "success": true,
  "data": {
    "my_reports": {
      "total": 2,
      "submitted": 1,
      "under_review": 0,
      "forwarded": 1,
      "rejected": 0
    },
    "reports": [
      {
        "id": 1,
        "registration_number": "SLP-2026-0610-0001",
        "status": "forwarded",
        "submitted_at": "2026-06-10T00:00:00Z"
      }
    ],
    "unread_messages": 1
  }
}
```

---

## 12. Audit Log Endpoints

Base path: `/api/v1/audit-logs`

### 12.1 List Audit Logs

```
GET /api/v1/audit-logs
Auth: Bearer Token
Roles: super_admin (full), admin (filtered — system.audit_log.view limited)
Permission: system.audit_log.view
Query Params: ?event=case.status_changed&severity=CRITICAL&actor_id=3&resource_type=Case&from=2026-06-01&to=2026-06-10&page=1
```

> Admin hanya melihat log severity INFO dan WARNING. Super Admin melihat semua termasuk CRITICAL.

**Success Response (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "event": "case.status_changed",
      "severity": "INFO",
      "actor": { "id": 3, "name": "Satgas A", "role": "satgas_ppks" },
      "resource_type": "Case",
      "resource_id": 1,
      "old_values": { "status": "assessment" },
      "new_values": { "status": "investigation" },
      "created_at": "2026-06-11T09:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "per_page": 30, "total": 150, "last_page": 5 }
}
```

### 12.2 Get Audit Log Detail

```
GET /api/v1/audit-logs/{id}
Auth: Bearer Token
Roles: super_admin, admin (filtered)
Permission: system.audit_log.view
```

---

## 13. Break-Glass Endpoints

Base path: `/api/v1/cases/{id}/break-glass` and `/api/v1/break-glass`

### 13.1 Activate Break-Glass Access

```
POST /api/v1/cases/{id}/break-glass
Auth: Bearer Token
Roles: super_admin ONLY
Permission: system.break_glass_access
Audit Event: AUD-SEC-04 (severity: CRITICAL)
```

**Request Body:**

```json
{
  "justification": "Insiden keamanan: data korban berpotensi terekspos akibat akses tidak sah. Diperlukan verifikasi data kasus untuk incident response.",
  "scope": ["investigation", "evidence", "identity"]
}
```

> `justification` WAJIB minimal 50 karakter. `scope` menentukan data apa yang bisa diakses.

**Success Response (201):**

```json
{
  "success": true,
  "message": "Break-glass access activated. Session expires in 4 hours.",
  "data": {
    "session_id": "550e8400-e29b-41d4-a716-446655440000",
    "case_id": 1,
    "scope": ["investigation", "evidence", "identity"],
    "expires_at": "2026-06-10T16:00:00Z",
    "notice": "Akses ini dicatat sebagai audit CRITICAL dan akan di-review oleh Project Owner dalam 48 jam."
  }
}
```

### 13.2 Revoke Break-Glass Session

```
DELETE /api/v1/cases/{id}/break-glass
Auth: Bearer Token
Roles: super_admin
```

**Request Body:**

```json
{
  "session_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

### 13.3 List Break-Glass Sessions

```
GET /api/v1/break-glass/sessions
Auth: Bearer Token
Roles: super_admin
Query Params: ?active=true&page=1
```

**Success Response (200):**

```json
{
  "success": true,
  "data": [
    {
      "session_id": "550e8400-e29b-41d4-a716-446655440000",
      "case_id": 1,
      "case_registration_number": "SLP-2026-0610-0001",
      "actor": { "id": 1, "name": "Super Admin" },
      "justification": "Insiden keamanan...",
      "scope": ["investigation", "evidence", "identity"],
      "is_active": true,
      "created_at": "2026-06-10T12:00:00Z",
      "expires_at": "2026-06-10T16:00:00Z",
      "revoked_at": null
    }
  ]
}
```

---

## 14. System Settings Endpoints

Base path: `/api/v1/system-settings`

### 14.1 List Settings

```
GET /api/v1/system-settings
Auth: Bearer Token
Roles: super_admin
Permission: system.configure
```

### 14.2 Update Setting

```
PUT /api/v1/system-settings/{key}
Auth: Bearer Token
Roles: super_admin
Permission: system.configure
Audit Event: AUD-SYS-01
```

**Request Body:**

```json
{
  "value": "5"
}
```

---

## 15. Endpoint Priority Tiers

Endpoint dikelompokkan berdasarkan prioritas implementasi agar Backend Agent dapat membangun secara bertahap.

### 15.1 MVP Core

Endpoint yang **WAJIB** ada untuk sistem bisa berfungsi minimal.

| # | Endpoint | Modul |
|---|----------|-------|
| 1 | `POST /auth/login` | Auth |
| 2 | `POST /auth/logout` | Auth |
| 3 | `GET /auth/me` | Auth |
| 4 | `POST /auth/register` | Auth |
| 5 | `GET /master/*` (semua) | Master Data |
| 6 | `POST /reports` | Reports |
| 7 | `POST /reports/anonymous` | Reports |
| 8 | `GET /reports` | Reports |
| 9 | `GET /reports/{id}` | Reports |
| 10 | `PATCH /reports/{id}/reject` | Reports |
| 11 | `PATCH /reports/{id}/forward-to-satgas` | Reports |
| 12 | `GET /reports/tracking/{code}` | Reports |
| 13 | `GET /cases` | Cases |
| 14 | `GET /cases/{id}` | Cases |
| 15 | `PATCH /cases/{id}/assign-satgas` | Cases |
| 16 | `POST /cases/{id}/risk-assessment` | Cases |
| 17 | `POST /cases/{id}/investigation-activities` | Cases |
| 18 | `POST /cases/{id}/recommendations` | Cases |
| 19 | `POST /cases/{id}/decisions` | Cases |
| 20 | `PATCH /cases/{id}/close` | Cases |
| 21 | `POST /reports/{id}/evidences` | Evidence |
| 22 | `GET /evidences/{id}/download` | Evidence |
| 23 | `GET /users` | Users |
| 24 | `POST /users` | Users |

### 15.2 MVP Extended

Endpoint yang **SEBAIKNYA** ada untuk pengalaman pengguna yang layak.

| # | Endpoint | Modul |
|---|----------|-------|
| 1 | `POST /auth/logout-all` | Auth |
| 2 | `PATCH /reports/{id}/verify` | Reports |
| 3 | `PATCH /reports/{id}/request-info` | Reports |
| 4 | `PATCH /cases/{id}/status` | Cases |
| 5 | `POST /cases/{id}/recovery-monitoring` | Cases |
| 6 | `PATCH /cases/{id}/escalate` | Cases |
| 7 | `POST /cases/{id}/evidences` | Evidence |
| 8 | `DELETE /evidences/{id}` | Evidence |
| 9 | `GET /cases/{id}/messages` | Messages |
| 10 | `POST /cases/{id}/messages` | Messages |
| 11 | `GET /notifications` | Notifications |
| 12 | `PATCH /notifications/{id}/read` | Notifications |
| 13 | `PATCH /notifications/read-all` | Notifications |
| 14 | `GET /dashboard/admin` | Dashboard |
| 15 | `GET /dashboard/satgas` | Dashboard |
| 16 | `GET /dashboard/reporter` | Dashboard |
| 17 | `GET /users/{id}` | Users |
| 18 | `PUT /users/{id}` | Users |
| 19 | `PATCH /users/{id}/deactivate` | Users |
| 20 | `PATCH /users/{id}/role` | Users |

### 15.3 Post-MVP

Endpoint yang bisa ditunda tanpa mengurangi fungsionalitas inti.

| # | Endpoint | Modul |
|---|----------|-------|
| 1 | `POST /auth/forgot-password` | Auth |
| 2 | `POST /auth/reset-password` | Auth |
| 3 | `GET /audit-logs` | Audit |
| 4 | `GET /audit-logs/{id}` | Audit |
| 5 | `POST /cases/{id}/break-glass` | Break-Glass |
| 6 | `DELETE /cases/{id}/break-glass` | Break-Glass |
| 7 | `GET /break-glass/sessions` | Break-Glass |
| 8 | `GET /system-settings` | Settings |
| 9 | `PUT /system-settings/{key}` | Settings |
| 10 | `GET /evidences/{id}` (metadata only) | Evidence |

> **Catatan**: Backend Agent harus menyelesaikan semua **MVP Core** sebelum mengerjakan **MVP Extended**. **Post-MVP** dikerjakan setelah MVP stabil.

---

## 16. Rate Limiting

Sesuai `SECURITY_POLICY.md` Section 6:

| Kategori | Endpoint | Limit | Window |
|----------|----------|:-----:|:------:|
| **Auth — Login** | `POST /auth/login` | 5 | 1 menit |
| **Auth — Register** | `POST /auth/register` | 3 | 1 menit |
| **Auth — Password Reset** | `POST /auth/forgot-password`, `POST /auth/reset-password` | 3 | 1 menit |
| **Anonymous Report** | `POST /reports/anonymous` | 3 | 1 menit |
| **Anonymous Track** | `GET /reports/tracking/{code}` | 10 | 1 menit |
| **Evidence Upload** | `POST /*/evidences` | 5 | 1 menit |
| **Evidence Download** | `GET /evidences/*/download` | 10 | 1 menit |
| **General — Authenticated** | Semua endpoint lain (authenticated) | 60 | 1 menit |
| **General — Guest** | Semua endpoint lain (guest) | 30 | 1 menit |

### Rate Limit Headers

Setiap response menyertakan header rate limit:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 55
X-RateLimit-Reset: 1686355200
Retry-After: 60  (hanya saat 429)
```

### Rate Limit — Khusus Anonymous

```
PENTING: Untuk endpoint anonymous, rate limiting dilakukan di:
├── Application layer (Laravel throttle middleware)
├── IP digunakan in-memory oleh middleware
├── IP TIDAK dicatat di audit log bisnis (actor_ip = NULL)
├── Jika perlu security log → IP di-hash (SHA-256 + daily_salt)
└── Security log anonymous → auto-purge 7 hari
```

---

## 17. API Versioning

### 17.1 Strategi

```
URL-based versioning:

/api/v1/reports    ← current
/api/v2/reports    ← future (jika breaking change)

Aturan:
├── Major version di URL (/v1/, /v2/)
├── Non-breaking changes di versi yang sama
├── Breaking changes → versi baru
├── Versi lama di-sunset dengan notice 6 bulan
└── Header deprecation: Sunset: Sat, 01 Jan 2028 00:00:00 GMT
```

### 17.2 Non-Breaking Changes (tidak bump versi)

- Menambah field baru di response
- Menambah endpoint baru
- Menambah optional query parameter
- Menambah optional request body field

### 17.3 Breaking Changes (bump versi)

- Menghapus field dari response
- Mengubah tipe data field
- Mengubah URL path
- Mengubah required fields
- Mengubah error format

---

## 18. Integration Notes

### 18.1 React Web (Frontend)

```
Integrasi React ↔ API:
├── Gunakan API_SPECIFICATION.md sebagai kontrak
├── Ganti mock data ke API secara bertahap
├── Buat API client layer (axios instance + interceptors)
├── Token disimpan in-memory (React state)
├── Auth context menggunakan GET /auth/me
├── TanStack Query untuk data fetching
├── TanStack Mutation untuk data mutation
├── Error handling seragam berdasarkan status code
├── Form validation di frontend = UX helper, backend = source of truth
└── File upload menggunakan multipart/form-data
```

### 18.2 Flutter Mobile (Post-MVP)

```
Integrasi Flutter ↔ API:
├── Gunakan API_SPECIFICATION.md yang sama
├── Tunggu sampai API stabil (semua endpoint tested)
├── Token disimpan di flutter_secure_storage
├── Dio sebagai HTTP client
├── Retrofit untuk API contract
└── Alur auth sama dengan React (login → token → Bearer)
```

### 18.3 WhatsApp Fonnte (Server-side)

```
Fonnte BUKAN dipanggil oleh frontend atau mobile:

├── Fonnte dipanggil oleh Laravel Queue (server-side background job)
├── Flow: User action → API → Event → Queue Job → FonnteService → WhatsApp API
├── Frontend TIDAK tahu tentang Fonnte
├── Mobile TIDAK tahu tentang Fonnte
├── Queue: SendWhatsAppNotification job
├── Template-based messages (no freeform)
├── Retry: 3x dengan exponential backoff
├── Fail gracefully: jika Fonnte down, proses utama tetap berjalan
└── Log: delivery tracking via notifications.delivery_status, notifications.provider_response
```

### 18.4 CORS Configuration

```php
// config/cors.php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),  // React dev
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
    ],
    'max_age' => 86400,
    'supports_credentials' => false,  // true jika Post-MVP httpOnly cookie
];
```

### 18.5 REV-WF-03 R1 Reporter Transparency Contract

The executable routes, resources, and authorization checks are the source of truth for this contract.

```text
GET /api/v1/portal/reports/{registrationNumber}
GET /api/v1/portal/reports/{registrationNumber}/handling-progress
Auth: Bearer Token
Role: reporter
Scope: report owned by the authenticated reporter
```

- The report detail response includes `submitted_details`, containing the report's submitted incident, respondent, witness, confidential-contact, and current reporter-account projections. Current account identity is not a historical report snapshot.
- The handling-progress endpoint accepts the external registration number only. It returns domain state, safe dates, and aggregate counts for Case, Investigation, Recommendation, Decision, Recovery/monitoring, and Evidence. It does not return staff identifiers, narrative content, findings, notes, draft content, filenames, custody data, storage paths, or internal entity identifiers.
- `final_summary` remains `null` in R1. Final case-outcome content belongs to a later revision.
- Anonymous classification does not prevent an authenticated owner from viewing their own submitted values. Internal projections continue to mask Reporter identity for anonymous Reports.
- Internal submitted-detail projection is limited to same-campus Admin, active assigned Satgas on the Case, and Super Admin only when the sensitive cross-campus-read feature flag permits it.
- Report priority is projected from the linked Case: `unavailable` when no Case exists, `unassessed` when the Case has no priority, and `assessed` with the Case priority reference otherwise. The legacy Report priority column is not authoritative.
- Reporter UI renders collapsible report-detail and handling-progress cards. The progress card has Investigation, Recommendation, Decision, Recovery, and Evidence sections and does not expose sensitive operational content.
- REV-WF-03 R2 and R3 are not completed by this contract.

---

> **Catatan**: Dokumen ini adalah Tier 2 (GOVERNED). Perubahan memerlukan persetujuan Project Owner. API specification ini menjadi kontrak wajib antara Backend Agent, Web Agent, dan Mobile Agent.
