import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import {
  Outlet,
  Link,
  createRootRouteWithContext,
  useRouter,
  useRouterState,
  HeadContent,
  Scripts,
} from "@tanstack/react-router";
import { Toaster } from "sonner";
import { useTranslation } from "react-i18next";
import { AuthProvider } from "@/components/auth-provider";
import { InstitutionalSupport } from "@/components/ui/institutional-support";

import "@/i18n";

import appCss from "../styles.css?url";

function NotFoundComponent() {
  const { t } = useTranslation(["common"]);

  return (
    <div className="flex min-h-screen items-center justify-center bg-background px-4">
      <div className="max-w-md text-center">
        <h1 className="text-7xl font-bold text-foreground">404</h1>
        <h2 className="mt-4 text-xl font-semibold">{t("common:pageNotFound")}</h2>
        <p className="mt-2 text-sm text-muted-foreground">{t("common:pageNotFoundDesc")}</p>
        <Link
          to="/login"
          className="mt-6 inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
        >
          {t("common:goHome")}
        </Link>
      </div>
    </div>
  );
}

function ErrorComponent({ error, reset }: { error: Error; reset: () => void }) {
  console.error(error);
  const router = useRouter();
  const { t } = useTranslation(["common"]);

  return (
    <div className="flex min-h-screen items-center justify-center bg-background px-4">
      <div className="max-w-md text-center">
        <h1 className="text-xl font-semibold">{t("common:pageError")}</h1>
        <p className="mt-2 text-sm text-muted-foreground">{t("common:unexpectedError")}</p>
        <button
          onClick={() => {
            router.invalidate();
            reset();
          }}
          className="mt-6 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
        >
          {t("common:tryAgain")}
        </button>
      </div>
    </div>
  );
}

export const Route = createRootRouteWithContext<{ queryClient: QueryClient }>()({
  head: () => ({
    meta: [
      { charSet: "utf-8" },
      { name: "viewport", content: "width=device-width, initial-scale=1" },
      {
        title: "SILAPPKASAL - Sistem Layanan PPKS Kampus",
      },
      {
        name: "description",
        content:
          "SILAPPKASAL: dashboard aman untuk Satgas PPKS kampus dalam mengelola pengaduan dan alur penanganan.",
      },
      {
        property: "og:title",
        content: "SILAPPKASAL - Sistem Layanan PPKS Kampus",
      },
      {
        name: "twitter:title",
        content: "SILAPPKASAL - Sistem Layanan PPKS Kampus",
      },
      {
        property: "og:description",
        content: "SILAPPKASAL adalah dashboard web untuk pengelolaan layanan PPKS kampus.",
      },
      {
        name: "twitter:description",
        content: "SILAPPKASAL adalah dashboard web untuk pengelolaan layanan PPKS kampus.",
      },
      { name: "twitter:card", content: "summary_large_image" },
      { property: "og:type", content: "website" },
      { name: "theme-color", content: "#1f365c" },
      { name: "application-name", content: "SILAPPKASAL" },
      { name: "apple-mobile-web-app-capable", content: "yes" },
      { name: "apple-mobile-web-app-status-bar-style", content: "default" },
    ],
    links: [
      { rel: "icon", type: "image/x-icon", href: "/Logo.ico" },
      { rel: "manifest", href: "/manifest.webmanifest" },
      { rel: "stylesheet", href: appCss },
    ],
  }),
  shellComponent: RootShell,
  component: RootComponent,
  notFoundComponent: NotFoundComponent,
  errorComponent: ErrorComponent,
});

function RootShell({ children }: { children: React.ReactNode }) {
  const { i18n } = useTranslation();

  return (
    <html lang={i18n.language || "id"}>
      <head>
        <HeadContent />
      </head>
      <body>
        {children}
        <Scripts />
      </body>
    </html>
  );
}

function RootComponent() {
  const { queryClient } = Route.useRouteContext();
  const pathname = useRouterState({ select: (state) => state.location.pathname });
  const shouldRenderPublicFooter =
    pathname === "/information-center" ||
    pathname.startsWith("/information-center/") ||
    pathname === "/register" ||
    pathname === "/track";

  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <Outlet />
        {shouldRenderPublicFooter ? (
          <footer className="border-t bg-background/80 px-4 py-4 md:px-6">
            <InstitutionalSupport variant="compact" tone="auto" />
          </footer>
        ) : null}
        <Toaster richColors position="top-center" />
      </AuthProvider>
    </QueryClientProvider>
  );
}
