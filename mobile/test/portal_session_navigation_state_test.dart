import 'package:flutter_test/flutter_test.dart';
import 'package:silappkasal/features/web_portal/portal_session_navigation_state.dart';

void main() {
  test('marks a return to login after portal access as logged out', () {
    final state = PortalSessionNavigationState();

    state.recordNavigation(
      Uri.parse('https://silappkasal.web.id/portal/account'),
    );
    state.recordNavigation(Uri.parse('https://silappkasal.web.id/login'));

    expect(state.isLoggedOut, isTrue);
  });

  test('clears logged-out state when the user enters the portal again', () {
    final state = PortalSessionNavigationState();

    state.recordNavigation(Uri.parse('https://silappkasal.web.id/portal'));
    state.recordNavigation(Uri.parse('https://silappkasal.web.id/login'));
    state.recordNavigation(Uri.parse('https://silappkasal.web.id/portal'));

    expect(state.isLoggedOut, isFalse);
  });
}
