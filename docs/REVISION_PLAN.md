# REVISION_PLAN.md — SILAPPKASAL Revision Implementation Plan

> Status: Proposed — awaiting Product Owner approval
> Last Updated: 2026-07-06
> Source: `docs/Revisi.md` (15 items from Product Owner business-flow testing)
> Baseline: UX-01..UX-09B complete, RC-01..RC-05 complete (RC-05 hotfix verified), Milestone 31-B2 complete
> Constraints honored: no code implemented by this document; no routing, RBAC, API contract, or business rule change is authorized by this plan alone.

---

## 1. Purpose

This document converts the Product Owner revision list in `docs/Revisi.md` into five safe, independently mergeable milestones (REV-01 through REV-05). Each milestone is scoped so that:

- Frontend-only polish ships first and fast.
- Anything requiring new backend behavior is isolated, tested, and reviewed separately.
- The evidence feature (Revisi item 15) is split into planned phases and is never implemented casually.
- Reporter-facing surfaces never gain access to sensitive internal case details.
- Satgas never gains Admin-only capabilities through UI changes.

---

## 2. Revision Item Classification

| # | Revisi Item (short) | Classification | Notes |
|---|---|---|---|
| 1 | Forwarded-to-Satgas confirmation text on reports page | frontend + existing backend data | Report `status_code = forwarded` already exists in the report payload. |
| 2 | Hide "Tugaskan Satgas" on `/dashboard/cases` for Satgas role | frontend-only | Backend policy already forbids Satgas assignment; UI must mirror it. |
| 3 | Case status change does not auto-refresh | frontend-only | Missing/incomplete TanStack Query invalidation after the status mutation. |
| 4 | Min-50-character live counter on investigation plan summary | frontend-only | Mirror the RC-01 wizard chronology counter pattern. |
| 5 | "Current case status" indicator in the case summary/actions panel | frontend + existing backend data | `status_code` / `status_label` / `current_stage` already in `CaseRecord`. |
| 6 | Internal case progress timeline (Admin/Super Admin/Satgas) | frontend + existing backend data (verify) | Derive from fields already returned on case detail (forwarded/assignment/investigation/recommendation/decision/recovery/closed timestamps and status histories). If a gap exists, a small metadata-only backend addition is scoped inside REV-02, not invented in the frontend. |
| 7 | "Next step" card driven by case status + user role | frontend-only | Pure presentation over existing status + `user.role.code`. |
| 8 | Reporter-safe report progress timeline (portal) | backend/API required | Needs a privacy-filtered portal timeline endpoint. Must NOT be assembled client-side from sensitive data. |
| 9 | Safe final completion message for closed cases (portal) | frontend + existing backend data | Portal already abstracts to `Completed`; message renders off that safe status. |
| 10 | Hide/disable "Tambah Monitoring" when recovery is completed | frontend-only | Backend already rejects it; UI must stop offering it. |
| 11 | Risk & priority assessment flow (`risk_levels`, `priority_levels`) | backend/API required | No assessment endpoint currently exists in the v1 API surface. |
| 12 | Clean up global "Aksi penugasan belum tersedia" placeholder | frontend-only | Assignment flow becomes per-case: list → detail → assign. |
| 13 | Role-aware assign button copy for Satgas | frontend-only | Informational copy: "Penugasan Satgas dikelola oleh Admin/Pimpinan PPKS." |
| 14 | Sensitive-detail restriction copy with human role labels | frontend-only | i18n copy change using Admin / Pimpinan PPKS / Satgas PPKS / Pelapor labels. |
| 15 | Evidence flow (attach, upload, lifecycle, custody, reporter signals) | large feature / must be split | Split across REV-04 (metadata + empty state) and REV-05 (private file upload/download/preview + chain of custody + reporter attach). Never faked, never public storage. |

Evidence split (mandated):

1. **Evidence metadata** → REV-04
2. **Evidence file upload / private storage** → REV-05
3. **Evidence lifecycle/status** → REV-04 (display + metadata transitions using the existing M11 foundation) with file-linked transitions completed in REV-05
4. **Evidence audit trail / chain of custody** → foundation already exists (M11 custody tables); surfaced in REV-04, file events completed in REV-05

---

## 3. Global Frontend Design Direction (applies to every REV milestone)

All frontend work must be clean, modern, readable, and comfortable — not merely functional.

