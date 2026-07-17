import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import type { BreakGlassReveal } from "@/lib/break-glass-types";
import { formatDateTime } from "@/lib/format";
import { useTranslation } from "react-i18next";

interface BreakGlassRevealViewProps {
  reveal: BreakGlassReveal;
}

export function BreakGlassRevealView({ reveal }: BreakGlassRevealViewProps) {
  const { t, i18n } = useTranslation(["dashboard"]);
  const validUntil = reveal.valid_until ?? reveal.expires_at ?? null;

  return (
    <Card className="border-primary/30">
      <CardHeader>
        <CardTitle className="text-base">{t("dashboard:breakGlass.reveal.title")}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <Alert className="border-amber-200 bg-amber-50 text-amber-950">
          <AlertDescription>{t("dashboard:breakGlass.reveal.auditNotice")}</AlertDescription>
        </Alert>
        <div className="grid gap-3 text-sm sm:grid-cols-2">
          <Field label={t("dashboard:breakGlass.reveal.name")}>{reveal.name ?? t("dashboard:common.notAvailable")}</Field>
          <Field label={t("dashboard:breakGlass.reveal.email")}>{reveal.email ?? t("dashboard:common.notAvailable")}</Field>
          {validUntil && (
            <Field label={t("dashboard:breakGlass.reveal.validUntil")}>
              {formatDateTime(validUntil, i18n.language)}
            </Field>
          )}
        </div>
      </CardContent>
    </Card>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
      <div className="mt-1 font-medium">{children}</div>
    </div>
  );
}
