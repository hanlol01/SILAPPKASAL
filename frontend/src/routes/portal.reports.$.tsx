import { createFileRoute } from "@tanstack/react-router";
import { PortalReportDetailContent } from "./portal.reports.$registrationNumber";

export const Route = createFileRoute("/portal/reports/$")({
  component: PortalReportSplatDetailPage,
  head: () => ({
    meta: [
      { title: "Report Detail - SafeCampus Portal" },
      {
        name: "description",
        content: "View the current status and details of your report.",
      },
    ],
  }),
});

function PortalReportSplatDetailPage() {
  const { _splat } = Route.useParams();

  return <PortalReportDetailContent registrationNumber={_splat ?? ""} />;
}
