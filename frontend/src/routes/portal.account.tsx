import { createFileRoute } from "@tanstack/react-router";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";
import { User, ShieldCheck, KeyRound, Pencil, Save, X, Loader2 } from "lucide-react";
import { CollapsibleDataCard } from "@/components/collapsible-data-card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { PasswordInput } from "@/components/ui/password-input";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
  Card,
  CardContent,
  CardHeader,
} from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Separator } from "@/components/ui/separator";
import { QueryErrorState } from "@/components/query-state";
import {
  portalQueryKeys,
  getMyProfile,
  updateMyProfile,
  getMyAccountStatus,
  changeMyPassword,
} from "@/lib/portal-api";
import { formatDate } from "@/lib/format";
import { useAuth } from "@/hooks/use-auth";
import { hasPortalAccess } from "@/lib/auth-roles";
import { apiErrorMessage, laravelFieldErrors } from "@/lib/form-errors";
import { optionalPhoneNumberError, phoneInputAttributes } from "@/lib/phone-validation";
import { useTranslation } from "react-i18next";
import type {
  PortalProfileUpdatePayload,
  ProfileStatusCode,
  PortalChangePasswordPayload,
} from "@/lib/portal-types";

export const Route = createFileRoute("/portal/account")({
  component: PortalAccountPage,
  head: () => ({
    meta: [
      { title: "Account - SILAPPKASAL Portal" },
      {
        name: "description",
        content: "Manage your profile and account settings.",
      },
    ],
  }),
});

type FieldErrors = Record<string, string>;

// ---------------------------------------------------------------------------
// Main page
// ---------------------------------------------------------------------------

function PortalAccountPage() {
  const { t } = useTranslation(["portal"]);
  const { roleCode } = useAuth();
  const enabled = hasPortalAccess(roleCode);

  const profileQuery = useQuery({
    queryKey: portalQueryKeys.profile(),
    queryFn: getMyProfile,
    enabled,
  });

  const accountStatusQuery = useQuery({
    queryKey: portalQueryKeys.accountStatus(),
    queryFn: getMyAccountStatus,
    enabled,
  });

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t("account")}</h1>
        <p className="text-sm text-muted-foreground">
          {t("accountSubtitle")}
        </p>
      </div>

      {/* Profile section */}
      {profileQuery.isLoading && <ProfileSkeleton />}
      {profileQuery.isError && (
        <QueryErrorState
          message={t("profileLoadError")}
          onRetry={() => profileQuery.refetch()}
        />
      )}
      {profileQuery.isSuccess && <ProfileSection data={profileQuery.data} />}

      {/* Account status section */}
      {accountStatusQuery.isLoading && <StatusSkeleton />}
      {accountStatusQuery.isError && (
        <QueryErrorState
          message={t("accountStatusLoadError")}
          onRetry={() => accountStatusQuery.refetch()}
        />
      )}
      {accountStatusQuery.isSuccess && (
        <AccountStatusSection data={accountStatusQuery.data} />
      )}

      {/* Change password section */}
      <ChangePasswordSection />
    </div>
  );
}

// ---------------------------------------------------------------------------
// Profile — view / edit mode
// ---------------------------------------------------------------------------

