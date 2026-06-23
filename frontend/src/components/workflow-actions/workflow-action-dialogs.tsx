import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Ban, ClipboardEdit, FilePlus2, History, PenLine } from "lucide-react";
import { useState } from "react";
import { useForm } from "react-hook-form";
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
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
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
  INVESTIGATION_ACTIVITY_TYPES,
  labelOption,
} from "@/lib/workflow-action-options";

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
const evidenceMetadataSchema = z.object({
  evidence_type_code: z.string().min(1, "Required"),
  title: z.string().trim().min(1, "Required").max(255, "Maximum 255 characters"),
  description: optionalText,
  source: optionalText,
  collected_at: z.string().optional().refine((value) => !value || value <= today, "Date cannot be in the future"),
  classification: z.enum(EVIDENCE_CLASSIFICATIONS).optional(),
});
const evidenceStatusSchema = z.object({ status: z.enum(EVIDENCE_STATUSES) });

type CaseStatusValues = z.infer<typeof caseStatusSchema>;
type ActivityValues = z.infer<typeof activitySchema>;
type RecommendationValues = z.infer<typeof recommendationSchema>;
type DecisionValues = z.infer<typeof decisionSchema>;
type RecoveryMonitoringValues = z.infer<typeof recoveryMonitoringSchema>;
type EvidenceMetadataValues = z.infer<typeof evidenceMetadataSchema>;
type EvidenceStatusValues = z.infer<typeof evidenceStatusSchema>;

export function DisabledWorkflowAction({ title, description }: { title: string; description: string }) {
  return (
    <div className="rounded-lg border border-dashed p-3 text-sm">
      <div className="flex items-center gap-2 font-medium">
        <Ban className="h-4 w-4" /> {title}
      </div>
      <p className="mt-2 text-xs text-muted-foreground">{description}</p>
    </div>
  );
}

export function CaseStatusAction({
  caseId,
  currentStatus,
}: {
  caseId: number | string;
  currentStatus: string;
}) {
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
    onSuccess: () => {
      toast.success("Case status updated");
      setOpen(false);
      form.reset({ status: "" });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.case(caseId) });
      queryClient.invalidateQueries({ queryKey: ["operations", "cases"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, "Case status could not be updated"));
    },
  });

  const options = caseStatusOptions(statusesQuery.data ?? [], currentStatus);

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button className="w-full" variant="outline" disabled={statusesQuery.isLoading || options.length === 0}>
          <History className="mr-2 h-4 w-4" /> Update status
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Update case status</DialogTitle>
          <DialogDescription>Backend RBAC and transition rules remain authoritative.</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
            <FormField
              control={form.control}
              name="status"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Status</FormLabel>
                  <Select onValueChange={field.onChange} value={field.value}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Select status" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {options.map((item) => (
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
            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending ? "Saving..." : "Save status"}
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
    onSuccess: () => {
      toast.success("Investigation activity added");
      setOpen(false);
      form.reset();
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.investigations(caseId) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.investigation(investigation.id) });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, "Activity could not be added"));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          <FilePlus2 className="mr-2 h-4 w-4" /> Add activity
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Add investigation activity</DialogTitle>
          <DialogDescription>Activity is submitted to the approved backend endpoint.</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
            <SelectField form={form} name="activity_type" label="Activity type" options={INVESTIGATION_ACTIVITY_TYPES} />
            <InputField form={form} name="activity_date" label="Activity date" type="date" />
            <TextareaField form={form} name="description" label="Description" />
            <TextareaField form={form} name="findings" label="Findings" />
            <TextareaField form={form} name="notes" label="Notes" />
            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending ? "Saving..." : "Add activity"}
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
    onSuccess: () => {
      toast.success("Recommendation updated");
      setOpen(false);
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.recommendations(caseId) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.recommendation(recommendation.id) });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, "Recommendation could not be updated"));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          <PenLine className="mr-2 h-4 w-4" /> Edit
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Edit recommendation</DialogTitle>
          <DialogDescription>Only fields returned by the backend are editable here.</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
            <TextareaField form={form} name="conclusion" label="Conclusion" />
            <TextareaField form={form} name="recommended_actions" label="Recommended actions" />
            <TextareaField form={form} name="sanction_recommendation" label="Sanction recommendation" />
            <TextareaField form={form} name="recovery_recommendation" label="Recovery recommendation" />
            <TextareaField form={form} name="prevention_recommendation" label="Prevention recommendation" />
            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending ? "Saving..." : "Save recommendation"}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

export function DecisionUpdateAction({ decision }: { decision: Decision }) {
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
    onSuccess: () => {
      toast.success("Decision updated");
      setOpen(false);
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.decisions(decision.recommendation_id) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.decision(decision.id) });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, "Decision could not be updated"));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          <ClipboardEdit className="mr-2 h-4 w-4" /> Edit
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Edit decision</DialogTitle>
          <DialogDescription>Decision content can be edited while backend rules allow draft changes.</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
            <SelectField form={form} name="outcome_code" label="Outcome" options={DECISION_OUTCOMES} />
            <InputField form={form} name="decision_number" label="Decision number" />
            <InputField form={form} name="decision_date" label="Decision date" type="date" />
            <TextareaField form={form} name="decision_summary" label="Summary" />
            <TextareaField form={form} name="decision_content" label="Content" className="min-h-32" />
            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending ? "Saving..." : "Save decision"}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

