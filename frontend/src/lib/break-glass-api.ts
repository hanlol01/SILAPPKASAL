import { apiRequest, apiRequestEnvelope } from "@/lib/api-client";
import type {
  BreakGlassDenyPayload,
  BreakGlassPage,
  BreakGlassRequest,
  BreakGlassRequestPayload,
  BreakGlassReveal,
} from "@/lib/break-glass-types";

export const breakGlassQueryKeys = {
  pending: (page = 1) => ["break-glass", "pending", page] as const,
  history: (page = 1) => ["break-glass", "history", page] as const,
  request: (id: string | number) => ["break-glass", "request", id] as const,
};

export async function requestBreakGlass(payload: BreakGlassRequestPayload) {
  return apiRequest<BreakGlassRequest>("/break-glass/request", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function getPendingRequests(page = 1): Promise<BreakGlassPage> {
  const envelope = await apiRequestEnvelope<BreakGlassRequest[]>("/break-glass/pending", {
    query: { page },
  });

  return {
    data: envelope.data,
    meta: envelope.meta ?? emptyMeta(envelope.data.length),
  };
}

export async function getBreakGlassHistory(page = 1): Promise<BreakGlassPage> {
  const envelope = await apiRequestEnvelope<BreakGlassRequest[]>("/break-glass/history", {
    query: { page },
  });

  return {
    data: envelope.data,
    meta: envelope.meta ?? emptyMeta(envelope.data.length),
  };
}

export function getBreakGlassRequest(id: string | number) {
  return apiRequest<BreakGlassRequest>(`/break-glass/${id}`);
}

export function approveBreakGlass(id: string | number) {
  return apiRequest<BreakGlassRequest>(`/break-glass/${id}/approve`, {
    method: "PATCH",
  });
}

export function denyBreakGlass(id: string | number, payload: BreakGlassDenyPayload) {
  return apiRequest<BreakGlassRequest>(`/break-glass/${id}/deny`, {
    method: "PATCH",
    body: JSON.stringify(payload),
  });
}

export function revealIdentity(id: string | number) {
  return apiRequest<BreakGlassReveal>(`/break-glass/${id}/reveal`);
}

function emptyMeta(total: number) {
  return {
    current_page: 1,
    per_page: total,
    total,
    last_page: 1,
  };
}
