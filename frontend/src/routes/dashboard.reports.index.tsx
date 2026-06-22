import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { FileText, Lock, Search, SlidersHorizontal } from "lucide-react";
import { useMemo, useState } from "react";
import { AccessDenied } from "@/components/access-denied";
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
import { useAuth } from "@/hooks/use-auth";
import { getReports, operationsQueryKeys } from "@/lib/operations-api";
import type { ReportReporter } from "@/lib/operations-types";

export const Route = createFileRoute("/dashboard/reports/")({
  component: ReportsPage,
  head: () => ({ meta: [{ title: "Reports - SafeCampus Admin" }] }),
});

const REPORT_STATUSES = ["submitted", "under_review", "need_info", "rejected", "forwarded"];
const REPORT_TYPES = ["open", "confidential", "anonymous"];

function ReportsPage() {
  const { roleCode } = useAuth();
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("all");
  const [reportType, setReportType] = useState("all");
  const query = useMemo(
    () => ({
      status: status === "all" ? undefined : status,
      report_type: reportType === "all" ? undefined : reportType,
      per_page: 50,
    }),
    [status, reportType],
  );
  const reportsQuery = useQuery({
    queryKey: operationsQueryKeys.reports(query),
    queryFn: () => getReports(query),
    enabled: roleCode === "super_admin" || roleCode === "admin",
  });

  if (roleCode !== "super_admin" && roleCode !== "admin") {
    return <AccessDenied />;
  }

  const filtered =
    reportsQuery.data?.data.filter((report) => {
      const haystack = `${report.registration_number} ${report.status} ${report.report_type} ${report.category?.name ?? ""}`.toLowerCase();
      return !q || haystack.includes(q.toLowerCase());
    }) ?? [];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Reports</h1>
          <p className="text-sm text-muted-foreground">
            Browse incoming report metadata and open report records.
          </p>
        </div>
        <Button disabled variant="outline" title="Requires an approved Satgas user lookup API">
          <FileText className="mr-2 h-4 w-4" /> Forward action unavailable
        </Button>
      </div>

      <Card>
        <CardContent className="space-y-4 p-4">
          <div className="flex flex-wrap items-center gap-2">
            <div className="relative min-w-[220px] flex-1">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Search registration, status, category..."
                value={q}
                onChange={(e) => setQ(e.target.value)}
                className="pl-9"
              />
            </div>
            <SlidersHorizontal className="h-4 w-4 text-muted-foreground" />
            <Select value={status} onValueChange={setStatus}>
              <SelectTrigger className="w-[170px]"><SelectValue placeholder="Status" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All statuses</SelectItem>
                {REPORT_STATUSES.map((item) => (
                  <SelectItem key={item} value={item}>{label(item)}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={reportType} onValueChange={setReportType}>
              <SelectTrigger className="w-[170px]"><SelectValue placeholder="Type" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All types</SelectItem>
                {REPORT_TYPES.map((item) => (
                  <SelectItem key={item} value={item}>{label(item)}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {reportsQuery.isLoading && (
            <div className="py-12 text-center text-sm text-muted-foreground">Loading reports...</div>
          )}
          {reportsQuery.isError && (
            <QueryErrorState message="Reports could not be loaded." onRetry={() => reportsQuery.refetch()} />
          )}
          {reportsQuery.isSuccess && (
            <div className="overflow-hidden rounded-lg border">
              <table className="w-full text-sm">
                <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                  <tr>
                    <th className="px-3 py-2 text-left">Registration</th>
                    <th className="px-3 py-2 text-left">Type</th>
                    <th className="px-3 py-2 text-left">Reporter</th>
                    <th className="px-3 py-2 text-left">Category</th>
                    <th className="px-3 py-2 text-left">Priority</th>
                    <th className="px-3 py-2 text-left">Status</th>
                    <th className="px-3 py-2 text-left">Submitted</th>
                    <th className="px-3 py-2"></th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map((report) => (
                    <tr key={report.id} className="border-t hover:bg-muted/40">
                      <td className="px-3 py-2 font-mono text-xs">{report.registration_number}</td>
                      <td className="px-3 py-2">
                        <div className="flex flex-wrap items-center gap-2">
                          <span>{label(report.report_type)}</span>
                          {(report.is_anonymous || report.report_type === "anonymous") && (
                            <Badge variant="outline" className="gap-1 text-muted-foreground">
                              <Lock className="h-3 w-3" />
                              Anonymous
                            </Badge>
                          )}
                        </div>
                      </td>
                      <td className="px-3 py-2">{reporterDisplay(report.reporter)}</td>
                      <td className="px-3 py-2">{report.category?.name ?? "Metadata unavailable"}</td>
                      <td className="px-3 py-2">{report.priority?.name ?? "-"}</td>
                      <td className="px-3 py-2"><Badge variant="outline">{label(report.status)}</Badge></td>
                      <td className="px-3 py-2 text-muted-foreground">{formatDate(report.submitted_at)}</td>
                      <td className="px-3 py-2 text-right">
                        <Button asChild size="sm" variant="ghost">
                          <Link to="/dashboard/reports/$id" params={{ id: String(report.id) }}>Detail</Link>
                        </Button>
                      </td>
                    </tr>
                  ))}
                  {filtered.length === 0 && (
                    <tr>
                      <td colSpan={8} className="px-3 py-12 text-center text-sm text-muted-foreground">
                        No reports match your filters.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          )}
          {reportsQuery.data?.meta && (
            <div className="text-sm text-muted-foreground">
              Showing {filtered.length} of {reportsQuery.data.meta.total} reports.
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

function label(value: string) {
  return value.replace(/[-_]/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
}

function reporterDisplay(reporter: ReportReporter | null | undefined) {
  if (!reporter) {
    return <span className="text-muted-foreground">Metadata unavailable</span>;
  }

  if ("masked" in reporter && reporter.masked === true) {
    return <span className="text-muted-foreground">Reporter identity hidden</span>;
  }

  return reporter.name;
}

function formatDate(value: string | null | undefined) {
  return value ? new Date(value).toLocaleString() : "-";
}
