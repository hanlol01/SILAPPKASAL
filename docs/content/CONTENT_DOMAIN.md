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

FAQ answers use the same restricted document pipeline without Article-only headings, callouts,
images, or dividers. Consultation contacts use typed fields. Telephone and WhatsApp display values
allow common separators, but normalized values contain digits and an optional leading plus while
preserving a leading zero. Appointment links must be HTTPS and may not carry Report, Case,
registration, tracking, identity, incident, NIM, email, or telephone query data. Published
Consultation records require a verification owner and date.

## Attachments

Attachments use the private `content` filesystem disk. Cover images accept JPG/JPEG/PNG/WebP up to
5 MB. Other attachments and inline image references accept PDF/JPG/JPEG/PNG up to 10 MB. The backend
validates non-empty size, extension, detected MIME, PDF/image signature, and image dimensions before
storage. Responses never serialize private path, checksum, internal IDs, or protected original name.
Downloads require authorization, are audited, use a safe filename, and send `private, no-store` and
`X-Content-Type-Options: nosniff`.

The audited local PHP environment has `fileinfo` and `getimagesize`, but no GD, Imagick, or EXIF
extension. C1 therefore does not claim orientation correction, metadata stripping, re-encoding, or
derivative generation. Intervention Image was intentionally not added because no compatible image
driver is available. Production must either install and verify an approved driver before enabling
such processing or retain the documented validation-only behavior.

## Seeder boundary

`ContentFoundationSeeder` creates four sections, ten storyboard categories, 41 global Article
drafts, and eight global FAQ drafts using stable keys. Seeded versions require editorial review,
have no published pointer, and are not overwritten on rerun. Article bodies and FAQ answers are not
fabricated. No Consultation contact is seeded.

## Cache and future frontend boundary

All authenticated published, management, and attachment responses are private and non-cacheable.
C4 must exclude `/api`, `/dashboard/content`, `/portal/reports`, all Report/Case/Evidence routes,
private attachments, and authenticated management pages from any service-worker response cache.

C1 does not implement Admin management UI, Super Admin review UI, Reporter Pusat Informasi, PWA
manifest/service worker, notification delivery, scheduled publication, unauthenticated reading,
comments, reactions, bookmarks, multilingual bodies, Flutter, or production deployment.
