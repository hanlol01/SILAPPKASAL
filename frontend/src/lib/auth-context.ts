import { createContext } from "react";
import type { ApiUser, RoleCode } from "@/lib/api-types";

export interface AuthContextType {
  user: ApiUser | null;
  isAuthenticated: boolean;
  isHydrating: boolean;
  roleCode: RoleCode | null;
  login: (identifier: string, password: string, remember: boolean) => Promise<void>;
  logout: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextType | null>(null);
