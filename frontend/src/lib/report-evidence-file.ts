export const MAX_REPORT_EVIDENCE_FILES = 5;
export const MAX_REPORT_EVIDENCE_FILE_SIZE = 10 * 1024 * 1024;
export const REPORT_EVIDENCE_FILE_ACCEPT =
  ".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png";

const ALLOWED_EXTENSIONS = new Set(["pdf", "jpg", "jpeg", "png"]);
const ALLOWED_MIME_TYPES = new Set(["application/pdf", "image/jpeg", "image/png"]);

export type ReportEvidenceValidationKey = "invalidFormat" | "emptyFile" | "fileTooLarge";

export function validateReportEvidenceFile(file: File): ReportEvidenceValidationKey | null {
  const parts = file.name.split(".");
  const extension = parts.pop()?.toLowerCase() ?? "";

  if (
    parts.length !== 1
    || !parts[0]
    || !ALLOWED_EXTENSIONS.has(extension)
    || (file.type && !ALLOWED_MIME_TYPES.has(file.type))
  ) {
    return "invalidFormat";
  }
  if (file.size < 1) return "emptyFile";
  if (file.size > MAX_REPORT_EVIDENCE_FILE_SIZE) return "fileTooLarge";

  return null;
}

export function reportEvidenceFileKey(file: File) {
  return `${file.name}:${file.size}:${file.lastModified}`;
}
