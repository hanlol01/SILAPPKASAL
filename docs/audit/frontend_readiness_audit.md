# FRONTEND_READINESS_AUDIT.md

> Audited: 2026-06-23  
> Scope: All frontend routes, components, API integrations, localization, and mobile concerns  
> Method: Static code inspection only — no code modifications

---

## 1. Role-by-Role Page Audit

### 1.1 Reporter / Pelapor

| Page | Route | Status | Notes |
|---|---|---|---|
| Login | `/login` | ✅ Complete | Backend-integrated, role-aware redirect to `/portal`, bilingual |
| Portal Overview | `/portal` | ✅ Complete | Summary cards (total/active/completed/unread), bilingual |
| My Reports List | `/portal/reports` | ✅ Complete | Search/filter, registration number display, bilingual |
| Report Detail | `/portal/reports/:registrationNumber` | ✅ Complete | Safe status labels, privacy-safe metadata, bilingual |
| Notifications | `/portal/notifications` | ✅ Complete | Read-only list, bilingual |
| Account / Profile | `/portal/account` | ✅ Complete | Name/phone edit, change password, bilingual |
| Self-Registration | — | 🔴 Not Implemented | Backend API exists (`POST /api/v1/reporter-registrations`), no frontend form |
| Report Submission | — | 🔴 Not Implemented | Backend API exists (`POST /api/v1/reports`), no frontend submission form |
| Tracking Code Lookup | — | 🔴 Not Implemented | Backend API exists (`GET /api/v1/reports/track/:trackingCode`), no frontend page |

**Reporter DisabledWorkflowAction count:** 0  
**Reporter mock data in use:** None (orphaned mock-data file not imported)  
**Reporter missing API integrations:** Self-registration, report submission, tracking code lookup  
**Reporter missing CRUD actions:** Create report, self-register  
**Reporter missing navigation entries:** None for implemented pages; registration/submission routes don't exist yet  
**Reporter mobile responsiveness:** ✅ Portal layout uses responsive horizontal nav with overflow-x-auto, `hidden sm:inline` labels  
**Reporter localization:** ✅ Full bilingual (ID/EN) coverage — all portal pages use `useTranslation`  

---

### 1.2 Satgas PPKS

| Page | Route | Status | Notes |
|---|---|---|---|
| Login | `/login` | ✅ Complete | Redirects to `/dashboard` |
| Dashboard Overview | `/dashboard` | ✅ Complete | Backend summary + charts via React Query |
| Case List | `/dashboard/cases` | ✅ Complete | Backend-integrated with search/filter |
| Case Detail | `/dashboard/cases/:id` | ✅ Complete | Full workflow sections (investigations, recommendations, decisions, recoveries, evidence) |
| Workflow Pipeline | `/dashboard/workflow` | ✅ Complete | Backend workflow analytics |
| Settings | `/dashboard/settings` | 🟡 Partial | localStorage-only, no backend integration; notification preferences are cosmetic toggles |
| Report List | `/dashboard/reports` | 🔴 Not visible | Navigation hidden for Satgas (Reports nav limited to `super_admin`/`admin`) |
| Report Detail | `/dashboard/reports/:id` | 🔴 Not visible | `AccessDenied` for non-admin — correct per RBAC |
| Notifications | `/dashboard/notifications` | 🔴 Not Implemented | Route exists but renders `AccessDenied`; no notification list/read UI |
| Users | `/dashboard/users` | 🔴 Not Implemented | Route exists but renders `AccessDenied` — correct, Satgas has no user management |
| Content | `/dashboard/content` | 🔴 Not Implemented | Route exists but renders `AccessDenied` |
| Analytics | `/dashboard/analytics` | 🔴 Not visible | Navigation hidden for Satgas — correct per RBAC |
| Break-Glass | `/dashboard/break-glass` | 🔴 Not visible | Navigation hidden for Satgas — correct |

**Satgas workflow actions on Case Detail:**

| Action | Status |
|---|---|
| Case status update | ✅ Functional (assigned Satgas only) |
| Create investigation | ✅ Functional (assigned Satgas only) |
| Investigation status transition | ✅ Functional |
| Investigation activity logging | ✅ Functional |
| Create recommendation | ✅ Functional (assigned Satgas only) |
| Recommendation status transition | ✅ Functional |
| Recommendation update | ✅ Functional |
| Decision create/update/transition | ❌ Blocked — correct, Super Admin only |
| Recovery create/update/transition | ❌ Blocked — correct, Admin/Super Admin only |
| Recovery monitoring add | ✅ Functional (assigned Satgas) |
| Evidence metadata update | ✅ Functional (assigned Satgas) |
| Evidence status update | ✅ Functional (assigned Satgas) |

