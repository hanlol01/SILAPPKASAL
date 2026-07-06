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

# UX-07

## Bugs Found During QA

No new UX-07 bugs were found during QA.

# UX-08

## Bugs Found During QA

| Bug ID | Severity | Status | Affected Page | Expected Behavior | Actual Behavior | Possible Root Cause | Recommended Fix |
|---|---|---|---|---|---|---|---|
| UX08-BUG-001 | Medium | Verified | `/dashboard/cases/:id` | Case detail workflow tabs should default to the tab that matches the current case status or stage, for example recommendation cases should open on Recommendation and decision cases should open on Decision. | Verified fixed in UX-08 hotfix QA recheck: `dashboard.cases.$id.tsx` now computes `defaultWorkflowTab` from `current_stage`, `current_stage_label`, `status`, `status_label`, and `status_code`, then passes it to `<Tabs defaultValue={defaultWorkflowTab}>`. | Previous root cause was a static `defaultValue="investigation"` with no status-to-tab mapping. Hotfix added `WORKFLOW_TAB_BY_TOKEN`, normalization, and `defaultWorkflowTabForCase()`. | No further fix required. Keep UX08-ST-011 in manual smoke coverage for real case records across recommendation, decision, and recovery statuses. |

# UX-09A

## Bugs Found During QA

No new UX-09A bugs were found during QA.

# RC-03

## Bugs Found During QA

No new RC-03 bugs were found during QA.

# RC-04

## Bugs Found During QA

No new RC-04 bugs were found during QA.

# RC-05

## Bugs Found During QA

| Bug ID | Severity | Status | Affected Page | Expected Behavior | Actual Behavior | Possible Root Cause | Recommended Fix |
|---|---|---|---|---|---|---|---|
| RC05-BUG-001 | High | Verified | Dashboard Satgas assignment dialogs, including Forward Report / Assign Satgas flows | Satgas dialogs should be fully localized in both Bahasa Indonesia and English, and should not expose backend/API terminology to users. | Verified fixed in RC-05 hotfix QA recheck: `satgas-assignment-action.tsx` now uses `dashboard:workflow.assignment.*` i18n keys for loading, error, empty, labels, validation, success, and submit copy. The previous hardcoded English and "lookup API" wording are no longer present in the component. | Previous root cause was that RC-05 reintroduced or retained static Satgas dialog fallback copy outside i18n. Hotfix restored localized i18n usage. | No further fix required. Product Owner should execute RC05-ST-011 manually in Bahasa Indonesia and English. |
| RC05-BUG-002 | Medium | Verified | Dashboard pages using `frontend/src/locales/{id,en}/dashboard.json` | Dashboard user-facing copy should preserve RC-02 cleanup: no backend wording, no RBAC wording, no endpoint wording, no API wording, and no metadata wording where users can see it. | Verified fixed in RC-05 hotfix QA recheck: parsed dashboard locale values in both `id` and `en` contain no visible `backend`, `RBAC`, `endpoint`, `API`, or `metadata` wording. Remaining technical key names are not visible UI copy. | Previous root cause was incomplete or regressed dashboard locale value cleanup. Hotfix rewrote visible values to natural user-facing wording. | No further fix required. Keep RC05-ST-011 as manual regression coverage for visible dashboard copy. |

# REV-01

## Bugs Found During QA

