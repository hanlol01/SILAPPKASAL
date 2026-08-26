class PortalSessionNavigationState {
  bool _hasVisitedPortal = false;
  bool _isLoggedOut = false;

  bool get isLoggedOut => _isLoggedOut;

  void recordNavigation(Uri uri) {
    if (uri.path == '/portal' || uri.path.startsWith('/portal/')) {
      _hasVisitedPortal = true;
      _isLoggedOut = false;
      return;
    }

    if (uri.path == '/login' && _hasVisitedPortal) {
      _isLoggedOut = true;
    }
  }
}
