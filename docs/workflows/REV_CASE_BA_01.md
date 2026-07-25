# REV-CASE-BA-01 — Case Berita Acara

## Scope

REV-CASE-BA-01 adds a versioned, structured Berita Acara (Case Minutes) aggregate owned by a Case.
It does not change Case/Report lifecycle transitions, assignments, withdrawal handling, decisions,
final summaries, evidence, or media. It deliberately excludes PDF generation, signature, upload,
institutional numbering, download, public URL, and Reporter access.

## Lifecycle

1. An authorized actor creates one `draft` for an eligible Case.
2. A draft may be updated with its opaque lock token.
3. An active same-campus Admin finalizes a complete draft.
4. An authorized Admin or assigned Satgas may create a revision only from the sole active finalized
   version. The revision is a cloned new draft.
5. Finalizing that revision changes the previously finalized version to `superseded` in the same
   transaction. No BA version is overwritten or deleted.

The Case itself remains eligible only in `investigation` or `recommendation`. The shared Case mutation
guard still rejects a pending formal withdrawal or terminal Case before any BA row is locked.

## Privacy and projection

- Internal projection: authorized Campus Admin and actively assigned Satgas receive internal and
  anonymized narratives plus the minimal actor/version references needed for work.
- Metadata projection: Super Admin receives only BA public ID, safe Case/campus reference, version,
  status, and timestamps. It never receives any BA narrative, actor identity, Case report data,
  evidence, storage information, or mutation capability.
- Reporter receives neither endpoint nor BA field in portal/Case resources.
- Four narrative fields are encrypted at rest. `anonymized_summary` is authored separately; server
  finalization rejects direct available Reporter identifiers, but this safeguard is not an automated
  anonymization claim.

## Operations

The only migration is `2026_07_24_050000_create_case_minutes_table.php`; it is additive, has no
backfill, and rolls back only its table. The normal deployment step must run the idempotent RBAC and
master-data seeders so `case_minutes.*` permissions and `NOTIF-30` are present. This workflow document
does not claim any environment migration has been executed.