**Satgas DisabledWorkflowAction count:** 6 instances (all intentional UX hints — explained why an action is not available)
- Case status update (when not assigned)
- Create investigation (when preconditions not met)
- Create recommendation (when preconditions not met)
- Create decision (when preconditions not met — always shown as Satgas cannot create decisions)
- Create recovery (when preconditions not met — always shown as Satgas cannot create recoveries)
- Evidence files (M16 scope note — always shown)

**Satgas mock data in use:** None  
**Satgas missing API integrations:** Admin notification list (`GET /api/v1/notifications`), My Work queues (`GET /api/v1/my-work/*`)  
**Satgas missing CRUD actions:** None for implemented workflow scope  
**Satgas missing navigation entries:** Notifications (hidden behind AccessDenied)  
**Satgas mobile responsiveness:** ✅ Sidebar collapsible, responsive grids, `hidden md:block` patterns  
**Satgas localization:** 🔴 None — all dashboard pages use hardcoded English strings  

---

### 1.3 Admin

| Page | Route | Status | Notes |
|---|---|---|---|
| Login | `/login` | ✅ Complete | Redirects to `/dashboard` |
| Dashboard Overview | `/dashboard` | ✅ Complete | Backend summary + charts |
| Report List | `/dashboard/reports` | ✅ Complete | Search/filter, anonymous badge, forwarding action |
| Report Detail | `/dashboard/reports/:id` | ✅ Complete | Metadata view, break-glass request, forward-to-case action |
| Case List | `/dashboard/cases` | ✅ Complete | Search/filter, linked to case detail |
| Case Detail | `/dashboard/cases/:id` | ✅ Complete | Full workflow sections + Satgas assignment action |
| Workflow Pipeline | `/dashboard/workflow` | ✅ Complete | Backend analytics |
| Analytics | `/dashboard/analytics` | ✅ Complete | Reports/cases/evidence charts from backend |
| Settings | `/dashboard/settings` | 🟡 Partial | localStorage-only, cosmetic toggles |
| Notifications | `/dashboard/notifications` | 🔴 Not Implemented | Renders `AccessDenied` |
| Users | `/dashboard/users` | 🔴 Not Implemented | Renders `AccessDenied`; backend APIs exist (M23) |
| Content | `/dashboard/content` | 🔴 Not Implemented | Renders `AccessDenied` |
| Break-Glass | `/dashboard/break-glass` | 🔴 Not visible | Navigation hidden for Admin (Super Admin only) |
| Audit Logs | — | 🔴 Not Implemented | No route; backend APIs exist (M12) |
| Reporter Registration Review | — | 🔴 Not Implemented | No route; backend APIs exist (M19) |

**Admin workflow actions on Case Detail:**

| Action | Status |
|---|---|
| Satgas assignment | ✅ Functional |
| Case status update | ❌ Blocked — only assigned Satgas can update |
| Create investigation | ❌ Blocked — correct, assigned Satgas only |
| Investigation activity | ❌ Blocked — correct, assigned Satgas only |
| Create recommendation | ❌ Blocked — correct, assigned Satgas only |
| Create decision | ❌ Blocked — correct, Super Admin only |
| Decision update/transition | ❌ Blocked — correct, Super Admin only |
| Create recovery | ✅ Functional |
| Recovery status transition | ✅ Functional |
| Recovery monitoring add | ✅ Functional |
| Evidence metadata/status | ❌ Blocked — correct, assigned Satgas only |

**Admin DisabledWorkflowAction count:** Same 6 instances as Satgas (all intentional)  
**Admin mock data in use:** None  
**Admin missing API integrations:** Notification list/read/mark-all, User management CRUD, Audit log list/detail, Reporter registration review  
**Admin missing CRUD actions:** User activate/deactivate/role, Reporter registration approve/reject  
**Admin missing navigation entries:** Notifications, Users (routes exist but AccessDenied)  
**Admin mobile responsiveness:** ✅ Same as Satgas  
**Admin localization:** 🔴 None on dashboard pages  

---

### 1.4 Super Admin

