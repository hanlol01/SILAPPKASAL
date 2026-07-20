import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import { BreakGlassPendingList } from "@/components/admin/break-glass-pending-list";
import { AccessDenied } from "@/components/access-denied";
import { PageBreadcrumb } from "@/components/page-breadcrumb";
import { QueryErrorState } from "@/components/query-state";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import {
  breakGlassQueryKeys,
  getBreakGlassHistory,
  getPendingRequests,
} from "@/lib/break-glass-api";
import { useAuth } from "@/hooks/use-auth";

export const Route = createFileRoute("/dashboard/break-glass")({
  component: BreakGlassPage,
  head: () => ({ meta: [{ title: "Akses Darurat Identitas - SILAPPKASAL" }] }),
});

function BreakGlassPage() {
  const { roleCode, user } = useAuth();
  const { t } = useTranslation(["dashboard", "common"]);
  const [pendingPage, setPendingPage] = useState(1);
  const [historyPage, setHistoryPage] = useState(1);
  const canApprove = roleCode === "admin"
    && Boolean(user?.permissions?.includes("privacy.approve_break_glass"));

  const pendingQuery = useQuery({
    queryKey: breakGlassQueryKeys.pending(pendingPage),
    queryFn: () => getPendingRequests(pendingPage),
    enabled: canApprove,
    retry: false,
  });

  const historyQuery = useQuery({
    queryKey: breakGlassQueryKeys.history(historyPage),
    queryFn: () => getBreakGlassHistory(historyPage),
    enabled: canApprove,
    retry: false,
  });

  if (!canApprove) {
    return <AccessDenied />;
  }

  return (
    <div className="space-y-6">
      <PageBreadcrumb crumbs={[{ label: t("dashboard:breakGlass.title") }]} />
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t("dashboard:breakGlass.title")}</h1>
        <p className="text-sm text-muted-foreground">{t("dashboard:breakGlass.subtitle")}</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("dashboard:breakGlass.pending.title")}</CardTitle>
          <CardDescription>{t("dashboard:breakGlass.pending.description")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {pendingQuery.isLoading && <BreakGlassListSkeleton rows={3} />}
          {pendingQuery.isError && (
            <QueryErrorState
              message={t("dashboard:breakGlass.errors.pendingLoad")}
              onRetry={() => pendingQuery.refetch()}
            />
          )}
          {pendingQuery.isSuccess && (
            <>
              <BreakGlassPendingList
                requests={pendingQuery.data.data}
                emptyMessage={t("dashboard:breakGlass.pending.empty")}
              />
              <PaginationControls
                page={pendingQuery.data.meta.current_page}
                lastPage={pendingQuery.data.meta.last_page}
                onPageChange={setPendingPage}
              />
            </>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("dashboard:breakGlass.history.title")}</CardTitle>
          <CardDescription>{t("dashboard:breakGlass.history.description")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {historyQuery.isLoading && <BreakGlassListSkeleton rows={3} />}
          {historyQuery.isError && (
            <QueryErrorState
              message={t("dashboard:breakGlass.errors.historyLoad")}
              onRetry={() => historyQuery.refetch()}
            />
          )}
          {historyQuery.isSuccess && (
            <>
              <BreakGlassPendingList
                requests={historyQuery.data.data}
                emptyMessage={t("dashboard:breakGlass.history.empty")}
              />
              <PaginationControls
                page={historyQuery.data.meta.current_page}
                lastPage={historyQuery.data.meta.last_page}
                onPageChange={setHistoryPage}
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

function BreakGlassListSkeleton({ rows }: { rows: number }) {
  return (
    <div className="space-y-2">
      {Array.from({ length: rows }).map((_, index) => (
        <div key={index} className="flex items-center gap-3 rounded-lg border p-3">
          <Skeleton className="h-9 w-9 rounded-md" />
          <div className="flex-1 space-y-2">
            <Skeleton className="h-4 w-48" />
            <Skeleton className="h-3 w-64" />
          </div>
          <Skeleton className="h-8 w-20" />
        </div>
      ))}
    </div>
  );
}

function PaginationControls({
  page,
  lastPage,
  onPageChange,
}: {
  page: number;
  lastPage: number;
  onPageChange: (page: number) => void;
}) {
  const { t } = useTranslation(["dashboard"]);
  if (lastPage <= 1) return null;

  return (
    <div className="flex items-center justify-end gap-2 text-sm">
      <span className="text-muted-foreground">
        {t("dashboard:pagination.pageOf", { page, lastPage })}
      </span>
      <Button
        variant="outline"
        size="sm"
        disabled={page <= 1}
        onClick={() => onPageChange(page - 1)}
      >
        {t("dashboard:pagination.previous")}
      </Button>
      <Button
        variant="outline"
        size="sm"
        disabled={page >= lastPage}
        onClick={() => onPageChange(page + 1)}
      >
        {t("dashboard:pagination.next")}
      </Button>
    </div>
  );
}
