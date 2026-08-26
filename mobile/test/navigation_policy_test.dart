import 'package:flutter_test/flutter_test.dart';
import 'package:silappkasal/features/web_portal/navigation_policy.dart';

void main() {
  const policy = PortalNavigationPolicy();

  test('allows only approved SILAPPKASAL Reporter routes', () {
    expect(
      policy.isAllowed(Uri.parse('https://silappkasal.web.id/login')),
      isTrue,
    );
    expect(
      policy.isAllowed(Uri.parse('https://silappkasal.web.id/register')),
      isTrue,
    );
    expect(
      policy.isAllowed(Uri.parse('https://silappkasal.web.id/track')),
      isTrue,
    );
    expect(
      policy.isAllowed(Uri.parse('https://silappkasal.web.id/portal/reports')),
      isTrue,
    );
  });

  test('blocks non-Reporter, insecure, and foreign destinations', () {
    expect(
      policy.isAllowed(Uri.parse('https://silappkasal.web.id/dashboard')),
      isFalse,
    );
    expect(
      policy.isAllowed(Uri.parse('http://silappkasal.web.id/login')),
      isFalse,
    );
    expect(policy.isAllowed(Uri.parse('https://example.test/portal')), isFalse);
  });

  test('classifies portal, supported external, and blocked destinations', () {
    expect(
      policy.destinationFor(
        Uri.parse('https://silappkasal.web.id/portal/reports'),
      ),
      PortalNavigationDestination.inApp,
    );
    expect(
      policy.destinationFor(Uri.parse('https://support.example.test/help')),
      PortalNavigationDestination.external,
    );
    expect(
      policy.destinationFor(Uri.parse('mailto:help@silappkasal.test')),
      PortalNavigationDestination.external,
    );
    expect(
      policy.destinationFor(Uri.parse('tel:+62123456789')),
      PortalNavigationDestination.external,
    );
    expect(
      policy.destinationFor(Uri.parse('http://silappkasal.web.id/login')),
      PortalNavigationDestination.blocked,
    );
    expect(
      policy.destinationFor(Uri.parse('javascript:alert(1)')),
      PortalNavigationDestination.blocked,
    );
  });
}
