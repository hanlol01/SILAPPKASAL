
import 'package:flutter/services.dart';

enum NativeUploadSource { camera, gallery, files }

class NativePortalBridge {
  const NativePortalBridge();

  static const MethodChannel _channel = MethodChannel(
    'id.silappkasal.app/native',
  );

  Future<List<String>> pickFiles({
    required NativeUploadSource source,
    required bool allowMultiple,
    required List<String> acceptedMimeTypes,
  }) async {
    final values = await _channel.invokeListMethod<String>('pickFiles', {
      'source': source.name,
      'allowMultiple': allowMultiple,
      'mimeTypes': acceptedMimeTypes,
    });
    return approvedContentUris(values ?? const <String>[]);
  }

  Future<bool> previewDocument({
    required Uint8List bytes,
    required String filename,
    required String mimeType,
  }) async =>
      await _channel.invokeMethod<bool>('previewDocument', {
        'bytes': bytes,
        'filename': filename,
        'mimeType': mimeType,
      }) ??
      false;

  Future<bool> saveDocument({
    required Uint8List bytes,
    required String filename,
    required String mimeType,
  }) async =>
      await _channel.invokeMethod<bool>('saveDocument', {
        'bytes': bytes,
        'filename': filename,
        'mimeType': mimeType,
      }) ??
      false;
}

List<String> approvedContentUris(Iterable<String> values) => values
    .where((value) {
      final uri = Uri.tryParse(value);
      return uri != null && uri.scheme == 'content' && uri.hasAuthority;
    })
    .toSet()
    .toList(growable: false);

bool acceptsImageUpload(List<String> acceptTypes) =>
    acceptTypes.isEmpty ||
    acceptTypes.any(
      (type) =>
          type == '*/*' ||
          type.toLowerCase().startsWith('image/') ||
          <String>{
            '.jpg',
            '.jpeg',
            '.png',
            '.webp',
          }.contains(type.toLowerCase()),
    );

List<String> normalizedAcceptedMimeTypes(List<String> acceptTypes) {
  final values = acceptTypes
      .map((value) => value.trim().toLowerCase())
      .where((value) => value.contains('/') && value != '*/*')
      .toSet()
      .toList(growable: false);
  return values;
}
