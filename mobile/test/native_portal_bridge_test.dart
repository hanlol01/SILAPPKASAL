import 'package:flutter_test/flutter_test.dart';
import 'package:silappkasal/features/web_portal/native_portal_bridge.dart';

void main() {
  test('returns only Android content URIs to the WebView file callback', () {
    expect(
      approvedContentUris(<String>[
        'content://media/images/1',
        '/data/user/0/id.silappkasal.app/cache/photo.jpg',
        'file:///storage/emulated/0/photo.jpg',
        'content://media/images/1',
      ]),
      <String>['content://media/images/1'],
    );
  });

  test('offers image sources only when accepted by the web input', () {
    expect(
      acceptsImageUpload(<String>['application/pdf', 'image/jpeg']),
      isTrue,
    );
    expect(acceptsImageUpload(<String>['application/pdf']), isFalse);
  });

  test('keeps only MIME types understood by Android intents', () {
    expect(
      normalizedAcceptedMimeTypes(<String>[
        '.pdf',
        'application/pdf',
        'image/jpeg',
        '*/*',
      ]),
      <String>['application/pdf', 'image/jpeg'],
    );
  });
}
