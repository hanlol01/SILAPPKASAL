# Canvas Prompt — Mobile Reporter Public Access

Gunakan [MOBILE_REPORTER_PUBLIC_ACCESS_PRD.md](MOBILE_REPORTER_PUBLIC_ACCESS_PRD.md) sebagai spesifikasi. Tempel salah satu prompt di bawah ke Google Stitch atau Lovable.

## Prompt utama — Google Stitch

```text
Create a high-fidelity Android/iOS mobile app mockup named “SILAPPKASAL Reporter”.

Scope ONLY the public Reporter access flow before the Portal: Login, Register Reporter, and Track Anonymous Report. Do not design any staff, investigator, leader, or administrator screens.

Visual language: trustworthy Indonesian campus public service, calm dark navy background, teal/cyan primary action, readable white text, rounded cards, discreet security/privacy iconography, accessible contrast, 8pt spacing grid, Indonesian copy. Do not make it feel like a generic fintech app. Use a clean top brand mark “SILAPPKASAL”.

Generate 9 connected mobile canvases:
1. Login default.
2. Login validation/error/loading.
3. Register default.
4. Register with University dropdown opened.
5. Register with “Universitas STAI Sebelas April” selected and “Ekonomi Syariah” selected in Program Studi.
6. Register success with registration number and Back to Login.
7. Track Anonymous default.
8. Track Anonymous validation/error/loading.
9. Track Anonymous success showing only Registration Number, Status, and Submitted At.

Login details: title “Masuk”; identifier label “Email, NIM, atau NIP”; password label “Kata Sandi”; show/hide password; “Ingat saya”; primary button “Masuk”; links “Belum punya akun? Daftar sebagai Pelapor” and “Lacak pengaduan anonim”.

Register details: fields Nama Lengkap, NIM / NPM, Alamat Email, Nomor Telepon, Universitas dropdown, optional Fakultas dropdown only when available, Program Studi dropdown, Kata Sandi, Konfirmasi Kata Sandi, and button “Kirim Pendaftaran”. Before master data loads show “Memuat universitas...”; then “Pilih universitas”. Program Studi starts disabled and becomes enabled only after Universitas. Use the selected example values Universitas STAI Sebelas April and Ekonomi Syariah. Include visible inline validation, loading, empty/error dropdown state, and a safe success screen with a registration number.

Track Anonymous details: title “Lacak Pengaduan”; field label “Kode Pelacakan”; placeholder “XXXX-XXXX-XXXX-XXXX”; uppercase input; button “Lacak”; show loading “Melacak...”. Success may show only Nomor Registrasi, Status, Dikirim pada. Never show reporter identity, chronology, respondent data, or internal case information.

Use realistic mobile keyboard-safe layouts, 44px minimum interactive targets, labels above inputs, helper/error text below inputs, disabled button states, and clear back navigation. Link every action to the relevant canvas.
```

## Prompt utama — Lovable

```text
Design a responsive mobile-first prototype for “SILAPPKASAL Reporter”, an Indonesian campus sexual-violence reporting service. Build only public access: /login, /register, /track. No internal staff roles.

Follow these exact routes and behaviors:
- /login: Email, NIM, atau NIP; Kata Sandi with visibility toggle; Ingat saya; Masuk; links to /register and /track; validation, loading, safe login error.
- /register: Nama Lengkap, NIM / NPM, Alamat Email, Nomor Telepon, Universitas, optional Fakultas, Program Studi, Kata Sandi, Konfirmasi Kata Sandi. University and study program are live backend dropdowns. Model loading “Memuat universitas...”; choose Universitas STAI Sebelas April, then Program Studi Ekonomi Syariah. Program Studi is disabled before university selection. Include validation, dropdown loading/empty/error, submit loading, and a registration-success page displaying a registration number and Back to Login.
- /track: Kode Pelacakan uppercase with placeholder XXXX-XXXX-XXXX-XXXX; Lacak; error/loading. Success exposes only Nomor Registrasi, Status, Dikirim pada.

Style: dark navy, teal primary CTAs, restrained cards, high contrast, Indonesian labels, calming and privacy-respecting. Create all default, focused, disabled, error, loading, and success states. Add a lightweight flow map showing login ↔ register, login ↔ anonymous tracking, and success → login.
```

## Handoff untuk implementasi sesudah desain disetujui

- Sumber UI web: `frontend/src/routes/login.tsx`, `register.tsx`, dan `track.tsx`.
- Data pendaftaran harus tetap dari backend (universities → faculties/study programs), bukan mock list.
- Jangan memasukkan kredensial QA ke desain, mockup, source, atau dokumentasi.
- Kontrak endpoint lebih lengkap tersedia di `MOBILE_REPORTER_ENDPOINT_MATRIX.md`.

