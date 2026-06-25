# DEMO\_DATASET\_[SPEC.md](http://SPEC.md) 
 
**Project:** SILAPPKASAL
 
**Version:** 2.0
 
**Status:** Frozen
 
**Purpose**
 
Dokumen ini mendefinisikan dataset demo resmi SILAPPKASAL.
 
Dataset ini digunakan untuk:
 

*   Internal Demo
     
*   Smoke Testing
     
*   User Acceptance Test (UAT)
     
*   QA
     
*   Developer Onboarding
     
*   Regression Testing
     

Dataset harus bersifat:
 

*   realistis
     
*   konsisten
     
*   idempotent
     
*   merepresentasikan seluruh workflow bisnis
     
*   siap dipakai tanpa konfigurasi tambahan
     

---
 

# 1\. General Principles
 
Dataset **bukan** data acak.
 
Dataset harus membentuk alur bisnis nyata dari awal sampai akhir.
 
Semua dashboard harus memiliki data.
 
Tidak boleh ada halaman utama yang kosong.
 
Semua foreign key harus valid.
 
Semua seeder harus idempotent.
 
Gunakan updateOrCreate() bila memungkinkan.
 
---
 

# 2\. Demo Login Accounts
 
Semua akun menggunakan password:
 

    Demo123@
    

## Platform
 
Super Admin
 

    superadmin@silappkasal.test
    

## Per Kampus
 
Untuk setiap kampus dibuat:
 
Administrator
 

    admin.<kodekampus>@silappkasal.test
    

Satgas
 

    satgas.<kodekampus>@silappkasal.test
    

Reporter Approved
 

    reporter.<kodekampus>@silappkasal.test
    

Reporter Pending
 

    pending.<kodekampus>@silappkasal.test
    

Reporter Rejected
 

    rejected.<kodekampus>@silappkasal.test
    

---
 

# 3\. Universities
 
Seed universitas berikut.
 

1.  STAI Sebelas April
     
2.  STAI Al Musaddadiyah Garut
     
3.  Universitas Islam KH. Ilyas Ruhiyat
     
4.  Institut Nahdlatul Ulama Tasikmalaya
     
5.  Universitas Islam Darussalam Ciamis
     
6.  Sekolah Tinggi Ilmu Tarbiyah Nahdlatul Ulama Al-Farabi
     
7.  Institut Miftahul Azhar Banjar
     

Gunakan data nyata sejauh memungkinkan.
 
---
 

# 4\. Faculties & Study Programs
 
Gunakan struktur akademik yang realistis.
 
Untuk sekolah tinggi:
 

    has_faculties = false
    

Program studi langsung berada di bawah universitas.
 
Untuk universitas:
 
Gunakan struktur fakultas dan program studi yang benar.
 
---
 

# 5\. Users
 
Minimal:
 

*   1 Super Admin
     

Per kampus:
 

*   1 Admin
     
*   2 Satgas
     
*   2 Reporter Approved
     
*   1 Reporter Pending
     
*   1 Reporter Rejected
     

Semua user memiliki:
 

*   nama realistis
     
*   email konsisten
     
*   nomor telepon
     
*   universitas
     
*   fakultas bila ada
     
*   program studi
     

---
 

# 6\. Reporter Registrations
 
Harus tersedia contoh:
 
Pending
 
Approved
 
Rejected
 
Rejected harus memiliki:
 

*   rejection\_reason
     
*   reviewed\_by
     
*   reviewed\_at
     

Pending belum memiliki reviewer.
 
Approved sudah berubah menjadi User aktif.
 
---
 

# 7\. Reports
 
Seed berbagai jenis laporan.
 
Gunakan narasi realistis.
 
Tidak menggunakan lorem ipsum.
 
Kategori bervariasi.
 
Tanggal bervariasi.
 
---
 

# 8\. Cases
 
Buat kasus pada seluruh status workflow.
 
Contoh:
 
Submitted
 
Verification
 
Investigation
 
Recommendation
 
Decision
 
Recovery
 
Closed
 
---
 

# 9\. Investigations
 
Buat investigasi:
 
ongoing
 
completed
 
Assign investigator.
 
---
 

# 10\. Recommendations
 
Gunakan berbagai status.
 
Minimal:
 
draft
 
submitted
 
accepted
 
rejected
 
---
 

# 11\. Decisions
 
Minimal:
 
accepted
 
partially accepted
 
rejected
 
deferred
 
---
 

# 12\. Recoveries
 
Minimal:
 
planning
 
ongoing
 
completed
 
Monitoring juga tersedia.
 
---
 

# 13\. Evidence
 
Gunakan metadata realistis.
 
Tidak perlu file fisik.
 
---
 

# 14\. Notifications
 
Setiap role memiliki notifikasi.
 
Contoh:
 
Laporan baru
 
Investigasi selesai
 
Rekomendasi dibuat
 
Recovery selesai
 
Keputusan difinalisasi
 
---
 

# 15\. Audit Logs
 
Minimal 100 audit log.
 
Harus mencakup seluruh workflow.
 
Audit harus terlihat realistis.
 
---
 

# 16\. Dashboard Readiness
 
Dashboard tidak boleh kosong.
 
Semua widget memiliki data.
 
Analytics memiliki nilai.
 
Timeline memiliki aktivitas.
 
---
 

# 17\. Story-driven Dataset
 
Dataset harus membentuk alur berikut:
 
Reporter Registration
 
↓
 
Approval
 
↓
 
Login Reporter
 
↓
 
Create Report
 
↓
 
Case
 
↓
 
Investigation
 
↓
 
Recommendation
 
↓
 
Decision
 
↓
 
Recovery
 
↓
 
Monitoring
 
↓
 
Case Closed
 
Minimal terdapat beberapa kasus yang berhenti di setiap tahapan sehingga semua workflow dapat didemokan.
 
---
 

# 18\. Seeder Architecture
 
Disarankan dipisahkan menjadi:
 

*   CampusDemoSeeder
     
*   UserDemoSeeder
     
*   ReporterRegistrationDemoSeeder
     
*   ReportDemoSeeder
     
*   CaseDemoSeeder
     
*   InvestigationDemoSeeder
     
*   RecommendationDemoSeeder
     
*   DecisionDemoSeeder
     
*   RecoveryDemoSeeder
     
*   NotificationDemoSeeder
     
*   AuditDemoSeeder
     

DatabaseSeeder hanya menjadi orchestrator.
 
---
 

# 19\. Acceptance Criteria
 
Dataset dinyatakan selesai apabila:
 

*   migrate:fresh --seed berhasil
     
*   seluruh test tetap hijau
     
*   seluruh dashboard memiliki data
     
*   seluruh workflow dapat didemokan
     
*   registrasi mahasiswa dapat diuji
     
*   tracking dapat diuji
     
*   admin kampus memiliki data
     
*   satgas memiliki tugas
     
*   super admin melihat seluruh kampus
     
*   seluruh foreign key valid
     
*   tidak ada duplicate seed
     
*   seeder bersifat idempotent
     

---
 

# 20\. Out of Scope
 
Dataset ini tidak mengubah:
 

*   struktur database
     
*   API
     
*   RBAC
     
*   frontend
     
*   workflow bisnis
     

Dataset hanya menyediakan data demo yang realistis dan siap digunakan.