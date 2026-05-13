import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { Bell, FileWarning, Megaphone, Clock, Check } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { mockNotifications } from "@/mock-data";
import type { Notification } from "@/types";

export const Route = createFileRoute("/dashboard/notifications")({
  component: NotificationsPage,
  head: () => ({ meta: [{ title: "Notifications — SafeCampus Admin" }] }),
});

const KEY = "safecampus_notifications";

const ICONS: Record<Notification["type"], React.ComponentType<{ className?: string }>> = {
  report: FileWarning,
  status: Bell,
  reminder: Clock,
  article: Megaphone,
};

function NotificationsPage() {
  const [items, setItems] = useState<Notification[]>(mockNotifications);

  useEffect(() => {
    const raw = typeof window !== "undefined" ? localStorage.getItem(KEY) : null;
    if (raw) setItems(JSON.parse(raw));
  }, []);
  useEffect(() => {
    localStorage.setItem(KEY, JSON.stringify(items));
  }, [items]);

  const unread = items.filter((i) => !i.read).length;

  return (
    <div className="space-y-6">
      <div className="flex items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Notifications</h1>
          <p className="text-sm text-muted-foreground">
            {unread} unread · stored locally on this device.
          </p>
        </div>
        <Button
          variant="outline"
          onClick={() => setItems((p) => p.map((n) => ({ ...n, read: true })))}
        >
          <Check className="mr-2 h-4 w-4" /> Mark all read
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Inbox</CardTitle>
          <CardDescription>Recent system and team activity</CardDescription>
        </CardHeader>
        <CardContent>
          <ul className="divide-y">
            {items.map((n) => {
              const Icon = ICONS[n.type];
              return (
                <li key={n.id} className={`flex items-start gap-3 py-3 ${n.read ? "" : "bg-accent/20 -mx-4 px-4 rounded-md"}`}>
                  <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icon className="h-4 w-4" />
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-medium">{n.title}</span>
                      {!n.read && <Badge className="h-5 px-1.5 text-[10px]">new</Badge>}
                    </div>
                    <div className="text-sm text-muted-foreground">{n.message}</div>
                    <div className="mt-1 text-xs text-muted-foreground">
                      {new Date(n.date).toLocaleString()}
                    </div>
                  </div>
                  {!n.read && (
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() => setItems((p) => p.map((x) => (x.id === n.id ? { ...x, read: true } : x)))}
                    >
                      Mark read
                    </Button>
                  )}
                </li>
              );
            })}
          </ul>
        </CardContent>
      </Card>
    </div>
  );
}
