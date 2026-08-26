# Mobile Reporter — Screen-to-Endpoint Matrix

> Status: Verified from current web client and Laravel route definitions (static verification)  
> Last updated: 2026-08-26  
> Scope: authenticated Reporter navigation shown in the web portal.

## Verification statement

This matrix maps the Reporter screens currently visible in the web portal to their existing API operations. It is intended to give the Flutter application feature parity with the web Reporter experience. It is not a claim that a live login/session test has been executed; runtime QA still needs a disposable/test Reporter account and a non-destructive test checklist.

All listed private operations require the authenticated Reporter session and must retain server-side authorization. The mobile UI must use returned capabilities and safe response projections rather than infer permission from a local status label.

## 1. Header navigation in the supplied web screens

| Web/mobile feature | Current web route | Existing API operation(s) | Mobile requirement |
|---|---|---|---|
| Ringkasan | `/portal` | `GET /api/v1/portal/summary`; `GET /api/v1/portal/reports`; `GET /api/v1/content/featured` | Show own totals, unread count, recent own complaints, published education spotlight, and Information Center shortcuts. |
| Buat Pengaduan | `/portal/reports/new` | `GET /api/v1/master/report-types`, `report-categories`, `campus-statuses`, `location-types`, `relations`; `POST /api/v1/reports` | Preserve the current three-step wizard and server validation. |
| Pengaduan Saya | `/portal/reports` | `GET /api/v1/portal/reports` | List only the authenticated Reporter’s complaints; retain API pagination. |
| Detail Pengaduan | `/portal/reports/{registrationNumber}` | See section 2 | Use only reporter-safe detail/progress data. |
| Pusat Informasi | `/portal/information-center/*` | See section 3 | Reader access to published content only. No authoring/governance functions. |
| Notifikasi | `/portal/notifications` | `GET /api/v1/portal/notifications` | Current Reporter page is a read/list experience with unread filtering; do not invent read/mutation UX without confirmed product scope. |
| Akun | `/portal/account` | `GET/PATCH /api/v1/me/profile`; `GET /api/v1/me/account-status`; `PATCH /api/v1/me/change-password`; `POST /api/v1/auth/logout` | Provide the same self-service boundaries and safe logout. |

## 2. Detail Pengaduan capabilities

| Detail section | Existing API operation(s) | API/UX boundary |
|---|---|---|
| Summary and submitted data | `GET /api/v1/portal/reports/{registrationNumber}` | Own complaint only; includes safe status and submitted-data projection. |
| Safe timeline | `GET /api/v1/portal/reports/{registrationNumber}/timeline` | Reporter-safe stages only. Never replace with internal case status/case timeline APIs. |
| Handling progress | `GET /api/v1/portal/reports/{registrationNumber}/handling-progress` | Use backend’s safe progress projection; do not reveal staff/reviewer details or internal notes. |
| Supporting-file list | `GET /api/v1/portal/reports/{registrationNumber}/evidence-files` | Use returned `upload_allowed`, `max_files`, and `remaining_slots`. |
| Add supporting file | `POST /api/v1/portal/reports/{registrationNumber}/evidence-files` | Multipart file upload. Shown only when server permits; upload failure must not invalidate an existing complaint. |
| Preview/download supporting file | `GET /api/v1/portal/evidence-files/{uuid}/preview` and `/download` | Private streamed access; no public storage URL. |
| Direct cancellation | `POST /api/v1/portal/reports/{registrationNumber}/cancel` | Show only when the complaint’s returned cancellation capability permits it and product feature flag is enabled. |
| Current formal withdrawal | `GET /api/v1/portal/reports/{registrationNumber}/withdrawal` | Current request/status for this Reporter and complaint only. |
| Create formal withdrawal | `POST /api/v1/portal/reports/{registrationNumber}/withdrawals` | Reason-based flow; entry actions are feature-flag controlled. |
| Withdrawal DRAFT | `GET /api/v1/portal/withdrawals/{publicId}/draft-document`, `/draft-document/download`, `/draft-document/example` | Clearly label the material DRAFT; do not call it an official campus template. |
| Signed withdrawal document | `POST /api/v1/portal/withdrawals/{publicId}/signed-document`; `GET /api/v1/portal/withdrawals/{publicId}/signed-document/{attachmentPublicId}` | Private upload/download, version/history-aware UI. |
| Submit/cancel/resubmit withdrawal | `POST /api/v1/portal/withdrawals/{publicId}/submit`, `/cancel`, `/resubmit` | Submit lock version supplied by API; on `409`, refresh safe data rather than overwrite state. |
| Closure document | `GET /api/v1/portal/reports/{registrationNumber}/closure-document/preview` and `/download` | Only available after backend makes it available; keep in-app preview/private download behaviour. |

## 3. Pusat Informasi capabilities

| Information Center feature | Existing API operation(s) |
|---|---|
| Sections and category metadata | `GET /api/v1/content/sections`, `GET /api/v1/content/categories` |
| Education/policy list and search/filter | `GET /api/v1/content/articles` |
| Article detail | `GET /api/v1/content/articles/slug/{section}/{slug}` or `GET /api/v1/content/articles/{publicId}` |
| Article category options | `GET /api/v1/content/article-categories` |
| FAQ | `GET /api/v1/content/faqs` |
| Consultation | `GET /api/v1/content/consultation` |
| Featured education | `GET /api/v1/content/featured` |
| Permitted attachment | `GET /api/v1/content/attachments/{attachment}` |

All content-reader endpoints are authenticated and private/no-store in the current API routes. The mobile app must never expose content management, review, publish, category governance, or featured-placement endpoints.

## 4. Session and optional public flow

| Feature | Existing API operation(s) | Release scope |
|---|---|---|
| Login | `POST /api/v1/auth/login` | Required |
| Session/user bootstrap | `GET /api/v1/auth/me` | Required |
| Logout | `POST /api/v1/auth/logout` | Required |
| Public tracking-code lookup | `GET /api/v1/reports/track/{trackingCode}` | Optional companion flow; not a replacement for Reporter detail access |
| Public registration and correction | `/api/v1/reporter-registrations/*` | Optional companion flow; decide before mobile release scope is frozen |

## 5. Explicit parity decisions

1. The Flutter Reporter application should call the same API endpoints above, not copy the web page’s internal data/state logic.
2. Header-only preferences such as theme and language are client presentation settings; they do not require a Reporter backend endpoint in the current web implementation.
3. The mobile app must not use operational `/cases`, `/investigations`, `/recommendations`, `/decisions`, `/recoveries`, audit, break-glass, or content-management APIs.
4. Before implementation, repeat this static matrix review against the then-current `backend/api/routes/api.php` and API tests, then run non-destructive contract QA using a dedicated test Reporter.

