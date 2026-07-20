import { Separator } from "@/components/ui/separator";
import { formatDate } from "@/lib/format";
import {
  formatLocationType,
  formatReportCategory,
  formatReportType,
  formatRespondentCampusStatus,
  formatRespondentRelation,
} from "@/lib/format-labels";
import type { ReportInputDetails } from "@/lib/report-input-types";
import { useTranslation } from "react-i18next";

export function ReportInputDetailsContent({
  details,
  translationScope,
}: {
  details: ReportInputDetails;
  translationScope: "portal" | "dashboard";
}) {
  const { t, i18n } = useTranslation(["portal", "dashboard"]);
  const key = translationScope === "portal" ? "portal:submittedDetails" : "dashboard:reportInputDetails";
  const empty = t(`${key}.empty`);
  const account = details.reporter_account;

  return (
    <div className="min-w-0 space-y-5 text-sm">
      <DetailSection title={t(`${key}.sections.identification`)}>
        <DetailField label={t(`${key}.fields.reportType`)}>
          {formatReportType(t, details.identification.report_type)}
        </DetailField>
        <DetailField label={t(`${key}.fields.category`)}>
          {details.identification.category
            ? formatReportCategory(t, details.identification.category)
            : empty}
        </DetailField>
      </DetailSection>

      <Separator />
      <DetailSection title={t(`${key}.sections.incident`)}>
        <DetailField label={t(`${key}.fields.chronology`)} wide preserveWhitespace>
          {details.incident.chronology || empty}
        </DetailField>
        <DetailField label={t(`${key}.fields.incidentDate`)}>
          {details.incident.incident_date
            ? formatDate(details.incident.incident_date, i18n.language)
            : empty}
        </DetailField>
        <DetailField label={t(`${key}.fields.incidentTime`)}>
          {details.incident.incident_time || empty}
        </DetailField>
        <DetailField label={t(`${key}.fields.incidentLocation`)}>
          {details.incident.incident_location || empty}
        </DetailField>
        <DetailField label={t(`${key}.fields.locationType`)}>
          {details.incident.location_type
            ? formatLocationType(t, details.incident.location_type)
            : empty}
        </DetailField>
      </DetailSection>

      <Separator />
      <DetailSection title={t(`${key}.sections.respondent`)}>
        <DetailField label={t(`${key}.fields.respondentName`)}>
          {details.respondent.name || empty}
        </DetailField>
        <DetailField label={t(`${key}.fields.respondentCampusStatus`)}>
          {details.respondent.campus_status
            ? formatRespondentCampusStatus(t, details.respondent.campus_status)
            : empty}
        </DetailField>
        <DetailField label={t(`${key}.fields.respondentRelation`)}>
          {details.respondent.relation
            ? formatRespondentRelation(t, details.respondent.relation)
            : empty}
        </DetailField>
        <DetailField label={t(`${key}.fields.respondentDetails`)} preserveWhitespace>
          {details.respondent.details || empty}
        </DetailField>
        <DetailField label={t(`${key}.fields.witnessInformation`)} wide preserveWhitespace>
          {details.respondent.witness_information || empty}
        </DetailField>
        <DetailField label={t(`${key}.fields.confidentialContact`)}>
          {details.respondent.confidential_reporter_contact || empty}
        </DetailField>
      </DetailSection>

      <Separator />
      <DetailSection title={t(`${key}.sections.reporterAccount`)}>
        {account.masked ? (
          <p className="col-span-full text-sm text-muted-foreground">{t(`${key}.identityMasked`)}</p>
        ) : (
          <>
            <DetailField label={t(`${key}.fields.reporterName`)}>{account.name || empty}</DetailField>
            <DetailField label={t(`${key}.fields.nim`)}>{account.nim || empty}</DetailField>
            <DetailField label={t(`${key}.fields.email`)}>{account.email || empty}</DetailField>
            <DetailField label={t(`${key}.fields.accountPhone`)}>
              {account.phone_number || empty}
            </DetailField>
            <DetailField label={t(`${key}.fields.faculty`)}>
              {account.faculty?.name || empty}
            </DetailField>
            <DetailField label={t(`${key}.fields.studyProgram`)}>
              {account.study_program?.name || empty}
            </DetailField>
            <p className="col-span-full text-xs text-muted-foreground">
              {t(`${key}.currentAccountNotice`)}
            </p>
          </>
        )}
      </DetailSection>
    </div>
  );
}

function DetailSection({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="min-w-0 space-y-3">
      <h3 className="text-sm font-semibold">{title}</h3>
      <div className="grid min-w-0 gap-4 sm:grid-cols-2">{children}</div>
    </section>
  );
}

function DetailField({
  label,
  children,
  wide = false,
  preserveWhitespace = false,
}: {
  label: string;
  children: React.ReactNode;
  wide?: boolean;
  preserveWhitespace?: boolean;
}) {
  return (
    <div className={wide ? "min-w-0 sm:col-span-2" : "min-w-0"}>
      <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
      <div
        className={`mt-1 min-w-0 break-words [overflow-wrap:anywhere] ${
          preserveWhitespace ? "whitespace-pre-wrap" : ""
        }`}
      >
        {children}
      </div>
    </div>
  );
}
