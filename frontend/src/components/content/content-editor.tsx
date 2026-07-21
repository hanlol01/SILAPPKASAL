import {
  AlertCircle,
  Eye,
  FileText,
  ImageOff,
  Loader2,
  Paperclip,
  Save,
  Send,
  Smartphone,
  Trash2,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useBlocker } from "@tanstack/react-router";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";

import { ContentDocumentPreview } from "@/components/content/content-document-preview";
import { StructuredDocumentEditor } from "@/components/content/structured-document-editor";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { Textarea } from "@/components/ui/textarea";
import { useAuth } from "@/hooks/use-auth";
import { ApiError } from "@/lib/api-client";
import { documentHasText, documentHasUnsafeLink } from "@/lib/content-document";
import {
  contentManagementKeys,
  createManagedContent,
  getConsultationOptions,
  getContentCategories,
  removeContentAttachment,
  submitManagedContent,
  updateManagedContent,
  uploadContentPdf,
  type ContentPayload,
  type ContentType,
  type DocumentNode,
  type ManagedContentDetail,
} from "@/lib/content-management-api";
import { apiErrorMessage } from "@/lib/form-errors";
import { cn } from "@/lib/utils";

interface Props {
  contentType: ContentType;
  detail?: ManagedContentDetail;
  onBack: () => void;
  onSaved: (publicId: string) => void;
}

interface EditorState {
  sectionCode: string;
  categoryPublicId: string;
  title: string;
  excerpt: string;
  document: DocumentNode | null;
  consultationCtaPublicId: string;
  question: string;
  answerDocument: DocumentNode | null;
  displayOrder: string;
  serviceName: string;
  description: string;
  email: string;
  phone: string;
  whatsapp: string;
  officeAddress: string;
  operatingHours: string;
  emergencyAvailable: boolean;
  appointmentUrl: string;
  actionLabel: string;
  iconCode: string;
  sortOrder: string;
  isActive: boolean;
  verificationDate: string;
  verifiedOwner: string;
}