- **Layout & spacing**: `space-y-6` page rhythm, `space-y-4` stacked forms, `gap-4` grid forms, existing card padding presets. No one-off spacing values.
- **Cards**: reuse existing Card structure (header/title/description/content). New cards (Next Step, Current Status, Timeline) must match sibling cards on the same page.
- **Buttons**: preserve variant hierarchy (primary → outline → ghost; destructive only for destructive actions). No orphan buttons floating outside the action rail; no redundant `mr-2 h-4 w-4` icon classes inside Buttons.
- **Typography**: H1 `text-2xl font-semibold tracking-tight`; section titles via bare `CardTitle`; helper text `text-sm/text-xs text-muted-foreground`. No new heading styles.
- **Helper text**: proactive hints before validation fires (e.g. the min-50 counter), warning tone while below threshold, muted once satisfied.
- **Empty states**: shared `EmptyState` component only; distinguish filtered-empty vs truly-empty where a filter exists.
- **Status indicators**: multi-channel badges (icon + tone + localized label) via `StatusBadge`/`PortalStatusBadge` or a sibling following the same pattern. Never neutral outline badges for new status surfaces.
- **Timelines**: vertical, generous line-height, one event per row (icon + title + timestamp + optional short description), truncation-safe on mobile, most recent state clearly emphasized. No dense multi-column timelines.
- **Dialogs**: shadcn Dialog/AlertDialog patterns, `max-h-[90vh] overflow-y-auto` for long forms, AlertDialog for destructive confirmation.
- **Forms**: RHF + zod + shared field components + `applyLaravelErrors` + sonner toast fallback. No inline ad-hoc validation.
- **Mobile**: no page-level horizontal overflow; timeline and new cards must reflow at 360×740; follow the reports/cases mobile-card precedent where applicable.
- **Localization**: every new string in both `id` (default) and `en`. No backend/API/RBAC/endpoint/metadata jargon in visible copy (RC-02/RC-05 rule). Human role labels per item 14.
- **Privacy tone**: reporter-facing copy is calm, trauma-sensitive, and non-technical.

Avoid: cramped sections, random button placement, inconsistent card spacing, walls of text, sensitive data exposure, fake data, fake backend behavior, speculative redesign, unrelated refactors.

---

## 4. Milestones

### REV-01 — Workflow & Detail Polish (safe, visible, frontend-first)

**1. Objective**
Ship the high-visibility, low-risk workflow polish items on the admin/Satgas dashboard so daily operations feel complete, role-aware, and self-explanatory, without touching backend behavior.

**2. Included revision items**
1, 2, 3, 4, 5, 7, 10, 12, 13, 14

**3. Excluded items**
6, 8, 9 (timelines/completion message → REV-02), 11 (assessment → REV-03), 15 (evidence → REV-04/REV-05). No backend, routing, RBAC, or API contract change.

**4. Frontend scope**
- **Item 1**: After forward-to-case succeeds, show a persistent, neatly placed confirmation on the report detail/list surface ("Kasus sudah diteruskan ke Satgas terpilih") for reports in `forwarded` state — an info-tone inline `Alert` or muted status line near the existing status badge, not a toast-only signal. Do not change the forward button.
- **Item 2 + 13**: On `/dashboard/cases` (and case detail action rail), gate the assign affordance on `user.role.code`. For `satgas_ppks`, hide the button and render the informational line "Penugasan Satgas dikelola oleh Admin/Pimpinan PPKS." using the existing `DisabledWorkflowAction` info-alert pattern.
- **Item 3**: Fix query invalidation after `PATCH /cases/{case}/status` so case detail, case list, and My Work queries refetch immediately. No optimistic updates (project rule).
- **Item 4**: Add a live character counter + upfront helper hint to the investigation plan summary field ("Minimal 50 karakter"), `text-warning` below 50, `text-muted-foreground` at ≥ 50 — mirroring the wizard chronology pattern.
- **Item 5**: Add a compact "Status Kasus Terkini" block at the top of the case detail action rail: multi-channel `StatusBadge` + localized stage label. Single source: existing `CaseRecord` fields.
- **Item 7**: Add a "Langkah Berikutnya" card in the action rail: a small map of (status/stage × role) → one short localized sentence + optional pointer to the relevant tab/action. Read-only guidance; never enables an action the role cannot perform. Unknown combinations render a neutral fallback, never a blank card.
- **Item 10**: Hide (preferred) or disable-with-reason the "Tambah Monitoring" action when recovery status is `completed`/terminal, using `DisabledWorkflowAction` for the disabled variant.
- **Item 12**: Remove the global "Aksi penugasan belum tersedia" placeholder from `/dashboard/cases`; assignment lives on case detail only (list → detail → Tugaskan Satgas).
- **Item 14**: Replace restricted-detail copy with: "Akses detail dibatasi untuk menjaga kerahasiaan laporan. Pengguna dengan peran {{roleLabel}} hanya dapat melihat ringkasan operasional." Role labels from a small i18n map (Admin, Pimpinan PPKS, Satgas PPKS, Pelapor). Never render `user.role.code` raw.
- All new strings added to `id` and `en` locale files.

