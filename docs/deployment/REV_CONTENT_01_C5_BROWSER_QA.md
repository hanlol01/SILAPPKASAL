# REV-CONTENT-01 C5 Authenticated Browser QA

Date: 2026-07-22

Status: `BLOCKED`

Production data used: no.

## Why the gate is blocked

The repository contains no Playwright, Cypress, Puppeteer, WebDriver, or equivalent browser harness.
The guarded test suite must use SQLite `:memory:`, while the local disposable PostgreSQL database does
not exist. A persistent disposable authenticated runtime with safe test accounts therefore was not
available. Development PostgreSQL `silappkasal` was not used as a substitute.

The following checks did run:

- frontend content behavior tests: 27 passed;
- TypeScript and ESLint: passed (six existing Fast Refresh warnings);
- client and SSR production build: passed;
- manifest and `/login` preview HTTP smoke: 200;
- source review for permission guards, semantic links, labels, focus classes, reduced motion, 44 px
  controls, URL history, Accordion/Carousel primitives, authenticated PDF lifecycle, and cache cleanup.

These checks are evidence, but they are not a replacement for a rendered authenticated browser run.

## Required account matrix

Use disposable non-production identities and content only.

| Actor | Required state |
|---|---|
| Reporter A | active, own campus, `content.read.published` |
| Reporter B | active, same or separate campus, permission removed |
| Satgas | active, `content.read.published` |
| Campus Admin A | active, campus A authoring permissions |
| Campus Admin B | active, campus B authoring permissions |
| Super Admin Author | active, global authoring/governance permissions |
| Super Admin Reviewer | active, distinct from global author/editor |

Never use real reports, identities, evidence, consultation contacts, or production content.

## Viewport matrix

Repeat the applicable scenarios at 320 px, 360 px, 768 px, 1024 px, and a desktop width of at least
1440 px. Record browser/version, OS, result, screenshot reference, and defect ID.

| Scenario | 320 | 360 | 768 | 1024 | Desktop |
|---|---:|---:|---:|---:|---:|
| Login/logout and account replacement cache clearing | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN |
| Reporter reporting actions remain visible | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN |
| Permission-aware navigation and four shortcuts | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN |
| Featured swipe, arrows, keyboard, and server order | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN |
| Back/Forward restores search/filter/page/view/FAQ | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN |
| Mobile filter Sheet and desktop filter row | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN |
| Select and Accordion keyboard behavior | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN |
| Article card/detail structured rendering | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN |
| Consultation safe actions | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN |
| Authenticated PDF preview/download/popup fallback | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN |
| Duplicate-click and Object URL cleanup | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN |
| Unauthorized direct-route rejection | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN |
| Admin authoring and revision flow | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN |
| Super Admin review, second review, publish, archive | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN |
| Published pointer visibility before/after publication | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN |
| No horizontal overflow, visible focus, 44 px targets | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN |
| Indonesian/English route titles and labels | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN |

## Browser acceptance criteria

- Reporter without permission has no menu/shortcut/featured entry and direct route is denied.
- Reporter/Satgas see only global plus own-campus published-pointer records.
- Admin sees only own-campus management and cannot review/publish/global-author/cross-campus mutate.
- Super Admin cannot rewrite campus bodies and cannot review a version they authored or edited.
- Draft, submitted, in-review, revision-requested, rejected, approved-only, future, archived, and
  other-campus reader records remain absent.
- Every target viewport has no horizontal overflow and all keyboard/focus/touch requirements pass.
- Private PDF requests use bearer-authenticated fetch, never a tokenized URL, and leave no persistent
  Blob storage.
