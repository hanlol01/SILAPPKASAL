import { createContext, useContext, useEffect, useState, type ReactNode } from "react";

export type Role = "Super Admin" | "Satgas Officer" | "Reviewer";

export interface SessionUser {
  email: string;
  name: string;
  role: Role;
}

interface AuthContextType {
  user: SessionUser | null;
  login: (email: string, password: string, remember: boolean) => { ok: boolean; error?: string };
  logout: () => void;
}

const AuthContext = createContext<AuthContextType | null>(null);

const DEMO_USERS: Record<string, { password: string; name: string; role: Role }> = {
  "admin@safecampus.id": { password: "admin123", name: "Dr. Sarah Putri", role: "Super Admin" },
  "officer@safecampus.id": { password: "officer123", name: "Andi Wijaya", role: "Satgas Officer" },
  "reviewer@safecampus.id": { password: "reviewer123", name: "Maya Lestari", role: "Reviewer" },
};

const STORAGE_KEY = "safecampus_session";

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<SessionUser | null>(null);

  useEffect(() => {
    if (typeof window === "undefined") return;
    const raw = localStorage.getItem(STORAGE_KEY) || sessionStorage.getItem(STORAGE_KEY);
    if (raw) {
      try {
        setUser(JSON.parse(raw));
      } catch {
        // ignore
      }
    }
  }, []);

  const login = (email: string, password: string, remember: boolean) => {
    const record = DEMO_USERS[email.toLowerCase().trim()];
    if (!record || record.password !== password) {
      return { ok: false, error: "Invalid email or password." };
    }
    const session: SessionUser = { email, name: record.name, role: record.role };
    setUser(session);
    const store = remember ? localStorage : sessionStorage;
    store.setItem(STORAGE_KEY, JSON.stringify(session));
    return { ok: true };
  };

  const logout = () => {
    setUser(null);
    localStorage.removeItem(STORAGE_KEY);
    sessionStorage.removeItem(STORAGE_KEY);
  };

  return <AuthContext.Provider value={{ user, login, logout }}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}
