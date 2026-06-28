import { createFileRoute, Link } from "@tanstack/react-router";
import { useMutation } from "@tanstack/react-query";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { Search } from "lucide-react";
import { z } from "zod";

import { ApiError } from "@/lib/api-client";
import { formatDateTime } from "@/lib/format";
import { trackReport } from "@/lib/portal-api";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Form } from "@/components/ui/form";
import { TextInputField } from "@/components/form-fields";

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
  const { t, i18n } = useTranslation(["portal", "auth", "common"]);
  const form = useForm<TrackingValues>({
    resolver: zodResolver(trackingSchema(t("portal:trackingInvalidFormat"))),
    defaultValues: {
      tracking_code: "",
    },
  });

  const mutation = useMutation({
    mutationFn: (values: TrackingValues) => trackReport(values.tracking_code.trim()),
    onError: (err) => {
      form.setError("tracking_code", {
        type: "server",
        message: err instanceof ApiError ? err.message : t("portal:trackingError"),
      });
      if (!(err instanceof ApiError)) {
        toast.error(t("common:unexpectedError"));
      }
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
          <Form {...form}>
            <form className="space-y-3" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
              <TextInputField
                control={form.control}
                name="tracking_code"
                label={t("portal:trackingCode")}
                placeholder="XXXX-XXXX-XXXX-XXXX"
                transformValue={(value) => value.toUpperCase()}
              />
              <Button type="submit" className="w-full gap-2" disabled={mutation.isPending}>
                <Search className="h-4 w-4" />
                {mutation.isPending ? t("portal:trackingLoading") : t("portal:trackSubmit")}
              </Button>
            </form>
          </Form>

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
                  <dd className="font-medium">{formatDateTime(result.submitted_at, i18n.language)}</dd>
                </div>
              </dl>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

const trackingCodePattern = /^[A-Z0-9-]{16,32}$/;

function trackingSchema(invalidMessage: string) {
  return z.object({
    tracking_code: z
      .string()
      .min(1, invalidMessage)
      .refine((value) => trackingCodePattern.test(value.trim().toUpperCase()), invalidMessage),
  });
}

type TrackingValues = z.infer<ReturnType<typeof trackingSchema>>;
