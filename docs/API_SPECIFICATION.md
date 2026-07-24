# API_SPECIFICATION.md — REST API Contract

> **Sistem Informasi Laporan Pencegahan dan Penanganan Kekerasan Seksual (SILAPPKASAL)**
> Versi: REV-WF-03-R2 | Terakhir Diperbarui: 2026-07-20 | Status: BERLAKU | Tier: 2 (GOVERNED)

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

> **Terminologi UI**: kontrak teknis tetap menggunakan nama domain `Report`, tabel/field `reports`, dan route `/reports` untuk backward compatibility. Seluruh teks yang ditampilkan kepada pengguna menggunakan **Pengaduan** (ID) atau **Complaint** (EN).

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
  "message": "Complaint submitted successfully",
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
  "message": "Anonymous complaint submitted successfully. Please save your tracking code.",
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
Roles: super_admin (cross-campus metadata), admin (same-campus metadata), reporter (own reports)
Permission: reports.read.all / reports.read.own
Query Params: ?status=submitted&report_type=anonymous&category_code=RCAT-01&satgas_id=42&assignment_status=unassigned&university_id=7&page=1
```

> **Role-based filtering**:
> - Admin hanya melihat pengaduan kampusnya; `satgas_id` memfilter pengaduan yang kasusnya memiliki penugasan aktif kepada Satgas tersebut.
> - Admin dapat memakai `assignment_status=unassigned` untuk Pengaduan yang belum mempunyai Case atau yang Case-nya tidak mempunyai penugasan aktif. Riwayat penugasan nonaktif tidak dianggap penugasan aktif.
> - Super Admin melihat metadata pengaduan lintas kampus; `university_id` memfilter kampus pelapor.
> - Reporter hanya melihat pengaduannya sendiri (`reporter_id = auth()->id()`).
> - Satgas tidak memiliki akses langsung ke endpoint Report (akses melalui Case).
> - `satgas_id` dan `assignment_status` saling eksklusif, hanya tersedia bagi Admin yang mempunyai kampus, dan divalidasi dalam scope kampus tersebut.
> - `satgas_id`/`assignment_status` ditolak untuk role selain Admin dan `university_id` ditolak untuk role selain Super Admin dengan `422`.

**Success Response (200):**

```json
{
  "success": true,
  "message": "Complaints retrieved successfully",
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
  "message": "Complaint retrieved successfully",
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

### 6.8 Forward to Case and Assign Satgas

```
POST /api/v1/reports/{id}/forward-to-case
Auth: Bearer Token
Role: admin
Permission: reports.forward
Policy: ReportPolicy@forward
Audit Events: report.forwarded, case.created, case.assigned
```

> Endpoint ini membuat `Case` baru dari `Report`. Status report → `forwarded`. Case otomatis dibuat.

**Request Body:**

```json
{
  "satgas_ids": [3, 5]
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
      "lock_version": "9be4...64-character-sha256-token",
      "assignments": [
        { "satgas_id": 3, "assignment_type": "assign", "is_active": true },
        { "satgas_id": 5, "assignment_type": "assign", "is_active": true }
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
  "message": "Complaint status retrieved",
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
  - satgas_ppks: metadata kasus yang ditugaskan, atau antrean same-campus tanpa penugasan melalui `assignment_status=unassigned`; detail sensitif tetap hanya melalui endpoint detail setelah assignment sah
Query Params: ?status=investigation&risk_level=high&satgas_id=42&assignment_status=unassigned&sort=-forwarded_at&page=1
```

Filter scope opsional:

- `satgas_id`: hanya Admin; memfilter kasus dengan penugasan aktif kepada Satgas tersebut dan tetap dibatasi ke kampus Admin.
- `assignment_status=unassigned`: tersedia untuk Admin dan Satgas aktif dengan kampus. Admin memperoleh filter kasus tanpa penugasan; Satgas memperoleh antrean same-campus yang mutable, tidak dipause withdrawal, dan dapat diambil. Assignment historis/nonaktif diabaikan.
- `satgas_id` dan `assignment_status` saling eksklusif. Nilai status lain, kombinasi kedua parameter, actor tanpa kampus, dan penggunaan oleh role lain menghasilkan `422`.
- `university_id` tidak didukung pada daftar Kasus; filter Kampus Super Admin tersedia pada Ringkasan dan daftar Pengaduan.
- Parameter role yang salah atau filter yang tidak didukung ditolak dengan `422`; filter tidak memperluas campus isolation atau akses data sensitif.

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
      "lock_version": "9be4...64-character-sha256-token",
      "assignments": [
        { "satgas_id": 3, "satgas_name": "Satgas A", "assignment_type": "assign", "is_active": true }
      ],
      "assignment_capabilities": {
        "manage": { "allowed": true, "reason_code": null },
        "self_assign": { "allowed": false, "reason_code": "permission_missing" }
      }
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

### 7.3 Assign or Reassign Satgas

```
PATCH /api/v1/cases/{id}/assign
Auth: Bearer Token
Role: admin
Permission: cases.assign_satgas
Policy: CasePolicy@assign
Audit Events: case.assigned, case.reassigned
```

**Request Body:**

```json
{
  "satgas_ids": [3, 5],
  "lock_version": "9be4...64-character-sha256-token"
}
```

`lock_version` adalah token opaque dari response Case. Token stale, penugasan tanpa perubahan, Case terminal, Case pada tahap `decided`, `recovery`, `monitoring`, atau `escalated`, serta Case yang dipause withdrawal ditolak fail-closed. Reassign mengakhiri assignment yang dihapus dan mempertahankan seluruh baris histori. `lead_satgas_id` tidak lagi diterima.

### 7.4 Self-Assign Case

```
POST /api/v1/cases/{id}/self-assign
Auth: Bearer Token
Role: satgas_ppks
Permission gate: cases.read.assigned + role/same-campus checks
Policy: CasePolicy@selfAssign
Rate limit: 30 requests/minute
Audit Event: case.self_assigned
```

```json
{
  "lock_version": "9be4...64-character-sha256-token"
}
```

Identitas assignee selalu berasal dari authenticated actor. Field `user_id`, `satgas_id`, `satgas_ids`, `assignee_id`, dan `lead_satgas_id` dilarang. Self-assignment hanya berhasil bila tidak ada assignment aktif dan Case belum memasuki tahap keputusan final/tindak lanjut; transaksi mengunci Report → Case → pending Withdrawal sehingga dua klaim bersamaan menghasilkan tepat satu pemenang.

### 7.5 Update Case Status

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

### 7.6 Risk Assessment

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

### 7.7 Add Investigation Activity

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

### 7.8 Submit Recommendation

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

### 7.9 Record Decision

```
POST /api/v1/recommendations/{recommendation}/decisions
Auth: Bearer Token
Roles: admin (same campus)
Permission: cases.record_decision
Policy: DecisionPolicy@create
Audit Event: decision.created
```

**Request Body:**

```json
{
  "outcome_code": "accepted",
  "decision_date": "2026-06-20",
  "decision_summary": "Ringkasan putusan yang telah ditetapkan.",
  "decision_content": "Berdasarkan rekomendasi Satgas PPKS..."
}
```

`decision_number` is server-owned and remains `null` while the Decision is `draft` or
`recorded`. Create and update requests prohibit `decision_number`, `decision_code`,
`formal_decision_code`, `sequence`, `year`, `nomor_keputusan`, `kode_keputusan`, and
`decision_no`; supplied values return validation `422`.

### 7.9.1 Finalize Decision and Issue Formal Number

```http
PATCH /api/v1/decisions/{decision}/status
Authorization: Bearer <token>
Content-Type: application/json

{"status":"finalized"}
```

Only an active same-campus Admin with `cases.record_decision` may mutate a Decision.
Finalization is valid only from `recorded`, after the approved Recommendation and Case
workflow prerequisites pass. It atomically issues:

```text
SK/PPKS/{application-timezone year}/{global yearly sequence 001..999}
```

The same server timestamp determines the code year and `finalized_at`. The endpoint does not
accept a client number. An identical retry against an already finalized Decision returns the
existing resource without another sequence increment, audit, notification, or Case
transition. Legacy finalized Decisions with a null number remain null.

`409 decision_number_sequence_exhausted` indicates that the annual capacity of 999 has been
reached. `409 decision_number_conflict` indicates a database uniqueness conflict. Both are
transactional failures with no partial finalization.

Read projection is role-aware: same-campus Admin and assigned Satgas retain their authorized
Decision view; Super Admin receives metadata only (including `decision_number`); Reporter and
cross-campus/unauthorized actors receive no Decision projection. GET/resource serialization
never generates or changes a number.

### 7.10 Add Recovery Monitoring

```
POST /api/v1/recoveries/{recovery}/monitoring
Auth: Bearer Token
Roles: satgas_ppks (assigned)
Permission: cases.monitor
Policy: RecoveryPolicy@createMonitoring
Audit Event: recovery.monitoring_created
```

**Request Body:**

```json
{
  "monitoring_date": "2026-06-25",
  "condition_summary": "Korban menunjukkan perkembangan positif.",
  "follow_up_plan": "Lanjutkan sesi konseling berikutnya."
}
```

Monitoring is accepted only while the Recovery is `ongoing`. It does not complete a Recovery or
close a Case automatically.

### 7.11 Close Case

```
POST /api/v1/cases/{case}/close
Auth: Bearer Token
Roles: satgas_ppks (assigned)
Permission: cases.close
Policy: CasePolicy@close
Audit Event: case.closed
```

No request body. The service revalidates the active assignment, terminal Recovery path,
published compatible final summary, Monitoring requirement for the completed path, and rejects
generic Case status transitions to `closed`.

### 7.12 Escalate Case

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
Permission: evidence.view.case (active assigned Satgas; flagged Super Admin oversight uses its dedicated read permission)
```

> **PENTING**: Super Admin tidak memiliki akses default ke Evidence. Read-only sensitive oversight
> memerlukan feature flag dan permission khusus. Emergency Access R2 hanya mengungkap proyeksi
> identitas kepada Satgas pemohon dan tidak memberikan akses Evidence kepada Super Admin.

### 8.4 Download Evidence File

```
GET /api/v1/evidences/{id}/file
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

Dashboard analytics menggunakan endpoint berikut:

```text
GET /api/v1/dashboard/summary
GET /api/v1/dashboard/reports
GET /api/v1/dashboard/cases
GET /api/v1/dashboard/workflow
GET /api/v1/dashboard/evidence
Auth: Bearer Token
Roles: super_admin, admin, satgas_ppks
Cache-Control: private, no-store, max-age=0
```

Reporter tidak mempunyai akses ke dashboard analytics.

Catatan implementasi: middleware `private.no-store` dipertahankan pada seluruh endpoint
Dashboard. Middleware ini hanya menambahkan kebijakan response cache privat
(`Cache-Control: private, no-store, max-age=0`) dan tidak mengubah bentuk response, otorisasi,
atau kontrak filter endpoint.

### 11.1 Filter umum

| Parameter | Nilai | Keterangan |
|---|---|---|
| `date_from` | tanggal ISO | Awal rentang; default 29 hari sebelum `date_to`. |
| `date_to` | tanggal ISO | Akhir rentang; default hari ini. |
| `granularity` | `day`, `week`, `month` | Bucket grafik. |

Rentang maksimal adalah 366 hari. Parameter invalid menghasilkan `422`, bukan fallback diam-diam atau error `500`.

### 11.2 Filter Ringkasan Admin Kampus

Admin tetap dibatasi pada kampusnya sendiri:

- Tanpa parameter: agregasi baseline seluruh kampus Admin.
- `satgas_id={user_id}`: hanya Pengaduan, Kasus, dan data workflow turunannya yang mempunyai penugasan aktif kepada Satgas kampus tersebut.
- `assignment_status=unassigned`: hanya Pengaduan/Kasus yang belum memiliki penugasan aktif.
- `satgas_id` dan `assignment_status` tidak boleh dikirim bersamaan.
- Satgas nonaktif, bukan role Satgas, atau berasal dari kampus lain ditolak dengan `422`.

Contoh:

```text
GET /api/v1/dashboard/summary?satgas_id=42
GET /api/v1/dashboard/reports?assignment_status=unassigned
```

### 11.3 Filter Ringkasan Super Admin

- Tanpa `university_id`: agregasi global seluruh kampus.
- `university_id={id}`: agregasi Pengaduan, Kasus, distribusi, dan workflow terkait kampus aktif tersebut.
- `university_id` hanya diterima untuk Super Admin; role lain memperoleh `422`.

Contoh:

```text
GET /api/v1/dashboard/summary?university_id=7
GET /api/v1/dashboard/reports?university_id=7
```

Filter ini merupakan kontrak dashboard analytics. Daftar operasional Kasus `GET /api/v1/cases` tidak menerima `university_id`; filter Kampus Super Admin pada milestone ini tersedia pada Ringkasan dan daftar Pengaduan.

Kelima endpoint Dashboard (`summary`, `reports`, `cases`, `workflow`, dan `evidence`) menerima
filter role-scoped yang sama. Frontend Ringkasan, Analytics, dan Workflow menyimpan filter pada URL
dan meneruskannya ke setiap query beserta query key, sehingga refresh, navigasi browser, bookmark,
dan cache tidak mencampur scope berbeda.

### 11.4 Satgas Dashboard

Satgas tidak mendapat filter kampus atau Satgas. Seluruh agregasi tetap dibatasi pada Kasus dengan penugasan aktif miliknya.

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

## 13. Anonymous Reporter Emergency Access (REV-WF-03 R2)

Base path: `/api/v1/break-glass`. All endpoints require Sanctum authentication. Anonymous
identity remains masked in ordinary Report, Case, Evidence, and oversight resources.

| Method | Path | Actor and scope | Purpose |
|---|---|---|---|
| `POST` | `/request` | Active Satgas with `privacy.request_break_glass`, active assignment, same campus | Request access for an anonymous Report linked to a Case |
| `GET` | `/mine?case_id={id}` | Satgas requester | List only the authenticated requester's metadata |
| `GET` | `/pending` | Active same-campus Admin with `privacy.approve_break_glass` | Campus pending queue |
| `GET` | `/history` | Active same-campus Admin | Campus request history, including active grants |
| `GET` | `/{request}` | Requesting Satgas or same-campus Admin | Read authorized request metadata |
| `PATCH` | `/{request}/approve` | Same-campus Admin | Activate a pending grant immediately |
| `PATCH` | `/{request}/deny` | Same-campus Admin | Deny a pending request |
| `PATCH` | `/{request}/revoke` | Same-campus Admin | Revoke a currently active grant |
| `POST` | `/{request}/reveal` | Exclusive Satgas requester | Explicitly reveal the minimal identity projection |

Super Admin has no operational Emergency Access endpoint authority. Activity Log oversight is
unchanged and remains redacted.

### 13.1 Create Request

```json
{
  "case_id": 42,
  "reason_category": "investigation_necessity",
  "reason": "A documented, case-specific reason of at least fifty characters.",
  "requested_duration_minutes": 60,
  "acknowledgment": true
}
```

`requested_duration_minutes` accepts exactly `30`, `60`, `240`, or `1440`. The server resolves
the Report from the Case, validates the anonymous classification and Case/Report integrity, then
revalidates the active same-campus assignment. One pending or active request is allowed for each
Report/requester pair. Success returns `201` with request metadata; it never returns Reporter
identity or sensitive Report narrative.

### 13.2 Admin Review

Approval has no request body. In one transaction the server locks the request, revalidates the
requesting Satgas and assignment, and sets `approved_at`, `grant_starts_at`, and `expires_at`.
Approval does not reveal identity to Admin.

Denial and revocation require a reason of 10–2000 characters:

```json
{ "denial_reason": "The documented need is currently insufficient." }
```

```json
{ "revocation_reason": "The case need ended and access is no longer required." }
```

### 13.3 Requester-Only Reveal

`POST /api/v1/break-glass/{request}/reveal` performs no prefetch and revalidates the anonymous
Report, linked Case, exclusive requester, active account, active assignment, campus, grant start,
expiry, and revocation state. Each successful call increments `view_count`, updates
`last_viewed_at`, and writes a critical privacy audit event.

```json
{
  "success": true,
  "data": {
    "name": "Example Reporter",
    "nim": "EXAMPLE-001",
    "email": "reporter@example.test",
    "phone_number": "081234567890",
    "faculty": { "code": "FAC-01", "name": "Example Faculty" },
    "study_program": { "code": "PROG-01", "name": "Example Program" },
    "university": { "code": "UNI-01", "name": "Example University" }
  }
}
```

The projection contains no internal IDs, authentication fields, tokens, session data, or unrelated
account fields. Responses include `Cache-Control: no-store`, `Pragma: no-cache`, and `Expires: 0`.
Expired grants are denied even before their stored status is normalized.

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
| 19 | `POST /recommendations/{recommendation}/decisions` | Decisions |
| 20 | `POST /cases/{case}/close` | Cases |
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
| 5 | `POST /recoveries/{recovery}/monitoring` | Recovery |
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
| 5 | `GET /system-settings` | Settings |
| 6 | `PUT /system-settings/{key}` | Settings |
| 7 | `GET /evidences/{id}` (metadata only) | Evidence |

> Emergency Access is no longer a Post-MVP placeholder. Its executable R2 endpoints are specified
> in Section 13; the former `/cases/{id}/break-glass` and `/break-glass/sessions` proposals do not
> exist.

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
- REV-WF-03 R2 Emergency Access is implemented by Section 13. The R3 contract that supersedes the earlier `final_summary` limitation is defined in Section 18.6.

### 18.6 REV-WF-03 R3 Final Outcome and Closure Contract

R3 supersedes the R1 statement that `final_summary` is always `null`.

```text
GET    /api/v1/cases/{case}/final-summary
POST   /api/v1/cases/{case}/final-summary
PATCH  /api/v1/cases/{case}/final-summary
POST   /api/v1/cases/{case}/final-summary/publish
POST   /api/v1/cases/{case}/close
PATCH  /api/v1/recoveries/{recovery}/status
```

- Same-campus active Admin with `cases.monitor` creates, edits, and publishes the one-to-one final summary. Published summaries and summaries for closed Cases are immutable.
- `outcome_code` is a Case final outcome, separate from `DecisionOutcome`. The final-summary response supplies backend-derived compatible outcome options.
- A `discontinued` Recovery status request requires `discontinuation_reason`. That reason is encrypted and is never projected to the Reporter.
- `POST /cases/{case}/close` is the only future Case-closure mutation. Generic `PATCH /cases/{case}/status` requests for `closed` return `case_generic_closure_forbidden`.
- Completed path: Case `monitoring`, latest Recovery `completed`, at least one Monitoring, a published compatible final summary, and an active assigned Satgas with `cases.close`.
- Discontinued path: Case `recovery`, latest Recovery `discontinued`, a stored reason, a published compatible final summary, and an active assigned Satgas with `cases.close`. Monitoring is not required.
- Reporter handling progress projects only a published safe final summary. Historical closed Cases without a summary return `final_summary.state = legacy_completion` and no invented outcome.
- R3 is implemented in the repository. Deployment is not asserted by this document.

---

> **Catatan**: Dokumen ini adalah Tier 2 (GOVERNED). Perubahan memerlukan persetujuan Project Owner. API specification ini menjadi kontrak wajib antara Backend Agent, Web Agent, dan Mobile Agent.

## 18.7 REV-CONTENT-01 C1 Authenticated Content APIs

All routes require Sanctum authentication. Published responses return only the version referenced by
`published_version_id`, are restricted to global plus the actor's own campus, exclude archived items,
and send `Cache-Control: private, no-store`.

```text
GET  /api/v1/content/sections
GET  /api/v1/content/categories?section={code}
GET  /api/v1/content/article-categories?section={education|policy}
GET  /api/v1/content/articles?section={code}&category={legacyPublicId}&article_category={text}&search={text}&per_page={1..50}
GET  /api/v1/content/articles/{publicId}
GET  /api/v1/content/articles/slug/{section}/{slug}
GET  /api/v1/content/faqs?category={publicId}&search={text}&per_page={1..50}
GET  /api/v1/content/consultation
GET  /api/v1/content/featured?section=education
GET  /api/v1/content/attachments/{attachmentPublicId}
```

The C1 foundation plus C2/C3 authoring surface provides the following authenticated operations.
For Campus Admin these routes are own-campus only. For Super Admin in C3 they expose only global
authoring records; campus-authored content remains available only through the read-only governance
surface. These routes do not provide review decisions.

```text
GET    /api/v1/content-management/items
GET    /api/v1/content-management/summary
GET    /api/v1/content-management/capabilities
GET    /api/v1/content-management/article-categories?section={education|policy}
POST   /api/v1/content-management/article-categories
DELETE /api/v1/content-management/article-categories/{categoryPublicId}
GET    /api/v1/content-management/items/{itemPublicId}
POST   /api/v1/content-management/items
POST   /api/v1/content-management/items/{itemPublicId}/revisions
PATCH  /api/v1/content-management/versions/{versionPublicId}
POST   /api/v1/content-management/versions/{versionPublicId}/submit
POST   /api/v1/content-management/versions/{versionPublicId}/attachments
DELETE /api/v1/content-management/attachments/{attachmentPublicId}
```

The list accepts `content_type`, `lifecycle_status`, legacy category public ID, free-text
`article_category`, escaped search, `page`, and `per_page`. `article_category` matches the Article's
trimmed `category_name`, with a fallback to the retained legacy category relation. Campus Admin
list/detail/summary results include only the authenticated Admin's campus;
Super Admin authoring results include only global scope. Foreign-scope records return no list result
and cannot be opened directly. Detail responses project the controlled draft document, typed
Article/FAQ/Consultation fields, version-owned attachment metadata, the active Article cover
resource, the author's revision/rejection feedback, and permission-gated editorial attribution.
Campus Admin receives creator and submitter `name`, `email`, and nullable `role`, but reviewer,
approver, and publisher actor objects are always `null`; stage timestamps remain visible. Super
Admin receives all five actor objects. Internal numeric user IDs remain excluded. Revision creation
requires a published campus item with no active authoring version.
Attachment deletion is limited to an editable authorized version. A selected cover is detached before
deletion; referenced inline media returns 422 until the author removes its `imageReference` and saves
the document. General PDF and unreferenced media deletion is audited as
`content.attachment_removed` only after the private object and metadata are both removed.
If private-storage deletion fails, the API returns `503` with
`content_attachment_deletion_failed`; metadata and bytes remain available for a retry.

`POST /content-management/versions/{versionPublicId}/submit` requires the integer
`lock_version` returned by the management item/detail resource. The service locks and reloads the
version and item, then returns `409 content_stale_version` when the submitted value is stale. Draft
update uses the same conflict code. Archived content returns `409 content_archived` for update,
submit, revision creation, attachment upload, and attachment removal. Item, version, and attachment
UUIDs outside the Admin's campus—including global UUIDs—resolve as `404` on the management surface.

Article reader resources expose public ID, slug, title, plain excerpt, safe section/category/scope,
cover projection, publication time, computed reading time, and—for detail only—the controlled body,
sanitized projection, referenced `inline_images`, general PDF `attachments`, and related published
scope-safe Articles. Consultation is a
dedicated reader resource and is not accepted, projected, or resolved as an Article CTA. The legacy
Article CTA database relation remains nullable only for backward-compatible data retention. FAQ and
Consultation resources expose only approved reader fields.
No reader resource contains internal numeric IDs, author/editor/reviewer identifiers, review reasons,
draft pointers, private paths, checksums, encrypted narratives, or unpublished versions.

Article `document` input and reader `body` output use the version-owned controlled JSON contract.
Canonical nodes are `doc`, `paragraph`, `text`, `heading` (level 2 or 3), `bulletList`,
`orderedList`, `listItem`, `blockquote`, `horizontalRule`, `callout`
(`information|warning|help`), and `imageReference`. An `imageReference` contains only
`attachment_public_id` and `alt`; it never contains a storage URL, Base64 data, blob URL, remote URL,
or binary payload. Canonical marks
are `bold`, `italic`, `underline`, and `link`. Link attributes are limited to `href` and optional
`title`; accepted protocols are valid `http`, `https`, `mailto`, and `tel`. Client-supplied
`target`, `rel`, `class`, `style`, event handlers, raw HTML, H1, or any unknown node/mark/attribute
is rejected. Safe rendered external links receive `rel="noopener noreferrer"`.

Before persistence the server normalizes historical aliases `unorderedList`, `divider`,
`heading_2`, `heading_3`, `info`, `warning`, and `help` to the canonical contract and wraps supported
legacy inline list/quote/callout children in paragraphs. Normalization occurs only during an
authorized write or revision copy; it does not rewrite the existing published version. Malformed
JSON produces the standard validation response. The server rejects documents above 500,000
serialized bytes, 1,000 nodes, depth 12, 200,000 total text characters, 20,000 characters per text
node, four marks per text node, 2,000 total marks, or 2,048 characters per link.

Article authoring requires trimmed free-text `category_name` (maximum 100 characters). Category
metadata follows the lifecycle on `content_versions`: management responses project the editable or
latest version, while reader responses project only the version referenced by `published_version_id`.
The nullable legacy `category_public_id` remains accepted only as a compatibility bridge. On every
Article create or draft/revision update, a non-null `category_name` wins over any submitted
`category_public_id` and the version's legacy `category_id` is cleared. `category_public_id` is
resolved only when `category_name` is null. Changing an Article category is metadata editing, not a
section/scope placement change, and cannot affect readers before the version is published.
Consultation authoring and
reader resources support nullable `service_type`, `procedure`, and `confidentiality_info` fields in
addition to the verified institutional contact fields.

The management category endpoint returns structured registry entries with `public_id`, `name`,
`section_code`, `scope`, `usage_count`, `can_manage`, and `can_deactivate`. `POST` accepts the fixed
Article `section` (`education` or `policy`) plus a trimmed `name` of at most 100 characters containing
at least one letter or number. Campus Admin creates an own-campus registry entry; Super Admin creates
a global entry. Names are NFC-normalized when supported, internal whitespace is collapsed, and
case-insensitive identity is stored in `normalized_name`. A new entry returns 201 with
`result=created`; an active duplicate returns 200 with `result=existing`; an inactive duplicate is
reactivated and returns 200 with `result=reactivated`. All three responses contain current
`usage_count` and `can_deactivate` metadata. `DELETE` is a non-destructive deactivation and returns
`409 content_category_in_use` with `usage_count` when the category is referenced by content. Registry
mutations use `throttle:30,1`; the category list uses `throttle:60,1`. Reporter and Satgas cannot use
the management registry. `content_versions.category_name` is the canonical lifecycle value; retained
item-level category columns are compatibility/denormalized metadata and are not reader sources. A
version's legacy `category_id` fallback is used only when its `category_name IS NULL`. GET usage
metadata and DELETE eligibility count each content item once per category across its active
`current_draft_version_id` and `published_version_id` pointers. Published A plus draft B therefore
temporarily uses both A and B without consulting stale item metadata. Duplicate identity is limited to
`section_id + scope_key + normalized_name`: the same normalized name is allowed between Global and
Campus scopes and between different campuses, independent of creation order. A second normalized
match within the same Global scope or the same campus scope resolves to the existing/reactivated
contract above, including under a concurrent unique-constraint race.

Article detail supports the existing UUID public-ID route and the section-aware slug route
`/content/articles/slug/{section}/{slug}`, where `section` is `education` or `policy`. The reader first
filters the requested section and published lifecycle, then prefers the actor's own-campus Article over
the global Article with the same slug. Draft, review, rejected, archived, and future-published versions
return 404. A slug without an explicit section is not a valid route.

`POST /content-management/versions/{versionPublicId}/attachments` is multipart with required
`purpose` and `file`. `purpose=attachment` accepts PDF only up to 10 MB.
`purpose=cover` accepts JPEG/PNG/WebP up to 5 MB and `purpose=inline_image` accepts the same formats
up to 10 MB; both image purposes require `alt_text`. Image attempts return 422 unless
`CONTENT_IMAGE_UPLOADS_ENABLED=true` and the runtime GD processor confirms all required
decode/encode/orientation functions. The processor verifies fileinfo MIME against image signature,
checks dimension/pixel/memory budgets before decode, normalizes JPEG orientation, strips metadata by
decode/re-encode, and verifies the encoded output again. SVG and remote retrieval are never accepted.

`GET /content-management/capabilities` returns `image_upload_available`, `image_formats`,
`cover_max_bytes`, and `inline_image_max_bytes`. File resources include generated names, purpose,
detected MIME, safe size/dimensions/alt text, and the authenticated relative `download_url`; they
never expose private path, checksum, internal ID, uploader ID, or protected original filename.
Download headers use generated names such as `lampiran-{attachmentPublicId}.pdf`.

The authenticated attachment reader returns a draft binary only to an authorized manager. Reporter
access additionally requires the exact published pointer, published lifecycle, reader campus scope,
and an active reference: selected cover FK for `cover`, controlled-document reference for
`inline_image`, or published general attachment ownership for PDF. Unknown, foreign, draft, and
orphan media all resolve as 404. Responses remain `private, no-store` and `nosniff`.

New inline media can enter the editor only after this upload response is registered as an authorized
reference. Pasted/dropped files, pasted HTML images, arbitrary UUID insertion, and URL-based media
remain blocked. Revision creation clones immutable private bytes to new UUID paths and rewrites cover
and inline references, leaving the prior published version unchanged.

## 18.8 REV-CONTENT-01 C3 Editorial Governance APIs

All routes require Sanctum, the `super_admin` role, and the explicit route permission shown by the
operation. Policies and locked services repeat authorization. Draft, decision, and governance
responses use `Cache-Control: private, no-store` and never serialize internal IDs, private paths,
checksums, protected original filenames, or encrypted review storage.

```text
content.read.management.all:
GET  /api/v1/content-governance/reviews
GET  /api/v1/content-governance/published
GET  /api/v1/content-governance/campuses
GET  /api/v1/content-governance/categories?section={code}
GET  /api/v1/content-governance/items/{itemPublicId}

content.review:
POST /api/v1/content-governance/versions/{versionPublicId}/start-review
POST /api/v1/content-governance/versions/{versionPublicId}/request-revision
POST /api/v1/content-governance/versions/{versionPublicId}/reject
POST /api/v1/content-governance/versions/{versionPublicId}/approve
POST /api/v1/content-governance/versions/{versionPublicId}/publish

content.archive:
POST /api/v1/content-governance/items/{itemPublicId}/archive

content.feature.manage:
GET    /api/v1/content-governance/featured
GET    /api/v1/content-governance/featured/eligible
GET    /api/v1/content-governance/featured/campuses
POST   /api/v1/content-governance/featured
PATCH  /api/v1/content-governance/featured/{placementPublicId}
DELETE /api/v1/content-governance/featured/{placementPublicId}
```

The review list is server-paginated and accepts review state, scope, content type, section, category
public ID, campus code, submission-date range, escaped search, `page`, and `per_page`. Its default
states are `submitted`, `in_review`, and `approved`. Published items eligible for archival governance
use the separate `/published` query. That query projects only `published_version_id`, including while
a newer draft, submitted, in-review, approved-only, revision-requested, or rejected version exists;
those versions never replace the Published Library projection. Detail projects the submitted/current
version, the previous published version when different, safe PDF resources, server capabilities,
author editorial note, and an append-only editorial timeline built only from actual audit actions.

Governance resources project the following primary attribution to an active Super Admin with
`content.read.management.all`:

```json
{
  "created_by": { "name": "Editor", "email": "editor@example.test", "role": "admin" },
  "submitted_by": { "name": "Editor", "email": "editor@example.test", "role": "admin" },
  "reviewed_by": { "name": "Reviewer", "email": "reviewer@example.test", "role": "super_admin" },
  "approved_by": { "name": "Reviewer", "email": "reviewer@example.test", "role": "super_admin" },
  "published_by": { "name": "Publisher", "email": "publisher@example.test", "role": "super_admin" },
  "created_at": "2026-07-23T10:00:00Z",
  "version": {
    "version_number": 2,
    "submitted_at": "2026-07-23T11:00:00Z",
    "reviewed_at": "2026-07-23T12:00:00Z",
    "approved_at": "2026-07-23T12:00:00Z",
    "published_at": "2026-07-23T13:00:00Z"
  }
}
```

`created_by` is the item creator. `submitted_by` and `published_by` are version-level foreign keys.
`reviewed_by` is selected only from `review_started`, `revision_requested`, and `rejected`
decisions; `approved_by` is selected only from `approved`. Both use
`decided_at DESC, id DESC`. `direct_global_published`, `archived`, publication, featured-placement,
and other administrative actions cannot become a reviewer or approver. `published_by` never falls
back to a review decision. Nullable values are returned as `null`; the API never substitutes the
current caller.

Campus Admin management responses expose only `created_by` and `submitted_by` identities. Their
`reviewed_by`, `approved_by`, and `published_by` values are `null`. The Admin detail
`editorial_timeline` contains only actual audit events, masks central editorial actors as
`label=central_team` with null name/email/role, and exposes escaped revision/rejection notes. Super
Admin governance `decision_history` includes full authorized actor name/email/role. Both timelines
are ordered by `created_at ASC, id ASC`, capped at 200 recent events, and expose a boolean
`*_truncated` flag. Decision notes are paired by version number and action state, with
`decided_at ASC, id ASC` as the deterministic occurrence order; timestamp equality alone is never
used as identity. Repeated submissions remain separate events. Global content has `scope=global`
and `university=null`; clients label that as “Semua Kampus”.

Campus Admin creation is actor-bound: only `scope=campus` with the authenticated Admin's
`university_id` is accepted; Global or foreign-campus input is rejected. Super Admin global
authoring remains `scope=global`, saves as draft, and requires explicit submit, review, approval,
and publication actions. The existing creator/author/editor separation-of-duties checks remain.

Start review, revision request, rejection, approval, publication, and archive require the integer
item `lock_version`. Revision/rejection/archive reasons are 10-2,000 characters; approval note is
optional. `content_stale_review`, `content_invalid_lifecycle_transition`, `content_archived`, and
`content_active_authoring_version` are stable conflict codes. Approval and publication remain two
distinct lifecycle transitions. Approval reruns publishability validation; publication locks the
same approved version and atomically updates the published pointer. A content author cannot review
or publish their own version; creator, author, and editor checks also apply to global Super Admin
content after transactional locking. No direct global publication service exists: global content
must be submitted, reviewed and approved by an eligible second Super Admin before publication.

Reporter/public reader resources do not contain any `created_by`, `submitted_by`, `reviewed_by`,
`approved_by`, `published_by`, editorial timeline, review identity, or actor email field. Management
and governance attribution responses remain `private, no-store` and permission gated. Paginated
list queries eager load only deterministic latest relevant-review and latest-approval one-of-many
relations; they do not load the full decision collection.

Governance PDF actions fetch `/content/attachments/{attachmentPublicId}` with the authenticated
Bearer session and create only a temporary browser Blob URL. Protected endpoints are never opened
through raw anchor navigation and tokens are never placed in query strings. TanStack governance
queue, detail/history, Published Library, category/campus options, featured placements, and featured
eligibility requests forward cancellation signals to the authenticated fetch layer.

Featured create accepts authoritative content public ID, exact global/campus scope, campus code when
campus-scoped, rank 1-5, active flag, and optional active window. Update and delete require the opaque
`concurrency_token` returned by the placement resource. The backend resolves eligibility from the
current published Article pointer and returns `content_featured_stale` or
`content_featured_conflict` on concurrency/rank conflicts. Placement windows do not schedule content
publication.

## 18.9 REV-CONTENT-01 C4 Authenticated Reader Client

C4 consumes the existing authenticated endpoints in section 18.7 without changing the publication
lifecycle or adding public routes. Reporter, Satgas, Campus Admin, and Super Admin may read only when
their active account has `content.read.published`. Every response remains `private, no-store` and
contains global plus own-campus published content only.

Reporter Information Center routes are:

```text
GET /portal/information-center
GET /portal/information-center/education
GET /portal/information-center/education/{slug}
GET /portal/information-center/policies
GET /portal/information-center/policies/{slug}
GET /portal/information-center/faq
GET /portal/information-center/consultation
```

`/portal/information-center` is a directory only. Article search and category state live on the
dedicated Education and Policy routes and are forwarded to the server; FAQ and Consultation have
dedicated routes. Reporter detail navigation uses section-aware slugs and breadcrumbs. The Reporter
dashboard's Sorotan Edukasi requests the explicit `education` filter and preserves active placement
rank/window order. Featured governance accepts only published Education Articles; a Policy placement
is never projected into Sorotan Edukasi. If a safe cover is absent or cannot be loaded, the client
renders an Education-themed CSS fallback.

Article cards and detail use only the published resource fields already defined in section 18.7.
Detail renders the controlled `body` JSON and does not execute `body_html`. Safe PDF, cover, and
referenced inline-image resources are retrieved from `/content/attachments/{attachmentPublicId}`
with the Bearer session; tokens never enter the URL. The API separately projects `cover`,
`inline_images`, and general PDF `attachments` from the exact published version. The client uses
temporary object URLs and revokes them on replacement, error, or unmount. Consultation actions do not place Report, registration, identity, or
incident data into an outbound URL.

## REV-WITHDRAW-01A Direct Reporter Cancellation

```text
POST /api/v1/portal/reports/{registrationNumber}/cancel
Auth: Sanctum
Role: active Reporter
Permission: reports.cancel.own
Middleware: private.no-store, throttle:reporter.cancellation
```

Request:

```json
{
  "reason": "Alasan pembatalan dengan panjang 20 sampai 2.000 karakter."
}
```

The endpoint is available only when `REPORT_EARLY_CANCELLATION_ENABLED=true`. The registration
number is not ownership proof: the locked Report must have a non-null `reporter_id` equal to the
authenticated actor. Eligibility is exact: Report status `submitted`, no Case, `forwarded_at` null,
no active withdrawal, active Reporter, and the required permission. An authenticated owner of an
anonymous Report remains eligible; a legacy anonymous row with null `reporter_id` remains hidden.
The server trims leading and trailing Unicode whitespace before applying the required 20–2,000
Unicode-character limits. Internal whitespace and newlines are preserved, and only the normalized
value is encrypted and stored.

Success returns only the public withdrawal reference, Report status `cancelled`, Reporter-safe
status `cancelled_by_reporter`, completion timestamp, and the latest capabilities. It never returns
the withdrawal reason, internal IDs, campus metadata, or audit metadata. Validation failures use
422, hidden ownership uses 404, authorization uses 403, state/concurrency conflicts use 409, and
the disabled flag uses 409 with `report_cancellation_feature_disabled`.

`GET /api/v1/portal/reports/{registrationNumber}` additionally projects:

```json
{
  "withdrawal_capabilities": {
    "can_cancel": false,
    "can_request_withdrawal": true,
    "cancellation_block_reason_code": "case_exists",
    "withdrawal_block_reason_code": null,
    "active_withdrawal": null
  }
}
```

The capability is backend-authoritative. Direct cancellation and formal withdrawal are mutually
exclusive in the Reporter UI.

Case status `withdrawn` is operationally terminal. Existing Case detail/read endpoints remain
available to authorized assigned Satgas for historical visibility, but active/workload projections
exclude it. Every Case or child-workflow mutation rechecks the locked Case and returns `409` with
`case_operationally_terminal`; active assignment rows are retained and are not mutation authority
for a terminal Case.

## REV-WITHDRAW-01B Formal Reporter Withdrawal

Formal withdrawal is available only when `REPORT_FORMAL_WITHDRAWAL_ENABLED=true`, the active
Reporter has `reports.withdraw.own`, exact non-null ownership is verified, the Report is forwarded
or has a Case, and the Report/Case/Decision state remains eligible. A legacy anonymous Report with
null `reporter_id` is hidden; an authenticated anonymous owner is supported with a masked document
identity.

All endpoints use Sanctum, `private.no-store`, public UUID references, and an explicit named
throttle:

| Method | Endpoint | Purpose | Throttle |
|---|---|---|---|
| `GET` | `/api/v1/portal/reports/{registrationNumber}/withdrawal` | Restore the active Reporter wizard | `reporter.withdrawal.read` |
| `POST` | `/api/v1/portal/reports/{registrationNumber}/withdrawals` | Create a `draft` request | `reporter.withdrawal.create` |
| `GET` | `/api/v1/portal/withdrawals/{publicId}/draft-document` | Render authenticated print-safe DRAFT HTML | `reporter.withdrawal.document` |
| `POST` | `/api/v1/portal/withdrawals/{publicId}/signed-document` | Upload an immutable signed-document version | `reporter.withdrawal.upload` |
| `GET` | `/api/v1/portal/withdrawals/{publicId}/signed-document/{attachmentPublicId}` | Download an owned private version | `reporter.withdrawal.document` |
| `POST` | `/api/v1/portal/withdrawals/{publicId}/submit` | Move `waiting_document` to `pending_review` | `reporter.withdrawal.mutate` |
| `POST` | `/api/v1/portal/withdrawals/{publicId}/cancel` | Cancel an active request | `reporter.withdrawal.mutate` |

Create accepts JSON `{ "reason": "..." }`. Boundary Unicode whitespace is trimmed before the
20–2,000 character validation; the normalized reason is encrypted. The owner detail may project the
owner's reason for the wizard, but summary, notification, timeline, and audit projections omit it.

The active state machine in 01B is:

```text
draft -> waiting_document -> pending_review
draft | waiting_document | pending_review -> cancelled
```

`GET draft-document` is read-only for withdrawal lifecycle state, timestamps, and `lock_version`.
The first accepted signed-document upload is the explicit authenticated mutation that performs
`draft -> waiting_document`; later uploads remain in `waiting_document`. Upload, submit, and cancel
must include the current `lock_version`, and stale values return HTTP 409 with `stale_update`.
No `approved` or `rejected` endpoint exists in this submilestone. Report and Case status values do
not change. While a formal request is `pending_review`, operational Case mutations return HTTP 409
with `error_code=withdrawal_pending_review`; read-only detail and history remain available.

Signed-document upload is multipart with fields `file` and current integer `lock_version`. Submit
and cancel accept JSON `{ "lock_version": <current> }`. Allowed formats are PDF, JPEG, and PNG, at
most 10 MiB. The server enforces detected/declared MIME parity, matching single extension, non-empty
content, structural checks, image pixel limits, safe filename handling, a private disk, a UUID path,
and SHA-256 integrity. SVG, executable/remote content, raw storage paths, and public URLs are not
accepted or projected. Each upload creates the next immutable
`signed_withdrawal_statement` version. Submission rechecks the latest stored file size/checksum
inside the transaction.

The owner response contains only public references, Reporter-safe status/timestamps, `lock_version`, the latest safe
attachment plus immutable attachment-history metadata (`document_type`, `version`, MIME, size, upload time), and authoritative
capabilities (`can_view_draft`, `can_upload_document`, `can_submit`,
`can_cancel_request`, `can_resubmit`). Attachment history is ordered newest-first and remains
owner-authenticated after a final decision. Internal IDs, disk, path, hash, original filename, reviewer metadata, and
attachment contents are absent.

Submission sends `NOTIF-26` after commit only to active same-campus Admin users with
`reports.withdraw.review.own_campus`. Cancelling a request that had reached `pending_review` sends
`NOTIF-27` to the same recipient scope. Neither notification contains the reason or an attachment
URL.

### REV-WITHDRAW-01C Admin review and resubmission

The formal state machine is extended without changing the Report/Case state before a decision:

```text
pending_review -> approved
pending_review -> rejected
rejected -> new draft (only when resubmission_allowed=true)
```

Campus Admin review endpoints use `private.no-store`, Sanctum, named throttles, the active
`reports.withdraw.review.own_campus` permission, and exact campus authorization:

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/v1/report-withdrawals` | Oldest-first paginated queue; default `status=pending_review`; optional `status`, `search`, `page`, and `per_page` |
| `GET` | `/api/v1/report-withdrawals/{publicId}` | Role-filtered detail and server-authoritative review capabilities |
| `GET` | `/api/v1/report-withdrawals/{publicId}/signed-document/{attachmentPublicId}` | Private latest signed-document preview/download for an authorized campus Admin |
| `POST` | `/api/v1/report-withdrawals/{publicId}/approve` | Approve with `{ "lock_version": n, "confirmed": true }` |
| `POST` | `/api/v1/report-withdrawals/{publicId}/reject` | Reject with `lock_version`, normalized 20-2,000 character `rejection_reason`, and boolean `resubmission_allowed` |
| `POST` | `/api/v1/portal/withdrawals/{publicId}/resubmit` | Owner-only creation of a fresh draft with new `reason` and the rejected request's `lock_version` |

Approval locks Report, Case when present, and Withdrawal, then revalidates reviewer, campus,
optimistic version, current Report/Case/Decision state, and latest document integrity. It revokes
related active break-glass grants, marks the request `approved`, and changes Report and Case to
`withdrawn` with `withdrawn_at`. It does not set `closed_at`, delete assignments, evidence,
documents, or history, or create a Case. A stale or changed state returns HTTP 409 without partial
finalization. `withdrawn` is permanently read-only and remains historical rather than closed or
decided workload.

Rejection changes only the request, records encrypted rejection text and the resubmission choice,
and releases the operational pause. A permitted resubmission creates an independent `draft` whose
`supersedes_id` points to the rejected request; attachments are not copied, and one rejected request
can be superseded only once. New request creation and resubmission require
`REPORT_FORMAL_WITHDRAWAL_ENABLED=true`. Admin review of an already submitted request remains
available while the flag is off so pending work is not stranded.

Admin list responses omit reasons and attachment metadata. Admin detail may include the Reporter
reason, rejection reason, safe version/MIME/size metadata, and capabilities, but never internal IDs,
disk/path, checksums, raw original filenames, or document URLs. Super Admin receives cross-campus
monitoring metadata only and no reason, document, or mutation capability. Satgas does not use these
endpoints and receives only generic pause/withdrawn state through existing Case reads. Elapsed
waiting time is informational and is not an SLA; it stops at `reviewed_at` or `cancelled_at` after
a final outcome.

Search treats `%`, `_`, and the escape marker as literal characters rather than caller-controlled
wildcards. Approval/rejection notifications contain public references and status only; they omit
reasons, document links, and internal subject IDs.