function ProfileSection({
  data,
}: {
  data: import("@/lib/portal-types").PortalProfile;
}) {
  const { t } = useTranslation(["portal", "common"]);
  const queryClient = useQueryClient();
  const [editing, setEditing] = useState(false);
  const [name, setName] = useState(data.name);
  const [phoneNumber, setPhoneNumber] = useState(data.phone_number ?? "");
  const [profileStatus, setProfileStatus] = useState<ProfileStatusCode | null>(data.profile_status);
  const [profileStatusOther, setProfileStatusOther] = useState(data.profile_status_other ?? "");
  const [address, setAddress] = useState(data.address ?? "");
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});

  const mutation = useMutation({
    mutationFn: (payload: PortalProfileUpdatePayload) =>
      updateMyProfile(payload),
    onSuccess: () => {
      toast.success(t("profileUpdated"));
      queryClient.invalidateQueries({ queryKey: portalQueryKeys.profile() });
      setEditing(false);
      setFieldErrors({});
    },
    onError: (err) => {
      const fe = laravelFieldErrors(err);
      if (Object.keys(fe).length > 0) {
        setFieldErrors(fe);
      } else {
        toast.error(apiErrorMessage(err, t("common:unexpectedError")));
      }
    },
  });

  function handleEdit() {
    setName(data.name);
    setPhoneNumber(data.phone_number ?? "");
    setProfileStatus(data.profile_status);
    setProfileStatusOther(data.profile_status_other ?? "");
    setAddress(data.address ?? "");
    setFieldErrors({});
    setEditing(true);
  }

  function handleCancel() {
    setEditing(false);
    setFieldErrors({});
  }

  function handleSave() {
    const phoneError = optionalPhoneNumberError(phoneNumber, t("common:validation.phoneNumber"));

    if (phoneError) {
      setFieldErrors((current) => ({ ...current, phone_number: phoneError }));
      return;
    }

    mutation.mutate({
      name: name.trim(),
      phone_number: phoneNumber || null,
      profile_status: profileStatus,
      profile_status_other: profileStatus === "other" ? profileStatusOther.trim() || null : null,
      address: address.trim() || null,
    });
  }

  return (
    <CollapsibleDataCard
      icon={User}
      title={t("profile")}
      description={t("profileDesc")}
      expandLabel={t("collapsible.expand")}
      collapseLabel={t("collapsible.collapse")}
      headerAction={!editing ? (
          <Button variant="outline" size="sm" onClick={handleEdit}>
            <Pencil className="mr-2 h-3.5 w-3.5" /> {t("common:edit")}
          </Button>
        ) : undefined}
    >
        {editing ? (
          <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              {/* Name — editable */}
              <div className="space-y-1.5">
                <Label htmlFor="profile-name">{t("name")}</Label>
                <Input
                  id="profile-name"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  disabled={mutation.isPending}
                />
                {fieldErrors.name && (
                  <p className="text-xs text-destructive">
                    {fieldErrors.name}
                  </p>
                )}
              </div>
              {/* Phone — editable */}
              <div className="space-y-1.5">
                <Label htmlFor="profile-phone">{t("phoneNumber")}</Label>
                <Input
                  id="profile-phone"
                  {...phoneInputAttributes}
                  value={phoneNumber}
                  onChange={(e) => {
                    setPhoneNumber(e.target.value);
                    setFieldErrors((current) => ({ ...current, phone_number: "" }));
                  }}
                  placeholder={t("optional")}
                  disabled={mutation.isPending}
                />
                {fieldErrors.phone_number && (
                  <p className="text-xs text-destructive">
                    {fieldErrors.phone_number}
                  </p>
                )}
              </div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label htmlFor="profile-status">{t("profileStatus")}</Label>
                <Select
                  value={profileStatus ?? "unset"}
                  onValueChange={(value) => {
                    const nextStatus = value === "unset" ? null : value as ProfileStatusCode;
                    setProfileStatus(nextStatus);
                    if (nextStatus !== "other") setProfileStatusOther("");
                  }}
                  disabled={mutation.isPending}
                >
                  <SelectTrigger id="profile-status"><SelectValue /></SelectTrigger>
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
                <div className="space-y-1.5">
                  <Label htmlFor="profile-status-other">{t("profileStatusOther")}</Label>
                  <Input id="profile-status-other" value={profileStatusOther} onChange={(event) => setProfileStatusOther(event.target.value)} disabled={mutation.isPending} />
                  {fieldErrors.profile_status_other && <p className="text-xs text-destructive">{fieldErrors.profile_status_other}</p>}
                </div>
              )}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="profile-address">{t("shortAddress")}</Label>
              <Textarea id="profile-address" rows={3} maxLength={160} value={address} onChange={(event) => setAddress(event.target.value)} placeholder={t("shortAddressHint")} disabled={mutation.isPending} />
              <p className="text-xs text-muted-foreground">{t("shortAddressHelp")}</p>
              {fieldErrors.address && <p className="text-xs text-destructive">{fieldErrors.address}</p>}
            </div>
            {/* Read-only fields shown for context */}
            <div className="grid gap-4 text-sm sm:grid-cols-2">
              <ReadOnlyField label={t("email")} value={data.email} />
              {data.nim && <ReadOnlyField label="NIM" value={data.nim} />}
              {data.nip && <ReadOnlyField label="NIP" value={data.nip} />}
            </div>
            {/* Actions */}
            <div className="flex gap-2">
              <Button
                size="sm"
                onClick={handleSave}
                disabled={mutation.isPending}
              >
                {mutation.isPending ? (
                  <Loader2 className="mr-2 h-3.5 w-3.5 animate-spin" />
                ) : (
                  <Save className="mr-2 h-3.5 w-3.5" />
                )}
                {t("common:save")}
              </Button>
              <Button
                variant="ghost"
                size="sm"
                onClick={handleCancel}
                disabled={mutation.isPending}
              >
                <X className="mr-2 h-3.5 w-3.5" /> {t("common:cancel")}
              </Button>
            </div>
          </div>
        ) : (
          <div className="grid gap-4 text-sm sm:grid-cols-2">
            <ReadOnlyField label={t("name")} value={data.name} />
            <ReadOnlyField label={t("phoneNumber")} value={data.phone_number} />
            <ReadOnlyField label={t("profileStatus")} value={profileStatusLabel(t, data.profile_status, data.profile_status_other)} />
            <ReadOnlyField label={t("shortAddress")} value={data.address} />
            <ReadOnlyField label={t("email")} value={data.email} />
            {data.nim && <ReadOnlyField label="NIM" value={data.nim} />}
            {data.nip && <ReadOnlyField label="NIP" value={data.nip} />}
          </div>
        )}
    </CollapsibleDataCard>
  );
}

