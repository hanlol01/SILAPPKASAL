import {
  AlertCircle,
  ArrowLeft,
  Clock3,
  Eye,
  FileText,
  ImageUp,
  ImageOff,
  Loader2,
  Paperclip,
  Save,
  Send,
  Smartphone,
  Trash2,
} from "lucide-react";
import { lazy, Suspense, useCallback, useEffect, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useBlocker } from "@tanstack/react-router";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";

import { ContentDocumentPreview } from "@/components/content/content-document-preview";
import { ContentCategoryCombobox } from "@/components/content/content-category-combobox";
import { AuthenticatedContentCover } from "@/components/content/authenticated-content-cover";
import { CollapsibleDataCard } from "@/components/collapsible-data-card";
import type { ArticleEditorSaveStatus } from "@/components/content/article-wysiwyg-editor";
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { useAuth } from "@/hooks/use-auth";
import { ApiError } from "@/lib/api-client";
import {
  articleCategoryEditorState,
  articleCategoryWriteFields,
} from "@/lib/content-editor-category";
import { documentHasText, documentHasUnsafeLink } from "@/lib/content-document";
import { contentFieldName } from "@/lib/content-management-errors";
import {
  contentManagementKeys,
  createManagedContent,
  getContentManagementCapabilities,
  getContentCategories,
  removeContentAttachment,
  submitManagedContent,
  updateManagedContent,
  uploadContentPdf,
  uploadContentCover,
  uploadContentInlineImage,
  type ContentAttachment,
  type ContentPayload,
  type ContentType,
  type DocumentNode,
  type ManagedContentDetail,
} from "@/lib/content-management-api";
import { apiErrorMessage } from "@/lib/form-errors";
import { cn } from "@/lib/utils";

const ArticleWysiwygEditor = lazy(() =>
  import("@/components/content/article-wysiwyg-editor").then((module) => ({
    default: module.ArticleWysiwygEditor,
  })),
);

const DEFAULT_IMAGE_FORMATS = ["image/jpeg", "image/png", "image/webp"] as const;
const DEFAULT_COVER_MAX_BYTES = 5 * 1024 * 1024;
const DEFAULT_INLINE_IMAGE_MAX_BYTES = 10 * 1024 * 1024;
const DEFAULT_IMAGE_SOURCE_MAX_BYTES = 10 * 1024 * 1024;
const DEFAULT_ALT_TEXT_MAX_LENGTH = 500;
const IMAGE_EXTENSIONS_BY_MIME: Record<string, readonly string[]> = {
  "image/jpeg": ["jpg", "jpeg"],
  "image/png": ["png"],
  "image/webp": ["webp"],
};

interface Props {
  contentType: ContentType;
  detail?: ManagedContentDetail;
  scope?: "campus" | "global";
  onBack: () => void;
  onSaved: (publicId: string) => void;
}

interface EditorState {
  sectionCode: string;
  categoryPublicId: string | null;
  categoryName: string;
  title: string;
  excerpt: string;
  document: DocumentNode | null;
  coverAltText: string;
  question: string;
  answerDocument: DocumentNode | null;
  displayOrder: string;
  serviceName: string;
  description: string;
  serviceType: string;
  email: string;
  phone: string;
  whatsapp: string;
  officeAddress: string;
  operatingHours: string;
  procedure: string;
  confidentialityInfo: string;
  emergencyAvailable: boolean;
  appointmentUrl: string;
  actionLabel: string;
  iconCode: string;
  sortOrder: string;
  isActive: boolean;
  verificationDate: string;
  verifiedOwner: string;
}

