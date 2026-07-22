import { createFileRoute, Navigate } from "@tanstack/react-router";

import { AccessDenied } from "@/components/access-denied";
import { useAuth } from "@/hooks/use-auth";
import { canReadPublishedContent } from "@/lib/published-content-access";

export const Route = createFileRoute("/information-center")({
  component: LegacyInformationCenterRedirect,
});

function LegacyInformationCenterRedirect() {
  const { isAuthenticated, isHydrating, roleCode, user } = useAuth();

  if (isHydrating) return null;
  if (!isAuthenticated) {
    return <Navigate to="/login" search={{ redirect: "/information-center" }} replace />;
  }
  if (!canReadPublishedContent(user)) {
    return <AccessDenied backTo={roleCode === "reporter" ? "/portal" : "/dashboard"} />;
  }

  return roleCode === "reporter" ? (
    <Navigate to="/portal/information-center" replace />
  ) : (
    <Navigate to="/dashboard/information-center" replace />
  );
}
