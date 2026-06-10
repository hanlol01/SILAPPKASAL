# PRD.md — Product Requirements Document

> **Sistem Informasi Laporan Pencegahan dan Penanganan Kekerasan Seksual (SILAPPKASAL)**
> Versi: 1.1.0 | Terakhir Diperbarui: 2026-06-09 | Status: BERLAKU

---

## Daftar Isi

1. [Product Overview](#1-product-overview)
2. [Business Goals](#2-business-goals)
3. [User Roles](#3-user-roles)
4. [User Journey](#4-user-journey)
5. [Feature List](#5-feature-list)
6. [Functional Requirements](#6-functional-requirements)
7. [Business Rules](#7-business-rules)
8. [Notification Requirements](#8-notification-requirements)
9. [Non-Functional Requirements](#9-non-functional-requirements)
10. [Future Roadmap](#10-future-roadmap)

---

## 1. Product Overview

### 1.1 Ringkasan Produk

**SILAPPKASAL** (Sistem Informasi Laporan Pencegahan dan Penanganan Kekerasan Seksual) adalah platform digital yang dirancang khusus untuk lingkungan perguruan tinggi, memfasilitasi pelaporan dan penanganan kasus kekerasan seksual secara **aman, terstruktur, dan transparan**.

Sistem ini memungkinkan seluruh sivitas akademika — mahasiswa, dosen, dan tenaga kependidikan — untuk melaporkan dugaan kekerasan seksual melalui kanal digital yang terjamin kerahasiaannya, sekaligus memberikan dukungan operasional bagi Satgas PPKS dalam mengelola siklus penuh penanganan kasus.

### 1.2 Peran Dokumen Ini

| Dokumen | Peran sebagai Source of Truth |
|---------|-------------------------------|
| **AGENTS.md** | Aturan, perilaku, coding standards, dan workflow agent |
| **PROJECT_MASTER.md** | Blueprint proyek, arsitektur, stack final, dan keputusan yang dibekukan |
| **PRD.md** (dokumen ini) | Kebutuhan produk, fitur, business rules, dan non-functional requirements |

### 1.3 Proposisi Nilai

| Untuk | Nilai yang Diberikan |
|-------|---------------------|
| **Pelapor (Korban/Saksi)** | Kanal pelaporan yang aman, mudah, dan rahasia — termasuk opsi anonim — dengan transparansi progres kasus melalui tracking real-time. |
| **Admin Sistem** | Dashboard terpusat untuk verifikasi, routing, dan monitoring laporan masuk, serta pelaporan statistik berkala. |
| **Satgas PPKS** | Sistem manajemen kasus komprehensif: asesmen risiko, investigasi, rekomendasi, pendampingan, dan monitoring — dilengkapi SLA tracking. |
| **Institusi (Perguruan Tinggi)** | Data berbasis bukti untuk pengambilan kebijakan pencegahan, serta compliance terhadap regulasi (Permendikbudristek No. 30/2021). |

### 1.4 Ruang Lingkup Produk (Phase 1)

- **Web Admin Dashboard** — Untuk Admin Sistem dan Super Admin.
- **Web Satgas Dashboard** — Untuk anggota Satgas PPKS.
- **Web Pelapor Tester** — Interface web untuk pengujian alur pelaporan (sebelum Flutter di Phase 2).
- **Backend REST API** — Source of truth untuk seluruh logika bisnis.

---

## 2. Business Goals

### 2.1 Tujuan Bisnis Utama

| # | Tujuan | KPI | Target |
|---|--------|-----|--------|
| BG-01 | Meningkatkan aksesibilitas pelaporan | Jumlah laporan yang masuk via digital | Adopsi >60% dari total laporan dalam 6 bulan |
| BG-02 | Mempercepat proses verifikasi | Waktu rata-rata verifikasi awal | ≤2 hari kerja |
| BG-03 | Memastikan kepatuhan SLA | % kasus yang selesai dalam SLA | ≥85% kasus sesuai SLA |
| BG-04 | Melindungi data dan privasi | Jumlah insiden kebocoran data | 0 (zero incident) |
| BG-05 | Mendukung keputusan berbasis data | Ketersediaan laporan statistik | Laporan bulanan tersedia otomatis |
| BG-06 | Meningkatkan kepercayaan pelapor | Rasio pelapor yang melacak kasusnya | >70% pelapor aktif tracking |

### 2.2 Success Metrics

| Metrik | Definisi | Cara Mengukur |
|--------|----------|---------------|
| **Time to First Response** | Waktu dari laporan masuk hingga admin memverifikasi | Rata-rata selisih `submitted_at` dan `verified_at` |
| **Case Resolution Time** | Waktu dari laporan masuk hingga kasus selesai | Rata-rata selisih `submitted_at` dan `closed_at` |
| **SLA Compliance Rate** | % tahapan yang selesai sesuai target SLA | Count tahapan on-time / total tahapan |
| **Reporter Satisfaction** | Tingkat kepuasan pelapor terhadap proses | Survey post-case (jika memungkinkan) |
| **System Uptime** | Ketersediaan sistem | Monitoring uptime (target: 99.5%) |
| **Adoption Rate** | % sivitas akademika yang mendaftar/menggunakan | Registered users / total sivitas |

---

## 3. User Roles

### 3.1 Pelapor

| Atribut | Detail |
|---------|--------|
| **Siapa** | Mahasiswa, dosen, tenaga kependidikan, atau pihak lain yang terkait lingkungan kampus |
| **Motivasi** | Melaporkan dugaan kekerasan seksual yang dialami/disaksikan secara aman |
| **Kebutuhan Utama** | Formulir pelaporan yang mudah, jaminan kerahasiaan, transparansi progres |
| **Platform** | Mobile (Phase 2), Web (tester di Phase 1) |
| **Level Teknis** | Rendah–Menengah |

### 3.2 Admin Sistem

| Atribut | Detail |
|---------|--------|
| **Siapa** | Staff yang ditunjuk untuk mengelola sistem dan memverifikasi laporan |
| **Motivasi** | Memastikan laporan terverifikasi dan tersalurkan ke Satgas tepat waktu |
| **Kebutuhan Utama** | Dashboard efisien, notifikasi real-time, tools verifikasi |
| **Platform** | Web |
| **Level Teknis** | Menengah |

### 3.3 Satgas PPKS

| Atribut | Detail |
|---------|--------|
| **Siapa** | Anggota Satuan Tugas PPKS yang ditunjuk institusi |
| **Motivasi** | Menangani kasus secara profesional, adil, dan tepat waktu |
| **Kebutuhan Utama** | Manajemen kasus komprehensif, tools asesmen & investigasi, tracking SLA |
| **Platform** | Web |
| **Level Teknis** | Menengah |

### 3.4 Super Admin

| Atribut | Detail |
|---------|--------|
| **Siapa** | Pengelola utama sistem (IT admin / penanggung jawab aplikasi) |
| **Motivasi** | Memastikan sistem berjalan optimal dan aman |
| **Kebutuhan Utama** | Konfigurasi sistem, manajemen akun admin/satgas, audit log |
| **Platform** | Web |
| **Level Teknis** | Tinggi |

---

## 4. User Journey

### 4.1 Journey: Pelapor Membuat Laporan Terbuka

```
TRIGGER: Pelapor mengalami/menyaksikan kekerasan seksual

1. DISCOVERY
   → Pelapor mendapatkan info tentang SILAPPKASAL (sosialisasi kampus, poster, website)
   → Pelapor mengunduh aplikasi / mengakses website

2. REGISTRATION & LOGIN
   → Pelapor mendaftar menggunakan email kampus / NIM
   → Verifikasi email
   → Login ke sistem

3. REPORT CREATION
   → Pelapor mengakses menu "Buat Laporan"
   → Memilih jenis laporan: "Terbuka"
   → Mengisi formulir:
     - Jenis kekerasan (dropdown berdasarkan kategori UU)
     - Kronologi kejadian (text area, min 50 karakter)
     - Waktu kejadian (date & time picker)
     - Lokasi kejadian (text input)
     - Pihak terlibat:
       - Data terlapor (nama, status kampus, relasi)
       - Data saksi (opsional)
     - Bukti pendukung (upload file: foto, video, dokumen, screenshot)
   → Review data sebelum submit
   → Submit laporan

4. CONFIRMATION
   → Sistem menampilkan nomor registrasi kasus (format: SLP-YYYY-MMDD-XXXX)
   → Pelapor menerima notifikasi WhatsApp: "Laporan Anda telah diterima"
   → Dashboard pelapor menampilkan status: "Submitted"

5. TRACKING
   → Pelapor membuka dashboard → melihat timeline kasus
   → Setiap perubahan status → notifikasi WhatsApp
   → Pelapor dapat mengirim pesan ke Satgas via fitur internal messaging

6. RESOLUTION
   → Pelapor menerima notifikasi ketika keputusan dikeluarkan
   → Pelapor dapat melihat rangkuman penanganan
   → Pelapor mendapat akses ke layanan pendampingan (jika diperlukan)
```

### 4.2 Journey: Pelapor Anonim

```
TRIGGER: Pelapor ingin melaporkan tanpa mengungkap identitas

1. ACCESS
   → Pelapor mengakses sistem tanpa login
   → Memilih "Laporan Anonim"

2. REPORT CREATION
   → Mengisi formulir (sama seperti terbuka, TANPA data diri pelapor)
   → Upload bukti pendukung (sangat dianjurkan)
   → Submit laporan

3. CONFIRMATION
   → Sistem generate kode pelacakan unik (tracking code)
   → Kode ditampilkan di layar — pelapor HARUS menyimpan kode ini
   → TIDAK ADA notifikasi WhatsApp (karena anonim)

4. TRACKING
   → Pelapor memasukkan kode pelacakan di halaman tracking
   → Melihat status terkini kasus
   → Dapat mengirim/menerima pesan anonim dari Satgas

5. LIMITATIONS
   → Waktu verifikasi lebih lama
   → Satgas mungkin perlu informasi tambahan (via pesan anonim)
   → Jika info tidak cukup, kasus mungkin tidak bisa dilanjutkan
```

### 4.3 Journey: Admin Memverifikasi Laporan

```
TRIGGER: Laporan baru masuk ke sistem

1. NOTIFICATION
   → Admin menerima notifikasi (in-app + WhatsApp)
   → Admin login ke dashboard

2. REVIEW
   → Admin membuka daftar laporan masuk (sorted by urgency & timestamp)
   → Membuka detail laporan
   → Memeriksa kelengkapan:
     ☐ Kronologi cukup detail?
     ☐ Waktu dan lokasi jelas?
     ☐ Pihak terlibat teridentifikasi?
     ☐ Bukti pendukung ada?

3. DECISION
   → Jika lengkap & valid:
     - Admin klasifikasi urgensi (rendah/sedang/tinggi)
     - Admin forward ke Satgas
     - Status berubah: "forwarded"
     - Notifikasi ke Satgas & Pelapor
   
   → Jika butuh info tambahan:
     - Admin membuat catatan "info yang dibutuhkan"
     - Status berubah: "need_info"
     - Notifikasi ke Pelapor
   
   → Jika tidak valid (bukan kekerasan seksual, spam, dll):
     - Admin menolak dengan alasan tertulis
     - Status berubah: "rejected"
     - Notifikasi ke Pelapor
```

### 4.4 Journey: Satgas Menangani Kasus

```
TRIGGER: Kasus diteruskan ke Satgas oleh Admin

1. RECEIVE & REVIEW
   → Satgas menerima notifikasi kasus baru
   → Login ke dashboard Satgas
   → Membaca detail kasus dan bukti

2. RISK ASSESSMENT (Maks 5 hari kerja)
   → Satgas mengisi form asesmen risiko:
     - Level risiko: Rendah / Sedang / Tinggi
     - Justifikasi level risiko
     - Langkah perlindungan yang direkomendasikan
     - Apakah membutuhkan perlindungan darurat? (Jika ya: maks 1x24 jam)
   → Status berubah: "assessment"
   → Notifikasi ke Pelapor

3. INVESTIGATION (14-30 hari kerja)
   → Satgas membuat rencana investigasi
   → Mencatat kegiatan investigasi di sistem:
     - Wawancara korban (catatan, tanggal)
     - Wawancara saksi (catatan, tanggal)
     - Wawancara terlapor (catatan, tanggal)
     - Analisis bukti (temuan)
   → Upload dokumen investigasi
   → Status berubah: "investigation"
   → Update progres ke Pelapor secara berkala

4. MEDIATION (Opsional)
   → Jika memenuhi syarat mediasi:
     - Bukan kekerasan berat
     - Korban menyetujui secara sukarela
     - Tidak ada ancaman keselamatan
   → Satgas mencatat proses mediasi:
     - Tanggal mediasi
     - Pihak yang hadir
     - Hasil mediasi
     - Berita acara
   → Status: "mediation"

5. RECOMMENDATION (Maks 7 hari kerja)
   → Satgas menyusun rekomendasi:
     - Kesimpulan investigasi
     - Rekomendasi sanksi (jika terbukti)
     - Rekomendasi pemulihan korban
     - Rekomendasi tindakan pencegahan
   → Upload dokumen rekomendasi
   → Status berubah: "recommendation"

6. INSTITUTIONAL DECISION (Maks 14 hari kerja)
   → Rekomendasi diajukan ke pimpinan PT (di luar sistem)
   → Satgas mencatat keputusan di sistem:
     - Nomor SK
     - Isi keputusan
     - Tanggal keputusan
   → Status berubah: "decided"
   → Notifikasi ke Pelapor

7. RECOVERY & MONITORING (3-6 bulan)
   → Satgas mencatat kegiatan pendampingan:
     - Pendampingan psikologis
     - Pendampingan hukum
     - Pendampingan akademik
   → Monitoring berkala:
     - Checklist kondisi korban
     - Catatan perkembangan
   → Status: "monitoring" → "closed"
```

---

## 5. Feature List

### 5.1 MVP Core — Fitur Minimum Operasional

> Tanpa fitur-fitur ini, sistem tidak bisa beroperasi sama sekali.

#### Modul Autentikasi & Pengguna

| ID | Fitur | Deskripsi | Klasifikasi |
|----|-------|-----------|-------------|
| F-AUTH-01 | Login | Login dengan email/NIM/NIP + password | MVP Core |
| F-AUTH-02 | Logout | Logout dan revoke token | MVP Core |
| F-AUTH-03 | Register (Pelapor) | Registrasi akun pelapor | MVP Core |
| F-USER-01 | User CRUD | Admin membuat, melihat, mengedit, menonaktifkan user | MVP Core |
| F-USER-02 | Role Assignment | Admin menetapkan role ke user | MVP Core |

#### Modul Pelaporan

| ID | Fitur | Deskripsi | Klasifikasi |
|----|-------|-----------|-------------|
| F-RPT-01 | Buat Laporan Terbuka | Pelapor membuat laporan dengan identitas diketahui | MVP Core |
| F-RPT-02 | Buat Laporan Rahasia | Pelapor membuat laporan dengan identitas dilindungi | MVP Core |
| F-RPT-03 | Buat Laporan Anonim | Pelapor membuat laporan tanpa login, dapat kode tracking | MVP Core |
| F-RPT-04 | Upload Bukti | Upload file bukti (gambar, video, dokumen) ke S3 | MVP Core |
| F-RPT-05 | Tracking Kasus | Pelapor melihat status kasus via dashboard/kode tracking | MVP Core |
| F-RPT-06 | Nomor Registrasi | Sistem generate nomor unik per laporan | MVP Core |

#### Modul Manajemen Kasus

| ID | Fitur | Deskripsi | Klasifikasi |
|----|-------|-----------|-------------|
| F-CASE-01 | Daftar Laporan Masuk | Admin melihat semua laporan yang masuk | MVP Core |
| F-CASE-02 | Verifikasi Laporan | Admin verifikasi kelengkapan dan validitas | MVP Core |
| F-CASE-03 | Minta Info Tambahan | Admin meminta pelapor melengkapi informasi | MVP Core |
| F-CASE-04 | Tolak Laporan | Admin menolak laporan dengan alasan | MVP Core |
| F-CASE-05 | Forward ke Satgas | Admin meneruskan laporan terverifikasi ke Satgas | MVP Core |
| F-CASE-06 | Asesmen Risiko | Satgas mengisi form asesmen risiko | MVP Core |
| F-CASE-07 | Manajemen Investigasi | Satgas mencatat aktivitas investigasi | MVP Core |

#### Modul Audit & Keamanan

| ID | Fitur | Deskripsi | Klasifikasi |
|----|-------|-----------|-------------|
| F-AUD-01 | Audit Log | Log seluruh aktivitas CRUD pada data kasus | MVP Core |
| F-AUD-03 | Data Encryption | Field-level encryption data sensitif di database | MVP Core |
| F-AUD-04 | Session Management | Manajemen session dan token revocation | MVP Core |

### 5.2 MVP Extended — Fitur Operasional Penuh

> Sistem bisa beroperasi tanpa ini, tapi belum ideal untuk penggunaan sehari-hari.

#### Modul Autentikasi & Pengguna

| ID | Fitur | Deskripsi | Klasifikasi |
|----|-------|-----------|-------------|
| F-AUTH-04 | Reset Password | Kirim link reset password via email | MVP Extended |
| F-AUTH-05 | Profile Management | Lihat dan edit profil pengguna | MVP Extended |
| F-USER-03 | User Search & Filter | Pencarian dan filter daftar user | MVP Extended |

#### Modul Pelaporan

| ID | Fitur | Deskripsi | Klasifikasi |
|----|-------|-----------|-------------|
| F-RPT-07 | Formulir Multi-step | Formulir laporan terbagi dalam beberapa langkah | MVP Extended |

#### Modul Manajemen Kasus

| ID | Fitur | Deskripsi | Klasifikasi |
|----|-------|-----------|-------------|
| F-CASE-08 | Mediasi (Opsional) | Satgas mencatat proses mediasi | MVP Extended |
| F-CASE-09 | Rekomendasi | Satgas menyusun dan mengajukan rekomendasi | MVP Extended |
| F-CASE-10 | Keputusan Institusi | Satgas mencatat keputusan pimpinan | MVP Extended |
| F-CASE-11 | Pemulihan & Monitoring | Satgas mencatat pendampingan dan monitoring | MVP Extended |
| F-CASE-12 | Timeline Kasus | Visualisasi tahapan kasus secara kronologis | MVP Extended |
| F-CASE-13 | Assign Satgas ke Kasus | Admin/Koordinator Satgas menugaskan anggota | MVP Extended |
| F-CASE-14 | Filter & Search Kasus | Pencarian dan filter berdasarkan status, tanggal, risiko | MVP Extended |

#### Modul Dashboard & Statistik

| ID | Fitur | Deskripsi | Klasifikasi |
|----|-------|-----------|-------------|
| F-DASH-01 | Dashboard Admin | Ringkasan laporan masuk, pending, selesai | MVP Extended |
| F-DASH-02 | Dashboard Satgas | Ringkasan kasus aktif, SLA, tugas | MVP Extended |
| F-DASH-03 | Statistik Laporan | Jumlah laporan per jenis, per periode | MVP Extended |
| F-DASH-04 | Statistik Kasus | Jumlah kasus per status, per level risiko | MVP Extended |

#### Modul Komunikasi

| ID | Fitur | Deskripsi | Klasifikasi |
|----|-------|-----------|-------------|
| F-COM-01 | Pesan Internal | Pesan antara pelapor dan Satgas dalam konteks kasus | MVP Extended |
| F-COM-02 | Pesan Anonim | Pesan tanpa identitas untuk pelapor anonim | MVP Extended |

#### Modul Notifikasi

| ID | Fitur | Deskripsi | Klasifikasi |
|----|-------|-----------|-------------|
| F-NOTIF-01 | Notifikasi WhatsApp | Kirim notifikasi via Fonnte saat status berubah | MVP Extended |

### 5.3 Post-MVP — Fitur Tambahan

> Dijadwalkan setelah MVP stabil. Meningkatkan pengalaman dan efisiensi operasional.

#### Modul Pelaporan

| ID | Fitur | Deskripsi | Klasifikasi |
|----|-------|-----------|-------------|
| F-RPT-08 | Draft Laporan | Simpan draft laporan sebelum submit | Post-MVP |

#### Modul Dashboard & Statistik

| ID | Fitur | Deskripsi | Klasifikasi |
|----|-------|-----------|-------------|
| F-DASH-05 | SLA Monitoring | Indikator kasus yang mendekati/melewati SLA | Post-MVP |
| F-DASH-06 | Export Laporan | Export statistik ke PDF/CSV | Post-MVP |

#### Modul Komunikasi

| ID | Fitur | Deskripsi | Klasifikasi |
|----|-------|-----------|-------------|
| F-COM-03 | Catatan Internal Satgas | Catatan yang hanya bisa dilihat sesama Satgas | Post-MVP |

#### Modul Notifikasi

| ID | Fitur | Deskripsi | Klasifikasi |
|----|-------|-----------|-------------|
| F-NOTIF-02 | In-app Notification | Notifikasi di dalam aplikasi web | Post-MVP |
| F-NOTIF-03 | Notifikasi SLA Warning | Alert ketika kasus mendekati batas SLA | Post-MVP |

#### Modul Audit & Keamanan

| ID | Fitur | Deskripsi | Klasifikasi |
|----|-------|-----------|-------------|
| F-AUD-02 | Audit Log Viewer | Super Admin melihat dan filter audit log | Post-MVP |

### 5.4 Fitur Phase 2 (Mobile)

| ID | Fitur | Deskripsi | Klasifikasi |
|----|-------|-----------|-------------|
| F-MOB-01 | Login Mobile | Login di aplikasi Flutter | MVP Core (Phase 2) |
| F-MOB-02 | Report Submission | Buat laporan dari mobile | MVP Core (Phase 2) |
| F-MOB-03 | Case Tracking | Tracking kasus di mobile | MVP Core (Phase 2) |
| F-MOB-04 | Push Notification | Push notification untuk status update | MVP Extended (Phase 2) |
| F-MOB-05 | Offline Draft | Simpan draft laporan secara offline | MVP Extended (Phase 2) |
| F-MOB-06 | Camera Capture | Ambil foto bukti langsung dari kamera | MVP Extended (Phase 2) |
| F-MOB-07 | Biometric Auth | Login dengan fingerprint/face ID | Post-MVP (Phase 2) |

---

## 6. Functional Requirements

### 6.1 Autentikasi

| ID | Requirement | Detail |
|----|-------------|--------|
| FR-AUTH-01 | Sistem harus mendukung login dengan email/NIM/NIP dan password. | Input: identifier (email/NIM/NIP) + password. Output: Bearer token + user data + role. |
| FR-AUTH-02 | Sistem harus mendukung registrasi pelapor baru. | Input: nama lengkap, email, NIM/NIP, nomor WhatsApp, password. Validasi: email unik, NIM/NIP unik, password min 8 karakter (huruf + angka). |
| FR-AUTH-03 | Sistem harus mendukung token-based authentication. | Menggunakan Laravel Sanctum. Token berlaku 24 jam. Mendukung multiple devices. |
| FR-AUTH-04 | Sistem harus mendukung logout. | Revoke token saat ini. Opsi: revoke semua token (logout dari semua perangkat). |
| FR-AUTH-05 | Sistem harus mendukung reset password. | Kirim link reset ke email. Link berlaku 60 menit. One-time use. |

### 6.2 Pelaporan

| ID | Requirement | Detail |
|----|-------------|--------|
| FR-RPT-01 | Sistem harus menerima 3 jenis laporan: terbuka, rahasia, anonim. | Terbuka & rahasia membutuhkan login. Anonim tidak membutuhkan login. |
| FR-RPT-02 | Formulir laporan wajib mengandung field berikut. | Wajib: jenis kekerasan, kronologi (min 50 karakter), waktu kejadian, lokasi kejadian. Opsional: data terlapor, data saksi, bukti pendukung. |
| FR-RPT-03 | Sistem harus generate nomor registrasi unik. | Format: `SLP-YYYY-MMDD-XXXX` (contoh: SLP-2026-0609-0001). Auto-increment per hari. |
| FR-RPT-04 | Sistem harus generate kode pelacakan untuk laporan anonim. | Format: 16 karakter alfanumerik random (contoh: `A7X9-K2M4-P8Q3-R1W5`). Case-insensitive saat input. |
| FR-RPT-05 | Sistem harus mendukung upload file bukti. | Tipe yang diizinkan: JPG, JPEG, PNG, GIF, MP4, MOV, PDF, DOC, DOCX. Maks ukuran per file: 25 MB. Maks jumlah file per laporan: 10. |
| FR-RPT-06 | File bukti harus disimpan di S3-compatible storage. | File dienkripsi (encryption at rest) sebelum upload. Nama file di-randomize (UUID). Metadata asli disimpan di database. |
| FR-RPT-07 | Pelapor harus bisa melihat status kasusnya. | Via dashboard (jika login) atau kode pelacakan (jika anonim). Menampilkan: status saat ini, timeline perubahan status, pesan dari Satgas. |

### 6.3 Manajemen Kasus

| ID | Requirement | Detail |
|----|-------------|--------|
| FR-CASE-01 | Admin harus bisa melihat daftar laporan masuk. | Sorted by: tanggal (terbaru), urgensi. Filter by: status, jenis laporan, tanggal. Pagination: 20 per halaman. |
| FR-CASE-02 | Admin harus bisa memverifikasi laporan. | Aksi: terima (forward ke Satgas), minta info tambahan, tolak. Wajib: catatan verifikasi untuk setiap aksi. |
| FR-CASE-03 | Status kasus harus berubah sesuai workflow. | Transisi yang valid (lihat Bagian 7.3). Tidak boleh ada skip tahapan. Setiap perubahan status harus trigger notifikasi. |
| FR-CASE-04 | Satgas harus bisa mengisi form asesmen risiko. | Field: level risiko (enum: low/medium/high), justifikasi (text, min 20 karakter), langkah perlindungan yang direkomendasikan (text), kebutuhan perlindungan darurat (boolean). |
| FR-CASE-05 | Satgas harus bisa mencatat aktivitas investigasi. | Tipe aktivitas: wawancara korban, wawancara saksi, wawancara terlapor, analisis bukti, pengumpulan dokumen. Setiap entri: tanggal, deskripsi, temuan, lampiran. |
| FR-CASE-06 | Satgas harus bisa menyusun rekomendasi. | Field: kesimpulan investigasi, rekomendasi tindakan, rekomendasi sanksi, rekomendasi pemulihan korban. Lampiran: dokumen rekomendasi (upload). |
| FR-CASE-07 | Satgas harus bisa mencatat keputusan institusi. | Field: nomor SK, tanggal keputusan, isi keputusan (text), lampiran SK (upload). |
| FR-CASE-08 | Satgas harus bisa mencatat kegiatan pemulihan & monitoring. | Tipe: pendampingan psikologis, hukum, akademik. Setiap entri: tanggal, deskripsi, status (ongoing/completed). Monitoring: catatan berkala kondisi korban. |

### 6.4 Dashboard & Statistik

| ID | Requirement | Detail |
|----|-------------|--------|
| FR-DASH-01 | Dashboard Admin harus menampilkan ringkasan real-time. | Widget: total laporan (today/week/month), laporan pending verifikasi, laporan per status, SLA compliance rate. |
| FR-DASH-02 | Dashboard Satgas harus menampilkan overview kasus. | Widget: kasus yang ditugaskan, kasus per status, kasus mendekati SLA, kasus per level risiko. |
| FR-DASH-03 | Sistem harus menyediakan statistik agregat. | Per periode (minggu/bulan/tahun): jumlah laporan per jenis, jumlah kasus per status, jumlah kasus per level risiko, rata-rata waktu penyelesaian per tahap. |

### 6.5 Komunikasi Internal

| ID | Requirement | Detail |
|----|-------------|--------|
| FR-COM-01 | Pelapor dan Satgas harus bisa bertukar pesan dalam konteks kasus. | Thread per kasus. Hanya pelapor (pemilik kasus) dan Satgas (yang ditugaskan) yang bisa membaca. Mendukung lampiran. |
| FR-COM-02 | Pelapor anonim harus bisa berkomunikasi tanpa mengungkap identitas. | Akses via kode pelacakan. Sistem tidak menyimpan IP atau data identifikasi. |

---

## 7. Business Rules

### 7.1 Aturan Pelaporan

| ID | Rule | Detail |
|----|------|--------|
| BR-RPT-01 | Setiap laporan mendapat nomor registrasi unik. | Generated otomatis oleh sistem. Tidak bisa diubah. |
| BR-RPT-02 | Laporan yang sudah di-submit tidak bisa diedit oleh pelapor. | Pelapor hanya bisa menambah informasi via pesan internal. |
| BR-RPT-03 | Admin tidak boleh mengubah isi laporan. | Admin hanya boleh: verifikasi, minta info, tolak, forward. |
| BR-RPT-04 | Laporan anonim tetap diproses jika informasi memadai. | Satgas menentukan apakah informasi cukup untuk ditindaklanjuti. |
| BR-RPT-05 | Pelapor anonim yang kehilangan kode pelacakan tidak bisa mendapatkan kode baru. | Ini by-design untuk menjaga anonimitas. Sistem tidak menyimpan data yang bisa mengaitkan kode ke individu. |
| BR-RPT-06 | Satu akun pelapor bisa memiliki lebih dari satu laporan aktif. | Tidak ada batasan jumlah laporan per pelapor. |

### 7.2 Aturan Penanganan Kasus

| ID | Rule | Detail |
|----|------|--------|
| BR-CASE-01 | Kasus hanya bisa berprogress secara sequential. | Tidak bisa skip tahapan (misal: dari verifikasi langsung ke rekomendasi). |
| BR-CASE-02 | Perlindungan darurat harus diproses dalam 1×24 jam. | Jika asesmen menunjukkan risiko tinggi dan kebutuhan darurat. |
| BR-CASE-03 | Investigasi harus berpusat pada korban (victim-centered). | Prinsip: meminimalkan pengulangan cerita, menjaga kerahasiaan, non-diskriminatif. |
| BR-CASE-04 | Mediasi hanya jika memenuhi SEMUA syarat. | 1) Korban menyetujui secara sukarela, 2) Bukan kekerasan seksual berat, 3) Tidak ada ancaman keselamatan, 4) Sesuai peraturan PT. |
| BR-CASE-05 | Keputusan akhir berada di pimpinan PT. | Satgas hanya memberikan rekomendasi. Sistem mencatat keputusan tetapi bukan pembuat keputusan. |
| BR-CASE-06 | Monitoring pasca-kasus wajib dilakukan 3-6 bulan. | Satgas harus melakukan checklist berkala dan mencatatnya di sistem. |
| BR-CASE-07 | Kasus hanya bisa ditutup oleh Satgas. | Setelah monitoring selesai dan semua checklist terpenuhi. |

### 7.3 Status Transition Rules

```
submitted    → [under_review, rejected]
under_review → [need_info, forwarded, rejected]
need_info    → [under_review]                    (pelapor melengkapi info)
forwarded    → [assessment]
assessment   → [investigation]
investigation → [mediation, recommendation]
mediation    → [recommendation]
recommendation → [decision]
decision     → [decided]
decided      → [recovery]
recovery     → [monitoring]
monitoring   → [closed]

Special:
ANY_ACTIVE   → [escalated]                       (eskalasi bisa dari tahap mana pun)
rejected     → TERMINAL (tidak bisa dilanjutkan)
closed       → TERMINAL (tidak bisa dibuka kembali via sistem)
```

### 7.4 Aturan Kerahasiaan

| ID | Rule | Detail |
|----|------|--------|
| BR-SEC-01 | Identitas pelapor anonim TIDAK BOLEH terungkap. | Sistem tidak menyimpan data yang bisa mengaitkan kode tracking ke individu. Tidak ada logging IP/device untuk pelapor anonim. |
| BR-SEC-02 | Identitas korban hanya bisa diakses oleh Satgas yang ditugaskan. | Admin hanya melihat nomor registrasi dan metadata (tanpa identitas korban). |
| BR-SEC-03 | Data kasus tidak boleh diakses oleh pihak yang tidak berwenang. | RBAC ketat. Setiap akses tercatat di audit log. |
| BR-SEC-04 | Data kasus tidak boleh dihapus dari sistem. | Soft delete jika diperlukan. Audit trail tetap tersimpan. |
| BR-SEC-05 | Identitas terlapor dilindungi sampai ada keputusan. | Prinsip praduga tak bersalah. Data hanya bisa diakses oleh Satgas yang menangani. |

### 7.5 Aturan SLA

| ID | Rule | Detail |
|----|------|--------|
| BR-SLA-01 | Sistem harus mencatat timestamp setiap perubahan status. | Untuk kalkulasi durasi per tahap. |
| BR-SLA-02 | Jika SLA hampir terlampaui, sistem mengirim warning. | 75% dari target SLA → notifikasi warning ke penanggung jawab. |
| BR-SLA-03 | Jika SLA terlampaui, sistem mengirim alert. | Notifikasi ke penanggung jawab + Super Admin. |
| BR-SLA-04 | Perhitungan SLA menggunakan hari kerja. | Sabtu, Minggu, dan hari libur nasional tidak dihitung. |

---

## 8. Notification Requirements

### 8.1 Kanal Notifikasi

| Kanal | Provider | Target Pengguna | Phase |
|-------|----------|-----------------|-------|
| WhatsApp | Fonnte API | Pelapor (non-anonim), Admin, Satgas | Phase 1 |
| In-App | Built-in | Admin, Satgas | Phase 1 (Post-MVP) |
| Push Notification | FCM/APNs | Pelapor (mobile) | Phase 2 |

### 8.2 Trigger Notifikasi

| ID | Event | Penerima | Kanal | Klasifikasi |
|----|-------|----------|-------|-------------|
| N-01 | Laporan baru masuk | Admin | WhatsApp, In-App | MVP Extended |
| N-02 | Laporan diterima (konfirmasi ke pelapor) | Pelapor | WhatsApp | MVP Extended |
| N-03 | Status kasus berubah | Pelapor | WhatsApp | MVP Extended |
| N-04 | Info tambahan dibutuhkan | Pelapor | WhatsApp | MVP Extended |
| N-05 | Laporan ditolak | Pelapor | WhatsApp | MVP Extended |
| N-06 | Kasus di-forward ke Satgas | Satgas (ditugaskan) | WhatsApp, In-App | MVP Extended |
| N-07 | Pesan baru di internal messaging | Pelapor / Satgas | WhatsApp, In-App | MVP Extended |
| N-08 | SLA warning (75% threshold) | Admin / Satgas | WhatsApp, In-App | Post-MVP |
| N-09 | SLA breach (terlampaui) | Admin + Super Admin | WhatsApp, In-App | Post-MVP |
| N-10 | Keputusan institusi dikeluarkan | Pelapor | WhatsApp | MVP Extended |
| N-11 | Kasus ditutup | Pelapor | WhatsApp | MVP Extended |

### 8.3 Template Notifikasi WhatsApp

```
# N-01: Laporan Baru Masuk (ke Admin)
---
📋 *SILAPPKASAL — Laporan Baru*

Nomor Registrasi: {registration_number}
Jenis Laporan: {report_type}
Tanggal Masuk: {submitted_at}
Urgensi: {urgency_level}

Silakan lakukan verifikasi segera.
Dashboard: {admin_dashboard_url}
---

# N-02: Konfirmasi Laporan (ke Pelapor)
---
✅ *SILAPPKASAL — Laporan Diterima*

Laporan Anda telah berhasil dikirim.

Nomor Registrasi: {registration_number}
Status: Menunggu Verifikasi

Anda akan menerima notifikasi untuk setiap perkembangan.
Pantau status kasus Anda: {tracking_url}
---

# N-03: Perubahan Status (ke Pelapor)
---
🔄 *SILAPPKASAL — Update Status Kasus*

Nomor Registrasi: {registration_number}
Status Sebelumnya: {previous_status}
Status Saat Ini: {current_status}

{status_description}

Pantau detail kasus Anda: {tracking_url}
---

# N-06: Kasus Diteruskan (ke Satgas)
---
📌 *SILAPPKASAL — Kasus Baru Ditugaskan*

Nomor Registrasi: {registration_number}
Level Urgensi: {urgency_level}
Jenis Laporan: {report_type}
Tanggal Laporan: {submitted_at}

Segera lakukan asesmen risiko.
Target SLA Asesmen: {sla_deadline}
Dashboard: {satgas_dashboard_url}
---

# N-08: SLA Warning
---
⚠️ *SILAPPKASAL — Peringatan SLA*

Kasus {registration_number} mendekati batas SLA.

Tahap Saat Ini: {current_stage}
Deadline SLA: {sla_deadline}
Sisa Waktu: {remaining_time}

Segera tindak lanjuti.
---
```

### 8.4 Aturan Notifikasi

| # | Aturan | Detail |
|---|--------|--------|
| 1 | Pelapor anonim TIDAK menerima WhatsApp. | Karena sistem tidak menyimpan nomor telepon pelapor anonim. |
| 2 | Notifikasi gagal kirim harus di-retry. | Maksimal 3 kali retry dengan exponential backoff (1 menit, 5 menit, 15 menit). |
| 3 | Notifikasi gagal setelah retry harus di-log. | Masuk ke tabel `notification_logs` dengan status `failed`. |
| 4 | Isi notifikasi TIDAK BOLEH mengandung data sensitif. | Tidak boleh menyertakan: nama korban, detail kronologi, identitas terlapor. |
| 5 | Template notifikasi harus bisa dikonfigurasi. | Admin bisa mengubah template via konfigurasi (bukan hardcoded). |

---

## 9. Non-Functional Requirements

### 9.1 Performance

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-PERF-01 | API response time (p95) | ≤ 500ms untuk operasi standar |
| NFR-PERF-02 | API response time untuk query kompleks (p95) | ≤ 2 detik |
| NFR-PERF-03 | File upload | ≤ 10 detik untuk file 25 MB |
| NFR-PERF-04 | Halaman web load time | ≤ 3 detik (First Contentful Paint) |
| NFR-PERF-05 | Concurrent users | Mendukung minimum 300 concurrent users |
| NFR-PERF-06 | Database query | Tidak ada query yang memakan waktu >1 detik |

### 9.2 Reliability & Availability

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-REL-01 | System uptime | ≥ 99.5% (maksimal ~44 jam downtime per tahun) |
| NFR-REL-02 | Data durability | Tidak boleh ada kehilangan data laporan/kasus |
| NFR-REL-03 | Backup recovery | RTO (Recovery Time Objective): ≤ 4 jam |
| NFR-REL-04 | Backup | RPO (Recovery Point Objective): ≤ 6 jam |
| NFR-REL-05 | Graceful degradation | Jika notifikasi gagal, proses utama tetap berjalan |

### 9.3 Security

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-SEC-01 | Encryption in transit | HTTPS (TLS 1.3) wajib untuk semua komunikasi |
| NFR-SEC-02 | Encryption at rest | AES-256-GCM untuk file bukti di S3 |
| NFR-SEC-03 | Field-level encryption | AES-256 untuk kolom sensitif (kronologi, identitas korban, dsb.) |
| NFR-SEC-04 | Password policy | Min 8 karakter, kombinasi huruf + angka |
| NFR-SEC-05 | Rate limiting | Login: 5/menit, API: 60/menit, Upload: 10/jam |
| NFR-SEC-06 | Session management | Token expiry 24 jam, revocation support |
| NFR-SEC-07 | Audit trail | Setiap operasi pada data kasus tercatat |
| NFR-SEC-08 | Input sanitization | Semua input divalidasi dan disanitasi |
| NFR-SEC-09 | File validation | MIME type, ukuran, ekstensi divalidasi server-side |
| NFR-SEC-10 | Compliance | UU PDP, UU TPKS, Permendikbudristek No. 30/2021 |

### 9.4 Usability

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-USE-01 | Aksesibilitas | WCAG 2.1 Level AA |
| NFR-USE-02 | Browser support | Chrome, Firefox, Safari, Edge (2 versi terakhir) |
| NFR-USE-03 | Responsive design | Desktop (≥1024px), Tablet (≥768px) |
| NFR-USE-04 | Bahasa antarmuka | Bahasa Indonesia |
| NFR-USE-05 | Error messages | Jelas, actionable, dalam Bahasa Indonesia |
| NFR-USE-06 | Formulir | Validasi real-time, progress indicator, auto-save draft |

### 9.5 Scalability

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-SCL-01 | User growth | Arsitektur mendukung hingga 10.000 pengguna |
| NFR-SCL-02 | Data growth | Mendukung hingga 100.000 laporan/kasus |
| NFR-SCL-03 | Storage | Scalable storage via S3-compatible |
| NFR-SCL-04 | API versioning | Mendukung versioning untuk backward compatibility |

### 9.6 Maintainability

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-MNT-01 | Code coverage | ≥ 80% untuk backend business logic |
| NFR-MNT-02 | Documentation | Setiap API endpoint terdokumentasi di `API_SPECIFICATION.md` |
| NFR-MNT-03 | Coding standards | Mengikuti standar di `AGENTS.md` |
| NFR-MNT-04 | Database migrations | Semua schema change via migration |
| NFR-MNT-05 | Environment config | Semua config via environment variables |

---

## 10. Future Roadmap

### 10.1 Phase 3: Enhancement (Post-MVP)

| # | Fitur | Deskripsi | Justifikasi |
|---|-------|-----------|-------------|
| 1 | Advanced Analytics | Dashboard analitik lanjutan dengan trend analysis, pattern recognition | Data-driven policy making |
| 2 | Multi-language Support | Antarmuka dalam bahasa Inggris dan bahasa daerah | Inklusivitas |
| 3 | Bulk Export/Import | Export kasus ke PDF/Excel, import data massal | Operasional Satgas |
| 4 | Advanced Search | Full-text search di dalam kasus (dengan access control) | Efisiensi Satgas |
| 5 | Email Notification | Kanal notifikasi tambahan via email | Redundansi notifikasi |
| 6 | Two-Factor Authentication | 2FA untuk admin dan satgas | Security hardening |

### 10.2 Phase 4: Maturity (Long-term)

| # | Fitur | Deskripsi | Justifikasi |
|---|-------|-----------|-------------|
| 1 | AI-Assisted Risk Assessment | Model ML untuk membantu asesmen risiko | Konsistensi dan kecepatan |
| 2 | Video Consultation | Konsultasi video (encrypted in transit) antara Satgas dan Pelapor | Pendampingan remote |
| 3 | SIA Integration | Integrasi ke Sistem Informasi Akademik | Verifikasi identitas otomatis |
| 4 | Multi-tenant | Mendukung beberapa kampus dalam satu platform | Skalabilitas institusi |
| 5 | Knowledge Base | Database pengetahuan untuk Satgas | Konsistensi penanganan |
| 6 | Whistleblower Protection | Fitur perlindungan pelapor yang lebih canggih | Keamanan pelapor |

### 10.3 Kriteria Evaluasi untuk Feature Baru

Setiap fitur baru harus dievaluasi berdasarkan:

| Kriteria | Bobot | Pertanyaan |
|----------|-------|------------|
| **Impact on Victim Safety** | 30% | Apakah fitur ini meningkatkan keselamatan korban? |
| **Regulatory Compliance** | 25% | Apakah fitur ini mendukung kepatuhan regulasi? |
| **Operational Efficiency** | 20% | Apakah fitur ini meningkatkan efisiensi Satgas? |
| **Technical Feasibility** | 15% | Seberapa kompleks implementasinya? |
| **User Request** | 10% | Apakah pengguna meminta fitur ini? |

---

## Lampiran: Kategori Kekerasan Seksual

Berdasarkan Permendikbudristek No. 30 Tahun 2021 dan UU TPKS:

| Kategori | Contoh |
|----------|--------|
| Pelecehan seksual verbal | Komentar seksual, lelucon seksual, siulan |
| Pelecehan seksual non-verbal | Gesture seksual, tatapan seksual, eksibisionisme |
| Pelecehan seksual fisik | Sentuhan tidak diinginkan, meraba, mencium paksa |
| Pemaksaan hubungan seksual | Pemerkosaan, percobaan pemerkosaan |
| Kekerasan seksual berbasis digital | Penyebaran konten intim tanpa izin (revenge porn), sexting paksa, cyberstalking |
| Pemaksaan kontrasepsi | Memaksa penggunaan/menolak kontrasepsi |
| Pemaksaan sterilisasi | Memaksa prosedur sterilisasi |
| Pemaksaan perkawinan | Memaksa menikah karena tekanan pihak tertentu |
| Penyiksaan seksual | Penyiksaan yang melibatkan organ seksual |
| Eksploitasi seksual | Memanfaatkan posisi/kekuasaan untuk kepentingan seksual |
| Perbudakan seksual | Memaksa seseorang melakukan aktivitas seksual secara berulang |

---

> **Catatan**: Dokumen ini adalah **living document** yang dikelola oleh Project Owner. Versi terbaru selalu menjadi acuan. Agent TIDAK BOLEH memodifikasi dokumen ini.
