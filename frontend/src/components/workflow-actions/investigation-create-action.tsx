import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { FileSearch, Loader2 } from "lucide-react";
import { useMemo, useState } from "react";
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import { createInvestigation, operationsQueryKeys } from "@/lib/operations-api";
import type { CaseAssignment } from "@/lib/operations-types";

const investigationCreateSchema = z.object({
  lead_investigator_id: z.string().min(1, "Required"),
  plan_summary: z.string().trim().min(50, "Minimum 50 characters").max(5000, "Maximum 5000 characters"),
});

type InvestigationCreateValues = z.infer<typeof investigationCreateSchema>;

export function InvestigationCreateAction({
  caseId,
  assignments,
}: {
  caseId: number | string;
  assignments: CaseAssignment[];
}) {
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const activeAssignments = useMemo(
    () => assignments.filter((assignment) => assignment.is_active),
    [assignments],
  );

  const form = useForm<InvestigationCreateValues>({
    resolver: zodResolver(investigationCreateSchema),
    defaultValues: {
      lead_investigator_id: activeAssignments[0]?.satgas_id ? String(activeAssignments[0].satgas_id) : "",
      plan_summary: "",
    },
  });

  const mutation = useMutation({
    mutationFn: (values: InvestigationCreateValues) =>
      createInvestigation(caseId, {
        lead_investigator_id: Number(values.lead_investigator_id),
        plan_summary: values.plan_summary.trim(),
      }),
    onSuccess: () => {
      toast.success("Investigation created");
      setOpen(false);
      form.reset({
        lead_investigator_id: activeAssignments[0]?.satgas_id ? String(activeAssignments[0].satgas_id) : "",
        plan_summary: "",
      });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.case(caseId) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.investigations(caseId) });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["my-work"] });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, "Investigation could not be created"));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button className="w-full" variant="outline" disabled={activeAssignments.length === 0}>
          <FileSearch className="mr-2 h-4 w-4" /> Create investigation
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Create investigation</DialogTitle>
          <DialogDescription>
            Select an active assigned Satgas user as lead investigator and provide the investigation plan.
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
            <FormField
              control={form.control}
              name="lead_investigator_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Lead investigator</FormLabel>
                  <Select onValueChange={field.onChange} value={field.value}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Select assigned Satgas" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {activeAssignments.map((assignment) => (
                        <SelectItem key={assignment.id} value={String(assignment.satgas_id)}>
                          {assignment.satgas_name ?? `Satgas #${assignment.satgas_id}`}
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
              name="plan_summary"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Plan summary</FormLabel>
                  <FormControl>
                    <Textarea
                      {...field}
                      className="min-h-32"
                      placeholder="Describe the investigation plan, scope, and initial handling steps."
                    />
                  </FormControl>
                  <p className="text-xs text-muted-foreground">Required, minimum 50 characters.</p>
                  <FormMessage />
                </FormItem>
              )}
            />

            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                Create investigation
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