| Page | Route | Status | Notes |
|---|---|---|---|
| Login | `/login` | ✅ Complete | Redirects to `/dashboard` |
| Dashboard Overview | `/dashboard` | ✅ Complete | Global scope backend analytics |
| Report List | `/dashboard/reports` | ✅ Complete | Full report management |
| Report Detail | `/dashboard/reports/:id` | ✅ Complete | Metadata + break-glass + forward-to-case |
| Case List | `/dashboard/cases` | ✅ Complete | Full case management |
| Case Detail | `/dashboard/cases/:id` | ✅ Complete | All workflow actions available |
| Workflow Pipeline | `/dashboard/workflow` | ✅ Complete | Global workflow analytics |
| Analytics | `/dashboard/analytics` | ✅ Complete | Full analytics suite |
| Break-Glass | `/dashboard/break-glass` | ✅ Complete | Request, pending list, approve/deny, reveal |
| Settings | `/dashboard/settings` | 🟡 Partial | localStorage-only |
| Notifications | `/dashboard/notifications` | 🔴 Not Implemented | Renders `AccessDenied` |
| Users | `/dashboard/users` | 🔴 Not Implemented | Renders `AccessDenied` |
| Content | `/dashboard/content` | 🔴 Not Implemented | Renders `AccessDenied` |
| Audit Logs | — | 🔴 Not Implemented | No route exists |
| Reporter Registration Review | — | 🔴 Not Implemented | No route exists |

**Super Admin workflow actions on Case Detail:**

| Action | Status |
|---|---|
| Satgas assignment | ✅ Functional |
| Case status update | ❌ Blocked — only assigned Satgas (by design) |
| Create/update/transition decisions | ✅ Functional |
| Create/update/transition recoveries | ✅ Functional |
| Recovery monitoring add | ✅ Functional |

**Super Admin DisabledWorkflowAction count:** Same 6 instances  
**Super Admin mock data in use:** None  
**Super Admin missing API integrations:** Same as Admin, plus audit log viewer  
**Super Admin missing CRUD actions:** Same as Admin  
**Super Admin missing navigation entries:** Notifications, Users  
**Super Admin mobile responsiveness:** ✅ Same as Satgas/Admin  
**Super Admin localization:** 🔴 None on dashboard pages  

---

## 2. Cross-Role Findings

### 2.1 DisabledWorkflowAction Summary

| Location | Count | Type |
|---|---|---|
| [dashboard.cases.$id.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.cases.$id.tsx) L273 | 1 | Case status (intentional — non-assigned users) |
| [dashboard.cases.$id.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.cases.$id.tsx) L282 | 1 | Create investigation (intentional — precondition) |
| [dashboard.cases.$id.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.cases.$id.tsx) L295 | 1 | Create recommendation (intentional — precondition) |
| [dashboard.cases.$id.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.cases.$id.tsx) L310 | 1 | Create decision (intentional — precondition) |
| [dashboard.cases.$id.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.cases.$id.tsx) L325 | 1 | Create recovery (intentional — precondition) |
| [dashboard.cases.$id.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.cases.$id.tsx) L605 | 1 | Evidence files (M16 scope note — still references M16) |

> [!NOTE]
> All 6 `DisabledWorkflowAction` instances are **intentional UX hints** explaining why an action is unavailable. They are NOT stubs or placeholders. Only the evidence files instance (L605) references an outdated milestone label ("M16") and should be updated for clarity.

### 2.2 Mock Data

| File | Status |
|---|---|
| [mock-data/index.ts](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/mock-data/index.ts) | 🟡 Orphaned — file exists (190 lines) but is NOT imported anywhere. Safe to delete. |
| [types/index.ts](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/types/index.ts) | 🟡 Orphaned — legacy types used only by mock-data and [status-badge.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/components/status-badge.tsx). Status-badge itself is only imported by portal report detail. |

### 2.3 Missing API Integrations (Backend exists, frontend does not)

