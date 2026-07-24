# Provisional Complaint Withdrawal Policy Register

Decision state: provisional, pending leadership confirmation.

| Decision | Current provisional rule (01A + 01B + 01C) |
|---|---|
| Direct cancellation window | Only exact `submitted`, null `forwarded_at`, no Case |
| Ownership | Active Reporter with exact non-null `reporter_id` ownership |
| Permission | `reports.cancel.own` |
| Approval | Not required before handling starts |
| Data retention | Complaint and existing Reporter evidence remain as history |
| Reason privacy | Encrypted; excluded from response, audit metadata, and notification |
| Reason normalization | Trim Unicode boundary whitespace before applying the 20–2,000 character limits |
| Admin notification | Informational, after commit, active same-campus authorized Admin only |
| Formal withdrawal eligibility | Feature-on active Reporter owner; forwarded Report or eligible Case before decided/recovery/monitoring/terminal/escalated; Decision not finalized; no active request |
| Formal state machine | `draft -> waiting_document -> pending_review -> approved/rejected`; active Reporter states may become `cancelled` |
| Formal document | Authenticated print-safe DRAFT only; not an official campus template or generated official PDF |
| Signed attachment | Private immutable PDF/JPEG/PNG, 10 MiB maximum, versioned per request, integrity checked |
| Pending effect | Report/Case statuses unchanged; operational Case mutation paused with `withdrawal_pending_review` |
| Formal cancellation | Reporter may cancel before decision; private attachment history retained and operations resume |
| Admin decision | Active own-campus Admin with `reports.withdraw.review.own_campus`; lock/version/state/document revalidation required |
| Rejection | Encrypted required reason; Admin chooses whether a fresh resubmission is permitted |
| Resubmission | Exact owner only; one unique new draft may supersede a rejected request; no attachment reuse |
| Approval | Report and existing Case become `withdrawn`; `closed_at`, assignments, evidence, and history are preserved |
| Break-glass | Related active grants are revoked on approval; unrelated/history rows remain |
| Super Admin | Monitoring metadata only; no reason, document, or decision action |
| Satgas | Generic pause/withdrawn state only; no reason, document, or reviewer metadata |
| Case terminal state | `closed` and `withdrawn` are read-only for every operational mutation |
| Assignment retention | Assignment rows remain for history and read visibility, but not active workload |
| Default production posture | Both withdrawal feature flags disabled; formal activation requires additive migration and operational approval |

Open leadership decisions for later submilestones:

1. approved official letter template and final signature/meterai requirements;
2. Admin review SLA (elapsed time is not currently an SLA);
3. production activation date and user communication.
