import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ClipboardEdit, FilePlus2, History, Loader2, PenLine } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useForm, type FieldValues, type Path, type UseFormReturn } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
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
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import {
  formatCaseStatus,
  formatDecisionOutcome,
  formatEvidenceClassification,
  formatEvidenceStatus,
  formatGenericLabel,
} from "@/lib/format-labels";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import { getMasterData, masterDataQueryKeys } from "@/lib/master-data-api";
import {
  createInvestigationActivity,
  createRecoveryMonitoring,
  operationsQueryKeys,
  updateCaseStatus,
  updateDecision,
  updateEvidenceMetadata,
  updateEvidenceStatus,
  updateRecommendation,
} from "@/lib/operations-api";
import type {
  Decision,
  EvidenceMetadata,
  Investigation,
  Recommendation,
  Recovery,
} from "@/lib/operations-types";
import {
  DECISION_OUTCOMES,
  EVIDENCE_CLASSIFICATIONS,
  EVIDENCE_STATUSES,
  EVIDENCE_STATUS_TRANSITIONS,
  INVESTIGATION_ACTIVITY_TYPES,
  labelOption,
} from "@/lib/workflow-action-options";
import { synchronizeWorkflowCaches } from "@/lib/workflow-cache-sync";

const today = new Date().toISOString().slice(0, 10);
const requiredText = z.string().trim().min(1, "Required").max(10000, "Maximum 10000 characters");
const optionalText = z.string().trim().max(10000, "Maximum 10000 characters").optional();
const requiredDate = z.string().min(1, "Required").refine((value) => value <= today, "Date cannot be in the future");

const caseStatusSchema = z.object({ status: z.string().min(1, "Required") });
const activitySchema = z.object({
  activity_type: z.enum(INVESTIGATION_ACTIVITY_TYPES),
  activity_date: requiredDate,
  description: requiredText,
  findings: optionalText,
  notes: optionalText,
});
const recommendationSchema = z.object({
  conclusion: requiredText,
  recommended_actions: requiredText,
  sanction_recommendation: optionalText,
  recovery_recommendation: optionalText,
  prevention_recommendation: optionalText,
});
const decisionSchema = z.object({
  outcome_code: z.enum(DECISION_OUTCOMES),
  decision_number: z.string().trim().max(100, "Maximum 100 characters").optional(),
  decision_date: requiredDate,
  decision_summary: requiredText,
  decision_content: z.string().trim().min(1, "Required").max(20000, "Maximum 20000 characters"),
});
const recoveryMonitoringSchema = z.object({
  monitoring_date: requiredDate,
  condition_summary: requiredText,
  follow_up_plan: optionalText,
  notes: optionalText,
});

type CaseStatusValues = z.infer<typeof caseStatusSchema>;
type ActivityValues = z.infer<typeof activitySchema>;
type RecommendationValues = z.infer<typeof recommendationSchema>;
type DecisionValues = z.infer<typeof decisionSchema>;
type RecoveryMonitoringValues = z.infer<typeof recoveryMonitoringSchema>;
interface EvidenceMetadataValues {
  evidence_type_code: string;
  title: string;
  description?: string;
  source?: string;
  collected_at?: string;
  classification?: (typeof EVIDENCE_CLASSIFICATIONS)[number];
}
interface EvidenceStatusValues {
  status: string;
}

