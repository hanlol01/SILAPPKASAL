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

export interface DocumentMark {
  type: "bold" | "italic" | "link";
  attrs?: { href?: string };
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
}

export interface ManagedContentSummary {
  public_id: string;
  content_type: ContentType;
  slug: string;
  scope: "campus";
  section: ContentSection;
  category: ContentCategory | null;
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
    submitted_at: string | null;
    published_at: string | null;
    updated_at: string;
    article: {
      document: DocumentNode | null;
      estimated_reading_minutes: number;
      cover_alt_text: string | null;
      consultation_cta_public_id: string | null;
    } | null;
    faq: { question: string; answer_document: DocumentNode | null; display_order: number } | null;
    consultation: {
      service_name: string;
      description: string | null;
      email: string | null;
      phone_display: string | null;
      whatsapp_display: string | null;
      office_address: string | null;
      operating_hours: string | null;
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
}

export interface ContentPayload {
  content_type?: ContentType;
  section_code?: string;
  category_public_id?: string | null;
  scope?: "campus";
  university_id?: number;
  title?: string;
  excerpt?: string | null;
  document?: DocumentNode;
  consultation_cta_public_id?: string | null;
  question?: string;
  answer_document?: DocumentNode;
  display_order?: number;
  service_name?: string;
  description?: string | null;
  email?: string | null;
  phone_display?: string | null;
  whatsapp_display?: string | null;
  office_address?: string | null;
  operating_hours?: string | null;
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
  consultationOptions: () => [...contentManagementKeys.all, "consultation-options"] as const,
  categories: (section: string) => [...contentManagementKeys.all, "categories", section] as const,
};

export async function getManagedContent(filters: Record<string, QueryValue>) {
  const envelope = await apiRequestEnvelope<ManagedContentSummary[]>("/content-management/items", {
    query: filters,
  });
  return { data: envelope.data, meta: envelope.meta as PaginationMeta };
}

export function getContentSummary() {
  return apiRequest<Record<ContentLifecycleStatus, number>>("/content-management/summary");
}

export function getManagedContentDetail(publicId: string) {
  return apiRequest<ManagedContentDetail>(`/content-management/items/${publicId}`);
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

export function submitManagedContent(versionPublicId: string) {
  return apiRequest<ManagedContentSummary>(
    `/content-management/versions/${versionPublicId}/submit`,
    { method: "POST" },
  );
}

export function createContentRevision(itemPublicId: string) {
  return apiRequest<ManagedContentSummary>(`/content-management/items/${itemPublicId}/revisions`, {
    method: "POST",
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

export function removeContentAttachment(publicId: string) {
  return apiRequest<null>(`/content-management/attachments/${publicId}`, { method: "DELETE" });
}

export function getContentCategories(section: string) {
  return apiRequest<ContentCategory[]>("/content/categories", { query: { section } });
}

export function getConsultationOptions() {
  return apiRequest<Array<{ public_id: string; scope: "global" | "campus"; service_name: string }>>(
    "/content-management/consultation-options",
  );
}
