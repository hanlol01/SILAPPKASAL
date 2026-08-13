import { createFileRoute } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { useTheme } from "@/hooks/use-theme";
import { getLocalStorageItem, setLocalStorageItem } from "@/lib/auth-storage";
import { PageBreadcrumb } from "@/components/page-breadcrumb";
import { apiErrorMessage } from "@/lib/form-errors";
import { getMyProfile, portalQueryKeys, updateMyProfile } from "@/lib/portal-api";
import type { ProfileStatusCode } from "@/lib/portal-types";

export const Route = createFileRoute("/dashboard/settings")({
  component: SettingsPage,
  head: () => ({ meta: [{ title: "Pengaturan - SILAPPKASAL" }] }),
});

/**
 * Current storage key for institution-level settings.
 * Renamed from "safecampus_settings" to make the namespace match the product.
 * A one-time migration on load preserves any previously saved values.
 */
const STORAGE_KEY = "silappkasal.settings.v1";
const LEGACY_STORAGE_KEY = "safecampus_settings";

interface Settings {
  campusName: string;
  campusTagline: string;
  notifyEmail: boolean;
  notifyInApp: boolean;
  notifyDigest: boolean;
}

const DEFAULTS: Settings = {
  campusName: "",
  campusTagline: "",
  notifyEmail: true,
  notifyInApp: true,
  notifyDigest: false,
};

function readStoredSettings(): Partial<Settings> | null {
  const current = getLocalStorageItem(STORAGE_KEY);
  if (current) {
    try {
      return JSON.parse(current) as Partial<Settings>;
    } catch {
      return null;
    }
  }
  const legacy = getLocalStorageItem(LEGACY_STORAGE_KEY);
  if (legacy) {
    try {
      return JSON.parse(legacy) as Partial<Settings>;
    } catch {
      return null;
    }
  }
  return null;
}

