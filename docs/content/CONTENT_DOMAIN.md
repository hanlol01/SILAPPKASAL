# REV-CONTENT-01 C1 Content Domain

Status: C1-C3 publication, authoring, and governance implemented; not deployed.

## Aggregate and lifecycle

`content_items` is the stable identity shared by Article, FAQ, and Consultation. Authoring and
publication state belongs to `content_versions`; type-specific bodies belong to exactly one of
`article_version_contents`, `faq_version_contents`, or `consultation_version_contents`.

The supported lifecycle is:

```text
draft -> submitted -> in_review -> approved -> published
                         |-> revision_requested -> draft/edit/resubmit
                         |-> rejected
published item -> archived
```

Reader visibility is controlled only by `content_items.published_version_id`. A status string is
not sufficient. Publishing replaces this pointer atomically. The prior published version remains
the reader version while a revision is draft, submitted, in review, revision-requested, or
rejected. Published versions are immutable. Review decisions are append-only and narrative reasons
are encrypted.

Primary editorial attribution uses the existing item creator and append-only review-decision actor,
plus nullable version-level submitter and publisher relations. Submission and publication write
their actor foreign key in the same transaction as the lifecycle timestamp and audit event. Legacy
versions may legitimately project no actor; the system never guesses from the current user.
Resubmission updates the current version's submitter while every prior submission remains an actual,
ordered audit event.

## Scope

Every category, item, and featured placement has `global` or `campus` scope. Global records have no
university. Campus records require one university. Published reader queries return global content
plus content from the authenticated actor's university only. Management queries and authorization
are separate from reader scoping.

Stable system sections are `education`, `policy`, `faq`, and `consultation`. Campus Admin cannot
create top-level sections or global content. Super Admin may author global content and govern the
domain; a reviewer cannot approve their own campus-authored content.

Campus Admin draft creation accepts only the actor's campus and Campus scope inside the locked
domain service; foreign-campus or Global input fails closed. Global content always has a null
university, applies to every campus, and is authored only
by Super Admin. Saving a Global draft never publishes it; submit, review, approval, and publication
remain distinct explicit transitions under the existing separation-of-duties policy.

## Structured content and contacts

Article bodies use a controlled JSON document with paragraphs, H2/H3, bold, italic, ordered and
unordered lists, blockquotes, allowlisted links, information/warning/help callouts, attachment image
references, and dividers. H1 and unsupported nodes or marks are rejected. Server rendering is passed
through Symfony HtmlSanitizer. The searchable plain-text derivative and reading time are computed
server-side.

The server rejects a structured document before rendering when serialized JSON exceeds 500,000
bytes, a text node exceeds 20,000 characters, total searchable text exceeds 200,000 characters,
the document exceeds 1,000 nodes or depth 12, a text node has more than four marks, total marks
exceed 2,000, or a link exceeds 2,048 characters.

FAQ answers use the same restricted document pipeline without Article-only headings, callouts,
images, or dividers. Consultation contacts use typed fields. Telephone and WhatsApp display values
allow common separators, but normalized values contain digits and an optional leading plus while
preserving a leading zero. Appointment links must be HTTPS and may not carry Report, Case,
registration, tracking, identity, incident, NIM, email, or telephone query data. Published
Consultation records require a verification owner and date. Consultation is a standalone
Information Center destination; per-Article Consultation CTA input, validation, eager loading, and
reader projection are no longer used. The nullable legacy relation remains in the schema only so
historical rows stay readable.

## Attachments

Attachments use the private `content` filesystem disk. The current default is fail-closed for every
JPG/JPEG/PNG/WebP upload because the audited runtime has no verified metadata-stripping image
re-encoder. `CONTENT_IMAGE_UPLOADS_ENABLED=true` is insufficient by itself: a runtime-available
`ContentImageProcessor` implementation must also safely normalize orientation, strip metadata, and
re-encode the image. Until that implementation exists, only PDF general attachments up to 10 MB are
accepted; cover and inline-image uploads are unavailable.