**5. Backend scope**
None. Backend remains authoritative and unchanged.

**6. Data/API dependency**
Existing endpoints only: `GET /reports`, `GET /reports/{report}`, `GET /cases`, `GET /cases/{case}`, `PATCH /cases/{case}/status`, recovery status fields on `GET /recoveries/{recovery}` (already embedded in case detail sections). No new fields required; if a needed field is missing, the item is deferred to REV-02 backend scope rather than faked.

**7. Risk level**
**Low.** UI-layer only; backend policies already enforce every rule being mirrored.

**8. UI/UX design expectation**
- Action rail on case detail reads top-down: Current Status → Next Step → available actions → informational alerts. No cramped stacking; `space-y-4` between rail cards.
- Confirmation text (item 1) sits visually attached to the report status area, info tone, one sentence, icon-led.
- Next Step card: one short sentence + optional single link/button; never a paragraph wall.
- Counter helper (item 4) occupies the standard `FormDescription` slot, right-aligned counter, no layout shift while typing.
- Role-aware messaging uses the established info `Alert` (icon + title + description), consistent with `DisabledWorkflowAction`.
- All surfaces verified at 360×740: rail stacks below content, no horizontal overflow.

**9. Acceptance criteria**
- Forwarded reports display the confirmation text in both languages; non-forwarded reports do not.
- Satgas users never see an enabled assign control anywhere; they see the management notice instead. Admin/Super Admin flow is unchanged.
- After a successful status change, the new status is visible in list + detail + Current Status block without manual refresh.
- Investigation plan summary shows the min-50 hint and live counter with the warning→muted color transition; the create action still validates as before.
- Current Status block always matches the header badge; Next Step card renders a correct sentence for every seeded status × role combination and a safe fallback otherwise.
- "Tambah Monitoring" is not offered when recovery is completed; the backend rejection path can no longer be triggered from the UI.
- Global assignment placeholder is gone; per-case assignment on detail still works (M24 behavior intact).
- No visible copy contains technical jargon; all new keys exist in `id` and `en`.
- `npx.cmd tsc --noEmit`, `npm.cmd run build`, `npm.cmd run lint` pass at existing baselines (0 lint errors, 6 known warnings).

**10. Recommended QA checks**
- Static review: role gating uses `user.role.code` only; no display-name logic.
- Grep: no new hardcoded English in components; no raw role codes in copy; no `Loading...` text introduced.
- Verify invalidation keys cover case detail, case list, and `my-work` queries.
- Confirm no changes under `backend/api/`, no route path changes, no API client signature changes.
- Regression spot-check RC-04 pagination and RC-03 badges on touched pages.

**11. Recommended human smoke tests**
| ID | Role | Steps | Expected |
|---|---|---|---|
| REV01-ST-001 | Admin | Forward a report to a case; return to reports | Confirmation text visible on the forwarded report, both languages |
| REV01-ST-002 | Satgas | Open `/dashboard/cases` and a case detail | No assign button; management notice shown instead |
| REV01-ST-003 | Satgas | Change case status | List, detail, and Current Status update without manual refresh |
| REV01-ST-004 | Satgas | Open Buat Investigasi; type < 50 then ≥ 50 chars | Counter live-updates; warning color flips to muted at 50 |
| REV01-ST-005 | All internal roles | Open case details across statuses | Current Status block + correct Next Step sentence per role |
| REV01-ST-006 | Admin/Super Admin | Open a completed recovery | No Tambah Monitoring affordance |
| REV01-ST-007 | Admin | Assign Satgas from case detail | Per-case flow works; no global placeholder anywhere |
| REV01-ST-008 | All | Mobile 360×740 walkthrough of touched pages | No overflow; rail cards stack cleanly |

---

### REV-02 — Case Progress Timelines & Safe Completion Messaging

**1. Objective**
Give internal roles an operational case timeline, and give reporters a privacy-safe progress timeline plus a humane completion message — with all privacy filtering enforced server-side.

**2. Included revision items**
6, 8, 9

**3. Excluded items**
11 (assessment), 15 (evidence). Evidence-related timeline events are added later by REV-04/REV-05, not anticipated here with fake entries.

