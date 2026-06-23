import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { ClipboardList, Loader2 } from "lucide-react";
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
import { Textarea } from "@/components/ui/textarea";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import { createRecommendation, operationsQueryKeys } from "@/lib/operations-api";
import type { Investigation } from "@/lib/operations-types";

const requiredText = z.string().trim().min(1, "Required").max(10000, "Maximum 10000 characters");
const optionalText = z.string().trim().max(10000, "Maximum 10000 characters").optional();

const recommendationCreateSchema = z.object({
  conclusion: requiredText,
  recommended_actions: requiredText,
  sanction_recommendation: optionalText,
  recovery_recommendation: optionalText,
  prevention_recommendation: optionalText,
});

type RecommendationCreateValues = z.infer<typeof recommendationCreateSchema>;

export function RecommendationCreateAction({
  caseId,
  investigation,
}: {
  caseId: number | string;
  investigation: Investigation;
}) {
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const form = useForm<RecommendationCreateValues>({
    resolver: zodResolver(recommendationCreateSchema),
    defaultValues: {
      conclusion: "",
      recommended_actions: "",
      sanction_recommendation: "",
      recovery_recommendation: "",
      prevention_recommendation: "",
    },
  });

  const mutation = useMutation({
    mutationFn: (values: RecommendationCreateValues) =>
      createRecommendation(caseId, {
        investigation_id: investigation.id,
        ...nullifyEmpty(values),
      }),
    onSuccess: () => {
      toast.success("Recommendation created");
      setOpen(false);
      form.reset();
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.case(caseId) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.recommendations(caseId) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.investigations(caseId) });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["my-work"] });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, "Recommendation could not be created"));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button className="w-full" variant="outline">
          <ClipboardList className="mr-2 h-4 w-4" /> Create recommendation
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Create recommendation</DialogTitle>
          <DialogDescription>
            The latest completed investigation is selected automatically. No investigation picker is exposed.
          </DialogDescription>
        </DialogHeader>

        <div className="rounded-lg border bg-muted/30 p-3 text-xs text-muted-foreground">
          Using completed investigation #{investigation.id}
          {investigation.completed_at ? `, completed ${formatDateTime(investigation.completed_at)}` : ""}.
        </div>

        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
            <TextareaField form={form} name="conclusion" label="Conclusion" />
            <TextareaField form={form} name="recommended_actions" label="Recommended actions" />
            <TextareaField form={form} name="sanction_recommendation" label="Sanction recommendation" />
            <TextareaField form={form} name="recovery_recommendation" label="Recovery recommendation" />
            <TextareaField form={form} name="prevention_recommendation" label="Prevention recommendation" />

            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                Create recommendation
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

function TextareaField<T extends Record<string, unknown>>({
  form,
  name,
  label,
}: {
  form: ReturnType<typeof useForm<T>>;
  name: keyof T & string;
  label: string;
}) {
  return (
    <FormField
      control={form.control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel>{label}</FormLabel>
          <FormControl>
            <Textarea {...field} value={(field.value as string | undefined) ?? ""} className="min-h-28" />
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

function formatDateTime(value: string) {
  return new Intl.DateTimeFormat("en", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}
