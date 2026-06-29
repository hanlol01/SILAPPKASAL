# FRONTEND_REVIEW.md — SILAPPKASAL Frontend Holistic UX Review (UX-09)

> Reviewer role: Principal UX Reviewer
> Scope: `frontend/` only — read-only review for internal demo readiness
> Audience: Project Owner + Executive demo stakeholders
> Inputs read: `docs/UX_IMPROVEMENT_PLAN.md`, `docs/REPORT_UX_AUDIT.md`, `docs/QA_REPORT.md`, `docs/BUG_REPORT.md`, `docs/SMOKE_TEST.md`
> Code surfaces inspected: `frontend/src/routes/*`, `frontend/src/layouts/*`, `frontend/src/components/*`, `frontend/src/lib/*`, `frontend/src/locales/{id,en}/*`, `frontend/src/styles.css`
> Out of scope: backend, API contracts, RBAC, database, seeders, mobile/, redesign, framework changes.
> Output policy: this document only. No source files modified.

---

## Executive Summary

### Overall UX Score: **82 / 100**

SILAPPKASAL has matured significantly through UX-01..UX-08. The shadcn/Radix + Tailwind v4 + TanStack stack delivers a coherent, modern visual language, and the canonical form pattern (react-hook-form + zod + `applyLaravelErrors` + sonner) is now consistently adopted across public, portal, and admin surfaces. The reporter Report Wizard, status badges, master-data console, analytics, and case detail are demo-quality. Localization (id/en), responsive admin tables (mobile card fallback), breadcrumbs on detail pages, multi-channel status badges, and confirmation `AlertDialog`s on destructive actions all land as designed.

The remaining gap between "polished SaaS" and "production-ready government institutional product" is narrow but visible. Three surfaces still feel like prototype/developer UI and would be the first things an executive notices: (1) the **Settings page** is entirely hardcoded English with UI-only switches and a literal "Drop an SVG or PNG here (UI-only)" placeholder, (2) the **Break-glass page** is fully hardcoded English with raw status copy, and (3) the **sidebar footer literally reads "prototype"** via `dashboard:brand.prototype`. The login page also displays "2026 SILAPPKASAL - Prototipe rahasia". Together these undermine the institutional posture the rest of the product earns. The portal landing page is also thin (summary cards only, no recent-activity or next-step CTA), and the case detail page leans heavily on neutral `outline` Badges instead of the multi-channel `StatusBadge` it ships, weakening visual consistency at the most-watched workflow surface.

No blocker for an internal demo. Several P0 items can be resolved with copy edits and small surface cleanups (no contract change), and would move the score into the **90s** for the demo session.

### Strengths

- **Coherent design system**: shadcn primitives, OKLCH-based semantic tokens (`primary`, `info`, `success`, `warning`, `destructive`), Inter font, `0.75rem` radius, dark mode parity. The visual feel matches contemporary government-grade SaaS.
- **Mature form pattern**: RHF + zod + shared `TextInputField`/`SelectFormField`/`DatePicker`/`TimePicker`, with field-level `applyLaravelErrors` and a localized fallback toast. The reporter wizard is well-architected (per-step trigger, server-error step jump, `WizardProgress` indicator with check marks).
- **Trauma-sensitive reporter UX**: incident-time "Saya tidak ingat waktunya", quick-pick chips (Pagi/Siang/Sore/Malam), future-date and future-time guards, content advisory banner above the wizard.
- **Privacy posture**: `PortalStatusBadge` localized via i18n, anonymous-report `Lock` badge on reports list, sensitive-report card on case detail, `AccessDenied` route guards, masked reporter affordance (`identityHidden`) in admin reports table.
- **Operational depth on case detail**: workflow grouped into `Tabs` (Investigation / Recommendation / Decision / Recovery / Evidence), default tab derived from `current_stage` / `status_code`, sticky right-rail with role-aware action availability and `DisabledWorkflowAction` info alerts explaining *why* an action is disabled.
- **Responsive baseline**: reports/cases lists ship dedicated `md:hidden` mobile card layout; all other admin tables wrapped in `overflow-x-auto`; portal nav labels visible at every breakpoint with `aria-label` fallback; toast position `top-center`.
- **Accessibility floor**: `StatusBadge` is multi-channel (icon + label + color), avatar menu has `aria-label={t("common:userMenu")}`, language switcher carries `sr-only`, contrast on `--success` ≈ 6.65:1 and `--info` ≈ 6.89:1 vs white (AA-pass per QA-UX07-006).
- **Localization architecture is correct**: four namespaces (`common`, `auth`, `portal`, `dashboard`), `id` as default with `en` fallback, `format-labels.ts` as a single enum-to-label boundary, `formatDateTime(value, i18n.language)` everywhere it matters.

### Weaknesses

- **Three prototype-flavored surfaces remain visible in the demo path**: `dashboard.settings.tsx` (English-only, UI-only switches, `(UI-only)` literal), `dashboard.break-glass.tsx` (English-only, raw status copy), and the sidebar footer `dashboard:brand.prototype` + login overlay `"2026 SILAPPKASAL - Prototipe rahasia"`.
- **Brand identity is generic**: the brand mark in both layouts is a generic `ShieldCheck` Lucide icon. There is no institutional crest, no government identity (Kemendikbudristek lineage, campus PPKS lineage), no campus selector in the topbar even though multi-campus support exists in master data.
- **Case detail uses neutral `Badge variant="outline"` for status** in the header and per-section rows instead of the project's own multi-channel `StatusBadge`. The strongest visual asset (icon + tone + label) is not used on the highest-stakes operational page.
- **Portal landing page is thin**: it shows summary cards only. No recent reports preview, no "continue your draft" CTA, no SLA expectation copy, no next-best-action.
- **Information architecture lacks a global anchor**: no breadcrumbs on top-level list pages (only on detail pages), no "Beranda" link on the public/login surfaces, and `/` redirects directly to `/login` with no marketing/landing context. A reviewer arriving at the bare URL sees only a login form.
- **Dashboard topbar negative space**: after UX-03 removed the dead search input, the topbar reads `SidebarTrigger` → empty `flex-1` → controls. It feels under-furnished. A breadcrumb trail or scope/campus indicator would fill the gap meaningfully.
- **`dashboard.cases.$id.tsx` still uses a plain text `"loading"` initial state** rather than a Skeleton (acknowledged in QA-UX08-013 as residual). Inconsistent with the rest of the app.
- **Workflow page conversion-counts denominator** divides by `Math.max(...)` so the Progress bars are *relative* not *funnel* values; without a footnote this can mislead an executive looking at a literal "100%" on the largest stage.
- **Confidentiality on settings storage**: "Campus profile", "Brand logo", and notification preferences are stored in `localStorage` under `safecampus_settings` — a stale code name ("safecampus") leaks the product's prior identity, and `localStorage` is not appropriate for institution-level settings semantically.
- **Auto-generated meta tags carry encoding artifacts**: several routes have `"Overview â€” SILAPPKASAL Portal"` in `<title>`, indicating an em-dash was double-encoded at some point. Visible in browser tabs.
- **Form helper text density is uneven on the reporter wizard**: step 2 chronology shows `0/10000` counter, but no minimum hint is shown until validation fires. Combined with the trauma-sensitive context, an upfront "Minimal 50 karakter" inline hint would lower the cost of the first validation slap.
- **Recovery/decision badges still rely on generic `Badge variant="outline"`** even though the project ships full multi-channel status badges for case status. Status semantics across the workflow surface read as visually flat.

---

## Recommendations

Each recommendation includes: **ID**, **Category**, **Page(s)**, **Description**, **Why it matters**, **Recommended solution**, and **Estimated effort** (S = ≤0.5d, M = 0.5–2d, L = 2–5d).

### Critical (must address before executive demo)

#### C-01 — Remove explicit "prototype" labels from demo path

- **Category**: Demo Readiness, Localization
- **Page(s)**: `layouts/dashboard-layout.tsx` (sidebar footer via `dashboard:brand.prototype`), `routes/login.tsx` (`"2026 SILAPPKASAL - Prototipe rahasia"` left-rail footer)
- **Description**: The sidebar footer text and the login page hero overlay both literally tell the viewer this is a prototype. The sidebar string is even keyed (`dashboard:brand.prototype`) which means it is intentionally translated, not a stray debug string.
- **Why it matters**: For an internal demo to executives this single line frames the entire product as not-real. It contradicts the institutional posture the rest of the UI works hard to project. It is the first thing a reviewer sees in the left rail and the first thing on the login page.
- **Recommended solution**: Replace `dashboard:brand.prototype` value with an institutional footer string (e.g. `"Sistem Pelaporan & Penanganan Kekerasan Seksual"` plus a build/version line, no "prototype" wording). On `login.tsx` remove `"Prototipe rahasia"` from the year line and either drop it or replace with a generic copyright + institution name. Both are pure copy edits in `locales/id/dashboard.json`, `locales/en/dashboard.json`, and `routes/login.tsx`.
- **Effort**: S

#### C-02 — Localize and de-prototype the Settings page

