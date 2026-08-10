import { createFileRoute, Navigate } from "@tanstack/react-router";
import { useTranslation } from "react-i18next";

import { StaffManagement } from "@/components/staff-management";
import { PageBreadcrumb } from "@/components/page-breadcrumb";
import { useAuth } from "@/hooks/use-auth";

export const Route = createFileRoute("/dashboard/staff")({
  component: CampusStaffPage,
});

function CampusStaffPage() {
  const { t } = useTranslation(["dashboard"]);
  const { roleCode, user } = useAuth();

  if (roleCode !== "admin" || !user?.university_id) return <Navigate to="/dashboard" replace />;

  return (
    <div className="space-y-6">
      <PageBreadcrumb crumbs={[{ label: t("dashboard:staff.title") }]} />
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t("dashboard:staff.title")}</h1>
        <p className="text-sm text-muted-foreground">{t("dashboard:staff.adminPageSubtitle")}</p>
      </div>
      <StaffManagement universityId={user.university_id} universityName={user.university?.name} canManageAdministrators={false} />
    </div>
  );
}
