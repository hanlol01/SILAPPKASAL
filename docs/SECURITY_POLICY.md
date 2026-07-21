# SECURITY_POLICY.md — Security Policy & Standards

> **Sistem Informasi Laporan Pencegahan dan Penanganan Kekerasan Seksual (SILAPPKASAL)**
> Versi: REV-WF-03-R2 | Terakhir Diperbarui: 2026-07-20 | Status: BERLAKU | Tier: 2 (GOVERNED)

---

## Daftar Isi

1. [Security Principles](#1-security-principles)
2. [Threat Model](#2-threat-model)
3. [OWASP Considerations](#3-owasp-considerations)
4. [Input Validation Rules](#4-input-validation-rules)
5. [File Upload Security](#5-file-upload-security)
6. [Rate Limiting Strategy](#6-rate-limiting-strategy)
7. [RBAC Policy](#7-rbac-policy)
8. [Audit Logging Policy](#8-audit-logging-policy)
9. [Password Policy](#9-password-policy)
10. [Session Security](#10-session-security)
11. [Token Security](#11-token-security)
12. [Database Security](#12-database-security)
13. [Encryption Strategy](#13-encryption-strategy)
14. [Private Storage Policy](#14-private-storage-policy)
15. [Backup Policy](#15-backup-policy)
16. [Incident Response](#16-incident-response)
17. [Cloudflare Recommendation](#17-cloudflare-recommendation)
18. [VPS Hardening Recommendation](#18-vps-hardening-recommendation)
19. [Laravel Queue Security](#19-laravel-queue-security)
20. [WhatsApp Integration Security](#20-whatsapp-integration-security)

---

## 1. Security Principles

### 1.1 Prinsip Utama

| # | Prinsip | Deskripsi | Penerapan |
|---|---------|-----------|-----------|
| 1 | **Security First** | Keamanan adalah prioritas utama. Setiap keputusan desain mempertimbangkan dampak keamanan. | Review keamanan wajib untuk setiap fitur baru. |
| 2 | **Defense in Depth** | Keamanan berlapis. Tidak mengandalkan satu mekanisme saja. | Validasi di middleware, policy, dan service layer. |
| 3 | **Principle of Least Privilege** | Setiap pengguna/komponen hanya memiliki akses minimum yang dibutuhkan. | RBAC ketat, scoped database queries. |
| 4 | **Fail Secure** | Jika terjadi kegagalan, sistem harus gagal ke kondisi aman. | Default deny, whitelist approach. |
| 5 | **Separation of Duties** | Pemisahan tanggung jawab antar role. | Admin ≠ Satgas. Verifikasi ≠ Investigasi. |
| 6 | **Victim-Centered Security** | Keamanan diprioritaskan untuk melindungi korban dan pelapor. | Enkripsi data korban, anonimitas terjaga. |
| 7 | **Auditability** | Setiap aksi yang menyentuh data kasus harus tercatat. | Audit log pada semua operasi sensitif. |
| 8 | **No Security by Obscurity** | Keamanan tidak bergantung pada kerahasiaan implementasi. | Standar industri (AES-256, Argon2id, TLS 1.3). |

### 1.2 Klasifikasi Data

| Klasifikasi | Contoh Data | Perlakuan Wajib |
|-------------|-------------|-----------------|
| **CRITICAL** | Identitas korban, kronologi kekerasan, bukti digital, identitas terlapor | Field-level encryption (AES-256), akses sangat terbatas (hanya Satgas assigned), audit log wajib pada setiap akses |
| **CONFIDENTIAL** | Identitas pelapor, hasil investigasi, rekomendasi, keputusan | Encryption at rest, RBAC ketat, audit log wajib |
| **INTERNAL** | Data operasional Satgas, catatan internal, SLA data | RBAC, akses internal saja |
| **PUBLIC** | Statistik agregat (tanpa identitas), informasi umum sistem | Boleh diakses publik. Pastikan tidak ada data identitas yang bocor. |

### 1.3 Compliance

Sistem harus mematuhi:

- **UU No. 27 Tahun 2022** — Pelindungan Data Pribadi (UU PDP)
- **UU No. 12 Tahun 2022** — Tindak Pidana Kekerasan Seksual (UU TPKS)
- **Permendikbudristek No. 30 Tahun 2021** — PPKS di Lingkungan Perguruan Tinggi
- **Permendikbudristek No. 24 Tahun 2024** — Perubahan atas No. 30/2021

---

## 2. Threat Model

### 2.1 Aset yang Dilindungi

| Aset | Nilai | Dampak jika Terkompromi |
|------|-------|------------------------|
| Identitas korban | CRITICAL | Re-victimization, stigma sosial, bahaya fisik |
| Identitas pelapor anonim | CRITICAL | Pengungkapan identitas, intimidasi, retaliasi |
| Bukti digital | CRITICAL | Penghancuran bukti, penyalahgunaan konten intim |
| Data investigasi | CONFIDENTIAL | Gangguan proses hukum, kebocoran informasi |
| Kredensial pengguna | CONFIDENTIAL | Akses tidak sah ke sistem |
| Data statistik agregat | INTERNAL | Manipulasi data kebijakan |

### 2.2 Pelaku Ancaman (Threat Actors)

| Pelaku | Motivasi | Kapabilitas | Mitigasi |
|--------|----------|-------------|----------|
| **Terlapor** | Mengakses/menghapus laporan tentang dirinya | Rendah–Menengah | RBAC ketat, data isolation, audit log |
| **Insider (Admin/Satgas)** | Kebocoran data karena lalai atau niat jahat | Menengah–Tinggi | Least privilege, audit log, separation of duties |
| **External Attacker** | Data breach, defacement, ransomware | Menengah | OWASP mitigasi, WAF, VPS hardening |
| **Pihak institusi** | Menekan untuk menghapus/mengubah data | Tinggi (akses politis) | Immutable audit log, backup off-site, no soft-delete pada audit |
| **Automated Bots** | Brute force, spam laporan | Rendah | Rate limiting, CAPTCHA (future) |

### 2.3 Attack Vectors

```
┌─────────────────────────────────────────────────────────┐
│                    ATTACK SURFACE                        │
│                                                          │
│  Internet-facing:                                        │
│  ├── Web Application (React)                             │
│  │   ├── XSS via user input                              │
│  │   ├── CSRF (mitigated: stateless API)                 │
│  │   └── Clickjacking                                    │
│  │                                                       │
│  ├── REST API (Laravel)                                  │
│  │   ├── SQL Injection                                   │
│  │   ├── Broken Authentication                           │
│  │   ├── Broken Access Control (IDOR)                    │
│  │   ├── Mass Assignment                                 │
│  │   ├── Rate Limit Bypass                               │
│  │   └── File Upload Abuse                               │
│  │                                                       │
│  └── File Storage (Private)                              │
│      ├── Unauthorized file access                        │
│      └── Malware upload                                  │
│                                                          │
│  Internal:                                               │
│  ├── Database access                                     │
│  ├── Server access                                       │
│  └── Insider threat                                      │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## 3. OWASP Considerations

Mitigasi berdasarkan OWASP Top 10 (2021):

### 3.1 Matriks OWASP

| # | OWASP Category | Risiko di SILAPPKASAL | Mitigasi |
|---|----------------|----------------------|----------|
| A01 | **Broken Access Control** | Akses ke kasus orang lain, privilege escalation | RBAC + Policy per resource, scoped queries, middleware role check |
| A02 | **Cryptographic Failures** | Bocornya data korban dari DB/storage | AES-256 field-level encryption, TLS 1.3, Argon2id password hashing |
| A03 | **Injection** | SQL injection, command injection | Eloquent ORM (parameterized), FormRequest validation, no raw queries |
| A04 | **Insecure Design** | Data korban terekspos di response | API Resource transformasi, data classification, security review wajib |
| A05 | **Security Misconfiguration** | Debug mode on production, default credentials | `.env` management, APP_DEBUG=false, security headers |
| A06 | **Vulnerable Components** | Library dengan CVE | `composer audit`, `npm audit`, dependency monitoring |
| A07 | **Auth Failures** | Brute force login, session hijacking | Rate limiting, token expiry, Sanctum revocation |
| A08 | **Software Integrity** | Supply chain attack | Lock files (composer.lock, package-lock.json), verified packages |
| A09 | **Logging Failures** | Tidak bisa trace breach | Comprehensive audit logging, immutable logs, alerting |
| A10 | **SSRF** | API memanggil URL berbahaya | Tidak ada user-supplied URL fetching, whitelist external services |

---

## 4. Input Validation Rules

### 4.1 Prinsip Validasi

| Prinsip | Detail |
|---------|--------|
| **Server-side wajib** | Semua validasi harus dilakukan di backend (Laravel FormRequest). Client-side hanya untuk UX. |
| **Whitelist approach** | Validasi berdasarkan apa yang dibolehkan, bukan apa yang dilarang. |
| **Strict typing** | `declare(strict_types=1)` di semua file PHP. TypeScript `strict: true`. |
| **Sanitize output** | Escape semua output ke HTML. Laravel Blade auto-escapes. React auto-escapes. |
| **No trust boundary crossing** | Data dari client dianggap tidak terpercaya. Selalu re-validate di setiap layer. |

### 4.2 Aturan Validasi per Entitas

#### User Registration

| Field | Rules | Sanitization |
|-------|-------|-------------|
| `name` | `required`, `string`, `max:255` | `strip_tags`, `trim` |
| `email` | `required`, `email:rfc,dns`, `unique:users`, `max:255` | `lowercase`, `trim` |
| `nim` | `nullable`, `string`, `regex:/^[0-9]+$/`, `unique:users`, `max:20` | `trim` |
| `nip` | `nullable`, `string`, `regex:/^[0-9]+$/`, `unique:users`, `max:20` | `trim` |
| `phone_number` | `required`, `string`, `regex:/^628[0-9]{8,12}$/`, `max:15` | `trim` |
| `password` | `required`, `string`, `min:8`, `regex:/[a-zA-Z]/`, `regex:/[0-9]/`, `confirmed` | — |

#### Report Submission

| Field | Rules | Sanitization |
|-------|-------|-------------|
| `report_type` | `required`, `in:open,confidential,anonymous` | — |
| `category_code` | `required`, `exists:report_categories,code` | — |
| `chronology` | `required`, `string`, `min:50`, `max:10000` | `strip_tags` (tapi simpan original encrypted) |
| `incident_date` | `required`, `date`, `before_or_equal:today` | — |
| `incident_time` | `nullable`, `date_format:H:i` | — |
| `incident_location` | `required`, `string`, `max:500` | `strip_tags`, `trim` |
| `respondent_name` | `nullable`, `string`, `max:255` | `strip_tags`, `trim` |
| `respondent_campus_status` | `nullable`, `in:mahasiswa,dosen,tendik,alumni,pihak_luar` | — |
| `respondent_relation` | `nullable`, `exists:relations,code` | — |

#### Risk Assessment

| Field | Rules |
|-------|-------|
| `risk_level` | `required`, `in:low,medium,high` |
| `justification` | `required`, `string`, `min:20`, `max:5000` |
| `protection_steps` | `required`, `string`, `max:5000` |
| `emergency_protection_needed` | `required`, `boolean` |

### 4.3 Perlindungan Terhadap Injection

```php
// ✅ BENAR: Eloquent (parameterized)
$reports = Report::where('status', $status)
    ->where('reporter_id', $userId)
    ->get();

// ❌ SALAH: Raw SQL tanpa binding
$reports = DB::select("SELECT * FROM reports WHERE status = '$status'");

// ✅ BENAR: Raw query dengan binding (jika benar-benar diperlukan)
$reports = DB::select('SELECT * FROM reports WHERE status = ?', [$status]);
```

---

## 5. File Upload Security

### 5.1 Validasi Upload

| Check | Detail | Implementasi |
|-------|--------|-------------|
| **Ekstensi** | Whitelist: JPG, JPEG, PNG, GIF, MP4, MOV, PDF, DOC, DOCX | Laravel `mimes` validation |
| **MIME type** | Validasi MIME type di server | Laravel `mimetypes` validation |
| **Ukuran** | Maks 25 MB per file | Laravel `max:25600` (KB) |
| **Jumlah** | Maks 10 file per laporan | Custom validation rule |
| **Nama file** | Diganti UUID v4 | `Str::uuid() . '.' . $ext` |
| **Magic bytes** | Validasi header file | Custom validation (file signature check) |
| **Metadata** | Strip EXIF data dari gambar | Intervention Image / manual strip |
| **Antivirus** | Scan file (jika memungkinkan) | ClamAV integration (opsional/future) |

### 5.2 Alur Upload

```
Client upload file
    │
    ▼
FormRequest Validation
├── Check extension (whitelist)
├── Check MIME type
├── Check file size (≤ 25 MB)
├── Check total files per report (≤ 10)
    │
    ▼
Service Layer
├── Generate UUID filename
├── Strip EXIF metadata
├── Encrypt file (AES-256)
│   └── Encryption key dari ENCRYPTION_KEY env
    │
    ▼
Storage
├── Development: storage/app/private/evidence/{case_id}/
├── Production: S3 bucket (optional/future)
    │
    ▼
Database
├── Simpan: uuid_filename, original_name, mime_type,
│   file_size, storage_path, encryption_iv
├── Audit log: AUD-EVID-01
```

### 5.3 Akses File

```
File bukti TIDAK boleh diakses langsung via URL publik.

Akses hanya melalui:
1. Controller terproteksi (auth + policy check)
2. Signed URL (temporary, configurable expiry)

Alur akses:
  Request → auth:sanctum → EvidencePolicy::view → Decrypt → Stream response
```

### 5.4 Private Storage Config

```php
// config/filesystems.php
'disks' => [
    'evidence' => [
        'driver' => 'local',
        'root' => storage_path('app/private/evidence'),
        'visibility' => 'private',
    ],

    // Future: S3 for production
    // 'evidence' => [
    //     'driver' => 's3',
    //     'key' => env('AWS_ACCESS_KEY_ID'),
    //     'secret' => env('AWS_SECRET_ACCESS_KEY'),
    //     'region' => env('AWS_DEFAULT_REGION'),
    //     'bucket' => env('AWS_BUCKET'),
    //     'endpoint' => env('AWS_ENDPOINT'),
    //     'use_path_style_endpoint' => true,
    //     'visibility' => 'private',
    // ],
],
```

---

## 6. Rate Limiting Strategy

### 6.1 Rate Limits

| Endpoint Group | Limit | Window | Scope | Alasan |
|---------------|-------|--------|-------|--------|
| `auth.login` | 5 requests | 1 menit | Per IP | Anti brute-force |
| `auth.register` | 3 requests | 1 menit | Per IP | Anti spam akun |
| `auth.forgot-password` | 3 requests | 1 jam | Per email | Anti abuse |
| `reports.store` | 10 requests | 1 jam | Per user/IP | Anti spam laporan |
| `reports.anonymous` | 5 requests | 1 jam | Per IP | Anti spam anonim |
| `evidence.upload` | 10 requests | 1 jam | Per user | Anti abuse storage |
| `api.general` | 60 requests | 1 menit | Per user | General API protection |
| `api.public` | 30 requests | 1 menit | Per IP | Public endpoint protection |

### 6.2 Implementasi Laravel

```php
// app/Providers/RouteServiceProvider.php (atau bootstrap/app.php)
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});

RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('report-submit', function (Request $request) {
    return Limit::perHour(10)->by($request->user()?->id ?: $request->ip());
});
```

### 6.3 Response saat Rate Limited

```json
// HTTP 429 Too Many Requests
{
  "success": false,
  "message": "Terlalu banyak percobaan. Coba lagi dalam {seconds} detik.",
  "errors": null
}

// Headers:
// Retry-After: 60
// X-RateLimit-Limit: 5
// X-RateLimit-Remaining: 0
```

---

## 7. RBAC Policy

### 7.1 Implementasi RBAC

```
RBAC Layer Stack:

1. Route Middleware (role check)
   └── Cek apakah user memiliki role yang diizinkan untuk endpoint

2. Laravel Policy (resource-level)
   └── Cek apakah user boleh melakukan aksi pada resource spesifik
   └── Contoh: Reporter hanya bisa lihat laporan miliknya

3. Service Layer (business logic)
   └── Cek aturan bisnis tambahan
   └── Contoh: Satgas hanya bisa akses kasus yang ditugaskan
```

### 7.2 Policy per Resource

| Resource | Policy Class | Aksi | Aturan |
|----------|-------------|------|--------|
| Report | `ReportPolicy` | `view` | Owner (reporter), Admin (all), Satgas (assigned case) |
| Report | `ReportPolicy` | `create` | Reporter, Anonymous |
| Report | `ReportPolicy` | `verify` | Admin, Super Admin |
| Case | `CasePolicy` | `view` | Satgas aktif yang ditugaskan; Admin sesuai scope kampus; Super Admin read-only sesuai permission dan feature flag sensitive oversight. Identitas anonymous tetap masked. |
| Case | `CasePolicy` | `assess` | Satgas (assigned) |
| Case | `CasePolicy` | `investigate` | Satgas (assigned) |
| Case | `CasePolicy` | `close` | Satgas (assigned) |
| Evidence | `EvidencePolicy` | `view` | Satgas aktif yang ditugaskan; Super Admin hanya melalui read-only sensitive oversight yang diaktifkan eksplisit. Emergency Access R2 tidak memberikan akses Evidence. |
| Evidence | `EvidencePolicy` | `upload` | Satgas aktif yang ditugaskan sesuai tahap Investigation; Reporter Supporting Files memakai resource terpisah. |
| Evidence | `EvidencePolicy` | `download` | Satgas aktif yang ditugaskan; Super Admin hanya melalui read-only sensitive oversight yang diaktifkan eksplisit. |
| User | `UserPolicy` | `create` | Admin, Super Admin |
| User | `UserPolicy` | `assignRole` | Super Admin only |

### 7.3 Data Isolation

```
PENTING: Data isolation per role

Super Admin:
├── Bisa lihat metadata SEMUA kasus (status, SLA, assignment)
├── TIDAK otomatis melihat data investigasi, bukti, atau identitas korban
├── Akses ke data sensitif hanya via system.break_glass_access (emergency)
└── Setiap break-glass dicatat sebagai audit log CRITICAL

Admin:
├── Bisa lihat SEMUA laporan (metadata + isi laporan awal)
├── Bisa lihat metadata kasus (via cases.read.metadata)
├── TIDAK bisa lihat detail investigasi, bukti kasus, atau catatan internal Satgas
└── TIDAK memiliki cases.read.all (hanya cases.read.metadata)

Satgas:
├── HANYA bisa lihat kasus yang ditugaskan
├── Bisa lihat SEMUA data pada kasus yang ditugaskan
└── TIDAK bisa lihat data pengguna/akun

Reporter:
├── HANYA bisa lihat laporan milik sendiri
├── TIDAK bisa lihat laporan orang lain
└── TIDAK bisa lihat data internal kasus (investigasi, catatan Satgas)

Anonymous:
├── HANYA bisa akses via tracking code
├── TIDAK ada data user yang disimpan
├── TIDAK ada logging IP/device pada record laporan/kasus
├── IP hanya digunakan in-memory untuk rate limiting
├── Security log: IP di-hash (SHA-256 + daily salt) atau di-mask
└── Security log anonim di-purge setelah 7 hari
```

> **Catatan (Audit Patch v1.0.1)**: Data isolation untuk Admin dan Super Admin telah diperketat. Admin sekarang hanya memiliki `cases.read.metadata` (bukan `cases.read.all`). Super Admin tidak otomatis memiliki akses bukti (`evidence.view.case` = ❌). Detail lengkap di `MASTER_DATA.md` Section 2.3 dan 2.4.

---

## 8. Audit Logging Policy

### 8.1 Prinsip Audit

| # | Prinsip | Detail |
|---|---------|--------|
| 1 | **Completeness** | Setiap operasi pada data kasus harus dicatat |
| 2 | **Immutability** | Audit log TIDAK BOLEH dihapus atau dimodifikasi |
| 3 | **Confidentiality** | Audit log tidak boleh berisi data sensitif (masked) |
| 4 | **Availability** | Audit log harus bisa diquery untuk investigasi |
| 5 | **Retention** | Minimum 5 tahun |

### 8.2 Data yang Dicatat per Event

```json
{
  "id": "uuid",
  "event": "case.status_changed",
  "severity": "INFO",
  "actor_id": 15,
  "actor_role": "satgas_ppks",
  "actor_ip": "192.168.1.x",
  "user_agent": "Mozilla/5.0 ...",
  "resource_type": "Case",
  "resource_id": 42,
  "old_values": { "status": "assessment" },
  "new_values": { "status": "investigation" },
  "metadata": { "justification": "..." },
  "created_at": "2026-06-09T20:36:02Z"
}
```

### 8.3 Data Masking dalam Audit Log

```
WAJIB di-mask:
├── Nama korban         → "K***n"
├── Nama terlapor       → "T***r"
├── Nomor telepon       → "6281****6789"
├── Email               → "j***@university.ac.id"
├── Kronologi           → TIDAK dicatat di audit log (hanya ID laporan)
├── Detail bukti        → Hanya file_id dan mime_type (bukan konten)

BOLEH dicatat tanpa mask:
├── User ID (actor)
├── Report/Case ID
├── Status changes
├── Timestamp
├── IP Address (untuk security events, authenticated users)

ATURAN KHUSUS ANONYMOUS (Audit Patch v1.0.1):
├── actor_ip = NULL pada audit log bisnis untuk aksi anonim
├── Security log (rate limit, abuse detection) → IP di-hash atau di-mask
├── Format hash: SHA-256(IP + daily_rotating_salt)
├── Format mask: 192.168.xxx.xxx (last octet hidden)
└── Security log anonim → auto-purge setelah 7 hari (retention pendek)
```

### 8.4 Database Schema (tabel `audit_logs`)

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | `uuid` PK | UUID v4 |
| `event` | `varchar(100)` | Event code (e.g., `case.status_changed`) |
| `severity` | `enum` | `INFO`, `WARNING`, `CRITICAL` |
| `actor_id` | `bigint` NULLABLE | User ID (null untuk aksi anonim) |
| `actor_role` | `varchar(20)` NULLABLE | Role saat aksi dilakukan |
| `actor_ip` | `inet` | IP address |
| `user_agent` | `text` NULLABLE | Browser/client info |
| `resource_type` | `varchar(50)` | Model class name |
| `resource_id` | `bigint` NULLABLE | Resource primary key |
| `old_values` | `jsonb` NULLABLE | Nilai sebelum perubahan (masked) |
| `new_values` | `jsonb` NULLABLE | Nilai setelah perubahan (masked) |
| `metadata` | `jsonb` NULLABLE | Data tambahan |
| `created_at` | `timestamp` | Immutable |

> **PENTING**: Tabel audit_logs TIDAK memiliki `updated_at` atau `deleted_at`. Entries bersifat immutable.

---

## 9. Password Policy

### 9.1 Aturan Password

| Aturan | Nilai | Alasan |
|--------|-------|--------|
| Minimum panjang | 8 karakter | Balance security vs usability |
| Karakter wajib | Huruf (a-zA-Z) + Angka (0-9) | Complexity requirement |
| Maks panjang | 128 karakter | Prevent DoS via long password hashing |
| Hashing algorithm | Argon2id (primary), Bcrypt (fallback) | Industry standard, memory-hard |
| Re-hashing | Otomatis jika cost berubah | Laravel `Hash::needsRehash()` |
| Password reuse | Tidak dicek (MVP) | Post-MVP: cek 5 password terakhir |
| Expiry | Tidak ada (MVP) | Post-MVP: 90 hari untuk admin/satgas |

### 9.2 Implementasi

```php
// Laravel default hashing (config/hashing.php)
'driver' => 'argon2id',
'argon' => [
    'memory' => 65536,  // 64 MB
    'threads' => 1,
    'time' => 4,
],

// Validation rule
'password' => [
    'required',
    'string',
    'min:8',
    'max:128',
    'regex:/[a-zA-Z]/',  // At least one letter
    'regex:/[0-9]/',      // At least one number
    'confirmed',
],
```

### 9.3 Password Reset

- Link reset berlaku **60 menit**
- Sekali pakai (one-time use)
- Setelah reset berhasil: **semua token di-revoke**
- Rate limit: 3 request per jam per email

---

## 10. Session Security

### 10.1 Stateless API

```
SILAPPKASAL menggunakan STATELESS API.
Tidak ada session di sisi server.

Implikasi:
├── Tidak ada session cookie
├── Tidak ada session storage di Redis/file
├── Setiap request harus membawa Bearer token
├── CSRF protection TIDAK diperlukan untuk API
└── Session fixation attack TIDAK berlaku
```

### 10.2 CORS Configuration

```php
// config/cors.php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
        // Tambahkan production URL saat deploy
    ],
    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'],
    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Retry-After'],
    'max_age' => 86400,
    'supports_credentials' => false,  // Stateless, tidak perlu credentials
];
```

### 10.3 Security Headers

```
# Nginx config (production)
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "DENY" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self' https://md.fonnte.com;" always;
```

---

## 11. Token Security

### 11.1 Sanctum Token Security

| Aspek | Detail |
|-------|--------|
| Format | Opaque random token (40 chars + hash) |
| Storage (server) | SHA-256 hash di `personal_access_tokens` |
| Storage (client web) | **In-memory** (React state) — BUKAN localStorage. Lihat catatan MVP di bawah. |
| Storage (client mobile) | `flutter_secure_storage` (encrypted keychain) |
| Transport | HTTPS only, Bearer header |
| Expiry | 24 jam (configurable via system settings) |
| Revocation | Immediate (delete from DB) |
| Max per user | 5 tokens (clean up oldest on exceed) |

### 11.1.1 Catatan MVP: Token Storage (Audit Patch v1.0.1)

> **PENTING**: Untuk MVP, React web menyimpan token **in-memory** saja. Dampaknya:

| Aspek | Detail |
|-------|--------|
| **Trade-off** | User akan logout otomatis saat browser di-refresh atau tab ditutup |
| **Alasan** | In-memory storage paling aman terhadap XSS — tidak ada token yang bisa diakses oleh script malicious |
| **localStorage** | ❌ DITOLAK untuk MVP — rentan XSS |
| **Post-MVP** | Persistent login via **httpOnly Secure Cookie** dapat dipertimbangkan jika logout-on-refresh mengganggu UX. Memerlukan: CSRF protection, CORS credentials, dan update pada `AUTH_FLOW.md`. |

> Referensi lengkap: `AUTH_FLOW.md` Section 5.2.1

### 11.2 Token Compromise Mitigation

```
Jika token diketahui terkompromi:

1. Revoke token spesifik (POST /auth/logout)
2. Revoke semua token (POST /auth/logout-all)
3. Admin dapat menonaktifkan akun → semua token dihapus
4. Ganti password → semua token dihapus
5. Audit log: trace aktivitas dengan token terkompromi
```

---

## 12. Database Security

### 12.1 Aturan Database

| # | Aturan | Detail |
|---|--------|--------|
| 1 | **Parameterized queries only** | Gunakan Eloquent ORM. Raw query hanya dengan binding. |
| 2 | **Scoped queries** | Selalu filter berdasarkan role/ownership. Tidak ada `SELECT *` tanpa scope. |
| 3 | **Soft delete** | Data kasus menggunakan soft delete. Data TIDAK PERNAH dihapus permanen. |
| 4 | **Migration wajib** | Semua schema change melalui Laravel migration. |
| 5 | **No direct DB access** | Aplikasi lain tidak boleh langsung akses database. Hanya via API. |
| 6 | **Connection encryption** | PostgreSQL connection via SSL (production). |
| 7 | **Separate DB user** | Aplikasi menggunakan user DB dengan privilege terbatas (bukan superuser). |
| 8 | **Backup encryption** | Backup database dienkripsi sebelum disimpan. |

### 12.2 PostgreSQL Hardening

```
# postgresql.conf (production recommendations)
ssl = on
ssl_cert_file = '/path/to/server.crt'
ssl_key_file = '/path/to/server.key'

password_encryption = scram-sha-256
log_connections = on
log_disconnections = on
log_statement = 'mod'  # Log INSERT/UPDATE/DELETE

# pg_hba.conf
# Hanya izinkan koneksi dari localhost dan app server
host silappkasal silappkasal_user 127.0.0.1/32 scram-sha-256
host silappkasal silappkasal_user {app_server_ip}/32 scram-sha-256
```

### 12.3 DB User Privileges

```sql
-- Application user (least privilege)
CREATE USER silappkasal_user WITH PASSWORD '<strong_password>';
GRANT CONNECT ON DATABASE silappkasal TO silappkasal_user;
GRANT USAGE ON SCHEMA public TO silappkasal_user;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO silappkasal_user;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO silappkasal_user;

-- TIDAK diberi: CREATE, DROP, ALTER, TRUNCATE, REFERENCES
-- Migration dijalankan dengan user terpisah yang memiliki DDL privilege
```

---

## 13. Encryption Strategy

### 13.1 Ringkasan

> **PENTING**: Sistem ini BUKAN end-to-end encryption (E2EE). Server memiliki akses ke data yang didekripsi pada saat pemrosesan. Ini adalah keputusan desain yang disengaja agar backend dapat menjalankan logika bisnis (validasi, search, reporting).

| Layer | Teknik | Standar | Implementasi |
|-------|--------|---------|-------------|
| **Encryption in Transit** | TLS | TLS 1.3 | HTTPS wajib. SSL termination di Nginx/Cloudflare. |
| **Encryption at Rest (Database)** | Field-level encryption | AES-256-GCM | Kolom sensitif dienkripsi di level aplikasi sebelum simpan ke DB. |
| **Encryption at Rest (Files)** | File encryption | AES-256 | File bukti dienkripsi sebelum simpan ke private storage. |
| **Password Hashing** | One-way hash | Argon2id | Laravel Hash facade. Tidak bisa di-decrypt (by-design). |
| **Token Hashing** | One-way hash | SHA-256 | Sanctum menyimpan hash token. Plaintext hanya dikirim sekali ke client. |

### 13.2 Field-Level Encryption

Kolom yang WAJIB dienkripsi:

| Tabel | Kolom | Klasifikasi | Alasan |
|-------|-------|-------------|--------|
| `reports` | `chronology` | CRITICAL | Kronologi kekerasan |
| `reports` | `incident_location` | CONFIDENTIAL | Lokasi kejadian |
| `reports` | `respondent_name` | CRITICAL | Identitas terlapor |
| `reports` | `respondent_details` | CRITICAL | Detail terlapor |
| `reports` | `witness_info` | CONFIDENTIAL | Data saksi |
| `reporters` | `phone_number` | CONFIDENTIAL | Nomor telepon pelapor |
| `case_assessments` | `justification` | CONFIDENTIAL | Justifikasi risiko |
| `case_assessments` | `protection_steps` | CONFIDENTIAL | Langkah perlindungan |
| `investigations` | `findings` | CRITICAL | Temuan investigasi |
| `investigations` | `interview_notes` | CRITICAL | Catatan wawancara |
| `recommendations` | `conclusion` | CONFIDENTIAL | Kesimpulan |
| `recommendations` | `sanction_recommendation` | CONFIDENTIAL | Rekomendasi sanksi |
| `messages` | `content` | CONFIDENTIAL | Isi pesan |

### 13.3 Implementasi Enkripsi Laravel

```php
// Menggunakan Laravel built-in encryption
use Illuminate\Support\Facades\Crypt;

// Model: menggunakan encrypted casting
class Report extends Model
{
    protected $casts = [
        'chronology' => 'encrypted',
        'incident_location' => 'encrypted',
        'respondent_name' => 'encrypted',
        'respondent_details' => 'encrypted',
        'witness_info' => 'encrypted',
    ];
}

// Atau manual encryption/decryption
$encrypted = Crypt::encryptString($plainText);
$decrypted = Crypt::decryptString($encrypted);
```

### 13.4 Key Management

```
Encryption keys:

1. APP_KEY (Laravel)
   ├── Digunakan untuk: Laravel Crypt facade, signed URLs, session encryption
   ├── Dihasilkan oleh: php artisan key:generate
   ├── Storage: .env file (JANGAN PERNAH commit)
   └── Rotasi: Manual, dengan migrasi data

2. ENCRYPTION_KEY (Custom, jika dibutuhkan)
   ├── Digunakan untuk: File encryption (jika terpisah dari APP_KEY)
   ├── Storage: .env file
   └── Rotasi: Manual, dengan re-encrypt semua file

PENTING:
├── Backup encryption keys secara terpisah dan aman
├── Key TIDAK disimpan di database
├── Key TIDAK disimpan di version control
└── Kehilangan key = kehilangan akses ke data terenkripsi
```

---

## 14. Private Storage Policy

### 14.1 Prinsip Storage

| # | Prinsip | Detail |
|---|---------|--------|
| 1 | **Private by default** | Semua file bukti disimpan di private storage |
| 2 | **No public URL** | File TIDAK boleh diakses via URL publik |
| 3 | **Access via controller** | Akses file hanya melalui controller yang terproteksi (auth + policy) |
| 4 | **Encrypted at rest** | File dienkripsi sebelum disimpan |
| 5 | **UUID filename** | Nama file di-randomize, metadata asli di DB |

### 14.2 Storage Strategy

```
Development (Localhost First):
├── Driver: local
├── Path: storage/app/private/evidence/{case_id}/
├── Visibility: private
├── Web server: Nginx/PHP TIDAK serve file ini langsung
└── Access: Via Laravel controller + Stream response

Production (Future/Opsional):
├── Driver: s3
├── Bucket: silappkasal-evidence
├── Visibility: private
├── Endpoint: S3-compatible (AWS/Cloudflare R2/MinIO)
└── Access: Via pre-signed URL (expiry: 15 menit)

PENTING:
├── File di storage/app/public/ → JANGAN PERNAH simpan bukti di sini
├── File di public/ → JANGAN PERNAH simpan bukti di sini
└── .gitignore harus exclude storage/app/private/evidence/
```

### 14.3 Folder Structure

```
storage/
└── app/
    └── private/
        └── evidence/
            ├── {case_id}/
            │   ├── {uuid1}.enc     ← File terenkripsi
            │   ├── {uuid2}.enc
            │   └── {uuid3}.enc
            └── temp/
                └── {upload_session}/  ← File sementara saat upload
```

---

## 15. Backup Policy

### 15.1 Schedule Backup

| Data | Frekuensi | Retention | Lokasi | Enkripsi |
|------|-----------|-----------|--------|:--------:|
| Database (full dump) | Harian (02:00 WIB) | 30 hari | Off-site storage | Ya |
| Database (incremental) | Per 6 jam | 7 hari | Local + off-site | Ya |
| File bukti | Real-time (jika S3) / Harian (jika local) | Permanent | Off-site storage | Ya |
| Configuration (.env, nginx, etc.) | Setiap perubahan | Version controlled | Git (tanpa secrets) | — |
| Encryption keys | Manual, setelah rotasi | Permanent | Offline secure storage | Ya |

### 15.2 Recovery Objectives

| Metrik | Target | Detail |
|--------|--------|--------|
| **RTO** (Recovery Time Objective) | ≤ 4 jam | Waktu maksimal untuk restore sistem ke kondisi operasional |
| **RPO** (Recovery Point Objective) | ≤ 6 jam | Kehilangan data maksimal yang bisa ditoleransi |

### 15.3 Backup Script (Referensi)

```bash
#!/bin/bash
# backup-db.sh — Backup database harian

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup/silappkasal/db"
DB_NAME="silappkasal"

# Dump database
pg_dump -U silappkasal_backup -h localhost -Fc $DB_NAME > "$BACKUP_DIR/silappkasal_$DATE.dump"

# Encrypt backup
openssl enc -aes-256-cbc -salt -pbkdf2 \
  -in "$BACKUP_DIR/silappkasal_$DATE.dump" \
  -out "$BACKUP_DIR/silappkasal_$DATE.dump.enc" \
  -pass file:/path/to/backup-passphrase

# Remove unencrypted dump
rm "$BACKUP_DIR/silappkasal_$DATE.dump"

# Cleanup: hapus backup > 30 hari
find "$BACKUP_DIR" -name "*.dump.enc" -mtime +30 -delete

# Upload to off-site (rclone/rsync/s3cmd)
# rclone copy "$BACKUP_DIR/silappkasal_$DATE.dump.enc" remote:backups/
```

### 15.4 Recovery Test

| Frekuensi | Tes | PIC |
|-----------|-----|-----|
| Bulanan | Restore database dari backup ke environment test | DevOps / Admin |
| Kuartalan | Full disaster recovery drill | DevOps + Super Admin |

---

## 16. Incident Response

### 16.1 Tingkat Keparahan

| Level | Nama | Deskripsi | Response Time |
|-------|------|-----------|:-------------:|
| **SEV-1** | Critical | Data breach, data korban terekspos, sistem down | ≤ 1 jam |
| **SEV-2** | High | Unauthorized access terdeteksi, vulnerability aktif | ≤ 4 jam |
| **SEV-3** | Medium | Anomali keamanan, percobaan serangan terblokir | ≤ 24 jam |
| **SEV-4** | Low | Konfigurasi suboptimal, audit finding minor | ≤ 72 jam |

> **Catatan (Audit Patch v1.0.1)**: Penggunaan `system.break_glass_access` yang tidak sah termasuk kategori **SEV-2** dan harus direspon dalam ≤ 4 jam. Audit event: `AUD-SEC-04: security.break_glass_activated` (lihat `MASTER_DATA.md` Section 12).

### 16.2 Prosedur Incident Response

```
1. DETECT
   ├── Monitoring audit log untuk anomali
   ├── Alert dari rate limiting / WAF
   ├── Laporan dari pengguna
   └── Automated security scan

2. CONTAIN
   ├── Isolasi sistem/akun yang terkompromi
   ├── Revoke semua token (jika auth breach)
   ├── Block IP/range jika serangan dari luar
   └── Preserve evidence (jangan hapus log)

3. ANALYZE
   ├── Identifikasi root cause
   ├── Tentukan scope dampak
   ├── Identifikasi data yang terpengaruh
   └── Dokumentasikan timeline

4. REMEDIATE
   ├── Patch vulnerability
   ├── Reset kredensial yang terkompromi
   ├── Re-encrypt data jika key terkompromi
   └── Update security policy jika perlu

5. RECOVER
   ├── Restore dari backup jika perlu
   ├── Verify integritas data
   ├── Re-enable layanan
   └── Monitor closely pasca incident

6. REPORT
   ├── Internal incident report
   ├── Notifikasi ke pengguna yang terdampak (jika ada data breach)
   ├── Laporan ke regulator (jika diwajibkan UU PDP)
   └── Update BUILD_NOTES.md dan SECURITY_POLICY.md
```

### 16.3 Kontak Eskalasi

| Situasi | Eskalasi Ke | Timeframe |
|---------|-------------|:---------:|
| SEV-1 (data breach) | Project Owner → Pimpinan PT | Segera |
| SEV-2 (unauthorized access) | Project Owner | ≤ 4 jam |
| Vulnerability ditemukan | Project Owner | ≤ 24 jam |
| Kepatuhan UU PDP | DPO (Data Protection Officer) jika ada | Sesuai regulasi |

---

## 17. Cloudflare Recommendation

> **Status**: Rekomendasi untuk production. Bukan keputusan final.

### 17.1 Manfaat Cloudflare

| Fitur | Manfaat untuk SILAPPKASAL |
|-------|--------------------------|
| **DDoS Protection** | Melindungi dari serangan DDoS |
| **WAF** | Web Application Firewall — blokir serangan umum |
| **SSL/TLS** | Free SSL certificate, auto-renew |
| **CDN** | Cache static assets (React build) |
| **Rate Limiting** | Layer tambahan di atas Laravel rate limiting |
| **Bot Management** | Blokir bot otomatis |
| **IP Filtering** | Geo-restriction jika diperlukan |

### 17.2 Konfigurasi Rekomendasi

```
SSL/TLS:
├── Mode: Full (Strict)
├── Minimum TLS: 1.2
├── Always Use HTTPS: On
└── HSTS: On (max-age 6 bulan)

Security:
├── WAF: On (OWASP ruleset)
├── Bot Fight Mode: On
├── Hotlink Protection: On
└── Browser Integrity Check: On

Caching:
├── Cache static assets (JS/CSS/images): Yes
├── Cache API responses: NO (private data)
├── Cache HTML: NO (dynamic content)
└── Development Mode: On (saat development)

Page Rules:
├── api.silappkasal.ac.id/* → Cache Level: Bypass
└── admin.silappkasal.ac.id/assets/* → Cache Level: Standard
```

---

## 18. VPS Hardening Recommendation

### 18.1 OS Level

```bash
# 1. Update system
apt update && apt upgrade -y

# 2. Disable root SSH login
# /etc/ssh/sshd_config
PermitRootLogin no
PasswordAuthentication no  # Gunakan SSH key only
MaxAuthTries 3
AllowUsers deploy_user

# 3. Firewall (UFW)
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH (ubah port jika perlu)
ufw allow 80/tcp      # HTTP (redirect ke HTTPS)
ufw allow 443/tcp     # HTTPS
ufw enable

# 4. Fail2Ban
apt install fail2ban
# Configure untuk SSH, Nginx
# Ban setelah 5 percobaan gagal, durasi 1 jam

# 5. Automatic security updates
apt install unattended-upgrades
dpkg-reconfigure -plow unattended-upgrades
```

### 18.2 Application Level

```
Nginx:
├── Disable server tokens (server_tokens off)
├── Limit request body size (client_max_body_size 30M)
├── Enable security headers (lihat Section 10.3)
├── Disable unnecessary HTTP methods
└── SSL configuration (Mozilla recommended)

PHP-FPM:
├── Disable dangerous functions (exec, system, passthru, shell_exec)
├── Set open_basedir
├── Disable expose_php
├── Set memory_limit (256M)
├── Set max_execution_time (30s, kecuali untuk upload: 120s)
└── Set upload_max_filesize (25M)

Laravel:
├── APP_DEBUG=false (production)
├── APP_ENV=production
├── LOG_LEVEL=warning (production)
├── Trusted proxies configured
└── CORS whitelist only production domains

PostgreSQL:
├── Listen on localhost only (atau specific IP)
├── SSL mode required
├── Separate DB user for app (no superuser)
└── Log slow queries
```

### 18.3 Monitoring

```
Recommended monitoring:
├── Uptime: UptimeRobot / Better Uptime (free tier)
├── Server: htop, df, free (manual)
├── Logs: Laravel log (storage/logs/), Nginx error log
├── Security: fail2ban status, audit log review
└── Performance: Laravel Telescope (development only)
```

---

## 19. Laravel Queue Security

### 19.1 Queue Usage di SILAPPKASAL

| Job | Queue | Deskripsi | Data Sensitif |
|-----|-------|-----------|:-------------:|
| `SendWhatsAppNotification` | `notifications` | Kirim notifikasi WhatsApp via Fonnte | Nomor telepon |
| `ProcessEvidenceUpload` | `uploads` | Encrypt dan simpan file bukti | File path |
| `GenerateStatisticsReport` | `reports` | Generate laporan statistik | Agregat (no PII) |
| `CleanExpiredTokens` | `maintenance` | Hapus token expired | Token hashes |

### 19.2 Aturan Keamanan Queue

| # | Aturan | Detail |
|---|--------|--------|
| 1 | **Data minimal di payload** | Job hanya menyimpan ID, bukan data lengkap. Data diambil dari DB saat proses. |
| 2 | **Queue driver** | Gunakan `database` driver (development) atau `redis` (production). BUKAN `sync`. |
| 3 | **Retry policy** | Maks 3 retry. Exponential backoff. |
| 4 | **Failed job handling** | Failed jobs masuk ke `failed_jobs` table. Tidak boleh berisi data sensitif. |
| 5 | **Supervisor** | Queue worker dijalankan via Supervisor (production). Auto-restart on failure. |
| 6 | **Timeout** | Max execution time per job: 120 detik. |
| 7 | **Logging** | Job execution di-log, tapi tanpa data sensitif. |

### 19.3 Queue Configuration

```php
// config/queue.php
'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => true,  // Job hanya dispatch setelah DB transaction commit
    ],
],

// Job class example
class SendWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // 60 detik antara retry
    public int $timeout = 30;

    // Hanya simpan ID, bukan data sensitif
    public function __construct(
        private int $notificationId
    ) {}

    public function handle(FonnteService $fonnte): void
    {
        $notification = Notification::findOrFail($this->notificationId);
        // Process...
    }
}
```

---

## 20. WhatsApp Integration Security

### 20.1 Fonnte API Security

| Aspek | Detail |
|-------|--------|
| **API Endpoint** | `https://md.fonnte.com/api/send/` |
| **Authentication** | API Token di header `Authorization` |
| **Transport** | HTTPS (encryption in transit) |
| **Token Storage** | `.env` file (`FONNTE_API_TOKEN`). JANGAN hardcode. |
| **Rate Limit** | Sesuai plan Fonnte. Monitor usage. |

### 20.2 Aturan Keamanan

| # | Aturan | Detail |
|---|--------|--------|
| 1 | **Token di .env** | `FONNTE_API_TOKEN` dan `FONNTE_DEVICE_ID` hanya di `.env`. |
| 2 | **Via Queue** | Pengiriman WA via Laravel Queue (async). Bukan sync di request. |
| 3 | **No PII in message** | Pesan WA TIDAK boleh berisi: nama korban, kronologi, identitas terlapor. |
| 4 | **Template-based** | Gunakan template yang sudah disetujui. Tidak ada freeform message. |
| 5 | **Log delivery status** | Catat status pengiriman di `notification_logs`. |
| 6 | **Fail gracefully** | Jika Fonnte down, proses utama tetap berjalan. Notifikasi masuk antrian retry. |
| 7 | **Phone number validation** | Validasi format nomor sebelum kirim (format: `628xxxxxxxxxx`). |
| 8 | **Anonim = no WA** | Pelapor anonim TIDAK menerima notifikasi WA (by-design). |

### 20.3 Implementasi

```php
// FonnteService.php (pseudocode)
class FonnteService
{
    public function send(string $phoneNumber, string $templateKey, array $params): bool
    {
        // 1. Validate phone format
        if (!preg_match('/^628[0-9]{8,12}$/', $phoneNumber)) {
            throw new InvalidPhoneNumberException();
        }

        // 2. Build message from template (NOT freeform)
        $message = $this->buildFromTemplate($templateKey, $params);

        // 3. Send via HTTPS
        $response = Http::withHeaders([
            'Authorization' => config('services.fonnte.token'),
        ])->post('https://md.fonnte.com/api/send/', [
            'target' => $phoneNumber,
            'message' => $message,
        ]);

        // 4. Log result
        NotificationLog::create([
            'channel' => 'whatsapp',
            'phone_number' => $this->maskPhone($phoneNumber),
            'template' => $templateKey,
            'status' => $response->successful() ? 'sent' : 'failed',
            'response_code' => $response->status(),
        ]);

        return $response->successful();
    }

    private function maskPhone(string $phone): string
    {
        // 628123456789 → 6281****6789
        return substr($phone, 0, 4) . '****' . substr($phone, -4);
    }
}
```

---

## Ringkasan Security Checklist

Checklist ini wajib diverifikasi sebelum deployment ke production.

### Pre-Production Checklist

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] HTTPS enforced (TLS 1.3)
- [ ] Security headers terpasang
- [ ] CORS hanya whitelist production domains
- [ ] Rate limiting aktif di semua endpoint
- [ ] Field-level encryption aktif untuk data sensitif
- [ ] File bukti di private storage (bukan public)
- [ ] Audit logging aktif
- [ ] Password hashing menggunakan Argon2id
- [ ] Token expiry terkonfigurasi (24 jam)
- [ ] Database user bukan superuser
- [ ] PostgreSQL SSL aktif
- [ ] SSH key-only authentication
- [ ] Firewall aktif (UFW)
- [ ] Fail2Ban terpasang
- [ ] Backup script berjalan
- [ ] `composer audit` clean
- [ ] `npm audit` clean
- [ ] `.env` tidak ada di version control
- [ ] Encryption keys di-backup secara terpisah dan aman
- [ ] Fonnte API token hanya di `.env`
- [ ] Server tokens disabled di Nginx
- [ ] PHP `expose_php` off
- [ ] Laravel Telescope disabled di production
- [ ] Queue worker berjalan via Supervisor
- [ ] Error pages tidak menampilkan stack trace

---

## 21. Anonymous Emergency Access (REV-WF-03 R2)

Anonymous Reporter identity is masked by default and may be returned only from the authenticated
`POST /api/v1/break-glass/{request}/reveal` endpoint. Normal Report, Case, Evidence, supporting-file,
and Super Admin oversight resources must not embed the identity projection.

Security controls:

- active assigned same-campus Satgas is the only request role and exclusive reveal recipient;
- active same-campus Admin may review, approve, deny, and revoke but cannot reveal;
- Super Admin has redacted Activity Log oversight only and no operational Emergency Access action;
- approval, revoke, reveal, and expiry checks run inside transactions with locked current state;
- grant activation begins on approval and accepts only 30, 60, 240, or 1440 minutes;
- elapsed or revoked grants are denied independently of frontend state;
- every request, approve, deny, reveal, revoke, and normalization-to-expired event is audited using
  allowlisted metadata without identity, reason narratives, filenames, or case content;
- the reveal response sends `Cache-Control: no-store`, `Pragma: no-cache`, and `Expires: 0`;
- identity is not stored in TanStack Query, localStorage, sessionStorage, URLs, toasts, or logs, and
  protected-dialog state is cleared on close;
- no service worker handles or persists the reveal response;
- anonymous internal filename metadata and Content-Disposition values use generated safe names;
  file bytes are unchanged and embedded filenames inside content cannot be sanitized.

The detailed lifecycle, legacy compatibility, and deployment order are defined in
`docs/security/BREAK_GLASS_POLICY.md`.

## Changelog

| Versi | Tanggal | Perubahan |
|-------|---------|----------|
| 1.0.0 | 2026-06-09 | Versi awal |
| 1.0.1-patch | 2026-06-10 | Audit patch: pembatasan akses Admin ke metadata kasus saja, Super Admin tidak otomatis akses bukti (break-glass protocol), penguatan privasi anonim (hashed/masked IP, retention 7 hari), catatan MVP token in-memory, rencana Post-MVP httpOnly cookie, penambahan audit event `security.break_glass_activated` |
| REV-WF-03-R2 | 2026-07-20 | Requester-scoped Anonymous Emergency Access, same-campus Admin review/revoke, non-cacheable reveal, expiry normalization, redacted audit, and anonymous filename protection. |
| REV-CONTENT-01-C1 | 2026-07-21 | Campus-scoped published content, immutable versions, controlled rich text, private attachments, redacted content audit, and non-cacheable authenticated readers. |

## 22. Content publication security (REV-CONTENT-01 C1)

- Reader queries use the published-version pointer and actor campus scope; lifecycle text alone never
  exposes a version.
- Draft, review, rejected, revision-requested, and archived content is excluded from reader APIs.
- Article and FAQ input is a controlled JSON document. Server-side sanitization and protocol
  allowlisting are mandatory; arbitrary authoritative HTML is not accepted.
- Content files remain on a private disk. Upload and download both revalidate authorization. Private
  path, checksum, internal IDs, protected original filename, and review narratives are not serialized.
- Review/archive reasons and editorial notes use encrypted casts. Their values are excluded from
  allowlisted audit metadata.
- Audit metadata may identify only safe content public IDs, version numbers, type/section/category,
  scope, safe university code, transition, decision code, attachment public ID/purpose, and result.
- Authenticated content and management responses use private, no-store caching. They must never be
  placed in a shared or service-worker response cache.
- Campus Admin Content-audit queries are restricted before pagination to `scope=campus` events whose
  safe university code equals the Admin's university. Global and cross-campus Content events are
  hidden; detail lookup returns a non-disclosing 404. Super Admin retains all-campus Content audit.
- Image uploads fail closed while a verified metadata-stripping and re-encoding processor is absent.
  Enabling an environment flag alone never enables raw image storage. PDF general attachments remain
  available under the private-storage validation boundary.
- Revision attachment cloning generates new UUIDs/private paths and rewrites cover/image references;
  submit and publication reject foreign, missing, wrong-purpose, or dangling attachment references.
- Download filenames are generated from attachment purpose/public ID. Audit action
  `content.attachment_download_authorized` records successful authorization and response preparation,
  not byte-complete delivery.
- Automated tests are guarded to SQLite `:memory:` by default. Local PostgreSQL verification requires
  the exact database `silappkasal_test` plus an explicit matching confirmation; `silappkasal` is
  prohibited.

---

> **Catatan**: Dokumen ini adalah Tier 2 (GOVERNED). Perubahan memerlukan persetujuan Project Owner. Dokumen ini menjadi referensi wajib bagi semua agent, terutama Backend Agent dan Reviewer Agent.
