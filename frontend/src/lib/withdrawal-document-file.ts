export const WITHDRAWAL_DOCUMENT_MAX_BYTES = 10 * 1024 * 1024;
export const WITHDRAWAL_DOCUMENT_ACCEPT = ".pdf,.jpg,.jpeg,.png";

const EXTENSIONS_BY_MIME: Record<string, string[]> = {
  "application/pdf": ["pdf"],
  "image/jpeg": ["jpg", "jpeg"],
  "image/png": ["png"],
};

export type WithdrawalDocumentValidationKey =
  | "invalidFormat"
  | "emptyFile"
  | "fileTooLarge"
  | "unsafeFilename";

export function validateWithdrawalDocumentFile(
  file: Pick<File, "name" | "size" | "type">,
): WithdrawalDocumentValidationKey | null {
  if (file.size < 1) return "emptyFile";
  if (file.size > WITHDRAWAL_DOCUMENT_MAX_BYTES) return "fileTooLarge";
  if (file.name.includes("/") || file.name.includes("\\") || file.name.split(".").length !== 2) {
    return "unsafeFilename";
  }

  const extension = file.name.split(".").pop()?.toLowerCase() ?? "";
  const allowedExtensions = EXTENSIONS_BY_MIME[file.type.toLowerCase()];

  if (!allowedExtensions || !allowedExtensions.includes(extension)) {
    return "invalidFormat";
  }

  return null;
}
