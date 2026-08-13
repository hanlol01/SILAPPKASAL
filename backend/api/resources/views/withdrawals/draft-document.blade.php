<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Pernyataan Permohonan Penghentian Penanganan Laporan</title>
    <style>
        @page {
            margin: 25.4mm;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: #000;
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            line-height: 1.5;
        }

        p { margin: 0; }

        .title,
        .number,
        .signature { text-align: center; }

        .title {
            font-weight: 700;
            line-height: 1.5;
        }

        .number { line-height: 1.5; }
        .blank { height: 18pt; }
        .field { line-height: 1.5; }
        .body-copy { text-align: justify; }
        .signature-gap { height: 18pt; }
        .stamp-gap { height: 36pt; }
    </style>
</head>
<body>
    <p class="title">SURAT PERNYATAAN</p>
    <p class="title">PERMOHONAN PENGHENTIAN PENANGANAN LAPORAN</p>
    <p class="number"><strong>Nomor</strong> : {{ $documentNumber }}</p>

    <div class="blank"></div>

    <p>Yang bertanda tangan di bawah ini :</p>
    <p class="field"><strong>Nama</strong> : {{ $reporterAccountName }}</p>
    <p class="field"><strong>NIM</strong> : {{ $reporterNim }}</p>
    <p class="field"><strong>Status</strong> : {{ $reporterStatus }}</p>
    <p class="field"><strong>Program Studi/Unit Kerja</strong> : {{ $reporterProgram }}</p>
    <p class="field"><strong>Alamat</strong> : {{ $reporterAddress }}</p>
    <p class="field"><strong>Nomor Telepon</strong> : {{ $reporterPhone }}</p>

    <p>Selanjutnya dalam surat pernyataan ini disebut sebagai <strong>Pelapor</strong>.</p>
    <p>Dengan ini menyatakan bahwa saya merupakan pelapor atas dugaan kasus kekerasan seksual yang telah saya sampaikan kepada Satuan Tugas Pencegahan dan Penanganan Kekerasan di Lingkungan Perguruan Tinggi dengan :</p>
    <p class="field">Nomor Registrasi Laporan : {{ $reportNumber }}</p>
    <p class="field">Tanggal Pelaporan : {{ $reportDate }}</p>

    <p class="body-copy">Setelah mempertimbangkan berbagai aspek dan atas kehendak saya sendiri, tanpa adanya tekanan, intimidasi, paksaan, ataupun pengaruh dari pihak mana pun, saya mengajukan <strong>permohonan penghentian penanganan laporan</strong> tersebut.</p>
    <p class="body-copy">Demikian surat pernyataan ini saya buat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>

    <div class="blank"></div>

    <section class="signature">
        <p>{{ $reporterAddress }}, {{ $issuedDate }}</p>
        <p>Yang Membuat Pernyataan,</p>
        <div class="signature-gap"></div>
        <p>Tempel Materai Rp10.000</p>
        <div class="stamp-gap"></div>
        <p>{{ $reporterName }}</p>
    </section>
</body>
</html>
