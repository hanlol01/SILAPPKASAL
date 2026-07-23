import { createFileRoute } from "@tanstack/react-router";

import { InformationFaqPage } from "@/components/content/information-faq-page";

export const Route = createFileRoute("/dashboard/information-center/faq")({
  component: () => <InformationFaqPage area="dashboard" />,
});
