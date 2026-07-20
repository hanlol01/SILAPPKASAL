# Anonymous Emergency Access Policy — REV-WF-03 R2

**Project:** SILAPPKASAL  
**Status:** Implemented repository contract
**Updated:** 2026-07-20
**Source of truth:** executable routes, policies, services, migrations, resources, and tests

This policy supersedes the M26 Break Glass ownership and TTL rules. It does not change the default
masking of anonymous Reporter identity and does not implement REV-WF-03 R3.

## 1. Purpose and boundary

Emergency Access is an exceptional, case-specific, time-bounded, and audited mechanism. Anonymous
identity remains masked in normal Report and Case resources, Super Admin oversight, file metadata,
and ordinary frontend views. Identity is returned only by the dedicated requester-only reveal
endpoint.

Emergency Access must never be used for routine lookup, curiosity, retaliation, disclosure to the
respondent, bulk access, or contact outside the authorized case-handling need.

## 2. Role ownership

| Role | Request | Review | Approve/deny | Revoke | Reveal |
|---|---:|---:|---:|---:|---:|
| Reporter | No | No | No | No | No |
| Satgas PPKS | Own assigned Case only | Own metadata only | No | No | Own active grant only |
| Admin Kampus | No | Same campus | Same campus | Same-campus active grant | No |
| Super Admin | No | Audit oversight only | No | No | No |

The Satgas requester and Admin reviewer are necessarily different roles, so self-approval is not
available. A grant is exclusive to its `requestor_id`; another assigned Satgas cannot use it.

## 3. Request eligibility

The backend accepts a request only when every condition is true:

- requester is an active `satgas_ppks` user with `privacy.request_break_glass`;
- the supplied Case exists and has an integrity-valid linked Report;
- the Report is classified `anonymous` and retains a Reporter account relation;
- requester belongs to the Report campus and has a current active Case assignment;
- reason category is supported and the sanitized reason is 50–2000 characters;
- duration is exactly 30, 60, 240, or 1440 minutes;
- no pending or active request exists for the same Report/requester pair.

Denied, revoked, or expired history remains readable and a later request may be created.

## 4. Review and grant lifecycle

```text
pending
  ├─ Admin Kampus denies  → denied
  └─ Admin Kampus approves → approved (grant starts immediately)
                                  ├─ time reaches expires_at → expired
                                  └─ Admin Kampus revokes    → revoked
```

Approval locks the request and revalidates the requester account, permissions, campus, Case/Report
integrity, and active assignment. It sets `approved_at`, `grant_starts_at`, and `expires_at` from the
approved request duration. Expiry does not begin at first reveal.

Denial requires `denial_reason`. Revocation requires `revocation_reason`, is allowed only while a
grant is active, and takes effect immediately. Narratives remain in authorized request metadata but
are excluded from audit metadata.

## 5. Reveal controls

Each explicit reveal revalidates:

- the authenticated actor is the active Satgas requester;
- `requestor_id` matches the actor;
- Report remains anonymous and linked to the same valid Case;
- actor remains same-campus and actively assigned;
- stored state is `approved` or compatible legacy `viewed`;
- current time is within `[grant_starts_at, expires_at)`;
- `revoked_at` is null.

Success returns only `name`, `nim`, `email`, `phone_number`, `faculty`, `study_program`, and
`university`. It increments `view_count`, updates `last_viewed_at`, and audits every reveal. The
response is non-cacheable (`Cache-Control: no-store`, `Pragma: no-cache`, `Expires: 0`). The React
client does not put identity in TanStack Query, URLs, browser storage, toasts, or logs, and clears the
dialog state on close.

## 6. Expiry correctness

`BreakGlassRequest::isGrantActive()` is the source of truth. An elapsed `expires_at` denies reveal
even if the stored status is still `approved`. Bounded access-time normalization changes the status
to `expired` and emits `break_glass.expire` once. Correctness does not require a scheduler.

## 7. Anonymous filename protection

For anonymous Reports, internal list responses and Content-Disposition headers use:

- Reporter Supporting Files: `supporting-file.{ext}`;
- Internal Investigation Evidence: `internal-evidence.{ext}`.

The Reporter owner continues to see their original Supporting File names. File bytes are not
modified. A filename embedded inside the file content cannot be sanitized by this feature.

## 8. Audit and notifications

Critical privacy audit actions are `break_glass.request`, `break_glass.approve`,
`break_glass.deny`, `break_glass.view_identity`, `break_glass.revoke`, and `break_glass.expire`.
Allowed metadata is limited to public Report/Case references, reason category, duration code,
status, expiry, view count, and safe result state. Reporter identity, request/denial/revocation
narratives, filenames, Evidence content, and witness/respondent content are forbidden.

Campus Admins receive a generic review notification. The requester receives generic resolution
notifications. The Reporter receives a generic privacy notice on approval without requester,
reviewer, reason, or identity data.

## 9. Legacy compatibility

Existing rows are preserved. Legacy duration is 480 minutes. For legacy `viewed` grants,
`grant_starts_at` is derived from `viewed_at`; for unviewed approved grants it is derived from
`approved_at` with bounded fallbacks. Old elapsed grants become `expired`. Existing `viewed_at`
may initialize `view_count=1`, but migration creates no reveal audit event. Legacy `viewed` remains
read-compatible while it is within its migrated grant window.

## 10. Deployment

Apply the two R2 migrations in order through the normal deployment migration command:

1. `2026_07_20_000000_add_emergency_access_lifecycle_to_break_glass_requests.php`
2. `2026_07_20_010000_reconcile_r2_emergency_access_permissions.php`

Back up the database first. Do not seed production. The schema migration backfills in chunks,
normalizes duplicate active rows conservatively, and adds a PostgreSQL/SQLite-compatible partial
unique index for the Report/requester active lookup. The RBAC migration only reconciles the four
managed Emergency Access permissions and preserves unrelated Super Admin oversight authorities.