**4. Frontend scope**
- **Item 6 (internal timeline)**: New "Progress Kasus" section on `/dashboard/cases/:id` rendering a vertical timeline: laporan dikirim → diteruskan ke Satgas → Satgas ditugaskan → investigasi dibuat → rekomendasi dikirim → putusan final → pemulihan selesai → kasus ditutup. Each event: icon in a small tone circle, localized title, `formatDateTime` timestamp, optional one-line detail. Skeleton while loading; `EmptyState.Inline`-style message when history is minimal.
- **Item 8 (reporter timeline)**: "Perkembangan Laporan" section on `/portal/reports/:registrationNumber` consuming ONLY the new safe portal timeline payload. Labels map to the four safe portal stages; absolutely no Satgas names, recommendation/decision/evidence content, internal status codes, or staff identities.
- **Item 9 (completion message)**: When the portal report is `Completed`, render a calm success-tone card: "Kasus Anda telah selesai ditangani. Untuk informasi lanjutan silakan hubungi kanal resmi Satgas PPKS." (i18n `id`/`en`).

**5. Backend scope**
- **Item 6**: First, verify the internal timeline is fully derivable from the existing case detail payload (forwarded_at, assignment history, investigation/recommendation/decision/recovery status histories, closed_at). If derivable → no backend change. If a specific timestamp is missing → add a metadata-only, role-scoped timeline read (e.g. `GET /api/v1/cases/{case}/timeline`) exposing event type + timestamp + safe label only, guarded by existing case policies, with feature tests.
- **Item 8**: New reporter-scoped safe timeline, e.g. `GET /api/v1/portal/reports/{registrationNumber}/timeline` (or an embedded `timeline` array on the existing portal report detail). Server maps internal statuses to safe stages; payload contains only safe stage code, safe label key, and date. Reporter-role-only, own-reports-only, tested for privacy (assert absence of sensitive fields).
- No changes to write endpoints, RBAC roles, or business rules.

**6. Data/API dependency**
- Internal: existing case detail payload; conditional small read endpoint as above.
- Portal: new safe timeline read (backend/API required). Frontend must not ship item 8 before the endpoint exists — no client-side reconstruction, no mocked data.

**7. Risk level**
**Medium.** Read-only additions, but the reporter timeline is privacy-critical; a leak here breaks the product's core promise.

**8. UI/UX design expectation**
- Timeline visual language identical on both surfaces (spacing, icon circles, connector line), with the portal variant simpler and softer.
- Completed events use success tone; the current/most recent event is emphasized (stronger icon tone + medium weight title); future steps are either omitted or rendered muted — choose one treatment and apply consistently.
- One event per row; timestamps localized; long labels wrap without breaking the connector line; readable at 360×740.
- Completion card uses the success token palette, an icon, and at most two short sentences. No buttons unless a real destination exists.
- Internal timeline placed as a full-width section (or dedicated position agreed with PO) so the right rail does not become cramped.

**9. Acceptance criteria**
- Internal timeline shows accurate, ordered events for seeded cases at every lifecycle stage; missing stages are simply absent (no fake entries).
- Reporter timeline renders only safe stages/labels/dates; manual payload inspection confirms no sensitive fields on the wire.
- Backend privacy tests assert the portal timeline never serializes staff identities, narratives, recommendation/decision/recovery/evidence content, or raw internal status codes.
- Completion card appears only for `Completed` reports, in both languages.
- Existing portal privacy rules (safe statuses only) remain intact.
- Frontend baselines pass (tsc/build/lint); backend `php artisan test` passes with new tests added (baseline 125 passed / 1025 assertions plus additions).

**10. Recommended QA checks**
- Backend: feature tests for role scoping (reporter sees own reports only; Satgas/admin scoping per existing policies), privacy field-absence assertions, 404/403 paths.
- Frontend: verify timeline consumes API data only; grep for any hardcoded event fabrication.
- Verify no write endpoint or existing response shape changed.
- i18n parity check for all new keys.

**11. Recommended human smoke tests**
| ID | Role | Steps | Expected |
|---|---|---|---|
| REV02-ST-001 | Satgas | Open an assigned case mid-investigation | Timeline shows events up to investigation; current step emphasized |
| REV02-ST-002 | Admin | Open a closed case | Full timeline through closure, correct order and dates |
| REV02-ST-003 | Reporter | Open own report detail (in-process) | Safe timeline only; no names, no internal codes |
| REV02-ST-004 | Reporter | Open a completed report | Completion card + Completed stage; both languages |
| REV02-ST-005 | Reporter | Inspect network payloads (PO/QA) | Timeline payload contains only safe fields |
| REV02-ST-006 | All | Mobile 360×740 timeline check | Vertical timeline readable, no overflow |

