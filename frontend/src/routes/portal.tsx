import { createFileRoute, Navigate, Outlet, useRouterState } from "@tanstack/react-router";
import { useTranslation } from "react-i18next";
import { AccessDenied } from "@/components/access-denied";
import { AuthSessionLoader } from "@/components/auth-session-loader";
import { PortalLayout } from "@/layouts/portal-layout";
import { useAuth } from "@/hooks/use-auth";
import { hasPortalAccess } from "@/lib/auth-roles";

export const Route = createFileRoute("/portal")({
  component: PortalShell,
});

function PortalShell() {
  const { t } = useTranslation(["common", "portal"]);
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

  if (isAuthenticated && !hasPortalAccess(roleCode)) {
    return <AccessDenied backTo="/dashboard" backLabel={t("common:backToDashboard")} />;
  }

  return (
    <PortalLayout>
      <Outlet />
    </PortalLayout>
  );
}
