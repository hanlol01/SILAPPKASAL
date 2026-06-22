import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Loader2, ShieldAlert } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
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
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { ApiError } from "@/lib/api-client";
import { requestBreakGlass } from "@/lib/break-glass-api";
import type { BreakGlassReasonCategory } from "@/lib/break-glass-types";

const REASON_CATEGORIES: Array<{ value: BreakGlassReasonCategory; label: string }> = [
  { value: "legal_requirement", label: "Legal requirement" },
  { value: "safety_emergency", label: "Safety emergency" },
  { value: "investigation_necessity", label: "Investigation necessity" },
  { value: "institutional_compliance", label: "Institutional compliance" },
  { value: "victim_consent", label: "Victim/reporter consent" },
];

interface BreakGlassRequestDialogProps {
  reportId: number;
  registrationNumber: string;
}

export function BreakGlassRequestDialog({
  reportId,
  registrationNumber,
}: BreakGlassRequestDialogProps) {
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [reasonCategory, setReasonCategory] =
    useState<BreakGlassReasonCategory>("investigation_necessity");
  const [reason, setReason] = useState("");
  const [acknowledgment, setAcknowledgment] = useState(false);
  const [fieldError, setFieldError] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: () =>
      requestBreakGlass({
        report_id: reportId,
        reason_category: reasonCategory,
        reason,
        acknowledgment,
      }),
    onSuccess: () => {
      toast.success("Break-glass request submitted");
      setOpen(false);
      setReason("");
      setAcknowledgment(false);
      setFieldError(null);
      queryClient.invalidateQueries({ queryKey: ["break-glass"] });
    },
    onError: (error) => {
      setFieldError(error instanceof ApiError ? error.message : "Break-glass request failed");
      toast.error(error instanceof ApiError ? error.message : "Break-glass request failed");
    },
  });

  function submit() {
    if (reason.trim().length < 50) {
      setFieldError("Reason must be at least 50 characters.");
      return;
    }

    if (!acknowledgment) {
      setFieldError("You must acknowledge the privacy policy before submitting.");
      return;
    }

    setFieldError(null);
    mutation.mutate();
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button variant="outline" className="w-full">
          <ShieldAlert className="mr-2 h-4 w-4" />
          Request break-glass
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Request break-glass access</DialogTitle>
          <DialogDescription>
            Submit an audited request to reveal the reporter identity for {registrationNumber}.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-2">
            <Label>Reason category</Label>
            <Select
              value={reasonCategory}
              onValueChange={(value) => setReasonCategory(value as BreakGlassReasonCategory)}
              disabled={mutation.isPending}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {REASON_CATEGORIES.map((category) => (
                  <SelectItem key={category.value} value={category.value}>
                    {category.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="break-glass-reason">Reason</Label>
            <Textarea
              id="break-glass-reason"
              value={reason}
              onChange={(event) => setReason(event.target.value)}
              minLength={50}
              maxLength={2000}
              disabled={mutation.isPending}
              placeholder="Describe the documented, policy-aligned reason for requesting exceptional access."
              className="min-h-32"
            />
            <div className="text-xs text-muted-foreground">{reason.trim().length}/2000 characters</div>
          </div>

          <label className="flex items-start gap-3 rounded-lg border p-3 text-sm">
            <Checkbox
              checked={acknowledgment}
              onCheckedChange={(checked) => setAcknowledgment(checked === true)}
              disabled={mutation.isPending}
            />
            <span>
              I acknowledge this is exceptional, audited access and must not be used for routine
              identity lookup.
            </span>
          </label>

          {fieldError && (
            <div className="rounded-lg border border-destructive/40 p-3 text-sm text-destructive">
              {fieldError}
            </div>
          )}
        </div>

        <DialogFooter>
          <Button onClick={submit} disabled={mutation.isPending}>
            {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            Submit request
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
