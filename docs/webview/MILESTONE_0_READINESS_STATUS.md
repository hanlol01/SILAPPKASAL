# Milestone 0 — Readiness Status

## Status

**Complete. The actual SILAPPKASAL Flutter project builds successfully as an Android APK.**

## Verified on 26 August 2026

| Check | Result |
| --- | --- |
| Flutter SDK | PASS — Flutter stable 3.47.1, Dart 3.13.1. |
| Android SDK | PASS — Android SDK found at the configured user SDK location; Android platform/build tools and emulator detected. |
| Android Studio JDK | PASS — bundled JDK detected by Flutter. |
| Network dependencies | PASS — Flutter can resolve package dependencies. |
| Flutter project | PASS — created in `mobile/`. |
| Application label | PASS — `SILAPPKASAL`. |
| Application ID / namespace | PASS — `id.silappkasal.app`. |
| Static analysis | PASS — `flutter analyze`. |
| Flutter scaffold test | PASS — `flutter test`. |
| Android project build | PASS — `flutter build apk` completed successfully. |

## Created baseline

- Flutter Android project in `mobile/`.
- Android manifest and Gradle identity aligned to `id.silappkasal.app`.
- Minimal native activity package aligned to the same namespace.
- Updated mobile README with scope, prerequisites, and local commands.

No WebView behavior, website credentials, QA data, backend integration, or APK artifact has been added in this milestone.

## Build evidence

- Command: `flutter build apk`
- Output: `mobile/build/app/outputs/flutter-apk/app-release.apk`
- Size: 44,103,336 bytes
- SHA-256: `240298BFA51DFA0804497C3C3EC72A27E5B94EBE0AD335DBA4041A406C9FB6B2`

The build result is the relevant Android environment gate. On the installed Android CLI version, `flutter doctor --android-licenses` is no longer required.

The generated APK uses Flutter's current placeholder release-signing configuration (debug key). It proves the toolchain and project build, but it is **not** the final signed distribution APK. Milestone 5 will configure a non-repository release keystore before internal distribution.

## Next milestone

Milestone 1 will install/configure the maintained Flutter WebView dependency and add the Reporter WebView shell: HTTPS portal entry, loading state, safe Android Back behavior, and a debug APK smoke build.
