import { createFileRoute } from "@tanstack/react-router";

import { InformationCenterHome } from "@/components/content/information-center-home";

export const Route = createFileRoute("/portal/information-center/")({
  component: () => <InformationCenterHome area="portal" />,
});
