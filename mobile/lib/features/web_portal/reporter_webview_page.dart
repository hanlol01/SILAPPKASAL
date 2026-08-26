import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:webview_flutter_android/webview_flutter_android.dart';

import 'mobile_document_bridge.dart';
import 'native_portal_bridge.dart';
import 'navigation_policy.dart';
import 'portal_session_navigation_state.dart';
import 'webview_error_view.dart';

class ReporterWebViewPage extends StatefulWidget {
  const ReporterWebViewPage({super.key});

  @override
  State<ReporterWebViewPage> createState() => _ReporterWebViewPageState();
}

class _ReporterWebViewPageState extends State<ReporterWebViewPage> {
  static const _policy = PortalNavigationPolicy();

  late final WebViewController _controller;
  late final MobileDocumentTransferManager _documentTransferManager;
  final NativePortalBridge _nativeBridge = const NativePortalBridge();
  final WebViewCookieManager _cookieManager = WebViewCookieManager();
  final PortalSessionNavigationState _sessionState =
      PortalSessionNavigationState();
  Uri _currentUri = _policy.entryUri;
  int _progress = 0;
  bool _isLoading = true;
  bool _hasLoadError = false;

  @override
  void initState() {
    super.initState();
    AndroidWebViewController.enableDebugging(!kReleaseMode);
    _documentTransferManager = MobileDocumentTransferManager(
      onTransfer: _handleDocumentTransfer,
      onError: _showDocumentTransferError,
    );
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0xFF071521))
      ..addJavaScriptChannel(
        'MobileDocumentBridge',
        onMessageReceived: (message) {
          unawaited(
            _documentTransferManager.handleMessage(
              message.message,
              trustedPage: _policy.isAllowed(_currentUri),
            ),
          );
        },
      )
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: _onNavigationRequest,
          onPageStarted: _onPageStarted,
          onPageFinished: _onPageFinished,
          onProgress: _onProgress,
          onWebResourceError: _onWebResourceError,
        ),
      );
    unawaited(_initializeWebView());
  }

  Future<void> _initializeWebView() async {
    final platformController = _controller.platform;
    if (platformController is AndroidWebViewController) {
      await platformController.setAllowFileAccess(false);
      await platformController.setAllowContentAccess(true);
      await platformController.setOnShowFileSelector(_selectFilesForWebForm);
    }

    await _controller.loadRequest(_currentUri);
  }

  Future<List<String>> _selectFilesForWebForm(FileSelectorParams params) async {
    final source = await _chooseUploadSource(params.acceptTypes);
    if (source == null) {
      return const <String>[];
    }

    try {
      return await _nativeBridge.pickFiles(
        source: source,
        allowMultiple: params.mode == FileSelectorMode.openMultiple,
        acceptedMimeTypes: normalizedAcceptedMimeTypes(params.acceptTypes),
      );
    } on PlatformException {
      _showFileSelectorError();
    }

    return const <String>[];
  }

  Future<NativeUploadSource?> _chooseUploadSource(
    List<String> acceptTypes,
  ) async {
    if (!mounted) {
      return null;
    }

    final allowImages = acceptsImageUpload(acceptTypes);
    return showModalBottomSheet<NativeUploadSource>(
      context: context,
      useSafeArea: true,
      showDragHandle: true,
      builder: (context) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const ListTile(
              title: Text('Pilih sumber file'),
              subtitle: Text(
                'Gunakan kamera, galeri, atau penyimpanan perangkat.',
              ),
            ),
            if (allowImages)
              ListTile(
                leading: const Icon(Icons.camera_alt_outlined),
                title: const Text('Ambil foto dengan kamera'),
                onTap: () => Navigator.pop(context, NativeUploadSource.camera),
              ),
            if (allowImages)
              ListTile(
                leading: const Icon(Icons.photo_library_outlined),
                title: const Text('Pilih dari galeri'),
                onTap: () => Navigator.pop(context, NativeUploadSource.gallery),
              ),
            ListTile(
              leading: const Icon(Icons.folder_open_outlined),
              title: const Text('Pilih dokumen dari Files'),
              onTap: () => Navigator.pop(context, NativeUploadSource.files),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _handleDocumentTransfer(MobileDocumentTransfer transfer) async {
    try {
      final completed = switch (transfer.action) {
        MobileDocumentAction.preview => await _nativeBridge.previewDocument(
          bytes: transfer.bytes,
          filename: transfer.filename,
          mimeType: transfer.mimeType,
        ),
        MobileDocumentAction.download => await _nativeBridge.saveDocument(
          bytes: transfer.bytes,
          filename: transfer.filename,
          mimeType: transfer.mimeType,
        ),
      };
      if (completed && transfer.action == MobileDocumentAction.download) {
        _showMessage('Dokumen berhasil disimpan.');
      }
    } on PlatformException {
      _showDocumentTransferError('Dokumen tidak dapat dibuka atau disimpan.');
    }
  }

  void _showDocumentTransferError(String message) => _showMessage(message);

  void _showMessage(String message) {
    if (!mounted) {
      return;
    }
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  void _showFileSelectorError() {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Pemilih file tidak tersedia. Silakan coba kembali.'),
      ),
    );
  }

  Future<NavigationDecision> _onNavigationRequest(
    NavigationRequest request,
  ) async {
    final uri = Uri.tryParse(request.url);
    if (uri == null) {
      _showUnavailableLinkMessage();
      return NavigationDecision.prevent;
    }

    switch (_policy.destinationFor(uri)) {
      case PortalNavigationDestination.inApp:
        return NavigationDecision.navigate;
      case PortalNavigationDestination.external:
        await _openExternalUri(uri);
        return NavigationDecision.prevent;
      case PortalNavigationDestination.blocked:
        _showUnavailableLinkMessage();
        return NavigationDecision.prevent;
    }
  }

  Future<void> _openExternalUri(Uri uri) async {
    final launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!launched) {
      _showUnavailableLinkMessage();
    }
  }

  void _showUnavailableLinkMessage() {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Tautan ini tidak tersedia di aplikasi Reporter.'),
      ),
    );
  }

  void _onPageStarted(String url) {
    final uri = Uri.tryParse(url);
    if (uri != null && _policy.isAllowed(uri)) {
      _currentUri = uri;
      _sessionState.recordNavigation(uri);
    }
    if (mounted) {
      setState(() {
        _isLoading = true;
        _hasLoadError = false;
      });
    }
  }

  void _onPageFinished(String url) {
    final uri = Uri.tryParse(url);
    if (uri != null && _policy.isAllowed(uri)) {
      unawaited(_controller.runJavaScript(mobileDocumentBridgeScript));
    }
    if (uri != null && uri.path == '/login' && _sessionState.isLoggedOut) {
      unawaited(_clearWebSessionAfterLogout());
    }

    if (mounted) {
      setState(() {
        _isLoading = false;
        _progress = 100;
      });
    }
  }

  void _onProgress(int value) {
    if (mounted) {
      setState(() => _progress = value);
    }
  }

  void _onWebResourceError(WebResourceError error) {
    if (error.isForMainFrame != true || !mounted) {
      return;
    }
    setState(() {
      _isLoading = false;
      _hasLoadError = true;
    });
  }

  Future<void> _retry() async {
    setState(() {
      _hasLoadError = false;
      _isLoading = true;
      _progress = 0;
    });
    await _controller.loadRequest(_currentUri);
  }

  Future<void> _handleBack() async {
    if (_sessionState.isLoggedOut) {
      await SystemNavigator.pop();
      return;
    }

    if (await _controller.canGoBack()) {
      await _controller.goBack();
      return;
    }
    await SystemNavigator.pop();
  }

  Future<void> _clearWebSessionAfterLogout() async {
    _documentTransferManager.reset();
    await _controller.clearLocalStorage();
    await _controller.clearCache();
    await _cookieManager.clearCookies();
  }

  @override
  Widget build(BuildContext context) {
    return PopScope<Object?>(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) {
          _handleBack();
        }
      },
      child: Scaffold(
        body: SafeArea(
          child: _hasLoadError
              ? WebViewErrorView(onRetry: _retry)
              : Stack(
                  children: [
                    WebViewWidget(controller: _controller),
                    if (_isLoading)
                      Align(
                        alignment: Alignment.topCenter,
                        child: LinearProgressIndicator(
                          value: _progress == 0 || _progress == 100
                              ? null
                              : _progress / 100,
                        ),
                      ),
                  ],
                ),
        ),
      ),
    );
  }
}
