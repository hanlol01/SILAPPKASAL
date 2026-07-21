# REV-CONTENT-01 C2 Campus Content Management

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
   remains visible until the revision is approved and published by the later C3 workflow.

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

## Deferred

Super Admin review/approval/rejection UI, featured-content governance, Reporter and Satgas reader UI,
PWA/service worker, notifications, image upload, scheduled publication, comments, reactions,
bookmarks, and Flutter remain deferred to C3-C5 or later revisions.