---

### REV-03 — Risk & Priority Assessment Flow

**1. Objective**
Implement the missing assessment step: assigned Satgas records risk level and priority level while the case is in `assessment`, using master data, with full validation, policy, audit, and notification alignment.

**2. Included revision items**
11

**3. Excluded items**
Everything else. Assessment must not implicitly change assignment rules, statuses outside its own transition, or reporter-visible content (the portal continues to show only safe stages).

**4. Frontend scope**
- "Asesmen Risiko & Prioritas" action on case detail, visible/enabled only for the actively assigned Satgas while case status is `assessment`; otherwise the standard `DisabledWorkflowAction` explanation (localized, role-aware per item 13/14 tone).
- Dialog form (RHF + zod + shared fields): risk level select (from `GET /master/risk_levels`), priority level select (from `GET /master/priority_levels`), and a short justification/notes textarea if the approved backend contract includes one. Laravel 422 errors map field-level.
- After success: query invalidation refreshes case detail; recorded risk/priority render as multi-channel badges in the case summary (extend the workflow badge tone map — no neutral outline badges).
- Read-only display of the recorded assessment for Admin/Super Admin per metadata-first rules.

**5. Backend scope**
- New assessment write capability, e.g. `PATCH /api/v1/cases/{case}/assessment` (final shape to be approved before implementation): validates `risk_level_code` and `priority_level_code` against master data via FormRequest; policy restricts to actively assigned Satgas; service enforces case-status invariant (`assessment` only) inside a transaction; audit log entry; low-noise notification consistent with M17 patterns; feature + policy tests.
- If the `cases` table lacks risk/priority columns, a migration is included here (verify `DATABASE_SCHEMA.md` first). No other schema drift.
- Case status transition out of `assessment` continues to use the existing status endpoint and master-data `valid_transitions` — the assessment endpoint records the assessment; it does not become a parallel status mechanism.

**6. Data/API dependency**
- Existing: `GET /master/{type}` for `risk_levels` and `priority_levels` (verify seeder coverage), case detail, status transitions.
- New: the assessment write endpoint. Frontend work is blocked until the backend contract is approved and merged.

**7. Risk level**
**Medium-high.** First new write endpoint in this revision cycle; touches policy, validation, audit, and possibly a migration. Mitigated by narrow scope and the established service/policy/FormRequest pattern.

**8. UI/UX design expectation**
- Dialog matches existing workflow dialogs: title + one-line description, `space-y-4` fields, primary submit right-aligned in footer, `max-h-[90vh] overflow-y-auto`.
- Selects show localized labels via `format-labels.ts`-style formatters; helper text explains the SLA context in one calm sentence (e.g. asesmen maks 5 hari kerja) without jargon.
- Risk/priority badges use distinct tones (e.g. Tinggi → destructive-soft, Sedang → warning, Rendah → success) with icons, consistent light/dark contrast.
- Disabled state explains *why* (wrong status or not assigned) in human language.

**9. Acceptance criteria**
- Assigned Satgas can record an assessment only while the case is `assessment`; all other roles/statuses are rejected by backend and never offered by UI.
- Values are validated against master data; invalid codes → 422 with field errors surfaced inline.
- Audit log records the assessment action with masking rules respected.
- Recorded risk/priority appear on case detail (badge treatment) and persist across refresh.
- Admin/Super Admin see the recorded values per metadata-first policy; reporters see nothing new.
- Backend tests cover happy path, wrong role, wrong status, invalid codes, and audit emission; full suite passes.
- Frontend baselines pass (tsc/build/lint).

**10. Recommended QA checks**
- Policy tests: non-assigned Satgas, admin, super_admin, reporter all rejected on write.
- Invariant test: assessment rejected when case is in any non-`assessment` status.
- Contract check: response shape follows `{ success, message, data }` envelope.
- Frontend: no numeric-ID inputs (project rule), no optimistic updates, invalidation correct.
- Verify master data seeders actually provide `risk_levels`/`priority_levels`; if absent, seeding is part of this milestone, flagged to PO first.

