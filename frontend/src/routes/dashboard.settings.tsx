import { createFileRoute } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState, type ComponentProps, type FormEvent } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { PasswordInput } from "@/components/ui/password-input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { useTheme } from "@/hooks/use-theme";
import { getLocalStorageItem, setLocalStorageItem } from "@/lib/auth-storage";
import { PageBreadcrumb } from "@/components/page-breadcrumb";
import { apiErrorMessage, laravelFieldErrors } from "@/lib/form-errors";
import { optionalPhoneNumberError, phoneInputAttributes } from "@/lib/phone-validation";
import { changeMyPassword, getMyProfile, portalQueryKeys, updateMyProfile } from "@/lib/portal-api";
import type { PortalChangePasswordPayload, PortalProfile, PortalProfileUpdatePayload, ProfileStatusCode } from "@/lib/portal-types";

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

type FieldErrors = Record<string, string>;

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

function ProfileSettingsCard() {
  const { t } = useTranslation(["portal", "common"]);
  const queryClient = useQueryClient();
  const [name, setName] = useState("");
  const [phoneNumber, setPhoneNumber] = useState("");
  const [profileStatus, setProfileStatus] = useState<ProfileStatusCode | null>(null);
  const [profileStatusOther, setProfileStatusOther] = useState("");
  const [address, setAddress] = useState("");
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const profileQuery = useQuery({
    queryKey: portalQueryKeys.profile(),
    queryFn: getMyProfile,
  });

  const profileMutation = useMutation({
    mutationFn: (payload: PortalProfileUpdatePayload) => updateMyProfile(payload),
    onSuccess: (profile) => {
      queryClient.setQueryData(portalQueryKeys.profile(), profile);
      toast.success(t("profileUpdated"));
      setFieldErrors({});
    },
    onError: (error) => {
      const errors = laravelFieldErrors(error);
      if (Object.keys(errors).length > 0) {
        setFieldErrors(errors);
        return;
      }

      toast.error(apiErrorMessage(error, t("profileLoadError")));
    },
  });

  useEffect(() => {
    if (!profileQuery.data) return;

    setName(profileQuery.data.name);
    setPhoneNumber(profileQuery.data.phone_number ?? "");
    setProfileStatus(profileQuery.data.profile_status);
    setProfileStatusOther(profileQuery.data.profile_status_other ?? "");
    setAddress(profileQuery.data.address ?? "");
  }, [profileQuery.data]);

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const phoneError = optionalPhoneNumberError(
      phoneNumber,
      t("common:validation.phoneNumber"),
    );

    if (phoneError) {
      setFieldErrors((current) => ({ ...current, phone_number: phoneError }));
      return;
    }

    setFieldErrors({});
    profileMutation.mutate({
      name: name.trim(),
      phone_number: phoneNumber || null,
      profile_status: profileStatus,
      profile_status_other: profileStatus === "other" ? profileStatusOther.trim() || null : null,
      address: address.trim() || null,
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("profile")}</CardTitle>
        <CardDescription>{t("profileDesc")}</CardDescription>
      </CardHeader>
      <CardContent>
        {profileQuery.isPending ? (
          <p className="text-sm text-muted-foreground">{t("common:loading")}</p>
        ) : profileQuery.isError ? (
          <div className="space-y-3">
            <p role="alert" className="text-sm text-destructive">{t("profileLoadError")}</p>
            <Button type="button" variant="outline" onClick={() => profileQuery.refetch()}>
              {t("common:retry")}
            </Button>
          </div>
        ) : (
          <form className="space-y-4" onSubmit={handleSubmit}>
            <div className="grid gap-4 sm:grid-cols-2">
              <ProfileTextField
                id="settings-profile-name"
                label={t("name")}
                value={name}
                onChange={setName}
                disabled={profileMutation.isPending}
                error={fieldErrors.name}
              />
              <ProfileTextField
                id="settings-profile-phone"
                label={t("phoneNumber")}
                value={phoneNumber}
                onChange={(value) => {
                  setPhoneNumber(value);
                  setFieldErrors((current) => ({ ...current, phone_number: "" }));
                }}
                disabled={profileMutation.isPending}
                error={fieldErrors.phone_number}
                inputProps={phoneInputAttributes}
                placeholder={t("optional")}
              />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label htmlFor="settings-profile-status">{t("profileStatus")}</Label>
                <Select
                  value={profileStatus ?? "unset"}
                  onValueChange={(value) => {
                    const nextStatus = value === "unset" ? null : value as ProfileStatusCode;
                    setProfileStatus(nextStatus);
                    if (nextStatus !== "other") setProfileStatusOther("");
                  }}
                  disabled={profileMutation.isPending}
                >
                  <SelectTrigger id="settings-profile-status"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="unset">{t("optional")}</SelectItem>
                    <SelectItem value="student">{t("profileStatuses.student")}</SelectItem>
                    <SelectItem value="lecturer">{t("profileStatuses.lecturer")}</SelectItem>
                    <SelectItem value="education_staff">{t("profileStatuses.educationStaff")}</SelectItem>
                    <SelectItem value="employee">{t("profileStatuses.employee")}</SelectItem>
                    <SelectItem value="other">{t("profileStatuses.other")}</SelectItem>
                  </SelectContent>
                </Select>
                {fieldErrors.profile_status && <p className="text-xs text-destructive">{fieldErrors.profile_status}</p>}
              </div>
              {profileStatus === "other" && (
                <ProfileTextField
                  id="settings-profile-status-other"
                  label={t("profileStatusOther")}
                  value={profileStatusOther}
                  onChange={setProfileStatusOther}
                  disabled={profileMutation.isPending}
                  error={fieldErrors.profile_status_other}
                  maxLength={100}
                />
              )}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="settings-profile-address">{t("shortAddress")}</Label>
              <Textarea
                id="settings-profile-address"
                rows={3}
                maxLength={160}
                value={address}
                placeholder={t("shortAddressHint")}
                onChange={(event) => setAddress(event.target.value)}
                disabled={profileMutation.isPending}
              />
              <p className="text-xs text-muted-foreground">{t("shortAddressHelp")}</p>
              {fieldErrors.address && <p className="text-xs text-destructive">{fieldErrors.address}</p>}
            </div>
            {profileQuery.data && <ProfileReadOnlyFields data={profileQuery.data} />}
            <Button type="submit" className="w-full sm:w-auto" disabled={profileMutation.isPending}>
              {t("common:save")}
            </Button>
          </form>
        )}
      </CardContent>
    </Card>
  );
}