| Backend API | Backend Milestone | Frontend Status |
|---|---|---|
| `GET /api/v1/notifications` | M17 | 🔴 No API client, no page |
| `PATCH /api/v1/notifications/:id/read` | M17 | 🔴 No API client |
| `PATCH /api/v1/notifications/read-all` | M17 | 🔴 No API client |
| `GET /api/v1/my-work/summary` | M18 | 🔴 No API client (invalidation keys prepared) |
| `GET /api/v1/my-work/cases` | M18 | 🔴 No API client |
| `GET /api/v1/my-work/investigations` | M18 | 🔴 No API client |
| `GET /api/v1/my-work/recommendations` | M18 | 🔴 No API client |
| `POST /api/v1/reporter-registrations` | M19 | 🔴 No self-registration form |
| `GET /api/v1/reporter-registrations` | M19 | 🔴 No admin review list |
| `GET /api/v1/reporter-registrations/:id` | M19 | 🔴 No admin review detail |
| `PATCH .../approve` | M19 | 🔴 No admin approve action |
| `PATCH .../reject` | M19 | 🔴 No admin reject action |
| `GET /api/v1/users` | M23 | 🔴 No user list page |
| `GET /api/v1/users/:id` | M23 | 🔴 No user detail page |
| `PATCH .../activate` | M23 | 🔴 No activate action |
| `PATCH .../deactivate` | M23 | 🔴 No deactivate action |
| `PATCH .../role` | M23 | 🔴 No role assignment action |
| `GET /api/v1/audit-logs` | M12 | 🔴 No audit log page |
| `GET /api/v1/audit-logs/:id` | M12 | 🔴 No audit log detail |
| `POST /api/v1/reports` | M5 | 🔴 No report submission form |
| `GET /api/v1/reports/track/:trackingCode` | M5 | 🔴 No tracking lookup page |

### 2.4 Cosmetic / Non-Functional UI Elements

