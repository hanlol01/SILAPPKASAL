enum PortalNavigationDestination { inApp, external, blocked }

class PortalNavigationPolicy {
  const PortalNavigationPolicy({this.host = 'silappkasal.web.id'});

  final String host;

  Uri get entryUri => Uri.https(host, '/login');

  PortalNavigationDestination destinationFor(Uri uri) {
    if (isAllowed(uri)) {
      return PortalNavigationDestination.inApp;
    }

    if (_isSupportedExternalUri(uri)) {
      return PortalNavigationDestination.external;
    }

    return PortalNavigationDestination.blocked;
  }

  bool isAllowed(Uri uri) {
    if (uri.scheme != 'https' || uri.host.toLowerCase() != host.toLowerCase()) {
      return false;
    }

    final path = uri.path;
    return path == '/login' ||
        path == '/register' ||
        path == '/track' ||
        path == '/portal' ||
        path.startsWith('/portal/');
  }

  bool _isSupportedExternalUri(Uri uri) {
    if (uri.scheme == 'https' && uri.hasAuthority) {
      return true;
    }

    return (uri.scheme == 'mailto' || uri.scheme == 'tel') &&
        uri.path.isNotEmpty;
  }
}
