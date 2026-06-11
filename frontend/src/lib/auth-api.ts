import { apiRequest } from "@/lib/api-client";
import type { ApiUser, LoginResponseData } from "@/lib/api-types";

export function loginRequest(identifier: string, password: string) {
  return apiRequest<LoginResponseData>("/auth/login", {
    method: "POST",
    body: JSON.stringify({ identifier, password }),
  });
}

export function meRequest() {
  return apiRequest<ApiUser>("/auth/me");
}

export function logoutRequest() {
  return apiRequest<null>("/auth/logout", { method: "POST" });
}
