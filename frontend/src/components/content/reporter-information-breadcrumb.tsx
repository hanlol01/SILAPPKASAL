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

interface ReporterInformationBreadcrumbProps {
  current?: string;
  section?: {
    label: string;
    to: "/portal/information-center/education" | "/portal/information-center/policies";
  };
}

export function ReporterInformationBreadcrumb({ current, section }: ReporterInformationBreadcrumbProps) {
  const { t } = useTranslation(["portal", "informationCenter"]);
  return (
    <Breadcrumb>
      <BreadcrumbList>
        <BreadcrumbItem><BreadcrumbLink asChild><Link to="/portal">{t("portal:overview")}</Link></BreadcrumbLink></BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          {current || section ? <BreadcrumbLink asChild><Link to="/portal/information-center">{t("informationCenter:title")}</Link></BreadcrumbLink> : <BreadcrumbPage>{t("informationCenter:title")}</BreadcrumbPage>}
        </BreadcrumbItem>
        {section && <><BreadcrumbSeparator /><BreadcrumbItem>{current ? <BreadcrumbLink asChild><Link to={section.to}>{section.label}</Link></BreadcrumbLink> : <BreadcrumbPage>{section.label}</BreadcrumbPage>}</BreadcrumbItem></>}
        {current && <><BreadcrumbSeparator /><BreadcrumbItem><BreadcrumbPage>{current}</BreadcrumbPage></BreadcrumbItem></>}
      </BreadcrumbList>
    </Breadcrumb>
  );
}
