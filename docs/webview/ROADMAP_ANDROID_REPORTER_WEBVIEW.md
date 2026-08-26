# Roadmap — SILAPPKASAL Android Reporter WebView

## Tujuan rilis

Menghasilkan APK Android bernama **SILAPPKASAL** yang membuka portal web Reporter secara lancar melalui WebView, tanpa menulis ulang fitur web dan tanpa menerbitkannya ke Play Store pada fase ini.

Rujukan teknis: [ANDROID_REPORTER_WEBVIEW_IMPLEMENTATION_PLAN.md](ANDROID_REPORTER_WEBVIEW_IMPLEMENTATION_PLAN.md).

## Prinsip roadmap

- Satu milestone hanya selesai jika kriteria penerimaannya lolos.
- Tidak ada perubahan backend atau portal web kecuali ditemukan incompatibility WebView yang sudah direproduksi dan disetujui.
- QA fitur yang mengubah pengaduan dilakukan dengan data QA yang disetujui, idealnya pada staging.
- APK bukan artefak yang otomatis dikomit ke repository.

## Milestone 0 — Readiness dan baseline

### Tujuan

Memastikan ruang kerja Flutter, perangkat Android, URL portal, dan batas akses Reporter siap sebelum kode dibuat.

### Pekerjaan

- Konfirmasi Flutter/Dart dan Android SDK yang tersedia.
- Verifikasi `mobile/` masih placeholder dan aman menjadi lokasi aplikasi Flutter.
- Siapkan Android device/emulator untuk smoke test.
- Tetapkan konfigurasi non-rahasia: app name `SILAPPKASAL`, application ID `id.silappkasal.app`, production URL `https://silappkasal.web.id`.
- Siapkan kebijakan URL: Login/Register/Track/`/portal/*` hanya pada host SILAPPKASAL yang disetujui.
- Siapkan akun/data QA tanpa menaruh kredensial di source atau dokumen.

### Output

- Baseline environment tercatat.
- Keputusan konfigurasi siap digunakan.
- Daftar device/emulator uji tersedia.

### Gate selesai

- Flutter dan Android build tool dapat dijalankan.
- Tidak ada konflik dengan isi `mobile/` yang ada.
- URL produksi dapat dibuka dari perangkat Android.

## Milestone 1 — APK WebView dasar

### Tujuan

Mendapatkan APK Android yang memasang dan membuka halaman Login SILAPPKASAL secara stabil.

### Pekerjaan

- Inisialisasi proyek Flutter pada `mobile/`.
- Konfigurasi nama aplikasi dan application ID.
- Implementasi startup/splash sederhana dan WebView dasar.
- Muat URL Login produksi melalui HTTPS.
- Aktifkan JavaScript yang diperlukan oleh portal React.
- Tambahkan loading progress dan error/retry dasar.
- Implementasikan Android Back untuk riwayat WebView dan keluar aplikasi pada root.

### Output

- Debug APK.
- README build/run lokal awal.
- WebView yang membuka `/login`.

### Gate selesai

- APK dapat diinstal pada device uji.
- Login, Register, dan Track dapat dibuka sebagai halaman web yang sama dengan browser.
- Tidak ada native form/fitur Reporter yang ditulis ulang.
- Back tidak membuat aplikasi blank atau crash.

## Milestone 2 — Kebijakan navigasi dan keamanan shell

### Tujuan

Menjadikan WebView sebagai aplikasi Reporter yang terbatas dan aman, bukan browser umum.

### Pekerjaan

- Implementasi allowlist host dan HTTPS.
- Izinkan hanya `/login`, `/register`, `/track`, `/portal/*`, serta asset aplikasi yang diperlukan.
- Blokir/handle URL internal non-Reporter dan skema berisiko (`http`, `file`, `javascript`).
- Dispatch link eksternal yang disetujui ke Android handler/browser.
- Pastikan logout tidak membuka halaman portal sebelumnya melalui tombol Back.
- Matikan WebView debugging pada release build.
- Pastikan tidak ada credential/token/log data pengaduan pada aplikasi.

### Output

- Navigation policy terdokumentasi di code dan README.
- State halaman terblokir/netral.
- Release-safe WebView settings.

### Gate selesai

- URL yang tidak diizinkan tidak dapat dibuka dalam WebView.
- Reporter dapat menyelesaikan navigasi web normal tanpa terblokir salah.
- Uji logout dan Android Back lulus.

