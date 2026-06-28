import { createFileRoute, Link, Outlet, useRouterState, Navigate } from "@tanstack/react-router";
import { useTranslation } from "react-i18next";

import { Button } from "@/components/ui/button";
import { useAuth } from "@/hooks/use-auth";

export const Route = createFileRoute("/dashboard/master-data")({
  component: MasterDataLayout,
});

const tabs = [
  { labelKey: "universities", to: "/dashboard/master-data/universities" },
  { labelKey: "faculties", to: "/dashboard/master-data/faculties" },
  { labelKey: "studyPrograms", to: "/dashboard/master-data/study-programs" },
];

function MasterDataLayout() {
  const { roleCode } = useAuth();
  const { t } = useTranslation(["dashboard"]);
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
          <h1 className="text-2xl font-semibold tracking-tight">{t("dashboard:masterData.title")}</h1>
          <p className="text-sm text-muted-foreground">{t("dashboard:masterData.subtitle")}</p>
        </div>
      </div>
      <div className="flex flex-wrap gap-2">
        {tabs.map((tab) => (
          <Button key={tab.to} variant={path === tab.to ? "default" : "outline"} size="sm" asChild>
            <Link to={tab.to}>{t(`dashboard:masterData.${tab.labelKey}`)}</Link>
          </Button>
        ))}
      </div>
      <Outlet />
    </div>
  );
}