- **Category**: Localization, Demo Readiness, Design Consistency
- **Page**: `routes/dashboard.settings.tsx`
- **Description**: Settings is entirely hardcoded English (`"Settings"`, `"Customize your campus profile, theme, and notifications."`, `"Campus profile"`, `"Brand logo"`, `"Dark mode"`, `"High-contrast tables"`, `"Email alerts"`, ...) and contains a `"Drop an SVG or PNG here (UI-only)"` literal in a dashed box. The default campus name is `"Universitas Indonesia"` with motto `"Veritas, Probitas, Justitia"` hardcoded — a specific institution's identity baked into defaults. Storage key is `safecampus_settings` (legacy product name leak). A `"High-contrast tables"` switch is wired to `defaultChecked` with no handler.
- **Why it matters**: Settings is one of the few pages an executive will click into to verify configurability. The page currently shouts prototype on every line: English-only in an Indonesian-default product, `(UI-only)` confession, dead switches, legacy storage key, and a default that names another institution.
- **Recommended solution**: Migrate every visible string to `dashboard:settings.*` keys in both `id` and `en` JSON. Replace the brand-logo drop area with either a real upload affordance or a clearly worded "Belum tersedia" placeholder (without the words "UI-only"). Remove or wire the high-contrast switch. Rename storage key to a project-scoped namespace (e.g. `silappkasal.settings.v1`) — guarded so old keys still hydrate during a migration window. Replace hardcoded campus defaults with empty strings sourced from the authenticated user's campus context.
- **Effort**: M

#### C-03 — Localize and clean the Break-glass page

- **Category**: Localization, Demo Readiness
- **Page**: `routes/dashboard.break-glass.tsx`
- **Description**: The page is fully hardcoded English: title `"Break-glass"`, `"Review exceptional access requests for anonymous report identities."`, `"Pending requests"`, `"History"`, `"Loading pending requests..."`, `"No pending break-glass requests."`, pagination text `"Page X of Y"` / `"Previous"` / `"Next"`. The term "Break-glass" itself is a technical/security pattern not a user-facing label.
- **Why it matters**: This is the most privacy-sensitive page in the product. It is where a super admin requests/approves access to anonymous reporter identities. The page's copy needs to read with institutional gravity, in Bahasa Indonesia, and avoid technical jargon. Executive viewers will judge institutional seriousness on this exact page.
- **Recommended solution**: Add a `dashboard:breakGlass.*` block in both locales. Choose an institutional label such as `"Akses Darurat Identitas Pelapor"` (or whatever the privacy team prefers). Replace pagination strings with already-existing or new `common:pagination.*` keys. Replace the plain `"Loading..."` strings with `Skeleton` rows consistent with the rest of the app (UX-08 standard).
- **Effort**: M

#### C-04 — Adopt the multi-channel `StatusBadge` on the case detail header and section rows

- **Category**: Design Consistency, Accessibility
- **Page**: `routes/dashboard.cases.$id.tsx`
- **Description**: The case header renders `<Badge variant="outline">{formatCaseStatus(t, c.status_code)}</Badge>` and every workflow section row uses `<Badge variant="outline">` for investigation/recommendation/decision/recovery/evidence status. The project ships `components/status-badge.tsx` which gives icon + tonal color + localized label and has been contrast-verified for WCAG AA.
- **Why it matters**: Case detail is the single highest-stakes operational page Satgas and admins will demo. Using the neutral outline Badge here loses the visual hierarchy investment from UX-07 and makes the page look flatter than reports/cases lists.
- **Recommended solution**: Replace the header status with `<StatusBadge status={c.status as CaseStatus} />` (cast guarded by an enum check). For workflow-stage statuses that don't share `CaseStatus`, introduce sibling `InvestigationStatusBadge`, `DecisionStatusBadge` (same shape, different tone map) — reuse the same `Badge` + icon + tone pattern. At minimum, color-code the existing outline badges by status without changing the component API.
- **Effort**: M

### High

#### H-01 — Strengthen the portal landing page with next-best-action

- **Category**: Dashboard Experience, User Journey (Reporter)
- **Page**: `routes/portal.index.tsx`
- **Description**: Currently shows only `PortalSummaryCards`. After a reporter logs in there is no preview of recent reports, no "Lapor sekarang" CTA, no SLA expectation copy ("Laporan Anda akan ditinjau dalam 3 hari kerja"), no link to track a previous code.
- **Why it matters**: The reporter is the most fragile user in this product. The landing page should orient them quickly: "Saya bisa apa di sini?" The current page leaves them to interpret bare numbers. For a demo, the empty-feeling overview also reads as unfinished.
- **Recommended solution**: Below the summary cards, add (1) a primary CTA card with `PlusCircle` icon linking to `/portal/reports/new`, (2) a compact list of the 3 most recent reports with their `PortalStatusBadge` and a "Lihat semua" link to `/portal/reports`, and (3) an advisory paragraph reusing the trauma-sensitive copy already present in `portal.json`. No new endpoint needed; reuse `getPortalSummary` and the existing reports query.
- **Effort**: M

#### H-02 — Fix encoded em-dashes in `<title>` meta

- **Category**: Demo Readiness, Localization
- **Pages**: `routes/portal.index.tsx`, `routes/portal.notifications.tsx`, `routes/dashboard.settings.tsx` (`Settings â€” SILAPPKASAL Admin`)
- **Description**: Several `head().meta[].title` values contain `â€”` — the classic UTF-8/Latin-1 double-encoding artifact for `—`. These appear in browser tabs and bookmarks.
- **Why it matters**: Garbled text in browser tabs is the kind of detail a demo audience notices and that contradicts the product's polish elsewhere.
- **Recommended solution**: Replace the em-dash with a plain ASCII hyphen-space-hyphen (`SILAPPKASAL - Portal`) or with a properly-encoded em-dash (`—`). Apply the same fix wherever the artifact appears across `routes/*`.
- **Effort**: S

#### H-03 — Replace generic shield brand mark with institutional identity

- **Category**: Demo Readiness, Information Architecture
- **Pages**: `layouts/dashboard-layout.tsx`, `layouts/portal-layout.tsx`, `routes/login.tsx`
- **Description**: Brand mark in the sidebar and portal topbar is a generic `ShieldCheck` from Lucide. The login page does use `/Logo.ico` (already a real asset) but the rest of the app does not.
- **Why it matters**: Institutional applications carry visual lineage (crest, ministry mark, university lineage). A generic shield reads as template/prototype. The asset already exists in `public/`.
- **Recommended solution**: Use `/Logo.ico` (or a proper SVG variant if available) in the sidebar header and portal topbar, scaled appropriately. If a partner-ministry crest is required by the institution, add a placeholder secondary-mark slot. No code-level component change — this is an asset swap inside the existing brand block.
- **Effort**: S

#### H-04 — Add an inline minimum-length hint to wizard chronology

- **Category**: User Journey (Reporter), Form Consistency
- **Page**: `routes/portal.reports.new.tsx`
- **Description**: The chronology field on step 2 of the report wizard shows `chronology.length/10000` as helper text. The 50-character minimum is only revealed after the user clicks Lanjut. The product targets a fragile reporter.
- **Why it matters**: Trauma-sensitive forms should never lead with negative reinforcement. A reporter who is told "min 50 chars" after they thought they were done feels challenged at the worst moment.
- **Recommended solution**: Render an additional `FormDescription` line such as `t("portal:reportWizard.chronologyMinHint")` reading "Minimal 50 karakter agar kami bisa menindaklanjuti." Switch the live counter color to `text-warning` while length < 50 and to `text-muted-foreground` once ≥ 50.
- **Effort**: S

#### H-05 — Add Skeleton initial state to case detail

- **Category**: Loading consistency
- **Page**: `routes/dashboard.cases.$id.tsx`
- **Description**: While `caseQuery.isLoading`, the page renders `<div className="py-12 text-center text-sm text-muted-foreground">Loading...</div>`. Every other detail/list page in the product has moved to Skeletons (UX-08 standard). This was acknowledged in QA-UX08-013 as residual risk.
- **Why it matters**: Case detail is the densest page; the layout shift when the spinner is replaced by the real card is visible. Skeletons preserve geometry.
- **Recommended solution**: Build a `CaseDetailSkeleton` mirroring the metadata card + tabs strip + right-rail layout. Render it during `caseQuery.isLoading`.
- **Effort**: S

#### H-06 — Restore breadcrumbs (or a back affordance) on top-level admin lists

- **Category**: Information Architecture, Navigation
- **Pages**: `routes/dashboard.reports.index.tsx`, `routes/dashboard.cases.index.tsx`, `routes/dashboard.registrations.tsx`, `routes/dashboard.users.tsx`, `routes/dashboard.master-data.*.tsx`, `routes/dashboard.workflow.tsx`, `routes/dashboard.analytics.tsx`
- **Description**: UX-06 added breadcrumbs to detail pages but list pages remain unanchored. The dashboard topbar is also empty after UX-03 removed the dead search.
- **Why it matters**: Without a list-level breadcrumb the topbar visually leaks empty space and the user must rely on the sidebar to know where they are. A breadcrumb that reads `Beranda / Laporan` is a small lift but a big institutional signal.
- **Recommended solution**: Render `Breadcrumb` consistently at the top of every list page with parent `Beranda` (`/dashboard`) and current page name from i18n. Alternatively or additionally, move the active section title from the page body up into the topbar to fill the empty `flex-1` and become the implicit "you are here" anchor.
- **Effort**: M

