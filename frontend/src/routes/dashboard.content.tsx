import { createFileRoute } from "@tanstack/react-router";
import { AccessDenied } from "@/components/access-denied";

export const Route = createFileRoute("/dashboard/content")({
  component: AccessDenied,
  head: () => ({ meta: [{ title: "Access denied - SafeCampus Admin" }] }),
});
