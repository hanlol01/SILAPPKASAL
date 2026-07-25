import { createFileRoute, useNavigate, Link } from "@tanstack/react-router";
import { useState, useEffect } from "react";
import { useTranslation } from "react-i18next";

import { toast } from "sonner";
import { apiErrorMessage } from "@/lib/form-errors";
import { AuthSessionLoader } from "@/components/auth-session-loader";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { PasswordInput } from "@/components/ui/password-input";
import { Checkbox } from "@/components/ui/checkbox";
import { Card, CardContent } from "@/components/ui/card";
import { InstitutionalSupport } from "@/components/ui/institutional-support";
import { useAuth } from "@/hooks/use-auth";
import { hasDashboardAccess, hasPortalAccess } from "@/lib/auth-roles";
import type { RoleCode } from "@/lib/api-types";

type LoginSearch = {
  redirect?: string;
};

function homeForRole(roleCode: string | null): string {
  return hasPortalAccess(roleCode as import("@/lib/api-types").RoleCode | null)
    ? "/portal"
    : "/dashboard";
}

function isInternalRedirect(value: string) {
  return (
    value.startsWith("/") &&
    !value.startsWith("//") &&
    !value.includes("\\") &&
    !value.includes("\n") &&
    !value.includes("\r")
  );
}

function isWithinPath(path: string, base: string) {
  return path === base || path.startsWith(`${base}/`);
}

function safeRedirectForRole(roleCode: RoleCode | null, redirect: string | undefined) {
  if (!redirect || !isInternalRedirect(redirect)) {
    return homeForRole(roleCode);
  }

  const pathOnly = redirect.split(/[?#]/, 1)[0];
  if (hasPortalAccess(roleCode) && isWithinPath(pathOnly, "/portal")) {
    return redirect;
  }

  if (hasDashboardAccess(roleCode) && isWithinPath(pathOnly, "/dashboard")) {
    return redirect;
  }

  return homeForRole(roleCode);
}

export const Route = createFileRoute("/login")({
  validateSearch: (search: Record<string, unknown>): LoginSearch => ({
    redirect: typeof search.redirect === "string" ? search.redirect : undefined,
  }),
  component: LoginPage,
  head: () => ({
    meta: [
      { title: "Masuk - SILAPPKASAL" },
      { name: "description", content: "Masuk ke dashboard admin/satgas SILAPPKASAL." },
    ],
  }),
});

function LoginPage() {
  const { t } = useTranslation(["auth", "common"]);
  const { isHydrating, login, user } = useAuth();
  const navigate = useNavigate();
  const { redirect } = Route.useSearch();
  const [identifier, setIdentifier] = useState("");
  const [password, setPassword] = useState("");
  const [remember, setRemember] = useState(true);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (user) {
      navigate({
        href: safeRedirectForRole(user.role?.code ?? null, redirect),
        replace: true,
      });
    }
  }, [redirect, user, navigate]);

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const result = await login(identifier, password, remember);
      toast.success(t("welcomeBack"));
      if (result === "registration") {
        navigate({ to: "/registration/pending", replace: true });
      }
    } catch (error) {
      toast.error(apiErrorMessage(error, t("loginFailed")));
    } finally {
      setLoading(false);
    }
  };

  if (isHydrating) {
    return <AuthSessionLoader />;
  }

  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      <div className="relative hidden flex-col bg-sidebar p-8 text-sidebar-foreground lg:flex xl:p-10">
        <div className="flex items-center gap-2">
          <img src="/Logo.ico" alt="Logo" className="h-10 w-10 object-contain" />
          <span className="font-semibold">SILAPPKASAL</span>
        </div>
        <div className="my-auto w-full max-w-xl space-y-6 py-8">
          <div className="space-y-4">
            <h1 className="text-3xl font-semibold leading-tight">
              {t("heroTitle")}
            </h1>
            <p className="text-sm text-sidebar-foreground/70">
              {t("heroSubtitle")}
            </p>
          </div>
          <InstitutionalSupport variant="featured" tone="dark" />
        </div>
        <div className="text-xs text-sidebar-foreground/60">
          {t("common:copyright", { defaultValue: "2026 SILAPPKASAL. Hak akses terbatas untuk Satgas PPKS." })}
        </div>
      </div>
      <div className="flex min-h-screen items-center justify-center p-4 sm:p-6">
        <div className="w-full max-w-md">
          <Card className="w-full border-border/60 shadow-sm">
            <CardContent className="p-8">
              <div className="mb-6 lg:hidden">
                <div className="flex items-center gap-2">
                  <img src="/Logo.ico" alt="Logo" className="h-10 w-10 object-contain" />
                  <span className="font-semibold">SILAPPKASAL</span>
                </div>
              </div>
              <h2 className="text-2xl font-semibold tracking-tight">{t("signIn")}</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                {t("signInDescription")}
              </p>
              <form onSubmit={onSubmit} className="mt-6 space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="identifier">{t("email")}</Label>
                  <Input
                    id="identifier"
                    type="text"
                    value={identifier}
                    onChange={(e) => setIdentifier(e.target.value)}
                    required
                  />
                  <p className="text-xs text-muted-foreground">{t("registrationLoginHint")}</p>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="password">{t("password")}</Label>
                  <PasswordInput
                    id="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    required
                  />
                </div>
                <div className="flex items-center">
                  <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                      checked={remember}
                      onCheckedChange={(v) => setRemember(v === true)}
                    />
                    {t("rememberMe")}
                  </label>
                </div>
                <Button type="submit" className="w-full" disabled={loading}>
                  {loading ? t("signingIn") : t("signIn")}
                </Button>
              </form>
              <div className="mt-6 flex flex-col gap-2 text-sm">
                <Link to="/register" className="text-primary hover:underline">
                  {t("registerLink")}
                </Link>
                <Link to="/track" className="text-primary hover:underline">
                  {t("trackLink")}
                </Link>
              </div>
            </CardContent>
          </Card>
          <div className="mt-6 lg:hidden">
            <InstitutionalSupport variant="featured" tone="light" />
          </div>
        </div>
      </div>
    </div>
  );
}
