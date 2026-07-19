import { CheckCircle2, Circle, Clock3, FastForward, HelpCircle } from "lucide-react";
import { useTranslation } from "react-i18next";
import { Badge } from "@/components/ui/badge";
import { formatDate, formatDateTime } from "@/lib/format";
import {
  formatInvestigationActivityType,
  formatInvestigationStatus,
} from "@/lib/format-labels";
import type { Investigation, InvestigationActivity } from "@/lib/operations-types";

const STAGES = [
  "planning",
  "evidence_collection",
  "victim_interview",
  "witness_interview",
  "respondent_interview",
  "evidence_analysis",
  "report_drafting",
  "completed",
] as const;

export function InvestigationStageProgress({
  investigation,
  language,
}: {
  investigation: Investigation;
  language: string;
}) {
  const { t } = useTranslation(["dashboard"]);
  const activities = investigation.activities ?? [];
  const currentStage = investigation.status ?? "";
  const currentIndex = STAGES.indexOf(currentStage as (typeof STAGES)[number]);
  const grouped = groupActivities(activities);

  return (
    <div className="mt-4 min-w-0 space-y-4 rounded-lg border bg-muted/20 p-3 sm:p-4">
      <div>
        <h4 className="font-medium">{t("dashboard:investigationProgress.title")}</h4>
        <p className="mt-1 text-xs text-muted-foreground">
          {t("dashboard:investigationProgress.description")}
        </p>
      </div>

      <ol className="grid min-w-0 gap-2 sm:grid-cols-2 xl:grid-cols-3">
        {STAGES.map((stage, index) => {
          const count = grouped.get(stage)?.length ?? 0;
          const state = stageState(index, currentIndex, currentStage, count);
          const Icon = state === "completed" || state === "terminal" ? CheckCircle2 : state === "current" ? Clock3 : state === "skipped" ? FastForward : Circle;
          return (
            <li key={stage} className="flex min-w-0 items-start gap-2 rounded-md border bg-background p-2.5">
              <Icon className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
              <div className="min-w-0 flex-1">
                <div className="break-words text-sm font-medium [overflow-wrap:anywhere]">
                  {formatInvestigationStatus(t, stage)}
                </div>
                <div className="mt-1 flex flex-wrap gap-1.5">
                  <Badge variant="outline">{t(`dashboard:investigationProgress.states.${state}`)}</Badge>
                  <Badge variant="secondary">
                    {t("dashboard:investigationProgress.activityCount", { count })}
                  </Badge>
                </div>
              </div>
            </li>
          );
        })}
      </ol>

      {currentStage !== "completed" && (grouped.get(currentStage)?.length ?? 0) === 0 && (
        <p className="rounded-md border border-warning/40 bg-warning/10 p-3 text-sm">
          {t("dashboard:investigationProgress.currentStageNeedsActivity", {
            stage: formatInvestigationStatus(t, currentStage),
          })}
        </p>
      )}

      <div className="space-y-3">
        <h4 className="font-medium">{t("dashboard:investigationProgress.history")}</h4>
        {[...STAGES, "legacy"].map((stage) => {
          const stageActivities = grouped.get(stage) ?? [];
          if (stageActivities.length === 0) return null;

          return (
            <section key={stage} className="min-w-0 space-y-2">
              <div className="flex min-w-0 flex-wrap items-center gap-2">
                {stage === "legacy" && <HelpCircle className="h-4 w-4 text-muted-foreground" />}
                <h5 className="break-words text-sm font-semibold [overflow-wrap:anywhere]">
                  {stage === "legacy"
                    ? t("dashboard:investigationProgress.legacyStage")
                    : formatInvestigationStatus(t, stage)}
                </h5>
                <Badge variant="secondary">{stageActivities.length}</Badge>
              </div>
              <div className="space-y-2">
                {stageActivities.map((activity) => (
                  <ActivityCard
                    key={activity.id}
                    activity={activity}
                    language={language}
                  />
                ))}
              </div>
            </section>
          );
        })}
      </div>
    </div>
  );
}

function ActivityCard({ activity, language }: { activity: InvestigationActivity; language: string }) {
  const { t } = useTranslation(["dashboard"]);

  return (
    <article className="min-w-0 rounded-md border bg-background p-3 text-sm">
      <div className="flex min-w-0 flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <div className="break-words font-medium [overflow-wrap:anywhere]">
            {formatInvestigationActivityType(t, activity.activity_type)}
          </div>
          <Badge variant="outline" className="mt-1.5">
            {activity.investigation_stage
              ? formatInvestigationStatus(t, activity.investigation_stage)
              : t("dashboard:investigationProgress.legacyStage")}
          </Badge>
          <div className="mt-1 break-words text-xs text-muted-foreground [overflow-wrap:anywhere]">
            {t("dashboard:investigationProgress.recordedBy", {
              name: activity.investigator?.name ?? t("dashboard:common.metadataUnavailable"),
            })}
          </div>
        </div>
        <div className="text-xs text-muted-foreground">
          {formatDate(activity.activity_date, language)}
        </div>
      </div>
      <details className="mt-3 min-w-0" open={activity.description?.length ? activity.description.length < 240 : true}>
        <summary className="cursor-pointer font-medium">
          {t("dashboard:investigationProgress.activityDetails")}
        </summary>
        <div className="mt-2 min-w-0 space-y-2">
          <ActivityField label={t("dashboard:workflow.description")} value={activity.description} />
          {activity.findings && <ActivityField label={t("dashboard:workflow.findings")} value={activity.findings} />}
          {activity.notes && <ActivityField label={t("dashboard:workflow.notes")} value={activity.notes} />}
        </div>
      </details>
      <div className="mt-3 text-xs text-muted-foreground">
        {t("dashboard:investigationProgress.createdAt", {
          date: formatDateTime(activity.created_at, language),
        })}
      </div>
    </article>
  );
}

function ActivityField({ label, value }: { label: string; value?: string | null }) {
  return (
    <div className="min-w-0">
      <div className="text-xs font-medium text-muted-foreground">{label}</div>
      <p className="mt-0.5 break-words whitespace-pre-wrap [overflow-wrap:anywhere]">{value || "-"}</p>
    </div>
  );
}

function groupActivities(activities: InvestigationActivity[]) {
  const grouped = new Map<string, InvestigationActivity[]>();
  for (const activity of activities) {
    const stage = activity.investigation_stage ?? "legacy";
    grouped.set(stage, [...(grouped.get(stage) ?? []), activity]);
  }
  return grouped;
}

function stageState(
  index: number,
  currentIndex: number,
  currentStage: string,
  activityCount: number,
): "completed" | "current" | "skipped" | "future" | "terminal" {
  if (currentStage === "completed" && index === currentIndex) return "terminal";
  if (currentStage !== "completed" && index === currentIndex) return "current";
  if (index < currentIndex) return activityCount > 0 ? "completed" : "skipped";
  return "future";
}
