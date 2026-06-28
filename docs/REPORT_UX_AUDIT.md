# REPORT_UX_AUDIT.md — SILAPPKASAL UX Audit

> Status: Audit (informational, non-binding)
> Last Updated: 2026-06-28
> Scope: `frontend/` only — refinement of the existing implementation.
> Out of scope: backend behavior, RBAC rules, API contracts, redesign, framework changes.

This audit respects the existing architecture: React 19 + Vite + TanStack Start, TanStack Router (file-based routing in `frontend/src/routes`), TanStack Query for server state, shadcn/ui + Radix + Tailwind for UI, react-hook-form + zod for the workflow-action dialogs, sonner for toasts, and i18next with `id` default and `en` optional. The goal is consistency, accessibility, mobile-first usability, and user confidence — not replacement.

Key reference inputs:

- `docs/PROJECT_MASTER.md` (mission, 7-stage workflow, role matrix)
- `docs/PROJECT_HANDOFF.md` (milestone state up to M31-B2, contract notes)
- `docs/ARCHITECTURE_DECISIONS.md` (RBAC, privacy invariants)
- `docs/DEVELOPMENT_WORKFLOW.md` (security checklist, DoD, token rules)
- `docs/DEMO_DATASET_SPEC.md` (demo coverage requirements)

---

## 1. Executive summary

The product has a coherent IA, a privacy-first portal, and a strong shadcn-based design system. The workflow-action dialogs (`frontend/src/components/workflow-actions/*`) already show the project's intended pattern: react-hook-form + zod + shadcn `Form`/`Select`/`Dialog` + `applyLaravelErrors` + sonner toast. Most usability problems trace back to **inconsistent adoption of that same pattern** in older forms (public registration, registration correction, admin user create, master-data dialog, report wizard).

Top systemic issues, ranked:

1. The reporter Report Wizard (`portal.reports.new.tsx`) advances steps without per-step validation; backend errors can land on a step the user isn't viewing.
2. Form inputs are inconsistent: some routes use raw `<select>` and `<input type="date"/time>`, others use the shadcn `Select` + zod schema. This creates visible style drift and accessibility gaps.
3. Native date/time inputs are used throughout for dates that are always Indonesian-locale and never future-dated; no calendar (shadcn `Calendar`) is wired up despite being available.
4. Enum/status localization is partly translated, partly not. Portal status labels are hardcoded to Bahasa Indonesia in `portal-status-badge.tsx` and ignore `i18n.language`. The report wizard's `report_type` fallback is hardcoded English.
5. Several admin tables (`dashboard.users.tsx`, `dashboard.registrations.tsx`, `dashboard.master-data.universities.tsx`) lack mobile-responsive collapses (no horizontal scroll affordance, no card fallback).
6. Error handling is bifurcated: workflow dialogs use `applyLaravelErrors` to set field-level errors, but the older public/portal forms keep their own `setErrors` map and a parallel toast pattern. The wizard goes one step further and shows only a top-level toast on non-`ApiError` failures.
7. Several non-decorative interactive elements lack accessible names (icon-only buttons, `<select>` without `aria-label`, the dashboard top-bar search has no submit semantics).

None of the findings require redesign or contract changes. All are achievable within the existing components and locales.

---

## 2. Inventory of audited surfaces

| Area | Files reviewed |
|---|---|
| Public | `routes/index.tsx`, `routes/login.tsx`, `routes/register.tsx`, `routes/track.tsx`, `routes/registration.pending.tsx`, `routes/registration.correction.tsx` |
| Reporter portal | `routes/portal.tsx`, `routes/portal.index.tsx`, `routes/portal.reports.index.tsx`, `routes/portal.reports.new.tsx`, `routes/portal.reports.$registrationNumber.tsx`, `routes/portal.notifications.tsx`, `routes/portal.account.tsx`, `components/portal/*`, `layouts/portal-layout.tsx` |
| Admin | `routes/dashboard.tsx`, `routes/dashboard.index.tsx`, `routes/dashboard.registrations.tsx`, `routes/dashboard.registrations.$id.tsx`, `routes/dashboard.users.tsx`, `routes/dashboard.reports.index.tsx`, `routes/dashboard.cases.index.tsx`, `layouts/dashboard-layout.tsx` |
| Satgas | `routes/dashboard.cases.$id.tsx`, `components/workflow-actions/*` (investigation, recommendation, decision, recovery, evidence) |
| Super Admin | `routes/dashboard.analytics.tsx`, `routes/dashboard.workflow.tsx`, `routes/dashboard.master-data*.tsx`, `routes/dashboard.break-glass.tsx`, `routes/dashboard.settings.tsx` |
| Shared | `components/ui/*`, `components/query-state.tsx`, `components/status-badge.tsx`, `components/portal/portal-status-badge.tsx`, `components/auth-provider.tsx`, `components/language-switcher.tsx`, `lib/format-labels.ts`, `lib/form-errors.ts`, `lib/api-client.ts`, `locales/{id,en}/*` |

