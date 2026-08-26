# PRD — SILAPPKASAL Mobile Reporter

> Status: Draft for mockup and product review  
> Audience: Product Owner, UI/UX designer, Flutter developer, backend developer, QA  
> Last updated: 2026-08-26  
> Implementation source of truth: current Laravel routes, policies, Resources, and tests. This document is a mobile design reference; it does not change backend behaviour.

## 1. Product summary

SILAPPKASAL Mobile Reporter is a Flutter application for a Reporter/Pelapor to securely submit and follow up their own complaint. It is the mobile counterpart of the existing Reporter Portal, not an operational application for Admin, Super Admin, or Satgas PPKS.

The application must use the existing Laravel API. The mobile client owns presentation, accessibility, local form state, safe token storage, and network recovery. The backend remains authoritative for identity, permissions, validation, campus scope, complaint state, privacy, and document/file access.

## 2. Goals and success criteria

### Goals

- Let a registered Reporter sign in and safely understand what they can do next.
- Let a Reporter submit a complaint through a calm, step-by-step mobile flow.
- Let a Reporter see only their own complaint data and reporter-safe handling progress.
- Let a Reporter upload, view, preview, or download their permitted supporting files and documents.
- Preserve privacy, informed choice, and a trauma-informed tone in every sensitive interaction.

### Success criteria

- A Reporter can complete a valid complaint submission on a phone without needing desktop UI.
- Every mobile data action maps to an existing authorized API operation; no mobile-only business rule is introduced.
- Detail/progress views never reveal internal investigator notes, staff identities, internal evidence details, decision-review metadata, or data belonging to another Reporter.
- Loading, empty, offline, validation, unauthorized, and retry states are designed for every primary journey.

## 3. Users, roles, and boundaries

| Actor | Mobile access | Boundary |
|---|---|---|
| Reporter | Full app scope described here | Own account and own complaints only |
| Prospective reporter | Public registration and tracking, if included in the release | No access to another person's account or complaint |
| Admin / Super Admin / Satgas | No mobile operational screens | Must use their authorized web experience |

`Anonymous` is a complaint classification, not an unauthenticated role. The Reporter is authenticated, while identity masking rules apply to authorized operational viewers.

## 4. Scope

### In scope: authenticated Reporter app

1. Login, logout, session restoration, and expired-session handling.
2. Reporter dashboard/overview.
3. Create complaint, including optional supporting-file upload.
4. My complaints list, search/filter where the backend contract supports it, and complaint detail.
5. Reporter-safe timeline and handling-progress views.
6. Supporting-file list, add file while allowed, preview, and download.
7. Direct cancellation where backend capability permits it.
8. Formal withdrawal request: reason, draft document, signed-document upload, submit, cancel, and resubmit where permitted.
9. Closure-document view/download when the backend marks it available.
10. Notifications, profile, account status, and change password.
11. Published Information Center: education, policies, FAQ, consultation, and permitted attachments.

### Optional public companion screens

Public registration, pending/rejected-registration correction, and tracking-code lookup exist in the web product. They should be designed as a separate public entry flow if the first mobile release will also support account acquisition and public tracking. They are not required for an already-authenticated Reporter MVP.

### Out of scope

- Admin, Super Admin, Satgas PPKS, case assignment, investigation, recommendation, decision administration, recovery administration, audit, break-glass, user management, and campus master-data management.
- A new backend API, new workflow status, new role, or frontend-controlled authorization rule.
- Offline submission of a final complaint before a separately approved offline-draft design exists. Local unsent drafts may be considered later, but must be encrypted/cleared according to an approved mobile security design.

## 5. Information architecture

Recommended bottom navigation:

| Tab | Purpose | Primary destination |
|---|---|---|
| Beranda | Reporter summary and next action | Overview |
| Laporan Saya | Own complaint list and detail | Complaint list |
| Buat Laporan | Start a protected submission | Three-step wizard |
| Informasi | Published prevention/support information | Information Center |
| Akun | Profile, password, notifications, logout | Account |

Notifications can appear as a badge in the Account tab and as a dedicated destination. A complaint detail must use contextual sections rather than add more global tabs.

