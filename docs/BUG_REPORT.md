# UX-01

## Bugs Found During QA

| Bug ID | Severity | Status | Affected Page | Expected Behavior | Actual Behavior | Possible Root Cause | Recommended Fix |
|---|---|---|---|---|---|---|---|
| UX01-BUG-001 | Medium | Verified | `/register`, `/registration/correction`, `/dashboard/users` Create Reporter | Client-side validation messages should be localized and consistent with the active language, especially because Bahasa Indonesia is the default experience. | Verified fixed in hotfix QA recheck: zod schemas now use `common:validation.required` and `common:validation.email`, with matching keys in `id/common.json` and `en/common.json`. | Previous root cause was untranslated zod schema messages. Hotfix added localized validation messages. | No further fix required. Keep UX-01 smoke localization case for manual Product Owner verification. |
| UX01-BUG-002 | Low | Verified | `/register`, `/registration/correction` | Password confirmation fields should fail client-side when confirmation does not match the password. | Verified fixed in hotfix QA recheck: registration validates `password_confirmation` with `.refine()`, and registration correction validates `new_password_confirmation` with `.superRefine()`. | Previous root cause was missing client-side cross-field validation. Hotfix added confirmation mismatch validation mapped to the confirmation field. | No further fix required. Keep password confirmation mismatch in manual regression smoke coverage. |

# UX-02

## Bugs Found During QA

| Bug ID | Severity | Status | Affected Page | Expected Behavior | Actual Behavior | Possible Root Cause | Recommended Fix |
|---|---|---|---|---|---|---|---|
| UX02-BUG-001 | Medium | Verified | `/portal/reports/new` | If the incident date is today, incident time should not be later than the current local time. | Verified fixed in hotfix QA recheck: wizard schema now applies `incidentTimeFuture` validation via `superRefine()` when `incident_date` equals today. | Previous behavior allowed a future time on today's incident date because UX-02 only blocked future dates. | No further fix required. Keep a manual smoke case for today's future-time boundary. |
| UX02-BUG-002 | Medium | Verified | `/portal/reports/new` | Location type options should display localized labels in Bahasa Indonesia and English. | Verified fixed in hotfix QA recheck: location type options now use `formatLocationType(t, item.name)` with matching `portal:locationTypes.*` keys in `id` and `en`. | Previous behavior rendered raw master-data names for location type options. | No further fix required. Ensure master-data codes/names continue to match locale keys. |
| UX02-BUG-003 | Low | Verified | `/portal/reports/new` | Time input should remain visually usable in dark mode, including the native picker indicator. | Verified fixed in hotfix QA recheck: `TimePicker` input now includes dark color-scheme and WebKit calendar-picker-indicator invert styling. | Native browser time input indicator did not inherit app dark-mode styling consistently. | No further fix required. Keep visual dark-mode browser check in manual smoke coverage. |

# UX-03

## Bugs Found During QA

| Bug ID | Severity | Status | Affected Page | Expected Behavior | Actual Behavior | Possible Root Cause | Recommended Fix |
|---|---|---|---|---|---|---|---|
| UX03-BUG-001 | High | Verified | `/login` | The forgot-password affordance should not be an active dead link. Per UX-03 checklist, it should either be removed or disabled with clear explanatory copy until a real forgot-password flow exists. | Verified fixed in hotfix QA recheck: the `Lupa kata sandi?` / `Forgot password?` link is no longer rendered in the login form, and no `Link to="/login"` forgot-password affordance remains in `frontend/src/routes/login.tsx`. | Previous root cause was that UX-03 removed the dashboard topbar dead search but missed the login forgot-password dead affordance from `REPORT_UX_AUDIT.md` F-11 and the UX-03 manual checklist. | No further fix required for UX-03. Keep the forgot-password capability out of the UI until a real flow is implemented in a future milestone. |
