export function safeConsultationEmail(value: string | null): string | null {
  const trimmed = value?.trim() ?? "";
  return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(trimmed) ? trimmed : null;
}

export function normalizeConsultationPhone(value: string | null): string | null {
  if (!value) return null;
  const normalized = value
    .trim()
    .replace(/(?!^)\+/g, "")
    .replace(/[^\d+]/g, "");
  return /^\+?\d{6,15}$/.test(normalized) ? normalized : null;
}

export function normalizeConsultationWhatsApp(value: string | null): string | null {
  const phone = normalizeConsultationPhone(value);
  if (!phone || phone.startsWith("0")) return null;
  const digits = phone.replace(/^\+/, "");
  return /^\d{6,15}$/.test(digits) ? digits : null;
}

export function safeConsultationHttpsUrl(value: string | null): string | null {
  if (!value) return null;
  try {
    const url = new URL(value);
    return url.protocol === "https:" && !url.username && !url.password ? url.toString() : null;
  } catch {
    return null;
  }
}
