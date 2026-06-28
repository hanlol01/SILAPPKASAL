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
