import { type ReactNode, useEffect, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { loginRequest, logoutRequest, meRequest } from "@/lib/auth-api";
import { AuthContext } from "@/lib/auth-context";
import { clearAuthToken, getAuthToken, setAuthToken } from "@/lib/auth-storage";
import type { ApiUser } from "@/lib/api-types";

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const [user, setUser] = useState<ApiUser | null>(null);
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

  const login = async (identifier: string, password: string, remember: boolean) => {
    const result = await loginRequest(identifier, password);
    setAuthToken(result.token, remember ? "local" : "session");
    setUser(result.user);
    queryClient.setQueryData(["auth", "me"], result.user);
  };

  const logout = async () => {
    try {
      if (getAuthToken()) {
        await logoutRequest();
      }
    } finally {
      clearAuthToken();
      setUser(null);
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
        isAuthenticated: Boolean(user),
        isHydrating,
        roleCode,
        login,
        logout,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}