---

## 3. Evaluation by criterion (summary)

| # | Criterion | Verdict | Notes |
|---|---|---|---|
| 1 | Information Architecture | Good | Role-aware nav, clear separation of `/portal` and `/dashboard`. |
| 2 | Navigation | Good w/ gaps | No breadcrumbs anywhere; back navigation depends on contextual buttons. |
| 3 | Visual Hierarchy | Good | Consistent page title + subtitle pattern. Some pages mix translated and untranslated headings. |
| 4 | Form UX | Mixed | Workflow dialogs use RHF+zod; public/portal forms use raw `useState` + native validation. |
| 5 | Stepper UX | Weak | Wizard advances without step validation, no progress indicator. |
| 6 | Validation UX | Mixed | Backend-side OK; client-side only present in workflow dialogs. |
| 7 | Date Picker UX | Weak | Native `<input type="date">` everywhere; no future-date guard in older forms; no Indonesian-locale calendar. |
| 8 | Time Picker UX | Weak | Native `<input type="time">` only; no quick presets, no "unknown time" affordance. |
| 9 | Dropdown UX | Inconsistent | shadcn `Select` only in workflow dialogs and admin lists; raw `<select>` in public/portal/admin-create forms. |
| 10 | Localization | Mixed | i18n wired up; `portal-status-badge.tsx` hardcodes Indonesian labels; wizard hardcodes English fallbacks; several admin pages still have English-only strings (master data, workflow, analytics scope summary). |
| 11 | Error Messages | Mixed | Field-level errors exist; cross-step + non-ApiError errors are under-served. |
| 12 | Toast Messages | Consistent | sonner with `richColors`. Some success toasts triggered before navigation but no follow-up confirmation in the destination view. |
| 13 | Accessibility | Needs work | Several icon-only buttons lack labels; native selects unlabeled; color contrast on `text-warning-foreground` light theme is borderline. |
| 14 | Mobile-first usability | Needs work | Admin tables don't collapse; portal nav is horizontally scrollable but hides labels under `sm` breakpoint, leaving icon-only buttons without labels. |
| 15 | Responsive behavior | Mixed | Detail pages adapt well; tables and dialog widths assume desktop. |
| 16 | Consistency | Mixed | Two parallel form patterns (legacy vs. workflow). |
| 17 | Enum/status presentation | Mixed | `format-labels.ts` is correct and complete; not used consistently. |
| 18 | Overall user confidence | Moderate | Privacy framing is excellent in copy; wizard step-flow and validation gaps reduce confidence for the highest-stakes flow. |

---

## 4. Findings

> Each finding follows the requested template: Severity • Affected pages • Root cause • Recommended fix • Expected UX • Implementation complexity.

### F-01 — Wizard advances without per-step validation

- **Severity**: Critical
- **Affected**: `routes/portal.reports.new.tsx`
- **Root cause**: The wizard's `onSubmit` checks `step < 3` and increments without validating step fields. Native `required`/`minLength` is only enforced when the user finally submits at step 3. Backend errors set into `errors` state are rendered per field on whatever step that field lives on, so a step-1 `category_code` error is invisible while the user sits on step 3.
- **Recommended fix**: Adopt the workflow-dialogs pattern — react-hook-form + zod, with one schema per step (`stepOneSchema`, `stepTwoSchema`, `stepThreeSchema`) and a final merged schema. Trigger `form.trigger([...stepFields])` before advancing. Wire backend Laravel errors through `applyLaravelErrors` from `lib/form-errors.ts`; if an error key belongs to a previous step, set `step` to that step before showing the toast.
- **Expected UX**: Reporter cannot bypass missing required fields; backend rejections snap focus back to the step containing the failing field with the message inline.
- **Complexity**: Medium

