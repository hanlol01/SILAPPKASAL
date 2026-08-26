import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:silappkasal/features/web_portal/webview_error_view.dart';

void main() {
  testWidgets('shows a retry action when the portal cannot be loaded', (
    tester,
  ) async {
    var retryCount = 0;

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(body: WebViewErrorView(onRetry: () => retryCount += 1)),
      ),
    );

    expect(find.text('Tidak dapat memuat SILAPPKASAL'), findsOneWidget);
    await tester.tap(find.byKey(const Key('webview-retry-button')));
    expect(retryCount, 1);
  });
}
