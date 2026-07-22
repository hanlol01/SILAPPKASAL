import { createFileRoute, Navigate, Outlet, useRouterState } from "@tanstack/react-router";
import { AccessDenied } from "@/components/access-denied";
import { AuthSessionLoader } from "@/components/auth-session-loader";
import { DashboardLayout } from "@/layouts/dashboard-layout";
import { useAuth } from "@/hooks/use-auth";
import { hasDashboardAccess } from "@/lib/auth-roles";
import { canEnterInformationCenterPath } from "@/lib/published-content-access";

export const Route = createFileRoute("/dashboard")({
  component: DashboardShell,
});

function DashboardShell() {
  const { isAuthenticated, isHydrating, roleCode } = useAuth();
  const redirectTo = useRouterState({
    select: (state) => `${state.location.pathname}${state.location.searchStr}`,
  });
  const pathname = useRouterState({ select: (state) => state.location.pathname });

  if (isHydrating) {
    return <AuthSessionLoader />;
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" search={{ redirect: redirectTo }} replace />;
  }

  if (canEnterInformationCenterPath(roleCode, pathname)) {
    return <Navigate to="/portal/information-center" replace />;
  }

  if (
    isAuthenticated &&
    !hasDashboardAccess(roleCode) &&
    !canEnterInformationCenterPath(roleCode, pathname)
  ) {
    return <AccessDenied />;
  }

  return (
    <DashboardLayout>
      <Outlet />
    </DashboardLayout>
  );
}
