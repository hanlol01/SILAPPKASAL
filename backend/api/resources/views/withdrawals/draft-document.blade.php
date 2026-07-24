<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Surat Pernyataan Pencabutan Pengaduan — DRAFT</title>
    <style>
        :root {
            color-scheme: light;
            font-family: "Times New Roman", Times, serif;
            color: #111827;
            background: #e5e7eb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 32px 16px;
        }

        .page {
            position: relative;
            width: min(210mm, 100%);
            min-height: 297mm;
            margin: 0 auto;
            overflow: hidden;
            background: #fff;
            padding: 24mm 22mm;
            box-shadow: 0 12px 35px rgba(15, 23, 42, .16);
        }

        .watermark {
            position: absolute;
            inset: 42% auto auto 50%;
            z-index: 0;
            width: 160%;
            transform: translate(-50%, -50%) rotate(-32deg);
            color: rgba(185, 28, 28, .10);
            font-family: Arial, sans-serif;
            font-size: 56px;
            font-weight: 800;
            letter-spacing: .12em;
            text-align: center;
            pointer-events: none;
        }

        .content {
            position: relative;
            z-index: 1;
        }

        h1 {
            margin: 0;
            font-size: 18px;
            line-height: 1.4;
            text-align: center;
            text-decoration: underline;
            text-transform: uppercase;
        }

        .reference {
            margin: 8px 0 32px;
            font-family: Arial, sans-serif;
            font-size: 11px;
            text-align: center;
        }

        p,
        td {
            font-size: 14px;
            line-height: 1.7;
        }

        table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }

        td:first-child {
            width: 34%;
            vertical-align: top;
        }

        .reason {
            min-height: 96px;
            margin: 8px 0 22px;
            padding: 12px 14px;
            border: 1px solid #9ca3af;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .signature {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 42px;
        }

        .signature-box {
            min-height: 170px;
            text-align: center;
        }

        .signature-space {
            height: 95px;
            margin-top: 12px;
            border: 1px dashed #9ca3af;
            color: #6b7280;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 95px;
        }

        .name {
            font-weight: 700;
            text-decoration: underline;
        }

        .warning {
            margin-top: 36px;
            padding: 10px 12px;
            border: 2px solid #b91c1c;
            color: #991b1b;
            font-family: Arial, sans-serif;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.5;
            text-align: center;
        }

        @media (max-width: 700px) {
            body {
                padding: 0;
            }

            .page {
                min-height: 100vh;
                padding: 24px 20px;
                box-shadow: none;
            }

            .signature {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            @page {
                size: A4;
                margin: 0;
            }

            body {
                padding: 0;
                background: #fff;
            }

            .page {
                width: 210mm;
                min-height: 297mm;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
<main class="page">
    <div class="watermark" aria-hidden="true">
        DRAFT<br>
        BELUM MERUPAKAN FORMAT RESMI KAMPUS
    </div>

    <article class="content">
        <h1>Surat Pernyataan Pencabutan Pengaduan</h1>
        <p class="reference">Referensi dokumen: {{ $documentReference }}</p>

        <p>Saya yang bertanda tangan di bawah ini:</p>

        <table>
            <tr>
                <td>Nama Pelapor</td>
                <td>: {{ $reporterDisplayName }}</td>
            </tr>
            <tr>
                <td>Nomor Registrasi Pengaduan</td>
                <td>: {{ $registrationNumber }}</td>
            </tr>
            <tr>
                <td>Tanggal Dokumen DRAFT</td>
                <td>: {{ $createdAt?->format('d-m-Y') }}</td>
            </tr>
        </table>

        <p>
            Dengan ini menyatakan bahwa saya mengajukan pencabutan atas Pengaduan dengan nomor
            registrasi tersebut di atas. Permohonan ini saya ajukan secara sadar dan tanpa
            mengurangi kewajiban kampus untuk menjalankan proses verifikasi sesuai kebijakan yang berlaku.
        </p>

        <p><strong>Alasan pencabutan:</strong></p>
        <div class="reason">{{ $reason }}</div>

        <p>Tempat dan tanggal penandatanganan: ________________________________</p>

        <section class="signature">
            <div class="signature-box">
                <strong>Area Meterai</strong>
                <div class="signature-space">Tempel meterai sesuai ketentuan</div>
            </div>
            <div class="signature-box">
                <strong>Tanda Tangan Pelapor</strong>
                <div class="signature-space">Tanda tangan di sini</div>
                <p class="name">{{ $reporterDisplayName }}</p>
            </div>
        </section>

        <aside class="warning">
            DRAFT — BELUM MERUPAKAN FORMAT RESMI KAMPUS.<br>
            Dokumen ini merupakan format sementara untuk submilestone REV-WITHDRAW-01B dan tidak
            boleh dianggap sebagai surat resmi yang telah disahkan kampus.
        </aside>
    </article>
</main>
</body>
</html>
