import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Loader2, ShieldAlert } from "lucide-react";
import { useState } from "react";
import { useTranslation } from "react-i18next";
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
import { requestBreakGlass } from "@/lib/break-glass-api";
import type {
  BreakGlassDurationMinutes,
  BreakGlassReasonCategory,
} from "@/lib/break-glass-types";
import { apiErrorMessage } from "@/lib/form-errors";

const REASON_CATEGORIES: BreakGlassReasonCategory[] = [
  "legal_requirement",
  "safety_emergency",
  "investigation_necessity",
  "institutional_compliance",
  "victim_consent",
];

interface BreakGlassRequestDialogProps {
  caseId: number;
  registrationNumber: string;
  disabled?: boolean;
}

export function BreakGlassRequestDialog({
  caseId,
  registrationNumber,
  disabled = false,
}: BreakGlassRequestDialogProps) {
  const { t } = useTranslation(["dashboard"]);
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [reasonCategory, setReasonCategory] =
    useState<BreakGlassReasonCategory>("investigation_necessity");
  const [reason, setReason] = useState("");
  const [duration, setDuration] = useState<BreakGlassDurationMinutes>(60);
  const [acknowledgment, setAcknowledgment] = useState(false);
  const [fieldError, setFieldError] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: () =>
      requestBreakGlass({
        case_id: caseId,
        reason_category: reasonCategory,
        reason,
        requested_duration_minutes: duration,
        acknowledgment,
      }),
    onSuccess: () => {
      toast.success(t("dashboard:breakGlass.request.success"));
      setOpen(false);
      setReason("");
      setDuration(60);
      setAcknowledgment(false);
      setFieldError(null);
      queryClient.invalidateQueries({ queryKey: ["break-glass"] });
    },
    onError: (error) => {
      const message = apiErrorMessage(error, t("dashboard:breakGlass.request.error"));
      setFieldError(message);
      toast.error(message);
    },
  });

  function submit() {
    if (reason.trim().length < 50) {
      setFieldError(t("dashboard:breakGlass.request.reasonMin"));
      return;
    }

    if (!acknowledgment) {
      setFieldError(t("dashboard:breakGlass.request.acknowledgmentRequired"));
      return;
    }

    setFieldError(null);
    mutation.mutate();
  }

  function handleOpenChange(nextOpen: boolean) {
    setOpen(nextOpen);
    if (!nextOpen && !mutation.isPending) {
      setFieldError(null);
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger asChild>
        <Button variant="outline" className="w-full" disabled={disabled}>
          <ShieldAlert className="mr-2 h-4 w-4" />
          {t("dashboard:breakGlass.request.trigger")}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t("dashboard:breakGlass.request.title")}</DialogTitle>
          <DialogDescription>
            {t("dashboard:breakGlass.request.description", { registrationNumber })}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-2">
            <Label>{t("dashboard:breakGlass.request.category")}</Label>
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
                  <SelectItem key={category} value={category}>
                    {t(`dashboard:breakGlass.reasonCategories.${category}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label>{t("dashboard:breakGlass.request.duration")}</Label>
            <Select
              value={String(duration)}
              onValueChange={(value) => setDuration(Number(value) as BreakGlassDurationMinutes)}
              disabled={mutation.isPending}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {[30, 60, 240, 1440].map((minutes) => (
                  <SelectItem key={minutes} value={String(minutes)}>
                    {t(`dashboard:breakGlass.duration.${minutes}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <p className="text-xs text-muted-foreground">
              {t("dashboard:breakGlass.request.durationHint")}
            </p>
          </div>

          <div className="space-y-2">
            <Label htmlFor="break-glass-reason">{t("dashboard:breakGlass.request.reason")}</Label>
            <Textarea
              id="break-glass-reason"
              value={reason}
              onChange={(event) => setReason(event.target.value)}
              minLength={50}
              maxLength={2000}
              disabled={mutation.isPending}
              placeholder={t("dashboard:breakGlass.request.reasonPlaceholder")}
              className="min-h-32"
            />
            <div className="text-xs text-muted-foreground">
              {t("dashboard:breakGlass.request.characterCount", { count: reason.trim().length, max: 2000 })}
            </div>
          </div>

          <label className="flex items-start gap-3 rounded-lg border p-3 text-sm">
            <Checkbox
              checked={acknowledgment}
              onCheckedChange={(checked) => setAcknowledgment(checked === true)}
              disabled={mutation.isPending}
            />
            <span>
              {t("dashboard:breakGlass.request.acknowledgment")}
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
            {t("dashboard:breakGlass.request.submit")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
