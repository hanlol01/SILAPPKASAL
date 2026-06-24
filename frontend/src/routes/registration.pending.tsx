import { createFileRoute, Link, Navigate, useNavigate } from "@tanstack/react-router";
import { Clock } from "lucide-react";
import { useTranslation } from "react-i18next";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { useAuth } from "@/hooks/use-auth";

export const Route = createFileRoute("/registration/pending")({
  component: PendingRegistrationPage,
});

function PendingRegistrationPage() {
  const { t } = useTranslation(["auth"]);
  const { registration, logout } = useAuth();
  const navigate = useNavigate();

  if (!registration) return <Navigate to="/login" replace />;
  if (registration.status === "rejected") return <Navigate to="/registration/correction" replace />;

  return (
    <div className="min-h-screen bg-muted/30 px-4 py-8">
      <Card className="mx-auto max-w-lg">
        <CardHeader>
          <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
            <Clock className="h-6 w-6" />
          </div>
          <CardTitle>{t("pendingTitle")}</CardTitle>
          <p className="text-sm text-muted-foreground">{t("pendingBody")}</p>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="rounded-md border bg-muted/40 p-3">
            <div className="text-xs text-muted-foreground">{t("registrationNumber")}</div>
            <div className="font-mono text-sm font-medium">{registration.registration_number}</div>
          </div>
          <Button
            variant="outline"
            className="w-full"
            onClick={async () => {
              await logout();
              navigate({ to: "/login" });
            }}
          >
            {t("clearSession")}
          </Button>
          <p className="text-center text-sm text-muted-foreground">
            <Link to="/login" className="text-primary hover:underline">{t("backToLogin")}</Link>
          </p>
        </CardContent>
      </Card>
    </div>
  );
}