**11. Recommended human smoke tests**
| ID | Role | Steps | Expected |
|---|---|---|---|
| REV03-ST-001 | Assigned Satgas | Open case in `assessment`; record risk+priority | Success; badges appear; audit entry exists |
| REV03-ST-002 | Assigned Satgas | Open case in `investigation` | Assessment action disabled with clear reason |
| REV03-ST-003 | Non-assigned Satgas / Admin | Attempt assessment (UI + direct request) | UI hides action; backend returns 403 |
| REV03-ST-004 | Assigned Satgas | Submit with empty selections | Inline localized validation; no request side effects |
| REV03-ST-005 | Admin | View assessed case | Risk/priority visible read-only; both languages |
| REV03-ST-006 | Reporter | View own related report | No new internal detail leaked |

---

### REV-04 — Evidence Metadata Activation & Empty State (Evidence Phase 1)

**1. Objective**
Activate the existing M11 evidence metadata foundation in the UI: assigned Satgas can register and manage evidence metadata during investigation, the Bukti tab gets a proper empty state, lifecycle statuses render as first-class badges, and chain-of-custody metadata events become visible. **No file upload, download, preview, or storage in this milestone.**

**2. Included revision items**
15 — partial: evidence metadata, evidence empty state, "Tambah Bukti" (metadata), lifecycle status display, custody metadata surfacing.

**3. Excluded items**
15 — file upload/private storage, download/streaming, preview, reporter attach-at-submit, reporter post-submit upload, reporter-safe evidence signals tied to real files, break-glass access (all → REV-05 or later). All other Revisi items.

**4. Frontend scope**
- Bukti tab on case detail (assigned Satgas): "Tambah Bukti" button opening a metadata dialog (evidence type, classification, description, collected date via shared `DatePicker`, source — per the existing `POST /investigations/{investigation}/evidences` contract). RHF + zod + `applyLaravelErrors`.
- Evidence lifecycle badges via the multi-channel pattern (terdaftar/ditinjau/diverifikasi/ditolak/diarsipkan tones + icons), replacing neutral outline badges; status transitions through the existing `PATCH /evidences/{evidence}/status` where valid options exist.
- Empty state using shared `EmptyState`: "Belum ada bukti yang tercatat. Satgas dapat menambahkan metadata bukti selama tahap investigasi." — with the add action in the `action` slot for assigned Satgas.
- Custody events (`GET /evidences/{evidence}/custody`) rendered as a compact read-only sub-timeline reusing the REV-02 timeline visual language.
- Honest capability messaging: a muted informational line stating file attachment is not yet available ("Lampiran file bukti belum tersedia pada tahap ini.") — never a fake upload control, never "(UI-only)" wording.
- Admin/Super Admin continue to have no evidence access (M11 rule); their view of the tab keeps the item-14 restricted-detail copy.

**5. Backend scope**
None expected — M11 endpoints already exist (`POST/GET` evidences, `PATCH` metadata/status, `GET` custody). If QA verification finds an endpoint gap versus this UI scope, the gap is documented and either resolved as a minimal patch approved by the PO or the affected UI piece is deferred. No storage, no upload routes.

**6. Data/API dependency**
Existing M11 evidence endpoints and lifecycle constants; existing evidence access policy (assigned Satgas only). Requires an investigation to exist (evidence is investigation-owned), so UI gates the add action on an existing investigation.

**7. Risk level**
**Medium.** Prepared-but-lightly-exercised backend surface; access rules are strict and must be mirrored exactly. No storage risk yet.

**8. UI/UX design expectation**
- Bukti tab layout: header row (title + count + Tambah Bukti button right-aligned), evidence entries as consistent rows/cards (type icon, title/description, lifecycle badge, collected date), custody expandable or per-evidence detail — no cramped multi-badge rows.
- Dialog mirrors other workflow dialogs exactly (spacing, footer, scroll behavior).
- Empty state is the dashed-border shared component, icon-led, with the exact PO-approved copy.
- Capability notice is one muted line, not an alert wall.
- Mobile: evidence rows collapse gracefully; dialog single-column below `sm`.

**9. Acceptance criteria**
- Assigned Satgas can create evidence metadata during an active investigation; entries appear immediately after invalidation.
- Lifecycle badges render distinctly for every status; status updates work through the existing endpoint and refresh correctly.
- Non-assigned Satgas, Admin, Super Admin, and reporters see no evidence content and no add affordance; restricted copy (item 14) shown where appropriate.
- Empty state and capability notice appear per spec, both languages.
- Custody events display read-only with actor-safe rendering as returned by the API (no client-side enrichment).
- No upload/download/preview control exists anywhere; grep confirms no file input added.
- tsc/build/lint pass; backend suite unchanged and passing.

