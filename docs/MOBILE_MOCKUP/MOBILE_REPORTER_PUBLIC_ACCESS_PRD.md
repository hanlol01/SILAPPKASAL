# PRD — Mobile Reporter Public Access

## Tujuan

Mendesain tiga halaman akses awal aplikasi Mobile Reporter dengan perilaku setara web produksi saat ini:

1. Login Reporter.
2. Pendaftaran akun Reporter.
3. Lacak pengaduan anonim menggunakan kode pelacakan.

Ini adalah fase desain pertama. Tidak mencakup dashboard atau penanganan pengaduan setelah login.

## Prinsip parity

- Mobile mengubah layout desktop menjadi pola mobile, **bukan** mengubah aturan produk, field, validasi, tujuan tautan, atau data backend.
- Semua dropdown memuat data dari backend. Jangan membuat daftar universitas, fakultas, program studi, atau kategori secara hard-code.
- Akses berhasil selalu ditentukan oleh respons backend dan role akun. Mobile Reporter tidak boleh membuka halaman role internal.
- Gunakan Bahasa Indonesia sebagai bahasa awal; pertahankan titik ekstensi untuk perubahan bahasa bila UI web menyediakannya.

## Halaman 1 — Login (`/login`)

### Tampilan

- Branding SILAPPKASAL dan konteks layanan pelaporan kampus yang aman/rahasia.
- Judul: **Masuk**.
- Deskripsi masuk.
- Field identifier berlabel **Email, NIM, atau NIP**.
- Field **Kata Sandi** dengan show/hide password.
- Checkbox **Ingat saya**.
- Tombol utama **Masuk**; saat memproses tampilkan state loading dan cegah submit ganda.
- Tautan: **Belum punya akun? Daftar sebagai Pelapor** menuju Register.
- Tautan: **Lacak pengaduan anonim** menuju Lacak Anonim.

### Perilaku

- Field identifier dan kata sandi wajib diisi.
- Kesalahan login ditampilkan aman di area form tanpa membocorkan detail akun.
- Setelah autentikasi, backend menentukan tujuan aman berdasarkan role. Untuk Reporter, tujuan adalah Portal Reporter.
- Jika akun memerlukan penyelesaian pendaftaran, ikuti respons backend ke status pendaftaran; jangan memaksa masuk ke portal.

## Halaman 2 — Register Reporter (`/register`)

### Tampilan dan field

Form pendaftaran reporter memiliki field berikut:

| Field | Wajib | Sumber/nilai |
| --- | --- | --- |
| Nama Lengkap | Ya | Input teks. |
| NIM / NPM | Ya | Input teks. |
| Alamat Email | Ya | Input email. |
| Nomor Telepon | Ya | Input nomor telepon sesuai validasi backend. |
| Universitas | Ya | Dropdown backend. Untuk canvas contoh pilih **Universitas STAI Sebelas April**. |
| Fakultas | Opsional dan kondisional | Muncul bila universitas memiliki fakultas. Memilih fakultas mereset Program Studi. |
| Program Studi | Ya | Dropdown backend, aktif setelah universitas dipilih. Untuk canvas contoh pilih **Ekonomi Syariah**. |
| Kata Sandi | Ya | Password dengan show/hide. |
| Konfirmasi Kata Sandi | Ya | Harus sama dengan Kata Sandi. |

Tombol: **Kirim Pendaftaran** dan tautan **Kembali ke login**.

### Perilaku

- Saat data universitas dimuat, trigger menampilkan **Memuat universitas...**. Saat belum dipilih, tampilkan **Pilih universitas**.
- Program Studi disabled sampai Universitas dipilih; loading dan empty state harus terlihat jelas.
- Pada contoh desain, alur dropdown harus terlihat: pilih Universitas STAI Sebelas April → Program Studi Ekonomi Syariah.
- Ketika pendaftaran sukses, gantikan form dengan halaman sukses: judul/penjelasan sukses, **nomor pendaftaran**, serta tombol kembali ke Login.
- Tampilkan validasi field di bawah field terkait dan error backend yang aman.

## Halaman 3 — Lacak Pengaduan Anonim (`/track`)

### Tampilan

- Branding ringan dan tautan **Daftar** serta Login.
- Judul: **Lacak Pengaduan**.
- Deskripsi singkat tentang pelacakan aman.
- Field **Kode Pelacakan** dengan placeholder `XXXX-XXXX-XXXX-XXXX`.
- Input otomatis mengubah huruf menjadi kapital.
- Tombol utama **Lacak**, dengan state loading **Melacak...**.

### Validasi dan hasil

- Kode wajib dan mengikuti pola 16–32 karakter kapital, angka, atau tanda hubung.
- Bila salah/tidak ditemukan, tampilkan error pada field tanpa mengungkap data lain.
- Bila valid, tampilkan hanya data aman: **Nomor Registrasi**, **Status**, dan **Dikirim pada**.
- Jangan tampilkan identitas pelapor, kronologi, informasi terlapor, atau dokumen dari halaman pelacakan anonim.

## State yang wajib dibuat di mockup

- Default.
- Field validation error.
- Request loading/disabled submit.
- API error aman.
- Register sukses dengan nomor pendaftaran.
- Lacak anonim sukses dengan nomor registrasi/status/tanggal.
- Dropdown loading, enabled, selected, dan empty/error.

## Acceptance checklist desain

- Ketiga halaman dapat diakses satu sama lain melalui tautan yang tepat.
- Semua field dan label di atas ada.
- Register menampilkan contoh pilihan Universitas STAI Sebelas April dan Ekonomi Syariah.
- Desain tidak memasukkan menu/fitur Petugas, Investigator, Pimpinan, atau Administrator.
- Desain menjaga privasi: tidak ada penjelasan yang mengundang pengguna mengisi detail kejadian pada Login/Register/Track.

