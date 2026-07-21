import { clearAuthToken } from "@/lib/auth-storage";

export const AUTH_SESSION_INVALIDATED_EVENT = "silappkasal:auth-session-invalidated";

export function invalidateAuthSession(): void {
  clearAuthToken();
  if (typeof window !== "undefined") {
    window.dispatchEvent(new Event(AUTH_SESSION_INVALIDATED_EVENT));
  }
}
