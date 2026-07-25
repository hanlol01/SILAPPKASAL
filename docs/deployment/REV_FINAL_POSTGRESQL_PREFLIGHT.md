# REV-FINAL-INTEGRATION-01 — PostgreSQL Preflight dan Execution Plan

**Status:** rencana operasional. Tidak satu pun command migrasi di dokumen ini telah dijalankan
oleh REV-FINAL-INTEGRATION-01. Jalankan hanya pada database disposable atau target yang telah
disetujui, setelah backup tervalidasi.

Semua command Artisan berikut dijalankan dari `backend/api`. Operator memasok environment melalui
mekanisme deployment yang disetujui; jangan menempelkan credential atau connection string ke shell
history, tiket, atau evidence.

## Static review yang telah dilakukan

### `2026_07_24_040000_add_formal_decision_number_sequence.php`

- Preflight hanya menghitung kelompok `decisions.decision_number` non-null yang duplikat; NULL
  bukan conflict dan tidak ada rewrite/backfill.
- Setelah preflight bersih, migration membuat `decision_number_sequences(year smallint PK,
  last_value unsignedBigInteger Laravel)` dan unique index nullable
  `decisions_decision_number_unique`.
- Nama index aman untuk PostgreSQL. DDL berada dalam transaksi Laravel; rollback hanya melepas
  index dan tabel sequence, bukan `decisions.decision_number`.
- Generator menggunakan `insertOrIgnore`, lalu `lockForUpdate` setelah row tersedia. Ia berhenti
  sebelum nilai diubah pada exhaustion `999` dan transaction caller membatalkan finalisasi.

### `2026_07_24_050000_create_case_minutes_table.php`

- `public_id` memakai UUID Laravel, `case_id`/aktor memakai bigint seperti tabel induk.
- Constraint `(case_id, version)` unik, index `(case_id, status)`, dan index `supersedes_id`
  memiliki nama eksplisit dan pendek.
- FK Case dan creator memakai `RESTRICT`; updater/finalizer memakai `SET NULL`; self-FK
  `supersedes_id` memakai `RESTRICT`. Tidak ada full-text index ciphertext.
- Encrypted narrative tetap kolom `text`; tidak ada file, URL, nomor dokumen, atau data binary.
- Path SQLite untuk rollback hanya menonaktifkan FK pada koneksi SQLite. PostgreSQL langsung
  `dropIfExists('case_minutes')`, sehingga tidak membawa workaround SQLite ke PostgreSQL.

## Stop conditions

Hentikan sebelum `migrate` bila salah satu kondisi berikut terjadi:

1. target bukan database yang sudah disetujui atau nama database tidak sesuai change record;
2. `APP_ENV`, `DB_CONNECTION`, `DB_URL`, host, atau migration status tidak tervalidasi;
3. backup PostgreSQL dan private storage tidak terbukti dapat dipulihkan;
4. duplicate query di bawah menghasilkan baris;
5. ada migration pending di luar release yang belum direview;
6. schema inspection atau migration dry inspection berbeda dari manifest ini.

## Jalur A — disposable rehearsal (wajib lebih dahulu)

1. Catat release commit, waktu, operator, nama database disposable, dan checksum backup; jangan
   catat credential pada tiket atau dokumen.
2. Buat backup PostgreSQL yang dapat dipulihkan dan backup disk privat terkait. Uji restore pada
   lingkungan disposable sebelum target produksi.
3. Verifikasi environment disposable menggunakan command read-only, misalnya:

   ```bash
   php artisan about
   php artisan migrate:status
   ```

   Konfirmasi database disposable memang bukan target deployment, `DB_CONNECTION=pgsql`, dan
   migration status sesuai manifest. Jangan menyalin output secret.

4. Jalankan duplicate preflight **sebelum** migration:

   ```sql
   SELECT COUNT(*) AS duplicate_non_null_decision_number_groups
   FROM (
       SELECT 1
       FROM decisions
       WHERE decision_number IS NOT NULL
       GROUP BY decision_number
       HAVING COUNT(*) > 1
   ) AS duplicate_groups;
   ```

   Nilai harus `0`. STOP bila lebih besar dari `0`; jangan menampilkan nomor yang duplikat atau
   memperbaiki data dengan script ad-hoc dalam release window.

5. Lakukan dry inspection (`php artisan migrate:status` dan review migration yang pending), lalu
   jalankan migration normal yang telah disetujui pada **database disposable saja**. Tidak gunakan
   `migrate:fresh`, `migrate:refresh`, `db:wipe`, backfill, atau rollback buta. Aplikasi tidak
   menjalankan migration otomatis saat start.

   ```bash
   php artisan migrate
   ```

6. Setelah migrate, verifikasi schema secara read-only:

   ```sql
   SELECT indexname, indexdef
   FROM pg_indexes
   WHERE tablename IN ('decisions', 'case_minutes', 'decision_number_sequences')
   ORDER BY tablename, indexname;

   SELECT conname, pg_get_constraintdef(oid)
   FROM pg_constraint
   WHERE conrelid IN ('case_minutes'::regclass, 'decisions'::regclass)
   ORDER BY conname;

   SELECT column_name, data_type, is_nullable
   FROM information_schema.columns
   WHERE table_name IN ('decisions', 'decision_number_sequences', 'case_minutes')
   ORDER BY table_name, ordinal_position;

   SELECT year, last_value
   FROM decision_number_sequences
   ORDER BY year;
   ```

   Verify explicitly: nullable `decisions.decision_number` remains supported; the named unique index
   protects non-null values; `decision_number_sequences.year/last_value` exists; `case_minutes`
   has unique `public_id`, unique `(case_id, version)`, status index, self-reference, and Case/user
   FK actions that preserve history (`RESTRICT`/`SET NULL` as applicable). STOP on any mismatch.

7. On disposable data, test a new Decision finalization and verify one sequence row/year, monotonically
   increasing `last_value`, nullable legacy Decision values, and no duplicate formal number. Test BA
   `case_minutes` FK, unique `(case_id, version)`, metadata-only Super Admin projection, and
   finalized/superseded history. Verify approved queue/cache/config values and worker readiness with
   the platform owner, then run authenticated application smoke. Rehearse rollback only on the
   disposable database and attach non-sensitive evidence.

## Jalur B — target deployment database (terpisah dan masih pending)

1. Gunakan hanya setelah Jalur A, backup PostgreSQL/private storage, restore rehearsal, UAT browser,
   dan approval deployment/rollback owner tercatat PASS.
2. Ulangi environment verification dan duplicate **count-only** preflight pada database target yang
   namanya dikonfirmasi operator. STOP bila target berbeda, backup tidak terbukti, nilai duplicate
   bukan `0`, atau terdapat migration pending di luar release.
3. Catat `php artisan migrate:status`, output schema verification, release commit, operator, dan
   waktu sebagai evidence tanpa credential. Jalankan migration additive normal hanya setelah change
   approval eksplisit; jangan memakai perintah destructive.

   ```bash
   php artisan migrate --force
   ```
4. Setelah migration, ulangi schema, queue/cache/config, dan smoke checks di atas. Sebelum membuka
   traffic, gunakan rollback decision gate: owner menyetujui hasil smoke atau mengaktifkan rollback
   plan. Jalur ini tidak menyatakan target migration telah dijalankan.

Promote only after smoke, monitoring, and rollback evidence are signed off. Production execution
requires a separate authorization; this plan does not authorize it.