Responses never serialize private path, checksum, internal IDs, or protected original name.
Reader-facing names are generated from purpose and attachment public ID, for example
`lampiran-{public_id}.pdf`; client filenames never enter `Content-Disposition`. Downloads revalidate
authorization, send `private, no-store` and `X-Content-Type-Options: nosniff`, and record
`content.attachment_download_authorized` after authorization and storage existence checks. The event
means response preparation was authorized, not that the client received every streamed byte.

Creating a revision clones authorized private attachment bytes to new UUID paths, verifies size and
SHA-256, drops protected original-name metadata, rewrites structured image UUIDs, and rewrites the
cover foreign key. Any failure rolls back database rows and deletes newly copied files. Submit and
publish revalidate that all attachment bytes exist and that cover/inline purposes and version
ownership match their use.

## Database integrity and deterministic readers

Repair migration `2026_07_21_020000_harden_content_publication_constraints.php` adds explicit
scope/university, content-type, lifecycle, featured-rank, and featured-window constraints. PostgreSQL
uses named CHECK constraints; SQLite tests use equivalent named insert/update triggers. Version
pointer existence remains protected by foreign keys. Pointer ownership is additionally enforced by
locked publication transactions and by reader joins requiring version/item ownership.

Article detail uses the section-aware route
`GET /api/v1/content/articles/slug/{education|policy}/{slug}`. The database query constrains the
section before resolving own-campus versus global precedence, so equal slugs in Education and Policy
cannot cross-resolve. The legacy public-ID reader remains a compatibility endpoint but is not used
by the Information Center UI. Lists and related/fallback projections use public ID as their final
stable ordering key. Expired featured placements are deactivated before replacement, future windows
are not eligible early, and rank remains limited to 1-5.

Article version `category_name` is canonical free text. The registry normalizes category identity with NFC
when the runtime supports it, trimmed and collapsed whitespace, and lowercase comparison. A database
unique constraint covers `section_id + scope_key + normalized_name` to close concurrent duplicate
creation within one global or campus scope. The same normalized name is allowed between Global and
Campus and between different campuses; the outcome does not depend on which scope creates it first.
Legacy version `category_id` is consulted only when that version's `category_name IS NULL`. On create
and draft/revision updates, a non-null canonical name clears any stale legacy relation, including
when an unrelated field is edited. Management projects the editable/latest version; public readers,
filters, category lists, related results, and featured cards project the published pointer version.
Category edits are metadata changes and are not part of immutable section/scope placement. A draft
revision therefore cannot change the published category, title, or body until publication moves the
pointer. Item-level category fields remain only for compatibility and denormalized synchronization.

Registry usage is pointer-aware: each item is counted once for a category found on its active current
draft and once for a different category on its active published version. Thus published A plus draft
B protects both registry names; after B is published and the draft pointer is cleared, A is no longer
counted for that item. Stale item-level category metadata is never part of this calculation.

## Test database isolation

The default PHPUnit configuration force-overrides inherited database credentials with
`APP_ENV=testing`, SQLite `:memory:`, and array/sync test drivers. Application startup and the base
test case use `TestDatabaseGuard`: only SQLite `:memory:` or local PostgreSQL `silappkasal_test` with
`TEST_DATABASE_CONFIRM=silappkasal_test` is accepted. `.env.testing` remains ignored; the tracked
`.env.testing.example` contains no secrets. `silappkasal` must never be used for automated tests or
destructive verification.

The base test case runs the guard immediately after `refreshApplication()` bootstraps Laravel and
before Laravel calls `setUpTraits()`. Therefore `RefreshDatabase`, `DatabaseMigrations`, database
truncation, and transaction setup cannot run first. A stale configuration cache is not trusted: if
the effective cached environment or database resolves outside the allowlist, test setup fails
without clearing or rewriting the developer's cache. Use `composer test` when a deliberate
pre-test `config:clear` is required; direct PHPUnit/Artisan test runs remain fail-closed.

