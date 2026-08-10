import { useTranslation } from "react-i18next";

import { cn } from "@/lib/utils";

export type InstitutionalSupportProps = {
  variant?: "featured" | "compact";
  tone?: "light" | "dark" | "auto";
  className?: string;
};

type Institution = {
  id: "kemenag" | "lpdp" | "uniga";
  alt: string;
  href: string;
  src: string;
  width: number;
  height: number;
  imageClassName: string;
};

const INSTITUTIONS: readonly Institution[] = [
  {
    id: "kemenag",
    alt: "Kementerian Agama Republik Indonesia",
    href: "https://kemenag.go.id/",
    src: "/brand/institutional-support/kemenag.png",
    width: 708,
    height: 635,
    imageClassName: "max-h-8 max-w-12 sm:max-h-11 sm:max-w-[5.75rem]",
  },
  {
    id: "lpdp",
    alt: "Lembaga Pengelola Dana Pendidikan",
    href: "https://lpdp.kemenkeu.go.id/en/",
    src: "/brand/institutional-support/lpdp.png",
    width: 1600,
    height: 777,
    imageClassName: "max-h-7 max-w-[4.5rem] sm:max-h-10 sm:max-w-40",
  },
  {
    id: "uniga",
    alt: "Universitas Garut",
    href: "https://uniga.ac.id/",
    src: "/brand/institutional-support/uniga.png",
    width: 526,
    height: 526,
    imageClassName: "max-h-8 max-w-12 sm:max-h-11 sm:max-w-[5.5rem]",
  },
];

const LPDP_WHITE_SRC = "/brand/institutional-support/lpdp_white.png";

function LpdpLogo({ institution, tone, loading }: {
  institution: Institution;
  tone: InstitutionalSupportProps["tone"];
  loading: "eager" | "lazy";
}) {
  if (tone === "dark") {
    return (
      <img
        src={LPDP_WHITE_SRC}
        alt={institution.alt}
        width={2400}
        height={1165}
        loading={loading}
        className={cn("h-auto w-auto object-contain", institution.imageClassName)}
      />
    );
  }

  if (tone === "light") {
    return (
      <img
        src={institution.src}
        alt={institution.alt}
        width={institution.width}
        height={institution.height}
        loading={loading}
        className={cn("h-auto w-auto object-contain", institution.imageClassName)}
      />
    );
  }

  return (
    <>
      <img
        src={institution.src}
        alt={institution.alt}
        width={institution.width}
        height={institution.height}
        loading={loading}
        className={cn("h-auto w-auto object-contain dark:hidden", institution.imageClassName)}
      />
      <img
        src={LPDP_WHITE_SRC}
        alt={institution.alt}
        width={2400}
        height={1165}
        loading={loading}
        className={cn("hidden h-auto w-auto object-contain dark:block", institution.imageClassName)}
      />
    </>
  );
}

export function InstitutionalSupport({
  variant = "compact",
  tone = "auto",
  className,
}: InstitutionalSupportProps) {
  const { t } = useTranslation("common");
  const featured = variant === "featured";
  const loading = featured ? "eager" : "lazy";
  const copyClassName = tone === "dark" ? "text-sidebar-foreground" : "text-foreground";
  const mutedCopyClassName = tone === "dark" ? "text-sidebar-foreground/75" : "text-muted-foreground";
  const panelClassName = tone === "dark"
    ? "border-white/15 bg-white/5"
    : "border-border/70 bg-background/80";
  const logoSurfaceClassName = tone === "dark"
    ? "bg-transparent"
    : tone === "light"
      ? "bg-muted/40"
      : "bg-muted/40 dark:bg-transparent";

  return (
    <section
      aria-label={t("institutionalSupport.sectionLabel")}
      data-variant={variant}
      className={cn(
        "w-full",
        featured ? cn("rounded-xl border px-4 py-4 sm:px-5", panelClassName) : "",
        className,
      )}
    >
      {featured ? (
        <div className="mb-4 space-y-2">
          <p className={cn("text-[11px] font-semibold uppercase tracking-[0.16em]", mutedCopyClassName)}>
            {t("institutionalSupport.badge")}
          </p>
          <h2 className={cn("text-sm font-semibold leading-snug sm:text-base", copyClassName)}>
            {t("institutionalSupport.featuredHeading")}
          </h2>
        </div>
      ) : (
        <p className={cn("mb-3 text-xs font-medium", mutedCopyClassName)}>
          {t("institutionalSupport.compactHeading")}
        </p>
      )}

      <div className="grid grid-cols-3 divide-x divide-border/70 overflow-hidden rounded-lg">
        {INSTITUTIONS.map((institution) => (
          <a
            key={institution.id}
            href={institution.href}
            target="_blank"
            rel="noopener noreferrer"
            aria-label={institution.alt}
            className={cn(
              "flex h-14 min-w-0 items-center justify-center px-1 transition-colors hover:bg-muted/70 focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset sm:h-[4.5rem] sm:px-3",
              logoSurfaceClassName,
            )}
          >
            {institution.id === "lpdp" ? (
              <LpdpLogo institution={institution} tone={tone} loading={loading} />
            ) : (
              <img
                src={institution.src}
                alt={institution.alt}
                width={institution.width}
                height={institution.height}
                loading={loading}
                className={cn("h-auto w-auto object-contain", institution.imageClassName)}
              />
            )}
          </a>
        ))}
      </div>
    </section>
  );
}
