# UX_IMPROVEMENT_PLAN.md — SILAPPKASAL UX Stabilization Plan

> Status: Planning (implementation-ready)
> Last Updated: 2026-06-28
> Authoritative source for: UX-01..UX-08 milestones
> Companion to: `docs/REPORT_UX_AUDIT.md`
> Scope: `frontend/` only — refinement of the existing implementation.

This document converts the findings from `docs/REPORT_UX_AUDIT.md` into a single, ordered, implementation-ready plan. It is the operative reference for the implementing agent. Every milestone declares concrete files, frozen decisions, acceptance criteria, and a manual test checklist.

Referenced inputs:

- `docs/REPORT_UX_AUDIT.md` (findings F-01..F-28)
- `docs/PROJECT_MASTER.md` (mission, role matrix, 7-stage workflow, SLAs)
- `docs/PROJECT_HANDOFF.md` (current milestone state up to M31-B2, contract notes)
- `docs/ARCHITECTURE_DECISIONS.md` (RBAC, privacy invariants, FE/BE boundary)
- `docs/DEVELOPMENT_WORKFLOW.md` (security checklist, DoD, token rules)

---

## 1. Executive Summary

The SILAPPKASAL frontend is structurally sound: TanStack Start + TanStack Router file-based routing, TanStack Query for server state, shadcn/ui + Radix + Tailwind v4 for UI, react-hook-form + zod proven in the workflow-action dialogs, sonner for toasts, i18next with `id` default and `en` optional. The remaining UX issues are almost entirely **adoption gaps**: older routes (public registration, registration correction, the reporter Report Wizard, the admin CreateReporter form, and the master-data dialog) predate the canonical pattern and still use raw `useState`, native `<select>`, native `<input type="date">`, and manual error maps.

This plan groups the audit findings into eight milestones that progress from foundations (form architecture, design tokens) to user-facing experience (wizard, responsiveness), to polish (accessibility, workflow tabs). It is explicitly **a refinement plan, not a redesign**. No backend contract changes are proposed. No architectural reshuffles are proposed. The canonical reference for every "how should this look in code" question is `frontend/src/components/workflow-actions/workflow-action-dialogs.tsx` and `frontend/src/components/workflow-actions/investigation-create-action.tsx`.

Outcome targets:

- One consistent form pattern across every authored form.
- One consistent date/time picking experience aligned with Indonesian locale and trauma-sensitive copy.
- One consistent dropdown component (shadcn `Select`) everywhere.
- One consistent loading / empty / error pattern.
- One consistent localization story (no hardcoded Indonesian or English in components).
- Mobile-first tables, navigation, and toasts.
- Auditable, accessible icon-only buttons and destructive admin actions.

---

## 2. Objectives

1. **Improve overall User Experience.** Specifically: increase reporter confidence on the wizard, reduce dropouts, eliminate dead affordances, and remove cross-step confusion.
2. **Improve Design Consistency.** One spacing rhythm, one typography ladder, one button/card/dialog/status pattern. The product should feel authored by one team.
3. **Modernize forms while respecting the existing design system.** Migrate legacy `useState` forms onto react-hook-form + zod + shadcn `Form` + `applyLaravelErrors`. No new form libraries.
4. **Keep mobile-first experience as top priority.** Admin tables and portal navigation are usable on a phone.
5. **Do not change backend contracts.** All payloads, query params, response shapes remain identical.
6. **Do not change business rules.** RBAC, status transitions, anonymous-report invariants, audit triggers stay as-is.
7. **Do not replace existing architecture.** TanStack Router, TanStack Query, shadcn/Radix, Tailwind v4, sonner, i18next are fixed.

---

## 3. Scope

In scope for this plan:

| Surface | Pages / Components |
|---|---|
| Public pages | `routes/index.tsx`, `routes/login.tsx`, `routes/register.tsx`, `routes/track.tsx`, `routes/registration.pending.tsx`, `routes/registration.correction.tsx` |
| Reporter Portal | `routes/portal.tsx`, `routes/portal.index.tsx`, `routes/portal.reports.index.tsx`, `routes/portal.reports.new.tsx`, `routes/portal.reports.$registrationNumber.tsx`, `routes/portal.notifications.tsx`, `routes/portal.account.tsx`, `components/portal/*`, `layouts/portal-layout.tsx` |
| Admin | `routes/dashboard.tsx`, `routes/dashboard.index.tsx`, `routes/dashboard.registrations.tsx`, `routes/dashboard.registrations.$id.tsx`, `routes/dashboard.users.tsx`, `routes/dashboard.reports.index.tsx`, `routes/dashboard.cases.index.tsx`, `layouts/dashboard-layout.tsx` |
| Satgas | `routes/dashboard.cases.$id.tsx`, `components/workflow-actions/*` |
| Super Admin | `routes/dashboard.analytics.tsx`, `routes/dashboard.workflow.tsx`, `routes/dashboard.master-data*.tsx`, `routes/dashboard.break-glass.tsx`, `routes/dashboard.settings.tsx` |
| Shared | `components/ui/*`, `components/query-state.tsx`, `components/status-badge.tsx`, `components/portal/portal-status-badge.tsx`, `components/auth-provider.tsx`, `components/language-switcher.tsx`, `lib/format.ts`, `lib/format-labels.ts`, `lib/form-errors.ts`, `locales/{id,en}/*` |

---

## 4. Out of Scope

Explicitly **out of scope** for this plan (handled separately or deferred):

