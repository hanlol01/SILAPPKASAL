# SILAPPKASAL Android Reporter WebView — Implementation Plan

## 1. Document purpose

This is the implementation handoff for the first mobile delivery of SILAPPKASAL. It is separate from the native-mobile PRD and design/mockup documents.

The goal is a fast Android APK named **SILAPPKASAL** that exposes the existing Reporter web portal through a secure, reliable WebView. It must reuse the current production web UI, backend APIs, validation, and business workflows; it must not rewrite the Reporter portal in Flutter.

## 2. Locked product decisions

| Decision | Value |
| --- | --- |
| App display name | `SILAPPKASAL` |
| First platform | Android only |
| Distribution | Direct APK; no Play Store in this phase |
| Android application ID | `id.silappkasal.app` |
| Initial portal URL | `https://silappkasal.web.id/login` |
| Supported role | Reporter/Pelapor only |
| Source of product behavior | Existing production web Reporter portal |
| Native mockup/UI rewrite | Out of scope for phase 1 |

## 3. Scope

### In scope

- A Flutter Android application and releaseable APK.
- Splash/loading shell followed by WebView loading the existing website.
- Public web routes: `/login`, `/register`, `/track`.
- Reporter portal routes under `/portal/*`.
- Web login/session/logout behavior as provided by the website.
- Android Back behavior, loading progress, network error state, and retry.
- File chooser support for reporter evidence uploads from Android files/gallery/camera where supported by the webpage and Android WebView.
- Safe handling of document previews/downloads and external links.
- Navigation protections that prevent the WebView from becoming a general-purpose browser or exposing non-Reporter internal routes.
- QA and reproducible instructions to build a debug APK and signed release APK.

### Out of scope

- Rebuilding Reporter screens, forms, APIs, or validation in Flutter.
- Native push notifications, biometric lock, offline data, background sync, camera-native evidence capture, or local report storage.
- iOS, Play Store submission, analytics, crash reporting, or multi-role staff dashboards.
- Changing the Laravel backend or React web portal solely for the APK, unless a verified WebView incompatibility requires a separately approved fix.

## 4. Functional behavior

### 4.1 Startup and route entry

1. Start on `https://silappkasal.web.id/login`.
2. Preserve the web application’s existing Login, Register, and Anonymous Tracking flows.
3. After web login, allow navigation to Reporter pages under `/portal/*`.
4. The app does not provide a native duplicate login form or store credentials itself.

### 4.2 Allowed navigation

The WebView may load HTTPS URLs on `silappkasal.web.id` for these paths:

- `/login`
- `/register`
- `/track`
- `/portal` and `/portal/*`
- Static/application assets required by those pages.

Navigation to a known internal dashboard path, unsupported host, insecure `http://` URL, `javascript:` URL, `file:` URL, or unknown deep link must be blocked or safely handled. The application should redirect a blocked internal SILAPPKASAL route back to `/login` or display a neutral “Halaman tidak tersedia pada aplikasi Reporter” state.

### 4.3 Android Back

- If the WebView has history, go back inside the WebView.
- If at the entry page with no history, show the standard Android exit behavior (double-back confirmation or app exit; choose one and keep it consistent).
- Do not accidentally return to internal-role pages retained in history.

### 4.4 Loading and failure states

- Show a branded startup/loading view until the first portal page finishes loading.
- Show a compact in-app progress indicator during navigation.
- For no connection, SSL/navigation error, timeout, or unavailable host: show a clear Indonesian error state, Retry button, and return-to-login action.
- Do not show raw server stack traces, tokens, URLs with sensitive query values, or WebView error codes to users.

### 4.5 Files, downloads, and external links

- Web file inputs used by Reporter evidence flows must open Android’s chooser and return the selected file(s) to WebView.
- Do not silently request camera/media permissions. Request only when a user chooses the relevant source and Android requires it.
- PDF/document preview and download behavior must be verified on target Android devices. If an URL is a downloadable attachment or external URL, pass it to a trusted Android handler/browser after an explicit user action.
- `mailto:`, `tel:`, WhatsApp, and other external-intent links must open a suitable installed handler, not render a blank WebView page.

## 5. Security and privacy requirements

Reporter data is sensitive. The APK must meet these minimum constraints:

- Enable JavaScript only because the React portal requires it; do not add arbitrary JavaScript bridge methods.
- Do not inject credentials, bearer tokens, QA credentials, or test data into source code, configuration, screenshots, or logs.
- Keep WebView storage/cookies only as required by the web session; clear it on web logout when supported by the site.
- Enforce HTTPS and restrict top-level navigation to the approved production host.
- Disable debugging/remote inspection in release builds.
- Avoid local caching, screenshots, analytics payloads, or crash reports that contain report narratives, attachments, or personal data.
- The Flutter shell must not bypass backend authorization. The web backend remains the authority for roles, permissions, campus scoping, and report access.
- The app should set Android’s secure-screen flag where feasible to reduce screenshots/app-switcher exposure; validate usability with the product owner before making it mandatory.

