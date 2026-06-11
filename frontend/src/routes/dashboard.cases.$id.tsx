import { createFileRoute, Link } from "@tanstack/react-router";
import { useQueries, useQuery } from "@tanstack/react-query";
import {
  ArrowLeft,
  BriefcaseMedical,
  ClipboardList,
  FileArchive,
  FileSearch,
  Gavel,
  Lock,
  Scale,
  UserRoundSearch,
} from "lucide-react";
import { QueryErrorState } from "@/components/query-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import {
  getCase,
  getCaseInvestigations,
  getCaseRecommendations,
  getDecisionRecoveries,
  getInvestigationEvidences,
  getRecommendationDecisions,
  operationsQueryKeys,
} from "@/lib/operations-api";
import type {
  Decision,
  EvidenceMetadata,
  Investigation,
  Recommendation,
  Recovery,
} from "@/lib/operations-types";

export const Route = createFileRoute("/dashboard/cases/$id")({
  component: CaseDetail,
  head: () => ({ meta: [{ title: "Case detail - SafeCampus Admin" }] }),
});

function CaseDetail() {
  const { id } = Route.useParams();
  const caseQuery = useQuery({
    queryKey: operationsQueryKeys.case(id),
    queryFn: () => getCase(id),
  });
  const investigationsQuery = useQuery({
    queryKey: operationsQueryKeys.investigations(id),
    queryFn: () => getCaseInvestigations(id),
  });
  const recommendationsQuery = useQuery({
    queryKey: operationsQueryKeys.recommendations(id),
    queryFn: () => getCaseRecommendations(id),
  });
  const decisionQueries = useQueries({
    queries: (recommendationsQuery.data ?? []).map((recommendation) => ({
      queryKey: operationsQueryKeys.decisions(recommendation.id),
      queryFn: () => getRecommendationDecisions(recommendation.id),
      enabled: recommendationsQuery.isSuccess,
    })),
  });
  const decisions = decisionQueries.flatMap((query) => query.data ?? []);
  const recoveryQueries = useQueries({
    queries: decisions.map((decision) => ({
      queryKey: operationsQueryKeys.recoveries(decision.id),
      queryFn: () => getDecisionRecoveries(decision.id),
      enabled: decisions.length > 0,
    })),
  });
  const recoveries = recoveryQueries.flatMap((query) => query.data ?? []);
  const evidenceQueries = useQueries({
    queries: (investigationsQuery.data ?? []).map((investigation) => ({
      queryKey: operationsQueryKeys.evidences(investigation.id),
      queryFn: () => getInvestigationEvidences(investigation.id),
      enabled: investigationsQuery.isSuccess,
    })),
  });
  const evidences = evidenceQueries.flatMap((query) => query.data ?? []);

  if (caseQuery.isLoading) {
    return <div className="py-12 text-center text-sm text-muted-foreground">Loading case...</div>;
  }

  if (caseQuery.isError || !caseQuery.data) {
    return <QueryErrorState message="Case could not be loaded." onRetry={() => caseQuery.refetch()} />;
  }

  const c = caseQuery.data;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center gap-3">
        <Button variant="ghost" size="sm" asChild>
          <Link to="/dashboard/cases">
            <ArrowLeft className="mr-2 h-4 w-4" /> All cases
          </Link>
        </Button>
        <div className="flex items-center gap-2">
          <h1 className="font-mono text-lg font-semibold">{c.case_number}</h1>
          <Badge variant="outline">{label(c.status_code)}</Badge>
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="space-y-4 lg:col-span-2">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Case metadata</CardTitle>
              <CardDescription>Fields are rendered only when returned by backend RBAC.</CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
              <Field label="Case number">{c.case_number}</Field>
              <Field label="Registration">{c.registration_number}</Field>
              <Field label="Status">{c.status ?? label(c.status_code)}</Field>
              <Field label="Stage">{c.current_stage_label ?? label(c.current_stage ?? "-")}</Field>
              <Field label="Risk">{c.risk_level ?? c.risk_level_code ?? "-"}</Field>
              <Field label="Priority">{c.priority ?? "-"}</Field>
              <Field label="Forwarded">{formatDate(c.forwarded_at)}</Field>
              <Field label="Closed">{formatDate(c.closed_at)}</Field>
            </CardContent>
          </Card>

          <SensitiveReportSection report={c.report} />
          <InvestigationsSection investigations={investigationsQuery.data ?? []} loading={investigationsQuery.isLoading} />
          <RecommendationsSection recommendations={recommendationsQuery.data ?? []} loading={recommendationsQuery.isLoading} />
          <DecisionsSection decisions={decisions} loading={decisionQueries.some((query) => query.isLoading)} />
          <RecoveriesSection recoveries={recoveries} loading={recoveryQueries.some((query) => query.isLoading)} />
          <EvidenceSection evidences={evidences} loading={evidenceQueries.some((query) => query.isLoading)} />
        </div>

        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Assignments</CardTitle>
              <CardDescription>Case assignment data returned by backend.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {(c.assignments ?? []).length === 0 && (
                <EmptyText>No active assignments returned.</EmptyText>
              )}
              {(c.assignments ?? []).map((assignment) => (
                <div key={assignment.id} className="rounded-lg border p-3 text-sm">
                  <div className="font-medium">{assignment.satgas_name ?? `Satgas #${assignment.satgas_id}`}</div>
                  <div className="mt-1 text-xs text-muted-foreground">
                    {assignment.is_lead ? "Lead Satgas" : "Assigned Satgas"} - {formatDate(assignment.assigned_at)}
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">Actions</CardTitle>
              <CardDescription>Mutation UI is deferred for M15.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              <Button disabled className="w-full" variant="outline">
                <UserRoundSearch className="mr-2 h-4 w-4" /> Assign Satgas
              </Button>
              <Button disabled className="w-full" variant="outline">
                Update status
              </Button>
              <p className="text-xs text-muted-foreground">
                Assignment requires an approved Satgas user lookup API. Status/workflow mutation
                forms are intentionally deferred; this screen is read-only operational browsing.
              </p>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}

function SensitiveReportSection({ report }: { report: unknown }) {
  if (!report || typeof report !== "object") {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Lock className="h-4 w-4" /> Sensitive report detail
          </CardTitle>
          <CardDescription>
            Backend returned metadata-only access for this case.
          </CardDescription>
        </CardHeader>
      </Card>
    );
  }

  const data = report as Record<string, unknown>;
  const respondent = data.respondent as { name?: string | null; details?: string | null } | undefined;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Sensitive report detail</CardTitle>
        <CardDescription>Shown because backend returned these fields for this user.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4 text-sm">
        <Field label="Chronology">{asText(data.chronology)}</Field>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Incident date">{asText(data.incident_date)}</Field>
          <Field label="Incident time">{asText(data.incident_time)}</Field>
          <Field label="Incident location">{asText(data.incident_location)}</Field>
          <Field label="Witness info">{asText(data.witness_info)}</Field>
        </div>
        <Separator />
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Respondent name">{respondent?.name ?? "-"}</Field>
          <Field label="Respondent details">{respondent?.details ?? "-"}</Field>
        </div>
      </CardContent>
    </Card>
  );
}

function InvestigationsSection({ investigations, loading }: { investigations: Investigation[]; loading: boolean }) {
  return (
    <SectionCard icon={FileSearch} title="Investigations" loading={loading} empty={investigations.length === 0}>
      {investigations.map((item) => (
        <div key={item.id} className="rounded-lg border p-3 text-sm">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="font-medium">Investigation #{item.id}</div>
            <Badge variant="outline">{label(item.status_code)}</Badge>
          </div>
          <div className="mt-2 grid gap-2 text-muted-foreground sm:grid-cols-2">
            <div>Lead: {item.lead_investigator?.name ?? "Metadata unavailable"}</div>
            <div>Started: {formatDate(item.started_at)}</div>
          </div>
          {item.plan_summary || item.findings || item.conclusion ? (
            <div className="mt-3 space-y-2">
              {item.plan_summary && <Field label="Plan summary">{item.plan_summary}</Field>}
              {item.findings && <Field label="Findings">{item.findings}</Field>}
              {item.conclusion && <Field label="Conclusion">{item.conclusion}</Field>}
            </div>
          ) : (
            <MetadataOnlyText />
          )}
        </div>
      ))}
    </SectionCard>
  );
}

function RecommendationsSection({ recommendations, loading }: { recommendations: Recommendation[]; loading: boolean }) {
  return (
    <SectionCard icon={ClipboardList} title="Recommendations" loading={loading} empty={recommendations.length === 0}>
      {recommendations.map((item) => (
        <div key={item.id} className="rounded-lg border p-3 text-sm">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="font-medium">Recommendation #{item.id}</div>
            <Badge variant="outline">{label(item.status_code)}</Badge>
          </div>
          <div className="mt-2 text-muted-foreground">Author: {item.author?.name ?? "Metadata unavailable"}</div>
          {item.conclusion || item.recommended_actions ? (
            <div className="mt-3 space-y-2">
              {item.conclusion && <Field label="Conclusion">{item.conclusion}</Field>}
              {item.recommended_actions && <Field label="Recommended actions">{item.recommended_actions}</Field>}
              {item.sanction_recommendation && <Field label="Sanction">{item.sanction_recommendation}</Field>}
              {item.recovery_recommendation && <Field label="Recovery">{item.recovery_recommendation}</Field>}
              {item.prevention_recommendation && <Field label="Prevention">{item.prevention_recommendation}</Field>}
            </div>
          ) : (
            <MetadataOnlyText />
          )}
        </div>
      ))}
    </SectionCard>
  );
}

function DecisionsSection({ decisions, loading }: { decisions: Decision[]; loading: boolean }) {
  return (
    <SectionCard icon={Scale} title="Decisions" loading={loading} empty={decisions.length === 0}>
      {decisions.map((item) => (
        <div key={item.id} className="rounded-lg border p-3 text-sm">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="font-medium">Decision #{item.id}</div>
            <Badge variant="outline">{label(item.status_code)}</Badge>
          </div>
          <div className="mt-2 grid gap-2 text-muted-foreground sm:grid-cols-2">
            <div>Outcome: {label(item.outcome_code)}</div>
            <div>Date: {formatDate(item.decision_date)}</div>
          </div>
          {item.decision_summary || item.decision_content ? (
            <div className="mt-3 space-y-2">
              {item.decision_summary && <Field label="Summary">{item.decision_summary}</Field>}
              {item.decision_content && <Field label="Content">{item.decision_content}</Field>}
            </div>
          ) : (
            <MetadataOnlyText />
          )}
        </div>
      ))}
    </SectionCard>
  );
}

function RecoveriesSection({ recoveries, loading }: { recoveries: Recovery[]; loading: boolean }) {
  return (
    <SectionCard icon={BriefcaseMedical} title="Recoveries" loading={loading} empty={recoveries.length === 0}>
      {recoveries.map((item) => (
        <div key={item.id} className="rounded-lg border p-3 text-sm">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="font-medium">{item.recovery_type?.name ?? `Recovery #${item.id}`}</div>
            <Badge variant="outline">{label(item.status_code)}</Badge>
          </div>
          {item.recovery_plan || item.support_needs || item.notes ? (
            <div className="mt-3 space-y-2">
              {item.recovery_plan && <Field label="Plan">{item.recovery_plan}</Field>}
              {item.support_needs && <Field label="Support needs">{item.support_needs}</Field>}
              {item.notes && <Field label="Notes">{item.notes}</Field>}
            </div>
          ) : (
            <MetadataOnlyText />
          )}
        </div>
      ))}
    </SectionCard>
  );
}

function EvidenceSection({ evidences, loading }: { evidences: EvidenceMetadata[]; loading: boolean }) {
  return (
    <SectionCard icon={FileArchive} title="Evidence metadata" loading={loading} empty={evidences.length === 0}>
      {evidences.map((item) => (
        <div key={item.id} className="rounded-lg border p-3 text-sm">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="font-medium">{item.title}</div>
            <Badge variant="outline">{label(item.status)}</Badge>
          </div>
          <div className="mt-2 grid gap-2 text-muted-foreground sm:grid-cols-2">
            <div>Type: {item.evidence_type?.name ?? "Metadata unavailable"}</div>
            <div>Classification: {label(item.classification ?? "-")}</div>
            <div>Collected: {formatDate(item.collected_at)}</div>
            <div>Submitted by: {item.submitted_by?.name ?? "Metadata unavailable"}</div>
          </div>
          {item.status_semantics && (
            <div className="mt-3 rounded-md bg-muted p-2 text-xs text-muted-foreground">
              {item.status_semantics}
            </div>
          )}
          {item.description && <Field label="Description">{item.description}</Field>}
          {item.file_metadata && (
            <div className="mt-3 grid gap-2 text-xs text-muted-foreground sm:grid-cols-2">
              <div>Filename: {item.file_metadata.original_filename ?? "-"}</div>
              <div>MIME: {item.file_metadata.mime_type ?? "-"}</div>
              <div>Size: {item.file_metadata.file_size ?? "-"}</div>
              <div>Checksum: {item.file_metadata.checksum_sha256 ?? "-"}</div>
            </div>
          )}
        </div>
      ))}
    </SectionCard>
  );
}

function SectionCard({
  icon: Icon,
  title,
  loading,
  empty,
  children,
}: {
  icon: React.ComponentType<{ className?: string }>;
  title: string;
  loading: boolean;
  empty: boolean;
  children: React.ReactNode;
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Icon className="h-4 w-4" /> {title}
        </CardTitle>
        <CardDescription>Read-only operational data from approved backend endpoints.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-3">
        {loading && <EmptyText>Loading {title.toLowerCase()}...</EmptyText>}
        {!loading && empty && <EmptyText>No {title.toLowerCase()} returned.</EmptyText>}
        {!loading && !empty && children}
      </CardContent>
    </Card>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
      <div className="mt-1 whitespace-pre-wrap text-sm">{children}</div>
    </div>
  );
}

function MetadataOnlyText() {
  return <div className="mt-3 text-xs text-muted-foreground">Metadata-only response for this user.</div>;
}

function EmptyText({ children }: { children: React.ReactNode }) {
  return <div className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">{children}</div>;
}

function asText(value: unknown) {
  return typeof value === "string" && value ? value : "-";
}

function label(value: string) {
  if (value === "-") return value;
  return value.replace(/[-_]/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatDate(value: string | null | undefined) {
  return value ? new Date(value).toLocaleString() : "-";
}