- Backend behavior, API contracts, response shapes, or new endpoints.
- RBAC matrix, status transitions, SLA enforcement, audit triggers.
- New product features (forgot password, federated search, mark-as-read mutation, evidence upload, WhatsApp/Fonnte, mobile Flutter).
- Visual rebrand: logo, color palette, fonts, typography ladder beyond what shadcn defaults already define.
- Routing migration (no change from file-based TanStack Router).
- Switching state libraries (no Zustand/Jotai; we keep TanStack Query + local `useState`/RHF).
- Adding test runners or test infrastructure (the project currently has none for FE).
- Performance optimizations (bundle splitting, code-splitting routes) beyond what TanStack Start already provides.
- Server-side localization or runtime locale negotiation — we keep `lng: "id"` with `LanguageDetector`.
- Any work in `mobile/` or `backend/`.

---

## 5. Frozen Decisions

These decisions are binding. The implementing agent must not deviate without explicit Project Owner approval.

### 5.1 Unified Form Pattern

- **Library**: `react-hook-form` + `@hookform/resolvers` + `zod`. Both are already in `package.json`.
- **Components**: shadcn `Form`, `FormField`, `FormItem`, `FormLabel`, `FormControl`, `FormMessage` from `components/ui/form.tsx`.
- **Server-error mapping**: `applyLaravelErrors(form, error)` from `lib/form-errors.ts`. Every mutation `onError` calls it before showing a fallback toast.
- **Fallback toast**: `toast.error(apiErrorMessage(error, t("common:unexpectedError")))` outside the `instanceof ApiError` guard.
- **Reference**: `components/workflow-actions/investigation-create-action.tsx`.
- **Forbidden**: raw `useState`-based form state for any new or refactored form, parallel `setErrors` maps, manual `update()` setters.

### 5.2 Validation Strategy

- Client-side: zod schemas declared per form (and per wizard step) next to the form.
- Wizard step gate: `await form.trigger([...stepFields])` before incrementing the step.
- Server-side: backend remains authoritative; client validation never replaces server validation.
- Backend 422 errors map to fields via `applyLaravelErrors`. If a returned key belongs to an earlier step, the wizard jumps to that step before rendering the message.
- Optional fields are explicitly `.optional()` in zod; empty strings are normalized to `null` via the existing `nullifyEmpty` helper (see workflow dialogs).

### 5.3 Date Component Strategy

- **Component**: shadcn `Calendar` (`react-day-picker`, already installed) wrapped inside a shadcn `Popover` triggered by a Button styled as input.
- **Locale**: `date-fns/locale` `id` or `enUS` selected via `i18n.language`.
- **Constraints**: incident dates and any historical date use `disabled={{ after: new Date() }}` on the Calendar AND `requiredDate.refine(value <= today)` in zod. Both must agree.
- **Storage format**: `YYYY-MM-DD` string (existing backend contract). UI displays via `format(date, "d MMMM yyyy", { locale })`.
- **A shared component** `components/ui/date-picker.tsx` (new) wraps this and exposes an `<input>`-compatible API for RHF (`value: string; onChange: (value: string) => void`).

### 5.4 Time Component Strategy

- **Primary**: keep `<Input type="time">` for keyboard precision (a11y benefit).
- **Enhancements** (additive, not replacement):
  - Quick-pick chips: Pagi / Siang / Sore / Malam (08:00 / 13:00 / 17:00 / 20:00).
  - A checkbox "Saya tidak ingat waktunya" / "I don't remember the time" that disables the input and submits `null`.
- **Wrapped** in a shared `components/ui/time-picker.tsx` (new) with the same `value/onChange` contract as the date picker.
- **Storage format**: `HH:mm` string or `null`.

### 5.5 Dropdown Strategy

- **One component only**: shadcn `Select` (`components/ui/select.tsx`).
- **Forbidden** in any refactored or new form: raw `<select>`, third-party combobox, native `optgroup`.
- For long lists (e.g. universities with > 30 items, study programs), pair `Select` with `Command` (cmdk is installed) inside a `Popover` to provide search — a new shared `components/ui/searchable-select.tsx` may be introduced if and only if a list exceeds 30 items.
- Disabled and loading affordances are expressed via the trigger's `disabled` prop and a `placeholder` that reads from i18n.

### 5.6 Dialog Consistency

- **Component**: shadcn `Dialog` for non-destructive actions; shadcn `AlertDialog` for destructive (deactivate, reject, reset password, delete) and any irreversible-looking action.
- **Layout**: every dialog has `DialogHeader` with `DialogTitle` + `DialogDescription`, body, and `DialogFooter` with primary action right-aligned.
- **Overflow**: long dialogs use `className="max-h-[90vh] overflow-y-auto"` on `DialogContent` (existing convention).
- **Confirmation copy**: AlertDialog destructive actions name the entity being affected ("akun reporter — Budi Santoso") and use destructive variant on the confirm button.

### 5.7 Toast Strategy

- **Component**: sonner, already mounted at `routes/__root.tsx`.
- **Position**: `top-center` (single value, regardless of viewport — simpler than media-query branching and avoids overlap with mobile chrome on top-right).
- **Variants**: `richColors` enabled; use `toast.success` for completions, `toast.error` for failures, `toast.info` only for non-blocking advisories. No long-lived loading toasts (we use button `isPending` for that).
- **Localization**: all toast text from i18n; no inline English/Indonesian literals.
- **Network/unknown errors**: every mutation `onError` ends with `toast.error(apiErrorMessage(error, t("common:unexpectedError")))`.

### 5.8 Loading State Strategy

- **Lists, tables, cards, charts**: shadcn `Skeleton` placeholders that match the final layout shape. Reuse `StatSkeletonGrid` and the explicit skeletons in `portal.account.tsx` and `portal.reports.index.tsx` as references.
- **Buttons**: in-button `Loader2` spinner + disabled state during pending mutation; text changes to `t("dashboard:common.saving")` / `t("...submitting")`.
- **Full-screen hydration**: existing centered text fallback in `dashboard.tsx` / `portal.tsx` is retained.
- **Forbidden**: plain `"Loading..."` text inside cards once the page has converged onto skeletons.

