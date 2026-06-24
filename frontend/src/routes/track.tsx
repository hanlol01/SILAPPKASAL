import { createFileRoute, Link } from "@tanstack/react-router";
import { useMutation } from "@tanstack/react-query";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import { Search } from "lucide-react";

import { ApiError } from "@/lib/api-client";
import { trackReport } from "@/lib/portal-api";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

export const Route = createFileRoute("/track")({
  component: TrackPage,
  head: () => ({
    meta: [
      { title: "Lacak Laporan - SILAPPKASAL" },
      { name: "description", content: "Lacak status aman laporan anonim." },
    ],
  }),
});

function TrackPage() {
  const { t } = useTranslation(["portal", "auth"]);
  const [trackingCode, setTrackingCode] = useState("");
  const [error, setError] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: () => trackReport(trackingCode.trim()),
    onMutate: () => setError(null),
    onError: (err) => {
      setError(err instanceof ApiError ? err.message : t("portal:trackingError"));
    },
  });

  const result = mutation.data;

  return (
    <div className="min-h-screen bg-muted/30 px-4 py-8">
      <div className="mx-auto mb-8 flex max-w-xl items-center justify-between">
        <Link to="/login" className="flex items-center gap-2 font-semibold">
          <img src="/Logo.ico" alt="Logo" className="h-8 w-8" />
          SILAPPKASAL
        </Link>
        <Button asChild variant="ghost" size="sm">
          <Link to="/register">{t("auth:registerShort")}</Link>
        </Button>
      </div>
      <Card className="mx-auto max-w-xl">
        <CardHeader>
          <CardTitle>{t("portal:trackTitle")}</CardTitle>
          <p className="text-sm text-muted-foreground">{t("portal:trackSubtitle")}</p>
        </CardHeader>
        <CardContent className="space-y-5">
          <form
            className="space-y-3"
            onSubmit={(event) => {
              event.preventDefault();
              mutation.mutate();
            }}
          >
            <div className="space-y-2">
              <Label htmlFor="tracking_code">{t("portal:trackingCode")}</Label>
              <Input
                id="tracking_code"
                value={trackingCode}
                onChange={(e) => setTrackingCode(e.target.value.toUpperCase())}
                placeholder="XXXX-XXXX-XXXX-XXXX"
                required
              />
            </div>
            <Button type="submit" className="w-full gap-2" disabled={mutation.isPending}>
              <Search className="h-4 w-4" />
              {mutation.isPending ? t("portal:trackingLoading") : t("portal:trackSubmit")}
            </Button>
          </form>

          {error && <div className="rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">{error}</div>}

          {result && (
            <div className="rounded-md border bg-background p-4">
              <dl className="grid gap-3 text-sm">
                <div className="flex justify-between gap-4">
                  <dt className="text-muted-foreground">{t("portal:registrationNumber")}</dt>
                  <dd className="font-medium">{result.registration_number}</dd>
                </div>
                <div className="flex justify-between gap-4">
                  <dt className="text-muted-foreground">{t("portal:status")}</dt>
                  <dd className="font-medium">{result.status}</dd>
                </div>
                <div className="flex justify-between gap-4">
                  <dt className="text-muted-foreground">{t("portal:submitted")}</dt>
                  <dd className="font-medium">{result.submitted_at ? new Date(result.submitted_at).toLocaleString() : "-"}</dd>
                </div>
              </dl>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
