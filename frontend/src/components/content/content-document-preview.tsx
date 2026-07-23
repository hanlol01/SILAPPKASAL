import { AlertTriangle, ImageOff } from "lucide-react";
import { type ReactNode, useCallback, useState } from "react";
import { useTranslation } from "react-i18next";

import { AuthenticatedContentImage } from "@/components/content/authenticated-content-image";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { normalizeSafeContentLink } from "@/lib/content-document";
import type { ContentAttachment, DocumentNode } from "@/lib/content-management-api";

export function ContentDocumentPreview({
  document,
  media,
}: {
  document: DocumentNode | null;
  media?: ContentAttachment[];
}) {
  const { t } = useTranslation("content");
  if (document && !isSafePreviewDocument(document)) {
    return (
      <Alert variant="destructive">
        <AlertTriangle className="h-4 w-4" />
        <AlertDescription>{t("editor.previewUnavailable")}</AlertDescription>
      </Alert>
    );
  }

  return (
    <div className="prose prose-slate max-w-none dark:prose-invert">
      {document?.content?.map((node, index) => (
        <PreviewNode
          depth={1}
          imageFallback={t("editor.imageLoadError")}
          key={index}
          media={media}
          node={node}
        />
      ))}
    </div>
  );
}

function PreviewNode({
  node,
  depth,
  imageFallback,
  media,
}: {
  node: DocumentNode;
  depth: number;
  imageFallback: string;
  media?: ContentAttachment[];
}) {
  if (depth > 12) return null;
  if (node.type === "text") {
    let content: ReactNode = node.text ?? "";
    for (const mark of node.marks ?? []) {
      if (mark.type === "bold") content = <strong>{content}</strong>;
      if (mark.type === "italic") content = <em>{content}</em>;
      if (mark.type === "underline") content = <u>{content}</u>;
      if (mark.type === "link") {
        const href = normalizeSafeContentLink(mark.attrs?.href ?? "");
        if (href) {
          const external = /^https?:/iu.test(href);
          content = (
            <a
              href={href}
              rel={external ? "noopener noreferrer" : undefined}
              target={external ? "_blank" : undefined}
              title={mark.attrs?.title}
            >
              {content}
            </a>
          );
        }
      }
    }
    return <>{content}</>;
  }
  const children = node.content?.map((child, index) => (
    <PreviewNode
      depth={depth + 1}
      imageFallback={imageFallback}
      key={index}
      media={media}
      node={child}
    />
  ));
  if (node.type === "paragraph") return <p>{children}</p>;
  if (node.type === "heading" || node.type === "heading_2" || node.type === "heading_3")
    return node.type === "heading_3" || node.attrs?.level === 3 ? (
      <h3>{children}</h3>
    ) : (
      <h2>{children}</h2>
    );
  if (node.type === "orderedList") return <ol>{children}</ol>;
  if (node.type === "unorderedList" || node.type === "bulletList") return <ul>{children}</ul>;
  if (node.type === "listItem") return <li>{children}</li>;
  if (node.type === "blockquote") return <blockquote>{children}</blockquote>;
  if (node.type === "divider" || node.type === "horizontalRule") return <hr />;
  if (
    node.type === "callout" ||
    node.type === "info" ||
    node.type === "warning" ||
    node.type === "help"
  ) {
    const variant =
      node.type === "info"
        ? "information"
        : node.type === "warning" || node.type === "help"
          ? node.type
          : String(node.attrs?.variant ?? "information");
    return (
      <aside
        className="my-4 rounded-lg border-l-4 border-primary bg-muted p-4"
        data-callout={variant}
      >
        {children}
      </aside>
    );
  }
  if (node.type === "imageReference") {
    const alt = typeof node.attrs?.alt === "string" ? node.attrs.alt : "";
    const publicId =
      typeof node.attrs?.attachment_public_id === "string"
        ? node.attrs.attachment_public_id
        : "";
    const attachment = media?.find(
      (candidate) =>
        candidate.public_id === publicId &&
        candidate.purpose === "inline_image" &&
        candidate.mime_type.startsWith("image/"),
    );
    return (
      <PreviewImage
        alt={alt}
        fallback={imageFallback}
        publicId={media === undefined ? publicId : (attachment?.public_id ?? "")}
      />
    );
  }
  return null;
}

function PreviewImage({
  publicId,
  alt,
  fallback,
}: {
  publicId: string;
  alt: string;
  fallback: string;
}) {
  const [unavailable, setUnavailable] = useState(false);
  const markUnavailable = useCallback(() => setUnavailable(true), []);

  return (
    <figure className="my-6 overflow-hidden rounded-xl border bg-muted/30">
      <div className="relative flex min-h-48 items-center justify-center bg-gradient-to-br from-sky-950 via-primary to-cyan-700">
        {!unavailable && publicId ? (
          <AuthenticatedContentImage
            publicId={publicId}
            alt={alt}
            className="max-h-[40rem] w-full object-contain"
            onUnavailable={markUnavailable}
          />
        ) : (
          <div className="flex items-center gap-2 p-6 text-sm text-white">
            <ImageOff aria-hidden="true" className="h-5 w-5 shrink-0" />
            <span>{alt || fallback}</span>
          </div>
        )}
      </div>
      {alt ? (
        <figcaption className="border-t bg-background px-4 py-2 text-sm text-muted-foreground">
          {alt}
        </figcaption>
      ) : null}
    </figure>
  );
}

function isSafePreviewDocument(document: DocumentNode): boolean {
  if (document.type !== "doc" || !Array.isArray(document.content)) return false;

  const state = { nodes: 0 };
  const visit = (node: DocumentNode | null | undefined, depth: number): boolean => {
    state.nodes += 1;
    if (state.nodes > 1000 || depth > 12) return false;
    if (!node || typeof node !== "object" || Array.isArray(node)) return false;
    if ((depth === 0) !== (node.type === "doc")) return false;
    if (node.content !== undefined && !Array.isArray(node.content)) return false;
    if (node.marks !== undefined && !Array.isArray(node.marks)) return false;
    if (
      ![
        "doc",
        "text",
        "paragraph",
        "heading",
        "heading_2",
        "heading_3",
        "orderedList",
        "unorderedList",
        "bulletList",
        "listItem",
        "blockquote",
        "callout",
        "info",
        "warning",
        "help",
        "divider",
        "horizontalRule",
        "imageReference",
      ].includes(node.type)
    ) {
      return false;
    }
    if (node.type === "heading" && node.attrs?.level !== 2 && node.attrs?.level !== 3) return false;
    if (
      node.type === "callout" &&
      !["information", "warning", "help"].includes(String(node.attrs?.variant))
    ) {
      return false;
    }
    if (node.type === "text") {
      if (typeof node.text !== "string" || node.text.length > 20000) return false;
      for (const mark of node.marks ?? []) {
        if (!mark || typeof mark !== "object" || Array.isArray(mark)) return false;
        if (!["bold", "italic", "underline", "link"].includes(mark.type)) return false;
        if (
          mark.type === "link" &&
          normalizeSafeContentLink(mark.attrs?.href ?? "") === null
        ) {
          return false;
        }
      }
    }

    return (node.content ?? []).every((child) => visit(child, depth + 1));
  };

  return visit(document, 0);
}
