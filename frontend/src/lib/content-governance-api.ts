import { apiRequest, apiRequestEnvelope } from "@/lib/api-client";
import type { PaginationMeta } from "@/lib/api-types";
import type {
  ContentAttachment,
  ContentCategory,
  ContentLifecycleStatus,
  ContentSection,
  ContentType,
  DocumentNode,
} from "@/lib/content-management-api";

type QueryValue = string | number | boolean | undefined;

export interface GovernanceCapabilities {
  start_review: boolean;
  request_revision: boolean;
  reject: boolean;
  approve: boolean;
  publish: boolean;
  archive: boolean;
}

export interface GovernanceContentSummary {
  public_id: string;
  content_type: ContentType;
  scope: "global" | "campus";
  section: ContentSection;
  category: ContentCategory | null;
  category_name: string | null;
  university: { code: string; name: string } | null;
  author: { name: string; role: string | null } | null;
  lock_version: number;
  lifecycle_status: ContentLifecycleStatus;
  version: {
    public_id: string;
    version_number: number;
    status: ContentLifecycleStatus;
    title: string;
    excerpt: string | null;
    submitted_at: string | null;
    published_at: string | null;
    requires_editorial_review: boolean;
  };
  capabilities: GovernanceCapabilities;
}

export type GovernanceVersionDetail = GovernanceContentSummary["version"] & {
  editorial_note: string | null;
  published_at: string | null;
  article: {
    document: DocumentNode | null;
    estimated_reading_minutes: number;
    cover_alt_text: string | null;
  } | null;
  faq: { question: string; answer_document: DocumentNode | null; display_order: number } | null;
  consultation: {
    service_name: string;
    description: string | null;
    service_type: string | null;
    email: string | null;
    phone_display: string | null;
    whatsapp_display: string | null;
    office_address: string | null;
    operating_hours: string | null;
    procedure: string | null;
    confidentiality_info: string | null;
    emergency_available: boolean;
    appointment_url: string | null;
    action_label: string | null;
    icon_code: string | null;
    sort_order: number;
    is_active: boolean;
    verification_date: string | null;
    verified_owner: string | null;
  } | null;
  attachments: ContentAttachment[];
};

export interface GovernanceContentDetail extends Omit<GovernanceContentSummary, "version"> {
  version: GovernanceVersionDetail;
  previous_published_version: GovernanceVersionDetail | null;
  decision_history: Array<{
    public_id: string;
    state: string;
    actor: { name: string | null; role: string | null };
    timestamp: string;
    note: string | null;
    version_number: number | null;
  }>;
  archived_at: string | null;
}

export interface FeaturedPlacement {
  public_id: string;
  scope: "global" | "campus";
  university: { code: string; name: string } | null;
  rank: number;
  is_active: boolean;
  active_from: string | null;
  active_until: string | null;
  state: "current" | "future" | "expired" | "inactive";
  updated_at: string;
  concurrency_token: string;
  content: FeaturedEligibleContent;
}

export interface FeaturedEligibleContent {
  public_id: string;
  scope: "global" | "campus";
  university: { code: string; name: string } | null;
  title: string;
  excerpt: string | null;
  published_at: string;
  section: string;
  category: string | null;
}

export interface FeaturedPayload {
  content_public_id: string;
  scope: "global" | "campus";
  university_code?: string | null;
  rank: number;
  is_active: boolean;
  active_from?: string | null;
  active_until?: string | null;
  concurrency_token?: string;
}

export interface GovernanceCategoryChoice {
  public_id: string;
  name: string;
  section_code: string;
  scope: "global" | "campus";
  university: { code: string; name: string } | null;
}

export const contentGovernanceKeys = {
  all: ["content-governance"] as const,
  reviews: (filters: Record<string, QueryValue>) =>
    [...contentGovernanceKeys.all, "reviews", filters] as const,
  published: (filters: Record<string, QueryValue>) =>
    [...contentGovernanceKeys.all, "published", filters] as const,
  detail: (publicId: string) => [...contentGovernanceKeys.all, "detail", publicId] as const,
  featured: (filters: Record<string, QueryValue>) =>
    [...contentGovernanceKeys.all, "featured", filters] as const,
  eligible: (filters: Record<string, QueryValue>) =>
    [...contentGovernanceKeys.all, "eligible", filters] as const,
  campuses: () => [...contentGovernanceKeys.all, "campuses"] as const,
  categories: (section: string) => [...contentGovernanceKeys.all, "categories", section] as const,
};

