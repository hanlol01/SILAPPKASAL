# UX-01

## Executive Summary

UX-01 was reviewed against `docs/REPORT_UX_AUDIT.md` and `docs/UX_IMPROVEMENT_PLAN.md`, scoped to the Unified Form Architecture milestone.

The implementation substantially satisfies the UX-01 architecture goal for public registration, registration correction, tracking-code lookup, and the admin Create Reporter form. The reviewed forms now use `react-hook-form`, `zodResolver`, shared `Form`/field components, shadcn `Select`, and field-level server error mapping through `applyLaravelErrors` or `form.setError`.

Manual code review found one non-blocking localization and validation consistency issue: most new zod schemas still rely on default zod validation messages instead of translated copy. This can expose English/default validation text in the Bahasa Indonesia default experience.

Smoke tests were generated for Product Owner execution only. No implementation files were modified and no smoke tests were executed.

## Implementation Score (0-100)

86

## PASS / FAIL

PASS

## Findings

| ID | Area | Result | Evidence |
|---|---|---|---|
| QA-UX01-001 | Scope compliance | PASS | UX-01 files from the plan were reviewed: `routes/register.tsx`, `routes/registration.correction.tsx`, `routes/track.tsx`, and `routes/dashboard.users.tsx` CreateReporter. No backend/API/RBAC files were changed for QA. |
| QA-UX01-002 | Architecture | PASS | Registration, correction, tracking, and Create Reporter use `useForm` with `zodResolver`; Create Reporter and registration/correction use shared `TextInputField`, `PasswordField`, and `SelectFormField`. |
| QA-UX01-003 | Regression | PASS | Payload construction remains field-by-field aligned with `lib/registration-api.ts`, `lib/admin-users-api.ts`, and `lib/portal-api.ts`: registration, correction, Create Reporter, and track endpoints keep their existing method and field contracts. |
| QA-UX01-004 | Localization | PARTIAL | Track validation uses translated `portal:trackingInvalidFormat`, but registration, correction, and Create Reporter zod schemas use default zod messages such as `.min(1)` / `.email()` without localized message strings. |
| QA-UX01-005 | Accessibility | PASS | Refactored form controls use shared field components with `FormLabel`, `FormControl`, and `FormMessage`, improving label association and inline error announcement compared with raw inputs/selects. |
| QA-UX01-006 | Code consistency | PASS | The refactored forms follow the existing workflow-dialog pattern: RHF + zod + shadcn form fields + server-error mapping + toast fallback. |
| QA-UX01-007 | Validation | PARTIAL | Required-field and email validation are present client-side. Password confirmation and new-password confirmation still appear to rely on backend validation rather than a client-side cross-field zod refinement. |
| QA-UX01-008 | Design consistency | PASS | The reviewed forms use shadcn `Select` via `SelectFormField` / `SelectInput`; no native `<select>` remains in the UX-01 scoped files. |
| QA-UX01-009 | Runtime risks | LOW | Risk is mainly copy-quality/runtime UX, not data loss: default zod messages may display in the wrong language before backend validation is reached. |

## Recommendations

1. Localize zod validation messages in `register.tsx`, `registration.correction.tsx`, and `dashboard.users.tsx` using existing i18n namespaces.
2. Add client-side cross-field refinements for `password_confirmation` and `new_password_confirmation` so users receive immediate inline feedback before backend submission.
3. Keep UX-02 wizard remediation separate; `portal.reports.new.tsx` remains governed by UX-02 per the improvement plan.

# UX-01 Hotfix QA Recheck

## Summary

QA recheck focused only on the UX-01 hotfix for the previously reported bugs in `docs/BUG_REPORT.md`.

Both reported bugs are verified as resolved:

- `UX01-BUG-001`: zod client-side validation messages now use localized `common:validation.*` keys in `id` and `en`.
- `UX01-BUG-002`: password confirmation mismatch validation is now handled client-side in registration and registration correction schemas.

Verification commands:

| Check | Command | Result |
|---|---|---|
| TypeScript | `npx.cmd tsc --noEmit` from `frontend/` | PASS |
| Build | `npm run build` from `frontend/` | PASS |
| Lint | `npm run lint` from `frontend/` | PASS with 0 errors and 6 existing react-refresh warnings in shadcn UI files |

## Score

96

## PASS / FAIL

PASS

## Regression Findings

No regression found in the hotfix scope.

Reviewed implementation behavior:

- Registration schema now uses localized required/email messages and validates `password_confirmation` against `password`.
- Registration correction schema now uses localized required/email messages and validates `new_password_confirmation` against `new_password` when either new-password field is present.
- Admin Create Reporter schema now uses localized required/email messages.
- `id/common.json` and `en/common.json` contain matching validation keys.
- Existing payload construction for registration, correction, and Create Reporter remains unchanged.

Tooling notes:

- `npx tsc --noEmit` via PowerShell failed because `npx.ps1` is blocked by local execution policy; re-run with `npx.cmd tsc --noEmit` passed.
- Build emitted non-blocking chunk-size/import warnings from the Vite/TanStack dependency output.
- Lint emitted the known 6 react-refresh warnings in shared shadcn UI files and no errors.

## Remaining Risks

- Manual browser smoke tests were not executed by QA; Product Owner should still run the UX-01 smoke cases.
- Localization verification was performed by static code review and build/lint/typecheck, not by interactive language-toggle testing.
- Existing lint warnings remain outside the hotfix scope.

# UX-02

## Executive Summary

UX-02 was reviewed against `docs/REPORT_UX_AUDIT.md` findings F-01, F-02, F-03, F-06, F-07, F-08, and F-09, and against the UX-02 acceptance criteria in `docs/UX_IMPROVEMENT_PLAN.md`.

The implementation passes QA. The reporter report wizard now uses a single `react-hook-form` + zod schema, validates the current step before advancing, maps Laravel field errors back to the relevant step, provides a localized progress header, localizes report-type labels, uses a locale-aware `DatePicker` with future-date blocking, and uses a `TimePicker` with quick picks plus an "I don't remember the time" option.

No implementation files were modified during QA. Smoke tests below were generated for manual Product Owner execution only.

## Implementation Score (0-100)

93

## PASS / FAIL

PASS

## Findings

| ID | Area | Result | Evidence |
|---|---|---|---|
| QA-UX02-001 | Scope compliance | PASS | Review stayed within UX-02 surfaces: `portal.reports.new.tsx`, `components/ui/date-picker.tsx`, `components/ui/time-picker.tsx`, and `locales/{id,en}/portal.json`. |
| QA-UX02-002 | Step validation | PASS | `goNext()` calls `form.trigger(stepFields[step], { shouldFocus: true })` before advancing, so users cannot bypass required fields on step 1 or step 2. |
| QA-UX02-003 | Architecture | PASS | Wizard uses `useForm<WizardValues>`, `zodResolver`, `FormField`, `SelectFormField`, `DatePicker`, and `TimePicker`; legacy local error map and manual `setErrors` pattern are removed. |
| QA-UX02-004 | Backend error routing | PASS | `onError` applies Laravel errors, derives the target step through `stepForLaravelErrors(error)`, then sets the wizard step so the relevant inline field error is visible. |
| QA-UX02-005 | Localization | PASS | Step labels, date/time helper labels, quick-pick labels, validation messages, and report-type labels are sourced from `portal` / `common` i18n keys in both `id` and `en`. |
| QA-UX02-006 | Date UX | PASS | `DatePicker` uses `date-fns` locale based on `i18n.language`; future dates are disabled in the calendar and rejected by zod. |
| QA-UX02-007 | Time UX | PASS | `TimePicker` supports manual time input, Pagi/Siang/Sore/Malam quick picks, and an unknown-time checkbox that maps the value to `null`. |
| QA-UX02-008 | Payload compatibility | PASS | `toReportPayload()` preserves existing `POST /reports` field names and nullifies optional empty values, including `incident_time`. |
| QA-UX02-009 | Regression | PASS | TypeScript, build, and lint completed successfully. Build emitted only non-blocking dependency/chunk warnings; lint retained the existing 6 react-refresh warnings in shared shadcn UI files. |
| QA-UX02-010 | Accessibility | PASS with residual risk | Form controls use labels and `FormMessage`; DatePicker/TimePicker are keyboard-reachable. Manual screen-reader verification is still recommended because QA did not run browser assistive-tech testing. |

