import 'package:file_selector/file_selector.dart';

List<XTypeGroup> fileTypeGroupsFromAcceptedMimeTypes(List<String> acceptTypes) {
  final mimeTypes = acceptTypes
      .where((type) => type.isNotEmpty && type != '*/*')
      .toSet()
      .toList(growable: false);

  if (mimeTypes.isEmpty) {
    return const <XTypeGroup>[];
  }

  return <XTypeGroup>[
    XTypeGroup(label: 'File yang didukung', mimeTypes: mimeTypes),
  ];
}
