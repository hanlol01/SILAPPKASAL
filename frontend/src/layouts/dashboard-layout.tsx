import { Link, useRouterState, useNavigate } from "@tanstack/react-router";
import {
  LayoutDashboard,
  FileText,
  FolderKanban,
  GitBranch,
  BarChart3,
  Settings,
  ShieldAlert,
  Users,
  ClipboardList,
  LogOut,
  Moon,
  Sun,
  Database,
  ScrollText,
  LibraryBig,
  BookOpenCheck,
  Library,
  FileCheck2,
} from "lucide-react";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  SidebarTrigger,
  useSidebar,
} from "@/components/ui/sidebar";
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
import { InstitutionalSupport } from "@/components/ui/institutional-support";
import { LanguageSwitcher } from "@/components/language-switcher";
import { useAuth } from "@/hooks/use-auth";
import { useTheme } from "@/hooks/use-theme";
import { formatRoleLabel } from "@/lib/format-labels";
import { getSessionStorageItem, setSessionStorageItem } from "@/lib/auth-storage";
import { type ReactNode, useCallback, useEffect, useLayoutEffect, useRef, useState } from "react";
import type { RoleCode } from "@/lib/api-types";
import { useTranslation } from "react-i18next";

const nav: {
  key: string;
  url: string;
  icon: React.ComponentType<{ className?: string }>;
  roles: RoleCode[];
  permission?: string;
}[] = [
  {
    key: "overview",
    url: "/dashboard",
    icon: LayoutDashboard,
    roles: ["super_admin", "admin", "satgas_ppks"],
  },
  {
    key: "reports",
    url: "/dashboard/reports",
    icon: FileText,
    roles: ["super_admin", "admin"],
  },
  {
    key: "reportWithdrawals",
    url: "/dashboard/report-withdrawals",
    icon: FileCheck2,
    roles: ["admin"],
    permission: "reports.withdraw.review.own_campus",
  },
  {
    key: "reportWithdrawals",
    url: "/dashboard/report-withdrawals",
    icon: FileCheck2,
    roles: ["super_admin"],
    permission: "reports.read.all",
  },
  {
    key: "cases",
    url: "/dashboard/cases",
    icon: FolderKanban,
    roles: ["admin", "satgas_ppks"],
  },
  {
    key: "workflow",
    url: "/dashboard/workflow",
    icon: GitBranch,
    roles: ["super_admin", "admin", "satgas_ppks"],
  },
  {
    key: "analytics",
    url: "/dashboard/analytics",
    icon: BarChart3,
    roles: ["super_admin", "admin"],
  },
  {
    key: "registrations",
    url: "/dashboard/registrations",
    icon: ClipboardList,
    roles: ["super_admin", "admin"],
  },
  {
    key: "users",
    url: "/dashboard/users",
    icon: Users,
    roles: ["super_admin", "admin"],
  },
  {
    key: "staff",
    url: "/dashboard/staff",
    icon: Users,
    roles: ["admin"],
    permission: "users.update",
  },
  {
    key: "masterData",
    url: "/dashboard/master-data",
    icon: Database,
    roles: ["super_admin"],
  },
  {
    key: "informationCenter",
    url: "/dashboard/information-center",
    icon: Library,
    roles: ["super_admin", "admin", "satgas_ppks", "reporter"],
    permission: "content.read.published",
  },
  {
    key: "contentManagement",
    url: "/dashboard/content",
    icon: LibraryBig,
    roles: ["admin"],
    permission: "content.read.management.own_campus",
  },
  {
    key: "contentGovernance",
    url: "/dashboard/content-governance",
    icon: BookOpenCheck,
    roles: ["super_admin"],
    permission: "content.read.management.all",
  },
  {
    key: "activityLog",
    url: "/dashboard/activity-log",
    icon: ScrollText,
    roles: ["super_admin"],
    permission: "system.audit_log.oversight",
  },
  {
    key: "breakGlass",
    url: "/dashboard/break-glass",
    icon: ShieldAlert,
    roles: ["admin"],
    permission: "privacy.approve_break_glass",
  },
  {
    key: "settings",
    url: "/dashboard/settings",
    icon: Settings,
    roles: ["super_admin", "admin", "satgas_ppks"],
  },
];

const DASHBOARD_SIDEBAR_STATE_KEY = "silappkasal_dashboard_sidebar_state";
const useBrowserLayoutEffect = typeof window === "undefined" ? useEffect : useLayoutEffect;