## Recommendations

1. Product Owner should execute the UX-02 smoke cases manually, especially server-error routing back to prior steps.
2. Keep an eye on the report wizard bundle size because adding calendar/date-fns increases the route chunk; current build warning is non-blocking and not isolated to UX-02.
3. During UX-04, confirm the shared `DatePicker` and `TimePicker` APIs still satisfy other form consumers before broad adoption.

## Verification

| Check | Command | Result |
|---|---|---|
| TypeScript | `npx.cmd tsc --noEmit` from `frontend/` | PASS |
| Build | `npm run build` from `frontend/` | PASS |
| Lint | `npm run lint` from `frontend/` | PASS with 0 errors and 6 existing react-refresh warnings |

# UX-02 Hotfix QA Recheck

## Summary

QA recheck focused only on the UX-02 hotfix areas referenced by `UX02-BUG-001`, `UX02-BUG-002`, and `UX02-BUG-003`.

Note: the current `docs/BUG_REPORT.md` did not previously contain detailed rows for these three UX-02 bug IDs; it only stated that no UX-02 bugs were found. This recheck records the three requested bug IDs in the living bug report and marks them according to the hotfix evidence found in the implementation.

Verified hotfix behavior:

- `UX02-BUG-001`: incident time for today's incident date is now rejected when the selected time is later than the current time.
- `UX02-BUG-002`: location type options now route through `formatLocationType()` and localized `portal:locationTypes.*` keys.
- `UX02-BUG-003`: native time input receives dark-mode styling for the field and WebKit picker indicator.

## Score

96

## PASS / FAIL

PASS

## Regression Findings

No regression found in the UX-02 hotfix scope.

Verification commands:

| Check | Command | Result |
|---|---|---|
| TypeScript | `npx.cmd tsc --noEmit` from `frontend/` | PASS |
| Build | `npm run build` from `frontend/` | PASS |
| Lint | `npm run lint` from `frontend/` | PASS with 0 errors and 6 existing react-refresh warnings |

Tooling notes:

- Build emitted non-blocking chunk-size/import warnings from the Vite/TanStack dependency output.
- Lint retained the existing 6 react-refresh warnings in shared shadcn UI files.

## Remaining Risks

- Manual browser verification is still required for the exact current-time boundary because the validation depends on the local clock at submit time.
- Dark-mode time picker appearance was verified by code review and build/lint, not visual browser inspection.
- Location type localization depends on backend/master-data codes matching the `portal:locationTypes.*` keys.

# UX-03

## Executive Summary

UX-03 was reviewed against `docs/REPORT_UX_AUDIT.md` and the UX-03 scope in `docs/UX_IMPROVEMENT_PLAN.md`, focused on validation experience, error surfaces, destructive-action confirmations, and removal of dead affordances.

The implementation is mostly aligned with the UX-03 plan. Registration correction now surfaces rejection reasons with a destructive alert and field-level Laravel errors, disabled workflow actions use an informational shadcn Alert pattern, the dashboard topbar search affordance has been removed, and the targeted destructive actions use AlertDialog confirmations. TypeScript, build, and lint completed without errors.

One UX-03 checklist item remains unresolved: the `/login` "Lupa kata sandi?" / "Forgot password?" affordance is still an active link pointing back to `/login`, despite the UX-03 manual checklist requiring it to be removed, disabled, or made non-active until a real flow exists. This was logged as `UX03-BUG-001`, so UX-03 does not fully pass QA.

## Implementation Score (0-100)

84

## PASS / FAIL

FAIL

## Findings

| ID | Area | Result | Evidence |
|---|---|---|---|
| QA-UX03-001 | Scope compliance | FAIL | Reviewed UX-03 surfaces from the plan: registration correction, dashboard layout topbar, workflow disabled action, user destructive actions, registration rejection, and university deactivation. The login forgot-password dead affordance remains unresolved from the UX-03 checklist. |
| QA-UX03-002 | Registration correction error surface | PASS | `/registration/correction` renders rejection reason inside a destructive `Alert` with icon/title/description and keeps Laravel 422 mapping through `applyLaravelErrors()`. |
| QA-UX03-003 | Dashboard topbar dead search | PASS | `dashboard-layout.tsx` no longer renders the nonfunctional topbar search input; the topbar keeps sidebar trigger, notification, language switcher, theme toggle, and user menu. |
| QA-UX03-004 | Disabled workflow action | PASS | `DisabledWorkflowAction` renders a shadcn `Alert` with an Info icon, `AlertTitle`, and `AlertDescription`, replacing the previous button-like dead affordance. |
| QA-UX03-005 | Destructive user actions | PASS | `/dashboard/users` wraps deactivate and reset-password actions in `AlertDialog`; reset password requires typing the exact reporter email before the confirm action is enabled. |
| QA-UX03-006 | Registration rejection confirmation | PASS | `/dashboard/registrations/$id` disables reject until the reason has at least 10 characters, then shows an `AlertDialog` containing the rejection reason before mutation. |
| QA-UX03-007 | University deactivation confirmation | PASS | `/dashboard/master-data/universities` wraps deactivate in `AlertDialog` and includes the institution name in the title/description. |
| QA-UX03-008 | Localization | PASS | New confirmation/rejection alert copy is present in both `id` and `en` locale files for auth/dashboard surfaces reviewed. |
| QA-UX03-009 | Accessibility | PASS with residual risk | AlertDialog primitives provide accessible dialog semantics; labels are present for reset-password email confirmation. Manual keyboard/screen-reader verification is still recommended. |
| QA-UX03-010 | Regression | PASS | `npx.cmd tsc --noEmit`, `npm.cmd run build`, and `npm.cmd run lint` passed. Lint retained 6 existing react-refresh warnings in shared UI files. |

## Recommendations

1. Fix `UX03-BUG-001` by removing the active forgot-password link or rendering it as a disabled/informational control until a real forgot-password flow is available.
2. Product Owner should execute the UX-03 smoke cases manually, especially destructive-dialog cancel/confirm behavior.
3. Keep destructive confirmations consistent in future milestones by using the shared `AlertDialog` pattern and explicit object names in titles/descriptions.

## Verification

