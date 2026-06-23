import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Gavel, Loader2 } from "lucide-react";
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
import { createDecision, operationsQueryKeys } from "@/lib/operations-api";
import type { Recommendation } from "@/lib/operations-types";
import { DECISION_OUTCOMES, labelOption } from "@/lib/workflow-action-options";

const today = new Date().toISOString().slice(0, 10);
const requiredDate = z.string().min(1, "Required").refine((value) => value <= today, "Date cannot be in the future");
const requiredText = z.string().trim().min(1, "Required").max(10000, "Maximum 10000 characters");

const decisionCreateSchema = z.object({
  outcome_code: z.enum(DECISION_OUTCOMES),
  decision_number: z.string().trim().max(100, "Maximum 100 characters").optional(),
  decision_date: requiredDate,
  decision_summary: requiredText,
  decision_content: z.string().trim().min(1, "Required").max(20000, "Maximum 20000 characters"),
});

type DecisionCreateValues = z.infer<typeof decisionCreateSchema>;

export function DecisionCreateAction({
  recommendation,
  caseId,
}: {
  recommendation: Recommendation;
  caseId: number | string;
}) {
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const form = useForm<DecisionCreateValues>({
    resolver: zodResolver(decisionCreateSchema),
    defaultValues: {
      outcome_code: "accepted",
      decision_number: "",
      decision_date: today,
      decision_summary: "",
      decision_content: "",
    },
  });

  const mutation = useMutation({
    mutationFn: (values: DecisionCreateValues) => createDecision(recommendation.id, nullifyEmpty(values)),
    onSuccess: () => {
      toast.success("Decision created");
      setOpen(false);
      form.reset();
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.case(caseId) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.recommendations(caseId) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.recommendation(recommendation.id) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.decisions(recommendation.id) });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["my-work"] });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, "Decision could not be created"));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button className="w-full" variant="outline">
          <Gavel className="mr-2 h-4 w-4" /> Create decision
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Create decision</DialogTitle>
          <DialogDescription>
            Record an institutional decision for the submitted recommendation.
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
            <FormField
              control={form.control}
              name="outcome_code"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Outcome</FormLabel>
                  <Select onValueChange={field.onChange} value={field.value}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Select outcome" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {DECISION_OUTCOMES.map((outcome) => (
                        <SelectItem key={outcome} value={outcome}>
                          {labelOption(outcome)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
            <InputField form={form} name="decision_number" label="Decision number" />
            <InputField form={form} name="decision_date" label="Decision date" type="date" />
            <TextareaField form={form} name="decision_summary" label="Summary" />
            <TextareaField form={form} name="decision_content" label="Content" className="min-h-32" />

            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                Create decision
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

function nullifyEmpty<T extends Record<string, unknown>>(values: T): T {
  return Object.fromEntries(
    Object.entries(values).map(([key, value]) => [key, value === "" ? null : value]),
  ) as T;
}
