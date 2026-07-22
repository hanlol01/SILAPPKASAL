import { Link, useRouterState, useNavigate } from "@tanstack/react-router";
import {
  LayoutDashboard,
  FileText,
  PlusCircle,
  Bell,
  UserCog,
  LogOut,
  Menu,
  Moon,
  Sun,
  Library,
} from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from "@/components/ui/sheet";
import { useAuth } from "@/hooks/use-auth";
import { useTheme } from "@/hooks/use-theme";
import { useTranslation } from "react-i18next";
import { LanguageSwitcher } from "@/components/language-switcher";
import { canReadPublishedContent } from "@/lib/published-content-access";
import type { ReactNode } from "react";

/**
 * Portal navigation items — all child routes are now registered.
 */
const nav = [
  { titleKey: "overview", url: "/portal" as const, icon: LayoutDashboard },
  { titleKey: "newReport", url: "/portal/reports/new" as const, icon: PlusCircle },
  { titleKey: "myReports", url: "/portal/reports" as const, icon: FileText },
  {
    titleKey: "informationCenter",
    url: "/portal/information-center" as const,
    icon: Library,
    requiresPublishedContent: true,
  },
  { titleKey: "notifications", url: "/portal/notifications" as const, icon: Bell },
  { titleKey: "account", url: "/portal/account" as const, icon: UserCog },
];

function PortalNav() {
  const { t } = useTranslation(["portal"]);
  const { user } = useAuth();
  const path = useRouterState({ select: (s) => s.location.pathname });
  const visibleNav = nav.filter(
    (item) =>
      !("requiresPublishedContent" in item && item.requiresPublishedContent) ||
      canReadPublishedContent(user),
  );

  return (
    <nav className="flex min-w-0 items-center gap-1 overflow-x-auto">
      {visibleNav.map((item) => {
        const active = item.url === "/portal" ? path === "/portal" : path.startsWith(item.url);
        return (
          <Button
            key={item.url}
            asChild
            variant={active ? "secondary" : "ghost"}
            size="sm"
            className="h-auto shrink-0 gap-1.5 px-2 py-1.5 sm:gap-2"
            aria-label={t(item.titleKey)}
          >
            <Link to={item.url}>
              <item.icon className="h-4 w-4 shrink-0" />
              <span className="text-xs sm:text-sm">{t(item.titleKey)}</span>
            </Link>
          </Button>
        );
      })}
    </nav>
  );
}

function PortalMobileNav() {
  const { t } = useTranslation(["portal"]);
  const { user } = useAuth();
  const path = useRouterState({ select: (s) => s.location.pathname });
  const [open, setOpen] = useState(false);
  const visibleNav = nav.filter(
    (item) =>
      !("requiresPublishedContent" in item && item.requiresPublishedContent) ||
      canReadPublishedContent(user),
  );

  return (
    <Sheet open={open} onOpenChange={setOpen}>
      <SheetTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          className="h-11 w-11 md:hidden"
          aria-label={t("portal:openNavigation")}
        >
          <Menu className="h-5 w-5" />
        </Button>
      </SheetTrigger>
      <SheetContent side="left" className="w-[82vw] max-w-xs p-0">
        <SheetHeader className="border-b p-4 text-left">
          <SheetTitle className="flex items-center gap-2 text-base">
            <span className="flex h-8 w-8 items-center justify-center overflow-hidden rounded-lg bg-primary/10">
              <img src="/Logo.ico" alt="" aria-hidden="true" className="h-6 w-6 object-contain" />
            </span>
            SILAPPKASAL
          </SheetTitle>
        </SheetHeader>
        <nav className="grid gap-1 p-3">
          {visibleNav.map((item) => {
            const active = item.url === "/portal" ? path === "/portal" : path.startsWith(item.url);
            return (
              <Button
                key={item.url}
                asChild
                variant={active ? "secondary" : "ghost"}
                className="h-11 justify-start gap-3 px-3"
                aria-label={t(item.titleKey)}
                onClick={() => setOpen(false)}
              >
                <Link to={item.url}>
                  <item.icon className="h-4 w-4 shrink-0" />
                  <span>{t(item.titleKey)}</span>
                </Link>
              </Button>
            );
          })}
        </nav>
      </SheetContent>
    </Sheet>
  );
}

function PortalTopbar() {
  const { t } = useTranslation(["common", "portal"]);
  const { user, logout } = useAuth();
  const { theme, toggle } = useTheme();
  const navigate = useNavigate();
  const initials =
    user?.name
      .split(" ")
      .map((part) => part[0])
      .slice(0, 2)
      .join("") || "U";

  return (
    <header className="sticky top-0 z-30 border-b bg-background/80 backdrop-blur">
      <div className="flex h-14 items-center gap-3 px-4">
        <PortalMobileNav />

        {/* Brand */}
        <Link to="/portal" className="flex min-w-0 shrink-0 items-center gap-2">
          <div className="flex h-8 w-8 items-center justify-center overflow-hidden rounded-lg bg-primary/10">
            <img src="/Logo.ico" alt="" aria-hidden="true" className="h-6 w-6 object-contain" />
          </div>
          <span className="truncate text-sm font-semibold">SILAPPKASAL</span>
        </Link>

        <div className="hidden min-w-0 md:block">
          <PortalNav />
        </div>

        {/* Right side */}
        <div className="ml-auto flex items-center gap-2">
          <LanguageSwitcher />
          <Button variant="ghost" size="icon" className="h-11 w-11" onClick={toggle} aria-label={t("toggleTheme")}>
            {theme === "dark" ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
          </Button>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" className="min-h-11 gap-2 px-2" aria-label={t("common:userMenu")}>
                <Avatar className="h-7 w-7">
                  <AvatarFallback className="bg-primary text-primary-foreground text-xs">
                    {initials}
                  </AvatarFallback>
                </Avatar>
                <span className="hidden text-sm font-medium md:inline">{user?.name}</span>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
              <DropdownMenuLabel>{user?.email}</DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem asChild>
                <Link to="/portal/account">
                  <UserCog className="mr-2 h-4 w-4" /> {t("portal:account")}
                </Link>
              </DropdownMenuItem>
              <DropdownMenuItem
                onClick={async () => {
                  await logout();
                  navigate({ to: "/login" });
                }}
              >
                <LogOut className="mr-2 h-4 w-4" /> {t("signOut")}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </header>
  );
}

export function PortalLayout({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-screen w-full flex-col bg-muted/30">
      <PortalTopbar />
      <main className="flex-1 p-4 md:p-6">{children}</main>
    </div>
  );
}
