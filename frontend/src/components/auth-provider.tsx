import { type ReactNode } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { loginRequest, logoutRequest, meRequest } from "@/lib/auth-api";
import { AuthContext } from "@/lib/auth-context";
import { clearAuthToken, getAuthToken, setAuthToken } from "@/lib/auth-storage";

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const hasToken = Boolean(getAuthToken());

  const meQuery = useQuery({
    queryKey: ["auth", "me"],
    queryFn: meRequest,
    enabled: hasToken,
    retry: false,
  });

  const login = async (identifier: string, password: string, remember: boolean) => {
    const result = await loginRequest(identifier, password);
    setAuthToken(result.token, remember ? "local" : "session");
    queryClient.setQueryData(["auth", "me"], result.user);
  };

  const logout = async () => {
    try {
      if (getAuthToken()) {
        await logoutRequest();
      }
    } finally {
      clearAuthToken();
      queryClient.removeQueries({ queryKey: ["auth"] });
      queryClient.removeQueries({ queryKey: ["dashboard"] });
      queryClient.removeQueries({ queryKey: ["master-data"] });
    }
  };

  const user = meQuery.data ?? null;
  const roleCode = user?.role?.code ?? null;

  return (
    <AuthContext.Provider
      value={{
        user,
        isAuthenticated: Boolean(user),
        isHydrating: hasToken && meQuery.isLoading,
        roleCode,
        login,
        logout,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}
