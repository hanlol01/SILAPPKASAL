import { createFileRoute, Outlet, useNavigate } from "@tanstack/react-router";
import { useEffect } from "react";
import { DashboardLayout } from "@/layouts/dashboard-layout";
import { useAuth } from "@/hooks/use-auth";

export const Route = createFileRoute("/dashboard")({
  component: DashboardShell,
});

function DashboardShell() {
  const { user } = useAuth();
  const navigate = useNavigate();

  useEffect(() => {
    // Allow brief mount before checking, since auth hydrates from storage
    const t = setTimeout(() => {
      const raw =
        typeof window !== "undefined"
          ? localStorage.getItem("safecampus_session") ||
            sessionStorage.getItem("safecampus_session")
          : null;
      if (!user && !raw) navigate({ to: "/login" });
    }, 50);
    return () => clearTimeout(t);
  }, [user, navigate]);

  return (
    <DashboardLayout>
      <Outlet />
    </DashboardLayout>
  );
}