| Check | Command | Result |
|---|---|---|
| TypeScript | `npx.cmd tsc --noEmit` from `frontend/` | PASS |
| Build | `npm.cmd run build` from `frontend/` | PASS with non-blocking Vite/TanStack chunk/import warnings |
| Lint | `npm.cmd run lint` from `frontend/` | PASS with 0 errors and 6 existing react-refresh warnings |

# UX-03 Hotfix QA Recheck

## Summary

QA recheck focused only on the UX-03 hotfix for `UX03-BUG-001`.

Verified hotfix behavior:

- The `/login` form no longer renders the active `Lupa kata sandi?` / `Forgot password?` link.
- No `Link to="/login"` forgot-password affordance remains in `frontend/src/routes/login.tsx`.
- Login page still keeps the expected active links to `/register` and `/track`.
- The hotfix aligns with `docs/REPORT_UX_AUDIT.md` F-11 and the UX-03 checklist item that forgot password must not exist as an active dead link.

## Score

98

## PASS / FAIL

PASS

## Regression Findings

No regression found in the UX-03 hotfix scope.

Verification commands:

| Check | Command | Result |
|---|---|---|
| TypeScript | `npx.cmd tsc --noEmit` from `frontend/` | PASS |
| Build | `npm.cmd run build` from `frontend/` | PASS with non-blocking Vite/TanStack chunk/import warnings |
| Lint | `npm.cmd run lint` from `frontend/` | PASS with 0 errors and 6 existing react-refresh warnings |

Tooling notes:

- Build retained the existing non-blocking Vite/TanStack chunk-size and unused-import warnings.
- Lint retained the existing 6 react-refresh warnings in shared shadcn UI files.

## Remaining Risks

- Manual browser verification is still recommended for `/login` in Bahasa Indonesia and English to confirm the removed affordance is absent visually.
- The actual forgot-password capability remains outside UX-03 scope and should only return as a real, implemented flow in a future milestone.

# UX-04

## Executive Summary

UX-04 was reviewed against `docs/REPORT_UX_AUDIT.md` findings F-05, F-06, and F-07, and against the UX-04 acceptance criteria in `docs/UX_IMPROVEMENT_PLAN.md`.

The implementation substantially improves consistency. Native `<select>` usage has been removed from `frontend/src`, legacy dropdowns now route through shared shadcn `Select` wrappers, DatePicker is centralized with locale-aware month names and `YYYY-MM-DD` storage, workflow action date fields now use the shared DatePicker pattern, and the reporter report wizard uses the shared TimePicker with quick picks and the "I don't remember" option.

One UX consistency issue remains: the TimePicker displays and placeholders time as `HH.mm` / `00.00`, while the UX plan specifies time display/storage as `HH:mm`. The internal value and report payload still use `HH:mm | null`, so this is not a payload regression, but it is a visible consistency mismatch and was logged as `UX04-BUG-001`.

## Implementation Score (0-100)

88

## PASS / FAIL

FAIL

## Findings

| ID | Area | Result | Evidence |
|---|---|---|---|
| QA-UX04-001 | Scope compliance | FAIL | UX-04 target files and related consumers were reviewed. Dropdown/date/time migration is broadly complete, but TimePicker display format does not match the plan's `HH:mm` display expectation. |
| QA-UX04-002 | Native select removal | PASS | `rg -n '<select' frontend/src` returned no matches; legacy public/admin dropdown consumers now use `SelectFormField` or `SelectInput`. |
| QA-UX04-003 | Native date input removal | PASS | `rg -n 'type="date"' frontend/src` returned no matches; date selection is centralized through `DatePicker`. |
| QA-UX04-004 | Native time input removal | PASS | `rg -n 'type="time"' frontend/src` returned no matches; incident time is handled by `TimePicker`. |
| QA-UX04-005 | DatePicker architecture | PASS | `components/ui/date-picker.tsx` uses shadcn `Popover` + `Calendar`, maps `i18n.language` to `date-fns` `id` / `enUS`, formats display as `d MMMM yyyy`, and emits `yyyy-MM-dd`. |
| QA-UX04-006 | TimePicker architecture | PASS with issue | `components/ui/time-picker.tsx` provides hour/minute selection, quick picks, disabled max-time options, and an unknown-time checkbox. It emits `HH:mm` or `null`, but displays selected values with dots. |
| QA-UX04-007 | Reporter report wizard payload | PASS | `portal.reports.new.tsx` passes TimePicker values through RHF state and `toReportPayload()` normalizes empty/unknown time to `incident_time: null`. |
| QA-UX04-008 | Public registration/correction dropdowns | PASS | `/register` and `/registration/correction` use `SelectFormField` for university, faculty, and study program fields, preserving dependent reset behavior. |
| QA-UX04-009 | Admin dropdowns | PASS | Registration filters, Create Reporter, and campus master-data university/faculty/study-program selects use shared shadcn select wrappers rather than native selects. |
| QA-UX04-010 | Regression | PASS | `npx.cmd tsc --noEmit`, `npm.cmd run build`, and `npm.cmd run lint` passed. Lint retained 6 existing react-refresh warnings in shared UI files. |

## Recommendations

1. Fix `UX04-BUG-001` by aligning TimePicker visible placeholder and selected display with `HH:mm`, or update the UX plan and smoke expectations if `HH.mm` is intentionally chosen for Indonesian display.
2. Product Owner should manually verify DatePicker month names in Bahasa Indonesia and English because QA verified locale wiring by static inspection, not browser interaction.
3. Keep `SelectInput` / `SelectFormField` as the single path for future dropdowns so native select usage does not re-enter the codebase.

## Verification

| Check | Command | Result |
|---|---|---|
| TypeScript | `npx.cmd tsc --noEmit` from `frontend/` | PASS |
| Build | `npm.cmd run build` from `frontend/` | PASS with non-blocking Vite/TanStack chunk/import warnings |
| Lint | `npm.cmd run lint` from `frontend/` | PASS with 0 errors and 6 existing react-refresh warnings |
| Native select search | `rg -n '<select' frontend/src` | PASS, no matches |
| Native date input search | `rg -n 'type="date"' frontend/src` | PASS, no matches |
| Native time input search | `rg -n 'type="time"' frontend/src` | PASS, no matches |

# UX-04 Hotfix QA Recheck

## Summary

QA recheck focused only on the UX-04 hotfix for `UX04-BUG-001`.

Verified hotfix behavior:

- `TimePicker` default placeholder now uses `Contoh : 00:00`.
- Selected TimePicker values render directly as `HH:mm` instead of replacing `:` with `.`.
- `/portal/reports/new` passes `placeholder="Contoh : 00:00"` to the shared TimePicker.
- Quick-pick values remain `HH:mm`, for example `08:00`, and the unknown-time path still supports `null`.

## Score

98

## PASS / FAIL

PASS

## Regression Findings

No regression found in the UX-04 hotfix scope.

Verification commands:

| Check | Command | Result |
|---|---|---|
| TypeScript | `npx.cmd tsc --noEmit` from `frontend/` | PASS |
| Build | `npm.cmd run build` from `frontend/` | PASS with non-blocking Vite/TanStack chunk/import warnings |
| Lint | `npm.cmd run lint` from `frontend/` | PASS with 0 errors and 6 existing react-refresh warnings |

Tooling notes:

- Build retained the existing non-blocking Vite/TanStack chunk-size and unused-import warnings.
- Lint retained the existing 6 react-refresh warnings in shared shadcn UI files.

## Remaining Risks

