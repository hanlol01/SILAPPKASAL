import type { Editor, JSONContent } from "@tiptap/core";
import { EditorContent, useEditor, useEditorState } from "@tiptap/react";
import {
  AlertTriangle,
  Bold,
  CircleHelp,
  Eraser,
  ImagePlus,
  Info,
  Italic,
  Link2,
  List,
  ListOrdered,
  Loader2,
  Minus,
  Quote,
  Redo2,
  Underline,
  Undo2,
  Unlink,
} from "lucide-react";
import {
  type FormEvent,
  type ReactNode,
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
} from "react";
import { useTranslation } from "react-i18next";

import {
  articleEditorSchema,
} from "@/components/content/article-editor-extensions";
import { articleEditorMediaExtensions } from "@/components/content/article-editor-media-extensions";
import { ContentDocumentPreview } from "@/components/content/content-document-preview";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import {
  articleDocumentFromTiptap,
  collectAuthorizedImageReferences,
  countArticleWords,
  documentSignature,
  prepareArticleDocumentForTiptap,
} from "@/lib/article-document-tiptap";
import { normalizeSafeContentLink } from "@/lib/content-document";
import type { ContentAttachment, DocumentNode } from "@/lib/content-management-api";
import { cn } from "@/lib/utils";

export type ArticleEditorSaveStatus = "pristine" | "dirty" | "saved" | "failed";

interface ArticleWysiwygEditorProps {
  value: DocumentNode | null;
  disabled?: boolean;
  saveStatus?: ArticleEditorSaveStatus;
  onCompatibilityChange?: (compatible: boolean) => void;
  onUploadImage?: (file: File, altText: string) => Promise<ContentAttachment>;
  imageFormats?: string[];
  imageMaxBytes?: number;
  imageAltMaxLength?: number;
  onChange: (document: DocumentNode) => void;
}

const DEFAULT_IMAGE_FORMATS = ["image/jpeg", "image/png", "image/webp"];
const DEFAULT_IMAGE_MAX_BYTES = 10 * 1024 * 1024;
const DEFAULT_IMAGE_ALT_MAX_LENGTH = 500;
const IMAGE_EXTENSIONS_BY_MIME: Record<string, readonly string[]> = {
  "image/jpeg": ["jpg", "jpeg"],
  "image/png": ["png"],
  "image/webp": ["webp"],
};

const EMPTY_TOOLBAR_STATE = {
  bold: false,
  italic: false,
  underline: false,
  link: false,
  blockquote: false,
  bulletList: false,
  orderedList: false,
  headingLevel: 0,
  calloutVariant: "",
  canUndo: false,
  canRedo: false,
  text: "",
  empty: true,
};

export function ArticleWysiwygEditor({
  value,
  disabled = false,
  saveStatus = "pristine",
  onCompatibilityChange,
  onUploadImage,
  imageFormats = DEFAULT_IMAGE_FORMATS,
  imageMaxBytes = DEFAULT_IMAGE_MAX_BYTES,
  imageAltMaxLength = DEFAULT_IMAGE_ALT_MAX_LENGTH,
  onChange,
}: ArticleWysiwygEditorProps) {
  const { t } = useTranslation("content");
  const localDocumentRef = useRef<DocumentNode | null>(null);
  const compatibility = useMemo(() => {
    if (value !== null && value === localDocumentRef.current) {
      return {
        document: value,
        unsupported: [],
        failureKind: null,
      };
    }

    const prepared = prepareArticleDocumentForTiptap(value);
    if (prepared.unsupported.length > 0) return prepared;

    try {
      articleEditorSchema.nodeFromJSON(prepared.document).check();
      return prepared;
    } catch {
      return {
        document: prepared.document,
        unsupported: ["document:schema"],
      };
    }
  }, [value]);

  useEffect(() => {
    onCompatibilityChange?.(compatibility.unsupported.length === 0);
  }, [compatibility.unsupported.length, onCompatibilityChange]);

  if (compatibility.unsupported.length > 0) {
    return (
      <div className="space-y-3">
        <Alert variant="destructive">
          <AlertTriangle className="h-4 w-4" />
          <AlertTitle>{t("editor.incompatibleTitle")}</AlertTitle>
          <AlertDescription>{t("editor.incompatibleDescription")}</AlertDescription>
        </Alert>
        <div className="rounded-xl border bg-muted/20 p-4">
          <ContentDocumentPreview document={value} />
        </div>
      </div>
    );
  }

  return (
    <CompatibleArticleEditor
      disabled={disabled}
      document={compatibility.document}
      imageFormats={imageFormats}
      imageMaxBytes={imageMaxBytes}
      imageAltMaxLength={imageAltMaxLength}
      onUploadImage={onUploadImage}
      saveStatus={saveStatus}
      onChange={(document) => {
        localDocumentRef.current = document;
        onChange(document);
      }}
    />
  );
}

