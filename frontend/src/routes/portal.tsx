import { createFileRoute, Outlet, useNavigate } from "@tanstack/react-router";
import { useEffect } from "react";
import { AccessDenied } from "@/components/access-denied";
import { PortalLayout } from "@/layouts/portal-layout";
import { useAuth } from "@/hooks/use-auth";
import { hasPortalAccess } from "@/lib/auth-roles";

export const Route = createFileRoute("/portal")({
  component: PortalShell,
});

function PortalShell() {
  const { isAuthenticated, isHydrating, roleCode } = useAuth();
  const navigate = useNavigate();

  useEffect(() => {
    if (!isHydrating && !isAuthenticated) {
      navigate({ to: "/login" });
    }
  }, [isAuthenticated, isHydrating, navigate]);

  if (isHydrating) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background text-sm text-muted-foreground">
        Loading portal...
      </div>
    );
  }

  if (isAuthenticated && !hasPortalAccess(roleCode)) {
    return <AccessDenied backTo="/dashboard" backLabel="Back to dashboard" />;
  }

  return (
    <PortalLayout>
      <Outlet />
    </PortalLayout>
  );
}
