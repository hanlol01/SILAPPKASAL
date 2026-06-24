import { type ReactNode, useEffect, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { loginRequest, logoutRequest, meRequest } from "@/lib/auth-api";
import { AuthContext } from "@/lib/auth-context";
import {
  clearAuthToken,
  clearRegistrationState,
  getAuthToken,
  getRegistrationState,
  setAuthToken,
  setRegistrationState,
} from "@/lib/auth-storage";
import type { ApiUser, ReporterRegistrationAuthState } from "@/lib/api-types";

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const [user, setUser] = useState<ApiUser | null>(null);
  const [registration, setRegistrationValue] = useState<ReporterRegistrationAuthState | null>(() =>
    getRegistrationState<ReporterRegistrationAuthState>(),
  );
  const hasToken = Boolean(getAuthToken());

  const meQuery = useQuery({
    queryKey: ["auth", "me"],
    queryFn: meRequest,
    enabled: hasToken && !user,
    retry: false,
  });

  useEffect(() => {
    if (meQuery.data) {
      setUser(meQuery.data);
    }
  }, [meQuery.data]);

  const setRegistration = (value: ReporterRegistrationAuthState | null) => {
    setRegistrationValue(value);
    if (value) {
      clearAuthToken();
      setUser(null);
      setRegistrationState(value);
      return;
    }

    clearRegistrationState();
  };

  const login = async (identifier: string, password: string, remember: boolean) => {
    const result = await loginRequest(identifier, password);

    if (result.type === "registration") {
      setRegistration(result.registration);
      queryClient.removeQueries({ queryKey: ["auth"] });
      return "registration" as const;
    }

    clearRegistrationState();
    setRegistrationValue(null);
    setAuthToken(result.token, remember ? "local" : "session");
    setUser(result.user);
    queryClient.setQueryData(["auth", "me"], result.user);
    return "user" as const;
  };

  const logout = async () => {
    try {
      if (getAuthToken()) {
        await logoutRequest();
      }
    } finally {
      clearAuthToken();
      clearRegistrationState();
      setUser(null);
      setRegistrationValue(null);
      queryClient.removeQueries({ queryKey: ["auth"] });
      queryClient.removeQueries({ queryKey: ["dashboard"] });
      queryClient.removeQueries({ queryKey: ["master-data"] });
      queryClient.removeQueries({ queryKey: ["portal"] });
    }
  };

  const roleCode = user?.role?.code ?? null;
  const isHydrating = hasToken && !user && (meQuery.isPending || meQuery.isFetching);

  return (
    <AuthContext.Provider
      value={{
        user,
        registration,
        isAuthenticated: Boolean(user),
        isRegistrationAuthenticated: Boolean(registration),
        isHydrating,
        roleCode,
        login,
        setRegistration,
        logout,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}