- Manual browser verification is still recommended to confirm the TimePicker display reads naturally in Bahasa Indonesia and English.
- The recheck was limited to the display-format hotfix and did not re-execute the full UX-04 smoke suite.

# Functional Hotfix QA Recheck - Case Status Transitions

## Summary

QA recheck focused on the frontend case status transition hotfix for assigned Satgas users after a report has been forwarded to a case.

Verified behavior by static implementation review:

- Assigned Satgas users can see `CaseStatusAction` on `/dashboard/cases/$id` when they are actively assigned to the case and the case is not closed.
- Non-assigned users, non-Satgas users, or closed cases see the disabled workflow explanation instead of the transition action.
- `CaseStatusAction` reads `case-statuses` master data and derives options from the current status `valid_transitions`.
- The frontend now matches transition targets against both status `code` and `name`, so backend seed transitions like `forwarded -> assessment` and `assessment -> investigation` resolve to selectable options even when the current case status is passed as a code.
- Backend still remains authoritative: `CasePolicy::updateStatus()` requires an active assigned Satgas user, and `CaseService::updateStatus()` rejects any target not present in the current status `valid_transitions` with `Invalid case status transition`.
- No backend implementation change was observed for this hotfix; reviewed backend files were read-only during QA.

## Score

96

## PASS / FAIL

PASS

## Regression Findings

No regression found in the functional hotfix scope.

Verification commands:

| Check | Command | Result |
|---|---|---|
| TypeScript | `npx.cmd tsc --noEmit` from `frontend/` | PASS |
| Build | `npm.cmd run build` from `frontend/` | PASS with non-blocking Vite/TanStack chunk/import warnings |
| Lint | `npm.cmd run lint` from `frontend/` | PASS with 0 errors and 6 existing react-refresh warnings |

Tooling notes:

- `git status --short` initially showed modified files only in docs and frontend paths; no `backend/api` path appeared in the modified-file list.
- A later path-limited Git status/diff attempt was blocked by Git dubious-ownership protection in the sandbox, so backend-change confirmation is based on the successful status output plus read-only backend inspection.

## Remaining Risks

- Browser/session verification with an actual assigned Satgas account is still recommended because QA did not execute an authenticated end-to-end UI flow.
- Backend invalid-transition behavior was verified by code review, not by sending a live invalid PATCH request.

# UX-05

## Executive Summary

QA reviewed UX-05 against `REPORT_UX_AUDIT.md` and `UX_IMPROVEMENT_PLAN.md`, focusing on Localization & Enum Consistency.

Implementation is partially complete. Portal status badges, report type labels, master-data pages, dashboard reports list, and case-detail date formatting are mostly aligned with the UX-05 target. TypeScript, build, and lint verification did not show blocking regressions.

However, UX-05 does not fully satisfy the localization acceptance criteria. The workflow analytics page still exposes backend/raw enum semantics through user-visible copy, and the dashboard analytics page remains largely hardcoded in English despite being part of the audited localization/date-format consistency surface.

## Implementation Score (0-100)

78

## PASS / FAIL

FAIL

## Findings

| ID | Area | Result | Evidence |
|---|---|---|---|
| QA-UX05-001 | Scope compliance | FAIL | UX-05 goal requires audited visible labels to route through i18n/format helpers. `dashboard.workflow.tsx` still renders `workflow.metric_semantics` directly, and `dashboard.analytics.tsx` still contains hardcoded English UI copy. |
| QA-UX05-002 | Portal status badge localization | PASS | `components/portal/portal-status-badge.tsx` uses `useTranslation(["portal"])` and renders `t(\`portal:${portalStatus}\`)` instead of hardcoded Indonesian status labels. |
| QA-UX05-003 | Report type localization | PASS | `portal.reports.new.tsx` maps `open`, `confidential`, and `anonymous` through `portal:reportTypes.*` keys; the dashboard reports list uses `formatReportType(t, ...)`. |
| QA-UX05-004 | Master-data localization | PASS | Master-data index, universities, faculties, and study-program pages use `dashboard:masterData.*` and enum format helpers for campus type and degree level labels. |
| QA-UX05-005 | Workflow localization | FAIL | Static labels are translated, but distribution rows use `formatGenericLabel()` and the metric description renders raw backend `metric_semantics`, so user-visible workflow strings are not fully localized. |
| QA-UX05-006 | Date formatting consistency | PASS with residual risk | `dashboard.reports.index.tsx` and `dashboard.cases.$id.tsx` use `formatDateTime(value, i18n.language)` for displayed dates. Remaining `new Date()` usage in case detail is internal sorting only, not display formatting. |
| QA-UX05-007 | Analytics localization | FAIL | `dashboard.analytics.tsx` still uses hardcoded English headings, descriptions, empty states, chart titles, and `labelFromKey()` returning English title-case labels. |
| QA-UX05-008 | Accessibility and design consistency | PASS | UX-05 changes reuse existing shadcn/Radix controls, badges, cards, and translation-driven labels without introducing obvious new keyboard or layout risks. |
| QA-UX05-009 | Regression | PASS | `npx.cmd tsc --noEmit`, `npm.cmd run build`, and `npm.cmd run lint` passed. Lint retained 6 existing react-refresh warnings in shared UI files. |

## Recommendations

1. Fix `UX05-BUG-001` by replacing user-visible workflow backend semantics and generic enum labels with locale-aware translation keys or typed enum formatters.
2. Fix `UX05-BUG-002` by migrating `dashboard.analytics.tsx` visible copy to `dashboard:analytics.*` locale keys and replacing `labelFromKey()` with `format-labels.ts` helpers where enum codes are known.
3. Keep backend-provided names that are true master-data names as data, but avoid rendering backend technical codes or semantics strings directly as explanatory UI copy.
4. Product Owner should manually execute UX-05 smoke tests in both Bahasa Indonesia and English after the hotfix.

## Verification

| Check | Command | Result |
|---|---|---|
| TypeScript | `npx.cmd tsc --noEmit` from `frontend/` | PASS |
| Build | `npm.cmd run build` from `frontend/` | PASS with non-blocking Vite/TanStack chunk-size and unused-import warnings |
| Lint | `npm.cmd run lint` from `frontend/` | PASS with 0 errors and 6 existing react-refresh warnings |
| Hardcoded portal status search | `rg -n 'Dikirim|Dalam Peninjauan|Sedang Diproses|Selesai' frontend/src/components` | PASS, no component matches |
| Date display search | `rg -n 'toLocaleString|toLocaleDateString|new Date\(' ...` on audited dashboard routes | PASS for display formatting; only internal sorting `new Date()` remains in case detail |

# UX-05 Hotfix QA Recheck

## Summary

QA recheck focused only on the UX-05 hotfix for the previously reported open bugs:

- `UX05-BUG-001` on `/dashboard/workflow`
- `UX05-BUG-002` on `/dashboard/analytics`

Both bugs are verified resolved. The workflow page no longer renders backend `metric_semantics` directly and now uses localized explanatory copy plus typed enum formatters. The analytics page has been migrated to `dashboard:analytics.*` locale keys and typed format helpers for known enum-like values.

`docs/SMOKE_TEST.md` was not changed because the manual UX-05 smoke scope remains the same.

## Score

96

## PASS / FAIL

PASS

## Regression Findings

No regression found in the UX-05 hotfix scope.

Verification evidence:

