# REV-WITHDRAW-01B — Reporter Formal Withdrawal

REV-WITHDRAW-01B implements only the Reporter side of formal complaint withdrawal. It does not
approve, reject, or finalize a withdrawal.

## Eligibility and state

The server is authoritative. A formal request requires the enabled feature flag, an active Reporter
with `reports.withdraw.own`, exact non-null Report ownership, an undeleted forwarded Report or
existing eligible Case, no finalized Decision, and no active withdrawal. Legacy null-owner
anonymous Reports are not claimable. Authenticated anonymous owners use the masked name
`Pelapor Anonim` in the DRAFT.

```text
draft
  -> waiting_document
  -> pending_review

draft | waiting_document | pending_review
  -> cancelled
```

The DRAFT GET is lifecycle read-only. The first accepted signed-document upload is the explicit
authenticated mutation that moves `draft` to `waiting_document`; every upload, submit, and cancel
requires the current `lock_version`. Submission needs the latest valid signed document. Report and
Case status values never change in this submilestone.

## DRAFT and signed document

The DRAFT is owner-only authenticated HTML with A4 print CSS and the watermark:

```text
DRAFT
BELUM MERUPAKAN FORMAT RESMI KAMPUS
```

It is a temporary statement layout, not an official campus PDF/template. Browser print/save-to-PDF
is the only PDF generation path.

Signed documents accept PDF, JPEG, or PNG up to 10 MiB. They are stored on the private
`withdrawal` disk by UUID path. Each upload creates the next immutable
`signed_withdrawal_statement` version; prior versions remain private history. Resources project
only a public attachment reference, type, version, safe MIME/size, and upload time.

## Pending operational pause

At `pending_review`, the Case remains readable but operational mutations are blocked centrally with
HTTP 409 `withdrawal_pending_review`. Lock acquisition is ordered Report → Case → Withdrawal.
Cancelling the request removes the pause without changing the prior Report/Case status, deleting the
request, deleting documents, or changing assignments.

## Audit and notification

Create, DRAFT preparation, DRAFT view, upload, download, submit, and cancellation have dedicated
audit actions. A DRAFT view audit never changes withdrawal lifecycle data.
Allowlisted metadata excludes the reason, original filename, path, checksum, content, signature, and
meterai data.

Submission sends `NOTIF-26` after commit to active same-campus authorized Admin users. Cancelling
after submission sends `NOTIF-27` to the same scope. Satgas and other campuses receive neither.

## Limitations

- no Admin review queue or document preview;
- no approve/reject endpoint;
- no rejection resubmission;
- no Super Admin approval;
- no official campus template or PDF generator;
- no external antivirus service; server-side MIME, signature, image-bound, and PDF active-content
  checks remain mandatory and fail closed;
- no final Report/Case `withdrawn` transition;
- no assignment or Putusan changes.
