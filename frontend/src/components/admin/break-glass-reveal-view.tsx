import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import type { BreakGlassReveal } from "@/lib/break-glass-types";

interface BreakGlassRevealViewProps {
  reveal: BreakGlassReveal;
}

export function BreakGlassRevealView({ reveal }: BreakGlassRevealViewProps) {
  const validUntil = reveal.valid_until ?? reveal.expires_at ?? null;

  return (
    <Card className="border-primary/30">
      <CardHeader>
        <CardTitle className="text-base">Revealed identity</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <Alert className="border-amber-200 bg-amber-50 text-amber-950">
          <AlertDescription>This identity access is logged and audited.</AlertDescription>
        </Alert>
        <div className="grid gap-3 text-sm sm:grid-cols-2">
          <Field label="Name">{reveal.name ?? "Unavailable"}</Field>
          <Field label="Email">{reveal.email ?? "Unavailable"}</Field>
          {validUntil && (
            <Field label="Valid until">{formatDateTime(validUntil)}</Field>
          )}
        </div>
      </CardContent>
    </Card>
  );
}

function formatDateTime(value: string) {
  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
      <div className="mt-1 font-medium">{children}</div>
    </div>
  );
}
