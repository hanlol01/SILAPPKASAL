# Milestone 2 — Navigation and WebView Security Status

## Status

**Complete for source, automated-test, and Android-build verification.**

Physical-device validation remains part of the Reporter parity QA in Milestone 4.

## Delivered

- A three-way navigation policy:
  - allowed SILAPPKASAL Reporter routes stay in the WebView;
  - supported external `https`, `mailto:`, and `tel:` links are handed to the Android external application handler;
  - all other routes and schemes are prevented.
- The in-WebView allowlist is HTTPS-only, restricted to `silappkasal.web.id`, and permits only `/login`, `/register`, `/track`, `/portal`, and `/portal/*`.
- Android package-visibility declarations for the supported external handlers.
- A neutral error message if an external handler is unavailable or a route is blocked.
- Logout-history hardening:
  - returning to `/login` after a Reporter portal page clears WebView local storage, cache, and cookies;
  - Android Back exits from that logged-out login state instead of returning to a cached portal page;
  - entering `/portal/*` again resets that logged-out state after a new successful web login.
- WebView debugging remains disabled for release builds through `!kReleaseMode`.

## Security boundary

The Flutter shell does not handle credentials, tokens, report data, or backend authorization. It only classifies top-level navigation. The existing website and backend remain responsible for Reporter authentication, authorization, campus scope, and session creation.

`http:`, `file:`, `javascript:`, unknown custom schemes, and internal SILAPPKASAL routes outside the Reporter allowlist are not loaded by the WebView. External sites do not render inside the application; Android opens them in the selected system handler.

## Validation

| Check | Result |
| --- | --- |
| `dart format lib test` | PASS |
| `flutter pub get` | PASS |
| `flutter analyze` | PASS — no issues |
| `flutter test` | PASS — 6 tests |
| `flutter build apk` | PASS |
| `git diff --check` | PASS — no whitespace errors |

The automated tests cover the internal/external/blocked URL classifications and the session-state transitions used to prevent Back navigation to a prior portal session. They do not replace a physical-device logout test.

## Build evidence

- APK: `mobile/build/app/outputs/flutter-apk/app-release.apk`
- Size: 44,993,471 bytes
- SHA-256: `C84CB8380B0A0C9A8F7CDB9BE3A69D67124DF4716212BD903687D6A031EB626B`

The APK continues to use the template debug signing configuration and is for local/internal QA only. Milestone 5 must configure an external, protected release keystore before distribution.

## Deferred work

- Android file chooser, attachment upload, document/PDF, and download behavior (Milestone 3).
- Physical-device tests for external handlers, logout/Back, and the live Reporter journey (Milestone 4).
- Release keystore, release notes, checksum handoff, and signed distribution APK (Milestone 5).
