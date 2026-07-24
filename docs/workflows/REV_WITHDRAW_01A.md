# REV-WITHDRAW-01A Workflow Revision Note

Status: implemented in source, disabled by default, not asserted as migrated or deployed.

Direct cancellation is provisional and applies only while a complaint is still exactly
`submitted`, has never been forwarded, and has no Case. It completes immediately without Admin
approval, preserves the complaint as history, and creates a completed early-cancellation withdrawal
record. Reporter evidence already attached to the complaint is preserved and does not block the
action.

Once the complaint is `under_review`, `need_info`, or `forwarded`, direct cancellation is no longer
available. Those states belong to the later formal-withdrawal workflow. Rejected, cancelled,
withdrawn, Case-backed, soft-deleted, ambiguous-owner, and legacy null-owner Reports are never
eligible.

The provisional terminal foundation adds `cancelled` and `withdrawn`, but REV-WITHDRAW-01A mutates
only Report `submitted → cancelled`. It does not create a Case, modify assignments, add Satgas UI,
review a request, upload a letter, generate a document, or alter Decision/BA behavior.

Feature flags:

- `REPORT_EARLY_CANCELLATION_ENABLED=false`
- `REPORT_FORMAL_WITHDRAWAL_ENABLED=false`

Final formal-withdrawal templates, review rules, and production enablement await leadership
approval.

## Terminal Case semantics

`closed` and `withdrawn` are operationally terminal. A withdrawn Case remains readable as history,
and its active assignment rows remain stored so an assigned Satgas retains read-only visibility.
Those assignments are not active workload: withdrawn Cases are excluded from Dashboard open counts,
assigned/unassigned operational analytics, My Work, and the active quick filter. An explicit
`withdrawn` status filter may still retrieve the historical Case.

All Case and child-workflow mutations recheck terminal state after locking the Case. Assignment,
assessment, status/escalation, investigation, evidence, recommendation, decision, recovery,
monitoring, final-summary, closure, and Case-backed Reporter evidence mutations fail with a state
conflict. This terminal behavior remains provisional pending leadership approval.

## Direct-cancellation reason contract

The API trims leading and trailing Unicode whitespace before validation. The canonical result is
then validated as 20–2,000 Unicode characters and encrypted for storage. Whitespace, paragraphs, and
newlines inside the reason are preserved. The reason remains excluded from API responses, audit
metadata, notifications, logs, and exception context.

## Known unrelated regression debt

The complete demo `DatabaseSeeder` regression still encounters an existing
`ContentFoundationSeeder` duplicate for the normalized Content category `Perspektif Psikolog`.
That failure predates and is not caused by the withdrawal patch; REV-WITHDRAW-01A does not modify
the Content category registry or seeder.