function CompatibleArticleEditor({
  document,
  disabled,
  saveStatus,
  onUploadImage,
  imageFormats,
  imageMaxBytes,
  imageAltMaxLength,
  onChange,
}: {
  document: DocumentNode;
  disabled: boolean;
  saveStatus: ArticleEditorSaveStatus;
  onUploadImage?: (file: File, altText: string) => Promise<ContentAttachment>;
  imageFormats: string[];
  imageMaxBytes: number;
  imageAltMaxLength: number;
  onChange: (document: DocumentNode) => void;
}) {
  const { t } = useTranslation("content");
  const onChangeRef = useRef(onChange);
  const acceptedDocumentRef = useRef(document);
  const authorizedImageReferencesRef = useRef(
    new Map(collectAuthorizedImageReferences(document)),
  );
  const lastEmittedSignature = useRef(documentSignature(document));
  const [linkOpen, setLinkOpen] = useState(false);
  const [linkHref, setLinkHref] = useState("");
  const [linkTitle, setLinkTitle] = useState("");
  const [linkError, setLinkError] = useState("");
  const [blockedMediaMessage, setBlockedMediaMessage] = useState("");
  const [imageOpen, setImageOpen] = useState(false);
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imageAlt, setImageAlt] = useState("");
  const [imageError, setImageError] = useState("");
  const [imageUploading, setImageUploading] = useState(false);
  const imageInputId = useId();

  useEffect(() => {
    onChangeRef.current = onChange;
  }, [onChange]);

  useEffect(() => {
    acceptedDocumentRef.current = document;
    authorizedImageReferencesRef.current = new Map(
      collectAuthorizedImageReferences(document),
    );
  }, [document]);

  const editor = useEditor({
    extensions: articleEditorMediaExtensions,
    content: document as JSONContent,
    editable: !disabled,
    immediatelyRender: false,
    shouldRerenderOnTransaction: false,
    editorProps: {
      attributes: {
        "aria-label": t("editor.canvas"),
      },
      handlePaste: (_view, event) => {
        if (!clipboardContainsFile(event.clipboardData)) return false;
        setBlockedMediaMessage(t("editor.mediaBlocked"));
        return true;
      },
      handleDrop: (_view, event) => {
        if (!dataTransferContainsFile(event.dataTransfer)) return false;
        setBlockedMediaMessage(t("editor.mediaBlocked"));
        return true;
      },
    },
    onUpdate: ({ editor: currentEditor }) => {
      try {
        const nextDocument = articleDocumentFromTiptap(
          currentEditor.getJSON(),
          authorizedImageReferencesRef.current,
        );
        setBlockedMediaMessage("");
        lastEmittedSignature.current = documentSignature(nextDocument);
        onChangeRef.current(nextDocument);
      } catch {
        setBlockedMediaMessage(t("editor.invalidChangeBlocked"));
        currentEditor.commands.setContent(acceptedDocumentRef.current as JSONContent, {
          emitUpdate: false,
          errorOnInvalidContent: true,
        });
      }
    },
  });

  useEffect(() => {
    if (!editor) return;
    editor.setEditable(!disabled);
  }, [disabled, editor]);

  useEffect(() => {
    if (!editor) return;

    const nextSignature = documentSignature(document);
    if (
      nextSignature === lastEmittedSignature.current ||
      nextSignature ===
        documentSignature(
          articleDocumentFromTiptap(editor.getJSON(), authorizedImageReferencesRef.current),
        )
    ) {
      lastEmittedSignature.current = nextSignature;
      return;
    }

    editor.commands.setContent(document as JSONContent, {
      emitUpdate: false,
      errorOnInvalidContent: true,
    });
    lastEmittedSignature.current = nextSignature;
  }, [document, editor]);

  const toolbarState =
    useEditorState({
      editor,
      selector: ({ editor: currentEditor }) => {
        if (!currentEditor) return EMPTY_TOOLBAR_STATE;
        const headingLevel = currentEditor.isActive("heading", { level: 2 })
          ? 2
          : currentEditor.isActive("heading", { level: 3 })
            ? 3
            : 0;
        const calloutVariant = currentEditor.isActive("callout")
          ? String(currentEditor.getAttributes("callout").variant ?? "")
          : "";

        return {
          bold: currentEditor.isActive("bold"),
          italic: currentEditor.isActive("italic"),
          underline: currentEditor.isActive("underline"),
          link: currentEditor.isActive("link"),
          blockquote: currentEditor.isActive("blockquote"),
          bulletList: currentEditor.isActive("bulletList"),
          orderedList: currentEditor.isActive("orderedList"),
          headingLevel,
          calloutVariant,
          canUndo: currentEditor.can().chain().undo().run(),
          canRedo: currentEditor.can().chain().redo().run(),
          text: currentEditor.getText({ blockSeparator: " " }),
          empty: currentEditor.isEmpty,
        };
      },
    }) ?? EMPTY_TOOLBAR_STATE;

  const wordCount = countArticleWords(toolbarState.text);
  const characterCount = [...toolbarState.text].length;
  const readingMinutes = wordCount === 0 ? 0 : Math.max(1, Math.ceil(wordCount / 200));
  const controlsDisabled = disabled || !editor;

  const openLinkEditor = () => {
    if (!editor) return;
    const attrs = editor.getAttributes("link");
    setLinkHref(typeof attrs.href === "string" ? attrs.href : "");
    setLinkTitle(typeof attrs.title === "string" ? attrs.title : "");
    setLinkError("");
  };

  const applyLink = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!editor) return;

    const href = normalizeSafeContentLink(linkHref);
    const title = linkTitle.trim();
    if (href === null) {
      setLinkError(t("editor.linkInvalid"));
      return;
    }

    editor
      .chain()
      .focus()
      .extendMarkRange("link")
      .setLink({
        href,
        title: title || null,
      })
      .run();
    setLinkOpen(false);
    setLinkError("");
  };

  const removeLink = () => {
    editor?.chain().focus().extendMarkRange("link").unsetLink().run();
    setLinkOpen(false);
    setLinkError("");
  };

  const uploadImage = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!editor || !onUploadImage || imageUploading) return;
    const altText = imageAlt.trim();
    const extension = imageFile?.name.toLowerCase().split(".").pop() ?? "";
    const validType =
      imageFile !== null &&
      imageFormats.includes(imageFile.type) &&
      (IMAGE_EXTENSIONS_BY_MIME[imageFile.type] ?? []).includes(extension);

    if (!imageFile || !validType || imageFile.size < 1 || imageFile.size > imageMaxBytes) {
      setImageError(t("editor.imageInvalid", {
        formats: imageFormats.map(imageFormatName).join(", "),
        size: formatFileSize(imageMaxBytes),
      }));
      return;
    }
    if (!altText || [...altText].length > imageAltMaxLength) {
      setImageError(t("editor.imageAltRequired", { max: imageAltMaxLength }));
      return;
    }

    setImageUploading(true);
    setImageError("");
    try {
      const attachment = await onUploadImage(imageFile, altText);
      if (
        attachment.purpose !== "inline_image" ||
        !attachment.mime_type.startsWith("image/")
      ) {
        throw new Error("The server did not return a validated inline image.");
      }

      const imageNode: DocumentNode = {
        type: "imageReference",
        attrs: {
          attachment_public_id: attachment.public_id,
          alt: attachment.alt_text ?? altText,
        },
      };
      const trustedReference = collectAuthorizedImageReferences({
        type: "doc",
        content: [imageNode],
      });
      for (const [signature, reference] of trustedReference) {
        authorizedImageReferencesRef.current.set(signature, reference);
      }

      const inserted = editor.chain().focus().insertContent(imageNode).run();
      if (!inserted) {
        throw new Error("The uploaded image could not be inserted.");
      }

      setImageOpen(false);
      setImageFile(null);
      setImageAlt("");
      setBlockedMediaMessage("");
    } catch {
      setImageError(t("editor.imageUploadError"));
    } finally {
      setImageUploading(false);
    }
  };

  const blockFormat =
    toolbarState.headingLevel === 2 ? "h2" : toolbarState.headingLevel === 3 ? "h3" : "paragraph";

  return (
    <TooltipProvider delayDuration={300}>
      <div
        className={cn(
          "article-wysiwyg-editor min-w-0 rounded-xl border bg-background shadow-sm",
          disabled && "bg-muted/20",
        )}
      >
        <div
          aria-label={t("editor.toolbar")}
          className="sticky top-0 z-10 flex max-w-full flex-nowrap items-center gap-1 overflow-x-auto rounded-t-xl border-b bg-background/95 p-2 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-background/85"
          role="toolbar"
        >
          <ToolbarButton
            disabled={controlsDisabled || !toolbarState.canUndo}
            label={t("editor.undo")}
            onClick={() => editor?.chain().focus().undo().run()}
          >
            <Undo2 />
          </ToolbarButton>
          <ToolbarButton
            disabled={controlsDisabled || !toolbarState.canRedo}
            label={t("editor.redo")}
            onClick={() => editor?.chain().focus().redo().run()}
          >
            <Redo2 />
          </ToolbarButton>

          <ToolbarSeparator />

          <Select
            disabled={controlsDisabled}
            value={blockFormat}
            onValueChange={(next) => {
              if (next === "h2") {
                editor?.chain().focus().setHeading({ level: 2 }).run();
              } else if (next === "h3") {
                editor?.chain().focus().setHeading({ level: 3 }).run();
              } else {
                editor?.chain().focus().setParagraph().run();
              }
            }}
          >
            <SelectTrigger aria-label={t("editor.blockStyle")} className="h-9 w-36 bg-background">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="paragraph">{t("editor.paragraph")}</SelectItem>
              <SelectItem value="h2">{t("editor.h2")}</SelectItem>
              <SelectItem value="h3">{t("editor.h3")}</SelectItem>
            </SelectContent>
          </Select>

          <ToolbarSeparator />

          <ToolbarButton
            active={toolbarState.bold}
            disabled={controlsDisabled}
            label={t("editor.bold")}
            onClick={() => editor?.chain().focus().toggleBold().run()}
          >
            <Bold />
          </ToolbarButton>
          <ToolbarButton
            active={toolbarState.italic}
            disabled={controlsDisabled}
            label={t("editor.italic")}
            onClick={() => editor?.chain().focus().toggleItalic().run()}
          >
            <Italic />
          </ToolbarButton>
          <ToolbarButton
            active={toolbarState.underline}
            disabled={controlsDisabled}
            label={t("editor.underline")}
            onClick={() => editor?.chain().focus().toggleUnderline().run()}
          >
            <Underline />
          </ToolbarButton>
          <ToolbarButton
            disabled={controlsDisabled}
            label={t("editor.clearFormatting")}
            onClick={() => editor?.chain().focus().unsetAllMarks().clearNodes().run()}
          >
            <Eraser />
          </ToolbarButton>

          <Popover
            open={linkOpen}
            onOpenChange={(open) => {
              setLinkOpen(open);
              if (open) openLinkEditor();
              else setLinkError("");
            }}
          >
            <Tooltip>
              <TooltipTrigger asChild>
                <PopoverTrigger asChild>
                  <Button
                    aria-label={t("editor.link")}
                    aria-pressed={toolbarState.link}
                    className="h-9 w-9"
                    disabled={controlsDisabled}
                    size="icon"
                    type="button"
                    variant={toolbarState.link ? "secondary" : "ghost"}
                  >
                    <Link2 />
                  </Button>
                </PopoverTrigger>
              </TooltipTrigger>
              <TooltipContent>{t("editor.link")}</TooltipContent>
            </Tooltip>
            <PopoverContent align="start" className="w-[min(20rem,calc(100vw-2rem))]">
              <form className="space-y-4" onSubmit={applyLink}>
                <div className="space-y-1.5">
                  <Label htmlFor="article-editor-link-href">{t("editor.linkUrl")}</Label>
                  <Input
                    id="article-editor-link-href"
                    maxLength={2048}
                    placeholder={t("editor.linkPlaceholder")}
                    value={linkHref}
                    onChange={(event) => {
                      setLinkHref(event.target.value);
                      setLinkError("");
                    }}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="article-editor-link-title">{t("editor.linkTitle")}</Label>
                  <Input
                    id="article-editor-link-title"
                    maxLength={255}
                    value={linkTitle}
                    onChange={(event) => setLinkTitle(event.target.value)}
                  />
                </div>
                {linkError ? (
                  <p className="text-sm text-destructive" role="alert">
                    {linkError}
                  </p>
                ) : null}
                <div className="flex flex-wrap justify-end gap-2">
                  {toolbarState.link ? (
                    <Button onClick={removeLink} type="button" variant="outline">
                      <Unlink />
                      {t("editor.removeLink")}
                    </Button>
                  ) : null}
                  <Button type="submit">{t("editor.applyLink")}</Button>
                </div>
              </form>
            </PopoverContent>
          </Popover>

          <ToolbarSeparator />

          {onUploadImage ? (
            <Popover
              open={imageOpen}
              onOpenChange={(open) => {
                setImageOpen(open);
                if (!open) {
                  setImageError("");
                  setImageFile(null);
                  setImageAlt("");
                }
              }}
            >
              <Tooltip>
                <TooltipTrigger asChild>
                  <PopoverTrigger asChild>
                    <Button
                      aria-label={t("editor.insertImage")}
                      className="h-9 w-9"
                      disabled={controlsDisabled || imageUploading}
                      size="icon"
                      type="button"
                      variant="ghost"
                    >
                      {imageUploading ? (
                        <Loader2 className="animate-spin motion-reduce:animate-none" />
                      ) : (
                        <ImagePlus />
                      )}
                    </Button>
                  </PopoverTrigger>
                </TooltipTrigger>
                <TooltipContent>{t("editor.insertImage")}</TooltipContent>
              </Tooltip>
              <PopoverContent align="start" className="w-[min(22rem,calc(100vw-2rem))]">
                <form className="space-y-4" onSubmit={(event) => void uploadImage(event)}>
                  <div className="space-y-1.5">
                    <Label htmlFor={imageInputId}>{t("editor.imageFile")}</Label>
                    <Input
                      accept={imageAcceptValue(imageFormats)}
                      disabled={imageUploading}
                      id={imageInputId}
                      type="file"
                      onChange={(event) => {
                        setImageFile(event.target.files?.[0] ?? null);
                        setImageError("");
                      }}
                    />
                    <p className="text-xs text-muted-foreground">
                      {t("editor.imageHelp", {
                        formats: imageFormats.map(imageFormatName).join(", "),
                        size: formatFileSize(imageMaxBytes),
                      })}
                    </p>
                  </div>
                  <div className="space-y-1.5">
                    <Label htmlFor={`${imageInputId}-alt`}>{t("editor.imageAlt")}</Label>
                    <Input
                      disabled={imageUploading}
                      id={`${imageInputId}-alt`}
                      maxLength={imageAltMaxLength}
                      value={imageAlt}
                      onChange={(event) => {
                        setImageAlt(event.target.value);
                        setImageError("");
                      }}
                    />
                  </div>
                  {imageError ? (
                    <p className="text-sm text-destructive" role="alert">
                      {imageError}
                    </p>
                  ) : null}
                  <Button className="w-full" disabled={imageUploading} type="submit">
                    {imageUploading ? (
                      <Loader2 className="animate-spin motion-reduce:animate-none" />
                    ) : (
                      <ImagePlus />
                    )}
                    {imageUploading ? t("editor.imageUploading") : t("editor.insertImage")}
                  </Button>
                </form>
              </PopoverContent>
            </Popover>
          ) : null}

          {onUploadImage ? <ToolbarSeparator /> : null}

          <ToolbarButton
            active={toolbarState.bulletList}
            disabled={controlsDisabled}
            label={t("editor.unorderedList")}
            onClick={() => editor?.chain().focus().toggleBulletList().run()}
          >
            <List />
          </ToolbarButton>
          <ToolbarButton
            active={toolbarState.orderedList}
            disabled={controlsDisabled}
            label={t("editor.orderedList")}
            onClick={() => editor?.chain().focus().toggleOrderedList().run()}
          >
            <ListOrdered />
          </ToolbarButton>
          <ToolbarButton
            active={toolbarState.blockquote}
            disabled={controlsDisabled}
            label={t("editor.blockquote")}
            onClick={() => editor?.chain().focus().toggleBlockquote().run()}
          >
            <Quote />
          </ToolbarButton>

          <ToolbarSeparator />

          <ToolbarButton
            active={toolbarState.calloutVariant === "information"}
            disabled={controlsDisabled}
            label={t("editor.information")}
            onClick={() => applyCallout(editor, "information")}
          >
            <Info />
          </ToolbarButton>
          <ToolbarButton
            active={toolbarState.calloutVariant === "warning"}
            disabled={controlsDisabled}
            label={t("editor.warning")}
            onClick={() => applyCallout(editor, "warning")}
          >
            <AlertTriangle />
          </ToolbarButton>
          <ToolbarButton
            active={toolbarState.calloutVariant === "help"}
            disabled={controlsDisabled}
            label={t("editor.help")}
            onClick={() => applyCallout(editor, "help")}
          >
            <CircleHelp />
          </ToolbarButton>
          <ToolbarButton
            disabled={controlsDisabled}
            label={t("editor.divider")}
            onClick={() =>
              editor?.chain().focus().insertContent({ type: "horizontalRule" }).run()
            }
          >
            <Minus />
          </ToolbarButton>
        </div>

        <div className="relative">
          {toolbarState.empty && !disabled ? (
            <p
              aria-hidden="true"
              className="pointer-events-none absolute left-6 top-6 z-[1] text-base text-muted-foreground/70"
            >
              {t("editor.placeholder")}
            </p>
          ) : null}
          <EditorContent
            aria-label={t("editor.canvas")}
            className={cn(!editor && "min-h-72 animate-pulse bg-muted/30")}
            editor={editor}
          />
        </div>

        {blockedMediaMessage ? (
          <p
            className="border-t bg-destructive/5 px-3 py-2 text-xs text-destructive"
            role="alert"
          >
            {blockedMediaMessage}
          </p>
        ) : null}

        <div className="flex flex-wrap items-center justify-between gap-2 rounded-b-xl border-t bg-muted/20 px-3 py-2 text-xs text-muted-foreground">
          <span>{t("editor.keyboardHint")}</span>
          <span className="flex flex-wrap items-center justify-end gap-x-3 gap-y-1" aria-live="polite">
            <span>{t(`editor.status.${saveStatus}`)}</span>
            <span>
              {t("editor.count", {
                words: wordCount,
                characters: characterCount,
              })}
            </span>
            <span>{t("editor.readingTime", { minutes: readingMinutes })}</span>
          </span>
        </div>
      </div>
    </TooltipProvider>
  );
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

