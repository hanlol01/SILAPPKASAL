# REV-CONTENT-01 C1 Content Domain

Status: backend foundation implemented; not deployed.

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

## Scope

Every category, item, and featured placement has `global` or `campus` scope. Global records have no
university. Campus records require one university. Published reader queries return global content
plus content from the authenticated actor's university only. Management queries and authorization
are separate from reader scoping.

Stable system sections are `education`, `policy`, `faq`, and `consultation`. Campus Admin cannot
create top-level sections or global content. Super Admin may author global content and govern the
domain; a reviewer cannot approve their own campus-authored content.

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
Consultation records require a verification owner and date. Article Consultation CTAs must resolve
through the target's current published pointer to an active, unarchived, scope-safe Consultation
payload at submit and publication time. Reader projection omits a CTA that later becomes inactive or
otherwise ineligible without failing the Article response.

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

Article detail is public-ID-only: `GET /api/v1/content/articles/{publicId}`. Slugs are list metadata,
not an ambiguous detail resolver. Lists and related/fallback projections use public ID as their final
stable ordering key. Expired featured placements are deactivated before replacement, future windows
are not eligible early, and rank remains limited to 1-5.

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

## Cache and future frontend boundary

All authenticated published, management, and attachment responses are private and non-cacheable.
C4 must exclude `/api`, `/dashboard/content`, `/portal/reports`, all Report/Case/Evidence routes,
private attachments, and authenticated management pages from any service-worker response cache.

C2 implements the campus Admin management UI at `/dashboard/content` and the minimum management
read/revision/PDF-removal endpoints documented in `CONTENT_MANAGEMENT.md`. Super Admin review UI,
Reporter Pusat Informasi, PWA manifest/service worker, notification delivery, scheduled publication,
unauthenticated reading, comments, reactions, bookmarks, multilingual bodies, Flutter, and
production deployment remain deferred.

C2 integrity hardening treats `lock_version` as mandatory on submission and revalidates it after
row locking. Archived items are read-only across every Admin mutation; an item with an active
authoring version must be resolved before archive rather than silently retaining an editable draft.
Management identifiers are resolved inside the actor's campus scope before mutation services run.
Private PDF removal deletes storage first inside the guarded operation and removes metadata/audits
only after storage confirms success. Structured documents retain their original JSON node-by-node:
complex supported shapes that the simple editor cannot safely edit are shown read-only and are
serialized unchanged.
