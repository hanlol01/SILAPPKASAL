import { createFileRoute } from "@tanstack/react-router";
import { AccessDenied } from "@/components/access-denied";

export const Route = createFileRoute("/dashboard/notifications")({
  component: AccessDenied,
  head: () => ({ meta: [{ title: "Access denied - SILAPPKASAL Admin" }] }),
});
