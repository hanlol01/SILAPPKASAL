# Complaint Withdrawal Leadership UAT Checklist

Use this checklist in a non-production environment with additive migrations applied normally.
Never use `migrate:fresh`. Each checkbox below is a test step and expected result combined. Record
its explicit `Lulus` or `Gagal` result plus screenshots/log references in the result table; keep
evidence outside the source commit.

## Preconditions

- [ ] Feature flags and test accounts are approved for UAT.
- [ ] Reporter, own-campus Admin, different-campus Admin, Satgas, and Super Admin accounts exist.
- [ ] Private `withdrawal` disk is writable and not web served; queue worker is running.
- [ ] Test data covers submitted, forwarded, eligible Case, pending review, rejected, cancelled, and
      approved/withdrawn outcomes.
- [ ] PostgreSQL migration/preflight status and known limitations are acknowledged.

## Reporter

- [ ] Direct cancellation appears only for an eligible submitted complaint and requires a 20-2,000
      character reason plus confirmation.
- [ ] Success refreshes list, detail, summary, badge, and timeline to “Dibatalkan oleh Pelapor”.
- [ ] Formal flow survives refresh/login: reason -> DRAFT -> signed upload -> submit.
- [ ] A new upload creates a visible immutable version; older versions remain privately downloadable.
- [ ] A stale tab receives a safe conflict and refresh action, never false success.
- [ ] Pending review shows the generic pause and permits only server-authorized request cancellation.
- [ ] Rejection displays the reason; resubmit appears only when allowed and starts without an old attachment.
- [ ] Approval displays Report “Dicabut” and withdrawal “Pencabutan Disetujui”; all mutation actions
      disappear while owner attachment history remains authenticated.

## Campus Admin

- [ ] Navigation opens the default oldest-first `pending_review` queue.
- [ ] Search, status, page, and page size survive refresh/back/bookmark.
- [ ] Initial loading, refetch, no requests, filtered empty, network error/retry, and access denied are distinct.
- [ ] Pending Report list badge says “Cabut Aduan”; Report status has not changed before approval.
- [ ] Detail links to review and shows only own-campus request data.
- [ ] Private PDF/image preview loads without a public URL and has a safe failure/retry state.
- [ ] Approve and reject cannot double-submit; stale decisions reload authoritative detail.
- [ ] Approval updates queue, Report/Case detail/list, Dashboard/workflow/workload, notifications, and timeline.
- [ ] Rejection preserves Report/Case state and releases the operational pause.
- [ ] A different-campus Admin receives authorization-safe not-found/forbidden behavior and no metadata.

## Satgas

- [ ] Pending review shows only “Proses penanganan sedang dihentikan sementara.”
- [ ] No reason, document, reviewer identity, or withdrawal-detail link appears.
- [ ] All Case mutations fail closed with 409, including direct/deep-linked requests.
- [ ] Approved Case says “Proses pengaduan telah dihentikan.” and is read-only.
- [ ] Assignment/history remains visible; withdrawn is absent from active My Work but searchable as history.

## Super Admin

- [ ] Campus filter on Ringkasan and Pengaduan still changes query and results correctly.
- [ ] Withdrawal monitoring shows only campus, registration, status, timestamps, elapsed duration, and result.
- [ ] Reason, rejection reason, attachment metadata/bytes, filename, reviewer identity, and decision actions are absent.
- [ ] Direct calls to the private document and approve/reject endpoints are denied.

## Security, accessibility, and presentation

- [ ] Logout/account switch removes Reporter and operations caches before the next identity renders.
- [ ] Browser URL/storage/console contains no cancellation or withdrawal reason.
- [ ] Blob URLs disappear after preview replacement, failure, navigation, and unmount.
- [ ] Keyboard-only use reaches triggers, fields, file input, dialogs, confirmation, retry, and close controls;
      focus returns to the trigger.
- [ ] Screen reader announces labels, errors, progress/current step, loading, status text, and preview fallback.
- [ ] At 320 px, tablet, and desktop, long registration numbers/reasons and attachment history do not overflow.
- [ ] Light/dark themes preserve readable status, warning, destructive, disabled, iframe/image, and empty states.

## Evidence and sign-off

Complete one row for every executed scenario. Do not leave `Status` as `Belum diuji` when signing
off; use only `Lulus` or `Gagal` and link the matching evidence.

| Role/area | Test step | Expected result | Status (`Lulus`/`Gagal`) | Evidence reference |
|---|---|---|---|---|
| Reporter | Direct cancellation | Only an eligible submitted complaint can be cancelled and all Reporter projections refresh safely. | Belum diuji | |
| Reporter | Formal create, DRAFT, upload, submit | State survives refresh, immutable versions remain private, and stale mutation never reports success. | Belum diuji | |
| Reporter | Pending cancellation | Only the authoritative cancellation capability is shown and operational pause is explained safely. | Belum diuji | |
| Reporter | Rejection and resubmission | Owner-only rejection reason is visible; an allowed resubmission starts without prior attachments. | Belum diuji | |
| Reporter | Approval | Complaint is `Dicabut`, request is read-only, and owner document history remains authenticated. | Belum diuji | |
| Campus Admin | Queue and filters | Pending default, URL persistence, loading/refetch/error, and filtered/unfiltered empty states match the checklist. | Belum diuji | |
| Campus Admin | Private review and decision | Own-campus document preview and approve/reject are capability- and lock-version-guarded without false success. | Belum diuji | |
| Satgas | Pending pause | Only the generic pause banner is shown; mutation actions are absent and direct API attempts fail closed. | Belum diuji | |
| Satgas | Withdrawn history | Case is read-only, generic terminal copy is shown, and retained assignments are historical only. | Belum diuji | |
| Super Admin | Monitoring | Only approved metadata is visible; reason, identity, document, and mutation access are absent. | Belum diuji | |
| Privacy/cache | Logout and account switch | No prior identity's Portal, operations, Dashboard, reason, document, or Blob data renders for the next identity. | Belum diuji | |
| Accessibility | Keyboard and screen reader | Dialog focus, labels, errors, progress, loading, status, retry, and focus return are usable. | Belum diuji | |
| Responsive/theme | Mobile through desktop | 320 px, tablet, desktop, long content, light, and dark states have no blocking overflow or contrast issue. | Belum diuji | |
| Locale | Indonesian and English | Meaning and status distinctions remain equivalent in both locales. | Belum diuji | |
| Browser print | DRAFT print | Authenticated print-safe DRAFT renders and prints without being represented as an official template. | Belum diuji | |

- UAT environment/build: ____________________
- PostgreSQL preflight reference: ____________________
- Test evidence location: ____________________
- Product/operations approver: ____________________  Date: __________
- Security/privacy approver: ____________________  Date: __________
- Accepted limitations/exceptions: ________________________________________________
