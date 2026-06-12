import { useRouterState, useNavigate } from "@tanstack/react-router";
import {
  LayoutDashboard,
  FileText,
  Bell,
  UserCog,
  ShieldCheck,
  LogOut,
  Moon,
  Sun,
} from "lucide-react";
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
import { useAuth } from "@/hooks/use-auth";
import { useTheme } from "@/hooks/use-theme";
import type { ReactNode } from "react";

/**
 * Portal navigation items.
 *
 * Note: child route files (portal.reports.index.tsx etc.) will be created in
 * later phases. Until then we use `navigate()` instead of `<Link>` to avoid
 * TanStack Router's strict typed-path check.
 */
const nav = [
  { title: "Overview", url: "/portal", icon: LayoutDashboard },
  { title: "My Reports", url: "/portal/reports", icon: FileText },
  { title: "Notifications", url: "/portal/notifications", icon: Bell },
  { title: "Account", url: "/portal/account", icon: UserCog },
];

function PortalNav() {
  const path = useRouterState({ select: (s) => s.location.pathname });
  const navigate = useNavigate();

  return (
    <nav className="flex items-center gap-1 overflow-x-auto">
      {nav.map((item) => {
        const active =
          item.url === "/portal"
            ? path === "/portal"
            : path.startsWith(item.url);
        return (
          <Button
            key={item.url}
            variant={active ? "secondary" : "ghost"}
            size="sm"
            className="shrink-0 gap-2"
            onClick={() => navigate({ to: item.url as "/" })}
          >
            <item.icon className="h-4 w-4" />
            <span className="hidden sm:inline">{item.title}</span>
          </Button>
        );
      })}
    </nav>
  );
}

function PortalTopbar() {
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
        {/* Brand */}
        <button
          type="button"
          onClick={() => navigate({ to: "/portal" })}
          className="flex shrink-0 items-center gap-2"
        >
          <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
            <ShieldCheck className="h-4 w-4" />
          </div>
          <span className="hidden text-sm font-semibold md:inline">
            SafeCampus
          </span>
        </button>

        {/* Horizontal nav */}
        <PortalNav />

        {/* Right side */}
        <div className="ml-auto flex items-center gap-2">
          <Button
            variant="ghost"
            size="icon"
            onClick={toggle}
            aria-label="Toggle theme"
          >
            {theme === "dark" ? (
              <Sun className="h-4 w-4" />
            ) : (
              <Moon className="h-4 w-4" />
            )}
          </Button>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" className="gap-2 px-2">
                <Avatar className="h-7 w-7">
                  <AvatarFallback className="bg-primary text-primary-foreground text-xs">
                    {initials}
                  </AvatarFallback>
                </Avatar>
                <span className="hidden text-sm font-medium md:inline">
                  {user?.name}
                </span>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
              <DropdownMenuLabel>{user?.email}</DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem
                onClick={() => navigate({ to: "/portal/account" as "/" })}
              >
                <UserCog className="mr-2 h-4 w-4" /> Account
              </DropdownMenuItem>
              <DropdownMenuItem
                onClick={async () => {
                  await logout();
                  navigate({ to: "/login" });
                }}
              >
                <LogOut className="mr-2 h-4 w-4" /> Sign out
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
