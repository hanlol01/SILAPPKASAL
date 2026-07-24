import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  Download,
  FileCheck2,
  FileText,
  Loader2,
  Printer,
  RotateCcw,
  Send,
  Upload,
} from "lucide-react";
import { useMemo, useRef, useState } from "react";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { z } from "zod";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Progress } from "@/components/ui/progress";
import { Textarea } from "@/components/ui/textarea";
import { ApiError } from "@/lib/api-client";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import {
  cancelPortalFormalWithdrawal,
  createPortalFormalWithdrawal,
  downloadPortalWithdrawalSignedDocument,
  getPortalFormalWithdrawal,
  getPortalWithdrawalDraftDocument,
  portalQueryKeys,
  resubmitPortalFormalWithdrawal,
  submitPortalFormalWithdrawal,
  uploadPortalWithdrawalSignedDocument,
} from "@/lib/portal-api";
import type {
  ActiveWithdrawalSummary,
  FormalWithdrawalDetail,
} from "@/lib/portal-types";
import {
  validateWithdrawalDocumentFile,
  WITHDRAWAL_DOCUMENT_ACCEPT,
} from "@/lib/withdrawal-document-file";

interface FormalWithdrawalWizardProps {
  registrationNumber: string;
  canRequestWithdrawal: boolean;
  activeWithdrawal: ActiveWithdrawalSummary | null;
}

function withdrawalReasonSchema(messages: {
  required: string;
  min: string;
  max: string;
  confirmation: string;
}) {
  return z.object({
    reason: z
      .string()
      .trim()
      .min(1, messages.required)
      .min(20, messages.min)
      .max(2000, messages.max),
    confirmed: z.boolean().refine((value) => value, messages.confirmation),
  });
}

type WithdrawalReasonValues = z.infer<ReturnType<typeof withdrawalReasonSchema>>;