export function CaseStatusAction({
  caseId,
  currentStatus,
  onAvailabilityChange,
}: {
  caseId: number | string;
  currentStatus: string;
  onAvailabilityChange?: (available: boolean) => void;
}) {
  const { t } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const statusesQuery = useQuery({
    queryKey: masterDataQueryKeys.list("case-statuses"),
    queryFn: () => getMasterData("case-statuses"),
  });
  const form = useForm<CaseStatusValues>({
    resolver: zodResolver(caseStatusSchema),
    defaultValues: { status: "" },
  });
  const mutation = useMutation({
    mutationFn: (values: CaseStatusValues) => updateCaseStatus(caseId, values),
    onSuccess: async () => {
      await synchronizeWorkflowCaches(queryClient, { caseId });
      form.reset({ status: "" });
      setOpen(false);
      toast.success(t("dashboard:workflow.caseStatusUpdated"));
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.caseStatusError")));
    },
  });

  const options = useMemo(
    () => caseStatusOptions(statusesQuery.data ?? [], currentStatus),
    [currentStatus, statusesQuery.data],
  );

  useEffect(() => {
    if (!statusesQuery.isSuccess) return;
    const selected = form.getValues("status");
    if (selected && !options.some((option) => option.code === selected || option.name === selected)) {
      form.resetField("status");
    }
  }, [form, options, statusesQuery.isSuccess]);

  useEffect(() => {
    if (statusesQuery.isSuccess) {
      onAvailabilityChange?.(options.length > 0);
      return;
    }

    onAvailabilityChange?.(statusesQuery.isPending);
  }, [onAvailabilityChange, options.length, statusesQuery.isPending, statusesQuery.isSuccess]);

  if (statusesQuery.isPending) {
    return (
      <Button className="w-full" variant="outline" disabled>
        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
        {t("dashboard:workflow.loadingStatuses")}
      </Button>
    );
  }

  if (statusesQuery.isError && !open) return null;
  if (statusesQuery.isSuccess && options.length === 0 && !open) return null;

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button className="w-full" variant="outline" disabled={statusesQuery.isLoading || options.length === 0}>
          <History className="mr-2 h-4 w-4" /> {t("dashboard:workflow.updateCaseStatus")}
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("dashboard:workflow.updateCaseStatus")}</DialogTitle>
          <DialogDescription>{t("dashboard:workflow.backendAuthoritative")}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => {
            if (!mutation.isPending) mutation.mutate(values);
          })}>
            <FormField
              control={form.control}
              name="status"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("dashboard:workflow.status")}</FormLabel>
                  <Select onValueChange={field.onChange} value={field.value} disabled={mutation.isPending || options.length === 0}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder={t("dashboard:workflow.selectStatus")} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {options.map((item) => (
                        <SelectItem key={item.code} value={item.code}>
                          {formatCaseStatus(t, item.name)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending || options.length === 0}>
                {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {mutation.isPending ? t("dashboard:common.saving") : t("dashboard:workflow.saveStatus")}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

export function InvestigationActivityAction({
  investigation,
  caseId,
}: {
  investigation: Investigation;
  caseId: number | string;
}) {
  const { t } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const form = useForm<ActivityValues>({
    resolver: zodResolver(activitySchema),
    defaultValues: {
      activity_type: "case_review",
      activity_date: today,
      description: "",
      findings: "",
      notes: "",
    },
  });
  const mutation = useMutation({
    mutationFn: (values: ActivityValues) =>
      createInvestigationActivity(investigation.id, nullifyEmpty(values)),
    onSuccess: async () => {
      await synchronizeWorkflowCaches(queryClient, {
        caseId,
        exactKeys: [
          operationsQueryKeys.investigations(caseId),
          operationsQueryKeys.investigation(investigation.id),
        ],
      });
      form.reset();
      setOpen(false);
      toast.success(t("dashboard:workflow.activityAdded"));
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.activityError")));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          <FilePlus2 className="mr-2 h-4 w-4" /> {t("dashboard:workflow.addActivity")}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t("dashboard:workflow.addActivity")}</DialogTitle>
          <DialogDescription>{t("dashboard:workflow.activityEndpointDesc")}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
            <form className="space-y-4" onSubmit={form.handleSubmit((values) => {
              if (!mutation.isPending) mutation.mutate(values);
            })}>
            <SelectField form={form} name="activity_type" label={t("dashboard:workflow.activityType")} options={INVESTIGATION_ACTIVITY_TYPES} formatter={(value) => formatGenericLabel(value)} />
            <DatePickerField form={form} name="activity_date" label={t("dashboard:workflow.activityDate")} disableFuture />
            <TextareaField form={form} name="description" label={t("dashboard:workflow.description")} />
            <TextareaField form={form} name="findings" label={t("dashboard:workflow.findings")} />
            <TextareaField form={form} name="notes" label={t("dashboard:workflow.notes")} />
            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {mutation.isPending ? t("dashboard:common.saving") : t("dashboard:workflow.addActivity")}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

export function RecommendationUpdateAction({
  recommendation,
  caseId,
}: {
  recommendation: Recommendation;
  caseId: number | string;
}) {
  const { t } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const form = useForm<RecommendationValues>({
    resolver: zodResolver(recommendationSchema),
    defaultValues: {
      conclusion: recommendation.conclusion ?? "",
      recommended_actions: recommendation.recommended_actions ?? "",
      sanction_recommendation: recommendation.sanction_recommendation ?? "",
      recovery_recommendation: recommendation.recovery_recommendation ?? "",
      prevention_recommendation: recommendation.prevention_recommendation ?? "",
    },
  });
  const mutation = useMutation({
    mutationFn: (values: RecommendationValues) => updateRecommendation(recommendation.id, nullifyEmpty(values)),
    onSuccess: async () => {
      await synchronizeWorkflowCaches(queryClient, {
        caseId,
        exactKeys: [
          operationsQueryKeys.recommendations(caseId),
          operationsQueryKeys.recommendation(recommendation.id),
        ],
      });
      setOpen(false);
      toast.success(t("dashboard:workflow.recommendationUpdated"));
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.recommendationUpdateError")));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          <PenLine className="mr-2 h-4 w-4" /> {t("dashboard:common.edit")}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t("dashboard:workflow.editRecommendation")}</DialogTitle>
          <DialogDescription>{t("dashboard:workflow.editableBackendFields")}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => {
            if (!mutation.isPending) mutation.mutate(values);
          })}>
            <TextareaField form={form} name="conclusion" label={t("dashboard:sections.conclusion")} />
            <TextareaField form={form} name="recommended_actions" label={t("dashboard:sections.recommendedActions")} />
            <TextareaField form={form} name="sanction_recommendation" label={t("dashboard:sections.sanction")} />
            <TextareaField form={form} name="recovery_recommendation" label={t("dashboard:sections.recovery")} />
            <TextareaField form={form} name="prevention_recommendation" label={t("dashboard:sections.prevention")} />
            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {mutation.isPending ? t("dashboard:common.saving") : t("dashboard:workflow.saveRecommendation")}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

export function DecisionUpdateAction({
  decision,
  caseId,
}: {
  decision: Decision;
  caseId: string | number;
}) {
  const { t } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const form = useForm<DecisionValues>({
    resolver: zodResolver(decisionSchema),
    defaultValues: {
      outcome_code: asDecisionOutcome(decision.outcome_code),
      decision_number: decision.decision_number ?? "",
      decision_date: dateInput(decision.decision_date) || today,
      decision_summary: decision.decision_summary ?? "",
      decision_content: decision.decision_content ?? "",
    },
  });
  const mutation = useMutation({
    mutationFn: (values: DecisionValues) => updateDecision(decision.id, nullifyEmpty(values)),
    onSuccess: async () => {
      await synchronizeWorkflowCaches(queryClient, {
        caseId,
        exactKeys: [
          operationsQueryKeys.decisions(decision.recommendation_id),
          operationsQueryKeys.decision(decision.id),
        ],
      });
      setOpen(false);
      toast.success(t("dashboard:workflow.decisionUpdated"));
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.decisionUpdateError")));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          <ClipboardEdit className="mr-2 h-4 w-4" /> {t("dashboard:common.edit")}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t("dashboard:workflow.editDecision")}</DialogTitle>
          <DialogDescription>{t("dashboard:workflow.decisionDraftDesc")}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => {
            if (!mutation.isPending) mutation.mutate(values);
          })}>
            <SelectField form={form} name="outcome_code" label={t("dashboard:workflow.outcome")} options={DECISION_OUTCOMES} formatter={(value) => formatDecisionOutcome(t, value)} />
            <InputField form={form} name="decision_number" label={t("dashboard:workflow.decisionNumber")} />
            <DatePickerField form={form} name="decision_date" label={t("dashboard:workflow.decisionDate")} disableFuture />
            <TextareaField form={form} name="decision_summary" label={t("dashboard:workflow.summary")} />
            <TextareaField form={form} name="decision_content" label={t("dashboard:workflow.content")} className="min-h-32" />
            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {mutation.isPending ? t("dashboard:common.saving") : t("dashboard:workflow.saveDecision")}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

export function RecoveryMonitoringAction({
  recovery,
  caseId,
}: {
  recovery: Recovery;
  caseId: string | number;
}) {
  const { t } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const form = useForm<RecoveryMonitoringValues>({
    resolver: zodResolver(recoveryMonitoringSchema),
    defaultValues: {
      monitoring_date: today,
      condition_summary: "",
      follow_up_plan: "",
      notes: "",
    },
  });
  const mutation = useMutation({
    mutationFn: (values: RecoveryMonitoringValues) => createRecoveryMonitoring(recovery.id, nullifyEmpty(values)),
    onSuccess: async () => {
      await synchronizeWorkflowCaches(queryClient, {
        caseId,
        exactKeys: [
          operationsQueryKeys.recoveries(recovery.decision_id),
          operationsQueryKeys.recovery(recovery.id),
          operationsQueryKeys.recoveryMonitoring(recovery.id),
        ],
      });
      form.reset();
      setOpen(false);
      toast.success(t("dashboard:workflow.monitoringAdded"));
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.monitoringError")));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          <FilePlus2 className="mr-2 h-4 w-4" /> {t("dashboard:workflow.addMonitoring")}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t("dashboard:workflow.addMonitoring")}</DialogTitle>
          <DialogDescription>{t("dashboard:workflow.monitoringAllowedDesc")}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => {
            if (!mutation.isPending) mutation.mutate(values);
          })}>
            <DatePickerField form={form} name="monitoring_date" label={t("dashboard:workflow.monitoringDate")} disableFuture />
            <TextareaField form={form} name="condition_summary" label={t("dashboard:workflow.conditionSummary")} />
            <TextareaField form={form} name="follow_up_plan" label={t("dashboard:workflow.followUpPlan")} />
            <TextareaField form={form} name="notes" label={t("dashboard:workflow.notes")} />
            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {mutation.isPending ? t("dashboard:common.saving") : t("dashboard:workflow.addMonitoring")}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

export function EvidenceMetadataAction({ evidence }: { evidence: EvidenceMetadata }) {
  const { t } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const evidenceTypesQuery = useQuery({
    queryKey: masterDataQueryKeys.list("evidence-types"),
    queryFn: () => getMasterData("evidence-types"),
  });
  const evidenceMetadataSchema = useMemo(
    () =>
      z.object({
        evidence_type_code: z.string().min(1, t("dashboard:workflow.required")),
        title: z.string().trim().min(1, t("dashboard:workflow.required")).max(255, t("dashboard:workflow.max255")),
        description: z.string().trim().max(10000, t("dashboard:workflow.max10000")).optional(),
        source: z.string().trim().max(10000, t("dashboard:workflow.max10000")).optional(),
        collected_at: z
          .string()
          .optional()
          .refine((value) => !value || value <= today, t("dashboard:workflow.dateFuture")),
        classification: z.enum(EVIDENCE_CLASSIFICATIONS).optional(),
      }),
    [t],
  );
  const form = useForm<EvidenceMetadataValues>({
    resolver: zodResolver(evidenceMetadataSchema),
    defaultValues: {
      evidence_type_code: evidence.evidence_type?.code ?? "",
      title: evidence.title,
      description: evidence.description ?? "",
      source: evidence.source ?? "",
      collected_at: dateInput(evidence.collected_at),
      classification: asEvidenceClassification(evidence.classification),
    },
  });
  const mutation = useMutation({
    mutationFn: (values: EvidenceMetadataValues) =>
      updateEvidenceMetadata(evidence.id, nullifyEmpty({ ...values })),
    onSuccess: () => {
      toast.success(t("dashboard:workflow.evidenceUpdated"));
      setOpen(false);
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.evidences(evidence.investigation_id) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.evidence(evidence.id) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.evidenceCustody(evidence.id) });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.evidenceError")));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          <PenLine className="mr-2 h-4 w-4" /> {t("dashboard:workflow.editMetadata")}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t("dashboard:workflow.editMetadata")}</DialogTitle>
          <DialogDescription>{t("dashboard:workflow.evidenceMetadataDesc")}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
            <FormField
              control={form.control}
              name="evidence_type_code"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("dashboard:workflow.evidenceType")}</FormLabel>
                  <Select onValueChange={field.onChange} value={field.value} disabled={evidenceTypesQuery.isLoading}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder={t("dashboard:workflow.selectEvidenceType")} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {(evidenceTypesQuery.data ?? []).map((item) => (
                        <SelectItem key={item.code} value={item.code}>
                          {item.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
            <InputField form={form} name="title" label={t("dashboard:workflow.title")} />
            <TextareaField form={form} name="description" label={t("dashboard:workflow.description")} />
            <TextareaField form={form} name="source" label={t("dashboard:workflow.source")} />
            <DatePickerField form={form} name="collected_at" label={t("dashboard:workflow.collectedAt")} disableFuture />
            <SelectField form={form} name="classification" label={t("dashboard:workflow.classification")} options={EVIDENCE_CLASSIFICATIONS} formatter={(value) => formatEvidenceClassification(t, value)} />
            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending ? t("dashboard:common.saving") : t("dashboard:workflow.saveMetadata")}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

export function EvidenceStatusAction({ evidence }: { evidence: EvidenceMetadata }) {
  const { t } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const transitions = EVIDENCE_STATUS_TRANSITIONS[evidence.status] ?? [];
  const evidenceStatusSchema = useMemo(
    () => z.object({ status: z.string().min(1, t("dashboard:workflow.required")) }),
    [t],
  );
  const form = useForm<EvidenceStatusValues>({
    resolver: zodResolver(evidenceStatusSchema),
    defaultValues: { status: "" },
  });
  const mutation = useMutation({
    mutationFn: (values: EvidenceStatusValues) => updateEvidenceStatus(evidence.id, values),
    onSuccess: () => {
      toast.success(t("dashboard:workflow.evidenceStatusUpdated"));
      setOpen(false);
      form.reset({ status: "" });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.evidences(evidence.investigation_id) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.evidence(evidence.id) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.evidenceCustody(evidence.id) });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.evidenceStatusError")));
    },
  });

  if (transitions.length === 0) {
    return null;
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          <History className="mr-2 h-4 w-4" /> {t("dashboard:workflow.status")}
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("dashboard:workflow.updateEvidenceStatus")}</DialogTitle>
          <DialogDescription>{t("dashboard:workflow.validTransitionsOnly")}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
            <SelectField form={form} name="status" label={t("dashboard:workflow.status")} options={transitions} formatter={(value) => formatEvidenceStatus(t, value)} />
            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending ? t("dashboard:common.saving") : t("dashboard:workflow.saveStatus")}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

function InputField<T extends FieldValues>({
  form,
  name,
  label,
  type = "text",
}: {
  form: UseFormReturn<T>;
  name: Path<T>;
  label: string;
  type?: string;
}) {
  return (
    <FormField
      control={form.control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel>{label}</FormLabel>
          <FormControl>
            <Input type={type} {...field} value={(field.value as string | undefined) ?? ""} />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

function DatePickerField<T extends FieldValues>({
  form,
  name,
  label,
  disableFuture,
}: {
  form: UseFormReturn<T>;
  name: Path<T>;
  label: string;
  disableFuture?: boolean;
}) {
  return (
    <FormField
      control={form.control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel>{label}</FormLabel>
          <FormControl>
            <DatePicker
              value={(field.value as string | undefined) ?? ""}
              onChange={field.onChange}
              placeholder={label}
              disableFuture={disableFuture}
            />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

function TextareaField<T extends FieldValues>({
  form,
  name,
  label,
  className,
}: {
  form: UseFormReturn<T>;
  name: Path<T>;
  label: string;
  className?: string;
}) {
  return (
    <FormField
      control={form.control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel>{label}</FormLabel>
          <FormControl>
            <Textarea {...field} value={(field.value as string | undefined) ?? ""} className={className} />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

function SelectField<T extends FieldValues>({
  form,
  name,
  label,
  options,
  formatter,
}: {
  form: UseFormReturn<T>;
  name: Path<T>;
  label: string;
  options: readonly string[];
  formatter?: (value: string) => string;
}) {
  return (
    <FormField
      control={form.control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel>{label}</FormLabel>
          <Select onValueChange={field.onChange} value={(field.value as string | undefined) ?? ""}>
            <FormControl>
              <SelectTrigger>
                <SelectValue placeholder={label} />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              {options.map((option) => (
                <SelectItem key={option} value={option}>
                  {formatter ? formatter(option) : labelOption(option)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

function caseStatusOptions(items: { code: string; name: string; valid_transitions?: string[] | null }[], current: string) {
  const currentItem = items.find((item) => matchesCaseStatus(item, current));
  const transitions = currentItem?.valid_transitions ?? [];

  if (transitions.length === 0) return [];

  const allowed = new Set(transitions.map(normalizeCaseStatusKey));

  return items.filter((item) => allowed.has(normalizeCaseStatusKey(item.code)) || allowed.has(normalizeCaseStatusKey(item.name)));
}

function matchesCaseStatus(item: { code: string; name: string }, value: string) {
  const normalizedValue = normalizeCaseStatusKey(value);

  return normalizeCaseStatusKey(item.code) === normalizedValue || normalizeCaseStatusKey(item.name) === normalizedValue;
}

function normalizeCaseStatusKey(value: string) {
  return value.trim().toLowerCase();
}

function nullifyEmpty<T extends Record<string, unknown>>(values: T): T {
  return Object.fromEntries(
    Object.entries(values).map(([key, value]) => [key, value === "" ? null : value]),
  ) as T;
}

function dateInput(value: string | null | undefined) {
  return value ? value.slice(0, 10) : "";
}

function asDecisionOutcome(value: string): DecisionValues["outcome_code"] {
  return DECISION_OUTCOMES.includes(value as DecisionValues["outcome_code"])
    ? (value as DecisionValues["outcome_code"])
    : "accepted";
}

function asEvidenceClassification(value: string | null | undefined): EvidenceMetadataValues["classification"] {
  type EvidenceClassificationValue = NonNullable<EvidenceMetadataValues["classification"]>;
  const normalized = (value ?? "") as EvidenceClassificationValue;

  return EVIDENCE_CLASSIFICATIONS.includes(normalized)
    ? normalized
    : "internal";
}

function asEvidenceStatus(value: string): EvidenceStatusValues["status"] {
  const status = value as (typeof EVIDENCE_STATUSES)[number];
  return EVIDENCE_STATUSES.includes(status) ? status : "registered";
}
