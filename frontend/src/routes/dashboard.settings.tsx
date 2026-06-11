import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { useTheme } from "@/hooks/use-theme";
import { getLocalStorageItem, setLocalStorageItem } from "@/lib/auth-storage";
import { toast } from "sonner";

export const Route = createFileRoute("/dashboard/settings")({
  component: SettingsPage,
  head: () => ({ meta: [{ title: "Settings — SafeCampus Admin" }] }),
});

const KEY = "safecampus_settings";

interface Settings {
  campusName: string;
  campusTagline: string;
  notifyEmail: boolean;
  notifyInApp: boolean;
  notifyDigest: boolean;
}

const DEFAULTS: Settings = {
  campusName: "Universitas Indonesia",
  campusTagline: "Veritas, Probitas, Justitia",
  notifyEmail: true,
  notifyInApp: true,
  notifyDigest: false,
};

function SettingsPage() {
  const { theme, toggle } = useTheme();
  const [s, setS] = useState<Settings>(DEFAULTS);

  useEffect(() => {
    const raw = getLocalStorageItem(KEY);
    if (raw) setS({ ...DEFAULTS, ...JSON.parse(raw) });
  }, []);

  const save = () => {
    setLocalStorageItem(KEY, JSON.stringify(s));
    toast.success("Settings saved");
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Settings</h1>
        <p className="text-sm text-muted-foreground">Customize your campus profile, theme, and notifications.</p>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Campus profile</CardTitle>
            <CardDescription>How your institution is presented across the platform.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-2">
              <Label>Campus name</Label>
              <Input value={s.campusName} onChange={(e) => setS({ ...s, campusName: e.target.value })} />
            </div>
            <div className="grid gap-2">
              <Label>Tagline / motto</Label>
              <Textarea rows={2} value={s.campusTagline} onChange={(e) => setS({ ...s, campusTagline: e.target.value })} />
            </div>
            <div className="grid gap-2">
              <Label>Brand logo</Label>
              <div className="flex items-center gap-3 rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                Drop an SVG or PNG here (UI-only)
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Appearance</CardTitle>
            <CardDescription>Theme and density preferences.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between rounded-lg border p-4">
              <div>
                <div className="font-medium">Dark mode</div>
                <div className="text-xs text-muted-foreground">Easier on the eyes for late-night reviews.</div>
              </div>
              <Switch checked={theme === "dark"} onCheckedChange={toggle} />
            </div>
            <div className="flex items-center justify-between rounded-lg border p-4">
              <div>
                <div className="font-medium">High-contrast tables</div>
                <div className="text-xs text-muted-foreground">Improves readability on dense data views.</div>
              </div>
              <Switch defaultChecked />
            </div>
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Notifications</CardTitle>
            <CardDescription>Choose how you want to be alerted.</CardDescription>
          </CardHeader>
          <CardContent className="grid gap-3 sm:grid-cols-3">
            {[
              { k: "notifyEmail" as const, label: "Email alerts", desc: "New reports and status changes" },
              { k: "notifyInApp" as const, label: "In-app", desc: "Realtime banner & inbox" },
              { k: "notifyDigest" as const, label: "Weekly digest", desc: "Summary every Monday" },
            ].map((row) => (
              <div key={row.k} className="flex items-center justify-between rounded-lg border p-4">
                <div>
                  <div className="font-medium">{row.label}</div>
                  <div className="text-xs text-muted-foreground">{row.desc}</div>
                </div>
                <Switch checked={s[row.k]} onCheckedChange={(v) => setS({ ...s, [row.k]: Boolean(v) })} />
              </div>
            ))}
          </CardContent>
        </Card>
      </div>

      <div className="flex justify-end">
        <Button onClick={save}>Save changes</Button>
      </div>
    </div>
  );
}