## Seeder boundary

`ContentFoundationSeeder` creates four sections, ten storyboard categories, 41 global Article
drafts, and eight global FAQ drafts using stable keys. Seeded versions require editorial review,
have no published pointer, and are not overwritten on rerun. Article bodies and FAQ answers are not
fabricated. No Consultation contact is seeded.

## Cache and reader frontend boundary

All authenticated published, management, and attachment responses are private and non-cacheable.
C4 adds no service worker and therefore creates no offline response cache. The web manifest starts at
the non-content `/login` shell. Any future service worker must exclude `/api`, `/dashboard/content`,
`/portal/reports`, all Report/Case/Evidence routes, private attachments, authenticated reader pages,
and authenticated management pages.

C2 implements the campus Admin management UI at `/dashboard/content`. The Admin/Super Admin
Information Center directory is `/dashboard/information-center`, with dedicated Education, Policy,
FAQ, and Consultation routes. C3 implements Super Admin
review, distinct approval/publication, global authoring, archive, decision history, and featured
placement governance at `/dashboard/content-governance`, as documented in `CONTENT_MANAGEMENT.md`.
Reporter/Satgas Pusat Informasi, the featured carousel, authenticated Article/FAQ/Consultation reader,
and a manifest-only app-shell foundation are implemented in C4. Service-worker caching, notification
delivery, scheduled publication, unauthenticated reading, comments, reactions, bookmarks,
multilingual bodies, Flutter, and production deployment remain deferred.

C2 integrity hardening treats `lock_version` as mandatory on submission and revalidates it after
row locking. Archived items are read-only across every Admin mutation; an item with an active
authoring version must be resolved before archive rather than silently retaining an editable draft.
Management identifiers are resolved inside the actor's campus scope before mutation services run.
Private PDF removal deletes storage first inside the guarded operation and removes metadata/audits
only after storage confirms success. Structured documents retain their original JSON node-by-node:
complex supported shapes that the simple editor cannot safely edit are shown read-only and are
serialized unchanged.

## C3 editorial and featured concurrency

Editorial mutations lock and reload actor, version, and item, then require the current item
`lock_version`. Stale review, invalid lifecycle, archived state, and active-authoring conflicts use
stable 409 codes. Approval reruns the controlled-document, CTA, attachment, scope, and publication
checks; publication is a separate locked transition that atomically advances the published pointer.
The executable domain has no editable-version or direct-global publication path. Global items must
pass submission and review by a Super Admin who is not the creator, author, or last editor; only that
exact authoritative approved version may be published.

The review queue contains only submitted, in-review, and approved authoring versions. Published
content is exposed through a separate governance query so archive operations do not redefine queue
eligibility. The published query always projects `publishedVersion`, even while another version is
being authored or has been rejected, and never substitutes `latestVersion`. Review detail combines
authoritative audit actions with encrypted append-only review decisions. Timeline and attribution
ordering use immutable IDs as a secondary key whenever timestamps match. History is bounded to the
200 latest actual audit events.

Campus Admin management resources project creator/submitter name/email/role and stage timestamps,
but never reviewer, approver, or publisher identities. Central timeline actors are replaced with a
generic central-team label. Super Admin governance resources may project all five identities.
Reviewer identity is restricted to review-start/revision/rejection decisions, approval identity to
approval decisions, and publisher identity to the version publisher foreign key. Paginated lists
eager load only deterministic one-of-many decision relations rather than the full decision history.
Responses remain `private, no-store`. Reporter/public resources project none of those internal actor
fields or the editorial timeline.

Featured placements use an opaque concurrency token derived from the locked persisted placement
state. This avoids timestamp-resolution races without a schema change. The backend independently
resolves the Article, scope, campus, publication pointer, archive state, rank, and active window;
frontend titles or publication claims are never trusted.
