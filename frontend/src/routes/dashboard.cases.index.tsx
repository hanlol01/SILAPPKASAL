import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { Eye, Search, SlidersHorizontal } from "lucide-react";
import { useMemo, useState } from "react";
import { useTranslation } from "react-i18next";
import { QueryErrorState } from "@/components/query-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { formatDateTime } from "@/lib/format";
import { formatCaseStatus } from "@/lib/format-labels";
import { getCases, operationsQueryKeys } from "@/lib/operations-api";

export const Route = createFileRoute("/dashboard/cases/")({
  component: CasesPage,
  head: () => ({ meta: [{ title: "Cases - SILAPPKASAL Admin" }] }),
});

const CASE_STATUSES = [
  "forwarded",
  "assessment",
  "investigation",
  "mediation",
  "recommendation",
  "decision",
  "decided",
  "recovery",
  "monitoring",
  "closed",
  "escalated",
];

function CasesPage() {
  const { t, i18n } = useTranslation(["dashboard"]);
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("all");
  const query = useMemo(
    () => ({
      status: status === "all" ? undefined : status,
      per_page: 50,
    }),
    [status],
  );
  const casesQuery = useQuery({
    queryKey: operationsQueryKeys.cases(query),
    queryFn: () => getCases(query),
  });
  const filtered =
    casesQuery.data?.data.filter((item) => {
      const haystack = `${item.case_number} ${item.registration_number} ${item.status_code} ${item.current_stage ?? ""}`.toLowerCase();
      return !q || haystack.includes(q.toLowerCase());
    }) ?? [];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{t("dashboard:cases.title")}</h1>
          <p className="text-sm text-muted-foreground">{t("dashboard:cases.subtitle")}</p>
        </div>
        <Button disabled variant="outline" title={t("dashboard:cases.assignmentUnavailableTitle")}>
          {t("dashboard:cases.assignmentUnavailable")}
        </Button>
      </div>

      <Card>
        <CardContent className="space-y-4 p-4">
          <div className="flex flex-wrap items-center gap-2">
            <div className="relative min-w-[220px] flex-1">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder={t("dashboard:cases.search")}
                value={q}
                onChange={(e) => setQ(e.target.value)}
                className="pl-9"
              />
            </div>
            <SlidersHorizontal className="h-4 w-4 text-muted-foreground" />
            <Select value={status} onValueChange={setStatus}>
              <SelectTrigger className="w-[180px]"><SelectValue placeholder={t("dashboard:common.status")} /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t("dashboard:common.allStatuses")}</SelectItem>
                {CASE_STATUSES.map((item) => (
                  <SelectItem key={item} value={item}>{formatCaseStatus(t, item)}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {casesQuery.isLoading && (
            <div className="py-12 text-center text-sm text-muted-foreground">{t("dashboard:cases.loading")}</div>
          )}
          {casesQuery.isError && (
            <QueryErrorState message={t("dashboard:cases.error")} onRetry={() => casesQuery.refetch()} />
          )}
          {casesQuery.isSuccess && (
            <div className="overflow-hidden rounded-lg border">
              <table className="w-full text-sm">
                <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                  <tr>
                    <th className="px-3 py-2 text-left">{t("dashboard:cases.case")}</th>
                    <th className="px-3 py-2 text-left">{t("dashboard:reports.registration")}</th>
                    <th className="px-3 py-2 text-left">{t("dashboard:common.status")}</th>
                    <th className="px-3 py-2 text-left">{t("dashboard:common.risk")}</th>
                    <th className="px-3 py-2 text-left">{t("dashboard:common.priority")}</th>
                    <th className="px-3 py-2 text-left">{t("dashboard:common.forwarded")}</th>
                    <th className="px-3 py-2"></th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map((item) => (
                    <tr key={item.id} className="border-t hover:bg-muted/40">
                      <td className="px-3 py-2 font-mono text-xs">{item.case_number}</td>
                      <td className="px-3 py-2 font-mono text-xs">{item.registration_number}</td>
                      <td className="px-3 py-2"><Badge variant="outline">{formatCaseStatus(t, item.status_code)}</Badge></td>
                      <td className="px-3 py-2">{item.risk_level ?? item.risk_level_code ?? "-"}</td>
                      <td className="px-3 py-2">{item.priority ?? "-"}</td>
                      <td className="px-3 py-2 text-muted-foreground">{formatDateTime(item.forwarded_at, i18n.language)}</td>
                      <td className="px-3 py-2 text-right">
                        <Button asChild size="sm" variant="ghost">
                          <Link to="/dashboard/cases/$id" params={{ id: String(item.id) }}>
                            <Eye className="mr-1 h-3.5 w-3.5" /> {t("dashboard:common.detail")}
                          </Link>
                        </Button>
                      </td>
                    </tr>
                  ))}
                  {filtered.length === 0 && (
                    <tr>
                      <td colSpan={7} className="px-3 py-12 text-center text-sm text-muted-foreground">
                        {t("dashboard:cases.empty")}
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          )}
          {casesQuery.data?.meta && (
            <div className="text-sm text-muted-foreground">
              {t("dashboard:cases.showing", { shown: filtered.length, total: casesQuery.data.meta.total })}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