export function RecoveryMonitoringAction({ recovery }: { recovery: Recovery }) {
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
    onSuccess: () => {
      toast.success("Recovery monitoring added");
      setOpen(false);
      form.reset();
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.recoveries(recovery.decision_id) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.recovery(recovery.id) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.recoveryMonitoring(recovery.id) });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, "Monitoring could not be added"));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          <FilePlus2 className="mr-2 h-4 w-4" /> Add monitoring
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Add recovery monitoring</DialogTitle>
          <DialogDescription>Monitoring creation is allowed only when backend RBAC permits it.</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
            <InputField form={form} name="monitoring_date" label="Monitoring date" type="date" />
            <TextareaField form={form} name="condition_summary" label="Condition summary" />
            <TextareaField form={form} name="follow_up_plan" label="Follow-up plan" />
            <TextareaField form={form} name="notes" label="Notes" />
            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending ? "Saving..." : "Add monitoring"}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

export function EvidenceMetadataAction({ evidence }: { evidence: EvidenceMetadata }) {
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const evidenceTypesQuery = useQuery({
    queryKey: masterDataQueryKeys.list("evidence-types"),
    queryFn: () => getMasterData("evidence-types"),
  });
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
    mutationFn: (values: EvidenceMetadataValues) => updateEvidenceMetadata(evidence.id, nullifyEmpty(values)),
    onSuccess: () => {
      toast.success("Evidence metadata updated");
      setOpen(false);
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.evidences(evidence.investigation_id) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.evidence(evidence.id) });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, "Evidence metadata could not be updated"));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          <PenLine className="mr-2 h-4 w-4" /> Edit metadata
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Edit evidence metadata</DialogTitle>
          <DialogDescription>No file upload, download, preview, or storage fields are included.</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
            <FormField
              control={form.control}
              name="evidence_type_code"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Evidence type</FormLabel>
                  <Select onValueChange={field.onChange} value={field.value} disabled={evidenceTypesQuery.isLoading}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Select evidence type" />
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
            <InputField form={form} name="title" label="Title" />
            <TextareaField form={form} name="description" label="Description" />
            <TextareaField form={form} name="source" label="Source" />
            <InputField form={form} name="collected_at" label="Collected at" type="date" />
            <SelectField form={form} name="classification" label="Classification" options={EVIDENCE_CLASSIFICATIONS} />
            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending ? "Saving..." : "Save metadata"}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

export function EvidenceStatusAction({ evidence }: { evidence: EvidenceMetadata }) {
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const form = useForm<EvidenceStatusValues>({
    resolver: zodResolver(evidenceStatusSchema),
    defaultValues: { status: asEvidenceStatus(evidence.status) },
  });
  const mutation = useMutation({
    mutationFn: (values: EvidenceStatusValues) => updateEvidenceStatus(evidence.id, values),
    onSuccess: () => {
      toast.success("Evidence status updated");
      setOpen(false);
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.evidences(evidence.investigation_id) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.evidence(evidence.id) });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, "Evidence status could not be updated"));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          <History className="mr-2 h-4 w-4" /> Status
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Update evidence status</DialogTitle>
          <DialogDescription>Backend transition rules remain authoritative.</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
            <SelectField form={form} name="status" label="Status" options={EVIDENCE_STATUSES} />
            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending ? "Saving..." : "Save status"}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

function InputField<T extends Record<string, unknown>>({
  form,
  name,
  label,
  type = "text",
}: {
  form: ReturnType<typeof useForm<T>>;
  name: keyof T & string;
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

function TextareaField<T extends Record<string, unknown>>({
  form,
  name,
  label,
  className,
}: {
  form: ReturnType<typeof useForm<T>>;
  name: keyof T & string;
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

function SelectField<T extends Record<string, unknown>>({
  form,
  name,
  label,
  options,
}: {
  form: ReturnType<typeof useForm<T>>;
  name: keyof T & string;
  label: string;
  options: readonly string[];
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
                <SelectValue placeholder={`Select ${label.toLowerCase()}`} />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              {options.map((option) => (
                <SelectItem key={option} value={option}>
                  {labelOption(option)}
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
  const currentItem = items.find((item) => item.code === current);
  const transitions = currentItem?.valid_transitions ?? [];
  const allowed = transitions.length > 0 ? transitions : items.map((item) => item.code).filter((code) => code !== current);

  return items.filter((item) => allowed.includes(item.code));
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
  return EVIDENCE_CLASSIFICATIONS.includes(value as EvidenceMetadataValues["classification"])
    ? (value as EvidenceMetadataValues["classification"])
    : "internal";
}

function asEvidenceStatus(value: string): EvidenceStatusValues["status"] {
  return EVIDENCE_STATUSES.includes(value as EvidenceStatusValues["status"])
    ? (value as EvidenceStatusValues["status"])
    : "registered";
}
