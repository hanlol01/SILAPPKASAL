# Mobile Reporter — Complete Page Parity Catalog

> Status: Design parity baseline  
> Last updated: 2026-08-26  
> Companion documents: `MOBILE_REPORTER_PRD.md`, `MOBILE_REPORTER_ENDPOINT_MATRIX.md`, `MOBILE_REPORTER_MOCKUP_CANVAS.md`

## 1. Meaning of “same”

The Flutter application must preserve **functional parity** with the current Reporter Portal:

- the same Reporter-only pages, data, cards, fields, actions, permissions, conditional states, and business flow;
- the same backend-owned status/capability rules and private API endpoints;
- the same published Information Center content available to a Reporter;
- the same loading, empty, failure, validation, success, and unavailable states.

It does **not** mean copying the desktop layout pixel-for-pixel. Desktop header navigation becomes mobile navigation and multi-column desktop cards become readable stacked cards, but no Reporter capability, action, or information block may disappear without an explicit Product Owner decision.

Dynamic values—counts, complaint numbers, statuses, article cover/title/body, dates, file data, notifications, and campus information—must be rendered from the API, never hard-coded from the examples in this document.

## 2. Global mobile shell

| Current Reporter Portal element | Mobile parity requirement |
|---|---|
| Header: Ringkasan, Buat Pengaduan, Pengaduan Saya, Pusat Informasi, Notifikasi, Akun | Bottom navigation: Ringkasan, Pengaduan Saya, Buat Pengaduan, Pusat Informasi, Akun. Notifikasi is a visible badge/entry in Akun or an equally discoverable top action. |
| Language selector | Keep Indonesian default and English option. |
| Theme selector | Keep as a local presentation preference if retained in mockup; no backend API dependency. |
| Reporter avatar/name | Show own safe profile identity; do not expose staff/other Reporter identity. |
| Breadcrumbs/back actions | Use a clear mobile app-bar Back action and page title. |
| Footer/support logos | Include on long public/information screens where brand policy requires it; do not consume primary task space on every app screen. |

## 3. Ringkasan / Dashboard Reporter

### 3.1 Required content parity

The following is the complete mobile equivalent of the dashboard shown by the Product Owner.

| Order | Component | Content/data | Action |
|---|---|---|---|
| 1 | Page title | `Ringkasan` and `Sekilas tentang pengaduan Anda.` | None |
| 2 | Summary cards | Total Pengaduan; Pengaduan Aktif; Pengaduan Selesai; Notifikasi. Each card has an icon, number, label, and short explanation. | Appropriate cards may open Pengaduan Saya or Notifikasi; do not make a card tappable without a real destination. |
| 3 | Primary action card | `Buat pengaduan baru`; supporting text about secure/confidential reporting; `Mulai pengaduan` button. | Opens the first complaint-wizard screen. |
| 4 | Sorotan Edukasi | Published featured article cover/poster, category, title, excerpt, `Baca Artikel`, `Lihat Semua Edukasi`. | Article detail and Education list. |
| 5 | Jelajahi Pusat Informasi | Four cards: Edukasi, Seputar Kebijakan, FAQ, Konsultasi; each retains its explanatory text. | Opens the corresponding Information Center section. |
| 6 | Pengaduan terbaru | Section title, short description, `Lihat semua pengaduan`, latest own complaint cards. | List page or selected complaint detail. |
| 7 | Complaint preview card | Registration number, reporter-safe complaint status, complaint type, category, submitted date, `Lihat`. | Own complaint detail. |
| 8 | Service notice | Current reporter-facing handling/SLA information, only if provided/approved by the product copy. | Informational only. |

### 3.2 Dashboard states

- Loading: four summary skeleton cards, featured-content skeleton, and recent-complaint skeletons.
- No complaint yet: show the primary report action; do not show fake complaint cards.
- No featured education: keep an unobtrusive Education destination; do not show placeholder article text.
- No notifications: total is zero and the Notifikasi destination remains usable.
- Network/API failure: retry state per section, without hiding successfully loaded independent sections.

## 4. Buat Pengaduan

### 4.1 Wizard shell

