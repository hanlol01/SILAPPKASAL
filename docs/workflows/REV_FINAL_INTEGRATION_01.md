# REV-FINAL-INTEGRATION-01 — Final Integration and Release Readiness

**Status:** `READY WITH CONDITIONS` untuk source dan regression otomatis. Dokumen ini bukan
otorisasi deploy, migrasi PostgreSQL, atau UAT produksi.

## Scope dan checkpoint

Branch kerja dibuat dari checkpoint `a5e3f4bebff1342d73e9a818b9c9630ce6132227`.
Rantai revisi yang diverifikasi adalah:

```text
REV-ED-01 / REV-MEDIA-01 / REV-GLOBAL-TERM-01
  -> REV-WITHDRAW-01A..01D
  -> REV-ASSIGN-01
  -> REV-DECISION-CODE-01
  -> REV-CASE-BA-01
```

Commit ancestor yang wajib ada adalah `5341a237` (withdrawal integration), `7f1472b`
(assignment), `0bcc688` (formal decision code), dan `a5e3f4b` (Case Minutes).
Milestone ini tidak menambah fitur, migration, dependency, atau perubahan lifecycle. Satu hardening
integrasi menambahkan `private.no-store` pada endpoint formal Decision; ia hanya menetapkan header
cache dan tidak mengubah RBAC atau response contract.

## Peta integrasi aktual

```text
Pengaduan -> Case -> Assignment -> Investigation -> Recommendation
          -> Decision recorded -> Decision finalized + SK/PPKS/YYYY/NNN
          -> Recovery / Monitoring -> Final Summary -> Closure

Pengaduan tanpa Case -> pembatalan langsung
Pengaduan/Case -> withdrawal formal draft -> dokumen privat -> pending_review
                -> Admin approve/reject -> withdrawn read-only / resubmission

Case investigation/recommendation -> BA draft -> BA finalized
                                  -> revision draft -> versi lama superseded
```

Hotspot bersama adalah `CaseMutationGuard`, `CaseCampusScope`, policy per domain,
`WorkflowDatabaseNotification`, `AuditLogService`, dan frontend
`synchronizeWorkflowCaches`. Guard memegang urutan `Report -> Case -> pending Withdrawal`
sebelum child row domain dikunci.

## Otorisasi dan privasi terkonsolidasi

| Role | Operasional Case | Withdrawal | Decision / nomor formal | Berita Acara |
|---|---|---|---|---|
| Reporter | Hanya Pengaduan sendiri dan proyeksi aman | Pembatalan langsung / withdrawal milik sendiri | Tidak ada endpoint atau nomor internal | Tidak ada endpoint/proyeksi |
| Satgas PPKS | Hanya Case assignment aktif, same-campus | Tidak memiliki review | Baca sesuai assignment; tidak mutasi Decision | Read/write/revise hanya assignment aktif; tidak finalize |
| Admin Kampus | Same-campus dengan permission | Queue/review own campus | Create/update/finalize own campus | Read/write/finalize own campus |
| Super Admin | Oversight sesuai permission, tanpa mutasi operasional | Metadata-only lintas kampus | Metadata-only termasuk nomor formal | Metadata-only tanpa narasi |

`is_lead` adalah compatibility field dan tidak memberi authority. `ketua_satgas` bukan role
aktif. Semua capability UI berasal dari projection backend; frontend bukan authority boundary.

Case terminal, termasuk `withdrawn`, adalah read-only. Formal withdrawal `pending_review`
mem-pause assignment, investigasi, rekomendasi, Decision, nomor formal, Recovery, final
summary, closure, dan Case Minutes. Keputusan akhir tetap idempoten: retry finalisasi tidak
menambah nomor, audit, notifikasi, atau transisi Case.

## Locking, audit, dan cache

- Lock global: `Report -> Case -> pending Withdrawal -> child domain`.
- Child row Case Minutes dikunci menaik menurut `id`; Recommendation dikunci sebelum Decision;
  Decision Number Sequence dikunci setelah Decision.
- Token stale Case/assignment, withdrawal, content, dan Case Minute dicek setelah row lock.
- `WorkflowDatabaseNotification` memakai `afterCommit`; Case Minute juga mendaftarkan callback
  `DB::afterCommit` sebelum membuat notifikasi.
- Audit memakai allowlist metadata. Narasi BA, alasan withdrawal, dokumen privat, path storage,
  dan identitas anonim tidak boleh masuk payload audit/notifikasi.
- Private query keys (`operations`, `dashboard`, `portal`, dan Content) dibatalkan dan dihapus
  saat logout, invalidasi sesi, atau pergantian akun/role.

## Batas validasi eksternal

Belum ada klaim bahwa migration PostgreSQL 040000/050000, parallel-concurrency PostgreSQL,
browser E2E, UAT manual, backup/restore, atau deployment telah dilakukan. Gunakan dokumen
preflight, rollback, release checklist, dan UAT companion pada milestone ini sebelum deployment.