export function ContentEditor({ contentType, detail, scope = "campus", onBack, onSaved }: Props) {
  const { t, i18n } = useTranslation(["content", "common"]);
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const sourceIdentity = `${contentType}:${detail?.public_id ?? "new"}:${detail?.version.public_id ?? "new"}`;
  const [state, setState] = useState<EditorState>(() => initialState(contentType, detail));
  const [dirty, setDirty] = useState(false);
  const [saveStatus, setSaveStatus] = useState<ArticleEditorSaveStatus>("pristine");
  const [articleDocumentCompatibility, setArticleDocumentCompatibility] = useState({
    sourceIdentity: "",
    compatible: false,
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [touchedFields, setTouchedFields] = useState<Set<keyof EditorState>>(new Set());
  const [saveAttempted, setSaveAttempted] = useState(false);
  const [previewOpen, setPreviewOpen] = useState(false);
  const [mobilePreview, setMobilePreview] = useState(false);
  const [confirmLeave, setConfirmLeave] = useState(false);
  const [uploadProgress, setUploadProgress] = useState<number | null>(null);
  const [emergencyConfirmed, setEmergencyConfirmed] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const coverInputRef = useRef<HTMLInputElement>(null);
  const detailRef = useRef(detail);
  const navigationAllowedRef = useRef(false);
  const sourceIdentityRef = useRef(sourceIdentity);
  detailRef.current = detail;
  const permissions = new Set(user?.permissions ?? []);
  const globalAuthor = scope === "global" && permissions.has("content.publish.global");
  const canCreate = globalAuthor || permissions.has("content.create.campus");
  const canUpdate = globalAuthor || permissions.has("content.update.own_campus");
  const canSubmit = globalAuthor || permissions.has("content.submit.own_campus");
  const canManageAttachments =
    globalAuthor || permissions.has("content.attachment.manage.own_campus");
  const editable = detail
    ? detail.archived_at === null && detail.has_editable_version && canUpdate
    : canCreate;
  const articleDocumentCompatible =
    contentType !== "article" ||
    (articleDocumentCompatibility.sourceIdentity === sourceIdentity &&
      articleDocumentCompatibility.compatible);
  const locale = i18n.resolvedLanguage?.startsWith("en") ? "en" : "id";
  const handleArticleDocumentCompatibility = useCallback(
    (compatible: boolean) => {
      setArticleDocumentCompatibility((current) => {
        if (current.sourceIdentity === sourceIdentity && current.compatible === compatible) {
          return current;
        }

        return { sourceIdentity, compatible };
      });
    },
    [sourceIdentity],
  );
  const navigationBlocker = useBlocker({
    shouldBlockFn: () => dirty && !navigationAllowedRef.current,
    enableBeforeUnload: dirty,
    withResolver: true,
  });

  useEffect(() => {
    if (dirty) return;
    const sourceChanged = sourceIdentityRef.current !== sourceIdentity;
    sourceIdentityRef.current = sourceIdentity;
    setState(initialState(contentType, detailRef.current));
    setErrors({});
    setTouchedFields(new Set());
    setSaveAttempted(false);
    if (sourceChanged) setSaveStatus("pristine");
  }, [
    contentType,
    detail?.public_id,
    detail?.version.public_id,
    detail?.lock_version,
    dirty,
    sourceIdentity,
  ]);

  const categoriesQuery = useQuery({
    queryKey: contentManagementKeys.categories(state.sectionCode),
    queryFn: () => getContentCategories(state.sectionCode),
    enabled: contentType === "faq" && Boolean(state.sectionCode),
  });
  const capabilitiesQuery = useQuery({
    queryKey: contentManagementKeys.capabilities(),
    queryFn: getContentManagementCapabilities,
    staleTime: 5 * 60 * 1000,
  });
  const imageFormats = capabilitiesQuery.data?.image_formats ?? [...DEFAULT_IMAGE_FORMATS];
  const coverMaxBytes =
    capabilitiesQuery.data?.cover_max_bytes ?? DEFAULT_COVER_MAX_BYTES;
  const inlineImageMaxBytes =
    capabilitiesQuery.data?.inline_image_max_bytes ?? DEFAULT_INLINE_IMAGE_MAX_BYTES;
  const imageSourceMaxBytes =
    capabilitiesQuery.data?.max_image_source_bytes ?? DEFAULT_IMAGE_SOURCE_MAX_BYTES;
  const altTextMaxLength =
    capabilitiesQuery.data?.alt_text_max_length ?? DEFAULT_ALT_TEXT_MAX_LENGTH;
  const imageAccept = imageAcceptValue(imageFormats);
  const imageFormatLabel = imageFormats.map(imageFormatName).join(", ");

  const invalidate = async (publicId?: string) => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: contentManagementKeys.lists() }),
      queryClient.invalidateQueries({ queryKey: contentManagementKeys.summary() }),
      ...(publicId
        ? [queryClient.invalidateQueries({ queryKey: contentManagementKeys.detail(publicId) })]
        : []),
    ]);
  };

  const handleMutationError = (error: unknown) => {
    if (contentType === "article") setSaveStatus("failed");
    setErrors(apiFieldErrors(error, contentType));
    if (error instanceof ApiError && error.errorCode === "content_stale_version") {
      toast.error(t("content:errors.stale"));
      void invalidate(detail?.public_id);
      return;
    }

    toast.error(apiErrorMessage(error, t("content:loadError")));
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (!articleDocumentCompatible) throw new ClientValidationError();
      setSaveAttempted(true);
      const validation = validate(state, contentType, emergencyConfirmed, t);
      if (Object.keys(validation).length) {
        setErrors(validation);
        if (contentType === "article") setSaveStatus("failed");
        throw new ClientValidationError();
      }
      setErrors({});
      const payload = payloadFromState(
        state,
        contentType,
        scope,
        user?.university_id,
        detail?.lock_version,
      );
      return detail
        ? updateManagedContent(detail.version.public_id, payload)
        : createManagedContent(payload);
    },
    onSuccess: async (result) => {
      toast.success(t(detail ? "content:saved" : "content:created"));
      await invalidate(result.public_id);
      navigationAllowedRef.current = true;
      setDirty(false);
      if (contentType === "article") setSaveStatus("saved");
      setTouchedFields(new Set());
      setSaveAttempted(false);
      onSaved(result.public_id);
    },
    onError: (error) => {
      if (error instanceof ClientValidationError) return;
      handleMutationError(error);
    },
  });

  const submitMutation = useMutation({
    mutationFn: async () => {
      if (!articleDocumentCompatible) throw new ClientValidationError();
      if (!detail) throw new Error("Save the draft before submitting.");
      let lockVersion = detail.lock_version;
      if (dirty) {
        const saved = await saveMutation.mutateAsync();
        lockVersion = saved.lock_version;
      }
      return submitManagedContent(detail.version.public_id, lockVersion);
    },
    onSuccess: async () => {
      navigationAllowedRef.current = true;
      setDirty(false);
      toast.success(t("content:submittedSuccess"));
      await invalidate(detail?.public_id);
      onBack();
    },
    onError: (error) => {
      handleMutationError(error);
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

  const coverMutation = useMutation({
    mutationFn: (file: File) => {
      if (!detail) throw new Error("Save the draft before uploading a cover.");
      if (!state.coverAltText.trim()) throw new ClientValidationError(t("content:validation.coverAlt"));
      if (
        !matchesImageCapability(file, imageFormats) ||
        file.size < 1 ||
        file.size > imageSourceMaxBytes
      ) {
        throw new ClientValidationError(t("content:validation.cover", {
          formats: imageFormatLabel,
          size: formatFileSize(imageSourceMaxBytes),
        }));
      }
      return uploadContentCover(detail.version.public_id, file, state.coverAltText.trim());
    },
    onSuccess: async () => {
      if (coverInputRef.current) coverInputRef.current.value = "";
      toast.success(t("content:coverUploaded"));
      await invalidate(detail?.public_id);
    },
    onError: (error) => toast.error(contentImageUploadError(error, t, imageSourceMaxBytes, coverMaxBytes)),
  });

  const inlineImageMutation = useMutation({
    mutationFn: ({ file, altText }: { file: File; altText: string }) => {
      if (!detail) throw new Error("Save the draft before uploading inline images.");
      return uploadContentInlineImage(detail.version.public_id, file, altText);
    },
    onSuccess: async () => {
      await invalidate(detail?.public_id);
    },
  });

  const removeMutation = useMutation({
    mutationFn: ({ publicId }: { publicId: string; purpose: ContentAttachment["purpose"] }) =>
      removeContentAttachment(publicId),
    onSuccess: async (_result, variables) => {
      if (variables.purpose === "cover") {
        setState((current) => ({ ...current, coverAltText: "" }));
      }
      toast.success(t("content:attachmentRemoved"));
      await invalidate(detail?.public_id);
    },
    onError: (error) => toast.error(apiErrorMessage(error, t("content:loadError"))),
  });

  const set = <K extends keyof EditorState>(key: K, value: EditorState[K]) => {
    setState((current) => ({ ...current, [key]: value }));
    navigationAllowedRef.current = false;
    setDirty(true);
    if (contentType === "article") setSaveStatus("dirty");
    setErrors((current) => ({ ...current, [key]: "" }));
  };
  const markTouched = (key: keyof EditorState) => {
    setTouchedFields((current) => new Set(current).add(key));
  };
  const handleCategoryBlur = () => {
    markTouched("categoryName");
    const categoryName = state.categoryName.trim();
    setErrors((current) => ({
      ...current,
      categoryName: !categoryName
        ? t("content:validation.category")
        : categoryName.length > 100
          ? t("content:validation.categoryLength")
          : !/[\p{L}\p{N}]/u.test(categoryName)
            ? t("content:validation.categoryFormat")
          : "",
    }));
  };
  const requestBack = () => (dirty ? setConfirmLeave(true) : onBack());

  return (
    <div className="min-w-0 space-y-5 pb-40 sm:pb-24">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Button variant="ghost" className="mb-2 -ml-3 min-h-11" onClick={requestBack}>
            <ArrowLeft className="mr-2 h-4 w-4" />
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
        <Button className="min-h-11" onClick={() => setPreviewOpen(true)}>
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
      {detail && detail.editorial_timeline.length > 0 && (
        <CollapsibleDataCard
          title={t("content:editorialTimeline")}
          description={t("content:editorialTimelineDescription")}
          icon={Clock3}
        >
            <ol className="space-y-4">
              {detail.editorial_timeline.map((event) => {
                const actor = event.actor.label === "central_team"
                  ? t("content:timelineCentralTeam")
                  : event.actor.label === "system"
                    ? t("content:timelineSystem")
                    : event.actor.name ?? t("content:timelineSystem");

                return (
                  <li key={event.public_id} className="border-l-2 border-muted pl-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <p className="font-medium">
                        {t(`content:timelineStates.${event.state}`, { defaultValue: event.state })}
                      </p>
                      <time className="text-xs text-muted-foreground" dateTime={event.timestamp}>
                        {new Intl.DateTimeFormat(locale === "en" ? "en-ID" : "id-ID", {
                          dateStyle: "medium",
                          timeStyle: "short",
                        }).format(new Date(event.timestamp))}
                      </time>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">{actor}</p>
                    {event.note && (
                      <p className="mt-2 whitespace-pre-wrap rounded-md bg-muted/60 p-3 text-sm">
                        {event.note}
                      </p>
                    )}
                  </li>
                );
              })}
            </ol>
            {detail.editorial_timeline_truncated && (
              <p className="mt-4 text-xs text-muted-foreground">{t("content:timelineTruncated")}</p>
            )}
        </CollapsibleDataCard>
      )}

      <div className="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <Card className="min-w-0">
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
                categoryError={saveAttempted || touchedFields.has("categoryName") ? errors.categoryName : undefined}
                onCategoryBlur={handleCategoryBlur}
                canManageCategories={permissions.has("content.category.govern") || permissions.has("content.category.manage.own_campus")}
                locale={locale}
                saveStatus={saveStatus}
                onDocumentCompatibilityChange={handleArticleDocumentCompatibility}
                imageFormats={imageFormats}
                imageMaxBytes={inlineImageMaxBytes}
                imageAltMaxLength={altTextMaxLength}
                onUploadImage={
                  editable &&
                  canManageAttachments &&
                  detail &&
                  capabilitiesQuery.data?.image_upload_available
                    ? (file, altText) =>
                        inlineImageMutation.mutateAsync({ file, altText })
                    : undefined
                }
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
          {contentType === "article" && !detail && (
            <Alert className="border-amber-300/70 bg-amber-50/80 text-amber-950 dark:border-amber-400/40 dark:bg-amber-950/30 dark:text-amber-100">
              <AlertCircle className="h-4 w-4" />
              <AlertTitle>{t("content:imageDraftRequiredTitle")}</AlertTitle>
              <AlertDescription>{t("content:imageDraftRequiredDescription")}</AlertDescription>
            </Alert>
          )}
          {contentType === "article" && !capabilitiesQuery.data?.image_upload_available && (
            <Alert><ImageOff className="h-4 w-4" /><AlertDescription>{t("content:imageUnavailable")}</AlertDescription></Alert>
          )}
          {contentType === "article" && capabilitiesQuery.data?.image_upload_available && (
            <Card>
              <CardHeader><CardTitle className="text-base">{t("content:coverTitle")}</CardTitle><CardDescription>{t("content:coverHelp", { formats: imageFormatLabel, size: formatFileSize(imageSourceMaxBytes) })}</CardDescription></CardHeader>
              <CardContent className="space-y-3">
                <Field label={t("content:coverAltText")} error={errors.coverAltText}><Input maxLength={altTextMaxLength} disabled={!editable} value={state.coverAltText} onChange={(event) => set("coverAltText", event.target.value)} /></Field>
                {detail?.version.article?.cover ? (
                  <ManagedCoverPreview
                    attachment={detail.version.article.cover}
                    canRemove={editable && canManageAttachments}
                    removing={removeMutation.isPending}
                    onRemove={() =>
                      removeMutation.mutate({
                        publicId: detail.version.article?.cover?.public_id ?? "",
                        purpose: "cover",
                      })
                    }
                  />
                ) : null}
                {editable && canManageAttachments && detail ? <><Input ref={coverInputRef} className="sr-only" id="content-cover" type="file" accept={imageAccept} onChange={(event) => { const file = event.target.files?.[0]; if (file) coverMutation.mutate(file); }} /><Button type="button" className="min-h-11 w-full" disabled={coverMutation.isPending} onClick={() => coverInputRef.current?.click()}>{coverMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <ImageUp className="mr-2 h-4 w-4" />}{t("content:chooseCover")}</Button></> : <p className="text-sm text-muted-foreground">{t("content:saveDraft")}</p>}
              </CardContent>
            </Card>
          )}
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
                    {editable && canManageAttachments && (
                      <Button
                        size="icon"
                        variant="ghost"
                        className="h-11 w-11 text-destructive"
                        aria-label={`${t("content:remove")} ${attachment.filename}`}
                        disabled={removeMutation.isPending}
                        onClick={() =>
                          removeMutation.mutate({
                            publicId: attachment.public_id,
                            purpose: attachment.purpose,
                          })
                        }
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
              {editable && canManageAttachments && detail && (
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
        <div className="fixed inset-x-0 bottom-0 z-20 border-t bg-background/95 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur md:left-[var(--sidebar-width)]">
          <div className="mx-auto grid max-w-6xl grid-cols-2 gap-2 sm:flex sm:justify-end">
            <Button
              className="min-h-11 w-full sm:w-auto"
              onClick={() => setPreviewOpen(true)}
            >
              <Eye className="mr-2 h-4 w-4" />
              {t("content:preview")}
            </Button>
            <Button
              className="min-h-11 w-full sm:w-auto"
              disabled={
                !articleDocumentCompatible || saveMutation.isPending || submitMutation.isPending
              }
              onClick={() => saveMutation.mutate()}
            >
              {saveMutation.isPending ? (
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              ) : (
                <Save className="mr-2 h-4 w-4" />
              )}
              {t("content:saveDraft")}
            </Button>
            {detail && canSubmit && (
              <Button
                className="col-span-2 min-h-11 w-full sm:col-auto sm:w-auto"
                disabled={
                  saveMutation.isPending ||
                  submitMutation.isPending ||
                  !articleDocumentCompatible ||
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
        attachments={
          detail?.version.attachments.filter((item) => item.purpose === "attachment") ?? []
        }
        media={detail?.version.attachments ?? []}
        mobile={mobilePreview}
        setMobile={setMobilePreview}
        categoryName={contentType === "article" ? state.categoryName : categoriesQuery.data?.find((item) => item.public_id === state.categoryPublicId)?.name}
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
                navigationAllowedRef.current = true;
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
  categoryError,
  onCategoryBlur,
  canManageCategories,
  locale,
  saveStatus,
  onDocumentCompatibilityChange,
  imageFormats,
  imageMaxBytes,
  imageAltMaxLength,
  onUploadImage,
}: {
  state: EditorState;
  set: Setter;
  editable: boolean;
  errors: Record<string, string>;
  categoryError?: string;
  onCategoryBlur: () => void;
  canManageCategories: boolean;
  locale: "id" | "en";
  saveStatus: ArticleEditorSaveStatus;
  onDocumentCompatibilityChange: (compatible: boolean) => void;
  imageFormats: string[];
  imageMaxBytes: number;
  imageAltMaxLength: number;
  onUploadImage?: (file: File, altText: string) => Promise<ContentAttachment>;
}) {
  const { t } = useTranslation("content");
  return (
    <>
      <div className="grid gap-4 md:grid-cols-2">
        <Field label={t("section")} error={errors.sectionCode}>
          <Select
            disabled={!editable}
            value={state.sectionCode}
            onValueChange={(value) => {
              set("sectionCode", value);
              set("categoryName", "");
              set("categoryPublicId", null);
            }}
          >
            <SelectTrigger className="h-11 w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="education">{locale === "en" ? "Education" : "Edukasi"}</SelectItem>
              <SelectItem value="policy">{locale === "en" ? "Policy" : "Seputar Kebijakan"}</SelectItem>
            </SelectContent>
          </Select>
        </Field>
        <Field label={t("category")} error={categoryError} description={t("categoryFreeTextHelp")}>
          <ContentCategoryCombobox
            section={state.sectionCode as "education" | "policy"}
            value={state.categoryName}
            onValueChange={(value) => {
              set("categoryName", value);
              set("categoryPublicId", null);
            }}
            disabled={!editable}
            allowCreate={canManageCategories}
            allowManage={canManageCategories}
            onBlur={onCategoryBlur}
          />
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
      <Field label={t("body")} description={t("editor.helper")} error={errors.document}>
        <Suspense
          fallback={
            <div
              className="flex min-h-72 items-center justify-center rounded-xl border bg-muted/20 text-sm text-muted-foreground"
              role="status"
            >
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              {t("editor.loading")}
            </div>
          }
        >
          <ArticleWysiwygEditor
            disabled={!editable}
            imageFormats={imageFormats}
            imageMaxBytes={imageMaxBytes}
            imageAltMaxLength={imageAltMaxLength}
            onCompatibilityChange={onDocumentCompatibilityChange}
            onUploadImage={onUploadImage}
            saveStatus={saveStatus}
            value={state.document}
            onChange={(value) => set("document", value)}
          />
        </Suspense>
      </Field>
    </>
  );
}

function matchesImageCapability(file: File, formats: readonly string[]): boolean {
  const extension = file.name.toLowerCase().split(".").pop() ?? "";
  const allowedExtensions = IMAGE_EXTENSIONS_BY_MIME[file.type] ?? [];

  return formats.includes(file.type) && allowedExtensions.includes(extension);
}

function imageAcceptValue(formats: readonly string[]): string {
  const extensions = formats.flatMap((mime) =>
    (IMAGE_EXTENSIONS_BY_MIME[mime] ?? []).map((extension) => `.${extension}`),
  );

  return [...formats, ...extensions].join(",");
}

function imageFormatName(mime: string): string {
  return mime === "image/jpeg"
    ? "JPG/JPEG"
    : mime === "image/png"
      ? "PNG"
      : mime === "image/webp"
        ? "WebP"
        : mime;
}

function formatFileSize(bytes: number): string {
  const megabytes = bytes / (1024 * 1024);
  return `${Number.isInteger(megabytes) ? megabytes : megabytes.toFixed(1)} MB`;
}

function ManagedCoverPreview({
  attachment,
  canRemove,
  removing,
  onRemove,
}: {
  attachment: ContentAttachment;
  canRemove: boolean;
  removing: boolean;
  onRemove: () => void;
}) {
  const { t } = useTranslation("content");
  const [unavailable, setUnavailable] = useState(false);

  return (
    <div className="overflow-hidden rounded-xl border">
      <div className="relative aspect-video bg-gradient-to-br from-sky-950 via-primary to-cyan-700">
        {!unavailable ? (
          <AuthenticatedContentCover
            publicId={attachment.public_id}
            alt={attachment.alt_text ?? attachment.filename}
            className="absolute inset-0 h-full w-full object-cover"
            onUnavailable={() => setUnavailable(true)}
          />
        ) : (
          <div className="absolute inset-0 flex items-center justify-center p-4 text-center text-sm text-white">
            <ImageOff className="mr-2 h-5 w-5" aria-hidden="true" />
            {t("coverPreviewUnavailable")}
          </div>
        )}
      </div>
      <div className="flex min-w-0 items-center gap-2 border-t p-3 text-sm">
        <span className="min-w-0 flex-1 truncate">{attachment.filename}</span>
        {canRemove ? (
          <Button
            aria-label={`${t("remove")} ${attachment.filename}`}
            className="h-11 w-11 text-destructive"
            disabled={removing}
            onClick={onRemove}
            size="icon"
            type="button"
            variant="ghost"
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        ) : null}
      </div>
    </div>
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
        <Select
          disabled={!editable}
          value={state.categoryPublicId ?? ""}
          onValueChange={(value) => set("categoryPublicId", value === "none" ? null : value)}
        >
          <SelectTrigger className="h-11 w-full">
            <SelectValue placeholder={t("noCategory")} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="none">{t("noCategory")}</SelectItem>
            {categories.map((category) => (
              <SelectItem key={category.public_id} value={category.public_id}>
                {category.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
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
      <Field label={t("serviceType")}><Input maxLength={150} disabled={!editable} value={state.serviceType} onChange={(event) => set("serviceType", event.target.value)} /></Field>
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
      <Field label={t("procedure")}><Textarea maxLength={5000} disabled={!editable} value={state.procedure} onChange={(event) => set("procedure", event.target.value)} /></Field>
      <Field label={t("confidentialityInfo")}><Textarea maxLength={5000} disabled={!editable} value={state.confidentialityInfo} onChange={(event) => set("confidentialityInfo", event.target.value)} /></Field>
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
    <fieldset className="min-w-0 space-y-2 [&>input]:h-11 [&>textarea]:min-h-24">
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
  media,
  mobile,
  setMobile,
  categoryName,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  state: EditorState;
  contentType: ContentType;
  attachments: Array<{ public_id: string; filename: string }>;
  media: ContentAttachment[];
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
              {state.serviceType && <Badge variant="outline">{state.serviceType}</Badge>}
              <p>{state.description}</p>
              {state.email && <p>{state.email}</p>}
              {state.phone && <p>{state.phone}</p>}
              {state.whatsapp && <p>WhatsApp: {state.whatsapp}</p>}
              {state.officeAddress && <p>{state.officeAddress}</p>}
              {state.operatingHours && <p>{state.operatingHours}</p>}
              {state.procedure && <p className="whitespace-pre-line">{state.procedure}</p>}
              {state.confidentialityInfo && <p className="whitespace-pre-line">{state.confidentialityInfo}</p>}
              {state.emergencyAvailable && (
                <Badge variant="destructive">{t("emergencyAvailable")}</Badge>
              )}
              {state.appointmentUrl && state.actionLabel && isValidUrl(state.appointmentUrl) && (
                <Button asChild className="mt-2">
                  <a href={state.appointmentUrl} target="_blank" rel="noreferrer noopener">
                    {state.actionLabel}
                  </a>
                </Button>
              )}
            </div>
          ) : (
            <ContentDocumentPreview document={document} media={media} />
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
  const articleCategory = articleCategoryEditorState(detail);
  return {
    sectionCode: detail?.section.code ?? (contentType === "article" ? "education" : contentType),
    categoryPublicId:
      contentType === "article"
        ? articleCategory.categoryPublicId
        : (detail?.category?.public_id ?? null),
    categoryName: articleCategory.categoryName,
    title: detail?.version.title ?? "",
    excerpt: detail?.version.excerpt ?? "",
    document: article?.document ?? null,
    coverAltText: article?.cover_alt_text ?? "",
    question: faq?.question ?? "",
    answerDocument: faq?.answer_document ?? null,
    displayOrder: String(faq?.display_order ?? 0),
    serviceName: consultation?.service_name ?? "",
    description: consultation?.description ?? "",
    serviceType: consultation?.service_type ?? "",
    email: consultation?.email ?? "",
    phone: consultation?.phone_display ?? "",
    whatsapp: consultation?.whatsapp_display ?? "",
    officeAddress: consultation?.office_address ?? "",
    operatingHours: consultation?.operating_hours ?? "",
    procedure: consultation?.procedure ?? "",
    confidentialityInfo: consultation?.confidentiality_info ?? "",
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
  scope: "campus" | "global",
  universityId?: number | null,
  lockVersion?: number,
): ContentPayload {
  const common: ContentPayload = {
    content_type: type,
    section_code: state.sectionCode,
    category_public_id: state.categoryPublicId || null,
    scope,
    university_id: scope === "campus" ? (universityId ?? undefined) : undefined,
    lock_version: lockVersion,
  };
  if (type === "article")
    return {
      ...common,
      ...articleCategoryWriteFields(state.categoryName, state.categoryPublicId),
      title: state.title.trim(),
      excerpt: state.excerpt.trim() || null,
      cover_alt_text: state.coverAltText.trim() || null,
      document: state.document ?? undefined,
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
    service_type: state.serviceType.trim() || null,
    email: state.email.trim() || null,
    phone_display: state.phone.trim() || null,
    whatsapp_display: state.whatsapp.trim() || null,
    office_address: state.officeAddress.trim() || null,
    operating_hours: state.operatingHours.trim() || null,
    procedure: state.procedure.trim() || null,
    confidentiality_info: state.confidentialityInfo.trim() || null,
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
    if (!state.categoryName.trim()) errors.categoryName = t("content:validation.category");
    else if (state.categoryName.trim().length > 100) errors.categoryName = t("content:validation.categoryLength");
    else if (!/[\p{L}\p{N}]/u.test(state.categoryName.trim())) errors.categoryName = t("content:validation.categoryFormat");
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
function apiFieldErrors(error: unknown, contentType: ContentType): Record<string, string> {
  return error instanceof ApiError && error.errors
    ? Object.fromEntries(
        Object.entries(error.errors).map(([key, value]) => [
          contentFieldName(contentType, key),
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

function contentImageUploadError(
  error: unknown,
  t: ReturnType<typeof useTranslation>["t"],
  sourceMaxBytes: number,
  storedMaxBytes: number,
) {
  if (error instanceof ClientValidationError) return error.message;
  if (error instanceof ApiError) {
    if (error.status === 413) return t("content:validation.imageRequestTooLarge", { size: formatFileSize(sourceMaxBytes) });
    if (error.status === 422) {
      const message = error.errors?.file?.[0]?.toLowerCase() ?? "";
      if (message.includes("source image") || message.includes("processing size")) {
        return t("content:validation.cover", { formats: "JPG/JPEG, PNG, WebP", size: formatFileSize(sourceMaxBytes) });
      }
      if (message.includes("optimized image")) {
        return t("content:validation.imageOutputTooLarge", { size: formatFileSize(storedMaxBytes) });
      }
      if (message.includes("dimension") || message.includes("memory")) return t("content:validation.imageDimensions");
      return t("content:validation.imageProcessing");
    }
  }

  return apiErrorMessage(error, t("content:loadError"));
}