| Check | Command | Result |
|---|---|---|
| Workflow/analytics stale string search | `rg -n "metric_semantics\|labelFromKey\|Backend dashboard analytics across\|Total reports\|Cases by stage\|No case stage data\|descriptive_counts_only_not_kpi\|formatGenericLabel" frontend/src/routes/dashboard.workflow.tsx frontend/src/routes/dashboard.analytics.tsx` | PASS, no matches |
| Locale and formatter wiring search | `rg -n "dashboard:analytics\|metricSemantics\|reportCategory\|priorityLevel\|riskLevel\|recoveryType\|evidenceType" ...` | PASS, expected locale keys and formatter usage found |
| TypeScript | `npx.cmd tsc --noEmit` from `frontend/` | PASS |
| Build | `npm.cmd run build` from `frontend/` | PASS with non-blocking Vite/TanStack chunk-size and unused-import warnings |
| Lint | `npm.cmd run lint` from `frontend/` | PASS with 0 errors and 6 existing react-refresh warnings |

## Remaining Risks

- QA verified the hotfix by static inspection and tooling, not by an authenticated browser session toggling Bahasa Indonesia and English.
- Chart labels that come from backend data still depend on backend keys matching the locale dictionaries; unknown keys fall back to formatted raw labels by design.
- Existing build chunk-size warnings and existing shared UI `react-refresh/only-export-components` lint warnings remain non-blocking and unchanged.

# UX-05 Runtime Hotfix QA Recheck

## Summary

QA recheck focused on the runtime hotfix for `/dashboard/analytics` after the crash symptom `value.replace is not a function`.

Verified outcomes:

- `/dashboard/analytics` formatter path no longer assumes label values are strings.
- `format-labels.ts` helpers now accept `unknown` and normalize strings, numbers, booleans, bigint values, objects, arrays, null, undefined, and empty strings before fallback formatting.
- Analytics aggregation labels now pass through `analyticsLabelKey()` before calling `formatCaseStatus`, `formatReportCategory`, and `formatEvidenceClassification`.
- Workflow and analytics localization fixes from the previous UX-05 hotfix remain intact.
- TypeScript, build, and lint did not introduce a blocking regression.

## Score

97

## PASS / FAIL

PASS

## Regression Findings

No regression found in the UX-05 runtime hotfix scope.

Verification evidence:

| Check | Command | Result |
|---|---|---|
| Formatter runtime non-string check | `npx.cmd tsx -e "...formatCaseStatus/formatReportCategory/formatGenericLabel..."` from `frontend/` | PASS; string, number, boolean, object, array, null, undefined, and empty string inputs completed without `value.replace is not a function` |
| Replace usage inspection | `rg -n "\.replace\(|formatCaseStatus\(|formatReportCategory\(|formatEvidenceClassification\(|analyticsLabelKey\(" frontend/src/lib/format-labels.ts frontend/src/routes/dashboard.analytics.tsx frontend/src/routes/dashboard.workflow.tsx` | PASS; `.replace()` remains only after `fallbackSource()` normalization in `format-labels.ts`; analytics uses `analyticsLabelKey()` before enum formatters |
| TypeScript | `npx.cmd tsc --noEmit` from `frontend/` | PASS |
| Build | `npm.cmd run build` from `frontend/` | PASS with existing non-blocking Vite/TanStack chunk-size and unused-import warnings |
| Lint | `npm.cmd run lint` from `frontend/` | PASS with 0 errors and 6 existing react-refresh warnings |

## Bug Status Updates

| Bug ID | Status | QA Result |
|---|---|---|
| UX05-BUG-001 | Verified | Workflow localization remained intact. |
| UX05-BUG-002 | Verified | Analytics localization remained intact. |
| UX05-BUG-003 | Verified | Runtime formatter crash is fixed. |

## Remaining Risks

- QA did not execute an authenticated browser session against live dashboard data; `/dashboard/analytics` runtime behavior was verified through static inspection, production build, and targeted formatter runtime checks.
- Unknown backend aggregation shapes still fall back to serialized/formatted labels, so Product Owner should include real analytics data in manual smoke verification.
- The first targeted formatter runtime command attempted to fetch `tsx` and was blocked by sandbox network restrictions; the re-run was approved and completed successfully.

# UX-06

## Executive Summary

QA reviewed UX-06 against `REPORT_UX_AUDIT.md` and `UX_IMPROVEMENT_PLAN.md`, focusing on Responsive & Mobile-first Improvements for F-13, F-14, F-19, and F-22.

Implementation is aligned with the milestone scope. Admin table surfaces now use horizontal overflow containment, reports/cases lists include mobile card layouts below `md`, portal navigation labels remain visible at small breakpoints with nav button labels, toaster placement is `top-center`, and the required detail pages now include breadcrumbs with localized parent labels.

No new UX-06 bugs were found during static QA and tooling verification.

## Implementation Score (0-100)

94

## PASS / FAIL

PASS

## Findings

| ID | Area | Result | Evidence |
|---|---|---|---|
| QA-UX06-001 | Scope compliance | PASS | UX-06 target files were reviewed: admin list pages, portal layout, root toaster, and four detail pages. Changes map to F-13, F-14, F-19, and F-22. |
| QA-UX06-002 | Admin table overflow containment | PASS | `dashboard.registrations.tsx`, `dashboard.users.tsx`, `dashboard.reports.index.tsx`, `dashboard.cases.index.tsx`, and `dashboard.master-data.universities.tsx` contain `overflow-x-auto` wrappers around table surfaces. |
| QA-UX06-003 | Reports mobile card layout | PASS | `/dashboard/reports` renders a `grid gap-3 md:hidden` card list and a `hidden ... md:block` table wrapper for desktop/tablet. |
| QA-UX06-004 | Cases mobile card layout | PASS | `/dashboard/cases` renders a `grid gap-3 md:hidden` card list and a `hidden ... md:block` table wrapper for desktop/tablet. |
| QA-UX06-005 | Portal nav mobile labels | PASS | `portal-layout.tsx` removed the `hidden sm:inline` label pattern, uses `text-xs sm:text-sm`, keeps nav labels visible, and adds `aria-label={t(item.titleKey)}` to each nav button. |
| QA-UX06-006 | Toast position | PASS | `routes/__root.tsx` renders `<Toaster richColors position="top-center" />`. |
| QA-UX06-007 | Detail page breadcrumbs | PASS | Breadcrumbs are present in `/dashboard/cases/$id`, `/dashboard/registrations/$id`, `/dashboard/reports/$id`, and `/portal/reports/$registrationNumber` with localized parent labels. |
| QA-UX06-008 | Regression | PASS | `npx.cmd tsc --noEmit`, `npm.cmd run build`, and `npm.cmd run lint` passed. Lint retained 6 existing react-refresh warnings in shared UI files. |

## Recommendations

1. Product Owner should manually execute the UX-06 smoke suite on a real 360x740 or browser-emulated viewport to confirm there is no page-level horizontal overflow.
2. Include at least one long registration number, long reporter name, and long university/program name in manual testing to verify truncation/wrapping remains readable on mobile cards and scrolled tables.
3. Keep UX-07 separate for icon-only accessibility and status contrast work; UX-06 did not need to complete those future-scope items.

## Verification

