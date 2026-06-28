# UX-01

## Bugs Found During QA

| Bug ID | Severity | Status | Affected Page | Expected Behavior | Actual Behavior | Possible Root Cause | Recommended Fix |
|---|---|---|---|---|---|---|---|
| UX01-BUG-001 | Medium | Verified | `/register`, `/registration/correction`, `/dashboard/users` Create Reporter | Client-side validation messages should be localized and consistent with the active language, especially because Bahasa Indonesia is the default experience. | Verified fixed in hotfix QA recheck: zod schemas now use `common:validation.required` and `common:validation.email`, with matching keys in `id/common.json` and `en/common.json`. | Previous root cause was untranslated zod schema messages. Hotfix added localized validation messages. | No further fix required. Keep UX-01 smoke localization case for manual Product Owner verification. |
| UX01-BUG-002 | Low | Verified | `/register`, `/registration/correction` | Password confirmation fields should fail client-side when confirmation does not match the password. | Verified fixed in hotfix QA recheck: registration validates `password_confirmation` with `.refine()`, and registration correction validates `new_password_confirmation` with `.superRefine()`. | Previous root cause was missing client-side cross-field validation. Hotfix added confirmation mismatch validation mapped to the confirmation field. | No further fix required. Keep password confirmation mismatch in manual regression smoke coverage. |
