# Mobile Reporter Mockup Selection Canvas

> Companion to `MOBILE_REPORTER_PRD.md`  
> Purpose: choose a coherent design direction before generating screens in Google Stitch or Lovable.

## A. Recommended design direction

Choose this as the baseline: **Calm, private, guided**.

- Tone: supportive, direct, non-judgmental, never sensational.
- Visual character: institutional and trustworthy, with generous spacing and soft neutral surfaces.
- Primary color: use the established SILAPPKASAL/institutional palette from the web brand; reserve red for confirmed irreversible actions only.
- Layout: one primary action per screen; persistent progress for sensitive multi-step flows; bottom sheets for simple choices and full screens for detailed forms/documents.
- Motion: subtle, functional, and optional; no celebratory/confetti treatment after complaint submission.

## B. Selections to make before mockup generation

| Canvas area | Recommended selection | Alternative | Decision to record |
|---|---|---|---|
| Navigation | Five-item bottom navigation: Beranda, Laporan Saya, Buat Laporan, Informasi, Akun | Four items with Buat Laporan as a floating action | Choose one only; do not use both |
| Submission flow | Three full-screen steps with a top progress indicator | Single long form | Use three steps; matches existing portal logic |
| Complaint detail | Header + segmented/accordion sections | Dense dashboard cards | Use sections: Ringkasan, Progres, Bukti, Dokumen |
| Status language | Safe plain-language labels + icon + color | Raw internal workflow codes | Use safe labels only |
| Evidence upload | Inline upload queue with per-file progress | Blocking upload page | Inline queue; final report can still submit without files |
| Cancellation/withdrawal | Explicit consequence page then confirmation | One-tap destructive action | Use two-stage confirmation |
| Document view | In-app preview before guarded export | Immediate download/share | Use preview first |
| Empty states | Explain why, what happens next, one clear action | Generic “No data” | Use contextual explanations |
| Language | Indonesian default; optional English | English-first | Indonesian default |

## C. Screen canvas

### C1. Authentication and entry

**MR-02 Login**

- Header: institutional mark, “Masuk ke SILAPPKASAL”.
- Fields: email/NIM/NIP, password, show/hide password.
- Controls: remember-session option, primary Masuk button, loading state.
- Secondary: Daftar akun and Lacak laporan only when public companion scope is approved.
- Error: generic safe login failure; never indicate whether a sensitive account exists beyond approved backend messages.

### C2. Home

**MR-03 Beranda**

- Greeting and a short, empathetic explanatory line.
- Dominant action card: `Buat Laporan`.
- Compact status summary: total, in progress, completed; do not expose internal case stages.
- Recent own complaint cards with safe status badge and last safe update.
- Information shortcuts: Edukasi, Kebijakan, FAQ, Konsultasi.
- States: first-time user (no complaints), loading skeleton, offline/retry.

### C3. Complaint submission

**MR-05 Step 1 — Jenis laporan**

- 1/3 progress label; type selection cards; category dropdown.
- Small safety note explaining that only needed information should be supplied.

**MR-06 Step 2 — Kejadian**

- 2/3 progress label; chronology textarea with minimum-character guidance and count.
- Date picker with Today/Yesterday quick choices; time picker with “Saya tidak tahu waktunya”.
- Location text field and optional location type.

**MR-07 Step 3 — Informasi tambahan & bukti**

- 3/3 progress label; respondent section labelled optional until the user begins it.
- Witness information; conditional confidential contact number.
- Optional supporting-file queue: add, remove before upload, size/type guidance, per-file progress, retry.
- Footer: Back and Submit. Final submit displays a calm confirmation summary.

**MR-08 Submission success**

- Clear success state without celebratory animation.
- Registration number; tracking code if issued; copy/save action with privacy explanation.
- File result state: all files uploaded / some files not uploaded, with a link to complaint detail.

### C4. Complaint list and detail

**MR-04 Laporan Saya**

- Search/filter affordances only if supported by the API design; do not make a non-functional search bar.
- Complaint cards: registration number, type, category, safe status, submitted date, chevron.
- Empty state: explain that the first report can be made from the central action.

**MR-09 Detail laporan**

- Header: registration number, safe status, type badge.
- Sections: Ringkasan laporan, Informasi yang dikirim, Progres penanganan, Bukti pendukung, Dokumen, Tindakan laporan.
- Submitter’s original narrative should be collapsible, with an understandable privacy/safety message.

**MR-10 Progres**

- Reporter-safe chronological timeline.
- Optional expanded progress sections for investigation, recommendation, decision, recovery, and evidence only when returned by the safe backend projection.
- Never show staff names, internal notes, reviewers, raw metadata, or internal status codes.

### C5. Supporting files and documents

**MR-11 Bukti pendukung**

- File cards: original display name, type/size, state, and permitted Preview/Unduh action.
- Show “Tambah bukti” only when the API says upload is allowed and slots remain.
- Empty state distinguishes “no files yet” from “upload no longer allowed”.

**MR-14 Dokumen penutupan**

- Document number and issuance date.
- In-app preview first; a guarded export/download action.
- Clear unavailable state if a case is not complete or no document is issued.

### C6. Cancellation and withdrawal

**MR-12 Batalkan laporan**

- Explain the consequence, ask for a reason, require intentional confirmation, then submit.
- Visible only when server capability allows it.

**MR-13 Pencabutan formal**

- Progress states: reason → draft document → signed-document upload → submit → awaiting review → approved/rejected/resubmit.
- Include profile-completion sheet if document-required identity fields are incomplete.
- Handle stale/409 response with a refresh/retry affordance; never overwrite the user’s entered reason silently.
- Clearly label generated material as `DRAFT`, not as an official campus template.