export async function getGovernanceReviews(filters: Record<string, QueryValue>, signal?: AbortSignal) {
  const envelope = await apiRequestEnvelope<GovernanceContentSummary[]>(
    "/content-governance/reviews",
    { query: filters, signal },
  );
  return { data: envelope.data, meta: envelope.meta as PaginationMeta };
}

export async function getGovernancePublished(filters: Record<string, QueryValue>, signal?: AbortSignal) {
  const envelope = await apiRequestEnvelope<GovernanceContentSummary[]>(
    "/content-governance/published",
    { query: filters, signal },
  );
  return { data: envelope.data, meta: envelope.meta as PaginationMeta };
}

export function getGovernanceDetail(publicId: string, signal?: AbortSignal) {
  return apiRequest<GovernanceContentDetail>(`/content-governance/items/${publicId}`, { signal });
}

export function getGovernanceCampuses(signal?: AbortSignal) {
  return apiRequest<Array<{ code: string; name: string }>>("/content-governance/campuses", { signal });
}

export function getGovernanceCategories(section?: string, signal?: AbortSignal) {
  return apiRequest<GovernanceCategoryChoice[]>("/content-governance/categories", {
    query: { section: section || undefined },
    signal,
  });
}

function editorialAction(
  versionPublicId: string,
  action: "start-review" | "request-revision" | "reject" | "approve" | "publish",
  body: Record<string, unknown>,
) {
  return apiRequest<GovernanceContentSummary>(
    `/content-governance/versions/${versionPublicId}/${action}`,
    { method: "POST", body: JSON.stringify(body) },
  );
}

export const startContentReview = (version: string, lockVersion: number) =>
  editorialAction(version, "start-review", { lock_version: lockVersion });
export const requestContentRevision = (version: string, lockVersion: number, reason: string) =>
  editorialAction(version, "request-revision", { lock_version: lockVersion, reason });
export const rejectContent = (version: string, lockVersion: number, reason: string) =>
  editorialAction(version, "reject", { lock_version: lockVersion, reason });
export const approveContent = (version: string, lockVersion: number, note?: string) =>
  editorialAction(version, "approve", { lock_version: lockVersion, note: note || null });
export const publishContent = (version: string, lockVersion: number) =>
  editorialAction(version, "publish", { lock_version: lockVersion });
export const archiveContent = (publicId: string, lockVersion: number, reason: string) =>
  apiRequest<GovernanceContentSummary>(`/content-governance/items/${publicId}/archive`, {
    method: "POST",
    body: JSON.stringify({ lock_version: lockVersion, reason }),
  });

export function getFeaturedPlacements(filters: Record<string, QueryValue>, signal?: AbortSignal) {
  return apiRequest<FeaturedPlacement[]>("/content-governance/featured", { query: filters, signal });
}

export function getFeaturedEligible(filters: Record<string, QueryValue>, signal?: AbortSignal) {
  return apiRequest<FeaturedEligibleContent[]>("/content-governance/featured/eligible", {
    query: filters,
    signal,
  });
}

export function getFeaturedCampuses(signal?: AbortSignal) {
  return apiRequest<Array<{ code: string; name: string }>>("/content-governance/featured/campuses", { signal });
}

export function createFeaturedPlacement(payload: FeaturedPayload) {
  return apiRequest<FeaturedPlacement>("/content-governance/featured", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export function updateFeaturedPlacement(publicId: string, payload: FeaturedPayload) {
  return apiRequest<FeaturedPlacement>(`/content-governance/featured/${publicId}`, {
    method: "PATCH",
    body: JSON.stringify(payload),
  });
}

export function removeFeaturedPlacement(publicId: string, concurrencyToken: string) {
  return apiRequest<null>(`/content-governance/featured/${publicId}`, {
    method: "DELETE",
    body: JSON.stringify({ concurrency_token: concurrencyToken }),
  });
}
