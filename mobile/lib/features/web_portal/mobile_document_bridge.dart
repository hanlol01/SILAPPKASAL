import 'dart:convert';
import 'dart:typed_data';

enum MobileDocumentAction { preview, download }

class MobileDocumentTransfer {
  const MobileDocumentTransfer({
    required this.action,
    required this.filename,
    required this.mimeType,
    required this.bytes,
  });

  final MobileDocumentAction action;
  final String filename;
  final String mimeType;
  final Uint8List bytes;
}

typedef MobileDocumentTransferHandler = Future<void> Function(
  MobileDocumentTransfer transfer,
);

class MobileDocumentTransferManager {
  MobileDocumentTransferManager({required this.onTransfer, this.onError});

  static const int maxDocumentBytes = 25 * 1024 * 1024;
  static const Set<String> _allowedMimeTypes = <String>{
    'application/pdf',
    'application/octet-stream',
    'image/jpeg',
    'image/png',
    'image/webp',
  };

  final MobileDocumentTransferHandler onTransfer;
  final void Function(String message)? onError;
  final Map<String, _PendingDocumentTransfer> _pending =
      <String, _PendingDocumentTransfer>{};

  Future<void> handleMessage(
    String rawMessage, {
    required bool trustedPage,
  }) async {
    if (!trustedPage) {
      onError?.call('Dokumen ditolak karena halaman tidak diizinkan.');
      return;
    }

    try {
      final value = jsonDecode(rawMessage);
      if (value is! Map<String, dynamic>) {
        throw const FormatException('Invalid document message.');
      }

      final event = value['event'];
      final id = value['id'];
      if (event is! String || id is! String || !_isValidTransferId(id)) {
        throw const FormatException('Invalid document transfer identity.');
      }

      switch (event) {
        case 'begin':
          _begin(id, value);
        case 'chunk':
          _appendChunk(id, value);
        case 'end':
          await _finish(id);
        case 'cancel':
          _pending.remove(id);
        default:
          throw const FormatException('Unsupported document event.');
      }
    } catch (_) {
      onError?.call('Dokumen tidak dapat diproses oleh aplikasi.');
    }
  }

  void reset() => _pending.clear();

  void _begin(String id, Map<String, dynamic> value) {
    final action = switch (value['action']) {
      'preview' => MobileDocumentAction.preview,
      'download' => MobileDocumentAction.download,
      _ => throw const FormatException('Invalid document action.'),
    };
    final rawMimeType = value['mimeType'];
    final rawFilename = value['filename'];
    final size = value['size'];
    if (rawMimeType is! String ||
        rawFilename is! String ||
        size is! num ||
        size < 0 ||
        size > maxDocumentBytes) {
      throw const FormatException('Invalid document metadata.');
    }

    final mimeType = rawMimeType.toLowerCase().split(';').first.trim();
    if (!_allowedMimeTypes.contains(mimeType)) {
      throw const FormatException('Unsupported document type.');
    }

    _pending[id] = _PendingDocumentTransfer(
      action: action,
      filename: _safeFilename(rawFilename, mimeType),
      mimeType:
          mimeType == 'application/octet-stream' &&
              rawFilename.toLowerCase().endsWith('.pdf')
          ? 'application/pdf'
          : mimeType,
      expectedBytes: size.toInt(),
    );
  }

  void _appendChunk(String id, Map<String, dynamic> value) {
    final transfer = _pending[id];
    final data = value['data'];
    if (transfer == null || data is! String || data.length > 256 * 1024) {
      throw const FormatException('Invalid document chunk.');
    }

    transfer.encodedBytes.write(data);
    final maxEncodedLength = ((maxDocumentBytes + 2) ~/ 3) * 4 + 4;
    if (transfer.encodedBytes.length > maxEncodedLength) {
      _pending.remove(id);
      throw const FormatException('Document exceeds size limit.');
    }
  }

  Future<void> _finish(String id) async {
    final transfer = _pending.remove(id);
    if (transfer == null) {
      throw const FormatException('Unknown document transfer.');
    }

    final bytes = base64Decode(transfer.encodedBytes.toString());
    if (bytes.length > maxDocumentBytes ||
        (transfer.expectedBytes > 0 &&
            bytes.length != transfer.expectedBytes)) {
      throw const FormatException('Incomplete document transfer.');
    }

    await onTransfer(
      MobileDocumentTransfer(
        action: transfer.action,
        filename: transfer.filename,
        mimeType: transfer.mimeType,
        bytes: bytes,
      ),
    );
  }

  static bool _isValidTransferId(String value) =>
      RegExp(r'^[a-zA-Z0-9_-]{1,80}$').hasMatch(value);