| Element | Location | Issue |
|---|---|---|
| Search bar | [dashboard-layout.tsx:L164](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/layouts/dashboard-layout.tsx#L164) | No `onChange` handler — purely visual |
| Settings page | [dashboard.settings.tsx](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.settings.tsx) | Campus profile, notification toggles, brand logo all save to `localStorage` only — no backend integration |
| High-contrast tables toggle | [dashboard.settings.tsx:L99](file:///d:/PROJECT%20CODING/SILAPPKASAL/frontend/src/routes/dashboard.settings.tsx#L99) | `defaultChecked` but not wired to any state |

### 2.5 Localization Gaps

| Area | ID (Bahasa) | EN (English) | Status |
|---|---|---|---|
| Portal pages | ✅ `portal.json` | ✅ `portal.json` | ✅ Full bilingual |
| Login page | ✅ `auth.json` | ✅ `auth.json` | ✅ Full bilingual |
| Common strings | ✅ `common.json` | ✅ `common.json` | ✅ Full bilingual |
| Dashboard Overview | — | — | 🔴 Hardcoded English |
| Dashboard Reports | — | — | 🔴 Hardcoded English |
| Dashboard Cases | — | — | 🔴 Hardcoded English |
| Dashboard Case Detail | — | — | 🔴 Hardcoded English |
| Dashboard Workflow | — | — | 🔴 Hardcoded English |
| Dashboard Analytics | — | — | 🔴 Hardcoded English |
| Dashboard Settings | — | — | 🔴 Hardcoded English |
| Dashboard Break-Glass | — | — | 🔴 Hardcoded English |
| Workflow Action Dialogs | — | — | 🔴 Hardcoded English |
| Dashboard Layout/Navigation | — | — | 🔴 Hardcoded English |
| Language Switcher in Dashboard | — | — | 🔴 Not present (only in Portal) |

---

## 3. Frontend Completion Percentage

### 3.1 By Role

| Role | Implemented Pages | Total Required Pages | Completion % |
|---|---|---|---|
| **Reporter** | 6/9 | 9 | **67%** |
| **Satgas PPKS** | 5/7 | 7 | **71%** |
| **Admin** | 8/13 | 13 | **62%** |
| **Super Admin** | 9/14 | 14 | **64%** |

> [!NOTE]
> "Required pages" includes all pages where a backend API exists. Pages marked as correctly blocked (e.g., Satgas cannot access Reports) are not counted as missing.

### 3.2 Overall Frontend Completion

| Category | Done | Total | % |
|---|---|---|---|
| Route pages implemented | 18 | 23 routes | 78% |
| Route pages functional (not AccessDenied) | 15 | 23 routes | 65% |
| Backend API integrations consumed | 38 | 59 endpoints | **64%** |
| Workflow actions implemented | 18 | 18 actions | **100%** |
| Localization coverage | Portal + Login | Portal + Login + Dashboard | **35%** |
| Mock data removed | Orphaned | — | 🟡 |

### Overall Frontend Readiness: **~65%**

---

## 4. Remaining Frontend Milestones

| # | Milestone Name | Scope | Effort |
|---|---|---|---|
| F1 | Dashboard Notifications | Admin/Satgas notification list, read/mark-all, unread badge in nav | Medium |
| F2 | User Management UI | User list, detail, activate/deactivate, role assignment for Admin/Super Admin | Medium |
| F3 | Reporter Registration Review | Admin registration list, detail, approve/reject actions | Medium |
| F4 | Reporter Self-Registration Form | Public registration form at `/register` | Small |
| F5 | Report Submission Form | Reporter/public report submission with anonymous option | Medium |
| F6 | Tracking Code Lookup | Public tracking code lookup page | Small |
| F7 | Audit Log Viewer | Admin/Super Admin audit log list and detail | Medium |
| F8 | My Work / Work Queue UI | Satgas-focused work queue page with summary/cases/investigations/recommendations | Medium |
| F9 | Dashboard Localization | Bilingual support for all dashboard pages and workflow actions | Large |
| F10 | Settings Backend Integration | Replace localStorage settings with backend API (or remove cosmetic toggles) | Small |
| F11 | Dead Code Cleanup | Remove orphaned `mock-data/`, `types/index.ts`, `status-badge.tsx` legacy code | Small |
| F12 | Search Implementation | Wire dashboard search bar or remove it | Small |
| F13 | Evidence File Upload/Download | File upload/download/preview UI for evidence (requires backend work too) | Large |

---

## 5. Recommended Order

### 5.1 Before Internal Demo

> Priority: Show a complete, impressive flow to stakeholders

| Order | Milestone | Why |
|---|---|---|
| 1 | F11 — Dead Code Cleanup | Remove noise before demo |
| 2 | F1 — Dashboard Notifications | Visible bell badge + notification list makes the app feel alive |
| 3 | F2 — User Management UI | Stakeholders expect to see user admin |
| 4 | F4 — Reporter Self-Registration | Show the full reporter flow from registration to report tracking |
| 5 | F10 — Settings Backend / Cleanup | Settings page should not feel broken |
| 6 | F12 — Search Implementation | Search bar should work or be removed |

### 5.2 Before UAT (User Acceptance Testing)

> Priority: All CRUD flows complete for every role

| Order | Milestone | Why |
|---|---|---|
| 1 | F1 — Dashboard Notifications | Core UX — testers will look for notifications |
| 2 | F2 — User Management UI | Admin must manage users during UAT |
| 3 | F3 — Reporter Registration Review | Admin must approve registrations during UAT |
| 4 | F4 — Reporter Self-Registration | Reporters need to register during UAT |
| 5 | F5 — Report Submission Form | Reporters need to submit reports during UAT |
| 6 | F6 — Tracking Code Lookup | Anonymous reporters need tracking during UAT |
| 7 | F8 — My Work / Work Queue UI | Satgas testers need their work queue |
| 8 | F7 — Audit Log Viewer | Compliance testers will audit the audit trail |
| 9 | F10 — Settings Backend | Settings must persist across sessions |
| 10 | F11 — Dead Code Cleanup | Clean codebase for testing |
| 11 | F12 — Search Implementation | Testers will try to search |

### 5.3 Before Go-Live

> Priority: Production-quality completeness

| Order | Milestone | Why |
|---|---|---|
| 1 | All UAT milestones (F1–F12) | Full feature coverage |
| 2 | F9 — Dashboard Localization | Bahasa Indonesia required for all users in production |
| 3 | F13 — Evidence File Upload/Download | Evidence handling is critical for real case management |
| 4 | Security hardening pass | Token expiry, CORS, CSP, rate limiting on frontend |
| 5 | Accessibility audit | Screen readers, keyboard navigation, ARIA labels |
| 6 | Performance optimization | Code splitting for large chunks (dashboard.cases.$id = 147KB) |

---

## 6. Risk Summary

| Risk | Severity | Notes |
|---|---|---|
| No notification UI for Admin/Satgas | **High** | Backend dispatches 21 notification types but Admin/Satgas cannot see any in the frontend |
| No user management UI | **High** | Admin cannot manage users without direct API calls |
| No reporter registration flow | **High** | Reporters cannot sign up without admin manually creating accounts |
| No report submission form | **High** | Core business function not available in frontend |
| Dashboard not localized | **Medium** | Only Portal has bilingual support; all dashboard pages are English-only |
| Orphaned mock data / legacy types | **Low** | Not imported but adds confusion and maintenance burden |
| Search bar is cosmetic | **Low** | May confuse users expecting it to work |
| Settings saves to localStorage only | **Low** | Settings lost on device/browser change |
| Evidence file upload not implemented | **Medium** | Evidence metadata exists but no file handling |
| My Work page not implemented | **Medium** | Satgas has no dedicated work queue view |
