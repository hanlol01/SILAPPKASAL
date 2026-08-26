# SILAPPKASAL Android

Android-only Flutter shell for the SILAPPKASAL Reporter portal. The first release is a WebView APK; it reuses the existing web Reporter experience instead of duplicating portal screens or backend logic in Flutter.

## Configuration

- Application label: `SILAPPKASAL`
- Application ID: `id.silappkasal.app`
- Initial portal URL: `https://silappkasal.web.id/login`
- Scope: Reporter only — Login, Register, Anonymous Tracking, and `/portal/*`

## Current implementation state

Milestones 0–2 are complete. Milestone 3 now includes a native Android source chooser (Camera, Gallery, and Files), secure `content://` upload handoff, and protected Blob document preview/download support. Automated tests, APK builds, and Android 8.1 device smoke checks pass; an actual Reporter form selection still requires final user confirmation. See the WebView status documents in `docs/webview/` for exact evidence and remaining QA gates.

## Local prerequisites

1. Flutter stable SDK and Android SDK/Android Studio.
2. Confirm the toolchain:

   ```powershell
   flutter doctor -v
   ```

   On the installed current Android CLI, `flutter doctor --android-licenses` is no longer required.

## Commands

```powershell
cd mobile
flutter pub get
flutter analyze
flutter test
flutter build apk
```

The local QA APK is written to `build/app/outputs/flutter-apk/app-release.apk`. It uses the Flutter template's debug signing configuration and must not be treated as the final signed distribution APK. Do not commit APK binaries, keystores, credentials, or report data.

## References

- `docs/webview/ANDROID_REPORTER_WEBVIEW_IMPLEMENTATION_PLAN.md`
- `docs/webview/ROADMAP_ANDROID_REPORTER_WEBVIEW.md`
- `docs/webview/MILESTONE_1_WEBVIEW_FOUNDATION_STATUS.md`
- `docs/webview/MILESTONE_2_NAVIGATION_SECURITY_STATUS.md`
- `docs/webview/MILESTONE_3_FILE_DOCUMENT_STATUS.md`
