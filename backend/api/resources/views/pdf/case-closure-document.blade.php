<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 2.54cm; }
        body {
            color: #000;
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            line-height: 1.5;
        }
        p { margin: 0 0 8pt; text-align: justify; }
        .title {
            font-weight: bold;
            line-height: 1.25;
            margin: 0;
            text-align: center;
        }
        .title-main { font-size: 14pt; }
        .title-sub { font-size: 12pt; margin-top: 5pt; }
        .number { font-weight: bold; margin: 5pt 0 24pt; text-align: center; }
        .section-title { font-weight: bold; margin: 5pt 0 2pt; }
        .intro { margin-bottom: 4pt; }
        .identity-list { margin: 0 0 4pt 19pt; padding-left: 14pt; }
        .identity-list li { margin: 0 0 1pt; padding-left: 3pt; }
        .identity-label { display: inline-block; width: 210pt; }
        .steps { margin: 0 0 4pt 22pt; padding-left: 18pt; }
        .steps > li { margin: 0 0 1pt; padding-left: 3pt; text-align: justify; }
        .principles { list-style-type: disc; margin: 1pt 0 0 24pt; padding-left: 16pt; }
        .principles li { margin: 0; }
        .page-break { page-break-before: always; }
        .signature-date { margin-top: 12pt; text-align: right; }
        .signature { margin-top: 4pt; text-align: center; }
        .signature p { margin: 0; text-align: center; }
        .signature-space { height: 55pt; }
        .signer-name { font-weight: bold; }
    </style>
</head>
<body>
    <p class="title title-main">BERITA ACARA</p>
    <p class="title title-sub">HASIL AKHIR REKAPITULASI PELAPORAN DUGAAN KASUS KEKERASAN<br>
        SEKSUAL DI {{ $universityName }}    </p>
    <p class="number">Nomor: {{ $documentNumber }}</p>

    <p class="intro">Pada hari ini, {{ $issuedDay }} tanggal {{ $issuedDateNumber }} bulan {{ $issuedMonth }} tahun {{ $issuedYear }} bertempat di {{ $universityAddress }} kami yang bertanda tangan di bawah ini merupakan Tim/Satuan Tugas Pencegahan dan Penanganan Kekerasan di Lingkungan ({{ $universityName }} telah melaksanakan rekapitulasi dan penelaahan terhadap seluruh dokumen pelaporan dugaan kekerasan seksual yang diterima selama proses penanganan kasus.</p>
    <p>Berdasarkan hasil pemeriksaan administrasi, telaah dokumen, klarifikasi, serta pencatatan yang telah dilakukan sesuai dengan ketentuan peraturan perundang-undangan dan kebijakan internal perguruan tinggi, diperoleh hasil sebagai berikut:</p>

    <p class="section-title">I. Identitas Kasus</p>
    <ul class="identity-list">
        <li><span class="identity-label">Nomor Registrasi Laporan</span>: {{ $registrationNumber }}</li>
        <li><span class="identity-label">Tanggal Penerimaan Laporan</span>: {{ $receivedDate }}</li>
        <li><span class="identity-label">Status Kasus</span>: {{ $caseStatus }}</li>
        <li>Kerahasiaan Identitas Korban dan Terlapor dijaga sesuai ketentuan yang berlaku.</li>
    </ul>

    <p class="section-title">II. Ringkasan Pelaksanaan Penanganan</p>
    <ol class="steps">
        <li>Laporan telah diterima dan diregistrasi sesuai mekanisme yang berlaku.</li>
        <li>Tim telah melakukan verifikasi administrasi terhadap dokumen pendukung.</li>
        <li>Klarifikasi kepada pihak-pihak terkait telah dilaksanakan sesuai kebutuhan.</li>
        <li>Pendampingan kepada pelapor/korban telah difasilitasi sesuai ketentuan.</li>
        <li>Seluruh tahapan penanganan dilaksanakan dengan memperhatikan prinsip:
            <ul class="principles">
                <li>Kerahasiaan;</li>
                <li>Keadilan;</li>
                <li>Non-diskriminasi;</li>
                <li>Perlindungan korban;</li>
                <li>Objektivitas;</li>
                <li>Akuntabilitas.</li>
            </ul>
        </li>
    </ol>

    <p class="section-title">III. Hasil Rekapitulasi Pelaporan</p>
    <p>Berdasarkan hasil rekapitulasi pelaksanaan penanganan maka kasus ini dinyatakan <strong>SELESAI</strong></p>

    <div class="page-break"></div>

    <p class="section-title">IV. Kesimpulan</p>
    <p>Berdasarkan hasil rekapitulasi dokumen, pemeriksaan administrasi, dan proses penanganan yang telah dilakukan, maka dapat disimpulkan bahwa:</p>
    <ol class="steps">
        <li>Seluruh tahapan penanganan laporan telah dilaksanakan sesuai dengan Standar Operasional Prosedur (SOP) serta ketentuan yang berlaku di lingkungan perguruan tinggi.</li>
        <li>Rekapitulasi ini merupakan dokumen administrasi yang memuat hasil pencatatan proses penanganan laporan dan tidak dimaksudkan sebagai putusan hukum.</li>
        <li>Apabila dalam proses selanjutnya ditemukan bukti baru atau informasi tambahan yang relevan, maka penanganan dapat dilakukan sesuai mekanisme yang berlaku.</li>
        <li>Dokumen ini menjadi bagian dari arsip resmi Satuan Tugas sebagai bentuk akuntabilitas pelaksanaan penanganan laporan.</li>
    </ol>

    <p class="section-title">V. Rekomendasi</p>
    <p>Sebagai tindak lanjut dari hasil rekapitulasi pelaporan, Tim merekomendasikan:</p>
    <ol class="steps">
        <li>Penyimpanan seluruh dokumen secara aman dengan tetap menjaga kerahasiaan identitas seluruh pihak.</li>
        <li>Pelaksanaan monitoring terhadap tindak lanjut rekomendasi yang telah ditetapkan.</li>
        <li>Penguatan upaya pencegahan melalui kegiatan edukasi, sosialisasi, dan peningkatan kapasitas sivitas akademika.</li>
    </ol>

    <p>Demikian Berita Acara Hasil Akhir Rekapitulasi Pelaporan Dugaan Kasus Kekerasan Seksual ini dibuat dengan sebenar-benarnya untuk dipergunakan sebagaimana mestinya.</p>

    <p class="signature-date">{{ $universityAddress }}. {{ $issuedDateLong }}</p>
    <p>&nbsp;</p>
    <div class="signature">
        <p>Mengetahui,</p>
        <p>Ketua Satuan Tugas Pencegahan dan Penanganan Kekerasan</p>
        <p>{{ $universityName }}</p>
        <div class="signature-space"></div>
        <p class="signer-name">{{ $signerName }}</p>
        <p>NIP/NIK {{ $signerIdentityNumber }}</p>
    </div>
</body>
</html>
