import { createFileRoute } from "@tanstack/react-router";
import { AccessDenied } from "@/components/access-denied";

export const Route = createFileRoute("/dashboard/users")({
  component: AccessDenied,
  head: () => ({ meta: [{ title: "Access denied - SafeCampus Admin" }] }),
});
