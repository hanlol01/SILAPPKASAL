import { apiRequest, apiRequestEnvelope } from "@/lib/api-client";
import type { CampusRef, PaginationMeta, ReporterRegistrationAuthState } from "@/lib/api-types";

type QueryValue = string | number | boolean | undefined;

export interface University extends CampusRef {
  abbreviation: string | null;
  type: string | null;
  has_faculties: boolean;
}

export interface Faculty extends CampusRef {
  university_id: number;
}

export interface StudyProgram extends CampusRef {
  university_id: number;
  faculty_id: number | null;
  degree_level: string | null;
}

export interface ReporterRegistrationPayload {
  name: string;
  nim: string;
  email: string;
  phone_number: string;
  university_id: number;
  faculty_id?: number | null;
  study_program_id: number;
  password: string;
  password_confirmation: string;
}

export interface ReporterRegistrationCorrectPayload {
  email: string;
  password: string;
  name: string;
  nim: string;
  phone_number: string;
  university_id: number;
  faculty_id?: number | null;
  study_program_id: number;
  new_password?: string;
  new_password_confirmation?: string;
}

export interface ReporterRegistrationListItem extends ReporterRegistrationAuthState {
  reviewed_by?: number | null;
  reviewed_at?: string | null;
  approved_user_id?: number | null;
}

export interface ReporterRegistrationSubmitResult {
  id: number;
  registration_number: string;
  status: string;
  submitted_at: string | null;
}

export const campusQueryKeys = {
  universities: () => ["campus", "universities"] as const,
  faculties: (universityId?: number | null) => ["campus", "faculties", universityId ?? ""] as const,
  studyPrograms: (universityId?: number | null, facultyId?: number | null) =>
    ["campus", "study-programs", universityId ?? "", facultyId ?? ""] as const,
};

export const registrationQueryKeys = {
  list: (query?: Record<string, QueryValue>) => ["reporter-registrations", query] as const,
  detail: (id: string | number) => ["reporter-registrations", id] as const,
};

export function getUniversities() {
  return apiRequest<University[]>("/universities");
}

export function getFaculties(universityId: number) {
  return apiRequest<Faculty[]>("/faculties", { query: { university_id: universityId } });
}

export function getStudyPrograms(universityId: number, facultyId?: number | null) {
  return apiRequest<StudyProgram[]>("/study-programs", {
    query: { university_id: universityId, faculty_id: facultyId ?? undefined },
  });
}

export function submitReporterRegistration(payload: ReporterRegistrationPayload) {
  return apiRequest<ReporterRegistrationSubmitResult>("/reporter-registrations", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export function correctReporterRegistration(payload: ReporterRegistrationCorrectPayload) {
  return apiRequest<ReporterRegistrationAuthState>("/reporter-registrations/correct", {
    method: "PATCH",
    body: JSON.stringify(payload),
  });
}

export async function getReporterRegistrations(
  query?: Record<string, QueryValue>,
): Promise<{ data: ReporterRegistrationListItem[]; meta: PaginationMeta }> {
  const envelope = await apiRequestEnvelope<ReporterRegistrationListItem[]>("/reporter-registrations", { query });
  return {
    data: envelope.data,
    meta: envelope.meta ?? { current_page: 1, per_page: envelope.data.length, total: envelope.data.length, last_page: 1 },
  };
}

export function getReporterRegistration(id: string | number) {
  return apiRequest<ReporterRegistrationListItem>(`/reporter-registrations/${id}`);
}

export function approveReporterRegistration(id: string | number) {
  return apiRequest<ReporterRegistrationListItem>(`/reporter-registrations/${id}/approve`, { method: "PATCH" });
}

export function rejectReporterRegistration(id: string | number, rejection_reason: string) {
  return apiRequest<ReporterRegistrationListItem>(`/reporter-registrations/${id}/reject`, {
    method: "PATCH",
    body: JSON.stringify({ rejection_reason }),
  });
}
