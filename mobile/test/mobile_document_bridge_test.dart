import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:silappkasal/features/web_portal/mobile_document_bridge.dart';

void main() {
  test(
    'assembles a protected PDF blob and dispatches its requested action',
    () async {
      MobileDocumentTransfer? received;
      final manager = MobileDocumentTransferManager(
        onTransfer: (transfer) async => received = transfer,
      );
      final encoded = base64Encode(utf8.encode('%PDF-test'));

      await manager.handleMessage(
        jsonEncode(<String, Object>{
          'event': 'begin',
          'id': 'transfer-1',
          'action': 'download',
          'filename': 'Berita Acara.pdf',
          'mimeType': 'application/pdf',
          'size': 9,
        }),
        trustedPage: true,
      );
      await manager.handleMessage(
        jsonEncode(<String, Object>{
          'event': 'chunk',
          'id': 'transfer-1',
          'data': encoded,
        }),
        trustedPage: true,
      );
      await manager.handleMessage(
        jsonEncode(<String, Object>{'event': 'end', 'id': 'transfer-1'}),
        trustedPage: true,
      );

      expect(received, isNotNull);
      expect(received!.action, MobileDocumentAction.download);
      expect(received!.filename, 'Berita Acara.pdf');
      expect(utf8.decode(received!.bytes), '%PDF-test');
    },
  );

  test('rejects document messages outside an approved Reporter page', () async {
    var dispatched = false;
    final errors = <String>[];
    final manager = MobileDocumentTransferManager(
      onTransfer: (_) async => dispatched = true,
      onError: errors.add,
    );

    await manager.handleMessage(
      jsonEncode(<String, Object>{
        'event': 'begin',
        'id': 'transfer-2',
        'action': 'preview',
        'filename': 'secret.pdf',
        'mimeType': 'application/pdf',
        'size': 10,
      }),
      trustedPage: false,
    );

    expect(dispatched, isFalse);
    expect(errors, isNotEmpty);
  });
}
