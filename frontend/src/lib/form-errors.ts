import type { FieldValues, Path, UseFormReturn } from "react-hook-form";
import { ApiError } from "@/lib/api-client";

export function apiErrorMessage(error: unknown, fallback = "Request failed") {
  return error instanceof ApiError ? error.message : fallback;
}

export function applyLaravelErrors<T extends FieldValues>(form: UseFormReturn<T>, error: unknown) {
  if (!(error instanceof ApiError) || !error.errors) return;

  Object.entries(error.errors).forEach(([name, messages]) => {
    form.setError(name as Path<T>, {
      type: "server",
      message: messages[0] ?? error.message,
    });
  });
}
