<?php

namespace App\Services;

use RuntimeException;
use setasign\Fpdi\Fpdi;
use Throwable;

class WithdrawalDraftPdfConverter
{
    private const TEMPLATE_PATH = 'templates/withdrawals/DRAFT.pdf';

    /** @param array<string, string> $replacements */
    public function convert(array $replacements): string
    {
        $templatePath = resource_path(self::TEMPLATE_PATH);

        if (! is_file($templatePath)) {
            throw new RuntimeException('Withdrawal draft PDF template is unavailable.');
        }

        try {
            $pdf = new Fpdi('P', 'mm', 'A4');
            $pdf->SetAutoPageBreak(false);
            $pdf->SetCompression(false);
            $pageCount = $pdf->setSourceFile($templatePath);

            if ($pageCount !== 1) {
                throw new RuntimeException('Withdrawal draft PDF template must have one page.');
            }

            $templateId = $pdf->importPage(1);
            $templateSize = $pdf->getTemplateSize($templateId);
            $pdf->AddPage($templateSize['orientation'], [$templateSize['width'], $templateSize['height']]);
            $pdf->useTemplate($templateId);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFillColor(255, 255, 255);

            $this->writeValues($pdf, $replacements);
            $output = $pdf->Output('S');
        } catch (Throwable $exception) {
            throw new RuntimeException('Withdrawal draft PDF could not be prepared.', previous: $exception);
        }

        if (! is_string($output) || ! str_starts_with($output, '%PDF-')) {
            throw new RuntimeException('Withdrawal draft PDF was not created.');
        }

        return $output;
    }

    /** @param array<string, string> $replacements */
    private function writeValues(Fpdi $pdf, array $replacements): void
    {
        $this->writeValue($pdf, $this->value($replacements, 'generate_system'), 94.5, 44.0, 93.0);
        $this->writeValue($pdf, $this->value($replacements, 'nama_akun_pelapor'), 78.0, 70.0, 110.0);
        $this->writeValue($pdf, $this->value($replacements, 'nim_pelapor'), 78.0, 77.3, 110.0);
        $this->writeValue($pdf, $this->value($replacements, 'status_akun_pelapor'), 78.0, 84.6, 110.0);
        $this->writeValue($pdf, $this->value($replacements, 'program_studi_pelapor'), 78.0, 91.9, 110.0);
        $this->writeValue($pdf, $this->value($replacements, 'alamat_pelapor'), 78.0, 99.2, 110.0);
        $this->writeValue($pdf, $this->value($replacements, 'nomor_telepon_pelapor'), 78.0, 106.5, 110.0);
        $this->writeValue($pdf, $this->value($replacements, 'nomor_laporan'), 91.0, 143.4, 94.0);
        $this->writeValue($pdf, $this->value($replacements, 'tanggal_pelaporan'), 91.0, 151.0, 94.0);
        $this->writeCenteredValue(
            $pdf,
            $this->value($replacements, 'alamat_pelapor').', '.$this->value($replacements, 'hari, tanggal bulan tahun'),
            52.0,
            206.7,
            106.0,
        );
        $this->writeCenteredValue($pdf, $this->value($replacements, 'nama_pelapor'), 65.0, 256.7, 80.0);
    }

    /** @param array<string, string> $replacements */
    private function value(array $replacements, string $key): string
    {
        if (! array_key_exists($key, $replacements)) {
            throw new RuntimeException('Withdrawal draft replacement data is incomplete.');
        }

        return $this->toPdfText((string) $replacements[$key]);
    }

    private function writeValue(Fpdi $pdf, string $value, float $x, float $baseline, float $width): void
    {
        $fontSize = $this->fittedFontSize($pdf, $value, $width);
        $height = ($fontSize * 0.3528) + 1.8;

        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($x, $baseline - $height, $width, $height + 1.5, 'F');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Times', '', $fontSize);
        $pdf->Text($x, $baseline, $value);
    }

    private function writeCenteredValue(Fpdi $pdf, string $value, float $x, float $baseline, float $width): void
    {
        $fontSize = $this->fittedFontSize($pdf, $value, $width);
        $height = ($fontSize * 0.3528) + 1.8;

        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($x, $baseline - $height, $width, $height + 1.5, 'F');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Times', '', $fontSize);
        $pdf->SetXY($x, $baseline - $height);
        $pdf->Cell($width, $height, $value, 0, 0, 'C');
    }

    private function fittedFontSize(Fpdi $pdf, string $value, float $width): float
    {
        for ($fontSize = 12.0; $fontSize >= 8.0; $fontSize -= 0.5) {
            $pdf->SetFont('Times', '', $fontSize);

            if ($pdf->GetStringWidth($value) <= $width) {
                return $fontSize;
            }
        }

        return 8.0;
    }

    private function toPdfText(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);

        return $converted === false ? '?' : $converted;
    }
}