## 6. Technical approach

### 6.1 Project placement

The current `mobile/` directory contains only a placeholder README. Create the Flutter project there when implementation begins; do not overwrite unrelated repository areas.

Suggested structure:

```text
mobile/
  android/
  lib/
    main.dart
    app.dart
    features/web_portal/
      reporter_webview_page.dart
      navigation_policy.dart
      webview_error_view.dart
  assets/
    branding/
  pubspec.yaml
  README.md
```

### 6.2 Packages

Use a maintained Flutter WebView package compatible with the selected Flutter SDK—normally `webview_flutter` with its Android implementation. Add only packages proven necessary for:

- Opening external Android intents/documents.
- Handling downloads/file selection that cannot be supported through the base WebView API.
- Runtime permissions only when Android/version-specific file or camera behavior requires them.

Package versions must be selected from the current Flutter SDK compatibility at implementation time. Do not add a package merely for a future native feature.

### 6.3 Configuration

- Android minimum/target SDK: choose a currently supported Flutter/Android combination at implementation time.
- Internet permission is required.
- App label: `SILAPPKASAL`.
- Application ID: `id.silappkasal.app`.
- Production base URL must be centralized in one non-secret build configuration value, defaulting to `https://silappkasal.web.id`.
- Debug builds may permit an explicitly supplied local/staging URL only; release builds must not silently point away from production.

## 7. Milestones

### Milestone 1 — WebView foundation

Deliverables:

- Flutter project in `mobile/`.
- Android app name/application ID configuration.
- Branded startup view.
- Reporter WebView opens Login and can navigate through the current portal.
- Back navigation and loading progress.
- Approved-host/navigation policy.
- Debug APK build instructions.

Acceptance:

- APK installs on a physical Android device.
- Login, Register, Track, and Reporter Portal open exactly as the website does.
- No native recreation of web screens exists.

### Milestone 2 — Web capability integration

Deliverables:

- Safe external-link dispatch.
- File chooser support for web evidence upload.
- Document/PDF/download behavior appropriate for Android.
- Network error/retry view.
- Release-safe WebView settings.

Acceptance:

- A QA user can select a permitted file in the existing web form.
- Document actions behave predictably and never produce an unhandled blank page.
- Unsupported/untrusted navigation is blocked.

### Milestone 3 — Reporter parity QA and APK handoff

Deliverables:

- Physical-device QA on at least one supported Android version.
- Reporter-only route/role smoke tests.
- Build/release instructions and APK checksum/version record.
- Known limitations list.

Acceptance:

- The web Reporter journey is usable in the APK: Login → Register/Track where applicable → Portal → Reports → Details → Information Center → Account/logout.
- No internal dashboard can be reached through normal app navigation.
- APK is generated and installs cleanly.

## 8. QA checklist

Use only approved QA data. Do not place account credentials in this document.

### Public access

- [ ] Open app from clean install.
- [ ] Login page loads.
- [ ] Register page loads; university and study-program dropdowns work.
- [ ] Anonymous tracking page loads and validation message is readable.

### Reporter portal

- [ ] Valid Reporter login loads `/portal`.
- [ ] Ringkasan, Buat Pengaduan, Pengaduan Saya, Pusat Informasi, Notifikasi, and Akun navigate successfully.
- [ ] Existing report detail renders without desktop-only clipping.
- [ ] Logout returns to Login and does not expose the previous page on Back.

### Android integration

- [ ] Back behavior is correct at root and nested pages.
- [ ] Slow/no-network error and Retry are understandable.
- [ ] Upload chooser opens and cancellation returns safely to the web form.
- [ ] Preview/download/external links do not dead-end inside WebView.
- [ ] Rotation, app background/foreground, and process restore do not corrupt the session.

### Security

- [ ] Release build has WebView debugging disabled.
- [ ] Non-HTTPS/unknown-host links are blocked.
- [ ] Internal-role URL attempts are blocked/handled.
- [ ] Logs and generated APK artifacts contain no credentials or report content.

## 9. Build and handoff expectations

The final deliverable is an APK, accompanied by:

- Version name and version code.
- Build date and Git commit reference.
- SHA-256 checksum of the APK.
- Minimum Android version tested.
- Brief QA result/known-limitation note.
- Secure transfer location for the APK; do not commit APK binaries to the repository unless the repository’s release process explicitly requires it.

## 10. Explicit non-goals and future upgrade path

This WebView app is an expedient first delivery. The existing documents under `docs/MOBILE_REPORTER_*.md` remain the reference if/when SILAPPKASAL moves to a native Flutter Reporter application. The backend contracts and product behaviors must stay compatible with both delivery modes.

