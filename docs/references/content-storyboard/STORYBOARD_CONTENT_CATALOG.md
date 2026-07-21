# SILAPKASAL Storyboard Content Catalog

## 1. Source Information

| Item | Value |
| --- | --- |
| Source filename | `STORYBOARD_APLIKASI_SILAPKASAL.pdf` |
| Source type | Low-fidelity mobile application storyboard in PDF format |
| Total page count | 18 pages |
| Primary reference | Mobile navigation, content taxonomy, article-title ideas, and layout concepts |

The source is a mobile storyboard for SILAPKASAL. Its login, report-submission,
and report-progress screens are reference material only and are not
implementation requirements for REV-CONTENT-01. The existing application
workflows remain the source of truth for those functions.

## 2. Static Information Sections

The information center uses these fixed navigation sections:

1. Edukasi
2. Seputar Kebijakan
3. FAQ
4. Konsultasi

Edukasi, Seputar Kebijakan, FAQ, and Konsultasi are static navigation
sections. Records contained within them are dynamic and managed through the
database. FAQ is included in the approved project scope even though the
storyboard does not provide a dedicated standalone FAQ page.

Lapor Kasus and Progres Laporan appear on storyboard pages 16 and 18, but are
excluded from content-management scope because their application workflows
already exist.

## 3. Education Categories

The Education landing concept appears on storyboard page 6. Its category
detail references appear on pages 7-14.

| Stable code | Display name | Storyboard page reference | Recommended icon concept | Short neutral description | Seeded scope | Initial active state | Article seed status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `perspective_islam` | Perspektif Islam | Pages 6-7 | Open book or religious reference | Materi edukasi berbasis sumber keislaman yang telah diverifikasi dan ditinjau. | `global` | `active` | `draft only` |
| `perspective_psychology` | Perspektif Psikolog | Pages 6 and 8 | Brain or wellbeing | Pengetahuan umum tentang respons psikologis, batasan diri, komunikasi, dan dukungan. | `global` | `active` | `draft only` |
| `perspective_law` | Perspektif Hukum | Pages 6 and 9 | Scales | Informasi hukum umum yang membantu pembaca memahami hak dan jalur bantuan. | `global` | `active` | `draft only` |
| `perspective_sociocultural` | Perspektif Sosial Budaya | Pages 6 and 10 | Community or users | Edukasi mengenai pengaruh norma, stigma, komunitas, dan perubahan budaya. | `global` | `active` | `draft only` |
| `speak_up` | Ayo Speak Up! | Pages 6 and 11 | Megaphone | Panduan umum untuk mengenali situasi dan mencari jalur pelaporan yang tepat. | `global` | `active` | `draft only` |
| `stop_sexual_violence` | STOP Kekerasan Seksual! | Pages 6 and 13 | Protective shield | Materi pencegahan dan respons awal yang aman terhadap kekerasan seksual. | `global` | `active` | `draft only` |
| `you_are_not_alone` | Kamu Tidak Sendiri | Pages 6 and 12 | Heart and helping hand | Materi dukungan yang menegaskan pilihan bantuan tanpa menjanjikan hasil tertentu. | `global` | `active` | `draft only` |
| `understand_reporting_flow` | Pahami Alur Pelaporanmu! | Pages 6 and 14 | Route or signpost | Informasi umum untuk mempersiapkan dan memahami pelaporan di lingkungan kampus. | `global` | `active` | `draft only` |

## 4. Policy Categories

The Policy landing concept appears on storyboard page 15.

| Stable code | Display name | Storyboard page reference | Recommended icon concept | Short neutral description | Seeded scope | Initial active state | Article seed status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `legislation` | Perundang-undangan | Page 15 | Legal document or landmark | Ringkasan edukatif mengenai regulasi yang telah diverifikasi dan masih berlaku. | `global` | `active` | `draft only` |
| `higher_education_policy` | Kebijakan Perguruan Tinggi | Page 15 | University building or policy document | Informasi kebijakan perguruan tinggi yang telah ditinjau dan disetujui untuk publikasi. | `global` | `active` | `draft only` |

## 5. Seed Article Titles

All article records below are editorial placeholders. Titles are sourced from
the storyboard, while their bodies must be prepared and reviewed separately.

### Perspektif Islam

