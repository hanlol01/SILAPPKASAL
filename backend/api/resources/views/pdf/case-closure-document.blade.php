<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 2.3cm 2.4cm 2cm; }
        body { font-family: "Times New Roman", Times, serif; font-size: 11pt; line-height: 1.42; color: #000; }
        .title { text-align: center; font-weight: bold; margin: 0; }
        .title-main { font-size: 13pt; text-decoration: underline; }
        .title-sub { font-size: 11pt; margin-top: 4px; }
        .number { text-align: center; margin: 10px 0 20px; }
        p { margin: 0 0 11px; text-align: justify; }
        h3 { font-size: 11pt; margin: 15px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin: 0 0 10px; }
        td { vertical-align: top; padding: 2px 0; }
        .label { width: 39%; }
        .colon { width: 3%; }
        .narrative { white-space: pre-line; }
        .signature { width: 44%; margin-left: 56%; margin-top: 26px; text-align: center; }
        .signature-space { height: 62px; }
        .footer-note { font-size: 9pt; margin-top: 26px; }
    </style>
</head>
<body>
    <p class="title title-main">BERITA ACARA</p>
    <p class="title title-sub">HASIL AKHIR PELAPORAN DUGAAN KEKERASAN SEKSUAL<br>DI {{ $universityName }}</p>
    <p class="number">Nomor: {{ $documentNumber }}</p>

    <p>Pada hari {{ $issuedDate }}, bertempat di {{ $universityAddress }}, telah dibuat Berita Acara Hasil Pelaporan Dugaan Kekerasan Seksual di lingkungan {{ $universityName }}. Dokumen ini merupakan pernyataan resmi bahwa proses penanganan laporan telah mencapai tahap selesai sesuai ketentuan yang berlaku.</p>

    <h3>I. IDENTITAS KASUS</h3>
    <table>
        <tr><td class="label">Nomor Registrasi Laporan</td><td class="colon">:</td><td>{{ $registrationNumber }}</td></tr>
        <tr><td class="label">Nomor Kasus</td><td class="colon">:</td><td>{{ $caseNumber }}</td></tr>
        <tr><td class="label">Tanggal Penerimaan Laporan</td><td class="colon">:</td><td>{{ $receivedDate }}</td></tr>
        <tr><td class="label">Status Kasus</td><td class="colon">:</td><td>Ditutup</td></tr>
    </table>

    <h3>II. RINGKASAN PELAKSANAAN PENANGANAN</h3>
    <p>Penanganan laporan telah dilakukan melalui tahapan operasional yang relevan dan telah dicatat pada sistem SILAPPKASAL. Dokumen ini tidak memuat rincian sensitif pihak-pihak terkait maupun bukti perkara.</p>

    <h3>III. HASIL AKHIR PELAPORAN</h3>
    <table>
        <tr><td class="label">Hasil Akhir</td><td class="colon">:</td><td>{{ $outcome }}</td></tr>
    </table>
    <p class="narrative">{{ $officialStatement }}</p>

    <h3>IV. KESIMPULAN</h3>
    <p class="narrative">{{ $closingExplanation }}</p>

    <h3>V. TINDAK LANJUT</h3>
    <p class="narrative">{{ $followUp ?: 'Tidak terdapat tindak lanjut tambahan yang dicantumkan dalam rangkuman akhir.' }}</p>

    <p>Demikian Berita Acara ini dibuat dengan sebenar-benarnya untuk digunakan sebagaimana mestinya.</p>

    <div class="signature">
        <p style="text-align:center">{{ $universityName }}, {{ $issuedDate }}</p>
        <p style="text-align:center">Satgas PPKS yang Bertanggung Jawab,</p>
        <div class="signature-space"></div>
        <p style="text-align:center; font-weight:bold; text-decoration:underline">{{ $leadName }}</p>
        <p style="text-align:center">NIP/NIK. {{ $leadNip }}</p>
    </div>
    <p class="footer-note">Dokumen resmi ini diterbitkan melalui SILAPPKASAL dan dapat diverifikasi melalui nomor dokumen serta nomor kasus di atas.</p>
</body>
</html>