| Check | Command | Result |
|---|---|---|
| Responsive pattern grep | `rg -n "overflow-x-auto|md:hidden|hidden md:table|hidden md:block|hidden sm:inline|aria-label|position=\"top-center\"|Breadcrumb" ...` | PASS; expected wrappers, mobile card layouts, nav labels, toaster position, and breadcrumbs found |
| Table/breadcrumb/toaster grep | `rg -n "<table|<Breadcrumb|position=|hidden sm:inline|aria-label=\\{t\\(item.titleKey\\)\\}" ...` | PASS; target tables and breadcrumbs present; no active `hidden sm:inline` portal nav label pattern found |
| TypeScript | `npx.cmd tsc --noEmit` from `frontend/` | PASS |
| Build | `npm.cmd run build` from `frontend/` | PASS with existing non-blocking Vite/TanStack chunk-size and unused-import warnings |
| Lint | `npm.cmd run lint` from `frontend/` | PASS with 0 errors and 6 existing react-refresh warnings |

## Remaining Risks

- QA did not run an authenticated browser viewport test at 360x740; responsive behavior was verified by static inspection and build/lint/TypeScript checks.
- Breadcrumbs on some admin detail pages are rendered after successful data load; manual QA should also observe loading/error states to confirm the experience remains understandable.
- The portal nav is horizontally scrollable by design on narrow screens; manual QA should confirm this feels usable with all labels visible.

# UX-07

## Executive Summary

QA reviewed UX-07 against `REPORT_UX_AUDIT.md` and `UX_IMPROVEMENT_PLAN.md`, focusing on Accessibility & Status Multi-channel for F-20 and F-21.

Implementation is aligned with the milestone scope. Case and portal status badges now provide multi-channel status communication through text, color, and leading icons. Dashboard and portal avatar dropdown triggers now have localized accessible names. Light-mode `success` and `info` tokens were darkened and independently checked against white backgrounds, passing WCAG AA contrast for normal text.

No new UX-07 bugs were found during static QA, contrast calculation, and tooling verification.

## Implementation Score (0-100)

96

## PASS / FAIL

PASS

## Findings

| ID | Area | Result | Evidence |
|---|---|---|---|
| QA-UX07-001 | Scope compliance | PASS | UX-07 target files were reviewed: `status-badge.tsx`, `portal-status-badge.tsx`, `dashboard-layout.tsx`, `portal-layout.tsx`, `styles.css`, and common locale files. Changes map to F-20 and F-21 only. |
| QA-UX07-002 | Case status multi-channel badges | PASS | `StatusBadge` maps each `CaseStatus` to a distinct Lucide icon and renders icon + localized label + tonal color. Icons are `aria-hidden="true"` so the text label remains the accessible status. |
| QA-UX07-003 | Portal status multi-channel badges | PASS | `PortalStatusBadge` maps Submitted, Under Review, In Process, Completed, and unknown fallback labels to leading icons and keeps localized label rendering via `t("portal:${portalStatus}")`. |
| QA-UX07-004 | Accessible avatar menu trigger | PASS | Dashboard and portal topbar avatar dropdown triggers include `aria-label={t("common:userMenu")}` with matching `id/common.json` and `en/common.json` keys. |
| QA-UX07-005 | Icon-only control audit | PASS | Grep found named/sr-only coverage for known icon-only controls: dashboard theme toggle, portal nav/theme buttons, language switcher, breadcrumb overflow, carousel controls, dialog/sheet close buttons, password toggle, pagination, and sidebar trigger. `dashboard.users.tsx` row actions are text buttons, not icon-only buttons. |
| QA-UX07-006 | Contrast | PASS | Light-mode `--success` is `oklch(0.45 0.15 155)` and `--info` is `oklch(0.45 0.13 230)`. Independent OKLCH-to-sRGB WCAG calculation returned approximately 6.65:1 for success and 6.89:1 for info against white. |
| QA-UX07-007 | Localization | PASS | New `common:userMenu` key exists in Bahasa Indonesia and English. Portal status badge still resolves labels from locale files instead of hardcoded display text. |
| QA-UX07-008 | Regression | PASS | `npx.cmd tsc --noEmit`, `npm.cmd run build`, and `npm.cmd run lint` passed. Lint retained 6 existing react-refresh warnings in shared UI files. |

## Recommendations

1. Product Owner should manually verify status badges in both light and dark themes using browser accessibility inspection, especially warning, success, and info states.
2. Include color-blindness simulation or grayscale review during manual UX sign-off to confirm the new icons make status distinguishable without relying on color.
3. Keep future status additions guarded by the same pattern: explicit icon mapping, localized text label, and contrast-checked tone.

## Verification

| Check | Command | Result |
|---|---|---|
| UX-07 scope grep | `rg -n "UX-07|F-20|F-21|status|contrast|aria|accessib" docs/REPORT_UX_AUDIT.md docs/UX_IMPROVEMENT_PLAN.md` | PASS; milestone scope confirmed as Accessibility & Status Multi-channel |
| Implementation inspection | Direct read/grep of UX-07 target files | PASS; status icons, avatar `aria-label`, locale keys, and token updates present |
| Icon-only accessibility grep | `rg -n 'size="icon"|aria-label|sr-only' frontend/src/components frontend/src/layouts frontend/src/routes` | PASS; known icon-only controls have accessible names or sr-only text |
| Status hardcode grep | `rg -n 'Dikirim|Dalam Peninjauan|Sedang Diproses|Selesai|Submitted|Under Review|In Process|Completed' frontend/src/components frontend/src/routes frontend/src/locales` | PASS; user-facing status text remains in locale files or comments, not hardcoded badge rendering |
| Contrast calculation | Node OKLCH-to-sRGB WCAG calculation for `--success` and `--info` against white | PASS; success approximately 6.65:1, info approximately 6.89:1 |
| TypeScript | `npx.cmd tsc --noEmit` from `frontend/` | PASS |
| Build | `npm.cmd run build` from `frontend/` | PASS with existing non-blocking Vite/TanStack chunk-size and unused-import warnings |
| Lint | `npm.cmd run lint` from `frontend/` | PASS with 0 errors and 6 existing react-refresh warnings |

## Remaining Risks

- QA did not run an authenticated browser session with a screen reader; accessible names and badge semantics were verified by static inspection and grep.
- Contrast calculation was performed for success/info against white; Product Owner should still inspect badge combinations in real pages, including tinted backgrounds and dark mode.
- Global success/info token darkening can subtly affect other light-mode UI surfaces using `text-success`, `text-info`, `bg-success/15`, or `bg-info/15`; no blocking regression was found during static review/build.

# UX-08

## Executive Summary

QA reviewed UX-08 against `REPORT_UX_AUDIT.md` and `UX_IMPROVEMENT_PLAN.md`, focusing on Workflow & Detail Polish for F-17, F-18, F-23, and the UI-only portion of F-27.

Most UX-08 implementation is aligned with scope. A shared `EmptyState` component was introduced and adopted across the targeted admin list pages, workflow and registration-detail loading states now use skeleton placeholders, case detail workflow sections are grouped into tabs without route changes, portal notifications display the advisory copy, and localization keys exist in Bahasa Indonesia and English.

QA found one acceptance gap: the case detail workflow tabs always default to `investigation`, while the UX-08 plan requires the default tab to be determined by the current case status. Because this is an explicit acceptance criterion, UX-08 is marked FAIL until fixed.

## Implementation Score (0-100)

88