export function ContentEditor({ contentType, detail, onBack, onSaved }: Props) {
  const { t, i18n } = useTranslation(["content", "common"]);
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [state, setState] = useState<EditorState>(() => initialState(contentType, detail));
  const [dirty, setDirty] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [previewOpen, setPreviewOpen] = useState(false);
  const [mobilePreview, setMobilePreview] = useState(false);
  const [confirmLeave, setConfirmLeave] = useState(false);
  const [uploadProgress, setUploadProgress] = useState<number | null>(null);
  const [emergencyConfirmed, setEmergencyConfirmed] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const detailRef = useRef(detail);
  detailRef.current = detail;
  const editable = detail ? detail.has_editable_version : true;
  const locale = i18n.resolvedLanguage?.startsWith("en") ? "en" : "id";
  const navigationBlocker = useBlocker({
    shouldBlockFn: () => dirty,
    enableBeforeUnload: dirty,
    withResolver: true,
  });

  useEffect(() => {
    setState(initialState(contentType, detailRef.current));
    setDirty(false);
    setErrors({});
  }, [contentType, detail?.public_id, detail?.version.public_id, detail?.lock_version]);

  const categoriesQuery = useQuery({
    queryKey: contentManagementKeys.categories(state.sectionCode),
    queryFn: () => getContentCategories(state.sectionCode),
    enabled: contentType !== "consultation" && Boolean(state.sectionCode),
  });
  const consultationOptionsQuery = useQuery({
    queryKey: contentManagementKeys.consultationOptions(),
    queryFn: getConsultationOptions,
    enabled: contentType === "article",
  });

  const invalidate = async (publicId?: string) => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: contentManagementKeys.lists() }),
      queryClient.invalidateQueries({ queryKey: contentManagementKeys.summary() }),
      ...(publicId
        ? [queryClient.invalidateQueries({ queryKey: contentManagementKeys.detail(publicId) })]
        : []),
    ]);
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      const validation = validate(state, contentType, emergencyConfirmed, t);
      if (Object.keys(validation).length) {
        setErrors(validation);
        throw new ClientValidationError();
      }
      setErrors({});
      const payload = payloadFromState(
        state,
        contentType,
        user?.university_id,
        detail?.lock_version,
      );
      return detail
        ? updateManagedContent(detail.version.public_id, payload)
        : createManagedContent(payload);
    },
    onSuccess: async (result) => {
      setDirty(false);
      toast.success(t(detail ? "content:saved" : "content:created"));
      await invalidate(result.public_id);
      onSaved(result.public_id);
    },
    onError: (error) => {
      if (error instanceof ClientValidationError) return;
      setErrors(apiFieldErrors(error));
      toast.error(apiErrorMessage(error, t("content:loadError")));
    },
  });

  const submitMutation = useMutation({
    mutationFn: async () => {
      if (!detail) throw new Error("Save the draft before submitting.");
      if (dirty) await saveMutation.mutateAsync();
      return submitManagedContent(detail.version.public_id);
    },
    onSuccess: async () => {
      setDirty(false);
      toast.success(t("content:submittedSuccess"));
      await invalidate(detail?.public_id);
      onBack();
    },
    onError: (error) => {
      setErrors(apiFieldErrors(error));
      toast.error(apiErrorMessage(error, t("content:loadError")));
    },
  });

  const uploadMutation = useMutation({
    mutationFn: (file: File) => {
      if (!detail) throw new Error("Save the draft before uploading attachments.");
      if (
        file.type !== "application/pdf" ||
        !file.name.toLowerCase().endsWith(".pdf") ||
        file.size < 1 ||
        file.size > 10 * 1024 * 1024
      ) {
        throw new ClientValidationError(t("content:validation.pdf"));
      }
      setUploadProgress(0);
      return uploadContentPdf(detail.version.public_id, file, setUploadProgress);
    },
    onSuccess: async () => {
      setUploadProgress(null);
      if (fileInputRef.current) fileInputRef.current.value = "";
      await invalidate(detail?.public_id);
    },
    onError: (error) => {
      setUploadProgress(null);
      toast.error(
        error instanceof ClientValidationError
          ? error.message
          : apiErrorMessage(error, t("content:loadError")),
      );
    },
  });

  const removeMutation = useMutation({
    mutationFn: removeContentAttachment,
    onSuccess: async () => {
      toast.success(t("content:attachmentRemoved"));
      await invalidate(detail?.public_id);
    },
    onError: (error) => toast.error(apiErrorMessage(error, t("content:loadError"))),
  });

  const set = <K extends keyof EditorState>(key: K, value: EditorState[K]) => {
    setState((current) => ({ ...current, [key]: value }));
    setDirty(true);
    setErrors((current) => ({ ...current, [key]: "" }));
  };
  const requestBack = () => (dirty ? setConfirmLeave(true) : onBack());

  return (
    <div className="space-y-5 pb-24">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Button variant="ghost" className="mb-2 -ml-3 min-h-11" onClick={requestBack}>
            {t("content:back")}
          </Button>
          <h1 className="text-2xl font-semibold">
            {detail ? detail.version.title : t(`content:${contentType}`)}
          </h1>
          <div className="mt-2 flex flex-wrap gap-2">
            <Badge variant="outline">{t(`content:${detail?.lifecycle_status ?? "draft"}`)}</Badge>
            <Badge variant="secondary">{t("content:campus")}</Badge>
            {detail?.version.requires_editorial_review && (
              <Badge variant="outline">{t("content:editorialReview")}</Badge>
            )}
          </div>
        </div>
        <Button variant="outline" className="min-h-11" onClick={() => setPreviewOpen(true)}>
          <Eye className="mr-2 h-4 w-4" />
          {t("content:preview")}
        </Button>
      </div>

      {detail?.review_feedback && (
        <Alert variant="destructive">
          <AlertCircle className="h-4 w-4" />
          <AlertTitle>{t("content:reviewFeedback")}</AlertTitle>
          <AlertDescription>{detail.review_feedback.reason}</AlertDescription>
        </Alert>
      )}
      {!editable && (
        <Alert>
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{t("content:readOnly")}</AlertDescription>
        </Alert>
      )}

      <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <Card>
          <CardHeader>
            <CardTitle>{t(`content:${contentType}`)}</CardTitle>
            <CardDescription>{t("content:subtitle")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-5">
            {contentType === "article" && (
              <ArticleFields
                state={state}
                set={set}
                editable={editable}
                errors={errors}
                categories={categoriesQuery.data ?? []}
                consultationOptions={consultationOptionsQuery.data ?? []}
                locale={locale}
              />
            )}
            {contentType === "faq" && (
              <FaqFields
                state={state}
                set={set}
                editable={editable}
                errors={errors}
                categories={categoriesQuery.data ?? []}
              />
            )}
            {contentType === "consultation" && (
              <ConsultationFields
                state={state}
                set={set}
                editable={editable}
                errors={errors}
                emergencyConfirmed={emergencyConfirmed}
                setEmergencyConfirmed={setEmergencyConfirmed}
              />
            )}
          </CardContent>
        </Card>

        <div className="space-y-5">
          <Alert>
            <ImageOff className="h-4 w-4" />
            <AlertDescription>{t("content:imageUnavailable")}</AlertDescription>
          </Alert>
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <Paperclip className="h-4 w-4" />
                {t("content:pdfTitle")}
              </CardTitle>
              <CardDescription>{t("content:pdfHelp")}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {detail?.version.attachments
                .filter((item) => item.purpose === "attachment")
                .map((attachment) => (
                  <div
                    key={attachment.public_id}
                    className="flex items-center gap-2 rounded-md border p-3 text-sm"
                  >
                    <FileText className="h-4 w-4 shrink-0" />
                    <span className="min-w-0 flex-1 truncate">{attachment.filename}</span>
                    {editable && (
                      <Button
                        size="icon"
                        variant="ghost"
                        className="h-11 w-11 text-destructive"
                        aria-label={`${t("content:remove")} ${attachment.filename}`}
                        disabled={removeMutation.isPending}
                        onClick={() => removeMutation.mutate(attachment.public_id)}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    )}
                  </div>
                ))}
              {detail &&
                detail.version.attachments.filter((item) => item.purpose === "attachment")
                  .length === 0 && (
                  <p className="text-sm text-muted-foreground">{t("content:noAttachments")}</p>
                )}
              {!detail && <p className="text-sm text-muted-foreground">{t("content:saveDraft")}</p>}
              {editable && detail && (
                <>
                  <Input
                    ref={fileInputRef}
                    className="sr-only"
                    id="content-pdf"
                    type="file"
                    accept="application/pdf,.pdf"
                    onChange={(event) => {
                      const file = event.target.files?.[0];
                      if (file) uploadMutation.mutate(file);
                    }}
                  />
                  <Button
                    type="button"
                    variant="outline"
                    className="min-h-11 w-full"
                    disabled={uploadMutation.isPending}
                    onClick={() => fileInputRef.current?.click()}
                  >
                    {uploadMutation.isPending ? (
                      <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    ) : (
                      <Paperclip className="mr-2 h-4 w-4" />
                    )}
                    {t("content:choosePdf")}
                  </Button>
                  {uploadProgress !== null && (
                    <div aria-live="polite">
                      <Progress value={uploadProgress} />
                      <p className="mt-1 text-xs text-muted-foreground">
                        {t("content:uploading", { percent: uploadProgress })}
                      </p>
                    </div>
                  )}
                </>
              )}
            </CardContent>
          </Card>
        </div>
      </div>

      {editable && (
        <div className="fixed inset-x-0 bottom-0 z-20 border-t bg-background/95 p-3 backdrop-blur md:left-[var(--sidebar-width)]">
          <div className="mx-auto flex max-w-6xl justify-end gap-2">
            <Button variant="outline" className="min-h-11" onClick={() => setPreviewOpen(true)}>
              <Eye className="mr-2 h-4 w-4" />
              {t("content:preview")}
            </Button>
            <Button
              className="min-h-11"
              disabled={saveMutation.isPending || submitMutation.isPending}
              onClick={() => saveMutation.mutate()}
            >
              {saveMutation.isPending ? (
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              ) : (
                <Save className="mr-2 h-4 w-4" />
              )}
              {t("content:saveDraft")}
            </Button>
            {detail && (
              <Button
                className="min-h-11"
                variant="secondary"
                disabled={
                  saveMutation.isPending ||
                  submitMutation.isPending ||
                  (detail.lifecycle_status === "revision_requested" && !dirty)
                }
                onClick={() => submitMutation.mutate()}
              >
                {submitMutation.isPending ? (
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                ) : (
                  <Send className="mr-2 h-4 w-4" />
                )}
                {t(
                  detail.lifecycle_status === "revision_requested"
                    ? "content:resubmit"
                    : "content:submit",
                )}
              </Button>
            )}
          </div>
        </div>
      )}

      <ContentPreviewDialog
        open={previewOpen}
        onOpenChange={setPreviewOpen}
        state={state}
        contentType={contentType}
        attachments={detail?.version.attachments ?? []}
        mobile={mobilePreview}
        setMobile={setMobilePreview}
        categoryName={
          categoriesQuery.data?.find((item) => item.public_id === state.categoryPublicId)?.name
        }
      />
      <AlertDialog
        open={confirmLeave || navigationBlocker.status === "blocked"}
        onOpenChange={(open) => {
          setConfirmLeave(open);
          if (!open && navigationBlocker.status === "blocked") navigationBlocker.reset();
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("content:unsavedTitle")}</AlertDialogTitle>
            <AlertDialogDescription>{t("content:unsavedDescription")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("content:stay")}</AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              onClick={() => {
                setDirty(false);
                if (navigationBlocker.status === "blocked") navigationBlocker.proceed();
                else onBack();
              }}
            >
              {t("content:leave")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

type Setter = <K extends keyof EditorState>(key: K, value: EditorState[K]) => void;

function ArticleFields({
  state,
  set,
  editable,
  errors,
  categories,
  consultationOptions,
  locale,
}: {
  state: EditorState;
  set: Setter;
  editable: boolean;
  errors: Record<string, string>;
  categories: Array<{ public_id: string; name: string; section_code?: string | null }>;
  consultationOptions: Array<{ public_id: string; service_name: string; scope: string }>;
  locale: "id" | "en";
}) {
  const { t } = useTranslation("content");
  return (
    <>
      <div className="grid gap-4 md:grid-cols-2">
        <Field label={t("section")} error={errors.sectionCode}>
          <select
            className="h-11 w-full rounded-md border bg-background px-3"
            disabled={!editable}
            value={state.sectionCode}
            onChange={(event) => {
              set("sectionCode", event.target.value);
              set("categoryPublicId", "");
            }}
          >
            <option value="education">{locale === "en" ? "Education" : "Edukasi"}</option>
            <option value="policy">{locale === "en" ? "Policy" : "Seputar Kebijakan"}</option>
          </select>
        </Field>
        <Field label={t("category")} error={errors.categoryPublicId}>
          <select
            className="h-11 w-full rounded-md border bg-background px-3"
            disabled={!editable}
            value={state.categoryPublicId}
            onChange={(event) => set("categoryPublicId", event.target.value)}
          >
            <option value="">{t("selectCategory")}</option>
            {categories.map((category) => (
              <option key={category.public_id} value={category.public_id}>
                {category.name}
              </option>
            ))}
          </select>
        </Field>
      </div>
      <Field label={t("titleField")} error={errors.title}>
        <Input
          maxLength={255}
          disabled={!editable}
          value={state.title}
          onChange={(event) => set("title", event.target.value)}
        />
      </Field>
      <Field label={t("excerpt")} error={errors.excerpt}>
        <Textarea
          maxLength={1000}
          disabled={!editable}
          value={state.excerpt}
          onChange={(event) => set("excerpt", event.target.value)}
        />
      </Field>
      <Field label={t("body")} error={errors.document}>
        <StructuredDocumentEditor
          disabled={!editable}
          value={state.document}
          onChange={(value) => set("document", value)}
          error={errors.document}
        />
      </Field>
      <Field label={t("consultationCta")} error={errors.consultationCtaPublicId}>
        <select
          className="h-11 w-full rounded-md border bg-background px-3"
          disabled={!editable}
          value={state.consultationCtaPublicId}
          onChange={(event) => set("consultationCtaPublicId", event.target.value)}
        >
          <option value="">{t("noCta")}</option>
          {consultationOptions.map((item) => (
            <option key={item.public_id} value={item.public_id}>
              {item.service_name} ({t(item.scope)})
            </option>
          ))}
        </select>
      </Field>
    </>
  );
}

function FaqFields({
  state,
  set,
  editable,
  errors,
  categories,
}: {
  state: EditorState;
  set: Setter;
  editable: boolean;
  errors: Record<string, string>;
  categories: Array<{ public_id: string; name: string }>;
}) {
  const { t } = useTranslation("content");
  return (
    <>
      <Field label={t("category")}>
        <select
          className="h-11 w-full rounded-md border bg-background px-3"
          disabled={!editable}
          value={state.categoryPublicId}
          onChange={(event) => set("categoryPublicId", event.target.value)}
        >
          <option value="">{t("noCategory")}</option>
          {categories.map((category) => (
            <option key={category.public_id} value={category.public_id}>
              {category.name}
            </option>
          ))}
        </select>
      </Field>
      <Field label={t("question")} error={errors.question}>
        <Input
          maxLength={500}
          disabled={!editable}
          value={state.question}
          onChange={(event) => set("question", event.target.value)}
        />
      </Field>
      <Field label={t("answer")} error={errors.answerDocument}>
        <StructuredDocumentEditor
          faq
          disabled={!editable}
          value={state.answerDocument}
          onChange={(value) => set("answerDocument", value)}
          error={errors.answerDocument}
        />
      </Field>
      <Field label={t("displayOrder")}>
        <Input
          type="number"
          min={0}
          max={65535}
          disabled={!editable}
          value={state.displayOrder}
          onChange={(event) => set("displayOrder", event.target.value)}
        />
      </Field>
    </>
  );
}

function ConsultationFields({
  state,
  set,
  editable,
  errors,
  emergencyConfirmed,
  setEmergencyConfirmed,
}: {
  state: EditorState;
  set: Setter;
  editable: boolean;
  errors: Record<string, string>;
  emergencyConfirmed: boolean;
  setEmergencyConfirmed: (value: boolean) => void;
}) {
  const { t } = useTranslation("content");
  return (
    <>
      <Alert>
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>{t("noInventedContacts")}</AlertDescription>
      </Alert>
      <Field label={t("serviceName")} error={errors.serviceName}>
        <Input
          maxLength={200}
          disabled={!editable}
          value={state.serviceName}
          onChange={(event) => set("serviceName", event.target.value)}
        />
      </Field>
      <Field label={t("description")}>
        <Textarea
          maxLength={5000}
          disabled={!editable}
          value={state.description}
          onChange={(event) => set("description", event.target.value)}
        />
      </Field>
      <div className="grid gap-4 md:grid-cols-2">
        <Field label={t("email")} error={errors.email}>
          <Input
            type="email"
            disabled={!editable}
            value={state.email}
            onChange={(event) => set("email", event.target.value)}
          />
        </Field>
        <Field label={t("phone")} description={t("phoneHelp")}>
          <Input
            type="tel"
            inputMode="tel"
            disabled={!editable}
            value={state.phone}
            onChange={(event) => set("phone", event.target.value)}
          />
        </Field>
      </div>
      <div className="grid gap-4 md:grid-cols-2">
        <Field label={t("whatsapp")} description={t("phoneHelp")}>
          <Input
            type="tel"
            inputMode="tel"
            disabled={!editable}
            value={state.whatsapp}
            onChange={(event) => set("whatsapp", event.target.value)}
          />
        </Field>
        <Field label={t("appointmentUrl")} error={errors.appointmentUrl}>
          <Input
            type="url"
            disabled={!editable}
            value={state.appointmentUrl}
            onChange={(event) => set("appointmentUrl", event.target.value)}
          />
        </Field>
      </div>
      <Field label={t("officeAddress")}>
        <Textarea
          disabled={!editable}
          value={state.officeAddress}
          onChange={(event) => set("officeAddress", event.target.value)}
        />
      </Field>
      <Field label={t("operatingHours")}>
        <Textarea
          disabled={!editable}
          value={state.operatingHours}
          onChange={(event) => set("operatingHours", event.target.value)}
        />
      </Field>
      <div className="grid gap-4 md:grid-cols-2">
        <Field label={t("actionLabel")}>
          <Input
            disabled={!editable}
            value={state.actionLabel}
            onChange={(event) => set("actionLabel", event.target.value)}
          />
        </Field>
        <Field label={t("iconCode")}>
          <Input
            pattern="[A-Za-z0-9_-]+"
            disabled={!editable}
            value={state.iconCode}
            onChange={(event) => set("iconCode", event.target.value)}
          />
        </Field>
      </div>
      <div className="grid gap-4 md:grid-cols-3">
        <Field label={t("displayOrder")}>
          <Input
            type="number"
            min={0}
            max={65535}
            disabled={!editable}
            value={state.sortOrder}
            onChange={(event) => set("sortOrder", event.target.value)}
          />
        </Field>
        <Field label={t("verificationDate")}>
          <Input
            type="date"
            max={new Date().toISOString().slice(0, 10)}
            disabled={!editable}
            value={state.verificationDate}
            onChange={(event) => set("verificationDate", event.target.value)}
          />
        </Field>
        <Field label={t("verifiedOwner")}>
          <Input
            disabled={!editable}
            value={state.verifiedOwner}
            onChange={(event) => set("verifiedOwner", event.target.value)}
          />
        </Field>
      </div>
      <div className="space-y-3 rounded-lg border p-4">
        <CheckRow
          id="consultation-active"
          label={t("active")}
          checked={state.isActive}
          disabled={!editable}
          onChange={(value) => set("isActive", value)}
        />
        <CheckRow
          id="consultation-emergency"
          label={t("emergencyAvailable")}
          checked={state.emergencyAvailable}
          disabled={!editable}
          onChange={(value) => {
            set("emergencyAvailable", value);
            setEmergencyConfirmed(false);
          }}
        />
        {state.emergencyAvailable && (
          <CheckRow
            id="consultation-emergency-confirm"
            label={t("emergencyConfirm")}
            checked={emergencyConfirmed}
            disabled={!editable}
            onChange={setEmergencyConfirmed}
          />
        )}
        {errors.emergencyConfirmed && (
          <p role="alert" className="text-sm text-destructive">
            {errors.emergencyConfirmed}
          </p>
        )}
      </div>
    </>
  );
}

function Field({
  label,
  description,
  error,
  children,
}: {
  label: string;
  description?: string;
  error?: string;
  children: React.ReactNode;
}) {
  return (
    <fieldset className="min-w-0 space-y-2">
      <legend className="text-sm font-medium leading-none">{label}</legend>
      {children}
      {description && <p className="text-xs text-muted-foreground">{description}</p>}
      {error && (
        <p role="alert" className="text-sm text-destructive">
          {error}
        </p>
      )}
    </fieldset>
  );
}

function CheckRow({
  id,
  label,
  checked,
  disabled,
  onChange,
}: {
  id: string;
  label: string;
  checked: boolean;
  disabled: boolean;
  onChange: (value: boolean) => void;
}) {
  return (
    <div className="flex min-h-11 items-center gap-3">
      <Checkbox
        id={id}
        checked={checked}
        disabled={disabled}
        onCheckedChange={(value) => onChange(value === true)}
      />
      <Label htmlFor={id} className="cursor-pointer">
        {label}
      </Label>
    </div>
  );
}

function ContentPreviewDialog({
  open,
  onOpenChange,
  state,
  contentType,
  attachments,
  mobile,
  setMobile,
  categoryName,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  state: EditorState;
  contentType: ContentType;
  attachments: Array<{ public_id: string; filename: string }>;
  mobile: boolean;
  setMobile: (value: boolean) => void;
  categoryName?: string;
}) {
  const { t } = useTranslation("content");
  const title =
    contentType === "article"
      ? state.title
      : contentType === "faq"
        ? state.question
        : state.serviceName;
  const document = contentType === "article" ? state.document : state.answerDocument;
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[92vh] max-w-5xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t("draftPreview")}</DialogTitle>
          <DialogDescription>{t("notPublished")}</DialogDescription>
        </DialogHeader>
        <div className="flex justify-end gap-2">
          <Button
            size="sm"
            variant={!mobile ? "secondary" : "outline"}
            onClick={() => setMobile(false)}
          >
            {t("desktop")}
          </Button>
          <Button
            size="sm"
            variant={mobile ? "secondary" : "outline"}
            onClick={() => setMobile(true)}
          >
            <Smartphone className="mr-1 h-4 w-4" />
            {t("mobile")}
          </Button>
        </div>
        <article
          className={cn(
            "mx-auto w-full rounded-xl border bg-background p-5 shadow-sm",
            mobile ? "max-w-sm" : "max-w-3xl",
          )}
        >
          <div className="mb-5 space-y-3">
            {categoryName && <Badge>{categoryName}</Badge>}
            <h1 className="text-3xl font-bold tracking-tight">{title || "—"}</h1>
            {state.excerpt && <p className="text-lg text-muted-foreground">{state.excerpt}</p>}
            <p className="text-xs text-muted-foreground">{t("publicationPlaceholder")}</p>
          </div>
          {contentType === "consultation" ? (
            <div className="space-y-3 rounded-lg border p-4">
              <h2 className="text-xl font-semibold">{state.serviceName}</h2>
              <p>{state.description}</p>
              {state.email && <p>{state.email}</p>}
              {state.phone && <p>{state.phone}</p>}
              {state.whatsapp && <p>WhatsApp: {state.whatsapp}</p>}
              {state.operatingHours && <p>{state.operatingHours}</p>}
            </div>
          ) : (
            <ContentDocumentPreview document={document} />
          )}
          {attachments.length > 0 && (
            <section className="mt-6">
              <h2 className="text-lg font-semibold">{t("pdfTitle")}</h2>
              <ul className="mt-2 space-y-2">
                {attachments.map((item) => (
                  <li key={item.public_id} className="rounded border p-2 text-sm">
                    {item.filename}
                  </li>
                ))}
              </ul>
            </section>
          )}
        </article>
      </DialogContent>
    </Dialog>
  );
}

function initialState(contentType: ContentType, detail?: ManagedContentDetail): EditorState {
  const article = detail?.version.article;
  const faq = detail?.version.faq;
  const consultation = detail?.version.consultation;
  return {
    sectionCode: detail?.section.code ?? (contentType === "article" ? "education" : contentType),
    categoryPublicId: detail?.category?.public_id ?? "",
    title: detail?.version.title ?? "",
    excerpt: detail?.version.excerpt ?? "",
    document: article?.document ?? null,
    consultationCtaPublicId: article?.consultation_cta_public_id ?? "",
    question: faq?.question ?? "",
    answerDocument: faq?.answer_document ?? null,
    displayOrder: String(faq?.display_order ?? 0),
    serviceName: consultation?.service_name ?? "",
    description: consultation?.description ?? "",
    email: consultation?.email ?? "",
    phone: consultation?.phone_display ?? "",
    whatsapp: consultation?.whatsapp_display ?? "",
    officeAddress: consultation?.office_address ?? "",
    operatingHours: consultation?.operating_hours ?? "",
    emergencyAvailable: consultation?.emergency_available ?? false,
    appointmentUrl: consultation?.appointment_url ?? "",
    actionLabel: consultation?.action_label ?? "",
    iconCode: consultation?.icon_code ?? "",
    sortOrder: String(consultation?.sort_order ?? 0),
    isActive: consultation?.is_active ?? true,
    verificationDate: consultation?.verification_date ?? "",
    verifiedOwner: consultation?.verified_owner ?? "",
  };
}

function payloadFromState(
  state: EditorState,
  type: ContentType,
  universityId?: number | null,
  lockVersion?: number,
): ContentPayload {
  const common: ContentPayload = {
    content_type: type,
    section_code: state.sectionCode,
    category_public_id: state.categoryPublicId || null,
    scope: "campus",
    university_id: universityId ?? undefined,
    lock_version: lockVersion,
  };
  if (type === "article")
    return {
      ...common,
      title: state.title.trim(),
      excerpt: state.excerpt.trim() || null,
      document: state.document ?? undefined,
      consultation_cta_public_id: state.consultationCtaPublicId || null,
    };
  if (type === "faq")
    return {
      ...common,
      title: state.question.trim(),
      question: state.question.trim(),
      answer_document: state.answerDocument ?? undefined,
      display_order: Number(state.displayOrder || 0),
    };
  return {
    ...common,
    title: state.serviceName.trim(),
    service_name: state.serviceName.trim(),
    description: state.description.trim() || null,
    email: state.email.trim() || null,
    phone_display: state.phone.trim() || null,
    whatsapp_display: state.whatsapp.trim() || null,
    office_address: state.officeAddress.trim() || null,
    operating_hours: state.operatingHours.trim() || null,
    emergency_available: state.emergencyAvailable,
    appointment_url: state.appointmentUrl.trim() || null,
    action_label: state.actionLabel.trim() || null,
    icon_code: state.iconCode.trim() || null,
    sort_order: Number(state.sortOrder || 0),
    is_active: state.isActive,
    verification_date: state.verificationDate || null,
    verified_owner: state.verifiedOwner.trim() || null,
  };
}

function validate(
  state: EditorState,
  type: ContentType,
  emergencyConfirmed: boolean,
  t: ReturnType<typeof useTranslation>["t"],
): Record<string, string> {
  const errors: Record<string, string> = {};
  if (type === "article") {
    if (!state.title.trim()) errors.title = t("content:validation.required");
    if (!state.categoryPublicId) errors.categoryPublicId = t("content:validation.category");
    if (!documentHasText(state.document)) errors.document = t("content:validation.document");
    else if (documentHasUnsafeLink(state.document)) errors.document = t("content:validation.https");
  } else if (type === "faq") {
    if (!state.question.trim()) errors.question = t("content:validation.required");
    if (!documentHasText(state.answerDocument))
      errors.answerDocument = t("content:validation.document");
    else if (documentHasUnsafeLink(state.answerDocument))
      errors.answerDocument = t("content:validation.https");
  } else {
    if (!state.serviceName.trim()) errors.serviceName = t("content:validation.required");
    if (
      state.appointmentUrl &&
      (!state.appointmentUrl.startsWith("https://") || !isValidUrl(state.appointmentUrl))
    )
      errors.appointmentUrl = t("content:validation.https");
    if (state.emergencyAvailable && !emergencyConfirmed)
      errors.emergencyConfirmed = t("content:validation.emergency");
  }
  return errors;
}

function isValidUrl(value: string): boolean {
  try {
    return new URL(value).protocol === "https:";
  } catch {
    return false;
  }
}
function apiFieldErrors(error: unknown): Record<string, string> {
  const fieldNames: Record<string, string> = {
    section_code: "sectionCode",
    category_public_id: "categoryPublicId",
    consultation_cta_public_id: "consultationCtaPublicId",
    answer_document: "answerDocument",
    service_name: "serviceName",
    phone_display: "phone",
    whatsapp_display: "whatsapp",
    appointment_url: "appointmentUrl",
  };
  return error instanceof ApiError && error.errors
    ? Object.fromEntries(
        Object.entries(error.errors).map(([key, value]) => [
          fieldNames[key] ?? key,
          value[0] ?? error.message,
        ]),
      )
    : {};
}
class ClientValidationError extends Error {
  constructor(message = "Validation failed") {
    super(message);
  }
}
