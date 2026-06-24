import { createContext } from "react";
import type { ApiUser, ReporterRegistrationAuthState, RoleCode } from "@/lib/api-types";

export interface AuthContextType {
  user: ApiUser | null;
  registration: ReporterRegistrationAuthState | null;
  isAuthenticated: boolean;
  isRegistrationAuthenticated: boolean;
  isHydrating: boolean;
  roleCode: RoleCode | null;
  login: (identifier: string, password: string, remember: boolean) => Promise<"user" | "registration">;
  setRegistration: (registration: ReporterRegistrationAuthState | null) => void;
  logout: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextType | null>(null);
