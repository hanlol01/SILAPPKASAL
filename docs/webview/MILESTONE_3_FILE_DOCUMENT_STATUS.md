# Milestone 3 — File and Document Android Integration Status

## Status

**Implementation, automated verification, Android build, and controlled device smoke tests complete. Final Reporter-form confirmation is pending.**

## Delivered

- Web file inputs open a native source sheet with **Ambil foto dengan kamera**, **Pilih dari galeri**, and **Pilih dokumen dari Files** when the input accepts images. PDF-only inputs show Files only.
- Camera capture uses an app-scoped `FileProvider`; it does not request broad storage access or declare direct camera permission.
- Gallery and Files use Android system intents. Only validated `content://` URIs are returned to the WebView callback; raw paths and `file://` URLs are rejected.
- Single and multiple selection are supported where the web input requests them.
- The web input's MIME filters are forwarded to Android where applicable.
- Cancellation returns an empty selection and preserves the current web form.
- Protected browser Blob downloads are transferred through a mobile-only JavaScript channel and saved through Android's `ACTION_CREATE_DOCUMENT` flow.
- Protected PDF previews are written only to app cache and exposed to an Android viewer through the app-scoped `FileProvider`.
- Preview/download compatibility is implemented in the Flutter/Android shell. Backend APIs and the deployed React portal were not changed.

## Security boundaries

- The existing HTTPS host and Reporter-route policy remains active; `blob:`, `file:`, `javascript:`, and unsupported routes are not broadly enabled.
- Document bridge messages are accepted only while the current page is an approved SILAPPKASAL Reporter route.
- Accepted native document types are limited to PDF, JPEG, PNG, and WebP, with a 25 MB mobile bridge limit.
- Transfer identifiers, filenames, MIME types, and byte counts are validated. Filenames are sanitized before cache or save operations.
- Document bytes remain in memory/app cache during handoff and are not logged.
- WebView debugging remains disabled in release builds.

## Validation

| Check | Result |
| --- | --- |
| Regression test before fix | RED — Blob target remained `about:blank`; selected web input remained at `fileCount: 0` |
| `dart format lib test` | PASS |
| `flutter analyze` | PASS — no issues |
| `flutter test` | PASS — 13 tests |
| `flutter build apk --debug` | PASS |
| `flutter build apk` | PASS |
| Release APK clean install/update and launch | PASS on CPH1823, Android 8.1 / API 27 |
| Mobile document bridge installed on approved page | PASS |
| Synthetic protected PDF preview | PASS — Android viewer chooser opened |
| Synthetic protected PDF download | PASS — Android document save picker opened; save was cancelled intentionally |
| Upload source sheet | PASS — Camera, Gallery, and Files options visible |
| Real Reporter web input receives a selected file | PENDING user confirmation on the installed release build |

## Build evidence

- APK: `mobile/build/app/outputs/flutter-apk/app-release.apk`
- Size: 45,585,027 bytes
- SHA-256: `FF5A4F1DCA8940FFF00BAC32F628E5F5D19349C7F5FB2DC9915F5CD048AE8C69`
- Version: `1.0.0+1`

The current release APK still uses the Flutter template's debug signing key. It is suitable for internal QA only and is not the final Milestone 5 signed distribution artifact.

## Remaining physical-device checks

Use approved QA data only. Do not include credentials or report narratives in logs or screenshots.

1. On an actual Reporter evidence input, choose one safe JPG/PNG through Gallery and confirm the web form changes from zero to one selected file.
2. Repeat using a safe PDF through Files.
3. If the form permits multiple files, select multiple files and confirm the web form receives all of them.
4. Test Camera and confirm the captured image appears in the form after returning to the WebView.
5. Trigger the actual **Pratinjau**, **Unduh PDF**, and **Unduh DRAF** actions and confirm the Android chooser/save flow matches the controlled bridge tests.
6. Confirm website type, size, and maximum-file-count validation remains unchanged.

## Decision gate

Proceed to full Milestone 4 Reporter parity QA after the real Reporter input and real protected-document actions are confirmed on the installed APK. Any failure should be recorded with the page/action and visible result; credentials, report content, and document bytes must not be captured.