| Bug ID | Severity | Status | Affected Page | Expected Behavior | Actual Behavior | Possible Root Cause | Recommended Fix |
|---|---|---|---|---|---|---|---|
| REV01-BUG-001 | High | Invalid / Not Reproducible on correct branch | `/dashboard/reports/:id` | Forwarded reports should show a persistent, neatly placed confirmation text such as "Kasus sudah diteruskan ke Satgas terpilih" near the report status/action area. | Recheck on `feature/rev-01-workflow-detail-polish`: not reproducible. `dashboard.reports.$id.tsx` renders an info `Alert` when `report.status === "forwarded"` and uses `dashboard:reports.forwardedNoticeTitle` / `forwardedNotice`; matching id/en locale keys exist. | Previous QA was executed on `main`, not the REV-01 branch. | No fix required for this bug on the correct branch. Keep `REV01-ST-001` for manual Product Owner validation. |
| REV01-BUG-002 | High | Invalid / Not Reproducible on correct branch | `/dashboard/cases` | The global "Aksi penugasan belum tersedia" placeholder should be removed; assignment should happen per case from case detail. | Recheck on `feature/rev-01-workflow-detail-polish`: not reproducible. `dashboard.cases.index.tsx` no longer renders the disabled global assignment placeholder; the list only exposes detail navigation. | Previous QA was executed on `main`, not the REV-01 branch. | No fix required for this bug on the correct branch. Keep `REV01-ST-002` for manual confirmation. |
| REV01-BUG-003 | High | Invalid / Not Reproducible on correct branch | `/dashboard/cases/:id` action rail for Satgas | Satgas users should not see an assign button. They should see an informational notice: "Penugasan Satgas dikelola oleh Admin/Pimpinan PPKS." | Recheck on `feature/rev-01-workflow-detail-polish`: not reproducible. Non-admin path renders `DisabledWorkflowAction` with `dashboard:cases.assignmentManagedBy`; id/en locale keys exist. Admin/Super Admin assignment action remains unchanged. | Previous QA was executed on `main`, not the REV-01 branch. | No fix required for this bug on the correct branch. Keep `REV01-ST-003` and `REV01-ST-004` for role-based manual validation. |
| REV01-BUG-004 | High | Invalid / Not Reproducible on correct branch | `/dashboard/cases/:id` action rail | Case detail action rail should start with a "Status Kasus Terkini" indicator and include a "Langkah Berikutnya" card derived from status/stage and `user.role.code`. | Recheck on `feature/rev-01-workflow-detail-polish`: not reproducible. Case detail renders `dashboard:cases.currentStatusTitle` and a `dashboard:cases.nextStep.*` card using `nextStepMessage()` with status and role-aware audience selection. | Previous QA was executed on `main`, not the REV-01 branch. | No fix required for this bug on the correct branch. Keep `REV01-ST-007` and `REV01-ST-008` for manual status/role coverage. |
| REV01-BUG-005 | Medium | Verified | `/dashboard/cases/:id` Create Investigation dialog | Investigation plan summary should show an upfront min-50 helper plus live character counter, warning color below 50 and muted color at 50 or more. All validation text should be localized. | Verified fixed in REV-01 hotfix QA recheck: `investigation-create-action.tsx` now creates the zod schema from localized `t("dashboard:workflow.*")` messages, and the old hardcoded `"Required"`, `"Minimum 50 characters"`, and `"Maximum 5000 characters"` strings are no longer present in the component. Helper text and live counter remain implemented. | Previous root cause was localized helper/counter coverage without localized zod validation messages. Hotfix added localized schema messages with matching id/en locale keys. | No further fix required. Product Owner should still execute `REV01-ST-006` manually in Bahasa Indonesia and English. |
| REV01-BUG-006 | High | Invalid / Not Reproducible on correct branch | `/dashboard/cases/:id` Recovery tab | "Tambah Monitoring" should be hidden or disabled with reason when recovery status is completed/terminal. | Recheck on `feature/rev-01-workflow-detail-polish`: not reproducible. `RecoveriesSection` renders `RecoveryMonitoringAction` only when `canAddMonitoring && item.status === "ongoing"`, so completed/discontinued rows do not show the action. | Previous QA was executed on `main`, not the REV-01 branch. | No fix required for this bug on the correct branch. Keep `REV01-ST-009` for manual terminal recovery coverage. |
| REV01-BUG-007 | Medium | Invalid / Not Reproducible on correct branch | `/dashboard/cases/:id` restricted-sensitive detail areas | Restricted-detail copy should use human role labels and the copy: "Akses detail dibatasi untuk menjaga kerahasiaan laporan. Pengguna dengan peran {{roleLabel}} hanya dapat melihat ringkasan operasional." | Recheck on `feature/rev-01-workflow-detail-polish`: not reproducible. `restrictedRoleLabel()` maps role codes to human labels and restricted sections render `dashboard:cases.restrictedDetail` with `roleLabel`; id/en locale keys exist. | Previous QA was executed on `main`, not the REV-01 branch. | No fix required for this bug on the correct branch. Keep `REV01-ST-010` for manual restricted-copy validation. |
| REV01-BUG-008 | Medium | Invalid / Not Reproducible on correct branch | Case status mutation in workflow action dialog | After a case status update, case detail, case list, dashboard, and My Work queries should refresh when applicable; no optimistic updates should be introduced. | Recheck on `feature/rev-01-workflow-detail-polish`: not reproducible. `CaseStatusAction` now invalidates `["operations", "case"]`, `["operations", "cases"]`, `["dashboard"]`, and `["my-work"]`. No optimistic update was found. | Previous QA was executed on `main`, not the REV-01 branch. | No fix required for this bug on the correct branch. Keep `REV01-ST-005` for manual status-refresh confirmation. |

# REV-02

## Bugs Found During QA

| Bug ID | Severity | Status | Affected Page | Expected Behavior | Actual Behavior | Possible Root Cause | Recommended Fix |
|---|---|---|---|---|---|---|---|
| REV02-BUG-001 | High | Verified | Backend test suite / reporter-safe timeline privacy test | REV-02 backend verification should pass, including feature tests for reporter-safe timeline privacy and access control. | Verified fixed in REV-02 hotfix QA recheck: `PortalReportTimelineTest` now creates a valid Admin user and uses that ID for `case_assignments.assigned_by`; the test also asserts the Admin name is not leaked. `php artisan test --filter=PortalReportTimelineTest` passed 6 tests / 50 assertions, and full `php artisan test` passed 181 tests / 1608 assertions. | Previous root cause was a test fixture inserting `assigned_by => null` into a non-nullable `case_assignments.assigned_by` column. Hotfix corrected the fixture without weakening privacy assertions. | No further fix required. Keep REV-02 smoke tests for manual portal timeline and privacy validation. |
