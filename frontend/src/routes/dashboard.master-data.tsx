import { createFileRoute, Link, Outlet, useRouterState, Navigate } from "@tanstack/react-router";

import { Button } from "@/components/ui/button";
import { useAuth } from "@/hooks/use-auth";

export const Route = createFileRoute("/dashboard/master-data")({
  component: MasterDataLayout,
});

const tabs = [
  { label: "Universities", to: "/dashboard/master-data/universities" },
  { label: "Faculties", to: "/dashboard/master-data/faculties" },
  { label: "Study Programs", to: "/dashboard/master-data/study-programs" },
];

function MasterDataLayout() {
  const { roleCode } = useAuth();
  const path = useRouterState({ select: (state) => state.location.pathname });

  if (roleCode !== "super_admin") {
    return <Navigate to="/dashboard" replace />;
  }

  if (path === "/dashboard/master-data") {
    return <Navigate to="/dashboard/master-data/universities" replace />;
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Campus Master Data</h1>
          <p className="text-sm text-muted-foreground">Manage universities, faculties, and study programs.</p>
        </div>
      </div>
      <div className="flex flex-wrap gap-2">
        {tabs.map((tab) => (
          <Button key={tab.to} variant={path === tab.to ? "default" : "outline"} size="sm" asChild>
            <Link to={tab.to}>{tab.label}</Link>
          </Button>
        ))}
      </div>
      <Outlet />
    </div>
  );
}
