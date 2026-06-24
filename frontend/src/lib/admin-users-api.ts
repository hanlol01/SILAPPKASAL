import { apiRequest, apiRequestEnvelope } from "@/lib/api-client";
import type { ApiUser, PaginationMeta } from "@/lib/api-types";

type QueryValue = string | number | boolean | undefined;

export interface UserListResult {
  data: ApiUser[];
  meta: PaginationMeta;
}

export interface ManualReporterPayload {
  name: string;
  email: string;
  nim: string;
  phone_number: string;
  university_id: number;
  faculty_id?: number | null;
  study_program_id: number;
  password: string;
}

export interface TemporaryPasswordResult {
  user: ApiUser;
  temporary_password: string;
}

export const adminUsersQueryKeys = {
  list: (query?: Record<string, QueryValue>) => ["admin-users", query] as const,
  detail: (id: string | number) => ["admin-users", id] as const,
};

export async function getUsers(query?: Record<string, QueryValue>): Promise<UserListResult> {
  const envelope = await apiRequestEnvelope<ApiUser[]>("/users", { query });
  return {
    data: envelope.data,
    meta: envelope.meta ?? { current_page: 1, per_page: envelope.data.length, total: envelope.data.length, last_page: 1 },
  };
}

export function createReporter(payload: ManualReporterPayload) {
  return apiRequest<TemporaryPasswordResult>("/users/reporters", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export function activateUser(id: string | number) {
  return apiRequest<ApiUser>(`/users/${id}/activate`, { method: "PATCH" });
}

export function deactivateUser(id: string | number) {
  return apiRequest<ApiUser>(`/users/${id}/deactivate`, { method: "PATCH" });
}

export function resetUserPassword(id: string | number) {
  return apiRequest<TemporaryPasswordResult>(`/users/${id}/reset-password`, { method: "PATCH" });
}
