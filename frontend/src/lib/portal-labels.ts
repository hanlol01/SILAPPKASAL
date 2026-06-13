import { label as humanize } from "@/lib/format";

type LocaleCode = "id" | "en";

function localeFromLanguage(language: string | undefined): LocaleCode {
  return language?.toLowerCase().startsWith("id") ? "id" : "en";
}

function normalized(value: string): string {
  return value.trim().toLowerCase().replace(/[-\s]+/g, "_");
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

export function portalNotificationTypeLabel(value: string, language: string | undefined): string {
  const locale = localeFromLanguage(language);

  switch (normalized(value)) {
    case "case_status_changed":
      return locale === "id" ? "Perubahan Status Kasus" : "Case Status Changed";
    default:
      return locale === "id" ? "Notifikasi" : "Notification";
  }
}

export function portalNotificationBody(value: string, language: string | undefined): string {
  const locale = localeFromLanguage(language);

  if (value.trim() === "Your report status was updated.") {
    return locale === "id"
      ? "Status laporan Anda telah diperbarui."
      : "Your report status was updated.";
  }

  return value;
}
