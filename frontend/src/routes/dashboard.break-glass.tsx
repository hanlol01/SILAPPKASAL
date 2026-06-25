import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { BreakGlassPendingList } from "@/components/admin/break-glass-pending-list";
import { AccessDenied } from "@/components/access-denied";
import { QueryErrorState } from "@/components/query-state";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  breakGlassQueryKeys,
  getBreakGlassHistory,
  getPendingRequests,
} from "@/lib/break-glass-api";
import { useAuth } from "@/hooks/use-auth";

export const Route = createFileRoute("/dashboard/break-glass")({
  component: BreakGlassPage,
  head: () => ({ meta: [{ title: "Break-glass - SILAPPKASAL Admin" }] }),
});

function BreakGlassPage() {
  const { roleCode, user } = useAuth();
  const [pendingPage, setPendingPage] = useState(1);
  const [historyPage, setHistoryPage] = useState(1);
  const canApprove = roleCode === "super_admin"
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
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Break-glass</h1>
        <p className="text-sm text-muted-foreground">
          Review exceptional access requests for anonymous report identities.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Pending requests</CardTitle>
          <CardDescription>Approve, deny, or reveal only approved requests.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {pendingQuery.isLoading && (
            <div className="py-10 text-center text-sm text-muted-foreground">Loading pending requests...</div>
          )}
          {pendingQuery.isError && (
            <QueryErrorState
              message="Pending break-glass requests could not be loaded."
              onRetry={() => pendingQuery.refetch()}
            />
          )}
          {pendingQuery.isSuccess && (
            <>
              <BreakGlassPendingList
                requests={pendingQuery.data.data}
                emptyMessage="No pending break-glass requests."
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
          <CardTitle className="text-base">History</CardTitle>
          <CardDescription>Simple historical list of break-glass requests.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {historyQuery.isLoading && (
            <div className="py-10 text-center text-sm text-muted-foreground">Loading history...</div>
          )}
          {historyQuery.isError && (
            <QueryErrorState
              message="Break-glass history could not be loaded."
              onRetry={() => historyQuery.refetch()}
            />
          )}
          {historyQuery.isSuccess && (
            <>
              <BreakGlassPendingList
                requests={historyQuery.data.data}
                showActions={false}
                emptyMessage="No break-glass history yet."
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

function PaginationControls({
  page,
  lastPage,
  onPageChange,
}: {
  page: number;
  lastPage: number;
  onPageChange: (page: number) => void;
}) {
  if (lastPage <= 1) return null;

  return (
    <div className="flex items-center justify-end gap-2 text-sm">
      <span className="text-muted-foreground">
        Page {page} of {lastPage}
      </span>
      <Button
        variant="outline"
        size="sm"
        disabled={page <= 1}
        onClick={() => onPageChange(page - 1)}
      >
        Previous
      </Button>
      <Button
        variant="outline"
        size="sm"
        disabled={page >= lastPage}
        onClick={() => onPageChange(page + 1)}
      >
        Next
      </Button>
    </div>
  );
}