function profileStatusLabel(
  t: (key: string) => string,
  status: ProfileStatusCode | null,
  other: string | null,
) {
  if (status === "other") return other || t("optional");
  if (!status) return t("optional");

  const labels: Record<Exclude<ProfileStatusCode, "other">, string> = {
    student: t("profileStatuses.student"),
    lecturer: t("profileStatuses.lecturer"),
    education_staff: t("profileStatuses.educationStaff"),
    employee: t("profileStatuses.employee"),
  };

  return labels[status];
}

// ---------------------------------------------------------------------------
// Account status — read-only
// ---------------------------------------------------------------------------

function AccountStatusSection({
  data,
}: {
  data: import("@/lib/portal-types").PortalAccountStatus;
}) {
  const { t, i18n } = useTranslation(["portal", "common"]);
  return (
    <CollapsibleDataCard
      icon={ShieldCheck}
      title={t("accountStatus")}
      description={t("accountStatusDesc")}
      contentClassName="grid gap-4 text-sm sm:grid-cols-3"
      expandLabel={t("collapsible.expand")}
      collapseLabel={t("collapsible.collapse")}
    >
        <ReadOnlyField
          label={t("accountActive")}
          value={data.is_active ? t("common:yes") : t("common:no")}
        />
        <ReadOnlyField
          label={t("emailVerified")}
          value={formatDate(data.email_verified_at, i18n.language)}
        />
        <ReadOnlyField
          label={t("accountCreated")}
          value={formatDate(data.created_at, i18n.language)}
        />
        {data.registration_number && (
          <ReadOnlyField
            label={t("registrationNumber")}
            value={data.registration_number}
          />
        )}
    </CollapsibleDataCard>
  );
}

// ---------------------------------------------------------------------------
// Change password
// ---------------------------------------------------------------------------

