import { Link } from "@tanstack/react-router";
import { useTranslation } from "react-i18next";
import type { ReactNode } from "react";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";

export interface BreadcrumbCrumb {
  /** Visible label for this crumb. */
  label: string;
  /**
   * Optional pre-built link element (e.g. a TanStack Router <Link to="...">).
   * Provide this for intermediate crumbs that should be navigable. When
   * omitted the crumb renders as the current page.
   */
  link?: ReactNode;
}

interface PageBreadcrumbProps {
  /**
   * Crumbs between the home anchor and the current page.
   * The component always prepends the home anchor (Beranda / Home) and
   * renders the final crumb as the current page when no link is supplied.
   */
  crumbs: BreadcrumbCrumb[];
}

/**
 * Shared breadcrumb for top-level admin pages.
 *
 * Always anchors at the dashboard home ("Beranda"). Most demo-path pages
 * only need a single trailing crumb such as `t("dashboard:reports.title")`,
 * so the API is intentionally minimal. Pass a `link` prop on a crumb when
 * a navigable intermediate level is required (e.g. detail pages).
 *
 * Example (list page):
 *   <PageBreadcrumb crumbs={[{ label: t("dashboard:reports.title") }]} />
 *
 * Example (detail page):
 *   <PageBreadcrumb crumbs={[
 *     { label: t("dashboard:cases.title"), link: <Link to="/dashboard/cases">{t("dashboard:cases.title")}</Link> },
 *     { label: caseNumber },
 *   ]} />
 */
export function PageBreadcrumb({ crumbs }: PageBreadcrumbProps) {
  const { t } = useTranslation(["common"]);

  return (
    <Breadcrumb>
      <BreadcrumbList>
        <BreadcrumbItem>
          <BreadcrumbLink asChild>
            <Link to="/dashboard">{t("common:home")}</Link>
          </BreadcrumbLink>
        </BreadcrumbItem>
        {crumbs.map((crumb, index) => {
          const isLast = index === crumbs.length - 1;
          return (
            <span key={`${crumb.label}-${index}`} className="contents">
              <BreadcrumbSeparator />
              <BreadcrumbItem>
                {crumb.link && !isLast ? (
                  <BreadcrumbLink asChild>{crumb.link}</BreadcrumbLink>
                ) : (
                  <BreadcrumbPage>{crumb.label}</BreadcrumbPage>
                )}
              </BreadcrumbItem>
            </span>
          );
        })}
      </BreadcrumbList>
    </Breadcrumb>
  );
}