### F-02 — No step indicator / progress feedback

- **Severity**: High
- **Affected**: `routes/portal.reports.new.tsx`
- **Root cause**: Only a `"Langkah {{step}}"` label is shown. Users can't see total steps, what each step contains, or what they have completed.
- **Recommended fix**: Add a 3-dot progress header rendered above the card, using existing primitives (Tailwind + lucide `Check`). Keep label translated via `dashboard:`/`portal:` namespaces. Title each step (Identification, Incident, Respondent) instead of "Step N".
- **Expected UX**: Reporter understands position, scope, and how much remains. Reduces dropout.
- **Complexity**: Low

### F-03 — Report-type dropdown contains hardcoded English fallbacks

- **Severity**: High
- **Affected**: `routes/portal.reports.new.tsx`
- **Root cause**: The fallback for `report-types` master data uses `{ code: "open", name: "Open" }`, `"Confidential"`, `"Anonymous"`. The portal locale files already contain Bahasa Indonesian and English translations (`"Open": "Terbuka"` etc.) and `format-labels.ts` exposes `formatReportType`, but neither is used.
- **Recommended fix**: Drop the inline fallback; gate the wizard on `reportTypesQuery.isSuccess` (already standard for other lookups). For display, route the value through `formatReportType(t, code)` or a portal-specific helper that reads `portal.json` keys (`t("portal:Open")` etc.).
- **Expected UX**: Indonesian-default user sees Indonesian labels everywhere; English user sees English. No bilingual leak.
- **Complexity**: Low

### F-04 — Portal status badge ignores `i18n.language`

- **Severity**: High
- **Affected**: `components/portal/portal-status-badge.tsx`; used by `portal.reports.index.tsx`, `portal.reports.$registrationNumber.tsx`
- **Root cause**: `getStatusLabel` switches on the lowercased English status and **returns hardcoded Indonesian strings** ("Dikirim", "Dalam Peninjauan", ...). The component accepts a `t` from `useTranslation(["portal"])` but never calls it.
- **Recommended fix**: Replace the inline mapping with `t(`portal:${status}`)`. The keys (`"Submitted"`, `"Under Review"`, `"In Process"`, `"Completed"`) already exist in both `id/portal.json` and `en/portal.json`.
- **Expected UX**: English-language user sees English status text; behavior remains identical for Indonesian default.
- **Complexity**: Low

### F-05 — Native `<select>` used instead of shadcn `Select` in legacy forms

- **Severity**: High
- **Affected**: `routes/register.tsx`, `routes/registration.correction.tsx`, `routes/dashboard.registrations.tsx`, `routes/dashboard.users.tsx` (filters and `CreateReporterCard`), `routes/dashboard.master-data.universities.tsx` (dialog), `routes/portal.reports.new.tsx`
- **Root cause**: Earlier milestones (M19–M22) used raw `<select className="h-10 rounded-md border ...">` to ship faster. The shadcn `Select` (Radix-based) is already used in `dashboard.reports.index.tsx`, `dashboard.cases.index.tsx`, and every workflow dialog.
- **Recommended fix**: Migrate these `<select>` instances to shadcn `Select` + `SelectTrigger`/`SelectContent`/`SelectItem` and (where forms use RHF) `FormField`. Keep the disabled/loading affordances and the `placeholder` (mapped to `<SelectValue placeholder=…>`).
- **Expected UX**: Visual parity, dark-mode parity, keyboard navigation, screen-reader names, larger hit targets, focus rings consistent with the rest of the product.
- **Complexity**: Medium (touches ~6 routes)

### F-06 — Date inputs use native browser pickers with no constraints

- **Severity**: High
- **Affected**: `portal.reports.new.tsx` (incident_date), `dashboard.users.tsx` (none currently — incidental), workflow dialogs (already constrain `<= today` via zod), `dashboard.master-data.universities.tsx` (no date fields), public registration (no dates)
- **Root cause**: The wizard uses `<Input type="date">` without min/max attributes. The workflow dialogs already constrain dates via `requiredDate.refine((value) => value <= today, "Date cannot be in the future")`. The wizard does not.
- **Recommended fix**: At minimum, add a future-date guard in zod (same `today` constant). For consistency with the design system and to support Indonesian month names, swap `<Input type="date">` for a shadcn `Popover` + `Calendar` (`react-day-picker` already installed). Provide `locale={i18n.language === "id" ? id : enUS}` (date-fns is in deps).
- **Expected UX**: User cannot pick a future incident date. Month/day names render in Indonesian for ID locale.
- **Complexity**: Medium

