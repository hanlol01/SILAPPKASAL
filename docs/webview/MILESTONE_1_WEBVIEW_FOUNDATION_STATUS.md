# Milestone 1 — WebView Foundation Status

## Status

**Complete for implementation and build verification.**

## Delivered

- Flutter app entry and dark SILAPPKASAL shell.
- Production Reporter portal entry at `https://silappkasal.web.id/login`.
- Official Flutter WebView dependencies:
  - `webview_flutter` 4.14.1
  - `webview_flutter_android` 4.14.0
- Android Internet permission and Android API 24 minimum SDK, matching the WebView package support requirement.
- Portal navigation policy that permits HTTPS only on `silappkasal.web.id` for `/login`, `/register`, `/track`, `/portal`, and `/portal/*`.
- Rejected navigation shows a neutral in-app message rather than opening an unrestricted browser destination.
- Loading progress indicator for web navigation.
- Main-frame network error screen with Retry action.
- Android Back behavior: return through WebView history, otherwise exit the app.
- WebView debugging enabled only outside release builds.

## Validation

| Check | Result |
| --- | --- |
| `flutter pub get` | PASS |
| `flutter analyze` | PASS |
| `flutter test` | PASS — 3 tests |
| `flutter build apk` | PASS |

## Build evidence

- APK: `mobile/build/app/outputs/flutter-apk/app-release.apk`
- Size: 44,959,490 bytes
- SHA-256: `0AD862FDE44A7E694F1328FB6EC0A484FA84557FFB7E46FF943D98D7CB087606`

## Windows/Kotlin build note

The first WebView build exposed a Kotlin incremental-cache failure because the project is on `D:` while the Pub package cache is on `C:`. `mobile/android/gradle.properties` now sets `kotlin.incremental=false`, a documented Kotlin JVM build fallback. The clean rebuild produced the APK above. This trades incremental Kotlin build speed for reliable Windows builds across drive roots.

## Deliberately deferred to Milestone 2

- Android file chooser and camera/gallery behavior for evidence uploads.
- PDF/document download and preview handling.
- `mailto:`, `tel:`, WhatsApp, and other external Android intents.
- Logout/history hardening and final route-policy QA on a physical device.
- Physical-device testing of the live website.

## Notes for test installation

The generated APK is still built with the placeholder debug signing configuration defined by the Flutter Android template. It is suitable for local QA only. A protected release keystore will be configured before any internal distribution in Milestone 5.

