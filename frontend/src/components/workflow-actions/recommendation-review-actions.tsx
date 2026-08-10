import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient, type QueryClient } from "@tanstack/react-query";
import { CheckCircle2, Loader2, RotateCcw, Send } from "lucide-react";
import { useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { z } from "zod";
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
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Textarea } from "@/components/ui/textarea";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import {
  operationsQueryKeys,
  reviewRecommendation,
  submitRecommendation,
} from "@/lib/operations-api";
import type { Recommendation, RecommendationReviewPayload } from "@/lib/operations-types";
import { synchronizeWorkflowCaches } from "@/lib/workflow-cache-sync";

export function RecommendationSubmitAction({
  recommendation,
  caseId,
}: {
  recommendation: Recommendation;
  caseId: string | number;
}) {
  const { t } = useTranslation(["dashboard", "common"]);
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const mutation = useMutation({
    mutationFn: () => submitRecommendation(recommendation.id),
    onSuccess: async () => {
      await synchronizeRecommendationState(queryClient, caseId, recommendation.id);
      setOpen(false);
      toast.success(t("dashboard:workflow.recommendationSubmitted"));
    },
    onError: (error) => {
      toast.error(apiErrorMessage(error, t("dashboard:workflow.recommendationSubmitError")));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          <Send className="mr-2 h-4 w-4" />
          {t("dashboard:workflow.submitRecommendation")}
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("dashboard:workflow.submitRecommendation")}</DialogTitle>
          <DialogDescription>{t("dashboard:workflow.submitRecommendationDesc")}</DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button
            type="button"
            disabled={mutation.isPending}
            onClick={() => {
              if (!mutation.isPending) mutation.mutate();
            }}
          >
            {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            {mutation.isPending
              ? t("dashboard:common.saving")
              : t("dashboard:workflow.submitRecommendation")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export function RecommendationReviewActions({
  recommendation,
  caseId,
}: {
  recommendation: Recommendation;
  caseId: string | number;
}) {
  const { t } = useTranslation(["dashboard"]);
  const [approveOpen, setApproveOpen] = useState(false);
  const [returnOpen, setReturnOpen] = useState(false);
  const queryClient = useQueryClient();
  const returnSchema = useMemo(
    () =>
      z.object({
        revision_note: z
          .string()
          .trim()
          .min(1, t("common:validation.required"))
          .max(5000, t("dashboard:workflow.revisionNoteMax")),
      }),
    [t],
  );
  type ReturnValues = z.infer<typeof returnSchema>;
  const form = useForm<ReturnValues>({
    resolver: zodResolver(returnSchema),
    defaultValues: { revision_note: "" },
  });
  const mutation = useMutation({
    mutationFn: (payload: RecommendationReviewPayload) =>
      reviewRecommendation(recommendation.id, payload),
    onSuccess: async (_result, payload) => {
      await synchronizeRecommendationState(queryClient, caseId, recommendation.id);
      form.reset();
      setApproveOpen(false);
      setReturnOpen(false);
      toast.success(
        payload.action === "approve"
          ? t("dashboard:workflow.recommendationApproved")
          : t("dashboard:workflow.recommendationReturned"),
      );
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.recommendationReviewError")));
    },
  });

  return (
    <div className="flex min-w-0 flex-wrap gap-2">
      <Dialog open={approveOpen} onOpenChange={setApproveOpen}>
        <DialogTrigger asChild>
          <Button size="sm">
            <CheckCircle2 className="mr-2 h-4 w-4" />
            {t("dashboard:workflow.approveRecommendation")}
          </Button>
        </DialogTrigger>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("dashboard:workflow.approveRecommendation")}</DialogTitle>
            <DialogDescription>{t("dashboard:workflow.approveRecommendationDesc")}</DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              type="button"
              disabled={mutation.isPending}
              onClick={() => {
                if (!mutation.isPending) mutation.mutate({ action: "approve" });
              }}
            >
              {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {mutation.isPending
                ? t("dashboard:common.saving")
                : t("dashboard:workflow.approveRecommendation")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={returnOpen} onOpenChange={setReturnOpen}>
        <DialogTrigger asChild>
          <Button size="sm" variant="outline">
            <RotateCcw className="mr-2 h-4 w-4" />
            {t("dashboard:workflow.returnRecommendation")}
          </Button>
        </DialogTrigger>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("dashboard:workflow.returnRecommendation")}</DialogTitle>
            <DialogDescription>{t("dashboard:workflow.returnRecommendationDesc")}</DialogDescription>
          </DialogHeader>
          <Form {...form}>
            <form
              className="space-y-4"
              onSubmit={form.handleSubmit((values) => {
                if (!mutation.isPending) {
                  mutation.mutate({ action: "return_for_revision", ...values });
                }
              })}
            >
              <FormField
                control={form.control}
                name="revision_note"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("dashboard:workflow.revisionNote")}</FormLabel>
                    <FormControl>
                      <Textarea {...field} className="min-h-32" />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <DialogFooter>
                <Button type="submit" variant="destructive" disabled={mutation.isPending}>
                  {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                  {mutation.isPending
                    ? t("dashboard:common.saving")
                    : t("dashboard:workflow.returnRecommendation")}
                </Button>
              </DialogFooter>
            </form>
          </Form>
        </DialogContent>
      </Dialog>
    </div>
  );
}

async function synchronizeRecommendationState(
  queryClient: QueryClient,
  caseId: string | number,
  recommendationId: string | number,
) {
  await synchronizeWorkflowCaches(queryClient, {
    caseId,
    exactKeys: [
      operationsQueryKeys.recommendations(caseId),
      operationsQueryKeys.recommendation(recommendationId),
      operationsQueryKeys.recommendationStatusOptions(recommendationId),
      operationsQueryKeys.decisions(recommendationId),
    ],
  });
}