## 6. Screen inventory and mockup requirements

| ID | Screen | Required content and primary action |
|---|---|---|
| MR-01 | Splash/session restore | Brand, loading state; silently restore session or navigate to login |
| MR-02 | Login | Email/NIM/NIP identifier, password, remember-session choice, login, registration/tracking links if public companion scope is enabled |
| MR-03 | Overview | Greeting, complaint totals/status summary, unread notification count, primary `Buat Laporan`, recent own complaints, Information Center shortcuts |
| MR-04 | My complaints | Search/filter controls if supported, status-safe complaint cards, empty state, pagination/load-more state |
| MR-05 | Create complaint — step 1 | Complaint type and category; visible three-step progress indicator |
| MR-06 | Create complaint — step 2 | Chronology, incident date, optional/unknown incident time, incident location, optional location type |
| MR-07 | Create complaint — step 3 | Optional respondent context, witness information, confidential-contact phone where required, optional supporting files, review/submit |
| MR-08 | Submission success | Registration number, tracking code when supplied, file-upload result, safe save/copy action, links to detail/list |
| MR-09 | Complaint detail | Registration number, complaint type, category, reporter-safe status, submitted date, safe submitted-data accordion |
| MR-10 | Complaint progress | Safe timeline and handling-progress accordions; never expose internal operational notes or staff identity |
| MR-11 | Supporting files | File list, allowed-slot/status information, upload affordance only when API capability permits, preview/download states |
| MR-12 | Direct cancellation | Consequence explanation, reason input, confirmation, success/error state; only shown when enabled by backend capability |
| MR-13 | Formal withdrawal | Create reason, review DRAFT, complete profile fields needed for document, upload signed document, submit/cancel/resubmit/status/history |
| MR-14 | Closure document | Document number/date, preview/download, unavailable state |
| MR-15 | Notifications | Own notifications, unread filter, empty/loading/error state |
| MR-16 | Account | Read-only identity/campus data, editable permitted profile fields, account status, change password, logout |
| MR-17 | Information Center | Education/policy lists and articles, FAQ, consultation, featured content, authorized attachments |
| MR-18 | Public registration/tracking (optional) | Registration, pending/rejected correction, and tracking-code lookup only if included in release scope |

## 7. Complaint-submission form contract

The mobile wizard must preserve the existing three-step structure and must not make optional information feel mandatory.

### Step 1 — Complaint classification

- `report_type` — required, selected from backend master data.
- `category` — required, selected from backend master data.

### Step 2 — Incident details

- `chronology` — required; current web UX requires 50–10,000 characters.
- `incident_date` — required; cannot be a future date.
- `incident_time` — optional; include an explicit “time unknown” choice.
- `incident_location` — required.
- `location_type` — optional; selected from master data.

### Step 3 — Context and supporting files

- Respondent name, campus status, relationship, and detail are optional as a group; once one is provided, the related required fields must be completed.
- Witness information is optional.
- Reporter phone is required only for the confidential complaint type when the backend requires it.
- Supporting files are optional and a failed file upload must not block final complaint submission. Show progress and a clear partial-upload result.

On success, show the backend-issued registration number and tracking code, if any. The tracking code must be presented as sensitive: copy/save affordance, no unnecessary sharing action, and an explanation to store it safely.

## 8. Reporter-safe complaint detail and progress

The complaint detail may show:

- registration number, complaint type, category, safe status label, and submitted date;
- the Reporter’s submitted information;
- reporter-safe timeline stages;
- handling progress such as high-level investigation/recommendation/decision/recovery progress where the API projects it safely;
- safe final summary and closure document when available;
- own supporting-file state and permitted withdrawal/cancellation actions.

It must not show raw internal status codes as the primary label, operational case notes, identity/contact data of staff or other parties, internal evidence contents/counts beyond an approved reporter-safe projection, decision review details, or audit records.

## 9. File and document UX