function ChangePasswordSection() {
  const { t } = useTranslation(["portal", "common"]);
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});

  const mutation = useMutation({
    mutationFn: (payload: PortalChangePasswordPayload) =>
      changeMyPassword(payload),
    onSuccess: () => {
      toast.success(t("passwordChanged"));
      setCurrentPassword("");
      setNewPassword("");
      setConfirmPassword("");
      setFieldErrors({});
    },
    onError: (err) => {
      const fe = laravelFieldErrors(err);
      if (Object.keys(fe).length > 0) {
        setFieldErrors(fe);
      } else {
        toast.error(apiErrorMessage(err, t("common:unexpectedError")));
      }
    },
  });

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setFieldErrors({});
    mutation.mutate({
      current_password: currentPassword,
      password: newPassword,
      password_confirmation: confirmPassword,
    });
  }

  return (
    <CollapsibleDataCard
      icon={KeyRound}
      title={t("changePassword")}
      description={t("changePasswordDesc")}
      expandLabel={t("collapsible.expand")}
      collapseLabel={t("collapsible.collapse")}
    >
        <form onSubmit={handleSubmit} className="max-w-md space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="pw-current">{t("currentPassword")}</Label>
            <PasswordInput
              id="pw-current"
              value={currentPassword}
              onChange={(e) => setCurrentPassword(e.target.value)}
              autoComplete="current-password"
              disabled={mutation.isPending}
            />
            {fieldErrors.current_password && (
              <p className="text-xs text-destructive">
                {fieldErrors.current_password}
              </p>
            )}
          </div>
          <Separator />
          <div className="space-y-1.5">
            <Label htmlFor="pw-new">{t("newPassword")}</Label>
            <PasswordInput
              id="pw-new"
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
              autoComplete="new-password"
              disabled={mutation.isPending}
            />
            {fieldErrors.password && (
              <p className="text-xs text-destructive">
                {fieldErrors.password}
              </p>
            )}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="pw-confirm">{t("confirmNewPassword")}</Label>
            <PasswordInput
              id="pw-confirm"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              autoComplete="new-password"
              disabled={mutation.isPending}
            />
            {fieldErrors.password_confirmation && (
              <p className="text-xs text-destructive">
                {fieldErrors.password_confirmation}
              </p>
            )}
          </div>
          <Button type="submit" size="sm" disabled={mutation.isPending}>
            {mutation.isPending ? (
              <Loader2 className="mr-2 h-3.5 w-3.5 animate-spin" />
            ) : (
              <KeyRound className="mr-2 h-3.5 w-3.5" />
            )}
            {t("changePassword")}
          </Button>
        </form>
    </CollapsibleDataCard>
  );
}

// ---------------------------------------------------------------------------
// Shared field component
// ---------------------------------------------------------------------------

function ReadOnlyField({
  label,
  value,
}: {
  label: string;
  value: string | null | undefined;
}) {
  return (
    <div>
      <div className="text-xs uppercase tracking-wide text-muted-foreground">
        {label}
      </div>
      <div className="mt-1 text-sm">{value ?? "—"}</div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Skeletons
// ---------------------------------------------------------------------------

function ProfileSkeleton() {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center gap-3">
        <Skeleton className="h-9 w-9 rounded-lg" />
        <div className="space-y-1.5">
          <Skeleton className="h-4 w-20" />
          <Skeleton className="h-3 w-40" />
        </div>
      </CardHeader>
      <CardContent className="grid gap-4 sm:grid-cols-2">
        {Array.from({ length: 4 }).map((_, i) => (
          <div key={i} className="space-y-1.5">
            <Skeleton className="h-3 w-20" />
            <Skeleton className="h-4 w-36" />
          </div>
        ))}
      </CardContent>
    </Card>
  );
}

function StatusSkeleton() {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center gap-3">
        <Skeleton className="h-9 w-9 rounded-lg" />
        <div className="space-y-1.5">
          <Skeleton className="h-4 w-28" />
          <Skeleton className="h-3 w-44" />
        </div>
      </CardHeader>
      <CardContent className="grid gap-4 sm:grid-cols-3">
        {Array.from({ length: 4 }).map((_, i) => (
          <div key={i} className="space-y-1.5">
            <Skeleton className="h-3 w-24" />
            <Skeleton className="h-4 w-28" />
          </div>
        ))}
      </CardContent>
    </Card>
  );
}
