import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { Bell, Inbox } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { QueryErrorState } from "@/components/query-state";
import { PortalNotificationItem } from "@/components/portal/portal-notification-item";
import { portalQueryKeys, getPortalNotifications } from "@/lib/portal-api";
import { useAuth } from "@/hooks/use-auth";
import { hasPortalAccess } from "@/lib/auth-roles";
import { useTranslation } from "react-i18next";

export const Route = createFileRoute("/portal/notifications")({
  component: PortalNotificationsPage,
  head: () => ({
    meta: [
      { title: "Notifications â€” SILAPPKASAL Portal" },
      {
        name: "description",
        content: "View notifications about your report status updates.",
      },
    ],
  }),
});

function PortalNotificationsPage() {
  const { t } = useTranslation(["portal"]);
  const { roleCode } = useAuth();

  const notificationsQuery = useQuery({
    queryKey: portalQueryKeys.notifications(),
    queryFn: getPortalNotifications,
    enabled: hasPortalAccess(roleCode),
  });

  const notifications = notificationsQuery.data ?? [];
  const unreadCount = notifications.filter((n) => n.read_at === null).length;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">
          {t("notifications")}
        </h1>
        <p className="text-sm text-muted-foreground">
          {notificationsQuery.isSuccess
            ? unreadCount > 0
              ? t("unreadNotifications", { count: unreadCount })
              : t("allCaughtUp")
            : t("notificationsSubtitle")}
        </p>
      </div>

      {/* Loading */}
      {notificationsQuery.isLoading && (
        <div className="space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <Card key={i}>
              <CardContent className="flex gap-3 p-4">
                <Skeleton className="h-9 w-9 rounded-lg" />
                <div className="flex-1 space-y-2">
                  <Skeleton className="h-4 w-48" />
                  <Skeleton className="h-3 w-72" />
                  <Skeleton className="h-3 w-32" />
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Error */}
      {notificationsQuery.isError && (
        <QueryErrorState
          message={t("notificationsLoadError")}
          onRetry={() => notificationsQuery.refetch()}
        />
      )}

      {/* Success */}
      {notificationsQuery.isSuccess && (
        <>
          {notifications.length === 0 ? (
            <Card>
              <CardContent className="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <Inbox className="h-8 w-8 text-muted-foreground/50" />
                <div>
                  <p className="text-sm font-medium">{t("noNotifications")}</p>
                  <p className="text-sm text-muted-foreground">
                    {t("noNotificationsDesc")}
                  </p>
                </div>
              </CardContent>
            </Card>
          ) : (
            <div className="space-y-3">
              {notifications.map((notification) => (
                <PortalNotificationItem
                  key={notification.id}
                  notification={notification}
                />
              ))}
            </div>
          )}
        </>
      )}
    </div>
  );
}
