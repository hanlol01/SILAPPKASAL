import { createFileRoute } from "@tanstack/react-router";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";
import { User, ShieldCheck, KeyRound, Pencil, Save, X, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { PasswordInput } from "@/components/ui/password-input";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
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
import { ApiError } from "@/lib/api-client";
import { useTranslation } from "react-i18next";
import type {
  PortalProfileUpdatePayload,
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

// ---------------------------------------------------------------------------
// Helper: extract field errors from ApiError (Laravel 422)
// ---------------------------------------------------------------------------

type FieldErrors = Record<string, string>;

function extractFieldErrors(err: unknown): FieldErrors {
  if (err instanceof ApiError && err.errors) {
    const flat: FieldErrors = {};
    for (const [key, messages] of Object.entries(err.errors)) {
      if (messages.length > 0) flat[key] = messages[0];
    }
    return flat;
  }
  return {};
}

function errorMessage(err: unknown): string {
  if (err instanceof ApiError) return err.message;
  return "An unexpected error occurred.";
}

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
      const fe = extractFieldErrors(err);
      if (Object.keys(fe).length > 0) {
        setFieldErrors(fe);
      } else {
        toast.error(err instanceof ApiError ? err.message : t("common:unexpectedError"));
      }
    },
  });

  function handleEdit() {
    setName(data.name);
    setPhoneNumber(data.phone_number ?? "");
    setFieldErrors({});
    setEditing(true);
  }

  function handleCancel() {
    setEditing(false);
    setFieldErrors({});
  }

  function handleSave() {
    mutation.mutate({
      name: name.trim(),
      phone_number: phoneNumber.trim() || null,
    });
  }

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <User className="h-4 w-4" />
          </div>
          <div>
            <CardTitle className="text-base">{t("profile")}</CardTitle>
            <CardDescription>{t("profileDesc")}</CardDescription>
          </div>
        </div>
        {!editing && (
          <Button variant="outline" size="sm" onClick={handleEdit}>
            <Pencil className="mr-2 h-3.5 w-3.5" /> {t("common:edit")}
          </Button>
        )}
      </CardHeader>
      <CardContent>
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
                  type="tel"
                  value={phoneNumber}
                  onChange={(e) => setPhoneNumber(e.target.value)}
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
            <ReadOnlyField label={t("email")} value={data.email} />
            {data.nim && <ReadOnlyField label="NIM" value={data.nim} />}
            {data.nip && <ReadOnlyField label="NIP" value={data.nip} />}
          </div>
        )}
      </CardContent>
    </Card>
  );
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
    <Card>
      <CardHeader>
        <div className="flex items-center gap-3">
          <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <ShieldCheck className="h-4 w-4" />
          </div>
          <div>
            <CardTitle className="text-base">{t("accountStatus")}</CardTitle>
            <CardDescription>{t("accountStatusDesc")}</CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent className="grid gap-4 text-sm sm:grid-cols-3">
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
      </CardContent>
    </Card>
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
      const fe = extractFieldErrors(err);
      if (Object.keys(fe).length > 0) {
        setFieldErrors(fe);
      } else {
        toast.error(err instanceof ApiError ? err.message : t("common:unexpectedError"));
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
    <Card>
      <CardHeader>
        <div className="flex items-center gap-3">
          <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <KeyRound className="h-4 w-4" />
          </div>
          <div>
            <CardTitle className="text-base">{t("changePassword")}</CardTitle>
            <CardDescription>
              {t("changePasswordDesc")}
            </CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent>
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
      </CardContent>
    </Card>
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
