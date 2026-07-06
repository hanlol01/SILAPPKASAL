# Daftar Perubahan dan Fitur Baru

1. **Admin**: Setelah klik *teruskan ke kasus* lalu memilih satgas terpilih, berikan teks di halaman reports yang menyatakan *Kasus sudah diteruskan ke satgas terpilih*. Tombol *Teruskan ke Kasus* tidak perlu diubah, cukup tambahkan teks secara rapi di posisi yang nyaman dilihat.

2. **Satgas/Admin/Superadmin**: Di halaman dashboard/cases, hilangkan tombol *Tugaskan Satgas* pada setiap case jika fitur ini hanya diperuntukkan bagi Admin/Superadmin.

3. **Satgas/Admin/Superadmin**: Pada halaman dashboard/cases, ketika status kasus diubah, halaman tidak refresh otomatis. Pengguna harus melakukan refresh manual agar status terbaru terlihat. Solusi sementara adalah menunggu beberapa detik setelah perubahan status sebelum melakukan refresh.

4. **Satgas/Admin/Superadmin**: Di halaman dashboard/cases, saat menekan tombol *Buat Investigasi*, di form ringkasan rencana harus ada indikator jumlah karakter minimal 50 agar pengguna tahu sisa karakter yang dibutuhkan.

5. **Satgas/Admin/Superadmin**: Di bagian aksi atau ringkasan kasus, tambahkan indikator *Status Kasus Terkini* agar pengguna tidak perlu membuka opsi ubah status untuk melihat status terbaru.

6. **Tambah Progress Kasus / Riwayat Alur**: Pada halaman detail kasus internal untuk Admin, Super Admin, dan Satgas, tampilkan timeline operasional seperti laporan dikirim, diteruskan ke Satgas, Satgas ditugaskan, investigasi dibuat, rekomendasi dikirim, putusan final, pemulihan selesai, hingga kasus ditutup.

7. **Langkah Berikutnya Card**: Tambahkan sebuah card pada panel aksi di halaman detail kasus yang membaca status kasus dan role pengguna untuk memberi arahan tindakan selanjutnya (misalnya Satgas mengubah status kasus atau Super Admin membuat putusan).

8. **Perkembangan Laporan**: Pada halaman detail laporan pelapor, tampilkan timeline perkembangan laporan yang aman dan tidak menampilkan informasi sensitif seperti nama Satgas atau isi rekomendasi/putusan/bukti/sanksi/dll.

9. **Ringkasan Status Akhir**: Saat kasus selesai, tampilkan pesan aman seperti â€œKasus Anda telah selesai ditangani. Untuk informasi lanjutan silakan hubungi kanal resmi Satgas **PPKS**.â€

10. **Perbaikan Tombol Aksi**: Tombol *Tambah Monitoring* harus disembunyikan atau dinonaktifkan ketika status pemulihan sudah selesai (completed), karena backend hanya mengizinkan monitoring dibuat saat recovery ongoing.

11. **Asesmen Risiko & Prioritas**:
    - Saat ini belum ada flow input normal.
    - Perlu form/aksi assessment untuk penugasan satgas.
    - Hanya aktif saat status kasus assessment.
    - Menggunakan master data `risk_levels` dan `priority_levels`.

12. **Placeholder Aksi Penugasan (/dashboard/cases)**:
    - Rapiakan tombol global seperti â€œAksi penugasan belum tersediaâ€.
    - Flow yang disarankan: daftar kasus â†’ detail kasus â†’ Tugaskan Satgas.
    - Penugasan sebaiknya per case bukan aksi global tanpa case terpilih.

13. **Tombol Tugaskan Satgas untuk Role Satgas**:
    - Permission backend sudah benar; satgas tidak boleh assign.
    - UI perlu role-aware.
    - Untuk satgas, tombol disembunyikan atau diganti keterangan â€œPenugasan Satgas dikelola oleh Admin/Pimpinan **PPKS**.â€

14. **Copy Pembatasan Detail Sensitif**:
    - Gunakan label peran manusiawi: Admin, Pimpinan **PPKS**, Satgas **PPKS**, Pelapor.
    - Copy disarankan: â€œAkses detail dibatasi untuk menjaga kerahasiaan laporan. Pengguna dengan peran {{roleLabel}} hanya dapat melihat ringkasan operasional.â€
15. `r`n15. **Flow Bukti / Evidence**:
    - Saat ini tab **Bukti** pada detail kasus baru menampilkan metadata bukti, sedangkan upload, unduh, pratinjau, dan penyimpanan file fisik belum tersedia.
    - Pelapor sebaiknya dapat melampirkan bukti saat membuat laporan, tetapi bukti harus bersifat opsional agar pelapor tetap bisa mengirim laporan tanpa hambatan.
    - Pelapor juga sebaiknya dapat menambahkan bukti setelah laporan dibuat selama laporan/kasus masih aktif dan sesuai batasan keamanan.
    - Bukti dari pelapor harus disimpan sebagai file private, bukan public asset, dengan validasi tipe file, ukuran file, metadata file, dan kontrol akses yang ketat.
    - Saat laporan diteruskan menjadi kasus, bukti dari pelapor perlu muncul di tab **Bukti** sebagai bukti awal yang dapat ditinjau oleh Satgas yang ditugaskan.
    - Tambahkan tombol **Tambah Bukti** di tab Bukti untuk Satgas assigned, minimal untuk membuat metadata bukti terlebih dahulu jika upload file penuh belum siap.
    - Tambahkan empty state yang jelas ketika belum ada bukti, misalnya: "Belum ada bukti yang tercatat. Satgas dapat menambahkan metadata bukti selama tahap investigasi."
    - Bukti sebaiknya menjadi bagian dari flow investigasi, bukan hanya muncul setelah kasus selesai. Idealnya bukti dikumpulkan saat status kasus masih investigasi sebelum rekomendasi dibuat.
    - Tambahkan status lifecycle bukti seperti terdaftar, ditinjau, diverifikasi, ditolak, atau diarsipkan.
    - Tambahkan audit trail / chain of custody untuk setiap perubahan bukti, termasuk siapa yang mengunggah, meninjau, mengubah status, atau mengunduh file.
    - Portal pelapor tidak perlu menampilkan detail bukti secara bebas. Cukup tampilkan informasi aman seperti "Bukti telah diterima" atau "Bukti sedang ditinjau" tanpa membuka detail internal.
    - Revisi bukti ini bergantung pada revisi lain seperti **Perkembangan Laporan**, **Progress Kasus / Riwayat Alur**, **Copy Pembatasan Detail Sensitif**, dan kontrol role/permission, karena bukti menyentuh keamanan file, RBAC, audit trail, dan transparansi aman kepada pelapor.

