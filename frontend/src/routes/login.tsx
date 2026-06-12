import { createFileRoute, useNavigate, Link } from "@tanstack/react-router";
import { useState, useEffect } from "react";
import { useTranslation } from "react-i18next";
import { ShieldCheck } from "lucide-react";
import { toast } from "sonner";
import { ApiError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import { Card, CardContent } from "@/components/ui/card";
import { useAuth } from "@/hooks/use-auth";
import { hasPortalAccess } from "@/lib/auth-roles";

function homeForRole(roleCode: string | null): string {
  return hasPortalAccess(roleCode as import("@/lib/api-types").RoleCode | null)
    ? "/portal"
    : "/dashboard";
}

export const Route = createFileRoute("/login")({
  component: LoginPage,
  head: () => ({
    meta: [
      { title: "Sign in - SafeCampus Admin" },
      { name: "description", content: "Sign in to the SafeCampus PPKS administrator console." },
    ],
  }),
});

function LoginPage() {
  const { t } = useTranslation(["auth"]);
  const { login, user } = useAuth();
  const navigate = useNavigate();
  const [identifier, setIdentifier] = useState("");
  const [password, setPassword] = useState("");
  const [remember, setRemember] = useState(true);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (user) navigate({ to: homeForRole(user.role?.code ?? null) as "/" });
  }, [user, navigate]);

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      await login(identifier, password, remember);
      toast.success(t("welcomeBack"));
    } catch (error) {
      const message = error instanceof ApiError ? error.message : t("loginFailed");
      toast.error(message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      <div className="relative hidden flex-col justify-between bg-sidebar p-10 text-sidebar-foreground lg:flex">
        <div className="flex items-center gap-2">
          <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
            <ShieldCheck className="h-5 w-5" />
          </div>
          <span className="font-semibold">SafeCampus Admin</span>
        </div>
        <div className="space-y-4">
          <h1 className="text-3xl font-semibold leading-tight">
            {t("heroTitle")}
          </h1>
          <p className="text-sm text-sidebar-foreground/70">
            {t("heroSubtitle")}
          </p>
        </div>
        <div className="text-xs text-sidebar-foreground/60">
          2026 SafeCampus - Confidential prototype
        </div>
      </div>
      <div className="flex items-center justify-center p-6">
        <Card className="w-full max-w-md border-border/60 shadow-sm">
          <CardContent className="p-8">
            <div className="mb-6 lg:hidden">
              <div className="flex items-center gap-2">
                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                  <ShieldCheck className="h-5 w-5" />
                </div>
                <span className="font-semibold">SafeCampus Admin</span>
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
              </div>
              <div className="space-y-2">
                <Label htmlFor="password">{t("password")}</Label>
                <Input
                  id="password"
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                />
              </div>
              <div className="flex items-center justify-between">
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={remember}
                    onCheckedChange={(v) => setRemember(v === true)}
                  />
                  {t("rememberMe")}
                </label>
                <Link to="/login" className="text-sm text-primary hover:underline">
                  {t("forgotPassword")}
                </Link>
              </div>
              <Button type="submit" className="w-full" disabled={loading}>
                {loading ? t("signingIn") : t("signIn")}
              </Button>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