#### H-07 — Disambiguate the workflow pipeline "100%" progress bars

- **Category**: Dashboard Experience, Clarity
- **Page**: `routes/dashboard.workflow.tsx`
- **Description**: Each step's `Progress` bar uses `Math.round((count / maxConversion) * 100)` where `maxConversion = Math.max(...counts, 1)`. The largest stage will always render as a full bar, which an executive will read as "100% conversion".
- **Why it matters**: This is a perception bug, not a logic bug. The page's purpose is to communicate the operational funnel.
- **Recommended solution**: Either (a) compute a true funnel ratio against the first stage (`count / firstStageCount`), or (b) keep the relative bar but add a small caption beneath each bar saying `t("dashboard:workflow.pipeline.relativeBarHint")` reading "Skala relatif terhadap tahap terbanyak." The latter is the conservative, demo-safe choice.
- **Effort**: S

### Medium

#### M-01 — Add a global page meta consistency pass

- **Category**: Demo Readiness
- **Pages**: All `routes/*` declaring `head().meta`
- **Description**: Page titles are a mix of `Title - SILAPPKASAL Admin`, `Title — SILAPPKASAL Portal`, `Masuk - SILAPPKASAL`, and broken-encoded variants (see H-02). Some pages declare no title at all.
- **Why it matters**: Browser tabs are part of the demo. Inconsistent suffixes feel sloppy in screen sharing and recording.
- **Recommended solution**: Standardize on `SILAPPKASAL · Section · Page` or similar. Centralize via a small helper `buildPageTitle(section, page)`. Audit every `head()` call to use it.
- **Effort**: M

#### M-02 — Localize and harden access-denied page

