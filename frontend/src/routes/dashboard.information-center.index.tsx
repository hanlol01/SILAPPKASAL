import { createFileRoute } from "@tanstack/react-router";

import { InformationCenterHome } from "@/components/content/information-center-home";

export const Route = createFileRoute("/dashboard/information-center/")({
  component: () => <InformationCenterHome area="dashboard" />,
});
