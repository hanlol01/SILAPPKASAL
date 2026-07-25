# REV-FINAL-INTEGRATION-01 — Release Readiness Checklist

**Keputusan source saat ini:** `READY WITH CONDITIONS`.

## Gate register

Status yang diperbolehkan adalah `PASS`, `FAIL`, atau `PENDING`. Baris `PENDING` adalah stop
condition deployment, bukan klaim selesai. Owner mengisi evidence reference dan approval pada change
record; jangan menaruh credential, dump, atau PII di dokumen ini.

| Gate | Status saat checkpoint | Owner | Evidence/reference | Stop condition | Approval |
|---|---|---|---|---|---|
| 1. Release commit/revision ditetapkan | PENDING | Release owner | Git chain hingga `a5e3f4b` plus cache-header hardening patch yang belum dipilih sebagai release commit | Commit final belum ditetapkan atau tidak cocok | Pending |
| 2. Worktree calon release bersih | PENDING | Release owner | `git status --short` sebelum tag/deploy | Ada perubahan/staging tak direview | Pending |
| 3. Backup PostgreSQL selesai | PENDING | DBA | Backup checksum/reference | Backup tidak ada/tidak terbaca | Pending |
| 4. Backup private storage selesai | PENDING | Storage owner | Backup checksum/reference | Backup tidak ada/tidak terbaca | Pending |
| 5. Restore rehearsal berhasil | PENDING | DBA + storage owner | Disposable restore evidence | Restore gagal/tidak dibuktikan | Pending |
| 6. Duplicate decision-number preflight target lulus | PENDING | DBA | Count-only query result `0` | Nilai bukan `0` | Pending |
| 7. PostgreSQL disposable migration/rollback rehearsal lulus | PENDING | DBA + engineering | Migration/schema/rollback evidence | Schema/migration mismatch | Pending |
| 8. Target database migration status diperiksa | PENDING | DBA | `migrate:status` + approved manifest | Target/migration pending tak direview | Pending |
| 9. Browser/manual UAT lulus | PENDING | Product owner + QA | UAT row evidence | Privacy/role scenario gagal | Pending |
| 10. Privacy boundaries lulus | PENDING | Security + QA | Automated evidence + UAT privacy rows | Leak/projection mismatch | Pending |
| 11. Queue worker siap | PENDING | Platform owner | Worker/failed-job readiness evidence | Worker/queue tidak sehat | Pending |
| 12. Cache/config readiness | PENDING | Platform owner | Approved config/cache check | Config/cache target salah | Pending |
| 13. Rollback owner tersedia | PENDING | Change manager | Named owner in change record | Owner tidak tersedia | Pending |
| 14. Deployment owner memberi approval | PENDING | Deployment owner | Change approval ID | Approval belum ada | Pending |
| 15. Post-deploy smoke plan tersedia | PENDING | QA + release owner | UAT/smoke reference | Rencana/evidence tidak tersedia | Pending |
| Automated regression and static review | PASS | Engineering/QA | `QA_REPORT.md`, route/static checks | New Blocking/High regression | QA recorded |

Scoped Pint `--test` passes for all 66 PHP files in the integration source chain. Whole-tree style
debt outside that scope is not a reason to run mass formatting during release.

## Go / no-go

Jangan deploy bila ada Blocking/High regression, duplicate formal number, backup tidak dapat
dipulihkan, target database tidak tervalidasi, atau UAT privacy/role gate belum disetujui.
Warning toolchain baseline tidak boleh disamakan dengan PASS tanpa klasifikasi.

## Pre-deploy sequence

1. Lock release commit dan gunakan backend/frontend artifact dari commit sama.
2. Jalankan preflight PostgreSQL di `REV_FINAL_POSTGRESQL_PREFLIGHT.md` pada disposable target.
3. Review backup/restore evidence dan migration status.
4. Jalankan manual UAT checklist tanpa menggunakan data produksi sensitif.
5. Dapatkan approval owner untuk maintenance, migration, deployment, dan rollback owner.
6. Setelah authorized deploy, lakukan health, role, workflow, cache, private-file, dan notification
   smoke checks sebelum membuka traffic penuh.

## Post-deploy monitoring

- Monitor error/409 rate untuk stale lock, paused withdrawal, formal decision sequence, dan BA.
- Monitor queue notification after-commit, file-preview MIME failures, and authorization denials.
- Monitor unique-index/FK errors for `decisions` and `case_minutes`.
- Confirm no reporter-facing response gains Decision number, BA, or internal narrative fields.
