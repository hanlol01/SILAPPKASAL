# REV-WITHDRAW-01C Admin Review and Finalization

This submilestone adds the operational Campus Admin queue, private review, approval/rejection,
Reporter resubmission, and final `withdrawn` lifecycle. The governing decision is still provisional
policy pending leadership confirmation; this implementation does not claim an official campus
letter template, Putusan code, Berita Acara, SLA, or Super Admin approval role.

## State and authorization

Only `pending_review` can become `approved` or `rejected`. The reviewer is an active Campus Admin
with `reports.withdraw.review.own_campus` and exact campus scope. Super Admin sees metadata only.
Satgas sees a generic operational pause and, after approval, a generic withdrawn terminal state.

Approval locks Report, Case if present, then Withdrawal and revalidates permission, campus,
`lock_version`, lifecycle/soft-delete state, finalized Decision, and latest signed document. A valid
approval revokes exact related active break-glass grants, marks Report and Case `withdrawn`, fills
`withdrawn_at`, retains assignments/evidence/history, leaves `closed_at` null, records allowlisted
audits, and queues the Reporter notification after commit. A conflict leaves all lifecycle state
unchanged.

Rejection records the reviewer timestamps, encrypted normalized reason, and
`resubmission_allowed`; Report and Case do not change and the pending operational pause ends. An
allowed Reporter resubmission creates a fresh `draft` with `supersedes_id`; the rejected request and
its private attachment history remain immutable, the new draft must receive a new document, and the
unique supersession link prevents a rejected request from branching into multiple replacements.

## Privacy and operations

The Admin list excludes reasons and documents. Admin detail exposes only user-safe request data and
safe attachment version/MIME/size metadata. Document bytes remain private/no-store and are audited
only after authorization and readable-stream preparation. Super Admin never receives reasons,
attachment metadata, document bytes, internal lifecycle IDs, or mutation capability. Satgas never
receives withdrawal references, reasons, documents, or reviewer metadata through operational
Report/Case resources.

The formal feature flag blocks new Reporter create/resubmit/upload/submit work when disabled, but
does not block Admin resolution of requests already pending. Queue elapsed time is descriptive and
is not an SLA. The print-safe DRAFT remains explicitly unofficial.
