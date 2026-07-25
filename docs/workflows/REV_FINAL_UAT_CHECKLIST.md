# REV-FINAL-INTEGRATION-01 — Manual UAT Checklist

**Execution:** pending. Use only approved disposable accounts/data and record operator, build,
environment, timestamp, result, and non-sensitive evidence reference per row.

| ID | Role / prerequisite | Steps | Expected result | Evidence reference | Actual result / status | Tester / date |
|---:|---|---|---|---|---|---|
| 1 | Reporter, submitted Pengaduan without Case | Direct cancellation | Completed cancellation, safe portal status, no operational data leak | Pending | Pending | Pending |
| 2 | Reporter, eligible Case | Create/upload/submit/cancel/resubmit formal withdrawal | Private document, valid state transitions, no URL/path leak | Pending | Pending | Pending |
| 3 | Campus Admin same campus | Review queue approve/reject | Campus-only queue; approval/rejection remains atomic | Pending | Pending | Pending |
| 4 | Assigned Satgas | Self-assignment | Only unassigned same-campus Case can be claimed | Pending | Pending | Pending |
| 5 | Campus Admin | Multi-assignment/reassignment | History retained; only new assignees notified | Pending | Pending | Pending |
| 6 | Two eligible actors | Assignment stale conflict | One winner; stale actor receives safe conflict | Pending | Pending | Pending |
| 7 | Pending formal withdrawal | Attempt Case mutation | Assignment, investigation, recommendation, Decision, BA, Recovery, summary, closure rejected | Pending | Pending | Pending |
| 8 | Campus Admin, recorded Decision | Finalize | Server issues `SK/PPKS/YYYY/NNN` once | Pending | Pending | Pending |
| 9 | Same finalized Decision | Retry finalization | Existing result; no second number/audit/transition | Pending | Pending | Pending |
| 10 | Super Admin | Read Decision | Metadata-only; no narrative mutation | Pending | Pending | Pending |
| 11 | Reporter | Portal detail | No formal Decision number or internal workflow data | Pending | Pending | Pending |
| 12 | Campus Admin, eligible Case | Create/edit/finalize BA | Versioned BA finalized only after required fields | Pending | Pending | Pending |
| 13 | Assigned Satgas | Create/edit BA | Allowed; finalization control unavailable | Pending | Pending | Pending |
| 14 | Active finalized BA | Create revision | New draft; prior active version becomes superseded only when replacement finalizes | Pending | Pending | Pending |
| 15 | Super Admin | Read BA | Metadata only; no narrative/actor identity | Pending | Pending | Pending |
| 16 | Reporter | Attempt BA route/detail | Denied/no projection | Pending | Pending | Pending |
| 17 | BA with direct identifier | Finalize | Safe 422 safeguard; no partial finalization | Pending | Pending | Pending |
| 18 | Anonymous Reporter Case | Normal operational access | Identity remains masked without break-glass | Pending | Pending | Pending |
| 19 | Break-glass requester/reviewer | Request/approve/revoke | Exact assignment, TTL, audit, requester-only reveal | Pending | Pending | Pending |
| 20 | Logout then another actor login | Navigate private views | Previous private query data absent | Pending | Pending | Pending |
| 21 | All relevant roles | Check terminology/filters | User-facing Pengaduan and campus/Satgas filters behave correctly | Pending | Pending | Pending |
| 22 | Private evidence/withdrawal/content file | Preview MIME/blob failure | Safe error, no storage path or persistent object URL | Pending | Pending | Pending |
| 23 | Withdrawn or terminal Case | Attempt mutation | Read-only historical state | Pending | Pending | Pending |
| 24 | Workflow notification recipient | Trigger eligible action | Correct minimal recipient/payload; no narrative/reason/document URL | Pending | Pending | Pending |

Browser/component E2E harness is not presently claimed by this checklist. A completed row requires
real browser evidence; source-contract tests are not a substitute.