- **Category**: Localization, Accessibility
- **Page**: `components/access-denied.tsx` (used by `routes/dashboard.content.tsx` and via guards on analytics, break-glass, reports)
- **Description**: Not verified end-to-end in this review but `dashboard.content.tsx` is just `component: AccessDenied`, meaning the route exists in the sidebar map and resolves to a denial page. This is OK as a fallback but reads as an unfinished navigation entry if listed.
- **Why it matters**: A nav item that always leads to access denied is a UX dead end.
- **Recommended solution**: Either remove the `/dashboard/content` route entry from the sidebar (it isn't currently in `nav` array — verify and prune), or convert the route to a real content surface. Verify `AccessDenied` copy is localized.
- **Effort**: S

#### M-03 — Strengthen empty state on portal notifications

- **Category**: Empty states, User Journey (Reporter)
- **Page**: `routes/portal.notifications.tsx`
- **Description**: Empty state uses a card with `Inbox` icon and two lines, which is good. However the page-level advisory (`notificationsAdvisory`) reads as a footnote and may be missed. The unread-count copy can also conflict semantically with the advisory ("Anda memiliki 3 notifikasi belum dibaca" while advisory says "akan ditandai otomatis").
- **Why it matters**: Mixed messaging on the page that establishes notification semantics.
- **Recommended solution**: Move the advisory into a small `Alert` (info variant) directly above the list, not below the subtitle. Refine the copy in coordination with the Project Owner so unread-count and advisory don't contradict.
- **Effort**: S

#### M-04 — Add a campus/scope indicator to the dashboard topbar

- **Category**: Information Architecture, Demo Readiness
- **Page**: `layouts/dashboard-layout.tsx`
- **Description**: Multi-campus master data exists but the topbar gives no indication of which campus scope the admin is currently viewing. Analytics and overview pages show a `scope` value inside cards but the persistent chrome doesn't.
- **Why it matters**: A super admin demoing across campuses needs an at-a-glance scope. Without it, the same dashboard "feels" the same regardless of context, which weakens the multi-campus story.
- **Recommended solution**: Add a scope `Badge` next to the sidebar trigger in the topbar showing `summary.scope` or current-campus name when available. Surface a `DropdownMenu` if the user can switch scopes; otherwise read-only.
- **Effort**: M

#### M-05 — Consolidate workflow stage badges into a multi-channel component

- **Category**: Design Consistency, Accessibility
- **Pages**: `routes/dashboard.cases.$id.tsx`, `routes/dashboard.workflow.tsx`
- **Description**: Investigation/recommendation/decision/recovery/evidence statuses are rendered with `<Badge variant="outline">`. The product proves the multi-channel pattern works (case status, portal status). Extending it to the workflow sub-statuses would unify the visual story.
- **Why it matters**: When five different status families on the same page are visually identical, color-blind and low-vision users can't differentiate them quickly, and the page reads as flat to everyone.
- **Recommended solution**: Create `WorkflowStatusBadge` accepting `{ family: "investigation" | "recommendation" | "decision" | "recovery" | "evidence", code }`. Map to icon + tone from a single table. Adopt across both pages.
- **Effort**: M

#### M-06 — Reduce the verbosity of the wizard's content-warning amber banner on subsequent steps

- **Category**: User Journey (Reporter)
- **Page**: `routes/portal.reports.new.tsx`
- **Description**: The amber `reportContentWarning` banner is shown above the wizard card on every step. Once the reporter has acknowledged it on step 1, it competes for attention with the form fields on steps 2 and 3.
- **Why it matters**: Repeated high-affect copy desensitizes and creates visual noise during the most cognitively expensive parts of the form.
- **Recommended solution**: Render the banner only on step 1; on steps 2 and 3 render a much smaller `Lock`-icon line in the card footer reading "Disimpan dengan kerahasiaan PPKS" or equivalent.
- **Effort**: S

#### M-07 — Add an explicit anonymous-reporter marker on the portal report detail header

- **Category**: Privacy, Design Consistency
- **Page**: `routes/portal.reports.$registrationNumber.tsx`
- **Description**: Per `BUG_REPORT.md` and `QA-UX06-007` the page has breadcrumbs and the portal status badge is multi-channel. The anonymous-report `Lock` badge present in the admin reports table should also be mirrored on the portal detail page so the reporter sees clearly that their identity is masked.
- **Why it matters**: Privacy trust is the entire product proposition. The reporter should *see* the lock.
- **Recommended solution**: Add an `anonymous` indicator (icon + tone + text) in the portal detail header when `report.is_anonymous || report.report_type === 'anonymous'`.
- **Effort**: S

#### M-08 — Skeletonize remaining `"Loading…"` text states

- **Category**: Loading consistency, Demo Readiness
- **Pages**: `routes/dashboard.break-glass.tsx`, `routes/dashboard.reports.index.tsx` (initial query), `routes/dashboard.cases.$id.tsx`
- **Description**: A handful of pages still emit `<div>Loading...</div>` rather than Skeletons. UX-08 standardized this elsewhere.
- **Why it matters**: Visual stability and demo polish.
- **Recommended solution**: Replace with Skeleton primitives matching the card/table shape.
- **Effort**: S

#### M-09 — Add an institutional footer to public surfaces

- **Category**: Demo Readiness, Information Architecture
- **Pages**: `routes/login.tsx`, `routes/register.tsx`, `routes/track.tsx`, `routes/registration.pending.tsx`, `routes/registration.correction.tsx`
- **Description**: Public pages have no footer with institution name, support contact, privacy policy link, or accessibility statement.
- **Why it matters**: Institutional products are expected to carry these footers; their absence reads as not-yet-published.
- **Recommended solution**: Add a thin `<footer>` with institution name, year, and at least one link to a privacy/accessibility page (or a `mailto:` for support while pages don't exist).
- **Effort**: S

### Low

#### L-01 — Rename legacy localStorage key `safecampus_settings`

- **Category**: Demo Readiness
- **Page**: `routes/dashboard.settings.tsx`
- **Description**: Storage key is `safecampus_settings`, leaking a previous product name.
- **Why it matters**: A reviewer opening DevTools sees the legacy name. Minor but unprofessional.
- **Recommended solution**: Rename to `silappkasal.settings.v1` with a one-time migration that hydrates from the old key if present.
- **Effort**: S

#### L-02 — Hide or finalize `"High-contrast tables"` switch

- **Category**: Demo Readiness
- **Page**: `routes/dashboard.settings.tsx`
- **Description**: The switch is rendered with `defaultChecked` and no handler — it doesn't change anything.
- **Why it matters**: Demo viewers will flip the switch.
- **Recommended solution**: Either wire it to a real `text-base` density token (would need design pass) or remove until implemented.
- **Effort**: S

#### L-03 — Audit and trim unused `dashboard.content.tsx`

- **Category**: Information Architecture
- **Page**: `routes/dashboard.content.tsx`
- **Description**: Route exists and resolves to `AccessDenied`. No navigation entry points there in the sidebar.
- **Why it matters**: Dead routes confuse maintenance and may surface accidentally via deep-link logs.
- **Recommended solution**: Remove the route file entirely if there is no business intent, or replace with a real content surface.
- **Effort**: S

#### L-04 — Standardize `"-"` placeholders to a localized `t("common:notAvailable")` shorthand

- **Category**: Localization, Design Consistency
- **Pages**: Multiple (case detail, reports list, registrations list, master-data tables)
- **Description**: Empty/null values are commonly rendered as the literal character `-`. The locale files already include `common:notAvailable` (used in analytics).
- **Why it matters**: Consistent visual treatment of "no data" reads as deliberate.
- **Recommended solution**: Introduce a small helper `displayOrDash(value)` or replace selected `-` with localized `Tidak tersedia` / `N/A`. Keep `-` only in tight table cells where space is constrained.
- **Effort**: S

#### L-05 — Add a `nav` accessibility landmark and skip-to-content link

- **Category**: Accessibility
- **Pages**: `layouts/dashboard-layout.tsx`, `layouts/portal-layout.tsx`
- **Description**: The portal nav uses `<nav>` element (good). The dashboard sidebar uses shadcn primitives that already render the right roles. A top-of-page "skip to content" link is not present.
- **Why it matters**: For government accessibility checklists (WCAG 2.1 AA, Permenpan), skip links are a near-mandatory affordance.
- **Recommended solution**: Add a visually-hidden `<a href="#main-content">{t("common:skipToContent")}</a>` at the top of both layouts, made visible on focus. Mark `<main>` with `id="main-content"`.
- **Effort**: S

#### L-06 — Verify keyboard escape order in workflow `AlertDialog` confirmations

- **Category**: Accessibility
- **Pages**: `routes/dashboard.users.tsx`, `routes/dashboard.registrations.$id.tsx`, `routes/dashboard.master-data.universities.tsx`
- **Description**: shadcn's AlertDialog provides correct focus management; manual verification with a keyboard only is recommended before demo per QA residual risk notes.
- **Why it matters**: Keyboard-only navigation is a common executive-demo flex.
- **Recommended solution**: Walk through deactivate-user, reject-registration, deactivate-university with Tab/Shift+Tab/Enter/Escape only. Document any deviations.
- **Effort**: S

#### L-07 — Lower visual weight of the `"All"` filter Select trigger when no filter is applied

- **Category**: Design Consistency
- **Pages**: `routes/dashboard.reports.index.tsx`, `routes/dashboard.cases.index.tsx`, `routes/dashboard.registrations.tsx`
- **Description**: The status/type filter triggers always render with full visual weight even when value is `all`. A user can't quickly tell at a glance whether a filter is active.
- **Why it matters**: Filter state perception speeds up admin work.
- **Recommended solution**: When value === `all`, render the trigger with `variant="outline"` styling. When a filter is active, render with `bg-primary/10 text-primary` accent so the active filter visually pops.
- **Effort**: S

#### L-08 — Quiet the redundant `"Cari ..."` placeholders by tightening helper copy

- **Category**: Localization, Design Consistency
- **Pages**: `routes/dashboard.reports.index.tsx`, `routes/dashboard.cases.index.tsx`, `routes/dashboard.master-data.*.tsx`
- **Description**: Search placeholders are present and translated, but they vary in tone across pages.
- **Why it matters**: Small voice-and-tone inconsistencies add up.
- **Recommended solution**: Define a single tone rule (e.g. `Cari <noun>...`) and unify placeholder copy across list pages. Pure i18n edit.
- **Effort**: S

#### L-09 — Confirm color-blind distinguishability of workflow chart palette

- **Category**: Accessibility, Dashboard Experience
- **Pages**: `routes/dashboard.index.tsx`, `routes/dashboard.analytics.tsx`
- **Description**: Charts use `--chart-1..5`. Visual review and grayscale simulation should confirm differentiation. Patterns/legends already help.
- **Why it matters**: Government accessibility expectations cover chart legibility.
- **Recommended solution**: Run a grayscale / deuteranopia simulation. If two slices collapse visually, adjust one of `--chart-1..5` token's chroma/hue.
- **Effort**: S

#### L-10 — Replace `formatGenericLabel` calls with a documented "unknown enum" fallback

- **Category**: Localization
- **Pages**: `routes/dashboard.index.tsx` (`formatGenericLabel(item.key)`)
- **Description**: The overview's category distribution chart still labels via `formatGenericLabel(item.key)` rather than `formatReportCategory(t, item.key)`. Inconsistent with the analytics page (UX-05 hotfix) which uses typed formatters.
- **Why it matters**: Mixed formatter strategies risk leaking raw backend codes in unfamiliar locales.
- **Recommended solution**: Use `formatReportCategory(t, analyticsLabelKey(item.key))` in the overview chart for parity with `dashboard.analytics.tsx`.
- **Effort**: S

---

## Category-by-Category Notes

### 1. Information Architecture

- Sidebar role-aware filtering (`super_admin` / `admin` / `satgas_ppks`) is clean and the menu hierarchy matches the operational mental model (Overview → Reports → Cases → Workflow → Analytics → Registrations → Users → Master Data → Break-glass → Settings).
- Portal nav is flat and appropriate for the five reporter-facing pages.
- **Gaps**: no breadcrumbs on list pages (H-06), no top-bar scope/campus indicator (M-04), `/` redirects to `/login` with no landing context, no global search (acknowledged as future work).

### 2. Design Consistency

- Spacing rhythm (`space-y-6`, `p-4 md:p-6`, `gap-4`) is consistent across pages.
- Typography ladder (H1 `text-2xl font-semibold tracking-tight`, subtitle `text-sm text-muted-foreground`) is held.
- Cards/dialogs/page headers are uniform.
- **Gap**: Case detail and workflow rows use neutral outline Badges instead of the multi-channel `StatusBadge` (C-04, M-05), and the workflow stage progress bars need a denominator caption (H-07).

### 3. Localization

- Default `id`, optional `en`, namespaces correct, `format-labels.ts` is the single boundary for enum-to-label.
- Portal status badge and report-type options are localized.
- **Gaps**: Settings page (C-02), Break-glass page (C-03), `dashboard:brand.prototype` literal (C-01), encoded em-dashes in titles (H-02), occasional raw `-` placeholders (L-04).

### 4. Accessibility

- Status badges are multi-channel.
- Avatar menus carry `aria-label`.
- Contrast verified at AA for `--success` and `--info`.
- **Gaps**: skip-to-content link missing (L-05), color-blind chart audit pending (L-09), manual keyboard walkthrough of destructive dialogs recommended (L-06).

### 5. Mobile Experience

- Reports/cases lists have proper mobile card layouts.
- Other admin tables wrapped in `overflow-x-auto`.
- Portal nav labels always visible.
- Toast position `top-center`.
- **No critical gaps** beyond the dialog-density issue typical of any admin product on a phone.

### 6. Dashboard Experience

- Overview, Analytics, Workflow all use real `recharts` visualizations against semantic chart tokens, with skeleton loaders and proper empty states.
- **Gaps**: Workflow progress denominator (H-07), generic outline badges on case/workflow rows (C-04, M-05), portal overview is thin (H-01).

### 7. User Journey

- **Reporter**: registration → login → wizard → tracking flow is coherent and trauma-sensitive. The portal landing is the weakest step (H-01).
- **Admin**: registrations review with reject-confirm-dialog → users management with reset-password gating by email-confirm → reports/cases lists with filters. Strong.
- **Satgas**: case detail with tabbed workflow, lead-investigator-aware actions, `DisabledWorkflowAction` reasons. Strong; visual flatness from generic badges is the main concern (C-04).
- **Super Admin**: analytics + workflow + master-data + break-glass + settings. Break-glass and Settings are the unfinished surfaces (C-02, C-03).

### 8. Demo Readiness

- Most of the product is demo-ready.
- The single biggest wins are removing the literal "prototype" copy (C-01), localizing Settings and Break-glass (C-02, C-03), and switching case detail to multi-channel badges (C-04). With these four, the product reads as institutional-grade in the demo session.

---

## Suggested Demo-Day Priority Ordering

If only a half-day of polish is available before the executive demo:

1. **C-01** Remove "prototype" labels (15 min)
2. **H-02** Fix encoded em-dashes (15 min)
3. **C-03** Localize Break-glass page (1–2 h)
4. **C-02** Localize Settings page and remove `(UI-only)` literal (1–2 h)
5. **C-04** Adopt `StatusBadge` on case detail header (30 min)
6. **H-07** Add relative-bar caption on workflow pipeline (10 min)
7. **H-03** Swap generic shield for `/Logo.ico` in sidebar/portal (10 min)
8. **H-05** Skeleton for case detail initial load (30 min)

These eight items, all S/M effort, would move the demo score visibly into the 90s without touching backend, RBAC, routing, or any architectural surface — strictly within the UX-only scope of this review.

---

# Design System Consistency Audit

> Added as a follow-up section in the same UX-09 review task, after the holistic review identified that several findings actually trace to design-system drift rather than per-page bugs.
> Scope: read-only audit of `frontend/src/components/ui/*`, `frontend/src/components/*`, `frontend/src/layouts/*`, `frontend/src/routes/*`, `frontend/src/styles.css`, `frontend/src/lib/format*.ts`.
> No backend inspection. No source files modified.
> Severity scale: **Critical** (blocks demo polish), **High** (visible inconsistency), **Medium** (drift accumulates), **Low** (housekeeping).
> Effort scale: **S** ≤ 0.5d, **M** 0.5–2d, **L** 2–5d.

The shadcn baseline is intact and the UX-01..UX-08 work added a strong set of conventions (semantic tokens, multi-channel `StatusBadge`, `EmptyState`, `Skeleton`, `DatePicker`/`TimePicker`, `applyLaravelErrors`). The remaining drift is mostly **adoption drift**: local re-implementations that duplicate existing primitives, ad-hoc Tailwind palette use that bypasses semantic tokens, and minor typography/spacing mismatches between near-identical pages.

---

## 1. Icon Consistency

**Library**: `lucide-react` only (verified). No emoji. No mixed icon libraries. `Button` already enforces `[&_svg]:size-4 [&_svg]:shrink-0` via `buttonVariants`, so any `<Icon className="h-4 w-4" />` inside a button is redundant but not harmful. Stroke width is the Lucide default (2px) everywhere; no custom `strokeWidth` overrides found, which is correct.

### DSC-ICN-01 — Inconsistent icon sizing across visually similar containers

- **Severity**: Medium
- **Affected**: `routes/dashboard.index.tsx` (StatCard `h-5 w-5` in `h-10 w-10` tone wrapper), `routes/dashboard.analytics.tsx` (StatCard has *no* tone-icon at all), `layouts/dashboard-layout.tsx` (sidebar brand `ShieldCheck` `h-5 w-5` in `h-9 w-9` wrapper), `routes/dashboard.workflow.tsx` (step glyph `h-5 w-5` in `h-10 w-10`), `routes/portal.notifications.tsx` (empty-state `Inbox` `h-8 w-8`), `components/empty-state.tsx` (`h-6 w-6` in `h-12 w-12`), `components/portal/portal-status-badge.tsx` (icon `h-3 w-3`), `components/status-badge.tsx` (icon `h-3 w-3`).
- **Description**: The product has at least seven icon-size buckets in active use: `h-3 w-3`, `h-4 w-4`, `h-5 w-5`, `h-6 w-6`, `h-8 w-8`, `h-9 w-9`, `h-10 w-10`. Some of those are legitimate (badge glyph vs page glyph), but the relationship between **icon size** and **wrapper size** is not codified, so `h-9 w-9` brand wrapper holds a `h-5 w-5` icon while `h-12 w-12` empty-state wrapper holds a `h-6 w-6` icon — different inset ratios, visually unequal.
- **Recommended solution**: Define an icon-token table in a small `components/ui/icon.tsx` (or document it inside `UX_IMPROVEMENT_PLAN.md`): `badge` = `h-3 w-3`, `inline` = `h-4 w-4`, `pageGlyph` = `h-5 w-5` (always inside `h-10 w-10` tone wrapper), `emptyState` = `h-6 w-6` (always inside `h-12 w-12` wrapper). Audit and update call sites. Pure className change, no API impact.
- **Effort**: S

### DSC-ICN-02 — Redundant `mr-2 h-4 w-4` inside Buttons that already enforce `[&_svg]:size-4`

- **Severity**: Low
- **Affected**: Virtually every `<Button>` with a leading icon: workflow action dialogs, `routes/dashboard.cases.$id.tsx` (`UserRoundSearch`, `ArrowLeft`), `layouts/dashboard-layout.tsx` topbar, `routes/dashboard.reports.index.tsx` `FileText`, and more.
- **Description**: `buttonVariants` sets `gap-2` and `[&_svg]:size-4`, so `<Icon className="mr-2 h-4 w-4" />` is **double-spaced** (a `gap-2` plus an `mr-2` margin) and **double-sized** (`h-4 w-4` then overridden to `size-4`). Visible on careful inspection as extra spacing between icon and label.
- **Recommended solution**: Standardize to `<Icon />` (no className) inside any Button. Optionally introduce a tiny `<ButtonIcon icon={X} />` helper to make the convention obvious. This drift was introduced by porting code from older shadcn versions where `gap-2` was not in `buttonVariants`.
- **Effort**: S

### DSC-ICN-03 — Semantic icon-to-meaning drift

- **Severity**: Medium
- **Affected**: `routes/dashboard.cases.$id.tsx` uses `History` for "Update case status"; `EvidenceStatusAction` also uses `History` in `workflow-action-dialogs.tsx` for "update evidence status". `FilePlus2` is used both for "add activity" and "add monitoring". `PenLine` is used for "edit recommendation" while `ClipboardEdit` is used for "edit decision". Two different verbs for the same action ("edit").
- **Description**: Icons should be a vocabulary. The same verb should map to the same glyph. Mixed verbs for "edit" / "history" / "add" are minor signals that the product was assembled by multiple hands.
- **Recommended solution**: Codify a small icon-vocabulary table in `docs/UX_IMPROVEMENT_PLAN.md`: `edit` → `PenLine`, `delete` → `Trash2`, `add` → `Plus`, `status transition` → `ArrowRightCircle`, `history` → `History` (reserve for *viewing* history only). Refactor in a single PR.
- **Effort**: S

### DSC-ICN-04 — Icon-only buttons missing standard tooltip

- **Severity**: Low
- **Affected**: `layouts/dashboard-layout.tsx` (theme toggle), `layouts/portal-layout.tsx` (theme toggle), `components/language-switcher.tsx`.
- **Description**: They have `aria-label` (good, UX-07 addressed this), but no visible `Tooltip`. Other Lucide-icon-only triggers in the product (e.g. sidebar collapse) get one for free via shadcn. The pure-icon controls in the topbar don't.
- **Recommended solution**: Wrap each icon-only `Button` in `Tooltip` with the same string as `aria-label`. Hover delay 200ms default.
- **Effort**: S

---

## 2. Typography Consistency

Global ladder is largely consistent. Issues are localized.

### DSC-TYP-01 — Page H1 ladder diverges in detail headers

- **Severity**: Medium
- **Affected**: `routes/dashboard.cases.$id.tsx` renders `<h1 className="font-mono text-lg font-semibold">{c.case_number}</h1>` — a monospace, `text-lg`, no tracking-tight; vs the rest of the app's H1 `text-2xl font-semibold tracking-tight`. `routes/login.tsx` left-rail uses `text-3xl font-semibold leading-tight` while the right-rail uses `text-2xl font-semibold tracking-tight`.
- **Description**: A detail page's H1 should be the case identifier *plus* a localized "Detail kasus" label, both at the standard H1 size. The current case header reads like a chip, not a heading.
- **Recommended solution**: Standardize: H1 = `text-2xl font-semibold tracking-tight`, with monospace case numbers rendered as inline `font-mono` spans within the H1 text. Update `dashboard.cases.$id.tsx`, `dashboard.registrations.$id.tsx`, and `dashboard.reports.$id.tsx` to match.
- **Effort**: S

### DSC-TYP-02 — Section title size drift between `CardTitle` consumers

- **Severity**: Low
- **Affected**: `routes/dashboard.cases.$id.tsx` uses `<CardTitle className="text-base">` everywhere (forces a smaller size); `routes/dashboard.analytics.tsx` and `routes/dashboard.index.tsx` use bare `<CardTitle>`. The explicit `text-base` overrides accumulate as inconsistencies if shadcn defaults ever change.
- **Description**: All sections should follow the same source-of-truth size.
- **Recommended solution**: Drop explicit `text-base` overrides on `CardTitle` once verified the default size matches. Encode the rule "section titles use bare `<CardTitle>`" in the design contract.
- **Effort**: S

### DSC-TYP-03 — Stat numeric size inconsistent across StatCards

- **Severity**: Medium
- **Affected**: `routes/dashboard.index.tsx` StatCard uses `text-3xl font-semibold tracking-tight`; `routes/dashboard.analytics.tsx` StatCard uses `text-3xl font-semibold` (no tracking-tight). Both pages live in the same admin context.
- **Description**: A demo session that flips between Overview and Analytics will show slight stat-number visual drift.
- **Recommended solution**: Extract a single shared `StatCard` to `components/ui/stat-card.tsx` and use it from both pages. Eliminates the local duplication entirely.
- **Effort**: M

### DSC-TYP-04 — Helper text classes drift

- **Severity**: Low
- **Affected**: `routes/portal.notifications.tsx` uses `mt-1 text-xs text-muted-foreground/80` (note the `/80` opacity) for the advisory; the rest of the product uses `text-xs text-muted-foreground` (no opacity tweak). `routes/dashboard.reports.index.tsx` `MobileField` uses `text-[11px] uppercase text-muted-foreground` (arbitrary value); `routes/dashboard.cases.$id.tsx` `Field` uses `text-xs uppercase tracking-wide text-muted-foreground` (different specs). Both are read-only label cousins.
- **Description**: Three different "small label" styles exist for the same semantic role.
- **Recommended solution**: Pick one: `text-xs uppercase tracking-wide text-muted-foreground`. Replace all three local helpers (`MobileField`, `Field`, ad-hoc `<div className="text-[11px]">`) with a single shared `<ReadOnlyLabel>` or `<MetaLabel>` component.
- **Effort**: S

### DSC-TYP-05 — Badge text weight assumption

- **Severity**: Low
- **Affected**: `components/ui/badge.tsx` (`text-xs font-semibold`), `components/status-badge.tsx` (`font-medium capitalize` overrides to `font-medium`), `components/portal/portal-status-badge.tsx` (similar override).
- **Description**: The shadcn badge default is `font-semibold`, but the project's preferred status badges use `font-medium`. Workflow `Badge variant="outline"` instances in case detail therefore look heavier than the canonical status badges.
- **Recommended solution**: Either change the base `badgeVariants` to `font-medium` (would affect every badge across the app, do under feature flag), or always wrap statuses in `StatusBadge`/`PortalStatusBadge` (already recommended in C-04/M-05 of the main review).
- **Effort**: S

---

## 3. Dialog Consistency (Dialog / AlertDialog / Sheet / Popover)

All four are vendored from shadcn with sensible defaults. The drift is at the **title and header spacing** level.

### DSC-DLG-01 — Title typography differs between Dialog, AlertDialog, and Sheet

- **Severity**: Medium
- **Affected**: `components/ui/dialog.tsx` (`DialogTitle`: `text-lg font-semibold leading-none tracking-tight`); `components/ui/alert-dialog.tsx` (`AlertDialogTitle`: `text-lg font-semibold` — no leading-none, no tracking-tight); `components/ui/sheet.tsx` (`SheetTitle`: `text-lg font-semibold text-foreground` — explicit foreground, no tracking-tight).
- **Description**: Open a regular Dialog (e.g. workflow action), then open an AlertDialog (e.g. deactivate user), the title visually shifts. The `leading-none tracking-tight` on `DialogTitle` makes its baseline slightly tighter.
- **Recommended solution**: Standardize all three title primitives on `text-lg font-semibold leading-none tracking-tight`. One-line change per file.
- **Effort**: S

### DSC-DLG-02 — Header spacing differs between Dialog and AlertDialog

- **Severity**: Low
- **Affected**: `DialogHeader` uses `space-y-1.5`; `AlertDialogHeader` and `SheetHeader` use `space-y-2`. The vertical gap between title and description is therefore not consistent.
- **Description**: Subtle but observable side-by-side.
- **Recommended solution**: Pick one (`space-y-2` matches the rest of the form rhythm at `space-y-4` halved).
- **Effort**: S

### DSC-DLG-03 — Destructive AlertDialog confirm button bypasses the button variant system

- **Severity**: Medium
- **Affected**: `routes/dashboard.users.tsx` (deactivate, reset password), `routes/dashboard.master-data.universities.tsx` (deactivate), `routes/dashboard.registrations.$id.tsx` (reject).
- **Description**: The destructive action button is rendered as `<AlertDialogAction className="bg-destructive text-destructive-foreground hover:bg-destructive/90">`. `AlertDialogAction` uses `buttonVariants()` (default = primary) under the hood, so the destructive className is layered on top, producing duplicate background classes. A `variant="destructive"` is what `buttonVariants` already exposes.
- **Recommended solution**: Either (a) update `AlertDialogAction` to accept a `variant` prop and pass it to `buttonVariants`, or (b) introduce a shared `<DestructiveAlertAction>` that wraps `AlertDialogAction` with `className={buttonVariants({ variant: "destructive" })}`. Removes the duplicated inline classes across pages.
- **Effort**: M

### DSC-DLG-04 — Long dialogs apply `max-h-[90vh] overflow-y-auto` only sometimes

- **Severity**: Medium
- **Affected**: `workflow-action-dialogs.tsx` applies it on `EvidenceMetadataAction`, `RecommendationUpdateAction`, `DecisionUpdateAction`, `RecoveryMonitoringAction`, `InvestigationActivityAction`; `investigation-create-action.tsx` applies it; but `CaseStatusAction` and some smaller dialogs do not. The "applied when long" rule is currently judgement-based per dialog.
- **Description**: A dialog that grows after locale switch to Bahasa (slightly longer copy) or with longer placeholder hints can clip on short viewports if the rule was missed.
- **Recommended solution**: Apply `className="max-h-[90vh] overflow-y-auto"` to `DialogContent` itself (default in the primitive), or add a `<ScrollableDialogContent>` wrapper used by all workflow forms. Single source of truth.
- **Effort**: S

### DSC-DLG-05 — Popover/Tooltip default padding mismatches Dialog padding language

- **Severity**: Low
- **Affected**: `components/ui/popover.tsx` (`p-4`), `components/ui/dialog.tsx` (`p-6`).
- **Description**: Not a bug, but Popovers used as date/time selectors look slightly tighter than dialogs. With shared `DatePicker` opening a Popover whose calendar then has its own internal padding, the optical density flips between dialog and popover. Mostly noticeable at 200% zoom.
- **Recommended solution**: Acceptable as-is. Document the rationale in the design contract if not already.
- **Effort**: S

### DSC-DLG-06 — Sheet is shipped but apparently unused

- **Severity**: Low
- **Affected**: `components/ui/sheet.tsx` exists; no consumer found in the routes inspected.
- **Description**: Either (a) Sheet is reserved for future mobile patterns (e.g. portal nav drawer), in which case fine, or (b) it should be removed from the bundle.
- **Recommended solution**: Confirm with Project Owner. If reserved, write a one-line note in the design contract. If dead code, remove.
- **Effort**: S

---

## 4. Empty State Consistency

The shared `<EmptyState>` exists (UX-08) but is not universally adopted.

### DSC-EMP-01 — Local empty-state re-implementations bypass the shared component

- **Severity**: High
- **Affected**: `routes/portal.notifications.tsx` (renders its own `Card` + `Inbox` icon empty state); `routes/dashboard.cases.$id.tsx` (`EmptyText` local component used by `SectionCard`); `routes/dashboard.workflow.tsx` (`rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground`); `routes/dashboard.analytics.tsx` (`EmptyChart` local).
- **Description**: Four different "no data" presentations exist in the same product. UX-08 introduced `EmptyState` and applied it to admin list pages, but detail/section/chart consumers still hand-roll their own.
- **Recommended solution**: Extract two more variants of `EmptyState`: `EmptyState.Inline` (no border, smaller, for inline section empty states such as "no investigations yet") and `EmptyState.Chart` (centered text only, for chart `<div>` slots). Reuse the same prop API. Then refactor each call site.
- **Effort**: M

### DSC-EMP-02 — Filtered vs truly-empty differentiation is not enforced everywhere

- **Severity**: Medium
- **Affected**: `routes/dashboard.reports.index.tsx` and `routes/dashboard.cases.index.tsx` correctly distinguish (`SearchX` vs `Inbox`); `routes/dashboard.workflow.tsx` and `routes/dashboard.cases.$id.tsx` do not.
- **Description**: A super admin who has filtered a workflow scope down to zero results sees the same "no data" message as a brand-new instance with truly no data. UX-08 acceptance required both flavors.
- **Recommended solution**: When the parent has filter state, the EmptyState should branch on filter activity. Add a `isFiltered` prop or two presets to `<EmptyState>`.
- **Effort**: S

### DSC-EMP-03 — Optional `action` prop on `EmptyState` is documented but rarely used

- **Severity**: Low
- **Affected**: All admin list pages.
- **Description**: Empty states are a high-leverage place to drop a CTA ("Buat reporter pertama", "Tambah universitas"). Currently the `action` slot is empty across the app.
- **Recommended solution**: For each list, define the single most useful next action and pass it as `action`. Pure copy + Button work.
- **Effort**: S

---

## 5. Skeleton Consistency

Skeleton primitive is single-source (`bg-primary/10`). Adoption is uneven.

### DSC-SKL-01 — Plain text "Loading..." still appears in three demo-path surfaces

- **Severity**: High
- **Affected**: `routes/dashboard.cases.$id.tsx` (initial), `routes/dashboard.break-glass.tsx` (pending + history), `routes/dashboard.reports.index.tsx` (initial query while filters resolve).
- **Description**: UX-08 standardized on Skeletons. These three remain as plain `<div>Loading...</div>` which produces a layout shift when the real content lands. Duplicates main-review H-05, M-08, C-03; restated here to anchor the design-system rule.
- **Recommended solution**: Each surface gets a `Skeleton`-based placeholder that mirrors final layout geometry. Codify the rule: "no plain Loading text inside cards once the page has converged onto skeletons".
- **Effort**: M (aggregated)

### DSC-SKL-02 — Skeleton shapes vary between similar lists

- **Severity**: Medium
- **Affected**: `routes/dashboard.workflow.tsx` (5-column step skeleton); `routes/portal.notifications.tsx` (4 rows of card-with-avatar); `routes/dashboard.master-data.universities.tsx` (table-row skeletons); admin lists like `dashboard.registrations.tsx` (per UX-08 verified).
- **Description**: Each skeleton is locally hand-shaped. There is no shared `<TableSkeleton rows={5} cols={5} />` or `<CardListSkeleton count={3} />` helper.
- **Recommended solution**: Extract `<TableSkeleton />`, `<CardListSkeleton />`, `<StatGridSkeleton />` (already exists as `StatSkeletonGrid`), `<ChartSkeleton />`. Each accepts a `count`/`rows`/`cols` prop. Replaces ad-hoc `Array.from({ length: 4 }).map(...)` patterns.
- **Effort**: M

### DSC-SKL-03 — Skeleton color saturates more than neutral pages

- **Severity**: Low
- **Affected**: `components/ui/skeleton.tsx` uses `bg-primary/10`. With the project's primary being a deep navy-ish OKLCH(0.32 0.09 256), 10% tint is visible on a `bg-muted/30` page background.
- **Description**: Most design systems use `bg-muted` or `bg-foreground/5` for skeletons (neutral). The current choice tints skeletons slightly blue, which can read as "active" rather than "loading".
- **Recommended solution**: Change to `bg-muted` (or `bg-foreground/10`) for a neutral pulse. Verify dark mode.
- **Effort**: S

---

## 6. Spacing Consistency

Page padding and section rhythm hold. Card-level rhythm diverges.

### DSC-SPC-01 — Card padding is one of `p-3`, `p-4`, `p-5`, `p-6` depending on the page

- **Severity**: Medium
- **Affected**: `routes/dashboard.reports.index.tsx` (filter card `p-4`); `routes/dashboard.users.tsx` (filter card `p-4`); `routes/dashboard.index.tsx` (StatCard `p-5`); `routes/dashboard.analytics.tsx` (StatCard `p-5`); workflow step list items `p-4`; `Field`/`EmptyText`/local containers a mix of `p-3` and `p-4`; dialogs `p-6` via primitive.
- **Description**: Frozen Decision §6.1 in `UX_IMPROVEMENT_PLAN.md` says `p-4` compact, `p-5` stats, `p-6` content-heavy. The rule is held in spirit but the boundary is judgement-based per author.
- **Recommended solution**: Codify per-shadcn-component default padding in a small `card-presets.ts` or via composition: `<Card.Compact>`, `<Card.Stat>`, `<Card.Content>`. Pick once.
- **Effort**: M

### DSC-SPC-02 — Filter card grid breaks awkwardly on tablet

- **Severity**: Medium
- **Affected**: `routes/dashboard.users.tsx` (`grid gap-3 p-4 md:grid-cols-5`) jumps from 1-col under `md` to 5-col at and above `md`. There is no 2- or 3-col intermediate.
- **Description**: On 768–1023px tablets, five inputs in one row are cramped; on phones, they stack 1-per-row. The discontinuity hurts the tablet experience.
- **Recommended solution**: Use `grid-cols-1 sm:grid-cols-2 lg:grid-cols-5` for filter rows. Same edit applied across `dashboard.users.tsx`, `dashboard.registrations.tsx` filter cards.
- **Effort**: S

### DSC-SPC-03 — Form `space-y-4` vs `gap-3` mismatch

- **Severity**: Low
- **Affected**: Most forms use `space-y-4` (per Frozen Decision §6.1). `CreateReporterCard` in `routes/dashboard.users.tsx` uses `grid gap-3 md:grid-cols-2` for its form, which is correct for a grid form but mixes rhythm with neighboring `space-y-4` forms.
- **Description**: Grid forms naturally use `gap`, stacked forms use `space-y`. Acceptable; document the rule.
- **Recommended solution**: No code change. Add a note to the design contract: "stacked forms = `space-y-4`; grid forms = `gap-4` (not `gap-3`)". Then bump CreateReporter to `gap-4` for consistency with grid gap rule.
- **Effort**: S

### DSC-SPC-04 — Tabs list in case detail wraps on mobile without visual hierarchy

- **Severity**: Medium
- **Affected**: `routes/dashboard.cases.$id.tsx` `<TabsList className="w-full flex-wrap justify-start">`.
- **Description**: Five tabs wrap to two rows on narrow screens, with the second row left-aligned and unstyled. Looks like an overflow accident, not a deliberate row break.
- **Recommended solution**: Either (a) make the tab list horizontally scrollable with `overflow-x-auto whitespace-nowrap` (preserves single-row), or (b) on `<sm` switch to a `Select`-based navigation. The scroll variant is cheaper and more institutional.
- **Effort**: S

---

## 7. Component Reuse

Local duplications are the largest remaining design-system debt.

### DSC-CMP-01 — `PageHeader` re-implemented in at least three routes

- **Severity**: High
- **Affected**: `routes/dashboard.analytics.tsx`, `routes/dashboard.workflow.tsx`, plus inline pattern in `routes/dashboard.index.tsx`, `routes/portal.index.tsx`, `routes/dashboard.users.tsx`, `routes/dashboard.cases.$id.tsx`.
- **Description**: Every page begins with `<div><h1 className="text-2xl font-semibold tracking-tight">{title}</h1><p className="text-sm text-muted-foreground">{subtitle}</p></div>`. Two routes have extracted a local `PageHeader` for this; the rest inline it. Same code, six copies.
- **Recommended solution**: Promote `<PageHeader title description actions />` to `components/page-header.tsx` and adopt across the app. Encodes the H1 ladder (DSC-TYP-01) in one place and gives an `actions` slot for the right-aligned button (per `dashboard.users.tsx` pattern). Same component can sit above the breadcrumb work in H-06 of the main review.
- **Effort**: M

### DSC-CMP-02 — Form-field wrappers re-defined inside `workflow-action-dialogs.tsx`

- **Severity**: High
- **Affected**: `components/workflow-actions/workflow-action-dialogs.tsx` defines local `InputField`, `TextareaField`, `DatePickerField`, `SelectField` even though `components/form-fields.tsx` exports `TextInputField`, `SelectFormField`, `PasswordField`. Plus `routes/portal.reports.new.tsx` also defines a local `TextareaField`.
- **Description**: Two parallel sets of form-field wrappers exist with different prop names (`control` vs `form`, `name: FieldPath` vs `name: Path<T>`). Confusing for new contributors, hurts discoverability.
- **Recommended solution**: Consolidate into `components/form-fields.tsx` with one canonical generic shape. Migrate `workflow-action-dialogs.tsx` and the wizard's local `TextareaField` to consume the shared set. This was implicitly part of UX-01 but didn't reach the workflow dialogs.
- **Effort**: M

### DSC-CMP-03 — `StatCard` duplicated across two analytics pages

- **Severity**: Medium
- **Affected**: `routes/dashboard.index.tsx` defines `StatCard(label, value, delta, icon, tone)`. `routes/dashboard.analytics.tsx` defines `StatCard(label, value, description)` (no icon, different shape).
- **Description**: Two prop shapes for the same conceptual component lead to visually different stat cards on two pages that are demoed back-to-back.
- **Recommended solution**: Promote a single `StatCard` to `components/ui/stat-card.tsx` with optional `icon`/`tone`. Both pages adopt it.
- **Effort**: M

### DSC-CMP-04 — Read-only field metaphor (`Field`/`MobileField`/inline labels) duplicated

- **Severity**: Medium
- **Affected**: `routes/dashboard.cases.$id.tsx` defines `Field(label, children)`. `routes/dashboard.reports.index.tsx` defines `MobileField`. `routes/portal.reports.new.tsx` defines `InfoRow`. All produce a small uppercase label + value pair.
- **Description**: Three local components express the same primitive.
- **Recommended solution**: Extract `<MetaField label value>` (or `<KeyValueRow>`) to `components/ui/meta-field.tsx`. Pairs naturally with the `ReadOnlyLabel` from DSC-TYP-04.
- **Effort**: S

### DSC-CMP-05 — `EmptyText` and `EmptyChart` duplicate `EmptyState` semantics

- **Severity**: Medium
- **Affected**: `routes/dashboard.cases.$id.tsx` (`EmptyText`), `routes/dashboard.analytics.tsx` (`EmptyChart`).
- **Description**: See DSC-EMP-01. Folded here to highlight the reuse angle.
- **Recommended solution**: Replace both with `EmptyState` variants (DSC-EMP-01 solution).
- **Effort**: S

---

## 8. Motion Consistency

Motion is sober and consistent thanks to shadcn defaults.

### DSC-MOT-01 — Only one motion language is present and it is the shadcn default

- **Severity**: Low (informational)
- **Affected**: All overlay primitives (`Dialog`, `AlertDialog`, `Sheet`, `Popover`, `DropdownMenu`) use the same `data-[state=open]:animate-in data-[state=closed]:animate-out` with `fade`, `zoom`, and `slide` variants. `Loader2` uses `animate-spin`. No custom motion is introduced anywhere.
- **Description**: This is actually a strength: institutional UI should be calm. No bouncing, no parallax. The risk is that future contributors add `transition-all duration-500` ad-hoc and break the calm.
- **Recommended solution**: Document in design contract: "Only shadcn-default motion. No custom keyframes. `animate-spin` is the only allowed motion class for loading states."
- **Effort**: S

### DSC-MOT-02 — Sheet open/close timings diverge

- **Severity**: Low
- **Affected**: `components/ui/sheet.tsx` (`data-[state=closed]:duration-300 data-[state=open]:duration-500`). Other overlays use shadcn's default ~200ms (`duration-200` on `Dialog`).
- **Description**: A Sheet opening feels noticeably slower than a Dialog opening (2.5×). When the product eventually adopts Sheet for a mobile portal drawer (anticipated by `components/ui/sheet.tsx` being present), the speed feel will mismatch.
- **Recommended solution**: Bring Sheet timings down to `duration-200`/`duration-150`.
- **Effort**: S

### DSC-MOT-03 — No `prefers-reduced-motion` guard

- **Severity**: Medium
- **Affected**: All animations including dialog overlays, `Loader2` spinner.
- **Description**: Government accessibility expectations and WCAG 2.3.3 (Animation from Interactions) recommend honoring `prefers-reduced-motion`. shadcn's `tw-animate-css` does not auto-disable when the user requests reduced motion.
- **Recommended solution**: Add a global CSS guard in `styles.css`:
  ```css
  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
  }
  ```
  Test that the `Loader2` spinner still indicates progress (it will be visible as a static icon; consider replacing with text "Memproses…" when reduced motion is active in a later iteration).
- **Effort**: S

---

## 9. Color Token Consistency

The semantic token system (`primary`, `info`, `success`, `warning`, `destructive`, `muted`) is correctly defined and largely respected. Two ad-hoc bypasses exist.

### DSC-CLR-01 — Raw Tailwind `amber` palette bypasses the `--warning` token

- **Severity**: High
- **Affected**: `routes/portal.reports.new.tsx` (wizard content warning banner: `bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200`); `routes/dashboard.users.tsx` (temporary password card: `border-amber-300 bg-amber-50 dark:bg-amber-950/20`).
- **Description**: Frozen Decision §6.10 in `UX_IMPROVEMENT_PLAN.md` forbids raw Tailwind palette use. These two surfaces are exactly the surfaces where a designer would expect `--warning` to be used. The pages drift from the rest of the product's dark-mode + light-mode parity, and a theme change in `styles.css` will not propagate to them.
- **Recommended solution**: Replace with `bg-warning/15 text-warning-foreground border-warning/30` (matching the Frozen Decision tone map for warning). Verify both light and dark themes carry through.
- **Effort**: S

### DSC-CLR-02 — Hardcoded `bg-black/80` overlay color

- **Severity**: Low
- **Affected**: `components/ui/dialog.tsx`, `components/ui/alert-dialog.tsx`, `components/ui/sheet.tsx` (`bg-black/80`).
- **Description**: Overlays are explicit black; this is shadcn default. In dark mode the overlay is still black, which is correct, but the convention should be acknowledged in the design contract because someone may try to "fix" it to `bg-background/80` (incorrect, breaks contrast).
- **Recommended solution**: Leave as-is. Document the rationale in the design contract.
- **Effort**: S

### DSC-CLR-03 — `chart-1..5` palette not contrast-audited for color blindness

- **Severity**: Medium
- **Affected**: All chart consumers (`dashboard.index.tsx`, `dashboard.analytics.tsx`).
- **Description**: Mentioned in main review L-09. From a design-system perspective, these tokens should be selected with a documented contrast/color-blind matrix. Currently they are visually distinct in normal vision; deuteranopia/protanopia simulation has not been verified.
- **Recommended solution**: Add a one-time accessibility audit step: run the existing token values through a CVD simulator, adjust hues if any two collapse. Document in `styles.css` next to the tokens.
- **Effort**: M

### DSC-CLR-04 — `Badge variant="outline"` defaults to bare foreground color (no semantic tone)

- **Severity**: Medium
- **Affected**: `components/ui/badge.tsx` (`outline: "text-foreground"`). All workflow stage statuses on case detail and workflow page.
- **Description**: The shadcn outline badge has no border tone hint, so every workflow-stage status reads as the same neutral chip until a `className` is layered. This is the root cause of the visual flatness called out in C-04 and M-05 of the main review.
- **Recommended solution**: Either keep `outline` as the neutral chip *and* always pair workflow statuses with a `StatusBadge`-style helper (preferred), or extend `badgeVariants` with `info | success | warning | destructive-soft` semantic variants matching the Frozen Decision tone map.
- **Effort**: M

---

## 10. Responsive Consistency

The responsive baseline from UX-06 holds for high-traffic lists. Drift exists on (a) detail pages, (b) some filter rows, and (c) the case detail tab strip.

### DSC-RSP-01 — Mobile card layout exists for reports/cases but not for users/registrations/master-data

- **Severity**: Medium
- **Affected**: `routes/dashboard.reports.index.tsx` and `routes/dashboard.cases.index.tsx` ship `md:hidden` card layouts. `routes/dashboard.users.tsx`, `routes/dashboard.registrations.tsx`, `routes/dashboard.master-data.universities.tsx`, `routes/dashboard.master-data.faculties.tsx`, `routes/dashboard.master-data.study-programs.tsx` rely on `overflow-x-auto` only.
- **Description**: A Super Admin demoing on mobile (likely scenario for executive read-along) sees inconsistent responsive language. Reports/cases collapse beautifully; users/registrations/master-data force horizontal scroll.
- **Recommended solution**: Apply the same `md:hidden` card layout pattern to at least `dashboard.users.tsx` and `dashboard.registrations.tsx`. Master-data tables may stay as horizontal scroll because they are admin-density screens unlikely to be used on mobile.
- **Effort**: M

### DSC-RSP-02 — Detail pages do not collapse the two-column grid until `lg`

- **Severity**: Low
- **Affected**: `routes/dashboard.cases.$id.tsx` (`grid gap-4 lg:grid-cols-3`), `routes/dashboard.registrations.$id.tsx` (presumed similar).
- **Description**: On tablets between `md` and `lg`, the right-rail (assignments + actions) stacks below the content, which is correct. No issue. Documented for completeness.
- **Recommended solution**: No change. Confirms current breakpoint is right.
- **Effort**: S

### DSC-RSP-03 — Filter card jumps directly from 1-col to 5-col at `md` (no intermediate)

- **Severity**: Medium
- **Affected**: `routes/dashboard.users.tsx` (`grid gap-3 p-4 md:grid-cols-5`).
- **Description**: Same issue as DSC-SPC-02. Folded here for the responsive lens.
- **Recommended solution**: See DSC-SPC-02.
- **Effort**: S

### DSC-RSP-04 — Portal nav can horizontally scroll without affordance indicator

- **Severity**: Low
- **Affected**: `layouts/portal-layout.tsx` (`<nav className="flex min-w-0 items-center gap-1 overflow-x-auto">`).
- **Description**: On narrow viewports the nav scrolls but has no visible scroll affordance (no fade edge, no scrollbar styling). User may not realize there is more.
- **Recommended solution**: Add a `before:` and `after:` linear-gradient mask on the parent wrapper to fade the leading/trailing edges, indicating scroll possibility. Pure CSS, no JS.
- **Effort**: S

### DSC-RSP-05 — Tabs list wrap-vs-scroll behavior on mobile

- **Severity**: Medium
- **Affected**: `routes/dashboard.cases.$id.tsx` (`<TabsList className="w-full flex-wrap justify-start">`).
- **Description**: Same issue as DSC-SPC-04, folded here.
- **Recommended solution**: See DSC-SPC-04.
- **Effort**: S

### DSC-RSP-06 — No documented breakpoint contract

- **Severity**: Low
- **Affected**: All routes.
- **Description**: Tailwind defaults (sm 640, md 768, lg 1024, xl 1280) are used implicitly. Frozen Decision §5.11 mentions them but the per-component breakpoint preference (e.g. tables collapse at `md`, side-rail collapses at `lg`, dialogs respond at `sm`) is not codified.
- **Recommended solution**: Document the rule in `UX_IMPROVEMENT_PLAN.md`: "Tables collapse at `md`. Two-column page grids collapse at `lg`. Form grids inside dialogs collapse at `sm` (already the default for `md:grid-cols-2`)."
- **Effort**: S

---

## Summary of Severities

| Severity | Count | IDs |
|---|---:|---|
| **High** | 5 | DSC-EMP-01, DSC-SKL-01, DSC-CMP-01, DSC-CMP-02, DSC-CLR-01 |
| **Medium** | 17 | DSC-ICN-01, DSC-ICN-03, DSC-TYP-01, DSC-TYP-03, DSC-DLG-01, DSC-DLG-03, DSC-DLG-04, DSC-EMP-02, DSC-SKL-02, DSC-SPC-01, DSC-SPC-02, DSC-SPC-04, DSC-CMP-03, DSC-CMP-04, DSC-CMP-05, DSC-MOT-03, DSC-CLR-03, DSC-CLR-04, DSC-RSP-01, DSC-RSP-03, DSC-RSP-05 |
| **Low** | 14 | DSC-ICN-02, DSC-ICN-04, DSC-TYP-02, DSC-TYP-04, DSC-TYP-05, DSC-DLG-02, DSC-DLG-05, DSC-DLG-06, DSC-EMP-03, DSC-SKL-03, DSC-SPC-03, DSC-MOT-01, DSC-MOT-02, DSC-CLR-02, DSC-RSP-02, DSC-RSP-04, DSC-RSP-06 |

## Suggested Order of Adoption (design-system PRs, not feature work)

1. **DSC-CMP-01** (PageHeader) and **DSC-CMP-03** (StatCard) — unlocks downstream typography consistency wins.
2. **DSC-CMP-02** (form-field consolidation) — unlocks easier consistency review of every new form.
3. **DSC-CLR-01** (raw amber → `--warning`) — high-visibility, low-effort.
4. **DSC-DLG-01 / DSC-DLG-03** (dialog title typography + destructive variant) — visible on every confirmation.
5. **DSC-EMP-01** (EmptyState variants) plus **DSC-SKL-02** (Skeleton helpers) — a Loading/Empty pass that touches every list.
6. **DSC-MOT-03** (`prefers-reduced-motion` guard) — single CSS rule, accessibility compliance.
7. **DSC-RSP-01** (mobile card layout for users + registrations) — finishes the UX-06 story.
8. **DSC-SPC-04 / DSC-RSP-05** (tabs scroll vs wrap) — makes case detail demo-clean on mobile.
9. Remaining Low items as background hygiene.

These eight PRs would resolve the design-system drift identified here while staying strictly within the UX-only scope. No backend, RBAC, or routing impact. Each PR is independently mergeable.

---

> **End of review.** This document was created as the sole output of the UX-09 holistic review task. No existing files were modified.