  static String _safeFilename(String value, String mimeType) {
    final withoutControls = value
        .replaceAll(RegExp(r'[\x00-\x1F\x7F]'), '')
        .replaceAll(RegExp(r'[\\/:*?"<>|]'), '-')
        .trim();
    final fallback = mimeType.startsWith('image/')
        ? 'dokumen-gambar'
        : 'dokumen-SILAPPKASAL';
    var filename = withoutControls.isEmpty ? fallback : withoutControls;
    if (filename.length > 120) {
      filename = filename.substring(0, 120);
    }
    if (!filename.contains('.')) {
      filename += mimeType == 'application/pdf' ? '.pdf' : '.bin';
    }
    return filename;
  }
}

class _PendingDocumentTransfer {
  _PendingDocumentTransfer({
    required this.action,
    required this.filename,
    required this.mimeType,
    required this.expectedBytes,
  });

  final MobileDocumentAction action;
  final String filename;
  final String mimeType;
  final int expectedBytes;
  final StringBuffer encodedBytes = StringBuffer();
}

const String mobileDocumentBridgeScript = r'''
(() => {
  if (window.__silappkasalMobileDocumentBridgeInstalled) return;
  const bridge = window.MobileDocumentBridge;
  if (!bridge || typeof bridge.postMessage !== 'function') return;
  window.__silappkasalMobileDocumentBridgeInstalled = true;

  const blobs = new Map();
  const originalCreateObjectURL = URL.createObjectURL.bind(URL);
  const originalRevokeObjectURL = URL.revokeObjectURL.bind(URL);
  const originalOpen = window.open.bind(window);
  const originalAnchorClick = HTMLAnchorElement.prototype.click;
  const chunkSize = 128 * 1024;

  const post = (message) => bridge.postMessage(JSON.stringify(message));
  const blobKey = (url) => String(url || '').split('#')[0];
  const safeName = (value, fallback) => {
    const name = String(value || '').replace(/[\\/:*?"<>|\u0000-\u001f\u007f]/g, '-').trim();
    return (name || fallback).slice(0, 120);
  };

  async function transferBlob(blob, action, requestedName) {
    const id = `doc_${Date.now()}_${Math.random().toString(36).slice(2)}`;
    const mimeType = blob.type || 'application/octet-stream';
    const fallback = mimeType.startsWith('image/') ? 'dokumen-gambar' : 'dokumen-SILAPPKASAL.pdf';
    const filename = safeName(requestedName, fallback);
    post({ event: 'begin', id, action, filename, mimeType, size: blob.size });
    try {
      const dataUrl = await new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result || ''));
        reader.onerror = () => reject(reader.error || new Error('FileReader failed'));
        reader.readAsDataURL(blob);
      });
      const encoded = dataUrl.slice(dataUrl.indexOf(',') + 1);
      for (let offset = 0; offset < encoded.length; offset += chunkSize) {
        post({ event: 'chunk', id, data: encoded.slice(offset, offset + chunkSize) });
      }
      post({ event: 'end', id });
    } catch (_) {
      post({ event: 'cancel', id });
    }
  }

  URL.createObjectURL = function(object) {
    const url = originalCreateObjectURL(object);
    if (object instanceof Blob) blobs.set(url, object);
    return url;
  };
  URL.revokeObjectURL = function(url) {
    blobs.delete(blobKey(url));
    return originalRevokeObjectURL(url);
  };

  function mobilePopup() {
    let closed = false;
    const loadListeners = [];
    const popup = {
      opener: null,
      document: { title: '', body: { textContent: null } },
      get closed() { return closed; },
      close() { closed = true; },
      addEventListener(type, listener) {
        if (type === 'load' && typeof listener === 'function') loadListeners.push(listener);
      },
      location: {
        replace(url) {
          const blob = blobs.get(blobKey(url));
          if (!blob) return;
          const description = `${popup.document.title} ${popup.document.body.textContent || ''}`;
          const download = /\b(draf|download|unduh)\b/i.test(description);
          const requestedName = download ? `${popup.document.title || 'Dokumen DRAF'}.pdf` : 'Pratinjau Dokumen.pdf';
          void transferBlob(blob, download ? 'download' : 'preview', requestedName).finally(() => {
            loadListeners.splice(0).forEach((listener) => listener.call(popup));
          });
        },
      },
    };
    return popup;
  }

  window.open = function(url, target, features) {
    const candidate = String(url || '');
    if ((candidate === '' || candidate === 'about:blank') && (!target || target === '_blank')) {
      return mobilePopup();
    }
    return originalOpen(url, target, features);
  };

  HTMLAnchorElement.prototype.click = function() {
    const blob = blobs.get(blobKey(this.href));
    if (blob && this.hasAttribute('download')) {
      void transferBlob(blob, 'download', this.download);
      return;
    }
    return originalAnchorClick.call(this);
  };
})();
''';