### F-07 — Time input has no presets and no "time unknown" affordance

- **Severity**: Medium
- **Affected**: `portal.reports.new.tsx` (incident_time)
- **Root cause**: A bare `<Input type="time">` is used. Trauma-sensitive reporting often requires "I don't remember" or rough time windows; the field accepts only minute-precision time or empty.
- **Recommended fix**: Keep `<input type="time">` for now, but add (1) a checkbox "Saya tidak ingat waktunya / I don't remember the time" that disables the field and submits `null`, and (2) quick-pick chips for Pagi/Siang/Sore/Malam mapped to representative times (e.g. 08:00/13:00/17:00/20:00). Backend already accepts `null`.
- **Expected UX**: Reporters who don't recall exact time aren't forced to guess; reporters who only remember rough window can express it.
- **Complexity**: Low

### F-08 — Wizard reinvents form state instead of using RHF + zod

- **Severity**: High
- **Affected**: `routes/portal.reports.new.tsx`, `routes/register.tsx`, `routes/registration.correction.tsx`, `routes/dashboard.users.tsx` (`CreateReporterCard`)
- **Root cause**: These routes manage form state with raw `useState`, a parallel `errors` map, and a manual `update` setter. The project already has the canonical pattern in `frontend/src/components/workflow-actions/*` using `useForm` + `zodResolver` + `applyLaravelErrors`.
- **Recommended fix**: Refactor these forms to RHF + zod. Use `applyLaravelErrors(form, error)` to map Laravel 422 errors back to fields. This also unlocks `form.trigger()` for the wizard step gate.
- **Expected UX**: Consistent error rendering, automatic focus on the first invalid field, no "clear errors on change" boilerplate.
- **Complexity**: Medium

### F-09 — Non-`ApiError` failures swallow the toast in the wizard

- **Severity**: Medium
- **Affected**: `routes/portal.reports.new.tsx`
- **Root cause**: `onError` only acts inside `if (error instanceof ApiError)`. Network failures or 5xx parse errors trigger no UI feedback at all.
- **Recommended fix**: Move `toast.error(...)` to the outer scope; only `setErrors(error.errors ?? {})` should be gated by the `ApiError` check. Use `apiErrorMessage(error, t("common:unexpectedError"))` for the fallback message.
- **Expected UX**: Any submission failure produces a toast.
- **Complexity**: Low

### F-10 — Registration correction shows only `rejection_reason`, no field-level highlighting

- **Severity**: Medium
- **Affected**: `routes/registration.correction.tsx`
- **Root cause**: The page renders the full free-text rejection reason at the top and re-shows all fields editable. There is no way for the admin/reviewer to indicate which fields the reporter must correct; the reporter must read prose to figure that out.
- **Recommended fix**: This is largely a backend contract enhancement (out of audit scope), but the UI today can be improved by highlighting fields whose Laravel validation failed on resubmit (already done by `applyLaravelErrors` if migrated) and by surfacing the rejection reason inside a shadcn `Alert` with destructive variant.
- **Expected UX**: Reporter can scan and fix only what's wrong rather than re-checking every field.
- **Complexity**: Low (frontend portion only)

### F-11 — "Forgot password" link is dead and goes to /login

- **Severity**: High
- **Affected**: `routes/login.tsx`
- **Root cause**: The link uses `to="/login"`. Forgot-password is not implemented in the backend yet but the visible affordance suggests it exists.
- **Recommended fix**: Either (a) remove the link until backend implements it, or (b) replace with disabled `<span>` carrying `title="Hubungi admin kampus"` and an info icon. Do not advertise capabilities that don't exist.
- **Expected UX**: User isn't sent in a loop.
- **Complexity**: Low

### F-12 — Top-bar search input is decorative (no submit, no results)

