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

export interface BreadcrumbCrumb {
  /** Visible label for this crumb. */
  label: string;
  /** Optional target. If omitted, the crumb is rendered as the current page. */
  to?: string;
}

interface PageBreadcrumbProps {
  /**
   * Crumbs between the home anchor and the current page.
   * The component always prepends "Beranda" ("/dashboard") and renders the final
   * crumb as the current page when it has no `to`.
   */
  crumbs: BreadcrumbCrumb[];
  /**
   * Override the home anchor target (defaults to "/dashboard").
   */
  homeHref?: string;
  /**
   * Override the home anchor label (defaults to common:home).
   */
  homeLabel?: string;
}

/**
 * Shared breadcrumb for top-level admin list pages.
 *
 * Always anchors at "Beranda" (or the provided home label). The first crumb
 * passed in is rendered as a link if `to` is provided, otherwise as the
 * current page. Subsequent crumbs follow the same rule. Designed to be a
 * one-liner per page so the breadcrumb rhythm stays consistent across the
 * dashboard.
 *
 * Example:
 *   <PageBreadcrumb crumbs={[{ label: t("dashboard:reports.title") }]} />
 */
export function PageBreadcrumb({
  crumbs,
  homeHref = "/dashboard",
  homeLabel,
}: PageBreadcrumbProps) {
  const { t } = useTranslation(["common", "dashboard"]);
  const home = homeLabel ?? t("common:home");

  return (
    <Breadcrumb>
      <BreadcrumbList>
        <BreadcrumbItem>
          <BreadcrumbLink asChild>
            <Link to={homeHref}>{home}</Link>
          </BreadcrumbLink>
        </BreadcrumbItem>
        {crumbs.map((crumb, index) => {
          const isLast = index === crumbs.length - 1;
          return (
            <span key={`${crumb.label}-${index}`} className="contents">
              <BreadcrumbSeparator />
              <BreadcrumbItem>
                {crumb.to && !isLast ? (
                  <BreadcrumbLink asChild>
                    <Link to={crumb.to}>{crumb.label}</Link>
                  </BreadcrumbLink>
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
