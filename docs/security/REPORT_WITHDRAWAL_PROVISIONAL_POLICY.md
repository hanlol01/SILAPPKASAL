# Provisional Complaint Withdrawal Policy Register

Decision state: provisional, pending leadership confirmation.

| Decision | REV-WITHDRAW-01A rule |
|---|---|
| Direct cancellation window | Only exact `submitted`, null `forwarded_at`, no Case |
| Ownership | Active Reporter with exact non-null `reporter_id` ownership |
| Permission | `reports.cancel.own` |
| Approval | Not required before handling starts |
| Data retention | Complaint and existing Reporter evidence remain as history |
| Reason privacy | Encrypted; excluded from response, audit metadata, and notification |
| Reason normalization | Trim Unicode boundary whitespace before applying the 20–2,000 character limits |
| Admin notification | Informational, after commit, active same-campus authorized Admin only |
| Formal withdrawal | Not exposed; reserved for `under_review`, `need_info`, and later states |
| Case terminal state | `closed` and `withdrawn` are read-only for every operational mutation |
| Assignment retention | Assignment rows remain for history and read visibility, but not active workload |
| Default production posture | Both withdrawal feature flags disabled |

Open leadership decisions for later submilestones:

1. approved formal letter template and signature requirements;
2. Admin review SLA and rejection/resubmission rules;
3. exact post-Case withdrawal effects and terminal reporting treatment;
4. production activation date and user communication.
