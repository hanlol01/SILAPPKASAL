import { apiRequest, apiRequestEnvelope, apiUpload } from "@/lib/api-client";
import type { PaginationMeta } from "@/lib/api-types";

export type ContentType = "article" | "faq" | "consultation";
export type ContentLifecycleStatus =
  | "draft"
  | "submitted"
  | "in_review"
  | "revision_requested"
  | "rejected"
  | "approved"
  | "published"
  | "archived";

export interface ContentSection {
  code: string;
  label: { id: string; en: string };
  description: string | null;
  display_order: number;
}

export interface ContentCategory {
  public_id: string;
  code: string;
  name: string;
  slug: string;
  description: string | null;
  icon_code: string | null;
  display_order: number;
  section_code?: string | null;
  scope: "global" | "campus";
}

export interface ManagedArticleCategory {
  public_id: string | null;
  name: string;
  section_code: "education" | "policy";
  scope: "global" | "campus";
  usage_count: number;
  can_manage: boolean;
  can_deactivate: boolean;
  result?: "created" | "existing" | "reactivated";
}

export interface DocumentMark {
  type: "bold" | "italic" | "underline" | "link";
  attrs?: { href?: string; title?: string };
}

export interface DocumentNode {
  type: string;
  text?: string;
  attrs?: Record<string, string | number | boolean | null>;
  marks?: DocumentMark[];
  content?: DocumentNode[];
}

export interface ContentAttachment {
  public_id: string;
  purpose: "attachment" | "cover" | "inline_image";
  filename: string;
  mime_type: string;
  extension: string;
  size: number;
  alt_text: string | null;
  display_order: number;
  download_url: string;
}

export interface ContentVersionSummary {
  public_id: string;
  version_number: number;
  status: ContentLifecycleStatus;
  title: string;
  excerpt: string | null;
  requires_editorial_review: boolean;
  submitted_at: string | null;
  reviewed_at: string | null;
  approved_at: string | null;
  published_at: string | null;
}

export interface ContentActor {
  name: string;
  email: string;
  role: string | null;
}

export interface ContentTimelineEvent {
  public_id: string;
  action: string;
  state: string;
  actor: {
    name: string | null;
    email: string | null;
    role: string | null;
    label: "central_team" | "system" | null;
  };
  timestamp: string;
  note: string | null;
  version_number: number | null;
  from_status: string | null;
  to_status: string | null;
  result: string | null;
}