## PASS / FAIL

FAIL

## Findings

| ID | Area | Result | Evidence |
|---|---|---|---|
| QA-UX08-001 | Scope compliance | PASS | UX-08 target files were reviewed: `components/empty-state.tsx`, targeted admin lists, `dashboard.workflow.tsx`, `dashboard.registrations.$id.tsx`, `dashboard.cases.$id.tsx`, `portal.notifications.tsx`, and locale files. |
| QA-UX08-002 | Shared EmptyState | PASS | `components/empty-state.tsx` exposes icon, title, description, and optional action props, and renders a consistent dashed-border empty state. |
| QA-UX08-003 | Admin list empty states | PASS | Registrations, users, reports, cases, and master-data universities render `EmptyState` for both filtered-empty and truly-empty conditions. |
| QA-UX08-004 | Skeleton loaders | PASS | `dashboard.workflow.tsx`, `dashboard.registrations.$id.tsx`, `dashboard.master-data.universities.tsx`, and `portal.notifications.tsx` render shadcn `Skeleton` placeholders during loading states. |
| QA-UX08-005 | Case workflow tabs | FAIL | `dashboard.cases.$id.tsx` wraps Investigation, Recommendation, Decision, Recovery, and Evidence in shadcn `Tabs`, but uses `defaultValue="investigation"` statically instead of deriving the default tab from current case status. |
| QA-UX08-006 | Routing and navigation | PASS | Static inspection found workflow tabs are local UI state and do not introduce route params; existing links to case, report, and registration detail pages remain present. |
| QA-UX08-007 | Portal notification advisory | PASS | `portal.notifications.tsx` renders `t("notificationsAdvisory")`, and the advisory key exists in `id/portal.json` and `en/portal.json`. |
| QA-UX08-008 | Localization consistency | PASS | Empty-state, tab, and notification advisory keys exist in both `id` and `en` locale files. |
| QA-UX08-009 | Responsive behavior | PASS | Reports and cases lists retain UX-06 mobile card layouts (`md:hidden`) and desktop table wrappers (`hidden ... md:block`) while adding EmptyState coverage. |
| QA-UX08-010 | Accessibility preservation | PASS | Existing UX-07 topbar `aria-label` coverage, breadcrumb semantics, and status badge components remain present in static inspection. |
| QA-UX08-011 | Regression UX-01 through UX-07 | PASS with risk | No static regression was found in validation/localization/responsive/accessibility surfaces touched by UX-08. The new tabs acceptance issue is scoped to UX-08 and logged as `UX08-BUG-001`. |
| QA-UX08-012 | Runtime/tooling | PASS | `npx.cmd tsc --noEmit`, `npm.cmd run build`, and `npm.cmd run lint` all passed. Lint retained 6 existing react-refresh warnings in shared UI files. |

## Recommendations

1. Fix `UX08-BUG-001` by deriving the case detail tab default from `c.status` or `c.current_stage`, mapping workflow statuses to `investigation`, `recommendation`, `decision`, `recovery`, or `evidence`.
2. Product Owner should manually verify each workflow status opens the most relevant tab by default and that switching tabs does not change the URL.
3. Include mobile viewport checks for EmptyState and Tabs because wide tab labels can wrap on small screens.

## Verification

| Check | Command | Result |
|---|---|---|
| UX-08 scope grep | `rg -n "UX-08|F-17|F-18|F-23|F-27|empty|skeleton|Tabs|notifications" docs/REPORT_UX_AUDIT.md docs/UX_IMPROVEMENT_PLAN.md` | PASS; milestone scope and acceptance criteria confirmed |
| Implementation inspection | Direct read/grep of UX-08 target files | FAIL; `Tabs defaultValue="investigation"` is static and does not follow current case status |
| EmptyState and skeleton grep | `rg -n "EmptyState|Skeleton|Loading...|Tabs|notificationsAdvisory" ...` | PASS with one risk; EmptyState/advisory/skeletons found in target files, but case detail initial loading still uses text outside the explicit skeleton replacement file list |
| Regression grep | `rg -n 'md:hidden|hidden overflow-x-auto|Breadcrumb|aria-label|StatusBadge|PortalStatusBadge|EmptyState|Tabs defaultValue' ...` | PASS with bug noted; UX-06/UX-07 patterns remain present and the static tab default was confirmed |
| Localization grep | `rg -n 'filteredEmptyTitle|filteredEmptyDesc|emptyTitle|emptyDesc|notificationsAdvisory|tabInvestigation|tabRecommendation|tabDecision|tabRecovery|tabEvidence' ...` | PASS; keys exist in Bahasa Indonesia and English |
| TypeScript | `npx.cmd tsc --noEmit` from `frontend/` | PASS |
| Build | `npm.cmd run build` from `frontend/` | PASS with existing non-blocking Vite/TanStack chunk-size and unused-import warnings |
| Lint | `npm.cmd run lint` from `frontend/` | PASS with 0 errors and 6 existing react-refresh warnings |

## Bugs Found

| Bug ID | Severity | Status | Summary |
|---|---|---|---|
| UX08-BUG-001 | Medium | Open | Case detail workflow tabs always default to Investigation instead of defaulting based on current case status. |

## Remaining Risks

- QA did not run authenticated browser walkthroughs or visual snapshots; responsive behavior, navigation, and runtime safety were verified by static inspection plus TypeScript/build/lint.
- `dashboard.cases.$id.tsx` still uses a text loading state for the initial case query; UX-08 approach only explicitly targeted skeleton replacement in `dashboard.workflow.tsx` and `dashboard.registrations.$id.tsx`, so this is tracked as residual polish risk rather than a blocking UX-08 bug.
- The portal notifications advisory says notifications are marked automatically when opening related reports; QA did not verify backend read-state behavior and treats this as a product-truth item for Product Owner confirmation.

# UX-08 Hotfix QA Recheck

## Summary

QA rechecked the UX-08 hotfix for `UX08-BUG-001`, focusing only on case-detail workflow tab default behavior, manual tab switching, and regression risk.

The hotfix resolves the reported issue. `dashboard.cases.$id.tsx` now computes `defaultWorkflowTab` through `defaultWorkflowTabForCase(c)`, prioritizing `current_stage`, `current_stage_label`, `status`, `status_label`, and `status_code`. The mapping covers workflow stage numbers and known case status tokens for investigation, recommendation, decision, and recovery. The tab component remains local UI state via `defaultValue={defaultWorkflowTab}`, so manual tab switching remains available and does not introduce URL parameters.

## Score

97

## PASS / FAIL

PASS

## Regression Findings

No regression found in the UX-08 hotfix scope.

Verification evidence:

| Check | Command | Result |
|---|---|---|
| Bug status inspection | `rg -n "UX08-BUG-001|defaultWorkflowTab|Tabs defaultValue" docs/BUG_REPORT.md docs/QA_REPORT.md frontend/src/routes/dashboard.cases.$id.tsx` | PASS; bug still tracked in docs and implementation now uses `defaultWorkflowTab` instead of a static `investigation` default |
| Implementation inspection | Direct read of `frontend/src/routes/dashboard.cases.$id.tsx` | PASS; `WORKFLOW_TAB_BY_TOKEN` maps stage/status tokens and `defaultWorkflowTabForCase(c)` derives the default tab from case record fields |
| Backend/resource shape check | `rg -n "CaseRecord|current_stage|current_stage_label|status_code" frontend/src/lib/operations-types.ts backend/api/app` | PASS; `CaseRecord` includes the fields used by the hotfix and backend resource exposes `current_stage`, `current_stage_label`, and `status_code` |
| Manual tab switching review | Static inspection of `<Tabs defaultValue={defaultWorkflowTab}>` and tab triggers | PASS; tabs remain uncontrolled/local and no route param or URL state was introduced |
| TypeScript | `npx.cmd tsc --noEmit` from `frontend/` | PASS |
| Build | `npm.cmd run build` from `frontend/` | PASS with existing non-blocking Vite/TanStack chunk-size and unused-import warnings |
| Lint | `npm.cmd run lint` from `frontend/` | PASS with 0 errors and 6 existing react-refresh warnings |

## Bug Status Updates

| Bug ID | Status | QA Result |
|---|---|---|
| UX08-BUG-001 | Verified | Workflow tabs now default according to available case status/stage fields, and manual tab switching remains local to the component. |

## Remaining Risks

- QA did not execute an authenticated browser session with real case records for every workflow status; verification was based on static inspection, backend/resource shape checks, and build tooling.
- The Evidence tab is not selected by default from case status because evidence is a supporting workflow section rather than a case status/stage token in the current backend model.
- Unknown future status codes still fall back to Investigation by design; future backend statuses should be added to `WORKFLOW_TAB_BY_TOKEN` when introduced.

# UX-09A

## Executive Summary

QA reviewed UX-09A against the Critical and High findings in `docs/REPORT_UX_AUDIT.md`, the milestone grouping in `docs/UX_IMPROVEMENT_PLAN.md`, and regression expectations from UX-01 through UX-08.

Static inspection confirms that the Critical wizard issue and the High UX findings are addressed in the inspected implementation surfaces: per-step wizard validation, progress indicator, localized report type/status labels, shadcn Select migration, DatePicker/TimePicker adoption, RHF + zod form handling, removal of the dead forgot-password affordance, responsive admin lists, localized Master Data/Workflow pages, and AlertDialog confirmation for destructive admin actions.

No new UX-09A bugs were found. TypeScript, production build, and lint all passed.

## Implementation Score

96

## PASS / FAIL

PASS

## Findings

| Area | Result | Evidence |
|---|---|---|
| Critical F-01 wizard step validation | PASS | `/portal/reports/new` uses RHF + zod and `form.trigger(stepFields[step], { shouldFocus: true })` before advancing wizard steps. |
| High F-02 wizard progress | PASS | `WizardProgress` is rendered above the report wizard and labels the current step. |
| High F-03 report type localization | PASS | Report type options are gated by `reportTypesQuery.isSuccess` and mapped from backend/master-data labels instead of hardcoded English fallbacks. |
| High F-04 portal status badge localization | PASS | `PortalStatusBadge` resolves labels through `t(\`portal:${portalStatus}\`, { defaultValue: portalStatus })` and keeps status icons from UX-07. |
| High F-05 native select migration | PASS | `rg` found no native `<select>` in `frontend/src/routes` or `frontend/src/components`; affected forms use shadcn Select wrappers. |
| High F-06 date picker constraints | PASS | Native `type="date"` is no longer found in searched routes/components; portal and workflow date fields use shared `DatePicker` with future-date disabling where applicable. |
| High F-08 RHF + zod migration | PASS | Portal wizard, registration, correction, and Create Reporter use `useForm`, `zodResolver`, field components, and `applyLaravelErrors`. |
| High F-11 dead forgot-password link | PASS | Login route no longer renders a forgot-password link or self-link to `/login`. |
| High F-13 responsive admin tables | PASS | Registrations/users/universities use local horizontal wrappers, while reports/cases keep mobile card layouts from UX-06. |
| High F-15 Master Data localization | PASS | Master Data route uses `dashboard:masterData.*` keys and locale files contain corresponding `id` and `en` entries. |
| High F-16 Workflow localization | PASS | Workflow page renders translated pipeline labels, descriptions, empty states, and locale-aware date formatting. |
| High F-25 destructive confirmations | PASS | Registration rejection, user deactivation/reset password, and university deactivation use AlertDialog confirmations. |
| Regression UX-01 through UX-08 | PASS | Validation, localization, responsive layouts, breadcrumbs, status badges, accessibility labels, empty states, and UX-08 workflow tab default remain present in static inspection. |
| Settings / branding / break-glass / portal | PASS | Settings uses `silappkasal.settings.v1` branding namespace and translated settings copy; Break Glass and Portal surfaces continue using i18n-backed labels and existing routes. |

## Recommendations

1. Product Owner should execute the UX-09A smoke tests on real seeded data, especially wizard validation, destructive confirmations, and responsive admin lists.
2. Keep the existing lint Fast Refresh warnings tracked as technical debt; they are non-blocking but still appear on every lint run.
3. Add manual browser checks for Bahasa Indonesia and English on portal, workflow, settings, and break-glass pages because this QA pass used static inspection plus build tooling, not an authenticated visual walkthrough.

## Verification Results

| Check | Command | Result |
|---|---|---|
| Critical/High scope confirmation | `rg -n "F-0[1-9]|F-1[0-9]|F-2[0-9]|Critical|High" docs/REPORT_UX_AUDIT.md docs/UX_IMPROVEMENT_PLAN.md` | PASS; Critical and High findings confirmed for review scope |
| Select/date migration search | `rg -n '<select|type="date"|input type="date"|DatePicker|TimePicker|Calendar' frontend/src/routes frontend/src/components` | PASS; no native select/date inputs found, shared DatePicker/TimePicker usage confirmed |
| Create Reporter form review | `rg -n "useForm|zodResolver|CreateReporterCard|applyLaravelErrors|SelectFormField|TextInputField|PasswordField|form\\.handleSubmit" frontend/src/routes/dashboard.users.tsx` | PASS; Create Reporter uses RHF, zod resolver, field components, and Laravel error mapping |
| Forgot-password review | `rg -n "Forgot|forgot|Lupa|password|reset" frontend/src/routes/login.tsx` | PASS; no dead forgot-password affordance found |
| Destructive action review | `rg -n "AlertDialog|reject|reason|confirmation|Reject" ...` | PASS; destructive admin actions use AlertDialog confirmation patterns |
| Localization/settings/break-glass review | Targeted grep/read of dashboard locale-backed routes and locale files | PASS; inspected surfaces use i18n keys and product branding namespace |
| TypeScript | `npx.cmd tsc --noEmit` from `frontend/` | PASS |
| Build | `npm.cmd run build` from `frontend/` | PASS with non-blocking Vite/TanStack chunk-size and unused-import warnings |
| Lint | `npm.cmd run lint` from `frontend/` | PASS with 0 errors and 6 existing Fast Refresh warnings in shared UI files |

## Bugs Found

No new UX-09A bugs were found during QA.

## Remaining Risks

- QA did not execute an authenticated browser walkthrough or screenshot comparison; responsive behavior, runtime safety, and visual consistency were verified through static inspection and build tooling.
- Backend invalid-transition and data-contract behavior were not revalidated in this UX-09A pass because UX-09A scope is UX remediation/regression review and no backend changes were expected.
- Unknown future backend enum/status values may still fall back to raw labels where no locale key exists; current reviewed UX-09A labels are covered.
