# REV-CONTENT-01 C4 Information Center

Status: implemented locally; not pushed or deployed.

## Routes and roles

- `/portal` remains the Reporter landing page and contains four Information Center shortcuts plus
  the published Education spotlight.
- `/portal/information-center` is a directory only. Its four cards open
  `/portal/information-center/education`, `/policies`, `/faq`, and `/consultation`.
- Reporter Article detail uses
  `/portal/information-center/{education|policies}/{slug}`.
- `/dashboard/information-center` gives Admin and Super Admin the same directory flow, with
  dedicated `/education`, `/policies`, `/faq`, and `/consultation` routes and
  section-aware slug detail routes. Management CTAs are shown only when the actor has the relevant
  content permission.
- Reporter, Satgas, Campus Admin, and Super Admin require an active account and
  `content.read.published`. Reporter access to the dashboard shell is limited to the Information
  Center subtree. C4 adds no authoring or governance action.

## Reader behavior

The client consumes only `/api/v1/content/*` reader resources. The backend published pointer,
publication time, archive state, actor campus, and explicit permission decide visibility. Article
and FAQ searches, section/category filters, and pagination remain server-driven. Featured ordering
is the exact server collection order; the client does not re-rank placements or fallback Articles.

Article cards use the published category, section, title, excerpt, publication time, optional computed
reading time, and optional safe cover projection. Their default is a CSS/icon no-image surface. The
application does not use remote stock images or fabricated media. Article detail renders controlled
document JSON and ignores the HTML projection.

Article categories are searchable free text backed by a scoped registry. Version `category_name` is
canonical; version `category_id` is a fallback only for historical rows whose version
`category_name IS NULL`. Campus Admin can create/deactivate only own-campus suggestions, while
Super Admin manages global suggestions. In-use categories cannot be deactivated. Inactive
categories remain attached to existing Article names but are not offered as new suggestions.
When an Article version is written with a canonical name, any submitted or previously stale legacy
category relation on that version is cleared. Admin editors project the editable/latest version;
Information Center readers project only the published pointer version. Changing a draft category is
Article metadata editing, not a section/scope placement change, and cannot affect list, detail,
filter, related, category-directory, or featured reader output before publication. Item-level
category fields are retained only as compatibility/denormalized metadata.

Category identity is normalized by trimming, collapsing repeated whitespace, lowercasing, and NFC
Unicode normalization when available. Duplicate `POST` returns the existing active entry with 200;
an inactive match is reactivated with 200; a newly inserted entry returns 201. Database uniqueness
on section, scope key, and normalized name closes concurrent creation races. The same normalized
name is allowed across Global and Campus or across different campuses; only a duplicate in the same
section and exact scope is existing/reactivated, regardless of creation order.

FAQ answers use the accessible Radix Accordion and controlled document renderer. Consultation cards
show only non-empty published fields. `mailto:`, `tel:`, WhatsApp, and appointment actions are
validated; telephone/WhatsApp actions require confirmation and never prefill sensitive data.

## Attachments

Published PDF attachments are fetched with the authenticated API client. Temporary Blob URLs are
revoked on replacement, close, error, and unmount. Popup blocking triggers the same authenticated
download path. Tokens, storage paths, checksums, protected original filenames, and attachment bytes
are not placed in URLs or browser storage.

## Query privacy

Reader query keys start with `published-content` and include the current account ID. Every reader
request consumes TanStack Query's AbortSignal. Authentication invalidation, logout, and account
replacement cancel and remove reader, management, and governance queries before the next identity can
render. No content response is persisted to localStorage, sessionStorage, or a service-worker cache.

## PWA boundary

C4 adds `manifest.webmanifest`, standalone display metadata, a safe `/login` start URL, and the
existing project-owned icon. It registers no service worker. Consequently Article bodies, FAQ
answers, Consultation records, PDFs, Reports, Cases, and Evidence cannot be exposed from an offline
runtime cache. Full installability icons, service-worker updates, offline UX, and iOS-specific icon
assets require approved project-owned assets and a separate cache-security review.

## Responsive and accessibility behavior

- Article and Consultation layouts are one-column at 320/360 px, expand at tablet/desktop widths, and
  use safe-area bottom padding.
- Featured Articles use a touch-friendly Embla carousel with keyboard-operable 44 px controls and no
  autoplay.
- Full Article cards and shortcuts use semantic links, visible focus, responsive wrapping, and
  reduced-motion-safe transitions.
- Pages use one H1, ordered headings, labelled search/filter controls, live loading/error states, and
  controlled external links.

## C4 reader repair

- Portal navigation, dashboard shortcuts, and featured content are rendered only when the active
  account has `content.read.published`; the route and backend remain independently authoritative.
- Search, filters, pagination, and FAQ expansion create browser-history entries. Back and Forward can
  restore prior reader state. Invalid, duplicate, overlong, or context-incompatible URL state is
  canonicalized with replacement so normalization does not add history noise.
- Filter controls use a bottom Sheet below `lg`; desktop controls wrap and use fluid widths. Section
  and category Select triggers have stable IDs and programmatic labels in both layouts.
- Published PDF controls meet the 44 px touch target. Preview remains single-flight, uses
  authenticated bytes, falls back to authenticated download when popups are blocked, and revokes
  temporary Object URLs.
- Published reader APIs attach `private, no-store` to successful and error responses. Existing and
  unknown unauthorized attachment UUIDs both resolve as 404, preventing an existence oracle.
- Frontend regression tests execute production permission, URL-state, cache-clearing, consultation
  destination, carousel-keyboard, and PDF lifecycle logic directly. Source assertions remain only
  as secondary wiring and markup guards because the repository has no browser DOM test harness.

## Deferred

Service-worker caching, offline private content, public unauthenticated access, image upload,
notification delivery, scheduled publication, comments, reactions, bookmarks, analytics expansion,
Flutter, production deployment, and PostgreSQL runtime verification remain outside C4.

## C5 release verification status

C5 automated verification passes for published-pointer isolation, campus scope, reader permissions,
management/governance boundaries, lifecycle transitions, featured placements, private attachments,
query-cache clearing, URL state, localization parity, TypeScript, lint, and client/SSR production
build. Auth responses are now private/no-store and the executable API CORS allowlist is driven by
`CORS_ALLOWED_ORIGINS`/`FRONTEND_URL`.

This does not mean production release gates are complete. Disposable PostgreSQL verification and
authenticated browser QA at 320, 360, 768, 1024, and desktop widths remain blocked and are documented
in the C5 deployment reports. Image upload remains fail-closed, the PWA remains manifest-only, no
service worker or offline private cache exists, and Graphify remains deferred.
