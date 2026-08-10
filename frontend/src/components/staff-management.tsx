import { useEffect, useMemo, useState, type ReactNode } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";

import {
  activateStaffUser,
  adminUsersQueryKeys,
  createStaffUser,
  deactivateStaffUser,
  getStaffUsers,
  resetStaffUserPassword,
  updateStaffUser,
  type StaffUserPayload,
  type StaffUserUpdatePayload,
} from "@/lib/admin-users-api";
import { ApiError } from "@/lib/api-client";
import type { ApiUser } from "@/lib/api-types";
import { apiErrorMessage } from "@/lib/form-errors";
import { ActiveStateBadge } from "@/components/status-badge";
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { PasswordInput } from "@/components/ui/password-input";
import { Label } from "@/components/ui/label";
import { SelectInput } from "@/components/form-fields";
import { EmptyState } from "@/components/empty-state";
import { Inbox, SearchX } from "lucide-react";

type StaffFormValues = Omit<StaffUserPayload, "university_id">;

const emptyStaffForm = (): StaffFormValues => ({
  name: "",
  email: "",
  nip: "",
  phone_number: "",
  role_code: "satgas_ppks",
  password: "",
  password_confirmation: "",
});

export function StaffManagement({
  universityId,
  universityName,
  canManageAdministrators,
}: {
  universityId: number;
  universityName?: string | null;
  canManageAdministrators: boolean;
}) {
  const { t } = useTranslation(["dashboard"]);
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [isActive, setIsActive] = useState("");
  const [createOpen, setCreateOpen] = useState(false);
  const [editing, setEditing] = useState<ApiUser | null>(null);
  const [passwordTarget, setPasswordTarget] = useState<ApiUser | null>(null);
  const [statusTarget, setStatusTarget] = useState<ApiUser | null>(null);
  const query = useMemo(
    () => ({ university_id: universityId, search: search || undefined, is_active: isActive || undefined }),
    [universityId, search, isActive],
  );
  const staffQuery = useQuery({
    queryKey: adminUsersQueryKeys.list({ ...query, scope: "staff" }),
    queryFn: () => getStaffUsers(query),
  });
  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["admin-users"] });
  const statusMutation = useMutation({
    mutationFn: (target: ApiUser) => target.is_active ? deactivateStaffUser(target.id) : activateStaffUser(target.id),
    onSuccess: () => {
      toast.success(t("dashboard:staff.statusUpdated"));
      setStatusTarget(null);
      invalidate();
    },
    onError: (error) => toast.error(apiErrorMessage(error, t("dashboard:staff.statusError"))),
  });

  return (
    <Card>
      <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <CardTitle>{t("dashboard:staff.title")}</CardTitle>
          <p className="text-sm text-muted-foreground">
            {universityName
              ? t("dashboard:staff.subtitleWithUniversity", { university: universityName })
              : t("dashboard:staff.subtitle")}
          </p>
        </div>
        <Button onClick={() => setCreateOpen(true)}>{t("dashboard:staff.create")}</Button>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid gap-3 sm:grid-cols-2">
          <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder={t("dashboard:staff.search")} />
          <SelectInput
            value={isActive}
            onValueChange={setIsActive}
            placeholder={t("dashboard:staff.anyStatus")}
            options={[
              { value: "", label: t("dashboard:staff.anyStatus") },
              { value: "true", label: t("dashboard:staff.active") },
              { value: "false", label: t("dashboard:staff.inactive") },
            ]}
          />
        </div>
        <div className="overflow-x-auto rounded-md border">
          <table className="w-full min-w-[720px] text-sm">
            <thead className="bg-muted/50 text-left">
              <tr>
                <th className="p-3">{t("dashboard:staff.staff")}</th>
                <th className="p-3">{t("dashboard:staff.role")}</th>
                <th className="p-3">{t("dashboard:staff.nip")}</th>
                <th className="p-3">{t("dashboard:common.status")}</th>
                <th className="p-3 text-right">{t("dashboard:common.actions")}</th>
              </tr>
            </thead>
            <tbody>
              {(staffQuery.data?.data ?? []).map((staff) => (
                <tr key={staff.id} className="border-t">
                  <td className="p-3">
                    <div className="font-medium">{staff.name}</div>
                    <div className="text-xs text-muted-foreground">{staff.email}</div>
                  </td>
                  <td className="p-3">{staff.role?.code === "admin" ? t("dashboard:staff.admin") : t("dashboard:staff.satgas")}</td>
                  <td className="p-3 font-mono text-xs">{staff.nip}</td>
                  <td className="p-3">
                    <ActiveStateBadge active={staff.is_active} activeLabel={t("dashboard:staff.active")} inactiveLabel={t("dashboard:staff.inactive")} />
                  </td>
                  <td className="p-3 text-right">
                    <div className="flex flex-wrap justify-end gap-2">
                      <Button size="sm" variant="outline" onClick={() => setEditing(staff)}>{t("dashboard:common.edit")}</Button>
                      <Button size="sm" variant="outline" onClick={() => setPasswordTarget(staff)}>{t("dashboard:staff.resetPassword")}</Button>
                      <Button size="sm" variant="outline" onClick={() => setStatusTarget(staff)}>
                        {staff.is_active ? t("dashboard:staff.deactivate") : t("dashboard:staff.activate")}
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
              {staffQuery.isSuccess && staffQuery.data.data.length === 0 && (
                <tr>
                  <td colSpan={5} className="p-0">
                    <EmptyState icon={search || isActive ? SearchX : Inbox} title={t(search || isActive ? "dashboard:staff.filteredEmptyTitle" : "dashboard:staff.emptyTitle")} description={t(search || isActive ? "dashboard:staff.filteredEmptyDesc" : "dashboard:staff.emptyDesc")} />
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </CardContent>
      <StaffDialog
        open={createOpen || Boolean(editing)}
        staff={editing}
        universityId={universityId}
        canManageAdministrators={canManageAdministrators}
        onOpenChange={(open) => {
          if (!open) {
            setCreateOpen(false);
            setEditing(null);
          }
        }}
        onSaved={() => {
          setCreateOpen(false);
          setEditing(null);
          invalidate();
        }}
      />
      <ResetStaffPasswordDialog target={passwordTarget} onOpenChange={(open) => !open && setPasswordTarget(null)} onSaved={() => { setPasswordTarget(null); invalidate(); }} />
      <AlertDialog open={Boolean(statusTarget)} onOpenChange={(open) => !open && setStatusTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{statusTarget?.is_active ? t("dashboard:staff.deactivateTitle", { name: statusTarget.name }) : t("dashboard:staff.activateTitle", { name: statusTarget?.name })}</AlertDialogTitle>
            <AlertDialogDescription>{statusTarget?.is_active ? t("dashboard:staff.deactivateDescription") : t("dashboard:staff.activateDescription")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("dashboard:common.cancel")}</AlertDialogCancel>
            <AlertDialogAction disabled={statusMutation.isPending || !statusTarget} onClick={(event) => { event.preventDefault(); if (statusTarget) statusMutation.mutate(statusTarget); }}>
              {statusTarget?.is_active ? t("dashboard:staff.deactivate") : t("dashboard:staff.activate")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  );
}

function StaffDialog({ open, staff, universityId, canManageAdministrators, onOpenChange, onSaved }: {
  open: boolean;
  staff: ApiUser | null;
  universityId: number;
  canManageAdministrators: boolean;
  onOpenChange: (open: boolean) => void;
  onSaved: () => void;
}) {
  const { t } = useTranslation(["dashboard"]);
  const [values, setValues] = useState<StaffFormValues>(emptyStaffForm);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const mutation = useMutation({
    mutationFn: () => {
      if (staff) {
        const payload: StaffUserUpdatePayload = { name: values.name, email: values.email, nip: values.nip, phone_number: values.phone_number || null };
        return updateStaffUser(staff.id, payload);
      }
      return createStaffUser({ ...values, university_id: universityId });
    },
    onSuccess: () => {
      toast.success(t(staff ? "dashboard:staff.updated" : "dashboard:staff.created"));
      onSaved();
    },
    onError: (error) => {
      if (error instanceof ApiError) setErrors(error.errors ?? {});
      toast.error(apiErrorMessage(error, t("dashboard:staff.saveError")));
    },
  });

  useEffect(() => {
    setValues(staff ? {
      name: staff.name,
      email: staff.email,
      nip: staff.nip ?? "",
      phone_number: staff.phone_number ?? "",
      role_code: staff.role?.code === "admin" ? "admin" : "satgas_ppks",
      password: "",
      password_confirmation: "",
    } : emptyStaffForm());
    setErrors({});
  }, [staff, open]);

  const setValue = <K extends keyof StaffFormValues>(key: K, value: StaffFormValues[K]) => {
    setValues((current) => ({ ...current, [key]: value }));
    setErrors((current) => ({ ...current, [key]: [] }));
  };
  const roleOptions = canManageAdministrators
    ? [{ value: "satgas_ppks", label: t("dashboard:staff.satgas") }, { value: "admin", label: t("dashboard:staff.admin") }]
    : [{ value: "satgas_ppks", label: t("dashboard:staff.satgas") }];

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl">
        <DialogHeader><DialogTitle>{t(staff ? "dashboard:staff.edit" : "dashboard:staff.create")}</DialogTitle></DialogHeader>
        <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }}>
          <Field label={t("dashboard:staff.name")} error={errors.name?.[0]}><Input value={values.name} onChange={(event) => setValue("name", event.target.value)} required /></Field>
          <Field label={t("dashboard:staff.email")} error={errors.email?.[0]}><Input type="email" value={values.email} onChange={(event) => setValue("email", event.target.value)} required /></Field>
          <Field label={t("dashboard:staff.nip")} error={errors.nip?.[0]}><Input value={values.nip} onChange={(event) => setValue("nip", event.target.value)} required /></Field>
          <Field label={t("dashboard:staff.phone")} error={errors.phone_number?.[0]}><Input value={values.phone_number ?? ""} onChange={(event) => setValue("phone_number", event.target.value)} /></Field>
          <Field label={t("dashboard:staff.role")} error={errors.role_code?.[0]}>
            {staff ? <Input value={values.role_code === "admin" ? t("dashboard:staff.admin") : t("dashboard:staff.satgas")} disabled /> : <SelectInput value={values.role_code} onValueChange={(value) => setValue("role_code", value as StaffFormValues["role_code"])} options={roleOptions} placeholder={t("dashboard:staff.role")} />}
          </Field>
          {!staff && <>
            <Field label={t("dashboard:staff.password")} error={errors.password?.[0]}><PasswordInput value={values.password} onChange={(event) => setValue("password", event.target.value)} required autoComplete="new-password" /></Field>
            <Field label={t("dashboard:staff.passwordConfirmation")} error={errors.password_confirmation?.[0]}><PasswordInput value={values.password_confirmation} onChange={(event) => setValue("password_confirmation", event.target.value)} required autoComplete="new-password" /></Field>
          </>}
          <div className="sm:col-span-2 flex justify-end gap-2"><Button type="button" variant="outline" onClick={() => onOpenChange(false)}>{t("dashboard:common.cancel")}</Button><Button type="submit" disabled={mutation.isPending}>{mutation.isPending ? t("dashboard:common.saving") : t("dashboard:common.save")}</Button></div>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function ResetStaffPasswordDialog({ target, onOpenChange, onSaved }: { target: ApiUser | null; onOpenChange: (open: boolean) => void; onSaved: () => void }) {
  const { t } = useTranslation(["dashboard"]);
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const mutation = useMutation({
    mutationFn: () => resetStaffUserPassword(target!.id, password, confirmation),
    onSuccess: () => {
      toast.success(t("dashboard:staff.passwordReset"));
      setPassword("");
      setConfirmation("");
      onSaved();
    },
    onError: (error) => {
      if (error instanceof ApiError) setErrors(error.errors ?? {});
      toast.error(apiErrorMessage(error, t("dashboard:staff.passwordResetError")));
    },
  });

  useEffect(() => {
    if (!target) {
      setPassword("");
      setConfirmation("");
      setErrors({});
    }
  }, [target]);

  return (
    <Dialog open={Boolean(target)} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader><DialogTitle>{t("dashboard:staff.resetPasswordTitle", { name: target?.name })}</DialogTitle></DialogHeader>
        <p className="text-sm text-muted-foreground">{t("dashboard:staff.resetPasswordDescription")}</p>
        <div className="grid gap-3">
          <Field label={t("dashboard:staff.newPassword")} error={errors.password?.[0]}><PasswordInput value={password} onChange={(event) => setPassword(event.target.value)} autoComplete="new-password" /></Field>
          <Field label={t("dashboard:staff.passwordConfirmation")} error={errors.password_confirmation?.[0]}><PasswordInput value={confirmation} onChange={(event) => setConfirmation(event.target.value)} autoComplete="new-password" /></Field>
          <div className="flex justify-end gap-2"><Button variant="outline" onClick={() => onOpenChange(false)}>{t("dashboard:common.cancel")}</Button><Button disabled={mutation.isPending || password.length < 8 || password !== confirmation} onClick={() => mutation.mutate()}>{mutation.isPending ? t("dashboard:common.saving") : t("dashboard:staff.resetPassword")}</Button></div>
        </div>
      </DialogContent>
    </Dialog>
  );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
  return <div className="grid gap-2"><Label>{label}</Label>{children}{error && <p className="text-xs text-destructive">{error}</p>}</div>;
}
