import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Eye, Loader2 } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { BreakGlassRevealView } from "@/components/admin/break-glass-reveal-view";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import { ApiError } from "@/lib/api-client";
import {
  approveBreakGlass,
  denyBreakGlass,
  revealIdentity,
} from "@/lib/break-glass-api";
import type { BreakGlassRequest, BreakGlassReveal } from "@/lib/break-glass-types";

interface BreakGlassPendingListProps {
  requests: BreakGlassRequest[];
  showActions?: boolean;
  emptyMessage?: string;
}

export function BreakGlassPendingList({
  requests,
  showActions = true,
  emptyMessage = "No break-glass requests found.",
}: BreakGlassPendingListProps) {
  const queryClient = useQueryClient();
  const [denyTarget, setDenyTarget] = useState<BreakGlassRequest | null>(null);
  const [denialReason, setDenialReason] = useState("");
  const [reveal, setReveal] = useState<BreakGlassReveal | null>(null);

  const approveMutation = useMutation({
    mutationFn: (id: number) => approveBreakGlass(id),
    onSuccess: () => {
      toast.success("Break-glass request approved");
      invalidateBreakGlass(queryClient);
    },
    onError: (error) => toast.error(errorMessage(error, "Approval failed")),
  });

  const denyMutation = useMutation({
    mutationFn: ({ id, denial_reason }: { id: number; denial_reason: string }) =>
      denyBreakGlass(id, { denial_reason }),
    onSuccess: () => {
      toast.success("Break-glass request denied");
      setDenyTarget(null);
      setDenialReason("");
      invalidateBreakGlass(queryClient);
    },
    onError: (error) => toast.error(errorMessage(error, "Denial failed")),
  });

  const revealMutation = useMutation({
    mutationFn: (id: number) => revealIdentity(id),
    onSuccess: (data) => {
      setReveal(data);
      invalidateBreakGlass(queryClient);
    },
    onError: (error) => toast.error(errorMessage(error, "Reveal failed")),
  });

  function submitDenial() {
    if (!denyTarget) return;

    if (denialReason.trim().length < 10) {
      toast.error("Denial reason must be at least 10 characters.");
      return;
    }

    denyMutation.mutate({
      id: denyTarget.id,
      denial_reason: denialReason.trim(),
    });
  }

  return (
    <div className="space-y-4">
      {reveal && <BreakGlassRevealView reveal={reveal} />}

      <div className="overflow-hidden rounded-lg border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Report</TableHead>
              <TableHead>Requestor</TableHead>
              <TableHead>Reason</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Requested</TableHead>
              {showActions && <TableHead className="text-right">Actions</TableHead>}
            </TableRow>
          </TableHeader>
          <TableBody>
            {requests.map((request) => (
              <TableRow key={request.id}>
                <TableCell>
                  <div className="font-mono text-xs">
                    {request.report?.registration_number ?? "Unknown report"}
                  </div>
                  <div className="text-xs text-muted-foreground">
                    {request.report?.report_type ?? "metadata unavailable"}
                  </div>
                </TableCell>
                <TableCell>
                  <div className="font-medium">{request.requestor?.name ?? "Unknown requestor"}</div>
                  <div className="text-xs text-muted-foreground">
                    {request.requestor?.role?.name ?? request.requestor?.role?.code ?? "-"}
                  </div>
                </TableCell>
                <TableCell>
                  <div className="text-sm">{reasonCategoryLabel(request.reason_category)}</div>
                  <div className="mt-1 line-clamp-2 max-w-md text-xs text-muted-foreground">
                    {request.reason}
                  </div>
                </TableCell>
                <TableCell>
                  <Badge variant="outline">{label(request.status)}</Badge>
                </TableCell>
                <TableCell className="text-muted-foreground">
                  {formatDate(request.requested_at)}
                </TableCell>
                {showActions && (
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-2">
                      {request.status === "pending" && (
                        <>
                          <Button
                            size="sm"
                            onClick={() => approveMutation.mutate(request.id)}
                            disabled={approveMutation.isPending || denyMutation.isPending}
                          >
                            {approveMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            Approve
                          </Button>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setDenyTarget(request)}
                            disabled={approveMutation.isPending || denyMutation.isPending}
                          >
                            Deny
                          </Button>
                        </>
                      )}
                      {request.is_viewable && request.status !== "pending" && (
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => revealMutation.mutate(request.id)}
                          disabled={revealMutation.isPending}
                        >
                          {revealMutation.isPending ? (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                          ) : (
                            <Eye className="mr-2 h-4 w-4" />
                          )}
                          Reveal
                        </Button>
                      )}
                    </div>
                  </TableCell>
                )}
              </TableRow>
            ))}

            {requests.length === 0 && (
              <TableRow>
                <TableCell
                  colSpan={showActions ? 6 : 5}
                  className="py-10 text-center text-sm text-muted-foreground"
                >
                  {emptyMessage}
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      <Dialog open={Boolean(denyTarget)} onOpenChange={(open) => !open && setDenyTarget(null)}>
        <DialogTrigger asChild>
          <span hidden />
        </DialogTrigger>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Deny break-glass request</DialogTitle>
            <DialogDescription>
              Provide a short reason. The reporter is not notified when a request is denied.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-2">
            <Label htmlFor="denial-reason">Denial reason</Label>
            <Textarea
              id="denial-reason"
              value={denialReason}
              onChange={(event) => setDenialReason(event.target.value)}
              className="min-h-28"
              disabled={denyMutation.isPending}
            />
          </div>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setDenyTarget(null)}
              disabled={denyMutation.isPending}
            >
              Cancel
            </Button>
            <Button onClick={submitDenial} disabled={denyMutation.isPending}>
              {denyMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Deny request
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function invalidateBreakGlass(queryClient: ReturnType<typeof useQueryClient>) {
  queryClient.invalidateQueries({ queryKey: ["break-glass"] });
}

function errorMessage(error: unknown, fallback: string) {
  return error instanceof ApiError ? error.message : fallback;
}

function reasonCategoryLabel(value: string) {
  return label(value);
}

function label(value: string) {
  return value.replace(/[-_]/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatDate(value: string | null | undefined) {
  return value ? new Date(value).toLocaleString() : "-";
}