### 5.9 Empty State Strategy

- A shared `components/empty-state.tsx` (new) is introduced with props `{ icon: LucideIcon; title: string; description: string; action?: ReactNode }`.
- Two flavors of copy per list:
  - **Filtered empty**: "Tidak ada hasil yang cocok dengan filter ini." + suggestion to clear filters.
  - **Truly empty**: domain-specific copy from i18n.
- The portal already implements both flavors in `portal.reports.index.tsx`; that's the visual reference.

### 5.10 Enum / Status Label Strategy

- **Source of truth**: `lib/format-labels.ts`. Every status, role, type, or enum code rendered to the user passes through one of its `format*` helpers.
- **Portal status**: `components/portal/portal-status-badge.tsx` must use `t(`portal:${status}`)`; the keys (`Submitted`, `Under Review`, `In Process`, `Completed`) already exist in both locales.
- **Backend enum names**: when the backend returns `item.name` for master data, **do not** render that string raw if it is also an enum we localize. Prefer the `formatXxx(t, item.code)` helper. When the value is genuinely free text (e.g. report category name from a multi-language seeded master data), render `item.name` and accept that the source is localized server-side.
- **Forbidden**: hardcoded English fallbacks in dropdown options, hardcoded Indonesian text in components that already have i18n wired in.

### 5.11 Responsive Behavior

- **Breakpoints**: Tailwind defaults (sm 640, md 768, lg 1024, xl 1280). No custom breakpoints introduced.
- **Tables**: every admin table is wrapped in `overflow-x-auto`. The two highest-traffic tables (reports list, cases list) ship a card-list fallback under `md:hidden`, with a `hidden md:table` table above.
- **Portal nav**: labels remain visible at every breakpoint (`text-xs sm:text-sm`). Icon-only fallback is forbidden because the reporter portal is mobile-primary.
- **Dialogs**: use `max-w-2xl` for multi-field forms; the body uses a 2-column grid that collapses to 1 column under `md`. This matches existing patterns.
- **Toasts**: `position="top-center"` everywhere; we do not branch on viewport.

### 5.12 Localization Behavior

- **Default language**: `id` (Bahasa Indonesia). Fallback: `id`. Optional second language: `en`.
- **Namespaces**: keep `common`, `auth`, `portal`, `dashboard`. No new namespaces unless a feature requires it.
- **Detection**: `LanguageDetector` reads from `localStorage` only (existing setting).
- **Forbidden**: any user-visible string literal in JSX outside `t(...)`, except brand strings (`SILAPPKASAL`) and unit symbols.
- **Side-effect rule**: when adding a key, add it to both `id` and `en` JSON in the same change. Missing English fallbacks must default-value to the Indonesian copy (via `t(key, { defaultValue: "..." })`) only for the transition window of UX-05.

### 5.13 Accessibility Floor

- Every interactive element has a discernible name (visible text, `aria-label`, or `<span className="sr-only">`).
- Every form field has a `<FormLabel>` linked to its control.
- Color is never the sole status carrier; every status badge carries a text label and (UX-07) a small icon.
- Focus rings (`focus-visible:ring`) on every interactive element — already standard via shadcn but verified.
- WCAG AA contrast for `text-warning-foreground` and `text-success` in both light and dark themes is verified in UX-07.

---

## 6. Design Consistency Contract

This is the design-system addendum that the implementing agent must enforce while executing the milestones below.

### 6.1 Spacing

- **Page padding**: `p-4 md:p-6` on `<main>` (existing in both layouts).
- **Section vertical rhythm**: `space-y-6` between top-level sections of a page.
- **Card body**: `p-4` for compact cards (table cards), `p-5` for stat cards (existing), `p-6` for content-heavy cards.
- **Stack inside cards**: `space-y-4` for form rows, `space-y-3` for read-only key/value lists.
- **Grid gaps**: `gap-4` for form rows; `gap-3` for chip groups.

### 6.2 Typography

- **Page title (H1)**: `text-2xl font-semibold tracking-tight`.
- **Section title (H2 / CardTitle base)**: `text-base font-semibold`. shadcn's `CardTitle` default is used.
- **Subtitle / description**: `text-sm text-muted-foreground`.
- **Read-only label**: `text-xs uppercase tracking-wide text-muted-foreground`.
- **Read-only value**: `text-sm`.
- **Monospace**: registration numbers and case numbers use `font-mono`. Phone numbers do not.
- **Forbidden**: arbitrary `text-3xl`/`text-xl` headings inside content cards.

### 6.3 Buttons

- **Primary action**: `<Button>` default variant. One per form / dialog footer.
- **Secondary**: `variant="outline"`.
- **Tertiary / row actions**: `variant="ghost"`, `size="sm"`.
- **Destructive**: `variant="destructive"`, paired with `AlertDialog`.
- **Disabled rationale**: when disabled because of backend prerequisites, render via the `DisabledWorkflowAction` info alert (see UX-08), not a greyed button alone.
- **Loading**: `disabled` + `Loader2` icon inside button text via the existing convention.
- **Icon-only buttons**: always include `aria-label`. Examples: theme toggle, language toggle, avatar trigger.

### 6.4 Dialogs

