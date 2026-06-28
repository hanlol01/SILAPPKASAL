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

# UX-04

## Bugs Found During QA

| Bug ID | Severity | Status | Affected Page | Expected Behavior | Actual Behavior | Possible Root Cause | Recommended Fix |
|---|---|---|---|---|---|---|---|
| UX04-BUG-001 | Medium | Verified | `/portal/reports/new`, shared `TimePicker` | Time display should be consistent with the UX plan's `HH:mm` format while payload remains `HH:mm \| null`. | Verified fixed in hotfix QA recheck: `TimePicker` now defaults to `Contoh : 00:00`, renders selected values directly as `HH:mm`, and `/portal/reports/new` passes `placeholder="Contoh : 00:00"`. | Previous root cause was localized dot-separated presentation that diverged from the UX plan and smoke expectation. | No further fix required. Keep UX-04 smoke coverage for TimePicker display, quick picks, and unknown-time payload behavior. |

# UX-05

## Bugs Found During QA

| Bug ID | Severity | Status | Affected Page | Expected Behavior | Actual Behavior | Possible Root Cause | Recommended Fix |
|---|---|---|---|---|---|---|---|
| UX05-BUG-001 | Medium | Verified | `/dashboard/workflow` | All user-visible workflow copy and enum/status labels should be localized through i18n or locale-aware format helpers. Technical backend semantics should not be shown directly to users. | Verified fixed in hotfix QA recheck: workflow now renders localized `dashboard:workflow.pipeline.metricSemantics`, uses typed enum formatters for status distributions, decision outcomes, and recovery types, and no longer renders `workflow.metric_semantics` directly. | Previous root cause was that UX-05 migrated the page structure to i18n but left some backend-derived explanatory text and generic enum labels as direct display values. | No further fix required. Keep UX-05 smoke coverage for workflow localization in Bahasa Indonesia and English. |
| UX05-BUG-002 | Medium | Verified | `/dashboard/analytics` | The audited dashboard analytics surface should support Bahasa Indonesia and English consistently, and enum/date labels should route through i18n or `format-labels.ts`. | Verified fixed in hotfix QA recheck: analytics page now uses `useTranslation(["dashboard"])`, `dashboard:analytics.*` locale keys, localized chart/card/empty-state copy, and enum formatters for case status, report category, and evidence classification. | Previous root cause was that UX-05 localized workflow/master-data surfaces but did not migrate the analytics page even though `REPORT_UX_AUDIT.md` flagged it under localization/date consistency. | No further fix required. Keep UX-05 smoke coverage for analytics localization and chart labels. |
| UX05-BUG-003 | High | Verified | `/dashboard/analytics`, shared `format-labels.ts` helpers | Analytics should not crash when backend aggregation keys are non-string values; label helpers should safely handle strings, numbers, booleans, objects, arrays, null, and undefined. | Verified fixed in runtime hotfix QA recheck: `format-labels.ts` now accepts `unknown`, normalizes non-string values through `readableValue()`, and the targeted formatter runtime check no longer throws `value.replace is not a function`. | Previous root cause was label formatting assuming the fallback source was always a string before calling `.replace()`, while analytics aggregation keys can be object-shaped or otherwise non-string. | No further fix required. Keep analytics smoke coverage for backend aggregation data with object-shaped category/stage/classification keys. |

# UX-06

## Bugs Found During QA

No new UX-06 bugs were found during QA.
