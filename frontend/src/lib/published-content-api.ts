import { apiRequest, apiRequestEnvelope } from "@/lib/api-client";
import type { PaginationMeta } from "@/lib/api-types";
import type {
  ContentAttachment,
  ContentCategory,
  ContentSection,
  DocumentNode,
} from "@/lib/content-management-api";

export interface PublishedArticle {
  public_id: string;
  slug: string;
  title: string;
  excerpt: string | null;
  category: ContentCategory | null;
  category_name: string | null;
  section: ContentSection;
  scope: "global" | "campus";
  cover: ContentAttachment | null;
  published_at: string;
  estimated_reading_minutes: number | null;
  featured: boolean;
  body?: DocumentNode | null;
  attachments?: ContentAttachment[];
  related_articles?: PublishedArticle[];
}

export interface PublishedFaq {
  public_id: string;
  category: ContentCategory | null;
  question: string;
  answer: DocumentNode | null;
  display_order: number;
}

export interface PublishedConsultation {
  public_id: string;
  service_name: string;
  description: string | null;
  service_type: string | null;
  email: string | null;
  phone: string | null;
  whatsapp: string | null;
  office_address: string | null;
  operating_hours: string | null;
  procedure: string | null;
  confidentiality_info: string | null;
  emergency_available: boolean;
  appointment_url: string | null;
  action_label: string | null;
  icon_code: string | null;
  display_order: number;
  scope: "global" | "campus";
  verification_date: string | null;
}

export interface PublishedContentFilters {
  section?: string;
  category?: string;
  article_category?: string;
  search?: string;
  page?: number;
  per_page?: number;
}

export const publishedContentKeys = {
  all: (identity: number | null | undefined) =>
    ["published-content", identity ?? "anonymous"] as const,
  sections: (identity: number | null | undefined) =>
    [...publishedContentKeys.all(identity), "sections"] as const,
  categories: (identity: number | null | undefined, section?: string) =>
    [...publishedContentKeys.all(identity), "categories", section ?? "all"] as const,
  articles: (identity: number | null | undefined, filters: PublishedContentFilters) =>
    [...publishedContentKeys.all(identity), "articles", filters] as const,
  article: (identity: number | null | undefined, publicId: string) =>
    [...publishedContentKeys.all(identity), "article", publicId] as const,
  articleCategories: (identity: number | null | undefined, section: string) =>
    [...publishedContentKeys.all(identity), "article-categories", section] as const,
  faqs: (identity: number | null | undefined, filters: PublishedContentFilters) =>
    [...publishedContentKeys.all(identity), "faqs", filters] as const,
  consultation: (identity: number | null | undefined) =>
    [...publishedContentKeys.all(identity), "consultation"] as const,
  featured: (identity: number | null | undefined, filters: FeaturedContentFilters = {}) =>
    [...publishedContentKeys.all(identity), "featured", filters] as const,
};

export function getPublishedSections(signal?: AbortSignal) {
  return apiRequest<ContentSection[]>("/content/sections", { signal });
}

export function getPublishedCategories(section?: string, signal?: AbortSignal) {
  return apiRequest<ContentCategory[]>("/content/categories", {
    query: { section },
    signal,
  });
}

export function getPublishedArticles(filters: PublishedContentFilters, signal?: AbortSignal) {
  return apiRequestEnvelope<PublishedArticle[], PaginationMeta>("/content/articles", {
    query: {
      section: filters.section,
      category: filters.category,
      article_category: filters.article_category,
      search: filters.search,
      page: filters.page,
      per_page: filters.per_page,
    },
    signal,
  });
}

export function getPublishedArticleBySlug(
  section: "education" | "policy",
  slug: string,
  signal?: AbortSignal,
) {
  return apiRequest<PublishedArticle>(
    `/content/articles/slug/${section}/${encodeURIComponent(slug)}`,
    { signal },
  );
}

export function getPublishedArticleCategories(section: "education" | "policy", signal?: AbortSignal) {
  return apiRequest<string[]>("/content/article-categories", { query: { section }, signal });
}

export function getPublishedFaqs(filters: PublishedContentFilters, signal?: AbortSignal) {
  return apiRequestEnvelope<PublishedFaq[], PaginationMeta>("/content/faqs", {
    query: {
      section: filters.section,
      category: filters.category,
      search: filters.search,
      page: filters.page,
      per_page: filters.per_page,
    },
    signal,
  });
}

export function getPublishedConsultation(signal?: AbortSignal) {
  return apiRequest<PublishedConsultation[]>("/content/consultation", { signal });
}

export interface FeaturedContentFilters {
  section?: "education" | "policy";
  limit?: number;
  require_cover?: boolean;
}

export function getFeaturedContent(filters: FeaturedContentFilters = {}, signal?: AbortSignal) {
  return apiRequest<PublishedArticle[]>("/content/featured", {
    query: {
      section: filters.section,
      limit: filters.limit,
      require_cover: filters.require_cover,
    },
    signal,
  });
}