function ToolbarButton({
  active = false,
  children,
  disabled,
  label,
  onClick,
}: {
  active?: boolean;
  children: ReactNode;
  disabled?: boolean;
  label: string;
  onClick: () => void;
}) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          aria-label={label}
          aria-pressed={active}
          className="h-9 w-9"
          disabled={disabled}
          onClick={onClick}
          size="icon"
          type="button"
          variant={active ? "secondary" : "ghost"}
        >
          {children}
        </Button>
      </TooltipTrigger>
      <TooltipContent>{label}</TooltipContent>
    </Tooltip>
  );
}

function ToolbarSeparator() {
  return (
    <Separator aria-hidden="true" className="mx-0.5 hidden h-6 sm:block" orientation="vertical" />
  );
}

function applyCallout(editor: Editor | null, variant: "information" | "warning" | "help") {
  if (!editor) return;

  if (!editor.isActive("callout")) {
    editor.chain().focus().wrapIn("callout", { variant }).run();
    return;
  }

  const activeVariant = String(editor.getAttributes("callout").variant ?? "");
  if (activeVariant === variant) {
    editor.chain().focus().lift("callout").run();
    return;
  }

  editor.chain().focus().updateAttributes("callout", { variant }).run();
}

function clipboardContainsFile(data: DataTransfer | null): boolean {
  return Boolean(
    data && (data.files.length > 0 || [...data.items].some((item) => item.kind === "file")),
  );
}

function dataTransferContainsFile(data: DataTransfer | null): boolean {
  return Boolean(
    data && (data.files.length > 0 || [...data.items].some((item) => item.kind === "file")),
  );
}
