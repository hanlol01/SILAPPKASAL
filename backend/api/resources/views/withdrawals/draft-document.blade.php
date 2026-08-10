<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Surat Pernyataan Permohonan Penghentian Penanganan Laporan</title>
    <style>
        :root {
            color-scheme: light;
            font-family: "Times New Roman", Times, serif;
            color: #111;
            background: #e5e7eb;
        }

        * { box-sizing: border-box; }

        body { margin: 0; padding: 32px 16px; }

        .page {
            width: min(210mm, 100%);
            min-height: 297mm;
            margin: 0 auto;
            padding: 25.4mm;
            background: #fff;
            box-shadow: 0 12px 35px rgba(15, 23, 42, .16);
        }

        h1 {
            margin: 0;
            font-size: 14pt;
            line-height: 1.25;
            text-align: center;
            text-transform: uppercase;
        }

        .number { margin: 9pt 0 27pt; text-align: center; font-size: 12pt; }
        p { margin: 0 0 10pt; font-size: 12pt; line-height: 1.5; text-align: justify; }
        .field { margin-bottom: 7pt; text-align: left; }
        .spacer { height: 16pt; }
        .signature { margin-top: 30pt; text-align: center; }
        .signature-space { height: 88pt; }

        @media (max-width: 700px) {
            body { padding: 0; }
            .page { min-height: 100vh; padding: 24px 20px; box-shadow: none; }
        }

        @media print {
            @page { size: A4; margin: 25.4mm; }
            body { padding: 0; background: #fff; }
            .page { width: auto; min-height: auto; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
<main class="page">
    <h1>SURAT PERNYATAAN</h1>
    <h1>PERMOHONAN PENGHENTIAN PENANGANAN LAPORAN</h1>
    <p class="number">Nomor: {{ $documentNumber }}</p>

    <p>Yang bertandatangan di bawah ini:</p>
    <p class="field">Nama: ....................................................................................</p>
    <p class="field">NIM: ......................................................................................</p>
    <p class="field">Status: Mahasiswa/Dosen/Tenaga Kependidikan/Lainnya</p>
    <p class="field">Program Studi/Unit Kerja: .................................................................</p>
    <p class="field">Alamat: ....................................................................................</p>
    <p class="field">Nomor Telepon: .............................................................................</p>

    <p>Selanjutnya dalam surat pernyataan ini disebut sebagai Pelapor.</p>
    <p>Dengan ini menyatakan bahwa saya merupakan pelapor atas dugaan kasus kekerasan seksual yang telah saya sampaikan kepada Satuan Tugas Pencegahan dan Penanganan Kekerasan di Lingkungan Perguruan Tinggi dengan:</p>
    <p class="field">Nomor Registrasi Laporan: .................................................................</p>
    <p class="field">Tanggal Pelaporan: ......................................................................</p>

    <p>Setelah mempertimbangkan berbagai aspek dan atas kehendak saya sendiri, tanpa adanya tekanan, intimidasi, paksaan, ataupun pengaruh dari pihak mana pun, saya mengajukan permohonan penghentian penanganan laporan tersebut.</p>
    <p>Demikian surat pernyataan ini saya buat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>

    <section class="signature">
        <p>................................................................, .................... 20....</p>
        <p>Yang Membuat Pernyataan,</p>
        <div class="signature-space">Materai Rp10.000</div>
        <p>................................................................</p>
        <p>Nama Pelapor</p>
    </section>
</main>
</body>
</html>