- App bar: Back/close only after a confirmation if entered data would be lost.
- Clear `Langkah 1 dari 3`, `Langkah 2 dari 3`, `Langkah 3 dari 3` progress indicator.
- Content warning/trauma-informed copy remains visible but compact.
- Footer actions: Kembali and Lanjutkan on steps 1–2; Kembali and Kirim Pengaduan on step 3.
- Each step validates its own fields before moving forward; the final backend validates all submitted data.

### 4.2 Step 1 — Jenis pengaduan

| Required component | Detail |
|---|---|
| Jenis pengaduan | Required selector populated from API master data. |
| Kategori | Required selector populated from API master data. |
| State | Master-data loading, no options, field validation, and retry. |

### 4.3 Step 2 — Detail kejadian

| Required component | Detail |
|---|---|
| Kronologi | Required textarea; current web validation guidance is 50–10,000 characters with visible count. |
| Tanggal kejadian | Required date control; future dates blocked. |
| Waktu kejadian | Optional time control, quick choices, and explicit `Saya tidak tahu waktunya`. |
| Lokasi kejadian | Required free-text field. |
| Jenis lokasi | Optional API-backed selector. |

### 4.4 Step 3 — Informasi tambahan dan bukti

| Required component | Detail |
|---|---|
| Informasi terlapor | Optional group: name, campus status, relation, details. Once a user starts the group, required related fields must be completed. |
| Informasi saksi | Optional. |
| Kontak rahasia | Conditional phone field for confidential complaint type as required by backend validation. |
| Bukti pendukung | Optional file queue: add, remove before upload, file validation, progress, retry, file count/remaining capacity. Failed optional file upload must not prevent report submission. |

### 4.5 Submission success

- Calm successful-submission confirmation; no celebratory animation.
- Server-issued registration number.
- Tracking code when supplied by backend, with copy/save-safe action and explanatory text.
- Evidence result: uploaded count and partial-upload warning when applicable.
- Actions: `Lihat pengaduan dan bukti` and `Pengaduan Saya`.

## 5. Pengaduan Saya

### 5.1 Complaint list

| Component | Mobile parity |
|---|---|
| Page heading/description | Explain that this is the Reporter’s own complaints. |
| List controls | Preserve only supported filters/search and API pagination. A non-functional search field is prohibited. |
| Complaint card | Registration number, safe status badge, report-type badge, category, submitted date, and tap/`Lihat` action. |
| Empty state | Explain that no complaint has been submitted yet and offer `Buat Pengaduan`. |
| Pagination | Load-more or pagination controls based on existing API metadata. |

### 5.2 Detail Pengaduan

The detail screen is a long, sectional mobile page. All sections below are required when the backend returns their data/capability.

| Section | Required content/actions |
|---|---|
| Header | Back, registration number, reporter-safe status badge, report-type badge. |
| Available actions | Direct cancellation and/or formal withdrawal only when backend capabilities permit. |
| Completion notice | Safe completed-state message. |
| Berita Acara Hasil Pelaporan | Available closure document number/date; `Pratinjau` and `Unduh PDF` only when backend makes the document available. |
| Informasi Pengaduan | Registration number, complaint type, category, safe status, submitted date. |
| Detail Pengaduan yang Anda Kirim | Expandable copy of the Reporter’s submission: complaint identity, chronology, date/time/location, respondent/witness context, and the Reporter account snapshot that the current web page presents. |
| Bukti Pendukung | Permitted supporting-file list, upload availability/limits, add/preview/download actions and empty/unavailable states. |
| Perkembangan Pengaduan | Reporter-safe timeline such as submitted, in handling, complete. |
| Perkembangan Penanganan Kasus | Reporter-safe progress accordions for investigation, recommendation, decision, recovery, monitoring, and evidence only as supplied by the safe progress API. |
| Rangkuman Akhir Penanganan | Published final summary/outcome statements when returned; never add internal reviewer notes. |

### 5.3 Direct cancellation

- Trigger only when `can_cancel` is true.
- Consequence explanation, reason field, confirmation dialog, pending/success/error state.
- Success refreshes list, summary cards, detail, timeline, and progress.

### 5.4 Formal withdrawal

