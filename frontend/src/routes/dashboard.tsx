import { createFileRoute, Navigate, Outlet, useRouterState } from "@tanstack/react-router";
import { AccessDenied } from "@/components/access-denied";
import { AuthSessionLoader } from "@/components/auth-session-loader";
import { DashboardLayout } from "@/layouts/dashboard-layout";
import { useAuth } from "@/hooks/use-auth";
import { hasDashboardAccess } from "@/lib/auth-roles";

export const Route = createFileRoute("/dashboard")({
  component: DashboardShell,
});

function DashboardShell() {
  const { isAuthenticated, isHydrating, roleCode } = useAuth();
  const redirectTo = useRouterState({
    select: (state) => `${state.location.pathname}${state.location.searchStr}`,
  });

  if (isHydrating) {
    return <AuthSessionLoader />;
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" search={{ redirect: redirectTo }} replace />;
  }

  if (isAuthenticated && !hasDashboardAccess(roleCode)) {
    return <AccessDenied />;
  }

  return (
    <DashboardLayout>
      <Outlet />
    </DashboardLayout>
  );
}