export interface ManagedContentSummary {
  public_id: string;
  content_type: ContentType;
  slug: string;
  scope: "campus" | "global";
  section: ContentSection;
  category: ContentCategory | null;
  category_name: string | null;
  university: { code: string; name: string } | null;
  created_by: ContentActor | null;
  submitted_by: ContentActor | null;
  reviewed_by: ContentActor | null;
  approved_by: ContentActor | null;
  published_by: ContentActor | null;
  lock_version: number;
  lifecycle_status: ContentLifecycleStatus;
  version: ContentVersionSummary;
  has_editable_version: boolean;
  published_version: { public_id: string; version_number: number; published_at: string } | null;
  archived_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface ManagedContentDetail extends Omit<ManagedContentSummary, "version"> {
  version: ContentVersionSummary & {
    updated_at: string;
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
  review_feedback: {
    decision: "revision_requested" | "rejected";
    reason: string;
    decided_at: string;
  } | null;
  editorial_timeline: ContentTimelineEvent[];
  editorial_timeline_truncated: boolean;
}

export interface ContentPayload {
  content_type?: ContentType;
  section_code?: string;
  category_public_id?: string | null;
  category_name?: string | null;
  scope?: "campus" | "global";
  university_id?: number;
  title?: string;
  excerpt?: string | null;
  cover_alt_text?: string | null;
  document?: DocumentNode;
  question?: string;
  answer_document?: DocumentNode;
  display_order?: number;
  service_name?: string;
  description?: string | null;
  service_type?: string | null;
  email?: string | null;
  phone_display?: string | null;
  whatsapp_display?: string | null;
  office_address?: string | null;
  operating_hours?: string | null;
  procedure?: string | null;
  confidentiality_info?: string | null;
  emergency_available?: boolean;
  appointment_url?: string | null;
  action_label?: string | null;
  icon_code?: string | null;
  sort_order?: number;
  is_active?: boolean;
  verification_date?: string | null;
  verified_owner?: string | null;
  lock_version?: number;
}

type QueryValue = string | number | boolean | undefined;

export const contentManagementKeys = {
  all: ["content-management"] as const,
  lists: () => [...contentManagementKeys.all, "list"] as const,
  list: (filters: Record<string, QueryValue>) =>
    [...contentManagementKeys.lists(), filters] as const,
  summary: () => [...contentManagementKeys.all, "summary"] as const,
  detail: (publicId: string) => [...contentManagementKeys.all, "detail", publicId] as const,
  capabilities: () => [...contentManagementKeys.all, "capabilities"] as const,
  articleCategories: (section: string) => [...contentManagementKeys.all, "article-categories", section] as const,
  categories: (section: string) => [...contentManagementKeys.all, "categories", section] as const,
};

export async function getManagedContent(filters: Record<string, QueryValue>, signal?: AbortSignal) {
  const envelope = await apiRequestEnvelope<ManagedContentSummary[]>("/content-management/items", {
    query: filters,
    signal,
  });
  return { data: envelope.data, meta: envelope.meta as PaginationMeta };
}

export function getContentSummary() {
  return apiRequest<Record<ContentLifecycleStatus, number>>("/content-management/summary");
}

export function getManagedContentDetail(publicId: string, signal?: AbortSignal) {
  return apiRequest<ManagedContentDetail>(`/content-management/items/${publicId}`, { signal });
}

export function createManagedContent(payload: ContentPayload) {
  return apiRequest<ManagedContentSummary>("/content-management/items", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export function updateManagedContent(versionPublicId: string, payload: ContentPayload) {
  return apiRequest<ManagedContentSummary>(`/content-management/versions/${versionPublicId}`, {
    method: "PATCH",
    body: JSON.stringify(payload),
  });
}

export function submitManagedContent(versionPublicId: string, lockVersion: number) {
  return apiRequest<ManagedContentSummary>(
    `/content-management/versions/${versionPublicId}/submit`,
    { method: "POST", body: JSON.stringify({ lock_version: lockVersion }) },
  );
}

export function createContentRevision(itemPublicId: string, lockVersion: number) {
  return apiRequest<ManagedContentSummary>(`/content-management/items/${itemPublicId}/revisions`, {
    method: "POST",
    body: JSON.stringify({ lock_version: lockVersion }),
  });
}

export function uploadContentPdf(
  versionPublicId: string,
  file: File,
  onProgress?: (percent: number) => void,
) {
  const data = new FormData();
  data.append("purpose", "attachment");
  data.append("file", file);
  return apiUpload<ContentAttachment>(
    `/content-management/versions/${versionPublicId}/attachments`,
    data,
    onProgress,
  );
}

export function uploadContentCover(versionPublicId: string, file: File, altText: string) {
  const data = new FormData();
  data.append("purpose", "cover");
  data.append("file", file);
  data.append("alt_text", altText);
  return apiUpload<ContentAttachment>(`/content-management/versions/${versionPublicId}/attachments`, data);
}

export function removeContentAttachment(publicId: string) {
  return apiRequest<null>(`/content-management/attachments/${publicId}`, { method: "DELETE" });
}

export function getContentCategories(section: string) {
  return apiRequest<ContentCategory[]>("/content/categories", { query: { section } });
}

export function getContentManagementCapabilities() {
  return apiRequest<{ image_upload_available: boolean }>("/content-management/capabilities");
}

export function getManagedArticleCategories(section: string) {
  return apiRequest<ManagedArticleCategory[]>("/content-management/article-categories", {
    query: { section },
  });
}

export function createManagedArticleCategory(section: "education" | "policy", name: string) {
  return apiRequest<ManagedArticleCategory>("/content-management/article-categories", {
    method: "POST",
    body: JSON.stringify({ section, name }),
  });
}

export function deactivateManagedArticleCategory(publicId: string) {
  return apiRequest<null>(`/content-management/article-categories/${publicId}`, {
    method: "DELETE",
  });
}
