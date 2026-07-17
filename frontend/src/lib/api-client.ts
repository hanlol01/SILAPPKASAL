import { clearAuthToken, getAuthToken } from "@/lib/auth-storage";
import type { ApiEnvelope, PaginationMeta } from "@/lib/api-types";
import i18n from "@/i18n";

export class ApiError extends Error {
  status: number;
  errorCode?: string | null;
  errors?: Record<string, string[]> | null;

  constructor(
    message: string,
    status: number,
    errors?: Record<string, string[]> | null,
    errorCode?: string | null,
  ) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
    this.errorCode = errorCode;
  }
}

const DEFAULT_API_BASE_URL = "http://localhost:8000/api/v1";

function apiBaseUrl() {
  return (import.meta.env.VITE_API_BASE_URL || DEFAULT_API_BASE_URL).replace(/\/$/, "");
}

function buildUrl(path: string, query?: Record<string, string | number | boolean | undefined>) {
  const url = new URL(`${apiBaseUrl()}${path.startsWith("/") ? path : `/${path}`}`);

  Object.entries(query ?? {}).forEach(([key, value]) => {
    if (value !== undefined && value !== "") {
      url.searchParams.set(key, String(value));
    }
  });

  return url.toString();
}

function isFormDataBody(body: BodyInit | null | undefined): body is FormData {
  return typeof FormData !== "undefined" && body instanceof FormData;
}

function activeApiLocale() {
  return i18n.resolvedLanguage?.toLowerCase().startsWith("en") ? "en" : "id";
}

async function apiFetch<T, TMeta = PaginationMeta>(
  path: string,
  init: RequestInit & { query?: Record<string, string | number | boolean | undefined> } = {},
) {
  const token = getAuthToken();
  const headers = new Headers(init.headers);

  headers.set("Accept", "application/json");
  headers.set("Accept-Language", activeApiLocale());
  if (init.body && !isFormDataBody(init.body) && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }
  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const response = await fetch(buildUrl(path, init.query), {
    ...init,
    headers,
  });

  const payload = (await response.json().catch(() => null)) as ApiEnvelope<T, TMeta> | null;

  if (!response.ok || !payload?.success) {
    if (response.status === 401) {
      clearAuthToken();
    }

    throw new ApiError(
      payload?.message || "Request failed",
      response.status,
      payload?.errors ?? null,
      payload?.error_code ?? null,
    );
  }

  return payload;
}

export async function apiRequest<T>(
  path: string,
  init: RequestInit & { query?: Record<string, string | number | boolean | undefined> } = {},
) {
  const payload = await apiFetch<T>(path, init);

  return payload.data;
}

export async function apiRequestEnvelope<
  T,
  TMeta = PaginationMeta,
>(
  path: string,
  init: RequestInit & { query?: Record<string, string | number | boolean | undefined> } = {},
) {
  return apiFetch<T, TMeta>(path, init);
}

export async function apiDownload(path: string, fallbackFilename: string) {
  const token = getAuthToken();
  const headers = new Headers({ Accept: "application/octet-stream" });
  headers.set("Accept-Language", activeApiLocale());

  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const response = await fetch(buildUrl(path), { headers });

  if (!response.ok) {
    const payload = (await response.json().catch(() => null)) as ApiEnvelope<unknown> | null;

    if (response.status === 401) {
      clearAuthToken();
    }

    throw new ApiError(
      payload?.message || "Request failed",
      response.status,
      payload?.errors ?? null,
      payload?.error_code ?? null,
    );
  }

  if (typeof document === "undefined" || typeof URL.createObjectURL !== "function") {
    throw new ApiError("File download is unavailable", 500);
  }

  const filename = parseDownloadFilename(
    response.headers.get("Content-Disposition"),
    fallbackFilename,
  );
  const objectUrl = URL.createObjectURL(await response.blob());

  try {
    const link = document.createElement("a");
    link.href = objectUrl;
    link.download = filename;
    link.hidden = true;
    document.body.appendChild(link);
    link.click();
    link.remove();
  } finally {
    URL.revokeObjectURL(objectUrl);
  }
}

export interface AuthenticatedBlobResponse {
  blob: Blob;
  contentType: string;
  contentLength: number | null;
  filename: string | null;
}

export async function apiFetchBlob(
  path: string,
  options: { signal?: AbortSignal } = {},
): Promise<AuthenticatedBlobResponse> {
  const token = getAuthToken();
  const headers = new Headers({ Accept: "application/pdf, image/jpeg, image/png" });
  headers.set("Accept-Language", activeApiLocale());

  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const response = await fetch(buildUrl(path), {
    headers,
    signal: options.signal,
  });

  if (!response.ok) {
    const payload = (await response.json().catch(() => null)) as ApiEnvelope<unknown> | null;

    if (response.status === 401) {
      clearAuthToken();
    }

    throw new ApiError(
      payload?.message || "Request failed",
      response.status,
      payload?.errors ?? null,
      payload?.error_code ?? null,
    );
  }

  const rawContentType = response.headers.get("Content-Type") ?? "";
  const contentType = rawContentType.split(";", 1)[0].trim().toLowerCase();
  const rawContentLength = response.headers.get("Content-Length");
  const parsedContentLength = rawContentLength === null ? Number.NaN : Number(rawContentLength);
  const contentDisposition = response.headers.get("Content-Disposition");

  return {
    blob: await response.blob(),
    contentType,
    contentLength: Number.isFinite(parsedContentLength) && parsedContentLength >= 0
      ? parsedContentLength
      : null,
    filename: contentDisposition
      ? parseDownloadFilename(contentDisposition, "") || null
      : null,
  };
}

function parseDownloadFilename(contentDisposition: string | null, fallbackFilename: string) {
  const encodedFilename = contentDisposition?.match(/filename\*\s*=\s*UTF-8''([^;]+)/i)?.[1];
  const basicFilename = contentDisposition?.match(/filename\s*=\s*(?:"([^"]*)"|([^;]+))/i);
  let candidate = encodedFilename
    ? safeDecodeURIComponent(encodedFilename.trim().replace(/^"|"$/g, ""))
    : (basicFilename?.[1] ?? basicFilename?.[2] ?? fallbackFilename).trim();

  candidate = stripControlCharacters(candidate.split(/[\\/]/).pop() ?? "")
    .trim()
    .slice(0, 255);

  return candidate || fallbackFilename;
}

function safeDecodeURIComponent(value: string) {
  try {
    return decodeURIComponent(value);
  } catch {
    return value;
  }
}

function stripControlCharacters(value: string) {
  return Array.from(value)
    .filter((character) => {
      const codePoint = character.codePointAt(0) ?? 0;
      return codePoint >= 32 && codePoint !== 127;
    })
    .join("");
}
