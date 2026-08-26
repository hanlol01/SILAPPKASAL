import 'package:flutter_test/flutter_test.dart';
import 'package:silappkasal/features/web_portal/file_selector_configuration.dart';

void main() {
  test('keeps the web form MIME filter when it is specific', () {
    final groups = fileTypeGroupsFromAcceptedMimeTypes(<String>[
      'application/pdf',
      'image/jpeg',
      'application/pdf',
    ]);

    expect(groups, hasLength(1));
    expect(groups.single.mimeTypes, <String>['application/pdf', 'image/jpeg']);
  });

  test(
    'allows the Android chooser to show all files for a wildcard filter',
    () {
      final groups = fileTypeGroupsFromAcceptedMimeTypes(<String>['*/*']);

      expect(groups, isEmpty);
    },
  );
}
