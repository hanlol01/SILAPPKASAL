import { Link } from "@tanstack/react-router";
import { useTranslation } from "react-i18next";

import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";

interface Props {
  current?: string;
  section?: {
    label: string;
    to:
      | "/dashboard/information-center/education"
      | "/dashboard/information-center/policies";
  };
}

export function DashboardInformationBreadcrumb({ current, section }: Props) {
  const { t } = useTranslation(["dashboard", "informationCenter"]);

  return (
    <Breadcrumb>
      <BreadcrumbList>
        <BreadcrumbItem>
          <BreadcrumbLink asChild><Link to="/dashboard">{t("dashboard:nav.overview")}</Link></BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          {current || section ? (
            <BreadcrumbLink asChild><Link to="/dashboard/information-center">{t("informationCenter:title")}</Link></BreadcrumbLink>
          ) : (
            <BreadcrumbPage>{t("informationCenter:title")}</BreadcrumbPage>
          )}
        </BreadcrumbItem>
        {section && (
          <>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              {current ? <BreadcrumbLink asChild><Link to={section.to}>{section.label}</Link></BreadcrumbLink> : <BreadcrumbPage>{section.label}</BreadcrumbPage>}
            </BreadcrumbItem>
          </>
        )}
        {current && (
          <>
            <BreadcrumbSeparator />
            <BreadcrumbItem><BreadcrumbPage>{current}</BreadcrumbPage></BreadcrumbItem>
          </>
        )}
      </BreadcrumbList>
    </Breadcrumb>
  );
}