- See §5.6. In addition:
  - Title sentence case in i18n; never title case.
  - Description is a single sentence explaining what the dialog will do.
  - Footer right-aligned (`DialogFooter`'s default).
  - Dismiss/cancel button to the left of primary on multi-button footers.

### 6.5 Cards

- **Detail / form card**: `Card` with `CardHeader` (title + description), `CardContent`, optional `CardFooter`.
- **Stat card**: `Card` with `CardContent` only, label + value + delta (see `dashboard.index.tsx`).
- **Empty placeholder**: a card containing `<EmptyState>` (§5.9).
- **Forbidden**: nested cards. If hierarchy is needed, use a `Separator`.

### 6.6 Forms

- See §5.1, §5.2.
- Required fields are unmarked visually; optional fields display `(Opsional)` next to the label, sourced from `t("auth:optional")` or `t("portal:optional")`.
- Helper text goes under the field as `<p className="text-xs text-muted-foreground">`.
- Inline server errors render via `<FormMessage />`.
- Submit button text changes during pending: "Kirim" → "Mengirim..." or equivalent i18n key.

### 6.7 Date / Time Inputs

- See §5.3 and §5.4.
- Always rendered through `components/ui/date-picker.tsx` and `components/ui/time-picker.tsx` after UX-02 ships.
- Display format: `d MMMM yyyy` with i18n locale; storage format: `YYYY-MM-DD`. Time display `HH:mm`.

### 6.8 Icons

- **Library**: `lucide-react` only. No emoji.
- **Size**: `h-4 w-4` for inline icons; `h-5 w-5` for stat-card glyphs; `h-3 w-3` for badge glyphs.
- **Color**: inherits from parent text-color. Tone-on-tone via `text-primary`, `text-muted-foreground`, `text-destructive`, etc.
- **Pairing**: an icon next to text always has `mr-2` (or `gap-2` on the container).

### 6.9 Status Badges

- **Component**: `Badge` (shadcn) wrapped by domain helpers — `StatusBadge` for case statuses, `PortalStatusBadge` for portal labels.
- **Carriers**: text label (i18n) + tonal color + (after UX-07) a small leading icon.
- **Tone mapping** (fixed):
  - Info / submitted: `bg-info/15 text-info border-info/30`.
  - Warning / under review: `bg-warning/15 text-warning-foreground border-warning/30`.
  - Primary / in process: `bg-primary/15 text-primary border-primary/30`.
  - Success / completed: `bg-success/15 text-success border-success/30`.
  - Neutral / closed: `bg-muted text-muted-foreground border-border`.
- **Forbidden**: ad-hoc inline color classes per page.

### 6.10 Color Usage

- Use semantic tokens (`primary`, `info`, `warning`, `success`, `destructive`, `muted`) defined in `styles.css`.
- **Forbidden**: raw Tailwind palette (`bg-blue-500`, `text-red-600`) in any new or refactored code.
- Dark theme parity is mandatory: every new color choice is verified in both themes.

---
## Implementation Order

1. UX-01 Unified Form Architecture
2. UX-02 Report Wizard Experience
3. UX-03 Validation Experience
4. UX-04 Date, Time & Dropdown Consistency
5. UX-05 Localization Consistency
6. UX-06 Responsive Improvements
7. UX-07 Accessibility
8. UX-08 Workflow Polish
## 7. Implementation Milestones

Milestones are ordered. Each can be merged independently once its acceptance criteria pass.

## Implementation Order

1. UX-01 Unified Form Architecture
2. UX-02 Report Wizard Experience
3. UX-03 Validation Experience
4. UX-04 Date, Time & Dropdown Consistency
5. UX-05 Localization Consistency
6. UX-06 Responsive Improvements
7. UX-07 Accessibility
8. UX-08 Workflow Polish

### UX-01 — Unified Form Architecture (foundation)

Groups: F-08, F-05 (partial), F-09, F-26.

**Goal**: One way to author a form. Eliminate the legacy `useState` + `setErrors` + native-`<select>` pattern from public and admin forms.

**Files**:

- `routes/register.tsx`
- `routes/registration.correction.tsx`
- `routes/track.tsx`
- `routes/dashboard.users.tsx` (the `CreateReporterCard` subcomponent)
- `lib/form-errors.ts` (kept; potentially extended with a `setFormError` helper for top-level non-field errors)

**Approach**:

1. Convert each form to `useForm<Schema>` + `zodResolver(schema)`.
2. Replace every raw `<select>` with shadcn `Select` inside `FormField`.
3. Replace local `errors` state with `applyLaravelErrors(form, error)` on mutation `onError`.
4. Add the unified non-`ApiError` toast fallback.
5. For `track.tsx`, add a zod refinement on the tracking-code pattern (F-26).
6. Keep all form payloads byte-identical to the current implementation (verified field-by-field).

**Acceptance criteria**:

- No `useState<Record<string, string[]>>` for form errors anywhere in the four files.
- No native `<select>` anywhere in the four files.
- All four forms display server-side 422 errors inline under the corresponding field.
- All four forms produce a toast for non-`ApiError` failures.
- Track page rejects invalid tracking-code format before sending the request.
- `npm run lint` and `npm run build` pass.
- Network payloads (`POST /reporter-registrations`, `PATCH /reporter-registrations/correct`, `POST /users/reporters`, `GET /reports/track/{code}`) remain identical (verified manually against `lib/registration-api.ts`, `lib/admin-users-api.ts`, `lib/portal-api.ts`).

---

### UX-02 — Report Wizard Experience

Groups: F-01, F-02, F-03, F-06 (partial — incident date), F-07, F-08 (wizard portion), F-09.

**Goal**: The reporter cannot bypass missing required fields; backend errors snap focus to the correct step; the wizard feels guided.

**Files**:

- `routes/portal.reports.new.tsx`
- `components/ui/date-picker.tsx` (new — see UX-04)
- `components/ui/time-picker.tsx` (new — see UX-04)
- `locales/{id,en}/portal.json` (new keys for step names, time presets, "I don't remember")

**Approach**:

1. Define three zod schemas (`stepOneSchema`, `stepTwoSchema`, `stepThreeSchema`) and a `wizardSchema = stepOneSchema.merge(stepTwoSchema).merge(stepThreeSchema)`.
2. Use a single `useForm<WizardValues>` with `mode: "onBlur"`.
3. On "Next", call `await form.trigger([...currentStepFields])`; only advance if it returns `true`.
4. Add a 3-dot progress header above the card with localized step names: "Identifikasi laporan", "Detail kejadian", "Pihak terlapor".
5. Replace the hardcoded `report_type` English fallback with i18n-aware option labels via `t("portal:Open")` etc. Gate options on `reportTypesQuery.isSuccess`.
6. Replace `<Input type="date">` for `incident_date` with the new `DatePicker`, locale-aware, future-disabled.
7. Replace `<Input type="time">` for `incident_time` with the new `TimePicker` (chips + unknown checkbox + native fallback).
8. Map backend Laravel errors via `applyLaravelErrors`. If any errored key belongs to step 1 or 2, set `step` to that step before showing the toast.
9. Move the non-`ApiError` `toast.error` outside the `instanceof` guard.

**Acceptance criteria**:

- Clicking "Next" on step 1 with empty `category_code` shows an inline error and does not advance.
- Clicking "Next" on step 2 with `chronology` shorter than 50 chars shows an inline error and does not advance.
- Submitting on step 3 with a server error keyed to `category_code` automatically returns the user to step 1 with the error visible.
- The progress header reflects current step and previously completed steps.
- `report_type` options render in Indonesian by default and English when language is `en`.
- `incident_date` cannot be set to a future date (UI disabled + zod refusal).
- `incident_time` can be cleared via the "Saya tidak ingat waktunya" checkbox; quick chips populate the field.
- A simulated network failure (offline / 500) produces a toast.
- Payload to `POST /reports` is byte-identical to the current implementation (with `incident_time: null` when checkbox is checked).
- `npm run lint` and `npm run build` pass.

---

### UX-03 — Validation Experience & Error Surfaces

Groups: F-10, F-12, F-24, F-25.

**Goal**: Errors and reasons appear where the user looks; destructive actions ask for confirmation; dead affordances are removed.

**Files**:

- `routes/registration.correction.tsx`
- `layouts/dashboard-layout.tsx` (search input)
- `routes/dashboard.cases.$id.tsx` (`DisabledWorkflowAction` styling — shared usage)
- `components/workflow-actions/workflow-action-dialogs.tsx` (`DisabledWorkflowAction` source)
- `routes/dashboard.users.tsx` (deactivate, reset password)
- `routes/dashboard.registrations.$id.tsx` (reject)
- `routes/dashboard.master-data.universities.tsx` (deactivate)
- `components/ui/alert-dialog.tsx` (existing; used)

**Approach**:

1. Promote `DisabledWorkflowAction` from a dashed bordered box to a shadcn `Alert` info variant with `Info` icon and a clear title/description split. Keep the same export name and props.
2. On `registration.correction.tsx`, wrap `registration.rejection_reason` in a destructive `Alert` and make field-level highlight automatic via `applyLaravelErrors` once UX-01 is applied.
3. On the dashboard topbar, remove the decorative `<Input placeholder="Cari dashboard...">` and replace it with empty space. (Global Command-K search is deferred to Future Improvements.)
4. Wrap every destructive admin action in `AlertDialog`:
   - Deactivate user: "Tindakan ini akan menonaktifkan akun reporter ini. Pengguna akan kehilangan akses portal."
   - Reset password: confirmation requires typing the user's email; on confirm, the temporary password panel appears as today.
   - Reject registration: minimum-10-character reason already enforced; the AlertDialog re-shows the entered reason before confirming.
   - Deactivate university: confirmation message names the institution.

**Acceptance criteria**:

- The dashboard topbar no longer shows a non-functional search input.
- `DisabledWorkflowAction` renders as an info Alert with icon, title, description.
- The destructive admin actions listed above each open an `AlertDialog` that must be confirmed before mutation fires.
- `lint` and `build` pass.

---

### UX-04 — Date, Time, and Dropdown Consistency

Groups: F-05 (full), F-06 (full), F-07.

**Goal**: One date picker, one time picker, one dropdown component used everywhere.

**Files**:

- `components/ui/date-picker.tsx` (new shared component)
- `components/ui/time-picker.tsx` (new shared component)
- `components/ui/searchable-select.tsx` (new — only if >30-item lists exist)
- `routes/register.tsx`, `routes/registration.correction.tsx`, `routes/dashboard.registrations.tsx`, `routes/dashboard.users.tsx`, `routes/dashboard.master-data.universities.tsx`, `routes/portal.reports.new.tsx` (consumers)
- `components/workflow-actions/workflow-action-dialogs.tsx` (replace `InputField` `type="date"` instances with `DatePickerField`)

**Approach**:

1. Build `DatePicker` as a `Popover` + `Calendar` combo. Props: `value: string` (YYYY-MM-DD or ""), `onChange: (value: string) => void`, `disabled?: boolean`, `disableFuture?: boolean`, `placeholder?: string`. Locale via `i18n.language` mapped to `date-fns/locale`.
2. Build `TimePicker` with native `<input type="time">` + quick chips + unknown checkbox.
3. Migrate every remaining `<select>` to shadcn `Select`. Use `SelectField`-style wrapping via `FormField`.
4. For the universities select (potentially long list once seeded across 7 campuses), introduce `SearchableSelect` only if usability testing during this milestone indicates a need.
5. Update workflow dialogs: the `InputField` with `type="date"` continues to validate via zod but renders through `DatePickerField` for consistency.

**Acceptance criteria**:

- Searching the frontend for `<select` yields **zero** matches outside of dev-tooling.
- Searching for `type="date"` yields only references inside `DatePicker` internals.
- Searching for `type="time"` yields only references inside `TimePicker` internals.
- Calendar renders Indonesian month names by default and English when language is `en`.
- All consumers correctly emit `YYYY-MM-DD` and `HH:mm | null` payloads.
- `lint` and `build` pass.

---

### UX-05 — Localization & Enum Consistency

Groups: F-03 (cleanup follow-up), F-04, F-15, F-16, F-28.

**Goal**: No user-visible string outside i18n. No hardcoded Indonesian label inside a translatable component. Enum codes always route through `format-labels.ts`.

**Files**:

- `components/portal/portal-status-badge.tsx`
- `routes/dashboard.workflow.tsx`
- `routes/dashboard.master-data.tsx`
- `routes/dashboard.master-data.universities.tsx` (English-only fragments)
- `routes/dashboard.master-data.faculties.tsx`
- `routes/dashboard.master-data.study-programs.tsx`
- `lib/format.ts` (centralize `formatDate`, `formatDateTime` with locale parameter)
- `routes/dashboard.cases.$id.tsx`, `routes/dashboard.reports.index.tsx` (replace `new Date().toLocaleString()` with `formatDateTime`)
- `locales/{id,en}/*.json` (additions)

**Approach**:

1. Refactor `portal-status-badge.tsx` to call `t(`portal:${status}`)`.
2. Translate every visible string on `dashboard.workflow.tsx` and `dashboard.master-data*.tsx`. Add the necessary keys to both locale files.
3. Replace `labelFromKey` on `dashboard.workflow.tsx` with `formatGenericLabel`.
4. Extend `lib/format.ts` with a `formatDateTime(value, locale)` helper using `date-fns` + `id`/`enUS` locales.
5. Migrate every `new Date(x).toLocaleString()` and `new Date(x).toLocaleDateString()` to `formatDateTime`.

**Acceptance criteria**:

- A grep for hardcoded `"Dikirim"|"Dalam Peninjauan"|"Sedang Diproses"|"Selesai"` in `frontend/src/components` returns zero matches outside locale JSON.
- `dashboard.workflow.tsx` and `dashboard.master-data*.tsx` show fully Indonesian copy by default.
- Switching language from `id` to `en` updates every visible label across the audited surfaces.
- All dates render through `formatDateTime` with consistent format across pages.
- `lint` and `build` pass.

---

### UX-06 — Responsive & Mobile-first Improvements

Groups: F-13, F-14, F-19, F-22.

**Goal**: The app is usable on a phone for every role.

**Files**:

- `routes/dashboard.registrations.tsx`, `routes/dashboard.users.tsx`, `routes/dashboard.reports.index.tsx`, `routes/dashboard.cases.index.tsx`, `routes/dashboard.master-data.universities.tsx`
- `layouts/portal-layout.tsx`
- `routes/__root.tsx` (toast position)
- `routes/dashboard.cases.$id.tsx`, `routes/dashboard.registrations.$id.tsx`, `routes/dashboard.reports.$id.tsx`, `routes/portal.reports.$registrationNumber.tsx` (breadcrumbs)
- `components/ui/breadcrumb.tsx` (existing; used)

**Approach**:

1. Wrap every table in `<div className="overflow-x-auto">`.
2. For `dashboard.reports.index.tsx` and `dashboard.cases.index.tsx`, ship an alternate mobile layout: `md:hidden` card list above and `hidden md:table` table below. The card layout mirrors `PortalReportCard` styling.
3. Portal nav: remove the `hidden sm:inline` on labels; use `text-xs sm:text-sm`. Add `aria-label` on every nav button.
4. Change sonner `position` to `top-center`.
5. Add `Breadcrumb` to the four detail pages with `i18n`-sourced labels.

**Acceptance criteria**:

- On a 360×740 viewport, no horizontal page overflow occurs on any audited admin page.
- Portal nav labels are visible on a 360×740 viewport.
- Reports list and Cases list show card layout under `md`.
- Detail pages display a breadcrumb at the top.
- Toasts appear top-center across mobile and desktop.
- `lint` and `build` pass.

---

### UX-07 — Accessibility & Status Multi-channel

Groups: F-20, F-21.

**Goal**: Screen-reader and color-blind users have first-class status comprehension; every icon-only button has a name.

**Files**:

- `layouts/dashboard-layout.tsx`, `layouts/portal-layout.tsx`
- `components/portal/portal-status-badge.tsx`, `components/status-badge.tsx`
- `components/language-switcher.tsx` (already has `sr-only`; verify)
- Any table row with icon-only action buttons.

**Approach**:

1. Audit every `Button` whose visible content is an icon only; add `aria-label` from i18n.
2. Add a small leading icon to each status badge: `Clock` for Submitted, `Eye` for Under Review, `Loader2` for In Process, `CheckCircle2` for Completed, `Lock` for Closed.
3. Verify all status badge tones pass WCAG AA contrast in both themes; if `text-warning-foreground` fails on light theme, adjust the warning tone in `styles.css` semantic tokens (a single token tweak, not a per-component change).
4. Verify `FormLabel` is present on every form control (UX-01 and UX-04 enforce this for refactored forms).

**Acceptance criteria**:

- A screen-reader pass over the topbars and the case detail action column names every control.
- Every status badge shows label + icon + color.
- Contrast check (manual using browser devtools “inspect accessibility”) passes AA on every status state in both themes.
- `lint` and `build` pass.

---

### UX-08 — Workflow & Detail Polish

Groups: F-17, F-18, F-23, F-27 (UI-only portion).

**Goal**: Detail screens are fast to scan; loading and empty states are consistent; reporter notifications page sets correct expectations.

**Files**:

- `components/empty-state.tsx` (new shared component)
- `routes/dashboard.cases.$id.tsx` (introduce shadcn `Tabs` to split workflow sections)
- `routes/dashboard.cases.index.tsx`, `routes/dashboard.reports.index.tsx`, `routes/dashboard.users.tsx`, `routes/dashboard.registrations.tsx`, `routes/dashboard.master-data.universities.tsx` (adopt EmptyState)
- `routes/dashboard.workflow.tsx`, `routes/dashboard.registrations.$id.tsx` (Skeleton placeholders)
- `routes/portal.notifications.tsx` (advisory copy under subtitle)
- `locales/{id,en}/dashboard.json`, `locales/{id,en}/portal.json` (additions)

**Approach**:

1. Introduce `<EmptyState icon title description action />` and apply it to all listed lists with both flavors (filtered vs truly empty).
2. Replace plain `"Loading..."` strings with `Skeleton` placeholders in `dashboard.workflow.tsx` and `dashboard.registrations.$id.tsx`.
3. On `dashboard.cases.$id.tsx`, wrap the workflow stages (Investigation / Recommendation / Decision / Recovery / Evidence) in shadcn `Tabs`. Metadata + Sensitive report + Assignments stay outside the tabs. Default tab is determined by the current case status (e.g. status `investigation` → Investigation tab).
4. On `portal.notifications.tsx`, add a single i18n-sourced advisory under the subtitle: "Notifikasi ini hanya untuk dibaca dan akan ditandai otomatis saat Anda membuka laporan terkait." (a literal-truth statement; if not true, replace with truthful copy via Project Owner).

**Acceptance criteria**:

- Every admin list shows an `EmptyState` (filtered or empty) instead of a one-liner.
- Every page has skeleton placeholders during initial load.
- Case detail page uses `Tabs` to switch between workflow stages; the URL remains the same; tab state is held in component state (no router param change).
- Portal notifications page surfaces the advisory copy.
- `lint` and `build` pass.

---


## 8. Acceptance Criteria (cross-milestone)

In addition to per-milestone criteria, the entire plan is considered complete only when:

- A grep of `frontend/src` for the strings `<select`, `type="date"` (outside `DatePicker`), `type="time"` (outside `TimePicker`), `useState<Record<string, string[]>>`, and `"Loading..."` returns zero matches.
- A grep of `frontend/src` for hardcoded English/Indonesian status labels (`Dikirim`, `Dalam Peninjauan`, `Sedang Diproses`, `Selesai`, `Submitted`, `Under Review`, `In Process`, `Completed`) outside `locales/` returns zero matches.
- `npm run lint` returns 0 errors (existing 6 pre-existing shadcn/Lovable react-refresh warnings are tolerated, no new warnings introduced).
- `npm run build` succeeds.
- No backend file has been modified.
- No backend payload has changed (verified by reading `lib/*-api.ts` against PROJECT_HANDOFF §3).
- The latest backend test baseline (`125 passed, 1025 assertions` per PROJECT_HANDOFF) is not regressed because backend is untouched.

---

## 9. Manual Testing Checklist

Manual smoke test to run at the end of each milestone, scoped to changed surfaces, and end-to-end at the end of the plan. Tester provides language toggle (id ↔ en) coverage where indicated.

### 9.1 Public pages (unauthenticated)

- `/` redirects to `/login`.
- `/login`: identifier login with email succeeds; identifier login with NIM/NIP succeeds (where seeded); failed login shows toast; toast position is top-center.
- `/login`: "Forgot password" link does not exist as an active link (or is disabled with a tooltip).
- `/register`: all dropdowns are shadcn `Select` (visual check: keyboard navigable, dark-mode parity); validation errors render inline; 429 rate-limit returns a toast.
- `/register`: language toggle changes every visible string including option placeholders and helper text.
- `/registration/pending`: shows the registration number; "Keluar dari sesi" returns to `/login`.
- `/registration/correction`: rejection reason renders in a destructive Alert; password confirmation field validates against the new password.
- `/track`: invalid tracking code format is rejected client-side before the API call; valid code shows status; 404 produces an Alert.

### 9.2 Reporter Portal (`role=reporter`)

- `/portal`: summary cards render with skeletons during load and resolved numbers after.
- `/portal/reports`: search filter narrows the list; empty filter shows the "no matching reports" EmptyState; truly empty account shows "no reports yet".
- `/portal/reports/new`: 3-step wizard.
  - Step 1: cannot advance with empty category.
  - Step 2: cannot advance with chronology < 50 chars; future incident date is rejected at the picker; time picker accepts a chip, manual time, and the "I don't remember" checkbox.
  - Step 3: submit with a server error keyed to a step-1 field returns to step 1.
  - Success view shows the registration number, tracking code (if any), and a link to `/portal/reports`.
- `/portal/reports/{registrationNumber}`: breadcrumb is present; status badge is in the active language; anonymous-report badge is shown when applicable.
- `/portal/notifications`: read-only; advisory copy is visible.
- `/portal/account`: profile edit changes are persisted; password change requires current password and confirmation.
- Portal nav: labels are visible on a 360×740 viewport.

### 9.3 Admin (`role=admin` or `super_admin`)

- `/dashboard`: stat cards + charts render; skeletons during load; current-scope summary panel localized.
- `/dashboard/registrations`: list filters work; status dropdown is shadcn `Select`; row "Review" navigates to detail.
- `/dashboard/registrations/{id}`: approve action shows a toast and returns to list; reject action requires ≥ 10 chars reason and an `AlertDialog` confirmation that re-shows the reason.
- `/dashboard/users`: filter dropdowns are shadcn `Select`; activate/deactivate opens an `AlertDialog` confirming the user name; reset password requires typing the user's email; the resulting temporary password panel still appears.
- `/dashboard/reports`: filters by status and report type; status badge tones match Design Consistency mapping; table collapses to card list under `md`.
- `/dashboard/cases`: same responsive behavior as reports list.
- Dashboard topbar: no non-functional search input.

### 9.4 Satgas (`role=satgas_ppks`)

- `/dashboard/cases`: only assigned cases visible.
- `/dashboard/cases/{id}`:
  - Tabs render Investigation / Recommendation / Decision / Recovery / Evidence; default tab matches case status.
  - Sensitive report card renders only for assigned satgas.
  - Investigation create dialog: lead investigator selectable from assigned satgas; plan summary requires ≥ 50 chars; date input is the new DatePicker.
  - Activity create dialog: date input is the new DatePicker.
  - Disabled actions render as info Alert with reason.
- Workflow status updates show a toast on success and update the page without a manual refresh.

### 9.5 Super Admin (`role=super_admin`)

- `/dashboard/analytics`: charts render with skeleton placeholders during load.
- `/dashboard/workflow`: titles, descriptions, empty states, and scope footer are localized.
- `/dashboard/master-data`: tab nav (Universities / Faculties / Study Programs) is localized.
- `/dashboard/master-data/universities`: create dialog is shadcn `Select` for the `type` and `has_faculties` fields; deactivate action requires `AlertDialog` confirmation that names the institution.
- `/dashboard/break-glass`, `/dashboard/settings`: pages still load without errors (no functional changes expected in this plan).

### 9.6 Cross-cutting

- Language toggle (`id` ↔ `en`) updates every visible string on every page in scope. No string remains in the wrong language.
- Tab through every primary form using a keyboard: focus order is logical; focus rings are visible.
- On a 360×740 viewport, every admin page is usable without horizontal page scrolling.
- Toasts appear top-center on every viewport.
- Browser zoom to 200% does not break form layouts.

---

## 10. Risks

| # | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R-01 | Migrating `register.tsx` to RHF accidentally changes the registration payload, breaking M31-B1 contract. | Low | High | UX-01 acceptance criteria explicitly require payload equality; verify against `lib/registration-api.ts`. |
| R-02 | DatePicker introduces a date-formatting drift between display (`d MMMM yyyy`) and storage (`YYYY-MM-DD`). | Medium | Medium | Internal contract documented in §5.3; unit-level manual checks in UX-04 acceptance. |
| R-03 | Shadcn `Calendar` doesn't ship with locale-aware month names without explicit `locale` prop wiring. | High | Low | UX-04 explicitly maps `i18n.language` to `date-fns/locale`; verified in manual checklist. |
| R-04 | Migrating the report wizard changes the per-step UX enough that already-trained users are momentarily disoriented. | Medium | Low | Step names match the existing copy; the form fields are unchanged. |
| R-05 | Adding leading icons to status badges visually crowds tight table cells. | Medium | Low | Icon size `h-3 w-3` and badge gap kept tight; verified at table density during UX-07. |
| R-06 | Workflow tabs on case detail might hide stages that should be co-visible during a handoff conversation. | Medium | Medium | Tabs only group workflow stages; assignments, metadata, sensitive report remain pinned outside the tab strip. |
| R-07 | Removing the topbar search input may surprise users who never realized it was decorative. | Low | Low | Document this in the release notes for the milestone. |
| R-08 | The current TypeScript baseline already fails `npx tsc --noEmit` per PROJECT_HANDOFF; new code may inherit unrelated errors. | High | Medium | This plan does not require `tsc --noEmit` to pass; acceptance criteria use `lint` + `build`. Fixing pre-existing TS errors is out of scope. |
| R-09 | Localization additions diverge between `id` and `en` JSON files. | Medium | Medium | Frozen Decision §5.12 mandates concurrent updates to both locale files. |
| R-10 | Destructive `AlertDialog` adoption is bypassable if a developer reverts to plain `Button` for speed. | Medium | High | Decision §5.6 makes `AlertDialog` mandatory for destructive variant buttons; code review enforces. |
| R-11 | The Indonesian-locale `date-fns` import (`import { id } from "date-fns/locale"`) increases bundle size. | Medium | Low | The library is already a dependency; one extra locale add is negligible. |
| R-12 | Implementing all milestones in one merge risks regressions. | High | High | Milestones are independently mergeable; merge gate per milestone is `lint`+`build`+manual checklist. |

---

## 11. Future Improvements (post-plan)

Deferred to a later iteration; not part of this plan's acceptance:

1. **Field-level rejection markers on registration correction** (audit F-10). Requires backend contract: rejection metadata keyed by field. Once available, the existing form pattern surfaces it automatically.
2. **Global Command-K search** (audit F-12). Requires a federated backend search endpoint and a `cmdk`-based palette.
3. **Portal-scoped mark-as-read mutation** (audit F-27). Requires `PATCH /api/v1/portal/notifications/{id}/read` from backend.
4. **SLA-aware visual cues** on case lists/details, derived from PROJECT_MASTER §6.2.
5. **Multi-campus theming** for Super Admin context-switching (campus-accent badge per row).
6. **Export to PDF/CSV** for case detail and analytics (PROJECT_MASTER §9 Post-MVP).
7. **Push notification UI** paired with the Fonnte/WhatsApp pipeline.
8. **Forgot password flow** (currently a dead link removed in UX-03).
9. **Restoring `tsc --noEmit`** as a green check (cleanup beyond UX scope).
10. **Visual regression testing** (Playwright + image snapshots) to guard the design-consistency contract.
11. **Mobile bottom-tab navigation** for the reporter portal (alternative to UX-06's label-always-visible top nav).
12. **Per-locale demo dataset** seeded against `DEMO_DATASET_SPEC.md` for smoke testing the localization rules.

---

Do Not Modify

- backend/
- API contracts
- RBAC
- Database
- Seeder
- Authentication flow
- Routing architecture

A milestone is COMPLETE only if:

- npm run build passes
- no new TypeScript errors introduced
- manual checklist completed
- no backend changes
- no API changes
- no regression found

> **End of plan.** This document is the implementation reference for UX-01..UX-08. The implementing agent must consult per-milestone acceptance criteria before declaring any milestone complete and must not extend scope beyond what is listed here without Project Owner approval.
