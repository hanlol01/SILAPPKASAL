

Daftar Master Data Kebutuhan Aplikasi SILAPPKASAL
Dokumen  ini  berisi  daftar  master  data  yang  perlu  disiapkan  oleh  pihak  perguruan  tinggi
untuk mendukung pengembangan aplikasi pelaporan kekerasan seksual SILAPPKASAL.
## 1. Master Data Kampus
- Nama perguruan tinggi
- Singkatan kampus
- Alamat kampus
- Website kampus
- Email resmi
- Nomor hotline layanan pengaduan/ Admin
## 2. Master Jenis Kekerasan
## Contoh :
- Pelecehan verbal
- Pelecehan fisik
- Pelecehan online
## • Intimidasi
## • Ancaman
- Perundungan seksual
- Kekerasan seksual
- Kategori lainnya
## 3. Master Status Penanganan
## Contoh :
- Laporan diterima
## • Verifikasi
## • Investigasi
## • Mediasi
## • Penyelesaian
## • Selesai
## • Ditutup
## 4. Master Role Pengguna
## Contoh :
## • Admin
## • Satgas
- Mahasiswa/Pelapor
## 5. Master Data Satgas / Petugas
- Nama petugas
## • Jabatan
- Unit kerja
## • Email
- Nomor HP
- Role sistem

## 6. Master Kategori Artikel Edukasi
## Contoh :
## • Pencegahan
- Edukasi seksual
- Regulasi kampus
## • Konseling
- Awareness campaign
- Informasi layanan
## 7. Master Lokasi Kampus
## Contoh :
## • Gedung
## • Laboratorium
- Area parkir
## • Asrama
- Area publik kampus
- Lokasi online/digital
- SOP dan Workflow Penanganan Kasus
- SOP Admin, Satgas, Mahasiswa/i
- Alur penanganan kasus
- SLA penanganan
- Mekanisme investigasi
- Mekanisme mediasi
- Mekanisme eskalasi
- Ketentuan laporan anonim
- Template Dokumen (Apabila dibutuhkan output laporan berupa surat)
- Berita acara
- Surat tindak lanjut
- Surat pemanggilan
- Form investigasi
- Form konseling
## 10. Data Dummy Awal
- Contoh laporan
- Contoh artikel
- Contoh user
- Contoh workflow

Catatan: Pengumpulan   master   data   sejak   awal   akan   membantu   proses   analisis,
desain  sistem,  implementasi,  dan  pengembangan  aplikasi  menjadi  lebih  cepat  dan
terstruktur.

## 11. REV-CONTENT-01 Storyboard Seed Contract

`ContentFoundationSeeder` is the canonical content master-data initializer. It creates the stable
sections Edukasi, Seputar Kebijakan, FAQ, and Konsultasi; the exact eight Education and two Policy
categories from `STORYBOARD_CONTENT_CATALOG.md`; 41 Article starter records; and eight planned FAQ
questions. Stable keys make reruns idempotent and reruns do not overwrite editorial changes.

Every seeded Article and FAQ version is global, `draft`, unpublished, sourced as `storyboard_seed`,
and marked `requires_editorial_review`. Article bodies and FAQ answers are intentionally empty except
for one approved low-risk neutral excerpt. No law, quotation, diagnosis, testimonial, emergency
claim, institutional contact, or Consultation record is invented. Running this seeder in production
requires explicit product-owner approval even though it cannot publish content.
