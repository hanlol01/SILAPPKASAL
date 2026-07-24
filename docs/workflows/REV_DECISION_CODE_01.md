# REV-DECISION-CODE-01 — Formal Decision Code

## Product Contract

`decisions.decision_number` remains the only formal decision identifier. No second
`formal_decision_code` field is introduced.

New numbers use the canonical stored and displayed form:

```text
SK/PPKS/{YYYY}/{NNN}
```

- `SK/PPKS` is fixed and case-sensitive.
- `YYYY` comes from the application-timezone year of the single server issuance timestamp.
- `NNN` is a global, zero-padded, yearly sequence from `001` through `999`.
- The timestamp used to derive the year is also written to `finalized_at`.
- A year at `999` fails closed; it never emits `1000`, resets, or reuses a number.

## Issuance Lifecycle

A number is issued only on the valid `recorded -> finalized` transition. Draft creation,
draft editing, `draft -> recorded`, GET requests, resources, and frontend rendering never
generate a number.

The transactional lock order is:

```text
Report -> Case -> pending Withdrawal -> Recommendation -> Decision -> DecisionNumberSequence
```

After all workflow and authorization checks pass, the generator creates or finds the yearly
sequence row with `insertOrIgnore`, locks it with `lockForUpdate`, selects the next unused
canonical value, updates the row, saves the Decision, transitions the Case to `decided`,
records one audit event, and queues the assigned-Satgas notification with `afterCommit`.

## Idempotency and Failure

Repeating `status=finalized` against an already finalized Decision returns that Decision
without changing its number, sequence, history, audit, notification, or Case. This includes
legacy finalized Decisions whose number is `null`. Other mutations after finalization remain
forbidden.

Sequence exhaustion and decision-number unique conflicts return deterministic `409` errors:

- `decision_number_sequence_exhausted`
- `decision_number_conflict`

Any failure rolls back number reservation, Decision status/number, `finalized_at`, Case
transition, status history, and audit. Queued notifications are dispatched only after commit.

## Legacy Compatibility

- Existing non-null values remain byte-for-byte unchanged and need not match the new format.
- A non-finalized legacy Decision with an existing number finalizes with that number and does
  not consume a sequence value.
- A finalized legacy Decision with a null number remains null on read and idempotent retry.
- Canonical legacy values already occupying a yearly number are skipped under the locked
  sequence; the legacy row is not rewritten.
- There is no backfill, normalization, or GET-time mutation.

## Authorization and Visibility

Only an active, same-campus `admin` with `cases.record_decision` may create, edit, record, or
finalize a Decision. Super Admin, Satgas, Reporter, inactive Admin, Admin without permission,
and cross-campus Admin cannot mutate it.

Visibility of `decision_number`:

| Actor | Visibility |
|---|---|
| Same-campus Admin with Decision access | Full authorized Decision resource |
| Assigned Satgas with valid Case read access | Existing assigned Decision view |
| Super Admin with oversight read access | Metadata-only projection, including the number |
| Reporter, cross-campus actor, unauthorized actor | No Decision projection |

The Super Admin projection excludes Decision narrative/content, notes, Reporter identity,
victim narrative, evidence, attachments, withdrawal documents, and sensitive recommendation
content. Reporter portal contracts do not contain the formal number.

## API Contract

The existing endpoint remains authoritative:

```http
PATCH /api/v1/decisions/{decision}/status
Content-Type: application/json

{"status":"finalized"}
```

Create, update, and status requests prohibit client-supplied numbering fields, including
`decision_number`, `decision_code`, `formal_decision_code`, `sequence`, `year`,
`nomor_keputusan`, `kode_keputusan`, and `decision_no`. Spoofing returns validation `422`.

## Migration

Migration:

```text
2026_07_24_040000_add_formal_decision_number_sequence.php
```

Before schema mutation, it counts duplicate non-null stored `decision_number` groups. If any
exist, it throws a bounded operational error without including the values and without changing
data or schema.

It then:

- creates `decision_number_sequences(year PK, last_value, timestamps)`;
- adds global unique index `decisions_decision_number_unique`;
- keeps `decisions.decision_number` nullable;
- performs no backfill.

For SQLite tests and PostgreSQL, the additive table and index changes run in one database
transaction after the preflight, so an unexpected index-creation failure does not leave the
sequence table behind.

Rollback drops only the unique index and sequence table. It does not remove or rewrite
`decisions.decision_number`.

## Audit, Notification, and Tests

Actual finalization uses the existing `decision.status_changed` action with safe metadata:
transition, case reference, `decision_number`, outcome/status, and `finalized_at`. Narrative
fields remain excluded. `NOTIF-15` is sent only to active assigned Satgas and may include the
formal number.

Automated coverage uses isolated SQLite test databases for lifecycle, yearly sequence,
timezone boundary, capacity, legacy compatibility, idempotency, rollback, request spoofing,
RBAC/campus isolation, privacy projections, nullable/unique schema behavior, duplicate
preflight, and rollback.

Limitations retained for operational verification:

- true-parallel PostgreSQL contention/race testing has not been run;
- the additive migration has not been run against the PostgreSQL development database;
- frontend coverage is source-contract/unit oriented rather than browser E2E/UAT.