function ChangePasswordSettingsCard() {
  const { t } = useTranslation(["portal", "common"]);
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const passwordMutation = useMutation({
    mutationFn: (payload: PortalChangePasswordPayload) => changeMyPassword(payload),
    onSuccess: () => {
      toast.success(t("passwordChanged"));
      setCurrentPassword("");
      setNewPassword("");
      setConfirmPassword("");
      setFieldErrors({});
    },
    onError: (error) => {
      const errors = laravelFieldErrors(error);
      if (Object.keys(errors).length > 0) {
        setFieldErrors(errors);
        return;
      }

      toast.error(apiErrorMessage(error, t("common:unexpectedError")));
    },
  });

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setFieldErrors({});
    passwordMutation.mutate({
      current_password: currentPassword,
      password: newPassword,
      password_confirmation: confirmPassword,
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("changePassword")}</CardTitle>
        <CardDescription>{t("changePasswordDesc")}</CardDescription>
      </CardHeader>
      <CardContent>
        <form className="max-w-md space-y-4" onSubmit={handleSubmit}>
          <PasswordField
            id="settings-current-password"
            label={t("currentPassword")}
            value={currentPassword}
            onChange={setCurrentPassword}
            disabled={passwordMutation.isPending}
            autoComplete="current-password"
            error={fieldErrors.current_password}
          />
          <Separator />
          <PasswordField
            id="settings-new-password"
            label={t("newPassword")}
            value={newPassword}
            onChange={setNewPassword}
            disabled={passwordMutation.isPending}
            autoComplete="new-password"
            error={fieldErrors.password}
          />
          <PasswordField
            id="settings-confirm-password"
            label={t("confirmNewPassword")}
            value={confirmPassword}
            onChange={setConfirmPassword}
            disabled={passwordMutation.isPending}
            autoComplete="new-password"
            error={fieldErrors.password_confirmation}
          />
          <Button type="submit" className="w-full sm:w-auto" disabled={passwordMutation.isPending}>
            {t("changePassword")}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}

function ProfileTextField({
  id,
  label,
  value,
  onChange,
  disabled,
  error,
  inputProps,
  placeholder,
  maxLength,
}: {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  disabled: boolean;
  error?: string;
  inputProps?: ComponentProps<typeof Input>;
  placeholder?: string;
  maxLength?: number;
}) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{label}</Label>
      <Input
        id={id}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        disabled={disabled}
        placeholder={placeholder}
        maxLength={maxLength}
        {...inputProps}
      />
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  );
}

function PasswordField({
  id,
  label,
  value,
  onChange,
  disabled,
  autoComplete,
  error,
}: {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  disabled: boolean;
  autoComplete: string;
  error?: string;
}) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{label}</Label>
      <PasswordInput
        id={id}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        disabled={disabled}
        autoComplete={autoComplete}
      />
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  );
}

function ProfileReadOnlyFields({ data }: { data: PortalProfile }) {
  const { t } = useTranslation(["portal"]);

  return (
    <div className="grid gap-4 border-t pt-4 text-sm sm:grid-cols-2">
      <ProfileReadOnlyField label={t("email")} value={data.email} />
      {data.nim && <ProfileReadOnlyField label="NIM" value={data.nim} />}
      {data.nip && <ProfileReadOnlyField label="NIP" value={data.nip} />}
    </div>
  );
}

function ProfileReadOnlyField({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
      <p className="mt-1 break-words text-sm">{value}</p>
    </div>
  );
}

function SettingsPage() {
  const { t } = useTranslation(["dashboard", "portal", "common"]);
  const { theme, toggle } = useTheme();
  const [settings, setSettings] = useState<Settings>(DEFAULTS);

  useEffect(() => {
    const stored = readStoredSettings();
    if (stored) {
      setSettings({ ...DEFAULTS, ...stored });
    }
  }, []);

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
        <ProfileSettingsCard />
        <ChangePasswordSettingsCard />

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
