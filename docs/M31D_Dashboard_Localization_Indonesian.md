# M31-D — Dashboard Localization & Indonesian Default

## Goal

Before internal demo, ensure Indonesian is the default language for **all roles** — not only reporter. Every layout (Super Admin, Admin, Satgas, Reporter Portal, public pages) should render Indonesian labels by default, with a language switcher available to toggle to English.

## Status: DRAFT — Awaiting Approval

---

## Current State

| Area | Localized? | Notes |
|---|---|---|
| Reporter portal (`/portal/*`) | ✅ Yes | `portal` + `common` + `auth` namespaces, bilingual |
| Public pages (`/register`, `/track`) | ✅ Yes | `auth` + `portal` namespaces, bilingual |
| Registration states (`/registration/*`) | ✅ Yes | `auth` namespace |
| Login page | ✅ Yes | `auth` namespace |
| Dashboard layout (sidebar, topbar) | 🔴 No | 10 nav items + topbar strings hardcoded English |
| Dashboard pages (19 files) | 🔴 No | ~298 hardcoded English strings |
| Workflow action components (10 files) | 🔴 No | ~210 hardcoded English strings |
| Shared components | 🔴 Partial | `query-state.tsx`, `access-denied.tsx`, `status-badge.tsx` hardcoded |
| Root route (404, error, meta) | 🔴 No | English-only |
| `<html lang="">` attribute | 🔴 Hardcoded `"en"` | [__root.tsx L123](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/__root.tsx#L123) |
| i18n default language | ✅ `fallbackLng: 'id'` | But `LanguageDetector` with `localStorage` may override on first visit for EN-locale browsers |
| Language switcher | 🟡 Portal only | Not in dashboard layout |
| `dashboard` namespace | 🔴 Does not exist | No `locales/id/dashboard.json` or `en/dashboard.json` |

### Hardcoded String Inventory

| File / Group | Approx Strings | Priority |
|---|---|---|
| **Dashboard Layout** (nav + topbar) | ~15 | 🔴 High |
| **dashboard.index.tsx** (overview) | ~13 | 🔴 High |
| **dashboard.reports.index.tsx** | ~20 | 🔴 High |
| **dashboard.reports.$id.tsx** | ~16 | 🟡 Medium |
| **dashboard.cases.index.tsx** | ~19 | 🔴 High |
| **dashboard.cases.$id.tsx** | ~69 | 🔴 High |
| **dashboard.workflow.tsx** | ~16 | 🟡 Medium |
| **dashboard.analytics.tsx** | ~19 | 🟡 Medium |
| **dashboard.registrations.tsx** | ~7 | 🔴 High |
| **dashboard.registrations.$id.tsx** | ~18 | 🔴 High |
| **dashboard.users.tsx** | ~20 | 🔴 High |
| **dashboard.settings.tsx** | ~8 | 🟢 Low |
| **dashboard.break-glass.tsx** | ~3 | 🟢 Low |
| **dashboard.master-data.*.tsx** (4 files) | ~69 | 🟡 Medium |
| **Workflow actions** (10 components) | ~210 | 🔴 High |
| **Shared components** (access-denied, query-state, status-badge) | ~10 | 🟡 Medium |
| **Root 404/error** | ~5 | 🟢 Low |
| **Total** | **~537** | |

---

## Proposed Changes

### Sub-phase D1 — i18n Foundation & Default Language

#### [MODIFY] [i18n.ts](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/i18n.ts)

- Import new `dashboard` namespace JSON files for `id` and `en`
- Add `'dashboard'` to the `ns` array
- Ensure `fallbackLng: 'id'` is preserved
- Add `lng: 'id'` to **force** Indonesian as default regardless of browser locale (LanguageDetector still allows switching via localStorage, but first-visit is always Indonesian)

#### [NEW] `locales/id/dashboard.json`

New namespace with all Indonesian translations for dashboard pages. Estimated ~300 keys covering:
- Navigation labels
- Page titles and subtitles
- Card titles and descriptions
- Table headers
- Button labels
- Empty states
- Toast messages
- Workflow action labels
- Status labels
- Filter placeholders

#### [NEW] `locales/en/dashboard.json`

Mirror of Indonesian dashboard namespace with English translations.

---

### Sub-phase D2 — Dashboard Layout & Language Switcher

#### [MODIFY] [dashboard-layout.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/layouts/dashboard-layout.tsx)

- Add `useTranslation(['dashboard', 'common'])` to sidebar and topbar
- Replace 10 hardcoded nav `title` strings with `t('dashboard:navOverview')`, `t('dashboard:navReports')`, etc.
- Add `<LanguageSwitcher />` to the dashboard topbar (next to theme toggle)
- Replace "Settings", "Sign out" in dropdown with `t()` keys

#### [MODIFY] [__root.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/__root.tsx)

- Change `<html lang="en">` to use dynamic `i18n.language` value
- Localize 404 and error component strings using `useTranslation(['common'])`
- Add i18n keys to `common` namespace: `pageNotFound`, `pageNotFoundDesc`, `goHome`, `pageError`, `tryAgain`

---

### Sub-phase D3 — Dashboard Pages (High Priority)

Localize all **🔴 High** priority pages. For each page:
- Add `useTranslation(['dashboard'])` import
- Replace all hardcoded English strings with `t()` calls
- Use consistent key naming: `{pageName}.{element}` (e.g., `overview.title`, `reports.searchPlaceholder`)

#### [MODIFY] Pages:

| File | Approx Keys |
|---|---|
| [dashboard.index.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.index.tsx) | ~13 |
| [dashboard.reports.index.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.reports.index.tsx) | ~20 |
| [dashboard.cases.index.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.cases.index.tsx) | ~19 |
| [dashboard.cases.$id.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.cases.$id.tsx) | ~69 |
| [dashboard.registrations.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.registrations.tsx) | ~7 |
| [dashboard.registrations.$id.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.registrations.$id.tsx) | ~18 |
| [dashboard.users.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.users.tsx) | ~20 |

#### [MODIFY] Workflow Actions (10 files in `components/workflow-actions/`):

| File | Approx Keys |
|---|---|
| [workflow-action-dialogs.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/components/workflow-actions/workflow-action-dialogs.tsx) | ~88 |
| [satgas-assignment-action.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/components/workflow-actions/satgas-assignment-action.tsx) | ~19 |
| [investigation-create-action.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/components/workflow-actions/investigation-create-action.tsx) | ~10 |
| [investigation-status-action.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/components/workflow-actions/investigation-status-action.tsx) | ~11 |
| [recommendation-create-action.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/components/workflow-actions/recommendation-create-action.tsx) | ~16 |
| [recommendation-status-action.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/components/workflow-actions/recommendation-status-action.tsx) | ~11 |
| [decision-create-action.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/components/workflow-actions/decision-create-action.tsx) | ~18 |
| [decision-status-action.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/components/workflow-actions/decision-status-action.tsx) | ~11 |
| [recovery-create-action.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/components/workflow-actions/recovery-create-action.tsx) | ~14 |
| [recovery-status-action.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/components/workflow-actions/recovery-status-action.tsx) | ~12 |

---

### Sub-phase D4 — Dashboard Pages (Medium/Low Priority)

#### [MODIFY] Pages:

| File | Approx Keys |
|---|---|
| [dashboard.reports.$id.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.reports.$id.tsx) | ~16 |
| [dashboard.workflow.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.workflow.tsx) | ~16 |
| [dashboard.analytics.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.analytics.tsx) | ~19 |
| [dashboard.settings.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.settings.tsx) | ~8 |
| [dashboard.break-glass.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.break-glass.tsx) | ~3 |
| `dashboard.master-data.tsx` + 3 sub-pages | ~69 |

#### [MODIFY] Shared components:

| File | Approx Keys |
|---|---|
| [access-denied.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/components/access-denied.tsx) | ~3 |
| [query-state.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/components/query-state.tsx) | ~3 |
| [status-badge.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/components/status-badge.tsx) | ~7 |

---

### Sub-phase D5 — Verification & Build

- Run `npm run build` — must pass
- Run `tsc --noEmit` — must pass
- Grep for remaining hardcoded English strings in `.tsx` files
- Verify language switcher toggles all dashboard labels
- Verify login → pending → correction flow remains bilingual
- Verify portal flow remains bilingual
- Manual spot-check on mobile viewport

---

## Localization Strategy

### Namespace Design

| Namespace | Scope | Status |
|---|---|---|
| `common` | Shared across all layouts (save, cancel, back, next, sign out, theme toggle, error pages) | Exists — extend with ~5 keys |
| `auth` | Login, registration, pending/correction pages | Exists — no changes |
| `portal` | Reporter portal pages | Exists — no changes |
| `dashboard` | **NEW** — All dashboard pages, sidebar nav, workflow actions | ~300 keys |

### Key Naming Convention

```
{section}.{element}

Examples:
  nav.overview          → "Ringkasan"
  nav.reports           → "Laporan"
  overview.title        → "Ringkasan Dashboard"
  overview.subtitle     → "Tampilan real-time laporan masuk, progres kasus, dan aktivitas tim."
  overview.totalReports → "Total Laporan"
  cases.title           → "Daftar Kasus"
  cases.backToList      → "Kembali ke daftar"
  workflow.createInvestigation → "Buat Investigasi"
  workflow.submitFindings → "Kirim Temuan"
  users.createReporter  → "Tambah Pelapor"
  users.resetPassword   → "Reset Kata Sandi"
  users.tempPasswordWarning → "Salin dengan aman sekarang. Kata sandi sementara tidak disimpan setelah pesan ini ditutup."
```

### Translation Approach

- **Direct Indonesian equivalents** for standard UI terms (Laporan, Kasus, Status, Cari, Simpan)
- **Domain-specific terms** per SOP: Satgas PPKS, Pelapor, Terlapor, Investigasi, Rekomendasi, Putusan, Pemulihan
- **Keep English technical terms** where Indonesian equivalent is unclear or would confuse operators: e.g., "Break-glass" stays as "Break-glass", "NIM" stays as "NIM"
- **Status codes** from backend (e.g., `submitted`, `under_review`) are mapped to Indonesian display labels in the locale file, not in the backend
- **No backend changes** — all localization is frontend-only string replacement

---

## Acceptance Criteria

### Must-Have (Blocking)

1. ✅ Indonesian is the default language on first visit (no localStorage override for new users)
2. ✅ `<html lang="id">` when Indonesian is active; `<html lang="en">` when English is active
3. ✅ Language switcher is present in both dashboard topbar AND portal topbar
4. ✅ All dashboard sidebar navigation items are localized
5. ✅ All page titles, subtitles, and empty states are localized
6. ✅ All table headers are localized
7. ✅ All button labels (approve, reject, create, edit, activate, deactivate, reset password) are localized
8. ✅ All workflow action dialog titles and labels are localized
9. ✅ All toast messages are localized
10. ✅ Switching language updates all visible text immediately (no page reload needed)
11. ✅ `npm run build` passes
12. ✅ `tsc --noEmit` passes (no TypeScript errors)
13. ✅ Existing reporter/portal/auth localization is unchanged

### Nice-to-Have (Non-blocking)

14. 🟡 404 and error pages localized
15. 🟡 SEO meta tags (og:title, description) reflect active language
16. 🟡 Filter placeholder text localized
17. 🟡 Chart axis labels and legend text localized (Recharts)

---

## Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Missing translation keys cause `t()` to return raw keys in UI | 🟡 Medium | Use `i18n.options.saveMissing = true` during development to log missing keys; final grep scan |
| `LanguageDetector` overrides `lng: 'id'` for EN-locale browser users on first visit | 🟡 Medium | Set `lng: 'id'` explicitly AND keep `fallbackLng: 'id'`; detector only activates after user manually switches |
| Inconsistent Indonesian terminology across pages | 🟡 Medium | Use SOP document as terminology reference; maintain a glossary section in the locale file |
| Recharts labels from backend (status codes) render in English | 🟢 Low | Create a `labelFromKey` utility that looks up translations; or accept backend codes for demo |
| Long Indonesian translations break layout on small dashboard cards | 🟢 Low | Test on responsive viewport; use `truncate` class where needed |

---

## Open Questions

> [!IMPORTANT]
> **OQ-1: Should the html `<title>` tag also be in Indonesian?**
>
> Currently `"Overview - SafeCampus Admin"`. Should this become `"Ringkasan - SafeCampus Admin"` when in ID mode?
> - If yes: requires dynamic `head()` function per route, which TanStack Start supports but adds complexity.
> - If no: English-only page titles are acceptable for browser tabs and bookmarks.

> [!IMPORTANT]
> **OQ-2: What to do with backend-returned status codes in labels?**
>
> Dashboard receives status values like `submitted`, `under_review`, `in_progress` from the backend API. These are displayed in Badge components and Recharts legends.
> - **Option A:** Create a frontend mapping function `statusToLabel(code, lang)` that translates codes to display labels. More work but fully localized.
> - **Option B:** Keep backend codes as-is (Title Case formatting). Less work, but mixed language when in Indonesian mode.
> - **Recommended:** Option A — the `status-badge.tsx` already has a mapping pattern. Extend it with i18n keys.

> [!NOTE]
> **OQ-3: Should "SafeCampus" brand name be translated to "SILAPPKASAL"?**
>
> The dashboard sidebar header says "SafeCampus" / "Admin Console". The portal topbar says "SafeCampus". Should these become "SILAPPKASAL" in Indonesian mode, or keep "SafeCampus" as the universal brand?

---

## Effort Estimate

| Sub-phase | Scope | Estimated Keys | Effort |
|---|---|---|---|
| D1 — i18n Foundation | Config + 2 new JSON files | ~300 keys (authoring) | Medium |
| D2 — Layout + Switcher | 2 layout files + root | ~20 keys (modification) | Small |
| D3 — High Priority Pages | 7 pages + 10 workflow components | ~380 replacements | Large |
| D4 — Medium/Low Pages | 9 pages + 3 shared components | ~145 replacements | Medium |
| D5 — Verification | Build + grep + manual | — | Small |
| **Total** | **31 files modified, 2 new files** | **~537 keys** | |

---

## Recommended Execution Order

1. **D1** — Create locale files and update i18n config (foundation must exist before pages can reference keys)
2. **D2** — Layout and language switcher (immediately visible change, validates the i18n pipeline)
3. **D3** — High-priority pages in order: layout nav → overview → registrations → users → reports → cases → workflow actions
4. **D4** — Remaining pages: analytics, workflow, settings, break-glass, master-data, shared components
5. **D5** — Final build verification and missing-key sweep