- Use private API download/preview endpoints only; never display a public storage URL.
- Show filename, type, size, upload state, failure reason, and allowed action only when supplied/authorized.
- Support progress, retry, cancel-before-upload, validation failure, expired session, preview-unavailable, and network-error states.
- File selection guidance must state allowed type/size/count based on the live API response or approved configuration; do not hard-code a different contract in the mobile UI.
- Formal withdrawal and closure documents are sensitive. Use a confirmation/interstitial before external sharing and avoid caching in app galleries or public folders where platform controls permit.

## 10. Privacy, safety, and accessibility requirements

- Bahasa Indonesia is the default language; English is optional parity with the web application.
- Use calm, plain, non-judgmental Indonesian. Avoid coercive wording and avoid red/destructive visual treatment except for clearly irreversible confirmation.
- Explain optional fields, allow unknown time, and let the Reporter pause navigation without losing already entered data within the active form session.
- Use text + icon + color for status; color alone must not convey state.
- Minimum touch target: 44 × 44 logical pixels. Support screen readers, dynamic text sizing, clear focus order, and meaningful error messages next to inputs.
- Treat all API responses, logs, analytics events, screenshots, local files, and crash reports as potentially sensitive. Never log narrative, identity, tracking code, signed documents, or file contents.

## 11. Backend integration principles

| Area | Existing API family |
|---|---|
| Auth and profile | `/api/v1/auth/*`, `/api/v1/me/*` |
| Complaint submission/tracking | `/api/v1/reports`, `/api/v1/reports/track/{trackingCode}` |
| Reporter portal | `/api/v1/portal/summary`, `/reports`, `/reports/{registrationNumber}`, `/timeline`, `/handling-progress`, `/notifications` |
| Reporter supporting files | `/api/v1/portal/reports/{registrationNumber}/evidence-files`, `/api/v1/portal/evidence-files/{uuid}/*` |
| Cancellation/withdrawal | `/api/v1/portal/reports/{registrationNumber}/cancel`, `/withdrawal*`, `/api/v1/portal/withdrawals/{publicId}/*` |
| Closure documents | `/api/v1/portal/reports/{registrationNumber}/closure-document/*` |
| Published content | authenticated `/api/v1/content/*` |

The mobile implementation must read server-returned capabilities before displaying destructive or state-dependent actions. Feature flags currently control early cancellation and formal-withdrawal entry operations; unavailable actions must be absent or explicitly explained, not simulated.

## 12. Non-functional requirements for the later Flutter build

- Flutter is the planned mobile stack; use a secure platform-backed token store, not plain preferences.
- Centralize authenticated API requests, request IDs/error handling, token expiry, and 401 logout handling.
- Preserve current backend pagination and error shapes; do not infer success from HTTP code alone.
- Use resumable/retry-aware UX for file operations where API support permits; never silently discard a user-selected file.
- Design for Android first while retaining iOS-compatible patterns. Minimum supported OS versions are a later engineering decision.
- No production analytics/telemetry SDK may be added without a separate privacy review.

## 13. Acceptance checklist for mockup review

- [ ] Every screen in the agreed release scope has default, loading, empty, validation-error, API-error, and success states where relevant.
- [ ] Every primary action has a destination, confirmation rule, and safe back/cancel behavior.
- [ ] Complaint submission uses the exact three-step structure and field conditionality described above.
- [ ] Complaint detail/progress is visibly reporter-safe and does not resemble an internal case-management screen.
- [ ] Sensitive file/document actions are private, deliberate, and understandable.
- [ ] The design uses Indonesian default copy and is readable at small mobile widths.
- [ ] No Admin/Satgas/Super Admin feature appears in navigation, mock data, or empty states.

## 14. Design decisions still requiring Product Owner confirmation

1. Is public registration, pending/rejected correction, and tracking included in the first mobile release or kept web-only initially?
2. Is local encrypted draft saving desired for incomplete complaint forms, and what is its retention/erase policy?
3. Which device platforms and minimum OS versions are required for the first release?
4. Must the mobile app support push notifications in release one, or is in-app notification parity sufficient?
5. Should closure/withdrawal documents open only in an in-app viewer, or can users export them through the operating system’s share sheet after an explicit warning?