## Milestone 3 — File, dokumen, dan integrasi Android

### Tujuan

Memastikan alur web yang membutuhkan kemampuan perangkat tidak berhenti di WebView.

### Pekerjaan

- Implementasi file chooser dari input upload web.
- Uji pemilihan file yang diizinkan dan pembatalan chooser.
- Tangani preview/unduh PDF atau dokumen.
- Tangani link `mailto:`, `tel:`, dan external link melalui intent Android yang aman.
- Minta permission hanya saat diperlukan oleh platform/alur user.
- Uji error jaringan dan Retry.

### Output

- Upload file dan alur dokumen dapat digunakan pada device uji.
- Catatan limitasi Android/WebView bila ada.

### Gate selesai

- File chooser mengembalikan pengguna ke form web tanpa kehilangan halaman.
- Aksi dokumen/link tidak menghasilkan blank page atau crash.
- No-network dan retry dapat dipahami pengguna.

## Milestone 4 — QA parity Reporter

### Tujuan

Membuktikan bahwa APK mempertahankan alur Reporter web pada perangkat Android.

### Pekerjaan

- QA public access: Login, Register, Lacak Anonim.
- QA Reporter read-only: Ringkasan, Pengaduan Saya, detail, Pusat Informasi, Notifikasi, Akun, logout.
- QA write workflow hanya dengan data QA yang disetujui: submit dummy, upload dummy, dan status aman bila diperlukan.
- Uji portrait, rotasi, background/foreground, jaringan lambat, dan restart aplikasi.
- Bandingkan dengan [MOBILE_REPORTER_PAGE_PARITY_CATALOG.md](../MOBILE_REPORTER_PAGE_PARITY_CATALOG.md).

### Output

- Laporan QA Android WebView.
- Daftar bug/blocker dan keputusan go/no-go.

### Gate selesai

- Semua alur prioritas tinggi Reporter lulus atau memiliki pengecualian tertulis yang disetujui.
- Tidak ada kebocoran ke role internal melalui navigasi normal.
- Tidak ada blocker untuk instalasi atau navigasi aplikasi.

## Milestone 5 — Release APK internal

### Tujuan

Menyerahkan APK yang dapat diinstal langsung oleh pengguna/penguji internal.

### Pekerjaan

- Tetapkan version name dan version code.
- Konfigurasi signing release menggunakan keystore yang tidak masuk Git.
- Build release APK.
- Hitung SHA-256 APK dan catat device Android yang diuji.
- Buat release note singkat, langkah instalasi, dan known limitations.
- Distribusikan APK melalui kanal aman yang disetujui.

### Output

- Signed release APK.
- SHA-256 checksum.
- Release note dan QA summary.

### Gate selesai

- APK ditandatangani, dapat diinstal, dan lolos smoke test setelah instalasi bersih.
- Artefak release tidak mengandung secret dan tidak dikomit tanpa proses release yang disetujui.

## Urutan dependensi

```text
M0 Readiness
  → M1 WebView dasar
    → M2 Kebijakan navigasi/keamanan
      → M3 File dan dokumen Android
        → M4 QA parity Reporter
          → M5 APK internal
```

## Risiko dan keputusan gate

| Risiko | Dampak | Keputusan sebelum lanjut |
| --- | --- | --- |
| Portal web tidak kompatibel dengan Android WebView | Login/navigasi gagal | Reproduksi, dokumentasikan, lalu perbaiki minimal di shell atau web setelah persetujuan. |
| Upload/download tidak didukung WebView | Reporter tidak dapat mengelola bukti/dokumen | Jangan melewati Milestone 3 tanpa solusi dan uji device. |
| Perubahan data QA di produksi | Mengganggu operasi Satgas | Gunakan staging atau data dummy yang disetujui. |
| URL internal terbuka | Risiko akses/pengalaman salah | Perkuat policy Milestone 2 sebelum QA parity. |
| Keystore hilang/bocor | APK release tidak dapat diperbarui/berisiko | Simpan di lokasi aman di luar repository. |

## Definition of done rilis v1

Rilis v1 selesai apabila signed APK Android SILAPPKASAL dapat diinstal, membuka website Reporter yang sama dengan browser, melayani navigasi dan file/document flow utama dengan aman, membatasi host/rute sesuai kebijakan, serta memiliki laporan QA dan checksum rilis.

