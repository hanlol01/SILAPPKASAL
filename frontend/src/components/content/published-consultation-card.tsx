import {
  CalendarCheck,
  Clock3,
  ExternalLink,
  Mail,
  MapPin,
  MessageCircle,
  Phone,
  ShieldCheck,
} from "lucide-react";
import { useTranslation } from "react-i18next";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import type { PublishedConsultation } from "@/lib/published-content-api";
import {
  normalizeConsultationPhone,
  normalizeConsultationWhatsApp,
  safeConsultationEmail,
  safeConsultationHttpsUrl,
} from "@/lib/consultation-actions";

export function PublishedConsultationCard({ item }: { item: PublishedConsultation }) {
  const { t, i18n } = useTranslation("informationCenter");
  const email = safeConsultationEmail(item.email);
  const phone = normalizeConsultationPhone(item.phone);
  const whatsapp = normalizeConsultationWhatsApp(item.whatsapp);
  const appointment = safeConsultationHttpsUrl(item.appointment_url);
  const verifiedDate = item.verification_date
    ? new Intl.DateTimeFormat(i18n.language, { dateStyle: "medium" }).format(
        new Date(item.verification_date),
      )
    : null;

  function confirmContact(message: string) {
    return typeof window !== "undefined" && window.confirm(message);
  }

  return (
    <Card className="h-full min-w-0 overflow-hidden rounded-2xl">
      <CardHeader className="space-y-3">
        <div className="flex items-start justify-between gap-3">
          <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
            <ShieldCheck className="h-5 w-5" aria-hidden="true" />
          </span>
          <Badge variant={item.emergency_available ? "destructive" : "secondary"}>
            {item.emergency_available ? t("consultation.emergency") : t("consultation.standard")}
          </Badge>
        </div>
        <h3 className="text-xl font-semibold leading-snug tracking-tight">{item.service_name}</h3>
        {item.service_type && <Badge variant="outline" className="w-fit">{item.service_type}</Badge>}
        {item.description && (
          <p className="text-sm leading-6 text-muted-foreground">{item.description}</p>
        )}
      </CardHeader>
      <CardContent className="space-y-4">
        <dl className="space-y-3 text-sm">
          {item.operating_hours && (
            <InfoRow icon={Clock3} label={t("consultation.hours")} value={item.operating_hours} />
          )}
          {item.office_address && (
            <InfoRow icon={MapPin} label={t("consultation.address")} value={item.office_address} />
          )}
          {email && <InfoRow icon={Mail} label={t("consultation.email")} value={email} href={`mailto:${email}`} />}
          {item.phone && (
            <InfoRow icon={Phone} label={t("consultation.phone")} value={item.phone} />
          )}
          {item.whatsapp && (
            <InfoRow
              icon={MessageCircle}
              label={t("consultation.whatsapp")}
              value={item.whatsapp}
              href={whatsapp ? `https://wa.me/${whatsapp}` : undefined}
              external
            />
          )}
          {verifiedDate && (
            <InfoRow icon={CalendarCheck} label={t("consultation.verified")} value={verifiedDate} />
          )}
        </dl>

        {item.procedure && <div className="rounded-xl bg-muted/50 p-4"><h4 className="font-medium">{t("consultation.procedure")}</h4><p className="mt-1 whitespace-pre-line text-sm leading-6 text-muted-foreground">{item.procedure}</p></div>}
        {item.confidentiality_info && <div className="rounded-xl border border-primary/20 bg-primary/5 p-4"><h4 className="font-medium">{t("consultation.confidentiality")}</h4><p className="mt-1 whitespace-pre-line text-sm leading-6 text-muted-foreground">{item.confidentiality_info}</p></div>}

        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
          {email && (
            <Button asChild variant="outline" className="min-h-11 justify-start">
              <a href={`mailto:${email}`}>
                <Mail className="h-4 w-4" aria-hidden="true" />
                {t("consultation.email")}
              </a>
            </Button>
          )}
          {phone && (
            <Button asChild variant="outline" className="min-h-11 justify-start">
              <a
                href={`tel:${phone}`}
                onClick={(event) => {
                  if (
                    !confirmContact(t("consultation.confirmPhone", { service: item.service_name }))
                  )
                    event.preventDefault();
                }}
              >
                <Phone className="h-4 w-4" aria-hidden="true" />
                {t("consultation.phone")}
              </a>
            </Button>
          )}
          {whatsapp && (
            <Button asChild variant="outline" className="min-h-11 justify-start">
              <a
                href={`https://wa.me/${whatsapp}`}
                target="_blank"
                rel="noreferrer noopener"
                onClick={(event) => {
                  if (
                    !confirmContact(
                      t("consultation.confirmWhatsapp", { service: item.service_name }),
                    )
                  )
                    event.preventDefault();
                }}
              >
                <MessageCircle className="h-4 w-4" aria-hidden="true" />
                {t("consultation.whatsapp")}
              </a>
            </Button>
          )}
          {appointment && (
            <Button asChild className="min-h-11 justify-start">
              <a href={appointment} target="_blank" rel="noreferrer noopener">
                <ExternalLink className="h-4 w-4" aria-hidden="true" />
                {item.action_label || t("consultation.appointment")}
              </a>
            </Button>
          )}
        </div>
        {item.emergency_available && (
          <p className="rounded-xl bg-destructive/10 p-3 text-sm text-destructive" role="note">
            {t("consultation.emergencyNotice")}
          </p>
        )}
      </CardContent>
    </Card>
  );
}

function InfoRow({
  icon: Icon,
  label,
  value,
  href,
  external = false,
}: {
  icon: typeof Clock3;
  label: string;
  value: string;
  href?: string;
  external?: boolean;
}) {
  return (
    <div className="flex items-start gap-3">
      <Icon className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
      <div className="min-w-0">
        <dt className="font-medium">{label}</dt>
        <dd className="break-words text-muted-foreground">
          {href ? <a className="underline decoration-muted-foreground/50 underline-offset-4 hover:text-foreground" href={href} target={external ? "_blank" : undefined} rel={external ? "noreferrer noopener" : undefined}>{value}</a> : value}
        </dd>
      </div>
    </div>
  );
}