**10. Recommended QA checks**
- RBAC mirror check: every evidence UI affordance gated on assigned-Satgas via API data, not client assumptions.
- Grep: no `input type="file"`, no storage/upload wording in visible copy, no "(UI-only)" literals.
- Verify evidence creation gated on investigation existence; error paths (403/404/422) render humane messages.
- i18n parity for all evidence keys; badge contrast check light/dark.

**11. Recommended human smoke tests**
| ID | Role | Steps | Expected |
|---|---|---|---|
| REV04-ST-001 | Assigned Satgas | Open Bukti tab on a case with no evidence | Shared empty state with add action |
| REV04-ST-002 | Assigned Satgas | Create evidence metadata | Entry appears with lifecycle badge and date |
| REV04-ST-003 | Assigned Satgas | Update evidence status | Badge updates; custody event recorded and visible |
| REV04-ST-004 | Admin/Super Admin | Open the same case | No evidence detail; restricted-access copy shown |
| REV04-ST-005 | Reporter | Portal report detail | No evidence information visible |
| REV04-ST-006 | All | Language toggle + mobile check on Bukti tab | Fully localized; no overflow |

---

### REV-05 — Evidence Files: Private Upload, Secure Download/Preview, Chain of Custody (Evidence Phase 2)

**1. Objective**
Implement real evidence files end-to-end with security-first architecture: private storage, controller-streamed access, reporter attachment (optional at submit and post-submit while active), Satgas review integration, complete chain of custody, and safe reporter-facing signals. This milestone requires its own approved technical design before any code.

**2. Included revision items**
15 — remainder: file upload + private storage; download/streaming + preview; reporter attach-at-submit (optional) and post-submit additions; reporter-submitted evidence surfacing in the Bukti tab on forward; custody events for upload/review/status/download; reporter-safe status signals ("Bukti telah diterima" / "Bukti sedang ditinjau").

**3. Excluded items**
Break-glass evidence access for Admin/Super Admin (separate security milestone), WhatsApp notifications about evidence, bulk export, mobile app. All other Revisi items (done in prior milestones).

**4. Frontend scope**
- Reporter wizard: optional evidence attach step/section — clearly optional, trauma-sensitive copy, client-side type/size pre-validation mirroring server rules, per-file progress, graceful failure that never blocks report submission.
- Portal report detail: add-evidence affordance while the report/case is active (per approved rules); safe status signals only — never file listings with internal review detail.
- Bukti tab: reporter-submitted files appear as initial evidence after forward; assigned Satgas gets secure download (streamed) and preview for safe MIME types; custody log extends with file events.
- Upload UX: shadcn-consistent dropzone/file field (new shared component following existing form-field conventions), localized errors for type/size/count limits.

**5. Backend scope**
- Private `evidence` disk (local private in dev per DEVELOPMENT_WORKFLOW §8.6; S3-compatible optional for production per ADR-006), UUID filenames, server-side MIME validation, max-size enforcement, checksum computation and storage.
- Upload endpoints (multipart) for reporter (own active report, throttled) and assigned Satgas (investigation evidence), download via controller `StreamedResponse` with policy checks — **no public URLs ever**.
- Anonymous-report handling: uploads must not attach identity/IP/device data to anonymous report evidence (SECURITY checklist §10.7).
- Chain-of-custody events on upload, review, status change, and download; audit log entries with masking.
- Queue jobs where needed (e.g. checksum/scan hooks) on the existing database queue.
- Feature tests: upload validation, RBAC (Super Admin has **no** default access — break-glass remains unimplemented), streaming authorization, custody emission, anonymous privacy.
- Prerequisite: a written evidence architecture note (storage layout, size/type matrix, retention, threat notes) approved by the PO before implementation starts.

**6. Data/API dependency**
Depends on REV-04 (metadata UI live), REV-02 (timeline integration points), and item-14 copy. New multipart endpoints and streaming route are net-new API surface requiring contract approval.

**7. Risk level**
**High.** File handling, privacy of victims/reporters, storage security, and anonymous-report constraints. Highest-stakes milestone in this cycle; must not be rushed or partially faked.

**8. UI/UX design expectation**
- Wizard attach section: calm, clearly optional ("Opsional — Anda tetap dapat mengirim laporan tanpa bukti"), compact file list with per-file state (queued/uploading/done/failed), remove affordance, no aggressive red on validation.
- Satgas file rows: filename (display-safe), type icon, size, lifecycle badge, uploaded-by role label (human label, not identity where restricted), custody link, download button (outline + icon).
- Preview in a dialog for safe types only; everything else download-only with a clear notice.
- Reporter portal signals: single soft badge/line per the safe-status vocabulary; no counts of internal review actions.
- All states (uploading, failed, oversized, wrong type) have designed, localized treatments — no browser-default alerts.

