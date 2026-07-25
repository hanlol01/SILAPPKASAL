# REV-FINAL-INTEGRATION-01 — Rollback Plan

## Prinsip

Rollback adalah **backup-first** dan coordinated antara code, PostgreSQL, serta private storage.
Jangan memakai `migrate:rollback` secara buta setelah formal Decision number atau Berita Acara
dipakai secara operasional. Tidak ada migration pada REV-FINAL-INTEGRATION-01 sendiri.

## Trigger rollback

- failure migration atau constraint/schema mismatch;
- privacy/authorization leak, duplicate formal number, atau deadlock berulang;
- critical workflow regression setelah smoke;
- queue/cache/service failure yang membuat state transaksional tidak konsisten.

## A. Application rollback sebelum migration/data baru dipakai

1. Application code dapat dikembalikan ke commit checkpoint yang disetujui setelah owner menyetujui
   maintenance/traffic control.
2. Additive schema boleh tetap ada bila kompatibel dengan code rollback; jangan otomatis menjalankan
   migration down pada production hanya untuk menyamakan version code.
3. Catat waktu, commit, status migration, dan bukti health sebelum membuka traffic.

## B. Application rollback setelah data baru mulai dibuat

- Jangan drop unique index `decisions_decision_number_unique` tanpa analisis data dan persetujuan
  eksplisit; jangan drop `decision_number_sequences` setelah finalization baru terjadi.
- Jangan drop `case_minutes` setelah BA baru dibuat. Jangan menghapus issued decision number,
  mengubah legacy number, mereset sequence, atau menghapus history BA.
- Down `050000_create_case_minutes_table` dan `040000_add_formal_decision_number_sequence` hanya
  dapat direhearse pada database disposable, atau sebelum data operasional relevan ada dan setelah
  owner menyetujui. Tidak ada `migrate:rollback` buta.

## C. Failed migration

1. Aktifkan maintenance/traffic control sesuai runbook lingkungan dan hentikan mutation worker.
2. Catat error, release commit, dan status aktual dari `php artisan migrate:status`; jangan
   mengasumsikan DDL sudah rollback atau partial state aman.
3. Inspeksi schema/index/constraint aktual secara read-only. Eskalasi ke DBA/change owner bila ada
   partial DDL, duplicate constraint, atau data state yang tidak dapat dijelaskan.
4. Ambil backup tambahan dari state saat ini dan pilih restore checkpoint tervalidasi bila data baru
   telah ditulis. Jangan menghapus evidence incident.

## D. Operational rollback dan restore path yang disukai

1. Deploy code checkpoint yang disetujui.
2. Restore PostgreSQL dari backup pre-release yang telah diuji.
3. Restore private storage dari recovery point yang sama bila change window menyentuh dokumen/media.
4. Resume queue hanya setelah cache/config verification dan smoke role minimal lulus; clear cache
   atau keluar maintenance mode hanya melalui operator berwenang.
5. Verifikasi schema, migration status, health endpoint, queue, cache, dan smoke role minimal.
6. Catat root cause, owner, approval, dan evidence; jangan membuka traffic sampai owner menyetujui
   hasil verifikasi.

## Larangan

Tidak ada `migrate:fresh`, `migrate:refresh`, `db:wipe`, backfill ad-hoc, penghapusan Case/BA,
atau perubahan nomor formal sebagai langkah rollback. Kredensial, dump, dan path backup tidak
ditulis pada dokumentasi ini.
