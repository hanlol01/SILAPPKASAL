import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { History, Loader2 } from "lucide-react";
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import {
  getRecommendationStatusOptions,
  operationsQueryKeys,
  updateRecommendationStatus,
} from "@/lib/operations-api";
import type { Recommendation } from "@/lib/operations-types";

const recommendationStatusSchema = z.object({
  status: z.string().min(1, "Required"),
});

type RecommendationStatusValues = z.infer<typeof recommendationStatusSchema>;

export function RecommendationStatusAction({
  recommendation,
  caseId,
}: {
  recommendation: Recommendation;
  caseId: number | string;
}) {
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const optionsQuery = useQuery({
    queryKey: operationsQueryKeys.recommendationStatusOptions(recommendation.id),
    queryFn: () => getRecommendationStatusOptions(recommendation.id),
    enabled: open,
  });
  const form = useForm<RecommendationStatusValues>({
    resolver: zodResolver(recommendationStatusSchema),
    defaultValues: { status: "" },
  });

  const options = optionsQuery.data?.valid_transitions ?? [];
  const mutation = useMutation({
    mutationFn: (values: RecommendationStatusValues) =>
      updateRecommendationStatus(recommendation.id, values),
    onSuccess: () => {
      toast.success("Recommendation status updated");
      setOpen(false);
      form.reset({ status: "" });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.case(caseId) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.recommendations(caseId) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.recommendation(recommendation.id) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.recommendationStatusOptions(recommendation.id) });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["my-work"] });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, "Recommendation status could not be updated"));
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
          <DialogTitle>Update recommendation status</DialogTitle>
          <DialogDescription>
            Only valid transitions returned by the backend are available.
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
            <FormField
              control={form.control}
              name="status"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Status</FormLabel>
                  <Select
                    onValueChange={field.onChange}
                    value={field.value}
                    disabled={optionsQuery.isLoading || options.length === 0}
                  >
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder={optionsQuery.isLoading ? "Loading statuses..." : "Select status"} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {options.map((option) => (
                        <SelectItem key={option.code} value={option.code}>
                          {label(option.name)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {optionsQuery.isSuccess && options.length === 0 && (
                    <p className="text-xs text-muted-foreground">No valid next status is available.</p>
                  )}
                  <FormMessage />
                </FormItem>
              )}
            />

            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending || options.length === 0}>
                {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                Save status
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

function label(value: string) {
  return value.replace(/[-_]/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
}
