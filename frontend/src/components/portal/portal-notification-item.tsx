import { Bell, BellDot, Info, FileText, AlertTriangle } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import { formatDateTime } from "@/lib/format";
import {
  portalNotificationContent,
} from "@/lib/portal-labels";
import type { PortalNotification } from "@/lib/portal-types";
import { useTranslation } from "react-i18next";

/**
 * Picks an icon based on the notification type code.
 * Falls back to a generic Bell for unknown types.
 */
function typeIcon(type: string) {
  switch (type) {
    case "case_assigned":
    case "case_status_changed":
    case "investigation_update":
    case "recommendation_update":
    case "decision_update":
    case "recovery_update":
      return FileText;
    case "break_glass_denied":
      return AlertTriangle;
    case "privacy_notice":
    case "break_glass_approved":
      return Info;
    default:
      return Bell;
  }
}

interface PortalNotificationItemProps {
  notification: PortalNotification;
}

/**
 * A single read-only notification row.
 *
 * Visual distinction between read and unread:
 * - Unread: left accent border + slightly bolder title + BellDot icon indicator
 * - Read: standard border + muted title
 *
 * No mutation actions (mark read, dismiss, etc.) — purely display.
 */
export function PortalNotificationItem({
  notification,
}: PortalNotificationItemProps) {
  const { t, i18n } = useTranslation(["portal"]);
  const isUnread = notification.read_at === null;
  const content = portalNotificationContent(t, notification);
  const Icon = typeIcon(content.iconToken);

  return (
    <Card
      className={cn(
        "transition-colors",
        isUnread && "border-l-4 border-l-primary",
      )}
    >
      <CardContent className="flex gap-3 p-4">
        {/* Icon */}
        <div
          className={cn(
            "flex h-9 w-9 shrink-0 items-center justify-center rounded-lg",
            isUnread
              ? "bg-primary/10 text-primary"
              : "bg-muted text-muted-foreground",
          )}
        >
          <Icon className="h-4 w-4" />
        </div>

        {/* Content */}
        <div className="min-w-0 flex-1">
          <div className="flex items-start justify-between gap-2">
            <p
              className={cn(
                "text-sm",
                isUnread ? "font-medium" : "text-muted-foreground",
              )}
            >
              {content.title}
            </p>
            {isUnread && (
              <BellDot className="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary" />
            )}
          </div>
          <p className="mt-0.5 text-sm text-muted-foreground">{content.body}</p>
          <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
            <span>{content.typeLabel}</span>
            <span>{formatDateTime(notification.created_at, i18n.language)}</span>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