| Stable seed key | Article title | Category code | Scope | Initial lifecycle state | Editorial review required | Initial body source | Published |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `perspective_islam_quran_verses` | Ayat Al-Qur’an | `perspective_islam` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_islam_hadith` | Hadis | `perspective_islam` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_islam_inspirational_stories` | Kisah Inspiratif | `perspective_islam` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_islam_scholar_views` | Kata Ulama | `perspective_islam` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |

### Perspektif Psikolog

| Stable seed key | Article title | Category code | Scope | Initial lifecycle state | Editorial review required | Initial body source | Published |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `perspective_psychology_myths_facts` | Mitos vs Fakta tentang Kekerasan Seksual | `perspective_psychology` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_psychology_warning_signs` | Mengenali Tanda Bahaya | `perspective_psychology` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_psychology_personal_boundaries` | Membangun Batasan Diri | `perspective_psychology` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_psychology_assertive_communication` | Komunikasi Asertif | `perspective_psychology` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_psychology_friends_community_role` | Peran Teman dan Komunitas | `perspective_psychology` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_psychology_gaslighting_manipulation` | Mengenali Gaslighting dan Manipulasi | `perspective_psychology` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |

### Perspektif Hukum

| Stable seed key | Article title | Category code | Scope | Initial lifecycle state | Editorial review required | Initial body source | Published |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `perspective_law_forms` | Mengenali Bentuk Kekerasan Seksual dalam Hukum | `perspective_law` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_law_victim_rights` | Hak-Hak Korban dalam Perspektif Hukum | `perspective_law` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_law_available_steps` | Langkah Hukum yang Bisa Ditempuh | `perspective_law` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_law_perpetrator_sanctions` | Sanksi Hukum bagi Pelaku | `perspective_law` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_law_legal_aid_support` | Bantuan Hukum dan Lembaga Pendukung | `perspective_law` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_law_prevention_through_policy` | Pencegahan Kekerasan Seksual melalui Kebijakan Hukum | `perspective_law` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |

### Perspektif Sosial Budaya

| Stable seed key | Article title | Category code | Scope | Initial lifecycle state | Editorial review required | Initial body source | Published |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `perspective_sociocultural_social_norms` | Norma Sosial dan Kekerasan Seksual | `perspective_sociocultural` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_sociocultural_patriarchy_impact` | Budaya Patriarki dan Dampaknya | `perspective_sociocultural` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_sociocultural_community_prevention_role` | Peran Masyarakat dalam Pencegahan | `perspective_sociocultural` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_sociocultural_victim_stigma` | Stigma terhadap Korban | `perspective_sociocultural` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_sociocultural_campaigns_movements` | Kampanye dan Gerakan Sosial | `perspective_sociocultural` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `perspective_sociocultural_culture_change_safe_campus` | Perubahan Budaya untuk Kampus Aman | `perspective_sociocultural` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |

### Ayo Speak Up!

| Stable seed key | Article title | Category code | Scope | Initial lifecycle state | Editorial review required | Initial body source | Published |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `speak_up_reportable_situations` | Mengenali Situasi yang Harus Dilaporkan | `speak_up` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `speak_up_how_to_speak` | Bagaimana Cara Berani Bicara? | `speak_up` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `speak_up_where_to_report` | Ke Mana Harus Melapor? | `speak_up` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `speak_up_myths_facts` | Mitos vs Fakta tentang Speak Up | `speak_up` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `speak_up_building_culture` | Bersama Membangun Budaya Speak Up | `speak_up` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |

### STOP Kekerasan Seksual!

| Stable seed key | Article title | Category code | Scope | Initial lifecycle state | Editorial review required | Initial body source | Published |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `stop_sexual_violence_why_stop` | Mengapa Harus Dihentikan? | `stop_sexual_violence` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `stop_sexual_violence_prevention_steps` | Langkah-Langkah Pencegahan | `stop_sexual_violence` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `stop_sexual_violence_when_you_or_friend_affected` | Jika Kamu atau Temanmu Mengalami Kekerasan Seksual | `stop_sexual_violence` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `stop_sexual_violence_together_we_can_stop` | Bersama, Kita Bisa Menghentikan Kekerasan Seksual! | `stop_sexual_violence` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |

### Kamu Tidak Sendiri

| Stable seed key | Article title | Category code | Scope | Initial lifecycle state | Editorial review required | Initial body source | Published |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `you_are_not_alone_why` | Mengapa Kamu Tidak Sendiri? | `you_are_not_alone` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `you_are_not_alone_find_support` | Temukan Dukungan di Sekitarmu | `you_are_not_alone` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `you_are_not_alone_recovery_steps` | Langkah untuk Memulihkan Diri | `you_are_not_alone` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `you_are_not_alone_recovery_testimonial` | Testimoni: Mereka Berhasil Bangkit | `you_are_not_alone` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `you_are_not_alone_build_safe_environment` | Bersama Membangun Lingkungan Aman | `you_are_not_alone` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |

### Pahami Alur Pelaporanmu!

| Stable seed key | Article title | Category code | Scope | Initial lifecycle state | Editorial review required | Initial body source | Published |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `understand_reporting_flow_prepare_self_evidence` | Langkah Awal: Siapkan Diri dan Bukti | `understand_reporting_flow` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `understand_reporting_flow_where_to_report` | Ke Mana Harus Melapor? | `understand_reporting_flow` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `understand_reporting_flow_campus_reporting_flow` | Alur Pelaporan di Kampus | `understand_reporting_flow` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `understand_reporting_flow_rights_to_know` | Hak-Hak yang Harus Kamu Ketahui | `understand_reporting_flow` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |
| `understand_reporting_flow_do_not_be_afraid` | Jangan Takut, Kamu Tidak Sendiri! | `understand_reporting_flow` | `global` | `draft` | `yes` | `editorial starter draft` | `no` |

## 6. FAQ Seed Planning

The storyboard does not provide authoritative FAQ answers. The following
titles are planning inputs only and must not be published automatically.

| Suggested draft FAQ title | Lifecycle state | Required review | Published |
| --- | --- | --- | --- |
| Apakah identitas pelapor aman? | `draft` | Editorial and legal review | `no` |
| Siapa yang dapat melihat laporan saya? | `draft` | Editorial and legal review | `no` |
| Apa perbedaan laporan terbuka, rahasia, dan anonim? | `draft` | Editorial and legal review | `no` |
| Apa yang perlu dipersiapkan sebelum membuat laporan? | `draft` | Editorial and legal review | `no` |
| Bagaimana proses penanganan laporan? | `draft` | Editorial and legal review | `no` |
| Bagaimana cara menghubungi PPKS? | `draft` | Editorial and legal review | `no` |
| Apakah saya dapat melihat progres laporan? | `draft` | Editorial and legal review | `no` |
| Apakah bukti tambahan dapat dikirim setelah laporan dibuat? | `draft` | Editorial and legal review | `no` |

## 7. Consultation Data Planning

Konsultasi is represented as a menu concept on storyboard pages 5 and 17.
Its records are structured service contacts, not articles.

| Field | Planning rule |
| --- | --- |
| `service_name` | Nama layanan yang telah disetujui. |
| `description` | Deskripsi singkat mengenai jenis bantuan yang tersedia. |
| `email` | Alamat layanan yang telah diverifikasi. |
| `phone` | Nomor telepon layanan yang telah diverifikasi. |
| `whatsapp` | Nomor atau tujuan WhatsApp resmi yang telah diverifikasi. |
| `office_address` | Alamat kantor resmi. |
| `operating_hours` | Jam layanan yang telah diverifikasi tanpa klaim ketersediaan tambahan. |
| `emergency_availability` | Penanda ketersediaan darurat yang hanya boleh diaktifkan dengan persetujuan eksplisit. |
| `appointment_url` | Tautan HTTPS resmi untuk membuat janji. |
| `action_label` | Label tindakan singkat yang sesuai dengan kanal layanan. |
| `icon_code` | Kode ikon dari daftar yang disetujui. |
| `display_order` | Urutan tampil numerik dan deterministik. |
| `active_state` | Status aktif record layanan. |
| `scope` | `campus` atau `global`, sesuai pemilik dan cakupan layanan. |

No phone number, email address, office address, service hour, or emergency
claim is supplied by this catalog. All contact data must come from a verified
institutional owner.

## 8. Seeder Rules

- Sections and categories may be seeded with lifecycle value `active`.
- Article records are seeded as `global` drafts.
- FAQ drafts remain unpublished.
- Consultation records must not be seeded with fabricated contact data.
- Seeder reruns must be idempotent.
- Every seeded record must use a stable seed key.
- Seeder reruns must not overwrite editorial changes made after initial seed.
- Seeded content must never be published automatically.
- Legal, religious, psychological, and testimonial claims must not be fabricated.
- Seed metadata must identify the storyboard or system seed as its origin.
- Seeded article and FAQ records must set `requires_editorial_review = true`.

## 9. UI Reference Mapping

| Storyboard concept | Future PWA UI reference |
| --- | --- |
| Education category menu | Mobile two-column category grid with large, recognizable icons |
| Featured education topics | Article highlight cards with concise titles and excerpts |
| Visual topic blocks | Respectful image-led cards with fallback category illustrations |
| Selected topic | Dedicated article detail page with readable prose hierarchy |
| FAQ section | Keyboard-accessible accordion with one question per item |
| Consultation section | Structured contact cards with explicit action buttons |

These concepts are visual and information-architecture references. They are
not pixel-perfect technical specifications, and the future UI must continue to
follow SILAPPKASAL's established design system, accessibility rules, privacy
boundaries, and responsive behavior.

## 10. Deferred Scope

- Login by NIM.
- Flutter implementation.
- Notification delivery for information content.
- Article comments.
- Article reactions.
- Article bookmarks.
- Public unauthenticated articles.
- Multilingual article bodies.
- Scheduled publication.
- Advanced content analytics.