export function FormalWithdrawalWizard({
  registrationNumber,
  canRequestWithdrawal,
  activeWithdrawal,
}: FormalWithdrawalWizardProps) {
  const { t, i18n } = useTranslation(["portal", "common"]);
  const queryClient = useQueryClient();
  const inputRef = useRef<HTMLInputElement>(null);
  const documentFrameRef = useRef<HTMLIFrameElement>(null);
  const [open, setOpen] = useState(false);
  const [documentHtml, setDocumentHtml] = useState<string | null>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [fileError, setFileError] = useState<string | null>(null);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [resubmitReason, setResubmitReason] = useState("");
  const schema = useMemo(
    () =>
      withdrawalReasonSchema({
        required: t("withdrawal.validation.reasonRequired"),
        min: t("withdrawal.validation.reasonMin"),
        max: t("withdrawal.validation.reasonMax"),
        confirmation: t("withdrawal.validation.confirmationRequired"),
      }),
    [t],
  );
  const form = useForm<WithdrawalReasonValues>({
    resolver: zodResolver(schema),
    defaultValues: { reason: "", confirmed: false },
  });
  const withdrawalQuery = useQuery({
    queryKey: portalQueryKeys.reportWithdrawal(registrationNumber),
    queryFn: () => getPortalFormalWithdrawal(registrationNumber),
    enabled: open && activeWithdrawal !== null,
    retry: false,
  });

  async function invalidateWithdrawalState() {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: portalQueryKeys.report(registrationNumber) }),
      queryClient.invalidateQueries({ queryKey: portalQueryKeys.reportsRoot() }),
      queryClient.invalidateQueries({ queryKey: portalQueryKeys.summary() }),
      queryClient.invalidateQueries({
        queryKey: portalQueryKeys.reportWithdrawal(registrationNumber),
      }),
      queryClient.invalidateQueries({
        queryKey: portalQueryKeys.reportTimeline(registrationNumber),
      }),
      queryClient.invalidateQueries({
        queryKey: portalQueryKeys.reportHandlingProgress(registrationNumber),
      }),
      queryClient.invalidateQueries({
        queryKey: portalQueryKeys.reportEvidenceFiles(registrationNumber),
      }),
      queryClient.invalidateQueries({ queryKey: ["dashboard"] }),
      queryClient.invalidateQueries({ queryKey: ["operations"] }),
    ]);
  }

  function cacheWithdrawal(data: FormalWithdrawalDetail) {
    queryClient.setQueryData(portalQueryKeys.reportWithdrawal(registrationNumber), data);
  }

  function handleMutationError(error: unknown, fallback: string) {
    if (error instanceof ApiError && error.status === 409) {
      toast.error(t("withdrawal.staleError"), {
        action: {
          label: t("withdrawal.refreshAction"),
          onClick: () => void invalidateWithdrawalState(),
        },
      });
      return;
    }

    toast.error(apiErrorMessage(error, fallback));
  }

  const createMutation = useMutation({
    mutationFn: (values: WithdrawalReasonValues) =>
      createPortalFormalWithdrawal(registrationNumber, values.reason.trim()),
    onSuccess: async (data) => {
      cacheWithdrawal(data);
      form.reset();
      await invalidateWithdrawalState();
      toast.success(t("withdrawal.createSuccess"));
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      handleMutationError(error, t("withdrawal.createError"));
    },
  });
  const documentMutation = useMutation({
    mutationFn: (publicId: string) => getPortalWithdrawalDraftDocument(publicId),
    onSuccess: (html) => {
      setDocumentHtml(html);
      toast.success(t("withdrawal.documentLoaded"));
    },
    onError: (error) => handleMutationError(error, t("withdrawal.documentError")),
  });
  const uploadMutation = useMutation({
    mutationFn: ({
      publicId,
      file,
      lockVersion,
    }: {
      publicId: string;
      file: File;
      lockVersion: number;
    }) => uploadPortalWithdrawalSignedDocument(publicId, file, lockVersion, setUploadProgress),
    onSuccess: async (data) => {
      cacheWithdrawal(data);
      clearSelectedFile();
      await invalidateWithdrawalState();
      toast.success(t("withdrawal.uploadSuccess"));
    },
    onError: (error) => {
      setUploadProgress(0);
      handleMutationError(error, t("withdrawal.uploadError"));
    },
  });
  const submitMutation = useMutation({
    mutationFn: ({ publicId, lockVersion }: { publicId: string; lockVersion: number }) =>
      submitPortalFormalWithdrawal(publicId, lockVersion),
    onSuccess: async (data) => {
      cacheWithdrawal(data);
      await invalidateWithdrawalState();
      toast.success(t("withdrawal.submitSuccess"));
    },
    onError: (error) => handleMutationError(error, t("withdrawal.submitError")),
  });
  const cancelMutation = useMutation({
    mutationFn: ({ publicId, lockVersion }: { publicId: string; lockVersion: number }) =>
      cancelPortalFormalWithdrawal(publicId, lockVersion),
    onSuccess: async () => {
      queryClient.removeQueries({ queryKey: portalQueryKeys.reportWithdrawal(registrationNumber) });
      await invalidateWithdrawalState();
      resetLocalState();
      setOpen(false);
      toast.success(t("withdrawal.cancelSuccess"));
    },
    onError: (error) => handleMutationError(error, t("withdrawal.cancelError")),
  });
  const resubmitMutation = useMutation({
    mutationFn: ({ publicId, lockVersion }: { publicId: string; lockVersion: number }) =>
      resubmitPortalFormalWithdrawal(publicId, resubmitReason.trim(), lockVersion),
    onSuccess: async (data) => {
      cacheWithdrawal(data);
      setResubmitReason("");
      setDocumentHtml(null);
      clearSelectedFile();
      await invalidateWithdrawalState();
      toast.success(t("withdrawal.resubmitSuccess"));
    },
    onError: (error) => handleMutationError(error, t("withdrawal.resubmitError")),
  });

  const withdrawal = withdrawalQuery.data ?? createMutation.data ?? null;
  const effectiveStatus = withdrawal?.status ?? activeWithdrawal?.status ?? null;
  const effectiveLockVersion = withdrawal?.lock_version ?? activeWithdrawal?.lock_version ?? null;
  const effectiveReference =
    withdrawal?.withdrawal_reference ?? activeWithdrawal?.withdrawal_reference ?? null;
  const effectiveCapabilities = withdrawal?.capabilities ?? activeWithdrawal?.capabilities ?? null;
  const effectiveAttachment =
    withdrawal?.latest_attachment ?? activeWithdrawal?.latest_attachment ?? null;
  const effectiveAttachments =
    withdrawal?.attachments ??
    activeWithdrawal?.attachments ??
    (effectiveAttachment ? [effectiveAttachment] : []);
  const currentStep = withdrawalStep(effectiveStatus, effectiveAttachment !== null);
  const isBusy =
    createMutation.isPending ||
    documentMutation.isPending ||
    uploadMutation.isPending ||
    submitMutation.isPending ||
    cancelMutation.isPending ||
    resubmitMutation.isPending;

  function resetLocalState() {
    form.reset();
    setDocumentHtml(null);
    setResubmitReason("");
    clearSelectedFile();
    createMutation.reset();
    documentMutation.reset();
    uploadMutation.reset();
    submitMutation.reset();
    cancelMutation.reset();
    resubmitMutation.reset();
  }

  function handleOpenChange(nextOpen: boolean) {
    if (isBusy) return;
    setOpen(nextOpen);
    if (!nextOpen) resetLocalState();
  }

  function clearSelectedFile() {
    setSelectedFile(null);
    setFileError(null);
    setUploadProgress(0);
    if (inputRef.current) inputRef.current.value = "";
  }

  function handleFile(file: File | null) {
    if (!file) {
      clearSelectedFile();
      return;
    }

    const validationKey = validateWithdrawalDocumentFile(file);
    if (validationKey) {
      clearSelectedFile();
      setFileError(t(`withdrawal.fileValidation.${validationKey}`));
      return;
    }

    setSelectedFile(file);
    setFileError(null);
  }

  function openPrintableDocument() {
    const printWindow = documentFrameRef.current?.contentWindow;
    if (!documentHtml || !printWindow) {
      toast.error(t("withdrawal.documentError"));
      return;
    }

    try {
      printWindow.focus();
      printWindow.print();
    } catch {
      toast.error(t("withdrawal.documentError"));
    }
  }

  const hasActiveRequest = activeWithdrawal !== null || withdrawal !== null;
  const stateUnavailable =
    activeWithdrawal !== null && withdrawalQuery.data === undefined &&
    (withdrawalQuery.isPending || withdrawalQuery.isError);

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger asChild>
        <Button type="button" variant="outline" className="min-h-11">
          <RotateCcw className="h-4 w-4" aria-hidden="true" />
          {hasActiveRequest
            ? t("withdrawal.continueTrigger")
            : t("withdrawal.createTrigger")}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>{t("withdrawal.title")}</DialogTitle>
          <DialogDescription>{t("withdrawal.description")}</DialogDescription>
        </DialogHeader>

        <WizardProgress step={currentStep} />

        {activeWithdrawal && withdrawalQuery.isPending && (
          <div className="flex min-h-32 items-center justify-center" aria-live="polite">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" aria-hidden="true" />
            <span className="sr-only">{t("withdrawal.loading")}</span>
          </div>
        )}

        {activeWithdrawal && withdrawalQuery.isError && (
          <div role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 p-4">
            <p className="text-sm">{t("withdrawal.loadError")}</p>
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="mt-3"
              onClick={() => withdrawalQuery.refetch()}
            >
              {t("common:retry")}
            </Button>
          </div>
        )}

        {!hasActiveRequest && canRequestWithdrawal && (
          <Form {...form}>
            <form
              className="space-y-5"
              onSubmit={form.handleSubmit((values) => {
                if (!createMutation.isPending) createMutation.mutate(values);
              })}
            >
              <FormField
                control={form.control}
                name="reason"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("withdrawal.reasonLabel")}</FormLabel>
                    <FormControl>
                      <Textarea
                        {...field}
                        rows={7}
                        maxLength={2000}
                        disabled={createMutation.isPending}
                        placeholder={t("withdrawal.reasonPlaceholder")}
                      />
                    </FormControl>
                    <FormDescription>
                      {t("withdrawal.reasonHelp", { count: field.value.length })}
                    </FormDescription>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="confirmed"
                render={({ field }) => (
                  <FormItem className="rounded-md border p-4">
                    <div className="flex items-start gap-3">
                      <FormControl>
                        <Checkbox
                          checked={field.value}
                          onCheckedChange={(checked) => field.onChange(checked === true)}
                          disabled={createMutation.isPending}
                        />
                      </FormControl>
                      <div className="space-y-1">
                        <FormLabel className="font-normal">
                          {t("withdrawal.confirmationLabel")}
                        </FormLabel>
                        <FormMessage />
                      </div>
                    </div>
                  </FormItem>
                )}
              />
              {createMutation.isError && (
                <p role="alert" className="text-sm text-destructive">
                  {apiErrorMessage(createMutation.error, t("withdrawal.createError"))}
                </p>
              )}
              <Button type="submit" disabled={createMutation.isPending}>
                {createMutation.isPending && (
                  <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                )}
                {t("withdrawal.createAction")}
              </Button>
            </form>
          </Form>
        )}

        {!stateUnavailable && effectiveReference && effectiveCapabilities && (
          <div className="space-y-5">
            <StatusPanel
              status={effectiveStatus}
              submittedAt={withdrawal?.submitted_at ?? activeWithdrawal?.submitted_at ?? null}
            />

            {effectiveStatus === "rejected" && (
              <section className="space-y-4 rounded-lg border border-destructive/30 bg-destructive/5 p-4" aria-labelledby="withdrawal-rejected-title">
                <div>
                  <h3 id="withdrawal-rejected-title" className="font-medium">{t("withdrawal.rejectedTitle")}</h3>
                  <p className="mt-2 whitespace-pre-wrap text-sm">
                    {withdrawal?.rejection_reason ?? activeWithdrawal?.rejection_reason ?? t("withdrawal.rejectedReasonUnavailable")}
                  </p>
                </div>
                {effectiveCapabilities.can_resubmit && (
                  <div className="space-y-3">
                    <label htmlFor={`withdrawal-resubmit-${registrationNumber}`} className="text-sm font-medium">{t("withdrawal.resubmitReasonLabel")}</label>
                    <Textarea
                      id={`withdrawal-resubmit-${registrationNumber}`}
                      rows={6}
                      maxLength={2000}
                      value={resubmitReason}
                      onChange={(event) => setResubmitReason(event.target.value)}
                      placeholder={t("withdrawal.reasonPlaceholder")}
                      disabled={resubmitMutation.isPending}
                    />
                    <p className="text-xs text-muted-foreground">{t("withdrawal.reasonHelp", { count: resubmitReason.trim().length })}</p>
                    <Button
                      type="button"
                      disabled={resubmitReason.trim().length < 20 || resubmitReason.trim().length > 2000 || effectiveLockVersion === null || resubmitMutation.isPending}
                      onClick={() => effectiveLockVersion !== null && resubmitMutation.mutate({ publicId: effectiveReference, lockVersion: effectiveLockVersion })}
                    >
                      {resubmitMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <RotateCcw className="h-4 w-4" />}
                      {t("withdrawal.resubmitAction")}
                    </Button>
                  </div>
                )}
                {!effectiveCapabilities.can_resubmit && (
                  <p className="text-sm text-muted-foreground">{t("withdrawal.resubmitUnavailable")}</p>
                )}
              </section>
            )}

            {effectiveCapabilities.can_view_draft && (
              <section className="space-y-3 rounded-lg border p-4" aria-labelledby="withdrawal-draft-title">
                <div>
                  <h3 id="withdrawal-draft-title" className="font-medium">
                    {t("withdrawal.documentTitle")}
                  </h3>
                  <p className="mt-1 text-sm text-muted-foreground">
                    {t("withdrawal.documentDescription")}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    disabled={documentMutation.isPending}
                    onClick={() => documentMutation.mutate(effectiveReference)}
                  >
                    {documentMutation.isPending ? (
                      <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                    ) : (
                      <FileText className="h-4 w-4" aria-hidden="true" />
                    )}
                    {t("withdrawal.viewDraft")}
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    disabled={!documentHtml}
                    onClick={openPrintableDocument}
                  >
                    <Printer className="h-4 w-4" aria-hidden="true" />
                    {t("withdrawal.printDraft")}
                  </Button>
                </div>
                {documentHtml && (
                  <iframe
                    ref={documentFrameRef}
                    title={t("withdrawal.documentFrameTitle")}
                    srcDoc={documentHtml}
                    sandbox="allow-modals allow-same-origin"
                    className="aspect-[210/297] w-full rounded-md border bg-white"
                  />
                )}
              </section>
            )}

            {(effectiveCapabilities.can_upload_document || effectiveAttachment) && (
              <section className="space-y-4 rounded-lg border p-4" aria-labelledby="withdrawal-upload-title">
                <div>
                  <h3 id="withdrawal-upload-title" className="font-medium">
                    {t("withdrawal.uploadTitle")}
                  </h3>
                  <p className="mt-1 text-sm text-muted-foreground">
                    {t("withdrawal.uploadDescription")}
                  </p>
                </div>

                {effectiveAttachments.length > 0 && (
                  <div className="space-y-2" aria-labelledby="withdrawal-document-history">
                    <p id="withdrawal-document-history" className="text-sm font-medium">
                      {t("withdrawal.documentHistory")}
                    </p>
                    {effectiveAttachments.map((attachment) => {
                      const isLatest =
                        attachment.attachment_reference === effectiveAttachment?.attachment_reference;

                      return (
                        <div
                          key={attachment.attachment_reference}
                          className="flex flex-col gap-3 rounded-md bg-muted/40 p-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                          <div className="min-w-0">
                            <p className="font-medium">
                              {isLatest
                                ? t("withdrawal.latestVersion", { version: attachment.version })
                                : t("withdrawal.previousVersion", { version: attachment.version })}
                            </p>
                            <p className="break-words text-sm text-muted-foreground">
                              {attachment.mime_type} · {formatBytes(attachment.size, i18n.language)}
                            </p>
                          </div>
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="self-start sm:self-auto"
                            aria-label={t("withdrawal.downloadVersion", { version: attachment.version })}
                            onClick={() =>
                              downloadPortalWithdrawalSignedDocument(
                                effectiveReference,
                                attachment.attachment_reference,
                              ).catch((error: unknown) =>
                                toast.error(apiErrorMessage(error, t("withdrawal.downloadError"))),
                              )
                            }
                          >
                            <Download className="h-4 w-4" aria-hidden="true" />
                            {t("withdrawal.download")}
                          </Button>
                        </div>
                      );
                    })}
                  </div>
                )}

                {effectiveCapabilities.can_upload_document && (
                  <div className="space-y-3">
                    <label
                      htmlFor={`withdrawal-document-${registrationNumber}`}
                      className="block text-sm font-medium"
                    >
                      {t("withdrawal.chooseFile")}
                    </label>
                    <input
                      ref={inputRef}
                      id={`withdrawal-document-${registrationNumber}`}
                      type="file"
                      accept={WITHDRAWAL_DOCUMENT_ACCEPT}
                      className="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-2 file:font-medium"
                      disabled={uploadMutation.isPending}
                      aria-describedby={
                        fileError ? "withdrawal-file-error" : "withdrawal-file-help"
                      }
                      onChange={(event) => handleFile(event.target.files?.[0] ?? null)}
                    />
                    <p id="withdrawal-file-help" className="text-xs text-muted-foreground">
                      {t("withdrawal.uploadHelp")}
                    </p>
                    {fileError && (
                      <p id="withdrawal-file-error" role="alert" className="text-sm text-destructive">
                        {fileError}
                      </p>
                    )}
                    {selectedFile && (
                      <p className="break-all text-sm">
                        {t("withdrawal.selectedFile", { name: selectedFile.name })}
                      </p>
                    )}
                    {uploadMutation.isPending && (
                      <div className="space-y-1" aria-live="polite">
                        <Progress value={uploadProgress} />
                        <p className="text-xs text-muted-foreground">
                          {t("withdrawal.uploadProgress", { progress: uploadProgress })}
                        </p>
                      </div>
                    )}
                    <Button
                      type="button"
                      disabled={
                        !selectedFile || effectiveLockVersion === null || uploadMutation.isPending
                      }
                      onClick={() => {
                        if (selectedFile && effectiveLockVersion !== null) {
                          uploadMutation.mutate({
                            publicId: effectiveReference,
                            file: selectedFile,
                            lockVersion: effectiveLockVersion,
                          });
                        }
                      }}
                    >
                      {uploadMutation.isPending ? (
                        <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                      ) : (
                        <Upload className="h-4 w-4" aria-hidden="true" />
                      )}
                      {effectiveAttachment
                        ? t("withdrawal.replaceDocument")
                        : t("withdrawal.uploadAction")}
                    </Button>
                  </div>
                )}
              </section>
            )}

            {effectiveStatus === "waiting_document" && (
              <section className="space-y-3 rounded-lg border p-4" aria-labelledby="withdrawal-submit-title">
                <div>
                  <h3 id="withdrawal-submit-title" className="font-medium">
                    {t("withdrawal.submitTitle")}
                  </h3>
                  <p className="mt-1 text-sm text-muted-foreground">
                    {t("withdrawal.submitDescription")}
                  </p>
                </div>
                <Button
                  type="button"
                  disabled={
                    !effectiveCapabilities.can_submit ||
                    effectiveLockVersion === null ||
                    submitMutation.isPending
                  }
                  onClick={() => {
                    if (effectiveLockVersion !== null) {
                      submitMutation.mutate({
                        publicId: effectiveReference,
                        lockVersion: effectiveLockVersion,
                      });
                    }
                  }}
                >
                  {submitMutation.isPending ? (
                    <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                  ) : (
                    <Send className="h-4 w-4" aria-hidden="true" />
                  )}
                  {t("withdrawal.submitAction")}
                </Button>
                {!effectiveAttachment && (
                  <p className="text-sm text-muted-foreground">
                    {t("withdrawal.submitNeedsDocument")}
                  </p>
                )}
              </section>
            )}

            {effectiveCapabilities.can_cancel_request && (
              <AlertDialog>
                <AlertDialogTrigger asChild>
                    <Button
                      type="button"
                      variant="destructive"
                      disabled={effectiveLockVersion === null || cancelMutation.isPending}
                    >
                    {t("withdrawal.cancelTrigger")}
                  </Button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                  <AlertDialogHeader>
                    <AlertDialogTitle>{t("withdrawal.cancelTitle")}</AlertDialogTitle>
                    <AlertDialogDescription>
                      {t("withdrawal.cancelDescription")}
                    </AlertDialogDescription>
                  </AlertDialogHeader>
                  <AlertDialogFooter>
                    <AlertDialogCancel>{t("common:back")}</AlertDialogCancel>
                    <AlertDialogAction
                      disabled={cancelMutation.isPending}
                      onClick={() => {
                        if (effectiveLockVersion !== null) {
                          cancelMutation.mutate({
                            publicId: effectiveReference,
                            lockVersion: effectiveLockVersion,
                          });
                        }
                      }}
                    >
                      {cancelMutation.isPending && (
                        <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                      )}
                      {t("withdrawal.cancelAction")}
                    </AlertDialogAction>
                  </AlertDialogFooter>
                </AlertDialogContent>
              </AlertDialog>
            )}
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}

function WizardProgress({ step }: { step: number }) {
  const { t } = useTranslation(["portal"]);

  return (
    <ol className="grid grid-cols-5 gap-1" aria-label={t("withdrawal.progressLabel")}>
      {Array.from({ length: 5 }, (_, index) => index + 1).map((item) => (
        <li key={item} className="min-w-0 text-center">
          <div
            className={
              item <= step
                ? "mx-auto flex h-8 w-8 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground"
                : "mx-auto flex h-8 w-8 items-center justify-center rounded-full bg-muted text-xs text-muted-foreground"
            }
            aria-current={item === step ? "step" : undefined}
          >
            {item}
          </div>
          <span className="mt-1 hidden text-[11px] text-muted-foreground sm:block">
            {t(`withdrawal.steps.${item}`)}
          </span>
        </li>
      ))}
    </ol>
  );
}

function StatusPanel({
  status,
  submittedAt,
}: {
  status: string | null;
  submittedAt: string | null;
}) {
  const { t, i18n } = useTranslation(["portal"]);
  const pending = status === "pending_review";

  return (
    <div
      className={
        pending
          ? "rounded-lg border border-warning/30 bg-warning/10 p-4"
          : "rounded-lg border bg-muted/30 p-4"
      }
    >
      <div className="flex flex-wrap items-center gap-2">
        <FileCheck2 className="h-5 w-5" aria-hidden="true" />
        <Badge variant="outline">{t(`withdrawal.status.${status ?? "draft"}`)}</Badge>
      </div>
      {pending && (
        <div className="mt-3 space-y-1 text-sm">
          <p>{t("withdrawal.pendingDocumentReceived")}</p>
          <p>{t("withdrawal.pendingCasePaused")}</p>
          {submittedAt && (
            <p>
              {t("withdrawal.pendingSubmittedAt", {
                value: new Intl.DateTimeFormat(i18n.language, {
                  dateStyle: "medium",
                  timeStyle: "short",
                }).format(new Date(submittedAt)),
              })}
            </p>
          )}
        </div>
      )}
    </div>
  );
}

function withdrawalStep(status: string | null, hasAttachment: boolean) {
  if (["pending_review", "approved", "rejected"].includes(status ?? "")) return 5;
  if (status === "waiting_document" && hasAttachment) return 4;
  if (status === "waiting_document") return 3;
  if (status === "draft") return 2;
  return 1;
}

function formatBytes(bytes: number, language: string) {
  return `${new Intl.NumberFormat(language, { maximumFractionDigits: 1 }).format(bytes / 1024 / 1024)} MB`;
}
