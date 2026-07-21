import { createFileRoute, Navigate, Outlet } from "@tanstack/react-router";

import { AccessDenied } from "@/components/access-denied";
import { useAuth } from "@/hooks/use-auth";
import { canReadPublishedContent } from "@/lib/published-content-access";

export const Route = createFileRoute("/dashboard/information-center")({
  component: InformationCenterShell,
});

function InformationCenterShell() {
  const { isAuthenticated, isHydrating, roleCode, user } = useAuth();

  if (isHydrating) return null;
  if (!isAuthenticated)
    return <Navigate to="/login" search={{ redirect: "/dashboard/information-center" }} replace />;
  if (!canReadPublishedContent(user)) {
    return <AccessDenied backTo={roleCode === "reporter" ? "/portal" : "/dashboard"} />;
  }

  return <Outlet />;
}
