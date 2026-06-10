<?php

namespace Database\Seeders;

use App\Models\CampusStatus;
use App\Models\CaseStatus;
use App\Models\EscalationType;
use App\Models\EvidenceType;
use App\Models\InvestigationStatus;
use App\Models\LocationType;
use App\Models\NotificationType;
use App\Models\PriorityLevel;
use App\Models\RecommendationStatus;
use App\Models\RecoveryType;
use App\Models\Relation;
use App\Models\ReportCategory;
use App\Models\ReportType;
use App\Models\RiskLevel;
use App\Models\SanctionType;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedReportCategories();
        $this->seedSimple(ReportType::class, [
            ['RTYP-01', 'open', 'Pelapor mengungkap identitas. Satgas dapat menghubungi langsung.'],
            ['RTYP-02', 'confidential', 'Identitas pelapor dilindungi dan hanya pihak tertentu yang mengetahui.'],
            ['RTYP-03', 'anonymous', 'Identitas tersembunyi dan akses via kode pelacakan.'],
        ]);
        $this->seedSimple(EvidenceType::class, [
            ['EVID-01', 'Foto', 'Foto bukti kejadian, screenshot, hasil tangkap layar.'],
            ['EVID-02', 'Video', 'Rekaman video sebagai bukti.'],
            ['EVID-03', 'Dokumen', 'Surat, dokumen pendukung, transkrip.'],
            ['EVID-04', 'Tangkapan Layar', 'Screenshot chat, email, media sosial.'],
        ]);
        $this->seedCaseStatuses();
        $this->seedSimple(InvestigationStatus::class, [
            ['INVS-01', 'planning', 'Satgas menyusun rencana investigasi.'],
            ['INVS-02', 'evidence_collection', 'Mengumpulkan bukti fisik dan digital.'],
            ['INVS-03', 'victim_interview', 'Wawancara korban oleh petugas terlatih.'],
            ['INVS-04', 'witness_interview', 'Wawancara saksi langsung dan tidak langsung.'],
            ['INVS-05', 'respondent_interview', 'Wawancara terlapor.'],
            ['INVS-06', 'evidence_analysis', 'Analisis dokumen, rekaman, chat, email, media sosial.'],
            ['INVS-07', 'report_drafting', 'Penyusunan BAP dan laporan investigasi.'],
            ['INVS-08', 'completed', 'Investigasi selesai, siap untuk rekomendasi.'],
        ]);
        $this->seedSimple(RecommendationStatus::class, [
            ['RECS-01', 'drafting', 'Satgas sedang menyusun rekomendasi.'],
            ['RECS-02', 'internal_review', 'Rekomendasi direview oleh sesama Satgas.'],
            ['RECS-03', 'submitted_to_leader', 'Rekomendasi diajukan ke pimpinan PT.'],
            ['RECS-04', 'accepted', 'Rekomendasi diterima oleh pimpinan.'],
            ['RECS-05', 'partially_accepted', 'Rekomendasi diterima dengan modifikasi.'],
            ['RECS-06', 'rejected', 'Rekomendasi ditolak, perlu revisi.'],
            ['RECS-07', 'revised', 'Rekomendasi direvisi setelah feedback.'],
        ]);
        $this->seedNotificationTypes();
        $this->seedSimple(RiskLevel::class, [
            ['RISK-01', 'low', 'Tidak ada ancaman langsung terhadap keselamatan korban.'],
            ['RISK-02', 'medium', 'Korban mengalami tekanan psikologis dan ada potensi eskalasi.'],
            ['RISK-03', 'high', 'Ancaman keselamatan aktif atau potensi trauma berat.'],
        ]);
        $this->seedSimple(PriorityLevel::class, [
            ['PRIO-01', 'urgent', 'Kekerasan berat, ancaman keselamatan aktif, korban anak.'],
            ['PRIO-02', 'high', 'Kekerasan fisik, potensi eskalasi.'],
            ['PRIO-03', 'normal', 'Kasus standar, membutuhkan penanganan reguler.'],
            ['PRIO-04', 'low', 'Laporan informasional, tidak ada urgensi langsung.'],
        ]);
        $this->seedSimple(CampusStatus::class, [
            ['CAMP-01', 'mahasiswa', 'Mahasiswa aktif.'],
            ['CAMP-02', 'dosen', 'Dosen/tenaga pengajar.'],
            ['CAMP-03', 'tendik', 'Tenaga kependidikan.'],
            ['CAMP-04', 'alumni', 'Alumni.'],
            ['CAMP-05', 'pihak_luar', 'Pihak luar yang terkait lingkungan kampus.'],
        ]);
        $this->seedSimple(Relation::class, [
            ['REL-01', 'dosen_mahasiswa', 'Dosen - Mahasiswa.'],
            ['REL-02', 'sesama_mahasiswa', 'Sesama mahasiswa.'],
            ['REL-03', 'atasan_bawahan', 'Atasan - Bawahan struktural.'],
            ['REL-04', 'sesama_pegawai', 'Sesama pegawai/tendik.'],
            ['REL-05', 'pembimbing', 'Pembimbing akademik/skripsi.'],
            ['REL-06', 'organisasi', 'Dalam konteks organisasi kampus.'],
            ['REL-07', 'tidak_dikenal', 'Tidak mengenal secara personal.'],
            ['REL-99', 'lainnya', 'Relasi lain.'],
        ]);
        $this->seedSimple(LocationType::class, [
            ['LOC-01', 'dalam_kampus', 'Di dalam area kampus.'],
            ['LOC-02', 'luar_kampus_terkait', 'Di luar kampus, terkait kegiatan kampus.'],
            ['LOC-03', 'luar_kampus_tidak_terkait', 'Di luar kampus, tidak terkait kegiatan kampus.'],
            ['LOC-04', 'online', 'Melalui media digital / online.'],
        ]);
        $this->seedSimple(EscalationType::class, [
            ['ESC-01', 'internal', 'Eskalasi ke pimpinan kampus.'],
            ['ESC-02', 'kepolisian', 'Eskalasi ke kepolisian.'],
            ['ESC-03', 'lpsk', 'Eskalasi ke LPSK.'],
            ['ESC-04', 'rumah_sakit', 'Eskalasi ke rumah sakit.'],
            ['ESC-05', 'psikolog', 'Eskalasi ke psikolog profesional.'],
            ['ESC-06', 'bantuan_hukum', 'Eskalasi ke lembaga bantuan hukum.'],
        ]);
        $this->seedSimple(RecoveryType::class, [
            ['RCV-01', 'psychological', 'Pendampingan psikologis.'],
            ['RCV-02', 'legal', 'Pendampingan hukum.'],
            ['RCV-03', 'academic', 'Pendampingan akademik.'],
            ['RCV-04', 'medical', 'Pendampingan medis.'],
        ]);
        $this->seedSimple(SanctionType::class, [
            ['SANC-01', 'warning', 'Peringatan tertulis.'],
            ['SANC-02', 'suspension', 'Skorsing/pemberhentian sementara.'],
            ['SANC-03', 'demotion', 'Penurunan jabatan.'],
            ['SANC-04', 'expulsion', 'Pemberhentian/dikeluarkan.'],
            ['SANC-05', 'restriction', 'Pembatasan akses/kegiatan.'],
            ['SANC-06', 'obligation', 'Kewajiban tertentu.'],
            ['SANC-07', 'other', 'Sanksi lain sesuai peraturan PT.'],
        ]);
    }

    private function seedReportCategories(): void
    {
        $rows = [
            ['RCAT-01', 'Pelecehan seksual verbal', 'Ucapan bernuansa seksual yang tidak diinginkan', 'Komentar seksual, lelucon seksual, siulan'],
            ['RCAT-02', 'Pelecehan seksual non-verbal', 'Gestur atau tindakan non-fisik bernuansa seksual', 'Gesture seksual, tatapan seksual, eksibisionisme'],
            ['RCAT-03', 'Pelecehan seksual fisik', 'Kontak fisik bernuansa seksual tanpa persetujuan', 'Sentuhan tidak diinginkan, meraba, mencium paksa'],
            ['RCAT-04', 'Pemaksaan hubungan seksual', 'Pemaksaan aktivitas seksual', 'Pemerkosaan, percobaan pemerkosaan'],
            ['RCAT-05', 'Kekerasan seksual berbasis digital', 'Kekerasan seksual melalui media digital', 'Penyebaran konten intim tanpa izin, sexting paksa, cyberstalking'],
            ['RCAT-06', 'Pemaksaan kontrasepsi', 'Pemaksaan terkait alat kontrasepsi', 'Memaksa penggunaan/menolak kontrasepsi'],
            ['RCAT-07', 'Pemaksaan sterilisasi', 'Pemaksaan prosedur sterilisasi', 'Memaksa prosedur sterilisasi tanpa persetujuan'],
            ['RCAT-08', 'Pemaksaan perkawinan', 'Memaksa pernikahan', 'Memaksa menikah karena tekanan'],
            ['RCAT-09', 'Penyiksaan seksual', 'Penyiksaan melibatkan organ seksual', 'Penyiksaan bernuansa seksual'],
            ['RCAT-10', 'Eksploitasi seksual', 'Memanfaatkan posisi untuk kepentingan seksual', 'Penyalahgunaan kekuasaan untuk hubungan seksual'],
            ['RCAT-11', 'Perbudakan seksual', 'Pemaksaan aktivitas seksual berulang', 'Memaksa aktivitas seksual secara sistematis'],
            ['RCAT-99', 'Lainnya', 'Kategori yang tidak tercakup di atas', 'Harus diisi deskripsi manual'],
        ];

        foreach ($rows as $index => [$code, $name, $description, $examples]) {
            ReportCategory::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'examples' => $examples,
                    'legal_basis' => 'Permendikbudristek No. 30 Tahun 2021 dan UU TPKS',
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }

    private function seedCaseStatuses(): void
    {
        $rows = [
            ['CSTS-01', 'submitted', 'Laporan baru masuk ke sistem.', 1, 'Pelaporan', false, 'system', ['under_review']],
            ['CSTS-02', 'under_review', 'Admin sedang memeriksa kelengkapan laporan.', 2, 'Verifikasi', false, 'admin', ['need_info', 'forwarded', 'rejected']],
            ['CSTS-03', 'need_info', 'Admin membutuhkan informasi tambahan.', 2, 'Verifikasi', false, 'admin', ['under_review']],
            ['CSTS-04', 'rejected', 'Laporan ditolak dengan alasan tertulis.', 2, 'Verifikasi', true, 'admin', []],
            ['CSTS-05', 'forwarded', 'Laporan diteruskan ke Satgas PPKS.', 2, 'Verifikasi', false, 'admin', ['assessment']],
            ['CSTS-06', 'assessment', 'Satgas melakukan asesmen risiko.', 3, 'Asesmen', false, 'satgas_ppks', ['investigation']],
            ['CSTS-07', 'investigation', 'Proses investigasi sedang berjalan.', 4, 'Investigasi', false, 'satgas_ppks', ['mediation', 'recommendation']],
            ['CSTS-08', 'mediation', 'Proses mediasi opsional.', 4, 'Investigasi', false, 'satgas_ppks', ['recommendation']],
            ['CSTS-09', 'recommendation', 'Satgas menyusun rekomendasi.', 5, 'Rekomendasi', false, 'satgas_ppks', ['decision']],
            ['CSTS-10', 'decision', 'Menunggu keputusan pimpinan PT.', 6, 'Keputusan', false, 'leader', ['decided']],
            ['CSTS-11', 'decided', 'Keputusan pimpinan telah diterbitkan.', 6, 'Keputusan', false, 'leader', ['recovery']],
            ['CSTS-12', 'recovery', 'Tahap pendampingan korban.', 7, 'Monitoring', false, 'satgas_ppks', ['monitoring']],
            ['CSTS-13', 'monitoring', 'Monitoring pasca kasus.', 7, 'Monitoring', false, 'satgas_ppks', ['closed']],
            ['CSTS-14', 'closed', 'Kasus selesai.', 7, 'Monitoring', true, 'satgas_ppks', []],
            ['CSTS-15', 'escalated', 'Kasus dieskalasi ke pihak luar.', 0, 'Eskalasi', false, 'satgas_ppks', []],
        ];

        foreach ($rows as $index => [$code, $name, $description, $stage, $stageName, $terminal, $role, $transitions]) {
            CaseStatus::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'workflow_stage' => $stage,
                    'stage_name' => $stageName,
                    'is_terminal' => $terminal,
                    'responsible_role' => $role,
                    'valid_transitions' => $transitions,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }

    private function seedNotificationTypes(): void
    {
        $rows = [
            ['NOTIF-01', 'Laporan baru masuk', 'Admin', 'both', 'report.new', 'mvp_extended'],
            ['NOTIF-02', 'Konfirmasi laporan diterima', 'Pelapor', 'whatsapp', 'report.confirmed', 'mvp_extended'],
            ['NOTIF-03', 'Status kasus berubah', 'Pelapor', 'whatsapp', 'case.status_changed', 'mvp_extended'],
            ['NOTIF-04', 'Info tambahan dibutuhkan', 'Pelapor', 'whatsapp', 'report.need_info', 'mvp_extended'],
            ['NOTIF-05', 'Laporan ditolak', 'Pelapor', 'whatsapp', 'report.rejected', 'mvp_extended'],
            ['NOTIF-06', 'Kasus di-forward ke Satgas', 'Satgas', 'both', 'case.forwarded', 'mvp_extended'],
            ['NOTIF-07', 'Pesan baru di messaging', 'Pelapor/Satgas', 'both', 'message.new', 'mvp_extended'],
            ['NOTIF-08', 'SLA warning (75%)', 'Admin/Satgas', 'both', 'sla.warning', 'post_mvp'],
            ['NOTIF-09', 'SLA breach', 'Admin + Super Admin', 'both', 'sla.breach', 'post_mvp'],
            ['NOTIF-10', 'Keputusan institusi dikeluarkan', 'Pelapor', 'whatsapp', 'case.decided', 'mvp_extended'],
            ['NOTIF-11', 'Kasus ditutup', 'Pelapor', 'whatsapp', 'case.closed', 'mvp_extended'],
        ];

        foreach ($rows as $index => [$code, $name, $recipient, $channel, $template, $classification]) {
            NotificationType::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $name,
                    'channel' => $channel,
                    'template_key' => $template,
                    'recipient_role' => $recipient,
                    'classification' => $classification,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }

    /**
     * @param class-string<\App\Models\MasterData> $model
     * @param list<array{0: string, 1: string, 2: string}> $rows
     */
    private function seedSimple(string $model, array $rows): void
    {
        foreach ($rows as $index => [$code, $name, $description]) {
            $model::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
