import { apiRequest, apiRequestEnvelope } from "@/lib/api-client";
import type { PaginationMeta } from "@/lib/api-types";
import type { Faculty, StudyProgram, University } from "@/lib/registration-api";

type QueryValue = string | number | boolean | undefined;

export interface CampusListResponse<T> {
  data: T[];
  meta: PaginationMeta;
}

export interface CampusUniversity extends University {
  address: string | null;
  website: string | null;
  email: string | null;
  hotline: string | null;
  is_active: boolean;
  sort_order: number;
  faculties_count?: number;
  study_programs_count?: number;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface CampusFaculty extends Faculty {
  is_active: boolean;
  sort_order: number;
  university?: Pick<CampusUniversity, "id" | "code" | "name">;
  study_programs_count?: number;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface CampusStudyProgram extends StudyProgram {
  is_active: boolean;
  sort_order: number;
  university?: Pick<CampusUniversity, "id" | "code" | "name">;
  faculty?: Pick<CampusFaculty, "id" | "code" | "name"> | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface UniversityPayload {
  code: string;
  name: string;
  abbreviation?: string | null;
  address?: string | null;
  website?: string | null;
  email?: string | null;
  hotline?: string | null;
  type: string;
  has_faculties: boolean;
  sort_order?: number;
}

export interface FacultyPayload {
  university_id: number;
  code: string;
  name: string;
  sort_order?: number;
}

export interface StudyProgramPayload {
  university_id: number;
  faculty_id?: number | null;
  code: string;
  name: string;
  degree_level: string;
  sort_order?: number;
}

export const campusAdminQueryKeys = {
  universities: (query?: Record<string, QueryValue>) => ["campus-admin", "universities", query] as const,
  faculties: (query?: Record<string, QueryValue>) => ["campus-admin", "faculties", query] as const,
  studyPrograms: (query?: Record<string, QueryValue>) => ["campus-admin", "study-programs", query] as const,
};

async function list<T>(path: string, query?: Record<string, QueryValue>): Promise<CampusListResponse<T>> {
  const envelope = await apiRequestEnvelope<T[]>(path, { query });
  return {
    data: envelope.data,
    meta: envelope.meta ?? { current_page: 1, per_page: envelope.data.length, total: envelope.data.length, last_page: 1 },
  };
}

export const getCampusUniversities = (query?: Record<string, QueryValue>) =>
  list<CampusUniversity>("/campus-admin/universities", query);

export const getCampusUniversity = (id: number | string) =>
  apiRequest<CampusUniversity>(`/campus-admin/universities/${id}`);

export const createCampusUniversity = (payload: UniversityPayload) =>
  apiRequest<CampusUniversity>("/campus-admin/universities", {
    method: "POST",
    body: JSON.stringify(payload),
  });

export const updateCampusUniversity = (id: number, payload: UniversityPayload) =>
  apiRequest<CampusUniversity>(`/campus-admin/universities/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });

export const toggleCampusUniversity = (id: number) =>
  apiRequest<CampusUniversity>(`/campus-admin/universities/${id}/toggle-active`, { method: "PATCH" });

export const getCampusFaculties = (query?: Record<string, QueryValue>) =>
  list<CampusFaculty>("/campus-admin/faculties", query);

export const createCampusFaculty = (payload: FacultyPayload) =>
  apiRequest<CampusFaculty>("/campus-admin/faculties", {
    method: "POST",
    body: JSON.stringify(payload),
  });

export const updateCampusFaculty = (id: number, payload: FacultyPayload) =>
  apiRequest<CampusFaculty>(`/campus-admin/faculties/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });

export const toggleCampusFaculty = (id: number) =>
  apiRequest<CampusFaculty>(`/campus-admin/faculties/${id}/toggle-active`, { method: "PATCH" });

export const getCampusStudyPrograms = (query?: Record<string, QueryValue>) =>
  list<CampusStudyProgram>("/campus-admin/study-programs", query);

export const createCampusStudyProgram = (payload: StudyProgramPayload) =>
  apiRequest<CampusStudyProgram>("/campus-admin/study-programs", {
    method: "POST",
    body: JSON.stringify(payload),
  });

export const updateCampusStudyProgram = (id: number, payload: StudyProgramPayload) =>
  apiRequest<CampusStudyProgram>(`/campus-admin/study-programs/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });

export const toggleCampusStudyProgram = (id: number) =>
  apiRequest<CampusStudyProgram>(`/campus-admin/study-programs/${id}/toggle-active`, { method: "PATCH" });
