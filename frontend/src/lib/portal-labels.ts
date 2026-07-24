import { label as humanize } from "@/lib/format";
import type { TFunction } from "i18next";
import type { PortalNotification } from "@/lib/portal-types";

type LocaleCode = "id" | "en";

function localeFromLanguage(language: string | undefined): LocaleCode {
  return language?.toLowerCase().startsWith("en") ? "en" : "id";
}

function normalized(value: string): string {
  return value.trim().toLowerCase().replace(/[-\s]+/g, "_");
}

export type ReporterSafeStatusCode =
  | "submitted"
  | "under_review"
  | "in_process"
  | "completed"
  | "cancelled_by_reporter"
  | "withdrawn";

export function portalStatusCode(value: string): ReporterSafeStatusCode | null {
  const token = normalized(value);

  if (token === "submitted") return "submitted";
  if (token === "under_review") return "under_review";
  if (token === "in_process") return "in_process";
  if (token === "completed") return "completed";
  if (token === "cancelled_by_reporter") return "cancelled_by_reporter";
  if (token === "withdrawn") return "withdrawn";

  return null;
}

export function portalStatusLabel(t: TFunction, value: string): string {
  const code = portalStatusCode(value);
  return code
    ? t(`portal:statusCodes.${code}`)
    : t("portal:statusCodes.unknown");
}

export function portalReportTypeLabel(value: string, language: string | undefined): string {
  const locale = localeFromLanguage(language);

  switch (normalized(value)) {
    case "open":
      return locale === "id" ? "Terbuka" : "Open";
    case "confidential":
      return locale === "id" ? "Rahasia" : "Confidential";
    case "anonymous":
      return locale === "id" ? "Anonim" : "Anonymous";
    default:
      return humanize(value);
  }
}

export function portalNotificationContent(t: TFunction, notification: PortalNotification) {
  const typeCode = normalized(notification.notification_type_code ?? "");
  const event = normalized(notification.event ?? "");
  const eventFromTypeCode: Record<string, string> = {
    notif_12: "case_assigned",
    notif_13: "case_status_changed",
    notif_14: "recommendation_update",
    notif_15: "decision_update",
    notif_16: "recommendation_update",
    notif_17: "recommendation_update",
    notif_18: "decision_update",
    notif_19: "decision_update",
    notif_20: "recovery_update",
    notif_21: "recovery_update",
    notif_22: "case_status_changed",
    notif_23: "recommendation_update",
    notif_24: "recommendation_update",
  };
  const resolvedEvent = eventFromTypeCode[typeCode] ?? eventFromTypeCode[event] ?? (event || typeCode);
  const categorizedEvent = resolvedEvent.startsWith("investigation_")
    ? "investigation_update"
    : resolvedEvent.startsWith("recommendation_")
      ? "recommendation_update"
      : resolvedEvent.startsWith("decision_")
        ? "decision_update"
        : resolvedEvent.startsWith("recovery_")
          ? "recovery_update"
          : resolvedEvent;
  const knownEvent = [
    "case_assigned",
    "case_status_changed",
    "investigation_update",
    "recommendation_update",
    "decision_update",
    "recovery_update",
    "privacy_notice",
    "break_glass_approved",
    "break_glass_denied",
  ].includes(categorizedEvent)
    ? categorizedEvent
    : "default";

  return {
    title: t(`portal:notificationContent.${knownEvent}.title`),
    body: t(`portal:notificationContent.${knownEvent}.body`),
    typeLabel: t(`portal:notificationContent.${knownEvent}.type`),
    iconToken: knownEvent,
  };
}
