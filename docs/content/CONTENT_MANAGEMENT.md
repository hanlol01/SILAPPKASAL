# REV-CONTENT-01 C2-C3 Content Management and Governance

## Scope

Campus Admin uses `/dashboard/content` to author Article, FAQ, and Consultation content for the
Admin's own campus. The page never offers global scope, another campus, top-level section creation,
featured placement, approval, publication, or archive actions. Backend policies and locked services
remain authoritative if a route is called directly.

## Authoring workflow

1. Create a campus draft and choose Article, FAQ, or Consultation.
2. Save the controlled content fields. Article and FAQ bodies are JSON documents, not arbitrary HTML.
3. Optionally attach private PDF files to an editable version.
4. Preview the draft in desktop or mobile width. Preview is always labeled not published.
5. Submit the saved draft to Super Admin. Submitted and in-review versions become read-only.
6. If revision is requested, read the projected review reason, edit the version, save it back to
   `draft`, and resubmit.
7. If content is published, create a new immutable-source revision. The prior published version
   remains visible until the revision is approved and published through the C3 governance workflow.

Rejected content is read-only. A new revision is offered only when the rejected item also has an
existing published version and the backend permits revision creation.

## Editor contract

The Article editor supports paragraph, H2, H3, bold, italic, ordered and unordered list, blockquote,
HTTP/HTTPS/mailto link, information/warning/help callout, and divider nodes. H1, iframe, raw HTML,
tables, video, arbitrary style, and image references are not authorable in C2. FAQ uses the restricted
paragraph/list/blockquote subset. The backend repeats document validation and sanitization.

Consultation uses structured fields. Appointment URLs must use HTTPS and must not carry Report,
registration, identity, or incident information. Emergency availability requires explicit UI
confirmation. No real contact value is seeded or invented by C2.

## Attachments and images

Only general PDF attachments up to 10 MB are enabled. The UI checks extension, MIME, non-empty size,
and size before upload; the backend remains authoritative and verifies signature and private storage.
Resources expose a generated display/download filename, never the original protected filename,
storage path, or checksum. Removal is limited to an editable own-campus version and records the
allowlisted `content.attachment_removed` audit event.

Cover and inline-image controls remain disabled because the audited runtime cannot safely re-encode
and strip metadata. C2 does not bypass the C1 fail-closed image policy.

## Caching and browser state

All management and attachment responses are `private, no-store`. Draft bodies are held only in React
state and TanStack Query memory; C2 does not persist draft content in localStorage. A browser unload
warning and an in-app confirmation protect unsaved changes. Any future service worker must exclude
`/api` and `/dashboard/content`.

## C3 Super Admin governance

Super Admin uses `/dashboard/content-governance`. `Review Konten` contains a server-filtered
editorial queue and a separate published-content governance list. Campus versions are always
read-only in this surface: Super Admin may preview, start review, request revision, reject, approve,
publish, or archive when the server capability permits, but cannot rewrite campus titles, bodies,
contacts, or attachments. Mandatory revision, rejection, and archive narratives are preserved in
the append-only decision history and never copied into audit metadata.

`Konten Global` reuses the controlled C2 editor with scope fixed to `global` and no campus selector.
Global drafts follow the same submit, review, approve, and publish lifecycle. The creator, author,
or last editor cannot review that version, so publication requires a different Super Admin. Direct publication is not
offered by the C3 UI.

`Konten Unggulan` manages ranks 1-5 for eligible published Articles. Placement windows affect only
featured visibility and are not scheduled publication. Update and removal require the opaque
server-projected `concurrency_token`; stale tokens return `409 content_featured_stale`.
Changing the selected Article is recorded distinctly as a replacement audit result.

All governance queries are `private, no-store`. Logout, authentication invalidation, and account
replacement cancel and remove both `content-management` and `content-governance` TanStack queries.

## Deferred

Reporter and Satgas Information Center UI, featured carousel, PWA/service worker, notifications,
image upload, scheduled publication, comments, reactions, bookmarks, and Flutter remain deferred to
C4-C5 or later revisions.