function AppSidebar() {
  const path = useRouterState({ select: (s) => s.location.pathname });
  const { isMobile, setOpenMobile } = useSidebar();
  const { roleCode, user } = useAuth();
  const { t } = useTranslation(["dashboard"]);
  const items = nav.filter(
    (item) =>
      roleCode &&
      item.roles.includes(roleCode) &&
      (!item.permission || user?.permissions?.includes(item.permission)),
  );
  const closeMobileSidebar = useCallback(() => {
    if (isMobile) setOpenMobile(false);
  }, [isMobile, setOpenMobile]);

  useEffect(() => {
    closeMobileSidebar();
  }, [closeMobileSidebar, path]);

  return (
    <Sidebar collapsible="icon">
      <SidebarHeader className="border-b border-sidebar-border group-data-[collapsible=icon]:p-0">
        <div className="flex min-w-0 items-center gap-2 px-2 py-2 group-data-[collapsible=icon]:h-12 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:gap-0 group-data-[collapsible=icon]:px-0">
          <div className="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-sidebar-primary/10 group-data-[collapsible=icon]:h-8 group-data-[collapsible=icon]:w-8">
            <img
              src="/Logo.ico"
              alt=""
              aria-hidden="true"
              className="h-7 w-7 object-contain group-data-[collapsible=icon]:h-6 group-data-[collapsible=icon]:w-6"
            />
          </div>
          <div className="flex flex-col leading-tight group-data-[collapsible=icon]:hidden">
            <span className="text-sm font-semibold text-sidebar-foreground">
              {t("dashboard:brand.name")}
            </span>
            <span className="text-xs text-sidebar-foreground/60">
              {t("dashboard:brand.console")}
            </span>
          </div>
        </div>
      </SidebarHeader>
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel>{t("dashboard:nav.workspace")}</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {items.map((item) => {
                const active =
                  item.url === "/dashboard" ? path === "/dashboard" : path.startsWith(item.url);
                return (
                  <SidebarMenuItem key={item.url}>
                    <SidebarMenuButton
                      asChild
                      isActive={active}
                      className="h-11 md:h-8"
                      tooltip={t(`dashboard:nav.${item.key}`)}
                    >
                      <Link to={item.url} onClick={closeMobileSidebar}>
                        <item.icon className="h-4 w-4" />
                        <span>{t(`dashboard:nav.${item.key}`)}</span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                );
              })}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
      <SidebarFooter className="border-t border-sidebar-border">
        <div className="px-2 py-2 text-[11px] text-sidebar-foreground/60 group-data-[collapsible=icon]:hidden">
          {t("dashboard:brand.prototype")}
        </div>
      </SidebarFooter>
    </Sidebar>
  );
}

function Topbar() {
  const { user, logout } = useAuth();
  const { theme, toggle } = useTheme();
  const { t } = useTranslation(["dashboard"]);
  const navigate = useNavigate();
  const initials =
    user?.name
      .split(" ")
      .map((part) => part[0])
      .slice(0, 2)
      .join("") || "AD";

  return (
    <header className="sticky top-0 z-30 flex h-14 items-center gap-3 border-b bg-background/80 px-4 backdrop-blur">
      <SidebarTrigger />
      <div className="ml-2 flex-1" aria-hidden="true" />
      <div className="ml-auto flex items-center gap-2">
        <LanguageSwitcher />
        <Button
          variant="ghost"
          size="icon"
          className="h-11 w-11"
          onClick={toggle}
          aria-label={t("dashboard:topbar.toggleTheme")}
        >
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
              <div className="hidden text-left leading-tight md:block">
                <div className="text-sm font-medium">{user?.name}</div>
                <div className="text-xs text-muted-foreground">
                  {formatRoleLabel(t, user?.role?.code ?? null)}
                </div>
              </div>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-56">
            <DropdownMenuLabel>{user?.email}</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              onClick={() =>
                navigate({
                  to: user?.role?.code === "reporter" ? "/portal/account" : "/dashboard/settings",
                })
              }
            >
              <Settings className="mr-2 h-4 w-4" /> {t("dashboard:topbar.settings")}
            </DropdownMenuItem>
            <DropdownMenuItem
              onClick={async () => {
                await logout();
                navigate({ to: "/login" });
              }}
            >
              <LogOut className="mr-2 h-4 w-4" /> {t("dashboard:topbar.signOut")}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>
  );
}

export function DashboardLayout({ children }: { children: ReactNode }) {
  const [desktopOpen, setDesktopOpen] = useState(true);
  const preferenceRestoredRef = useRef(false);

  useBrowserLayoutEffect(() => {
    const stored = getSessionStorageItem(DASHBOARD_SIDEBAR_STATE_KEY);

    if (stored === "expanded" || stored === "collapsed") {
      setDesktopOpen(stored === "expanded");
    }

    preferenceRestoredRef.current = true;
  }, []);

  const handleDesktopOpenChange = useCallback((nextOpen: boolean) => {
    setDesktopOpen(nextOpen);

    if (preferenceRestoredRef.current) {
      setSessionStorageItem(DASHBOARD_SIDEBAR_STATE_KEY, nextOpen ? "expanded" : "collapsed");
    }
  }, []);

  return (
    <SidebarProvider open={desktopOpen} onOpenChange={handleDesktopOpenChange}>
      <div className="flex min-h-screen w-full bg-muted/30">
        <AppSidebar />
        <div className="flex min-w-0 flex-1 flex-col">
          <Topbar />
          <main className="min-w-0 flex-1 p-4 md:p-6">{children}</main>
          <footer className="border-t bg-background/80 px-4 py-4 md:px-6">
            <InstitutionalSupport variant="compact" tone="auto" />
          </footer>
        </div>
      </div>
    </SidebarProvider>
  );
}
