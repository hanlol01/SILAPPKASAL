import { createFileRoute } from "@tanstack/react-router";

import { InformationConsultationPage } from "@/components/content/information-consultation-page";

export const Route = createFileRoute("/dashboard/information-center/consultation")({
  component: () => <InformationConsultationPage area="dashboard" />,
});
