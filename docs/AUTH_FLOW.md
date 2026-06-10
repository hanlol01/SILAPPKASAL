# AUTH_FLOW.md — Authentication & Authorization Flow

> **Sistem Informasi Laporan Pencegahan dan Penanganan Kekerasan Seksual (SILAPPKASAL)**
> Versi: 1.0.1-patch | Terakhir Diperbarui: 2026-06-10 | Status: BERLAKU — AUDIT PATCH | Tier: 2 (GOVERNED)

---

## Daftar Isi

1. [Authentication Architecture](#1-authentication-architecture)
2. [Laravel Sanctum Strategy](#2-laravel-sanctum-strategy)
3. [Login Flow](#3-login-flow)
4. [Logout Flow](#4-logout-flow)
5. [Session Flow](#5-session-flow)
6. [Token Flow](#6-token-flow)
7. [Password Reset Flow](#7-password-reset-flow)
8. [Account Activation Flow](#8-account-activation-flow)
9. [Role Permission Flow](#9-role-permission-flow)
10. [Route Protection Strategy](#10-route-protection-strategy)
11. [Flutter Authentication Flow](#11-flutter-authentication-flow)
12. [React Authentication Flow](#12-react-authentication-flow)
13. [Future 2FA Strategy](#13-future-2fa-strategy)

---

## 1. Authentication Architecture

### 1.1 Overview

SILAPPKASAL menggunakan **stateless API authentication** berbasis token. Backend Laravel bertindak sebagai sumber kebenaran tunggal untuk autentikasi dan otorisasi. Seluruh client (React web, Flutter mobile) berkomunikasi melalui REST API.

### 1.2 Diagram Arsitektur

```
┌─────────────────────────────────────────────────────────────────┐
│                      CLIENT APPLICATIONS                         │
│                                                                  │
│  ┌─────────────────────┐       ┌─────────────────────┐          │
│  │    React Web App     │       │   Flutter Mobile     │         │
│  │                      │       │   (Phase 2)          │         │
│  │  ┌────────────────┐  │       │  ┌────────────────┐  │         │
│  │  │ Auth Context    │  │       │  │ Auth Provider   │  │        │
│  │  │ Token Storage   │  │       │  │ Secure Storage  │  │        │
│  │  │ (memory/cookie) │  │       │  │ (encrypted)     │  │        │
│  │  └───────┬────────┘  │       │  └───────┬────────┘  │         │
│  └──────────┼───────────┘       └──────────┼───────────┘         │
│             │                              │                      │
└─────────────┼──────────────────────────────┼──────────────────────┘
              │          HTTPS + Bearer Token │
              ▼                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    LARAVEL BACKEND (API)                          │
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐   │
│  │  Sanctum      │  │  Middleware   │  │  Gates & Policies    │  │
│  │  Token Auth   │  │  Pipeline    │  │  (Authorization)     │  │
│  │               │  │              │  │                      │  │
│  │  - Issue      │  │  - auth      │  │  - ReportPolicy      │  │
│  │  - Validate   │  │  - throttle  │  │  - CasePolicy        │  │
│  │  - Revoke     │  │  - role      │  │  - UserPolicy         │  │
│  │               │  │  - audit     │  │  - EvidencePolicy     │  │
│  └──────┬───────┘  └──────┬───────┘  └──────────┬───────────┘   │
│         │                 │                      │               │
│         ▼                 ▼                      ▼               │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │                    PostgreSQL Database                      │  │
│  │                                                            │  │
│  │  personal_access_tokens  │  users  │  roles  │  permissions│  │
│  └────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### 1.3 Prinsip Autentikasi

| # | Prinsip | Detail |
|---|---------|--------|
| 1 | **Stateless API** | Tidak ada session di server. Setiap request membawa token. |
| 2 | **Backend is Source of Truth** | Validasi token dan permission hanya di backend. Client hanya menyimpan token. |
| 3 | **Token-based** | Menggunakan Laravel Sanctum Personal Access Token. |
| 4 | **RBAC** | Role-Based Access Control via Laravel Gates dan Policies. |
| 5 | **Principle of Least Privilege** | Setiap role hanya memiliki permission minimum yang dibutuhkan. |
| 6 | **Defense in Depth** | Validasi di middleware, policy, dan service layer. |

---

## 2. Laravel Sanctum Strategy

### 2.1 Mengapa Sanctum

| Kriteria | Sanctum | Passport | JWT (tymon) |
|----------|:-------:|:--------:|:-----------:|
| Lightweight | ✅ | ❌ | ✅ |
| SPA-friendly | ✅ | ✅ | ✅ |
| Mobile-friendly | ✅ | ✅ | ✅ |
| Token revocation | ✅ | ✅ | ❌ |
| Laravel native | ✅ | ✅ | ❌ |
| OAuth2 overhead | ❌ | ✅ | ❌ |
| Complexity | Low | High | Medium |

**Keputusan**: Sanctum dipilih karena lightweight, native Laravel, mendukung token revocation, dan sesuai untuk SPA + Mobile API tanpa overhead OAuth2.

### 2.2 Konfigurasi Sanctum

```php
// config/sanctum.php
return [
    'stateful' => [], // Tidak menggunakan SPA cookie auth
    'guard' => ['web'],
    'expiration' => 1440, // 24 jam dalam menit
    'token_prefix' => '',
    'middleware' => [
        'authenticate_session' => null,
        'encrypt_cookies' => null,
        'validate_csrf_token' => null,
    ],
];
```

### 2.3 Token Model

Sanctum menggunakan tabel `personal_access_tokens`:

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | `bigint` PK | Auto-increment |
| `tokenable_type` | `varchar` | Polymorphic (App\Models\User) |
| `tokenable_id` | `bigint` | User ID |
| `name` | `varchar` | Nama token (e.g., "web-login", "mobile-login") |
| `token` | `varchar(64)` | SHA-256 hash dari plaintext token |
| `abilities` | `text` NULLABLE | JSON abilities (default: `["*"]`) |
| `last_used_at` | `timestamp` NULLABLE | Kapan token terakhir digunakan |
| `expires_at` | `timestamp` NULLABLE | Kapan token kedaluwarsa |
| `created_at` | `timestamp` | — |
| `updated_at` | `timestamp` | — |

---

## 3. Login Flow

### 3.1 Diagram Alur Login

```
┌──────────┐                    ┌──────────────┐                   ┌──────────┐
│  Client   │                    │   Backend    │                   │ Database │
└────┬─────┘                    └──────┬───────┘                   └────┬─────┘
     │                                 │                                │
     │  POST /api/v1/auth/login        │                                │
     │  {identifier, password}         │                                │
     │────────────────────────────────►│                                │
     │                                 │                                │
     │                                 │  Rate Limit Check              │
     │                                 │  (5 attempts/minute)           │
     │                                 │                                │
     │                                 │  Validate Input                │
     │                                 │  (FormRequest)                 │
     │                                 │                                │
     │                                 │  Find User by                  │
     │                                 │  email/NIM/NIP                 │
     │                                 │───────────────────────────────►│
     │                                 │◄───────────────────────────────│
     │                                 │                                │
     │                                 │  Verify Password               │
     │                                 │  (Hash::check)                 │
     │                                 │                                │
     │                                 │  Check: is_active?             │
     │                                 │                                │
     │                           ┌─────┤  Generate Sanctum Token        │
     │                           │     │  $user->createToken('web')     │
     │                           │     │───────────────────────────────►│
     │                           │     │◄───────────────────────────────│
     │                           │     │                                │
     │                           │     │  Log: AUD-AUTH-01              │
     │                           │     │───────────────────────────────►│
     │                           │     │                                │
     │  200 OK                   │     │                                │
     │  {token, user, role}      │     │                                │
     │◄──────────────────────────┘     │                                │
     │                                 │                                │
     │  [Store token in memory/        │                                │
     │   secure storage]               │                                │
     │                                 │                                │
```

### 3.2 Login Request

```
POST /api/v1/auth/login
Content-Type: application/json

{
  "identifier": "john@university.ac.id",  // email, NIM, atau NIP
  "password": "SecurePass123"
}
```

### 3.3 Login Response (Success)

```json
{
  "success": true,
  "message": "Login berhasil",
  "data": {
    "token": "1|abcdef1234567890...",
    "token_type": "Bearer",
    "expires_at": "2026-06-10T20:36:02Z",
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@university.ac.id",
      "nim": "2024001001",
      "role": "reporter",
      "phone_number": "628123456789",
      "is_active": true,
      "profile_completed": true
    }
  }
}
```

### 3.4 Login Response (Error)

```json
// 401 - Credentials salah
{
  "success": false,
  "message": "Email/NIM/NIP atau password salah",
  "errors": null
}

// 403 - Akun nonaktif
{
  "success": false,
  "message": "Akun Anda telah dinonaktifkan. Hubungi admin.",
  "errors": null
}

// 429 - Rate limited
{
  "success": false,
  "message": "Terlalu banyak percobaan login. Coba lagi dalam 60 detik.",
  "errors": null
}
```

### 3.5 Validasi Login

| Field | Rule | Error Message |
|-------|------|---------------|
| `identifier` | `required`, `string`, `max:255` | Identifier wajib diisi |
| `password` | `required`, `string`, `min:8` | Password wajib diisi (min 8 karakter) |

### 3.6 Backend Logic

```php
// Pseudocode — AuthService::login()
function login(LoginRequest $request): array
{
    $user = User::where('email', $request->identifier)
                ->orWhere('nim', $request->identifier)
                ->orWhere('nip', $request->identifier)
                ->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        AuditLog::record('auth.login_failed', ...);
        throw new AuthenticationException('Credentials invalid');
    }

    if (!$user->is_active) {
        throw new AccountDeactivatedException();
    }

    $token = $user->createToken('web-login', ['*'], now()->addHours(24));

    AuditLog::record('auth.login', ...);

    return [
        'token' => $token->plainTextToken,
        'user' => new UserResource($user),
    ];
}
```

---

## 4. Logout Flow

### 4.1 Diagram Alur Logout

```
┌──────────┐                    ┌──────────────┐                   ┌──────────┐
│  Client   │                    │   Backend    │                   │ Database │
└────┬─────┘                    └──────┬───────┘                   └────┬─────┘
     │                                 │                                │
     │  POST /api/v1/auth/logout       │                                │
     │  Authorization: Bearer {token}  │                                │
     │────────────────────────────────►│                                │
     │                                 │                                │
     │                                 │  Validate Token (Sanctum)      │
     │                                 │                                │
     │                                 │  Revoke Current Token          │
     │                                 │  $request->user()              │
     │                                 │    ->currentAccessToken()      │
     │                                 │    ->delete()                  │
     │                                 │───────────────────────────────►│
     │                                 │                                │
     │                                 │  Log: AUD-AUTH-03              │
     │                                 │───────────────────────────────►│
     │                                 │                                │
     │  200 OK                         │                                │
     │  {"message": "Logout berhasil"} │                                │
     │◄────────────────────────────────│                                │
     │                                 │                                │
     │  [Clear token from storage]     │                                │
     │  [Redirect to login]            │                                │
```

### 4.2 Logout Variants

| Endpoint | Aksi | Use Case |
|----------|------|----------|
| `POST /api/v1/auth/logout` | Revoke token saat ini | Logout dari perangkat ini saja |
| `POST /api/v1/auth/logout-all` | Revoke semua token user | Logout dari semua perangkat (security measure) |

---

## 5. Session Flow

### 5.1 Stateless Design

SILAPPKASAL menggunakan **stateless API** — tidak ada session di sisi server.

```
PENTING: Sistem ini BUKAN menggunakan session-based auth.

- Tidak ada session ID di cookie
- Tidak ada session storage di server
- Setiap request harus membawa Bearer token
- Token divalidasi di setiap request
- State disimpan di client (React state / Flutter state)
```

### 5.2 Client-Side Session Management

```
React Web:
├── Token disimpan di memory (React state / context)
├── Token TIDAK disimpan di localStorage (XSS risk)
├── Opsional: HTTP-only cookie untuk persistent login
├── Auth state dikelola oleh AuthContext / Zustand
└── Auto-redirect ke login jika 401

Flutter Mobile (Phase 2):
├── Token disimpan di flutter_secure_storage (encrypted)
├── Token di-load saat app launch
├── Biometric unlock sebagai guard opsional
└── Auto-redirect ke login jika 401
```

### 5.2.1 Catatan MVP: Token Storage React Web (Audit Patch v1.0.1)

> **PENTING — Trade-off MVP yang Disengaja:**

| Aspek | Detail |
|-------|--------|
| **Strategi MVP** | Token disimpan **in-memory** (React state/context) |
| **Dampak** | User akan **logout otomatis** saat browser di-refresh atau tab ditutup |
| **Alasan** | In-memory storage adalah opsi **paling aman terhadap XSS**. Tidak ada token yang bisa diakses oleh JavaScript malicious karena token tidak persist di localStorage atau sessionStorage. |
| **Alternatif localStorage** | ❌ DITOLAK — Rentan XSS. Token yang disimpan di localStorage dapat dicuri oleh script malicious. |
| **Perbaikan Post-MVP** | Persistent login menggunakan **httpOnly Secure Cookie** dapat dipertimbangkan jika logout-on-refresh dianggap mengganggu UX. |

```
Post-MVP: Persistent Login via httpOnly Secure Cookie

Jika dibutuhkan (evaluasi setelah MVP stabil):
├── Backend mengeluarkan httpOnly cookie bersamaan dengan token response
├── Cookie attributes: httpOnly, Secure, SameSite=Lax, Path=/api
├── Frontend mengirim cookie otomatis (withCredentials: true)
├── CORS harus mengizinkan credentials (supports_credentials: true)
├── CSRF protection perlu ditambahkan untuk cookie-based auth
└── Perubahan ini memerlukan update AUTH_FLOW.md dan SECURITY_POLICY.md

Keputusan: PENDING — evaluasi setelah MVP berdasarkan feedback user.
```

### 5.3 Token Refresh Strategy

```
Strategy: Sliding Window

1. Token diterbitkan dengan expiry 24 jam
2. Setiap request yang berhasil, backend bisa memperpanjang expiry
3. Jika token expired → client harus login ulang
4. Tidak ada refresh token terpisah (simplicity)

Alur:
  Client Request → Sanctum Validate Token → Expired?
    ├── No  → Process Request → Update last_used_at
    └── Yes → Return 401 → Client Redirect to Login
```

---

## 6. Token Flow

### 6.1 Lifecycle Token

```
┌─────────────────────────────────────────────────────────┐
│                    TOKEN LIFECYCLE                        │
│                                                          │
│  1. CREATED                                              │
│     └── Login berhasil → Token diterbitkan               │
│         └── Hash disimpan di DB                          │
│         └── Plaintext dikirim ke client (sekali saja)    │
│                                                          │
│  2. ACTIVE                                               │
│     └── Client menyertakan di header Authorization       │
│     └── Backend validasi hash                            │
│     └── last_used_at diupdate                            │
│                                                          │
│  3. EXPIRED                                              │
│     └── expires_at < now()                               │
│     └── Backend return 401                               │
│     └── Client harus login ulang                         │
│                                                          │
│  4. REVOKED                                              │
│     └── Logout → Token dihapus dari DB                   │
│     └── Atau: Admin menonaktifkan akun                   │
│     └── Atau: Logout-all                                 │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

### 6.2 Token di Request

```
GET /api/v1/reports
Authorization: Bearer 1|abcdef1234567890...
Content-Type: application/json
Accept: application/json
```

### 6.3 Token Policies

| Policy | Nilai | Alasan |
|--------|-------|--------|
| Expiry default | 24 jam | Balance antara keamanan dan UX |
| Max tokens per user | 5 | Mendukung multi-device, mencegah abuse |
| Token naming | `{platform}-login` | `web-login`, `mobile-login` |
| Revoke on password change | Ya | Security: invalidate semua sesi setelah ganti password |
| Revoke on deactivation | Ya | Admin deactivate → semua token dihapus |
| Token storage (client) | Memory (web), Secure Storage (mobile) | Minimalisasi risiko XSS/theft |

---

## 7. Password Reset Flow

### 7.1 Diagram Alur

```
┌──────────┐              ┌──────────────┐              ┌──────────┐
│  Client   │              │   Backend    │              │   Email   │
└────┬─────┘              └──────┬───────┘              └────┬─────┘
     │                           │                           │
     │  POST /api/v1/auth/       │                           │
     │    forgot-password        │                           │
     │  {email}                  │                           │
     │──────────────────────────►│                           │
     │                           │                           │
     │                           │  Find User by email       │
     │                           │  Generate reset token     │
     │                           │  Store hashed token in DB │
     │                           │  (expires: 60 min)        │
     │                           │                           │
     │                           │  Send reset email         │
     │                           │──────────────────────────►│
     │                           │                           │
     │  200 OK                   │                           │
     │  "Link reset dikirim"     │                           │
     │◄──────────────────────────│                           │
     │                           │                           │
     │  [User buka email]        │                           │
     │  [Klik link reset]        │                           │
     │                           │                           │
     │  POST /api/v1/auth/       │                           │
     │    reset-password         │                           │
     │  {token, email,           │                           │
     │   password,               │                           │
     │   password_confirmation}  │                           │
     │──────────────────────────►│                           │
     │                           │                           │
     │                           │  Validate token           │
     │                           │  Check expiry (60 min)    │
     │                           │  Update password          │
     │                           │  Delete reset token       │
     │                           │  Revoke ALL user tokens   │
     │                           │  Log: AUD-AUTH-04         │
     │                           │                           │
     │  200 OK                   │                           │
     │  "Password berhasil       │                           │
     │   direset"                │                           │
     │◄──────────────────────────│                           │
```

### 7.2 Aturan Password Reset

| Aturan | Nilai |
|--------|-------|
| Token expiry | 60 menit |
| Token usage | Sekali pakai (one-time use) |
| Revoke semua token setelah reset | Ya |
| Rate limit request reset | 3 per jam per email |
| Notifikasi | Email reset link |
| Validasi password baru | Min 8 karakter, kombinasi huruf + angka |

---

## 8. Account Activation Flow

### 8.1 Alur Registrasi & Aktivasi

```
┌──────────┐              ┌──────────────┐              ┌──────────┐
│  Client   │              │   Backend    │              │   Email   │
└────┬─────┘              └──────┬───────┘              └────┬─────┘
     │                           │                           │
     │  POST /api/v1/auth/       │                           │
     │    register               │                           │
     │  {name, email, nim,       │                           │
     │   phone, password, ...}   │                           │
     │──────────────────────────►│                           │
     │                           │                           │
     │                           │  Validate input           │
     │                           │  Create user              │
     │                           │  (is_active = true)       │
     │                           │  Assign role: reporter    │
     │                           │  Generate token           │
     │                           │  Log: AUD-USER-01         │
     │                           │                           │
     │  201 Created              │                           │
     │  {token, user}            │                           │
     │◄──────────────────────────│                           │
     │                           │                           │
     │  [User langsung aktif]    │                           │
     │  [Bisa langsung login]    │                           │
```

### 8.2 Pembuatan Akun oleh Admin

```
Admin/Super Admin membuat akun:

POST /api/v1/users
{
  "name": "Nama Satgas",
  "email": "satgas@university.ac.id",
  "nip": "1234567890",
  "phone_number": "628123456789",
  "role": "satgas_ppks",
  "password": "<generated>"
}

→ Akun langsung aktif
→ Password diberikan ke user melalui kanal aman (di luar sistem)
→ User disarankan ganti password setelah login pertama
```

### 8.3 Deaktivasi Akun

```
Ketika admin menonaktifkan akun:

1. User.is_active = false
2. Semua token user di-revoke
3. User tidak bisa login
4. Audit log: AUD-USER-03
5. Data user TIDAK dihapus (soft approach)
```

---

## 9. Role Permission Flow

### 9.1 Diagram Otorisasi

```
Request masuk
    │
    ▼
┌─────────────────────┐
│  Middleware: auth    │  → Validasi token → 401 jika invalid
└─────────┬───────────┘
          │
          ▼
┌─────────────────────┐
│  Middleware: role    │  → Cek role user → 403 jika tidak sesuai
│  (custom middleware) │
└─────────┬───────────┘
          │
          ▼
┌─────────────────────┐
│  Controller         │
│  ├── $this->authorize│  → Laravel Policy → 403 jika tidak diizinkan
│  │   ('action',     │
│  │    $resource)     │
│  └── Business Logic  │
└─────────────────────┘
```

### 9.2 Implementasi RBAC

```php
// Middleware: CheckRole
Route::middleware(['auth:sanctum', 'role:admin,super_admin'])
    ->group(function () {
        Route::get('/reports', [ReportController::class, 'index']);
    });

// Policy
class ReportPolicy
{
    public function view(User $user, Report $report): bool
    {
        return match ($user->role->code) {
            'super_admin', 'admin' => true,
            'satgas_ppks' => $report->assignedSatgas->contains($user),
            'reporter' => $report->reporter_id === $user->id,
            default => false,
        };
    }
}

// Gate (untuk permission-based)
Gate::define('cases.assess_risk', function (User $user) {
    return $user->role->code === 'satgas_ppks';
});
```

### 9.3 Role Hierarchy Check

| Aksi | super_admin | admin | satgas_ppks | reporter | anonymous |
|------|:-----------:|:-----:|:-----------:|:--------:|:---------:|
| Manage system config | ✅ | ❌ | ❌ | ❌ | ❌ |
| Manage users (CRUD) | ✅ | ✅ | ❌ | ❌ | ❌ |
| Verify & route reports | ✅ | ✅ | ❌ | ❌ | ❌ |
| Handle cases | ❌ | ❌ | ✅ | ❌ | ❌ |
| Submit reports | ❌ | ❌ | ❌ | ✅ | ✅ |
| Track own cases | ❌ | ❌ | ❌ | ✅ | 🔑 |

> 🔑 = Via tracking code only

---

## 10. Route Protection Strategy

### 10.1 Route Groups

```php
// routes/api.php

// === PUBLIC ROUTES (No Auth) ===
Route::prefix('v1')->group(function () {

    // Authentication
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

    // Anonymous Report
    Route::post('/reports/anonymous', [AnonymousReportController::class, 'store']);
    Route::get('/reports/track/{tracking_code}', [AnonymousReportController::class, 'track']);
    Route::post('/reports/track/{tracking_code}/messages', [AnonymousMessageController::class, 'store']);
    Route::get('/reports/track/{tracking_code}/messages', [AnonymousMessageController::class, 'index']);
});

// === AUTHENTICATED ROUTES ===
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // Auth Management
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // === REPORTER ROUTES ===
    Route::middleware(['role:reporter'])->group(function () {
        Route::post('/reports', [ReportController::class, 'store']);
        Route::get('/reports/my', [ReportController::class, 'myReports']);
        Route::get('/reports/{report}', [ReportController::class, 'show']);
        Route::post('/reports/{report}/evidence', [EvidenceController::class, 'store']);
        Route::get('/reports/{report}/messages', [MessageController::class, 'index']);
        Route::post('/reports/{report}/messages', [MessageController::class, 'store']);
    });

    // === ADMIN ROUTES ===
    Route::middleware(['role:admin,super_admin'])->group(function () {
        Route::get('/admin/reports', [AdminReportController::class, 'index']);
        Route::post('/admin/reports/{report}/verify', [AdminReportController::class, 'verify']);
        Route::post('/admin/reports/{report}/reject', [AdminReportController::class, 'reject']);
        Route::post('/admin/reports/{report}/request-info', [AdminReportController::class, 'requestInfo']);
        Route::post('/admin/reports/{report}/forward', [AdminReportController::class, 'forward']);
        Route::get('/admin/dashboard', [AdminDashboardController::class, 'index']);
        Route::apiResource('/admin/users', UserController::class);
    });

    // === SATGAS ROUTES ===
    Route::middleware(['role:satgas_ppks'])->group(function () {
        Route::get('/satgas/cases', [SatgasCaseController::class, 'index']);
        Route::get('/satgas/cases/{case}', [SatgasCaseController::class, 'show']);
        Route::post('/satgas/cases/{case}/assess', [RiskAssessmentController::class, 'store']);
        Route::post('/satgas/cases/{case}/investigate', [InvestigationController::class, 'store']);
        Route::post('/satgas/cases/{case}/recommend', [RecommendationController::class, 'store']);
        Route::post('/satgas/cases/{case}/decide', [DecisionController::class, 'store']);
        Route::post('/satgas/cases/{case}/monitor', [MonitoringController::class, 'store']);
        Route::post('/satgas/cases/{case}/close', [SatgasCaseController::class, 'close']);
        Route::post('/satgas/cases/{case}/escalate', [SatgasCaseController::class, 'escalate']);
        Route::get('/satgas/dashboard', [SatgasDashboardController::class, 'index']);
    });

    // === SUPER ADMIN ROUTES ===
    Route::middleware(['role:super_admin'])->group(function () {
        Route::get('/admin/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/admin/settings', [SettingsController::class, 'index']);
        Route::put('/admin/settings', [SettingsController::class, 'update']);
        Route::post('/admin/users/{user}/assign-role', [UserController::class, 'assignRole']);
    });
});
```

### 10.2 Middleware Stack

```
Semua API requests melewati middleware pipeline:

1. TrustProxies         → Handle proxy headers
2. HandleCors           → CORS headers
3. ThrottleRequests     → Rate limiting
4. SubstituteBindings   → Route model binding

Authenticated routes menambahkan:
5. auth:sanctum         → Validate Bearer token
6. role:{roles}         → Validate user role (custom)
7. AuditLogMiddleware   → Log request ke audit trail (custom)
```

---

## 11. Flutter Authentication Flow

> **Phase 2** — Spesifikasi ini disediakan sebagai referensi untuk Mobile Agent.

### 11.1 Alur Flutter Auth

```
App Launch
    │
    ▼
┌─────────────────────┐
│ Check Secure Storage │  → Ada token?
└─────────┬───────────┘
          │
    ┌─────┴─────┐
    │           │
    ▼           ▼
  Token       No Token
  exists      found
    │           │
    ▼           │
  GET /auth/me  │
    │           │
 ┌──┴──┐       │
 │     │       │
 ▼     ▼       ▼
200   401    Login Screen
 │     │       │
 ▼     ▼       ▼
Home  Clear   POST /auth/login
Screen token     │
       │         ▼
       └──→  Save token to
          Secure Storage
               │
               ▼
          Home Screen
```

### 11.2 Secure Storage

```dart
// Flutter: Token Storage Strategy
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class AuthStorage {
  static const _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
    iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock),
  );

  static Future<void> saveToken(String token) async {
    await _storage.write(key: 'auth_token', value: token);
  }

  static Future<String?> getToken() async {
    return await _storage.read(key: 'auth_token');
  }

  static Future<void> clearToken() async {
    await _storage.delete(key: 'auth_token');
  }
}
```

---

## 12. React Authentication Flow

### 12.1 Alur React Auth

```
App Mount
    │
    ▼
┌─────────────────────┐
│ AuthProvider Init     │
│ Check memory/cookie   │  → Ada token?
└─────────┬────────────┘
          │
    ┌─────┴─────┐
    │           │
    ▼           ▼
  Token       No Token
  exists      found
    │           │
    ▼           │
  GET /auth/me  │
  (TanStack     │
   Query)       │
    │           │
 ┌──┴──┐       │
 │     │       │
 ▼     ▼       ▼
200   401    Login Page
 │     │    (TanStack
 ▼     ▼     Router)
Auth  Clear     │
Context token   ▼
set    │     POST /auth/login
 │     │     (TanStack
 ▼     └──→   Mutation)
Protected       │
Routes          ▼
             Store token
             in memory
                │
                ▼
             Set Auth
             Context
                │
                ▼
             Redirect to
             Dashboard
```

### 12.2 Auth Context Implementation

```typescript
// Pseudocode — React Auth Context
interface AuthContextType {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (identifier: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

// Token storage: in-memory (NOT localStorage)
let inMemoryToken: string | null = null;

// TanStack Query: GET /auth/me
const useCurrentUser = () => useQuery({
  queryKey: ['auth', 'me'],
  queryFn: () => authApi.getCurrentUser(),
  enabled: !!inMemoryToken,
  retry: false,
});

// TanStack Mutation: POST /auth/login
const useLogin = () => useMutation({
  mutationFn: authApi.login,
  onSuccess: (data) => {
    inMemoryToken = data.token;
    queryClient.invalidateQueries({ queryKey: ['auth'] });
  },
});
```

> **Catatan MVP (Audit Patch v1.0.1)**: Karena token disimpan in-memory (`inMemoryToken`), variabel ini akan hilang saat page refresh. Ini berarti **user harus login ulang setelah refresh browser**. Ini adalah trade-off keamanan yang disengaja untuk MVP. Lihat Section 5.2.1 untuk detail dan rencana Post-MVP.

### 12.3 Route Protection (TanStack Router)

```typescript
// Pseudocode — Protected Route
const protectedRoute = createFileRoute('/dashboard')({
  beforeLoad: ({ context }) => {
    if (!context.auth.isAuthenticated) {
      throw redirect({ to: '/login' });
    }
  },
});

// Role-based route
const adminRoute = createFileRoute('/admin/reports')({
  beforeLoad: ({ context }) => {
    if (!['admin', 'super_admin'].includes(context.auth.user?.role)) {
      throw redirect({ to: '/unauthorized' });
    }
  },
});
```

---

## 13. Future 2FA Strategy

> **Status**: Post-MVP / Phase 3. Untuk admin dan satgas yang menangani data sensitif.

### 13.1 Rencana Implementasi

```
2FA hanya untuk role:
├── super_admin  → WAJIB
├── admin        → WAJIB
└── satgas_ppks  → WAJIB

2FA TIDAK untuk:
├── reporter     → Opsional (user choice)
└── anonymous    → Tidak berlaku
```

### 13.2 Metode 2FA yang Dipertimbangkan

| Metode | Prioritas | Kelebihan | Kekurangan |
|--------|:---------:|-----------|------------|
| TOTP (Google Authenticator) | **1st** | Standar industri, offline, gratis | Perlu app authenticator |
| WhatsApp OTP (via Fonnte) | **2nd** | Familiar, infrastruktur sudah ada | Biaya per OTP, dependency Fonnte |
| Email OTP | **3rd** | Simple, tidak perlu app | Kurang aman, delay email |

### 13.3 Alur 2FA (Future)

```
Login (dengan 2FA enabled):

1. POST /auth/login {identifier, password}
   → Response: { requires_2fa: true, session_token: "temp-..." }

2. POST /auth/verify-2fa {session_token, otp_code}
   → Response: { token: "1|abc...", user: {...} }

3. Jika OTP salah → 3 percobaan → lock 15 menit
```

---

## Changelog

| Versi | Tanggal | Perubahan |
|-------|---------|----------|
| 1.0.0 | 2026-06-09 | Versi awal |
| 1.0.1-patch | 2026-06-10 | Audit patch: catatan MVP token in-memory React (logout on refresh), rencana Post-MVP httpOnly Secure Cookie, penguatan aturan privasi anonim |

---

> **Catatan**: Dokumen ini adalah Tier 2 (GOVERNED). Perubahan memerlukan persetujuan. Seluruh alur di dokumen ini menjadi referensi wajib bagi Backend Agent, Web Agent, dan Mobile Agent.