**9. Acceptance criteria**
- Files stored privately; direct URL access impossible; download only via authorized streamed responses.
- MIME type and size validated server-side; UUID filenames; checksums stored; original filename never used as storage path.
- Reporter can submit a report with zero evidence with no friction; attach and post-submit add work within approved limits.
- Reporter-submitted evidence appears in the Bukti tab after forward, reviewable by assigned Satgas only.
- Super Admin/Admin cannot access evidence files (no break-glass yet) — verified by tests.
- Anonymous report uploads store no reporter identity/IP/device linkage in evidence business fields.
- Every upload/review/status-change/download produces a custody event and audit entry.
- Reporter sees only safe signals; payload inspection confirms no internal review detail on portal endpoints.
- Full backend suite passes with new coverage; frontend baselines pass.

**10. Recommended QA checks**
- Security checklist §10.5 full pass (no public folder, controller streaming, UUID names, server-side MIME, checksum).
- Attempt direct storage-path access and cross-role download (must fail).
- Oversized/forbidden-type upload attempts rejected server-side even if client validation bypassed.
- Anonymous report evidence privacy assertions.
- Custody/audit completeness for every file action, including download.
- Throttle checks on reporter upload endpoints.

**11. Recommended human smoke tests**
| ID | Role | Steps | Expected |
|---|---|---|---|
| REV05-ST-001 | Reporter | Submit report without evidence | Succeeds unhindered |
| REV05-ST-002 | Reporter | Attach valid files during wizard; then oversized/wrong type | Valid files accepted with progress; invalid rejected with humane copy |
| REV05-ST-003 | Reporter | Add evidence post-submit on active report; then on closed case | Allowed on active; blocked with clear explanation when closed |
| REV05-ST-004 | Assigned Satgas | Review, preview safe type, download, change status | All work; custody log shows each action |
| REV05-ST-005 | Admin/Super Admin | Attempt to view/download evidence file | Denied; restricted copy shown |
| REV05-ST-006 | Reporter | Check portal after Satgas review | Only safe signal ("Bukti sedang ditinjau"); no internal detail |
| REV05-ST-007 | QA | Direct URL access to a stored file | 403/404; never served publicly |
| REV05-ST-008 | All | Mobile + both languages across upload/review flows | Localized, responsive, no overflow |

---

## 5. Sequencing & Dependencies

```
REV-01 (frontend polish, low risk)
   ↓
REV-02 (timelines + safe completion; small read-only backend additions)
   ↓
REV-03 (assessment write flow; new endpoint + tests)
   ↓
REV-04 (evidence metadata UI on existing M11 backend)
   ↓
REV-05 (evidence files; requires approved architecture note first)
```

- REV-01 has no dependencies and should start immediately.
- REV-02's reporter timeline (item 8) is blocked on its backend endpoint; the internal timeline (item 6) can proceed once derivability is verified.
- REV-05 must not begin implementation until the evidence architecture note is approved by the Product Owner.

## 6. Cross-Milestone Verification Commands (Product Owner)

Frontend (`frontend/`): `npx.cmd tsc --noEmit`, `npm.cmd run build`, `npm.cmd run lint` — expected: PASS, 0 lint errors, 6 known react-refresh warnings, non-blocking chunk warnings.

Backend (`backend/api`, REV-02/03/05 only): `php artisan migrate --force`, `php artisan db:seed --force`, `php artisan route:list --path=api/v1`, `php artisan test` — expected: all tests pass (pre-M31 stored baseline 125 passed / 1025 assertions, plus M31 and new milestone additions).

## 7. Open Questions for Product Owner

1. REV-02: is a dedicated internal timeline endpoint preferred, or derivation from the existing case detail payload if complete?
2. REV-03: confirm `risk_levels` and `priority_levels` master data exists in seeders; confirm whether an assessment notes/justification field is wanted.
3. REV-03: should recording an assessment remain separate from the `assessment → investigation` status transition (recommended), or trigger it?
4. REV-05: approve file type/size matrix, per-report file count limit, and retention expectations before architecture drafting.
5. REV-05 exclusion confirmation: break-glass evidence access stays out of scope for this cycle.

---

> This plan authorizes documentation only. Each REV milestone requires explicit Product Owner approval before implementation, and REV-05 additionally requires an approved evidence architecture note.
