import { createFileRoute, Outlet, useNavigate } from "@tanstack/react-router";
import { useEffect } from "react";
import { AccessDenied } from "@/components/access-denied";
import { DashboardLayout } from "@/layouts/dashboard-layout";
import { useAuth } from "@/hooks/use-auth";
import { hasDashboardAccess } from "@/lib/auth-roles";

export const Route = createFileRoute("/dashboard")({
  component: DashboardShell,
});

function DashboardShell() {
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
        Loading dashboard...
      </div>
    );
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