### C7. Account, notifications, and information

**MR-15 Notifikasi**: own notifications, unread filter, readable date, empty state, retry state.

**MR-16 Akun**: read-only identity/campus context; permitted profile editing; account state; change password; logout confirmation.

**MR-17 Information Center**: featured content, education/policy catalog, article reader, FAQ, consultation, permitted private attachment view/download.

## D. Interaction and state matrix

| Interaction | Required designed states |
|---|---|
| App launch | loading, restored session, expired session, offline |
| Login | default, field validation, password visibility, submitting, backend failure, locked/rate-limited response |
| Complaint wizard | step validation, save-in-session, previous step, submit, submission failure, partial file-upload success |
| Complaint list/detail | skeleton, empty, pagination/load-more, 404/ownership failure, retry |
| File action | choosing, validation error, uploading percentage, uploaded, failed/retry, preview unavailable, download failure |
| Cancellation/withdrawal | unavailable, warning, confirmation, pending, completed, rejected/resubmit, stale-data refresh |
| Notifications/content | loading, empty, unavailable, retry, attachment access denied |

## E. Paste-ready Google Stitch prompt

```text
Create a high-fidelity Android-first mobile app mockup named “SILAPPKASAL”.

This is a private Reporter/Pelapor app for a university sexual-violence reporting and support system. It is NOT an admin, investigator, or case-management dashboard. Use Bahasa Indonesia as the default language. The tone must be calm, supportive, clear, trauma-informed, and institutional. Avoid sensational imagery, gamification, confetti, or visual pressure.

Design direction: calm, private, guided. Use a refined Indonesian university service aesthetic, generous spacing, soft neutral cards, an accessible dark navy primary color from the institutional brand, and muted semantic status colors. Every status uses icon + text + color. Minimum touch targets should feel spacious.

Create these linked mobile screens:
1. Login: identifier, password, show/hide password, remember-session choice, Masuk button.
2. Home: greeting, prominent Buat Laporan card, safe complaint summary, recent own complaint cards, information shortcuts.
3. My complaints: safe-status cards and thoughtful empty state.
4. New complaint step 1 of 3: report type and category.
5. Step 2 of 3: chronology, incident date, optional/unknown time, location.
6. Step 3 of 3: optional respondent/witness context, confidential phone when needed, optional supporting-file upload queue with progress and retry.
7. Submission success: registration number, tracking code with copy/save action, partial-upload-safe result.
8. Complaint detail: registration number, safe status, submitted information accordion, timeline, progress, evidence, documents, and actions.
9. Supporting files: private file cards with preview/download; add-file action only when allowed.
10. Formal withdrawal flow: reason, generated DRAFT document, signed document upload, submit/review/rejected-resubmit states.
11. Notifications, Account/profile/change-password, and Information Center.

Use bottom navigation: Beranda, Laporan Saya, Buat Laporan, Informasi, Akun. Do not show Admin, Satgas, staff names, internal investigation notes, raw workflow codes, audit logs, or data from another reporter. Include loading, empty, validation-error, network-error, and confirmation states for every primary flow.
```

## F. Paste-ready Lovable prompt

```text
Create a mobile-first, high-fidelity clickable product prototype for a Flutter application called SILAPPKASAL Mobile Reporter. Produce design screens and prototype links only; do not invent backend logic or admin functionality.

Product: a secure university Reporter/Pelapor portal for submitting and tracking the reporter’s own sexual-violence complaint. Indonesian is the default language. The visual voice is calm, private, supportive, trauma-informed, and formal. Use an accessible institutional navy palette, neutral surfaces, readable typography, soft cards, and status badges with icon + text + color.

Information architecture: Beranda, Laporan Saya, Buat Laporan, Informasi, Akun.

Build the complete screen set and clickable paths for login; home; complaint list; complaint submission wizard with exactly three steps; submission success; complaint detail; safe timeline/progress; supporting-file list/upload/preview; cancellation confirmation; formal withdrawal states; closure-document preview; notifications; profile/change password; and Information Center.

Required UX constraints:
- Only Reporter features. Never show Admin, Super Admin, Satgas, internal case workflows, staff identities, internal notes, raw codes, audit logs, or other reporters’ data.
- Complaint wizard fields: type/category; chronology/date/time/location; optional respondent and witness context; optional files. Unknown incident time must be possible. File failure must not prevent final complaint submission.
- Show realistic loading, empty, validation, offline, upload-progress, error, success, destructive-confirmation, and stale-data-refresh states.
- Formal withdrawal has reason, DRAFT document, signed-document upload, submit, awaiting review, rejected/resubmit, and cancellation states. Clearly mark DRAFT as not an official template.
- Use Indonesian labels such as Buat Laporan, Laporan Saya, Progres Penanganan, Bukti Pendukung, Pencabutan Laporan, and Simpan dengan Aman.

Create a design system page first, then all screens. Make screens responsive for common Android widths and use components that map naturally to Flutter: app bar, bottom navigation, cards, text fields, select sheets, date/time pickers, dialogs, snackbars, accordions, and file rows.
```

## G. Handoff checklist after mockup approval

- Capture the selected canvas choices and screen links.
- Confirm which optional public companion screens are in release one.
- Map each screen action to the current Laravel endpoint/capability contract.
- Produce Flutter component and navigation specifications; do not generate React-web implementation as the production target.
- Review sensitive copy and document/file handling with product/security stakeholders before implementation.