- **Severity**: Medium
- **Affected**: `layouts/dashboard-layout.tsx`
- **Root cause**: The dashboard top-bar has `<Input placeholder="Cari dashboard..." />` with no `onChange`, `onKeyDown`, or form. It looks functional.
- **Recommended fix**: Either implement global search (out of scope) or remove the input and replace with a `Command`-palette trigger button (cmdk is already in deps), or hide it.
- **Expected UX**: Affordances match behavior.
- **Complexity**: Low (remove) / High (implement)

### F-13 — Admin tables don't collapse on small screens

- **Severity**: High
- **Affected**: `routes/dashboard.registrations.tsx`, `routes/dashboard.users.tsx`, `routes/dashboard.master-data.universities.tsx`, `routes/dashboard.reports.index.tsx`, `routes/dashboard.cases.index.tsx`
- **Root cause**: All five render a full `<table>` inside a parent without `overflow-x-auto` on the wrapper (the master-data page does have it; the others don't). On phones, the table forces the layout wider than the viewport.
- **Recommended fix**: Wrap every table in `<div className="overflow-x-auto">` (or apply the master-data pattern globally). For the highest-traffic tables (reports, cases), add a `md:hidden` card list and `hidden md:table` table. Reuse `PortalReportCard` pattern as inspiration.
- **Expected UX**: Admins on mobile can scroll/read tables. Tablet users see compressed but legible data.
- **Complexity**: Medium

### F-14 — Portal nav hides labels on small screens, leaving icon-only buttons

- **Severity**: Medium
- **Affected**: `layouts/portal-layout.tsx`
- **Root cause**: `<span className="hidden sm:inline">{t(item.titleKey)}</span>` hides the text below `sm`, leaving the user with five anonymous icons (Overview, New Report, My Reports, Notifications, Account). For a reporter portal whose primary surface is mobile this is the wrong tradeoff.
- **Recommended fix**: Either (a) keep labels visible at all breakpoints with smaller text (`text-xs sm:text-sm`), or (b) move to a bottom tab bar on mobile (`sm:hidden` bottom-fixed). Add `aria-label={t(item.titleKey)}` on each button regardless.
- **Expected UX**: Reporter on a phone can identify each nav item without guessing.
- **Complexity**: Low

### F-15 — Master Data page is English-only

- **Severity**: High
- **Affected**: `routes/dashboard.master-data.tsx`, `routes/dashboard.master-data.universities.tsx` (visible labels), `routes/dashboard.master-data.faculties.tsx`, `routes/dashboard.master-data.study-programs.tsx`
- **Root cause**: Tabs and headings are hardcoded English (`"Campus Master Data"`, `"Manage universities, faculties, and study programs."`, `"Universities"`, `"Faculties"`, `"Study Programs"`). The dialog uses `t("dashboard:masterData...")` properly, but the parent layout does not.
- **Recommended fix**: Move the tabs array into the component and use `t("dashboard:masterData.tabs.universities")` etc. Add the new keys to both `id/dashboard.json` and `en/dashboard.json`. Default lang is `id` per `i18n.ts`.
- **Expected UX**: Bahasa-Indonesia user no longer sees English at the top of the super-admin Master Data page.
- **Complexity**: Low

### F-16 — Dashboard Workflow page is English-only

- **Severity**: High
- **Affected**: `routes/dashboard.workflow.tsx`
- **Root cause**: The entire page (title, step labels, distribution titles, descriptions, empty-state copy, scope footer) is hardcoded English. The `labelFromKey` helper title-cases raw backend keys instead of routing through `formatGenericLabel` / `format-labels`.
- **Recommended fix**: Add `dashboard:workflow.pipeline.*` keys to both locales, replace hardcoded strings with `t(...)`. Replace `labelFromKey` with `formatGenericLabel` (already imported across the codebase).
- **Expected UX**: Indonesian super-admins see Indonesian copy on a core analytics page.
- **Complexity**: Low

### F-17 — Empty-state copy quality is inconsistent across roles

- **Severity**: Medium
- **Affected**: `routes/dashboard.cases.index.tsx`, `routes/dashboard.reports.index.tsx`, `routes/dashboard.users.tsx`, `routes/dashboard.registrations.tsx`, `routes/dashboard.master-data.universities.tsx`
- **Root cause**: Most admin tables show a flat one-liner (`"No registrations match the filter."`) instead of the rich empty state used in `portal.reports.index.tsx` (icon + title + subtext + suggested next action).
- **Recommended fix**: Extract a generic `<EmptyState icon title description action />` shared component (already substantially implemented twice). Apply to admin lists. Differentiate "filtered empty" vs "truly empty" copy.
- **Expected UX**: Admin understands whether the filter is too tight or whether there is genuinely no data.
- **Complexity**: Low

### F-18 — Loading states are non-uniform

- **Severity**: Medium
- **Affected**: `routes/dashboard.cases.index.tsx`, `routes/dashboard.cases.$id.tsx`, `routes/dashboard.registrations.$id.tsx`, `routes/dashboard.workflow.tsx`
- **Root cause**: Some pages render skeletons (good: `portal.reports.index.tsx`, `portal.account.tsx`, dashboard overview via `StatSkeletonGrid`); others render `"Loading..."` text. Mixed messaging makes the app feel less polished.
- **Recommended fix**: Standardize on `Skeleton`-based placeholders for any list, card, or chart. Keep text fallbacks only for short, secondary panels.
- **Expected UX**: Visual stability while data loads, no layout shift.
- **Complexity**: Low–Medium

### F-19 — Toast position fixed to `top-right`, hard to see on mobile

- **Severity**: Low
- **Affected**: `routes/__root.tsx`
- **Root cause**: `<Toaster richColors position="top-right" />`. On phones, the top-right may be partially under the system status bar / browser chrome and far from the user's thumbs after a form submit.
- **Recommended fix**: Use `position={isMobile ? "top-center" : "top-right"}` via a small `useMediaQuery` hook, or set `position="top-center"` everywhere. Keep `richColors`.
- **Expected UX**: Toasts visible without scroll on mobile, especially after submit.
- **Complexity**: Low

### F-20 — Icon-only buttons lack accessible names

- **Severity**: Medium
- **Affected**: `layouts/dashboard-layout.tsx` (theme toggle has `aria-label`, search icon does not), `layouts/portal-layout.tsx` (theme toggle has `aria-label`, the avatar trigger does not), `components/language-switcher.tsx` (has `sr-only` text — OK), `components/portal/portal-status-badge.tsx`, table action buttons in `dashboard.users.tsx` (icon `Eye`/`Lock` are inside `Button` with no `aria-label`).
- **Root cause**: Some controls were ported without screen-reader names.
- **Recommended fix**: Add `aria-label` to every icon-only Button. For dropdown triggers, ensure the visible text inside `<Button>` is read or add `aria-label`.
- **Expected UX**: Screen-reader users can navigate the topbars and table action columns.
- **Complexity**: Low

### F-21 — Color-only status indication on some badges

- **Severity**: Medium
- **Affected**: `components/status-badge.tsx`, `components/portal/portal-status-badge.tsx`
- **Root cause**: Status is differentiated by a colored badge. Color is not the *only* signal (label text is also present), so this is partially mitigated — but the `warning` and `success` tones in light theme have borderline contrast (`bg-warning/15 text-warning-foreground`).
- **Recommended fix**: Verify both badges pass WCAG AA contrast in light/dark. Add a small icon glyph per status (e.g. `Clock` for Under Review, `CheckCircle2` for Completed) so the status is multi-channel.
- **Expected UX**: Accessible to low-vision and color-blind users.
- **Complexity**: Low

### F-22 — No breadcrumbs anywhere

- **Severity**: Low
- **Affected**: All `/dashboard/*` and `/portal/*` detail pages
- **Root cause**: Navigation depends on contextual "Back" buttons. Deep links (e.g. case detail) leave the user without a sense of hierarchy.
- **Recommended fix**: Use the existing shadcn `Breadcrumb` primitive (it's in `components/ui/breadcrumb.tsx`) for `dashboard.cases.$id.tsx`, `dashboard.registrations.$id.tsx`, `dashboard.reports.$id.tsx`, `portal.reports.$registrationNumber.tsx`. Pull labels from i18n.
- **Expected UX**: Orientation in deeply nested detail views.
- **Complexity**: Low

### F-23 — Case detail dense layout, hard to scan on tablet

- **Severity**: Medium
- **Affected**: `routes/dashboard.cases.$id.tsx`
- **Root cause**: Six sections (metadata, sensitive report, investigations, recommendations, decisions, recoveries, evidence) stack vertically with the right rail of assignment + actions. The page becomes very long for Satgas, who need quick state assessment.
- **Recommended fix**: Add tab navigation inside the case detail (shadcn `Tabs`) to switch between Investigation / Recommendation / Decision / Recovery / Evidence. Keep the sticky right rail. Preserve all current data — only the presentation changes.
- **Expected UX**: Satgas can jump to the active workflow stage without scrolling. Print and scroll fatigue reduce.
- **Complexity**: Medium

### F-24 — Workflow create dialogs disabled-state explanations are buried

- **Severity**: Low
- **Affected**: `routes/dashboard.cases.$id.tsx` via `DisabledWorkflowAction`
- **Root cause**: When an action is unavailable (wrong status, no recommendation, etc.), the `DisabledWorkflowAction` component shows reason text inside a dashed border. Functionally correct but visually identical to empty-state placeholders.
- **Recommended fix**: Use an `Alert` (info variant) with an `Info` icon to differentiate "action unavailable for reason X" from "no data here yet".
- **Expected UX**: Satgas understands why a button is missing, not just that it is missing.
- **Complexity**: Low

### F-25 — Confirmation step missing for destructive admin actions

- **Severity**: High
- **Affected**: `routes/dashboard.users.tsx` (Deactivate, Reset Password), `routes/dashboard.registrations.$id.tsx` (Reject), `routes/dashboard.master-data.universities.tsx` (Deactivate)
- **Root cause**: One click triggers the mutation. Reset Password generates a one-time temporary password (shown once in an amber card). Deactivating a user is reversible but consequential.
- **Recommended fix**: Wrap each destructive button in shadcn `AlertDialog` with explicit confirm copy ("Tindakan ini akan menonaktifkan akun Reporter. Pengguna akan kehilangan akses portal."). Reset password should require typing the user's NIM or email to confirm.
- **Expected UX**: Accidental clicks no longer perform irreversible-looking actions; auditors see explicit consent in audit logs.
- **Complexity**: Low

### F-26 — Track page does not validate tracking-code format client-side

- **Severity**: Low
- **Affected**: `routes/track.tsx`
- **Root cause**: The input accepts any uppercase string. The placeholder hints at `XXXX-XXXX-XXXX-XXXX` but no format constraint is enforced before the API call, so users get a backend 404 instead of a friendly nudge.
- **Recommended fix**: Add a zod refinement (`/^[A-Z0-9-]{16,32}$/` or whatever pattern matches the backend tracking code), show an inline hint when the format doesn't match. Trim whitespace.
- **Expected UX**: Faster feedback for typos; fewer backend roundtrips.
- **Complexity**: Low

### F-27 — Notifications are read-only — no "mark as read" affordance in portal

- **Severity**: Medium
- **Affected**: `routes/portal.notifications.tsx`
- **Root cause**: Per current backend contract, the portal endpoint is GET-only and the M17 notification mutations are admin-side. The portal page therefore correctly does not include a mark-read button — but users expect one. The unread count never goes down from inside the portal.
- **Recommended fix**: This requires a backend extension (portal-scoped `PATCH /portal/notifications/{id}/read`). Out of pure frontend scope; mark as future. In the meantime, add a one-line explanation under the page subtitle ("Notifikasi ini hanya untuk dibaca. Pembaruan status akan ditandai otomatis saat Anda membuka laporan terkait.") if that statement is true; otherwise keep silent.
- **Expected UX**: Reporter understands why the badge keeps showing the same number.
- **Complexity**: Low (UI copy) / High (full mark-as-read with backend)

### F-28 — Dashboard analytics + case detail pages have inconsistent date formatting

- **Severity**: Low
- **Affected**: `routes/dashboard.cases.$id.tsx` (uses `new Date(value).toLocaleString()`), `routes/dashboard.reports.index.tsx` (same), `routes/portal.account.tsx` (uses `lib/format.ts` `formatDate`), `routes/dashboard.workflow.tsx` (no formatting; uses ISO bucket directly).
- **Root cause**: Two parallel date-formatting strategies — raw `toLocaleString()` (locale-dependent on user OS) vs the centralized `formatDate` helper. ISO bucket strings appear unformatted in the workflow footer.
- **Recommended fix**: Route every date display through `lib/format.ts` `formatDate` (or a new `formatDateTime`) with the active `i18n.language` passed in. Honor `id`/`en` date-fns locales.
- **Expected UX**: Same date appears the same in every screen, regardless of OS settings.
- **Complexity**: Low

---

## 5. Priority roadmap

### Quick wins (Low complexity, high payoff — target: next refinement sprint)

1. F-04 — Portal status badge: use `t()` instead of hardcoded Indonesian.
2. F-03 — Wizard `report_type` fallback: remove hardcoded English fallbacks; gate on `isSuccess`.
3. F-09 — Wizard non-`ApiError` toast: move `toast.error` outside the `instanceof` guard.
4. F-11 — Login: remove or disable the dead "Forgot password" link.
5. F-19 — Toaster: change `position` to `top-center` on mobile.
6. F-20 — Add `aria-label` to all icon-only buttons in both layouts.
7. F-14 — Portal nav: keep labels visible at all breakpoints (or add `aria-label`).
8. F-15 — Master Data page: translate tabs and headings.
9. F-16 — Workflow page: translate title, descriptions, empty-state copy, scope footer.
10. F-22 — Add `Breadcrumb` to detail pages (case, registration, report, portal report).
11. F-24 — Disabled workflow actions: switch to info `Alert`.
12. F-26 — Track page: client-side format validation on tracking code.
13. F-28 — Centralize date formatting via `lib/format.ts`.
14. F-17 — Generic `<EmptyState />` and apply to admin lists.
15. F-21 — Verify badge contrast; add status icons.

### Medium improvements (Medium complexity — target: 1–2 sprints)

1. F-01 — Per-step zod validation in the wizard with `form.trigger()` before advancing.
2. F-02 — 3-step progress indicator above the wizard card.
3. F-08 — Migrate `register.tsx`, `registration.correction.tsx`, `CreateReporterCard`, and the wizard to RHF + zod + `applyLaravelErrors`.
4. F-05 — Migrate raw `<select>` instances to shadcn `Select` in all affected routes.
5. F-06 — Replace `<input type="date">` with shadcn `Popover` + `Calendar`, with `id`/`en` locale.
6. F-07 — Incident time: add "I don't remember" checkbox + Pagi/Siang/Sore/Malam preset chips.
7. F-13 — Admin tables: add `overflow-x-auto` everywhere; convert the two highest-traffic tables (reports, cases) to card list under `md`.
8. F-18 — Standardize loading skeletons across all admin pages.
9. F-25 — Wrap destructive admin actions in `AlertDialog` with explicit confirm.
10. F-23 — Case detail: introduce `Tabs` to split the six sections.

### Long-term improvements (High complexity or cross-team — target: roadmap)

1. F-10 — Field-level rejection markers on registration correction (backend contract change required: rejection metadata per field).
2. F-12 — Global Command-K search using `cmdk` and a federated backend search endpoint (backend work required).
3. F-27 — Portal-scoped mark-as-read endpoint and unread badge live update (backend work required).
4. SLA-aware case detail: visually highlight cases past their stage SLA (PROJECT_MASTER §6.2). Requires backend SLA exposure.
5. Multi-campus admin awareness: surface campus chip on every list (already partly via campus filter) and add per-campus theme accents for Super Admin context-switching.
6. PDF/CSV export for analytics and case detail (mentioned in PROJECT_MASTER §9 Post-MVP).
7. Push / WhatsApp delivery affordances in notifications page (paired with Fonnte work).

---

## 6. Closing notes

- The cleanest reference implementation in the repository is `frontend/src/components/workflow-actions/workflow-action-dialogs.tsx` and `investigation-create-action.tsx`. Whenever a refinement asks "how do we do this consistently?", that file is the answer: RHF + zod + shadcn `Form` + `Select` + `Dialog` + `applyLaravelErrors` + sonner.
- The localization architecture is correct (`i18n.ts` with namespaced JSON, `format-labels.ts` for enum→i18n translation). The work is adoption, not redesign.
- Privacy and RBAC behavior is well-preserved across the UI surfaces audited — no findings recommend changing privacy boundaries.
- This document is intentionally an audit, not a redesign brief. It should be triaged by Project Owner before any of the roadmap items are converted into milestones.

> **End of audit.** No source files were modified.
