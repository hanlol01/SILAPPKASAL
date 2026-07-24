# REV-ASSIGN-01 — Satgas Assignment dan Self-Assignment

## Tujuan

Milestone ini menyatukan penugasan Satgas pada Case, menambahkan antrean self-assignment yang aman, mempertahankan histori, dan menghapus ketergantungan operasional pada Ketua/lead Satgas tanpa perubahan schema.

## Role, permission, dan campus scope

- Admin aktif dengan `cases.assign_satgas` dapat assign/reassign Case kampusnya.
- Satgas PPKS aktif dengan `cases.read.assigned` dapat melihat antrean Case tanpa penugasan pada kampusnya dan mengambil satu Case untuk dirinya.
- Super Admin tetap metadata-only dan tidak memperoleh mutation assignment.
- Reporter dan role lain fail-closed.
- Target Admin assignment harus user aktif, ber-role `satgas_ppks`, dan berada pada kampus Case.
- Self-assignment tidak menerima ID assignee dari client; assignee berasal dari authenticated actor.

Tidak ada role `ketua_satgas` pada master role aktif. Kolom legacy `case_assignments.is_lead` dipertahankan untuk kompatibilitas data, tetapi tidak dipakai policy, service, capability, atau UI dan tidak memberi kewenangan.

## Flow API

1. `POST /api/v1/reports/{report}/forward-to-case` menerima `satgas_ids` tanpa lead.
2. `PATCH /api/v1/cases/{case}/assign` menerima `satgas_ids` dan token `lock_version`.
3. `GET /api/v1/cases?assignment_status=unassigned` menyediakan antrean same-campus untuk Satgas eligible.
4. `POST /api/v1/cases/{case}/self-assign` hanya menerima `lock_version`.
5. Response Case memisahkan `assignments` aktif dan `assignment_history`, serta memproyeksikan `assignment_capabilities`.

## Mutability dan concurrency

Semua mutation menggunakan `CaseMutationGuard` dengan urutan lock Report → Case → pending Withdrawal. Case operationally terminal, withdrawn, closed, sudah berada pada tahap `decided`, `recovery`, `monitoring`, atau `escalated`, maupun dipause oleh formal withdrawal `pending_review` ditolak. Token `lock_version` adalah SHA-256 opaque yang diturunkan dari state Case dan assignment aktif; tidak ada kolom atau migration baru.

Self-assignment memeriksa ketiadaan assignment aktif di dalam transaksi setelah Case terkunci. Dua actor yang memakai token awal yang sama tidak dapat menghasilkan dua assignment aktif: pemenang pertama mengubah token dan operasi berikutnya menerima `409 case_assignment_stale` atau `case_assignment_unavailable` setelah reload.

## Histori, audit, dan notification

- Reassign menonaktifkan baris yang dilepas dengan `unassigned_at` dan membuat baris baru untuk assignee baru.
- Audit event membedakan `case.assigned`, `case.reassigned`, dan `case.self_assigned`, dengan ID assignee berbentuk metadata skalar non-sensitif.
- Notification `case_assigned` dikirim hanya kepada assignee baru; self-assignee menerima notification miliknya.
- Response mutation assignment tidak memproyeksikan detail pengaduan sensitif. Satgas yang sudah sah ditugaskan mengambil detail melalui endpoint Case yang tetap dilindungi policy.

## UI

Admin memakai dialog reusable untuk memilih Satgas aktif tanpa field lead. Satgas memperoleh filter “Penugasan saya / Tersedia untuk diambil” dan aksi “Ambil penugasan” hanya dari capability server. Success, validation, dan conflict menyinkronkan cache Case, daftar, dashboard, dan My Work. Case terminal atau dipause tidak memproyeksikan capability mutation.

## Coverage dan batasan

Coverage backend mencakup assign/reassign, self-assignment, spoofed target, permission, campus isolation, target invalid, terminal/withdrawal guard, stale token, histori, audit, notification, privacy, dan simulasi dua klaim berurutan dengan token sama. Coverage frontend memeriksa capability, payload, cache refresh, conflict, locale, dan penghapusan control lead.

Tidak ada migration baru dan migration PostgreSQL tidak dijalankan. Uji true-parallel PostgreSQL dan browser E2E tetap menjadi validasi lanjutan; test SQLite memverifikasi invariant transaksi sejauh harness mendukung.