function SettingsPage() {
  const { t } = useTranslation(["dashboard", "portal", "common"]);
  const { theme, toggle } = useTheme();
  const queryClient = useQueryClient();
  const [settings, setSettings] = useState<Settings>(DEFAULTS);
  const [profileStatus, setProfileStatus] = useState<ProfileStatusCode | null>(null);
  const [profileStatusOther, setProfileStatusOther] = useState("");
  const [address, setAddress] = useState("");
  const profileQuery = useQuery({
    queryKey: portalQueryKeys.profile(),
    queryFn: getMyProfile,
  });
  const profileMutation = useMutation({
    mutationFn: () => updateMyProfile({
      profile_status: profileStatus,
      profile_status_other: profileStatus === "other" ? profileStatusOther.trim() || null : null,
      address: address.trim() || null,
    }),
    onSuccess: (profile) => {
      queryClient.setQueryData(portalQueryKeys.profile(), profile);
      toast.success(t("portal:profileUpdated"));
    },
    onError: (error) => toast.error(apiErrorMessage(error, t("portal:profileLoadError"))),
  });

  useEffect(() => {
    const stored = readStoredSettings();
    if (stored) {
      setSettings({ ...DEFAULTS, ...stored });
    }
  }, []);

  useEffect(() => {
    if (!profileQuery.data) return;

    setProfileStatus(profileQuery.data.profile_status);
    setProfileStatusOther(profileQuery.data.profile_status_other ?? "");
    setAddress(profileQuery.data.address ?? "");
  }, [profileQuery.data]);

  const save = () => {
    setLocalStorageItem(STORAGE_KEY, JSON.stringify(settings));
    toast.success(t("dashboard:settings.saveSuccess"));
  };

  const notificationRows: { key: keyof Pick<Settings, "notifyEmail" | "notifyInApp" | "notifyDigest">; label: string; description: string }[] = [
    {
      key: "notifyEmail",
      label: t("dashboard:settings.notifications.email"),
      description: t("dashboard:settings.notifications.emailDesc"),
    },
    {
      key: "notifyInApp",
      label: t("dashboard:settings.notifications.inApp"),
      description: t("dashboard:settings.notifications.inAppDesc"),
    },
    {
      key: "notifyDigest",
      label: t("dashboard:settings.notifications.weeklyDigest"),
      description: t("dashboard:settings.notifications.weeklyDigestDesc"),
    },
  ];

  return (
    <div className="space-y-6">
      <PageBreadcrumb crumbs={[{ label: t("dashboard:nav.settings") }]} />
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t("dashboard:settings.title")}</h1>
        <p className="text-sm text-muted-foreground">{t("dashboard:settings.subtitle")}</p>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>{t("portal:profile")}</CardTitle>
            <CardDescription>{t("portal:profileDesc")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {profileQuery.isError ? (
              <p role="alert" className="text-sm text-destructive">{t("portal:profileLoadError")}</p>
            ) : (
              <>
                <div className="grid gap-2">
                  <Label htmlFor="settings-profile-status">{t("portal:profileStatus")}</Label>
                  <Select
                    value={profileStatus ?? "unset"}
                    onValueChange={(value) => {
                      const nextStatus = value === "unset" ? null : value as ProfileStatusCode;
                      setProfileStatus(nextStatus);
                      if (nextStatus !== "other") setProfileStatusOther("");
                    }}
                    disabled={profileQuery.isPending || profileMutation.isPending}
                  >
                    <SelectTrigger id="settings-profile-status"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="unset">{t("portal:optional")}</SelectItem>
                      <SelectItem value="student">{t("portal:profileStatuses.student")}</SelectItem>
                      <SelectItem value="lecturer">{t("portal:profileStatuses.lecturer")}</SelectItem>
                      <SelectItem value="education_staff">{t("portal:profileStatuses.educationStaff")}</SelectItem>
                      <SelectItem value="employee">{t("portal:profileStatuses.employee")}</SelectItem>
                      <SelectItem value="other">{t("portal:profileStatuses.other")}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                {profileStatus === "other" && (
                  <div className="grid gap-2">
                    <Label htmlFor="settings-profile-status-other">{t("portal:profileStatusOther")}</Label>
                    <Input
                      id="settings-profile-status-other"
                      value={profileStatusOther}
                      maxLength={100}
                      onChange={(event) => setProfileStatusOther(event.target.value)}
                      disabled={profileMutation.isPending}
                    />
                  </div>
                )}
                <div className="grid gap-2">
                  <Label htmlFor="settings-profile-address">{t("portal:shortAddress")}</Label>
                  <Textarea
                    id="settings-profile-address"
                    rows={3}
                    maxLength={160}
                    value={address}
                    placeholder={t("portal:shortAddressHint")}
                    onChange={(event) => setAddress(event.target.value)}
                    disabled={profileQuery.isPending || profileMutation.isPending}
                  />
                  <p className="text-xs text-muted-foreground">{t("portal:shortAddressHelp")}</p>
                </div>
                <Button
                  type="button"
                  className="w-full sm:w-auto"
                  disabled={profileQuery.isPending || profileMutation.isPending}
                  onClick={() => profileMutation.mutate()}
                >
                  {t("dashboard:settings.saveChanges")}
                </Button>
              </>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("dashboard:settings.campusProfile.title")}</CardTitle>
            <CardDescription>{t("dashboard:settings.campusProfile.description")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-2">
              <Label htmlFor="settings-campus-name">{t("dashboard:settings.campusProfile.name")}</Label>
              <Input
                id="settings-campus-name"
                value={settings.campusName}
                placeholder={t("dashboard:settings.campusProfile.namePlaceholder")}
                onChange={(event) => setSettings({ ...settings, campusName: event.target.value })}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="settings-campus-tagline">{t("dashboard:settings.campusProfile.tagline")}</Label>
              <Textarea
                id="settings-campus-tagline"
                rows={2}
                value={settings.campusTagline}
                placeholder={t("dashboard:settings.campusProfile.taglinePlaceholder")}
                onChange={(event) => setSettings({ ...settings, campusTagline: event.target.value })}
              />
            </div>
            <div className="grid gap-2">
              <Label>{t("dashboard:settings.campusProfile.brandLogo")}</Label>
              <div className="flex items-center gap-3 rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                {t("dashboard:settings.campusProfile.brandLogoHint")}
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("dashboard:settings.appearance.title")}</CardTitle>
            <CardDescription>{t("dashboard:settings.appearance.description")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between rounded-lg border p-4">
              <div>
                <div className="font-medium">{t("dashboard:settings.appearance.darkMode")}</div>
                <div className="text-xs text-muted-foreground">{t("dashboard:settings.appearance.darkModeDesc")}</div>
              </div>
              <Switch checked={theme === "dark"} onCheckedChange={toggle} aria-label={t("dashboard:settings.appearance.darkMode")} />
            </div>
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>{t("dashboard:settings.notifications.title")}</CardTitle>
            <CardDescription>{t("dashboard:settings.notifications.description")}</CardDescription>
          </CardHeader>
          <CardContent className="grid gap-3 sm:grid-cols-3">
            {notificationRows.map((row) => (
              <div key={row.key} className="flex items-center justify-between rounded-lg border p-4">
                <div>
                  <div className="font-medium">{row.label}</div>
                  <div className="text-xs text-muted-foreground">{row.description}</div>
                </div>
                <Switch
                  checked={settings[row.key]}
                  onCheckedChange={(value) => setSettings({ ...settings, [row.key]: Boolean(value) })}
                  aria-label={row.label}
                />
              </div>
            ))}
          </CardContent>
        </Card>
      </div>

      <div className="flex justify-end">
        <Button onClick={save}>{t("dashboard:settings.saveChanges")}</Button>
      </div>
    </div>
  );
}
