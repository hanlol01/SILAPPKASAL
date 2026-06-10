# MASTER_DATA.md — Master Data & Reference Tables

> **Sistem Informasi Laporan Pencegahan dan Penanganan Kekerasan Seksual (SILAPPKASAL)**
> Versi: 1.0.1-patch | Terakhir Diperbarui: 2026-06-10 | Status: BERLAKU — AUDIT PATCH | Tier: 2 (GOVERNED)

---

## Daftar Isi

1. [User Roles](#1-user-roles)
2. [Permission Matrix](#2-permission-matrix)
3. [Report Categories](#3-report-categories)
4. [Report Types](#4-report-types)
5. [Evidence Types](#5-evidence-types)
6. [Case Status](#6-case-status)
7. [Investigation Status](#7-investigation-status)
8. [Recommendation Status](#8-recommendation-status)
9. [Notification Types](#9-notification-types)
10. [Risk Levels](#10-risk-levels)
11. [Priority Levels](#11-priority-levels)
12. [Audit Log Events](#12-audit-log-events)
13. [System Settings](#13-system-settings)
14. [Reference Tables](#14-reference-tables)

---

## 1. User Roles

Setiap pengguna sistem memiliki tepat **satu role**. Role menentukan akses dan kemampuan pengguna dalam sistem.

| Kode | Role | Deskripsi | Platform | Guard |
|------|------|-----------|----------|-------|
| `ROLE-01` | `super_admin` | Pengelola seluruh sistem. Mengelola akun admin/satgas, konfigurasi sistem, melihat audit log. Tidak terlibat langsung dalam penanganan kasus. | Web | `sanctum` |
| `ROLE-02` | `admin` | Memverifikasi laporan masuk, meneruskan ke Satgas, mengelola akun pelapor, membuat laporan statistik. Tidak boleh mengubah isi laporan atau mengakses data investigasi. | Web | `sanctum` |
| `ROLE-03` | `satgas_ppks` | Menangani kasus: asesmen risiko, investigasi, rekomendasi, pendampingan, monitoring. Hanya mengakses kasus yang ditugaskan. | Web | `sanctum` |
| `ROLE-04` | `reporter` | Membuat laporan (terbuka/rahasia), memantau status kasus, berkomunikasi dengan Satgas via pesan internal. | Web (tester), Mobile (Phase 2) | `sanctum` |
| `ROLE-05` | `anonymous` | Membuat laporan anonim tanpa login. Akses terbatas pada submit laporan dan tracking via kode pelacakan. | Web, Mobile (Phase 2) | Tidak ada (public) |

### 1.1 Hierarki Role

```
super_admin (ROLE-01)
├── Mewarisi semua permission admin
├── Konfigurasi sistem
├── Manajemen akun admin & satgas
└── Audit log viewer

admin (ROLE-02)
├── Verifikasi & routing laporan
├── Manajemen akun pelapor
└── Statistik & reporting

satgas_ppks (ROLE-03)
├── Asesmen risiko
├── Investigasi
├── Rekomendasi
├── Pendampingan & monitoring
└── Hanya kasus yang ditugaskan

reporter (ROLE-04)
├── Submit laporan (terbuka/rahasia)
├── Track kasus sendiri
└── Pesan internal

anonymous (ROLE-05)
├── Submit laporan anonim
└── Track via kode pelacakan
```

### 1.2 Database Schema (tabel `roles`)

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | `bigint` PK | Auto-increment |
| `code` | `varchar(20)` UNIQUE | Kode role (e.g., `super_admin`) |
| `name` | `varchar(50)` | Nama tampilan (e.g., "Super Admin") |
| `description` | `text` | Deskripsi role |
| `is_active` | `boolean` | Default `true` |
| `created_at` | `timestamp` | — |
| `updated_at` | `timestamp` | — |

---

## 2. Permission Matrix

### 2.1 Daftar Permission

| Kode | Permission | Deskripsi | Modul |
|------|-----------|-----------|-------|
| `PERM-001` | `system.configure` | Mengubah konfigurasi sistem | Sistem |
| `PERM-002` | `system.audit_log.view` | Melihat audit log | Sistem |
| `PERM-003` | `users.create` | Membuat akun pengguna | User |
| `PERM-004` | `users.read` | Melihat daftar pengguna | User |
| `PERM-005` | `users.update` | Mengedit akun pengguna | User |
| `PERM-006` | `users.deactivate` | Menonaktifkan akun pengguna | User |
| `PERM-007` | `users.assign_role` | Menetapkan role ke pengguna | User |
| `PERM-010` | `reports.create` | Membuat laporan baru | Laporan |
| `PERM-011` | `reports.read.own` | Melihat laporan sendiri | Laporan |
| `PERM-012` | `reports.read.all` | Melihat semua laporan masuk | Laporan |
| `PERM-013` | `reports.verify` | Memverifikasi laporan | Laporan |
| `PERM-014` | `reports.reject` | Menolak laporan | Laporan |
| `PERM-015` | `reports.forward` | Meneruskan laporan ke Satgas | Laporan |
| `PERM-016` | `reports.request_info` | Meminta info tambahan dari pelapor | Laporan |
| `PERM-019` | `cases.read.metadata` | Melihat metadata kasus (nomor registrasi, status, SLA, statistik) tanpa akses ke detail investigasi, bukti, atau data sensitif korban/terlapor. Untuk Admin. | Kasus |
| `PERM-020` | `cases.read.assigned` | Melihat data lengkap kasus yang ditugaskan (termasuk investigasi, bukti, catatan). Untuk Satgas. | Kasus |
| `PERM-021` | `cases.read.all` | Melihat metadata semua kasus. **Hanya untuk Super Admin.** Dibatasi pada scope audit/metadata (nomor registrasi, status, SLA, assignment). BUKAN akses bebas ke data investigasi, kronologi, atau identitas korban/terlapor. Akses ke data sensitif memerlukan `system.break_glass_access`. | Kasus |
| `PERM-022` | `cases.assess_risk` | Mengisi asesmen risiko | Kasus |
| `PERM-023` | `cases.investigate` | Mencatat aktivitas investigasi | Kasus |
| `PERM-024` | `cases.recommend` | Menyusun rekomendasi | Kasus |
| `PERM-025` | `cases.record_decision` | Mencatat keputusan institusi | Kasus |
| `PERM-026` | `cases.monitor` | Mencatat monitoring pasca kasus | Kasus |
| `PERM-027` | `cases.close` | Menutup kasus | Kasus |
| `PERM-028` | `cases.assign_satgas` | Menugaskan Satgas ke kasus | Kasus |
| `PERM-029` | `cases.escalate` | Mengeskalasi kasus | Kasus |
| `PERM-030` | `messages.send` | Mengirim pesan internal | Komunikasi |
| `PERM-031` | `messages.read.case` | Membaca pesan dalam konteks kasus | Komunikasi |
| `PERM-040` | `dashboard.admin` | Melihat dashboard admin | Dashboard |
| `PERM-041` | `dashboard.satgas` | Melihat dashboard satgas | Dashboard |
| `PERM-042` | `statistics.view` | Melihat statistik | Dashboard |
| `PERM-043` | `statistics.export` | Export statistik | Dashboard |
| `PERM-050` | `evidence.upload` | Mengupload bukti | Bukti |
| `PERM-051` | `evidence.view.case` | Melihat bukti dalam konteks kasus. Hanya untuk Satgas (assigned) dan Reporter (own). | Bukti |
| `PERM-052` | `evidence.download` | Mengunduh bukti | Bukti |
| `PERM-060` | `system.break_glass_access` | Akses darurat ke data sensitif kasus (investigasi, bukti, identitas) untuk Super Admin. Memerlukan alasan tertulis, dicatat sebagai audit log **CRITICAL**, dan hanya untuk kondisi emergency (misal: insiden keamanan, perintah regulator/pengadilan). | Sistem |

### 2.2 Matriks Role × Permission

| Permission | `super_admin` | `admin` | `satgas_ppks` | `reporter` | `anonymous` |
|------------|:---:|:---:|:---:|:---:|:---:|
| `system.configure` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `system.audit_log.view` | ✅ | 📊 | ❌ | ❌ | ❌ |
| `system.break_glass_access` | 🚨 | ❌ | ❌ | ❌ | ❌ |
| `users.create` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `users.read` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `users.update` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `users.deactivate` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `users.assign_role` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `reports.create` | ❌ | ❌ | ❌ | ✅ | ✅ |
| `reports.read.own` | ❌ | ❌ | ❌ | ✅ | 🔑 |
| `reports.read.all` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `reports.verify` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `reports.reject` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `reports.forward` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `reports.request_info` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `cases.read.metadata` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `cases.read.assigned` | ❌ | ❌ | ✅ | ❌ | ❌ |
| `cases.read.all` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `cases.assess_risk` | ❌ | ❌ | ✅ | ❌ | ❌ |
| `cases.investigate` | ❌ | ❌ | ✅ | ❌ | ❌ |
| `cases.recommend` | ❌ | ❌ | ✅ | ❌ | ❌ |
| `cases.record_decision` | ❌ | ❌ | ✅ | ❌ | ❌ |
| `cases.monitor` | ❌ | ❌ | ✅ | ❌ | ❌ |
| `cases.close` | ❌ | ❌ | ✅ | ❌ | ❌ |
| `cases.assign_satgas` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `cases.escalate` | ❌ | ❌ | ✅ | ❌ | ❌ |
| `messages.send` | ❌ | ❌ | ✅ | ✅ | 🔑 |
| `messages.read.case` | ❌ | ❌ | ✅ | ✅ | 🔑 |
| `dashboard.admin` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `dashboard.satgas` | ❌ | ❌ | ✅ | ❌ | ❌ |
| `statistics.view` | ✅ | ✅ | 📊 | ❌ | ❌ |
| `statistics.export` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `evidence.upload` | ❌ | ❌ | ✅ | ✅ | ✅ |
| `evidence.view.case` | ❌ | ❌ | ✅ | ✅ | 🔑 |
| `evidence.download` | ❌ | ❌ | ✅ | ❌ | ❌ |

> ✅ = Full access | 📊 = Limited/filtered access | 🔑 = Via tracking code only | ❌ = No access | 🚨 = Emergency only (break-glass)

### 2.3 Catatan Perubahan Permission (Audit Patch v1.0.1)

> **PENTING — Perubahan terhadap versi 1.0.0:**

| Perubahan | Sebelum (v1.0.0) | Sesudah (v1.0.1-patch) | Alasan |
|-----------|------------------|------------------------|--------|
| **Admin → `cases.read.all`** | ✅ (full access) | ❌ → diganti `cases.read.metadata` | Admin tidak memerlukan akses ke detail investigasi. Cukup metadata (status, SLA, nomor registrasi) untuk routing dan statistik. |
| **Super Admin → `cases.read.all`** | ✅ (full access) | ✅ (metadata/audit scope only) | Scope dibatasi ke metadata. Akses data sensitif (investigasi, identitas korban) memerlukan `system.break_glass_access`. |
| **Super Admin → `evidence.view.case`** | ✅ | ❌ | Super Admin tidak otomatis melihat bukti kasus. Akses darurat via `system.break_glass_access`. |
| **Baru: `cases.read.metadata`** | — | Ditambahkan | Permission baru untuk Admin melihat metadata kasus tanpa akses data sensitif. |
| **Baru: `system.break_glass_access`** | — | Ditambahkan | Mekanisme akses darurat untuk Super Admin ke data sensitif dengan audit trail CRITICAL. |

### 2.4 Break-Glass Access Protocol (`system.break_glass_access`)

Mekanisme akses darurat yang memungkinkan Super Admin mengakses data sensitif kasus (investigasi, bukti, identitas) dalam kondisi emergency.

| Aspek | Ketentuan |
|-------|----------|
| **Siapa** | Hanya `super_admin` |
| **Kapan** | Kondisi emergency: insiden keamanan, perintah pengadilan/regulator, investigasi internal terhadap Satgas |
| **Syarat Aktivasi** | Wajib mengisi alasan tertulis (`justification`) minimal 50 karakter |
| **Durasi** | Sesi akses terbatas (maksimal 4 jam per sesi, configurable) |
| **Audit** | Dicatat sebagai audit log severity **CRITICAL** (`AUD-SEC-04: security.break_glass_activated`) |
| **Data yang Dicatat** | `actor_id`, `case_id`, `justification`, `resources_accessed`, `timestamp_start`, `timestamp_end` |
| **Notifikasi** | Notifikasi otomatis ke seluruh Super Admin lain (jika ada) |
| **Review** | Setiap penggunaan break-glass WAJIB di-review oleh Project Owner dalam 48 jam |
| **Penyalahgunaan** | Penggunaan tanpa justifikasi valid merupakan pelanggaran kebijakan |

```
Alur Break-Glass Access:

1. Super Admin memilih "Emergency Access" pada kasus tertentu
2. Sistem menampilkan form justifikasi (wajib isi, min 50 karakter)
3. Super Admin memilih scope akses yang dibutuhkan:
   ├── Investigasi detail
   ├── Bukti/evidence files
   └── Identitas korban/terlapor
4. Sistem mencatat audit log CRITICAL
5. Akses diberikan dengan durasi terbatas
6. Setelah sesi berakhir, akses otomatis dicabut
7. Notifikasi ke Super Admin lain + Project Owner
```

---

## 3. Report Categories

Berdasarkan Permendikbudristek No. 30 Tahun 2021 dan UU TPKS.

| Kode | Kategori | Deskripsi | Contoh |
|------|----------|-----------|--------|
| `RCAT-01` | Pelecehan seksual verbal | Ucapan bernuansa seksual yang tidak diinginkan | Komentar seksual, lelucon seksual, siulan |
| `RCAT-02` | Pelecehan seksual non-verbal | Gestur atau tindakan non-fisik bernuansa seksual | Gesture seksual, tatapan seksual, eksibisionisme |
| `RCAT-03` | Pelecehan seksual fisik | Kontak fisik bernuansa seksual tanpa persetujuan | Sentuhan tidak diinginkan, meraba, mencium paksa |
| `RCAT-04` | Pemaksaan hubungan seksual | Pemaksaan aktivitas seksual | Pemerkosaan, percobaan pemerkosaan |
| `RCAT-05` | Kekerasan seksual berbasis digital | Kekerasan seksual melalui media digital | Penyebaran konten intim tanpa izin, sexting paksa, cyberstalking |
| `RCAT-06` | Pemaksaan kontrasepsi | Pemaksaan terkait alat kontrasepsi | Memaksa penggunaan/menolak kontrasepsi |
| `RCAT-07` | Pemaksaan sterilisasi | Pemaksaan prosedur sterilisasi | Memaksa prosedur sterilisasi tanpa persetujuan |
| `RCAT-08` | Pemaksaan perkawinan | Memaksa pernikahan | Memaksa menikah karena tekanan |
| `RCAT-09` | Penyiksaan seksual | Penyiksaan melibatkan organ seksual | Penyiksaan bernuansa seksual |
| `RCAT-10` | Eksploitasi seksual | Memanfaatkan posisi untuk kepentingan seksual | Penyalahgunaan kekuasaan untuk hubungan seksual |
| `RCAT-11` | Perbudakan seksual | Pemaksaan aktivitas seksual berulang | Memaksa aktivitas seksual secara sistematis |
| `RCAT-99` | Lainnya | Kategori yang tidak tercakup di atas | Harus diisi deskripsi manual |

### 3.1 Database Schema (tabel `report_categories`)

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | `bigint` PK | Auto-increment |
| `code` | `varchar(10)` UNIQUE | Kode kategori (e.g., `RCAT-01`) |
| `name` | `varchar(100)` | Nama kategori |
| `description` | `text` | Deskripsi lengkap |
| `examples` | `text` NULLABLE | Contoh kasus |
| `legal_basis` | `varchar(255)` NULLABLE | Dasar hukum |
| `is_active` | `boolean` | Default `true` |
| `sort_order` | `integer` | Urutan tampilan |
| `created_at` | `timestamp` | — |
| `updated_at` | `timestamp` | — |

---

## 4. Report Types

| Kode | Tipe | Deskripsi Bisnis | Login Required | Identitas Pelapor | Akses Satgas ke Identitas | Notifikasi WA |
|------|------|-----------------|:--------------:|-------------------|:-------------------------:|:-------------:|
| `RTYP-01` | `open` (Terbuka) | Pelapor mengungkap identitas. Satgas dapat menghubungi langsung. Proses verifikasi lebih cepat. | Ya | Diketahui | Ya | Ya |
| `RTYP-02` | `confidential` (Rahasia) | Identitas pelapor dilindungi. Hanya pihak tertentu (Satgas yang ditugaskan) yang mengetahui. Komunikasi via sistem. | Ya | Dilindungi | Ya (terbatas) | Ya |
| `RTYP-03` | `anonymous` (Anonim) | Identitas sepenuhnya tersembunyi. Tidak ada data yang mengaitkan laporan ke individu. Akses via kode pelacakan. | Tidak | Tersembunyi | Tidak | Tidak |

### 4.1 Aturan Bisnis per Tipe

- **Terbuka**: Alur standar. Pelapor dapat dihubungi langsung.
- **Rahasia**: Data pelapor dienkripsi (field-level encryption). Admin hanya melihat nomor registrasi.
- **Anonim**: Tidak ada logging IP/device. Kode pelacakan menjadi satu-satunya cara akses. Jika hilang, tidak bisa dipulihkan (by-design).

### 4.2 Perlindungan Privasi Anonim (Audit Patch v1.0.1)

> **PENTING**: Ketentuan ini memperkuat aturan privasi anonim di seluruh sistem.

| Aspek | Ketentuan | Alasan |
|-------|-----------|--------|
| **IP Address** | ❌ TIDAK disimpan pada tabel `reports`, `cases`, atau audit log bisnis | Mencegah de-anonimisasi pelapor |
| **Device Fingerprint** | ❌ TIDAK disimpan dalam konteks laporan anonim | Mencegah korelasi device ke individu |
| **IP untuk Rate Limiting** | ✅ Boleh digunakan sementara (in-memory) oleh middleware rate limiter | Perlindungan terhadap abuse/spam |
| **Security Log** | ✅ Boleh mencatat IP dalam bentuk **hashed** (`SHA-256(IP + daily_salt)`) atau **masked** (`192.168.xxx.xxx`) untuk deteksi serangan | Keamanan sistem tanpa mengorbankan anonimitas |
| **Retention Security Log Anonim** | Maksimal **7 hari** untuk security log yang mengandung hashed/masked IP dari aksi anonim | Minimalisasi risiko korelasi temporal |
| **Larangan Korelasi** | ❌ Dilarang mengkorelasikan hashed IP security log dengan laporan anonim tertentu | By-design untuk menjaga anonimitas |

```
Alur IP pada Laporan Anonim:

1. Request masuk → IP tersedia di HTTP layer
2. Rate Limiter (middleware) → IP digunakan in-memory untuk throttling
3. Security Log → IP di-hash (SHA-256 + daily rotating salt) atau di-mask
4. Report/Case record → IP TIDAK dicatat
5. Audit Log bisnis → actor_ip = NULL untuk aksi anonim
6. Security log entry → auto-purge setelah 7 hari
```

---

## 5. Evidence Types

| Kode | Tipe | Ekstensi yang Diizinkan | Maks Ukuran | Deskripsi |
|------|------|------------------------|-------------|-----------|
| `EVID-01` | Foto | JPG, JPEG, PNG, GIF | 25 MB | Foto bukti kejadian, screenshot, hasil tangkap layar |
| `EVID-02` | Video | MP4, MOV | 25 MB | Rekaman video sebagai bukti |
| `EVID-03` | Dokumen | PDF, DOC, DOCX | 25 MB | Surat, dokumen pendukung, transkrip |
| `EVID-04` | Tangkapan Layar | JPG, JPEG, PNG | 25 MB | Screenshot chat, email, media sosial |

### 5.1 Aturan Upload

| Aturan | Nilai |
|--------|-------|
| Maks jumlah file per laporan | 10 |
| Maks ukuran per file | 25 MB |
| Maks total ukuran per laporan | 250 MB |
| Penamaan file di storage | UUID v4 (nama asli disimpan di DB) |
| Validasi | MIME type + ekstensi (server-side) |
| Penyimpanan (development) | Local private storage (`storage/app/private/evidence/`) |
| Penyimpanan (production) | S3-compatible storage (opsional/future) |
| Enkripsi | Encryption at rest (file dienkripsi sebelum simpan) |
| Akses | Hanya via signed URL / controller terproteksi |

---

## 6. Case Status

### 6.1 Daftar Status

| Kode | Status | Label Tampilan | Deskripsi Bisnis | Tahap Workflow | Penanggung Jawab |
|------|--------|----------------|------------------|:--------------:|-----------------|
| `CSTS-01` | `submitted` | Laporan Dikirim | Laporan baru masuk ke sistem, menunggu verifikasi admin. | 1 - Pelaporan | Sistem |
| `CSTS-02` | `under_review` | Sedang Diverifikasi | Admin sedang memeriksa kelengkapan dan validitas laporan. | 2 - Verifikasi | Admin |
| `CSTS-03` | `need_info` | Butuh Informasi | Admin membutuhkan informasi tambahan dari pelapor. | 2 - Verifikasi | Admin |
| `CSTS-04` | `rejected` | Ditolak | Laporan ditolak dengan alasan tertulis. **Status terminal.** | 2 - Verifikasi | Admin |
| `CSTS-05` | `forwarded` | Diteruskan ke Satgas | Laporan terverifikasi dan diteruskan ke Satgas PPKS. | 2 - Verifikasi | Admin |
| `CSTS-06` | `assessment` | Asesmen Risiko | Satgas melakukan asesmen tingkat risiko kasus. | 3 - Asesmen | Satgas |
| `CSTS-07` | `investigation` | Investigasi | Proses investigasi sedang berjalan. | 4 - Investigasi | Satgas |
| `CSTS-08` | `mediation` | Mediasi | Proses mediasi (opsional, jika memenuhi syarat). | 4 - Investigasi | Satgas |
| `CSTS-09` | `recommendation` | Rekomendasi | Satgas menyusun rekomendasi penanganan. | 5 - Rekomendasi | Satgas |
| `CSTS-10` | `decision` | Menunggu Keputusan | Rekomendasi diajukan, menunggu keputusan pimpinan PT. | 6 - Keputusan | Pimpinan PT |
| `CSTS-11` | `decided` | Keputusan Dikeluarkan | Keputusan pimpinan telah diterbitkan (SK). | 6 - Keputusan | Pimpinan PT |
| `CSTS-12` | `recovery` | Pemulihan | Tahap pendampingan korban (psikologis, hukum, akademik). | 7 - Monitoring | Satgas |
| `CSTS-13` | `monitoring` | Monitoring | Monitoring pasca kasus (3-6 bulan). | 7 - Monitoring | Satgas |
| `CSTS-14` | `closed` | Selesai | Kasus selesai. **Status terminal.** | 7 - Monitoring | Satgas |
| `CSTS-15` | `escalated` | Dieskalasi | Kasus dieskalasi ke pihak luar (kepolisian, LPSK, dll). | Kapan saja | Satgas |

### 6.2 Transisi Status yang Valid

```
submitted     → [under_review]
under_review  → [need_info, forwarded, rejected]
need_info     → [under_review]
forwarded     → [assessment]
assessment    → [investigation]
investigation → [mediation, recommendation]
mediation     → [recommendation]
recommendation → [decision]
decision      → [decided]
decided       → [recovery]
recovery      → [monitoring]
monitoring    → [closed]

Special Transitions:
ANY_ACTIVE    → [escalated]     (dari status mana pun yang bukan terminal)
rejected      → TERMINAL        (tidak bisa dilanjutkan)
closed        → TERMINAL        (tidak bisa dibuka kembali via sistem)
```

### 6.3 Status Groups

| Group | Status yang Termasuk | Kegunaan |
|-------|---------------------|----------|
| `active` | Semua kecuali `rejected`, `closed` | Filter kasus aktif |
| `pending_admin` | `submitted`, `under_review`, `need_info` | Antrian kerja admin |
| `pending_satgas` | `forwarded`, `assessment`, `investigation`, `mediation`, `recommendation` | Antrian kerja satgas |
| `pending_decision` | `decision` | Menunggu pimpinan |
| `post_decision` | `decided`, `recovery`, `monitoring` | Pasca keputusan |
| `terminal` | `rejected`, `closed` | Kasus selesai |

---

## 7. Investigation Status

Status internal untuk mencatat progres investigasi per kasus.

| Kode | Status | Label | Deskripsi |
|------|--------|-------|-----------|
| `INVS-01` | `planning` | Perencanaan | Satgas menyusun rencana investigasi |
| `INVS-02` | `evidence_collection` | Pengumpulan Bukti | Mengumpulkan bukti fisik dan digital |
| `INVS-03` | `victim_interview` | Wawancara Korban | Wawancara korban oleh petugas terlatih |
| `INVS-04` | `witness_interview` | Wawancara Saksi | Wawancara saksi langsung dan tidak langsung |
| `INVS-05` | `respondent_interview` | Wawancara Terlapor | Wawancara terlapor (hak klarifikasi, hak didampingi) |
| `INVS-06` | `evidence_analysis` | Analisis Bukti | Analisis dokumen, rekaman, chat, email, media sosial |
| `INVS-07` | `report_drafting` | Penyusunan Laporan | Penyusunan BAP dan laporan investigasi |
| `INVS-08` | `completed` | Selesai | Investigasi selesai, siap untuk rekomendasi |

---

## 8. Recommendation Status

| Kode | Status | Label | Deskripsi |
|------|--------|-------|-----------|
| `RECS-01` | `drafting` | Penyusunan | Satgas sedang menyusun rekomendasi |
| `RECS-02` | `internal_review` | Review Internal | Rekomendasi direview oleh sesama Satgas |
| `RECS-03` | `submitted_to_leader` | Diajukan | Rekomendasi diajukan ke pimpinan PT |
| `RECS-04` | `accepted` | Diterima | Rekomendasi diterima oleh pimpinan |
| `RECS-05` | `partially_accepted` | Diterima Sebagian | Rekomendasi diterima dengan modifikasi |
| `RECS-06` | `rejected` | Ditolak | Rekomendasi ditolak, perlu revisi |
| `RECS-07` | `revised` | Direvisi | Rekomendasi direvisi setelah feedback |

---

## 9. Notification Types

| Kode | Event | Penerima | Kanal | Template Key | Klasifikasi |
|------|-------|----------|-------|-------------|-------------|
| `NOTIF-01` | Laporan baru masuk | Admin | WhatsApp, In-App | `report.new` | MVP Extended |
| `NOTIF-02` | Konfirmasi laporan diterima | Pelapor | WhatsApp | `report.confirmed` | MVP Extended |
| `NOTIF-03` | Status kasus berubah | Pelapor | WhatsApp | `case.status_changed` | MVP Extended |
| `NOTIF-04` | Info tambahan dibutuhkan | Pelapor | WhatsApp | `report.need_info` | MVP Extended |
| `NOTIF-05` | Laporan ditolak | Pelapor | WhatsApp | `report.rejected` | MVP Extended |
| `NOTIF-06` | Kasus di-forward ke Satgas | Satgas | WhatsApp, In-App | `case.forwarded` | MVP Extended |
| `NOTIF-07` | Pesan baru di messaging | Pelapor/Satgas | WhatsApp, In-App | `message.new` | MVP Extended |
| `NOTIF-08` | SLA warning (75%) | Admin/Satgas | WhatsApp, In-App | `sla.warning` | Post-MVP |
| `NOTIF-09` | SLA breach (terlampaui) | Admin + Super Admin | WhatsApp, In-App | `sla.breach` | Post-MVP |
| `NOTIF-10` | Keputusan institusi dikeluarkan | Pelapor | WhatsApp | `case.decided` | MVP Extended |
| `NOTIF-11` | Kasus ditutup | Pelapor | WhatsApp | `case.closed` | MVP Extended |

### 9.1 Aturan Notifikasi

| Kode | Aturan | Detail |
|------|--------|--------|
| `NRUL-01` | Pelapor anonim tidak menerima WhatsApp | Sistem tidak menyimpan nomor telepon pelapor anonim |
| `NRUL-02` | Retry pada kegagalan | Maks 3 kali, exponential backoff (1, 5, 15 menit) |
| `NRUL-03` | Logging kegagalan | Notifikasi gagal dicatat di `notification_logs` |
| `NRUL-04` | Tanpa data sensitif | Notifikasi tidak boleh memuat nama korban, kronologi, identitas terlapor |
| `NRUL-05` | Template configurable | Template bisa diubah via konfigurasi sistem |

---

## 10. Risk Levels

| Kode | Level | Label | Deskripsi | SLA Perlindungan | Tindakan |
|------|-------|-------|-----------|:----------------:|----------|
| `RISK-01` | `low` | Rendah | Tidak ada ancaman langsung terhadap keselamatan korban. Kejadian bersifat verbal/non-verbal ringan. | Standar | Prosedur standar investigasi |
| `RISK-02` | `medium` | Sedang | Korban mengalami tekanan psikologis. Ada potensi eskalasi. Membutuhkan perhatian prioritas. | Standar | Prosedur prioritas, rujukan psikologis |
| `RISK-03` | `high` | Tinggi | Ancaman keselamatan aktif, kekerasan berulang, potensi trauma berat, atau korban anak. | Maks 1×24 jam | Perlindungan darurat, eskalasi jika perlu |

### 10.1 Kriteria Eskalasi Darurat (Risk Level: High)

1. Ancaman langsung terhadap keselamatan korban
2. Kekerasan seksual berat (RCAT-04, RCAT-09, RCAT-11)
3. Korban adalah anak di bawah umur
4. Potensi penghilangan barang bukti
5. Risiko pengulangan tindak kekerasan

---

## 11. Priority Levels

Digunakan untuk prioritas penanganan laporan oleh Admin saat verifikasi.

| Kode | Level | Label | Deskripsi | Target SLA Verifikasi |
|------|-------|-------|-----------|:---------------------:|
| `PRIO-01` | `urgent` | Mendesak | Kekerasan berat, ancaman keselamatan aktif, korban anak | 4 jam kerja |
| `PRIO-02` | `high` | Tinggi | Kekerasan fisik, potensi eskalasi | 1 hari kerja |
| `PRIO-03` | `normal` | Normal | Kasus standar, membutuhkan penanganan reguler | 2 hari kerja |
| `PRIO-04` | `low` | Rendah | Laporan informasional, tidak ada urgensi langsung | 2 hari kerja |

---

## 12. Audit Log Events

### 12.1 Daftar Event

| Kode | Event | Deskripsi | Data yang Dicatat | Severity |
|------|-------|-----------|-------------------|----------|
| `AUD-AUTH-01` | `auth.login` | User berhasil login | user_id, ip, user_agent, timestamp | INFO |
| `AUD-AUTH-02` | `auth.login_failed` | Percobaan login gagal | identifier, ip, user_agent, reason | WARNING |
| `AUD-AUTH-03` | `auth.logout` | User logout | user_id, timestamp | INFO |
| `AUD-AUTH-04` | `auth.password_reset` | Password berhasil direset | user_id, timestamp | INFO |
| `AUD-AUTH-05` | `auth.token_revoked` | Token direvoke | user_id, token_id, timestamp | INFO |
| `AUD-USER-01` | `user.created` | Akun baru dibuat | creator_id, new_user_id, role | INFO |
| `AUD-USER-02` | `user.updated` | Data user diubah | editor_id, user_id, changed_fields | INFO |
| `AUD-USER-03` | `user.deactivated` | Akun dinonaktifkan | admin_id, user_id, reason | WARNING |
| `AUD-USER-04` | `user.role_changed` | Role user diubah | admin_id, user_id, old_role, new_role | WARNING |
| `AUD-RPT-01` | `report.submitted` | Laporan baru di-submit | reporter_id (null jika anonim), report_id, type | INFO |
| `AUD-RPT-02` | `report.verified` | Laporan diverifikasi | admin_id, report_id, action | INFO |
| `AUD-RPT-03` | `report.rejected` | Laporan ditolak | admin_id, report_id, reason | WARNING |
| `AUD-RPT-04` | `report.forwarded` | Laporan diteruskan ke Satgas | admin_id, report_id, satgas_id | INFO |
| `AUD-RPT-05` | `report.info_requested` | Info tambahan diminta | admin_id, report_id | INFO |
| `AUD-CASE-01` | `case.risk_assessed` | Asesmen risiko dilakukan | satgas_id, case_id, risk_level | INFO |
| `AUD-CASE-02` | `case.investigation_started` | Investigasi dimulai | satgas_id, case_id | INFO |
| `AUD-CASE-03` | `case.investigation_updated` | Aktivitas investigasi ditambah | satgas_id, case_id, activity_type | INFO |
| `AUD-CASE-04` | `case.recommendation_submitted` | Rekomendasi diajukan | satgas_id, case_id | INFO |
| `AUD-CASE-05` | `case.decision_recorded` | Keputusan dicatat | satgas_id, case_id, decision_number | INFO |
| `AUD-CASE-06` | `case.status_changed` | Status kasus berubah | actor_id, case_id, old_status, new_status | INFO |
| `AUD-CASE-07` | `case.closed` | Kasus ditutup | satgas_id, case_id | INFO |
| `AUD-CASE-08` | `case.escalated` | Kasus dieskalasi | satgas_id, case_id, escalation_type | WARNING |
| `AUD-CASE-09` | `case.satgas_assigned` | Satgas ditugaskan ke kasus | admin_id, case_id, satgas_id | INFO |
| `AUD-EVID-01` | `evidence.uploaded` | Bukti diupload | uploader_id, report_id, file_type, file_size | INFO |
| `AUD-EVID-02` | `evidence.viewed` | Bukti dilihat | viewer_id, evidence_id | INFO |
| `AUD-EVID-03` | `evidence.downloaded` | Bukti diunduh | downloader_id, evidence_id | WARNING |
| `AUD-MSG-01` | `message.sent` | Pesan dikirim | sender_id, case_id, is_anonymous | INFO |
| `AUD-SYS-01` | `system.config_changed` | Konfigurasi sistem diubah | admin_id, config_key, old_value, new_value | WARNING |
| `AUD-SEC-01` | `security.rate_limit_hit` | Rate limit tercapai | ip, endpoint, count | WARNING |
| `AUD-SEC-02` | `security.unauthorized_access` | Akses tidak sah terdeteksi | user_id, resource, ip | CRITICAL |
| `AUD-SEC-03` | `security.suspicious_activity` | Aktivitas mencurigakan | user_id, activity_type, details | CRITICAL |
| `AUD-SEC-04` | `security.break_glass_activated` | Super Admin mengaktifkan akses darurat ke data sensitif kasus via `system.break_glass_access` | actor_id, case_id, justification, scope_requested, resources_accessed, timestamp_start, timestamp_end | CRITICAL |

### 12.2 Aturan Audit Log

| Aturan | Detail |
|--------|--------|
| Retention | Minimum 5 tahun (sesuai data retention policy) |
| Immutability | Audit log tidak boleh dihapus atau dimodifikasi |
| Data masking | Nama korban, kronologi, data sensitif harus di-mask dalam log |
| Akses | Hanya `super_admin` yang bisa melihat audit log lengkap |
| Storage | Tabel terpisah, tidak di-soft-delete |

---

## 13. System Settings

Konfigurasi yang dapat diubah oleh Super Admin via antarmuka.

| Kode | Key | Tipe | Default | Deskripsi |
|------|-----|------|---------|-----------|
| `SSET-01` | `sla.verification_days` | `integer` | `2` | Target SLA verifikasi awal (hari kerja) |
| `SSET-02` | `sla.assessment_days` | `integer` | `5` | Target SLA asesmen risiko (hari kerja) |
| `SSET-03` | `sla.investigation_days` | `integer` | `30` | Target SLA investigasi (hari kerja) |
| `SSET-04` | `sla.recommendation_days` | `integer` | `7` | Target SLA rekomendasi (hari kerja) |
| `SSET-05` | `sla.decision_days` | `integer` | `14` | Target SLA keputusan (hari kerja) |
| `SSET-06` | `sla.emergency_hours` | `integer` | `24` | Target perlindungan darurat (jam) |
| `SSET-07` | `sla.monitoring_months` | `integer` | `6` | Durasi monitoring pasca kasus (bulan) |
| `SSET-08` | `sla.warning_threshold` | `integer` | `75` | Threshold SLA warning (persentase) |
| `SSET-10` | `upload.max_file_size_mb` | `integer` | `25` | Maks ukuran file per upload (MB) |
| `SSET-11` | `upload.max_files_per_report` | `integer` | `10` | Maks jumlah file per laporan |
| `SSET-12` | `upload.allowed_extensions` | `json` | `["jpg","jpeg","png","gif","mp4","mov","pdf","doc","docx"]` | Ekstensi yang diizinkan |
| `SSET-20` | `auth.token_expiry_hours` | `integer` | `24` | Masa berlaku token (jam) |
| `SSET-21` | `auth.password_min_length` | `integer` | `8` | Minimum panjang password |
| `SSET-22` | `auth.max_login_attempts` | `integer` | `5` | Maks percobaan login per menit |
| `SSET-30` | `notification.retry_max` | `integer` | `3` | Maks retry notifikasi gagal |
| `SSET-31` | `notification.fonnte_enabled` | `boolean` | `true` | Aktifkan notifikasi WhatsApp |
| `SSET-40` | `pagination.default_per_page` | `integer` | `20` | Default item per halaman |

---

## 14. Reference Tables

### 14.1 Tabel Relasi Kampus (Pelapor & Terlapor)

| Kode | Status Kampus | Deskripsi |
|------|--------------|-----------|
| `CAMP-01` | `mahasiswa` | Mahasiswa aktif |
| `CAMP-02` | `dosen` | Dosen/tenaga pengajar |
| `CAMP-03` | `tendik` | Tenaga kependidikan |
| `CAMP-04` | `alumni` | Alumni |
| `CAMP-05` | `pihak_luar` | Pihak luar yang terkait lingkungan kampus |

### 14.2 Relasi Pelapor dengan Terlapor

| Kode | Relasi | Deskripsi |
|------|--------|-----------|
| `REL-01` | `dosen_mahasiswa` | Dosen — Mahasiswa |
| `REL-02` | `sesama_mahasiswa` | Sesama mahasiswa |
| `REL-03` | `atasan_bawahan` | Atasan — Bawahan (struktural) |
| `REL-04` | `sesama_pegawai` | Sesama pegawai/tendik |
| `REL-05` | `pembimbing` | Pembimbing akademik/skripsi |
| `REL-06` | `organisasi` | Dalam konteks organisasi kampus |
| `REL-07` | `tidak_dikenal` | Tidak mengenal secara personal |
| `REL-99` | `lainnya` | Relasi lain (harus dideskripsikan) |

### 14.3 Lokasi Kejadian

| Kode | Lokasi | Deskripsi |
|------|--------|-----------|
| `LOC-01` | `dalam_kampus` | Di dalam area kampus |
| `LOC-02` | `luar_kampus_terkait` | Di luar kampus, terkait kegiatan kampus |
| `LOC-03` | `luar_kampus_tidak_terkait` | Di luar kampus, tidak terkait kegiatan kampus |
| `LOC-04` | `online` | Melalui media digital / online |

### 14.4 Jenis Eskalasi

| Kode | Tipe | Target | Deskripsi |
|------|------|--------|-----------|
| `ESC-01` | `internal` | Pimpinan kampus | Terlapor adalah pejabat, atau kasus berdampak luas |
| `ESC-02` | `kepolisian` | Kepolisian | Kasus pidana yang memerlukan proses hukum |
| `ESC-03` | `lpsk` | LPSK | Perlindungan saksi dan korban |
| `ESC-04` | `rumah_sakit` | Rumah Sakit | Penanganan medis darurat |
| `ESC-05` | `psikolog` | Psikolog Profesional | Pendampingan psikologis |
| `ESC-06` | `bantuan_hukum` | Lembaga Bantuan Hukum | Pendampingan hukum |

### 14.5 Jenis Pendampingan (Recovery)

| Kode | Tipe | Deskripsi |
|------|------|-----------|
| `RCV-01` | `psychological` | Pendampingan psikologis |
| `RCV-02` | `legal` | Pendampingan hukum |
| `RCV-03` | `academic` | Pendampingan akademik |
| `RCV-04` | `medical` | Pendampingan medis |

### 14.6 Jenis Sanksi (Rekomendasi)

| Kode | Tipe | Deskripsi |
|------|------|-----------|
| `SANC-01` | `warning` | Peringatan tertulis |
| `SANC-02` | `suspension` | Skorsing/pemberhentian sementara |
| `SANC-03` | `demotion` | Penurunan jabatan (untuk pegawai) |
| `SANC-04` | `expulsion` | Pemberhentian/dikeluarkan |
| `SANC-05` | `restriction` | Pembatasan akses/kegiatan |
| `SANC-06` | `obligation` | Kewajiban tertentu (konseling, dll) |
| `SANC-07` | `other` | Sanksi lain sesuai peraturan PT |

---

## Changelog

| Versi | Tanggal | Perubahan |
|-------|---------|----------|
| 1.0.0 | 2026-06-09 | Versi awal |
| 1.0.1-patch | 2026-06-10 | Audit patch: pembatasan permission Admin (→ `cases.read.metadata`), pembatasan akses bukti Super Admin (→ `evidence.view.case` ❌), penambahan `system.break_glass_access`, penguatan privasi anonim (hashed IP, retention 7 hari) |

---

> **Catatan**: Dokumen ini adalah Tier 2 (GOVERNED). Perubahan memerlukan persetujuan Project Owner. Seluruh kode unik di dokumen ini menjadi referensi wajib untuk implementasi database, API, dan frontend.
