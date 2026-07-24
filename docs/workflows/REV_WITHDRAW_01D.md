# REV-WITHDRAW-01D Final Integration

REV-WITHDRAW-01D completes cross-role presentation and regression coverage for the withdrawal
foundation delivered in 01A-01C. It adds no database migration and does not alter the approved
Report/Case lifecycle, Satgas assignment rules, Putusan, Berita Acara, or SLA.

## Final flow

```text
submitted + no Case
  -> direct cancellation -> cancelled_by_reporter

forwarded/eligible Case
  -> draft -> waiting_document -> pending_review
  -> cancelled | rejected -> optional fresh draft | approved -> Report/Case withdrawn
```

Every formal mutation sends the current `lock_version`. A 409 never produces optimistic success;
the client offers a refresh and server state remains authoritative. Resubmission creates a fresh
request with no copied attachment. The rejected request and every immutable attachment version
remain historical.

## Role and privacy matrix

| Surface | Reporter | Campus Admin | Satgas | Super Admin |
|---|---|---|---|---|
| Direct cancellation | Own eligible complaint | History/status only | No action | Metadata through existing monitoring |
| Formal draft/upload/submit/cancel/resubmit | Own request, capability-gated | No Reporter mutation | No access | No access |
| Review queue | No access | Exact own campus | No access | Cross-campus metadata only |
| Reason/rejection reason | Own request | Authorized own-campus detail | Never | Never |
| Signed document | Own authenticated request and version history | Authorized own-campus private preview | Never | Never |
| Approve/reject | Never | `reports.withdraw.review.own_campus` and server capability | Never | Never |
| Pending operational state | Reporter-safe status | Review metadata/action | Generic pause only | Metadata only |
| Approved state | Complaint `Dicabut`, request read-only | Historical review detail | Generic terminal read-only | Final metadata only |

Super Admin responses contain campus, registration number, withdrawal status, submitted/reviewed
timestamps, elapsed duration, and result. Elapsed duration stops at `reviewed_at` or `cancelled_at`
after a final outcome instead of continuing to increase. They omit reasons, attachments, filenames, reviewer
identity, storage metadata, and mutation capability. Satgas operational resources contain neither a
withdrawal reference nor private request metadata.

## Status matrix

| Withdrawal status | Reporter capability | Admin capability | Report/Case effect |
|---|---|---|---|
| `draft` | View DRAFT, upload, cancel | None | No status change |
| `waiting_document` | View DRAFT, upload/replace, submit when valid, cancel | None | No status change |
| `pending_review` | Read-only attachment history, cancel request | Review/approve/reject in own campus | Status unchanged; Case mutations paused |
| `rejected` | Read-only; resubmit only when projected | Historical detail | Pause released; Report/Case unchanged |
| `cancelled` | Read-only history | Historical detail | Pause released; Report/Case unchanged |
| `approved` | Read-only status and private owner attachments | Historical detail/document | Report and existing Case terminal `withdrawn` |

`cancelled_by_reporter` is the separate direct-cancellation outcome. It applies only before handling
starts and does not create a formal approval flow.

## Capability rules

Frontend visibility never grants authority. Reporter actions use the server-projected
`can_cancel`, `can_request_withdrawal`, `can_view_draft`, `can_upload_document`, `can_submit`,
`can_cancel_request`, and `can_resubmit` flags. Admin review uses `can_review`, `can_approve`,
`can_reject`, and `can_view_signed_document`. The backend repeats ownership, active-user, role,
permission, campus, lifecycle, integrity, and optimistic-lock checks for every request.

## Cache and invalidation matrix

| Mutation | Invalidated client data |
|---|---|
| Direct cancellation | Reporter report list/detail, summary, timeline, handling/evidence; Dashboard and operations roots |
| Formal create/upload/submit/cancel/resubmit | Reporter report/withdrawal/timeline/handling/evidence; Dashboard and all operations projections |
| Admin approve/reject | Review list/detail; Report/Case list and detail via operations root; Dashboard summary, analytics, workflow, evidence/workload |

All `operations` query data is private and cancelled/removed during logout, session invalidation,
and account transition. Filter values are part of query keys and the withdrawal queue uses
URL-synchronized status/search/pagination without previous-filter placeholder data.

## UX and security invariants

- Direct cancellation has priority over formal withdrawal only when its authoritative capability is
  true; an applicable formal request suppresses direct cancellation.
- Initial loading, background refetch, filtered/unfiltered empty, access denied, network error,
  validation failure, lifecycle conflict, and stale update have distinct safe states.
- Review preview uses authenticated Blob data, revokes object URLs, aborts replaced/unmounted
  requests, and displays a retryable safe fallback.
- No reason is placed in URL, local/session storage, console, audit metadata, or notification.
- No attachment receives a public URL; responses omit internal IDs, disk, path, hash, and raw
  original filename.
- Pending and withdrawn Case mutations remain backend-blocked even if a stale/deep-linked client
  attempts an action. Assignments remain visible only as history.
- Status UI includes text and does not rely only on color. Dialogs are labelled, scrollable, and
  usable from 320 px through desktop in light/dark themes.

## Known limitations and release gates

- The print-safe DRAFT is not the official campus template; leadership approval is pending.
- PostgreSQL additive-migration preflight and true parallel concurrency testing are pending.
- External antivirus is not integrated; current validation remains structural and fail-closed.
- Browser E2E and assistive-technology device coverage are pending; repository frontend coverage is
  source-contract plus TypeScript/build/lint.
- Elapsed queue time is informational, not SLA enforcement.
- Feature flags remain disabled by default until product, security, storage, queue-worker, and
  operational approval is complete.

Use `REV_WITHDRAW_UAT_CHECKLIST.md` for leadership acceptance.
