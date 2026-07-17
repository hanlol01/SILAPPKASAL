import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { FilePlus2 } from "lucide-react";
import { useState } from "react";
import { useForm } from "react-hook-form";
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
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import { formatEvidenceClassification, formatEvidenceType } from "@/lib/format-labels";
import { getMasterData, masterDataQueryKeys } from "@/lib/master-data-api";
import { createEvidence, operationsQueryKeys } from "@/lib/operations-api";
import type { EvidenceCreatePayload, Investigation } from "@/lib/operations-types";
import { EVIDENCE_CLASSIFICATIONS } from "@/lib/workflow-action-options";

const today = new Date().toISOString().slice(0, 10);

type EvidenceClassificationOption = (typeof EVIDENCE_CLASSIFICATIONS)[number];

interface EvidenceCreateValues {
  evidence_type_code: string;
  title: string;
  description?: string;
  source?: string;
  collected_at?: string;
  classification: EvidenceClassificationOption;
}

const defaultValues: EvidenceCreateValues = {
  evidence_type_code: "",
  title: "",
  description: "",
  source: "",
  collected_at: "",
  classification: "confidential",
};

/**
 * REV-04 "Tambah Bukti" action for the assigned Satgas.
 * Records evidence metadata against the case investigation through the
 * existing evidence creation flow. No file upload, download, preview, or
 * storage behavior exists in this phase.
 */
export function EvidenceCreateAction({ investigation }: { investigation: Investigation }) {
  const { t } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const evidenceTypesQuery = useQuery({
    queryKey: masterDataQueryKeys.list("evidence-types"),
    queryFn: () => getMasterData("evidence-types"),
  });

  const schema = z.object({
    evidence_type_code: z.string().min(1, t("dashboard:workflow.required")),
    title: z
      .string()
      .trim()
      .min(1, t("dashboard:workflow.required"))
      .max(255, t("dashboard:workflow.max255")),
    description: z.string().trim().max(10000, t("dashboard:workflow.max10000")).optional(),
    source: z.string().trim().max(10000, t("dashboard:workflow.max10000")).optional(),
    collected_at: z
      .string()
      .optional()
      .refine((value) => !value || value <= today, t("dashboard:workflow.dateFuture")),
    classification: z.enum(EVIDENCE_CLASSIFICATIONS, {
      errorMap: () => ({ message: t("dashboard:workflow.required") }),
    }),
  });

  const form = useForm<EvidenceCreateValues>({
    resolver: zodResolver(schema),
    defaultValues,
  });

  const mutation = useMutation({
    mutationFn: (values: EvidenceCreateValues) => createEvidence(investigation.id, toPayload(values)),
    onSuccess: () => {
      toast.success(t("dashboard:workflow.evidenceCreated"));
      setOpen(false);
      form.reset(defaultValues);
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.evidences(investigation.id) });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.evidenceCreateError")));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" disabled={evidenceTypesQuery.isLoading}>
          <FilePlus2 className="mr-2 h-4 w-4" /> {t("dashboard:workflow.addEvidence")}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t("dashboard:workflow.addEvidence")}</DialogTitle>
          <DialogDescription>{t("dashboard:workflow.addEvidenceDesc")}</DialogDescription>
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
                          {formatEvidenceType(t, item.code ?? item.name)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="title"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("dashboard:workflow.title")}</FormLabel>
                  <FormControl>
                    <Input {...field} value={field.value ?? ""} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="description"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("dashboard:workflow.description")}</FormLabel>
                  <FormControl>
                    <Textarea {...field} value={field.value ?? ""} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="source"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("dashboard:workflow.source")}</FormLabel>
                  <FormControl>
                    <Textarea {...field} value={field.value ?? ""} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="collected_at"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("dashboard:workflow.collectedAt")}</FormLabel>
                  <FormControl>
                    <DatePicker
                      value={field.value ?? ""}
                      onChange={field.onChange}
                      placeholder={t("dashboard:workflow.collectedAt")}
                      disableFuture
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="classification"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("dashboard:workflow.classification")}</FormLabel>
                  <Select onValueChange={field.onChange} value={field.value}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder={t("dashboard:workflow.classification")} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {EVIDENCE_CLASSIFICATIONS.map((option) => (
                        <SelectItem key={option} value={option}>
                          {formatEvidenceClassification(t, option)}
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
                {mutation.isPending ? t("dashboard:common.saving") : t("dashboard:workflow.addEvidence")}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

function toPayload(values: EvidenceCreateValues): EvidenceCreatePayload {
  return {
    evidence_type_code: values.evidence_type_code,
    title: values.title,
    description: values.description?.trim() ? values.description : null,
    source: values.source?.trim() ? values.source : null,
    collected_at: values.collected_at ? values.collected_at : null,
    classification: values.classification,
  };
}
