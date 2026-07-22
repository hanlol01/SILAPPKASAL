import { createFileRoute, Outlet } from "@tanstack/react-router";
import { AccessDenied } from "@/components/access-denied";
import { useAuth } from "@/hooks/use-auth";
import { canReadPublishedContent } from "@/lib/published-content-access";

export const Route = createFileRoute("/portal/information-center")({ component: ReporterInformationShell });
function ReporterInformationShell() {
  const { user } = useAuth();
  if (!canReadPublishedContent(user)) return <AccessDenied backTo="/portal" />;
  return <Outlet />;
}