- Trigger only when `can_request_withdrawal` is true or an active request exists.
- Required sequence: reason and confirmation → DRAFT document → profile completion if required → signed document upload → submit → pending review.
- Rejected request exposes allowed resubmission with reason; active request may be cancelled by its owner where capability permits.
- Signed-document history must remain visible only to the owning Reporter.
- A stale lock-version response must show refresh/retry, not silently lose user state.

## 6. Pusat Informasi

### 6.1 Information Center landing

- Page title and supporting copy.
- Four destination cards: Edukasi, Seputar Kebijakan, FAQ, Konsultasi.
- Contextual `Buat Pengaduan` call-to-action.
- No authoring, review, publication, governance, or content-management controls.

### 6.2 Edukasi and Seputar Kebijakan list

| Component | Required parity |
|---|---|
| Header | Section title and explanatory copy. |
| Search/filter | Published-content search and category filter, only with backend-supported query parameters. |
| Article cards | Cover/poster when available, category, title, excerpt, publication date, `Baca artikel`. |
| Pagination | Counts and previous/next/load-more control from API metadata. |
| CTA | Calm `Buat Pengaduan` prompt. |

### 6.3 Article detail

- Article cover, title, category, publication metadata, sanitized rich content, and allowed attachments.
- Back to Education/Policy list; no editing controls.
- Attachment preview/download uses authenticated private access.

### 6.4 FAQ and consultation

- FAQ: published questions/answers, search/filter where supported, loading/empty/error states.
- Consultation: published, verified institutional contact/help channels; no invented hotline, diagnosis, or emergency claim.

## 7. Notifikasi

| Component | Required parity |
|---|---|
| Header | `Notifikasi` and short description. |
| Unread filter | Show if the existing query supports it. |
| Notification item | Safe icon/type, title/body, received date, read/unread visual state. |
| States | Loading, none yet, request failure/retry, pagination if returned. |

The current Reporter Portal uses its reporter-specific list endpoint. Do not add mark-as-read mutation UI until separately confirmed in product scope.

## 8. Akun

| Section | Required parity |
|---|---|
| Profil | Read-only identity/campus context plus permitted edits only: profile status, “other” status explanation when relevant, address, name/phone if current API allows. |
| Status akun | Current account status/projection from backend. |
| Ganti kata sandi | Current password, new password, confirmation, validation and success/session behavior. |
| Language/theme | Client preferences if retained. |
| Logout | Clear confirmation and secure local-session removal. |

## 9. Mandatory cross-page states and rules

| Rule | Requirement |
|---|---|
| Ownership | Every complaint, file, withdrawal, notification, and document displayed belongs to the signed-in Reporter. |
| Safe progress | Never substitute internal Case, Investigation, Recommendation, Decision, Recovery, Audit, or Break-glass data for reporter-safe API data. |
| Dynamic content | Counts, cards, categories, articles, dates, and capabilities come from API responses. |
| Refresh | Mutation success invalidates/refetches summary, list, detail, progress, evidence, and relevant notification state. |
| Error handling | Design 401/session expiry, 403, 404, 409 stale data, 422 field error, 429 rate limit, offline, and general server failure. |
| Accessibility | Text + icon + color for statuses; screen-reader labels; logical focus; 44px minimum touch targets; readable small-screen layout. |
| Privacy | No sensitive data in mobile logs, analytics, screenshots, share previews, public cache, or local unencrypted storage. |

## 10. Design review acceptance test

The mobile mockup is ready for Flutter planning only if every item below is true:

- [ ] Each page/section/action in this catalog exists in the selected mockup or has an explicit approved exclusion.
- [ ] Dashboard contains the full card/action/content composition from section 3.
- [ ] The complaint wizard contains all three steps, field conditionality, file states, and success result.
- [ ] Complaint detail includes every safely available section in section 5.2.
- [ ] Information Center includes landing, education, policy, article, FAQ, and consultation flows.
- [ ] Cancellation, formal withdrawal, files, documents, notifications, and account flows are not omitted.
- [ ] Every primary action has a destination and its loading/error/empty/confirmation state.
- [ ] No Admin, Super Admin, Satgas, internal workflow, or content-governance element appears.
- [ ] The endpoint mapping is reviewed against `MOBILE_REPORTER_ENDPOINT_MATRIX.md` before Flutter implementation.

